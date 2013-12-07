<?php

namespace Djambi\GameDispositions;

use Djambi\Factories\GameDispositionsFactory;
use Djambi\GameDisposition;

class GameDisposition4std extends GameDisposition {
  public function __construct(GameDispositionsFactory $factory, $settings = NULL) {
    $this->useStandardGrid($settings)->setNbPlayers(4)->useStandardRuleset();
  }
}
