<?php

namespace Djambi\Moves;

use Djambi\Exceptions\DisallowedActionException;
use Djambi\Gameplay\Cell;
use Djambi\Gameplay\Piece;
use Djambi\Strings\Glossary;
use Djambi\Strings\GlossaryTerm;

class Necromobility extends BaseMoveInteraction implements MoveInteractionInterface {

  public function setSelectedPiece(Piece $target) {
    if ($target->isAlive()) {
      throw new DisallowedActionException(new GlossaryTerm(Glossary::EXCEPTION_NECROMOVE_ALIVE));
    }
    elseif (!$this->getTriggeringMove()->getSelectedPiece()->getDescription()->hasHabilityMoveDeadPieces()) {
      throw new DisallowedActionException(new GlossaryTerm(Glossary::EXCEPTION_NECROMOVE_DISALLOWED));
    }
    return parent::setSelectedPiece($target);
  }

  public function findPossibleChoices() {
    $this->setPossibleChoices($this->getActingFaction()->getBattlefield()
      ->getFreeCells($this->getSelectedPiece(), FALSE, FALSE, $this->getTriggeringMove()->getSelectedPiece()->getPosition()));
    return $this;
  }

  public function executeChoice(Cell $cell) {
    $possible_destinations = $this->getPossibleChoices();
    if (!isset($possible_destinations[$cell->getName()])) {
      throw new DisallowedActionException(new GlossaryTerm(Glossary::EXCEPTION_KILL_WRONG_GRAVE,
        array('%location' => $cell->getName())));
    }
    $this->getTriggeringMove()->getSelectedPiece()->getFaction()
      ->getBattlefield()->logMove($this->getSelectedPiece(), $cell, "necromobility", $this->getTriggeringMove()->getSelectedPiece());
    $this->getSelectedPiece()->setPosition($cell);
    $this->checkCompleted();
    return $this;
  }
}
