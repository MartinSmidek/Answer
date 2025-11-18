<?php
/*
  (c) 2025-2026 Martin Smidek <martin@smidek.eu>

  přepínač na aktuální verzi online přihlašování pro YMCA Setkání a YMCA Familia 
  debuger je lokálne nastaven pro verze PHP: 7.2.33 - musí být ručně spuštěn Chrome

 */

if ($_SERVER['REMOTE_ADDR']??0 == '127.0.0.1') {
  $_GET['test']= 2; // NEZAPISOVAT! a trasovat
//  $_GET['test']= 1; // zapisovat a trasovat
  $_GET['mail']= 0; // NEZASÍLAT!
}
else {
  $_GET['test']= 0; $_GET['mail']= 1; // ZASÍLAT + ZAPISOVAT
}

require_once("prihlaska_2025.4.php"); // odkazem z www.setkani.org
