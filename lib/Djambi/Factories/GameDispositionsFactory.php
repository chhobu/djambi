<?php
namespace Djambi\Factories;
use Djambi\Exceptions\DispositionNotFoundException;
use Djambi\Interfaces\GameDispositionsFactoryInterface;

/**
 * Class DjambiGameDispostionFactory
 */
class GameDispositionsFactory implements GameDispositionsFactoryInterface {

  /**
   * Prevents this class from being instancied.
   */
  protected function __construct() {}

  /**
   * Charge une disposition de jeu.
   *
   * @param string $code
   *   Code de la disposition (par exemple : 3hex)
   * @param array $scheme_settings
   *   Tableau associatif contenant les options de la disposition
   *
   * @throws \Djambi\Exceptions\DispositionNotFoundException
   * @return \Djambi\GameDisposition
   *   Objet étendant la classe abstraite DjambiGameDisposition
   */
  public static function loadDisposition($code, $scheme_settings = NULL) {
    $class = '\Djambi\GameDispositions\GameDisposition' . $code;
    if (class_exists($class)) {
      return new $class(new self(), $scheme_settings);
    }
    elseif (class_exists($code)) {
      return new $code(new self(), $scheme_settings);
    }
    else {
      throw new DispositionNotFoundException("Unable to load disposition " . $code);
    }
  }

  /**
   * Liste les dispositions de jeu publiques.
   *
   * @return array
   *   Tableau associatif, contenant en clé le code de disposition,
   *   en valeur, son nom d'affichage.
   */
  public static function listPublicDispositions() {
    return array(
      '2std' => '2STD_DESCRIPTION',
      '3hex' => '3HEX_DESCRIPTION',
      '4std' => '4STD_DESCRIPTION',
    );
  }

  /**
   * Liste les différents nombres de joueurs possibles sur les parties.
   *
   * @return array
   *   Tableau de clés / valeurs affiché dans une liste de type select
   */
  public static function listNbPlayersAvailable() {
    return array(2 => 2, 3 => 3, 4 => 4);
  }

}
