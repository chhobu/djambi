<?php
class DjambiBattlefield {
  private $id,
          $scheme,
          $cells =array(),
          $factions = array(),
          $moves = array(),
          $mode,
          $status,
          $turns = array(),
          $play_order,
          $events = array(),
          $options = array(),
          $infos = array(),
          $rules = array(),
          $living_factions_at_turn_begin = array(),
          $summary = array(),
          $disposition,
          $displayed_turn_id,
          $habilities_store = array(),
          $game_manager;

  /**
   * Construction de l'objet DjambiBattlefield
   * @param String $id : identifiant
   * @param array $data : informations sur la partie en cours ou à créer
   * @return DjambiBattlefield
   */
  public function __construct($data) {
    if (!isset($data['id']) || !isset($data['mode']) || !isset($data['disposition'])) {
      throw new Exception('Error during DjambiBattlefield object creation : bad data argument.');
    }
    $this->id = $data['id'];
    $this->setDefaultOptions();
    $this->moves = array();
    $this->events = array();
    $this->summary = array();
    if (isset($data['is_new']) && $data['is_new']) {
      unset($data['is_new']);
      if (isset($data['sequence'])) {
        $this->setInfo('sequence', $data['sequence']);
      }
      $this->setDisposition($data['disposition']);
      $this->setMode($data['mode']);
      return $this->createNewGame($data);
    }
    else {
      return $this->loadBattlefield($data);
    }
  }

  // ------------------------------------------------------------
  // ------------------ FONCTIONS STATIQUES ---------------------
  // ------------------------------------------------------------

  /**
   * Retourne le nom d'une case à partir d'un tableau de coordonnées
   * @param array $position : tableeau contenant des clés 'x' et 'y'
   * @return string
   */
  public static function locateCell($position) {
    if (!isset($position['x']) || !isset($position['y'])) {
      throw new Exception('Bad argument');
    }
    return DjambiBattlefield::locateCellByXY($position["x"], $position["y"]);
  }

  /**
   * Renvoie le nom d'une case à partir de ses coordonnées
   * @param int $x
   * @param int $y
   * @return string
   */
  public static function locateCellByXY($x, $y) {
    return DjambiBattlefield::intToAlpha($x) . $y;
  }

  /**
   * Convertit une coordonnée en code alphabétique
   * @param int $int
   * @param boolean $inverse
   * @return string
   */
  public static function intToAlpha($int, $inverse = FALSE) {
    if ($int < 0 || $int > 1000) {
      throw new Exception('Bad argument : ' . $int);
    }
    static $alpha = array("A", "B", "C", "D", "E", "F", "G", "H", "I", "J", "K",
        "L", "M", "N", "O", "P", "Q", "R", "S", "T", "U", "V", "W", "X", "Y", "Z");
    $alpha_size = count($alpha);
    if ($int > $alpha_size) {
      $j = 1;
      for ($i = $alpha_size ; $i < $int; $i++) {
        if ($i - $j * $alpha_size >= $alpha_size) {
          $j++;
        }
        $alpha[] = $alpha[$j - 1] . $alpha[($i - $j * $alpha_size)];
      }
    }
    if ($inverse) {
      return array_search($int, $alpha);
    }
    if (isset($alpha[$int -1])) {
      return $alpha[$int - 1];
    }
    return '#';
  }

  // ------------------------------------------------------------
  // ------------- CREATION / CHARGEMENT D'UNE PARTIE -----------
  // ------------------------------------------------------------

