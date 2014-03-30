<?php
namespace Drupal\kw_djambi\Djambi\Players;

use Djambi\Exceptions\PlayerInvalidException;

class DrupalIdentifiedPlayer extends DrupalPlayer {

  public function __construct($id) {
    parent::__construct($id);
  }

  public function register(array $data = NULL) {
    if (empty($data['user']) || !is_object($data['user']) || empty($data['user']->uid)) {
      throw new PlayerInvalidException("DrupalIdentifiedPlayer must be an unanonymous Drupal user object.");
    }
    $user = $data['user'];
    $this->setRegistered(TRUE);
    $this->setId($user->uid);
    $this->setName(format_username($user));
    $this->setUser($user);
  }

  public static function loadPlayer(array $data) {
    $data['user'] = user_load($data['id']);
    return parent::loadPlayer($data);
  }

}
