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

  protected function setupNewGame($players, $options = array())
  {
    // Start a database transaction
    self::DbQuery("START TRANSACTION");

    try {
      // Initialize players
      $this->initializePlayers($players);

      // Initialize decks and cards
      $this->initDecks();

      $this->dealInitialHands($players);

      // Set up game state
      $this->setGameStateInitialValue('currentTurn', 0);

      $this->activeNextPlayer();

      // Commit the transaction
      self::DbQuery("COMMIT");

      return array(
        'players' => $players,
        'options' => $options
      );

    } catch (Exception $e) {
      self::DbQuery("ROLLBACK");
      $this->customLog("setupNewGame", "Error during game setup: " . $e->getMessage());
      throw $e;
    }
  }

  function stResolveSelections()
  {
    // Get the selected cards for the round
    $selections = self::getCollectionFromDB("SELECT player_id, card_id FROM PlayerSelections");

    $cardCounts = [];
    foreach ($selections as $player_id => $selection) {
      $card_id = $selection['card_id'];
      $card = self::getObjectFromDB("SELECT * FROM Cards WHERE card_id = $card_id");

      if (isset($cardCounts[$card['card_type_arg']])) {
        $cardCounts[$card['card_type_arg']][] = $player_id;
      } else {
        $cardCounts[$card['card_type_arg']] = [$player_id];
      }
    }
    try {
      self::DbQuery("START TRANSACTION");
      foreach ($cardCounts as $card_number => $player_ids) {
        if (count($player_ids) > 1) {
          // Discard duplicate cards
          foreach ($player_ids as $player_id) {
            $card_id = $selections[$player_id]['card_id'];
            self::DbQuery("UPDATE Cards SET card_location = 'discard' WHERE card_id = $card_id");
          }
        } else {
          // Place the card on the board
          $player_id = $player_ids[0];
          $card_id = $selections[$player_id]['card_id'];
          $this->playCard($card_id, $card_number);
        }
      }

      // Clear selections for the next round
      self::DbQuery("DELETE FROM PlayerSelections");

      $transition = "nextTurn";
      if ($this->isGameEnd()) {
        $transition = "endGame";
      }


      foreach ($cardCounts as $card_number => $player_ids) {
        // Notify players
        self::notifyAllPlayers(
          'cardPlayed',
          clienttranslate('${player_name} plays ${card_number}'),
          array(
            'player_name' => "todo build query",
            'card_number' => $card_number,
          )
        );
      }
      $this->customLog("transition", $transition);
      // Proceed to the next player or end game
      $this->gamestate->nextState($transition);
      self::DbQuery('COMMIT');
    } catch (Exception $e) {
      $this->customLog("adsfasdfasdfdfsa", $e->getMessage());
      self::DbQuery('ROLLBACK');
      throw new BgaUserException($e->getMessage());
    }
  }

  private function initializePlayers($players)
  {
    $sql = "INSERT INTO player (player_id, player_name, player_color, player_canal, player_avatar) VALUES ";
    $values = array();
    foreach ($players as $player_id => $player) {
      $color = $this->getPlayerColor($player_id);
      $values[] = "($player_id, '" . addslashes($player['player_name']) . "', '$color', '', '')";
    }
    $sql .= implode(',', $values);
    self::DbQuery($sql);
  }

  private function dealInitialHands($players)
  {
    $playerCount = count($players);
    $cardsPerPlayer = ($playerCount == 2) ? 7 : (($playerCount == 3) ? 6 : 5);

    foreach ($players as $player_id => $player) {
      $cards = $this->getCards($player_id, 'deck', $cardsPerPlayer);
      foreach ($cards as $card) {
        $this->moveCard($card['id'], 'hand', $player_id);
        self::DbQuery("INSERT INTO PlayerHands (card_type_arg, player_id, card_id) VALUES ({$card['type']}, $player_id, {$card['id']})");
      }
    }
  }
  private function initDecks()
  {
    // Each player gets a deck of cards 1-36
    $cards = array();
    $players = $this->loadPlayersBasicInfos();
    foreach ($players as $player_id => $player) {
      for ($i = 1; $i <= 36; $i++) {
        $cards[] = array(
          'card_type' => $player['player_color'],
          'card_type_arg' => $i,
          'card_location' => 'deck',
          'player_id' => $player['player_id']
        );
      }
    }

    // Insert cards into Cards table
    foreach ($cards as $card) {
      $sql = "INSERT INTO Cards (card_type, card_type_arg, card_location, player_id, card_location_arg) 
                VALUES ('{$card['card_type']}', {$card['card_type_arg']}, '{$card['card_location']}', {$card['player_id']}, 0)";
      self::DbQuery($sql);
    }

    // Shuffle the deck
    $this->shuffleDeck();
  }



  private function validateCardPlay($player_id, $card_id, $card_number)
  {
    // Check if the card belongs to the player
    $card = self::getObjectFromDB("SELECT * FROM Cards WHERE card_id = $card_id AND card_location = 'hand' AND player_id = $player_id");
    if (!$card) {
      throw new BgaUserException(self::_("You don't have this card in your hand"));
    }

    // Check for chains
    $chains = self::getObjectListFromDB("SELECT * FROM Chains WHERE player_id != $player_id");
    foreach ($chains as $chain) {
      if ($card_number > $chain['start_position'] && $card_number < $chain['end_position']) {
        throw new BgaUserException(self::_("You cannot play within another player's chain"));
      }
    }

    // Check for locks
    $locks = self::getObjectListFromDB("SELECT * FROM Locks WHERE player_id != $player_id");
    foreach ($locks as $lock) {
      if ($card_number >= $lock['start_position'] && $card_number <= $lock['end_position']) {
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
  public function playCard($card_id, $card_number, $lock = false)
  {
    $player_id = self::getActivePlayerId();
    try {
      // Validate the move
      $this->validateCardPlay($player_id, $card_id, $card_number);

      // Update the game state
      self::DbQuery("UPDATE Cards SET card_location = 'board' WHERE card_id = $card_id");

      // Check for chains and locks
      $this->checkChainsAndLocks($player_id, $card_id, $card_number, $lock);

      self::DbQuery("INSERT INTO CardPlacements (game_id, card_id, player_id, card_number) VALUES ({$this->getGameId()}, $card_id, $player_id, $card_number)");

    } catch (BgaUserException $e) {
      $this->gamestate->changeActivePlayer($player_id); // Set the active player to the current player
      $this->gamestate->nextState('playerTurn'); // Keep the player in their turn 
      self::notifyPlayer($player_id, 'invalidMove', clienttranslate('Invalid move: ${message}'), array('message' => $e->getMessage()));
    }
  }


  public function getGameLogs($limit = 100, $section = null)
  {
    $sql = "SELECT * FROM game_logs";
    if ($section !== null) {
      $escapedSection = self::escapeStringForDB($section);
      $sql .= " WHERE section = '$escapedSection'";
    }
    $sql .= " ORDER BY created_at DESC LIMIT $limit";
    return self::getObjectListFromDB($sql);
  }

  private function customLog($section, $data = null)
  {
    $escapedSection = self::escapeStringForDB($section);
    $escapedMessage = "";

    if ($data !== null) {
      $dataString = is_array($data) || is_object($data) ? json_encode($data) : strval($data);
      $escapedMessage .= " | Data: " . self::escapeStringForDB($dataString);
    }

    $sql = "INSERT INTO game_logs (section, message) VALUES ('$escapedSection', '$escapedMessage')";
    self::DbQuery($sql);
  }
  public function selectCard($player_id, $card_id)
  {
    self::checkAction('selectCard');

    // Validate that the card belongs to the player
    $card = self::getObjectFromDB("SELECT * FROM Cards WHERE card_id = $card_id AND card_location = 'hand' AND player_id = $player_id");
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
    } else {
      // If not all players have selected, go to next person
      $this->gamestate->nextState('nextPlayer');
    }
  }

  // public function resolveSelections()
  // {
  //   // Retrieve all player selections
  //   $selections = self::getCollectionFromDB("SELECT player_id, card_id FROM PlayerSelections");

  //   // Prepare the data for client-side resolution
  //   $selection_data = array();
  //   foreach ($selections as $player_id => $selection) {
  //     $card = self::getObjectFromDB("SELECT card_id, card_type AS card_color, card_type_arg AS card_number FROM Cards WHERE card_id = {$selection['card_id']}");
  //     $selection_data[$player_id] = $card;
  //   }

  //   // Notify all players about the selections and trigger client-side resolution
  //   self::notifyAllPlayers(
  //     'resolveSelections',
  //     '',
  //     array(
  //       'selections' => $selection_data
  //     )
  //   );

  //   // Clear the selections table for the next round
  //   self::DbQuery("DELETE FROM PlayerSelections");

  //   // Move to the next game state (you might want to wait for client-side resolution to complete)
  //   $this->gamestate->nextState('nextRound');
  // }

  function stNextPlayer()
  {
    // Proceed to the next player
    $player_id = self::activeNextPlayer();
    $this->gamestate->changeActivePlayer($player_id); // Set the active player to the current player
    if ($this->isGameEnd()) {
      $this->gamestate->nextState("endGame");
    } else {
      $this->gamestate->nextState("playerTurn");
    }
  }

  function isGameEnd()
  {
    $players = self::loadPlayersBasicInfos();
    $active_players = array_filter($players, function ($player) {
      return $player['player_zombie'] == 0;
    });

    if (count($active_players) <= 2) {
      foreach ($active_players as $player_id => $player) {
        if (!$this->hasLegalMove($player_id)) {
          return true;
        }
      }
    }

    return false;
  }

  function hasLegalMove($player_id)
  {
    // Implement your logic to check if a player has a legal move
    $cards_in_hand = self::getObjectListFromDB("SELECT card_type_arg, card_id FROM PlayerHands WHERE player_id = $player_id");
    foreach ($cards_in_hand as $card) {
      try {
        $this->validateCardPlay($player_id, $card['card_id'], $card['card_type_arg']);
        return true;
      } catch (Exception $e) {
        return false;
      }
    }
    return false;
  }

  public function zombieTurn($state, $active_player)
  {
    $statename = $state['name'];

    if ($statename == 'playerTurn') {
      $this->gamestate->nextState('endTurn');
    } else {
      // For other states, just go to the next state
      $this->gamestate->nextState('zombiePass');
    }
  }

  public function getCurrentPlayerId($bReturnNullIfNotLogged = false)
  {
    return parent::getCurrentPlayerId($bReturnNullIfNotLogged);
  }

  function getAllDatas()
  {
    $result = array();
    $current_player_id = $this->getCurrentPlayerId();
    $result['current_player_id'] = $current_player_id;
    $result['players'] = $this->loadPlayersBasicInfos();

    // Fetch only the current player's hand
    if ($current_player_id !== null) {
      $result['playerHand'] = $this->getPlayerCards($current_player_id);
    } else {
      $result['playerHand'] = array();
    }

    $result['cardPlacements'] = self::getObjectListFromDB("SELECT * FROM CardPlacements");
    return $result;
  }



  // Helper method to get cards
  private function getCards($player_id, $location, $nbr = 1)
  {
    return self::getObjectListFromDB("SELECT card_id id, card_type_arg type FROM Cards WHERE card_location='$location' AND player_id=$player_id ORDER BY card_location_arg LIMIT $nbr");
  }

  // Helper method to move a card
  private function moveCard($card_id, $location, $location_arg)
  {
    $sql = "UPDATE Cards SET card_location='$location' WHERE card_id=$card_id";
    self::DbQuery($sql);
  }

  public function getPlayerCards($player_id)
  {
    return self::getObjectListFromDB("SELECT c.* FROM Cards c JOIN PlayerHands ph ON c.card_id = ph.card_id WHERE ph.player_id = $player_id");
  }


  private function shuffleDeck()
  {
    // Shuffle the deck by updating card_location_arg with random values
    $sql = "UPDATE Cards SET card_location_arg = FLOOR(1 + RAND() * 100)";
    self::DbQuery($sql);
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