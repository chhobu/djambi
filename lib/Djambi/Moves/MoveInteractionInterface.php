<?php

namespace Djambi\Moves;


use Djambi\Gameplay\Cell;
use Djambi\Gameplay\Piece;

interface MoveInteractionInterface {

  /**
   * @return Piece
   */
  public function getSelectedPiece();

  /** @return Move */
  public function getTriggeringMove();

  /** @return MoveInteractionInterface */
  public function findPossibleChoices();

  /** @return Cell[] */
  public function getPossibleChoices();

  /** @return Cell */
  public function getChoice();

  public function isCompleted();

  /**
   * Exécute une interaction.
   *
   * @param Cell $cell
   *
   * @return MoveInteractionInterface
   */
  public function executeChoice(Cell $cell);

  public function revert();

  public static function isTriggerable(Move $move, Piece $target = NULL, $allow_interactions = TRUE);

  public function getMessage();
}
