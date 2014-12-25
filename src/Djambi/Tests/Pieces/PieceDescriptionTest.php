<?php
namespace Djambi\Tests\Pieces;

use Djambi\GameDispositions\GameDispositionsFactory;
use Djambi\GameFactories\GameFactory;
use Djambi\Grids\GridInterface;
use Djambi\PieceDescriptions\Leader;
use Djambi\PieceDescriptions\Militant;
use Djambi\PieceDescriptions\PiecesContainer;
use Djambi\PieceDescriptions\Reporter;
use Djambi\Strings\Glossary;

/**
 * Created by PhpStorm.
 * User: buchho
 * Date: 23/12/14
 * Time: 21:05
 */

class PieceDescriptionTest extends \PHPUnit_Framework_TestCase {

  /**
   * @expectedException \Djambi\Grids\Exceptions\InvalidGridException
   */
  public function testBadDisposition() {
    new Leader(array('w' => 1, 'y' => 1));
  }

  /**
   * @expectedException \Djambi\Grids\Exceptions\InvalidGridException
   */
  public function testBadStartPosition() {
    $disp_factory = GameDispositionsFactory::initiateCustomDisposition();
    $disp_factory->setDimensions(7, 7);
    $disp_factory->setShape(GridInterface::SHAPE_CARDINAL);
    $container_t1 = new PiecesContainer();
    $container_t1->addPiece(new Leader('A1'));
    $container_t1->addPiece(new Reporter('A2'));
    $disp_factory->addSide($container_t1);
    $container_t2 = new PiecesContainer();
    $container_t2->addPiece(new Leader('A1'));
    $disp_factory->addSide($container_t2);
    $disposition = $disp_factory->deliverDisposition();
    $game_factory = new GameFactory();
    $game_factory->setDisposition($disposition);
    $game_factory->createGameManager();
  }

  public function testPieceName() {
    $piece = new Militant('A1');
    $this->assertEquals(Glossary::PIECE_MILITANT, $piece->getLongname());
    $this->assertEquals(1, $piece->getValue());
    $this->assertEquals('M', $piece->getShortname());
    $this->assertEquals('http://djambi.net/regles/militant', $piece->getRuleUrl());
  }

}