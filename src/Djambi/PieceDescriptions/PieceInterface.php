<?php
/**
 * Created by PhpStorm.
 * User: buchho
 * Date: 24/12/14
 * Time: 10:51
 */

namespace Djambi\PieceDescriptions;


interface PieceInterface {

  public function __construct($start_position);

  public function getType();

  public function getShortname();

  public function getLongname();

  public function getRuleUrl();

  public function getStartPosition();

  public function getValue();

  /**
   * @return PiecesContainerInterface
   */
  public function getContainer();

  public function setContainer(PiecesContainerInterface $container);

}