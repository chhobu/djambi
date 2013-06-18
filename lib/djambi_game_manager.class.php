<?php
define('KW_DJAMBI_MODE_SANDBOX', 'bac-a-sable');
define('KW_DJAMBI_MODE_FRIENDLY', 'amical');
define('KW_DJAMBI_MODE_TRAINING', 'training');

define('KW_DJAMBI_STATUS_PENDING', 'pending');
define('KW_DJAMBI_STATUS_FINISHED', 'finished');
define('KW_DJAMBI_STATUS_DRAW_PROPOSAL', 'draw_proposal');
define('KW_DJAMBI_STATUS_RECRUITING', 'recruiting');

define('KW_DJAMBI_USER_PLAYING', 'playing'); // Partie en cours
define('KW_DJAMBI_USER_WINNER', 'winner'); // Fin du jeu, vainqueur
define('KW_DJAMBI_USER_DRAW', 'draw'); // Fin du jeu, nul
define('KW_DJAMBI_USER_KILLED', 'killed'); // Fin du jeu, perdant
define('KW_DJAMBI_USER_WITHDRAW', 'withdraw'); // Fin du jeu, abandon
define('KW_DJAMBI_USER_VASSALIZED', 'vassalized'); // Camp vassalisé
define('KW_DJAMBI_USER_FANTOCHE', 'fantoche'); // Camp fantôche
define('KW_DJAMBI_USER_SURROUNDED', 'surrounded'); // Fin du jeu, encerclement
define('KW_DJAMBI_USER_DEFECT', 'defect'); // Fin du jeu, disqualification
define('KW_DJAMBI_USER_EMPTY_SLOT', 'empty'); // Création de partie, place libre
define('KW_DJAMBI_USER_READY', 'ready'); // Création de partie, prêt à jouer

class DjambiGameManager {
  private $battlefield,
          $persistant = FALSE;

  protected function __construct(DjambiBattlefield $battlefield) {
    $battlefield->setGameManager($this);
    $this->battlefield = $battlefield;
    return $this;
  }

  public static function createGame($mode, $disposition, $players_data) {
    $battlefield = new DjambiBattlefield(array_merge(array(
        'id' => uniqid('Dj'),
        'mode' => $mode,
        'disposition' => $disposition,
        'is_new' => TRUE
    ), $players_data));
    return new self($battlefield);
  }

  public static function loadGame($data) {
    $battlefield = new DjambiBattlefield($data);
    return new self($battlefield);
  }

