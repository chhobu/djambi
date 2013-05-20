<?php
function _kw_djambi_build_game_form(&$form, &$form_state, DjambiBattlefield $grid) {
  global $user;
  $form['#grid'] = $grid;
  $current_user_faction = _kw_djambi_get_user_faction($grid, $user);
  if (!$grid->isFinished() && !is_null($current_user_faction) && $grid->getMode() != KW_DJAMBI_MODE_SANDBOX) {
    $user_data = _kw_djambi_update_user_infos($current_user_faction->getUserData(),
      $current_user_faction->getUserDataItem('djuid'));
    $current_user_faction->updateUserData($user_data);
  }
  $faction = $grid->getPlayingFaction();
  // Récapitulatif de la partie...
  if (isset($form_state['show_replay'])) {
    $grid->viewTurnHistory($form_state['show_replay']);
    $replay = TRUE;
  }
  else {
    $replay = FALSE;
  }
  // Inclusion du Javascript et du CSS
  if ($grid->isPending() && _kw_djambi_check_current_user($faction)) {
    drupal_add_library('system', 'ui.core');
    drupal_add_library('system', 'ui.widget');
    drupal_add_library('system', 'ui.mouse');
    drupal_add_library('system', 'ui.draggable');
    drupal_add_library('system', 'ui.droppable');
  }
  $form['#attached']['css'][] = drupal_get_path('module', 'kw_djambi').'/kw_djambi.css';
  $form['#attached']['js'][] = drupal_get_path('module', 'kw_djambi').'/js/kw_djambi.js';
  $ajax_settings = array(
      'callback' => 'kw_djambi_ajax_callback',
      'wrapper' => 'DjambiContainer' . $grid->getId(),
      'effect' => 'none',
      'method' => 'replace',
  );
  // Création de l'ossature du formulaire
  if (!$grid->isNotBegin()) {
    $form['#theme'] = 'djambi_grid';
  }
  else {
    $form['#theme'] = 'djambi_recruiting_phase';
  }
  $do_refresh = 'no';
  $form['grid'] = array(
      '#type' => 'fieldset',
      '#title' => t('Djambi grid #!number', array('!number' => $grid->getInfo('sequence'))),
      '#weight' => 0,
  );
  if (!$grid->isFinished() && !$replay && ($grid->getMode() != KW_DJAMBI_MODE_SANDBOX || is_null($current_user_faction))) {
      $do_refresh = 'yes';
      $form['grid']['controls']['refresh'] = array(
          '#type' => 'submit',
          '#attributes' => array('class' => array('refresh-button')),
          '#value' => t('Refresh'),
          '#ajax' => $ajax_settings,
          '#weight' => 99,
          '#submit' => array('kw_djambi_game_form_refresh_submit'),
          '#name' => 'refresh'
      );
  }
  $attributes = array(
    'id' => 'DjambiContainer' . $grid->getId(),
    'class' => array('djambi'),
    'data-grid' => $grid->getId(),
    'data-version' => $grid->getInfo('changed'),
    'data-status' => $grid->getStatus(),
    'data-refresh' => $do_refresh,
    'data-user-faction' => (!is_null($current_user_faction) ? $current_user_faction->getId() : '0'),
    'data-user-status' => (!is_null($current_user_faction) ? $current_user_faction->getStatus() : '0'),
    'data-turn' => $grid->getCurrentTurnId()
  );
  if (isset($form_state['autoplay']) && $form_state['autoplay']) {
    $attributes['class'][] = 'autoplay';
  }
  $form['#prefix'] = '<div ' . drupal_attributes($attributes) . '>';
  $form['#suffix'] = '</div>';
  $form['options'] = array(
      '#type' => 'fieldset',
      '#title' => t('Game configuration'),
      '#weight' => 4,
      '#collapsible' => TRUE,
      '#collapsed' => $grid->isNotBegin() ? FALSE : TRUE,
  );
  if (!$grid->isNotBegin()) {
    $form['stats'] = array(
        '#type' => 'fieldset',
        '#title' => $grid->getStatus() == KW_DJAMBI_STATUS_FINISHED ? t('Stats and awards') : t('Stats'),
        '#weight' => 5
    );
  }
  $form['log'] = array(
      '#type' => 'fieldset',
      '#title' => t('Treachery log'),
      '#weight' => 10,
      '#collapsible' => !$grid->isPending(),
      '#collapsed' => !$grid->isNotBegin()
  );
  // Recrutement des joueurs...
  if ($grid->isNotBegin()) {
    _kw_djambi_build_part_recruiting($form, $grid, $current_user_faction, $ajax_settings);
  }
  // Contrôle de l'affichage de l'aide et des derniers mouvements
  _kw_djambi_build_part_move_controls($form, $form_state, $grid, $faction, $ajax_settings);
  // Affichage du récapitulatif des règles
  _kw_djambi_build_part_rules($form, $grid);
  if (!isset($form_state['show_replay'])) {
    // Interface de réponse sur proposition de partie nulle
    if ($faction && $grid->getStatus() == KW_DJAMBI_STATUS_DRAW_PROPOSAL) {
      _kw_djambi_build_part_draw_proposal($form, $grid, $faction, $ajax_settings);
    }
    elseif ($faction && $grid->getStatus() == KW_DJAMBI_STATUS_PENDING && _kw_djambi_check_current_user($faction)) {
      _kw_djambi_build_part_player_actions($form, $form_state, $grid, $faction, $ajax_settings);
      _kw_djambi_build_part_pieces_controls($form, $form_state, $grid, $faction, $ajax_settings);
    }
  }
  // Log des événements
  if (!empty($form['log'])) {
    _kw_djambi_build_part_log($form, $form_state, $grid);
  }
  // Afichage des stats
  if (!empty($form['stats'])) {
    _kw_djambi_build_part_stats($form, $grid);
  }
  // Ajout des conteneurs si besoin
  if (isset($form['grid']['controls'])) {
    $form['grid']['controls']['#type'] = 'container';
    $form['grid']['controls']['#attributes'] = array('class' => array('controls'));
    $form['grid']['controls']['#weight'] = 10;
  }
  // Redirection
  $form["#action"] = "#DjambiGrid" . $grid->getId();
}

function _kw_djambi_build_part_recruiting(&$form, DjambiBattlefield $grid, $current_user_faction, $ajax_settings) {
  global $user;
  $allow_anonymous = $grid->getOption('allow_anonymous_players');
  $reject = FALSE;
  $form['grid']['factions'] = array(
      '#type' => 'container'
  );
  foreach ($grid->getFactions() as $faction) {
    if ($faction->getStatus() == KW_DJAMBI_USER_VASSALIZED) {
      continue;
    }
    $form['grid']['factions'][$faction->getId()]['label'] = array(
        '#type' => 'item',
        '#title' => '<span class="faction ' . $faction->getClass() . '">'
        . _kw_djambi_get_translatable_messages($faction->getName()) . '</span>',
        '#markup' => $faction->getStatus() == KW_DJAMBI_USER_EMPTY_SLOT ?
        t('Empty') : theme('username', array('account' => user_load($faction->getUserDataItem('uid'))))
    );
    if ($faction->getStatus() != KW_DJAMBI_USER_EMPTY_SLOT) {
      $form['grid']['factions'][$faction->getId()]['infos'] = array(
          '#type' => 'value',
          '#value' => array(
              'ip' => $faction->getUserDataItem('ip'),
              'ping' => $faction->getUserDataItem('ping'),
              'joined' => $faction->getUserDataItem('joined'),
              'djuid' => $faction->getUserDataItem('djuid'),
              'me' => !is_null($current_user_faction) && $current_user_faction->getId() == $faction->getId() ? TRUE : FALSE
          )
      );
    }
    if (!is_null($current_user_faction) && $faction->getId() == $current_user_faction->getId()) {
      $form['grid']['factions'][$faction->getId()]['action'] = array(
          '#type' => 'submit',
          '#name' => 'action',
          '#id' => 'action-leave-game',
          '#value' => t('Leave game'),
          '#submit' => array('kw_djambi_game_form_cancel_participation_submit'),
      );
    }
    elseif ($faction->getStatus() == KW_DJAMBI_USER_EMPTY_SLOT) {
      if ($allow_anonymous || $user->uid > 0) {
        $form['grid']['factions'][$faction->getId()]['action'] = array(
          '#type' => 'submit',
          '#name' => 'action-play-' . $faction->getId(),
          '#id' => 'action-play-' . $faction->getId(),
          '#value' => !is_null($current_user_faction) ? t('Play this side') : t('Join this game'),
          '#submit' => array('kw_djambi_game_form_participate_submit'),
          '#ajax' => $ajax_settings
        );
      }
      else {
        $reject = TRUE;
      }
    }
  }
  if ($reject) {
    $form['grid']['reject'] = array(
      '#markup' => "<div class='messages warning'>" . t('Only registered players can join this game. Please !login or !register.', array(
          '!login' => l(t('login to your account'), 'user/login'),
          '!register' => l(t('create a new account'), 'user/register')
       )) . "</div>"
    );
  }
}

