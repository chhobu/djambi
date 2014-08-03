<?php

namespace Djambi\IA;

use Djambi\Gameplay\Cell;
use Djambi\Moves\Move;
use Djambi\Moves\MoveInteractionInterface;

class PredictableIA extends BaseIA {
  protected $strategy;

  /**
   * Renvoie le choix mûrement réfléchi d'un mouvement.
   *
   * @param Move[] $moves
   *   Liste de tous les mouvements possibles
   *
   * @return Move
   *   Mouvement choisi
   **/
  public function decideMove(array $moves) {
    $file = $this->getSetting('strategy_file');
    if (!is_null($file)) {
      $json = file_get_contents($file);
      $strategy = json_decode($json, TRUE);
      unlink($file);
      if (isset($strategy['select']) && isset($strategy['destination'])) {
        foreach ($moves as $move) {
          if ($move->getSelectedPiece()->getPosition()->getName() == $strategy['select']
          && $move->getDestination()->getName() == $strategy['destination']) {
            $this->strategy = $strategy;
            return $move;
          }
        }
      }
    }
    return NULL;
  }

  /**
   * Renvoie un choix sur une interaction.
   *
   * @param MoveInteractionInterface $move
   *   Interaction suite à un mouvement
   *
   * @return Cell
   *   Choix de déplacement à réaliser
   */
  public function decideInteraction(MoveInteractionInterface $move) {
    if (!empty($this->strategy['interactions'])) {
      $interaction = current($this->strategy['interactions']);
      foreach ($move->getPossibleChoices() as $choice) {
        if ($choice->getName() == $interaction) {
          return $choice;
        }
      }
    }
    return NULL;
  }
}
