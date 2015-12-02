<?php # Systém An(w)er/Ezer/YMCA Setkání, (c) 2008-2014 Martin Šmídek <martin@smidek.eu>
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
  # nastavení systému Ans(w)er před voláním AJAX
  #   $app        = kořenová podsložka aplikace ... ys/ys2/fa/fa2/cr
  #   $answer_db  = logický název hlavní databáze (s případným '_test')
  #   $dbs_plus   = pole s dalšími databázemi ve formátu $dbs
  #   $php_lib    = pole s *.php - pro 'ini'

  require_once("answer.inc");
  $dbs_plus= array(
    array( // ostré
      'setkani' =>      array(0,'192.168.1.146','gandi','','cp1250','setkani',1)), // 1=vynechat zálohování
    array( // lokální
      'setkani' =>      array(0,'localhost','gandi','','utf8','setkani',1))        // 1=vynechat zálohování
  );
  answer_ini('ys','ezer_ys',$dbs_plus,
    array("ys/ys_fce.php","ys/ys_lide.php","ds/ds_fce.php","db/db_akce.php","db/db_akce2.php"));
  // SPECIFICKÉ PARAMETRY
  global $ezer_path_root;
  $VPS= 'VPS';
  $path_banka['5500']= "$ezer_path_root/banky/5500/";
  $EZER->options->org= 'YMCA Setkání';
?>
