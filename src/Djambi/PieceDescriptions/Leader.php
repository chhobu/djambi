<?php
namespace Djambi\PieceDescriptions;

use Djambi\PieceDescriptions\Habilities\HabilityAccessThrone;
use Djambi\PieceDescriptions\Habilities\HabilityKillByAttack;
use Djambi\PieceDescriptions\Habilities\HabilityKillRuler;
use Djambi\PieceDescriptions\Habilities\RestrictionMustLive;
use Djambi\Strings\Glossary;
use Djambi\Strings\GlossaryTerm;

class Leader extends BasePieceDescription implements RestrictionMustLive, HabilityKillByAttack, HabilityKillRuler, HabilityAccessThrone {
  const PIECE_VALUE = 10;

  public function __construct($start_position) {
    $this->describePiece('leader', 'L', new GlossaryTerm(Glossary::PIECE_LEADER), $start_position);
  }
}
