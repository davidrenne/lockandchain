<?php
class LockAndChainTestScenarios
{
  private $game;
  private $player_ids;

  public function __construct($game)
  {
    $this->game = $game;
    $players = $this->game->loadPlayersBasicInfos();
    $this->player_ids = array_keys($players);
  }

  private function insertPlayerCards($player_id, $cards)
  {
    foreach ($cards as $card) {
      $this->game->DbQuery("INSERT INTO Cards (card_type, card_type_arg, card_location, player_id, card_location_arg) 
                VALUES ('{$this->getPlayerColor($player_id)}', $card, 'deck', $player_id, 0)");
    }
  }

  private function getPlayerColor($player_id)
  {
    $colors = array(
      1 => 'blue',   // Blue
      2 => 'green',  // Green
      3 => 'purple', // Purple
      4 => 'red'     // Red
    );
    return $colors[($player_id % 4) + 1];

  }

  public function testDiscardedChainBreakLockCreate()
  {
    $this->game->DbQuery("DELETE FROM Cards WHERE player_id IN (" . implode(',', $this->player_ids) . ")");
    $this->insertPlayerCards($this->player_ids[0], [36, 1, 2, 3, 5, 9]);
    $this->insertPlayerCards($this->player_ids[1], [36, 6, 2]);
  }

  public function testRemovePilesAfterPlayerKnockOut()
  {
    $this->game->DbQuery("DELETE FROM Cards WHERE player_id IN (" . implode(',', $this->player_ids) . ")");
    $this->insertPlayerCards($this->player_ids[0], [1, 32, 10, 15, 20, 25, 30]);
    $this->insertPlayerCards($this->player_ids[1], [35, 36, 2, 7, 12, 17, 22]);
    if (isset($this->player_ids[2])) {
      $this->insertPlayerCards($this->player_ids[2], [33, 34, 3, 8, 13, 18, 23]);
    }
  }

  public function testAbsoluteTie()
  {
    $this->game->DbQuery("DELETE FROM Cards WHERE player_id IN (" . implode(',', $this->player_ids) . ")");
    $this->insertPlayerCards($this->player_ids[0], [1, 2, 24, 36]);
    $this->insertPlayerCards($this->player_ids[1], [24, 25, 26, 36]);
  }

  public function testTieButWinner()
  {
    $this->game->DbQuery("DELETE FROM Cards WHERE player_id IN (" . implode(',', $this->player_ids) . ")");
    $this->insertPlayerCards($this->player_ids[0], [1, 2, 3, 4, 36]);
    $this->insertPlayerCards($this->player_ids[1], [24, 25, 26, 36]);
  }

  public function testQuick4Player()
  {
    $this->game->DbQuery("DELETE FROM Cards WHERE player_id IN (" . implode(',', $this->player_ids) . ")");
    $this->insertPlayerCards($this->player_ids[0], [1, 10, 20, 30, 36]);
    $this->insertPlayerCards($this->player_ids[1], [2, 11, 21, 31, 35]);
    if (isset($this->player_ids[2])) {
      $this->insertPlayerCards($this->player_ids[2], [3, 12, 22, 32, 34]);
    }
    if (isset($this->player_ids[3])) {
      $this->insertPlayerCards($this->player_ids[3], [4, 13, 23, 33, 35]);
    }
  }

  public function testQuickLock()
  {
    $this->game->DbQuery("DELETE FROM Cards WHERE player_id IN (" . implode(',', $this->player_ids) . ")");
    $this->insertPlayerCards($this->player_ids[0], [1, 2, 3, 5, 6, 7]);
    $this->insertPlayerCards($this->player_ids[1], [2, 34, 35, 36, 33, 32]);
  }
}