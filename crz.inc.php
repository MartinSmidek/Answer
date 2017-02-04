<?php
  # Systém An(w)er/Ezer/YMCA Familia a YMCA Setkání, (c) 2008-2017 Martin Šmídek <martin@smidek.eu>
  # verze pro Centrum pro rodinný život Olomouc

  # nastavení systému Ans(w)er před voláním AJAX
  #   $app        = kořenová podsložka aplikace ... db2
  #   $answer_db  = logický název hlavní databáze (s případným '_test')
  #   $dbs_plus   = pole s dalšími databázemi ve formátu $dbs
  #   $php_lib    = pole s *.php - pro 'ini'

  require_once("answer.inc.php");
  $dbs_plus= array(array(),array());
  $php= array(
    'db2/db2_fce.php',
    'db2/db2_ys2_fce.php'
  );
  $ezer= array(
  );
  answer_ini('crz','ezer_crz',$dbs_plus,$php,$ezer);
//                                                         if (isset($USER)) var_dump($USER); else echo "USER nedef";
  // SPECIFICKÉ PARAMETRY
  global $USER;
  $VPS= 'VPS';
  $EZER->options->org= 'CPRZ Olomouc';
?>
