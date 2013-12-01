<?php

namespace Djambi\Interfaces;

use Djambi\Cell;
use Djambi\Move;

interface MoveInteractionInterface {

  /** @return Move */
  public function getTriggeringMove();

  /** @return bool */
  public function isCompleted();

  /** @return string */
  public function getType();

  /** @return string */
  public static function getInteractionType();

  /** @return MoveInteractionInterface */
  public function findPossibleChoices();

  /** @return Cell[] */
  public function getPossibleChoices();

  /**
   * Exécute une interaction.
   *
   * @param \Djambi\Cell $cell
   *
   * @return MoveInteractionInterface
   */
  public function executeChoice(Cell $cell);
}
