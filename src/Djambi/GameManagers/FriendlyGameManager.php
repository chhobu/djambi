<?php
/**
 * Created by PhpStorm.
 * User: buchho
 * Date: 28/10/14
 * Time: 00:50
 */

namespace Djambi\GameManagers;


use Djambi\Enums\StatusEnum;
use Djambi\Exceptions\DisallowedActionException;
use Djambi\Gameplay\Faction;
use Djambi\Interfaces\ExposedElementInterface;
use Djambi\Players\HumanPlayer;
use Djambi\Players\PlayerInterface;
use Djambi\Strings\Glossary;
use Djambi\Strings\GlossaryTerm;

class FriendlyGameManager extends BaseGameManager implements MultiPlayerGameInterface, ExposedElementInterface, PersistantGameInterface {

  public static function getDescription() {
    return new GlossaryTerm(Glossary::MODE_FRIENDLY_DESCRIPTION);
  }

  public function isCancelActionAllowed() {
    return FALSE;
  }

  protected function addDefaultPlayers(&$players) {
    parent::addDefaultPlayers($players);
    for ($i = count($players) + 1; $i <= $this->disposition->getNbPlayers(); $i++) {
      $players[] = HumanPlayer::createEmptyHumanPlayer();
    }
    return $this;
  }


  public function addNewPlayer(PlayerInterface $player, Faction $target) {
    $nb_empty_factions = 0;
    $grid = $this->getBattlefield();
    if ($this->getStatus() != StatusEnum::STATUS_RECRUITING) {
      throw new DisallowedActionException("Cannot add new player after game begin.", 1);
    }
    foreach ($grid->getFactions() as $faction) {
      if ($faction->getId() != $target->getId() && $player->isPlayingFaction($faction)) {
        $faction->removePlayer();
      }
      if ($faction->getStatus() == Faction::STATUS_EMPTY_SLOT) {
        $nb_empty_factions++;
      }
    }
    if ($target->getStatus() == Faction::STATUS_EMPTY_SLOT) {
      $target->changePlayer($player);
      $nb_empty_factions -= 1;
    }
    else {
      throw new DisallowedActionException("Trying to add player in a non-empty slot.", 2);
    }
    if ($nb_empty_factions == 0) {
      $this->setStatus(StatusEnum::STATUS_PENDING)->play();
    }
    $this->propagateChanges();
    return $this;
  }

  public function ejectPlayer(PlayerInterface $player) {
    $grid = $this->getBattlefield();
    if ($this->getStatus() == StatusEnum::STATUS_RECRUITING) {
      $nb_playing_factions = 0;
      foreach ($grid->getFactions() as $faction) {
        if ($player->isPlayingFaction($faction)) {
          $faction->removePlayer();
        }
        if ($faction->getStatus() == Faction::STATUS_READY) {
          $nb_playing_factions++;
        }
      }
      if ($nb_playing_factions == 0) {
        $this->delete();
      }
      else {
        $this->propagateChanges();
      }
    }
    else {
      throw new DisallowedActionException("Cannot remove player after game begin.", 1);
    }
    return $this;
  }

  public function propagateChanges() {
    parent::propagateChanges();
    $this->save();
  }

  public static function load($id) {
    // TODO: Implement load() method.
  }

  public function save() {
    // TODO: Implement save() method.
  }

  public function reload() {
    // TODO: Implement reload() method.
  }

  public function delete() {
    // TODO: Implement delete() method.
  }


} 