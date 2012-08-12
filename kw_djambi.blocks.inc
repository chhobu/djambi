<?php
function _kw_djambi_list_games() {
  $modes = DjambiBattlefield::getModes();
  $elements['grid1'] = _kw_djambi_list_recruiting_games($modes);
  $elements['grid1']['#weight'] = 1;
  $links = array();
  if (node_access('create', 'kw_djambi')) {
    $links['add-friendly-djambi-game'] = array(
      'title' => t('Create a new friendly game'),
      'href' => 'node/add/kw-djambi',
      'query' => array('mode' => KW_DJAMBI_MODE_FRIENDLY)
    );
  }
  if (!empty($links)) {
    $elements['links1'] = array(
        '#markup' => theme('links', array('links' => $links)),
        '#weight' => 2
    );
  }
  return $elements;
}

function _kw_djambi_list_recruiting_games($modes) {
  $options = array();
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
    ->fields('u2', array('djuid', 'nid'))
    ->condition('u2.status', KW_DJAMBI_USER_READY)
    ->groupBy('u2.nid');
  $subquery2->addExpression('COUNT(u2.djuid)', 'ready_slots');
  $grid->addTable($subquery2, 'du2', 'inner', 'du2.nid = dn.nid');
  $grid->addTable('node', 'n', 'inner', 'n.nid = dn.nid');
  $grid->addKey('nid');
  $grid->addCondition('dn.autodelete', time(), '>');
  $grid->addColumn('title', t('Game'), ':title_link', array('field' => 'n.title'));
  $grid->addVariable('title_link', array(
    'callback' => 'l',
    'args' => array(':title', 'node/:nid')
  ));
  $grid->addColumn('created', t('Created'), ':created_date', array(
    'expression' => 'UNIX_TIMESTAMP() - dn.begin'
  ));
  $grid->addVariable('created_date', array(
      'callback' => 'format_interval',
      'args' => array(':created')
  ));
  $grid->addColumn('mode', t('Mode'), ':mode_label', array('field' => 'dn.mode'));
  $grid->addVariable('mode_label', array(
      'callback' => '_kw_djambi_get_translatable_messages',
      'args' => array(':mode')
  ));
  $grid->addField('ready_slots', 'du2');
  $grid->addExpression('total_slots', 'du1.empty_slots + du2.ready_slots');
  $grid->addColumn('playing', t('Slots'), ':ready_slots/:total_slots');
  $grid->addColumn('players', t('Players'), ':players_list');
  $grid->addVariable("players_list", array(
      'subquery_output' => ":name",
      'subquery' => "SELECT D.nid, D.cookie, U.* "
        . "FROM {djambi_users} D INNER JOIN {users} U ON U.uid = D.uid AND D.status = :status "
        . "WHERE D.nid IN (:input) ORDER BY U.name",
      'subquery_args' => array(':status' => KW_DJAMBI_USER_READY),
      'subquery_callback' => '_kw_djambi_list_build_games_users_list',
      'export' => array('with_me')
  ));
  $grid->addColumn('actions', t('Actions'), ':action_link');
  $grid->addVariable('action_link', array(
      'final_callback' => '_kw_djambi_list_build_actions_link'
  ));
  $grid->addDefaultOrder('nid', 'desc');
  $grid->addPager(10);
  $grid->addAutoRefresh(60);
  $grid->setEmptyText(t('There is currently no pending games.'));
  return $grid->build();
}

function _kw_djambi_list_build_games_users_list($args) {
  global $user;
  $cookie = _kw_djambi_cookie();
  $account = (object) $args;
  $args['name'] = theme('username', array('account' => $account));
  if (($user->uid > 0 && $user->uid == $account->uid) || $cookie == $account->cookie) {
    $args['name'] = '[' . t('me !') . ']';
    $args['with_me'] = TRUE;
  }
  else {
    $args['with_me'] = FALSE;
  }
  return $args;
}

function _kw_djambi_list_build_actions_link($row) {
  if ($row->with_me) {
    $label = t('Return to this game');
  }
  else {
    $label = t('Join this game');
  }
  $output = l($label, 'node/' . $row->nid);
  return $output;
}

function _kw_djambi_list_user_games() {

}