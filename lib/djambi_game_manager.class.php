<?php
/**
 * @file
 * Déclaration de la class DjambiGameManager permettant de créer
 * et de gérer la persistance de parties de Djambi.
 */

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
define('KW_DJAMBI_USER_PLAYING', 'playing');
// Fin du jeu, vainqueur :
define('KW_DJAMBI_USER_WINNER', 'winner');
// Fin du jeu, nul :
define('KW_DJAMBI_USER_DRAW', 'draw');
// Fin du jeu, perdant :
define('KW_DJAMBI_USER_KILLED', 'killed');
// Fin du jeu, abandon :
define('KW_DJAMBI_USER_WITHDRAW', 'withdraw');
// Camp vassalisé :
define('KW_DJAMBI_USER_VASSALIZED', 'vassalized');
// Camp fantôche :
define('KW_DJAMBI_USER_FANTOCHE', 'fantoche');
// Fin du jeu, encerclement :
define('KW_DJAMBI_USER_SURROUNDED', 'surrounded');
// Fin du jeu, disqualification :
define('KW_DJAMBI_USER_DEFECT', 'defect');
// Création de partie, place libre :
define('KW_DJAMBI_USER_EMPTY_SLOT', 'empty');
// Création de partie, prêt à jouer :
define('KW_DJAMBI_USER_READY', 'ready');

/**
 * Class DjambiException
 */
class DjambiException extends Exception {}

/**
 * Interface GameManagerInterface
 */
interface DjambiGameManagerInterface {
  /**
   * Met à jour les données d'un utilisateur.
   *
   * @param array $user_data
   *   Données à mettre à jour
   * @param string $user_id
   *   Identifiant de l'utilisateur
   * @param bool $is_new_user
   *   TRUE s'il s'agit d'un nouveau joueur
   */
  public function updateUserInfos($user_data, $user_id, $is_new_user = FALSE);

  /**
   * Lance les actions permettant de rendre une partie jouable.
   */
  public function play();

  /**
   * Sauvegarde une partie.
   */
  public function save();

  /**
   * Recharge une partie.
   */
  public function reload();

  /**
   * Charge une partie.
   */
  public static function load($data, DjambiGameOptionsStore $store = NULL);

  /**
   * Créer une partie.
   */
  public static function create($data, DjambiGameOptionsStore $store = NULL);
}

/**
 * Class DjambiGameManager
 * Gère la persistance des parties de Djambi.
 */
class DjambiGameManager implements DjambiGameManagerInterface {
  /** @var DjambiBattlefield $battlefield */
  protected $battlefield;
  /** @var DjambiGameOptionsStore $store */
  protected $optionsStore;
  /** @var bool $persistant */
  protected $persistant = FALSE;

  /**
   * Empêche la création directe d'un GameManager.
   */
  protected function __construct(DjambiGameOptionsStore $store = NULL) {
    if (is_null($store)) {
      $store = new DjambiGameOptionsStoreStandardRuleset();
    }
    $this->optionsStore = $store;
    return $this;
  }

  /**
   * Implements create().
   */
  public static function create($data, DjambiGameOptionsStore $store = NULL) {
    $data['id'] = uniqid('Dj');
    $data['is_new'] = TRUE;
    $gm = new self($store);
    $battlefield = new DjambiBattlefield($gm, $data);
    $gm->setBattlefield($battlefield);
    return $gm;
  }

