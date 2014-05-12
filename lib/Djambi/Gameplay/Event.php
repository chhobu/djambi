<?php
/**
 * Created by PhpStorm.
 * User: buchho
 * Date: 09/05/14
 * Time: 00:23
 */

namespace Djambi\Gameplay;


use Djambi\Persistance\PersistantDjambiObject;
use Djambi\Strings\GlossaryTerm;

class Event extends PersistantDjambiObject {
  const SEVERITY_MAJOR = 'major';
  const SEVERITY_NORMAL = 'normal';
  const SEVERITY_MINOR = 'minor';

  /** @var GlossaryTerm */
  protected $description;
  /** @var String */
  protected $severity;
  /** @var int */
  protected $time;

  protected function prepareArrayConversion() {
    parent::prepareArrayConversion();
  }

  public static function fromArray(array $array, array $context = array()) {

  }
}