function _kw_djambi_build_part_move_controls(&$form, &$form_state, DjambiBattlefield $grid, $faction, $ajax_settings) {
  global $user;
  $current_turn_id = $grid->getCurrentTurnId();
  $show_moves = $grid->isPending();
  if (isset($form_state['show_moves'])) {
    $show_moves = $form_state['show_moves'];
  }
  elseif ($user->uid > 0 && isset($user->data['djambi_show_moves'])) {
    $show_moves = $user->data['djambi_show_moves'];
  }
  elseif (isset($_SESSION['djambi']['show_moves'])) {
    $show_moves = $_SESSION['djambi']['show_moves'];
  }
  $last_move = 0;
  if (!$grid->isFinished()) {
    $turns = $grid->getTurns();
    $showable_turns = array();
    $faction_turns = array();
    foreach ($turns as $turn_key => $turn) {
      if (!empty($turn['end']) && ($turn['turn'] == $turns[$current_turn_id]['turn'] || (
          $turn['turn'] == $turns[$current_turn_id]['turn'] - 1 && $turn['turn_scheme'] >= $turns[$current_turn_id]['turn_scheme']))) {
        $faction_turns[$turn['side']][$turn_key] = $turn_key;
      }
    }
    if (!empty($faction_turns)) {
      foreach ($faction_turns as $faction_key => $turn) {
        $factions = count($faction_turns);
        if (count($turn) > $factions) {
          $turn = array_slice($turn, -$factions, $factions, TRUE);
        }
        $showable_turns += $turn;
      }
      asort($showable_turns);
      $last_move = min($showable_turns);
    }
    $form['showable_turns'] = array(
        '#type' => 'value',
        '#value' => $showable_turns
    );
  }
  $ui_buttons = array(
      '#type' => 'submit',
      '#name' => 'ui',
      '#limit_validation_errors' => array(array('showable_turns')),
      '#ajax' => $ajax_settings
  );
  if (!isset($form_state['show_replay'])) {
    if ($grid->isFinished()) {
      $form['grid']['controls']['replay'] = array_merge($ui_buttons, array(
          '#id' => 'ui-replay',
          '#value' => '<< ' . t('Review this game'),
          '#submit' => array('kw_djambi_game_form_replay_submit'),
      ));
    }
    $form['grid']['controls']['previous_turn'] = array_merge($ui_buttons, array(
       '#id' => 'ui-replay-backward',
       '#value' => '< ' . ($grid->isFinished() ? t('Show previous move') : t('Show previous moves')),
       '#submit' => array('kw_djambi_game_form_replay_submit'),
       '#disabled' => $current_turn_id > 0 ? FALSE : TRUE
    ));
  }
  else {
    if ($grid->isFinished()) {
      $form['grid']['controls']['first_turn'] = array_merge($ui_buttons, array(
        '#id' => 'ui-replay-begin',
        '#value' => '<< ' . t('Go back to the first move'),
        '#submit' => array('kw_djambi_game_form_replay_submit'),
        '#disabled' => $form_state['show_replay'] > 0 ? FALSE : TRUE
      ));
    }
    $form['grid']['controls']['previous_turn'] = array_merge($ui_buttons, array(
        '#id' => 'ui-replay-backward',
        '#value' => '< ' . t('Show previous move'),
        '#submit' => array('kw_djambi_game_form_replay_submit'),
        '#disabled' => $form_state['show_replay'] > $last_move ? FALSE : TRUE
    ));
    $form['grid']['controls']['pause'] = array_merge($ui_buttons, array(
        '#id' => 'ui-replay-pause',
        '#value' =>  '|| ' . t('Pause'),
        '#submit' => array('kw_djambi_game_form_replay_submit'),
        '#attributes' => array('style' => 'display:none')
    ));
    $form['grid']['controls']['autoplay'] = array_merge($ui_buttons, array(
        '#id' => 'ui-replay-autoplay',
        '#value' => '|> ' . t('Auto-play'),
        '#submit' => array('kw_djambi_game_form_replay_submit'),
        '#attributes' => array('style' => 'display:none')
    ));
    $form['grid']['controls']['next_turn'] = array_merge($ui_buttons, array(
        '#id' => 'ui-replay-forward',
        '#value' => t('Show next move') . ' >',
        '#submit' => array('kw_djambi_game_form_replay_submit'),
        '#disabled' => (($grid->isPending() && $current_turn_id - 1 <= $form_state['show_replay']) ||
            ($grid->isFinished() && $current_turn_id <= $form_state['show_replay']) ? TRUE : FALSE)
    ));
    $form['grid']['controls']['last_turn'] = array_merge($ui_buttons, array(
        '#id' => 'ui-replay-end',
        '#value' => $grid->isPending() ? t('Go back to current turn') . ' >>' : t('Go back to final positions') . ' >>',
        '#submit' => array('kw_djambi_game_form_replay_submit'),
    ));
  }
  if ($grid->isPending() || isset($form_state['show_replay'])) {
    $form['grid']['controls']['show_moves_button'] = array(
        '#type' => 'submit',
        '#name' => 'ui',
        '#id' => 'ui-show-move',
        '#value' => $show_moves ? '- ' . t('Hide last moves descriptions') : '+ ' . t('Show last moves descriptions'),
        '#limit_validation_errors' => array(array('show_moves')),
        '#submit' => array('kw_djambi_game_form_show_moves_submit'),
        '#ajax' => $ajax_settings
    );
  }
  if ($grid->isPending() && !isset($form_state['show_replay'])) {
    $form['grid']['controls']['show_help_button'] = array(
        '#type' => 'submit',
        '#name' => 'ui',
        '#id' => 'ui-show-help',
        '#value' => '(?) ' . (!empty($form_state['show_help']) ? t('Hide help') : t('Show help')),
        '#limit_validation_errors' => array(),
        '#submit' => array('kw_djambi_game_form_show_help_submit'),
        '#ajax' => $ajax_settings
    );
  }
  $form['show_help'] = array(
      '#type' => 'value',
      '#value' => !empty($form_state['show_help']) ? TRUE : FALSE
  );
  $form['show_replay'] = array(
      '#type' => 'value',
      '#value' => !empty($form_state['show_replay']) ? $form_state['show_replay'] : FALSE
  );
  $form['show_moves'] = array(
      '#type' => 'value',
      '#value' => !empty($form_state['show_moves']) ? $form_state['show_moves'] : $show_moves
  );
}

function _kw_djambi_build_part_rules(&$form, DjambiBattlefield $grid) {
  $options = DjambiBattlefield::getOptionsInfo();
  $rules_rows = array();
  $nb_rule = $nb_option = 1;
  foreach ($options as $key => $option) {
    if ($option['type'] == 'rule_variant' || $option['type'] == 'game_option') {
      if (isset($option['modes']) && !in_array($grid->getMode(), $option['modes'])) {
        continue;
      }
      $value = $grid->getOption($key);
      if (!empty($option['choices'][$value])) {
        if ($option['type'] == 'rule_variant') {
          $title = t('Rule variant #!number', array('!number' => str_pad($nb_rule++, 2, '0', STR_PAD_LEFT)));
        }
        elseif ($option['type'] == 'game_option') {
          $title = t('Game option #!number', array('!number' => str_pad($nb_option++, 2, '0', STR_PAD_LEFT)));
        }
        else {
          $title = $option['type'];
        }
        $rules_rows[] = array(
            array('data' =>
                '<span class="' . str_replace(array('_', ' '), '-', $option['type']) . '">'
                . $title . '</span> - <strong>'
                . (!empty($option['title']) ? _kw_djambi_get_translatable_messages($option['title']) : NULL)
                . '</strong>'),
            array('class' => array('description'), 'data' => _kw_djambi_get_translatable_messages($option['choices'][$value], array('!value' => $value)))
        );
      }
    }
  }
  if (!empty($rules_rows)) {
    $rules_header = array(
        array('data' => 'Label'),
        array('data' => 'Value')
    );
    $form['options']['table'] = array('#markup' => theme('table', array('rows' => $rules_rows, 'header' => $rules_header)));
  }
}

function _kw_djambi_build_part_draw_proposal(&$form, DjambiBattlefield $grid, DjambiPoliticalFaction $faction, $ajax_settings) {
  $form['actions'] = array(
      '#type' => 'fieldset',
      '#title' => t('Actions'),
      '#weight' => 1
  );
  $factions = $grid->getFactions();
  foreach ($factions as $side) {
    if ($side->getDrawStatus() == 1) {
      $peacemonger = $side;
      break;
    }
  }
  if (_kw_djambi_check_current_user($faction)) {
    $form['actions']['descriptions'] = array(
        '#type' => 'item',
        '#title' => t('Now playing') . ' : ',
        '#prefix' => '<div class="container-inline">',
        '#suffix' => '</div>',
        '#markup' => t('!faction side', array('!faction' =>
            "<span class='faction " . $faction->getClass() . "'>"
            . _kw_djambi_get_translatable_messages($faction->getName()) . "</span>")),
        '#weight' => -10,
    );
    $form['actions']['explain'] = array(
        '#type' => 'markup',
        '#markup' => t("A peace proposal has been made by !faction side. What is your answer ?",
            array('!faction' => "<span class='faction " . $peacemonger->getClass() . "'>"
                . _kw_djambi_get_translatable_messages($peacemonger->getName()) . "</span>"))
    );
    $form['actions']['accept_peace'] = array(
        '#type' => 'submit',
        '#name' => 'action',
        '#id' => 'action-accept-peace',
        '#value' => t("OK, end this game with a draw and stay good friends."),
        '#submit' => array('kw_djambi_game_form_accept_peace_submit'),
        '#ajax' => $ajax_settings
    );
    $form['actions']['reject_peace'] = array(
        '#type' => 'submit',
        '#name' => 'action',
        '#id' => 'action-reject-peace',
        '#value' => t("No way, I am sure to win this game."),
        '#submit' => array('kw_djambi_game_form_reject_peace_submit'),
        '#ajax' => $ajax_settings
    );
  }
  else {
    $form['actions']['explain'] = array(
        '#type' => 'markup',
        '#markup' => t("A peace proposal has been made by !faction side. Waiting for !current side answer...",
            array('!faction' => _kw_djambi_get_translatable_messages($peacemonger->getName()),
                '!current' => _kw_djambi_get_translatable_messages($faction->getName())))
    );
  }
}

