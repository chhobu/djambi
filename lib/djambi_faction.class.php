<?php
/**
 * @file
 * Déclare la classe DjambiPoliticalFaction, qui gère les différents camps
 * d'une partie de Djambi.
 */

/**
 * Class DjambiPoliticalFaction
 */
class DjambiPoliticalFaction {
  protected $status;
  protected $ranking;
  protected $userData;
  protected $id;
  protected $name;
  protected $class;
  /* @var  \DjambiPoliticalFaction $control */
  protected $control;
  /* @var bool $alive */
  protected $alive;
  /* @var  \DjambiPiece[] $pieces */
  protected $pieces;
  /* @var \DjambiBattlefield $battlefield */
  protected $battlefield;
  protected $startOrder;
  /* @var bool $playing */
  protected $playing;
  protected $skippedTurns;
  protected $lastDrawProposal;
  protected $drawStatus;
  /* @var DjambiIA $ia */
  protected $ia;

  public function __construct(DjambiBattlefield $battlefield, $user_data, $id, $data) {
    $this->battlefield = $battlefield;
    $this->userData = $user_data;
    $this->id = $id;
    $this->name = $data['name'];
    $this->class = $data['class'];
    $this->control = $this;
    $this->alive = TRUE;
    $this->pieces = array();
    $this->startOrder = $data['start_order'];
    $this->playing = FALSE;
    $this->skippedTurns = isset($data['skipped_turns']) ? $data['skipped_turns'] : 0;
    $this->lastDrawProposal = isset($data['last_draw_proposal']) ? $data['last_draw_proposal'] : 0;
    $this->drawStatus = isset($data['draw_status']) ? $data['draw_status'] : NULL;
    $this->ranking = isset($data['ranking']) ? $data['ranking'] : NULL;
    $this->master = isset($data['master']) ? $data['master'] : NULL;
    $ia_class = $this->getUserDataItem('ia');
    if (!empty($ia_class)) {
      $ia_class = DjambiIA::getDefaultIAClass();
      $ia = new $ia_class($this);
      $this->ia = $ia;
    }
    $this->setStatus($this->getUserDataItem('status'));
  }

  public static function buildFactionsInfos() {
    $factions = array();
    $factions['R'] = array(
      'name' => 'Red',
      'class' => 'rouge',
      'start_order' => 1,
    );
    $factions['B'] = array(
      'name' => 'Blue',
      'class' => 'bleu',
      'start_order' => 2,
    );
    $factions['J'] = array(
      'name' => 'Yellow',
      'class' => 'jaune',
      'start_order' => 3,
    );
    $factions['V'] = array(
      'name' => 'Green',
      'class' => 'vert',
      'start_order' => 4,
    );
    return $factions;
  }

  public function updateUserData($data) {
    foreach ($data as $key => $value) {
      $this->userData[$key] = $value;
    }
    return $this;
  }

  public function getUserData() {
    return $this->userData;
  }

