<?php

namespace Djambi\GameDispositions;

class GameDisposition2mini extends BaseGameDisposition {
  public function __construct() {
    $this->useMiniGridWith2Sides();
    $this->useStandardRuleset();
    $this->setNbPlayers(2);
  }
}
