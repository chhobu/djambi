<?php
namespace Drupal\djambi\Form;

use Composer\Autoload\ClassLoader;
use Djambi\GameFactories\GameFactory;
use Djambi\GameManagers\BasicGameManager;
use Djambi\GameManagers\GameManagerInterface;
use Djambi\Moves\Move;
use Drupal\Component\Utility\Crypt;
use Drupal\Core\Form\FormBase;
use Drupal\djambi\Players\Drupal8Player;
use Drupal\djambi\Services\ShortTempStore;
use Drupal\djambi\Services\ShortTempStoreFactory;
use Symfony\Component\DependencyInjection\ContainerInterface;

class DjambiGridForm extends FormBase {

  const COOKIE_NAME = 'djambiplayerid';

  /** @var GameManagerInterface */
  protected $gameManager;

  /** @var ShortTempStore */
  protected $tmpStore;

  /** @var string */
  protected $gameId;

  /**
   * @param ContainerInterface $container
   *
   * @return static
   */
  public static function create(ContainerInterface $container) {
    /** @var DjambiGridForm $form */
    $form = parent::create($container);
    /** @var ClassLoader $class_loader */
    $class_loader = $container->get('class_loader');
    $class_loader->set('Djambi', array(drupal_get_path('module', 'djambi') . '/lib'));
    /** @var ShortTempStoreFactory $tmp_store_factory */
    $tmp_store_factory = $container->get('djambi.shorttempstore');
    $user = $form->currentUser();
    $full_cookie_name = 'Drupal_visitor_' . static::COOKIE_NAME;
    $game_id_prefix = 'sandbox-';
    if ($user->isAuthenticated()) {
      $owner = $user->id();
      $user_prefix = 'uid-';
    }
    else {
      $player_cookie = $form->getRequest()->cookies->get($full_cookie_name);
      if (is_null($player_cookie)) {
        $owner = Crypt::hashBase64(REQUEST_TIME . session_id());
        user_cookie_save(array(static::COOKIE_NAME => $owner));
      }
      else {
        $owner = $player_cookie;
      }
      $user_prefix = 'cookie-';
    }
    $form->setTmpStore($tmp_store_factory->get('djambi', $owner));
    $form->gameId = $game_id_prefix . $user_prefix . $owner;
    return $form;
  }

  public function getGameId() {
    return $this->gameId;
  }

  /**
   * @return ShortTempStore
   */
  protected function getTmpStore() {
    return $this->tmpStore;
  }

