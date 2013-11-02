<?php
/**
 * @file
 * Classe de gestion de jeu via un contenu de type djambi.
 */

/**
 * Class DjambiGameManagerNode
 */
class DjambiGameManagerNode extends DjambiGameManager {
  protected static $battlefields = array();

  /**
   * Fonction constructeur (statique) de la class DjambiGameManagerNode.
   * Ne pas appeler directement cette fonction.
   *
   * @param DjambiGameOptionsStore $store
   *   Options de jeu à appliquer
   */
  protected function __construct(DjambiGameOptionsStore $store = NULL) {
    parent::__construct($store);
    $this->setPersistant(TRUE);
    return $this;
  }

  /**
   * Récupère la partie de Djambi du contexte courant.
   *
   * @throws Exception
   * @return DjambiGameManagerNode
   *   Renvoie la partie de Djambi courante.
   */
  public static function retrieveCurrentGame() {
    if (empty(self::$battlefields)) {
      throw new Exception('Game not found');
    }
    $gm = new self();
    $battlefield = current(self::$battlefields);
    $gm->setBattlefield($battlefield);
    return $gm;
  }

  /**
   * Charge une partie de Djambi à partir d'un identifiant de noeud.
   *
   * @param int $nid
   *   Identifiant du noeud
   * @param bool $reset
   *   TRUE pour forcer un nouveau chargement de l'objet
   *
   * @throws DjambiException
   * @return DjambiGameManagerNode
   *   Renvoie la partie de Djambi trouvée.
   */
  public static function loadGameFromNid($nid, $reset = FALSE) {
    if (!$reset && isset(self::$battlefields[$nid])) {
      return self::$battlefields[$nid];
    }
    $query = db_select('djambi_node', 'dj')
    ->fields('dj', array('nid', 'data', 'status', 'mode', 'compressed',
        'autodelete', 'changed', 'disposition'))
        ->condition('dj.nid', $nid);
    $result = $query->execute()->fetchAssoc();
    if (empty($result)) {
      throw new DjambiException('Game not found');
    }
    $query = db_select('djambi_users', 'dju')
    ->fields('dju')
    ->condition("dju.nid", $nid);
    $users_result = $query->execute()->fetchAll();
    if (empty($users_result)) {
      throw new DjambiException('Players not found');
    }
    $users = array();
    foreach ($users_result as $user) {
      $users[$user->faction] = array(
        'djuid' => $user->djuid,
        'uid' => $user->uid,
        'cookie' => $user->cookie,
        'status' => $user->status,
        'ranking' => $user->ranking,
        'human' => $user->human,
        'ia' => $user->ia,
      );
      if (!empty($user->data)) {
        $user_data = unserialize($user->data);
        if (is_array($user_data)) {
          foreach ($user_data as $label => $data) {
            if (!isset($users[$user->faction][$label])) {
              $users[$user->faction][$label] = $data;
            }
          }
        }
      }
    }
    $data = $result['data'];
    if ($result['compressed']) {
      $data = gzuncompress($data);
    }
    $data = unserialize($data);
    $data['disposition'] = $result['disposition'];
    $data['users'] = $users;
    // Compatibilité anciennes parties :
    if (!isset($data['status'])) {
      $data['id'] = $result['nid'];
      $data = array_merge($data, $result);
      if (isset($data['sequence'])) {
        $data['infos']['sequence'] = $data['sequence'];
        unset($data['sequence']);
      }
    }
    $gm = new self();
    $battlefield = new DjambiBattlefield($gm, $data);
    $battlefield->setInfo('nid', $nid);
    $battlefield->setInfo('changed', $result['changed']);
    $gm->setBattlefield($battlefield);
    self::$battlefields[$nid] = $battlefield;
    return $gm;
  }

  /**
   * Instancie une partie de Djambi liée à un contenu de type djambi_nodes.
   *
   * @param string $game_id
   *   Identifiant d'une partie
   * @param string $mode
   *   Mode de jeu : KW_DJAMBI_MODE_FRIENDLY ou KW_DJAMBI_MODE_SANDBOX
   * @param string $disposition
   *   Code de la classe de disposition de l'échiquier à utiliser
   * @param array $players_data
   *   Données concernant les joueurs
   *
   * @param array $options
   *   Options de jeu : éléments de règles du jeu
   *
   * @return DjambiGameManagerNode
   *   Nouvelle partie de Djambi
   */
  public static function createGameNode($game_id, $mode, $disposition, $players_data, $options) {
    $gm = new self();
    $data = array_merge(array(
      'id' => 'DjSeq' . $game_id,
      'mode' => $mode,
      'disposition' => $disposition,
      'is_new' => TRUE,
    ), $players_data);
    $battlefield = new DjambiBattlefield($gm, $data);
    $battlefield->setInfo('sequence', $game_id);
    foreach ($options as $option => $value) {
      $battlefield->setOption($option, $value);
    }
    $gm->setBattlefield($battlefield);
    return $gm;
  }

