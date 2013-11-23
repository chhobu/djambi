<?php
namespace Djambi;

use Djambi\IA\DummyIA;
use Djambi\Players\ComputerPlayer;

class IA {
  /* @var string $name */
  protected $name;
  /* @var ComputerPlayer $faction */
  protected $player;
  /* @var string className */
  protected $className;

  public static function useIA(ComputerPlayer $player, $className) {
    if (class_exists($className) && $className != __CLASS__) {
      $ia = new $className($player);
      $ia->className = $className;
    }
    else {
      $ia = new DummyIA($player);
      $ia->className = 'DjambiIADummy';
    }
    return $ia;
  }

  protected function __construct(ComputerPlayer $player, $name = 'DefaultBot') {
    $this->player = $player;
    $this->name = $name;
  }

  public function getClassName() {
    return $this->className;
  }

  public function getName() {
    return $this->name;
  }

  protected function getPlayer() {
    return $this->player;
  }

  public function getBattlefield() {
    return $this->getPlayer()->getFaction()->getBattlefield();
  }

  public function play() {
    $available_moves = array();
    foreach ($this->getPlayer()->getFaction()->getControlledPieces() as $piece) {
      if ($piece->isMovable()) {
        foreach ($piece->getAllowableMoves() as $destination) {
          $available_moves[] = array_merge($piece->evaluateMove($destination), array(
            'piece' => $piece,
            'destination' => $destination,
          ));
        }
      }
    }
    if (empty($available_moves)) {
      $this->getPlayer()->getFaction()->skipTurn();
    }
    else {
      $move = $this->decideMove($available_moves);
      /* @var Piece $piece */
      $piece = $move['piece'];
      $piece->move($move['destination']);
      foreach ($move['interactions'] as $interaction) {
        switch ($interaction['type']) {
          case('manipulation'):
            $destination = $this->decideManipulation($interaction['choices']);
            $piece->manipulate($interaction['target'], $piece->getBattlefield()->findCellByName($destination));
            break;

          case('necromobility'):
            $destination = $this->decideDeadPlacement($interaction['choices']);
            $piece->necromove($interaction['target'], $destination);
            break;

          case('reportage'):
            $victim_id = $this->decideReportage($interaction['choices']);
            $victim = $this->getBattlefield()->getPieceById($victim_id);
            $piece->kill($victim, $victim->getPosition());
            break;

          case('murder'):
            $destination = $this->decideDeadPlacement($interaction['choices']);
            $piece->kill($interaction['target'], $piece->getBattlefield()->findCellByName($destination));
            break;

          case('throne_evacuation'):
            $destination = $this->decideEvacuation($interaction['choices']);
            $piece->evacuate($destination);
            break;
        }
      }
    }
    foreach ($this->getPlayer()->getFaction()->getControlledPieces() as $piece) {
      $piece->setMovable(FALSE);
    }
    $this->getBattlefield()->resetCells();
  }

  protected function decideMove($moves) {
    return $moves[array_rand($moves, 1)];
  }

  protected function decideDeadPlacement($choices) {
    return $choices[array_rand($choices, 1)];
  }

  protected function decideReportage($choices) {
    return $choices[array_rand($choices, 1)];
  }

  protected function decideEvacuation($choices) {
    return $choices[array_rand($choices, 1)];
  }

  protected function decideManipulation($choices) {
    return $choices[array_rand($choices, 1)];
  }

}
