<?php
/**
 * Created by PhpStorm.
 * User: buchho
 * Date: 02/05/14
 * Time: 01:02
 */

namespace Djambi\Strings;


use Djambi\Persistance\ArrayableInterface;
use Djambi\Persistance\PersistantDjambiTrait;

class GlossaryTerm implements ArrayableInterface {

  use PersistantDjambiTrait;

  protected $string;
  protected $args = array();

  public function __construct($string, $args = array()) {
    $this->string = $string;
    $this->args = $args;
  }

  public function __toString() {
    return Glossary::getInstance()->displayTerm($this);
  }

  protected function prepareArrayConversion() {
    $save[] = 'string';
    if (!empty($this->getArgs())) {
      $save[] = 'args';
    }
    $this->addPersistantProperties($save);
    return $this;
  }

  public static function fromArray(array $data, array $context = array()) {
    $args = !empty($data['args']) && is_array($data['args']) ? $data['args'] : array();
    return new static($data['string'], $args);
  }

  /**
   * @return array
   */
  public function getArgs() {
    return $this->args;
  }

  /**
   * @return string
   */
  public function getString() {
    return $this->string;
  }

}
