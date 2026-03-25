<?php
/*
  (c) 2025-2026 Martin Smidek <martin@smidek.eu>

  přepínač na aktuální verzi online přihlašování pro YMCA Setkání a YMCA Familia 

 */

//  $_GET['test']= 0; $_GET['mail']= 1; // ZASÍLAT + ZAPISOVAT
//  $_GET['test']= 2; $_GET['mail']= 0; // NEZASÍLAT + NEZAPISOVAT

if (!isset($_GET['org'])) $_GET['org']= 1; 
require_once("prihlaska_2025.5.php"); // odkazem z www.setkani.org
