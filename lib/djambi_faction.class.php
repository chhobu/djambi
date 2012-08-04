<?php
class DjambiPoliticalFaction {
  private $status, $ranking,
    $user_data, $id, $name, $class, $control, $alive,
    $pieces, $battlefield = NULL, $start_order, $playing,
    $skipped_turns, $last_draw_proposal, $draw_status;

  public function __construct($user_data, $id, $data) {
    $this->user_data = $user_data;
    $this->id = $id;
    $this->name = $data['name'];
    $this->class = $data['class'];
    $this->control = $this;
    $this->alive = TRUE;
    $this->pieces = array();
    $this->start_order = $data['start_order'];
    $this->playing = FALSE;
    $this->skipped_turns = isset($data['skipped_turns']) ? $data['skipped_turns'] : 0;
    $this->last_draw_proposal = isset($data['last_draw_proposal']) ? $data['last_draw_proposal'] : 0;
    $this->draw_status = isset($data['draw_status']) ? $data['draw_status'] : NULL;
    $this->ranking = isset($data['ranking']) ? $data['ranking'] : NULL;
    $this->master = isset($data['master']) ? $data['master'] : NULL;
    $this->setStatus($this->getUserDataItem('status'));
  }

  public static function buildFactionsInfos() {
    $factions = array();
    $factions['R'] = array('name' => 'Red', 'class' =>  'rouge', 'start_order' => 1);
    $factions['B'] = array('name' => 'Blue', 'class' => 'bleu', 'start_order' => 2);
    $factions['J'] = array('name' => 'Yellow', 'class' => 'jaune', 'start_order' => 3);
    $factions['V'] = array('name' => 'Green', 'class' =>  'vert', 'start_order' => 4);
    return $factions;
  }

  public function updateUserData($data) {
    foreach ($data as $key => $value) {
      $this->user_data[$key] = $value;
    }
    return $this;
  }

  public function getUserData() {
    return $this->user_data;
  }

  public function getUserDataItem($item) {
    if (!isset($this->user_data[$item])) {
      return NULL;
    }
    return $this->user_data[$item];
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
    return $this->start_order;
  }

  public function getPieces() {
    return $this->pieces;
  }

  public function getControl() {
    return $this->control;
  }

  public function getSkippedTurns() {
    return $this->skipped_turns;
  }

  public function getMaster() {
    return $this->master;
  }

  public function addSkippedTurn() {
    $this->skipped_turns++;
  }

  public function getLastDrawProposal() {
    return $this->last_draw_proposal;
  }

  public function getDrawStatus() {
    return $this->draw_status;
  }

  public function setLastDrawProposal($turn) {
    $this->last_draw_proposal = $turn;
    return $this;
  }

  public function setDrawStatus($value) {
    $this->draw_status = $value;
    return $this;
  }

