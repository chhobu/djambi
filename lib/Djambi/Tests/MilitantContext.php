<?php

namespace Djambi\Tests;


use Behat\Gherkin\Node\TableNode;
use Djambi\Cell;
use Djambi\Exceptions\DisallowedActionException;
use Djambi\Faction;
use Djambi\Factories\GameDispositionsFactory;
use Djambi\BaseGrid;
use Djambi\Moves\Murder;
use Djambi\PieceDescriptions\Leader;
use Djambi\PieceDescriptions\Militant;

class MilitantContext extends DjambiTestBaseContext {
  /**
   * @Given /^a custom (\d+) squares chessboard with the following pieces:$/
   */
  public function initiateNewCustomGame($nb_cells, TableNode $pieces_table) {
    $factory = GameDispositionsFactory::buildNewCustomDisposition();
    $factory->setShape(BaseGrid::SHAPE_CARDINAL);
    $dimenstions = sqrt($nb_cells);
    $factory->setDimensions($dimenstions, $dimenstions);
    $sides = BaseGrid::getSidesInfos();
    $factions_pieces = array();
    $factions_orders = array();
    $deads = array();
    $types = array();
    foreach ($pieces_table->getHash() as $key => $line) {
      $types[$line['side']][$line['piece type']][$key] = $line['status'];
      switch ($line['piece type']) {
        case ('Militant'):
        case ('M'):
          $piece = new Militant(count($types[$line['side']][$line['piece type']]), $line['position']);
          break;

        case('Leader'):
        case('L'):
          $piece = new Leader(count($types[$line['side']][$line['piece type']]), $line['position']);
          break;

        default:
          throw new \Exception("Unrecognized piece type : " . $line['piece type']);
      }
      $factions_pieces[$line['side']][$key] = $piece;
      foreach ($sides as $side) {
        if ($side['name'] == $line['side']) {
          $factions_orders[$line['side']] = $side['start_order'];
          break;
        }
      }
      if ($line['status'] == 'dead') {
        $deads[] = $line['position'];
      }
      if (!isset($factions_orders[$line['side']])) {
        $factions_orders[$line['side']] = $key;
      }
    }
    array_multisort($factions_orders, $factions_pieces);
    foreach ($factions_pieces as $pieces) {
      $factory->addSide(array(), Faction::STATUS_READY, $pieces);
    }
    $this->getGameFactory()->setDisposition($factory->deliverDisposition());
    $this->setGame($this->getGameFactory()->createGameManager());
    foreach ($deads as $dead) {
      $cell = $this->getGame()->getBattlefield()->findCellByName($dead);
      assertNotNull($cell);
      $cell->getOccupant()->setAlive(FALSE);
    }
    $this->getGame()->play();
    assertInstanceOf('\Djambi\Interfaces\GameManagerInterface', $this->getGame());
  }

  /**
   * @Given /^I am a Djambi player controlling the "([^"]*)" Side$/
   */
  public function giveFactionToCurrentPlayer($faction_name) {
    $faction = $this->findFaction($faction_name);
    $this->setCurrentPlayer($faction->getPlayer());
  }

  /**
   * @When /^I select the "([^"]*)" in "([^"]*)"$/
   */
  public function selectPiece($piece_type, $position) {
    $location = $this->getGame()->getBattlefield()->findCellByName($position);
    $this->getGame()->getBattlefield()->getCurrentMove()->selectPiece($location->getOccupant());
  }

  /**
   * @Then /^I should be able to move it to "([^"]*)"$/
   */
  public function assertAllowedMove($arg1) {
    $current_move = $this->getGame()->getBattlefield()->getCurrentMove();
    assertNotNull($current_move);
    $moves = explode(' ', $arg1);
    foreach ($moves as $move) {
      $cell = $this->getGame()->getBattlefield()->findCellByName($move);
      $current_move->tryMoveSelectedPiece($cell);
    }
  }

  /**
   * @Given /^I should get an error when trying to move it not to "([^"]*)"$/
   */
  public function assertOnlyAllowedMoves($destinations) {
    $this->assertDisallowedMoves($destinations, TRUE);
  }

