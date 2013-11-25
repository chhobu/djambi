<?php
namespace Djambi\PieceDescriptions;

use Djambi\PieceDescription;

class Propagandist extends PieceDescription {
  public function __construct($num, $start_position) {
    $this->setHabilities(array(
      self::HABILITY_CONVERT_PIECES => TRUE,
      self::HABILITY_UNCONVERTIBLE => TRUE,
      self::HABILITY_SIGNATURE => TRUE,
    ));
    parent::__construct('propagandist', 'P', 'Propagandist', $num, $start_position, 3);
  }
}
