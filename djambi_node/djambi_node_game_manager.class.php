<?php
class DjambiGameManagerNode extends DjambiGameManager {
  private static $battlefields = array();

  protected function __construct($battlefield) {
    parent::__construct($battlefield);
    $this->setPersistant(TRUE);
    return $this;
  }

  /**
   * @return NULL|DjambiGameManagerNode
   */
  public static function retrieveCurrentGame() {
    if (empty(self::$battlefields)) {
      throw new Exception('Game not found');
    }
    $battlefield = current(self::$battlefields);
    return new self($battlefield);
  }

  /**
   * @return DjambiGameManagerNode
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
      throw new Exception('Game not found');
    }
    $query = db_select('djambi_users', 'dju')
    ->fields('dju')
    ->condition("dju.nid", $nid);
    $users_result = $query->execute()->fetchAll();
    if (empty($users_result)) {
      throw new Exception('Players not found');
    }
    $users = array();
    foreach($users_result as $key => $user) {
      $users[$user->faction] = array(
          'djuid' => $user->djuid,
          'uid' => $user->uid,
          'cookie' => $user->cookie,
          'status' => $user->status,
          'ranking' => $user->ranking,
          'human' => $user->human,
          'ia' => $user->ia
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
    $data["users"] = $users;
    if (!isset($data['status'])) { // Compatibilité anciennes parties
      $data['id'] = $result['nid'];
      $data = array_merge($data, $result);
      if (isset($data['sequence'])) {
        $data['infos']['sequence'] = $data['sequence'];
        unset($data['sequence']);
      }
    }
    $battlefield = new DjambiBattlefield($data);
    $battlefield->setInfo('nid', $nid);
    $battlefield->setInfo('changed', $result['changed']);
    self::$battlefields[$nid] = $battlefield;
    return new self($battlefield);
  }

  public static function createGameNode($game_id, $mode, $disposition, $players_data, $options) {
    $battlefield = new DjambiBattlefield(array_merge(array(
        'id' => 'DjSeq' . $game_id,
        'mode' => $mode,
        'disposition' => $disposition,
        'is_new' => TRUE
    ), $players_data));
    $battlefield->setInfo('sequence', $game_id);
    foreach ($options as $option => $value) {
      $battlefield->setOption($option, $value);
    }
    return new self($battlefield);
  }

  public function saveGame() {
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
        'compressed' => $compress ? 1 : 0
    ));
    $query1->condition('nid', $grid->getInfo('nid'));
    $query1->execute();
    foreach ($grid->getFactions() as $key => $faction) {
      $record = array(
          'status' => $faction->getStatus(),
          'ranking' => $faction->getRanking(),
          'djuid' => $faction->getUserData('djuid')
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

  public function reload() {
    $nid = $this->getBattlefield()->getInfo('nid');
    return self::loadGameFromNid($nid, TRUE);
  }

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
        'djuid' => $user_id
    );
    if ($is_new_user) {
      global $user;
      $record['uid'] = $user->uid;
      $record['cookie'] = _kw_djambi_cookie();
    }
    drupal_write_record('djambi_users', $record, array('djuid'));
    return $user_data;
  }

  public function updateUserInfos($user_data, $user_id, $is_new_user = FALSE) {
    return self::updateStaticUserInfos($user_data, $user_id, $is_new_user);
  }

}