<?php
namespace Djambi\PieceDescriptions;

use Djambi\PieceDescription;

class Judge extends PieceDescription {
  public function __construct($num, $start_position) {
    $this->setHabilities(array(
      'block_adjacent_pieces' => TRUE,
    ));
    parent::__construct('judge', 'J', 'Judge', $num, $start_position, 2);
  }
}
