<?php
/**
 * Created by PhpStorm.
 * User: buchho
 * Date: 20/05/14
 * Time: 23:46
 */

namespace Drupal\djambi\Form\Actions;


use Djambi\Exceptions\Exception;
use Djambi\GameManagers\BasicGameManager;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\djambi\Form\DjambiFormBase;

abstract class DjambiGridActionBase implements DjambiGridActionInterface  {
  use StringTranslationTrait;
  const ACTION_NAME = 'undefined';

  /** @var DjambiFormBase */
  protected $form;
  /** @var array */
  protected $classes = array();
  /** @var array */
  protected $submit;
  /** @var array */
  protected $validate;
  /** @var string */
  protected $title;

  protected function __construct(DjambiFormBase $form) {
    $this->form = $form;
    if (static::ACTION_NAME != 'undefined') {
      $this->addClass('button-' . static::ACTION_NAME);
    }
    $this->submit = array(array($form, 'submitForm'));
    $this->validate = array(
      array($form, 'validateForm'),
      array($this, 'validate'),
    );
  }

  protected function isPrinted() {
    return $this->form->getGameManager()->getStatus() == BasicGameManager::STATUS_PENDING;
  }

  protected function isActive() {
    return TRUE;
  }

  public static function addButton(DjambiFormBase $form_object, array &$form_array, $weight = 0) {
    $action = new static($form_object);
    if (!$action->isPrinted()) {
      return $action;
    }
    $button = array(
      '#type' => 'submit',
      '#submit' => $action->getSubmit(),
      '#validate' => $action->getValidate(),
    );
    $button['#limit_validation_errors'] = array(
      array('turn_id'),
    );
    if (!empty($action->classes)) {
      $button['#attributes']['class'] = $action->getClasses();
    }
    if (!$action->isActive()) {
      $button['#disabled'] = TRUE;
    }
    if (!empty($weight)) {
      $button['#weight'] = $weight;
    }
    $button['#value'] = $action->getTitle();
    $form_array[static::ACTION_NAME] = $button;
    return $action;
  }

  /**
   * @return mixed
   */
  public function getValidate() {
    return $this->validate;
  }

  /**
   * @return array
   */
  public function getSubmit() {
    return $this->submit;
  }

  /**
   * @return DjambiFormBase
   */
  public function getForm() {
    return $this->form;
  }

  /**
   * @return mixed
   */
  public function getTitle() {
    return $this->title;
  }

  /**
   * @return array
   */
  public function getClasses() {
    return $this->classes;
  }

  /**
   * @param string $title
   */
  public function setTitle($title) {
    $this->title = $title;
  }

  /**
   * @param array $class
   */
  public function addClass($class) {
    $this->classes[] = $class;
  }

  protected function raiseError(Exception $exception) {
    $this->getForm()->addFormError(static::ACTION_NAME, $form_state, $this->t('Invalid action fired : @exception.', array(
      '@exception' => $exception->getMessage(),
    )));
  }

}