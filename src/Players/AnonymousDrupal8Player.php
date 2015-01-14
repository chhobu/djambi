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

  public static function retrieveId($session_id) {
    $djambi_id = static::getCookie(static::COOKIE_AUTH_NAME);
    if (empty($djambi_id)) {
      $djambi_id = static::CLASS_NICKNAME . Crypt::hashBase64($session_id);
    }
    return $djambi_id;
  }

  public function useSeat() {
    $this->setCookie(static::COOKIE_AUTH_NAME, $this->getId());
    return parent::useSeat();
  }

  public function loadDisplaySettings() {
    $user_settings = $this->getCookie(static::COOKIE_SETTINGS_NAME);
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
    $this->setCookie(static::COOKIE_SETTINGS_NAME, serialize($settings));
    return parent::saveDisplaySettings($settings);
  }

  public function clearDisplaySettings() {
    $this->deleteCookie(static::COOKIE_SETTINGS_NAME);
    return parent::clearDisplaySettings();
  }

  public function __sleep() {
    $keys = get_object_vars($this);
    unset($keys['account']);
    return array_keys($keys);
  }

}
