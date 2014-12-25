<?php

namespace Djambi\Moves;

use Djambi\Gameplay\Cell;
use Djambi\Gameplay\Piece;
use Djambi\PieceDescriptions\Habilities\HabilityAccessThrone;
use Djambi\Strings\Glossary;
use Djambi\Strings\GlossaryTerm;

class ThroneEvacuation extends BaseMoveInteraction implements MoveInteractionInterface {

  public static function isTriggerable(Move $move, Piece $target = NULL, $allow_interactions = TRUE) {
    if (!$move->getSelectedPiece()->getDescription() instanceof HabilityAccessThrone && $move->getDestination()->getType() == Cell::TYPE_THRONE) {
      $move->triggerInteraction(new self($move));
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

  public static function log(array &$items, array $interaction_history, array $turn_history) {
    $items[] = new GlossaryTerm(Glossary::INTERACTION_MOVE_LOG, array(
      '@piece_id' => $interaction_history['selectedPiece'],
      '%origin' => $turn_history['move']['destination'],
      '%destination' => $interaction_history['choice'],
    ));
  }
}
