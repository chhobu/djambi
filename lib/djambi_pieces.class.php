<?php
class DjambiPiece {
  private $faction, $alive, $shortname, $longname, $type, $habilities, $position, $image, $movable,
    $allowable_moves;

  public function __construct(DjambiPoliticalFaction $faction, $shortname, $longname, $type, $x, $y, $alive) {
    $this->faction = $faction;
    $this->alive = $alive;
    $this->shortname = $shortname;
    $this->longname = $longname;
    $this->type = $type;
    $this->image = drupal_get_path("module", "kw_djambi"). "/img/" . $type . ".png";
    $this->habilities = array(
      "limited_move"       => FALSE,
      "access_throne"      => FALSE,
      "kill_throne_leader" => FALSE,
      "move_dead_pieces"   => FALSE,
      "move_living_pieces" => FALSE,
      "kill_by_proximity"  => FALSE,
      "kill_by_attack"     => FALSE,
      "kill_signature"     => FALSE,
      "must_live"          => FALSE
    );
    $this->setPosition($x, $y);
    $this->movable = FALSE;
    $this->allowable_moves = array();
    $this->id = $faction->getId()."-".$this->shortname;
    return $this;
  }

  public function getId() {
    return $this->id;
  }

  public function getShortname($mode = NULL) {
    if ($mode == "html") {
      return "<abbr title=\"". $this->longname ."\">" . $this->shortname . "</abbr>";
    }
    elseif ($mode == "t") {
      return t($this->shortname);
    }
    return $this->shortname;
  }

  public function getLongname() {
    return $this->longname;
  }

  public function getType() {
    return $this->type;
  }

  public function getFaction() {
    return $this->faction;
  }

  public function getImage() {
    return $this->image;
  }

  public function getHability($name) {
    return $this->habilities[$name];
  }

  public function setHability($name, $value) {
    $this->habilities[$name] = $value;
    return $this;
  }

  public function getPosition() {
    return $this->position;
  }

  public function setPosition($x, $y) {
    $current_position = isset($this->position) ? $this->position : NULL;
    $this->position = array("x" => $x, "y" => $y);
    $this->faction->getBattlefield()->placePiece($this, $current_position);
    return $this;
  }

  public function isAlive() {
    return $this->alive;
  }

  public function setDead($ressucite = FALSE) {
    $this->alive = $ressucite;
    return $this;
  }

  public function setMovable($movable) {
    $this->movable = $movable;
    return $this;
  }

  public function isMovable() {
    return $this->movable;
  }

  public function setAllowableMoves($array) {
    $this->allowable_moves = $array;
    return $this;
  }

  public function getAllowableMoves() {
    return $this->allowable_moves;
  }

  public function buildAllowableMoves($allow_interactions = TRUE) {
    $cells = $this->getFaction()->getBattlefield()->getCells();
    $rows = $this->getFaction()->getBattlefield()->getRows();
    $cols = $this->getFaction()->getBattlefield()->getCols();
    if (!$this->isAlive()) {
      return;
    }
    $next_case = array();
    $position = $this->getPosition();
    $next_case[] = array("delta_x" => -1, "delta_y" => 1);
    $next_case[] = array("delta_x" => -1, "delta_y" => 0);
    $next_case[] = array("delta_x" => -1, "delta_y" => -1);
    $next_case[] = array("delta_x" => 0, "delta_y" => 1);
    $next_case[] = array("delta_x" => 0, "delta_y" => -1);
    $next_case[] = array("delta_x" => 1, "delta_y" => 0);
    $next_case[] = array("delta_x" => 1, "delta_y" => 1);
    $next_case[] = array("delta_x" => 1, "delta_y" => -1);
    foreach ($next_case as $key_case => $case) {
      $next_case[$key_case]["x"] = $position["x"] + $case["delta_x"];
      $next_case[$key_case]["y"] = $position["y"] + $case["delta_y"];
      if ($next_case[$key_case]["x"] < 1 || $next_case[$key_case]["x"] > $cols ||
        $next_case[$key_case]["y"] < 1 || $next_case[$key_case]["y"] > $rows) {
        unset($next_case[$key_case]);
        continue;
      }
      $cell = DjambiBattlefield::locateCell($next_case[$key_case]);
      $move_ok = $this->getFaction()->getBattlefield()->checkAvailableCell($this, $cell, $allow_interactions);
      if (!$move_ok && isset($cells[$cell]["occupant"])) {
        unset($next_case[$key_case]);
        continue;
      }
      elseif (!isset($cells[$cell]["occupant"])) {
        $obstacle = FALSE;
        for ($i = 2; $obstacle == FALSE; $i++) {
          if ($this->getHability("limited_move") && $i > $this->getHability("limited_move")) {
            $obstacle = TRUE;
          }
          else {
            $next_next_case = array("x" => $position["x"] + $next_case[$key_case]["delta_x"] * $i,
              "y" => $position["y"] + $next_case[$key_case]["delta_y"] * $i);
            if ($next_next_case["x"] < 1 || $next_next_case["x"] > $cols ||
              $next_next_case["y"] < 1 || $next_next_case["y"] > $rows) {
              $obstacle = TRUE;
            }
            else {
              $next_cell = DjambiBattlefield::locateCell($next_next_case);
              $test = $this->getFaction()->getBattlefield()->checkAvailableCell($this, $next_cell, $allow_interactions);
              if ($test) {
                $next_case[] = $next_next_case;
              }
              if (isset($cells[$next_cell]["occupant"])) {
                $obstacle = TRUE;
              }
            }
          }
        }
        if ($cells[$cell]["type"] == "throne" && !$this->getHability("access_throne")) {
          unset($next_case[$key_case]);
        }
      }
    }
    if (count($next_case) > 0) {
      $this->setMovable(TRUE);
      $this->setAllowableMoves($next_case);
      foreach ($next_case as $key => $case) {
        $cell = DjambiBattlefield::locateCell($case);
        $this->getFaction()->getBattlefield()->updateCell($cell, "reachable", TRUE);
      }
    }
  }

