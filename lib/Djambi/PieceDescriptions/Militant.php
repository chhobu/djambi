<?php
namespace Djambi\PieceDescriptions;

use Djambi\Strings\Glossary;
use Djambi\Strings\GlossaryTerm;

class Militant extends BasePieceDescription {
  public function __construct($num, $start_position) {
    $this->setHabilities(array(
      self::HABILITY_LIMITED_MOVE => 2,
      self::HABILITY_KILL_BY_ATTACK => TRUE,
    ));
    $this->describePiece('militant', 'M', new GlossaryTerm(Glossary::MILITANT), $num, $start_position, 1);
  }
}
