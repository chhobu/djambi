<?php

namespace Djambi\Moves;

use Djambi\Exceptions\DisallowedActionException;
use Djambi\Exceptions\IllogicMoveException;
use Djambi\Gameplay\BattlefieldInterface;
use Djambi\Gameplay\Cell;
use Djambi\Gameplay\Event;
use Djambi\Gameplay\Faction;
use Djambi\Gameplay\Piece;
use Djambi\Persistance\PersistantDjambiObject;
use Djambi\Strings\Glossary;
use Djambi\Strings\GlossaryTerm;

class Move extends PersistantDjambiObject {
  const PHASE_PIECE_SELECTION = 'piece_selection';
  const PHASE_PIECE_DESTINATION = 'piece_destination';
  const PHASE_PIECE_INTERACTIONS = 'move_interactions';
  const PHASE_FINISHED = 'finished';

  /** @var Faction */
  protected $actingFaction;
  /** @var Piece */
  protected $selectedPiece;
  /** @var Cell */
  protected $origin;
  /** @var Cell */
  protected $destination;
  /** @var string */
  protected $phase = self::PHASE_PIECE_SELECTION;
  /** @var MoveInteractionInterface[] */
  protected $interactions = array();
  /** @var Piece[] */
  protected $kills = array();
  /** @var Event[] */
  protected $events = array();

  protected function prepareArrayConversion() {
    $objects = array(
      'selectedPiece' => 'id',
      'actingFaction' => 'id',
      'destination' => 'name',
      'origin' => 'name',
    );
    if (!empty($this->kills)) {
      $objects['kills'] = 'id';
    }
    $this->addDependantObjects($objects);
    $persist = array();
    if (!$this->isCompleted()) {
      $persist[] = 'phase';
      if (!empty($this->events)) {
        $persist[] = 'events';
      }
    }
    if (!empty($this->interactions)) {
      $persist[] = 'interactions';
    }
    $this->addPersistantProperties($persist);
    return parent::prepareArrayConversion();
  }

  public static function fromArray(array $array, array $context = array()) {
    /** @var BattlefieldInterface $battlefield */
    $battlefield = $context['battlefield'];
    /** @var Move $move */
    $move = new static($battlefield->findFactionById($array['actingFaction']));
    if (!empty($array['phase'])) {
      $move->setPhase($array['phase']);
    }
    else {
      $move->setPhase(static::PHASE_FINISHED);
    }
    if (!empty($array['selectedPiece'])) {
      $move->setSelectedPiece($battlefield->findPieceById($array['selectedPiece']));
    }
    if (!empty($array['destination'])) {
      $move->destination = $battlefield->findCellByName($array['destination']);
    }
    if (!empty($array['origin'])) {
      $move->origin = $battlefield->findCellByName($array['origin']);
    }
    if (!empty($array['phase'])) {
      $move->setPhase($array['phase']);
    }
    else {
      $move->setPhase(static::PHASE_FINISHED);
    }
    if (!empty($array['interactions'])) {
      $context['move'] = $move;
      foreach ($array['interactions'] as $interaction) {
        $interaction = call_user_func($interaction['className'] . '::fromArray', $interaction, $context);
        $move->interactions[] = $interaction;
      }
    }
    if (!empty($array['kills'])) {
      foreach ($array['kills'] as $position => $piece_id) {
        $move->kills[$position] = $battlefield->findPieceById($piece_id);
      }
    }
    if (!empty($array['events'])) {
      $move->setEvents($array['events']);
    }
    return $move;
  }

