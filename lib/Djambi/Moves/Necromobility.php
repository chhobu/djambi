<?php

namespace Djambi\Moves;

use Djambi\Exceptions\DisallowedActionException;
use Djambi\Gameplay\Cell;
use Djambi\Gameplay\Event;
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
    $this->getSelectedPiece()->setPosition($cell);
    if ($cell->getType() == Cell::TYPE_THRONE) {
      $event = new Event(new GlossaryTerm(Glossary::EVENT_THRONE_MAUSOLEUM, array(
        '!piece_id' => $this->getSelectedPiece()->getId(),
      )));
      $this->getTriggeringMove()->triggerEvent($event);
    }
    return parent::executeChoice($cell);
  }

  public function revert() {
    $this->getSelectedPiece()->setPosition($this->getTriggeringMove()->getDestination());
  }
}
