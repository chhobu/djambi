<?php
/**
 * Affichage du nom d'une pièce
 */
function theme_djambi_piece_name($variables) {
  $piece = $variables['piece'];
  $attributes['class'] = array($piece->getType());
  if (!empty($variables['use_control_class'])) {
    $attributes['class'][] = $piece->getFaction()->getControl->getClass();
  }
  elseif (!empty($variables['use_faction_class'])) {
    $attributes['class'][] = $piece->getFaction()->getClass();
  }
  return "<span " . drupal_attributes($attributes) . ">" . _kw_djambi_get_full_piece_name($piece) . "</span>";
}

/**
 * Affichage du nom d'une faction
 */
function theme_djambi_faction_name($variables) {
  $faction = $variables['faction'];
  $attributes['class'] = array('faction', $faction->getClass());
  if (!empty($variables['use_control_class'])) {
    $attributes['class'][] = $faction->getControl()->getClass();
  }
  return "<span " . drupal_attributes($attributes) . ">" . _kw_djambi_get_translatable_messages($faction->getName()) . "</span>";
}


/**
 * Affichage de la grille à partir des données du formulaire
 */
function theme_djambi_grid($variables) {
  global $user;
  $form = $variables["form"];
  /* @var $grid DjambiBattlefield */
  $grid = $form["#grid"];
  $cells = $grid->getCells();
  $turns = $grid->getTurns();
  $moves = $grid->getMoves();
  $image_folder = base_path() . drupal_get_path('module', 'kw_djambi') . '/img/';
  $image_extension = 'png';
  $current_form_action = $variables['current_form_action'];

  // Construction de la grille
  $past_moves = $variables['past_moves'];
  $last_moves = $variables['described_moves'];
  $show_changes = $variables['show_changes'];
  $show_moves = $variables['show_moves'];
  $rows = $variables['rows'];
  $animated_moves = ($show_changes && !empty($past_moves) && isset($past_moves['animations'])) ? $past_moves['animations'] : array();
  foreach ($grid->getCells() as $key => $cell) {
    $row_inner_html = "";
    $changes = array();
    $extra_classes = array('cell', $cell['type'],
        isset($form['pieces']["movable"]) && isset($cell["reachable"]) ? "reachable" : "unreachable");
    // Affichage des mouvements passés
    if ($show_changes && !empty($past_moves) && isset($past_moves['changing_cells'][$key])) {
      $extra_classes[] = 'past-move';
      $extra_classes[] = $past_moves['changing_cells'][$key];
      foreach ($past_moves['moves'] as $order => $move) {
        if ($move['location'] == $key) {
          $acting_faction = $grid->getFactionById($move['faction']);
          $change_html = "<div class='change " . $acting_faction->getClass() . "' data-order='" . $order . "'>";
          if (!$move['hidden']) {
            $change_html .= "<span class='order' title=\"" . t("A move involving !faction side happened here during phase !move of the game.", array(
             '!faction' => _kw_djambi_get_translatable_messages($acting_faction->getName()),
             '!move' => $move['order']
            )) . "\">" . $move['order'] . "</span>";
            $change_html .= "<div class='description'>" . $move['description'] . "</div>";
          }
          $change_html .= "</div>";
          $changes[] = $change_html;
        }
      }
    }
    // Affichage des descriptions des mouvements précédents
    elseif ($show_moves && !empty($last_moves) && isset($last_moves['changing_cells'][$key])) {
      $extra_classes[] = 'recent-move';
      foreach ($last_moves['moves'] as $order => $move) {
        if ($move['location'] == $key) {
          $acting_faction = $grid->getFactionById($move['faction']);
          if (!$acting_faction) {
            continue;
          }
          $change_html = "<div class='change " . $acting_faction->getClass() . "' data-order='" . $order . "'><span class='order' title=\""
            . t("A move involving !faction side recently happened here (it was move #!move).", array(
                '!faction' => _kw_djambi_get_translatable_messages($acting_faction->getName()),
                '!move' => $move['order']
            )) . "\">" . $move['order'] . "</span><div class='description'>" . $move['description'] . "</div></div>";
          $changes[] = $change_html;
        }
      }
    }
    if (!empty($changes)) {
      $row_inner_html = "<div class='changes'>" . implode(array_slice($changes, -2, 2)) . "</div>";
    }
    // Affichage d'une couche de sélection de destination
    if (!empty($variables['current_form_action']) && $variables['current_form_action'] != 'piece_selection') {
      if (isset($form['pieces'][$current_form_action]["cell-" . $key])) {
        $destination = drupal_render($form['pieces'][$current_form_action]["cell-" . $key]);
        $row_inner_html .= "<div class='destination-layer'>" . $destination . "</div>";
        $extra_classes[] = "selectable";
      }
      else {
        $extra_classes[] = "unselectable";
      }
    }
    // Affichage d'une pièece occupant la cellule
    if (!is_null($cell["occupant"])) {
      $piece = $cell["occupant"];
      $faction = $piece->getFaction();
      $extra_classes[] = "with-piece";
      if (isset($form['pieces']["movable"]) && $piece->isMovable()) {
        $extra_classes[] = "with-movable-piece";
        $moves = $piece->getAllowableMoves();
      }
      $is_selected = isset($form['pieces'][$current_form_action]) && isset($form['pieces'][$current_form_action]["#selected_piece_id"])
        && $piece->getId() == $form['pieces'][$current_form_action]["#selected_piece_id"];
      if ($is_selected) {
        $extra_classes[] = "with-selected-piece";
        if ($current_form_action == 'murder') {
          $form["textes"]["phase"]["#markup"] .= ' [' . $variables['helps']['cadavre'] . ']';
        }
        elseif (isset($variables['helps'][$piece->getType()])) {
          $form["textes"]["phase"]["#markup"] .= ' [' . $variables['helps'][$piece->getType()] . ']';
        }
      }
      $attributes = array();
      $attributes['class'] = array('piece');
      if (!$piece->isAlive()) {
        $attributes['class'][] = 'dead';
        $attributes['title']=  t("Here lies a dead piece");
      }
      else {
        $attributes['title'] = _kw_djambi_get_full_piece_name($piece);
        $attributes['class'][] = 'alive';
        $attributes['class'][] = $piece->getType();
        $attributes['class'][] = $faction->getClass() . '-origin';
        if ($faction->getControl()->isAlive()) {
          $attributes['class'][] = $faction->getControl()->getClass() . '-control';
        }
        if (isset($form['pieces']["movable"]) && $piece->isMovable()) {
          $attributes['class'][] = 'movable';
          $attributes['title'] .= " - " . t("this piece can be moved during this turn");
          $attributes['data-moves'] = implode(' ', $moves);
        }
        else {
          $attributes['class'][] = 'unmovable';
        }
      }
      if (!empty($animated_moves) && isset($animated_moves['pieces'][$piece->getId()])) {
        foreach ($animated_moves['pieces'][$piece->getId()] as $move_id) {
          $move = $animated_moves['moves'][$move_id];
          $attributes['data-animation-' . $move_id] = $move['type'] . ':' . $move['to'];
        }
      }
      $row_inner_html .= "<div " . drupal_attributes($attributes) . ">";
      if (!$piece->isAlive()) {
        $row_inner_html .= "<abbr title=\"" . t("Dead") . "\">†</abbr>";
      }
      elseif (!empty($form['pieces']) && isset($form['pieces']['movable']) && isset($form['pieces']['movable'][$piece->getId()])) {
        $row_inner_html .= drupal_render($form['pieces']['movable'][$piece->getId()]);
      }
      else {
        $row_inner_html .= "<img src=\"" . $image_folder . $piece->getImage() . '.' . $image_extension . "\" alt=\"\" />";
      }
      if ($piece->isAlive()) {
        $row_inner_html .= "<span class='piece-txt'><abbr title=\"" . t("!piece, owned by !color team.", array(
              "!piece" => _kw_djambi_get_full_piece_name($piece),
              "!color" => _kw_djambi_get_translatable_messages($faction->getControl()->getName())
            )) . "\">"
            . $piece->getId() . "</abbr></span>";
      }
      $row_inner_html .= "</div>";
      if ($is_selected) {
        if (in_array($current_form_action, array('murder', 'necromobility'))) {
          $row_inner_html .= "<div class='piece dead positionable' title=\""
            . t("Dead piece, waiting for you to bury somewhere.") . "\"><abbr title=\""
            . t("Dead") . "\">†</abbr></div>";
        }
        elseif ($current_form_action == 'manipulation') {
          $manipulated_piece = $grid->getPieceById($form["pieces"]["manipulation"]["#target_piece_id"]);
          $classes = array('piece', 'alive', 'positionable');
          $piece_name = _kw_djambi_get_full_piece_name($manipulated_piece);
          $classes[] = 'alive';
          $classes[] = $manipulated_piece->getType();
          $classes[] = $manipulated_piece->getFaction()->getClass() . '-origin';
          if ($manipulated_piece->getFaction()->getControl()->isAlive()) {
            $classes[] = $manipulated_piece->getFaction()->getControl()->getClass() . '-control';
          }
          $title = t("Move the manipulated !piece_name in an empty cell", array('!piece' => $piece_name));
          $row_inner_html .= "<div class='" . implode(' ', $classes) . "' title=\"" . $title . "\">"
              . "<img src=\"" . $image_folder . $manipulated_piece->getImage() . '.' . $image_extension . "\" alt=\"" . $piece_name . "\" />"
              . "</div>";
        }
      }
      if ($variables['show_help']) {
        if ($piece->isAlive() && isset($variables['helps'][$piece->getType()])) {
          $row_inner_html .= ' <span class="help-link">' . $variables['helps'][$piece->getType()] . '</span>';
        }
        elseif (!$piece->isAlive() && isset($variables['helps']['cadavre'])) {
          $row_inner_html .= ' <span class="help-link">' . $variables['helps']['cadavre'] . '</span>';
        }
      }
    }
    $rows[$cell['y']]['data'][$cell['x']] = array(
        'data' => $row_inner_html,
        'title' => t('!xy case', array('!xy' => $key))
        . ($current_form_action == 'piece_selection' && isset($cell['reachable']) ? ' - ' . t('a piece can move here...') : ''),
        'class' => $extra_classes,
        'data-coord' => $key
    );
  }
  // Cases spéciales
  $thrones = $grid->getSpecialCells('throne');
  if (!empty($thrones)) {
    foreach ($thrones as $key => $throne) {
      $rows[$cells[$throne]['x']]["data"][$cells[$throne]['y']]["title"] = t("Throne case !");
      $rows[$cells[$throne]['x']]["data"][$cells[$throne]['y']]["data"] .= "<div class='element-invisible'>" . t("Throne case !") . "</div>"
          . ($variables['show_help'] && isset($variables['helps']['labyrinthe']) ? "<span class='help-link'>" . $variables['helps']['labyrinthe'] . "</span>" : "");
    }
  }

  // Affichage de la grille
  $grid_classes = array('djambigrid');
  $grid_id = 'DjambiGrid' . $grid->getId();
  if ($grid->getScheme()->getDisposition() == 'hexagonal') {
    $markup = theme('hexagonal_grid', array(
      'rows' => $rows,
      'attributes' => array('class' => $grid_classes, 'id' => $grid_id),
      'caption' => $variables['caption']
    ));
  }
  else {
    $markup = theme('table', array(
      'header' => $variables['header'],
      'rows' => $rows,
      'attributes' => array('class' => $grid_classes, 'id' => $grid_id),
      'caption' => $variables['caption'],
      'sticky' => !$show_changes
    ));
  }
  $html = $variables['game_description'] . $markup;
  $children = element_children($form);
  foreach ($children as $child) {
    if ($child == 'textes') {
      continue;
    }
    $html .= drupal_render($form[$child]);
  }
  $html .= $variables['events'];
  return $html;
}

