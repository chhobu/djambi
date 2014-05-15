<?php
/**
 * Created by PhpStorm.
 * User: buchho
 * Date: 09/05/14
 * Time: 00:42
 */

namespace Djambi\Gameplay;


use Djambi\Moves\Move;
use Djambi\Persistance\PersistantDjambiObject;

class Turn extends PersistantDjambiObject {
  /** @var int */
  protected $id;
  /** @var int */
  protected $start;
  /** @var int */
  protected $end;
  /** @var int */
  protected $round;
  /** @var int */
  protected $playOrderKey;
  /** @var Move */
  protected $move;
  /** @var Event[] */
  protected $events = array();
  /** @var Faction */
  protected $actingFaction;

  protected function prepareArrayConversion() {
    $attributes = array(
      'id',
      'start',
      'end',
      'round',
      'playOrderKey',
      'move',
    );
    if (!empty($this->events)) {
      $attributes[] = 'events';
    }
    $this->addPersistantProperties($attributes);
    $this->addDependantObjects(array(
      'actingFaction' => 'id',
    ));
    parent::prepareArrayConversion();
  }

  public static function fromArray(array $array, array $context = array()) {
    /** @var Battlefield $battlefield */
    $battlefield = $context['battlefield'];
    $turn = new static($battlefield->findFactionById($array['actingFaction']), $array['id'], $array['round'], $array['playOrderKey']);
    $turn->start = $array['start'];
    if (!empty($array['end'])) {
      $turn->end = $array['end'];
    }
    if (!empty($array['move'])) {
      $turn->move = call_user_func($array['move']['className'] . '::fromArray', $array['move'], $context);
    }
    if (!empty($array['events'])) {
      foreach ($array['events'] as $event) {
        $turn->events[] = call_user_func($event['className'] . '::fromArray', $event, $context);
      }
    }
    return $turn;
  }

  public static function begin(Battlefield $battlefield, $round, $play_order_key) {
    $id = count($battlefield->getPastTurns()) + 1;
    $faction = $battlefield->getPlayingFaction();
    $turn = new static($faction, $id, $round, $play_order_key);
    $turn->setMove(new Move($faction));
    return $turn;
  }

  public function endsTurn() {
    $this->end = time();
    if (!$this->move->isCompleted()) {
      $this->move = NULL;
    }
    return $this;
  }

  protected function __construct(Faction $faction, $id, $round, $play_order_key) {
    $this->id = $id;
    $this->start = time();
    $this->actingFaction = $faction;
    $this->round = $round;
    $this->playOrderKey = $play_order_key;
  }

  /**
   * @return Faction
   */
  public function getActingFaction() {
    return $this->actingFaction;
  }

  /**
   * @return int
   */
  public function getStart() {
    return $this->start;
  }

  /**
   * @return int
   */
  public function getEnd() {
    return $this->end;
  }

  /**
   * @return Event[]
   */
  public function getEvents() {
    return $this->events;
  }

  public function logEvent(Event $event) {
    $this->events[] = $event;
    return $this;
  }

  /**
   * @return int
   */
  public function getId() {
    return $this->id;
  }

  /**
   * @return Move
   */
  public function getMove() {
    return $this->move;
  }

  public function setMove(Move $move) {
    $this->move = $move;
    return $this;
  }

  public function resetMove() {
    $this->move->revert();
    $this->move = new Move($this->actingFaction);
    return $this;
  }

  public function cancelCompletedMove() {
    $this->move->revert(FALSE);
    $this->move = new Move($this->actingFaction);
    return $this;
  }

  /**
   * @return int
   */
  public function getRound() {
    return $this->round;
  }

  /**
   * return int
   */
  public function getPlayOrderKey() {
    return $this->playOrderKey;
  }
}
