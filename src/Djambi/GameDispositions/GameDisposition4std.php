<?php

namespace Djambi\GameDispositions;

class GameDisposition4std extends BaseGameDisposition {
  public function __construct() {
    $this->useStandardGrid()->setNbPlayers(4)->useStandardRuleset();
  }
}
