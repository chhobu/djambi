<?php

namespace Djambi\Grids;

use Djambi\Gameplay\Faction;

class StandardGridWith4Sides extends BaseGrid {
  public function __construct() {
    $this->useStandardGrid(9, 9);
    $this->useStandardPieces();
    $this->addSide($this->pieceScheme, array('x' => 1, 'y' => 9),
      isset($settings['start_statuses'][1]) ? $settings['start_statuses'][1] : Faction::STATUS_READY);
    $this->addSide($this->pieceScheme, array('x' => 9, 'y' => 9),
      isset($settings['start_statuses'][2]) ? $settings['start_statuses'][2] : Faction::STATUS_READY);
    $this->addSide($this->pieceScheme, array('x' => 9, 'y' => 1),
      isset($settings['start_statuses'][3]) ? $settings['start_statuses'][3] : Faction::STATUS_READY);
    $this->addSide($this->pieceScheme, array('x' => 1, 'y' => 1),
      isset($settings['start_statuses'][4]) ? $settings['start_statuses'][4] : Faction::STATUS_READY);
  }
}
