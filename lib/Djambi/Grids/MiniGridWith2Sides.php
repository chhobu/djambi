<?php

namespace Djambi\Grids;
use Djambi\Grid;


class MiniGridWith2Sides extends Grid {
  public function __construct($settings = NULL) {
    $this->useStandardGrid(7, 7);
    $this->addPiece('\Djambi\PieceDescriptions\Leader', NULL, array('x' => 0, 'y' => 0));
    $this->addPiece('\Djambi\PieceDescriptions\Militant', 1, array('x' => 1, 'y' => 0));
    $this->addPiece('\Djambi\PieceDescriptions\Militant', 2, array('x' => -1, 'y' => 0));
    if (isset($settings['surprise_piece'])) {
      $surprise = $settings['surprise_piece'];
    }
    else {
      $surprises = array(
        '\Djambi\PieceDescriptions\Assassin',
        '\Djambi\PieceDescriptions\Reporter',
        '\Djambi\PieceDescriptions\Diplomate',
      );
      $surprise = $surprises[array_rand($surprises)];
      $settings['surprise_piece'] = $surprise;
    }
    $this->addPiece($surprise, NULL, array('x' => 0, 'y' => 1));
    $this->addSide(array('x' => 1, 'y' => 7));
    $this->addSide(array('x' => 7, 'y' => 1));
    $this->setSettings($settings);
  }
}
