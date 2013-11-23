<?php
/**
 * @file
 * Déclare la class DjambiPiece, qui gère les déplacements et les changements
 * d'état des pièces de Djambi.
 */

namespace Djambi;
use Djambi\Exceptions\Exception;

/**
 * Class DjambiPiece
 */
class Piece {
  /* @var string $id */
  protected $id;
  /* @var Faction $faction */
  protected $faction;
  /* @var string $originalFactionId */
  protected $originalFactionId;
  /* @var  bool $alive */
  protected $alive;
  /* @var Cell $position */
  protected $position;
  /* @var bool $movable */
  protected $movable = FALSE;
  /* @var Cell[] $allowableMoves */
  protected $allowableMoves = array();
  /* @var PieceDescription $description */
  protected $description;

  public function __construct(PieceDescription $piece, Faction $faction, $original_faction_id, Cell $position, $alive) {
    $this->description = $piece;
    $this->faction = $faction;
    $this->originalFactionId = $original_faction_id;
    $this->id = $faction->getId() . '-' . $piece->getShortname();
    $this->alive = $alive;
    $this->setPosition($position);
    $this->getBattlefield()->addHabilitiesInStore($piece->getHabilities());
  }

  public function getId() {
    return $this->id;
  }

  public function getDescription() {
    return $this->description;
  }

  public function getShortname() {
    return $this->getDescription()->getShortname();
  }

  public function getLongname() {
    return $this->getDescription()->echoName();
  }

  public function getType() {
    return $this->getDescription()->getType();
  }

  public function getFaction() {
    return $this->faction;
  }

  public function getBattlefield() {
    return $this->faction->getBattlefield();
  }

  public function getOriginalFaction() {
    return $this->getBattlefield()->getFactionById($this->originalFactionId);
  }

