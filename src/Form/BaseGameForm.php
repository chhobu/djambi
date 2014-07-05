<?php
/**
 * Created by PhpStorm.
 * User: buchho
 * Date: 08/05/14
 * Time: 14:04
 */

namespace Drupal\djambi\Form;


use Djambi\GameManagers\GameManagerInterface;
use Djambi\Grids\BaseGrid;
use Djambi\Strings\Glossary;
use Drupal\Core\Form\FormBase;
use Drupal\djambi\Players\Drupal8Player;
use Drupal\djambi\Services\ShortTempStore;
use Drupal\djambi\Utils\GameUI;
use Symfony\Component\DependencyInjection\ContainerInterface;

abstract class BaseGameForm extends FormBase implements GameFormInterface {

  const GAME_ID_PREFIX = '';
  const FORM_WRAPPER = 'DjambiFormWrapper-';

  /** @var GameManagerInterface */
  protected $gameManager;

  /** @var Drupal8Player */
  protected $currentPlayer;

  /** @var ShortTempStore */
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
    $form->setCurrentPlayer(Drupal8Player::fromCurrentUser($form->currentUser(), $form->getRequest()));
    // Utilisation d'un objet de type KeyValueStore
    $form->tmpStore = $container->get('djambi.shorttempstore')->get('djambi', $form->getCurrentPlayer()->getId());
    return $form;
  }

  public static function retrieve(Drupal8Player $player, GameManagerInterface $game_manager, ShortTempStore $store) {
    $form = new static();
    $form->setCurrentPlayer($player);
    $form->setTmpStore($store);
    $form->setGameManager($game_manager);
    Glossary::getInstance()->setTranslaterHandler(array($form, 'translateDjambiStrings'));
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

  protected function setTmpStore(ShortTempStore $store) {
    $this->tmpStore = $store;
    return $this;
  }

  protected function getTmpStore() {
    return $this->tmpStore;
  }

  public function getErrorHandler() {
    return $this->errorHandler();
  }

  protected function updateStoredGameManager() {
    $this->getTmpStore()->setExpire(60 * 60);
    $this->getTmpStore()->setIfOwner($this->getFormId(), $this->getGameManager());
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
  public function buildForm(array $form, array &$form_state) {
    if (empty($this->getGameManager()) || !empty($form_state['rebuild'])) {
      $this->loadStoredGameManager();
    }

    $form['#theme'] = 'djambi_grid';
    $form['#attached']['library'][] = 'djambi/djambi.ui.watch';
    $form['#djambi_game_manager'] = $this->getGameManager();
    $form['#djambi_current_player'] = $this->getCurrentPlayer();
    $form['#prefix'] = '<div id="' . static::FORM_WRAPPER . $this->getFormId() . '">';
    $form['#suffix'] = '</div>';
    $form['#attributes']['class'][] = 'djambi-grid-form';

    $form_state['no_cache'] = TRUE;

    return $form;
  }

  public function submitForm(array &$form, array &$form_state) {
    $form_state['rebuild'] = TRUE;
    $this->updateStoredGameManager();
  }

  protected function buildFormDisplaySettings(&$form, &$form_state) {
    $settings = $this->getCurrentPlayer()->loadDisplaySettings()->getDisplaySettings();
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
      '#ajax' => $this->getAjaxSettings(),
    );
    if ($settings != GameUI::getDefaultDisplaySettings()) {
      $form['display']['actions']['display-reset'] = array(
        '#type' => 'submit',
        '#value' => t('Reset to default settings'),
        '#submit' => array(array($this, 'submitResetDisplaySettings')),
        '#limit_validation_errors' => array(),
        '#attributes' => array('class' => array('button--cancel')),
        '#ajax' => $this->getAjaxSettings(),
      );
    }
  }

  public function submitDisplaySettings($form, &$form_state) {
    $form_state['rebuild'] = TRUE;
    $settings = array_merge($this->getCurrentPlayer()->getDisplaySettings(), $form_state['values']['display']);
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

  public function submitResetDisplaySettings($form, &$form_state) {
    $this->getCurrentPlayer()->clearDisplaySettings();
    $_SESSION['djambi']['extend_display_fieldset'] = TRUE;
  }

  protected function getAjaxSettings() {
    return array(
      'path' => 'djambi/ajax',
      'wrapper' => static::FORM_WRAPPER . $this->getFormId(),
    );
  }

}
