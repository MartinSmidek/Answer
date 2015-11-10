<?php # Systém An(w)er/Ezer/CPR, (c) 2008-2014 Martin Šmídek <martin@smidek.eu>

  # inicializace systémů Ans(w)er
  #   $app        = kořenová podsložka aplikace ... ys/ys2/fa/fa2/cr
  #   $app_name   = jméno aplikace
  #   $db_name    = hlavní databáze ... bylo-li v URL &test=1 přidá se do options.tabu_db
  #   $skin       = ck|ch
  #   $js_lib     = pole s *.js
  #   $css_lib    = pole s *.css
  #   $options    = doplnění Ezer.options

  require_once("answer.php");
  $options= (object)array(
    'skill'      => "'c'",
    'autoskill'  => "'!c'"
  );
  answer_php('cr','Ans(w)er - Centrum pro rodinu','ezer_cr','ck',
    array("ds/fce.js"),array("skins/ck/ck.ezer.css","cr/cr.css.php"),$options);
?>
