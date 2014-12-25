<?php
/**
 * @file
 * Gestion des utilisateurs d'une partie de Djambi
 */

namespace Djambi\Players;

use Djambi\Players\Exceptions\PlayerNotFoundException;
use Djambi\GameManagers\PlayableGameInterface;
use Djambi\Gameplay\Faction;
use Djambi\Persistance\ArrayableInterface;
use Djambi\Persistance\PersistantDjambiTrait;

/**
 * Class DjambiPlayer
 */
abstract class BasePlayer implements PlayerInterface, ArrayableInterface {

  use PersistantDjambiTrait;

  const CLASS_NICKNAME = '';

  /* @var string $id */
  protected $id;
  /* @var Faction $faction */
  protected $faction;

  protected function __construct($id = NULL) {
    if (is_null($id)) {
      $id = static::generateId();
    }
    $this->id = $id;
  }

  public function setFaction(Faction $faction) {
    $this->faction = $faction;
    return $this;
  }

  public function getFaction() {
    return $this->faction;
  }

  public function removeFaction() {
    $this->faction = NULL;
    return $this;
  }

  public function displayName() {
    return $this->getId();
  }

  public function getId() {
    return $this->id;
  }

  protected function generateId() {
    $prefix = static::CLASS_NICKNAME;
    return uniqid($prefix);
  }

  public function isHuman() {
    return $this instanceof HumanPlayerInterface;
  }

  /**
   * Récupère un objet de type DjambiPlayer à partir d'un tableau de données.
   *
   * @param array $data
   *   Tableau de données
   * @param array $context
   *
   * @throws \Djambi\Players\Exceptions\PlayerNotFoundException
   * @return BasePlayer
   *   Joueur de Djambi
   */
  public static function fromArray(array $data, array $context = array()) {
    if (empty($data['id'])) {
      throw new PlayerNotFoundException("Missing id entry for loading player.");
    }
    $player = new static($data['id']);
    return $player;
  }

  protected function prepareArrayConversion() {
    $this->addPersistantProperties(array('id'));
    return $this;
  }

  public function isPlayingFaction(Faction $faction) {
    if (!is_null($faction->getPlayer()) && (get_class($faction->getPlayer()) == get_class($this))
      && $faction->getPlayer()->getId() == $this->getId()
    ) {
      return TRUE;
    }
    return FALSE;
  }

  public function isPlayingGame(PlayableGameInterface $game) {
    foreach ($game->getBattlefield()->getFactions() as $faction) {
      if ($this->isPlayingFaction($faction)) {
        return TRUE;
      }
    }
    return FALSE;
  }

}
