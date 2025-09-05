<?php # Systém An(w)er/Ezer/YMCA Familia a YMCA Setkání, (c) 2008-2015 Martin Šmídek <martin@smidek.eu>

  # nastavení systému Ans(w)er před voláním AJAX
  #   $answer_db  = logický název hlavní databáze 

  global // import 
    $ezer_root; 
  global // export
    $EZER, $ezer_server, $ezer_version;
  
  // vyzvednutí ostatních hodnot ze SESSION
  $ezer_server=  $_SESSION[$ezer_root]['ezer_server'];
  $ezer_version= $_SESSION[$ezer_root]['ezer'];
  $abs_root=     $_SESSION[$ezer_root]['abs_root'];
  $rel_root=     $_SESSION[$ezer_root]['rel_root'];
  chdir($abs_root);

  // inicializace objektu Ezer
  $EZER= (object)array(
      'version'=>'ezer3.2', // "ezer{$_SESSION[$ezer_root]['ezer']}",
      'options'=>(object)array(
          'mail' => "martin@smidek.eu",
          'phone' => "603&nbsp;150&nbsp;565",
          'author' => "Martin"
      ),
      'activity'=>(object)array());
      
  // informace pro debugger
  $dbg_info= (object)array(
    'src_path'  => array('ms50','db2','ezer3.2') // poloha a preference zdrojových modulů
  );

  // databáze
  $deep_root= "../files/answer";
  require_once("$deep_root/ms50.dbs.php");
  
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
  
  // definice modulů specifických pro Answer
  $app_php= array(
    "db2/db-akce.php",
    "db2/db-bank.php",
    "db2/db-cenik.php",
    "db2/db-data.php",
    "db2/db-dotaznik.php",
    "db2/db-dum.php",
    "db2/db-elim.php",
    "db2/db-evid.php",
    "db2/db-foto.php",
    "db2/db-lib.php",
    "db2/db-mail.php",
    "db2/db-mapa.php",
    "db2/db-pece.php",
    "db2/db-pokl.php",
    "db2/db-prihl.php",
    "db2/db-stat.php",
    "db2/db-tisk.php",
    "db2/db-ucast.php",
    "db2/db-obsolete.php",
    "db2/db2_tcpdf.php",
    "ezer$ezer_version/server/ezer_ruian.php",
  );
  
  $ezer= array(
  );
  
  // PDF knihovny
  require_once('tcpdf/tcpdf.php');
//  require_once('tcpdf/db2_tcpdf.php');
 
  // je to aplikace se startem v rootu
  chdir($abs_root);
  require_once("{$EZER->version}/ezer_ajax.php");

//  echo("db2.inc.php end<br>");
  // SPECIFICKÉ PARAMETRY
  global $USER;
  $VPS= 'VPS';
  $EZER->options->org= 'Centrum pro rodinu, Vysočina';
