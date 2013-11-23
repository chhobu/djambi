<?php
/**
 * @file
 * Contient les fonctions de gestion du formulaire principal de Djambi
 *   (grille de jeu).
 */

/**
 * Génère le formulaire de jeu de Djambi.
 */
function _kw_djambi_build_game_form(&$form, &$form_state, \Djambi\Battlefield $grid, $options = NULL) {
  $context = \Drupal\kw_djambi\Djambi\DjambiContext::getInstance();
  $show_log = TRUE;
  $show_rules = TRUE;
  $show_stats = TRUE;
  if (isset($options['show_log'])) {
    $show_log = $options['show_log'];
  }
  if (isset($options['show_rules'])) {
    $show_rules = $options['show_rules'];
  }
  if (isset($options['show_stats'])) {
    $show_stats = $options['show_stats'];
  }
  $current_user_faction = $context->getUserFaction($grid);
  if ($grid->isFinished() && !empty($current_user_faction)) {
    $form_state['kw_djambi']['result'] = $current_user_faction->getStatus();
  }
  if (isset($form_state['show_replay'])) {
    $grid->viewTurnHistory($form_state['show_replay']);
    $replay = TRUE;
  }
  else {
    $replay = FALSE;
  }
  if (!$replay && !$grid->isFinished()) {
    $is_refresh_modes = !in_array($grid->getMode(), array(
      KW_DJAMBI_MODE_SANDBOX,
      KW_DJAMBI_MODE_TRAINING,
    ));
    if ($is_refresh_modes && !is_null($current_user_faction)) {
      $context->sendSignal();
    }
  }
  $faction = $grid->getPlayingFaction();
  $is_user_playing_current_faction = empty($faction) ? FALSE : $context->checkUserPlayingFaction($faction);
  // Inclusion du Javascript et du CSS :
  if (!empty($faction)) {
    $jquery_uis = array(
      'ui.core',
      'ui.mouse',
      'ui.mouse',
      'ui.draggable',
      'ui.droppable',
    );
    foreach ($jquery_uis as $ui) {
      $form['#attached']['library'][] = array('system', $ui);
    }
  }
  $form['#attached']['css'][] = drupal_get_path('module', 'kw_djambi') . '/kw_djambi.css';
  $form['#attached']['js'][] = drupal_get_path('module', 'kw_djambi') . '/js/kw_djambi.js';
  $ajax_settings = array(
    'callback' => 'kw_djambi_ajax_callback',
    'wrapper' => 'DjambiContainer' . $grid->getId(),
    'effect' => 'none',
    'method' => 'replace',
  );
  // Création de l'ossature du formulaire :
  $do_refresh = 'no';
  if (!$grid->isFinished() && !$replay) {
    if ($grid->isNotBegin()) {
      $do_refresh = 'check';
    }
    elseif (is_null($current_user_faction)) {
      $do_refresh = 'check';
    }
    elseif ($faction && !is_null($faction->getPlayer()) && !$faction->getPlayer()->isHuman()) {
      $do_refresh = 'force';
    }
    elseif ($faction && $grid->getMode() != KW_DJAMBI_MODE_SANDBOX) {
      $do_refresh = $faction->getId() != $current_user_faction->getId() ? 'check' : 'minimal';
    }
  }
  $grid_id = 'DjambiContainer' . $grid->getId();
  $attributes = array(
    'id' => 'DjambiGridFieldset' . $grid->getId(),
    'class' => array('djambi'),
    'data-version' => $grid->getGameManager()->getChanged(),
    'data-status' => $grid->getStatus(),
    'data-refresh' => $do_refresh,
    'data-user-faction' => (!empty($current_user_faction) ? $current_user_faction->getId() : '0'),
    'data-user-status' => (!empty($current_user_faction) ? $current_user_faction->getStatus() : '0'),
    'data-turn' => $grid->getCurrentTurnId(),
  );
  $nid = $grid->getGameManager()->getInfo('nid');
  if (!empty($nid)) {
    $attributes['data-nid'] = $nid;
  }
  if ($replay) {
    if (isset($form_state['autoplay']) && $form_state['autoplay']) {
      $attributes['class'][] = 'autoplay';
    }
    if (isset($form_state['pause'])) {
      $attributes['class'][] = 'paused';
      unset($form_state['pause']);
    }
    $attributes['class'][] = 'animated';
  }
  $form['#prefix'] = '<div id="' . $grid_id . '">';
  $form['#suffix'] = '</div>';
  $sequence = $grid->getGameManager()->getInfo('sequence');
  if ($sequence) {
    $title = t('Djambi grid #!number', array('!number' => $sequence));
  }
  else {
    $title = t('Unnumeroted Djambi grid');
  }
  $form['grid'] = array(
    '#type' => 'fieldset',
    '#title' => $title,
    '#weight' => 0,
    '#prefix' => '<div ' . drupal_attributes($attributes) . '>',
    '#suffix' => '</div>',
    '#grid' => $grid,
    '#theme' => $grid->isNotBegin() ? 'djambi_recruiting_phase' : 'djambi_grid',
  );
  if ($do_refresh != 'no') {
    $form['grid']['controls']['refresh'] = array(
      '#type' => 'submit',
      '#attributes' => array('class' => array('refresh-button')),
      '#value' => t('Refresh'),
      '#ajax' => $ajax_settings,
      '#weight' => 99,
      '#submit' => array('kw_djambi_game_form_refresh_submit'),
      '#name' => 'ui-refresh',
      '#limit_validation_errors' => array(),
    );
  }
  if ($show_log) {
    $form['options'] = array(
      '#type' => 'fieldset',
      '#title' => t('Game configuration'),
      '#weight' => 4,
      '#collapsible' => TRUE,
      '#collapsed' => $grid->isNotBegin() ? FALSE : TRUE,
    );
  }
  if ($show_stats && !$grid->isNotBegin()) {
    $form['stats'] = array(
      '#type' => 'fieldset',
      '#title' => $grid->getStatus() == KW_DJAMBI_STATUS_FINISHED ? t('Stats and awards') : t('Stats'),
      '#weight' => 5,
    );
  }
  if ($show_log) {
    $form['log'] = array(
      '#type' => 'fieldset',
      '#title' => $grid->isNotBegin() ? t('Recruitment log') : t('Treachery log'),
      '#weight' => 10,
      '#collapsible' => TRUE,
      '#collapsed' => FALSE,
    );
  }
  // Recrutement des joueurs...
  if ($grid->isNotBegin()) {
    _kw_djambi_build_part_recruiting($form, $grid, $current_user_faction, $ajax_settings);
  }
  else {
    // Contrôle de l'affichage de l'aide et des derniers mouvements :
    _kw_djambi_build_part_move_controls($form, $form_state, $grid);
  }
  // Affichage du récapitulatif des règles
  if ($show_rules) {
    _kw_djambi_build_part_rules($form, $grid);
  }
  if (!isset($form_state['show_replay']) && $grid->isPending()) {
    // Interface de réponse sur proposition de partie nulle :
    if ($faction && $grid->getStatus() == KW_DJAMBI_STATUS_DRAW_PROPOSAL) {
      _kw_djambi_build_part_draw_proposal($form['grid'], $grid, $faction, $ajax_settings, $is_user_playing_current_faction);
    }
    elseif ($faction && $grid->getStatus() == KW_DJAMBI_STATUS_PENDING && $is_user_playing_current_faction) {
      _kw_djambi_build_part_player_actions($form, $form_state, $grid, $faction, $ajax_settings);
      _kw_djambi_build_part_pieces_controls($form['grid'], $form_state, $grid, $faction, $ajax_settings);
    }
    elseif (!is_null($faction->getControl()->getPlayer()) && !$faction->getControl()->getPlayer()->isHuman()) {
      _kw_djambi_build_part_computer_actions($form, $ajax_settings);
    }
  }
  // Log des événements :
  if ($show_log && !empty($form['log'])) {
    _kw_djambi_build_part_log($form, $form_state, $grid);
  }
  // Afichage des stats :
  if ($show_stats && !empty($form['stats'])) {
    _kw_djambi_build_part_stats($form, $grid);
  }
  // Ajout des conteneurs si besoin :
  if (isset($form['grid']['controls'])) {
    $form['grid']['controls']['#type'] = 'container';
    $form['grid']['controls']['#attributes'] = array('class' => array('controls'));
    $form['grid']['controls']['#weight'] = 10;
  }
  // Redirection :
  $form["#action"] = "#DjambiGrid" . $grid->getId();
}

/**
 * Interface d'attente de nouveaux joueurs.
 *
 * @param array $form
 *   Formulaire Drupal
 * @param \Djambi\Battlefield $grid
 *   Grille de Djambi
 * @param \Djambi\Faction $current_user_faction
 *   Faction contrôlée par l'utilisateur courant
 * @param array $ajax_settings
 *   Configuration AJAX commune au formulaire courant
 */
function _kw_djambi_build_part_recruiting(&$form, \Djambi\Battlefield $grid, $current_user_faction, $ajax_settings) {
  $context = \Drupal\kw_djambi\Djambi\DjambiContext::getInstance();
  $allow_anonymous = $grid->getOption('allow_anonymous_players');
  $reject = FALSE;
  $form['grid']['factions'] = array(
    '#type' => 'container',
  );
  /* @var $faction \Djambi\Faction */
  foreach ($grid->getFactions() as $faction) {
    if ($faction->getStatus() == KW_DJAMBI_FACTION_STATUS_VASSALIZED) {
      continue;
    }
    $form['grid']['factions'][$faction->getId()]['label'] = array(
      '#type' => 'item',
      '#title' => theme('djambi_faction_name', array('faction' => $faction)),
      '#markup' => $faction->getStatus() == KW_DJAMBI_FACTION_STATUS_EMPTY_SLOT ?
      t('Empty') : $faction->getPlayer()->displayName(),
    );
    $me = $context->getCurrentUser()->isPlayingFaction($faction);
    if ($faction->getStatus() != KW_DJAMBI_FACTION_STATUS_EMPTY_SLOT) {
      $form['grid']['factions'][$faction->getId()]['infos'] = array(
        '#type' => 'value',
        '#value' => array(
          'me' => $me,
        ),
      );
    }
    if ($me) {
      $form['grid']['factions'][$faction->getId()]['action'] = array(
        '#type' => 'submit',
        '#name' => 'action',
        '#id' => 'action-leave-game',
        '#value' => t('Leave game'),
        '#submit' => array('kw_djambi_game_form_cancel_participation_submit'),
      );
    }
    elseif ($faction->getStatus() == KW_DJAMBI_FACTION_STATUS_EMPTY_SLOT) {
      if ($allow_anonymous || !$context->isAnonymous()) {
        $form['grid']['factions'][$faction->getId()]['action'] = array(
          '#type' => 'submit',
          '#name' => 'action-play-' . $faction->getId(),
          '#id' => 'action-play-' . $faction->getId(),
          '#value' => !is_null($current_user_faction) ? t('Play this side') : t('Join this game'),
          '#submit' => array('kw_djambi_game_form_participate_submit'),
          '#ajax' => $ajax_settings,
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
          '!register' => l(t('create a new account'), 'user/register'),
        )) . "</div>",
    );
  }
}

