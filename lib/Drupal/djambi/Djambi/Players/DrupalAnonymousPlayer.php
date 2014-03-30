<?php
namespace Drupal\kw_djambi\Djambi\Players;

use Djambi\Exceptions\PlayerInvalidException;

class DrupalAnonymousPlayer extends DrupalPlayer {

  public function __construct($id) {
    parent::__construct($id);
  }

  public function register(array $data = NULL) {
    if (empty($data['user']) || !is_object($data['user']) && $data['user']->uid !== 0) {
      throw new PlayerInvalidException("DrupalAnonymousPlayer must be an anonymous Drupal user object.");
    }
    $user = $data['user'];
    $this->setRegistered(TRUE);
    $this->setName(format_username($user));
    $this->setUser($user);
  }

  public static function loadPlayer(array $data) {
    $data['user'] = drupal_anonymous_user();
    return parent::loadPlayer($data);
  }

  public function getName() {
    if (!is_null($this->getLastSignal())) {
      return parent::getName() . " (" . check_plain($this->getLastSignal()->getIp()) . ")";
    }
    return parent::getName();
  }

  public function displayName() {
    $extra = '';
    if (!is_null($this->getLastSignal())) {
      $extra = ' (' . check_plain($this->getLastSignal()->getIp()) . ')';
    }
    return theme('username', array('account' => $this->getUser())) . $extra;
  }

}
