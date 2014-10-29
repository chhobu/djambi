<?php
/**
 * Created by PhpStorm.
 * User: buchho
 * Date: 01/05/14
 * Time: 12:36
 */

namespace Djambi\Tests;
require_once 'bootstrap.php';

use Djambi\Enums\StatusEnum;
use Djambi\GameManagers\PlayableGameInterface;
use Djambi\Gameplay\Cell;
use Djambi\Gameplay\Faction;
use Djambi\Gameplay\Piece;
use Djambi\Persistance\ArrayableInterface;
use Djambi\Strings\GlossaryTerm;

abstract class BaseDjambiTest extends \PHPUnit_Framework_TestCase {

  const CHECK_SAME_VALUE = '---auto---';

  /** @var PlayableGameInterface */
  protected $game;
  /** @var GlossaryTerm[] */
  protected $log = array();

  protected function checkPossibleMoves(Piece $piece, $expected_moves) {
    $diff = array_diff($piece->getAllowableMovesNames(), $expected_moves);
    $this->assertEmpty($diff, "Some allowable moves were not expected : " . implode(", ", $diff));
    $diff2 = array_diff($expected_moves, $piece->getAllowableMovesNames());
    $this->assertEmpty($diff2, "Some expected moves are not not allowed : " . implode(", ", $diff2));
  }

  protected function checkNewTurn($faction_id) {
    $playing_faction_id = $this->game->getBattlefield()
      ->getPlayingFaction()
      ->getId();
    $this->assertEquals(StatusEnum::STATUS_PENDING, $this->game->getStatus());
    $this->assertEquals($faction_id, $playing_faction_id, "Current playing faction is " . $playing_faction_id);
  }

  protected function checkPlayOrder($expected) {
    $play_order = $this->game->getBattlefield()->getPlayOrder();
    $this->assertEquals($expected, array_values($play_order), "Play order (" . implode(", ", $play_order) . ") was not what was expected");
  }

  protected function checkPosition($piece_id, $position) {
    $piece = $this->game->getBattlefield()->findPieceById($piece_id);
    $this->assertEquals($position, $piece->getPosition()->getName());
    $occupant = $this->game->getBattlefield()
      ->findCellByName($position)
      ->getOccupant();
    $this->assertNotEquals(NULL, $occupant);
    $this->assertEquals($piece->getId(), $occupant->getId());
  }

  protected function checkEmptyCell($position) {
    $this->assertEquals(NULL, $this->game->getBattlefield()
        ->findCellByName($position)
        ->getOccupant());
  }

  protected function checkGameStatus($status) {
    $this->assertEquals($status, $this->game->getStatus());
  }

  protected function checkGameFinished($winner) {
    $grid = $this->game->getBattlefield();
    $this->checkGameStatus(StatusEnum::STATUS_FINISHED);
    $this->assertEquals(NULL, $grid->getPlayingFaction());
    $this->assertEquals(NULL, $grid->getCurrentTurn());
    if (!is_array($winner)) {
      $this->assertEquals(Faction::STATUS_WINNER, $grid->findFactionById($winner)
          ->getStatus());
    }
    else {
      foreach ($winner as $faction_id) {
        $this->assertEquals(Faction::STATUS_DRAW, $grid->findFactionById($faction_id)
            ->getStatus());
      }
    }
  }

