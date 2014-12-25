<?php
/**
 * Created by PhpStorm.
 * User: buchho
 * Date: 29/10/14
 * Time: 01:14
 */

namespace Djambi\GameDispositions;


use Djambi\GameOptions\GameOptionsStore;
use Djambi\Grids\GridInterface;

interface DispositionInterface {

  /**
   * @return int
   */
  public static function getNbPlayers();

  /**
   * @return GridInterface
   */
  public function getGrid();

  /**
   * @return GameOptionsStore
   */
  public function getOptionsStore();

}