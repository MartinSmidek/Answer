<?php # Systém An(w)er/Ezer/YMCA Familia a YMCA Setkání, (c) 2008-2015 Martin Šmídek <martin@smidek.eu>

  # nastavení systému Ans(w)er před voláním AJAX
  #   $app        = kořenová podsložka aplikace ... db2
  #   $answer_db  = logický název hlavní databáze (s případným '_test')
  #   $dbs_plus   = pole s dalšími databázemi ve formátu $dbs
  #   $php_lib    = pole s *.php - pro 'ini'

  require_once("answer.inc");
  $dbs_plus= array(
    array( // ostré
      'ezer_ys' =>      array(0,'localhost','gandi','r8d0st','utf8','ezer_ys'),
      'setkani' =>      array(0,'192.168.1.146','gandi','','cp1250','setkani',1)), // 1=vynechat zálohování
    array( // lokální
      'ezer_ys' =>      array(0,'localhost','gandi','','utf8','ezer_ys'),
      'setkani' =>      array(0,'localhost','gandi','','utf8','setkani',1))        // 1=vynechat zálohování
  );
  $php= array(
    'db2/db2_fce.php',
    'ys2/ys2_fce.php'
  );
  $ezer= array(
    'ys2/ys2.pok',
    'ys2/ys2.pok.den',
    'ys2/ys2.pok.nas',
    'ys2/ys2.ds',
    'ys2/ys2.ds.kl',
    'ds/ds',
    'ds/ds.tab',
    'ds/ds.dum'
  );
  answer_ini('db2_test','ezer_db2',$dbs_plus,$php,$ezer);
  // SPECIFICKÉ PARAMETRY
  $VPS= 'PPS';
  $EZER->options->org= 'YMCA Familia, YMCA Setkání';
?>
