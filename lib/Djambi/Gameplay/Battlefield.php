<?php
/**
 * @file
 * Introduic une classe DjambiBattlefield permettant de construire et de gérér
 * un plateau de jeu.
 */

namespace Djambi\Gameplay;

use Djambi\Exceptions\GameNotFoundException;
use Djambi\Exceptions\GridInvalidException;
use Djambi\Exceptions\CellNotFoundException;
use Djambi\Exceptions\FactionNotFoundException;
use Djambi\Exceptions\PieceNotFoundException;
use Djambi\GameManagers\BasicGameManager;
use Djambi\GameManagers\GameManagerInterface;
use Djambi\GameOptions\StandardRuleset;
use Djambi\Grids\BaseGrid;
use Djambi\Persistance\PersistantDjambiObject;
use Djambi\PieceDescriptions\BasePieceDescription;
use Djambi\Players\HumanPlayer;
use Djambi\Players\PlayerInterface;
use Djambi\Strings\Glossary;
use Djambi\Strings\GlossaryTerm;

/**
 * Class DjambiBattlefield
 */
class Battlefield extends PersistantDjambiObject implements BattlefieldInterface {

  /* @var GameManagerInterface */
  protected $gameManager;
  /** @var Cell[] */
  protected $cells = array();
  /** @var Faction[] */
  protected $factions = array();
  /** @var array */
  protected $playOrder;
  /** @var array */
  protected $cellsIndex = array();
  /** @var String */
  protected $ruler;
  /** @var Turn */
  protected $currentTurn;
  /** @var array */
  protected $pastTurns = array();

  /**
   * Préparation à l'enregistrement en BdD : transformation en tableau.
   *
   * @return array
   */
  protected function prepareArrayConversion() {
    $this->addPersistantProperties(array(
      'factions',
      'turns',
      'currentTurn',
      'playOrder',
    ));
    $this->addDependantObjects(array('gameManager' => 'id'));
    return parent::prepareArrayConversion();
  }

  /**
   * Charge une grille de Djambi.
   *
   * @param array $array
   * @param array $context
   *
   * @throws CellNotFoundException
   * @throws FactionNotFoundException
   * @throws GameNotFoundException
   * @return Battlefield
   */
  public static function fromArray(array $array, array $context = array()) {
    if (empty($context['gameManager'])) {
      throw new GameNotFoundException("Cannot load a battlefield without a game manager context !");
    }
    /** @var GameManagerInterface $game */
    $game = $context['gameManager'];
    $battlefield = new static($game);
    $battlefield->pastTurns = isset($array['pastTurns']) ? $array['pastTurns'] : array();
    $battlefield->factions = array();
    if (isset($array['playOrder'])) {
      $battlefield->playOrder = $array['playOrder'];
    }
    $context['battlefield'] = $battlefield;
    foreach ($array['factions'] as $key => $faction) {
      $battlefield->factions[$key] = call_user_func($faction['className'] . '::fromArray', $faction, $context);
    }
    if (!empty($array['currentTurn'])) {
      $battlefield->setCurrentTurn(call_user_func($array['currentTurn']['className'] . '::fromArray', $array['currentTurn'], $context));
    }
    return $battlefield;
  }

  /**
   * Construction de l'objet DjambiBattlefield.
   *
   * @param GameManagerInterface $gm
   *   Objet de gestion du jeu
   *
   * @return Battlefield
   *   Nouvel objet plateau de jeu
   */
  protected function __construct(GameManagerInterface $gm) {
    $this->gameManager = $gm;
    $this->buildField();
  }

