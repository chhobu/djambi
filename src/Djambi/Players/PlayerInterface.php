<?php
namespace Djambi\Players;


use Djambi\GameManagers\PlayableGameInterface;
use Djambi\Gameplay\Faction;

interface PlayerInterface {
  /**
   * @return string
   */
  public function getId();

  /**
   * @return string
   */
  public function displayName();

  /**
   * @return Faction
   */
  public function getFaction();

  /**
   * @return bool
   */
  public function isHuman();

  /**
   * @param array $data
   *
   * @return PlayerInterface
   */
  public static function fromArray(array $data);

  /**
   * @return PlayerInterface
   */
  public function toArray();

  /**
   * @param Faction $faction
   *
   * @return PlayerInterface
   */
  public function setFaction(Faction $faction);

  /**
   * @return PlayerInterface
   */
  public function removeFaction();

  /**
   * @param Faction $faction
   *
   * @return bool
   */
  public function isPlayingFaction(Faction $faction);

  /**
   * @param PlayableGameInterface $game

   *
*@retun bool
   */
  public function isPlayingGame(PlayableGameInterface $game);
}
