{OVERALL_GAME_HEADER}

<div id="game_area">
    <h1>Lock and Chain</h1>

    <div id="board">
        <!-- Generate 6x6 grid -->
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

    <div id="player_hand"></div>

    <div id="player_boards">
        <!-- BEGIN player_board -->
        <div id="player_board_{PLAYER_ID}" class="player_board"></div>
        <!-- END player_board -->
    </div>
</div>

{OVERALL_GAME_FOOTER}