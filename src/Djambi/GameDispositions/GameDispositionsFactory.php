<?php
namespace Djambi\GameDispositions;

use Djambi\GameDispositions\Exceptions\DispositionNotFoundException;
use Djambi\Gameplay\Faction;
use Djambi\Grids\GridInterface;
use Djambi\PieceDescriptions\PiecesContainerInterface;

/**
 * Class DjambiGameDispostionFactory
 */
class GameDispositionsFactory implements GameDispositionsFactoryInterface {
  /** @var array */
  protected $settings = array();

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
   * @throws \Djambi\GameDispositions\Exceptions\DispositionNotFoundException
   * @return BaseGameDisposition
   *   Objet étendant la classe abstraite DjambiGameDisposition
   */
  public static function useDisposition($code, $scheme_settings = NULL) {
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
   * @return GameDispositionsFactory
   */
  public static function initiateCustomDisposition() {
    $factory = new static();
    return $factory;
  }

  /**
   * Liste les dispositions de jeu publiques.
   *
   * @return array
   */
  public static function listPublicDispositions() {
    $dispositions = array();
    $classes = get_declared_classes();
    foreach($classes as $class_name) {
      $implements = class_implements($class_name);
      if(in_array('\Djambi\GameDispositions\DispositionInterface', $implements) &&
        in_array('\Djambi\Interfaces\ExposedElementInterface', $implements)) {
          $dispositions[$class_name] = call_user_func($class_name . '::getDescription');
      }
    }
    return $dispositions;
  }

  /**
   * Liste les différents nombres de joueurs possibles sur les parties.
   *
   * @return array
   *   Tableau de clés / valeurs affiché dans une liste de type select
   */
  public static function listNbPlayersAvailable() {
    $nb_players = array();
    $classes = get_declared_classes();
    foreach($classes as $class_name) {
      $implements = class_implements($class_name);
      if(in_array('\Djambi\GameDispositions\DispositionInterface', $implements) &&
        in_array('\Djambi\Interfaces\ExposedElementInterface', $implements)) {
        $nb_current = call_user_func($class_name . '::getNbPlayers');
        $nb_players[$nb_current] = $nb_current;
      }
    }
    return $nb_players;
  }

  protected function getSettings() {
    return $this->settings;
  }

  protected function addSetting($setting_name, $setting_value) {
    $this->settings[$setting_name] = $setting_value;
    return $this;
  }

  public function setDimensions($cols, $rows) {
    $this->addSetting('cols', $cols);
    $this->addSetting('rows', $rows);
    return $this;
  }

  public function setShape($shape) {
    $this->addSetting('disposition', $shape);
    return $this;
  }

  public function addSpecialCell($type, $location) {
    $special_cells = isset($this->settings['specialCells']) ? $this->settings['specialCells'] : array();
    $special_cells[] = array(
      'type' => $type,
      'location' => $location,
    );
    $this->addSetting('specialCells', $special_cells);
    return $this;
  }

  /**
   * Ajoute une faction dans la grille.
   *
   * @param PiecesContainerInterface $container
   *   Liste de pièces spécifiques au camp
   * @param mixed $start_position
   * @param string $start_status
   *   Statut de départ de la faction
   *
   * @return GridInterface
   */
  public function addSide(PiecesContainerInterface $container, $start_position = NULL, $start_status = Faction::STATUS_READY) {
    $sides = isset($this->settings['sides']) ? $this->settings['sides'] : array();
    $side['start_status'] = $start_status;
    $side['start_position'] = $start_position;
    $side['pieces'] = $container;
    $sides[] = $side;
    $this->addSetting('sides', $sides);
    return $this;
  }

  public function deliverDisposition() {
    return new GameDispositionCustom($this->getSettings());
  }

}
