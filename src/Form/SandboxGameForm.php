<?php
namespace Drupal\djambi\Form;

use Djambi\Exceptions\DisallowedActionException;
use Djambi\Exceptions\Exception;
use Djambi\GameFactories\GameFactory;
use Djambi\GameManagers\BasicGameManager;
use Djambi\Gameplay\Faction;
use Djambi\Moves\Manipulation;
use Djambi\Moves\Move;
use Djambi\Moves\MoveInteractionInterface;
use Djambi\Moves\Murder;
use Djambi\Moves\Necromobility;
use Djambi\Moves\Reportage;
use Djambi\Moves\ThroneEvacuation;
use Djambi\Strings\GlossaryTerm;
use Drupal\djambi\Form\Actions\CancelLastTurn;
use Drupal\djambi\Form\Actions\CancelPieceSelection;
use Drupal\djambi\Form\Actions\DrawAccept;
use Drupal\djambi\Form\Actions\DrawProposal;
use Drupal\djambi\Form\Actions\DrawReject;
use Drupal\djambi\Form\Actions\Restart;
use Drupal\djambi\Form\Actions\SkipTurn;
use Drupal\djambi\Form\Actions\Withdraw;
use Drupal\djambi\Utils\GameUI;
use Drupal\djambi\Widgets\PlayersTable;

class SandboxGameForm extends BaseGameForm {

  const GAME_ID_PREFIX = 'sandbox-';

  /**
   * @return $this
   */
  public function createGameManager() {
    $game_factory = new GameFactory();
    $game_factory->setMode(BasicGameManager::MODE_SANDBOX);
    $game_factory->setId($this->getFormId());
    $game_factory->addPlayer($this->getCurrentPlayer());
    $this->getCurrentPlayer()->useSeat();
    $this->setGameManager($game_factory->createGameManager());
    $this->gameManager->setInfo('form', get_class($this));
    $this->gameManager->setInfo('path', request_uri());
    $this->gameManager->play();
    $this->updateStoredGameManager();
    return $this;
  }

  public function getFormId() {
    return static::GAME_ID_PREFIX . $this->getCurrentPlayer()->getId();
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
    if (empty($this->getGameManager()) || !empty($form_state['rebuild'])) {
      $this->loadStoredGameManager();
    }

    $form['#theme'] = 'djambi_grid';
    $form['#attached']['library'][] = 'djambi/djambi.ui';
    $form['#djambi_game_manager'] = $this->getGameManager();
    $form['#djambi_current_player'] = $this->getCurrentPlayer();
    $form['#prefix'] = '<div id="' . static::FORM_WRAPPER . $this->getFormId() . '">';
    $form['#suffix'] = '</div>';
    $form['#attributes']['class'][] = 'djambi-grid-form';

    $form_state['no_cache'] = TRUE;

    $form['intro'] = array(
      '#markup' => '<p>' . $this->t("Welcome to Djambi training area. You can play here"
      . " a Djambi game where you control successively all sides : this way, "
      . " you will be able to learn Djambi basic rules, experiment new tactics "
      . " or play with (future ex-)friends in a hot chair mode.") . '</p>',
    );

    $current_turn = $this->getGameManager()->getBattlefield()->getCurrentTurn();
    $form['turn_id'] = array(
      '#type' => 'hidden',
      '#value' => !empty($current_turn) ? $current_turn->getId() : NULL,
    );

    $this->buildGameStatusPanel($form);

    switch ($this->getGameManager()->getStatus()) {
      case(BasicGameManager::STATUS_PENDING):
        $this->buildPendingGameInfosPanel($form);
        $this->buildFormGrid($form);
        break;

      case(BasicGameManager::STATUS_DRAW_PROPOSAL):
        $this->buildPendingGameInfosPanel($form);
        $this->buildFormDrawProposal($form);
        break;

      case(BasicGameManager::STATUS_FINISHED):
        $this->buildFormFinished($form);
        break;
    }

    $this->buildFormDisplaySettings($form, $form_state);

    return $form;
  }

  protected function buildGameStatusPanel(array &$form) {
    $players_table = PlayersTable::build(array('game' => $this->getGameManager(), 'current_player' => $this->getCurrentPlayer()));
    $form['game_status_panel'] = array(
      '#type' => 'details',
      '#open' => TRUE,
      '#title' => $this->t('Game status : %status, started at %created, last change at %updated', array(
        '%status' => new GlossaryTerm($this->getGameManager()->getStatus()),
        '%created' => \Drupal::service('date')->format($this->getGameManager()->getBegin(), 'short'),
        '%updated' => \Drupal::service('date')->format($this->getGameManager()->getChanged(), 'short'),
      )),
      'players_table' => array(
        '#theme' => 'table',
        '#rows' => $players_table->getRows(),
        '#header' => $players_table->getHeader(),
        '#attributes' => array(
          'class' => array('datatable', 'is-centered', 'djambi-players-table'),
        ),
        '#caption' => t("Current sides statuses"),
      ),
    );
  }

