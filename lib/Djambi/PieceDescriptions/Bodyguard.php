<?php
namespace Djambi\PieceDescriptions;

use Djambi\PieceDescription;

class Bodyguard extends PieceDescription {
  public function __construct($num, $start_position) {
    $this->setHabilities(array(
      self::HABILITY_PROTECT_BY_PROXIMITY => TRUE,
      self::HABILITY_KILL_BY_ATTACK => TRUE,
      self::HABILITY_LIMITED_MOVE => 2,
    ));
    parent::__construct('bodyguard', 'B', 'Bodyguard', $num, $start_position, 2);
  }
}
