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

  // Play card action
  public function playCard()
  {
    self::setAjaxMode();
    $card_id = self::getArg("id", AT_posint, true);
    $cell_id = self::getArg("cell_id", AT_posint, true); // Add cell_id to AJAX call
    $this->game->playCard($card_id, $cell_id);
    self::ajaxResponse();
  }
}