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

      // Define the player hand template
      this.jstpl_player_hand = dojo.string.substitute(
        '<div class="player-hand" id="player-hand-${player_id}">${cards}</div>',
        { player_id: "", cards: "" }
      );

      // Define the player card template
      this.jstpl_player_card = dojo.string.substitute(
        '<div class="player_card" id="player_card_${CARD_ID}" draggable="true"><img src="https://studio.boardgamearena.com:8084/data/themereleases/current/games/lockandchain/999999-9999/img/lockandchainnumbers_${CARD_COLOR}_${CARD_NUMBER}.png" /></div>',
        { CARD_ID: "", CARD_COLOR: "", CARD_NUMBER: "" }
      );

      console.log(
        "Templates defined:",
        this.jstpl_player_hand,
        this.jstpl_player_card
      );
    },

    setup: function (gamedatas) {
      console.log("Starting game setup");

      // Setting up player boards and hands
      for (var player_id in gamedatas.players) {
        var player = gamedatas.players[player_id];

        // Create player hand container if it doesn't exist
        if (!dojo.byId("player-hand-" + player_id)) {
          dojo.place(
            this.format_block("jstpl_player_hand", {
              player_id: player_id,
            }),
            "player_board_" + player_id
          );
        }

        // Setup player hand
        if (gamedatas.playerHands && gamedatas.playerHands[player_id]) {
          gamedatas.playerHands[player_id].forEach((card) => {
            dojo.place(
              this.format_block("jstpl_player_card", {
                CARD_ID: card.card_id,
                CARD_COLOR: card.card_type,
                CARD_NUMBER: card.card_type_arg.toString().padStart(2, "0"),
              }),
              "player-hand-" + player_id
            );
          });
        }

        // Make all cards selectable
        this.makeCardsSelectable(player_id);
      }

      // Add confirm button
      this.addConfirmButton();

      this.setupNotifications();

      console.log("Ending game setup");
    },

    makeCardsSelectable: function (player_id) {
      dojo.query("#player-hand-" + player_id + " .player_card").forEach(
        dojo.hitch(this, function (card) {
          dojo.connect(card, "onclick", this, "onCardSelect");
        })
      );
    },

    addConfirmButton: function () {
      // Add a confirm button to each player's board
      for (var player_id in this.gamedatas.players) {
        dojo.place(
          '<button id="confirm-button-' +
            player_id +
            '" class="confirm-button">Confirm Selection</button>',
          "player_board_" + player_id
        );
        dojo.connect(
          $("confirm-button-" + player_id),
          "onclick",
          this,
          "onConfirmSelection"
        );
      }
    },

    onPlayerCardClick: function (evt) {
      var cardId = evt.currentTarget.id.split("_")[2];
      if (this.checkAction("playCard", true)) {
        this.ajaxcall(
          "/lockandchain/lockandchain/playCard.html",
          {
            card_id: cardId,
            lock: false,
          },
          this,
          function (result) {}
        );
      }
    },

    onCardDrop: function (evt) {
      evt.preventDefault();
      var cardId = evt.dataTransfer.getData("text").split("_")[2];
      var cellId = evt.currentTarget.id.split("_")[2];
      if (this.checkAction("playCard", true)) {
        this.ajaxcall(
          "/lockandchain/lockandchain/playCard.html",
          {
            card_id: cardId,
            cell_id: cellId,
            lock: false,
          },
          this,
          function (result) {}
        );
      }
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
      var card = evt.currentTarget;
      dojo.toggleClass(card, "selected");
    },

    onConfirmSelection: function (evt) {
      var player_id = evt.target.id.split("-")[2];
      var selectedCard = dojo.query(
        "#player-hand-" + player_id + " .player_card.selected"
      )[0];

      if (selectedCard) {
        var card_id = selectedCard.id.split("_")[2];

        // Send the selection to the server
        this.ajaxcall(
          "/lockandchain/lockandchain/selectCard.html",
          { card_id: card_id },
          this,
          function (result) {}
        );
      } else {
        this.showMessage(_("Please select a card before confirming."), "error");
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

    setupNotifications: function () {
      console.log("notifications subscriptions setup");

      // Example 1: standard notification handling
      dojo.subscribe("cardPlayed", this, "notif_cardPlayed");
    },

    notif_cardPlayed: function (notif) {
      console.log("notif_cardPlayed");
      console.log(notif);

      // Note: notif.args contains the arguments specified during you "notifyAllPlayers" / "notifyPlayer" PHP call
      // Example implementation of handling the card played notification
      var card_id = notif.args.card_id;
      var cell_id = notif.args.cell_id;
      var cardElement = dojo.byId("player_card_" + card_id);
      if (cardElement) {
        dojo.place(cardElement, "cell_" + cell_id);
      } else {
        dojo.place(
          '<img src="https://studio.boardgamearena.com:8084/data/themereleases/current/games/lockandchain/999999-9999/img/lockandchainnumbers_' +
            notif.args.card_color +
            "_" +
            notif.args.card_number +
            '.png" />',
          "cell_" + cell_id
        );
      }
    },
  });
});
