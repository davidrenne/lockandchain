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
    // Load the template
    $this->tpl['PAGE_NAME'] = 'lockandchain_lockandchain';
    $this->page->begin_block($this->tpl['PAGE_NAME'], "grid_row");
    $this->page->begin_block($this->tpl['PAGE_NAME'], "grid_cell");
    $this->page->begin_block($this->tpl['PAGE_NAME'], "player_card");

    global $g_user;
    $players = $this->game->loadPlayersBasicInfos();

    // Generate the 6x6 grid
    for ($row = 0; $row < 6; $row++) {
      for ($col = 0; $col < 6; $col++) {
        $cell_id = $row * 6 + $col + 1;
        $this->page->insert_block(
          "grid_cell",
          array(
            'ROW' => $row,
            'COL' => $col,
            'CELL_ID' => str_pad($cell_id, 3, '0', STR_PAD_LEFT)
          )
        );
      }
      $this->page->insert_block("grid_row");
    }

    // Generate player hands
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