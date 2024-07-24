<?php

/**
 *------
 * BGA framework: © Gregory Isabelli <gisabelli@boardgamearena.com> & Emmanuel Colin <ecolin@boardgamearena.com>
 * Checkers implementation : © <Your name here> <Your email address here>
 *
 * This code has been produced on the BGA studio platform for use on http://boardgamearena.com.
 * See http://en.boardgamearena.com/#!doc/Studio for more information.
 * -----
 *
 * stats.inc.php
 *
 * Checkers game statistics description
 *
 */

/*
    In this file, you are describing game statistics, that will be displayed at the end of the
    game.
    
    There are 2 types of statistics:
    _ table statistics, that are not associated to a specific player (ie: 1 value for each game).
    _ player statistics, that are associated to each players (ie: 1 value for each player in the game).

    Statistics types can be "int" for integer, and "float" for floating point values.
    
    Once you defined your statistics there, you can start using "initStat", "setStat" and "incStat" method
    in your game logic, using statistics names defined below.
    
    !! It is not a good idea to modify this file when a game is running !!
*/

// $stats_type = array(

//   // Statistics global to table
//   "table" => array(
//     "turns_number" => array(
//       "id" => 10,
//       "name" => totranslate("Number of turns"),
//       "type" => "int"
//     ),
//     "men_captured" => array(
//       "id" => 11,
//       "name" => totranslate("Number of men captured"),
//       "type" => "int"
//     ),
//     "kings_captured" => array(
//       "id" => 12,
//       "name" => totranslate("Number of kings captured"),
//       "type" => "int"
//     ),
//     "men_made_king" => array(
//       "id" => 13,
//       "name" => totranslate("Number of men made king"),
//       "type" => "int"
//     ),

//   ),

//   // Statistics existing for each player
//   "player" => array(
//     "turns_number" => array(
//       "id" => 10,
//       "name" => totranslate("Number of turns"),
//       "type" => "int"
//     ),
//     "men_captured" => array(
//       "id" => 11,
//       "name" => totranslate("Number of men captured"),
//       "type" => "int"
//     ),
//     "kings_captured" => array(
//       "id" => 12,
//       "name" => totranslate("Number of kings captured"),
//       "type" => "int"
//     ),
//     "men_made_king" => array(
//       "id" => 13,
//       "name" => totranslate("Number of men made king"),
//       "type" => "int"
//     ),

//   )

// );
