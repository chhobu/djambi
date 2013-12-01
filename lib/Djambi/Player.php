<?php
/**
 * @file
 * Gestion des utilisateurs d'une partie de Djambi
 */

namespace Djambi;
use Djambi\Exceptions\PlayerNotFoundException;
use Djambi\Exceptions\PlayerInvalidException;

/**
 * Class DjambiPlayer
 */
abstract class Player implements Interfaces\PlayerInterface {
  const TYPE_HUMAN = 'human';
  const TYPE_COMPUTER = 'computer';

  /* @var string $type */
  private $type = 'human';
  /* @var string $className */
  protected $className;
  /* @var string $id */
  private $id;
  /* @var string $name; */
  private $name;
  /* @var Faction $faction */
  private $faction;

  protected function __construct() {}

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

  public function getName() {
    if (empty($this->name)) {
      return $this->id;
    }
    else {
      return $this->name;
    }
  }

  protected function setName($name) {
    $this->name = $name;
  }

  public function displayName() {
    return $this->getName();
  }

  public function getId() {
    return $this->id;
  }

  public function setId($id, $prefix = '') {
    if (!is_null($id)) {
      $this->id = $id;
    }
    else {
      $this->id = uniqid($prefix);
    }
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

  public function getClassName() {
    return $this->className;
  }

  protected function setClassName() {
    $this->className = get_class($this);
  }

  /**
   * Récupère un objet de type DjambiPlayer à partir d'un tableau de données.
   *
   * @param array $data
   *   Tableau de données
   *
   * @throws Exceptions\PlayerNotFoundException
   * @return Player
   *   Joueur de Djambi
   */
  public static function loadPlayer(array $data) {
    if (empty($data['id'])) {
      throw new PlayerNotFoundException("Missing id entry for loading player.");
    }
    return new static($data['id']);
  }

  public function isPlayingFaction(Faction $faction) {
    if (!is_null($faction->getPlayer())) {
      if ($faction->getPlayer()->getClassName() == $this->getClassName()
        && $faction->getPlayer()->getId() == $this->getId()) {
        return TRUE;
      }
    }
    return FALSE;
  }

}
