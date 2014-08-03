<?php
/**
 * Created by PhpStorm.
 * User: buchho
 * Date: 20/05/14
 * Time: 23:47
 */

namespace Drupal\djambi\Form\Actions;


use Djambi\Exceptions\DisallowedActionException;
use Drupal\Core\Form\FormStateInterface;
use Drupal\djambi\Form\BaseGameForm;

class Withdraw extends BaseAction {

  const ACTION_NAME = 'withdraw';

  protected function __construct(BaseGameForm $form) {
    $this->setTitle($this->t('Withdraw'));
    $this->addClass('button--danger');
    parent::__construct($form);
  }

  public function validate(&$form, FormStateInterface $form_state) {
    if (!empty($form_state->getErrors())) {
      return;
    }
    try {
      $this->getForm()->getGameManager()->getBattlefield()->getPlayingFaction()->withdraw();
    }
    catch (DisallowedActionException $exception) {
      $this->raiseError($form_state, $exception);
    }
  }
}
