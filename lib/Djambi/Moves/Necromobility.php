<?php

namespace Djambi\Moves;

use Djambi\Exceptions\DisallowedActionException;
use Djambi\Gameplay\Cell;
use Djambi\Gameplay\Piece;
use Djambi\Strings\Glossary;
use Djambi\Strings\GlossaryTerm;

class Necromobility extends BaseMoveInteraction implements MoveInteractionInterface {

  public static function isTriggerable(Move $move, Piece $target = NULL, $allow_interactions = TRUE) {
    if (!empty($target) && self::checkNecromobilityPossibility($move->getSelectedPiece(), $target, $allow_interactions) && $allow_interactions) {
      $move->triggerInteraction(new static($move, $target));
      return TRUE;
    }
    return FALSE;
  }

  public static function checkNecromobilityPossibility(Piece $piece, Piece $target, $allow_interactions) {
    return $allow_interactions && !$target->isAlive() && $piece->getDescription()->hasHabilityMoveDeadPieces();
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
