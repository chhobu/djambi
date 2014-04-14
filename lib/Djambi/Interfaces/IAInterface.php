<?php

namespace Djambi\Interfaces;


use Djambi\Cell;
use Djambi\Move;
use Djambi\Players\ComputerPlayer;

interface IAInterface {
  /**
   * Instancie une IA.
   *
   * @param ComputerPlayer $player
   * @param string $name
   *
   * @return IAInterface
   */
  public static function instanciate(ComputerPlayer $player, $name = NULL);

  /**
   * Renvoie le nom de la classe appelée.
   *
   * @return string
   */
  public static function getClass();

  /**
   * Renvoie le nom de l'IA utilisée.
   *
   * @return string
   */
  public function getName();

  /**
   * Lance le tour de jeu d'une IA.
   *
   * @return IAInterface
   */
  public function play();

  /**
   * Renvoie le choix mûrement réfléchi d'un mouvement.
   *
   * @param Move[] $moves
   *   Liste de tous les mouvements possibles
   *
   * @return Move
   *   Mouvement choisi
   **/
  public function decideMove(array $moves);

  /**
   * Renvoie un choix sur une interaction.
   *
   * @param MoveInteractionInterface $move
   *   Interaction suite à un mouvement
   *
   * @return Cell
   *   Choix de déplacement à réaliser
   */
  public function decideInteraction(MoveInteractionInterface $move);

  public function setSettings(array $settings);

  /**
   * @return array
   */
  public function getSettings();

  public function addSetting($var, $value);

  public function getSetting($var);
}
