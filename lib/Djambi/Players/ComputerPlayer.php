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

  public function useIa($class_name) {
    $ia_method = 'instanciate';
    if (class_exists($class_name) && method_exists($class_name, $ia_method)) {
      $ia = call_user_func_array($class_name . '::' . $ia_method, array($this));
      if ($ia instanceof IA) {
        $this->ia = $ia;
        return $this;
      }
    }
    throw new PlayerInvalidException("Computer player must use an extended IA class");
  }

  public static function loadPlayer(array $data) {
    $player = parent::loadPlayer($data);
    if ($player instanceof ComputerPlayer && !empty($data['ia'])) {
      $player->useIa($data['ia']);
      $player->getIa()->setSettings($data['ia_settings']);
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
      'ia_settings' => $this->getIa()->getSettings(),
      'id' => $this->getId(),
    );
    return $data;
  }

}
