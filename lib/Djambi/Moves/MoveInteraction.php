<?php

namespace Djambi\Moves;


use Djambi\Cell;
use Djambi\Exceptions\IllogicMoveException;
use Djambi\Interfaces\MoveInteractionInterface;
use Djambi\Move;
use Djambi\Piece;

abstract class MoveInteraction extends Move implements MoveInteractionInterface {
  /** @var  Move */
  private $triggeringMove;
  /** @var Cell[] */
  private $possibleChoices = array();

  public function __construct(Move $move) {
    $this->setType(static::getInteractionType());
    $this->setTriggeringMove($move);
  }

  public function getTriggeringMove() {
    return $this->triggeringMove;
  }

  protected function setTriggeringMove(Move $move) {
    $this->triggeringMove = $move;
    return $this;
  }

  public function selectPiece(Piece $piece) {
    $this->setSelectedPiece($piece);
    $this->setPhase(self::PHASE_PIECE_DESTINATION);
    return $this;
  }

  public function getActingFaction() {
    return $this->getTriggeringMove()->getActingFaction();
  }

  protected function checkCompleted() {
    $this->setCompleted(TRUE);
    return $this->getTriggeringMove()->checkCompleted();
  }

  public function triggerInteraction(MoveInteractionInterface $interaction) {
    $this->getTriggeringMove()->triggerInteraction($interaction);
    return $this;
  }

  public function getPossibleChoices() {
    return $this->possibleChoices;
  }

  protected function setPossibleChoices(array $choices) {
    $this->possibleChoices = $choices;
    return $this;
  }

  public function moveSelectedPiece(Cell $cell) {
    if ($this->getPhase() == self::PHASE_PIECE_DESTINATION) {
      $this->setDestination($cell);
      return $this;
    }
    else {
      throw new IllogicMoveException("Attempt to choose piece destination before piece selection phase during move interactions.");
    }
  }

}
