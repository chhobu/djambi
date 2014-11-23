<?php
namespace Drupal\djambi\Form;

use Djambi\Enums\StatusEnum;
use Djambi\Exceptions\DisallowedActionException;
use Djambi\Exceptions\DjambiExceptionInterface;
use Djambi\GameFactories\GameFactory;
use Djambi\Gameplay\Faction;
use Djambi\Moves\Move;
use Djambi\Moves\MoveInteractionInterface;
use Djambi\Strings\GlossaryTerm;
use Drupal\Core\Form\FormStateInterface;
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
    $game_factory = new GameFactory('\Djambi\GameManagers\SandboxGameManager');
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
   * @inheritdoc
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);
    $form['#attached']['library'][] = 'djambi/djambi.ui.play';

    $current_turn = $this->getGameManager()->getBattlefield()->getCurrentTurn();
    $form['turn_id'] = array(
      '#type' => 'hidden',
      '#value' => !empty($current_turn) ? $current_turn->getId() : NULL,
    );

    if ($this->getCurrentPlayer()->getDisplaySetting(GameUI::SETTING_DISPLAY_PLAYERS_TABLE)) {
      $this->buildGameStatusPanel($form);
    }

    switch ($this->getGameManager()->getStatus()) {
      case(StatusEnum::STATUS_PENDING):
        $this->buildPendingGameInfosPanel($form);
        $this->buildFormGrid($form);
        break;

      case(StatusEnum::STATUS_DRAW_PROPOSAL):
        $this->buildPendingGameInfosPanel($form);
        $this->buildFormDrawProposal($form);
        break;

      case(StatusEnum::STATUS_FINISHED):
        $this->buildFinalGameInfosPanel($form);
        $this->buildFormFinished($form);
        break;
    }

    $this->buildFormDisplaySettings($form);

    return $form;
  }

  protected function buildGameStatusPanel(array &$form) {
    $players_table = PlayersTable::build(array('game' => $this->getGameManager(), 'current_player' => $this->getCurrentPlayer()));
    $date_service = \Drupal::service('date.formatter');
    $form['game_status_panel'] = array(
      '#type' => 'details',
      '#open' => TRUE,
      '#title' => $this->t('Game status : %status, started at %created, last change at %updated', array(
        '%status' => new GlossaryTerm($this->getGameManager()->getStatus()),
        '%created' => $date_service->format($this->getGameManager()->getBegin(), 'short'),
        '%updated' => $date_service->format($this->getGameManager()->getChanged(), 'short'),
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
    return $this;
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
    $last_moves = $this->buildLastMovesPanel($game->getBattlefield()->getCurrentTurn()->getRound(),
      $game->getBattlefield()->getCurrentTurn()->getPlayOrderKey());
    if (!empty($last_moves)) {
      $last_moves_markup = array(
        '#theme' => 'item_list',
        '#items' => $last_moves,
        '#list_type' => 'ul',
      );
      $descriptions[] = array(
        'term' => t('Last moves :'),
        'description' => $last_moves_markup,
        'attributes' => array('class' => array('djambi-infos__last-moves')),
      );
    }
    $form['game_infos_panel'] = array(
      '#theme' => 'description_list',
      '#groups' => $descriptions,
      '#attributes' => array('class' => array('djambi-infos')),
    );
    return $this;
  }

  protected function buildFinalGameInfosPanel(&$form) {
    $descriptions = array();
    $past_turns = $this->getGameManager()->getBattlefield()->getPastTurns();
    $last_turn = end($past_turns);
    $descriptions[] = array(
      'term' => t('Final round :'),
      'description' => t('Round %round', array(
        '%round' => $last_turn['round'],
      )),
      'attributes' => array('class' => array('djambi-infos__current-round')),
    );
    $last_moves = $this->buildLastMovesPanel($last_turn['round'], $last_turn['playOrderKey']);
    if (!empty($last_moves)) {
      $last_moves_markup = array(
        '#theme' => 'item_list',
        '#items' => $last_moves,
        '#list_type' => 'ul',
      );
      $descriptions[] = array(
        'term' => t('Last moves :'),
        'description' => $last_moves_markup,
        'attributes' => array('class' => array('djambi-infos__last-moves')),
      );
    }
    $form['game_infos_panel'] = array(
      '#theme' => 'description_list',
      '#groups' => $descriptions,
      '#attributes' => array('class' => array('djambi-infos')),
    );
    return $this;
  }

  protected function buildLastMovesPanel($current_round, $current_play_order) {
    if (!$this->getCurrentPlayer()->getDisplaySetting(GameUI::SETTING_DISPLAY_LAST_MOVES_PANEL)) {
      return NULL;
    }
    $last_moves = array();
    $battlefield = $this->getGameManager()->getBattlefield();
    $past_turns = $battlefield->getPastTurns();
    krsort($past_turns);
    foreach ($past_turns as $turn) {
      if ($current_round == $turn['round'] || ($current_round - 1 == $turn['round'] && $current_play_order <= $turn['playOrderKey'])) {
        $submoves = $this->addSubmovesDescriptions($turn);
        if (!empty($turn['move'])) {
          Move::log($submoves, $turn);
        }
        $submoves_markup = array(
          '#theme' => 'item_list',
          '#attributes' => array('class' => array('submoves')),
          '#items' => $submoves,
        );
        $move = array(
          '#theme' => 'djambi_last_move_item',
          '#time' => \Drupal::service('date.formatter')->format($turn['end'], 'time'),
          '#side' => GameUI::printFactionFullName($battlefield->findFactionById($turn['actingFaction'])),
          '#description' => \Drupal::service('renderer')->render($submoves_markup),
        );
        $last_moves[] = array(
          '#wrapper_attributes' => array('class' => array('djambi-last-move-item')),
          'value' => $move,
        );
      }
    }
    return $last_moves;
  }

  protected function addSubmovesDescriptions($turn) {
    $submoves = array();
    if (!empty($turn['events'])) {
      foreach ($turn['events'] as $event) {
        if (!empty($event['changes'])) {
          foreach ($event['changes'] as $change) {
            if (!empty($change['description'])) {
              $submoves[] = GlossaryTerm::fromArray($change['description']);
            }
          }
        }
      }
    }
    return $submoves;
  }

  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);
    $current_turn = $this->getGameManager()->getBattlefield()->getCurrentTurn();
    $current_turn_id = !empty($current_turn) ? $current_turn->getId() : NULL;
    $input = $form_state->getUserInput();
    $transmitted_turn_id = $input['turn_id'];
    if ($current_turn_id != $transmitted_turn_id) {
      $form_state->setErrorByName('turn_id', $this->t("You are using an outdated version of this game. It has been now refreshed, you can now select an other action."));
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
      '#validate' => array('::validateForm'),
      '#submit' => array('::submitForm'),
      '#attributes' => array('class' => array('button--primary')),
      '#ajax' => $this->getAjaxSettings(),
    );

    switch ($current_phase) {
      case(Move::PHASE_PIECE_SELECTION):
        SkipTurn::addButton($this, $grid_form['actions'], $this->getAjaxSettings());
        DrawProposal::addButton($this, $grid_form['actions'], $this->getAjaxSettings());
        CancelLastTurn::addButton($this, $grid_form['actions'], $this->getAjaxSettings());
        Withdraw::addButton($this, $grid_form['actions'], $this->getAjaxSettings());
        Restart::addButton($this, $grid_form['actions'], $this->getAjaxSettings());
        $this->buildFormGridPieceSelection($grid_form);
        break;

      case(Move::PHASE_PIECE_DESTINATION):
        CancelPieceSelection::addButton($this, $grid_form['actions'], $this->getAjaxSettings());
        $this->buildFormGridPieceDestination($grid_form);
        break;

      case(Move::PHASE_PIECE_INTERACTIONS):
        CancelPieceSelection::addButton($this, $grid_form['actions'], $this->getAjaxSettings());
        $interaction = $grid->getCurrentTurn()->getMove()->getFirstInteraction();
        if ($interaction->isDealingWithPiecesOnly()) {
          $this->buildFormGridVictimChoice($grid_form, $interaction);
        }
        else {
          $this->buildFormGridFreeCellSelection($grid_form, $interaction);
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
    $grid_form['actions']['validation']['#validate'][] = '::validatePieceSelection';
  }

  public function validatePieceSelection(array &$form, FormStateInterface $form_state) {
    $grid = $this->getGameManager()->getBattlefield();
    $values = $form_state->getValues();
    $inputs = $form_state->getUserInput();
    try {
      $piece = $grid->findCellByName($values['cells'])->getOccupant();
      $old_selected_piece = $grid->getCurrentTurn()->getMove()->getSelectedPiece();
      if (!is_null($old_selected_piece) && $old_selected_piece->getId() != $piece->getId()) {
        $grid->getCurrentTurn()->resetMove();
      }
      $grid->getCurrentTurn()->getMove()->selectPiece($piece);
    }
    catch (DjambiExceptionInterface $exception) {
      $form_state->setErrorByName('cells', $this->t('@message Please choose a movable piece.',
        array('@message' => $exception->getMessage())));
    }
    if (!empty($inputs['js-extra-choice'])) {
      $values['cells'] = $inputs['js-extra-choice'];
      unset($inputs['js-extra-choice']);
      $form_state->setValues($values);
      $form_state->setUserInput($inputs);
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
    $grid_form['actions']['validation']['#validate'][] = '::validatePieceDestination';
  }

  public function validatePieceDestination(array &$form, FormStateInterface $form_state) {
    $grid = $this->getGameManager()->getBattlefield();
    $values = $form_state->getValues();
    try {
      $cell = $grid->findCellByName($values['cells']);
      $grid->getCurrentTurn()->getMove()->executeChoice($cell);
    }
    catch (DisallowedActionException $exception) {
      $form_state->setErrorByName('cells', $this->t('You have selected an unreachable cell. Please choose a valid destination.'));
    }
    catch (DjambiExceptionInterface $exception) {
      $form_state->setErrorByName('cells', $this->t('Invalid destination detected. Please choose an empty cell.'));
    }
  }

  protected function buildFormGridFreeCellSelection(&$grid_form, MoveInteractionInterface $interaction) {
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
      '#title' => $interaction->getMessage(),
      '#attributes' => array('class' => array('is-inline')),
    );
    $grid_form['actions']['validation']['#validate'][] = '::validateInteractionChoice';
  }

  protected function buildFormGridVictimChoice(&$grid_form, MoveInteractionInterface $interaction) {
    $cell_choices = array();
    foreach ($interaction->getPossibleChoices() as $cell) {
      $cell_choices[$cell->getName()] = GameUI::printPieceFullName($cell->getOccupant())
        . ' (' . $cell->getName() . ')';
      $cell->setSelectable(TRUE);
    }
    asort($cell_choices);
    $grid_form['cells'] = array(
      '#type' => 'radios',
      '#required' => TRUE,
      '#options' => $cell_choices,
      '#title' => $interaction->getMessage(),
      '#attributes' => array('class' => array('is-inline')),
    );
    $grid_form['actions']['validation']['#validate'][] = '::validateInteractionChoice';
  }

  public function validateInteractionChoice(&$form, FormStateInterface $form_state) {
    $grid = $this->getGameManager()->getBattlefield();
    $move = $grid->getCurrentTurn()->getMove();
    $values = $form_state->getValues();
    if (!empty($move) && !empty($move->getInteractions())) {
      try {
        $move->getFirstInteraction()->executeChoice($grid->findCellByName($values['cells']));
      }
      catch (DjambiExceptionInterface $exception) {
        $form_state->setErrorByName('cells', $this->t('Invalid choice detected : @exception. Please select an other option.',
          array('@exception' => $exception->getMessage())));
      }
    }
    else {
      $grid->getCurrentTurn()->resetMove();
      $form_state->setErrorByName('cells', $this->t('Invalid move data. Please start again your actions.'));
    }
  }

  protected function buildFormDrawProposal(array &$grid_form) {
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
    DrawReject::addButton($this, $grid_form['actions'], $this->getAjaxSettings());
    DrawAccept::addButton($this, $grid_form['actions'], $this->getAjaxSettings());
  }

  protected function buildFormFinished(array &$grid_form) {
    $grid_form['actions'] = array('#type' => 'actions');
    Restart::addButton($this, $grid_form['actions'], $this->getAjaxSettings());
  }

}
