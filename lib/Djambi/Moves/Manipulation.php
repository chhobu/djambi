<?php

namespace Djambi\Moves;


use Djambi\Cell;
use Djambi\Exceptions\DisallowedActionException;
use Djambi\Interfaces\MoveInteractionInterface;
use Djambi\Move;
use Djambi\Piece;

class Manipulation extends MoveInteraction implements MoveInteractionInterface {

  public static function getInteractionType() {
    return 'manipulation';
  }

  public function __construct(Move $move, Piece $target) {
    parent::__construct($move);
    $this->selectPiece($target);
    if (!$this->getSelectedPiece()->isAlive()) {
      throw new DisallowedActionException("Attempt to manipulate a dead piece.");
    }
    elseif (!$this->getTriggeringMove()->getSelectedPiece()->checkManipulatingPossibility($this->getSelectedPiece())) {
      throw new DisallowedActionException("Attempt to manipulate an unmanipulable piece.");
    }
  }

  public function moveSelectedPiece(Cell $cell) {
    parent::moveSelectedPiece($cell);
    $this->findPossibleChoices();
    $possible_destinations = $this->getPossibleChoices();
    if (!isset($possible_destinations[$cell->getName()])) {
      throw new DisallowedActionException("Attempt to place manipulated piece into an occupied cell.");
    }
    $this->executeChoice($cell);
    return $this;
  }

  public function findPossibleChoices() {
    $this->setPossibleChoices($this->getActingFaction()->getBattlefield()
      ->getFreeCells($this->getSelectedPiece(), TRUE, FALSE, $this->getTriggeringMove()->getSelectedPiece()->getPosition()));
    return $this;
  }

  public function executeChoice(Cell $cell) {
    $this->getTriggeringMove()->getSelectedPiece()->manipulate($this, $this->getSelectedPiece(), $cell);
    $this->checkCompleted();
    return $this;
  }

}
