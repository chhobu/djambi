<?php

namespace Djambi\Grids;

use Djambi\PieceDescriptions\Assassin;
use Djambi\PieceDescriptions\Diplomat;
use Djambi\PieceDescriptions\Leader;
use Djambi\PieceDescriptions\Militant;
use Djambi\PieceDescriptions\PieceInterface;
use Djambi\PieceDescriptions\PiecesContainer;
use Djambi\PieceDescriptions\Reporter;


class MiniGridWith2Sides extends BaseGrid {

  /** @var PieceInterface */
  protected $surprise_piece;

  public function __construct($surprise_piece = NULL) {
    $this->useStandardGrid(7, 7);
    $container = new PiecesContainer();
    $container->addPiece(new Leader(array('x' => 0, 'y' => 0, 'relative' => TRUE)))
      ->addPiece(new Militant(array('x' => 1, 'y' => 0, 'relative' => TRUE)))
      ->addPiece(new Militant(array('x' => -1, 'y' => 0, 'relative' => TRUE)));
    if (empty($surprise_piece)) {
      $surprise_location = array('x' => 0, 'y' => 1, 'relative' => TRUE);
      $surprises = array(
        new Assassin($surprise_location),
        new Reporter($surprise_location),
        new Diplomat($surprise_location),
      );
      $surprise_piece = $surprises[array_rand($surprises)];
    }
    $container->addPiece($surprise_piece);
    $this->addSide($container, array('x' => 1, 'y' => 7));
    $this->addSide($container, array('x' => 7, 'y' => 1));
  }

  public static function fromArray(array $array, array $context = array()) {
    return new static($array['surprise_piece']);
  }

  protected function prepareArrayConversion() {
    $this->addPersistantProperties(array('surprise_piece'));
    return $this;
  }
}
