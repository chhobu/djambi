<?php
/**
 * @file
 * Déclaration et constitution des blocs du module djambi_node.
 */

use Djambi\Faction;
use Djambi\GameManager;
use Djambi\Grid;
use Djambi\Signal;
use Drupal\kw_djambi\Djambi\DjambiContext;
use \Drupal\kw_djambi\Djambi\GameManagers\DrupalGameManager;
use \Djambi\Factories\GameDispositionsFactory;

/**
 * Construit un bloc listant les parties de Djambi (nouvelles et anciennes).
 *
 * @return array
 *   Eléments à afficher
 */
function _djambi_node_list_games() {
  global $_djambi_node_can_create_friendly_games;
  $_djambi_node_can_create_friendly_games = TRUE;
  drupal_add_css(drupal_get_path('module', 'kw_djambi') . '/css/kw_djambi.css', array('every_page' => TRUE));
  $elements['grid1'] = _djambi_node_list_recruiting_games();
  $elements['grid1']['#weight'] = 1;
  $modes = DrupalGameManager::getModes();
  $links = array();
  if (node_access('create', 'djambi')) {
    if ($_djambi_node_can_create_friendly_games) {
      $links['friendly-link'] = array(
        'title' => t('Create a new friendly game'),
        'href' => 'node/add/djambi',
        'query' => array('mode' => DrupalGameManager::MODE_FRIENDLY),
        'attributes' => array('class' => array('icon militant')),
      );
    }
  }
  if (!empty($links)) {
    $elements['links1'] = array(
      '#markup' => theme('links', array('links' => $links)),
      '#weight' => 2,
    );
  }
  $elements['grid2'] = _djambi_node_list_viewable_games($modes);
  $elements['grid2']['#weight'] = 5;
  return $elements;
}

/**
 * Construit un tableau listant les parties de Djambi en phase de recrutement.
 */
function _djambi_node_list_recruiting_games($item_id = NULL) {
  $options = array(
    'form_display' => 'advanced',
    'file' => drupal_get_path('module', 'djambi_node') . '/djambi_node.blocks.inc',
    'mode' => is_null($item_id) ? 'list' : 'item',
    'mode_context' => array(
      'item_id' => $item_id,
      'entity_type' => 'node',
    ),
    'home' => '<front>',
  );
  $grid = new KwDatagrid('DjambiGameRecrutingList', 'djambi_node', 'dn', __FUNCTION__, array(), $options);
  $grid->setTitle(t('New djambi games'));
  $grid->addCondition('dn.status', DrupalGameManager::STATUS_RECRUITING);
  $subquery1 = db_select('djambi_users', 'u1')
    ->fields('u1', array('nid'))
    ->condition('u1.status', Faction::STATUS_EMPTY_SLOT)
    ->groupBy('u1.nid');
  $subquery1->addExpression('COUNT(u1.nid)', 'empty_slots');
  $grid->addTable($subquery1, 'du1', 'inner', 'du1.nid = dn.nid');
  $subquery2 = db_select('djambi_users', 'u2')
    ->fields('u2', array('nid'))
    ->condition('u2.status', Faction::STATUS_READY)
    ->groupBy('u2.nid');
  $subquery2->addExpression('COUNT(u2.nid)', 'ready_slots');
  $grid->addTable($subquery2, 'du2', 'inner', 'du2.nid = dn.nid');
  $grid->addTable('node', 'n', 'inner', 'n.nid = dn.nid');
  $grid->addKey('nid');
  $grid->addCondition('dn.autodelete', time(), '>');
  $grid->addField('status', 'dn');
  $grid->addColumn('title', t('Game'), ':title_link', array('field' => 'n.title'));
  $grid->addVariable('title_link', array(
    'callback' => '_djambi_node_shorten_title_link',
    'args' => array(':title', 'node/:nid'),
  ));
  $grid->addColumn('mode', t('Mode'), ':mode_label', array('field' => 'dn.mode'));
  $grid->addVariable('mode_label', array(
    'callback' => '_kw_djambi_get_translatable_messages',
    'args' => array(':mode'),
  ));
  $grid->addField('ready_slots', 'du2');
  $grid->addExpression('total_slots', 'du1.empty_slots + du2.ready_slots');
  $grid->addColumn('playing', t('Slots'), ':ready_slots/:total_slots', array(
    'header' => '<abbr title="Number of human players" class="icon users"></abbr>',
  ));
  $grid->addColumn('created', t('Created since'), ':created_date', array(
    'expression' => 'UNIX_TIMESTAMP() - dn.begin',
  ));
  $grid->addVariable('created_date', array(
    'callback' => 'format_interval',
    'args' => array(':created'),
  ));
  $grid->addColumn('players', t('Players'), ':players_list');
  $grid->addVariable("players_list", array(
    'subquery_output' => ":name",
    'subquery' => "SELECT D.nid, D.cookie, U.* "
      . "FROM {djambi_users} D INNER JOIN {users} U ON U.uid = D.uid AND D.status = :status "
      . "WHERE D.nid IN (:input) ORDER BY U.name",
    'subquery_args' => array(':status' => Faction::STATUS_READY),
    'subquery_callback' => '_djambi_node_list_build_games_users_list',
    'export' => array('with_me'),
  ));
  $grid->addColumn('actions', t('Actions'), ':action_link');
  $grid->addVariable('action_link', array(
    'final_callback' => '_djambi_node_list_build_actions_link',
  ));
  $grid->addDefaultOrder('nid', 'desc');
  $grid->addPager(10);
  $grid->addAutoRefresh(90);
  $grid->setEmptyText(t('There is currently no new games.'));
  return $grid->build();
}

