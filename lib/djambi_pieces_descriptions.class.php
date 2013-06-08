<?php
class DjambiPieceDescription {
  private $type,
          $shortname,
          $num,
          $generic_name,
          $image_pattern,
          $rule_pattern,
          $start_position,
          $value,
          $habilities = array();

  public function __construct($type, $generic_shortname, $generic_name, $num, $start_position, $value) {
    $this->type = $type;
    $this->num = $num;
    $this->shortname = $num == 0 ? $generic_shortname : $generic_shortname . $num;
    $this->start_position = $start_position;
    $this->generic_name = $generic_name;
    $this->image_pattern = $type;
    $this->rule_pattern = $type;
    $this->value = $value;
  }

  public function getType() {
    return $this->type;
  }

  public function getShortname() {
    return $this->shortname;
  }

  public function getGenericName() {
    return $this->generic_name;
  }

  public function echoName() {
    if ($this->getNum() > 0) {
      return $this->getGenericName() . ' #' . $this->getNum();
    }
    else {
      return $this->getGenericName();
    }
  }

  public function getNum() {
    return $this->num;
  }

  public function getImagePattern() {
    return $this->image_pattern;
  }

  public function getRulePattern() {
    return $this->rule_pattern;
  }

  public function setImagePattern($image_pattern) {
    $this->image_pattern = $image_pattern;
    return $this;
  }

  public function setRulePattern($rule_pattern) {
    $this->rule_pattern = $rule_pattern;
    return $this;
  }

  public function getStartPosition() {
    return $this->start_position;
  }

  public function getValue() {
    return $this->value;
  }

  public function setHabilities($habilities) {
    foreach ($habilities as $name => $value) {
      $this->giveHability($name, $value);
    }
    return $this;
  }

  public function getHabilities() {
    return $this->habilities;
  }

  private function getHability($name) {
    return isset($this->habilities[$name]) ? $this->habilities[$name] : FALSE;
  }

  protected function giveHability($name, $value) {
    $this->habilities[$name] = $value;
    return $this;
  }

  public function hasHabilityLimitedMove() {
    return $this->getHability('limited_move');
  }

  public function hasHabilityAccessThrone() {
    return $this->getHability('access_throne');
  }

  public function hasHabilityKillThroneLeader() {
    return $this->getHability('kill_throne_leader');
  }

  public function hasHabilityMoveDeadPieces() {
    return $this->getHability('move_dead_pieces');
  }

  public function hasHabilityMoveLivingPieces() {
    return $this->getHability('move_living_pieces');
  }

  public function hasHabilityKillByProximity() {
    return $this->getHability('kill_by_proximity');
  }

  public function hasHabilityKillByAttack() {
    return $this->getHability('kill_by_attack');
  }

  public function hasHabilitySignature() {
    return $this->getHability('signature');
  }

  public function hasHabilityMustLive() {
    return $this->getHability('must_live');
  }

  public function hasHabilityBlockAdjacentPieces() {
    return $this->getHability('block_adjacent_pieces');
  }

  public function hasHabilityConvertPieces() {
    return $this->getHability('convert_pieces');
  }

  public function hasHabilityCanDefect() {
    return $this->getHability('cannot_defect');
  }

  public function hasHabilityProtectAdjacentPieces() {
    return $this->getHability('protect_adjacent_pieces');
  }

  public function hasHabilityKamikaze() {
    return $this->getHability('kamikaze');
  }

  public function hasHabilityGainPromotion() {
    return $this->getHability('gain_promotion');
  }

  public function hasHabilityRaiseDeadPieces() {
    return $this->getHability('raise_dead_pieces');
  }

  public function hasHabilityEnterFortress() {
    return $this->getHability('enter_fortress');
  }
}

