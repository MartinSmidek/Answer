<?php
/**
  (c) 2024 Martin Smidek <martin@smidek.eu>
   
  pilotní verze online přihlašování pro YMCA Setkání (jen obnovy a LK MS YS)
  debuger je lokálne nastaven pro verze PHP: 7.2.33 - musí být ručně spuštěn Chrome

  lze parametrizovat takto:
     p_registrace  -- je povoleno registrovat se s novým emailem
    p_pro_par     -- obecně manželský pár, výjimečně s dítětem, případně pečovatelem
    p_rod_adresa  -- ... umožňuje se úprava rodinných údajů (adresa, SPZ, datum svatby)
    p_obcanky     -- ... umožňuje se úprava osobních údajů (občanka, telefon)
    p_souhlas     -- vyžaduje se společný souhlas se zpracováním uvedených osobních údajů
 jen pro obnovy MS
    p_obnova      -- pro obnovu: neúčastník aktuálního LK bude přihlášen jako náhradník
    p_vps         -- nastavit funkci VPS podle letního kurzu
 jen pro LK MS
    p_pro_LK      -- pro manželský pár i s dětmi, případně pečovatelem, s citlivými údaji
    p_dokument    -- vytvořit PDF a uložit jako dokument k pobytu
    p_upozorneni  -- vyžaduje se osobní souhlas s uvedenými podmínkami účasti na LK
*/
header('Content-Type:text/html;charset=utf-8');
header('Cache-Control:no-cache,no-store,must-revalidate');
if (!isset($_GET['akce']) || !is_numeric($_GET['akce'])) die("Online přihlašování není k dospozici."); 
session_start();
$MAIL= 1; // 1 - maily se posílají | 0 - mail se jen ukáže - lze nastavit url&mail=0
$TEST= 0; // 0 - bez testování | 1 - výpis stavu a sql | 2 - neukládat | 3 - login s testovacím mailem
$CORR= 1; // 1 - načte již uloženou přihlášku a umožní opravu
$AKCE= "T_{$_GET['akce']}"; // ID akce pro SESSION
if (!isset($_SESSION[$AKCE])) $_SESSION[$AKCE]= (object)[];
// -------------------------------------- nastavení &test &mail se projeví jen z chráněných IP adres
if (!ip_ok()) {
  die("Online přihlašování není ještě k dospozici."); 
}
// -------------------------------------------------------------------------- varianty pro testování
//$testovaci_mail= 'martin@smidek.eu';          $TEST= 3; // známý pár
//$testovaci_mail= 'anabasis@seznam.cz';        $TEST= 3; // známá rodina ale bez ženy
//$testovaci_mail= 'frantisekbezdek@atlas.cz';  $TEST= 3; // známá osoba ale bez rodiny
//$testovaci_mail= 'kancelar@setkani.org';      $TEST= 3; 
$testovaci_mail= 'nemo2@smidek.eu';         $TEST= 3; // neznámý mail
//$TEST= 2;
if (!isset($testovaci_mail)) {
  $TEST= $_GET['test'] ?? ($_SESSION[$AKCE]->test ?? $TEST);
  $MAIL= $_GET['mail'] ?? ($_SESSION[$AKCE]->mail ?? $MAIL);
}
# --------------------------------------------------------------- zpracování jednoho stavu formuláře
start();                // nastavení $vars podle SESSION
connect_db();           // napojení na databázi a na Ezer 
debug($_POST,"\$_POST na startu");
read_akce();            // načtení údajů o akci z Answeru včetně popisu získávaných položek
//debug($akce);
read_form();            // načtení údajů formuáře
trace_vars('START');
//$MAIL= 0; // 1 - maily se posílají | 0 - mail se jen ukáže - lze nastavit url&mail=0
todo();
trace_vars('END');
page();
exit;
/** ------------------------------------------ stavové proměnné uložené v SESSION
# klient = ID přihlášeného 
# cleni = ID -> {spolu=0/1, jmeno, sex, role, narozeni}  ... ID může být  -<i> pro přidané členy
# v cleni, rodina, pobyt jsou v čitelném tvaru vč. datumu, hodnotou select však jsou klíče
# ----------------------------------------------------------------------------- inicializace procesu
# inicializuje celý proces a provede potřebnou úpravu dat předaných přes POST
#   checkbox: chk_name ... pokud je v POST, tak nastaví chk_name na 1 
#             uplatní se v $post a $vars
#   submit:   cmd_name ... zruší cmd_x, která nejsou v aktuálním POST
#             uplatní se v $post */
# --------------------------------------------------------------------------------- napojení na Ezer
# doplnění údajů pro přihlášku - případné doplnění $vars - definice položek
#   přidání údajů z Answeru: nazev, garant:*, form:pata
function start() { // ------------------------------------------------------------------------ start
  global $errors, $refresh, $mailbox, $vars, $TEST, $MAIL, $AKCE;
  global $ezer_server, $trace;
    # ------------------------------------------ start
  // nastavení nového=prázdného formuláře
  $errors= [];
  $trace= '';
  $mailbox= '';
    # ------------------------------------------ ochrana proti znovunačtení formuláře
  $refresh= '';
//  $trace.= debugx($_POST,'$_POST - start');
  $stamp_sess= $_SESSION[$AKCE]->stamp??date("i:s");
  $stamp_post= $_POST['stamp']??'?';
//  $trace.= debugx($_POST,'$_POST - start');
//  display("SESSION: $stamp_sess POST: $stamp_post");
  if ($stamp_sess != $stamp_post) {
//    $trace.= debugx($_POST,'$_POST - start');
  //  $trace.= debugx($_SESSION,'$_SESSION - start');
    do_session_restart();
    $_POST= [];
    $refresh= '<p><i>... refresh prohlížeče způsobil jeho inicializaci ...</i></p>';
  }
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
    $_SESSION[$AKCE]->form= 0;
    $_SESSION[$AKCE]->pobyt= (object)[];
    $_SESSION[$AKCE]->rodina= [];
    $_SESSION[$AKCE]->cleni= [];
    $_SESSION[$AKCE]->post= $_POST;
    $index= "prihlaska.php";                      // <== index
    $_SESSION[$AKCE]->index= $index;
    $_SESSION[$AKCE]->server= $ezer_server;
    $_SESSION[$AKCE]->test= $TEST;
    $_SESSION[$AKCE]->mail= $MAIL;
  }