function _kw_djambi_build_part_player_actions(&$form, &$form_state, DjambiBattlefield $grid, DjambiPoliticalFaction $faction, $ajax_settings) {
  $actions = array();
  $descriptions = array();
  // Annulation de sélection d'une pièce
  if (!empty($form_state["kw_djambi"]) && in_array($form_state["kw_djambi"]["phase"], array('move_interactions', 'piece_destination'))) {
    $actions['cancel_selection'] = array(
        '#type' => 'submit',
        '#name' => 'action',
        '#id' => 'action-cancel-selection',
        '#value' => t('Cancel piece selection'),
        '#submit' => array('kw_djambi_game_form_cancel_selection_submit'),
        '#ajax' => $ajax_settings
    );
  }
  // Annulation d'un mouvement
  elseif ($grid->getMode() == KW_DJAMBI_MODE_SANDBOX && count($grid->getTurns()) > 1) {
    $actions['cancel_last_turn'] = array(
        '#type' => 'submit',
        '#name' => 'action',
        '#id' => 'action-cancel-move',
        '#value' => t('Cancel last move'),
        '#submit' => array('kw_djambi_game_form_cancel_last_turn_submit'),
        '#ajax' => $ajax_settings
    );
  }
  // Passer son tour
  $allowed_skip_turns = $grid->getOption('allowed_skipped_turns_per_user');
  if ($allowed_skip_turns == -1 || $allowed_skip_turns > $faction->getSkippedTurns()) {
    if (empty($form_state['kw_djambi']['selected_piece'])) {
      $actions['skip_turn'] = array(
          '#type' => 'submit',
          '#name' => 'action',
          '#id' => 'action-skip-turn',
          '#value' => $allowed_skip_turns == -1 ? t('Skip turn') : t('Skip turn (!nb of !max already skipped)', array(
              '!nb' => $faction->getSkippedTurns(), '!max' => $allowed_skip_turns)),
          '#submit' => array('kw_djambi_game_form_skip_turn_submit'),
          '#ajax' => $ajax_settings
      );
    }
    if ($allowed_skip_turns == -1) {
      $descriptions[] = t('There is no limitation concerning turn skippings.');
    }
    else {
      $descriptions[] = format_plural($allowed_skip_turns, 'You can only skip your turn 1 time.',
          'You can skip your turn only @count times');
    }
  }
  // Proposer une nulle
  $draw_proposal_delay = $grid->getOption('turns_before_draw_proposal');
  $turns = $grid->getTurns();
  if ($draw_proposal_delay != -1 && $faction->getLastDrawProposal() + $draw_proposal_delay <= $turns[$grid->getCurrentTurnId()]['turn']) {
    $actions['draw_proposal'] = array(
        '#type' => 'submit',
        '#name' => 'action',
        '#id' => 'action-ask-draw',
        '#value' => t('Ask for a draw'),
        '#submit' => array('kw_djambi_game_form_draw_proposal_submit'),
        '#ajax' => $ajax_settings
    );
    if ($draw_proposal_delay > 1) {
      $descriptions[] = t('You can ask for a draw every !nb turns', array('!nb' => $draw_proposal_delay));
    }
    $descriptions[] = t('Asking for a draw will end your turn without making a piece move.');
  }
  elseif ($draw_proposal_delay == -1) {
    $descriptions[] = t('No mercy game : draw proposals are forbidden.');
  }
  else {
    $descriptions[] = t('You cannot ask for a draw before turn !turn.',
        array('!turn' => ($faction->getLastDrawProposal() + $draw_proposal_delay)));
  }
  // Abandonner
  $actions['withdrawal'] = array(
      '#type' => 'submit',
      '#name' => 'action',
      '#id' => 'action-withdraw',
      '#value' => t('Withdraw'),
      '#submit' => array('kw_djambi_game_form_withdraw_submit'),
      '#ajax' => $ajax_settings
  );
  // Retour après abandon
  if ($grid->getOption('rule_comeback') == 'allowed' && $grid->getMode() == KW_DJAMBI_MODE_SANDBOX) {
    foreach ($grid->getFactions() as $withdrawn_faction) {
      if ($withdrawn_faction->canComeBackAfterWithdraw()) {
        $actions['comeback']['comeback_' . $withdrawn_faction->getId()] = array(
            '#type' => 'submit',
            '#name' => 'action',
            '#id' => 'action-allow-comeback' . $withdrawn_faction->getId(),
            '#value' => t('Allow !faction side to come back in the game', array(
                '!faction' => _kw_djambi_get_translatable_messages($withdrawn_faction->getName()))),
            '#submit' => array('kw_djambi_game_form_comeback_submit'),
            '#ajax' => $ajax_settings,
            '#weight' => 10
        );
      }
    }
    if (isset($actions['comeback'])) {
      $actions['comeback']['separator'] = array(
          '#markup' => '<hr />'
      );
    }
  }
  // Regroupement des actions dans un fieldset
  if (!empty($actions) || !empty($descriptions)) {
    $form['grid']['actions'] = array(
        '#type' => 'fieldset',
        '#title' => t('Actions'),
        '#weight' => 20
    );
    foreach ($actions as $key => $value) {
      $form['grid']['actions'][$key] = $value;
    }
    if (!empty($descriptions)) {
      $form['grid']['actions']['descriptions'] = array(
          '#type' => 'item',
          '#title' => t('Now playing') . ' : ',
          '#prefix' => '<div class="container-inline">',
          '#suffix' => '</div>',
          '#markup' => t('!faction side', array('!faction' =>
              "<span class='faction " . $faction->getClass() . "'>"
              . _kw_djambi_get_translatable_messages($faction->getName()) . "</span>")),
          '#weight' => -10,
          '#description' => theme('item_list', array('items' => $descriptions))
      );
    }
  }
}

