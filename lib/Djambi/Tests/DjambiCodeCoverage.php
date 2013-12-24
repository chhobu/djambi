<?php

namespace Djambi\Tests;

require_once 'PHP/CodeCoverage/Autoload.php';

class DjambiCodeCoverage {
  /** @var  DjambiCodeCoverage */
  protected static $instance;
  /** @var \PHP_CodeCoverage */
  protected $coverage;

  protected function __construct() {
    $filter = new \PHP_CodeCoverage_Filter();
    $filter->addDirectoryToWhitelist(__DIR__ . "/../../Djambi");
    $this->coverage = new \PHP_CodeCoverage(NULL, $filter);
  }

  public function beginCoverage() {
    $this->coverage->start('Behat Test');
    return $this;
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
    $writer = new \PHP_CodeCoverage_Report_Clover();
    $path = __DIR__ . "/../../../../../../../sites/djambi_test/tests/build/coverage";
    if (!is_dir($path)) {
      $oldmask = umask(0);
      mkdir($path, 0777, TRUE);
      umask($oldmask);
    }
    $writer->process($this->coverage, $path . "/behat-lib.xml");
    $writer = new \PHP_CodeCoverage_Report_HTML();
    $writer->process($this->coverage, $path . '/behat-lib-html');
    return $this;
  }
}
