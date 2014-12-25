<?php
namespace Djambi\PieceDescriptions;

use Djambi\PieceDescriptions\Habilities\HabilityMoveLivingPieces;
use Djambi\Strings\Glossary;
use Djambi\Strings\GlossaryTerm;

class Diplomat extends BasePieceDescription implements HabilityMoveLivingPieces {
  const PIECE_VALUE = 2;

  public function __construct($start_position) {
    $this->describePiece('diplomate', 'D', new GlossaryTerm(Glossary::PIECE_DIPLOMATE), $start_position);
  }
}
