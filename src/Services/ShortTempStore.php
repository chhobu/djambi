<?php

/**
 * @file
 * Contains Drupal\djambi\Tools\ShortTempStore.
 */

namespace Drupal\djambi\Services;

use Drupal\user\TempStore;

class ShortTempStore extends TempStore {

  protected $expire = 3600;

  public function setExpire($expire) {
    $this->expire = $expire;
  }

}
