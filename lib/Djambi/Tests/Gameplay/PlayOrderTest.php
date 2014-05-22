<?php
/**
use Djambi\Tests\BaseDjambiTest;
 * Created by PhpStorm.
 * User: buchho
 * Date: 09/05/14
 * Time: 15:14
 */

namespace Djambi\Tests\Gameplay;

use Djambi\GameDispositions\GameDispositionsFactory;
use Djambi\GameFactories\GameFactory;
use Djambi\GameManagers\BasicGameManager;
use Djambi\GameOptions\StandardRuleset;
use Djambi\Gameplay\Cell;
use Djambi\Gameplay\Faction;
use Djambi\PieceDescriptions\Leader;
use Djambi\PieceDescriptions\Militant;
use Djambi\Tests\BaseDjambiTest;

class PlayOrderTest extends BaseDjambiTest {

  const THRONE = 'C3';

  public function setUp() {
    $disposition = GameDispositionsFactory::createNewCustomDisposition();
    $disposition->setDimensions(5, 5);
    $disposition->addSide(NULL, Faction::STATUS_READY, array(
       new Leader(NULL, 'A5'),
       new Militant(NULL, 'B3'),
    ));
    $disposition->addSide(NULL, Faction::STATUS_READY, array(
      new Leader(NULL, 'E5'),
      new Militant(NULL, 'C4'),
    ));
    $disposition->addSide(NULL, Faction::STATUS_READY, array(
      new Leader(NULL, 'E1'),
      new Militant(NULL, 'D3'),
    ));
    $disposition->addSide(NULL, Faction::STATUS_READY, array(
      new Leader(NULL, 'A1'),
      new Militant(NULL, 'C2'),
    ));

    $factory = new GameFactory();
    $factory->setDisposition($disposition->deliverDisposition());
    $factory->setMode(BasicGameManager::MODE_SANDBOX);
    $this->game = $factory->createGameManager();
    $skip_turn_rules = $this->game->getDisposition()->getOptionsStore()->retrieve(StandardRuleset::GAMEPLAY_ELEMENT_SKIPPED_TURNS);
    $skip_turn_rules->setValue(-1);

    $this->assertEquals(Cell::TYPE_THRONE, $this->game->getBattlefield()->findCellByName(self::THRONE)->getType());
  }

  public function testPlayOrderAfterThroneAccessScenario1() {
    $this->game->play();
    $grid = $this->game->getBattlefield();

    $this->checkPlayOrder(array('t1', 't2', 't3', 't4'));
    $this->assertEquals(1, $grid->getCurrentTurn()->getRound());
    $this->doMove($grid->findPieceById('t1-L'), self::THRONE);

    $this->checkNewTurn('t2');
    $this->checkPlayOrder(array('t2', 't1', 't3', 't1', 't4', 't1'));
    $this->assertEquals(1, $grid->getCurrentTurn()->getRound());
    $grid->getPlayingFaction()->skipTurn();

    $this->checkNewTurn('t1');
    $this->checkPlayOrder(array('t1', 't3', 't1', 't4', 't1', 't2'));
    $this->assertEquals(1, $grid->getCurrentTurn()->getRound());
    $grid->getPlayingFaction()->skipTurn();

    $this->checkNewTurn('t3');
    $this->checkPlayOrder(array('t3', 't1', 't4', 't1', 't2', 't1'));
    $this->assertEquals(1, $grid->getCurrentTurn()->getRound());
    $grid->getPlayingFaction()->withdraw();

    $this->checkNewTurn('t4');
    $this->checkPlayOrder(array('t4', 't1', 't2', 't1'));
    $this->assertEquals(1, $grid->getCurrentTurn()->getRound());
    $this->doMove($grid->findPieceById('t4-L'), self::THRONE, array(
      'murder' => array('choice' => 'A5'),
    ));

    $this->checkNewTurn('t2');
    $this->checkPlayOrder(array('t2', 't4', 't4'));
    $this->assertEquals(2, $grid->getCurrentTurn()->getRound());
    $grid->getPlayingFaction()->skipTurn();

    $this->checkNewTurn('t4');
    $this->checkPlayOrder(array('t4', 't4', 't2'));
    $this->assertEquals(2, $grid->getCurrentTurn()->getRound());
    $this->doMove($grid->findPieceById('t4-L'), 'A1');

    $this->checkNewTurn('t2');
    $this->checkPlayOrder(array('t2', 't4'));
    $this->assertEquals(3, $grid->getCurrentTurn()->getRound());
    $this->doMove($grid->findPieceById('t2-L'), self::THRONE);

    $this->checkNewTurn('t4');
    $this->checkPlayOrder(array('t4', 't2', 't2'));
    $this->assertEquals(3, $grid->getCurrentTurn()->getRound());
    $grid->getPlayingFaction()->skipTurn();

    $this->checkNewTurn('t2');
    $this->checkPlayOrder(array('t2', 't2', 't4'));
    $this->assertEquals(3, $grid->getCurrentTurn()->getRound());
    $grid->getPlayingFaction()->skipTurn();

    $this->checkNewTurn('t2');
    $this->checkPlayOrder(array('t2', 't4', 't2'));
    $this->assertEquals(4, $grid->getCurrentTurn()->getRound());
    $this->doMove($grid->findPieceById('t2-L'), 'E5');

    $this->checkNewTurn('t4');
    $this->checkPlayOrder(array('t4', 't2'));
    $this->assertEquals(4, $grid->getCurrentTurn()->getRound());
    $this->doMove($grid->findPieceById('t4-L'), 'E5', array(
      'murder' => array('choice' => 'E4'),
    ));

    $this->checkGameFinished('t4');
    $turns = $grid->getPastTurns();
    $last_turn = array_pop($turns);
    $this->assertEquals(4, $last_turn['round']);
    $this->assertEquals(1, $grid->findFactionById('t4')->getRanking());
    $this->assertEquals(2, $grid->findFactionById('t2')->getRanking());
    $this->assertEquals(3, $grid->findFactionById('t1')->getRanking());
    $this->assertEquals(4, $grid->findFactionById('t3')->getRanking());
  }

