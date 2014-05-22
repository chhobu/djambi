<?php
/**
 * Created by PhpStorm.
 * User: buchho
 * Date: 20/05/14
 * Time: 23:47
 */

namespace Drupal\djambi\Form\Actions;


use Djambi\Exceptions\DisallowedActionException;
use Drupal\djambi\Form\DjambiFormBase;

class DjambiGridActionWithdraw extends DjambiGridActionBase {

  const ACTION_NAME = 'withdraw';

  protected function __construct(DjambiFormBase $form) {
    $this->setTitle($this->t('Withdraw'));
    $this->addClass('button-danger');
    parent::__construct($form);
  }

  public function validate(&$form, &$form_state) {
    try {
      $this->getForm()->getGameManager()->getBattlefield()->getPlayingFaction()->withdraw();
    }
    catch (DisallowedActionException $exception) {
      $this->raiseError($exception);
    }
  }
}
