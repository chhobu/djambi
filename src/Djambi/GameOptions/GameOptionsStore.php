<?php
namespace Djambi\GameOptions;

use Djambi\Exceptions\GameOptionInvalidException;
use Djambi\Persistance\ArrayableInterface;
use Djambi\Persistance\PersistantDjambiTrait;

class GameOptionsStore implements ArrayableInterface {

  use PersistantDjambiTrait;

  /**
   * @var array
   */
  protected $stores;

  public function __construct() {
    $this->stores = array();
  }

  public static function fromArray(array $array, array $context = array()) {
    $args = $array;
    unset($args['className']);
    $game_options_store = call_user_func($array['className'] . '::fromArray', $args, $context);
    $context['gameStore'] = $game_options_store;
    foreach ($array['stores'] as $store) {
      foreach ($store as $rule) {
        $option_args = $rule;
        unset($rule['className']);
        $option = call_user_func_array($rule['className'] . '::fromArray', $option_args, $context);
        $game_options_store->addInStore($option);
      }
    }
    return $game_options_store;
  }

  protected function prepareArrayConversion() {
    $this->addPersistantProperties(array('stores'));
    return $this;
  }

  public function addInStore(BaseGameOption $option) {
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
   * @return BaseGameOption
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
   * @return BaseGameOption[]
   */
  public function getStore($type) {
    if (isset($this->stores[$type])) {
      return $this->stores[$type];
    }
    return array();
  }

  /**
   * @return BaseGameOption[]
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
      /* @var BaseGameOption $item */
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
