<?php # (c) 2009-2011 Martin Smidek <martin@smidek.eu>

  $ezer_local= preg_match('/^\w+\.ezer/',$_SERVER["SERVER_NAME"]); // identifikace ladícího serveru

  require_once("ezer2/server/ae_slib.php");

  // OBECNÉ PARAMETRY

  // parametry s první hodnotou pro server a druhou (případně) pro local
  $db= array('ezer_ys','ezer_ys');
  $dbs= array(
    array( /* ostré */
      'ezer_ys' =>      array(0,'localhost','gandi','r8d0st','utf8'),
      'ezer_system' =>  array(0,'localhost','gandi','r8d0st','utf8','ezer_ys'),
      'setkani' =>      array(0,'192.168.1.146','gandi','','cp1250')),
    array( /* lokální */
      'ezer_ys' =>      array(0,'localhost','gandi','','utf8'),
      'ezer_system' =>  array(0,'localhost','gandi','','utf8','ezer_ys'),
      'setkani' =>      array(0,'localhost','gandi','','cp1250'))
  );
  // kořeny cest
  $path_root=  array("/home/www/ezer/www-ys/2","C:/Apache/htdocs/ezer/www-ys2");
  $path_pspad= array(null,"C:/Program Files (x86)/PSPad editor/Syntax");
  // cesty pro zálohy
  $path_backup= array("{$path_root[0]}/zalohy","C:/Apache/htdocs/ezer/www-ys2/zalohy");
  $path_backup= $path_backup[$ezer_local?1:0];          // složka záloh databází
  $ezer_mysql_path= array("","c:/apache/mysql/bin/");
  $ezer_mysql_path= $ezer_mysql_path[$ezer_local?1:0];  // cesta k utilitě mysql (i s lomítkem)
  // cesty pro zadávání url
  $path_url= array("http://answer.setkani.org/ys.php","http://ys2.ezer/ys.php");
  $path_url= $path_url[$ezer_local?1:0];                // prefix url
  // ostatní parametry
  $tracking= '_track';
  $tracked= ',akce,pobyt,spolu,osoba,tvori,rodina,_user,';
  root_inc($db,$dbs,$tracking,$tracked,$path_root,$path_pspad);

  // PARAMETRY SPECIFICKÉ PRO APLIKACI

  // moduly interpreta zahrnuté do aplikace - budou zpracovány i reference.i_doc pro tabulky kompilátoru
  $ezer_comp_ezer= "app,ezer,ezer_report,ezer_fdom1,ezer_fdom2";
  $ezer_comp_root= "ds/fce,ys/ys_fce";
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
    "ys/ys_fce.php",
    "ys/ys_ucet.php",
    "ys/ys_lide.php",
    "db/db_akce.php",
    "fis/fis_tcpdf.php",
    "ds/ds_fce.php"
  );

  // parametrizace $EZER
  $EZER->options->web=    'www.setkani.org';
  $EZER->options->author= 'Martin';
  $EZER->options->mail=   'martin@smidek.eu';
  $EZER->options->phone=  '603150565';
  $EZER->options->skype=  'martin_smidek';
  $EZER->activity->skip=  'GAN';      // viz system.php::sys_table

  // knihovní moduly
  require_once("$ezer_path_root/ezer2/ezer2_fce.php");
  // PDF knihovny
  require_once('tcpdf/config/lang/eng.php');
  require_once('tcpdf/tcpdf.php');
  // vložení modulů
  foreach($ezer_php as $php) {
    require_once("$ezer_path_root/$php");
  }

?>