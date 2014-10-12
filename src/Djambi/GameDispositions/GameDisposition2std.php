<?php

namespace Djambi\GameDispositions;


use Djambi\Gameplay\Faction;

class GameDisposition2std extends BaseGameDisposition {
  public function __construct() {
    $this->useStandardGrid()
      ->setNbPlayers(2)
      ->useStandardRuleset();
    $this->getGrid()
      ->alterSide(2, array('start_status' => Faction::STATUS_VASSALIZED, 'control' => 't1'))
      ->alterSide(4, array('start_status' => Faction::STATUS_VASSALIZED, 'control' => 't3'));
  }
}