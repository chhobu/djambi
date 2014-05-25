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

  public static function printPieceFullName(Piece $piece, $html = TRUE) {
    $elements = array(
      '#theme' => 'djambi_piece_full_name',
      '#piece' => $piece,
      '#html' => $html,
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
}
