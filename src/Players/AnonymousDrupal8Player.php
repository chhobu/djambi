<?php
/**
 * Created by PhpStorm.
 * User: buchho
 * Date: 25/05/14
 * Time: 16:43
 */

namespace Drupal\djambi\Players;


use Drupal\Component\Utility\Crypt;

class AnonymousDrupal8Player extends Drupal8Player {
  const CLASS_NICKNAME = 'anon-';
  const COOKIE_SETTINGS_NAME = 'djambisettings';

  public function useSeat() {
    $this->id = static::CLASS_NICKNAME . Crypt::hashBase64($this->getId());
    user_cookie_save(array(static::COOKIE_NAME => $this->getId()));
    return parent::useSeat();
  }

  public function loadDisplaySettings() {
    $user_settings = \Drupal::request()->cookies->get('Drupal_visitor_' . static::COOKIE_SETTINGS_NAME);
    if (!empty($user_settings)) {
      $user_settings = unserialize($user_settings);
    }
    else {
      $user_settings = array();
    }
    $this->displaySettings = $user_settings;
    return parent::loadDisplaySettings();
  }

  public function saveDisplaySettings($settings) {
    user_cookie_save(array(static::COOKIE_SETTINGS_NAME => serialize($settings)));
    return parent::saveDisplaySettings($settings);
  }

  public function clearDisplaySettings() {
    user_cookie_delete(static::COOKIE_SETTINGS_NAME);
    return parent::clearDisplaySettings();
  }

}
