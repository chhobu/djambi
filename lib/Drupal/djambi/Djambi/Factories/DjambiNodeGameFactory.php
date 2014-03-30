<?php

namespace Drupal\kw_djambi\Djambi\Factories;

use Djambi\Exceptions\Exception;
use Djambi\Interfaces\GameFactoryInterface;
use Drupal\kw_djambi\Djambi\GameManagers\DrupalGameManager;
use Drupal\kw_djambi\Djambi\Traits\DrupalDjambiFormTrait;

class DjambiNodeGameFactory implements GameFactoryInterface {
  /** @var  \stdClass */
  private $node;

  use DrupalDjambiFormTrait;

  public function __construct($node) {
    $this->node = $node;
  }

  protected function setNode($node) {
    $this->node = $node;
  }

  protected function getNode() {
    return $this->node;
  }

  public function createGameManager() {
    try {
      $game = DrupalGameManager::loadGame(array('nid' => $this->getNode()->nid));
      $game->play();
      return $game;
    }
    catch (Exception $e) {
      watchdog('djambi', 'Unable to load Djambi game from node !nid : exception type %exc with message "@message" (file %file, line %line)', array(
        '!nid' => $this->getNode()->nid,
        '%exc' => get_class($e),
        '@message' => $e->getMessage(),
        '%file' => $e->getFile(),
        '%line' => $e->getLine(),
      ), WATCHDOG_WARNING);
      drupal_not_found();
      die();
    }
  }
}
