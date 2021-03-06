<?php
namespace Drupal\kw_djambi\Djambi;


use Djambi\Faction;
use Djambi\GameManager;
use Djambi\Interfaces\BattlefieldInterface;
use Djambi\Interfaces\GameManagerInterface;
use Djambi\Signal;
use Drupal\kw_djambi\Djambi\Players\DrupalPlayer;

class DjambiContext {
  /** @var DrupalPlayer */
  private $currentUser;
  /** @var GameManager */
  private $currentGame;
  /** @var DjambiContext */
  protected static $instance;

  protected function __construct() {
    $this->updateCurrentUser();
  }

  public function getCurrentUser($force_register = FALSE) {
    if ($force_register && !$this->currentUser->isRegistered()) {
      $this->setDjambiCookie()->updateCurrentUser();
    }
    return $this->currentUser;
  }

  public function getCurrentGame() {
    return $this->currentGame;
  }

  public function setCurrentGame(GameManagerInterface $gm) {
    $this->currentGame = $gm;
    $this->getUserFaction($gm->getBattlefield());
    return $this;
  }

  public function resetCurrentGame() {
    $this->currentGame = NULL;
    return $this;
  }

  public function updateCurrentUser() {
    global $user;
    $visitor = new DrupalPlayer($this->getDjambiCookie());
    if ($user->uid > 0 || !is_null($this->getDjambiCookie())) {
      $this->currentUser = $visitor->register(array('user' => $user));
    }
    else {
      $this->currentUser = $visitor->setUser($user);
    }
    return $this;
  }

  protected function setCurrentUser(DrupalPlayer $player) {
    $this->currentUser = $player;
    return $this;
  }

  public static function getInstance() {
    if (is_null(self::$instance)) {
      self::$instance = new static();
    }
    return self::$instance;
  }

  /**
   * Vérifie si une faction est contrôlée par l'utilisateur courant.
   *
   * @param Faction $faction
   *   Faction à vérifier
   * @param bool $control
   *   TRUE pour vérifier également que l'utilisateur possède le contrôle en
   *   cours de jeu de la faction à vérifier.
   *
   * @return bool
   *   TRUE si la faction est contrôlée par l'utilisateur
   */
  public function checkUserPlayingFaction(Faction $faction, $control = FALSE) {
    if ($control) {
      $faction = $faction->getControl();
    }
    return $this->getCurrentUser()->isPlayingFaction($faction);
  }

  /**
   * Détermine si un utilisateur courant contrôle une faction dans une partie.
   *
   * @param BattlefieldInterface $grid
   *   Grille de Djambi à examiner
   *
   * @return Faction
   *   Renvoie le camp contrôlé par l'utilisateur si trouvé.
   *   Si rien n'est trouvé, renvoie une valeur nulle.
   */
  public function getUserFaction(BattlefieldInterface $grid) {
    $current_user_faction = NULL;
    if (!$grid->getGameManager()->isFinished() && $grid->getGameManager()->getMode() == GameManager::MODE_SANDBOX
    && $this->checkUserPlayingFaction($grid->getPlayingFaction())) {
      $current_user_faction = $grid->getPlayingFaction();
      $this->currentUser = $current_user_faction->getPlayer();
    }
    else {
      if (is_array($grid->getFactions())) {
        foreach ($grid->getFactions() as $faction) {
          if ($this->checkUserPlayingFaction($faction, FALSE)) {
            $current_user_faction = $faction;
            $this->currentUser = $current_user_faction->getPlayer();
            break;
          }
        }
      }
    }
    return $current_user_faction;
  }

  protected function setDjambiCookie() {
    if (is_null($this->getDjambiCookie())) {
      $cookie = $this->getCurrentUser()->getId();
      $_SESSION['djambi']['djambi_cookie_id'] = $cookie;
      user_cookie_save(array('djambi_cookie_id' => $cookie));
    }
    return $this;
  }

  public function getDjambiCookie() {
    if (isset($_COOKIE['Drupal_visitor_djambi_cookie_id'])) {
      return $_COOKIE['Drupal_visitor_djambi_cookie_id'];
    }
    elseif (isset($_SESSION['djambi']['djambi_cookie_id'])) {
      return $_SESSION['djambi']['djambi_cookie_id'];
    }
    else {
      return NULL;
    }
  }

  public function isAnonymous() {
    return $this->getCurrentUser()->getUser()->uid == 0;
  }

  public function getIp() {
    return ip_address();
  }

  public function sendSignal() {
    $signal = Signal::createSignal($this->getCurrentUser(), $this->getIp());
    $signal->propagate();
    return $this;
  }

}
