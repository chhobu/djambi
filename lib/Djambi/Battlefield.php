<?php
/**
 * @file
 * Introduic une classe DjambiBattlefield permettant de construire et de gérér
 * un plateau de jeu.
 */

namespace Djambi;
use Djambi\Exceptions\BadGridException;
use Djambi\Exceptions\CellNotFoundException;
use Djambi\Exceptions\FactionNotFoundException;
use Djambi\Exceptions\PieceNotFoundException;
use Djambi\Interfaces\BattlefieldInterface;
use Djambi\Interfaces\GameManagerInterface;
use Djambi\Interfaces\PlayerInterface;
use Djambi\Players\HumanPlayer;

/**
 * Class DjambiBattlefield
 */
class Battlefield implements BattlefieldInterface {
  protected $id;
  /* @var GameManagerInterface $gameManager */
  protected $gameManager;
  /** @var GameDisposition $disposition */
  protected $disposition;
  /** @var Cell[] $cells */
  protected $cells = array();
  /** @var Faction[] $factions */
  protected $factions = array();
  /** @var array $moves */
  protected $moves = array();
  /** @var array $turns */
  protected $turns = array();
  /** @var array $events */
  protected $events = array();
  /** @var array $options */
  protected $options = array();
  /** @var array $habilities_store */
  protected $habilitiesStore = array();
  /** @var array $summary */
  protected $summary = array();
  protected $mode;
  protected $status;
  protected $playOrder;
  protected $displayedTurnId;
  /** @var array $cellsIndex */
  protected $cellsIndex = array();

  /**
   * Construction de l'objet DjambiBattlefield.
   *
   * @param GameManagerInterface $gm
   *   Objet de gestion du jeu
   * @param string $id
   *   Identifiant du jeu
   * @param string $mode
   *   Mode de jeu
   * @param GameDisposition $disposition
   *   Disposition de jeu
   *
   * @return Battlefield
   *   Nouvel objet plateau de jeu
   */
  protected function __construct(GameManagerInterface $gm, $id, $mode, GameDisposition $disposition) {
    $this->gameManager = $gm;
    $this->id = $id;
    $this->disposition = $disposition;
    $this->setDefaultOptions();
    $this->moves = array();
    $this->events = array();
    $this->summary = array();
    $this->mode = $mode;
  }

  /* ---------------------------------------------------------
  ------------- CREATION / CHARGEMENT D'UNE PARTIE -----------
  ----------------------------------------------------------*/

