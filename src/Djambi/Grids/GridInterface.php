<?php

namespace Djambi\Grids;


use Djambi\Gameplay\Faction;
use Djambi\PieceDescriptions\BasePieceDescription;

interface GridInterface {

  /**
   * Ajoute une faction dans la grille.
   *
   * @param array $start_origin
   *   Point d'origine du chef de la faction
   * @param string $start_status
   *   Statut de départ de la faction
   * @param BasePieceDescription[] $specific_pieces
   *   Liste de pièces spécifiques au camp
   *
   * @return GridInterface
   */
  public function addSide(array $start_origin = NULL, $start_status = Faction::STATUS_READY, $specific_pieces = array());

  /**
   * @param $side_order
   * @param array $changes
   *
   * @return GridInterface
   */
  public function alterSide($side_order, array $changes);

  /**
   * @param $type
   * @param $location
   *
   * @return GridInterface
   */
  public function addSpecialCell($type, $location);

  /**
   * @param BasePieceDescription $piece
   *
   * @return GridInterface
   */
  public function addCommonPiece(BasePieceDescription $piece);

  /**
   * @param $shape
   *
   * @return GridInterface
   */
  public function setShape($shape);

  /**
   * @param $cols
   * @param $rows
   *
   * @return GridInterface
   */
  public function setDimensions($cols, $rows);

  /**
   * @return array
   */
  public function getSides();

  /**
   * @return int
   */
  public function getCols();

  /**
   * @return int
   */
  public function getRows();

  /**
   * @return array
   */
  public function getSpecialCells();

  /**
   * @return String
   */
  public function getShape();

  /**
   * @return BasePieceDescription[]
   */
  public function getPieceScheme();

  /**
   * @return array
   */
  public function getDirections();

  /**
   * @param String $direction
   *
   * @return array
   */
  public function getDirection($direction);

}