  private function createNewGame($data) {
    $user_id = isset($data['user_id']) ? $data['user_id'] : 0;
    $user_cookie = isset($data['user_cookie']) ? $data['user_cookie'] : NULL;
    if (isset($data['computers'])) {
      $computers_classes = $data['computers'];
    }
    $dispositions = DjambiGameManager::getDispositions();
    $disposition = $dispositions[$this->disposition];
    // Construction des factions
    if (isset($disposition['sides'])) {
      $players = $disposition['sides'];
    }
    else {
      $players = array_fill(1, $disposition['nb'], 'playable');
    }
    $players_info = array();
    $ready = TRUE;
    foreach ($players as $key => $player) {
      switch ($player) {
        case('playable'):
          if ($this->mode == KW_DJAMBI_MODE_SANDBOX || ($key == 1 && in_array($this->mode, array(KW_DJAMBI_MODE_FRIENDLY, KW_DJAMBI_MODE_TRAINING)))) {
            $user_data['ip'] = function_exists('ip_address') ? ip_address() : $_SERVER['REMOTE_ADDR'];
            if ($this->mode != KW_DJAMBI_MODE_SANDBOX) {
              $user_data['ping'] = $user_data['joined'] = time();
            }
            $players_info[$key] = array(
                'uid' => $user_id,
                'data' => $user_data,
                'status' => KW_DJAMBI_USER_READY,
                'cookie' => $user_cookie,
                'human' => TRUE,
                'ia' => FALSE
            );
          }
          else {
            if ($this->mode == KW_DJAMBI_MODE_FRIENDLY) {
              $players_info[$key] = array(
                'uid' => 0,
                'data' => array(),
                'status' => KW_DJAMBI_USER_EMPTY_SLOT,
                'cookie' => NULL,
                'human' => TRUE,
                'ia' => NULL
              );
              $ready = FALSE;
            }
            else {
              $class_candidate = NULL;
              if (isset($computers_classes)) {
                if (is_array($computers_classes) && isset($computers_classes[$key])) {
                  $class_candidate = $computers_classes[$key];
                }
                else {
                  $class_candidate = $computers_classes;
                }
              }
              if (!is_null($class_candidate) && class_exists($class_candidate) && in_array($class_candidate, class_parents($class_candidate))) {
                $computer_class = $class_candidate;
              }
              else {
                $computer_class = DjambiIA::getDefaultIAClass();
              }
              $players_info[$key] = array(
                  'uid' => 0,
                  'data' => array(),
                  'status' => KW_DJAMBI_USER_READY,
                  'cookie' => NULL,
                  'human' => FALSE,
                  'ia' => $computer_class
              );
            }
          }
          break;
        case('vassal'):
          $players_info[$key] = array(
            'uid' => 0,
            'data' => array(),
            'status' => KW_DJAMBI_USER_VASSALIZED,
            'cookie' => NULL,
            'human' => FALSE,
            'ia' => NULL
          );
          break;
      }
    }
    $this->setInfo('players_info', $players_info);
    $factions_data = DjambiPoliticalFaction::buildFactionsInfos();
    $nb_factions = count($players_info);
    $i = 0;
    foreach ($factions_data as $key => $faction_data) {
      $i++;
      if ($i > count($players_info)) {
        break;
      }
      $faction = new DjambiPoliticalFaction($this, $players_info[$i], $key, $faction_data);
      $this->factions[$i] = $faction;
    }
    // Placement initial des pièces
    $scheme_class = $disposition['scheme'];
    if (!class_exists($scheme_class) || !in_array('DjambiBattlefieldScheme', class_parents($scheme_class))) {
      throw new Exception('Unknown battlefield scheme.');
    }
    /* @var $scheme DjambiBattlefieldScheme */
    $scheme = new $scheme_class(isset($disposition['scheme_settings']) ? $disposition['scheme_settings'] : array());
    $this->setScheme($scheme);
    $this->buildField();
    $directions = $scheme->getDirections();
    $scheme_sides = $scheme->getSides();
    $cells = $this->getCells();
    foreach ($this->factions as $i => $faction) {
      $start_order = $faction->getStartOrder();
      $current_side = current(array_slice($scheme_sides, $start_order - 1, 1));
      $leader_position = self::locateCell($current_side);
      $start_scheme = array();
      $axis = NULL;
      foreach ($directions as $orientation => $direction) {
        $next_cell = $leader_position;
        while (isset($cells[$next_cell]['neighbours'][$orientation])) {
          if ($cells[$next_cell]['type'] == 'throne') {
            $axis = $orientation;
            break;
          }
          $next_cell = $cells[$next_cell]['neighbours'][$orientation];
        }
        if (!empty($axis)) {
          break;
        }
      }
      if (empty($axis)) {
        throw new Exception('Bad pieces start scheme.');
      }
      /* @var $piece DjambiPieceDescription */
      foreach ($scheme->getPieceScheme() as $piece_id => $piece) {
        $start_position = $piece->getStartPosition();
        $starting_cell = $leader_position;
        for ($i = 0; $i < $start_position['y'] ; $i++) {
          $starting_cell = $cells[$starting_cell]['neighbours'][$axis];
        }
        if ($start_position['x'] > 0) {
          $new_axis = $directions[$axis]['right'];
        }
        else {
          $new_axis = $directions[$axis]['left'];
        }
        for ($i = 0; $i < abs($start_position['x']) ; $i++) {
          $starting_cell = $cells[$starting_cell]['neighbours'][$new_axis];
        }
        $start_scheme[$piece_id] = array('x' => $cells[$starting_cell]['x'], 'y' => $cells[$starting_cell]['y']);
      }
      $faction->createPieces($scheme->getPieceScheme(), $start_scheme);
    }
    $this->logEvent('info', 'NEW_DJAMBI_GAME');
    if ($ready) {
      $this->setStatus(KW_DJAMBI_STATUS_PENDING);
    }
    else {
      $this->setStatus(KW_DJAMBI_STATUS_RECRUITING);
    }
    $this->setInfo('changed', time());
    return $this;
  }

  private function loadBattlefield($data) {
    if (isset($data['scheme']) && class_exists($data['scheme']) && in_array('DjambiBattlefieldScheme', class_parents($data['scheme']))) {
      if (isset($data['scheme_settings'])) {
        $settings = $data['scheme_settings'];
      }
      else {
        $settings = array();
      }
      $scheme = new $data['scheme']($settings);
    }
    else {
      $special_cells = array();
      foreach ($data['special_cells'] as $cell => $type) {
        $special_cells[] = array('type' => $type, 'location' => $cell);
      }
      $scheme = new DjambiBattlefieldScheme(array(
          'disposition' => $data['options']['directions'],
          'rows' => $data['rows'],
          'cols' => $data['cols'],
          'special_cells' => $special_cells
      ));
    }
    $this->setScheme($scheme);
    $this->setMode($data['mode']);
    $this->setDisposition($data['disposition']);
    $this->setStatus($data['status']);
    $this->infos = isset($data['infos']) ? $data['infos'] : $this->infos;
    $this->moves = isset($data['moves']) ? $data['moves'] : $this->moves;
    $this->turns = isset($data['turns']) ? $data['turns'] : $this->turns;
    $this->points = isset($data['points']) ? $data['points'] : $this->points;
    $this->events = isset($data['events']) ? $data['events'] : $this->events;
    $this->summary = isset($data['summary']) ? $data['summary'] : $this->summary;
    $this->factions = array();
    if (isset($data['options']) && is_array($data['options'])) {
      foreach($data['options'] as $option => $value) {
        $this->setOption($option, $value);
      }
    }
    $this->buildField();
    $pieces_scheme = $scheme->getPieceScheme();
    $controls = array();
    foreach ($data['factions'] as $key => $faction_data) {
      $faction = new DjambiPoliticalFaction($this, $data['users'][$key], $key, $faction_data);
      $positions = array();
      foreach ($data['positions'] as $cell => $piece_id) {
        $piece_data = explode('-', $piece_id, 2);
        if ($piece_data[0] == $key) {
          $positions[$piece_data[1]] = $this->cells[$cell];
        }
      }
      $faction->setAlive($faction_data['alive']);
      $faction->createPieces($pieces_scheme, $positions, $data['deads']);
      $this->factions[] = $faction;
    }
    if (!empty($this->summary)) {
      $this->rebuildFactionsControls($this->summary[max(array_keys($this->summary))]);
    }
    return $this;
  }