  /**
   * Crée une nouvelle grille de Djambi.
   *
   * @param GameManagerInterface $game
   *   Objet de gestion de la partie
   * @param PlayerInterface[] $players
   *   Liste des joueurs
   *
   * @throws GridInvalidException
   * @return Battlefield
   *   Nouvelle grille de Djambi
   */
  public static function createNewBattlefield(GameManagerInterface $game, $players) {
    $battlefield = new self($game);
    $scheme = $game->getDisposition()->getGrid();
    $directions = $scheme->getDirections();
    $scheme_sides = $scheme->getSides();
    // Construction des factions :
    $ready = TRUE;
    $controls = array();
    foreach ($scheme_sides as $side) {
      if ($side['start_status'] == Faction::STATUS_READY) {
        /* @var HumanPlayer $player */
        if ($game->getMode() == BasicGameManager::MODE_SANDBOX) {
          $player = current($players);
        }
        else {
          $player = array_shift($players);
        }
        if (empty($player)) {
          $side['start_status'] = Faction::STATUS_EMPTY_SLOT;
          $ready = FALSE;
        }
      }
      else {
        $player = NULL;
      }
      $faction = new Faction($battlefield, $side['id'],
        $side['name'], $side['class'], $side['start_order'], $player);
      $faction->setStatus($side['start_status']);
      if (isset($side['control'])) {
        $controls[$faction->getId()] = $side['control'];
      }
      $battlefield->factions[$side['id']] = $faction;
      // Placement des pièces communes :
      $start_order = $faction->getStartOrder();
      $leader_position = current(array_slice($scheme_sides, $start_order - 1, 1));
      if (!empty($leader_position['placement']) && $leader_position['placement'] == BaseGrid::PIECE_PLACEMENT_RELATIVE) {
        $start_scheme = array();
        $axis = NULL;
        foreach ($directions as $orientation => $direction) {
          $next_cell = $battlefield->findCell($leader_position['x'], $leader_position['y']);
          $continue = TRUE;
          while ($continue) {
            if ($next_cell->getType() == Cell::TYPE_THRONE) {
              $axis = $orientation;
              break;
            }
            $neighbours = $next_cell->getNeighbours();
            if (isset($neighbours[$orientation])) {
              $next_cell = $neighbours[$orientation];
            }
            else {
              $continue = FALSE;
            }
          }
          if (!empty($axis)) {
            break;
          }
        }
        if (empty($axis)) {
          throw new GridInvalidException('Bad pieces start scheme.');
        }
        foreach ($scheme->getPieceScheme() as $piece_id => $piece) {
          $start_position = $piece->getStartPosition();
          $starting_cell = $battlefield->findCell($leader_position['x'], $leader_position['y']);
          for ($i = 0; $i < $start_position['y']; $i++) {
            $neighbours = $starting_cell->getNeighbours();
            $starting_cell = $neighbours[$axis];
          }
          if ($start_position['x'] > 0) {
            $new_axis = $directions[$axis]['right'];
          }
          else {
            $new_axis = $directions[$axis]['left'];
          }
          for ($i = 0; $i < abs($start_position['x']); $i++) {
            $neighbours = $starting_cell->getNeighbours();
            $starting_cell = $neighbours[$new_axis];
          }
          $start_scheme[$piece_id] = array(
            'x' => $starting_cell->getX(),
            'y' => $starting_cell->getY(),
          );
        }
        $faction->createPieces($scheme->getPieceScheme(), $start_scheme);
      }
      // Placement des pièces spécifiques
      if (!empty($side['specific_pieces'])) {
        $specific_start_positions = array();
        /* @var BasePieceDescription $specific_piece_description */
        foreach ($side['specific_pieces'] as $key => $specific_piece_description) {
          if (!is_array($specific_piece_description->getStartPosition())) {
            $cell = $battlefield->findCellByName($specific_piece_description->getStartPosition());
            $specific_start_position = array('x' => $cell->getX(), 'y' => $cell->getY());
          }
          else {
            $specific_start_position = $specific_piece_description->getStartPosition();
          }
          $specific_start_positions[$key] = $specific_start_position;
        }
        $faction->createPieces($side['specific_pieces'], $specific_start_positions);
      }
    }
    if (!empty($controls)) {
      foreach ($controls as $controlled => $controller) {
        $battlefield->findFactionById($controlled)->setControl($battlefield->findFactionById($controller));
      }
    }
    $game->setStatus($ready ? BasicGameManager::STATUS_PENDING : BasicGameManager::STATUS_RECRUITING);
    return $battlefield;
  }

