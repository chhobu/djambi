<?php
namespace Djambi\Interfaces;

use Djambi\Faction;

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
  public static function loadPlayer(array $data);

  /**
   * @return PlayerInterface
   */
  public function saveToArray();

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
}
