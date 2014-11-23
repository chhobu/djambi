<?php

/**
 * @file
 * Definition of Drupal\user\TempStoreFactory.
 */

namespace Drupal\djambi\Services;

use Drupal\user\TempStoreFactory;

/**
 * Creates a key/value storage object for the current user or anonymous session.
 */
class DjambiTempStoreFactory extends TempStoreFactory {

  public function getDjambiCollection($owner = NULL) {
    return $this->get('djambi', $owner);
  }

  public function garbageCollection() {
    $storage = new DatabaseStorageCleanable('djambi', $this->serializer, $this->connection);
    $storage->forceGarbageCollection();
    $storage->destruct();
  }

}
