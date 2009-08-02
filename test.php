<?php # (c) 2009 Martin Smidek <martin@smidek.eu>

$ezer_root= 'test';                           // jméno adresáře a hlavního objektu aplikace

$mysql_db= 'ezer_test';
$ezer_db= array(
  'ezer_test' => array(0,'localhost','gandi','','utf8')
);
$ezer_html_cp= 'UTF-8';
$ezer_sylk_cp= 'windows-1250';

// cesty
$ezer_path_appl= "C:/Apache/htdocs/ezer/www-ys2/$ezer_root";
$ezer_path_libr= "C:/Apache/htdocs/ezer/www-ys2/libr";
$ezer_path_docs= "C:/Apache/htdocs/ezer/www-ys2/docs";
$ezer_path_code= "C:/Apache/htdocs/ezer/www-ys2/$ezer_root/code";
$ezer_path_serv= "C:/Apache/htdocs/ezer/www-ys2/ezer2/server";

$user_must_log_in= false;
// definice ladícího prostředí
define('TRACE_FB', 1);

// parametrizace standardních modulů
$EZER= (object)array();
$EZER->options->root= $ezer_root;
$EZER->options->app= 'test';
$EZER->options->index= 'test.html';
$EZER->options->web= 'www.proglas.cz';
$EZER->options->mail= 'smidek@proglas.cz';
$EZER->activity->touch_limit= 50; // počet dotyků (ae_hits) po kterých je uskutečněn zápis do _touch
$EZER->activity->colors= "80:#f0d7e4,40:#e0d7e4,20:#dce7f4,0:#e7e7e7"; // viz system.php::sys_table

// require_once("./init.php");
?>
