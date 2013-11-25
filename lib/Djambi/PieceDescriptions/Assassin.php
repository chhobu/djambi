<?php
namespace Djambi\PieceDescriptions;

use Djambi\PieceDescription;

class Assassin extends PieceDescription {
  public function __construct($num, $start_position) {
    $this->setHabilities(array(
      self::HABILITY_KILL_BY_ATTACK => TRUE,
      self::HABILITY_KILL_THRONE_LEADER => TRUE,
      self::HABILITY_SIGNATURE => TRUE,
      self::HABILITY_KILL_FORTIFIED_PIECES => TRUE,
    ));
    parent::__construct('assassin', 'A', 'Assassin', $num, $start_position, 2);
  }
}
