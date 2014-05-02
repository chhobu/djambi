<?php
use Djambi\GameManagers\GameManagerInterface;
use Djambi\Grids\BaseGrid;
use Drupal\Core\Render\Element;

function theme_djambi_grid($variables) {
  $structure = array();
  $form = $variables['form'];
  /** @var GameManagerInterface $game */
  $game = $form['#djambi_game_manager'];
  $cells = array();
  $caption = '';
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
  $structure['grid'] = array(
    '#theme' => $grid_theme_function,
    '#cells' => $cells,
    '#caption' => $caption,
    '#game' => $game,
  );
  foreach (Element::children($form) as $child) {
    $structure[$child] = $form[$child];
  }
  return drupal_render($structure);
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
  if (!empty($piece)) {
    $variables['piece_representation'] = $piece->getId();
    $move = $piece->getFaction()->getBattlefield()->getCurrentMove();
    if (!empty($move) && !empty($move->getSelectedPiece()) && $move->getSelectedPiece()->getId() == $piece->getId()) {
      $extra_classes[] = 'selected';
    }
  }
  $variables['extra_classes'] = implode(' ', $extra_classes);
}

function template_preprocess_djambi_cell(&$variables) {
  /** @var \Djambi\Gameplay\Cell $cell */
  $cell = $variables['cell'];
  $piece = array(
    '#theme' => 'djambi_piece',
    '#piece' => $cell->getOccupant(),
  );
  $variables['piece'] = drupal_render($piece);
}
