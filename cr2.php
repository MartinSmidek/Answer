<?php # Systém An(w)er/YMCA Setkání/YMCA Familia, (c) 2008-2015 Martin Šmídek <martin@smidek.eu>

  # inicializace systémů Ans(w)er
  #   $app        = kořenová podsložka aplikace ... db2
  #   $app_name   = jméno aplikace
  #   $db_name    = hlavní databáze ... bylo-li v URL &test=1 přidá se do options.tabu_db
  #   $skin       = ck|ch
  #   $js_lib     = pole s *.js
  #   $css_lib    = pole s *.css
  #   $options    = doplnění Ezer.options

  $app=  'cr2';
  $app_name= "Answer2 - Centrum pro rodinu"; //"<span style='color:red'>testování Answer CPR</span>";

  require_once("answer.php");
  $options= (object)array(
//     'watch_access' => 0,
    'watch_access' => 4,
    'watch_access_opt' => // ... barvení v Uživatelé + select v ezer2.syst.ezer
       "{name:{4:'CPR Ostrava'},
         abbr:{4:'C'},
         css:{4:'ezer_ys'}}",
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
  $choice_css= "skins/ck/ck.ezer.css=skin";

  $js= array(
  );
  $css= array($choice_css,
    "db2/db2.css"
  );
  answer_php($app,$app_name,'ezer_cr','ck',$js,$css,$options);
?>