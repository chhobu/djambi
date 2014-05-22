<?php
/**
 * Created by PhpStorm.
 * User: buchho
 * Date: 21/05/14
 * Time: 00:25
 */

namespace Drupal\djambi\Form\Actions;


use Drupal\djambi\Form\DjambiFormBase;

class DjambiGridActionCancelLastTurn extends DjambiGridActionBase {

  const ACTION_NAME = 'cancel-last-turn';

  protected function __construct(DjambiFormBase $form) {
    $this->setTitle($this->t('Cancel last turn'));
    $this->addClass('button-cancel');
    parent::__construct($form);
  }

  protected function isActive() {
    return count($this->getForm()->getGameManager()->getBattlefield()->getPastTurns()) > 0;
  }

  public function validate(&$form, &$form_state) {
    $this->getForm()->getGameManager()->getBattlefield()->cancelLastTurn();
  }

}