  private function buildField() {
    $cells = array();
    $special_cells = $this->getScheme()->getSpecialCells();
    for ($x = 1; $x <= $this->getCols(); $x++) {
      for ($y = 1; $y <= $this->getRows(); $y++) {
        $cells[self::locateCellByXY($x, $y)] = array('x' => $x, 'y' => $y, 'type' => 'std', 'occupant' => NULL);
      }
    }
    foreach ($special_cells as $key => $description) {
      $cells[$description['location']]['type'] = $description['type'];
    }
    foreach ($cells as $key => $cell) {
      if ($cell['type'] == 'disabled') {
        continue;
      }
      $neighbours = array();
      foreach ($this->getScheme()->getDirections() as $d => $direction) {
        $new_x = $cell['x'] + $direction['x'];
        $new_y = $cell['y'] + $direction['y'];
        if (!empty($direction['modulo_x'])) {
          if ($cell['y'] % 2 == 1) {
            if (in_array($d, array('NE', 'SE'))) {
              $new_x = $cell['x'];
            }
          }
          else {
            if (in_array($d, array('NW', 'SW'))) {
              $new_x = $cell['x'];
            }
          }
        }
        $neighbour = self::locateCellByXY($new_x, $new_y);
        if (isset($cells[$neighbour]) && $cells[$neighbour]['type'] != 'disabled') {
          $neighbours[$d] = $neighbour;
        }
      }
      $cells[$key]['neighbours'] = $neighbours;
    }
    $this->cells = $cells;
    return $this;
  }

  public function placePiece(DjambiPiece $piece, $old_position = NULL) {
    $new_position = $piece->getPosition();
    $this->cells[self::locateCell($new_position)]["occupant"] = $piece;
    if (!is_null($old_position) && !($new_position['x'] == $old_position['x'] && $new_position['y'] == $old_position['y'])) {
      $old_cell = self::locateCell($old_position);
      $occupant = $this->getCellOccupant($old_cell);
      if ($occupant && $occupant->getId() == $piece->getId()) {
        $this->cells[$old_cell]["occupant"] = NULL;
      }
    }
    return $this;
  }

  public function getCellOccupant($cell) {
    if (!empty($this->cells[$cell]['occupant'])) {
      return $this->cells[$cell]['occupant'];
    }
    return NULL;
  }

  private function setDefaultOptions() {
    $defaults = DjambiGameManager::getOptionsInfo();
    foreach ($defaults as $key => $value) {
      $this->setOption($key, $value['default']);
    }
    return $this;
  }

  // ----------------------------------------------------------
  // ---------- RECUPERATION D'INFOS SUR LA PARTIE ------------
  // ----------------------------------------------------------

  public function getHabilitiesStore() {
    return $this->habilities_store;
  }

  public function addHabilitiesInStore($habilities) {
    foreach ($habilities as $hability => $value) {
      if (!$this->isHabilityInStore($hability)) {
        $this->habilities_store[] = $hability;
      }
    }
    return $this;
  }

  public function isHabilityInStore($hability) {
    return in_array($hability, $this->getHabilitiesStore());
  }

  private function setScheme(DjambiBattlefieldScheme $scheme) {
    $this->scheme = $scheme;
    return $this;
  }

  /**
   * @return DjambiBattlefieldScheme
   */
  public function getScheme() {
    return $this->scheme;
  }

  public function setDisposition($disposition) {
    $dispositions = DjambiGameManager::getDispositions();
    if (isset($dispositions[$disposition])) {
      $this->disposition = $disposition;
    }
    return $this;
  }

  public function getDisposition() {
    if (is_null($this->disposition)) {
      $this->disposition = '4std';
    }
    return $this->disposition;
  }

  public function getInfo($info) {
    if (!isset($this->infos[$info])) {
      return FALSE;
    }
    return $this->infos[$info];
  }

  public function setInfo($info, $value) {
    $this->infos[$info] = $value;
    return $this;
  }

  public function getId() {
    return $this->id;
  }

  public function getFactions() {
    return $this->factions;
  }

  public function getFactionById($id) {
    foreach($this->factions as $key => $faction) {
      if ($faction->getId() == $id) {
        $faction->setBattlefield($this);
        return $faction;
      }
    }
    return FALSE;
  }

  /**
   * @return DjambiPoliticalFaction
   */
  public function getPlayingFaction() {
    if (!$this->isPending()) {
      return FALSE;
    }
    $play_order = current($this->getPlayOrder());
    return $this->getFactionById($play_order["side"]);
  }

  public function isPending() {
    if (in_array($this->getStatus(), array(KW_DJAMBI_STATUS_PENDING, KW_DJAMBI_STATUS_DRAW_PROPOSAL))) {
      return TRUE;
    }
    else {
      return FALSE;
    }
  }

  public function isFinished() {
    if ($this->getStatus() == KW_DJAMBI_STATUS_FINISHED) {
      return TRUE;
    }
    else {
      return FALSE;
    }
  }

  public function isNotBegin() {
    if ($this->getStatus() == KW_DJAMBI_STATUS_RECRUITING) {
      return TRUE;
    }
    else {
      return FALSE;
    }
  }

  /**
   * @return DjambiPiece
   */
  public function getPieceById($piece_id) {
    list($faction_id, $piece_id) = explode("-", $piece_id, 2);
    $faction = $this->getFactionById($faction_id);
    $pieces = $faction->getPieces();
    if (isset($pieces[$piece_id])) {
      return $pieces[$piece_id];
    }
    return FALSE;
  }

  public function getMoves() {
    return $this->moves;
  }

