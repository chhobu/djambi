<?php
/**
 * @file
 * Classe de gestion de jeu via un contenu de type djambi.
 */

namespace Drupal\kw_djambi\Djambi\GameManagers;
use Djambi\Exceptions\GameNotFoundException;
use Djambi\Factories\GameDispositionsFactory;
use Djambi\GameManager;
use Djambi\Players\ComputerPlayer;
use Djambi\Signal;
use Drupal\kw_djambi\Djambi\DjambiContext;
use Drupal\kw_djambi\Djambi\Players\DrupalPlayer;

/**
 * Class DjambiGameManagerNode
 */
class DrupalGameManager extends GameManager {
  protected $autodeleteTime;

  /**
   * Fonction constructeur (statique) de la class DjambiGameManagerNode.
   * Ne pas appeler directement cette fonction.
   */
  protected function __construct() {
    parent::__construct();
    $this->setPersistant(TRUE);
  }

  /**
   * Fixe le temps de conservation d'une partie.
   */
  public function getAutodeleteTime() {
    if (is_null($this->autodeleteTime)) {
      $conservation_longue = FALSE;
      $modes_longue_conservation_finished = array(
        static::MODE_FRIENDLY,
      );
      if (in_array($this->getBattlefield()->getMode(), $modes_longue_conservation_finished)
        && $this->getBattlefield()->isFinished()) {
        $conservation_longue = TRUE;
      }
      if ($conservation_longue) {
        $this->autodeleteTime = time() + 60 * 60 * 24 * 365;
      }
      else {
        $this->autodeleteTime = time() + 60 * 60 * 24;
      }
    }
    return $this->autodeleteTime;
  }

  public function setAutodeleteTime($time) {
    $this->autodeleteTime = $time;
    return $this;
  }

  public static function loadGame($data) {
    if (!isset($data['nid'])) {
      throw new GameNotFoundException('Unable to load any Djambi game : No nid parameter.');
    }
    $context = DjambiContext::getInstance();
    if (!empty($data['reset'])) {
      if (!is_null($context->getCurrentGame()) && $context->getCurrentGame()->getInfo('nid') == $data['nid']) {
        return $context->getCurrentGame();
      }
    }
    $nid = $data['nid'];
    $node = node_load($nid);
    $result = db_query('SELECT * FROM {djambi_node} WHERE nid=:nid', array(
      ':nid' => $nid,
    ))->fetchAssoc();
    if (empty($result)) {
      throw new GameNotFoundException('Game not found');
    }
    if (empty($result['data'])) {
      $data = $result;
    }
    else {
      if ($result['compressed']) {
        $data = gzuncompress($result['data']);
      }
      else {
        $data = $result['data'];
      }
      $data = unserialize($data);
    }
    $players_data = db_query("SELECT faction,uid,cookie,joined,data FROM {djambi_users} WHERE nid=:nid",
      array(':nid' => $nid))->fetchAllAssoc('faction');
    foreach ($players_data as $player) {
      $data['factions'][$player->faction]['data'] = unserialize($player->data);
      if (!empty($player->joined)) {
        $data['factions'][$player->faction]['data']['joined'] = $player->joined;
      }
    }
    static::loadCompatibleData($result, $data, $players_data);
    /* @var DrupalGameManager $gm */
    $gm = parent::loadGame($data);
    $gm->setInfo('nid', $node->nid);
    $gm->setAutodeleteTime($result['autodelete']);
    $gm->setBegin($result['begin']);
    $gm->setChanged($result['changed']);
    $context->setCurrentGame($gm);
    return $gm;
  }

  protected static function loadCompatibleData($result, &$data, $players_data) {
    // Compatibilité anciennes parties :
    if (!isset($data['disposition'])) {
      $data['disposition'] = $result['disposition'];
    }
    if (!isset($data['status'])) {
      $data['id'] = $result['nid'];
      $data = array_merge($data, $result);
      if (isset($data['sequence'])) {
        $data['infos']['sequence'] = $data['sequence'];
        unset($data['sequence']);
      }
    }
    foreach ($data['factions'] as $key => $faction) {
      if (!isset($faction['player'])) {
        if (isset($players_data[$key]) && ($players_data[$key]->uid > 0 || !empty($players_data[$key]->cookie))) {
          $player = array(
            'className' => $players_data[$key]->uid > 0 ? 'Drupal\kw_djambi\Djambi\Players\DrupalIdentifiedPlayer' : 'Drupal\kw_djambi\Djambi\Players\DrupalAnonymousPlayer',
            'registered' => TRUE,
            'id' => $players_data[$key]->uid > 0 ? $players_data[$key]->uid : $players_data[$key]->cookie,
          );
          $data['factions'][$key]['player'] = $player;
        }
        else {
          $data['factions'][$key]['player'] = NULL;
        }
      }
    }
  }

