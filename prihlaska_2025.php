<?php
/*
  (c) 2025 Martin Smidek <martin@smidek.eu>

  přepínač na asktuální verzi online přihlašování pro YMCA Setkání (jen obnovy a LK MS YS)
  debuger je lokálne nastaven pro verze PHP: 7.2.33 - musí být ručně spuštěn Chrome

 */

if ($_SERVER['REMOTE_ADDR']??0 == '127.0.0.1') {
//  $_GET['test']= 2; // NEZAPISOVAT! a trasovat
//  $_GET['test']= 1; // zapisovat a trasovat
//  $_GET['mail']= 0; // NEZASÍLAT!
}
else {
//  $_GET['test']= 0; $_GET['mail']= 1; // ZASÍLAT + ZAPISOVAT
}

//$_GET['akce']= 3094; // Letní kurz 2025 čl.6270
//$_GET['akce']= 3085; // Jarní obnova 2025
//$_GET['akce']= 3095; // Podzimní obnova 2025
//$_GET['akce']= 2973; // Víkend pro chlapy 2025
//$_GET['akce']= 3093; // Rajnochovice 2025
//$_GET['akce']= 3056; // Muži Ostrava
//$_GET['akce']= 3120; // Pohodový tábor rodin čl.6278

//if ($_GET['akce']==3094)
//  require_once("prihlaska_2025.2.php"); // LK MS 2025
//else
  require_once("prihlaska_2025.3.php"); // odkazem z www.setkani.org
