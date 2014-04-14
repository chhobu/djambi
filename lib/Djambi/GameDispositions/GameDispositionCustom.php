<?php

namespace Djambi\GameDispositions;


use Djambi\Faction;
use Djambi\Factories\GameDispositionsFactory;
use Djambi\Grid;

class GameDispositionCustom extends BaseGameDisposition {
  public function __construct(GameDispositionsFactory $factory, $settings) {
    $grid = new Grid($settings);
    $this->setGrid($grid);
    $nb_players = 0;
    foreach ($grid->getSides() as $side) {
      if ($side['start_status'] != Faction::STATUS_VASSALIZED) {
        $nb_players++;
      }
    }
    $this->setNbPlayers($nb_players);
    $this->useStandardRuleset();
  }
}
