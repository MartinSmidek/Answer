<?php
/**
# pilotní verze online přihlašování pro YMCA Setkání (jen obnovy a LK MS YS)
# debuger je lokálne nastaven pro verze PHP: 7.2.33 - musí být ručně spuštěn Chrome

# lze parametrizovat takto:
#
# p_pro_par     -- obecně manželský pár, výjimečně s dítětem, případně pečovatelem
#+p_pro_LK      -- Letní kurz MS pro manželský pár i s dětmi, případně pečovatelem, s citlivými údaji
# p_upozorneni  -- vyžaduje se osobní souhlas s uvedenými podmínkami účasti na LK
# p_rod_adresa  -- ... umožňuje se úprava rodinných údajů (adresa, SPZ, datum svatby)
# p_obcanky     -- ... umožňuje se úprava osobních údajů (občanka, telefon)
# p_obnova      -- pro obnovu: neúčastník aktuálního LK bude přihlášen jako náhradník
# p_registrace  -- je povoleno registrovat se s novým emailem
# p_souhlas     -- vyžaduje se společný souhlas se zpracováním uvedených osobních údajů
# p_dokument    -- vytvořit PDF a uložit jako dokument k pobytu
*/

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
//$TEST= 3; // 3 = simulace db + trasování + přeskok loginu

