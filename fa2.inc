<?php # (c) 2009-2011 Martin Smidek <martin@smidek.eu>

  $ezer_root= 'fa2';                                             // adresář a hlavní objekt aplikace
  $answer_db= $_SESSION[$ezer_root]['GET']['test'] ? 'ezer_fa_test' : 'ezer_fa'; // hlavní databáze

  // nastavení verze jádra
  $EZER= (object)array();
  $EZER->version= isset($_GET['ezer']) ? "ezer{$_GET['ezer']}" : 'ezer2.2';

  $ezer_local= preg_match('/^\w+\.ezer|192.168/',$_SERVER["SERVER_NAME"]); // identifikace ladícího serveru


  // android
  $android=    preg_match('/android|x11/i',$_SERVER['HTTP_USER_AGENT']);
  $ezer_ksweb= $android && $_SERVER["SERVER_NAME"]=="localhost"; // identifikace ladícího serveru KSWEB/Android

  require_once("{$EZER->version}/server/ae_slib.php");

  // OBECNÉ PARAMETRY
  $VPS= 'PPS';

  // parametry s první hodnotou pro server a druhou (případně) pro local
  $db= array('ezer_fa','ezer_fa');
  $dbs= array( $ezer_ksweb
  ? array( /* lokální android */
      'ezer_fa' =>      array(0,'localhost','root','','utf8',$answer_db),
      'ezer_system' =>  array(0,'localhost','root','','utf8',$answer_db))
  : array( /* ostré */
      'ezer_fa' =>      array(0,'localhost','gandi','r8d0st','utf8',$answer_db),
      'ezer_ys' =>      array(0,'localhost','gandi','r8d0st','utf8'),
      'ezer_system' =>  array(0,'localhost','gandi','r8d0st','utf8',$answer_db)),
    array( /* lokální */
      'ezer_fa' =>      array(0,'localhost','gandi','','utf8',$answer_db),
      'ezer_ys' =>      array(0,'localhost','gandi','','utf8'),
      'ezer_system' =>  array(0,'localhost','gandi','','utf8',$answer_db))
  );
  // kořeny cest
  $path_root=  array($ezer_ksweb?"/storage/sdcard0/htdocs/www-ys2":
    "/home/www/ezer/www-ys/2","C:/Apache/htdocs/ezer/www-ys2");
  $path_pspad= array(null,"C:/Program Files (x86)/PSPad editor/Syntax");
  // cesty pro zálohy
  $path_backup= array("{$path_root[0]}/zalohy","C:/Apache/htdocs/ezer/www-ys2/zalohy");
  $path_backup= $path_backup[$ezer_local?1:0];          // složka záloh databází
  $ezer_mysql_path= array("/usr/bin/","c:/apache/mysql/bin/");
  $ezer_mysql_path= $ezer_mysql_path[$ezer_local?1:0];  // cesta k utilitě mysql (i s lomítkem)
  // cesty pro zadávání url
  $path_url= array("http://answer.setkani.org/{$ezer_root}.php","http://ys2.ezer/{$ezer_root}.php");
  $path_url= $path_url[$ezer_local?1:0];                // prefix url
  // ostatní parametry
  $tracking= '_track';
  $tracked= ',chlapi,akce,pobyt,spolu,osoba,tvori,rodina,_user,';
  root_inc($db,$dbs,$tracking,$tracked,$path_root,$path_pspad);

  // PARAMETRY SPECIFICKÉ PRO APLIKACI

  // moduly interpreta zahrnuté do aplikace - budou zpracovány i reference.i_doc pro tabulky kompilátoru
  $ezer_comp_ezer= "app,ezer,ezer_report,ezer_fdom1,ezer_fdom2";
  $ezer_comp_root= "db/db_fce";
  // moduly v Ezerscriptu mimo složku aplikace
  $ezer_ezer= array(
    "ds/ds.dum",
    "ds/ds.tab",
    "db/db.akce",
    "db/db.akce.chl",
    "db/db.akce.lst",
    "db/db.akce.ducast",
    "db/db.akce.evi",
    "db/db.akce.ema"
  );
  // standardní moduly v PHP obsažené v $ezer_path_root/ezer2 - vynechané v dokumentaci
  $ezer_php_libr= array(
    'server/session.php',
    'server/ae_slib.php',
    'server/reference.php',
    'ezer2_fce.php',
    'server/sys_doc.php',
    'server/ezer2.php'
  );
  // uživatelské i knihovní moduly v PHP obsažené v $ezer_path_root
  $ezer_php= array(
    "fa/fa_fce.php",
//     "ys/ys_ucet.php",
    "db/db_akce.php",
    "db/db_akce2.php",
    "fis/fis_tcpdf.php",
//     "ds/ds_fce.php",
    "fa/fa_lide.php"
  );

  // parametrizace $EZER
  $EZER->options->local= $ezer_local;
  $EZER->options->org=    'YMCA Familia';
  $EZER->options->web=    'www.setkani.org';
  $EZER->options->author= 'Martin';
  $EZER->options->mail=   'martin@smidek.eu';
  $EZER->options->mail_subject='';
  $EZER->options->phone=  '603150565';
  $EZER->options->skype=  'martin_smidek';
  $EZER->activity->skip=  'GAN';      // viz system.php::sys_table
  $EZER->todo= (object)array();
  $EZER->todo->notify=     "new";                // poslat mail o novém požadavku
  $EZER->todo->cond_browse= "m:1;sp:cast>1";     // viz ezer2.syst.ezer panel needs
  $EZER->todo->cond_select= "m:1;sp:data>1";     // vybere se 1. vhodné

  // knihovní moduly
  require_once("$ezer_path_root/{$EZER->version}/ezer2_fce.php");
  // PDF knihovny
  require_once('tcpdf/config/lang/eng.php');
  require_once('tcpdf/tcpdf.php');
  // vložení modulů
  foreach($ezer_php as $php) {
    require_once("$ezer_path_root/$php");
  }

?>