  public function testPlayOrderAfterThroneAccessScenario2() {
    $this->game->play();
    $grid = $this->game->getBattlefield();

    $this->checkPlayOrder(array('t1', 't2', 't3', 't4'));
    $this->assertEquals(1, $grid->getCurrentTurn()->getRound());
    $this->doMove($grid->findPieceById('t1-L'), 'E5', array(
      'murder' => array('choice' => 'A5'),
    ));

    $this->checkNewTurn('t3');
    $this->checkPlayOrder(array('t3', 't4', 't1'));
    $this->doMove($grid->findPieceById('t3-L'), self::THRONE);

    $this->checkNewTurn('t4');
    $this->checkPlayOrder(array('t4', 't3', 't1', 't3'));
    $this->doMove($grid->findPieceById('t4-L'), self::THRONE, array(
      'murder' => array('choice' => 'A2'),
    ));

    $this->checkNewTurn('t1');
    $this->checkPlayOrder(array('t1', 't4', 't4'));
    $this->assertEquals(2, $grid->getCurrentTurn()->getRound());
    $grid->getPlayingFaction()->withdraw();

    $this->checkGameFinished('t4');
    $this->assertEquals(1, $grid->findFactionById('t4')->getRanking());
    $this->assertEquals(2, $grid->findFactionById('t1')->getRanking());
    $this->assertEquals(3, $grid->findFactionById('t3')->getRanking());
    $this->assertEquals(4, $grid->findFactionById('t2')->getRanking());
  }

