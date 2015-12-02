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

//   # nastavení systému Ans(w)er před voláním AJAX
//   #   $app        = kořenová podsložka aplikace ... ys/ys2/fa/fa2/cr
//   #   $answer_db  = logický název hlavní databáze (s případným '_test')
//   #   $dbs_plus   = pole s dalšími databázemi ve formátu $dbs
//   #   $php_lib    = pole s *.php - pro 'ini'
//
//   require_once("answer.inc");
//   $dbs_plus= array(
//     array( // ostré
//       'setkani' =>      array(0,'192.168.1.146','gandi','','cp1250','setkani',1)), // 1=vynechat zálohování
//     array( // lokální
//       'setkani' =>      array(0,'localhost','gandi','','utf8','setkani',1))        // 1=vynechat zálohování
//   );
//   $php_plus= array("ds/ds_fce.php","ys/ys_fce.php","ys/ys_ucet.php","ys/ys.ban.php",
//     "db/db_akce.php","db/db_akce2.php");
//   answer_ini('ys2','ezer_ys',$dbs_plus,$php_plus);
//   // SPECIFICKÉ PARAMETRY
//   $VPS= 'VPS';
//   global $ezer_path_root;
//   $path_banka['5500']= "$ezer_path_root/banky/5500/";
//   $EZER->options->org= 'YMCA Setkání';

?>
