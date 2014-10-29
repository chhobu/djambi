<?php
/**
 * Created by PhpStorm.
 * User: buchho
 * Date: 28/10/14
 * Time: 00:49
 */

namespace Djambi\GameManagers;


use Djambi\Interfaces\ExposedElementInterface;
use Djambi\Strings\Glossary;
use Djambi\Strings\GlossaryTerm;

class SandboxGameManager extends BaseGameManager implements ExposedElementInterface {

  public static function getDescription() {
    return new GlossaryTerm(Glossary::MODE_SANDBOX_DESCRIPTION);
  }

  public function isCancelActionAllowed() {
    return TRUE;
  }

  protected function addDefaultPlayers(&$players) {
    parent::addDefaultPlayers($players);
    $default_player = current($players);
    for ($i = count($players) + 1; $i <= $this->disposition->getNbPlayers(); $i++) {
      $players[] = $default_player;
    }
    return $this;
  }


}