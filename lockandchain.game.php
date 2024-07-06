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
    // SQL queries for creating necessary tables with foreign key constraints
    $queries = [
      "CREATE TABLE IF NOT EXISTS `Cards` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `number` INT NOT NULL,
                `color` VARCHAR(7) NOT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8;",

      "CREATE TABLE IF NOT EXISTS `PlayerHands` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `player_id` INT,
                `card_id` INT,
                FOREIGN KEY (`player_id`) REFERENCES `player`(`player_id`),
                FOREIGN KEY (`card_id`) REFERENCES `Cards`(`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8;",

      "CREATE TABLE IF NOT EXISTS `CardPlacements` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `game_id` INT,
                `card_id` INT,
                `player_id` INT,
                `position` INT,
                FOREIGN KEY (`game_id`) REFERENCES `global`(`global_id`),
                FOREIGN KEY (`card_id`) REFERENCES `Cards`(`id`),
                FOREIGN KEY (`player_id`) REFERENCES `player`(`player_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8;",

      "CREATE TABLE IF NOT EXISTS `Chains` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `player_id` INT,
                `start_position` INT,
                `end_position` INT,
                FOREIGN KEY (`player_id`) REFERENCES `player`(`player_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8;",

      "CREATE TABLE IF NOT EXISTS `Locks` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `player_id` INT,
                `start_position` INT,
                `end_position` INT,
                FOREIGN KEY (`player_id`) REFERENCES `player`(`player_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8;",

      "CREATE TABLE IF NOT EXISTS `GameActions` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `game_id` INT,
                `player_id` INT,
                `action_type` VARCHAR(50),
                `card_id` INT,
                `timestamp` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (`game_id`) REFERENCES `global`(`global_id`),
                FOREIGN KEY (`player_id`) REFERENCES `player`(`player_id`),
                FOREIGN KEY (`card_id`) REFERENCES `Cards`(`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8;"
    ];

    // Execute each query
    foreach ($queries as $query) {
      self::DbQuery($query);
    }

    // Setup other game elements
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
      foreach ($this->getPlayers() as $player_id => $player) {
        $cards[] = array('type' => 'card', 'type_arg' => $i, 'location' => 'deck', 'location_arg' => $player_id);
      }
    }
    $this->cards->createCards($cards, 'deck');
    $this->cards->shuffle('deck');
  }

  // Fetch player cards
  public function getPlayerCards($player_id)
  {
    $sql = "SELECT c.id, c.number, c.color FROM Cards c JOIN PlayerHands ph ON c.id = ph.card_id WHERE ph.player_id = $player_id";
    return self::getCollectionFromDb($sql);
  }

  // Handle player actions
  public function playCard($card_id, $cell_id)
  {
    $player_id = self::getActivePlayerId();

    // Fetch the card details using card_id
    $card = $this->cards->getCard($card_id);
    $card_value = $card['type_arg'];
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
  function getAllDatas()
  {
    $result = array();
    $result['players'] = $this->loadPlayersBasicInfos();
    $result['cards'] = self::getObjectListFromDB("SELECT * FROM Cards");
    $result['playerHands'] = self::getObjectListFromDB("SELECT * FROM PlayerHands");
    $result['cardPlacements'] = self::getObjectListFromDB("SELECT * FROM CardPlacements");
    return $result;
  }
}