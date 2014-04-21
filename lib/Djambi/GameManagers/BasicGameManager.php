<?php
/**
 * @file
 * Déclaration de la class DjambiGameManager permettant de créer
 * et de gérer la persistance de parties de Djambi.
 */

namespace Djambi\GameManagers;

use Djambi\Exceptions\DisallowedActionException;
use Djambi\Exceptions\GameNotFoundException;
use Djambi\Exceptions\GameOptionInvalidException;
use Djambi\Exceptions\GridInvalidException;
use Djambi\Exceptions\UnpersistableObjectException;
use Djambi\GameDispositions\BaseGameDisposition;
use Djambi\Gameplay\Battlefield;
use Djambi\Gameplay\BattlefieldInterface;
use Djambi\Gameplay\Faction;
use Djambi\PersistantDjambiObject;
use Djambi\Players\PlayerInterface;

/**
 * Class DjambiGameManager
 * Gère la persistance des parties de Djambi.
 */
class BasicGameManager extends PersistantDjambiObject implements GameManagerInterface {
  const MODE_SANDBOX = 'bac-a-sable';
  const MODE_FRIENDLY = 'amical';
  const MODE_TRAINING = 'training';

  const STATUS_PENDING = 'pending';
  const STATUS_FINISHED = 'finished';
  const STATUS_DRAW_PROPOSAL = 'draw_proposal';
  const STATUS_RECRUITING = 'recruiting';

  /** @var string */
  private $id;
  /** @var Battlefield $battlefield */
  private $battlefield;
  /** @var int $changed */
  private $changed;
  /** @var int $created */
  private $begin;
  /** @var array $infos */
  protected $infos = array();
  /** @var string */
  private $mode;
  /** @var string */
  private $status;
  /** @var BaseGameDisposition */
  private $disposition;

  protected function prepareArrayConversion() {
    $this->addPersistantProperties(array(
      'id',
      'changed',
      'begin',
      'mode',
      'status',
      'infos',
      'disposition',
      'battlefield',
    ));
    return parent::prepareArrayConversion();
  }

