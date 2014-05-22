<?php
/**
 * Created by PhpStorm.
 * User: buchho
 * Date: 21/05/14
 * Time: 01:49
 */

namespace Drupal\djambi\Form\Actions;


use Djambi\Exceptions\DisallowedActionException;
use Drupal\djambi\Form\DjambiFormBase;

class DjambiGridActionDrawReject extends DjambiGridActionBase {
  const ACTION_NAME = 'reject-draw';

  protected function __construct(DjambiFormBase $form) {
    $this->addClass('button-primary');
    $this->addClass('button-no');
    $this->setTitle($this->t("No, I'm sure to win this one !"));
    parent::__construct($form);
  }

  public function validate(&$form, &$form_state) {
    try {
      $this->getForm()->getGameManager()->getBattlefield()->getPlayingFaction()->rejectDraw();
    }
    catch (DisallowedActionException $exception) {
      $this->raiseError($exception);
    }
  }
}
