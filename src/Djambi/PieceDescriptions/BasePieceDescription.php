<?php
namespace Djambi\PieceDescriptions;

use Djambi\Grids\Exceptions\InvalidGridException;
use Djambi\Persistance\ArrayableInterface;
use Djambi\Persistance\PersistantDjambiTrait;
use Djambi\Strings\GlossaryTerm;

abstract class BasePieceDescription implements ArrayableInterface, PieceInterface {

  use PersistantDjambiTrait;

  const PIECE_VALUE = 1;

  /** @var PiecesContainer */
  protected $container;
  /** @var string : type de pièce */
  protected $type;
  /** @var string : nom court de la pièce */
  protected $shortname;
  /** @var GlossaryTerm */
  protected $longName;
  /** @var array : position de départ (par rapport au chef, coordonnées x, y) */
  protected $startPosition;
  /** @var int : valeur de la pièce */
  protected $value;

  protected function describePiece($type, $generic_shortname, GlossaryTerm $generic_name, $start_position) {
    $this->type = $type;
    $this->shortname = $generic_shortname;
    $this->longName = $generic_name;
    if (!is_array($start_position)) {
      $this->setStartCellName($start_position);
    }
    else {
      $this->setStartPosition($start_position);
    }
    $this->setValue(static::PIECE_VALUE);
  }

  protected function prepareArrayConversion() {
    $this->addPersistantProperties(array(
      'startPosition',
    ));
    return $this;
  }

  public static function fromArray(array $array, array $context = array()) {
    $piece = new static($array['startPosition']);
    return $piece;
  }

  public function getContainer() {
    return $this->container;
  }

  public function setContainer(PiecesContainerInterface $container) {
    $this->container = $container;
    return $this;
  }

  public function getType() {
    return $this->type;
  }

  public function getShortname() {
    return $this->shortname;
  }

  public function getLongname() {
    return $this->longName;
  }

  public function getRuleUrl() {
    return 'http://djambi.net/regles/' . $this->type;
  }

  public function getStartPosition() {
    return $this->startPosition;
  }

  protected function setStartPosition($position) {
    if (!is_array($position) || !isset($position['x']) || !isset($position['y'])) {
      throw new InvalidGridException("Invalid start position for piece " . $this->getShortname());
    }
    $this->startPosition = $position;
    return $this;
  }

  protected function setStartCellName($cell_name) {
    $this->startPosition = $cell_name;
    return $this;
  }

  public function getValue() {
    return $this->value;
  }

  protected function setValue($value) {
    $this->value = $value;
    return $this;
  }

}
