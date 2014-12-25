<?php
namespace Djambi\Grids;

class CustomGrid extends BaseGrid {

  protected function __construct() {}

  protected function prepareArrayConversion() {
    $this->addDependantObjects(array(
      'shape',
      'rows',
      'cols',
      'specialCells',
      'sides',
    ));
    return $this;
  }

  public static function fromArray(array $array, array $context = array()) {
    $grid = new static();
    $cols = isset($array['cols']) ? $array['cols'] : 9;
    $rows = isset($array['rows']) ? $array['rows'] : 9;
    if (!empty($array['specialCells'])) {
      foreach ($array['specialCells'] as $special_cell) {
        $grid->addSpecialCell($special_cell['type'], $special_cell['location']);
      }
    }
    if (isset($array['shape']) && $array['shape'] == self::SHAPE_HEXAGONAL) {
      $grid->useHexagonalGrid($rows, $cols);
    }
    else {
      $grid->useStandardGrid($rows, $cols);
    }
    if (!empty($array['sides'])) {
      foreach ($array['sides'] as $side) {
        $grid->addSide($side['pieces'], $side['start_position'], $side['start_status']);
      }
    }
    return $grid;
  }
}