/**
 * Construit la partie contrôle des mouvements du formulaire grille de Djambi.
 */
function _kw_djambi_build_part_move_controls(&$form, &$form_state, \Djambi\Battlefield $grid) {
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
            $turn['turn'] == $turns[$current_turn_id]['turn'] - 1 && $turn['turn_scheme'] >= $turns[$current_turn_id]['turn_scheme']))
      ) {
        $faction_turns[$turn['side']][$turn_key] = $turn_key;
      }
    }
    if (!empty($faction_turns)) {
      foreach ($faction_turns as $turn) {
        $factions = count($faction_turns);
        if (count($turn) > $factions) {
          $turn = array_slice($turn, -$factions, $factions, TRUE);
        }
        $showable_turns += $turn;
      }
      asort($showable_turns);
      $last_move = min($showable_turns);
    }
    $form['grid']['controls']['showable_turns'] = array(
      '#type' => 'value',
      '#value' => $showable_turns,
    );
  }
  $ui_buttons = array(
    '#type' => 'submit',
    '#limit_validation_errors' => array(array('showable_turns')),
    '#ajax' => array('callback' => 'kw_djambi_ajax_grid_callback'),
  );
  if (!isset($form_state['show_replay'])) {
    if ($grid->isFinished()) {
      $form['grid']['controls']['replay'] = array_merge($ui_buttons, array(
        '#name' => 'ui-replay',
        '#value' => '<< ' . t('Review this game'),
        '#submit' => array('kw_djambi_game_form_replay_submit'),
      ));
    }
    $form['grid']['controls']['previous_turn'] = array_merge($ui_buttons, array(
      '#name' => 'ui-replay-backward',
      '#value' => '< ' . ($grid->isFinished() ? t('Show previous move') : t('Show previous moves')),
      '#submit' => array('kw_djambi_game_form_replay_submit'),
      '#disabled' => $current_turn_id > 0 ? FALSE : TRUE,
    ));
  }
  else {
    if ($grid->isFinished()) {
      $form['grid']['controls']['first_turn'] = array_merge($ui_buttons, array(
        '#name' => 'ui-replay-begin',
        '#value' => '<< ' . t('Go back to the first move'),
        '#submit' => array('kw_djambi_game_form_replay_submit'),
        '#disabled' => $form_state['show_replay'] > 0 ? FALSE : TRUE,
      ));
    }
    $form['grid']['controls']['previous_turn'] = array_merge($ui_buttons, array(
      '#name' => 'ui-replay-backward',
      '#value' => '< ' . t('Show previous move'),
      '#submit' => array('kw_djambi_game_form_replay_submit'),
      '#disabled' => $form_state['show_replay'] > $last_move ? FALSE : TRUE,
    ));
    $form['grid']['controls']['pause'] = array_merge($ui_buttons, array(
      '#name' => 'ui-replay-pause',
      '#value' => '|| ' . t('Pause'),
      '#submit' => array('kw_djambi_game_form_replay_submit'),
      '#attributes' => array('style' => 'display:none'),
    ));
    $form['grid']['controls']['autoplay'] = array_merge($ui_buttons, array(
      '#name' => 'ui-replay-autoplay',
      '#value' => '|> ' . t('Auto-play'),
      '#submit' => array('kw_djambi_game_form_replay_submit'),
      '#attributes' => array('style' => 'display:none'),
      '#disabled' => (($grid->isPending() && $current_turn_id - 1 <= $form_state['show_replay']) ||
        ($grid->isFinished() && $current_turn_id <= $form_state['show_replay']) ? TRUE : FALSE),
    ));
    $form['grid']['controls']['next_turn'] = array_merge($ui_buttons, array(
      '#name' => 'ui-replay-forward',
      '#value' => t('Show next move') . ' >',
      '#submit' => array('kw_djambi_game_form_replay_submit'),
      '#disabled' => (($grid->isPending() && $current_turn_id - 1 <= $form_state['show_replay']) ||
        ($grid->isFinished() && $current_turn_id <= $form_state['show_replay']) ? TRUE : FALSE),
    ));
    $form['grid']['controls']['last_turn'] = array_merge($ui_buttons, array(
      '#name' => 'ui-replay-end',
      '#value' => $grid->isPending() ? t('Go back to current turn') . ' >>' : t('Go back to final positions') . ' >>',
      '#submit' => array('kw_djambi_game_form_replay_submit'),
    ));
  }
  if ($grid->isPending() || isset($form_state['show_replay'])) {
    $form['grid']['controls']['show_moves_button'] = array(
      '#type' => 'submit',
      '#name' => 'ui-show-move',
      '#value' => $show_moves ? '- ' . t('Hide last moves descriptions') : '+ ' . t('Show last moves descriptions'),
      '#limit_validation_errors' => array(array('show_moves')),
      '#submit' => array('kw_djambi_game_form_show_moves_submit'),
      '#ajax' => array('callback' => 'kw_djambi_ajax_grid_callback'),
    );
  }
  if ($grid->isPending() && !isset($form_state['show_replay'])) {
    $form['grid']['controls']['show_help_button'] = array(
      '#type' => 'submit',
      '#name' => 'ui-show-help',
      '#value' => '(?) ' . (!empty($form_state['show_help']) ? t('Hide help') : t('Show help')),
      '#limit_validation_errors' => array(),
      '#submit' => array('kw_djambi_game_form_show_help_submit'),
      '#ajax' => array('callback' => 'kw_djambi_ajax_grid_callback'),
    );
  }
  $form['grid']['controls']['show_help'] = array(
    '#type' => 'value',
    '#value' => !empty($form_state['show_help']) ? TRUE : FALSE,
  );
  $form['grid']['controls']['show_replay'] = array(
    '#type' => 'value',
    '#value' => !empty($form_state['show_replay']) ? $form_state['show_replay'] : FALSE,
  );
  $form['grid']['controls']['show_moves'] = array(
    '#type' => 'value',
    '#value' => !empty($form_state['show_moves']) ? $form_state['show_moves'] : $show_moves,
  );
}

/**
 * Construit la partie règles du jeu du formulaire grille de Djambi.
 */
function _kw_djambi_build_part_rules(&$form, \Djambi\Battlefield $grid) {
  $rules_rows = array();
  $options = $grid->getDisposition()->getOptionsStore()->getAllGameOptions();
  /* @var \Djambi\GameOption $option */
  foreach ($options as $option) {
    $key = $option->getName();
    $modes = $option->getModes();
    if (!empty($modes) && !in_array($grid->getMode(), $modes)) {
      continue;
    }
    $value = $grid->getOption($key);
    $choices = $option->getChoices();
    if (is_array($choices) && !empty($choices[$value])) {
      $title = $option->getGenericLabel();
      $title_option = $option->getTitle();
      $rules_rows[] = array(
        array(
          'data' => (!empty($title) ? '<span class="' . $option->getCssClass() . '">'
          . _kw_djambi_get_translatable_messages($title, $option->getGenericLabelArgs()) . '</span> - ' : '') . '<strong>'
          . (!empty($title_option) ? _kw_djambi_get_translatable_messages($title_option) : NULL)
          . '</strong>',
        ),
        array(
          'class' => array('description'),
          'data' => _kw_djambi_get_translatable_messages($choices[$value], array('!value' => $value)),
        ),
      );
    }
  }
  if (!empty($rules_rows)) {
    $rules_header = array(
      array('data' => 'Label'),
      array('data' => 'Value'),
    );
    $form['options']['table'] = array(
      '#markup' => theme('table', array(
        'rows' => $rules_rows,
        'header' => $rules_header,
      )),
    );
  }
}

/**
 * Construit la partie négociations de paix du formulaire grille de Djambi.
 */
function _kw_djambi_build_part_draw_proposal(&$form, \Djambi\Battlefield $grid, \Djambi\Faction $faction, $ajax_settings, $is_user_playing_current_faction) {
  $factions = $grid->getFactions();
  /* @var $side \Djambi\Faction */
  foreach ($factions as $side) {
    if ($side->getDrawStatus() == 1) {
      $peacemonger = $side;
      break;
    }
  }
  if (!isset($peacemonger)) {
    return;
  }
  $form['actions'] = array(
    '#type' => 'fieldset',
    '#title' => t('Actions'),
    '#weight' => 20,
  );
  if ($is_user_playing_current_faction) {
    $form['actions']['descriptions'] = array(
      '#type' => 'item',
      '#title' => t('Now playing') . ' : ',
      '#prefix' => '<div class="container-inline">',
      '#suffix' => '</div>',
      '#markup' => t('!faction side', array(
        '!faction' =>
          theme('djambi_faction_name', array('faction' => $faction)),
      )),
      '#weight' => -10,
    );
    $form['actions']['explain'] = array(
      '#type' => 'markup',
      '#markup' => t("A peace proposal has been made by !faction side. What is your answer ?",
        array('!faction' => theme('djambi_faction_name', array('faction' => $peacemonger)))),
    );
    $form['actions']['accept_peace'] = array(
      '#type' => 'submit',
      '#name' => 'action',
      '#id' => 'action-accept-peace',
      '#value' => t("OK, end this game with a draw and stay good friends."),
      '#submit' => array('kw_djambi_game_form_accept_peace_submit'),
      '#ajax' => $ajax_settings,
    );
    $form['actions']['reject_peace'] = array(
      '#type' => 'submit',
      '#name' => 'action',
      '#id' => 'action-reject-peace',
      '#value' => t("No way, I am sure to win this game."),
      '#submit' => array('kw_djambi_game_form_reject_peace_submit'),
      '#ajax' => $ajax_settings,
    );
  }
  else {
    $form['actions']['explain'] = array(
      '#type' => 'markup',
      '#markup' => t("A peace proposal has been made by !faction side. Waiting for !current side answer...",
        array(
          '!faction' => _kw_djambi_get_translatable_messages($peacemonger->getName()),
          '!current' => _kw_djambi_get_translatable_messages($faction->getName()),
        )),
    );
  }
}

