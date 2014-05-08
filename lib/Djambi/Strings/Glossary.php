<?php
/**
 * Created by PhpStorm.
 * User: buchho
 * Date: 01/05/14
 * Time: 00:20
 */

namespace Djambi\Strings;


class Glossary {
  const ASSASSIN = "Assassin";
  const DIPLOMATE = "Diplomat";
  const LEADER = "Leader";
  const MILITANT = "Militant";
  const NECROMOBILE = "Necromobile";
  const REPORTER = "Reporter";

  const BLUE = "Blue";
  const GREEN = "Green";
  const RED = "Red";
  const YELLOW = "Yellow";

  const EXCEPTION_MANIPULATION_BAD_DESTINATION = "Attempt to place manipulated piece (%piece_id) into an occupied cell (%location).";
  const EXCEPTION_MOVE_DISALLOWED = "Attempt to move a piece (%piece_id) to an unauthorized location (%location).";
  const EXCEPTION_MOVE_ILLOGIC = "Attempt to choose piece destination before piece selection phase.";
  const EXCEPTION_MOVE_UNMOVABLE = "The piece %piece_id is not movable.";
  const EXCEPTION_MOVE_UNCONTROLLED = "You are not controlling the piece %piece_id.";
  const EXCEPTION_KILL_WRONG_GRAVE = "Attempt to bury a corpse into an invalid location (%location).";
  const EXCEPTION_REPORTAGE_BAD_VICTIM_CHOICE = "Attempt to make a reportage in %location without required authorizations.";
  const EXCEPTION_PIECE_NOT_FOUND = "Piece with id @piece not found.";
  const EXCEPTION_CELL_NOT_FOUND = "Cell with name @name not found.";

  /** @var Glossary */
  protected static $instance;
  protected $translaterHandler = 'strtr';

  protected function __construct() {}

  public function getGlossaryTerms() {
    $reflect = new \ReflectionClass(get_class($this));
    return $reflect->getConstants();
  }

  public static function getInstance() {
    if (is_null(self::$instance)) {
      self::$instance = new self();
    }
    return self::$instance;
  }

  public function setTranslaterHandler($translater) {
    $this->translaterHandler = $translater;
  }

  public function displayTerm(GlossaryTerm $term) {
    $args = is_array($term->getArgs()) ? $term->getArgs() : array();
    return call_user_func($this->translaterHandler, $term->getString(), $args);
  }

}
