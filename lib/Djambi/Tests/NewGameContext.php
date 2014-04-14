<?php

namespace Djambi\Tests;

use Behat\Gherkin\Node\TableNode;
use Djambi\Cell;
use Djambi\Faction;
use Djambi\Factories\GameDispositionsFactory;
use Djambi\BasicGameManager;
use Djambi\Players\HumanPlayer;

class NewGameContext extends DjambiTestBaseContext {

  /**
   * @Given /^I am a Djambi Player$/
   */
  public function initiateCurrentPlayer() {
    $this->setCurrentPlayer(new HumanPlayer($this->pickPlayerName()));
  }

  /**
   * @When /^I initiate a new (\d+) players game in a standard grid$/
   */
  public function initiateNewStandardGame($nb_players) {
    $this->intiateNewGame('std', $nb_players);
  }

  /**
   * @When /^I initiate a new (\d+) players game in an hexagonal grid$/
   */
  public function initiateNewHexagonalGame($nb_players) {
    $this->intiateNewGame('hex', $nb_players);
  }

  /**
   * @When /^I initiate a new (\d+) players game in a mini grid$/
   */
  public function initiateNewMiniGame($nb_players) {
    $this->intiateNewGame('mini', $nb_players);
  }

  protected function intiateNewGame($type, $nb_players) {
    $this->getGameFactory()->setMode(BasicGameManager::MODE_SANDBOX);
    $this->getGameFactory()->setDisposition(GameDispositionsFactory::loadDisposition($nb_players . $type));
    $this->getGameFactory()->addPlayer($this->getCurrentPlayer());
    $this->setGame($this->getGameFactory()->createGameManager());
    $this->getGame()->play();
  }

  /**
   * @Then /^I should have a (\d+) squares chessboard$/
   */
  public function assertNbCells($arg1) {
    $cells = $this->getGame()->getBattlefield()->getCells();
    $i = 0;
    foreach ($cells as $cell) {
      if ($cell->getType() != Cell::TYPE_DISABLED) {
        $i++;
      }
    }
    assertEquals($arg1, $i);
  }

  /**
   * @Given /^I should have (\d+) sides$/
   */
  public function assertNbFactions($arg1) {
    $factions = $this->getGame()->getBattlefield()->getFactions();
    assertEquals($arg1, count($factions));
  }

  /**
   * @Given /^I should have (\d+) pieces per side$/
   */
  public function assertNbPieces($arg1) {
    $pieces = $this->getCurrentPlayer()->getFaction()->getPieces();
    assertEquals($arg1, count($pieces));
  }

  /**
   * @Given /^I should have the following pieces positions:$/
   */
  public function assertPiecesPositions(TableNode $table) {
    foreach ($table->getHash() as $line) {
      foreach ($this->getGame()->getBattlefield()->getFactions() as $faction) {
        $found = FALSE;
        assertArrayHasKey($faction->getName(), $line);
        foreach ($faction->getPieces() as $piece) {
          $position_name = $piece->getPosition()->getName();
          $possible_pieces = explode(' or ', $line['piece type']);
          if ($position_name == $line[$faction->getName()]
          && (in_array($piece->getDescription()->getGenericName(), $possible_pieces)
          || in_array($piece->getDescription()->getShortname(), $possible_pieces)
          || in_array($piece->getDescription()->getImagePattern(), $possible_pieces))) {
            $found = TRUE;
            break;
          }
        }
        assertTrue($found, $line['piece type'] . " not in " . $line[$faction->getName()] . " for faction " . $faction->getName());
      }
    }
  }

  /**
   * @Given /^Side "([^"]*)" should be vassalized and controlled by "([^"]*)" side$/
   */
  public function assertFactionVassalized($vassalized, $controlled) {
    $faction1 = $this->findFaction($vassalized);
    $faction2 = $this->findFaction($controlled);
    assertEquals($faction1->getStatus(), Faction::STATUS_VASSALIZED);
    assertEquals($faction1->getControl()->getId(), $faction2->getId());
  }

}
