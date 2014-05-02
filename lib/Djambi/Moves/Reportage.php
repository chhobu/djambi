<?php

namespace Djambi\Moves;


use Djambi\Exceptions\DisallowedActionException;
use Djambi\GameOptions\StandardRuleset;
use Djambi\Gameplay\Cell;
use Djambi\Strings\Glossary;
use Djambi\Strings\GlossaryTerm;

class Reportage extends BaseMoveInteraction implements MoveInteractionInterface {

  public function setTargets($choices) {
    $this->setPossibleChoices($choices);
    return $this;
  }

  protected function checkVictimCanBeKilled(Cell $cell) {
    $victim = $cell->getOccupant();
    if (empty($victim)) {
      throw new DisallowedActionException(new GlossaryTerm(Glossary::EXCEPTION_REPORTAGE_DISALLOWED));
    }
    elseif (!$victim->isAlive()) {
      throw new DisallowedActionException(new GlossaryTerm(Glossary::EXCEPTION_REPORTAGE_DEAD));
    }
    elseif ($this->getActingFaction()->getBattlefield()->getGameManager()->getOption(StandardRuleset::RULE_REPORTERS) == 'pravda'
      && $victim->getFaction()->getControl()->getId() == $this->getSelectedPiece()->getFaction()->getControl()->getId()) {
      throw new DisallowedActionException(new GlossaryTerm(Glossary::EXCEPTION_REPORTAGE_OWN));
    }
    return $this;
  }

  public function findPossibleChoices() {
    return $this;
  }

  public function executeChoice(Cell $cell) {
    $this->checkVictimCanBeKilled($cell);
    $cell->getOccupant()->dieDieDie($cell);
    $this->checkCompleted();
    return $this;
  }
}
