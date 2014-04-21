<?php

namespace Djambi\GameManagers;

use Djambi\Exceptions\PlayerNotFoundException;
use Djambi\PersistantDjambiObject;
use Djambi\Players\HumanPlayerInterface;

class Signal extends PersistantDjambiObject {
  /** @var HumanPlayerInterface */
  protected $player;
  /** @var int */
  protected $time;
  /** @var string */
  protected $ip;

  protected function __construct(HumanPlayerInterface $player, $ip, $time) {
    $this->time = $time;
    $this->ip = $ip;
    $this->player = $player;
  }

  public static function fromArray(array $data, array $context = array()) {
    if (!isset($context['player'])) {
      throw new PlayerNotFoundException();
    }
    return new static($context['player'], $data['ip'], $data['time']);
  }

  protected function prepareArrayConversion() {
    $this->addPersistantProperties(array('ip', 'time'));
    $this->addDependantObjects(array('player' => 'getId'));
    return parent::prepareArrayConversion();
  }

  public static function createSignal(HumanPlayerInterface $player, $ip) {
    return new static($player, $ip, time());
  }

  public function getTime() {
    return $this->time;
  }

  public function getIp() {
    return $this->ip;
  }

  protected function getPlayer() {
    return $this->player;
  }

}
