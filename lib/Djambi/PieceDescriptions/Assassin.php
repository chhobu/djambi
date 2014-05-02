<?php
namespace Djambi\PieceDescriptions;

use Djambi\Strings\Glossary;
use Djambi\Strings\GlossaryTerm;

class Assassin extends BasePieceDescription {
  public function __construct($num, $start_position) {
    $this->setHabilities(array(
      self::HABILITY_KILL_BY_ATTACK => TRUE,
      self::HABILITY_KILL_THRONE_LEADER => TRUE,
      self::HABILITY_SIGNATURE => TRUE,
    ));
    $this->describePiece('assassin', 'A', new GlossaryTerm(Glossary::ASSASSIN), $num, $start_position, 2);
  }
}
