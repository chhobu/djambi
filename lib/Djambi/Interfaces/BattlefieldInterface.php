<?php

namespace Djambi\Interfaces;


use Djambi\GameDisposition;

interface BattlefieldInterface {
  public static function createNewBattlefield(GameManagerInterface $gm, $players, $id, $mode, GameDisposition $disposition);
  public static function loadBattlefield(GameManagerInterface $gm, GameDisposition $disposition, $data);
  public function toArray();
  public function getStatus();
  public function changeTurn();
  public function prepareNewTurn();
}
