<?php
  // detekce aktivního serveru
  $ezer_server= 
    $_SERVER["SERVER_NAME"]=='answer.bean'        ? 0 : (        // 0:lokální 
    $_SERVER["SERVER_NAME"]=='mfis.proglas.cz'    ? 1 : (        // 1:matouš
    $_SERVER["SERVER_NAME"]=='answer.setkani.org' ? 2 : (        // 2:synology YMCA
    $_SERVER["SERVER_NAME"]=='tutorial.doma'      ? 3 : -1)));   // 3:synology DOMA

//  $kernel= "ezer".(isset($_GET['ezer']) ? $_GET['ezer'] : '3.1'); 
  $kernel= "ezer3.1"; 
  // test přihlášení potvrzeného mailem
  $login= $ezer_server ? '1' : (isset($_GET['login']) ? $_GET['login'] : '0');       

  // parametry aplikace LAB
  $app_name=  "Test ";
  $app_root=  'test';
  $app_js=    array(
//      "/tut/i_fce.js"
      );
//  // fakultativní přidání Hightcharts
//  if (isset($_GET['hightcharts'])) {
//    $code= "../ezer3.1/client/licensed/highcharts/code";
//    $app_js[]= "$code/highcharts.js";
//    $app_js[]= "$code/highcharts-more.js";
//    $app_js[]= "$code/modules/exporting.js";
//    $app_js[]= "$code/modules/export-data.js";
//  }
  $app_css=   array(
//      "tut/tut.css.php","$kernel/client/wiki.css"
      );
  $skin=      'ck';

  $abs_roots= array(
      "C:/Ezer/beans/answer",
      "/home/www/ezer/www-fis/3",
      "/volume1/web/www/answer",
      "/volume1/web/www/tutorial"
    );
  $rel_roots= array(
      "http://answer.bean:8080",
      "https://mfis.proglas.cz/3",
      "https://answer.setkani.org",
      "http://tutorial.doma"
    );
  $path_foto= "{$abs_roots[$ezer_server]}/fotky";

  // (re)definice Ezer.options
  $favicons= array('test_local.png','test.png','test_dsm.png');
  $add_pars= array(
    'favicon' => $favicons[$ezer_server]
  );
  $add_options= (object)array(
    'watch_git'    => 1,                // sleduj git-verzi aplikace a jádra, při změně navrhni restart
//    'login_interval'   => 2,          // povolená nečinnost v minutách - default=2 hodiny
//    'gc_maxlifetime' => 1440,         // životnost nečinné SESSION v sekundách - default=12 hodin
    'path_foto'    => "'$path_foto'",   // absolutní cesta do složky fotky
    'curr_version' => 1,                // při přihlášení je nahrazeno nejvyšší ezer_kernel.version
    'curr_users'   => 1                 // zobrazovat v aktuální hodině aktivní uživatele
  );

  // vynucení přihlášení
  if ( $login ) {
    $add_pars= array_merge($add_pars,array(
//      'no_local'  => 1,   // true = testy přihlašování
//      'watch_pin' => 1,   // true = povolit mobilní přístup jen po vložení PINu odeslaného mailem
      'watch_key' => $ezer_server<=1 ? 0 : 1,   // true = povolit přístup jen po vložení klíče
      'watch_ip'  => $ezer_server<=1 ? 0 : 1    // true = povolit přístup jen ze známých IP adres
//      'watch_key' => 1,                         // true = povolit přístup jen po vložení klíče i lokálně
//      'watch_ip'  => 1                          // true = povolit přístup jen ze známých IP adres
    ));
  }
  else {
    $app_login= 'Guest/';                   // pouze pro automatické přihlášení
  }
  
  // je to aplikace se startem v podsložce
  require_once("../$kernel/ezer_main.php");

  ?>
