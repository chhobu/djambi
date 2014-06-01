<?php
use Djambi\GameManagers\GameManagerInterface;
use Djambi\Gameplay\Cell;
use Djambi\Grids\BaseGrid;
use Drupal\Component\Utility\Unicode;
use Drupal\Core\Template\Attribute;
use Drupal\djambi\Players\Drupal8Player;
use Drupal\djambi\Utils\GameUI;
use Drupal\djambi\Widgets\PlayersTable;

function template_preprocess_djambi_grid(&$variables) {
  /** @var Drupal8Player $current_player */
  $current_player = $variables['form']['#current_player'];
  /** @var GameManagerInterface $game */
  $game = $variables['form']['#djambi_game_manager'];
  if ($game->isPending()) {
    $playing_next = array();
    $play_order = $game->getBattlefield()->getPlayOrder();
    while (next($play_order)) {
      $playing_next[] = GameUI::printFactionFullName($game->getBattlefield()->findFactionById(current($play_order)));
    }
    reset($play_order);
    $descriptions[] = array(
      'term' => t('Current round :'),
      'description' => t('Round %round', array('%round' => $game->getBattlefield()->getCurrentTurn()->getRound())),
      'attributes' => array('class' => array('djambi-infos__current-round')),
    );
    $descriptions[] = array(
      'term' => t('Playing now :'),
      'description' => GameUI::printFactionFullName($game->getBattlefield()->getPlayingFaction()),
      'attributes' => array('class' => array('djambi-infos__playing-now')),
    );
    $playing_next_markup = array(
      '#theme' => 'item_list',
      '#items' => $playing_next,
      '#list_type' => 'ol',
    );
    $descriptions[] = array(
      'term' => t('Playing next :'),
      'description' => $playing_next_markup,
      'attributes' => array('class' => array('djambi-infos__playing-next')),
    );
    $variables['game_infos'] = array(
      '#theme' => 'description_list',
      '#groups' => $descriptions,
      '#attributes' => array('class' => array('djambi-infos')),
    );
    $caption = t('Displaying current turn (round %round - move %move).', array(
      '%round' => $game->getBattlefield()->getCurrentTurn()->getRound(),
      '%move' => $game->getBattlefield()->getCurrentTurn()->getId(),
    ));
    if ($current_player->isPlayingFaction($game->getBattlefield()->getPlayingFaction()->getControl())) {
      $caption .= ' ' . t("It's your turn !");
    }
    else {
      $caption .= ' ' . t("Waiting for !player decisions...",
        array('!player' => $game->getBattlefield()->getPlayingFaction()->getPlayer()->displayName()));
    }
    $players_table = PlayersTable::build(array('game' => $game, 'current_player' => $current_player));
    $variables['players_table'] = array(
      '#theme' => 'table',
      '#rows' => $players_table->getRows(),
      '#header' => $players_table->getHeader(),
      '#attributes' => array(
        'class' => array('datatable', 'is-centered', 'djambi-players-table'),
      ),
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
  );
  $variables['grid']['#attributes']['class'][] = 'djambi-grid';
  $size = $current_player->getDisplaySetting(GameUI::SETTING_GRID_SIZE);
  $variables['grid']['#attributes']['class'][] = 'djambi-grid--size-' . $size;
  $variables['grid']['#attributes']['id'] = 'DjambiGrid-' . $game->getId();
  if ($current_player->getDisplaySetting(GameUI::SETTING_HIGHLIGHT_CELLS)) {
    $variables['grid']['#attributes']['class'][] = 'is-with-highlighted-cells';
  }
  if ($game->getDisposition()->getGrid()->getShape() == BaseGrid::SHAPE_HEXAGONAL &&
    !$current_player->getDisplaySetting(GameUI::SETTING_DISPLAY_CELL_NAME_HEXAGONAL)) {
    $variables['grid']['#attributes']['class'][] = 'djambi-grid--no-cell-names';
  }
  elseif ($game->getDisposition()->getGrid()->getShape() == BaseGrid::SHAPE_CARDINAL &&
    !$current_player->getDisplaySetting(GameUI::SETTING_DISPLAY_CELL_NAME_CARDINAL)) {
    $variables['grid']['#attributes']['class'][] = 'djambi-grid--no-cell-names';
  }
  if ($game->isPending()) {
    $variables['grid']['#attributes']['class'][] = 'is-pending';
    $turn = $game->getBattlefield()->getCurrentTurn();
    if (!empty($turn) && !empty($turn->getMove())) {
      $variables['grid']['#attributes']['class'][] = 'is-' . $turn->getMove()->getActingFaction()->getClass() . '-turn';
      if (!empty($turn->getMove()->getSelectedPiece())) {
        $variables['grid']['#attributes']['class'][] = 'is-with-selected-piece';
      }
      else {
        $variables['grid']['#attributes']['class'][] = 'is-without-selected-piece';
      }
    }
  }
  elseif ($game->isFinished()) {
    $variables['grid']['#attributes']['class'][] = 'is-finished';
  }
}

function theme_djambi_cardinal_grid($variables) {
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
    '#attributes' => $variables['attributes'],
  );
  return drupal_render($table);
}

