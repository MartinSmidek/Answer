<?php # Systém An(w)er/Ezer, (c) 2008-2014 Martin Šmídek <martin@smidek.eu>

# nastavení systémů Ans(w)er před voláním AJAX
#
#   $app        = kořenová podsložka aplikace ... ys/ys2/fa/fa2/cr
#   $answer_db  = logický název hlavní databáze
#   $answer_dbx = fyzický název hlavní databáze (s případným '_test')
#   $dbs_plus   = pole s dalšími databázemi ve formátu $dbs
#   $php_lib    = pole s *.php - pro 'ini'
#
function answer_ezer($app) {
  global $EZER;

  if ( !isset($EZER->version) ) {
    // nastavení zobrazení PHP-chyb klientem při &err=1
    if ( isset($_GET['err']) && $_GET['err'] ) {
      error_reporting(E_ALL ^ E_NOTICE);
      ini_set('display_errors', 'On');
    }
    // nastavení verze jádra
    $EZER= (object)array();
    $ev= (isset($_GET['ezer']) ? $_GET['ezer']
       : (isset($_SESSION[$app]['GET']['ezer']) ? $_SESSION[$app]['GET']['ezer'] : '2.2'));
    setcookie("ev",$ev,time()+3600*24*30);
    $EZER->version= "ezer{$ev}";
  }

  // přepínač pro fáze migrace pod PDO - const EZER_PDO_PORT=1|2|3 -- pro jádro >=3
  if ( $EZER->version=='ezer3.1' ) {
    if ( isset($_SESSION[$app]['pdo']) && $_SESSION[$app]['pdo']==2 ) {
      require_once("{$EZER->version}/pdo.inc.php");
    }
    else {
      require_once("{$EZER->version}/mysql.inc.php");
    }
    require_once("{$EZER->version}/server/ezer_pdo.php");
  }

//  // android
//  $android=    preg_match('/android|x11/i',$_SERVER['HTTP_USER_AGENT']);
//  $ezer_ksweb= $android && $server_name=="localhost"; // identifikace ladícího serveru KSWEB/Android
}

