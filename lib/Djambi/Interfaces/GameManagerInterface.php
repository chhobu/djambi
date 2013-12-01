<?php
namespace Djambi\Interfaces;
use Djambi\Faction;
use Djambi\GameDisposition;
use Djambi\Player;
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
   * @param
   *   Nom de la fonction appelant la méthode
   *
   * @return GameManagerInterface
   */
  public function save($called_from);

  /**
   * Recharge une partie.
   *
   * @return GameManagerInterface
   */
  public function reload();

  /**
   * Réinitialise la partie à la dernière sauvegarde effectuée.
   *
   * @return GameManagerInterface
   */
  public function rollback();

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
  public static function createGame($players, $id, $mode, GameDisposition $disposition, $battlefield_factory = NULL);

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
   *
   * @param Signal $signal
   *
   * @return GameManagerInterface
   */
  public function listenSignal(Signal $signal);

  /**
   * Exclut un joueur d'une partie.
   *
   * @param Player $player
   *
   * @return GameManagerInterface
   */
  public function ejectPlayer(Player $player);

  /**
   * Ajoute un joueur sur la partie en cours.
   *
   * @param Player $player
   * @param Faction $faction
   *
   * @return GameManagerInterface
   */
  public function addNewPlayer(Player $player, Faction $faction);

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
   * @return GameManagerInterface
   */
  public function setStatus($status);

  /**
   * Renvoie le mode de jeu.
   *
   * @return string
   */
  public function getMode();

  /**
   * Renvoie la disposition de la grille de jeu.
   *
   * @return GameDisposition
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
   *
   * @return bool
   */
  public function isNotBegin();

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
   * @return mixed
   */
  public function setOption($option_key, $value);
}
