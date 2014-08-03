<?php
/**
 * Created by PhpStorm.
 * User: buchho
 * Date: 20/05/14
 * Time: 23:47
 */

namespace Drupal\djambi\Form\Actions;


use Djambi\Exceptions\DisallowedActionException;
use Djambi\GameOptions\StandardRuleset;
use Drupal\Core\Form\FormStateInterface;
use Drupal\djambi\Form\BaseGameForm;

class DrawProposal extends BaseAction {

  const ACTION_NAME = 'ask-draw';

  protected function __construct(BaseGameForm $form) {
    $this->addClass('button--secondary');

    parent::__construct($form);
  }

  protected function isPrinted() {
    $game = $this->getForm()->getGameManager();
    $rule_draw_delay = $game->getOption(StandardRuleset::GAMEPLAY_ELEMENT_DRAW_DELAY);
    return $rule_draw_delay > -1 && parent::isPrinted();
  }

  protected function isActive() {
    $game = $this->getForm()->getGameManager();
    $rule_draw_delay = $game->getOption(StandardRuleset::GAMEPLAY_ELEMENT_DRAW_DELAY);
    $active = $game->getBattlefield()->getPlayingFaction()->canCallForADraw();
    if (!$active) {
      $this->setTitle($this->formatPlural($rule_draw_delay + $game->getBattlefield()->getPlayingFaction()->getLastDrawProposal() - $game->getBattlefield()->getCurrentTurn()->getRound(),
        'You cannot ask for a draw until next round', 'You cannot ask for a draw until @count rounds'));
    }
    else {
      $this->setTitle($this->t('Ask for a draw'));
    }
    return $active;
  }

  public function validate(&$form, FormStateInterface $form_state) {
    if (!empty($form_state->getErrors())) {
      return;
    }
    try {
      $this->getForm()->getGameManager()->getBattlefield()->getPlayingFaction()->callForADraw();
    }
    catch (DisallowedActionException $exception) {
      $this->raiseError($form_state, $exception);
    }
  }
}
