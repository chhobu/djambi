<?php

namespace Djambi\Interfaces;


use Djambi\Cell;
use Djambi\Faction;
use Djambi\Move;
use Djambi\Player;

interface BattlefieldInterface {

  /**
   * @return GameManagerInterface
   */
  public function getGameManager();

  /**
   * @param GameManagerInterface $game
   * @param Player[] $players

   *
*@return BattlefieldInterface
   */
  public static function createNewBattlefield(GameManagerInterface $game, $players);

  /**
   * @param GameManagerInterface $game
   * @param array $data

   *
*@return BattlefieldInterface
   */
  public static function loadBattlefield(GameManagerInterface $game, $data);

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
   * @param string $id
   *
   * @return Faction
   */
  public function getFactionById($id);

  /**
   * @return Cell[]
   */
  public function getCells();

  /**
   * @param string
   *
   * @return array
   */
  public function getSpecialCells($type);

  /**
   * @return Faction
   */
  public function getPlayingFaction();

  /**
   * @return Move
   */
  public function getCurrentMove();

  /**
   * @param string $name
   *
   * @return Cell
   */
  public function findCellByName($name);

}
