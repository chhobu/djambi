<?php
define('KW_DJAMBI_MODE_SANDBOX', 'bac-a-sable');
define('KW_DJAMBI_MODE_FRIENDLY', 'amical');

define('KW_DJAMBI_STATUS_PENDING', 'pending');
define('KW_DJAMBI_STATUS_FINISHED', 'finished');
define('KW_DJAMBI_STATUS_DRAW_PROPOSAL', 'draw_proposal');
define('KW_DJAMBI_STATUS_RECRUITING', 'recruiting');

define('KW_DJAMBI_USER_PLAYING', 'playing'); // Partie en cours
define('KW_DJAMBI_USER_WINNER', 'winner'); // Fin du jeu, vainqueur
define('KW_DJAMBI_USER_DRAW', 'draw'); // Fin du jeu, nul
define('KW_DJAMBI_USER_KILLED', 'killed'); // Fin du jeu, perdant
define('KW_DJAMBI_USER_WITHDRAW', 'withdraw'); // Fin du jeu, abandon
define('KW_DJAMBI_USER_VASSALIZED', 'vassalized'); // Fin du jeu, abandon
define('KW_DJAMBI_USER_SURROUNDED', 'surrounded'); // Fin du jeu, encerclement
define('KW_DJAMBI_USER_DEFECT', 'defect'); // Fin du jeu, disqualification
define('KW_DJAMBI_USER_EMPTY_SLOT', 'empty'); // Création de partie, place libre
define('KW_DJAMBI_USER_READY', 'ready'); // Création de partie, prêt à jouer


class DjambiBattlefield {
  private $id, $rows, $cols, $cells, $factions, $directions,
    $moves, $mode, $status, $turns, $play_order, $events, $options,
    $infos, $rules, $living_factions_at_turn_begin, $summary, $disposition;

  public function __construct($id, $data) {
    $this->id = $id;
    $this->setDefaultOptions();
    $this->moves = array();
    $this->events = array();
    $this->summary = array();
    if ($id > 0) {
      return $this->loadBattlefield($data);
    }
    else {
      $this->setInfo('sequence', $data['sequence']);
      $this->setDisposition($data['disposition']);
      $this->setMode($data['mode']);
      return $this->createNewGame($data['user_id'], $data['user_cookie']);
    }
  }

