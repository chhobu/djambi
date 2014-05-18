<?php
namespace Drupal\djambi\Form;

use Djambi\Exceptions\DisallowedActionException;
use Djambi\Exceptions\Exception;
use Djambi\GameFactories\GameFactory;
use Djambi\GameManagers\BasicGameManager;
use Djambi\GameManagers\GameManagerInterface;
use Djambi\GameOptions\StandardRuleset;
use Djambi\Gameplay\Faction;
use Djambi\Moves\Manipulation;
use Djambi\Moves\Move;
use Djambi\Moves\MoveInteractionInterface;
use Djambi\Moves\Murder;
use Djambi\Moves\Necromobility;
use Djambi\Moves\Reportage;
use Djambi\Moves\ThroneEvacuation;
use Drupal\Component\Utility\Crypt;
use Drupal\djambi\Players\Drupal8Player;
use Drupal\djambi\Services\ShortTempStore;
use Drupal\djambi\Services\ShortTempStoreFactory;
use Symfony\Component\DependencyInjection\ContainerInterface;

class DjambiGridForm extends DjambiFormBase {

  const COOKIE_NAME = 'djambiplayerid';

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
      '#theme' => 'djambi_grid',
      '#djambi_game_manager' => $this->getGameManager(),
    );

    if ($this->getGameManager()->getStatus() == BasicGameManager::STATUS_PENDING) {
      $this->buildFormGrid($form['grid']);
    }
    elseif ($this->getGameManager()->getStatus() == BasicGameManager::STATUS_DRAW_PROPOSAL) {
      $this->buildFormDrawProposal($form['grid']);
    }
    return $form;
  }

  public function submitForm(array &$form, array &$form_state) {
    $this->updateStoredGameManager();
  }

  protected function buildFormGrid(array &$grid_form) {
    $grid = $this->getGameManager()->getBattlefield();
    $current_phase = $grid->getCurrentTurn()->getMove()->getPhase();

    $grid_form['actions'] = array(
      '#type' => 'actions',
    );
    $grid_form['actions']['validation'] = array(
      '#type' => 'submit',
      '#value' => $this->t('Validate'),
      '#submit' => array(array($this, 'submitForm')),
      '#attributes' => array('class' => array('button-primary')),
    );
    $rule_skip_turn = $this->getGameManager()->getOption(StandardRuleset::GAMEPLAY_ELEMENT_SKIPPED_TURNS);
    if ($rule_skip_turn != 0) {
      if (!$grid->getPlayingFaction()->canSkipTurn()) {
        $label = $this->t('You cannot skip turns anymore');
      }
      elseif ($rule_skip_turn == -1) {
        $label = $this->t('Skip turn');
      }
      else {
        $label = $this->formatPlural($rule_skip_turn - $grid->getPlayingFaction()->getSkippedTurns(),
          'Skip turn (only 1 allowed)', 'Skip turn (still @count allowed)');
      }
      $grid_form['actions']['skipTurn'] = array(
        '#type' => 'submit',
        '#value' => $label,
        '#validate' => array(array($this, 'validateSkipTurn')),
        '#submit' => array(array($this, 'submitForm')),
        '#limit_validation_errors' => array(),
        '#attributes' => array('class' => array('button-warning', 'button-skip-turn')),
        '#disabled' => !$grid->getPlayingFaction()->canSkipTurn(),
      );
    }
    $rule_draw_delay = $this->getGameManager()->getOption(StandardRuleset::GAMEPLAY_ELEMENT_DRAW_DELAY);
    if ($rule_draw_delay != -1) {
      $grid_form['actions']['draw'] = array(
        '#type' => 'submit',
        '#value' => $grid->getPlayingFaction()->canSkipTurn() ? $this->t('Ask for a draw') : $this->formatPlural($rule_skip_turn + $grid->getPlayingFaction()->getLastDrawProposal() - $grid->getCurrentTurn()->getRound(),
          'You cannot ask for a draw until next round', 'You cannot ask for a draw until @count rounds'),
        '#validate' => array(array($this, 'validateAskDraw')),
        '#submit' => array(array($this, 'submitForm')),
        '#limit_validation_errors' => array(),
        '#attributes' => array('class' => array('button-secondary', 'button-ask-draw')),
        '#disabled' => !$grid->getPlayingFaction()->canCallForADraw(),
      );
    }
    $grid_form['actions']['withdraw'] = array(
      '#type' => 'submit',
      '#value' => $this->t('Withdraw'),
      '#validate' => array(array($this, 'validateWithdraw')),
      '#submit' => array(array($this, 'submitForm')),
      '#limit_validation_errors' => array(),
      '#attributes' => array('class' => array('button-danger', 'button-withdraw')),
    );
    $grid_form['actions']['cancelLastTurn'] = array(
      '#type' => 'submit',
      '#value' => $this->t('Cancel last turn'),
      '#validate' => array(array($this, 'validateCancelLastTurn')),
      '#submit' => array(array($this, 'submitForm')),
      '#limit_validation_errors' => array(),
      '#attributes' => array('class' => array('button-cancel')),
      '#disabled' => count($grid->getPastTurns()) == 0,
    );

    switch ($current_phase) {
      case(Move::PHASE_PIECE_SELECTION):
        $this->buildFormGridPieceSelection($grid_form);
        break;

      case(Move::PHASE_PIECE_DESTINATION):
        $this->buildFormGridPieceDestination($grid_form);
        break;

      case(Move::PHASE_PIECE_INTERACTIONS):
        $interaction = $grid->getCurrentTurn()->getMove()->getFirstInteraction();
        $args = array();
        if ($interaction->getSelectedPiece()->isAlive()) {
          $args['!target'] = static::printPieceFullName($interaction->getSelectedPiece());
        }
        if ($interaction->getSelectedPiece()->getId() != $interaction->getTriggeringMove()->getSelectedPiece()) {
          $args['!piece'] = static::printPieceFullName($interaction->getTriggeringMove()->getSelectedPiece());
        }
        if ($interaction instanceof Murder) {
          $this->buildFormGridFreeCellSelection($grid_form, $interaction,
            $this->t("!target has been slayed by !piece. Select now a place to place to bury its corpse.", $args));
        }
        elseif ($interaction instanceof Manipulation) {
          $this->buildFormGridFreeCellSelection($grid_form, $interaction,
            $this->t("!target has been manipulated by !piece's devious words. Select now its new location.", $args));
        }
        elseif ($interaction instanceof Necromobility) {
          $args['!location'] = $interaction->getTriggeringMove()->getDestination()->getName();
          $this->buildFormGridFreeCellSelection($grid_form, $interaction,
            $this->t("!piece has desecrated a grave in !location. Select now a new burial place.", $args));
        }
        elseif ($interaction instanceof ThroneEvacuation) {
          $this->buildFormGridFreeCellSelection($grid_form, $interaction,
            $this->t("!piece cannot occupy the throne case. Select a runaway location.", $args));
        }
        elseif ($interaction instanceof Reportage) {
          $this->buildFormGridVictimChoice($grid_form, $interaction,
            $this->t("!piece cannot reveal a scandal on several persons. Select the victim to focus on.", $args));
        }
        break;
    }

  }

  protected function buildFormGridPieceSelection(array &$grid_form) {
    $grid = $this->getGameManager()->getBattlefield();
    $cell_choices = array();
    foreach ($grid->getPlayingFaction()->getPieces() as $piece) {
      if ($piece->isMovable()) {
        $cell_choices[$piece->getPosition()->getName()] = static::printPieceFullName($piece) . ' (' . $piece->getPosition()->getName() . ')';
        $piece->setSelectable(TRUE);
      }
    }
    asort($cell_choices);
    $grid_form['cells'] = array(
      '#type' => 'radios',
      '#required' => TRUE,
      '#options' => $cell_choices,
      '#title' => $this->t('Select a movable piece...'),
    );
    $grid_form['actions']['validation']['#validate'] = array(array($this, 'validatePieceSelection'));
  }

  public function validatePieceSelection(array &$form, array &$form_state) {
    $grid = $this->getGameManager()->getBattlefield();
    try {
      $piece = $grid->findCellByName($form_state['values']['cells'])
        ->getOccupant();
      $old_selected_piece = $grid->getCurrentTurn()->getMove()->getSelectedPiece();
      if (!is_null($old_selected_piece) && $old_selected_piece->getId() != $piece->getId()) {
        $grid->getCurrentTurn()->resetMove();
      }
      $grid->getCurrentTurn()->getMove()->selectPiece($piece);
    }
    catch (Exception $exception) {
      $this->setFormError('cells', $form_state, $this->t('Invalid piece selection detected : @message. Please choose a movable piece.',
        array('@message' => $exception->getMessage())));
    }
  }

  protected function buildFormGridPieceDestination(&$grid_form) {
    $grid = $this->getGameManager()->getBattlefield();
    $cell_choices = array();
    $selected_piece = $grid->getCurrentTurn()->getMove()->getSelectedPiece();
    foreach ($selected_piece->getAllowableMoves() as $free_cell) {
      $cell_choices[$free_cell->getName()] = $free_cell->getName()
      . (!empty($free_cell->getOccupant()) ? ' ' . t("(occupied by !piece)", array('!piece' => static::printPieceFullName($free_cell->getOccupant()))) : "");
      $free_cell->setSelectable(TRUE);
    }
    ksort($cell_choices);
    $grid_form['cells'] = array(
      '#type' => 'radios',
      '#required' => TRUE,
      '#options' => $cell_choices,
      '#title' => t('!piece is selected. Now select its destination...', array(
        '!piece' => static::printPieceFullName($selected_piece),
      )),
    );
    $grid_form['actions']['validation']['#validate'] = array(array($this, 'validatePieceDestination'));
    $this->addCancelPieceSelectionButton($grid_form['actions']);
  }

  protected function addCancelPieceSelectionButton(&$form_part) {
    $form_part['cancel_selection'] = array(
      '#type' => 'submit',
      '#value' => $this->t('Cancel piece selection'),
      '#validate' => array(array($this, 'validatePieceSelectionCancel')),
      '#submit' => array(array($this, 'submitForm')),
      '#limit_validation_errors' => array(),
      '#attributes' => array('class' => array('button-cancel')),
    );
  }

  public function validatePieceDestination(array &$form, array &$form_state) {
    $grid = $this->getGameManager()->getBattlefield();
    try {
      $cell = $grid->findCellByName($form_state['values']['cells']);
      $grid->getCurrentTurn()->getMove()->executeChoice($cell);
    }
    catch (DisallowedActionException $exception) {
      $this->setFormError('cells', $form_state, $this->t('You have selected an unreachable cell. Please choose a valid destination.'));
    }
    catch (Exception $exception) {
      $this->setFormError('cells', $form_state, $this->t('Invalid destination detected. Please choose an empty cell.'));
    }
  }

  public function validatePieceSelectionCancel(array &$form, array &$form_state) {
    $grid = $this->getGameManager()->getBattlefield();
    $grid->getCurrentTurn()->resetMove();
  }

  protected function buildFormGridFreeCellSelection(&$grid_form, MoveInteractionInterface $interaction, $message) {
    $cell_choices = array();
    foreach ($interaction->getPossibleChoices() as $free_cell) {
      $cell_choices[$free_cell->getName()] = $free_cell->getName();
      $free_cell->setSelectable(TRUE);
    }
    ksort($cell_choices);
    $grid_form['cells'] = array(
      '#type' => 'radios',
      '#required' => TRUE,
      '#options' => $cell_choices,
      '#title' => $message,
    );
    $grid_form['actions']['validation']['#validate'] = array(
      array($this, 'validateInteractionChoice'),
    );
    $this->addCancelPieceSelectionButton($grid_form['actions']);
  }

  protected function buildFormGridVictimChoice(&$grid_form, Reportage $interaction, $message) {
    $cell_choices = array();
    foreach ($interaction->getPossibleChoices() as $cell) {
      $cell_choices[$cell->getName()] = static::printPieceFullName($cell->getOccupant())
        . ' (' . $cell->getName() . ')';
      $cell->getOccupant()->setSelectable(TRUE);
    }
    asort($cell_choices);
    $grid_form['cells'] = array(
      '#type' => 'radios',
      '#required' => TRUE,
      '#options' => $cell_choices,
      '#title' => $message,
    );
    $grid_form['actions']['validation']['#validate'] = array(
      array($this, 'validateInteractionChoice'),
    );
    $this->addCancelPieceSelectionButton($grid_form['actions']);
  }

  public function validateInteractionChoice(&$form, &$form_state) {
    $grid = $this->getGameManager()->getBattlefield();
    $move = $grid->getCurrentTurn()->getMove();
    if (!empty($move) && !empty($move->getInteractions())) {
      try {
        $move->getFirstInteraction()->executeChoice($grid->findCellByName($form_state['values']['cells']));
      }
      catch (Exception $exception) {
        $this->setFormError('cells', $form_state, $this->t('Invalid choice detected : @exception. Please select an other option.',
          array('@exception' => $exception->getMessage())));
      }
    }
    else {
      $this->validatePieceSelectionCancel($form, $form_state);
      $this->setFormError('cells', $form_state, $this->t('Invalid move data. Please start again your actions.'));
    }
  }

  public function validateSkipTurn(&$form, &$form_state) {
    $grid = $this->getGameManager()->getBattlefield();
    try {
      $grid->getPlayingFaction()->skipTurn();
    }
    catch (DisallowedActionException $exception) {
      $this->setFormError('actions', $form_state, $this->t('Invalid action fired : @exception.', array(
        '@exception' => $exception->getMessage(),
      )));
    }
  }

  public function validateWithdraw(&$form, &$form_state) {
    $grid = $this->getGameManager()->getBattlefield();
    try {
      $grid->getPlayingFaction()->withdraw();
    }
    catch (DisallowedActionException $exception) {
      $this->setFormError('actions', $form_state, $this->t('Invalid action fired : @exception', array(
        '@exception' => $exception->getMessage(),
      )));
    }
  }

  public function validateAskDraw(&$form, &$form_state) {
    $grid = $this->getGameManager()->getBattlefield();
    try {
      $grid->getPlayingFaction()->callForADraw();
    }
    catch (DisallowedActionException $exception) {
      $this->setFormError('actions', $form_state, $this->t('Invalid action fired : @exception', array(
        '@exception' => $exception->getMessage(),
      )));
    }
  }

  public function validateCancelLastTurn(&$form, &$form_state) {
    $this->getGameManager()->getBattlefield()->cancelLastTurn();
  }

  public function buildFormDrawProposal(array &$grid_form) {
    $peacemonger_faction = NULL;
    $accepted_factions = array();
    $undecided_factions = array();
    foreach ($this->getGameManager()->getBattlefield()->getFactions() as $faction) {
      if ($faction->getDrawStatus() == Faction::DRAW_STATUS_PROPOSED) {
        $peacemonger_faction = $this->printFactionFullName($faction);
      }
      elseif ($faction->getDrawStatus() == Faction::DRAW_STATUS_ACCEPTED) {
        $accepted_factions[] = $this->printFactionFullName($faction);
      }
      elseif ($faction->getDrawStatus() == Faction::DRAW_STATUS_UNDECIDED
        && $faction->getControl()->getId() != $this->getGameManager()->getBattlefield()->getPlayingFaction()->getId()) {
        $undecided_factions[] = $this->printFactionFullName($faction);
      }
    }
    $grid_form['draw_explanation'] = array(
      '#markup' => $this->t("!faction has called for a draw. What is your answer ?", array(
        '!faction' => $peacemonger_faction,
      )),
    );
    if (!empty($accepted_factions)) {
      $grid_form['draw_accepted'] = array(
        '#markup' => $this->formatPlural(count($accepted_factions), "The following side has accepted the draw : !factions.",
          "The following sides have accepted the draw : !factions.", array('!factions' => implode(', ', $accepted_factions))),
      );
    }
    if (!empty($undecided_factions)) {
      $grid_form['draw_undecided'] = array(
        '#markup' => $this->formatPlural(count($undecided_factions), "The following side has not made his mind now : !factions",
          "The following sides have to announce their decisions : !factions.", array('!factions' => implode(', ', $undecided_factions))),
      );
    }
    $grid_form['actions'] = array(
      '#type' => 'actions',
    );
    $grid_form['actions']['rejectDraw'] = array(
      '#type' => 'submit',
      '#value' => $this->t("No, I'm sure to win this one !"),
      '#submit' => array(array($this, 'submitForm')),
      '#validate' => array(array($this, 'validateRejectDraw')),
      '#limit_validation_errors' => array(),
      '#attributes' => array('class' => array('button-primary', 'button-no')),
    );
    $grid_form['actions']['acceptDraw'] = array(
      '#type' => 'submit',
      '#value' => $this->t("Yes, let's end this mess and stay good friends."),
      '#submit' => array(array($this, 'submitForm')),
      '#validate' => array(array($this, 'validateAcceptDraw')),
      '#limit_validation_errors' => array(),
      '#attributes' => array('class' => array('button-danger', 'button-yes')),
    );
  }

  public function validateRejectDraw() {
    try {
      $this->getGameManager()->getBattlefield()->getPlayingFaction()->rejectDraw();
    }
    catch (DisallowedActionException $exception) {
      $this->setFormError('actions', $form_state, $this->t('Invalid action fired : @exception', array(
        '@exception' => $exception->getMessage(),
      )));
    }
  }

  public function validateAcceptDraw() {
    try {
      $this->getGameManager()->getBattlefield()->getPlayingFaction()->acceptDraw();
    }
    catch (DisallowedActionException $exception) {
      $this->setFormError('actions', $form_state, $this->t('Invalid action fired : @exception', array(
        '@exception' => $exception->getMessage(),
      )));
    }
  }

}
