<?php

namespace Djambi\GameDispositions;

use Djambi\Factories\GameDispositionsFactory;
use Djambi\GameDisposition;

class GameDisposition2mini extends GameDisposition {
  public function __construct(GameDispositionsFactory $factory, $settings = NULL) {
    $this->useMiniGridWith2Sides($settings);
    $this->useStandardRuleset();
    $this->setNbPlayers(2);
  }
}