/**
 * Génération d'une pseudo-grille avec cases hexagonales
 */
function theme_hexagonal_grid($variables) {
  $rows = $variables['rows'];
  $attributes = $variables['attributes'];
  $caption = $variables['caption'];
  $attributes['class'][] = 'hexagonal-grid';
  $html = '<div class="hexagonal-grid-wrapper"><div ' . drupal_attributes($attributes) . '>';
  if (!empty($caption)) {
    $html.= "<div class='caption'>" . $caption . "</div>";
  }
  $i = 0;
  foreach ($rows as $y => $row) {
    $i++;
    $attributes = array();
    foreach ($row as $key => $value) {
      if ($key != 'data') {
        $attributes[$key] = $value;
      }
    }
    $attributes['class'][] = ($i % 2 == 0 ? 'even' : 'odd');
    $attributes['class'][] = 'row';
    $html .= '<div ' . drupal_attributes($attributes) . '>';
    foreach ($row['data'] as $x => $cell) {
      $attributes = array();
      foreach ($cell as $key => $value) {
        if ($key != 'data') {
          $attributes[$key] = $value;
        }
      }
      if (empty($attributes['class']) || !in_array('cell', $attributes['class'])) {
        $attributes['class'][] = 'cell';
      }
      $html .= "<div " . drupal_attributes($attributes) . ">";
      if (!in_array('disabled', $cell['class'])) {
        $html .= "<div class='cell-top'></div><div class='cell-top-inside'></div>";
        $html .= "<div class='cell-bottom'></div><div class='cell-bottom-inside'></div>";
        $html .= "<div class='cell-coordonates'>" . $cell['data-coord'] . "</div>";
        $html .= "<div class='cell-content'>" . $cell['data'] . "</div>";
      }
      $html .= "</div>";
    }
    $html .= "</div>";
  }
  $html .= '</div></div>';
  return $html;
}

