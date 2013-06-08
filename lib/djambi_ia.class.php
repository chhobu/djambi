<?php
class DjambiIA {
  private $name,
          $faction;

  public static function getDefaultIAClass() {
    return 'DjambiIADummy';
  }

  public function __construct(DjambiPoliticalFaction $faction, $name = 'SillyBot') {
    $this->faction = $faction;
    $this->name = $name;
  }

  public function getName() {
    return $this->name;
  }

  /**
   * @return DjambiPoliticalFaction
   */
  protected function getFaction() {
    return $this->faction;
  }

  public function getBattlefield() {
    return $this->getFaction()->getBattlefield();
  }

  public function play() {
    $available_moves = array();
    /* @var $piece DjambiPiece */
    foreach ($this->getFaction()->getControlledPieces() as $piece) {
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
      $this->getFaction()->skipTurn();
    }
    else {
      $move = $this->decideMove($available_moves);
      $piece = $move['piece'];
      $piece->move($move['destination']);
      foreach ($move['interactions'] as $interaction) {
        switch ($interaction['type']) {
          case('manipulation') :
            $destination = $this->decideManipulation($interaction['choices']);
            $piece->manipulate($interaction['target'], $destination);
            break;
          case('necromobility') :
            $destination = $this->decideDeadPlacement($interaction['choices']);
            $piece->necromove($interaction['target'], $destination);
            break;
          case('reportage') :
            $victim_id = $this->decideReportage($interaction['choices']);
            $victim = $this->getBattlefield()->getPieceById($victim_id);
            $piece->kill($victim, $victim->getPosition());
            break;
          case('murder') :
            $destination = $this->decideDeadPlacement($interaction['choices']);
            $piece->kill($interaction['target'], $destination);
            break;
          case('throne_evacuation') :
            $destination = $this->decideEvacuation($interaction['choices']);
            $piece->evacuate($destination);
            break;
        }
      }
    }
    foreach ($this->getFaction()->getControlledPieces() as $piece) {
      $piece->setMovable(FALSE);
    }
    $this->getBattlefield()->resetCells();
  }

  private function decideMove($moves) {
    return $moves[array_rand($moves, 1)];
  }

  private function decideDeadPlacement($choices) {
    return $choices[array_rand($choices, 1)];
  }

  private function decideReportage($choices) {
    return $choices[array_rand($choices, 1)];
  }

  private function decideEvacuation($choices) {
    return $choices[array_rand($choices, 1)];
  }

  private function decideManipulation($choices) {
    return $choices[array_rand($choices, 1)];
  }

}

class DjambiIADummy extends DjambiIA {
   public function __construct(DjambiPoliticalFaction $faction, $name = 'BetaBot') {
     parent::__construct($faction, $name);
   }
}