/**
 * Construit un tableau listant les parties de Djambi en cours ou terminées.
 */
function _djambi_node_list_viewable_games($modes = NULL, $item_id = NULL) {
  if (is_null($modes)) {
    $modes = DrupalGameManager::getModes();
  }
  $mode_choices = array();
  foreach ($modes as $value) {
    if ($value != DrupalGameManager::MODE_SANDBOX) {
      $mode_choices[$value] = _kw_djambi_get_translatable_messages($value);
    }
  }
  $statuses = DrupalGameManager::getStatuses(array(
    'with_description' => TRUE,
    'with_recruting' => FALSE,
    'with_pending' => TRUE,
    'with_finished' => TRUE,
  ));
  foreach ($statuses as $status => $label) {
    $statuses[$status] = _kw_djambi_get_translatable_messages($label);
  }
  $options = array(
    'form_display' => 'advanced',
    'file' => drupal_get_path('module', 'djambi_node') . '/djambi_node.blocks.inc',
    'mode' => is_null($item_id) ? 'list' : 'item',
    'mode_context' => array(
      'item_id' => $item_id,
      'entity_type' => 'node',
    ),
    'home' => '<front>',
  );
  $grid = new KwDatagrid('DjambiGameViewableList', 'djambi_node', 'dn', __FUNCTION__, array(), $options);
  $grid->setTitle(t('Pending and finished djambi games'));
  $grid->addCondition('dn.status', DrupalGameManager::STATUS_RECRUITING, '!=');
  $grid->addCondition('dn.mode', DrupalGameManager::MODE_SANDBOX, '!=');
  $subquery2 = db_select('djambi_users', 'u2')
    ->fields('u2', array('nid'))
    ->condition('u2.status', Faction::STATUS_VASSALIZED, '!=')
    ->groupBy('u2.nid');
  $subquery2->addExpression('COUNT(u2.nid)', 'slots');
  $grid->addTable($subquery2, 'pl', 'inner', 'pl.nid = dn.nid');
  $grid->addTable('node', 'n', 'inner', 'n.nid = dn.nid');
  $grid->addKey('nid');
  $grid->addField('status', 'dn');
  $grid->addCondition('dn.autodelete', time(), '>');
  $grid->addColumn('title', t('Game'), ':title_link', array(
    'field' => 'n.title',
    'sortable' => TRUE,
    'itemable' => TRUE,
    'main' => TRUE,
  ));
  $grid->addVariable('title_link', array(
    'callback' => '_djambi_node_shorten_title_link',
    'args' => array(':title', 'node/:nid'),
  ));
  $grid->addColumn('mode', t('Mode'), ':mode_label', array(
    'field' => 'dn.mode',
    'filtrable' => KW_FILTRABLE_ENUM,
    'filtrable_options' => array('choices' => $mode_choices),
  ));
  $grid->addVariable('mode_label', array(
    'callback' => '_djambi_node_list_build_custom_label',
    'args' => array(':mode', $mode_choices),
  ));
  $grid->addColumn('nb_players', t('Players'), '<div class="digit">:nb_players</div>', array(
    'header' => '<abbr title="' . t("Number of human players") . '" class="icon users"></abbr>',
    'field' => 'pl.slots',
    'filtrable' => KW_FILTRABLE_NUM,
    'filtrable_options' => array('choices' => GameDispositionsFactory::listNbPlayersAvailable()),
  ));
  $grid->addColumn("players", t('Players list'), ':players_list', array(
    'hidden' => TRUE,
    'always_execute' => TRUE,
  ));
  $grid->addVariable('players_list', array(
    'subquery_output' => ":name",
    'subquery' => "SELECT D.nid, D.cookie, U.* "
      . "FROM {djambi_users} D INNER JOIN {users} U ON U.uid = D.uid AND D.status != :status "
      . "WHERE D.nid IN (:input) ORDER BY U.name",
    'subquery_args' => array(':status' => Faction::STATUS_VASSALIZED),
    'subquery_callback' => '_djambi_node_list_build_games_users_list',
    'export' => array('with_me'),
    'output_mode' => 'ul',
  ));
  $subquery3 = db_select('users', 'u');
  $subquery3->innerJoin('djambi_users', 'd', 'd.uid = u.uid');
  $subquery3->condition('u.uid', 0, '>');
  $subquery3->addField('u', 'name');
  $subquery3->addField('d', 'nid');
  $subquery3->groupBy('u.name');
  $subquery3->groupBy('d.nid');
  $grid->addFilter('playing', t('Player name'), KW_FILTRABLE_STRING, array(
    "jointure" => array(
      "table" => $subquery3,
      "alias" => "plf",
      "condition" => "plf.nid = dn.nid",
      "filtering" => "name",
    ),
  ));
  $grid->addColumn('status', t('Status'), ':status_label', array(
    'field' => 'dn.status',
    'filtrable' => KW_FILTRABLE_ENUM,
    'filtrable_options' => array('choices' => $statuses),
    'always_execute' => TRUE,
  ));
  $grid->addVariable('status_label', array(
    'callback' => '_djambi_node_list_build_custom_label',
    'args' => array(':status', $statuses),
  ));
  $grid->addColumn('changed_ts', t('Last move'), ':changed_interval', array(
    'expression' => 'UNIX_TIMESTAMP() - dn.changed',
    'sortable' => TRUE,
  ));
  $grid->addVariable('changed_interval', array(
    'callback' => 'format_interval',
    'args' => array(':changed_ts'),
  ));
  $grid->addField('changed', 'dn');
  $grid->addFilter('changed', t('Last update'), KW_FILTRABLE_DATETIME, array(
    'return_format' => 'timestamp',
    'display_callback' => 'format_date',
    'display_args' => array(':filter', 'short'),
    'datepicker' => array(
      'maxDate' => '+1d',
      'yearRange' => '2012:+0',
    ),
  ));
  $grid->addColumn('moves', t("Number of played moves"), '<div class="digit">:moves</div>', array(
    'header' => "<abbr title='" . t("Number of played moves") . "' class='icon bars'></abbr>",
    'field' => 'dn.nb_moves',
    'sortable' => TRUE,
  ));
  $subquery4 = db_select('djambi_users', 'du');
  $subquery4->innerJoin('users', 'u', 'u.uid = du.uid');
  $subquery4->condition('du.status', Faction::STATUS_WINNER);
  $subquery4->addField('du', 'faction');
  $subquery4->addField('du', 'nid');
  $subquery4->fields('u');
  $grid->addColumn('winner', t('Winner'), ':winner_name', array(
    'field' => 'win.name',
    'hidden' => TRUE,
    'filtrable' => KW_FILTRABLE_STRING,
  ));
  $grid->addTable($subquery4, 'win', 'left', 'dn.nid = win.nid', array(
    'linked_column' => 'winner',
  ));
  $grid->addField('winner_uid', 'win', 'uid');
  $grid->addField('winner_status', 'win', 'status');
  $grid->addField('winner_faction', 'win', 'faction');
  $grid->addVariable('winner_name', array(
    'callback' => '_djambi_node_list_build_winnername',
    'args' => array(
      ':winner',
      ':winner_uid',
      ':winner_status',
      ':winner_faction',
    ),
  ));
  $grid->addColumn('actions', t('Actions'), ':action_link');
  $grid->addVariable('action_link', array(
    'final_callback' => '_djambi_node_list_build_actions_link',
  ));
  $grid->addDefaultOrder('changed_ts', 'asc');
  $grid->addPager(10);
  $grid->addAutoRefresh(100);
  $grid->setEmptyText(t('There is currently no pending or finished games to view.'));
  return $grid->build();
}

