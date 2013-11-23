<?php
namespace Djambi\PieceDescriptions;

use Djambi\PieceDescription;

class Militant extends PieceDescription {
  public function __construct($num, $start_position) {
    $this->setHabilities(array(
      'limited_move' => 2,
      'kill_by_attack' => TRUE,
      'gain_promotion' => array(
        'threshold' => 3,
        'choices' => array(
          'DjambiPieceLeader',
          'DjambiPieceAssassin',
        ),
      ),
    ));
    parent::__construct('militant', 'M', 'Militant', $num, $start_position, 1);
  }
}