/**
 * Affichage du tableau des joueurs sur une partie en cours
 */
function theme_djambi_players_table($variables) {
  global $user;
  $grid = $variables['grid'];
  $pheader['sides'] = array('data' => t('Sides'), 'scope' => 'col');
  $pheader['players'] = array('data' => t('Players'), 'scope' => 'col');
  if ($grid->getMode() == KW_DJAMBI_MODE_FRIENDLY) {
    //$pheader['ip'] = array('data' => t('IP address'), 'scope' => 'col');
    $pheader['ping'] = array('data' => t('Ping'), 'scope' => 'col');
  }
  $pheader['status'] = array('data' => t('Status'), 'scope' => 'col');
  $prows = array();
  foreach ($grid->getFactions() as $faction) {
    $prow = array();
    $prow['sides'] = array('data' => theme('djambi_faction_name', array('faction' => $faction)));
    if (!in_array($faction->getStatus(), array(KW_DJAMBI_USER_VASSALIZED, KW_DJAMBI_USER_EMPTY_SLOT))) {
      $me = $grid->getGameManager()->checkUserPlayingFaction($user->uid, _kw_djambi_cookie(), $faction, FALSE);
      if (!$faction->isHumanControlled()) {
        $prow['players'] = array('data' => '<div class="icon cogs">' . t('Computer') . ' <em>(' . $faction->getIa()->getName() . ')</em></div>');
      }
      else {
        $uid = $faction->getUserDataItem('uid');
        if ($me && $uid == 0) {
          $prow['players'] = array('data' => t('Me !'));
        }
        else {
          $prow['players'] = array('data' => theme('username', array('account' => user_load($uid)))
              . ($me ? ' (' . t('Me !') . ')' : ''));
        }
      }
      if (isset($pheader['ip'])) {
        $prow['ip'] = array('data' => $faction->getUserDataItem('ip'));
      }
      if (isset($pheader['ping'])) {
        $ping = _kw_djambi_format_ping_infos($faction->getUserDataItem('ping'));
        $prow['ping'] = array('data' => $ping['status'], 'class' => array('ping-info', $ping['class']), 'title' => $ping['title']);
      }
    }
    else {
      $me = FALSE;
      $prow['players'] = array('data' => '-');
      if (isset($pheader['ip'])) {
        $prow['ip'] = array('data' => '-');
      }
      if (isset($pheader['ping'])) {
        $prow['ping'] = array('data' => '-');
      }
    }
    $prow['status'] = array('data' => _kw_djambi_get_translatable_messages($faction->getStatus()) .
        ($faction->getControl()->getId() != $faction->getId() ? ', '
            . t('controlled by !faction', array('!faction' => theme('djambi_faction_name', array('faction' => $faction->getControl())))) : ''));
    $prows[] = array('data' => $prow, 'data-djuid' => $faction->getUserDataItem('djuid'),
        'class' => $me ? array('me') : array('not-me'));
  }
  return theme('table', array('rows' => $prows, 'header' => $pheader,
      'attributes' => array('class' => array('players')), 'sticky' => FALSE));
}