function _kw_djambi_build_part_pieces_controls(&$form, &$form_state, DjambiBattlefield $grid, DjambiPoliticalFaction $faction, $ajax_settings) {
  $phase_text = NULL;
  // Par défaut : phase de sélection des pièces
  if (!isset($form_state['kw_djambi'])) {
    $form_state['kw_djambi'] = array(
        'phase' => 'piece_selection'
    );
  }
  // Sélection des pièces
  $is_current_user = _kw_djambi_check_current_user($faction);
  if ($is_current_user) {
    $piece_selection_phases = array('piece_selection', 'piece_destination');
    if ($is_current_user && in_array($form_state['kw_djambi']['phase'], $piece_selection_phases)) {
      /* @var $piece DjambiPiece */
      foreach($faction->getControlledPieces() as $key => $piece) {
        if ($piece->isMovable()) {
          $form['pieces']['movable'][$piece->getId()] = array(
              '#type' => 'image_button',
              '#return_value' => $piece->getId(),
              '#src' => $piece->getImage(),
              '#name' => 'selection-' .$piece->getId(),
              '#id' => 'selection-' .$piece->getId(),
              '#attributes' => array(
                  'alt' => t('Move !piece', array('!piece' => $piece->getShortname('t'))),
                  'data-grid-button' => 'selection'
              ),
              '#ajax' => $ajax_settings
          );
        }
      }
      $form['piece_destination'] = array(
          '#type' => 'hidden',
          '#value' => ''
      );
    }
    // Destination des pièces
    if ($form_state['kw_djambi']['phase'] == 'piece_destination') {
      /* @var $selected_piece DjambiPiece */
      $selected_piece = $form_state['kw_djambi']['selected_piece'];
      $form['pieces']['destination']['#selected_piece_id'] = $selected_piece->getId();
      $reachable_cells = $selected_piece->getAllowableMoves();
      foreach ($reachable_cells as $key => $cell_key) {
        $alt_text = t('Move the !piece here (!case).', array(
            '!piece' => _kw_djambi_get_full_piece_name($selected_piece), '!case' => $cell_key
        ));
        $form['pieces']['destination']['cell-' . $cell_key] = array(
            '#type' => 'image_button',
            '#name' => 'target-' . $cell_key,
            '#id' => 'target-' . $cell_key,
            '#return_value' => $cell_key,
            '#src' => base_path() . drupal_get_path('module', 'kw_djambi') . '/img/apply.png',
            '#attributes' => array(
                'alt' => $alt_text,
                'title' => $alt_text,
                'data-grid-button' => 'destination'
             ),
            '#ajax' => $ajax_settings
        );
      }
      $form["pieces"]["movable"][$selected_piece->getId()]["#attributes"]["class"][] = "selected";
      $phase_text = t("The !piece is selected. Choose now its destination case, or select another piece to move.",
          array("!piece" => "<span class='piece " . $selected_piece->getType() . "'>" . _kw_djambi_get_full_piece_name($selected_piece) . "</span>")
      );
    }
    // Résultat du déplacement
    elseif ($form_state['kw_djambi']['phase'] == 'move_interactions') {
      $current_interaction = current($form_state['kw_djambi']['interactions']);
      $selected_piece = $form_state['kw_djambi']['selected_piece'];
      // Cas 1 : placement d'une pièce tuée
      if ($current_interaction['type'] == 'murder') {
        $form['pieces']['murder']['#selected_piece_id'] = $selected_piece->getId();
        $victim_piece = $current_interaction['target'];
        $free_cells = $grid->getFreeCells($victim_piece, FALSE, TRUE);
        foreach ($free_cells as $cell) {
          $alt_text = t('Bury !piece in !case', array(
              '!piece' => _kw_djambi_get_full_piece_name($victim_piece), '!case' => $cell
          ));
          $form['pieces']['murder']['cell-' . $cell] = array(
              '#type' => 'image_button',
              '#name' => 'target-' . $cell,
              '#id' => 'target-' . $cell,
              '#return_value' => $cell,
              '#src' => base_path() . drupal_get_path('module', 'kw_djambi') . '/img/flag_black.png',
              '#attributes' => array(
                  'alt' => $alt_text,
                  'title' => $alt_text,
                  'data-grid-button' => 'interaction'
              ),
              '#ajax' => $ajax_settings
          );
        }
        $phase_text = t('Your !piece has killed the !victim, select now the case where you victim will rest in peace.',
            array(
                '!piece' => '<span class="piece ' . $selected_piece->getType() . '">' . _kw_djambi_get_full_piece_name($selected_piece) . '</span>',
                '!victim' => '<span class="piece ' . $victim_piece->getType() . '">' . _kw_djambi_get_full_piece_name($victim_piece) . '</span>'
            ));
      }
      // Cas 2 : placement d'une pièce tuée déplacée
      elseif ($current_interaction["type"] == "necromobility") {
        $form["pieces"]["necromobility"]["#selected_piece_id"] = $selected_piece->getId();
        $victim_piece = $current_interaction["target"];
        $free_cells = $grid->getFreeCells($victim_piece);
        foreach ($free_cells as $cell) {
          $alt_text = t("Move the dead piece to case !case.", array("!case" => $cell));
          $form["pieces"]["necromobility"]["cell-" . $cell] = array(
              "#type" => "image_button",
              '#name' => 'target-' . $cell,
              '#id' => 'target-' . $cell,
              "#return_value" => $cell,
              "#src" => base_path() . drupal_get_path("module", "kw_djambi") . "/img/flag_black.png",
              '#attributes' => array(
                  'alt' => $alt_text,
                  'title' => $alt_text,
                  'data-grid-button' => 'interaction'
              ),
              '#ajax' => $ajax_settings
          );
        }
        $phase_text = t("Your !piece has exhumed an old dead piece, select now its new burial place.", array(
            "!piece" => "<span class='piece " . $selected_piece->getType() . "'>" . _kw_djambi_get_full_piece_name($selected_piece) . "</span>"
        ));
      }
      // Cas 3 : placement d'une pièce manipulée
      elseif ($current_interaction["type"] == "manipulation") {
        $manipulated_piece = $current_interaction["target"];
        $form["pieces"]["manipulation"]["#selected_piece_id"] = $selected_piece->getId();
        $form["pieces"]["manipulation"]["#target_piece_id"] = $manipulated_piece->getId();
        $free_cells = $grid->getFreeCells($manipulated_piece);
        foreach ($free_cells as $cell) {
          $alt_text = t("Move manipulated !piece to !case", array(
              "!piece" => _kw_djambi_get_full_piece_name($manipulated_piece), "!case" => $cell
          ));
          $form["pieces"]["manipulation"]["cell-" . $cell] = array(
              "#type" => "image_button",
              '#name' => 'target-' . $cell,
              '#id' => 'target-' . $cell,
              "#return_value" => $cell,
              "#src" => base_path() . drupal_get_path("module", "kw_djambi") . "/img/note2.png",
              '#attributes' => array(
                  'alt' => $alt_text,
                  'title' => $alt_text,
                  'data-grid-button' => 'interaction'
              ),
              '#ajax' => $ajax_settings
          );
        }
        $phase_text = t("Your !diplomat is manipulating the !piece, select now the destination case for your subjucated victim.",
            array(
                "!diplomat" => "<span class='piece " . $selected_piece->getType() . "'>" . _kw_djambi_get_full_piece_name($selected_piece) . "</span>",
                "!piece" => "<span class='piece " . $manipulated_piece->getType() . "'>" . _kw_djambi_get_full_piece_name($manipulated_piece) . "</span>"
            ));
      }
      // Cas 4 : sélection de la victime d'un reportage
      elseif ($current_interaction["type"] == "reportage") {
        $form["pieces"]["reportage"]["#selected_piece_id"] = $selected_piece->getId();
        foreach ($current_interaction["victims"] as $victim) {
          $position = DjambiBattlefield::locateCell($victim->getPosition());
          $alt_text = t("Lauch an inquiry on !piece", array(
              "!piece" => _kw_djambi_get_full_piece_name($victim)
          ));
          $form["pieces"]["reportage"]["cell-" . $position] = array(
              "#type" => "image_button",
              '#name' => 'target-' . $position,
              '#id' => 'target-' . $position,
              "#return_value" => $position,
              "#src" => base_path() . drupal_get_path("module", "kw_djambi") . "/img/flag_black.png",
              '#attributes' => array(
                  'alt' => $alt_text,
                  'title' => $alt_text,
                  'data-grid-button' => 'interaction'
              ),
              '#ajax' => $ajax_settings
          );
        }
        $phase_text = t("Your !reporter has to choose beetween several victims...",
            array("!reporter" => "<span class='piece " . $selected_piece->getType() . "'>" . _kw_djambi_get_full_piece_name($selected_piece) . "</span>"));
      }
      // Cas 5 : évacuation du trône
      elseif ($current_interaction["type"] == "throne_evacuation") {
        $form["pieces"]["throne_evacuation"]["#selected_piece_id"] = $selected_piece->getId();
        $selected_piece->buildAllowableMoves(FALSE);
        foreach ($selected_piece->getAllowableMoves() as $key => $position) {
          $alt_text = t("Move your !piece to !case", array(
              "!piece" => _kw_djambi_get_full_piece_name($selected_piece),
              "!case" => $position
          ));
          $form["pieces"]["throne_evacuation"]["cell-" . $position] = array(
              "#type" => "image_button",
              '#name' => 'target-' . $position,
              '#id' => 'target-' . $position,
              "#return_value" => $position,
              "#src" => base_path() . drupal_get_path("module", "kw_djambi") . "/img/apply.png",
              '#attributes' => array(
                  'alt' => $alt_text,
                  'title' => $alt_text,
                  'data-grid-button' => 'interaction'
              ),
              '#ajax' => $ajax_settings
          );
        }
        $phase_text = t("Your !piece is not allowed to stay here. Run away !",
            array("!piece" => "<span class='piece " . $selected_piece->getType() . "'>" . _kw_djambi_get_full_piece_name($selected_piece) . "</span>"));
      }
    }
    // Attente du mouvement...
    elseif ($form_state["kw_djambi"]["phase"] == "piece_selection") {
      $phase_text = t("It's your turn. Select a movable piece in the grid...");
    }
  }
  if (!empty($phase_text)) {
    $form["textes"]["phase"] = array(
        "#type" => "markup",
        "#markup" => $phase_text
    );
  }
}

/**
 * Renvoie une liste d'événements survenus lors d'une partie
 * @param DjambiBattlefield $grid
 */
function _kw_djambi_build_part_log(&$form, &$form_state, DjambiBattlefield $grid) {
  // Construction des logs
  $default_value = 20;
  $nb_current_msg = !isset($form_state['kw_logs']['end']) ? $default_value : $form_state['kw_logs']['end'];
  $log = array('time' => array(), 'msg' => array());
  foreach ($grid->getEvents() as $key => $event) {
    $msg = _kw_djambi_describe_event($event, $grid);
    $log["time"][] = $event["time"];
    $log["msg"][] = "<div class='event ". $event["type"] . "'><span class=''>" . format_date($event["time"], "short") . " :</span> " . $msg . "</div>";
  }
  foreach ($grid->getMoves() as $key => $move) {
    $msg = _kw_djambi_describe_move($move, $grid);
    if (!empty($msg)) {
      $log["time"][] = $move["time"];
      $log["msg"][] = "<div class='" . $move["type"] . "'><span class=''>" . format_date($move["time"], "short") . " :</span>  " . $msg . "</div>";
    }
  }
  array_multisort($log["time"], SORT_DESC, $log["msg"]);
  // Affichage
  $nb_total_msg = count($log['msg']);
  $ajax_log_settings = array(
        'callback' => 'kw_djambi_ajax_log_callback',
        'wrapper' => 'GridLog' . $grid->getId(),
        'effect' => 'fade',
        'method' => 'replace',
  );
  if ($nb_current_msg == -1) {
    $nb_current_msg = $nb_total_msg;
    $offset_length = $nb_total_msg;
  }
  else {
    $offset_length = $default_value;
  }
  $msg = array_slice($log['msg'], isset($form_state['kw_logs']['begin']) ? $form_state['kw_logs']['begin'] : 0, $offset_length, TRUE);
  $form['log']['container'] = array(
        'history' => array(
            '#markup' => theme('item_list', array('items' => $msg))),
        'more' => array(
            '#type' => 'container',
            '#attributes' => array('id' => 'GridLog' . $grid->getId())
        )
  );
  if ($nb_total_msg > ($nb_current_msg + 20)) {
    $form['log']['container']['more']['load_more_logs'] = array(
        '#type' => 'submit',
        '#value' => t('Load 20 older logs'),
        '#submit' => array('kw_djambi_load_more_logs'),
        '#ajax' => $ajax_log_settings,
        '#name' => 'ui',
        '#id' => 'ui-load-logs'
    );
  }
  if ($nb_total_msg > $nb_current_msg) {
    $form['log']['container']['more']['load_all_logs'] = array(
        '#type' => 'submit',
        '#value' => t('Load all logs'),
        '#submit' => array('kw_djambi_load_all_logs'),
        '#ajax' => $ajax_log_settings,
        '#name' => 'ui',
        '#id' => 'ui-load-all-logs'
    );
  }
}

/**
 * Génère les tableaux de statistiques
 * @param DjambiBattlefield $grid
 * @return multitype:Ambigous <An, string>
 */