  public function getUserDataItem($item) {
    if (!isset($this->userData[$item])) {
      return NULL;
    }
    return $this->userData[$item];
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

  /**
   * @return DjambiIA
   */
  public function getIa() {
    return $this->ia;
  }

  public function setStatus($status) {
    if ($this->status == KW_DJAMBI_USER_VASSALIZED) {
      return $this;
    }
    $this->status = $status;
    $allowed_statuses = array(
      KW_DJAMBI_USER_PLAYING,
      KW_DJAMBI_USER_READY,
      KW_DJAMBI_USER_DRAW,
      KW_DJAMBI_USER_WINNER,
    );
    if (in_array($status, $allowed_statuses)) {
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
   * @return DjambiPiece[]
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

  public function setControl(DjambiPoliticalFaction $faction, $log = TRUE) {
    $old_control = $this->control;
    $this->control = $faction;
    $grid = $this->getBattlefield();
    foreach ($grid->getFactions() as $f) {
      if ($f->getId() != $this->getId() && $f->getControl()->getId() == $this->getId()) {
        if ($grid->getOption('rule_vassalization') == 'full_control' || $f->getStatus() == KW_DJAMBI_USER_KILLED) {
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
        if ($faction->getStatus() == KW_DJAMBI_USER_PLAYING) {
          $faction->setStatus(KW_DJAMBI_USER_READY);
        }
      }
      $this->setStatus(KW_DJAMBI_USER_PLAYING);
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

  public function isHumanControlled() {
    return $this->getUserDataItem('human');
  }

  /**
   * @return DjambiBattlefield
   */
  public function getBattlefield() {
    return $this->battlefield;
  }

  public function setBattlefield(DjambiBattlefield $grid) {
    $this->battlefield = $grid;
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
      $piece = new DjambiPiece($piece_description, $this, $original_faction_id, $start_cell, $alive);
      $this->pieces[$key] = $piece;
    }
  }

  public function skipTurn() {
    $this->addSkippedTurn();
    $this->getBattlefield()->logEvent('event', 'SKIPPED_TURN', array(
      'faction1' => $this->getId(),
      '!nb' => $this->getSkippedTurns(),
    ));
    $this->getBattlefield()->changeTurn();
  }

  public function withdraw() {
    $this->getBattlefield()->logEvent('event', 'WITHDRAWAL', array('faction1' => $this->getId()));
    $this->dieDieDie(KW_DJAMBI_USER_WITHDRAW);
    $this->getBattlefield()->updateSummary();
  }

  public function canComeBackAfterWithdraw() {
    if ($this->getStatus() == KW_DJAMBI_USER_WITHDRAW
        && $this->getBattlefield()->getOption('rule_comeback') == 'allowed'
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
      $this->setStatus(KW_DJAMBI_USER_READY);
      $this->getBattlefield()->logEvent('event', 'COMEBACK_AFTER_WITHDRAW', array('faction1' => $this->getId()));
      $this->getBattlefield()->updateSummary();
    }
  }

  public function callForADraw() {
    $turns = $this->getBattlefield()->getTurns();
    $this->setLastDrawProposal($turns[$this->getBattlefield()->getCurrentTurnId()]['turn']);
    $this->getBattlefield()->logEvent('event', 'DRAW_PROPOSAL', array('faction1' => $this->getId()));
    $this->getBattlefield()->setStatus(KW_DJAMBI_STATUS_DRAW_PROPOSAL);
    $this->setDrawStatus(1);
    $this->getBattlefield()->changeTurn();
  }

  public function acceptDraw() {
    $this->getBattlefield()->logEvent('event', 'DRAW_ACCEPTED', array('faction1' => $this->getId()));
    $this->setDrawStatus(2);
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
  }

  public function rejectDraw() {
    $this->getBattlefield()->logEvent('event', 'DRAW_REJECTED', array('faction1' => $this->getId()));
    $this->getBattlefield()->setStatus(KW_DJAMBI_STATUS_PENDING);
    $factions = $this->getBattlefield()->getFactions();
    foreach ($factions as $faction) {
      $this->getBattlefield()->getFactionById($faction->getId())->setDrawStatus(NULL);
    }
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
    $thrones = $this->getBattlefield()->getSpecialCells("throne");
    $nb_factions = $this->getBattlefield()->countLivingFactions();
    $checked = array();
    /* @var $leader DjambiPiece */
    foreach ($leaders as $leader) {
      $position = $leader->getPosition();
      if (in_array($position->getName(), $thrones)) {
        return TRUE;
      }
      // Règle d'encerclement strict :
      $strict_rule = in_array($this->getBattlefield()->getOption('rule_surrounding'), array('strict', 'loose'));
      if ($strict_rule && $nb_factions > 2) {
        if ($has_necromobile && $this->getBattlefield()->getOption('rule_surrounding') == 'loose') {
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
                if (!$occupant->isAlive() && $alternate_cell->getType() != "throne") {
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
    $data = array(
      'name' => $this->name,
      'class' => $this->class,
      'control' => $this->control->getId(),
      'alive' => $this->alive,
      'start_order' => $this->startOrder,
      'ranking' => $this->ranking,
      'status' => $this->status,
      'master' => $this->master,
    );
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
