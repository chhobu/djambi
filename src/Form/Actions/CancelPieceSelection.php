<?php
/**
 * Created by PhpStorm.
 * User: buchho
 * Date: 21/05/14
 * Time: 01:36
 */

namespace Drupal\djambi\Form\Actions;


use Drupal\Core\Form\FormStateInterface;
use Drupal\djambi\Form\BaseGameForm;

class CancelPieceSelection extends BaseAction {

  const ACTION_NAME = 'cancel-selection';

  public function validate(&$form, FormStateInterface $form_state) {
    if (!empty($form_state->getErrors())) {
      return;
    }
    $this->getForm()->getGameManager()->getBattlefield()->getCurrentTurn()->resetMove();
    if (!empty($form_state['input']['js-extra-choice'])) {
      $form_state['values']['cells'] = $form_state['input']['js-extra-choice'];
      unset($form_state['input']['js-extra-choice']);
      $this->getForm()->validatePieceSelection($form, $form_state);
    }
  }

  protected function __construct(BaseGameForm $form) {
    $this->setTitle($this->t('Cancel piece selection'));
    $this->addClass('button--cancel');
    $this->addValidateField('js-extra-choice');
    parent::__construct($form);
  }

}