  public function getEvents() {
    return $this->events;
  }

  public function getRows() {
    return $this->getScheme()->getRows();
  }

  public function getCols() {
    return $this->getScheme()->getCols();
  }

  public function getCells() {
    return $this->cells;
  }

  public function getCellXY($key) {
    if (!isset($this->cells[$key])) {
      throw new Exception("Undefined cell : " . $key);
    }
    return array('x' => $this->cells[$key]['x'], 'y' => $this->cells[$key]['y']);
  }

  public function updateCell($cell, $key, $value) {
    $this->cells[$cell][$key] = $value;
  }

  public function resetCells() {
    foreach ($this->cells as $key => $data) {
      if (isset($data['reachable'])) {
        unset($this->cells[$key]['reachable']);
      }
    }
    return $this;
  }

  public function getStatus() {
    return $this->status;
  }

  public function getMode() {
    return $this->mode;
  }

  public function setMode($mode) {
    $this->mode = $mode;
    return $this;
  }

  public function getDimensions() {
    return max($this->getRows(), $this->getCols());
  }

  public function getTurns() {
    return $this->turns;
  }

  public function getOption($option_key) {
    if (isset($this->options[$option_key])) {
      return $this->options[$option_key];
    }
    return NULL;
  }

  public function setOption($option_key, $value) {
    $this->options[$option_key] = $value;
    return $this;
  }

  /**
   * @return DjambiGameManager
   */
  public function getGameManager() {
    return $this->game_manager;
  }

  public function setGameManager(DjambiGameManager $gm) {
    $this->game_manager = $gm;
    return $this;
  }

  // ----------------------------------------------------------
  // ---------- GESTION DES EVENEMENTS DE JEU -----------------
  // ----------------------------------------------------------

  public function cancelLastTurn() {
    $current_turn_key = $this->getCurrentTurnId();
    unset($this->turns[$current_turn_key]);
    $last_turn = end($this->turns);
    $last_turn_key = $this->getCurrentTurnId();
    $last_turn['end'] = NULL;
    $this->turns[$last_turn_key] = $last_turn;
    $this->viewTurnHistory($last_turn_key, TRUE);
    return $this;
  }

  public function viewTurnHistory($turn, $unset = FALSE) {
    $cells = $this->getCells();
    $inverted_moves = $this->moves;
    krsort($inverted_moves);
    foreach ($inverted_moves as $key => $move) {
      if ($move['turn'] >= $turn) {
        $piece = $this->getPieceById($move['target']);
        if (!$piece) {
          continue;
        }
        $position = $cells[$move['from']];
        $piece->setPosition($position);
        if ($move['type'] == 'murder' || $move['type'] == 'elimination') {
          $piece->setAlive(TRUE);
        }
        if ($unset) {
          unset($this->moves[$key]);
        }
      }
    }
    foreach ($this->events as $key => $event) {
      if ($event['turn'] >= $turn) {
        if ($event['turn'] == $turn && in_array($event['event'], array('NEW_DJAMBI_GAME', 'NEW_TURN', 'TURN_BEGIN'))) {
          continue;
        }
        if ($unset) {
          unset($this->events[$key]);
        }
      }
    }
    ksort($this->summary);
    foreach ($this->summary as $key => $data) {
      if ($key >= $turn && $key != 0) {
        unset($this->summary[$key]);
      }
      else {
        $summary = $data;
      }
    }
    if (!empty($summary)) {
      $this->rebuildFactionsControls($summary);
    }
    if (!$unset) {
      $this->displayed_turn_id = $turn;
    }
    return $this;
  }

  public function returnLastMoveData($version, $show_moves, $description_function = NULL) {
    $moves = $this->getMoves();
    $new_moves = array();
    $changing_cells = array();
    if (!empty($moves)) {
      foreach ($moves as $key => $move) {
        if ($move['time'] > $version) {
          $new_move = $this->returnMoveData($move, TRUE, $description_function, $changing_cells);
          if (!empty($new_move)) {
            $new_moves[] = $new_move;
          }
        }
      }
    }
    return array('show_moves' => $show_moves, 'changing_cells' => $changing_cells, 'moves' => $new_moves);
  }

  public function returnPastMoveData($turn_id, $show_moves, $description_function = NULL) {
    $moves = $this->getMoves();
    $animated_moves = array();
    $changing_cells = array();
    $past_moves = array();
    if (!empty($moves)) {
      $i = 0;
      foreach ($moves as $move) {
        if ($move['turn'] == $turn_id) {
          $past_move = $this->returnMoveData($move, $show_moves, $description_function, $changing_cells);
          if (!empty($past_move)) {
            $animated_moves['moves'][$i] = $move;
            $animated_moves['pieces'][$move['target']][] = $i;
            $past_moves[$i++] = $past_move;
          }
        }
      }
    }
    return array('animations' => $animated_moves, 'changing_cells' => $changing_cells, 'moves' => $past_moves);
  }

  public function returnShowableMoveData($showable_turns, $description_function = NULL) {
    $moves = $this->getMoves();
    $last_moves = array();
    $changing_cells = array();
    foreach ($moves as $move_key => $move) {
      if (in_array($move['turn'], $showable_turns)) {
        $last_move = $this->returnMoveData($move, TRUE, $description_function, $changing_cells);
        if (!empty($last_move)) {
          $last_moves[] = $last_move;
        }
      }
    }
    return array('changing_cells' => $changing_cells, 'moves' => $last_moves);
  }

