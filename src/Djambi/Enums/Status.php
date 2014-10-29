<?php
/**
 * Created by PhpStorm.
 * User: buchho
 * Date: 28/10/14
 * Time: 22:35
 */

namespace Djambi\Enums;


use Djambi\Strings\GlossaryTerm;

class Status {
  /** @var String */
  private $value;
  /** @var GlossaryTerm */
  private $description;
  /** @var bool */
  private $new = FALSE;
  /** @var bool */
  private $pending = FALSE;
  /** @var bool */
  private $finished = FALSE;

  public function __construct($value, GlossaryTerm $description) {
    $this->value = $value;
    $this->description = $description;
  }

  /**
   * @return GlossaryTerm
   */
  public function getDescription() {
    return $this->description;
  }

  /**
   * @return String
   */
  public function getValue() {
    return $this->value;
  }

  /**
   * @return boolean
   */
  public function isFinished() {
    return $this->finished;
  }

  /**
   * @param boolean $finished
   */
  public function setFinished($finished) {
    $this->finished = $finished;
  }

  /**
   * @return boolean
   */
  public function isPending() {
    return $this->pending;
  }

  /**
   * @param boolean $pending
   */
  public function setPending($pending) {
    $this->pending = $pending;
  }

  /**
   * @return boolean
   */
  public function isNew() {
    return $this->new;
  }

  /**
   * @param boolean $new
   */
  public function setNew($new) {
    $this->new = $new;
  }

} 