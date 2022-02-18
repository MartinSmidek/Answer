<?php # Systém An(w)er/Ezer/YMCA Familia a YMCA Setkání, (c) 2008-2015 Martin Šmídek <martin@smidek.eu>

  # nastavení systému Ans(w)er před voláním AJAX
  #   $app        = kořenová podsložka aplikace ... db2
  #   $answer_db  = logický název hlavní databáze (s případným '_test')
  #   $dbs_plus   = pole s dalšími databázemi ve formátu $dbs
  #   $php_lib    = pole s *.php - pro 'ini'

  global // import 
    $ezer_root; 
  global // export
    $EZER, $ezer_server, $ezer_version;
  global // klíče
    $api_gmail_user, $api_gmail_pass;
  
  // vyzvednutí ostatních hodnot ze SESSION
  $ezer_server=  $_SESSION[$ezer_root]['ezer_server'];
  $ezer_version= "ezer{$_SESSION[$ezer_root]['ezer']}";
  $abs_root=     $_SESSION[$ezer_root]['abs_root'];
  $rel_root=     $_SESSION[$ezer_root]['rel_root'];
  chdir($abs_root);

  // databáze
  $deep_root= "../files/answer";
  require_once("$deep_root/db2.dbs.php");
  
  $path_backup= "$deep_root/sql";
  
  // inicializace objektu Ezer
  $EZER= (object)array(
      'version'=>"ezer{$_SESSION[$ezer_root]['ezer']}",
      'options'=>(object)array(
          'mail' => "martin@smidek.eu",
          'phone' => "603&nbsp;150&nbsp;565",
          'author' => "Martin"
      ),
      'activity'=>(object)array(),
      'CMS'=>(object)array(
        'GMAIL'=>(object)array(
//          'TEST'=>1,
          'mail'=>$api_gmail_user, // adresa odesílatele mailů
          'name'=>'YMCA Setkání', 
          'pswd'=>$api_gmail_pass
      ))
    );

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
  $app_php= array(
    "db2/db2_ys2_fce3.php",
    "db2/db2_fce3.php",
    "$ezer_version/server/ezer_ruian.php",
    "$ezer_version/server/ezer_cms3.php"
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