  protected function buildPendingGameInfosPanel(array &$form) {
    $descriptions = array();
    $game = $this->getGameManager();
    $playing_next = array();
    $play_order = $game->getBattlefield()->getPlayOrder();
    while (next($play_order)) {
      $playing_next[] = GameUI::printFactionFullName($game->getBattlefield()->findFactionById(current($play_order)));
    }
    reset($play_order);
    $descriptions[] = array(
      'term' => t('Current round :'),
      'description' => t('Round %round', array(
        '%round' => $game->getBattlefield()->getCurrentTurn()->getRound(),
      )),
      'attributes' => array('class' => array('djambi-infos__current-round')),
    );
    $descriptions[] = array(
      'term' => t('Playing now :'),
      'description' => GameUI::printFactionFullName($game->getBattlefield()->getPlayingFaction()),
      'attributes' => array('class' => array('djambi-infos__playing-now')),
    );
    $playing_next_markup = array(
      '#theme' => 'item_list',
      '#items' => $playing_next,
      '#list_type' => 'ol',
    );
    $descriptions[] = array(
      'term' => t('Playing next :'),
      'description' => $playing_next_markup,
      'attributes' => array('class' => array('djambi-infos__playing-next')),
    );
    $form['game_infos_panel'] = array(
      '#theme' => 'description_list',
      '#groups' => $descriptions,
      '#attributes' => array('class' => array('djambi-infos')),
    );
  }

  public function validateForm(array &$form, array &$form_state) {
    $current_turn = $this->getGameManager()->getBattlefield()->getCurrentTurn();
    $current_turn_id = !empty($current_turn) ? $current_turn->getId() : NULL;
    $transmitted_turn_id = $form_state['input']['turn_id'];
    if ($current_turn_id != $transmitted_turn_id) {
      $this->setFormError('turn_id', $form_state, $this->t("You are using an outdated version of this game. It has been now refreshed, you can now select an other action."));
    }
  }

