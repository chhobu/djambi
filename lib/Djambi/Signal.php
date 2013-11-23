<?php

namespace Djambi;


use Djambi\Interfaces\HumanPlayerInterface;

class Signal {
  /**
   * @var HumanPlayerInterface
   */
  protected $player;
  /**
   * @var int
   */
  protected $ping;
  /**
   * @var string
   */
  protected $ip;

  protected function __construct(HumanPlayerInterface $player, $ip, $ping) {
    $this->player = $player;
    $this->ping = $ping;
    $this->ip = $ip;
    $player->setLastSignal($this);
  }

  public static function loadSignal(HumanPlayerInterface $player, $ip, $ping) {
    return new static($player, $ip, $ping);
  }

  public static function createSignal(HumanPlayerInterface $player, $ip) {
    return new static($player, $ip, time());
  }

  public function getPing() {
    return $this->ping;
  }

  public function getIp() {
    return $this->ip;
  }

  public function getPlayer() {
    return $this->player;
  }

  public function propagate() {
    if (!is_null($this->player->getFaction())) {
      $this->player->getFaction()->getBattlefield()->getGameManager()->listenSignal($this);
    }
    return $this;
  }

  public function toArray() {
    $array = array(
      'ip' => $this->ip,
      'ping' => $this->ping,
    );
    return $array;
  }
}
