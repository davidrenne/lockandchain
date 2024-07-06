{OVERALL_GAME_HEADER}

<div id="game_area">
    <h1>Lock and Chain</h1>

    <div id="board">
        <!-- BEGIN grid_row -->
        <div class="grid_row">
            <!-- BEGIN grid_cell -->
            <div class="grid_cell" id="cell_{ROW}_{COL}">
                <img src="img/lockandchainnumbers_board_{CELL_ID}.png" class="board_card" />
                <div class="card_placeholder"></div>
            </div>
            <!-- END grid_cell -->
        </div>
        <!-- END grid_row -->
    </div>

    <div id="player_hand">
        <!-- BEGIN player_card -->
        <div class="player_card" id="player_card_{CARD_ID}">
            <img src="img/lockandchainnumbers_{CARD_COLOR}_{CARD_NUMBER}.png" />
        </div>
        <!-- END player_card -->
    </div>
</div>

{OVERALL_GAME_FOOTER}