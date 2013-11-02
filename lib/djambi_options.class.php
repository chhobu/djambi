<?php
/**
 * @file
 * Gère les différentes variantes de règles et options de jeu.
 */

/**
 * Class DjambiGameOptionsStore
 */
class DjambiGameOptionsStore {
  protected $stores;

  /**
   * Constructeur d'un magasin de règles et d'options
   */
  public function __construct() {
    $this->stores = array();
  }

  /**
   * @param DjambiGameOption $option
   *
   * @return DjambiGameOptionsStore
   */
  public function addInStore(DjambiGameOption $option) {
    $this->stores[$option->getType()][$option->getName()] = $option;
    return $this;
  }

  /**
   * @param $type
   * @param $name
   *
   * @return DjambiGameOption
   */
  public function retrieve($type, $name) {
    if (isset($this->stores[$type][$name])) {
      return $this->stores[$type][$name];
    }
    return FALSE;
  }

  /**
   * @param $type
   *
   * @return DjambiGameOption[]
   */
  public function getStore($type) {
    if (isset($this->stores[$type])) {
      return $this->stores[$type];
    }
    return array();
  }

}

/**
 * Interface DjambiGameOptionInterface
 */
interface DjambiGameOptionInterface {

  static public function register(DjambiGameOptionsStore $store, $name, $title, $default, $widget = NULL, $choices = NULL);

  static public function retrieve(DjambiGameOptionsStore $store, $name);

  static public function listItems(DjambiGameOptionsStore $store);

}

/**
 * Class DjambiGameOption
 */
abstract class DjambiGameOption implements DjambiGameOptionInterface {
  protected $name;
  protected $title;
  protected $widget;
  protected $type;
  protected $choices;
  protected $configurable = TRUE;
  protected $modes;
  protected $default;
  protected $value;

  /**
   * @param DjambiGameOptionsStore $store
   * @param string $type
   * @param string $name
   * @param string $title
   * @param mixed $default
   * @param string $widget
   * @param array $choices
   */
  protected function __construct(DjambiGameOptionsStore $store, $type, $name, $title, $default, $widget = NULL, $choices = NULL) {
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
    $store->addInStore($this);
    return $this;
  }

  public function setName($name) {
    $this->name = $name;
    return $this;
  }

  /**
   * @return string
   */
  public function getName() {
    return $this->name;
  }

  public function setWidget($widget) {
    $this->widget = $widget;
    return $this;
  }

  /**
   * @return string
   */
  public function getWidget() {
    return $this->widget;
  }

  public function setChoices($choices) {
    if (is_array($choices)) {
      $this->choices = $choices;
    }
    return $this;
  }

  /**
   * @return array
   */
  public function getChoices() {
    return $this->choices;
  }

  public function setType($type) {
    $this->type = $type;
    return $this;
  }

  /**
   * @return string
   */
  public function getType() {
    return $this->type;
  }

  public function setModes($modes) {
    $this->modes = $modes;
    return $this;
  }

  /**
   * @return mixed
   */
  public function getModes() {
    return $this->modes;
  }

  public function setTitle($title) {
    $this->title = $title;
    return $this;
  }

  /**
   * @return mixed
   */
  public function getTitle() {
    return $this->title;
  }

  public function setDefault($default) {
    $this->default = $default;
    return $this;
  }

  /**
   * @return mixed
   */
  public function getDefault() {
    return $this->default;
  }

  public function setConfigurable($configurable) {
    $this->configurable = $configurable;
    return $this;
  }

  /**
   * @return bool
   */
  public function isConfigurable() {
    return $this->configurable;
  }

  /**
   * @return mixed
   */
  public function getValue() {
    return $this->value;
  }

  public function setValue($value) {
    if (is_array($this->choices) && !isset($this->choices[$value])) {
      throw new DjambiGameOptionException('Wrong choice in game option ' . get_class($this) . ' : ' . $value);
    }
    $this->value = $value;
    return $this;
  }
}

/**
 * Class DjambiGameOptionException
 */
class DjambiGameOptionException extends DjambiException {}

/**
 * Class DjambiGameOptionGameplayElement
 */
class DjambiGameOptionGameplayElement extends DjambiGameOption {
  /**
   * Implements register().
   */
  static public function register(DjambiGameOptionsStore $store, $name, $title, $default, $widget = NULL, $choices = NULL) {
    return new self($store, 'game_option', $name, $title, $default, $widget, $choices);
  }