  private function returnMoveData($move, $show_moves, $description_function, &$changing_cells) {
    $new_move = array();
    if ($move['type'] == 'move' || !isset($move['acting_faction'])) {
      $faction_id = $move['target_faction'];
    }
    else {
      $faction_id = $move['acting_faction'];
    }
    $acting_faction = $this->getFactionById($faction_id);
    if ($acting_faction) {
      $changing_cells[$move['from']] = $acting_faction->getClass();
      $changing_cells[$move['to']] = $acting_faction->getClass();
      $new_move = array(
          'location' => $move['to'],
          'origin' => $move['from'],
          'order' => $move['turn'] + 1,
          'faction' => $acting_faction->getId(),
          'faction_class' => $acting_faction->getClass(),
          'animation' => $move['type'] . ':' . $move['to'],
          'hidden' => !$show_moves
      );
      if (!is_null($description_function) && function_exists($description_function)) {
        $new_move['description'] = call_user_func_array($description_function, array($move, $this));
      }
    }
    return $new_move;
  }

  private function rebuildFactionsControls($summary) {
    foreach ($summary['factions'] as $faction_key => $data) {
      $faction = $this->getFactionById($faction_key);
      $faction->setStatus($data['status']);
      $faction->setControl($this->getFactionById($data['control']), FALSE);
      $faction->setMaster(isset($data['master']) ? $data['master'] : NULL);
    }
    return $this;
  }

  public function endGame($living_factions) {
    $nb_living_factions = count($living_factions);
    if ($nb_living_factions == 1) {
      $winner_id = current($living_factions);
      $winner = $this->getFactionById($winner_id);
      $winner->setStatus(KW_DJAMBI_USER_WINNER);
      $winner->setRanking(1);
      $this->logEvent('event', 'THE_WINNER_IS', array('faction1' => $winner->getId()));
    }
    else {
      $this->logEvent("event", "DRAW");
      foreach ($living_factions as $faction_id) {
        $faction = $this->getFactionById($faction_id);
        $faction->setStatus(KW_DJAMBI_USER_DRAW);
        $faction->setRanking($nb_living_factions);
      }
    }
    $this->setStatus(KW_DJAMBI_STATUS_FINISHED);
    $this->updateSummary();
    $this->buildFinalRanking($nb_living_factions);
    $this->logEvent("event", "END");
    return $this;
  }

  public function changeTurn() {
    $changes = FALSE;
    // Log de la fin du tour
    $last_turn_key = $this->getCurrentTurnId();
    $this->turns[$last_turn_key]["end"] = time();
    $kings = $this->findKings();
    // Attribution des pièces vivantes à l'occupant du trône
    if (!empty($kings)) {
      foreach ($this->getFactions() as $faction) {
        if (!$faction->getControl()->isAlive()) {
          if (count($kings) == 1) {
            // Cas d'un abandon : lors de la prise de pouvoir, retrait de l'ancien chef
            $pieces = $faction->getPieces();
            /* @var $piece DjambiPiece */
            foreach ($pieces as $key => $piece) {
              if ($this->getOption('rule_vassalization') == 'full_control'
                  && $piece->isAlive() && $piece->getDescription()->hasHabilityMustLive()) {
                $piece->setAlive(FALSE);
                $this->logMove($piece, $piece->getPosition(), 'elimination');
              }
            }
            // Prise de contrôle
            $faction->setControl($this->getFactionById(current($kings)));
            $changes = TRUE;
          }
        }
      }
    }
    elseif ($this->getOption('rule_vassalization') != 'full_control') {
      foreach ($this->getFactions() as $faction) {
        if (!$faction->isAlive()) {
          if (in_array($faction->getStatus(), array(KW_DJAMBI_USER_DEFECT, KW_DJAMBI_USER_WITHDRAW, KW_DJAMBI_USER_SURROUNDED))
             && $faction->getControl()->getId() != $faction->getId()) {
             $faction->setControl($faction);
             $changes = TRUE;
          }
        }
      }
    }
    // Vérification des conditions de victoire
    $living_factions = array();
    /* @var $faction DjambiPoliticalFaction */
    foreach ($this->getFactions() as $key => $faction) {
      if ($faction->isAlive()) {
        $control_leader = $faction->checkLeaderFreedom();
        if (!$control_leader) {
          $this->logEvent("event", "SURROUNDED", array('faction1' => $faction->getId()));
          if ($this->getOption('rule_comeback') == 'never' && $this->getOption('rule_vassalization') == 'full_control') {
            foreach ($faction->getPieces() as $piece) {
              if ($piece->isAlive() && $piece->getDescription()->hasHabilityMustLive() && $piece->getFaction()->getId() == $faction->getId()) {
                $this->logMove($piece, $piece->getPosition(), 'elimination');
                $piece->setAlive(FALSE);
              }
            }
          }
          $faction->dieDieDie(KW_DJAMBI_USER_SURROUNDED);
          $changes = TRUE;
        }
        else {
          $living_factions[] = $faction->getId();
        }
      }
      elseif ($this->getOption('rule_comeback') == 'surrounded' ||
          ($this->getOption('rule_comeback') == 'allowed' && empty($kings))) {
        if ($faction->getStatus() == KW_DJAMBI_USER_SURROUNDED) {
          $control_leader = $faction->checkLeaderFreedom();
          if ($control_leader) {
            $faction->setStatus(KW_DJAMBI_USER_READY);
            $this->logEvent("event", "COMEBACK_AFTER_SURROUND", array('faction1' => $faction->getId()));
            $changes = TRUE;
          }
        }
      }
    }
    $total = count($living_factions);
    if ($total < 2) {
      $this->endGame($living_factions);
    }
    else {
      $this->definePlayOrder();
      if ($changes) {
        $this->updateSummary();
      }
    }
    return $this;
  }

  private function findKings() {
    $kings = array();
    $thrones = $this->getSpecialCells("throne");
    foreach ($thrones as $throne) {
      if (!empty($this->cells[$throne]["occupant"])) {
        $occupant = $this->cells[$throne]["occupant"];
        if ($occupant->isAlive()) {
          $kings[] = $occupant->getFaction()->getControl()->getId();
          break;
        }
      }
    }
    return array_unique($kings);
  }

