<?php
/**
 * @file
 * Description des différentes dispositions de jeu de Djambi possibles
 */

namespace Djambi;
use Djambi\Stores\GameOptionsStore;
use Djambi\Stores\GameOptionsStoreStandardRuleset;

/**
 * Class DjambiGameDisposition
 */
abstract class GameDisposition  {
  /** @var Grid $grid */
  protected $grid;
  /** @var int $nb */
  protected $nbPlayers = 4;
  /** @var GameOptionsStore $optionsStore */
  protected $optionsStore;

  /**
   * @return int
   */
  public function getNbPlayers() {
    return $this->nbPlayers;
  }

  /**
   * @return Grid
   */
  public function getGrid() {
    return $this->grid;
  }

  protected function setNbPlayers($nb_players) {
    $this->nbPlayers = $nb_players;
    return $this;
  }

  protected function setGrid(Grid $scheme) {
    $this->grid = $scheme;
    return $this;
  }

  public function getName() {
    return str_replace('Djambi\GameDispositions\GameDisposition', '', get_class($this));
  }

  protected function useStandardRuleset() {
    $this->optionsStore = new GameOptionsStoreStandardRuleset();
  }

  public function getOptionsStore() {
    if (empty($this->optionsStore)) {
      $this->useStandardRuleset();
    }
    return $this->optionsStore;
  }

  protected function setOptionsStore(GameOptionsStore $store) {
    $this->optionsStore = $store;
  }

}
