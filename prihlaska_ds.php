<?php
# pilotní verze online přihlašování pro YMCA Setkání (jen typ pro DS)
# debuger je lokálne nastaven pro verze PHP: 7.2.33 - musí být ručně spuštěn Chrome
$TEST= 0;
$OPTIONS= ['akce'=>'test','err'=>3];

if (isset($OPTIONS['akce']) && (!isset($_GET['akce']) || $_GET['akce']!='ds')) 
  die("Online přihlašování na akce Domu setkání není k dospozici."); 

# $TEST=n   ... 1 pro ostrý běh s informacemi, maily se neodesílají, jen vypisují
#           ... 2 pro ladící běh se simulací úspěšného příhlášení, nezapisuje se do databáze

if (isset($_GET['test'])) 
  $TEST= $_GET['test'];
elseif (isset($_SESSION['akce']['test'])) 
  $TEST= $_SESSION['akce']['test'];
    
# ------------------------------------------ zpracování jednoho formuláře
init();
parm();
todo();
page();
exit;
# ------------------------------------------ stavové proměnné uložené v SESSION
# klient = ID přihlášeného 
# novi = počet uživatelem přidaných členů
# cleni = ID -> {spolu=0/1, jmeno, sex, role, narozeni}  ... ID může být  -<i> pro přidané členy
# ----------------------------------------------------------------------------- inicializace procesu
function init() {
  global $vars, $novi, $cleni, $post, $msg, $OPTIONS;
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
    $_SESSION['akce']['test']= $TEST;
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
  $refresh= '';
  session_start();
  $stamp_sess= $_SESSION['akce']['stamp']??date("i:s");
  $stamp_post= $_POST['stamp']??'?';
  display("SESSION: $stamp_sess POST: $stamp_post");
  if ($stamp_sess != $stamp_post) {
  //  $trace.= debugx($_POST,'$_POST - start');
  //  $trace.= debugx($_SESSION,'$_SESSION - start');
    session_unset();
    session_destroy();
    session_write_close();
    session_start();
    $_POST= [];
    $refresh= '<p><i>... refresh prohlížeče způsobil jeho inicializaci ...</i></p>';
  }
  # ------------------------------------------ start
  // nastavení nového=prázdného formuláře
  $errors= [];
//  $trace.= debugx($_SESSION,'$_SESSION - vstup');
  if (!isset($_SESSION['akce']['faze'])) {
    $_POST= [];
    $_SESSION['akce']['start']= 1;
    $_SESSION['akce']['faze']= 'a';
    $_SESSION['akce']['history']= '';
    $_SESSION['akce']['klient']= 0;
    $_SESSION['akce']['rodina']= 0;
    $_SESSION['akce']['novi']= array();
    $_SESSION['akce']['cleni']= array();
    $_SESSION['akce']['post']= $_POST;
    $index= "prihlaska.php"; $del_index= '?';
    foreach ($OPTIONS as $get=>$val) {
      $index.= "$del_index$get=$val";
      $del_index= '&';
    }
    $_SESSION['akce']['index']= $index;
    $_SESSION['akce']['server']= $ezer_server;
  }
//  $trace.= debugx($_SESSION['akce'],'$_SESSION[akce] - start');
  $vars= $_SESSION['akce'];
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
  // zpamatování vstupních hodnot
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
    $trace.= '<br>ladící běh se simulovaným přihlášením na martin@smidek.eu';
    $post= ['email'=>'martin@smidek.eu'];
//    $trace.= debugx($post,'$post - start');
  }
  $_SESSION['akce']['post']= $post;
  $msg= '';
}
# ------------------------------------------------------------------------------- parametrizace akce
# doplnění údajů pro přihlášku
function parm() {
  global $TEST, $parm;
  // parametry přihlášky - 
  $parm= [
    'akce:id_akce'  => 1554, 
    'akce:typ'      => 'DS', 
    'akce:nazev'    => 'akci v Domě setkání', 
    'akce:popis'    => "pro rodiče s dětmi. "
  ];
  list($parm['akce:org'],$parm['akce:nazev'],$garant)= // doplnění garanta
      select("access,nazev,poradatel",'akce',"id_duakce={$parm['akce:id_akce']}");
  list($ok,$parm['garant:jmeno'],$parm['garant:telefon'],$parm['garant:mail'])= // doplnění garanta
      select("COUNT(*),CONCAT(jmeno,' ',prijmeni),telefon,email",
          "osoba LEFT JOIN _cis ON druh='akce_garant' AND data='$garant'",
          "id_osoba=ikona");
  $parm['form:pata']= "<i>Je možné, že se vám během vyplňování objeví nějaká chyba, "
    . " případně nedojde slíbené potvrzení. "
    . "<br><br>Přihlaste se prosím v takovém případě mailem zaslaným na "
    . ($ok ? "vedoucího akce: <br>{$parm['garant:jmeno']} {$parm['garant:mail']}" : 'kancelar@setkani.org')
    . ".<br><br>Připojte prosím popis závady. Omlouváme se za nepříjemnost s beta-verzí přihlášek.</i>";
  if (!$ok) {
    $TEST= 0;
    page("<b style='color:red'><br>Přihlášku bohužel nelze použít - akce nemá definovaného garanta!</b>");
    exit;
  }
}
# --------------------------------------------------------------------------------- definice procesu
function todo() {
  global $TEST, $vars;
  // logika formulářů
  if ($vars['faze']=='a') { // => a* | b
    clear_post_but("/email|^.$/");
    do_mail_klienta();  
  }  
  if ($vars['faze']=='b') { // => b* | c
    do_kontrola_pinu(); 
  }
  if ($vars['faze']=='c') { // => c* | d
    do_vyplneni_dat();  
  }
  if ($vars['faze']=='d') { // => .
    do_rozlouceni();  
    // vyčisti vše
    unset($vars['post']);
    if ($TEST) {
      unset($_SESSION['akce']);
    }
    else {
      session_unset();
      session_destroy();
    }
    session_write_close();
//    init();
  }
}
# ---------------------------------------------------------------------------------- do_mail_klienta
# získá mail klienta, ověří jeho korektnost a evidenci v DB a pošle mu mail s PINem
#  IN: email
# CMD: zaslat
# OUT: email, zaslany_pin, klient, rodina, cleni
# MSG: *a=>chyby adresy | b=>poslán mail
function do_mail_klienta() { // faze A
  global $msg, $vars, $cleni, $post, $form;
  global $TEST, $refresh;
  
  clear_post_but("/email|zaslany_pin|pin/");
  load_vars(); // naplni $klient, $rodina, $novi, $cleni, $post
  trace_vars(' > do_mail_klienta');
  
  $post['email']= $post['email'] ?? '';                   
  $regexp= "REGEXP '(^|[;,\\\\s]+){$post['email']}($|[;,\\\\s]+)'";
//  if ($TEST<2) {
//    // při simulovaném přihlášení neruš připravené $post
//    clear_post_but("/email|^.$/");
//  }
  $chyby= '';
  $ok= emailIsValid($post['email'],$chyby);
  if (!$ok) 
    $chyby= $post['email'] ? "Tuto emailovou adresu není možné použít:<br>$chyby" : ' ';
  else {
    // zjistíme, zda jej máme v databázi
    list($pocet,$ido,$role,$idr,$jmena)= select(
        "COUNT(id_osoba),id_osoba,role,id_rodina,GROUP_CONCAT(CONCAT(jmeno,' ',prijmeni))",
        'osoba AS o JOIN tvori USING (id_osoba) JOIN rodina USING (id_rodina)',
        "o.deleted='' AND role IN ('a','b') "
        . "AND (kontakt=1 AND email $regexp OR kontakt=0 AND emaily $regexp)");
    // a jestli již není na akci přihlášen
    if ($pocet==0) {
      $chyby= "Tento mail bohužel v evidenci YMCA Setkání nemáme,"
          . " <br>přihlaste se prosím pomocí jiného svého mailu nebo mailem manžela/ky.";
    }
    elseif ($pocet>1) {
      $chyby= "Tento mail používá více osob ($jmena), "
          . " <br>přihlaste se prosím pomocí jiného svého mailu nebo mailem manžela/ky.";
    }
  }
  if (!$chyby) {
    $vars['klient']= $ido;
    $vars['rodina']= $idr;
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
      // NEW
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
    }
//    $chyby.= "STOP";
    // jdeme dál
    $vars['faze']= 'b';
  }
  if ($chyby) {
    $msg= zvyraznit("<p>$chyby</p>");
    $form= <<<__EOF
      $refresh
      <p>Abychom ověřili, že se přihlašujete právě vy, napište svůj mail, pošleme na něj přihlašovací PIN.</p>
      <input type="text" size="24" name='email' value='{$post['email']}'>
      <input type="hidden" name='pin' value=''>
      <input type="submit" name="cmd_zaslat" value="Zaslat PIN">
      $msg
__EOF;
  }
