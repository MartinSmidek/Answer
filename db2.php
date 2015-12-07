<?php # Systém An(w)er/YMCA Setkání/YMCA Familia, (c) 2008-2015 Martin Šmídek <martin@smidek.eu>

// if ( !isset($_COOKIE['ezer_db2_go']) && !isset($_GET['martin']) ) {
//
// echo(<<<__EOD
// <html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en" dir="ltr" slick-uniqueid="1" spellcheck="false">
//  <head>
//   <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
//   <meta http-equiv="X-UA-Compatible" content="IE=9">
//   <link rel="shortcut icon" href="./db2/img/db2.png">
//   <title>Answer instalace</title>
//   <script>
//     var createCookie = function(name, value, days) {
//       var expires;
//       if (days) {
//         var date = new Date();
//         date.setTime(date.getTime() + (days * 24 * 60 * 60 * 1000));
//         expires = "; expires=" + date.toGMTString();
//       }
//       else {
//         expires = "";
//       }
//       document.cookie = name + "=" + value + expires + "; path=/";
//     }
//   </script>
//  </head>
//  <body style="padding:100px">
//   <div style="background-color:yellow; border:1px solid red; width:700px">
//    <div style="float:right;padding:5px;">2.12.2015</div>
//    <div style="padding:50px;">
//     Vážení klienti, milí kamarádi,
//     <br><br>na serveru je nainstalována nová verze systému Answer.
//     Obsahuje podle dohody výborů obou organizací spojenou databázi YMCA Setkání a YMCA Familia
//     (zvolené řešení je korektní vzhledem k ochraně osobních dat účastníků našich akcí).
//     <br>
//     <br>Vyzkoušejte si prosím novou podobu Answeru nejprve nanečisto v testovacím prostředí (1),
//     teprve až si vše vyzkoušíte, začněte pracovat v ostré verzi (2).
//     Změny do ostré verze ale prosím dělejte až po Mikuláši.
//     Pokud byste potřebovali nějakou informaci nebo vysvětlení, zavolejte mi prosím na 603 150 565,
//     nebo pište na <a href="mailto:martin@smidek.eu">můj mail</a>.
//     <br><br>
//     Přeji vám příjemnou práci a dobrý adventní čas
//     <br>Martin
//     <br><br>
//     <button onclick="window.location.href='http://answer.setkani.org/db2_test.php';">(1) Vyzkouším si nové prostředí v testovací databázi, s daty mohu dělat cokoliv mě napadne</button>
//     <br><br>
//     <button onclick="createCookie('ezer_db2_go',1,1);window.location.href='http://answer.setkani.org/db2.php';">(2) Chci vyzkoušet ostrou verzi ale nebudu do ní před Mikulášem nic zapisovat ani mazat</button>
//    </div>
//   </div>
//  </body>
// </html>
// __EOD
// );
//
// }
// else {
//   setcookie ("ezer_db2_go", "", time() - 3600);

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
  $app_last_access= "{$app}_last_access";
  if ( isset($_COOKIE[$app_last_access])
    && $_COOKIE[$app_last_access]>0 &&  $_COOKIE[$app_last_access]<4 ) {
    $cookie= $_COOKIE[$app_last_access];
  }
  $choice_css=
    $cookie==1 ? "skins/ck/ck.ezer.css=skin" : (
    $cookie==2 ? "skins/ch/ch.ezer.css=skin" : "skins/db/db.ezer.css=skin" );

  $js= array(
    "ds/fce.js"
  );
  $css= array($choice_css,
    "db2/db2.css"
  );
  answer_php($app,"Answer ...",'ezer_db2','db',$js,$css,$options);
// }
?>
