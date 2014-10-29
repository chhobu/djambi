<?php
/**
 * Created by PhpStorm.
 * User: buchho
 * Date: 28/10/14
 * Time: 23:33
 */

namespace Djambi\GameManagers;


interface PersistantGameInterface {
  /**
   * Charge une partie.
   *
   * @param string $id
   *
   * @return PersistantGameInterface
   */
  public static function load($id);

  /**
   * Sauvegarde une partie.
   *
   * @return PersistantGameInterface
   */
  public function save();

  /**
   * Recharge une partie.
   *
   * @return PersistantGameInterface
   */
  public function reload();

  /**
   * Supprime une partie.
   */
  public function delete();
} 