class DjambiPieceLeader extends DjambiPieceDescription {
  public function __construct($num, $start_position) {
    $this->setHabilities(array(
        'must_live' => TRUE,
        'kill_by_attack' => TRUE,
        'kill_throne_leader' => TRUE,
        'access_throne' => TRUE,
        'cannot_defect' => TRUE
    ));
    return parent::__construct('leader', 'L', 'Leader', $num, $start_position, 10);
  }
}

class DjambiPieceAssassin extends DjambiPieceDescription {
  public function __construct($num, $start_position) {
    $this->setHabilities(array(
        'kill_by_attack' => TRUE,
        'kill_throne_leader' => TRUE,
        'signature' => TRUE,
        'enter_fortress' => TRUE
    ));
    return parent::__construct('assassin', 'A', 'Assassin', $num, $start_position, 2);
  }
}

class DjambiPieceReporter extends DjambiPieceDescription {
  public function __construct($num, $start_position) {
    $this->setHabilities(array(
        'kill_by_proximity' => TRUE,
        'kill_throne_leader' => TRUE,
    ));
    return parent::__construct('reporter', 'R', 'Reporter', $num, $start_position, 3);
  }
}

class DjambiPieceDiplomate extends DjambiPieceDescription {
  public function __construct($num, $start_position) {
    $this->setHabilities(array(
        'move_living_pieces' => TRUE,
    ));
    return parent::__construct('diplomate', 'D', 'Diplomate', $num, $start_position, 2);
  }
}

class DjambiPieceNecromobile extends DjambiPieceDescription {
  public function __construct($num, $start_position) {
    $this->setHabilities(array(
        'move_dead_pieces' => TRUE,
    ));
    return parent::__construct('necromobile', 'N', 'Necromobile', $num, $start_position, 5);
  }
}

class DjambiPieceMilitant extends DjambiPieceDescription {
  public function __construct($num, $start_position) {
    $this->setHabilities(array(
        'limited_move' => 2,
        'kill_by_attack' => TRUE,
        'gain_promotion' => array(
          'threshold' => 3,
          'choices' => array('DjambiPieceLegend', 'DjambiPieceLeader', 'DjambiPieceAssassin')
        )
    ));
    return parent::__construct('militant', 'M', 'Militant', $num, $start_position, 1);
  }
}

class DjambiPieceJudge extends DjambiPieceDescription {
  public function __construct($num, $start_position) {
    $this->setHabilities(array(
        'block_adjacent_pieces' => TRUE,
    ));
    return parent::__construct('judge', 'J', 'Judge', $num, $start_position, 2);
  }
}

class DjambiPiecePropagandist extends DjambiPieceDescription {
  public function __construct($num, $start_position) {
    $this->setHabilities(array(
        'convert_pieces' => TRUE,
        'cannot_defect' => TRUE,
        'signature' => TRUE
    ));
    return parent::__construct('propagandist', 'P', 'Propagandist', $num, $start_position, 3);
  }
}

class DjambiPieceBodyguard extends DjambiPieceDescription {
  public function __construct($num, $start_position) {
    $this->setHabilities(array(
      'protect_adjacent_pieces' => TRUE,
    ));
    parent::__construct('bodyguard', 'B', 'Bodyguard', $num, $start_position, 2);
  }
}

class DjambiPieceFanatic extends DjambiPieceDescription {
  public function __construct($num, $start_position) {
    $this->setHabilities(array(
      'kill_by_proximity' => TRUE,
      'kill_throne_leader' => TRUE,
      'limited_move' => 2,
      'kamikaze' => TRUE,
      'cannot_defect' => TRUE
    ));
    return parent::__construct('fanatic', 'F', 'Fanatic', $num, $start_position, 1);
  }
}

class DjambiPieceLegend extends DjambiPieceDescription {
  public function __construct($num, $start_position) {
    $this->setHabilities(array(
      'raise_dead_pieces' => TRUE,
      'cannot_defect' => TRUE,
      'signature' => TRUE
    ));
    return parent::__construct('legend', 'L', 'Legend', $num, $start_position, 6);
  }
}
