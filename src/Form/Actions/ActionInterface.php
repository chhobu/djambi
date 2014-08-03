<?php
/**
 * Created by PhpStorm.
 * User: buchho
 * Date: 21/05/14
 * Time: 00:52
 */

namespace Drupal\djambi\Form\Actions;


use Drupal\Core\Form\FormStateInterface;
use Drupal\djambi\Form\BaseGameForm;

interface ActionInterface {
  public function validate(&$form, FormStateInterface $form_state);
  public static function addButton(BaseGameForm $form_object, array &$form_array, array $ajax = NULL, $weight = 0);
}
