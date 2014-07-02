<?php
/**
 * Created by PhpStorm.
 * User: buchho
 * Date: 25/05/14
 * Time: 16:42
 */

namespace Drupal\djambi\Players;

use Drupal\user\UserData;

class AuthenticatedDrupal8Player extends Drupal8Player {
  const CLASS_NICKNAME = 'uid-';

  /** @var UserData */
  protected $userData;

  protected function getUserData() {
    if (is_null($this->userData)) {
      $this->setUserData(\Drupal::service('user.data'));
    }
    return $this->userData;
  }

  protected function setUserData(UserData $user_data) {
    $this->userData = $user_data;
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
}
