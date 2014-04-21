<?php
namespace Djambi\PieceDescriptions;

class Necromobile extends BasePieceDescription {
  public function __construct($num, $start_position) {
    $this->setHabilities(array(
      self::HABILITY_MOVE_DEAD_PEACES => TRUE,
    ));
    $this->describePiece('necromobile', 'N', 'Necromobile', $num, $start_position, 5);
  }
}
