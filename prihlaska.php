<?php
/**
# pilotní verze online přihlašování pro YMCA Setkání (jen obnovy a LK MS YS)
# debuger je lokálne nastaven pro verze PHP: 7.2.33 - musí být ručně spuštěn Chrome

# lze parametrizovat takto:
#
# p_pro_par     -- akce je určena manželským párům, výjimečně s dítětem a jeho pečovatelem
# p_obnova      -- pro obnovu: neúčastník aktuálního LK bude přihlášen jako náhradník
# p_registrace  -- je povoleno regsitrovat se s novým emailem
# p_souhlas     -- vyžaduje se souhlas se zpracováním uvedených osobních údajů
*/

header('Content-Type:text/html;charset=utf-8');
header('Cache-Control:no-cache,no-store,must-revalidate');
if (!isset($_GET['akce']) || !is_numeric($_GET['akce'])) die("Online přihlašování není k dospozici."); 
session_start();

$MAIL= 1; // 1 - maily se posílají | 0 - mail se jen ukáže - lze nastavit url&mail=0
$TEST= 0; // bez testování - lze nastavit url&test=n
$AKCE= "T_{$_GET['akce']}"; // ID akce pro SESSION
if (!isset($_SESSION[$AKCE])) $_SESSION[$AKCE]= (object)[];

// nastavení &test &mail se projeví jen z chráněných IP adres
if (ip_ok()) {
  $TEST= $_GET['test'] ?? ($_SESSION[$AKCE]->test ?? $TEST);
  $MAIL= $_GET['mail'] ?? ($_SESSION[$AKCE]->mail ?? $MAIL);

//$TEST= 0; // 0 = ostrý běh
//$TEST= 1; // 1 = ostrý běh + trasování 
//$TEST= 2; // 2 = simulace db + trasování 
$TEST= 3; // 3 = simulace db + trasování + přeskok loginu

if ($TEST==3) $testovaci_mail= 'martin@smidek.eu';
//if ($TEST==3) $testovaci_mail= 'martin.smidek@outlook.com';
//if ($TEST==3) $testovaci_mail= 'tholik@volny.cz';
//if ($TEST==3) $testovaci_mail= 'petr.glogar@seznam.cz';
}
# ------------------------------------------ zpracování jednoho formuláře
init();
parm($_GET['akce']);
$MAIL= 0; // 1 - maily se posílají | 0 - mail se jen ukáže - lze nastavit url&mail=0
todo();
page();
exit;
/** ------------------------------------------ stavové proměnné uložené v SESSION
# klient = ID přihlášeného 
# cleni = ID -> {spolu=0/1, jmeno, sex, role, narozeni}  ... ID může být  -<i> pro přidané členy
# ----------------------------------------------------------------------------- inicializace procesu
# inicializuje celý proces a provede potřebnou úpravu dat předaných přes POST
#   checkbox: chk_name ... pokud je v POST, tak nastaví chk_name na 1 
#             uplatní se v $post a $vars
#   submit:   cmd_name ... zruší cmd_x, která nejsou v aktuálním POST
#             uplatní se v $post */
function init() {
  global $AKCE, $vars, $mailbox, $cleni, $post, $msg;
  global $TEST, $MAIL, $errors;  
  # ------------------------------------------ napojení na Ezer
  global $ezer_server, $dbs, $db, $ezer_db, $USER, $kernel, $ezer_path_serv, $mysql_db_track, 
      $answer_db, $mysql_tracked, $trace, $totrace, $y; // $y je obecná stavová proměnná Ezer
  date_default_timezone_set('Europe/Prague');
  if ($TEST || isset($_GET['err']) && $_GET['err'] ) error_reporting(-1); else error_reporting(0);
  ini_set('display_errors', 'On');
  // prostředí Ezer
  $USER= (object)['abbr'=>'WEB'];
  $kernel= "ezer3.2";
  $ezer_path_serv= "$kernel/server";
  $deep_root= "../files/answer";
  require_once("$deep_root/dbt.dbs.php"); // <== testovací databáze
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
    $trace.= debugx($_POST,'$_POST - start');
  }
  $y= (object)[];
  // otevření databáze a redefine OBSOLETE
  if (isset($dbs[$ezer_server])) $dbs= $dbs[$ezer_server];
  if (isset($db[$ezer_server])) $db= $db[$ezer_server];
  $ezer_db= $dbs;
  ezer_connect($answer_db);
  # ------------------------------------------ ochrana proti refresh
  $trace= '';
  $mailbox= '';
  $refresh= '';
  $trace.= debugx($_POST,'$_POST - start');
  $stamp_sess= $_SESSION[$AKCE]->stamp??date("i:s");
  $stamp_post= $_POST['stamp']??'?';
  $trace.= debugx($_POST,'$_POST - start');
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
  if (!isset($_SESSION[$AKCE]->faze)) {
    $_POST= [];
    $_SESSION[$AKCE]->start= 1;
    $_SESSION[$AKCE]->faze= 'a';
    $_SESSION[$AKCE]->history= '';
    $_SESSION[$AKCE]->klient= 0;
    $_SESSION[$AKCE]->kontrola= 0;
    $_SESSION[$AKCE]->user= '';
    $_SESSION[$AKCE]->chk_souhlas= 0;
    $_SESSION[$AKCE]->pobyt= (object)[];
    $_SESSION[$AKCE]->rodina= [];
    $_SESSION[$AKCE]->cleni= [];
    $_SESSION[$AKCE]->post= $_POST;
    $index= "prihlaska_2.php"; 
    $_SESSION[$AKCE]->index= $index;
    $_SESSION[$AKCE]->server= $ezer_server;
    $_SESSION[$AKCE]->test= $TEST;
    $_SESSION[$AKCE]->mail= $MAIL;
  }
