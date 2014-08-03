<?php
/**
 * Created by PhpStorm.
 * User: buchho
 * Date: 21/05/14
 * Time: 01:49
 */

namespace Drupal\djambi\Form\Actions;


use Djambi\Exceptions\DisallowedActionException;
use Drupal\Core\Form\FormStateInterface;
use Drupal\djambi\Form\BaseGameForm;

class DrawReject extends BaseAction {
  const ACTION_NAME = 'reject-draw';

  protected function __construct(BaseGameForm $form) {
    $this->addClass('button--primary');
    $this->addClass('button--no');
    $this->setTitle($this->t("No, I'm sure to win this one !"));
    parent::__construct($form);
  }

  public function validate(&$form, FormStateInterface $form_state) {
    if (!empty($form_state->getErrors())) {
      return;
    }
    try {
      $this->getForm()->getGameManager()->getBattlefield()->getPlayingFaction()->rejectDraw();
    }
    catch (DisallowedActionException $exception) {
      $this->raiseError($form_state, $exception);
    }
  }
}
