<?php
namespace Djambi\Tests\Gameplay;

use Djambi\GameDispositions\GameDispositionsFactory;
use Djambi\GameFactories\GameFactory;
use Djambi\GameManagers\BaseGameManager;
use Djambi\Tests\BaseDjambiTest;

class RollbackTurnTest extends BaseDjambiTest {
  public function setUp() {
    $factory = new GameFactory();
    $factory->setDisposition(GameDispositionsFactory::useDisposition('2std'));
    $factory->setMode(BaseGameManager::MODE_SANDBOX);
    $this->game = $factory->createGameManager();
  }

  public function testRollbackSimpleMove() {
    $this->game->play();
    $necro1 = $this->game->getBattlefield()->findPieceById('t1-N');
    $this->doMove($necro1, 'C4');
    $this->checkNewTurn('t3');

    $this->game->getBattlefield()->cancelLastTurn();
    $this->checkNewTurn('t1');
    $this->checkPosition($necro1, 'C7');
  }

}
