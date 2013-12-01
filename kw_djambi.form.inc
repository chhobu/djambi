<?php
/**
 * @file
 * Contient les fonctions de gestion du formulaire principal de Djambi
 *   (grille de jeu).
 */

use Djambi\Battlefield;
use Djambi\Exceptions\DisallowedActionException;
use Djambi\Exceptions\FactionNotFoundException;
use Djambi\Exceptions\PieceNotFoundException;
use Djambi\Faction;
use Djambi\GameManager;
use Djambi\Move;
use Djambi\Moves\Manipulation;
use Djambi\Moves\Murder;
use Djambi\Moves\Necromobility;
use Djambi\Moves\Reportage;
use Djambi\Moves\ThroneEvacuation;
use Djambi\Players\ComputerPlayer;
use Djambi\Signal;
use Djambi\Stores\StandardRuleset;
use Drupal\kw_djambi\Djambi\DjambiContext;
use Djambi\Exceptions\PlayerInvalidException;

/**
 * Génère le formulaire de jeu de Djambi.
 */
function _kw_djambi_build_game_form(&$form, &$form_state, GameManager $game, $options = NULL) {
  $context = DjambiContext::getInstance();
  $grid = $game->getBattlefield();
  $show_log = isset($options['show_log']) ? $options['show_log'] : TRUE;
  $show_rules = isset($options['show_rules']) ? $options['show_rules'] : TRUE;
  $show_stats = isset($options['show_stats']) ? $options['show_stats'] : TRUE;
  $current_user_faction = $context->getUserFaction($grid);
  if ($game->isFinished() && !empty($current_user_faction)) {
    $form_state['djambi']['result'] = $current_user_faction->getStatus();
  }
  if (isset($form_state['show_replay'])) {
    $grid->viewTurnHistory($form_state['show_replay']);
    $replay = TRUE;
  }
  else {
    $replay = FALSE;
  }
  if (!$replay && !$game->isFinished()) {
    $is_refresh_modes = !in_array($game->getMode(), array(
      GameManager::MODE_SANDBOX,
      GameManager::MODE_TRAINING,
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
  $form['#attached']['css'][] = drupal_get_path('module', 'kw_djambi') . '/css/kw_djambi.css';
  $form['#attached']['js'][] = drupal_get_path('module', 'kw_djambi') . '/js/kw_djambi.js';
  $ajax_settings = array(
    'callback' => 'kw_djambi_ajax_callback',
    'wrapper' => 'DjambiContainer' . $game->getId(),
    'effect' => 'none',
    'method' => 'replace',
  );
  // Création de l'ossature du formulaire :
  $do_refresh = 'no';
  if (!$game->isFinished() && !$replay) {
    if ($game->isNotBegin()) {
      $do_refresh = 'check';
    }
    elseif (is_null($current_user_faction)) {
      $do_refresh = 'check';
    }
    elseif ($faction && !is_null($faction->getPlayer()) && !$faction->getPlayer()->isHuman()) {
      $do_refresh = 'force';
    }
    elseif ($faction && $game->getMode() != GameManager::MODE_SANDBOX) {
      $do_refresh = $faction->getId() != $current_user_faction->getId() ? 'check' : 'minimal';
    }
  }
  $grid_id = 'DjambiContainer' . $game->getId();
  $attributes = array(
    'id' => 'DjambiGridFieldset' . $game->getId(),
    'class' => array('djambi'),
    'data-version' => $game->getChanged(),
    'data-status' => $game->getStatus(),
    'data-refresh' => $do_refresh,
    'data-user-faction' => (!empty($current_user_faction) ? $current_user_faction->getId() : '0'),
    'data-user-status' => (!empty($current_user_faction) ? $current_user_faction->getStatus() : '0'),
    'data-turn' => $grid->getCurrentTurnId(),
  );
  $nid = $game->getInfo('nid');
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
  $sequence = $game->getInfo('sequence');
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
    '#game' => $game,
    '#theme' => $game->isNotBegin() ? 'djambi_recruiting_phase' : 'djambi_grid',
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
  if ($show_rules) {
    $form['options'] = array(
      '#type' => 'fieldset',
      '#title' => t('Game configuration'),
      '#weight' => 4,
      '#collapsible' => TRUE,
      '#collapsed' => $game->isNotBegin() ? FALSE : TRUE,
    );
  }
  if ($show_stats && !$game->isNotBegin()) {
    $form['stats'] = array(
      '#type' => 'fieldset',
      '#title' => $game->getStatus() == GameManager::STATUS_FINISHED ? t('Stats and awards') : t('Stats'),
      '#weight' => 5,
    );
  }
  if ($show_log) {
    $form['log'] = array(
      '#type' => 'fieldset',
      '#title' => $game->isNotBegin() ? t('Recruitment log') : t('Treachery log'),
      '#weight' => 10,
      '#collapsible' => TRUE,
      '#collapsed' => FALSE,
    );
  }
  // Recrutement des joueurs...
  if ($game->isNotBegin()) {
    _kw_djambi_build_part_recruiting($form, $grid, $current_user_faction, $ajax_settings);
  }
  else {
    // Contrôle de l'affichage de l'aide et des derniers mouvements :
    _kw_djambi_build_part_move_controls($form, $form_state, $game);
  }
  // Affichage du récapitulatif des règles
  if ($show_rules) {
    _kw_djambi_build_part_rules($form, $game);
  }
  if (!isset($form_state['show_replay']) && $game->isPending()) {
    // Interface de réponse sur proposition de partie nulle :
    if ($faction && $game->getStatus() == GameManager::STATUS_DRAW_PROPOSAL) {
      _kw_djambi_build_part_draw_proposal($form['grid'], $grid, $faction, $ajax_settings, $is_user_playing_current_faction);
    }
    elseif ($faction && $game->getStatus() == GameManager::STATUS_PENDING && $is_user_playing_current_faction) {
      _kw_djambi_build_part_player_actions($form, $form_state, $grid, $faction, $ajax_settings);
      _kw_djambi_build_part_pieces_controls($form['grid'], $form_state, $grid, $faction, $ajax_settings);
    }
    elseif (!is_null($faction->getControl()->getPlayer()) && !$faction->getControl()->getPlayer()->isHuman()) {
      _kw_djambi_build_part_computer_actions($form, $ajax_settings);
    }
  }
  // Log des événements :
  if ($show_log && !empty($form['log'])) {
    _kw_djambi_build_part_log($form, $form_state, $game);
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
  $form["#action"] = "#DjambiGrid" . $game->getId();
}

/**
 * Interface d'attente de nouveaux joueurs.
 */
function _kw_djambi_build_part_recruiting(&$form, Battlefield $grid, $current_user_faction, $ajax_settings) {
  $context = DjambiContext::getInstance();
  $allow_anonymous = $grid->getGameManager()->getOption(StandardRuleset::GAMEPLAY_ELEMENT_ANONYMOUS_PLAYERS);
  $reject = FALSE;
  $form['grid']['factions'] = array(
    '#type' => 'container',
  );
  foreach ($grid->getFactions() as $faction) {
    if ($faction->getStatus() == Faction::STATUS_VASSALIZED) {
      continue;
    }
    $form['grid']['factions'][$faction->getId()]['label'] = array(
      '#type' => 'item',
      '#title' => theme('djambi_faction_name', array('faction' => $faction)),
      '#markup' => $faction->getStatus() == Faction::STATUS_EMPTY_SLOT ?
      t('Empty') : $faction->getPlayer()->displayName(),
    );
    $me = $context->getCurrentUser()->isPlayingFaction($faction);
    if ($faction->getStatus() != Faction::STATUS_EMPTY_SLOT) {
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
    elseif ($faction->getStatus() == Faction::STATUS_EMPTY_SLOT) {
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
function _kw_djambi_build_part_move_controls(&$form, &$form_state, GameManager $gm) {
  global $user;
  $current_turn_id = $gm->getBattlefield()->getCurrentTurnId();
  $show_moves = $gm->isPending();
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
  if (!$gm->isFinished()) {
    $turns = $gm->getBattlefield()->getTurns();
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
    if ($gm->isFinished()) {
      $form['grid']['controls']['replay'] = array_merge($ui_buttons, array(
        '#name' => 'ui-replay',
        '#value' => '<< ' . t('Review this game'),
        '#submit' => array('kw_djambi_game_form_replay_submit'),
      ));
    }
    $form['grid']['controls']['previous_turn'] = array_merge($ui_buttons, array(
      '#name' => 'ui-replay-backward',
      '#value' => '< ' . ($gm->isFinished() ? t('Show previous move') : t('Show previous moves')),
      '#submit' => array('kw_djambi_game_form_replay_submit'),
      '#disabled' => $current_turn_id > 0 ? FALSE : TRUE,
    ));
  }
  else {
    if ($gm->isFinished()) {
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
      '#disabled' => (($gm->isPending() && $current_turn_id - 1 <= $form_state['show_replay']) ||
        ($gm->isFinished() && $current_turn_id <= $form_state['show_replay']) ? TRUE : FALSE),
    ));
    $form['grid']['controls']['next_turn'] = array_merge($ui_buttons, array(
      '#name' => 'ui-replay-forward',
      '#value' => t('Show next move') . ' >',
      '#submit' => array('kw_djambi_game_form_replay_submit'),
      '#disabled' => (($gm->isPending() && $current_turn_id - 1 <= $form_state['show_replay']) ||
        ($gm->isFinished() && $current_turn_id <= $form_state['show_replay']) ? TRUE : FALSE),
    ));
    $form['grid']['controls']['last_turn'] = array_merge($ui_buttons, array(
      '#name' => 'ui-replay-end',
      '#value' => $gm->isPending() ? t('Go back to current turn') . ' >>' : t('Go back to final positions') . ' >>',
      '#submit' => array('kw_djambi_game_form_replay_submit'),
    ));
  }
  if ($gm->isPending() || isset($form_state['show_replay'])) {
    $form['grid']['controls']['show_moves_button'] = array(
      '#type' => 'submit',
      '#name' => 'ui-show-move',
      '#value' => $show_moves ? '- ' . t('Hide last moves descriptions') : '+ ' . t('Show last moves descriptions'),
      '#limit_validation_errors' => array(array('show_moves')),
      '#submit' => array('kw_djambi_game_form_show_moves_submit'),
      '#ajax' => array('callback' => 'kw_djambi_ajax_grid_callback'),
    );
  }
  if ($gm->isPending() && !isset($form_state['show_replay'])) {
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
function _kw_djambi_build_part_rules(&$form, GameManager $gm) {
  $rules_rows = array();
  $options = $gm->getDisposition()->getOptionsStore()->getAllGameOptions();
  foreach ($options as $option) {
    $key = $option->getName();
    $modes = $option->getModes();
    if (!empty($modes) && !in_array($gm->getMode(), $modes)) {
      continue;
    }
    $value = $gm->getOption($key);
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
function _kw_djambi_build_part_draw_proposal(&$form, Battlefield $grid, Faction $faction, $ajax_settings, $is_user_playing_current_faction) {
  foreach ($grid->getFactions() as $side) {
    if ($side->getDrawStatus() == Faction::DRAW_STATUS_PROPOSED) {
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
function _kw_djambi_build_part_player_actions(&$form, &$form_state, Battlefield $grid, Faction $faction, $ajax_settings) {
  $actions = array();
  $descriptions = array();
  // Annulation de sélection d'une pièce :
  if ($grid->getCurrentMove()->getPhase() != Move::PHASE_PIECE_SELECTION) {
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
  elseif ($grid->getGameManager()->getMode() == GameManager::MODE_SANDBOX && count($grid->getTurns()) > 1) {
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
  $allowed_skip_turns = $grid->getGameManager()->getOption(StandardRuleset::GAMEPLAY_ELEMENT_SKIPPED_TURNS);
  if ($allowed_skip_turns == -1 || $allowed_skip_turns > $faction->getSkippedTurns()) {
    if (is_null($grid->getCurrentMove()->getSelectedPiece())) {
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
  $draw_proposal_delay = $grid->getGameManager()->getOption(StandardRuleset::GAMEPLAY_ELEMENT_DRAW_DELAY);
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
  if ($grid->getGameManager()->getOption(StandardRuleset::RULE_COMEBACK) == 'allowed'
  && $grid->getGameManager()->getMode() == GameManager::MODE_SANDBOX) {
    /* @var $withdrawn_faction Faction */
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
function _kw_djambi_build_part_pieces_controls(&$form, &$form_state, Battlefield $grid, Faction $faction, $ajax_settings) {
  $phase_text = NULL;
  $current_move = $grid->getCurrentMove();
  // Sélection des pièces :
  $image_path = base_path() . drupal_get_path('module', 'kw_djambi') . '/img/';
  $image_extension = '.png';
  $piece_selection_phases = array(Move::PHASE_PIECE_SELECTION, Move::PHASE_PIECE_DESTINATION);
  $global_instructions = array(
    '#limit_validation_errors' => array(array('pieces')),
    '#validate' => array('kw_djambi_game_form_validate'),
    '#submit' => array('kw_djambi_game_form_submit'),
    '#ajax' => $ajax_settings,
  );
  if (in_array($current_move->getPhase(), $piece_selection_phases)) {
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
        ) + $global_instructions;
      }
    }
    $form['piece_destination'] = array(
      '#type' => 'hidden',
      '#value' => '',
    );
  }
  // Destination des pièces :
  if ($current_move->getPhase() == Move::PHASE_PIECE_DESTINATION) {
    $selected_piece = $current_move->getSelectedPiece();
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
      ) + $global_instructions;
    }
    $form["pieces"]["movable"][$selected_piece->getId()]["#attributes"]["class"][] = "selected";
    $phase_text = t("The !piece is selected. Choose now its destination case, or select another piece to move.",
      array("!piece" => theme('djambi_piece_name', array('piece' => $selected_piece)))
    );
  }
  // Résultat du déplacement :
  elseif ($current_move->getPhase() == Move::PHASE_PIECE_INTERACTIONS
  && !is_null($current_interaction = $current_move->getFirstInteraction())) {
    $selected_piece = $current_move->getSelectedPiece();
    $current_interaction->findPossibleChoices();
    // Cas 1 : placement d'une pièce tuée
    if ($current_interaction instanceof Murder) {
      $form['pieces']['murder']['#selected_piece_id'] = $selected_piece->getId();
      $victim_piece = $current_interaction->getSelectedPiece();
      foreach ($current_interaction->getPossibleChoices() as $cell) {
        $alt_text = t('Bury !piece in !case', array(
          '!piece' => _kw_djambi_get_full_piece_name($victim_piece),
          '!case' => $cell->getName(),
        ));
        $form['pieces']['murder']['cell-' . $cell->getName()] = array(
          '#type' => 'image_button',
          '#name' => 'target-' . $cell->getName(),
          '#id' => 'target-' . $cell->getName(),
          '#return_value' => $cell->getName(),
          '#src' => base_path() . drupal_get_path('module', 'kw_djambi') . '/img/flag_black.png',
          '#attributes' => array(
            'alt' => $alt_text,
            'title' => $alt_text,
            'data-grid-button' => 'interaction',
          ),
        ) + $global_instructions;
      }
      $phase_text = t('Your !piece has killed the !victim, select now the case where you victim will rest in peace.',
        array(
          '!piece' => theme('djambi_piece_name', array('piece' => $selected_piece)),
          '!victim' => theme('djambi_piece_name', array('piece' => $victim_piece)),
        ));
    }
    // Cas 2 : placement d'une pièce tuée déplacée :
    elseif ($current_interaction instanceof Necromobility) {
      $form["pieces"]["necromobility"]["#selected_piece_id"] = $selected_piece->getId();
      foreach ($current_interaction->getPossibleChoices() as $cell) {
        $alt_text = t("Move the dead piece to case !case.", array("!case" => $cell->getName()));
        $form["pieces"]["necromobility"]["cell-" . $cell->getName()] = array(
          "#type" => "image_button",
          '#name' => 'target-' . $cell->getName(),
          '#id' => 'target-' . $cell->getName(),
          "#return_value" => $cell->getName(),
          "#src" => base_path() . drupal_get_path("module", "kw_djambi") . "/img/flag_black.png",
          '#attributes' => array(
            'alt' => $alt_text,
            'title' => $alt_text,
            'data-grid-button' => 'interaction',
          ),
        ) + $global_instructions;
      }
      $phase_text = t("Your !piece has exhumed an old dead piece, select now its new burial place.", array(
        "!piece" => theme('djambi_piece_name', array('piece' => $selected_piece)),
      ));
    }
    // Cas 3 : placement d'une pièce manipulée :
    elseif ($current_interaction instanceof Manipulation) {
      $manipulated_piece = $current_interaction->getSelectedPiece();
      $form["pieces"]["manipulation"]["#selected_piece_id"] = $selected_piece->getId();
      $form["pieces"]["manipulation"]["#target_piece_id"] = $manipulated_piece->getId();
      foreach ($current_interaction->getPossibleChoices() as $cell) {
        $alt_text = t("Move manipulated !piece to !case", array(
          "!piece" => _kw_djambi_get_full_piece_name($manipulated_piece),
          "!case" => $cell->getName(),
        ));
        $form["pieces"]["manipulation"]["cell-" . $cell->getName()] = array(
          "#type" => "image_button",
          '#name' => 'target-' . $cell->getName(),
          '#id' => 'target-' . $cell->getName(),
          "#return_value" => $cell->getName(),
          "#src" => base_path() . drupal_get_path("module", "kw_djambi") . "/img/note2.png",
          '#attributes' => array(
            'alt' => $alt_text,
            'title' => $alt_text,
            'data-grid-button' => 'interaction',
          ),
        ) + $global_instructions;
      }
      $phase_text = t("Your !diplomat is manipulating the !piece, select now the destination case for your subjucated victim.",
        array(
          "!diplomat" => theme('djambi_piece_name', array('piece' => $selected_piece)),
          "!piece" => theme('djambi_piece_name', array('piece' => $manipulated_piece)),
        ));
    }
    // Cas 4 : sélection de la victime d'un reportage :
    elseif ($current_interaction instanceof Reportage) {
      $form["pieces"]["reportage"]["#selected_piece_id"] = $selected_piece->getId();
      foreach ($current_interaction->getPossibleChoices() as $cell) {
        $victim = $cell->getOccupant();
        $position = $cell->getName();
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
        ) + $global_instructions;
      }
      $phase_text = t("Your !reporter has to choose beetween several victims...",
        array("!reporter" => theme('djambi_piece_name', array('piece' => $selected_piece))));
    }
    // Cas 5 : évacuation du trône :
    elseif ($current_interaction instanceof ThroneEvacuation) {
      $form["pieces"]["throne_evacuation"]["#selected_piece_id"] = $selected_piece->getId();
      foreach ($current_interaction->getPossibleChoices() as $cell) {
        $position = $cell->getName();
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
        ) + $global_instructions;
      }
      $phase_text = t("Your !piece is not allowed to stay here. Run away !",
        array("!piece" => theme('djambi_piece_name', array('piece' => $selected_piece))));
    }
  }
  // Attente du mouvement...
  elseif ($current_move->getPhase() == Move::PHASE_PIECE_SELECTION) {
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
function _kw_djambi_build_part_log(&$form, &$form_state, GameManager $gm) {
  $html = '';
  $detailed_log = FALSE;
  $live_logs = FALSE;
  $show_logs = array();
  $grid = $gm->getBattlefield();
  $turns = $grid->getTurns();
  $current_turn_id = 0;
  if ($gm->isPending() || $gm->isNotBegin()) {
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
  if ($gm->isFinished()) {
    $header['view'] = array('data' => t('Actions'));
  }
  $buttons = array(
    '#type' => 'submit',
    '#ajax' => array('callback' => 'kw_djambi_ajax_grid_callback'),
    '#value' => t('Watch'),
    '#submit' => array('kw_djambi_game_form_replay_submit'),
  );
  $log_id = 'DjambiLog' . $gm->getId();
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
  if ($gm->isPending()) {
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
    elseif (!$gm->isNotBegin()) {
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
function _kw_djambi_build_part_stats(&$form, Battlefield $grid) {
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
    if ($grid->getGameManager()->getMode() != GameManager::MODE_SANDBOX) {
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
  if ($grid->getGameManager()->getStatus() == GameManager::STATUS_FINISHED) {
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
    Faction::STATUS_EMPTY_SLOT,
    Faction::STATUS_VASSALIZED,
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
    foreach ($factions as $subfaction) {
      if (in_array($subfaction->getStatus(), $stop_status)) {
        continue;
      }
      $murders = $stats_murders[$faction->getId()][$subfaction->getId()];
      $total += $murders;
      $std_row = array('data' => $murders, 'class' => array('number'));
      if ($grid->getGameManager()->getOption(StandardRuleset::RULE_CANIBALISM) == 'yes'
      || $grid->getGameManager()->getOption(StandardRuleset::RULE_REPORTERS) == 'foxnews') {
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
      6 => array('data' => _kw_djambi_get_translatable_messages($grid->getGameManager()->getStatus())),
      1 => array('data' => 0, 'class' => array('number')),
      2 => array('data' => 0, 'class' => array('number')),
      3 => array('data' => 0, 'class' => array('number')),
      4 => array('data' => 0, 'class' => array('number')),
      5 => array('data' => '-', 'class' => array('number')),
    ),
  );
  foreach ($factions as $faction) {
    if (in_array($faction->getStatus(), $stop_status)) {
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
function _kw_djambi_stats_get_winners(Battlefield $grid, $stats, $type = 'piece', $order = 'max') {
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
  /* @var $gm GameManager */
  $gm = $form_state['djambi']['game'];
  $form_state['djambi_logs'] = array();
  if ($clicked_button['#name'] == 'ui-log-full') {
    foreach ($gm->getBattlefield()->getTurns() as $turn) {
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
  /* @var $gm GameManager */
  $gm = $form_state['djambi']['game'];
  $commands = array();
  $commands[] = ajax_command_replace('#DjambiGridFieldset' . $gm->getId(), drupal_render($form['grid']));
  $commands[] = ajax_command_invoke('#DjambiGrid' . $gm->getId(), 'scrollTo');
  return array('#type' => 'ajax', '#commands' => $commands);
}

/**
 * Fonction appelée après une requête Ajax : chargement des logs.
 */
function kw_djambi_ajax_log_callback($form, $form_state) {
  /* @var $gm GameManager */
  $gm = $form_state['djambi']['game'];
  $selector = '#DjambiLog' . $gm->getId();
  $commands = array();
  $commands[] = ajax_command_replace($selector, drupal_render($form['log']['container']));
  $commands[] = ajax_command_invoke($selector, 'scrollTo');
  return array('#type' => 'ajax', '#commands' => $commands);
}

/**
 * Soumission du formulaire de jeu.
 */
function kw_djambi_game_form_submit($form, &$form_state) {
  $form_state['rebuild'] = TRUE;
}

/**
 * Soumission de formulaire : annulation de la sélection d'une pièce.
 */
function kw_djambi_game_form_cancel_selection_submit($form, &$form_state) {
  $form_state['rebuild'] = TRUE;
  /* @var GameManager $gm */
  $gm = $form_state['djambi']['game'];
  $gm->rollback();
}

/**
 * Soumission du bouton d'annulation du dernier tour du jeu.
 */
function kw_djambi_game_form_cancel_last_turn_submit($form, &$form_state) {
  $form_state['rebuild'] = TRUE;
  /* @var $gm GameManager */
  $gm = $form_state['djambi']['game'];
  $gm->getBattlefield()->cancelLastTurn();
}

/**
 * Soumission du bouton "passer son tour".
 */
function kw_djambi_game_form_skip_turn_submit($form, &$form_state) {
  $form_state['rebuild'] = TRUE;
  /* @var $gm GameManager */
  $gm = $form_state['djambi']['game'];
  $gm->getBattlefield()->getPlayingFaction()->skipTurn();
}

/**
 * Soumission du bouton "Abandonner la partie".
 */
function kw_djambi_game_form_withdraw_submit($form, &$form_state) {
  $form_state['rebuild'] = TRUE;
  /* @var $gm GameManager */
  $gm = $form_state['djambi']['game'];
  $gm->getBattlefield()->getPlayingFaction()->withdraw();
}

/**
 * Soumission du bouton "Revenir dans la partie".
 */
function kw_djambi_game_form_comeback_submit($form, &$form_state) {
  $form_state['rebuild'] = TRUE;
  /* @var $gm GameManager */
  $gm = $form_state['djambi']['game'];
  $clicked_button = $form_state['clicked_button']['#parents'][0];
  $id = substr($clicked_button, strpos($clicked_button, '_') + 1);
  $gm->getBattlefield()->getFactionById($id)->comeBackAfterWithdraw();
}

/**
 * Soumission du bouton "Proposer une partie nulle".
 */
function kw_djambi_game_form_draw_proposal_submit($form, &$form_state) {
  $form_state['rebuild'] = TRUE;
  /* @var $gm GameManager */
  $gm = $form_state['djambi']['game'];
  $gm->getBattlefield()->getPlayingFaction()->callForADraw();
}

/**
 * Soumission du bouton "Accepter une partie nulle".
 */
function kw_djambi_game_form_accept_peace_submit($form, &$form_state) {
  $form_state['rebuild'] = TRUE;
  /* @var $gm GameManager */
  $gm = $form_state['djambi']['game'];
  $gm->getBattlefield()->getPlayingFaction()->acceptDraw();
}

/**
 * Soumission du bouton "Refuser une partie nulle".
 */
function kw_djambi_game_form_reject_peace_submit($form, &$form_state) {
  $form_state['rebuild'] = TRUE;
  /* @var $gm GameManager */
  $gm = $form_state['djambi']['game'];
  $gm->getBattlefield()->getPlayingFaction()->rejectDraw();
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
  /* @var $gm GameManager */
  $gm = $form_state['djambi']['game'];
  $gm->rollback();
  $grid = $gm->getBattlefield();
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
    if ($gm->isPending()) {
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
    if (($gm->isFinished() && $form_state['show_replay'] < $grid->getCurrentTurnId())
    || ($gm->isPending() && $form_state['show_replay'] < $grid->getCurrentTurnId() - 1)) {
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
  /* @var $gm GameManager */
  $gm = $form_state['djambi']['game'];
  $grid = $gm->getBattlefield();
  $faction = $grid->getPlayingFaction();
  if (!empty($faction) && $faction->getPlayer() instanceof ComputerPlayer) {
    $faction->getPlayer()->getIa()->play();
    if ($_GET['q'] == 'system/ajax') {
      $form_state['show_replay'] = $gm->isFinished() ? $grid->getCurrentTurnId() : $grid->getCurrentTurnId() - 1;
      $form_state['autoplay'] = TRUE;
    }
  }
  else {
    $form_state['djambi']['game'] = $grid->getGameManager()->reload()->play();
  }
}

/**
 * Soumission du bouton "Rejoindre une partie".
 */
function kw_djambi_game_form_participate_submit($form, &$form_state) {
  $form_state['rebuild'] = TRUE;
  $context = DjambiContext::getInstance();
  /* @var $gm GameManager */
  $gm = $form_state['djambi']['game'];
  $grid = $gm->getBattlefield();
  // Rechargement du dernier état de la grille pour vérifier que le camp visé
  // n'a pas été occupé depuis le chargement initial de la page.
  if (!is_null($gm)) {
    $gm = $gm->reload();
    $form_state['djambi']['game'] = $gm;
    $grid = $gm->getBattlefield();
  }
  try {
    $target_faction = $grid->getFactionById($form_state['clicked_button']['#array_parents'][2]);
    $player = $context->getCurrentUser(TRUE);
    $player->setLastSignal(Signal::createSignal($player, $context->getIp()));
    $gm->addNewPlayer($player, $target_faction);
    drupal_set_message(t('You are now playing the !color team.',
      array('!color' => _kw_djambi_get_translatable_messages($target_faction->getName()))));
  }
  catch (FactionNotFoundException $e) {
    drupal_set_message(t("Target faction not found."), 'error');
  }
  catch (PlayerInvalidException $e1) {
    drupal_set_message(t('Only registered players can join this game. Please !login or !register.', array(
      '!login' => l(t('login to your account'), 'user/login'),
      '!register' => l(t('create a new account'), 'user/register'),
    )), 'error');
    return;
  }
  catch (DisallowedActionException $e2) {
    if ($e2->getCode() == 2) {
      drupal_set_message(t('This faction is used by an other player.'), 'error');
    }
    else {
      drupal_set_message(t('Disallowed action detected. Please refresh this page.'), 'error');
    }
  }
}

/**
 * Soumission du bouton "Quitter la partie".
 */
function kw_djambi_game_form_cancel_participation_submit($form, &$form_state) {
  /* @var $gm GameManager */
  $gm = $form_state['djambi']['game'];
  $seq = $gm->getInfo('sequence');
  $player = DjambiContext::getInstance()->getCurrentUser();
  $player->setLastSignal(Signal::createSignal($player, DjambiContext::getInstance()->getIp()));
  $gm->ejectPlayer($player);
  drupal_set_message(t('You just left Djambi game #!nb', array('!nb' => $seq)));
  drupal_goto('<front>');
}

/**
 * Validation des actions sur la grille de jeu.
 */
function kw_djambi_game_form_validate($form, &$form_state) {
  /* @var $gm GameManager */
  $gm = $form_state['djambi']['game'];
  $grid = $gm->getBattlefield();
  $errors = array();
  // Détection du bouton cliqué :
  $button = $form_state["triggering_element"];
  $type_button = isset($button["#array_parents"][2]) ? $button["#array_parents"][2] : NULL;
  // Vérification : utilisateur courant = joueur de la faction en jeu
  $is_current_user = DjambiContext::getInstance()->checkUserPlayingFaction($grid->getPlayingFaction());
  $move = $grid->getCurrentMove();
  if (!$is_current_user) {
    $errors[] = t("It is not your turn !");
  }
  // Sélection d'une pièce :
  elseif ($type_button == "movable") {
    $piece_id = $button['#return_value'];
    try {
      $piece = $grid->getPieceById($piece_id);
      try {
        $old_selection = !is_null($move) ? $move->getSelectedPiece() : NULL;
        if (!is_null($piece) && !empty($old_selection) && $old_selection->getId() == $piece->getId()) {
          $grid->resetCurrentMove();
        }
        else {
          $grid->getCurrentMove()->selectPiece($piece);
        }
      }
      catch (DisallowedActionException $e2) {
        $errors[] = t("You are not allowed to select piece !piece.", array(
          "!piece" => _kw_djambi_get_full_piece_name($piece),
        ));
      }
    }
    catch(PieceNotFoundException $e1) {
      $errors[] = t('Piece @piece not found', array('@piece' => $piece_id));
    }
    // Gestion du glisser-déposer :
    $move = $grid->getCurrentMove();
    if (!empty($move) && !empty($form_state["input"]["piece_destination"])) {
      $destination = $form_state["input"]["piece_destination"];
      try {
        $move->moveSelectedPiece($grid->findCellByName($destination));
      }
      catch(DisallowedActionException $e3) {
        $errors[] = t("You are not allowed to move !piece into !case case.", array(
          "!piece" => _kw_djambi_get_full_piece_name($move->getSelectedPiece()),
          "!case" => $destination,
        ));
      }
    }
  }
  // Destination d'une pièce :
  elseif ($type_button == "destination") {
    $destination = $button['#return_value'];
    try {
      $move->moveSelectedPiece($grid->findCellByName($destination));
    }
    catch (DisallowedActionException $e3) {
      $errors[] = t("You are not allowed to move !piece into !case case.", array(
        "!piece" => _kw_djambi_get_full_piece_name($move->getSelectedPiece()),
        "!case" => $destination,
      ));
    }
  }
  // Traitement des interactions supplémentaires après un déplacement :
  elseif ($move->getPhase() == Move::PHASE_PIECE_INTERACTIONS) {
    $current_interaction = $move->getFirstInteraction();
    $selected_piece = $move->getSelectedPiece();
    if (!empty($current_interaction) && $type_button == $current_interaction->getType()) {
      // Manipulations :
      if ($current_interaction instanceof Manipulation) {
        try {
          $current_interaction->moveSelectedPiece($grid->findCellByName($button['#return_value']));
        }
        catch (DisallowedActionException $e4) {
          $errors[] = t("Your !piece refused to do this kind of disallowed diplomacy work.", array(
            '!piece' => _kw_djambi_get_full_piece_name($selected_piece),
          ));
        }
      }
      // Choix du placement d'une pièce venant d'être tuée :
      elseif ($current_interaction instanceof Murder) {
        try {
          $current_interaction->moveSelectedPiece($grid->findCellByName($button['#return_value']));
        }
        catch (DisallowedActionException $e5) {
          $errors[] = t("Your !piece has been sentenced guilty trying to kill an unauthorized piece.", array(
            '!piece' => _kw_djambi_get_full_piece_name($selected_piece),
          ));
        }
      }
      // Déplacement d'un mort par un nécromobile :
      elseif ($current_interaction instanceof Necromobility) {
        try {
          $current_interaction->moveSelectedPiece($grid->findCellByName($button['#return_value']));
        }
        catch (DisallowedActionException $e6) {
          $errors[] = t("Your !piece is doing something wrong with a dead piece. Pfew.", array(
            '!piece' => _kw_djambi_get_full_piece_name($selected_piece),
          ));
        }
      }
      // Choix des victimes d'un reportage :
      elseif ($current_interaction instanceof Reportage) {
        try {
          $current_interaction->setVictim($grid->findCellByName($button['#return_value']));
        }
        catch (DisallowedActionException $e7) {
          $errors[] = t("Your !piece reportage will not be published, it was breaking press deontology rules.", array(
            '!piece' => _kw_djambi_get_full_piece_name($selected_piece),
          ));
        }
      }
      elseif ($current_interaction instanceof ThroneEvacuation) {
        try {
          $current_interaction->moveSelectedPiece($grid->findCellByName($button['#return_value']));
        }
        catch (DisallowedActionException $e8) {
          $errors[] = t("You are not allowed to move !piece to !case.", array(
            "!piece" => _kw_djambi_get_full_piece_name($move->getSelectedPiece()),
            "!case" => $button['#return_value'],
          ));
        }
      }
    }
  }
  if (!empty($errors)) {
    foreach ($errors as $msg) {
      form_set_error('pieces', $msg);
    }
    $grid->resetCurrentMove();
  }
}
