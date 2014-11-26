<?php # Systém An(w)er/Ezer/YMCA Setkání, (c) 2008-2014 Martin Šmídek <martin@smidek.eu>
  global $ezer_path_root;
  require_once("answer.inc");
  $dbs_plus= array(
    array( // ostré
      'setkani' =>      array(0,'192.168.1.146','gandi','','cp1250')),
    array( // lokální
      'setkani' =>      array(0,'localhost','gandi','','utf8'))
  );
  answer_ini('ys2','ezer_ys',$dbs_plus,array("ds/ds_fce.php"));
  // SPECIFICKÉ PARAMETRY
  $VPS= 'VPS';
  $path_banka['5500']= "$ezer_path_root/banky/5500/";
  $EZER->options->org= 'YMCA Setkání';
?>
