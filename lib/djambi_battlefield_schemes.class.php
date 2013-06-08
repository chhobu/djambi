<?php
class DjambiBattlefieldScheme {
  private $piece_scheme = array();
  private $disposition;
  private $rows;
  private $cols;
  private $special_cells;
  private $sides;
  private $allowable_dispositions = array('cardinal', 'hexagonal');
  private $directions = array();
  private $settings = array();

  public function __construct($settings) {
    $cols = isset($settings['cols']) ? $settings['cols'] : 9;
    $rows = isset($settings['rows']) ? $settings['rows'] : 9;
    if (isset($settings['disposition']) && $settings['disposition'] == 'hexagonal') {
      $this->useHexagonalGrid($rows, $cols);
    }
    else {
      $this->useStandardGrid($rows, $cols);
    }
    $this->special_cells = isset($settings['special_cells']) ? $settings['special_cells'] : $this->special_cells;
    $this->setSettings($settings);
    $this->useStandardPieces();
    return $this;
  }

  protected function useStandardPieces() {
    $this->addPiece('DjambiPieceLeader', NULL, array('x' => 0, 'y' => 0));
    $this->addPiece('DjambiPieceDiplomate', NULL, array('x' => 0, 'y' => 1));
    $this->addPiece('DjambiPieceReporter', NULL, array('x' => -1, 'y' => 0));
    $this->addPiece('DjambiPieceAssassin', NULL, array('x' => 1, 'y' => 0));
    $this->addPiece('DjambiPieceNecromobile', NULL, array('x' => 0, 'y' => 2));
    $this->addPiece('DjambiPieceMilitant', 1, array('x' => -2, 'y' => 0));
    $this->addPiece('DjambiPieceMilitant', 2, array('x' => 2, 'y' => 0));
    $this->addPiece('DjambiPieceMilitant', 3, array('x' => -1, 'y' => 1));
    $this->addPiece('DjambiPieceMilitant', 4, array('x' => 1, 'y' => 1));
  }

  protected function useStandardGrid($cols = 9, $rows = 9) {
    $this->setCols($cols);
    $this->setRows($rows);
    $this->setDispostion('cardinal');
    $this->addSpecialCell('throne', array('x' => ceil($rows / 2), 'y' => ceil($cols / 2)));
    $this->useCardinalDirections(TRUE);
  }

  protected function useHexagonalGrid($cols = 9, $rows = 9) {
    $this->setCols($cols);
    $this->setRows($rows);
    $this->setDispostion('hexagonal');
    $this->addSpecialCell('throne', array('x' => ceil($rows / 2), 'y' => ceil($cols / 2)));
    $this->useHexagonalDirections();
    return $this;
  }

  protected function useCardinalDirections($diagonals) {
    $directions = array();
    if (!$diagonals) {
      $directions['N'] = array('x' => 0, 'y' => -1, 'diagonal' => FALSE, 'left' => 'W', 'right' => 'E');
      $directions['E'] = array('x' => 1, 'y' => 0, 'diagonal' => FALSE, 'left' => 'N', 'right' => 'S');
      $directions['S'] = array('x' => 0, 'y' => 1, 'diagonal' => FALSE, 'left' => 'E', 'right' => 'W');
      $directions['W'] = array('x' => -1, 'y' => 0, 'diagonal' => FALSE, 'left' => 'S', 'right' => 'N');
    }
    else {
      $directions['N'] = array('x' => 0, 'y' => -1, 'diagonal' => FALSE, 'left' => 'NW', 'right' => 'NE');
      $directions['E'] = array('x' => 1, 'y' => 0, 'diagonal' => FALSE, 'left' => 'NE', 'right' => 'SE');
      $directions['S'] = array('x' => 0, 'y' => 1, 'diagonal' => FALSE, 'left' => 'SE', 'right' => 'SW');
      $directions['W'] = array('x' => -1, 'y' => 0, 'diagonal' => FALSE, 'left' => 'SW', 'right' => 'NW');
      $directions['NE'] = array('x' => 1, 'y' => -1, 'diagonal' => TRUE, 'left' => 'N', 'right' => 'E');
      $directions['SE'] = array('x' => 1, 'y' => 1, 'diagonal' => TRUE, 'left' => 'E', 'right' => 'S');
      $directions['SW'] = array('x' => -1, 'y' => 1, 'diagonal' => TRUE, 'left' => 'S', 'right' => 'W');
      $directions['NW'] = array('x' => -1, 'y' => -1, 'diagonal' => TRUE, 'left' => 'W', 'right' => 'N');
    }
    $this->directions = $directions;
    return $this;
  }

  protected function useHexagonalDirections() {
    $directions = array(
        'NE' => array('x' => 1, 'y' => -1, 'diagonal' => FALSE, 'left' => 'NW', 'right' => 'E', 'modulo_x' => TRUE),
        'E' => array('x' => 1, 'y' => 0, 'diagonal' => FALSE, 'left' => 'NE', 'right' => 'SE'),
        'SE' => array('x' => 1, 'y' => 1, 'diagonal' => FALSE, 'left' => 'E', 'right' => 'SW', 'modulo_x' => TRUE),
        'SW' => array('x' => -1, 'y' => 1, 'diagonal' => FALSE, 'left' => 'SE', 'right' => 'W', 'modulo_x' => TRUE),
        'W' => array('x' => -1, 'y' => 0, 'diagonal' => FALSE, 'left' => 'SW', 'right' => 'NW'),
        'NW' => array('x' => -1, 'y' => -1, 'diagonal' => FALSE, 'left' => 'W', 'right' => 'NE', 'modulo_x' => TRUE)
    );
    $this->directions = $directions;
    return $this;
  }

