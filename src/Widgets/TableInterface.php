<?php
/**
 * Created by PhpStorm.
 * User: buchho
 * Date: 27/05/14
 * Time: 23:48
 */

namespace Drupal\djambi\Widgets;


interface TableInterface {

  /**
   * @return array
   */
  public function getRows();

  /**
   * @return array
   */
  public function getHeader();

  /**
   * @return array
   */
  public function getCaption();

  /**
   * @return array
   */
  public function getAttributes();

  /**
   * @return $this
   */
  public function generateRows();

  /**
   * @return $this
   */
  public function declareHeader();

  /**
   * @param mixed $data
   *
   * @return TableInterface
   */
  public static function build($data);
}
