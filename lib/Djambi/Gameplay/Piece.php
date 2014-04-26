<?php
/**
 * @file
 * Déclare la class DjambiPiece, qui gère les déplacements et les changements
 * d'état des pièces de Djambi.
 */

namespace Djambi\Gameplay;

use Djambi\Exceptions\DisallowedActionException;
use Djambi\Exceptions\IllogicMoveException;
use Djambi\Moves\Manipulation;
use Djambi\Moves\Move;
use Djambi\Moves\Murder;
use Djambi\Moves\Necromobility;
use Djambi\Moves\Reportage;
use Djambi\Moves\ThroneEvacuation;
use Djambi\GameOptions\StandardRuleset;
use Djambi\Persistance\PersistantDjambiObject;
use Djambi\PieceDescriptions\BasePieceDescription;

/**
 * Class DjambiPiece
 */
class Piece extends PersistantDjambiObject {
  /* @var string $id */
  private $id;
  /* @var Faction $faction */
  private $faction;
  /* @var string $originalFactionId */
  private $originalFactionId;
  /* @var  bool $alive */
  private $alive;
  /* @var Cell $position */
  private $position;
  /* @var bool $movable */
  private $movable = FALSE;
  /* @var Cell[] $allowableMoves */
  private $allowableMoves = array();
  /* @var BasePieceDescription $description */
  private $description;

  protected function prepareArrayConversion() {
    $this->addDependantObjects(array(
      'faction' => 'id',
      'position' => 'name',
      'allowableMoves' => 'name',
    ));
    $this->addPersistantProperties(array(
      'id',
      'originalFactionId',
      'alive',
      'description',
    ));
    return parent::prepareArrayConversion();
  }

  public static function fromArray(array $array, array $context = array()) {
    /** @var Faction $faction */
    $faction = $context['faction'];
    $description = call_user_func($array['description']['className'] . '::fromArray', $array['description'], $context);
    $cell = $faction->getBattlefield()->findCellByName($array['position']);
    $piece = new static($description, $faction, $array['originalFactionId'], $cell, $array['alive']);
    if (!empty($array['allowableMoves'])) {
      foreach ($array['allowableMoves'] as $direction => $cell) {
        $piece->allowableMoves[$direction] = $faction->getBattlefield()->findCellByName($cell);
      }
    }
    return $piece;
  }

  public function __construct(BasePieceDescription $piece, Faction $faction, $original_faction_id, Cell $position, $alive) {
    $this->setDescription($piece);
    $this->setFaction($faction);
    $this->setOriginalFactionId($original_faction_id);
    $this->setId($faction->getId() . '-' . $piece->getShortname());
    $this->setAlive($alive);
    $this->setPosition($position);
  }

  public function getId() {
    return $this->id;
  }

  protected function setId($id) {
    $this->id = $id;
    return $this;
  }

  public function getDescription() {
    return $this->description;
  }

