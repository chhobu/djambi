<?php
namespace Djambi\Players;

use Djambi\GameManagers\Signal;

class HumanPlayer extends BasePlayer implements HumanPlayerInterface {
  /** @var bool */
  protected $emptySeat = TRUE;
  /** @var Signal */
  protected $lastSignal;
  /** @var int */
  protected $joined;

  /**
   * @return bool
   */
  public function isEmptySeat() {
    return $this->emptySeat;
  }

  protected function setEmptySeat($empty) {
    $this->emptySeat = $empty ? TRUE : FALSE;
    return $this;
  }

  /**
   * @return Signal;
   */
  public function getLastSignal() {
    return $this->lastSignal;
  }

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

  protected function setJoined($joined) {
    $this->joined = $joined;
    return $this;
  }

  public static function createEmptyHumanPlayer() {
    return new static();
  }

  public function useSeat() {
    if ($this->isEmptySeat()) {
      $this->setJoined(time());
      $this->setEmptySeat(FALSE);
    }
    return $this;
  }

  protected function prepareArrayConversion() {
    $this->addPersistantProperties(array('emptySeat', 'joined', 'lastSignal'));
    return parent::prepareArrayConversion();
  }

  public static function fromArray(array $data, array $context = array()) {
    /* @var \Djambi\Players\HumanPlayer $player */
    $player = parent::fromArray($data);
    if (isset($data['emptySeat'])) {
      $player->setEmptySeat($data['emptySeat']);
    }
    if (!$player->isEmptySeat()) {
      $player->setJoined(!empty($data['joined']) ? $data['joined'] : time());
    }
    if (!empty($data['lastSignal'])) {
      $player->setLastSignal(Signal::fromArray($data['lastSignal'], array('player' => $player)));
    }
    return $player;
  }

}
