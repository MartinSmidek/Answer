<?php # Systém An(w)er/Ezer/YMCA Setkání, (c) 2008-2014 Martin Šmídek <martin@smidek.eu>

  # inicializace systému Ans(w)er
  #   $app        = kořenová podsložka aplikace ... ys/ys2/fa/fa2/cr
  #   $app_name   = jméno aplikace
  #   $db_name    = hlavní databáze ... bylo-li v URL &test=1 přidá se do options.tabu_db
  #   $skin       = ck|ch
  #   $js_lib     = pole s *.js
  #   $css_lib    = pole s *.css
  #   $options    = doplnění Ezer.options

  require_once("answer.php");
  $options= (object)array(
    'web'        => "'http://www.setkani.org'", // web organizace - pro odmítnutí odhlášení
    'skill'      => "'y'",
    'autoskill'  => "'!y'",
    'Google' => "{                      // definice oprávnění přístupu na Google
        CLIENT_ID:'854681585120-rla91b0i46oei7njt6f32mst668871sa.apps.googleusercontent.com'
      }"
  );
  $js= array("https://apis.google.com/js/client.js?onload=Ezer.Google.ApiLoaded","ds/fce.js");
//   $css= array("./ezer2.2/client/ezer.css.php","./db/db.css.php","./ys/ys.css.php");
  $css= array("skins/ck.ezer.css");
  answer_php('ys2','Ans(w)er','ezer_ys','ck',$js,$css,$options);
?>
