<?php

namespace Djambi\Stores;
use Djambi\GameManager;
use Djambi\GameOptions\GameplayElement;
use Djambi\GameOptions\RuleVariant;

/**
 * Class DjambiGameOptionsFactoryStandardRuleset
 */
class StandardRuleset extends GameOptionsStore {
  const GAMEPLAY_ELEMENT_ANONYMOUS_PLAYERS = 'allow_anonymous_players';
  const GAMEPLAY_ELEMENT_DRAW_DELAY = 'turns_before_draw_proposal';
  const GAMEPLAY_ELEMENT_SKIPPED_TURNS = 'allowed_skipped_turns_per_user';

  const RULE_SURROUNDING = 'rule_surrounding';
  const RULE_COMEBACK = 'rule_comeback';
  const RULE_VASSALIZATION = 'rule_vassalization';
  const RULE_CANIBALISM = 'rule_canibalism';
  const RULE_DIPLOMACY = 'rule_self_diplomacy';
  const RULE_REPORTERS = 'rule_press_liberty';
  const RULE_EXTRA_INTERACTIONS = 'rule_throne_interactions';

  /**
   * Crée et enregistre un ensemble d'options et de règles standards standards.
   */
  public function __construct() {
    $option3 = new GameplayElement($this, self::GAMEPLAY_ELEMENT_ANONYMOUS_PLAYERS, 'OPTION3', 1, 'radios', array(
      1 => 'OPTION3_YES',
      0 => 'OPTION3_NO',
    ));
    $option3->setModes(array(GameManager::MODE_FRIENDLY));

    new GameplayElement($this, self::GAMEPLAY_ELEMENT_SKIPPED_TURNS, 'OPTION1', -1, 'select', array(
      0 => 'OPTION1_NEVER',
      1 => 'OPTION1_XTIME',
      2 => 'OPTION1_XTIME',
      3 => 'OPTION1_XTIME',
      4 => 'OPTION1_XTIME',
      5 => 'OPTION1_XTIME',
      10 => 'OPTION1_XTIME',
      -1 => 'OPTION1_ALWAYS',
    ));

    new GameplayElement($this, self::GAMEPLAY_ELEMENT_DRAW_DELAY, 'OPTION2', 10, 'select', array(
      -1 => 'OPTION2_NEVER',
      0 => 'OPTION2_ALWAYS',
      2 => 'OPTION2_XTURN',
      5 => 'OPTION2_XTURN',
      10 => 'OPTION2_XTURN',
      20 => 'OPTION2_XTURN',
    ));

    new RuleVariant($this, self::RULE_SURROUNDING, 'RULE1', 'throne_access', 'radios', array(
      'throne_access' => 'RULE1_THRONE_ACCESS',
      'strict' => 'RULE1_STRICT',
      'loose' => 'RULE1_LOOSE',
    ));

    new RuleVariant($this, self::RULE_COMEBACK, 'RULE2', 'allowed', 'radios', array(
      'never' => 'RULE2_NEVER',
      'surrounding' => 'RULE2_SURROUNDING',
      'allowed' => 'RULE2_ALLOWED',
    ));

    new RuleVariant($this, self::RULE_VASSALIZATION, 'RULE3', 'full_control', 'radios', array(
      'temporary' => 'RULE3_TEMPORARY',
      'full_control' => 'RULE3_FULL_CONTROL',
    ));

    new RuleVariant($this, self::RULE_CANIBALISM, 'RULE4', 'no', 'radios', array(
      'yes' => 'RULE4_YES',
      'vassals' => 'RULE4_VASSALS',
      'no' => 'RULE4_NO',
      'ethical' => 'RULE4_ETHICAL',
    ));

    new RuleVariant($this, self::RULE_DIPLOMACY, 'RULE5', 'never', 'radios', array(
      'never' => 'RULE5_NEVER',
      'vassal' => 'RULE5_VASSAL',
    ));

    new RuleVariant($this, self::RULE_REPORTERS, 'RULE6', 'pravda', 'radios', array(
      'pravda' => 'RULE6_PRAVDA',
      'foxnews' => 'RULE6_FOXNEWS',
    ));

    new RuleVariant($this, self::RULE_EXTRA_INTERACTIONS, 'RULE7', 'normal', 'radios', array(
      'normal' => 'RULE7_NORMAL',
      'extended' => 'RULE7_EXTENDED',
    ));

  }
}