  /**
   * Génère les cellules d'une grille de Djambi.

   * @return Battlefield
   *   Grille de Djambi courante
   */
  protected function buildField() {
    $grid = $this->getGameManager()->getDisposition()->getGrid();
    $special_cells = $grid->getSpecialCells();
    $cols = $grid->getCols();
    $rows = $grid->getRows();
    for ($x = 1; $x <= $cols; $x++) {
      for ($y = 1; $y <= $rows; $y++) {
        Cell::createByXY($this, $x, $y);
      }
    }
    foreach ($special_cells as $description) {
      $cell = $this->findCell($description['location']['x'], $description['location']['y']);
      $cell->setType($description['type']);
    }
    foreach ($this->cells as $cell) {
      if ($cell->getType() == Cell::TYPE_DISABLED) {
        continue;
      }
      foreach ($grid->getDirections() as $d => $direction) {
        $new_x = $cell->getX() + $direction['x'];
        $new_y = $cell->getY() + $direction['y'];
        if (!empty($direction['modulo_x'])) {
          if ($cell->getY() % 2 == 1) {
            if (in_array($d, array('NE', 'SE'))) {
              $new_x = $cell->getX();
            }
          }
          else {
            if (in_array($d, array('NW', 'SW'))) {
              $new_x = $cell->getX();
            }
          }
        }
        try {
          $neighbour = $this->findCell($new_x, $new_y);
          if ($neighbour->getType() != Cell::TYPE_DISABLED) {
            $cell->addNeighbour($neighbour, $d);
          }
        }
        catch (CellNotFoundException $e) {
          continue;
        }
      }

    }
    return $this;
  }

  /**
   * @return Faction[]
   */
  public function getFactions() {
    return $this->factions;
  }

  /**
   * Renvoie un objet faction à partir de son identifiant.
   *
   * @param string $id
   *   Identifiant de la faction à renvoyer.
   *
   * @throws FactionNotFoundException
   * @return Faction
   *   Faction si trouvée, FALSE sinon.
   */
  public function findFactionById($id) {
    foreach ($this->factions as $faction) {
      if ($faction->getId() == $id) {
        $faction->setBattlefield($this);
        return $faction;
      }
    }
    throw new FactionNotFoundException("Faction " . $id . " not found.");
  }

  /**
   * Renvoie l'objet faction actuellement en tour de jeu.

   * @return Faction
   *   Faction si trouvé, NULL sinon.
   */
  public function getPlayingFaction() {
    if (!$this->getGameManager()->isPending()) {
      return NULL;
    }
    return $this->findFactionById(current($this->getPlayOrder()));
  }

  /**
   * Renvoie un object pièce à partir de son identifiant.
   *
   * @param string $piece_id
   *   Identifiant d'une pièce (par exemple : R-N)
   *
   * @throws PieceNotFoundException
   * @return Piece
   *   Renvoie la pièce associée.
   */
  public function findPieceById($piece_id) {
    list($faction_id, $piece_description_id) = explode("-", $piece_id, 2);
    $faction = $this->findFactionById($faction_id);
    $pieces = $faction->getPieces();
    if (isset($pieces[$piece_description_id])) {
      return $pieces[$piece_description_id];
    }
    else {
      throw new PieceNotFoundException(new GlossaryTerm(Glossary::EXCEPTION_PIECE_NOT_FOUND,
        array('@piece' => $piece_id)));
    }
  }

  public function getCells() {
    return $this->cells;
  }

  /**
   * Enregistre une nouvelle cellule sur la grille.
   */
  public function registerCell(Cell $cell) {
    $this->cells[$cell->getName()] = $cell;
    $this->cellsIndex[$cell->getX()][$cell->getY()] = $cell->getName();
    return $this;
  }

  /**
   * Retourne une cellule de la grille du jeu.
   *
   * @param int $x
   *   Coordonée verticale
   * @param int $y
   *   Coordonnée horizontale
   *
   * @throws CellNotFoundException
   * @return Cell
   *   Cellulue de Djambi
   */
  public function findCell($x, $y) {
    if (!isset($this->cellsIndex[$x][$y])) {
      throw new CellNotFoundException('X:' . $x . '-Y:' . $y);
    }
    return $this->findCellByName($this->cellsIndex[$x][$y]);
  }

  /**
   * @param string $name
   *
   * @return Cell
   * @throws CellNotFoundException
   */
  public function findCellByName($name) {
    if (!isset($this->cells[$name])) {
      throw new CellNotFoundException(new GlossaryTerm(Glossary::EXCEPTION_CELL_NOT_FOUND,
        array('@name' => $name)));
    }
    return $this->cells[$name];
  }

