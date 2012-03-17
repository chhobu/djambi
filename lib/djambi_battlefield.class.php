<?php
define("KW_DJAMBI_MODE_SANDBOX", "bac-a-sable");
define("KW_DJAMBI_MODE_HOTCHAIR", "hotchair");
define("KW_DJAMBI_MODE_ROULETTE", "roulette");
define("KW_DJAMBI_MODE_VIP", "vip");
define("KW_DJAMBI_STATUS_PENDING", "pending");
define("KW_DJAMBI_STATUS_FINISHED", "finished");
define("KW_DJAMBI_USER_PLAYING", "playing");
define("KW_DJAMBI_USER_WINNER", "winner");
define("KW_DJAMBI_USER_LOSER", "loser");
define("KW_DJAMBI_USER_DEFECT", "defect");
define("KW_DJAMBI_USER_EMPTY_SLOT", "empty");
define("KW_DJAMBI_USER_READY", "ready");
define("KW_DJAMBI_USER_WAITING", "waiting");

class DjambiBattlefield {
  private $id, $rows, $cols, $cells, $factions, $moves, $mode, $status, $turns, $play_order, $events;

  public function __construct($id, $data, DjambiPoliticalFaction $faction1 = NULL, DjambiPoliticalFaction $faction2 = NULL, DjambiPoliticalFaction $faction3 = NULL, DjambiPoliticalFaction $faction4 = NULL) {
    $this->id = $id;
    if (!is_null($data) && is_array($data)) {
      return $this->loadBattlefield($data);
    }
    elseif (!is_null($faction1) && !is_null($faction2) && !is_null($faction3) && !is_null($faction4)) {
      return $this->buildNewBattlefieldWith4Factions($faction1, $faction2, $faction3, $faction4);
    }
    else {
      return FALSE;
    }
  }

  private function loadBattlefield($data) {
    $this->rows = $data["rows"];
    $this->cols = $data["cols"];
    $this->moves = isset($data["moves"]) ? $data["moves"] : array();
    $this->cells = $this->buildStdField($data["special_cells"]);
    $this->turns = isset($data["turns"]) ? $data["turns"] : array();
    $this->points = isset($data["points"]) ? $data["points"] : 0;
    $this->events = isset($data["events"]) ? $data["events"] : array();
    $this->factions = array();
    $controls = array();
    foreach ($data["factions"] as $key => $faction_data) {
      $faction = new DjambiPoliticalFaction($data["users"][$key], $key, $faction_data["name"], $faction_data["class"], $faction_data["start_order"]);
      $positions = array();
      foreach ($data["positions"] as $cell => $piece_id) {
        $piece_data = explode("-", $piece_id, 2);
        if ($piece_data[0] == $key) {
          $positions[$piece_data[1]] = $this->cells[$cell];
        }
      }
      $faction->setBattlefield($this);
      $faction->setAlive($faction_data["alive"]);
      $faction->createPieces($data["pieces"], $positions, $data["deads"]);
      $this->factions[] = $faction;
      $controls[$key] = $faction_data["control"];
    }
    foreach ($controls as $key_faction => $key_control) {
      $faction = $this->getFactionById($key_faction);
      $faction->setControl($this->getFactionById($key_control), FALSE);
    }
    return $this;
  }

  private function buildStdField($special_cells = array()) {
    $cells = array();
    for ($x = 1; $x <= $this->cols; $x++) {
      for ($y = 1; $y <= $this->rows; $y++) {
        $cells[self::locateCellByXY($x, $y)] = array("x" => $x, "y" => $y, "type" => "std", "occupant" => NULL);
      }
    }
    foreach ($special_cells as $key => $type) {
      $cells[$key]["type"] = $type;
    }
    return $cells;
  }

