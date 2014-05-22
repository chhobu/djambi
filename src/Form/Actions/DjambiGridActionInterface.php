<?php
/**
 * Created by PhpStorm.
 * User: buchho
 * Date: 21/05/14
 * Time: 00:52
 */

namespace Drupal\djambi\Form\Actions;


use Drupal\djambi\Form\DjambiFormBase;

interface DjambiGridActionInterface {
  public function validate(&$form, &$form_state);
  public static function addButton(DjambiFormBase $form_object, array &$form_array, $weight = 0);
  public function getForm();
}
