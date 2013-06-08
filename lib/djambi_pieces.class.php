<?php
class DjambiPiece {
  private $id,
          $faction,
          $original_faction_id,
          $alive,
          $position,
          $movable = FALSE,
          $allowable_moves = array(),
          $description;

  public function __construct(DjambiPieceDescription $piece, DjambiPoliticalFaction $faction, $original_faction_id, $position, $alive) {
    $this->description = $piece;
    $this->faction = $faction;
    $this->original_faction_id = $original_faction_id;
    $this->id = $faction->getId() . '-' . $piece->getShortname();
    $this->alive = $alive;
    $this->setPosition($position);
    $this->getBattlefield()->addHabilitiesInStore($piece->getHabilities());
    return $this;
  }

  public function getId() {
    return $this->id;
  }

  /**
   * @return DjambiPieceDescription
   */
  public function getDescription() {
    return $this->description;
  }

  public function getShortname($mode = NULL) {
    if ($mode == "html") {
      return "<abbr title=\"". $this->getDescription()->getLongname() ."\">" . $this->getDescription()->getShortname() . "</abbr>";
    }
    return $this->getDescription()->getShortname();
  }

  public function getLongname() {
    return $this->getDescription()->echoName();
  }

  public function getType() {
    return $this->getDescription()->getType();
  }

  /**
   * @return DjambiPoliticalFaction
   */
  public function getFaction() {
    return $this->faction;
  }

  /**
   * @return DjambiBattlefield
   */
  public function getBattlefield() {
    return $this->faction->getBattlefield();
  }

  /**
   * @return DjambiPoliticalFaction
   */
  public function getOriginalFaction() {
    return $this->getBattlefield()->getFactionById($this->original_faction_id);
  }

  public function getOriginalFactionId() {
    return $this->original_faction_id;
  }

  public function getImage() {
    return $this->getDescription()->getImagePattern();
  }

  public function getHability($name) {
    if ($this->getBattlefield()->isHabilityInStore($name)) {
      $habilities = $this->getDescription()->getHabilities();
      return isset($habilities[$name]) ? $habilities[$name] : FALSE;
    }
    else {
      throw new Exception('Undeclared hability');
    }
  }

  public function getPosition() {
    return $this->position;
  }

