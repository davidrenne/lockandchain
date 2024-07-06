<?php
$machinestates = array(
  // Initial state
  ST_BGA_GAME_SETUP => array(
    'name' => 'gameSetup',
    'description' => '',
    'type' => 'manager',
    'action' => 'stGameSetup',
    'transitions' => array('' => ST_MULTIPLAYER)
  ),

  // Player turn state
  ST_MULTIPLAYER => array(
    'name' => 'playerTurn',
    'description' => clienttranslate('${actplayer} must play a card'),
    'descriptionmyturn' => clienttranslate('${you} must play a card'),
    'type' => 'activeplayer',
    'possibleactions' => array('playCard'),
    'transitions' => array('playCard' => ST_NEXT_PLAYER)
  ),

  // Next player state
  ST_NEXT_PLAYER => array(
    'name' => 'nextPlayer',
    'description' => '',
    'type' => 'game',
    'action' => 'stNextPlayer',
    'transitions' => array('nextTurn' => ST_MULTIPLAYER, 'endGame' => ST_GAME_END)
  ),

  // End game state
  ST_GAME_END => array(
    'name' => 'gameEnd',
    'description' => clienttranslate('End of game'),
    'type' => 'manager',
    'action' => 'stGameEnd',
    'args' => 'argGameEnd'
  )
);
?>