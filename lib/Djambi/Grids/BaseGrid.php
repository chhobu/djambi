<?php
/**
 * @file
 * Implémente la notion de schéma de jeu (forme et taille des grilles,
 * disposition initiale des pièces, localisation des cases spéciales,...) et
 * fournit des schémas pour des cas classiques.
 */

namespace Djambi\Grids;
use Djambi\Exceptions\GridInvalidException;
use Djambi\Gameplay\Cell;
use Djambi\Gameplay\Faction;
use Djambi\Persistance\PersistantDjambiObject;
use Djambi\PieceDescriptions\Assassin;
use Djambi\PieceDescriptions\BasePieceDescription;
use Djambi\PieceDescriptions\Diplomat;
use Djambi\PieceDescriptions\Leader;
use Djambi\PieceDescriptions\Militant;
use Djambi\PieceDescriptions\Necromobile;
use Djambi\PieceDescriptions\Reporter;
use Djambi\Strings\Glossary;
use Djambi\Strings\GlossaryTerm;

/**
 * Class DjambiBattlefieldScheme
 */
abstract class BaseGrid extends PersistantDjambiObject implements GridInterface {
  const SHAPE_HEXAGONAL = 'hexagonal';
  const SHAPE_CARDINAL = 'cardinal';

  const PIECE_PLACEMENT_RELATIVE = 'leader-relative';
  const PIECE_PLACEMENT_SPECIFIC_ONLY = 'specific';

  /* @var array $allowableDispotions */
  protected $allowableShapes = array(
    self::SHAPE_CARDINAL,
    self::SHAPE_HEXAGONAL,
  );
  /* @var BasePieceDescription[] $pieceScheme */
  protected $pieceScheme = array();
  /* @var string $disposition */
  protected $shape = self::SHAPE_CARDINAL;
  /* @var int $rows */
  protected $rows = 9;
  /* @var int $cols */
  protected $cols = 9;
  /* @var array $specialCells */
  protected $specialCells = array();
  /* @var array $sides */
  protected $sides = array();
  /* @var array $directions */
  protected $directions = array();

  public static function fromArray(array $array, array $context = array()) {
    return new static();
  }

  protected function useStandardPieces() {
    $this->addCommonPiece(new Leader(NULL, array('x' => 0, 'y' => 0)));
    $this->addCommonPiece(new Diplomat(NULL, array('x' => 0, 'y' => 1)));
    $this->addCommonPiece(new Reporter(NULL, array('x' => -1, 'y' => 0)));
    $this->addCommonPiece(new Assassin(NULL, array('x' => 1, 'y' => 0)));
    $this->addCommonPiece(new Necromobile(NULL, array('x' => 0, 'y' => 2)));
    $this->addCommonPiece(new Militant(1, array('x' => -2, 'y' => 0)));
    $this->addCommonPiece(new Militant(2, array('x' => 2, 'y' => 0)));
    $this->addCommonPiece(new Militant(3, array('x' => -1, 'y' => 1)));
    $this->addCommonPiece(new Militant(4, array('x' => 1, 'y' => 1)));
  }

  protected function useStandardGrid($cols = 9, $rows = 9) {
    $this->setCols($cols);
    $this->setRows($rows);
    $this->setShape(self::SHAPE_CARDINAL);
    $this->addSpecialCell(Cell::TYPE_THRONE, array('x' => ceil($rows / 2), 'y' => ceil($cols / 2)));
    $this->useCardinalDirections(TRUE);
  }

  protected function useHexagonalGrid($cols = 9, $rows = 9) {
    $this->setCols($cols);
    $this->setRows($rows);
    $this->setShape(self::SHAPE_HEXAGONAL);
    $this->addSpecialCell(Cell::TYPE_THRONE, array('x' => ceil($rows / 2), 'y' => ceil($cols / 2)));
    $this->useHexagonalDirections();
    return $this;
  }

  protected function useCardinalDirections($diagonals) {
    $directions = array();
    if (!$diagonals) {
      $directions['N'] = array(
        'x' => 0,
        'y' => -1,
        'diagonal' => FALSE,
        'left' => 'W',
        'right' => 'E',
      );
      $directions['E'] = array(
        'x' => 1,
        'y' => 0,
        'diagonal' => FALSE,
        'left' => 'N',
        'right' => 'S',
      );
      $directions['S'] = array(
        'x' => 0,
        'y' => 1,
        'diagonal' => FALSE,
        'left' => 'E',
        'right' => 'W',
      );
      $directions['W'] = array(
        'x' => -1,
        'y' => 0,
        'diagonal' => FALSE,
        'left' => 'S',
        'right' => 'N',
      );
    }
    else {
      $directions['N'] = array(
        'x' => 0,
        'y' => -1,
        'diagonal' => FALSE,
        'left' => 'NW',
        'right' => 'NE',
      );
      $directions['E'] = array(
        'x' => 1,
        'y' => 0,
        'diagonal' => FALSE,
        'left' => 'NE',
        'right' => 'SE',
      );
      $directions['S'] = array(
        'x' => 0,
        'y' => 1,
        'diagonal' => FALSE,
        'left' => 'SE',
        'right' => 'SW',
      );
      $directions['W'] = array(
        'x' => -1,
        'y' => 0,
        'diagonal' => FALSE,
        'left' => 'SW',
        'right' => 'NW',
      );
      $directions['NE'] = array(
        'x' => 1,
        'y' => -1,
        'diagonal' => TRUE,
        'left' => 'N',
        'right' => 'E',
      );
      $directions['SE'] = array(
        'x' => 1,
        'y' => 1,
        'diagonal' => TRUE,
        'left' => 'E',
        'right' => 'S',
      );
      $directions['SW'] = array(
        'x' => -1,
        'y' => 1,
        'diagonal' => TRUE,
        'left' => 'S',
        'right' => 'W',
      );
      $directions['NW'] = array(
        'x' => -1,
        'y' => -1,
        'diagonal' => TRUE,
        'left' => 'W',
        'right' => 'N',
      );
    }
    $this->directions = $directions;
    return $this;
  }

