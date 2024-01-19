<?php
header('Content-Type:text/html;charset=utf-8');
header('Cache-Control:no-cache,no-store,must-revalidate');
# pilotní verze online přihlašování pro YMCA Setkání (jen typ pro DS)
# debuger je lokálne nastaven pro verze PHP: 7.2.33 - musí být ručně spuštěn Chrome

if (!isset($_GET['akce']) || !is_numeric($_GET['akce'])) die("Online přihlašování není k dospozici."); 

$MAIL= 0; // 0 - mail se jen ukáže
$TEST= 0; // bez testování - lze nastavit url&test=n
$AKCE= "A_{$_GET['akce']}"; // ID akce pro SESSION
    
if (isset($_GET['test'])) 
  $TEST= $_GET['test'];
elseif (isset($_SESSION[$AKCE]['test'])) 
  $TEST= $_SESSION[$AKCE]['test'];

//$TEST= 0; // 0 = žádné trasování - ostrý běh až na $MAIL
//$TEST= 1; // 1 = trasování - ostrý běh až na $MAIL
//$TEST= 2; // 2 = trasování + přednastavený mail 
//$TEST= 3; // 3 = trasování + přednastavený mail a přeskok loginu

if ($TEST==3) $testovaci_mail= 'martin@smidek.eu';
//if ($TEST==3) $testovaci_mail= 'martin.smidek@outlook.com';
# ------------------------------------------ zpracování jednoho formuláře

