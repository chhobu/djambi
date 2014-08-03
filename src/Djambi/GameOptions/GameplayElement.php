<?php
namespace Djambi\GameOptions;

class GameplayElement extends BaseGameOption {

  public function __construct(GameOptionsStore $store, $name, $title, $default, $widget = NULL, $choices = NULL) {
    parent::__construct($store, __CLASS__, $name, $title, $default, $widget, $choices);
    $this->setGenericLabel("GAMEPLAY_ELEMENT", array(
      '!num' => str_pad(str_replace('OPTION', '', $title), 2, '0', STR_PAD_LEFT),
    ));
  }

}
