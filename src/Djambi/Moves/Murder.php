<?php

namespace Djambi\Moves;

use Djambi\Exceptions\DisallowedActionException;
use Djambi\GameOptions\StandardRuleset;
use Djambi\Gameplay\Cell;
use Djambi\Gameplay\Event;
use Djambi\Gameplay\Piece;
use Djambi\PieceDescriptions\Habilities\HabilityAccessThrone;
use Djambi\PieceDescriptions\Habilities\HabilityKillByAttack;
use Djambi\PieceDescriptions\Habilities\HabilityKillRuler;
use Djambi\PieceDescriptions\Habilities\RestrictionSignature;
use Djambi\Strings\Glossary;
use Djambi\Strings\GlossaryTerm;

class Murder extends BaseMoveInteraction implements MoveInteractionInterface {

  public static function isTriggerable(Move $move, Piece $target = NULL, $allow_interactions = TRUE) {
    $piece = $move->getSelectedPiece();
    if (!empty($target) && static::checkMurderingPossibility($piece, $target, $allow_interactions)) {
      if (!$piece->getDescription() instanceof RestrictionSignature) {
        $move->triggerInteraction(new self($move, $target));
        return TRUE;
      }
      else {
        $move->triggerKill($target, $piece->getPosition());
        if ($piece->getPosition()->getType() == Cell::TYPE_THRONE && !$piece->getDescription() instanceof HabilityAccessThrone) {
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
    if ($occupant->isAlive() && $piece->getDescription() instanceof HabilityKillByAttack) {
      if (!$allow_interactions) {
        $extra_interactions = static::allowExtraInteractions($piece);
        if (!$extra_interactions || !$occupant->getDescription() instanceof HabilityAccessThrone) {
          return FALSE;
        }
      }
      if (!$piece->getDescription() instanceof HabilityKillRuler && $occupant->getPosition()->getType() == Cell::TYPE_THRONE) {
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
    $this->getSelectedPiece()->setPosition($this->getTriggeringMove()->getDestination())->setAlive(TRUE);
  }

  public function getMessage() {
    return new GlossaryTerm(Glossary::INTERACTION_MURDER_MESSAGE, array(
      '!piece_id1' => $this->getTriggeringMove()->getSelectedPiece()->getId(),
      '!piece_id2' => $this->getSelectedPiece()->getId(),
    ));
  }

  public static function log(array &$items, array $interaction_history, array $turn_history) {
    $items[] = new GlossaryTerm(Glossary::INTERACTION_KILLED_LOG, array(
      '@piece_id' => $interaction_history['selectedPiece'],
      '%location' => $turn_history['move']['destination'],
    ));
    $items[] = new GlossaryTerm(Glossary::INTERACTION_CORPSE_LOG, array(
      '@corpse_id' => $interaction_history['selectedPiece'],
      '%origin' => $turn_history['move']['destination'],
      '%destination' => $interaction_history['choice'],
    ));
  }
}
