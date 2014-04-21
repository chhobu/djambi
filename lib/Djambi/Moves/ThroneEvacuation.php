<?php

namespace Djambi\Moves;

use Djambi\Exceptions\DisallowedActionException;
use Djambi\Gameplay\Cell;

class ThroneEvacuation extends BaseMoveInteraction implements MoveInteractionInterface {

  public static function getInteractionType() {
    return 'throne_evacuation';
  }

  public function __construct(Move $move) {
    parent::__construct($move);
    $this->selectPiece($this->getTriggeringMove()->getSelectedPiece());
  }

  public function moveSelectedPiece(Cell $cell) {
    parent::moveSelectedPiece($cell);
    if (!in_array($cell->getName(), $this->getSelectedPiece()->getAllowableMovesNames())) {
      throw new DisallowedActionException("Disallowed move : piece " . $this->getSelectedPiece()->getId() . " to " . $cell->getName());
    }
    $this->executeChoice($cell);
    return $this;
  }

  public function findPossibleChoices() {
    $this->getSelectedPiece()->buildAllowableMoves(FALSE, $this->getTriggeringMove()->getDestination());
    $this->setPossibleChoices($this->getSelectedPiece()->getAllowableMoves());
    return $this;
  }

  public function executeChoice(Cell $cell) {
    $this->getTriggeringMove()->getSelectedPiece()->evacuate($this, $cell);
    $this->checkCompleted();
    return $this;
  }
}