  public function testPlayOrderAfterLeaderKillScenario1() {
    $grid = $this->game->getBattlefield();
    $m4 = $grid->findPieceById('t4-M');
    $this->game->play();

    $grid->getPlayingFaction()->skipTurn();

    $this->assertEquals(1, $grid->getCurrentTurn()->getRound());
    $this->checkNewTurn('t2');
    $this->checkPlayOrder(array('t2', 't3', 't4', 't1'));
    $grid->getPlayingFaction()->skipTurn();

    $this->checkPlayOrder(array('t3', 't4', 't1', 't2'));
    $grid->getPlayingFaction()->skipTurn();

    $this->checkPlayOrder(array('t4', 't1', 't2', 't3'));
    $this->doMove($grid->findPieceById('t4-L'), self::THRONE);

    $this->checkPlayOrder(array('t1', 't4', 't2', 't4', 't3', 't4'));
    $this->assertEquals(2, $grid->getCurrentTurn()->getRound());
    $grid->getPlayingFaction()->skipTurn();

    $this->checkPlayOrder(array('t4', 't2', 't4', 't3', 't4', 't1'));
    $this->doMove($m4, 'A2');

    $this->checkPlayOrder(array('t2', 't4', 't3', 't4', 't1', 't4'));
    $grid->getPlayingFaction()->skipTurn();

    $this->checkPlayOrder(array('t4', 't3', 't4', 't1', 't4', 't2'));
    $this->doMove($m4, 'A4');

    $this->checkPlayOrder(array('t3', 't4', 't1', 't4', 't2', 't4'));
    $grid->getPlayingFaction()->skipTurn();

    $this->checkPlayOrder(array('t4', 't1', 't4', 't2', 't4', 't3'));
    $this->doMove($m4, 'A5', array('murder' => array('choice' => 'D2')));

    $this->checkPlayOrder(array('t2', 't4', 't3', 't4'));
    $this->assertEquals(3, $grid->getCurrentTurn()->getRound());
    $grid->getPlayingFaction()->skipTurn();

    $this->checkPlayOrder(array('t4', 't3', 't4', 't2'));
    $this->doMove($m4, 'C5');

    $this->checkPlayOrder(array('t3', 't4', 't2', 't4'));
    $grid->getPlayingFaction()->skipTurn();

    $this->checkPlayOrder(array('t4', 't2', 't4', 't3'));
    $this->doMove($m4, 'E5', array('murder' => array('choice' => 'D5')));

    $this->checkPlayOrder(array('t3', 't4', 't4'));
    $this->assertEquals(4, $grid->getCurrentTurn()->getRound());
    $grid->getPlayingFaction()->skipTurn();

    $this->checkPlayOrder(array('t4', 't4', 't3'));
    $this->doMove($m4, 'E4');

    $this->checkPlayOrder(array('t4', 't3', 't4'));
    $this->doMove($m4, 'E3');

    $this->checkPlayOrder(array('t3', 't4', 't4'));
    $this->assertEquals(5, $grid->getCurrentTurn()->getRound());
    $grid->getPlayingFaction()->withdraw();

    $this->checkGameFinished('t4');
    $this->assertEquals(1, $grid->findFactionById('t4')->getRanking());
    $this->assertEquals(2, $grid->findFactionById('t3')->getRanking());
    $this->assertEquals(3, $grid->findFactionById('t2')->getRanking());
    $this->assertEquals(4, $grid->findFactionById('t1')->getRanking());
  }

  public function testPlayOrderAfterLeaderKillScenario2() {
    $grid = $this->game->getBattlefield();
    $m1 = $grid->findPieceById('t1-M');
    $this->game->play();

    // Tour 1 : Team 1 au pouvoir
    $this->assertEquals(1, $grid->getCurrentTurn()->getRound());
    $this->doMove($grid->findPieceById('t1-L'), self::THRONE);

    $this->assertEquals(1, $grid->getCurrentTurn()->getRound());
    $this->checkNewTurn('t2');
    $this->checkPlayOrder(array('t2', 't1', 't3', 't1', 't4', 't1'));
    $grid->getPlayingFaction()->skipTurn();

    $this->checkPlayOrder(array('t1', 't3', 't1', 't4', 't1', 't2'));
    $this->doMove($m1, 'A2');

    $this->checkPlayOrder(array('t3', 't1', 't4', 't1', 't2', 't1'));
    $grid->getPlayingFaction()->skipTurn();

    $this->checkPlayOrder(array('t1', 't4', 't1', 't2', 't1', 't3'));
    $this->doMove($m1, 'A1', array('murder' => array('choice' => 'A2')));

    // Tour 2 : Arf.
    $this->assertEquals(2, $grid->getCurrentTurn()->getRound());
    $this->checkPlayOrder(array('t2', 't1', 't3', 't1'));
    $grid->getPlayingFaction()->skipTurn();

    $this->checkPlayOrder(array('t1', 't3', 't1', 't2'));
    $this->doMove($m1, 'C1');

    $this->checkPlayOrder(array('t3', 't1', 't2', 't1'));
    $grid->getPlayingFaction()->withdraw();

    $this->assertEquals(3, $grid->getCurrentTurn()->getRound());
    $this->checkPlayOrder(array('t1', 't2', 't1'));
    $grid->getPlayingFaction()->skipTurn();

    $this->checkPlayOrder(array('t2', 't1', 't1'));
    $grid->getPlayingFaction()->skipTurn();

    $this->checkPlayOrder(array('t1', 't1', 't2'));
    $grid->getPlayingFaction()->skipTurn();

    // Tour 3 : Fin
    $this->assertEquals(4, $grid->getCurrentTurn()->getRound());
    $this->checkPlayOrder(array('t1', 't2', 't1'));
    $this->doMove($grid->findPieceById('t1-L'), 'E5', array('murder' => array('choice' => 'A3')));

    $this->checkGameFinished('t1');
    $this->assertEquals(1, $grid->findFactionById('t1')->getRanking());
    $this->assertEquals(2, $grid->findFactionById('t2')->getRanking());
    $this->assertEquals(3, $grid->findFactionById('t3')->getRanking());
    $this->assertEquals(4, $grid->findFactionById('t4')->getRanking());

  }

}
