<?php
namespace Djambi\PieceDescriptions;

use Djambi\PieceDescription;

class Necromobile extends PieceDescription {
  public function __construct($num, $start_position) {
    $this->setHabilities(array(
      self::HABILITY_MOVE_DEAD_PEACES => TRUE,
    ));
    parent::__construct('necromobile', 'N', 'Necromobile', $num, $start_position, 5);
  }
}
