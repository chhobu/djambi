<?php

namespace Djambi\Moves;

use Djambi\Exceptions\DisallowedActionException;
use Djambi\Gameplay\Cell;
use Djambi\Gameplay\Piece;
use Djambi\Strings\Glossary;
use Djambi\Strings\GlossaryTerm;

class Murder extends BaseMoveInteraction implements MoveInteractionInterface {

  public function setSelectedPiece(Piece $target) {
    if (!$target->isAlive()) {
      throw new DisallowedActionException(new GlossaryTerm(Glossary::EXCEPTION_KILL_DEAD));
    }
    elseif (!$this->getTriggeringMove()->getSelectedPiece()->checkAttackingPossibility($target)) {
      throw new DisallowedActionException(new GlossaryTerm(Glossary::EXCEPTION_KILL_DISALLOWED, array(
        '%piece_id_1' => $target->getId(),
        '%piece_id_2' => $this->getTriggeringMove()->getSelectedPiece()->getId(),
      )));
    }
    return parent::setSelectedPiece($target);
  }

  public function findPossibleChoices() {
    $this->setPossibleChoices($this->getActingFaction()->getBattlefield()
      ->getFreeCells($this->getSelectedPiece(), FALSE, TRUE, $this->getTriggeringMove()->getSelectedPiece()->getPosition()));
    return $this;
  }

  public function executeChoice(Cell $cell) {
    $possible_destinations = $this->getPossibleChoices();
    if (!isset($possible_destinations[$cell->getName()])) {
      throw new DisallowedActionException(new GlossaryTerm(Glossary::EXCEPTION_KILL_WRONG_GRAVE,
        array('%location' => $cell->getName())));
    }
    $this->getSelectedPiece()->dieDieDie($cell);
    $this->checkCompleted();
    return $this;
  }
}
