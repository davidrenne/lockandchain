// At the top of your lockandchain.js file, outside of the define block:
var jstpl_player_hand =
  '<h3>${PLAYER_NAME}\'s Hand</h3>\
    <div id="player_hand_${PLAYER_ID}" class="player_hand">\
      ${PLAYER_CARDS}\
   </div>';

var jstpl_player_card =
  '<div class="player_card" id="player_card_${CARD_ID}">\
    <img src="https://studio.boardgamearena.com:8084/data/themereleases/current/games/lockandchain/999999-9999/img/lockandchainnumbers_${CARD_COLOR}_${CARD_NUMBER}.png" />\
</div>';

var selectedCard = null;
var jstpl_confirm_button =
  '<button id="confirm_button" class="bgabutton bgabutton_blue">Confirm Selection</button>';

define([
  "dojo",
  "dojo/_base/declare",
  "ebg/core/gamegui",
  "ebg/counter",
  "dojo/string",
], function (dojo, declare) {
  return declare("bgagame.lockandchain", ebg.core.gamegui, {
    constructor: function () {
      console.log("lockandchain constructor");
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
            CARD_ID: card.card_id,
            CARD_COLOR: card.card_type,
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

      this.setupNotifications();

      console.log("Ending game setup");
    },

    makeCardsSelectable: function (playerId) {
      dojo.query(`#player_hand_${playerId} .player_card`).forEach(
        dojo.hitch(this, function (card) {
          dojo.connect(card, "onclick", this, "onCardSelect");
        })
      );
    },

    onEnteringState: function (stateName, args) {
      console.log("Entering state: " + stateName);

      switch (stateName) {
        case "dummmy":
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
          var playerId = this.player_id; // Retrieve the player_id
          this.ajaxcall(
            "/lockandchain/lockandchain/selectCard.html",
            { player_id: playerId, card_id: cardId },
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
    resolveSelections: function (selections) {
      // Display all selected cards
      var selectionDisplay = $("selection-display");
      if (!selectionDisplay) {
        selectionDisplay = dojo.place(
          '<div id="selection-display"></div>',
          "game_play_area"
        );
      }
      dojo.empty(selectionDisplay);

      // Check for duplicate selections
      var cardCounts = {};
      for (var player_id in selections) {
        var card = selections[player_id];
        if (cardCounts[card.card_number]) {
          cardCounts[card.card_number].push(player_id);
        } else {
          cardCounts[card.card_number] = [player_id];
        }

        dojo.place(
          this.format_block("jstpl_player_card", {
            CARD_ID: card.card_id,
            CARD_COLOR: card.card_type,
            CARD_NUMBER: card.card_number,
          }),
          selectionDisplay
        );
      }

      // Resolve duplicates and place cards
      for (var card_number in cardCounts) {
        if (cardCounts[card_number].length > 1) {
          // Duplicate found, discard these cards
          cardCounts[card_number].forEach((player_id) => {
            this.discardCard(selections[player_id].card_id);
          });
        } else {
          // No duplicate, attempt to place the card
          var player_id = cardCounts[card_number][0];
          var card = selections[player_id];
          this.placeCard(card.card_id, card.card_number);
        }
      }

      // Refresh the player's hand after resolving selections
      this.ajaxcall(
        "/lockandchain/lockandchain/getPlayerHand.html",
        { player_id: this.player_id },
        this,
        function (gamedatas) {
          this.refreshPlayerHand(gamedatas);
        }
      );
    },

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

    onEnteringState: function (stateName, args) {
      console.log("Entering state: " + stateName);

      switch (stateName) {
        case "dummmy":
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
      dojo.subscribe("cardDiscarded", this, "notif_cardDiscarded"); // Add this line
    },

    notif_cardPlayed: function (notif) {
      console.log("notif_cardPlayed");
      console.log(notif);
      var card_number2 = notif.args.card_number2;
      var card_number = notif.args.card_number;
      var color = notif.args.color;
      dojo.empty("cell_" + card_number); // Clear existing content
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
  });
});
