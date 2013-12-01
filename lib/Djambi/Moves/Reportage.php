<?php

namespace Djambi\Moves;


use Djambi\Cell;
use Djambi\Exceptions\DisallowedActionException;
use Djambi\Exceptions\IllogicMoveException;
use Djambi\Interfaces\MoveInteractionInterface;
use Djambi\Move;
use Djambi\Piece;
use Djambi\Stores\StandardRuleset;

class Reportage extends MoveInteraction implements MoveInteractionInterface {
  /** @var Piece[] */
  private $potentialTargets;

  public static function getInteractionType() {
    return 'reportage';
  }

  public function __construct(Move $move) {
    parent::__construct($move);
    $this->selectPiece($this->getTriggeringMove()->getSelectedPiece());
  }

  public function setPotentialTargets($potential_targets) {
    $this->potentialTargets = $potential_targets;
    return $this;
  }

  public function getPotentialTargets() {
    return $this->potentialTargets;
  }

  public function setVictim(Cell $cell) {
    if ($this->getPhase() != self::PHASE_PIECE_DESTINATION) {
      throw new IllogicMoveException("Attempt to choose piece destination before piece selection phase during move interactions.");
    }
    $victim = $cell->getOccupant();
    if (empty($victim)) {
      throw new DisallowedActionException("Attempt to make a reportage about just absolutely nothing. Not interesting !");
    }
    elseif (!$victim->isAlive()) {
      throw new DisallowedActionException("Attempt to make a reportage about a dead piece. Too late.");
    }
    elseif ($this->getActingFaction()->getBattlefield()->getGameManager()->getOption(StandardRuleset::RULE_REPORTERS) == 'pravda'
      && $victim->getFaction()->getControl()->getId() == $this->getSelectedPiece()->getFaction()->getControl()->getId()) {
      throw new DisallowedActionException("Attempt to make a reportage about self controlled piece. Bad idea.");
    }
    $this->executeChoice($cell);
    return $this;
  }

  public function moveSelectedPiece(Cell $cell) {
    throw new IllogicMoveException("Destination cell cannot be set during a reportage.");
  }

  public function findPossibleChoices() {
    $ids = array();
    foreach ($this->getPotentialTargets() as $victim) {
      $ids[] = $victim->getPosition();
    }
    $this->setPossibleChoices($ids);
    return $this;
  }

  public function executeChoice(Cell $cell) {
    $this->getTriggeringMove()->getSelectedPiece()->kill($this, $cell->getOccupant(), $cell);
    $this->checkCompleted();
    return $this;
  }
}
