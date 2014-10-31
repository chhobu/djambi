<?php
/**
 * Created by PhpStorm.
 * User: buchho
 * Date: 13/05/14
 * Time: 21:21
 */

namespace Djambi\Gameplay;


use Djambi\Persistance\ArrayableInterface;
use Djambi\Persistance\PersistantDjambiTrait;

abstract class BaseChange implements ArrayableInterface {

  use PersistantDjambiTrait;

  /** @var ArrayableInterface */
  protected $object;
  /** @var String */
  protected $change;
  /** @var mixed */
  protected $oldValue;
  /** @var mixed */
  protected $newValue;

  protected function prepareArrayConversion() {
    $this->addDependantObjects(array(
      'object' => static::objectId(),
    ));
    $this->addPersistantProperties(array(
      'change',
      'oldValue',
      'newValue',
    ));
    return $this;
  }

  public static function fromArray(array $array, array $context = array()) {
    $object = static::objectLoad($array['object'], $context);
    if (!isset($array['oldValue'])) {
      $array['oldValue'] = NULL;
    }
    return new static($object, $array['change'], $array['oldValue'], $array['newValue']);
  }

  public static function objectId() {
    return 'id';
  }

  public static function objectLoad($array, $context) {
    return call_user_func($array['className'] . '::fromArray', $array, $context);
  }

  public function __construct(ArrayableInterface $object, $change, $old_value, $new_value) {
    $this->object = $object;
    $this->change = $change;
    $this->oldValue = $old_value;
    $this->newValue = $new_value;
  }

  public function getObject() {
    return $this->object;
  }

  public function getChange() {
    return $this->change;
  }

  public function getOldValue() {
    return $this->oldValue;
  }

  public function getNewValue() {
    return $this->newValue;
  }

  public function execute() {
    call_user_func(array($this->getObject(), 'set' . ucfirst($this->getChange())), $this->getNewValue());
    return $this;
  }

  public function revert() {
    call_user_func(array($this->getObject(), 'set' . ucfirst($this->getChange())), $this->getOldValue());
    return $this;
  }
}
