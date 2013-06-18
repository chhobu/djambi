<?php
function _djambi_node_list_games() {
  global $djambi_can_create_friendly_games;
  $djambi_can_create_friendly_games = TRUE;
  drupal_add_css(drupal_get_path('module', 'kw_djambi').'/kw_djambi.css', array('every_page' => TRUE));
  $elements['grid1'] = _djambi_node_list_recruiting_games();
  $elements['grid1']['#weight'] = 1;
  $modes = DjambiGameManager::getModes();
  $links = array();
  if (node_access('create', 'djambi')) {
    if ($djambi_can_create_friendly_games) {
      $links['friendly-link'] = array(
          'title' => t('Create a new friendly game'),
          'href' => 'node/add/djambi',
          'query' => array('mode' => KW_DJAMBI_MODE_FRIENDLY),
          'attributes' => array('class' => array('icon militant'))
      );
    }
  }
  if (!empty($links)) {
    $elements['links1'] = array(
        '#markup' => theme('links', array('links' => $links)),
        '#weight' => 2
    );
  }
  $elements['grid2'] = _djambi_node_list_viewable_games($modes);
  $elements['grid2']['#weight'] = 5;
  return $elements;
}

function _djambi_node_list_recruiting_games($item_id = NULL) {
  $statuses = DjambiGameManager::getStatuses();
  $options = array(
    'form_display' => 'advanced',
    'file' => drupal_get_path('module', 'djambi_node') . '/djambi_node.blocks.inc',
    'mode' => is_null($item_id) ? 'list' : 'item',
    'mode_context' => array(
        'item_id' => $item_id,
        'entity_type' => 'node',
    ),
    'home' => '<front>'
  );
  $grid = new KwDatagrid('DjambiGameRecrutingList', 'djambi_node', 'dn', __FUNCTION__, array(), $options);
  $grid->setTitle(t('New djambi games'));
  $grid->addCondition('dn.status', KW_DJAMBI_STATUS_RECRUITING);
  $subquery1 = db_select('djambi_users', 'u1')
    ->fields('u1', array('nid'))
    ->condition('u1.status', KW_DJAMBI_USER_EMPTY_SLOT)
    ->groupBy('u1.nid');
  $subquery1->addExpression('COUNT(u1.djuid)', 'empty_slots');
  $grid->addTable($subquery1, 'du1', 'inner', 'du1.nid = dn.nid');
  $subquery2 = db_select('djambi_users', 'u2')
    ->fields('u2', array('nid'))
    ->condition('u2.status', KW_DJAMBI_USER_READY)
    ->groupBy('u2.nid');
  $subquery2->addExpression('COUNT(u2.djuid)', 'ready_slots');
  $grid->addTable($subquery2, 'du2', 'inner', 'du2.nid = dn.nid');
  $grid->addTable('node', 'n', 'inner', 'n.nid = dn.nid');
  $grid->addKey('nid');
  $grid->addCondition('dn.autodelete', time(), '>');
  $grid->addField('status', 'dn');
  $grid->addColumn('title', t('Game'), ':title_link', array('field' => 'n.title'));
  $grid->addVariable('title_link', array(
    'callback' => '_djambi_node_shorten_title_link',
    'args' => array(':title', 'node/:nid')
  ));
  $grid->addColumn('mode', t('Mode'), ':mode_label', array('field' => 'dn.mode'));
  $grid->addVariable('mode_label', array(
      'callback' => '_kw_djambi_get_translatable_messages',
      'args' => array(':mode')
  ));
  $grid->addField('ready_slots', 'du2');
  $grid->addExpression('total_slots', 'du1.empty_slots + du2.ready_slots');
  $grid->addColumn('playing', t('Slots'), ':ready_slots/:total_slots', array(
      'header' => '<abbr title="Number of human players" class="icon users"></abbr>'
  ));
  $grid->addColumn('created', t('Created since'), ':created_date', array(
      'expression' => 'UNIX_TIMESTAMP() - dn.begin'
  ));
  $grid->addVariable('created_date', array(
      'callback' => 'format_interval',
      'args' => array(':created')
  ));
  $grid->addColumn('players', t('Players'), ':players_list');
  $grid->addVariable("players_list", array(
      'subquery_output' => ":name",
      'subquery' => "SELECT D.nid, D.cookie, U.* "
        . "FROM {djambi_users} D INNER JOIN {users} U ON U.uid = D.uid AND D.status = :status "
        . "WHERE D.nid IN (:input) ORDER BY U.name",
      'subquery_args' => array(':status' => KW_DJAMBI_USER_READY),
      'subquery_callback' => '_djambi_node_list_build_games_users_list',
      'export' => array('with_me')
  ));
  $grid->addColumn('actions', t('Actions'), ':action_link');
  $grid->addVariable('action_link', array(
      'final_callback' => '_djambi_node_list_build_actions_link'
  ));
  $grid->addDefaultOrder('nid', 'desc');
  $grid->addPager(10);
  $grid->addAutoRefresh(60);
  $grid->setEmptyText(t('There is currently no new games.'));
  return $grid->build();
}

