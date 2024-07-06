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

  // Initialize decks 
  private function initDecks()
  {
    $cards = array();
    for ($i = 1; $i <= 36; $i++) {
      foreach ($this->getPlayers() as $player_id => $player) {
        $cards[] = array('type' => 'card', 'type_arg' => $i, 'location' => 'deck', 'location_arg' => $player_id);
      }
    }

    // Insert cards into the database
    foreach ($cards as $card) {
      $sql = "INSERT INTO Cards (card_type, card_type_arg, card_location, card_location_arg) VALUES ('card', {$card['type_arg']}, 'deck', {$card['location_arg']})";
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
  public function playCard($card_id, $cell_id)
  {
    $player_id = self::getActivePlayerId();

    $card = self::getObjectFromDB("SELECT * FROM Cards WHERE card_id = $card_id");
    $card_value = $card['card_type_arg'];
    $card_color = $card['card_type']; // Assuming there is a color field in your card definition

    // Validate the move and update the game state
    // (e.g., check if the card can be played, update game state)

    // Notify players
    self::notifyAllPlayers(
      'playCard',
      clienttranslate('${player_name} plays ${card_value} on cell ${cell_id}'),
      array(
        'player_name' => self::getActivePlayerName(),
        'card_value' => $card_value,
        'cell_id' => $cell_id,
        'card_color' => $card_color
      )
    );

    // Check for chains and locks
    $this->checkChainsAndLocks($player_id, $card_id, $cell_id);

    // Move to the next state
    $this->gamestate->nextState('playCard');
  }

  function getAllDatas()
  {
    $result = array();
    $result['players'] = $this->loadPlayersBasicInfos();
    $result['cards'] = self::getObjectListFromDB("SELECT * FROM Cards");
    $result['playerHands'] = self::getObjectListFromDB("SELECT * FROM PlayerHands");
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