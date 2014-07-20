<?php
/**
 * @file
 * Déclare la classe DjambiPoliticalFaction, qui gère les différents camps
 * d'une partie de Djambi.
 */

namespace Djambi\Gameplay;

use Djambi\Exceptions\DisallowedActionException;
use Djambi\GameManagers\BaseGameManager;
use Djambi\GameOptions\StandardRuleset;
use Djambi\Persistance\PersistantDjambiObject;
use Djambi\Players\ComputerPlayer;
use Djambi\Players\HumanPlayer;
use Djambi\Players\HumanPlayerInterface;
use Djambi\Players\PlayerInterface;
use Djambi\Strings\Glossary;
use Djambi\Strings\GlossaryTerm;

/**
 * Class DjambiPoliticalFaction
 */
class Faction extends PersistantDjambiObject {
  const DRAW_STATUS_ACCEPTED = 2;
  const DRAW_STATUS_PROPOSED = 1;
  const DRAW_STATUS_REJECTED = 0;
  const DRAW_STATUS_UNDECIDED = -1;

  const STATUS_PLAYING = 'playing';
  const STATUS_WINNER = 'winner';
  const STATUS_DRAW = 'draw';
  const STATUS_KILLED = 'killed';
  const STATUS_WITHDRAW = 'withdraw';
  const STATUS_VASSALIZED = 'vassalized';
  const STATUS_SURROUNDED = 'surrounded';
  const STATUS_DEFECT = 'defect';
  const STATUS_EMPTY_SLOT = 'empty';
  const STATUS_READY = 'ready';

  /* @var string $status */
  protected $status;
  /* @var int $ranking */
  protected $ranking;
  /* @var string $id */
  protected $id;
  /* @var string $name */
  protected $name;
  /* @var string $class */
  protected $class;
  /* @var Faction $control */
  protected $control;
  /* @var bool $alive */
  protected $alive = FALSE;
  /* @var Piece[] $pieces */
  protected $pieces;
  /* @var Battlefield $battlefield */
  protected $battlefield;
  /* @var int $startOrder; */
  protected $startOrder;
  /* @var bool $playing */
  protected $playing = FALSE;
  /* @var int $skippedTurns */
  protected $skippedTurns = 0;
  /* @var int $lastDrawProposal */
  protected $lastDrawProposal;
  /* @var int $drawStatus */
  protected $drawStatus;
  /* @var PlayerInterface $player */
  protected $player;

  protected function prepareArrayConversion() {
    $this->addDependantObjects(array('battlefield' => 'id', 'control' => 'id'));
    $this->addPersistantProperties(array(
      'id',
      'name',
      'class',
      'startOrder',
      'status',
      'ranking',
      'alive',
      'playing',
      'player',
      'skippedTurns',
      'lastDrawProposal',
      'drawStatus',
      'pieces',
    ));
    return parent::prepareArrayConversion();
  }

  public static function fromArray(array $array, array $context = array()) {
    /** @var Battlefield $battlefield */
    $battlefield = $context['battlefield'];
    if (!empty($array['player'])) {
      $player = call_user_func($array['player']['className'] . '::fromArray', $array['player'], $context);
    }
    else {
      $player = NULL;
    }
    /** @var Faction $faction */
    $faction = new static($battlefield, $array['id'], $array['name'], $array['class'], $array['startOrder'], $player);
    $simple_properties = array(
      'status',
      'ranking',
      'alive',
      'playing',
      'skippedTurns',
      'lastDrawProposal',
      'drawStatus',
    );
    foreach ($simple_properties as $property) {
      if (isset($array[$property])) {
        if (method_exists($faction, 'set' . ucfirst($property))) {
          call_user_func(array($faction, 'set' . ucfirst($property)), $array[$property]);
        }
        else {
          $faction->$property = $array[$property];
        }
      }
    }
    // Définition des pièces
    $context['faction'] = $faction;
    foreach ($array['pieces'] as $flat_piece) {
      $piece = call_user_func($flat_piece['className'] . '::fromArray', $flat_piece, $context);
      $faction->addPiece($piece);
    }
    // Récupération du contrôle
    foreach ($battlefield->getFactions() as $referenced_faction) {
      if ($referenced_faction->getId() == $array['control']) {
        $faction->setControl($referenced_faction, FALSE);
      }
      elseif ($faction->getId() == $referenced_faction->getId()) {
        $referenced_faction->setControl($faction, FALSE);
      }
    }
    return $faction;
  }

