<?php

namespace Djambi\Gameplay;


use Djambi\GameManagers\GameManagerInterface;
use Djambi\Players\PlayerInterface;

interface BattlefieldInterface {

  /**
   * @return GameManagerInterface
   */
  public function getGameManager();

  /**
   * @param GameManagerInterface $game
   * @param PlayerInterface[] $players
   *
   * @return BattlefieldInterface
   */
  public static function createNewBattlefield(GameManagerInterface $game, $players);

  /**
   * @return BattlefieldInterface
   */
  public function changeTurn();

  /**
   * @param bool $reset
   *
   * @return BattlefieldInterface
   */
  public function prepareTurn($reset = FALSE);

  /**
   * @return Faction[]
   */
  public function getFactions();

  /**
   * @param string $id
   *
   * @return Faction
   */
  public function findFactionById($id);

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
   * @return Turn
   */
  public function getCurrentTurn();

  /**
   * @return $this
   */
  public function cancelLastTurn();

  /**
   * @return array
   */
  public function getPastTurns();

  /**
   * @param string $name
   *
   * @return Cell
   */
  public function findCellByName($name);

  /**
   * @param string $piece_id
   *
   * @return Piece
   */
  public function findPieceById($piece_id);

}
