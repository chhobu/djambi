<?php
namespace Djambi\Tests\Gameplay;

use Djambi\GameDispositions\GameDispositionsFactory;
use Djambi\GameFactories\GameFactory;
use Djambi\Grids\GridInterface;
use Djambi\PieceDescriptions\Assassin;
use Djambi\PieceDescriptions\Leader;
use Djambi\PieceDescriptions\PiecesContainer;
use Djambi\Tests\BaseDjambiTest;

class RollbackTurnTest extends BaseDjambiTest {

  public function testRollbackSimpleMove() {
    $factory = new GameFactory();
    $factory->setDisposition(GameDispositionsFactory::useDisposition('2std'));
    $this->game = $factory->createGameManager();

    $this->game->play();
    $necro1 = 't1-N';
    $this->doMove($necro1, 'C4');
    $this->checkNewTurn('t3');

    $this->game->getBattlefield()->cancelLastTurn();
    $this->checkNewTurn('t1');
    $this->checkPosition($necro1, 'C7');
  }

  public function testRollbackAfterSideEliminationByRuler() {
    $factory = new GameFactory();
    $disposition = GameDispositionsFactory::initiateCustomDisposition();
    $disposition->setShape(GridInterface::SHAPE_CARDINAL);
    $disposition->setDimensions(7, 7);

    $container_t1 = new PiecesContainer();
    $disposition->addSide($container_t1->addPiece(new Leader('A1'))
      ->addPiece(new Assassin('A2'))
      ->addPiece(new Assassin('B1'))
    );

    $container_t2 = new PiecesContainer();
    $disposition->addSide($container_t2->addPiece(new Leader('G7'))
      ->addPiece(new Assassin('G6'))
      ->addPiece(new Assassin('F7'))
    );

    $container_t3 = new PiecesContainer();
    $disposition->addSide($container_t3->addPiece(new Leader('A7'))
      ->addPiece(new Assassin('A6'))
      ->addPiece(new Assassin('B7'))
    );

    $factory->setDisposition($disposition->deliverDisposition());
    $this->game = $factory->createGameManager();
    $this->game->play();
    $battlefield = $this->game->getBattlefield();

    $this->doMove('t1-L', 'D4');
    $this->checkPlayOrder(array('t2', 't1', 't3', 't1'));

    $battlefield->getPlayingFaction()->skipTurn();

    $this->doMove('t1-A1', 'F7');

    $this->doMove('t3-L', 'B6');

    $this->doMove('t1-A1', 'G7');
    $this->assertFalse($battlefield->findPieceById('t2-L')->isAlive());
    $this->assertEquals('t1', $battlefield->findFactionById('t2')->getControl()->getId());
    $this->assertEquals('t3', $battlefield->findFactionById('t3')->getControl()->getId());

    $battlefield->cancelLastTurn();

    $this->checkPosition('t1-A1', 'F7');
    $this->assertTrue($battlefield->findPieceById('t2-L')->isAlive());
    $this->assertEquals('t1', $battlefield->findFactionById('t1')->getControl()->getId());
    $this->assertEquals('t2', $battlefield->findFactionById('t2')->getControl()->getId());
    $this->assertEquals('t3', $battlefield->findFactionById('t3')->getControl()->getId());
    $this->checkPlayOrder(array('t1', 't2', 't1', 't3', 't1'));
  }

}