  /**
   * Sauve la partie de Djambi courante dans un type de contenu djambi_node.
   *
   * @return int
   *   Renvoie le timestamp de mise à jour en BdD de la partie.
   */
  public function save() {
    $grid = $this->getBattlefield();
    $compress = FALSE;
    if ($grid->getStatus() == KW_DJAMBI_STATUS_FINISHED) {
      $compress = TRUE;
    }
    $data = serialize($grid->toArray());
    if ($compress) {
      $data = gzcompress($data);
    }
    $query1 = db_update('djambi_node');
    $moves = 0;
    foreach ($grid->getMoves() as $move) {
      if ($move['type'] == 'move') {
        $moves++;
      }
    }
    $autodelete_option = 'std';
    if ($grid->isFinished() && in_array($grid->getMode(), array(KW_DJAMBI_MODE_FRIENDLY))) {
      $autodelete_option = 'extended';
    }
    $changed = time();
    $query1->fields(array(
        'nb_moves' => $moves,
        'data' => $data,
        'changed' => $changed,
        'status' => $grid->getStatus(),
        'autodelete' => $grid->getInfo('autodelete') === 0 ? 0 : _djambi_node_autodelete_time($autodelete_option),
        'compressed' => $compress ? 1 : 0,
    ));
    $query1->condition('nid', $grid->getInfo('nid'));
    $query1->execute();
    /* @var $faction DjambiPoliticalFaction */
    foreach ($grid->getFactions() as $faction) {
      $record = array(
        'status' => $faction->getStatus(),
        'ranking' => $faction->getRanking(),
        'djuid' => $faction->getUserData('djuid'),
      );
      if ($faction->getStatus() == KW_DJAMBI_USER_EMPTY_SLOT) {
        $record['uid'] = 0;
        $record['data'] = array();
        $record['cookie'] = NULL;
      }
      drupal_write_record('djambi_users', $record, array('djuid'));
    }
    return $changed;
  }

  /**
   * Recharge la partie de Djambi courante.
   *
   * @return DjambiGameManagerNode
   *   Renvoie la partie de Djambi courante.
   */
  public function reload() {
    $nid = $this->getBattlefield()->getInfo('nid');
    return self::loadGameFromNid($nid, TRUE);
  }

  /**
   * Enregistre les données d'un utilisateur.
   *
   * @param array $user_data
   *   Informations sur l'utilisateur
   * @param int $user_id
   *   Identifiant de l'utilisateur
   * @param bool $is_new_user
   *   TRUE s'il s'agit d'un nouvel utilisateur
   *
   * @return array
   *   Variable $user_date mise à jour
   */
  public static function updateStaticUserInfos($user_data, $user_id, $is_new_user = FALSE) {
    $user_data['ip'] = ip_address();
    $user_data['ping'] = time();
    $unstored_keys = array('djuid', 'cookie', 'status', 'ranking', 'uid');
    foreach ($user_data as $key => $value) {
      if (in_array($key, $unstored_keys)) {
        unset($user_data[$key]);
      }
    }
    $record = array(
      'data' => $user_data,
      'djuid' => $user_id,
    );
    if ($is_new_user) {
      global $user;
      $record['uid'] = $user->uid;
      $record['cookie'] = _kw_djambi_cookie();
    }
    drupal_write_record('djambi_users', $record, array('djuid'));
    return $user_data;
  }

  /**
   * Surcharge de la méthode updateUserInfos().
   *
   * @param array $user_data
   *   Informations sur l'utilisateur
   * @param int $user_id
   *   Identifiant de l'utilisateur
   * @param bool $is_new_user
   *   TRUE s'il s'agit d'un nouvel utilisateur
   *
   * @return array
   *   Variable $user_date mise à jour
   */
  public function updateUserInfos($user_data, $user_id, $is_new_user = FALSE) {
    return self::updateStaticUserInfos($user_data, $user_id, $is_new_user);
  }

}
