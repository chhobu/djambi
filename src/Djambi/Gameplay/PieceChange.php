<?php
/**
 * Created by PhpStorm.
 * User: buchho
 * Date: 13/05/14
 * Time: 21:17
 */

namespace Djambi\Gameplay;


class PieceChange extends BaseChange {

  public static function objectLoad($array, $context) {
    /** @var Battlefield $battlefield */
    $battlefield = $context['battlefield'];
    return $battlefield->findPieceById($array);
  }

  /**
   * @return Piece
   */
  public function getObject() {
    return $this->object;
  }

}
