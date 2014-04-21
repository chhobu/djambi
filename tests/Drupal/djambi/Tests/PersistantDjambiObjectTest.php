<?php
namespace Drupal\djambi\Players {
  use Drupal\djambi\Tests\PersistantDjambiObjectTest;

  function user_load($uid) {
    return PersistantDjambiObjectTest::getMockUser();
  }
}

namespace Drupal\djambi\Tests {

  use Djambi\ArrayableInterface;
  use Djambi\GameFactory;
  use Djambi\GameManagers\BasicGameManager;
  use Djambi\GameManagers\Signal;
  use Djambi\Players\HumanPlayer;
  use Drupal\djambi\Players\Drupal8Player;

  class PersistantDjambiObjectTest extends DjambiUnitTestCase {

    const CHECK_SAME_VALUE = '---auto---';

    public static function getInfo() {
      return array(
        'name' => 'Djambi objects persistance unit Test',
        'description' => 'Test Djambi classes object to array transformation',
        'group' => 'Djambi',
      );
    }

    /**
     * @param ArrayableInterface $object
     * @param array $expected_properties
     * @param array $context
     *
     * @dataProvider addObjectDataProvider
     */
    public function testObjectTransformation($object, $expected_properties, $context = array()) {
      $array = $object->toArray();
      $this->assertEquals(count($expected_properties), count($array) - 1, "Array generated from object is not what it is expected");
      $new_object = $object::fromArray($array, $context);
      $this->compareObjectsAfterTransformation($object, $new_object, $expected_properties, $context, $array);
    }

    /**
     * @dataProvider addObjectDataProvider
     */
    public function testObjectSerialization($object, $expected_properties, $context = array()) {
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
        if (is_object($actual_value) && $actual_value instanceof ArrayableInterface && $expected_value instanceof ArrayableInterface) {
          $expected_value = $expected_value->toArray();
          $actual_value = $actual_value->toArray();
        }
        $this->assertEquals($expected_value, $actual_value, "Property \"" . $property . "\" fails persisting");
      }
    }

    public function addObjectDataProvider() {
      $object1 = HumanPlayer::createEmptyHumanPlayer();
      $signal = Signal::createSignal($object1, $this->randomName());
      $object1->setLastSignal($signal);
      $object1->useSeat();
      $values[] = array(
        $object1,
        array(
          'id' => self::CHECK_SAME_VALUE,
          'lastSignal' => $signal,
          'joined' => self::CHECK_SAME_VALUE,
          'emptySeat' => FALSE,
        ),
      );

      $object2 = HumanPlayer::createEmptyHumanPlayer();
      $values[] = array(
        $object2,
        array(
          'id' => self::CHECK_SAME_VALUE,
          'emptySeat' => TRUE,
        ),
      );

      $object3 = $signal;
      $values[] = array(
        $object3,
        array(
          'ip' => self::CHECK_SAME_VALUE,
          'time' => self::CHECK_SAME_VALUE,
          'player' => $object1,
        ),
        array('player' => $object1),
      );

      // $mock = $this->getMock('Drupal\Core\Session\AccountInterface');
      $mock = $this->getMockUser();
      $mock->expects($this->any())->method('id')->will($this->returnValue(42));
      $object4 = Drupal8Player::createEmptyHumanPlayer();
      $object4->useSeat();
      $object4->setAccount($mock);
      $values[] = array(
        $object4,
        array(
          'id' => self::CHECK_SAME_VALUE,
          'emptySeat' => FALSE,
          'joined' => self::CHECK_SAME_VALUE,
          'account' => $mock,
        ),
        array('account' => $mock),
      );

      $values[] = array(
        $object4,
        array(
          'id' => self::CHECK_SAME_VALUE,
          'emptySeat' => FALSE,
          'joined' => self::CHECK_SAME_VALUE,
          'account' => $mock,
        ),
      );

      $game_factory = new GameFactory();
      $game_factory->setMode(BasicGameManager::MODE_SANDBOX);
      $game_factory->addPlayer($object1);
      $object5 = $game_factory->createGameManager();
      $object5->play();
      $values[] = array(
        $object5,
        array(
          'id' => self::CHECK_SAME_VALUE,
          'changed' => self::CHECK_SAME_VALUE,
          'begin' => self::CHECK_SAME_VALUE,
          'mode' => BasicGameManager::MODE_SANDBOX,
          'status' => BasicGameManager::STATUS_PENDING,
          'infos' => array(),
          'disposition' => $game_factory->getDisposition(),
          'battlefield' => $object5->getBattlefield(),
        ),
      );

      return $values;
    }

    public static function getMockUser() {
      return \PHPUnit_Framework_MockObject_Generator::getMock('Drupal\Core\Session\AccountInterface');
    }

  }
}