/**
 * Ajoute des arguments dans la KwDatagrid listant les parties de Djambi.
 *
 * @param array $args
 *   Arguments par défaut utilisés par la KwDatagrid
 *
 * @return array
 *   Tableau d'arguments mis à jour avec les données sur l'utilisateur actuel
 */
function _djambi_node_list_build_games_users_list($args) {
  global $user, $_djambi_node_can_create_friendly_games;
  $cookie = DjambiContext::getInstance()->getDjambiCookie();
  $account = (object) $args;
  $args['name'] = theme('username', array('account' => $account));
  if (($user->uid > 0 && $user->uid == $account->uid) || (!empty($account->cookie) && $cookie == $account->cookie)) {
    $args['name'] = '[' . t('me !') . ']';
    $args['with_me'] = TRUE;
    $_djambi_node_can_create_friendly_games = FALSE;
  }
  else {
    $args['with_me'] = FALSE;
  }
  return $args;
}

/**
 * Affiche le contenu de la colonne actions dans une KwDatagrid.
 *
 * @param stdClass $row
 *   Ligne en cours d'affichage
 *
 * @return string
 *   Code HTML affiché dans la colonne actions
 */
function _djambi_node_list_build_actions_link($row) {
  if ($row->status == GameManager::STATUS_RECRUITING) {
    if (isset($row->with_me) && $row->with_me) {
      $label = t('Return to this game');
      $classes = array('icon enter');
    }
    else {
      $label = t('Join this game');
      $classes = array('icon signup');
    }
  }
  elseif ($row->status != GameManager::STATUS_FINISHED) {
    if (isset($row->with_me) && $row->with_me) {
      $label = t('Play this game');
      $classes = array('icon signup');
    }
    else {
      $label = t('Watch this game');
      $classes = array('icon eye-open');
    }
  }
  else {
    $label = t('View game result');
    $classes = array('icon play');
  }
  $output = l($label, 'node/' . $row->nid, array('attributes' => array('class' => $classes)));
  return $output;
}

