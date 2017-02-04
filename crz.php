<?php
  # Systém An(w)er/YMCA Setkání/YMCA Familia, (c) 2008-2015 Martin Šmídek <martin@smidek.eu>
  # verze pro Centrum pro rodinný život Olomouc

  # inicializace systémů Ans(w)er
  #   $app        = kořenová podsložka aplikace ... db2
  #   $app_name   = jméno aplikace
  #   $db_name    = hlavní databáze ... bylo-li v URL &test=1 přidá se do options.tabu_db
  #   $skin       = ck|ch
  #   $js_lib     = pole s *.js
  #   $css_lib    = pole s *.css
  #   $options    = doplnění Ezer.options

  $app=  'crz';
  $app_name= "Answer - Centrum pro rodinný život";

  require_once("answer.php");
  $options= (object)array(
    'curr_version' => 0,                       // nezjišťovat změny verze
//     'watch_access' => 0,
    'watch_access' => 4,
    'watch_access_opt' => // ... barvení v Uživatelé + select v ezer2.syst.ezer
       "{name:{4:'CRZ Olomouc'},
         abbr:{4:'C'},
         css:{4:''}}",
    'web'          => "''", // web organizace - pro odmítnutí odhlášení
    'skill'        => "'d'",
    'autoskill'    => "'!d'"
  );

  $cookie= 4;
  $app_last_access= "{$app}_last_access";
  if ( isset($_COOKIE[$app_last_access])
    && $_COOKIE[$app_last_access]>0 ) { //&&  $_COOKIE[$app_last_access]<4 ) {
    $cookie= $_COOKIE[$app_last_access];
  }
  $choice_css= "skins/ch/ch.ezer.css";

  $js= array(
  );
  $css= array($choice_css,
    "db2/db2.css"
  );
  $_GET['gmap']= 0;
  answer_php($app,$app_name,'ezer_crz','ch',$js,$css,$options);
?>