  public function getPlayOrder($reset = FALSE) {
    if (empty($this->play_order) || $reset) {
      $this->definePlayOrder();
    }
    reset($this->play_order);
    return $this->play_order;
  }

  public function setStatus($status) {
    $this->status = $status;
    return $this;
  }

  public function getLivingFactionsAtTurnBegin() {
    return $this->living_factions_at_turn_begin;
  }

  private function definePlayOrder() {
    $this->play_order = array();
    $orders = array();
    $selected_faction = NULL;
    $nb_factions = 0;
    foreach ($this->factions as $key => $faction) {
      $orders["orders"][] = $faction->getStartOrder();
      $orders["factions"][] = $faction->getId();
      $orders["alive"][] = $faction->isAlive();
      if ($faction->isAlive()) {
        $nb_factions++;
      }
    }
    $this->living_factions_at_turn_begin = $nb_factions;
    $total_factions = count($orders["factions"]);
    $thrones = $this->getSpecialCells("throne");
    $nb_thrones = count($thrones);
    $turn_scheme = array();
    for ($i = 0 ; $i < $total_factions ; $i++) {
      $turn_scheme[] = array(
        "side" => $i,
        "type" => "std",
        "played" => FALSE,
        "playable" => TRUE,
        "alive" => TRUE
      );
      foreach ($thrones as $j => $throne) {
        $turn_scheme[] = array(
          "side" => NULL,
          "type" => "throne",
          "case" => $throne,
          "played" => FALSE,
          "playable" => TRUE,
          "alive" => TRUE
        );
      }
    }
    array_multisort($orders["orders"], $orders["factions"], $orders["alive"]);
    foreach ($orders["factions"] as $order => $faction_key) {
      foreach ($turn_scheme as $key => $turn) {
        if ($turn["side"] == $order) {
          $turn_scheme[$key]["side"] = $faction_key;
          $turn_scheme[$key]["alive"] = $orders["alive"][$order];
          foreach ($thrones as $tk => $case) {
            $turn_scheme[$key + $tk +1]["alive"] = $orders["alive"][$order];
          }
          break;
        }
      }
    }
    $rulers = array();
    if (!empty($thrones) && $this->getStatus() == KW_DJAMBI_STATUS_PENDING) {
      foreach ($thrones as $throne) {
        if (isset($this->cells[$throne]) && !empty($this->cells[$throne]["occupant"])) {
          $piece = $this->cells[$throne]["occupant"];
          if ($piece->getDescription()->hasHabilityAccessThrone() && $piece->isAlive()) {
            foreach ($turn_scheme as $key => $turn) {
              if ($turn["type"] == "throne" && $turn["case"] == $throne) {
                if ($piece->getFaction()->getControl()->getId() == $piece->getFaction()->getId()) {
                  $turn_scheme[$key]["side"] = $piece->getFaction()->getControl()->getId();
                }
                $rulers[] = $piece->getFaction()->getControl()->getId();
              }
            }
          }
        }
      }
      $prev_side = NULL;
      $last_playable_turn_scheme = NULL;
      $last_playable_prev_side = NULL;
      foreach ($turn_scheme as $key => $turn) {
        if ($turn["side"] == $prev_side) {
          $turn_scheme[$key]["playable"] = FALSE;
        }
        if ($turn["side"] != NULL && $turn["alive"] && $turn["type"] == "std") {
          $prev_side = (!$turn_scheme[$key]["playable"] && $nb_factions == 2) ? NULL : $turn["side"];
        }
        elseif ($turn["type"] != "std" && $turn["side"] != NULL) {
          $prev_side = $turn["side"];
        }
        if($turn["side"] && $turn["alive"] && $turn_scheme[$key]["playable"]) {
          $last_playable_turn_scheme = $key;
          $last_playable_prev_side = $prev_side;
        }
      }
      if ($nb_factions > 2 && $turn_scheme[0]["side"] == $last_playable_prev_side) {
        $turn_scheme[$last_playable_turn_scheme]["playable"] = FALSE;
      }
    }
    elseif ($this->getStatus() == KW_DJAMBI_STATUS_DRAW_PROPOSAL) {
      foreach ($turn_scheme as $key => $turn) {
        if (!empty($turn['side']) && $turn['playable']) {
          $side = $this->getFactionById($turn['side']);
          if (!is_null($side->getDrawStatus())) {
            $turn_scheme[$key]['playable'] = FALSE;
          }
        }
      }
    }
    $max_ts = max(array_keys($turn_scheme));
    $new_turn = FALSE;
    $new_phase = TRUE;
    if (!empty($this->turns)) {
      $last_turn = end($this->turns);
      $current_scheme_key = $last_turn["turn_scheme"];
      $current_phase = $last_turn["turn"];
      $current_side = $last_turn["side"];
      if (!empty($last_turn["end"])) {
        $new_turn = TRUE;
        $current_scheme_key ++;
      }
      else {
        $new_phase = FALSE;
      }
      foreach ($turn_scheme as $key => $turn) {
        if ($current_scheme_key > $key) {
          $turn_scheme[$key]["played"] = TRUE;
        }
        elseif ($turn["playable"] && $turn["alive"] && $turn["side"] != NULL) {
          $new_phase = FALSE;
        }
      }
      if ($new_phase) {
        $current_phase = $last_turn["turn"] + 1;
        foreach ($turn_scheme as $key => $turn) {
          if ($key == 0 && $turn["side"] == $last_turn["side"] && !in_array($turn["side"], $rulers)) {
            $turn_scheme[$key]["played"] = TRUE;
          }
          else {
            $turn_scheme[$key]["played"] = FALSE;
          }
        }
      }
    }
    else {
      $new_turn = TRUE;
      $current_phase = 1;
    }
    foreach ($turn_scheme as $key => $turn) {
      if ($turn["playable"] && $turn["alive"] && !$turn["played"] && $turn["side"] != NULL) {
        $this->play_order[] = array(
          "side" => $turn["side"],
          "turn_scheme" => $key
        );
      }
    }
    if (empty($this->play_order)) {
      return FALSE;
    }
    $current_order = current($this->play_order);
    $corrections = FALSE;
    // Un camp ne peut pas jouer deux fois de suite après avoir tué un chef ennemi
    if ($new_turn && $nb_factions > 2 && !empty($this->turns) && $this->turns[$this->getCurrentTurnId()]['side'] == $current_order['side']) {
      unset($this->play_order[key($this->play_order)]);
      $corrections = TRUE;
    }
    // Un camp ne peut pas jouer immédiatement après avoir accédé au pouvoir ou s'être retiré du pouvoir
    elseif ($new_turn && $nb_factions == 2) {
      $last_turn_id = $this->getCurrentTurnId();
      foreach($this->moves as $move) {
        if ($move['turn'] == $last_turn_id && !empty($move['special_event'])
            && in_array($move['special_event'], array('THRONE_ACCESS', 'THRONE_RETREAT'))
            && $this->turns[$last_turn_id]['side'] == $current_order['side']) {
          unset($this->play_order[key($this->play_order)]);
          $corrections = TRUE;
          break;
        }
      }
    }
    if ($corrections && empty($this->play_order)) {
      $current_phase++;
      $new_phase = TRUE;
    }
    $displayed_next_turns = 4;
    if (count($this->play_order) < $displayed_next_turns) {
      $i = 0;
      while(count($this->play_order) < $displayed_next_turns) {
        if ($i > $max_ts) {
          $i = 0;
        }
        if (isset($turn_scheme[$i]) && $turn_scheme[$i]["alive"] && $turn_scheme[$i]["side"] != NULL && $turn_scheme[$i]["playable"]) {
          $this->play_order[] = array(
              "side" => $turn_scheme[$i]["side"],
              "turn_scheme" => $i
          );
        }
        $i++;
      }
    }
    $current_order = current($this->play_order);
    $selected_faction = $this->getFactionById($current_order["side"]);
    $selected_faction->setPlaying(TRUE);
    $begin = !empty($last_turn) ? $last_turn['end'] + 1 : time();
    if ($new_turn) {
      $this->turns[] = array(
        "begin" => $begin,
        "end" => NULL,
        "side" => $current_order["side"],
        "turn_scheme" => $current_order["turn_scheme"],
        "turn" => $current_phase
      );
      $this->logEvent("notice", "TURN_BEGIN", array('faction1' => $selected_faction->getId()), $begin);
    }
    if ($new_phase) {
      if ($current_phase == 1) {
        $this->logEvent("event", "GAME_START");
      }
      $this->logEvent("notice", "NEW_TURN", array("!turn" => $current_phase), $begin);
    }
    return TRUE;
  }

