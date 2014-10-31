<?php
/**
 * @file
 * Déclaration de la class DjambiGameManager permettant de créer
 * et de gérer la persistance de parties de Djambi.
 */

namespace Djambi\GameManagers;

use Djambi\Enums\Status;
use Djambi\Enums\StatusEnum;
use Djambi\Exceptions\GameNotFoundException;
use Djambi\Exceptions\GameOptionInvalidException;
use Djambi\Exceptions\GridInvalidException;
use Djambi\GameDispositions\BaseGameDisposition;
use Djambi\Gameplay\Battlefield;
use Djambi\Gameplay\BattlefieldInterface;
use Djambi\Persistance\ArrayableInterface;
use Djambi\Persistance\PersistantDjambiTrait;
use Djambi\Players\HumanPlayer;
use Djambi\Players\PlayerInterface;

/**
 * Class DjambiGameManager
 * Gère la persistance des parties de Djambi.
 */
abstract class BaseGameManager implements PlayableGameInterface, ArrayableInterface {

  use PersistantDjambiTrait;

  /** @var string */
  protected $id;
  /** @var Battlefield $battlefield */
  protected $battlefield;
  /** @var int $changed */
  protected $changed;
  /** @var int $created */
  protected $begin;
  /** @var array $infos */
  protected $infos = array();
  /** @var string */
  protected $status;
  /** @var BaseGameDisposition */
  protected $disposition;

  protected function prepareArrayConversion() {
    $this->addPersistantProperties(array(
      'id',
      'changed',
      'begin',
      'status',
      'infos',
      'disposition',
      'battlefield',
    ));
    return $this;
  }

  public static function fromArray(array $data, array $context = array()) {
    if (!isset($data['id']) || !isset($data['status']) || !isset($data['disposition'])) {
      throw new GameNotFoundException("Missing required mode information for loading a game.");
    }
    $game = new static($data['id']);
    if (isset($data['begin'])) {
      $game->setBegin($data['begin']);
    }
    if (isset($data['changed'])) {
      $game->setChanged($data['changed']);
    }
    if (!empty($data['infos'])) {
      foreach ($data['infos'] as $info => $value) {
        $game->setInfo($info, $value);
      }
    }
    $game->setStatus($data['status']);
    $game->setDisposition(call_user_func($data['disposition']['className'] . '::fromArray', $data['disposition'], $context));
    if (!empty($data['battlefield'])) {
      $context['gameManager'] = $game;
      $game->setBattlefield(call_user_func($data['battlefield']['className'] . '::fromArray', $data['battlefield'], $context));
    }
    return $game;
  }

  /**
   * Empêche la création directe d'un GameManager.
   *
   * @param int $id
   */
  protected function __construct($id = NULL) {
    if (is_null($id)) {
      $id = uniqid();
    }
    $this->setId($id);
  }

  /**
   * Implements create().
   *
   * @param PlayerInterface[] $players
   * @param string $id
   * @param BaseGameDisposition $disposition
   * @param String $battlefield_class
   *
   * @throws GridInvalidException
   * @return static
   */
  public static function create($players, $id, BaseGameDisposition $disposition, $battlefield_class = NULL) {
    /* @var BaseGameManager $game */
    $game = new static($id);
    $game->setDisposition($disposition);
    $game->addDefaultPlayers($players);
    if (is_null($battlefield_class) || $battlefield_class == '\Djambi\Gameplay\Battlefield') {
      $grid = Battlefield::createNewBattlefield($game, $players);
    }
    else {
      $grid = call_user_func_array($battlefield_class . '::createNewBattlefield',
        array($game, $players));
      $game->setInfo('battlefield_class', $battlefield_class);
    }
    $game->setBattlefield($grid);
    $time = time();
    $game->setBegin($time);
    $game->setChanged($time);
    return $game;
  }

  protected function addDefaultPlayers(&$players) {
    if (empty($players)) {
      $players[] = HumanPlayer::createEmptyHumanPlayer();
    }
    return $this;
  }

  public function getId() {
    return $this->id;
  }

  protected function setId($id) {
    if (is_null($id)) {
      $this->id = uniqid($id);
    }
    else {
      $this->id = $id;
    }
    return $this;
  }

  protected function setDisposition(BaseGameDisposition $disposition) {
    $this->disposition = $disposition;
    return $this;
  }

  public function getDisposition() {
    return $this->disposition;
  }

  public function setStatus($status) {
    if (StatusEnum::getStatus($status) instanceof Status) {
      $this->status = $status;
    }
    return $this;
  }

  public function getStatus() {
    return $this->status;
  }

  /**
   * @return bool
   */
  public function isPending() {
    return in_array(StatusEnum::getStatus($this->getStatus()), StatusEnum::getPendingStatuses());
  }

  /**
   * @return bool
   */
  public function isFinished() {
    return in_array(StatusEnum::getStatus($this->getStatus()), StatusEnum::getFinishedStatuses());
  }

  /**
   * @return bool
   */
  public function isNew() {
    return in_array(StatusEnum::getStatus($this->getStatus()), StatusEnum::getNewStatuses());
  }

  /**
   * Renvoie la grille de Djambi associée au jeu.
   */
  public function getBattlefield() {
    return $this->battlefield;
  }

  /**
   * Associe une grille de Djambi au jeu.
   *
   * @param BattlefieldInterface $grid
   *   Grille de Djambi
   *
   * @return BaseGameManager
   */
  protected function setBattlefield(BattlefieldInterface $grid) {
    $this->battlefield = $grid;
    return $this;
  }

  public function getChanged() {
    return $this->changed;
  }

  public function getBegin() {
    return $this->begin;
  }

  protected function setBegin($begin) {
    $this->begin = $begin;
    return $this;
  }

  protected function setChanged($changed) {
    $this->changed = $changed;
    return $this;
  }

  public function propagateChanges() {
    $this->setChanged(time());
  }

  public function play() {
    if ($this->getStatus() == StatusEnum::STATUS_PENDING) {
      $this->getBattlefield()->prepareTurn();
    }
    return $this;
  }

  public function getInfo($info) {
    if (!isset($this->infos[$info])) {
      return FALSE;
    }
    return $this->infos[$info];
  }

  public function setInfo($info, $value) {
    $this->infos[$info] = $value;
    return $this;
  }

  public function getOption($option_key) {
    try {
      return $this->getDisposition()
        ->getOptionsStore()
        ->retrieve($option_key)
        ->getValue();
    } catch (GameOptionInvalidException $e) {
      return NULL;
    }
  }

  public function setOption($option_key, $value) {
    $this->getDisposition()
      ->getOptionsStore()
      ->retrieve($option_key)
      ->setValue($value);
    return $this;
  }

}
