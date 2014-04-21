<?php
namespace Djambi\PieceDescriptions;

use Djambi\Exceptions\GridInvalidException;
use Djambi\PersistantDjambiObject;

abstract class BasePieceDescription extends PersistantDjambiObject {
  /** @var string : type de pièce */
  private $type;
  /** @var string : nom court de la pièce */
  protected $shortname;
  /** @var int : nom complet de la pièce */
  protected $num;
  /** @var string : nom générique du type de pièce */
  protected $genericName;
  /** @var string : libellé permettant de charger l'image associée */
  private $imagePattern;
  /** @var string : libellé permettant de charger la règle associée */
  private $rulePattern;
  /** @var array : position de départ (par rapport au chef, coordonnées x, y) */
  private $startPosition;
  /** @var int : valeur de la pièce */
  private $value;
  /** @var array : liste des capacités de la pièce */
  protected $habilities = array();

  protected function describePiece($type, $generic_shortname, $generic_name, $num, $start_position, $value) {
    $this->type = $type;
    $this->num = $num;
    $this->shortname = $num == 0 ? $generic_shortname : $generic_shortname . $num;
    $this->genericName = $generic_name;
    if (!is_array($start_position)) {
      $this->setStartCellName($start_position);
    }
    else {
      $this->setStartPosition($start_position);
    }
    $this->setImagePattern($type);
    $this->setRulePattern($type);
    $this->setValue($value);
  }

  protected function prepareArrayConversion() {
    $this->addPersistantProperties(array(
      'num',
      'startPosition',
    ));
    return parent::prepareArrayConversion();
  }

  public static function fromArray(array $array, array $context = array()) {
    return new static(isset($array['num']) ? $array['num'] : NULL, $array['startPosition']);
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

  public function getRuleUrl() {
    return 'http://djambi.net/regles/' . $this->rulePattern;
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

  protected function setStartPosition($position) {
    if (!is_array($position) || !isset($position['x']) || !isset($position['y'])) {
      throw new GridInvalidException("Invalid start position for piece " . $this->getShortname());
    }
    $this->startPosition = $position;
    return $this;
  }

  protected function setStartCellName($cell_name) {
    $this->startPosition = $cell_name;
    return $this;
  }

  public function getValue() {
    return $this->value;
  }

  protected function setValue($value) {
    $this->value = $value;
    return $this;
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
    return !empty($this->habilities[$name]) ? $this->habilities[$name] : FALSE;
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

}
