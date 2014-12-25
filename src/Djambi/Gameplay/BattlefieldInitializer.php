<?php
/**
 * Created by PhpStorm.
 * User: buchho
 * Date: 24/12/14
 * Time: 15:37
 */

namespace Djambi\Gameplay;


use Djambi\Enums\StatusEnum;
use Djambi\GameManagers\PlayableGameInterface;
use Djambi\Grids\Exceptions\InvalidGridException;
use Djambi\PieceDescriptions\PieceInterface;
use Djambi\PieceDescriptions\PiecesContainerInterface;
use Djambi\Players\HumanPlayer;
use Djambi\Players\PlayerInterface;

class BattlefieldInitializer {
  /** @var Battlefield */
  protected $battlefield;

  /**
   * Crée une nouvelle grille de Djambi.
   *
   * @param PlayableGameInterface $game
   *   Objet de gestion de la partie
   * @param PlayerInterface[] $players
   *   Liste des joueurs
   *
   * @throws InvalidGridException
   * @return Battlefield
   *   Nouvelle grille de Djambi
   */
  public static function createNewBattlefield(PlayableGameInterface $game, $players) {
    $battle_creator = new static();
    $battle_creator->battlefield = new Battlefield($game);
    $scheme = $game->getDisposition()->getGrid();
    $scheme_sides = $scheme->getSides();
    // Construction des factions :
    $ready = TRUE;
    $controls = array();
    foreach ($scheme_sides as $side) {
      $axis = NULL;
      if ($side['start_status'] == Faction::STATUS_READY) {
        /* @var HumanPlayer $player */
        $player = array_shift($players);
        if (empty($player)) {
          $side['start_status'] = Faction::STATUS_EMPTY_SLOT;
          $ready = FALSE;
        }
      }
      else {
        $player = NULL;
      }
      $faction = new Faction($battle_creator->battlefield, $side['id'],
        $side['name'], $side['class'], $side['start_order'], $player);
      $faction->setStatus($side['start_status']);
      if (isset($side['control'])) {
        $controls[$faction->getId()] = $side['control'];
      }
      $battle_creator->battlefield->addFaction($faction);
      $start_order = $faction->getStartOrder();
      $leader_position = current(array_slice($scheme_sides, $start_order - 1, 1));
      /** @var PiecesContainerInterface $pieces_container */
      $pieces_container = $side['pieces'];
      foreach ($pieces_container->getTypes() as $type) {
        foreach ($pieces_container->getPiecesByType($type) as $piece) {
          $start_position = $piece->getStartPosition();
          if (is_array($start_position) && !empty($start_position['relative'])) {
            if (is_null($axis)) {
              $axis = $battle_creator->findAxis($leader_position);
            }
            $battle_creator->placePieceRelative($faction, $piece, $leader_position, $axis);
          }
          else {
            $battle_creator->placePieceAbsolute($faction, $piece);
          }
        }
      }
    }
    if (!empty($controls)) {
      foreach ($controls as $controlled => $controller) {
        $battle_creator->battlefield->findFactionById($controlled)
          ->setControl($battle_creator->battlefield->findFactionById($controller), FALSE);
      }
    }
    $game->setStatus($ready ? StatusEnum::STATUS_PENDING : StatusEnum::STATUS_RECRUITING);
    return $battle_creator->battlefield;
  }

  protected function findAxis(array $leader_position) {
    $directions = $this->battlefield->getGameManager()->getDisposition()->getGrid()->getDirections();
    $axis = NULL;
    foreach ($directions as $orientation => $direction) {
      $next_cell = $this->battlefield->findCell($leader_position['x'], $leader_position['y']);
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
      throw new InvalidGridException('Bad pieces start scheme.');
    }
    return $axis;
  }

  protected function placePieceRelative(Faction $faction, PieceInterface $piece, $leader_position, $axis) {
    $directions = $this->battlefield->getGameManager()->getDisposition()->getGrid()->getDirections();
    $start_position = $piece->getStartPosition();
    $starting_cell = $this->battlefield->findCell($leader_position['x'], $leader_position['y']);
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
    $faction->createPiece($piece, $starting_cell);
    return $this;
  }

  protected function placePieceAbsolute(Faction $faction, PieceInterface $piece) {
    $start_position = $piece->getStartPosition();
    if (is_array($start_position) && isset($start_position['x'], $start_position['y'])) {
      $start_cell = $this->battlefield->findCell($start_position['x'], $start_position['y']);
    }
    else {
      $start_cell = $this->battlefield->findCellByName($start_position);
    }
    $faction->createPiece($piece, $start_cell);
    return $this;
  }
}