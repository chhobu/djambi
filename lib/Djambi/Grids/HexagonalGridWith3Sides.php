<?php

namespace Djambi\Grids;
use Djambi\Cell;
use Djambi\Grid;


class HexagonalGridWith3Sides extends Grid {
  public function __construct($settings = NULL) {
    $this->useHexagonalGrid(9, 9);
    $this->useStandardPieces();
    $this->addSpecialCell(Cell::TYPE_DISABLED, array('x' => 1, 'y' => 1));
    $this->addSpecialCell(Cell::TYPE_DISABLED, array('x' => 2, 'y' => 1));
    $this->addSpecialCell(Cell::TYPE_DISABLED, array('x' => 8, 'y' => 1));
    $this->addSpecialCell(Cell::TYPE_DISABLED, array('x' => 9, 'y' => 1));
    $this->addSpecialCell(Cell::TYPE_DISABLED, array('x' => 1, 'y' => 2));
    $this->addSpecialCell(Cell::TYPE_DISABLED, array('x' => 8, 'y' => 2));
    $this->addSpecialCell(Cell::TYPE_DISABLED, array('x' => 9, 'y' => 2));
    $this->addSpecialCell(Cell::TYPE_DISABLED, array('x' => 1, 'y' => 3));
    $this->addSpecialCell(Cell::TYPE_DISABLED, array('x' => 9, 'y' => 3));
    $this->addSpecialCell(Cell::TYPE_DISABLED, array('x' => 9, 'y' => 4));
    $this->addSpecialCell(Cell::TYPE_DISABLED, array('x' => 9, 'y' => 6));
    $this->addSpecialCell(Cell::TYPE_DISABLED, array('x' => 1, 'y' => 7));
    $this->addSpecialCell(Cell::TYPE_DISABLED, array('x' => 9, 'y' => 7));
    $this->addSpecialCell(Cell::TYPE_DISABLED, array('x' => 1, 'y' => 8));
    $this->addSpecialCell(Cell::TYPE_DISABLED, array('x' => 8, 'y' => 8));
    $this->addSpecialCell(Cell::TYPE_DISABLED, array('x' => 9, 'y' => 8));
    $this->addSpecialCell(Cell::TYPE_DISABLED, array('x' => 1, 'y' => 9));
    $this->addSpecialCell(Cell::TYPE_DISABLED, array('x' => 2, 'y' => 9));
    $this->addSpecialCell(Cell::TYPE_DISABLED, array('x' => 8, 'y' => 9));
    $this->addSpecialCell(Cell::TYPE_DISABLED, array('x' => 9, 'y' => 9));
    $this->addSide(array('x' => 1, 'y' => 5));
    $this->addSide(array('x' => 7, 'y' => 9));
    $this->addSide(array('x' => 7, 'y' => 1));
  }
}
