<?php
namespace Djambi\PieceDescriptions;

use Djambi\PieceDescription;

class Fanatic extends PieceDescription {
  public function __construct($num, $start_position) {
    $this->setHabilities(array(
      self::HABILITY_KILL_FORTIFIED_PIECES => TRUE,
      self::HABILITY_KILL_THRONE_LEADER => TRUE,
      self::HABILITY_LIMITED_MOVE => 2,
      self::HABILITY_KAMIKAZE => TRUE,
    ));
    parent::__construct('fanatic', 'F', 'Fanatic', $num, $start_position, 1);
  }
}