<?php
/**
 * @file
 * Description des différentes dispositions de jeu de Djambi possibles
 */

namespace Djambi;
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
    $this->optionsStore = new StandardRuleset();
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
