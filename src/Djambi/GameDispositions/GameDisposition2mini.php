<?php

namespace Djambi\GameDispositions;

class GameDisposition2mini extends BaseGameDisposition {
  public function __construct() {
    $this->useMiniGridWith2Sides();
    $this->useStandardRuleset();
  }

  public static function getNbPlayers() {
    return 2;
  }
}
