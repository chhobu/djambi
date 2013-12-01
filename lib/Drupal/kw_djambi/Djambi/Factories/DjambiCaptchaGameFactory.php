<?php

namespace Drupal\kw_djambi\Djambi\Factories;


use Djambi\Factories\GameDispositionsFactory;
use Djambi\GameDisposition;
use Djambi\GameManager;
use Djambi\IA\DummyIA;
use Djambi\Interfaces\GameFactoryInterface;
use Djambi\Interfaces\HumanPlayerInterface;
use Djambi\Players\ComputerPlayer;
use Drupal\kw_djambi\Djambi\DjambiContext;
use Drupal\kw_djambi\Djambi\Traits\DrupalDjambiFormTrait;

class DjambiCaptchaGameFactory implements GameFactoryInterface {
  private $player1;
  private $player2;
  private $disposition;

  use DrupalDjambiFormTrait;

  public function __construct() {}

  public function setPlayer1(HumanPlayerInterface $player1) {
    $this->player1 = $player1;
    return $this;
  }

  public function getPlayer1() {
    if (is_null($this->player1)) {
      $this->player1 = DjambiContext::getInstance()->getCurrentUser(TRUE);
    }
    return $this->player1;
  }

  public function setPlayer2(ComputerPlayer $player2) {
    $this->player2 = $player2;
    return $this;
  }

  public function getPlayer2() {
    if (is_null($this->player2)) {
      $this->player2 = new ComputerPlayer();
      $this->player2->useIa(DummyIA::instanciate($this->player2));
    }
    return $this->player2;
  }

  public function setDisposition(GameDisposition $disposition) {
    $this->disposition = $disposition;
    return $this;
  }

  public function getDisposition() {
    if (is_null($this->disposition)) {
      $this->disposition = GameDispositionsFactory::loadDisposition('2mini');
    }
    return $this->disposition;
  }

  public function createGameManager() {
    $players = array($this->getPlayer1(), $this->getPlayer2());
    $game = GameManager::createGame($players, uniqid('Captcha-'), GameManager::MODE_TRAINING, $this->getDisposition());
    $game->setOption('turns_before_draw_proposal', -1);
    $game->setInfo('interface', 'minimal')->play();
    return $game;
  }

}
