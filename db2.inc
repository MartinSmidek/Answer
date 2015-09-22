<?php # Systém An(w)er/Ezer/YMCA Familia a YMCA Setkání, (c) 2008-2015 Martin Šmídek <martin@smidek.eu>

  # nastavení systému Ans(w)er před voláním AJAX
  #   $app        = kořenová podsložka aplikace ... db2
  #   $answer_db  = logický název hlavní databáze (s případným '_test')
  #   $dbs_plus   = pole s dalšími databázemi ve formátu $dbs
  #   $php_lib    = pole s *.php - pro 'ini'

  require_once("answer.inc");
  answer_ini('db2','ezer_db2',array(array(),array()),array('db2/db2_fce.php'));
  // SPECIFICKÉ PARAMETRY
  $VPS= 'PPS';
  $EZER->options->org= 'YMCA Familia, YMCA Setkání';
?>