  private function buildNewBattlefieldWith4Factions(DjambiPoliticalFaction $faction1, DjambiPoliticalFaction $faction2, DjambiPoliticalFaction $faction3, DjambiPoliticalFaction $faction4) {
    $this->rows = 9;
    $this->cols = 9;
    $this->moves = array();
    $this->events = array();
    $this->cells = $this->buildStdField(array("E5" => "throne"));
    $this->factions = array(
      1 => $faction1,
      2 => $faction2,
      3 => $faction3,
      4 => $faction4
    );
    $pieces_scheme = $this->createPiecesScheme();
    foreach ($this->factions as $key => $faction) {
      if ($key == 1) {
        $start_scheme = array(
          "L"  => array("x" => 1, "y" => 1),
          "R"  => array("x" => 1, "y" => 2),
          "M1" => array("x" => 1, "y" => 3),
          "A"  => array("x" => 2, "y" => 1),
          "D"  => array("x" => 2, "y" => 2),
          "M2" => array("x" => 2, "y" => 3),
          "M3" => array("x" => 3, "y" => 1),
          "M4" => array("x" => 3, "y" => 2),
          "N"  => array("x" => 3, "y" => 3)
        );
      }
      elseif ($key == 2) {
        $start_scheme = array(
          "L"  => array("x" => 9, "y" => 1),
          "R"  => array("x" => 9, "y" => 2),
          "M1" => array("x" => 9, "y" => 3),
          "A"  => array("x" => 8, "y" => 1),
          "D"  => array("x" => 8, "y" => 2),
          "M2" => array("x" => 8, "y" => 3),
          "M3" => array("x" => 7, "y" => 1),
          "M4" => array("x" => 7, "y" => 2),
          "N"  => array("x" => 7, "y" => 3)
        );

      }
      elseif ($key == 3) {
        $start_scheme = array(
          "L"  => array("x" => 9, "y" => 9),
          "R"  => array("x" => 9, "y" => 8),
          "M1" => array("x" => 9, "y" => 7),
          "A"  => array("x" => 8, "y" => 9),
          "D"  => array("x" => 8, "y" => 8),
          "M2" => array("x" => 8, "y" => 7),
          "M3" => array("x" => 7, "y" => 9),
          "M4" => array("x" => 7, "y" => 8),
          "N"  => array("x" => 7, "y" => 7)
        );

      }
      elseif ($key == 4) {
        $start_scheme = array(
          "L"  => array("x" => 1, "y" => 9),
          "R"  => array("x" => 1, "y" => 8),
          "M1" => array("x" => 1, "y" => 7),
          "A"  => array("x" => 2, "y" => 9),
          "D"  => array("x" => 2, "y" => 8),
          "M2" => array("x" => 2, "y" => 7),
          "M3" => array("x" => 3, "y" => 9),
          "M4" => array("x" => 3, "y" => 8),
          "N"  => array("x" => 3, "y" => 7)
        );
      }
      $faction->setBattlefield($this);
      $faction->createPieces($pieces_scheme, $start_scheme);
    }
    $this->logEvent("info", "NEW_DJAMBI_GAME");
    return $this;
  }

  public static function locateCell($position) {
    return DjambiBattlefield::locateCellByXY($position["x"], $position["y"]);
  }

  public static function locateCellByXY($x, $y) {
    return DjambiBattlefield::intToAlpha($x) . $y;
  }

  private function createPiecesScheme() {
    $militant_habilities = array("limited_move" => 2, "kill_by_attack" => TRUE);
    return array(
      "L" => array(
        "shortname" => "Lea",
        "longname" => "Leader",
        "type" => "leader",
        "habilities" => array("kill_throne_leader" => TRUE, "access_throne" => TRUE, "kill_by_attack" => TRUE, "must_live" => TRUE)
      ),
      "R" => array(
        "shortname" => "Rep",
        "longname" => "Reporter",
        "type" => "reporter",
        "habilities" => array("kill_by_proximity" => TRUE, "kill_throne_leader" => TRUE)
      ),
      "M1" => array(
        "shortname" => "M#1",
        "longname" => "Militant #1",
        "type" => "militant",
        "habilities" => $militant_habilities
      ),
      "M2" => array(
        "shortname" => "M#2",
        "longname" => "Militant #2",
        "type" => "militant",
        "habilities" => $militant_habilities
      ),
      "M3" => array(
        "shortname" => "M#3",
        "longname" => "Militant #3",
        "type" => "militant",
        "habilities" => $militant_habilities
      ),
      "M4" => array(
        "shortname" => "M#4",
        "longname" => "Militant #4",
        "type" => "militant",
        "habilities" => $militant_habilities
      ),
      "A" => array(
        "shortname" => "Sni",
        "longname" => "Sniper",
        "type" => "assassin",
        "habilities" => array("kill_by_attack" => TRUE, "kill_signature" => TRUE, "kill_throne_leader" => TRUE)
      ),
      "D" => array(
        "shortname" => "Dip",
        "longname" => "Diplomat",
        "type" => "diplomate",
        "habilities" => array("move_living_pieces" => TRUE)
      ),
      "N" => array(
        "shortname" => "Nec",
        "longname" => "Necromobil",
        "type" => "necromobile",
        "habilities" => array("move_dead_pieces" => TRUE)
      )
    );
  }

