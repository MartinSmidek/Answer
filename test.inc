<?php # (c) 2010 Martin Smidek <martin@smidek.eu>
  error_reporting(E_ALL ^ E_NOTICE);
  // nastavení verze jádra
  $EZER= (object)array();
	$ev= ($_GET['ezer'] ? $_GET['ezer'] : ($_COOKIE['ev'] ? $_COOKIE['ev'] : 2));
  setcookie("ev",$ev,time()+3600*24*30);
  $EZER->version= "ezer{$ev}";
  $EZER->version= "ezer2.2";

  $ezer_local= preg_match('/^\w+\.ezer/',$_SERVER["SERVER_NAME"]); // identifikace ladícího serveru

  require_once("{$EZER->version}/server/ae_slib.php");

  // OBECNÉ PARAMETRY

  // parametry s první hodnotou pro server a druhou (případně) pro local
  //   databáze => (,server,username,userpass,kódování,[jméno databáze])
  // databáze 'ezer_system' obsahuje platnou tabulku _user
  // (fyzické jméno databáze může být změněno pátým členem v tabulce $dbs)
  $db= array('ezer_test','ezer_test');
  $dbs= array(
    array(
      'ezer_test'     => array(0,'localhost','gandi','r8d0st','utf8'),
      'ezer_system'   => array(0,'localhost','gandi','r8d0st','utf8','ezer_test')),
    array(
      'ezer_test'     => array(0,'localhost','gandi','','utf8'),
      'ezer_system'   => array(0,'localhost','gandi','','utf8','ezer_test'))
  );
  // kořeny cest
  $path_root=  array("/home/www/ezer/www-ys/2","C:/Apache/htdocs/ezer/www-ys2");
  $path_pspad= array(null,"C:/Program Files (x86)/PSPad editor/Syntax");
  // ostatní parametry
  $tracking= '_track';
  $tracked= ',_user,';
  root_inc($db,$dbs,$tracking,$tracked,$path_root,$path_pspad);

  // PARAMETRY SPECIFICKÉ PRO APLIKACI

  // specifické cesty

  // moduly interpreta zahrnuté do aplikace - budou zpracovány i reference.i_doc pro tabulky kompilátoru
  $ezer_comp_ezer= "app,ezer,area,ezer_report,ezer_fdom1,ezer_fdom2";
  $ezer_comp_root= "test/fce";
  // moduly v Ezerscriptu mimo složku aplikace
  $ezer_ezer= array(
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
    "{$EZER->version}/ezer2_fce.php",
//     'test/test_fce_fis.php',      /* různé API od Google */
    'test/test_fis_google.php',
    'test/test.tut.php',
    'test/test.cg.php',
    'test/cg.php'
  );

  // parametrizace $EZER
  $EZER->options->web=    'www.setkani.org';
  $EZER->options->author= 'Gándí';
  $EZER->options->mail=   'martin@smidek.eu'; //'smidek@proglas.cz';
  $EZER->options->phone=  'kl.523';
  $EZER->options->skype=  'martin_smidek';
  $EZER->activity->skip=  'GAN';      // viz system.php::sys_table
  // PDF knihovny
  require_once('tcpdf/config/lang/eng.php');
  require_once('tcpdf/tcpdf.php');
  // vložení modulů
  foreach($ezer_php as $php) {
    require_once("$ezer_path_root/$php");
  }

?>
