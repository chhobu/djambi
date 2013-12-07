<?php

namespace Djambi\GameDispositions;

use Djambi\Faction;
use Djambi\Factories\GameDispositionsFactory;
use Djambi\GameDisposition;

class GameDisposition2std extends GameDisposition {
  public function __construct(GameDispositionsFactory $factory, $settings = NULL) {
    $settings['start_statuses'] = array(
      2 => Faction::STATUS_VASSALIZED,
      4 => Faction::STATUS_VASSALIZED,
    );
    $this->useStandardGrid($settings)->setNbPlayers(2)->useStandardRuleset();
  }
}
