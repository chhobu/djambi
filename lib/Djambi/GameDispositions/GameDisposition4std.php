<?php

namespace Djambi\GameDispositions;

use Djambi\Factories\GameDispositionsFactory;
use Djambi\GameDisposition;
use Djambi\Grids\StandardGridWith4Sides;

class GameDisposition4std extends GameDisposition {
  public function __construct(GameDispositionsFactory $factory, $settings = NULL) {
    $scheme = new StandardGridWith4Sides($settings);
    $this->setGrid($scheme)->setNbPlayers(4);
    $this->useStandardRuleset();
  }
}