  /**
   * @param String $piece_id
   * @param String $destination
   * @param array $target_interactions
   * @param bool $is_completed
   *
   * @throws \Djambi\Exceptions\DisallowedActionException
   * @throws \Djambi\Exceptions\IllogicMoveException
   */
  protected function doMove($piece_id, $destination, $target_interactions = array(), $is_completed = TRUE) {
    $this->log = array();
    $grid = $this->game->getBattlefield();
    $piece = $grid->findPieceById($piece_id);
    $move = $grid->getCurrentTurn()->getMove();
    $move->selectPiece($piece);
    $move->executeChoice($grid->findCellByName($destination));
    $interactions = $move->getInteractions();
    if (empty($target_interactions)) {
      $this->assertEmpty($interactions, "The move trigger some interactions that were not expected");
    }
    else {
      $this->assertNotEmpty($interactions, "The move did not trigger any interactions");
      $nb_cells = 0;
      foreach ($grid->getCells() as $cell) {
        if ($cell->isEnabled()) {
          $nb_cells++;
        }
      }
      $allowed_keys = array(
        'choice',
        'type',
        'expected_choices',
        'forbidden_choices',
        'pieces_selection',
        'message',
      );
      foreach ($target_interactions as $expected) {
        foreach (array_keys($expected) as $key) {
          if (!in_array($key, $allowed_keys)) {
            $this->fail("Unexpected expected move result asertion : "
              . $key . " (must be one of this values : " . implode(", ", $allowed_keys) . ")");
          }
        }
        $interaction = $move->getFirstInteraction();
        $this->assertNotEmpty($interaction, "The move did not trigger all expected interactions");
        if (!empty($expected['type'])) {
          $this->assertInstanceOf($expected['type'], $interaction);
        }
        $possible_choices = array();
        foreach ($interaction->findPossibleChoices()
                   ->getPossibleChoices() as $choice) {
          $possible_choices[] = $choice->getName();
        }
        if (!empty($expected['message'])) {
          $this->assertEquals($expected['message'], $interaction->getMessage()
              ->__toString());
        }
        if (!empty($expected['pieces_selection'])) {
          $this->assertEquals($interaction->isDealingWithPiecesOnly(), $expected['pieces_selection']);
        }
        if (!empty($expected['expected_choices'])) {
          $diff1 = array_diff($possible_choices, $expected['expected_choices']);
          $this->assertEmpty($diff1, "Some possible choices were not expected : " . implode(', ', $diff1));
          $diff2 = array_diff($expected['expected_choices'], $possible_choices);
          $this->assertEmpty($diff2, "Some expected choices are not possible : " . implode(', ', $diff2));
        }
        if (!empty($expected['forbidden_choices'])) {
          $expected['forbidden_choices'] = array_unique($expected['forbidden_choices']);
          $intersect = array_intersect($possible_choices, $expected['forbidden_choices']);
          $this->assertEmpty($intersect, "Some possible choices are not forbidden : " . implode(', ', $intersect));
          $this->assertEquals($nb_cells, count($possible_choices) + count($expected['forbidden_choices']), "Some forbidden choices were forgotten.");
        }
        $interaction->executeChoice($grid->findCellByName($expected['choice']));
      }
    }
    $this->assertEquals($is_completed, $move->isCompleted());
    $past_turn = end($this->game->getBattlefield()->getPastTurns());
    $move->log($this->log, $past_turn);
  }

  /**
   * @param ArrayableInterface $object
   * @param array $expected_properties
   * @param array $context
   *
   * @return \stdClass
   */
  protected function checkObjectTransformation($object, $expected_properties, $context = array()) {
    $converted_object = $object->toArray();
    $this->assertEquals(count($expected_properties), count($converted_object) - 1, "Array generated from object is not what it is expected");
    $new_object = $object::fromArray($converted_object, $context);
    $this->compareObjectsAfterTransformation($object, $new_object, $expected_properties, $converted_object);
    return $new_object;
  }

  protected function checkObjectSerialization($object, $expected_properties) {
    $string = serialize($object);
    $new_object = unserialize($string);
    $this->compareObjectsAfterTransformation($object, $new_object, $expected_properties);
    return $new_object;
  }

  /**
   * @param ArrayableInterface $object
   * @param ArrayableInterface $new_object
   * @param array $expected_properties
   * @param null $converted_object
   */
  protected function compareObjectsAfterTransformation($object, $new_object, $expected_properties, $converted_object = NULL) {
    $this->assertInstanceOf($object->getClassName(), $new_object);
    $reflection = new \ReflectionClass($object->getClassName());
    foreach ($expected_properties as $property => $expected_value) {
      if (!is_null($converted_object)) {
        $this->assertArrayHasKey($property, $converted_object);
      }
      $reflection_property = $reflection->getProperty($property);
      $reflection_property->setAccessible(TRUE);
      $actual_value = $reflection_property->getValue($new_object);
      if ($expected_value == self::CHECK_SAME_VALUE) {
        $expected_value = $reflection_property->getValue($object);
      }
      if (is_array($expected_value)) {
        foreach ($expected_value as $key => $expected_array) {
          if (is_object($expected_array) && $expected_array instanceof ArrayableInterface) {
            $converted_array = $expected_array->toArray();
            $actual_array = $actual_value[$key];
            if ($actual_array instanceof ArrayableInterface) {
              $actual_array = $actual_array->toArray();
            }
            $this->assertEquals($converted_array, $actual_array);
          }
          else {
            $this->assertEquals($expected_value[$key], $actual_value[$key]);
          }
        }
        continue;
      }
      elseif (is_object($actual_value)) {
        if ($actual_value instanceof ArrayableInterface && $expected_value instanceof ArrayableInterface) {
          $expected_value = $expected_value->toArray();
          $actual_value = $actual_value->toArray();
        }
        else {
          $this->assertInstanceOf(get_class($expected_value), $actual_value);
          if ($expected_value instanceof Cell) {
            $expected_value = $expected_value->getName();
            $actual_value = $actual_value->getName();
          }
          else {
            $this->fail(sprintf("Cannot compare object property '%s' from class '%s'.",
              $property, get_class($expected_value)));
          }
        }
      }
      $this->assertEquals($expected_value, $actual_value, sprintf("Property '%s' from class '%s' fails persisting.",
        $property, get_class($object)));
    }
  }

  protected function checkLog($string) {
    foreach ($this->log as $log) {
      if ($string == $log->__toString()) {
        return;
      }
    }
    $this->fail(sprintf("String '%s' not found in log.", $string));
  }

}