  public function defineMovablePieces() {
    /* @var $active_faction DjambiPoliticalFaction */
    $current_order = current($this->play_order);
    $active_faction = $this->getFactionById($current_order["side"]);
    /* @var $piece DjambiPiece */
    $can_move = FALSE;
    foreach ($active_faction->getControlledPieces() as $key => $piece) {
      $moves = $piece->buildAllowableMoves();
      if ($moves > 0) {
        $can_move = TRUE;
      }
    }
    if (!$can_move && $active_faction->getSkippedTurns() == $this->getOption('allowed_skipped_turns_per_user')) {
      $active_faction->withdraw();
      $this->changeTurn();
    }
    return $this;
  }

  public function countLivingFactions() {
    $nb_alive = 0;
    foreach ($this->getFactions() as $faction) {
      if ($faction->isAlive()) {
        $nb_alive++;
      }
    }
    return $nb_alive;
  }

  public function findNeighbourCells($position, $use_diagonals = TRUE) {
    $cell = $this->cells[self::locateCell($position)];
    $next_positions = array();
    foreach ($cell['neighbours'] as $direction_key => $neighbour) {
      $direction = $this->getScheme()->getDirection($direction_key);
      if ($use_diagonals || !$direction['diagonal']) {
        $next_positions[] = array('x' => $this->cells[$neighbour]['x'],
          'y' => $this->cells[$neighbour]['y']);
      }
    }
    return $next_positions;
  }

  public function getSpecialCells($type) {
    $special_cells = array();
    foreach ($this->cells as $key => $cell) {
      if (isset($cell["type"]) && $cell["type"] == $type) {
        $special_cells[] = $key;
      }
    }
    return $special_cells;
  }

  public function getFreeCells(DjambiPiece $piece, $keep_alive = TRUE, $murder = FALSE, $force_free_cell = NULL) {
    $freecells = array();
    foreach ($this->cells as $key => $cell) {
      if (!isset($cell['occupant']) || (!is_null($force_free_cell) && DjambiBattlefield::locateCell($force_free_cell) == $key)) {
        if($cell["type"] == 'throne') {
          $can_place_throne = FALSE;
          if ($piece->getDescription()->hasHabilityAccessThrone() && $piece->isAlive() && $keep_alive) {
            $can_place_throne = TRUE;
          }
          if ($this->getOption('rule_throne_interactions') == 'extended' && $murder
            && $piece->getDescription()->hasHabilityAccessThrone()) {
            $can_place_throne = TRUE;
          }
          if ($can_place_throne) {
            $freecells[] = $key;
          }
        }
        else {
          $freecells[] = $key;
        }
      }
    }
    return $freecells;
  }

  public function getSummary() {
    return $this->summary;
  }

  public function prepareSummary() {
    $vassals = array();
    $players = array();
    foreach($this->factions as $faction) {
      if ($faction->getStatus() == KW_DJAMBI_USER_VASSALIZED) {
        $vassals[] = $faction->getId();
      }
      else {
        $players[] = $faction;
      }
    }
    if (!empty($vassals) && !empty($players) && count($vassals) == count($players)) {
      foreach ($vassals as $id) {
        $control = current($players);
        $this->getFactionById($id)->setControl($control, TRUE);
        next($players);
      }
    }
    $this->updateSummary();
    return $this;
  }

