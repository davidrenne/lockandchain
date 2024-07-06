<?php

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
    // SQL queries for creating necessary tables
    $sql = "
        CREATE TABLE IF NOT EXISTS `Cards` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `number` INT NOT NULL,
            `color` VARCHAR(7) NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8;

        CREATE TABLE IF NOT EXISTS `PlayerHands` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `player_id` INT,
            `card_id` INT,
            FOREIGN KEY (`player_id`) REFERENCES `player`(`player_id`),
            FOREIGN KEY (`card_id`) REFERENCES `Cards`(`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8;

        CREATE TABLE IF NOT EXISTS `CardPlacements` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `game_id` INT,
            `card_id` INT,
            `player_id` INT,
            `position` INT,
            FOREIGN KEY (`game_id`) REFERENCES `global`(`global_id`),
            FOREIGN KEY (`card_id`) REFERENCES `Cards`(`id`),
            FOREIGN KEY (`player_id`) REFERENCES `player`(`player_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8;

        CREATE TABLE IF NOT EXISTS `Chains` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `player_id` INT,
            `start_position` INT,
            `end_position` INT,
            FOREIGN KEY (`player_id`) REFERENCES `player`(`player_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8;

        CREATE TABLE IF NOT EXISTS `Locks` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `player_id` INT,
            `start_position` INT,
            `end_position` INT,
            FOREIGN KEY (`player_id`) REFERENCES `player`(`player_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8;

        CREATE TABLE IF NOT EXISTS `GameActions` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `game_id` INT,
            `player_id` INT,
            `action_type` VARCHAR(50),
            `card_id` INT,
            `timestamp` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (`game_id`) REFERENCES `global`(`global_id`),
            FOREIGN KEY (`player_id`) REFERENCES `player`(`player_id`),
            FOREIGN KEY (`card_id`) REFERENCES `Cards`(`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
        ";

    self::DbQuery($sql);

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
    // Each player gets a deck of cards 1-36
    $cards = array();
    for ($i = 1; $i <= 36; $i++) {
      foreach (self::loadPlayersBasicInfos() as $player_id => $player) {
        $cards[] = array('type' => 'card', 'type_arg' => $i, 'location' => 'deck', 'location_arg' => $player_id);
      }
    }
    self::DbQuery("INSERT INTO Cards (number, color) VALUES " . implode(',', array_map(function ($card) {
      return "({$card['type_arg']}, 'color')";
    }, $cards)));
  }

  // Handle player actions
  public function playCard($card_id, $cell_id)
  {
    $player_id = self::getActivePlayerId();

    // Fetch the card details using card_id
    $card = self::getObjectFromDB("SELECT * FROM Cards WHERE id = $card_id");
    $card_value = $card['number'];
    $card_color = $card['color']; // Assuming there is a color field in your card definition

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

  // Check chains and locks (dummy implementation, needs actual logic)
  private function checkChainsAndLocks($player_id, $card_id, $cell_id)
  {
    // Implement your logic to check for chains and locks
  }

  // Get all data to initialize the client-side game state
  function getAllDatas()
  {
    $result = array();

    // Get basic player info
    $result['players'] = $this->loadPlayersBasicInfos();

    // Get cards data
    $result['cards'] = self::getObjectListFromDB("SELECT * FROM Cards");

    // Get player hands data
    $result['playerHands'] = self::getObjectListFromDB("SELECT * FROM PlayerHands");

    // Get card placements data
    $result['cardPlacements'] = self::getObjectListFromDB("SELECT * FROM CardPlacements");

    return $result;
  }

  // Get the progression of the game
  function getGameProgression()
  {
    // Implement a way to calculate the game's progression.
    return 0;
  }

  // Get player color
  private function getPlayerColor($player_id)
  {
    $colors = array("ff0000", "008000", "0000ff", "ffa500", "773300"); // Add or modify colors as needed
    return $colors[$player_id % count($colors)];
  }
}