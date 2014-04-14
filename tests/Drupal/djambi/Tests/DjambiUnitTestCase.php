<?php
/**
 * Created by PhpStorm.
 * User: buchho
 * Date: 13/04/14
 * Time: 22:24
 */

namespace Drupal\djambi\Tests;

spl_autoload_register(function($class) {
  if (substr($class, 0, 7) == 'Djambi\\') {
    include __DIR__ . '/../../../../lib/' . str_replace('\\', '/', $class) . '.php';
  }
});

use Drupal\Tests\UnitTestCase;

class DjambiUnitTestCase extends UnitTestCase {

}
