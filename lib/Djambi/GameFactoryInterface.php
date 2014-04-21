<?php
namespace Djambi;

use Djambi\GameManagers\GameManagerInterface;

interface GameFactoryInterface {
  /**
   * Instancie une nouvelle partie.
   *
   * @return GameManagerInterface
   */
  public function createGameManager();
}
