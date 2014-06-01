<?php
namespace Drupal\djambi\Players;

use Djambi\Exceptions\PlayerInvalidException;
use Djambi\Players\HumanPlayer;
use Drupal\Core\Session\AccountInterface;
use Drupal\djambi\Services\ShortTempStore;
use Drupal\djambi\Utils\GameUI;
use Drupal\user\Entity\User;
use Symfony\Component\HttpFoundation\Request;

abstract class Drupal8Player extends HumanPlayer {
  const COOKIE_NAME = 'djambiplayerid';

  /** @var AccountInterface */
  protected $account;

  /** @var array */
  protected $displaySettings;

  public function setAccount(AccountInterface $user) {
    $this->account = $user;
    return $this;
  }

  public function getAccount() {
    return $this->account;
  }

  public function getDisplaySetting($setting) {
    if (!isset($this->displaySettings[$setting])) {
      throw new PlayerInvalidException("Unknown setting : " . $setting);
    }
    return $this->displaySettings[$setting];
  }

  public function getDisplaySettings() {
    return $this->displaySettings;
  }

  public function loadDisplaySettings(ShortTempStore $store) {
    $user_settings = $store->getIfOwner('settings-' . $this->getId());
    if (empty($user_settings)) {
      $user_settings = array();
    }
    $this->displaySettings = array_merge(GameUI::getDefaultDisplaySettings(), $user_settings);
    return $this;
  }

  public function saveDisplaySettings($settings, ShortTempStore $store) {
    $store->setExpire(60 * 60 * 24 * 100);
    $store->setIfOwner('settings-' . $this->getId(), $settings);
    $this->displaySettings = $settings;
    return $this;
  }

  public function clearDisplaySettings(ShortTempStore $store) {
    $store->deleteIfOwner('settings-' . $this->getId());
    $this->displaySettings = GameUI::getDefaultDisplaySettings();
    return $this;
  }

  protected function prepareArrayConversion() {
    $this->addDependantObjects(array('account' => 'id'));
    return parent::prepareArrayConversion();
  }

  public function displayName() {
    $theme_username = array(
      '#theme' => 'username',
      '#account' => $this->getAccount(),
    );
    return drupal_render($theme_username);
  }

  public static function fromArray(array $data, array $context = array()) {
    /** @var Drupal8Player $player */
    $player = parent::fromArray($data);
    if (isset($context['account'])) {
      $account = $context['account'];
    }
    else {
      $account = User::load($data['account']);
    }
    if ($account instanceof AccountInterface) {
      $player->setAccount($account);
    }
    else {
      throw new PlayerInvalidException("Unable loading player object.");
    }
    return $player;
  }

  public static function fromCurrentUser(AccountInterface $account, Request $request) {
    if ($account->isAuthenticated()) {
      $player = new AuthenticatedDrupal8Player(AuthenticatedDrupal8Player::CLASS_NICKNAME . $account->id());
    }
    else {
      $anonymous_id = $request->cookies->get('Drupal_visitor_' . static::COOKIE_NAME);
      $player = new AnonymousDrupal8Player($anonymous_id);
    }
    $player->setAccount($account);
    return $player;
  }

}
