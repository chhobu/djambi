<?php
namespace Djambi\PieceDescriptions;

use Djambi\PieceDescription;

class Fanatic extends PieceDescription {
  public function __construct($num, $start_position) {
    $this->setHabilities(array(
      'kill_by_proximity' => TRUE,
      'kill_throne_leader' => TRUE,
      'limited_move' => 2,
      'kamikaze' => TRUE,
    ));
    parent::__construct('fanatic', 'F', 'Fanatic', $num, $start_position, 1);
  }
}