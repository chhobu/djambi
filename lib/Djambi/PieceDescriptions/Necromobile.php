<?php
namespace Djambi\PieceDescriptions;

use Djambi\PieceDescription;

class Necromobile extends PieceDescription {
  public function __construct($num, $start_position) {
    $this->setHabilities(array(
      'move_dead_pieces' => TRUE,
    ));
    parent::__construct('necromobile', 'N', 'Necromobile', $num, $start_position, 5);
  }
}