  protected function setTmpStore(ShortTempStore $tmp_store) {
    $this->tmpStore = $tmp_store;
    return $this;
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

  /**
   * @throws \Djambi\Exceptions\Exception
   * @return GameManagerInterface
   */
  protected function createGameManager() {
    $user = $this->currentUser();
    $player = Drupal8Player::createEmptyHumanPlayer();
    $player->useSeat();
    $player->setAccount($user);
    $game_factory = new GameFactory();
    $game_factory->setMode(BasicGameManager::MODE_SANDBOX);
    $game_factory->setId($this->getGameId());
    $game_factory->addPlayer($player);
    $this->setGameManager($game_factory->createGameManager());
    $this->gameManager->play();
    $this->updateStoredGameManager();
    return $this;
  }

  protected function updateStoredGameManager() {
    $this->getTmpStore()->setIfOwner($this->getGameId(), $this->getGameManager());
  }

  protected function loadStoredGameManager() {
    $stored_game_manager = $this->getTmpStore()->getIfOwner($this->getGameId());
    if (empty($stored_game_manager)) {
      $this->createGameManager();
    }
    else {
      $this->setGameManager($stored_game_manager);
    }
    return $this;
  }

  /**
   * Returns a unique string identifying the form.
   * @return string
   *   The unique string identifying the form.
   */
  public function getFormId() {
    return 'djambi_grid_form';
  }

  /**
   * Form constructor.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param array $form_state
   *   An associative array containing the current state of the form.
   *
   * @return array
   *   The form structure.
   */
  public function buildForm(array $form, array &$form_state) {
    $this->loadStoredGameManager();

    $form['intro'] = array(
      '#markup' => '<p>' . $this->t("Welcome to Djambi training area. You can play here"
      . " a Djambi game where you control successively all sides : this way, "
      . " you will be able to learn Djambi basic rules, experiment new tactics "
      . " or play with (future ex-)friends in a hot chair mode.") . '</p>',
    );

    $form['grid'] = array(
      '#type' => 'fieldset',
      '#title' => $this->t('Sandbox Djambi grid #!id', array(
        '!id' => $this->getGameManager()->getId(),
      )),
    );

    if ($this->getGameManager()->isPending()) {
      $this->buildFormGrid($form['grid']);
    }
    return $form;
  }

  protected function buildFormGrid(array &$grid_form) {
    $grid = $this->getGameManager()->getBattlefield();
    $current_phase = $grid->getCurrentMove()->getPhase();

    $grid_form['actions'] = array(
      '#type' => 'actions',
    );
    $grid_form['actions']['validation'] = array(
      '#type' => 'submit',
      '#value' => $this->t('Validate'),
    );

    switch ($current_phase) {
      case(Move::PHASE_PIECE_SELECTION):
        $this->buildFormGridPieceSelection($grid_form);
        break;

      case(Move::PHASE_PIECE_DESTINATION):
        $this->buildFormGridPieceDestination($grid_form);
        break;
    }

  }

  protected function buildFormGridPieceSelection(array &$grid_form) {
    $grid = $this->getGameManager()->getBattlefield();
    $cell_choices = array();
    foreach ($grid->getPlayingFaction()->getPieces() as $piece) {
      if ($piece->isMovable()) {
        $cell_choices[$piece->getPosition()->getName()] = $piece->getLongname();
      }
    }
    $grid_form['cells'] = array(
      '#type' => 'radios',
      '#required' => TRUE,
      '#options' => $cell_choices,
      '#title' => $this->t('Select a movable piece...'),
    );
    $grid_form['actions']['validation']['#submit'] = array(array($this, 'submitPieceSelection'));
  }

  public function submitPieceSelection(array &$form, array &$form_state) {
    $grid = $this->getGameManager()->getBattlefield();
    $cell = $grid->findCellByName($form_state['values']['cells']);
    $grid->getCurrentMove()->selectPiece($cell->getOccupant());
    $this->updateStoredGameManager();
  }

  protected function buildFormGridPieceDestination(&$grid_form) {
    $grid = $this->getGameManager()->getBattlefield();
    $cell_choices = array();
    foreach ($grid->getCurrentMove()
               ->getSelectedPiece()
               ->getAllowableMoves() as $free_cell) {
      $cell_choices[$free_cell->getName()] = $free_cell->getName();
    }
    $grid_form['cells'] = array(
      '#type' => 'radios',
      '#required' => TRUE,
      '#options' => $cell_choices,
      '#title' => t('%piece is selected. Now select its destination...', array(
          '%piece' => $grid->getCurrentMove()->getSelectedPiece()->getLongname(),
        )),
    );
    $grid_form['actions']['validation']['#submit'] = array(array($this, 'submitPieceDestination'));
    $grid_form['actions']['cancel_selection'] = array(
      '#type' => 'submit',
      '#value' => $this->t('Cancel piece selection'),
      '#submit' => array(array($this, 'submitPieceSelectionCancel')),
      '#limit_validation_errors' => array(),
    );
  }

  public function submitPieceDestination(array &$form, array &$form_state) {
    $grid = $this->getGameManager()->getBattlefield();
    $cell = $grid->findCellByName($form_state['values']['cells']);
    $grid->getCurrentMove()->moveSelectedPiece($cell);
    $this->updateStoredGameManager();
  }

  public function submitPieceSelectionCancel(array &$form, array &$form_state) {
    $grid = $this->getGameManager()->getBattlefield();
    $grid->getCurrentMove()->reset();
    $this->updateStoredGameManager();
  }

  /**
   * Form validation handler.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param array $form_state
   *   An associative array containing the current state of the form.
   */
  public function validateForm(array &$form, array &$form_state) {
  }

  /**
   * Form submission handler.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param array $form_state
   *   An associative array containing the current state of the form.
   */
  public function submitForm(array &$form, array &$form_state) {
  }

}
