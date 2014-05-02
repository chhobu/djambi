<?php
namespace Djambi\PieceDescriptions;

use Djambi\Strings\Glossary;
use Djambi\Strings\GlossaryTerm;

class Diplomat extends BasePieceDescription {
  public function __construct($num, $start_position) {
    $this->setHabilities(array(
      self::HABILITITY_MOVE_LIVING_PIECES => TRUE,
    ));
    $this->describePiece('diplomate', 'D', new GlossaryTerm(Glossary::DIPLOMATE), $num, $start_position, 2);
  }
}
