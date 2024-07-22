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
    $sql = "SELECT player_color FROM player WHERE player_id = $player_id";
    $result = $this->game->getObjectFromDatabase($sql);
    return $result['player_color'];
  }

  public function testDiscardedChainBreakLockCreate()
  {
    // Clear existing cards
    $this->game->DbQuery("DELETE FROM Cards WHERE player_id IN (" . implode(',', $this->player_ids) . ")");

    // Set up initial hands
    $this->insertPlayerCards($this->player_ids[0], [36, 1, 2, 3, 5, 9]);
    $this->insertPlayerCards($this->player_ids[1], [36, 6, 2, 5]);

    // Note: The actual playing of cards and validation of moves should be done in the main game logic
    // This setup allows for:
    // 1. Both players to play 36 (which should be discarded)
    // 2. Player 1 to play 1, 2, 3 to create a lock
    // 3. Player 2 to attempt playing 6 (should be rejected)
    // 4. Player 2 to attempt playing 2 on Player 1's 2 (should be rejected)
    // 5. Player 2 to play 5 (should be accepted)
  }

  public function testRemovePilesAfterPlayerKnockOut()
  {
    // Clear existing cards
    $this->game->DbQuery("DELETE FROM Cards WHERE player_id IN (" . implode(',', $this->player_ids) . ")");

    // Set up initial hands
    $this->insertPlayerCards($this->player_ids[0], [1, 32, 10, 15, 20, 25, 30]);
    $this->insertPlayerCards($this->player_ids[1], [35, 36, 2, 7, 12, 17, 22]);
    if (isset($this->player_ids[2])) {
      $this->insertPlayerCards($this->player_ids[2], [33, 34, 3, 8, 13, 18, 23]);
    }

    // This setup allows for:
    // 1. Player 1 to play 1, then 32
    // 2. Players 2 and 3 to play their high cards (33, 34, 35, 36) in any order
    // 3. Continued play until all players are unable to make a move
    // 4. Verification of pile removal when players are knocked out
  }

  public function testAbsoluteTie()
  {
    // Clear existing cards
    $this->game->DbQuery("DELETE FROM Cards WHERE player_id IN (" . implode(',', $this->player_ids) . ")");

    // Set up initial hands
    $this->insertPlayerCards($this->player_ids[0], [1, 2, 24, 36]);
    $this->insertPlayerCards($this->player_ids[1], [24, 25, 26, 36]);

    // This setup allows for:
    // 1. Player 1 to play 1, 2, 24
    // 2. Player 2 to play 24, 25, 26
    // 3. Both players to play 36 simultaneously
    // 4. Verification of a tie game end
  }

  public function testTieButWinner()
  {
    // Clear existing cards
    $this->game->DbQuery("DELETE FROM Cards WHERE player_id IN (" . implode(',', $this->player_ids) . ")");

    // Set up initial hands
    $this->insertPlayerCards($this->player_ids[0], [1, 2, 3, 4, 36]);
    $this->insertPlayerCards($this->player_ids[1], [24, 25, 26, 35, 36]);

    // This setup allows for:
    // 1. Player 1 to play 1, 2, 3, 4 to create a lock of 4
    // 2. Player 2 to play 24, 25, 26 to create a lock of 3
    // 3. Both players to play 36 simultaneously
    // 4. Verification that Player 1 wins due to having a larger lock
  }

  public function testQuick4Player()
  {
    // Clear existing cards
    $this->game->DbQuery("DELETE FROM Cards WHERE player_id IN (" . implode(',', $this->player_ids) . ")");

    // Set up initial hands with ascending order
    $this->insertPlayerCards($this->player_ids[0], range(1, 36));
    $this->insertPlayerCards($this->player_ids[1], [2, 11, 21, 31, 35, 22, 23, 24, 25]);
    if (isset($this->player_ids[2])) {
      $this->insertPlayerCards($this->player_ids[2], [3, 12, 22, 32, 34]);
    }
    if (isset($this->player_ids[3])) {
      $this->insertPlayerCards($this->player_ids[3], [4, 13, 23, 33, 35, 32]);
    }

    // This setup allows for:
    // 1. All players to play their cards in ascending order
    // 2. Verification that players are knocked out quickly and correctly
  }

  public function testQuickLock()
  {
    // Clear existing cards
    $this->game->DbQuery("DELETE FROM Cards WHERE player_id IN (" . implode(',', $this->player_ids) . ")");

    // Set up initial hands
    $this->insertPlayerCards($this->player_ids[0], [1, 2, 3, 5, 6, 7]);
    $this->insertPlayerCards($this->player_ids[1], [2, 34, 35, 36, 33, 32]);

    // This setup allows for:
    // 1. Player 1 to play 1, 2, 3 to create a lock
    // 2. Player 2 to play 34, 35, 36 to create another lock
    // 3. Verification of lock animation and rotation
    // 4. Player 2 to attempt playing 2 on Player 1's 2 (should be rejected)
  }
}