<?php
namespace Djambi\GameManagers;

use Djambi\GameDispositions\BaseGameDisposition;
use Djambi\Gameplay\BattlefieldInterface;
use Djambi\Players\PlayerInterface;

/**
 * Interface GameManagerInterface
 */
interface PlayableGameInterface {

  /**
   * Crée une partie.
   *
   * @param PlayerInterface[] $players
   * @param string $id
   * @param BaseGameDisposition $disposition
   * @param string $battlefield_class
   *
   * @return PlayableGameInterface
   */
  public static function create($players, $id, BaseGameDisposition $disposition, $battlefield_class = NULL);

  /**
   * Lance les actions permettant de rendre une partie jouable.
   *
   * @return PlayableGameInterface
   */
  public function play();

  /**
   * Signale un changement à l'ensemble des joueurs.
   *
   * @return PlayableGameInterface
   */
  public function propagateChanges();

  /**
   * Renvoie le champ de bataille associé au jeu...
   *
   * @return BattlefieldInterface
   */
  public function getBattlefield();

  /**
   * Renvoie une information associée à la partie en cours.
   *
   * @param string $info
   *
   * @return mixed
   */
  public function getInfo($info);

  /**
   * Associe une information à la partie en cours.
   *
   * @param string $info
   * @param mixed $value
   *
   * @return PlayableGameInterface
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
   * Renvoie le statut de la partie.
   *
   * @return string
   */
  public function getStatus();

  /**
   * Change le statut de la partie.
   *
   * @param string $status
   *
   * @return PlayableGameInterface
   */
  public function setStatus($status);

  /**
   * Renvoie la disposition de la grille de jeu.
   *
   * @return BaseGameDisposition
   */
  public function getDisposition();

  /**
   * Renvoie l'identifiant de la partie.
   *
   * @return string
   */
  public function getId();

  /**
   * Détermine si une partie est en cours.
   *
   * @return bool
   */
  public function isPending();

  /**
   * Détermine si une partie est terminée.
   *
   * @return bool
   */
  public function isFinished();

  /**
   * Détermine si une partie n'est pas commencée.
   * @return bool
   */
  public function isNew();

  /**
   * Détermine si les annulations d'action sont autorisées.
   *
   * @return bool
   */
  public function isCancelActionAllowed();

  /**
   * Récupère la valeur d'une option de jeu.
   *
   * @param $option_key
   *
   * @return mixed
   */
  public function getOption($option_key);

  /**
   * Change une option de jeu.
   *
   * @param $option_key
   * @param $value
   *
   * @return PlayableGameInterface
   */
  public function setOption($option_key, $value);

}
