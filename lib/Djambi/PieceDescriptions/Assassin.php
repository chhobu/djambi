<?php
namespace Djambi\PieceDescriptions;

use Djambi\PieceDescription;

class Assassin extends PieceDescription {
  public function __construct($num, $start_position) {
    $this->setHabilities(array(
      'kill_by_attack' => TRUE,
      'kill_throne_leader' => TRUE,
      'signature' => TRUE,
      'enter_fortress' => TRUE,
    ));
    parent::__construct('assassin', 'A', 'Assassin', $num, $start_position, 2);
  }
}