function _kw_djambi_build_part_stats(&$form, DjambiBattlefield $grid) {
  $items = array();
  $factions = $grid->getFactions();
  $throne = NULL;
  $ranking = array();
  $players = array();
  if (empty($factions)) {
    return;
  }
  foreach ($factions as $faction) {
    if (in_array($faction->getStatus(), array(KW_DJAMBI_USER_VASSALIZED, KW_DJAMBI_USER_EMPTY_SLOT))) {
      continue;
    }
    if ($grid->getMode() != KW_DJAMBI_MODE_SANDBOX) {
      $username = theme('username', array('account' => user_load($faction->getUserDataItem('uid'))))
      . ($faction->getUserDataItem('uid') == 0 ? ' (' . $faction->getUserDataItem('ip') . ')' : '');
      $players[$faction->getId()] = $username;
    }
    $ranking[$faction->getId()] = $faction->getRanking();
    $stats['peace'][$faction->getId()] = 0;
  }
  if (!empty($ranking)) {
    asort($ranking);
  }
  foreach ($grid->getTurns() as $turn) {
    if (!empty($turn['end'])) {
      @$stats['playtime'][$turn['side']] += $turn['end'] - $turn['begin'];
    }
    if ($turn['turn_scheme'] % 2 == 1) {
      @$stats['throne'][$turn['side']]++;
    }
  }
  foreach ($grid->getMoves() as $key => $move) {
    if ($move['type'] == 'move') {
      $cells = $grid->getCells();
      $distance = max(
          abs($cells[$move['from']]['x'] - $cells[$move['to']]['x']),
          abs($cells[$move['from']]['y'] - $cells[$move['to']]['y']));
      @$stats['mobility'][$move['target']] += $distance;
      @$stats['moves']['turns'][$move['target_faction']]++;
      @$stats['moves']['distance'][$move['target_faction']] += $distance;
    }
    elseif ($move['type'] == 'murder') {
      @$stats['murders'][$move['acting_faction']][$move['target_faction']]++;
      if (isset($stats['peace'][$move['acting_faction']])) {
        $stats['peace'][$move['acting_faction']]++;
      }
      @$stats['terminator'][$move['acting']]++;
      if ($grid->getPieceById($move['acting'])->getType() == 'reporter') {
        $target = $grid->getPieceById($move['target']);
        @$stats['pulitzer'][$move['acting']]++;
        if ($target->getHability('must_live')) {
          $stats['pulitzer'][$move['acting']] += 4;
        }
        if ($target->getHability('move_dead_pieces')) {
          $stats['pulitzer'][$move['acting']] += 1;
        }
        if (!$target->getHability('limited_move')) {
          $stats['pulitzer'][$move['acting']] += 2;
        }
      }
    }
    elseif ($move['type'] == 'manipulation') {
      @$stats['manipulation'][$move['acting_faction']]++;
    }
    elseif ($move['type'] == 'necromobility') {
      @$stats['necromobility'][$move['acting_faction']]++;
    }
  }
  if ($grid->getStatus() == KW_DJAMBI_STATUS_FINISHED) {
    $header = array(
        array('data' => t('Award')),
        array('data' => t('Awarded to'))
    );
    $rows = array();
    $winners_array = array();
    $win_rank = 0;
    while (empty($winners_array)) {
      $win_rank++;
      $winners_array = array_keys($ranking, $win_rank, TRUE);
    }
    $winners = array();
    foreach ($winners_array as $winner) {
      $faction = $grid->getFactionById($winner);
      $winners[] = '<span class="faction ' . $faction->getClass() . '">'
      . _kw_djambi_get_translatable_messages($faction->getName()) . '</span>'
      . (!empty($players[$winner]) ? ' ' . $players[$winner] : '');
    }
    $rows[] = array(
        'data' => array(
            array('data' => count($winners) == 1 ? t('Great Beloved Ruler of Djambi') : t("No winner in this game... Last standing teams :"),
                'header' => TRUE),
            array('data' => t('!pieces', array(
                '!pieces' => implode(', ', $winners)))
            )
        ));
    $ranking_array = array();
    foreach ($ranking as $side => $rank) {
      if (empty($rank) || $rank == $win_rank) {
        continue;
      }
      $faction = $grid->getFactionById($side);
      $ranking_array[] = t('Rank !rank :  !side', array(
          '!rank' => $rank,
          '!side' => '<span class="faction ' . $faction->getClass() . '">'
          . _kw_djambi_get_translatable_messages($faction->getName()) . '</span>'
          . (!empty($players[$side]) ? ' ' . $players[$side] : '')
      ));
    }
    if (!empty($ranking_array)) {
      $rows[] = array(
          'data' => array(
              array('data' => t('Ashamed loser opponents'), 'header' => TRUE),
              array('data' => theme('item_list', array('items' => $ranking_array)))
          )
      );
    }
    if (!empty($stats['mobility']) && max($stats['mobility']) > 1) {
      $move =
      $rows[] = array(
          'data' => array(
              array('data' => t('Sarkozy Price of hyper-activity'), 'header' => TRUE),
              array('data' => t('!pieces, with !nb cases crossed', array(
                  '!pieces' => implode(', ', _kw_djambi_stats_get_winners($grid, 'mobility', $stats)),
                  '!nb' => max($stats['mobility'])))
              )
          ));
    }
    if (!empty($stats['terminator']) && max($stats['terminator']) > 1) {
      $rows[] = array(
          'data' => array(
              array('data' => t('Agent 47 Price of most efficient dirty work cleaner'), 'header' => TRUE),
              array('data' => t('!pieces, with !nb victims', array(
                  '!pieces' => implode(', ', _kw_djambi_stats_get_winners($grid, 'terminator', $stats)),
                  '!nb' => max($stats['terminator'])))
              )
          ));
    }
    if (!empty($stats['necromobility'])) {
      $nmove = max($stats['necromobility']);
      $rows[] = array(
          'data' => array(
              array('data' => t('Pope Formose Price of best dead exhumation specialist'), 'header' => TRUE),
              array('data' => format_plural($nmove, '!pieces, with !nb exhumation', '!pieces, with !nb exhumations', array(
                  '!pieces' => implode(', ', _kw_djambi_stats_get_winners($grid, 'necromobility', $stats, 'faction')),
                  '!nb' => $nmove)))
          ));
    }
    if (!empty($stats['manipulation'])) {
      $manipulations = max($stats['manipulation']);
      $rows[] = array(
          'data' => array(
              array('data' => t('Machiavelli Price of political manipulation'), 'header' => TRUE),
              array('data' => format_plural($manipulations, '!pieces, with !nb manipulated piece', '!pieces, with !nb manipulated pieces', array(
                  '!pieces' => implode(', ', _kw_djambi_stats_get_winners($grid, 'manipulation', $stats, 'faction')),
                  '!nb' => $manipulations))
              )
          ));
    }
    if (!empty($stats['peace']) && min($stats['peace']) < 2) {
      $rows[] = array(
          'data' => array(
              array('data' => t('Nobel Price of peace'), 'header' => TRUE),
              array('data' => implode(', ', _kw_djambi_stats_get_winners($grid, 'peace', $stats, 'faction', 'min')))
          ));
    }
    if (!empty($stats['pulitzer'])) {
      $scandals = max($stats['pulitzer']);
      $rows[] = array(
          'data' => array(
              array('data' => t('Pulitzer Price'), 'header' => TRUE),
              array('data' => implode(', ', _kw_djambi_stats_get_winners($grid, 'pulitzer', $stats)))
          ));
    }
    $items[] = theme('table', array('header' => $header, 'rows' => $rows, 'caption' => t('Table of honours')));
  }
  // Tableau des victimes
  $header = array();
  $header['Sides'] = array('data' => t('Sides'));
  $rows = array();
  $global_total = array(
      'Sides' => array('data' => 'Total', 'header' => TRUE),
  );
  $global_total_data = 0;
  foreach ($factions as $key => $faction) {
    if (in_array($faction->getStatus(), array(KW_DJAMBI_USER_EMPTY_SLOT, KW_DJAMBI_USER_VASSALIZED))) {
      continue;
    }
    $header[$faction->getId()] = array(
        'data' => '<abbr title="' . t('!faction deaths', array('!faction' => _kw_djambi_get_translatable_messages($faction->getName())))
          .'">' . $faction->getId() . '†</abbr>',
        'class' => array($faction->getClass(), 'faction', 'stats')
    );
    $row = array();
    $row['Sides'] = array(
        'class' => array($faction->getClass(), 'faction'),
        'data' => t('!faction murders', array('!faction' =>
            _kw_djambi_get_translatable_messages($faction->getName()))),
        'header' => TRUE);
    $total = 0;
    foreach ($factions as $subkey => $subfaction) {
      if (in_array($subfaction->getStatus(), array(KW_DJAMBI_USER_EMPTY_SLOT, KW_DJAMBI_USER_VASSALIZED))) {
        continue;
      }
      $murders = isset($stats['murders'][$faction->getId()][$subfaction->getId()]) ?
      $stats['murders'][$faction->getId()][$subfaction->getId()] : 0;
      $total += $murders;
      $std_row = array('data' => $murders, 'class' => array('number'));
      if ($grid->getOption('rule_canibalism') == 'yes' || $grid->getOption('rule_press_liberty') == 'foxnews') {
        $row[$subfaction->getId()] = $std_row;
      }
      else {
        $row[$subfaction->getId()] = ($faction->getId() != $subfaction->getId()) ? $std_row
        : array('data' => '-', 'class' => array('locked'));
      }
      if (!isset($global_total[$subfaction->getId()])) {
        $global_total[$subfaction->getId()] = array(
            'data' => 0,
            'class' => array('total', 'number')
        );
      }
      $global_total[$subfaction->getId()]['data'] += $murders;
    }
    $row['Total'] = array('data' => $total, 'class' => array('total', 'number'));
    $global_total_data += $total;
    $rows[] = $row;
  }
  $global_total['Total'] = array('data' => $global_total_data, 'class' => array('number'));
  $rows[] = array('data' => $global_total, 'class' => array('total'));
  $header['Total'] = array('data' => t('Total'));
  $items[] = theme('table', array('header' => $header, 'rows' => $rows, 'caption' => t('Table of dead pieces')));
  // Tableau des déplacements
  $header = array(
      0 => array('data' => t('Sides')),
      6 =>  array('data' => t('Status')),
      1 =>  array('data' => t('Moves')),
      2 =>  array('data' => t('Distance')),
      3 =>  array('data' => t('Rule length')),
      4 =>  array('data' => t('Play time')),
      5 =>  array('data' => '<abbr title="'. t("Average turn duration") .'">' . t('Avg t.d.') . '</abbr>')
  );
  $rows = array();
  $total = array(
      'class' => array('total'),
      'data' => array(
          0 => array('data' => 'Total'),
          6 => array('data' => _kw_djambi_get_translatable_messages($grid->getStatus())),
          1 => array('data' => 0, 'class' => array('number')),
          2 => array('data' => 0, 'class' => array('number')),
          3 => array('data' => 0, 'class' => array('number')),
          4 => array('data' => 0, 'class' => array('number')),
          5 => array('data' => '-', 'class' => array('number'))
      )
  );
  foreach ($factions as $faction) {
    if (in_array($faction->getStatus(), array(KW_DJAMBI_USER_EMPTY_SLOT, KW_DJAMBI_USER_VASSALIZED))) {
      continue;
    }
    $row = array();
    $row[0] = array(
        'data' => t('!faction', array('!faction' =>
            _kw_djambi_get_translatable_messages($faction->getName()))),
        'class' => array($faction->getClass(), 'faction', 'stats'),
        'header' => TRUE
    );
    $row[6] = array('data' => _kw_djambi_get_translatable_messages($faction->getStatus()));
    $row[1] = array(
        'data' => !empty($stats['moves']['turns'][$faction->getId()]) ? $stats['moves']['turns'][$faction->getId()] : 0,
        'class' => 'number');
    $total['data'][1]['data'] += $row[1]['data'];
    $row[2] = array(
        'data' => !empty($stats['moves']['distance'][$faction->getId()]) ? $stats['moves']['distance'][$faction->getId()] : 0,
        'class' => 'number');
    $total['data'][2]['data'] += $row[2]['data'];
    $row[3] = array(
        'data' => !empty($stats['throne'][$faction->getId()]) ? $stats['throne'][$faction->getId()] : 0,
        'class' => 'number');
    $total['data'][3]['data'] += $row[3]['data'];
    $row[4] = array(
        'data' => !empty($stats['playtime'][$faction->getId()]) ?
        kw_tools_duration($stats['playtime'][$faction->getId()]) : 0,
        'class' => 'number');
    if (isset($stats['playtime'][$faction->getId()])) {
      $total['data'][4]['data'] += $stats['playtime'][$faction->getId()];
    }
    $row[5] = array(
        'data' => !empty($stats['playtime'][$faction->getId()]) && !empty($stats['moves']['turns'][$faction->getId()]) ?
        kw_tools_duration($stats['playtime'][$faction->getId()] / $stats['moves']['turns'][$faction->getId()]) : '-',
        'class' => 'number');
    $rows[] = $row;
  }
  if ($total['data'][1]['data'] > 0) {
    $total['data'][5]['data'] = kw_tools_duration($total['data'][4]['data'] / $total['data'][1]['data']);
  }
  $total['data'][4]['data'] = kw_tools_duration($total['data'][4]['data']);
  $rows[] = $total;
  $items[] = theme('table', array('header' => $header, 'rows' => $rows, 'caption' => t('Table of moves')));
  $form['stats']['items'] = array(
        '#markup' => theme('item_list', array('items' => $items, 'attributes' => array('class' => array('stats'))))
  );
}

