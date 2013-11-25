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

  const HABILITY_LIMITED_MOVE = 'limited_move';
  public function hasHabilityLimitedMove() {
    return $this->getHability(self::HABILITY_LIMITED_MOVE);
  }

  const HABILITY_ACCESS_THRONE = 'access_throne';
  public function hasHabilityAccessThrone() {
    return $this->getHability(self::HABILITY_ACCESS_THRONE);
  }

  const HABILITY_KILL_THRONE_LEADER = 'kill_throne_leader';
  public function hasHabilityKillThroneLeader() {
    return $this->getHability(self::HABILITY_KILL_THRONE_LEADER);
  }

  const HABILITY_MOVE_DEAD_PEACES = 'move_dead_pieces';
  public function hasHabilityMoveDeadPieces() {
    return $this->getHability(self::HABILITY_MOVE_DEAD_PEACES);
  }

  const HABILITITY_MOVE_LIVING_PIECES = 'move_living_pieces';
  public function hasHabilityMoveLivingPieces() {
    return $this->getHability(self::HABILITITY_MOVE_LIVING_PIECES);
  }

  const HABILITY_KILL_BY_PROXIMITY = 'kill_by_proximity';
  public function hasHabilityKillByProximity() {
    return $this->getHability(self::HABILITY_KILL_BY_PROXIMITY);
  }

  const HABILITY_KILL_BY_ATTACK = 'kill_by_attack';
  public function hasHabilityKillByAttack() {
    return $this->getHability(self::HABILITY_KILL_BY_ATTACK);
  }

  const HABILITY_SIGNATURE = 'signature';
  public function hasHabilitySignature() {
    return $this->getHability(self::HABILITY_SIGNATURE);
  }

  const HABILITY_MUST_LIVE = 'must_live';
  public function hasHabilityMustLive() {
    return $this->getHability(self::HABILITY_MUST_LIVE);
  }

  const HABILIITY_BLOCK_BY_PROXIMITY = 'block_by_proximity';
  public function hasHabilityBlockAdjacentPieces() {
    return $this->getHability(self::HABILIITY_BLOCK_BY_PROXIMITY);
  }

  const HABILITY_CONVERT_PIECES = 'convert_pieces';
  public function hasHabilityConvertPieces() {
    return $this->getHability(self::HABILITY_CONVERT_PIECES);
  }

  const HABILITY_UNCONVERTIBLE = 'unconvertible';
  public function hasHabilityCanDefect() {
    return $this->getHability(self::HABILITY_UNCONVERTIBLE);
  }

  const HABILITY_PROTECT_BY_PROXIMITY = 'protect_by_proximity';
  public function hasHabilityProtectAdjacentPieces() {
    return $this->getHability(self::HABILITY_PROTECT_BY_PROXIMITY);
  }

  const HABILITY_KAMIKAZE = 'kamikaze';
  public function hasHabilityKamikaze() {
    return $this->getHability(self::HABILITY_KAMIKAZE);
  }

  const HABILITY_KILL_FORTIFIED_PIECES = 'enter_fortress';
  public function hasHabilityEnterFortress() {
    return $this->getHability(self::HABILITY_KILL_FORTIFIED_PIECES);
  }
}
