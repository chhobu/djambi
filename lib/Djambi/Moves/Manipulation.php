<?php

namespace Djambi\Moves;


use Djambi\Exceptions\DisallowedActionException;
use Djambi\Gameplay\Cell;
use Djambi\Gameplay\Piece;
use Djambi\Strings\Glossary;
use Djambi\Strings\GlossaryTerm;

class Manipulation extends BaseMoveInteraction implements MoveInteractionInterface {

  public function setSelectedPiece(Piece $target) {
    if (!$target->isAlive()) {
      throw new DisallowedActionException(new GlossaryTerm(Glossary::EXCEPTION_MANIPULATION_DEAD));
    }
    elseif (!$this->getTriggeringMove()->getSelectedPiece()->checkManipulatingPossibility($target)) {
      throw new DisallowedActionException(new GlossaryTerm(Glossary::EXCEPTION_MANIPULATION_WRONG,
        array('%piece_id' => $target->getId())));
    }
    return parent::setSelectedPiece($target);
  }

  public function findPossibleChoices() {
    $this->setPossibleChoices($this->getActingFaction()->getBattlefield()
      ->getFreeCells($this->getSelectedPiece(), TRUE, FALSE, $this->getTriggeringMove()->getSelectedPiece()->getPosition()));
    return $this;
  }

  public function executeChoice(Cell $cell) {
    $possible_destinations = $this->getPossibleChoices();
    if (!isset($possible_destinations[$cell->getName()])) {
      throw new DisallowedActionException(new GlossaryTerm(Glossary::EXCEPTION_MANIPULATION_BAD_DESTINATION,
        array('%piece_id' => $this->getSelectedPiece()->getId(), '%location' => $cell->getName())));
    }
    $this->getTriggeringMove()->getSelectedPiece()->getFaction()
      ->getBattlefield()->logMove($this->getSelectedPiece(), $cell, "manipulation", $this->getTriggeringMove()->getSelectedPiece());
    $this->getSelectedPiece()->setPosition($cell);
    $this->checkCompleted();
    return $this;
  }

}
