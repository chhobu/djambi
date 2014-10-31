<?php
/**
 * @file
 * Gère les différentes variantes de règles et options de jeu.
 */

namespace Djambi\GameOptions;

use Djambi\Exceptions\GameOptionInvalidException;
use Djambi\Persistance\ArrayableInterface;
use Djambi\Persistance\PersistantDjambiTrait;

abstract class BaseGameOption implements ArrayableInterface {

  use PersistantDjambiTrait {
    toArray as toTraitArray;
  }

  /* @var string */
  protected $name;
  /* @var string */
  protected $title;
  /* @var string */
  protected $widget;
  /* @var string */
  protected $type;
  /* @var array */
  protected $choices;
  /* @var bool */
  protected $configurable = TRUE;
  /* @var mixed */
  protected $default;
  /* @var mixed */
  protected $value;
  /* @var string */
  protected $cssClass;
  /* @var string */
  protected $genericLabel;
  /* @var array */
  protected $genericLabelArgs;
  /* @var bool */
  protected $definedInConstructor = FALSE;
  /* @var array */
  protected $conditions;

  public static function fromArray(array $array, array $context = array()) {
    if (!empty($array['definedFromConstructor'])) {
      /** @var GameOptionsStore $game_store */
      $game_store = $context['gameStore'];
      $option = $game_store->retrieve($array['name']);
    }
    else {
      $facultative_properties = array('widget', 'value', 'default', 'choices');
      foreach ($facultative_properties as $property) {
        if (empty($array[$property])) {
          $array[$property] = NULL;
        }
      }
      $option = new static($context['gameStore'], $array['type'], $array['name'], $array['title'], $array['default'], $array['widget'], $array['choices']);
    }
    $option->setValue($array['value']);
    return $option;
  }

  protected function prepareArrayConversion() {
    if ($this->isDefinedInConstructor() && $this->getValue() != $this->getDefault()) {
      $this->addPersistantProperties(array('value', 'definedInContructor'));
    }
    else {
      $this->addPersistantProperties(array(
        'name',
        'title',
        'widget',
        'type',
        'choices',
        'default',
        'value',
        'definedInConstructor',
      ));
    }
    return $this;
  }

  public function toArray() {
    if ($this->isDefinedInConstructor() && $this->getValue() == $this->getDefault()) {
      return NULL;
    }
    return $this->toTraitArray();
  }

  /**
   * @param GameOptionsStore $store
   * @param string $type
   * @param string $name
   * @param string $title
   * @param mixed $default
   * @param string $widget
   * @param array $choices
   */
  protected function __construct(GameOptionsStore $store, $type, $name, $title, $default, $widget = NULL, $choices = NULL) {
    $this->setType($type);
    $this->setName($name);
    $this->setTitle($title);
    if (!empty($widget)) {
      $this->setWidget($widget);
      $this->setConfigurable(TRUE);
      if (!empty($choices)) {
        $this->setChoices($choices);
      }
    }
    else {
      $this->setConfigurable(FALSE);
    }
    $this->setDefault($default);
    $this->setValue($default);
    $this->setCssClass('game-option');
    $store->addInStore($this);
  }

  public function setName($name) {
    $this->name = $name;
    return $this;
  }

  public function getName() {
    return $this->name;
  }

  public function setWidget($widget) {
    $this->widget = $widget;
    return $this;
  }

  public function getWidget() {
    return $this->widget;
  }

  public function setChoices($choices) {
    if (is_array($choices)) {
      $this->choices = $choices;
    }
    return $this;
  }

  public function getChoices() {
    return $this->choices;
  }

  public function setType($type) {
    $this->type = $type;
    return $this;
  }

  public function getType() {
    return $this->type;
  }

  public function setTitle($title) {
    $this->title = $title;
    return $this;
  }

  public function getTitle() {
    return $this->title;
  }

  public function setDefault($default) {
    $this->default = $default;
    return $this;
  }

  public function getDefault() {
    return $this->default;
  }

  public function setConfigurable($configurable) {
    $this->configurable = $configurable;
    return $this;
  }

  public function isConfigurable() {
    return $this->configurable;
  }

  public function getValue() {
    return $this->value;
  }

  public function setValue($value) {
    if (is_array($this->choices) && !isset($this->choices[$value])) {
      throw new GameOptionInvalidException('Wrong choice in game option ' . get_class($this) . ' : ' . $value);
    }
    $this->value = $value;
    return $this;
  }

  public function getCssClass() {
    return $this->cssClass;
  }

  protected function setCssClass($class) {
    $this->cssClass = $class;
    return $this;
  }

  public function getGenericLabel() {
    return $this->genericLabel;
  }

  protected function setGenericLabel($label, $args = NULL) {
    $this->genericLabel = $label;
    if (!is_null($args)) {
      $this->genericLabelArgs = $args;
    }
    return $this;
  }

  public function getGenericLabelArgs() {
    return $this->genericLabelArgs;
  }

  public function isDefinedInConstructor() {
    return $this->definedInConstructor;
  }

  public function setDefinedInConstructor($bool) {
    $this->definedInConstructor = $bool;
    return $this;
  }

  public function getConditions() {
    return $this->conditions;
  }

  public function addCondition($expression, $value) {
    $this->conditions[] = array($expression, $value);
    return $this;
  }

}
