<?php
namespace Djambi\Players;

use Djambi\Interfaces\HumanPlayerInterface;
use Djambi\Player;
use Djambi\Signal;

class HumanPlayer extends Player implements HumanPlayerInterface {
  /** @var bool */
  protected $registered = FALSE;
  /** @var Signal */
  protected $lastSignal;
  /** @var int */
  protected $joined;

  public function __construct($id, $prefix = '') {
    $this->type = 'human';
    $this->className = get_class($this);
    $this->setId($id, $prefix);
  }

  public function isRegistered() {
    return $this->registered;
  }

  protected function setRegistered($bool) {
    $this->registered = $bool ? TRUE : FALSE;
  }

  public function register(array $data = NULL) {
    $this->setRegistered(TRUE);
    return $this;
  }

  public function saveToArray() {
    $data = array(
      'className' => $this->getClassName(),
      'registered' => $this->isRegistered(),
      'id' => $this->getId(),
    );
    return $data;
  }

  /**
   * @return Signal;
   */
  public function getLastSignal() {
    return $this->lastSignal;
  }

  /**
   * @param Signal $signal
   *
   * @return HumanPlayerInterface
   */
  public function setLastSignal(Signal $signal) {
    $this->lastSignal = $signal;
    return $this;
  }

  /**
   * @return int
   */
  public function getJoined() {
    return $this->joined;
  }

  /**
   * @param int $joined
   *
   * @return HumanPlayerInterface
   */
  public function setJoined($joined) {
    $this->joined = $joined;
    return $this;
  }

  public static function loadPlayer(array $data) {
    /* @var \Djambi\Players\HumanPlayer $player */
    $player = parent::loadPlayer($data);
    if ($data['registered']) {
      $player->register($data);
    }
    if (!empty($data['joined'])) {
      $joined = $data['joined'];
    }
    else {
      $joined = time();
    }
    $player->setJoined($joined);
    if (!empty($data['ip']) && !empty($data['ping'])) {
      Signal::loadSignal($player, $data['ip'], $data['ping']);
    }
    return $player;
  }

}
