<?php

namespace Djambi\Moves;

use Djambi\Exceptions\DisallowedActionException;
use Djambi\GameOptions\StandardRuleset;
use Djambi\Gameplay\Cell;
use Djambi\Gameplay\Event;
use Djambi\Gameplay\Piece;
use Djambi\Strings\Glossary;
use Djambi\Strings\GlossaryTerm;

class Murder extends BaseMoveInteraction implements MoveInteractionInterface {

  public static function isTriggerable(Move $move, Piece $target = NULL, $allow_interactions = TRUE) {
    $piece = $move->getSelectedPiece();
    if (!empty($target) && static::checkMurderingPossibility($piece, $target, $allow_interactions)) {
      if (!$piece->getDescription()->hasHabilitySignature()) {
        $move->triggerInteraction(new static($move, $target));
        return TRUE;
      }
      else {
        $move->triggerKill($target, $piece->getPosition());
        if ($piece->getPosition()->getType() == Cell::TYPE_THRONE && !$piece->getDescription()->hasHabilityAccessThrone()) {
          $event = new Event(new GlossaryTerm(Glossary::EVENT_ASSASSIN_GOLDEN_MOVE, array(
            '!piece_id' => $piece->getId(),
          )));
          $move->triggerEvent($event);
        }
        return TRUE;
      }
    }
    return FALSE;
  }

  public static function checkMurderingPossibility(Piece $piece, Piece $occupant, $allow_interactions) {
    $can_attack = FALSE;
    if ($occupant->isAlive() && $piece->getDescription()->hasHabilityKillByAttack()) {
      if (!$allow_interactions) {
        $extra_interactions = static::allowExtraInteractions($piece);
        if (!$extra_interactions && !$occupant->getDescription()->hasHabilityAccessThrone()) {
          return FALSE;
        }
      }
      if (!$piece->getDescription()->hasHabilityKillThroneLeader() && $occupant->getPosition()->getType() == Cell::TYPE_THRONE) {
        $can_attack = FALSE;
      }
      else {
        $canibalism = $piece->getBattlefield()->getGameManager()->getOption(StandardRuleset::RULE_CANIBALISM);
        if ($canibalism == 'yes') {
          $can_attack = TRUE;
        }
        elseif ($canibalism == 'vassals') {
          $can_attack = ($occupant->getFaction()->getId() != $piece->getFaction()->getId()) ? TRUE : FALSE;
        }
        elseif ($canibalism == 'ethical' && !$occupant->getFaction()->getControl()->isAlive()) {
          $can_attack = FALSE;
        }
        else {
          $can_attack = ($occupant->getFaction()->getControl()->getId() != $piece->getFaction()->getControl()->getId()) ? TRUE : FALSE;
        }
      }
    }
    return $can_attack;
  }

  public function findPossibleChoices() {
    $this->setPossibleChoices($this->getActingFaction()->getBattlefield()
      ->getFreeCells($this->getSelectedPiece(), FALSE, TRUE, $this->getTriggeringMove()->getSelectedPiece()->getPosition()));
    return $this;
  }

  public function executeChoice(Cell $cell) {
    $possible_destinations = $this->getPossibleChoices();
    if (!isset($possible_destinations[$cell->getName()])) {
      throw new DisallowedActionException(new GlossaryTerm(Glossary::EXCEPTION_KILL_WRONG_GRAVE,
        array('%location' => $cell->getName())));
    }
    $this->getSelectedPiece()->dieDieDie($cell, $this->getTriggeringMove());
    return parent::executeChoice($cell);
  }

  public function revert() {
    $this->getSelectedPiece()->setPosition($this->getTriggeringMove()->getDestination())
      ->setAlive(TRUE);
  }
}
