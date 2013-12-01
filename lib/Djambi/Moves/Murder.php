<?php

namespace Djambi\Moves;


use Djambi\Cell;
use Djambi\Exceptions\DisallowedActionException;
use Djambi\Interfaces\MoveInteractionInterface;
use Djambi\Move;
use Djambi\Piece;

class Murder extends MoveInteraction implements MoveInteractionInterface {

  public static function getInteractionType() {
    return 'murder';
  }

  public function __construct(Move $move, Piece $target) {
    parent::__construct($move);
    $this->selectPiece($target);
    if (!$this->getSelectedPiece()->isAlive()) {
      throw new DisallowedActionException("Attempt to kill a dead piece.");
    }
    elseif (!$this->getTriggeringMove()->getSelectedPiece()->checkAttackingPossibility($this->getSelectedPiece())) {
      throw new DisallowedActionException("Attempt to commit an impossible murder.");
    }
  }

  public function moveSelectedPiece(Cell $cell) {
    parent::moveSelectedPiece($cell);
    $this->findPossibleChoices();
    $possible_destinations = $this->getPossibleChoices();
    if (!isset($possible_destinations[$cell->getName()])) {
      throw new DisallowedActionException("Attempt to bury a corpse into an occupied cell.");
    }
    $this->executeChoice($cell);
    return $this;
  }

  public function findPossibleChoices() {
    $this->setPossibleChoices($this->getActingFaction()->getBattlefield()
      ->getFreeCells($this->getSelectedPiece(), FALSE, TRUE, $this->getTriggeringMove()->getSelectedPiece()->getPosition()));
    return $this;
  }

  public function executeChoice(Cell $cell) {
    $this->getTriggeringMove()->getSelectedPiece()->kill($this, $this->getSelectedPiece(), $cell);
    $this->checkCompleted();
    return $this;
  }
}