  /**
   * Implements retrieve().
   */
  static public function retrieve(DjambiGameOptionsStore $store, $name) {
    return $store->retrieve('game_option', $name);
  }

  /**
   * Implements listItems().
   */
  static public function listItems(DjambiGameOptionsStore $store) {
    return $store->getStore('game_option');
  }
}

/**
 * Class DjambiGameOptionRuleVariant
 */
class DjambiGameOptionRuleVariant extends DjambiGameOption {
  /**
   * Implements register().
   */
  static public function register(DjambiGameOptionsStore $store, $name, $title, $default, $widget = NULL, $choices = NULL) {
    return new self($store, 'rule_variant', $name, $title, $default, $widget, $choices);
  }

  /**
   * Implements retrieve().
   */
  static public function retrieve(DjambiGameOptionsStore $store, $name) {
    return $store->retrieve('rule_variant', $name);
  }

  /**
   * Implements listItems().
   */
  static public function listItems(DjambiGameOptionsStore $store) {
    return $store->getStore('rule_variant');
  }
}

/**
 * Class DjambiGameOptionsFactoryStandardRuleset
 */
class DjambiGameOptionsStoreStandardRuleset extends DjambiGameOptionsStore {

  /**
   * Crée et enregistre un ensemble d'options et de règles standards standards.
   */
  public function __construct() {
    $option3 = DjambiGameOptionGameplayElement::register($this, 'allow_anonymous_players', 'OPTION3', 1, 'radios', array(
      1 => 'OPTION3_YES',
      0 => 'OPTION3_NO',
    ));
    $option3->setModes(array(KW_DJAMBI_MODE_FRIENDLY));

    DjambiGameOptionGameplayElement::register($this, 'allowed_skipped_turns_per_user', 'OPTION1', -1, 'select', array(
      0 => 'OPTION1_NEVER',
      1 => 'OPTION1_XTIME',
      2 => 'OPTION1_XTIME',
      3 => 'OPTION1_XTIME',
      4 => 'OPTION1_XTIME',
      5 => 'OPTION1_XTIME',
      10 => 'OPTION1_XTIME',
      -1 => 'OPTION1_ALWAYS',
    ));

    DjambiGameOptionGameplayElement::register($this, 'turns_before_draw_proposal', 'OPTION2', 10, 'select', array(
      -1 => 'OPTION2_NEVER',
      0 => 'OPTION2_ALWAYS',
      2 => 'OPTION2_XTURN',
      5 => 'OPTION2_XTURN',
      10 => 'OPTION2_XTURN',
      20 => 'OPTION2_XTURN',
    ));

    DjambiGameOptionRuleVariant::register($this, 'rule_surrounding', 'RULE1', 'throne_access', 'radios', array(
      'throne_access' => 'RULE1_THRONE_ACCESS',
      'strict' => 'RULE1_STRICT',
      'loose' => 'RULE1_LOOSE',
    ));

    DjambiGameOptionRuleVariant::register($this, 'rule_comeback', 'RULE2', 'allowed', 'radios', array(
      'never' => 'RULE2_NEVER',
      'surrounding' => 'RULE2_SURROUNDING',
      'allowed' => 'RULE2_ALLOWED',
    ));

    DjambiGameOptionRuleVariant::register($this, 'rule_vassalization', 'RULE3', 'full_control', 'radios', array(
      'temporary' => 'RULE3_TEMPORARY',
      'full_control' => 'RULE3_FULL_CONTROL',
    ));

    DjambiGameOptionRuleVariant::register($this, 'rule_canibalism', 'RULE4', 'no', 'radios', array(
      'yes' => 'RULE4_YES',
      'vassals' => 'RULE4_VASSALS',
      'no' => 'RULE4_NO',
      'ethical' => 'RULE4_ETHICAL',
    ));

    DjambiGameOptionRuleVariant::register($this, 'rule_self_diplomacy', 'RULE5', 'never', 'radios', array(
      'never' => 'RULE5_NEVER',
      'vassal' => 'RULE5_VASSAL',
    ));

    DjambiGameOptionRuleVariant::register($this, 'rule_press_liberty', 'RULE6', 'pravda', 'radios', array(
      'pravda' => 'RULE6_PRAVDA',
      'foxnews' => 'RULE6_FOXNEWS',
    ));

    DjambiGameOptionRuleVariant::register($this, 'rule_throne_interactions', 'RULE7', 'normal', 'radios', array(
      'normal' => 'RULE7_NORMAL',
      'extended' => 'RULE7_EXTENDED',
    ));

    return $this;
  }
}
