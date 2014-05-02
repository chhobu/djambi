<?php

namespace Djambi\Moves;

use Djambi\Exceptions\DisallowedActionException;
use Djambi\Exceptions\IllogicMoveException;
use Djambi\GameOptions\StandardRuleset;
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
  /** @var Piece[] */
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
    $current_cell = $this->getSelectedPiece()->getPosition();
    $battlefield = $this->getSelectedPiece()->getFaction()->getBattlefield();
    $target = $this->getDestination()->getOccupant();
    $move_ok = FALSE;
    $extra_interaction = FALSE;
    // Vérifie si la pièce dispose d'un droit d'interaction supplémentaire
    // lors d'une évacuation de trône :
    if (!$allow_interactions && $battlefield->getGameManager()->getOption(StandardRuleset::RULE_EXTRA_INTERACTIONS) == 'extended') {
      if ($current_cell->getType() == Cell::TYPE_THRONE && !empty($target) && $target->getDescription()->hasHabilityAccessThrone()) {
        $extra_interaction = TRUE;
      }
    }
    // Vérifie les conséquences d'un déplacement si le déplacement se fait
    // sur une case occupée :
    if ($this->getDestination()->getName() != $current_cell->getName()) {
      if (!empty($target)) {
        $this->checkTrigerringManipulation($move_ok, $target, $allow_interactions, $extra_interaction);
        $this->checkTrigerringNecromobily($move_ok, $target, $allow_interactions);
        $this->checkTrigerringMurder($move_ok, $target, $allow_interactions, $extra_interaction);
      }
      else {
        $this->checkTriggeringReportage($allow_interactions);
        $move_ok = TRUE;
      }
    }
    if ($move_ok && !$this->getSelectedPiece()->getDescription()->hasHabilityAccessThrone()
      && $this->getDestination()->getType() == Cell::TYPE_THRONE) {
      $this->triggerInteraction(new ThroneEvacuation($this));
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
        $golden_move = new Manipulation($this, $event['target']);
        $golden_move->executeChoice($event['position']);
        $this->triggerInteraction($golden_move);
        $battlefield->logEvent('event', 'DIPLOMAT_GOLDEN_MOVE', array('piece' => $this->getSelectedPiece()->getId()));
      }
      elseif ($event['type'] == 'assassin_golden_move') {
        $battlefield->logEvent('event', 'ASSASSIN_GOLDEN_MOVE', array('piece' => $this->getSelectedPiece()->getId()));
      }
    }
    return $this;
  }

  protected function checkTrigerringManipulation(&$move_ok, Piece $target, $allow_interactions, $extra_interaction) {
    $piece = $this->getSelectedPiece();
    if ($piece->getDescription()->hasHabilityMoveLivingPieces()) {
      if ($piece->getFaction()->getBattlefield()->getGameManager()->getOption(StandardRuleset::RULE_DIPLOMACY) == 'vassal') {
        $can_manipulate = $target->getFaction()->getId() != $piece->getFaction()->getId();
      }
      else {
        $can_manipulate = $target->getFaction()->getControl()->getId() != $piece->getFaction()->getControl()->getId();
      }
      if ($can_manipulate && $target->isAlive() && ($allow_interactions || $extra_interaction)) {
        if ($allow_interactions) {
          $this->triggerInteraction(new Manipulation($this, $target));
        }
        elseif ($extra_interaction) {
          $this->triggerEvent(array(
            'type' => 'diplomate_golden_move',
            'target' => $target,
            'position' => $piece->getPosition(),
          ));
        }
        $move_ok = TRUE;
      }
    }
  }

  protected function checkTrigerringNecromobily(&$move_ok, Piece $target, $allow_interactions) {
    if (!$target->isAlive() && $this->getSelectedPiece()->getDescription()->hasHabilityMoveDeadPieces() && $allow_interactions) {
      $this->triggerInteraction(new Necromobility($this, $target));
      $move_ok = TRUE;
    }
  }

  protected function checkTrigerringMurder(&$move_ok, Piece $target, $allow_interactions, $extra_interaction) {
    $piece = $this->getSelectedPiece();
    if ($target->isAlive()) {
      // Signature de l'assassin
      $test = $piece->getDescription()->hasHabilityKillByAttack();
      if ($piece->getDescription()->hasHabilitySignature() && $test && ($allow_interactions || $extra_interaction)) {
        $this->triggerKill($target, $piece->getPosition());
        $move_ok = TRUE;
        if ($extra_interaction) {
          $this->triggerEvent(array('type' => 'assassin_golden_move'));
        }
      }
      // Déplacement du corps de la victime :
      elseif ($test && $allow_interactions) {
        $this->triggerInteraction(new Murder($this, $target));
        $move_ok = TRUE;
      }
    }
  }

  protected function checkTriggeringReportage($allow_interactions) {
    $piece = $this->getSelectedPiece();
    if ($piece->getDescription()->hasHabilityKillByProximity() && $allow_interactions) {
      $grid = $piece->getFaction()->getBattlefield();
      $next_cells = $grid->findNeighbourCells($this->getDestination(), FALSE);
      $victims = array();
      foreach ($next_cells as $key) {
        $cell = $grid->findCell($key['x'], $key['y']);
        $occupant = $cell->getOccupant();
        if (!empty($occupant)) {
          if ($occupant->isAlive() && $occupant->getId() != $piece->getId()) {
            if ($grid->getGameManager()->getOption(StandardRuleset::RULE_REPORTERS) == 'foxnews' ||
              $occupant->getFaction()->getControl()->getId() != $piece->getFaction()->getControl()->getId()) {
              $canibalism = $grid->getGameManager()->getOption(StandardRuleset::RULE_CANIBALISM);
              if ($canibalism != 'ethical' || $occupant->getFaction()->getControl()->isAlive()) {
                $victims[$cell->getName()] = $occupant->getPosition();
              }
            }
          }
        }
      }
      if ($grid->getGameManager()->getOption(StandardRuleset::RULE_REPORTERS) == 'pravda' && count($victims) > 1) {
        $reportage = new Reportage($this);
        $this->triggerInteraction($reportage->setTargets($victims));
      }
      elseif (!empty($victims)) {
        /* @var Cell $victim_cell */
        foreach ($victims as $victim_cell) {
          $this->triggerKill($victim_cell->getOccupant(), $victim_cell);
        }
      }
    }
  }

}
