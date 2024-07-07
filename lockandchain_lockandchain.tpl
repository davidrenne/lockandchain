{OVERALL_GAME_HEADER}

<div id="game_area">
    <h1>Lock and Chain</h1>

    <div id="board">
        <!-- BEGIN board -->
        <div class="grid_cell" id="cell_{CELL_ID}">
            <img src="https://studio.boardgamearena.com:8084/data/themereleases/current/games/lockandchain/999999-9999/img/lockandchainnumbers_board_{CELL_ID}.png" class="board_card" />
            <div class="card_placeholder"></div>
        </div>
        <!-- END board -->
    </div>

    <!-- BEGIN player_hand -->
    <div id="player_hand_{PLAYER_ID}" class="player_hand">
        <h3>{PLAYER_NAME}'s Hand</h3>
        <!-- BEGIN player_card -->
        <div class="player_card" id="player_card_{CARD_ID}">
            <img src="https://studio.boardgamearena.com:8084/data/themereleases/current/games/lockandchain/999999-9999/img/lockandchainnumbers_{CARD_COLOR}_{CARD_NUMBER}.png" />
        </div>
        <!-- END player_card -->
    </div>
    <!-- END player_hand -->
</div>

{OVERALL_GAME_FOOTER}