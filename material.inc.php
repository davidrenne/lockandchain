<?php
$this->colors = array(
  "red" => array("value" => "ff0000"),
  "purple" => array("value" => "800080"),
  "blue" => array("value" => "0000ff"),
  "green" => array("value" => "008000")
);

$this->cards = array();
foreach (array_keys($this->colors) as $color) {
  for ($i = 1; $i <= 36; $i++) {
    $this->cards[] = array('type' => $color, 'type_arg' => $i, 'nbr' => 1);
  }
}