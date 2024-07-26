<?php
// Define game state IDs
if (!defined('ST_BGA_GAME_SETUP')) {
  define('ST_BGA_GAME_SETUP', 1);
}



if (!defined('ST_MULTIPLAYER_SELECT_CARDS')) {
  define('ST_MULTIPLAYER_SELECT_CARDS', 2);
}

if (!defined('ST_RESOLVE_SELECTIONS')) {
  define('ST_RESOLVE_SELECTIONS', 3);
}

$machinestates = array(
  // The initial state. Please do not modify.
  ST_BGA_GAME_SETUP => array(
    "name" => "gameSetup",
    "description" => clienttranslate("Game setup"),
    "type" => "manager",
    "action" => "stGameSetup",
    "transitions" => array("" => ST_MULTIPLAYER_SELECT_CARDS)
  ),

  // New state for simultaneous selection
  ST_MULTIPLAYER_SELECT_CARDS => [
    'name' => 'selectCards',
    "args" => "argMultiPlayerTurn",
    'description' => clienttranslate('Waiting for players to select cards'),
    'type' => 'multipleactiveplayer',
    'possibleactions' => ['selectCard', 'resolveSelections'],
    'transitions' => [
      'resolve' => ST_RESOLVE_SELECTIONS, // Transition to the resolve state
    ],
  ],


  ST_RESOLVE_SELECTIONS => array(
    "name" => "resolveSelections",
    "description" => "",
    "type" => "game",
    "action" => "stResolveSelections",
    'updateGameProgression' => true,
    "possibleactions" => array("gameEnd", "multiplayerSelectCards"),
    "transitions" => array(
      'multiplayerSelectCards' => ST_MULTIPLAYER_SELECT_CARDS,
      "gameEnd" => 99,
      "zombiePass" => 2
    )
  ),

  99 => array(
    "name" => "gameEnd",
    "description" => clienttranslate("End of game"),
    "type" => "manager",
    "action" => "stEndGame",
    'args' => 'argGameEnd'
  )
);