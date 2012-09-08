<?php
class DjambiPiece {
  private $faction, $alive, $shortname, $longname, $type, $habilities, $position, $image, $movable,
    $allowable_moves;

  public function __construct(DjambiPoliticalFaction $faction, $piece_scheme_id, $shortname, $longname, $type, $x, $y, $alive) {
    $this->faction = $faction;
    $this->alive = $alive;
    $this->shortname = $shortname;
    $this->longname = $longname;
    $this->type = $type;
    $this->image = drupal_get_path("module", "kw_djambi"). "/img/" . $type . ".png";
    $this->habilities = array(
      'limited_move'       => FALSE,
      'access_throne'      => FALSE,
      'kill_throne_leader' => FALSE,
      'move_dead_pieces'   => FALSE,
      'move_living_pieces' => FALSE,
      'kill_by_proximity'  => FALSE,
      'kill_by_attack'     => FALSE,
      'kill_signature'     => FALSE,
      'must_live'          => FALSE
    );
    $this->setPosition($x, $y);
    $this->movable = FALSE;
    $this->allowable_moves = array();
    $this->id = $faction->getId() . '-' . $piece_scheme_id;
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

  public function setAlive($value) {
    $this->alive = $value;
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
    if (!$this->isAlive()) {
      return;
    }
    $cells = $this->getFaction()->getBattlefield()->getCells();
    $rows = $this->getFaction()->getBattlefield()->getRows();
    $cols = $this->getFaction()->getBattlefield()->getCols();
    $directions = $this->getFaction()->getBattlefield()->getDirections();
    $current_cell = $cells[DjambiBattlefield::locateCell($this->getPosition())];
    if (!empty($current_cell['neighbours'])) {
      $next_cases = $current_cell['neighbours'];
      foreach ($next_cases as $direction => $cell) {
        $move_ok = $this->checkAvailableMove($cell, $allow_interactions);
        if (!$move_ok && isset($cells[$cell]['occupant'])) {
          unset($next_cases[$direction]);
          continue;
        }
        elseif (!isset($cells[$cell]['occupant'])) {
          $obstacle = FALSE;
          $next_cell = $cell;
          for ($i = 2; $obstacle == FALSE; $i++) {
            if ($this->getHability('limited_move') && $i > $this->getHability('limited_move')) {
              $obstacle = TRUE;
            }
            else {
              if (!isset($cells[$next_cell]['neighbours'][$direction])) {
                $obstacle = TRUE;
              }
              else {
                $next_cell = $cells[$next_cell]['neighbours'][$direction];
                $test = $this->checkAvailableMove($next_cell, $allow_interactions);
                if ($test) {
                  if (!in_array($next_cell, $next_cases)) {
                    $next_cases[$direction . $i] = $next_cell;
                  }
                  else {
                    $obstacle = TRUE;
                  }
                }
                if (isset($cells[$next_cell]['occupant'])) {
                  $obstacle = TRUE;
                }
              }
            }
          }
          if ($cells[$cell]['type'] == 'throne' && !$this->getHability('access_throne')) {
            unset($next_cases[$direction]);
          }
        }
      }
    }
    if (!empty($next_cases)) {
      $this->setMovable(TRUE);
      $this->setAllowableMoves($next_cases);
      foreach ($next_cases as $cell) {
        $this->getFaction()->getBattlefield()->updateCell($cell, 'reachable', TRUE);
      }
    }
    return count($this->allowable_moves);
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
    $kills = array();
    $current_position = $this->position;
    $move_ok = FALSE;
    $extra_interaction = FALSE;
    $destination = $this->checkNewPosition($destination);
    if (!$allow_interactions && $this->getFaction()->getBattlefield()->getOption('rule_throne_interactions') == 'extended') {
      $cells = $this->getFaction()->getBattlefield()->getCells();
      $current_cell = $cells[DjambiBattlefield::locateCell($current_position)];
      if ($current_cell['type'] == 'throne' && isset($destination['occupant'])) {
        $target = $destination['occupant'];
        if ($target->getHability('access_throne')) {
          $extra_interaction = TRUE;
        }
      }
    }
    if ($destination && ($destination["x"] != $current_position["x"] || $destination["y"] && $current_position["y"])) {
      if (isset($destination["occupant"])) {
        /* @var $target DjambiPiece */
        $target = $destination["occupant"];
        if ($this->getFaction()->getBattlefield()->getOption('rule_self_diplomacy') == 'vassal') {
          $can_manipulate = $target->getFaction()->getId() != $this->getFaction()->getId();
        }
        else {
          $can_manipulate = $target->getFaction()->getControl()->getId() != $this->getFaction()->getControl()->getId();
        }
        if ($target->isAlive() && $this->getHability("move_living_pieces") && ($allow_interactions || $extra_interaction)
          && $can_manipulate) {
          if ($allow_interactions) {
            $interactions[] = array("type" => "manipulation", "target" => $target);
          }
          elseif ($extra_interaction) {
            $target->move($current_position);
            $this->getFaction()->getBattlefield()->logEvent('event', 'DIPLOMAT_GOLDEN_MOVE', array('piece' => $this->getId()));
          }
          $move_ok = TRUE;
        }
        elseif (!$target->isAlive() && $this->getHability("move_dead_pieces") && $allow_interactions) {
          $interactions[] = array("type" => "necromobility", "target" => $target);
          $move_ok = TRUE;
        }
        elseif ($target->isAlive()) {
          // Signature de l'assassin
          if ($this->getHability("kill_signature") && ($allow_interactions || $extra_interaction)) {
            $kills[] = array(
              'victim' => $target,
              'position' => $current_position
            );
            $move_ok = TRUE;
            if ($extra_interaction) {
              $this->getFaction()->getBattlefield()->logEvent('event', 'ASSASSIN_GOLDEN_MOVE', array('piece' => $this->getId()));
            }
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
              if ($occupant->isAlive()) {
                if ($grid->getOption('rule_press_liberty') == 'foxnews' ||
                    $occupant->getFaction()->getControl()->getId() != $this->getFaction()->getControl()->getId()) {
                  $canibalism = $grid->getOption('rule_canibalism');
                  if ($canibalism != 'ethical' || $occupant->getFaction()->getControl()->isAlive()) {
                    $victims[$key] = $occupant;
                  }
                }
              }
            }
          }
          if ($grid->getOption('rule_press_liberty') == 'pravda' && count($victims) > 1) {
            $interactions[] = array("type" => "reportage", "reporter" => $this, "victims" => $victims);
          }
          elseif (count($victims) > 0) {
            foreach ($victims as $victim) {
              $kills[] = array(
                'victim' => $victim,
                'position' => $victim->getPosition()
              );
            }
          }
        }
        $move_ok = TRUE;
      }
      if ($move_ok) {
        $this->faction->getBattlefield()->logMove($this, $destination, 'move', isset($destination['occupant']) ? $destination['occupant'] : NULL);
        $this->setPosition($destination["x"], $destination["y"]);
        if (!empty($kills)) {
          foreach($kills as $kill) {
            $this->kill($kill['victim'], $kill['position']);
          }
        }
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
    $victim->setAlive(FALSE);
    $this->faction->getBattlefield()->logMove($victim, $destination, "murder", $this);
    $victim->setPosition($destination["x"], $destination["y"]);
    if ($victim->getHability("must_live")) {
      $victim->getFaction()->dieDieDie(KW_DJAMBI_USER_KILLED);
      $victim->getFaction()->setControl($this->faction->getControl());
      $victim->getFaction()->setMaster($this->faction->getControl()->getId());
      $this->faction->getBattlefield()->updateSummary();
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

  public function checkAvailableMove($cell, $allow_interactions) {
    $move_ok = FALSE;
    $grid = $this->getFaction()->getBattlefield();
    $cells = $grid->getCells();
    if (isset($cells[$cell]['occupant'])) {
      $occupant = $cells[$cell]['occupant'];
      $can_attack = $this->checkAttackingPossibility($occupant);
      $can_manipulate = $this->checkManipulatingPossibility($occupant);
      if (!$allow_interactions) {
        $move_ok = FALSE;
        if ($grid->getOption('rule_throne_interactions') == 'extended') {
          if ($occupant->isAlive() && $occupant->getHability('access_throne')) {
            if ($can_manipulate || $can_attack) {
              $move_ok = TRUE;
            }
          }
        }
      }
      else {
        if ($occupant->isAlive() && ($can_attack || $can_manipulate)) {
          if ($can_attack) {
            if ($cells[$cell]['type'] == 'throne') {
              if ($this->getHability('kill_throne_leader')) {
                $move_ok = TRUE;
              }
            }
            else {
              $move_ok = TRUE;
            }
          }
          elseif ($can_manipulate) {
            $move_ok = TRUE;
          }
        }
        elseif (!$occupant->isAlive() && $this->getHability('move_dead_pieces')) {
          $move_ok = TRUE;
        }
      }
    }
    else {
      if ($cells[$cell]['type'] != 'throne' || $this->getHability('access_throne')) {
        $move_ok = TRUE;
      }
    }
    return $move_ok;
  }

  public function checkAttackingPossibility(DjambiPiece $occupant) {
    $can_attack = FALSE;
    if ($this->getHability('kill_by_attack')) {
      $canibalism = $this->getFaction()->getBattlefield()->getOption('rule_canibalism');
      if ($canibalism == 'yes') {
        $can_attack = TRUE;
      }
      elseif ($canibalism == 'vassals') {
        $can_attack = ($occupant->getFaction()->getId() != $this->getFaction()->getId()) ? TRUE : FALSE;
      }
      else {
        $can_attack = ($occupant->getFaction()->getControl()->getId() != $this->getFaction()->getControl()->getId()) ? TRUE : FALSE;
        if ($canibalism == 'ethical' && !$occupant->getFaction()->getControl()->isAlive()) {
          $can_attack = FALSE;
        }
      }
    }
    return $can_attack;
  }

  public function checkManipulatingPossibility(DjambiPiece $occupant) {
    $can_manipulate = FALSE;
    if ($this->getHability('move_living_pieces')) {
      $manipulation_rule = $this->getFaction()->getBattlefield()->getOption('rule_self_diplomacy');
      if ($manipulation_rule == 'vassal') {
        $can_manipulate = ($occupant->getFaction()->getId() != $this->getFaction()->getId()) ? TRUE : FALSE;
      }
      else {
        $can_manipulate = ($occupant->getFaction()->getControl()->getId() != $this->getFaction()->getControl()->getId()) ? TRUE : FALSE;
      }
    }
    return $can_manipulate;
  }

}