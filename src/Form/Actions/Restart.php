<?php
/**
 * Created by PhpStorm.
 * User: buchho
 * Date: 20/05/14
 * Time: 23:47
 */

namespace Drupal\djambi\Form\Actions;


use Djambi\Enums\StatusEnum;
use Drupal\Core\Form\FormStateInterface;
use Drupal\djambi\Form\BaseGameForm;

class Restart extends BaseAction {

  const ACTION_NAME = 'restart';

  protected function __construct(BaseGameForm $form) {
    $this->setTitle($this->t('Restart'));
    $this->addClass('button--danger');
    parent::__construct($form);
  }

  public function isPrinted() {
    return in_array($this->getForm()->getGameManager()->getStatus(),
      array(StatusEnum::STATUS_PENDING, StatusEnum::STATUS_FINISHED))
    && $this->getForm()->getGameManager()->isCancelActionAllowed();
  }

  public function validate(&$form, FormStateInterface $form_state) {
    $this->getForm()->resetGameManager();
  }

  protected function isActive() {
    return !empty($this->getForm()->getGameManager()->getBattlefield()->getPastTurns());
  }

}
