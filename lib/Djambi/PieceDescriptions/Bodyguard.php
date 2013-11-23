<?php
namespace Djambi\PieceDescriptions;

use Djambi\PieceDescription;

class Bodyguard extends PieceDescription {
  public function __construct($num, $start_position) {
    $this->setHabilities(array(
      'protect_adjacent_pieces' => TRUE,
    ));
    parent::__construct('bodyguard', 'B', 'Bodyguard', $num, $start_position, 2);
  }
}