  public function setPosition($position) {
    if (!is_array($position)) {
      $position = $this->getBattlefield()->getCellXY($position);
    }
    $current_position = isset($this->position) ? $this->position : NULL;
    $this->position = $position;
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
    if (!$movable) {
      $this->setAllowableMoves(array());
    }
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

  public function buildAllowableMoves($allow_interactions = TRUE, $force_position = NULL) {
    if (!$this->isAlive()) {
      return;
    }
    $cells = $this->getBattlefield()->getCells();
    $rows = $this->getBattlefield()->getRows();
    $cols = $this->getBattlefield()->getCols();
    $directions = $this->getBattlefield()->getScheme()->getDirections();
    if (!empty($force_position)) {
      $current_cell = $cells[DjambiBattlefield::locateCell($force_position)];
      $force_empty_position = DjambiBattlefield::locateCell($this->getPosition());
    }
    else {
      $current_cell = $cells[DjambiBattlefield::locateCell($this->getPosition())];
      $force_empty_position = NULL;
    }
    if (!empty($current_cell['neighbours'])) {
      $next_cases = $current_cell['neighbours'];
      foreach ($next_cases as $direction => $cell) {
        $move_ok = $this->checkAvailableMove($cell, $allow_interactions, $cell == $force_empty_position);
        if (!$move_ok && isset($cells[$cell]['occupant'])) {
          unset($next_cases[$direction]);
          continue;
        }
        elseif (!isset($cells[$cell]['occupant']) || $cell == $force_empty_position) {
          $obstacle = FALSE;
          $next_cell = $cell;
          for ($i = 2; $obstacle == FALSE; $i++) {
            $limited_move = $this->getDescription()->hasHabilityLimitedMove();
            if ($limited_move && $i > $limited_move) {
              $obstacle = TRUE;
            }
            else {
              if (!isset($cells[$next_cell]['neighbours'][$direction])) {
                $obstacle = TRUE;
              }
              else {
                $next_cell = $cells[$next_cell]['neighbours'][$direction];
                $test = $this->checkAvailableMove($next_cell, $allow_interactions, $next_cell == $force_empty_position);
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
          if ($cells[$cell]['type'] == 'throne' && !$this->getDescription()->hasHabilityAccessThrone()) {
            unset($next_cases[$direction]);
          }
        }
      }
    }
    if (!empty($next_cases)) {
      $this->setMovable(TRUE);
      $this->setAllowableMoves($next_cases);
      foreach ($next_cases as $cell) {
        $this->getBattlefield()->updateCell($cell, 'reachable', TRUE);
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

  public function evaluateMove($destination) {
    $return = $this->prepareMove($destination, TRUE, TRUE);
    if (!empty($return['interactions'])) {
      foreach ($return['interactions'] as $key => $interaction) {
        $choices = array();
        if (isset($interaction['target'])) {
          $target = $interaction['target'];
        }
        switch ($interaction['type']) {
          case('manipulation') :
            $choices = $this->getBattlefield()->getFreeCells($target, TRUE, FALSE, $this->getPosition());
            break;
          case('necromobility') :
            $choices = $this->getBattlefield()->getFreeCells($target, FALSE, FALSE, $this->getPosition());
            break;
          case('reportage') :
            foreach ($interaction['victims'] as $victim) {
              $choices[] = $victim->getId();
            }
            break;
          case('murder') :
            $choices = $this->getBattlefield()->getFreeCells($target, FALSE, TRUE, $this->getPosition());
            break;
          case('throne_evacuation') :
            $choices = $this->buildAllowableMoves(FALSE, $destination);
            break;
        }
        $return['interactions'][$key]['choices'] = $choices;
      }
    }
    return $return;
  }

  public function evacuate($destination) {
    $return = $this->prepareMove($destination, FALSE, FALSE);
    if ($return['allowed']) {
      $this->executeMove($destination, $return['kills'], $return['events']);
    }
    return $return['interactions'];
  }

  public function move($destination) {
    $return = $this->prepareMove($destination, TRUE, FALSE);
    if ($return['allowed']) {
      $this->executeMove($destination, $return['kills'], $return['events']);
    }
    return $return['interactions'];
  }

  private function prepareMove($destination, $allow_interactions, $simulate) {
    $interactions = array();
    $events = array();
    $kills = array();
    $current_position = $this->getPosition();
    $move_ok = FALSE;
    $extra_interaction = FALSE;
    $destination = $this->checkNewPosition($destination);
    // Vérifie si la pièce dispose d'un droit d'interaction supplémentaire lors d'une évacuation de trône
    if (!$allow_interactions && $this->getBattlefield()->getOption('rule_throne_interactions') == 'extended') {
      $cells = $this->getBattlefield()->getCells();
      $current_cell = $cells[DjambiBattlefield::locateCell($current_position)];
      if ($current_cell['type'] == 'throne' && isset($destination['occupant'])) {
        /* @var $target DjambiPiece */
        $target = $destination['occupant'];
        if ($target->getDescription()->hasHabilityAccessThrone()) {
          $extra_interaction = TRUE;
        }
      }
    }
    // Vérifie les conséquences d'un déplacement si le déplacement se fait sur une case occupée
    if ($destination && ($destination["x"] != $current_position["x"] || $destination["y"] && $current_position["y"])) {
      if (isset($destination["occupant"])) {
        /* @var $target DjambiPiece */
        $target = $destination["occupant"];
        // ----> Manipulation ?
        if ($this->getBattlefield()->getOption('rule_self_diplomacy') == 'vassal') {
          $can_manipulate = $target->getFaction()->getId() != $this->getFaction()->getId();
        }
        else {
          $can_manipulate = $target->getFaction()->getControl()->getId() != $this->getFaction()->getControl()->getId();
        }
        if ($target->isAlive() && $this->getDescription()->hasHabilityMoveLivingPieces() && ($allow_interactions || $extra_interaction)
          && $can_manipulate) {
          if ($allow_interactions) {
            $interactions[] = array("type" => "manipulation", "target" => $target);
          }
          elseif ($extra_interaction) {
            $events[] = array('type' => 'diplomate_golden_move', 'target' => $target, 'position' => $current_position);
          }
          $move_ok = TRUE;
        }
        //  ----> Necromobilité ?
        elseif (!$target->isAlive() && $this->getDescription()->hasHabilityMoveDeadPieces() && $allow_interactions) {
          $interactions[] = array("type" => "necromobility", "target" => $target);
          $move_ok = TRUE;
        }
        // ----> Assassinat ?
        elseif ($target->isAlive()) {
          // Signature de l'assassin
          if ($this->getDescription()->hasHabilitySignature() && $this->getDescription()->hasHabilityKillByAttack()
          && ($allow_interactions || $extra_interaction)) {
            $kills[] = array(
              'victim' => $target,
              'position' => $current_position
            );
            $move_ok = TRUE;
            if ($extra_interaction) {
              $events[] = array('type' => 'assassin_golden_move');
            }
          }
          // Déplacement du corps de la victime
          elseif ($this->getDescription()->hasHabilityKillByAttack() && $allow_interactions) {
            $interactions[] = array("type" => "murder", "target" => $target, "default" => $current_position);
            $move_ok = TRUE;
          }
        }
      }
      else {
        // ----> reportage ?
        // Eventuel choix de la victime du reporter
        if ($this->getDescription()->hasHabilityKillByProximity() && $allow_interactions) {
          $grid = $this->faction->getBattlefield();
          $next_cells = $grid->findNeighbourCells($destination, FALSE);
          $cells = $grid->getCells();
          $victims = array();
          foreach ($next_cells as $cell) {
            $key = DjambiBattlefield::locateCell($cell);
            if (!empty($cells[$key]["occupant"])) {
              $occupant = $cells[$key]["occupant"];
              if ($occupant->isAlive() && $occupant->getId() != $this->getId()) {
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
    }
    if (!$move_ok) {
      $interactions[] = array("type" => "piece_destination", "target" => $this);
    }
    elseif (!$this->getDescription()->hasHabilityAccessThrone() && $destination["type"] == "throne") {
      $interactions[] = array("type" => "throne_evacuation", "target" => $this);
    }
    return array(
        'allowed' => $move_ok,
        'interactions' => $interactions,
        'events' => $events,
        'kills' => $kills
    );
  }

  private function executeMove($destination, $kills, $events) {
    $this->faction->getBattlefield()->logMove($this, $destination, 'move',
        isset($destination['occupant']) ? $destination['occupant'] : NULL);
    $this->setPosition($destination);
    if (!empty($kills)) {
      foreach($kills as $kill) {
        $this->kill($kill['victim'], $kill['position']);
      }
    }
    if (!empty($return['events'])) {
      foreach ($return['events'] as $event) {
        if ($event['type'] == 'diplomat_golden_move') {
          $target = $event['target'];
          $target->move($event['position']);
          $this->getBattlefield()->logEvent('event', 'DIPLOMAT_GOLDEN_MOVE', array('piece' => $this->getId()));
        }
        elseif ($event['type'] == 'assassin_golden_move') {
          $this->getBattlefield()->logEvent('event', 'ASSASSIN_GOLDEN_MOVE', array('piece' => $this->getId()));
        }
      }
    }
  }

  public function kill(DjambiPiece $victim, $destination) {
    $destination = $this->checkNewPosition($destination);
    $victim->setAlive(FALSE);
    $this->faction->getBattlefield()->logMove($victim, $destination, "murder", $this);
    $victim->setPosition($destination);
    if ($victim->getDescription()->hasHabilityMustLive()) {
      $this->getBattlefield()->logEvent('event', 'LEADER_KILLED', array(
          'faction1' => $victim->getFaction()->getId(),
          'piece' => $victim->getId()
      ));
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
    $victim->setPosition($destination);
  }

  public function necromove(DjambiPiece $victim, $destination) {
    $destination = $this->checkNewPosition($destination);
    $this->faction->getBattlefield()->logMove($victim, $destination, "necromobility", $this);
    $victim->setPosition($destination);
  }

  public function checkAvailableMove($cell, $allow_interactions, $force_empty = FALSE) {
    $move_ok = FALSE;
    $grid = $this->getBattlefield();
    $cells = $grid->getCells();
    if ($force_empty || !isset($cells[$cell]['occupant'])) {
      if ($cells[$cell]['type'] != 'throne' || $this->getDescription()->hasHabilityAccessThrone()) {
        $move_ok = TRUE;
      }
    }
    else {
      /* @var $occupant DjambiPiece */
      $occupant = $cells[$cell]['occupant'];
      $can_attack = $this->checkAttackingPossibility($occupant);
      $can_manipulate = $this->checkManipulatingPossibility($occupant);
      if (!$allow_interactions) {
        $move_ok = FALSE;
        if ($grid->getOption('rule_throne_interactions') == 'extended') {
          if ($occupant->isAlive() && $occupant->getDescription()->hasHabilityKillThroneLeader()) {
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
              if ($this->getDescription()->hasHabilityKillThroneLeader()) {
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
        elseif (!$occupant->isAlive() && $this->getDescription()->hasHabilityMoveDeadPieces()) {
          $move_ok = TRUE;
        }
      }
    }
    return $move_ok;
  }

  public function checkAttackingPossibility(DjambiPiece $occupant) {
    $can_attack = FALSE;
    if ($this->getDescription()->hasHabilityKillByAttack()) {
      $canibalism = $this->getBattlefield()->getOption('rule_canibalism');
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
    if ($this->getDescription()->hasHabilityMoveLivingPieces()) {
      $manipulation_rule = $this->getBattlefield()->getOption('rule_self_diplomacy');
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