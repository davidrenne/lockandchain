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

  ST_PLACE_CARD => array(
    "name" => "placeCard",
    "description" => clienttranslate('${actplayer} must place the selected card'),
    "descriptionmyturn" => clienttranslate('${you} must place the selected card'),
    "type" => "activeplayer",
    "possibleactions" => array("playCard"),
    "transitions" => array("playCard" => ST_NEXT_PLAYER)
  ),

  ST_PLAYER_TURN => array(
    "name" => "playerTurn",
    "description" => clienttranslate('${actplayer} must select a card'),
    "descriptionmyturn" => clienttranslate('${you} must select a card'),
    "type" => "activeplayer",
    "possibleactions" => array("selectCard"),
    "transitions" => array(
      "selectCard" => ST_PLAYER_TURN,
      "resolveSelections" => ST_RESOLVE_SELECTIONS
    )
  ),

  ST_RESOLVE_SELECTIONS => array(
    "name" => "resolveSelections",
    "description" => clienttranslate('Resolving card selections'),
    "type" => "game",
    "action" => "stResolveSelections",
    "transitions" => array("" => ST_NEXT_PLAYER)
  ),

  // Define the zombie pass state
  'zombiePass' => array(
    'name' => 'zombiePass',
    'description' => '',
    'type' => 'game',
    'action' => 'stZombiePass',
    'transitions' => array('playerTurn' => ST_PLAYER_TURN, 'endGame' => ST_END_GAME)
  ),

  // Transition to next player
  ST_NEXT_PLAYER => array(
    "name" => "nextPlayer",
    "description" => clienttranslate('Next player must play a card'),
    "type" => "game",
    "action" => "stNextPlayer",
    "transitions" => array("playerTurn" => ST_PLAYER_TURN, "endGame" => ST_END_GAME),
  ),

  // End game
  ST_END_GAME => array(
    "name" => "endGame",
    "description" => clienttranslate("End of game"),
    "type" => "manager",
    "action" => "stEndGame",
    "transitions" => array()
  ),
);