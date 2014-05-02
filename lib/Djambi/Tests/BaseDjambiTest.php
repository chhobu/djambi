<?php
/**
 * Created by PhpStorm.
 * User: buchho
 * Date: 01/05/14
 * Time: 12:36
 */

namespace Djambi\Tests;


use Djambi\GameManagers\BasicGameManager;
use Djambi\Gameplay\Faction;
use Djambi\Gameplay\Piece;
use Djambi\Persistance\ArrayableInterface;

abstract class BaseDjambiTest extends \PHPUnit_Framework_TestCase {

  const CHECK_SAME_VALUE = '---auto---';

  /** @var BasicGameManager */
  protected $game;

  protected function checkPossibleMoves(Piece $piece, $expected_moves) {
    $diff = array_diff($piece->getAllowableMovesNames(), $expected_moves);
    $this->assertEmpty($diff, "Some allowable moves were not expected : " . implode(", ", $diff));
    $diff2 = array_diff($expected_moves, $piece->getAllowableMovesNames());
    $this->assertEmpty($diff2, "Some expected moves are not not allowed : " . implode(", ", $diff2));
  }

  protected function checkNewTurn($faction_id) {
    $playing_faction_id = $this->game->getBattlefield()->getPlayingFaction()->getId();
    $this->assertEquals(BasicGameManager::STATUS_PENDING, $this->game->getStatus());
    $this->assertEquals($faction_id, $playing_faction_id, "Current playing faction is " . $playing_faction_id);
  }

  protected function checkPosition(Piece $piece, $position) {
    $this->assertEquals($position, $piece->getPosition()->getName());
    $occupant = $this->game->getBattlefield()->findCellByName($position)->getOccupant();
    $this->assertNotEquals(NULL, $occupant);
    $this->assertEquals($piece->getId(), $occupant->getId());
  }

  protected function checkEmptyCell($position) {
    $this->assertEquals(NULL, $this->game->getBattlefield()->findCellByName($position)->getOccupant());
  }

  protected function checkGameStatus($status) {
    $this->assertEquals($status, $this->game->getStatus());
  }

  protected function checkGameFinished($winner) {
    $grid = $this->game->getBattlefield();
    $this->checkGameStatus(BasicGameManager::STATUS_FINISHED);
    $this->assertEquals(NULL, $grid->getPlayingFaction());
    $this->assertEquals(Faction::STATUS_WINNER, $grid->findFactionById($winner)->getStatus());
  }

  protected function doMove($piece, $destination, $expected_interactions = array()) {
    $grid = $this->game->getBattlefield();
    $move = $grid->getCurrentMove();
    $move->selectPiece($piece);
    $move->executeChoice($grid->findCellByName($destination));
    $interactions = $move->getInteractions();
    if (empty($expected_interactions)) {
      $this->assertEmpty($interactions, "The move trigger some interactions that were not expected");
    }
    else {
      $this->assertNotEmpty($interactions, "The move did not trigger any interactions");
      foreach ($expected_interactions as $expected) {
        $interaction = $move->getFirstInteraction();
        $this->assertNotEmpty($interaction, "The move did not trigger all expected interactions");
        if (!empty($expected['type'])) {
          $this->assertInstanceOf($expected['type'], $interaction);
        }
        $possible_choices = array();
        foreach ($interaction->findPossibleChoices()->getPossibleChoices() as $choice) {
          $possible_choices[] = $choice->getName();
        }
        if (!empty($expected['expected_choices'])) {
          $diff1 = array_diff($possible_choices, $expected['expected_choices']);
          $this->assertEmpty($diff1, "Some possible choices were not expected : " . implode(', ', $diff1));
          $diff2 = array_diff($expected['expected_choices'], $possible_choices);
          $this->assertEmpty($diff2, "Some expected choices are not possible : " . implode(', ', $diff2));
        }
        if (!empty($expected['forbidden_choices'])) {
          $intersect = array_intersect($possible_choices, $expected['forbidden_choices']);
          $this->assertEmpty($intersect, "Some possible choices were not forbidden : " . implode(', ', $intersect));
          $intersect2 = array_intersect($expected['forbidden_choices'], $possible_choices);
          $this->assertEmpty($intersect2, "Some forbidden choices are possible : " . implode(', ', $intersect2));
        }
        $interaction->executeChoice($grid->findCellByName($expected['choice']));
      }
    }
    $this->assertTrue($move->isCompleted());
  }

  /**
   * @param ArrayableInterface $object
   * @param array $expected_properties
   * @param array $context
   */
  protected function checkObjectTransformation($object, $expected_properties, $context = array()) {
    $array = $object->toArray();
    $this->assertEquals(count($expected_properties), count($array) - 1, "Array generated from object is not what it is expected");
    $new_object = $object::fromArray($array, $context);
    $this->compareObjectsAfterTransformation($object, $new_object, $expected_properties, $context, $array);
  }

  protected function checkObjectSerialization($object, $expected_properties, $context = array()) {
    $string = serialize($object);
    $new_object = unserialize($string);
    $this->compareObjectsAfterTransformation($object, $new_object, $expected_properties, $context);
  }

  /**
   * @param ArrayableInterface $object
   * @param ArrayableInterface $new_object
   * @param array $expected_properties
   * @param array $context
   * @param array $array
   */
  protected function compareObjectsAfterTransformation($object, $new_object, $expected_properties, $context, $array = NULL) {
    $this->assertInstanceOf($object->getClassName(), $new_object);
    $reflection = new \ReflectionClass($object->getClassName());
    foreach ($expected_properties as $property => $expected_value) {
      if (!is_null($array)) {
        $this->assertArrayHasKey($property, $array);
      }
      $reflection_property = $reflection->getProperty($property);
      $reflection_property->setAccessible(TRUE);
      $actual_value = $reflection_property->getValue($new_object);
      if ($expected_value == self::CHECK_SAME_VALUE) {
        $expected_value = $reflection_property->getValue($object);
      }
      if (is_array($expected_value)) {
        foreach ($expected_value as $key => $expected_array_element) {
          if (is_object($expected_array_element) && $expected_array_element instanceof ArrayableInterface) {
            $expected_converted_element = $expected_array_element->toArray();
            $actual_array_element = $actual_value[$key];
            if ($actual_array_element instanceof ArrayableInterface) {
              $actual_array_element = $actual_array_element->toArray();
            }
            $this->assertEquals($expected_converted_element, $actual_array_element);
          }
          else {
            $this->assertEquals($expected_value[$key], $actual_value[$key]);
          }
        }
        continue;
      }
      elseif (is_object($actual_value) && $actual_value instanceof ArrayableInterface && $expected_value instanceof ArrayableInterface) {
        $expected_value = $expected_value->toArray();
        $actual_value = $actual_value->toArray();
      }
      $this->assertEquals($expected_value, $actual_value, "Property \"" . $property . "\" from class \"" . get_class($object) . "\" fails persisting");
    }
  }

}
