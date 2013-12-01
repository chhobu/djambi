<?php

namespace Drupal\kw_djambi\Djambi\Traits;


use Djambi\GameManager;

trait DrupalDjambiFormTrait {

  abstract public function createGameManager();

  /**
   * Génère le formulaire de jeu sous Drupal.
   *
   * @param array $form
   * @param array $form_state
   * @param array $form_options
   *
   * @return GameManager
   */
  public function generateGameUsingDrupalForm(&$form, &$form_state, array $form_options = array()) {
    if (!empty($form_state['djambi']['game'])) {
      $game = $form_state['djambi']['game'];
    }
    else {
      $game = $this->createGameManager();
      $form_state['djambi']['game'] = $game;
    }
    form_load_include($form_state, 'inc', 'kw_djambi', 'kw_djambi.form');
    _kw_djambi_build_game_form($form, $form_state, $game, $form_options);
    return $game;
  }

}