  public static function intToAlpha($int, $inverse = FALSE) {
    $alpha = array("#", "A", "B", "C", "D", "E", "F", "G", "H", "I", "F", "G", "H", "I", "J", "K",
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
        if ($move['type'] == 'murder') {
          $piece->setDead(TRUE);
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
        if ($event['event'] == 'GAME_OVER') {
          $faction = $this->getFactionById($event['args']['!id']);
          $faction->setAlive(TRUE);
          $pieces = $faction->getPieces();
          foreach ($pieces as $key => $piece) {
            if ($piece->getHability('must_live')) {
              $piece->setDead(TRUE);
            }
          }
        }
        if ($event['event'] == 'CHANGING_SIDE') {
          $faction = $this->getFactionById($event['args']['!old_id']);
          $faction->setControl($faction, FALSE);
          if (!empty($events['args']['!controlled'])) {
            foreach ($events['args']['!controlled'] as $controlled_faction) {
              $controlled_faction->setControl($faction, FALSE);
            }
          }
        }
        if ($event['turn'] == $last_turn_key && in_array($event['event'], array('NEW_DJAMBI_GAME', 'NEW_TURN', 'TURN_BEGIN'))) {
          continue;
        }
        unset($this->events[$key]);
      }
    }
  }

  public function changeTurn() {
    // Log de la fin du tour
    $last_turn_key = $this->getCurrentTurnId();
    $this->turns[$last_turn_key]["end"] = time();
    // Vérification des conditions de victoire
    $living_factions = array();
    /* @var $faction DjambiPoliticalFaction */
    foreach ($this->getFactions() as $key => $faction) {
      if ($faction->isAlive()) {
        $control_leader = FALSE;
        $control_necro = FALSE;
        $leaders = array();
        $pieces = $faction->getControlledPieces();
        /* @var $piece DjambiPiece */
        foreach ($pieces as $key => $piece) {
          if ($piece->isAlive()) {
            // Contrôle 1 : chef vivant ?
            if ($piece->getHability("must_live")) {
              $control_leader = TRUE;
              $leaders[] = $piece;
            }
            // Contrôle 2 : nécromobile vivant ?
            if ($piece->getHability("move_dead_pieces")) {
              $control_necro = TRUE;
            }
          }
        }
        // Contrôle 3 : case pouvoir atteignable par le chef ?
        if ($control_leader && !$control_necro) {
          $control_leader = $this->checkLeaderFreedom($leaders);
          if (!$control_leader) {
            $this->logEvent(
              "event",
              "SURROUNDED",
              array(
                "!class" => $faction->getClass(),
                "!!faction" => $faction->getName()
              ));
            foreach ($leaders as $leader) {
              $leader->setDead();
            }
            $faction->setDead();
          }
        }
        if ($control_leader) {
          $living_factions[] = $faction->getId();
        }
      }
    }
    $total = count($living_factions);
    if ($total < 2) {
      $this->logEvent("event", "END");
      if ($total == 0) {
        $this->logEvent("event", "DRAW");
      }
      else {
        $winner_id = current($living_factions);
        $winner = $this->getFactionById($winner_id);
        $this->logEvent('event', 'THE_WINNER_IS',
          array('!id' => $winner->getId(), '!class' => $winner->getClass(), '!!faction' => $winner->getName()));
      }
      $this->setStatus(KW_DJAMBI_STATUS_FINISHED);
      return;
    }
    // Attribution des pièces mortes à l'occupant du trône
    foreach ($this->getFactions() as $faction) {
      if (!$faction->getControl()->isAlive()) {
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
        if (!empty($kings)) {
          $kings = array_unique($kings);
          if (count($kings) == 1) {
            $faction->setControl($this->getFactionById(current($kings)));
          }
        }
      }
    }
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
    if (!empty($thrones)) {
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
        if ($move['turn'] == $last_turn_id && in_array($move['special_event'], array('throne access', 'throne retreat')) && $this->turns[$last_turn_id]['side'] == $current_order['side']) {
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
    $selected_faction->isPlaying(TRUE);
    if ($new_turn) {
      $this->turns[] = array(
        "begin" => time(),
        "end" => NULL,
        "side" => $current_order["side"],
        "turn_scheme" => $current_order["turn_scheme"],
        "turn" => $current_phase
      );
      $this->logEvent("notice", "TURN_BEGIN",
        array(
          "!class" => $selected_faction->getClass(),
          "!!faction" => $selected_faction->getName()
        )
      );
    }
    if ($new_phase) {
      $this->logEvent("notice", "NEW_TURN"
        , array("!turn" => $current_phase));
    }
    return TRUE;
  }

  private function defineMovablePieces() {
    /* @var $active_faction DjambiPoliticalFaction */
    $current_order = current($this->play_order);
    $active_faction = $this->getFactionById($current_order["side"]);
    /* @var $piece DjambiPiece */
    foreach ($active_faction->getControlledPieces() as $key => $piece) {
      $piece->buildAllowableMoves();
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

  private function checkLeaderFreedom($leaders) {
    $thrones = $this->getSpecialCells("throne");
    $checked = array();
    /* @var $leader DjambiPiece */
    foreach ($leaders as $leader) {
      $position = $leader->getPosition();
      $alternate_position = self::locateCell($position);
      if (in_array($alternate_position, $thrones)) {
        return TRUE;
      }
      $checked[$alternate_position] = $position;
      $check_further[$alternate_position] = $position;
      while (!empty($check_further)) {
        $position = current($check_further);
        $next_positions = $this->findNeighbourCells($position);
        foreach ($next_positions as $key => $coord) {
          $blocked = FALSE;
          $alternate_position = self::locateCell($coord);
          if (!isset($checked[$alternate_position])) {
            if (!empty($this->cells[$alternate_position]["occupant"])) {
              $occupant = $this->cells[$alternate_position]["occupant"];
              if (!$occupant->isAlive() && $this->cells[$alternate_position]["type"] != "throne") {
                $blocked = TRUE;
              }
              elseif(in_array($alternate_position, $thrones)) {
                return TRUE;
              }
            }
            elseif (in_array($alternate_position, $thrones)) {
              return TRUE;
            }
            if (!$blocked) {
              $check_further[$alternate_position] = $coord;
            }
            $checked[$alternate_position] = $coord;
          }
        }
        unset($check_further[key($check_further)]);
      }
    }
    return FALSE;
  }

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
    }
  }

  public function logEvent($type, $event_txt, $event_args = NULL, DjambiPoliticalFaction $from = NULL, DjambiPoliticalFaction $to = NULL) {
    $event = array(
      "turn" => $this->getCurrentTurnId(),
      "time" => time(),
      "type" => $type,
      "event" => $event_txt,
      "args" => $event_args
    );
    if (!is_null($from)) {
      $event["from"] = $from->getId();
    }
    if (!is_null($to)) {
      $event["to"] = $to;
    }
    $this->events[] = $event;
  }

  private function getCurrentTurnId() {
    return empty($this->turns) ? 0 : max(array_keys($this->turns));
  }

  public function logMove(DjambiPiece $target_piece, $destination, $type = "move", DjambiPiece $acting_piece = NULL) {
    $destination_cell = self::locateCell($destination);
    $origin_cell = self::locateCell($target_piece->getPosition());
    if ($this->cells[$destination_cell]["type"] == "throne" && $target_piece->getType() == "leader" && $target_piece->isAlive()) {
      $special_event = "throne access";
    }
    elseif ($this->cells[$origin_cell]["type"] == "throne" && $target_piece->getType() == "leader" && $target_piece->isAlive()) {
      $special_event = "throne retreat";
    }
    elseif ($this->cells[$origin_cell]["type"] == "throne" && $target_piece->getType() == "leader" && !$target_piece->isAlive()) {
      $special_event = "throne evacuation";
    }
    else {
      $special_event = NULL;
    }
    $move = array(
      "turn" => $this->getCurrentTurnId(),
      "time" => time(),
      "target_faction" => $target_piece->getFaction()->getControl()->getId(),
      "target" => $target_piece->getId(),
      "from" => $origin_cell,
      "to" => $destination_cell,
      "type" => $type,
      "acting" => is_null($acting_piece) ? NULL : $acting_piece->getId(),
      "acting_faction" => is_null($acting_piece) ? NULL : $acting_piece->getFaction()->getControl()->getId(),
      "special_event" => $special_event
    );
    $this->moves[] = $move;
  }

  public function toDatabase() {
    $positions = array();
    $pieces = array();
    $factions = array();
    $deads = array();
    $special_cells = array();
    foreach ($this->cells as $key => $cell) {
      if (isset($cell["type"]) && $cell["type"] != "std") {
        $special_cells[$key] = $cell["type"];
      }
      if (isset($cell["occupant"])) {
        $piece = $cell["occupant"];
        $positions[$key] = $piece->getId();
        if (!$piece->isAlive()) {
          $deads[] = $piece->getId();
        }
        if (!isset($pieces[$piece->getShortname()])) {
          $pieces[$piece->getShortname()] = $piece->toDatabase();
        }
      }
    }
    foreach ($this->factions as $key => $faction) {
      $factions[$faction->getId()] = $faction->toDatabase();
    }
    return array(
      "rows" => $this->rows,
      "cols" => $this->cols,
      "positions" => $positions,
      "pieces" => $pieces,
      "factions" => $factions,
      "moves" => $this->moves,
      "turns" => isset($this->turns) ? $this->turns : array(),
      "points" => isset($this->points) ? $this->points : 0,
      "deads" => $deads,
      "special_cells" => $special_cells,
      "events" => $this->events
    );
  }

  public function toHtml() {
    return drupal_get_form("kw_djambi_game_form", $this);
  }
}