  public function getOriginalFactionId() {
    return $this->originalFactionId;
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

  public function setPosition(Cell $position) {
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
    $this->allowableMoves = $array;
    return $this;
  }

  public function getAllowableMoves() {
    return $this->allowableMoves;
  }

  public function getAllowableMovesNames() {
    $moves = array();
    foreach ($this->allowableMoves as $cell) {
      $moves[] = $cell->getName();
    }
    return $moves;
  }

  public function buildAllowableMoves($allow_interactions = TRUE, Cell $force_position = NULL) {
    if (!$this->isAlive()) {
      return 0;
    }
    if (!is_null($force_position)) {
      $current_cell = $force_position;
      $force_empty_position = $this->getPosition()->getName();
    }
    else {
      $current_cell = $this->getPosition();
      $force_empty_position = NULL;
    }
    $next_cases = $current_cell->getNeighbours();
    if (!empty($next_cases)) {
      foreach ($next_cases as $direction => $cell) {
        $move_ok = $this->checkAvailableMove($cell, $allow_interactions, !empty($force_empty_position) && $force_empty_position == $cell->getName());
        $occupant = $cell->getOccupant();
        if (!$move_ok && !empty($occupant)) {
          unset($next_cases[$direction]);
          continue;
        }
        elseif (empty($occupant) || $cell == $force_empty_position) {
          $obstacle = FALSE;
          $next_cell = $cell;
          for ($i = 2; $obstacle == FALSE; $i++) {
            $limited_move = $this->getDescription()->hasHabilityLimitedMove();
            if ($limited_move && $i > $limited_move) {
              $obstacle = TRUE;
            }
            else {
              $neighbours = $next_cell->getNeighbours();
              if (!isset($neighbours[$direction])) {
                $obstacle = TRUE;
              }
              else {
                $next_cell = $neighbours[$direction];
                $test = $this->checkAvailableMove($next_cell, $allow_interactions, !empty($force_empty_position) && $next_cell->getName() == $force_empty_position);
                if ($test) {
                  if (!in_array($next_cell, $next_cases)) {
                    $next_cases[$direction . $i] = $next_cell;
                  }
                  else {
                    $obstacle = TRUE;
                  }
                }
                $next_cell_occupant = $next_cell->getOccupant();
                if (!empty($next_cell_occupant)) {
                  $obstacle = TRUE;
                }
              }
            }
          }
          if ($cell->getType() == 'throne' && !$this->getDescription()->hasHabilityAccessThrone()) {
            unset($next_cases[$direction]);
          }
        }
      }
    }
    if (!empty($next_cases)) {
      $this->setMovable(TRUE);
      $this->setAllowableMoves($next_cases);
      foreach ($next_cases as $cell) {
        $cell->setReachable(TRUE);
      }
    }
    return count($this->allowableMoves);
  }

  public function evaluateMove(Cell $destination) {
    $return = $this->prepareMove($destination, TRUE);
    if (!empty($return['interactions'])) {
      foreach ($return['interactions'] as $key => $interaction) {
        $choices = array();
        $target = NULL;
        if (isset($interaction['target'])) {
          $target = $interaction['target'];
        }
        switch ($interaction['type']) {
          case('manipulation'):
            if (!empty($target)) {
              $choices = $this->getBattlefield()->getFreeCells($target, TRUE, FALSE, $this->getPosition());
            }
            break;

          case('necromobility'):
            if (!empty($target)) {
              $choices = $this->getBattlefield()->getFreeCells($target, FALSE, FALSE, $this->getPosition());
            }
            break;

          case('reportage'):
            /* @var Piece $victim */
            foreach ($interaction['victims'] as $victim) {
              $choices[] = $victim->getId();
            }
            break;

          case('murder'):
            if (!empty($target)) {
              $choices = $this->getBattlefield()->getFreeCells($target, FALSE, TRUE, $this->getPosition());
            }
            break;

          case('throne_evacuation'):
            $choices = $this->buildAllowableMoves(FALSE, $destination);
            break;
        }
        $return['interactions'][$key]['choices'] = $choices;
      }
    }
    return $return;
  }

  public function evacuate(Cell $destination) {
    $return = $this->prepareMove($destination, FALSE);
    if ($return['allowed']) {
      $this->executeMove($return['cell'], $return['kills'], $return['events']);
    }
    return $return['interactions'];
  }

  public function move(Cell $destination) {
    $return = $this->prepareMove($destination, TRUE);
    if ($return['allowed']) {
      $this->executeMove($return['cell'], $return['kills'], $return['events']);
    }
    return $return['interactions'];
  }

  protected function prepareMove(Cell $destination, $allow_interactions) {
    $interactions = array();
    $events = array();
    $kills = array();
    $current_cell = $this->getPosition();
    $move_ok = FALSE;
    $extra_interaction = FALSE;
    // Vérifie si la pièce dispose d'un droit d'interaction supplémentaire
    // lors d'une évacuation de trône :
    if (!$allow_interactions && $this->getBattlefield()->getOption('rule_throne_interactions') == 'extended') {
      $target = $destination->getOccupant();
      if ($current_cell->getType() == 'throne' && !empty($target) && $target->getDescription()->hasHabilityAccessThrone()) {
        $extra_interaction = TRUE;
      }
    }
    // Vérifie les conséquences d'un déplacement si le déplacement se fait
    // sur une case occupée :
    if ($destination->getName() != $current_cell->getName()) {
      $target = $destination->getOccupant();
      if (!empty($target)) {
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
            $events[] = array(
              'type' => 'diplomate_golden_move',
              'target' => $target,
              'position' => $current_cell->getName(),
            );
          }
          $move_ok = TRUE;
        }
        // ----> Necromobilité ?
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
              'position' => $current_cell,
            );
            $move_ok = TRUE;
            if ($extra_interaction) {
              $events[] = array('type' => 'assassin_golden_move');
            }
          }
          // Déplacement du corps de la victime :
          elseif ($this->getDescription()->hasHabilityKillByAttack() && $allow_interactions) {
            $interactions[] = array(
              "type" => "murder",
              "target" => $target,
              "default" => $current_cell->getName(),
            );
            $move_ok = TRUE;
          }
        }
      }
      else {
        // ----> reportage ?
        // Eventuel choix de la victime du reporter :
        if ($this->getDescription()->hasHabilityKillByProximity() && $allow_interactions) {
          $grid = $this->faction->getBattlefield();
          $next_cells = $grid->findNeighbourCells($destination, FALSE);
          $victims = array();
          foreach ($next_cells as $key) {
            $cell = $this->getBattlefield()->findCell($key['x'], $key['y']);
            $occupant = $cell->getOccupant();
            if (!empty($occupant)) {
              if ($occupant->isAlive() && $occupant->getId() != $this->getId()) {
                if ($grid->getOption('rule_press_liberty') == 'foxnews' ||
                    $occupant->getFaction()->getControl()->getId() != $this->getFaction()->getControl()->getId()) {
                  $canibalism = $grid->getOption('rule_canibalism');
                  if ($canibalism != 'ethical' || $occupant->getFaction()->getControl()->isAlive()) {
                    $victims[$cell->getName()] = $occupant;
                  }
                }
              }
            }
          }
          if ($grid->getOption('rule_press_liberty') == 'pravda' && count($victims) > 1) {
            $interactions[] = array(
              "type" => "reportage",
              "reporter" => $this,
              "victims" => $victims,
            );
          }
          elseif (!empty($victims)) {
            /* @var Piece $victim */
            foreach ($victims as $victim) {
              $kills[] = array(
                'victim' => $victim,
                'position' => $victim->getPosition(),
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
    elseif (!$this->getDescription()->hasHabilityAccessThrone() && $destination->getType() == "throne") {
      $interactions[] = array("type" => "throne_evacuation", "target" => $this);
    }
    return array(
      'cell' => $destination,
      'allowed' => $move_ok,
      'interactions' => $interactions,
      'events' => $events,
      'kills' => $kills,
    );
  }

  protected function executeMove(Cell $destination, $kills, $events = NULL) {
    $target = $destination->getOccupant();
    $this->faction->getBattlefield()->logMove($this, $destination, 'move', $target);
    $this->setPosition($destination);
    if (!empty($kills)) {
      foreach ($kills as $kill) {
        $this->kill($kill['victim'], $kill['position']);
      }
    }
    if (!empty($events)) {
      foreach ($events as $event) {
        if ($event['type'] == 'diplomat_golden_move') {
          /* @var Piece $target */
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

  public function kill(Piece $victim, Cell $destination) {
    $victim->setAlive(FALSE);
    $this->faction->getBattlefield()->logMove($victim, $destination, "murder", $this);
    $victim->setPosition($destination);
    if ($victim->getDescription()->hasHabilityMustLive()) {
      $this->getBattlefield()->logEvent('event', 'LEADER_KILLED', array(
        'faction1' => $victim->getFaction()->getId(),
        'piece' => $victim->getId(),
      ));
      $victim->getFaction()->dieDieDie(KW_DJAMBI_FACTION_STATUS_KILLED);
      $victim->getFaction()->setControl($this->faction->getControl());
      $victim->getFaction()->setMaster($this->faction->getControl()->getId());
      $this->faction->getBattlefield()->updateSummary();
      $this->faction->getBattlefield()->getPlayOrder(TRUE);
    }
  }

  public function manipulate(Piece $victim, Cell $destination) {
    $this->faction->getBattlefield()->logMove($victim, $destination, "manipulation", $this);
    $victim->setPosition($destination);
  }

  public function necromove(Piece $victim, Cell $destination) {
    $this->faction->getBattlefield()->logMove($victim, $destination, "necromobility", $this);
    $victim->setPosition($destination);
  }

  public function checkAvailableMove(Cell $cell, $allow_interactions, $force_empty = FALSE) {
    $move_ok = FALSE;
    $occupant = $cell->getOccupant();
    if ($force_empty || empty($occupant)) {
      if ($cell->getType() != 'throne' || $this->getDescription()->hasHabilityAccessThrone()) {
        $move_ok = TRUE;
      }
    }
    else {
      $can_attack = $this->checkAttackingPossibility($occupant);
      $can_manipulate = $this->checkManipulatingPossibility($occupant);
      if (!$allow_interactions) {
        $move_ok = FALSE;
        if ($this->getBattlefield()->getOption('rule_throne_interactions') == 'extended') {
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
            if ($cell->getType() == 'throne') {
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

  public function checkAttackingPossibility(Piece $occupant) {
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

  public function checkManipulatingPossibility(Piece $occupant) {
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
