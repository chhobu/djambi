<?php

namespace Djambi\Persistance;


interface ArrayableInterface {

  /**
   * @param $array
   * @param $context
   *
   * @return ArrayableInterface
   */
  public static function fromArray(array $array, array $context = array());

  /**
   * @return array
   */
  public function toArray();

  /**
   * @return string
   */
  public function getClassName();

}