  public function __construct(Faction $faction) {
    $this->setActingFaction($faction);
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
      throw new DisallowedActionException(new GlossaryTerm(Glossary::EXCEPTION_MOVE_UNCONTROLLED,
        array('%piece_id' => $piece->getId())));
    }
    if (!$piece->isAlive() || !$piece->isMovable()) {
      throw new DisallowedActionException(new GlossaryTerm(Glossary::EXCEPTION_MOVE_UNMOVABLE));
    }
    $this->setSelectedPiece($piece);
    $this->setOrigin($piece->getPosition());
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
      throw new DisallowedActionException(new GlossaryTerm(Glossary::EXCEPTION_MOVE_DISALLOWED,
        array('%piece_id' => $this->getSelectedPiece()->getId(), '%location' => $cell->getName())));
    }
    $this->destination = $cell;
    return $this;
  }

  protected function setOrigin(Cell $cell) {
    $this->origin = $cell;
    return $this;
  }

  public function getOrigin() {
    return $this->origin;
  }

  public function executeChoice(Cell $cell) {
    if ($this->getPhase() == self::PHASE_PIECE_SELECTION) {
      throw new IllogicMoveException(new GlossaryTerm(Glossary::EXCEPTION_MOVE_ILLOGIC));
    }
    $this->setDestination($cell);
    if ($this->prepareMove($this->getPhase() != self::PHASE_PIECE_INTERACTIONS)) {
      $this->executeMove();
      $this->setPhase(self::PHASE_PIECE_INTERACTIONS);
      $this->checkCompleted();
    }
    else {
      throw new DisallowedActionException(new GlossaryTerm(Glossary::EXCEPTION_MOVE_DISALLOWED,
        array('%piece_id' => $this->getSelectedPiece()->getId(), '%location' => $cell)));
    }
    return $this;
  }

  public function evaluateChoice(Cell $cell) {
    $this->setDestination($cell);
    if ($this->prepareMove(TRUE)) {
      foreach ($this->getInteractions() as $interaction) {
        $interaction->findPossibleChoices();
      }
    }
    else {
      throw new DisallowedActionException(new GlossaryTerm(Glossary::EXCEPTION_MOVE_DISALLOWED,
        array('%piece_id' => $this->getSelectedPiece()->getId(), '%location' => $cell)));
    }
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

  public function isCompleted() {
    return $this->phase == static::PHASE_FINISHED;
  }

  public function checkCompleted() {
    $interaction = $this->getFirstInteraction();
    if (is_null($interaction) && $this->getPhase() == self::PHASE_PIECE_INTERACTIONS) {
      $battlefield = $this->getSelectedPiece()->getFaction()->getBattlefield();
      $event = new Event(new GlossaryTerm(Glossary::EVENT_MOVE_COMPLETED, array(
        '!faction_id' => $this->getActingFaction())), Event::LOG_LEVEL_MINOR);
      array_unshift($this->events, $event);
      foreach ($this->getEvents() as $event) {
        $battlefield->getCurrentTurn()->logEvent($event);
        if ($event->getDescription()->getString() == Glossary::EVENT_DIPLOMAT_GOLDEN_MOVE) {
          $args = $event->getDescription()->getArgs();
          Manipulation::triggerGoldenMove($this, $battlefield->findPieceById($args['!target_id']),
            $battlefield->findCellByName($args['!position']));
        }
      }
      $this->setPhase(static::PHASE_FINISHED);
      $this->getActingFaction()->getBattlefield()->changeTurn();
    }
    return $this->isCompleted();
  }

  public function triggerKill(Piece $piece, Cell $location) {
    $this->kills[$location->getName()] = $piece;
    return $this;
  }

  public function getKills() {
    return $this->kills;
  }

  public function triggerEvent(Event $event) {
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

  protected function prepareMove($allow_interactions) {
    $target = $this->getDestination()->getOccupant();
    $move_ok = FALSE;
    // Vérifie les conséquences d'un déplacement si le déplacement se fait
    // sur une case occupée :
    if ($this->getDestination()->getName() != $this->getSelectedPiece()->getPosition()->getName()) {
      if (!empty($target)) {
        $manipulable = Manipulation::isTriggerable($this, $target, $allow_interactions);
        $necromovable = Necromobility::isTriggerable($this, $target, $allow_interactions);
        $murderable = Murder::isTriggerable($this, $target, $allow_interactions);
        if ($manipulable || $necromovable || $murderable) {
          $move_ok = TRUE;
        }
      }
      else {
        Reportage::isTriggerable($this, NULL, $allow_interactions);
        $move_ok = TRUE;
      }
    }
    if ($move_ok) {
      ThroneEvacuation::isTriggerable($this);
      if ($this->getSelectedPiece()->getDescription()->hasHabilityAccessThrone()) {
        if ($this->getDestination()->getType() == Cell::TYPE_THRONE) {
          $this->triggerEvent(new Event(new GlossaryTerm(Glossary::EVENT_THRONE_ACCESS, array(
            '!piece_id' => $this->getSelectedPiece()->getId(),
          ))));
        }
        elseif ($this->getOrigin()->getType() == Cell::TYPE_THRONE) {
          $this->triggerEvent(new Event(new GlossaryTerm(Glossary::EVENT_THRONE_RETREAT, array(
            '!piece_id' => $this->getSelectedPiece()->getId(),
          ))));
        }
      }
    }
    return $move_ok;
  }

  protected function executeMove() {
    $battlefield = $this->getSelectedPiece()->getFaction()->getBattlefield();
    $this->getSelectedPiece()->setPosition($this->getDestination());
    foreach ($this->getKills() as $location => $victim) {
      $victim->dieDieDie($battlefield->findCellByName($location), $this);
    }
    return $this;
  }

  public function revert($full_clean = TRUE) {
    /** @var MoveInteractionInterface $interaction */
    foreach (array_reverse($this->interactions) as $interaction) {
      $interaction->revert();
    }
    foreach ($this->kills as $victim) {
      $victim->setAlive(TRUE);
      if ($this->getSelectedPiece()->getDescription()->hasHabilitySignature()) {
        $victim->setPosition($this->getDestination());
      }
    }
    if (!is_null($this->getSelectedPiece())) {
      $this->getSelectedPiece()->setPosition($this->getOrigin());
      if ($full_clean) {
        $this->getSelectedPiece()->buildAllowableMoves();
        $this->setPhase(static::PHASE_PIECE_SELECTION);
        $this->selectedPiece = NULL;
        $this->destination = NULL;
        $this->origin = NULL;
        $this->interactions = array();
        $this->kills = array();
        $this->events = array();
      }
    }
  }

}
