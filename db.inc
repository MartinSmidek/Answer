<?php # (c) 2009-2010 Martin Smidek <martin@smidek.eu>

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

  // knihovn� moduly
  require_once("$ezer_path_root/ezer2/ezer2_fce.php");
  // vlo�en� modul�
  foreach($ezer_php as $php) {
    require_once("$ezer_path_root/$php");
  }

?>