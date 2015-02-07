<?php
/**
 * Created by PhpStorm.
 * User: buchho
 * Date: 08/05/14
 * Time: 14:04
 */

namespace Drupal\djambi\Form;


use Djambi\GameManagers\PlayableGameInterface;
use Djambi\Grids\BaseGrid;
use Djambi\Strings\Glossary;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\djambi\Players\Drupal8Player;
use Drupal\djambi\Utils\GameUI;
use Drupal\user\TempStore;
use Symfony\Component\DependencyInjection\ContainerInterface;

abstract class BaseGameForm extends FormBase implements GameFormInterface {

  const GAME_ID_PREFIX = '';
  const FORM_WRAPPER = 'DjambiFormWrapper-';

  /** @var PlayableGameInterface */
  protected $gameManager;

  /** @var Drupal8Player */
  protected $currentPlayer;

  /** @var TempStore */
  protected $tmpStore;

  /**
   * @param ContainerInterface $container
   *
   * @return static
   */
  public static function create(ContainerInterface $container) {
    /** @var BaseGameForm $form */
    $form = parent::create($container);
    // Gestion des chaînes traduisibles issues de la librairie Djambi
    Glossary::getInstance()->setTranslaterHandler(array($form, 'translateDjambiStrings'));
    // Gestion de l'utilisateur courant
    $form->currentPlayer = Drupal8Player::fromCurrentUser($form->currentUser(), $form->getRequest());
    // Utilisation d'un objet de type KeyValueStore
    $form->tmpStore = $container->get('djambi.tempstore')->get('djambi', $form->getCurrentPlayer()->getId());
    return $form;
  }

  public function translateDjambiStrings($string, $args) {
    if (isset($args['@corpse_id'])) {
      $args['!corpse_name'] = GameUI::printPieceLog($this->getGameManager()->getBattlefield()->findPieceById($args['@corpse_id']), TRUE);
      $string = str_replace('@corpse_id', '!corpse_name', $string);
      unset($args['@corpse_id']);
    }
    if (isset($args['@piece_id'])) {
      $args['!piece_name'] = GameUI::printPieceLog($this->getGameManager()->getBattlefield()->findPieceById($args['@piece_id']), FALSE);
      $string = str_replace('@piece_id', '!piece_name', $string);
      unset($args['@piece_id']);
    }
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
        $args[$replacement] = GameUI::printPieceFullName($this->getGameManager()->getBattlefield()->findPieceById($args[$replacement]), $html);
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
        $args[$replacement] = GameUI::printFactionFullName($this->getGameManager()->getBattlefield()->findFactionById($args[$replacement]), $html);
      }
    }
    return $this->t($string, $args);
  }

  /**
   * @return PlayableGameInterface
   */
  public function getGameManager() {
    return $this->gameManager;
  }

  /**
   * @param PlayableGameInterface $game_manager

   *
*@return $this
   */
  protected function setGameManager(PlayableGameInterface $game_manager) {
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

  protected function getTmpStore() {
    return $this->tmpStore;
  }

  protected function updateStoredGameManager() {
    $this->getTmpStore()->set($this->getFormId(), $this->getGameManager());
  }

  protected function loadStoredGameManager() {
    $stored_game_manager = $this->getTmpStore()->get($this->getFormId());
    if (empty($stored_game_manager)) {
      $this->createGameManager();
    }
    else {
      $this->setGameManager($stored_game_manager);
    }
    return $this;
  }

  /**
   * @inheritdoc
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $rebuild = $form_state->isRebuilding();
    if (empty($this->getGameManager()) || !empty($rebuild)) {
      $this->loadStoredGameManager();
    }

    $form['#theme'] = 'djambi_grid';
    $form['#attached']['library'][] = 'djambi/djambi.ui.watch';
    $form['#djambi_game_manager'] = $this->getGameManager();
    $form['#djambi_current_player'] = $this->getCurrentPlayer();
    $form['#prefix'] = '<div id="' . static::FORM_WRAPPER . $this->getFormId() . '">';
    $form['#suffix'] = '</div>';
    $form['#attributes']['class'][] = 'djambi-grid-form';

    $form_state->disableCache();

    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    $form_state->setRebuild(TRUE);
    $this->updateStoredGameManager();
  }

  protected function buildFormDisplaySettings(&$form) {
    $settings = $this->getCurrentPlayer()->getDisplaySettings();
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
    if ($this->currentPlayer->isPlayingGame($this->getGameManager())) {
      $setting = GameUI::SETTING_HIGHLIGHT_CELLS;
      $form['display'][$setting] = array(
        '#type' => 'checkbox',
        '#title' => t('Change cell background colors to highlight possible selections or allowable moves'),
        '#default_value' => $settings[$setting],
      );
      $setting2 = GameUI::SETTING_DISPLAY_PLAYERS_TABLE;
      $form['display'][$setting2] = array(
        '#type' => 'checkbox',
        '#title' => t('Display current side statuses table'),
        '#default_value' => $settings[$setting2],
      );
      $setting3 = GameUI::SETTING_DISPLAY_CHOICES;
      $form['display'][$setting3] = array(
        '#type' => 'checkbox',
        '#title' => t('Display all available choices below the grid'),
        '#default_value' => $settings[$setting3],
      );
    }
    $setting4 = GameUI::SETTING_DISPLAY_LAST_MOVES_PANEL;
    $form['display'][$setting4] = array(
      '#type' => 'checkbox',
      '#title' => t('Display last moves panel'),
      '#default_value' => $settings[$setting4],
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
      '#submit' => array('::submitDisplaySettings'),
      '#limit_validation_errors' => array(array('display')),
      '#ajax' => $this->getAjaxSettings(),
    );
    if ($settings != GameUI::getDefaultDisplaySettings()) {
      $form['display']['actions']['display-reset'] = array(
        '#type' => 'submit',
        '#value' => t('Reset to default settings'),
        '#submit' => array('::submitResetDisplaySettings'),
        '#limit_validation_errors' => array(),
        '#attributes' => array('class' => array('button--cancel')),
        '#ajax' => $this->getAjaxSettings(),
      );
    }
  }

  public function submitDisplaySettings(array $form, FormStateInterface $form_state) {
    $form_state->setRebuild(TRUE);
    $values = $form_state->getValues();
    $settings = array_merge($this->getCurrentPlayer()->getDisplaySettings(), $values['display']);
    $default_settings = GameUI::getDefaultDisplaySettings();
    foreach ($settings as $key => $value) {
      if (!isset($default_settings[$key]) || $default_settings[$key] == $value) {
        unset($settings[$key]);
      }
    }
    if (empty($settings)) {
      $this->getCurrentPlayer()->clearDisplaySettings();
    }
    else {
      $this->getCurrentPlayer()->saveDisplaySettings($settings);
    }
    $_SESSION['djambi']['extend_display_fieldset'] = TRUE;
  }

  public function submitResetDisplaySettings(array $form, FormStateInterface $form_state) {
    $form_state->setRebuild(TRUE);
    $this->getCurrentPlayer()->clearDisplaySettings();
    $form_state->setUserInput(array());
    $_SESSION['djambi']['extend_display_fieldset'] = TRUE;
  }

  protected function getAjaxSettings() {
    return array(
      'wrapper' => static::FORM_WRAPPER . $this->getFormId(),
    );
  }

}
