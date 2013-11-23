<?php
namespace Djambi\Players;

use Djambi\IA;
use Djambi\Player;

class ComputerPlayer extends Player {
  /* @var IA $ia; */
  protected $ia;

  public function __construct($id = NULL) {
    $this->type = 'computer';
    $this->className = get_class($this);
    $this->setId($id, 'Bot-');
  }

  public function getIa() {
    return $this->ia;
  }

  public function useIa($ia_class) {
    $this->ia = IA::useIA($this, $ia_class);
  }

  public static function loadPlayer(array $data) {
    /* @var \Djambi\Players\ComputerPlayer $player */
    $player = parent::loadPlayer($data);
    $player->useIa($data['ia']);
    return $player;
  }

  public function saveToArray() {
    $data = array(
      'className' => $this->getClassName(),
      'ia' => $this->getIa()->getClassName(),
      'id' => $this->getId(),
    );
    return $data;
  }

}
