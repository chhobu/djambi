<?php

namespace Djambi\GameDispositions;

use Djambi\Factories\GameDispositionsFactory;
use Djambi\GameDisposition;
use Djambi\Grids\HexagonalGridWith3Sides;

class GameDisposition3hex extends GameDisposition {
  public function __construct(GameDispositionsFactory $factory, $settings = NULL) {
    $scheme = new HexagonalGridWith3Sides($settings);
    $this->setGrid($scheme)->setNbPlayers(3);
    $this->useStandardRuleset();
  }
}
