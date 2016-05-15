<?php # (c) 2010 Martin Smidek <martin@smidek.eu>
  error_reporting(E_ALL ^ E_NOTICE);
  global $ezer_root;
  $ezer_root= 'panel';

  chdir("../..");
  // nastavení verze jádra
  $EZER= (object)array();
	$ev= ($_GET['ezer'] ? $_GET['ezer'] : ($_COOKIE['ev'] ? $_COOKIE['ev'] : 2));
  setcookie("ev",$ev,time()+3600*24*30);
  $EZER->version= "ezer{$ev}";
  $EZER->version= "ezer2.2";

  // identifikace ladícího serveru
  $ezer_local= preg_match('/^\w+\.ezer|ezer\.\w+|^localhost|^192\.168\./',$_SERVER["SERVER_NAME"]);

  // cesty
  $path= $_SERVER['PHP_SELF'];
  $path= substr($path,0,strrpos($path,'/'));
  $path= substr($path,0,strrpos($path,'/'));
  $abs_root= $_SERVER['DOCUMENT_ROOT'].$path;
  $rel_root= $_SERVER['HTTP_HOST'].$path;

  require_once("{$EZER->version}/server/ae_slib.php");

  // OBECNÉ PARAMETRY

  // parametry s první hodnotou pro server a druhou (případně) pro local
  //   databáze => (,server,username,userpass,kódování,[jméno databáze])
  // databáze 'ezer_system' obsahuje platnou tabulku _user
  // (fyzické jméno databáze může být změněno pátým členem v tabulce $dbs)

// $nam= $ezer_local ? 'gandi' : 'proglas';
// $pas= $ezer_local ? '' : 'pr0gl8s';
// $ezer_db= array( /* lokální */
//   'setkani' =>  array(0,'localhost',$nam,$pas,'utf8')
// );

//   $nam= $_SERVER['HTTP_HOST']=='10.10.10.4' ? 'zasilatelstvi' : 'gandi';
//   $pas= $_SERVER['HTTP_HOST']=='10.10.10.4' ? '5t4E37*46F'    : 'r8d0st';
  $db= array('setkani','setkani');
  $dbs= array(
    array(
      'setkani'       => array(0,'localhost','gandi','','utf8'),
//       'ezer_test'     => array(0,'localhost',$nam,$pas,'utf8','ezer_tutorial'),
      'ezer_kernel'   => array(0,'localhost',$nam,$pas,'utf8'),
      'ezer_system'   => array(0,'localhost',$nam,$pas,'utf8','ezer_tutorial')),
    array(
//       'ezer_test'     => array(0,'localhost','gandi','','utf8','ezer_tutorial'),
      'ezer_kernel'   => array(0,'localhost','gandi','','utf8'),
      'setkani'       => array(0,'localhost','gandi','','utf8'),
      'ezer_system'   => array(0,'localhost','gandi','','utf8','ezer_tutorial'))
  );

  $path_root=  array($abs_root,$abs_root);
  $path_pspad= array(null,"C:/Program Files (x86)/PSPad editor/Syntax");
  // ostatní parametry
  $tracking= '_track';
  $tracked= ',_user,';
  root_inc($db,$dbs,$tracking,$tracked,$path_root,$path_pspad,$ezer_root);

  // PARAMETRY SPECIFICKÉ PRO APLIKACI

  // specifické cesty

  // moduly interpreta zahrnuté do aplikace - budou zpracovány i reference.i_doc pro tabulky kompilátoru
  $ezer_comp_ezer= "app,ezer,area,ezer_report,ezer_fdom1,ezer_fdom2";
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
    "panel/template.php",
  );

  // parametrizace $EZER
  $EZER->options->web=    'www.setkani.org';
  $EZER->options->author= 'Gándí';
  $EZER->options->mail=   'martin@smidek.eu'; //'smidek@proglas.cz';
  $EZER->options->phone=  'kl.523';
  $EZER->options->skype=  'martin_smidek';
  $EZER->activity->skip=  'GAN';      // viz system.php::sys_table
  // PDF knihovny
  require_once('tcpdf/tcpdf.php');
  // vložení modulů
  foreach($ezer_php as $php) {
    require_once("$ezer_path_root/$php");
  }

?>
