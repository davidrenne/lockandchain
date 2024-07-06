<?php
class view_lockandchain_lockandchain extends game_view
{
  function getGameName()
  {
    return "lockandchain";
  }

  function build_page($viewArgs)
  {
    $players = $this->game->loadPlayersBasicInfos();
    $template = self::getGameName() . "_" . self::getGameName();
    $this->page->begin_block($template, "player_hand");
    $this->page->begin_block($template, "player_card");
    $this->page->begin_block($template, "grid_row");
    $this->page->begin_block($template, "grid_cell");

    // Player hands
    foreach ($players as $player_id => $player) {
      $this->page->insert_block(
        "player_hand",
        array(
          "PLAYER_ID" => $player_id,
          "PLAYER_NAME" => $player['player_name']
        )
      );
    }

    // Board
    for ($row = 1; $row <= 6; $row++) {
      for ($col = 1; $col <= 6; $col++) {
        $cellId = ($row - 1) * 6 + $col;
        $this->page->insert_block(
          "grid_cell",
          array(
            "ROW" => $row,
            "COL" => $col,
            "CELL_ID" => $cellId
          )
        );
      }
    }
  }
}