  public function __construct(Battlefield $battlefield, $id, $name, $class, $start_order, PlayerInterface $player = NULL) {
    $this->battlefield = $battlefield;
    $this->id = $id;
    $this->setName($name);
    $this->setClass($class);
    $this->startOrder = $start_order;
    $this->setControl($this);
    $this->player = $player;
    if (!is_null($player)) {
      $player->setFaction($this);
    }
  }

  public function getStatus() {
    return $this->status;
  }

  public function getRanking() {
    return $this->ranking;
  }

  public function getId() {
    return $this->id;
  }

  public function getName() {
    return $this->name;
  }

  protected function setName($name) {
    $this->name = $name;
    return $this;
  }

  public function getClass() {
    return $this->class;
  }

  protected function setClass($class) {
    $this->class = $class;
    return $this;
  }

  public function getStartOrder() {
    return $this->startOrder;
  }

  public function getPieces() {
    return $this->pieces;
  }

  public function getControl() {
    return $this->control;
  }

  public function isSelfControlled() {
    return $this->getId() == $this->getControl()->getId();
  }

  public function getSkippedTurns() {
    return $this->skippedTurns;
  }

  public function setSkippedTurns($nb_turns) {
    $this->skippedTurns = $nb_turns;
    return $this;
  }

  public function addSkippedTurn() {
    $this->skippedTurns++;
    return $this;
  }

  public function getLastDrawProposal() {
    return $this->lastDrawProposal;
  }

  public function getDrawStatus() {
    return $this->drawStatus;
  }

  public function setLastDrawProposal($turn) {
    $this->lastDrawProposal = $turn;
    return $this;
  }

  public function setDrawStatus($value) {
    if (is_null($value)) {
      $this->getBattlefield()->getGameManager()->setStatus(BaseGameManager::STATUS_PENDING);
    }
    else {
      $this->getBattlefield()->getGameManager()->setStatus(BaseGameManager::STATUS_DRAW_PROPOSAL);
    }
    $this->drawStatus = $value;
    return $this;
  }

  public function setStatus($status) {
    if ($this->status == self::STATUS_VASSALIZED) {
      return $this;
    }
    $this->status = $status;
    $living_statuses = array(
      self::STATUS_PLAYING,
      self::STATUS_READY,
      self::STATUS_DRAW,
      self::STATUS_WINNER,
    );
    if (in_array($status, $living_statuses)) {
      $this->setAlive(TRUE);
    }
    else {
      $this->setAlive(FALSE);
    }
    return $this;
  }

  public function setRanking($ranking) {
    $this->ranking = $ranking;
    return $this;
  }

  /**
   * @return Piece[]
   */
  public function getControlledPieces() {
    $pieces = array();
    foreach ($this->battlefield->getFactions() as $faction) {
      if ($faction->getControl()->getId() == $this->getId()) {
        foreach ($faction->getPieces() as $piece) {
          if ($piece->isAlive()) {
            $pieces[$piece->getId()] = $piece;
          }
        }
      }
    }
    return $pieces;
  }

  public function setControlId($faction_id) {
    $this->setControl($this->getBattlefield()->findFactionById($faction_id), FALSE);
    return $this;
  }

  public function setControl(Faction $faction, $log = TRUE) {
    $old_control = $this->control;
    $this->control = $faction;
    if (is_null($old_control)) {
      return $this;
    }
    $grid = $this->getBattlefield();
    foreach ($grid->getFactions() as $existing_faction) {
      if ($existing_faction->getId() != $this->getId() && $existing_faction->getControl()->getId() == $this->getId()) {
        if ($grid->getGameManager()->getOption(StandardRuleset::RULE_VASSALIZATION) == 'full_control'
        || $existing_faction->getStatus() == self::STATUS_KILLED) {
          $existing_faction->setControl($faction, FALSE);
        }
        elseif ($faction->getId() != $this->id) {
          $existing_faction->setControl($existing_faction, FALSE);
        }
      }
      if ($faction->getId() == $this->id && !$existing_faction->isAlive()
          && $existing_faction->getControl()->getId() != $this->id) {
        $existing_faction->setControl($this, FALSE);
      }
    }
    if ($log && !empty($existing_faction)) {
      if ($faction->getId() != $this->getId()) {
        $event = new Event(
          new GlossaryTerm(Glossary::EVENT_CHANGING_SIDE, array(
            '!faction_id1' => $this->getId(),
            '!faction_id2' => $faction->getId(),
            '!controlled' => $existing_faction->getId(),
          )), Event::LOG_LEVEL_NORMAL
        );
        $event->logChange(new FactionChange($existing_faction, 'controlId', $this->getId(), $faction->getId()));
        $this->getBattlefield()->getCurrentTurn()->logEvent($event);
      }
      else {
        $event = new Event(
          new GlossaryTerm(Glossary::EVENT_INDEPENDANT_SIDE, array(
            'faction_id1' => $this->getId(),
            'faction_id2' => $old_control->getId(),
          )), Event::LOG_LEVEL_NORMAL
        );
        $event->logChange(new FactionChange($existing_faction, 'controlId', $old_control->getId(), $this->getId()));
        $this->getBattlefield()->getCurrentTurn()->logEvent($event);
      }
    }
    return $this;
  }

