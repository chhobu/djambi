<?php
namespace Djambi\PieceDescriptions;

use Djambi\PieceDescriptions\Habilities\HabilityKillByProximity;
use Djambi\PieceDescriptions\Habilities\HabilityKillRuler;
use Djambi\Strings\Glossary;
use Djambi\Strings\GlossaryTerm;

class Reporter extends BasePieceDescription implements HabilityKillByProximity, HabilityKillRuler {

  const PIECE_VALUE = 3;

  public function __construct($start_position) {
    $this->describePiece('reporter', 'R', new GlossaryTerm(Glossary::PIECE_REPORTER), $start_position);
  }
}
