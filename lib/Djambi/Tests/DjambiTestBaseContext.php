<?php

namespace Djambi\Tests;

use Behat\Behat\Context\BehatContext;
use Djambi\Exceptions\FactionNotFoundException;
use Djambi\Exceptions\PieceNotFoundException;
use Djambi\Faction;
use Djambi\Factories\GameFactory;
use Djambi\Interfaces\GameManagerInterface;
use Djambi\Player;
use Djambi\Players\HumanPlayer;

//
// Require 3rd-party libraries here:
//
require_once 'PHPUnit/Autoload.php';
require_once 'PHPUnit/Framework/Assert/Functions.php';

spl_autoload_register(function ($class) {
  require_once '../../' . str_replace('\\', '/', $class) . '.php';
});

/**
 * Features context.
 */
class DjambiTestBaseContext extends BehatContext {
  /** @var HumanPlayer */
  private $currentPlayer;
  /** @var GameManagerInterface */
  private $game;
  /** @var GameFactory */
  private $gameFactory;
  /** @var array */
  protected $playerNames = array(
    'Machiavel',
    'Richelieu',
    'Sun-Tzu',
    'Metternich',
    'George W Bush',
    'Mazarin',
    'Von Clausewitz',
    'Kissinger',
    'Hannibal',
    'Darius',
  );

  /**
   * Initializes context.
   * Every scenario gets it's own context object.
   *
   * @param array $parameters context parameters (set them up through behat.yml)
   */
  protected function __construct(array $parameters) {
    $this->gameFactory = new GameFactory();
  }

  protected function getCurrentPlayer() {
    return $this->currentPlayer;
  }

  protected function setCurrentPlayer(Player $player) {
    $this->currentPlayer = $player;
    return $this;
  }

  /**
   * @param \Djambi\Interfaces\GameManagerInterface $game
   *
   * @return DjambiTestBaseContext
   */
  protected function setGame($game) {
    $this->game = $game;
    return $this;
  }

  /**
   * @return \Djambi\Interfaces\GameManagerInterface
   */
  protected function getGame() {
    return $this->game;
  }

  /**
   * @return \Djambi\Factories\GameFactory
   */
  protected function getGameFactory() {
    return $this->gameFactory;
  }

  protected function pickPlayerName() {
    shuffle($this->playerNames);
    return array_shift($this->playerNames);
  }

  protected function findFaction($string) {
    $factions = $this->getGame()->getBattlefield()->getFactions();
    foreach ($factions as $faction) {
      if ($string == $faction->getClass() || $string == $faction->getName() || $string == $faction->getId()) {
        return $faction;
      }
    }
    throw new FactionNotFoundException("Faction " . $string . " not found");
  }

  protected function findPieceInFaction($type, Faction $faction) {
    $type_elements = explode(' ', str_replace(array('#', 'n°'), '', $type));
    if (count($type_elements) > 1) {
      $number = is_numeric($type_elements[1]) ? $type_elements[1] : NULL;
      $type = $type_elements[0];
    }
    foreach ($faction->getPieces() as $piece) {
      if ($piece->getDescription()->getGenericName() == $type
      || $piece->getId() == $type
      || $piece->getDescription()->getShortname() == $type
      || $piece->getDescription()->getImagePattern() == $type) {
        if (empty($number) || $piece->getDescription()->getNum() == $number) {
          return $piece;
        }
      }
    }
    throw new PieceNotFoundException("Piece " . $type . " not found in faction " . $faction->getName());
  }

}
