<?php
namespace Djambi\Players;

use Djambi\Exceptions\Exception;
use Djambi\IA;
use Djambi\IA\DummyIA;
use Djambi\Player;
use Djambi\Exceptions\PlayerInvalidException;

class ComputerPlayer extends Player {
  /* @var IA $ia; */
  private $ia;

  public function __construct($id = NULL) {
    $this->setType(self::TYPE_COMPUTER);
    $this->setClassName();
    $this->setId($id, 'Bot-');
  }

  public function getIa() {
    if (is_null($this->ia)) {
      $this->ia = DummyIA::instanciate($this);
    }
    return $this->ia;
  }

  public function useIa(IA $îa) {
    $this->ia = $îa;
    return $this;
  }

  public static function loadPlayer(array $data) {
    $player = parent::loadPlayer($data);
    $ia_method = 'instanciate';
    if ($player instanceof ComputerPlayer && !empty($data['ia'])
    && class_exists($data['ia']) && method_exists($data['ia'], $ia_method)) {
      $ia = call_user_func_array($data['ia'] . '::' . $ia_method, array($player));
      if ($ia instanceof IA) {
        $player->useIa($ia);
      }
      else {
        throw new PlayerInvalidException("Computer player must use an extended IA class");
      }
    }
    else {
      throw new Exception("Failed loading computer player");
    }
    return $player;
  }

  public function saveToArray() {
    $data = array(
      'className' => $this->getClassName(),
      'ia' => get_class($this->getIa()),
      'id' => $this->getId(),
    );
    return $data;
  }

}
