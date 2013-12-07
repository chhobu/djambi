<?php

namespace Djambi\Grids;

use Djambi\Grid;
use Djambi\PieceDescriptions\Assassin;
use Djambi\PieceDescriptions\Diplomat;
use Djambi\PieceDescriptions\Leader;
use Djambi\PieceDescriptions\Militant;
use Djambi\PieceDescriptions\Reporter;


class MiniGridWith2Sides extends Grid {
  public function __construct($settings = NULL) {
    $this->useStandardGrid(7, 7);
    $this->addCommonPiece(new Leader(NULL, array('x' => 0, 'y' => 0)));
    $this->addCommonPiece(new Militant(1, array('x' => 1, 'y' => 0)));
    $this->addCommonPiece(new Militant(2, array('x' => -1, 'y' => 0)));
    if (isset($settings['surprise_piece'])) {
      $surprise = $settings['surprise_piece'];
    }
    else {
      $surprise_location = array('x' => 0, 'y' => 1);
      $surprises = array(
        new Assassin(NULL, $surprise_location),
        new Reporter(NULL, $surprise_location),
        new Diplomat(NULL, $surprise_location),
      );
      $surprise = $surprises[array_rand($surprises)];
      $settings['surprise_piece'] = $surprise;
    }
    $this->addCommonPiece($surprise);
    $this->addSide(array('x' => 1, 'y' => 7));
    $this->addSide(array('x' => 7, 'y' => 1));
    $this->setSettings($settings);
  }
}
