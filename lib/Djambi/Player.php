<?php
/**
 * @file
 * Gestion des utilisateurs d'une partie de Djambi
 */

namespace Djambi;
use Djambi\Exceptions\PlayerNotFoundException;

/**
 * Class DjambiPlayer
 */
abstract class Player implements Interfaces\PlayerInterface {
  /* @var string $type */
  protected $type = 'human';
  /* @var string $className */
  protected $className;
  /* @var string $id */
  protected $id;
  /* @var string $name; */
  protected $name;
  /* @var Faction $faction */
  protected $faction;

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
    return $this->type == 'human';
  }

  public function getClassName() {
    return $this->className;
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
