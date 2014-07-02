<?php
namespace Drupal\djambi\Controller;

use Djambi\GameManagers\GameManagerInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\djambi\Form\BaseGameForm;
use Drupal\djambi\Players\Drupal8Player;
use Drupal\djambi\Services\ShortTempStore;
use Drupal\djambi\Services\ShortTempStoreFactory;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

class DjambiAjaxController extends ControllerBase {
  /** @var GameManagerInterface */
  protected $gameManager;
  /** @var ShortTempStore */
  protected $store;
  /** @var BaseGameForm */
  protected $formController;
  /** @var Drupal8Player */
  protected $currentPlayer;

  public function content(Request $request) {
    $stored_game_key = $request->request->get('form_id');
    /** @var ShortTempStoreFactory $tmp_store_factory */
    $tmp_store_factory = \Drupal::service('djambi.shorttempstore');
    $this->currentPlayer = Drupal8Player::fromCurrentUser($this->currentUser(), $request);
    $this->store = $tmp_store_factory->get('djambi', $this->currentPlayer->getId());
    $this->gameManager = $this->store->get($stored_game_key);
    if (empty($this->gameManager)) {
      if ($request->isXmlHttpRequest()) {
        drupal_set_message($this->t("The game has been deleted."), "error");
        return $this->t("Game over...");
      }
      else {
        throw new HttpException(403);
      }
    }
    $this->formController = call_user_func_array($this->gameManager->getInfo('form') . '::retrieve', array(
      $this->currentPlayer,
      $this->gameManager,
      $this->store,
    ));
    $form_state = $this->formBuilder()->getFormStateDefaults();
    $form_state['no_redirect'] = TRUE;
    $form_state['input'] = $request->request->all();
    $form_state['build_info']['callback_object'] = $this->formController;
    $form = $this->formController->buildForm(array(), $form_state);
    $this->formBuilder()->prepareForm($this->formController->getFormId(), $form, $form_state);
    $this->formBuilder()->processForm($this->formController->getFormId(), $form, $form_state);
    $form['#action'] = $this->gameManager->getInfo('path');
    return $form;
  }

}
