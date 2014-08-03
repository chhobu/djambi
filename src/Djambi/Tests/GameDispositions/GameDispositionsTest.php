<?php
/**
 * Created by PhpStorm.
 * User: buchho
 * Date: 26/04/14
 * Time: 00:24
 */

namespace Djambi\Tests\GameDispositions;


use Djambi\GameDispositions\GameDispositionsFactory;
use Djambi\GameFactories\GameFactory;
use Djambi\GameManagers\BaseGameManager;
use Djambi\GameManagers\GameManagerInterface;
use Djambi\Gameplay\Faction;

class GameDispositionsTest extends \PHPUnit_Framework_TestCase {

  /** @var GameFactory */
  private $gameFactory;

  /** @var GameManagerInterface */
  private $game;

  public function setUp() {
    $this->gameFactory = new GameFactory();
    $this->getGameFactory()->setMode(BaseGameManager::MODE_SANDBOX);
    parent::setUp();
  }

  public function testGameDisposition4std() {
    // When I initiate a new 4 players game in a standard grid
    $this->getGameFactory()->setDisposition(GameDispositionsFactory::useDisposition('4std'));
    $this->setGame($this->getGameFactory()->createGameManager());
    $this->getGame()->play();
    // Then I should have a 81 squares chessboard
    $this->assertNbCells(81);
    // And I should have 4 sides
    $this->assertNbFactions(4);
    // And I should have 9 pieces per side
    $this->assertNbPiecePerFaction(9);
    // And I should have the following pieces positions:
    $expected_positions = array(
      'leader' => array(array('A9', 'I9', 'I1', 'A1')),
      'assassin' => array(array('B9', 'I8', 'H1', 'A2')),
      'reporter' => array(array('A8', 'H9', 'I2', 'B1')),
      'diplomate' => array(array('B8', 'H8', 'H2', 'B2')),
      'necromobile' => array(array('C7', 'G7', 'G3', 'C3')),
      'militant' => array(
        array('B7', 'H7', 'H3', 'B3'),
        array('C9', 'G9', 'G1', 'C1'),
        array('C8', 'G8', 'G2', 'C2'),
        array('A7', 'I7', 'I3', 'A3'),
      ),
    );
    $this->assertPiecesPositions($expected_positions);
  }

  public function testGameDisposition3hex() {
    $this->getGameFactory()->setDisposition(GameDispositionsFactory::useDisposition('3hex'));
    $this->setGame($this->getGameFactory()->createGameManager());
    $this->getGame()->play();

    $this->assertNbCells(61);
    $this->assertNbFactions(3);
    $this->assertNbPiecePerFaction(9);
    $expected_positions = array(
      'leader' => array(array('A5', 'G9', 'G1')),
      'assassin' => array(array('A6', 'G8', 'F1')),
      'reporter' => array(array('A4', 'F9', 'G2')),
      'diplomate' => array(array('B5', 'F8', 'F2')),
      'necromobile' => array(array('C5', 'F7', 'F3')),
      'militant' => array(
        array('B3', 'G7', 'E1'),
        array('B4', 'H7', 'E2'),
        array('B6', 'E8', 'G3'),
        array('B7', 'E9', 'H3'),
      ),
    );
    $this->assertPiecesPositions($expected_positions);
  }

  public function testGameDisposition2mini() {
    $this->getGameFactory()->setDisposition(GameDispositionsFactory::useDisposition('2mini'));
    $this->setGame($this->getGameFactory()->createGameManager());
    $this->getGame()->play();

    $this->assertNbCells(49);
    $this->assertNbFactions(2);
    $this->assertNbPiecePerFaction(4);
    $expected_positions = array(
      'leader' => array(array('A7', 'G1')),
      'assassin' => array(array('B6', 'F2')),
      'diplomate' => array(array('B6', 'F2')),
      'reporter' => array(array('B6', 'F2')),
      'militant' => array(
        array('B7', 'F1'),
        array('A6', 'G2'),
      ),
    );
    $this->assertPiecesPositions($expected_positions);
  }

  public function testGameDisposition2std() {
    $this->getGameFactory()->setDisposition(GameDispositionsFactory::useDisposition('2std'));
    $this->setGame($this->getGameFactory()->createGameManager());
    $this->getGame()->play();

    $this->assertNbCells(81);
    $this->assertNbFactions(4);
    $this->assertNbPiecePerFaction(9);

    $this->assertEquals($this->getGame()->getBattlefield()->findFactionById('t2')->getStatus(), Faction::STATUS_VASSALIZED);
    $this->assertEquals($this->getGame()->getBattlefield()->findFactionById('t2')->getControl()->getId(), 't1');
    $this->assertEquals($this->getGame()->getBattlefield()->findFactionById('t4')->getStatus(), Faction::STATUS_VASSALIZED);
    $this->assertEquals($this->getGame()->getBattlefield()->findFactionById('t4')->getControl()->getId(), 't3');
  }

  protected function assertNbFactions($expected) {
    $factions = $this->getGame()->getBattlefield()->getFactions();
    $this->assertEquals($expected, count($factions));
  }

  protected function assertNbPiecePerFaction($expected) {
    $faction = current($this->getGame()->getBattlefield()->getFactions());
    $this->assertEquals($expected, count($faction->getPieces()));
  }

  protected function assertNbCells($expected) {
    $nb_cells = 0;
    foreach ($this->getGame()->getBattlefield()->getCells() as $cell) {
      if ($cell->getType() != $cell::TYPE_DISABLED) {
        $nb_cells++;
      }
    }
    $this->assertEquals($expected, $nb_cells);
  }

  protected function assertPiecesPositions($expected) {
    $factions = $this->getGame()->getBattlefield()->getFactions();
    /** @var Faction $faction */
    foreach (array_values($factions) as $key => $faction) {
      foreach ($faction->getPieces() as $piece) {
        $shortname = $piece->getDescription()->getType();
        $this->assertArrayHasKey($shortname, $expected);
        $piece_expected_positions = $expected[$shortname];
        $position_found = FALSE;
        foreach ($piece_expected_positions as $expected_piece_sort) {
          if ($expected_piece_sort[$key] == $piece->getPosition()->getName()) {
            $position_found = TRUE;
            break;
          }
        }
        $this->assertTrue($position_found, "Piece " . $shortname . " in an unexpected cell : " . $piece->getPosition()->getName());
      }
    }
  }

  /**
   * @return GameFactory
   */
  protected function getGameFactory() {
    return $this->gameFactory;
  }

  /**
   * @return GameManagerInterface
   */
  protected function getGame() {
    return $this->game;
  }

  /**
   * @param GameManagerInterface $game
   */
  protected function setGame($game) {
    $this->game = $game;
  }
}
