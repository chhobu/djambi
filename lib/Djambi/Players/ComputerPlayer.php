<?php
namespace Djambi\Players;

use Djambi\IA\DummyIA;
use Djambi\Exceptions\PlayerInvalidException;
use Djambi\Interfaces\IAInterface;

class ComputerPlayer extends BasePlayer {
  const CLASS_NICKNAME = 'bot-';

  /* @var IAInterface $ia; */
  private $ia;

  public function __construct($id = NULL) {
    parent::__construct($id);
    $this->setType(self::TYPE_COMPUTER);
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
      if ($ia instanceof IAInterface) {
        $this->ia = $ia;
        return $this;
      }
    }
    throw new PlayerInvalidException("Computer player must use an extended IA class");
  }

  public static function fromArray(array $data, array $context = array()) {
    $player = parent::fromArray($data, $context);
    if ($player instanceof ComputerPlayer && !empty($data['ia'])) {
      $player->useIa($data['ia']);
      $player->getIa()->setSettings($data['ia_settings']);
    }
    else {
      throw new PlayerInvalidException("Failed loading computer player");
    }
    return $player;
  }

  protected function prepareArrayConversion() {
    $this->addPersistantProperties(array('ia'));
    parent::prepareArrayConversion();
  }

}
