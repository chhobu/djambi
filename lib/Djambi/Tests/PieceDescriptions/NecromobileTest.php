<?php
/**
 * Created by PhpStorm.
 * User: buchho
 * Date: 01/05/14
 * Time: 21:37
 */

namespace Djambi\Tests\PieceDescriptions;


use Djambi\GameDispositions\GameDispositionsFactory;
use Djambi\GameFactories\GameFactory;
use Djambi\Gameplay\Faction;
use Djambi\Grids\BaseGrid;
use Djambi\PieceDescriptions\Leader;
use Djambi\PieceDescriptions\Militant;
use Djambi\PieceDescriptions\Necromobile;
use Djambi\Tests\BaseDjambiTest;

class NecromobileTest extends BaseDjambiTest {

  const MILITANT1_TEAM1_START_POSITION = 'B6';
  const NECRO_TEAM1_START_POSITION = 'E3';
  const THRONE_POSITION = 'D4';
  const LEADER_TEAM1_START_POSITION = 'A7';
  const LEADER_TEAM2_START_POSITION = 'D3';
  const LEADER_TEAM3_START_POSITION = 'A1';
  const MILITANT1_TEAM2_START_POSITION = 'F2';
  const MILITANT_DEAD_START_POSITION = 'C5';

  public function setUp() {
    $disposition = GameDispositionsFactory::createNewCustomDisposition();
    $disposition->setShape(BaseGrid::SHAPE_CARDINAL);
    $disposition->setDimensions(7, 7);
    $disposition->addSide(array(), Faction::STATUS_READY, array(
      new Leader(NULL, self::LEADER_TEAM1_START_POSITION),
      new Militant(NULL, self::MILITANT1_TEAM1_START_POSITION),
      new Necromobile(NULL, self::NECRO_TEAM1_START_POSITION),
    ));
    $disposition->addSide(array(), Faction::STATUS_READY, array(
      new Leader(NULL, self::LEADER_TEAM2_START_POSITION),
      new Militant(1, self::MILITANT1_TEAM2_START_POSITION),
      new Militant(2, self::MILITANT_DEAD_START_POSITION),
    ));
    $disposition->addSide(array(), Faction::STATUS_READY, array(
      new Leader(NULL, self::LEADER_TEAM3_START_POSITION),
    ));
    $factory = new GameFactory();
    $factory->setDisposition($disposition->deliverDisposition());
    $this->game = $factory->createGameManager();
    $this->game->getBattlefield()->findPieceById('t2-M2')->setAlive(FALSE);

    $this->assertEquals('throne', $this->game->getBattlefield()->findCellByName(self::THRONE_POSITION)->getType());
  }

  public function testNecromobilePossibleMoves() {
    $this->game->play();
    $battlefield = $this->game->getBattlefield();
    $piece = $battlefield->findPieceById('t1-N');
    $expected_moves = explode(' ', 'E2 F3 E4 F4 D2 E1 G3 E5 E6 E7 G5 C5 C1');
    $this->checkPossibleMoves($piece, $expected_moves);
  }

  public function testNecromobileNormalMove() {
    $this->game->play();
    $grid = $this->game->getBattlefield();
    $piece1 = $grid->findPieceById('t1-N');
    $destination = 'C1';
    $this->doMove($piece1, $destination, NULL);

    $this->checkNewTurn('t2');
    $this->checkPosition($piece1, $destination);
    $this->checkEmptyCell(self::NECRO_TEAM1_START_POSITION);
  }

  /**
   * @dataProvider provideForbiddenDestinations
   * @expectedException \Djambi\Exceptions\DisallowedActionException
   */
  public function testNecromobileForbiddenMoves($position) {
    $this->game->play();
    $grid = $this->game->getBattlefield();
    $piece1 = $grid->findPieceById('t1-N');
    $this->doMove($piece1, $position);
  }

  public function provideForbiddenDestinations() {
    return array(
      array(self::THRONE_POSITION),
      array(self::MILITANT1_TEAM1_START_POSITION),
      array(self::MILITANT1_TEAM2_START_POSITION),
      array('B4'),
      array('A2'),
    );
  }

  public function testNecromobileThroneEvacuation() {
    $grid = $this->game->getBattlefield();
    $destination = self::THRONE_POSITION;
    $target = $grid->findPieceById('t3-L')
      ->setPosition($grid->findCellByName($destination))
      ->setAlive(FALSE);
    $this->game->play();

    $piece = $grid->findPieceById('t1-N');
    $bury_in = 'D5';
    $evacuate = 'B4';
    $expected_interactions = array(
      'necromobility' => array(
        'type' => 'Djambi\\Moves\\Necromobility',
        'choice' => $bury_in,
      ),
      'evacuation' => array(
        'type' => 'Djambi\\Moves\\ThroneEvacuation',
        'expected_choices' => explode(' ', 'A1 E3 E4 C4 E5 C3 F4 G4 B4 A4 F6 G7 B2'),
        'choice' => $evacuate,
      ),
    );
    $this->doMove($piece, $destination, $expected_interactions);

    $this->checkPosition($piece, $evacuate);
    $this->checkPosition($target, $bury_in);
    $this->assertFalse($target->isAlive());
    $this->checkNewTurn('t2');
  }

  public function testNecromobility() {
    $this->game->play();
    $grid = $this->game->getBattlefield();

    $piece = $grid->findPieceById('t1-N');
    $destination = self::MILITANT_DEAD_START_POSITION;
    $target = $grid->findCellByName($destination)->getOccupant();
    $this->assertNotEquals(NULL, $target);
    $bury_in = 'A2';
    $this->doMove($piece, $destination, array(
      'necromobility' => array(
        'type' => 'Djambi\\Moves\\Necromobility',
        'choice' => $bury_in,
        'forbidden_choices' => array(
          self::MILITANT1_TEAM2_START_POSITION,
          self::LEADER_TEAM2_START_POSITION,
          self::LEADER_TEAM1_START_POSITION,
          self::MILITANT_DEAD_START_POSITION,
          self::LEADER_TEAM3_START_POSITION,
          self::THRONE_POSITION,
          self::MILITANT1_TEAM1_START_POSITION,
        ),
      ),
    ));

    $this->checkNewTurn('t2');
    $this->checkPosition($piece, $destination);
    $this->checkPosition($target, $bury_in);
    $this->assertFalse($target->isAlive());

    $grid->cancelLastTurn();
    $this->checkNewTurn('t1');
    $this->checkPosition($piece, self::NECRO_TEAM1_START_POSITION);
    $this->checkPosition($target, $destination);
    $this->assertFalse($target->isAlive());
  }

  /**
   * @expectedException \Djambi\Exceptions\DisallowedActionException
   */
  public function testBadNecromobility() {
    $this->game->play();
    $grid = $this->game->getBattlefield();

    $piece = $grid->findPieceById('t1-N');
    $destination = self::MILITANT_DEAD_START_POSITION;
    $this->doMove($piece, $destination, array(
      'necromobility' => array('choice' => self::THRONE_POSITION),
    ));
  }

}
