<?php
namespace Djambi\PieceDescriptions;

class Reporter extends BasePieceDescription {
  public function __construct($num, $start_position) {
    $this->setHabilities(array(
      self::HABILITY_KILL_BY_PROXIMITY => TRUE,
      self::HABILITY_KILL_THRONE_LEADER => TRUE,
    ));
    $this->describePiece('reporter', 'R', 'Reporter', $num, $start_position, 3);
  }
}
