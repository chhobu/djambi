<?php
/**
 * Created by PhpStorm.
 * User: buchho
 * Date: 20/05/14
 * Time: 23:47
 */

namespace Drupal\djambi\Form\Actions;


use Djambi\GameManagers\BasicGameManager;
use Drupal\djambi\Form\DjambiFormBase;

class DjambiGridActionRestart extends DjambiGridActionBase {

  const ACTION_NAME = 'restart';

  protected function __construct(DjambiFormBase $form) {
    $this->setTitle($this->t('Restart'));
    $this->addClass('button-danger');
    parent::__construct($form);
  }

  public function isPrinted() {
    return in_array($this->getForm()->getGameManager()->getStatus(),
      array(BasicGameManager::STATUS_PENDING, BasicGameManager::STATUS_FINISHED))
    && $this->getForm()->getGameManager()->getMode() == BasicGameManager::MODE_SANDBOX;
  }

  public function validate(&$form, &$form_state) {
    $this->getForm()->resetGameManager();
  }
}
