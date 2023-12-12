<?php
# pilotní verze online přihlašování pro YMCA Setkání (jen typ pro VPS)
# debuger je lokálne nastaven pro verze PHP: 7.2.33
$TEST= 0;
$OPTIONS= ['akce'=>'dolany','err'=>3];

if (isset($OPTIONS['akce']) && (!isset($_GET['akce']) || $_GET['akce']!='dolany')) 
  die("Online přihlašování na akce YMCA Setkání není k dospozici."); 
if (isset($_GET['trace']) || isset($_SESSION['akce']['trace'])) $TEST= 1;
    
init();
# ------------------------------------------ parametry přihlášky
$parm= [
  'akce:id_akce'  => 1539, 
  'akce:typ'      => 'VPS', 
  'akce:na'       => 'Duchovní obnovu VPS', 
  'akce:popis'    => "kterou se přihlašujete na tradiční duchovní obnovu, kterou pro nás"
                  . " připravuje Komunita blahoslavenství v Dolanech. Zahájení je v pátek 19. ledna "
                  . " v 18:00 a ukončení v neděli 21. ledna po obědě. ",
  'form:pata'     => 'Je možné, že se vám během vyplňování objeví nějaká chyba, '
                  . ' případně nedojde slíbené potvrzení. Omlouváme se za nepříjemnost s beta-verzí přihlášek.'
                  . '<br><br>Přihlaste se prosím v takovém případě mailem zaslaným na kancelar@setkani.org.'
                  . '<br>Připojte prosím popis závady.',
  'end'           => '.'
];
todo();
page();
exit;
# ----------------------------------------------------------------------------- inicializace procesu
function init() {
  global $post, $faze, $msg, $OPTIONS;
  global $trace, $totrace, $TEST, $y, $errors, $mysql_db_track, $mysql_tracked;  // $y je obecná stavová proměnná Ezer
  # ------------------------------------------ start
  date_default_timezone_set('Europe/Prague');
  if ( isset($_GET['err']) && $_GET['err'] ) error_reporting(-1); else error_reporting(0);
  ini_set('display_errors', 'On');
  # ------------------------------------------ init
  // skryté definice
  global $ezer_server, $dbs, $ezer_db, $USER, $kernel, $ezer_path_serv;
  $USER= (object)['abbr'=>'WEB'];
  $kernel= "ezer3.2";
  $ezer_path_serv= "$kernel/server";
  $deep_root= "../files/answer";
  require_once("$deep_root/db2.dbs.php");
  require_once("$kernel/server/ae_slib.php");
  require_once("$kernel/pdo.inc.php");
  require_once("$kernel/server/ezer_pdo.php");
  require_once("db2/db2_fce3.php");
  
  global $ezer_db, $db, $dbs, $ezer_server;
  // definice zápisů do _track
  $mysql_db_track= true;
  $mysql_tracked= ',akce,pobyt,spolu,osoba,tvori,rodina,_user,';
  // nastavení nového=prázdného formuláře
  session_start();
  # ------------------------------------------ trasování 
  $trace= '';
  if ($TEST) {
    $totrace= 'Mu';
    $_SESSION['akce']['trace']= $TEST;
  }
  $y= (object)[];
  $errors= [];
  // otevření databáze a redefine OBSOLETE
  if (isset($dbs[$ezer_server])) $dbs= $dbs[$ezer_server];
  if (isset($db[$ezer_server])) $db= $db[$ezer_server];
  $ezer_db= $dbs;
  ezer_connect('ezer_db2');
//  $trace.= debugx($_SESSION,'$_SESSION - vstup');
  if (!isset($_SESSION['akce']['faze'])) {
    $_POST= [];
//    $_POST= ['email'=>'martin@smidek.eu','pin'=>'1234'];
//    $_POST= ['email'=>'martin@smidek.eux'];
//    $_POST= ['email'=>'martin@smidek.eu'];
//    $_POST= ['email'=>'martin.smidek@gmail.com'];
    $_SESSION['akce']['faze']= 'a';
//    $_SESSION['akce']['faze']= 'b';
    $_SESSION['akce']['history']= '';
    $_SESSION['akce']['POST']= $_POST;
    $index= "prihlaska.php"; $del_index= '?';
    foreach ($OPTIONS as $get=>$val) {
      $index.= "$del_index$get=$val";
      $del_index= '&';
    }
    $_SESSION['akce']['index']= $index;
    $_SESSION['akce']['server']= $ezer_server;
  }
  $trace.= debugx($_SESSION['akce'],'$_SESSION[akce] - start');
  $trace.= debugx($_POST,'$_POST - start');
  $faze= $_SESSION['akce']['faze'];

  // zpracování hodnot
  $old_post= array_merge([],$_SESSION['akce']['POST']);
  $old_post= $_SESSION['akce']['POST'];
  $post= array_merge([],$_POST);
  // checkboxy musíme ze session vymazat a nově doplnit
  $checkboxs= ['a','b']; 
  foreach ($checkboxs as $fld) { unset($old_post[$fld]); }
  // pamatování hodnot pro postupnou úpravu
  $post= array_replace($old_post,$post);
  $msg= '';
  // zpamatování vstupních hodnot
  $trace.= '<hr>';
//  display("init-end fáze:$faze, _POST[email]={$_POST['email']}"
//  . ", _SESSION[akce][POST][email]={$_SESSION['akce']['POST']['email']}"
//  . ", old_post[email]={$old_post['email']}"
//  . ", post[email]={$post['email']}"
//  );
}
# --------------------------------------------------------------------------------- definice procesu
function todo() {
  global $post, $faze, $msg, $form, $parm;
  global $TEST, $errors, $ezer_server;
  global $email, $pin, $note;
  $_SESSION['akce']['history'].= $faze;
  $email= $post['email'] ?? '';                   
  display("todo-begin fáze:$faze, email=$email");
  $pin= $post['pin'] ?? '';
  $note= $post['note'] ?? '';
  $regexp= "REGEXP '(^|[;,\\\\s]+)$email($|[;,\\\\s]+)'";

  switch ($faze) {
  // --------------------------------------------- otestování emailu, pokud je ok poslání PIN
  // $post obsahuje: email, zaslany_pin
  //           nově: idr, ido_a, jmeno_a, ido_b, jmeno_b
  case 'a': 
    $chyby= null;
    $ok= emailIsValid($email,$chyby);
    if (!$ok) 
      $chyby= $email ? "Tuto emailová adresu není možné použít:<br>$chyby" : ' ';
    else {
      clear_post_but("/email|^.$/");
      // zjistíme, zda jej máme v databázi
      list($pocet,$ido,$role,$idr,$jmena)= select(
          "COUNT(id_osoba),id_osoba,role,id_rodina,GROUP_CONCAT(CONCAT(jmeno,' ',prijmeni))",
          'osoba AS o JOIN tvori USING (id_osoba) JOIN rodina USING (id_rodina)',
          "o.deleted='' AND role IN ('a','b') "
          . "AND (kontakt=1 AND email $regexp OR kontakt=0 AND emaily $regexp)");
      display("a1: $pocet,$ido");
      // a jestli již není na akci přihlášen
      if ($pocet==0) {
        $chyby= "Tento mail bohužel v evidenci YMCA Setkání nemáme,"
            . " <br>přihlaste se prosím pomocí jiného svého mailu nebo mailem manžela/ky.";
      }
      elseif ($pocet>1) {
        $chyby= "Tento mail používá více osob ($jmena), "
            . " <br>přihlaste se prosím pomocí jiného svého mailu nebo mailem manžela/ky.";
      }
      else { // mail je ok, zjistíme zda již není přihlášen
        list($idp,$kdy,$kdo)= select("id_pobyt,IFNULL(kdy,''),IFNULL(kdo,'')",
            "pobyt JOIN spolu USING (id_pobyt) "
            . "LEFT JOIN _track ON klic=id_pobyt AND kde='pobyt' AND fld='id_akce' ",
            "id_osoba=$ido AND id_akce={$parm['akce:id_akce']} ORDER BY id_pobyt DESC LIMIT 1");
        display("a2: $idp,$kdy,$kdo");
        if ($idp) {
          $kdy= $kdy ? "od ".sql_time1($kdy) : '';
          $kdo= $kdo ? "pod značkou $kdo" : '';
          $chyby= "Na této akci jste již $kdy přihlášeni $kdo."
              . "<br><br>Přejeme vám příjemný pobyt :-)";
        }
      }
    }
    if (!$chyby) {
      // zašleme PIN 
      $pin= rand(1000,9999);
//      query("UPDATE osoba SET pin=$pin,pin_vydan=NOW() WHERE id_osoba=$ido");
      $msg= simple_mail('martin@smidek.eu', $email, "PIN ($pin) pro prihlášení na akci",
          "V přihlášce na akci napiš vedle svojí mailové adresy $pin a pokračuj tlačítkem [Ověřit PIN]");
      if ( $msg!='ok' ) {
        $chyby.= "Litujeme, mail s PINem se nepovedlo odeslat, přihlas se prosím na akci jiným způsobem."
            . "<br>($msg)";
      }
      else {
        $msg= "Byl vám poslán mail";
        // doplníme hodnoty do $post 
        $post['zaslany_pin']= $pin;
        $post['idr']= $idr;
        if ($role=='a') {
          $post['ido_a']= $ido;
          $post['jmeno_a']= $jmena;
          list($post['ido_b'],$post['jmeno_b'])= select(
              "id_osoba,CONCAT(jmeno,' ',prijmeni)",'osoba JOIN tvori USING (id_osoba)',
              "id_rodina=$idr AND role='b' ");
        }
        else {
          $post['ido_b']= $ido;
          $post['jmeno_b']= $jmena;
          list($post['ido_a'],$post['jmeno_a'])= select(
              "id_osoba,CONCAT(jmeno,' ',prijmeni)",'osoba JOIN tvori USING (id_osoba)',
              "id_rodina=$idr AND role='a' ");
        }
      }
      $chyby.= "STOP";
      // jdeme dál
      $faze= 'b';
      todo();
      break;
    }
    if ($chyby) {
      $msg= "<p>$chyby</p>";
      $form= <<<__EOF
        <p>Abychom ověřili, že se přihlašujete právě vy, napište svůj mail, pošleme na něj přihlašovací PIN.</p>
        <input type="text" size="24" name='email' value='$email'>
        <input type="submit" value="Zaslat PIN">
        $msg
__EOF;
    }
    break;
  // --------------------------------------------------------------- porovnání zaslaného PINU
  // $post obsahuje: email, zaslany_pin, idr, ido_a, jmeno_a, ido_b, jmeno_b
  //           nově: pin
  case 'b': 
    clear_post_but("/email|zaslany_pin|idr|ido_a|jmeno_a|ido_b|jmeno_b/");
    // zjistíme PIN zapsaný u nositele mailové adresy
    if ($pin && $pin==$post['zaslany_pin']) {
      $post['a']= $post['b']= 'x'; // default = oba manžel=
      $faze= 'c';
      todo();
    }
    else {
      $msg= $pin ? "<p>Do mailu jsme poslali odlišný PIN</p>" : "<p></p>";
      $form= <<<__EOF
        <p>Na uvedený mail vám byl zaslán PIN, opište jej vedle své mailové adresy.</p>
        <input type="text" size="24" name='email' value='$email' disabled>
        <input type="text" size="4" name='pin' value="$pin">
        <input type="submit" value="ověřit PIN">
        $msg
__EOF;
    }
    break;
  // ----------------------------------------------------------------- vyžádání údajů přihlášky
  case 'c':
  // $post obsahuje: email, pin, idr, ido_a, jmeno_a, ido_b, jmeno_b, pin
  //           nově: a, b, note, ano, ne
    if (isset($post['ne'])) {
      clear_post_but("/---/");
      $faze= 'd';
      $msg= "Vyplňování přihlášky bylo ukončeno bez jejího odeslání. "
          . "<br>Na akci jste se tedy nepřihlásili.";
      todo();
      break;
    }
    if (isset($post['ano'])) {
      if ($TEST) { $msg= debugx($post,'opis hodnot'); }
      // validace hodnot
      $zapsat= true;
      if (!isset($post['a']) && !isset($post['b'])) {
        $msg= "Zaškrtněte prosím kdo se akce zúčastní";
        $zapsat= false;
      }      
      if ($zapsat) {
        // vytvoření pobytu
        // web_changes= 1/2 pro INSERT/UPDATE pobyt a spolu | 4/8 pro INSERT/UPDATE osoba
        $chng= array(
          (object)array('fld'=>'id_akce',    'op'=>'i','val'=>$parm['akce:id_akce']),
          (object)array('fld'=>'i0_rodina',  'op'=>'i','val'=>$post['idr']),
          (object)array('fld'=>'web_changes','op'=>'i','val'=>1),
          (object)array('fld'=>'web_zmena',  'op'=>'i','val'=>date('Y-m-d'))
        );
        if ($post['note'])
          $chng[]= (object)array('fld'=>'poznamka', 'op'=>'i','val'=>$post['note']);
        $idp= ezer_qry("INSERT",'pobyt',0,$chng);
        if (!$idp) $errors[]= "Nastala chyba při zápisu do databáze (p)"; 
        if ($idp) {
          // přidej k pobytu osoby
          if ($post['a']) {
            $ids= ezer_qry("INSERT",'spolu',0,array(
              (object)array('fld'=>'id_pobyt', 'op'=>'i','val'=>$idp),
              (object)array('fld'=>'id_osoba', 'op'=>'i','val'=>$post['ido_a']),
              (object)array('fld'=>'s_role',   'op'=>'i','val'=>1) // jako účastník
            ));
            if (!$ids) $errors[]= "Nastala chyba při zápisu do databáze (a)"; 
          }
          if ($post['b']) {
            $ids= ezer_qry("INSERT",'spolu',0,array(
              (object)array('fld'=>'id_pobyt', 'op'=>'i','val'=>$idp),
              (object)array('fld'=>'id_osoba', 'op'=>'i','val'=>$post['ido_b']),
              (object)array('fld'=>'s_role',   'op'=>'i','val'=>1) // jako účastník
            ));
            if (!$ids) $errors[]= "Nastala chyba při zápisu do databáze (b)"; 
          }
        }
        // připrav závěrečnou zprávu
        if (count($errors)) {
          $msg= "Při zpracování přihlášky došlo bohužel k chybě. "
              . "<br>Přihlaste se prosím posláním mailu Markétě Zelinkové";
        }
        else {
          $rekapitulace= "pro účastníky: ";
          if ($post['a']) $rekapitulace.= $post['jmeno_a'];
          if ($post['b']) $rekapitulace.= ($post['a'] ? ' a ' : '').$post['jmeno_b'];
          if ($post['note']) $rekapitulace.= "<br>s poznámkou {$post['note']}.";
          $msg= "<p>Na adresu $email bude posláno potvrzení o přihlášce</p>"
              . "$rekapitulace"
              . "<p>Těšíme se na setkání.</p>";
          $cc= $from= $ezer_server ? "kancelar@setkani.org" : "martin.smidek@gmail.com";
          simple_mail($from, $post['email'], "Potvrzení přijetí přihlášky", 
              "Potvrzujeme, že jsme přijali vaši přihlášku na {$parm['akce:na']}"
              . "<br>$rekapitulace",$cc);
        }
        // uzavři formulář
        clear_post_but("/---/");
        $faze= 'd';
        todo();
        break;
      }
    }
    // zobraz hodnoty z databáze pokud není ani ANO ani NE
    $a_role=  isset($post['a']) ? 'checked' : '';
    $b_role=  isset($post['b']) ? 'checked' : '';

    $form= <<<__EOF
      <p>Na tento mail vám bude posláno potvrzení o přijetí přihlášky:</p>
      <input type="text" size="24" name='email' value='$email' disabled>
      <input type="text" size="4" name='pin' value="$pin" disabled>
      <p>K pobytu na akci se přihlašuje:</p>
      <input type="checkbox" name='a' value="x" $a_role /><label for="a">{$post['jmeno_a']}</label>
      <br><input type="checkbox" name='b' value="x" $b_role /><label for="b">{$post['jmeno_b']}</label>
      <br><textarea rows="3" cols="32" name='note'>$note</textarea> 
      <br><input type="submit" name="ano" value="odeslat přihlášku" />
      <input type="submit" name="ne" value="neposílat" />
      <p>$msg</p>
__EOF;
    break;
  // ------------------------- konec
  case 'd':
    if (substr($_SESSION['akce']['history'],-2,1)=='d') {
      clear_post_but("/---/");
      $faze= 'a';
      todo();
    }
    else {
      $form= <<<__EOF
        <p>$msg</p>
__EOF;
    }
  }
  $_SESSION['akce']['faze']= $faze;
  $_SESSION['akce']['POST']= $post;
  display("todo-end fáze:$faze, email=$email");
}
// ------------------------------------------------------------------------------- zobrazení stránky
function page() {
  global $form, $parm, $index;
  global $TEST, $trace, $y, $errors;
  $icon= "akce.png";
  if ($TEST) {
    if (count($errors)) $trace.= '<hr><span style="color:red">'.implode('<hr>',$errors).'</span>';
//    $trace.= '<hr>'.debugx($post,'$post');
    $trace.= '<hr>'.debugx($_SESSION['akce'],'$_SESSION[akce] - výstup');
    $trace.= '<hr>'.nl2br($y->qry??'');
  }
  else $trace= '';
  echo <<<__EOD
  <!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
  <html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en" dir="ltr">
  <head>
    <meta charset="utf-8" />
    <meta Content-Type="text/html" />
    <meta http-equiv="X-UA-Compatible" content="IE=11" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title>Přihláška na akci YMCA Setkání</title>
    <link rel="shortcut icon" href="/akce/img/$icon" />
    <link rel="stylesheet" href="/akce/css/akce.css" type="text/css" media="screen" charset="utf-8" />
    <link rel="stylesheet" href="/ezer3.2/client/licensed/font-awesome/css/font-awesome.min.css?" type="text/css" media="screen" charset="utf-8">
    <link rel="stylesheet" id="customify-google-font-css" href="//fonts.googleapis.com/css?family=Open+Sans%3A300%2C300i%2C400%2C400i%2C600%2C600i%2C700%2C700i%2C800%2C800i&amp;ver=0.3.5" type="text/css" media="all">
  </head>
  <body onload="">
    <div id='obal'>
      <div id='head'><a href="https://www.setkani.org"><i class="fa fa-home"></i> YMCA Setkání</a></div>
      <h1>Přihláška na {$parm['akce:na']}</h1>
      <div id='popis'>{$parm['akce:popis']}</div>
      <div class='formular'>
        <form action="$index" method="post">
          $form
        </form>
      </div>
      <div id='tail'>{$parm['form:pata']}</div>
    </div>
    <div class='trace'>$trace</div>
  </body>
  </html>
__EOD;
}
# ----------------------------------------------------------------------------------- pomocné funkce
function clear_post_but($flds_match) {
  global $post;
  foreach (array_keys($post) as $fld) {
    if (!preg_match($flds_match,$fld)) {
      unset($post[$fld]);
      unset($_POST[$fld]);
    }
  }
  $_SESSION['akce']['POST']= $post;
}
function simple_mail($replyto,$address,$subject,$body,$cc='') {
  global $api_gmail_user, $api_gmail_pass, $api_gmail_name, $TEST;
  $msg= '';
  $smtp= (object)[
      "Host" => "smtp.gmail.com",
      "Port" => 465,
      "SMTPAuth" => 1,
      "SMTPSecure" => "ssl",
      "Username" => $api_gmail_user,
      "Password" => $api_gmail_pass
  ];
  $mail= mail2_new_PHPMailer($smtp);
  if ( !$mail ) { 
    $msg= "CHYBA odesílací adresu nelze použít ($api_gmail_user)";
    goto end;
  }
  $mail->From= $mail->Username;
  $mail->addReplyTo($replyto);
  if ($cc) {
    $mail->AddCC($cc);
  }
  $mail->FromName= $api_gmail_name;
  $mail->AddAddress($address);   
  $mail->Subject= $subject;
  $mail->Body= $body;
  if ($TEST) {
    $msg= 'ok'; // TEST bez odeslání
  }
  else {
    if ($TEST) {
      // pseudo dump 
      $mail->SMTPDebug= 3;
      $mail->Debugoutput= function($str, $level) { display("debug level $level; message: $str</div>");};
      $pars= (object)array();
      foreach (explode(',',"Mailer,Host,Port,SMTPAuth,SMTPSecure,Username,From,FromName,SMTPOptions") as $p) {
        $pars->$p= $mail->$p;
      }
      debug($pars,"nastavení PHPMAILER");
    }
    $ok= $mail->Send();
    if ( $ok  ) {
      $msg= "ok";
    }
    else {
      $msg= "CHYBA při odesílání mailu došlo k chybě: $mail->ErrorInfo";
      goto end;
    }
  }
  end:
    return $msg;
}

