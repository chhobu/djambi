<?php
/**
 * Created by PhpStorm.
 * User: buchho
 * Date: 25/05/14
 * Time: 16:42
 */

namespace Drupal\djambi\Players;

use Drupal\user\Entity\User;
use Drupal\user\UserData;

class AuthenticatedDrupal8Player extends Drupal8Player {
  const CLASS_NICKNAME = 'uid-';

  /** @var UserData */
  protected $user_data;
  /** @var int */
  protected $uid;

  protected function getUserData() {
    if (is_null($this->user_data)) {
      $this->setUserData(\Drupal::service('user.data'));
    }
    return $this->user_data;
  }

  protected function setUserData(UserData $user_data) {
    $this->user_data = $user_data;
  }

  public function getAccount() {
    if (is_null($this->account) && !empty($this->uid)) {
      $this->account = User::load($this->uid);
    }
    return $this->account;
  }

  public function loadDisplaySettings() {
    $this->displaySettings = $this->getUserData()->get('djambi', $this->getAccount()->id(), 'display_settings');
    if (empty($this->displaySettings)) {
      $this->displaySettings = array();
    }
    return parent::loadDisplaySettings();
  }

  public function saveDisplaySettings($settings) {
    $this->getUserData()->set('djambi', $this->getAccount()->id(), 'display_settings', $settings);
    return parent::saveDisplaySettings($settings);
  }

  public function clearDisplaySettings() {
    $this->getUserData()->delete('djambi', $this->getAccount()->id(), 'display_settings');
    return parent::clearDisplaySettings();
  }

  public function __sleep() {
    $this->uid = $this->account->id();
    $keys = get_object_vars($this);
    unset($keys['user_data'], $keys['account']);
    return array_keys($keys);
  }
}
