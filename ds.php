<?php # (c) 2009 Martin Smidek <martin@smidek.eu>

$mysql_db= 'ezer_test';
$ezer_db= array(
  'setkani' => array('192.168.1.203','proglas','pr0gl8s'),
  'ezer_test' => array('localhost','gandi','')
);

$ezer_mysql_cp= 'UTF8';
$ezer_html_cp= 'UTF-8';

$ezer_root= 'ds';                             // jméno adresáře a hlavního objektu aplikace

// cesty
$ezer_path_appl= "C:/Apache/htdocs/ezer/www-ys2/$ezer_root";
$ezer_path_libr= "C:/Apache/htdocs/ezer/www-ys2/libr";
$ezer_path_docs= "C:/Apache/htdocs/ezer/www-ys2/docs";
$ezer_path_code= "C:/Apache/htdocs/ezer/www-ys2/$ezer_root/code";
$ezer_path_serv= "C:/Apache/htdocs/ezer/www-ys2/ezer2/server";

// definice ladícího prostředí
define('TRACE_FB', 1);

// parametrizace standardních modulů
$user_must_log_in= false;
$EZER= (object)array();
$EZER->options->root= $ezer_root;
$EZER->options->app= 'dum';
$EZER->options->index= 'ds.html';
$EZER->options->web= 'www.setkani.org';
$EZER->options->mail= 'martin@smidek.eu';
$EZER->activity->touch_limit= 50; // počet dotyků (ae_hits) po kterých je uskutečněn zápis do _touch
$EZER->activity->colors= "80:#f0d7e4,40:#e0d7e4,20:#dce7f4,0:#e7e7e7"; // viz system.php::sys_table

// obecné moduly
require_once("$ezer_path_libr/ys_fce.php");

// moduly aplikace
require_once("$ezer_path_appl/ds_fce.php");
?>
