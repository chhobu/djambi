<?php
namespace Djambi\PieceDescriptions;

use Djambi\PieceDescription;

class Leader extends PieceDescription {
  public function __construct($num, $start_position) {
    $this->setHabilities(array(
      'must_live' => TRUE,
      'kill_by_attack' => TRUE,
      'kill_throne_leader' => TRUE,
      'access_throne' => TRUE,
      'cannot_defect' => TRUE,
    ));
    parent::__construct('leader', 'L', 'Leader', $num, $start_position, 10);
  }
}
