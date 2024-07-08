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
    // Retrieve the card_id from the AJAX call
    $card_id = self::getArg("card_id", AT_posint, true);
    $player_id = $this->game->getCurrentPlayerId();
    // Call the selectCard method in your game logic
    $this->game->selectCard($player_id, $card_id);

    self::ajaxResponse();
  }

  // Play card action
  public function playCard()
  {
    self::setAjaxMode();
    $card_id = self::getArg("card_id", AT_posint, true);
    $lock = self::getArg("lock", AT_bool, false);
    $this->game->playCard($card_id, $lock);
    self::ajaxResponse();
  }
}