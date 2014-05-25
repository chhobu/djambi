<?php
/**
 * @file
 * Gestion des utilisateurs d'une partie de Djambi
 */

namespace Djambi\Players;

use Djambi\Exceptions\PlayerNotFoundException;
use Djambi\Exceptions\PlayerInvalidException;
use Djambi\Gameplay\Faction;
use Djambi\Persistance\PersistantDjambiObject;

/**
 * Class DjambiPlayer
 */
abstract class BasePlayer extends PersistantDjambiObject implements PlayerInterface {
  const TYPE_HUMAN = 'human';
  const TYPE_COMPUTER = 'computer';
  const CLASS_NICKNAME = '';

  /* @var string $type */
  protected $type = self::TYPE_HUMAN;
  /* @var string $className */
  protected $className;
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
    return $this->type == self::TYPE_HUMAN;
  }

  protected function setType($type) {
    switch ($type) {
      case (self::TYPE_COMPUTER):
      case (self::TYPE_HUMAN):
        $this->type = $type;
        break;

      default:
        throw new PlayerInvalidException("Invalid player type : " . $type);
    }
  }

  /**
   * Récupère un objet de type DjambiPlayer à partir d'un tableau de données.
   *
   * @param array $data
   *   Tableau de données
   * @param array $context
   *
   * @throws \Djambi\Exceptions\PlayerNotFoundException
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
    return parent::prepareArrayConversion();
  }

  public function isPlayingFaction(Faction $faction) {
    if (!is_null($faction->getPlayer()) && $faction->getPlayer()->getClassName() == $this->getClassName()
    && $faction->getPlayer()->getId() == $this->getId()) {
      return TRUE;
    }
    return FALSE;
  }

}