/**
 * Génération de la légende d'une grille
 */
function theme_djambi_grid_caption($variables) {
  $grid = $variables['grid'];
  $displayed_turn = $variables['displayed_turn'];
  $displayed_turn_id = $variables['displayed_turn_id'];
  $show_changes = $variables['show_changes'];
  $turns = $grid->getTurns();
  $max_turn = end($turns);
  $caption_title = _kw_djambi_get_translatable_messages($grid->getStatus());
  if (!$show_changes && $grid->isPending()) {
    $current_turn = end($turns);
    $caption_title .= ' - <span class="turn">' . t("Turn #%turn", array("%turn" => $current_turn["turn"])) . '</span>';
  }
  else {
    if ($grid->isPending()) {
      $caption_title .= ' <span class="warning">' . t("Replay mode : you are currently watching a past state ot the battlefield !") . '</span>';
    }
    $caption_title .= ' - <span class="turn">' . t("Turn #%displayed_turn out of #%max_turn",
        array("%displayed_turn" => $displayed_turn["turn"], '%max_turn' => $max_turn["turn"])) . '</span>';
    if (!empty($animated_moves)) {
      $playing_faction = $grid->getFactionById($displayed_turn['side']);
      $caption_title .= "<br /><span class='recap'>" . t("Showing !faction side move, began at %begin, duration : %duration", array(
          '!faction' => theme('djambi_faction_name', array('faction' => $playing_faction)),
          '%begin' => format_date($displayed_turn['begin'], 'short'),
          '%duration' => format_interval($displayed_turn['end'] - $displayed_turn['begin'])
      )) . "</span>";
    }
    else {
      foreach ($grid->getEvents() as $event) {
        if ($event['turn'] == $displayed_turn_id && $event['type'] == 'event') {
          $caption_title .= "<br /><span class='recap'><em>" . format_date($event['time'], 'short') ."</em> : " . _kw_djambi_get_translatable_messages($event['event'], $event['args'], $grid) . "</span>";
          break;
        }
      }
    }
    if ($grid->isFinished()) {
      $final_time = isset($max_turn['end']) ? $max_turn['end'] : $max_turn['begin'];
      $caption_title .= "<br /><span class='recap'>" . t("Finished on %date, game total duration : %duration", array(
          '%date' => format_date($final_time, 'short'),
          '%duration' => format_interval($final_time - $turns[0]['begin'])
      )) . "</span>";
    }
  }
  return $caption_title;
}

