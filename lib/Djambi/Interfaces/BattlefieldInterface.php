<?php

namespace Djambi\Interfaces;


use Djambi\Faction;
use Djambi\Player;

interface BattlefieldInterface {

  /**
   * @return GameManagerInterface
   */
  public function getGameManager();

  /**
   * @param GameManagerInterface $gm
   * @param Player[] $players
   *
   * @return BattlefieldInterface
   */
  public static function createNewBattlefield(GameManagerInterface $gm, $players);

  /**
   * @param GameManagerInterface $gm
   * @param array $data
   *
   * @return BattlefieldInterface
   */
  public static function loadBattlefield(GameManagerInterface $gm, $data);

  /**
   * @return array
   */
  public function toArray();

  /**
   * @return BattlefieldInterface
   */
  public function changeTurn();

  /**
   * @return BattlefieldInterface
   */
  public function prepareTurn();

  /**
   * @return Faction[]
   */
  public function getFactions();

  /**
   * @return Faction
   */
  public function getPlayingFaction();

}