  /**
   * Implements load().
   */
  public static function load($data, DjambiGameOptionsStore $store = NULL) {
    $gm = new self($store);
    $battlefield = new DjambiBattlefield($gm, $data);
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
   * @param bool $with_description
   *   TRUE pour renvoyer la description des états.
   * @param bool $with_recruiting
   *   TRUE pour inclure également les états avant le début du jeu.
   * @param bool $with_pending
   *   TRUE pour inclure également les états parties en cours
   * @param bool $with_finished
   *   TRUE pour inclure également les états parties terminées
   *
   * @return array:
   *   Tableau contenant les différents statuts disponibles.
   */
  public static function getStatuses($with_description = FALSE, $with_recruiting = TRUE, $with_pending = TRUE, $with_finished = TRUE) {
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
   * @param DjambiBattlefield $grid
   *   Grille de Djambi
   */
  protected function setBattlefield(DjambiBattlefield $grid) {
    $this->battlefield = $grid;
  }

  /**
   * Renvoie le magasin d'options associé au jeu.
   */
  public function getOptionsStore() {
    return $this->optionsStore;
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
  protected function setPersistant($bool) {
    $this->persistant = $bool;
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
    $this->getBattlefield()->setInfo('changed', time());
    $data = $this->getBattlefield()->toArray();
    /* @var $faction DjambiPoliticalFaction */
    foreach ($this->getBattlefield()->getFactions() as $faction) {
      $user_data = array(
        'uid' => $faction->getUserDataItem('uid'),
        'status' => $faction->getStatus(),
        'ranking' => $faction->getRanking(),
        'human' => $faction->isHumanControlled(),
        'ia' => $faction->getUserDataItem('ia'),
        'cookie' => NULL,
      );
      $data['users'][$faction->getId()] = $user_data;
    }
    return $data;
  }

  /**
   * Recharge la partie en cours.
   *
   * @return DjambiGameManager
   *   Renvoie l'objet de persistance de la partie rechargé.
   */
  public function reload() {
    return $this;
  }

  /**
   * Implémente la fonction updateUserInfos.
   *
   * Dans ce cas, ne sert à rien, car pas de persistance des données.
   */
  public function updateUserInfos($user_data, $user_id, $is_new_user = FALSE) {
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
   *
   * @return DjambiGameManager
   *   Renvoie l'objet DjambiGameManager courant.
   */
  public function play() {
    if ($this->isPlayable()) {
      $summary = $this->getBattlefield()->getSummary();
      if (empty($summary)) {
        $this->getBattlefield()->prepareSummary();
      }
      $this->getBattlefield()->getPlayOrder(TRUE);
      $this->getBattlefield()->defineMovablePieces();
    }
    return $this;
  }

  /**
   * Vérifie si une faction est contrôlée par l'utilisateur courant.
   *
   * @param string $user_id
   *   Identifiant de l'utilisateur
   * @param string $user_cookie
   *   Cookie permettant d'identifier l'utilisateur anonyme
   * @param DjambiPoliticalFaction $faction
   *   Faction à vérifier
   * @param bool $control
   *   TRUE pour vérifier également que l'utilisateur possède le contrôle en
   *   cours de jeu de la faction à vérifier.
   *
   * @return bool
   *   TRUE si la faction est contrôlée par l'utilisateur
   */
  public function checkUserPlayingFaction($user_id, $user_cookie, DjambiPoliticalFaction $faction, $control = TRUE) {
    if (empty($faction)) {
      return FALSE;
    }
    if ($control) {
      $user_data = $faction->getControl()->getUserData();
    }
    else {
      $user_data = $faction->getUserData();
    }
    if ($user_id > 0) {
      if ($user_data['uid'] == $user_id && $user_data['human']) {
        return TRUE;
      }
      else {
        return FALSE;
      }
    }
    elseif (!$this->isPersistant()) {
      if ($user_data['human']) {
        return TRUE;
      }
    }
    elseif (!empty($user_cookie) && $user_data['cookie'] == $user_cookie) {
      return TRUE;
    }
    return FALSE;
  }

  /**
   * Détermine si un utilisateur courant contrôle une faction.
   *
   * @param string $user_id
   *   Identiant de l'utilisateur
   * @param string $user_cookie
   *   Cookie permettant d'identifier un utilisateur anonyme
   *
   * @return DjambiPoliticalFaction
   *   Renvoie le camp contrôlé par l'utilisateur si trouvé.
   *   Si rien n'est trouvé, renvoie une valeur nulle.
   */
  public function getUserFaction($user_id, $user_cookie) {
    $grid = $this->getBattlefield();
    $current_user_faction = NULL;
    if (is_array($grid->getFactions())) {
      foreach ($grid->getFactions() as $faction) {
        if ($this->checkUserPlayingFaction($user_id, $user_cookie, $faction, FALSE)) {
          $current_user_faction = $faction;
        }
      }
    }
    if (!$grid->isFinished() && !is_null($current_user_faction) && $grid->getMode() == KW_DJAMBI_MODE_SANDBOX) {
      return $grid->getPlayingFaction();
    }
    return $current_user_faction;
  }

}
