<?php
/**
 * Created by PhpStorm.
 * User: buchho
 * Date: 29/12/14
 * Time: 17:58
 */

namespace Djambi\Tests\Gameplay;


use Djambi\Enums\StatusEnum;
use Djambi\GameDispositions\GameDispositionsFactory;
use Djambi\GameFactories\GameFactory;
use Djambi\GameOptions\StandardRuleset;
use Djambi\Grids\GridInterface;
use Djambi\PieceDescriptions\Assassin;
use Djambi\PieceDescriptions\Leader;
use Djambi\PieceDescriptions\PiecesContainer;
use Djambi\Tests\BaseDjambiTest;

class PeaceNegociationWithRuler extends BaseDjambiTest {

  public function setup() {
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
  }

  public function testDrawRejected() {
    $this->game->setOption(StandardRuleset::GAMEPLAY_ELEMENT_DRAW_DELAY, 2);
    $this->game->play();
    $battlefield = $this->game->getBattlefield();

    $battlefield->getPlayingFaction()->skipTurn();
    $battlefield->getPlayingFaction()->skipTurn();
    $battlefield->getPlayingFaction()->skipTurn();
    $this->assertEquals(2, $battlefield->getCurrentTurn()->getRound());

    $this->doMove('t1-A1', 'C2');
    $battlefield->getPlayingFaction()->skipTurn();
    $this->doMove('t3-A1', 'C6');
    $this->assertEquals(3, $battlefield->getCurrentTurn()->getRound());

    $this->simpleDrawRejectTest();
    $this->simpleDrawRollbackTest();
    $this->complexDrawRejectTest();
    $this->complexDrawRollbackTest();
  }

  protected function simpleDrawRejectTest() {
    $battlefield = $this->game->getBattlefield();
    $this->checkPlayOrder(array('t1', 't2', 't3'));
    $battlefield->getPlayingFaction()->skipTurn();
    $this->checkPlayOrder(array('t2', 't3', 't1'));
    $battlefield->getPlayingFaction()->callForADraw();
    $this->checkPlayOrder(array('t3', 't1'));
    $battlefield->getPlayingFaction()->acceptDraw();
    $this->checkPlayOrder(array('t1'));
    $battlefield->getPlayingFaction()->rejectDraw();
    $this->assertEquals(4, $battlefield->getCurrentTurn()->getRound());
  }

  protected function simpleDrawRollbackTest() {
    $battlefield = $this->game->getBattlefield();
    $this->doMove('t1-L', 'A7', array(
      0 => array('choice' => 'B6'),
    ));
    $this->checkNewTurn('t2');
    $this->checkPlayOrder(array('t2', 't1'));

    $battlefield->cancelLastTurn();
    $this->checkGameStatus(StatusEnum::STATUS_DRAW_PROPOSAL);
    $this->checkPlayOrder(array('t1'));

    $battlefield->cancelLastTurn();
    $this->checkGameStatus(StatusEnum::STATUS_DRAW_PROPOSAL);
    $this->checkPlayOrder(array('t3', 't1'));
    $this->assertEquals(3, $battlefield->getCurrentTurn()->getRound());
    $battlefield->getPlayingFaction()->rejectDraw();
    $battlefield->getPlayingFaction()->skipTurn();
  }

  protected function complexDrawRejectTest() {
    $battlefield = $this->game->getBattlefield();
    $this->doMove('t1-L', 'D4');
    $this->checkNewTurn('t2');
    $this->assertEquals(FALSE, $battlefield->getPlayingFaction()->canCallForADraw());
    $battlefield->getPlayingFaction()->skipTurn();
    $this->checkNewTurn('t1');
    $battlefield->getPlayingFaction()->skipTurn();
    $this->checkNewTurn('t3');
    $this->assertEquals(TRUE, $battlefield->getPlayingFaction()->canCallForADraw());
    $battlefield->getPlayingFaction()->skipTurn();
    $this->assertEquals(5, $battlefield->getCurrentTurn()->getRound());

    $this->checkNewTurn('t1');
    $this->checkPlayOrder(array('t1', 't2', 't1', 't3'));
    $battlefield->getPlayingFaction()->callForADraw();

    $this->checkPlayOrder(array('t2', 't3'));
    $this->checkGameStatus(StatusEnum::STATUS_DRAW_PROPOSAL);
    $battlefield->getPlayingFaction()->acceptDraw();

    $this->checkPlayOrder(array('t3'));
    $this->checkGameStatus(StatusEnum::STATUS_DRAW_PROPOSAL);
    $battlefield->getPlayingFaction()->rejectDraw();

    $this->checkPlayOrder(array('t1', 't3', 't1', 't2'));
    $this->checkGameStatus(StatusEnum::STATUS_PENDING);
  }

  protected function complexDrawRollbackTest() {
    $battlefield = $this->game->getBattlefield();
    $this->doMove('t1-L', 'A7', array(
      'murder' => array(
        'type' => 'Djambi\\Moves\\Murder',
        'choice' => 'B6',
      ),
    ));
    $this->checkNewTurn('t2');

    $battlefield->cancelLastTurn();
    $this->checkGameStatus(StatusEnum::STATUS_DRAW_PROPOSAL);
    $this->checkPlayOrder(array('t3'));
    $battlefield->cancelLastTurn();

    $this->checkGameStatus(StatusEnum::STATUS_DRAW_PROPOSAL);
    $this->checkPlayOrder(array('t2', 't3'));
    $battlefield->getPlayingFaction()->rejectDraw();

    $battlefield->cancelLastTurn();
    $this->checkGameStatus(StatusEnum::STATUS_PENDING);
    $this->checkPlayOrder(array('t1', 't2', 't1', 't3'));
  }

}