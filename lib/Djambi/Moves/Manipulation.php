<?php

namespace Djambi\Moves;


use Djambi\Exceptions\DisallowedActionException;
use Djambi\GameOptions\StandardRuleset;
use Djambi\Gameplay\Cell;
use Djambi\Gameplay\Piece;
use Djambi\Strings\Glossary;
use Djambi\Strings\GlossaryTerm;

class Manipulation extends BaseMoveInteraction implements MoveInteractionInterface {

  public static function isTriggerable(Move $move, Piece $target = NULL, $allow_interactions = TRUE) {
    $piece = $move->getSelectedPiece();
    if (!empty($target) && static::checkManipulatingPossibility($piece, $target, $allow_interactions)) {
      if ($piece->getPosition()->getType() != Cell::TYPE_THRONE) {
        $move->triggerInteraction(new static($move, $target));
      }
      else {
        $move->triggerEvent(array(
          'type' => 'diplomate_golden_move',
          'target' => $target,
          'position' => $piece->getPosition(),
        ));
      }
      return TRUE;
    }
    return FALSE;
  }

  public static function triggerGoldenMove(Move $move, Piece $target, Cell $destination) {
    $golden_move = new static($move, $target);
    $move->triggerInteraction($golden_move);
    $golden_move->executeChoice($destination);
  }

  public static function checkManipulatingPossibility(Piece $piece, Piece $occupant, $allow_interactions) {
    $can_manipulate = FALSE;
    if ($occupant->isAlive() && $piece->getDescription()->hasHabilityMoveLivingPieces()) {
      if (!$allow_interactions) {
        if (!static::allowExtraInteractions($piece) && $piece->getPosition()->getType() == Cell::TYPE_THRONE
        && $occupant->getDescription()->hasHabilityAccessThrone()) {
          return FALSE;
        }
      }
      $manipulation_rule = $piece->getBattlefield()->getGameManager()->getOption(StandardRuleset::RULE_DIPLOMACY);
      if ($manipulation_rule == 'vassal') {
        $can_manipulate = ($occupant->getFaction()->getId() != $piece->getFaction()->getId()) ? TRUE : FALSE;
      }
      else {
        $can_manipulate = ($occupant->getFaction()->getControl()->getId() != $piece->getFaction()->getControl()->getId()) ? TRUE : FALSE;
      }
    }
    return $can_manipulate;
  }

  public function findPossibleChoices() {
    $this->setPossibleChoices($this->getActingFaction()->getBattlefield()
      ->getFreeCells($this->getSelectedPiece(), TRUE, FALSE, $this->getTriggeringMove()->getSelectedPiece()->getPosition()));
    return $this;
  }

  public function executeChoice(Cell $cell) {
    $possible_destinations = $this->getPossibleChoices();
    if (!isset($possible_destinations[$cell->getName()])) {
      throw new DisallowedActionException(new GlossaryTerm(Glossary::EXCEPTION_MANIPULATION_BAD_DESTINATION,
        array('%piece_id' => $this->getSelectedPiece()->getId(), '%location' => $cell->getName())));
    }
    $this->getSelectedPiece()->setPosition($cell);
    return parent::executeChoice($cell);
  }

  public function revert() {
    $this->getSelectedPiece()->setPosition($this->getTriggeringMove()->getDestination());
  }

}
