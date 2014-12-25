<?php

namespace Djambi\GameDispositions;

use Djambi\Interfaces\ExposedElementInterface;
use Djambi\Strings\Glossary;
use Djambi\Strings\GlossaryTerm;

class GameDisposition4std extends BaseGameDisposition implements ExposedElementInterface {
  public function __construct() {
    $this->useStandardGrid()->useStandardRuleset();
  }

  public static function getDescription() {
    return new GlossaryTerm(Glossary::DISPOSITION_4STD_DESCRIPTION);
  }

  public static function getNbPlayers() {
    return 4;
  }
}
