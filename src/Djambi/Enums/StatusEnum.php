<?php
/**
 * Created by PhpStorm.
 * User: buchho
 * Date: 28/10/14
 * Time: 22:12
 */

namespace Djambi\Enums;


use Djambi\Strings\Glossary;
use Djambi\Strings\GlossaryTerm;

class StatusEnum {

  const STATUS_PENDING = 'pending';
  const STATUS_FINISHED = 'finished';
  const STATUS_DRAW_PROPOSAL = 'draw_proposal';
  const STATUS_RECRUITING = 'recruiting';

  /** @var Status[] */
  private static $statuses = array();

  private function __construct() {}

  private function __clone() {}

  protected static function init() {
    if (empty(self::$statuses)) {
      $pending = new Status(self::STATUS_PENDING, new GlossaryTerm(Glossary::STATUS_PENDING_DESCRIPTION));
      $pending->setPending(TRUE);
      $statuses[$pending->getValue()] = $pending;

      $finished = new Status(self::STATUS_FINISHED, new GlossaryTerm(Glossary::STATUS_FINISHED_DESCRIPTION));
      $finished->setFinished(TRUE);
      $statuses[$finished->getValue()] = $finished;

      $draw = new Status(self::STATUS_DRAW_PROPOSAL, new GlossaryTerm(Glossary::STATUS_DRAW_PROPOSAL_DESCRIPTION));
      $draw->setPending(TRUE);
      $statuses[$draw->getValue()] = $draw;

      $recruiting = new Status(self::STATUS_RECRUITING, new GlossaryTerm(Glossary::STATUS_RECRUITING_DESCRIPTION));
      $recruiting->setNew(TRUE);
      $statuses[$recruiting->getValue()] = $recruiting;

      self::$statuses = $statuses;
    }
  }

  /**
   * @param String $status
   * @throws StatusNotFoundException
   * @return Status
   */
  public static function getStatus($status) {
    self::init();
    if (isset(self::$statuses[$status])) {
      return self::$statuses[$status];
    }
    throw new StatusNotFoundException(sprintf("Status with value '%s' not found", $status));
  }

  /**
   * @return Status[]
   */
  public static function getAllStatuses() {
    self::init();
    return self::$statuses;
  }

  /**
   * @return Status[]
   */
  public static function getNewStatuses() {
    self::init();
    $filtered_statuses = array();
    foreach (self::$statuses as $status) {
      if ($status->isNew()) {
        $filtered_statuses[] = $status;
      }
    }
    return $filtered_statuses;
  }

  /**
   * @return Status[]
   */
  public static function getPendingStatuses() {
    self::init();
    $filtered_statuses = array();
    foreach (self::$statuses as $status) {
      if ($status->isPending()) {
        $filtered_statuses[] = $status;
      }
    }
    return $filtered_statuses;
  }

  /**
   * @return Status[]
   */
  public static function getFinishedStatuses() {
    self::init();
    $filtered_statuses = array();
    foreach (self::$statuses as $status) {
      if ($status->isFinished()) {
        $filtered_statuses[] = $status;
      }
    }
    return $filtered_statuses;
  }

}