<?php

namespace Djambi\Moves;

use Djambi\GameOptions\StandardRuleset;
use Djambi\Gameplay\Cell;
use Djambi\Gameplay\Piece;
use Djambi\Persistance\PersistantDjambiObject;

abstract class BaseMoveInteraction extends PersistantDjambiObject implements MoveInteractionInterface {
  /** @var Piece */
  protected $selectedPiece;
  /** @var Cell */
  protected $destination;
  /** @var  Move */
  protected $triggeringMove;
  /** @var Cell[] */
  protected $possibleChoices;
  /** @var boolean */
  protected $completed;

  protected function prepareArrayConversion() {
    $this->addPersistantProperties('completed');
    $this->addDependantObjects(array(
      'possibleChoices' => 'name',
      'selectedPiece' => 'id',
      'destination' => 'name',
    ));
    return parent::prepareArrayConversion();
  }

  public static function fromArray(array $array, array $context = array()) {
    /** @var Move $move */
    $move = $context['move'];
    $grid = $move->getSelectedPiece()->getFaction()->getBattlefield();
    /** @var BaseMoveInteraction $interaction */
    $interaction = new static($context['move'], $grid->findPieceById($array['selectedPiece']));
    if (!empty($array['possibleChoices']) && !empty($interaction->getTriggeringMove()->getSelectedPiece())) {
      $choices = array();
      /** @var Cell $cell */
      foreach ($array['possibleChoices'] as $cell_name) {
        $choices[] = $grid->findCellByName($cell_name);
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

  protected function checkCompleted() {
    $this->setCompleted(TRUE);
    return $this->getTriggeringMove()->checkCompleted();
  }

  public function isCompleted() {
    return $this->completed;
  }

  protected function setCompleted($bool) {
    $this->completed = $bool ? TRUE : FALSE;
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

}
