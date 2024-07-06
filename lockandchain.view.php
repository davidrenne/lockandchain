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
    error_log("Starting build_page method");
    // Extract the template content
    $this->tpl['PAGE_NAME'] = 'lockandchain_lockandchain';
    error_log("Template name: " . $this->tpl['PAGE_NAME']);

    // Generate the board
    $this->page->begin_block($this->tpl['PAGE_NAME'], "board");
    for ($i = 1; $i <= 36; $i++) {
      $this->page->insert_block(
        "board",
        array(
          'CELL_ID' => str_pad($i, 3, '0', STR_PAD_LEFT)
        )
      );
    }

    // Generate player hands
    $players = $this->game->loadPlayersBasicInfos();
    $this->page->begin_block($this->tpl['PAGE_NAME'], "player_card");
    foreach ($players as $player_id => $info) {
      $cards = $this->game->getPlayerCards($player_id);
      foreach ($cards as $card) {
        $this->page->insert_block(
          "player_card",
          array(
            'CARD_ID' => $card['card_id'],
            'CARD_COLOR' => $card['card_type'],
            'CARD_NUMBER' => str_pad($card['card_type_arg'], 2, '0', STR_PAD_LEFT)
          )
        );
      }
    }
  }
}