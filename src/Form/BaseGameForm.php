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
use Djambi\Grids\BaseGrid;
use Djambi\Strings\Glossary;
use Drupal\Core\Form\FormBase;
use Drupal\djambi\Players\Drupal8Player;
use Drupal\djambi\Services\ShortTempStoreFactory;
use Drupal\djambi\Utils\GameUI;
use Symfony\Component\DependencyInjection\ContainerInterface;

abstract class BaseGameForm extends FormBase implements GameFormInterface {

  const GAME_ID_PREFIX = '';

  /** @var GameManagerInterface */
  protected $gameManager;

  /** @var Drupal8Player */
  protected $currentPlayer;

  /** @var ShortTempStoreFactory */
  protected $tmpStoreFactory;

  /**
   * Returns a unique string identifying the form.
   * @return string
   *   The unique string identifying the form.
   */
  public function getFormId() {
    return 'djambi_grid_form';
  }

  /**
   * @param ContainerInterface $container
   *
   * @return static
   */
  public static function create(ContainerInterface $container) {
    /** @var BaseGameForm $form */
    $form = parent::create($container);
    // Chargement de la librairie Djambi
    /** @var ClassLoader $class_loader */
    $class_loader = $container->get('class_loader');
    $class_loader->set('Djambi', array(drupal_get_path('module', 'djambi') . '/lib'));
    // Gestion des chaînes traduisibles issues de la librairie Djambi
    Glossary::getInstance()->setTranslaterHandler(array($form, 'translateDjambiStrings'));
    // Gestion de l'utilisateur courant
    $form->setCurrentPlayer(Drupal8Player::fromCurrentUser($form->currentUser(), $form->getRequest()));
    // Utilisation d'un objet de type KeyValueStore
    $form->tmpStoreFactory = $container->get('djambi.shorttempstore');
    return $form;
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
        $args[$replacement] = GameUI::printPieceFullName($this->getGameManager()
            ->getBattlefield()
            ->findPieceById($args[$replacement]), $html);
      }
    }
    $faction_replacements = array(
      '!faction_id' => TRUE,
      '!faction_id1' => TRUE,
      '!faction_id2' => TRUE,
      '%faction_id' => FALSE,
      '%faction_id1' => FALSE,
      '%faction_id2' => FALSE,
    );
    foreach ($faction_replacements as $replacement => $html) {
      if (isset($args[$replacement])) {
        $args[$replacement] = GameUI::printFactionFullName($this->getGameManager()
            ->getBattlefield()
            ->findFactionById($args[$replacement]), $html);
      }
    }
    return $this->t($string, $args);
  }

  /**
   * @return GameManagerInterface
   */
  public function getGameManager() {
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

  public function resetGameManager() {
    $this->gameManager = NULL;
    return $this;
  }

  /**
   * @return Drupal8Player
   */
  public function getCurrentPlayer() {
    return $this->currentPlayer;
  }

  protected function setCurrentPlayer(Drupal8Player $player) {
    $this->currentPlayer = $player;
    return $this;
  }

  /**
   * @return ShortTempStoreFactory
   */
  protected function getTmpStoreFactory() {
    return $this->tmpStoreFactory;
  }

  protected function getTmpStore() {
    return $this->getTmpStoreFactory()->get('djambi', $this->getCurrentPlayer()->getId());
  }

  public function addFormError($name, &$form_state, $message) {
    $this->setFormError($name, $form_state, $message);
    return $this;
  }

  protected function updateStoredGameManager() {
    $this->getTmpStore()->setExpire(60 * 60);
    $this->getTmpStore()->setIfOwner($this->getGameId(), $this->getGameManager());
  }

  protected function loadStoredGameManager() {
    $stored_game_manager = $this->getTmpStore()->get($this->getGameId());
    if (empty($stored_game_manager)) {
      $this->createGameManager();
    }
    else {
      $this->setGameManager($stored_game_manager);
    }
    return $this;
  }

  public function submitForm(array &$form, array &$form_state) {
    $this->updateStoredGameManager();
  }

  protected function buildFormDisplaySettings(&$form, &$form_state) {
    $settings = $this->getCurrentPlayer()->loadDisplaySettings($this->getTmpStore())->getDisplaySettings();
    if (!empty($_SESSION['djambi']['extend_display_fieldset'])) {
      $open = TRUE;
      unset($_SESSION['djambi']['extend_display_fieldset']);
    }
    else {
      $open = FALSE;
    }
    $form['display'] = array(
      '#type' => 'details',
      '#title' => t('Display settings'),
      '#open' => $open,
      '#tree' => TRUE,
    );
    $setting = GameUI::SETTING_HIGHLIGHT_CELLS;
    $form['display'][$setting] = array(
      '#type' => 'checkbox',
      '#title' => t('Change cell background colors to highlight possible selections or allowable moves'),
      '#default_value' => $settings[$setting],
    );
    if ($this->getGameManager()->getDisposition()->getGrid()->getShape() == BaseGrid::SHAPE_HEXAGONAL) {
      $setting = GameUI::SETTING_DISPLAY_CELL_NAME_HEXAGONAL;
    }
    else {
      $setting = GameUI::SETTING_DISPLAY_CELL_NAME_CARDINAL;
    }
    $form['display'][$setting] = array(
      '#type' => 'checkbox',
      '#title' => t('Display cell names'),
      '#default_value' => $settings[$setting],
    );
    $setting = GameUI::SETTING_GRID_SIZE;
    $form['display'][$setting] = array(
      '#type' => 'radios',
      '#title' => t('Grid size'),
      '#options' => array(
        GameUI::GRID_SIZE_SMALL => t('small'),
        GameUI::GRID_SIZE_STANDARD => t('standard'),
        GameUI::GRID_SIZE_BIG => t('big'),
        GameUI::GRID_SIZE_ADAPTATIVE => t('adjusted to screen dimensions'),
      ),
      '#default_value' => $settings[$setting],
    );
    $form['display']['actions'] = array('#type' => 'actions');
    $form['display']['actions']['display-submit'] = array(
      '#type' => 'submit',
      '#value' => t('Save display settings'),
      '#submit' => array(array($this, 'submitDisplaySettings')),
      '#limit_validation_errors' => array(array('display')),
    );
    if ($settings != GameUI::getDefaultDisplaySettings()) {
      $form['display']['actions']['display-reset'] = array(
        '#type' => 'submit',
        '#value' => t('Reset to default settings'),
        '#submit' => array(array($this, 'submitResetDisplaySettings')),
        '#limit_validation_errors' => array(),
        '#attributes' => array('class' => array('button--cancel')),
      );
    }
  }

  public function submitDisplaySettings($form, &$form_state) {
    $settings = array_merge($this->getCurrentPlayer()->getDisplaySettings(), $form_state['values']['display']);
    $default_settings = GameUI::getDefaultDisplaySettings();
    foreach ($settings as $key => $value) {
      if (!isset($default_settings[$key]) || $default_settings[$key] == $value) {
        unset($settings[$key]);
      }
    }
    if (empty($settings)) {
      $this->getCurrentPlayer()->clearDisplaySettings($this->getTmpStore());
    }
    else {
      $this->getCurrentPlayer()->saveDisplaySettings($settings, $this->getTmpStore());
    }
    $_SESSION['djambi']['extend_display_fieldset'] = TRUE;
  }

  public function submitResetDisplaySettings($form, &$form_state) {
    $this->getCurrentPlayer()->clearDisplaySettings($this->getTmpStore());
    $_SESSION['djambi']['extend_display_fieldset'] = TRUE;
  }

}
