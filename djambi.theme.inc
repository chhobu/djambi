<?php
use Djambi\GameManagers\GameManagerInterface;
use Djambi\Gameplay\Cell;
use Djambi\Grids\BaseGrid;
use Drupal\Component\Utility\Unicode;
use Drupal\djambi\Players\Drupal8Player;
use Drupal\djambi\Utils\GameUI;
use Drupal\djambi\Widgets\PlayersTable;

function template_preprocess_djambi_grid(&$variables) {
  /** @var GameManagerInterface $game */
  $game = $variables['form']['#djambi_game_manager'];
  $variables['game'] = $game;
  if ($game->isPending()) {
    $playing_next = array();
    $play_order = $game->getBattlefield()->getPlayOrder();
    while (next($play_order)) {
      $playing_next[] = GameUI::printFactionFullName($game->getBattlefield()->findFactionById(current($play_order)));
    }
    reset($play_order);
    $variables['current_round'] = $game->getBattlefield()->getCurrentTurn()->getRound();
    $variables['current_player_name'] = $game->getBattlefield()->getPlayingFaction()->getPlayer()->displayName();
    $variables['current_faction_name'] = GameUI::printFactionFullName($game->getBattlefield()->getPlayingFaction());
    $variables['playing_next_faction_names'] = $playing_next;
    $caption = t('Displaying current turn (round %round - move %move).', array(
      '%round' => $variables['current_round'],
      '%move' => $game->getBattlefield()->getCurrentTurn()->getId(),
    ));
    $current_player = Drupal8Player::fromCurrentUser(\Drupal::currentUser(), \Drupal::request());
    if ($current_player->isPlayingFaction($game->getBattlefield()->getPlayingFaction()->getControl())) {
      $caption .= ' ' . t("It's your turn !");
    }
    else {
      $caption .= ' ' . t("Waiting for !player decisions...", array('!player' => $variables['current_player_name']));
    }
    $players_table = PlayersTable::build(array('game' => $game, 'current_player' => $current_player));
    $variables['players_table'] = array(
      '#theme' => 'table',
      '#rows' => $players_table->getRows(),
      '#header' => $players_table->getHeader(),
      '#attributes' => array('class' => array('djambi-grid-players-table')),
      '#caption' => t("Current sides statuses"),
    );
  }
  else {
    $last_move = end($game->getBattlefield()->getPastTurns());
    $variables['current_round'] = $last_move['round'];
    $caption = t('Displaying final positions (round %round - move %move).', array(
      '%round' => $variables['current_round'],
      '%move' => $last_move['id'],
    ));
  }
  $cells = array();
  foreach ($game->getBattlefield()->getCells() as $cell) {
    $cells[$cell->getY()]['data'][$cell->getColumnName()] = array(
      'data' => array(
        '#theme' => 'djambi_cell',
        '#cell' => $cell,
        '#game' => $game,
      ),
    );
  }
  if ($game->getDisposition()->getGrid()->getShape() == BaseGrid::SHAPE_HEXAGONAL) {
    $grid_theme_function = 'djambi_hexagonal_grid';
  }
  else {
    $grid_theme_function = 'djambi_cardinal_grid';
  }
  $variables['grid'] = array(
    '#theme' => $grid_theme_function,
    '#cells' => $cells,
    '#caption' => $caption,
    '#game' => $game,
  );
}

function theme_djambi_cardinal_grid($variables) {
  /** @var GameManagerInterface $game */
  $game = $variables['game'];
  $cells = $variables['cells'];
  $caption = $variables['caption'];
  $header = array();
  $header[] = array('data' => '#');
  $first_row = current($cells);
  foreach ($first_row['data'] as $key => $cell) {
    $header[] = array('data' => $key);
  }
  foreach ($cells as $row_number => $row) {
    $head_cell = array(
      'data' => $row_number,
      'header' => TRUE,
    );
    array_unshift($cells[$row_number]['data'], $head_cell);
  }
  $table = array(
    '#theme' => 'table',
    '#rows' => $cells,
    '#header' => $header,
    '#caption' => $caption,
    '#attributes' => array(
      'class' => array('djambi-grid'),
      'id' => 'DjambiGrid-' . $game->getId(),
    ),
  );
  return drupal_render($table);
}

function template_preprocess_djambi_piece(&$variables) {
  /** @var \Djambi\Gameplay\Piece $piece */
  $piece = $variables['piece'];
  $extra_classes = array();
  if (!empty($piece) && !is_null($current_turn = $piece->getFaction()->getBattlefield()->getCurrentTurn())) {
    $move = $current_turn->getMove();
    if (!empty($move) && !empty($move->getSelectedPiece()) && $move->getSelectedPiece()->getId() == $piece->getId()) {
      $extra_classes[] = 'selected';
    }
    if ($piece->isSelectable()) {
      $variables['piece_form_label'] = 'edit-cells-' . Unicode::strtolower($piece->getPosition()->getName());
    }
    $variables['piece_full_name'] = GameUI::printPieceFullName($piece, FALSE);
  }
  $variables['extra_classes'] = implode(' ', $extra_classes);
}

function template_preprocess_djambi_cell(&$variables) {
  /** @var Cell $cell */
  $cell = $variables['cell'];
  $piece = array(
    '#theme' => 'djambi_piece',
    '#piece' => $cell->getOccupant(),
  );
  $variables['throne'] = $cell->getType() == Cell::TYPE_THRONE;
  if ($cell->isSelectable()) {
    $variables['cell_form_label'] = 'edit-cells-' . Unicode::strtolower($cell->getName());
  }
  $variables['piece'] = drupal_render($piece);
}
