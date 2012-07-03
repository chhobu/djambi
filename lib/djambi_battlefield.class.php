<?php
define('KW_DJAMBI_MODE_SANDBOX', 'bac-a-sable');

define('KW_DJAMBI_STATUS_PENDING', 'pending');
define('KW_DJAMBI_STATUS_FINISHED', 'finished');
define('KW_DJAMBI_STATUS_DRAW_PROPOSAL', 'draw_proposal');

define('KW_DJAMBI_USER_PLAYING', 'playing'); // Partie en cours
define('KW_DJAMBI_USER_WINNER', 'winner'); // Fin du jeu, vainqueur
define('KW_DJAMBI_USER_DRAW', 'draw'); // Fin du jeu, nul
define('KW_DJAMBI_USER_KILLED', 'killed'); // Fin du jeu, perdant
define('KW_DJAMBI_USER_WITHDRAW', 'withdraw'); // Fin du jeu, abandon
define('KW_DJAMBI_USER_SURROUNDED', 'surrounded'); // Fin du jeu, encerclement
define('KW_DJAMBI_USER_DEFECT', 'defect'); // Fin du jeu, disqualification
define('KW_DJAMBI_USER_EMPTY_SLOT', 'empty'); // Création de partie, place libre
define('KW_DJAMBI_USER_READY', 'ready'); // Création de partie, prêt à jouer


class DjambiBattlefield {
  private $id, $rows, $cols, $cells, $factions, $directions,
    $moves, $mode, $status, $turns, $play_order, $events, $options,
    $infos, $rules, $living_factions_at_turn_begin, $summary;

  public function __construct($id, $new_game, $data) {
    $this->id = $id;
    $this->setDefaultOptions();
    $this->moves = array();
    $this->events = array();
    $this->summary = array();
    if (!$new_game) {
      return $this->loadBattlefield($data);
    }
    else {
      $nb_factions = count($data['factions']);
      if ($nb_factions == 4) {
        return $this->buildNewBattlefieldWith4Factions($data['factions'],
            isset($data['options']) ? $data['options'] : NULL);
      }
    }
    return FALSE;
  }

  public static function getModes($with_description = FALSE) {
    $modes = array(
        KW_DJAMBI_MODE_SANDBOX => 'MODE_SANDBOX_DESCRIPTION'
    );
    if ($with_description) {
      return $modes;
    }
    else {
      return array_keys($modes);
    }
  }

