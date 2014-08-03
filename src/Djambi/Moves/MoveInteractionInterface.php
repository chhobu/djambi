<?php

namespace Djambi\Moves;


use Djambi\Gameplay\Cell;
use Djambi\Gameplay\Piece;
use Djambi\Strings\GlossaryTerm;

interface MoveInteractionInterface {

  /**
   * @return Piece
   */
  public function getSelectedPiece();

  /**
   * @return Move
   */
  public function getTriggeringMove();

  /**
   * @return MoveInteractionInterface
   */
  public function findPossibleChoices();

  /**
   * @return Cell[]
   */
  public function getPossibleChoices();

  /**
   * @return Cell
   */
  public function getChoice();

  /**
   * @return bool
   */
  public function isCompleted();

  /**
   * Exécute une interaction.
   *
   * @param Cell $cell
   *
   * @return MoveInteractionInterface
   */
  public function executeChoice(Cell $cell);

  /**
   * Annule une interaction
   *
   * @return static
   */
  public function revert();

  /**
   * @param Move $move
   * @param Piece $target
   * @param bool $allow_interactions
   *
   * @return boolean
   */
  public static function isTriggerable(Move $move, Piece $target = NULL, $allow_interactions = TRUE);

  /**
   * @return GlossaryTerm
   */
  public function getMessage();

  /**
   * @param array $items
   * @param array $interaction_history
   * @param array $turn_history
   *
   * @return void
   */
  public static function log(array &$items, array $interaction_history, array $turn_history);

  /**
   * @return bool
   */
  public function isDealingWithPiecesOnly();
}
