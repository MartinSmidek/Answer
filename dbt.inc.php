<?php # Systém An(w)er/Ezer/YMCA Familia a YMCA Setkání, (c) 2008-2015 Martin Šmídek <martin@smidek.eu>

  # nastavení systému Ans(w)er před voláním AJAX
  #   $app        = kořenová podsložka aplikace ... db2
  #   $answer_db  = logický název hlavní databáze (s případným '_test')
  #   $dbs_plus   = pole s dalšími databázemi ve formátu $dbs
  #   $php_lib    = pole s *.php - pro 'ini'

  global // import 
    $ezer_root; 
  global // export
    $EZER, $ezer_server;
  
  // vyzvednutí ostatních hodnot ze SESSION
  $ezer_server=  $_SESSION[$ezer_root]['ezer_server'];
  $kernel= "ezer{$_SESSION[$ezer_root]['ezer']}";
  $abs_root=     $_SESSION[$ezer_root]['abs_root'];
  $rel_root=     $_SESSION[$ezer_root]['rel_root'];
  chdir($abs_root);

//  echo("db2.inc.php start, server=$ezer_server");

  // inicializace objektu Ezer
  $EZER= (object)array(
      'version'=>"ezer{$_SESSION[$ezer_root]['ezer']}",
      'options'=>(object)array(
          'mail' => "martin@smidek.eu",
          'phone' => "603&nbsp;150&nbsp;565",
          'author' => "Martin"
      ),
      'activity'=>(object)array());

  // ošetření běhu s testovací databází
  $answer_db= 'ezer_db2';
  $answer_dbx= 'ezer_db2_test';

//  $db_test= isset($_SESSION[$ezer_root]['db_test']) && $_SESSION[$ezer_root]['db_test']; 
//  $answer_dbx= $db_test ? "{$answer_db}_test" : $answer_db;
  $_SESSION[$ezer_root]['db_test']= 1;
  $_SESSION[$ezer_root]['ezer_db']= $answer_dbx;

  // přístup k databázím
  $db= array($answer_db,$answer_db,$answer_db);
  $dbs_plus= array(
    array( // lokální - bean
      'setkani' =>      array(0,'localhost','gandi','','cp1250','setkani',1)),
    array( // ostré - lukáš
      'setkani' =>      array(0,'192.168.1.146','gandi','','cp1250','setkani',1)),
    array( // ostré - dsm
      'setkani' =>      array(0,'localhost','ymca','JW4YNPTDf4Axkj9','cp1250','setkani',1))
  );
  $dbs= array(
    array_merge(array( // lokální - bean
      $answer_db =>     array(0,'localhost','gandi','','utf8',$answer_dbx),
      'ezer_system' =>  array(0,'localhost','gandi','','utf8',$answer_dbx),
      'ezer_group'  =>  array(0,'localhost','gandi','','utf8','ezer_answer'), 
      'ezer_kernel' =>  array(0,'localhost','gandi','','utf8')                
    ), $dbs_plus[0]),
    array_merge(array( // ostré - matouš
      $answer_db =>     array(0,'localhost','gandi','r8d0st','utf8',$answer_dbx),
      'ezer_system' =>  array(0,'localhost','gandi','r8d0st','utf8',$answer_dbx),
      'ezer_group'  =>  array(0,'localhost','gandi','r8d0st','utf8','ezer_answer'),
      'ezer_kernel' =>  array(0,'localhost','gandi','r8d0st','utf8','ezer_kernel') 
    ), $dbs_plus[1]),
    array_merge(array( // ostré - dsm
      $answer_db =>     array(0,'localhost','ymca','JW4YNPTDf4Axkj9','utf8',$answer_dbx),
      'ezer_system' =>  array(0,'localhost','ymca','JW4YNPTDf4Axkj9','utf8',$answer_dbx),
      'ezer_group'  =>  array(0,'localhost','ymca','JW4YNPTDf4Axkj9','utf8','ezer_answer'),
      'ezer_kernel' =>  array(0,'localhost','ymca','JW4YNPTDf4Axkj9','utf8','ezer_kernel') 
    ), $dbs_plus[2])
  );      

  // definice zápisů do _track
  $tracking= '_track';
  $tracked= ',akce,pobyt,spolu,osoba,tvori,rodina,_user,';
  
  // definice modulů specifických pro Answer
  $k= substr($kernel,4,1)=='3' ? '3' : '';
  $app_php= array(
    "db2/db2_ys2_fce$k.php",
    "db2/db2_fce$k.php"
  );
  
  $ezer= array(
  );
  
  // PDF knihovny
  require_once('tcpdf/tcpdf.php');
  require_once('tcpdf/db2_tcpdf.php');
 
  // je to aplikace se startem v rootu
  chdir($abs_root);
  require_once("{$EZER->version}/ezer_ajax.php");

//  echo("db2.inc.php end<br>");
  // SPECIFICKÉ PARAMETRY
  global $USER;
  $VPS= isset($USER) ? ($USER->org==1 ? 'VPS' : 'PPS') : 'VPS';
  $EZER->options->org= 'YMCA Familia, YMCA Setkání';


/*
  # nastavení systému Ans(w)er před voláním AJAX
  #   $app        = kořenová podsložka aplikace ... db2
  #   $answer_db  = logický název hlavní databáze (s případným '_test')
  #   $dbs_plus   = pole s dalšími databázemi ve formátu $dbs
  #   $php_lib    = pole s *.php - pro 'ini'

  require_once("answer.inc.php");
  $dbs_plus= array(
    array( // ostré
      'setkani' =>      array(0,'192.168.1.146','gandi','','cp1250','setkani',1)), // 1=vynechat zálohování
    array( // lokální
      'setkani' =>      array(0,'localhost','gandi','','cp1250','setkani',1))        // 1=vynechat zálohování
  );
  
  global $EZER;
  answer_ezer('dbt');
  $db2_ys2_fcex= $EZER->version=='ezer3.1' ? 'db2_ys2_fce3' : 'db2_ys2_fce';
  
  $php= array(
    "db2/$db2_ys2_fcex.php"
  );
  
  $ezer= array(
  );
  answer_ini('dbt','ezer_db2',$dbs_plus,$php,$ezer);

  // SPECIFICKÉ PARAMETRY
  global $USER;
  $VPS= isset($USER) ? ($USER->org==1 ? 'VPS' : 'PPS') : 'VPS';
  $EZER->options->org= 'YMCA Familia, YMCA Setkání';
*/
?>
