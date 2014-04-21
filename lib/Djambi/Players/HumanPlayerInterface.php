<?php
namespace Djambi\Players;

use Djambi\GameManagers\Signal;

interface HumanPlayerInterface extends PlayerInterface {

  /**
   * @return bool
   */
  public function isEmptySeat();

  /**
   * @return Signal;
   */
  public function getLastSignal();

  /**
   * @return int
   */
  public function getJoined();

  /**
   * @return HumanPlayerInterface
   */
  public static function createEmptyHumanPlayer();

  /**
   * @return HumanPlayerInterface
   */
  public function useSeat();

}
