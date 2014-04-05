<?php
namespace Drupal\djambi\Controller;

class SandboxGameController {
  public static function play() {
    $elements['intro'] = array(
      '#markup' => '<p>' . t("Welcome to Djambi training area. You are about to play "
      . "a Djambi game where you control successively all sides : this way, "
      . "you will be able to learn Djambi basic rules, experiment new tactics "
      . "or play with (future ex-)friends in a hot chair mode.") . '</p>',
    );
    return $elements;
  }
}
