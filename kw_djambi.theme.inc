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
    $turn = $grid->getCurrentTurnId() - $show_moves_settings['turn'];
    $i = 1;
    if (!empty($moves)) {
      $show_changes = TRUE;
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
  foreach ($grid->getCells() as $key => $cell) {
    $row_inner_html = "";
    $extra_classes = array('cell', $cell["type"],
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
  $fieldset_title = _kw_djambi_get_translatable_messages($grid->getStatus());
  $turn = end($grid->getTurns());
  $fieldset_title .= ' - <span class="turn">' . t("Turn #%turn", array("%turn" => $turn["turn"])) . '</span>';
  if (in_array($grid->getStatus(), array(KW_DJAMBI_STATUS_PENDING, KW_DJAMBI_STATUS_DRAW_PROPOSAL))) {
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
        $last_change = $grid->getInfo('changed');
        $infos_lines[t("Current phase")] = '<span class="refresh">'
          . t("Waiting for !side move for !duration...", array(
            '!side' => $now_playing_faction->getName(),
            '!duration' => '<span class="time-elapsed">' . kw_tools_duration(time() - $last_change) . '</span>'
        )) . '(' . t('Last update : !date', array('!date' => '<span class="time-last-update">' . format_date(time(), 'custom', 'H:i:s') . '</span>'))
        . '<span class="refresh-link"> - ' . l(t('Refresh'), 'node/' . $grid->getId()) . '</span>)</span>';
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
      }
    }
  }
  $c = 0;
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
  // Affichage du tableau
  $rows[5]["data"][5]["title"] = t("Throne case !");
  $rows[5]["data"][5]["data"] .= "<div class='element-invisible'>" . t("Throne case !") . "</div>";
  $form['grid']['table'] = array(
      '#markup' => theme("table", array(
          "header" => $header,
          "rows" => $rows,
          "attributes" => array("class" => array("djambigrid"), "id" => "DjambiGrid" . $grid->getId()),
          "caption" => $fieldset_title
      )),
      '#weight' => 0
  );
  // Affichage
  unset($form['pieces']);
  $html = drupal_render_children($form);
  return $html;
}