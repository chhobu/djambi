<?php
/**
 * Created by PhpStorm.
 * User: buchho
 * Date: 25/05/14
 * Time: 16:43
 */

namespace Drupal\djambi\Players;


use Drupal\Component\Utility\Crypt;

class AnonymousDrupal8Player extends Drupal8Player {
  const CLASS_NICKNAME = 'anon-';

  public function useSeat() {
    $this->id = static::CLASS_NICKNAME . Crypt::hashBase64($this->getId());
    user_cookie_save(array(static::COOKIE_NAME => $this->getId()));
    parent::useSeat();
  }

}