  private function createNewGame($user_id, $user_cookie) {
    $dispositions = self::getDispositions();
    // Construction des factions
    switch ($this->disposition) {
      case('2std'):
        $players = array(
        1 => 'human',
        2 => 'vassal',
        3 => 'human',
        4 => 'vassal'
        );
        break;
      default:
        $players = array_fill(1, $this->disposition['nb'], 'human');
    }
    $players_info = array();
    foreach ($players as $key => $player) {
      switch ($player) {
        case('human'):
          if ($this->mode == KW_DJAMBI_MODE_SANDBOX || ($key == 1 && $this->mode == KW_DJAMBI_MODE_FRIENDLY)) {
            $data['ip'] = $_SERVER['REMOTE_ADDR'];
            if ($this->mode != KW_DJAMBI_MODE_SANDBOX) {
              $data['ping'] = $data['joined'] = time();
            }
            $players_info[$key] = array(
                'uid' => $user_id,
                'data' => $data,
                'status' => KW_DJAMBI_USER_READY,
                'cookie' => $user_cookie
            );
          }
          else {
            $players_info[$key] = array(
                'uid' => 0,
                'data' => array(),
                'status' => KW_DJAMBI_USER_EMPTY_SLOT,
                'cookie' => NULL
            );
          }
          break;
        case('vassal'):
          $players_info[$key] = array(
            'uid' => 0,
            'data' => array(),
            'status' => KW_DJAMBI_USER_VASSALIZED,
            'cookie' => NULL
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
      $faction = new DjambiPoliticalFaction($players_info[$i], $key, $faction_data);
      $this->factions[$i] = $faction;
    }
    $this->setOption('directions', $dispositions[$this->disposition]['directions']);
    if ($nb_factions == 4) {
      $this->buildSquareBattlefieldWith4Factions();
    }
    elseif ($nb_factions == 3) {
      $this->buildHexagonalBattlefieldWith3Factions();
    }
    $this->logEvent('info', 'NEW_DJAMBI_GAME');
    return $this;
  }

  public static function getModes($with_description = FALSE) {
    $modes = array(
        KW_DJAMBI_MODE_FRIENDLY => 'MODE_FRIENDLY_DESCRIPTION',
        KW_DJAMBI_MODE_SANDBOX => 'MODE_SANDBOX_DESCRIPTION',
    );
    if ($with_description) {
      return $modes;
    }
    else {
      return array_keys($modes);
    }
  }

  public static function getStatuses($with_description = FALSE, $with_recruiting = TRUE, $with_pending = TRUE, $with_finished = TRUE) {
    $statuses = array();
    if ($with_recruiting) {
      $statuses[KW_DJAMBI_STATUS_RECRUITING] = 'STATUS_RECRUITING_DESCRIPTION';
    }
    if ($with_pending) {
      $statuses[KW_DJAMBI_STATUS_PENDING] = 'STATUS_PENDING_DESCRIPTION';
      $statuses[KW_DJAMBI_STATUS_DRAW_PROPOSAL] = 'STATUS_DRAW_PROPOSAL_DESCRIPTION';
    }
    if ($with_finished) {
      $statuses[KW_DJAMBI_STATUS_FINISHED] = 'STATUS_FINISHED_DESCRIPTION';
    }
    if ($with_description) {
      return $statuses;
    }
    else {
      return array_keys($statuses);
    }
  }

  public static function getDispositions($elements = 'all') {
    $games = array(
        '4std' => array(
            'description' => '4STD_DESCRIPTION',
            'nb' => 4,
            'scheme' => 'standard',
            'grid' => 'table',
            'directions' => 'cardinal'
        ),
        '2std' => array(
            'description' => '2STD_DESCRIPTION',
            'nb' => 2,
            'scheme' => 'standard',
            'grid' => 'table',
            'directions' => 'cardinal'
        ),
        '3hex' => array(
            'description' => '3HEX_DESCRIPTION',
            'nb' => 3,
            'scheme' => 'standard',
            'grid' => 'hexagonal',
            'directions' => 'hexagonal'
        )
    );
    if ($elements == 'all') {
      return $games;
    }
    $return = array();
    foreach($games as $key => $game) {
      if (isset($game[$elements])) {
        $return[$key] = $game[$elements];
      }
      else {
        $return[$key] = $game;
      }
    }
    return $return;
  }

  public function setDisposition($disposition) {
    $dispositions = self::getDispositions();
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

  private function loadBattlefield($data) {
    $this->rows = $data['rows'];
    $this->cols = $data['cols'];
    $this->moves = isset($data['moves']) ? $data['moves'] : array();
    $this->turns = isset($data['turns']) ? $data['turns'] : array();
    $this->points = isset($data['points']) ? $data['points'] : 0;
    $this->events = isset($data['events']) ? $data['events'] : array();
    $this->summary = isset($data['summary']) ? $data['summary'] : array();
    $this->setInfo('sequence', $data['sequence']);
    $this->factions = array();
    if (isset($data['options']) && is_array($data['options'])) {
      foreach($data['options'] as $option => $value) {
        $this->setOption($option, $value);
      }
    }
    $this->cells = $this->buildField($data['special_cells']);
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
    }
    if (!empty($this->summary)) {
      $this->rebuildFactionsControls($this->summary[max(array_keys($this->summary))]);
    }
    return $this;
  }

  public static function getOptionsInfo() {
    return array(
      'allowed_skipped_turns_per_user' => array(
          'default' => -1,
          'configurable' => TRUE,
          'title' => 'OPTION1',
          'widget' => 'select',
          'type' => 'game_option',
          'choices' => array(
              0 => 'OPTION1_NEVER',
              1 => 'OPTION1_XTIME',
              2 => 'OPTION1_XTIME',
              3 => 'OPTION1_XTIME',
              4 => 'OPTION1_XTIME',
              5 => 'OPTION1_XTIME',
              10 => 'OPTION1_XTIME',
              -1 => 'OPTION1_ALWAYS')
      ),
      'turns_before_draw_proposal' => array(
          'default' => 10,
          'configurable' => TRUE,
          'title' => 'OPTION2',
          'widget' => 'select',
          'type' => 'game_option',
          'choices' => array(
              -1 => 'OPTION2_NEVER',
              0 => 'OPTION2_ALWAYS',
              2 => 'OPTION2_XTURN',
              5 => 'OPTION2_XTURN',
              10 => 'OPTION2_XTURN',
              20 => 'OPTION2_XTURN')
      ),
      'piece_scheme' => array(
          'default'=> 'standard',
          'configurable' => FALSE,
          'type' => 'game_option'
      ),
      'directions' => array(
          'default' => 'cardinal',
          'configurable' => FALSE,
          'type' => 'game_option'
       ),
      'rule_surrounding' => array(
         'title' => 'RULE1',
         'type' => 'rule_variant',
         'configurable' => TRUE,
         'default' => 'loose',
         'widget' => 'radios',
         'choices' => array(
           'throne_access' => 'RULE1_THRONE_ACCESS',
           'strict' => 'RULE1_STRICT',
           'loose' => 'RULE1_LOOSE'
         )
      ),
      'rule_comeback' => array(
         'title' => 'RULE2',
         'type' => 'rule_variant',
         'configurable' => TRUE,
         'default' => 'allowed',
         'widget' => 'radios',
         'choices' => array(
           'never' => 'RULE2_NEVER',
           'surrounding' => 'RULE2_SURROUNDING',
           'allowed' => 'RULE2_ALLOWED'
         )
      ),
      'rule_vassalization' => array(
         'title' => 'RULE3',
         'type' => 'rule_variant',
         'configurable' => TRUE,
         'widget' => 'radios',
         'default' => 'temporary',
         'choices' => array(
           'temporary' => 'RULE3_TEMPORARY',
           'full_control' => 'RULE3_FULL_CONTROL'
         )
      ),
      'rule_canibalism' => array(
         'title' => 'RULE4',
         'type' => 'rule_variant',
         'widget' => 'radios',
         'configurable' => TRUE,
         'default' => 'no',
         'choices' => array(
           'yes' => 'RULE4_YES',
           'vassals' => 'RULE4_VASSALS',
           'no' => 'RULE4_NO',
           'ethical' => 'RULE4_ETHICAL'
         )
      ),
      'rule_self_diplomacy' => array(
         'title' => 'RULE5',
         'type' => 'rule_variant',
         'configurable' => TRUE,
         'widget' => 'radios',
         'default' => 'vassal',
         'choices' => array(
           'never' => 'RULE5_NEVER',
           'vassal' => 'RULE5_VASSAL'
         )
      ),
      'rule_press_liberty' => array(
         'title' => 'RULE6',
         'type' => 'rule_variant',
         'configurable' => TRUE,
         'widget' => 'radios',
         'default' => 'pravda',
         'choices' => array(
           'pravda' => 'RULE6_PRAVDA',
           'foxnews' => 'RULE6_FOXNEWS'
         )
      ),
      'rule_throne_interactions' => array(
          'title' => 'RULE7',
          'type' => 'rule_variant',
          'configurable' => TRUE,
          'widget' => 'radios',
          'default' => 'extended',
          'choices' => array(
            'normal' => 'RULE7_NORMAL',
            'extended' => 'RULE7_EXTENDED'
          )
      ),
    );
  }

  private function setDefaultOptions() {
    $defaults = self::getOptionsInfo();
    foreach ($defaults as $key => $value) {
      $this->setOption($key, $value['default']);
    }
  }

  private function buildField($special_cells = array()) {
    $cells = array();
    $this->buildDirectionsArray();
    for ($x = 1; $x <= $this->cols; $x++) {
      for ($y = 1; $y <= $this->rows; $y++) {
        $cells[self::locateCellByXY($x, $y)] = array('x' => $x, 'y' => $y, 'type' => 'std', 'occupant' => NULL);
      }
    }
    foreach ($special_cells as $key => $type) {
      $cells[$key]['type'] = $type;
    }
    foreach ($cells as $key => $cell) {
      if ($cell['type'] == 'disabled') {
        continue;
      }
      $neighbours = array();
      foreach ($this->directions as $d => $direction) {
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
    return $cells;
  }

  private function buildDirectionsArray() {
    switch($this->getOption('directions')) {
      case('hexagonal'):
        $this->directions = array(
          'NE' => array('x' => 1, 'y' => -1, 'diagonal' => FALSE, 'modulo_x' => TRUE),
          'E' => array('x' => 1, 'y' => 0, 'diagonal' => FALSE),
          'SE' => array('x' => 1, 'y' => 1, 'diagonal' => FALSE, 'modulo_x' => TRUE),
          'SW' => array('x' => -1, 'y' => 1, 'diagonal' => FALSE, 'modulo_x' => TRUE),
          'W' => array('x' => -1, 'y' => 0, 'diagonal' => FALSE),
          'NW' => array('x' => -1, 'y' => -1, 'diagonal' => FALSE, 'modulo_x' => TRUE)
        );
        break;
      case('cardinal'):
      default :
        $this->directions = array(
            'N' => array('x' => 0, 'y' => -1, 'diagonal' => FALSE),
            'NE' => array('x' => 1, 'y' => -1, 'diagonal' => TRUE),
            'E' => array('x' => 1, 'y' => 0, 'diagonal' => FALSE),
            'SE' => array('x' => 1, 'y' => 1, 'diagonal' => TRUE),
            'S' => array('x' => 0, 'y' => 1, 'diagonal' => FALSE),
            'SW' => array('x' => -1, 'y' => 1, 'diagonal' => TRUE),
            'W' => array('x' => -1, 'y' => 0, 'diagonal' => FALSE),
            'NW' => array('x' => -1, 'y' => -1, 'diagonal' => TRUE)
        );
    }
  }

  private function buildHexagonalBattlefieldWith3Factions() {
    $this->rows = 9;
    $this->cols = 9;
    $special_cells = array(
      'E5' => 'throne',
      'A1' => 'disabled',
      'B1' => 'disabled',
      'H1' => 'disabled',
      'I1' => 'disabled',
      'A2' => 'disabled',
      'H2' => 'disabled',
      'I2' => 'disabled',
      'A3' => 'disabled',
      'I3' => 'disabled',
      'I4' => 'disabled',
      'I6' => 'disabled',
      'A7' => 'disabled',
      'I7' => 'disabled',
      'A8' => 'disabled',
      'H8' => 'disabled',
      'I8' => 'disabled',
      'A9' => 'disabled',
      'B9' => 'disabled',
      'H9' => 'disabled',
      'I9' => 'disabled'
    );
    $this->cells = $this->buildField($special_cells);
    $pieces_scheme = new DjambiPieceScheme();
    foreach ($this->factions as $key => $faction) {
      switch ($faction->getStartOrder()) {
        case(1) :
          $start_scheme = array(
            'L'  => array('x' => 1, 'y' => 5),
            'R'  => array('x' => 1, 'y' => 4),
            'M1' => array('x' => 2, 'y' => 3),
            'A'  => array('x' => 1, 'y' => 6),
            'D'  => array('x' => 2, 'y' => 5),
            'M2' => array('x' => 2, 'y' => 4),
            'M3' => array('x' => 2, 'y' => 6),
            'M4' => array('x' => 2, 'y' => 7),
            'N'  => array('x' => 3, 'y' => 5)
          );
          break;
        case(2) :
          $start_scheme = array(
            'L'  => array('x' => 7, 'y' => 9),
            'R'  => array('x' => 6, 'y' => 9),
            'M1' => array('x' => 5, 'y' => 9),
            'A'  => array('x' => 7, 'y' => 8),
            'D'  => array('x' => 6, 'y' => 8),
            'M2' => array('x' => 5, 'y' => 8),
            'M3' => array('x' => 8, 'y' => 7),
            'M4' => array('x' => 7, 'y' => 7),
            'N'  => array('x' => 6, 'y' => 7)
          );
          break;
        case(3) :
          $start_scheme = array(
            'L'  => array('x' => 7, 'y' => 1),
            'R'  => array('x' => 6, 'y' => 1),
            'M1' => array('x' => 5, 'y' => 1),
            'A'  => array('x' => 7, 'y' => 2),
            'D'  => array('x' => 6, 'y' => 2),
            'M2' => array('x' => 5, 'y' => 2),
            'M3' => array('x' => 8, 'y' => 3),
            'M4' => array('x' => 7, 'y' => 3),
            'N'  => array('x' => 6, 'y' => 3)
          );
          break;
      }
      $faction->setBattlefield($this);
      $faction->createPieces($pieces_scheme->getPieceScheme(), $start_scheme);
    }
  }

  private function buildSquareBattlefieldWith4Factions() {
    $this->rows = 9;
    $this->cols = 9;
    $this->cells = $this->buildField(array('E5' => 'throne'));
    $pieces_scheme = new DjambiPieceScheme();
    foreach ($this->factions as $key => $faction) {
      switch ($faction->getStartOrder()) {
        case (4) :
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
        break;
        case (3) :
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
        break;
        case (2) :
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
        break;
        case (1) :
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
        break;
      }
      $faction->setBattlefield($this);
      $faction->createPieces($pieces_scheme->getPieceScheme(), $start_scheme);
    }
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
    $inverted_moves = $this->moves;
    krsort($inverted_moves);
    foreach ($inverted_moves as $key => $move) {
      if ($move['turn'] == $last_turn_key || $move['turn'] == $current_turn_key) {
        $piece = $this->getPieceById($move['target']);
        $position = $cells[$move['from']];
        $piece->setPosition($position['x'], $position['y']);
        if ($move['type'] == 'murder' || $move['type'] == 'elimination') {
          $piece->setAlive(TRUE);
        }
        if ($move['turn'] == $current_turn_key && $move['type'] == 'type' && !empty($move['acting'])) {
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
    if (!empty($summary)) {
      $this->rebuildFactionsControls($summary);
    }
  }

  private function rebuildFactionsControls($summary) {
    foreach ($summary['factions'] as $faction_key => $data) {
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
    else {
      $this->definePlayOrder();
      if ($changes) {
        $this->updateSummary();
      }
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
    // Un camp ne peut pas jouer deux fois de suite après avoir tué un chef ennemi
    if ($new_turn && $nb_factions > 2 && !empty($this->turns) && $this->turns[$this->getCurrentTurnId()]['side'] == $current_order['side']) {
      unset($this->play_order[key($this->play_order)]);
    }
    // Un camp ne peut pas jouer immédiatement après avoir accédé au pouvoir ou s'être retiré du pouvoir
    elseif ($new_turn && $nb_factions == 2) {
      $last_turn_id = $this->getCurrentTurnId();
      foreach($this->moves as $move) {
        if ($move['turn'] == $last_turn_id && !empty($move['special_event'])
            && in_array($move['special_event'], array('THRONE_ACCESS', 'THRONE_RETREAT'))
            && $this->turns[$last_turn_id]['side'] == $current_order['side']) {
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
      $direction = $this->directions[$direction_key];
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

  public function getFreeCells(DjambiPiece $piece, $keep_alive = TRUE, $murder = FALSE) {
    $freecells = array();
    foreach ($this->cells as $key => $cell) {
      if (!isset($cell['occupant'])) {
        if($cell["type"] == 'throne') {
          $can_place_throne = FALSE;
          if ($piece->getHability('access_throne') && $piece->isAlive() && $keep_alive) {
            $can_place_throne = TRUE;
          }
          if ($this->getOption('rule_throne_interactions') == 'extended' && $murder
            && $piece->getHability('access_throne')) {
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

  public function play() {
    if ($this->status == KW_DJAMBI_STATUS_PENDING) {
      if (empty($this->summary)) {
        $this->prepareSummary();
        $this->updateSummary();
      }
      $this->getPlayOrder(TRUE);
      $this->defineMovablePieces();
    }
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
    if ($this->cells[$destination_cell]['type'] == 'throne' && $target_piece->getHability('access_throne') && $target_piece->isAlive()) {
      $special_event = 'THRONE_ACCESS';
    }
    elseif ($this->cells[$destination_cell]['type'] == 'throne' && $target_piece->getHability('access_throne') && !$target_piece->isAlive()) {
      $special_event = 'THRONE_MAUSOLEUM';
    }
    elseif ($this->cells[$origin_cell]['type'] == 'throne' && $target_piece->getHability('access_throne') && $target_piece->isAlive()) {
      $special_event = 'THRONE_RETREAT';
    }
    elseif ($this->cells[$origin_cell]['type'] == 'throne' && $target_piece->getHability('access_throne') && !$target_piece->isAlive()) {
      if ($acting_piece->getHability('kill_throne_leader')) {
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
      'summary' => $this->summary,
      'sequence' => $this->getInfo('sequence')
    );
  }

}