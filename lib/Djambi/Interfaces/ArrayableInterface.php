<?php

namespace Djambi\Interfaces;


interface ArrayableInterface {

  /**
   * @param $array
   * @param $context
   *
   * @return ArrayableInterface
   */
  public static function fromArray(array $array, array $context = array());

  /**
   * @internal param bool $dry_run
   * @return array
   */
  public function toArray();

  /**
   * @return string
   */
  public function getClassName();

}