  public function getPastTurns() {
    return $this->pastTurns;
  }

  protected function getLastTurn() {
    return end($this->pastTurns);
  }

  public function getCurrentTurn() {
    return $this->currentTurn;
  }

  public function setCurrentTurn(Turn $turn) {
    $this->currentTurn = $turn;
    return $this;
  }

  /**
   * Renvoie le gestionnaire de jeu associé à cette grille.
   */
  public function getGameManager() {
    return $this->gameManager;
  }

  /**
   * @return Battlefield
   *   Grille de Djambi courante
   */
  public function cancelLastTurn() {
    $last_turn_array = array_pop($this->pastTurns);
    $context['battlefield'] = $this;
    unset($last_turn_array['end']);
    /** @var Turn $last_turn */
    $last_turn = call_user_func($last_turn_array['className'] . '::fromArray', $last_turn_array, $context);
    $last_turn->cancelCompletedMove();
    $this->currentTurn = NULL;
    $this->playOrder = NULL;
    $this->getGameManager()->save();
    $this->prepareTurn();
    return $this;
  }

  /**
   * @param $living_factions
   *
   * @return Battlefield
   *   Grille de Djambi courante
   */
  public function endGame($living_factions) {
    $nb_living_factions = count($living_factions);
    if ($nb_living_factions == 1) {
      $winner_id = current($living_factions);
      $winner = $this->findFactionById($winner_id);
      $winner->setStatus(Faction::STATUS_WINNER)->setRanking(1);
      $this->logEvent('event', 'THE_WINNER_IS', array('faction1' => $winner->getId()));
    }
    else {
      $this->logEvent("event", "DRAW");
      foreach ($living_factions as $faction_id) {
        $faction = $this->findFactionById($faction_id);
        $faction->setStatus(Faction::STATUS_DRAW)->setRanking($nb_living_factions);
      }
    }
    $this->getGameManager()->setStatus(BasicGameManager::STATUS_FINISHED);
    $this->buildFinalRanking($nb_living_factions);
    $this->logEvent("event", "END");
    $this->pastTurns[] = $this->getCurrentTurn()->endsTurn()->toArray();
    $this->currentTurn = NULL;
    $this->playOrder = NULL;
    $this->getGameManager()->save();
    return $this;
  }

  /**
   * @return Battlefield
   *   Grille de Djambi courante
   */
  public function changeTurn() {
    // Vérification des conditions de victoire :
    $living_factions = array();
    foreach ($this->getFactions() as $faction) {
      if ($faction->isAlive()) {
        $control_leader = $faction->checkLeaderFreedom();
        if (!$control_leader) {
          $this->logEvent("event", "SURROUNDED", array('faction1' => $faction->getId()));
          if ($this->getGameManager()->getOption(StandardRuleset::RULE_COMEBACK) == 'never'
          && $this->getGameManager()->getOption(StandardRuleset::RULE_VASSALIZATION) == 'full_control') {
            foreach ($faction->getPieces() as $piece) {
              if ($piece->isAlive() && $piece->getDescription()->hasHabilityMustLive() && $piece->getFaction()->getId() == $faction->getId()) {
                $this->logMove($piece, $piece->getPosition(), 'elimination');
                $piece->setAlive(FALSE);
              }
            }
          }
          $faction->dieDieDie(Faction::STATUS_SURROUNDED);
        }
        else {
          $living_factions[] = $faction->getId();
        }
      }
      elseif ($this->getGameManager()->getOption(StandardRuleset::RULE_COMEBACK) == 'surrounded'
      || ($this->getGameManager()->getOption(StandardRuleset::RULE_COMEBACK) == 'allowed' && empty($kings))) {
        if ($faction->getStatus() == Faction::STATUS_SURROUNDED) {
          $control_leader = $faction->checkLeaderFreedom();
          if ($control_leader) {
            $faction->setStatus(Faction::STATUS_READY);
            $this->logEvent("event", "COMEBACK_AFTER_SURROUND", array('faction1' => $faction->getId()));
          }
        }
      }
    }
    $total = count($living_factions);
    if ($total < 2) {
      $this->endGame($living_factions);
    }
    else {
      // Attribution des pièces vivantes à l'occupant du trône :
      $this->findRuler();
      if (!empty($this->getRuler())) {
        foreach ($this->getFactions() as $faction) {
          if (!$faction->getControl()->isAlive()) {
            // Cas d'un abandon :
            // lors de la prise de pouvoir, retrait de l'ancien chef.
            $pieces = $faction->getPieces();
            foreach ($pieces as $piece) {
              if ($this->getGameManager()->getOption(StandardRuleset::RULE_VASSALIZATION) == 'full_control'
                && $piece->isAlive() && $piece->getDescription()->hasHabilityMustLive()) {
                $piece->setAlive(FALSE);
                $this->logMove($piece, $piece->getPosition(), 'elimination');
              }
            }
            // Prise de contrôle
            $faction->setControl($this->findFactionById($this->getRuler()));
          }
        }
      }
      elseif ($this->getGameManager()->getOption(StandardRuleset::RULE_VASSALIZATION) != 'full_control') {
        foreach ($this->getFactions() as $faction) {
          if (!$faction->isAlive()) {
            $allowed_statuses = array(
              Faction::STATUS_DEFECT,
              Faction::STATUS_WITHDRAW,
              Faction::STATUS_SURROUNDED,
            );
            if (in_array($faction->getStatus(), $allowed_statuses) && $faction->getControl()->getId() != $faction->getId()) {
              $faction->setControl($faction);
            }
          }
        }
      }
      $this->pastTurns[] = $this->getCurrentTurn()->endsTurn()->toArray();
      $this->currentTurn = NULL;
      $this->playOrder = NULL;
      $this->getGameManager()->save();
      $this->prepareTurn();
    }
    return $this;
  }

