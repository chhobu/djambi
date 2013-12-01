<?php

namespace Djambi\IA;

use Djambi\IA;
use Djambi\Interfaces\MoveInteractionInterface;
use Djambi\Players\ComputerPlayer;

class DummyIA extends IA {
  protected  function __construct(ComputerPlayer $player, $name = 'BetaBot') {
    parent::__construct($player, $name);
  }

  public function decideInteraction(MoveInteractionInterface $move) {
    $choices = $move->getPossibleChoices();
    return $choices[array_rand($choices, 1)];
  }

  public function decideMove(array $moves) {
    return $moves[array_rand($moves, 1)];
  }
}
