<?php

namespace Djambi\GameManagers;

use Djambi\Exceptions\GameNotFoundException;
use Djambi\GameDisposition;
use Djambi\GameManager;

class SessionGameManager extends GameManager {
  public static function createGame($players, $id, $mode, GameDisposition $disposition, $battlefield_factory = NULL) {
    $gm = parent::createGame($players, $id, $mode, $disposition, $battlefield_factory);
    if ($gm instanceof SessionGameManager) {
      $gm->saveInSession();
    }
    return $gm;
  }

  public static function loadGame($data) {
    if (!isset($data['id'])) {
      throw new GameNotFoundException("Missing ID parameter for loading game.");
    }
    $id = $data['id'];
    if (empty($_SESSION['djambi']['games'][$id])) {
      throw new GameNotFoundException("Unable to retrieve required game through session data.");
    }
    $data = $_SESSION['djambi']['games'][$id];
    $data['id'] = $id;
    return parent::loadGame($data);
  }

  public function save($called_from) {
    parent::save($called_from);
    $this->saveInSession();
    return $this;
  }

  protected function saveInSession() {
    $initial_state = $this->getInitialState();
    if (!empty($initial_state)) {
      $_SESSION['djambi']['games'][$this->getId()] = $initial_state;
    }
    return $this;
  }

}
