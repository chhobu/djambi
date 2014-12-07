<?php
/**
 * Created by PhpStorm.
 * User: buchho
 * Date: 21/05/14
 * Time: 01:15
 */

namespace Drupal\djambi\Form\Actions;


use Djambi\Exceptions\DisallowedActionException;
use Djambi\GameOptions\StandardRuleset;
use Drupal\Core\Form\FormStateInterface;
use Drupal\djambi\Form\BaseGameForm;

class SkipTurn extends BaseAction {

  const ACTION_NAME = 'skip-turn';

  protected function __construct(BaseGameForm $form) {
    $this->addClass('button--warning');
    parent::__construct($form);
  }

  protected function getSkippedTurnsRule() {
    return $this->getForm()->getGameManager()->getOption(StandardRuleset::GAMEPLAY_ELEMENT_SKIPPED_TURNS);
  }

  protected function isPrinted() {
    return $this->getSkippedTurnsRule() != 0 && parent::isPrinted();
  }

  protected function isActive() {
    $grid = $this->getForm()->getGameManager()->getBattlefield();
    $can_skip = $grid->getPlayingFaction()->canSkipTurn();
    if (!$grid->getPlayingFaction()->canSkipTurn()) {
      $label = $this->t('You cannot skip turns anymore');
    }
    elseif ($this->getSkippedTurnsRule() == -1) {
      $label = $this->t('Skip turn');
    }
    else {
      $label = $this->formatPlural($this->getSkippedTurnsRule() - $grid->getPlayingFaction()->getSkippedTurns(),
        'Skip turn (only 1 allowed)', 'Skip turn (still @count allowed)');
    }
    $this->setTitle($label);
    return $can_skip;
  }

  public function validate(&$form, FormStateInterface $form_state) {
    if (!empty($form_state->getErrors())) {
      return;
    }
    try {
      $this->getForm()->getGameManager()->getBattlefield()->getPlayingFaction()->skipTurn();
    }
    catch (DisallowedActionException $exception) {
      $this->raiseError($form_state, $exception);
    }
  }
}
