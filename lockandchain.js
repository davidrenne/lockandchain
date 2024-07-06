define([
  "dojo",
  "dojo/_base/declare",
  "ebg/core/gamegui",
  "ebg/counter",
], function (dojo, declare) {
  return declare("bgagame.lockandchain", ebg.core.gamegui, {
    constructor: function () {
      console.log("lockandchain constructor");

      // Define the player hand template
      this.jstpl_player_hand =
        '<div class="player-hand" id="player-hand-${player_id}">${cards}</div>';

      // Define the player card template
      this.jstpl_player_card =
        '<div class="player_card" id="player_card_${CARD_ID}">';
      this.jstpl_player_card +=
        '<img src="img/lockandchainnumbers_${CARD_COLOR}_${CARD_NUMBER}.png" />';
      this.jstpl_player_card += "</div>";

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
        var cards_html = "";
        if (gamedatas.playerHands && gamedatas.playerHands[player_id]) {
          for (var i in gamedatas.playerHands[player_id]) {
            var card = gamedatas.playerHands[player_id][i];
            cards_html += this.format_block(this.jstpl_player_card, {
              CARD_ID: card.id,
              CARD_COLOR: card.color,
              CARD_NUMBER: card.number,
            });
          }
        }

        console.log("Player hand HTML for player", player_id, cards_html);

        dojo.place(
          this.format_block(this.jstpl_player_hand, {
            player_id: player_id,
            cards: cards_html,
          }),
          "player_hand"
        );
      }

      // Setup game notifications to handle (see "setupNotifications" method below)
      this.setupNotifications();

      console.log("Ending game setup");
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
      dojo.place(
        '<img src="img/lockandchainnumbers_' +
          notif.args.card_color +
          "_" +
          notif.args.card_number +
          '.png" />',
        "cell_" + cell_id
      );
    },
  });
});