  protected function setDescription(BasePieceDescription $description) {
    $this->description = $description;
    return $this;
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

  protected function setFaction(Faction $faction) {
    $this->faction = $faction;
    return $this;
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

  public function setOriginalFactionId($id) {
    $this->originalFactionId = $id;
    return $this;
  }

  public function getImage() {
    return $this->getDescription()->getImagePattern();
  }

  public function getPosition() {
    return $this->position;
  }

  public function setPosition(Cell $new_position) {
    $old_position = isset($this->position) ? $this->position : NULL;
    $this->position = $new_position;
    $new_position->setOccupant($this);
    if (!is_null($old_position) && $new_position->getName() != $old_position->getName()) {
      if (!is_null($old_position->getOccupant()) && $old_position->getOccupant()->getId() == $this->getId()) {
        $old_position->emptyOccupant();
      }
    }
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
          if ($cell->getType() == Cell::TYPE_THRONE && !$this->getDescription()->hasHabilityAccessThrone()) {
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

  public function evaluateMove(Move $move) {
    $move_ok = $this->prepareMove($move, TRUE);
    if (!$move_ok) {
      throw new DisallowedActionException("Unauthorized move : piece " . $this->getId()
        . " from " . $this->getPosition()->getName() . " to " . $move->getDestination()->getName());
    }
    foreach ($move->getInteractions() as $interaction) {
      $interaction->findPossibleChoices();
    }
    return $this;
  }

  public function executeMove(Move $move, $allow_interactions = TRUE) {
    $destination = $move->getDestination();
    $move_ok = $this->prepareMove($move, $allow_interactions);
    if (!$move_ok) {
      throw new DisallowedActionException("Unauthorized move : piece " . $this->getId()
        . " from " . $this->getPosition()->getName() . " to " . $destination->getName());
    }
    elseif (empty($destination)) {
      throw new IllogicMoveException("Undefined destination in move execution.");
    }
    else {
      $target = $destination->getOccupant();
      $this->faction->getBattlefield()->logMove($this, $destination, 'move', $target);
      $this->setPosition($destination);
      foreach ($move->getKills() as $kill) {
        $this->kill($move, $kill['victim'], $kill['position']);
      }
      foreach ($move->getEvents() as $event) {
        if ($event['type'] == 'diplomat_golden_move') {
          $golden_move = new Manipulation($move, $event['target']);
          $golden_move->moveSelectedPiece($event['position']);
          $move->triggerInteraction($golden_move);
          $this->getBattlefield()->logEvent('event', 'DIPLOMAT_GOLDEN_MOVE', array('piece' => $this->getId()));
        }
        elseif ($event['type'] == 'assassin_golden_move') {
          $this->getBattlefield()->logEvent('event', 'ASSASSIN_GOLDEN_MOVE', array('piece' => $this->getId()));
        }
      }
    }
    return $this;
  }

  protected function prepareMove(Move $move, $allow_interactions) {
    $destination = $move->getDestination();
    $current_cell = $this->getPosition();
    $move_ok = FALSE;
    $extra_interaction = FALSE;
    // Vérifie si la pièce dispose d'un droit d'interaction supplémentaire
    // lors d'une évacuation de trône :
    if (!$allow_interactions && $this->getBattlefield()->getGameManager()->getOption(StandardRuleset::RULE_EXTRA_INTERACTIONS) == 'extended') {
      $target = $destination->getOccupant();
      if ($current_cell->getType() == Cell::TYPE_THRONE && !empty($target) && $target->getDescription()->hasHabilityAccessThrone()) {
        $extra_interaction = TRUE;
      }
    }
    // Vérifie les conséquences d'un déplacement si le déplacement se fait
    // sur une case occupée :
    if ($destination->getName() != $current_cell->getName()) {
      $target = $destination->getOccupant();
      if (!empty($target)) {
        // ----> Manipulation ?
        if ($this->getDescription()->hasHabilityMoveLivingPieces()) {
          if ($this->getBattlefield()->getGameManager()->getOption(StandardRuleset::RULE_DIPLOMACY) == 'vassal') {
            $can_manipulate = $target->getFaction()->getId() != $this->getFaction()->getId();
          }
          else {
            $can_manipulate = $target->getFaction()->getControl()->getId() != $this->getFaction()->getControl()->getId();
          }
          if ($can_manipulate && $target->isAlive() && ($allow_interactions || $extra_interaction)) {
            if ($allow_interactions) {
              $move->triggerInteraction(new Manipulation($move, $target));
            }
            elseif ($extra_interaction) {
              $move->triggerEvent(array(
                'type' => 'diplomate_golden_move',
                'target' => $target,
                'position' => $current_cell,
              ));
            }
            $move_ok = TRUE;
          }
        }
        // ----> Necromobilité ?
        elseif (!$target->isAlive() && $this->getDescription()->hasHabilityMoveDeadPieces() && $allow_interactions) {
          $move->triggerInteraction(new Necromobility($move, $target));
          $move_ok = TRUE;
        }
        // ----> Assassinat ?
        elseif ($target->isAlive()) {
          // Signature de l'assassin
          $test = $this->getDescription()->hasHabilityKillByAttack();
          if ($this->getDescription()->hasHabilitySignature() && $test && ($allow_interactions || $extra_interaction)) {
            $move->triggerKill($target, $current_cell);
            $move_ok = TRUE;
            if ($extra_interaction) {
              $move->triggerEvent(array('type' => 'assassin_golden_move'));
            }
          }
          // Déplacement du corps de la victime :
          elseif ($test && $allow_interactions) {
            $move->triggerInteraction(new Murder($move, $target));
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
                if ($grid->getGameManager()->getOption(StandardRuleset::RULE_REPORTERS) == 'foxnews' ||
                    $occupant->getFaction()->getControl()->getId() != $this->getFaction()->getControl()->getId()) {
                  $canibalism = $grid->getGameManager()->getOption(StandardRuleset::RULE_CANIBALISM);
                  if ($canibalism != 'ethical' || $occupant->getFaction()->getControl()->isAlive()) {
                    $victims[$cell->getName()] = $occupant;
                  }
                }
              }
            }
          }
          if ($grid->getGameManager()->getOption(StandardRuleset::RULE_REPORTERS) == 'pravda' && count($victims) > 1) {
            $reportage = new Reportage($move);
            $move->triggerInteraction($reportage->setPotentialTargets($victims));
          }
          elseif (!empty($victims)) {
            /* @var Piece $victim */
            foreach ($victims as $victim) {
              $move->triggerKill($victim, $victim->getPosition());
            }
          }
        }
        $move_ok = TRUE;
      }
    }
    if ($move_ok && !$this->getDescription()->hasHabilityAccessThrone() && $destination->getType() == Cell::TYPE_THRONE) {
      $move->triggerInteraction(new ThroneEvacuation($move));
    }
    return $move_ok;
  }

  public function kill(Move $move, Piece $victim, Cell $destination) {
    $victim->setAlive(FALSE);
    $this->faction->getBattlefield()->logMove($victim, $destination, "murder", $this);
    $victim->setPosition($destination);
    if ($victim->getDescription()->hasHabilityMustLive()) {
      $this->getBattlefield()->logEvent('event', 'LEADER_KILLED', array(
        'faction1' => $victim->getFaction()->getId(),
        'piece' => $victim->getId(),
      ));
      $victim->getFaction()->dieDieDie(Faction::STATUS_KILLED);
      $victim->getFaction()->setControl($this->faction->getControl());
      $victim->getFaction()->setMaster($this->faction->getControl()->getId());
      $this->faction->getBattlefield()->updateSummary();
      $this->faction->getBattlefield()->getPlayOrder(TRUE);
    }
  }

  public function evacuate(Move $move) {
    $this->executeMove($move, FALSE);
    return $this;
  }

  public function manipulate(Manipulation $move, Piece $victim, Cell $destination) {
    $this->faction->getBattlefield()->logMove($victim, $destination, "manipulation", $this);
    $victim->setPosition($destination);
  }

  public function necromove(Necromobility $move, Piece $victim, Cell $destination) {
    $this->faction->getBattlefield()->logMove($victim, $destination, "necromobility", $this);
    $victim->setPosition($destination);
  }

  public function checkAvailableMove(Cell $cell, $allow_interactions, $force_empty = FALSE) {
    $move_ok = FALSE;
    $occupant = $cell->getOccupant();
    if ($force_empty || empty($occupant)) {
      if ($cell->getType() != Cell::TYPE_THRONE || $this->getDescription()->hasHabilityAccessThrone()) {
        $move_ok = TRUE;
      }
    }
    else {
      $can_attack = $this->checkAttackingPossibility($occupant);
      $can_manipulate = $this->checkManipulatingPossibility($occupant);
      if (!$allow_interactions) {
        $move_ok = FALSE;
        if ($this->getBattlefield()->getGameManager()->getOption(StandardRuleset::RULE_EXTRA_INTERACTIONS) == 'extended') {
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
            if ($cell->getType() == Cell::TYPE_THRONE) {
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
      $canibalism = $this->getBattlefield()->getGameManager()->getOption(StandardRuleset::RULE_CANIBALISM);
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
      $manipulation_rule = $this->getBattlefield()->getGameManager()->getOption(StandardRuleset::RULE_DIPLOMACY);
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