init();
parm($_GET['akce']);
todo();
page();
exit;
# ------------------------------------------ stavové proměnné uložené v SESSION
# klient = ID přihlášeného 
# novi = počet uživatelem přidaných členů
# cleni = ID -> {spolu=0/1, jmeno, sex, role, narozeni}  ... ID může být  -<i> pro přidané členy
# ----------------------------------------------------------------------------- inicializace procesu
# inicializuje celý proces a provede potřebnou úpravu dat předaných přes POST
#   checkbox: chk_name ... pokud je v POST, tak nastaví chk_name na 1 
#             uplatní se v $post a $vars
#   submit:   cmd_name ... zruší cmd_x, která nejsou v aktuálním POST
#             uplatní se v $post
function init() {
  global $AKCE, $vars, $mailbox, $novi, $cleni, $post, $msg;
  global $TEST, $errors;  
  # ------------------------------------------ napojení na Ezer
  global $ezer_server, $dbs, $db, $ezer_db, $USER, $kernel, $ezer_path_serv, $mysql_db_track, 
      $mysql_tracked, $trace, $totrace, $y; // $y je obecná stavová proměnná Ezer
  date_default_timezone_set('Europe/Prague');
  if ($TEST || isset($_GET['err']) && $_GET['err'] ) error_reporting(-1); else error_reporting(0);
  ini_set('display_errors', 'On');
  // prostředí Ezer
  $USER= (object)['abbr'=>'WEB'];
  $kernel= "ezer3.2";
  $ezer_path_serv= "$kernel/server";
  $deep_root= "../files/answer";
  require_once("$deep_root/db2.dbs.php");
  require_once("$kernel/server/ae_slib.php");
  require_once("$kernel/pdo.inc.php");
  require_once("$kernel/server/ezer_pdo.php");
  require_once("db2/db2_fce3.php");
  // definice zápisů do _track
  $mysql_db_track= true;
  $mysql_tracked= ',akce,pobyt,spolu,osoba,tvori,rodina,_user,';
  # trasování 
  if ($TEST) {
    $totrace= 'Mu';
    $_SESSION[$AKCE]['test']= $TEST;
    $trace.= debugx($_POST,'$_POST - start');
  }
  $y= (object)[];
  // otevření databáze a redefine OBSOLETE
  if (isset($dbs[$ezer_server])) $dbs= $dbs[$ezer_server];
  if (isset($db[$ezer_server])) $db= $db[$ezer_server];
  $ezer_db= $dbs;
  ezer_connect('ezer_db2');
  # ------------------------------------------ ochrana proti refresh
  $trace= '';
  $mailbox= '';
  $refresh= '';
  $trace.= debugx($_POST,'$_POST - start');
  session_start();
  $stamp_sess= $_SESSION[$AKCE]['stamp']??date("i:s");
  $stamp_post= $_POST['stamp']??'?';
  display("SESSION: $stamp_sess POST: $stamp_post");
  if ($stamp_sess != $stamp_post) {
  //  $trace.= debugx($_POST,'$_POST - start');
  //  $trace.= debugx($_SESSION,'$_SESSION - start');
    do_session_restart();
    $_POST= [];
    $refresh= '<p><i>... refresh prohlížeče způsobil jeho inicializaci ...</i></p>';
  }
  # ------------------------------------------ start
  // nastavení nového=prázdného formuláře
  $errors= [];
//  $trace.= debugx($_SESSION,'$_SESSION - vstup');
  if (!isset($_SESSION[$AKCE]['faze'])) {
    $_POST= [];
    $_SESSION[$AKCE]['start']= 1;
    $_SESSION[$AKCE]['faze']= 'a';
    $_SESSION[$AKCE]['history']= '';
    $_SESSION[$AKCE]['klient']= 0;
    $_SESSION[$AKCE]['chk_souhlas']= 0;
    $_SESSION[$AKCE]['rodina']= 0;
    $_SESSION[$AKCE]['novi']= array();
    $_SESSION[$AKCE]['cleni']= array();
    $_SESSION[$AKCE]['post']= $_POST;
    $index= "prihlaska_2.php"; 
    $_SESSION[$AKCE]['index']= $index;
    $_SESSION[$AKCE]['server']= $ezer_server;
  }
//  $trace.= debugx($_SESSION[$AKCE],'$_SESSION[akce] - start');
  $vars= $_SESSION[$AKCE];
  $cleni= $vars['cleni'];
  $novi= $vars['novi'];
  foreach ($_POST as $tag=>$val) {
    if (substr($tag,0,1)=='-') { // položka z $novi
      list($id,$name)= explode('_',$tag);
      $novi[$id]->$name= $val;
    }
  }
  // zpracování hodnot
  $old_post= array_merge([],$vars['post']);
  foreach (array_keys($old_post) as $fld) {
    if (preg_match("/^cmd_.*$/",$fld)) {
      unset($old_post[$fld]);
    }
  }
  $post= array_merge([],$_POST);
  $post= array_replace($old_post,$post);
  // zpamatování vstupních hodnot typu checkbox
  $vars['chk_souhlas']= isset($_POST['chk_souhlas']) ? 1 : 0;
  foreach ($cleni as $id=>$clen) {
    $name= "{$id}_spolu";
    $clen->spolu= isset($_POST[$name]) ? 1 : 0;    
  }
  foreach ($novi as $id=>$novy) {
    foreach (array('spolu','syn','dcera') as $check ) {
      $name= "{$id}_$check";
      if (isset($_POST[$name]))
        $novy->$check= 1;    
      elseif ($check=='spolu')
        unset($novi[$id]);
      else
        $novy->$check= 0;    
    }
  }
  if ($TEST==2 && $vars['faze']=='a') {
    global $testovaci_mail;
    $trace.= "<br>ladící běh se simulovaným přihlášením na $testovaci_mail";
    $post= ['email'=>$testovaci_mail];
  }
  if ($TEST==3 && $vars['faze']=='a') {
    global $testovaci_mail;
    $trace.= "<br>ladící běh se simulovaným přihlášením na $testovaci_mail";
    $post= ['email'=>$testovaci_mail];
  }
  $vars['post']= $post;
  $_SESSION[$AKCE]= $vars;
  $msg= '';
}
# ------------------------------------------------------------------------------- parametrizace akce
# doplnění údajů pro přihlášku
function parm($id_akce) {
  global $TEST, $akce;
  $msg= '';
  // parametry přihlášky a ověření možnosti přihlášení
  list($ok,$web_online)= select("COUNT(*),web_online",'akce',"id_duakce=$id_akce");
  if (!$ok || !$web_online) { 
    $msg= "Na tuto akci se nelze přihlásit online"; goto end; }
  // dekódování web_online
  $akce= json_decode($web_online);
  if (!$akce || !$akce->p_enable) { 
    $msg= "Na tuto akci se bohužel nelze přihlásit online"; goto end; }
  // doplnění dalších údajů o akci
  list($akce->org,$akce->nazev,$garant,$od)= // doplnění garanta
      select("access,nazev,poradatel,datum_od",'akce',"id_duakce=$id_akce");
  if ($od<=date('Y-m-d')) { 
    $msg= "Akce '$akce->nazev' již proběhla, nelze se na ni přihlásit"; goto end; }
  list($ok,$akce->garant_jmeno,$akce->garant_telefon,$akce->garant_mail)= // doplnění garanta
      select("COUNT(*),CONCAT(jmeno,' ',prijmeni),telefon,email",
          "osoba LEFT JOIN _cis ON druh='akce_garant' AND data='$garant'",
          "id_osoba=ikona");
  // Přihlášku bohužel nelze použít - akce nemá definovaného garanta!
  $akce->help_kontakt= $ok 
      ? "<a href='mailto:$akce->garant_mail'>$akce->garant_jmeno $akce->garant_mail</a>" 
      : "<a href='mailto:kancelar@setkani.org'>kancelar@setkani.org</a>";
  $akce->form_pata= "Je možné, že se vám během vyplňování objeví nějaká chyba, "
    . " případně nedojde slíbené potvrzení. "
    . "<br><br>Přihlaste se prosím v takovém případě mailem zaslaným na $akce->help_kontakt."
    . "<br><br>Připojte prosím popis závady. Omlouváme se za nepříjemnost s beta-verzí přihlášek.";
  // doplnění konstant
  $akce->id_akce= $id_akce;
  $akce->form_souhlas= " Vyplněním této přihlášky dávám výslovný souhlas s použitím uvedených "
      . "osobních údajů pro potřeby organizace akcí YMCA Setkání v souladu s Nařízením "
      . "Evropského parlamentu a Rady (EU) 2016/679 ze dne 27. dubna 2016 o ochraně "
      . "fyzických osob a zákonem č. 101/2000 Sb. ČR. v platném znění. Současně souhlasím "
      . "s tím, že pořadatel je oprávněn dokumentovat její průběh – pořizovat foto, audio, "
      . "video záznamy a tyto materiály může použít pro účely další propagace své činnosti. "
      . "Přečetl jsem si a souhlasím s podrobnou Informací o zpracování osobních údajů "
      . "v YMCA Setkání, dostupnou na "
      . "<a href='https://www.setkani.org/ymca-setkani/5860' target='show'>www.setkani.org</a>.";
  // doplněné parametry z Answeru: nazev, garant:*, form:pata
end:    
  if ($msg) {
    $TEST= 0;
    page("<b style='color:red'><br>$msg</b>");
    exit;
  }
}
# --------------------------------------------------------------------------------- definice procesu
function todo() {
  global $vars;
  // logika formulářů
  if ($vars['faze']=='a') { // => a* | b
    clear_post_but("/email|^.$/");
    do_mail_klienta();  
  }  
  if ($vars['faze']=='b') { // => b* | c | n | a
    do_kontrola_pinu(); 
  }
  if ($vars['faze']=='c') { // => c* | d
    do_vyplneni_dat();  
  }
  if ($vars['faze']=='n') { // => n* | d
    do_novy_klient();  
  }
  if ($vars['faze']=='d') { // => .
    do_rozlouceni();  
    // vyčisti vše
    unset($vars['post']);
    do_session_restart();
//    if ($TEST) {
//      unset($_SESSION[$AKCE]);
//    }
//    else {
//      session_unset();
//      session_destroy();
//    }
//    session_write_close();
  }
  // pokud se cyklus vrátil
  if ($vars['faze']=='a') { // => a* | b
    clear_post_but("/email|^.$/");
    do_mail_klienta();  
  }  
}
# ---------------------------------------------------------------------------------- do_mail_klienta
# (a) získá mail klienta, ověří jeho korektnost a evidenci v DB a pošle mu mail s PINem
#  IN: email
# CMD: zaslat
# OUT: email, zaslany_pin, klient, rodina, cleni
# CMD poslat_pin: 
#   a - chyby adresy 
#   b - poslán mail
function do_mail_klienta() { // faze A
  global $msg, $vars, $post, $form;
  global $TEST, $refresh;
  
  clear_post_but("/email|zaslany_pin|pin/");
  do_begin();
  
  $post['email']= $post['email'] ?? '';                   
  $chyby= '';
  $ok= emailIsValid($post['email'],$chyby);
  if (!$ok) 
    $chyby= $post['email'] ? "Tuto emailovou adresu není možné použít:<br>$chyby" : ' ';
  if (!$chyby) {
    if ($TEST>1) {
      // zkratka se simulací přihlášení (nesmí být už přihlášen)
      $pin= '----';
      $post['pin']= $pin;
      $msg= 'ok';
    }
    else {
      // zašleme PIN 
      $pin= rand(1000,9999);
//      query("UPDATE osoba SET pin=$pin,pin_vydan=NOW() WHERE id_osoba=$ido");
      $msg= simple_mail('martin@smidek.eu', $post['email'], "PIN ($pin) pro prihlášení na akci",
          "V přihlášce na akci napiš vedle svojí mailové adresy $pin a pokračuj tlačítkem [Ověřit PIN]");
      if ( $msg!='ok' ) {
        $chyby.= "Litujeme, mail s PINem se nepovedlo odeslat, přihlas se prosím na akci jiným způsobem."
            . "<br>($msg)";
      }
    }
    if ( $msg=='ok' ) {
      $msg= "Byl vám poslán mail";
      // doplníme hodnoty do $post 
      $post['zaslany_pin']= $pin;
    }
    // jdeme dál
    $vars['faze']= 'b';
  }
  if ($chyby) {
    $msg= zvyraznit("<p>$chyby</p>");
    $form= <<<__EOF
      $refresh
      <p>Abychom ověřili, že se přihlašujete právě vy, napište svůj mail, pošleme na něj přihlašovací PIN.</p>
      <input type="text" size="24" name='email' value='{$post['email']}' placeholder='@'>
      <input type="hidden" name='pin' value=''>
      <input type="submit" name="cmd_zaslat" value="Zaslat PIN">
      $msg
__EOF;
  }
  do_end();
}
# --------------------------------------------------------------------------------- do_kontrola_pinu
# (b) ověří zapsaný PIN proti poslanému, v případě shody zjistí informace z db
#  IN: email, zaslany_pin
# CMD: overit
# OUT: email, zaslany_pin, klient, rodina, cleni
# CMD kontrola pinu a db
#   b - to je jiný pin 
#   n - není v db
#   c - je v db
#   d - je v db ale již přihlášen
# CMD registrace
#   n - ...
# CMD jiný mail
#   a - ...
function do_kontrola_pinu() { // fáze B
  global $akce, $msg, $cleni, $vars, $post, $form;
  do_begin();
  // -------------------------------------------- jiný mail (a)
  if (isset($post['cmd_jiny_mail'])) {
    clear_post_but("/---/");
    $vars['klient']= 0;
    $vars['faze']= 'a';
    do_end();
    return;
  }
  // -------------------------------------------- registrace (n)
  if (isset($post['cmd_registrace'])) {
    $vars['faze']= 'n';
    goto end;
  }
  // -------------------------------------------- ... kontrola pinu a údajů db
  clear_post_but("/email|zaslany_pin|pin/");

  $msg= '???';
  $pin= $post['pin'] ?? '';
  // zjistíme PIN zapsaný u nositele mailové adresy
  if ($pin && $pin==$post['zaslany_pin']) {
    // zjistíme, zda jej máme v databázi
    $regexp= "REGEXP '(^|[;,\\\\s]+){$post['email']}($|[;,\\\\s]+)'";
    list($pocet,$ido,$role,$idr,$jmena)= select(
        "COUNT(id_osoba),id_osoba,role,id_rodina,GROUP_CONCAT(CONCAT(jmeno,' ',prijmeni))",
        'osoba AS o JOIN tvori USING (id_osoba) JOIN rodina USING (id_rodina)',
        "o.deleted='' AND role IN ('a','b') "
        . "AND (kontakt=1 AND email $regexp OR kontakt=0 AND emaily $regexp)");
    // a jestli již není na akci přihlášen
    if ($pocet==0) {
      $msg= "Tento mail bohužel v evidenci YMCA Setkání nemáme,"
          . " pokud jste se již nějaké naší akce zúčastnili, "
          . "přihlaste se prosím pomocí mailu, který jste tehdy použil/a"
          . ($akce->p_registrace ? " - pokud s námi budete poprvé, pokračujte." : '.');
      $vars['klient']= -1;
    }
    elseif ($pocet>1) {
      $msg= "Tento mail používá více osob ($jmena), "
          . " <br>přihlaste se prosím pomocí jiného svého mailu (nebo mailem manžela/ky).";
      $vars['faze']= 'a';
      goto end;
    }
    else { // pocet==1 ... mail je jednoznačný
      $vars['klient']= $ido;
      $vars['rodina']= $idr;
        // položky do hlavičky
      $vars['user']= "$jmena<br>{$post['email']}";

      // zjistíme zda již není přihlášen
      list($idp,$kdy,$kdo)= select("id_pobyt,IFNULL(kdy,''),IFNULL(kdo,'')",
          "pobyt JOIN spolu USING (id_pobyt) "
          . "LEFT JOIN _track ON klic=id_pobyt AND kde='pobyt' AND fld='id_akce' ",
          "(id_osoba={$vars['klient']} OR i0_rodina={$vars['rodina']}) AND id_akce=$akce->id_akce "
          . "ORDER BY id_pobyt DESC LIMIT 1");
      display("a2: $idp,$kdy,$kdo");
      if ($idp) {
        $kdy= $kdy ? sql_time1($kdy) : '';
        $msg= $kdo=='WEB' ? "Na tuto akci jste se již $kdy přihlásili online přihláškou." : (
            $kdo ? "Na této akci jste již $kdy přihlášeni (zápis provedl uživatel se značkou $kdo" 
            : "Na této akci jste již $kdy přihlášeni.");
        $msg.= "<br><br>Přejeme vám příjemný pobyt :-)";
        $vars['faze']= 'd';
        goto end;
      }
      else { // klientova rodin ani klient sám an akci není

        // načti členy rodiny
        $ro= pdo_query(
          "SELECT id_osoba,role,jmeno,prijmeni,sex,narozeni
          FROM osoba AS o JOIN tvori USING (id_osoba)
          WHERE id_rodina=$idr AND o.deleted='' AND role IN ('a','b','d') 
          ORDER BY IF(id_osoba=$ido,'0',narozeni)  ");
        while ($ro && (list($ido_c,$role,$jmeno,$prijmeni,$sex,$narozeni)=pdo_fetch_array($ro))) {
          $cleni[$ido_c]= (object)array('spolu'=>$ido==$ido_c ? 1 : 0,
              'jmeno'=>$jmeno,'prijmeni'=>$prijmeni,'role'=>$role,
              'sex'=>$sex,'narozeni'=>$narozeni);
        }


        $msg= '';
        $vars['faze']= 'c';
        goto end;
      }
    }
  }
  if ($msg) {
    if (($vars['klient']??0)==-1) {
      $form= <<<__EOF
        <p>$msg</p>
        <input type="text" size="24" name='email' value="{$post['email']}" disabled placeholder='@'>
        <input type='submit' name='cmd_jiny_mail' value='zkusím jiný mail'>
__EOF;
      $form.= $akce->p_registrace
          ? "<input type='submit' name='cmd_registrace' value='pokračovat s tímto mailem'>"
          : "<p>Případně požádejte o radu organizátory akce $akce->help_kontakt.</p>";
    }
    else {
      $msg= $pin ? zvyraznit("<p>Do mailu jsme poslali odlišný PIN</p>") : "<p></p>";
      $form= <<<__EOF
        <p>Na uvedený mail vám byl zaslán PIN, opište jej vedle své mailové adresy.</p>
        <input type="text" size="24" name='email' value="{$post['email']}" disabled placeholder='@'>
        <input type='text' size="4" name='pin' value='$pin'>
        <input type='submit' name='cmd_overit' value='ověřit PIN'>
        $msg
__EOF;
    }
  }
end:  
  do_end();
}
# ---------------------------------------------------------------------------------- do_vyplneni_dat
# (c) získá data od klienta
#  IN: email, zaslany_pin, klient, rodina, cleni
# CMD: nove, ne, ano
# OUT: email, zaslany_pin, klient, rodina, cleni, novi, msg
# CMD další dítě
#   c - řádek pro nové dítě
# CMD kontrola vyplnění
#   c - doplňte a opravte data 
#   d - kontrola ok 
# CMD zapsání přihlášky 
#   d - uloženo a poslán mail
#   d - nastal problém
# CMD odchod bez zápisu
#   d - end
function do_vyplneni_dat() {
  global $akce, $msg, $vars, $novi, $cleni, $post, $form;
  global $errors;
  
  do_begin();
  
  $mis_souhlas= '';
  $post['note']= $post['note'] ?? '';
  // -------------------------------------------- nové dítě
  if (isset($post['cmd_nove'])) {
    clear_post_but("/email|zaslany_pin|pin|note/");
    $id= '-'.(count($novi)+1);
    $novi[$id]= (object)array('spolu'=>1,'jmeno'=>'','prijmeni'=>'',
        'syn'=>'','dcera'=>'','narozeni'=>'');
  }
  // -------------------------------------------- nepřihlašovat
  if (isset($post['cmd_ne'])) {
    clear_post_but("/---/");
    $msg= "Vyplňování přihlášky bylo ukončeno bez jejího odeslání. "
        . "<br>Na akci jste se tedy nepřihlásili.";
    $vars['faze']= 'd';
    goto end;
  }
  // -------------------------------------------- kontrola hodnot
  if (isset($post['cmd_ano']) || isset($post['cmd_check'])) {
    $zapsat= true;
    $neuplne= array();
    $poznamka= array();
    // ------------------------------ je aspoň jeden přihlášený?
    $spolu= 0;
    foreach ($cleni as $clen) {
      $spolu+= $clen->spolu;
    }
    foreach ($novi as $novy) {
      $spolu+= $novy->spolu;
    }
    if (!$spolu) {
      $neuplne[]= "Zaškrtněte prosím kdo se akce zúčastní";
      $zapsat= false;
    }      
    // ------------------------------ mají nově vyplnění všechny údaje?
    foreach ($novi as $novy) {
      if (!trim($novy->jmeno) || !trim($novy->prijmeni) || !trim($novy->narozeni)) 
        $neuplne[]= "vyplňte prosím u přidaných dětí jméno, příjmení a datum narození";
      // ------------------------------ je ok údaj syn&dcera?
      if ($novy->syn && $novy->dcera)
        $neuplne[]= "zvolte jen syn NEBO dcera (nebo nechte nevyplněné, pokud dítě není z vaší rodiny) ";
      if (!$novy->syn && !$novy->dcera)
        $poznamka[]= "<br>(volbu ANI dcera ANI syn chápeme tak, že se nejedná o člena vaší rodiny)";
      if ($novy->narozeni) {
        $datum= str_replace(' ','',$novy->narozeni);
        $dmy= explode('.',$datum);
        if (count($dmy)!=3) 
          $neuplne[]= "napište datum narození ve tvaru den.měsíc.rok ";
        else {
          if (!checkdate($dmy[1],$dmy[0],$dmy[2]))
            $neuplne[]= "opravte prosím datum narození (den.měsíc.rok) - je nějaké divné";
          elseif (date('Y')-$dmy[2] > 99 || date('Y')-$dmy[2] < 0) 
            $neuplne[]= "opravte prosím datum narození - nevypadá pravděpodobně";
        }
      }
    }
    if (!$vars['chk_souhlas']) {
      $neuplne[]= "potvrďte prosím svůj souhlas";
      $mis_souhlas= "class=missing";
    }
    if (count($neuplne)) 
      $msg.= zvyraznit(implode('<br>',$neuplne));
    elseif (isset($post['cmd_check'])) {
      $msg.= zvyraznit("Přihláška vypadá dobře, zašlete nám ji prosím",1);
      if (count($poznamka))
        $msg.= zvyraznit(implode('<br>',$poznamka),1);
    }
  }
  // -------------------------------------------- zápis do databáze pokud není $TEST>1
  $errors= [];
  if (isset($post['cmd_ano']) && $zapsat) {
    // vytvoření pobytu
    // web_changes= 1/2 pro INSERT/UPDATE pobyt a spolu | 4/8 pro INSERT/UPDATE osoba
    $chng= array(
      (object)array('fld'=>'id_akce',    'op'=>'i','val'=>$akce->id_akce),
      (object)array('fld'=>'i0_rodina',  'op'=>'i','val'=>$vars['rodina']),
      (object)array('fld'=>'web_changes','op'=>'i','val'=>1),
      (object)array('fld'=>'web_zmena',  'op'=>'i','val'=>date('Y-m-d'))
    );
    if ($post['note'])
      $chng[]= (object)array('fld'=>'poznamka', 'op'=>'i','val'=>$post['note']);
    $idp= _ezer_qry("INSERT",'pobyt',0,$chng);
    if (!$idp) $errors[]= "Nastala chyba při zápisu do databáze (p)"; 
    if (!count($errors)) {
      // ------------------------------ vytvoř nové členy rodiny
      foreach ($novi as $novy) {
        // přidání člena rodiny
        nove_dite($idp,$vars['rodina'],$novy->jmeno,$novy->prijmeni,$novy->narozeni,$novy->syn,$novy->dcera);
      }
      // ------------------------------ přidej staré členy rodiny
      if (!count($errors)) {
        foreach ($cleni as $ido=>$clen) {
          if ($clen->spolu) {
            $s_role= $clen->role=='d' ? 2 : 1;
            $chng= array(
              (object)array('fld'=>'id_pobyt',  'op'=>'i','val'=>$idp),
              (object)array('fld'=>'id_osoba',  'op'=>'i','val'=>$ido),
              (object)array('fld'=>'s_role',    'op'=>'i','val'=>$s_role)
            );
            $ids= _ezer_qry("INSERT",'spolu',0,$chng);
            if (!$ids) $errors[]= "Nastala chyba při zápisu do databáze (cs)"; 
          }
        }
      }

    }
    // uzavři formulář závěrečnou zprávou
    clear_post_but("/email/");
    $msg= count($errors) ? 'ko' : 'ok';
    $vars['faze']= 'd';
    goto end;
  }

  // zobraz hodnoty z databáze pokud není ani ANO ani NE
  $old_cleni= ''; $del= '';
  foreach ($cleni as $id=>$clen) {
    $name= "{$id}_spolu";
    $spolu=  $cleni[$id]->spolu ? 'checked' : '';
    $narozeni= substr($clen->narozeni,5,5)=='00-00' 
        ? substr($clen->narozeni,0,4) : sql_date1($clen->narozeni,0);
    $old_cleni.= "$del<input type='checkbox' name='$name' value='x' $spolu />"
        . "<label for='$name'>$clen->jmeno $clen->prijmeni, $narozeni</label>";
    $del= '<br>';
  }
  // pole pro přidání nových členů
  $new_cleni= ''; 
  foreach ($novi as $id=>$novy) {
    $spolu=  $novy->spolu ? 'checked' : '';
    $syn=  $novy->syn ? 'checked' : '';
    $dcera=  $novy->dcera ? 'checked' : '';
    $jmeno= $novy->jmeno ? "value='$novy->jmeno'" : "placeholder='jméno'";
    $prijmeni= $novy->prijmeni ? "value='$novy->prijmeni'" : "placeholder='příjmení'";
    $narozeni= $novy->narozeni ? "value='$novy->narozeni'" : "placeholder='narození'";
    $new_cleni.= <<<__EOF
        <br><input type='checkbox' name='{$id}_spolu' value='x' $spolu />
        <input type='text' name='{$id}_jmeno' size='7' $jmeno />
        <input type='text' name='{$id}_prijmeni' size='10' $prijmeni' />
        <input type='text' name='{$id}_narozeni' size='9' $narozeni />
        <input type='checkbox' name='{$id}_syn' value='x' $syn /><label for='{$id}_syn'>syn</label>
        <input type='checkbox' name='{$id}_dcera' value='x' $dcera /><label for='{$id}_dcera'>dcera</label>
__EOF;
  }

//    <p>Na zde uvedený mail vám pošleme potvrzení o přijetí přihlášky:</p>
//    <input type="text" size="24" name='email' value="{$post['email']}" disabled>
//    <input type="text" size="4" name='pin' value="{$post['zaslany_pin']}" disabled>
  $souhlas=  $vars['chk_souhlas'] ? 'checked' : '';
  $form= <<<__EOF
    <p>Poznačte prosím, koho na akci přihlašujete:</p>
    $old_cleni
    $new_cleni
    <br><button type="submit" name="cmd_nove"><i class="fa fa-green fa-plus"></i>
      chci přihlásit další dítě</button>
    <p>Doplňte případnou poznámku pro organizátory akce:</p>
    <textarea rows="3" cols="46" name='note'>{$post['note']}</textarea> 
    <br>
    <input type='checkbox' name='chk_souhlas' value=''  $souhlas $mis_souhlas>
    <label for='chk_souhlas' class='souhlas'>$akce->form_souhlas</label>
    <br><br>
    <br><button type="submit" name="cmd_check"><i class="fa fa-question"></i>
      zkontrolovat před odesláním</button>
    <button type="submit" name="cmd_ano"><i class="fa fa-green fa-send-o"></i> 
      odeslat přihlášku</button>
    <button type="submit" name="cmd_ne"><i class="fa fa-times fa-red"></i> 
      neposílat</button>
    <p>$msg</p>
__EOF;
end:
  do_end();
}
# ---------------------------------------------------------------------------------- do_vyplneni_dat
# (n) získá data od klienta
#  IN: email, zaslany_pin, klient, rodina, novi
# CMD: nove, ne, ano
# OUT: email, zaslany_pin, klient, rodina, novi, msg
# CMD další člen
#   c - řádek pro nového člena
# CMD kontrola vyplnění
#   c - doplňte a opravte data 
#   d - kontrola ok 
# CMD zapsání přihlášky 
#   d - uloženo a poslán mail
#   d - nastal problém
# CMD odchod bez zápisu
#   d - end
function do_novy_klient() {
  global $akce, $msg, $vars, $novi, $post, $form;
  
  do_begin();
  $post['note']= $post['note'] ?? '';
  
  $mis_souhlas= '';

  // -------------------------------------------- iniciace formuláře
  $faze_predchozi= substr($vars['history'],-2,1);
  if ($vars['faze']!=$faze_predchozi) {
    clear_post_but("/email|zaslany_pin|pin|note/");
    $novi= [];
    $novi[-1]= (object)array('spolu'=>1,'jmeno'=>'','prijmeni'=>'',
        'narozeni'=>'','ulice'=>'','psc'=>'','obec'=>'','telefon'=>'','email'=>'');
  }
  // -------------------------------------------- nový člen
  if (isset($post['cmd_nove'])) {
    $id= '-'.(count($novi)+1);
    $novi[$id]= (object)array('spolu'=>1,'jmeno'=>'','prijmeni'=>'',
        'syn'=>'','dcera'=>'','narozeni'=>'');
  }
  // -------------------------------------------- nepřihlašovat
  if (isset($post['cmd_ne'])) {
    clear_post_but("/---/");
    $msg= "Vyplňování registrace a přihlášení na akci bylo ukončeno bez jejího uložení. "
        . "<br>Na akci jste se tedy nepřihlásili.";
    $vars['faze']= 'd';
    goto end;
  }
  // -------------------------------------------- kontrola hodnot
  if (isset($post['cmd_ano_novy']) || isset($post['cmd_check'])) {
    $zapsat= true;
    $neuplne= array();
    $poznamka= array();
//    // ------------------------------ je aspoň jeden přihlášený?
//    $spolu= 0;
//    foreach ($novi as $novy) {
//      $spolu+= $novy->spolu;
//    }
//    if (!$spolu) {
//      $neuplne[]= "Zaškrtněte prosím kdo se akce zúčastní";
//      $zapsat= false;
//    }      
    // ------------------------------ mají nově vyplnění všechny údaje?
    foreach ($novi as $id=>$novy) {
      if ($id==-1) {
        // kontrola klienta
        if (!trim($novy->jmeno) || !trim($novy->prijmeni) || !trim($novy->narozeni)) 
          $neuplne[]= "vyplňte prosím svoje jméno, příjmení a datum narození";
        if (!trim($novy->ulice) || !trim($novy->psc) || !trim($novy->obec)) 
          $neuplne[]= "vyplňte prosím poštovní adresu svého bydliště";
        if (!trim($novy->telefon)) 
          $neuplne[]= "vyplňte prosím číslo svého mobilu";
      }
      else {
        if (!trim($novy->jmeno) || !trim($novy->prijmeni) || !trim($novy->narozeni)) 
          $neuplne[]= "vyplňte prosím u přidaných dětí jméno, příjmení a datum narození";
        // ------------------------------ je ok údaj syn&dcera?
        if ($novy->syn && $novy->dcera)
          $neuplne[]= "zvolte jen syn NEBO dcera (nebo nechte nevyplněné, pokud dítě není z vaší rodiny) ";
        if (!$novy->syn && !$novy->dcera)
          $poznamka[]= "<br>(volbu ANI dcera ANI syn chápeme tak, že se nejedná o člena vaší rodiny)";
      }
      if ($novy->narozeni) {
        $datum= str_replace(' ','',$novy->narozeni);
        $dmy= explode('.',$datum);
        if (count($dmy)!=3) 
          $neuplne[]= "napište datum narození ve tvaru den.měsíc.rok ";
        else {
          if (!checkdate($dmy[1],$dmy[0],$dmy[2]))
            $neuplne[]= "opravte prosím datum narození (den.měsíc.rok) - je nějaké divné";
          elseif (date('Y')-$dmy[2] > 99 || date('Y')-$dmy[2] < 0) 
            $neuplne[]= "opravte prosím datum narození - nevypadá pravděpodobně";
        }
      }
    }
    if (!$vars['chk_souhlas']) {
      $neuplne[]= "projevte prosím svůj souhlas";
      $mis_souhlas= "class=missing";
    }
    if (count($neuplne)) 
      $msg.= zvyraznit(implode('<br>',$neuplne));
    elseif (isset($post['cmd_check'])) {
      $msg.= zvyraznit("Přihláška vypadá dobře, zašlete nám ji prosím",1);
      if (count($poznamka))
        $msg.= zvyraznit(implode('<br>',$poznamka),1);
    }
    if (count($neuplne)) $zapsat= false;
  }
  // -------------------------------------------- zápis do databáze pokud není $TEST>1
  $errors= [];
  if (isset($post['cmd_ano_novy']) && $zapsat) {
    // --------------------------- zapiš klienta
    $novy= $novi[-1];
    $sex= select('sex','_jmena',"jmeno='{$novy->jmeno}' LIMIT 1");
    $sex= $sex==1 || $sex==2 ? $sex : 0;
    $ma_rodinu= isset($novi[-2]) ? 1 : 0;
    $narozeni= sql_date($novy->narozeni,1);
    $chng= array(
      (object)array('fld'=>'access',   'op'=>'i','val'=>$akce->org),
      (object)array('fld'=>'jmeno',    'op'=>'i','val'=>$novy->jmeno),
      (object)array('fld'=>'prijmeni', 'op'=>'i','val'=>$novy->prijmeni),
      (object)array('fld'=>'sex',      'op'=>'i','val'=>$sex),
      (object)array('fld'=>'narozeni', 'op'=>'i','val'=>$narozeni),
      (object)array('fld'=>'kontakt',  'op'=>'i','val'=>1),
      (object)array('fld'=>'telefon',  'op'=>'i','val'=>$novy->telefon),
      (object)array('fld'=>'email',    'op'=>'i','val'=>$post['email']),
      (object)array('fld'=>'adresa',  'op'=>'i','val'=>1-$ma_rodinu)
    );
    if (!$ma_rodinu) {
      array_push($chng,
        (object)array('fld'=>'ulice',   'op'=>'i','val'=>$novy->ulice),
        (object)array('fld'=>'psc',     'op'=>'i','val'=>$novy->psc),
        (object)array('fld'=>'obec',    'op'=>'i','val'=>$novy->obec)
      );
    }
    $klient= _ezer_qry("INSERT",'osoba',0,$chng);
    if (!$klient) $errors[]= "Nastala chyba při zápisu do databáze (n.o)"; 
    // --------------------------- pokud děti, založ rodinu klienta
    $rodina= 0;
    if (!count($errors) && $ma_rodinu) {
      $nazev= preg_replace('~ová$~','',$novy->prijmeni).'ovi';
      $chng= array(
        (object)array('fld'=>'access',  'op'=>'i','val'=>$akce->org),
        (object)array('fld'=>'nazev',   'op'=>'i','val'=>$nazev),
        (object)array('fld'=>'ulice',   'op'=>'i','val'=>$novy->ulice),
        (object)array('fld'=>'psc',     'op'=>'i','val'=>$novy->psc),
        (object)array('fld'=>'obec',    'op'=>'i','val'=>$novy->obec)
      );
      $rodina= _ezer_qry("INSERT",'rodina',0,$chng);
      if (!$rodina) $errors[]= "Nastala chyba při zápisu do databáze (n.r)"; 
      // přidej do rodiny
      if (!count($errors)) {
        $role= ($sex==1 ? 'a' : ($sex==2 ? 'b' : 'p'));
        $chng= array(
          (object)array('fld'=>'id_osoba', 'op'=>'i','val'=>$klient),
          (object)array('fld'=>'id_rodina','op'=>'i','val'=>$rodina),
          (object)array('fld'=>'role',     'op'=>'i','val'=>$role)
        );
        $t= _ezer_qry("INSERT",'tvori',0,$chng);
        if (!$t) $errors[]= "Nastala chyba při zápisu do databáze (n.t)"; 
      }
    }
    if (!count($errors)) {
      // ------------------------- vytvoř pobyt a zařaď klienta
      // web_changes= 1/2 pro INSERT/UPDATE pobyt a spolu | 4/8 pro INSERT/UPDATE osoba
      $chng= array(
        (object)array('fld'=>'id_akce',    'op'=>'i','val'=>$akce->id_akce),
        (object)array('fld'=>'i0_rodina',  'op'=>'i','val'=>$rodina),
        (object)array('fld'=>'web_changes','op'=>'i','val'=>1),
        (object)array('fld'=>'web_zmena',  'op'=>'i','val'=>date('Y-m-d'))
      );
      if ($post['note'])
        $chng[]= (object)array('fld'=>'poznamka', 'op'=>'i','val'=>$post['note']);
      $idp= _ezer_qry("INSERT",'pobyt',0,$chng);
      if (!$idp) $errors[]= "Nastala chyba při zápisu do databáze (n.p)"; 
      // je na akci
      if (!count($errors)) {
        $chng= array(
          (object)array('fld'=>'id_pobyt',  'op'=>'i','val'=>$idp),
          (object)array('fld'=>'id_osoba',  'op'=>'i','val'=>$klient),
          (object)array('fld'=>'s_role',    'op'=>'i','val'=>1) // účastník
        );
        $ids= _ezer_qry("INSERT",'spolu',0,$chng);
        if (!$ids) $errors[]= "Nastala chyba při zápisu do databáze (n.s)"; 
      }
      if (!count($errors)) {
        foreach ($novi as $id=>$novy) {
          if ($id==-1) continue;
          // přidání člena rodiny
          nove_dite($idp,$rodina,$novy->jmeno,$novy->prijmeni,$novy->narozeni,$novy->syn,$novy->dcera);
        }        
      }
    }    
    // uzavři formulář závěrečnou zprávou
    clear_post_but("/email/");
    $msg= count($errors) ? 'ko' : 'ok';
    $vars['faze']= 'd';
    goto end;
  }
  

  // ------------------------------ formulář
  // pole pro přidání nových členů
  $new_klient= '';
  $new_cleni= ''; 
  $dalsi= count($novi)>1 ? 'přihlašuji další dítě' : 'přihlašuji dítě';
  foreach ($novi as $id=>$novy) {
//    $spolu=  $novy->spolu ? 'checked' : '';
    $jmeno= $novy->jmeno ? "value='$novy->jmeno'" : "placeholder='jméno'";
    $prijmeni= $novy->prijmeni ? "value='$novy->prijmeni'" : "placeholder='příjmení'";
    $narozeni= $novy->narozeni ? "value='$novy->narozeni'" : "placeholder='narození'";
    if ($id==-1) {
      $ulice= $novy->ulice ? "value='$novy->ulice'" : "placeholder='ulice nebo č.p.'";
      $psc= $novy->psc ? "value='$novy->psc'" : "placeholder='PSČ'";
      $obec= $novy->obec ? "value='$novy->obec'" : "placeholder='obec/město'";
      $telefon= $novy->telefon ? "value='$novy->telefon'" : "placeholder='mobil/telefon'";
//        <input type='checkbox' name='{$id}_spolu' value='x' $spolu />
      $new_klient.= <<<__EOF
        <input type='text' name='{$id}_jmeno' size='7' $jmeno />
        <input type='text' name='{$id}_prijmeni' size='10' $prijmeni' />
        <input type='text' name='{$id}_narozeni' size='9' $narozeni />
        <input type='text' name='{$id}_telefon' size='11' $telefon />
        <br>
        <input type='text' name='{$id}_ulice' size='15' $ulice />
        <input type='text' name='{$id}_psc' size='5' $psc' />
        <input type='text' name='{$id}_obec' size='20' $obec />
__EOF;
    }
    else {
      $syn=  $novy->syn??'' ? 'checked' : '';
      $dcera=  $novy->dcera??'' ? 'checked' : '';
//        <input type='checkbox' name='{$id}_spolu' value='x' $spolu />
      $new_cleni.= <<<__EOF
        <input type='text' name='{$id}_jmeno' size='7' $jmeno />
        <input type='text' name='{$id}_prijmeni' size='10' $prijmeni' />
        <input type='text' name='{$id}_narozeni' size='9' $narozeni />
        <input type='checkbox' name='{$id}_syn' value='x' $syn /><label for='{$id}_syn'>syn</label>
        <input type='checkbox' name='{$id}_dcera' value='x' $dcera /><label for='{$id}_dcera'>dcera</label>
        <br>
__EOF;
    }
  }
//    <input type="text" size="4" name='pin' value="{$post['zaslany_pin']}" disabled>
  $souhlas=  $vars['chk_souhlas'] ? 'checked' : '';
  $form= <<<__EOF
    <p>Na uvedený mail vám pošleme potvrzení o přijetí přihlášky:</p>
    <input type="text" size="24" name='email' value="{$post['email']}" disabled>
    <p>Doplňte prosím svoje bydliště a mobil:</p>
    $new_klient
    <p>Doplňte, koho ještě na akci přihlašujete:</p>
    $new_cleni
    <button type="submit" name="cmd_nove"><i class="fa fa-green fa-plus"></i>
      $dalsi</button>
    <p>Doplňte případnou poznámku pro organizátory akce:</p>
    <textarea rows="3" cols="46" name='note' placeholder='poznámka k pobytu na akci'>{$post['note']}</textarea> 
    <br>
    <input type='checkbox' name='chk_souhlas' value=''  $souhlas $mis_souhlas>
    <label for='chk_souhlas' class='souhlas'>$akce->form_souhlas</label>
    <br><br>
    <button type="submit" name="cmd_check"><i class="fa fa-question"></i>
      zkontrolovat před odesláním</button>
    <button type="submit" name="cmd_ano_novy"><i class="fa fa-green fa-send-o"></i> 
      odeslat přihlášku</button>
    <button type="submit" name="cmd_ne"><i class="fa fa-times fa-red"></i> 
      neposílat</button>
    <p class='upozorneni'>$msg</p>
__EOF;
end:
  do_end();
}
# ------------------------------------------------------------------------------------ do_rozlouceni
# (d) rozloučí se s klientem
#  IN: email, zaslany_pin, klient, rodina, cleni, novi
# CMD: konec
#   a - s vymazanými daty 
function do_rozlouceni() {
  global $msg, $akce, $vars, $post, $form;
  $ok= $msg;
  do_begin();
  if (substr($vars['history'],-2,1)=='d') {
    clear_post_but("/---/");
    $vars['faze']= 'a';
  }
  elseif ($ok=='ok') {
    $msg= "Vaše přihláška byla zaevidována a poslali jsme Vám potvrzující mail na {$post['email']}.";
    $mail_subj= "Potvrzení přihlášky na $akce->nazev.";
    $mail_body= "Dobrý den,<p>potvrzuji příjem Vaší přihlášky na akci <b>$akce->nazev</b>."
    . "<br>V týdnu před akcí dostanete <i>Dopis na cestu</i> s doplňujícími informacemi.</p>"
    . "<p>S přáním hezkého dne<br>$akce->garant_jmeno"
    . "<br><a href=mailto:'$akce->garant_mail'>$akce->garant_mail</a>"
    . "<br>$akce->garant_telefon</p>";
    $ok_mail= simple_mail('martin@smidek.eu', $post['email'], $mail_subj,$mail_body,$akce->garant_mail); 
    $ok= $ok_mail ? 'ok' : 'ko';
  }
  if ($ok=='ko') {
    $msg= "Při zpracování přihlášky došlo bohužel k chybě. "
        . "<br>Přihlaste se prosím posláním mailu vedoucímu akce"
        . "<br><a href=mailto:'$akce->garant_mail'>$akce->garant_mail</a>";
  }
  $form= <<<__EOF
    <p>$msg</p>
__EOF;
}
// ------------------------------------------------------------------------------- zobrazení stránky
function page($problem='') {
  global $vars, $akce, $form, $info, $index;
  global $TEST, $trace, $mailbox, $y, $errors;
  $icon= "akce.png";
  $stamp= form_stamp();
  if ($TEST) {
    if (count($errors)) $trace.= '<hr><span style="color:red">'.implode('<hr>',$errors).'</span>';
//    $trace.= '<hr>'.debugx($post,'$post');
//    $trace.= '<hr>'.debugx($_SESSION['klub'],'$_SESSION[akce] - výstup');
    $trace.= '<hr>'.nl2br($y->qry??'');
    $trace= "<section class='trace'>$trace</section>";
    $if_trace= "style='overflow:auto'";
  }
  else {
    $trace= '';
    $if_trace= '';
  }
  $formular= $problem ?: <<<__EOD
      $problem
      <div class='box'>
        <form action="$index" method="post">
          $form
          <input type="hidden" name='stamp' value="$stamp">
        </form>
      </div>
__EOD;
  $user= $vars['user'] ?: '... přihlaste se prosím svým mailem a zaslaným PINem';
  $info= $info=='' ? '' : <<<__EOD
      <div class='box'>
        <form action="$index" method="post">
          $info
          <input type="hidden" name='stamp' value="$stamp">
        </form>
      </div>
__EOD;
  $mailbox= $mailbox ? "<div class='box' style='border-left: 20px solid grey'>$mailbox</div>" : '';
//      <div id='head'><a href="https://www.tvnoe.cz"><i class="fa fa-home"></i>NOE - televize dobrých zpráv</a></div>
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
    <link rel="stylesheet" href="/less/akce.css" type="text/css" media="screen" charset='utf-8'>
    <link rel="stylesheet" href="/ezer3.2/client/licensed/font-awesome/css/font-awesome.min.css?" type="text/css" media="screen" charset="utf-8">
    <link rel="stylesheet" id="customify-google-font-css" href="//fonts.googleapis.com/css?family=Open+Sans%3A300%2C300i%2C400%2C400i%2C600%2C600i%2C700%2C700i%2C800%2C800i&amp;ver=0.3.5" type="text/css" media="all">
    <script>
        // Použijeme JavaScript pro přesměrování, abychom se vyhnuli problémům s cachováním
        if (window.history.replaceState) {
            window.history.replaceState(null, null, window.location.href);
        }
    </script>  </head>
  <body $if_trace>
    <div class="wrapper">
      <header>
        <div class="header">
          <a class="logo" href="https://www.setkani.org" target="web" title="" >
            <img src="/img/husy_ymca.png" alt=""></a>
          <div class="user">
            <i class="fa fa-user"></i> $user
          </div>
        </div>
        <div class="intro">Přihláška na akci <b>$akce->nazev</b></div>
      </header>
      <main>
        $mailbox
        $formular
        $info
      </main>
      <footer>
        <div class="footer">
          © YMCA Setkání
        </div>
      </footer>
    </div>
        $trace
  </body>
  </html>
__EOD;
}
// ------------------------------------------------------------------------------- zobrazení stránky
function page_old($problem='') {
  global $form, $akce, $index;
  global $TEST, $trace, $y, $errors;
  $icon= "akce.png";
  $stamp= form_stamp();
  if ($TEST) {
    if (count($errors)) $trace.= '<hr><span style="color:red">'.implode('<hr>',$errors).'</span>';
//    $trace.= '<hr>'.debugx($post,'$post');
//    $trace.= '<hr>'.debugx($_SESSION[$AKCE],'$_SESSION[akce] - výstup');
    $trace.= '<hr>'.nl2br($y->qry??'');
    $trace= "<div class='trace'>$trace</div>";
  }
  else $trace= '';
  $formular= $problem ?: <<<__EOD
      <h1>Přihláška na <b>$akce->nazev</b></h1>
      <div id='popis'>$akce->popis</div>
      $problem
      <div class='formular'>
        <form action="$index" method="post">
          $form
          <input type="hidden" name='stamp' value="$stamp">
        </form>
      </div>
      <div id='tail'>$akce->form_pata</div>
__EOD;
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
    <link rel="stylesheet" href="/less/akce.css" type="text/css" media="screen" charset='utf-8'>
    <link rel="stylesheet" href="/ezer3.2/client/licensed/font-awesome/css/font-awesome.min.css?" type="text/css" media="screen" charset="utf-8">
    <link rel="stylesheet" id="customify-google-font-css" href="//fonts.googleapis.com/css?family=Open+Sans%3A300%2C300i%2C400%2C400i%2C600%2C600i%2C700%2C700i%2C800%2C800i&amp;ver=0.3.5" type="text/css" media="all">
    <script>
        // Použijeme JavaScript pro přesměrování, abychom se vyhnuli problémům s cachováním
        if (window.history.replaceState) {
            window.history.replaceState(null, null, window.location.href);
        }
    </script>  </head>
  <body onload="">
    <div id='obal'>
      <div id='head'><a href="https://www.setkani.org"><i class="fa fa-home"></i> YMCA Setkání</a></div>
      $formular
    </div>
    $trace
  </body>
  </html>
__EOD;
}
# --------------------------------------------------------------------------------- správa proměných
function do_begin() {
  global $AKCE, $TEST, $vars, $novi, $cleni, $post;
  $_SESSION[$AKCE]['history'].= $_SESSION[$AKCE]['faze'];
  $vars= $_SESSION[$AKCE];
  $novi= $vars['novi'];
  $cleni= $vars['cleni'];
  $post= $vars['post'];
  // trace
  if ($TEST) {
    $bt= debug_backtrace();
    trace_vars("beg >>> {$bt[1]['function']}");
  }
}
function do_end() {
  global $AKCE, $TEST, $vars, $novi, $cleni, $post;
  // uloží vars 
  $vars['novi']= $novi;
  $vars['cleni']= $cleni;
  $vars['post']= $post;
  $_SESSION[$AKCE]= $vars;
  // trace
  if ($TEST) {
    $bt= debug_backtrace();
    trace_vars("end <<< {$bt[1]['function']}");
  }
}
function trace_vars($title) {
  global $TEST, $trace, $vars;
  if ($TEST) {
    $vars_dump= [];
    foreach (explode(',',"stamp,faze,history,klient,user,chk_souhlas,rodina,novi,cleni,post") as $v) {
      $vars_dump[$v]= $vars[$v] ?? '?';
    }
    $trace.= '<hr>'.debugx($vars_dump,$title,0,3);
  }
}
function clear_post_but($flds_match) {
  global $vars, $post;
  foreach (array_keys($post) as $fld) {
    if (!preg_match($flds_match,$fld)) {
      unset($post[$fld]);
    }
  }
  $vars['post']= $post;
}
# ----------------------------------------------------------------------------------- pomocné funkce
function do_session_restart() {
  global $AKCE;
//  session_unset();
//  session_destroy();
  unset($_SESSION[$AKCE]);
  session_write_close();
  session_start();
}
function form_stamp() {
  global $AKCE, $vars;
  $stamp= date("i:s");
  $_SESSION[$AKCE]['stamp']= $stamp;
  session_write_close();
  display("STAMP {$vars['faze']}: $stamp ... {$_SESSION[$AKCE]['stamp']}");
  return $stamp;
}
function zvyraznit($msg,$ok=0) {
  $color= $ok ? 'green' : 'red';
  return "<b style='color:$color'>$msg</b>";
}
# odeslání mailu
# $TEST>0 zabrání odeslání, zobrazí mail v trasování
function simple_mail($replyto,$address,$subject,$body,$cc='') {
  global $api_gmail_user, $api_gmail_pass, $api_gmail_name, $MAIL, $TEST, $trace, $mailbox;
  $msg= '';
  if ($TEST || !$MAIL) {
    $mailbox= "<h3>Simulace odeslání mailu z adresy $api_gmail_name &lt;$api_gmail_user&gt;</h3>"
        . "<b>pro:</b> $address "
        . "<br><b>předmět:</b> $subject"
        . "<p><b>text:</b> $body</p>";
    if ($TEST) $trace.= "<hr>$mailbox<hr>";
    $msg= 'ok'; // TEST bez odeslání
  }
  else {
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
//    if ($TEST) {
//      // pseudo dump 
//      $mail->SMTPDebug= 3;
//      $mail->Debugoutput= function($str, $level) { display("debug level $level; message: $str</div>");};
//      $pars= (object)array();
//      foreach (explode(',',"Mailer,Host,Port,SMTPAuth,SMTPSecure,Username,From,FromName,SMTPOptions") as $p) {
//        $pars->$p= $mail->$p;
//      }
//      debug($pars,"nastavení PHPMAILER");
//    }
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
# _ezer_qry = ezer_qry ALE
# $TEST>0 zapne trasování
# $TEST>1 nezapíše do db, vrací jako hodnotu 1
function _ezer_qry($op,$table,$cond_key,$chng) {
  global $TEST, $trace, $USER;
  $ok= 1;
  if ($TEST<2) {
    $USER->abbr= 'WEB';
    $ok= ezer_qry($op,$table,$cond_key,$chng);
  }
  if ($TEST) {
    $trace.= debugx($chng,"$op $table = $ok (test=$TEST)");
  }
  return $ok;
}
# přidání dítěte do rodiny a na akci
function nove_dite($pobyt,$rodina,$jmeno,$prijmeni,$narozeni,$syn,$dcera) {
  global $akce, $errors;
  $sex= $dcera ? 2 : 1;
  $role= $dcera || $syn ? 'd' : 'p';
  $narozeni= sql_date($narozeni,1);
  $chng= array(
    (object)array('fld'=>'jmeno',    'op'=>'i','val'=>$jmeno),
    (object)array('fld'=>'prijmeni', 'op'=>'i','val'=>$prijmeni),
    (object)array('fld'=>'sex',      'op'=>'i','val'=>$sex),
    (object)array('fld'=>'narozeni', 'op'=>'i','val'=>$narozeni),
    (object)array('fld'=>'access',   'op'=>'i','val'=>$akce->org)
  );
  $ido= _ezer_qry("INSERT",'osoba',0,$chng);
  if (!$ido) $errors[]= "Nastala chyba při zápisu do databáze (o)"; 
  // patří do rodiny
  if (!count($errors)) {
    $chng= array(
      (object)array('fld'=>'id_rodina', 'op'=>'i','val'=>$rodina),
      (object)array('fld'=>'id_osoba',  'op'=>'i','val'=>$ido),
      (object)array('fld'=>'role',      'op'=>'i','val'=>$role)
    );
    $idt= _ezer_qry("INSERT",'tvori',0,$chng);
    if (!$idt) $errors[]= "Nastala chyba při zápisu do databáze (t)"; 
  }
  // je na akci
  if (!count($errors)) {
    $chng= array(
      (object)array('fld'=>'id_pobyt',  'op'=>'i','val'=>$pobyt),
      (object)array('fld'=>'id_osoba',  'op'=>'i','val'=>$ido),
      (object)array('fld'=>'s_role',    'op'=>'i','val'=>2) // dítě
    );
    $ids= _ezer_qry("INSERT",'spolu',0,$chng);
    if (!$ids) $errors[]= "Nastala chyba při zápisu do databáze (s)"; 
  }
  return count($errors);
}

