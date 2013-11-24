<?php

namespace Djambi\Stores;
use Djambi\GameManager;
use Djambi\GameOptions\GameplayElement;
use Djambi\GameOptions\RuleVariant;

/**
 * Class DjambiGameOptionsFactoryStandardRuleset
 */
class GameOptionsStoreStandardRuleset extends GameOptionsStore {

  /**
   * Crée et enregistre un ensemble d'options et de règles standards standards.
   */
  public function __construct() {
    $option3 = new GameplayElement($this, 'allow_anonymous_players', 'OPTION3', 1, 'radios', array(
      1 => 'OPTION3_YES',
      0 => 'OPTION3_NO',
    ));
    $option3->setModes(array(GameManager::MODE_FRIENDLY));

    new GameplayElement($this, 'allowed_skipped_turns_per_user', 'OPTION1', -1, 'select', array(
      0 => 'OPTION1_NEVER',
      1 => 'OPTION1_XTIME',
      2 => 'OPTION1_XTIME',
      3 => 'OPTION1_XTIME',
      4 => 'OPTION1_XTIME',
      5 => 'OPTION1_XTIME',
      10 => 'OPTION1_XTIME',
      -1 => 'OPTION1_ALWAYS',
    ));

    new GameplayElement($this, 'turns_before_draw_proposal', 'OPTION2', 10, 'select', array(
      -1 => 'OPTION2_NEVER',
      0 => 'OPTION2_ALWAYS',
      2 => 'OPTION2_XTURN',
      5 => 'OPTION2_XTURN',
      10 => 'OPTION2_XTURN',
      20 => 'OPTION2_XTURN',
    ));

    new RuleVariant($this, 'rule_surrounding', 'RULE1', 'throne_access', 'radios', array(
      'throne_access' => 'RULE1_THRONE_ACCESS',
      'strict' => 'RULE1_STRICT',
      'loose' => 'RULE1_LOOSE',
    ));

    new RuleVariant($this, 'rule_comeback', 'RULE2', 'allowed', 'radios', array(
      'never' => 'RULE2_NEVER',
      'surrounding' => 'RULE2_SURROUNDING',
      'allowed' => 'RULE2_ALLOWED',
    ));

    new RuleVariant($this, 'rule_vassalization', 'RULE3', 'full_control', 'radios', array(
      'temporary' => 'RULE3_TEMPORARY',
      'full_control' => 'RULE3_FULL_CONTROL',
    ));

    new RuleVariant($this, 'rule_canibalism', 'RULE4', 'no', 'radios', array(
      'yes' => 'RULE4_YES',
      'vassals' => 'RULE4_VASSALS',
      'no' => 'RULE4_NO',
      'ethical' => 'RULE4_ETHICAL',
    ));

    new RuleVariant($this, 'rule_self_diplomacy', 'RULE5', 'never', 'radios', array(
      'never' => 'RULE5_NEVER',
      'vassal' => 'RULE5_VASSAL',
    ));

    new RuleVariant($this, 'rule_press_liberty', 'RULE6', 'pravda', 'radios', array(
      'pravda' => 'RULE6_PRAVDA',
      'foxnews' => 'RULE6_FOXNEWS',
    ));

    new RuleVariant($this, 'rule_throne_interactions', 'RULE7', 'normal', 'radios', array(
      'normal' => 'RULE7_NORMAL',
      'extended' => 'RULE7_EXTENDED',
    ));

  }
}