  /**
   * Liste des modes de jeu
   * @param boolean $with_description
   * @param boolean $with_hidden
   * @return array
   */
  public static function getModes($with_description = FALSE, $with_hidden = FALSE) {
    $modes = array(
        KW_DJAMBI_MODE_FRIENDLY => 'MODE_FRIENDLY_DESCRIPTION',
        KW_DJAMBI_MODE_SANDBOX => 'MODE_SANDBOX_DESCRIPTION',
    );
    $hidden_modes = array(
        KW_DJAMBI_MODE_TRAINING => 'MODE_TRAINING_DESCRIPTION'
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
   * Liste des différentes statuts de jeu
   * @param boolean $with_description
   * @param boolean $with_recruiting
   * @param boolean $with_pending
   * @param boolean $with_finished
   * @return array:
   */
  public static function getStatuses($with_description = FALSE, $with_recruiting = TRUE, $with_pending = TRUE, $with_finished = TRUE) {
    $statuses = array();
    if ($with_recruiting) {
      $statuses[KW_DJAMBI_STATUS_RECRUITING] = 'STATUS_RECRUITING_DESCRIPTION';
    }
    if ($with_pending) {
      $statuses[KW_DJAMBI_STATUS_PENDING] = 'STATUS_PENDING_DESCRIPTION';
      $statuses[KW_DJAMBI_STATUS_DRAW_PROPOSAL] = 'STATUS_DRAW_PROPOSAL_DESCRIPTION';
    }
    if ($with_finished) {
      $statuses[KW_DJAMBI_STATUS_FINISHED] = 'STATUS_FINISHED_DESCRIPTION';
    }
    if ($with_description) {
      return $statuses;
    }
    else {
      return array_keys($statuses);
    }
  }

  /**
   * Liste des différentes dispositions de jeu disponibles
   * @param string $elements : liste des éléments du tableau à renvoyer : 'all', 'description', 'sides', 'nb' ou 'scheme'
   * @param boolean $with_hidden
   * @return array
   */
  public static function getDispositions($elements = 'all', $with_hidden = TRUE) {
    $games = array(
        '4std' => array(
            'description' => '4STD_DESCRIPTION',
            'nb' => 4,
            'scheme' => 'DjambiBattlefieldSchemeStandardGridWith4Sides'
        ),
        '2std' => array(
            'description' => '2STD_DESCRIPTION',
            'nb' => 2,
            'sides' => array(1 => 'playable', 2 => 'vassal', 3 => 'playable', 4 => 'vassal'),
            'scheme' => 'DjambiBattlefieldSchemeStandardGridWith4Sides'
        ),
        '3hex' => array(
            'description' => '3HEX_DESCRIPTION',
            'nb' => 3,
            'scheme' => 'DjambiBattlefieldSchemeHexagonalGridWith3Sides'
        )
    );
    $hidden_games = array(
        '2mini' => array(
            'description' => '2MINI_DESCRPTION',
            'nb' => 2,
            'scheme' => 'DjambiBattlefieldSchemeMiniGridWith2Sides',
            'hidden' => TRUE
        )
    );
    if ($with_hidden) {
      $games = array_merge($games, $hidden_games);
    }
    if ($elements == 'all') {
      return $games;
    }
    $return = array();
    foreach($games as $key => $game) {
      if (isset($game[$elements])) {
        $return[$key] = $game[$elements];
      }
      else {
        $return[$key] = $game;
      }
    }
    return $return;
  }

  /**
   * Liste des options de jeu
   * @return array
   */
  public static function getOptionsInfo() {
    return array(
        'allow_anonymous_players' => array(
            'default' => 1,
            'configurable' => TRUE,
            'title' => 'OPTION3',
            'widget' => 'radios',
            'type' => 'game_option',
            'choices' => array(1 => 'OPTION3_YES', 0 => 'OPTION3_NO'),
            'modes' => array(KW_DJAMBI_MODE_FRIENDLY)
        ),
        'allowed_skipped_turns_per_user' => array(
            'default' => -1,
            'configurable' => TRUE,
            'title' => 'OPTION1',
            'widget' => 'select',
            'type' => 'game_option',
            'choices' => array(
                0 => 'OPTION1_NEVER',
                1 => 'OPTION1_XTIME',
                2 => 'OPTION1_XTIME',
                3 => 'OPTION1_XTIME',
                4 => 'OPTION1_XTIME',
                5 => 'OPTION1_XTIME',
                10 => 'OPTION1_XTIME',
                -1 => 'OPTION1_ALWAYS')
        ),
        'turns_before_draw_proposal' => array(
            'default' => 10,
            'configurable' => TRUE,
            'title' => 'OPTION2',
            'widget' => 'select',
            'type' => 'game_option',
            'choices' => array(
                -1 => 'OPTION2_NEVER',
                0 => 'OPTION2_ALWAYS',
                2 => 'OPTION2_XTURN',
                5 => 'OPTION2_XTURN',
                10 => 'OPTION2_XTURN',
                20 => 'OPTION2_XTURN')
        ),
        'rule_surrounding' => array(
            'title' => 'RULE1',
            'type' => 'rule_variant',
            'configurable' => TRUE,
            'default' => 'throne_access',
            'widget' => 'radios',
            'choices' => array(
                'throne_access' => 'RULE1_THRONE_ACCESS',
                'strict' => 'RULE1_STRICT',
                'loose' => 'RULE1_LOOSE'
            )
        ),
        'rule_comeback' => array(
            'title' => 'RULE2',
            'type' => 'rule_variant',
            'configurable' => TRUE,
            'default' => 'allowed',
            'widget' => 'radios',
            'choices' => array(
                'never' => 'RULE2_NEVER',
                'surrounding' => 'RULE2_SURROUNDING',
                'allowed' => 'RULE2_ALLOWED'
            )
        ),
        'rule_vassalization' => array(
            'title' => 'RULE3',
            'type' => 'rule_variant',
            'configurable' => TRUE,
            'widget' => 'radios',
            'default' => 'full_control',
            'choices' => array(
                'temporary' => 'RULE3_TEMPORARY',
                'full_control' => 'RULE3_FULL_CONTROL'
            )
        ),
        'rule_canibalism' => array(
            'title' => 'RULE4',
            'type' => 'rule_variant',
            'widget' => 'radios',
            'configurable' => TRUE,
            'default' => 'no',
            'choices' => array(
                'yes' => 'RULE4_YES',
                'vassals' => 'RULE4_VASSALS',
                'no' => 'RULE4_NO',
                'ethical' => 'RULE4_ETHICAL'
            )
        ),
        'rule_self_diplomacy' => array(
            'title' => 'RULE5',
            'type' => 'rule_variant',
            'configurable' => TRUE,
            'widget' => 'radios',
            'default' => 'never',
            'choices' => array(
                'never' => 'RULE5_NEVER',
                'vassal' => 'RULE5_VASSAL'
            )
        ),
        'rule_press_liberty' => array(
            'title' => 'RULE6',
            'type' => 'rule_variant',
            'configurable' => TRUE,
            'widget' => 'radios',
            'default' => 'pravda',
            'choices' => array(
                'pravda' => 'RULE6_PRAVDA',
                'foxnews' => 'RULE6_FOXNEWS'
            )
        ),
        'rule_throne_interactions' => array(
            'title' => 'RULE7',
            'type' => 'rule_variant',
            'configurable' => TRUE,
            'widget' => 'radios',
            'default' => 'normal',
            'choices' => array(
                'normal' => 'RULE7_NORMAL',
                'extended' => 'RULE7_EXTENDED'
            )
        ),
    );
  }

  /**
   * @return DjambiBattlefield
   */
  public function getBattlefield() {
    return $this->battlefield;
  }

  /**
   * @return boolean
   */
  public function isPersistant() {
    return $this->persistant;
  }

  protected function setPersistant($bool) {
    $this->persistant = $bool;
  }

  public function saveGame() {
    $this->getBattlefield()->setInfo('changed', time());
    $data = $this->getBattlefield()->toArray();
    foreach ($this->getBattlefield()->getFactions() as $key => $faction) {
      $user_data = array(
          'uid' => $faction->getUserDataItem('uid'),
          'status' => $faction->getStatus(),
          'ranking' => $faction->getRanking(),
          'human' => $faction->isHumanControlled(),
          'ia' => $faction->getUserDataItem('ia'),
          'cookie' => NULL
      );
      $data['users'][$faction->getId()] = $user_data;
    }
    return $data;
  }

  public function reload() {
    return $this;
  }

  public function updateUserInfos($user_data, $user_id, $is_new_user = FALSE) {
    return FALSE;
  }

  public function isPlayable() {
    if ($this->getBattlefield()->getStatus() == KW_DJAMBI_STATUS_PENDING) {
      return TRUE;
    }
    else {
      return FALSE;
    }
  }

  public function play() {
    if ($this->isPlayable()) {
      $summary = $this->getBattlefield()->getSummary();
      if (empty($summary)) {
        $this->getBattlefield()->prepareSummary();
      }
      $this->getBattlefield()->getPlayOrder(TRUE);
      $this->getBattlefield()->defineMovablePieces();
    }
    return $this;
  }

  /**
   * Vérifie si la faction passée en argument est contrôlée par l'utilisateur courant.*
   * @return bool
   */
  public function checkUserPlayingFaction($user_id, $user_cookie, DjambiPoliticalFaction $faction, $control = TRUE) {
    if (empty($faction)) {
      return FALSE;
    }
    if ($control) {
      $user_data = $faction->getControl()->getUserData();
    }
    else {
      $user_data = $faction->getUserData();
    }
    if ($user_id > 0) {
      if ($user_data['uid'] == $user_id && $user_data['human']) {
        return TRUE;
      }
      else {
        return FALSE;
      }
    }
    elseif (!$this->isPersistant()) {
      if ($user_data['human']) {
        return TRUE;
      }
    }
    elseif (!empty($user_cookie) && $user_data['cookie'] == $user_cookie) {
      return TRUE;
    }
    else {
      return FALSE;
    }
  }

  /**
   * Détermine si un utilisateur courant contrôle une faction
   * @return DjambiPoliticalFaction
   */
  public function getUserFaction($user_id, $user_cookie) {
    $grid = $this->getBattlefield();
    $current_user_faction = NULL;
    if (is_array($grid->getFactions())) {
      foreach ($grid->getFactions() as $faction) {
        if ($this->checkUserPlayingFaction($user_id, $user_cookie, $faction, FALSE)) {
          $current_user_faction = $faction;
        }
      }
    }
    if (!$grid->isFinished() && !is_null($current_user_faction) && $grid->getMode() == KW_DJAMBI_MODE_SANDBOX) {
      return $grid->getPlayingFaction();
    }
    return $current_user_faction;
  }
}