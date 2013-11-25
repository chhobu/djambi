<?php
namespace Djambi\PieceDescriptions;

use Djambi\PieceDescription;

class Leader extends PieceDescription {
  public function __construct($num, $start_position) {
    $this->setHabilities(array(
      self::HABILITY_MUST_LIVE => TRUE,
      self::HABILITY_KILL_BY_ATTACK => TRUE,
      self::HABILITY_KILL_THRONE_LEADER => TRUE,
      self::HABILITY_ACCESS_THRONE => TRUE,
      self::HABILITY_UNCONVERTIBLE => TRUE,
    ));
    parent::__construct('leader', 'L', 'Leader', $num, $start_position, 10);
  }
}
