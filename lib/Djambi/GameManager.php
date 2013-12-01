<?php
/**
 * @file
 * Déclaration de la class DjambiGameManager permettant de créer
 * et de gérer la persistance de parties de Djambi.
 */

namespace Djambi;
use Djambi\Exceptions\DisallowedActionException;
use Djambi\Exceptions\DispositionNotFoundException;
use Djambi\Exceptions\GameOptionInvalidException;
use Djambi\Factories\GameDispositionsFactory;
use Djambi\Interfaces\BattlefieldInterface;
use Djambi\Interfaces\GameManagerInterface;

/**
 * Class DjambiGameManager
 * Gère la persistance des parties de Djambi.
 */
class GameManager implements GameManagerInterface {
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
  /** @var bool $new */
  private $new = FALSE;
  /** @var int $changed */
  private $changed;
  /** @var int $created */
  private $begin;
  /** @var array $infos */
  protected $infos = array();
  /** @var array $initialState */
  private $initialState;
  /** @var string */
  private $mode;
  /** @var string */
  private $status;
  /** @var GameDisposition */
  private $disposition;

  /**
   * Empêche la création directe d'un GameManager.
   */
  protected function __construct($id, $mode) {
    $this->setId($id);
    $this->setMode($mode);
  }

  /**
   * Implements create().
   */
  public static function createGame($players, $id, $mode, GameDisposition $disposition, $battlefield_factory = NULL) {
    /* @var GameManager $gm */
    $gm = new static($id, $mode);
    $gm->setDisposition($disposition);
    if (is_null($battlefield_factory)) {
      $grid = Battlefield::createNewBattlefield($gm, $players);
    }
    else {
      $grid = call_user_func_array($battlefield_factory . '::createNewBattlefield',
        array($gm, $players));
      $gm->setInfo('battlefield_factory', $battlefield_factory);
    }
    $gm->setBattlefield($grid);
    $time = time();
    $gm->setBegin($time);
    $gm->setChanged($time);
    $gm->setNew(TRUE);
    $gm->setInitialState($grid->toArray());
    return $gm;
  }

  /**
   * Implements load().
   */
  public static function loadGame($data) {
    if (!is_array($data) || !isset($data['mode'])) {
      throw new GameOptionInvalidException("Missing required mode information for loading a game.");
    }
    if (!isset($data['id'])) {
      $data['id'] = NULL;
    }
    /* @var $gm GameManager */
    $gm = new static($data['id'], $data['mode']);
    $gm->setNew(FALSE);
    $gm->loadBattlefield($data);
    $gm->setInitialState($data);
    return $gm;
  }

  protected function loadBattlefield($data) {
    if (isset($data['begin'])) {
      $this->setBegin($data['begin']);
    }
    if (isset($data['changed'])) {
      $this->setChanged($data['changed']);
    }
    if (isset($data['scheme_settings'])) {
      $scheme_settings = $data['scheme_settings'];
    }
    else {
      $scheme_settings = NULL;
    }
    if (isset($data['infos']['disposition_factory'])) {
      $disposition = call_user_func_array($data['infos']['disposition_factory'] . '::loadDisposition',
        array($scheme_settings));
    }
    elseif (isset($data['disposition'])) {
      $disposition = GameDispositionsFactory::loadDisposition($data['disposition'], $scheme_settings);
    }
    else {
      throw new DispositionNotFoundException("Missing required disposition data for loading a game.");
    }
    $this->setDisposition($disposition);
    if (isset($data['infos']['battlefield_factory'])) {
      $battlefield = call_user_func_array($data['infos']['battlefield_factory'] . '::loadBattlefield',
        array($this, $data));
    }
    else {
      $battlefield = Battlefield::loadBattlefield($this, $data);
    }
    if (!empty($data['infos'])) {
      foreach ($data['infos'] as $info => $value) {
        $this->setInfo($info, $value);
      }
    }
    $this->setBattlefield($battlefield);
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

  protected function setDisposition(GameDisposition $disposition) {
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
    if (in_array($this->getStatus(), array(self::STATUS_PENDING, self::STATUS_DRAW_PROPOSAL))) {
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
   * @return GameManager
   */
  protected function setBattlefield(BattlefieldInterface $grid) {
    $this->battlefield = $grid;
    return $this;
  }

  protected function setInitialState($data) {
    $data['begin'] = $this->getBegin();
    $data['changed'] = $this->getChanged();
    $this->initialState = $data;
    return $this;
  }

  protected function getInitialState() {
    return $this->initialState;
  }

  public function isNew() {
    return $this->new;
  }

  protected function setNew($is_new) {
    $this->new = $is_new;
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

  /**
   * Sauvegarde la partie, en générant un tableau de données.
   */
  public function save($called_from) {
    $this->setChanged(time());
    $this->setInitialState($this->getBattlefield()->toArray());
    return $this;
  }

  /**
   * Recharge la partie en cours.
   *
   * @return GameManager
   */
  public function reload() {
    return $this;
  }

  /**
   * Réinitialise la partie à la dernière sauvegarde effectuée.
   *
   * @return GameManager
   */
  public function rollback() {
    $initial_state = $this->getInitialState();
    if (!empty($initial_state)) {
      $this->loadBattlefield($initial_state);
      if (!$this->isFinished()) {
        $this->play();
      }
    }
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
      return $this->getDisposition()->getOptionsStore()->retrieve($option_key)->getValue();
    }
    catch(GameOptionInvalidException $e) {
      return NULL;
    }
  }

  public function setOption($option_key, $value) {
    $this->getDisposition()->getOptionsStore()->retrieve($option_key)->setValue($value);
    return $this;
  }

  public function listenSignal(Signal $signal) {
    return $this;
  }

  public function ejectPlayer(Player $player) {
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
        $grid->logEvent('info', 'TEAM_EXIT', array('!player' => $player->getName()));
        $this->save(__CLASS__);
      }
    }
    else {
      throw new DisallowedActionException("Cannot remove player after game begin.", 1);
    }
    return $this;
  }

  public function addNewPlayer(Player $player, Faction $target) {
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
      '!player' => $player->getName(),
    ));
    $this->save(__CLASS__);
    return $this;
  }

}
