<?php
/**
 * Affichage de la grille à partir des données du formulaire
 */
function theme_djambi_grid($variables) {
  global $user;
  $form = $variables["form"];
  // Détermination de la phase de jeu
  /* @var $grid DjambiBattlefield */
  $grid = $form["#grid"];
  $form_pieces = NULL;
  $current_form_action = NULL;
  $has_destination_layer = FALSE;
  $display_changes = TRUE;
  $display_events = TRUE;
  $dispositions = DjambiBattlefield::getDispositions();
  $disposition = $grid->getDisposition();
  if (isset($form["pieces"])) {
    $display_changes = FALSE;
    $form_pieces = $form["pieces"];
    if (isset($form_pieces["murder"])) {
      $current_form_action = "murder";
      $has_destination_layer = TRUE;
    }
    elseif (isset($form_pieces["necromobility"])) {
      $current_form_action = "necromobility";
      $has_destination_layer = TRUE;
    }
    elseif (isset($form_pieces["manipulation"])) {
      $current_form_action = "manipulation";
      $has_destination_layer = TRUE;
    }
    elseif (isset($form_pieces["destination"])) {
      $current_form_action = "destination";
      $has_destination_layer = TRUE;
    }
    elseif (isset($form_pieces["reportage"])) {
      $current_form_action = "reportage";
      $has_destination_layer = TRUE;
    }
    elseif (isset($form_pieces["throne_evacuation"])) {
      $current_form_action = "throne_evacuation";
      $has_destination_layer = TRUE;
    }
    else {
      $current_form_action = "piece_selection";
      $display_changes = TRUE;
    }
  }
  // Préparation de l'affichage des derniers mouvements
  $show_changes = FALSE;
  $show_moves_settings = $form['show_moves']['#value'];
  if ($show_moves_settings['show'] && $display_changes) {
    $moves = $grid->getMoves();
    $new_moves = array();
    $changing_cells = array();
    if (!empty($moves)) {
      if ($show_moves_settings['type'] == 'turn-based') {
        $last_move = $moves[max(array_keys($moves))];
        $turn = $last_move['turn'] - $show_moves_settings['turn'] + 1;
      }
      else {
        $turn = $grid->getCurrentTurnId() - $show_moves_settings['turn'];
      }
      $show_changes = TRUE;
      $i = 1;
      foreach ($moves as $key => $move) {
        if ($move['turn'] >= $turn) {
          if ($move['type'] == 'move') {
            $faction_id = $move['target_faction'];
          }
          else {
            $faction_id = $move['acting_faction'];
          }
          $acting_faction = $grid->getFactionById($faction_id);
          $changing_cells[$move['from']] = $acting_faction->getClass();
          $changing_cells[$move['to']] = $acting_faction->getClass();
          $new_moves[$i++] = array(
            'description' => _kw_djambi_describe_move($move, $grid),
            'location' => $move['to'],
            'order' => $move['turn'] + 1,
            'faction' => $acting_faction->getClass()
          );
        }
      }
    }
  }
  // Construction de la grille
  if ($dispositions[$disposition]['grid'] != 'hexagonal') {
    for ($i = 0; $i <= $grid->getDimensions(); $i++) {
      $header[] = array("data" => DjambiBattlefield::intToAlpha($i), "class" => array("graduation"));
      if ($i > 0) {
        $rows[$i] = array(
          "data" => array(
              0 => array("data" => $i, "header" => TRUE, "class" => array("graduation"))
          ),
          "no_striping" => TRUE,
          "class" => array("no-hover")
        );
      }
    }
  }
  foreach ($grid->getCells() as $key => $cell) {
    $row_inner_html = "";
    $extra_classes = array('cell', $cell['type'],
        isset($form_pieces["movable"]) && isset($cell["reachable"]) ? "reachable" : "unreachable");
    if ($show_changes && isset($changing_cells[$key])) {
      $extra_classes[] = 'recent-change';
      $extra_classes[] = $changing_cells[$key];
      $row_inner_html .= "<div class='changes'>";
      foreach ($new_moves as $order => $move) {
        if ($move['location'] == $key) {
          $row_inner_html .= "<div class='change " .$move['faction']. "' data-order='" . $order . "'><span class='order'>" . $move['order'] . "</span>"
          . "<div class='description'>" . $move['description'] . "</div></div>";
        }
      }
      $row_inner_html .= '</div>';
    }
    if ($has_destination_layer) {
      if (isset($form_pieces[$current_form_action]["cell-" . $key])) {
        $destination = drupal_render($form_pieces[$current_form_action]["cell-" . $key]);
        $row_inner_html .= "<div class='destination-layer'>" . $destination . "</div>";
        $extra_classes[] = "selectable";
      }
      else {
        $extra_classes[] = "unselectable";
      }
    }
    if(!is_null($cell["occupant"])) {
      $piece = $cell["occupant"];
      $faction = $piece->getFaction();
      $extra_classes[] = "with-piece";
      if (isset($form_pieces["movable"]) && $piece->isMovable()) {
        $extra_classes[] = "with-movable-piece";
        $moves = $piece->getAllowableMoves();
      }
      if (isset($form_pieces[$current_form_action]) && isset($form_pieces[$current_form_action]["#selected_piece_id"]) && $piece->getId() == $form_pieces[$current_form_action]["#selected_piece_id"]) {
        $extra_classes[] = "with-selected-piece";
      }
      $row_inner_html .= "<div class='piece " . (!$piece->isAlive() ? "dead "
      : "alive " . $piece->getType() . " " . ($faction->getControl()->isAlive() ? $faction->getControl()->getClass() . '-control ' : '')
          . $faction->getClass() . '-origin')
      . (isset($form_pieces["movable"]) && $piece->isMovable() ? " movable" : " unmovable") . "' "
      . (isset($form_pieces["movable"]) && $piece->isMovable() ? ' data-moves="' . implode(' ', $moves) . '"' : '')
      . "title=\"" . ($piece->isAlive() ? (_kw_djambi_get_full_piece_name($piece) . ($piece->isMovable() ? " - "
          . t("this piece can be moved during this turn") : "")) : t("Here lies a dead piece")) . "\""
          . ">";
      if (!$piece->isAlive()) {
        $row_inner_html .= "<abbr title=\"" . t("Dead") . "\">†</abbr>";
      }
      elseif (!is_null($form_pieces) && isset($form_pieces["movable"]) && isset($form_pieces["movable"][$piece->getId()])) {
        $row_inner_html .= drupal_render($form["pieces"]["movable"][$piece->getId()]);
      }
      else {
        $row_inner_html .= "<img src=\"/" . $piece->getImage() . "\" alt=\"\" />";
      }
      if ($piece->isAlive()) {
        $row_inner_html .= "<abbr title=\"" . t("!piece, owned by !color team.", array(
            "!piece" => _kw_djambi_get_full_piece_name($piece), "!color" => $faction->getControl()->getName("t"))) . "\">"
            . $piece->getId() . "</abbr>";
      }
      $row_inner_html .= "</div>";
    }
    $rows[$cell['y']]['data'][$cell['x']] = array(
        'data' => $row_inner_html,
        'title' => t('!xy case', array('!xy' => $key))
        . ($current_form_action == 'piece_selection' && isset($cell['reachable']) ? ' - ' . t('a piece can move here...') : ''),
        'class' => $extra_classes,
        'data-coord' => $key
    );
  }
  // Création des légendes de la grille
  $caption = "";;
  $infos_lines[t("Mode")] = _kw_djambi_get_translatable_messages($grid->getMode());
  if ($grid->getMode() == KW_DJAMBI_MODE_SANDBOX) {
    foreach ($grid->getFactions() as $random_faction) {
      if ($random_faction->getStatus() != KW_DJAMBI_USER_VASSALIZED) {
        break;
      }
    }
    $current_user = user_load($random_faction->getUserDataItem('uid'));
    $player = theme('username', array('account' => $current_user));
    if ($current_user->uid == 0) {
      $ip = $random_faction->getUserDataItem('ip');
      if (!empty($ip)) {
        $player .= ' (' . t('IP adress : !ip', array('!ip' => $ip)) . ')';
      }
    }
    $infos_lines[t('Player')] = t('!player, controlling all sides.', array('!player' => $player));
  }
  elseif ($grid->isPending()) {
    $pheader = array(
      array('data' => t('Sides')),
      array('data' => t('Players')),
      array('data' => t('IP address')),
      array('data' => t('Ping')),
      array('data' => t('Status'))
    );
    $prows = array();
    foreach ($grid->getFactions() as $faction) {
      $prow = array();
      $prow[] = array('data' => '<span class="faction ' . $faction->getClass() . '">' . $faction->getName() . '</span>');
      if (!in_array($faction->getStatus(), array(KW_DJAMBI_USER_VASSALIZED, KW_DJAMBI_USER_EMPTY_SLOT))) {
        $ping = _kw_djambi_format_ping_infos($faction->getUserDataItem('ping'));
        $me = _kw_djambi_check_current_user($faction, FALSE);
        $prow[] = array('data' => theme('username', array('account' => user_load($faction->getUserDataItem('uid'))))
          . ($me ? ' (' . t('Me !') . ')' : ''));
        $prow[] = array('data' => $faction->getUserDataItem('ip'));
        $prow[] = array('data' => $ping['status'], 'class' => array('ping-info', $ping['class']), 'title' => $ping['title']);
      }
      else {
        $me = FALSE;
        $prow[] = array('data' => '-');
        $prow[] = array('data' => '-');
        $prow[] = array('data' => '-');
      }
      $prow[] = array('data' => $faction->getStatus() .
          ($faction->getControl()->getId() != $faction->getId() ? ', '
              . t('controlled by !faction', array('!faction' => '<span class="faction ' . $faction->getControl()->getClass() . '">' . $faction->getControl()->getName() . '</span>')) : ''));
      $prows[] = array('data' => $prow, 'data-djuid' => $faction->getUserDataItem('djuid'),
            'class' => $me ? array('me') : array('not-me'));
    }
    $infos_lines[t('Players')] = theme('table', array('rows' => $prows, 'header' => $pheader,
        'attributes' => array('class' => array('players'))));
  }
  $fieldset_title = _kw_djambi_get_translatable_messages($grid->getStatus());
  $current_turn = end($grid->getTurns());
  $fieldset_title .= ' - <span class="turn">' . t("Turn #%turn", array("%turn" => $current_turn["turn"])) . '</span>';
  if ($grid->isPending()) {
    $play_order = $grid->getPlayOrder();
    $current_play_order = current($play_order);
    $now_playing_faction = $grid->getFactionById($current_play_order["side"]);
    $next_play_order = next($play_order);
    $playing_next_faction1 = $grid->getFactionById($next_play_order["side"]);
    $next_play_order = next($play_order);
    $playing_next_faction2 = $grid->getFactionById($next_play_order["side"]);
    $next_play_order = next($play_order);
    $playing_next_faction3 = $grid->getFactionById($next_play_order["side"]);
    $infos_lines[t("Now playing")] = t("!color side", array("!color" =>
        "<span class='faction " . $now_playing_faction->getClass() . "'>" . $now_playing_faction->getName("t") . "</span>"
    ));
    if ($grid->getStatus() != KW_DJAMBI_STATUS_DRAW_PROPOSAL) {
      $infos_lines[t("Playing next")] = t("!color1 side, then !color2 side, then !color3 side", array(
        "!color1" => "<span class='faction " . $playing_next_faction1->getClass() . "'>" . $playing_next_faction1->getName() . "</span>",
        "!color2" => "<span class='faction " . $playing_next_faction2->getClass() . "'>" . $playing_next_faction2->getName() . "</span>",
        "!color3" => "<span class='faction " . $playing_next_faction3->getClass() . "'>" . $playing_next_faction3->getName() . "</span>"
      ));
      if (isset($form["textes"]["phase"])) {
        $infos_lines[t("Current phase")] = $form["textes"]["phase"]["#markup"];
        unset($form["textes"]["phase"]);
      }
      else {
        $infos_lines[t("Current phase")] = _kw_djambi_waiting_label($now_playing_faction, $grid);
      }
    }
    else {
      $accepted = array();
      $waiting = array();
      foreach($grid->getFactions() as $playing_faction) {
        if (!$playing_faction->isAlive()) {
          continue;
        }
        $faction_name = '<span class="faction ' . $playing_faction->getClass() . '">' . $playing_faction->getName() . '</span>';
        if ($playing_faction->getDrawStatus() == 1) {
          $peacemonger = $faction_name;
        }
        elseif ($playing_faction->getDrawStatus() == 2) {
          $accepted[] = $faction_name;
        }
        elseif (is_null($playing_faction->getDrawStatus())) {
          $waiting[] = $faction_name;
        }
      }
      $infos_lines[t("Current phase")] = t("Draw asked by !faction side.", array('!faction' => $peacemonger));
      if (!empty($accepted)) {
        $infos_lines[t("Sides having accepted the draw")] = implode(', ', $accepted);
      }
      if (!empty($waiting)) {
        $infos_lines[t("Undecided sides")] = implode(', ', $waiting);
        $last_change = $grid->getInfo('changed');
        if (!_kw_djambi_check_current_user($playing_faction)) {
          $infos_lines[t("Pending decision")] = _kw_djambi_waiting_label($now_playing_faction, $grid);
        }
      }
    }
  }
  $c = 0;
  _kw_djambi_add_info_lines($infos_lines, $form);
  // Affichage du tableau
  $rows[5]["data"][5]["title"] = t("Throne case !");
  $rows[5]["data"][5]["data"] .= "<div class='element-invisible'>" . t("Throne case !") . "</div>";
  if ($dispositions[$disposition]['grid'] == 'hexagonal') {
    $markup = theme('hexagonal_grid', array(
      'rows' => $rows,
      'attributes' => array('class' => array('djambigrid'), 'id' => 'DjambiGrid' . $grid->getId()),
      'caption' => $fieldset_title
    ));
  }
  else {
    $markup = theme('table', array(
      'header' => $header,
      'rows' => $rows,
      'attributes' => array('class' => array('djambigrid'), 'id' => 'DjambiGrid' . $grid->getId()),
      'caption' => $fieldset_title
    ));
  }
  $form['grid']['table'] = array(
    '#markup' => $markup,
    '#weight' => 0
  );
  // Affichage des derniers événements
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
      $form['grid']['recent_events'] = array(
          '#markup' => '<div class="events">' . theme('item_list', array(
              'items' => $recent_events,
              'title' => t('Recent events'),
          )) . '</div>',
          '#weight' => 20
      );
    }
  }
  // Affichage
  unset($form['pieces']);
  $html = drupal_render_children($form);
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
    2 => array('data' => t('IP address')),
    3 => array('data' => t('Ping')),
    4 => array('data' => t('Waiting time')),
    5 => array('data' => t('Action'))
  );
  $rows = array();
  $empty = 0;
  foreach (element_children($form['grid']['factions']) as $id) {
    if (!empty($form['grid']['factions'][$id]['infos'])) {
      $ip = $form['grid']['factions'][$id]['infos']['#value']['ip'];
      $ping = $form['grid']['factions'][$id]['infos']['#value']['ping'];
      $ping_array = _kw_djambi_format_ping_infos($ping);
      $joined = format_interval(time() - $form['grid']['factions'][$id]['infos']['#value']['joined']);
    }
    else {
      $ip = $ping_array['status'] = $joined = '?';
      $ping_array['class'] = 'empty';
      $ping_array['title'] = '?';
      $empty++;
    }
    $row = array(
      0 => array('data' => $form['grid']['factions'][$id]['label']['#title']),
      1 => array('data' => $form['grid']['factions'][$id]['label']['#markup'] .
          (isset($form['grid']['factions'][$id]['infos']) && $form['grid']['factions'][$id]['infos']['#value']['me'] ? ' ('. t('Me !') . ')' : '')),
      2 => array('data' => $ip),
      3 => array('data' => $ping_array['status'], 'class' => array($ping_array['class'], 'ping-info'),
            'title' => $ping_array['title']),
      4 => array('data' => $joined, 'class' => array('joined')),
      5 => array('data' => !empty($form['grid']['factions'][$id]['action']) ? drupal_render($form['grid']['factions'][$id]['action']) : '-')
    );
    $rows[] = array('data' => $row, 'class' => isset($form['grid']['factions'][$id]['infos']) && $form['grid']['factions'][$id]['infos']['#value']['me'] ? array('me') : array('not-me'),
            'data-djuid' => !empty($form['grid']['factions'][$id]['infos']['#value']['djuid']) ? $form['grid']['factions'][$id]['infos']['#value']['djuid'] : NULL);
  }
  unset($form['grid']['factions']);
  $caption = t('Waiting for other players : !remaining', array('!remaining' =>
      format_plural($empty, '1 empty slot remaining', '!nb empty slots remaining', array('!nb' => $empty))));
  $infos_lines[t("Mode")] = _kw_djambi_get_translatable_messages($grid->getMode());
  $infos_lines[t("Unbegun game")] = _kw_djambi_waiting_label(NULL, $grid);
  _kw_djambi_add_info_lines($infos_lines, $form);
  $form['grid']['players'] = array(
      '#markup' => theme('table', array('header' => $header, 'rows' => $rows,
          'caption' => $caption, 'attributes' => array('class' => array('players')))),
      '#weight' => 10
  );
  $html = drupal_render_children($form);
  return $html;
}

/**
 * Affichage de lignes d'informations sur la partie en cours
 */
function _kw_djambi_add_info_lines($infos_lines, &$form) {
  if (!empty($infos_lines)) {
    $infos_items = array();
    foreach ($infos_lines as $legend => $line) {
      $infos_items[] = '<span class="label">' . $legend . ' : </span><span class="value">' . $line . '</span>';
    }
    $form['grid']['infos'] = array(
        '#markup' => theme('item_list', array('items' => $infos_items)),
        '#weight' => -10
    );
  }
}

/**
 * Affichage d'informations sur la phase en cours
 * (potentiellement mise à jour régulièrement par Ajax)
 */
function _kw_djambi_waiting_label($now_playing_faction, DjambiBattlefield $grid) {
  $last_change = $grid->getInfo('changed');
  if (!is_null($now_playing_faction)) {
    $args = array(
      '!side' => $now_playing_faction->getName(),
      '!duration' => '<span class="time-elapsed">' . kw_tools_duration(time() - $last_change) . '</span>'
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