  public function isPlaying() {
    return $this->playing;
  }

  public function setPlaying($playing) {
    $this->playing = $playing;
    if ($playing) {
      foreach ($this->getBattlefield()->getFactions() as $faction) {
        if ($faction->getStatus() == self::STATUS_PLAYING) {
          $faction->setStatus(self::STATUS_READY);
        }
      }
      $this->setStatus(self::STATUS_PLAYING);
    }
    return $this;
  }

  public function dieDieDie($user_status) {
    if ($this->isAlive()) {
      $event = new Event(new GlossaryTerm(Glossary::EVENT_FACTION_GAME_OVER,
        array('faction_id' => $this->getId())), Event::LOG_LEVEL_MAJOR);
      $event->logChange(new FactionChange($this, 'alive', TRUE, FALSE));
      $event->logChange(new FactionChange($this, 'status', $this->getStatus(), $user_status));
      $this->getBattlefield()->getCurrentTurn()->logEvent($event);
    }
    $this->setStatus($user_status);
    $this->setAlive(FALSE);
    return $this;
  }

  public function isAlive() {
    return $this->alive;
  }

  public function setAlive($alive) {
    $this->alive = $alive;
    return $this;
  }

  public function getBattlefield() {
    return $this->battlefield;
  }

  /**
   * @return HumanPlayer|ComputerPlayer
   */
  public function getPlayer() {
    return $this->player;
  }

  public function changePlayer(PlayerInterface $player) {
    $this->player = $player;
    $player->setFaction($this);
    $this->setStatus(self::STATUS_READY);
    if ($player instanceof HumanPlayerInterface && $player->isEmptySeat()) {
      $player->useSeat();
    }
    return $this;
  }

  public function removePlayer() {
    if (!empty($this->player)) {
      $this->player->removeFaction();
    }
    $this->player = NULL;
    $this->setStatus(self::STATUS_EMPTY_SLOT);
    return $this;
  }

  public function setBattlefield(Battlefield $grid) {
    $this->battlefield = $grid;
    return $this;
  }

  public function createPieces($pieces_scheme, $start_scheme) {
    foreach ($pieces_scheme as $key => $piece_description) {
      $alive = TRUE;
      if (!isset($start_scheme[$key])) {
        continue;
      }
      $original_faction_id = $this->getId();
      $start_cell = $this->getBattlefield()->findCell($start_scheme[$key]['x'], $start_scheme[$key]['y']);
      $piece = new Piece($piece_description, $this, $original_faction_id, $start_cell, $alive);
      $this->addPiece($piece);
    }
    return $this;
  }

  protected function addPiece(Piece $piece) {
    $this->pieces[$piece->getDescription()->getShortname()] = $piece;
    return $this;
  }

  public function canSkipTurn() {
    $max_skipped_turns = $this->getBattlefield()->getGameManager()->getOption(StandardRuleset::GAMEPLAY_ELEMENT_SKIPPED_TURNS);
    if ($this->getSkippedTurns() < $max_skipped_turns || $max_skipped_turns == -1) {
      return TRUE;
    }
    return FALSE;
  }

  public function skipTurn() {
    if (!$this->canSkipTurn()) {
      throw new DisallowedActionException(new GlossaryTerm(Glossary::EXCEPTION_MAX_SKIPPED_TURNS));
    }
    $this->addSkippedTurn();
    $event = new Event(new GlossaryTerm(Glossary::EVENT_SKIPPED_TURN, array(
      '!faction_id' => $this->getId(),
      '!nb' => $this->getSkippedTurns(),
    )), Event::LOG_LEVEL_NORMAL);
    $event->logChange(new FactionChange($this, 'skippedTurns', $this->getSkippedTurns() - 1, $this->getSkippedTurns()));
    $this->getBattlefield()->getCurrentTurn()->logEvent($event);
    $this->getBattlefield()->changeTurn();
    return $this;
  }

