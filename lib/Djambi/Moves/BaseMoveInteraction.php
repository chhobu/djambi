<?php

namespace Djambi\Moves;


use Djambi\Exceptions\IllogicMoveException;
use Djambi\Gameplay\BattlefieldInterface;
use Djambi\Gameplay\Cell;
use Djambi\Gameplay\Piece;

abstract class BaseMoveInteraction extends Move implements MoveInteractionInterface {
  /** @var  Move */
  private $triggeringMove;
  /** @var Cell[] */
  private $possibleChoices = array();

  protected function prepareArrayConversion() {
    $this->addDependantObjects(array('possibleChoices' => 'id'));
    return parent::prepareArrayConversion();
  }

  public static function fromArray(array $array, array $context = array()) {
    /** @var BattlefieldInterface $battlefield */
    $battlefield = $context['battlefield'];
    /** @var BaseMoveInteraction $interaction */
    $interaction = parent::fromArray($array, $context);
    if (!empty($array['possibleChoices'])) {
      $interactions = array();
      /** @var Cell $cell */
      foreach ($array['possibleChoices'] as $cell) {
        $interactions[] = $battlefield->findCellByName($cell);
      }
      $interaction->setPossibleChoices($interactions);
    }
    return $interaction;
  }

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

  protected function setDestination(Cell $cell) {
    $this->destination = $cell;
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