/**
 * Fonction d'aide à la génération des statistiques
 * Détermine le vainqueur d'un prix
 */
function _kw_djambi_stats_get_winners($grid, $price, $stats, $type = 'piece', $order = 'max') {
  $winners = array_keys($stats[$price], $order == 'min' ? min($stats[$price]) : max($stats[$price]));
  $winners_string_array = array();
  foreach ($winners as $winner) {
    if ($type == 'piece') {
      $piece = $grid->getPieceById($winner);
      $winners_string_array[] = '<span class="piece ' . $piece->getType() . ' ' . $piece->getFaction()->getClass() . '">' . _kw_djambi_get_full_piece_name($piece) . '</span>';
    }
    elseif ($type == 'faction') {
      $faction = $grid->getFactionById($winner);
      $winners_string_array[] = '<span class="faction ' . $faction->getClass() . '">'
      . _kw_djambi_get_translatable_messages($faction->getName()) . '</span>';
    }
  }
  return $winners_string_array;
}

/**
* Soumission de formulaire : chargement de logs supplémentaires
*/
function kw_djambi_load_more_logs($form, &$form_state) {
  $form_state['rebuild'] = TRUE;
  if (!isset($form_state['kw_logs']) || $form_state['kw_logs']['end'] == -1) {
    $form_state['kw_logs']['end'] = 20;
  }
  $form_state['kw_logs']['begin'] = $form_state['kw_logs']['end'];
  $form_state['kw_logs']['end'] += 20;
}

/**
 * Soumission de formulaire : chargement de tous les logs
 */
function kw_djambi_load_all_logs($form, &$form_state) {
  $form_state['rebuild'] = TRUE;
  $form_state['kw_logs']['begin'] = isset($form_state['kw_logs']['end']) ? $form_state['kw_logs']['end'] : 20;
  $form_state['kw_logs']['end'] = -1;
}

/**
 * Fonction appelée après une requête Ajax : regénération du formulaire de jeu
 */
function kw_djambi_ajax_callback($form, $form_state) {
  return $form;
}

/**
 * Fonction appelée après une requête Ajax : chargement des logs
 */
function kw_djambi_ajax_log_callback($form, $form_state) {
  return $form['log']['container'];
}

/**
 * Soumission du formulaire de jeu
 */
function kw_djambi_game_form_submit($form, &$form_state) {
  $change_turn = FALSE;
  $grid = $form['#grid'];
  $form_state['rebuild'] = TRUE;
  switch ($form_state['kw_djambi']['phase']) {
    case('interaction_result') :
      $interaction = current($form_state['kw_djambi']['interactions']);
      $piece = $form_state['kw_djambi']['selected_piece'];
      if ($interaction['type'] == 'murder') {
        $victim_piece = $interaction['target'];
        $piece->kill($victim_piece, $form_state['kw_djambi']['selected_interaction']);
        $grid = $piece->getFaction()->getBattlefield();
      }
      elseif ($interaction['type'] == 'necromobility') {
        $victim_piece = $interaction['target'];
        $piece->necromove($victim_piece, $form_state['kw_djambi']['selected_interaction']);
        $grid = $piece->getFaction()->getBattlefield();
      }
      elseif ($interaction['type'] == 'manipulation') {
        $manipulated_piece = $interaction['target'];
        $piece->manipulate($manipulated_piece, $form_state['kw_djambi']['selected_interaction']);
        $grid = $piece->getFaction()->getBattlefield();
      }
      elseif ($interaction['type'] == 'reportage') {
        $grid = $piece->getFaction()->getBattlefield();
        $cells = $grid->getCells();
        $victim = $cells[$form_state['kw_djambi']['selected_interaction']]['occupant'];
        $piece->kill($victim, $form_state['kw_djambi']['selected_interaction']);
        $grid = $piece->getFaction()->getBattlefield();
      }
      elseif ($interaction['type'] == 'throne_evacuation') {
        $piece->move($form_state['kw_djambi']['selected_interaction'], FALSE);
        $grid = $piece->getFaction()->getBattlefield();
      }
      $interaction_key = key($form_state['kw_djambi']['interactions']);
      unset($form_state['kw_djambi']['interactions'][$interaction_key]);
      unset($form_state['kw_djambi']['selected_interaction']);
      if (empty($form_state['kw_djambi']['interactions'])) {
        $change_turn = TRUE;
      }
      else {
        $form_state['kw_djambi']['phase'] = 'move_interactions';
      }
      break;
    case('move_result') :
      /* @var $piece DjambiPiece */
      $piece = $form_state['kw_djambi']['selected_piece'];
      $move_results = $piece->move($form_state['kw_djambi']['selected_destination']);
      $grid = $piece->getFaction()->getBattlefield();
      $form_state['kw_djambi']['selected_piece'] = $piece;
      if (!empty($move_results)) {
        $form_state['kw_djambi']['phase'] = 'move_interactions';
        $form_state['kw_djambi']['interactions'] = $move_results;
      }
      else {
        $change_turn = TRUE;
      }
      break;
  }
  if ($change_turn) {
    /* @var $grid DjambiBattlefield */
    $grid->changeTurn();
    _kw_djambi_save_game($grid);
  }
  $form_state['kw_djambi']['change_turn'] = $change_turn;
}

/**
 * Soumission de formulaire : annulation de la sélection d'une pièce
 */
function kw_djambi_game_form_cancel_selection_submit($form, &$form_state) {
  $form_state['rebuild'] = TRUE;
  if (isset($form_state['kw_djambi'])) {
    unset($form_state['kw_djambi']);
  }
}

/**
 * Soumission du bouton d'annulation du dernier tour du jeu
 */
function kw_djambi_game_form_cancel_last_turn_submit($form, &$form_state) {
  $form_state['rebuild'] = TRUE;
  $form_state['kw_djambi']['change_turn'] = TRUE;
  $grid = $form['#grid'];
  $grid->cancelLastTurn();
  _kw_djambi_save_game($grid);
}

/**
 * Soumission du bouton "passer son tour"
 */
function kw_djambi_game_form_skip_turn_submit($form, &$form_state) {
  $form_state['rebuild'] = TRUE;
  $grid = $form['#grid'];
  $faction = $grid->getPlayingFaction();
  $faction->skipTurn();
  $grid = $faction->getBattlefield();
  _kw_djambi_save_game($grid);
}

/**
 * Soumission du bouton "Abandonner la partie"
 */
function kw_djambi_game_form_withdraw_submit($form, &$form_state) {
  $form_state['rebuild'] = TRUE;
  $grid = $form['#grid'];
  $faction = $grid->getPlayingFaction();
  $faction->withdraw();
  $grid = $faction->getBattlefield();
  $grid->changeTurn();
  _kw_djambi_save_game($grid);
}

/**
 * Soumission du bouton "Revenir dans la partie"
 */
function kw_djambi_game_form_comeback_submit($form, &$form_state) {
  $form_state['rebuild'] = TRUE;
  $grid = $form['#grid'];
  $clicked_button = $form_state['clicked_button']['#parents'][0];
  $id = substr($clicked_button, strpos($clicked_button, '_') + 1);
  $faction = $grid->getFactionById($id);
  $faction->comeBackAfterWithdraw();
  $grid = $faction->getBattlefield();
  _kw_djambi_save_game($grid);
}

