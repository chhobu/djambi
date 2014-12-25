<?php

namespace Djambi\GameDispositions;


use Djambi\Interfaces\ExposedElementInterface;
use Djambi\Gameplay\Faction;
use Djambi\Strings\Glossary;
use Djambi\Strings\GlossaryTerm;

class GameDisposition2std extends BaseGameDisposition implements ExposedElementInterface {
  public function __construct() {
    $this->useStandardGrid()
      ->useStandardRuleset();
    $this->getGrid()
      ->alterSide(2, array('start_status' => Faction::STATUS_VASSALIZED, 'control' => 't1'))
      ->alterSide(4, array('start_status' => Faction::STATUS_VASSALIZED, 'control' => 't3'));
  }

  public static function getDescription() {
    return new GlossaryTerm(Glossary::DISPOSITION_2STD_DESCRIPTION);
  }

  public static function getNbPlayers() {
    return 2;
  }

}
