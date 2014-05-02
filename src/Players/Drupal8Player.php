<?php
namespace Drupal\djambi\Players;

use Djambi\Players\HumanPlayer;
use Drupal\Core\Session\AccountInterface;
use Drupal\user\UserInterface;

class Drupal8Player extends HumanPlayer {
  /** @var UserInterface */
  private $account;

  public function setAccount(AccountInterface $user) {
    $this->account = $user;
    return $this;
  }

  public function getAccount() {
    return $this->account;
  }

  protected function prepareArrayConversion() {
    $this->addDependantObjects(array('account' => 'id'));
    return parent::prepareArrayConversion();
  }

  public function displayName() {
    // FIXME utiliser plutôt une fonction de thème
    return $this->getAccount()->getUsername();
  }

  public static function fromArray(array $data, array $context = array()) {
    /** @var Drupal8Player $player */
    $player = parent::fromArray($data);
    if (isset($context['account']) && $context['account'] instanceof AccountInterface) {
      $player->setAccount($context['account']);
    }
    else {
      $account = user_load($data['account']);
      $player->setAccount($account);
    }
    return $player;
  }
}
