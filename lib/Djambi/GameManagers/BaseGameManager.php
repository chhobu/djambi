<?php
/**
 * Created by PhpStorm.
 * User: buchho
 * Date: 19/07/14
 * Time: 00:06
 */

namespace Djambi\GameManagers;


use Djambi\Persistance\PersistantDjambiObject;
use Djambi\Strings\Glossary;
use Djambi\Strings\GlossaryTerm;

abstract class BaseGameManager extends PersistantDjambiObject implements GameManagerInterface {
  const MODE_SANDBOX = 'bac-a-sable';
  const MODE_FRIENDLY = 'amical';
  const MODE_TRAINING = 'training';

  const STATUS_PENDING = 'pending';
  const STATUS_FINISHED = 'finished';
  const STATUS_DRAW_PROPOSAL = 'draw_proposal';
  const STATUS_RECRUITING = 'recruiting';

  /**
   * Liste les modes de jeu.
   *
   * @param bool $with_description
   *   TRUE pour inclure une description des modes de jeu
   * @param bool $with_hidden
   *   TRUE pour inclure les modes de jeu cachés
   *
   * @return array
   *   Tableau contenant les différents modes de jeu disponibles.
   */
  public static function getModes($with_description = FALSE, $with_hidden = FALSE) {
    $modes = array(
      BaseGameManager::MODE_FRIENDLY => new GlossaryTerm(Glossary::MODE_FRIENDLY_DESCRIPTION),
      BaseGameManager::MODE_SANDBOX => new GlossaryTerm(Glossary::MODE_SANDBOX_DESCRIPTION),
    );
    $hidden_modes = array(
      BaseGameManager::MODE_TRAINING => new GlossaryTerm(Glossary::MODE_TRAINING_DESCRIPTION),
    );
    if ($with_hidden) {
      $modes = array_merge($modes, $hidden_modes);
    }
    if ($with_description) {
      return $modes;
    }
    else {
      return array_keys($modes);
    }
  }

  /**
   * Liste les différentes statuts de jeu.
   *
   * @param array $options
   *   Tableau associatif d'options, pouvant contenir les éléments suivants :
   *   - with_description
   *   TRUE pour renvoyer la description des états.
   *   - with_recruiting
   *   TRUE pour inclure également les états avant le début du jeu.
   *   - with_pending
   *   TRUE pour inclure également les états parties en cours
   *    - $with_finished
   *   TRUE pour inclure également les états parties terminées
   *
   * @return array:
   *   Tableau contenant les différents statuts disponibles.
   */
  public static function getStatuses(array $options = NULL) {
    $with_description = isset($options['with_description']) ? $options['with_description'] : FALSE;
    $with_recruiting = isset($options['with_recruiting']) ? $options['with_recruiting'] : TRUE;
    $with_pending = isset($options['with_pending']) ? $options['with_pending'] : TRUE;
    $with_finished = isset($options['with_finished']) ? $options['with_finished'] : TRUE;
    $statuses = array();
    if ($with_recruiting) {
      $statuses[self::STATUS_RECRUITING] = new GlossaryTerm(Glossary::STATUS_RECRUITING_DESCRIPTION);
    }
    if ($with_pending) {
      $statuses[self::STATUS_PENDING] = new GlossaryTerm(Glossary::STATUS_PENDING_DESCRIPTION);
      $statuses[self::STATUS_DRAW_PROPOSAL] = new GlossaryTerm(Glossary::STATUS_DRAW_PROPOSAL_DESCRIPTION);
    }
    if ($with_finished) {
      $statuses[self::STATUS_FINISHED] = new GlossaryTerm(Glossary::STATUS_FINISHED_DESCRIPTION);
    }
    if ($with_description) {
      return $statuses;
    }
    else {
      return array_keys($statuses);
    }
  }
}
