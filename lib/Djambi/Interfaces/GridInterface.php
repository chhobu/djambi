<?php

namespace Djambi\Interfaces;


use Djambi\Faction;
use Djambi\PieceDescription;

interface GridInterface {

  /**
   * Ajoute une faction dans la grille.
   *
   * @param array $start_origin
   *   Point d'origine du chef de la faction
   * @param string $start_status
   *   Statut de départ de la faction
   * @param PieceDescription[] $specific_pieces
   *   Liste de pièces spécifiques au camp
   *
   * @return GridInterface
   */
  public function addSide(array $start_origin = NULL, $start_status = Faction::STATUS_READY, $specific_pieces = array());

  public function addSpecialCell($type, $location);

  public function addCommonPiece(PieceDescription $piece);

  public function setShape($shape);

  public function setDimensions($cols, $rows);
}