  public function updateSummary() {
    $infos = array();
    if (empty($this->summary)) {
      $key = -1;
    }
    else {
      $key = $this->getCurrentTurnId();
    }
    foreach ($this->factions as $faction) {
      $faction_info = array(
        'control' => $faction->getControl()->getId(),
        'status' => $faction->getStatus()
      );
      if (!is_null($faction->getMaster())) {
        $faction_info['master'] = $faction->getMaster();
      }
      $infos['factions'][$faction->getId()] = $faction_info;
    }
    foreach ($this->events as $key => $event) {
      if ($event['event'] == 'GAME_OVER') {
        $faction = $this->getFactionById($event['args']['faction1']);
        if (!$faction->isAlive()) {
          $infos['eliminations'][$faction->getId()] = $event['turn'];
        }
      }
    }
    $this->summary[$event['turn']] = $infos;
    return $this;
  }

  private function buildFinalRanking($begin) {
    $last_summary = $this->summary[max(array_keys($this->summary))];
    arsort($last_summary['eliminations']);
    $last_turn = NULL;
    $i = 0;
    $rank = 0;
    foreach ($last_summary['eliminations'] as $faction_key => $turn) {
      $i++;
      if ($last_turn != $turn) {
        $rank = $begin + $i;
      }
      $this->getFactionById($faction_key)->setRanking($rank);
      $last_turn = $turn;
    }
    return $this;
  }

  public function logEvent($type, $event_txt, $event_args = NULL, $time = NULL) {
    $event = array(
      "turn" => $this->getCurrentTurnId(),
      "time" => is_null($time) ? time() : $time,
      "type" => $type, // event, info, notice
      "event" => $event_txt,
      "args" => $event_args
    );
    $this->events[] = $event;
    return $this;
  }

  public function getCurrentTurnId() {
    return empty($this->turns) ? 0 : max(array_keys($this->turns));
  }

  public function getDisplayedTurnId() {
    if (!is_null($this->displayed_turn_id)) {
      return $this->displayed_turn_id;
    }
    else {
      return $this->getCurrentTurnId() + 1;
    }
  }

  public function logMove(DjambiPiece $target_piece, $destination, $type = "move", DjambiPiece $acting_piece = NULL) {
    if (is_array($destination)) {
      $destination_cell = self::locateCell($destination);
    }
    else {
      $destination_cell = $destination;
    }
    $origin_cell = self::locateCell($target_piece->getPosition());
    if ($this->cells[$destination_cell]['type'] == 'throne' && $target_piece->getDescription()->hasHabilityAccessThrone() && $target_piece->isAlive()) {
      $special_event = 'THRONE_ACCESS';
    }
    elseif ($this->cells[$destination_cell]['type'] == 'throne' && $target_piece->getDescription()->hasHabilityAccessThrone() && !$target_piece->isAlive()) {
      $special_event = 'THRONE_MAUSOLEUM';
    }
    elseif ($this->cells[$origin_cell]['type'] == 'throne' && $target_piece->getDescription()->hasHabilityAccessThrone() && $target_piece->isAlive()) {
      $special_event = 'THRONE_RETREAT';
    }
    elseif ($this->cells[$origin_cell]['type'] == 'throne' && $target_piece->getDescription()->hasHabilityAccessThrone() && !$target_piece->isAlive()) {
      if ($acting_piece->getDescription()->hasHabilityKillThroneLeader()) {
        $special_event = 'THRONE_MURDER';
      }
      else {
        $special_event = 'THRONE_EVACUATION';
      }
    }
    else {
      $special_event = NULL;
    }
    $move = array(
      'turn' => $this->getCurrentTurnId(),
      'time' => time(),
      'target_faction' => $target_piece->getFaction()->getControl()->getId(),
      'target' => $target_piece->getId(),
      'from' => $origin_cell,
      'to' => $destination_cell,
      'type' => $type,
    );
    if (!is_null($acting_piece)) {
      $move['acting'] = $acting_piece->getId();
      $move['acting_faction'] = $acting_piece->getFaction()->getControl()->getId();
    }
    if (!is_null($special_event)) {
      $this->logEvent('event', $special_event, array('piece' => $move['target']));
      $move['special_event'] = $special_event;
    }
    $this->moves[] = $move;
    return $this;
  }

  // ----------------------------------------------------------
  // ---------- ENREGISTREMENT EN BASE DE DONNEES -------------
  // ----------------------------------------------------------

  public function toArray() {
    $positions = array();
    $pieces = array();
    $factions = array();
    $deads = array();
    foreach ($this->cells as $key => $cell) {
      if (isset($cell['occupant'])) {
        $piece = $cell['occupant'];
        $positions[$key] = $piece->getId();
        if (!$piece->isAlive()) {
          $deads[] = $piece->getId();
        }
      }
    }
    foreach ($this->factions as $key => $faction) {
      $factions[$faction->getId()] = $faction->toArray();
    }
    $return = array(
      'id' => $this->id,
      'scheme' => get_class($this->getScheme()),
      'scheme_settings' => $this->getScheme()->getSettings(),
      'positions' => $positions,
      'factions' => $factions,
      'moves' => $this->moves,
      'turns' => isset($this->turns) ? $this->turns : array(),
      'points' => isset($this->points) ? $this->points : 0,
      'deads' => $deads,
      'events' => $this->events,
      'options' => $this->options,
      'summary' => $this->summary,
      'mode' => $this->getMode(),
      'status' => $this->getStatus(),
      'infos' => $this->infos,
      'disposition' => $this->getDisposition(),
    );
    return $return;
  }

}