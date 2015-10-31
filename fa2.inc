<?php # Systém An(w)er/Ezer/YMCA Familia, (c) 2008-2014 Martin Šmídek <martin@smidek.eu>

  # nastavení systému Ans(w)er před voláním AJAX
  #   $app        = kořenová podsložka aplikace ... ys/ys2/fa/fa2/cr
  #   $answer_db  = logický název hlavní databáze (s případným '_test')
  #   $dbs_plus   = pole s dalšími databázemi ve formátu $dbs
  #   $php_lib    = pole s *.php - pro 'ini'

  require_once("answer.inc");
  answer_ini('fa2','ezer_fa',array(array(),array()),
    array("db/db_akce.php","db/db_akce2.php"));
  // SPECIFICKÉ PARAMETRY
  $VPS= 'PPS';
  $EZER->options->org= 'YMCA Familia';
?>
