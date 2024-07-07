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

  private function validateCardPlay($player_id, $card_id, $cell_id)
  {
    // Check if the card belongs to the player
    $card = self::getObjectFromDB("SELECT * FROM Cards WHERE card_id = $card_id AND card_location = 'hand' AND card_location_arg = $player_id");
    if (!$card) {
      throw new BgaUserException(self::_("You don't have this card in your hand"));
    }

    // Check if the cell is empty
    $existing_card = self::getObjectFromDB("SELECT * FROM CardPlacements WHERE position = $cell_id");
    if ($existing_card) {
      throw new BgaUserException(self::_("This cell is already occupied"));
    }

    // Check for chains
    $chains = self::getObjectListFromDB("SELECT * FROM Chains WHERE player_id != $player_id");
    foreach ($chains as $chain) {
      if ($cell_id > $chain['start_position'] && $cell_id < $chain['end_position']) {
        throw new BgaUserException(self::_("You cannot play within another player's chain"));
      }
    }

    // Check for locks
    $locks = self::getObjectListFromDB("SELECT * FROM Locks");
    foreach ($locks as $lock) {
      if ($cell_id >= $lock['start_position'] && $cell_id <= $lock['end_position']) {
        throw new BgaUserException(self::_("You cannot play on a locked position"));
      }
    }

    // The move is valid if we've reached this point
  }

  private function checkChainsAndLocks($player_id, $card_id, $cell_id, $lock)
  {
    $player_cards = self::getObjectListFromDB("SELECT * FROM CardPlacements WHERE player_id = $player_id ORDER BY position");
    $new_card = ['position' => $cell_id];
    $player_cards[] = $new_card;
    usort($player_cards, function ($a, $b) {
      return $a['position'] - $b['position'];
    });

    // Check for chains
    $chains = [];
    for ($i = 0; $i < count($player_cards) - 1; $i++) {
      if ($player_cards[$i + 1]['position'] - $player_cards[$i]['position'] > 1) {
        $chains[] = [
          'start_position' => $player_cards[$i]['position'],
          'end_position' => $player_cards[$i + 1]['position']
        ];
      }
    }

    // Update chains in the database
    self::DbQuery("DELETE FROM Chains WHERE player_id = $player_id");
    foreach ($chains as $chain) {
      self::DbQuery("INSERT INTO Chains (player_id, start_position, end_position) VALUES ($player_id, {$chain['start_position']}, {$chain['end_position']})");
    }

    // Check for locks
    if ($lock) {
      $consecutive_cards = 1;
      $start_position = $cell_id;
      $end_position = $cell_id;

      for ($i = 0; $i < count($player_cards); $i++) {
        if ($player_cards[$i]['position'] == $cell_id) {
          // Check backwards
          for ($j = $i - 1; $j >= 0; $j--) {
            if ($player_cards[$j]['position'] == $player_cards[$j + 1]['position'] - 1) {
              $consecutive_cards++;
              $start_position = $player_cards[$j]['position'];
            } else {
              break;
            }
          }
          // Check forwards
          for ($j = $i + 1; $j < count($player_cards); $j++) {
            if ($player_cards[$j]['position'] == $player_cards[$j - 1]['position'] + 1) {
              $consecutive_cards++;
              $end_position = $player_cards[$j]['position'];
            } else {
              break;
            }
          }
          break;
        }
      }

      if ($consecutive_cards >= 3) {
        self::DbQuery("INSERT INTO Locks (player_id, start_position, end_position) VALUES ($player_id, $start_position, $end_position)");

        // Notify players about the new lock
        self::notifyAllPlayers(
          'newLock',
          clienttranslate('${player_name} created a lock from ${start} to ${end}'),
          array(
            'player_id' => $player_id,
            'player_name' => self::getPlayerNameById($player_id),
            'start' => $start_position,
            'end' => $end_position
          )
        );
      }
    }

    // Notify players about updated chains
    self::notifyAllPlayers(
      'chainsUpdated',
      clienttranslate('${player_name} chains have been updated'),
      array(
        'player_id' => $player_id,
        'player_name' => self::getPlayerNameById($player_id),
        'chains' => $chains
      )
    );
  }

  // Handle player actions
  public function playCard($card_id, $cell_id, $lock = false)
  {
    $player_id = self::getActivePlayerId();

    try {
      // Validate the move
      $this->validateCardPlay($player_id, $card_id, $cell_id);

      // Update the game state
      self::DbQuery("UPDATE Cards SET card_location = 'board', card_location_arg = $cell_id WHERE card_id = $card_id");
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
          'card_value' => self::getCardValueById($card_id),
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
    } catch (BgaUserException $e) {
      // Return the error message to the client
      throw new BgaUserException($e->getMessage());
    }
  }

  public function selectCard()
  {
    self::setAjaxMode();

    // Retrieve the card_id from the AJAX call
    $card_id = self::getArg("card_id", AT_posint, true);

    // Get the current player's ID
    $player_id = self::getCurrentPlayerId();

    // Validate that the card belongs to the player
    $card = self::getObjectFromDB("SELECT * FROM Cards WHERE card_id = $card_id AND card_location = 'hand' AND card_location_arg = $player_id");
    if (!$card) {
      throw new BgaUserException(self::_("You don't have this card in your hand"));
    }

    // Check if the player has already made a selection
    $existing_selection = self::getUniqueValueFromDB("SELECT card_id FROM PlayerSelections WHERE player_id = $player_id");
    if ($existing_selection) {
      // If there's an existing selection, remove it
      self::DbQuery("DELETE FROM PlayerSelections WHERE player_id = $player_id");
    }

    // Record the player's selection
    self::DbQuery("INSERT INTO PlayerSelections (player_id, card_id) VALUES ($player_id, $card_id)");

    // Notify all players about the selection (without revealing the card)
    self::notifyAllPlayers(
      'cardSelected',
      clienttranslate('${player_name} has selected a card'),
      array(
        'player_id' => $player_id,
        'player_name' => self::getActivePlayerName(),
      )
    );

    // Check if all players have made their selections
    $players_count = self::getPlayersNumber();
    $selections_count = self::getUniqueValueFromDB("SELECT COUNT(*) FROM PlayerSelections");

    if ($selections_count == $players_count) {
      // All players have made their selections, move to the resolution phase
      $this->gamestate->nextState('resolveSelections');
    }

    self::ajaxResponse();
  }

  public function resolveSelections()
  {
    // Retrieve all player selections
    $selections = self::getCollectionFromDB("SELECT player_id, card_id FROM PlayerSelections");

    // Prepare the data for client-side resolution
    $selection_data = array();
    foreach ($selections as $player_id => $selection) {
      $card = self::getObjectFromDB("SELECT card_id, card_type AS card_color, card_type_arg AS card_number FROM Cards WHERE card_id = {$selection['card_id']}");
      $selection_data[$player_id] = $card;
    }

    // Notify all players about the selections and trigger client-side resolution
    self::notifyAllPlayers(
      'resolveSelections',
      '',
      array(
        'selections' => $selection_data
      )
    );

    // Clear the selections table for the next round
    self::DbQuery("DELETE FROM PlayerSelections");

    // Move to the next game state (you might want to wait for client-side resolution to complete)
    $this->gamestate->nextState('nextRound');
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