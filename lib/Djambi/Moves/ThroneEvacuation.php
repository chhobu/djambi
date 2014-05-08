<?php

namespace Djambi\Moves;

use Djambi\Gameplay\Cell;
use Djambi\Gameplay\Piece;

class ThroneEvacuation extends BaseMoveInteraction implements MoveInteractionInterface {

  public static function isTriggerable(Move $move, Piece $target = NULL, $allow_interactions = TRUE) {
    if (!$move->getSelectedPiece()->getDescription()->hasHabilityAccessThrone() && $move->getDestination()->getType() == Cell::TYPE_THRONE) {
      $move->triggerInteraction(new static($move));
    }
    return TRUE;
  }

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
