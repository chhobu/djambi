<?php

namespace Djambi\Moves;

use Djambi\Gameplay\Cell;
use Djambi\Gameplay\Piece;
use Djambi\Strings\Glossary;
use Djambi\Strings\GlossaryTerm;

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
    $this->getSelectedPiece()->setPosition($cell);
    return parent::executeChoice($cell);
  }

  public function revert() {
    $this->getSelectedPiece()->setPosition($this->getTriggeringMove()->getDestination());
  }

  public function getMessage() {
    return new GlossaryTerm(Glossary::INTERACTION_EVACUATION_MESSAGE, array(
      '!piece_id' => $this->getTriggeringMove()->getSelectedPiece()->getId(),
    ));
  }
}