/**
 * Soumission du bouton "Proposer une partie nulle"
 */
function kw_djambi_game_form_draw_proposal_submit($form, &$form_state) {
  $form_state['rebuild'] = TRUE;
  $grid = $form['#grid'];
  $faction = $grid->getPlayingFaction();
  $faction->callForADraw();
  $grid = $faction->getBattlefield();
  _kw_djambi_save_game($grid);
}

/**
 * Soumission du bouton "Accepter une partie nulle"
 */
function kw_djambi_game_form_accept_peace_submit($form, &$form_state) {
  $form_state['rebuild'] = TRUE;
  $grid = $form['#grid'];
  $faction = $grid->getPlayingFaction();
  $faction->acceptDraw();
  $grid = $faction->getBattlefield();
  _kw_djambi_save_game($grid);
}

/**
 * Soumission du bouton "Refuser une partie nulle"
 */
function kw_djambi_game_form_reject_peace_submit($form, &$form_state) {
  $form_state['rebuild'] = TRUE;
  $grid = $form['#grid'];
  $faction = $grid->getPlayingFaction();
  $faction->rejectDraw();
  _kw_djambi_save_game($grid);
}

/**
 * Soumission du bouton "Afficher le mouvement précédent"
 */
function kw_djambi_game_form_show_prev_move_submit($form, &$form_state) {
  $form_state['rebuild'] = TRUE;
  if (isset($form_state['do_not_show_moves'])) {
    unset($form_state['do_not_show_moves']);
  }
  $form_state['show_turn_move'] = $form['show_moves']['#value']['prev'];
}

/**
 * Soumission du bouton "Afficher/cacher les boutons derniers déplacements"
 */
function kw_djambi_game_form_show_moves_submit($form, &$form_state) {
  global $user;
  $form_state['rebuild'] = TRUE;
  $form_state['show_moves'] = !$form_state['values']['show_moves'];
  if ($user->uid == 0 || isset($_SESSION['djambi']['show_moves'])) {
    $_SESSION['djambi']['show_moves'] = !$form_state['values']['show_moves'];
  }
  if ($user->uid > 0) {
    $edit['data']['djambi_show_moves'] = !$form_state['values']['show_moves'];
    user_save($user, $edit);
  }
}

/**
 * Soumission du bouton "Afficher l'aide"
 */
function kw_djambi_game_form_show_help_submit($form, &$form_state) {
  $form_state['rebuild'] = TRUE;
  if (isset($form_state['show_help'])) {
    unset($form_state['show_help']);
  }
  else {
    $form_state['show_help'] = TRUE;
  }
}

/**
 * Soumission du bouton "Revoir la partie"
 */
function kw_djambi_game_form_replay_submit($form, &$form_state) {
  $form_state['rebuild'] = TRUE;
  if ($form_state['clicked_button']['#id'] == 'ui-replay-pause') {
    $form_state['autoplay'] = FALSE;
    return;
  }
  if ($form_state['clicked_button']['#id'] == 'ui-replay-autoplay') {
    $form_state['autoplay'] = TRUE;
    $form_state['show_replay']++;
    return;
  }
  if (!isset($form_state['autoplay'])) {
    $form_state['autoplay'] = FALSE;
  }
  $grid = $form['#grid'];
  if (!isset($form_state['show_replay']) && $form_state['clicked_button']['#id'] == 'ui-replay-backward') {
    if ($grid->isPending()) {
      $form_state['show_replay'] = $grid->getCurrentTurnId() - count($form_state['values']['showable_turns']);
      $form_state['autoplay'] = TRUE;
    }
    else {
      $form_state['show_replay'] = $grid->getCurrentTurnId();
    }
  }
  elseif (!isset($form_state['show_replay']) || $form_state['clicked_button']['#id'] == 'ui-replay-begin') {
    $form_state['show_replay'] = 0;
    $form_state['autoplay'] = TRUE;
  }
  elseif ($form_state['clicked_button']['#id'] == 'ui-replay-backward') {
    if ($form_state['show_replay'] > 0) {
      $form_state['show_replay']--;
      $form_state['autoplay'] = FALSE;
    }
  }
  elseif ($form_state['clicked_button']['#id'] == 'ui-replay-end') {
    unset($form_state['show_replay']);
    unset($form_state['autoplay']);
  }
  else {
    if (($grid->isFinished() && $form_state['show_replay'] < $grid->getCurrentTurnId())
    || ($grid->isPending() && $form_state['show_replay'] < $grid->getCurrentTurnId() - 1)) {
      $form_state['show_replay']++;
    }
    else {
      unset($form_state['show_replay']);
    }
  }
}

/**
 * Soumission du bouton "Actualiser"
 */
function kw_djambi_game_form_refresh_submit($form, &$form_state) {
  $form_state['rebuild'] = TRUE;
}

/**
 * Soumission du bouton "Rejoindre une partie"
 */
function kw_djambi_game_form_participate_submit($form, &$form_state) {
  global $user;
  $grid = $form['#grid'];
  $form_state['kw_djambi']['change_turn'] = TRUE;
  $grid = _kw_djambi_load_game($grid->getId(), TRUE);
  if ($grid->getStatus() != KW_DJAMBI_STATUS_RECRUITING) {
    return;
  }
  $form_state['rebuild'] = TRUE;
  $allow_anonymous = $grid->getOption('allow_anonymous_players');
  if (!$allow_anonymous && $user->uid == 0) {
    drupal_set_message(t('Only registered players can join this game. Please !login or !register.', array(
          '!login' => l(t('login to your account'), 'user/login'),
          '!register' => l(t('create a new account'), 'user/register')
    )), 'error');
    return;
  }
  $clicked_button = $form_state['clicked_button'];
  $faction_id = $clicked_button['#array_parents'][2];
  $nb_empty_factions = 0;
  foreach ($grid->getFactions() as $faction) {
    if ($faction->getId() != $faction_id) {
      if (_kw_djambi_check_current_user($faction)) {
        $faction->setStatus(KW_DJAMBI_USER_EMPTY_SLOT);
      }
      if ($faction->getStatus() == KW_DJAMBI_USER_EMPTY_SLOT) {
        $nb_empty_factions++;
      }
    }
  }
  $faction = $grid->getFactionById($faction_id);
  if ($faction->getStatus() == KW_DJAMBI_USER_EMPTY_SLOT) {
    $faction->setStatus(KW_DJAMBI_USER_READY);
    $user_data = $faction->getUserData();
    $user_data['joined'] = time();
    _kw_djambi_update_user_infos($user_data, $faction->getUserDataItem('djuid'), TRUE);
    if ($nb_empty_factions == 0) {
      $grid->setStatus(KW_DJAMBI_STATUS_PENDING);
    }
    $grid->logEvent('info', 'NEW_TEAM', array('faction1' => $faction->getId(),
        '!user_id' => $user->uid, '!user_ip' => $_SERVER['REMOTE_ADDR']));
    // FIXME changer l'appel lors de la personnalisation des camps
    drupal_set_message(t('You are now playing the !color team.',
        array('!color' => _kw_djambi_get_translatable_messages($faction->getName()))));
    _kw_djambi_save_game($grid);
  }
  else {
    drupal_set_message(t('This faction is used by an other player.'), 'error');
  }
}

/**
 * Soumission du bouton "Quitter la partie"
 */
function kw_djambi_game_form_cancel_participation_submit($form, &$form_state) {
  $grid = $form['#grid'];
  if ($grid->getStatus() == KW_DJAMBI_STATUS_RECRUITING) {
    $nb_playing_factions = 0;
    foreach ($grid->getFactions() as $faction) {
      if (_kw_djambi_check_current_user($faction)) {
        $faction->setStatus(KW_DJAMBI_USER_EMPTY_SLOT);
      }
      if ($faction->getStatus() == KW_DJAMBI_USER_READY) {
        $nb_playing_factions++;
      }
    }
    if ($nb_playing_factions == 0) {
      node_delete($grid->getId());
    }
    else {
      global $user;
      $grid->logEvent('info', 'TEAM_EXIT', array('!user_id' => $user->uid, '!user_ip' => $_SERVER['REMOTE_ADDR']));
      _kw_djambi_save_game($grid);
    }
    drupal_set_message(t('You just left Djambi game #!nb', array('!nb' => $grid->getInfo('sequence'))));
    drupal_goto('<front>');
  }
}

/**
 * Validation de la sélection d'une pièce
 */
function kw_djambi_piece_selection_validate(&$errors, $piece_id, DjambiBattlefield $grid) {
  global $user;
  $errors = array();
  $piece_form_locator = "pieces[movable][" . $piece_id;
  $faction = $grid->getPlayingFaction();
  $is_current_user = _kw_djambi_check_current_user($faction);
  if (!$is_current_user) {
    $errors[] = array(
        "location" => $piece_form_locator,
        "msg" => t("You do not own the !faction pieces.", array("!faction",
            _kw_djambi_get_translatable_messages($faction->getName())))
    );
    return FALSE;
  }
  $pieces = $faction->getControlledPieces();
  if (!isset($pieces[$piece_id])) {
    $errors[] =  array(
        "location" => $piece_form_locator,
        "msg" => t("Unrecognized piece.")
    );
    return FALSE;
  }
  $piece = $pieces[$piece_id];
  if (!$piece->isMovable()) {
    $errors = array(
        "location" => $piece_form_locator,
        "msg" => t("The piece !piece cannot move currently.", array("!piece" => $piece->getLongname("t")))
    );
  }
  if (!empty($errors)) {
    return FALSE;
  }
  return $piece;
}

/**
 * Validation des actions sur la grille de jeu
 */
