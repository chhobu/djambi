<?php
namespace Djambi\GameOptions;

use Djambi\GameOption;
use Djambi\Stores\GameOptionsStore;

class RuleVariant extends GameOption {

  public function __construct(GameOptionsStore $store, $name, $title, $default, $widget = NULL, $choices = NULL) {
    parent::__construct($store, __CLASS__, $name, $title, $default, $widget, $choices);
    $this->setCssClass('rule-variant');
    $this->setGenericLabel('RULE_VARIANT', array(
      '!num' => str_pad(str_replace('RULE', '', $title), 2, '0', STR_PAD_LEFT),
    ));
  }
}
