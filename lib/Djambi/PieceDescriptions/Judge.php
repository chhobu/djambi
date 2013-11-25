<?php
namespace Djambi\PieceDescriptions;

use Djambi\PieceDescription;

class Judge extends PieceDescription {
  public function __construct($num, $start_position) {
    $this->setHabilities(array(
      self::HABILIITY_BLOCK_BY_PROXIMITY => TRUE,
      self::HABILITY_PROTECT_BY_PROXIMITY => TRUE,
    ));
    parent::__construct('judge', 'J', 'Judge', $num, $start_position, 2);
  }
}
