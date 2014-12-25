<?php
/**
 * Created by PhpStorm.
 * User: buchho
 * Date: 24/12/14
 * Time: 12:09
 */

namespace Djambi\PieceDescriptions;


interface PiecesContainerInterface {
  /**
   * @param PieceInterface $piece
   *
   * @return static
   */
  public function addPiece(PieceInterface $piece);

  /**
   * @param string $type
   *
   * @return PieceInterface[]
   */
  public function getPiecesByType($type);

  /**
   * @return array
   */
  public function getTypes();
}