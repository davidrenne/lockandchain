<?php

require_once (APP_BASE_PATH . "view/common/game.view.php");

class view_lockandchain_lockandchain extends game_view
{
  function getGameName()
  {
    return "lockandchain";
  }

  function build_page($viewArgs)
  {
    // Custom view logic here
    global $g_user;
    $players = $this->game->loadPlayersBasicInfos();
    $template = self::getGameName() . "_" . self::getGameName();

    // Ensure PLAYER_PANEL is initialized
    if (!isset($this->tpl['PLAYER_PANEL'])) {
      $this->tpl['PLAYER_PANEL'] = '';
    }

    // Begin block for player cards in hand
    $this->page->begin_block($template, "player_card");

    // Sample code to place player cards
    foreach ($players as $player_id => $info) {
      $cards = $this->game->getPlayerCards($player_id);
      foreach ($cards as $card) {
        $this->page->insert_block(
          "player_card",
          array(
            'CARD_ID' => $card['id'],
            'CARD_COLOR' => $card['color'],
            'CARD_NUMBER' => $card['number']
          )
        );
      }
    }

    // Ensure PLAYER_BOARD is initialized
    if (!isset($this->tpl['PLAYER_BOARD'])) {
      $this->tpl['PLAYER_BOARD'] = '';
    }

    // Adding the player boards to the main container
    foreach ($players as $player_id => $info) {
      $this->tpl['PLAYER_BOARD'] .= '<div id="player_board_' . $player_id . '" class="player_board"></div>';
    }

    // Insert player boards
    $this->page->insert_block("player_board", array());
  }
}

?>