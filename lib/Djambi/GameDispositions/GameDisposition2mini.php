<?php

namespace Djambi\GameDispositions;
use Djambi\Factories\GameDispositionsFactory;
use Djambi\GameDisposition;
use Djambi\Grids\MiniGridWith2Sides;

/**
 * Class DjambiGameDisposition2mini
 */
class GameDisposition2mini extends GameDisposition {
  public function __construct(GameDispositionsFactory $factory, $settings = NULL) {
    $scheme = new MiniGridWith2Sides($settings);
    $this->setGrid($scheme)->setNbPlayers(2);
    $this->useStandardRuleset();
  }
}
