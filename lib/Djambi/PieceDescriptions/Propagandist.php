<?php
namespace Djambi\PieceDescriptions;

use Djambi\PieceDescription;

class Propagandist extends PieceDescription {
  public function __construct($num, $start_position) {
    $this->setHabilities(array(
      'convert_pieces' => TRUE,
      'cannot_defect' => TRUE,
      'signature' => TRUE,
    ));
    parent::__construct('propagandist', 'P', 'Propagandist', $num, $start_position, 3);
  }
}