function kw_djambi_game_form_validate($form, &$form_state) {
  module_load_include('inc', 'kw_djambi', 'kw_djambi.form');
  /* @var $grid DjambiBattlefield */
  $grid = $form["#grid"];
  $errors = array();
  // Détection du bouton cliqué.
  $button = $form_state["triggering_element"];
  $type_button = isset($button["#array_parents"][1]) ? $button["#array_parents"][1] : NULL;
  // Traitement des différents cas
  if ($type_button == "movable") {
    $piece = kw_djambi_piece_selection_validate($errors, $button["#return_value"], $grid);
    if ($piece && isset($form_state["kw_djambi"]["selected_piece"])) {
      $selected_piece = $form_state["kw_djambi"]["selected_piece"];
      if ($piece->getId() == $selected_piece->getId()) {
        $piece = FALSE;
        unset($form_state["kw_djambi"]["selected_piece"]);
        $form_state["kw_djambi"]["phase"] = "piece_selection";
      }
    }
    if ($piece) {
      $form_state["kw_djambi"]["phase"] = "piece_destination";
      $form_state["kw_djambi"]["selected_piece"] = $piece;
      if (!empty($form_state["input"]["piece_destination"])) {
        $destination = kw_djambi_piece_destination_validate($errors, $piece, $form_state["input"]["piece_destination"], NULL);
        if ($destination) {
          $form_state["kw_djambi"]["phase"] = "move_result";
          $form_state["kw_djambi"]["selected_destination"] = $form_state["input"]["piece_destination"];
        }
      }
    }
  }
  elseif ($type_button == "destination" && isset($form_state["kw_djambi"]["selected_piece"])) {
    $piece = $form_state["kw_djambi"]["selected_piece"];
    $destination = kw_djambi_piece_destination_validate($errors, $piece, $button["#return_value"], $button["#name"]);
    if ($destination) {
      $form_state["kw_djambi"]["phase"] = "move_result";
      $form_state["kw_djambi"]["selected_destination"] = $button["#return_value"];
    }
  }
  elseif (isset($form_state["kw_djambi"]["interactions"])) {
    $current_interaction = current($form_state["kw_djambi"]["interactions"]);
    $selected_piece = $form_state["kw_djambi"]["selected_piece"];
    if ($current_interaction["type"] == "murder" && $type_button == $current_interaction["type"]) {
      $victim_piece = $current_interaction["target"];
      $death = kw_djambi_piece_murder_validate($errors, $grid, $selected_piece, $victim_piece, $button["#return_value"], $button["#name"]);
      if ($death) {
        $form_state["kw_djambi"]["phase"] = "interaction_result";
        $form_state["kw_djambi"]["selected_interaction"] = $button["#return_value"];
      }
    }
    elseif ($current_interaction["type"] == "necromobility" && $type_button == $current_interaction["type"]) {
      $manipulated_piece = $current_interaction["target"];
      $manipulation = kw_djambi_piece_necromobility_validate($errors, $grid, $manipulated_piece, $button["#return_value"], $button["#name"]);
      if ($manipulation) {
        $form_state["kw_djambi"]["phase"] = "interaction_result";
        $form_state["kw_djambi"]["selected_interaction"] = $button["#return_value"];
      }
    }
    elseif ($current_interaction["type"] == "manipulation" && $type_button == $current_interaction["type"]) {
      $manipulated_piece = $current_interaction["target"];
      $manipulation = kw_djambi_piece_manipulation_validate($errors, $grid, $selected_piece, $manipulated_piece, $button["#return_value"], $button["#name"]);
      if ($manipulation) {
        $form_state["kw_djambi"]["phase"] = "interaction_result";
        $form_state["kw_djambi"]["selected_interaction"] = $button["#return_value"];
      }
    }
    elseif ($current_interaction["type"] == "reportage" && $type_button == $current_interaction["type"]) {
      $reporter = $form_state["kw_djambi"]["selected_piece"];
      $reportage = kw_djambi_piece_reportage_validate($errors, $grid, $reporter, $button["#return_value"], $button["#name"]);
      if ($reportage) {
        $form_state["kw_djambi"]["phase"] = "interaction_result";
        $form_state["kw_djambi"]["selected_interaction"] = $button["#return_value"];
      }
    }
    elseif ($current_interaction["type"] == "throne_evacuation" && $type_button == $current_interaction["type"]) {
      $evacuation = kw_djambi_piece_evacuation_validate($errors, $grid, $selected_piece, $button["#return_value"], $button["#name"]);
      if ($evacuation) {
        $form_state["kw_djambi"]["phase"] = "interaction_result";
        $form_state["kw_djambi"]["selected_interaction"] = $button["#return_value"];
      }
    }
  }
  if (!empty($errors)) {
    foreach ($errors as $error) {
      form_set_error($error["location"], $error["msg"]);
    }
  }
}

/**
 * Validation du déplacement d'une pièce
 */
function kw_djambi_piece_destination_validate(&$errors, DjambiPiece $piece, $destination, $button_name) {
  $move = FALSE;
  foreach($piece->getAllowableMoves() as $allowable_move) {
    if($destination == $allowable_move) {
      $move = $destination;
      break;
    }
  }
  if (!$move && !is_null($button_name)) {
    $errors[] = array(
        "location" => "pieces[destination][" . $button_name,
        "msg" => t("You are not allowed to move !piece into !case case.", array(
            "!piece" => $piece->getLongname("t"), "!case" => $destination))
    );
  }
  return $move;
}

/**
 * Validation du meurtre d'une pièce
 */
function kw_djambi_piece_murder_validate(&$errors, DjambiBattlefield $grid, DjambiPiece $murderer, DjambiPiece $victim, $destination, $button_name) {
  if (!$victim->isAlive()) {
    $errors[] = array(
        "location" => "pieces[destination][" . $button_name,
        "msg" => t("Your victim seems to be already dead.")
    );
    return FALSE;
  }
  $can_attack = $murderer->checkAttackingPossibility($victim);
  if (!$can_attack) {
    $errors[] = array(
        "location" => "pieces[destination][" . $button_name,
        "msg" => t("You are not allowed to kill this piece.")
    );
    return FALSE;
  }
  $free_cells = $grid->getFreeCells($victim);
  if (!in_array($destination, $free_cells)) {
    $errors[] = array(
        "location" => "pieces[destination][" . $button_name,
        "msg" => t("You cannot place bury your victim here, case !case is unavailable.", array("!case" => $destination))
    );
    return FALSE;
  }
  return TRUE;
}

/**
 * Validation du reportage sur une pièce
 */
function kw_djambi_piece_reportage_validate(&$errors, DjambiBattlefield $grid, DjambiPiece $piece, $destination, $button_name) {
  $cells = $grid->getCells();
  if (!isset($cells[$destination])) {
    $errors[] = array(
        "location" => "pieces[destination][" . $button_name,
        "msg" => t("The reportage location is totally incorrect.")
    );
    return FALSE;
  }
  if (empty($cells[$destination]["occupant"])) {
    $errors[] = array(
        "location" => "pieces[destination][" . $button_name,
        "msg" => t("There is nobody here (!case).", array("!case" => $destination))
    );
    return FALSE;
  }
  $occupant = $cells[$destination]["occupant"];
  if (!$occupant->isAlive()) {
    $errors[] = array(
        "location" => "pieces[destination][" . $button_name,
        "msg" => t("Already dead, too late for a reportage.")
    );
    return FALSE;
  }
  if ($grid->getOption('rule_press_liberty') == 'pravda'
      && $occupant->getFaction()->getControl()->getId() == $piece->getFaction()->getControl()->getId()) {
    $errors[] = array(
        "location" => "pieces[destination][" . $button_name,
        "msg" => t("It is not a good idea to make a reportage about your own team evil actions.")
    );
    return FALSE;
  }
  return TRUE;
}

/**
 * Validation de la manipulation d'une pièce
 */
function kw_djambi_piece_manipulation_validate(&$errors, DjambiBattlefield $grid, DjambiPiece $diplomat, DjambiPiece $manipulated, $destination, $button_name) {
  if (!$manipulated->isAlive()) {
    $errors[] = array(
        "location" => "pieces[destination][" . $button_name,
        "msg" => t("You cannot manipulate a dead piece (use a necromobil for this disgusting work).")
    );
    return FALSE;
  }
  $can_manipulate = $diplomat->checkManipulatingPossibility($manipulated);
  if(!$can_manipulate) {
    $errors[] = array(
        "location" => "pieces[destination][ " . $button_name,
        "msg" => t("You cannot manipulate this piece.")
    );
    return FALSE;
  }
  $free_cells = $grid->getFreeCells($manipulated);
  if (!in_array($destination, $free_cells)) {
    $errors[] = array(
        "location" => "pieces[destination][" . $button_name,
        "msg" => t("You cannot place the manipulated piece here, case !case is unavailable.", array("!case" => $destination))
    );
    return FALSE;
  }
  return TRUE;
}

/**
 * Validation du déplacement d'un mort
 */
function kw_djambi_piece_necromobility_validate(&$errors, DjambiBattlefield $grid, DjambiPiece $piece, $destination, $button_name) {
  if ($piece->isAlive()) {
    $errors[] = array(
        "location" => "pieces[destination][" . $button_name,
        "msg" => t("This dead body is still breathing...")
    );
    return FALSE;
  }
  $free_cells = $grid->getFreeCells($piece);
  if (!in_array($destination, $free_cells)) {
    $errors[] = array(
        "location" => "pieces[destination][" . $button_name,
        "msg" => t("You cannot place the dead piece here, case !case is unavailable.", array("!case" => $destination))
    );
    return FALSE;
  }
  return TRUE;
}

/**
 * Validation de l'évacuation d'un pion
 */
function kw_djambi_piece_evacuation_validate(&$errors, DjambiBattlefield $grid, DjambiPiece $piece, $destination, $button_name) {
  return kw_djambi_piece_destination_validate($errors, $piece, $destination, $button_name);
}