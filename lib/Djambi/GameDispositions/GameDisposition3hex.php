<?php

namespace Djambi\GameDispositions;

use Djambi\Factories\GameDispositionsFactory;

class GameDisposition3hex extends BaseGameDisposition {
  public function __construct(GameDispositionsFactory $factory, $settings = NULL) {
    $this->useHexagonalGrid($settings)->setNbPlayers(3)->useStandardRuleset();
  }
}
