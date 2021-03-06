<?php
/**
 * @file
 * Description des différentes dispositions de jeu de Djambi possibles
 */

namespace Djambi;
use Djambi\Grids\HexagonalGridWith3Sides;
use Djambi\Grids\MiniGridWith2Sides;
use Djambi\Grids\StandardGridWith4Sides;
use Djambi\Stores\GameOptionsStore;
use Djambi\Stores\StandardRuleset;

/**
 * Class DjambiGameDisposition
 */
abstract class GameDisposition  {
  /** @var Grid $grid */
  private $grid;
  /** @var int $nb */
  private $nbPlayers = 4;
  /** @var GameOptionsStore $optionsStore */
  private $optionsStore;

  public function getNbPlayers() {
    return $this->nbPlayers;
  }

  public function getGrid() {
    if (is_null($this->grid)) {
      $this->useStandardGrid();
    }
    return $this->grid;
  }

  public function setNbPlayers($nb_players) {
    $this->nbPlayers = $nb_players;
    return $this;
  }

  public function setGrid(Grid $scheme) {
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

  public function useMiniGridWith2Sides($settings = array()) {
    $this->setGrid(new MiniGridWith2Sides($settings));
    return $this;
  }

  public function useStandardGrid($settings = array()) {
    $this->setGrid(new StandardGridWith4Sides($settings));
    return $this;
  }

  public function useHexagonalGrid($settings = array()) {
    $this->setGrid(new HexagonalGridWith3Sides($settings));
    return $this;
  }

}
