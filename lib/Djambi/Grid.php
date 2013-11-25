<?php
/**
 * @file
 * Implémente la notion de schéma de jeu (forme et taille des grilles,
 * disposition initiale des pièces, localisation des cases spéciales,...) et
 * fournit des schémas pour des cas classiques.
 */

namespace Djambi;
use Djambi\Exceptions\GridInvalidException;

/**
 * Class DjambiBattlefieldScheme
 */
class Grid {
  const DISPOSITION_HEXAGONAL = 'hexagonal';
  const DISPOSITION_CARDINAL = 'cardinal';

  /* @var PieceDescription[] $pieceScheme */
  protected $pieceScheme = array();
  /* @var string $disposition */
  protected $disposition;
  /* @var int $rows */
  protected $rows;
  /* @var int $cols */
  protected $cols;
  /* @var array $specialCells */
  protected $specialCells;
  /* @var array $sides */
  protected $sides;
  /* @var array $allowableDispotions */
  protected $allowableDispositions = array(self::DISPOSITION_CARDINAL, self::DISPOSITION_HEXAGONAL);
  /* @var array $directions */
  protected $directions = array();
  /* @var array $settings */
  protected $settings = array();

  public function __construct($settings = NULL) {
    $cols = isset($settings['cols']) ? $settings['cols'] : 9;
    $rows = isset($settings['rows']) ? $settings['rows'] : 9;
    if (isset($settings['disposition']) && $settings['disposition'] == self::DISPOSITION_HEXAGONAL) {
      $this->useHexagonalGrid($rows, $cols);
    }
    else {
      $this->useStandardGrid($rows, $cols);
    }
    $this->specialCells = isset($settings['special_cells']) ? $settings['special_cells'] : $this->specialCells;
    $this->setSettings($settings);
    $this->useStandardPieces();
  }

  protected function useStandardPieces() {
    $this->addPiece('\Djambi\PieceDescriptions\Leader', NULL, array('x' => 0, 'y' => 0));
    $this->addPiece('\Djambi\PieceDescriptions\Diplomate', NULL, array('x' => 0, 'y' => 1));
    $this->addPiece('\Djambi\PieceDescriptions\Reporter', NULL, array('x' => -1, 'y' => 0));
    $this->addPiece('\Djambi\PieceDescriptions\Assassin', NULL, array('x' => 1, 'y' => 0));
    $this->addPiece('\Djambi\PieceDescriptions\Necromobile', NULL, array('x' => 0, 'y' => 2));
    $this->addPiece('\Djambi\PieceDescriptions\Militant', 1, array('x' => -2, 'y' => 0));
    $this->addPiece('\Djambi\PieceDescriptions\Militant', 2, array('x' => 2, 'y' => 0));
    $this->addPiece('\Djambi\PieceDescriptions\Militant', 3, array('x' => -1, 'y' => 1));
    $this->addPiece('\Djambi\PieceDescriptions\Militant', 4, array('x' => 1, 'y' => 1));
  }

  protected function useStandardGrid($cols = 9, $rows = 9) {
    $this->setCols($cols);
    $this->setRows($rows);
    $this->setDispostion(self::DISPOSITION_CARDINAL);
    $this->addSpecialCell(Cell::TYPE_THRONE, array('x' => ceil($rows / 2), 'y' => ceil($cols / 2)));
    $this->useCardinalDirections(TRUE);
  }

  protected function useHexagonalGrid($cols = 9, $rows = 9) {
    $this->setCols($cols);
    $this->setRows($rows);
    $this->setDispostion(self::DISPOSITION_HEXAGONAL);
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

  protected function addAllowableDispositions($disposition) {
    if (!in_array($disposition, $this->allowableDispositions)) {
      $this->allowableDispositions[] = $disposition;
      return TRUE;
    }
    else {
      return FALSE;
    }
  }

  protected function setDispostion($disposition) {
    if (!in_array($disposition, $this->allowableDispositions)) {
      throw new GridInvalidException('Unknown disposition');
    }
    else {
      $this->disposition = $disposition;
    }
  }

  public function getDisposition() {
    return $this->disposition;
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

  protected function addPiece($class, $identifier, $start_scheme) {
    /* @var PieceDescription $piece */
    $piece = new $class($identifier, $start_scheme);
    $this->pieceScheme[$piece->getShortname()] = $piece;
  }

  public function getPieceScheme() {
    return $this->pieceScheme;
  }

  public static function getSidesInfos($i = NULL) {
    $factions = array();
    $factions['R'] = array(
      'id' => 'R',
      'name' => 'Red',
      'class' => 'rouge',
      'start_order' => 1,
    );
    $factions['B'] = array(
      'id' => 'B',
      'name' => 'Blue',
      'class' => 'bleu',
      'start_order' => 2,
    );
    $factions['J'] = array(
      'id' => 'J',
      'name' => 'Yellow',
      'class' => 'jaune',
      'start_order' => 3,
    );
    $factions['V'] = array(
      'id' => 'V',
      'name' => 'Green',
      'class' => 'vert',
      'start_order' => 4,
    );
    if (is_null($i)) {
      return $factions;
    }
    else {
      foreach ($factions as $faction) {
        if ($faction['start_order'] == $i) {
          return $faction;
        }
      }
      throw new GridInvalidException("Undescribed faction : #" . $i);
    }
  }

  protected function addSide($start_origin, $start_status = Faction::STATUS_READY) {
    $nb_sides = count($this->sides) + 1;
    $side_info = array_merge(self::getSidesInfos($nb_sides), $start_origin);
    $side_info['start_status'] = $start_status;
    $this->sides[] = $side_info;
  }

  public function getSides() {
    return $this->sides;
  }

  protected function addSpecialCell($type, $location) {
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
    if (isset($directions[$orientation])) {
      return $directions[$orientation];
    }
    else {
      throw new GridInvalidException('Unknown direction.');
    }
  }

  public function getSettings() {
    return $this->settings;
  }

  protected function setSettings($settings) {
    if (is_array($settings)) {
      $this->settings = $settings;
    }
    return $this;
  }

}
