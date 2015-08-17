<?php # Systém An(w)er/Ezer/YMCA Familia, (c) 2008-2014 Martin Šmídek <martin@smidek.eu>

  # inicializace systémů Ans(w)er
  #   $app        = kořenová podsložka aplikace ... db2
  #   $app_name   = jméno aplikace
  #   $db_name    = hlavní databáze ... bylo-li v URL &test=1 přidá se do options.tabu_db
  #   $skin       = ck|ch
  #   $js_lib     = pole s *.js
  #   $css_lib    = pole s *.css
  #   $options    = doplnění Ezer.options

  require_once("answer.php");
  $options= (object)array(
    'watch_access' => 3,
//     'watch_access' => "{1:'ezer_ys',2:'ezer_db',3:'ezer_db'}",  ... barvení v Uživatelé + selecty
    'web'          => "''", // web organizace - pro odmítnutí odhlášení
    'skill'        => "'d'",
    'autoskill'    => "'!d'"
  );
  answer_php('db2','Answer F+S','ezer_db2','db',array(),array("./db2/db2.css.php"),$options);
?>