function template_preprocess_djambi_piece(&$variables) {
  /** @var \Djambi\Gameplay\Piece $piece */
  $piece = $variables['piece'];
  $variables['piece_tag'] = 'div';
  $attributes = array();
  if (!empty($piece)) {
    $attributes['class'][] = 'djambi-piece';
    if (!is_null($current_turn = $piece->getFaction()->getBattlefield()->getCurrentTurn())) {
      $move = $current_turn->getMove();
      if (!empty($move) && !empty($move->getSelectedPiece()) && $move->getSelectedPiece()->getId() == $piece->getId()) {
        $attributes['class'][] = 'is-selected';
      }
      if ($piece->isSelectable()) {
        $attributes['for'] = 'edit-cells-' . Unicode::strtolower($piece->getPosition()->getName());
        $variables['piece_tag'] = 'label';
      }
      if ($piece->isMovable()) {
        $attributes['class'][] = 'is-movable';
      }
    }
    if ($piece->isAlive()) {
      $attributes['class'][] = 'is-alive';
      $attributes['class'][] = 'djambi-piece--type-' . $piece->getDescription()->getType();
      if ($piece->getFaction()->getControl()->isAlive()) {
        $attributes['class'][] = 'is-' . $piece->getFaction()->getControl()->getClass() . '-side';
      }
      $attributes['class'][] = 'is-' . $piece->getFaction()->getClass() . '-origin';
      $variables['piece_short_name'] = $piece->getId();
      $variables['piece_full_name'] = $attributes['title'] = GameUI::printPieceFullName($piece, FALSE);
      $attributes['data-piece-type'] = $piece->getDescription()->getGenericSymbol();
    }
    else {
      $attributes['class'][] = 'is-dead';
      $variables['piece_short_name'] = t("R.I.P.");
      $attributes['title'] = t("Here lies a dead piece.");
    }
  }
  $variables['attributes'] = new Attribute($attributes);
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
  $variables['piece'] = $piece;
  $attributes = array();
  $variables['cell_tag'] = 'div';
  $attributes['class'][] = 'djambi-cell';
  $attributes['class'][] = 'djambi-cell--type-' . $cell->getType();
  if ($cell->isReachable()) {
    $attributes['class'][] = 'is-reachable';
  }
  if ($cell->isEnabled()) {
    $attributes['class'][] = (empty($cell->getOccupant()) ? 'is-free' : 'is-occupied');
    if ($cell->isSelectable()) {
      $attributes['class'][] = 'is-selectable';
      $variables['cell_tag'] = 'label';
      $attributes['for'] = 'edit-cells-' . Unicode::strtolower($cell->getName());
    }
    elseif (!empty($cell->getOccupant()) && $cell->getOccupant()->isSelected()) {
      $attributes['class'][] = 'is-selected';
    }
    elseif (!empty($cell->getOccupant()) && $cell->getOccupant()->isMovable()) {
      $turn = $cell->getOccupant()->getBattlefield()->getCurrentTurn();
      if (!empty($turn) && !empty($turn->getMove()) && empty($turn->getMove()->getSelectedPiece())) {
        $attributes['class'][] = 'is-selectable';
      }
      else {
        $attributes['class'][] = 'is-with-movable-piece';
      }
    }
    $attributes['title'] = t('Cell @name', array('@name' => $cell->getName()));
    $attributes['data-cell-name'] = $cell->getName();
  }
  $variables['attributes'] = new Attribute($attributes);
}