  /**
   * @return array
   */
  protected function findRuler() {
    $kings = array();
    $thrones = $this->getSpecialCells(Cell::TYPE_THRONE);
    foreach ($thrones as $throne) {
      $cell = $this->findCellByName($throne);
      $occupant = $cell->getOccupant();
      if (!empty($occupant)) {
        if ($occupant->isAlive()) {
          $kings[] = $occupant->getFaction()->getControl()->getId();
          break;
        }
      }
    }
    if (!empty($kings) && count(array_unique($kings)) == 1) {
      $this->ruler = current($kings);
    }
    else {
      $this->ruler = NULL;
    }
    return NULL;
  }

  public function getRuler() {
    return $this->ruler;
  }

  public function getRulerFaction() {
    if (!empty($this->ruler)) {
      return $this->findFactionById($this->ruler);
    }
    return NULL;
  }

  /**
   * @return array
   */
  public function getPlayOrder() {
    if (!empty($this->playOrder)) {
      reset($this->playOrder);
    }
    return $this->playOrder;
  }

  public function prepareTurn() {
    if (is_null($this->getPlayOrder())) {
      $this->definePlayOrder();
      $this->defineMovablePieces();
    }
    return $this;
  }

  /**
   * @return bool
   */

  protected function definePlayOrder() {
    // Réinitialisation
    $this->playOrder = array();
    // Récupération des factions, de leur statut et de leurs ordres de départ
    // Constitution d'un schéma par défaut des tours de jeu possibles
    $nb_factions = 0;
    $scheme_size = count($this->factions) * 2;
    $turn_scheme = array();
    foreach ($this->factions as $faction) {
      if ($faction->isAlive() && $faction->getControl()->getId() == $faction->getId()) {
        $nb_factions++;
      }
      $turn_scheme[$faction->getStartOrder() * 2 - 1] = array(
        "side" => $faction->getId(),
        "type" => Cell::TYPE_STANDARD,
        "played" => FALSE,
        "playable" => $faction->getControl()->getId() == $faction->getId(),
        "alive" => $faction->isAlive(),
        "new_round" => FALSE,
      );
      $turn_scheme[$faction->getStartOrder() * 2] = array(
        "side" => NULL,
        "type" => Cell::TYPE_THRONE,
        "played" => FALSE,
        "playable" => $faction->getControl()->getId() == $faction->getId(),
        "alive" => $faction->isAlive(),
        "new_round" => FALSE,
        "exclude" => array($faction->getId()),
      );
    }
    ksort($turn_scheme);
    // Copie du tableau généré pour s'assurer d'une continuité
    foreach ($turn_scheme as $scheme) {
      $scheme['new_round'] = TRUE;
      $turn_scheme[] = $scheme;
    }
    // Récupération des données du tour précédent
    if (!empty($this->pastTurns)) {
      $last_turn = $this->getLastTurn();
      $current_round = $last_turn['round'];
      $last_play_order_key = $last_turn['playOrderKey'];
    }
    else {
      $last_turn = NULL;
      $last_play_order_key = -1;
      $current_round = 1;
    }
    // Gestion des tours de jeu liés à l'occupation de la case centrale
    if ($this->getGameManager()->getStatus() == BasicGameManager::STATUS_PENDING) {
      $this->findRuler();
      if (!empty($this->ruler)) {
        if ($nb_factions > 2) {
          $ruler_key = $this->findFactionById($this->ruler)->getStartOrder() * 2 - 2;
          while (isset($turn_scheme[$ruler_key])) {
            if ($turn_scheme[$ruler_key]['alive']) {
              $turn_scheme[$ruler_key]['exclude'][] = $this->ruler;
              break;
            }
            $ruler_key = $ruler_key - 2;
          }
          $ruler_key = $this->findFactionById($this->ruler)->getStartOrder() * 2 - 2 + $scheme_size;
          while (isset($turn_scheme[$ruler_key])) {
            if ($turn_scheme[$ruler_key]['alive'] && $turn_scheme[$ruler_key]['side'] != $this->ruler) {
              $turn_scheme[$ruler_key]['exclude'][] = $this->ruler;
              break;
            }
            $ruler_key = $ruler_key - 2;
          }
        }
        foreach ($turn_scheme as $key => $scheme) {
          if ($scheme['type'] == Cell::TYPE_THRONE) {
            $turn_scheme[$key]['side'] = $this->ruler;
            if (in_array($this->ruler, $scheme['exclude'])) {
              $turn_scheme[$key]['playable'] = FALSE;
            }
          }
        }
      }
    }
    // Ne pas répéter les tours de jeu de la faction au pouvoir
    // dans le cas d'une négociation de paix
    elseif ($this->getGameManager()->getStatus() == BasicGameManager::STATUS_DRAW_PROPOSAL) {
      foreach ($turn_scheme as $key => $scheme) {
        if (!empty($scheme['side']) && $scheme['playable']) {
          $side = $this->findFactionById($scheme['side']);
          if (!is_null($side->getDrawStatus())) {
            $turn_scheme[$key]['playable'] = FALSE;
          }
        }
      }
    }
    // Détermination des tours ayant déjà été joués
    foreach ($turn_scheme as $key => $scheme) {
      if ($last_play_order_key >= $key) {
        $turn_scheme[$key]['played'] = TRUE;
      }
    }
    // Détermination d'un ordre de jeu possible
    $first_element = NULL;
    foreach ($turn_scheme as $key => $scheme) {
      if (!is_null($first_element) && $key >= $first_element + $scheme_size) {
        break;
      }
      if ($scheme['playable'] && !$scheme['played'] && !empty($scheme['side']) && $scheme['alive']) {
        if (is_null($first_element) && !empty($last_turn) && $last_turn['actingFaction'] == $scheme['side']) {
          // Corrections de cas non-standards
          if ($nb_factions > 2) {
            // Un camp ne peut pas jouer 2x de suite
            // après avoir tué un chef ennemi
            continue;
          }
          elseif ((isset($last_turn['move']['destination']) && $this->findCellByName($last_turn['move']['destination'])->getType() == Cell::TYPE_THRONE)
          || (isset($last_turn['move']['origin']) && $this->findCellByName($last_turn['move']['origin'])->getType() == Cell::TYPE_THRONE)) {
            // Un camp ne peut pas jouer immédiatement après avoir
            // acquis ou quitté le pouvoir
            continue;
          }
        }
        $this->playOrder[$key] = $scheme['side'];
        if (is_null($first_element)) {
          $first_element = $key;
        }
      }
    }
    // Détermination de la faction en tour de jeu
    $this->findFactionById(current($this->playOrder))->setPlaying(TRUE);
    // Création d'un nouveau tour
    $new_play_order_key = key($this->playOrder);
    if ($turn_scheme[$new_play_order_key]['new_round']) {
      $current_round++;
      $new_play_order_key = $new_play_order_key - $scheme_size;
    }
    $this->setCurrentTurn(Turn::begin($this, $current_round, $new_play_order_key));
    return $this;
  }

