<?php
namespace Djambi\Stores;
use Djambi\Exceptions\GameOptionInvalidException;
use Djambi\GameOption;


class GameOptionsStore {
  private $stores;

  public function __construct() {
    $this->stores = array();
  }

  public function addInStore(GameOption $option) {
    $this->stores[$option->getType()][$option->getName()] = $option;
    return $this;
  }

  /**
   * Récupère une option à partir de son nom et de son type.
   *
   * @param string $name
   * @param string $type
   *
   * @throws \Djambi\Exceptions\GameOptionInvalidException
   * @return GameOption
   */
  public function retrieve($name, $type = NULL) {
    if (!is_null($type)) {
      if (isset($this->stores[$type][$name])) {
        return $this->stores[$type][$name];
      }
      else {
        throw new GameOptionInvalidException("Option (" . $type . ") not found : " . $name);
      }
    }
    else {
      foreach ($this->stores as $store) {
        if (isset($store[$name])) {
          return $store[$name];
        }
      }
      throw new GameOptionInvalidException("Option not found : " . $name);
    }
  }

  /**
   * @param $type
   *
   * @return GameOption[]
   */
  public function getStore($type) {
    if (isset($this->stores[$type])) {
      return $this->stores[$type];
    }
    return array();
  }

  /**
   * @return GameOption[]
   */
  public function getAllGameOptions() {
    $items = array();
    foreach ($this->stores as $store_items) {
      foreach ($store_items as $item) {
        $items[] = $item;
      }
    }
    return $items;
  }

  /**
   * Renvoie un tableau associatif contenant chaque valeur d'option.
   *
   * @param bool $only_not_default_values
   *   TRUE pour ne renvoyer que les valeurs différentes des options par défaut
   *
   * @return array
   */
  public function getAllGameOptionsValues($only_not_default_values = TRUE) {
    $values = array();
    foreach ($this->stores as $store_items) {
      /* @var GameOption $item */
      foreach ($store_items as $item) {
        if (!$only_not_default_values || ($only_not_default_values && $item->getValue() != $item->getDefault())) {
          $values[$item->getName()] = $item->getValue();
        }
      }
    }
    return $values;
  }

  /**
   * @return string
   */
  public function getStoresList() {
    return array_keys($this->stores);
  }

}
