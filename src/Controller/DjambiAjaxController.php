<?php
namespace Drupal\djambi\Controller;

use Djambi\GameManagers\Exceptions\GameNotFoundException;
use Djambi\GameManagers\PlayableGameInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Form\FormInterface;
use Drupal\Core\Form\FormState;
use Drupal\djambi\Players\Drupal8Player;
use Drupal\djambi\Services\ShortTempStore;
use Drupal\djambi\Services\ShortTempStoreFactory;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

class DjambiAjaxController extends ControllerBase {
  /** @var PlayableGameInterface */
  protected $game_manager;
  /** @var  Drupal8Player */
  protected $current_player;
  /** @var ShortTempStore */
  protected $store;

  public function content(Request $request) {
    try {
      $this->retrieveGameManager($request);
    }
    catch (GameNotFoundException $exception) {
      if ($request->isXmlHttpRequest()) {
        drupal_set_message($this->t("The game has been deleted."), "error");
        return $this->t("Game over...");
      }
      else {
        throw new HttpException(403);
      }
    }
    return $this->rebuildForm($request);
  }

  protected function retrieveGameManager(Request $request) {
    $stored_game_key = $request->request->get('form_id');
    /** @var ShortTempStoreFactory $tmp_store_factory */
    $tmp_store_factory = \Drupal::service('djambi.shorttempstore');
    $this->current_player = Drupal8Player::fromCurrentUser($this->currentUser(), $request);
    $this->store = $tmp_store_factory->get('djambi', $this->current_player->getId());
    $this->game_manager = $this->store->get($stored_game_key);
    if (empty($this->game_manager)) {
      throw new GameNotFoundException();
    }
  }

  protected function rebuildForm(Request $request) {
    /** @var FormInterface $form_controller */
    $form_controller = call_user_func_array($this->game_manager->getInfo('form') . '::retrieve',
      array($this->current_player, $this->game_manager, $this->store));
    $form_state = new FormState();
    $form_state->disableRedirect();
    $form_state->setUserInput($request->request->all());
    $build_info = $form_state->getBuildInfo();
    $build_info['callback_object'] = $form_controller;
    $form_state->setBuildInfo($build_info);
    $form = $form_controller->buildForm(array(), $form_state);
    $this->formBuilder()
      ->prepareForm($form_controller->getFormId(), $form, $form_state);
    $this->formBuilder()
      ->processForm($form_controller->getFormId(), $form, $form_state);
    $form['#action'] = $this->game_manager->getInfo('path');
    return $form;
  }

}
