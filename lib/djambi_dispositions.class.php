<?php
/**
 * @file
 * Description des différentes dispositions de jeu de Djambi possibles
 */

/**
 * Class DjambiGameDisposition
 */
abstract class DjambiGameDisposition  {
  /** @var DjambiBattlefieldScheme $scheme */
  protected $scheme;
  /** @var int $nb */
  protected $nbPlayers = 4;
  /** @var array $sides */
  protected $sides;

  /**
   * @return array
   */
  public function getSides() {
    return $this->sides;
  }

  /**
   * @return int
   */
  public function getNbPlayers() {
    return $this->nbPlayers;
  }

  /**
   * @return DjambiBattlefieldScheme
   */
  public function getScheme() {
    return $this->scheme;
  }

  protected function setNbPlayers($nb_players, $all_playable = TRUE) {
    $this->nbPlayers = $nb_players;
    if ($all_playable) {
      $sides = array_fill(1, $nb_players, 'playable');
      $this->setSides($sides);
    }
    return $this;
  }

  /**
   * Définit le statut des camps en début de partie.
   *
   * @param array $sides
   *   Tableau associatif contenant les états des camps en début de partie.
   *   Valeurs possibles :
   *   - playable => camp jouable
   *   - vassal => camp vassalisé dès le début de partie
   *
   * @return $this
   */
  protected function setSides($sides) {
    $this->sides = $sides;
    return $this;
  }

  protected function setScheme(DjambiBattlefieldScheme $scheme) {
    $this->scheme = $scheme;
    return $this;
  }

  public function getName() {
    return str_replace('DjambiGameDisposition', '', get_class($this));
  }

}

/**
 * Class DjambiGameDispostionFactory
 */
class DjambiGameDispositionsFactory {

  /**
   * Prevents this class from being instancied.
   */
  protected function __construct() {}

  /**
   * Charge une disposition de jeu.
   *
   * @param string $code
   *   Code de la disposition (par exemple : 3hex)
   *
   * @throws DjambiException
   * @return DjambiGameDisposition
   *   Objet étendant la classe abstraite DjambiGameDisposition
   */
  public static function loadDisposition($code) {
    $class = 'DjambiGameDisposition' . $code;
    if (!empty($class) && class_exists($class)) {
      return new $class();
    }
    else {
      throw new DjambiException('Missing disposition class : ' . $class);
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

/**
 * Class DjambiGameDisposition4std
 */
class DjambiGameDisposition4std extends DjambiGameDisposition {
  public function __construct() {
    $scheme = new DjambiBattlefieldSchemeStandardGridWith4Sides();
    $this->setScheme($scheme)->setNbPlayers(4);
  }
}

/**
 * Class DjambiGameDisposition3hex
 */
class DjambiGameDisposition3hex extends DjambiGameDisposition {
  public function __construct() {
    $scheme = new DjambiBattlefieldSchemeHexagonalGridWith3Sides();
    $this->setScheme($scheme)->setNbPlayers(3);
  }
}

/**
 * Class DjambiGameDisposition2std
 */
class DjambiGameDisposition2std extends DjambiGameDisposition {
  public function __construct() {
    $scheme = new DjambiBattlefieldSchemeStandardGridWith4Sides();
    $this->setScheme($scheme)->setNbPlayers(2)->setSides(array(
      1 => 'playable',
      2 => 'vassal',
      3 => 'playable',
      4 => 'vassal',
    ));
  }
}

/**
 * Class DjambiGameDisposition2mini
 */
class DjambiGameDisposition2mini extends DjambiGameDisposition {
  public function __construct() {
    $scheme = new DjambiBattlefieldSchemeMiniGridWith2Sides();
    $this->setScheme($scheme)->setNbPlayers(2);
  }
}
