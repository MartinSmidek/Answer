<?php # Systém An(w)er/Ezer/YMCA Familia, (c) 2008-2014 Martin Šmídek <martin@smidek.eu>
die(<<<__EOD
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en" dir="ltr" slick-uniqueid="1" spellcheck="false">
 <head>
  <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
  <meta http-equiv="X-UA-Compatible" content="IE=9">
  <link rel="shortcut icon" href="./db2/img/db2.png">
  <title>Answer instalace</title>
 </head>
 <body style="padding:100px">
  <div style="background-color:yellow; border:1px solid red; width:700px">
   <div style="float:right;padding:5px;">2.12.2015 - 12:00</div>
   <div style="padding:50px;">
    Vážení klienti, milí kamarádi,
    <br>s mírným skluzem dnes na serveru probíhá instalace nové verze systému Answer.
    <br>Omlouvám se, ale dnes již nebude možné se systémem pracovat.
    <br>Pokud byste přesto potřebovali nějakou informaci, zavolejte mi prosím na 603 150 565.
    <br>Zítra ráno bude Answer opět v provozu.
    <br>Martin
   </div>
  </div>
 </body>
</html>
__EOD
);
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
