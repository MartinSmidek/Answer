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
    'watch_access_opt' => // ... barvení v Uživatelé + select v ezer2.syst.ezer
       "{name:{1:'Setkání',2:'Familia',3:'Setkání+Familia'},
         abbr:{1:'S',2:'F',3:'S+F'},
         css:{1:'ezer_ys',2:'ezer_fa',3:'ezer_db'}}",
    'web'          => "''", // web organizace - pro odmítnutí odhlášení
    'skill'        => "'d'",
    'autoskill'    => "'!d'"
  );

  $cookie= 3;
  if ( isset($_COOKIE['last_access'])
    && $_COOKIE['last_access']>0 &&  $_COOKIE['last_access']<4 )
    $cookie= $_COOKIE['last_access'];
  $choice_css=
    $cookie==1 ? "skins/ys/ys.ezer.css=skin" : (
    $cookie==2 ? "skins/fa/fa.ezer.css=skin" : "skins/db/db.ezer.css=skin" );

  $css= array($choice_css,"./db2/db2.css");
  answer_php('db2',"Answer ...",'ezer_db2','db',array(),$css,$options);
?>
