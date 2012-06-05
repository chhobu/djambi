<?php
class DjambiPoliticalFaction {
  private $user_data, $id, $name, $class, $control, $alive,
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
  }

  public static function buildFactionsInfos() {
    $factions = array();
    $factions['R'] = array('name' => 'Red', 'class' =>  'rouge', 'start_order' => 1);
    $factions['B'] = array('name' => 'Blue', 'class' => 'bleu', 'start_order' => 2);
    $factions['J'] = array('name' => 'Yellow', 'class' => 'jaune', 'start_order' => 3);
    $factions['V'] = array('name' => 'Green', 'class' =>  'vert', 'start_order' => 4);
    return $factions;
  }

  public function getUserData() {
    return $this->user_data;
  }

  public function getUserDataItem($item) {
    return $this->user_data[$item];
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

  public function addSkippedTurn() {
    $this->skipped_turns++;
  }

  public function getLastDrawProposal() {
    return $this->last_draw_proposal;
  }

  public function setLastDrawProposal($turn) {
    $this->last_draw_proposal = $turn;
    return $this;
  }

  public function getDrawStatus() {
    return $this->draw_status;
  }

  public function setDrawStatus($value) {
    $this->draw_status = $value;
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
    foreach ($this->getBattlefield()->getFactions() as $key => $f) {
      $controlled = array();
      if ($f->getId() != $this->getId() && $f->getControl()->getId() == $this->getId()) {
        $f->setControl($faction, FALSE);
        $controlled[] = $f->getId();
      }
    }
    if ($log) {
      $this->getBattlefield()->logEvent("event",
        "CHANGING_SIDE", array('faction1' => $this->getId(), 'faction2' => $faction->getId(), '!controlled' => $f->getId())
      );
    }
    return $this;
  }

  public function isPlaying() {
    return $this->playing;
  }

  public function setPlaying($playing) {
    $this->playing = $playing;
    return $this;
  }

  public function setDead() {
    $this->getBattlefield()->logEvent("event", "GAME_OVER", array('faction1' => $this->getId()));
    return $this->setAlive(FALSE);
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
    $this->setDead();
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
      $this->getBattlefield()->endWithADraw();
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

  public function toDatabase() {
    $data = array(
      'name' => $this->name,
      'class' => $this->class,
      'control' => $this->control->getId(),
      'alive' => $this->alive,
      'start_order' => $this->start_order
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