  public static function createFromNodeForm(&$form_state, $node) {
    $context = DjambiContext::getInstance();
    $game_id = variable_get('kw_djambi_game_sequence', 0);
    $game_id++;
    $node->title = t("Machiavelli chess - Game #!i", array("!i" => $game_id));
    $mode = $form_state['values']['mode'];
    $player1 = $context->getCurrentUser(TRUE);
    $player1->redirectToCurrentGame($mode);
    Signal::createSignal($player1, $context->getIp());
    $player1->setJoined(time());
    $players[] = $player1;
    $settings = NULL;
    if (isset($form_state['values']['scheme_settings'])) {
      $settings = $form_state['values']['scheme_settings'];
    }
    if (isset($form_state['values']['factory'])) {
      $disposition = call_user_func_array($form_state['values']['factory'] . '::loadDisposition', $settings);
    }
    else {
      $disposition = GameDispositionsFactory::loadDisposition($form_state['values']['nb_players'], $settings);
    }
    if ($mode == static::MODE_SANDBOX) {
      array_fill(1, $disposition->getNbPlayers(), $player1);
    }
    else {
      array_fill(1, $disposition->getNbPlayers(), NULL);
    }
    $game = static::create($players, $game_id, $mode, $disposition);
    foreach ($form_state['values']['advanced'] as $option => $value) {
      $game->getBattlefield()->setOption($option, $value);
    }
    $game->setInfo('sequence', $game_id);
    variable_set('kw_djambi_game_sequence', $game_id);
    return $game;
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
    if ($grid->getStatus() == static::STATUS_FINISHED) {
      $compress = TRUE;
    }
    $data = $grid->toArray();
    $data['infos'] = $this->infos;
    $data = serialize($data);
    if ($compress) {
      $data = gzcompress($data);
    }
    $moves = 0;
    foreach ($grid->getMoves() as $move) {
      if ($move['type'] == 'move') {
        $moves++;
      }
    }
    $time = time();
    $this->setChanged($time);
    $nrecord = array(
      'nb_moves' => $moves,
      'data' => $data,
      'changed' => $time,
      'status' => $grid->getStatus(),
      'autodelete' => $this->getAutodeleteTime(),
      'compressed' => $compress,
      'nid' => $this->getInfo('nid'),
    );
    if ($this->isNew()) {
      $nrecord['mode'] = $grid->getMode();
      $nrecord['points'] = 0;
      $nrecord['begin'] = $this->getBegin();
      $nrecord['disposition'] = $grid->getDisposition()->getName();
      drupal_write_record('djambi_node', $nrecord);
    }
    else {
      drupal_write_record('djambi_node', $nrecord, array('nid'));
    }
    foreach ($grid->getFactions() as $faction) {
      $precord = array(
        'nid' => $this->getInfo('nid'),
        'status' => $faction->getStatus(),
        'faction' => $faction->getId(),
        'ranking' => $faction->getRanking(),
        'data' => NULL,
        'joined' => NULL,
        'human' => NULL,
        'uid' => NULL,
        'cookie' => NULL,
        'played' => NULL,
        'ia' => NULL,
      );
      $player = $faction->getPlayer();
      if (!is_null($player)) {
        $precord['human'] = $player->isHuman();
        if ($player instanceof DrupalPlayer) {
          $precord['uid'] = $player->getUser()->uid;
          $precord['joined'] = $player->getJoined();
          if ($player->getUser()->uid == 0) {
            $precord['cookie'] = $player->getId();
          }
          if (!is_null($player->getLastSignal())) {
            $precord['data'] = $player->getLastSignal()->toArray();
          }
        }
        elseif ($player instanceof ComputerPlayer) {
          $precord['ia'] = $player->getIa()->getClassName();
        }
      }
      if ($this->isNew()) {
        drupal_write_record('djambi_users', $precord);
      }
      else {
        drupal_write_record('djambi_users', $precord, array('faction', 'nid'));
      }
    }
    return $this;
  }

  public function reload() {
    return self::loadGame(array('nid' => $this->getInfo('nid'), 'reset' => TRUE));
  }

  public function delete() {
    $nid = $this->getInfo('nid');
    if (!empty($nid)) {
      node_delete($nid);
      $current_game = DjambiContext::getInstance()->getCurrentGame();
      if (!empty($current_game) && $current_game->getInfo('nid') == $nid) {
        DjambiContext::getInstance()->resetCurrentGame();
      }
    }
  }

  public function listenSignal(Signal $signal) {
    $record = array(
      'data' => array(
        'ping' => $signal->getPing(),
        'ip' => $signal->getIp(),
      ),
      'faction' => $signal->getPlayer()->getFaction()->getId(),
      'nid' => $this->getInfo(('nid')),
    );
    drupal_write_record('djambi_users', $record, array('faction', 'nid'));
    return $this;
  }

  public static function isReadyForUpdate(array &$data, $version) {
    if (empty($data['nid'])) {
      throw new GameNotFoundException("Missing nid parameter in checkUpdate function.");
    }
    $query = db_select('djambi_node', 'dj')
      ->fields('dj', array('nid', 'changed', 'status'))
      ->condition('dj.nid', $data['nid']);
    $data = $query->execute()->fetchAssoc();
    return $data['changed'] > $version ? TRUE : FALSE;
  }

}
