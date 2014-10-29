<?php

namespace Djambi\GameDispositions;

use Djambi\Gameplay\Faction;
use Djambi\Grids\CustomGrid;

class GameDispositionCustom extends BaseGameDisposition {
  public function __construct(GameDispositionsFactory $factory, $settings) {
    $this->setGrid(CustomGrid::fromArray($settings));
    $nb_players = 0;
    foreach ($this->getGrid()->getSides() as $side) {
      if ($side['start_status'] != Faction::STATUS_VASSALIZED) {
        $nb_players++;
      }
    }
    $this->setNbPlayers($nb_players);
    $this->useStandardRuleset();
  }

}
