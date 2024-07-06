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

    // Sample code to place player panels
    foreach ($players as $player_id => $info) {
      $this->tpl['PLAYER_PANEL'] .= self::_(
        "{$template}_player_panel.tpl",
        array(
          'PLAYER_ID' => $player_id,
          'PLAYER_NAME' => $info['player_name']
        )
      );
    }

    $this->page->begin_block($template, "player_card");

    foreach ($players as $player_id => $player) {
      $playerCards = $this->game->getPlayerCards($player_id);
      foreach ($playerCards as $card) {
        $this->page->insert_block(
          "player_card",
          array(
            "CARD_ID" => $card['id'],
            "CARD_COLOR" => $card['color'],
            "CARD_NUMBER" => $card['number']
          )
        );
      }
    }
  }
}
?>