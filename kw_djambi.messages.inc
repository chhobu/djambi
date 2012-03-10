<?php
function _kw_djambi_get_translatable_messages($message, $args = NULL) {
  switch($message) {
    case("throne access"):
      $txt = t("!piece is now the great and beloved ruler of the djambi world.", $args);
    break;
    case("throne retreat"):
      $txt = t("!piece's reign of despotism and terror is now over.", $args);
    break;
    case("throne evacuation"):
      $txt= t("!piece body has been exhumed from his mausoleum.", $args);
    break;
    case("NEW_DJAMBI_GAME"):
      $txt = t("New djambi chess game created.");
      break;
    case("SURROUNDED"):
      $txt = t("<span class='faction !class'>!!faction</span>'s side is surrounded by dead pieces and cannot access to power anymore.", $args);
      break;
    case("END"):
      $txt = t("End of the game !");
      break;
    case("DRAW"):
      $txt = t("This is a draw.");
      break;
    case("THE_WINNER_IS"):
      $txt = t("<span class='faction !class'>!!faction</span> wins !!!", $args);
      break;
    case("TURN_BEGIN"):
      $txt = t("<span class='faction !class'>!!faction</span> turn begins.", $args);
      break;
    case("NEW_TURN"):
      $txt = t("Turn !turn begins.", $args);
      break;
    case(KW_DJAMBI_MODE_SANDBOX):
      $txt = t("Sandbox");
      break;
    case(KW_DJAMBI_STATUS_PENDING):
      $txt = t("Game in progress...");
      break;
    case(KW_DJAMBI_MODE_HOTCHAIR):
      $txt = t("Hotchair game");
      break;
    case(KW_DJAMBI_MODE_ROULETTE):
      $txt = t("Open internet game");
      break;
    case(KW_DJAMBI_MODE_VIP):
      $txt = t("Invitation internet game");
      break;
    case(KW_DJAMBI_STATUS_FINISHED):
      $txt = t("Game over !");
      break;
    case("Sniper"):
      $txt = t("Sniper");
      break;
    case("Leader"):
      $txt = t("Leader");
      break;
    case("Necromobil"):
      $txt = t("Necromobil");
      break;
    case("Diplomat"):
      $txt = t("Diplomat");
      break;
    case("Reporter"):
      $txt = t("Reporter");
      break;
    case("Leader"):
      $txt = t("Leader");
      break;
    case("Militant #1"):
      $txt = t("Militant #1");
      break;
    case("Militant #2"):
      $txt = t("Militant #2");
      break;
    case("Militant #3"):
      $txt = t("Militant #3");
      break;
    case("CHANGING_SIDE"):
      $txt = t("The desperate remaining <span class='faction !old_class'>!!old_faction</span> side's partisans are now joining the <span class='faction !new_class'>!!new_faction</span> side !", $args);
      break;
    case("GAME_OVER"):
      $txt = t("Game over for <span class='faction !class'>!!faction</span> side !", $args);
      break;
    case("Red"):
      $txt = t("Red");
      break;
    case("Blue"):
      $txt = t("Blue");
      break;
    case("Yellow"):
      $txt = t("Yellow");
      break;
    case("Green"):
      $txt = t("Green");
      break;
    /*case(""):
      $txt = t("");
      break;*/
    default:
      $txt = $message;
  }
  return $txt;
}
