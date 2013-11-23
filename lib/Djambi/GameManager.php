<?php
/**
 * @file
 * Déclaration de la class DjambiGameManager permettant de créer
 * et de gérer la persistance de parties de Djambi.
 */

namespace Djambi;
use Djambi\Factories\GameDispositionsFactory;
use Djambi\Interfaces\BattlefieldInterface;
use Djambi\Interfaces\GameManagerInterface;

/**;
 * Déclaration des constantes concernant les différents modes de jeu :
 */
define('KW_DJAMBI_MODE_SANDBOX', 'bac-a-sable');
define('KW_DJAMBI_MODE_FRIENDLY', 'amical');
define('KW_DJAMBI_MODE_TRAINING', 'training');

/**
 * Déclaration des constantes concernant les phases de jeu :
 */
define('KW_DJAMBI_STATUS_PENDING', 'pending');
define('KW_DJAMBI_STATUS_FINISHED', 'finished');
define('KW_DJAMBI_STATUS_DRAW_PROPOSAL', 'draw_proposal');
define('KW_DJAMBI_STATUS_RECRUITING', 'recruiting');

/**
 * Déclaration des constantes concernant les statuts utilisateurs :
 */
// Partie en cours :
define('KW_DJAMBI_FACTION_STATUS_PLAYING', 'playing');
// Fin du jeu, vainqueur :
define('KW_DJAMBI_FACTION_STATUS_WINNER', 'winner');
// Fin du jeu, nul :
define('KW_DJAMBI_FACTION_STATUS_DRAW', 'draw');
// Fin du jeu, perdant :
define('KW_DJAMBI_FACTION_STATUS_KILLED', 'killed');
// Fin du jeu, abandon :
define('KW_DJAMBI_FACTION_STATUS_WITHDRAW', 'withdraw');
// Camp vassalisé :
define('KW_DJAMBI_FACTION_STATUS_VASSALIZED', 'vassalized');
// Fin du jeu, encerclement :
define('KW_DJAMBI_FACTION_STATUS_SURROUNDED', 'surrounded');
// Fin du jeu, disqualification :
define('KW_DJAMBI_FACTION_STATUS_DEFECT', 'defect');
// Création de partie, place libre :
define('KW_DJAMBI_FACTION_STATUS_EMPTY_SLOT', 'empty');
// Création de partie, prêt à jouer :
define('KW_DJAMBI_FACTION_STATUS_READY', 'ready');

/**
 * Class DjambiGameManager
 * Gère la persistance des parties de Djambi.
 */
class GameManager implements GameManagerInterface {
  /** @var Battlefield $battlefield */
  protected $battlefield;
  /** @var bool $persistant */
  protected $persistant = FALSE;
  /** @var bool $new */
  protected $new = FALSE;
  /** @var int $changed */
  protected $changed;
  /** @var int $created */
  protected $begin;
  /** @var array $infos */
  protected $infos = array();

  /**
   * Empêche la création directe d'un GameManager.
   */
  protected function __construct() {}

  /**
   * Implements create().
   */
  public static function create($players, $id, $mode, GameDisposition $disposition, $battlefield_factory = NULL) {
    /* @var GameManager $gm */
    $gm = new static();
    if (is_null($battlefield_factory)) {
      $grid = Battlefield::createNewBattlefield($gm, $players, $id, $mode, $disposition);
    }
    else {
      $grid = call_user_func_array($battlefield_factory . '::createNewBattlefield',
        array($gm, $players, $id, $mode, $disposition));
      $gm->setInfo('battlefield_factory', $battlefield_factory);
    }
    $gm->setBattlefield($grid);
    $gm->setBegin(time());
    $gm->setChanged(time());
    $gm->setNew(TRUE);
    return $gm;
  }

  /**
   * Implements load().
   */
  public static function loadGame($data) {
    /* @var $gm GameManager */
    $gm = new static();
    $gm->setNew(FALSE);
    if (isset($data['begin'])) {
      $gm->setBegin($data['begin']);
    }
    if (isset($data['changed'])) {
      $gm->setChanged($data['changed']);
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
    else {
      $disposition = GameDispositionsFactory::loadDisposition($data['disposition'], $scheme_settings);
    }
    if (isset($data['infos']['battlefield_factory'])) {
      $battlefield = call_user_func_array($data['infos']['battlefield_factory'] . '::loadBattlefield',
        array($gm, $disposition, $data));
    }
    else {
      $battlefield = Battlefield::loadBattlefield($gm, $disposition, $data);
    }
    if (!empty($data['infos'])) {
      foreach ($data['infos'] as $info => $value) {
        $gm->setInfo($info, $value);
      }
    }
    $gm->setBattlefield($battlefield);
    return $gm;
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
      KW_DJAMBI_MODE_FRIENDLY => 'MODE_FRIENDLY_DESCRIPTION',
      KW_DJAMBI_MODE_SANDBOX => 'MODE_SANDBOX_DESCRIPTION',
    );
    $hidden_modes = array(
      KW_DJAMBI_MODE_TRAINING => 'MODE_TRAINING_DESCRIPTION',
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
      $statuses[KW_DJAMBI_STATUS_RECRUITING] = 'STATUS_RECRUITING_DESCRIPTION';
    }
    if ($with_pending) {
      $statuses[KW_DJAMBI_STATUS_PENDING] = 'STATUS_PENDING_DESCRIPTION';
      $statuses[KW_DJAMBI_STATUS_DRAW_PROPOSAL] = 'STATUS_DRAW_PROPOSAL_DESCRIPTION';
    }
    if ($with_finished) {
      $statuses[KW_DJAMBI_STATUS_FINISHED] = 'STATUS_FINISHED_DESCRIPTION';
    }
    if ($with_description) {
      return $statuses;
    }
    else {
      return array_keys($statuses);
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
   */
  protected function setBattlefield(BattlefieldInterface $grid) {
    $this->battlefield = $grid;
  }

  /**
   * Détermine si la partie en cours est sauvegardable.
   */
  public function isPersistant() {
    return $this->persistant;
  }

  /**
   * Autorise la partie en cours à être sauvegardée.
   */
  protected function setPersistant($is_persistant) {
    $this->persistant = $is_persistant;
    return $this;
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
   *
   * @return array
   *   Tableau associatif, contenant les données permettant de recharger
   *   la partie
   */
  public function save() {
    $this->setChanged(time());
    $data = $this->getBattlefield()->toArray();
    $data['infos'] = $this->infos;
    $data['changed'] = $this->changed;
    $data['begin'] = $this->begin;
    return $data;
  }

  /**
   * Recharge la partie en cours.
   *
   * @return GameManager
   *   Renvoie l'objet de persistance de la partie rechargé.
   */
  public function reload() {
    return $this;
  }

  /**
   * Vérifie si une partie est jouable.
   *
   * @return bool
   *   Renvoie TRUE si le statut du jeu est en cours.
   */
  public function isPlayable() {
    if ($this->getBattlefield()->getStatus() == KW_DJAMBI_STATUS_PENDING) {
      return TRUE;
    }
    return FALSE;
  }

  /**
   * Lance les actions permettant de rendre une partie jouable.
   */
  public function play() {
    if ($this->isPlayable()) {
      $this->getBattlefield()->prepareNewTurn();
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

  public function listenSignal(Signal $signal) {
    return $this;
  }

}
