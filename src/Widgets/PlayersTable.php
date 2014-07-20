<?php
/**
 * Created by PhpStorm.
 * User: buchho
 * Date: 27/05/14
 * Time: 22:25
 */

namespace Drupal\djambi\Widgets;


use Djambi\Exceptions\Exception;
use Djambi\GameManagers\GameManagerInterface;
use Djambi\Gameplay\Faction;
use Djambi\Players\HumanPlayer;
use Djambi\Strings\GlossaryTerm;
use Drupal\djambi\Utils\GameUI;

class PlayersTable extends BaseTable {

  public static function build($data) {
    if (!isset($data['game'], $data['current_player'])
      || !$data['game'] instanceof GameManagerInterface
      || !$data['current_player'] instanceof HumanPlayer
    ) {
      throw new Exception("Invalid data arguments for generating PlayersTable.");
    }
    return parent::build($data);
  }

  /**
   * @return GameManagerInterface
   */
  protected function getGame() {
    return $this->data['game'];
  }

  public function declareHeader() {
    $this->header = array(
      'factions' => t('Faction'),
      'players' => t('Player'),
      'status' => t('Status'),
      'ruler' => t('Ruler ?'),
      'pieces' => t('Controlled pieces'),
      'moves' => t('Moves'),
      'playTime' => t('Total play time'),
      'avgPlayTime' => t('Average play time'),
    );
    return $this;
  }

  protected function isCurrentPlayer(Faction $faction) {
    /** @var HumanPlayer $current_player */
    $current_player = $this->data['current_player'];
    return $current_player->isPlayingFaction($faction);
  }

  public function generateRows() {
    foreach ($this->getGame()->getBattlefield()->getFactions() as $faction) {
      $row = $this->generateSingleRow($faction);
      $row['class'][] = $this->isCurrentPlayer($faction) ? 'is-me' : 'is-not-me';
      $this->rows[] = $row;
    }
    return $this;
  }

  protected function buildFactionsRowData(Faction $faction) {
    return array(
      'data' => GameUI::printFactionFullName($faction, FALSE),
      'class' => array('is-' . $faction->getClass() . '-side'),
      'header' => TRUE,
    );
  }

  protected function buildPlayersRowData(Faction $faction) {
    if (!empty($faction->getPlayer())) {
      return $faction->getPlayer()->displayName() . ($this->isCurrentPlayer($faction) ? " " . t("(Me !)") : "");
    }
    else {
      return $this->returnEmptyValue();
    }
  }

  protected function buildStatusRowData(Faction $faction) {
    switch ($faction->getStatus()) {
      case(Faction::STATUS_EMPTY_SLOT):
        return t("Unassigned, waiting for a new player...");

      case(Faction::STATUS_DEFECT):
        $return = t("Defected");
        break;

      case(Faction::STATUS_READY):
        return t("Playing, waiting for a new turn...");

      case(Faction::STATUS_PLAYING):
        return t("Currently playing");

      case(Faction::STATUS_KILLED):
        $return = t("Game over due to killed leader");
        break;

      case(Faction::STATUS_SURROUNDED):
        $return = t("Game over due to leader surrounding");
        break;

      case(Faction::STATUS_VASSALIZED):
        return t("Vassalized by !faction side", array('!faction' => GameUI::printFactionFullName($faction->getControl())));

      case(Faction::STATUS_DRAW):
        return t("White peace signatory");

      case(Faction::STATUS_WINNER):
        return t("Winner !");

      case(Faction::STATUS_WITHDRAW):
        return t("Withdrawn");

      default:
        $return = new GlossaryTerm($faction->getStatus());
    }
    if ($faction->getControl()->getId() != $faction->getId()) {
      $return .= " - " . t("Remaining pieces are now controlled by !faction side.",
          array('!faction' => GameUI::printFactionFullName($faction->getControl())));
    }
    return $return;
  }

  protected function buildRulerRowData(Faction $faction) {
    if ($faction->getStatus() == Faction::STATUS_EMPTY_SLOT) {
      return $this->returnEmptyValue();
    }
    $ruler = $this->getGame()->getBattlefield()->getRuler() == $faction->getId();
    return array(
      'data' => $ruler ? t('Yes') : t('No'),
      'class' => array('is-boolean', $ruler ? 'is-boolean--true' : 'is-boolean--false'),
    );
  }

  protected function buildPiecesRowData(Faction $faction) {
    if (!$faction->isAlive() || $faction->getStatus() == Faction::STATUS_EMPTY_SLOT || $faction->getControl()->getId() != $faction->getId()) {
      return $this->returnEmptyValue();
    }
    return array(
      'class' => array('is-numeric'),
      'data' => count($faction->getControlledPieces()),
    );
  }

  protected function buildMovesRowData(Faction $faction) {
    if ($faction->getStatus() == Faction::STATUS_VASSALIZED || $faction->getStatus() == Faction::STATUS_EMPTY_SLOT) {
      return $this->returnEmptyValue();
    }
    $moves = 0;
    $total_play_time = 0;
    $turns = 0;
    foreach ($this->getGame()->getBattlefield()->getPastTurns() as $past_turn) {
      if ($past_turn['actingFaction'] == $faction->getId()) {
        $turns++;
        $total_play_time += $past_turn['end'] - $past_turn['start'];
        if (!empty($past_turn['move'])) {
          $moves++;
        }
      }
    }
    $this->factionStats[$faction->getId()] = array(
      'turns' => $turns,
      'playTime' => $total_play_time,
    );
    return array(
      'class' => array('is-numeric'),
      'data' => $moves,
    );
  }

  protected function buildPlayTimeRowData(Faction $faction) {
    if (empty($this->factionStats[$faction->getId()]['playTime'])) {
      return $this->returnEmptyValue();
    }
    return array(
      'class' => array('is-numeric'),
      'data' => \Drupal::service('date')->formatInterval($this->factionStats[$faction->getId()]['playTime']),
    );
  }

  protected function buildAvgPlayTimeRowData(Faction $faction) {
    if (empty($this->factionStats[$faction->getId()]['turns'])) {
      return $this->returnEmptyValue();
    }
    return array(
      'class' => array('is-numeric'),
      'data' => \Drupal::service('date')->formatInterval($this->factionStats[$faction->getId()]['playTime'] / $this->factionStats[$faction->getId()]['turns']),
    );
  }

}