/**
 * Affiche une variable lisible aux utilisateurs dans une KwDatagrid.
 *
 * On passe pour cela par un tableau de correspondance contenant les valeurs
 * à afficher pour chaque valeur.
 */
function _djambi_node_list_build_custom_label($value, $labels) {
  return $labels[$value];
}

/**
 * Réduit le lien menant vers une partie dans une KwDatagrid.
 *
 * @param string $title
 *   Intitulé de la partie
 * @param string $link
 *   Lien vers la partie de Djambi
 *
 * @return string
 *   Lien HTML raccourci
 */
function _djambi_node_shorten_title_link($title, $link) {
  if (($position = strpos($title, '-')) > 0) {
    $title = trim(substr($title, $position + 1));
  }
  return l($title, $link);
}

/**
 * Formate le nom du vainqueur d'une partie de Djambi dans une KwDatagrid.
 *
 * @param string $name
 *   Nom du compte du vainqueur
 * @param int $uid
 *   Identifiant du compte du vainqueur
 * @param int $status
 *   Statut du compte du vainqueur
 * @param string $faction
 *   Identifiant de la faction utilisée par le vainqueur
 *
 * @return string
 *   Code HTML affiché dans la colonne vainqueur
 * TODO : passer cette fonction en thème.
 */
function _djambi_node_list_build_winnername($name, $uid, $status, $faction) {
  if (empty($faction)) {
    return '?';
  }
  $account = new stdClass();
  $account->uid = $uid;
  $account->name = $name;
  $account->status = $status;
  $factions_info = Grid::getSidesInfos();
  if (isset($factions_info[$faction])) {
    $class = $factions_info[$faction]['class'];
    $name = _kw_djambi_get_translatable_messages($factions_info[$faction]['name']);
  }
  else {
    $class = 'unknown';
    $name = $faction;
  }
  return '<span class="faction ' . $class . '">' . $name . '</span>&nbsp;:' . theme('username', array('account' => $account));
}

/**
 * Construit le bloc "Mes parties de Djambi".
 *
 * @param int $nid
 *   Identifiant du noeud actuellement affiché
 *
 * @return array
 *   Eléments affichables à l'aide de drupal_render
 */
