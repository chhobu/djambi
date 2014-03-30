<?php

namespace Drupal\kw_djambi\Djambi\Factories;


use Djambi\Factories\GameDispositionsFactory;
use Djambi\Factories\GameFactory;
use Djambi\GameManager;
use Djambi\IA\DummyIA;
use Djambi\IA\PredictableIA;
use Djambi\Interfaces\GameFactoryInterface;
use Djambi\Players\ComputerPlayer;
use Drupal\kw_djambi\Djambi\DjambiContext;
use Drupal\kw_djambi\Djambi\Traits\DrupalDjambiFormTrait;

class DjambiCaptchaGameFactory extends GameFactory implements GameFactoryInterface {

  use DrupalDjambiFormTrait;

  public function __construct() {
    parent::__construct();
    $this->setMode(GameManager::MODE_TRAINING);
  }

  protected function getDefaultComputerIa() {
    $ia_class = DummyIA::getClass();
    return $ia_class;
  }

  public function createGameManager() {
    $this->setDisposition(GameDispositionsFactory::loadDisposition('2mini'));
    $this->addPlayer(DjambiContext::getInstance()->getCurrentUser(TRUE), 1);
    $this->setId(uniqid('Captcha-'));
    $game = parent::createGameManager();
    $game->setOption('turns_before_draw_proposal', -1);
    $game->setInfo('interface', 'minimal')->play();
    if (isset($_GET['ia']) && $_GET['ia'] == 'predictable') {
      $player = $game->getBattlefield()->getFactionById('B')->getPlayer();
      if ($player instanceof ComputerPlayer) {
        $player->useIa(PredictableIA::getClass());
        $file_path = variable_get('file_temporary_path') . '/djambi/PredictableIA.' . $game->getId() . '.'
        . $player->getFaction()->getId() . '.json';
        $player->getIa()->addSetting('strategy_file', $file_path);
      }
    }
    return $game;
  }

}
