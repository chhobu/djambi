<?php
namespace Djambi\PieceDescriptions;

use Djambi\PieceDescription;

class Reporter extends PieceDescription {
  public function __construct($num, $start_position) {
    $this->setHabilities(array(
      'kill_by_proximity' => TRUE,
      'kill_throne_leader' => TRUE,
    ));
    parent::__construct('reporter', 'R', 'Reporter', $num, $start_position, 3);
  }
}