  protected function buildFormGrid(array &$grid_form) {
    $grid = $this->getGameManager()->getBattlefield();
    $current_phase = $grid->getCurrentTurn()->getMove()->getPhase();

    $grid_form['js-extra-choice'] = array(
      '#type' => 'hidden',
      '#value' => '',
    );

    $grid_form['actions'] = array(
      '#type' => 'actions',
    );
    $grid_form['actions']['validation'] = array(
      '#type' => 'submit',
      '#value' => $this->t('Validate'),
      '#validate' => array(array($this, 'validateForm')),
      '#submit' => array(array($this, 'submitForm')),
      '#attributes' => array('class' => array('button--primary')),
      '#ajax' => array(
        'path' => 'djambi/ajax',
        'wrapper' => static::FORM_WRAPPER . $this->getFormId(),
      ),
    );

    switch ($current_phase) {
      case(Move::PHASE_PIECE_SELECTION):
        SkipTurn::addButton($this, $grid_form['actions']);
        DrawProposal::addButton($this, $grid_form['actions']);
        CancelLastTurn::addButton($this, $grid_form['actions']);
        Withdraw::addButton($this, $grid_form['actions']);
        Restart::addButton($this, $grid_form['actions']);
        $this->buildFormGridPieceSelection($grid_form);
        break;

      case(Move::PHASE_PIECE_DESTINATION):
        CancelPieceSelection::addButton($this, $grid_form['actions']);
        $this->buildFormGridPieceDestination($grid_form);
        break;

      case(Move::PHASE_PIECE_INTERACTIONS):
        CancelPieceSelection::addButton($this, $grid_form['actions']);
        $interaction = $grid->getCurrentTurn()->getMove()->getFirstInteraction();
        $args = array();
        if ($interaction->getSelectedPiece()->isAlive()) {
          $args['!target'] = GameUI::printPieceFullName($interaction->getSelectedPiece());
        }
        if ($interaction->getSelectedPiece()->getId() != $interaction->getTriggeringMove()->getSelectedPiece()) {
          $args['!piece'] = GameUI::printPieceFullName($interaction->getTriggeringMove()
              ->getSelectedPiece());
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
    foreach ($grid->getPlayingFaction()->getControlledPieces() as $piece) {
      if ($piece->isMovable()) {
        $cell_choices[$piece->getPosition()->getName()] = GameUI::printPieceFullName($piece) . ' (' . $piece->getPosition()->getName() . ')';
        $piece->setSelectable(TRUE);
      }
    }
    asort($cell_choices);
    $grid_form['cells'] = array(
      '#type' => 'radios',
      '#required' => TRUE,
      '#options' => $cell_choices,
      '#title' => $this->t('Select a movable piece...'),
      '#attributes' => array('class' => array('is-inline')),
    );
    $grid_form['actions']['validation']['#validate'][] = array($this, 'validatePieceSelection');
  }

  public function validatePieceSelection(array &$form, array &$form_state) {
    $grid = $this->getGameManager()->getBattlefield();
    try {
      $piece = $grid->findCellByName($form_state['values']['cells'])->getOccupant();
      $old_selected_piece = $grid->getCurrentTurn()->getMove()->getSelectedPiece();
      if (!is_null($old_selected_piece) && $old_selected_piece->getId() != $piece->getId()) {
        $grid->getCurrentTurn()->resetMove();
      }
      $grid->getCurrentTurn()->getMove()->selectPiece($piece);
    }
    catch (Exception $exception) {
      $this->setFormError('cells', $form_state, $this->t('@message Please choose a movable piece.',
        array('@message' => $exception->getMessage())));
    }
    if (!empty($form_state['input']['js-extra-choice'])) {
      $form_state['values']['cells'] = $form_state['input']['js-extra-choice'];
      unset($form_state['input']['js-extra-choice']);
      $this->validatePieceDestination($form, $form_state);
    }
  }

  protected function buildFormGridPieceDestination(&$grid_form) {
    $grid = $this->getGameManager()->getBattlefield();
    $cell_choices = array();
    $selected_piece = $grid->getCurrentTurn()->getMove()->getSelectedPiece();
    foreach ($selected_piece->getAllowableMoves() as $free_cell) {
      $cell_choices[$free_cell->getName()] = $free_cell->getName()
      . (!empty($free_cell->getOccupant()) ? ' ' . t("(occupied by !piece)", array('!piece' => GameUI::printPieceFullName($free_cell->getOccupant()))) : "");
      $free_cell->setSelectable(TRUE);
    }
    ksort($cell_choices);
    $grid_form['cells'] = array(
      '#type' => 'radios',
      '#required' => TRUE,
      '#options' => $cell_choices,
      '#title' => t('!piece is selected. Now select its destination...', array(
        '!piece' => GameUI::printPieceFullName($selected_piece),
      )),
      '#attributes' => array('class' => array('is-inline')),
    );
    $grid_form['actions']['validation']['#validate'][] = array($this, 'validatePieceDestination');
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
      '#attributes' => array('class' => array('is-inline')),
    );
    $grid_form['actions']['validation']['#validate'][] = array($this, 'validateInteractionChoice');
  }

  protected function buildFormGridVictimChoice(&$grid_form, Reportage $interaction, $message) {
    $cell_choices = array();
    foreach ($interaction->getPossibleChoices() as $cell) {
      $cell_choices[$cell->getName()] = GameUI::printPieceFullName($cell->getOccupant())
        . ' (' . $cell->getName() . ')';
      $cell->getOccupant()->setSelectable(TRUE);
    }
    asort($cell_choices);
    $grid_form['cells'] = array(
      '#type' => 'radios',
      '#required' => TRUE,
      '#options' => $cell_choices,
      '#title' => $message,
      '#attributes' => array('class' => array('is-inline')),
    );
    $grid_form['actions']['validation']['#validate'][] = array($this, 'validateInteractionChoice');
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
      $grid->getCurrentTurn()->resetMove();
      $this->setFormError('cells', $form_state, $this->t('Invalid move data. Please start again your actions.'));
    }
  }

  public function buildFormDrawProposal(array &$grid_form) {
    $peacemonger_faction = NULL;
    $accepted_factions = array();
    $undecided_factions = array();
    foreach ($this->getGameManager()->getBattlefield()->getFactions() as $faction) {
      if ($faction->getDrawStatus() == Faction::DRAW_STATUS_PROPOSED) {
        $peacemonger_faction = GameUI::printFactionFullName($faction);
      }
      elseif ($faction->getDrawStatus() == Faction::DRAW_STATUS_ACCEPTED) {
        $accepted_factions[] = GameUI::printFactionFullName($faction);
      }
      elseif ($faction->getDrawStatus() == Faction::DRAW_STATUS_UNDECIDED
        && $faction->getControl()->getId() != $this->getGameManager()->getBattlefield()->getPlayingFaction()->getId()) {
        $undecided_factions[] = GameUI::printFactionFullName($faction);
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
    $grid_form['actions'] = array('#type' => 'actions');
    DrawReject::addButton($this, $grid_form['actions']);
    DrawAccept::addButton($this, $grid_form['actions']);
  }

  public function buildFormFinished(array &$grid_form) {
    $grid_form['actions'] = array('#type' => 'actions');
    Restart::addButton($this, $grid_form['actions']);
  }

}
