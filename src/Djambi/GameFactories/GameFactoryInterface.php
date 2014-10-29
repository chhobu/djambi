<?php
namespace Djambi\GameFactories;

use Djambi\GameDispositions\BaseGameDisposition;
use Djambi\GameManagers\PlayableGameInterface;
use Djambi\Players\PlayerInterface;

interface GameFactoryInterface {
  /**
   * Instancie une nouvelle partie.

   * @return PlayableGameInterface
   */
  public function createGameManager();

  public function addPlayer(PlayerInterface $player, $start_order = NULL);

  public function removePlayer(PlayerInterface $kickable_player);

  public function setDisposition(BaseGameDisposition $disposition);

  public function setBattlefieldClass($class_name);

  public function setId($id);
}
