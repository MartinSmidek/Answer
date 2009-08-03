<?php # (c) 2009 Martin Smidek <martin@smidek.eu>


$ezer_root= 'libr';                           // jméno adresáře a hlavního objektu aplikace
$ezer_name= 'libr';                           // jméno aplikace

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

$user_must_log_in= false;
// definice ladícího prostředí
define('TRACE_FB', 1);

// parametrizace standardních modulů
$EZER= (object)array();
$EZER->options->root= $ezer_root;
$EZER->options->app= $ezer_name;
$EZER->options->index= "$ezer_name.html";
$EZER->options->web= 'www.proglas.cz';
$EZER->options->mail= 'smidek@proglas.cz';
$EZER->activity->touch_limit= 50; // počet dotyků (ae_hits) po kterých je uskutečněn zápis do _touch
$EZER->activity->colors= "80:#f0d7e4,40:#e0d7e4,20:#dce7f4,0:#e7e7e7"; // viz system.php::sys_table

// require_once("./init.php");
?>
