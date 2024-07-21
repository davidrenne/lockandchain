<?php

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