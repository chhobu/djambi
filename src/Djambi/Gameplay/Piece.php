<?php
/**
 * @file
 * Déclare la class DjambiPiece, qui gère les déplacements et les changements
 * d'état des pièces de Djambi.
 */

namespace Djambi\Gameplay;

use Djambi\Moves\Manipulation;
use Djambi\Moves\Move;
use Djambi\Moves\Murder;
use Djambi\Moves\Necromobility;
use Djambi\Persistance\ArrayableInterface;
use Djambi\Persistance\PersistantDjambiTrait;
use Djambi\PieceDescriptions\BasePieceDescription;
use Djambi\Strings\Glossary;
use Djambi\Strings\GlossaryTerm;

/**
 * Class DjambiPiece
 */
class Piece implements ArrayableInterface {

  use PersistantDjambiTrait {
    prepareSerialization as traitSerialization;
  }

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
  /* @var BasePieceDescription $description */
  protected $description;
  /* @var bool $selectable */
  protected $selectable = FALSE;

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
    return $this;
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
    return $this->getBattlefield()->findFactionById($this->originalFactionId);
  }

  public function getOriginalFactionId() {
    return $this->originalFactionId;
  }

  public function setOriginalFactionId($id) {
    $this->originalFactionId = $id;
    return $this;
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

  public function dieDieDie(Cell $destination, Move $move = NULL) {
    $this->setAlive(FALSE);
    $old_position = $this->getPosition();
    $this->setPosition($destination);
    $trigerred_events = array();
    if ($this->getDescription()->hasHabilityMustLive()) {
      $main_event = new Event(new GlossaryTerm(Glossary::EVENT_LEADER_KILLED, array(
        '!faction_id' => $this->getFaction()->getId(),
        '!piece_id' => $this->getId(),
      )));
      $trigerred_events[] = $main_event;
      if ($old_position->getType() == Cell::TYPE_THRONE) {
        $trigerred_events[] = new Event(new GlossaryTerm(Glossary::EVENT_THRONE_MURDER, array(
          '!piece_id' => $this->getId(),
        )));
      }
      if ($destination->getType() == Cell::TYPE_THRONE) {
        $trigerred_events[] = new Event(new GlossaryTerm(Glossary::EVENT_THRONE_MAUSOLEUM, array(
          '!piece_id' => $this->getId(),
        )));
      }
      $old_control = $this->getFaction()->getControl()->getId();
      $this->getFaction()->setControl($this->getBattlefield()->getCurrentTurn()->getActingFaction());
      $main_event->logChange(new FactionChange($this->getFaction(), 'controlId', $old_control, $this->getFaction()->getControl()->getId()));
      $this->getFaction()->dieDieDie(Faction::STATUS_KILLED);
    }
    if (!empty($trigerred_events)) {
      foreach ($trigerred_events as $event) {
        if (is_null($move)) {
          $this->getBattlefield()->getCurrentTurn()->logEvent($event);
        }
        else {
          $move->triggerEvent($event);
        }
      }
    }
  }

  public function checkAvailableMove(Cell $cell, $allow_interactions, $force_empty = FALSE) {
    $move_ok = FALSE;
    $occupant = $cell->getOccupant();
    if ($force_empty || empty($occupant)) {
      if ($cell->getType() != Cell::TYPE_THRONE || $this->getDescription()->hasHabilityAccessThrone()) {
        $move_ok = TRUE;
      }
    }
    elseif (Murder::checkMurderingPossibility($this, $occupant, $allow_interactions)
    || Manipulation::checkManipulatingPossibility($this, $occupant, $allow_interactions)
    || Necromobility::checkNecromobilityPossibility($this, $occupant, $allow_interactions)) {
      $move_ok = TRUE;
    }
    return $move_ok;
  }

  public function setSelectable($bool) {
    $this->selectable = $bool;
    return $this;
  }

  public function isSelectable() {
    return $this->selectable;
  }

  public function isSelected() {
    if (!empty($this->getBattlefield()->getCurrentTurn()) && !empty($this->getBattlefield()->getCurrentTurn()->getMove()->getSelectedPiece())) {
      return $this->getBattlefield()->getCurrentTurn()->getMove()->getSelectedPiece()->getId() == $this->getId();
    }
    return FALSE;
  }

  protected function prepareSerialization() {
    $this->addUnserializableProperties(array('selectable'));
    return $this->traitSerialization();
  }

}
