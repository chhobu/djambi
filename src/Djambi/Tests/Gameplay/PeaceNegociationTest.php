<?php
/**
 * Created by PhpStorm.
 * User: buchho
 * Date: 16/05/14
 * Time: 16:22
 */

namespace Djambi\Tests\Gameplay;


use Djambi\Enums\StatusEnum;
use Djambi\GameDispositions\GameDispositionsFactory;
use Djambi\GameFactories\GameFactory;
use Djambi\GameOptions\StandardRuleset;
use Djambi\Gameplay\Faction;
use Djambi\Tests\BaseDjambiTest;

class PeaceNegociationTest extends BaseDjambiTest {
  public function setUp() {
    $factory = new GameFactory();
    $factory->setDisposition(GameDispositionsFactory::useDisposition('4std'));
    $this->game = $factory->createGameManager();
  }

  /**
   * @expectedException \Djambi\Exceptions\DisallowedActionException
   */
  public function testForbiddenDrawAccept() {
    $this->game->play();
    $this->game->getBattlefield()->getPlayingFaction()->acceptDraw();
  }

  /**
   * @expectedException \Djambi\Exceptions\DisallowedActionException
   */
  public function testForbiddenDrawReject() {
    $this->game->play();
    $this->game->getBattlefield()->getPlayingFaction()->rejectDraw();
  }

  /**
   * @expectedException \Djambi\Exceptions\DisallowedActionException
   */
  public function testForbiddenDrawProposal() {
    $this->game->setOption(StandardRuleset::GAMEPLAY_ELEMENT_DRAW_DELAY, 2);
    $this->game->play();
    $this->game->getBattlefield()->getPlayingFaction()->callForADraw();
  }

  public function testNormalDrawReject() {
    $this->game->setOption(StandardRuleset::GAMEPLAY_ELEMENT_DRAW_DELAY, 2);
    $this->game->play();
    $grid = $this->game->getBattlefield();

    $grid->getPlayingFaction()->skipTurn();
    $grid->getPlayingFaction()->skipTurn();
    $grid->getPlayingFaction()->skipTurn();
    $grid->getPlayingFaction()->skipTurn();

    $this->assertEquals(2, $grid->getCurrentTurn()->getRound());
    $grid->getPlayingFaction()->callForADraw();

    $this->assertEquals('t2', $grid->getPlayingFaction()->getId());
    $this->assertEquals(StatusEnum::STATUS_DRAW_PROPOSAL, $this->game->getStatus());
    $grid->getPlayingFaction()->acceptDraw();

    $this->assertEquals('t3', $grid->getPlayingFaction()->getId());
    $this->assertEquals(StatusEnum::STATUS_DRAW_PROPOSAL, $this->game->getStatus());
    $grid->getPlayingFaction()->acceptDraw();

    $this->assertEquals('t4', $grid->getPlayingFaction()->getId());
    $this->assertEquals(StatusEnum::STATUS_DRAW_PROPOSAL, $this->game->getStatus());
    $grid->getPlayingFaction()->rejectDraw();

    $this->checkNewTurn('t4');
    $this->checkPlayOrder(array('t4', 't1', 't2', 't3'));
  }

  public function testNormalDrawAccept() {
    $this->game->setOption(StandardRuleset::GAMEPLAY_ELEMENT_DRAW_DELAY, -1);
    $this->game->play();
    $grid = $this->game->getBattlefield();

    $grid->getPlayingFaction()->callForADraw();
    $grid->getPlayingFaction()->acceptDraw();
    $grid->getPlayingFaction()->acceptDraw();
    $grid->getPlayingFaction()->acceptDraw();

    $this->checkGameFinished(array('t1', 't2', 't3', 't4'));
  }

  public function testThroneDrawReject() {
    $grid = $this->game->getBattlefield();
    $this->game->setOption(StandardRuleset::GAMEPLAY_ELEMENT_DRAW_DELAY, -1);
    $grid->findPieceById('t1-L')->setPosition($grid->findCellByName('E5'));
    $this->game->play();

    $grid->getPlayingFaction()->skipTurn();
    $this->checkPlayOrder(array('t2', 't1', 't3', 't1', 't4', 't1'));
    $grid->getPlayingFaction()->callForADraw();
    $this->checkPlayOrder(array('t1', 't3', 't4', 't1'));
    $grid->getPlayingFaction()->acceptDraw();
    $this->checkPlayOrder(array('t3', 't4'));
    $grid->getPlayingFaction()->rejectDraw();
    $this->checkPlayOrder(array('t3', 't1', 't4', 't1', 't2', 't1'));
  }

  public function testDrawTurnCancel() {
    $grid = $this->game->getBattlefield();
    $this->game->setOption(StandardRuleset::GAMEPLAY_ELEMENT_DRAW_DELAY, -1);
    $this->game->play();

    $grid->getPlayingFaction()->callForADraw();
    $grid->getPlayingFaction()->acceptDraw();
    $grid->cancelLastTurn();
    $this->assertEquals('t2', $grid->getPlayingFaction()->getId());
    $this->assertEquals(StatusEnum::STATUS_DRAW_PROPOSAL, $this->game->getStatus());
    $this->assertEquals(Faction::DRAW_STATUS_UNDECIDED, $grid->getPlayingFaction()->getDrawStatus());

    $grid->cancelLastTurn();
    $this->checkNewTurn('t1');
  }
}
