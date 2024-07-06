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

        // Setup player hand
        if (gamedatas.playerHands && gamedatas.playerHands[player_id]) {
          gamedatas.playerHands[player_id].forEach((card) => {
            dojo.place(
              this.format_block("jstpl_player_card", {
                CARD_ID: card.card_id,
                CARD_COLOR: card.card_type,
                CARD_NUMBER: card.card_type_arg.toString().padStart(2, "0"),
              }),
              "player_hand"
            );
          });
        }
      }

      // Make cards in player's hand draggable
      if (this.isCurrentPlayerActive()) {
        this.makeCardsDraggable();
      }

      // Make board cells droppable
      this.makeCellsDroppable();

      this.setupNotifications();

      console.log("Ending game setup");
    },

    makeCardsDraggable: function () {
      dojo.query("#player_hand .player_card").forEach(
        dojo.hitch(this, function (card) {
          dojo.connect(card, "ondragstart", this, function (evt) {
            evt.dataTransfer.setData("text", evt.target.id);
          });
          dojo.connect(card, "onclick", this, "onPlayerCardClick");
        })
      );
    },

    makeCellsDroppable: function () {
      dojo.query(".grid_cell").forEach(
        dojo.hitch(this, function (cell) {
          dojo.connect(cell, "ondragover", function (evt) {
            evt.preventDefault();
          });
          dojo.connect(cell, "ondrop", this, "onCardDrop");
        })
      );
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
