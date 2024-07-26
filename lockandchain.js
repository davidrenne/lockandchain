// At the top of your lockandchain.js file, outside of the define block:
var jstpl_player_hand =
  '<h3>${PLAYER_NAME}\'s Hand</h3>\
    <div id="player_hand_${PLAYER_ID}" class="player_hand">\
      ${PLAYER_CARDS}\
   </div>';

var jstpl_player_card =
  '<div class="player_card ${CARD_CLASS}" id="player_card_${CARD_ID}">\
    <img src="https://studio.boardgamearena.com:8084/data/themereleases/current/games/lockandchain/999999-9999/img/lockandchainnumbers_${CARD_COLOR}_${CARD_NUMBER}.png" />\
</div>';

var selectedCard = null;
var jstpl_confirm_button =
  '<button id="confirm_button" class="bgabutton bgabutton_blue">Confirm Selection</button>';

let animationQueue = [];
function applyLockAnimation(newCards) {
  newCards.forEach((card) => {
    // Ensure card_number is defined and handle potential undefined values
    if (card.card_number !== undefined) {
      // Extract the card_number and pad it to 2 digits
      let cardNumber = card.card_number.toString().padStart(3, "0");

      // Store the card in the animation queue
      animationQueue.push(cardNumber);
      console.log("animationQueue", animationQueue);
      // Get the parent div with id cell_${cardNumber}
      let cellElement = document.getElementById(`cell_${cardNumber}`);
      if (cellElement) {
        // Get the image element within the cell
        let imageElement = cellElement.querySelector("img");
        if (imageElement && !imageElement.classList.contains("locked")) {
          // Apply the locked class and rotation animation
          imageElement.classList.add("locked", "rotate-and-scale");
        }
      }
    } else {
      console.error("card_number is undefined for card:", card);
    }
  });
}

function getColorName(hexColor) {
  const colors = {
    ff0000: "red",
    "00ff00": "green",
    "0000ff": "blue",
    800080: "purple",
  };

  hexColor = hexColor.toLowerCase();
  return colors[hexColor] || "unknown";
}

