<?php
/**
 * Created by PhpStorm.
 * User: buchho
 * Date: 01/05/14
 * Time: 12:13
 */

namespace Djambi\Tests\PieceDescriptions;

use Djambi\GameDispositions\GameDispositionsFactory;
use Djambi\GameFactories\GameFactory;
use Djambi\Gameplay\Faction;
use Djambi\Grids\BaseGrid;
use Djambi\PieceDescriptions\Leader;
use Djambi\PieceDescriptions\Militant;
use Djambi\PieceDescriptions\Reporter;
use Djambi\Tests\BaseDjambiTest;

class ReporterTest extends BaseDjambiTest {

  const MILITANT1_TEAM1_START_POSITION = 'B6';
  const REPORTER_TEAM1_START_POSITION = 'E3';
  const THRONE_POSITION = 'D4';
  const LEADER_TEAM1_START_POSITION = 'A7';
  const LEADER_TEAM2_START_POSITION = 'D3';
  const MILITANT1_TEAM2_START_POSITION = 'F2';
  const MILITANT2_TEAM2_START_POSITION = 'C6';

  public function setUp() {
    $disposition = GameDispositionsFactory::createNewCustomDisposition();
    $disposition->setShape(BaseGrid::SHAPE_CARDINAL);
    $disposition->setDimensions(7, 7);
    $disposition->addSide(array(), Faction::STATUS_READY, array(
      new Leader(NULL, self::LEADER_TEAM1_START_POSITION),
      new Militant(NULL, self::MILITANT1_TEAM1_START_POSITION),
      new Reporter(NULL, self::REPORTER_TEAM1_START_POSITION),
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

  public function testReporterPossibleMoves() {
    $this->game->play();
    $battlefield = $this->game->getBattlefield();
    $piece = $battlefield->findPieceById('t1-R');
    $expected_moves = explode(' ', 'E2 F3 E4 F4 D2 E1 G3 E5 E6 E7 G5 C5 C1');
    $this->checkPossibleMoves($piece, $expected_moves);
  }

  public function testReporterNormalMove() {
    $this->game->play();
    $grid = $this->game->getBattlefield();
    $piece1 = $grid->findPieceById('t1-R');
    $destination = 'C1';
    $this->doMove($piece1, $destination, NULL);

    $this->checkLog($piece1->getId() . ' : ' . self::REPORTER_TEAM1_START_POSITION . ' to ' . $destination);
    $this->checkNewTurn('t2');
    $this->checkPosition($piece1, $destination);
    $this->checkEmptyCell(self::REPORTER_TEAM1_START_POSITION);
  }

  /**
   * @dataProvider provideForbiddenDestinations
   * @expectedException \Djambi\Exceptions\DisallowedActionException
   *
   * @param $position
   */
  public function testReporterForbiddenMoves($position) {
    $this->game->play();
    $grid = $this->game->getBattlefield();
    $piece1 = $grid->findPieceById('t1-R');
    $this->doMove($piece1, $position);
  }

  public function provideForbiddenDestinations() {
    return array(
      array(self::THRONE_POSITION),
      array(self::MILITANT1_TEAM1_START_POSITION),
      array(self::MILITANT2_TEAM2_START_POSITION),
      array(self::MILITANT1_TEAM2_START_POSITION),
      array('C3'),
    );
  }

  public function testReporterCanKillLeaderInThrone() {
    $grid = $this->game->getBattlefield();
    $target = $grid->findPieceById('t2-L')->setPosition($grid->findCellByName(self::THRONE_POSITION));
    $this->game->play();

    $destination = 'D3';
    $piece = $grid->findPieceById('t1-R');
    $this->doMove($piece, $destination);

    $this->checkPosition($piece, $destination);
    $this->checkPosition($target, self::THRONE_POSITION);
    $this->assertFalse($target->isAlive());
    $this->assertEquals(Faction::STATUS_KILLED, $grid->findFactionById('t2')->getStatus());
    $this->checkGameFinished('t1');
  }

  public function testSingleReportage() {
    $this->game->play();
    $grid = $this->game->getBattlefield();

    $piece = $grid->findPieceById('t1-R');
    $destination = 'E2';
    $target = $grid->findCellByName(self::MILITANT1_TEAM2_START_POSITION)->getOccupant();
    $this->doMove($piece, $destination, NULL);

    $this->checkLog($piece->getId() . ' : ' . self::REPORTER_TEAM1_START_POSITION . ' to ' . $destination);
    $this->checkLog($target->getId() . ' killed in ' . self::MILITANT1_TEAM2_START_POSITION);
    $this->checkNewTurn('t2');
    $this->checkPosition($piece, $destination);
    $this->checkPosition($target, self::MILITANT1_TEAM2_START_POSITION);
    $this->assertFalse($target->isAlive());

    $grid->cancelLastTurn();
    $this->checkNewTurn('t1');
    $this->checkPosition($piece, self::REPORTER_TEAM1_START_POSITION);
    $this->checkPosition($target, self::MILITANT1_TEAM2_START_POSITION);
    $this->assertTrue($target->isAlive());
  }

  public function testMultipleReportage() {
    $grid = $this->game->getBattlefield();
    $leader_position = 'D2';
    $leader = $grid->findPieceById('t2-L')->setPosition($grid->findCellByName($leader_position));
    $this->game->play();

    $piece = $grid->findPieceById('t1-R');
    $destination = 'E2';
    $target = $grid->findCellByName(self::MILITANT1_TEAM2_START_POSITION)->getOccupant();
    $expected_interaction = array(
      'reportage' => array(
        'type' => 'Djambi\\Moves\\Reportage',
        'pieces_selection' => TRUE,
        'choice' => self::MILITANT1_TEAM2_START_POSITION,
        'message' => $piece->getId() . ' is on the way to reveal a massive scandal. Select the victim to focus on.',
        'expected_choices' => array(
          self::MILITANT1_TEAM2_START_POSITION,
          $leader_position,
        ),
      ),
    );
    $this->doMove($piece, $destination, $expected_interaction);

    $this->checkLog($piece->getId() . ' : ' . self::REPORTER_TEAM1_START_POSITION . ' to ' . $destination);
    $this->checkLog($target->getId() . ' killed in ' . self::MILITANT1_TEAM2_START_POSITION);
    $this->checkNewTurn('t2');
    $this->checkPosition($piece, $destination);
    $this->checkPosition($target, self::MILITANT1_TEAM2_START_POSITION);
    $this->assertFalse($target->isAlive());
    $this->checkPosition($leader, $leader_position);
    $this->assertTrue($leader->isAlive());

    $grid->cancelLastTurn();
    $this->checkNewTurn('t1');
    $this->checkPosition($piece, self::REPORTER_TEAM1_START_POSITION);
    $this->checkPosition($target, self::MILITANT1_TEAM2_START_POSITION);
    $this->assertTrue($target->isAlive());
    $this->checkPosition($leader, $leader_position);
    $this->assertTrue($leader->isAlive());
  }

  /**
   * @expectedException \Djambi\Exceptions\DisallowedActionException
   */
  public function testBadReportage() {
    $grid = $this->game->getBattlefield();
    $leader_position = 'D2';
    $grid->findPieceById('t2-L')->setPosition($grid->findCellByName($leader_position));
    $this->game->play();

    $piece = $grid->findPieceById('t1-R');
    $destination = 'E2';
    $expected_interaction = array(
      'reportage' => array(
        'choice' => self::THRONE_POSITION,
      ),
    );
    $this->doMove($piece, $destination, $expected_interaction);
  }

}
