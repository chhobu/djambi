<?php
class DjambiPoliticalFaction {
  private $uid, $id, $name, $class, $control, $alive, $pieces, $battlefield = NULL, $start_order, $playing;
  
  public function __construct($uid, $id, $name, $class, $start_order) {
    $this->uid = $uid; 
    $this->id = $id;
    $this->name = $name;
    $this->class = $class;
    $this->control = $this;
    $this->alive = TRUE;
    $this->pieces = array();
    $this->start_order = $start_order;
    $this->playing = FALSE;
  }
  
  public function getUid() {
    return $this->uid;
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
    $this->control = $faction;
    if ($this != $faction && $log) {
      foreach ($this->getBattlefield()->getFactions() as $key => $f) {
        if ($f->getControl()->getId() == $this->getId()) {
          $f->setControl($faction, FALSE);
        }
      }
      $this->getBattlefield()->logEvent("event", 
        "CHANGING_SIDE",
        array("!old_class" => $this->getClass(), "!!old_faction" => $this->getName(),
          "!new_class" => $faction->getClass(), "!!new_faction" => $faction->getName()
        ));
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
    $this->getBattlefield()->logEvent("event", "GAME_OVER",
      array("!class" => $this->getClass(), "!!faction" => $this->getName()));
    return $this->setAlive(FALSE);
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
  
  public function setBattlefield(DjambiBattlefield $bt) {
    $this->battlefield = $bt;
  }
  
  public function createPieces($pieces_scheme, $start_scheme, $deads = NULL) {
    foreach($pieces_scheme as $key => $scheme) {
      $alive = TRUE;
      if (!is_null($deads) && is_array($deads)) {
        if (array_search($this->getId(). "-" . $key, $deads) !== FALSE) {
          $alive = FALSE;
        }
      }
      $piece = new DjambiPiece($this, $scheme["shortname"], $scheme["longname"], 
        $scheme["type"], $start_scheme[$key]["x"], $start_scheme[$key]["y"], $alive);
      if (isset($scheme["habilities"]) && is_array($scheme["habilities"])) {
        foreach($scheme["habilities"] as $hability => $value) {
          $piece->setHability($hability, $value);
        }
      }
      $this->pieces[$key] = $piece;
    }
  }
  
  public function toDatabase() {
    return array(
      "name" => $this->name,
      "class" => $this->class,
      "control" => $this->control->getId(),
      "alive" => $this->alive,
      "start_order" => $this->start_order
    );
  }
}