function answer_ini($app,$answer_db,$dbs_plus,$php_lib,$ezer_mod=array()) {
  global $EZER,$ezer_local,$ezer_root,$ezer_path_root,$ezer_comp_ezer,$ezer_comp_root,
         $path_backup,$ezer_mysql_path,$path_url,$path_pspad,
         $path_files_h,$path_files_s,$path_files_href,
         $ezer_php_libr,$ezer_php,$ezer_ezer;

  $ezer_root= $app;                                             // adresář a hlavní objekt aplikace

//  if ( !isset($EZER->version) ) 
    answer_ezer($app);
  
  $server_name= isset($_SERVER["HTTP_X_FORWARDED_SERVER"])
    ?$_SERVER["HTTP_X_FORWARDED_SERVER"]:$_SERVER["SERVER_NAME"];
  $ezer_local= preg_match('/^\w+\.(ezer|bean)|192.168/',$server_name); // identifikace ladícího serveru

  require_once("{$EZER->version}/server/ae_slib.php");
  if (  $EZER->version=='ezer3.1' )
    require_once("{$EZER->version}/server/ezer_lib3.php");

  // ošetření běhu s testovací databází
  $db_test= isset($_SESSION[$app]['GET']['db_test']) && $_SESSION[$app]['GET']['db_test']; 
  $answer_dbx= $db_test ? "{$answer_db}_test" : $answer_db;
  $_SESSION[$app]['ezer_db']= $answer_dbx;

  // ošetření serveru ado.cz pro Centrum pro rodinný život
  $ezer_answer= 'ezer_answer';
  $ezer_kernel= 'ezer_kernel';
  $host= 'localhost';
  $name= 'gandi';
  $pass= 'r8d0st';
  if ( $server_name=='casopisrz.ado.cz' ) {
    $answer_dbx=  'adocz03';
    $ezer_answer= 'adocz04';
    $ezer_kernel= 'adocz02';
    $host= '127.0.0.1';
    $name= 'adocz002';
    $pass= 'LxUfz@n35Z33';
  }
  // databáze s první hodnotou pro server a druhou (případně) pro local
  $db= array($answer_db,$answer_db);
  $dbs= array(
    array_merge(array( /* ostré */
      $answer_db =>     array(0,$host,$name,$pass,'utf8',$answer_dbx),
      'ezer_system' =>  array(0,$host,$name,$pass,'utf8',$answer_dbx),
      'ezer_group'  =>  array(0,$host,$name,$pass,'utf8',$ezer_answer), // $_SESSION[$app]['group_db']
      'ezer_kernel' =>  array(0,$host,$name,$pass,'utf8',$ezer_kernel)  // sys('options','group_db')
    ), $dbs_plus[0]),
    array_merge(array( /* lokální */
      $answer_db =>     array(0,'localhost','gandi','','utf8',$answer_dbx),
      'ezer_system' =>  array(0,'localhost','gandi','','utf8',$answer_dbx),
      'ezer_group'  =>  array(0,'localhost','gandi','','utf8','ezer_answer'), // $_SESSION[$app]['group_db']
      'ezer_kernel' =>  array(0,'localhost','gandi','','utf8')                // sys('options','group_db')
    ), $dbs_plus[1])
  );
  // kořeny cest
  $path_root=  array(//$ezer_ksweb?"/storage/sdcard0/htdocs/www-ys2":
    "/home/www/ezer/www-ys/2","C:/Ezer/beans/answer");
  // kořen pro LabelDrop
  $abs_root= $path_root[$ezer_local?1:0];
  $path_files_href= "/docs/$app/";
  $path_files_s= "$abs_root$path_files_href";
  $path_files_h= substr($abs_root,0,strrpos($abs_root,'/'))."/files/".substr($ezer_root,0,2)."/";
//   $_SESSION[$app]['HTTP_EZER_FILE_ABSPATH']= $path_files;

  $path_pspad= array(null,"C:/Program Files (x86)/PSPad editor/Syntax");
  // cesty pro zálohy
  $path_backup= array("{$path_root[0]}/zalohy","C:/Ezer/www-ys2/zalohy");
  $path_backup= $path_backup[$ezer_local?1:0];          // složka záloh databází
  $ezer_mysql_path= array("/usr/bin/","c:/apache/mysql/bin/");
  $ezer_mysql_path= $ezer_mysql_path[$ezer_local?1:0];  // cesta k utilitě mysql (i s lomítkem)
  // cesty pro zadávání url
  $path_url= array("https://answer.setkani.org/{$ezer_root}.php","http://answer.bean:8080/{$ezer_root}.php");
  $path_url= $path_url[$ezer_local?1:0];                // prefix url
  // ostatní parametry
  $tracking= '_track';
  $tracked= ',akce,pobyt,spolu,osoba,tvori,rodina,_user,';
  root_inc($db,$dbs,$tracking,$tracked,$path_root,$path_pspad);

  // PARAMETRY SPECIFICKÉ PRO ANSWER

  // specifické cesty
  $bank= $ezer_local ? "C:/Ezer/www-ys2" : "/home/www/ezer/www-ys/2";

  // moduly interpreta zahrnuté do aplikace - budou zpracovány i reference.i_doc pro tabulky kompilátoru
  $db2_fcex= $EZER->version=='ezer3.1' ? 'db2_fce3' : 'db2_fce';
  $ezer_comp_ezer= "app,area,ezer,ezer_report,ezer_fdom1,ezer_fdom2";
  $ezer_comp_root= "ds/fce,db2/$db2_fcex";
  // moduly v Ezerscriptu mimo složku aplikace
  $ezer_ezer= array_merge(array(
//     "ds/ds.dum",
//     "ds/ds.tab",
//     "db/db.akce",
//     "db/db.akce.chl",
//     "db/db.akce.lst",
//     "db/db.akce.ducast",
//     "db/db.akce.evi",
//     "db/db.akce.ema"
    ),
    $ezer_mod
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
  $ezer_php= array_merge(array(
//     "ys/ys_ucet.php",       YS
//     "ys/ys.ban.php",        YS
//     "db/db_akce.php",       YS YS2 FA2 CR
//     "db/db_akce2.php",      YS YS2 FA2 CR
    'ezer3/cloc.php',
    "tcpdf/db2_tcpdf.php"),
    $php_lib
  );

  // parametrizace $EZER
  $EZER->options->local= $ezer_local;
  $EZER->options->org=    'YMCA Setkání';
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
//   require_once('tcpdf/config/lang/eng.php');
  require_once('tcpdf/tcpdf.php');
  // vložení modulů
    require_once("$ezer_path_root/db2/$db2_fcex.php");
  foreach($ezer_php as $php) {
    require_once("$ezer_path_root/$php");
  }
}
?>
