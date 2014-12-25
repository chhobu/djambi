<?php

namespace Djambi\Moves;


use Djambi\Exceptions\DisallowedActionException;
use Djambi\GameOptions\StandardRuleset;
use Djambi\Gameplay\Cell;
use Djambi\Gameplay\Piece;
use Djambi\PieceDescriptions\Habilities\HabilityKillByProximity;
use Djambi\Strings\Glossary;
use Djambi\Strings\GlossaryTerm;

class Reportage extends BaseMoveInteraction implements MoveInteractionInterface {

  public static function isTriggerable(Move $move, Piece $target = NULL, $allow_interactions = TRUE) {
    $piece = $move->getSelectedPiece();
    if ($piece->getDescription() instanceof HabilityKillByProximity && $allow_interactions) {
      $grid = $piece->getFaction()->getBattlefield();
      $next_cells = $grid->findNeighbourCells($move->getDestination(), FALSE);
      $victims = array();
      foreach ($next_cells as $key) {
        $cell = $grid->findCell($key['x'], $key['y']);
        $occupant = $cell->getOccupant();
        if (!empty($occupant)) {
          if ($occupant->isAlive() && $occupant->getId() != $piece->getId()) {
            if ($grid->getGameManager()->getOption(StandardRuleset::RULE_REPORTERS) == 'foxnews' ||
              $occupant->getFaction()->getControl()->getId() != $piece->getFaction()->getControl()->getId()) {
              $canibalism = $grid->getGameManager()->getOption(StandardRuleset::RULE_CANIBALISM);
              if ($canibalism != 'ethical' || $occupant->getFaction()->getControl()->isAlive()) {
                $victims[$cell->getName()] = $occupant->getPosition();
              }
            }
          }
        }
      }
      if ($grid->getGameManager()->getOption(StandardRuleset::RULE_REPORTERS) == 'pravda' && count($victims) > 1) {
        $reportage = new static($move);
        $move->triggerInteraction($reportage->setTargets($victims));
      }
      elseif (!empty($victims)) {
        /* @var Cell $victim_cell */
        foreach ($victims as $victim_cell) {
          $move->triggerKill($victim_cell->getOccupant(), $victim_cell);
        }
      }
    }
    return TRUE;
  }

  public function setTargets($choices) {
    $this->setPossibleChoices($choices);
    return $this;
  }

  public function findPossibleChoices() {
    return $this;
  }

  public function executeChoice(Cell $cell) {
    $possible_destinations = $this->getPossibleChoices();
    if (!isset($possible_destinations[$cell->getName()])) {
      throw new DisallowedActionException(new GlossaryTerm(Glossary::EXCEPTION_REPORTAGE_BAD_VICTIM_CHOICE,
        array('%location' => $cell->getName())));
    }
    $cell->getOccupant()->dieDieDie($cell, $this->getTriggeringMove());
    $this->setSelectedPiece($cell->getOccupant());
    return parent::executeChoice($cell);
  }

  public function revert() {
    if (!empty($this->getChoice())) {
      $this->getChoice()->getOccupant()->setAlive(TRUE);
    }
  }

  public function getMessage() {
    return new GlossaryTerm(Glossary::INTERACTION_REPORTAGE_MESSAGE, array(
      '!piece_id' => $this->getTriggeringMove()->getSelectedPiece()->getId(),
    ));
  }

  public static function log(array &$items, array $interaction_history, array $turn_history) {
    $items[] = new GlossaryTerm(Glossary::INTERACTION_KILLED_LOG, array(
      '@piece_id' => $interaction_history['selectedPiece'],
      '%location' => $interaction_history['choice'],
    ));
  }

  public function isDealingWithPiecesOnly() {
    return TRUE;
  }


}
