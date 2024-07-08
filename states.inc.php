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

if (!defined('ST_RESOLVE_SELECTIONS')) {
  define('ST_RESOLVE_SELECTIONS', 6);
}

if (!defined('ZOMBIE_PASS')) {
  define('ZOMBIE_PASS', 11);
}

if (!defined('ST_END_GAME')) {
  define('ST_END_GAME', 99);
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

  ST_PLAYER_TURN => array(
    "name" => "playerTurn",
    "description" => clienttranslate('${actplayer} must play a card'),
    "descriptionmyturn" => clienttranslate('${you} must play a card'),
    "type" => "multipleactiveplayer",
    "possibleactions" => array("selectCard", "resolveSelections"),
    "transitions" => array(
      "selectCard" => ST_RESOLVE_SELECTIONS,
      "nextPlayer" => ST_NEXT_PLAYER,
      "zombiePass" => ZOMBIE_PASS,
      "resolveSelections" => ST_RESOLVE_SELECTIONS,
    )
  ),

  ZOMBIE_PASS => array(
    'name' => 'zombiePass',
    'description' => '',
    'type' => 'game',
    'action' => 'stZombiePass',
    'transitions' => array('playerTurn' => ST_PLAYER_TURN, 'endGame' => ST_END_GAME)
  ),

  ST_RESOLVE_SELECTIONS => array(
    "name" => "resolveSelections",
    "description" => "",
    "type" => "game",
    "action" => "stResolveSelections",
    "transitions" => array("nextPlayer" => ST_PLAYER_TURN, "endGame" => ST_END_GAME)
  ),

  ST_NEXT_PLAYER => array(
    "name" => "nextPlayer",
    "description" => "",
    "type" => "game",
    "action" => "stNextPlayer",
    "transitions" => array("playerTurn" => ST_PLAYER_TURN)
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