  /**
   * Crée une nouvelle grille de Djambi.
   *
   * @param GameManagerInterface $gm
   *   Objet de gestion de la partie
   * @param PlayerInterface[] $players
   *   Liste des joueurs
   * @param string $id
   *   Identifiant de la grille
   * @param string $mode
   *   Mode de jeu
   * @param GameDisposition $disposition
   *   Disposition de la grille
   *
   * @throws BadGridException
   * @return Battlefield
   *   Nouvelle grille de Djambi
   */
  public static function createNewBattlefield(GameManagerInterface $gm, $players, $id, $mode, GameDisposition $disposition) {
    $battlefield = new self($gm, $id, $mode, $disposition);

    // Construction des factions :
    $ready = TRUE;
    foreach ($battlefield->getDisposition()->getGrid()->getSides() as $side) {
      if ($side['start_status'] == KW_DJAMBI_FACTION_STATUS_READY) {
        /* @var HumanPlayer $player */
        if ($battlefield->getMode() == KW_DJAMBI_MODE_SANDBOX) {
          $player = current($players);
        }
        else {
          $player = array_shift($players);
        }
        if (empty($player)) {
          $side['start_status'] = KW_DJAMBI_FACTION_STATUS_EMPTY_SLOT;
          $ready = FALSE;
        }
      }
      else {
        $player = NULL;
      }
      $data['status'] = $side['start_status'];
      $battlefield->factions[$side['id']] = new Faction($battlefield, $side['id'],
        $side['name'], $side['class'], $side['start_order'], $data, $player);
    }

    // Construction de la grille :
    $battlefield->buildField();
    $scheme = $battlefield->getDisposition()->getGrid();
    $directions = $scheme->getDirections();
    $scheme_sides = $scheme->getSides();
    foreach ($battlefield->factions as $faction) {
      $start_order = $faction->getStartOrder();
      $leader_position = current(array_slice($scheme_sides, $start_order - 1, 1));
      $start_scheme = array();
      $axis = NULL;
      foreach ($directions as $orientation => $direction) {
        $next_cell = $battlefield->findCell($leader_position['x'], $leader_position['y']);
        $continue = TRUE;
        while ($continue) {
          if ($next_cell->getType() == 'throne') {
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
        throw new BadGridException('Bad pieces start scheme.');
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
        $start_scheme[$piece_id] = array('x' => $starting_cell->getX(), 'y' => $starting_cell->getY());
      }
      $faction->createPieces($scheme->getPieceScheme(), $start_scheme);
    }
    $battlefield->logEvent('info', 'NEW_DJAMBI_GAME');
    $battlefield->setStatus($ready ? KW_DJAMBI_STATUS_PENDING : KW_DJAMBI_STATUS_RECRUITING);
    return $battlefield;
  }

  /**
   * Charge une grille de Djambi.
   *
   * @param GameManagerInterface $gm
   *   Objet GameManager lié
   * @param GameDisposition $disposition
   *   Disposition de jeu
   * @param array $data
   *   Tableau de données permettant de recréer la partie
   *
   * @throws Exceptions\FactionNotFoundException
   * @return Battlefield
   *   Grille de Djambi courante
   */
  public static function loadBattlefield(GameManagerInterface $gm, GameDisposition $disposition, $data) {
    $battlefield = new self($gm, $data['id'], $data['mode'], $disposition);
    $battlefield->setStatus($data['status']);
    $battlefield->moves = isset($data['moves']) ? $data['moves'] : $battlefield->moves;
    $battlefield->turns = isset($data['turns']) ? $data['turns'] : $battlefield->turns;
    $battlefield->events = isset($data['events']) ? $data['events'] : $battlefield->events;
    $battlefield->summary = isset($data['summary']) ? $data['summary'] : $battlefield->summary;
    $battlefield->factions = array();
    if (isset($data['options']) && is_array($data['options'])) {
      foreach ($data['options'] as $option => $value) {
        $battlefield->setOption($option, $value);
      }
    }
    $battlefield->buildField();
    $scheme = $battlefield->getDisposition()->getGrid();
    $pieces_scheme = $scheme->getPieceScheme();
    $sides_scheme = $scheme->getSides();
    foreach ($data['factions'] as $key => $faction_data) {
      $player = $id = $name = $class = $start_order = NULL;
      if (empty($faction_data['data'])) {
        $faction_data['data'] = array();
      }
      if (!is_null($faction_data['player'])) {
        $player = call_user_func_array($faction_data['player']['className'] . '::loadPlayer',
          array(array_merge($faction_data['player'], $faction_data['data'])));
      }
      if (!isset($faction_data['id'])) {
        foreach ($sides_scheme as $side_scheme) {
          if ($side_scheme['id'] == $key) {
            $id = $side_scheme['id'];
            $name = $side_scheme['name'];
            $start_order = $side_scheme['start_order'];
            $class = $side_scheme['class'];
          }
        }
      }
      else {
        $id = $faction_data['id'];
        $name = $faction_data['name'];
        $class = $faction_data['class'];
        $start_order = $faction_data['start_order'];
      }
      if (empty($id) || empty($name) || empty($class) || empty($start_order)) {
        throw new FactionNotFoundException("Cannot load faction.");
      }
      $faction = new Faction($battlefield, $id, $name, $class, $start_order, $faction_data, $player);
      $positions = array();
      foreach ($data['positions'] as $cell_name => $piece_id) {
        $cell = $battlefield->findCellByName($cell_name);
        $piece_data = explode('-', $piece_id, 2);
        if ($piece_data[0] == $key) {
          $positions[$piece_data[1]] = array(
            'x' => $cell->getX(),
            'y' => $cell->getY(),
          );
        }
      }
      $faction->setStatus($faction_data['status']);
      $faction->createPieces($pieces_scheme, $positions, $data['deads']);
      $battlefield->factions[] = $faction;
    }
    if (!empty($battlefield->summary)) {
      $battlefield->rebuildFactionsControls($battlefield->summary[max(array_keys($battlefield->summary))]);
    }
    return $battlefield;
  }

  /**
   * Génère les cellules d'une grille de Djambi.

   * @return Battlefield
   *   Grille de Djambi courante
   */
  protected function buildField() {
    $special_cells = $this->getDisposition()->getGrid()->getSpecialCells();
    for ($x = 1; $x <= $this->getCols(); $x++) {
      for ($y = 1; $y <= $this->getRows(); $y++) {
        Cell::createByXY($this, $x, $y);
      }
    }
    foreach ($special_cells as $description) {
      $cell = $this->findCell($description['location']['x'], $description['location']['y']);
      $cell->setType($description['type']);
    }
    foreach ($this->cells as $cell) {
      if ($cell->getType() == 'disabled') {
        continue;
      }
      foreach ($this->getDisposition()->getGrid()->getDirections() as $d => $direction) {
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
          if ($neighbour->getType() != 'disabled') {
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
   * Place une pièce sur la grille de Djambi.
   *
   * @param Piece $piece
   *   Pièce à placer
   * @param Cell $old_cell
   *   Ancienne cellule de la pièce
   *
   * @return Battlefield
   *   Grille de Djambi courante
   */
  public function placePiece(Piece $piece, Cell $old_cell = NULL) {
    $new_cell = $piece->getPosition();
    $new_cell->setOccupant($piece);
    if (!is_null($old_cell) && $new_cell->getName() != $old_cell->getName()) {
      $occupant = $old_cell->getOccupant();
      if ($occupant && $occupant->getId() == $piece->getId()) {
        $old_cell->emptyOccupant();
      }
    }
    return $this;
  }

  /**
   * Charge les options par défaut dans la grille de Djambi.

   * @return Battlefield
   *   Grille de Djambi courante
   */
  protected function setDefaultOptions() {
    foreach ($this->getDisposition()->getOptionsStore()->getAllGameOptions() as $object) {
      $this->setOption($object->getName(), $object->getDefault());
    }
    return $this;
  }

  /* -------------------------------------------------------
  ---------- RECUPERATION D'INFOS SUR LA PARTIE ------------
  -------------------------------------------------------- */

  /**
   * Renvoie les capacités des différentes pièces du jeu.
   *
   * @return array
   *   Liste des capacités
   */
  public function getHabilitiesStore() {
    return $this->habilitiesStore;
  }

  /**
   * Ajoute une capacité dans les caractéristiques des pièces de jeu.
   *
   * @param array $habilities
   *   Liste de capacités
   *
   * @return Battlefield
   *   Grille de Djambi courante
   */
  public function addHabilitiesInStore($habilities) {
    foreach ($habilities as $hability => $value) {
      if (!$this->isHabilityInStore($hability)) {
        $this->habilitiesStore[] = $hability;
      }
    }
    return $this;
  }

  /**
   * @param $hability
   *
   * @return bool
   */
  public function isHabilityInStore($hability) {
    return in_array($hability, $this->getHabilitiesStore());
  }

  public function getDisposition() {
    return $this->disposition;
  }

  public function getId() {
    return $this->id;
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
  public function getFactionById($id) {
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
    if (!$this->isPending()) {
      return NULL;
    }
    $play_order = current($this->getPlayOrder());
    return $this->getFactionById($play_order["side"]);
  }

  /**
   * @return bool
   */
  public function isPending() {
    if (in_array($this->getStatus(), array(KW_DJAMBI_STATUS_PENDING, KW_DJAMBI_STATUS_DRAW_PROPOSAL))) {
      return TRUE;
    }
    else {
      return FALSE;
    }
  }

  /**
   * @return bool
   */
  public function isFinished() {
    if ($this->getStatus() == KW_DJAMBI_STATUS_FINISHED) {
      return TRUE;
    }
    else {
      return FALSE;
    }
  }

  /**
   * @return bool
   */
  public function isNotBegin() {
    if ($this->getStatus() == KW_DJAMBI_STATUS_RECRUITING) {
      return TRUE;
    }
    else {
      return FALSE;
    }
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
  public function getPieceById($piece_id) {
    list($faction_id, $piece_description_id) = explode("-", $piece_id, 2);
    $faction = $this->getFactionById($faction_id);
    $pieces = $faction->getPieces();
    if (isset($pieces[$piece_description_id])) {
      return $pieces[$piece_description_id];
    }
    else {
      throw new PieceNotFoundException("Piece " . $piece_id . " not found.");
    }
  }

  public function getMoves() {
    return $this->moves;
  }

  public function getEvents() {
    return $this->events;
  }

  public function getRows() {
    return $this->getDisposition()->getGrid()->getRows();
  }

  public function getCols() {
    return $this->getDisposition()->getGrid()->getCols();
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
   * @throws Exceptions\CellNotFoundException
   * @return Cell
   *   Cellulue de Djambi
   */
  public function findCell($x, $y) {
    if (isset($this->cellsIndex[$x][$y])) {
      return $this->findCellByName($this->cellsIndex[$x][$y]);
    }
    else {
      throw new CellNotFoundException('X:' . $x . '-Y:' . $y);
    }
  }

  public function findCellByName($name) {
    if (isset($this->cells[$name])) {
      return $this->cells[$name];
    }
    else {
      throw new CellNotFoundException($name);
    }
  }

  /**
   * Réinitialise l'état "Reachable" des cellules de jeu.
   */
  public function resetCells() {
    foreach ($this->cells as $cell) {
      $cell->setReachable(FALSE);
    }
    return $this;
  }

  public function getStatus() {
    return $this->status;
  }

  public function getMode() {
    return $this->mode;
  }

  public function getDimensions() {
    return max($this->getRows(), $this->getCols());
  }

  public function getTurns() {
    return $this->turns;
  }

  public function getOption($option_key) {
    if (isset($this->options[$option_key])) {
      return $this->options[$option_key];
    }
    return NULL;
  }

  public function setOption($option_key, $value) {
    $this->options[$option_key] = $value;
    return $this;
  }

  /**
   * Renvoie le gestionnaire de jeu associé à cette grille.
   */
  public function getGameManager() {
    return $this->gameManager;
  }

  /* --------------------------------------------------------
  ---------- GESTION DES EVENEMENTS DE JEU ------------------
  ---------------------------------------------------------*/

  /**
   * @return Battlefield
   *   Grille de Djambi courante
   */
  public function cancelLastTurn() {
    $current_turn_key = $this->getCurrentTurnId();
    unset($this->turns[$current_turn_key]);
    $last_turn = end($this->turns);
    $last_turn_key = $this->getCurrentTurnId();
    $last_turn['end'] = NULL;
    $this->turns[$last_turn_key] = $last_turn;
    $this->viewTurnHistory($last_turn_key, TRUE);
    return $this;
  }

  /**
   * @param int $turn
   * @param bool $unset
   *
   * @return Battlefield
   *   Grille de Djambi courante
   */
  public function viewTurnHistory($turn, $unset = FALSE) {
    $inverted_moves = $this->moves;
    krsort($inverted_moves);
    foreach ($inverted_moves as $key => $move) {
      if ($move['turn'] >= $turn) {
        $piece = $this->getPieceById($move['target']);
        if (!$piece) {
          continue;
        }
        $position = $this->findCellByName($move['from']);
        $piece->setPosition($position);
        if ($move['type'] == 'murder' || $move['type'] == 'elimination') {
          $piece->setAlive(TRUE);
        }
        if ($unset) {
          unset($this->moves[$key]);
        }
      }
    }
    $delay = 0;
    foreach ($this->events as $key => $event) {
      if ($event['turn'] >= $turn) {
        $excluded_events = array('NEW_DJAMBI_GAME', 'NEW_TURN', 'TURN_BEGIN');
        if ($event['turn'] == $turn && $event['event'] == 'LEADER_KILLED') {
          $delay = 1;
        }
        if ($event['turn'] == $turn && in_array($event['event'], $excluded_events)) {
          continue;
        }
        if ($unset) {
          unset($this->events[$key]);
        }
      }
    }
    ksort($this->summary);
    foreach ($this->summary as $key => $data) {
      if ($key + $delay > $turn && $key != 1) {
        unset($this->summary[$key]);
      }
      else {
        $summary = $data;
      }
    }
    if (!empty($summary)) {
      $this->rebuildFactionsControls($summary);
    }
    if (!$unset) {
      $this->displayedTurnId = $turn;
    }
    return $this;
  }

  /**
   * @param $version
   * @param $show_moves
   * @param null $description_function
   *
   * @return array
   */
  public function returnLastMoveData($version, $show_moves, $description_function = NULL) {
    $moves = $this->getMoves();
    $new_moves = array();
    $changing_cells = array();
    if (!empty($moves)) {
      foreach ($moves as $move) {
        if ($move['time'] > $version) {
          $new_move = $this->returnMoveData($move, TRUE, $description_function, $changing_cells);
          if (!empty($new_move)) {
            $new_moves[] = $new_move;
          }
        }
      }
    }
    return array(
      'show_moves' => $show_moves,
      'changing_cells' => $changing_cells,
      'moves' => $new_moves,
    );
  }

  /**
   * @param $turn_id
   * @param $show_moves
   * @param null $description_function
   *
   * @return array
   */
  public function returnPastMoveData($turn_id, $show_moves, $description_function = NULL) {
    $moves = $this->getMoves();
    $animated_moves = array();
    $changing_cells = array();
    $past_moves = array();
    if (!empty($moves)) {
      $i = 0;
      foreach ($moves as $move) {
        if ($move['turn'] == $turn_id) {
          $past_move = $this->returnMoveData($move, $show_moves, $description_function, $changing_cells);
          if (!empty($past_move)) {
            $animated_moves['moves'][$i] = $move;
            $animated_moves['pieces'][$move['target']][] = $i;
            $past_moves[$i++] = $past_move;
          }
        }
      }
    }
    return array(
      'animations' => $animated_moves,
      'changing_cells' => $changing_cells,
      'moves' => $past_moves,
    );
  }

  /**
   * @param $showable_turns
   * @param null $description_function
   *
   * @return array
   */
  public function returnShowableMoveData($showable_turns, $description_function = NULL) {
    $moves = $this->getMoves();
    $last_moves = array();
    $changing_cells = array();
    foreach ($moves as $move) {
      if (in_array($move['turn'], $showable_turns)) {
        $last_move = $this->returnMoveData($move, TRUE, $description_function, $changing_cells);
        if (!empty($last_move)) {
          $last_moves[] = $last_move;
        }
      }
    }
    return array('changing_cells' => $changing_cells, 'moves' => $last_moves);
  }

  /**
   * @param $move
   * @param $show_moves
   * @param $description_function
   * @param $changing_cells
   *
   * @return array
   */
  protected function returnMoveData($move, $show_moves, $description_function, &$changing_cells) {
    $new_move = array();
    if ($move['type'] == 'move' || !isset($move['acting_faction'])) {
      $faction_id = $move['target_faction'];
    }
    else {
      $faction_id = $move['acting_faction'];
    }
    $acting_faction = $this->getFactionById($faction_id);
    if ($acting_faction) {
      $changing_cells[$move['from']] = $acting_faction->getClass();
      $changing_cells[$move['to']] = $acting_faction->getClass();
      $new_move = array(
        'location' => $move['to'],
        'origin' => $move['from'],
        'order' => $move['turn'] + 1,
        'faction' => $acting_faction->getId(),
        'faction_class' => $acting_faction->getClass(),
        'animation' => $move['type'] . ':' . $move['to'],
        'hidden' => !$show_moves,
      );
      if (!is_null($description_function) && function_exists($description_function)) {
        $new_move['description'] = call_user_func_array($description_function, array($move, $this));
      }
    }
    return $new_move;
  }

  /**
   * @param $summary
   *
   * @return Battlefield
   *   Grille de Djambi courante
   */
  protected function rebuildFactionsControls($summary) {
    foreach ($summary['factions'] as $faction_key => $data) {
      $faction = $this->getFactionById($faction_key);
      $faction->setStatus($data['status']);
      $faction->setControl($this->getFactionById($data['control']), FALSE);
      $faction->setMaster(isset($data['master']) ? $data['master'] : NULL);
    }
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
      $winner = $this->getFactionById($winner_id);
      $winner->setStatus(KW_DJAMBI_FACTION_STATUS_WINNER);
      $winner->setRanking(1);
      $this->logEvent('event', 'THE_WINNER_IS', array('faction1' => $winner->getId()));
    }
    else {
      $this->logEvent("event", "DRAW");
      foreach ($living_factions as $faction_id) {
        $faction = $this->getFactionById($faction_id);
        $faction->setStatus(KW_DJAMBI_FACTION_STATUS_DRAW);
        $faction->setRanking($nb_living_factions);
      }
    }
    $this->setStatus(KW_DJAMBI_STATUS_FINISHED);
    $this->updateSummary();
    $this->buildFinalRanking($nb_living_factions);
    $this->logEvent("event", "END");
    return $this;
  }

  /**
   * @return Battlefield
   *   Grille de Djambi courante
   */
  public function changeTurn() {
    $changes = FALSE;
    // Log de la fin du tour :
    $last_turn_key = $this->getCurrentTurnId();
    $this->turns[$last_turn_key]["end"] = time();
    // Vérification des conditions de victoire :
    $living_factions = array();
    /* @var $faction Faction */
    foreach ($this->getFactions() as $faction) {
      if ($faction->isAlive()) {
        $control_leader = $faction->checkLeaderFreedom();
        if (!$control_leader) {
          $this->logEvent("event", "SURROUNDED", array('faction1' => $faction->getId()));
          if ($this->getOption('rule_comeback') == 'never' && $this->getOption('rule_vassalization') == 'full_control') {
            foreach ($faction->getPieces() as $piece) {
              if ($piece->isAlive() && $piece->getDescription()->hasHabilityMustLive() && $piece->getFaction()->getId() == $faction->getId()) {
                $this->logMove($piece, $piece->getPosition(), 'elimination');
                $piece->setAlive(FALSE);
              }
            }
          }
          $faction->dieDieDie(KW_DJAMBI_FACTION_STATUS_SURROUNDED);
          $changes = TRUE;
        }
        else {
          $living_factions[] = $faction->getId();
        }
      }
      elseif ($this->getOption('rule_comeback') == 'surrounded' ||
          ($this->getOption('rule_comeback') == 'allowed' && empty($kings))) {
        if ($faction->getStatus() == KW_DJAMBI_FACTION_STATUS_SURROUNDED) {
          $control_leader = $faction->checkLeaderFreedom();
          if ($control_leader) {
            $faction->setStatus(KW_DJAMBI_FACTION_STATUS_READY);
            $this->logEvent("event", "COMEBACK_AFTER_SURROUND", array('faction1' => $faction->getId()));
            $changes = TRUE;
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
      $kings = $this->findKings();
      if (!empty($kings)) {
        foreach ($this->getFactions() as $faction) {
          if (!$faction->getControl()->isAlive()) {
            if (count($kings) == 1) {
              // Cas d'un abandon :
              // lors de la prise de pouvoir, retrait de l'ancien chef.
              $pieces = $faction->getPieces();
              foreach ($pieces as $piece) {
                if ($this->getOption('rule_vassalization') == 'full_control'
                  && $piece->isAlive() && $piece->getDescription()->hasHabilityMustLive()) {
                  $piece->setAlive(FALSE);
                  $this->logMove($piece, $piece->getPosition(), 'elimination');
                }
              }
              // Prise de contrôle
              $faction->setControl($this->getFactionById(current($kings)));
              $changes = TRUE;
            }
          }
        }
      }
      elseif ($this->getOption('rule_vassalization') != 'full_control') {
        foreach ($this->getFactions() as $faction) {
          if (!$faction->isAlive()) {
            $allowed_statuses = array(
              KW_DJAMBI_FACTION_STATUS_DEFECT,
              KW_DJAMBI_FACTION_STATUS_WITHDRAW,
              KW_DJAMBI_FACTION_STATUS_SURROUNDED,
            );
            if (in_array($faction->getStatus(), $allowed_statuses) && $faction->getControl()->getId() != $faction->getId()) {
              $faction->setControl($faction);
              $changes = TRUE;
            }
          }
        }
      }
      $this->definePlayOrder();
      if ($changes) {
        $this->updateSummary();
      }
    }
    return $this;
  }

  /**
   * @return array
   */
  protected function findKings() {
    $kings = array();
    $thrones = $this->getSpecialCells("throne");
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
    return array_unique($kings);
  }

  /**
   * @param bool $reset
   *
   * @return mixed
   */
  public function getPlayOrder($reset = FALSE) {
    if (empty($this->playOrder) || $reset) {
      $this->definePlayOrder();
    }
    reset($this->playOrder);
    return $this->playOrder;
  }

  public function setStatus($status) {
    $this->status = $status;
    return $this;
  }

  /**
   * @return bool
   */
  protected function definePlayOrder() {
    $this->playOrder = array();
    $orders = array();
    $selected_faction = NULL;
    $nb_factions = 0;
    foreach ($this->factions as $faction) {
      $orders["orders"][] = $faction->getStartOrder();
      $orders["factions"][] = $faction->getId();
      $orders["alive"][] = $faction->isAlive();
      if ($faction->isAlive()) {
        $nb_factions++;
      }
    }
    $total_factions = count($orders["factions"]);
    $thrones = $this->getSpecialCells("throne");
    $turn_scheme = array();
    for ($i = 0; $i < $total_factions; $i++) {
      $turn_scheme[] = array(
        "side" => $i,
        "type" => "std",
        "played" => FALSE,
        "playable" => TRUE,
        "alive" => TRUE,
      );
      foreach ($thrones as $throne) {
        $turn_scheme[] = array(
          "side" => NULL,
          "type" => "throne",
          "case" => $throne,
          "played" => FALSE,
          "playable" => TRUE,
          "alive" => TRUE,
        );
      }
    }
    array_multisort($orders["orders"], $orders["factions"], $orders["alive"]);
    foreach ($orders["factions"] as $order => $faction_key) {
      foreach ($turn_scheme as $key => $turn) {
        if ($turn["side"] == $order) {
          $turn_scheme[$key]["side"] = $faction_key;
          $turn_scheme[$key]["alive"] = $orders["alive"][$order];
          foreach ($thrones as $tk => $case) {
            $turn_scheme[$key + $tk + 1]["alive"] = $orders["alive"][$order];
          }
          break;
        }
      }
    }
    $rulers = array();
    if (!empty($thrones) && $this->getStatus() == KW_DJAMBI_STATUS_PENDING) {
      foreach ($thrones as $throne) {
        $cell = $this->cells[$throne];
        $piece = $cell->getOccupant();
        if (!empty($piece)) {
          if ($piece->getDescription()->hasHabilityAccessThrone() && $piece->isAlive()) {
            foreach ($turn_scheme as $key => $turn) {
              if ($turn["type"] == "throne" && $turn["case"] == $throne) {
                if ($piece->getFaction()->getControl()->getId() == $piece->getFaction()->getId()) {
                  $turn_scheme[$key]["side"] = $piece->getFaction()->getControl()->getId();
                }
                $rulers[] = $piece->getFaction()->getControl()->getId();
              }
            }
          }
        }
      }
      $prev_side = NULL;
      $last_playable_turn_scheme = NULL;
      $last_playable_prev_side = NULL;
      foreach ($turn_scheme as $key => $turn) {
        if ($turn["side"] == $prev_side) {
          $turn_scheme[$key]["playable"] = FALSE;
        }
        if ($turn["side"] != NULL && $turn["alive"] && $turn["type"] == "std") {
          $prev_side = (!$turn_scheme[$key]["playable"] && $nb_factions == 2) ? NULL : $turn["side"];
        }
        elseif ($turn["type"] != "std" && $turn["side"] != NULL) {
          $prev_side = $turn["side"];
        }
        if ($turn["side"] && $turn["alive"] && $turn_scheme[$key]["playable"]) {
          $last_playable_turn_scheme = $key;
          $last_playable_prev_side = $prev_side;
        }
      }
      if ($nb_factions > 2 && $turn_scheme[0]["side"] == $last_playable_prev_side) {
        $turn_scheme[$last_playable_turn_scheme]["playable"] = FALSE;
      }
    }
    elseif ($this->getStatus() == KW_DJAMBI_STATUS_DRAW_PROPOSAL) {
      foreach ($turn_scheme as $key => $turn) {
        if (!empty($turn['side']) && $turn['playable']) {
          $side = $this->getFactionById($turn['side']);
          if (!is_null($side->getDrawStatus())) {
            $turn_scheme[$key]['playable'] = FALSE;
          }
        }
      }
    }
    $max_ts = max(array_keys($turn_scheme));
    $new_turn = FALSE;
    $new_phase = TRUE;
    if (!empty($this->turns)) {
      $last_turn = end($this->turns);
      $current_scheme_key = $last_turn["turn_scheme"];
      $current_phase = $last_turn["turn"];
      if (!empty($last_turn["end"])) {
        $new_turn = TRUE;
        $current_scheme_key++;
      }
      else {
        $new_phase = FALSE;
      }
      foreach ($turn_scheme as $key => $turn) {
        if ($current_scheme_key > $key) {
          $turn_scheme[$key]["played"] = TRUE;
        }
        elseif ($turn["playable"] && $turn["alive"] && $turn["side"] != NULL) {
          $new_phase = FALSE;
        }
      }
      if ($new_phase) {
        $current_phase = $last_turn["turn"] + 1;
        foreach ($turn_scheme as $key => $turn) {
          if ($key == 0 && $turn["side"] == $last_turn["side"] && !in_array($turn["side"], $rulers)) {
            $turn_scheme[$key]["played"] = TRUE;
          }
          else {
            $turn_scheme[$key]["played"] = FALSE;
          }
        }
      }
    }
    else {
      $new_turn = TRUE;
      $current_phase = 1;
    }
    foreach ($turn_scheme as $key => $turn) {
      if ($turn["playable"] && $turn["alive"] && !$turn["played"] && $turn["side"] != NULL) {
        $this->playOrder[] = array(
          "side" => $turn["side"],
          "turn_scheme" => $key,
        );
      }
    }
    if (empty($this->playOrder)) {
      return FALSE;
    }
    $current_order = current($this->playOrder);
    $corrections = FALSE;
    // Un camp ne peut pas jouer 2x de suite après avoir tué un chef ennemi :
    if ($new_turn && $nb_factions > 2 && !empty($this->turns) && $this->turns[$this->getCurrentTurnId()]['side'] == $current_order['side']) {
      unset($this->playOrder[key($this->playOrder)]);
      $corrections = TRUE;
    }
    // Un camp ne peut pas jouer immédiatement après avoir accédé au pouvoir
    // ou s'être retiré du pouvoir :
    elseif ($new_turn && $nb_factions == 2) {
      $last_turn_id = $this->getCurrentTurnId();
      foreach ($this->moves as $move) {
        if ($move['turn'] == $last_turn_id && !empty($move['special_event'])
            && in_array($move['special_event'], array('THRONE_ACCESS', 'THRONE_RETREAT'))
            && $this->turns[$last_turn_id]['side'] == $current_order['side']) {
          unset($this->playOrder[key($this->playOrder)]);
          $corrections = TRUE;
          break;
        }
      }
    }
    if ($corrections && empty($this->playOrder)) {
      $current_phase++;
      $new_phase = TRUE;
    }
    $displayed_next_turns = 4;
    if (count($this->playOrder) < $displayed_next_turns) {
      $i = 0;
      while (count($this->playOrder) < $displayed_next_turns) {
        if ($i > $max_ts) {
          $i = 0;
        }
        if (isset($turn_scheme[$i]) && $turn_scheme[$i]["alive"] && $turn_scheme[$i]["side"] != NULL && $turn_scheme[$i]["playable"]) {
          $this->playOrder[] = array(
            "side" => $turn_scheme[$i]["side"],
            "turn_scheme" => $i,
          );
        }
        $i++;
      }
    }
    $current_order = current($this->playOrder);
    $selected_faction = $this->getFactionById($current_order["side"]);
    $selected_faction->setPlaying(TRUE);
    $begin = !empty($last_turn) ? $last_turn['end'] + 1 : time();
    if ($new_turn) {
      $this->turns[] = array(
        "begin" => $begin,
        "end" => NULL,
        "side" => $current_order["side"],
        "turn_scheme" => $current_order["turn_scheme"],
        "turn" => $current_phase,
      );
      $this->logEvent("notice", "TURN_BEGIN", array('faction1' => $selected_faction->getId()), $begin);
    }
    if ($new_phase) {
      if ($current_phase == 1) {
        $this->logEvent("event", "GAME_START");
      }
      $this->logEvent("notice", "NEW_TURN", array("!turn" => $current_phase), $begin);
    }
    return TRUE;
  }

  public function defineMovablePieces() {
    /* @var $active_faction Faction */
    $current_order = current($this->playOrder);
    $active_faction = $this->getFactionById($current_order["side"]);
    /* @var $piece Piece */
    $can_move = FALSE;
    foreach ($active_faction->getControlledPieces() as $piece) {
      $moves = $piece->buildAllowableMoves();
      if ($moves > 0) {
        $can_move = TRUE;
      }
    }
    if (!$can_move && $active_faction->getSkippedTurns() == $this->getOption('allowed_skipped_turns_per_user')) {
      $active_faction->withdraw();
      $this->changeTurn();
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
      if ($faction->isAlive()) {
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
      $direction = $this->getDisposition()->getGrid()->getDirection($direction_key);
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
   * @param Cell $force_free_cell
   *   Cellule libre même si déjà occupée
   *
   * @return array
   *   Liste de cases libres
   */
  public function getFreeCells(Piece $piece, $keep_alive = TRUE, $murder = FALSE, Cell $force_free_cell = NULL) {
    $freecells = array();
    foreach ($this->cells as $key => $cell) {
      $occupant = $cell->getOccupant();
      if (empty($occupant) || (!is_null($force_free_cell) && $force_free_cell->getName() == $key)) {
        if ($cell->getType() == 'throne') {
          // Un leader peut être manipulé au pouvoir dans tous les cas :
          if ($piece->getDescription()->hasHabilityAccessThrone() && $piece->isAlive() && $keep_alive) {
            $freecells[] = $key;
          }
          // Un leader mort peut être placé au pouvoir si variante activée :
          elseif ($this->getOption('rule_throne_interactions') == 'extended' && $murder
            && $piece->getDescription()->hasHabilityAccessThrone()) {
            $freecells[] = $key;
          }
        }
        else {
          $freecells[] = $key;
        }
      }
    }
    return $freecells;
  }

  public function prepareNewTurn() {
    $summary = $this->getSummary();
    if (empty($summary)) {
      $this->prepareSummary();
    }
    $this->getPlayOrder(TRUE);
    $this->defineMovablePieces();
  }

  public function getSummary() {
    return $this->summary;
  }

  public function prepareSummary() {
    $vassals = array();
    $players = array();
    foreach ($this->factions as $faction) {
      if ($faction->getStatus() == KW_DJAMBI_FACTION_STATUS_VASSALIZED) {
        $vassals[] = $faction->getId();
      }
      else {
        $players[] = $faction;
      }
    }
    if (!empty($vassals) && !empty($players) && count($vassals) == count($players)) {
      foreach ($vassals as $id) {
        $control = current($players);
        $this->getFactionById($id)->setControl($control, TRUE);
        next($players);
      }
    }
    $this->updateSummary();
    return $this;
  }

  public function updateSummary() {
    $infos = array();
    foreach ($this->getFactions() as $faction) {
      $faction_info = array(
        'control' => $faction->getControl()->getId(),
        'status' => $faction->getStatus(),
      );
      if (!is_null($faction->getMaster())) {
        $faction_info['master'] = $faction->getMaster();
      }
      $infos['factions'][$faction->getId()] = $faction_info;
    }
    foreach ($this->events as $event) {
      if ($event['event'] == 'GAME_OVER') {
        $faction = $this->getFactionById($event['args']['faction1']);
        if (!$faction->isAlive()) {
          $infos['eliminations'][$faction->getId()] = $event['turn'];
        }
      }
    }
    if (isset($event['turn'])) {
      $this->summary[$event['turn']] = $infos;
    }
    return $this;
  }

  protected function buildFinalRanking($begin) {
    $last_summary = $this->summary[max(array_keys($this->summary))];
    arsort($last_summary['eliminations']);
    $last_turn = NULL;
    $i = 0;
    $rank = 0;
    foreach ($last_summary['eliminations'] as $faction_key => $turn) {
      $i++;
      if ($last_turn != $turn) {
        $rank = $begin + $i;
      }
      $this->getFactionById($faction_key)->setRanking($rank);
      $last_turn = $turn;
    }
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
   */
  public function logEvent($type, $event_txt, $event_args = NULL, $time = NULL) {
    $event = array(
      "turn" => $this->getCurrentTurnId(),
      "time" => is_null($time) ? time() : $time,
      "type" => $type,
      "event" => $event_txt,
      "args" => $event_args,
    );
    $this->events[] = $event;
    return $this;
  }

  /**
   * @return int
   */
  public function getCurrentTurnId() {
    return empty($this->turns) ? 0 : max(array_keys($this->turns));
  }

  /**
   * @return int
   */
  public function getDisplayedTurnId() {
    if (!is_null($this->displayedTurnId)) {
      return $this->displayedTurnId;
    }
    else {
      return $this->getCurrentTurnId() + 1;
    }
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
   */
  public function logMove(Piece $target_piece, Cell $destination_cell, $type = "move", Piece $acting_piece = NULL) {
    $origin_cell_object = $target_piece->getPosition();
    if ($destination_cell->getType() == 'throne' && $target_piece->getDescription()->hasHabilityAccessThrone() && $target_piece->isAlive()) {
      $special_event = 'THRONE_ACCESS';
    }
    elseif ($destination_cell->getType() == 'throne' && $target_piece->getDescription()->hasHabilityAccessThrone() && !$target_piece->isAlive()) {
      $special_event = 'THRONE_MAUSOLEUM';
    }
    elseif ($origin_cell_object->getType() == 'throne' && $target_piece->getDescription()->hasHabilityAccessThrone() && $target_piece->isAlive()) {
      $special_event = 'THRONE_RETREAT';
    }
    elseif ($origin_cell_object->getType() == 'throne' && $target_piece->getDescription()->hasHabilityAccessThrone() && !$target_piece->isAlive()) {
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
      'turn' => $this->getCurrentTurnId(),
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
    $this->moves[] = $move;
    return $this;
  }

  // ----------------------------------------------------------
  // ---------- ENREGISTREMENT EN BASE DE DONNEES -------------
  // ----------------------------------------------------------

  /**
   * @return array
   */
  public function toArray() {
    $positions = array();
    $factions = array();
    $deads = array();
    foreach ($this->cells as $key => $cell) {
      $piece = $cell->getOccupant();
      if (!empty($piece)) {
        $positions[$key] = $piece->getId();
        if (!$piece->isAlive()) {
          $deads[] = $piece->getId();
        }
      }
    }
    foreach ($this->factions as $faction) {
      $factions[$faction->getId()] = $faction->toArray();
    }
    $return = array(
      'id' => $this->id,
      'positions' => $positions,
      'factions' => $factions,
      'moves' => $this->moves,
      'turns' => isset($this->turns) ? $this->turns : array(),
      'points' => isset($this->points) ? $this->points : 0,
      'deads' => $deads,
      'events' => $this->events,
      'options' => $this->options,
      'summary' => $this->summary,
      'mode' => $this->getMode(),
      'status' => $this->getStatus(),
      'disposition' => $this->getDisposition()->getName(),
      'scheme_settings' => $this->getDisposition()->getGrid()->getSettings(),
    );
    return $return;
  }

}
