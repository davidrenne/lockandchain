<?php

require_once (APP_GAMEMODULE_PATH . 'module/table/table.game.php');

class LockAndChain extends Table
{
  function __construct()
  {
    parent::__construct();
    self::initGameStateLabels(
      array(
        "currentTurn" => 10,
        // Other game state labels
      )
    );
  }

  protected function getGameName()
  {
    // Used for translations and stuff. Please do not modify.
    return "lockandchain";
  }

  // Setup new game
  protected function setupNewGame($players, $options = array())
  {
    // Create necessary tables using structured DbQuery calls
    self::DbQuery("
            CREATE TABLE IF NOT EXISTS `Cards` (
                `card_id` INT AUTO_INCREMENT PRIMARY KEY,
                `card_type` VARCHAR(32) NOT NULL,
                `card_type_arg` INT NOT NULL,
                `card_location` VARCHAR(32) NOT NULL,
                `card_location_arg` INT NOT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
        ");

    self::DbQuery("
            CREATE TABLE IF NOT EXISTS `PlayerHands` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `player_id` INT(10) UNSIGNED,
                `card_id` INT,
                FOREIGN KEY (`player_id`) REFERENCES `player`(`player_id`),
                FOREIGN KEY (`card_id`) REFERENCES `Cards`(`card_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
        ");

    self::DbQuery("
            CREATE TABLE IF NOT EXISTS `CardPlacements` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `game_id` INT,
                `card_id` INT,
                `player_id` INT(10) UNSIGNED,
                `position` INT,
                FOREIGN KEY (`card_id`) REFERENCES `Cards`(`card_id`),
                FOREIGN KEY (`player_id`) REFERENCES `player`(`player_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
        ");

    self::DbQuery("
            CREATE TABLE IF NOT EXISTS `Chains` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `player_id` INT(10) UNSIGNED,
                `start_position` INT,
                `end_position` INT,
                FOREIGN KEY (`player_id`) REFERENCES `player`(`player_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
        ");

    self::DbQuery("
            CREATE TABLE IF NOT EXISTS `Locks` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `player_id` INT(10) UNSIGNED,
                `start_position` INT,
                `end_position` INT,
                FOREIGN KEY (`player_id`) REFERENCES `player`(`player_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
        ");

    self::DbQuery("
            CREATE TABLE IF NOT EXISTS `GameActions` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `game_id` INT,
                `player_id` INT(10) UNSIGNED,
                `action_type` VARCHAR(50),
                `card_id` INT,
                `timestamp` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (`player_id`) REFERENCES `player`(`player_id`),
                FOREIGN KEY (`card_id`) REFERENCES `Cards`(`card_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
        ");

    // Initialize players
    $sql = "INSERT INTO player (player_id, player_name, player_color, player_canal, player_avatar) VALUES ";
    $values = array();
    foreach ($players as $player_id => $player) {
      $color = $this->getPlayerColor($player_id);
      $values[] = "($player_id, '" . addslashes($player['player_name']) . "', '$color', '', '')";
    }
    $sql .= implode(',', $values);
    self::DbQuery($sql);

    // Initialize decks and cards
    $this->initDecks();

    // Set up game state
    $this->setGameStateInitialValue('currentTurn', 0);

    $this->activeNextPlayer();
  }

  public function getPlayerCards($player_id)
  {
    return self::getObjectListFromDB("SELECT c.* FROM Cards c JOIN PlayerHands ph ON c.card_id = ph.card_id WHERE ph.player_id = $player_id");
  }

  // Initialize decks 
  private function initDecks()
  {
    // Each player gets a deck of cards 1-36
    $cards = array();
    $players = $this->loadPlayersBasicInfos(); // Use loadPlayersBasicInfos() instead of getPlayers()
    for ($i = 1; $i <= 36; $i++) {
      foreach ($players as $player_id => $player) {
        $cards[] = array(
          'card_type' => 'card',
          'card_type_arg' => $i,
          'card_location' => 'deck',
          'card_location_arg' => $player_id
        );
      }
    }

    // Insert cards into Cards table
    foreach ($cards as $card) {
      $sql = "INSERT INTO Cards (card_type, card_type_arg, card_location, card_location_arg) 
                VALUES ('{$card['card_type']}', {$card['card_type_arg']}, '{$card['card_location']}', {$card['card_location_arg']})";
      self::DbQuery($sql);
    }

    // Shuffle the deck
    $this->shuffleDeck();
  }

  private function shuffleDeck()
  {
    // Shuffle the deck by updating card_location_arg with random values
    $sql = "UPDATE Cards SET card_location_arg = FLOOR(1 + RAND() * 100)";
    self::DbQuery($sql);
  }

  // Handle player actions
  public function playCard($card_id, $cell_id, $lock = false)
  {
    $player_id = self::getActivePlayerId();

    $card = self::getObjectFromDB("SELECT * FROM Cards WHERE card_id = $card_id");
    $card_value = $card['card_type_arg'];
    $card_color = $card['card_type'];

    // Validate the move
    $this->validateCardPlay($player_id, $card_id, $cell_id);

    // Update the game state
    self::DbQuery("UPDATE Cards SET card_location = 'board', card_location_arg = $cell_id WHERE card_id = $card_id");
    self::DbQuery("DELETE FROM PlayerHands WHERE card_id = $card_id");
    self::DbQuery("INSERT INTO CardPlacements (game_id, card_id, player_id, position) VALUES ({$this->getGameId()}, $card_id, $player_id, $cell_id)");

    // Check for chains and locks
    $this->checkChainsAndLocks($player_id, $card_id, $cell_id, $lock);

    // Notify players
    self::notifyAllPlayers(
      'cardPlayed',
      clienttranslate('${player_name} plays ${card_value} on cell ${cell_id}'),
      array(
        'player_id' => $player_id,
        'player_name' => self::getActivePlayerName(),
        'card_id' => $card_id,
        'card_value' => $card_value,
        'card_color' => $card_color,
        'cell_id' => $cell_id,
        'lock' => $lock
      )
    );

    // Check for game end conditions
    if ($this->checkEndGame()) {
      $this->gamestate->nextState('endGame');
    } else {
      // Move to the next player
      $this->gamestate->nextState('nextPlayer');
    }
  }

  private function validateCardPlay($player_id, $card_id, $cell_id)
  {
    // Check if the card belongs to the player
    $card = self::getObjectFromDB("SELECT * FROM PlayerHands WHERE card_id = $card_id AND player_id = $player_id");
    if (!$card) {
      throw new BgaUserException(self::_("You don't have this card in your hand"));
    }

    // Check if the cell is empty
    $existing_card = self::getObjectFromDB("SELECT * FROM CardPlacements WHERE position = $cell_id");
    if ($existing_card) {
      throw new BgaUserException(self::_("This cell is already occupied"));
    }

    // Check for chains and locks
    $chains = self::getObjectListFromDB("SELECT * FROM Chains WHERE player_id != $player_id");
    $locks = self::getObjectListFromDB("SELECT * FROM Locks");

    foreach ($chains as $chain) {
      if ($cell_id > $chain['start_position'] && $cell_id < $chain['end_position']) {
        throw new BgaUserException(self::_("You cannot play within another player's chain"));
      }
    }

    foreach ($locks as $lock) {
      if ($cell_id >= $lock['start_position'] && $cell_id <= $lock['end_position']) {
        throw new BgaUserException(self::_("You cannot play on a locked position"));
      }
    }
  }

  private function checkChainsAndLocks($player_id, $card_id, $cell_id, $lock)
  {
    $player_cards = self::getObjectListFromDB("SELECT * FROM CardPlacements WHERE player_id = $player_id ORDER BY position");

    // Check for chains
    for ($i = 0; $i < count($player_cards) - 1; $i++) {
      if ($player_cards[$i + 1]['position'] - $player_cards[$i]['position'] > 1) {
        self::DbQuery("INSERT INTO Chains (player_id, start_position, end_position) VALUES ($player_id, {$player_cards[$i]['position']}, {$player_cards[$i + 1]['position']})");
      }
    }

    // Check for locks
    if ($lock) {
      $consecutive_cards = 1;
      $start_position = $cell_id;
      $end_position = $cell_id;

      foreach ($player_cards as $card) {
        if ($card['position'] == $cell_id - 1) {
          $consecutive_cards++;
          $start_position = $card['position'];
        } elseif ($card['position'] == $cell_id + 1) {
          $consecutive_cards++;
          $end_position = $card['position'];
        }
      }

      if ($consecutive_cards >= 3) {
        self::DbQuery("INSERT INTO Locks (player_id, start_position, end_position) VALUES ($player_id, $start_position, $end_position)");
      }
    }
  }

  private function checkEndGame()
  {
    $players = self::loadPlayersBasicInfos();
    $active_players = 0;

    foreach ($players as $player_id => $player) {
      $hand = $this->getPlayerCards($player_id);
      if (!empty($hand)) {
        $active_players++;
      }
    }

    return $active_players <= 1;
  }
  function getAllDatas()
  {
    $result = array();
    $result['players'] = $this->loadPlayersBasicInfos();
    $result['cards'] = self::getObjectListFromDB("SELECT * FROM Cards");

    // Fetch player hands
    $result['playerHands'] = array();
    $players = $this->loadPlayersBasicInfos();
    foreach ($players as $player_id => $player) {
      $result['playerHands'][$player_id] = self::getObjectListFromDB("SELECT c.* FROM Cards c JOIN PlayerHands ph ON c.card_id = ph.card_id WHERE ph.player_id = $player_id");
    }

    $result['cardPlacements'] = self::getObjectListFromDB("SELECT * FROM CardPlacements");
    return $result;
  }




  // Define the missing getPlayerColor method
  private function getPlayerColor($player_id)
  {
    // You need to define your player colors here
    // For example, return a color based on the player's ID or other logic
    $colors = array(
      1 => 'blue',   // Blue
      2 => 'green',  // Green
      3 => 'purple', // Purple
      4 => 'red'     // Red
    );

    return $colors[($player_id % 4) + 1];
  }

  // Other necessary methods...
}