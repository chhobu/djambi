<?php
namespace Djambi\PieceDescriptions;

use Djambi\PieceDescriptions\Habilities\HabilityMoveDeadPieces;
use Djambi\Strings\Glossary;
use Djambi\Strings\GlossaryTerm;

class Necromobile extends BasePieceDescription implements HabilityMoveDeadPieces {
  const PIECE_VALUE = 5;

  public function __construct($start_position) {
    $this->describePiece('necromobile', 'N', new GlossaryTerm(Glossary::PIECE_NECROMOBILE), $start_position);
  }
}
