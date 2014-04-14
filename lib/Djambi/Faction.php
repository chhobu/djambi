<?php
/**
 * @file
 * Déclare la classe DjambiPoliticalFaction, qui gère les différents camps
 * d'une partie de Djambi.
 */

namespace Djambi;

use Djambi\GameManagers\BasicGameManager;
use Djambi\Interfaces\HumanPlayerInterface;
use Djambi\Interfaces\PlayerInterface;
use Djambi\Players\ComputerPlayer;
use Djambi\Players\HumanPlayer;
use Djambi\Stores\StandardRuleset;

/**
 * Class DjambiPoliticalFaction
 */
class Faction {
  const DRAW_STATUS_ACCEPTED = 2;
  const DRAW_STATUS_PROPOSED = 1;
  const DRAW_STATUS_REJECTED = 0;

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
  private $status;
  /* @var int $ranking */
  private $ranking;
  /* @var string $id */
  private $id;
  /* @var string $name */
  private $name;
  /* @var string $class */
  private $class;
  /* @var Faction $control */
  private $control;
  /* @var bool $alive */
  private $alive = FALSE;
  /* @var Piece[] $pieces */
  private $pieces;
  /* @var Battlefield $battlefield */
  private $battlefield;
  /* @var int $startOrder; */
  private $startOrder;
  /* @var bool $playing */
  private $playing = FALSE;
  /* @var int $skippedTurns */
  private $skippedTurns;
  /* @var int $lastDrawProposal */
  private $lastDrawProposal;
  /* @var int $drawStatus */
  private $drawStatus;
  /* @var string $master */
  private $master;
  /* @var PlayerInterface $player */
  private $player;

  public function __construct(Battlefield $battlefield, $id, $name, $class, $start_order, $data, PlayerInterface $player = NULL) {
    $this->battlefield = $battlefield;
    $this->id = $id;
    $this->name = $name;
    $this->class = $class;
    $this->startOrder = $start_order;
    $this->control = $this;
    $this->setStatus(isset($data['status']) ? $data['status'] : self::STATUS_READY);
    $this->pieces = array();
    $this->playing = FALSE;
    $this->skippedTurns = isset($data['skipped_turns']) ? $data['skipped_turns'] : 0;
    $this->lastDrawProposal = isset($data['last_draw_proposal']) ? $data['last_draw_proposal'] : 0;
    $this->drawStatus = isset($data['draw_status']) ? $data['draw_status'] : NULL;
    $this->ranking = isset($data['ranking']) ? $data['ranking'] : NULL;
    $this->master = isset($data['master']) ? $data['master'] : NULL;
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

  public function getClass() {
    return $this->class;
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

  public function getSkippedTurns() {
    return $this->skippedTurns;
  }

  public function getMaster() {
    return $this->master;
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

  public function setMaster($master) {
    $this->master = $master;
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
          $pieces[$piece->getId()] = $piece;
        }
      }
    }
    return $pieces;
  }

