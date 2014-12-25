<?php
/**
 * Created by PhpStorm.
 * User: buchho
 * Date: 24/12/14
 * Time: 11:06
 */

namespace Djambi\PieceDescriptions;


class PiecesContainer implements PiecesContainerInterface {

  /** @var array */
  protected $pieces = array();

  public function addPiece(PieceInterface $piece) {
    $this->pieces[$piece->getType()][] = $piece;
    $piece->setContainer($this);
    return $this;
  }

  /**
   * @param string $type
   *
   * @return PieceInterface[]
   */
  public function getPiecesByType($type) {
    if (!isset($this->pieces[$type])) {
      throw new \OutOfBoundsException(sprintf("Piece of type %s not found in piece container.", $type));
    }
    return $this->pieces[$type];
  }

  /**
   * @return array
   */
  public function getTypes() {
    return array_keys($this->pieces);
  }
}