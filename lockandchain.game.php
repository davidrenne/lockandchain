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
    return "lockandchain";
  }

  protected function setupNewGame($players, $options = array())
  {
    $maxRetries = 3;
    for ($retry = 0; $retry < $maxRetries; $retry++) {
      try {
        self::DbQuery("START TRANSACTION");

        // SQL queries for creating necessary tables with foreign key constraints
        $sqlStatements = [
          "CREATE TABLE IF NOT EXISTS `Cards` (
                        `id` INT AUTO_INCREMENT PRIMARY KEY,
                        `number` INT NOT NULL,
                        `color` VARCHAR(7) NOT NULL
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8;",

          "CREATE TABLE IF NOT EXISTS `PlayerHands` (
                        `id` INT AUTO_INCREMENT PRIMARY KEY,
                        `player_id` INT,
                        `card_id` INT,
                        FOREIGN KEY (`player_id`) REFERENCES `player`(`player_id`) ON DELETE CASCADE
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8;",

          "CREATE TABLE IF NOT EXISTS `CardPlacements` (
                        `id` INT AUTO_INCREMENT PRIMARY KEY,
                        `game_id` INT,
                        `card_id` INT,
                        `player_id` INT,
                        `position` INT
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8;",

          "CREATE TABLE IF NOT EXISTS `Chains` (
                        `id` INT AUTO_INCREMENT PRIMARY KEY,
                        `player_id` INT,
                        `start_position` INT,
                        `end_position` INT
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8;",

          "CREATE TABLE IF NOT EXISTS `Locks` (
                        `id` INT AUTO_INCREMENT PRIMARY KEY,
                        `player_id` INT,
                        `start_position` INT,
                        `end_position` INT
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8;",

          "CREATE TABLE IF NOT EXISTS `GameActions` (
                        `id` INT AUTO_INCREMENT PRIMARY KEY,
                        `game_id` INT,
                        `player_id` INT,
                        `action_type` VARCHAR(50),
                        `card_id` INT,
                        `timestamp` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8;"
        ];

        foreach ($sqlStatements as $sql) {
          self::DbQuery($sql);
        }

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

        self::DbQuery("COMMIT");
        break; // If successful, break out of the retry loop
      } catch (Exception $e) {
        self::DbQuery("ROLLBACK");
        if ($retry == $maxRetries - 1) {
          throw $e; // Rethrow the exception if the max retries are reached
        }
      }
    }
  }

  private function initDecks()
  {
    $colors = array("red", "blue", "green", "yellow");
    foreach ($colors as $color) {
      for ($number = 1; $number <= 36; $number++) {
        $sql = "INSERT INTO Cards (number, color) VALUES ($number, '$color')";
        self::DbQuery($sql);
      }
    }
  }

  public function playCard($card_id, $cell_id)
  {
    $player_id = self::getActivePlayerId();

    $card = self::getObjectFromDB("SELECT * FROM Cards WHERE id = $card_id");
    $card_value = $card['number'];
    $card_color = $card['color'];

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

    $this->checkChainsAndLocks($player_id, $card_id, $cell_id);

    $this->gamestate->nextState('playCard');
  }

  private function checkChainsAndLocks($player_id, $card_id, $cell_id)
  {
    // Implement your logic to check for chains and locks
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

  function getGameProgression()
  {
    return 0;
  }

  private function getPlayerColor($player_id)
  {
    $colors = array("ff0000", "008000", "0000ff", "ffa500", "773300");
    return $colors[$player_id % count($colors)];
  }

  // Handle zombie player turns
  public function zombieTurn($state, $active_player)
  {
    $statename = $state['name'];

    if ($statename == 'playerTurn') {
      // Skip the zombie player's turn
      $this->gamestate->nextState('zombiePass');
    } else {
      // Default action: pass
      $this->gamestate->nextState('zombiePass');
    }
  }

}