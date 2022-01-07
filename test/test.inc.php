<?php # (c) 2010-2019 Martin Smidek <martin@smidek.eu>

  // klíče
  $deep_root= "../files/tut";
  require_once("$deep_root/tut.dbs.php");
  
  global // import 
    $ezer_root; 
  global // export
    $EZER, $ezer_server;
  global // klíče
    $api_gmail_user, $api_gmail_pass;
  
  // vyzvednutí ostatních hodnot ze SESSION
  $ezer_server=  $_SESSION[$ezer_root]['ezer_server'];
  $kernel= "ezer{$_SESSION[$ezer_root]['ezer']}";
  $abs_root=     $_SESSION[$ezer_root]['abs_root'];
  $rel_root=     $_SESSION[$ezer_root]['rel_root'];
  chdir($abs_root);

  // inicializace objektu Ezer
  $EZER= (object)array(
      'version'=>"ezer{$_SESSION[$ezer_root]['ezer']}",
      'options'=>(object)array(),
      'activity'=>(object)array(),
      'CMS'=>(object)array(
        'GMAIL'=>(object)array(
          'mail'=>$api_gmail_user, // adresa odesílatele mailů
          'name'=>'YMCA Setkání', 
          'pswd'=>$api_gmail_pass
      ))
  );

  // informace pro debugger o poloze ezer modulů
  $dbg_info= (object)array(
    'src_path'  => array('test','dbt','ezer3.1') // poloha a preference zdrojových modulů
  );

  $db= array('ezer_test','ezer_test','ezer_test','ezer_test');
  $dbs= array(
    array(
      'ezer_test'     => array(0,'localhost','gandi','radost','utf8','ezer_tutorial'),
      'ezer_kernel'   => array(0,'localhost','gandi','radost','utf8','ezer_tutorial'),
      'ezer_system'   => array(0,'localhost','gandi','radost','utf8','ezer_tutorial')),
    array(
      'ezer_test'     => array(0,'localhost','gandi','r8d0st','utf8','ezer_tutorial'),
      'ezer_kernel'   => array(0,'localhost','gandi','r8d0st','utf8','ezer_tutorial'),
      'ezer_system'   => array(0,'localhost','gandi','r8d0st','utf8','ezer_tutorial')),
    array( // Synology YMCA
      'ezer_test'     => array(0,'ymca','ymca','JW4YNPTDf4Axkj9','utf8','ezer_tutorial'),
      'ezer_kernel'   => array(0,'ymca','ymca','JW4YNPTDf4Axkj9','utf8'),
      'ezer_system'   => array(0,'ymca','ymca','JW4YNPTDf4Axkj9','utf8','ezer_tutorial')),
    array( // Synology DOMA
      'ezer_test'     => array(0,'localhost','gandi','radost','utf8','ezer_tutorial'),
      'ezer_kernel'   => array(0,'localhost','gandi','radost','utf8','ezer_tutorial'),
      'ezer_system'   => array(0,'localhost','gandi','radost','utf8','ezer_tutorial'))
  );
  
  // specifické PHP moduly
  $app_php=   array(
      'test/test_fce.php'
//      'tut/tut.the.php','tut/tut.bib.php','tut/tut.sand.php',
//      'ezer3.1/server/sys_doc.php','ezer3.1/server/ezer_ruian.php'
      );
  
  // aplikace se startem v podsložce
  require_once("{$EZER->version}/ezer_ajax.php");

