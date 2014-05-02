<?php
namespace Djambi\PieceDescriptions;

use Djambi\Strings\Glossary;
use Djambi\Strings\GlossaryTerm;

class Necromobile extends BasePieceDescription {
  public function __construct($num, $start_position) {
    $this->setHabilities(array(
      self::HABILITY_MOVE_DEAD_PEACES => TRUE,
    ));
    $this->describePiece('necromobile', 'N', new GlossaryTerm(Glossary::NECROMOBILE), $num, $start_position, 5);
  }
}
