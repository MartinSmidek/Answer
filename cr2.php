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
//    'clock_off' => 1,                          // vypnout minutový a hodinový chat se serverem
//    'curr_version' => 1,                       // při přihlášení je nahrazeno nejvyšší ezer_kernel.version
//    'watch_access' => 4,
    'watch_access_opt' => // ... barvení v Uživatelé + select v ezer2.syst.ezer
       "{name:{4:'CPR Ostrava'},
         abbr:{4:'C'},
         css:{4:'ezer_ys'}}",
    'web'          => "''", // web organizace - pro odmítnutí odhlášení
    'skill'        => "'d'",
    'autoskill'    => "'!d'",
    'db_test'      => 0
);

  $cookie= 4;
  $app_last_access= "{$app}_last_access";
  
//  if ( isset($_COOKIE[$app_last_access])
//    && $_COOKIE[$app_last_access]>0 ) { //&&  $_COOKIE[$app_last_access]<4 ) {
//    $cookie= $_COOKIE[$app_last_access];
//  }
  $get_ev= filter_input(INPUT_GET,'ezer',FILTER_SANITIZE_SPECIAL_CHARS);
  $ev= $get_ev=='3.1' ? '3' : '';
  $choice_css= "skins/ck/ck.ezer$ev.css=skin";

  $js= array(
  );
  $css= array($choice_css,
    "db2/db2.css"
  );
  answer_php($app,$app_name,'ezer_cr','ck',$js,$css,$options);
?>