  protected function addAllowableDispositions($disposition) {
    if (!in_array($disposition, $this->allowable_dispositions)) {
      $this->allowable_dispositions[] = $disposition;
      return TRUE;
    }
    else {
      return FALSE;
    }
  }

  private function setDispostion($disposition) {
    if (!in_array($disposition, $this->allowable_dispositions)) {
      throw new Exception('Unknown disposition');
    }
    else {
      $this->disposition = $disposition;
    }
  }

  public function getDisposition() {
    return $this->disposition;
  }

  private function setRows($nb) {
    if ($nb <= 0) {
      throw new Exception('Not enough rows');
    }
    elseif ($nb > 26) {
      throw new Exception('Too many rows');
    }
    else {
      $this->rows = $nb;
    }
  }

  public function getRows() {
    return $this->rows;
  }

  private function setCols($nb) {
    if ($nb <= 0) {
      throw new Exception('Not enough columns');
    }
    elseif ($nb > 26) {
      throw new Exception('Too many colums');
    }
    else {
      $this->cols = $nb;
    }
  }

  public function getCols() {
    return $this->cols;
  }

  protected function addPiece($class, $identifier, $start_scheme) {
    $piece = new $class($identifier, $start_scheme);
    $this->piece_scheme[$piece->getShortname()] = $piece;
  }

  public function getPieceScheme() {
    return $this->piece_scheme;
  }

  protected function addSide($start_origin) {
    $this->sides[] = $start_origin;
  }

  public function getSides() {
    return $this->sides;
  }

  protected function addSpecialCell($type, $location) {
    if (is_array($location)) {
      $location = DjambiBattlefield::locateCell($location);
    }
    $this->special_cells[] = array(
        'type' => $type,
        'location' => $location
    );
  }

  public function getSpecialCells() {
    return $this->special_cells;
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
      throw new Exception('Unknown direction.');
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

class DjambiBattlefieldSchemeStandardGridWith4Sides extends DjambiBattlefieldScheme {
  public function __construct($settings) {
    $this->useStandardGrid(9, 9);
    $this->useStandardPieces();
    $this->addSide(array('x' => 1, 'y' => 9));
    $this->addSide(array('x' => 9, 'y' => 9));
    $this->addSide(array('x' => 9, 'y' => 1));
    $this->addSide(array('x' => 1, 'y' => 1));
    return $this;
  }
}

class DjambiBattlefieldSchemeHexagonalGridWith3Sides extends DjambiBattlefieldScheme {
  public function __construct($settings) {
    $this->useHexagonalGrid(9, 9);
    $this->useStandardPieces();
    $this->addSpecialCell('disabled', array('x' => 1, 'y' => 1));
    $this->addSpecialCell('disabled', array('x' => 2, 'y' => 1));
    $this->addSpecialCell('disabled', array('x' => 8, 'y' => 1));
    $this->addSpecialCell('disabled', array('x' => 9, 'y' => 1));
    $this->addSpecialCell('disabled', array('x' => 1, 'y' => 2));
    $this->addSpecialCell('disabled', array('x' => 8, 'y' => 2));
    $this->addSpecialCell('disabled', array('x' => 9, 'y' => 2));
    $this->addSpecialCell('disabled', array('x' => 1, 'y' => 3));
    $this->addSpecialCell('disabled', array('x' => 9, 'y' => 3));
    $this->addSpecialCell('disabled', array('x' => 9, 'y' => 4));
    $this->addSpecialCell('disabled', array('x' => 9, 'y' => 6));
    $this->addSpecialCell('disabled', array('x' => 1, 'y' => 7));
    $this->addSpecialCell('disabled', array('x' => 9, 'y' => 7));
    $this->addSpecialCell('disabled', array('x' => 1, 'y' => 8));
    $this->addSpecialCell('disabled', array('x' => 8, 'y' => 8));
    $this->addSpecialCell('disabled', array('x' => 9, 'y' => 8));
    $this->addSpecialCell('disabled', array('x' => 1, 'y' => 9));
    $this->addSpecialCell('disabled', array('x' => 2, 'y' => 9));
    $this->addSpecialCell('disabled', array('x' => 8, 'y' => 9));
    $this->addSpecialCell('disabled', array('x' => 9, 'y' => 9));
    $this->addSide(array('x' => 1, 'y' => 5));
    $this->addSide(array('x' => 7, 'y' => 9));
    $this->addSide(array('x' => 7, 'y' => 1));
  }
}

class DjambiBattlefieldSchemeMiniGridWith2Sides extends DjambiBattlefieldScheme {
  public function __construct($settings) {
    $this->useStandardGrid(7, 7);
    $this->addPiece('DjambiPieceLeader', NULL, array('x' => 0, 'y' => 0));
    $this->addPiece('DjambiPieceMilitant', 1, array('x' => 1, 'y' => 0));
    $this->addPiece('DjambiPieceMilitant', 2, array('x' => -1, 'y' => 0));
    if (isset($settings['surprise_piece'])) {
      $surprise = $settings['surprise_piece'];
    }
    else {
      $surprises = array('DjambiPieceAssassin', 'DjambiPieceReporter', 'DjambiPieceDiplomate');
      $surprise = $surprises[array_rand($surprises)];
      $settings['surprise_piece'] = $surprise;
    }
    $this->addPiece($surprise, NULL, array('x' => 0, 'y' => 1));
    $this->addSide(array('x' => 1, 'y' => 7));
    $this->addSide(array('x' => 7, 'y' => 1));
    $this->setSettings($settings);
  }
}