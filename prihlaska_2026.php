<?php
/*
  (c) 2025-2026 Martin Smidek <martin@smidek.eu>

  přepínač na aktuální verzi online přihlašování pro YMCA Setkání a YMCA Familia 
  debuger je lokálne nastaven pro verze PHP: 7.2.33 - musí být ručně spuštěn Chrome

 */

//  $_GET['test']= 0; $_GET['mail']= 1; // ZASÍLAT + ZAPISOVAT
//  $_GET['test']= 2; $_GET['mail']= 0; // NEZASÍLAT + NEZAPISOVAT

if (!isset($_GET['org'])) $_GET['org']= 1; 
require_once("prihlaska_2025.4.php"); // odkazem z www.setkani.org
