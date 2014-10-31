<?php
/**
 * Created by PhpStorm.
 * User: buchho
 * Date: 09/05/14
 * Time: 00:23
 */

namespace Djambi\Gameplay;


use Djambi\Persistance\ArrayableInterface;
use Djambi\Persistance\PersistantDjambiTrait;
use Djambi\Strings\GlossaryTerm;

class Event implements ArrayableInterface {

  use PersistantDjambiTrait;

  const LOG_LEVEL_MAJOR = 'major';
  const LOG_LEVEL_NORMAL = 'normal';
  const LOG_LEVEL_MINOR = 'minor';

  /** @var GlossaryTerm */
  protected $description;
  /** @var String */
  protected $logLevel;
  /** @var int */
  protected $time;
  /** @var BaseChange[] */
  protected $changes;

  protected function prepareArrayConversion() {
    $attributes = array(
      'description',
      'logLevel',
      'time',
    );
    if (!empty($this->changes)) {
      $attributes[] = 'changes';
    }
    $this->addPersistantProperties($attributes);
    return $this;
  }

  public static function fromArray(array $array, array $context = array()) {
    $description = call_user_func($array['description']['className'] . '::fromArray', $array['description'], $context);
    $event = new static($description, $array['logLevel'], $array['time']);
    if (!empty($array['changes'])) {
      foreach ($array['changes'] as $change) {
        $event->logChange(call_user_func($change['className'] . '::fromArray', $change, $context));
      }
    }
    return $event;
  }

  public function __construct(GlossaryTerm $description, $severity = NULL, $time = NULL) {
    $this->logLevel = is_null($severity) ? static::LOG_LEVEL_NORMAL : $severity;
    $this->time = is_null($time) ? time() : $time;
    $this->description = $description;
  }

  public function getDescription() {
    return $this->description;
  }

  public function getLogLevel() {
    return $this->logLevel;
  }

  public function getTime() {
    return $this->time;
  }

  /**
   * @return BaseChange[]
   */
  public function getChanges() {
    return $this->changes;
  }

  public function logChange(BaseChange $change) {
    $this->changes[] = $change;
    return $this;
  }

  public function executeChange(BaseChange $change) {
    $change->execute();
    $this->logChange($change);
    return $this;
  }

  public function revertChanges() {
    /** @var BaseChange $change */
    if (!empty($this->getChanges())) {
      foreach (array_reverse($this->getChanges()) as $change) {
        $change->revert();
      }
    }
    return $this;
  }
}
