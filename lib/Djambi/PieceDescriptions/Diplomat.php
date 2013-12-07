<?php
namespace Djambi\PieceDescriptions;

use Djambi\PieceDescription;

class Diplomat extends PieceDescription {
  public function __construct($num, $start_position) {
    $this->setHabilities(array(
      self::HABILITITY_MOVE_LIVING_PIECES => TRUE,
    ));
    parent::__construct('diplomate', 'D', 'Diplomat', $num, $start_position, 2);
  }
}