/**
 * Construit la partie actions de l'IA du formulaire grille de Djambi.
 */
function _kw_djambi_build_part_computer_actions(&$form, $ajax_settings) {
  $form['grid']['computer-actions'] = array(
    '#type' => 'fieldset',
    '#title' => t('Actions'),
    '#weight' => 20,
  );
  $form['grid']['computer-actions']['computer-hurry'] = array(
    '#type' => 'submit',
    '#value' => t('Hurry computer move'),
    '#submit' => array('kw_djambi_game_form_refresh_submit'),
    '#name' => 'ui-computer-refresh',
    '#limit_validation_errors' => array(),
    '#ajax' => $ajax_settings,
  );
}

/**
 * Renvoie la partie actions de l'utilisateur du formulaire grille de Djambi.
 */
function _kw_djambi_build_part_player_actions(&$form, &$form_state, \Djambi\Battlefield $grid, \Djambi\Faction $faction, $ajax_settings) {
  $actions = array();
  $descriptions = array();
  // Annulation de sélection d'une pièce :
  if (!empty($form_state["kw_djambi"]) && in_array($form_state["kw_djambi"]["phase"], array(
      'move_interactions',
      'piece_destination',
    ))
  ) {
    $actions['cancel_selection'] = array(
      '#type' => 'submit',
      '#name' => 'action',
      '#id' => 'action-cancel-selection',
      '#value' => t('Cancel piece selection'),
      '#limit_validation_errors' => array(),
      '#submit' => array('kw_djambi_game_form_cancel_selection_submit'),
      '#ajax' => $ajax_settings,
    );
  }
  // Annulation d'un mouvement :
  elseif ($grid->getMode() == KW_DJAMBI_MODE_SANDBOX && count($grid->getTurns()) > 1) {
    $actions['cancel_last_turn'] = array(
      '#type' => 'submit',
      '#name' => 'action',
      '#id' => 'action-cancel-move',
      '#value' => t('Cancel last move'),
      '#limit_validation_errors' => array(),
      '#submit' => array('kw_djambi_game_form_cancel_last_turn_submit'),
      '#ajax' => $ajax_settings,
    );
  }
  // Passer son tour :
  $allowed_skip_turns = $grid->getOption('allowed_skipped_turns_per_user');
  if ($allowed_skip_turns == -1 || $allowed_skip_turns > $faction->getSkippedTurns()) {
    if (empty($form_state['kw_djambi']['selected_piece'])) {
      $actions['skip_turn'] = array(
        '#type' => 'submit',
        '#name' => 'action',
        '#id' => 'action-skip-turn',
        '#value' => $allowed_skip_turns == -1 ? t('Skip turn') : t('Skip turn (!nb of !max already skipped)', array(
            '!nb' => $faction->getSkippedTurns(),
            '!max' => $allowed_skip_turns,
          )),
        '#submit' => array('kw_djambi_game_form_skip_turn_submit'),
        '#limit_validation_errors' => array(),
        '#ajax' => $ajax_settings,
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
  // Proposer une nulle :
  $draw_proposal_delay = $grid->getOption('turns_before_draw_proposal');
  $turns = $grid->getTurns();
  if ($draw_proposal_delay != -1 && $faction->getLastDrawProposal() + $draw_proposal_delay <= $turns[$grid->getCurrentTurnId()]['turn']) {
    $actions['draw_proposal'] = array(
      '#type' => 'submit',
      '#name' => 'action',
      '#id' => 'action-ask-draw',
      '#value' => t('Ask for a draw'),
      '#limit_validation_errors' => array(),
      '#submit' => array('kw_djambi_game_form_draw_proposal_submit'),
      '#ajax' => $ajax_settings,
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
  // Abandonner :
  $actions['withdrawal'] = array(
    '#type' => 'submit',
    '#name' => 'action',
    '#id' => 'action-withdraw',
    '#value' => t('Withdraw'),
    '#limit_validation_errors' => array(),
    '#submit' => array('kw_djambi_game_form_withdraw_submit'),
    '#ajax' => $ajax_settings,
  );
  // Retour après abandon :
  if ($grid->getOption('rule_comeback') == 'allowed' && $grid->getMode() == KW_DJAMBI_MODE_SANDBOX) {
    /* @var $withdrawn_faction \Djambi\Faction */
    foreach ($grid->getFactions() as $withdrawn_faction) {
      if ($withdrawn_faction->canComeBackAfterWithdraw()) {
        $actions['comeback']['comeback_' . $withdrawn_faction->getId()] = array(
          '#type' => 'submit',
          '#name' => 'action',
          '#id' => 'action-allow-comeback' . $withdrawn_faction->getId(),
          '#value' => t('Allow !faction side to come back in the game', array(
            '!faction' => _kw_djambi_get_translatable_messages($withdrawn_faction->getName()),
          )),
          '#submit' => array('kw_djambi_game_form_comeback_submit'),
          '#limit_validation_errors' => array(),
          '#ajax' => $ajax_settings,
          '#weight' => 10,
        );
      }
    }
    if (isset($actions['comeback'])) {
      $actions['comeback']['separator'] = array(
        '#markup' => '<hr />',
      );
    }
  }
  // Regroupement des actions dans un fieldset :
  if (!empty($actions) || !empty($descriptions)) {
    $form['grid']['actions'] = array(
      '#type' => 'fieldset',
      '#title' => t('Actions'),
      '#weight' => 20,
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
        '#markup' => t('!faction side', array('!faction' => theme('djambi_faction_name', array('faction' => $faction)))),
        '#weight' => -10,
        '#description' => theme('item_list', array('items' => $descriptions)),
      );
    }
  }
}

/**
 * Construit la partie contrôle des pièces du formulaire grille de Djambi.
 */
function _kw_djambi_build_part_pieces_controls(&$form, &$form_state, \Djambi\Battlefield $grid, \Djambi\Faction $faction, $ajax_settings) {
  $phase_text = NULL;
  // Par défaut : phase de sélection des pièces
  if (empty($form_state['kw_djambi'])) {
    $form_state['kw_djambi'] = array(
      'phase' => 'piece_selection',
    );
  }
  // Sélection des pièces :
  $image_path = base_path() . drupal_get_path('module', 'kw_djambi') . '/img/';
  $image_extension = '.png';
  $piece_selection_phases = array('piece_selection', 'piece_destination');
  if (in_array($form_state['kw_djambi']['phase'], $piece_selection_phases)) {
    /* @var $piece \Djambi\Piece */
    foreach ($faction->getControlledPieces() as $piece) {
      if ($piece->isMovable()) {
        $form['pieces']['movable'][$piece->getId()] = array(
          '#type' => 'image_button',
          '#return_value' => $piece->getId(),
          '#src' => $image_path . $piece->getImage() . $image_extension,
          '#name' => 'selection-' . $piece->getId(),
          '#id' => 'selection-' . $piece->getId(),
          '#attributes' => array(
            'alt' => t('Move !piece', array('!piece' => _kw_djambi_get_translatable_messages($piece->getLongname()))),
            'data-grid-button' => 'selection',
          ),
          '#limit_validation_errors' => array(),
          '#validate' => array('kw_djambi_game_form_validate'),
          '#submit' => array('kw_djambi_game_form_submit'),
          '#ajax' => $ajax_settings,
        );
      }
    }
    $form['piece_destination'] = array(
      '#type' => 'hidden',
      '#value' => '',
    );
  }
  // Destination des pièces :
  if ($form_state['kw_djambi']['phase'] == 'piece_destination') {
    /* @var $selected_piece \Djambi\Piece */
    $selected_piece = $form_state['kw_djambi']['selected_piece'];
    $form['pieces']['destination']['#selected_piece_id'] = $selected_piece->getId();
    $reachable_cells = $selected_piece->getAllowableMovesNames();
    foreach ($reachable_cells as $cell_key) {
      $alt_text = t('Move the !piece here (!case).', array(
        '!piece' => _kw_djambi_get_full_piece_name($selected_piece),
        '!case' => $cell_key,
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
          'data-grid-button' => 'destination',
        ),
        '#limit_validation_errors' => array(),
        '#validate' => array('kw_djambi_game_form_validate'),
        '#submit' => array('kw_djambi_game_form_submit'),
        '#ajax' => $ajax_settings,
      );
    }
    $form["pieces"]["movable"][$selected_piece->getId()]["#attributes"]["class"][] = "selected";
    $phase_text = t("The !piece is selected. Choose now its destination case, or select another piece to move.",
      array("!piece" => theme('djambi_piece_name', array('piece' => $selected_piece)))
    );
  }
  // Résultat du déplacement :
  elseif ($form_state['kw_djambi']['phase'] == 'move_interactions') {
    $current_interaction = current($form_state['kw_djambi']['interactions']);
    /* @var $selected_piece \Djambi\Piece */
    $selected_piece = $form_state['kw_djambi']['selected_piece'];
    // Cas 1 : placement d'une pièce tuée
    if ($current_interaction['type'] == 'murder') {
      $form['pieces']['murder']['#selected_piece_id'] = $selected_piece->getId();
      $victim_piece = $current_interaction['target'];
      $free_cells = $grid->getFreeCells($victim_piece, FALSE, TRUE);
      foreach ($free_cells as $cell) {
        $alt_text = t('Bury !piece in !case', array(
          '!piece' => _kw_djambi_get_full_piece_name($victim_piece),
          '!case' => $cell,
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
            'data-grid-button' => 'interaction',
          ),
          '#limit_validation_errors' => array(),
          '#validate' => array('kw_djambi_game_form_validate'),
          '#submit' => array('kw_djambi_game_form_submit'),
          '#ajax' => $ajax_settings,
        );
      }
      $phase_text = t('Your !piece has killed the !victim, select now the case where you victim will rest in peace.',
        array(
          '!piece' => theme('djambi_piece_name', array('piece' => $selected_piece)),
          '!victim' => theme('djambi_piece_name', array('piece' => $victim_piece)),
        ));
    }
    // Cas 2 : placement d'une pièce tuée déplacée :
    elseif ($current_interaction["type"] == "necromobility") {
      $form["pieces"]["necromobility"]["#selected_piece_id"] = $selected_piece->getId();
      /* @var $victim_piece \Djambi\Piece */
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
            'data-grid-button' => 'interaction',
          ),
          '#limit_validation_errors' => array(),
          '#validate' => array('kw_djambi_game_form_validate'),
          '#submit' => array('kw_djambi_game_form_submit'),
          '#ajax' => $ajax_settings,
        );
      }
      $phase_text = t("Your !piece has exhumed an old dead piece, select now its new burial place.", array(
        "!piece" => theme('djambi_piece_name', array('piece' => $selected_piece)),
      ));
    }
    // Cas 3 : placement d'une pièce manipulée :
    elseif ($current_interaction["type"] == "manipulation") {
      /* @var $manipulated_piece \Djambi\Piece */
      $manipulated_piece = $current_interaction["target"];
      $form["pieces"]["manipulation"]["#selected_piece_id"] = $selected_piece->getId();
      $form["pieces"]["manipulation"]["#target_piece_id"] = $manipulated_piece->getId();
      $free_cells = $grid->getFreeCells($manipulated_piece);
      foreach ($free_cells as $cell) {
        $alt_text = t("Move manipulated !piece to !case", array(
          "!piece" => _kw_djambi_get_full_piece_name($manipulated_piece),
          "!case" => $cell,
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
            'data-grid-button' => 'interaction',
          ),
          '#limit_validation_errors' => array(),
          '#validate' => array('kw_djambi_game_form_validate'),
          '#submit' => array('kw_djambi_game_form_submit'),
          '#ajax' => $ajax_settings,
        );
      }
      $phase_text = t("Your !diplomat is manipulating the !piece, select now the destination case for your subjucated victim.",
        array(
          "!diplomat" => theme('djambi_piece_name', array('piece' => $selected_piece)),
          "!piece" => theme('djambi_piece_name', array('piece' => $manipulated_piece)),
        ));
    }
    // Cas 4 : sélection de la victime d'un reportage :
    elseif ($current_interaction["type"] == "reportage") {
      $form["pieces"]["reportage"]["#selected_piece_id"] = $selected_piece->getId();
      /* @var $victim \Djambi\Piece */
      foreach ($current_interaction["victims"] as $victim) {
        $position = $victim->getPosition()->getName();
        $alt_text = t("Lauch an inquiry on !piece", array(
          "!piece" => _kw_djambi_get_full_piece_name($victim),
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
            'data-grid-button' => 'interaction',
          ),
          '#limit_validation_errors' => array(),
          '#validate' => array('kw_djambi_game_form_validate'),
          '#submit' => array('kw_djambi_game_form_submit'),
          '#ajax' => $ajax_settings,
        );
      }
      $phase_text = t("Your !reporter has to choose beetween several victims...",
        array("!reporter" => theme('djambi_piece_name', array('piece' => $selected_piece))));
    }
    // Cas 5 : évacuation du trône :
    elseif ($current_interaction["type"] == "throne_evacuation") {
      $form["pieces"]["throne_evacuation"]["#selected_piece_id"] = $selected_piece->getId();
      $selected_piece->buildAllowableMoves(FALSE);
      foreach ($selected_piece->getAllowableMovesNames() as $position) {
        $alt_text = t("Move your !piece to !case", array(
          "!piece" => _kw_djambi_get_full_piece_name($selected_piece),
          "!case" => $position,
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
            'data-grid-button' => 'interaction',
          ),
          '#limit_validation_errors' => array(),
          '#validate' => array('kw_djambi_game_form_validate'),
          '#submit' => array('kw_djambi_game_form_submit'),
          '#ajax' => $ajax_settings,
        );
      }
      $phase_text = t("Your !piece is not allowed to stay here. Run away !",
        array("!piece" => theme('djambi_piece_name', array('piece' => $selected_piece))));
    }
  }
  // Attente du mouvement...
  elseif ($form_state["kw_djambi"]["phase"] == "piece_selection") {
    $phase_text = t("It's your turn. Select a movable piece in the grid...");
  }
  if (!empty($phase_text)) {
    $form["textes"]["phase"] = array(
      "#type" => "markup",
      "#markup" => $phase_text,
    );
  }
}

/**
 * Renvoie une liste d'événements survenus lors d'une partie.
 */
function _kw_djambi_build_part_log(&$form, &$form_state, \Djambi\Battlefield $grid) {
  $html = '';
  $detailed_log = FALSE;
  $live_logs = FALSE;
  $show_logs = array();
  $turns = $grid->getTurns();
  $current_turn_id = 0;
  if ($grid->isPending() || $grid->isNotBegin()) {
    $current_turn_id = isset($turns[$grid->getCurrentTurnId()]) ? $turns[$grid->getCurrentTurnId()]['turn'] : 0;
    $detailed_log = TRUE;
    if (empty($form_state['djambi_logs'])) {
      $show_logs = array($current_turn_id - 1, $current_turn_id);
      $live_logs = TRUE;
    }
    else {
      $show_logs = range(1, $current_turn_id);
    }
  }
  elseif (!empty($form_state['djambi_logs'])) {
    $detailed_log = TRUE;
    $show_logs = $form_state['djambi_logs'];
  }
  if ($detailed_log) {
    $header = array(
      'num' => array('data' => t("#"), 'scope' => 'col'),
      'time' => array('data' => t('Time'), 'scope' => 'col'),
      'type' => array('data' => t('Type'), 'scope' => 'col'),
      'event' => array('data' => t('Event'), 'scope' => 'col'),
    );
  }
  else {
    $header = array(
      'num' => array('data' => t("Turn"), 'scope' => 'col'),
      'time' => array('data' => t('Time'), 'scope' => 'col'),
      'event' => array('data' => t('Event'), 'scope' => 'col'),
    );
  }
  if ($grid->isFinished()) {
    $header['view'] = array('data' => t('Actions'));
  }
  $buttons = array(
    '#type' => 'submit',
    '#ajax' => array('callback' => 'kw_djambi_ajax_grid_callback'),
    '#value' => t('Watch'),
    '#submit' => array('kw_djambi_game_form_replay_submit'),
  );
  $log_id = 'DjambiLog' . $grid->getId();
  $form['log']['container'] = array(
    '#type' => 'container',
    '#prefix' => '<div id="' . $log_id . '">',
    '#suffix' => '</div>',
    '#theme' => 'djambi_log',
  );
  $form['log']['container']['buttons'] = array(
    '#type' => 'actions',
  );
  $logs = array();
  $times = array();
  if ($detailed_log) {
    foreach ($grid->getMoves() as $move) {
      if (!in_array($turns[$move['turn']]['turn'], $show_logs)) {
        continue;
      }
      $msg = _kw_djambi_describe_move($move, $grid);
      if (!empty($msg)) {
        $log = array();
        $log['num'] = array(
          'data' => ($move['turn'] + 1),
          'class' => array('txtright', 'faction', 'num'),
        );
        $log['time'] = array('data' => format_date($move['time'], 'short'));
        $log['type'] = _kw_djambi_get_translatable_messages($move['type']);
        $faction = $grid->getFactionById($log['type'] == 'move' || empty($move['acting_faction']) ? $move['target_faction'] : $move['acting_faction']);
        if ($faction) {
          $log['num']['class'][] = $faction->getClass();
          $log['num']['title'] = t("Move made by !side side", array('!side' => _kw_djambi_get_translatable_messages($faction->getName())));
        }
        $log['event'] = $msg;
        if (isset($header['view'])) {
          if (!isset($form['log']['container']['buttons']['move-view-' . $move['turn']])) {
            $form['log']['container']['buttons']['move-view-' . $move['turn']] = array_merge($buttons, array(
              '#name' => 'ui-move-view-' . $move['turn'],
            ));
            $log['view'] = '[move-view-' . $move['turn'] . ']';
          }
          else {
            $log['view'] = '-';
          }
        }
        $logs[$turns[$move['turn']]['turn']][] = $log;
        $times[$turns[$move['turn']]['turn']][] = $move['time'];
      }
    }
  }
  foreach ($grid->getEvents() as $event) {
    if ((!$detailed_log && $event['type'] != 'event') || ($detailed_log && !empty($turns) && !in_array($turns[$event['turn']]['turn'], $show_logs))) {
      continue;
    }
    $log = array();
    if ($detailed_log) {
      $log['num'] = array(
        'data' => $event['turn'] + 1,
        'class' => array('txtright', 'num'),
      );
    }
    else {
      $log['num'] = array(
        'data' => t('Turn #!turn', array('!turn' => $turns[$event['turn']]['turn'])),
        'class' => array('txtright', 'num'),
      );
    }
    $log['time'] = array('data' => format_date($event['time'], 'short'));
    if ($detailed_log) {
      $log['type'] = _kw_djambi_get_translatable_messages($event['type']);
    }
    if (!empty($event['args'])) {
      $faction = NULL;
      if (isset($event['args']['faction1'])) {
        $faction = $grid->getFactionById($event['args']['faction1']);
      }
      elseif (isset($event['args']['piece'])) {
        $faction = $grid->getPieceById($event['args']['piece'])->getFaction();
      }
      if (!is_null($faction)) {
        $log['num']['class'][] = 'faction';
        $log['num']['class'][] = $faction->getClass();
        $log['num']['title'] = t("Event dealing with !side side", array('!side' => _kw_djambi_get_translatable_messages($faction->getName())));
      }
    }
    $log['event'] = _kw_djambi_describe_event($event, $grid);
    if (isset($header['view'])) {
      if ($event['type'] == 'event' && !isset($form['log']['container']['buttons']['move-view-' . $event['turn']])) {
        $form['log']['container']['buttons']['move-view-' . $event['turn']] = array_merge($buttons, array(
          '#name' => 'ui-move-view-' . $event['turn'],
        ));
        $log['view'] = '[move-view-' . $event['turn'] . ']';
      }
      else {
        $log['view'] = '-';
      }
    }
    $correction = 0;
    if ($event['event'] == 'END') {
      $correction = 5;
    }
    elseif ($event['event'] == 'THE_WINNER_IS') {
      $correction = 2;
    }
    elseif ($event['event'] == 'NEW_TURN') {
      $correction = -1;
    }
    elseif ($event['type'] == 'event') {
      $correction = 1;
    }
    if ($detailed_log) {
      $turn_id = !empty($turns) ? $turns[$event['turn']]['turn'] : 0;
      $logs[$turn_id][] = $log;
      $times[$turn_id][] = $event['time'] + $correction;
    }
    else {
      $logs[] = $log;
    }
  }
  if ($detailed_log) {
    if ($live_logs) {
      krsort($times);
    }
    foreach ($times as $turn => $time) {
      array_multisort($time, $logs[$turn]);
      if ($live_logs) {
        krsort($logs[$turn]);
      }
      $html .= theme('table', array(
        'header' => $header,
        'rows' => $logs[$turn],
        'attributes' => array('class' => array('djambilog')),
        'caption' => $turn > 0 ? t('Turn #!turn events log', array('!turn' => $turn)) : t("Early game events log"),
      ));
    }
  }
  else {
    $html .= theme('table', array(
      'header' => $header,
      'rows' => $logs,
      'attributes' => array('class' => array('djambilog')),
      'caption' => t("List of major events that marred this game"),
    ));
  }
  $form['log']['container']['history'] = array(
    '#type' => 'markup',
    '#markup' => $html,
  );
  $form['log']['container']['actions'] = array(
    '#type' => 'actions',
  );
  $ajax_log_settings = array(
    'callback' => 'kw_djambi_ajax_log_callback',
  );
  if ($grid->isPending()) {
    if ($live_logs) {
      $form['log']['container']['actions']['view_full'] = array(
        '#name' => 'ui-log-full',
        '#type' => 'submit',
        '#value' => t("View all turns log"),
        '#submit' => array('kw_djambi_game_form_log_submit'),
        '#ajax' => $ajax_log_settings,
      );
    }
    elseif ($current_turn_id > 2) {
      $form['log']['container']['actions']['view_less'] = array(
        '#name' => 'ui-log-less',
        '#type' => 'submit',
        '#value' => t("View last turns log"),
        '#submit' => array('kw_djambi_game_form_log_submit'),
        '#ajax' => $ajax_log_settings,
      );
    }
  }
  else {
    if (!$detailed_log) {
      $form['log']['container']['actions']['view_full'] = array(
        '#name' => 'ui-log-full',
        '#type' => 'submit',
        '#value' => t("View detailed log"),
        '#submit' => array('kw_djambi_game_form_log_submit'),
        '#ajax' => $ajax_log_settings,
      );
    }
    elseif (!$grid->isNotBegin()) {
      $form['log']['container']['actions']['view_less'] = array(
        '#name' => 'ui-log-less',
        '#type' => 'submit',
        '#value' => t("View major events log"),
        '#submit' => array('kw_djambi_game_form_log_submit'),
        '#ajax' => $ajax_log_settings,
      );
    }
  }
}

/**
 * Génère les tableaux de statistiques.
 */
function _kw_djambi_build_part_stats(&$form, \Djambi\Battlefield $grid) {
  $items = array();
  $factions = $grid->getFactions();
  $throne = NULL;
  $ranking = array();
  $players = array();
  if (empty($factions)) {
    return;
  }
  $faction_ids = array();
  foreach ($factions as $faction) {
    if (is_null($faction->getPlayer())) {
      continue;
    }
    if ($grid->getMode() != KW_DJAMBI_MODE_SANDBOX) {
      $username = $faction->getPlayer()->displayName();
      $players[$faction->getId()] = $username;
    }
    $ranking[$faction->getId()] = $faction->getRanking();
    $faction_ids[] = $faction->getId();
  }
  $empty_faction_stats = array_fill_keys($faction_ids, 0);
  $stats_playtime = $empty_faction_stats;
  $stats_throne = $empty_faction_stats;
  $stats_turns = $empty_faction_stats;
  $stats_distance = $empty_faction_stats;
  $stats_manipulation = $empty_faction_stats;
  $stats_necromobility = $empty_faction_stats;
  $stats_peace = $empty_faction_stats;
  $stats_murders = array_fill_keys($faction_ids, $empty_faction_stats);
  $stats_mobility = array();
  $stats_pulitzer = array();
  $stats_terminator = array();
  if (!empty($ranking)) {
    asort($ranking);
  }
  foreach ($grid->getTurns() as $turn) {
    if (!empty($turn['end'])) {
      $stats_playtime[$turn['side']] += $turn['end'] - $turn['begin'];
    }
    if ($turn['turn_scheme'] % 2 == 1) {
      $stats_throne[$turn['side']]++;
    }
  }
  foreach ($grid->getMoves() as $move) {
    if ($move['type'] == 'move') {
      $cells = $grid->getCells();
      $distance = max(
        abs($cells[$move['from']]->getX() - $cells[$move['to']]->getX()),
        abs($cells[$move['from']]->getY() - $cells[$move['to']]->getY()));
      $stats_mobility[$move['target']] = isset($stats_mobility[$move['target']]) ? $stats_mobility[$move['target']] : 0;
      $stats_mobility[$move['target']] += $distance;
      $stats_turns[$move['target_faction']]++;
      $stats_distance[$move['target_faction']] += $distance;
    }
    elseif ($move['type'] == 'murder') {
      $stats_murders[$move['acting_faction']][$move['target_faction']]++;
      $stats_peace[$move['acting_faction']]++;
      $stats_terminator[$move['acting']] = isset($stats_terminator[$move['acting']]) ? $stats_terminator[$move['acting']] : 0;
      $stats_terminator[$move['acting']]++;
      $reporter = $grid->getPieceById($move['acting']);
      if ($reporter && $reporter->getType() == 'reporter') {
        $target = $grid->getPieceById($move['target']);
        if ($target) {
          $stats_pulitzer[$move['acting']] = isset($stats_pulitzer[$move['acting']]) ? $stats_pulitzer[$move['acting']] : 0;
          $stats_pulitzer[$move['acting']] += $target->getDescription()->getValue();
        }
      }
    }
    elseif ($move['type'] == 'manipulation') {
      $stats_manipulation[$move['acting_faction']]++;
    }
    elseif ($move['type'] == 'necromobility') {
      $stats_necromobility[$move['acting_faction']]++;
    }
  }
  if ($grid->getStatus() == KW_DJAMBI_STATUS_FINISHED) {
    $header = array(
      array('data' => t('Award')),
      array('data' => t('Awarded to')),
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
      $winners[] = theme('djambi_faction_name', array('faction' => $faction))
        . (!empty($players[$winner]) ? ' ' . $players[$winner] : '');
    }
    $rows[] = array(
      'data' => array(
        array(
          'data' => count($winners) == 1 ? t('Great Beloved Ruler of Djambi') : t("No winner in this game... Last standing teams :"),
          'header' => TRUE,
        ),
        array(
          'data' => t('!pieces', array('!pieces' => implode(', ', $winners))),
          'class' => array('icon', 'light', 'evil'),
        ),
      ),
    );
    $ranking_array = array();
    foreach ($ranking as $side => $rank) {
      if (empty($rank) || $rank == $win_rank) {
        continue;
      }
      $faction = $grid->getFactionById($side);
      $ranking_array[] = array(
        'data' => t('Rank !rank :  !side', array(
          '!rank' => $rank,
          '!side' => theme('djambi_faction_name', array('faction' => $faction)) . (!empty($players[$side]) ? ' ' . $players[$side] : ''),
        )),
        'class' => array('icon', 'light', ($rank == 2 ? 'confused' : 'sad')),
      );
    }
    if (!empty($ranking_array)) {
      $rows[] = array(
        'data' => array(
          array('data' => t('Ashamed loser opponents'), 'header' => TRUE),
          array('data' => theme('item_list', array('items' => $ranking_array))),
        ),
      );
    }
    if (!empty($stats_mobility) && max($stats_mobility) > 1) {
      $rows[] = array(
        'data' => array(
          array(
            'data' => t('Sarkozy Price of hyper-activity'),
            'header' => TRUE,
          ),
          array(
            'data' => t('!pieces, with !nb cases crossed', array(
              '!pieces' => implode(', ', _kw_djambi_stats_get_winners($grid, $stats_mobility)),
              '!nb' => max($stats_mobility),
            )),
          ),
        ),
      );
    }
    if (!empty($stats_terminator) && max($stats_terminator) > 1) {
      $rows[] = array(
        'data' => array(
          array(
            'data' => t('Agent 47 Price of most efficient dirty work cleaner'),
            'header' => TRUE,
          ),
          array(
            'data' => t('!pieces, with !nb victims', array(
              '!pieces' => implode(', ', _kw_djambi_stats_get_winners($grid, $stats_terminator)),
              '!nb' => max($stats_terminator),
            )),
          ),
        ),
      );
    }
    if (($nmove = max($stats_necromobility)) > 1) {
      $rows[] = array(
        'data' => array(
          array(
            'data' => t('Pope Formose Price of best dead exhumation specialist'),
            'header' => TRUE,
          ),
          array(
            'data' => format_plural($nmove, '!pieces, with !nb exhumation', '!pieces, with !nb exhumations', array(
              '!pieces' => implode(', ', _kw_djambi_stats_get_winners($grid, $stats_necromobility, 'faction')),
              '!nb' => $nmove,
            )),
          ),
        ),
      );
    }
    if (($manipulations = max($stats_manipulation)) > 1) {
      $rows[] = array(
        'data' => array(
          array(
            'data' => t('Machiavelli Price of political manipulation'),
            'header' => TRUE,
          ),
          array(
            'data' => format_plural($manipulations, '!pieces, with !nb manipulated piece', '!pieces, with !nb manipulated pieces', array(
              '!pieces' => implode(', ', _kw_djambi_stats_get_winners($grid, $stats_manipulation, 'faction')),
              '!nb' => $manipulations,
            )),
          ),
        ),
      );
    }
    if (min($stats_peace) < 2) {
      $rows[] = array(
        'data' => array(
          array('data' => t('Nobel Price of peace'), 'header' => TRUE),
          array(
            'data' => implode(', ', _kw_djambi_stats_get_winners($grid, $stats_peace, 'faction', 'min')),
          ),
        ),
      );
    }
    if (!empty($stats_pulitzer) && max($stats_pulitzer) > 1) {
      $rows[] = array(
        'data' => array(
          array('data' => t('Pulitzer Price'), 'header' => TRUE),
          array(
            'data' => implode(', ', _kw_djambi_stats_get_winners($grid, $stats_pulitzer)),
          ),
        ),
      );

    }
    $items[] = theme('table', array(
      'header' => $header,
      'rows' => $rows,
      'caption' => t('Table of honours'),
    ));
  }
  // Tableau des victimes :
  $header = array();
  $header['Sides'] = array('data' => t('Sides'));
  $rows = array();
  $global_total = array(
    'Sides' => array('data' => 'Total', 'header' => TRUE),
  );
  $global_total_data = 0;
  $stop_status = array(
    KW_DJAMBI_FACTION_STATUS_EMPTY_SLOT,
    KW_DJAMBI_FACTION_STATUS_VASSALIZED,
  );
  foreach ($factions as $faction) {
    if (in_array($faction->getStatus(), $stop_status)) {
      continue;
    }
    $header[$faction->getId()] = array(
      'data' => '<abbr title="' . t('!faction deaths', array('!faction' => _kw_djambi_get_translatable_messages($faction->getName())))
      . '">' . $faction->getId() . '†</abbr>',
      'class' => array($faction->getClass(), 'faction', 'stats'),
    );
    $row = array();
    $row['Sides'] = array(
      'class' => array($faction->getClass(), 'faction'),
      'data' => t('!faction murders', array(
        '!faction' => _kw_djambi_get_translatable_messages($faction->getName()),
      )),
      'header' => TRUE,
    );
    $total = 0;
    /* @var $subfaction \Djambi\Faction */
    foreach ($factions as $subfaction) {
      if (in_array($subfaction->getStatus(), array(
        KW_DJAMBI_FACTION_STATUS_EMPTY_SLOT,
        KW_DJAMBI_FACTION_STATUS_VASSALIZED,
      ))
      ) {
        continue;
      }
      $murders = $stats_murders[$faction->getId()][$subfaction->getId()];
      $total += $murders;
      $std_row = array('data' => $murders, 'class' => array('number'));
      if ($grid->getOption('rule_canibalism') == 'yes' || $grid->getOption('rule_press_liberty') == 'foxnews') {
        $row[$subfaction->getId()] = $std_row;
      }
      else {
        $row[$subfaction->getId()] = ($faction->getId() != $subfaction->getId()) ? $std_row : array('data' => '-', 'class' => array('locked'));
      }
      if (!isset($global_total[$subfaction->getId()])) {
        $global_total[$subfaction->getId()] = array(
          'data' => 0,
          'class' => array('total', 'number'),
        );
      }
      $global_total[$subfaction->getId()]['data'] += $murders;
    }
    $row['Total'] = array(
      'data' => $total,
      'class' => array('total', 'number'),
    );
    $global_total_data += $total;
    $rows[] = $row;
  }
  $global_total['Total'] = array(
    'data' => $global_total_data,
    'class' => array('number'),
  );
  $rows[] = array('data' => $global_total, 'class' => array('total'));
  $header['Total'] = array('data' => t('Total'));
  $items[] = theme('table', array(
    'header' => $header,
    'rows' => $rows,
    'caption' => t('Table of dead pieces'),
  ));
  // Tableau des déplacements :
  $header = array(
    0 => array('data' => t('Sides')),
    6 => array('data' => t('Status')),
    1 => array('data' => t('Moves')),
    2 => array('data' => t('Distance')),
    3 => array('data' => t('Rule length')),
    4 => array('data' => t('Play time')),
    5 => array('data' => '<abbr title="' . t("Average turn duration") . '">' . t('Avg t.d.') . '</abbr>'),
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
      5 => array('data' => '-', 'class' => array('number')),
    ),
  );
  foreach ($factions as $faction) {
    if (in_array($faction->getStatus(), array(
      KW_DJAMBI_FACTION_STATUS_EMPTY_SLOT,
      KW_DJAMBI_FACTION_STATUS_VASSALIZED,
    ))
    ) {
      continue;
    }
    $row = array();
    $row[0] = array(
      'data' => t('!faction', array(
        '!faction' => _kw_djambi_get_translatable_messages($faction->getName()),
      )),
      'class' => array($faction->getClass(), 'faction', 'stats'),
      'header' => TRUE,
    );
    $row[6] = array('data' => _kw_djambi_get_translatable_messages($faction->getStatus()));
    $row[1] = array(
      'data' => $stats_turns[$faction->getId()],
      'class' => 'number',
    );
    $total['data'][1]['data'] += $row[1]['data'];
    $row[2] = array(
      'data' => $stats_distance[$faction->getId()],
      'class' => 'number',
    );
    $total['data'][2]['data'] += $row[2]['data'];
    $row[3] = array(
      'data' => $stats_throne[$faction->getId()],
      'class' => 'number',
    );
    $total['data'][3]['data'] += $row[3]['data'];
    $row[4] = array(
      'data' => _kw_djambi_get_duration($stats_playtime[$faction->getId()]),
      'class' => 'number',
    );
    if (isset($stats_playtime[$faction->getId()])) {
      $total['data'][4]['data'] += $stats_playtime[$faction->getId()];
    }
    $row[5] = array(
      'data' => !empty($stats_turns[$faction->getId()]) ?
      _kw_djambi_get_duration($stats_playtime[$faction->getId()] / $stats_turns[$faction->getId()]) : '-',
      'class' => 'number',
    );
    $rows[] = $row;
  }
  if ($total['data'][1]['data'] > 0) {
    $total['data'][5]['data'] = _kw_djambi_get_duration($total['data'][4]['data'] / $total['data'][1]['data']);
  }
  $total['data'][4]['data'] = _kw_djambi_get_duration($total['data'][4]['data']);
  $rows[] = $total;
  $items[] = theme('table', array(
    'header' => $header,
    'rows' => $rows,
    'caption' => t('Table of moves'),
  ));
  $form['stats']['items'] = array(
    '#markup' => theme('item_list', array(
      'items' => $items,
      'attributes' => array('class' => array('stats')),
    )),
  );
}