  protected function cleanupMovableStates() {
    foreach ($this->getFactions() as $faction) {
      foreach ($faction->getPieces() as $piece) {
        $piece->setMovable(FALSE);
        $piece->setAllowableMoves(array());
      }
    }
    foreach ($this->cells as $cell) {
      $cell->setReachable(FALSE);
    }
    return $this;
  }

  protected function defineMovablePieces() {
    $this->cleanupMovableStates();
    $can_move = FALSE;
    foreach ($this->getPlayingFaction()->getControlledPieces() as $piece) {
      $moves = $piece->buildAllowableMoves();
      if ($moves > 0) {
        $can_move = TRUE;
      }
    }
    if (!$can_move && $this->getPlayingFaction()->getSkippedTurns() == $this->getGameManager()->getOption(StandardRuleset::GAMEPLAY_ELEMENT_SKIPPED_TURNS)) {
      $this->getPlayingFaction()->withdraw();
    }
    return $this;
  }

  /**
   * @return int
   */
  public function countLivingFactions() {
    $nb_alive = 0;
    /* @var $faction Faction */
    foreach ($this->getFactions() as $faction) {
      if ($faction->isAlive() && $faction->getControl()->getId() == $faction->getId()) {
        $nb_alive++;
      }
    }
    return $nb_alive;
  }

