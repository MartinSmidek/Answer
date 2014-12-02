<?php # Systém An(w)er/Ezer/CPR, (c) 2008-2014 Martin Šmídek <martin@smidek.eu>

  # nastavení systému Ans(w)er před voláním AJAX
  #   $app        = kořenová podsložka aplikace ... ys/ys2/fa/fa2/cr
  #   $answer_db  = logický název hlavní databáze (s případným '_test')
  #   $dbs_plus   = pole s dalšími databázemi ve formátu $dbs
  #   $php_lib    = pole s *.php - pro 'ini'

  require_once("answer.inc");
  answer_ini('cr','ezer_cr',array(array(),array()),array("cr/cr_fce.php","cr/cr_lide.php"));
  // SPECIFICKÉ PARAMETRY
  $VPS= 'VPS';
  $EZER->options->org= 'Centrum pro rodinu';
?>
