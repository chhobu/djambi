<?php

/**
 * @file
 * Definition of Drupal\user\TempStoreFactory.
 */

namespace Drupal\djambi\Services;

use Drupal\Core\KeyValueStore\DatabaseStorageExpirable;
use Drupal\user\TempStoreFactory;

/**
 * Creates a key/value storage object for the current user or anonymous session.
 */
class ShortTempStoreFactory extends TempStoreFactory {

  /** @var DatabaseStorageExpirable */
  protected $storage;

  /**
   * Creates a TempStore for the current user or anonymous session.
   *
   * @param string $collection
   *   The collection name to use for this key/value store. This is typically
   *   a shared namespace or module name, e.g. 'views', 'entity', etc.
   * @param mixed $owner
   *   (optional) The owner of this TempStore. By default, the TempStore is
   *   owned by the currently authenticated user, or by the active anonymous
   *   session if no user is logged in.
   *
   * @return ShortTempStore
   *   An instance of the the key/value store.
   */
  public function get($collection, $owner = NULL) {
    // Use the currently authenticated user ID or the active user ID unless
    // the owner is overridden.
    if (!isset($owner)) {
      $owner = \Drupal::currentUser()->id() ?: session_id();
    }

    // Store the data for this collection in the database.
    $this->storage = new DatabaseStorageExpirable($collection, $this->serializer, $this->connection);
    return new ShortTempStore($this->storage, $this->lockBackend, $owner);
  }

  public function destruct() {
    if (!empty($this->storage)) {
      $this->storage->destruct();
    }
  }

}
