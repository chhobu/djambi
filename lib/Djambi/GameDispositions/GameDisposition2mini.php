<?php

namespace Djambi\GameDispositions;

use Djambi\Factories\GameDispositionsFactory;

class GameDisposition2mini extends BaseGameDisposition {
  public function __construct(GameDispositionsFactory $factory, $settings = NULL) {
    $this->useMiniGridWith2Sides($settings);
    $this->useStandardRuleset();
    $this->setNbPlayers(2);
  }
}