/**
 * Affichage des derniers événements
 */
function theme_djambi_recent_events($variables) {
  $grid = $variables['grid'];
  $display_events = $grid->isPending();
  if ($grid->getInfo('interface') == 'minimal') {
    $display_events = FALSE;
  }
  if ($display_events) {
    $events = $grid->getEvents();
    $recent_events = array();
    if (!isset($turn)) {
      $turn = $grid->getCurrentTurnId() - 1;
    }
    foreach($events as $event) {
      if ($event['type'] != 'notice' && $event['turn'] >= $turn) {
        $recent_events[] = '<strong>' . format_date($event['time'], 'custom', 'H:i:s') . '</strong> : ' . _kw_djambi_describe_event($event, $grid);
      }
    }
    if (!empty($recent_events)) {
      return '<div class="events">' . theme('item_list', array(
              'items' => $recent_events,
              'title' => t('Recent events'),
          )) . '</div>';
    }
  }
}

/**
 * Affichage d'un tableau affiché pendant la phase de recrutement
 */
function theme_djambi_recruiting_phase($variables) {
  global $user;
  $form = $variables["form"];
  // Détermination de la phase de jeu
  /* @var $grid DjambiBattlefield */
  $grid = $form["#grid"];
  $header = array(
    0 => array('data' => t('Faction')),
    1 => array('data' => t('User')),
    //2 => array('data' => t('IP address')),
    3 => array('data' => t('Ping')),
    4 => array('data' => t('Waiting time')),
    5 => array('data' => t('Action'))
  );
  $rows = array();
  $empty = 0;
  foreach (element_children($form['factions']) as $id) {
    if (!empty($form['factions'][$id]['infos'])) {
      $ip = $form['factions'][$id]['infos']['#value']['ip'];
      $ping = $form['factions'][$id]['infos']['#value']['ping'];
      $ping_array = _kw_djambi_format_ping_infos($ping);
      $joined = format_interval(time() - $form['factions'][$id]['infos']['#value']['joined']);
    }
    else {
      $ip = $ping_array['status'] = $joined = '?';
      $ping_array['class'] = 'empty';
      $ping_array['title'] = '?';
      $empty++;
    }
    $row = array(
      0 => array('data' => $form['factions'][$id]['label']['#title']),
      1 => array('data' => $form['factions'][$id]['label']['#markup'] .
          (isset($form['factions'][$id]['infos']) && $form['factions'][$id]['infos']['#value']['me'] ? ' ('. t('Me !') . ')' : '')),
      //2 => array('data' => $ip),
      3 => array('data' => $ping_array['status'], 'class' => array($ping_array['class'], 'ping-info'),
            'title' => $ping_array['title']),
      4 => array('data' => $joined, 'class' => array('joined')),
      5 => array('data' => !empty($form['factions'][$id]['action']) ? drupal_render($form['factions'][$id]['action']) : '-')
    );
    $rows[] = array('data' => $row, 'class' => isset($form['factions'][$id]['infos']) && $form['factions'][$id]['infos']['#value']['me'] ? array('me') : array('not-me'),
            'data-djuid' => !empty($form['factions'][$id]['infos']['#value']['djuid']) ? $form['factions'][$id]['infos']['#value']['djuid'] : NULL);
  }
  unset($form['factions']);
  $caption = t('Waiting for other players : !remaining', array('!remaining' =>
      format_plural($empty, '1 empty slot remaining', '!nb empty slots remaining', array('!nb' => $empty))));
  $infos_lines[t("Mode")] = _kw_djambi_get_translatable_messages($grid->getMode());
  $infos_lines[t("Unbegun game")] = theme('djambi_waiting_label', array('faction' => NULL, 'grid' => $grid));
  $prefix = theme('djambi_game_description', array('infos_lines' => $infos_lines, 'grid' => $grid));
  $html = $prefix . theme('table', array('header' => $header, 'rows' => $rows,
          'caption' => $caption, 'attributes' => array('class' => array('players'))));
  $children = element_children($form);
  foreach ($children as $child) {
    $html .= drupal_render($form[$child]);
  }
  return $html;
}

