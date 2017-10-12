<?php # Systém An(w)er/YMCA Setkání/YMCA Familia, (c) 2008-2015 Martin Šmídek <martin@smidek.eu>

  # inicializace systémů Ans(w)er
  #   $app        = kořenová podsložka aplikace ... db2
  #   $app_name   = jméno aplikace
  #   $db_name    = hlavní databáze ... bylo-li v URL &test=1 přidá se do options.tabu_db
  #   $skin       = ck|ch
  #   $js_lib     = pole s *.js
  #   $css_lib    = pole s *.css
  #   $options    = doplnění Ezer.options

  $app=  'db2';

  require_once("answer.php");
  $options= (object) ['watch_access' => 3,
    'watch_access_opt' => // ... barvení v Uživatelé + select v ezer2.syst.ezer
       "{name:{1:'Setkání',2:'Familia',3:'Setkání+Familia'},
         abbr:{1:'S',2:'F',3:'S+F'},
         css:{1:'ezer_ys',2:'ezer_fa',3:'ezer_db'}}",
    'web'          => "''", // web organizace - pro odmítnutí odhlášení
    'skill'        => "'d'",
    'autoskill'    => "'!d'"
   ];

  $cookie= 3;
  $app_last_access= "{$app}_last_access";
  $coo= filter_input(INPUT_COOKIE,$app_last_access,FILTER_VALIDATE_INT);
  if ( $coo && $coo>0 && $coo<4 ) {
    $cookie= $coo;
  }
  $get_ev= filter_input(INPUT_GET,'ezer',FILTER_SANITIZE_SPECIAL_CHARS);
  $ev= $get_ev==3 ? '3' : '';
  $choice_css=
    $cookie==1 ? "skins/ck/ck.ezer$ev.css=skin" : (
    $cookie==2 ? "skins/ch/ch.ezer$ev.css=skin" : "skins/db/db.ezer$ev.css=skin" );
  $skin=
    $cookie==1 ? "ck" : (
    $cookie==2 ? "ch" : "db" );

  $js= array(
    $ev === '3' ? "db2/ds_fce3.js" : "db2/ds_fce.js"
  );
  $css= [$choice_css,
    "db2/db2.css"
   ];
  answer_php($app,"Answer ...",'ezer_db2',$skin,$js,$css,$options);

  
