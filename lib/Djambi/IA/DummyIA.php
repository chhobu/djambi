<?php
namespace Djambi\IA;


use Djambi\IA;
use Djambi\Players\ComputerPlayer;

class DummyIA extends IA {
  public function __construct(ComputerPlayer $player, $name = 'BetaBot') {
    parent::__construct($player, $name);
  }
} 