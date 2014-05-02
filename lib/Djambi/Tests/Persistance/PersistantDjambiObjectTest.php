<?php
namespace Djambi\Tests\Persistance;

use Djambi\Persistance\ArrayableInterface;
use Djambi\GameFactories\GameFactory;
use Djambi\GameManagers\BasicGameManager;
use Djambi\GameManagers\Signal;
use Djambi\Players\HumanPlayer;
use Djambi\Tests\BaseDjambiTest;

class PersistantDjambiObjectTest extends BaseDjambiTest {

  /**
   * @param ArrayableInterface $object
   * @param array $expected_properties
   * @param array $context
   *
   * @dataProvider addObjectDataProvider
   */
  public function testObjectTransformation($object, $expected_properties, $context = array()) {
    $this->checkObjectTransformation($object, $expected_properties, $context);
  }

  /**
   * @dataProvider addObjectDataProvider
   */
  public function testObjectSerialization($object, $expected_properties, $context = array()) {
    $this->checkObjectSerialization($object, $expected_properties, $context);
  }

  public function addObjectDataProvider() {
    $object1 = HumanPlayer::createEmptyHumanPlayer();
    $signal = Signal::createSignal($object1, 'signal !');
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

}