//  $trace.= debugx($_SESSION[$AKCE],'$_SESSION[akce] - start');
  $vars= (object)$_SESSION[$AKCE];
  $cleni= $vars->cleni;
  foreach ($_POST as $tag=>$val) {
    $m= null;
    if (preg_match("/([\-\d]+)_(.*)/",$tag,$m)) { // položka z $cleni
      $id= $m[1]; $name= $m[2];
      if (is_array($cleni[$id]->$name)) {
        if ($val!=$cleni[$id]->$name[0]) {
          $cleni[$id]->$name[1]= $val;
          $vars->kontrola= 0;
        }
      }
      elseif ($cleni[$id]->$name!=$val) {
        $cleni[$id]->$name= [$cleni[$id]->$name,$val];
      }
    }
    elseif (substr($tag,0,2)=='r_') { // položka z rodina
      $name= substr($tag,2);
      $rodina= $vars->rodina[key($vars->rodina)];
      if ($val!=$rodina->$name[0]) {
        $rodina->$name[1]= $val;
        $vars->kontrola= 0;
      }
    }
    elseif (substr($tag,0,2)=='p_') { // položka do pobyt
      $name= substr($tag,2);
      $vars->pobyt->$name= $val;
    }
  }
  // zpracování hodnot
  $old_post= array_merge([],(array)$vars->post);
  foreach (array_keys($old_post) as $fld) {
    if (preg_match("/^cmd_.*$/",$fld)) {
      unset($old_post[$fld]);
    }
  }
  $post= array_merge([],$_POST);
  $post= (object)array_replace($old_post,$post);
  // zpamatování vstupních hodnot typu checkbox
  $vars->chk_souhlas= isset($_POST['chk_souhlas']) ? 1 : 0;
  foreach ($cleni as $id=>$clen) {
    $name= "{$id}_spolu";
    $clen->spolu= isset($_POST[$name]) ? 1 : 0;    
//    $clen[$id]->$name= $val;
  }
  $vars->cleni= $cleni;