//if ($TEST==3) $testovaci_mail= 'martin@smidek.eu';
//if ($TEST==3) $testovaci_mail= 'lukcas@seznam.cz';
//if ($TEST==3) $testovaci_mail= 'tholik@volny.cz';
//if ($TEST==3) $testovaci_mail= 'kancelar@setkani.org';
//if ($TEST==3) $testovaci_mail= 'petr.glogar@seznam.cz';
}
else {
  die("Online přihlašování není ještě k dospozici."); 
}
# ------------------------------------------ zpracování jednoho formuláře
start();                // nastavení $vars podle SESSION
connect_db();           // napojení na databázi a na Ezer 
read_akce();            // načtení údajů o akci z Answeru včetně popisu získávaných položek
debug($akce);
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
# všechny hodnoty v cleni, rodina, pobyt jsou v databázovém tvaru
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
  //  $trace.= debugx($_POST,'$_POST - start');
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
     $ezer_path_root, $abs_root, $answer_db, $mysql_tracked, $trace, $totrace, 
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
    $trace.= debugx($_POST,'$_POST - start');
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
      nezbytné abyste dotazník pečlivě a pravdivě vyplnili.";
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
      'role'      => [''=>'role v rodině?','a'=>'manžel','b'=>'manželka','d'=>'dítě','p'=>'jiný vztah'],
      'cirkev'    => map_cis('ms_akce_cirkev','zkratka'),
      'vzdelani'  => map_cis('ms_akce_vzdelani','zkratka'),
    ];
  // definice obsahuje:  položka => [ délka , popis , formát ]
  //   X => pokud jméno položky začíná X, nebude se ukládat, jen zapisovat do PDF
  //   * => pokud popis začíná hvězdičkou bude se údaj vyžadovat (hvězdička za zobrazí červeně)
  //        je to ale nutné pro každou položku naprogramovat 
  $p_fld= [ // zobrazené položky tabulky POBYT, nezobrazené: id_pobyt, web_changes
      'pracovni'  =>['64/4','sem prosím napište případnou dietu, nebo jinou úpravu stravy '
          . '- poloviční porci, odhlášení jídla apod.','area']
    ];
  $r_fld= [ // položky tabulky RODINA
      'ulice'     =>[15,'* ulice nebo č.p.',''],
      'psc'       =>[ 5,'* PSČ',''],
      'obec'      =>[20,'* obec/město',''],
      'spz'       =>[12,'SPZ auta na akci',''],
      'datsvatba' =>[ 9,'* datum svatby','date'],
    ];
  $o_fld= array_merge(
    [ // položky tabulky OSOBA
      'spolu'     =>[ 0,'na akci?','check_spolu'],
      'jmeno'     =>[ 7,'* jméno',''],
      'prijmeni'  =>[10,'* příjmení',''],
      'narozeni'  =>[ 9,'* narození','date'],
      'role'      =>[ 9,'* role v rodině?','select'],
      'note'      =>[40,'poznámka (léky, alergie, apod.)','']],
    $akce->p_obcanky ? [
      'obcanka'   =>[11,'číslo občanky',''],
      'telefon'   =>[10,'telefon',''],
      'email'     =>[35,'e-mailová adresa','']] : [],
    $akce->p_pro_LK ? [
      'vzdelani'  =>[20,'vzdělání','select'],
      'zamest'    =>[35,'povolání, zaměstnání',''],
      'zajmy'     =>[35,'zájmy',''],
      'jazyk'     =>[20,'znalost jazyků (Aj, Nj, ...)',''],
      'aktivita'  =>[35,'aktivita v církvi',''],
      'cirkev'    =>[20,'příslušnost k církvi','select'],
      'Xpecuje_o' =>[12,'* bude pečovat o ...',''],
      'Xpovaha'    =>['70/1','popiš svoji povahu','area'],
      'Xmanzelstvi'=>['70/2','vyjádři se o vašem manželství','area'],
      'Xocekavani' =>['70/2','co očekáváš od účasti na MS','area'],
      'Xrozveden'  =>[20,'bylo předchozí manželství?',''],
      'Xupozorneni'=>[ 0,$akce->upozorneni,'check'],
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
  global $AKCE, $r_fld, $vars, $cleni, $post, $msg;
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
      $val= to_sql($r_fld,$fld,$val);
      $rodina= $vars->rodina[key($vars->rodina)];
      if ($val!=$rodina->$fld[0]) {
        $rodina->$fld[1]= $val;
        $vars->kontrola= 0;
      }
    }
    elseif (substr($tag,0,2)=='p_') { // položka do pobyt
      $fld= substr($tag,2);
      $vars->pobyt->$fld= $val;
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
}
# =========================================================================================== PROCES
function todo() { // ------------------------------------------------------------------------- to do
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
  }
  // pokud se cyklus vrátil
  if ($vars->faze=='a') { // => a* | b
    clear_post_but("/email|^.$/");
    do_mail_klienta();  
  }  
}
function do_mail_klienta() { // ----------------------------------------------------- do mail_klenta
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
function do_kontrola_pinu() { // -------------------------------------------------- do kontrola_pinu
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
//            if ($fld=='datsvatba') $val= sql2date($val);
            $vars->rodina[$idr]->$fld= [$val];
          }
        }
        // načti členy rodiny
        db_nacti_cleny_rodiny($idr,$ido);
        $msg= '';
        $vars->faze= 'c';
        $vars->pobyt->web_changes= 0;
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
function do_vyplneni_dat() { // ---------------------------------------------------- do vyplneni_dat
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
  global $akce, $msg, $vars, $cleni, $post, $form, $pdf_html;
  global $errors, $TEST;
  do_begin();
  $mis_souhlas= '';
  $mis_upozorneni= ['a'=>'','b'=>''];
  $pdf_html= '';
  $post->pracovni= $post->pracovni ?? '';
  // -------------------------------------------- počáteční nastavení formuláře
  if (!$vars->form) {
    $vars->form= (object)['par'=>1,'deti'=>2,'pecouni'=>1, // 1=tlačítko, 2=seznam
        'rodina'=>$akce->p_rod_adresa,'pozn'=>1,'souhlas'=>$akce->p_souhlas];
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
      if ($clen->role!='p') continue;
      if ($is>0) $olds++;
      $id= min($id,$is);
    }
    if ($vars->form->pecouni==2 || ($vars->form->pecouni==1 && $olds==0)  ) { 
      // když nejsou žádní staří anebo jsme už přidali nového přidej další
      $id--;
      $vars->cleni[$id]= $cleni[$id]= (object)array
          ('spolu'=>1,'jmeno'=>'','prijmeni'=>'','narozeni'=>'','role'=>'p', 
          'obcanka'=>'','telefon'=>'', 'Xpecuje_o' => '');
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
    foreach ($cleni as $id=>$clen) {
      $role= get('o','role',$id);
      if (!$clen->spolu) continue;
      if (in_array($role,['a','b'])) {
        if ($akce->p_upozorneni && !$clen->Xupozorneni) {
          $neuplne[]= "potvrďte prosím váš souhlas s upozorněním - ".($role=='a'?'muž':'žena');
          $mis_upozorneni[$role]= "class=missing";
        }
      }
      elseif (in_array($role,['p'])) {
        $dite= get('o','Xpecuje_o',$id);
        if (!$dite && get('o','jmeno',$id)) {
          $neuplne[]= "napište o koho bude ".get('o','jmeno',$id)." pečovat";
        }
      }
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
      $neuplne[]= "potvrďte prosím váš souhlas s použitím osobních údajů";
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
    $vars->pobyt->funkce= $ucast;
    $idp= db_open_pobyt();
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
      if ($clen->spolu && $ido>0) {
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
  // ============================================ poskládej prvky formuláře
  // -------------------------------------------- členové rodiny
  $clenove= '';
  if ($vars->form->par) {
    foreach ($cleni as $id=>$clen) {
      $role= get('o','role',$id);
      if (in_array($role,['a','b'])) {
        $upozorneni= $akce->p_upozorneni
          ? "<p class='souhlas'><input type='checkbox' name='{$id}_Xupozorneni' value=''  "
          . (get('o','Xupozorneni',$id) ? 'checked' : '')
          . " {$mis_upozorneni[$role]}><label for='{$id}_Xupozorneni' class='souhlas'>"
          . str_replace('@',$clen->role=='b'?'a':'',$akce->upozorneni)
          . "</label></p>"
          : '';
        $clenove.= "<div class='clen'>" 
            . elem_input('o',$id,['spolu'])
            . elem_text('o',$id,['jmeno','prijmeni',',','narozeni',',','role'])
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
    foreach ($cleni as $id=>$clen) {
      if ($id<0 || $clen->role!='d') continue;
      $clenove.= "<div class='clen'>" 
          . elem_input('o',$id,['spolu'])
          . elem_text('o',$id,['jmeno','prijmeni',',','narozeni',',','role'])
          . elem_input('o',$id,['note'])
          . "</div>";
    }
    foreach ($cleni as $id=>$clen) {
      if ($id>0 || $clen->role!='d') continue;
      $clenove.= "<div class='clen'>" 
          . elem_input('o',$id,['spolu','jmeno','prijmeni','narozeni','role','note'])
          . "</div>";
    }
    $clenove.= "<button type='submit' name='cmd_dalsi_dite'><i class='fa fa-green fa-plus'></i>
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
      $clenove.= '<br>';
      foreach ($cleni as $id=>$clen) {
        if ($id<0 || $clen->role!='p') continue;
        $clenove.= "<div class='clen'>" 
            . elem_input('o',$id,['spolu'])
            . elem_text('o',$id,['jmeno','prijmeni',', ','narozeni',', tel.','telefon'])
            . ($clen->spolu ? elem_input('o',$id,['obcanka']) : elem_text('o',$id,[', OP:','obcanka']) )
//            . elem_input('o',$id,['Xpecuje_o'])
            . "</div>";
      }
      foreach ($cleni as $id=>$clen) {
        if ($id>0 || $clen->role!='p') continue;
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
}
function do_rozlouceni() { // -------------------------------------------------------- do rozlouceni
# (d) rozloučí se s klientem
#  IN: email, zaslany_pin, klient, rodina, cleni
# CMD: konec
#   a - s vymazanými daty 
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
  header('Content-Type:text/html;charset=utf-8');
  header('Cache-Control:no-cache,no-store,must-revalidate');
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
function sql2date($v,$user2sql=0) { // -------------------------------------------------- sql 2 date
# transformace pomoci sql_date1 doplněná o možnost uvést jen rok
  if ($user2sql) { // datum=>sql
    $v= str_replace(' ','',$v);
    $v= $v=='' ? '0000-00-00' : (
        strlen($v)==4 ? "$v-00-00" : sql_date1($v,1)
    );
  }
  else { // sql=>datum (default)
    $v= substr($v,5,5)=='00-00' ? (substr($v,0,4)=='0000' ? '' : substr($v,0,4)) : sql_date1($v,0);
  }
  return $v;
}
function get($table,$fld,$id=0) { // ----------------------------------------------------------- get
# vrátí hodnotu v datovém tvaru - pro rodinnou není nutné udávat id
//  global $p_fld, $r_fld, $o_fld, $options;
  global $vars;
//  $desc= $table=='r' ? $r_fld : ($table=='p' ? $p_fld : $o_fld);
  if ($table=='r' && !$id) $id= key($vars->rodina);
  $pair= $table=='r' ? $vars->rodina[$id] : ($table=='p' ? $vars->pobyt : $vars->cleni[$id]);
  if (isset($pair->$fld)) {
    $v= is_array($pair->$fld) ? ($pair->$fld[1] ?? $pair->$fld[0]) : $pair->$fld;
//    if (isset($desc[$fld])) {
//      list(,,$typ)= $desc[$fld];
//      switch ($typ) {
//      case 'date':
//  //      $v= sql2date($v,0);
//        break;
//      case 'select':
////        $v= $options[$fld][$v] ?? '?';
//        break;
//      }
//    }
  }
  else $v= $fld;
  return $v;
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
function to_sql($desc,$fld,$val) { // ------------------------------------------------------- to_sql
# transformuje hodnotu do databázového tvaru podle údajů v popisu
  if (isset($desc[$fld])) {
    list(,,$typ)= $desc[$fld];
    switch ($typ) {
    case 'date':
      $val= sql2date($val,1);
      break;
    }
  }
  return $val;
}
function set($table,$fld,$val,$id=0) { // ------------------------------------------------------ set
  global $p_fld, $r_fld, $o_fld, $options, $vars;
  $desc= $table=='r' ? $r_fld : ($table=='p' ? $p_fld : $o_fld);
  if ($table=='r' && !$id) $id= key($vars->rodina);
  $pair= $table=='r' ? $vars->rodina[$id] : ($table=='p' ? $vars->pobyt : $vars->cleni[$id]);
  if (!isset($desc[$fld])) {
    $v= $val;
  }
  else {
    list(,,$typ)= $desc[$fld];
    switch ($typ) {
    case 'date':
      $v= sql2date($v,1);
      break;
    case 'select':
      $v= array_search($val,$options[$fld]);
      break;
    }
  }
  if (is_array($pair->$fld)) 
    $pair->$fld[1]= $v;
  else
    $pair->$fld= $v;
}
function elem_text($table,$id,$flds) {
  $html= '';
  foreach ($flds as $fld) {
    $html.= ' '.get_fmt($table,$fld,$id);
  }
  return "<span>$html</span>";
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
      $x= sql2date($v,0);
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
      $html.= "<label class='upper-area'>$title<textarea rows='$rows' cols='$cols' name='$name'>$v</textarea></label>";
      break;
    default:
      $x= $v ? "value='$v'" : ''; // "placeholder='$holder'";
      $html.= "<label class='upper'>$title<input type='text' name='$name' size='$len' $x></label>";
    }
  }
  return $html;
}
# --------------------------------------------------------------------------------- čtení z databáze
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
function db_nacti_cleny_rodiny($idr,$prvni_ido) {
  global $akce, $cleni,$o_fld;
  $nodb= [];
  $flds= '';
  foreach (array_keys($o_fld) as $f) {
    if (substr($f,0,1)=='X') { // položka začínající X nepatří do tabulky
      $nodb[]= $f;
      continue;
    }
    if (in_array($f,['spolu','telefon'])) continue; // zvláštní zpracování
    $flds.= ",$f";
  }
//  $nodb= ['-povaha','-manzelstvi','-ocekavani','-rozveden','upozorneni','-pecuje_o'];
//  $flds= implode(',',array_diff(array_keys($o_fld),$nodb,['spolu','telefon']));
  $ro= pdo_query(
    "SELECT id_osoba$flds,IF(kontakt=1,telefon,'') AS telefon
    FROM osoba AS o JOIN tvori USING (id_osoba)
    WHERE id_rodina=$idr AND o.deleted='' AND role IN ('a','b','d','p') 
    ORDER BY IF(id_osoba=$prvni_ido,'0',narozeni)  ");
  while ($ro && ($c= pdo_fetch_object($ro))) {
    $c->spolu= $prvni_ido==$c->id_osoba ? 1 : 0;
  if (($akce->p_pro_par || $akce->p_pro_LK) && in_array($c->role,['a','b'])) {
      $c->spolu= 1;
    }
//    if (isset($c->narozeni)) $c->narozeni= sql2date($c->narozeni);
    $cleni[$c->id_osoba]= $c;
    // doplň prázdné hodnoty 
    foreach ($nodb as $f) {
      $cleni[$c->id_osoba]->$f= '';
    }
  }
}
# -------------------------------------------------------------------------------- zápis do databáze
function db_open_pobyt() { // -------------------------------------------------------- db open_pobyt
# vytvoř pobyt - potřebujeme dále jeho ID 
  global $errors, $akce, $vars, $web_changes;
  // web_changes= 1/2 pro INSERT/UPDATE pobyt+spolu | 4/8 pro INSERT/UPDATE osoba | 16/32 pro INSERT/UPDATE rodina
  $web_changes= 1; 
  $ida= $akce->id_akce;
  $idr= key($vars->rodina);
  $chng= array(
    (object)array('fld'=>'id_akce',    'op'=>'i','val'=>$ida),
    (object)array('fld'=>'i0_rodina',  'op'=>'i','val'=>$idr),
    (object)array('fld'=>'web_changes','op'=>'i','val'=>1),
    (object)array('fld'=>'web_zmena',  'op'=>'i','val'=>date('Y-m-d'))
  );
  foreach ($vars->pobyt as $f=>$vals) {
    if (is_array($vals) && isset($vals[1]) && $vals[1]!=$vals[0]) {
      $chng[]= (object)['fld'=>$f, 'op'=>'i','val'=>$vals[1]];
    }
  }
  $idp= _ezer_qry("INSERT",'pobyt',0,$chng);
  if (!$idp) $errors[]= "Nastala chyba při zápisu do databáze (p)"; 
  $vars->pobyt->id_pobyt= $idp;
  return $idp;
}
function db_novy_clen_na_akci($pobyt,$rodina,$novy) { // ---------------------- db novy_clen_na_akci
# přidání dítěte do rodiny a na akci
  global $akce, $errors, $web_changes;
  // web_changes= 1/2 pro INSERT/UPDATE pobyt+spolu | 4/8 pro INSERT/UPDATE osoba | 16/32 pro INSERT/UPDATE rodina
  $chng= [];
  $ido= 0;
  $narozeni= sql_date($novy->narozeni,1);
  // nejprve podle jména a data narození, jestli už není v evidenci --- jen pro roli p
  if ($novy->role=='p') {
    list($pocet,$ido,$access)= select('COUNT(*),id_osoba,access','osoba',
        "deleted='' AND jmeno='$novy->jmeno' AND prijmeni='$novy->prijmeni' AND narozeni='$narozeni' ");
    if ($pocet==1) {
      // asi známe - přidáme jako poznámku do pracovní poznámky pobytu
      if ($access!=$akce->org) {
        // rozšíříme povolení
        $chng[]= (object)array('fld'=>'access', 'op'=>'u','old'=>$access,'val'=>$access|$akce->org);
      }
    }
    // zpráva do pracovní poznámky
    $p_old= get_p('pracovni');
    $p_new= $p_old ? "$p_old ... $novy->jmeno $novy->prijmeni bylo nalezeno jako ID=$ido" : $p_old;
    _ezer_qry("UPDATE",'pobyt',$pobyt,
        [(object)['fld'=>'pracovni', 'op'=>'u','old'=>$p_old,'val'=>$p_new]]);
  }
  if (!$ido) {
    set('p','web_changes',get('p','web_changes')|4);
    $sex= select('sex','_jmena',"jmeno='$novy->jmeno' LIMIT 1");
    $sex= $sex==1 || $sex==2 ? $sex : 0;
    $chng= array(
      (object)['fld'=>'jmeno',    'op'=>'i','val'=>$novy->jmeno],
      (object)['fld'=>'prijmeni', 'op'=>'i','val'=>$novy->prijmeni],
      (object)['fld'=>'sex',      'op'=>'i','val'=>$sex],
      (object)['fld'=>'narozeni', 'op'=>'i','val'=>$narozeni],
      (object)['fld'=>'access',   'op'=>'i','val'=>$akce->org],
      (object)['fld'=>'web_zmena',  'op'=>'i','val'=>date('Y-m-d')]
    );
    $ido= _ezer_qry("INSERT",'osoba',0,$chng);
    if (!$ido) $errors[]= "Nastala chyba při zápisu do databáze (o)"; 
    $web_changes|= 4; 
    $chng= []; // další položky přidáme přes UPDATE
  }
  if ($novy->telefon??0) {
    $chng[]= (object)array('fld'=>'kontakt', 'op'=>'i','val'=>1);
    $chng[]= (object)array('fld'=>'telefon', 'op'=>'i','val'=>$novy->telefon);
  }
  if ($novy->obcanka??0) {
    $chng[]= (object)array('fld'=>'obcanka', 'op'=>'i','val'=>$novy->obcanka);
  }
  if (count($chng) && !count($errors)) {
    if (!_ezer_qry("UPDATE",'osoba',$ido,$chng)) 
      $errors[]= "Nastala chyba při zápisu do databáze (o)"; 
    if ($pocet==1) $web_changes|= 8; 
  }
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
    set('p','web_changes',get('p','web_changes')|2);
    $chng= array(
      (object)['fld'=>'id_pobyt',  'op'=>'i','val'=>$pobyt],
      (object)['fld'=>'id_osoba',  'op'=>'i','val'=>$ido],
      (object)['fld'=>'s_role',    'op'=>'i','val'=>2], // dítě
      (object)['fld'=>'web_zmena', 'op'=>'i','val'=>date('Y-m-d')]
    );
    $ids= _ezer_qry("INSERT",'spolu',0,$chng);
    if (!$ids) $errors[]= "Nastala chyba při zápisu do databáze (s)"; 
  }
  return count($errors);
}
function db_clen_na_akci($idp,$ido,$s_role) { // ----------------------------------- db clen_na_akci
  global $errors, $cleni, $web_changes;
  // web_changes= 1/2 pro INSERT/UPDATE pobyt+spolu | 4/8 pro INSERT/UPDATE osoba | 16/32 pro INSERT/UPDATE rodina
  $web_changes= 1; 
  set('p','web_changes',get('p','web_changes')|2);
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
    if (substr($f,0,1)=='X') continue; // položka začínající X nepatří do tabulky
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
    $web_changes|= 8; 
  }
}
function do_oprav_rodinu($idr) { // ------------------------------------------------ do oprav_rodinu
# oprav rodinné údaje 
  global $vars, $errors, $web_changes;
  // web_changes= 1/2 pro INSERT/UPDATE pobyt+spolu | 4/8 pro INSERT/UPDATE osoba | 16/32 pro INSERT/UPDATE rodina
  $chng= [];
  $idr= key($vars->rodina);
  $rodina= $vars->rodina[$idr];
  foreach ($rodina as $f=>$vals) {
    if (substr($f,0,1)=='-') continue; // položka začínající - nepatří do tabulky
    if (is_array($vals) && isset($vals[1]) && $vals[1]!=$vals[0]) {
      $chng[]= (object)['fld'=>$f, 'op'=>'u','old'=>$vals[0],'val'=>$vals[1]];
    }
  }
  if (count($chng)) {
    if (!_ezer_qry("UPDATE",'rodina',$idr,$chng)) 
      $errors[]= "Nastala chyba při zápisu do databáze (r)"; 
    $web_changes|= 32; 
  }
}
function db_close_pobyt() { // ------------------------------------------------------ db close_pobyt
  global $errors, $vars, $web_changes;
  $chng= array(
    (object)array('fld'=>'web_changes','op'=>'i','val'=>$web_changes),
    (object)array('fld'=>'web_zmena',  'op'=>'i','val'=>date('Y-m-d'))
  );
  foreach ($vars->pobyt as $f=>$vals) {
    if (is_array($vals) && isset($vals[1]) && $vals[1]!=$vals[0]) {
      $chng[]= (object)['fld'=>$f, 'op'=>'u','old'=>$vals[0],'val'=>$vals[1]];
    }
  }
  if (!_ezer_qry("UPDATE",'pobyt',$vars->pobyt->id_pobyt,$chng)) 
    $errors[]= "Nastala chyba při zápisu do databáze (p)"; 
}
function gen_html($to_save=0) {
# vygeneruje textový tvar přihlášky
  global $akce, $vars, $cleni;
  $ted= date("j.n.Y H:i:s");
  $html= '';
  $html.= "<h3 style=\"text-align:center;\">Údaje z online přihlášky na akci \"$akce->nazev\"</h3>";
  $html.= "<p style=\"text-align:center;\"><i>vyplněné $ted a doplněné dříve svěřenými osobními údaji</i></p>";
  // odlišení muže, ženy a dětí
  $sebou= []; $deti= $del_d= ''; $chuvy= '';
  foreach (array_keys($cleni) as $id) {
    switch (get('o','role',$id)) {
      case 'a': $idm= $id; break;
      case 'b': $idz= $id; break;
      case 'd': 
        $jmeno= get('o','jmeno',$id).' '.get('o','prijmeni',$id);
        $narozeni= get_fmt('o','narozeni',$id);
        $deti.= "$del_d$jmeno, $narozeni";
        $del_d= ', ';
        if (get('o','spolu',$id))
          $sebou[]= (object)['jmeno' => $jmeno, 'narozeni' => $narozeni, 'note' => get('o','note',$id)]; 
        break;
      case 'p': 
        if (get('o','spolu',$id)) {
          $jmeno= get('o','jmeno',$id).' '.get('o','prijmeni',$id);
          $narozeni= get_fmt('o','narozeni',$id);
          $dite= get('o','Xpecuje_o',$id);
          $adresa= get('o','ulice',$id).', '.get('o','psc',$id).' '.get('o','obec',$id);
          $chuvy.= "<br>$jmeno, $narozeni, bydliště: $adresa, <b>pečuje o: $dite</b>";
        }
        break;
    }
  }
  $m= $cleni[$idm];
  $z= $cleni[$idz];
  // redakce osobních údajů
  $udaje= [
    ['Jméno a příjmení',$m->jmeno.' '.$m->prijmeni,$z->jmeno.' '.$z->prijmeni], 
    ['Datum narozeni',  get('o','narozeni',$idm), get('o','narozeni',$idz) ],
    ['Telefon',         get('o','telefon',$idm), get('o','telefon',$idz) ],
    ['E-mail',          get('o','email',$idm), get('o','email',$idz)], 
    ['Č. OP nebo cest. dokladu', get('o','obcanka',$idm), get('o','obcanka',$idz) ],
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
  $adresa= get('r','ulice').', '.get('r','psc').' '.get('r','obec');
  $html.= "<tr><th>Adresa, PSČ</th><td colspan=\"4\">$adresa</td></tr>";
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
  $jm= get('o','jazyk',$idm); $jm= $jm ? ", $jm" : '';
  $jz= get('o','jazyk',$idz); $jz= $jz ? ", $jz" : '';
  $udaje= [
    ['Vzdělání',              get_fmt('o','vzdelani',$idm), get_fmt('o','vzdelani',$idz)],
    ['Povolání, zaměstnání',  get('o','zamest',$idm), get('o','zamest',$idz)],
    ['Zájmy, znalost jazyků', get('o','zajmy',$idm).$jm, get('o','zajmy',$idz).$jz],
    ['Popiš svoji povahu',    get('o','Xpovaha',$idm), get('o','Xpovaha',$idz)],
    ['Vyjádři se o vašem manželství',  get('o','Xmanzelstvi',$idm), get('o','Xmanzelstvi',$idz)],
    ['Co od účasti očekávám', get('o','Xocekavani',$idm), get('o','Xocekavani',$idz)],
    ['Příslušnost k církvi',  get_fmt('o','cirkev',$idm), get_fmt('o','cirkev',$idz)],
    ['Aktivita v církvi',     get('o','aktivita',$idm), get('o','aktivita',$idz)],
  ];
  $th= "th colspan=\"2\"";
  $html.= "<table $table_attr><tr><th></th><$th>Muž</th><$th>Žena</th></tr>";
  $td= "td colspan=\"2\"";
  foreach ($udaje as $u) {
    $html.= "<tr><th>$u[0]</th><$td>$u[1]</td><$td>$u[2]</td></tr>";
  }
  $html.= "<tr><th>Děti (jméno + datum narození)</th><td colspan=\"4\">$deti</td></tr>";
  $html.= "<tr><th>SPZ auta na kurzu</th><td>"
      . get('r','spz')
      . "</td><td>Datum svatby: "
      . get_fmt('r','datsvatba')
      . "</td><td colspan=\"2\">Předchozí manželství? muž: "
      . (get('o','Xrozveden',$idm)?:'-')
      ." žena: "
      . (get('o','Xrozveden',$idz)?:'-')
      . "</td></tr>";
  $html.= "</table>";
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
# --------------------------------------------------------------------------------- správa proměných
function do_begin() {
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
function do_end() {
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
function do_session_restart() {
  global $AKCE;
  unset($_SESSION[$AKCE]);
  session_write_close();
  session_start();
  $_SESSION[$AKCE]= (object)[];
}
function trace_vars($title) {
  global $TEST, $trace, $vars;
  if ($TEST) {
    $vars_dump= [];
    foreach (explode(',',"stamp,faze,history,kontrola,klient,user,chk_souhlas,form,pobyt,rodina,cleni,post") as $v) {
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
  return in_array($ip,[
      '127.0.0.1', // localhost
      '86.49.254.42','80.95.103.170','217.64.3.170', // GAN
      '95.82.145.32'] // JZE
      );
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