  public function withdraw() {
    if (!$this->isAlive() || !$this->isSelfControlled()) {
      throw new DisallowedActionException(new GlossaryTerm(Glossary::EXCEPTION_WITHDRAW));
    }
    $event = new Event(new GlossaryTerm(Glossary::EVENT_WITHDRAWAL, array(
      '!faction_id1' => $this->getId(),
    )), Event::LOG_LEVEL_MAJOR);
    $this->getBattlefield()->getCurrentTurn()->logEvent($event);
    $this->dieDieDie(self::STATUS_WITHDRAW);
    $this->getBattlefield()->changeTurn();
    return $this;
  }

  public function canCallForADraw() {
    $draw_proposal_rule = $this->getBattlefield()->getGameManager()->getOption(StandardRuleset::GAMEPLAY_ELEMENT_DRAW_DELAY);
    if ($draw_proposal_rule == -1 || $this->getBattlefield()->getCurrentTurn()->getRound() >= $this->getLastDrawProposal() + $draw_proposal_rule) {
      return TRUE;
    }
    return FALSE;
  }

  public function callForADraw() {
    if ($this->getBattlefield()->getGameManager()->getStatus() != BaseGameManager::STATUS_PENDING || !$this->canCallForADraw()) {
      throw new DisallowedActionException(new GlossaryTerm(Glossary::EXCEPTION_ASK_DRAW_DISALLOWED));
    }
    $old_draw_value = $this->getLastDrawProposal();
    $this->setLastDrawProposal($this->getBattlefield()->getCurrentTurn()->getRound());
    $event = new Event(new GlossaryTerm(Glossary::EVENT_DRAW_PROPOSAL, array(
      '!faction_id' => $this->getId(),
    )));
    $event->logChange(new FactionChange($this, 'lastDrawProposal', $old_draw_value, $this->getLastDrawProposal()));
    $this->setDrawStatus(self::DRAW_STATUS_PROPOSED);
    $event->logChange(new FactionChange($this, 'drawStatus', NULL, static::DRAW_STATUS_PROPOSED));
    foreach ($this->getBattlefield()->getFactions() as $faction) {
      if ($faction->isAlive() && $faction->getId() != $this->getId() && $faction->isSelfControlled()) {
        $faction->setDrawStatus(static::DRAW_STATUS_UNDECIDED);
        $event->logChange(new FactionChange($faction, 'drawStatus', NULL, static::DRAW_STATUS_UNDECIDED));
      }
    }
    $this->getBattlefield()->getCurrentTurn()->logEvent($event);
    $this->getBattlefield()->changeTurn();
    return $this;
  }

  public function acceptDraw() {
    if ($this->getBattlefield()->getGameManager()->getStatus() != BaseGameManager::STATUS_DRAW_PROPOSAL &&
      $this->getDrawStatus() != static::DRAW_STATUS_UNDECIDED) {
      throw new DisallowedActionException(new GlossaryTerm(Glossary::EXCEPTION_DRAW_ANSWER_DISALLOWED));
    }
    $event = new Event(new GlossaryTerm(Glossary::EVENT_DRAW_ACCEPTED, array(
      '!faction_id' => $this->getId(),
    )));
    $this->setDrawStatus(self::DRAW_STATUS_ACCEPTED);
    $event->logChange(new FactionChange($this, 'drawStatus', self::DRAW_STATUS_UNDECIDED, self::DRAW_STATUS_ACCEPTED));
    $this->getBattlefield()->getCurrentTurn()->logEvent($event);
    $factions = $this->getBattlefield()->getFactions();
    $alive_factions = array();
    $accepted_draws = 0;
    foreach ($factions as $faction) {
      if ($faction->isAlive()) {
        $alive_factions[] = $faction->getId();
        if ($faction->getDrawStatus() > 0) {
          $accepted_draws++;
        }
      }
    }
    if ($accepted_draws == count($alive_factions)) {
      $this->getBattlefield()->endGame($alive_factions);
    }
    else {
      $this->getBattlefield()->changeTurn();
    }
    return $this;
  }