//  $trace.= debugx($_SESSION[$AKCE],'$_SESSION[akce] - start');
  $vars= (object)$_SESSION[$AKCE];
}
function connect_db() { // -------------------------------------------------------------- connect db
 global $ezer_server, $dbs, $db, $ezer_db, $USER, $kernel, $ezer_path_serv, $mysql_db_track, 
     $ezer_path_root, $abs_root, $answer_db, $mysql_tracked, $totrace, 
     $y; // $y je obecná stavová proměnná Ezer
  global $TEST;
  date_default_timezone_set('Europe/Prague');
  if ($TEST || isset($_GET['err']) && $_GET['err'] ) error_reporting(-1); else error_reporting(0);
  ini_set('display_errors', 'On');
  // prostředí Ezer
  $USER= (object)['abbr'=>'WEB'];
  $kernel= "ezer3.2";
  $ezer_path_serv= "$kernel/server";
  $deep_root= "../files/answer";
  require_once("$deep_root/dbt.dbs.php"); // <== testovací databáze a cesty např. $path_files_h
  $ezer_path_root= $abs_root;
  require_once("$kernel/server/ae_slib.php");
  require_once("$kernel/pdo.inc.php");
  require_once("$kernel/server/ezer_pdo.php");
  require_once("db2/db2_fce3.php");
  // PDF knihovny
  require_once('tcpdf/tcpdf.php');
  require_once('tcpdf/db2_tcpdf.php');
  // definice zápisů do _track
  $mysql_db_track= true;
  $mysql_tracked= ',akce,pobyt,spolu,osoba,tvori,rodina,_user,';
  # trasování 
  if ($TEST) {
    $totrace= 'Mu';
//    global $trace;
//    $trace.= debugx($_POST,'$_POST - start');
  }
  $y= (object)[];
  // otevření databáze a redefine OBSOLETE
  if (isset($dbs[$ezer_server])) $dbs= $dbs[$ezer_server];
  if (isset($db[$ezer_server])) $db= $db[$ezer_server];
  $ezer_db= $dbs;
  ezer_connect($answer_db);
}
function read_akce() { // ---------------------------------------------------------------- read akce
  global $TEST, $akce, $options, $p_fld, $r_fld, $o_fld;
  $msg= '';
  $id_akce= $_GET['akce'];
  // parametry přihlášky a ověření možnosti přihlášení
  list($ok,$web_online)= select("COUNT(*),web_online",'akce',"id_duakce=$id_akce");
  if (!$ok || !$web_online) { 
    $msg= "Na tuto akci se nelze přihlásit online"; goto end; }
  // dekódování web_online
  $akce= json_decode($web_online);
//            debug($akce,"web_online");
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
  $akce->form_pata= "Je možné, že se vám během vyplňování objeví nějaká chyba, 
      případně nedojde slíbené potvrzení. 
      <br><br>Přihlaste se prosím v takovém případě mailem zaslaným na $akce->help_kontakt.
      <br><br>Připojte prosím popis závady. Omlouváme se za nepříjemnost s beta-verzí přihlášek.";
  // doplnění konstant
  $akce->id_akce= $id_akce;
  $akce->preambule= "Tyto údaje slouží pouze pro vnitřní potřebu organizátorů kurzu MS, 
      nejsou poskytovány cizím osobám ani institucím. Pro vaši spokojenost během kurzu je 
      nezbytné, abyste dotazník pečlivě a pravdivě vyplnili.";
  $akce->form_souhlas= " Vyplněním této přihlášky dáváme výslovný souhlas s použitím uvedených 
      osobních údajů pro potřeby organizace akcí YMCA Setkání v souladu s Nařízením 
      Evropského parlamentu a Rady (EU) 2016/679 ze dne 27. dubna 2016 o ochraně 
      fyzických osob a zákonem č. 101/2000 Sb. ČR. Na našem webu naleznete 
      <a href='https://www.setkani.org/ymca-setkani/5860#anchor5860' target='show'>
      podrobnou informací o zpracování osobních údajů v YMCA Setkání</a>.";
  $akce->upozorneni= "Potvrzuji, že jsem byl@ upozorněn@, že není možné se účastnit pouze části kurzu, 
      že kurz není určen osobám závislým na alkoholu, drogách nebo jiných omamných látkách, ani
      osobám zatíženým neukončenou nevěrou, těžkou duševní nemocí či jiným omezením, která neumožňují 
      zapojit se plně do programu. Osoby duševně nemocné se mohou zúčastnit kurzu pouze se souhlasem 
      svého ošetřujícího lékaře nebo psychoterapeuta, v případě nejasností po konzultaci s vedením kurzu. 
      Zatržením prohlašuji, že jsem si plně vědom@, že pořadatel neodpovídá za škody a újmy, které by 
      mně/nám mohly vzniknout v souvislosti s nedodržením těchto zásad účasti na kurzu a veškerá rizika
      v takovém případě přebíráme na sebe.";
  // ------------------------------------------ definice položek
  $options= [
      'role'      => [''=>'vztah k rodině?','a'=>'manžel','b'=>'manželka','d'=>'dítě','p'=>'jiný vztah'],
//      'cirkev'    => map_cis('ms_akce_cirkev','zkratka'),
      'cirkev'    => [''=>'něco prosím vyberte',1=>'katolická',2=>'evangelická',16=>'nevěřící',21=>'hledající',23=>'křesťan'],
      'vzdelani'  => map_cis('ms_akce_vzdelani','zkratka'),
      'funkce'    => map_cis('ms_akce_funkce','zkratka'),
    ];
  $options['vzdelani']['']= 'něco prosím vyberte';
  // definice obsahuje:  položka => [ délka , popis , formát ]
  //   X => pokud jméno položky začíná X, nebude se ukládat, jen zapisovat do PDF
  //   * => pokud popis začíná hvězdičkou bude se údaj vyžadovat (hvězdička za zobrazí červeně)
  //        je to ale nutné pro každou položku naprogramovat 
  $p_fld= [ // zobrazené položky tabulky POBYT, nezobrazené: id_pobyt, web_changes
      'pracovni'  =>['64/4','sem prosím napište případnou dietu, nebo jinou úpravu stravy '
          . '- poloviční porci, odhlášení jídla apod.','area'],
      'funkce'    =>[0,'funkce na akci','select'],
    ];
  $r_fld= [ // položky tabulky RODINA
      'nazev'     =>[15,'jméno Vaší rodiny',''],
      'ulice'     =>[15,'* ulice nebo č.p.',''],
      'psc'       =>[ 5,'* PSČ',''],
      'obec'      =>[20,'* obec/město',''],
      'spz'       =>[12,'SPZ auta na akci',''],
      'datsvatba' =>[ 9,'* datum svatby','date'],
    ];
  $o_fld= array_merge(
    [ // položky tabulky OSOBA
      'spolu'     =>[ 0,'na akci?','check_spolu','abdp'],
      'jmeno'     =>[ 7,'* jméno','','abdp'],
      'prijmeni'  =>[10,'* příjmení','','abdp'],
      'rodne'     =>[10,'rozená','','b'],
      'narozeni'  =>[10,'* datum narození','date','abdp'],
      'role'      =>[ 9,'vztah k rodině?','select','abdp'],
      'note'      =>[40,'poznámka (léky, alergie, apod.)','','d']],
    $akce->p_obcanky ? [
      'obcanka'   =>[11,'* číslo OP nebo pasu','','abp'],
      'telefon'   =>[15,'* telefon','','abp'],
      'email'     =>[35,'* e-mailová adresa','','ab']] : [],
    $akce->p_pro_LK ? [
      'vzdelani'  =>[20,'* vzdělání','select','ab'],
      'zamest'    =>[35,'* povolání, zaměstnání','','ab'],
      'zajmy'     =>[35,'* zájmy','','ab'],
      'jazyk'     =>[20,'znalost jazyků (Aj, Nj, ...)','','ab'],
      'aktivita'  =>[35,'aktivita v církvi','','ab'],
      'cirkev'    =>[25,'* vztah ke křesťanství','select','ab'],
//      'cirkev'    =>[20,'* příslušnost k církvi','select','ab'],
      'Xpecuje_o' =>[12,'* bude pečovat o ...','','p'],
      'Xpovaha'    =>['70/1','* popiš svoji povahu','area','ab'],
      'Xmanzelstvi'=>['70/2','* vyjádři se o vašem manželství','area','ab'],
      'Xocekavani' =>['70/2','* co očekáváš od účasti na MS','area','ab'],
      'Xrozveden'  =>[20,'* bylo předchozí manželství?','','ab'],
      'Xupozorneni'=>[ 0,$akce->upozorneni,'check','ab'],
    ] : []
  );
end:    
//  global $trace;
//  $trace.= debugx($akce,'hodnoty web_online');
  if ($msg) {
    $TEST= 0;
    page("<b style='color:red'><br>$msg</b>");
    exit;
  }
} // definice položek
function read_form() { // ---------------------------------------------------------------- read form
  global $AKCE, $vars, $cleni, $post, $msg;
  global $TEST, $MAIL, $trace;  
  $cleni= $vars->cleni;
  foreach ($_POST as $tag=>$val) {
    $m= null;
    if (preg_match("/([\-\d]+)_(.*)/",$tag,$m)) { // položka z $cleni
      $id= $m[1]; $fld= $m[2];
      if (is_array($cleni[$id]->$fld)) {
        if ($val!=$cleni[$id]->$fld[0]) {
          $cleni[$id]->$fld[1]= $val;
          $vars->kontrola= 0;
        }
      }
      elseif ($cleni[$id]->$fld!=$val) {
        $cleni[$id]->$fld= [$cleni[$id]->$fld,$val];
      }
    }
    elseif (substr($tag,0,2)=='r_') { // položka z rodina
      $fld= substr($tag,2);
      $rodina= $vars->rodina[key($vars->rodina)];
      if (is_array($rodina->$fld)) {
        if ($val!=$rodina->$fld[0]) {
          $rodina->$fld[1]= $val;
          $vars->kontrola= 0;
        }
      }
      else {
        $rodina->$fld= [$rodina->$fld,$val];
        $vars->kontrola= 0;
      }
    }
    elseif (substr($tag,0,2)=='p_') { // položka do pobyt
      $fld= substr($tag,2);
      $pobyt= $vars->pobyt;
      if (is_array($pobyt->$fld)) {
        if ($val!=$pobyt->$fld[0]) {
          $pobyt->$fld[1]= $val;
          $vars->kontrola= 0;
        }
      }
      else {
        $pobyt->$fld= [$pobyt->$fld,$val];
        $vars->kontrola= 0;
      }
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
    $name= "{$id}_Xupozorneni";
    $clen->Xupozorneni= isset($_POST[$name]) ? 1 : 0;    
//    $clen[$id]->$name= $val;
  }
  $vars->cleni= $cleni;
  if ($TEST==3 && $vars->faze=='a') {
    global $testovaci_mail;
    $trace.= "<br>ladící běh se simulovaným přihlášením na $testovaci_mail";
    $post= (object)['email'=>$testovaci_mail];
  }
//   doplnění $vars - jen poprvé
//  if (!isset($vars->pro_par)) $vars->pro_par= $akce->p_pro_par;
  $vars->post= $post;
  $_SESSION[$AKCE]= $vars;
  $msg= '';
  display("TEST=$TEST MAIL=$MAIL");
} // převod $_POST do $vars
# =========================================================================================== PROCES
function todo() { // ------------------------------------------------------------------------- to do
  global $vars;
  // logika formulářů
  if ($vars->faze=='a') { // => a* | b
    clear_post_but("/email|^.$/");
    do_mail_klienta();  
  }  
  if ($vars->faze=='b') { // => b* | c | n | a
    do_nacteni_rodiny(); 
  }
  if ($vars->faze=='c') { // => c* | d
    do_vyplneni_dat();  
  }
  if ($vars->faze=='d') { // => .
    do_rozlouceni();  
    // vyčisti vše
    unset($vars->post);
    do_session_restart();
  }
  // pokud se cyklus vrátil
  if ($vars->faze=='a') { // => a* | b
    clear_post_but("/email|^.$/");
    do_mail_klienta();  
  }  
}
function do_mail_klienta() { // ----------------------------------------------------- do mail_klenta
# (a) získá mail klienta, ověří jeho korektnost a evidenci v DB a pošle mu mail s PINem
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
} // a
function do_nacteni_rodiny() { // ------------------------------------------------ do nacteni_rodiny
# (b) ověří zapsaný PIN proti poslanému, načte a vytvoří data rodiny
  global $akce, $msg, $vars, $cleni, $post, $form, $CORR;
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
    kompletuj_pobyt(0,0,0);
    $cleni[-1]->email= [$post->email];
    $vars->user= '-';
    $vars->faze= 'c';
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
        "COUNT(id_osoba),id_osoba,IFNULL(id_rodina,0),GROUP_CONCAT(CONCAT(jmeno,' ',prijmeni))",
        'osoba AS o LEFT JOIN tvori USING (id_osoba) LEFT JOIN rodina USING (id_rodina)',
        "o.deleted='' AND (role IN ('a','b') OR ISNULL(role))"
        . "AND (kontakt=1 AND email $regexp OR kontakt=0 AND emaily $regexp)");
    // a jestli již není na akci přihlášen
    if ($pocet==0) {
      $msg= "Tento mail v evidenci YMCA Setkání nemáme,"
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
      // položky do hlavičky
      $vars->user= "$jmena<br>$post->email";
      // zjistíme zda již není přihlášen
      list($idp,$kdy,$kdo,$web_json)= select("id_pobyt,IFNULL(kdy,''),IFNULL(kdo,''),web_json",
          "pobyt JOIN spolu USING (id_pobyt) "
          . "LEFT JOIN _track ON klic=id_pobyt AND kde='pobyt' AND fld='id_akce' ",
          "(id_osoba={$vars->klient} OR i0_rodina=$idr) AND id_akce=$akce->id_akce "
          . "ORDER BY id_pobyt DESC LIMIT 1");
//      display("a2: $idp,$kdy,$kdo");
      if ($idp && !$CORR) {
        $kdy= $kdy ? sql_time1($kdy) : '';
        $msg= $kdo=='WEB' ? "Na tuto akci jste se již $kdy přihlásili online přihláškou." : (
            $kdo ? "Na této akci jste již $kdy přihlášeni (zápis provedl uživatel se značkou $kdo" 
            : "Na této akci jste již $kdy přihlášeni.");
        $msg.= "<br><br>Přejeme vám příjemný pobyt :-)";
        $vars->faze= 'd';
        goto end;
      }
      elseif ($idp && $CORR) { // přihláška je uložena ale jdeme do opravy
        kompletuj_pobyt($idp,$idr,$ido);
        if ($web_json) {
          $X= json_decode($web_json);
          foreach ($X->cleni as $id=>$corr) {
            foreach ((array)$corr as $f=>$v) {
              $cleni[$id]->$f= [$v];
            }
          }
        }
        $msg= '';
        $vars->faze= 'c';
        $vars->pobyt->web_changes= 2;
        goto end;
      }
      else { // klientova rodina ani klient sám na akci není
        kompletuj_pobyt(0,$idr,$ido);
        $msg= '';
        $vars->faze= 'c';
        $vars->pobyt->web_changes= 0;
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
          ? " <input type='submit' name='cmd_registrace' value='pokračovat s tímto mailem'>"
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
} // b: načtení rodiny a členů pobytu - příp. doplnění
function do_vyplneni_dat() { // ---------------------------------------------------- do vyplneni_dat
# (c) získá data od klienta a umožní jejich opktrolu a opravu
  global $akce, $r_fld, $msg, $vars, $cleni, $post, $form, $pdf_html;
  global $errors, $TEST;
  do_begin();
  $mis_souhlas= '';
  $mis_upozorneni= ['a'=>'','b'=>''];
  $pdf_html= '';
//  $post->pracovni= $post->pracovni ?? '';
  // -------------------------------------------- počáteční nastavení formuláře
  if (!$vars->form) {
    $vars->form= (object)['par'=>1,'deti'=>2,'pecouni'=>1, // 1=tlačítko, 2=seznam
        'rodina'=>$akce->p_rod_adresa,'pozn'=>1,'souhlas'=>$akce->p_souhlas,
        'todo'=>0]; // označit červeně chybějící povinné údaje po kontrole formuláře
  }
  // -------------------------------------------- ! zobraz děti a pečouny
  if (isset($post->cmd_zobraz_deti)) {
    $vars->form->deti= 2;
  }
  // -------------------------------------------- ! další dítě
  if (isset($post->cmd_dalsi_dite)) {
    clear_post_but("/email|zaslany_pin|pin|pracovni/");
    $id= 0;
    foreach (array_keys($cleni) as $is) {
      $id= min($id,$is);
    }
    $id--;
    $vars->cleni[$id]= $cleni[$id]= (object)array
        ('spolu'=>1,'jmeno'=>'','prijmeni'=>'','narozeni'=>'','role'=>'d','note'=>'');
    $vars->kontrola= 0;
  }
  // -------------------------------------------- ! pečovatel
  if (isset($post->cmd_dalsi_pecoun)) {
    clear_post_but("/email|zaslany_pin|pin|pracovni/");
    $id= 0; $olds= 0;
    foreach ($cleni as $is=>$clen) {
      if (get_role($is)=='p' && $is>0) $olds++;
      $id= min($id,$is);
    }
    if ($vars->form->pecouni==2 || ($vars->form->pecouni==1 && $olds==0)  ) { 
      // když nejsou žádní staří anebo jsme už přidali nového přidej další
      $id--;
      $vars->cleni[$id]= $cleni[$id]= (object)array
          ('spolu'=>1,'jmeno'=>'','prijmeni'=>'','narozeni'=>'','role'=>'p', 
          'obcanka'=>'','telefon'=>'', 'Xpecuje_o' => '', 
          'ulice' => '', 'psc' => '', 'obec' => '');
    }
    $vars->form->pecouni= 2;
    $vars->kontrola= 0;
  }
  // -------------------------------------------- ! nepřihlašovat
  if (isset($post->cmd_ne)) {
    clear_post_but("/---/");
    $msg= "Vyplňování přihlášky bylo ukončeno bez jejího odeslání. "
        . "<br>Na akci jste se tedy nepřihlásili.";
    $vars->faze= 'd';
    goto end;
  }
  // -------------------------------------------- ! kontrola hodnot
  if (isset($post->cmd_ano) || isset($post->cmd_check)) {
    $zapsat= true;
    $neuplne= array();
    $doplnit= array();
    $vars->form->todo= 1;
    // ------------------------------ je aspoň jeden přihlášený?
    $spolu= 0;
    foreach ($cleni as $clen) {
      $spolu+= $clen->spolu;
    }
    if (!$spolu) {
      $neuplne[]= "Zaškrtněte prosím kdo se akce zúčastní";
      $zapsat= false;
    }      
    // ------------------------------ mají manželé vyplněné všechny údaje?
    $chybi= 0;
    foreach ($cleni as $id=>$clen) {
      if (!$clen->spolu) continue;
      if (elems_missed('o',$id)) {
        $chybi++;
      }
      check_datum($clen->narozeni,'datum narození',$neuplne);
    }
    if ($chybi) {
      $neuplne[]= "doplňte označené osobní údaje";
      $zapsat= false;
    }
    if (elems_missed('r')) {
      $neuplne[]= "doplňte označené rodinné údaje";
      $zapsat= false;
    }
    if (isset($r_fld['datsvatba'])) 
      check_datum(get('r','datsvatba'),'datum svatby',$neuplne);
    if (elems_missed('p')) {
      $neuplne[]= "doplňte označené poznámky k pobytu";
      $zapsat= false;
    }
//    // ------------------------------ mají nově vyplnění všechny údaje?
    if ($akce->p_souhlas && !$vars->chk_souhlas) {
      $neuplne[]= "potvrďte prosím váš souhlas s použitím osobních údajů";
      $mis_souhlas= "class=missing";
    }
    if (count($neuplne)) 
      $msg.= zvyraznit(implode('<br>',array_unique($neuplne)));
    elseif (isset($post->cmd_check)) {
      $msg.= zvyraznit("Přihláška vypadá dobře, zašlete nám ji prosím",1);
      if (count($doplnit))
        $msg.= zvyraznit(implode('<br>',$doplnit),1);
    }
    $vars->kontrola= count($neuplne) ? 0 : 1;
    // vygenerování PDF pro Letní kurz MS
    if ($akce->p_dokument && $vars->kontrola && $TEST) {
      $pdf_html= '<hr>'.gen_html();
    }
  }
  // -------------------------------------------- ! zápis do databáze (pokud není $TEST>1)
  $errors= [];
  if (isset($post->cmd_ano) && $zapsat) {
    // vytvoření pobytu
    // účast jako ¨účastník' pokud není p_obnova => neúčast na LK znamená "náhradník"
    $ucast= 0; // = účastník
    if ($akce->p_obnova) {
      $jako= byli_na_aktualnim_LK(key($vars->rodina));
      if (!$jako) $ucast= 9; // = náhradník
      elseif ($jako==2 && $akce->p_vps) $ucast= 1; // VPS
    }
    elseif ($akce->p_pro_LK) {
      $ucast= 13;
    }
    set('p','funkce',$ucast);
    // vytvoříme nový záznam pro pobyt, pokud nejde o opravu
    if (!$vars->pobyt->id_pobyt) 
      db_open_pobyt();
    // ------------------------------ oprav rodinné údaje případně vytvoř rodinu
    db_vytvor_nebo_oprav_rodinu();
    if (count($errors)) goto db_end;
    // ------------------------------ přidej (případně vytvoř) členy rodiny
    foreach ($cleni as $id=>$clen) {
      if (!$clen->spolu) continue;
      // přidání člena rodiny
      db_vytvor_nebo_oprav_clena($id);
      if (count($errors)) goto db_end;
    }
    db_close_pobyt();
    // uzavři formulář závěrečnou zprávou
  db_end:
    clear_post_but("/email/");
    $msg= count($errors) ? 'ko' : 'ok';
    $vars->faze= 'd';
    goto end;
  }
  // ============================================ poskládej prvky formuláře
  // -------------------------------------------- členové rodiny
  $clenove= '';
  if ($vars->form->par) {
    foreach ($cleni as $id=>$clen) {
      $role= get_role($id);
      if (in_array($role,['a','b'])) {
        $upozorneni= $akce->p_upozorneni
          ? "<p class='souhlas'><input type='checkbox' name='{$id}_Xupozorneni' value=''  "
          . (get('o','Xupozorneni',$id) ? 'checked' : '')
          . " {$mis_upozorneni[$role]}><label for='{$id}_Xupozorneni' class='souhlas'>"
          . str_replace('@',$role=='b'?'a':'',$akce->upozorneni)
          . "</label></p>"
          : '';
        $clenove.= "<div class='clen'>" 
            . elem_input('o',$id,['spolu'])
            . ( $id>0
                ? elem_text('o',$id,['jmeno','prijmeni']) 
                  . ($role=='b' ? elem_text('o',$id,['roz. ','rodne']) : '')
                  . elem_text('o',$id,[', ','narozeni',',','role'])
                : elem_input('o',$id,['jmeno','prijmeni'])
                  . ($role=='b' ? elem_input('o',$id,['rodne']) : '')
                  . elem_input('o',$id,[',','narozeni',',','role']))
            . '<br>'
            . elem_input('o',$id,['email','obcanka','telefon'])
            . '<br>'
            . elem_input('o',$id,['zamest','vzdelani','zajmy','jazyk',
                'aktivita','cirkev','Xpovaha','Xmanzelstvi','Xocekavani','Xrozveden']) 
            . $upozorneni
            . "</div>";
      }
    }
  }
  if ($vars->form->deti) {
    $deti= '';
    foreach ($cleni as $id=>$clen) {
      if ($id<0 || get_role($id)!='d') continue;
      $deti.= "<div class='clen'>" 
          . elem_input('o',$id,['spolu'])
          . elem_text('o',$id,['jmeno','prijmeni',',','narozeni',',','role'])
          . elem_input('o',$id,['note'])
          . "</div>";
    }
    foreach ($cleni as $id=>$clen) {
      if ($id>0 || get_role($id)!='d') continue;
      $deti.= "<div class='clen'>" 
          . elem_input('o',$id,['spolu','jmeno','prijmeni','narozeni','role','note'])
          . "</div>";
    }
    if ($deti) $clenove.= '<p><i>Naše děti (zapište prosím i ty, které necháváte doma)</i></p>';
    $clenove.= $deti;
    $clenove.= "<br><button type='submit' name='cmd_dalsi_dite'><i class='fa fa-green fa-plus'></i>
      chci přihlásit další dítě</button>";
  }
  if ($vars->form->pecouni) {
    // pokud jsou nějací členové s role=p tak zobraz napřed je, teprve na další stisk přidej prázdné
    // form->pecouni: 1=jen tlačítko 2=jen existující 3=prázdná pole
    if ($vars->form->pecouni==1 ) {
      $clenove.= "<br><button type='submit' name='cmd_dalsi_pecoun'><i class='fa fa-green fa-plus'></i>
        chci přihlásit osobního pečovatele</button>";
    }
    if ($vars->form->pecouni==2) {
      $clenove.= '<p><i>Volba osobního pečovatele</i></p>';
      foreach ($cleni as $id=>$clen) {
        if ($id<0 || get_role($id)!='p') continue;
        $clenove.= "<div class='clen'>" 
            . elem_input('o',$id,['spolu'])
            . elem_text('o',$id,['jmeno','prijmeni',', ','narozeni',', tel.','telefon'])
            . ($clen->spolu ? elem_input('o',$id,['obcanka']) : elem_text('o',$id,[', OP:','obcanka']) )
//            . elem_input('o',$id,['Xpecuje_o'])
            . "</div>";
      }
      foreach ($cleni as $id=>$clen) {
        if ($id>0 || get_role($id)!='p') continue;
        $clenove.= "<div class='clen'>" 
            . elem_input('o',$id,['spolu','jmeno','prijmeni','narozeni','obcanka','telefon','Xpecuje_o'])
            . "</div>";
      }
      $clenove.= "<br><button type='submit' name='cmd_dalsi_pecoun'><i class='fa fa-green fa-plus'></i>
        chci přihlásit dalšího osobního pečovatele</button>";
    }
  }
  // -------------------------------------------- úprava rodinné adresy
  $rod_adresa= '';
  if ($vars->form->rodina) {
    $rod_adresa= "<p>Zkontrolujte a případně upravte vaši rodinnou adresu a další údaje:</p>";
    $idr= key($vars->rodina);
    $rod_adresa.= elem_input('r',$idr,['ulice','psc','obec','spz','datsvatba']);
  }
  // -------------------------------------------- poznánka k pobytu
  $pobyt= '';
  if ($vars->form->pozn) {
    $pobyt= elem_input('p',0,['pracovni']);
  }
  // -------------------------------------------- souhlas
  $souhlas= '';
  if ($vars->form->souhlas) {
      $souhlas= "<p class='souhlas'><input type='checkbox' name='chk_souhlas' value=''  "
      . ($vars->chk_souhlas ? 'checked' : '')
      . " $mis_souhlas><label for='chk_souhlas' class='souhlas'>$akce->form_souhlas</label>"
      . "</p>";
  }
  // -------------------------------------------- redakce formuláře
  $enable_send= $vars->kontrola ? '' : 'disabled';
  $enable_green= $vars->kontrola ? 'fa-green' : '';
  $form= <<<__EOF
    <p>Poznačte, koho na akci přihlašujete. Zkontrolujte a případně upravte zobrazené údaje.</p>
    $clenove
    <div class='rodina'>
      $rod_adresa
      $pobyt
    </div>
    $souhlas
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
} // faze = c
function do_rozlouceni() { // -------------------------------------------------------- do rozlouceni
# (d) rozloučí se s klientem
  global $msg, $akce, $vars, $post, $form, $TEST;
  $ok= $msg;
  do_begin();
  if (substr($vars->history,-2,1)=='d') {
    clear_post_but("/---/");
    $vars->faze= 'a';
  }
  elseif ($ok=='ok') {
    if ($akce->p_dokument && $vars->kontrola && $TEST<2) {
      $msg= gen_html(1);
      if ($TEST) display($msg);
    }
    $ucastnici= ''; $del= '';
    foreach ($vars->cleni as $id=>$clen) {
      if (!$clen->spolu) continue;
      $jmeno= get('o','jmeno',$id);
      $prijmeni= get('o','prijmeni',$id);
      $ucastnici.= "$del$jmeno $prijmeni"; 
      $del= ', ';
    }
    $nazev= get('r','nazev');
    if ($akce->p_pro_LK) { // závěrečná zpráva a mail pro ---------------------------- Letní kurz MS
      $msg= "Vaše přihláška byla zaevidována a poslali jsme Vám potvrzující mail na $post->email.";
      $mail_subj= "Potvrzení přijetí přihlášky ($nazev) na akci $akce->nazev.";
      $mail_body= "Dobrý den,<p>potvrzuji přijetí vaší přihlášky na akci <b>$akce->nazev</b>"
      . " pro účastníky $ucastnici."
      . " Vaši přihlášku zpracuji do tří dnů."
      . "<br>V týdnu před akcí dostanete <i>Dopis na cestu</i> s doplňujícími informacemi.</p>" 
      . "<p>S přáním hezkého dne<br>$akce->garant_jmeno"
      . "<br><a href=mailto:'$akce->garant_mail'>$akce->garant_mail</a>"
      . "<br>$akce->garant_telefon</p>"
      . "<p><i>Tato odpověď je vygenerována automaticky</i></p>";
      $ok_mail= simple_mail($akce->garant_mail, $post->email, $mail_subj,$mail_body,$akce->garant_mail); 
      $ok= $ok_mail ? 'ok' : 'ko';
    }
    elseif ($akce->p_obnova) { // závěrečná zpráva a mail pro ---------------------------- Obnovy MS
      $text= !byli_na_aktualnim_LK(key($vars->rodina))
        ? ".</p>"
          . "<p>Účast na obnově mají zajištěnu přednostně účastníci letního kurzu. "
          . "Protože jste mezi nimi nebyli, zařadili jsme vás zatím mezi náhradníky. "
          . "Pokud bude místo, ozveme se nejpozději 2 týdny před akcí a účast vám potvrdíme.</p>"
        : " a zapisuji vás mezi účastníky."
          . " Vaši přihlášku zpracuji do tří dnů.<br>V týdnu před akcí dostanete <i>Dopis na cestu</i> s doplňujícími informacemi.</p>";
      $msg= "Vaše přihláška byla zaevidována a poslali jsme Vám potvrzující mail na $post->email.";
      $mail_subj= "Potvrzení přijetí přihlášky ($nazev) na akci $akce->nazev.";
      $mail_body= "Dobrý den,<p>potvrzuji přijetí vaší přihlášky na akci <b>$akce->nazev</b>"
      . " pro účastníky $ucastnici"
      . $text
      . "<p>S přáním hezkého dne<br>$akce->garant_jmeno"
      . "<br><a href=mailto:'$akce->garant_mail'>$akce->garant_mail</a>"
      . "<br>$akce->garant_telefon</p>"
      . "<p><i>Tato odpověď je vygenerována automaticky</i></p>";
      $ok_mail= simple_mail($akce->garant_mail, $post->email, $mail_subj,$mail_body,$akce->garant_mail); 
      $ok= $ok_mail ? 'ok' : 'ko';
    }
  }
  if ($ok=='ko') {
    $msg= "Při zpracování přihlášky došlo bohužel k chybě. "
        . "<br>Přihlaste se prosím posláním mailu organizátorům akce"
        . "<br><a href=mailto:'$akce->garant_mail'>$akce->garant_mail</a>";
  }
  $form= <<<__EOF
    <p>$msg</p>
__EOF;
} // faze = d
// =============================================================================== zobrazení stránky
function page($problem='') {
  global $vars, $akce, $form, $info, $index;
  global $TEST, $MAIL, $trace, $mailbox, $y, $errors, $pdf_html;
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
  $preambule= $vars->faze=='c' ? "<p class='souhlas'>$akce->preambule</p>" : '';
  $formular= $problem ?: <<<__EOD
      $problem
      <div class='box'>
        $preambule
        <form action="$index" method="post">
          $form
          <input type="hidden" name='stamp' value="$stamp">
        </form>
      </div>
__EOD;
  $user= '';
  if ($vars->user!='-')
    $user= "<i class='fa fa-user'></i>"
      .($vars->user ?: '... přihlaste se prosím svým <br> mailem a zaslaným PINem');
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
          <div class="user">$user</div>
        </div>
        <div class="intro">Přihláška na akci <b>$akce->nazev</b></div>
      </header>
      <main>
        $mailbox
        $formular
        $info
        $pdf_html
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
function get_role($id) { // --------------------------------------------------------------- get role
# vrátí hodnotu pole role
  global $cleni;
  $role= '';
  if (isset($cleni[$id]->role)) {
    $role= $cleni[$id]->role;
    $role= is_array($role) ? ($role[1] ?? $role[0]) : $role;
  }
  return $role;
}
function get($table,$fld,$id=0) { // ----------------------------------------------------------- get
# vrátí hodnotu v datovém tvaru - pro rodinnou není nutné udávat id
# pokud není definovaná vrátí false
  global $vars;
  if ($table=='r' && !$id) $id= key($vars->rodina);
  $pair= $table=='r' ? $vars->rodina[$id] : ($table=='p' ? $vars->pobyt : $vars->cleni[$id]);
  if (isset($pair->$fld)) {
    $v= is_array($pair->$fld) ? ($pair->$fld[1] ?? $pair->$fld[0]) : $pair->$fld;
  }
  else $v= false;
  return $v;
}
function gets($table,$id=0) { // ------------------------------------------------------------- get s
# vrátí hodnotu všech položek jako objekt - pro rodinnou není nutné udávat id
# hodnoty jsou v reprezentačním tvaru
  global $vars, $p_fld, $r_fld, $o_fld, $options;
  $ret= (object)[];
  if ($table=='r' && !$id) $id= key($vars->rodina);
  $desc= $table=='r' ? $r_fld : ($table=='p' ? $p_fld : $o_fld);
  $pair= $table=='r' ? $vars->rodina[$id] : ($table=='p' ? $vars->pobyt : $vars->cleni[$id]);
  foreach ($desc as $f=>list(,,$typ)) {
    if (!isset($pair->$f)) continue;
    $v= is_array($pair->$f) ? ($pair->$f[1] ?? $pair->$f[0]) : $pair->$f;
    if ($typ=='select') 
      $v= $options[$f][$v] ?? '?';
    $ret->$f= $v;
  }
  return $ret;
}
function inits($table) { // --     ---------------------------------------------------------- init s
# vrátí iniciální hodnotu všech položek jako objekt
  global $p_fld, $r_fld, $o_fld, $options;
  $ret= (object)[];
  $desc= $table=='r' ? $r_fld : ($table=='p' ? $p_fld : $o_fld);
  foreach ($desc as $f=>list(,,$typ)) {
    $v= init_value($typ);
    if ($typ=='select') 
      $v= $options[$f][0] ?? '?';
    $ret->$f= $v;
  }
  return $ret;
}
function get_fmt($table,$fld,$id=0) { // ----------------------------------------------------------- get
# vrátí hodnotu v uživatelském tvaru - pro rodinnou není nutné udávat id
  global $p_fld, $r_fld, $o_fld, $options;
  global $vars;
  $desc= $table=='r' ? $r_fld : ($table=='p' ? $p_fld : $o_fld);
  if ($table=='r' && !$id) $id= key($vars->rodina);
  $pair= $table=='r' ? $vars->rodina[$id] : ($table=='p' ? $vars->pobyt : $vars->cleni[$id]);
  if (isset($pair->$fld)) {
    $v= is_array($pair->$fld) ? ($pair->$fld[1] ?? $pair->$fld[0]) : $pair->$fld;
    if (isset($desc[$fld])) {
      list(,,$typ)= $desc[$fld];
      switch ($typ) {
      case 'date':
        $v= sql2date($v,0);
        break;
      case 'select':
        $v= $options[$fld][$v] ?? '?';
        break;
      }
    }
  }
  else $v= $fld;
  return $v;
}
function set($table,$fld,$val,$id=0) { // ------------------------------------------------------ set
  global $vars; //$p_fld, $r_fld, $o_fld, $options, 
//  $desc= $table=='r' ? $r_fld : ($table=='p' ? $p_fld : $o_fld);
  if ($table=='r' && !$id) $id= key($vars->rodina);
  $pair= $table=='r' ? $vars->rodina[$id] : ($table=='p' ? $vars->pobyt : $vars->cleni[$id]);
//  if (!isset($desc[$fld])) {
//    $v= $val;
//  }
//  else {
//    list(,,$typ)= $desc[$fld];
//    switch ($typ) {
//    case 'date':
//      $v= sql2date($v,1);
//      break;
//    case 'select':
//      $v= array_search($val,$options[$fld]);
//      break;
//    }
//  }
  if (is_array($pair->$fld)) 
    $pair->$fld[1]= $val;
  else
    $pair->$fld= $val;
}
function elem_text($table,$id,$flds) { // ------------------------------------------------ elem text
  $html= '';
  foreach ($flds as $fld) {
    $html.= ' '.get_fmt($table,$fld,$id);
  }
  return "<span>$html</span>";
}
function elems_missed($table,$id=0) { // ----------------------------------------------- elem missed
  global $p_fld, $r_fld, $o_fld, $vars;
  $missed= 0;
  if ($table=='p') {
    foreach ($p_fld as $f=>list(,$title,$typ)) {
      $v= $vars->pobyt->$f;
      if (is_array($v) && substr($title,0,1)=='*') {
        $v= $v[1] ?? $v[0];
        if ($v=='' || $typ=='select' && $v==0) {
          $missed= 1;
          goto end;
        }
      }
    }
  }
  if ($table=='r') {
    $idr= key($vars->rodina);
    $rodina= $vars->rodina[$idr];
    foreach ($r_fld as $f=>list(,$title,$typ)) {
      if (substr($title,0,1)=='*') {
        if (is_array($rodina->$f)) {
          $v= $rodina->$f[1] ?? $rodina->$f[0];
          if ($v=='' || $typ=='select' && $v==0) {
            $missed= 1;
            goto end;
          }
        }
      }
    }
  }
  if ($table=='o') {
    $clen= $vars->cleni[$id];
    foreach ($o_fld as $f=>list(,$title,$typ,$omez)) {
      if (substr($title,0,1)=='*' && strpos($omez,get_role($id))!==false) {
        if (is_array($clen->$f)) {
          $v= $clen->$f[1] ?? $clen->$f[0];
          if ($v=='' || ($typ=='select' && $v==0)) {
            $missed= 1;
            goto end;
          }
        }
      }
    }
  }
end:
  return $missed;
}
function elem_input($table,$id,$flds) { // ---------------------------------------------- elem input
# vytvoř část formuláře - pro vstup
  global $p_fld, $r_fld, $o_fld, $vars, $options;
  $html= '';
  $desc= $table=='r' ? $r_fld             : ($table=='o' ? $o_fld             : $p_fld);
  $pair= $table=='r' ? $vars->rodina[$id] : ($table=='o' ? $vars->cleni[$id]  : $vars->pobyt);
  $prfx= $table=='r' ? 'r_'               : ($table=='o' ? "{$id}_"           : 'p_');
  foreach ($flds as $fld) {
    if (!isset($desc[$fld])) {
//      $html.= $fld;
      continue;
    }
    $name= "$prfx$fld";
    list($len,$title,$typ)= $desc[$fld];
    if ($typ=='x') continue;
    // rozpoznání povinnosti položky
    $v= is_array($pair->$fld) ? ($pair->$fld[1] ?? $pair->$fld[0]) : $pair->$fld;
    $todo= '';
    if (substr($title,0,1)=='*' && ($table!='o' || $pair->spolu)) {
      $title=  "<b style='color:red'>*</b>".substr($title,1);
      if ($vars->form->todo && ($v=='' || $typ=='select' && $v==0)) {
        $todo= " class='missing'";
      }
    }
    switch ($typ) {
    case 'check_spolu':
    case 'check':
      $x=  $v ? 'checked' : '';
      $html.= "<label class='$typ'>$title<input type='checkbox' name='$name' value='x' $x$todo></label>";
      break;
//    case 'date':
//      $x= sql2date($v,0);
//      $html.= "<label class='upper'>$title<input type='text' name='$name' size='$len' value='$x'$todo></label>";
//      break;
    case 'select':
      $html.= "<label class='upper'>$title<select$todo name='$name'>";
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
      $html.= "<label class='upper-area'>$title<textarea rows='$rows' cols='$cols' name='$name'$todo>$v</textarea></label>";
      break;
    default:
      $x= $v ? "value='$v'" : ''; // "placeholder='$holder'";
      $html.= "<label class='upper'>$title<input type='text' name='$name' size='$len' $x$todo></label>";
    }
  }
  return $html;
}
# ================================================================ vytváření a načítání členů pobytu
function vytvor_pobyt() { // ---------------------------------------------------------- vytvor pobyt
  global $vars, $p_fld;
  $vars->pobyt= (object)['id_pobyt'=>0];
  foreach ($p_fld as $f=>list(,,$typ)) {
    $vars->pobyt->$f= [init_value($typ)];
//    $vars->pobyt->$f= substr($title,0,1)=='*' ? [init_value($typ)] : init_value($typ);
  }
  $vars->pobyt->web_changes= 1;
}
function vytvor_rodinu() { // -------------------------------------------------------- vytvor rodinu
  global $vars, $r_fld;
  $idr= -1;
  $vars->rodina= [$idr=>(object)[]];
  $rodina= $vars->rodina[$idr];
  foreach ($r_fld as $f=>list(,$title,$typ)) {
    $rodina->$f= substr($title,0,1)=='*' ? [init_value($typ)] : init_value($typ);
  }
}
function vytvor_clena($ido,$role) { // ------------------------------------------------ vytvor clena
  // inicializace dat pro dospělou osobu, přidáme roli a že je na akci
  global $cleni, $o_fld;
  $cleni[$ido]= (object)[];
  foreach ($o_fld as $f=>list(,$title,$typ,$omez)) {
    if (strpos($omez,$role)!==false)
      $cleni[$ido]->$f= substr($title,0,1)=='*' ? [init_value($typ)] : init_value($typ);
  }
  $cleni[$ido]->role= $role;
  $cleni[$ido]->spolu= 1;
}
function init_value($typ) { // ---------------------------------------------------------- init value
  $val= $typ=='select' ? 0 : '';
  return $val;
}
# --------------------------------------------------------------------------------- čtení z databáze
function byli_na_aktualnim_LK($rodina) { // ----------------------------------- byli na_aktualnim_LK
# pro pobyt na obnově zjistí, zda rodina byla na jejím LK 
# ... 0 nebyla vůbec | 1 jako účastníci | 2 jako sloužící VPS
  global $akce;
  $obnova_mesic= select('MONTH(datum_od)','akce',"id_duakce=$akce->id_akce");
  $rok_LK= $obnova_mesic>7 ? date('Y') : date('Y')-1;
  $byli= select1('IFNULL(IF(funkce=1,2,1),0)','pobyt JOIN akce ON id_akce=id_duakce',
      "akce.druh=1 AND YEAR(akce.datum_od)=$rok_LK AND pobyt.i0_rodina='$rodina'");
  return $byli;
}
function nacti_pobyt($idp) { // -------------------------------------------------------- nacti pobyt
  global $vars, $p_fld;
  $vars->pobyt= (object)['id_pobyt'=>$idp];
  $p= select_object('*','pobyt',"id_pobyt=$idp");
  foreach ($p as $f=>$v) {
    if (!isset($p_fld[$f])) continue;
    list(,$title,$typ)= $p_fld[$f];
    if ($typ=='date') 
      $v= sql2date($v);
    $vars->pobyt->$f= substr($title,0,1)=='*' ? [$v] : $v;
  }
  $vars->pobyt->web_changes= 2;
}
function nacti_rodinu($idr) { // ------------------------------------------------------ nacti rodinu
  global $akce, $vars, $r_fld;
  $vars->rodina= [$idr=>(object)[]];
  $rodina= $vars->rodina[$idr];
  if ($akce->p_rod_adresa) {
    $r= select_object('*','rodina',"id_rodina=$idr");
    foreach ($r as $f=>$v) {
      if (!isset($r_fld[$f]) || substr($f,0,1)=='X') continue;
      list(,$title,$typ)= $r_fld[$f];
      if ($typ=='date') 
        $v= sql2date($v);
      $rodina->$f= substr($title,0,1)=='*' ? [$v] : $v;
    }
  }
}
function nacti_clena($ido,$role) { // -------------------------------------------------- nacti clena
  // přečteme položky dané osoby, přidáme roli a že je na akci
  global $cleni, $o_fld;
  $clen= $cleni[$ido]= (object)[];
  $o= select_object('*','osoba',"id_osoba=$ido");
  foreach ($o_fld as $f=>list(,$title,$typ)) {
    if (substr($f,0,1)=='X') 
      $clen->$f= substr($title,0,1)=='*' ? [init_value($typ)] : init_value($typ);
    elseif (!isset($o->$f)) continue;
    else {
      $v= $o->$f;
      if ($typ=='date') $v= sql2date($v);
      $clen->$f= substr($title,0,1)=='*' ? [$v] : $v;
    }
  }
  $cleni[$ido]->role= $role;
  $cleni[$ido]->spolu= 1;
}
function db_nacti_cleny_rodiny($idr,$prvni_ido) { // ------------------------- db nacti_cleny_rodiny
  global $akce, $cleni,$o_fld;
  $nodb= [];
  $roles= []; // role členů rodiny
  $flds= '';
  foreach (array_keys($o_fld) as $f) {
    if (substr($f,0,1)=='X') { // položka začínající X nepatří do tabulky
      $nodb[]= $f;
      continue;
    }
    if (in_array($f,['role','spolu','telefon'])) continue; // zvláštní zpracování
    $flds.= ",$f";
  }
  $ro= pdo_query(
    "SELECT id_osoba$flds,role,IF(kontakt=1,telefon,'') AS telefon
    FROM osoba AS o JOIN tvori USING (id_osoba)
    WHERE id_rodina=$idr AND o.deleted='' AND role IN ('a','b','d','p') 
    ORDER BY IF(id_osoba=$prvni_ido,'0',narozeni)  ");
  while ($ro && ($c= pdo_fetch_object($ro))) {
    $roles[]= $c->role;
    $c->spolu= $prvni_ido==$c->id_osoba ? 1 : 0;
    if (($akce->p_pro_par || $akce->p_pro_LK) && in_array($c->role,['a','b'])) 
      $c->spolu= 1;
    foreach ((array)$c as $f=>$v) {
      if (!isset($o_fld[$f])) 
        continue;
      list(,$title,$typ)= $o_fld[$f];
      if ($typ=='date') 
        $v= sql2date($v);
      $c->$f= substr($title,0,1)=='*' ? [$v] : $v;
      if (isset($o_fld[$f][2]) && $o_fld[$f][2]=='date') 
        $c->$f= sql2date($v);
    }
    // doplň prázdné hodnoty 
    foreach ($nodb as $f) {
      list(,$title,$typ)= $o_fld[$f];
      $c->$f= substr($title,0,1)=='*' ? [init_value($typ)] : init_value($typ);
    }
    $cleni[$c->id_osoba]= $c;
  }
  return $roles;
}
function kompletuj_pobyt($idp,$idr,$ido) { // -------------------------------------- kompletuj pobyt
  // zajisti aby ve vars->rodina a cleni byla úplná rodina (byť s prázdnými položkami)
  // a byl iniciován resp. načten pobyt 
  if ($idr) { // rodina existuje
    nacti_rodinu($idr);        
    $roles= db_nacti_cleny_rodiny($idr,$ido);
    // případně do rodiny doplníme druhého z manželů
    if (!in_array('a',$roles)) 
      vytvor_clena(-1,'a');
    if (!in_array('b',$roles)) 
      vytvor_clena(-2,'b');
  }
  elseif ($ido) { // vytvoříme rodinu a načteme klienta a doplníme druhého z manželů
    $role= select('sex','osoba',"id_osoba=$ido");
    vytvor_rodinu();        
    nacti_clena($ido,['a']);
    vytvor_clena(-1,$role=='b' ? 'a' : 'b');
  }
  else { // vytvoříme rodinu 
    vytvor_rodinu();        
    vytvor_clena(-1,'a');
    vytvor_clena(-2,'b');
  }
  // vytvoř nebo načti pobyt
  if ($idp) {
    nacti_pobyt($idp);
  }
  else 
    vytvor_pobyt();
}
# ================================================================================ zápis do databáze
function db_open_pobyt() { // -------------------------------------------------------- db open_pobyt
# vytvoř pobyt - potřebujeme dále jeho ID 
  global $errors, $akce, $vars, $web_changes;
  // web_changes= 1/2 pro INSERT/UPDATE pobyt+spolu | 4/8 pro INSERT/UPDATE osoba | 16/32 pro INSERT/UPDATE rodina
  $web_changes= 1; 
  $ida= $akce->id_akce;
  $chng= array(
    (object)array('fld'=>'id_akce',    'op'=>'i','val'=>$ida),
    (object)array('fld'=>'web_zmena',  'op'=>'i','val'=>date('Y-m-d'))
  );
  $idp= _ezer_qry("INSERT",'pobyt',0,$chng);
  if (!$idp) $errors[]= "Nastala chyba při zápisu do databáze (p)"; 
  $vars->pobyt->id_pobyt= $idp;
  return $idp;
}
function db_vytvor_nebo_oprav_clena($id) { // --------------------------- db vytvor_nebo_oprav_clena
  global $errors, $o_fld, $akce, $vars, $cleni, $web_changes;
  // web_changes= 1/2 pro INSERT/UPDATE pobyt+spolu | 4/8 pro INSERT/UPDATE osoba | 16/32 pro INSERT/UPDATE rodina
  $web_changes= 1; 
  set('p','web_changes',get('p','web_changes')|2);
  // pobyt a rodina už musí být zapsané
  $idp= $vars->pobyt->id_pobyt;
  $idr= key($vars->rodina);
  $clen= $cleni[$id];
  $role= get('o','role',$id);
  $jmeno= get('o','jmeno',$id);
  $prijmeni= get('o','prijmeni',$id);
  $narozeni= date2sql(get('o','narozeni',$id));
  if ($id<0) {
    // člen ještě není v databázi
    $ido= 0;
    // abychom zamezili duplicitám podle jména a data narození zjistíme, jestli už není v evidenci 
    list($pocet,$idx,$access)= select('COUNT(*),id_osoba,access','osoba',
        "deleted='' AND jmeno='$jmeno' AND prijmeni='$prijmeni' AND narozeni='$narozeni' ");
    if ($pocet==1) {
      // asi známe - přidáme jako poznámku do pracovní poznámky pobytu
      $ido= $idx;
      if ($access!=$akce->org) {
        // rozšíříme povolení
        $chng[]= (object)array('fld'=>'access', 'op'=>'u','old'=>$access,'val'=>$access|$akce->org);
      }
      // zpráva do pracovní poznámky
      $p_old= get('p','pracovni');
      $p_new= $p_old ? "$p_old ... $jmeno $prijmeni bylo nalezeno jako ID=$ido" : $p_old;
      _ezer_qry("UPDATE",'pobyt',$idp,
          [(object)['fld'=>'pracovni', 'op'=>'u','old'=>$p_old,'val'=>$p_new]]);
    }
  }
  else {
    $ido= $id;
  }
  if ($ido==0) {
    // zapíšeme novou osobu a připojíme ji do rodiny
    set('p','web_changes',get('p','web_changes')|4);
    $sex= select('sex','_jmena',"jmeno='$jmeno' LIMIT 1");
    $sex= $sex==1 || $sex==2 ? $sex : 0;
    $kontakt= 0;
    $chng= array(
      (object)['fld'=>'sex',      'op'=>'i','val'=>$sex],
      (object)['fld'=>'access',   'op'=>'i','val'=>$akce->org],
      (object)['fld'=>'web_zmena','op'=>'i','val'=>date('Y-m-d')]
    );
    foreach ((array)$clen as $f=>$vals) {
      if (substr($f,0,1)=='X') continue; // položka začínající X nepatří do tabulky
      if (is_array($vals) && (!isset($vals[1]) || (isset($vals[1]) && $vals[1]!=$vals[0]))) {
        $v= $vals[1]??$vals[0];
        if (in_array($f,['telefon','email','nomail'])) {
          $chng[]= (object)['fld'=>$f, 'op'=>'i','val'=>$v];
          $kontakt= 1;
        }
        else {
          if ($o_fld[$f][2]=='date') 
            $v= date2sql($v);
          $chng[]= (object)['fld'=>$f, 'op'=>'i','val'=>$v];
        }
      }
    }
    if ($kontakt) $chng[]= (object)['fld'=>'kontakt', 'op'=>'i','val'=>1];
    $ido= _ezer_qry("INSERT",'osoba',0,$chng);
    if (!$ido) $errors[]= "Nastala chyba při zápisu do databáze (o)"; 
    $cleni[$ido]= $cleni[$id];
    unset($cleni[$id]);
    // zapiš, že patří do rodiny
    $chng= []; 
    if (!count($errors)) {
      $chng= array(
        (object)array('fld'=>'id_rodina', 'op'=>'i','val'=>$idr),
        (object)array('fld'=>'id_osoba',  'op'=>'i','val'=>$ido),
        (object)array('fld'=>'role',      'op'=>'i','val'=>$role)
      );
      $idt= _ezer_qry("INSERT",'tvori',0,$chng);
      if (!$idt) $errors[]= "Nastala chyba při zápisu do databáze (t)"; 
    }
  }
  else {
    // opravíme změněné hodnoty položek existující osoby
    $chng= [];
    $kontakt= 0;
    foreach ((array)$clen as $f=>$vals) {
      if (substr($f,0,1)=='X') continue; // položka začínající X nepatří do tabulky
      if (is_array($vals) && isset($vals[1]) && $vals[1]!=$vals[0]) {
        if (in_array($f,['telefon','email','nomail']) && $clen->kontakt[0]==0) {
          $chng[]= (object)['fld'=>'kontakt', 'op'=>'u','old'=>0,'val'=>1];
          $kontakt= 1;
        }
        else {
          $v0= $vals[0];
          $v= $vals[1];
          if ($o_fld[$f][2]=='date') {
            $v0= date2sql($v0);
            $v= date2sql($v);
          }
          $chng[]= (object)['fld'=>$f, 'op'=>'u','old'=>$v0,'val'=>$v];
        }
      }
    }
    if ($kontakt) $chng[]= (object)['fld'=>'kontakt', 'op'=>'i','val'=>1];
    if (count($chng)) {
      if (!_ezer_qry("UPDATE",'osoba',$ido,$chng)) 
        $errors[]= "Nastala chyba při zápisu do databáze (o)"; 
      $web_changes|= 8; 
    }
  }
  // zapojíme do pobytu
  $chng= array(
    (object)['fld'=>'id_pobyt',  'op'=>'i','val'=>$idp],
    (object)['fld'=>'id_osoba',  'op'=>'i','val'=>$ido],
    (object)['fld'=>'s_role',    'op'=>'i','val'=>$role=='d'?2:1]
  );
  $ids= _ezer_qry("INSERT",'spolu',0,$chng);
  if (!$ids) $errors[]= "Nastala chyba při zápisu do databáze (cs)"; 
}
function db_vytvor_nebo_oprav_rodinu() { // ---------------------------- do vytvor_nebo_oprav_rodinu
# oprav rodinné údaje resp. vytvoř novou rodinu
  global $akce, $r_fld, $vars, $cleni, $errors, $web_changes;
  // web_changes= 1/2 pro INSERT/UPDATE pobyt+spolu | 4/8 pro INSERT/UPDATE osoba | 16/32 pro INSERT/UPDATE rodina
  $id= key($vars->rodina);
  $rodina= $vars->rodina[$id];
  if ($id<0) {
    // musíme vytvořit rodinu - vymyslíme název
    $nazev= "nová-rodina";
    foreach (array_keys($cleni) as $ido) {
      $role= get_role($ido);
      $prijmeni= get('o','prijmeni',$ido);
      if ($role=='b' && $prijmeni) {
        $nazev= preg_replace('~ová$~','',$prijmeni).'ovi';
        break;
      }
      elseif ($role=='a') {
        $nazev= $prijmeni;
      }
    }
    // a vytvoříme ji
    set('p','web_changes',get('p','web_changes')|16);
    $chng= array(
      (object)['fld'=>'nazev',    'op'=>'i','val'=>$nazev],
      (object)['fld'=>'access',   'op'=>'i','val'=>$akce->org],
      (object)['fld'=>'web_zmena',  'op'=>'i','val'=>date('Y-m-d')]
    );
    foreach ((array)$rodina as $f=>$vals) {
      if (substr($f,0,1)=='X') continue; // položka začínající X nepatří do tabulky
      if (is_array($vals) && (!isset($vals[1]) || (isset($vals[1]) && $vals[1]!=$vals[0]))) {
        $v= $vals[1]??$vals[0];
        if ($r_fld[$f][2]=='date') 
          $v= date2sql($v);
        $chng[]= (object)['fld'=>$f, 'op'=>'i','val'=>$v];
      }
    }
    $idr= _ezer_qry("INSERT",'rodina',0,$chng);
    if (!$idr) $errors[]= "Nastala chyba při zápisu do databáze (o)"; 
    $vars->rodina[$idr]= $rodina;
    unset($vars->rodina[$id]);
  }
  else {
    $chng= [];
    foreach ($rodina as $f=>$vals) {
      if (substr($f,0,1)=='X') continue; // položka začínající X nepatří do tabulky
      if (is_array($vals) && isset($vals[1]) && $vals[1]!=$vals[0]) {
        $v0= $vals[0];
        $v= $vals[1];
        if ($r_fld[$f][2]=='date') {
          $v0= date2sql($v0);
          $v= date2sql($v);
        }
        $chng[]= (object)['fld'=>$f, 'op'=>'u','old'=>$v0,'val'=>$v];
      }
    }
    if (count($chng)) {
      if (!_ezer_qry("UPDATE",'rodina',$id,$chng)) 
        $errors[]= "Nastala chyba při zápisu do databáze (r)"; 
      $web_changes|= 32; 
    }
  }
}
function db_close_pobyt() { // ------------------------------------------------------ db close_pobyt
  global $errors, $vars, $cleni, $web_changes;
  // příprava položky web_json
  // struktura stejná jako $cleni ale zaznamenávají se jen konečné hodnoty položek začínajících X
  $web_json= (object)['cleni'=>[]]; 
  foreach ($cleni as $id=>$clen) {
    foreach ((array)$clen as $f=>$v) {
      if (!in_array($f,['spolu','Xpovaha','Xmanzelstvi','Xocekavani','Xrozveden']) ) continue;
      $v= $v[1]??($v[0]??$v);
      if ($v!=='' || $f=='spolu') {
        if (!isset($web_json->cleni[$id])) $web_json->cleni[$id]= (object)[];
        $web_json->cleni[$id]->$f= $v;
      }
    }
  }
  debug($web_json,"web_json");
  $web_json= json_encode($web_json);
  if (!$web_json) $errors[]= "chyba při ukládání: ".json_last_error();
  // úschova pobyt
  $idr= key($vars->rodina);
  $chng= array(
    (object)['fld'=>'i0_rodina',  'op'=>'i','val'=>$idr],
    (object)['fld'=>'web_changes','op'=>'i','val'=>$web_changes],
    (object)['fld'=>'web_json',   'op'=>'i','val'=>$web_json],
  );
  foreach ($vars->pobyt as $f=>$vals) {
    if (is_array($vals) && isset($vals[1]) && $vals[1]!=$vals[0]) {
      $chng[]= (object)['fld'=>$f, 'op'=>'i','val'=>$vals[1]];
    }
  }
  if (!_ezer_qry("UPDATE",'pobyt',$vars->pobyt->id_pobyt,$chng)) 
    $errors[]= "Nastala chyba při zápisu do databáze (p)"; 
}
function _ezer_qry($op,$table,$cond_key,$chng) { // --------------------------------------- ezer qry
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
# ============================================================================= vytváření PDF obrazu
function gen_html($to_save=0) {
# vygeneruje textový tvar přihlášky
  global $akce, $vars, $cleni;
  $ted= date("j.n.Y H:i:s");
  $html= '';
  $html.= "<h3 style=\"text-align:center;\">Údaje z online přihlášky na akci \"$akce->nazev\"</h3>";
  $html.= "<p style=\"text-align:center;\"><i>vyplněné $ted a doplněné dříve svěřenými osobními údaji</i></p>";
  // odlišení muže, ženy a dětí
  $sebou= []; $deti= $del_d= ''; $chuvy= '';
  $r= gets('r');
  $m= inits('o');
  $z= inits('o');
  foreach (array_keys($cleni) as $id) {
    $o= gets('o',$id);
    switch (get_role($id)) {
      case 'a': if ($o->spolu) $m= $o; break;
      case 'b': if ($o->spolu) $z= $o; break;
      case 'd': 
        $jmeno= "$o->jmeno $o->prijmeni";
        $deti.= "$del_d$jmeno, $o->narozeni";
        $del_d= ', ';
        if (get('o','spolu',$id))
          $sebou[]= (object)['jmeno' => $jmeno, 'narozeni' => $o->narozeni, 'note' => $o->note]; 
        break;
      case 'p': 
        if ($o->spolu) {
          $jmeno= "$o->jmeno $o->prijmeni";
          $adresa= "$o->ulice, $o->psc $o->obec";
          $chuvy.= "<br>$jmeno, $o->narozeni, bydliště: $adresa, <b>pečuje o: $o->Xpecuje_o</b>";
        }
        break;
    }
  }
  // redakce osobních údajů
//  $m= gets('o',$idm);
  $udaje= [
    ['Jméno a příjmení', "$m->jmeno $m->prijmeni", "$z->jmeno $z->prijmeni"], 
    ['Datum narozeni',    $m->narozeni, $z->narozeni ],
    ['Telefon',           $m->telefon, $z->telefon ],
    ['E-mail',            $m->email, $z->email], 
    ['Č. OP nebo cest. dokladu', $m->obcanka, $z->obcanka ],
  ];
  $html.= "
    <style>
      table.prihlaska { width:100%; border-collapse: collapse; }
      table.prihlaska td { border: 1px solid grey; }
      table.prihlaska th { text-align:center; }
    </style>
    ";
  $table_attr= "class=\"prihlaska\" cellpadding=\"7\"";
  $th= "th colspan=\"2\"";
  $td= "td colspan=\"2\"";
  $html.= "<table $table_attr><tr><th></th><$th>Muž</th><$th>Žena</th></tr>";
  foreach ($udaje as $u) {
    $html.= "<tr><th>$u[0]</th><$td>$u[1]</td><$td>$u[2]</td></tr>";
  }
  $html.= "<tr><th>Adresa, PSČ</th><td colspan=\"4\">$r->ulice, $r->psc $r->obec</td></tr>";
  $html.= "</table>";
  // děti
  if (count($sebou)) {
    $html.= "<p><i>Na Manželská setkání přihlašujeme i tyto děti:</i></p>";
    $html.= "<table $table_attr><tr><th>Jméno a příjmení</th><th>Datum narození</th><th>Poznámky (nemoci, alergie apod.)</th></tr>";
    foreach ($sebou as $d) {
      $html.= "<tr><td>$d->jmeno</td><td>$d->narozeni</td><td>$d->note</td></tr>";
    }
    $html.= "</table>";
    if ($chuvy) {
      $html.= "<p><b>a osobního pečovatele:</b> ";
      $html.= "$chuvy</p>";
    }
  }
  $html.= "<p></p>";
  // redakce citlivých údajů
  $jm= $m->jazyk; $jm= $jm ? ", $jm" : '';
  $jz= $z->jazyk; $jz= $jz ? ", $jz" : '';
  $udaje= [
    ['Vzdělání',              $m->vzdelani, $z->vzdelani],
    ['Povolání, zaměstnání',  $m->zamest, $z->zamest],
    ['Zájmy, znalost jazyků',"$m->zajmy $jm", "$z->zajmy $jz"],
    ['Popiš svoji povahu',    $m->Xpovaha, $z->Xpovaha],
    ['Vyjádři se o vašem manželství', $m->Xmanzelstvi, $z->Xmanzelstvi],
    ['Co od účasti očekávám', $m->Xocekavani, $z->Xocekavani],
    ['Příslušnost k církvi',  $m->cirkev, $z->cirkev],
    ['Aktivita v církvi',     $m->aktivita, $z->aktivita],
  ];
  $th= "th colspan=\"2\"";
  $html.= "<table $table_attr><tr><th></th><$th>Muž</th><$th>Žena</th></tr>";
  $td= "td colspan=\"2\"";
  foreach ($udaje as $u) {
    $html.= "<tr><th>$u[0]</th><$td>$u[1]</td><$td>$u[2]</td></tr>";
  }
  $html.= "<tr><th>Děti (jméno + datum narození)</th><td colspan=\"4\">$deti</td></tr>";
  $html.= "<tr>
    <th>SPZ auta na kurzu</th><td>$r->spz</td>
    <td>Datum svatby: $r->datsvatba</td>
    <td colspan=\"2\">Předchozí manželství? muž: "
      .($m->Xrozveden?:'-').", žena: ".($z->Xrozveden?:'-')."</td></tr>";
  $html.= "</table>";
  if ($akce->p_upozorneni)
    $html.= "<p><i>Souhlas obou manželů s podmínkami účasti na kurzu byl potvrzen $ted.</i></p>";
  // vložit přihlášku jako PDF do záložky Dokumenty daného pobytu
  if ($to_save) {
    global $path_files_h;
    $fname= "online-prihlaska.pdf";
    $foot= '';
    $idp= $vars->pobyt->id_pobyt;
    $f_abs= "$path_files_h/pobyt/{$fname}_$idp";
    tc_html($f_abs,$html,$foot);
    $html= "Přihláška byla vložena do záložky Dokumenty jako soubor $fname ";
  }
  return $html;
}
# --------------------------------------------------------------------------------- správa proměných
function do_begin() { // ------------------------------------------------------------------ do begin
  global $AKCE, $vars, $cleni, $post;
  $_SESSION[$AKCE]->history.= $_SESSION[$AKCE]->faze;
  $vars= $_SESSION[$AKCE];
  $cleni= $vars->cleni;
  $post= $vars->post;
  // trace
//  global $TEST;
//  if ($TEST) {
//    $bt= debug_backtrace();
//    trace_vars("beg >>> {$bt[1]['function']}");
//  }
}
function do_end() { // ---------------------------------------------------------------------- do end
  global $AKCE, $vars, $cleni, $post;
  // uloží vars 
  $vars->cleni= $cleni;
  $vars->post= $post;
  $_SESSION[$AKCE]= $vars;
  // trace
//  global $TEST;
//  if ($TEST) {
//    $bt= debug_backtrace();
//    trace_vars("end <<< {$bt[1]['function']}");
//  }
}
function do_session_restart() { // ---------------------------------------------- do session_restart
  global $AKCE;
  unset($_SESSION[$AKCE]);
  session_write_close();
  session_start();
  $_SESSION[$AKCE]= (object)[];
}
function trace_vars($title) { // -------------------------------------------------------- trace vars
  global $TEST, $trace, $vars;
  if ($TEST) {
    $vars_dump= [];
    foreach (explode(',',"stamp,faze,history,kontrola,klient,user,chk_souhlas,form,pobyt,rodina,cleni,post") as $v) {
      $vars_dump[$v]= $vars->$v ?? '?';
    }
    $trace.= '<hr>'.debugx($vars_dump,$title,0,4);
  }
}
function clear_post_but($flds_match) { // ------------------------------------------- clear post_but
  global $vars, $post;
  foreach (array_keys((array)$post) as $fld) {
    if (!preg_match($flds_match,$fld)) {
      unset($post->$fld);
    }
  }
  $vars->post= $post;
}
# ----------------------------------------------------------------------------------- pomocné funkce
function date2sql($d) { // -------------------------------------------------------------- date 2 sql
# transformace d.m.y na d-m-y resp. y na y-00-00
# pokud $d nemá korektní tvar vrací původní hodnotu
  $v= str_replace(' ','',$d);
  $v= $v=='' ? '0000-00-00' : (
      strlen($v)==4 ? "$v-00-00" : sql_date1($v,1)
  );
  return $v;
}
function sql2date($d) { // -------------------------------------------------------------- sql 2 date
# transformace d-m-y na d.m.y resp. y-00-00 na y
# pokud $d nemá korektní tvar vrací původní hodnotu
  $v= substr($d,5,5)=='00-00' ? (substr($d,0,4)=='0000' ? '' : substr($d,0,4)) : sql_date1($d,0);
  return $v;
}
function check_datum($d_val,$d_nazev,&$neuplne) { // ----------------------------------- check datum
  $ok= 1;
  if (isset($d_val)) {
    $d= is_array($d_val) ? ($d_val[1] ?? $d_val[0]) : $d_val;
    $datum= str_replace(' ','',$d);
    $dmy= explode('.',$datum);
    if (count($dmy)!=3) {
      $neuplne[]= "napište $d_nazev ve tvaru den.měsíc.rok ";
      $ok= 0;
    }
    else {
      if (!checkdate($dmy[1],$dmy[0],$dmy[2])) {
        $neuplne[]= "opravte prosím $d_nazev (den.měsíc.rok) - je nějaké divné";
      $ok= 0;
      }
      elseif (date('Y')-$dmy[2] > 99 || date('Y')-$dmy[2] < 0) {
        $neuplne[]= "opravte prosím $d_nazev - nevypadá pravděpodobně";
      $ok= 0;
      }
    }
  }
  return $ok;
}
function ip_ok() { // ------------------------------------------------------------------------ ip ok
# pozná localhost, IP Talichova, IP chata, LAN Noe
  $ip= $_SERVER['HTTP_X_REAL_IP'] ?? $_SERVER['REMOTE_ADDR'];
  return in_array($ip,[
      '127.0.0.1', // localhost
      '86.49.254.42','80.95.103.170','217.64.3.170', // GAN
      '95.82.145.32'] // JZE
      );
}
function form_stamp() { // -------------------------------------------------------------- form stamp
  global $AKCE, $vars;
  $stamp= date("i:s");
  $_SESSION[$AKCE]->stamp= $stamp;
  session_write_close();
  display("STAMP {$vars->faze}: $stamp ... {$_SESSION[$AKCE]->stamp}");
  return $stamp;
}
function zvyraznit($msg,$ok=0) { // ------------------------------------------------------ zvyraznit
  $color= $ok ? 'green' : 'red';
  return "<b style='color:$color'>$msg</b>";
}
function simple_mail($replyto,$address,$subject,$body,$cc='') { // --------------------- simple mail
# odeslání mailu
# $MAIL=0 zabrání odeslání, jen zobrazí mail v trasování
  global $api_gmail_user, $api_gmail_pass, $api_gmail_name, $MAIL, $TEST, $mailbox;
//  global $trace;
  $msg= '';
  if ($TEST || !$MAIL) {
    $mailbox= "<h3>Simulace odeslání mailu z adresy $api_gmail_name &lt;$api_gmail_user&gt;</h3>"
        . "<b>pro:</b> $address "
        . "<br><b>předmět:</b> $subject"
        . "<p><b>text:</b> $body</p>";
//    if ($TEST) $trace.= "<hr>$mailbox<hr>";
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
