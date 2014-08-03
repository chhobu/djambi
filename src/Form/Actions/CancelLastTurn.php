<?php
/**
 * Created by PhpStorm.
 * User: buchho
 * Date: 21/05/14
 * Time: 00:25
 */

namespace Drupal\djambi\Form\Actions;


use Drupal\Core\Form\FormStateInterface;
use Drupal\djambi\Form\BaseGameForm;

class CancelLastTurn extends BaseAction {

  const ACTION_NAME = 'cancel-last-turn';

  protected function __construct(BaseGameForm $form) {
    $this->setTitle($this->t('Cancel last turn'));
    $this->addClass('button--cancel');
    parent::__construct($form);
  }

  protected function isActive() {
    return count($this->getForm()->getGameManager()->getBattlefield()->getPastTurns()) > 0;
  }

  public function validate(&$form, FormStateInterface $form_state) {
    if (!empty($form_state->getErrors())) {
      return;
    }
    $this->getForm()->getGameManager()->getBattlefield()->cancelLastTurn();
  }

}
