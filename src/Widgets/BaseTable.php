<?php
/**
 * Created by PhpStorm.
 * User: buchho
 * Date: 27/05/14
 * Time: 23:46
 */

namespace Drupal\djambi\Widgets;


abstract class BaseTable implements TableInterface {

  /** @var array */
  protected $header = array();

  /** @var array */
  protected $rows = array();

  /** @var array */
  protected $factionStats = array();

  /** @var array */
  protected $attributes;

  /** @var String */
  protected $caption;

  protected $data;

  protected function __construct() {}

  public static function build($data) {
    /** @var BaseTable $static */
    $static = new static();
    $static->setData($data)->declareHeader()->generateRows();
    return $static;
  }

  /**
   * @param $data
   *
   * @return TableInterface
   */
  protected function setData($data) {
    $this->data = $data;
    return $this;
  }

  protected function generateSingleRow($data) {
    $row = array();
    foreach ($this->header as $key => $header) {
      $function = 'build' . ucfirst($key) . 'RowData';
      $return = $this->$function($data);
      if (!is_array($return)) {
        $return = array('data' => $return);
      }
      $row[$key] = $return;
    }
    return array('data' => $row);
  }

  public function getCaption() {
    return $this->caption;
  }

  public function getAttributes() {
    return $this->attributes;
  }

  protected function returnEmptyValue() {
    return array(
      'class' => array('col--disabled'),
      'data' => '-',
    );
  }

  public function getHeader() {
    return $this->header;
  }

  public function getRows() {
    return $this->rows;
  }
}
