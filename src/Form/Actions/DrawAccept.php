<?php
/**
 * Created by PhpStorm.
 * User: buchho
 * Date: 21/05/14
 * Time: 01:50
 */

namespace Drupal\djambi\Form\Actions;


use Djambi\Enums\StatusEnum;
use Djambi\Exceptions\DisallowedActionException;
use Drupal\Core\Form\FormStateInterface;
use Drupal\djambi\Form\BaseGameForm;

class DrawAccept extends BaseAction {
  const ACTION_NAME = 'accept-draw';

  protected function __construct(BaseGameForm $form) {
    $this->addClass('button--warning');
    $this->addClass('button--yes');
    $this->setTitle($this->t("Yes, let's end this mess and stay good friends."));
    parent::__construct($form);
  }

  public function validate(&$form, FormStateInterface $form_state) {
    if (!empty($form_state->getErrors())) {
      return;
    }
    try {
      $this->getForm()->getGameManager()->getBattlefield()->getPlayingFaction()->acceptDraw();
    }
    catch (DisallowedActionException $exception) {
      $this->raiseError($form_state, $exception);
    }
  }

  protected function isPrinted() {
    return $this->form->getGameManager()->getStatus() == StatusEnum::STATUS_DRAW_PROPOSAL;
  }
}
