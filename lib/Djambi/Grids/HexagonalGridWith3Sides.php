<?php

namespace Djambi\Grids;
use Djambi\Grid;


class HexagonalGridWith3Sides extends Grid {
  public function __construct($settings = NULL) {
    $this->useHexagonalGrid(9, 9);
    $this->useStandardPieces();
    $this->addSpecialCell('disabled', array('x' => 1, 'y' => 1));
    $this->addSpecialCell('disabled', array('x' => 2, 'y' => 1));
    $this->addSpecialCell('disabled', array('x' => 8, 'y' => 1));
    $this->addSpecialCell('disabled', array('x' => 9, 'y' => 1));
    $this->addSpecialCell('disabled', array('x' => 1, 'y' => 2));
    $this->addSpecialCell('disabled', array('x' => 8, 'y' => 2));
    $this->addSpecialCell('disabled', array('x' => 9, 'y' => 2));
    $this->addSpecialCell('disabled', array('x' => 1, 'y' => 3));
    $this->addSpecialCell('disabled', array('x' => 9, 'y' => 3));
    $this->addSpecialCell('disabled', array('x' => 9, 'y' => 4));
    $this->addSpecialCell('disabled', array('x' => 9, 'y' => 6));
    $this->addSpecialCell('disabled', array('x' => 1, 'y' => 7));
    $this->addSpecialCell('disabled', array('x' => 9, 'y' => 7));
    $this->addSpecialCell('disabled', array('x' => 1, 'y' => 8));
    $this->addSpecialCell('disabled', array('x' => 8, 'y' => 8));
    $this->addSpecialCell('disabled', array('x' => 9, 'y' => 8));
    $this->addSpecialCell('disabled', array('x' => 1, 'y' => 9));
    $this->addSpecialCell('disabled', array('x' => 2, 'y' => 9));
    $this->addSpecialCell('disabled', array('x' => 8, 'y' => 9));
    $this->addSpecialCell('disabled', array('x' => 9, 'y' => 9));
    $this->addSide(array('x' => 1, 'y' => 5));
    $this->addSide(array('x' => 7, 'y' => 9));
    $this->addSide(array('x' => 7, 'y' => 1));
  }
}
