<?php
class action_lockandchain extends APP_GameAction
{
  // Constructor: please do not modify
  public function __default()
  {
    if (self::isArg('notifwindow')) {
      $this->view = "common_notifwindow";
      $this->viewArgs['table'] = self::getArg("table", AT_posint, true);
    } else {
      $this->view = "lockandchain_lockandchain";
      self::trace("Complete reinitialization of board game");
    }
  }

  public function selectCard()
  {
    self::setAjaxMode();
    // Retrieve the card_id and player_id from the AJAX call
    $card_id = self::getArg("card_id", AT_posint, true);
    $player_id = $this->game->getCurrentPlayerId(); // Get the current player ID

    // Call the selectCard method in your game logic
    $this->game->selectCard($player_id, $card_id);

    // Check if all players have selected a card
    if ($this->allPlayersHaveSelected()) {
      $this->game->resolveSelections();
    } else {
      $this->game->nextPlayer();
    }

    self::ajaxResponse();
  }

  public function getPlayerHand()
  {
    self::setAjaxMode();

    $player_id = self::getArg("player_id", AT_posint, true);
    $result = $this->game->getCards($player_id, "hand", 7);
    self::ajaxResponse($result);
  }

  private function allPlayersHaveSelected()
  {
    $players = self::getCollectionFromDB("SELECT player_id FROM player WHERE player_eliminated = 0");
    $selections = self::getCollectionFromDB("SELECT player_id FROM PlayerSelections");
    return count($players) === count($selections);
  }
}