  public static function getAvailbaleNumberPlayers() {
    return array(
        '4std' => '4STD_DESCRIPTION'
    );
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

  private function loadBattlefield($data) {
    $this->rows = $data['rows'];
    $this->cols = $data['cols'];
    $this->moves = isset($data['moves']) ? $data['moves'] : array();
    $this->turns = isset($data['turns']) ? $data['turns'] : array();
    $this->points = isset($data['points']) ? $data['points'] : 0;
    $this->events = isset($data['events']) ? $data['events'] : array();
    $this->summary = isset($data['summary']) ? $data['summary'] : array();
    $this->factions = array();
    if (isset($data['options']) && is_array($data['options'])) {
      foreach($data['options'] as $option => $value) {
        $this->setOption($option, $value);
      }
    }
    $this->cells = $this->buildStdField($data['special_cells']);
    $pieces_scheme = new DjambiPieceScheme($this->getOption('piece_scheme'));
    $controls = array();
    foreach ($data['factions'] as $key => $faction_data) {
      $faction = new DjambiPoliticalFaction($data['users'][$key], $key, $faction_data);
      $positions = array();
      foreach ($data['positions'] as $cell => $piece_id) {
        $piece_data = explode('-', $piece_id, 2);
        if ($piece_data[0] == $key) {
          $positions[$piece_data[1]] = $this->cells[$cell];
        }
      }
      $faction->setBattlefield($this);
      $faction->setAlive($faction_data['alive']);
      $faction->createPieces($pieces_scheme->getPieceScheme(), $positions, $data['deads']);
      $this->factions[] = $faction;
      $controls[$key] = $faction_data['control'];
    }
    foreach ($controls as $key_faction => $key_control) {
      $faction = $this->getFactionById($key_faction);
      $faction->setControl($this->getFactionById($key_control), FALSE);
    }
    return $this;
  }

  public static function getDefaultOptions() {
    return array(
      'allowed_skipped_turns_per_user' => -1,
      'turns_before_draw_proposal' => 10,
      'piece_scheme' => 'standard',
      'directions' => 'cardinal',
      'rule_surrounding' => 'loose', // throne_access, strict, loose
      'rule_comeback' => 'allowed', // never, surrounding, allowed
      'rule_vassalization' => 'temporary' // temporary, full_control
    );
  }

  private function setDefaultOptions() {
    $defaults = self::getDefaultOptions();
    foreach ($defaults as $key => $value) {
      $this->setOption($key, $value);
    }
  }

  private function buildStdField($special_cells = array()) {
    $cells = array();
    $this->buildDirectionsArray();
    for ($x = 1; $x <= $this->cols; $x++) {
      for ($y = 1; $y <= $this->rows; $y++) {
        $cells[self::locateCellByXY($x, $y)] = array('x' => $x, 'y' => $y, 'type' => 'std', 'occupant' => NULL);
      }
    }
    foreach ($cells as $key => $cell) {
      $neighbours = array();
      foreach ($this->directions as $d => $direction) {
        $neighbour = self::locateCellByXY($cell['x'] + $direction['x'], $cell['y'] + $direction['y']);
        if (isset($cells[$neighbour])) {
          $neighbours[$d] = $neighbour;
        }
      }
      $cells[$key]['neighbours'] = $neighbours;
    }
    foreach ($special_cells as $key => $type) {
      $cells[$key]['type'] = $type;
    }
    return $cells;
  }

  private function buildDirectionsArray() {
    switch($this->getOption('directions')) {
      case('cardinal'):
      default :
        $this->directions = array(
            'N' => array('y' => 0, 'x' => -1),
            'NE' => array('y' => 1, 'x' => -1),
            'E' => array('y' => 1, 'x' => 0),
            'SE' => array('y' => 1, 'x' => 1),
            'S' => array('y' => 0, 'x' => 1),
            'SW' => array('y' => -1, 'x' => 1),
            'W' => array('y' => -1, 'x' => 0),
            'NW' => array('y' => -1, 'x' => -1));
    }
  }

  private function buildNewBattlefieldWith4Factions($factions, $options) {
    $this->rows = 9;
    $this->cols = 9;
    if (!empty($options) && is_array($options)) {
      foreach($options as $option => $value) {
        $this->setOption($option, $value);
      }
    }
    $this->factions = $factions;
    $this->cells = $this->buildStdField(array('E5' => 'throne'));
    $pieces_scheme = new DjambiPieceScheme();
    foreach ($this->factions as $key => $faction) {
      if ($faction->getStartOrder() == 4) {
        $start_scheme = array(
          'L'  => array('x' => 1, 'y' => 1),
          'R'  => array('x' => 1, 'y' => 2),
          'M1' => array('x' => 1, 'y' => 3),
          'A'  => array('x' => 2, 'y' => 1),
          'D'  => array('x' => 2, 'y' => 2),
          'M2' => array('x' => 2, 'y' => 3),
          'M3' => array('x' => 3, 'y' => 1),
          'M4' => array('x' => 3, 'y' => 2),
          'N'  => array('x' => 3, 'y' => 3)
        );
      }
      elseif ($faction->getStartOrder() == 3) {
        $start_scheme = array(
          'L'  => array('x' => 9, 'y' => 1),
          'R'  => array('x' => 9, 'y' => 2),
          'M1' => array('x' => 9, 'y' => 3),
          'A'  => array('x' => 8, 'y' => 1),
          'D'  => array('x' => 8, 'y' => 2),
          'M2' => array('x' => 8, 'y' => 3),
          'M3' => array('x' => 7, 'y' => 1),
          'M4' => array('x' => 7, 'y' => 2),
          'N'  => array('x' => 7, 'y' => 3)
        );
      }
      elseif ($faction->getStartOrder() == 2) {
        $start_scheme = array(
          'L'  => array('x' => 9, 'y' => 9),
          'R'  => array('x' => 9, 'y' => 8),
          'M1' => array('x' => 9, 'y' => 7),
          'A'  => array('x' => 8, 'y' => 9),
          'D'  => array('x' => 8, 'y' => 8),
          'M2' => array('x' => 8, 'y' => 7),
          'M3' => array('x' => 7, 'y' => 9),
          'M4' => array('x' => 7, 'y' => 8),
          'N'  => array('x' => 7, 'y' => 7)
        );

      }
      elseif ($faction->getStartOrder() == 1) {
        $start_scheme = array(
          'L'  => array('x' => 1, 'y' => 9),
          'R'  => array('x' => 1, 'y' => 8),
          'M1' => array('x' => 1, 'y' => 7),
          'A'  => array('x' => 2, 'y' => 9),
          'D'  => array('x' => 2, 'y' => 8),
          'M2' => array('x' => 2, 'y' => 7),
          'M3' => array('x' => 3, 'y' => 9),
          'M4' => array('x' => 3, 'y' => 8),
          'N'  => array('x' => 3, 'y' => 7)
        );
      }
      $faction->setBattlefield($this);
      $faction->createPieces($pieces_scheme->getPieceScheme(), $start_scheme);
    }
    $this->logEvent('info', 'NEW_DJAMBI_GAME');
    return $this;
  }

  public static function locateCell($position) {
    return DjambiBattlefield::locateCellByXY($position["x"], $position["y"]);
  }

  public static function locateCellByXY($x, $y) {
    return DjambiBattlefield::intToAlpha($x) . $y;
  }

  public static function intToAlpha($int, $inverse = FALSE) {
    $alpha = array("#", "A", "B", "C", "D", "E", "F", "G", "H", "I", "J", "K",
      "L", "M", "N", "O", "P", "Q", "R", "S", "T", "U", "V", "W", "X", "Y", "Z");
    if ($inverse) {
      return array_search($int, $alpha);
    }
    if (isset($alpha[$int])) {
      return $alpha[$int];
    }
    return $alpha[0];
  }

  public function placePiece($piece, $old_position = NULL) {
    $this->cells[$this->locateCell($piece->getPosition())]["occupant"] = $piece;
    if (!is_null($old_position) && $piece->getPosition() != $old_position) {
      $old_cell = $this->locateCell($old_position);
      if (isset($this->cells[$old_cell]["occupant"]) && $piece == $this->cells[$old_cell]["occupant"]) {
        $this->cells[$old_cell]["occupant"] = NULL;
      }
    }
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
        return $faction;
      }
    }
    return FALSE;
  }

  public function getPlayingFaction() {
    $play_order = current($this->getPlayOrder());
    return $this->getFactionById($play_order["side"]);
  }

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
    return $this->rows;
  }

  public function getCols() {
    return $this->cols;
  }

  public function getCells() {
    return $this->cells;
  }

  public function updateCell($cell, $key, $value) {
    $this->cells[$cell][$key] = $value;
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
    return max($this->rows, $this->cols);
  }

  public function getTurns() {
    return $this->turns;
  }

  public function getDirections() {
    return $this->directions;
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

  public function cancelLastTurn() {
    $current_turn_key = $this->getCurrentTurnId();
    unset($this->turns[$current_turn_key]);
    $last_turn = end($this->turns);
    $last_turn_key = $this->getCurrentTurnId();
    $last_turn['end'] = NULL;
    $this->turns[$last_turn_key] = $last_turn;
    $cells = $this->getCells();
    foreach ($this->moves as $key => $move) {
      if ($move['turn'] == $last_turn_key || $move['turn'] == $current_turn_key) {
        $piece = $this->getPieceById($move['target']);
        $position = $cells[$move['from']];
        $piece->setPosition($position['x'], $position['y']);
        if ($move['type'] == 'murder' || $move['type'] == 'elimination') {
          $piece->setAlive(TRUE);
        }
        if ($move['turn'] == $current_turn_key && $move['type'] == 'move' && !empty($move['acting'])) {
          $piece2 = $this->getPieceById($move['acting']);
          $position = self::locateCell($move['to']);
          $piece2->setPosition($position['x'], $position['y']);
        }
        unset($this->moves[$key]);
      }
    }
    foreach ($this->events as $key => $event) {
      if ($event['turn'] == $last_turn_key || $event['turn'] == $current_turn_key) {
        if ($event['turn'] == $last_turn_key && in_array($event['event'], array('NEW_DJAMBI_GAME', 'NEW_TURN', 'TURN_BEGIN'))) {
          continue;
        }
        unset($this->events[$key]);
      }
    }
    ksort($this->summary);
    foreach ($this->summary as $turn => $data) {
      if ($turn >= $last_turn_key) {
        unset($this->summary[$turn]);
      }
      else {
        $summary = $data;
      }
    }
    foreach ($summary['faction'] as $faction_key => $data) {
      $faction = $this->getFactionById($faction_key);
      $faction->setStatus($data['status']);
      $faction->setControl($this->getFactionById($data['control']), FALSE);
      $faction->setMaster(isset($data['master']) ? $data['master'] : NULL);
    }
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
      foreach ($living_factions as $faction) {
        $faction->setStatus(KW_DJAMBI_USER_DRAW);
        $faction->setRanking($nb_living_factions);
      }
    }
    $this->setStatus(KW_DJAMBI_STATUS_FINISHED);
    $this->updateSummary();
    $this->buildFinalRanking($nb_living_factions);
    $this->logEvent("event", "END");
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
            foreach ($pieces as $key => $piece) {
              if ($this->getOption('rule_vassalization') == 'full_control'
                  && $piece->isAlive() && $piece->getHability('must_live')) {
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
            foreach ($leaders as $leader) {
              $this->logMove($leader, $leader->getPosition(), 'elimination');
              $leader->setAlive(FALSE);
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
    elseif ($changes) {
      $this->updateSummary();
    }
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
          if ($piece->getHability("access_throne") && $piece->isAlive()) {
            foreach ($turn_scheme as $key => $turn) {
              if ($turn["type"] == "throne" && $turn["case"] == $throne) {
                $turn_scheme[$key]["side"] = $piece->getFaction()->getId();
                $rulers[] = $piece->getFaction()->getId();
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
    // Un camp ne peut pas jouer deux fois de suite après avoir tué un chef ennemi
    if ($new_turn && $nb_factions > 2 && !empty($this->turns) && $this->turns[$this->getCurrentTurnId()]['side'] == $current_order['side']) {
      unset($this->play_order[key($this->play_order)]);
    }
    // Un camp ne peut pas jouer immédiatement après avoir accédé au pouvoir
    elseif ($new_turn && $nb_factions == 2) {
      $last_turn_id = $this->getCurrentTurnId();
      foreach($this->moves as $move) {
        if ($move['turn'] == $last_turn_id && !empty($move['special_event']) && in_array($move['special_event'], array('throne access', 'throne retreat')) && $this->turns[$last_turn_id]['side'] == $current_order['side']) {
          unset($this->play_order[key($this->play_order)]);
          break;
        }
      }
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
      $this->logEvent("notice", "NEW_TURN", array("!turn" => $current_phase), $begin);
    }
    return TRUE;
  }

  private function defineMovablePieces() {
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
  }

  public function checkAvailableCell(DjambiPiece $piece, $cell, $allow_interactions) {
    $move_ok = FALSE;
    if (isset($this->cells[$cell]["occupant"])) {
      if (!$allow_interactions) {
        $move_ok = FALSE;
      }
      else {
        $occupant = $this->cells[$cell]["occupant"];
        if ($occupant->isAlive() && $occupant->getFaction()->getControl()->getId() != $piece->getFaction()->getControl()->getId()) {
          if ($piece->getHability("kill_by_attack")) {
            if ($this->cells[$cell]["type"] == "throne") {
              if ($piece->getHability("kill_throne_leader")) {
                $move_ok = TRUE;
              }
            }
            else {
              $move_ok = TRUE;
            }
          }
          elseif ($piece->getHability("move_living_pieces")) {
            $move_ok = TRUE;
          }
        }
        elseif (!$occupant->isAlive() && $piece->getHability("move_dead_pieces")) {
          $move_ok = TRUE;
        }
      }
    }
    else {
      if ($this->cells[$cell]["type"] != "throne" || $piece->getHability("access_throne")) {
        $move_ok = TRUE;
      }
    }
    return $move_ok;
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

  // FIXME adapter cette fonction à l'utilisation de directions
  public function findNeighbourCells($position, $use_diagonals = TRUE) {
    $next_positions = array();
    if ($position["x"] + 1 <= $this->cols) {
      $next_positions[] = array("x" => $position["x"] + 1, "y" => $position["y"]);
      if ($position["y"] + 1 <= $this->rows && $use_diagonals) {
        $next_positions[] = array("x" => $position["x"] + 1, "y" => $position["y"] + 1);
      }
      if ($position["y"] - 1 > 0 && $use_diagonals) {
        $next_positions[] = array("x" => $position["x"] + 1, "y" => $position["y"] - 1);
      }
    }
    if ($position["x"] - 1 > 0) {
      $next_positions[] = array("x" => $position["x"] - 1, "y" => $position["y"]);
      if ($position["y"] + 1 <= $this->rows && $use_diagonals) {
        $next_positions[] = array("x" => $position["x"] - 1, "y" => $position["y"] + 1);
      }
      if ($position["y"] - 1 > 0 && $use_diagonals) {
        $next_positions[] = array("x" => $position["x"] - 1, "y" => $position["y"] - 1);
      }
    }
    if ($position["y"] + 1 <= $this->rows) {
      $next_positions[] = array("x" => $position["x"], "y" => $position["y"] + 1);
    }
    if ($position["y"] - 1 > 0) {
      $next_positions[] = array("x" => $position["x"], "y" => $position["y"] - 1);
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

  public function getFreeCells(DjambiPiece $piece, $keep_alive = TRUE) {
    $freecells = array();
    foreach ($this->cells as $key => $cell) {
      if (!isset($cell["occupant"])) {
        if($cell["type"] != "throne" || ($piece->getType() == "leader" && $piece->isAlive() && $keep_alive)) {
          $freecells[] = $key;
        }
      }
    }
    return $freecells;
  }

  public function play() {
    if ($this->status == KW_DJAMBI_STATUS_PENDING) {
      $this->getPlayOrder(TRUE);
      $this->defineMovablePieces();
      if (empty($this->summary)) {
        $this->updateSummary();
      }
    }
  }

  public function getSummary() {
    return $this->summary;
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
    $this->summary[$key] = $infos;
  }

  private function buildFinalRanking($begin) {
    $last_summary = $this->summary[max(array_keys($this->summary))];
    arsort($last_summary['eliminations']);
    $last_turn = NULL;
    $i = 0;
    foreach ($last_summary['eliminations'] as $faction_key => $turn) {
      $i++;
      if ($last_turn != $turn) {
        $rank = $begin + $i;
      }
      $this->getFactionById($faction_key)->setRanking($rank);
      $last_turn = $turn;
    }
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
  }

  public function getCurrentTurnId() {
    return empty($this->turns) ? 0 : max(array_keys($this->turns));
  }

  public function logMove(DjambiPiece $target_piece, $destination, $type = "move", DjambiPiece $acting_piece = NULL) {
    $destination_cell = self::locateCell($destination);
    $origin_cell = self::locateCell($target_piece->getPosition());
    if ($this->cells[$destination_cell]['type'] == 'throne' && $target_piece->getType() == 'leader' && $target_piece->isAlive()) {
      $special_event = 'throne access';
    }
    elseif ($this->cells[$origin_cell]['type'] == 'throne' && $target_piece->getType() == 'leader' && $target_piece->isAlive()) {
      $special_event = 'throne retreat';
    }
    elseif ($this->cells[$origin_cell]['type'] == 'throne' && $target_piece->getType() == 'leader' && !$target_piece->isAlive()) {
      $special_event = 'throne evacuation';
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
      $move['special_event'] = $special_event;
    }
    $this->moves[] = $move;
  }

  public function toDatabase() {
    $positions = array();
    $pieces = array();
    $factions = array();
    $deads = array();
    $special_cells = array();
    foreach ($this->cells as $key => $cell) {
      if (isset($cell['type']) && $cell['type'] != 'std') {
        $special_cells[$key] = $cell['type'];
      }
      if (isset($cell['occupant'])) {
        $piece = $cell['occupant'];
        $positions[$key] = $piece->getId();
        if (!$piece->isAlive()) {
          $deads[] = $piece->getId();
        }
      }
    }
    foreach ($this->factions as $key => $faction) {
      $factions[$faction->getId()] = $faction->toDatabase();
    }
    return array(
      'rows' => $this->rows,
      'cols' => $this->cols,
      'positions' => $positions,
      'factions' => $factions,
      'moves' => $this->moves,
      'turns' => isset($this->turns) ? $this->turns : array(),
      'points' => isset($this->points) ? $this->points : 0,
      'deads' => $deads,
      'special_cells' => $special_cells,
      'events' => $this->events,
      'options' => $this->options,
      'summary' => $this->summary
    );
  }

}