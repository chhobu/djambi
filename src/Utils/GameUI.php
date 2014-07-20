<?php
/**
 * Created by PhpStorm.
 * User: buchho
 * Date: 25/05/14
 * Time: 00:28
 */

namespace Drupal\djambi\Utils;

use Djambi\Gameplay\Faction;
use Djambi\Gameplay\Piece;

class GameUI {

  const SETTING_GRID_SIZE = 'grid-size';
  const SETTING_HIGHLIGHT_CELLS = 'highlight-cells';
  const SETTING_DISPLAY_CELL_NAME_CARDINAL = 'display-cell-names-cardinal';
  const SETTING_DISPLAY_CELL_NAME_HEXAGONAL = 'display-cell-names-hexagonal';

  const GRID_SIZE_SMALL = 'small';
  const GRID_SIZE_BIG = 'big';
  const GRID_SIZE_STANDARD = 'standard';
  const GRID_SIZE_ADAPTATIVE = 'adaptative';

  public static function getDefaultDisplaySettings() {
    return array(
      static::SETTING_GRID_SIZE => static::GRID_SIZE_ADAPTATIVE,
      static::SETTING_HIGHLIGHT_CELLS => TRUE,
      static::SETTING_DISPLAY_CELL_NAME_CARDINAL => FALSE,
      static::SETTING_DISPLAY_CELL_NAME_HEXAGONAL => TRUE,
    );
  }

  public static function printPieceFullName(Piece $piece, $html = TRUE, $display_dead_name = FALSE) {
    $elements = array(
      '#theme' => 'djambi_piece_full_name',
      '#piece' => $piece,
      '#html' => $html,
      '#display_dead_name' => $display_dead_name,
    );
    return drupal_render($elements);
  }

  public static function printFactionFullName(Faction $faction, $html = TRUE) {
    $elements = array(
      '#theme' => 'djambi_faction_full_name',
      '#faction' => $faction,
      '#html' => $html,
    );
    return drupal_render($elements);
  }

  public static function printPieceLog(Piece $piece, $dead = FALSE) {
    $elements = array(
      '#theme' => 'djambi_piece_log',
      '#piece' => $piece,
      '#dead' => $dead,
    );
    return drupal_render($elements);
  }

}
