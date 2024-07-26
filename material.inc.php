<?php
$this->colors = array(
  "ff0000" => array("value" => "ff0000"),
  "800080" => array("value" => "800080"),
  "0000ff" => array("value" => "0000ff"),
  "00ff00" => array("value" => "00ff00")
);

$this->cards = array();
foreach (array_keys($this->colors) as $color) {
  for ($i = 1; $i <= 36; $i++) {
    $this->cards[] = array('type' => $color, 'type_arg' => $i, 'nbr' => 1);
  }
}