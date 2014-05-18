<?php
/**
 * Created by PhpStorm.
 * User: buchho
 * Date: 08/05/14
 * Time: 14:04
 */

namespace Drupal\djambi\Form;


use Composer\Autoload\ClassLoader;
use Djambi\GameManagers\GameManagerInterface;
use Djambi\Gameplay\Faction;
use Djambi\Gameplay\Piece;
use Djambi\Strings\Glossary;
use Drupal\Core\Form\FormBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

abstract class DjambiFormBase extends FormBase {

  /** @var GameManagerInterface */
  protected $gameManager;

  /**
   * Returns a unique string identifying the form.
   * @return string
   *   The unique string identifying the form.
   */
  public function getFormId() {
    return 'djambi_grid_form';
  }

  public static function create(ContainerInterface $container) {
    $form = parent::create($container);
    /** @var ClassLoader $class_loader */
    $class_loader = $container->get('class_loader');
    $class_loader->set('Djambi', array(drupal_get_path('module', 'djambi') . '/lib'));
    Glossary::getInstance()->setTranslaterHandler(array($form, 'translateDjambiStrings'));
    return $form;
  }

  public static function printPieceFullName(Piece $piece, $html = TRUE) {
    $elements = array(
      '#theme' => 'djambi_piece_full_name',
      '#piece' => $piece,
      '#html' => $html,
    );
    return drupal_render($elements);
  }

  public static function printFactionFullName(Faction $faction) {
    $elements = array(
      '#theme' => 'djambi_faction_full_name',
      '#faction' => $faction,
    );
    return drupal_render($elements);
  }

  public function translateDjambiStrings($string, $args) {
    $piece_replacements = array(
      '!piece_id_1' => TRUE,
      '%piece_id_1' => FALSE,
      '%piece_id_2' => FALSE,
      '!piece_id_2' => TRUE,
      '%piece_id' => FALSE,
      '!piece_id' => TRUE,
    );
    foreach ($piece_replacements as $replacement => $html) {
      if (isset($args[$replacement])) {
        $args[$replacement] = static::printPieceFullName($this->getGameManager()
          ->getBattlefield()
          ->findPieceById($args[$replacement]), $html);
      }
    }
    return $this->t($string, $args);
  }

  /**
   * @return GameManagerInterface
   */
  protected function getGameManager() {
    return $this->gameManager;
  }

  /**
   * @param GameManagerInterface $game_manager
   *
   * @return $this
   */
  protected function setGameManager(GameManagerInterface $game_manager) {
    $this->gameManager = $game_manager;
    return $this;
  }

  public function validateForm(array &$form, array &$form_state) {}

  public function submitForm(array &$form, array &$form_state) {}

}
