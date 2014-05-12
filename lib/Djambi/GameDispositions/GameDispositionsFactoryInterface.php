<?php

namespace Djambi\GameDispositions;


interface GameDispositionsFactoryInterface {
  public static function useDisposition($code, $scheme_settings = NULL);
  public static function listPublicDispositions();
  public static function listNbPlayersAvailable();
}
