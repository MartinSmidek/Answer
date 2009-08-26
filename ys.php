<?php # (c) 2009 Martin Smidek <martin@smidek.eu>

$mysql_db= 'ezer_test';
$ezer_db= array(
  'ezer_test' => array(0,'localhost','gandi','','utf8'),
//   'setkani' => array('192.168.1.203','proglas','pr0gl8s')
  'setkani' => array(0,'localhost','gandi','','latin1')
);

$ezer_mysql_cp= 'UTF8';
$ezer_html_cp= 'UTF-8';

$ezer_root= 'ys';                             // jméno adresáře a hlavního objektu aplikace

// cesty
$ezer_path_root= "C:/Apache/htdocs/ezer/www-ys2";
$ezer_path_appl= "$ezer_path_root/$ezer_root";
$ezer_path_libr= "$ezer_path_root/$ezer_root";
$ezer_path_docs= "$ezer_path_root/docs";
$ezer_path_code= "$ezer_path_root/$ezer_root/code";
$ezer_path_serv= "$ezer_path_root/ezer2/server";

// moduly zahrnuté do aplikace - budou zpracovány i reference.i_doc pro tabulky kompilátoru
$ezer_comp_ezer= "app,ezer,ezer_report,ezer_fdom1,ezer_fdom2";
$ezer_comp_root= "ds/fce";

// definice ladícího prostředí
define('TRACE_FB', 1);

// parametrizace standardních modulů
$user_must_log_in= true;
$EZER= (object)array();
$EZER->options->root= $ezer_root;
$EZER->options->app= 'anser';
$EZER->options->index= 'anser.html';
$EZER->options->web= 'www.setkani.org';
$EZER->options->mail= 'martin@smidek.eu';
$EZER->activity->touch_limit= 50; // počet dotyků (ae_hits) po kterých je uskutečněn zápis do _touch
$EZER->activity->colors= "80:#f0d7e4,40:#e0d7e4,20:#dce7f4,0:#e7e7e7"; // viz system.php::sys_table
// $EZER->options->docs_path= "C:/YMCA-kopie";
$EZER->options->docs_path= "C:/Apache/htdocs/ezer/www-ys2/docs";
$EZER->options->docs_ref= "./docs";

// require_once("./init.php");

// moduly aplikace
require_once("$ezer_path_appl/ys_fce.php");
require_once("$ezer_path_root/ds/ds_fce.php");
?>