  public function setControl(Faction $faction, $log = TRUE) {
    $old_control = $this->control;
    $this->control = $faction;
    $grid = $this->getBattlefield();
    foreach ($grid->getFactions() as $f) {
      if ($f->getId() != $this->getId() && $f->getControl()->getId() == $this->getId()) {
        if ($grid->getGameManager()->getOption(StandardRuleset::RULE_VASSALIZATION) == 'full_control'
        || $f->getStatus() == self::STATUS_KILLED) {
          $f->setControl($faction, FALSE);
        }
        elseif ($faction->getId() != $this->id) {
          $f->setControl($f, FALSE);
        }
      }
      if ($faction->getId() == $this->id && !$f->isAlive() && $f->getMaster() == $this->id
          && $f->getControl()->getId() != $this->id) {
        $f->setControl($this, FALSE);
      }
    }
    if ($log && !empty($f)) {
      if ($faction->getId() != $this->getId()) {
        $this->getBattlefield()->logEvent("event", "CHANGING_SIDE", array(
          'faction1' => $this->getId(),
          'faction2' => $faction->getId(),
          '!controlled' => $f->getId(),
        ));
      }
      else {
        $this->getBattlefield()->logEvent("event", "INDEPENDANT_SIDE", array(
          'faction1' => $this->getId(),
          'faction2' => $old_control->getId(),
        ));
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
      $this->getBattlefield()->logEvent("event", "GAME_OVER", array('faction1' => $this->getId()));
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

  public function createPieces($pieces_scheme, $start_scheme, $deads = NULL) {
    foreach ($pieces_scheme as $key => $piece_description) {
      $alive = TRUE;
      if (!is_null($deads) && is_array($deads)) {
        if (array_search($this->getId() . '-' . $key, $deads) !== FALSE) {
          $alive = FALSE;
        }
      }
      if (!isset($start_scheme[$key])) {
        continue;
      }
      $original_faction_id = $this->getId();
      $start_cell = $this->getBattlefield()->findCell($start_scheme[$key]['x'], $start_scheme[$key]['y']);
      $piece = new Piece($piece_description, $this, $original_faction_id, $start_cell, $alive);
      $this->pieces[$key] = $piece;
    }
    return $this;
  }

  public function skipTurn() {
    $this->addSkippedTurn();
    $this->getBattlefield()->logEvent('event', 'SKIPPED_TURN', array(
      'faction1' => $this->getId(),
      '!nb' => $this->getSkippedTurns(),
    ));
    $this->getBattlefield()->changeTurn();
    return $this;
  }

  public function withdraw() {
    $this->getBattlefield()->logEvent('event', 'WITHDRAWAL', array('faction1' => $this->getId()));
    $this->dieDieDie(self::STATUS_WITHDRAW);
    $this->getBattlefield()->updateSummary();
    $this->getBattlefield()->changeTurn();
    return $this;
  }

  public function canComeBackAfterWithdraw() {
    if ($this->getStatus() == self::STATUS_WITHDRAW
        && $this->getBattlefield()->getGameManager()->getOption(StandardRuleset::RULE_COMEBACK) == 'allowed'
        && $this->getControl()->getId() == $this->getId()
        && $this->checkLeaderFreedom()) {
      return TRUE;
    }
    else {
      return FALSE;
    }
  }

  public function comeBackAfterWithdraw() {
    if ($this->canComeBackAfterWithdraw()) {
      $this->setStatus(self::STATUS_READY);
      $this->getBattlefield()->logEvent('event', 'COMEBACK_AFTER_WITHDRAW', array('faction1' => $this->getId()));
      $this->getBattlefield()->updateSummary();
      $this->getBattlefield()->getGameManager(__METHOD__);
    }
    return $this;
  }

  public function callForADraw() {
    $turns = $this->getBattlefield()->getTurns();
    $this->setLastDrawProposal($turns[$this->getBattlefield()->getCurrentTurnId()]['turn']);
    $this->getBattlefield()->logEvent('event', 'DRAW_PROPOSAL', array('faction1' => $this->getId()));
    $this->getBattlefield()->getGameManager()->setStatus(BasicGameManager::STATUS_DRAW_PROPOSAL);
    $this->setDrawStatus(self::DRAW_STATUS_PROPOSED);
    $this->getBattlefield()->changeTurn();
    return $this;
  }

  public function acceptDraw() {
    $this->getBattlefield()->logEvent('event', 'DRAW_ACCEPTED', array('faction1' => $this->getId()));
    $this->setDrawStatus(self::DRAW_STATUS_ACCEPTED);
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
    $this->getBattlefield()->logEvent('event', 'DRAW_REJECTED', array('faction1' => $this->getId()));
    $this->getBattlefield()->getGameManager()->setStatus(BasicGameManager::STATUS_PENDING);
    $factions = $this->getBattlefield()->getFactions();
    foreach ($factions as $faction) {
      $this->getBattlefield()->getFactionById($faction->getId())->setDrawStatus(NULL);
    }
    $this->getBattlefield()->getGameManager()->save(__METHOD__);
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

  public function toArray() {
    $data = array();
    $data['status'] = $this->getStatus();
    $player = $this->getPlayer();
    if (!empty($player)) {
      $data['player'] = $this->getPlayer()->toArray();
    }
    else {
      $data['player'] = NULL;
    }
    if ($this->getControl()->getId() != $this->getId()) {
      $data['control'] = $this->getControl()->getId();
    }
    if (!is_null($this->getRanking())) {
      $data['ranking'] = $this->getRanking();
    }
    if (!is_null($this->getMaster())) {
      $data['master'] = $this->getMaster();
    }
    if ($this->skippedTurns > 0) {
      $data['skipped_turns'] = $this->skippedTurns;
    }
    if ($this->lastDrawProposal > 0) {
      $data['last_draw_proposal'] = $this->lastDrawProposal;
    }
    if (!is_null($this->drawStatus)) {
      $data['draw_status'] = $this->drawStatus;
    }
    return $data;
  }
}
