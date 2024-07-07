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
    $this->tpl['PAGE_NAME'] = 'lockandchain_lockandchain';

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

    // Generate only the current player's hand
    $current_player_id = $this->game->getAllDatas()['current_player_id'];
    $player = $this->game->loadPlayersBasicInfos()[$current_player_id];
    $cards = $this->game->getPlayerCards($current_player_id);

    $this->page->begin_block($this->tpl['PAGE_NAME'], "player_hand");
    $player_cards = '';
    foreach ($cards as $card) {
      $player_cards .= $this->page->insert_block("player_card", array(
        'CARD_ID' => $card['card_id'],
        'CARD_COLOR' => $card['card_type'],
        'CARD_NUMBER' => str_pad($card['card_type_arg'], 2, '0', STR_PAD_LEFT)
      ), true);
    }
    $this->page->insert_block(
      "player_hand",
      array(
        'PLAYER_ID' => $current_player_id,
        'PLAYER_NAME' => $player['player_name'],
        'PLAYER_CARDS' => $player_cards
      )
    );
  }
}
