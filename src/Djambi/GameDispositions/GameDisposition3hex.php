<?php

namespace Djambi\GameDispositions;

use Djambi\Interfaces\ExposedElementInterface;
use Djambi\Strings\Glossary;
use Djambi\Strings\GlossaryTerm;

class GameDisposition3hex extends BaseGameDisposition implements ExposedElementInterface {
  public function __construct() {
    $this->useHexagonalGrid()->useStandardRuleset();
  }

  public static function getDescription() {
    return new GlossaryTerm(Glossary::DISPOSITION_3HEX_DESCRIPTION);
  }

  public static function getNbPlayers() {
    return 3;
  }

}
