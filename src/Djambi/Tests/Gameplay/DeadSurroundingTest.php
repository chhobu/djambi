<?php
/**
 * Created by PhpStorm.
 * User: buchho
 * Date: 13/08/14
 * Time: 19:16
 */

namespace Gameplay;


use Djambi\GameDispositions\GameDispositionsFactory;
use Djambi\GameFactories\GameFactory;
use Djambi\Gameplay\Faction;
use Djambi\Grids\BaseGrid;
use Djambi\PieceDescriptions\Leader;
use Djambi\PieceDescriptions\Militant;
use Djambi\PieceDescriptions\Necromobile;
use Djambi\Tests\BaseDjambiTest;

class DeadSurroundingTest extends BaseDjambiTest {

  const LEADER_TEAM1_START_POSITION = 'A7';
  const MILITANT1_TEAM1_START_POSITION = 'A6';
  const MILITANT2_TEAM1_START_POSITION = 'B7';
  const NECROMOBILE_TEAM1_START_POSITION = 'C5';

  const LEADER_TEAM2_START_POSITION = 'G1';
  const MILITANT1_TEAM2_START_POSITION = 'G2';
  const MILITANT2_TEAM2_START_POSITION = 'F1';
  const NECROMOBILE_TEAM2_START_POSITION = 'E3';

  const LEADER_TEAM3_START_POSITION = 'A1';

  const LEADER_TEAM4_START_POSITION = 'G7';

  const THRONE_POSITION = 'D4';

  public function setup() {
    $disposition = GameDispositionsFactory::createNewCustomDisposition();
    $disposition->setShape(BaseGrid::SHAPE_CARDINAL);
    $disposition->setDimensions(7, 7);
    $disposition->addSide(array(), Faction::STATUS_READY, array(
      new Leader(NULL, self::LEADER_TEAM1_START_POSITION),
      new Militant(1, self::MILITANT1_TEAM1_START_POSITION),
      new Militant(2, self::MILITANT2_TEAM1_START_POSITION),
      new Necromobile(NULL, self::NECROMOBILE_TEAM1_START_POSITION),
    ));
    $disposition->addSide(array(), Faction::STATUS_READY, array(
      new Leader(NULL, self::LEADER_TEAM2_START_POSITION),
      new Militant(1, self::MILITANT1_TEAM2_START_POSITION),
      new Militant(2, self::MILITANT2_TEAM2_START_POSITION),
      new Necromobile(NULL, self::NECROMOBILE_TEAM2_START_POSITION),
    ));
    $disposition->addSide(array(), Faction::STATUS_READY, array(
      new Leader(NULL, self::LEADER_TEAM3_START_POSITION),
    ));
    $disposition->addSide(array(), Faction::STATUS_READY, array(
      new Leader(NULL, self::LEADER_TEAM4_START_POSITION),
    ));
    $factory = new GameFactory();
    $factory->setDisposition($disposition->deliverDisposition());
    $this->game = $factory->createGameManager();

    $this->game->getBattlefield()->findPieceById('t2-M1')->setAlive(FALSE);
    $this->game->getBattlefield()->findPieceById('t2-M2')->setAlive(FALSE);
    $this->game->getBattlefield()->findPieceById('t2-N')->setAlive(FALSE);

    $this->assertEquals('throne', $this->game->getBattlefield()->findCellByName(self::THRONE_POSITION)->getType());
  }

  public function testDeadSurroundingAndTurnCancel() {
    $this->game->play();
    $battlefield = $this->game->getBattlefield();

    $this->doMove('t1-N', self::NECROMOBILE_TEAM2_START_POSITION, array(
      'necromobility' => array('choice' => 'F2'),
    ));

    $this->checkNewTurn('t3');
    $this->assertEquals(Faction::STATUS_SURROUNDED, $battlefield->findFactionById('t2')->getStatus());
    $this->assertEquals('t2', $battlefield->findFactionById('t2')->getControl()->getId());
    $this->assertEquals(TRUE, $battlefield->findPieceById('t2-L')->isAlive());

    $battlefield->cancelLastTurn();

    $this->checkNewTurn('t1');
    $this->assertEquals(Faction::STATUS_READY, $battlefield->findFactionById('t2')->getStatus());
  }

} 