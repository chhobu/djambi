<?php
/**
 * @file
 * Implémente la notion de schéma de jeu (forme et taille des grilles,
 * disposition initiale des pièces, localisation des cases spéciales,...) et
 * fournit des schémas pour des cas classiques.
 */

namespace Djambi\Grids;
use Djambi\Grids\Exceptions\InvalidGridException;
use Djambi\Gameplay\Cell;
use Djambi\Gameplay\Faction;
use Djambi\Persistance\ArrayableInterface;
use Djambi\Persistance\PersistantDjambiTrait;
use Djambi\PieceDescriptions\Assassin;
use Djambi\PieceDescriptions\Diplomat;
use Djambi\PieceDescriptions\Leader;
use Djambi\PieceDescriptions\Militant;
use Djambi\PieceDescriptions\Necromobile;
use Djambi\PieceDescriptions\PiecesContainer;
use Djambi\PieceDescriptions\PiecesContainerInterface;
use Djambi\PieceDescriptions\Reporter;
use Djambi\Strings\Glossary;
use Djambi\Strings\GlossaryTerm;

/**
 * Class DjambiBattlefieldScheme
 */
abstract class BaseGrid implements GridInterface, ArrayableInterface {
  use PersistantDjambiTrait;

  /* @var array $allowableDispotions */
  protected $allowableShapes = array(
    self::SHAPE_CARDINAL,
    self::SHAPE_HEXAGONAL,
  );
  /* @var PiecesContainer $pieceScheme */
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

  protected function prepareArrayConversion() {
    return $this;
  }

  protected function useStandardPieces() {
    $container = new PiecesContainer();
    $container->addPiece(new Leader(array('x' => 0, 'y' => 0, 'relative' => TRUE)));
    $container->addPiece(new Diplomat(array('x' => 0, 'y' => 1, 'relative' => TRUE)));
    $container->addPiece(new Reporter(array('x' => -1, 'y' => 0, 'relative' => TRUE)));
    $container->addPiece(new Assassin(array('x' => 1, 'y' => 0, 'relative' => TRUE)));
    $container->addPiece(new Necromobile(array('x' => 0, 'y' => 2, 'relative' => TRUE)));
    $container->addPiece(new Militant(array('x' => -2, 'y' => 0, 'relative' => TRUE)));
    $container->addPiece(new Militant(array('x' => 2, 'y' => 0, 'relative' => TRUE)));
    $container->addPiece(new Militant(array('x' => -1, 'y' => 1, 'relative' => TRUE)));
    $container->addPiece(new Militant(array('x' => 1, 'y' => 1, 'relative' => TRUE)));
    $this->pieceScheme = $container;
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
      throw new InvalidGridException('Unknown disposition');
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
      throw new InvalidGridException('Not enough rows');
    }
    elseif ($nb > 26) {
      throw new InvalidGridException('Too many rows');
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
      throw new InvalidGridException('Not enough columns');
    }
    elseif ($nb > 26) {
      throw new InvalidGridException('Too many colums');
    }
    else {
      $this->cols = $nb;
    }
  }

  public function getCols() {
    return $this->cols;
  }

  public function getPieceScheme() {
    return $this->pieceScheme;
  }

  public static function getSidesInfos($order = NULL) {
    $factions = array();
    $factions['t1'] = array(
      'id' => 't1',
      'name' => new GlossaryTerm(Glossary::SIDE_RED),
      'class' => 'red',
      'start_order' => 1,
    );
    $factions['t2'] = array(
      'id' => 't2',
      'name' => new GlossaryTerm(Glossary::SIDE_BLUE),
      'class' => 'blue',
      'start_order' => 2,
    );
    $factions['t3'] = array(
      'id' => 't3',
      'name' => new GlossaryTerm(Glossary::SIDE_YELLOW),
      'class' => 'yellow',
      'start_order' => 3,
    );
    $factions['t4'] = array(
      'id' => 't4',
      'name' => new GlossaryTerm(Glossary::SIDE_GREEN),
      'class' => 'green',
      'start_order' => 4,
    );
    if (!is_null($order)) {
      foreach ($factions as $faction) {
        if ($faction['start_order'] == $order) {
          return $faction;
        }
      }
      throw new InvalidGridException("Undescribed faction : #" . $order);
    }
    return $factions;
  }

  public function addSide(PiecesContainerInterface $container, $start_origin = NULL, $start_status = Faction::STATUS_READY) {
    $nb_sides = count($this->sides) + 1;
    $side_info = array_merge(static::getSidesInfos($nb_sides), is_array($start_origin) ? $start_origin : array());
    $side_info['start_status'] = $start_status;
    $side_info['start_origin'] = $start_origin;
    $side_info['pieces'] = $container;
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
      throw new InvalidGridException('Unknown direction.');
    }
    return $directions[$orientation];
  }

}
