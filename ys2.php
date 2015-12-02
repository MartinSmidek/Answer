<?php # Systém An(w)er/Ezer/YMCA Setkání, (c) 2008-2014 Martin Šmídek <martin@smidek.eu>

die(<<<__EOD
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en" dir="ltr" slick-uniqueid="1" spellcheck="false">
 <head>
  <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
  <meta http-equiv="X-UA-Compatible" content="IE=9">
  <link rel="shortcut icon" href="./db2/img/db2.png">
  <title>Answer instalace</title>
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
  <div style="background-color:yellow; border:1px solid red; width:700px">
   <div style="float:right;padding:5px;">2.12.2015</div>
   <div style="padding:50px;">
    Vážení klienti, milí kamarádi,
    <br><br>na serveru je nainstalována nová verze systému Answer.
    Obsahuje podle dohody výborů obou organizací spojenou databázi YMCA Setkání a YMCA Familia
    (zvolené řešení je korektní vzhledem k ochraně osobních dat účastníků našich akcí).
    <br>
    <br>Vyzkoušejte si prosím novou podobu Answeru nejprve nanečisto v testovacím prostředí (1),
    teprve až si vše vyzkoušíte, začněte pracovat v ostré verzi (2).
    Změny do ostré verze ale prosím dělejte až po Mikuláši.
    Pokud byste potřebovali nějakou informaci nebo vysvětlení, zavolejte mi prosím na 603 150 565,
    nebo pište na <a href="mailto:martin@smidek.eu">můj mail</a>.
    <br><br>
    Přeji vám příjemnou práci a dobrý adventní čas
    <br>Martin
    <br><br>
    <button onclick="window.location.href='http://answer.setkani.org/db2_test.php';">(1) Vyzkouším si nové prostředí v testovací databázi, s daty mohu dělat cokoliv mě napadne</button>
    <br><br>
    <button onclick="createCookie('ezer_db2_go',1,1);window.location.href='http://answer.setkani.org/db2.php';">(2) Chci vyzkoušet ostrou verzi ale nebudu do ní před Mikulášem nic zapisovat ani mazat</button>
   </div>
  </div>
 </body>
</html>
__EOD
);

//   # inicializace systému Ans(w)er
//   #   $app        = kořenová podsložka aplikace ... ys/ys2/fa/fa2/cr
//   #   $app_name   = jméno aplikace
//   #   $db_name    = hlavní databáze ... bylo-li v URL &test=1 přidá se do options.tabu_db
//   #   $skin       = ck|ch
//   #   $js_lib     = pole s *.js
//   #   $css_lib    = pole s *.css
//   #   $options    = doplnění Ezer.options
//
//   require_once("answer.php");
//   $options= (object)array(
//     'web'        => "'http://www.setkani.org'", // web organizace - pro odmítnutí odhlášení
//     'skill'      => "'y'",
//     'autoskill'  => "'!y'",
//     'Google' => "{                      // definice oprávnění přístupu na Google
//         CLIENT_ID:'854681585120-rla91b0i46oei7njt6f32mst668871sa.apps.googleusercontent.com'
//       }"
//   );
//   $js= array("https://apis.google.com/js/client.js?onload=Ezer.Google.ApiLoaded","ds/fce.js");
// //   $css= array("./ezer2.2/client/ezer.css.php","./db/db.css.php","./ys/ys.css.php");
//   $css= array("skins/ck/ck.ezer.css");
//   answer_php('ys2','Ans(w)er','ezer_ys','ck',$js,$css,$options);

?>
