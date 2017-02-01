<?php # Systém An(w)er/Ezer/CPR, (c) 2008-2014 Martin Šmídek <martin@smidek.eu>
if ( !isset($_GET['answer']) || $_GET['answer']!=1 ) {
die(<<<__EOD
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en" dir="ltr" slick-uniqueid="1" spellcheck="false">
 <head>
  <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
  <meta http-equiv="X-UA-Compatible" content="IE=9">
  <link rel="shortcut icon" href="./cr2/img/cr2.png">
  <title>Answer2</title>
  <script>
    var createCookie = function(name, value, days) {
      var expires;
      if (days) {
        var date = new Date();
        date.setTime(date.getTime() + (days * 24 * 60 * 60 * 1000));
        expires = "; expires=" + date.toGMTString();
      }
      else {
        expires = "";
      }
      document.cookie = name + "=" + value + expires + "; path=/";
    }
  </script>
 </head>
 <body style="padding:100px">
  <div style="background-color:#cec; width:700px">
   <div style="float:right;padding:5px;text-align:right">1.2.2017<br>na Hromnice<br>o verzi více</div>
   <div style="padding:50px;">
    Vážení klienti, milí kamarádi,
    <br><br>na serveru je od začátku února 2017 podle domluvy
    <br>nainstalována nová verze systému Answer - verze 2.
    <br><br>Je pravděpodobné, že se z počátku vyskytnou nějaké problémy,
    <br>které se však vynasnažím co nejrychleji opravit (vyjma času od 6. - 12. února).
    <br>Věřím, že stejně jako v YMCA Setkání a v YMCA Familia shledáte,
    <br>že vám nová verze práci zjednoduší a zpříjemní.
    <br><br>Pokud byste potřebovali nějakou informaci nebo vysvětlení,
    <br>zavolejte mi prosím na 603 150 565,
    nebo pište na <a href="mailto:martin@smidek.eu">můj mail</a>.
    <br><br>
    Přeji vám dobré dny
    <br>Martin Šmídek
    <br><br>
    <!-- button onclick="window.location.href='http://answer.setkani.org/cr2.php';">
      Pracovat s novou verzí
    </button -->
   </div>
  </div>
 </body>
</html>
__EOD
);
}
else {

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
    'skill'      => "'c'",
    'autoskill'  => "'!c'"
  );
  answer_php('cr','Ans(w)er - Centrum pro rodinu','ezer_cr','ck',
    array("ds/fce.js"),array("skins/ck/ck.ezer.css","cr/cr.css.php"),$options);
}
?>