/**
 * Affichage des logs
 */
function theme_djambi_log($variables) {
  $form = $variables["form"];
  $buttons = array();
  if (!empty($form['buttons'])) {
    $children = element_children($form['buttons']);
    foreach ($children as $child) {
      $buttons['[' . $child . ']'] = drupal_render($form['buttons'][$child]);
    }
    unset($form['buttons']);
  }
  $html = drupal_render_children($form);
  if (!empty($buttons)) {
    $html = str_replace(array_keys($buttons), $buttons, $html);
  }
  return $html;
}

/**
 * Affichage de lignes d'informations sur la partie en cours
 */
function theme_djambi_game_description($variables) {
  $infos_lines = $variables['infos_lines'];
  if (!empty($infos_lines)) {
    $infos_items = array();
    foreach ($infos_lines as $legend => $line) {
      $infos_items[] = '<span class="pseudo-label">' . $legend . ' : </span><span class="value">' . $line . '</span>';
    }
    return theme('item_list', array('items' => $infos_items));
  }
}

/**
 * Affichage d'informations sur la phase en cours
 * (potentiellement mise à jour régulièrement par Ajax)
 */
function theme_djambi_waiting_label($variables) {
  $now_playing_faction = $variables['faction'];
  $grid = $variables['grid'];
  $last_change = $grid->getInfo('changed');
  if (!is_null($now_playing_faction)) {
    $args = array(
      '!side' => _kw_djambi_get_translatable_messages($now_playing_faction->getName()),
      '!duration' => '<span class="time-elapsed">' . _kw_djambi_get_duration(time() - $last_change) . '</span>'
    );
  }
  switch ($grid->getStatus()) {
    case(KW_DJAMBI_STATUS_RECRUITING) :
      $label = t("Waiting for new players...");
    break;
    case(KW_DJAMBI_STATUS_DRAW_PROPOSAL) :
      $label = t("Waiting for !side decision for !duration...", $args);
    break;
    default:
      $label = t("Waiting for !side move for !duration...", $args);
  }
  return '<span class="refresh">' . $label
  . ' (' . t('Last update : !date', array('!date' => '<span class="time-last-update">' . format_date(time(), 'custom', 'H:i:s') . '</span>'))
  . ')</span>';
}