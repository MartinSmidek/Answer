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
    'web'        => "'http://www.familia.cz'", // web organizace - pro odmítnutí odhlášení
    'skill'      => "'f'",
    'autoskill'  => "'!f'"
  );
//   $css= array("./ezer2.2/client/ezer.css.php","./db/db.css.php","./fa/fa.css.php");
  $css= array("skins/ch/ch.ezer.css");
  answer_php('fa2','Ans(w)er - Familia','ezer_fa','ch',array(),$css,$options);
?>
