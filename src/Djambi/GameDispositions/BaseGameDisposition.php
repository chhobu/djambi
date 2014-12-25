<?php
/**
 * @file
 * Description des différentes dispositions de jeu de Djambi possibles
 */

namespace Djambi\GameDispositions;

use Djambi\Grids\GridInterface;
use Djambi\Grids\HexagonalGridWith3Sides;
use Djambi\Grids\MiniGridWith2Sides;
use Djambi\Grids\StandardGridWith4Sides;
use Djambi\Persistance\ArrayableInterface;
use Djambi\GameOptions\GameOptionsStore;
use Djambi\GameOptions\StandardRuleset;
use Djambi\Persistance\PersistantDjambiTrait;

/**
 * Class DjambiGameDisposition
 */
abstract class BaseGameDisposition implements DispositionInterface, ArrayableInterface {

  use PersistantDjambiTrait;

  /** @var GridInterface $grid */
  protected $grid;
  /** @var GameOptionsStore $optionsStore */
  protected $optionsStore;

  protected function prepareArrayConversion() {
    $this->addPersistantProperties(array(
      'grid',
      'nbPlayers',
      'optionsStore',
    ));
    return $this;
  }

  public static function fromArray(array $array, array $context = array()) {
    return new static();
  }

  /**
   * @return GridInterface
   */
  public function getGrid() {
    if (is_null($this->grid)) {
      $this->useStandardGrid();
    }
    return $this->grid;
  }

  public function setGrid(GridInterface $scheme) {
    $this->grid = $scheme;
    return $this;
  }

  public function getName() {
    return str_replace('Djambi\GameDispositions\GameDisposition', '', get_class($this));
  }

  public function useStandardRuleset() {
    $this->optionsStore = new StandardRuleset();
  }

  public function getOptionsStore() {
    if (is_null($this->optionsStore)) {
      $this->useStandardRuleset();
    }
    return $this->optionsStore;
  }

  public function setOptionsStore(GameOptionsStore $store) {
    $this->optionsStore = $store;
  }

  public function useMiniGridWith2Sides() {
    $this->setGrid(new MiniGridWith2Sides());
    return $this;
  }

  public function useStandardGrid() {
    $this->setGrid(new StandardGridWith4Sides());
    return $this;
  }

  public function useHexagonalGrid() {
    $this->setGrid(new HexagonalGridWith3Sides());
    return $this;
  }

}
