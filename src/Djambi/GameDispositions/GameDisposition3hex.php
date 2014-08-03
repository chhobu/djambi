<?php

namespace Djambi\GameDispositions;

class GameDisposition3hex extends BaseGameDisposition {
  public function __construct() {
    $this->useHexagonalGrid()->setNbPlayers(3)->useStandardRuleset();
  }
}
