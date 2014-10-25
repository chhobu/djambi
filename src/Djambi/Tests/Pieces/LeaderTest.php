<?php
/**
 * Created by PhpStorm.
 * User: buchho
 * Date: 02/05/14
 * Time: 14:24
 */

namespace Djambi\Tests\Pieces;

use Djambi\GameDispositions\GameDispositionsFactory;
use Djambi\GameFactories\GameFactory;
use Djambi\Gameplay\Faction;
use Djambi\Grids\BaseGrid;
use Djambi\PieceDescriptions\Leader;
use Djambi\PieceDescriptions\Militant;
use Djambi\Tests\BaseDjambiTest;

class LeaderTest extends BaseDjambiTest {

  const MILITANT1_TEAM1_START_POSITION = 'A7';
  const MILITANT2_TEAM1_START_POSITION = 'E3';
  const THRONE_POSITION = 'D4';
  const LEADER_TEAM1_START_POSITION = 'B6';
  const LEADER_TEAM2_START_POSITION = 'D3';
  const MILITANT1_TEAM2_START_POSITION = 'B3';
  const MILITANT2_TEAM2_START_POSITION = 'C6';

  public function setUp() {
    $disposition = GameDispositionsFactory::createNewCustomDisposition();
    $disposition->setShape(BaseGrid::SHAPE_CARDINAL);
    $disposition->setDimensions(7, 7);
    $disposition->addSide(array(), Faction::STATUS_READY, array(
      new Leader(NULL, self::LEADER_TEAM1_START_POSITION),
      new Militant(1, self::MILITANT1_TEAM1_START_POSITION),
      new Militant(2, self::MILITANT2_TEAM1_START_POSITION),
    ));
    $disposition->addSide(array(), Faction::STATUS_READY, array(
      new Leader(NULL, self::LEADER_TEAM2_START_POSITION),
      new Militant(1, self::MILITANT1_TEAM2_START_POSITION),
      new Militant(2, self::MILITANT2_TEAM2_START_POSITION),
    ));
    $factory = new GameFactory();
    $factory->setDisposition($disposition->deliverDisposition());
    $this->game = $factory->createGameManager();
    $this->game->getBattlefield()->findPieceById('t2-M2')->setAlive(FALSE);

    $this->assertEquals('throne', $this->game->getBattlefield()->findCellByName(self::THRONE_POSITION)->getType());
  }

  public function testLeaderPossibleMoves() {
    $this->game->play();
    $battlefield = $this->game->getBattlefield();
    $piece = $battlefield->findPieceById('t1-L');
    $expected_moves = explode(' ', 'B5 B7 A6 C7 A5 B4 B3 D4 C5');
    $this->checkPossibleMoves($piece, $expected_moves);
  }

  public function testLeaderNormalMove() {
    $this->game->play();
    $piece1 = 't1-L';
    $destination = 'B4';
    $this->doMove($piece1, $destination, NULL);

    $this->checkNewTurn('t2');
    $this->checkPosition($piece1, $destination);
    $this->checkEmptyCell(self::LEADER_TEAM1_START_POSITION);
  }

  /**
   * @dataProvider provideForbiddenDestinations
   * @expectedException \Djambi\Exceptions\DisallowedActionException
   *
   * @param $position
   */
  public function testLeaderForbiddenMoves($position) {
    $this->game->play();
    $this->doMove('t1-L', $position);
  }

  public function provideForbiddenDestinations() {
    return array(
      array(self::MILITANT2_TEAM2_START_POSITION),
      array(self::MILITANT1_TEAM1_START_POSITION),
      array('C3'),
    );
  }

  public function testLeaderKill() {
    $this->game->play();
    $grid = $this->game->getBattlefield();

    $piece = 't1-L';
    $destination = self::MILITANT1_TEAM2_START_POSITION;
    $target = $grid->findCellByName($destination)->getOccupant();
    $placement = 'A2';
    $this->doMove($piece, $destination, array(
      'manipulation' => array(
        'type' => 'Djambi\\Moves\\Murder',
        'choice' => $placement,
        'forbidden_choices' => array(
          self::THRONE_POSITION,
          self::MILITANT1_TEAM2_START_POSITION,
          self::MILITANT1_TEAM1_START_POSITION,
          self::LEADER_TEAM2_START_POSITION,
          self::MILITANT2_TEAM1_START_POSITION,
          self::MILITANT2_TEAM2_START_POSITION,
        ),
      ),
    ));

    $this->checkNewTurn('t2');
    $this->checkPosition($piece, $destination);
    $this->checkPosition($target->getId(), $placement);
    $this->checkEmptyCell(self::LEADER_TEAM1_START_POSITION);
    $this->assertFalse($target->isAlive());
  }

  public function testLeaderKillThroneLeader() {
    $grid = $this->game->getBattlefield();
    $target_id = 't2-L';
    $target = $grid->findPieceById($target_id)->setPosition($grid->findCellByName(self::THRONE_POSITION));
    $this->game->play();

    $piece = 't1-L';
    $placement = 'A2';
    $this->doMove($piece, self::THRONE_POSITION, array(
      'manipulation' => array(
        'type' => 'Djambi\\Moves\\Murder',
        'choice' => $placement,
      ),
    ));

    $this->checkPosition($piece, self::THRONE_POSITION);
    $this->checkPosition($target_id, $placement);
    $this->checkEmptyCell(self::LEADER_TEAM1_START_POSITION);
    $this->assertFalse($target->isAlive());
    $this->checkGameFinished('t1');
    $this->assertEquals(Faction::STATUS_KILLED, $grid->findFactionById('t2')->getStatus());
  }

}
