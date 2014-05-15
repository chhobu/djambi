<?php
/**
 * Created by PhpStorm.
 * User: buchho
 * Date: 01/05/14
 * Time: 00:20
 */

namespace Djambi\Strings;


class Glossary {
  const PIECE_ASSASSIN = "Assassin";
  const PIECE_DIPLOMATE = "Diplomat";
  const PIECE_LEADER = "Leader";
  const PIECE_MILITANT = "Militant";
  const PIECE_NECROMOBILE = "Necromobile";
  const PIECE_REPORTER = "Reporter";

  const SIDE_BLUE = "Blue";
  const SIDE_GREEN = "Green";
  const SIDE_RED = "Red";
  const SIDE_YELLOW = "Yellow";

  const EXCEPTION_MANIPULATION_BAD_DESTINATION = "Attempt to place manipulated piece (%piece_id) into an occupied cell (%location).";
  const EXCEPTION_MOVE_DISALLOWED = "Attempt to move a piece (%piece_id) to an unauthorized location (%location).";
  const EXCEPTION_MOVE_ILLOGIC = "Attempt to choose piece destination before piece selection phase.";
  const EXCEPTION_MOVE_UNMOVABLE = "The piece %piece_id is not movable.";
  const EXCEPTION_MOVE_UNCONTROLLED = "You are not controlling the piece %piece_id.";
  const EXCEPTION_KILL_WRONG_GRAVE = "Attempt to bury a corpse into an invalid location (%location).";
  const EXCEPTION_REPORTAGE_BAD_VICTIM_CHOICE = "Attempt to make a reportage in %location without required authorizations.";
  const EXCEPTION_PIECE_NOT_FOUND = "Piece with id @piece not found.";
  const EXCEPTION_CELL_NOT_FOUND = "Cell with name @name not found.";

  const EVENT_WINNER = "Faction !faction_id wins !";
  const EVENT_DRAW = "No winner, this is a boring and disappointing draw.";
  const EVENT_THIS_IS_THE_END = "Game over...";
  const EVENT_SURROUNDED = "The piece !piece_id is surrounded by dead pieces and cannot access to power anymore.";
  const EVENT_COMEBACK_AFTER_SURROUND = "Faction !faction_id can come back and play again this game, as their leader is not surrounded by dead pieces anymore.";
  const EVENT_ELIMINATION = "The piece !piece_id is now totally useless and has nothing to do anymore in this game.";
  const EVENT_CHANGING_SIDE = "The remaining partisans of faction !faction_id1 are now supporting faction !faction_id2 leader.";
  const EVENT_INDEPENDANT_SIDE = "The remaining partisans of faction !faction_id1 have decided to stop their support to the disapointing faction !faction_id2 leader.";
  const EVENT_FACTION_GAME_OVER = "Game over for faction !faction_id.";
  const EVENT_SKIPPED_TURN = "The faction !faction_id skipped his turn (!nb skipped turn(s) since game beginning).";
  const EVENT_WITHDRAWAL = "The faction !faction_id are too desperate to continue, they have decided to withdraw from the game. Cowards !";
  const EVENT_COMEBACK_AFTER_WITHDRAW = "The !faction_id side got some new hope and is back in the game.";
  const EVENT_DRAW_PROPOSAL = "The !faction_id side has called for a draw.";
  const EVENT_DRAW_ACCEPTED = "The !faction_id side accepted the draw proposal.";
  const EVENT_DRAW_REJECTED = "The !faction_id side rejected the draw proposal.";
  const EVENT_LEADER_KILLED = "The !faction_id side partisans are mourning the tragic loss of their mentor !piece_id, victim of a tragic murder.";
  const EVENT_NEW_ROUND = "Round !round begins.";
  const EVENT_DIPLOMAT_GOLDEN_MOVE = "!piece_id has achieved a diplomat golden move !";
  const EVENT_ASSASSIN_GOLDEN_MOVE = "!piece_id has achieved an assassin golden move !";
  const EVENT_THRONE_ACCESS = "!piece_id is now the great and beloved ruler of this battlefield.";
  const EVENT_THRONE_MURDER = "!piece_id has been killed during his reign !";
  const EVENT_THRONE_RETREAT = "!piece_id's reign of despotism and terror is now over.";
  const EVENT_THRONE_MANIPULATION = "!piece_id has been placed to throne against his will through the action of a skilled diplomat.";
  const EVENT_THRONE_MAUSOLEUM = "!piece_id body has been placed in the throne case mausoleum. Let's worship his memory.";
  const EVENT_MOVE_COMPLETED = "A move was made by faction !faction_id.";

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
