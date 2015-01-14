<?php
/**
 * @file
 * Introduic une classe DjambiBattlefield permettant de construire et de gérér
 * un plateau de jeu.
 */

namespace Djambi\Gameplay;

use Djambi\Enums\StatusEnum;
use Djambi\GameManagers\Exceptions\GameNotFoundException;
use Djambi\Gameplay\Exceptions\CellNotFoundException;
use Djambi\Gameplay\Exceptions\FactionNotFoundException;
use Djambi\Gameplay\Exceptions\PieceNotFoundException;
use Djambi\GameManagers\PlayableGameInterface;
use Djambi\GameOptions\StandardRuleset;
use Djambi\Persistance\ArrayableInterface;
use Djambi\Persistance\PersistantDjambiTrait;
use Djambi\PieceDescriptions\Habilities\HabilityAccessThrone;
use Djambi\PieceDescriptions\Habilities\RestrictionMustLive;
use Djambi\Strings\Glossary;
use Djambi\Strings\GlossaryTerm;

/**
 * Class DjambiBattlefield
 */
class Battlefield implements BattlefieldInterface, ArrayableInterface {

  use PersistantDjambiTrait;

  /* @var PlayableGameInterface */
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
   * Préparation à l'enregistrement : transformation en tableau.
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
    return $this;
  }

  /**
   * Charge une grille de Djambi.
   *
   * @param array $array
   * @param array $context
   *
   * @throws CellNotFoundException
   * @throws \Djambi\Gameplay\Exceptions\FactionNotFoundException
   * @throws \Djambi\GameManagers\Exceptions\GameNotFoundException
   * @return Battlefield
   */
  public static function fromArray(array $array, array $context = array()) {
    if (empty($context['gameManager'])) {
      throw new GameNotFoundException("Cannot load a battlefield without a game manager context !");
    }
    /** @var PlayableGameInterface $game */
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
   * @param PlayableGameInterface $game
   *   Objet de gestion du jeu
   *
   * @return Battlefield
   *   Nouvel objet plateau de jeu
   */
  public function __construct(PlayableGameInterface $game) {
    $this->gameManager = $game;
    $this->buildField();
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
        } catch (CellNotFoundException $e) {
          continue;
        }
      }

    }
    return $this;
  }

  public function addFaction(Faction $faction) {
    $this->factions[$faction->getId()] = $faction;
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
    list($faction_id) = explode("-", $piece_id, 2);
    $faction = $this->findFactionById($faction_id);
    $pieces = $faction->getPieces();
    if (isset($pieces[$piece_id])) {
      return $pieces[$piece_id];
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
   *
   * @param Cell $cell
   *
   * @return $this
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
   * @throws \Djambi\Gameplay\Exceptions\CellNotFoundException
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
    $this->getCurrentTurn()->resetMove();
    $last_turn_array = array_pop($this->pastTurns);
    $context['battlefield'] = $this;
    unset($last_turn_array['end']);
    /** @var Turn $last_turn */
    $last_turn = call_user_func($last_turn_array['className'] . '::fromArray', $last_turn_array, $context);
    $last_turn->cancelCompletedTurn();
    $this->currentTurn = NULL;
    $this->playOrder = NULL;
    $this->getGameManager()->propagateChanges();
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
      $this->currentTurn->logEvent(new Event(new GlossaryTerm(Glossary::EVENT_WINNER,
        array('!faction_id' => $winner->getId())), Event::LOG_LEVEL_MAJOR));
    }
    else {
      $this->currentTurn->logEvent(new Event(new GlossaryTerm(Glossary::EVENT_DRAW), Event::LOG_LEVEL_MAJOR));
      foreach ($living_factions as $faction_id) {
        $faction = $this->findFactionById($faction_id);
        $faction->setStatus(Faction::STATUS_DRAW)
          ->setRanking($nb_living_factions);
      }
    }
    $this->getGameManager()->setStatus(StatusEnum::STATUS_FINISHED);
    $this->currentTurn->logEvent(new Event(new GlossaryTerm(Glossary::EVENT_THIS_IS_THE_END)));
    $this->pastTurns[] = $this->getCurrentTurn()->endsTurn()->toArray();
    $this->buildFinalRanking($nb_living_factions);
    $this->currentTurn = NULL;
    $this->playOrder = NULL;
    $this->getGameManager()->propagateChanges();
    return $this;
  }

  public function changeTurn() {
    // Vérification des conditions de victoire :
    $this->findRuler();
    $living_factions = $this->findLivingFactions();
    $total = count($living_factions);
    if ($total < 2) {
      $this->endGame($living_factions);
    }
    else {
      // Attribution des pièces vivantes à l'occupant du trône :
      $this->vassalizeFactions();
      // Préparation du nouveau tour
      $this->pastTurns[] = $this->getCurrentTurn()->endsTurn()->toArray();
      $this->currentTurn = NULL;
      $this->playOrder = NULL;
      $this->getGameManager()->propagateChanges();
      $this->prepareTurn();
    }
    return $this;
  }

  /**
   * @return array
   */
  protected function findLivingFactions() {
    $living_factions = array();
    foreach ($this->getFactions() as $faction) {
      if ($faction->isAlive()) {
        $control_leader = $faction->checkLeaderFreedom();
        if (!$control_leader) {
          $event = NULL;
          foreach ($faction->getPieces() as $piece) {
            if ($piece->isAlive() && $piece->getDescription() instanceof RestrictionMustLive
              && $piece->getFaction()->getId() == $faction->getId()
            ) {
              $event = new Event(new GlossaryTerm(Glossary::EVENT_SURROUNDED, array('!piece_id' => $piece->getId())), Event::LOG_LEVEL_MAJOR);
              if ($this->getGameManager()
                  ->getOption(StandardRuleset::RULE_COMEBACK) == 'never'
                && $this->getGameManager()
                  ->getOption(StandardRuleset::RULE_VASSALIZATION) == 'full_control'
              ) {
                $event->executeChange(new PieceChange($piece, 'alive', TRUE, FALSE));
              }
              $this->getCurrentTurn()->logEvent($event);
            }
          }
          if (!empty($event)) {
            $faction->dieDieDie(Faction::STATUS_SURROUNDED);
          }
        }
        else {
          $living_factions[] = $faction->getId();
        }
      }
      elseif ($this->getGameManager()
          ->getOption(StandardRuleset::RULE_COMEBACK) == 'surrounded'
        || ($this->getGameManager()
            ->getOption(StandardRuleset::RULE_COMEBACK) == 'allowed' && empty($this->getRuler()))
      ) {
        if ($faction->getStatus() == Faction::STATUS_SURROUNDED) {
          $control_leader = $faction->checkLeaderFreedom();
          if ($control_leader) {
            $change = new FactionChange($faction, 'status', Faction::STATUS_SURROUNDED, Faction::STATUS_READY);
            $event = new Event(new GlossaryTerm(Glossary::EVENT_COMEBACK_AFTER_SURROUND,
              array('!faction_id' => $faction->getId())), Event::LOG_LEVEL_MAJOR);
            $event->executeChange($change);
            $this->getCurrentTurn()->logEvent($event);
          }
        }
      }
    }
    return $living_factions;
  }

  protected function vassalizeFactions() {
    if (!empty($this->getRuler())) {
      foreach ($this->getFactions() as $faction) {
        if (!$faction->getControl()->isAlive()) {
          // Cas d'un abandon :
          // lors de la prise de pouvoir, retrait de l'ancien chef.
          $pieces = $faction->getPieces();
          foreach ($pieces as $piece) {
            if ($this->getGameManager()
                ->getOption(StandardRuleset::RULE_VASSALIZATION) == 'full_control'
              && $piece->isAlive() && $piece->getDescription() instanceof RestrictionMustLive
            ) {
              $event = new Event(new GlossaryTerm(Glossary::EVENT_ELIMINATION, array('!piece_id' => $piece)));
              $event->executeChange(new PieceChange($piece, 'alive', TRUE, FALSE));
              $this->getCurrentTurn()->logEvent($event);
            }
          }
          // Prise de contrôle
          $faction->setControl($this->findFactionById($this->getRuler()));
        }
      }
    }
    elseif ($this->getGameManager()
        ->getOption(StandardRuleset::RULE_VASSALIZATION) != 'full_control'
    ) {
      foreach ($this->getFactions() as $faction) {
        if (!$faction->isAlive()) {
          $allowed_statuses = array(
            Faction::STATUS_DEFECT,
            Faction::STATUS_WITHDRAW,
            Faction::STATUS_SURROUNDED,
          );
          if (in_array($faction->getStatus(), $allowed_statuses) && !$faction->isSelfControlled()) {
            $faction->setControl($faction);
          }
        }
      }
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

  public function prepareTurn($reset = FALSE) {
    if ($reset || is_null($this->getPlayOrder())) {
      $this->definePlayOrder();
      $this->defineMovablePieces();
    }
    return $this;
  }

  protected function definePlayOrder() {
    // Réinitialisation
    $this->playOrder = array();
    // Récupération des factions, de leur statut et de leurs ordres de départ
    // Constitution d'un schéma par défaut des tours de jeu possibles
    $nb_factions = 0;
    $scheme_size = count($this->factions) * 2;
    $turn_scheme = array();
    $events = array();
    $peace_negociation = $this->getGameManager()->getStatus() == StatusEnum::STATUS_DRAW_PROPOSAL;
    foreach ($this->factions as $faction) {
      if ($faction->isAlive() && $faction->isSelfControlled()) {
        $nb_factions++;
      }
      $turn_scheme[$faction->getStartOrder() * 2 - 1] = array(
        "side" => $faction->getId(),
        "type" => Cell::TYPE_STANDARD,
        "played" => FALSE,
        "playable" => $faction->isSelfControlled() &&
          (!$peace_negociation || $peace_negociation && $faction->getDrawStatus() == Faction::DRAW_STATUS_UNDECIDED),
        "alive" => $faction->isAlive(),
        "new_round" => FALSE,
      );
      $turn_scheme[$faction->getStartOrder() * 2] = array(
        "side" => NULL,
        "type" => Cell::TYPE_THRONE,
        "played" => FALSE,
        "playable" => $faction->isSelfControlled(),
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
    $this->findRuler();
    if (!empty($this->ruler)) {
      $ruler_faction = $this->findFactionById($this->ruler);
      $decided = $peace_negociation && $ruler_faction->getDrawStatus() != Faction::DRAW_STATUS_UNDECIDED;
      if ($nb_factions > 2) {
        $ruler_key = $ruler_faction->getStartOrder() * 2 - 2;
        while (isset($turn_scheme[$ruler_key])) {
          if ($turn_scheme[$ruler_key]['alive']) {
            $turn_scheme[$ruler_key]['exclude'][] = $this->ruler;
            break;
          }
          $ruler_key = $ruler_key - 2;
        }
        $ruler_key = $ruler_faction->getStartOrder() * 2 - 2 + $scheme_size;
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
          if ($decided || in_array($this->ruler, $scheme['exclude'])) {
            $turn_scheme[$key]['playable'] = FALSE;
          }
          elseif ($peace_negociation) {
            break;
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
        if (!$peace_negociation && is_null($first_element) && !empty($last_turn) && $last_turn['actingFaction'] == $scheme['side']) {
          // Corrections de cas non-standards
          if ($nb_factions > 2) {
            // Un camp ne peut pas jouer 2x de suite
            // après avoir tué un chef ennemi
            continue;
          }
          elseif ((isset($last_turn['move']['destination']) && $this->findCellByName($last_turn['move']['destination'])
                ->getType() == Cell::TYPE_THRONE)
            || (isset($last_turn['move']['origin']) && $this->findCellByName($last_turn['move']['origin'])
                ->getType() == Cell::TYPE_THRONE)
          ) {
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
    if ($nb_factions > 2 && !$peace_negociation) {
      $last = end($this->playOrder);
      $first = reset($this->playOrder);
      if ($first == $last) {
        array_pop($this->playOrder);
      }
    }
    // Détermination de la faction en tour de jeu
    $this->findFactionById(current($this->playOrder))->setPlaying(TRUE);
    // Création d'un nouveau tour
    $new_play_order_key = key($this->playOrder);
    if ($turn_scheme[$new_play_order_key]['new_round']) {
      $current_round++;
      $new_play_order_key = $new_play_order_key - $scheme_size;
      $events[] = new Event(new GlossaryTerm(Glossary::EVENT_NEW_ROUND, array(
        '!round' => $current_round,
      )), Event::LOG_LEVEL_MINOR);
    }
    $this->setCurrentTurn(Turn::begin($this, $current_round, $new_play_order_key));
    if (!empty($events)) {
      foreach ($events as $event) {
        $this->getCurrentTurn()->logEvent($event);
      }
    }
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
    if (!$can_move && $this->getPlayingFaction()
        ->getSkippedTurns() == $this->getGameManager()
        ->getOption(StandardRuleset::GAMEPLAY_ELEMENT_SKIPPED_TURNS)
    ) {
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
      if ($faction->isAlive() && $faction->isSelfControlled()) {
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
      $direction = $this->getGameManager()
        ->getDisposition()
        ->getGrid()
        ->getDirection($direction_key);
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
          if ($piece->getDescription() instanceof HabilityAccessThrone && $piece->isAlive() && $keep_alive) {
            $freecells[$key] = $cell;
          }
          // Un leader mort peut être placé au pouvoir si variante activée :
          elseif ($this->getGameManager()
              ->getOption(StandardRuleset::RULE_EXTRA_INTERACTIONS) == 'extended' && $murder
            && $piece->getDescription() instanceof HabilityAccessThrone
          ) {
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

  protected function buildFinalRanking($begin) {
    $past_turns = array_reverse($this->getPastTurns());
    $eliminations = array();
    foreach ($past_turns as $turn) {
      if (!empty($turn['events'])) {
        foreach ($turn['events'] as $event) {
          if (!empty($event['changes'])) {
            foreach ($event['changes'] as $change) {
              if ($change['className'] == 'Djambi\\Gameplay\\FactionChange' && $change['change'] == 'alive'
                && $change['newValue'] === FALSE && !isset($eliminations[$change['object']])
              ) {
                $eliminations[$change['object']] = $turn['id'];
              }
            }
          }
        }
      }
    }
    $last_ranking = $begin + 1;
    $last_turn_id = NULL;
    $same_rank = 1;
    foreach ($eliminations as $faction_id => $turn_id) {
      if ($turn_id == $last_turn_id) {
        $ranking = $last_ranking - ($same_rank++);
      }
      else {
        $ranking = $last_ranking;
        $same_rank = 1;
      }
      $last_ranking++;
      $last_turn_id = $turn_id;
      $this->findFactionById($faction_id)->setRanking($ranking);
    }
    return $this;
  }

}
