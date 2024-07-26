<?php

require_once (APP_GAMEMODULE_PATH . 'module/table/table.game.php');

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

  public function testRemovePilesAfterPlayerKnockOut()
  {
    // Clear existing cards
    $this->game->DbQuery("DELETE FROM Cards WHERE player_id IN (" . implode(',', $this->player_ids) . ")");

    // Set up initial hands
    $this->insertPlayerCards($this->player_ids[0], [1, 32, 10, 15, 20, 25, 30]);
    $this->insertPlayerCards($this->player_ids[1], [35, 36, 33, 7]);
    if (isset($this->player_ids[2])) {
      $this->insertPlayerCards($this->player_ids[2], [33, 34, 3]);
    }

    // This setup allows for:
    // 1. Player 1 to play 1, then 32
    // 2. Players 2 and 3 to play their high cards (33, 34, 35, 36) in any order, second player plays 33 over the last persons knocked
    // 3. Continued play until all players are unable to make a move
    // 4. Verification of pile removal when players are knocked out
  }

  public function testAbsoluteTie()
  {
    // Clear existing cards
    $this->game->DbQuery("DELETE FROM Cards WHERE player_id IN (" . implode(',', $this->player_ids) . ")");

    // Set up initial hands
    $this->insertPlayerCards($this->player_ids[0], [1, 2, 3, 36]);
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

  public function inBetweenChainTests()
  {
    // Clear existing cards
    $this->game->DbQuery("DELETE FROM Cards WHERE player_id IN (" . implode(',', $this->player_ids) . ")");

    // Set up initial hands with ascending order
    $this->insertPlayerCards($this->player_ids[0], [1, 3, 6, 9, 8, 33, 34, 35, 36]);
    $this->insertPlayerCards($this->player_ids[1], [3, 7, 6, 8, 9, 10, 11, 12, 13]);

    // This setup allows for:
    // 1. Ensure player 2 can play on the 3 card
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

class CardManager
{
  private $db;

  public function __construct($db)
  {
    $this->db = $db;
  }

  public function standardHandDeal($players)
  {
    $cards = array();
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
      $this->db->DbQuery($sql);
    }
  }
}

class LockAndChain extends Table
{
  private ?LockAndChainTestScenarios $testScenarios = null;
  private ?CardManager $cardManager = null;

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

  public function getObjectFromDatabase($sql)
  {
    return self::getObjectFromDB($sql);
  }

  private function initComponents()
  {
    if ($this->testScenarios === null) {
      $this->testScenarios = new LockAndChainTestScenarios($this);
    }
    if ($this->cardManager === null) {
      $this->cardManager = new CardManager($this);
    }
  }



  protected function setupNewGame($players, $options = array())
  {

    // Start a database transaction
    self::DbQuery("START TRANSACTION");

    try {
      // Initialize players
      $this->initializePlayers($players);

      $this->initStat('table', 'rounds_count', 0);
      $this->incNotifyRound();
      foreach ($players as $player_id => $player) {
        $this->initStat('player', 'cards_in_play', 0, $player_id);
        $this->initStat('player', 'chains_broken', 0, $player_id);
      }

      // Initialize components here
      $this->initComponents();

      // Initialize decks and cards
      $this->initDecks();

      // Randomly determine the starting hands based on inserted decks
      $this->dealInitialHands($players);

      // Set up game state
      $this->setGameStateInitialValue('currentTurn', 0);

      // Initialize the first player
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
  function incNotifyRound()
  {
    $this->incStat(1, 'rounds_count');
    $this->notifyRoundCounts();
  }

  function notifyRoundCounts()
  {
    $roundCount = $this->getStat('rounds_count');
    self::notifyAllPlayers('newRoundCount', clienttranslate('Round ${roundCount} begins'), [
      'roundCount' => $roundCount,
    ]);
  }

  function nextPlayer()
  {
    $this->gamestate->nextState("nextPlayer");
  }


  public function bgaGameEnded(): bool
  {
    return $this->gamestate->state_id() == ST_END_GAME;
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
    $notifications = array(); // Initialize hash map for collecting notifications

    try {
      self::DbQuery("START TRANSACTION");

      foreach ($cardCounts as $card_number => $player_ids) {
        if (count($player_ids) > 1) {
          // Discard duplicate cards
          foreach ($player_ids as $player_id) {
            $card_id = $selections[$player_id]['card_id'];
            $card = self::getObjectFromDB("SELECT * FROM Cards WHERE card_id = $card_id");
            if ($card) {
              $notifications[$card_id] = array(
                'player_name' => self::getPlayerNameById($player_id),
                'card_value' => $card['card_type_arg'],
                'color' => $card['card_type'],
                'card_id' => $card_id,
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
            if ($this->bgaGameEnded()) {
              return;
            }
            $invalid_player_id = $player_id;
            throw $e;
          }
          // Valid card at this point:

          $chains = self::getCollectionFromDb("SELECT * FROM Chains WHERE start_position <= $card_number AND end_position >= $card_number");
          foreach ($chains as $chain) {
            if ($chain['player_id'] != $player_id) {
              $breakingPlayer = self::getUniqueValueFromDB("SELECT player_name FROM player WHERE player_id = $player_id");
              $brokenPlayer = self::getUniqueValueFromDB("SELECT player_name FROM player WHERE player_id = {$chain['player_id']}");
              $chainRange = "{$chain['start_position']}-{$chain['end_position']}";
              self::notifyAllPlayers('chainBroken', clienttranslate('${breakingPlayer} broke ${brokenPlayer}\'s chain (${chainRange}) with a ${card_number}'), [
                'breakingPlayer' => $breakingPlayer,
                'brokenPlayer' => $brokenPlayer,
                'chainRange' => $chainRange,
                'card_number' => $card_number,
              ]);
              $this->incStat(1, 'chains_broken', $player_id);
            }
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
              'card_class' => $this->getCardClass(),
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
        if (!$this->isGameEnd()) {
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

      // Send all collected notifications for discarded cards
      foreach ($notifications as $notification) {
        self::notifyAllPlayers(
          'cardDiscarded',
          clienttranslate('${player_name}\'s ${color} ${card_value} was discarded from play because another player played the same card.'),
          $notification
        );
      }


      $this->updatePlayerStats();
      if (!$this->isGameEnd()) {
        $this->incNotifyRound();
        $this->notifyPlayerStats();
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

  function notifyPlayerStats()
  {
    self::notifyAllPlayers('updatePlayerStats', "", [
      'playerStats' => $this->getPlayerStats(),
    ]);
  }
  function updatePlayerStats()
  {
    $players = self::getCollectionFromDb("SELECT player_id FROM player");
    foreach ($players as $player_id => $player) {
      $cardsInPlay = self::getUniqueValueFromDB("SELECT COUNT(*) FROM Cards WHERE card_location IN ('hand', 'deck') AND card_location_arg = $player_id");
      $this->setStat($cardsInPlay, 'cards_in_play', $player_id);
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
      // $lockedCards = self::getObjectListFromDB(
      $lockedCards = self::getObjectListFromDB(
        "SELECT COUNT(*) as count
        FROM Locks l
        INNER JOIN CardPlacements cp ON l.start_position <= cp.card_number AND l.end_position >= cp.card_number
        INNER JOIN Cards c ON cp.card_id = c.card_id WHERE c.player_id = $player_id"
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

    $this->gamestate->nextState('gameEnd');
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


    $this->setStat(0, 'cards_in_play', $player_id);

    // Mark player as eliminated in the database
    self::DbQuery("UPDATE player SET player_eliminated = 1 WHERE player_id = $player_id");

    // Get all card_ids to be removed
    $card_ids = self::getCollectionFromDB("SELECT card_id FROM Cards WHERE player_id = $player_id");
    $card_types = self::getCollectionFromDB("SELECT card_type_arg FROM Cards WHERE player_id = $player_id");
    $card_ids2 = self::getCollectionFromDB("SELECT card_id FROM CardPlacements WHERE player_id = $player_id");
    $card_types2 = self::getCollectionFromDB("SELECT card_number AS card_type_arg FROM CardPlacements WHERE player_id = $player_id");

    $padded_card_ids = array_map(function ($card_id) {
      return str_pad($card_id, 2, '0', STR_PAD_LEFT);
    }, array_keys($card_ids));
    $padded_card_ids2 = array_map(function ($card_id) {
      return str_pad($card_id, 2, '0', STR_PAD_LEFT);
    }, array_keys($card_ids2));

    $padded_card_types = array_map(function ($card_id) {
      return str_pad($card_id, 3, '0', STR_PAD_LEFT);
    }, array_keys($card_types));
    $padded_card_types2 = array_map(function ($card_id) {
      return str_pad($card_id, 2, '0', STR_PAD_LEFT);
    }, array_keys($card_types2));

    $all_card_ids = array_merge(array_values($padded_card_ids), array_values($padded_card_ids2));
    $all_card_types = array_merge(array_values($padded_card_types), array_values($padded_card_types2));


    // Notify all players
    self::notifyAllPlayers(
      'playerKnockedOut',
      clienttranslate('${player_name} has been knocked out!'),
      array(
        'player_id' => $player_id,
        'player_name' => self::getPlayerNameById($player_id),
        'card_ids' => $all_card_ids,
        'card_types' => $all_card_types
      )
    );


    // Notify the player that they have been eliminated
    self::notifyPlayer(
      $player_id,
      'playerEliminated',
      clienttranslate('You have been knocked out of the game.'),
      array(
        'player_id' => $player_id
      )
    );

  }

  function getPlayerStats()
  {
    $playerStats = [];
    $players = self::getCollectionFromDb("SELECT player_id, player_name, player_eliminated, player_zombie FROM player");
    foreach ($players as $player_id => $player) {
      $cardsInPlay = self::getUniqueValueFromDB("SELECT COUNT(*) FROM Cards WHERE card_location IN ('hand', 'deck') AND player_id = $player_id");
      if ($player['player_zombie']) {
        $cardsInPlay = 0;
      }
      if ($player['player_eliminated']) {
        $cardsInPlay = 0;
      }
      $playerStats[$player_id] = [
        'name' => $player['player_name'],
        'cards_in_play' => $cardsInPlay
      ];
    }
    return $playerStats;
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
              'card_number' => str_pad($card['card_number'], 3, '0', STR_PAD_LEFT)
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
  // function handleKnockedOutPlayers($knockedOutPlayers)
  // {
  //   // Check if the game should end (e.g., only one player left)
  //   $activePlayers = self::getObjectListFromDB("SELECT player_id FROM player WHERE player_eliminated = 0");

  //   if (count($activePlayers) == 1) {
  //     // Declare the last active player as the winner
  //     $winnerId = $activePlayers[0]['player_id'];
  //     $this->declareWinner($winnerId);
  //   }
  // }

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

    $this->gamestate->nextState('gameEnd');
  }


  private function initializePlayers($players)
  {

    $default_colors = array("red", "blue", "green", "purple");
    $sql = "INSERT INTO player (player_id, player_name, player_color, player_canal, player_avatar) VALUES ";
    $values = array();
    foreach ($players as $player_id => $player) {
      $color = array_shift($default_colors);
      $values[] = "($player_id, '" . addslashes($player['player_name']) . "', '$color', '', '')";
    }
    $sql .= implode(',', $values);
    self::DbQuery($sql);
    $this->reattributeColorsBasedOnPreferences($players, array("red", "blue", "green", "purple"));
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
    // Standard deck initialization
    $this->cardManager->standardHandDeal($this->loadPlayersBasicInfos());

    // Shuffle the deck
    $this->shuffleDeck();

    // Comment out the above line and uncomment one of the following lines to run a test scenario:

    // $this->testScenarios->inBetweenChainTests();
    // $this->testScenarios->testRemovePilesAfterPlayerKnockOut();
    // $this->testScenarios->testAbsoluteTie();
    // $this->testScenarios->testTieButWinner();
    // $this->testScenarios->testQuick4Player();
    // $this->testScenarios->testQuickLock();

  }

  // function argGameEnd()
  // {
  //   $players = self::loadPlayersBasicInfos();
  //   $results = array();
  //   foreach ($players as $player_id => $player_info) {
  //     $score = self::getUniqueValueFromDB("SELECT player_score FROM player WHERE player_id = $player_id");
  //     $results[] = array(
  //       'player_id' => $player_id,
  //       'player_name' => $player_info['player_name'],
  //       'score' => $score
  //     );
  //   }
  //   return array(
  //     'results' => $results
  //   );
  // }

  // public function stGameEndStats()
  // {
  //   $this->log->gameEndStats();
  //   $this->gamestate->nextState('gameEnd');
  // }

  public function validateCardPlay($player_id, $card_id, $card_number)
  {
    // Check if the card belongs to the player
    $card = self::getObjectFromDB("SELECT * FROM Cards WHERE card_id = $card_id AND card_location = 'hand' AND player_id = $player_id");
    if (!$card) {
      throw new BgaUserException(clienttranslate("You don't have this card in your hand"));
    }

    // Check for chains
    $chains = self::getObjectListFromDB("SELECT * FROM Chains WHERE player_id != $player_id");
    foreach ($chains as $chain) {
      if ($card_number > $chain['start_position'] && $card_number < $chain['end_position']) {
        throw new BgaUserException(clienttranslate("You cannot play within another player's chain"));
      }
    }

    // Check for locks
    $locks = self::getObjectListFromDB("SELECT * FROM Locks WHERE player_id != $player_id");
    foreach ($locks as $lock) {
      if ($card_number >= $lock['start_position'] && $card_number <= $lock['end_position']) {
        throw new BgaUserException(clienttranslate("You cannot play on a locked position"));
      }
    }

    // The move is valid if we've reached this point
  }

  private function isGap($start, $end, $boardState, $player_id)
  {
    for ($i = $start; $i < $end; $i++) {
      if (isset($boardState[$i]) && $boardState[$i] != $player_id) {
        return false;
      }
    }
    return true;
  }

  function rebuildChainsAndLocks()
  {
    // Clear existing chains and locks
    self::DbQuery("DELETE FROM Chains");
    self::DbQuery("DELETE FROM Locks");

    // Get only active players
    $activePlayers = self::getObjectListFromDB("SELECT player_id FROM player WHERE player_eliminated = 0 AND player_zombie = 0");
    $boardState = $this->getBoardState();

    foreach ($activePlayers as $player) {
      $player_id = $player['player_id'];
      // Get all cards for the player
      $playerCards = [];
      foreach ($boardState as $cardNumber => $ownerId) {
        if ($ownerId == $player_id) {
          $playerCards[] = $cardNumber;
        }
      }
      sort($playerCards);

      $chains = [];
      $currentChain = [];
      $lastCard = -1;

      foreach ($playerCards as $card) {
        if (empty($currentChain)) {
          $currentChain[] = $card;
        } else {
          if ($card == end($currentChain) + 1 || $this->isGap($lastCard + 1, $card, $boardState, $player_id)) {
            $currentChain[] = $card;
          } else {
            $chains[] = $currentChain;
            $currentChain = [$card];
          }
        }
        $lastCard = $card;
      }

      if (!empty($currentChain)) {
        $chains[] = $currentChain;
      }

      // Insert chains into the database
      foreach ($chains as $chain) {
        $this->insertChains($player_id, $chain);
      }

      // Insert locks into the database
      $consecutiveCards = [];
      foreach ($chains as $chain) {
        foreach ($chain as $card) {
          $consecutiveCards[] = $card;
        }
      }

      $currentLock = [];
      for ($i = 0; $i < count($consecutiveCards); $i++) {
        if (empty($currentLock) || end($currentLock) + 1 == $consecutiveCards[$i]) {
          $currentLock[] = $consecutiveCards[$i];
        } else {
          if (count($currentLock) >= 3) {
            $this->insertLocks($player_id, $currentLock);
          }
          $currentLock = [$consecutiveCards[$i]];
        }
      }

      if (count($currentLock) >= 3) {
        $this->insertLocks($player_id, $currentLock);
      }
    }
  }


  private function insertChains($player_id, $chain)
  {
    for ($i = 0; $i < count($chain) - 1; $i++) {
      $start = $chain[$i];
      $end = $chain[$i + 1];
      self::DbQuery("INSERT INTO Chains (player_id, start_position, end_position) VALUES ($player_id, $start, $end)");
    }
  }

  private function insertLocks($player_id, $chain)
  {
    $start = $chain[0];
    $end = end($chain);
    self::DbQuery("INSERT INTO Locks (player_id, start_position, end_position) VALUES ($player_id, $start, $end)");
  }


  private function getBoardState()
  {
    $boardState = [];
    $placements = self::getObjectListFromDB($this->getBoardStateSQL());

    foreach ($placements as $placement) {
      $boardState[$placement['card_number']] = $placement['player_id'];
    }

    return $boardState;
  }

  public function getBoardStateArg(): array
  {
    return self::getObjectListFromDB($this->getBoardStateSQL());
  }
  public function getLocksArg(): array
  {
    return self::getObjectListFromDB("
        SELECT l.player_id, l.start_position, l.end_position, c.card_id, c.card_type AS color, cp.card_number
        FROM Locks l
        INNER JOIN CardPlacements cp ON l.start_position <= cp.card_number AND l.end_position >= cp.card_number
        INNER JOIN Cards c ON cp.card_id = c.card_id
    ");
  }


  private function getBoardStateSQL()
  {
    return "
      SELECT c.card_id, cp.card_number, cp.player_id, cp.position, c.card_type AS color
      FROM CardPlacements cp
      INNER JOIN (
          SELECT card_number, MAX(position) as max_position
          FROM CardPlacements
          GROUP BY card_number
      ) top_cards ON cp.card_number = top_cards.card_number AND cp.position = top_cards.max_position
      INNER JOIN Cards c ON cp.card_id = c.card_id
      ORDER BY cp.card_number;
    ";
  }
  // Handle player actions
  public function playCard($card_id, $card_number)
  {
    $card = self::getObjectFromDB("SELECT * FROM Cards WHERE card_id = $card_id");
    if (!$card) {
      throw new BgaUserException(clienttranslate("Cannot find card in playCard function"));
    }

    $player_id = $card["player_id"];
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
      throw new BgaUserException(clienttranslate("You don't have this card in your hand"));
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
  }

  function stNextPlayer()
  {
    // Proceed to the next player
    $current_player_id = $this->getCurrentPlayerId();
    $next_player_id = self::getNextActivePlayer($current_player_id);
    $this->gamestate->changeActivePlayer($next_player_id); // Set the active player to the current player 
    $this->gamestate->nextState("playerTurn");
  }

  private function getNextActivePlayer($current_player_id)
  {
    $next_player_id = self::getPlayerAfter($current_player_id);
    while ($this->isPlayerEliminated($next_player_id)) {
      $next_player_id = self::getPlayerAfter($next_player_id);
    }
    return $next_player_id;
  }

  private function isPlayerEliminated($player_id)
  {
    $sql = "SELECT player_eliminated FROM player WHERE player_id = $player_id";
    $result = self::getObjectFromDB($sql);
    return (bool) $result['player_eliminated'];
  }



  function isGameEnd()
  {
    $activePlayers = self::getObjectListFromDB("SELECT player_id FROM player WHERE player_eliminated = 0 AND player_zombie = 0");
    if (count($activePlayers) <= 1) {
      $this->declareWinner($activePlayers[0]['player_id']);
    }
    return count($activePlayers) <= 1;
  }

  // function isGameEnd()
  // {
  //   $active_players = self::getObjectListFromDB("SELECT * FROM player WHERE player_eliminated = 0 AND player_zombie = 0");
  //   if (count($active_players) <= 2) {
  //     $has_legal_move = 0; // Initialize with 0 (false)
  //     foreach ($active_players as $player_id => $player) {
  //       $has_legal_move |= $this->hasLegalMove($player_id); // Bitwise OR
  //     }
  //     // If no player has a legal move, the game ends
  //     return !$has_legal_move;
  //   }
  //   if (count($active_players) <= 1) {
  //     return true;
  //   }

  //   return false;
  // }

  // function isGameEnd()
  // {
  //   $activePlayers = self::getObjectListFromDB("SELECT player_id FROM player WHERE player_eliminated = 0");
  //   return count($activePlayers) <= 1;
  // }

  // build out stEndGame that calls gameWinner and publishes a winner with that player id in boardgamearena.com
  function stEndGame()
  {

    // $this->setStat($pointsWin ? 1 : 0, 'pointsWin');
    // $this->setStat($eliminationWin ? 1 : 0, 'eliminationWin');
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

    // Notify players with final scores
    $players = self::loadPlayersBasicInfos();
    $results = array();
    foreach ($players as $player_id => $player_info) {
      $score = self::getUniqueValueFromDB("SELECT player_score FROM player WHERE player_id = $player_id");
      $results[] = array(
        'player_id' => $player_id,
        'player_name' => $player_info['player_name'],
        'score' => $score
      );
    }

    // Set the game state arguments
    $this->gamestate->setAllPlayersMultiactive();

    // Notify players that the game has ended
    self::notifyAllPlayers(
      'endGame',
      clienttranslate('The game has ended.'),
      array(
        'results' => $results
      )
    );
    parent::stGameEnd();
  }

  function gameWinner()
  {

    $activePlayers = self::getObjectListFromDB("SELECT player_id FROM player WHERE player_eliminated = 0 AND player_zombie = 0");
    if (count($activePlayers) <= 1) {
      return $activePlayers[0]['player_id'];
    }

    return "error";
  }

  function hasLegalMove($player_id)
  {
    // Retrieve all cards in the player's hand
    $cards_in_hand = self::getObjectListFromDB("SELECT card_type_arg, card_id FROM PlayerHands WHERE player_id = $player_id");

    foreach ($cards_in_hand as $card) {
      try {
        // Check if the card can be legally played
        $this->validateCardPlay($player_id, $card['card_id'], $card['card_type_arg']);
        // If validateCardPlay does not throw an exception, return true
        return true;
      } catch (Exception $e) {
        // If an exception is caught, continue checking the next card
        continue;
      }
    }
    // If no cards can be legally played, return false
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

  function getCardClass()
  {
    $activePlayers = self::getObjectListFromDB("SELECT player_id FROM player");
    if (count($activePlayers) == 2) {
      return 'seven';
    } else if (count($activePlayers) == 3) {
      return 'six';
    } else {
      return 'five';
    }
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

    $result['card_class'] = $this->getCardClass();
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

  public function argPlayerTurn(): array
  {
    $players = self::loadPlayersBasicInfos();
    foreach ($players as $player_id => $player_info) {
      $players[$player_id]['color'] = $this->getPlayerColor($player_id);
    }
    return [
      'players' => $players,
      'boardState' => $this->getBoardStateArg(),
      'locks' => $this->getLocksArg(),
    ];
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
    $sql = "SELECT player_color FROM player WHERE player_id = $player_id";
    $result = self::getObjectFromDB($sql);
    return $result['player_color'];
  }

}