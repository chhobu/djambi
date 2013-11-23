<?php
namespace Djambi;

class PieceDescription {
  protected $type;
  protected $shortname;
  protected $num;
  protected $genericName;
  protected $imagePattern;
  protected $rulePattern;
  protected $startPosition;
  protected $value;
  protected $habilities = array();

  public function __construct($type, $generic_shortname, $generic_name, $num, $start_position, $value) {
    $this->type = $type;
    $this->num = $num;
    $this->shortname = $num == 0 ? $generic_shortname : $generic_shortname . $num;
    $this->startPosition = $start_position;
    $this->genericName = $generic_name;
    $this->imagePattern = $type;
    $this->rulePattern = $type;
    $this->value = $value;
  }

  public function getType() {
    return $this->type;
  }

  public function getShortname() {
    return $this->shortname;
  }

  public function getGenericName() {
    return $this->genericName;
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
    return $this->imagePattern;
  }

  public function getRulePattern() {
    return $this->rulePattern;
  }

  public function setImagePattern($image_pattern) {
    $this->imagePattern = $image_pattern;
    return $this;
  }

  public function setRulePattern($rule_pattern) {
    $this->rulePattern = $rule_pattern;
    return $this;
  }

  public function getStartPosition() {
    return $this->startPosition;
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

  protected function getHability($name) {
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
