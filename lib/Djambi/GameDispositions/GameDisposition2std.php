<?php

namespace Djambi\GameDispositions;
use Djambi\Faction;
use Djambi\Factories\GameDispositionsFactory;
use Djambi\GameDisposition;
use Djambi\Grids\StandardGridWith4Sides;

/**
 * Class DjambiGameDisposition2std
 */
class GameDisposition2std extends GameDisposition {
  public function __construct(GameDispositionsFactory $factory, $settings = NULL) {
    $settings['start_statuses'] = array(
      2 => Faction::STATUS_VASSALIZED,
      4 => Faction::STATUS_VASSALIZED,
    );
    $scheme = new StandardGridWith4Sides($settings);
    $this->setGrid($scheme)->setNbPlayers(2);
    $this->useStandardRuleset();
  }
}
