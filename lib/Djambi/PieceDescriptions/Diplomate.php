<?php
namespace Djambi\PieceDescriptions;

use Djambi\PieceDescription;

class Diplomate extends PieceDescription {
  public function __construct($num, $start_position) {
    $this->setHabilities(array(
      'move_living_pieces' => TRUE,
    ));
    parent::__construct('diplomate', 'D', 'Diplomate', $num, $start_position, 2);
  }
}
