<?php

namespace Djambi\Moves;

use Djambi\Exceptions\DisallowedActionException;
use Djambi\Gameplay\Cell;
use Djambi\Gameplay\Piece;

class Necromobility extends BaseMoveInteraction implements MoveInteractionInterface {

  public static function getInteractionType() {
    return 'necromobility';
  }

  public function __construct(Move $move, Piece $target) {
    parent::__construct($move);
    $this->selectPiece($target);
    if ($this->getSelectedPiece()->isAlive()) {
      throw new DisallowedActionException("Attempt to bury a living piece !");
    }
    elseif (!$this->getTriggeringMove()->getSelectedPiece()->getDescription()->hasHabilityMoveDeadPieces()) {
      throw new DisallowedActionException("Attempt to move dead pieces with an unqualified piece.");
    }
  }

  public function moveSelectedPiece(Cell $cell) {
    parent::moveSelectedPiece($cell);
    $this->findPossibleChoices();
    $possible_destinations = $this->getPossibleChoices();
    if (!isset($possible_destinations[$cell->getName()])) {
      throw new DisallowedActionException("Attempt to move a dead piece into an occupied cell.");
    }
    $this->executeChoice($cell);
    return $this;
  }

  public function findPossibleChoices() {
    $this->setPossibleChoices($this->getActingFaction()->getBattlefield()
      ->getFreeCells($this->getSelectedPiece(), FALSE, FALSE, $this->getTriggeringMove()->getSelectedPiece()->getPosition()));
    return $this;
  }

  public function executeChoice(Cell $cell) {
    $this->getTriggeringMove()->getSelectedPiece()->necromove($this, $this->getSelectedPiece(), $cell);
    $this->checkCompleted();
    return $this;
  }
}
