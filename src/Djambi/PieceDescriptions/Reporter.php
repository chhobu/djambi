<?php
namespace Djambi\PieceDescriptions;

use Djambi\Strings\Glossary;
use Djambi\Strings\GlossaryTerm;

class Reporter extends BasePieceDescription {
  public function __construct($num, $start_position) {
    $this->setHabilities(array(
      self::HABILITY_KILL_BY_PROXIMITY => TRUE,
      self::HABILITY_KILL_THRONE_LEADER => TRUE,
    ));
    $this->describePiece('reporter', 'R', new GlossaryTerm(Glossary::PIECE_REPORTER), $num, $start_position, 3);
  }
}