//  $trace.= debugx($_SESSION,'$_SESSION - stamp');
  save_vars(); // save $vars + $novi, $cleni, $post;
  trace_vars(' < do_mail_klienta');
}
# --------------------------------------------------------------------------------- do_kontrola_pinu
# ověří zapsaný PIN proti poslanému
#  IN: email, zaslany_pin
# CMD: overit
# OUT: email, zaslany_pin, klient, rodina, cleni
# MSG: *b=>jiný pin | c=> | d=>již přihlášen 
function do_kontrola_pinu() { // fáze B
  global $parm, $msg, $vars, $post, $form;
  
  clear_post_but("/email|zaslany_pin|pin/");
  load_vars(); // naplni $klient, $rodina, $novi, $cleni, $post
  trace_vars(' > do_kontrola_pinu');
  
  $pin= $post['pin'] ?? '';
  // zjistíme PIN zapsaný u nositele mailové adresy
  if ($pin && $pin==$post['zaslany_pin']) {
    // zjistíme zda již není přihlášen
    list($idp,$kdy,$kdo)= select("id_pobyt,IFNULL(kdy,''),IFNULL(kdo,'')",
        "pobyt JOIN spolu USING (id_pobyt) "
        . "LEFT JOIN _track ON klic=id_pobyt AND kde='pobyt' AND fld='id_akce' ",
        "(id_osoba={$vars['klient']} OR i0_rodina={$vars['rodina']}) AND id_akce={$parm['akce:id_akce']} "
        . "ORDER BY id_pobyt DESC LIMIT 1");
    display("a2: $idp,$kdy,$kdo");
    if ($idp) {
      $kdy= $kdy ? "od ".sql_time1($kdy) : '';
      $kdo= $kdo ? "pod značkou $kdo" : '';
      $msg= "Na této akci jste již $kdy přihlášeni $kdo."
          . "<br><br>Přejeme vám příjemný pobyt :-)";
      $vars['faze']= 'd';
    }
    else {
      $msg= '';
      $vars['faze']= 'c';
    }
  }
  else {
    $msg= $pin ? zvyraznit("<p>Do mailu jsme poslali odlišný PIN</p>") : "<p></p>";
    $form= <<<__EOF
      <p>Na uvedený mail vám byl zaslán PIN, opište jej vedle své mailové adresy.</p>
      <input type="text" size="24" name='email' value="{$post['email']}" disabled>
      <input type="text" size="4" name='pin' value="$pin">
      <input type="submit" name="cmd_overit" value="ověřit PIN">
      $msg
__EOF;
  }
  save_vars(); // save $vars + $novi, $cleni, $post;
  trace_vars(' < do_kontrola_pinu');
}
# ---------------------------------------------------------------------------------- do_vyplneni_dat
# získá data od klienta
#  IN: email, zaslany_pin, klient, rodina, cleni
# CMD: nove, ne, ano
# OUT: email, zaslany_pin, klient, rodina, cleni, novi, msg
# MSG: *c=>(|doplňte data|kontrola ok) | d=>ok|ko
function do_vyplneni_dat() {
  global $parm, $msg, $vars, $novi, $cleni, $post, $form;
  
  load_vars(); // naplni $klient, $rodina, $novi, $cleni, $post
  trace_vars(' > do_vyplneni_dat');
  
  $post['note']= $post['note'] ?? '';
  // -------------------------------------------- nové dítě
  if (isset($post['cmd_nove'])) {
    clear_post_but("/email|zaslany_pin|pin/");
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
      (object)array('fld'=>'id_akce',    'op'=>'i','val'=>$parm['akce:id_akce']),
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
        $sex= $novy->dcera ? 2 : 1;
        $role= $novy->dcera || $novy->syn ? 'd' : 'p';
        $narozeni= sql_date($novy->narozeni,1);
        $chng= array(
          (object)array('fld'=>'jmeno',    'op'=>'i','val'=>$novy->jmeno),
          (object)array('fld'=>'prijmeni', 'op'=>'i','val'=>$novy->prijmeni),
          (object)array('fld'=>'sex',      'op'=>'i','val'=>$sex),
          (object)array('fld'=>'narozeni', 'op'=>'i','val'=>$narozeni),
          (object)array('fld'=>'access',   'op'=>'i','val'=>$parm['akce:org'])
        );
        $ido= _ezer_qry("INSERT",'osoba',0,$chng);
        if (!$ido) $errors[]= "Nastala chyba při zápisu do databáze (o)"; 
        // patří do rodiny
        if (!count($errors)) {
          $chng= array(
            (object)array('fld'=>'id_rodina', 'op'=>'i','val'=>$vars['rodina']),
            (object)array('fld'=>'id_osoba',  'op'=>'i','val'=>$ido),
            (object)array('fld'=>'role',      'op'=>'i','val'=>$role)
          );
          $idt= _ezer_qry("INSERT",'tvori',0,$chng);
          if (!$idt) $errors[]= "Nastala chyba při zápisu do databáze (t)"; 
        }
        // je na akci
        if (!count($errors)) {
          $chng= array(
            (object)array('fld'=>'id_pobyt',  'op'=>'i','val'=>$idp),
            (object)array('fld'=>'id_osoba',  'op'=>'i','val'=>$ido),
            (object)array('fld'=>'s_role',    'op'=>'i','val'=>2) // dítě
          );
          $ids= _ezer_qry("INSERT",'spolu',0,$chng);
          if (!$ids) $errors[]= "Nastala chyba při zápisu do databáze (s)"; 
        }
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
    clear_post_but("/---/");
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

  $form= <<<__EOF
    <p>Na zde uvedený mail vám pošleme potvrzení o přijetí přihlášky:</p>
    <input type="text" size="24" name='email' value="{$post['email']}" disabled>
    <input type="text" size="4" name='pin' value="{$post['zaslany_pin']}" disabled>
    <p>Poznačte prosím, koho na akci přihlašujete:</p>
    $old_cleni
    $new_cleni
    <br><button type="submit" name="cmd_nove"><i class="fa fa-green fa-plus"></i>
      chci přihlásit další dítě</button>
    <p>Doplňte případnou poznámku pro organizátory akce:</p>
    <textarea rows="3" cols="46" name='note'>{$post['note']}</textarea> 
    <br><button type="submit" name="cmd_check"><i class="fa fa-question"></i>
      zkontrolovat před odesláním</button>
    <button type="submit" name="cmd_ano"><i class="fa fa-green fa-send-o"></i> 
      odeslat přihlášku</button>
    <button type="submit" name="cmd_ne"><i class="fa fa-times fa-red"></i> 
      neposílat</button>
    <p>$msg</p>
__EOF;
end:
  save_vars(); // save $vars + $novi, $cleni, $post;
  trace_vars(' < do_vyplneni_dat');
}
# ------------------------------------------------------------------------------------ do_rozlouceni
# rozloučí se s klientem
#  IN: email, zaslany_pin, klient, rodina, cleni, novi
# OUT: 
function do_rozlouceni() {
  global $msg, $parm, $vars, $post, $form;
  $ok= $msg;
  load_vars(); // naplni $klient, $rodina, $novi, $cleni, $post
  trace_vars(' > do_rozlouceni');
  if (substr($vars['history'],-2,1)=='d') {
    clear_post_but("/---/");
    $vars['faze']= 'a';
  }
  elseif ($ok=='ok') {
    $msg= "Vaše přihláška byla zaevidována a poslali jsme Vám potvrzující mail na {$post['email']}.";
    $mail_subj= "Potvrzení přihlášky na {$parm['akce:nazev']}.";
    $mail_body= "Dobrý den,<p>potvrzuji příjem Vaší přihlášky na akci <b>{$parm['akce:nazev']}</b>."
    . "<br>V týdnu před akcí dostanete <i>Dopis na cestu</i> s doplňujícími informacemi.</p>"
    . "<p>S přáním hezkého dne<br>{$parm['garant:jmeno']}"
    . "<br><a href=mailto:'{$parm['garant:mail']}'>{$parm['garant:mail']}</a>"
    . "<br>{$parm['garant:telefon']}</p>";
    $ok= simple_mail('martin@smidek.eu', $post['email'], $mail_subj,$mail_body,$parm['garant:mail']); 
  }
  if ($ok!='ok') {
    $msg= "Při zpracování přihlášky došlo bohužel k chybě. "
        . "<br>Přihlaste se prosím posláním mailu vedoucímu akce"
        . "<br><a href=mailto:'{$parm['garant:mail']}'>{$parm['garant:mail']}</a>";
  }
  $form= <<<__EOF
    <p>$msg</p>
__EOF;
}
// ------------------------------------------------------------------------------- zobrazení stránky
function page($problem='') {
  global $form, $parm, $index;
  global $TEST, $trace, $y, $errors;
  $icon= "akce.png";
  $stamp= form_stamp();
  if ($TEST) {
    if (count($errors)) $trace.= '<hr><span style="color:red">'.implode('<hr>',$errors).'</span>';
//    $trace.= '<hr>'.debugx($post,'$post');
//    $trace.= '<hr>'.debugx($_SESSION['akce'],'$_SESSION[akce] - výstup');
    $trace.= '<hr>'.nl2br($y->qry??'');
    $trace= "<div class='trace'>$trace</div>";
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
    <link rel="stylesheet" href="/less/akce.css" type="text/css" media="screen" charset="utf-8" />
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
      <h1>Přihláška na {$parm['akce:nazev']}</h1>
      <div id='popis'>{$parm['akce:popis']}</div>
      $problem
      <div class='formular'>
        <form action="$index" method="post">
          $form
          <input type="hidden" name='stamp' value="$stamp">
        </form>
      </div>
      <div id='tail'>{$parm['form:pata']}</div>
    </div>
    $trace
  </body>
  </html>
__EOD;
}
# --------------------------------------------------------------------------------- správa proměných
function trace_vars($title) {
  global $TEST, $trace, $vars;
  if ($TEST) {
    $vars_dump= [];
    foreach (explode(',',"stamp,faze,history,klient,rodina,novi,cleni,post") as $v) {
      $vars_dump[$v]= $vars[$v] ?? '?';
    }
    $trace.= '<hr>'.debugx($vars_dump,$title,0,1);
  }
}
function load_vars() {
  global $vars, $novi, $cleni, $post;
  $vars= $_SESSION['akce'];
  $novi= $vars['novi'];
  $cleni= $vars['cleni'];
  $post= $vars['post'];
}
function save_vars() {
  global $vars, $novi, $cleni, $post;
  // uloží vars 
  $vars['novi']= $novi;
  $vars['cleni']= $cleni;
  $vars['post']= $post;
  $_SESSION['akce']= $vars;
}
function clear_post_but($flds_match) {
  global $vars;
  foreach (array_keys($vars['post']) as $fld) {
    if (!preg_match($flds_match,$fld)) {
      unset($vars['post'][$fld]);
    }
  }
}
# ----------------------------------------------------------------------------------- pomocné funkce
function form_stamp() {
  global $vars;
  $stamp= date("i:s");
//  unset($_SESSION['akce']['stamp']);
//  session_write_close();
//  session_start();
  $_SESSION['akce']['stamp']= $stamp;
//  session_regenerate_id();
  session_write_close();
//  session_start();
  display("STAMP {$vars['faze']}: $stamp ... {$_SESSION['akce']['stamp']}");
  return $stamp;
}
function zvyraznit($msg,$ok=0) {
  $color= $ok ? 'green' : 'red';
  return "<b style='color:$color'>$msg</b>";
}
# odeslání mailu
# $TEST>0 zabrání odeslání, zobrazí mail v trasování
function simple_mail($replyto,$address,$subject,$body,$cc='') {
  global $api_gmail_user, $api_gmail_pass, $api_gmail_name, $TEST, $trace;
  $msg= '';
  if ($TEST) {
    $trace.= "<hr>MAIL: addr=$address, cc=$cc<br>subj=$subject<br>$body<hr>";
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
