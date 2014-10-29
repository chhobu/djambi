<?php
/**
 * Created by PhpStorm.
 * User: buchho
 * Date: 28/10/14
 * Time: 00:51
 */

namespace Djambi\GameManagers;


use Djambi\Gameplay\Faction;
use Djambi\Players\PlayerInterface;

interface MultiPlayerGameInterface {

  /**
   * Exclut un joueur d'une partie.
   *
   * @param PlayerInterface $player
   *
   * @return $this
   */
  public function ejectPlayer(PlayerInterface $player);

  /**
   * Ajoute un joueur sur la partie en cours.
   *
   * @param PlayerInterface $player
   * @param Faction $faction
   *
   * @return $this
   */
  public function addNewPlayer(PlayerInterface $player, Faction $faction);

}