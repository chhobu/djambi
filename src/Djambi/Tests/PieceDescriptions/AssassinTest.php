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
use Djambi\PieceDescriptions\Assassin;
use Djambi\PieceDescriptions\Leader;
use Djambi\PieceDescriptions\Militant;
use Djambi\Tests\BaseDjambiTest;

class AssassinTest extends BaseDjambiTest {

  const MILITANT1_TEAM1_START_POSITION = 'B6';
  const ASSASSIN_TEAM1_START_POSITION = 'E3';
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
      new Assassin(NULL, self::ASSASSIN_TEAM1_START_POSITION),
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

  public function testAssassinPossibleMoves() {
    $this->game->play();
    $battlefield = $this->game->getBattlefield();
    $piece = $battlefield->findPieceById('t1-A');
    $expected_moves = explode(' ', 'E2 F3 E4 D3 F2 F4 D2 E1 G3 E5 E6 E7 G5 C5 C1');
    $this->checkPossibleMoves($piece, $expected_moves);
  }

  public function testAssassinNormalMove() {
    $this->game->play();
    $grid = $this->game->getBattlefield();
    $piece1 = $grid->findPieceById('t1-A');
    $destination = 'C1';
    $this->doMove($piece1, $destination, NULL);

    $this->checkNewTurn('t2');
    $this->checkPosition($piece1, $destination);
    $this->checkEmptyCell(self::ASSASSIN_TEAM1_START_POSITION);
  }

  /**
   * @expectedException \Djambi\Exceptions\DisallowedActionException
   */
  public function testAssassinForbiddenMoves() {
    $this->game->play();
    $grid = $this->game->getBattlefield();
    $piece1 = $grid->findPieceById('t1-A');
    $this->doMove($piece1, 'C4');
  }

  public function testAssassinCanKillLeaderInThroneAndEvacuate() {
    $grid = $this->game->getBattlefield();
    $destination = self::THRONE_POSITION;
    $target = $grid->findPieceById('t2-L')->setPosition($grid->findCellByName($destination));
    $this->game->play();

    $piece = $grid->findPieceById('t1-A');
    $evacuate = 'D6';
    $expected_interactions = array(
      'evacuation' => array(
        'expected_choices' => explode(' ', 'D6 A1 D3 E4 D5 C4 E5 C5 C3 D2 D1 F4 G4 D7 B4 A4 F6 G7 B2'),
        'choice' => $evacuate,
      ),
    );
    $this->doMove($piece, $destination, $expected_interactions);

    $this->checkPosition($piece, $evacuate);
    $this->checkPosition($target, self::ASSASSIN_TEAM1_START_POSITION);
    $this->assertFalse($target->isAlive());
    $this->assertEquals(Faction::STATUS_KILLED, $grid->findFactionById('t2')->getStatus());
    $this->checkGameFinished('t1');
  }

  public function testAssassinKill() {
    $this->game->play();
    $grid = $this->game->getBattlefield();

    $piece = $grid->findPieceById('t1-A');
    $destination = self::MILITANT1_TEAM2_START_POSITION;
    $target = $grid->findCellByName($destination)->getOccupant();
    $this->assertNotEquals(NULL, $target);
    $this->doMove($piece, $destination, NULL);

    $this->checkNewTurn('t2');
    $this->checkPosition($piece, $destination);
    $this->checkPosition($target, self::ASSASSIN_TEAM1_START_POSITION);
    $this->assertFalse($target->isAlive());

    $grid->cancelLastTurn();
    $this->checkNewTurn('t1');
    $this->checkPosition($piece, self::ASSASSIN_TEAM1_START_POSITION);
    $this->checkPosition($target, $destination);
    $this->assertTrue($target->isAlive());
  }

}
