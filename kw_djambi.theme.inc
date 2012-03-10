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
  if (isset($form["pieces"])) {
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
    $extra_classes = array($cell["type"], "x" . $cell["x"], "y" . $cell["y"],
        isset($form_pieces["movable"]) && isset($cell["reachable"]) ? "reachable" : "unreachable");
    if ($has_destination_layer) {
      if (isset($form_pieces[$current_form_action]["cell-" . $key])) {
        $row_inner_html .= "<div class='destination-layer'>"
        . theme($form_pieces[$current_form_action]["cell-" . $key]["#type"], $form_pieces[$current_form_action]["cell-" . $key])
        . "</div>";
        unset($form["pieces"][$current_form_action]["cell-" . $key]);
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
      }
      if (isset($form_pieces[$current_form_action]) && isset($form_pieces[$current_form_action]["#selected_piece_id"]) && $piece->getId() == $form_pieces[$current_form_action]["#selected_piece_id"]) {
        $extra_classes[] = "with-selected-piece";
      }
      $row_inner_html .= "<div class='piece " . (!$piece->isAlive() ? "dead " : "alive ") . $piece->getType() . " " . $faction->getControl()->getClass()
      . (isset($form_pieces["movable"]) && $piece->isMovable() ? " movable" : " unmovable") . "' "
      . "title=\"" . ($piece->isAlive() ? (_kw_djambi_get_full_piece_name($piece) . ($piece->isMovable() ? " - "
          . t("this piece can be moved during this turn") : "")) : t("Here lies a dead piece")) . "\""
          . ">";
      if (!$piece->isAlive()) {
        $row_inner_html .= "<abbr title=\"" . t("Dead") . "\">†</abbr>";
      }
      elseif (!is_null($form_pieces) && isset($form_pieces["movable"]) && isset($form_pieces["movable"][$piece->getId()])) {
        $row_inner_html .= theme($form_pieces["movable"][$piece->getId()]["#type"],
            $form_pieces["movable"][$piece->getId()]);
        unset($form["pieces"]["movable"][$piece->getId()]);
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
    $rows[$cell["y"]]["data"][$cell["x"]] = array(
        "data" => $row_inner_html,
        "title" => t("!xy case", array("!xy" => $key))
        . ($current_form_action == "piece_selection" && isset($cell["reachable"]) ? " - " . t("a piece can move here...") : ""),
        "class" => $extra_classes
    );
  }
  // Création des légendes de la grille
  $caption = "";;
  $caption_lines[t("Mode")] = _kw_djambi_get_translatable_messages($grid->getMode());
  $fieldset_title = _kw_djambi_get_translatable_messages($grid->getStatus());
  $turn = end($grid->getTurns());
  $fieldset_title .= ' - <span class="turn">' . t("Turn #%turn", array("%turn" => $turn["turn"])) . '</span>';
  if ($grid->getStatus() == KW_DJAMBI_STATUS_PENDING) {
    $play_order = $grid->getPlayOrder();
    $current_play_order = current($play_order);
    $now_playing_faction = $grid->getFactionById($current_play_order["side"]);
    $next_play_order = next($play_order);
    $playing_next_faction1 = $grid->getFactionById($next_play_order["side"]);
    $next_play_order = next($play_order);
    $playing_next_faction2 = $grid->getFactionById($next_play_order["side"]);
    $next_play_order = next($play_order);
    $playing_next_faction3 = $grid->getFactionById($next_play_order["side"]);
    $caption_lines[t("Now playing")] = t("!color side", array("!color" =>
        "<span class='faction " . $now_playing_faction->getClass() . "'>" . $now_playing_faction->getName("t") . "</span>"
    ));
    $caption_lines[t("Playing next")] = t("!color1 side, then !color2 side, then !color3 side", array(
        "!color1" => "<span class='faction " . $playing_next_faction1->getClass() . "'>" . $playing_next_faction1->getName("t") . "</span>",
        "!color2" => "<span class='faction " . $playing_next_faction2->getClass() . "'>" . $playing_next_faction2->getName("t") . "</span>",
        "!color3" => "<span class='faction " . $playing_next_faction3->getClass() . "'>" . $playing_next_faction3->getName("t") . "</span>"
    ));
    if (isset($form["textes"]["phase"])) {
      $caption_lines[t("Current phase")] = $form["textes"]["phase"]["#value"];
      unset($form["textes"]["phase"]);
    }
    else {
      $caption_lines[t("Current phase")] = t("Waiting for an opponent move...");
    }
  }
  $c = 0;
  foreach ($caption_lines as $legend => $line) {
    if ($caption != "") {
      $caption .= "<span class='separateur'> / </span>";
    }
    $caption .= "<span class='caption-line' id='DjambiGrid" . $grid->getId() . "-caption" . $c++ . "'><span class='caption-label'>" . $legend . " : </span>"
    . "<span class='caption-value'>" . $line . "</span></span>";
  }
  // Affichage du tableau
  $rows[5]["data"][5]["title"] = t("Throne case !");
  $rows[5]["data"][5]["data"] .= "<div class='element-invisible'>" . t("Throne case !") . "</div>";
  $form['grid']['#title'] = $fieldset_title;
  $form['grid']['table'] = array(
      '#markup' => theme("table", array(
          "header" => $header,
          "rows" => $rows,
          "attributes" => array("class" => array("djambigrid"), "id" => "DjambiGrid" . $grid->getId()),
          "caption" => $caption
      ))
  );
  // Log des événements
  $log = array();
  foreach ($grid->getEvents() as $key => $event) {
    $args = array();
    if (is_array($event["args"])) {
      foreach ($event["args"] as $key => $arg) {
        if (substr($key, 0, 2) == "!!") {
          $arg = _kw_djambi_get_translatable_messages($arg);
        }
        $args[$key] = $arg;
      }
    }
    $msg = _kw_djambi_get_translatable_messages($event["event"], $args);
    $log["time"][] = $event["time"];
    $log["msg"][] = "<div class='event ". $event["type"] . "'><span class=''>" . format_date($event["time"], "short") . " :</span> " . $msg . "</div>";
  }
  foreach ($grid->getMoves() as $key => $move) {
    $msg = NULL;
    $piece = $grid->getPieceById($move["target"]);
    if ($move["type"] == "move") {
      $faction = $grid->getFactionById($move["target_faction"]);
      $msg = t("!faction has moved !piece from !origin to !destination.", array(
          "!faction" => "<span class='faction " . $faction->getClass() . "'>" . $faction->getName("t") . "</span>",
          "!piece" =>  "<span class='piece " . $piece->getType() . "'>" . _kw_djambi_get_full_piece_name($piece) . "</span>",
          "!origin" => $move["from"],
          "!destination" => $move["to"]
      ));
    }
    elseif ($move["type"] == "murder") {
      $victim = $piece;
      $responsable = $grid->getPieceById($move["acting"]);
      $msg = t("!victim has been killed by !piece. The crime took place in !origin, and the victim body was found in !destination.", array(
          "!victim" => "<span class='piece " . $victim->getType() . "'>" . _kw_djambi_get_full_piece_name($victim) . "</span>",
          "!piece" =>  "<span class='piece " . $responsable->getType() . "'>" . _kw_djambi_get_full_piece_name($responsable) . "</span>",
          "!origin" => $move["from"],
          "!destination" => $move["to"]
      ));
    }
    elseif ($move["type"] == "necromobility") {
      $responsable = $grid->getPieceById($move["acting"]);
      $msg = t("!piece has exhumed a dead body from !origin. Its new burial place is located in !destination.", array(
          "!piece" =>  "<span class='piece " . $responsable->getType() . "'>" . _kw_djambi_get_full_piece_name($responsable) . "</span>",
          "!origin" => $move["from"],
          "!destination" => $move["to"]
      ));
    }
    elseif ($move["type"] == "manipulation") {
      $victim = $piece;
      $responsable = $grid->getPieceById($move["acting"]);
      $msg = t("!victim has been manipulated by !piece from !origin to !destination.", array(
          "!victim" => "<span class='piece " . $victim->getType() . "'>" . _kw_djambi_get_full_piece_name($victim) . "</span>",
          "!piece" =>  "<span class='piece " . $responsable->getType() . "'>" . _kw_djambi_get_full_piece_name($responsable) . "</span>",
          "!origin" => $move["from"],
          "!destination" => $move["to"]
      ));
    }
    if (!empty($msg)) {
      $log["time"][] = $move["time"];
      $log["msg"][] = "<div class='" . $move["type"] . "'><span class=''>" . format_date($move["time"], "short") . " :</span>  " . $msg . "</div>";
    }
    if (!empty($move["special_event"])) {
      $msg2 = _kw_djambi_get_translatable_messages($move["special_event"],
          array("!piece" => _kw_djambi_get_full_piece_name($piece)));
      $log["time"][] = $move["time"];
      $log["msg"][] = "<div class='event'> " . $msg2 . "</div>";
    }
  }
  array_multisort($log["time"], SORT_DESC, $log["msg"]);
  $form['log']['history'] = array(
      '#markup' => theme("item_list", array("items" => $log["msg"]))
  );
  // Affichage
  $html = drupal_render_children($form);
  return $html;
}