function _djambi_node_list_viewable_games($modes = NULL, $item_id = NULL) {
  if (is_null($modes)) {
    $modes = DjambiGameManager::getModes();
  }
  $mode_choices = array();
  foreach ($modes as $key => $value) {
    if ($value != KW_DJAMBI_MODE_SANDBOX) {
      $mode_choices[$value] = _kw_djambi_get_translatable_messages($value);
    }
  }
  $statuses = DjambiGameManager::getStatuses(TRUE, FALSE, TRUE, TRUE);
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
    'home' => '<front>'
  );
  $grid = new KwDatagrid('DjambiGameViewableList', 'djambi_node', 'dn', __FUNCTION__, array(), $options);
  $grid->setTitle(t('Pending and finished djambi games'));
  $grid->addCondition('dn.status', KW_DJAMBI_STATUS_RECRUITING, '!=');
  $grid->addCondition('dn.mode', KW_DJAMBI_MODE_SANDBOX, '!=');
  $subquery2 = db_select('djambi_users', 'u2')
  ->fields('u2', array('nid'))
  ->condition('u2.status', KW_DJAMBI_USER_VASSALIZED, '!=')
  ->groupBy('u2.nid');
  $subquery2->addExpression('COUNT(u2.djuid)', 'slots');
  $grid->addTable($subquery2, 'pl', 'inner', 'pl.nid = dn.nid');
  $grid->addTable('node', 'n', 'inner', 'n.nid = dn.nid');
  $grid->addKey('nid');
  $grid->addField('status', 'dn');
  $grid->addCondition('dn.autodelete', time(), '>');
  $grid->addColumn('title', t('Game'), ':title_link', array(
      'field' => 'n.title',
      'sortable' => TRUE,
      'itemable' => TRUE,
      'main' => TRUE
  ));
  $grid->addVariable('title_link', array(
      'callback' => '_djambi_node_shorten_title_link',
      'args' => array(':title', 'node/:nid')
  ));
  $grid->addColumn('mode', t('Mode'), ':mode_label', array(
      'field' => 'dn.mode',
      'filtrable' => KW_FILTRABLE_ENUM,
      'filtrable_options' => array('choices' => $mode_choices)
  ));
  $grid->addVariable('mode_label', array(
      'callback' => '_djambi_node_list_build_custom_label',
      'args' => array(':mode', $mode_choices)
  ));
  $grid->addColumn('nb_players', t('Players'), '<div class="digit">:nb_players</div>', array(
      'header' => '<abbr title="Number of human players" class="icon users"></abbr>',
      'field' => 'pl.slots',
      'filtrable' => KW_FILTRABLE_NUM,
      'filtrable_options' => array('choices' => DjambiGameManager::getDispositions('nb'))
  ));
  $grid->addColumn("players", t('Players list'), ':players_list', array(
      'hidden' => TRUE,
      'always_execute' => TRUE
  ));
  $grid->addVariable('players_list', array(
      'subquery_output' => ":name",
      'subquery' => "SELECT D.nid, D.cookie, U.* "
      . "FROM {djambi_users} D INNER JOIN {users} U ON U.uid = D.uid AND D.status != :status "
      . "WHERE D.nid IN (:input) ORDER BY U.name",
      'subquery_args' => array(':status' => KW_DJAMBI_USER_VASSALIZED),
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
          "filtering" => "name"
      ),
  ));
  $grid->addColumn('status', t('Status'), ':status_label', array(
      'field' => 'dn.status',
      'filtrable' => KW_FILTRABLE_ENUM,
      'filtrable_options' => array('choices' => $statuses),
      'always_execute' => TRUE
  ));
  $grid->addVariable('status_label', array(
      'callback' => '_djambi_node_list_build_custom_label',
      'args' => array(':status', $statuses)
  ));
  $grid->addColumn('changed_ts', t('Last move'), ':changed_interval', array(
      'expression' => 'UNIX_TIMESTAMP() - dn.changed',
      'sortable' => TRUE
  ));
  $grid->addVariable('changed_interval', array(
      'callback' => 'format_interval',
      'args' => array(':changed_ts')
  ));
  $grid->addField('changed', 'dn');
  $grid->addFilter('changed', t('Last update'), KW_FILTRABLE_DATETIME, array(
      'return_format' => 'timestamp',
      'display_callback' => 'format_date',
      'display_args' => array(':filter', 'short'),
      'datepicker' => array(
          'maxDate' => '+1d',
          'yearRange' => '2012:+0'
      )
  ));
  $grid->addColumn('moves', t("Number of played moves"), '<div class="digit">:moves</div>', array(
      'header' => "<abbr title='". t("Number of played moves") . "' class='icon bars'></abbr>",
      'field' => 'dn.nb_moves',
      'sortable' => TRUE
  ));
  $subquery4 = db_select('djambi_users', 'du');
  $subquery4->innerJoin('users', 'u', 'u.uid = du.uid');
  $subquery4->condition('du.status', KW_DJAMBI_USER_WINNER);
  $subquery4->addField('du', 'faction');
  $subquery4->addField('du', 'nid');
  $subquery4->fields('u');
  $grid->addColumn('winner', t('Winner'), ':winner_name', array(
      'field' => 'win.name',
      'hidden' => TRUE,
      'filtrable' => KW_FILTRABLE_STRING
  ));
  $grid->addTable($subquery4, 'win', 'left', 'dn.nid = win.nid', array(
      'linked_column' => 'winner'
  ));
  $grid->addField('winner_uid', 'win', 'uid');
  $grid->addField('winner_status', 'win', 'status');
  $grid->addField('winner_faction', 'win', 'faction');
  $grid->addVariable('winner_name', array(
      'callback' => '_djambi_node_list_build_winnername',
      'args' => array(':winner', ':winner_uid', ':winner_status', ':winner_faction')
  ));
  $grid->addColumn('actions', t('Actions'), ':action_link');
  $grid->addVariable('action_link', array(
      'final_callback' => '_djambi_node_list_build_actions_link'
  ));
  $grid->addDefaultOrder('changed_ts', 'asc');
  $grid->addPager(10);
  $grid->addAutoRefresh(60);
  $grid->setEmptyText(t('There is currently no pending or finished games to view.'));
  return $grid->build();
}

