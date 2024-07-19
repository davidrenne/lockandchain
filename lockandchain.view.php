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
      // Query to get the top card for this position
      $topCard = self::getObjectFromDB("
        SELECT cp.*, c.card_type as card_color
        FROM CardPlacements cp
        LEFT JOIN Cards c ON cp.card_id = c.card_id
        WHERE cp.card_number = $i
        AND c.card_location = 'board'
        ORDER BY cp.position DESC
        LIMIT 1
      ");
      $img = "";
      if ($topCard) {
        // A card has been placed on this position
        $cardNumber = str_pad($topCard['card_number'], 2, '0', STR_PAD_LEFT);
        $img = "https://studio.boardgamearena.com:8084/data/themereleases/current/games/lockandchain/999999-9999/img/lockandchainnumbers_{$topCard['card_color']}_{$cardNumber}.png";
      } else {
        // No card placed, use the default board image
        $cardNumber = str_pad($i, 3, '0', STR_PAD_LEFT);
        $img = "https://studio.boardgamearena.com:8084/data/themereleases/current/games/lockandchain/999999-9999/img/lockandchainnumbers_board_{$cardNumber}.png";
      }

      $this->page->insert_block(
        "board",
        array(
          'CELL_ID' => str_pad($i, 3, '0', STR_PAD_LEFT),
          'IMG' => $img
        )
      );
    }
  }
}
