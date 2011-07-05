<?php
define("KW_DJAMBI_MODE_SANDBOX", "bac-a-sable");
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
    $this->logEvent("info", "New djambi chess game created."); // Translatable
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
        "shortname" => "Lea", // Translatable
        "longname" => "Leader", // Translatable
        "type" => "leader", 
        "habilities" => array("access_throne" => TRUE, "kill_by_attack" => TRUE, "must_live" => TRUE)
      ),
      "R" => array(
        "shortname" => "Rep", // Translatable
        "longname" => "Reporter", // Translatable
        "type" => "reporter",
        "habilities" => array("kill_by_proximity" => TRUE, "kill_throne_leader" => TRUE)
      ),
      "M1" => array(
        "shortname" => "M#1", // Translatable
        "longname" => "Militant #1", // Translatable
        "type" => "militant", 
        "habilities" => $militant_habilities
      ),
      "M2" => array(
        "shortname" => "M#2", // Translatable
        "longname" => "Militant #2", // Translatable
        "type" => "militant", 
        "habilities" => $militant_habilities
      ),
      "M3" => array(
        "shortname" => "M#3", // Translatable
        "longname" => "Militant #3", // Translatable
        "type" => "militant", 
        "habilities" => $militant_habilities
      ),
      "M4" => array(
        "shortname" => "M#4", // Translatable
        "longname" => "Militant #4", // Translatable
        "type" => "militant", 
        "habilities" => $militant_habilities
      ),
      "A" => array(
        "shortname" => "Sni", // Translatable
        "longname" => "Sniper", // Translatable
        "type" => "assassin", 
        "habilities" => array("kill_by_attack" => TRUE, "kill_signature" => TRUE, "kill_throne_leader" => TRUE)
      ),
      "D" => array(
        "shortname" => "Dip", // Translatable
        "longname" => "Diplomat", // Translatable
        "type" => "diplomate", 
        "habilities" => array("move_living_pieces" => TRUE)
      ),
      "N" => array(
        "shortname" => "Nec", // Translatable
        "longname" => "Necromobil", // Translatable
        "type" => "necromobile",
        "habilities" => array("move_dead_pieces" => TRUE)
      )
    );
  }
  
  public static function intToAlpha($int) {
    $alpha = array("#", "A", "B", "C", "D", "E", "F", "G", "H", "I", "F", "G", "H", "I", "J", "K",
      "L", "M", "N", "O", "P", "Q", "R", "S", "T", "U", "V", "W", "X", "Y", "Z");
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
    $play_order = $this->getPlayOrder();
    return $this->getFactionById($play_order[0]["side"]);
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
  
  public function changeTurn() {
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
            $this->logEvent("event", "<span class='faction !class'>!!faction</span>'s side is surrounded by dead pieces and cannot access to power anymore.", // Translatable
              array("!class" => $faction->getClass(), "!!faction" => $faction->getName()));
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
      $this->logEvent("event", "End of the game !"); // Translatable
      if ($total == 0) {
        $this->logEvent("event", "This is a draw..."); // Translatable
      }
      else {
        $winner_id = current($living_factions);
        $winner = $this->getFactionById($winner_id);
        $this->logEvent("event", "<span class='faction !class'>!!faction</span> wins !!!", // Translatable
          array("!class" => $winner->getClass(), "!!faction" => $winner->getName()));
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
    // Changement de tour
    $play_order = $this->getPlayOrder();
    $is_new_turn = FALSE;
    if (!empty($this->turns)) {
      array_shift($play_order);
      $current_play_order = current($play_order);
      $now_playing = $current_play_order["side"];
      $type_turn = $current_play_order["type"];
      $current_turn_array = end($this->turns);
      $current_turn = $current_turn_array["turn"];
      $played_turns = array();
      foreach ($this->turns as $turn) {
        if ($turn["turn"] == $current_turn && !empty($turn["side"])) {
          $played_turns[$turn["side"]] = 1;
        }
      }
      $alive_factions = array();
      /* @var $faction DjambiPoliticalFaction */
      foreach ($this->getFactions() as $faction) {
        if ($faction->isAlive()) {
          $alive_factions[$faction->getId()] = 1;
        }
      }
      if (array_intersect_key($alive_factions, $played_turns) == $alive_factions) {
        $current_turn++;
        $is_new_turn = TRUE;
      }
    }
    else {
      $now_playing = $play_order[0]["side"];
      $is_new_turn = TRUE;
      $current_turn = 1;
      $type_turn = "std";
    }
    $this->turns[] = array(
      "side" => $now_playing,
      "begin" => time(),
      "turn" => $current_turn,
      "type" => $type_turn
    );
    if ($is_new_turn) {
      $this->logEvent("notice", "Turn !turn begins." // Translatable
        , array("!turn" => $current_turn));
    }
    $faction = $this->getFactionById($now_playing);
    $this->logEvent("notice", "<span class='faction !class'>!!faction</span> turn begins.", // Translatable
      array("!class" => $faction->getClass(), "!!faction" => $faction->getName()));
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
      if ($faction->isAlive()) {
        $orders["orders"][] = $faction->getStartOrder();
        $orders["factions"][] = $faction->getId();
        $nb_factions++;
      }
    }
    array_multisort($orders["orders"], $orders["factions"]);
    if (count($this->turns) == 0) {
      foreach($orders["factions"] as $faction) {
        $this->play_order[] = array("side" => $faction, "type" => "std");
      }
      $current_turn = NULL;
    }
    else {
      $turns = $this->turns;
      $current_turn = array_pop($turns);
      $already_played_in_this_turn = array();
      foreach($turns as $key => $turn) {
        if ($turn["turn"] == $current_turn["turn"] && $turn["type"] == "std" && !in_array($turn["side"], $already_played_in_this_turn)) {
          $faction = $this->getFactionById($turn["side"]);
          if ($faction->isAlive()) {
            $already_played_in_this_turn[] = $turn["side"];
          }
        }
      }
      $nb_players = count($orders["factions"]);
      foreach ($already_played_in_this_turn as $key => $faction) {
        unset($orders["factions"][array_search($faction, $orders["factions"])]);
        $orders["factions"][] = $faction;
      }
      foreach ($orders["factions"] as $faction) {
        $this->play_order[] = array(
          "side" => $faction,
          "type" => "std"
        );
      }
      $rulers = array();
      $thrones = $this->getSpecialCells("throne");
      if (!empty($thrones)) {
        foreach ($thrones as $throne) {
          if (isset($this->cells[$throne]) && !empty($this->cells[$throne]["occupant"])) {
            $piece = $this->cells[$throne]["occupant"];
            if ($piece->getHability("access_throne") && $piece->isAlive()) {
              $rulers[] = $piece->getFaction()->getControl()->getId();
            }
          }
        }
      }
      // FIXME l'ordre des tours avec une pièce au pouvoir n'est pas bien géré
      if (!empty($rulers)) {
        $rulers = array_unique($rulers);
        $new_order = array();
        if($current_turn["type"] == "extra") {
          $new_order[] = array(
            "side" => $current_turn["side"],
            "type" => "extra"
          );
        }
        foreach ($this->play_order as $order) {
          $new_order[] = array(
            "side" => $order["side"],
            "type" => "std"
          );
          foreach ($rulers as $ruler) {
            if ($order["side"] != $ruler) {
              $new_order[] = array(
                "side" => $ruler,
                "type" => "extra",
              );
            }
          }
        }
        $last = NULL;
        foreach ($new_order as $key => $order) {
          if ($order["side"] == $last && $nb_factions > 2) {
            unset($new_order[$key]);
          }
          else {
            $last = $order["side"];
          }
        }
        $this->play_order = $new_order;
      }
    }
    $current_order = current($this->play_order);
    $selected_faction = $this->getFactionById($current_order["side"]);
    $selected_faction->isPlaying(TRUE);
    if (count($this->play_order) < 4) {
      $i = 0;
      while(count($this->play_order) < 4) {
        if ($i == 0 && current($orders["factions"])) {
          $this->play_order[] = array(
            "side" => current($orders["factions"]),
            "type" => "std"
          );
        }
        else {
          $next = next($orders["factions"]);
          if ($next === FALSE) {
            $next = reset($orders["factions"]);
          }
          $this->play_order[] = array(
            "side" => $next,
            "type" => "std"
          );
        }
        $i++;
      }
    }
    //__debug($this->play_order);
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
    // FIXME si leader tué par un reporter au pouvoir, les camps n'ayant plus de nécro doivent-ils être éliminés ???
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
              if (!$occupant->isAlive()) {
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
      if (empty($this->turns)) {
        $this->changeTurn();
      }
      $this->defineMovablePieces();
    }
  }
  
  public function logEvent($type, $event_txt, $event_args = NULL, DjambiPoliticalFaction $from = NULL, DjambiPoliticalFaction $to = NULL) {
    $event = array(
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
  
  public static function explainMode($mode, $return_type = "std") {
    switch($mode) {
      case(KW_DJAMBI_MODE_SANDBOX):
        $mode_explained = "Sandbox"; // Translatable
        break;
      default:
        $mode_explained = $mode;
    }
    return $return_type == "t" ? t($mode_explained) : $mode_explained;
  }
  
  public static function explainStatus($status, $return_type) {
    switch($status) {
      case(KW_DJAMBI_STATUS_PENDING):
        $status_explained = "Game in progress"; // Translatable
        break;
      default:
        $status_explained = $status;
    }
    return $return_type == "t" ? t($status_explained) : $status_explained;
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