  /**
   * @Given /^Piece "([^"]*)" from faction "([^"]*)" has been moved to throne$/
   */
  public function placePieceInThrone($type, $faction_name) {
    $faction = $this->findFaction($faction_name);
    $piece = $this->findPieceInFaction($type, $faction);
    $thrones = $this->getGame()->getBattlefield()->getSpecialCells(Cell::TYPE_THRONE);
    $piece->setPosition($this->getGame()->getBattlefield()->findCellByName(current($thrones)));
  }

  /**
   * @Then /^I should get an error when trying to move it to "([^"]*)"$/
   */
  public function assertDisallowedMoves($destinations, $negate = FALSE) {
    $cells = $this->getGame()->getBattlefield()->getCells();
    $moves = explode(' ', str_replace(array(',', 'or'), '', $destinations));
    foreach ($cells as $cell) {
      if ((in_array($cell->getName(), $moves) && !$negate) || ($negate && !in_array($cell->getName(), $moves))) {
        try {
          $this->getGame()->getBattlefield()->getCurrentMove()->moveSelectedPiece($cell);
          throw new \Exception("Move to " . $cell->getName() . " seems OK.");
        }
        catch (DisallowedActionException $e) {
          continue;
        }
      }
    }
  }

  /**
   * @Given /^I move it to "([^"]*)"$/
   */
  public function executeMove($destination) {
    $cell = $this->getGame()->getBattlefield()->findCellByName($destination);
    $this->getGame()->getBattlefield()->getCurrentMove()->moveSelectedPiece($cell);
  }

  /**
   * @Then /^the piece "([^"]*)" from faction "([^"]*)" should be selected$/
   */
  public function assertPieceSelectionFromInteraction($piece, $faction) {
    $faction = $this->findFaction($faction);
    $piece = $this->findPieceInFaction($piece, $faction);
    $interaction = $this->getGame()->getBattlefield()->getCurrentMove()->getFirstInteraction();
    assertNotNull($interaction);
    $selected = $interaction->getSelectedPiece();
    assertNotNull($selected);
    assertEquals($piece->getId(), $selected->getId());
  }

  /**
   * @Then /^the piece "([^"]*)" from faction "([^"]*)" should be dead$/
   */
  public function assertPieceDeath($piece, $faction) {
    $faction = $this->findFaction($faction);
    $piece = $this->findPieceInFaction($piece, $faction);
    assertFalse($piece->isAlive(), "Piece " . $piece->getId() . " is not dead.");
  }

  /**
   * @Given /^I should get an error when trying to bury it in "([^"]*)"$/
   */
  public function iShouldGetAnErrorWhenTryingToBuryItIn($destinations) {
    $cells = $this->getGame()->getBattlefield()->getCells();
    $moves = explode(' ', str_replace(array(',', 'or'), '', $destinations));
    foreach ($cells as $cell) {
      if (in_array($cell->getName(), $moves)) {
        try {
          $interaction = $this->getGame()->getBattlefield()->getCurrentMove()->getFirstInteraction();
          if ($interaction instanceof Murder) {
            $interaction->moveSelectedPiece($cell);
            throw new \Exception("Placing dead piece in an occupied cell (" . $cell->getName() . ") seems to be possible.");
          }
          else {
            throw new \Exception("Not in a murder interaction.");
          }
        }
        catch (DisallowedActionException $e) {
          continue;
        }
      }
    }
  }

  /**
   * @Given /^I should be able to place it in "([^"]*)"$/
   */
  public function iShouldBeAbleToPlaceItIn($position) {
    $cell = $this->getGame()->getBattlefield()->findCellByName($position);
    $interaction = $this->getGame()->getBattlefield()->getCurrentMove()->getFirstInteraction();
    if ($interaction instanceof Murder) {
      $interaction->moveSelectedPiece($cell);
    }
  }

  /**
   * @Given /^my turn shall end$/
   */
  public function assertNewTurn() {
    assertNull($this->getGame()->getBattlefield()->getCurrentMove()->getSelectedPiece());
  }

  /**
   * @Given /^I shall have won the game$/
   */
  public function iShallHaveWonTheGame() {
    assertTrue($this->getGame()->isFinished(), "Game status is " . $this->getGame()->getStatus());
    $ranking = $this->getCurrentPlayer()->getFaction()->getRanking();
    assertTrue($ranking == 1, "Current player has ranking " . $ranking);
  }
}
