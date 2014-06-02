<?php
/**
 * Created by PhpStorm.
 * User: buchho
 * Date: 02/05/14
 * Time: 12:08
 */

namespace Djambi\Tests\PieceDescriptions;

use Djambi\GameDispositions\GameDispositionsFactory;
use Djambi\GameFactories\GameFactory;
use Djambi\Gameplay\Faction;
use Djambi\Gameplay\Turn;
use Djambi\Grids\BaseGrid;
use Djambi\Moves\Manipulation;
use Djambi\Moves\Move;
use Djambi\PieceDescriptions\Diplomat;
use Djambi\PieceDescriptions\Leader;
use Djambi\PieceDescriptions\Militant;
use Djambi\Tests\BaseDjambiTest;

class DiplomatTest extends BaseDjambiTest {

  const MILITANT1_TEAM1_START_POSITION = 'B6';
  const DIPLOMAT_TEAM1_START_POSITION = 'E3';
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
      new Diplomat(NULL, self::DIPLOMAT_TEAM1_START_POSITION),
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

  public function testDiplomatPossibleMoves() {
    $this->game->play();
    $battlefield = $this->game->getBattlefield();
    $piece = $battlefield->findPieceById('t1-D');
    $expected_moves = explode(' ', 'D3 F2 E2 F3 E4 F4 D2 E1 G3 E5 E6 E7 G5 C5 C1');
    $this->checkPossibleMoves($piece, $expected_moves);
  }

  public function testDiplomatNormalMove() {
    $this->game->play();
    $grid = $this->game->getBattlefield();
    $piece1 = $grid->findPieceById('t1-D');
    $destination = 'C1';
    $this->doMove($piece1, $destination, NULL);

    $this->checkNewTurn('t2');
    $this->checkPosition($piece1, $destination);
    $this->checkEmptyCell(self::DIPLOMAT_TEAM1_START_POSITION);
  }

  /**
   * @dataProvider provideForbiddenDestinations
   * @expectedException \Djambi\Exceptions\DisallowedActionException
   */
  public function testDiplomatForbiddenMoves($position) {
    $this->game->play();
    $grid = $this->game->getBattlefield();
    $piece1 = $grid->findPieceById('t1-D');
    $this->doMove($piece1, $position);
  }

  public function provideForbiddenDestinations() {
    return array(
      array(self::THRONE_POSITION),
      array(self::MILITANT2_TEAM2_START_POSITION),
      array(self::MILITANT1_TEAM1_START_POSITION),
      array('C3'),
    );
  }

  public function testDiplomatCanManipulateLeaderInThroneAndEvacuate() {
    $grid = $this->game->getBattlefield();
    $target = $grid->findPieceById('t2-L')->setPosition($grid->findCellByName(self::THRONE_POSITION));
    $this->game->play();

    $destination = self::THRONE_POSITION;
    $evacuation = 'A4';
    $manipulation = 'A2';
    $piece = $grid->findPieceById('t1-D');
    $this->doMove($piece, $destination, array(
      'manipulation' => array(
        'type' => 'Djambi\\Moves\\Manipulation',
        'choice' => $manipulation,
      ),
      'evacuation' => array(
        'type' => 'Djambi\\Moves\\ThroneEvacuation',
        'choice' => $evacuation,
      ),
    ));

    $this->checkPosition($piece, $evacuation);
    $this->checkPosition($target, $manipulation);
    $this->assertTrue($target->isAlive());
    $this->checkNewTurn('t2');

    $grid->cancelLastTurn();
    $this->checkNewTurn('t1');
    $this->checkPosition($piece, self::DIPLOMAT_TEAM1_START_POSITION);
    $this->checkPosition($target, $destination);
    $this->assertTrue($target->isAlive());
    $this->checkEmptyCell($manipulation);
    $this->checkEmptyCell($evacuation);
  }

