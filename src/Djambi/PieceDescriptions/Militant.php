<?php
namespace Djambi\PieceDescriptions;

use Djambi\PieceDescriptions\Habilities\HabilityKillByAttack;
use Djambi\PieceDescriptions\Habilities\RestrictionMove;
use Djambi\Strings\Glossary;
use Djambi\Strings\GlossaryTerm;

class Militant extends BasePieceDescription implements HabilityKillByAttack, RestrictionMove {
  public function __construct($start_position) {
    $this->describePiece('militant', 'M', new GlossaryTerm(Glossary::PIECE_MILITANT), $start_position);
  }

  public function getMaximumMove() {
    return 2;
  }

}
