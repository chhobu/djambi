<?php
namespace Djambi\PieceDescriptions;

class Diplomat extends BasePieceDescription {
  public function __construct($num, $start_position) {
    $this->setHabilities(array(
      self::HABILITITY_MOVE_LIVING_PIECES => TRUE,
    ));
    $this->describePiece('diplomate', 'D', 'Diplomat', $num, $start_position, 2);
  }
}