  /**
   * Trouve les coordonnées des cases voisines.
   *
   * @param Cell $cell
   *   Case d'origine
   * @param bool $use_diagonals
   *   TRUE pour permettre le mouvement en diagonale.
   *
   * @return array
   *   Coordonnées (x,y) des cases voisines
   */
  public function findNeighbourCells(Cell $cell, $use_diagonals = TRUE) {
    $next_positions = array();
    foreach ($cell->getNeighbours() as $direction_key => $neighbour) {
      $direction = $this->getGameManager()->getDisposition()->getGrid()->getDirection($direction_key);
      if ($use_diagonals || !$direction['diagonal']) {
        $next_positions[] = array(
          'x' => $neighbour->getX(),
          'y' => $neighbour->getY(),
        );
      }
    }
    return $next_positions;
  }

  public function getSpecialCells($type) {
    $special_cells = array();
    foreach ($this->cells as $key => $cell) {
      if ($cell->getType() == $type) {
        $special_cells[] = $key;
      }
    }
    return $special_cells;
  }

  /**
   * Liste les cases libres d'une grille de Djambi
   *
   * @param Piece $piece
   *   Pièce à déplacer
   * @param bool $keep_alive
   *   TRUE si la pièce reste vivante
   * @param bool $murder
   *   TRUE si la pièce à déplacer vient d'être tuée
   * @param Cell $exclude_cell
   *   Force le caractère occupé d'une pièce
   *
   * @return Cell[]
   *   Liste de cases libres
   */
  public function getFreeCells(Piece $piece, $keep_alive = TRUE, $murder = FALSE, Cell $exclude_cell = NULL) {
    $freecells = array();
    foreach ($this->cells as $key => $cell) {
      $occupant = $cell->getOccupant();
      if (empty($occupant) && (!is_null($exclude_cell) || $exclude_cell->getName() != $key)) {
        if ($cell->getType() == Cell::TYPE_THRONE) {
          // Un leader peut être manipulé au pouvoir dans tous les cas :
          if ($piece->getDescription()->hasHabilityAccessThrone() && $piece->isAlive() && $keep_alive) {
            $freecells[$key] = $cell;
          }
          // Un leader mort peut être placé au pouvoir si variante activée :
          elseif ($this->getGameManager()->getOption(StandardRuleset::RULE_EXTRA_INTERACTIONS) == 'extended' && $murder
            && $piece->getDescription()->hasHabilityAccessThrone()) {
            $freecells[$key] = $cell;
          }
        }
        else {
          $freecells[$key] = $cell;
        }
      }
    }
    return $freecells;
  }

