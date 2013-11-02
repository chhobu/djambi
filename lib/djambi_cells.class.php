<?php
/**
 * @file
 * Classe permettant de gérer les cellules d'une grille de Djambi.
 */

class DjambiCell {
  /**
   * Nom de la cellule
   * @var string $name
   */
  protected $name;

  /**
   * Coordonnée verticale de la cellule
   * @var int $x
   */
  protected $x;

  /**
   * Coordonnée horizontale de la cellule
   * @var int $y
   */
  protected $y;

  /**
   * Pièce placée sur la cellule
   * @var DjambiPiece $occupant
   */
  protected $occupant;

  /**
   * Type de cellule. Par défaut : 'std' (standard).
   * @var string $type
   */
  protected $type = 'std';

  /**
   * Liste des cellules voisines
   * @var DjambiCell[] $neighbours
   */
  protected $neighbours = array();

  /**
   * Définit si une cellule est accessible par une pièce sélectionnée
   * @var bool $reachable
   */
  protected $reachable = FALSE;

  /**
   * Nom de la colonne sur laquelle se trouve la cellule
   * @var string $columnName
   */
  protected $columnName;

  /**
   * Ajoute une cellule dans la grille.
   *
   * @param DjambiBattlefield $grid
   *   Grille de Djambi
   * @param string $row_letter
   *   Nom de la colonne de la grille
   * @param int $x
   *   Coordonnée verticale
   * @param int $y
   *   Coordonnée horizontale
   */
  protected function __construct(DjambiBattlefield $grid, $row_letter, $x, $y) {
    $this->x = $x;
    $this->y = $y;
    $this->name = $row_letter . $y;
    $this->columnName = $row_letter;
    $grid->registerCell($this);
    return $this;
  }

  /**
   * Construit une cellule de Djambi à partir de ses coordonnées x / y.
   */
  public static function createByXY(DjambiBattlefield $grid, $x, $y) {
    $x_corrected = $x - 1;
    for ($row_letter = ""; $x_corrected >= 0; $x_corrected = intval($x_corrected / 26) - 1) {
      $row_letter = chr($x_corrected % 26 + 0x41) . $row_letter;
    }
    return new self($grid, $row_letter, $x, $y);
  }

  /**
   * @return int
   */
  public function getY() {
    return $this->y;
  }

  /**
   * @return int
   */
  public function getX() {
    return $this->x;
  }

  /**
   * @return string
   */
  public function getName() {
    return $this->name;
  }

  public function setOccupant(DjambiPiece $occupant) {
    $this->occupant = $occupant;
    return $this;
  }

  public function emptyOccupant() {
    $this->occupant = NULL;
    return $this;
  }

  /**
   * @return DjambiPiece
   */
  public function getOccupant() {
    return $this->occupant;
  }

  /**
   * Fixe le type de cellule.
   *
   * @param string $type
   *   Type de cellule. Par défaut : std (standard).
   *   Valeurs possibles : throne, disabled, std, fortress, bastion
   *
   * @return $this
   */
  public function setType($type) {
    $this->type = $type;
    return $this;
  }

  /**
   * @return string
   */
  public function getType() {
    return $this->type;
  }

  public function setNeighbours($neighbours) {
    $this->neighbours = $neighbours;
  }

  public function getNeighbours() {
    return $this->neighbours;
  }

  public function addNeighbour(DjambiCell $neighbour, $direction) {
    $this->neighbours[$direction] = $neighbour;
    return $this;
  }

  public function setReachable($bool) {
    $this->reachable = $bool;
    return $this;
  }

  /**
   * @return bool
   */
  public function isReachable() {
    return $this->reachable;
  }

  public function getColumnName() {
    return $this->columnName;
  }

}
