<?php
/**
 * Created by PhpStorm.
 * User: buchho
 * Date: 20/05/14
 * Time: 23:46
 */

namespace Drupal\djambi\Form\Actions;


use Djambi\Enums\StatusEnum;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\djambi\Form\BaseGameForm;
use Drupal\djambi\Form\SandboxGameForm;

abstract class BaseAction implements ActionInterface  {
  use StringTranslationTrait;
  const ACTION_NAME = 'undefined';

  /** @var SandboxGameForm */
  protected $form;
  /** @var array */
  protected $classes = array();
  /** @var array */
  protected $submit;
  /** @var array */
  protected $validate;
  /** @var string */
  protected $title;
  /** @var string */
  protected $validateFields = array();

  protected function __construct(BaseGameForm $form) {
    $this->form = $form;
    if (static::ACTION_NAME != 'undefined') {
      $this->addClass('button--' . static::ACTION_NAME);
    }
    $this->submit = array('::submitForm');
    $this->validate = array('::validateForm', array($this, 'validate'));
    $this->addValidateField('turn_id');
  }

  protected function isPrinted() {
    return $this->form->getGameManager()->getStatus() == StatusEnum::STATUS_PENDING;
  }

  protected function isActive() {
    return TRUE;
  }

  public static function addButton(BaseGameForm $form_object, array &$form_array, array $ajax = NULL, $weight = 0) {
    $action = new static($form_object);
    if (!$action->isPrinted()) {
      return $action;
    }
    $button = array(
      '#type' => 'submit',
      '#submit' => $action->getSubmit(),
      '#validate' => $action->getValidate(),
    );
    $button['#limit_validation_errors'] = array($action->getValidateFields());
    if (!empty($action->classes)) {
      $button['#attributes']['class'] = $action->getClasses();
    }
    if (!$action->isActive()) {
      $button['#disabled'] = TRUE;
    }
    if (!empty($ajax)) {
      $button['#ajax'] = $ajax;
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
  protected function getValidate() {
    return $this->validate;
  }

  /**
   * @return array
   */
  protected function getSubmit() {
    return $this->submit;
  }

  /**
   * @return SandboxGameForm
   */
  protected function getForm() {
    return $this->form;
  }

  /**
   * @return mixed
   */
  protected function getTitle() {
    return $this->title;
  }

  /**
   * @return array
   */
  protected function getClasses() {
    return $this->classes;
  }

  /**
   * @param string $title
   */
  protected function setTitle($title) {
    $this->title = $title;
  }

  /**
   * @param array $class
   */
  public function addClass($class) {
    $this->classes[] = $class;
  }

  protected function raiseError(FormStateInterface $form_state, \Exception $exception) {
    $form_state->setErrorByName(static::ACTION_NAME, $this->t('Invalid action fired : @exception.', array(
      '@exception' => $exception->getMessage(),
    )));
  }

  public function validate(&$form, FormStateInterface $form_state) {
  }

  protected function getValidateFields() {
    return $this->validateFields;
  }

  protected function addValidateField($field) {
    $this->validateFields[] = $field;
    return $this;
  }


}
