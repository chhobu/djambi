<?php

namespace Djambi\Tests;


class DjambiTestContext extends DjambiTestBaseContext {
  public function __construct(array $parameters) {
    $this->useContext('new_game', new NewGameContext($parameters));
    $this->useContext('militant', new MilitantContext($parameters));
  }
}
