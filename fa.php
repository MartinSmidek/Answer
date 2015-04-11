<?php # Systém An(w)er/Ezer/YMCA Familia, (c) 2008-2014 Martin Šmídek <martin@smidek.eu>

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
    'skill'      => "'f'",
    'autoskill'  => "'!f'"
  );
  answer_php('fa2','Ans(w)er - Familia','ezer_fa','ch',array(),array("./fa/fa.css.php"),$options);
?>