  // FIXME reconstruire cette fonction !
  protected function buildFinalRanking($begin) {
    return $this;
  }

  /**
   * Enregistre un événement d'une partie.
   *
   * @param string $type
   *   Différents types possibles : event, info, notice
   * @param string $event_txt
   *   Description de l'événement
   * @param array $event_args
   *   Arguments inclus dans la description de l'événement
   * @param int $time
   *   Timestamp auquel a eu lieu l'événement
   *
   * @return Battlefield
   *   Grille de Djambi courante
   *
   * @deprecated
   */
  // FIXME retirer cette fonction
  public function logEvent($type, $event_txt, $event_args = NULL, $time = NULL) {
    $event = array(
      //"turn" => $this->getCurrentTurnId(),
      "time" => is_null($time) ? time() : $time,
      "type" => $type,
      "event" => $event_txt,
      "args" => $event_args,
    );
    //$this->events[] = $event;
    return $this;
  }

  /**
   * Enregistre un mouvemement dans un tableau récapitulatif.
   * Entrées du tableau :
   * - turn : identifiant du tour de jeu courant
   * - time : timestamp du mouvement
   * - target_faction : identifiant du camp concerné par le mouvement
   * - target : indentifiant de la pièce concernée par le mouvement
   * - from : identifiant de la cellule de départ du mouvement
   * - to : identifiant de la cellule de fin du mouvement
   * - type : type de mouvement (move, necromove, murder, manipulation,
   * elimination ou evacuation)
   * - acting (optionnel) : identifiant de la pièce ayant provoqué le mouvement
   * - acting_faction (optionnel) : identifiant de la faction ayant provoqué
   * le mouvement
   * - special_event (optionnel) : événement déclenché par le mouvement
   *
   * @param Piece $target_piece
   *   Pièce concernée par le mouvement
   * @param Cell $destination_cell
   *   Destination du mouvement (move, necromove, murder, manipulation,
   *   elimination ou evacuation)
   * @param string $type
   *   Type du mouvement
   * @param Piece $acting_piece
   *   Pièce ayant provoqué le mouvement
   *
   * @return Battlefield
   *   Grille de Djambi courante
   *
   * @deprecated
   */
  public function logMove(Piece $target_piece, Cell $destination_cell, $type = "move", Piece $acting_piece = NULL) {
    $origin_cell_object = $target_piece->getPosition();
    if ($destination_cell->getType() == Cell::TYPE_THRONE && $target_piece->getDescription()->hasHabilityAccessThrone() && $target_piece->isAlive()) {
      $special_event = 'THRONE_ACCESS';
    }
    elseif ($destination_cell->getType() == Cell::TYPE_THRONE && $target_piece->getDescription()->hasHabilityAccessThrone() && !$target_piece->isAlive()) {
      $special_event = 'THRONE_MAUSOLEUM';
    }
    elseif ($origin_cell_object->getType() == Cell::TYPE_THRONE && $target_piece->getDescription()->hasHabilityAccessThrone() && $target_piece->isAlive()) {
      $special_event = 'THRONE_RETREAT';
    }
    elseif ($origin_cell_object->getType() == Cell::TYPE_THRONE && $target_piece->getDescription()->hasHabilityAccessThrone() && !$target_piece->isAlive()) {
      if ($acting_piece->getDescription()->hasHabilityKillThroneLeader()) {
        $special_event = 'THRONE_MURDER';
      }
      else {
        $special_event = 'THRONE_EVACUATION';
      }
    }
    else {
      $special_event = NULL;
    }
    $move = array(
      //'turn' => $this->getCurrentTurnId(),
      'time' => time(),
      'target_faction' => $target_piece->getFaction()->getControl()->getId(),
      'target' => $target_piece->getId(),
      'from' => $origin_cell_object->getName(),
      'to' => $destination_cell->getName(),
      'type' => $type,
    );
    if (!is_null($acting_piece)) {
      $move['acting'] = $acting_piece->getId();
      $move['acting_faction'] = $acting_piece->getFaction()->getControl()->getId();
    }
    if (!is_null($special_event)) {
      $this->logEvent('event', $special_event, array('piece' => $move['target']));
      $move['special_event'] = $special_event;
    }
    //$this->moves[] = $move;
    return $this;
  }

}
