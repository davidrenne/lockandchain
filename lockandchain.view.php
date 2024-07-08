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
  }
}
