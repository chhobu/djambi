<?php
namespace Djambi\PieceDescriptions;

use Djambi\PieceDescriptions\Habilities\HabilityKillByAttack;
use Djambi\PieceDescriptions\Habilities\HabilityKillRuler;
use Djambi\PieceDescriptions\Habilities\RestrictionSignature;
use Djambi\Strings\Glossary;
use Djambi\Strings\GlossaryTerm;

class Assassin extends BasePieceDescription implements HabilityKillByAttack, RestrictionSignature, HabilityKillRuler  {
  const PIECE_VALUE = 2;

  public function __construct($start_position) {
    $this->describePiece('assassin', 'A', new GlossaryTerm(Glossary::PIECE_ASSASSIN), $start_position);
  }
}