/**
 * Aide à la génération des statistiques : détermine le vainqueur d'un prix.
 */
function _kw_djambi_stats_get_winners(\Djambi\Battlefield $grid, $stats, $type = 'piece', $order = 'max') {
  $winners = array_keys($stats, $order == 'min' ? min($stats) : max($stats));
  $winners_string_array = array();
  foreach ($winners as $winner) {
    if ($type == 'piece') {
      $piece = $grid->getPieceById($winner);
      $winners_string_array[] = theme('djambi_piece_name', array(
        'piece' => $piece,
        'use_faction_class' => TRUE,
      ));
    }
    elseif ($type == 'faction') {
      $faction = $grid->getFactionById($winner);
      $winners_string_array[] = theme('djambi_faction_name', array('faction' => $faction));
    }
  }
  return $winners_string_array;
}

/**
 * Soumission de formulaire : chargement des logs complets.
 */
function kw_djambi_game_form_log_submit($form, &$form_state) {
  $form_state['rebuild'] = TRUE;
  $clicked_button = $form_state['clicked_button'];
  /* @var $grid \Djambi\Battlefield */
  $grid = $form['grid']['#grid'];
  $form_state['djambi_logs'] = array();
  if ($clicked_button['#name'] == 'ui-log-full') {
    foreach ($grid->getTurns() as $turn) {
      $form_state['djambi_logs'][] = $turn['turn'];
    }
  }
}