define([
  "dojo",
  "dojo/_base/declare",
  "ebg/core/gamegui",
  "ebg/counter",
  "dojo/string",
  "dojo/fx",
  "dojo/dom-style",
  "dojo/dom-construct",
], function (dojo, declare) {
  return declare("bgagame.lockandchain", ebg.core.gamegui, {
    currentLocks: [], // Maintain the current lock state

    constructor: function () {
      console.log("lockandchain constructor");
      this.notificationQueue = [];
      this.isProcessingNotification = false;
    },

    setup: function (gamedatas) {
      console.log("Starting game setup");

      // Setup the current player's hand
      this.refreshPlayerHand(gamedatas);

      // Add confirm button
      dojo.place(jstpl_confirm_button, "player_hand_container", "after");
      dojo.connect($("confirm_button"), "onclick", this, "onConfirmSelection");

      this.setupNotifications();

      console.log("Ending game setup");
    },

    refreshPlayerHand: function (gamedatas) {
      let currentPlayerId = gamedatas.current_player_id;
      let playerHandContainer = dojo.byId("player_hand_container");
      if (playerHandContainer && gamedatas.playerHand) {
        let playerCards = "";
        for (let i = 0; i < gamedatas.playerHand.length; i++) {
          let card = gamedatas.playerHand[i];
          playerCards += this.format_block("jstpl_player_card", {
            CARD_CLASS: gamedatas.card_class,
            CARD_ID: card.card_id,
            CARD_COLOR: getColorName(card.card_type),
            CARD_NUMBER: card.card_type_arg.toString().padStart(2, "0"),
          });
        }
        let playerHandHtml = this.format_block("jstpl_player_hand", {
          PLAYER_ID: currentPlayerId,
          PLAYER_NAME: gamedatas.players[currentPlayerId].name,
          PLAYER_CARDS: playerCards,
        });
        dojo.place(playerHandHtml, playerHandContainer);
        this.makeCardsSelectable(currentPlayerId);
      }
      // Add confirm button
      dojo.place(jstpl_confirm_button, "player_hand_container", "after");
      dojo.connect($("confirm_button"), "onclick", this, "onConfirmSelection");

      //this.setupNotifications();

      console.log("Ending game setup");
    },

    makeCardSelectable: function (cardId) {
      var card = $("player_card_" + cardId);
      if (card) {
        dojo.connect(card, "onclick", this, "onCardSelect");
      }
    },

    makeCardsSelectable: function (playerId) {
      dojo.query(`#player_hand_${playerId} .player_card`).forEach(
        dojo.hitch(this, function (card) {
          if (!dojo.hasClass(card, "selectable")) {
            dojo.connect(card, "onclick", this, "onCardSelect");
            dojo.addClass(card, "selectable");
          }
        })
      );
    },

    updateBoardState: function (newLockedCards) {
      console.error("newLockedCards-->>", newLockedCards);
      var newCardsAdded = this.checkForNewCards(newLockedCards);
      if (newCardsAdded.length > 0) {
        applyLockAnimation(newCardsAdded);
      }
      // Update the current lock state
      this.currentLocks = newLockedCards.slice();
    },

    checkForNewCards: function (newLockedCards) {
      var newCardsAdded = [];
      newLockedCards.forEach((newLock) => {
        var isNew = this.currentLocks.every(
          (currentLock) => currentLock.card_id !== newLock.card_id
        );
        if (isNew || this.currentLocks.length === 0) {
          newCardsAdded.push(newLock);
        }
      });

      return newCardsAdded;
    },

    onLeavingState: function (stateName) {
      console.log("Leaving state: " + stateName);

      switch (stateName) {
        case "dummmy":
          break;
      }
    },

    onUpdateActionButtons: function (stateName, args) {
      console.log("onUpdateActionButtons: " + stateName);

      if (this.isCurrentPlayerActive()) {
        switch (stateName) {
        }
      }
    },

    onCardSelect: function (evt) {
      if (this.checkAction("selectCard", true)) {
        var card = evt.currentTarget;
        if (selectedCard) {
          dojo.removeClass(selectedCard, "selected");
        }
        selectedCard = card;
        dojo.addClass(card, "selected");
        dojo.style("confirm_button", "display", "inline-block");
      }
    },

    onConfirmSelection: function () {
      if (this.checkAction("selectCard", true)) {
        if (selectedCard) {
          var cardId = selectedCard.id.split("_")[2];
          var playerId = this.player_id;
          this.ajaxcall(
            "/lockandchain/lockandchain/selectCard.html",
            { player_id: playerId, card_id: cardId, lock: true },
            this,
            function (result) {}
          );
        } else {
          this.showMessage(
            _("Please select a card before confirming."),
            "error"
          );
        }
      }
    },

    // This method would be called when all players have made their selections
    // resolveSelections: function (selections) {
    //   alert(0);
    //   // Display all selected cards
    //   var selectionDisplay = $("selection-display");
    //   if (!selectionDisplay) {
    //     selectionDisplay = dojo.place(
    //       '<div id="selection-display"></div>',
    //       "game_play_area"
    //     );
    //   }
    //   dojo.empty(selectionDisplay);

    //   // Check for duplicate selections
    //   var cardCounts = {};
    //   for (var player_id in selections) {
    //     var card = selections[player_id];
    //     if (cardCounts[card.card_number]) {
    //       cardCounts[card.card_number].push(player_id);
    //     } else {
    //       cardCounts[card.card_number] = [player_id];
    //     }

    //     dojo.place(
    //       this.format_block("jstpl_player_card", {
    //         CARD_CLASS: gamedatas.card_class,
    //         CARD_ID: card.card_id,
    //         CARD_COLOR: card.card_type,
    //         CARD_NUMBER: card.card_number,
    //       }),
    //       selectionDisplay
    //     );
    //   }

    //   // Resolve duplicates and place cards
    //   for (var card_number in cardCounts) {
    //     if (cardCounts[card_number].length > 1) {
    //       // Duplicate found, discard these cards
    //       cardCounts[card_number].forEach((player_id) => {
    //         this.discardCard(selections[player_id].card_id);
    //       });
    //     } else {
    //       // No duplicate, attempt to place the card
    //       var player_id = cardCounts[card_number][0];
    //       var card = selections[player_id];
    //       this.placeCard(card.card_id, card.card_number);
    //     }
    //   }

    //   dojo.style("confirm_button", "display", "inline-block");

    //   // Refresh the player's hand after resolving selections
    //   this.ajaxcall(
    //     "/lockandchain/lockandchain/getPlayerHand.html",
    //     { player_id: this.player_id, lock: true },
    //     this,
    //     function (gamedatas) {
    //       this.refreshPlayerHand(gamedatas);
    //     }
    //   );
    // },

    discardCard: function (card_id) {
      // Implement card discard logic
      console.log("Discarding card: " + card_id);
      // You would typically call a server action here to update the game state
    },

    placeCard: function (card_id, card_number) {
      // Implement card placement logic
      console.log("Placing card: " + card_id + " on position " + card_number);
      // You would typically call a server action here to update the game state
      // and then update the UI based on the result
    },

    updatePlayerColors: function (players) {
      for (var player_id in players) {
        var player = players[player_id];
        // Update the UI to reflect player colors
        var playerElement = document.getElementById("player_name_" + player_id);
        if (playerElement) {
          let aElement = playerElement.querySelector("a");
          if (aElement) {
            aElement.style.color = "#" + player.color;
          }
        }
      }
    },

    onEnteringState: function (stateName, args) {
      console.log("Entering state: " + stateName);

      switch (stateName) {
        case "playerTurn":
          this.updatePlayerColors(args.args.players);
          this.updateBoardState(args.args.locks);
          if (this.isCurrentPlayerActive()) {
            dojo.style("confirm_button", "display", "inline-block");
          } else {
            dojo.style("confirm_button", "display", "none");
          }
          break;
      }
    },

    onLeavingState: function (stateName) {
      console.log("Leaving state: " + stateName);

      switch (stateName) {
        case "dummmy":
          break;
      }
    },

    setupNotifications: function () {
      console.log("notifications subscriptions setup");

      // Example 1: standard notification handling
      dojo.subscribe("cardPlayed", this, "notif_cardPlayed");
      dojo.subscribe("selectionSuccess", this, "notif_selectionSuccess");
      dojo.subscribe("cardRevealed", this, "queueNotification");
      dojo.subscribe("cardRemoved", this, "queueNotification");
      dojo.subscribe("cardDiscarded", this, "notif_cardDiscarded");
      dojo.subscribe("newCardDrawn", this, "notif_newCardDrawn");
      dojo.subscribe("invalidSelection", this, "notif_invalidSelection");
      dojo.subscribe("playerEliminated", this, "notif_playerEliminated");
      dojo.subscribe("playerKnockedOut", this, "notif_playerKnockedOut");
      dojo.subscribe("endGame", this, "notif_endGame");
      dojo.subscribe("updatePlayerStats", this, "notif_updatePlayerStats");
      dojo.subscribe("newRoundCount", this, "notif_newRoundCount");

      this.ajaxcall(
        "/lockandchain/lockandchain/updateStats.html",
        {},
        this,
        function (result) {}
      );
    },

    notif_updatePlayerStats: function (notif) {
      var playerStats = notif.args.playerStats;
      for (var playerId in playerStats) {
        var stats = playerStats[playerId];
        var statsElement = dojo.byId("player_stats_" + playerId);
        if (!statsElement) {
          // Create the stats element if it doesn't exist
          statsElement = dojo.create(
            "div",
            {
              id: "player_stats_" + playerId,
              class: "player_stats",
              innerHTML: stats.cards_in_play + " cards in play",
            },
            "overall_player_board_" + playerId
          );
        } else {
          // Update the existing stats element
          statsElement.innerHTML = stats.cards_in_play + " cards in play";
        }
      }
    },

    notif_newRoundCount: function (notif) {
      var roundCount = notif.args.roundCount;
      dojo.byId("round_count").innerHTML = "Round: " + roundCount;
    },

    displayScores: function (results) {},

    notif_playerKnockedOut: function (notif) {
      var card_types = notif.args.card_types;
      var card_ids = notif.args.card_ids;
      // Remove all cards belonging to the knocked-out player
      card_types.forEach((card_id) => {
        let cellElement = document.getElementById(`cell_${card_id}`);
        if (cellElement) {
          // Get the image element within the cell
          let imageElement = cellElement.querySelector("img");
          if (imageElement) {
            console.log(
              "https://studio.boardgamearena.com:8084/data/themereleases/current/games/lockandchain/999999-9999/img/lockandchainnumbers_board_" +
                card_id +
                ".png"
            );
            imageElement.src =
              "https://studio.boardgamearena.com:8084/data/themereleases/current/games/lockandchain/999999-9999/img/lockandchainnumbers_board_" +
              card_id +
              ".png";
          }
        }
      });

      card_ids.forEach((card_id) => {
        var cardElement = dojo.query("#player_card_" + card_id)[0];
        if (cardElement) {
          dojo.destroy(cardElement);
        }
      });

      // Display a message to all players
      this.showMessage(
        _(notif.args.player_name + " has been knocked out!"),
        "info"
      );
    },

    notif_playerEliminated: function (notif) {
      if (notif.args.player_id === this.player_id) {
        // Create the message div
        var messageDiv = document.createElement("div");
        messageDiv.className = "knockout-message";

        // Create the emoji span
        var emojiSpan = document.createElement("span");
        emojiSpan.className = "emoji";
        emojiSpan.textContent = "💀";

        // Create the text span
        var textSpan = document.createElement("span");
        textSpan.textContent = _("You have been knocked out of the game.");

        // Append emoji and text to the message div
        messageDiv.appendChild(emojiSpan);
        messageDiv.appendChild(textSpan);

        // Append the message div to the messages container
        var messagesContainer = document.getElementById("messages");
        messagesContainer.appendChild(messageDiv);
      }
    },

    notif_selectionSuccess: function (notif) {
      dojo.style("confirm_button", "display", "none");
    },

    notif_invalidSelection: function (notif) {
      this.showMessage(_(notif.args.message), "error");
      // Reset selection if needed
      if (selectedCard) {
        dojo.removeClass(selectedCard, "selected");
        selectedCard = null;
      }
      // Note: We don't hide the confirm button here
    },

    notif_newCardDrawn: function (notif) {
      console.log("notif_newCardDrawn", notif);

      // Check if the card already exists in the player's hand
      var existingCard = dojo.query("#player_card_" + notif.args.card_id)[0];
      if (!existingCard) {
        // Create the new card element only if it doesn't exist
        var newCardHtml = this.format_block("jstpl_player_card", {
          CARD_CLASS: notif.args.card_class,
          CARD_ID: notif.args.card_id,
          CARD_COLOR: getColorName(notif.args.card_type),
          CARD_NUMBER: notif.args.card_type_arg.toString().padStart(2, "0"),
        });

        // Add the new card to the player's hand
        dojo.place(newCardHtml, "player_hand_" + this.player_id);

        // Make the new card selectable
        this.makeCardSelectable(notif.args.card_id);
      }
    },

    reapplyAnimations: function () {
      console.log("reapplyAnimations animationQueue", animationQueue);
      animationQueue.forEach((cardNumber) => {
        let cellElement = document.getElementById(`cell_${cardNumber}`);
        console.log("cellElement", cellElement);
        if (cellElement) {
          let imageElement = cellElement.querySelector("img");
          console.log(cellElement, imageElement, imageElement.classList);
          if (imageElement && !imageElement.classList.contains("locked")) {
            imageElement.classList.add("locked", "rotate-and-scale");
          }
        }
      });
    },

    notif_cardPlayed: function (notif) {
      console.log("notif_cardPlayed");
      console.log(notif);
      var card_number2 = notif.args.card_number2;
      var card_number = notif.args.card_number;
      var color = getColorName(notif.args.color);
      // dojo.empty("cell_" + card_number); // Clear existing content
      dojo.place(
        '<div class="card_container">' +
          '<img src="https://studio.boardgamearena.com:8084/data/themereleases/current/games/lockandchain/999999-9999/img/lockandchainnumbers_' +
          color +
          "_" +
          card_number2 +
          '.png" />' +
          '<div class="card_placeholder"></div>' +
          "</div>",
        "cell_" + card_number,
        "only"
      );

      // Remove the card from the player's hand
      var card_element = dojo.query("#player_card_" + notif.args.card_id)[0];
      if (card_element) {
        dojo.destroy(card_element);
      }
      this.reapplyAnimations();
    },

    notif_cardDiscarded: function (notif) {
      console.log("notif_cardDiscarded");
      console.log(notif);

      // Remove the card from the player's hand
      var card_element = dojo.query("#player_card_" + notif.args.card_id)[0];
      if (card_element) {
        dojo.destroy(card_element);
      }

      // Optionally, you can show a message or animation indicating the card was discarded
      this.showMessage(_("Card discarded: ") + notif.args.card_value, "info");
    },

    queueNotification: function (notif) {
      this.notificationQueue.push(notif);
      if (!this.isProcessingNotification) {
        this.processNextNotification();
      }
    },

    processNextNotification: function () {
      if (this.notificationQueue.length === 0) {
        this.isProcessingNotification = false;
        return;
      }

      this.isProcessingNotification = true;
      var notif = this.notificationQueue.shift();

      if (notif.type === "cardRevealed") {
        this.notif_cardRevealed(notif);
      } else if (notif.type === "cardRemoved") {
        this.notif_cardRemoved(notif);
      }
    },

    notif_cardRevealed: function (notif) {
      var cardNumber = notif.args.card_number;
      var newTopCardId = notif.args.new_top_card_id;
      var newTopPlayerId = notif.args.new_top_player_id;
      var oldCard = dojo.query("#cell_" + cardNumber + " .player_card")[0];

      // Fade out the old card
      dojo
        .fadeOut({
          node: oldCard,
          duration: 500,
          onEnd: dojo.hitch(this, function () {
            // Remove the old card
            dojo.empty("cell_" + cardNumber);

            // Create the new card
            var playerColor = getColorName(
              this.gamedatas.players[newTopPlayerId].color
            );
            var cardHtml = this.format_block("jstpl_player_card", {
              CARD_ID: newTopCardId,
              CARD_COLOR: playerColor,
              CARD_NUMBER: cardNumber.toString().padStart(2, "0"),
            });
            var newCard = dojo.place(cardHtml, "cell_" + cardNumber);
            dojo.style(newCard, "opacity", "0");

            // Fade in the new card
            dojo
              .fadeIn({
                node: newCard,
                duration: 500,
                onEnd: dojo.hitch(this, function () {
                  // Process the next notification after a short delay
                  setTimeout(dojo.hitch(this, "processNextNotification"), 200);
                }),
              })
              .play();
          }),
        })
        .play();
    },

    notif_endGame: function (notif) {
      this.showMessage(_("The game has ended."), "info");
    },

    notif_cardRemoved: function (notif) {
      var cardNumber = notif.args.card_number;
      var card = dojo.query("#cell_" + cardNumber + " img")[0];

      if (card) {
        // Fade out the card
        dojo
          .fadeOut({
            node: card,
            duration: 300,
            onEnd: dojo.hitch(this, function () {
              // Remove the card from the UI
              dojo.empty("cell_" + cardNumber);
              dojo.destroy(card);

              var emptySlot = dojo.place(
                '<div class="card_container">' +
                  '<img src="https://studio.boardgamearena.com:8084/data/themereleases/current/games/lockandchain/999999-9999/img/lockandchainnumbers_board_' +
                  cardNumber +
                  '.png" />' +
                  '<div class="card_placeholder"></div>' +
                  "</div>",
                "cell_" + cardNumber
              );
              dojo.style(emptySlot, "opacity", "0");

              // Fade in the empty slot
              dojo
                .fadeIn({
                  node: emptySlot,
                  duration: 200,
                  onEnd: dojo.hitch(this, function () {
                    // Process the next notification after a short delay
                    setTimeout(
                      dojo.hitch(this, "processNextNotification"),
                      200
                    );
                  }),
                })
                .play();
            }),
          })
          .play();
      } else {
        console.error("Card element not found for removal: ", cardNumber);
        // Ensure to process the next notification if the card element is not found
        this.processNextNotification();
      }
    },
  });
});
