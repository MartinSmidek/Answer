<?php # (c) 2009 Martin Smidek <martin@smidek.eu>

$mysql_db= 'ezer_ys';
$ezer_db= array(
  'ezer_ys' => array(0,'localhost','gandi','','utf8'),
//   'setkani' => array('192.168.1.203','proglas','pr0gl8s')
  'setkani' => array(0,'localhost','gandi','','latin1')
);

$ezer_mysql_cp= 'UTF8';
$ezer_html_cp= 'UTF-8';

$ezer_root= 'ds';                             // jméno adresáøe a hlavního objektu aplikace

// cesty
$ezer_path_root= "C:/Apache/htdocs/ezer/www-ys2";
$ezer_path_appl= "$ezer_path_root/$ezer_root";
$ezer_path_libr= "$ezer_path_root/$ezer_root";
$ezer_path_docs= "$ezer_path_root/docs";
$ezer_path_code= "$ezer_path_root/$ezer_root/code";
$ezer_path_serv= "$ezer_path_root/ezer2/server";
$ezer_path_svn= null;
$ezer_path_pspad= "c:/Program Files (x86)/PSPad editor/Syntax";

// moduly zahrnuté do aplikace - budou zpracovány i reference.i_doc pro tabulky kompilátoru
$ezer_comp_ezer= "app,ezer,ezer_report,ezer_fdom1,ezer_fdom2";
$ezer_comp_root= "ds/fce";

// moduly v Ezerscriptu mimo složku aplikace
$ezer_ezer= array(
  "ds/ds.dum",
  "ds/ds.tab"
);
// uživatelské moduly v PHP obsažené v $ezer_path_root
$ezer_php= array(
  "ys/ys_fce.php",
  "ys/ys_ucet.php",
  "ys/ys_lide.php",
  "ds/ds_fce.php"
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

// definice ladícího prostøedí
define('TRACE_FB', 0);

// parametrizace standardních modulù
$user_must_log_in= true;
$user_must_log_in= false;
$EZER= (object)array();
$EZER->options->root= $ezer_root;
$EZER->options->app= 'Ans(w)er';
$EZER->options->index= 'anser.html';
$EZER->options->web= 'www.setkani.org';
$EZER->options->mail= 'martin@smidek.eu';
$EZER->activity->touch_limit= 50; // poèet dotykù (ae_hits) po kterých je uskuteènìn zápis do _touch
$EZER->activity->colors= "80:#f0d7e4,40:#e0d7e4,20:#dce7f4,0:#e7e7e7"; // viz system.php::sys_table
$EZER->options->docs_path= "C:/YMCA/";
// $EZER->options->docs_path= "C:/Apache/htdocs/ezer/www-ys2/docs";
$EZER->options->docs_ref= "./docs";

// require_once("./init.php");

// knihovní moduly
require_once("$ezer_path_root/ezer2/ezer2_fce.php");
// moduly aplikace
foreach($ezer_php as $php) {
  require_once("$ezer_path_root/$php");
}
// require_once("$ezer_path_root/libr/libr_fce.php");
// require_once("$ezer_path_appl/ys_fce.php");
//   require_once("$ezer_path_appl/ys_ucet.php");    // funkce pro modul  Ucetnictvi
//   require_once("$ezer_path_appl/ys_lide.php");          // funkce pro modul Lidé
// require_once("$ezer_path_root/ds/ds_fce.php");
?>
