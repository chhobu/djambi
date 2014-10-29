<?php

namespace Djambi\GameDispositions;

use Djambi\Interfaces\ExposedElementInterface;
use Djambi\Strings\Glossary;
use Djambi\Strings\GlossaryTerm;

class GameDisposition4std extends BaseGameDisposition implements ExposedElementInterface {
  public function __construct() {
    $this->useStandardGrid()->setNbPlayers(4)->useStandardRuleset();
  }

  public static function getDescription() {
    return new GlossaryTerm(Glossary::DISPOSITION_4STD_DESCRIPTION);
  }

}
