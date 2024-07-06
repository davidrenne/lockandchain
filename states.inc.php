<?php
// Define game state IDs
define("ST_BGA_GAME_SETUP", 1);
define("ST_PLAYER_TURN", 2);
define("ST_NEXT_PLAYER", 3);
define("ST_END_GAME", 99);

$machinestates = array(
  // The initial state. Please do not modify.
  ST_BGA_GAME_SETUP => array(
    "name" => "gameSetup",
    "description" => clienttranslate("Game setup"),
    "type" => "manager",
    "action" => "stGameSetup",
    "transitions" => array("" => ST_PLAYER_TURN)
  ),

  // Player's turn
  ST_PLAYER_TURN => array(
    "name" => "playerTurn",
    "description" => clienttranslate('${actplayer} must play a card'),
    "descriptionmyturn" => clienttranslate('${you} must play a card'),
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