function _djambi_node_list_build_games_users_list($args) {
  global $user, $djambi_can_create_friendly_games;
  $cookie = _kw_djambi_cookie();
  $account = (object) $args;
  $args['name'] = theme('username', array('account' => $account));
  if (($user->uid > 0 && $user->uid == $account->uid) || (!empty($account->cookie) && $cookie == $account->cookie)) {
    $args['name'] = '[' . t('me !') . ']';
    $args['with_me'] = TRUE;
    $djambi_can_create_friendly_games = FALSE;
  }
  else {
    $args['with_me'] = FALSE;
  }
  return $args;
}

function _djambi_node_list_build_actions_link($row) {
  if ($row->status == KW_DJAMBI_STATUS_RECRUITING) {
    if (isset($row->with_me) && $row->with_me) {
      $label = t('Return to this game');
      $classes = array('icon enter');
    }
    else {
      $label = t('Join this game');
      $classes = array('icon signup');
    }
  }
  elseif ($row->status != KW_DJAMBI_STATUS_FINISHED) {
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

function _djambi_node_list_build_custom_label($value, $labels) {
  return $labels[$value];
}

function _djambi_node_shorten_title_link($title, $link) {
  if (($position = strpos($title, '-')) > 0) {
    $title = trim(substr($title, $position + 1));
  }
  return l($title, $link);
}

function _djambi_node_list_build_winnername($name, $uid, $status, $faction) {
  if (empty($faction)) {
    return '?';
  }
  $account = new stdClass();
  $account->uid = $uid;
  $account->name = $name;
  $account->status = $status;
  $factions_info = DjambiPoliticalFaction::buildFactionsInfos();
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

function _djambi_node_list_user_games($nid = 0) {
  global $user;
  $elements = array();
  $cookie = _kw_djambi_cookie();
  $auto_refresh = FALSE;
  drupal_add_css(drupal_get_path('module', 'kw_djambi').'/kw_djambi.css', array('every_page' => TRUE));
  // Détection d'une partie en cours
  if ($nid > 0) {
    try {
      $grid = DjambiGameManagerNode::loadGameFromNid($nid)->getBattlefield();
    }
    catch (Exception $e) {
      $grid = FALSE;
    }
  }
  else {
    /* @var $grid DjambiBattlefield */
    try {
      $grid = DjambiGameManagerNode::retrieveCurrentGame()->getBattlefield();
    }
    catch (Exception $e) {
      $grid = FALSE;
    }
  }
  if ($grid) {
    $nid = $grid->getInfo('nid');
    $user_faction = $grid->getGameManager()->getUserFaction($user->uid, _kw_djambi_cookie());
    if ($user_faction && $grid->getMode() != KW_DJAMBI_MODE_SANDBOX && !$grid->isFinished()) {
      $elements['playing'] = array(
            '#markup' => '<div class="active-game" id="DjambiActiveGameInfo" data-grid="' . $grid->getId() . '" '
                . 'data-status="'.$grid->getStatus().'" data-user-faction="' . $user_faction->getId() . '">' . t('You are playing the !faction side in this game.', array(
                '!faction' => '<span class="faction ' . $user_faction->getClass() . '">'
                . _kw_djambi_get_translatable_messages($user_faction->getName()) . '</span>'
          )) . '</div>'
        );
    }
  }
  // Liste des parties en cours
  $q = db_select('djambi_node', 'n');
  $q->innerJoin('node', 'node', 'n.nid = node.nid');
  if ($user->uid == 0) {
    $q->innerJoin('djambi_users', 'u', 'u.nid = n.nid AND u.cookie = :cookie', array(
        ':cookie' => _kw_djambi_cookie()
    ));
  }
  else {
    $q->innerJoin('djambi_users', 'u', 'u.nid = n.nid AND u.uid = :uid', array(
        ':uid' => $user->uid
    ));
  }
  if (!is_null($nid)) {
    $q->condition('n.nid', $nid, '!=');
  }
  $q->condition('n.status', DjambiGameManager::getStatuses(FALSE, TRUE, TRUE, FALSE));
  $q->condition('n.autodelete', time(), '>');
  $q->leftJoin('djambi_users', 'up', 'up.nid = n.nid AND up.status = :status',
      array(':status' => KW_DJAMBI_USER_PLAYING));
  $q->leftJoin('users', 'upu', 'upu.uid = up.uid');
  $q->fields('n', array('nid', 'status', 'mode', 'nb_moves', 'changed'));
  $q->addField('upu', 'name', 'playing_name');
  $q->addField('upu', 'status', 'playing_status');
  $q->addField('upu', 'uid', 'playing_uid');
  $q->addField('up', 'cookie', 'playing_cookie');
  $q->addField('up', 'faction', 'playing_faction');
  $q->addField('u', 'djuid', 'my_djuid');
  $q->addField('u', 'faction', 'my_faction');
  $q->addField('u', 'status', 'my_status');
  $q->addField('u', 'data', 'my_data');
  $q->addField('node', 'title');
  $q->orderBy('n.nid', 'DESC');
  $result = $q->execute()->fetchAll();
  $games = array();
  $nids = array();
  $factions_info = DjambiPoliticalFaction::buildFactionsInfos();
  foreach($result as $game) {
    if ($game->mode == KW_DJAMBI_MODE_SANDBOX) {
      if (!in_array($game->nid, $nids)) {
        $nids[] = $game->nid;
      }
      else {
        continue;
      }
    }
    $item = l($game->title, 'node/' . $game->nid) . ' (' . _kw_djambi_get_translatable_messages($game->mode) . ')';
    if ($game->mode != KW_DJAMBI_MODE_SANDBOX) {
      $auto_refresh = TRUE;
      DjambiGameManagerNode::updateStaticUserInfos(unserialize($game->my_data), $game->my_djuid);
      $item .= ' - ' . t('Status : !status', array('!status' => _kw_djambi_get_translatable_messages($game->status)));
      if (in_array($game->status, DjambiGameManager::getStatuses(FALSE, FALSE, TRUE, FALSE))) {
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
        if (($user->uid > 0 && $game->playing_uid == $user->uid) || (!empty($game->playing_cookie) && $game->playing_cookie == $cookie)) {
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
            '!status' => _kw_djambi_get_translatable_messages($game->my_status)
        ));
        $item .= ' - ' . t('Move #!move', array('!move' => $game->nb_moves));
        if (!is_null($game->playing_faction)) {
          $item .= ' - ' . t('Playing now : !faction (!player), for !duration', array(
            '!faction' => $faction,
            '!player' => $player,
            '!duration' => format_interval(time() - $game->changed)
          ));
        }
      }
    }
    $games[] = array('data' => $item, 'class' => array('leaf'));
  }
  if (!empty($games)) {
    $elements['games'] = array(
        '#markup' => '<div class="other-games">'
          . '<p>' . (!empty($elements['playing']) ? format_plural(count($games), "You are also involved in 1 other pending game :", "You are also involdved in @count other pending games :") :
              format_plural(count($games), "You are currently involved in 1 pending game :", "You are currently involved in @count pending games :")) . '</p>'
          . theme('item_list', array('items' => $games))
          . '</div>',
        '#weight' => 1
    );
  }
  elseif (empty($elements['playing'])) {
    if ($grid && $grid->getMode() == KW_DJAMBI_MODE_SANDBOX && !$grid->isFinished() && $user_faction) {
      $message = t('You are only involved in this sandbox djambi game.');
    }
    else {
      $message = t('You are not currently playing any djambi game.');
    }
    $elements['nogames'] = array(
        '#markup' => '<p>' . $message . '</p>',
        '#weight' => 1
    );
  }
  // Lien de création de partie (si autorisé)
  if (empty($elements['playing']) && node_access('create', 'djambi')) {
    $allowed_new_games = _djambi_node_check_user_already_playing($user, DjambiGameManager::getModes(), $cookie, FALSE);
    $links = array();
    if (!empty($allowed_new_games)) {
      $links['add-djambi-game'] = array(
        'title' => t('Play a new Djambi game'),
        'href' => 'djambi',
        'rel' => 'nofollow',
        'attributes' => array('class' => array('icon militant'))
      );
    }
  }
  drupal_add_js(drupal_get_path('module', 'kw_djambi') . '/js/kw_djambi.js',
      array('every_page' => TRUE));
  $links['refresh-my-djambi-panel'] = array(
    'title' => t('Refresh'),
    'href' => 'djambi/refresh/my-games-panel/' . ($grid ? $grid->getId() : 0),
    'query' => array('destination'  => isset($_GET['destination']) ? $_GET['destination'] : $_GET['q']),
    'attributes' => array('rel' => 'nofollow', 'class' => array('use-ajax', $auto_refresh ? 'do-auto-refresh' : 'no-auto-refresh'))
  );
  if (!empty($links)) {
    $elements['links1'] = array(
        '#markup' => theme('links', array('links' => $links)),
        '#weight' => 2
    );
  }
  return $elements;
}