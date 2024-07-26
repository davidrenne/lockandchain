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

    try {
      // Get the card details
      $card = $this->game->getObjectFromDB("SELECT * FROM Cards WHERE card_id = $card_id AND card_location = 'hand' AND player_id = $player_id");

      if (!$card) {
        throw new BgaUserException(clienttranslate("You don't have this card in your hand"));
      }

      // Validate the card play
      $this->game->validateCardPlay($player_id, $card_id, $card['card_type_arg']);

      // If validation passes, proceed with selection
      $this->game->selectCard($player_id, $card_id);
      $this->game->notifyPlayer($player_id, 'selectionSuccess', '', array());
    } catch (BgaUserException $e) {
      $this->game->notifyPlayer(
        $player_id,
        'invalidSelection',
        '',
        array(
          'message' => $e->getMessage()
        )
      );
    }

    self::ajaxResponse();
  }

  public function updateStats()
  {
    self::setAjaxMode();
    $this->game->notifyPlayerStats();
    $this->game->notifyRoundCounts();
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