<?php # (c) 2009 Martin Smidek <martin@smidek.eu>

  $ezer_local= preg_match('/^\w+\.ezer/',$_SERVER["SERVER_NAME"]); // identifikace lad�c�ho serveru

  require_once("ezer2/server/ae_slib.php");

  // OBECN� PARAMETRY

  // parametry s prvn� hodnotou pro server a druhou (p��padn�) pro local
  $db= array('ezer_ys','ezer_ys');
  $dbs= array(
    array( /* ostr� */
      'ezer_ys' =>      array(0,'localhost','gandi','r8d0st','utf8'),
      'ezer_system' =>  array(0,'localhost','gandi','r8d0st','utf8','ezer_ys'),
      'setkani' =>      array(0,'192.168.1.146','gandi','','cp1250')),
    array( /* lok�ln� */
      'ezer_ys' =>      array(0,'localhost','gandi','','utf8'),
      'ezer_system' =>  array(0,'localhost','gandi','','utf8','ezer_ys'),
      'setkani' =>      array(0,'localhost','gandi','','cp1250'))
  );
  // ko�eny cest
  $path_root=  array("/home/www/ezer/www-ys/2","C:/Apache/htdocs/ezer/www-ys2");
  $path_pspad= array(null,"C:/Program Files (x86)/PSPad editor/Syntax");
  // ostatn� parametry
  $tracking= '_track';
  $tracked= ',clen,dar,ka,davka,_user,';
  root_inc($db,$dbs,$tracking,$tracked,$path_root,$path_pspad);

  // PARAMETRY SPECIFICK� PRO APLIKACI

  // moduly interpreta zahrnut� do aplikace - budou zpracov�ny i reference.i_doc pro tabulky kompil�toru
  $ezer_comp_ezer= "app,ezer,ezer_report,ezer_fdom1,ezer_fdom2";
  $ezer_comp_root= "ds/fce,ys/ys_fce";
  // moduly v Ezerscriptu mimo slo�ku aplikace
  $ezer_ezer= array(
    "ds/ds.dum",
    "ds/ds.tab"
  );
  // standardn� moduly v PHP obsa�en� v $ezer_path_root/ezer2 - vynechan� v dokumentaci
  $ezer_php_libr= array(
    'server/session.php',
    'server/ae_slib.php',
    'server/reference.php',
    'ezer2_fce.php',
    'server/sys_doc.php',
    'server/ezer2.php'
  );
  // u�ivatelsk� i knihovn� moduly v PHP obsa�en� v $ezer_path_root
  $ezer_php= array(
    "ys/ys_fce.php",
    "ys/ys_ucet.php",
    "ys/ys_lide.php",
    "db/db_akce.php",
    "ds/ds_fce.php"
  );

  // parametrizace $EZER
  $EZER->options->web=    'www.setkani.org';
  $EZER->options->author= 'Martin';
  $EZER->options->mail=   'martin@smidek.eu';
  $EZER->options->phone=  '603150565';
  $EZER->options->skype=  'martin_smidek';
  $EZER->activity->skip=  'GAN';      // viz system.php::sys_table

  // knihovn� moduly
  require_once("$ezer_path_root/ezer2/ezer2_fce.php");
  // vlo�en� modul�
  foreach($ezer_php as $php) {
    require_once("$ezer_path_root/$php");
  }

/*
$mysql_db= 'ezer_ys';
$ezer_db= array(
  'ezer_ys' => array(0,'localhost','gandi','','utf8'),
//   'setkani' => array('192.168.1.203','proglas','pr0gl8s')
  'setkani' => array(0,'localhost','gandi','','latin1')
);

$ezer_mysql_cp= 'UTF8';
$ezer_html_cp= 'UTF-8';

$ezer_root= 'ds';                             // jm�no adres��e a hlavn�ho objektu aplikace

// cesty
$ezer_path_root= "C:/Apache/htdocs/ezer/www-ys2";
$ezer_path_appl= "$ezer_path_root/$ezer_root";
$ezer_path_libr= "$ezer_path_root/$ezer_root";
$ezer_path_docs= "$ezer_path_root/docs";
$ezer_path_code= "$ezer_path_root/$ezer_root/code";
$ezer_path_serv= "$ezer_path_root/ezer2/server";
$ezer_path_svn= null;
$ezer_path_pspad= "c:/Program Files (x86)/PSPad editor/Syntax";

// moduly zahrnut� do aplikace - budou zpracov�ny i reference.i_doc pro tabulky kompil�toru
$ezer_comp_ezer= "app,ezer,ezer_report,ezer_fdom1,ezer_fdom2";
$ezer_comp_root= "ds/fce";

// moduly v Ezerscriptu mimo slo�ku aplikace
$ezer_ezer= array(
  "ds/ds.dum",
  "ds/ds.tab"
);
// u�ivatelsk� moduly v PHP obsa�en� v $ezer_path_root
$ezer_php= array(
  "ys/ys_fce.php",
  "ys/ys_ucet.php",
  "ys/ys_lide.php",
  "ds/ds_fce.php"
);
// standardn� moduly v PHP obsa�en� v $ezer_path_root/ezer2 - vynechan� v dokumentaci
$ezer_php_libr= array(
  'server/session.php',
  'server/ae_slib.php',
  'server/reference.php',
  'ezer2_fce.php',
  'server/sys_doc.php',
  'server/ezer2.php'
);

// definice lad�c�ho prost�ed�
define('TRACE_FB', 0);

// parametrizace standardn�ch modul�
$user_must_log_in= true;
$user_must_log_in= false;
$EZER= (object)array();
$EZER->options->root= $ezer_root;
$EZER->options->app= 'Ans(w)er';
$EZER->options->index= 'anser.html';
$EZER->options->web= 'www.setkani.org';
$EZER->options->mail= 'martin@smidek.eu';
$EZER->activity->touch_limit= 50; // po�et dotyk� (ae_hits) po kter�ch je uskute�n�n z�pis do _touch
$EZER->activity->colors= "80:#f0d7e4,40:#e0d7e4,20:#dce7f4,0:#e7e7e7"; // viz system.php::sys_table
$EZER->options->docs_path= "C:/YMCA/";
// $EZER->options->docs_path= "C:/Apache/htdocs/ezer/www-ys2/docs";
$EZER->options->docs_ref= "./docs";

// require_once("./init.php");

// knihovn� moduly
require_once("$ezer_path_root/ezer2/ezer2_fce.php");
// moduly aplikace
foreach($ezer_php as $php) {
  require_once("$ezer_path_root/$php");
}
// require_once("$ezer_path_root/libr/libr_fce.php");
// require_once("$ezer_path_appl/ys_fce.php");
//   require_once("$ezer_path_appl/ys_ucet.php");    // funkce pro modul  Ucetnictvi
//   require_once("$ezer_path_appl/ys_lide.php");          // funkce pro modul Lid�
// require_once("$ezer_path_root/ds/ds_fce.php");
*/
?>