/**
 * Fonction appelée après une requête Ajax : regénération du formulaire de jeu.
 */
function kw_djambi_ajax_callback($form, $form_state) {
  return $form;
}

/**
 * Rechargement AJAX uniquement de la grille.
 */
function kw_djambi_ajax_grid_callback($form, $form_state) {
  /* @var $grid \Djambi\Battlefield */
  $grid = $form['grid']['#grid'];
  $commands = array();
  $commands[] = ajax_command_replace('#DjambiGridFieldset' . $grid->getId(), drupal_render($form['grid']));
  $commands[] = ajax_command_invoke('#DjambiGrid' . $grid->getId(), 'scrollTo');
  return array('#type' => 'ajax', '#commands' => $commands);
}

/**
 * Fonction appelée après une requête Ajax : chargement des logs.
 */
function kw_djambi_ajax_log_callback($form, $form_state) {
  /* @var $grid \Djambi\Battlefield */
  $grid = $form['grid']['#grid'];
  $selector = '#DjambiLog' . $grid->getId();
  $commands = array();
  $commands[] = ajax_command_replace($selector, drupal_render($form['log']['container']));
  $commands[] = ajax_command_invoke($selector, 'scrollTo');
  return array('#type' => 'ajax', '#commands' => $commands);
}

/**
 * Soumission du formulaire de jeu.
 */