  public static function fromArray(array $data, array $context = array()) {
    if (!isset($data['mode']) || !isset($data['id']) || !isset($data['status']) || !isset($data['disposition'])) {
      throw new GameNotFoundException("Missing required mode information for loading a game.");
    }
    $game = new static($data['mode'], $data['id']);
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
   */
  protected function __construct($mode, $id = NULL) {
    if (is_null($id)) {
      $id = uniqid();
    }
    $this->setId($id);
    $this->setMode($mode);
  }

  /**
   * Implements create().
   *
   * @param PlayerInterface[] $players
   * @param string $id
   * @param string $mode
   * @param BaseGameDisposition $disposition
   * @param null $battlefield_factory
   *
   * @throws GridInvalidException
   * @return static
   */
  public static function createGame($players, $id, $mode, BaseGameDisposition $disposition, $battlefield_factory = NULL) {
    /* @var BasicGameManager $game */
    $game = new static($mode, $id);
    $game->setDisposition($disposition);
    if (is_null($battlefield_factory)) {
      $grid = Battlefield::createNewBattlefield($game, $players);
    }
    else {
      $grid = call_user_func_array($battlefield_factory . '::createNewBattlefield',
        array($game, $players));
      $game->setInfo('battlefield_factory', $battlefield_factory);
    }
    $game->setBattlefield($grid);
    $time = time();
    $game->setBegin($time);
    $game->setChanged($time);
    return $game;
  }

  public static function load($mode, $id) {
    throw new UnpersistableObjectException("Standard game manager cannot be persisted !");
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

  /**
   * Liste les modes de jeu.
   *
   * @param bool $with_description
   *   TRUE pour inclure une description des modes de jeu
   * @param bool $with_hidden
   *   TRUE pour inclure les modes de jeu cachés
   *
   * @return array
   *   Tableau contenant les différents modes de jeu disponibles.
   */
  public static function getModes($with_description = FALSE, $with_hidden = FALSE) {
    $modes = array(
      static::MODE_FRIENDLY => 'MODE_FRIENDLY_DESCRIPTION',
      static::MODE_SANDBOX => 'MODE_SANDBOX_DESCRIPTION',
    );
    $hidden_modes = array(
      static::MODE_TRAINING => 'MODE_TRAINING_DESCRIPTION',
    );
    if ($with_hidden) {
      $modes = array_merge($modes, $hidden_modes);
    }
    if ($with_description) {
      return $modes;
    }
    else {
      return array_keys($modes);
    }
  }

  protected function setMode($mode) {
    $this->mode = $mode;
    return $this;
  }

  public function getMode() {
    return $this->mode;
  }

  /**
   * Liste les différentes statuts de jeu.
   *
   * @param array $options
   *   Tableau associatif d'options, pouvant contenir les éléments suivants :
   *   - with_description
   *   TRUE pour renvoyer la description des états.
   *   - with_recruiting
   *   TRUE pour inclure également les états avant le début du jeu.
   *   - with_pending
   *   TRUE pour inclure également les états parties en cours
   *    - $with_finished
   *   TRUE pour inclure également les états parties terminées
   *
   * @return array:
   *   Tableau contenant les différents statuts disponibles.
   */
  public static function getStatuses(array $options = NULL) {
    $with_description = isset($options['with_description']) ? $options['with_description'] : FALSE;
    $with_recruiting = isset($options['with_recruiting']) ? $options['with_recruiting'] : TRUE;
    $with_pending = isset($options['with_pending']) ? $options['with_pending'] : TRUE;
    $with_finished = isset($options['with_finished']) ? $options['with_finished'] : TRUE;
    $statuses = array();
    if ($with_recruiting) {
      $statuses[self::STATUS_RECRUITING] = 'STATUS_RECRUITING_DESCRIPTION';
    }
    if ($with_pending) {
      $statuses[self::STATUS_PENDING] = 'STATUS_PENDING_DESCRIPTION';
      $statuses[self::STATUS_DRAW_PROPOSAL] = 'STATUS_DRAW_PROPOSAL_DESCRIPTION';
    }
    if ($with_finished) {
      $statuses[self::STATUS_FINISHED] = 'STATUS_FINISHED_DESCRIPTION';
    }
    if ($with_description) {
      return $statuses;
    }
    else {
      return array_keys($statuses);
    }
  }

  public function setStatus($status) {
    $this->status = $status;
    return $this;
  }

  public function getStatus() {
    return $this->status;
  }

  /**
   * @return bool
   */
  public function isPending() {
    if (in_array($this->getStatus(), array(
        self::STATUS_PENDING,
        self::STATUS_DRAW_PROPOSAL,
      ))
    ) {
      return TRUE;
    }
    else {
      return FALSE;
    }
  }

  /**
   * @return bool
   */
  public function isFinished() {
    if ($this->getStatus() == self::STATUS_FINISHED) {
      return TRUE;
    }
    else {
      return FALSE;
    }
  }

  /**
   * @return bool
   */
  public function isNotBegin() {
    if ($this->getStatus() == self::STATUS_RECRUITING) {
      return TRUE;
    }
    else {
      return FALSE;
    }
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
   * @return BasicGameManager
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

  protected function preSave() {
    $this->setChanged(time());
  }

  public function save() {
    $this->preSave();
    return $this;
  }

  /**
   * Recharge la partie en cours.
   * @return BasicGameManager
   */
  public function reload() {
    return $this;
  }

  /**
   * Lance les actions permettant de rendre une partie jouable.
   */
  public function play() {
    if ($this->getStatus() == self::STATUS_PENDING) {
      $this->getBattlefield()->prepareTurn();
    }
    return $this;
  }

  public function delete() {
    $this->battlefield = NULL;
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
    }
    catch (GameOptionInvalidException $e) {
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

  public function listenSignal(Signal $signal) {
    return $this;
  }

  public function ejectPlayer(PlayerInterface $player) {
    $grid = $this->getBattlefield();
    if ($this->getStatus() == self::STATUS_RECRUITING) {
      $nb_playing_factions = 0;
      foreach ($grid->getFactions() as $faction) {
        if ($player->isPlayingFaction($faction)) {
          $faction->removePlayer();
        }
        if ($faction->getStatus() == Faction::STATUS_READY) {
          $nb_playing_factions++;
        }
      }
      if ($nb_playing_factions == 0) {
        $this->delete();
      }
      else {
        $grid->logEvent('info', 'TEAM_EXIT', array('!player' => $player->displayName()));
        $this->save(__CLASS__);
      }
    }
    else {
      throw new DisallowedActionException("Cannot remove player after game begin.", 1);
    }
    return $this;
  }

  public function addNewPlayer(PlayerInterface $player, Faction $target) {
    $nb_empty_factions = 0;
    $grid = $this->getBattlefield();
    if ($this->getStatus() != self::STATUS_RECRUITING) {
      throw new DisallowedActionException("Cannot add new player after game begin.", 1);
    }
    foreach ($grid->getFactions() as $faction) {
      if ($faction->getId() != $target->getId() && $player->isPlayingFaction($faction)) {
        $faction->removePlayer();
      }
      if ($faction->getStatus() == Faction::STATUS_EMPTY_SLOT) {
        $nb_empty_factions++;
      }
    }
    if ($target->getStatus() == Faction::STATUS_EMPTY_SLOT) {
      $target->changePlayer($player);
      $nb_empty_factions -= 1;
    }
    else {
      $exception = new DisallowedActionException("Trying to add player in a non-empty slot.", 2);
      throw $exception;
    }
    if ($nb_empty_factions == 0) {
      $this->setStatus(self::STATUS_PENDING)->play();
    }
    $grid->logEvent('info', 'NEW_TEAM', array(
      'faction1' => $target->getId(),
      '!player' => $player->displayName(),
    ));
    $this->save();
    return $this;
  }

}
