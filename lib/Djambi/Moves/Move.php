<?php

namespace Djambi\Moves;

use Djambi\Exceptions\DisallowedActionException;
use Djambi\Exceptions\IllogicMoveException;
use Djambi\Gameplay\BattlefieldInterface;
use Djambi\Gameplay\Cell;
use Djambi\Gameplay\Faction;
use Djambi\Gameplay\Piece;
use Djambi\Persistance\PersistantDjambiObject;
use Djambi\Strings\Glossary;
use Djambi\Strings\GlossaryTerm;

class Move extends PersistantDjambiObject {
  const PHASE_PIECE_SELECTION = 'piece_selection';
  const PHASE_PIECE_DESTINATION = 'piece_destination';
  const PHASE_PIECE_INTERACTIONS = 'move_interactions';

  /** @var Faction */
  protected $actingFaction;
  /** @var Piece */
  protected $selectedPiece;
  /** @var Cell */
  protected $destination;
  /** @var string */
  protected $phase = self::PHASE_PIECE_SELECTION;
  /** @var string */
  protected $type = 'move';
  /** @var MoveInteractionInterface[] */
  protected $interactions = array();
  /** @var bool */
  protected $completed = FALSE;
  /** @var Piece[] */
  protected $kills = array();
  /** @var array */
  protected $events = array();

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
    $move = new static($battlefield->findFactionById($array['actingFaction']));
    if (!empty($array['selectedPiece'])) {
      $move->setSelectedPiece($battlefield->findPieceById($array['selectedPiece']));
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
      throw new DisallowedActionException(new GlossaryTerm(Glossary::EXCEPTION_MOVE_UNCONTROLLED));
    }
    if (!$piece->isAlive() || !$piece->isMovable()) {
      throw new DisallowedActionException(new GlossaryTerm(Glossary::EXCEPTION_MOVE_UNMOVABLE));
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
      throw new DisallowedActionException(new GlossaryTerm(Glossary::EXCEPTION_MOVE_DISALLOWED,
        array('%piece_id' => $this->getSelectedPiece()->getId(), '%location' => $cell->getName())));
    }
    $this->destination = $cell;
    return $this;
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

  public function checkCompleted() {
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
    $this->kills[$location->getName()] = $piece;
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
    }
    return $move_ok;
  }

  protected function executeMove() {
    $target = $this->getDestination()->getOccupant();
    $battlefield = $this->getSelectedPiece()->getFaction()->getBattlefield();
    $battlefield->logMove($this->getSelectedPiece(), $this->getDestination(), 'move', $target);
    $this->getSelectedPiece()->setPosition($this->getDestination());
    foreach ($this->getKills() as $location => $victim) {
      $victim->dieDieDie($battlefield->findCellByName($location));
    }
    foreach ($this->getEvents() as $event) {
      if ($event['type'] == 'diplomat_golden_move') {
        Manipulation::triggerGoldenMove($this, $event['target'], $event['position']);
        $battlefield->logEvent('event', 'DIPLOMAT_GOLDEN_MOVE', array('piece' => $this->getSelectedPiece()->getId()));
      }
      elseif ($event['type'] == 'assassin_golden_move') {
        $battlefield->logEvent('event', 'ASSASSIN_GOLDEN_MOVE', array('piece' => $this->getSelectedPiece()->getId()));
      }
    }
    return $this;
  }

}
