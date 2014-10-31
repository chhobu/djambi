<?php
namespace Djambi\Grids;

class CustomGrid extends BaseGrid {

  protected function __construct() {}

  protected function prepareArrayConversion() {
    $this->addDependantObjects(array(
      'pieceScheme',
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
    if (!isset($array['pieceScheme'])) {
      $grid->useStandardPieces();
    }
    else {
      foreach ($array['pieceScheme'] as $piece_data) {
        $piece = call_user_func($piece_data['className'] . '::fromArray', $piece_data);
        $grid->addCommonPiece($piece);
      }
    }
    if (!empty($array['sides'])) {
      foreach ($array['sides'] as $side) {
        $pieces = array();
        if (!empty($side['specific_pieces'])) {
          foreach ($side['specific_pieces'] as $data) {
            $pieces[] = call_user_func($data['className'] . '::fromArray', $data);
          }
        }
        $grid->addSide($side['start_position'], $side['start_status'], $pieces);
      }
    }
    return $grid;
  }
}
