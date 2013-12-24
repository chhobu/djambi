<?php

namespace Djambi\Tests;

require_once 'PHP/CodeCoverage/Autoload.php';

class DjambiCodeCoverage {
  /** @var  DjambiCodeCoverage */
  protected static $instance;
  /** @var \PHP_CodeCoverage */
  protected $coverage;
  /** @var array */
  protected $params;

  protected function __construct() {}

  public function beginCoverage() {
    $this->coverage->start('Behat Test');
    return $this;
  }

  public static function initiateCoverage($params) {
    $instance = static::getInstance();
    $filter = new \PHP_CodeCoverage_Filter();
    $filter->addDirectoryToWhitelist(__DIR__ . "/../../Djambi");
    $instance->setCoverage(new \PHP_CodeCoverage(NULL, $filter));
    $instance->setParams($params);
    return $instance;
  }

  protected function setCoverage(\PHP_CodeCoverage $coverage) {
    $this->coverage = $coverage;
    return $this;
  }

  protected function setParams(array $params = NULL) {
    $this->params = $params;
    return $this;
  }

  protected function getParam($name) {
    if (isset($this->params[$name])) {
      return $this->params[$name];
    }
    return FALSE;
  }

  public static function getInstance() {
    if (is_null(self::$instance)) {
      self::$instance = new static();
    }
    return self::$instance;
  }

  public function generateCoverage() {
    try {
      $this->coverage->stop();
    }
    catch (\PHP_CodeCoverage_Exception $e) {}
    if ($path = $this->getParam('clover')) {
      $this->makeDir($path);
      $writer = new \PHP_CodeCoverage_Report_Clover();
      $writer->process($this->coverage, $path . "/behat-lib.xml");
    }
    if ($path = $this->getParam('html')) {
      $this->makeDir($path);
      $writer = new \PHP_CodeCoverage_Report_HTML();
      $writer->process($this->coverage, $path);
    }
    return $this;
  }

  protected function makeDir($path) {
    if (!is_dir($path)) {
      $oldmask = umask(0);
      mkdir($path, 0777, TRUE);
      umask($oldmask);
    }
    return $this;
  }
}
