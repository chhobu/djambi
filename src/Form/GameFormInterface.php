<?php
/**
 * Created by PhpStorm.
 * User: buchho
 * Date: 25/05/14
 * Time: 16:11
 */

namespace Drupal\djambi\Form;


use Djambi\GameManagers\GameManagerInterface;
use Drupal\djambi\Players\Drupal8Player;

interface GameFormInterface {

  /**
   * @return $this
   */
  public function createGameManager();

  /**
   * @return $this
   */
  public function resetGameManager();

  /**
   * @return GameManagerInterface
   */
  public function getGameManager();

  /**
   * @return Drupal8Player
   */
  public function getCurrentPlayer();

  /**
   * @return String
   */
  public function getGameId();

  /**
   * @param $string
   * @param $args
   *
   * @return string
   */
  public function translateDjambiStrings($string, $args);
}
