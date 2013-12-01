<?php
namespace Djambi;

use Djambi\Interfaces\IAInterface;
use Djambi\Players\ComputerPlayer;

abstract class IA implements IAInterface {
  /* @var string $name */
  private $name;
  /* @var ComputerPlayer $faction */
  private $player;

  protected function __construct(ComputerPlayer $player, $name = 'DefaultBot') {
    $this->player = $player;
    $this->name = $name;
  }

  public static function instanciate(ComputerPlayer $player, $name = NULL) {
    if (is_null($name)) {
      return new static($player);
    }
    else {
      return new static($player, $name);
    }
  }

  public function getName() {
    return $this->name;
  }

  protected function getPlayer() {
    return $this->player;
  }

  protected function getBattlefield() {
    return $this->getPlayer()->getFaction()->getBattlefield();
  }

  public function play() {
    $available_moves = array();
    foreach ($this->getPlayer()->getFaction()->getControlledPieces() as $piece) {
      if ($piece->isMovable()) {
        foreach ($piece->getAllowableMoves() as $destination) {
          $move = new Move($this->getPlayer()->getFaction());
          $move->selectPiece($piece);
          $move->tryMoveSelectedPiece($destination);
          $available_moves[] = $move;
        }
      }
    }
    if (empty($available_moves)) {
      $this->getPlayer()->getFaction()->skipTurn();
    }
    else {
      $move = $this->decideMove($available_moves);
      $move->getSelectedPiece()->executeMove($move);
      while (!$move->isCompleted()) {
        $interaction = $move->getFirstInteraction();
        if (!empty($interaction) && count($interaction->getPossibleChoices()) > 0) {
          $choice = $this->decideInteraction($interaction);
          $interaction->executeChoice($choice);
        }
        else {
          $this->getPlayer()->getFaction()->skipTurn();
          break;
        }
      }
    }
  }

}
