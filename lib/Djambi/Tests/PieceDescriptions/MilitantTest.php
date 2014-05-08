<?php
/**
 * Created by PhpStorm.
 * User: buchho
 * Date: 26/04/14
 * Time: 01:37
 */

namespace Djambi\Tests\PieceDescriptions;

use Djambi\GameDispositions\GameDispositionsFactory;
use Djambi\GameFactories\GameFactory;
use Djambi\Gameplay\Faction;
use Djambi\Grids\BaseGrid;
use Djambi\PieceDescriptions\Leader;
use Djambi\PieceDescriptions\Militant;
use Djambi\Tests\BaseDjambiTest;

class MilitantTest extends BaseDjambiTest {

  const MILITANT1_TEAM1_START_POSITION = 'B6';
  const MILITANT2_TEAM1_START_POSITION = 'E3';
  const THRONE_POSITION = 'D4';
  const LEADER_TEAM2_START_POSITION = 'D3';
  const MILITANT1_TEAM2_START_POSITION = 'F2';

  public function setUp() {
    $disposition = GameDispositionsFactory::createNewCustomDisposition();
    $disposition->setShape(BaseGrid::SHAPE_CARDINAL);
    $disposition->setDimensions(7, 7);
    $disposition->addSide(array(), Faction::STATUS_READY, array(
      new Leader(NULL, 'A7'),
      new Militant(1, self::MILITANT1_TEAM1_START_POSITION),
      new Militant(2, self::MILITANT2_TEAM1_START_POSITION),
    ));
    $disposition->addSide(array(), Faction::STATUS_READY, array(
      new Leader(NULL, self::LEADER_TEAM2_START_POSITION),
      new Militant(1, self::MILITANT1_TEAM2_START_POSITION),
      new Militant(2, 'C6'),
    ));
    $factory = new GameFactory();
    $factory->setDisposition($disposition->deliverDisposition());
    $this->game = $factory->createGameManager();
    $this->game->getBattlefield()->findPieceById('t2-M2')->setAlive(FALSE);

    $this->assertEquals('throne', $this->game->getBattlefield()->findCellByName(self::THRONE_POSITION)->getType());
  }

  public function testMilitantPossibleMoves() {
    $this->game->play();

    $battlefield = $this->game->getBattlefield();
    $piece1 = $battlefield->findPieceById('t1-M1');
    $expected_moves = array('A6', 'B7', 'B5', 'B4', 'A5', 'C5', 'C7');
    $this->checkPossibleMoves($piece1, $expected_moves);

    $piece2 = $battlefield->findPieceById('t1-M2');
    $expected_moves = explode(' ', 'E1 E2 E4 E5 D2 D3 C1 F3 G3 C5 F2 F4 G5');
    $this->checkPossibleMoves($piece2, $expected_moves);

    $piece3 = $battlefield->findPieceById('t2-M1');
    $this->assertEmpty($piece3->getAllowableMoves());
  }

  public function testMilitantNormalMove() {
    $this->game->play();
    $grid = $this->game->getBattlefield();
    $piece1 = $grid->findPieceById('t1-M1');
    $destination = 'A6';
    $this->doMove($piece1, $destination, NULL);

    $this->checkNewTurn('t2');
    $this->checkPosition($piece1, $destination);
    $this->checkEmptyCell(self::MILITANT1_TEAM1_START_POSITION);
  }

  /**
   * @expectedException \Djambi\Exceptions\DisallowedActionException
   */
  public function testMilitantForbiddenMoves() {
    $this->game->play();
    $grid = $this->game->getBattlefield();
    $piece1 = $grid->findPieceById('t1-M1');
    $this->doMove($piece1, 'D2');
  }

  /**
   * @expectedException \Djambi\Exceptions\DisallowedActionException
   */
  public function testMilitantShouldNotKillLeaderInThrone() {
    $grid = $this->game->getBattlefield();
    $grid->findPieceById('t2-L')->setPosition($grid->findCellByName(self::THRONE_POSITION));
    $this->game->play();

    $piece1 = $grid->findPieceById('t1-M1');
    $this->doMove($piece1, self::THRONE_POSITION);
  }

  public function testMilitantCanKillAndBury() {
    $this->game->play();
    $grid = $this->game->getBattlefield();

    $piece = $grid->findPieceById('t1-M2');
    $destination = self::MILITANT1_TEAM2_START_POSITION;
    $target = $grid->findCellByName($destination)->getOccupant();
    $this->assertNotEquals(NULL, $target);
    $bury_in = 'A1';
    $this->doMove($piece, $destination, array(
      'murder' => array(
        'type' => 'Djambi\\Moves\\Murder',
        'choice' => $bury_in,
        'forbidden_choices' => explode(' ', "C6 A7 B6 F2 C6 D4 D3"),
      ),
    ));

    $this->checkNewTurn('t2');
    $this->checkPosition($piece, $destination);
    $this->checkEmptyCell(self::MILITANT2_TEAM1_START_POSITION);
    $this->assertFalse($target->isAlive());
    $this->checkPosition($target, $bury_in);
  }

  public function testMilitantCanKillLeaderOutsideThroneAndWin() {
    $this->game->play();
    $grid = $this->game->getBattlefield();

    $piece = $grid->findPieceById('t1-M2');
    $destination = self::LEADER_TEAM2_START_POSITION;
    $target = $grid->findCellByName($destination)->getOccupant();
    $this->assertNotEquals(NULL, $target);
    $bury_in = 'A1';
    $this->doMove($piece, $destination, array(
      'murder' => array(
        'type' => 'Djambi\\Moves\\Murder',
        'choice' => $bury_in,
      ),
    ));

    $this->checkPosition($piece, $destination);
    $this->checkPosition($target, $bury_in);
    $this->checkEmptyCell(self::MILITANT2_TEAM1_START_POSITION);
    $this->assertFalse($target->isAlive());
    $this->assertEquals(Faction::STATUS_KILLED, $grid->findFactionById('t2')->getStatus());
    $this->checkGameFinished('t1');
  }

  /**
   * @expectedException \Djambi\Exceptions\DisallowedActionException
   */
  public function testMilitantBadKill() {
    $this->game->play();
    $grid = $this->game->getBattlefield();

    $piece = $grid->findPieceById('t1-M2');
    $destination = self::MILITANT1_TEAM2_START_POSITION;
    $bury_in = 'C6';
    $this->doMove($piece, $destination, array(
      'murder' => array(
        'type' => 'Djambi\\Moves\\Murder',
        'choice' => $bury_in,
      ),
    ));
  }
}