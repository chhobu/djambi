<?php
/**
 * Affichage de la grille à partir des données du formulaire
 */
function theme_djambi_grid($variables) {
  global $user;
  $form = $variables["form"];
  /* @var $grid DjambiBattlefield */
  $grid = $form["#grid"];
  $form_pieces = NULL;
  $current_form_action = NULL;
  $selected_piece = NULL;
  $has_destination_layer = FALSE;
  $display_events = $grid->isPending();
  if ($grid->getInfo('interface') == 'minimal') {
    $display_events = FALSE;
  }
  $cells = $grid->getCells();
  $image_folder = base_path() . drupal_get_path('module', 'kw_djambi') . '/img/';
  $image_extension = 'png';
  if (is_null($cells)) {
    return t("This game has been deleted.");
  }
  // Construction de l'aide en ligne
  $show_help = $form['show_help']['#value'];
  $show_moves = $form['show_moves']['#value'];
  $show_changes = FALSE;
  $helps = _kw_djambi_load_rule_nodes($grid);
  // Détermination de la phase de jeu
  if (isset($form["pieces"])) {
    $form_pieces = $form["pieces"];
    if (isset($form_pieces["murder"])) {
      $current_form_action = "murder";
      $has_destination_layer = TRUE;
      $show_moves = FALSE;
    }
    elseif (isset($form_pieces["necromobility"])) {
      $current_form_action = "necromobility";
      $has_destination_layer = TRUE;
      $show_moves = FALSE;
    }
    elseif (isset($form_pieces["manipulation"])) {
      $current_form_action = "manipulation";
      $has_destination_layer = TRUE;
      $show_moves = FALSE;
    }
    elseif (isset($form_pieces["destination"])) {
      $current_form_action = "destination";
      $has_destination_layer = TRUE;
      $show_moves = FALSE;
    }
    elseif (isset($form_pieces["reportage"])) {
      $current_form_action = "reportage";
      $has_destination_layer = TRUE;
      $show_moves = FALSE;
    }
    elseif (isset($form_pieces["throne_evacuation"])) {
      $current_form_action = "throne_evacuation";
      $has_destination_layer = TRUE;
      $show_moves = FALSE;
    }
    else {
      $current_form_action = "piece_selection";
    }
  }
  // Préparation de l'affichage d'un déplacement
  $turns = $grid->getTurns();
  $moves = $grid->getMoves();
  $displayed_turn_id = $grid->getDisplayedTurnId();
  $current_turn_id = $grid->getCurrentTurnId();
  if (!empty($turns)) {
    $displayed_turn = isset($turns[$displayed_turn_id]) ? $turns[$displayed_turn_id] : $turns[$current_turn_id];
  }
  else {
    $displayed_turn = 0;
  }
  $max_turn = end($turns);
  $new_moves = array();
  $last_moves = array();
  $changing_cells = array();
  if ($displayed_turn_id <= $current_turn_id) {
    $show_changes = TRUE;
    $animated_moves = array();
    if (!empty($moves)) {
      $i = 0;
      foreach ($moves as $move) {
        if (!$show_changes) {
          break;
        }
        if ($move['turn'] == $displayed_turn_id) {
          $animated_moves['moves'][$i] = $move;
          $animated_moves['pieces'][$move['target']][] = $i;
          if ($move['type'] == 'move' || !isset($move['acting_faction'])) {
            $faction_id = $move['target_faction'];
          }
          else {
            $faction_id = $move['acting_faction'];
          }
          $acting_faction = $grid->getFactionById($faction_id);
          if ($acting_faction) {
            $changing_cells[$move['from']] = $acting_faction->getClass();
            $changing_cells[$move['to']] = $acting_faction->getClass();
            $new_moves[$i++] = array(
              'description' => _kw_djambi_describe_move($move, $grid),
              'location' => $move['to'],
              'order' => $move['turn'] + 1,
              'faction' => $faction_id,
              'hidden' => !$show_moves
            );
          }
        }
      }
    }
  }
  // Affichage des derniers mouvements ?
  if ($show_moves && !$show_changes && !empty($moves)) {
    $factions = $grid->getFactions();
    if (isset($form['showable_turns'])) {
      $showable_turns = $form['showable_turns']['#value'];
      foreach ($moves as $move_key => $move) {
        if (in_array($move['turn'], $showable_turns)) {
          if ($move['type'] == 'move' || empty($move['acting_faction'])) {
            $faction_id = $move['target_faction'];
          }
          else {
            $faction_id = $move['acting_faction'];
          }
          $changing_cells[$move['from']] = $move['from'];
          $changing_cells[$move['to']] = $move['to'];
          $last_moves[] = array(
            'description' => _kw_djambi_describe_move($move, $grid),
            'location' => $move['to'],
            'order' => $move['turn'] + 1,
            'faction' => $faction_id
          );
        }
      }
    }
  }
  // Construction de la grille
  if ($grid->getScheme()->getDisposition() != 'hexagonal') {
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
    $changes = array();
    $extra_classes = array('cell', $cell['type'],
        isset($form_pieces["movable"]) && isset($cell["reachable"]) ? "reachable" : "unreachable");
    if (isset($changing_cells[$key])) {
      if ($show_changes && !empty($new_moves)) {
        $extra_classes[] = 'past-move';
        $extra_classes[] = $changing_cells[$key];
        foreach ($new_moves as $order => $move) {
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
      elseif ($show_moves && !empty($last_moves)) {
        $extra_classes[] = 'recent-move';
        foreach ($last_moves as $order => $move) {
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
      $is_selected = isset($form_pieces[$current_form_action]) && isset($form_pieces[$current_form_action]["#selected_piece_id"])
        && $piece->getId() == $form_pieces[$current_form_action]["#selected_piece_id"];
      if ($is_selected) {
        $extra_classes[] = "with-selected-piece";
        if ($current_form_action == 'murder') {
          $form["textes"]["phase"]["#markup"] .= ' [' . $helps['cadavre'] . ']';
        }
        elseif (isset($helps[$piece->getType()])) {
          $form["textes"]["phase"]["#markup"] .= ' [' . $helps[$piece->getType()] . ']';
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
        if (isset($form_pieces["movable"]) && $piece->isMovable()) {
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
      elseif (!is_null($form_pieces) && isset($form_pieces["movable"]) && isset($form_pieces["movable"][$piece->getId()])) {
        $row_inner_html .= drupal_render($form["pieces"]["movable"][$piece->getId()]);
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
      if ($show_help && $piece->isAlive() && isset($helps[$piece->getType()])) {
        $row_inner_html .= ' <span class="help-link">' . $helps[$piece->getType()] . '</span>';
      }
      elseif ($show_help && !$piece->isAlive() && isset($helps['cadavre'])) {
        $row_inner_html .= ' <span class="help-link">' . $helps['cadavre'] . '</span>';
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
  // Création des légendes de la grille
  $caption = "";
  if ($grid->getInfo('interface') != 'minimal') {
    $infos_lines[t("Mode")] = _kw_djambi_get_translatable_messages($grid->getMode());
  }
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
    $pheader['sides'] = array('data' => t('Sides'), 'scope' => 'col');
    $pheader['players'] = array('data' => t('Players'), 'scope' => 'col');
    if ($grid->getMode() == KW_DJAMBI_MODE_FRIENDLY) {
      $pheader['ip'] = array('data' => t('IP address'), 'scope' => 'col');
      $pheader['ping'] = array('data' => t('Ping'), 'scope' => 'col');
    }
    $pheader['status'] = array('data' => t('Status'), 'scope' => 'col');
    $prows = array();
    foreach ($grid->getFactions() as $faction) {
      $prow = array();
      $prow['sides'] = array('data' => '<span class="faction ' . $faction->getClass() . '">'
          . _kw_djambi_get_translatable_messages($faction->getName()) . '</span>');
      if (!in_array($faction->getStatus(), array(KW_DJAMBI_USER_VASSALIZED, KW_DJAMBI_USER_EMPTY_SLOT))) {
        $me = _kw_djambi_check_current_user($grid, $faction, FALSE);
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
              . t('controlled by !faction', array('!faction' => '<span class="faction ' . $faction->getControl()->getClass()
                  . '">' . _kw_djambi_get_translatable_messages($faction->getControl()->getName()) . '</span>')) : ''));
      $prows[] = array('data' => $prow, 'data-djuid' => $faction->getUserDataItem('djuid'),
            'class' => $me ? array('me') : array('not-me'));
    }
    $infos_lines[t('Players')] = theme('table', array('rows' => $prows, 'header' => $pheader,
        'attributes' => array('class' => array('players')), 'sticky' => FALSE));
  }
  $caption_title = _kw_djambi_get_translatable_messages($grid->getStatus());
  $turns = $grid->getTurns();
  if (!$show_changes && $grid->isPending()) {
    $current_turn = end($turns);
    $caption_title .= ' - <span class="turn">' . t("Turn #%turn", array("%turn" => $current_turn["turn"])) . '</span>';
  }
  else {
    $max_turn = end($turns);
    if ($grid->isPending()) {
      $caption_title .= ' <span class="warning">' . t("Replay mode : you are currently watching a past state ot the battlefield !") . '</span>';
    }
    $caption_title .= ' - <span class="turn">' . t("Turn #%displayed_turn out of #%max_turn",
        array("%displayed_turn" => $displayed_turn["turn"], '%max_turn' => $max_turn["turn"])) . '</span>';
    if (!empty($animated_moves)) {
      $playing_faction = $grid->getFactionById($displayed_turn['side']);
      $caption_title .= "<br /><span class='recap'>" . t("Showing !faction side move, began at %begin, duration : %duration", array(
        '!faction' => '<span class="faction ' . $playing_faction->getClass() . '">' . _kw_djambi_get_translatable_messages($playing_faction->getName()) . '</span>',
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
        "<span class='faction " . $now_playing_faction->getClass() . "'>"
        . _kw_djambi_get_translatable_messages($now_playing_faction->getName()) . "</span>"
    ));
    if ($grid->getStatus() != KW_DJAMBI_STATUS_DRAW_PROPOSAL) {
      $infos_lines[t("Playing next")] = t("!color1 side, then !color2 side, then !color3 side", array(
        "!color1" => "<span class='faction " . $playing_next_faction1->getClass() . "'>"
          . _kw_djambi_get_translatable_messages($playing_next_faction1->getName()) . "</span>",
        "!color2" => "<span class='faction " . $playing_next_faction2->getClass() . "'>"
          . _kw_djambi_get_translatable_messages($playing_next_faction2->getName()) . "</span>",
        "!color3" => "<span class='faction " . $playing_next_faction3->getClass() . "'>"
          . _kw_djambi_get_translatable_messages($playing_next_faction3->getName()) . "</span>"
      ));
      if (isset($form["textes"]["phase"])) {
        $infos_lines[t("Current phase")] = $form["textes"]["phase"]["#markup"];
        unset($form["textes"]["phase"]);
      }
      elseif (!$show_changes) {
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
        $faction_name = '<span class="faction ' . $playing_faction->getClass() . '">'
          . _kw_djambi_get_translatable_messages($playing_faction->getName()) . '</span>';
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
        if (!_kw_djambi_check_current_user($grid, $playing_faction)) {
          $infos_lines[t("Pending decision")] = _kw_djambi_waiting_label($now_playing_faction, $grid);
        }
      }
    }
  }
  if (!empty($infos_lines)) {
    _kw_djambi_add_info_lines($infos_lines, $form);
  }
  // Affichage du tableau
  $thrones = $grid->getSpecialCells('throne');
  if (!empty($thrones)) {
    foreach ($thrones as $key => $throne) {
      $rows[$cells[$throne]['x']]["data"][$cells[$throne]['y']]["title"] = t("Throne case !");
      $rows[$cells[$throne]['x']]["data"][$cells[$throne]['y']]["data"] .= "<div class='element-invisible'>" . t("Throne case !") . "</div>"
        . ($show_help && isset($helps['labyrinthe']) ? "<span class='help-link'>" . $helps['labyrinthe'] . "</span>" : "");
    }
  }
  $grid_classes = array('djambigrid', !$show_changes ? 'static' : 'animated');
  $grid_id = 'DjambiGrid' . $grid->getId();
  if ($grid->getScheme()->getDisposition() == 'hexagonal') {
    $markup = theme('hexagonal_grid', array(
      'rows' => $rows,
      'attributes' => array('class' => $grid_classes, 'id' => $grid_id),
      'caption' => $caption_title
    ));
  }
  else {
    $markup = theme('table', array(
      'header' => $header,
      'rows' => $rows,
      'attributes' => array('class' => $grid_classes, 'id' => $grid_id),
      'caption' => $caption_title,
      'sticky' => !$show_changes
    ));
  }
  $form['table'] = array(
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
      $form['recent_events'] = array(
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
  $form['#sorted'] = FALSE;
  $children = element_children($form, TRUE);
  $html = '';
  foreach ($children as $child) {
    $html .= drupal_render($form[$child]);
  }
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
      2 => array('data' => $ip),
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
  $infos_lines[t("Unbegun game")] = _kw_djambi_waiting_label(NULL, $grid);
  _kw_djambi_add_info_lines($infos_lines, $form);
  $form['players'] = array(
      '#markup' => theme('table', array('header' => $header, 'rows' => $rows,
          'caption' => $caption, 'attributes' => array('class' => array('players')))),
      '#weight' => 10
  );
  $form['#sorted'] = FALSE;
  $children = element_children($form, TRUE);
  $html = '';
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
    $html =str_replace(array_keys($buttons), $buttons, $html);
  }
  return $html;
}

/**
 * Affichage de lignes d'informations sur la partie en cours
 */
function _kw_djambi_add_info_lines($infos_lines, &$form) {
  if (!empty($infos_lines)) {
    $infos_items = array();
    foreach ($infos_lines as $legend => $line) {
      $infos_items[] = '<span class="pseudo-label">' . $legend . ' : </span><span class="value">' . $line . '</span>';
    }
    $form['infos'] = array(
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
      '!side' => _kw_djambi_get_translatable_messages($now_playing_faction->getName()),
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

/**
 * Chargement des contenus de type règle du jeu correspondant aux pièces présentes dans la partie
 * @param DjambiBattlefield $grid
 * @return array -> tableau de noeuds
 */
function _kw_djambi_load_rule_nodes(DjambiBattlefield $grid) {
  $nodes = array();
  if (!node_type_load('rule_element')) {
    return $nodes;
  }
  $scheme = $grid->getScheme();
  $types = array();
  foreach ($scheme->getPieceScheme() as $key => $piece) {
    $types[] = $piece->getRulePattern();
  }
  $pieces = $types;
  $types = array_unique($types);
  $types[] = 'cadavre';
  $types[] = 'labyrinthe';
  $raw_result = db_query('SELECT T.name, N.nid
      FROM {node} N
      INNER JOIN {field_data_field_game_concept} F on N.vid = F.revision_id
      INNER JOIN {taxonomy_term_data} T ON T.tid = F.field_game_concept_tid
      WHERE N.type = :type AND N.status = 1 AND T.name IN (:terms)',
      array(':type' => 'rule_element', ':terms' => $types));
  $with_transliteration = module_exists('transliteration');
  $with_lightbox = module_exists('ctools');
  if ($raw_result->rowCount() > 0) {
    if ($with_lightbox) {
      kw_tools_add_modal_settings();
    }
    foreach ($raw_result as $result) {
      if ($with_transliteration) {
        $type = transliteration_get($result->name);
      }
      else {
        $type = $result->name;
      }
      $type = strtolower($type);
      if (in_array($type, $pieces)) {
        $options = array(
          'attributes' => array('title' => t('Show help for !piece piece', array('!piece' => $result->name)))
        );
      }
      elseif ($type == 'cadavre') {
        $options = array('attributes' => array('title' => t('Show help for dead pieces')));
      }
      elseif ($type == 'labyrinthe') {
        $options = array('attributes' => array('title' => t('Show help for throne case')));
      }
      else {
        $options = array('attributes' => array('title' => $result->name));
      }
      if ($with_lightbox) {
        $nodes[$type] = ctools_modal_text_button(t('Help'),
            'kw/popup/nojs/' . $result->nid,
            $options['attributes']['title'],
            'ctools-modal-kweb-modal'
        );
      }
      else {
        $nodes[$type] = l(t('Help'), 'node/' . $result->nid, $options);
      }
    }
  }
  return $nodes;
}