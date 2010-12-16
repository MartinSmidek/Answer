<?php # (c) 2009 Martin Smidek <martin@smidek.eu>

  require_once("ezer2/server/ae_slib.php");

  // OBECNÉ PARAMETRY

  // parametry s první hodnotou pro server a druhou (případně) pro local
  $db= array('ezer_ys','ezer_test');
  $dbs= array(
    array(
      'ezer_ys' =>      array(0,'localhost','gandi','r8d0st','utf8'),
      'setkani' =>      array(0,'192.168.1.146','gandi','','cp1250')),
    array(
      'ezer_test' =>    array(0,'localhost','gandi','','utf8'),
      'setkani' =>      array(0,'localhost','gandi','','cp1250'))
  );
  // kořeny cest
  $path_root=  array("/home/www/ezer/www-ys/2","C:/Apache/htdocs/ezer/www-ys2");
  $path_pspad= array(null,"C:/Program Files (x86)/PSPad editor/Syntax");
  // ostatní parametry
  $tracking= '_track';
  $tracked= ',clen,dar,ka,davka,_user,';
  root_inc($db,$dbs,$tracking,$tracked,$path_root,$path_pspad);

  // PARAMETRY SPECIFICKÉ PRO APLIKACI

  // moduly interpreta zahrnuté do aplikace - budou zpracovány i reference.i_doc pro tabulky kompilátoru
  $ezer_comp_ezer= "app,ezer,ezer_report,ezer_fdom1,ezer_fdom2";
  $ezer_comp_root= "ds/fce,ys/ys_fce";
  // moduly v Ezerscriptu mimo složku aplikace
  $ezer_ezer= array(
    "ds/ds.dum",
    "ds/ds.tab"
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
    "ys/ys_akce.php",
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
  // vložení modulů
  foreach($ezer_php as $php) {
    require_once("$ezer_path_root/$php");
  }

/*
$ezer_root= 'test';                           // jméno adresáře a hlavního objektu aplikace

$mysql_db= 'ezer_test';
$ezer_db= array(
  'ezer_test' => array(0,'localhost','gandi','','utf8')
);
$ezer_html_cp= 'UTF-8';
$ezer_sylk_cp= 'windows-1250';

// cesty
$ezer_path_root= "C:/Apache/htdocs/ezer/www-ys2";
$ezer_path_appl= "$ezer_path_root/$ezer_root";
$ezer_path_libr= "$ezer_path_root/libr";
$ezer_path_docs= "$ezer_path_root/docs";
$ezer_path_code= "$ezer_path_root/$ezer_root/code";
$ezer_path_serv= "$ezer_path_root/ezer2/server";
$ezer_path_svn= null;
$ezer_path_pspad= "c:/Program Files (x86)/PSPad editor/Syntax";

// moduly zahrnuté do aplikace - budou zpracovány i reference.i_doc pro tabulky kompilátoru
$ezer_comp_ezer= "app,ezer,ezer_report,ezer_fdom1,ezer_fdom2";
$ezer_comp_root= "test/fce";

// moduly v Ezerscriptu mimo složku aplikace
$ezer_ezer= array(
);
// uživatelské moduly v PHP obsažené v $ezer_path_root
$ezer_php= array(
  "test/test_fce.php"
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

// definice ladícího prostředí
define('TRACE_FB', 1);

// parametrizace standardních modulů
$user_must_log_in= false;
$EZER= (object)array();
$EZER->options->root= $ezer_root;
$EZER->options->app= 'test';
$EZER->options->index= 'test.php';
$EZER->options->web= 'www.proglas.cz';
$EZER->options->mail= 'smidek@proglas.cz';
$EZER->activity->touch_limit= 50; // počet dotyků (ae_hits) po kterých je uskutečněn zápis do _touch
$EZER->activity->colors= "80:#f0d7e4,40:#e0d7e4,20:#dce7f4,0:#e7e7e7"; // viz system.php::sys_table

// knihovní moduly
require_once("$ezer_path_root/ezer2/ezer2_fce.php");
// moduly aplikace
foreach($ezer_php as $php) {
  require_once("$ezer_path_root/$php");
}
*/
?>
