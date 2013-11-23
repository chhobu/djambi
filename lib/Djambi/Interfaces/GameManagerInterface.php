<?php
namespace Djambi\Interfaces;
use Djambi\GameDisposition;
use Djambi\Signal;

/**
 * Interface GameManagerInterface
 */
interface GameManagerInterface {

  /**
   * Lance les actions permettant de rendre une partie jouable.
   *
   * @return GameManagerInterface
   */
  public function play();

  /**
   * Sauvegarde une partie.
   *
   * @return GameManagerInterface
   */
  public function save();

  /**
   * Recharge une partie.
   *
   * @return GameManagerInterface
   */
  public function reload();

  /**
   * Charge une partie.
   *
   * @param array $data
   *
   * @return GameManagerInterface
   */
  public static function loadGame($data);

  /**
   * Crée une partie.
   *
   * @param PlayerInterface[] $players
   * @param string $id
   * @param string $mode
   * @param \Djambi\GameDisposition $disposition
   * @param string $battlefield_factory
   *
   * @return GameManagerInterface
   */
  public static function create($players, $id, $mode, GameDisposition $disposition, $battlefield_factory = NULL);

  /**
   * Supprime une partie.
   */
  public function delete();

  /**
   * @return BattlefieldInterface
   */
  public function getBattlefield();

  /**
   * @param string $info
   *
   * @return mixed
   */
  public function getInfo($info);

  /**
   * @param string $info
   * @param mixed $value
   *
   * @return BattlefieldInterface
   */
  public function setInfo($info, $value);

  /**
   * Renvoie le timestamp de la dernière modification du jeu.
   *
   * @return int
   */
  public function getChanged();

  /**
   * Renvoie le timestamp de la création du jeu.
   *
   * @return int
   */
  public function getBegin();

  /**
   * Récupère et gère la persistance des signaux envoyés par les utilisateurs.
   */
  public function listenSignal(Signal $signal);

}
