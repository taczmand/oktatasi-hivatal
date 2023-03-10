<?php
require(__DIR__ . "/vendor/autoload.php");	
require("calculator.class.php");	//Pontszámító osztály
require("homework_input.php");		//Példaadatok

//Példányosítás, paraméterként 1 diák eredményét várja (fájlból olvasva jelenleg).
$calc = new Calculator($exampleData1);
//Output előállítása
print_r($calc->calculate());

?>