<?php

namespace Djambi\Persistance;

use Djambi\Exceptions\UnpersistableObjectException;

abstract class PersistantDjambiObject extends SerializableDjambiObject implements ArrayableInterface {
  /** @var array */
  private $persistantProperties = array();
  /** @var array */
  private $dependantObjects = array();
  /** @var array */
  private $persistantData = array();
  /** @var string */
  private $className;

  protected function saveData($data, $value) {
    $this->persistantData[$data] = $value;
  }

  protected function addDependantObjects(array $objects) {
    foreach ($objects as $name => $primary_key) {
      $this->dependantObjects[$name] = $primary_key;
    }
    return $this;
  }

  protected function addPersistantProperties(array $attributes) {
    foreach ($attributes as $value) {
      $this->persistantProperties[$value] = TRUE;
    }
    return $this;
  }

  protected function removePersistantProperties($attributes) {
    foreach ($attributes as $value) {
      if (isset($this->persistantProperties[$value])) {
        unset($this->persistantProperties[$value]);
      }
    }
    return $this;
  }

  /**
   * @return string
   */
  public function getClassName() {
    if (is_null($this->className)) {
      $this->className = get_class($this);
    }
    return $this->className;
  }

  protected function getPersistantData() {
    return $this->getPersistantData();
  }

  protected function prepareArrayConversion() {
    $this->addPersistantProperties(array('className'));
    return $this;
  }

  /**
   * @return array
   */
  public function toArray() {
    $this->prepareArrayConversion();
    foreach ($this->persistantProperties as $attribute => $valid) {
      if (!$valid) {
        continue;
      }
      $attribute_value = $this->retrievePropertyValue($attribute);
      if (is_null($attribute_value)) {
        continue;
      }
      elseif (is_object($attribute_value)) {
        $saved_value = $this->saveReferencedObjectsToArray($attribute_value);
        if (is_null($saved_value)) {
          continue;
        }
      }
      elseif (is_array($attribute_value) && !empty($attribute_value)) {
        $saved_value = $this->saveReferencedArrays($attribute_value);
        if (is_null($saved_value)) {
          continue;
        }
      }
      else {
        $saved_value = $attribute_value;
      }
      $this->saveData($attribute, $saved_value);
    }
    foreach ($this->dependantObjects as $object_name => $primary_key) {
      if (is_null($primary_key)) {
        continue;
      }
      $value = $this->retrievePropertyValue($object_name, NULL, FALSE);
      if (is_array($value)) {
        $object_list = $value;
        foreach ($value as $key => $object) {
          if (!empty($object) && is_object($object)) {
            $object_id = $this->retrievePropertyValue($primary_key, $object, FALSE);
            $object_list[$key] = $object_id;
          }
        }
        $this->saveData($object_name, $object_list);
      }
      else {
        $object = $value;
        if (!empty($object) && is_object($object)) {
          $object_id = $this->retrievePropertyValue($primary_key, $object, FALSE);
          if (!is_null($object_id)) {
            $this->saveData($object_name, $object_id);
          }
        }
      }
    }
    return $this->persistantData;
  }

  protected function retrievePropertyValue($property, $object = NULL, $can_be_boolean = TRUE) {
    if (is_null($object)) {
      $object = $this;
    }
    if (method_exists($object, 'get' . ucfirst($property))) {
      return call_user_func(array($object, 'get' . ucfirst($property)));
    }
    elseif ($can_be_boolean && method_exists($object, 'is' . ucfirst($property))) {
      return call_user_func(array($object, 'is' . ucfirst($property)));
    }
    elseif (method_exists($object, $property)) {
      return call_user_func(array($object, $property));
    }
    elseif (isset($object->$property)) {
      return $object->$property;
    }
    else {
      return NULL;
    }
  }

  protected function saveReferencedObjectsToArray($object) {
    if ($object instanceof ArrayableInterface) {
      return $object->toArray();
    }
    else {
      throw new UnpersistableObjectException();
    }
  }

  protected function saveReferencedArrays($array) {
    foreach ($array as $key => $value) {
      if (is_object($value)) {
        $array[$key] = $this->saveReferencedObjectsToArray($value);
      }
      elseif (is_array($value)) {
        $array[$key] = $this->saveReferencedArrays($value);
      }
    }
    return $array;
  }

  protected function prepareSerialization() {
    $this->addUnserializableProperties(array(
      'className',
      'dependantObjects',
      'persistantData',
      'persistantProperties',
    ));
    return parent::prepareSerialization();
  }

}