  public function setStatus($status) {
    if ($this->status == KW_DJAMBI_USER_VASSALIZED) {
      return $this;
    }
    $this->status = $status;
    if (in_array($status, array(KW_DJAMBI_USER_PLAYING, KW_DJAMBI_USER_READY, KW_DJAMBI_USER_DRAW, KW_DJAMBI_USER_WINNER))) {
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

  public function getControlledPieces() {
    $pieces = array();
    foreach ($this->battlefield->getFactions() as $faction) {
      if($faction->getControl()->getId() == $this->getId()) {
        foreach($faction->getPieces() as $key => $piece) {
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
    foreach ($grid->getFactions() as $key => $f) {
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
    if ($log) {
      if ($faction->getId() != $this->getId()) {
        $this->getBattlefield()->logEvent("event", "CHANGING_SIDE",
            array('faction1' => $this->getId(), 'faction2' => $faction->getId(), '!controlled' => $f->getId())
        );
      }
      else {
        $this->getBattlefield()->logEvent("event", "INDEPENDANT_SIDE",
            array('faction1' => $this->getId(), 'faction2' => $old_control->getId()));
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

  /**
   * @return DjambiBattlefield
   */
  public function getBattlefield() {
    return $this->battlefield;
  }

  public function setBattlefield(DjambiBattlefield $bt) {
    $this->battlefield = $bt;
  }

  public function createPieces($pieces_scheme, $start_scheme, $deads = NULL) {
    foreach($pieces_scheme as $key => $scheme) {
      $alive = TRUE;
      if (!is_null($deads) && is_array($deads)) {
        if (array_search($this->getId(). '-' . $key, $deads) !== FALSE) {
          $alive = FALSE;
        }
      }
      $piece = new DjambiPiece($this, $key, $scheme['shortname'], $scheme['longname'],
        $scheme['type'], $start_scheme[$key]['x'], $start_scheme[$key]['y'], $alive);
      if (isset($scheme['habilities']) && is_array($scheme['habilities'])) {
        foreach($scheme['habilities'] as $hability => $value) {
          $piece->setHability($hability, $value);
        }
      }
      $this->pieces[$key] = $piece;
    }
  }

  public function skipTurn() {
    $this->addSkippedTurn();
    $this->getBattlefield()->logEvent('event', 'SKIPPED_TURN', array('faction1' => $this->getId(),
        '!nb' => $this->getSkippedTurns()));
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
    $this->getBattlefield()->logEvent('info', 'DRAW_PROPOSAL', array('faction1' => $this->getId()));
    $this->getBattlefield()->setStatus(KW_DJAMBI_STATUS_DRAW_PROPOSAL);
    $this->setDrawStatus(1);
    $this->getBattlefield()->changeTurn();
  }

  public function acceptDraw() {
    $this->getBattlefield()->logEvent('info', 'DRAW_ACCEPTED', array('faction1' => $this->getId()));
    $this->setDrawStatus(2);
    $factions = $this->getBattlefield()->getFactions();
    $alive_factions = 0;
    $accepted_draws = 0;
    foreach ($factions as $faction) {
      if ($faction->isAlive()) {
        $alive_factions++;
        if ($faction->getDrawStatus() > 0) {
          $accepted_draws++;
        }
      }
    }
    if ($accepted_draws == $alive_factions) {
      $this->getBattlefield()->endGame($alive_factions);
    }
    else {
      $this->getBattlefield()->changeTurn();
    }
  }

  public function rejectDraw() {
    $this->getBattlefield()->logEvent('info', 'DRAW_REJECTED', array('faction1' => $this->getId()));
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
    $cells = $this->getBattlefield()->getCells();
    /* @var $piece DjambiPiece */
    foreach ($pieces as $key => $piece) {
      if ($piece->isAlive()) {
        // Contrôle 1 : chef vivant ?
        if ($piece->getHability("must_live")) {
          $control_leader = TRUE;
          $leaders[] = $piece;
        }
        // Contrôle 2 : nécromobile vivant ?
        if ($piece->getHability("move_dead_pieces")) {
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
      $alternate_position = DjambiBattlefield::locateCell($position);
      if (in_array($alternate_position, $thrones)) {
        return TRUE;
      }
      // Règle d'encerclement strict
      $strict_rule = in_array($this->getBattlefield()->getOption('rule_surrounding'), array('strict', 'loose'));
      if ($strict_rule && $nb_factions > 2) {
        if ($has_necromobile && $this->getBattlefield()->getOption('rule_surrounding') == 'loose') {
          return TRUE;
        }
        $escorte[$alternate_position] = $leader->getId();
        $checked = array();
        while (!empty($escorte)) {
          foreach ($escorte as $escorte_position => $piece_id) {
            $current_cell = $cells[$escorte_position];
            foreach($current_cell['neighbours'] as $neighbour) {
              if (in_array($neighbour, $checked)) {
                continue;
              }
              $cell = $cells[$neighbour];
              if (empty($cell['occupant'])) {
                return TRUE;
              }
              else {
                $piece = $cell['occupant'];
                if ($piece->isAlive()) {
                  $escorte[$neighbour] = $piece->getId();
                }
              }
              $checked[] = $neighbour;
            }
            unset($escorte[$escorte_position]);
          }
        }
        return FALSE;
      }
      // Règle d'encerclement par accès au pouvoir
      else {
        if ($has_necromobile) {
          return TRUE;
        }
        $checked[$alternate_position] = $position;
        $check_further[$alternate_position] = $position;
        while (!empty($check_further)) {
          $position = current($check_further);
          $next_positions = $this->getBattlefield()->findNeighbourCells($position);
          foreach ($next_positions as $key => $coord) {
            $blocked = FALSE;
            $alternate_position = DjambiBattlefield::locateCell($coord);
            if (!isset($checked[$alternate_position])) {
              if (!empty($cells[$alternate_position]["occupant"])) {
                $occupant = $cells[$alternate_position]["occupant"];
                if (!$occupant->isAlive() && $cells[$alternate_position]["type"] != "throne") {
                  $blocked = TRUE;
                }
                elseif(in_array($alternate_position, $thrones)) {
                  return TRUE;
                }
              }
              elseif (in_array($alternate_position, $thrones)) {
                return TRUE;
              }
              if (!$blocked) {
                $check_further[$alternate_position] = $coord;
              }
              $checked[$alternate_position] = $coord;
            }
          }
          unset($check_further[key($check_further)]);
        }
      }
    }
    return FALSE;
  }

  public function toDatabase() {
    $data = array(
      'name' => $this->name,
      'class' => $this->class,
      'control' => $this->control->getId(),
      'alive' => $this->alive,
      'start_order' => $this->start_order,
      'ranking' => $this->ranking,
      'status' => $this->status,
      'master' => $this->master,
    );
    if ($this->skipped_turns > 0) {
      $data['skipped_turns'] = $this->skipped_turns;
    }
    if ($this->last_draw_proposal > 0) {
      $data['last_draw_proposal'] = $this->last_draw_proposal;
    }
    if (!is_null($this->draw_status)) {
      $data['draw_status'] = $this->draw_status;
    }
    return $data;
  }
}