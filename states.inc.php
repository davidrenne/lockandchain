<?php
// Define game state IDs
if (!defined('ST_BGA_GAME_SETUP')) {
  define('ST_BGA_GAME_SETUP', 1);
}

if (!defined('ST_PLAYER_TURN')) {
  define('ST_PLAYER_TURN', 2);
}

if (!defined('ST_NEXT_PLAYER')) {
  define('ST_NEXT_PLAYER', 3);
}

if (!defined('ST_PLACE_CARD')) {
  define('ST_PLACE_CARD', 4);  // Use the next available number in your sequence
}

if (!defined('ST_PLAYER_TURN')) {
  define('ST_PLAYER_TURN', 5);
}

if (!defined('ST_END_GAME')) {
  define('ST_END_GAME', 99999);
}


$machinestates = array(
  // The initial state. Please do not modify.
  ST_BGA_GAME_SETUP => array(
    "name" => "gameSetup",
    "description" => clienttranslate("Game setup"),
    "type" => "manager",
    "action" => "stGameSetup",
    "transitions" => array("" => ST_PLAYER_TURN)
  ),

  // ST_PLAYER_TURN => array(
  //   "name" => "playerTurn",
  //   "description" => clienttranslate('${actplayer} must play a card'),
  //   "descriptionmyturn" => clienttranslate('${you} must play a card'),
  //   "type" => "activeplayer",
  //   "possibleactions" => array("playCard", "selectCard"),
  //   "transitions" => array("playCard" => ST_NEXT_PLAYER, "selectCard" => ST_PLAYER_TURN)
  // ),

  // Player's turn
  ST_PLAYER_TURN => array(
    "name" => "playerTurn",
    "description" => clienttranslate('${actplayer} must select a card'),
    "descriptionmyturn" => clienttranslate('${you} must select a card'),
    "type" => "activeplayer",
    "possibleactions" => array("selectCard"),
    "transitions" => array("selectCard" => ST_PLACE_CARD)
  ),

  ST_PLACE_CARD => array(
    "name" => "placeCard",
    "description" => clienttranslate('${actplayer} must place the selected card'),
    "descriptionmyturn" => clienttranslate('${you} must place the selected card'),
    "type" => "activeplayer",
    "possibleactions" => array("playCard"),
    "transitions" => array("playCard" => ST_NEXT_PLAYER)
  ),

  // Transition to next player
  ST_NEXT_PLAYER => array(
    "name" => "nextPlayer",
    "description" => clienttranslate('Next player\'s turn'),
    "type" => "game",
    "action" => "stNextPlayer",
    "transitions" => array("nextPlayer" => ST_PLAYER_TURN, "endGame" => ST_END_GAME)
  ),

  // End game
  ST_END_GAME => array(
    "name" => "endGame",
    "description" => clienttranslate("End of game"),
    "type" => "manager",
    "action" => "stEndGame",
    "transitions" => array("" => ST_BGA_GAME_SETUP)
  ),
);