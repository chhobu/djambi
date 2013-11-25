<?php
namespace Djambi\PieceDescriptions;

use Djambi\PieceDescription;

class Militant extends PieceDescription {
  public function __construct($num, $start_position) {
    $this->setHabilities(array(
      self::HABILITY_LIMITED_MOVE => 2,
      self::HABILITY_KILL_BY_ATTACK => TRUE,
    ));
    parent::__construct('militant', 'M', 'Militant', $num, $start_position, 1);
  }
}
