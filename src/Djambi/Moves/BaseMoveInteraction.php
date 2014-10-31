<?php

namespace Djambi\Moves;

use Djambi\GameOptions\StandardRuleset;
use Djambi\Gameplay\Cell;
use Djambi\Gameplay\Piece;
use Djambi\Persistance\ArrayableInterface;
use Djambi\Persistance\PersistantDjambiTrait;

abstract class BaseMoveInteraction implements MoveInteractionInterface, ArrayableInterface {

  use PersistantDjambiTrait;

  /** @var Piece */
  protected $selectedPiece;
  /** @var  Move */
  protected $triggeringMove;
  /** @var Cell[] */
  protected $possibleChoices;
  /** @var Cell */
  protected $choice;

  protected function prepareArrayConversion() {
    $refs['selectedPiece'] = 'id';
    if (!empty($this->choice)) {
      $refs['choice'] = 'name';
    }
    else {
      $refs['possibleChoices'] = 'name';
    }
    $this->addDependantObjects($refs);
    return $this;
  }

  public static function fromArray(array $array, array $context = array()) {
    /** @var Move $move */
    $move = $context['move'];
    $grid = $move->getSelectedPiece()->getFaction()->getBattlefield();
    /** @var BaseMoveInteraction $interaction */
    $interaction = new static($context['move'], $grid->findPieceById($array['selectedPiece']));
    if (!empty($array['choice'])) {
      $interaction->choice = $grid->findCellByName($array['choice']);
    }
    elseif (!empty($array['possibleChoices']) && !empty($interaction->getTriggeringMove()->getSelectedPiece())) {
      $choices = array();
      /** @var Cell $cell */
      foreach ($array['possibleChoices'] as $cell_name) {
        $choices[$cell_name] = $grid->findCellByName($cell_name);
      }
      $interaction->setPossibleChoices($choices);
    }
    return $interaction;
  }

  protected function __construct(Move $move, Piece $selected_piece = NULL) {
    $this->triggeringMove = $move;
    if (!is_null($selected_piece)) {
      static::setSelectedPiece($selected_piece);
    }
    else {
      static::setSelectedPiece($move->getSelectedPiece());
    }
  }

  public function getTriggeringMove() {
    return $this->triggeringMove;
  }

  public function getSelectedPiece() {
    return $this->selectedPiece;
  }

  protected function setSelectedPiece(Piece $piece) {
    $this->selectedPiece = $piece;
    return $this;
  }

  public function getActingFaction() {
    return $this->getTriggeringMove()->getActingFaction();
  }

  public function isCompleted() {
    if (!empty($this->getChoice())) {
      return TRUE;
    }
    else {
      return FALSE;
    }
  }

  public function getPossibleChoices() {
    if (is_null($this->possibleChoices)) {
      $this->findPossibleChoices();
    }
    return $this->possibleChoices;
  }

  protected function setPossibleChoices(array $choices) {
    $this->possibleChoices = $choices;
    return $this;
  }

  public function getChoice() {
    return $this->choice;
  }

  protected function setChoice(Cell $choice) {
    $this->choice = $choice;
    return $this;
  }

  public function executeChoice(Cell $cell) {
    $this->setChoice($cell);
    $this->getTriggeringMove()->checkCompleted();
    return $this;
  }

  public static function allowExtraInteractions(Piece $selected) {
    $extra_interaction = FALSE;
    // Vérifie si la pièce dispose d'un droit d'interaction supplémentaire
    // lors d'une évacuation de trône :
    if ($selected->getFaction()->getBattlefield()->getGameManager()->getOption(StandardRuleset::RULE_EXTRA_INTERACTIONS) == 'extended') {
      if ($selected->getPosition()->getType() == Cell::TYPE_THRONE && !empty($target) && $target->getDescription()->hasHabilityAccessThrone()) {
        $extra_interaction = TRUE;
      }
    }
    return $extra_interaction;
  }

  public function isDealingWithPiecesOnly() {
    return FALSE;
  }

}
