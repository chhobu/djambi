<?php
/**
 * Created by PhpStorm.
 * User: buchho
 * Date: 07/12/14
 * Time: 13:08
 */

/**
 * Implements hook_drush_command().
 */
function djambi_drush_command() {
  $items['djambi-clear-games'] = array(
    'description' => 'Clear current games key values entries (for testing purpose).',
    'aliases' => array('djb-cg'),
    'options' => array(
      'uid' => 'User ID key value entry to dismiss.',
      'run-cron' => 'Run cron immediately.',
    ),
  );

  return $items;
}

/**
 * Callback for the drush-demo-command command
 */
function drush_djambi_clear_games() {
  $uid = drush_get_option('uid', NULL);
  $cron = drush_get_option('run-cron', FALSE);
  $update = db_update("key_value_expire");
  $update->expression('expire', 'expire - 3610');
  $update->condition('collection', 'djambi');
  if (!empty($uid)) {
    $names = array('sandbox-uid-' . $uid);
    $update->condition('name', $names);
  }
  $update->execute();
  if ($cron) {
    drush_core_cron();
  }
}