  protected function useHexagonalDirections() {
    $directions = array(
      'NE' => array(
        'x' => 1,
        'y' => -1,
        'diagonal' => FALSE,
        'left' => 'NW',
        'right' => 'E',
        'modulo_x' => TRUE,
      ),
      'E' => array(
        'x' => 1,
        'y' => 0,
        'diagonal' => FALSE,
        'left' => 'NE',
        'right' => 'SE',
      ),
      'SE' => array(
        'x' => 1,
        'y' => 1,
        'diagonal' => FALSE,
        'left' => 'E',
        'right' => 'SW',
        'modulo_x' => TRUE,
      ),
      'SW' => array(
        'x' => -1,
        'y' => 1,
        'diagonal' => FALSE,
        'left' => 'SE',
        'right' => 'W',
        'modulo_x' => TRUE,
      ),
      'W' => array(
        'x' => -1,
        'y' => 0,
        'diagonal' => FALSE,
        'left' => 'SW',
        'right' => 'NW',
      ),
      'NW' => array(
        'x' => -1,
        'y' => -1,
        'diagonal' => FALSE,
        'left' => 'W',
        'right' => 'NE',
        'modulo_x' => TRUE,
      ),
    );
    $this->directions = $directions;
    return $this;
  }

  protected function addAllowableShapes($shape) {
    if (!in_array($shape, $this->allowableShapes)) {
      $this->allowableShapes[] = $shape;
      return TRUE;
    }
    else {
      return FALSE;
    }
  }

  public function setShape($shape) {
    if (!in_array($shape, $this->allowableShapes)) {
      throw new GridInvalidException('Unknown disposition');
    }
    else {
      $this->shape = $shape;
    }
  }

  public function getShape() {
    return $this->shape;
  }

  public function setDimensions($cols, $rows) {
    $this->setRows($rows);
    $this->setCols($cols);
    return $this;
  }

  protected function setRows($nb) {
    if ($nb <= 0) {
      throw new GridInvalidException('Not enough rows');
    }
    elseif ($nb > 26) {
      throw new GridInvalidException('Too many rows');
    }
    else {
      $this->rows = $nb;
    }
  }

  public function getRows() {
    return $this->rows;
  }

  protected function setCols($nb) {
    if ($nb <= 0) {
      throw new GridInvalidException('Not enough columns');
    }
    elseif ($nb > 26) {
      throw new GridInvalidException('Too many colums');
    }
    else {
      $this->cols = $nb;
    }
  }

  public function getCols() {
    return $this->cols;
  }

  public function addCommonPiece(BasePieceDescription $piece) {
    $this->pieceScheme[$piece->getShortname()] = $piece;
  }

  public function getPieceScheme() {
    return $this->pieceScheme;
  }

  public static function getSidesInfos($order = NULL) {
    $factions = array();
    $factions['t1'] = array(
      'id' => 't1',
      'name' => new GlossaryTerm(Glossary::SIDE_RED),
      'class' => 'rouge',
      'start_order' => 1,
    );
    $factions['t2'] = array(
      'id' => 't2',
      'name' => new GlossaryTerm(Glossary::SIDE_BLUE),
      'class' => 'bleu',
      'start_order' => 2,
    );
    $factions['t3'] = array(
      'id' => 't3',
      'name' => new GlossaryTerm(Glossary::SIDE_YELLOW),
      'class' => 'jaune',
      'start_order' => 3,
    );
    $factions['t4'] = array(
      'id' => 't4',
      'name' => new GlossaryTerm(Glossary::SIDE_GREEN),
      'class' => 'vert',
      'start_order' => 4,
    );
    if (!is_null($order)) {
      foreach ($factions as $faction) {
        if ($faction['start_order'] == $order) {
          return $faction;
        }
      }
      throw new GridInvalidException("Undescribed faction : #" . $order);
    }
    return $factions;
  }

  public function addSide(array $start_origin = NULL, $start_status = Faction::STATUS_READY, $specific_pieces = array()) {
    $nb_sides = count($this->sides) + 1;
    if (!is_null($start_origin) && isset($start_origin['x']) && isset($start_origin['y'])) {
      $start_origin['placement'] = self::PIECE_PLACEMENT_RELATIVE;
    }
    else {
      $start_origin['placement'] = self::PIECE_PLACEMENT_SPECIFIC_ONLY;
    }
    $side_info = array_merge(static::getSidesInfos($nb_sides), $start_origin);
    $side_info['start_status'] = $start_status;
    $side_info['specific_pieces'] = $specific_pieces;
    $this->sides[$side_info['id']] = $side_info;
  }

  public function alterSide($side_order, array $changes) {
    if (!empty($this->sides)) {
      foreach ($this->sides as $side_id => $side) {
        if ($side['start_order'] == $side_order) {
          foreach ($changes as $change => $new_value) {
            $this->sides[$side_id][$change] = $new_value;
          }
        }
      }
    }
    return $this;
  }

  public function getSides() {
    return $this->sides;
  }

  public function addSpecialCell($type, $location) {
    $this->specialCells[] = array(
      'type' => $type,
      'location' => $location,
    );
  }

  public function getSpecialCells() {
    return $this->specialCells;
  }

  public function getDirections() {
    return $this->directions;
  }

  public function getDirection($orientation) {
    $directions = $this->getDirections();
    if (!isset($directions[$orientation])) {
      throw new GridInvalidException('Unknown direction.');
    }
    return $directions[$orientation];
  }

}
