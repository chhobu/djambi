<?php

namespace Djambi\GameDispositions;

use Djambi\Factories\GameDispositionsFactory;

class GameDisposition4std extends BaseGameDisposition {
  public function __construct(GameDispositionsFactory $factory, $settings = NULL) {
    $this->useStandardGrid($settings)->setNbPlayers(4)->useStandardRuleset();
  }
}
