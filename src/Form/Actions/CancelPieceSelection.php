<?php
/**
 * Created by PhpStorm.
 * User: buchho
 * Date: 21/05/14
 * Time: 01:36
 */

namespace Drupal\djambi\Form\Actions;


use Drupal\djambi\Form\BaseGameForm;

class CancelPieceSelection extends BaseAction {

  const ACTION_NAME = 'cancel-selection';

  public function validate(&$form, &$form_state) {
    $this->getForm()->getGameManager()->getBattlefield()->getCurrentTurn()->resetMove();
  }

  protected function __construct(BaseGameForm $form) {
    $this->setTitle($this->t('Cancel piece selection'));
    $this->addClass('button-cancel');
    parent::__construct($form);
  }

}
