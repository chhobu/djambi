<?php

namespace Djambi\Interfaces;


interface GameDispositionsFactoryInterface {
  public static function loadDisposition($code, $scheme_settings = NULL);
  public static function listPublicDispositions();
  public static function listNbPlayersAvailable();
}
