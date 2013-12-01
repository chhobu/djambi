<?php
namespace Drupal\kw_djambi\Djambi\Players;

use Djambi\Players\HumanPlayer;
use Drupal\kw_djambi\Djambi\GameManagers\DrupalGameManager;

class DrupalPlayer extends HumanPlayer {
  /** @var \stdClass $user */
  private $user;
  /** @var array $games */
  protected $games;

  public function __construct($id = NULL) {
    parent::__construct($id);
  }

  public function register(array $data = NULL) {
    if (isset($data['user']) && is_object($data['user'])) {
      if ($data['user']->uid == 0) {
        $player = new DrupalAnonymousPlayer($this->getId());
      }
      else {
        $player = new DrupalIdentifiedPlayer($data['user']->uid);
      }
      $player->register($data);
    }
    else {
      $player = $this;
    }
    return $player;
  }

  public function getUser() {
    return $this->user;
  }

  public function setUser($user) {
    $this->user = $user;
    return $this;
  }

  public function getActiveGames() {
    if (is_null($this->games) || $this->isRegistered()) {
      // Recherche d'une partie en cours pour l'utilisateur ciblé :
      $q = db_select("djambi_users", "u");
      $q->join("djambi_node", "n", "n.nid = u.nid");
      $q->join("node", "n2", "n.nid = n2.nid");
      $q->fields("n", array("nid", "mode", "status"));
      $q->fields("n2", array("created"));
      $q->condition("n.status", DrupalGameManager::getStatuses(array(
        'with_pending' => TRUE,
        'with_recruting' => TRUE,
        'with_finished' => FALSE,
      )));
      if ($this->getUser()->uid > 0) {
        $q->condition("u.uid", $this->getId());
      }
      else {
        $q->condition('u.cookie', $this->getId());
      }
      $results = $q->execute()->fetchAll();
      if (!empty($results)) {
        foreach ($results as $result) {
          $this->games[$result->mode][] = $result;
        }
      }
    }
    return $this->games;
  }

  /**
   * Vérifie si l'utilisateur courant particip déjà à une partie.
   */
  public function isUserAlreadyPlaying() {
    $games = $this->getActiveGames();
    return !empty($games);
  }

  public function getNewGameAllowedModes() {
    $allowed = DrupalGameManager::getModes();
    $games = $this->getActiveGames();
    if (!empty($games)) {
      foreach ($games as $mode => $mode_games) {
        switch ($mode) {
          case(DrupalGameManager::MODE_SANDBOX):
            $forbidden_statuses = DrupalGameManager::getStatuses(array(
              'with_pending' => TRUE,
              'with_recruiting' => TRUE,
              'with_finished' => FALSE,
            ));
            break;

          case(DrupalGameManager::MODE_FRIENDLY):
            $forbidden_statuses = DrupalGameManager::getStatuses(array(
              'with_pending' => FALSE,
              'with_recruiting' => TRUE,
              'with_finished' => FALSE,
            ));
            break;

          default:
            $forbidden_statuses = array();
        }
        foreach ($mode_games as $game) {
          if (in_array($game->mode, $allowed) && in_array($game->status, $forbidden_statuses)) {
            unset($allowed[array_search($mode, $allowed)]);
            break;
          }
        }
      }
    }
    return $allowed;
  }

  public function redirectToCurrentGame($mode) {
    $games = $this->getActiveGames();
    if (!in_array($mode, $this->getNewGameAllowedModes()) && !empty($games[$mode])) {
      $current_game = current($games[$mode]);
      if ($mode == DrupalGameManager::MODE_SANDBOX) {
        drupal_set_message(t("You have already begun a !game on !date. This game does not seem to be finished : switching back to the last played move of the game.", array(
          "!date" => format_date($current_game->created),
          "!game" => _kw_djambi_get_translatable_messages($mode),
        )), 'warning');
      }
      elseif ($mode == DrupalGameManager::MODE_FRIENDLY) {
        drupal_set_message(t("You are already involved in a !game which is in a recruiting phase : you cannot create a new game.", array(
          "!game" => _kw_djambi_get_translatable_messages($mode),
        )), 'warning');
      }
      drupal_goto('node/' . $current_game->nid);
    }
  }

  public function displayName() {
    return theme('username', array('account' => $this->getUser()));
  }

}
