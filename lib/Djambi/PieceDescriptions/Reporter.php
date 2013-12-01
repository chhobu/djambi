<?php
namespace Djambi\PieceDescriptions;

use Djambi\PieceDescription;

class Reporter extends PieceDescription {
  public function __construct($num, $start_position) {
    $this->setHabilities(array(
      self::HABILITY_KILL_BY_PROXIMITY => TRUE,
      self::HABILITY_KILL_THRONE_LEADER => TRUE,
      self::HABILITY_KILL_FORTIFIED_PIECES => TRUE,
    ));
    parent::__construct('reporter', 'R', 'Reporter', $num, $start_position, 3);
  }
}
