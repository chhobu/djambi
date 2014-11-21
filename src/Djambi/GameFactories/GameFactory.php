<?php

namespace Djambi\GameFactories;


use Djambi\GameManagers\Exceptions\UnknownGameManagerException;
use Djambi\GameDispositions\BaseGameDisposition;
use Djambi\GameDispositions\GameDispositionsFactory;
use Djambi\GameManagers\PlayableGameInterface;
use Djambi\Players\PlayerInterface;

class GameFactory implements GameFactoryInterface {
  /** @var PlayerInterface[] */
  private $players = array();
  /** @var BaseGameDisposition */
  private $disposition;
  /** @var string */
  private $gameManagerClass;
  /** @var string */
  private $id;
  /** @var string */
  private $battlefieldClass;

  public function __construct($game_manager_class = '\Djambi\GameManagers\SandboxGameManager') {
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

  public function setDisposition(BaseGameDisposition $disposition) {
    $this->disposition = $disposition;
    return $this;
  }

  public function getDisposition() {
    if (is_null($this->disposition)) {
      $this->disposition = $this->getDefaultDisposition();
    }
    return $this->disposition;
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

  public function setBattlefieldClass($class_name) {
    $this->battlefieldClass = $class_name;
    return $this;
  }

  public function getBattlefieldClass() {
    return $this->battlefieldClass;
  }

  /**
   * Instancie une nouvelle partie.
   * @throws UnknownGameManagerException
   * @return PlayableGameInterface
   */
  public function createGameManager() {
    if (class_exists($this->getGameManagerClass())) {
      $gm = call_user_func_array($this->getGameManagerClass() . '::create', array(
        $this->getPlayers(),
        $this->getId(),
        $this->getDisposition(),
        $this->getBattlefieldClass(),
      ));
      return $gm;
    }
    else {
      throw new UnknownGameManagerException("Game manager " . $this->getGameManagerClass() . " not found.");
    }
  }

  protected function getDefaultId() {
    return uniqid();
  }

  protected function getDefaultDisposition() {
    return GameDispositionsFactory::useDisposition('4std');
  }

}