//  if ($TEST==2 && $vars->faze=='a') {
//    global $testovaci_mail;
//    $trace.= "<br>ladící běh se simulovaným přihlášením na $testovaci_mail";
//    $post= (object)['email'=>$testovaci_mail];
//  }
  if ($TEST==3 && $vars->faze=='a') {
    global $testovaci_mail;
    $trace.= "<br>ladící běh se simulovaným přihlášením na $testovaci_mail";
    $post= (object)['email'=>$testovaci_mail];
  }
  $vars->post= $post;
  $_SESSION[$AKCE]= $vars;
  $msg= '';
  display("TEST=$TEST MAIL=$MAIL");
}
# =============================================================================== parametrizace akce
# doplnění údajů pro přihlášku - případné doplnění $vars - definice položek
#   přidání údajů z Answeru: nazev, garant:*, form:pata
function parm($id_akce) {
  global $TEST, $trace, $akce, $vars, $options, $p_fld, $r_fld, $o_fld;
  $msg= '';
  // ------------------------------------------ definice položek
  $options= [
      'role'      => [''=>'role v rodině?','a'=>'manžel','b'=>'manželka','d'=>'dítě','p'=>'jiný vztah'],
      'cirkev'    => map_cis('ms_akce_cirkev','zkratka'),
      'vzdelani'  => map_cis('ms_akce_vzdelani','zkratka'),
    ];
  $p_fld= [ // položky tabulky POBYT
      'pracovni'  =>['64/4','sem prosím napište případnou dietu, nebo jinou úpravu stravy - poloviční porci, odhlášení jídla apod.','area']
    ];
  $r_fld= [ // položky tabulky RODINA
      'ulice'     =>[15,'* ulice nebo č.p.',''],
      'psc'       =>[ 5,'* PSČ',''],
      'obec'      =>[20,'* obec/město',''],
      'spz'       =>[12,'SPZ auta na akci',''],
      'datsvatba' =>[ 9,'* datum svatby','date'],
    ];
  $o_fld= [ // položky tabulky OSOBA
      'spolu'     =>[ 0,'na akci?','check_spolu'],
      'jmeno'     =>[ 7,'* jméno',''],
      'prijmeni'  =>[10,'* příjmení',''],
      'narozeni'  =>[ 9,'* narození','date'],
      'role'      =>[ 9,'* role v rodině?','select'],
      'obcanka'   =>[11,'číslo občanky',''],
      'telefon'   =>[10,'telefon',''],
      'vzdelani'  =>[20,'vzdělání','select'],
      'zamest'    =>[35,'povolání, zaměstnání',''],
      'zajmy'     =>[35,'zájmy',''],
      'jazyk'     =>[20,'znalost jazyků (A, N, ...)',''],
      'aktivita'  =>[35,'aktivita v církvi',''],
      'cirkev'    =>[20,'příslušnost k církvi','select'],
      'povaha'    =>[70,'popiš svoji povahu',''],
      'manzelstvi'=>['70/2','vyjádři se o vašem manželství','area'],
      'ocekavani' =>['70/2','co očekáváš od účasti na MS','area'],
      'rozveden'  =>[20,'bylo předchozí manželství?',''],
    ];
  // parametry přihlášky a ověření možnosti přihlášení
  list($ok,$web_online)= select("COUNT(*),web_online",'akce',"id_duakce=$id_akce");
  if (!$ok || !$web_online) { 
    $msg= "Na tuto akci se nelze přihlásit online"; goto end; }
  // dekódování web_online
  $akce= json_decode($web_online);
            debug($akce,"web_online");
  if (!$akce || !$akce->p_enable) { 
    $msg= "Na tuto akci se bohužel nelze přihlásit online"; goto end; }
  // doplnění dalších údajů o akci
  list($akce->org,$akce->nazev,$garant,$od)= // doplnění garanta
      select("access,nazev,poradatel,datum_od",'akce',"id_duakce=$id_akce");
  if ($od<=date('Y-m-d')) { 
    $msg= "Akce '$akce->nazev' již proběhla, nelze se na ni přihlásit"; goto end; }
  $MarketaZelinkova= 6849;
  list($ok,$akce->garant_jmeno,$akce->garant_telefon,$akce->garant_mail)= // doplnění garanta
      select("COUNT(*),CONCAT(jmeno,' ',prijmeni),telefon,email",
          "osoba LEFT JOIN _cis ON druh='akce_garant' AND data='$garant'",
          "id_osoba=IFNULL(ikona,$MarketaZelinkova)");
  // Přihlášku bohužel nelze použít - akce nemá definovaného garanta!
  list($akce->garant_mail)= preg_split("/[,;]/",str_replace(' ','',$akce->garant_mail));
  $akce->help_kontakt= "$akce->garant_jmeno <a href='mailto:$akce->garant_mail'>$akce->garant_mail</a>"; 
  $akce->form_pata= "Je možné, že se vám během vyplňování objeví nějaká chyba, "
    . " případně nedojde slíbené potvrzení. "
    . "<br><br>Přihlaste se prosím v takovém případě mailem zaslaným na $akce->help_kontakt."
    . "<br><br>Připojte prosím popis závady. Omlouváme se za nepříjemnost s beta-verzí přihlášek.";
  // doplnění konstant
  $akce->id_akce= $id_akce;
  $akce->form_souhlas= " Vyplněním této přihlášky dávám výslovný souhlas s použitím uvedených "
      . "osobních údajů pro potřeby organizace akcí YMCA Setkání v souladu s Nařízením "
      . "Evropského parlamentu a Rady (EU) 2016/679 ze dne 27. dubna 2016 o ochraně "
      . "fyzických osob a zákonem č. 101/2000 Sb. ČR. Na našem webu naleznete "
      . "<a href='https://www.setkani.org/ymca-setkani/5860#anchor5860' target='show'>"
      . "podrobnou informací o zpracování osobních údajů v YMCA Setkání</a>.";
  // doplnění $vars - jen poprvé
  if (!isset($vars->pro_par)) $vars->pro_par= $akce->p_pro_par;
end:    
  $trace.= debugx($akce,'hodnoty web_online');
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
  if ($vars->faze=='a') { // => a* | b
    clear_post_but("/email|^.$/");
    do_mail_klienta();  
  }  
  if ($vars->faze=='b') { // => b* | c | n | a
    do_kontrola_pinu(); 
  }
  if ($vars->faze=='c') { // => c* | d
    do_vyplneni_dat();  
  }
//  if ($vars->faze=='n') { // => n* | d
//    do_novy_klient();  
//  }
  if ($vars->faze=='d') { // => .
    do_rozlouceni();  
    // vyčisti vše
    unset($vars->post);
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
  if ($vars->faze=='a') { // => a* | b
    clear_post_but("/email|^.$/");
    do_mail_klienta();  
  }  
}
# ---------------------------------------------------------------------------------- do_mail_klienta
function do_mail_klienta() { // faze A
# (a) získá mail klienta, ověří jeho korektnost a evidenci v DB a pošle mu mail s PINem
#  IN: email
# CMD: zaslat
# OUT: email, zaslany_pin, klient, rodina, cleni
# CMD poslat_pin: 
#   a - chyby adresy 
#   b - poslán mail
  global $msg, $akce, $vars, $post, $form;
  global $TEST, $refresh;
  
  clear_post_but("/email|zaslany_pin|pin/");
  do_begin();
  
  $post->email= $post->email ?? '';                   
  $chyby= '';
  $ok= emailIsValid($post->email,$chyby);
  if (!$ok) 
    $chyby= $post->email ? "Tuto emailovou adresu není možné použít:<br>$chyby" : ' ';
  if (!$chyby) {
    if ($TEST==3) {
      // zkratka se simulací přihlášení (nesmí být už přihlášen)
      $pin= '----';
      $post->pin= $pin;
      $msg= 'ok';
    }
    else {
      // zašleme PIN 
      $pin= rand(1000,9999);
      $msg= simple_mail($akce->garant_mail, $post->email, "PIN ($pin) pro prihlášení na akci",
          "V přihlášce na akci napiš vedle svojí mailové adresy $pin a pokračuj tlačítkem [Ověřit PIN]");
      if ( $msg!='ok' ) {
        $chyby.= "Litujeme, mail s PINem se nepovedlo odeslat, přihlas se prosím na akci jiným způsobem."
            . "<br>($msg)";
      }
    }
    if ( $msg=='ok' ) {
      $msg= "Byl vám poslán mail";
      // doplníme hodnoty do $post 
      $post->zaslany_pin= $pin;
    }
    // jdeme dál
    $vars->faze= 'b';
  }
  if ($chyby) {
    $msg= zvyraznit("<p>$chyby</p>");
    $form= <<<__EOF
      $refresh
      <p>Abychom ověřili, že se přihlašujete právě vy, napište svůj mail, pošleme na něj přihlašovací PIN.</p>
      <input type="text" size="24" name='email' value='$post->email' placeholder='@'>
      <input type="hidden" name='pin' value=''>
      <input type="submit" name="cmd_zaslat" value="Zaslat PIN">
      $msg
__EOF;
  }
  do_end();
}
# --------------------------------------------------------------------------------- do_kontrola_pinu
function do_kontrola_pinu() { // fáze B
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
  global $akce, $msg, $vars, $post, $form;
  do_begin();
  // -------------------------------------------- jiný mail (a)
  if (isset($post->cmd_jiny_mail)) {
    clear_post_but("/---/");
    $vars->klient= 0;
    $vars->faze= 'a';
    do_end();
    return;
  }
  // -------------------------------------------- registrace (n)
  if (isset($post->cmd_registrace)) {
    $vars->faze= 'n';
    goto end;
  }
  // -------------------------------------------- ... kontrola pinu a údajů db
  clear_post_but("/email|zaslany_pin|pin/");

  $msg= '???';
  $pin= $post->pin ?? '';
  // ověříme PIN zapsaný u nositele mailové adresy a načteme údaje z db
  if ($pin && $pin==$post->zaslany_pin) {
    // zjistíme, zda jej máme v databázi
    $regexp= "REGEXP '(^|[;,\\\\s]+)$post->email($|[;,\\\\s]+)'";
    list($pocet,$ido,$idr,$jmena)= select(
        "COUNT(id_osoba),id_osoba,id_rodina,GROUP_CONCAT(CONCAT(jmeno,' ',prijmeni))",
        'osoba AS o JOIN tvori USING (id_osoba) JOIN rodina USING (id_rodina)',
        "o.deleted='' AND role IN ('a','b') "
        . "AND (kontakt=1 AND email $regexp OR kontakt=0 AND emaily $regexp)");
    // a jestli již není na akci přihlášen
    if ($pocet==0) {
      $msg= "Tento mail bohužel v evidenci YMCA Setkání nemáme,"
          . " pokud jste se již nějaké naší akce zúčastnili, "
          . "přihlaste se prosím pomocí mailu, který jste tehdy použil/a"
          . ($akce->p_registrace??0 ? " - pokud s námi budete poprvé, pokračujte." : '.');
      $vars->klient= -1;
    }
    elseif ($pocet>1) {
      $msg= "Tento mail používá více osob ($jmena), "
          . " <br>přihlaste se prosím pomocí jiného svého mailu (nebo mailem manžela/ky).";
      $vars->faze= 'a';
      goto end;
    }
    else { // pocet==1 ... mail je jednoznačný
      $vars->klient= $ido;
      $vars->rodina= [$idr=>(object)[]];
        // položky do hlavičky
      $vars->user= "$jmena<br>$post->email";

      // zjistíme zda již není přihlášen
      list($idp,$kdy,$kdo)= select("id_pobyt,IFNULL(kdy,''),IFNULL(kdo,'')",
          "pobyt JOIN spolu USING (id_pobyt) "
          . "LEFT JOIN _track ON klic=id_pobyt AND kde='pobyt' AND fld='id_akce' ",
          "(id_osoba={$vars->klient} OR i0_rodina=$idr) AND id_akce=$akce->id_akce "
          . "ORDER BY id_pobyt DESC LIMIT 1");
      display("a2: $idp,$kdy,$kdo");
      if ($idp) {
        $kdy= $kdy ? sql_time1($kdy) : '';
        $msg= $kdo=='WEB' ? "Na tuto akci jste se již $kdy přihlásili online přihláškou." : (
            $kdo ? "Na této akci jste již $kdy přihlášeni (zápis provedl uživatel se značkou $kdo" 
            : "Na této akci jste již $kdy přihlášeni.");
        $msg.= "<br><br>Přejeme vám příjemný pobyt :-)";
        $vars->faze= 'd';
        goto end;
      }
      else { // klientova rodina ani klient sám na akci není
        // pokud je povolená jejich úprava načti rodinnou adresu
        if ($akce->p_rod_adresa) {
          global $r_fld;
          $flds= implode(',',array_diff(array_keys($r_fld),[]));
          $r= select_object($flds,'rodina',"id_rodina=$idr");
          foreach ($r as $fld=>$val) {
            $vars->rodina[$idr]->$fld= [$val];
          }
        }
        // načti členy rodiny
        db_nacti_cleny_rodiny($idr,$ido);
        $msg= '';
        $vars->faze= 'c';
        $vars->pobyt->pracovni= '';
        goto end;
      }
    }
  }
  if ($msg) {
    if (($vars->klient??0)==-1) {
      $form= <<<__EOF
        <p>$msg</p>
        <input type="text" size="24" name='email' value="$post->email" disabled placeholder='@'>
        <input type='submit' name='cmd_jiny_mail' value='zkusím jiný mail'>
__EOF;
      $form.= $akce->p_registrace??0
          ? "<input type='submit' name='cmd_registrace' value='pokračovat s tímto mailem'>"
          : "<p>Případně požádejte o radu organizátory akce $akce->help_kontakt.</p>";
    }
    else {
      $msg= $pin ? zvyraznit("<p>Do mailu jsme poslali odlišný PIN</p>") : "<p></p>";
      $form= <<<__EOF
        <p>Na uvedený mail vám byl zaslán PIN, opište jej vedle své mailové adresy.</p>
        <input type="text" size="24" name='email' value="$post->email" disabled placeholder='@'>
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
function do_vyplneni_dat() {
# (c) získá data od klienta
#  IN: email, zaslany_pin, klient, rodina, cleni
# CMD: nove, ne, ano
# OUT: email, zaslany_pin, klient, rodina, cleni, msg
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
  global $akce, $msg, $vars, $cleni, $post, $form;
  global $errors;
  do_begin();
  $mis_souhlas= '';
  $post->pracovni= $post->pracovni ?? '';
  // -------------------------------------------- zobraz děti a pečouny
  if (isset($post->cmd_zobraz_deti)) {
    $vars->pro_par= 2;
  }
  // -------------------------------------------- nové dítě
  if (isset($post->cmd_dalsi_dite)) {
    clear_post_but("/email|zaslany_pin|pin|pracovni/");
    $id= 0;
    foreach (array_keys($cleni) as $is) {
      $id= min($id,$is);
    }
    $id--;
    $vars->cleni[$id]= $cleni[$id]= (object)array
        ('spolu'=>1,'jmeno'=>'','prijmeni'=>'','narozeni'=>'','role'=>'d');
    $vars->kontrola= 0;
  }
  // -------------------------------------------- nové pečovatel
  if (isset($post->cmd_dalsi_pecoun)) {
    clear_post_but("/email|zaslany_pin|pin|pracovni/");
    $id= 0;
    foreach (array_keys($cleni) as $is) {
      $id= min($id,$is);
    }
    $id--;
    $vars->cleni[$id]= $cleni[$id]= (object)array
        ('spolu'=>1,'jmeno'=>'','prijmeni'=>'','narozeni'=>'','role'=>'p', 'obcanka'=>'','telefon'=>'');
    $vars->kontrola= 0;
  }
  // -------------------------------------------- nepřihlašovat
  if (isset($post->cmd_ne)) {
    clear_post_but("/---/");
    $msg= "Vyplňování přihlášky bylo ukončeno bez jejího odeslání. "
        . "<br>Na akci jste se tedy nepřihlásili.";
    $vars->faze= 'd';
    goto end;
  }
  // -------------------------------------------- kontrola hodnot
  if (isset($post->cmd_ano) || isset($post->cmd_check)) {
    $zapsat= true;
    $neuplne= array();
    $doplnit= array();
    // ------------------------------ je aspoň jeden přihlášený?
    $spolu= 0;
    foreach ($cleni as $clen) {
      $spolu+= $clen->spolu;
    }
    if (!$spolu) {
      $neuplne[]= "Zaškrtněte prosím kdo se akce zúčastní";
      $zapsat= false;
    }      
    // ------------------------------ mají nově vyplnění všechny údaje?
    foreach ($cleni as $i=>$novy) {
      if ($i>0) continue;
      if (!$novy->spolu) {
        unset($cleni[$i]); continue;
      }
      if (!trim($novy->jmeno) || !trim($novy->prijmeni) || !trim($novy->narozeni)) 
        $neuplne[]= "vyplňte prosím u přidaných členů jméno, příjmení a datum narození";
      if (!$novy->role) 
        $neuplne[]= "doplňte prosím u přidaných členů jejich roli ve vaší rodině";
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
    if ($akce->p_souhlas && !$vars->chk_souhlas) {
      $neuplne[]= "potvrďte prosím svůj souhlas";
      $mis_souhlas= "class=missing";
    }
    if (count($neuplne)) 
      $msg.= zvyraznit(implode('<br>',$neuplne));
    elseif (isset($post->cmd_check)) {
      $msg.= zvyraznit("Přihláška vypadá dobře, zašlete nám ji prosím",1);
      if (count($doplnit))
        $msg.= zvyraznit(implode('<br>',$doplnit),1);
    }
    $vars->kontrola= count($neuplne) ? 0 : 1;
  }
  // -------------------------------------------- zápis do databáze pokud není $TEST>1
  $errors= [];
  if (isset($post->cmd_ano) && $zapsat) {
    // vytvoření pobytu
    // web_changes= 1/2 pro INSERT/UPDATE pobyt a spolu | 4/8 pro INSERT/UPDATE osoba
    // účast jako ¨účastník' pokud není p_obnova => neúčast na LK znamená "náhradník"
    $ucast= 0; // = účastník
    if ($akce->p_obnova) {
      $jako= byli_na_aktualnim_LK(key($vars->rodina));
      if (!$jako) $ucast= 9; // = náhradník
      elseif ($jako==2 && $akce->p_vps) $ucast= 1; // VPS
    }
    $idp= db_novy_pobyt($akce->id_akce,key($vars->rodina),$ucast,$vars->pobyt->pracovni);
    if (count($errors)) goto db_end;
    // ------------------------------ oprav rodinné údaje
    if ($akce->p_rod_adresa) {
      do_oprav_rodinu(key($vars->rodina));
      if (count($errors)) goto db_end;
    }
    // ------------------------------ vytvoř a přidej nové členy rodiny
    foreach ($cleni as $i=>$novy) {
      if ($i>0) continue;
      // přidání člena rodiny
      db_novy_clen_na_akci($idp,key($vars->rodina),$novy);
      if (count($errors)) goto db_end;
    }
    // ------------------------------ přidej staré členy rodiny a uprav údaje
    if (count($errors)) goto db_end;
    foreach ($cleni as $ido=>$clen) {
      if ($clen->spolu) {
        db_clen_na_akci($idp,$ido,$clen->role=='d' ? 2 : 1);
        if (count($errors)) goto db_end;
      }
    }
    // uzavři formulář závěrečnou zprávou
  db_end:
    clear_post_but("/email/");
    $msg= count($errors) ? 'ko' : 'ok';
    $vars->faze= 'd';
    goto end;
  }

  // zobraz hodnoty z databáze pokud není ani ANO ani NE
  $par_cleni= ''; 
  $old_cleni= ''; 
  $new_cleni= ''; 
  $pec_cleni= ''; 
  foreach ($cleni as $id=>$clen) {
    if ($id<0) {
      if (isset($clen->obcanka))
        $pec_cleni.= "<div class='clen'>" 
          . elem_input('o',$id,['spolu','jmeno','prijmeni','narozeni','role','obcanka','telefon'])
          . "</div>";
      else
        $new_cleni.= "<div class='clen'>" 
          . elem_input('o',$id,['spolu','jmeno','prijmeni','narozeni','role'])
          . "</div>";
    }
    else {
      $row= elem_input('o',$id,['spolu'])
          . elem_text('o',$id,['jmeno','prijmeni',',','narozeni',',','role']);
      if (in_array($clen->role,['a','b'])) {
        $par_cleni.= "<div class='clen'>$row" 
            . elem_input('o',$id,['obcanka','telefon','zamest','vzdelani','zajmy','jazyk',
                'aktivita','cirkev','povaha','manzelstvi','ocekavani','rozveden']) 
            . "</div>";
      }
      else {
        $old_cleni.= "<div class='clen'>$row</div>";
      }
    }
  }
  // -------------------------------------------- pokud je vyžadován souhlas
  $souhlas= $akce->p_souhlas
    ? "<input type='checkbox' name='chk_souhlas' value=''  "
      . ($vars->chk_souhlas ? 'checked' : '')
      . " $mis_souhlas><label for='chk_souhlas' class='souhlas'>$akce->form_souhlas</label>"
      . "<br><br>"
    : '';
  // -------------------------------------------- pokud je povolena úprava rodinné adresy
  $rod_adresa= '';
  if ($akce->p_rod_adresa) {
    $rod_adresa= "<p>Zkontrolujte a případně upravte vaši rodinnou adresu a další údaje:</p>";
    $idr= key($vars->rodina);
    $rod_adresa.= elem_input('r',$idr,['ulice','psc','obec','spz','datsvatba']);
  }
  $pobyt= elem_input('p',0,['pracovni']);
  $cmd_zobraz_deti= "<button type='submit' name='cmd_zobraz_deti'><i class='fa fa-green fa-plus'></i>
      po dohodě s organizátory přihlašuji i dítě s pečovatelem</button>";
  $cmd_dalsi_dite= "<button type='submit' name='cmd_dalsi_dite'><i class='fa fa-green fa-plus'></i>
      chci přihlásit další dítě</button>";
  $cmd_dalsi_pecoun= "<button type='submit' name='cmd_dalsi_pecoun'><i class='fa fa-green fa-plus'></i>
      chci přihlásit osobního pečovatele</button>";
  
  // -------------------------------------------- pro pár: volba varianty par1, par2 jinak celá rodina
  $form_cleni= '';
  switch ($vars->pro_par) {
    case 0: 
    case 2: 
      $form_cleni= "$par_cleni $old_cleni $new_cleni<br>$cmd_dalsi_dite $pec_cleni<br>$cmd_dalsi_pecoun"; break;
    case 1: 
      $form_cleni= "$par_cleni<br>$cmd_zobraz_deti"; break;
  }
  // -------------------------------------------- redakce formuláře
  $enable_send= $vars->kontrola ? '' : 'disabled';
  $enable_green= $vars->kontrola ? 'fa-green' : '';
  $form= <<<__EOF
    <p>Poznačte, koho na akci přihlašujete. Zkontrolujte a případně upravte zobrazené údaje.</p>
    $form_cleni
    <div class='rodina'>
      $rod_adresa
      $pobyt
    </div>
    $souhlas
    <br><br>
    <button type="submit" name="cmd_check"><i class="fa fa-question"></i>
      zkontrolovat před odesláním</button>
    <button type="submit" id="submit_form" name="cmd_ano" $enable_send><i class="fa $enable_green fa-send-o"></i> 
      odeslat přihlášku</button>
    <button type="submit" name="cmd_ne"><i class="fa fa-times fa-red"></i> 
      neposílat</button>
    <p>$msg</p>
__EOF;
end:
  do_end();
}
# ------------------------------------------------------------------------------------ do_rozlouceni
function do_rozlouceni() {
# (d) rozloučí se s klientem
#  IN: email, zaslany_pin, klient, rodina, cleni
# CMD: konec
#   a - s vymazanými daty 
  global $msg, $akce, $vars, $post, $form;
  $ok= $msg;
  do_begin();
  if (substr($vars->history,-2,1)=='d') {
    clear_post_but("/---/");
    $vars->faze= 'a';
  }
  elseif ($ok=='ok') {
    $ucastnici= ''; $del= '';
    foreach ($vars->cleni as $clen) {
      if (!$clen->spolu) continue;
      $ucastnici.= "$del$clen->jmeno $clen->prijmeni"; 
      $del= ', ';
    }
    $text= $akce->p_obnova && !byli_na_aktualnim_LK(key($vars->rodina))
      ? ".</p>"
        . "<p>Účast na obnově mají zajištěnu přednostně účastníci letního kurzu. "
        . "Protože jste mezi nimi nebyli, zařadili jsme vás zatím mezi náhradníky. "
        . "Pokud bude místo, ozveme se nejpozději 2 týdny před akcí a účast vám potvrdíme.</p>"
      : " a zapisuji vás mezi účastníky."
        . "<br>V týdnu před akcí dostanete <i>Dopis na cestu</i> s doplňujícími informacemi.</p>";
    $msg= "Vaše přihláška byla zaevidována a poslali jsme Vám potvrzující mail na $post->email.";
    $mail_subj= "Potvrzení přijetí přihlášky na akci $akce->nazev.";
    $mail_body= "Dobrý den,<p>potvrzuji přijetí vaší přihlášky na akci <b>$akce->nazev</b>"
    . " pro účastníky $ucastnici"
    . $text
    . "<p>S přáním hezkého dne<br>$akce->garant_jmeno"
    . "<br><a href=mailto:'$akce->garant_mail'>$akce->garant_mail</a>"
    . "<br>$akce->garant_telefon</p>";
    $ok_mail= simple_mail($akce->garant_mail, $post->email, $mail_subj,$mail_body,$akce->garant_mail); 
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
  global $TEST, $MAIL, $trace, $mailbox, $y, $errors;
  $icon= "akce_test.png";
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
  $user= $vars->user ?: '... přihlaste se prosím svým <br> mailem a zaslaným PINem';
  $info= $info=='' ? '' : <<<__EOD
      <div class='box'>
        <form action="$index" method="post">
          $info
          <input type="hidden" name='stamp' value="$stamp">
        </form>
      </div>
__EOD;
  // pokud dojde ke změně, zablokuj odeslání tj. vynuť novou kontrolu
  $function_check= <<<__EOF
    function check() {
      jQuery("input,select").change(function(){
        jQuery('#submit_form').prop("disabled",true);
      });
    }
__EOF;
  $warn= $TEST>1 ? ", bez zápisu" : '';
  $warn= $MAIL ? '' : "<div class='info'>simulace mailů$warn</div>";
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
    <link rel="shortcut icon" href="/db2/img/$icon" />
    <link rel="stylesheet" href="/less/akce_test.css?verze=2" type="text/css" media="screen" charset='utf-8'>
    <link rel="stylesheet" href="/ezer3.2/client/licensed/font-awesome/css/font-awesome.min.css?" type="text/css" media="screen" charset="utf-8">
    <link rel="stylesheet" id="customify-google-font-css" href="//fonts.googleapis.com/css?family=Open+Sans%3A300%2C300i%2C400%2C400i%2C600%2C600i%2C700%2C700i%2C800%2C800i&amp;ver=0.3.5" type="text/css" media="all">
    <script src="/ezer3.2/client/licensed/jquery-3.3.1.min.js" type="text/javascript" charset="utf-8"></script>
    <script>
        // Použijeme JavaScript pro přesměrování, abychom se vyhnuli problémům s cachováním
        if (window.history.replaceState) {
            window.history.replaceState(null, null, window.location.href);
        }
        $function_check
    </script>  </head>
  <body $if_trace onload='check();'>
    <div class="wrapper">
      <header>
        <div class="header">
          $warn
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
# ------------------------------------------------------------------------------- formulářové funkce
function elem_text($table,$id,$flds) {
# vytvoř část formuláře - jen text
  global $r_fld, $o_fld, $options, $vars;
  $html= '';
  $desc= $table=='r' ? $r_fld : $o_fld;
  $pair= $table=='r' ? $vars->rodina[$id] : $vars->cleni[$id];
  foreach ($flds as $fld) {
    if (!isset($desc[$fld])) {
      $html.= $fld;
      continue;
    }
    list(,,$typ)= $desc[$fld];
    $v= is_array($pair->$fld) ? ($pair->$fld[1] ?? $pair->$fld[0]) : $pair->$fld;
    switch ($typ) {
    case 'date':
      $v= substr($v,5,5)=='00-00' ? substr($v,0,4) : sql_date1($v,0);
      $html.= " $v";
      break;
    case 'select':
      $v= $options[$fld][$v] ?? '?';
      $html.= " $v";
      break;
    default:
      $html.= " $v";
    }
  }
  return "<span>$html</span>";
}
function elem_input($table,$id,$flds) { //trace();
# vytvoř část formuláře - pro vstup
  global $p_fld, $r_fld, $o_fld, $vars, $options;
  $html= '';
  $desc= $table=='r' ? $r_fld             : ($table=='o' ? $o_fld             : $p_fld);
  $pair= $table=='r' ? $vars->rodina[$id] : ($table=='o' ? $vars->cleni[$id]  : $vars->pobyt);
  $prfx= $table=='r' ? 'r_'               : ($table=='o' ? "{$id}_"           : 'p_');
  foreach ($flds as $fld) {
    if (!isset($desc[$fld])) {
      $html.= $fld;
      continue;
    }
    $name= "$prfx$fld";
    list($len,$title,$typ)= $desc[$fld];
    if ($typ=='x') continue;
//    $holder= str_replace('* ','',$title);
    $title=  str_replace('*',"<b style='color:red'>*</b>",$title);
    $v= is_array($pair->$fld) ? ($pair->$fld[1] ?? $pair->$fld[0]) : $pair->$fld;
    switch ($typ) {
    case 'check_spolu':
    case 'check':
      $x=  $v ? 'checked' : '';
      $html.= "<label class='$typ'>$title<input type='checkbox' name='$name' value='x' $x></label>";
      break;
    case 'date':
      $x= substr($v,5,5)=='00-00' ? substr($v,0,4) : sql_date1($v,0);
      $html.= "<label class='upper'>$title<input type='text' name='$name' size='$len' value='$x'></label>";
      break;
    case 'select':
//      $v= $options[$fld][$v] ?? '?';
      $html.= "<label class='upper'>$title<select name='$name'>";
      foreach ($options[$fld] as $vo=>$option) {
        if ($vo=='') {
          $html.= "<option disabled='disabled' selected='selected'>$option</option>";
          continue;
        }
        $selected= $v==$vo ? 'selected' : '';
        $html.= "\n  <option value='$vo' $selected>$option</option>";
      }
      $html.= "\n</select></label>";
      break;
    case 'area':
      list($cols,$rows)= explode('/',$len);
      $html.= "<label class='upper'>$title<textarea rows='$rows' cols='$cols' name='$name'>$v</textarea></label>";
      break;
    default:
      $x= $v ? "value='$v'" : ''; // "placeholder='$holder'";
      $html.= "<label class='upper'>$title<input type='text' name='$name' size='$len' $x></label>";
    }
  }
  return $html;
}
# -------------------------------------------------------------------------------- databázové funkce
function db_nacti_cleny_rodiny($idr,$prvni_ido) {
  global $akce, $cleni,$o_fld;
  $nodb= ['povaha','manzelstvi','ocekavani','rozveden'];
  $flds= implode(',',array_diff(array_keys($o_fld),$nodb,['spolu','telefon']));
  $ro= pdo_query(
    "SELECT id_osoba,$flds,IF(kontakt=1,telefon,'') AS telefon
    FROM osoba AS o JOIN tvori USING (id_osoba)
    WHERE id_rodina=$idr AND o.deleted='' AND role IN ('a','b','d') 
    ORDER BY IF(id_osoba=$prvni_ido,'0',narozeni)  ");
  while ($ro && ($c= pdo_fetch_object($ro))) {
    $c->spolu= $prvni_ido==$c->id_osoba ? 1 : 0;
    if ($akce->p_pro_par && in_array($c->role,['a','b'])) {
      $c->spolu= 1;
    }
    $cleni[$c->id_osoba]= $c;
    // doplň prázdné hodnoty 
    foreach ($nodb as $f) {
      $cleni[$c->id_osoba]->$f= '';
    }
  }
}
function db_novy_clen_na_akci($pobyt,$rodina,$novy) { 
# přidání dítěte do rodiny a na akci
  global $akce, $errors;
  $sex= select('sex','_jmena',"jmeno='$novy->jmeno' LIMIT 1");
  $sex= $sex==1 || $sex==2 ? $sex : 0;
  $narozeni= sql_date($novy->narozeni,1);
  $chng= array(
    (object)array('fld'=>'jmeno',    'op'=>'i','val'=>$novy->jmeno),
    (object)array('fld'=>'prijmeni', 'op'=>'i','val'=>$novy->prijmeni),
    (object)array('fld'=>'sex',      'op'=>'i','val'=>$sex),
    (object)array('fld'=>'narozeni', 'op'=>'i','val'=>$narozeni),
    (object)array('fld'=>'access',   'op'=>'i','val'=>$akce->org)
  );
  if ($novy->telefon??0) {
    $chng[]= (object)array('fld'=>'kontakt', 'op'=>'i','val'=>1);
    $chng[]= (object)array('fld'=>'telefon', 'op'=>'i','val'=>$novy->telefon);
  }
  if ($novy->obcanka??0) {
    $chng[]= (object)array('fld'=>'obcanka', 'op'=>'i','val'=>$novy->obcanka);
  }
  $ido= _ezer_qry("INSERT",'osoba',0,$chng);
  if (!$ido) $errors[]= "Nastala chyba při zápisu do databáze (o)"; 
  // patří do rodiny
  if (!count($errors)) {
    $chng= array(
      (object)array('fld'=>'id_rodina', 'op'=>'i','val'=>$rodina),
      (object)array('fld'=>'id_osoba',  'op'=>'i','val'=>$ido),
      (object)array('fld'=>'role',      'op'=>'i','val'=>$novy->role)
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
function db_clen_na_akci($idp,$ido,$s_role) {
  global $errors, $cleni;
  $chng= array(
    (object)['fld'=>'id_pobyt',  'op'=>'i','val'=>$idp],
    (object)['fld'=>'id_osoba',  'op'=>'i','val'=>$ido],
    (object)['fld'=>'s_role',    'op'=>'i','val'=>$s_role]
  );
  $ids= _ezer_qry("INSERT",'spolu',0,$chng);
  if (!$ids) $errors[]= "Nastala chyba při zápisu do databáze (cs)"; 
  // případná oprava oosbních údajů
  $chng= [];
  $clen= $cleni[$ido];
  foreach ((array)$clen as $f=>$vals) {
    if (is_array($vals) && isset($vals[1]) && $vals[1]!=$vals[0]) {
      if (in_array($f,['telefon','email','nomail']) && $clen->kontakt[0]==0) {
        $chng[]= (object)['fld'=>'kontakt', 'op'=>'u','old'=>0,'val'=>1];
        $chng[]= (object)['fld'=>$f, 'op'=>'u','val'=>$vals[1]];
      }
      else {
        $chng[]= (object)['fld'=>$f, 'op'=>'u','old'=>$vals[0],'val'=>$vals[1]];
      }
    }
  }
  if (count($chng)) {
    if (!_ezer_qry("UPDATE",'osoba',$ido,$chng)) 
      $errors[]= "Nastala chyba při zápisu do databáze (o)"; 
  }
}
function db_novy_pobyt($ida,$idr,$ucast,$pracovni) {
  global $errors;
  $chng= array(
    (object)array('fld'=>'id_akce',    'op'=>'i','val'=>$ida),
    (object)array('fld'=>'i0_rodina',  'op'=>'i','val'=>$idr),
    (object)array('fld'=>'funkce',     'op'=>'i','val'=>$ucast),
    (object)array('fld'=>'web_changes','op'=>'i','val'=>1),
    (object)array('fld'=>'web_zmena',  'op'=>'i','val'=>date('Y-m-d'))
  );
  if ($pracovni)
    $chng[]= (object)array('fld'=>'pracovni', 'op'=>'i','val'=>$pracovni);
  $idp= _ezer_qry("INSERT",'pobyt',0,$chng);
  if (!$idp) $errors[]= "Nastala chyba při zápisu do databáze (p)"; 
  return $idp;
}
function do_oprav_rodinu($idr) {
# oprav rodinné údaje 
  global $vars, $errors;
  $chng= [];
  $idr= key($vars->rodina);
  $rodina= $vars->rodina[$idr];
  foreach ($rodina as $f=>$vals) {
    if (is_array($vals) && isset($vals[1]) && $vals[1]!=$vals[0]) {
      $chng[]= (object)['fld'=>$f, 'op'=>'u','old'=>$vals[0],'val'=>$vals[1]];
    }
  }
  if (count($chng)) {
    if (!_ezer_qry("UPDATE",'rodina',$idr,$chng)) 
      $errors[]= "Nastala chyba při zápisu do databáze (r)"; 
  }
}
function byli_na_aktualnim_LK($rodina) {
# pro pobyt na obnově zjistí, zda rodina byla na jejím LK 
# ... 0 nebyla vůbec | 1 jako účastníci | 2 jako sloužící VPS
  global $akce;
  $obnova_mesic= select('MONTH(datum_od)','akce',"id_duakce=$akce->id_akce");
  $rok_LK= $obnova_mesic>7 ? date('Y') : date('Y')-1;
  $byli= select1('IFNULL(IF(funkce=1,2,1),0)','pobyt JOIN akce ON id_akce=id_duakce',
      "akce.druh=1 AND YEAR(akce.datum_od)=$rok_LK AND pobyt.i0_rodina='$rodina'");
  return $byli;
}
# --------------------------------------------------------------------------------- správa proměných
function do_begin() {
  global $AKCE, $TEST, $vars, $cleni, $post;
  $_SESSION[$AKCE]->history.= $_SESSION[$AKCE]->faze;
  $vars= $_SESSION[$AKCE];
  $cleni= $vars->cleni;
  $post= $vars->post;
  // trace
  if ($TEST) {
    $bt= debug_backtrace();
    trace_vars("beg >>> {$bt[1]['function']}");
  }
}
function do_end() {
  global $AKCE, $TEST, $vars, $cleni, $post;
  // uloží vars 
  $vars->cleni= $cleni;
  $vars->post= $post;
  $_SESSION[$AKCE]= $vars;
  // trace
  if ($TEST) {
    $bt= debug_backtrace();
    trace_vars("end <<< {$bt[1]['function']}");
  }
}
function do_session_restart() {
  global $AKCE;
//  session_unset();
//  session_destroy();
  unset($_SESSION[$AKCE]);
  session_write_close();
  session_start();
  $_SESSION[$AKCE]= (object)[];
}
function trace_vars($title) {
  global $TEST, $trace, $vars;
  if ($TEST) {
    $vars_dump= [];
    foreach (explode(',',"stamp,faze,history,kontrola,klient,user,chk_souhlas,pro_par,pobyt,rodina,cleni,post") as $v) {
      $vars_dump[$v]= $vars->$v ?? '?';
    }
    $trace.= '<hr>'.debugx($vars_dump,$title,0,4);
  }
}
function clear_post_but($flds_match) {
  global $vars, $post;
  foreach (array_keys((array)$post) as $fld) {
    if (!preg_match($flds_match,$fld)) {
      unset($post->$fld);
    }
  }
  $vars->post= $post;
}
# ----------------------------------------------------------------------------------- pomocné funkce
function ip_ok() {
# pozná localhost, IP Talichova, IP chata, LAN Noe
  $ip= $_SERVER['HTTP_X_REAL_IP'] ?? $_SERVER['REMOTE_ADDR'];
  return in_array($ip,['127.0.0.1','86.49.254.42','80.95.103.170','217.64.3.170']);
}
function form_stamp() {
  global $AKCE, $vars;
  $stamp= date("i:s");
  $_SESSION[$AKCE]->stamp= $stamp;
  session_write_close();
  display("STAMP {$vars->faze}: $stamp ... {$_SESSION[$AKCE]->stamp}");
  return $stamp;
}
function zvyraznit($msg,$ok=0) {
  $color= $ok ? 'green' : 'red';
  return "<b style='color:$color'>$msg</b>";
}
function simple_mail($replyto,$address,$subject,$body,$cc='') {
# odeslání mailu
# $MAIL=0 zabrání odeslání, jen zobrazí mail v trasování
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
function _ezer_qry($op,$table,$cond_key,$chng) {
# _ezer_qry = ezer_qry ALE
# $TEST>0 zapne trasování
# $TEST>1 nezapíše do db, vrací jako hodnotu 1
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

