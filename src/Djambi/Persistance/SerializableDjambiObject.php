<?php
/**
 * Created by PhpStorm.
 * User: buchho
 * Date: 08/05/14
 * Time: 13:22
 */

namespace Djambi\Persistance;


abstract class SerializableDjambiObject {
  /** @var array */
  private $unserializableProperties = array();

  protected function addUnserializableProperties(array $properties) {
    $this->unserializableProperties = array_merge($this->unserializableProperties, $properties);
    return $this;
  }

  protected function prepareSerialization() {
    $this->addUnserializableProperties(array('unserializableProperties'));
    return $this;
  }

  public function __sleep() {
    $this->prepareSerialization();
    $keys = get_object_vars($this);
    return array_diff(array_keys($keys), $this->unserializableProperties);
  }

}
