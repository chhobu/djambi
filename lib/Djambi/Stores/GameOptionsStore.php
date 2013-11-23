<?php
namespace Djambi\Stores;
use Djambi\Exceptions\BadGameOptionException;
use Djambi\GameOption;


class GameOptionsStore {
  protected $stores;

  public function __construct() {
    $this->stores = array();
  }

  public function addInStore(GameOption $option) {
    $this->stores[$option->getType()][$option->getName()] = $option;
    return $this;
  }

  /**
   * @param $type
   * @param $name
   *
   * @throws \Djambi\Exceptions\BadGameOptionException
   * @return GameOption
   */
  public function retrieve($type, $name) {
    if (isset($this->stores[$type][$name])) {
      return $this->stores[$type][$name];
    }
    else {
      throw new BadGameOptionException("Option (" . $type . ") not found : " . $name);
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
   * @return string
   */
  public function getStoresList() {
    return array_keys($this->stores);
  }

}