  public function testDiplomatManipulation() {
    $this->game->play();
    $grid = $this->game->getBattlefield();

    $piece = $grid->findPieceById('t1-D');
    $destination = self::MILITANT1_TEAM2_START_POSITION;
    $target = $grid->findCellByName($destination)->getOccupant();
    $placement = 'A2';
    $this->doMove($piece, $destination, array(
      'manipulation' => array(
        'type' => 'Djambi\\Moves\\Manipulation',
        'choice' => $placement,
        'forbidden_choices' => array(
          self::THRONE_POSITION,
          self::MILITANT1_TEAM2_START_POSITION,
          self::MILITANT1_TEAM1_START_POSITION,
          self::LEADER_TEAM1_START_POSITION,
          self::LEADER_TEAM2_START_POSITION,
          self::MILITANT2_TEAM2_START_POSITION,
        ),
      ),
    ));

    $this->checkNewTurn('t2');
    $this->checkPosition($piece, $destination);
    $this->checkPosition($target, $placement);
    $this->checkEmptyCell(self::DIPLOMAT_TEAM1_START_POSITION);
    $this->assertTrue($target->isAlive());

    $grid->cancelLastTurn();
    $this->checkNewTurn('t1');
    $this->checkPosition($piece, self::DIPLOMAT_TEAM1_START_POSITION);
    $this->checkPosition($target, $destination);
    $this->checkEmptyCell($placement);
    $this->assertTrue($target->isAlive());
  }

  /**
   * @expectedException \Djambi\Exceptions\DisallowedActionException
   */
  public function testBadManipulation() {
    $this->game->play();
    $grid = $this->game->getBattlefield();

    $piece = $grid->findPieceById('t1-D');
    $destination = self::MILITANT1_TEAM2_START_POSITION;
    $placement = self::MILITANT1_TEAM2_START_POSITION;
    $this->doMove($piece, $destination, array(
      'manipulation' => array(
        'type' => 'Djambi\\Moves\\Manipulation',
        'choice' => $placement,
      ),
    ));
  }

  public function testMovePersistance() {
    $grid = $this->game->getBattlefield();
    $grid->findPieceById('t2-L')->setPosition($grid->findCellByName(self::THRONE_POSITION));
    $this->game->play();

    $destination = self::THRONE_POSITION;
    $manipulation = 'A2';
    $piece = $grid->findPieceById('t1-D');
    $this->doMove($piece, $destination, array(
      'manipulation' => array(
        'choice' => $manipulation,
      ),
    ), FALSE);
    $this->assertEquals('t1', $grid->getPlayingFaction()->getId());

    $move = $grid->getCurrentTurn()->getMove();
    $properties = array(
      'selectedPiece' => $piece,
      'actingFaction' => $piece->getFaction(),
      'destination' => $grid->findCellByName($destination),
      'phase' => Move::PHASE_PIECE_INTERACTIONS,
      'interactions' => self::CHECK_SAME_VALUE,
      'origin' => $grid->findCellByName(self::DIPLOMAT_TEAM1_START_POSITION),
    );
    $context = array('battlefield' => $grid);
    /** @var Move $saved_move */
    $saved_move = $this->checkObjectTransformation($move, $properties, $context);
    $this->checkObjectSerialization($move, $properties);

    /** @var Manipulation $interaction1 */
    $interaction1 = current($saved_move->getInteractions());
    $this->assertEquals($manipulation, $interaction1->getChoice()->getName());
    $this->assertEquals(NULL, $saved_move->getFirstInteraction()->getChoice());

    $grid->getCurrentTurn()->resetMove();
    $this->doMove($piece, $destination, array(
      'manipulation' => array('choice' => $manipulation),
      'evacuation' => array(
        'expected_choices' => explode(', ', 'D5, D3, E4, C4, E3, E5, C5, C3, D2, D1, F4, G4, D6, D7, B4, A4, F6, G7, B2, A1'),
        'choice' => 'D5',
      ),
    ));
    $turns = $grid->getPastTurns();
    $saved_turn_array = end($turns);
    $saved_move = Turn::fromArray($saved_turn_array, $context)->getMove();
    $this->assertEquals(NULL, $saved_move->getFirstInteraction());
    $interactions = $saved_move->getInteractions();
    $this->assertEquals('D5', $interactions[1]->getChoice()->getName());
  }

}
