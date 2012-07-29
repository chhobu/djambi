<?php
/**
 * Validation de la sélection d'une pièce
 */
function kw_djambi_piece_selection_validate(&$errors, $piece_id, DjambiBattlefield $grid) {
  global $user;
  $errors = array();
  $piece_form_locator = "pieces[movable][" . $piece_id;
  $faction = $grid->getPlayingFaction();
  $is_current_user = _kw_djambi_check_current_user($faction);
  if (!$is_current_user) {
    $errors[] = array(
        "location" => $piece_form_locator,
        "msg" => t("You do not own the !faction pieces.", array("!faction", $faction->getName("t")))
    );
    return FALSE;
  }
  $pieces = $faction->getControlledPieces();
  if (!isset($pieces[$piece_id])) {
    $errors[] =  array(
        "location" => $piece_form_locator,
        "msg" => t("Unrecognized piece.")
    );
    return FALSE;
  }
  $piece = $pieces[$piece_id];
  if (!$piece->isMovable()) {
    $errors = array(
        "location" => $piece_form_locator,
        "msg" => t("The piece !piece cannot move currently.", array("!piece" => $piece->getLongname("t")))
    );
  }
  if (!empty($errors)) {
    return FALSE;
  }
  return $piece;
}

/**
 * Validation du déplacement d'une pièce
 */
function kw_djambi_piece_destination_validate(&$errors, DjambiPiece $piece, $destination, $button_name) {
  $move = FALSE;
  foreach($piece->getAllowableMoves() as $allowable_move) {
    if($destination == $allowable_move) {
      $move = $destination;
      break;
    }
  }
  if (!$move && !is_null($button_name)) {
    $errors[] = array(
        "location" => "pieces[destination][" . $button_name,
        "msg" => t("You are not allowed to move !piece into !case case.", array(
            "!piece" => $piece->getLongname("t"), "!case" => $destination))
    );
  }
  return $move;
}

/**
 * Validation du meurtre d'une pièce
 */
function kw_djambi_piece_murder_validate(&$errors, DjambiBattlefield $grid, DjambiPiece $murderer, DjambiPiece $victim, $destination, $button_name) {
  if (!$victim->isAlive()) {
    $errors[] = array(
        "location" => "pieces[destination][" . $button_name,
        "msg" => t("Your victim seems to be already dead.")
    );
    return FALSE;
  }
  $can_attack = $murderer->checkAttackingPossibility($victim);
  if (!$can_attack) {
    $errors[] = array(
        "location" => "pieces[destination][" . $button_name,
        "msg" => t("You are not allowed to kill this piece.")
    );
    return FALSE;
  }
  $free_cells = $grid->getFreeCells($victim);
  if (!in_array($destination, $free_cells)) {
    $errors[] = array(
        "location" => "pieces[destination][" . $button_name,
        "msg" => t("You cannot place bury your victim here, case !case is unavailable.", array("!case" => $destination))
    );
    return FALSE;
  }
  return TRUE;
}

/**
 * Validation du reportage sur une pièce
 */
function kw_djambi_piece_reportage_validate(&$errors, DjambiBattlefield $grid, DjambiPiece $piece, $destination, $button_name) {
  $cells = $grid->getCells();
  if (!isset($cells[$destination])) {
    $errors[] = array(
        "location" => "pieces[destination][" . $button_name,
        "msg" => t("The reportage location is totally incorrect.")
    );
    return FALSE;
  }
  if (empty($cells[$destination]["occupant"])) {
    $errors[] = array(
        "location" => "pieces[destination][" . $button_name,
        "msg" => t("There is nobody here (!case).", array("!case" => $destination))
    );
    return FALSE;
  }
  $occupant = $cells[$destination]["occupant"];
  if (!$occupant->isAlive()) {
    $errors[] = array(
        "location" => "pieces[destination][" . $button_name,
        "msg" => t("Already dead, too late for a reportage.")
    );
    return FALSE;
  }
  if ($grid->getOption('rule_press_liberty') == 'pravda'
      && $occupant->getFaction()->getControl()->getId() == $piece->getFaction()->getControl()->getId()) {
    $errors[] = array(
        "location" => "pieces[destination][" . $button_name,
        "msg" => t("It is not a good idea to make a reportage about your own team evil actions.")
    );
    return FALSE;
  }
  return TRUE;
}

/**
 * Validation de la manipulation d'une pièce
 */
function kw_djambi_piece_manipulation_validate(&$errors, DjambiBattlefield $grid, DjambiPiece $diplomat, DjambiPiece $manipulated, $destination, $button_name) {
  if (!$manipulated->isAlive()) {
    $errors[] = array(
        "location" => "pieces[destination][" . $button_name,
        "msg" => t("You cannot manipulate a dead piece (use a necromobil for this disgusting work).")
    );
    return FALSE;
  }
  $can_manipulate = $diplomat->checkManipulatingPossibility($manipulated);
  if(!$can_manipulate) {
    $errors[] = array(
        "location" => "pieces[destination][ " . $button_name,
        "msg" => t("You cannot manipulate this piece.")
    );
    return FALSE;
  }
  $free_cells = $grid->getFreeCells($manipulated);
  if (!in_array($destination, $free_cells)) {
    $errors[] = array(
        "location" => "pieces[destination][" . $button_name,
        "msg" => t("You cannot place the manipulated piece here, case !case is unavailable.", array("!case" => $destination))
    );
    return FALSE;
  }
  return TRUE;
}

/**
 * Validation du déplacement d'un mort
 */
function kw_djambi_piece_necromobility_validate(&$errors, DjambiBattlefield $grid, DjambiPiece $piece, $destination, $button_name) {
  if ($piece->isAlive()) {
    $errors[] = array(
        "location" => "pieces[destination][" . $button_name,
        "msg" => t("This dead body is still breathing...")
    );
    return FALSE;
  }
  $free_cells = $grid->getFreeCells($piece);
  if (!in_array($destination, $free_cells)) {
    $errors[] = array(
        "location" => "pieces[destination][" . $button_name,
        "msg" => t("You cannot place the dead piece here, case !case is unavailable.", array("!case" => $destination))
    );
    return FALSE;
  }
  return TRUE;
}

/**
 * Validation de l'évacuation d'un pion
 */
function kw_djambi_piece_evacuation_validate(&$errors, DjambiBattlefield $grid, DjambiPiece $piece, $destination, $button_name) {
  return kw_djambi_piece_destination_validate($errors, $piece, $destination, $button_name);
}