<?php

namespace Djambi\Grids;
use Djambi\Grid;

class StandardGridWith4Sides extends Grid {
  public function __construct($settings = NULL) {
    $this->useStandardGrid(9, 9);
    $this->useStandardPieces();
    $this->addSide(array('x' => 1, 'y' => 9),
      isset($settings['start_statuses'][1]) ? $settings['start_statuses'][1] : KW_DJAMBI_FACTION_STATUS_READY);
    $this->addSide(array('x' => 9, 'y' => 9),
      isset($settings['start_statuses'][2]) ? $settings['start_statuses'][2] : KW_DJAMBI_FACTION_STATUS_READY);
    $this->addSide(array('x' => 9, 'y' => 1),
      isset($settings['start_statuses'][3]) ? $settings['start_statuses'][3] : KW_DJAMBI_FACTION_STATUS_READY);
    $this->addSide(array('x' => 1, 'y' => 1),
      isset($settings['start_statuses'][4]) ? $settings['start_statuses'][4] : KW_DJAMBI_FACTION_STATUS_READY);
  }
}