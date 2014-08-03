<?php
namespace Djambi\GameDispositions;

use Djambi\Exceptions\DispositionNotFoundException;
use Djambi\Gameplay\Faction;
use Djambi\Grids\GridInterface;
use Djambi\PieceDescriptions\BasePieceDescription;

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
   * @throws \Djambi\Exceptions\DispositionNotFoundException
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
  public static function createNewCustomDisposition() {
    $factory = new static();
    return $factory;
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
   * @param array $start_position
   * @param string $start_status
   *   Statut de départ de la faction
   * @param BasePieceDescription[] $specific_pieces
   *   Liste de pièces spécifiques au camp
   *
   * @return GridInterface
   */
  public function addSide(array $start_position = NULL, $start_status = Faction::STATUS_READY, $specific_pieces = array()) {
    $sides = isset($this->settings['sides']) ? $this->settings['sides'] : array();
    $side = array(
      'start_position' => $start_position,
      'start_status' => $start_status,
    );
    if (!empty($specific_pieces)) {
      foreach ($specific_pieces as $key => $piece) {
        $side['specific_pieces'][$key] = $piece->toArray();
      }
    }
    $sides[] = $side;
    $this->addSetting('sides', $sides);
    return $this;
  }

  public function addCommonPiece(BasePieceDescription $piece) {
    $pieces = isset($this->settings['pieceScheme']) ? $this->settings['pieceScheme'] : array();
    $pieces[] = $piece->toArray();
    $this->addSetting('pieceScheme', $pieces);
    return $this;
  }

  public function deliverDisposition() {
    return new GameDispositionCustom($this, $this->getSettings());
  }

}
