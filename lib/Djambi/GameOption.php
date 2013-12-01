<?php
/**
 * @file
 * Gère les différentes variantes de règles et options de jeu.
 */

namespace Djambi;
use Djambi\Exceptions\GameOptionInvalidException;
use Djambi\Stores\GameOptionsStore;

class GameOption {
  /* @var string */
  private $name;
  /* @var string */
  private $title;
  /* @var string */
  private $widget;
  /* @var string */
  private $type;
  /* @var array */
  private $choices;
  /* @var bool */
  private $configurable = TRUE;
  /* @var array */
  private $modes;
  /* @var mixed */
  private $default;
  /* @var mixed */
  private $value;
  /* @var string */
  private $cssClass;
  /* @var string */
  private $genericLabel;
  /* @var array */
  private $genericLabelArgs;

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

  public function setModes($modes) {
    $this->modes = $modes;
    return $this;
  }

  public function getModes() {
    return $this->modes;
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

}