  public function rejectDraw() {
    if ($this->getBattlefield()->getGameManager()->getStatus() != BaseGameManager::STATUS_DRAW_PROPOSAL &&
      $this->getDrawStatus() != static::DRAW_STATUS_UNDECIDED) {
      throw new DisallowedActionException(new GlossaryTerm(Glossary::EXCEPTION_DRAW_ANSWER_DISALLOWED));
    }
    $event = new Event(new GlossaryTerm(Glossary::EVENT_DRAW_REJECTED, array(
      '!faction_id' => $this->getId(),
    )));
    $factions = $this->getBattlefield()->getFactions();
    foreach ($factions as $faction) {
      $this->getBattlefield()->findFactionById($faction->getId())->setDrawStatus(NULL);
      $event->logChange(new FactionChange($faction, 'drawStatus', static::DRAW_STATUS_UNDECIDED, NULL));
    }
    $this->getBattlefield()->getCurrentTurn()->logEvent($event);
    $this->getBattlefield()->prepareTurn(TRUE);
    return $this;
  }

  public function checkLeaderFreedom() {
    $control_leader = FALSE;
    $has_necromobile = FALSE;
    $leaders = array();
    $pieces = $this->getControlledPieces();
    foreach ($pieces as $piece) {
      if ($piece->isAlive()) {
        // Contrôle 1 : chef vivant ?
        if ($piece->getDescription()->hasHabilityMustLive() && $piece->getFaction()->getId() == $this->getId()) {
          $control_leader = TRUE;
          $leaders[] = $piece;
        }
        // Contrôle 2 : nécromobile vivant ?
        if ($piece->getDescription()->hasHabilityMoveDeadPieces()) {
          $has_necromobile = TRUE;
        }
      }
    }
    if (!$control_leader) {
      return FALSE;
    }
    // Contrôle 3 : case pouvoir atteignable par le chef ?
    $thrones = $this->getBattlefield()->getSpecialCells(Cell::TYPE_THRONE);
    $nb_factions = $this->getBattlefield()->countLivingFactions();
    $checked = array();
    /* @var $leader Piece */
    foreach ($leaders as $leader) {
      $position = $leader->getPosition();
      if (in_array($position->getName(), $thrones)) {
        return TRUE;
      }
      // Règle d'encerclement strict :
      $strict_rule = in_array($this->getBattlefield()->getGameManager()->getOption(StandardRuleset::RULE_SURROUNDING), array('strict', 'loose'));
      if ($strict_rule && $nb_factions > 2) {
        if ($has_necromobile && $this->getBattlefield()->getGameManager()->getOption(StandardRuleset::RULE_SURROUNDING) == 'loose') {
          return TRUE;
        }
        $escorte[$position->getName()] = $leader->getId();
        $checked = array();
        while (!empty($escorte)) {
          foreach ($escorte as $escorte_position => $piece_id) {
            $current_cell = $this->getBattlefield()->findCellByName($escorte_position);
            foreach ($current_cell->getNeighbours() as $cell) {
              if (isset($checked[$cell->getName()])) {
                continue;
              }
              $piece = $cell->getOccupant();
              if (empty($piece)) {
                return TRUE;
              }
              elseif ($piece->isAlive()) {
                $escorte[$cell->getName()] = $piece->getId();
              }
              $checked[$cell->getName()] = TRUE;
            }
            unset($escorte[$escorte_position]);
          }
        }
        return FALSE;
      }
      // Règle d'encerclement par accès au pouvoir :
      else {
        if ($has_necromobile) {
          return TRUE;
        }
        $checked[$position->getName()] = TRUE;
        $check_further[$position->getName()] = $position;
        while (!empty($check_further)) {
          $position = current($check_further);
          $next_positions = $this->getBattlefield()->findNeighbourCells($position);
          foreach ($next_positions as $coord) {
            $blocked = FALSE;
            $alternate_cell = $this->getBattlefield()->findCell($coord['x'], $coord['y']);
            if (!isset($checked[$alternate_cell->getName()])) {
              $occupant = $alternate_cell->getOccupant();
              if (!empty($occupant)) {
                if (!$occupant->isAlive() && $alternate_cell->getType() != Cell::TYPE_THRONE) {
                  $blocked = TRUE;
                }
                elseif (in_array($alternate_cell->getName(), $thrones)) {
                  return TRUE;
                }
              }
              elseif (in_array($alternate_cell->getName(), $thrones)) {
                return TRUE;
              }
              if (!$blocked) {
                $check_further[$alternate_cell->getName()] = $alternate_cell;
              }
              $checked[$alternate_cell->getName()] = TRUE;
            }
          }
          unset($check_further[$position->getName()]);
        }
      }
    }
    return FALSE;
  }

}
