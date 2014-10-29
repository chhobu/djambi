<?php

namespace Djambi\GameDispositions;

use Djambi\Interfaces\ExposedElementInterface;
use Djambi\Strings\Glossary;
use Djambi\Strings\GlossaryTerm;

class GameDisposition3hex extends BaseGameDisposition implements ExposedElementInterface {
  public function __construct() {
    $this->useHexagonalGrid()->setNbPlayers(3)->useStandardRuleset();
  }

  public static function getDescription() {
    return new GlossaryTerm(Glossary::DISPOSITION_3HEX_DESCRIPTION);
  }

}
