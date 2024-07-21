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

  function resolveSelections()
  {
    $this->gamestate->nextState("resolveSelections");
  }

  function nextPlayer()
  {
    $this->gamestate->nextState("nextPlayer");
  }


  function stResolveSelections()
  {
    // Get the selected cards for the round
    $selections = self::getCollectionFromDB("SELECT player_id, card_id FROM PlayerSelections");

    $cardCountIds = [];
    $cardCounts = [];
    foreach ($selections as $player_id => $selection) {
      $card_id = $selection['card_id'];
      $card = self::getObjectFromDB("SELECT * FROM Cards WHERE card_id = $card_id");

      if (isset($cardCounts[$card['card_type_arg']])) {
        $cardCounts[$card['card_type_arg']][] = $player_id;
        $cardCountIds[$card['card_type_arg']][] = $card_id;
      } else {
        $cardCounts[$card['card_type_arg']] = [$player_id];
        $cardCountIds[$card['card_type_arg']] = [$card_id];
      }
    }

    $invalid_player_id = null;

    try {
      self::DbQuery("START TRANSACTION");

      foreach ($cardCounts as $card_number => $player_ids) {
        if (count($player_ids) > 1) {
          // Discard duplicate cards
          foreach ($player_ids as $player_id) {
            $card_id = $selections[$player_id]['card_id'];
            $card = self::getObjectFromDB("SELECT * FROM Cards WHERE card_id = $card_id");
            if ($card) {
              self::notifyAllPlayers(
                'cardDiscarded',
                clienttranslate('${player_name}\'s ${color} ${card_value} was discarded from play because another player played the same card.'),
                array(
                  'player_name' => self::getPlayerNameById($player_id),
                  'card_value' => $card['card_type_arg'],
                  'color' => $card['card_type'],
                  'card_id' => $card_id,
                )
              );
            }
            self::DbQuery("UPDATE Cards SET card_location = 'discard' WHERE card_id = $card_id");
            self::DbQuery("DELETE FROM CardPlacements WHERE card_id = $card_id");
          }
        } else {
          // Place the card on the board
          $player_id = $player_ids[0];
          $card_id = $selections[$player_id]['card_id'];
          try {
            $this->playCard($card_id, $card_number);
          } catch (BgaUserException $e) {
            $invalid_player_id = $player_id;
            throw $e;
          }
        }

        self::DbQuery("DELETE FROM PlayerHands WHERE card_id IN (" . implode(',', $cardCountIds[$card_number]) . ")");
      }

      // Draw new cards for all players who played a card
      foreach ($selections as $player_id => $selection) {
        $cards = $this->getCards($player_id, 'deck', 1);
        foreach ($cards as $card) {
          $this->moveCard($card['id'], 'hand', $player_id);
          self::DbQuery("INSERT INTO PlayerHands (card_type_arg, player_id, card_id) VALUES ({$card['type']}, $player_id, {$card['id']})");
          // Notify the player about their new card
          self::notifyPlayer(
            $player_id,
            'newCardDrawn',
            '',
            array(
              'card_id' => $card['id'],
              'card_type' => $card['card_type'],
              'card_type_arg' => $card['type']
            )
          );
        }
      }

      // Clear selections for the next round
      self::DbQuery("DELETE FROM PlayerSelections");
      $this->rebuildChainsAndLocks();

      $players = self::loadPlayersBasicInfos();
      $knockedOutPlayers = array();

      foreach ($players as $player_id => $player) {
        if (!$this->playerHasValidMove($player_id)) {
          $knockedOutPlayers[] = $player_id;
        }
      }

      if (count($knockedOutPlayers) == count($players)) {
        // All players are knocked out
        $this->handleAllPlayersKnockedOut();
      } else if (!empty($knockedOutPlayers)) {
        foreach ($knockedOutPlayers as $player_id) {
          $this->knockOutPlayer($player_id);
        }
        if ($this->isGameEnd()) {
          $this->gamestate->nextState('endGame');
        } else {
          $this->gamestate->nextState('nextPlayer');
        }
      } else {
        // No players knocked out, continue to next player
        $this->gamestate->nextState('nextPlayer');
      }

      foreach ($cardCounts as $card_number => $player_ids) {
        if (count($player_ids) == 1) {
          // Notify players only for non-duplicate cards
          $cardId = $cardCountIds[$card_number][0];
          $color = $this->getPlayerColor($player_ids[0]);
          self::notifyAllPlayers(
            'cardPlayed',
            clienttranslate('${player_name} plays ${card_value}'),
            array(
              'player_name' => self::getPlayerNameById($player_ids[0]),
              'card_id' => $cardId,
              'card_value' => $card_number,
              'card_number2' => str_pad($card_number, 2, '0', STR_PAD_LEFT),
              'card_number' => str_pad($card_number, 3, '0', STR_PAD_LEFT),
              'color' => $color,
            )
          );
        }
      }

      self::DbQuery('COMMIT');
    } catch (Exception $e) {
      self::DbQuery('ROLLBACK');
      if ($invalid_player_id !== null) {
        // An invalid play occurred, set the active player to the one who made the invalid play
        $this->gamestate->changeActivePlayer($invalid_player_id);
        $this->gamestate->nextState('playerTurn');
      } else {
        // Some other error occurred
        throw new BgaUserException($e->getMessage());
      }
      return;
    }
  }


  function handleAllPlayersKnockedOut()
  {
    $lockedCardCounts = $this->getLockedCardCounts();
    $maxLockedCards = max($lockedCardCounts);
    $winners = array_keys($lockedCardCounts, $maxLockedCards);

    if (count($winners) == 1) {
      // One clear winner
      $this->declareWinner($winners[0]);
    } else {
      // Tie between players with the most locked cards
      $this->declareTie($winners);
    }
  }

  function getLockedCardCounts()
  {
    $counts = array();
    $players = self::loadPlayersBasicInfos();
    foreach ($players as $player_id => $player) {
      $lockedCards = self::getObjectListFromDB(
        "SELECT COUNT(*) as count FROM Locks WHERE player_id = $player_id"
      );
      $counts[$player_id] = $lockedCards[0]['count'];
    }
    return $counts;
  }

  function declareTie($winners)
  {
    $winnerNames = array_map(array($this, 'getPlayerNameById'), $winners);

    // Set the score for the tied players
    self::DbQuery("UPDATE player SET player_score = 1 WHERE player_id IN (" . implode(',', $winners) . ")");

    // Notify all players about the tie
    self::notifyAllPlayers(
      'gameEnd',
      clienttranslate('The game ends in a tie between ${player_names}!'),
      array(
        'player_names' => implode(', ', $winnerNames)
      )
    );

    $this->gamestate->nextState('endGame');
  }

  function playerHasValidMove($player_id)
  {
    $hand = $this->getPlayerCards($player_id);
    foreach ($hand as $card) {
      try {
        $this->validateCardPlay($player_id, $card['card_id'], $card['card_type_arg']);
        return true; // If any card can be played, return true
      } catch (BgaUserException $e) {
        // This card can't be played, continue checking others
      }
    }
    return false; // If no cards can be played, return false
  }

  function knockOutPlayer($player_id)
  {
    // Remove player's top cards from the board
    $this->removePlayerTopCards($player_id);

    // Mark player as eliminated in the database
    self::DbQuery("UPDATE player SET player_eliminated = 1 WHERE player_id = $player_id");

    // Notify all players
    self::notifyAllPlayers(
      'playerKnockedOut',
      clienttranslate('${player_name} has been knocked out!'),
      array(
        'player_id' => $player_id,
        'player_name' => self::getPlayerNameById($player_id)
      )
    );
  }

  function removePlayerTopCards($player_id)
  {
    // Start a transaction to ensure data consistency
    self::DbQuery("START TRANSACTION");

    try {
      // Get all top cards on the board
      $topCards = self::getObjectListFromDB("
            SELECT cp.card_id, cp.card_number, cp.position, c.player_id
            FROM CardPlacements cp
            JOIN Cards c ON cp.card_id = c.card_id
            INNER JOIN (
                SELECT card_number, MAX(position) as max_position
                FROM CardPlacements
                GROUP BY card_number
            ) top_pos ON cp.card_number = top_pos.card_number AND cp.position = top_pos.max_position
            ORDER BY cp.card_number
        ");

      foreach ($topCards as $card) {
        if ($card['player_id'] == $player_id) {
          // This top card belongs to the knocked-out player
          // Get all cards for this card number
          $cardsToDiscard = self::getObjectListFromDB("
                    SELECT cp.card_id
                    FROM CardPlacements cp
                    WHERE cp.card_number = {$card['card_number']}
                ");

          foreach ($cardsToDiscard as $discardCard) {
            // Move the card to the discard pile
            self::DbQuery("UPDATE Cards SET card_location = 'discard' WHERE card_id = {$discardCard['card_id']}");
            // Remove this card from CardPlacements
            self::DbQuery("DELETE FROM CardPlacements WHERE card_id = {$discardCard['card_id']}");
          }

          // Notify that the position is now empty
          self::notifyAllPlayers(
            'cardRemoved',
            '',
            array(
              'card_number' => $card['card_number']
            )
          );
        }
      }

      // Remove all cards from the knocked-out player's hand
      self::DbQuery("UPDATE Cards SET card_location = 'discard' WHERE player_id = $player_id AND card_location = 'hand'");

      // Remove all entries from PlayerHands for this player
      self::DbQuery("DELETE FROM PlayerHands WHERE player_id = $player_id");

      // Commit the transaction
      self::DbQuery("COMMIT");
    } catch (Exception $e) {
      // If there's any error, rollback the changes
      self::DbQuery("ROLLBACK");
      throw $e;
    }
  }
  function handleKnockedOutPlayers($knockedOutPlayers)
  {
    // Check if the game should end (e.g., only one player left)
    $activePlayers = self::getObjectListFromDB("SELECT player_id FROM player WHERE player_eliminated = 0");

    if (count($activePlayers) == 1) {
      // Declare the last active player as the winner
      $winnerId = $activePlayers[0]['player_id'];
      $this->declareWinner($winnerId);
    }
  }

  function declareWinner($player_id)
  {
    // Set the score for the winning player
    self::DbQuery("UPDATE player SET player_score = 1 WHERE player_id = $player_id");

    // Notify all players about the winner
    self::notifyAllPlayers(
      'gameEnd',
      clienttranslate('${player_name} wins the game!'),
      array(
        'player_id' => $player_id,
        'player_name' => self::getPlayerNameById($player_id)
      )
    );

    $this->gamestate->nextState('endGame');
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



  public function validateCardPlay($player_id, $card_id, $card_number)
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
  private function rebuildChainsAndLocks()
  {
    // Clear existing chains and locks
    self::DbQuery("DELETE FROM Chains");
    self::DbQuery("DELETE FROM Locks");

    $boardState = $this->getBoardState();
    $this->customLog("boardState", json_encode($boardState));

    $players = self::loadPlayersBasicInfos();

    foreach ($players as $player_id => $player) {
      $this->customLog("Processing player", $player_id);

      $playerCards = [];
      $lockStart = null;

      // Collect all cards for this player
      for ($i = 1; $i <= 36; $i++) {
        if (isset($boardState[$i]) && $boardState[$i] == $player_id) {
          $playerCards[] = $i;
        }
      }

      // Process chains and locks
      $chainStart = null;
      for ($i = 0; $i < count($playerCards); $i++) {
        $currentCard = $playerCards[$i];

        if ($chainStart === null) {
          $chainStart = $currentCard;
        }

        // Check if the chain should end
        $endChain = false;
        if ($i == count($playerCards) - 1) {
          $endChain = true;
        } else {
          $nextCard = $playerCards[$i + 1];
          for ($j = $currentCard + 1; $j < $nextCard; $j++) {
            if (isset($boardState[$j]) && $boardState[$j] != $player_id) {
              $endChain = true;
              break;
            }
          }
        }

        if ($endChain) {
          $this->insertChain($player_id, $chainStart, $currentCard);
          $chainStart = null;
        }

        // Check for locks
        if ($i >= 2 && $currentCard == $playerCards[$i - 1] + 1 && $playerCards[$i - 1] == $playerCards[$i - 2] + 1) {
          if ($lockStart === null) {
            $lockStart = $playerCards[$i - 2];
          }
          if ($i == count($playerCards) - 1 || $playerCards[$i + 1] != $currentCard + 1) {
            $this->insertLock($player_id, $lockStart, $currentCard);
            $lockStart = null;
          }
        } else {
          if ($lockStart !== null) {
            $this->insertLock($player_id, $lockStart, $playerCards[$i - 1]);
            $lockStart = null;
          }
        }
      }
    }
  }

  private function getBoardState()
  {
    $boardState = [];
    $placements = self::getObjectListFromDB("
        SELECT cp.card_number, cp.player_id, cp.position
        FROM CardPlacements cp
        INNER JOIN (
            SELECT card_number, MAX(position) as max_position
            FROM CardPlacements
            GROUP BY card_number
        ) top_cards ON cp.card_number = top_cards.card_number AND cp.position = top_cards.max_position
        ORDER BY cp.card_number
    ");

    foreach ($placements as $placement) {
      $boardState[$placement['card_number']] = $placement['player_id'];
    }

    return $boardState;
  }

  private function insertChain($player_id, $start, $end)
  {
    $this->customLog("Inserting Chain", "player: $player_id, start: $start, end: $end");
    self::DbQuery("INSERT INTO Chains (player_id, start_position, end_position) VALUES ($player_id, $start, $end)");
  }

  private function insertLock($player_id, $start, $end)
  {
    $this->customLog("Inserting Lock", "player: $player_id, start: $start, end: $end");
    self::DbQuery("INSERT INTO Locks (player_id, start_position, end_position) VALUES ($player_id, $start, $end)");
  }

  // Handle player actions
  public function playCard($card_id, $card_number)
  {
    $card = self::getObjectFromDB("SELECT * FROM Cards WHERE card_id = $card_id");
    if (!$card) {
      throw new BgaUserException(self::_("Cannot find card in playCard for card_id $card_id"));
    }

    $player_id = $card["player_id"];
    $this->customLog("adfasdf", $player_id);
    try {
      // Validate the move
      $this->validateCardPlay($player_id, $card_id, $card_number);

      // Update the game state
      self::DbQuery("UPDATE Cards SET card_location = 'board' WHERE card_id = $card_id");

      $sql = "INSERT INTO CardPlacements (card_id, player_id, card_number, position)
        SELECT $card_id, $player_id, $card_number,
               COALESCE(
                   (SELECT MAX(position) + 1
                    FROM (SELECT * FROM CardPlacements) AS cp
                    WHERE cp.card_number = $card_number),
                   1
               ) AS new_position";

      self::DbQuery($sql);

    } catch (BgaUserException $e) {
      // $this->gamestate->changeActivePlayer($player_id); // Set the active player to the current player
      // $this->gamestate->nextState('playerTurn'); // Keep the player in their turn 
      self::notifyPlayer($player_id, 'invalidMove', clienttranslate('Invalid move: ${message}'), array('message' => $e->getMessage()));
      throw $e;
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

  function stNextPlayer()
  {
    // Proceed to the next player
    $current_player_id = $this->getCurrentPlayerId();
    $next_player_id = self::getPlayerAfter($current_player_id);
    $this->gamestate->changeActivePlayer($next_player_id); // Set the active player to the current player
    if ($this->isGameEnd()) {
      $this->gamestate->nextState("endGame");
    } else {
      $this->gamestate->nextState("playerTurn");
    }
  }

  // function isGameEnd()
  // {
  //   $players = self::loadPlayersBasicInfos();
  //   $active_players = array_filter($players, function ($player) {
  //     return $player['player_zombie'] == 0;
  //   });

  //   if (count($active_players) <= 2) {
  //     $has_legal_move = 0; // Initialize with 0 (false)
  //     foreach ($active_players as $player_id => $player) {
  //       $has_legal_move |= $this->hasLegalMove($player_id); // Bitwise OR
  //     }
  //     // If no player has a legal move, the game ends
  //     return !$has_legal_move;
  //   }

  //   return false;
  // }

  function isGameEnd()
  {
    $activePlayers = self::getObjectListFromDB("SELECT player_id FROM player WHERE player_eliminated = 0");
    return count($activePlayers) <= 1;
  }

  // build out stEndGame that calls gameWinner and publishes a winner with that player id in boardgamearena.com
  function stEndGame()
  {
    $winner = $this->gameWinner();
    if ($winner != "error") {
      self::DbQuery("UPDATE player SET player_score = 1 WHERE player_id = $winner");
      self::notifyAllPlayers(
        'gameEnd',
        clienttranslate('${player_name} wins the game!'),
        array(
          'player_id' => $winner,
          'player_name' => self::getPlayerNameById($winner)
        )
      );
    }
  }

  function gameWinner()
  {
    $players = self::loadPlayersBasicInfos();
    $active_players = array_filter($players, function ($player) {
      return $player['player_zombie'] == 0;
    });

    if (count($active_players) <= 2) {
      $loser = 0;
      foreach ($active_players as $player_id => $player) {
        if (!$this->hasLegalMove($player_id)) {
          // Return the player who has a legal move
          $loser = $player_id;
        }
      }
      $winner = 0;
      foreach ($active_players as $player_id => $player) {
        if ($loser != $player_id) {
          $winner = $player_id;
        }
      }
      return $winner;
    }

    return "error";
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
    return self::getObjectListFromDB("SELECT card_type, card_id id, card_type_arg type FROM Cards WHERE card_location='$location' AND player_id=$player_id ORDER BY card_location_arg LIMIT $nbr");
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