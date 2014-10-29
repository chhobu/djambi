<?php
/**
 * Created by PhpStorm.
 * User: buchho
 * Date: 28/10/14
 * Time: 00:50
 */

namespace Djambi\GameManagers;


use Djambi\IA\DummyIA;
use Djambi\Players\ComputerPlayer;

class TrainingGameManager extends BaseGameManager {

  public function isCancelActionAllowed() {
    return TRUE;
  }

  protected function addDefaultPlayers(&$players) {
    parent::addDefaultPlayers($players);
    for ($i = count($players) + 1; $i <= $this->disposition->getNbPlayers(); $i++) {
      $computer = new ComputerPlayer();
      $computer->useIa($this->getDefaultComputerIa());
      $players[] = $computer;
    }
    return $this;
  }

  protected function  getDefaultComputerIa() {
    return DummyIA::getClass();
  }

}