function _djambi_node_list_user_games($nid = 0) {
  $context = DjambiContext::getInstance();
  $elements = array();
  $playing = FALSE;
  drupal_add_css(drupal_get_path('module', 'kw_djambi') . '/kw_djambi.css', array('every_page' => TRUE));
  if ($context->getCurrentUser()->isRegistered()) {
    // Détection d'une partie en cours :
    if ($nid > 0) {
      try {
        $gm = DrupalGameManager::loadGame(array('nid' => $nid));
      }
      catch (\Djambi\Exceptions\Exception $e) {
        $gm = $context->getCurrentGame();
      }
    }
    else {
      $gm = $context->getCurrentGame();
    }
    if (!empty($gm)) {
      $nid = $gm->getInfo('nid');
      $user_faction = $context->getUserFaction($gm->getBattlefield());
      if (!empty($user_faction) && !$gm->isFinished()) {
        $playing = TRUE;
        $uninteresting_modes = array(
          GameManager::MODE_SANDBOX,
          GameManager::MODE_TRAINING,
        );
        if (!in_array($gm->getMode(), $uninteresting_modes)) {
          $elements['playing'] = array(
            '#markup' => '<div class="active-game" id="DjambiActiveGameInfo" data-grid="' . $gm->getId() . '" '
            . 'data-status="' . $gm->getStatus() . '" data-user-faction="' . $user_faction->getId() . '">'
            . t('You are playing the !faction side in this game.', array(
              '!faction' => '<span class="faction ' . $user_faction->getClass() . '">'
                . _kw_djambi_get_translatable_messages($user_faction->getName()) . '</span>',
            )) . '</div>',
          );
        }
      }
    }
    // Liste des parties en cours :
    $uid = $context->getCurrentUser()->getUser()->uid;
    $cookie = $context->getDjambiCookie();
    $q = db_select('djambi_node', 'n');
    $q->innerJoin('node', 'node', 'n.nid = node.nid');
    if ($uid > 0) {
      $q->innerJoin('djambi_users', 'u', 'u.nid = n.nid AND u.uid = :uid', array(
        ':uid' => $uid,
      ));
      if (!empty($cookie)) {
        $q->leftJoin('djambi_users', 'u', 'u.nid = n.nid AND u.uid = 0 and u.cookie = :cookie', array(
          ':cookie' => $cookie,
        ));
      }
    }
    elseif (!empty($cookie)) {
      $q->innerJoin('djambi_users', 'u', 'u.nid = n.nid AND u.cookie = :cookie', array(
        ':cookie' => $cookie,
      ));
    }
    if (!is_null($nid)) {
      $q->condition('n.nid', $nid, '!=');
    }
    $q->condition('n.status', DrupalGameManager::getStatuses(array('with_finished' => FALSE)));
    $q->condition('n.autodelete', time(), '>');
    $q->leftJoin('djambi_users', 'up', 'up.nid = n.nid AND up.status = :status',
      array(':status' => Faction::STATUS_PLAYING));
    $q->leftJoin('users', 'upu', 'upu.uid = up.uid');
    $q->fields('n', array('nid', 'status', 'mode', 'nb_moves', 'changed'));
    $q->addField('upu', 'name', 'playing_name');
    $q->addField('upu', 'status', 'playing_status');
    $q->addField('upu', 'uid', 'playing_uid');
    $q->addField('up', 'cookie', 'playing_cookie');
    $q->addField('up', 'faction', 'playing_faction');
    $q->addField('u', 'faction', 'my_faction');
    $q->addField('u', 'status', 'my_status');
    $q->addField('u', 'data', 'my_data');
    $q->addField('node', 'title');
    $q->orderBy('n.nid', 'DESC');
    $result = $q->execute()->fetchAll();
    $games = array();
    $factions_info = Grid::getSidesInfos();
    foreach ($result as $game) {
      $item = l($game->title, 'node/' . $game->nid) . ' (' . _kw_djambi_get_translatable_messages($game->mode) . ')';
      if ($game->mode != GameManager::MODE_SANDBOX) {
        $item .= ' - ' . t('Status : !status', array('!status' => _kw_djambi_get_translatable_messages($game->status)));
        if (in_array($game->status, DrupalGameManager::getStatuses(array(
          'with_recruiting' => FALSE,
          'with_finished' => FALSE,
          'with_pending' => TRUE,
        )))
        ) {
          if (isset($factions_info[$game->my_faction])) {
            $my_faction = '<span class="faction ' . $factions_info[$game->my_faction]['class'] . '">'
              . _kw_djambi_get_translatable_messages($factions_info[$game->my_faction]['name']) . '</span>';
          }
          else {
            $my_faction = '<span class="faction">' . $game->my_faction . '</span>';
          }
          if (!is_null($game->playing_faction)) {
            if (isset($factions_info[$game->playing_faction])) {
              $faction = '<span class="faction ' . $factions_info[$game->playing_faction]['class'] . '">'
                . _kw_djambi_get_translatable_messages($factions_info[$game->playing_faction]['name']) . '</span>';
            }
            else {
              $faction = '<span class="faction">' . $game->playing_faction . '</span>';
            }
          }
          if (($uid > 0 && $game->playing_uid == $uid) || (!empty($game->playing_cookie) && $game->playing_cookie == $cookie)) {
            $player = l(t("it's your turn !!!"), 'node/' . $game->nid, array('attributes' => array('class' => array('play-djambi-game'))));
          }
          else {
            $account = new stdClass();
            $account->uid = $game->playing_uid;
            $account->name = $game->playing_name;
            $account->status = $game->playing_status;
            $player = theme('username', array('account' => $account));
          }
          $item .= ' - ' . t('Playing !my_faction side (!status)', array(
              '!my_faction' => $my_faction,
              '!status' => _kw_djambi_get_translatable_messages($game->my_status),
            ));
          $item .= ' - ' . t('Move #!move', array('!move' => $game->nb_moves));
          if (!is_null($game->playing_faction) && !empty($faction)) {
            $item .= ' - ' . t('Playing now : !faction (!player), for !duration', array(
                '!faction' => $faction,
                '!player' => $player,
                '!duration' => format_interval(time() - $game->changed),
              ));
          }
        }
      }
      $games[$game->nid] = array('data' => $item, 'class' => array('leaf'));
    }
    if (!empty($games) && ($uid > 0 || !empty($cookie))) {
      $query = db_update('djambi_users');
      $query->fields(array('data' => serialize(Signal::createSignal($context->getCurrentUser(), $context->getIp())->toArray())));
      $query->condition('nid', array_keys($games));
      $query->condition('status', GameManager::MODE_SANDBOX, '!=');
      if ($uid > 0) {
        $query->condition('uid', $uid);
      }
      else {
        $query->condition('cookie', $cookie);
      }
      $query->execute();
    }
  }

  if (!empty($games)) {
    $elements['games'] = array(
      '#markup' => '<div class="other-games"><p>' . ($playing ? format_plural(count($games), "You are also involved in 1 other pending game :", "You are also involdved in @count other pending games :") :
      format_plural(count($games), "You are currently involved in 1 pending game :", "You are currently involved in @count pending games :")) . '</p>'
      . theme('item_list', array('items' => $games))
      . '</div>',
      '#weight' => 1,
    );
  }
  elseif (empty($elements['playing'])) {
    if (!empty($gm) && $gm->getMode() == GameManager::MODE_SANDBOX && !$gm->isFinished() && !empty($user_faction)) {
      $message = t('You are only involved in this sandbox djambi game.');
    }
    else {
      $message = t('You are not currently playing any djambi game.');
    }
    $elements['nogames'] = array(
      '#markup' => '<p>' . $message . '</p>',
      '#weight' => 1,
    );
  }
  $refresh_link = 'djambi/refresh/my-games-panel/'
  . (!empty($gm) && $gm->getInfo('nid') > 0 ? $gm->getInfo('nid') : 0);
  $parameters = drupal_get_query_parameters($_GET, array());
  // Lien de création de partie (si autorisé)
  if (empty($elements['playing']) && node_access('create', 'djambi')) {
    $allowed_new_games = $context->getCurrentUser()->getNewGameAllowedModes();
    $links = array();

    $current_page = $parameters['q'] == $refresh_link ? $parameters['destination'] : $parameters['q'];
    if (!empty($allowed_new_games) && $current_page != 'node/add/djambi') {
      $links['add-djambi-game'] = array(
        'title' => t('Play a new Djambi game'),
        'href' => 'djambi',
        'rel' => 'nofollow',
        'attributes' => array('class' => array('icon militant')),
      );
    }
  }
  drupal_add_js(drupal_get_path('module', 'kw_djambi') . '/js/kw_djambi.js',
    array('every_page' => TRUE));
  drupal_add_library('system', 'drupal.ajax');
  $links['refresh-my-djambi-panel'] = array(
    'title' => t('Refresh'),
    'href' => $refresh_link,
    'query' => array('destination' => isset($parameters['destination']) ? $parameters['destination'] : $parameters['q']),
    'attributes' => array(
      'rel' => 'nofollow',
      'class' => array('use-ajax', 'ajax-no-ids'),
    ),
  );
  if (!empty($links)) {
    $elements['links1'] = array(
      '#markup' => theme('links', array('links' => $links)),
      '#weight' => 2,
    );
  }
  return $elements;
}
