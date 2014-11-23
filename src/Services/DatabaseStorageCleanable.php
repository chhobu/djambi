<?php
/**
 * Created by PhpStorm.
 * User: buchho
 * Date: 23/11/14
 * Time: 13:21
 */

namespace Drupal\djambi\Services;


use Drupal\Core\KeyValueStore\DatabaseStorageExpirable;

class DatabaseStorageCleanable extends DatabaseStorageExpirable {

  public function forceGarbageCollection() {
    $this->needsGarbageCollection = TRUE;
  }

  protected function garbageCollection() {
    $this->connection->delete($this->table)
      ->condition('expire', REQUEST_TIME, '<')
      ->condition('collection', $this->collection)
      ->execute();
  }

} 