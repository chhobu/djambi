<?php
namespace Djambi\Interfaces;


use Djambi\Signal;

interface HumanPlayerInterface extends PlayerInterface {

  /**
   * Enregistre les données nécessaires pour participer à une partie.
   *
   * @param array $data
   *
   * @return HumanPlayerInterface
   */
  public function register(array $data = NULL);

  /**
   * @return bool
   */
  public function isRegistered();

  /**
   * @return Signal;
   */
  public function getLastSignal();

  /**
   * @param Signal $signal
   *
   * @return HumanPlayerInterface
   */
  public function setLastSignal(Signal $signal);

  /**
   * @return int
   */
  public function getJoined();

  /**
   * @param int $joined
   *
   * @return HumanPlayerInterface
   */
  public function setJoined($joined);

}
