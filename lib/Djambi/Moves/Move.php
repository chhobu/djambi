<?php

namespace Djambi\Moves;

use Djambi\Exceptions\DisallowedActionException;
use Djambi\Exceptions\IllogicMoveException;
use Djambi\Gameplay\BattlefieldInterface;
use Djambi\Gameplay\Cell;
use Djambi\Gameplay\Faction;
use Djambi\Gameplay\Piece;
use Djambi\Persistance\PersistantDjambiObject;

class Move extends PersistantDjambiObject {
  const PHASE_PIECE_SELECTION = 'piece_selection';
  const PHASE_PIECE_DESTINATION = 'piece_destination';
  const PHASE_PIECE_INTERACTIONS = 'move_interactions';

  /** @var Faction */
  private $actingFaction;
  /** @var Piece */
  private $selectedPiece;
  /** @var Cell */
  protected $destination;
  /** @var string */
  private $phase = self::PHASE_PIECE_SELECTION;
  /** @var string */
  private $type = 'move';
  /** @var MoveInteractionInterface[] */
  private $interactions = array();
  /** @var bool */
  private $completed = FALSE;
  /** @var array */
  private $kills = array();
  /** @var array */
  private $events = array();

  protected function prepareArrayConversion() {
    $this->addDependantObjects(array(
      'selectedPiece' => 'id',
      'actingFaction' => 'id',
      'destination' => 'name',
    ));
    $this->addPersistantProperties(array(
      'phase',
      'type',
      'interactions',
      'kills',
      'events',
    ));
    return parent::prepareArrayConversion();
  }

  public static function fromArray(array $array, array $context = array()) {
    /** @var BattlefieldInterface $battlefield */
    $battlefield = $context['battlefield'];
    /** @var Move $move */
    $move = new static($battlefield->getFactionById($array['actingFaction']));
    if (!empty($array['selectedPiece'])) {
      $move->setSelectedPiece($battlefield->getPieceById($array['selectedPiece']));
    }
    $move->setPhase($array['phase']);
    $move->setType($array['type']);
    if (!empty($array['interactions'])) {
      foreach ($array['interactions'] as $interaction) {
        $interaction = call_user_func($interaction['className'] . '::fromArray', $interaction, $context);
        $move->interactions[] = $interaction;
      }
    }
    if (!empty($array['kills'])) {
      $move->setKills($array['kills']);
    }
    if (!empty($array['events'])) {
      $move->setEvents($array['events']);
    }
    return $move;
  }

  public function __construct(Faction $faction) {
    $this->setActingFaction($faction);
    $this->setType('move');
  }

  public function reset() {
    $this->selectedPiece = NULL;
    $this->destination = NULL;
    $this->phase = self::PHASE_PIECE_SELECTION;
    $this->interactions = array();
    $this->completed = FALSE;
    $this->events = array();
    $this->kills = array();
    $this->setType('move');
    return $this;
  }

  protected function setActingFaction(Faction $faction) {
    $this->actingFaction = $faction;
    return $this;
  }

  /**
   * @return Faction
   */
  public function getActingFaction() {
    return $this->actingFaction;
  }

  public function selectPiece(Piece $piece) {
    if ($this->getActingFaction()->getId() != $piece->getFaction()->getControl()->getId()) {
      throw new DisallowedActionException("Attempt to select an uncontrolled piece.");
    }
    if (!$piece->isAlive() || !$piece->isMovable()) {
      throw new DisallowedActionException("Attempt to select an unselectable piece.");
    }
    $this->setSelectedPiece($piece);
    $this->setPhase(self::PHASE_PIECE_DESTINATION);
    return $this;
  }

  protected function setSelectedPiece(Piece $piece) {
    $this->selectedPiece = $piece;
    return $this;
  }

  public function getSelectedPiece() {
    return $this->selectedPiece;
  }

  protected function setPhase($phase) {
    $this->phase = $phase;
    return $this;
  }

  public function getPhase() {
    return $this->phase;
  }

  protected function setDestination(Cell $cell) {
    if (!in_array($cell->getName(), $this->getSelectedPiece()->getAllowableMovesNames())) {
      throw new DisallowedActionException("Disallowed move : piece " . $this->getSelectedPiece()->getId() . " to " . $cell->getName());
    }
    $this->destination = $cell;
    return $this;
  }

  public function moveSelectedPiece(Cell $cell) {
    if ($this->getPhase() == self::PHASE_PIECE_DESTINATION) {
      $this->setDestination($cell);
      $this->setPhase(self::PHASE_PIECE_INTERACTIONS);
      $this->getSelectedPiece()->executeMove($this);
      $this->checkCompleted();
      return $this;
    }
    else {
      throw new IllogicMoveException("Attempt to choose piece destination before piece selection phase.");
    }
  }

  public function tryMoveSelectedPiece(Cell $cell) {
    $this->setDestination($cell);
    $this->getSelectedPiece()->evaluateMove($this);
    return $this;
  }

  public function getDestination() {
    return $this->destination;
  }

  public function triggerInteraction(MoveInteractionInterface $interaction) {
    $this->interactions[] = $interaction;
    return $this;
  }

  public function getInteractions() {
    return $this->interactions;
  }

  /**
   * @return MoveInteractionInterface
   */
  public function getFirstInteraction() {
    if (!empty($this->interactions)) {
      foreach ($this->interactions as $interaction) {
        if (!$interaction->isCompleted()) {
          return $interaction;
        }
      }
    }
    return NULL;
  }

  protected function setInteractions(array $înteractions) {
    $this->interactions = $înteractions;
    return $this;
  }

  public function getType() {
    return $this->type;
  }

  protected function setType($type) {
    $this->type = $type;
    return $this;
  }

  protected function setCompleted($bool) {
    $this->completed = $bool ? TRUE : FALSE;
    return $this;
  }

  public function isCompleted() {
    return $this->completed;
  }

  protected function checkCompleted() {
    $interaction = $this->getFirstInteraction();
    if (is_null($interaction) && $this->getPhase() == self::PHASE_PIECE_INTERACTIONS) {
      $this->setCompleted(TRUE);
      $this->endMove();
    }
    return $this->isCompleted();
  }

  protected function endMove() {
    $this->getActingFaction()->getBattlefield()->changeTurn();
  }

  public function triggerKill(Piece $piece, Cell $location) {
    $this->kills[$piece->getId()] = array('victim' => $piece, 'position' => $location);
    return $this;
  }

  public function getKills() {
    return $this->kills;
  }

  protected function setKills(array $kills) {
    $this->kills = $kills;
    return $this;
  }

  public function triggerEvent($event) {
    $this->events[] = $event;
    return $this;
  }

  public function getEvents() {
    return $this->events;
  }

  protected function setEvents(array $events) {
    $this->events = $events;
    return $this;
  }

}
