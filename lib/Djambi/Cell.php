<?php
/**
 * @file
 * Classe permettant de gérer les cellules d'une grille de Djambi.
 */

namespace Djambi;

use Djambi\Exceptions\GridInvalidException;

class Cell {
  const TYPE_STANDARD = 'std';
  const TYPE_THRONE = 'throne';
  const TYPE_DISABLED = 'disabled';
  const TYPE_FORTRESS = 'fortress';

  /** @var string : Nom de la cellule */
  private $name;
  /** @var int : Coordonnée verticale de la cellule */
  private $x;
  /** @var int : Coordonnée horizontale de la cellule */
  private $y;
  /** @var Piece : Pièce placée sur la cellule */
  private $occupant;
  /** @var string : Type de cellule. Par défaut : 'std' (standard). */
  private $type = self::TYPE_STANDARD;
  /** @var array : liste des types implémentés */
  protected $implementedTypes = array(
    self::TYPE_STANDARD,
    self::TYPE_DISABLED,
    self::TYPE_THRONE,
  );
  /** @var Cell[] : liste des cellules voisines */
  private $neighbours = array();
  /** @var bool : définit si une cellule est accessible par une pièce sélectionnée */
  private $reachable = FALSE;
  /** @var string : Nom de la colonne sur laquelle se trouve la cellule */
  private $columnName;

  /**
   * Ajoute une cellule dans la grille.
   *
   * @param Battlefield $grid
   *   Grille de Djambi
   * @param string $row_letter
   *   Nom de la colonne de la grille
   * @param int $x
   *   Coordonnée verticale
   * @param int $y
   *   Coordonnée horizontale
   */
  protected function __construct(Battlefield $grid, $row_letter, $x, $y) {
    $this->x = $x;
    $this->y = $y;
    $this->name = $row_letter . $y;
    $this->columnName = $row_letter;
    $grid->registerCell($this);
  }

  /**
   * Construit une cellule de Djambi à partir de ses coordonnées x / y.
   */
  public static function createByXY(Battlefield $grid, $x, $y) {
    $x_corrected = $x - 1;
    for ($row_letter = ""; $x_corrected >= 0; $x_corrected = intval($x_corrected / 26) - 1) {
      $row_letter = chr($x_corrected % 26 + 0x41) . $row_letter;
    }
    return new self($grid, $row_letter, $x, $y);
  }

  public function getY() {
    return $this->y;
  }

  public function getX() {
    return $this->x;
  }

  public function getName() {
    return $this->name;
  }

  public function setOccupant(Piece $occupant) {
    $this->occupant = $occupant;
    return $this;
  }

  public function emptyOccupant() {
    $this->occupant = NULL;
    return $this;
  }

  public function getOccupant() {
    return $this->occupant;
  }

  /**
   * Fixe le type de cellule.
   *
   * @param string $type
   *   Type de cellule. Par défaut : std (standard).
   *
   * @throws Exceptions\GridInvalidException
   * @return $this
   */
  public function setType($type) {
    if (in_array($type, $this->implementedTypes)) {
      $this->type = $type;
    }
    else {
      throw new GridInvalidException("Unimplemented cell type : " . $type);
    }
    return $this;
  }

  public function getType() {
    return $this->type;
  }

  public function setNeighbours($neighbours) {
    $this->neighbours = $neighbours;
  }

  public function getNeighbours() {
    return $this->neighbours;
  }

  public function addNeighbour(Cell $neighbour, $direction) {
    $this->neighbours[$direction] = $neighbour;
    return $this;
  }

  public function setReachable($bool) {
    $this->reachable = $bool;
    return $this;
  }

  public function isReachable() {
    return $this->reachable;
  }

  public function getColumnName() {
    return $this->columnName;
  }

  public function isEnabled() {
    return $this->getType() != self::TYPE_DISABLED;
  }

}
