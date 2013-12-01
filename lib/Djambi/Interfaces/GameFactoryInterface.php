<?php

namespace Djambi\Interfaces;


interface GameFactoryInterface {
  /**
   * Instancie une nouvelle partie.
   *
   * @return GameManagerInterface
   */
  public function createGameManager();
}