  private function checkNewPosition($destination) {
    $cells = $this->faction->getBattlefield()->getCells();
    if (is_array($destination)) {
      $destination = DjambiBattlefield::locateCell($destination);
    }
    if (!isset($cells[$destination])) {
      return FALSE;
    }
    return $cells[$destination];
  }

  public function move($destination, $allow_interactions = TRUE) {
    $interactions = array();
    $current_position = $this->position;
    $move_ok = FALSE;
    $destination = $this->checkNewPosition($destination);
    if ($destination && ($destination["x"] != $current_position["x"] || $destination["y"] && $current_position["y"])) {
      if (isset($destination["occupant"])) {
        /* @var $target DjambiPiece */
        $target = $destination["occupant"];
        if ($target->isAlive() && $this->getHability("move_living_pieces") && $allow_interactions
          && $target->getFaction()->getControl()->getId() != $this->getFaction()->getControl()->getId()) {
          $interactions[] = array("type" => "manipulation", "target" => $target);
          $move_ok = TRUE;
        }
        elseif (!$target->isAlive() && $this->getHability("move_dead_pieces") && $allow_interactions) {
          $interactions[] = array("type" => "necromobility", "target" => $target);
          $move_ok = TRUE;
        }
        elseif ($target->isAlive()) {
          // Signature de l'assassin
          if ($this->getHability("kill_signature") && $allow_interactions) {
            $this->kill($target, $current_position);
            $move_ok = TRUE;
          }
          // Déplacement du corps de la victime
          elseif ($this->getHability("kill_by_attack") && $allow_interactions) {
            $interactions[] = array("type" => "murder", "target" => $target, "default" => $current_position);
            $move_ok = TRUE;
          }
        }
      }
      else {
        // Eventuel choix de la victime du reporter
        if ($this->getHability("kill_by_proximity") && $allow_interactions) {
          $grid = $this->faction->getBattlefield();
          $next_cells = $grid->findNeighbourCells($destination, FALSE);
          $cells = $grid->getCells();
          $victims = array();
          foreach ($next_cells as $cell) {
            $key = DjambiBattlefield::locateCell($cell);
            if (!empty($cells[$key]["occupant"])) {
              $occupant = $cells[$key]["occupant"];
              if ($occupant->isAlive() && $occupant->getFaction()->getControl()->getId() != $this->getFaction()->getControl()->getId()) {
                $victims[$key] = $occupant;
              }
            }
          }
          if (count($victims) > 1) {
            $interactions[] = array("type" => "reportage", "reporter" => $this, "victims" => $victims);
          }
          elseif (count($victims) == 1) {
            $victim = current($victims);
            $this->kill($victim, $victim->getPosition());
          }
        }
        $move_ok = TRUE;
      }
      if ($move_ok) {
        $this->faction->getBattlefield()->logMove($this, $destination, 'move', isset($destination['occupant']) ? $destination['occupant'] : NULL);
        $this->setPosition($destination["x"], $destination["y"]);
        // Mouvement supplémentaire pour évacuer le trône
        if (!$this->getHability("access_throne") && $destination["type"] == "throne") {
          $interactions[] = array("type" => "throne_evacuation", "target" => $this);
        }
      }
    }
    if (!$move_ok) {
      $interactions[] = array("type" => "piece_destination", "target" => $this);
      return $interactions;
    }
    return $interactions;
  }

  public function kill(DjambiPiece $victim, $destination) {
    $destination = $this->checkNewPosition($destination);
    $victim->setDead();
    $this->faction->getBattlefield()->logMove($victim, $destination, "murder", $this);
    $victim->setPosition($destination["x"], $destination["y"]);
    if ($victim->getHability("must_live")) {
      $victim->getFaction()->setDead();
      $victim->getFaction()->setControl($this->faction->getControl());
      $this->faction->getBattlefield()->getPlayOrder(TRUE);
    }
  }

  public function manipulate(DjambiPiece $victim, $destination) {
    $destination = $this->checkNewPosition($destination);
    $this->faction->getBattlefield()->logMove($victim, $destination, "manipulation", $this);
    $victim->setPosition($destination["x"], $destination["y"]);
  }

  public function necromove(DjambiPiece $victim, $destination) {
    $destination = $this->checkNewPosition($destination);
    $this->faction->getBattlefield()->logMove($victim, $destination, "necromobility", $this);
    $victim->setPosition($destination["x"], $destination["y"]);
  }

  public function toDatabase() {
    return array(
      "habilities" => $this->habilities,
      "shortname" => $this->getShortname(),
      "longname" => $this->getLongname(),
      "image" => $this->getImage(),
      "type" => $this->getType()
    );
  }

}