<?php
namespace Djambi\PieceDescriptions;

class Militant extends BasePieceDescription {
  public function __construct($num, $start_position) {
    $this->setHabilities(array(
      self::HABILITY_LIMITED_MOVE => 2,
      self::HABILITY_KILL_BY_ATTACK => TRUE,
    ));
    $this->describePiece('militant', 'M', 'Militant', $num, $start_position, 1);
  }
}