function kw_djambi_game_form_submit($form, &$form_state) {
  $change_turn = FALSE;
  /* @var $grid \Djambi\Battlefield */
  $grid = $form['grid']['#grid'];
  $form_state['rebuild'] = TRUE;
  switch ($form_state['kw_djambi']['phase']) {
    case('interaction_result'):
      $interaction = current($form_state['kw_djambi']['interactions']);
      /* @var $piece \Djambi\Piece */
      $piece = $form_state['kw_djambi']['selected_piece'];
      $grid = $piece->getBattlefield();
      if ($interaction['type'] == 'murder') {
        $victim_piece = $interaction['target'];
        $piece->kill($victim_piece, $grid->findCellByName($form_state['kw_djambi']['selected_interaction']));
      }
      elseif ($interaction['type'] == 'necromobility') {
        $victim_piece = $interaction['target'];
        $piece->necromove($victim_piece, $grid->findCellByName($form_state['kw_djambi']['selected_interaction']));
      }
      elseif ($interaction['type'] == 'manipulation') {
        $manipulated_piece = $interaction['target'];
        $piece->manipulate($manipulated_piece, $grid->findCellByName($form_state['kw_djambi']['selected_interaction']));
      }
      elseif ($interaction['type'] == 'reportage') {
        $cells = $piece->getBattlefield()->getCells();
        $victim = $cells[$form_state['kw_djambi']['selected_interaction']]->getOccupant();
        $piece->kill($victim, $grid->findCellByName($form_state['kw_djambi']['selected_interaction']));
      }
      elseif ($interaction['type'] == 'throne_evacuation') {
        $piece->evacuate($grid->findCellByName($form_state['kw_djambi']['selected_interaction']));
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

    case('move_result'):
      /* @var $piece \Djambi\Piece */
      $piece = $form_state['kw_djambi']['selected_piece'];
      $grid = $piece->getBattlefield();
      $move_results = $piece->move($grid->findCellByName($form_state['kw_djambi']['selected_destination']));
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
    $grid->changeTurn();
    $form_state['saved_grid'] = $grid->getGameManager()->save();
  }
  $form_state['kw_djambi']['change_turn'] = $change_turn;
}

/**
 * Soumission de formulaire : annulation de la sélection d'une pièce.
 */
function kw_djambi_game_form_cancel_selection_submit($form, &$form_state) {
  $form_state['rebuild'] = TRUE;
  if (isset($form_state['kw_djambi'])) {
    unset($form_state['kw_djambi']);
  }
}

/**
 * Soumission du bouton d'annulation du dernier tour du jeu.
 */
function kw_djambi_game_form_cancel_last_turn_submit($form, &$form_state) {
  $form_state['rebuild'] = TRUE;
  $form_state['kw_djambi']['change_turn'] = TRUE;
  /* @var $grid \Djambi\Battlefield */
  $grid = $form['grid']['#grid'];
  $grid->cancelLastTurn();
  $form_state['saved_grid'] = $grid->getGameManager()->save();
}

/**
 * Soumission du bouton "passer son tour".
 */
function kw_djambi_game_form_skip_turn_submit($form, &$form_state) {
  $form_state['rebuild'] = TRUE;
  /* @var $grid \Djambi\Battlefield */
  $grid = $form['grid']['#grid'];
  $faction = $grid->getPlayingFaction();
  $faction->skipTurn();
  $grid = $faction->getBattlefield();
  $form_state['saved_grid'] = $grid->getGameManager()->save();
}

/**
 * Soumission du bouton "Abandonner la partie".
 */
function kw_djambi_game_form_withdraw_submit($form, &$form_state) {
  $form_state['rebuild'] = TRUE;
  /* @var $grid \Djambi\Battlefield */
  $grid = $form['grid']['#grid'];
  $faction = $grid->getPlayingFaction();
  $faction->withdraw();
  $grid = $faction->getBattlefield();
  $grid->changeTurn();
  $form_state['saved_grid'] = $grid->getGameManager()->save();
}

/**
 * Soumission du bouton "Revenir dans la partie".
 */
function kw_djambi_game_form_comeback_submit($form, &$form_state) {
  $form_state['rebuild'] = TRUE;
  /* @var $grid \Djambi\Battlefield */
  $grid = $form['grid']['#grid'];
  $clicked_button = $form_state['clicked_button']['#parents'][0];
  $id = substr($clicked_button, strpos($clicked_button, '_') + 1);
  $faction = $grid->getFactionById($id);
  $faction->comeBackAfterWithdraw();
  $grid = $faction->getBattlefield();
  $form_state['saved_grid'] = $grid->getGameManager()->save();
}

/**
 * Soumission du bouton "Proposer une partie nulle".
 */
function kw_djambi_game_form_draw_proposal_submit($form, &$form_state) {
  $form_state['rebuild'] = TRUE;
  /* @var $grid \Djambi\Battlefield */
  $grid = $form['grid']['#grid'];
  $faction = $grid->getPlayingFaction();
  $faction->callForADraw();
  $grid = $faction->getBattlefield();
  $form_state['saved_grid'] = $grid->getGameManager()->save();
}

/**
 * Soumission du bouton "Accepter une partie nulle".
 */
function kw_djambi_game_form_accept_peace_submit($form, &$form_state) {
  $form_state['rebuild'] = TRUE;
  /* @var $grid \Djambi\Battlefield */
  $grid = $form['grid']['#grid'];
  $faction = $grid->getPlayingFaction();
  $faction->acceptDraw();
  $grid = $faction->getBattlefield();
  $form_state['saved_grid'] = $grid->getGameManager()->save();
}

/**
 * Soumission du bouton "Refuser une partie nulle".
 */
function kw_djambi_game_form_reject_peace_submit($form, &$form_state) {
  $form_state['rebuild'] = TRUE;
  /* @var $grid \Djambi\Battlefield */
  $grid = $form['grid']['#grid'];
  $faction = $grid->getPlayingFaction();
  $faction->rejectDraw();
  $form_state['saved_grid'] = $grid->getGameManager()->save();
}

/**
 * Soumission du bouton "Afficher/cacher les boutons derniers déplacements".
 */
function kw_djambi_game_form_show_moves_submit($form, &$form_state) {
  global $user;
  $form_state['rebuild'] = TRUE;
  $form_state['show_moves'] = !$form_state['values']['show_moves'];
  $form_state['pause'] = TRUE;
  if ($user->uid == 0 || isset($_SESSION['djambi']['show_moves'])) {
    $_SESSION['djambi']['show_moves'] = !$form_state['values']['show_moves'];
  }
  if ($user->uid > 0) {
    $edit['data']['djambi_show_moves'] = !$form_state['values']['show_moves'];
    user_save($user, $edit);
  }
}

/**
 * Soumission du bouton "Afficher l'aide".
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
 * Soumission du bouton "Revoir la partie".
 */
function kw_djambi_game_form_replay_submit($form, &$form_state) {
  $form_state['rebuild'] = TRUE;
  /* @var $grid \Djambi\Battlefield */
  $grid = $form['grid']['#grid'];
  $back_to_live = FALSE;
  if ($form_state['clicked_button']['#name'] == 'ui-replay-pause') {
    $form_state['autoplay'] = FALSE;
    $form_state['pause'] = TRUE;
    return;
  }
  if ($form_state['clicked_button']['#name'] == 'ui-replay-autoplay') {
    $form_state['autoplay'] = TRUE;
    $form_state['show_replay']++;
    return;
  }
  if (!isset($form_state['autoplay'])) {
    $form_state['autoplay'] = FALSE;
  }
  if (substr($form_state['clicked_button']['#name'], 0, strlen('ui-move-view')) == 'ui-move-view') {
    $form_state['show_replay'] = substr($form_state['clicked_button']['#name'], strlen('ui-move-view-'));
    return;
  }
  if (!isset($form_state['show_replay']) && $form_state['clicked_button']['#name'] == 'ui-replay-backward') {
    if ($grid->isPending()) {
      $form_state['show_replay'] = $grid->getCurrentTurnId() - count($form_state['values']['showable_turns']);
      $form_state['autoplay'] = TRUE;
    }
    else {
      $form_state['show_replay'] = $grid->getCurrentTurnId();
    }
  }
  elseif (!isset($form_state['show_replay']) || $form_state['clicked_button']['#name'] == 'ui-replay-begin') {
    $form_state['show_replay'] = 0;
    $form_state['autoplay'] = TRUE;
  }
  elseif ($form_state['clicked_button']['#name'] == 'ui-replay-backward') {
    if ($form_state['show_replay'] > 0) {
      $form_state['show_replay']--;
      $form_state['autoplay'] = FALSE;
    }
  }
  elseif ($form_state['clicked_button']['#name'] == 'ui-replay-end') {
    $back_to_live = TRUE;
    unset($form_state['autoplay']);
  }
  else {
    if (($grid->isFinished() && $form_state['show_replay'] < $grid->getCurrentTurnId())
      || ($grid->isPending() && $form_state['show_replay'] < $grid->getCurrentTurnId() - 1)
    ) {
      $form_state['show_replay']++;
    }
    else {
      $back_to_live = TRUE;
    }
  }
  if ($back_to_live) {
    unset($form_state['show_replay']);
  }
}

/**
 * Soumission du bouton "Actualiser".
 */
function kw_djambi_game_form_refresh_submit($form, &$form_state) {
  $form_state['rebuild'] = TRUE;
  /* @var $grid \Djambi\Battlefield */
  $grid = $form['grid']['#grid'];
  $faction = $grid->getPlayingFaction();
  if (!empty($faction) && $faction->getPlayer() instanceof \Djambi\Players\ComputerPlayer) {
    $ia = $faction->getPlayer()->getIa();
    $ia->play();
    $grid->changeTurn();
    $form_state['saved_grid'] = $grid->getGameManager()->save();
    if ($_GET['q'] == 'system/ajax') {
      $form_state['show_replay'] = $grid->isFinished() ? $grid->getCurrentTurnId() : $grid->getCurrentTurnId() - 1;
      $form_state['autoplay'] = TRUE;
    }
  }
}

/**
 * Soumission du bouton "Rejoindre une partie".
 */
function kw_djambi_game_form_participate_submit($form, &$form_state) {
  $context = \Drupal\kw_djambi\Djambi\DjambiContext::getInstance();
  $form_state['kw_djambi']['change_turn'] = TRUE;
  /* @var \Djambi\Battlefield $grid */
  $grid = $form['grid']['#grid'];
  $gm = $grid->getGameManager();
  if (!is_null($gm)) {
    $grid = $gm->reload()->getBattlefield();
  }
  if ($grid->getStatus() != KW_DJAMBI_STATUS_RECRUITING) {
    return;
  }
  $form_state['rebuild'] = TRUE;
  $allow_anonymous = $grid->getOption('allow_anonymous_players');
  if (!$allow_anonymous && !$context->getCurrentUser() instanceof \Drupal\kw_djambi\Djambi\Players\DrupalAnonymousPlayer) {
    drupal_set_message(t('Only registered players can join this game. Please !login or !register.', array(
      '!login' => l(t('login to your account'), 'user/login'),
      '!register' => l(t('create a new account'), 'user/register'),
    )), 'error');
    return;
  }
  $clicked_button = $form_state['clicked_button'];
  $faction_id = $clicked_button['#array_parents'][2];
  $nb_empty_factions = 0;

  /* @var $faction \Djambi\Faction */
  foreach ($grid->getFactions() as $faction) {
    if ($faction->getId() != $faction_id) {
      if ($context->checkUserPlayingFaction($faction)) {
        $faction->removePlayer();
      }
      if ($faction->getStatus() == KW_DJAMBI_FACTION_STATUS_EMPTY_SLOT) {
        $nb_empty_factions++;
      }
    }
  }
  $faction = $grid->getFactionById($faction_id);
  if ($faction->getStatus() == KW_DJAMBI_FACTION_STATUS_EMPTY_SLOT) {
    $player = $context->getCurrentUser(TRUE);
    $faction->changePlayer($player);
    if (is_null($player->getLastSignal())) {
      \Djambi\Signal::createSignal($player, $context->getIp());
    }
    if ($nb_empty_factions == 0) {
      $grid->setStatus(KW_DJAMBI_STATUS_PENDING);
    }
    $grid->logEvent('info', 'NEW_TEAM', array(
      'faction1' => $faction->getId(),
      '!user_id' => $context->getCurrentUser()->getUser()->uid,
      '!user_ip' => $context->getIp(),
    ));
    drupal_set_message(t('You are now playing the !color team.',
      array('!color' => _kw_djambi_get_translatable_messages($faction->getName()))));
    $form_state['saved_grid'] = $grid->getGameManager()->save();
  }
  else {
    drupal_set_message(t('This faction is used by an other player.'), 'error');
  }
}

/**
 * Soumission du bouton "Quitter la partie".
 */
function kw_djambi_game_form_cancel_participation_submit($form, &$form_state) {
  /* @var $grid \Djambi\Battlefield */
  $grid = $form['grid']['#grid'];
  if ($grid->getStatus() == KW_DJAMBI_STATUS_RECRUITING) {
    $nb_playing_factions = 0;
    $context = \Drupal\kw_djambi\Djambi\DjambiContext::getInstance();
    /* @var $faction \Djambi\Faction */
    foreach ($grid->getFactions() as $faction) {
      if ($context->checkUserPlayingFaction($faction)) {
        $faction->removePlayer();
      }
      if ($faction->getStatus() == KW_DJAMBI_FACTION_STATUS_READY) {
        $nb_playing_factions++;
      }
    }
    if ($nb_playing_factions == 0) {
      $grid->getGameManager()->delete();
    }
    else {
      global $user;
      $grid->logEvent('info', 'TEAM_EXIT', array(
        '!user_id' => $user->uid,
        '!user_ip' => ip_address(),
      ));
      $form_state['saved_grid'] = $grid->getGameManager()->save();
    }
    drupal_set_message(t('You just left Djambi game #!nb', array(
      '!nb' => $grid->getGameManager()->getInfo('sequence'),
    )));
    drupal_goto('<front>');
  }
}

/**
 * Validation de la sélection d'une pièce.
 *
 * @param array $errors
 *   Tableau associatif contenant les erreurs d'un formulaire
 * @param string $piece_id
 *   Identifiant d'une pièce
 * @param \Djambi\Battlefield $grid
 *   Grille de Djambi
 *
 * @return \Djambi\Piece
 *   Renvoie la pièce sélectionnée si correct, FALSE sinon.
 */
function kw_djambi_piece_selection_validate(&$errors, $piece_id, \Djambi\Battlefield $grid) {
  $errors = array();
  $piece_form_locator = "grid[pieces][movable][" . $piece_id;
  $faction = $grid->getPlayingFaction();
  $context = \Drupal\kw_djambi\Djambi\DjambiContext::getInstance();
  if (!empty($faction) && !$context->checkUserPlayingFaction($faction)) {
    $errors[] = array(
      "location" => $piece_form_locator,
      "msg" => t("You do not own the !faction pieces.", array(
        "!faction",
        _kw_djambi_get_translatable_messages($faction->getName()),
      )),
    );
    return FALSE;
  }
  $pieces = $faction->getControlledPieces();
  if (!isset($pieces[$piece_id])) {
    $errors[] = array(
      "location" => $piece_form_locator,
      "msg" => t("Unrecognized piece."),
    );
    return FALSE;
  }
  $piece = $pieces[$piece_id];
  if (!$piece->isMovable()) {
    $errors = array(
      "location" => $piece_form_locator,
      "msg" => t("The piece !piece cannot be moved now.", array("!piece" => _kw_djambi_get_full_piece_name($piece))),
    );
  }
  if (!empty($errors)) {
    return FALSE;
  }
  return $piece;
}

/**
 * Validation des actions sur la grille de jeu.
 */
function kw_djambi_game_form_validate($form, &$form_state) {
  /* @var $grid \Djambi\Battlefield */
  $grid = $form['grid']["#grid"];
  $errors = array();
  // Détection du bouton cliqué :
  $button = $form_state["triggering_element"];
  $type_button = isset($button["#array_parents"][2]) ? $button["#array_parents"][2] : NULL;
  // Traitement des différents cas :
  if ($type_button == "movable") {
    $piece = kw_djambi_piece_selection_validate($errors, $button["#return_value"], $grid);
    if ($piece && isset($form_state["kw_djambi"]["selected_piece"])) {
      /* @var $selected_piece \Djambi\Piece */
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
      $evacuation = kw_djambi_piece_evacuation_validate($errors, $selected_piece, $button["#return_value"], $button["#name"]);
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
 * Validation du déplacement d'une pièce.
 */
function kw_djambi_piece_destination_validate(&$errors, \Djambi\Piece $piece, $destination, $button_name) {
  $move = FALSE;
  foreach ($piece->getAllowableMovesNames() as $allowable_move) {
    if ($destination == $allowable_move) {
      $move = $destination;
      break;
    }
  }
  if (!$move && !is_null($button_name)) {
    $errors[] = array(
      "location" => "pieces[destination][" . $button_name,
      "msg" => t("You are not allowed to move !piece into !case case.", array(
        "!piece" => $piece->getLongname("t"),
        "!case" => $destination,
      )),
    );
  }
  return $move;
}

/**
 * Validation du meurtre d'une pièce.
 */
function kw_djambi_piece_murder_validate(&$errors, \Djambi\Battlefield $grid, \Djambi\Piece $murderer, \Djambi\Piece $victim, $destination, $button_name) {
  if (!$victim->isAlive()) {
    $errors[] = array(
      "location" => "pieces[destination][" . $button_name,
      "msg" => t("Your victim seems to be already dead."),
    );
    return FALSE;
  }
  $can_attack = $murderer->checkAttackingPossibility($victim);
  if (!$can_attack) {
    $errors[] = array(
      "location" => "pieces[destination][" . $button_name,
      "msg" => t("You are not allowed to kill this piece."),
    );
    return FALSE;
  }
  $free_cells = $grid->getFreeCells($victim);
  if (!in_array($destination, $free_cells)) {
    $errors[] = array(
      "location" => "pieces[destination][" . $button_name,
      "msg" => t("You cannot place bury your victim here, case !case is unavailable.", array("!case" => $destination)),
    );
    return FALSE;
  }
  return TRUE;
}

/**
 * Validation du reportage sur une pièce.
 */
function kw_djambi_piece_reportage_validate(&$errors, \Djambi\Battlefield $grid, \Djambi\Piece $piece, $destination, $button_name) {
  $cells = $grid->getCells();
  if (!isset($cells[$destination])) {
    $errors[] = array(
      "location" => "pieces[destination][" . $button_name,
      "msg" => t("The reportage location is totally incorrect."),
    );
    return FALSE;
  }
  $occupant = $cells[$destination]->getOccupant();
  if (empty($occupant)) {
    $errors[] = array(
      "location" => "pieces[destination][" . $button_name,
      "msg" => t("There is nobody here (!case).", array("!case" => $destination)),
    );
    return FALSE;
  }
  if (!$occupant->isAlive()) {
    $errors[] = array(
      "location" => "pieces[destination][" . $button_name,
      "msg" => t("Already dead, too late for a reportage."),
    );
    return FALSE;
  }
  if ($grid->getOption('rule_press_liberty') == 'pravda'
    && $occupant->getFaction()->getControl()->getId() == $piece->getFaction()->getControl()->getId()) {
    $errors[] = array(
      "location" => "pieces[destination][" . $button_name,
      "msg" => t("It is not a good idea to make a reportage about your own team evil actions."),
    );
    return FALSE;
  }
  return TRUE;
}

/**
 * Validation de la manipulation d'une pièce.
 */
function kw_djambi_piece_manipulation_validate(&$errors, \Djambi\Battlefield $grid, \Djambi\Piece $diplomat, \Djambi\Piece $manipulated, $destination, $button_name) {
  if (!$manipulated->isAlive()) {
    $errors[] = array(
      "location" => "pieces[destination][" . $button_name,
      "msg" => t("You cannot manipulate a dead piece (use a necromobil for this disgusting work)."),
    );
    return FALSE;
  }
  $can_manipulate = $diplomat->checkManipulatingPossibility($manipulated);
  if (!$can_manipulate) {
    $errors[] = array(
      "location" => "pieces[destination][ " . $button_name,
      "msg" => t("You cannot manipulate this piece."),
    );
    return FALSE;
  }
  $free_cells = $grid->getFreeCells($manipulated);
  if (!in_array($destination, $free_cells)) {
    $errors[] = array(
      "location" => "pieces[destination][" . $button_name,
      "msg" => t("You cannot place the manipulated piece here, case !case is unavailable.", array("!case" => $destination)),
    );
    return FALSE;
  }
  return TRUE;
}

/**
 * Validation du déplacement d'un mort.
 */
function kw_djambi_piece_necromobility_validate(&$errors, \Djambi\Battlefield $grid, \Djambi\Piece $piece, $destination, $button_name) {
  if ($piece->isAlive()) {
    $errors[] = array(
      "location" => "pieces[destination][" . $button_name,
      "msg" => t("This dead body is still breathing..."),
    );
    return FALSE;
  }
  $free_cells = $grid->getFreeCells($piece);
  if (!in_array($destination, $free_cells)) {
    $errors[] = array(
      "location" => "pieces[destination][" . $button_name,
      "msg" => t("You cannot place the dead piece here, case !case is unavailable.", array("!case" => $destination)),
    );
    return FALSE;
  }
  return TRUE;
}

/**
 * Validation de l'évacuation d'un pion.
 */
function kw_djambi_piece_evacuation_validate(&$errors, \Djambi\Piece $piece, $destination, $button_name) {
  return kw_djambi_piece_destination_validate($errors, $piece, $destination, $button_name);
}
