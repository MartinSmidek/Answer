<?php # Systém An(w)er/Ezer/YMCA Familia a YMCA Setkání, (c) 2008-2015 Martin Šmídek <martin@smidek.eu>

  # nastavení systému Ans(w)er před voláním AJAX
  #   $app        = kořenová podsložka aplikace ... db2
  #   $answer_db  = logický název hlavní databáze (s případným '_test')
  #   $dbs_plus   = pole s dalšími databázemi ve formátu $dbs
  #   $php_lib    = pole s *.php - pro 'ini'

  global // import 
    $ezer_root; 
  global // export
    $EZER, $ezer_server;
  
  // vyzvednutí ostatních hodnot ze SESSION
  $ezer_server=  $_SESSION[$ezer_root]['ezer_server'];
  $kernel= "ezer{$_SESSION[$ezer_root]['ezer']}";
  $abs_root=     $_SESSION[$ezer_root]['abs_root'];
  $rel_root=     $_SESSION[$ezer_root]['rel_root'];
  chdir($abs_root);

//  echo("db2.inc.php start, server=$ezer_server");

  // inicializace objektu Ezer
  $EZER= (object)array(
      'version'=>"ezer{$_SESSION[$ezer_root]['ezer']}",
      'options'=>(object)array(
          'mail' => "martin@smidek.eu",
          'phone' => "603&nbsp;150&nbsp;565",
          'author' => "Martin"
      ),
      'activity'=>(object)array());

  // databáze
  $deep_root= "../files/answer";
  require_once("$deep_root/db2.dbs.php");
  
  $path_backup= "$deep_root/sql";
  
  // cesta k utilitám MySQL/MariaDB
  $ezer_mysql_path= array(
      "C:/Apache/bin/mysql/mysql5.7.21/bin",  // *.bean
      "/volume1/@appstore/MariaDB/usr/bin",   // Synology DOMA
      "/volume1/@appstore/MariaDB/usr/bin"   // Synology YMCA
    )[$ezer_server];

  // definice zápisů do _track
  $tracking= '_track';
  $tracked= ',akce,pobyt,spolu,osoba,tvori,rodina,_user,';
  
  // moduly interpreta zahrnuté do aplikace - budou zpracovány i reference.i_doc pro tabulky kompilátoru
  $ezer_comp_root= "db2/ds_fce3";

  // definice modulů specifických pro Answer
  $k= substr($kernel,4,1)=='3' ? '3' : '';
  $app_php= array(
    "db2/db2_ys2_fce$k.php",
    "db2/db2_fce$k.php"
  );
  
  $ezer= array(
  );
  
  // PDF knihovny
  require_once('tcpdf/tcpdf.php');
  require_once('tcpdf/db2_tcpdf.php');
 
  // je to aplikace se startem v rootu
  chdir($abs_root);
  require_once("{$EZER->version}/ezer_ajax.php");

//  echo("db2.inc.php end<br>");
  // SPECIFICKÉ PARAMETRY
  global $USER;
  $VPS= isset($USER) ? ($USER->org==1 ? 'VPS' : 'PPS') : 'VPS';
  $EZER->options->org= 'YMCA Familia, YMCA Setkání';
?>
