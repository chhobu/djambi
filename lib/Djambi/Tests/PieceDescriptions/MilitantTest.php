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
use Djambi\GameManagers\BasicGameManager;
use Djambi\Gameplay\Faction;
use Djambi\Grids\BaseGrid;
use Djambi\PieceDescriptions\Leader;
use Djambi\PieceDescriptions\Militant;

class MilitantTest extends \PHPUnit_Framework_TestCase {

  const MILITANT1_TEAM1_START_POSITION = 'B6';
  const MILITANT2_TEAM1_START_POSITION = 'E3';
  const THRONE_POSITION = 'D4';
  const LEADER_TEAM2_START_POSITION = 'D3';
  const MILITANT1_TEAM2_START_POSITION = 'F2';

  /** @var BasicGameManager */
  protected $game;

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
    $this->game->getBattlefield()->getPieceById('B-M2')->setAlive(FALSE);
    $this->game->play();

    $this->assertEquals('throne', $this->game->getBattlefield()->findCellByName(self::THRONE_POSITION)->getType());
  }

  public function testMilitantPossibleMoves() {
    $battlefield = $this->game->getBattlefield();
    $piece1 = $battlefield->getPieceById('R-M1');
    $expected_moves = array('A6', 'B7', 'B5', 'B4', 'A5', 'C5', 'C7');
    $this->assertEmpty(array_diff($piece1->getAllowableMovesNames(), $expected_moves));
    $piece2 = $battlefield->getPieceById('R-M2');
    $expected_moves = explode(' ', 'E1 E2 E4 E5 D2 D3 C1 F3 G3 C5 F2 F4 G5');
    $this->assertEmpty(array_diff($piece2->getAllowableMovesNames(), $expected_moves));
    $piece3 = $battlefield->getPieceById('B-M1');
    $this->assertEmpty($piece3->getAllowableMoves());
  }

  public function testMilitantNormalMove() {
    $grid = $this->game->getBattlefield();
    $piece1 = $grid->getPieceById('R-M1');
    $move = $grid->getCurrentMove();
    $move->selectPiece($piece1);
    $destination = 'A6';
    $move->moveSelectedPiece($grid->findCellByName($destination));

    // Changement de tour
    $this->assertEquals('B', $grid->getPlayingFaction()->getId());
    // Changement de case
    $this->assertEquals($destination, $piece1->getPosition()->getName());
    $this->assertEquals('R-M1', $grid->findCellByName($destination)->getOccupant()->getId());
    // Case d'origine libre
    $this->assertNull($grid->findCellByName(self::MILITANT1_TEAM1_START_POSITION)->getOccupant());
  }

  /**
   * @expectedException \Djambi\Exceptions\DisallowedActionException
   */
  public function testMilitantForbiddenMoves() {
    $grid = $this->game->getBattlefield();
    $piece1 = $grid->getPieceById('R-M1');
    $move = $grid->getCurrentMove();
    $move->selectPiece($piece1);
    $destination = 'D2';
    $move->moveSelectedPiece($grid->findCellByName($destination));
  }

  /**
   * @expectedException \Djambi\Exceptions\DisallowedActionException
   */
  public function testMilitantShouldNotKillLeaderInThrone() {
    $grid = $this->game->getBattlefield();
    $grid->getPieceById('B-L')->setPosition($grid->findCellByName(self::THRONE_POSITION));

    $piece1 = $grid->getPieceById('R-M1');
    $move = $grid->getCurrentMove();
    $move->selectPiece($piece1);
    $move->moveSelectedPiece($grid->findCellByName(self::THRONE_POSITION));
  }

  public function testMilitantCanKillAndBury() {
    $grid = $this->game->getBattlefield();

    $piece = $grid->getPieceById('R-M2');
    $move = $grid->getCurrentMove();
    $move->selectPiece($piece);
    $destination = self::MILITANT1_TEAM2_START_POSITION;
    $target = $grid->findCellByName($destination)->getOccupant();
    $this->assertNotNull($target);
    $move->moveSelectedPiece($grid->findCellByName($destination));

    $bury_in = 'A1';
    $this->assertInstanceOf('Djambi\\Moves\\Murder', $move->getFirstInteraction());
    $choices = $move->getFirstInteraction()->findPossibleChoices()->getPossibleChoices();
    $bury_possible_choices = array();
    foreach ($choices as $choice) {
      $bury_possible_choices[] = $choice->getName();
    }
    $disallowed_choices = explode(' ', "C6 A7 B6 F2 C6 D4 D3");
    $this->assertEmpty(array_intersect($bury_possible_choices, $disallowed_choices));
    $move->getFirstInteraction()->executeChoice($grid->findCellByName($bury_in));

    // Changement de tour
    $this->assertEquals('B', $grid->getPlayingFaction()->getId());
    // Changement de case
    $this->assertEquals($destination, $piece->getPosition()->getName());
    $this->assertEquals('R-M2', $grid->findCellByName($destination)->getOccupant()->getId());
    // Case d'origine libre
    $this->assertNull($grid->findCellByName(self::MILITANT2_TEAM1_START_POSITION)->getOccupant());
    // Pièce cible tuée
    $this->assertFalse($target->isAlive());
    // Pièce cible enterrée
    $this->assertEquals($bury_in, $target->getPosition()->getName());
  }

  public function testMilitantCanKillLeaderOutsideThroneAndWin() {
    $grid = $this->game->getBattlefield();

    $piece = $grid->getPieceById('R-M2');
    $move = $grid->getCurrentMove();
    $move->selectPiece($piece);
    $destination = self::LEADER_TEAM2_START_POSITION;
    $target = $grid->findCellByName($destination)->getOccupant();
    $this->assertNotNull($target);
    $move->moveSelectedPiece($grid->findCellByName($destination));

    $bury_in = 'A1';
    $this->assertInstanceOf('Djambi\\Moves\\Murder', $move->getFirstInteraction());
    $choices = $move->getFirstInteraction()->findPossibleChoices()->getPossibleChoices();
    $bury_possible_choices = array();
    foreach ($choices as $choice) {
      $bury_possible_choices[] = $choice->getName();
    }
    $disallowed_choices = explode(' ', "C6 A7 B6 F2 C6 D4 D3");
    $this->assertEmpty(array_intersect($bury_possible_choices, $disallowed_choices));
    $move->getFirstInteraction()->executeChoice($grid->findCellByName($bury_in));

    // Changement de tour
    $this->assertEquals(BasicGameManager::STATUS_FINISHED, $this->game->getStatus());
    // Changement de case
    $this->assertEquals($destination, $piece->getPosition()->getName());
    $this->assertEquals('R-M2', $grid->findCellByName($destination)->getOccupant()->getId());
    // Case d'origine libre
    $this->assertNull($grid->findCellByName(self::MILITANT2_TEAM1_START_POSITION)->getOccupant());
    // Pièce cible tuée
    $this->assertFalse($target->isAlive());
    // Pièce cible enterrée
    $this->assertEquals($bury_in, $target->getPosition()->getName());
    // Gagné !
    $this->assertEquals(Faction::STATUS_WINNER, $grid->getFactionById('R')->getStatus());
    $this->assertEquals(Faction::STATUS_KILLED, $grid->getFactionById('B')->getStatus());
    $this->assertNull($grid->getPlayingFaction());
  }
}
