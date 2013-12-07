<?php

namespace Djambi\GameDispositions;

use Djambi\Factories\GameDispositionsFactory;
use Djambi\GameDisposition;

class GameDisposition3hex extends GameDisposition {
  public function __construct(GameDispositionsFactory $factory, $settings = NULL) {
    $this->useHexagonalGrid($settings)->setNbPlayers(3)->useStandardRuleset();
  }
}
