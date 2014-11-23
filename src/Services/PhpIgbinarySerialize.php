<?php
/**
 * Created by PhpStorm.
 * User: buchho
 * Date: 23/11/14
 * Time: 12:03
 */

namespace Drupal\djambi\Services;

use Drupal\Component\Serialization\SerializationInterface;

class PhpIgbinarySerialize implements SerializationInterface {

  /**
   * {@inheritdoc}
   */
  public static function encode($data) {
    return igbinary_serialize($data);
  }

  /**
   * {@inheritdoc}
   */
  public static function decode($raw) {
    return igbinary_unserialize($raw);
  }

  /**
   * {@inheritdoc}
   */
  public static function getFileExtension() {
    return 'serialized';
  }

}
