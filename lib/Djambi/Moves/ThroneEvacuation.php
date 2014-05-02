<?php

namespace Djambi\Moves;

use Djambi\Gameplay\Cell;

class ThroneEvacuation extends BaseMoveInteraction implements MoveInteractionInterface {

  public function findPossibleChoices() {
    $possible_choices = array();
    $this->getSelectedPiece()->buildAllowableMoves(FALSE, $this->getTriggeringMove()->getDestination());
    foreach ($this->getSelectedPiece()->getAllowableMoves() as $cell) {
      $possible_choices[$cell->getName()] = $cell;
    }
    $this->setPossibleChoices($possible_choices);
    return $this;
  }

  public function executeChoice(Cell $cell) {
    $this->getTriggeringMove()->executeChoice($cell);
    $this->checkCompleted();
    return $this;
  }
}
