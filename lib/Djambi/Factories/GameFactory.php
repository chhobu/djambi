<?php

namespace Djambi\Factories;


use Djambi\Exceptions\Exception;
use Djambi\GameDisposition;
use Djambi\GameManager;
use Djambi\IA\DummyIA;
use Djambi\Interfaces\GameFactoryInterface;
use Djambi\Interfaces\GameManagerInterface;
use Djambi\Interfaces\PlayerInterface;
use Djambi\Players\ComputerPlayer;
use Djambi\Players\HumanPlayer;

class GameFactory implements GameFactoryInterface {
  /** @var string */
  private $mode = GameManager::MODE_FRIENDLY;
  /** @var PlayerInterface[] */
  private $players = array();
  /** @var GameDisposition */
  private $disposition;
  /** @var string */
  private $gameManagerClass;
  /** @var string */
  private $id;
  /** @var string */
  private $battlefieldFactory;

  public function __construct($game_manager_class = '\Djambi\GameManager') {
    $this->setGameManagerClass($game_manager_class);
  }

  public function setGameManagerClass($class) {
    $this->gameManagerClass = $class;
    return $this;
  }

  public function getGameManagerClass() {
    return $this->gameManagerClass;
  }

  public function addPlayer(PlayerInterface $player, $start_order = NULL) {
    if (is_null($start_order) || $start_order <= 0) {
      $start_order = !empty($this->players) ? max(array_keys($this->players)) + 1 : 1;
    }
    $this->players[$start_order] = $player;
    return $this;
  }

  public function resetPlayers() {
    $this->players = array();
    return $this;
  }

  public function removePlayer(PlayerInterface $kickable_player) {
    foreach ($this->players as $key => $player) {
      if ($player->getId() == $kickable_player->getId() && get_class($player) == get_class($kickable_player)) {
        unset($this->players[$key]);
      }
    }
    return $this;
  }

  public function getPlayers() {
    return $this->players;
  }

  protected function setPlayers() {
    return $this->players;
  }

  public function setDisposition(GameDisposition $disposition) {
    $this->disposition = $disposition;
    return $this;
  }

  public function getDisposition() {
    if (is_null($this->disposition)) {
      $this->disposition = $this->getDefaultDisposition();
    }
    return $this->disposition;
  }

  public function setMode($mode) {
    $this->mode = $mode;
    return $this;
  }

  public function getMode() {
    return $this->mode;
  }

  public function setId($id) {
    $this->id = $id;
    return $this;
  }

  public function getId() {
    if (is_null($this->id)) {
      $this->id = $this->getDefaultId();
    }
    return $this->id;
  }

  public function setBattlefieldFactory($class_name) {
    $this->battlefieldFactory = $class_name;
    return $this;
  }

  public function getBattlefieldFactory() {
    return $this->battlefieldFactory;
  }

  /**
   * Instancie une nouvelle partie.
   *
   * @throws \Djambi\Exceptions\Exception
   * @return GameManagerInterface
   */
  public function createGameManager() {
    if (class_exists($this->getGameManagerClass())) {
      $this->addDefaultPlayers();
      $gm = call_user_func_array($this->getGameManagerClass() . '::createGame', array(
        $this->getPlayers(),
        $this->getId(),
        $this->getMode(),
        $this->getDisposition(),
        $this->getBattlefieldFactory(),
      ));
      return $gm;
    }
    else {
      throw new Exception("Game manager " . $this->getGameManagerClass() . " not found.");
    }
  }

  protected function getDefaultId() {
    return uniqid();
  }

  protected function getDefaultDisposition() {
    return GameDispositionsFactory::loadDisposition('4std');
  }

  protected function addDefaultPlayers() {
    $disposition = $this->getDisposition();
    $default_player = $this->getDefaultCurrentPlayer();
    for ($i = count($this->players) + 1; $i <= $disposition->getNbPlayers(); $i++) {
      if ($i == 1 || $this->getMode() == GameManager::MODE_SANDBOX) {
        $this->addPlayer($default_player, $i);
      }
      elseif ($this->getMode() == GameManager::MODE_TRAINING) {
        $computer = new ComputerPlayer();
        $this->addPlayer($computer->useIa($this->getDefaultComputerIa()));
      }
      else {
        $this->addPlayer(new HumanPlayer('Player ' . $i));
      }
    }
    return $this;
  }

  protected function getDefaultCurrentPlayer() {
    return new HumanPlayer('Player 1');
  }

  protected function getDefaultComputerIa() {
    return DummyIA::getClass();
  }

}
