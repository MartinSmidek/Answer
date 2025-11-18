<?php
/**
 * (c) 2025 Martin Smidek <martin@smidek.eu> - online přihlašování pro YMCA Setkání a ASC
 * 
 * 2025-11-15 prihlaska.org.php obsahuje texty a vzhledové prvky specifické pro pořádající organizaci
 * 2025-11-14 do J je přidána možnost dotazu na rodinný stav a na počet dětí
 * 2025-11-11 do logu se přidává název akce kvůli výběru regulárním výrazem
 * 2025-11-11 v případě dotazu na účast se přidá 'mezi náhradníky' pokud tomu tak je
 * 2025-10-25 do R přidáno p_reg_single pro registraci jako single v rodinné přihlášce
 * 2025-10-22 do R přidáno zaškrtávací položka p_zadost s textem veta_zadost
 * 2025-10-21 v $_GET['org'] se při startu předá odkaz na složku s parametrizací podle organizace
 * verze 2025.4
 * 2025-08-29 parametr p_css určuje vzhled vč. loga a (c) podle _cis*akce_prihl_css
 * 2025-08-27 text nad dětmi rozlišen podle typu M|O, oprava s_role dítěte s chůvou
 * 2025-03-27 volání z www.setkani.org s parametrem sid
 * 2025-03-14 přidávání verze J a R
 * 2025-02-27 ostrý provoz pro MS: LK a Obnovy
 * 2025-02-04 sjednocení verze 2025.1 (pro Obnovy) s přihlášením na Letní kurz 
 * 2022-02-20 přidáno sólové přihlášení (typ=J)
 * 
 */
// <editor-fold defaultstate="collapsed" desc=" -------------------------------------------------------- inicializace + seznam emailů pro ladění">
// debuger je lokálne nastaven pro verze PHP: 7.2.33 - musí být ručně spuštěn Chrome
$VERZE= '2025'; // verze přihlášek: rok
$MINOR= '4'; // verze přihlášek: release
$CORR_JS= '1'; // verze přihlášek: oprava JS nebo CSS části pro vynucený reload
$MYSELF= "prihlaska_$VERZE.$MINOR"; // $CORR_JS se používá pro vynucené natažení javascriptu
$TEST_mail= '';
$ezer_version= '3.3';
//error_reporting(E_ALL);
// session
$SID= count($_POST) ? ($_POST['sid']??'') : ($_GET['sid']??'');
if ($SID) {
  session_id($SID);
}
session_start();
//session_start(['cookie_lifetime'=>60*60*24*2]); // dva dny
error_reporting(0);
//ini_set('display_errors', 'On');
set_error_handler(function ($severity, $message, $file, $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
});
// </editor-fold>

// ========================================================================== parametrizace aplikace
  $akce_default= [ // položky které aplikace umí
  // základní typ přihlášky
    'p_typ'         =>  0, // M|O|R|J ... letní kurz | obnova | rodinný | jednotlivci
    'p_pozde'       =>  0, // od teď přihlášené brát jen jako náhradníky
    'p_registrace'  =>  0, // je povoleno registrovat se neznámým emailem
    'p_sleva'       =>  0, // umožnit požádat o slevu
    'p_deti'        =>  0, // pobyt s dětmi
    'p_deti_but'    =>  0, //     zobrazit pro registrované až stiskem tlačítka
    'p_pecouni'     =>  0, // děti mohou mít osobní pečouny
    'p_rod_adresa'  =>  0, // umožnit kontrolu a úpravu rodinné adresy 
    'p_obcanky'     =>  0, // umožnit kontrolu a úpravu číslo obč. průkazu
    'p_kontakt'     =>  0, // umožnit kontrolu a úpravu telefonu a emailu
    'p_souhlas'     =>  0, // vyžadovat souhlas (GDPR) 
  // -- pro MS Obnovy i LK
    'p_strava'      =>  0, // umožnit zadat stravu
    'p_nocleh'      =>  0, // umožnit zadat typ ubytování
    'p_detska_od'   =>  3, // hranice poloviční stravy
    'p_detska_do'   => 10, // hranice poloviční stravy
  // -- jen pro obnovy MS
    'p_obnova'      =>  0, // O: OBNOVA MS: neúčastníky aktuálního LK brát jako náhradníky
  // -- jen pro LK MS
    'p_upozorneni'  =>  0, // M: LETNÍ KURZ MS: vyžadovat akceptaci upozornění
    'p_dokument'    =>  0, // M: LETNÍ KURZ MS: vytvořit PDF a uložit jako dokument k pobytu
  // -- jen pro jednotlivce
    'p_oso_adresa'  =>  0, // zadání osobní adresy, pokud není použije se rodinná ale změna se poptá zda jde o rodinnou nebo jen vlastní
  // -- jen pro registraci na akci J
//    'p_reg_rodina'  =>  0, // je povolena registrace rodiny ... TODO
  // -- jen pro registraci na akci R
    'p_reg_single'  =>  0, // je povolena registrace single 
    'p_akt_deti'    =>  0, // je povolen dotaz na počet dětí
    'p_akt_stav'    =>  0, // je povolen dotaz na aktuální rodinný stav
  // -- pro registraci na akci R|J
    'p_zadost'      =>  0, // speciální žádost typu ano/ne 
    'veta_zadost'   =>  '',// ... a její popis
  ]; 

try {
  $errors= [];
// ========================================================================================== .MAIN.
// rozlišení na volání z příkazové řádky - úvodní s prázdným SESSION nebo po ctrl-r
// a na volání přes AJAX z klientské části

// pro první volání nebo ctrl-r nastav akci
// hodnoty pro test a mail musí být navržené přes GET - uplatní se jen při během přihlášení do Answeru
//   $MAIL:  1 - maily se posílají | 0 - mail se jen ukáže - lze nastavit url&mail=0
//   $TEST:  0 - bez testování | 1 - výpis stavu a sql | 2 - neukládat | 3 - login s testovacím mailem
  $m= null;
  preg_match('/(answer|asc)(-test|)/',$_SERVER["SERVER_NAME"],$m);
  $_TEST= strtolower($m[2])=='-test' ? '_test' : '';
  $domain= strtolower($m[1]);
  global $ORG;
  $virgin= true;
  if (!count($_POST)) { // zde se proběhne jen poprvé
    $virgin= false;
    if (!isset($_GET['akce']) || !isset($_GET['org'])) {
      die("Online přihlašování není k dispozici."); 
    }
    require_once("prihlaska.org.php"); 
    $ORG= $access2org[$_GET['org']];
    $ORG->code= $_GET['org'];
    // detekce varianty: normální nebo testovací - buďto přihlášení do Answer nebo volání z webu
    $ANSWER= $SID ? 1 : ($_SESSION[
        $_TEST ? ($domain=='asc' ? 'asc' : 'dbt') 
               : ($domain=='asc' ? 'asc' : 'db2') 
        ]['user_id']??0);
    // odvození požadavku na test a ostrý mail
    $TEST= $_GET['test']??0 ? ($ANSWER?(0+$_GET['test']):0) : 0;
    $MAIL= $_GET['mail']??1 ? 1 : ($ANSWER?0:1);
    initialize($_GET['akce']); // přenese TEST i MAIL
  }
  if (!isset($_SESSION['A_akce'])) { session_reset(); }
  if (!isset($_SESSION['A_akce']) ) {
    die("Online přihlašování není možné, zkontrolujte prosím správnost adresy."); 
  }
  $AKCE= "A_{$_SESSION['A_akce']}";
  $vars= $_SESSION[$AKCE]??(object)[];
  $TEST= $vars->TEST;
  $MAIL= $vars->MAIL;
  $ORG=  $vars->ORG;
  $ANSWER= $vars->ANSWER; // na startu bylo přihlášení
//  $_TEST= '_test'; $TEST= $ANSWER= 1; $MAIL= 0; // ---------------------- SETKANI.ORG ----------------

  connect_db();           // napojení na databázi a na Ezer 
  read_akce();            // načtení údajů o akci z Answeru 
  if (!isset($akce->p_typ) ) {
    die("Online přihlašování pro tuto akce není možné."); 
  }

  polozky();              // popis položek a jiných textů
  if ( count($_POST) ) {
    // volání přes AJAX z existující klientské části
    $fce= $_POST['cmd'];
    $args= $_POST['args']??[];
    $call= '';
    if ($TEST) {
      $call= "function <b>$fce</b>";
      foreach ($args as $name=> $value) {
        if ($name=='*')
          $call.= "<br>$name";
        else 
          $call.= "<br>$name=$value";
      }
    }
    $DOM= (object)['php_function'=>$fce,'trace'=>'','form'=>''];
    if ( function_exists($fce)) {
      $vars= $_SESSION[$AKCE];
      call_user_func_array($fce,$args); // modifikuje $DOM
      if ($vars->id_akce??0)
        $_SESSION[$AKCE]= $vars;
    }
    else {
      $call.= " <b style='color:red'>neexistuje</b>";
      $DOM->errorbox= ['show',$call];
      $errors[]= $call;
    }
    // případné trasování
    if ($TEST) {
      global $trace;
      $trace= $trace??'' ? "<hr>$trace" : '';
      if (count($errors)) $trace.= '<hr><span style="color:red">'.implode('<hr>',$errors).'</span>';
      if (isset($y->error)) $trace.= '<hr>'.nl2br($y->error);
      $trace.= '<hr>'.nl2br($y->qry??'');
  //    unset($vars->DOM->trace); // zahodíme staré trace
      $dump= debugx($vars,'$vars');
      $dump.= debugx($akce,'$akce');
      $DOM->trace= "$call$trace<hr>$dump";
    }
    // pokračujeme v JS
    header('Content-type: application/json; charset=UTF-8');
    $yjson= json_encode($DOM);
    echo $yjson;
  }
  else {
    // volání z příkazové řádky vytvoří novou klientskou část 
    page();
    // po vytvoření klienta je volána funkce start()
  }
}
catch (Throwable $e) {
  $msg= $e->getMessage();
  $line= ''; $del= '';
  $tline= ''; $tdel= '';
  $traceback= $e->getTrace();
  $max_depth= 12;
  $max_string= 12;
  for ($depth=0; $depth<=min($max_depth,count($traceback)-1); $depth++ ) {
    $L= $traceback[$depth]['line']??'';
    $F= $traceback[$depth]['function'];
    $args= '';
    if ($F=='{closure}') $F= '';
    else {
      $A= $traceback[$depth]['args'];
      $dela= '';
      for ($i=0; $i<count($A); $i++) {
        $Ai= $A[$i];
        if ( is_string($Ai) ) {
          $arg= mb_substr(htmlspecialchars($Ai,ENT_NOQUOTES,'UTF-8'),0,$max_string)
              .(mb_strlen($Ai)>$max_string?'...':'');
        }
        elseif (is_numeric($Ai))
          $arg= $Ai;
        else
          $arg= '?';
        $args.= "$dela$arg";
        $dela= ',';
      }
      $args= "($args)";
    }
    $line.= "$del $L:$F$args "; $del= '<';
    $tline.= "$tdel $L: $F $args "; $tdel= '<br>';
  }
  if (preg_match('/unknown DOM/',$msg)) {
    $errpos= $msg;
  }
  else {
    $errpos= "$msg na řádku $tline";
  }
  append_log("<b style='color:red'>CATCH</b> ".str_replace('<br>',' | ',$errpos));
  $errmsg= "Omlouváme se, během práce programu došlo k nečekané chybě."
  . "<br><br>Přihlaste se na akci  mailem zaslaným na {$ORG->info->mail}."
  . ($akce??0 ? "<br>$akce->opravit_chybu" : '')
  . ($TEST ? "<hr><i>příčina chyby je v logu, zde se vypíše jen pokud bylo zapnuto trasování ...</i>"
      . "<br>$errpos" : '');
  echo $errmsg;
  exit;
}
// -------------------------------------------------------------------- obnova počátečního nastavení
function initialize($id_akce) { 
# zobrazí id.mail a cmd.zadost_o_pin, skryje vše ostatní a zapomene všechny hodnoty
  global $DOM, $DOM_default, $AKCE, $vars, $TEST, $MAIL, $ORG, $ANSWER;
  do_session_restart();
  if ($id_akce) {
    $_SESSION['A_akce']= $id_akce;
    $AKCE= "A_$id_akce"; // ID akce pro SESSION
    $_SESSION[$AKCE]= (object)[
      'id_akce'=>$id_akce,
      'TEST'=>$TEST, 'MAIL'=>$MAIL, 'ORG'=>$ORG,
      'ANSWER'=>$ANSWER,  // při zahájení (nebo po ctrl-r) bylo přihlášeno do Answeru
      'ido'=>0, 'idr'=>0, 'pin'=>'', 'klient'=>'', // ověřený klient jeho id je v prihlaska.id_osoba
      'form'=>(object)[],
  //    'DOM'=>$DOM_default,
      'cleni'=>[],
      'rodina'=>[],
      'pobyt'=>(object)[],
      'chk_souhlas'=>0,
    ];
    // pro volání přes AJAX
    $vars= $_SESSION[$AKCE];
    $DOM= $DOM_default;
  }
  else {
    $vars= (object)[];
    $DOM= (object)[];
  }
}
function polozky() { // -------------------------------------------------------------------- položky
  global $akce, $MAIL, $TEST, $TEST_mail, $TEXT, $DOM_default, $ORG, 
         $options, $sub_options, $p_fld, $r_fld, $o_fld;
  // popisné texty
  $TEXT= (object)[
      'usermail_nad1' => 
          'Abychom ověřili, že se přihlašujete právě vy, napište svůj mail, pošleme na něj přihlašovací PIN.',  
      'usermail_pod1' => 
          typ_akce('MO') ? '<i>Přihláška obsahuje otázky určené oběma manželům - je potřeba, abyste ji vyplňovali společně.</i>' : '',  
      'usermail_nad2' => 
          'Na uvedený mail vám byl zaslán PIN, opište jej vedle své mailové adresy.
           <br><i>(pokud PIN nedošel, podívejte se i složek Promoakce, Aktualizace, Spam, ...)</i>',  
      'usermail_nad3' => 
          "Tento mail v evidenci $ORG->name nemáme, tato akce předpokládá, že jste se již nějaké naší 
            akce zúčastnil/a, přihlaste se prosím pomocí toho, který jste tehdy použil/a",
      'usermail_nad4' => 
          'Tento mail máme na základě předchozích přihlášek a účastí na našich akcích uvedený 
           ve více souvislostech - zvolte prosím správnou možnost.',
      'usermail_nad5' => 
          "Tento mail v evidenci $ORG->name nemáme, pokud jste se již nějaké naší akce zúčastnili, 
           přihlaste se prosím pomocí mailu, který jste tehdy použil/a 
           - pokud s námi budete poprvé, pokračujte registrací.",
      'osoby_nad1' => 
          typ_akce('MOR') ? 'Poznačte, koho na akci přihlašujete. Zkontrolujte a případně upravte zobrazené údaje.' : (
          typ_akce('J') ? 'Zkontrolujte a případně doplňte své údaje.' : ''),  
      'deti' =>
          typ_akce('M')
          ? "<p><b>Děti</b> (zapište prosím i ty vaše, které necháváte doma)."
            . ( $akce->p_pecouni 
              ? '<br>Pečovatele pro dítě přidávejte, pouze pokud nevyužijete služeb našeho kolektivu pečovatelů.</p>'
              : '')
          : "<p><b>Děti</b> (zapište prosím všechny vaše děti)
             <br>Hlídání pro děti nezajišťujeme, pokud potřebujete vzít např. kojence s sebou, 
             musíte si pro něj zajistit a zaplatit pečovatele. 
             Toto dítě i pečovatele prosím také zapište do přihlášky.</p>",
      'strava' =>
          "<b>Objednáváme stravu:</b> snídani, oběd, večeři (dětem od $akce->p_detska_od "
          . "do $akce->p_detska_do let poloviční porce);"
          . '<br>nebo ji můžete jmenovitě upravit, případně vybrat dietu.',
      'rozlouceni1' => 
          'Přejeme Vám hezký den.',
      'rozlouceni2' => 
          'Přejeme Vám příjemný pobyt.',
    ];

  $DOM_default= (object)[ // pro start aplikace s prázdným SESSION
      // počáteční stav
      'user'=>'hide',
      'usermail'=>'show', 'email'=>['show',$TEST_mail ?: 'empty','enable'], 'pin'=>'hide', 
      'zadost_o_pin'=>'show', 'kontrola_pinu'=>'hide',
      'usermail_nad'=>$TEXT->usermail_nad1, 'usermail_pod'=>$TEXT->usermail_pod1, 
      'pin'=>'hide', 'kontrola_pinu'=>'hide', 'registrace'=>'hide', 'form'=>'hide',
      // testování
      'info'=> ((($_SERVER['HTTP_X_REAL_IP'] ?? $_SERVER['REMOTE_ADDR'])=='127.0.0.1' ? 'localhost ... ' : '')
        . ($MAIL ? '' : 'simulace mailů').($TEST>1 ? ', bez zápisu' : '')) ?: 'hide',
      'mailbox'=>'hide', 
      'errorbox'=>'hide',
      'alertbox'=>'hide',
    ];

  $options= [
      'role'      => [''=>'vztah k rodině?','a'=>'manžel','b'=>'manželka','d'=>'naše dítě','p'=>'jiný vztah'],
      'role_dite' => [''=>'vztah k rodině?','d'=>'naše dítě','p'=>'jiný vztah'],
      'Xstav'     => [''=>'?',1=>'svobodný',2=>'ženatý',3=>'rozvedený',4=>'vdovec'],
      'cirkev'    => [''=>'něco prosím vyberte',23=>'křesťan',1=>'katolická',2=>'evangelická',7=>'bratrská',
                      4=>'apoštolská',19=>'husitská',22=>'metodistická',18=>'baptistická',5=>'adventistická',
                      24=>'jiná',21=>'hledající',3=>'bez příslušnosti',16=>'nevěřící'],
      'vzdelani'  => [''=>'něco prosím vyberte',1=>'ZŠ',4=>'vyučen/a',2=>'SŠ',33=>'VOŠ',3=>'VŠ',16=>'VŠ student'],
      'funkce'    => map_cis_2('ms_akce_funkce','zkratka'),
      'Xvps'      => [''=>'něco prosím vyberte',1=>'počítáme se službou VPS',
                      2=>'raději bychom byli v "odpočinkové" skupince'],
      'Xporce'    => [1=>'celá',2=>'poloviční'],        // ['C','P']
      'Xdieta'    => [1=>'bez diety',2=>'bezlepková'],  // ['-','BL']
      // texty musí být stejné jako v prihl_show_2025
      'Xnocleh'   => [1=>'lůžkoviny',2=>'spacák',3=>'bez lůžka',4=>'spí jinde'],  
    ];
  // případné zúžení noclehů
  if ($akce->p_nocleh) {
    $noc= [0,'L','S','Z','-'];
    for ($i= 1; $i<=4; $i++) {
      if (strpos($akce->p_nocleh,$noc[$i])===false) {
        unset($options['Xnocleh'][$i]);
      }
    }
  }
  $options['cirkev']['']= 'něco prosím vyberte';
  $options['vzdelani']['']= 'něco prosím vyberte';
  // v $sub_options je převodní tabulka plné->zúžené podle _cis.ikona
  $sub_options= [
      'cirkev'    => map_cis_2('ms_akce_cirkev','ikona'),
      'vzdelani'  => map_cis_2('ms_akce_vzdelani','ikona'),
    ];
  // definice obsahuje:  položka => [ délka , popis , formát, u osob možné role, role u kterých je * nepovinná ]
  //   X => pokud jméno položky začíná X, nebude se ukládat, jen zapisovat do PDF
  //   * => pokud popis začíná hvězdičkou bude se údaj vyžadovat (hvězdička za zobrazí červeně)
  //        pokud bude zobrazováno jako text a nebude definováno, zobrazí se jako input
  //        je to ale nutné pro každou položku naprogramovat 
  $p_fld= array_merge( // zobrazené položky tabulky POBYT, nezobrazené: id_pobyt
    [ 'pracovni'    =>['64/4','sem prosím napište vzkaz organizátorům, např. informace, '
        . 'které nebylo možné nikam napsat','area'], // pro MO pobyt.pracovni, pro RJ pobyt.poznamka
      'funkce'      =>[0,'funkce na akci','select'],
      'sleva_zada'  =>[ 0,'Žádám o poskytnutí slevy','check_sleva'],
      'sleva_duvod' =>['64/4','* napište, proč žádáte o slevu','area'],
      'Xsouhlas'    =>[ 0,'*'.$akce->form_souhlas,'check_souhlas']],
    typ_akce('RJ') && ($akce->p_zadost??0) ? [
      'zadost'      =>[ 0,$akce->veta_zadost,'check_sleva'],
    ] : [],
    typ_akce('MO') ? [
      'Xvps'        =>[15,'* služba na kurzu','select'], // bude vložena jen pro neodpočívající VPS
    ] : [],
    typ_akce('MO') && ($akce->p_strava??0) ? [
      'Xstrava_s'   =>[ 0,'snídaně','check'],
      'Xstrava_o'   =>[ 0,'obědy','check'],
      'Xstrava_v'   =>[ 0,'večeře','check'],
    ] : []
  );
  $r_fld= array_merge(
    [ // položky tabulky RODINA
      'nazev'     =>[15,'* název rodiny',''],
      'ulice'     =>[15,'* ulice a č.or. NEBO č.p.',''],
      'psc'       =>[ 5,'* PSČ',''],
      'obec'      =>[20,'* obec/město',''],
      'stat'      =>[ 0,'stát',''],
      'telefony'  =>[ 0,'',''], // jen kvůli případnému odkazu z osoba
      'emaily'    =>[ 0,'',''], // jen kvůli případnému odkazu z osoba
      'spz'       =>[12,'SPZ auta na akci',''],
      'rozvod'    =>[ 0,'',''], // jen kvůli případnému dotazu p_akt_stav
    ],
    typ_akce('MO') ? [
      'r_umi'      =>[ 0,'seznam odborností','x'], // podle answer_umi např. 1=VPS
    ] : [],
    typ_akce('M') ? [
      'datsvatba' =>[ 9,'* datum svatby','date'],
      'r_ms'       =>[12,'počet účastí na jiném kurzu MS než YMCA Setkání či YMCA Familia','number'],
      'r_umi'      =>[ 0,'seznam odborností','x'], // podle answer_umi např. 1=VPS
    ] : []
  );
  $o_fld= array_merge(
    [ // položky tabulky OSOBA
      'spolu'     =>[ 0,'pojede<br />na akci','check_spolu','abdp'],
      'jmeno'     =>[ 8,'* jméno','','abdp'],
      'prijmeni'  =>[10,'* příjmení','','abdp'],
      'rodne'     =>[10,'* rozená','','ab',typ_akce('RJ') ? 'ab' : 'a'], // pro akce RJ nepovinné
      'narozeni'  =>[10,'* datum narození','date','abdp'],
      'umrti'     =>[10,'rok úmrtí','','abdp'],
      'role'      =>[ 9,'vztah k rodině?','select','abdp'],
      'note'      =>['70/2','poznámka (léky, alergie, apod.)','area','dp'],
      'telefon'   =>[15,'* telefon','','abp', typ_akce('R') ? 'dp' : 'd'],
      'email'     =>[35,'* e-mailová adresa','mail','abp','dp']], // pro pecouny nepovinné
    $akce->p_obcanky ? [
      'obcanka'   =>[11,'* číslo OP nebo pasu','','abp'],
      ] : [],
    $akce->p_oso_adresa ? [
      'adresa'    =>[ 0,'','','abp'],
      'ulice'     =>[15,'* ulice a č.or. NEBO č.p.','','abp'],
      'psc'       =>[ 5,'* PSČ','','abp'],
      'obec'      =>[20,'* obec/město','','abp'],
      'stat'      =>[ 0,'stát','','abp'],
      ] : [],
    typ_akce('J') && ($akce->p_akt_stav??0) ? [
      'Xstav'     =>[20,'* rodinný stav','select','ab'],
      'Xdeti'     =>[5,'počet dětí','','ab'],
    ] : [],
    typ_akce('M') ? [
      'vzdelani'  =>[20,'* vzdělání','sub_select','ab'],
      'zamest'    =>[35,'* povolání, obor, ve kterém pracujete/budete pracovat','','ab'],
      'zajmy'     =>[35,'* zájmy','','ab'],
      'jazyk'     =>[20,'znalost jazyků (Aj, Nj, ...)','','ab'],
      'aktivita'  =>[35,'aktivita v církvi, ve společnosti','','ab'],
      'cirkev'    =>[25,'* vztah ke křesťanství/církev','select','ab'],
//      'Xpecuje_o' =>[12,'* bude pečovat o ...','','p'],
      'Xpovaha'    =>['70/2','* popiš svoji povahu','area','ab'],
      'Xmanzelstvi'=>['70/2','* vyjádři se o vašem manželství','area','ab'],
      'Xocekavani' =>['70/2','* co očekáváš od účasti na MS','area','ab'],
      'Xrozveden'  =>[20,'* předchozí manželství? (ne, počet)','','ab'],
      'Xupozorneni'=>[ 0,'*'.$akce->upozorneni,'check','ab'],
    ] : [],
    typ_akce('MO') && ($akce->p_strava??0) ? [
      'o_pecoun'  =>[ 0,'','x','d'],  // =0 tlačítko, >0 id osobního pečovatele
      'o_dite'    =>[ 0,'','x','p'],  // id opečovávaného dítěte
      'Xstrava'   =>[ 0,'','x','abdp'],  // 0 = nedefinovaná, 1 = definovaná
      'Xstrava_s' =>[ 0,'snídaně','check','abdp'],
      'Xstrava_o' =>[ 0,'obědy','check','abdp'],
      'Xstrava_v' =>[ 0,'večeře','check','abdp'],
      'Xporce'    =>[10,'porce','select','abdp'],
      'Xdieta'    =>[15,'dieta','select','abdp'],
    ] : [],
    typ_akce('MO') && ($akce->p_nocleh??0) ? [
      'Xnocleh'   =>[20,'nocleh','select','abdp'],
    ] : [],
  );
  // případné opravy podle akce
  foreach ($akce as $key=>$val) {
    if ($key[0]=='t') {
      $fld= substr($key,3);
      switch ($key[1]) {
        case 'o': $x_fld= &$o_fld; break;
        case 'r': $x_fld= &$r_fld; break;
        case 'p': $x_fld= &$p_fld; break;
        default: continue 2;
      }
      if ($x_fld && isset($x_fld[$fld])) {
        $x_fld[$fld][1]= $val;
      }
    }
  }
} // definice položek formuláře
// ============================================================================== reakce na tlačítka
// každá z následujících funkcí je reakcí na kliknutí na nějaké tlačítko
// a dostává parametry specifikované u tlačítka. 
// Po ukončení funkce odevzdá změněné DOM a čeká se na další reakci uživatele
// --------------------------------------------------------------zahájení nebo pokračování po ctrl-r
function start() { 
# zobrazí id.mail a cmd.zadost_o_pin, skryje vše ostatní
  initialize($_SESSION['A_akce']??0);
} // úvodní obrazovka
// ------------------------------------------------------------------------------------ zadost o pin
function zadost_o_pin($email) { trace();
# pro korektní id.email pošle mail s PINem a zobrazí cmd.kontrola_pinu
# pro nekorektní id.email zobrazí chybu a opakuje
  global $DOM, $ANSWER, $_TEST, $vars;
  $chyby= '';
  $vars->email= trim($email); // pro korespondenci
  $ok= check_mail($vars->email,$chyby);
  if ($ok) {
    if ($_TEST && !$ANSWER) {
      vyber("Jsi v testovací databázi a nejsi nepřihlášen do Answeru. "
          . "Maily se budou opravdu posílat! <b style='color:red'>Ok?</b>",
          ["ANO:poslat_pin","NE"]);
    }
    else {
      poslat_pin();
    }
  }
  if ($chyby) {
    $DOM->usermail_pod= zvyraznit("<p>$chyby</p>");
  }
} // zadost o pin
// -------------------------------------------------------------------------------------- poslat pin
function poslat_pin() { trace();
# pro korektní id.email pošle mail s PINem a zobrazí cmd.kontrola_pinu
# pro nekorektní id.email zobrazí chybu a opakuje
  global $DOM, $TEST, $TEXT, $vars, $akce;
  // zašleme PIN 
  $pin= rand(1000,9999);
  $msg= simple_mail($akce->garant_mail, $vars->email, "PIN ($pin) pro prihlášení na akci",
      "V přihlášce na akci napiš vedle svojí mailové adresy $pin a pokračuj tlačítkem [Ověřit PIN]");
  if ( $msg!='ok' ) {
    $DOM->usermail_pod= zvyraznit("Litujeme, mail s PINem se nepovedlo odeslat, "
        . "přihlaste se prosím na akci jiným způsobem.</p>");
    if ($TEST) $DOM->usermail_pod.= "<p>$msg</p>";
  }
  else { // simple-mail může doplnit DOM->mailbox
    $vars->pin= $pin;
    $DOM->zadost_o_pin= "hide";
    $DOM->email= "disable";
    $DOM->pin= $TEST ? ["show",$pin] : "show"; 
    $DOM->kontrola_pinu= "show";
    $DOM->usermail_nad= $TEXT->usermail_nad2;
    $DOM->usermail_pod= "Byl vám poslán mail";
  }
} // poslat pin
// ----------------------------------------------------------------------------------- kontrola pinu
function kontrola_pinu($pin,$ignorovat_rozepsanou=0) { trace();
# pokud je nesprávný pin ???
# pokud je správný pin zjisti jestli je email známý a jednoznačný
# pokud je známý a jednoznačný vyplň user a připrav formulář přihlášení osob
# pokud je neznámý umožni zadání jiného
# pokud je nejednoznačný umožni zadání jiného
  global $DOM, $TEXT, $akce, $vars;
  if ($pin!=$vars->pin) { // chyba pinu
    $DOM->usermail_pod= zvyraznit("<p>Do mailu jsme poslali odlišný PIN, podívejte se prosím pozorně</p>");
  }
  else { // pin je ok - založíme záznam v tabulce prihlaska
    if (!$ignorovat_rozepsanou) {
      // zjistíme, zda to může být rozpracovaná přihláška
      $open= log_find_saved($vars->email); // uložená přihláška a nejsou přihlášení
      if ($open) { // -------- nalezena rozepsaná přihláška
        // doplní vars: continue, ido, idr
        $DOM->user= ["show","<i class='fa fa-user'></i> <br>$vars->email"];
        vyber("Chcete pokračovat ve vyplňování přihlášky uložené $open?",
            ["ANO:klient:=$vars->ido/$vars->idr,=0","NE:kontrola_pinu:=$pin,=1"]);
        goto end;
      } // nalezena rozepsaná přihláška
    }
    // jinak zapomeň předchozí vyplňování
    $vars->continue= 0;
    // jinak zjistíme, zda jej máme v databázi
    $regexp= "REGEXP '(^|[;,\\\\s]+)$vars->email($|[;,\\\\s]+)'";
    list($pocet,$idors)= select_2(
       "SELECT COUNT(o.id_osoba),GROUP_CONCAT(CONCAT(o.id_osoba,'/',IFNULL(id_rodina,0),'/',IFNULL(role,'-')))
        FROM osoba AS o LEFT JOIN tvori AS t ON t.id_osoba=o.id_osoba AND role IN ('a','b')
        WHERE deleted='' AND kontakt=1 AND email $regexp ORDER BY IFNULL(role,'')");
    display("osoba => $pocet: $idors");
    
    if ($pocet==0) {
      list($pocet,$idors)= select_2(
         "SELECT COUNT(id_osoba),GROUP_CONCAT(CONCAT(id_osoba,'/',id_rodina,'/',role))
          FROM osoba AS o JOIN tvori USING (id_osoba) JOIN rodina AS r USING (id_rodina)
          WHERE o.deleted='' AND r.deleted='' AND kontakt=0 AND emaily $regexp ORDER BY role");
      display("rodina => $pocet: $idors");
    } // úprava $pocet, pokud je to rodinný mail

    if ($pocet==1) { // -------------------------------- známý a jednoznačný mail
      list($ido,$idr)= explode('/',$idors);
      klient("$ido/$idr",1);
    } // známý a jednoznačný mail
    elseif ($pocet==0) { // -------------------------------- neznámý mail
      if ($akce->p_registrace) {
        $DOM->usermail_nad= $TEXT->usermail_nad5;
        $DOM->usermail_pod= "empty";
        $DOM->kontrola_pinu= "hide";
        $DOM->pin= "hide";
        $DOM->registrace= "show";
      }
      else {
        $DOM->usermail_nad= $TEXT->usermail_nad3;
        $DOM->email= 'enable';
        $DOM->kontrola_pinu= "hide";
        $DOM->pin= "hide";
        $DOM->zadost_o_pin= "show";
        $DOM->usermail_pod= 'empty';
        append_log("MAIL? neznámý $vars->email");
      }
    } // neznámý mail
    elseif ($pocet>1) { // -------------------------------- nejednoznačný mail
      $dotazy= [];
      // zkusíme redukovat - pokud je a|b tak další vynecháme
      $cleni= explode(',',$idors);
      $rodic= [];
      foreach ($cleni as $clen) {
        list($ido,$idr,$role)= explode('/',$clen);
        if (in_array($role,['a','b'])) $rodic[]= "$ido/$idr";
      }
      if (count($rodic)==1) 
        klient($rodic[0],1);
      else {
        $dupl= "";
        foreach ($cleni as $clen) {
          list($ido,$idr,$role)= explode('/',$clen);
          if (count($rodic)>1 && !in_array($role,['a','b'])) continue;
          $rodina= $idr ? 'rodina '.select_2('nazev','rodina',"id_rodina=$idr") : 'bez rodiny';
          list($jmeno,$prijmeni)= select_2('jmeno,prijmeni','osoba',"id_osoba=$ido");
          $dotazy[]= "$jmeno $prijmeni:klient:=$ido/$idr,1:$rodina";
          $dupl.= " $ido-$role-$idr";
        }
        vyber($TEXT->usermail_nad4,$dotazy);
        append_log("MAIL? $pocet &times; $vars->email ... $dupl (id_osoba-role-id_rodina)");
      }
    } // nejednoznačný mail      
  }
end:  
} // kontrola_pinu
// -------------------------------------------------------------------------------------- registrace
function registrace($gender) { trace();
# $ano=1/2 pokračujeme s registrací jako muž nebo žena
# $ano=0 pokračujeme s žádostí o jiný mail
# pokud je povolena akce poptá se single/rodina
  global $DOM, $akce;
  $DOM->usermail= 'hide';
  // je umožněna změna typu akce rodina/jednotlivec?
  if (
//      $akce->p_reg_rodina && typ_akce('J') ||  ... TODO
      $akce->p_reg_single && typ_akce('R') ) {
    vyber('Chci registrovat',[
        "jen sebe:registrace_jako:=J,=$gender",
        "také členy rodiny:registrace_jako:=R,=$gender"]);
  }
  else {
    return klient("-$gender/0",1); // nová přihláška + zvolený gender přihlašovaného
  }
} // registrace
// ------------------------------------------------------------------ registrace se změnou typu akce
function registrace_jako($typ_akce,$gender) { trace();
  global $vars; //, $akce;
  $vars->typ_akce= $typ_akce;
//  if ($typ_akce=='R' && $akce->p_oso_adresa) {
//    $akce->p_rod_adresa= 1;
//    $akce->p_oso_adresa= 0;
//  }
  polozky();
  return klient("-$gender/0",1); // nová přihláška + zvolený gender přihlašovaného
}
// ------------------------------------------------------------------------------------------ klient
function klient($idor,$nova_prihlaska=1) { trace();
# $id je nositelem přihlašovacího mailu
  global $DOM, $AKCE, $TEXT, $vars, $akce;
  $idp= 0;
  list($ido,$idr)= explode('/',$idor);
  $ido= intval($ido); $idr= intval($idr);
  if ($ido>0) { // známý klient - je přihlášen na akci?
    $OR= $idr ? "OR i0_rodina=$idr" : '';
    list($jmena,$vars->sex)= 
        select_2("SELECT CONCAT(jmeno,' ',prijmeni),sex FROM osoba WHERE id_osoba=$ido");
    // osobu známe  - zjistíme zda již není přihlášen
    $DOM->user= ["show","<i class='fa fa-user'></i> $jmena<br>$vars->email"];
    list($idp,$funkce,$kdy,$kdo)= select_2("id_pobyt,funkce,IFNULL(kdy,''),IFNULL(kdo,'')",
        "pobyt JOIN spolu USING (id_pobyt) "
        . "LEFT JOIN _track ON klic=id_pobyt AND kde='pobyt' AND fld='id_akce' ",
        "(id_osoba={$ido} $OR) AND id_akce=$akce->id_akce "
        . "ORDER BY id_pobyt DESC LIMIT 1");
    if ($idp) { // ------------------------------- už jsou zapsaní 
      $od_kdy= $kdy ? ' od '.sql_time1($kdy) : '';
      $kym= $kdo=='WEB' ? ' online přihláškou.' : '';
      $jako= $funkce==9 ? ' mezi náhradníky' : '';
      if ($kdo && $kdo!='WEB') {
        list($jmeno,$prijmeni)= select_2('forename,surname','_user',"abbr='$kdo'");
        $kym= ". $jmeno $prijmeni";
      } 
//      display("id_pobyt=$idp");
      log_write('id_pobyt',$idp);
      $DOM->usermail= "hide";
      $DOM->rozlouceni_text= $TEXT->rozlouceni2;
      if (isset($vars->idw_old)) {
        $_SESSION[$AKCE]->id_prihlaska= $vars->idw_old;
      }
      append_log("DOTAZ= ... už je přihlášen$jako $jmena ");
      hlaska("Na tuto akci jste již $od_kdy <br>přihlášeni$jako$kym","start");
      goto end;
    }
  }  // známý klient - je přihlášen na akci?
  log_open($vars->email);  // email je ověřený 
  $DOM->usermail= 'hide';
  $vars->ido= $ido;
  $vars->idr= $idr ?: (typ_akce('MOR') ? -1 : 0) ; // pokud je ido<0 tak je idr= J ? 0 ? MOR : -1
  log_write('id_osoba',$vars->ido);
  log_write('id_rodina',$vars->idr);
  if ($ido>0) { // přihláška známého
    $vars->klient= $jmena;
    append_log(($vars->continue ? str_pad($vars->continue,6,' ',STR_PAD_LEFT) 
        : 'KLIENT')." ... $jmena id_osoba=$ido, id_rodina=$idr");
    log_append_stav('OLD');
    // podle ido,idr nastav počáteční informace o klientovi
    if (typ_akce('MOR'))
      kompletuj_pobyt_par($vars->idr,$vars->ido);
    else
      kompletuj_pobyt_ucastnik($vars->idr,$vars->ido);
    return formular($nova_prihlaska);
  }
  else { // přihláška nového
    $vars->klient= '';
    append_log(($vars->continue ? str_pad($vars->continue,6,' ',STR_PAD_LEFT) 
        : "<b style='color:blue'>REGIST</b>") . " ... $vars->email");
//    append_log("<b style='color:blue'>REGIST</b> ... $vars->email");
    log_append_stav('REG');
    if (typ_akce('MOR'))
      kompletuj_pobyt_par($vars->idr,$vars->ido); // manžel má index -1, manželka -2
    else
      kompletuj_pobyt_ucastnik($vars->idr,$vars->ido);
    set('o','email',$vars->email,$vars->ido); 
    return formular();
  } // přihláška nového
end:
} // klient
// ------------------------------------------------------------------------------ formulář přihlášky
function formular(/*$nova=1*/) { trace();
# připrav prázdný formulář přihlášení osob
# doplň DOM o položky osob
  global $DOM, $vars, $akce;
  // nastavení formuláře
  $new= 1;
  if (($vars->continue??0) /* && $nova==0*/) {
    log_load_changes();   // z uchované přihlášky
    log_write_changes();  // do současné
    $new= 0;
  }
  // specifická část formuláře 
  $form= typ_akce('MO') ? form_MO($new) : (typ_akce('R') ? form_R($new) : form_J($new));
  // doplnění spodních tlačítek a souhlasu GDPR
  $red_x= 'fa fa-times fa-red';
  $red_h= 'fa fa-hourglass-half fa-red';
  // -------------------------------------------- souhlas
  if ($akce->p_souhlas) {
    $form.= "<p class='souhlas'>"
      . "<input type='checkbox' id='p_0_Xsouhlas' value='' onchange='elem_changed(this);'"
        . (get('p','Xsouhlas') ? 'checked' : '') . "><label for='p_0_Xsouhlas' class='souhlas'>"
      . $akce->form_souhlas
      . "</label></p>";
  }
  // -------------------------------------------- tlačítka ukončení
  $form.= "<button onclick=\"clear_css('chng');php2('kontrolovat');\"><i class='fa fa-green fa-send-o'></i>
           zkontrolovat a odeslat přihlášku</button>
         <button onclick=\"php2('prerusit,=1');\"><i class='$red_h'></i> uložit rozepsanou přihlášku</button>
         <button onclick=\"php2('zahodit');\"><i class='$red_x'></i> neposílat</button>";
  // změny zobrazení
  $DOM->usermail= 'hide';
  $DOM->form= ['show',$form];
} // formulář přihlášky
// ---------------------------------------------------------------------------- zkontrolovat úplnost
function kontrolovat() { trace();
# zkontroluje bezchybnost a úplnost přihlášky
  global $DOM, $akce, $vars;
  $chybi= [];
  $opravit= [];
  $chybi_osobni= $chybi_rodinne= $chybi_strava= 0;
  $spolu= 0;
//  # ------------------------------ testování PDF
//  global $TEST;
//  if (($akce->p_dokument??0) && $TEST>1) {
//    display(gen_html(0));
//  }
  // ------------------------------ je aspoň jeden dospělý přihlášený?
  foreach (array_keys($vars->cleni) as $id) {
    if (in_array(get_role($id),['a','b']))
      $spolu+= get('o','spolu',$id);
  }
  if (!$spolu) {
    hlaska('Přihlaste prosím na akci aspoň jednu dospělou osobu');
    goto end;
  }
  // ------------------------------ jsou vyplněné všechny údaje?
  // osobní údaje přítomných
  $chybi_upozorneni= '';
  foreach (array_keys($vars->cleni) as $id) {
    $spolu= get('o','spolu',$id);
    if ($spolu) { 
      $miss= elems_missed('o',$id,['Xupozorneni']);
      if (count($miss)) {
        $chybi_osobni++;
        $chybi= array_merge($chybi,$miss); 
      }
      // souhlas s upozorněním 
      if ($akce->p_upozorneni && in_array(get_role($id),['a','b']) && !get('o','Xupozorneni',$id)) {
        $chybi_upozorneni.= "<br>Potvrďte prosím, že berete na vědomí upozornění pro "
            . (get('o','role',$id)=='b' ? 'ženu' : 'muže');
      }
      // případné doplnění klienta
      if (!($vars->klient??'') && $id==$vars->ido) {
        $jmeno= get('o','jmeno',$id);
        $prijmeni= get('o','prijmeni',$id);
        if ($jmeno && $prijmeni) {
          $vars->klient= "$jmeno $prijmeni";
          $DOM->user= ["show","<i class='fa fa-user'></i> $vars->klient<br>$vars->email"];
        }
      }
    }
  }
  // rodinné údaje
  if (typ_akce('MOR')) {
    $idr= key($vars->rodina);
    $miss= elems_missed('r',$idr);
    if (count($miss)) {
      $chybi_rodinne++;
      $chybi= array_merge($chybi,$miss);
    }
  }
  // údaje k pobytu
  $miss= elems_missed('p',0,['Xsouhlas','sleva_duvod']);
  $chybi_souhlas= $akce->p_souhlas && !get('p','Xsouhlas') ? 1 : 0;
  $chybi_duvod= $akce->p_sleva && get('p','sleva_zada') && !get('p','sleva_duvod') ? 1 : 0;
  $chybi_strava= $akce->p_strava ? $vars->form->strava!=2 : 0;
  if (count($miss)) {
    // souhlasy
    $chybi_rodinne++;
    $chybi= array_merge($chybi,$miss);  
  }
  // ------------------------------ jsou opravené chybné údaje?
  foreach ($vars->form->kontrola??[] as $name) {
    $opravit[]= $name; $DOM->$name= 'ko'; 
  }
  // redakce případné výzvy
  if (count($chybi) || count($opravit) 
      || $chybi_souhlas || $chybi_upozorneni || $chybi_strava || $chybi_duvod) {
    foreach ($chybi as $name) { $DOM->$name= 'ko'; }
    $veta= 
         ($chybi_rodinne || $chybi_osobni ? 'Doplňte označené ' : '' )
        .($chybi_rodinne ? "společné údaje" : '')
        .($chybi_rodinne && $chybi_osobni ? ' a ' : '' )
        .($chybi_osobni ? "osobní údaje ".(
          typ_akce('MOR') ? "(alespoň u těch, kteří pojedou na akci)." : '') : '')
        .(count($opravit) ? "<br>Opravte chybně vyplněné údaje" : '')
        .($chybi_strava ? "<br>Rozklikněte a případně potom upravte objednávku stravy" : '' )
        .($chybi_souhlas ? "<br>Potvrďte prosím váš souhlas s použitím osobních údajů" : '' )
        .($chybi_duvod ? "<br>Napište prosím, proč žádáte o slevu" : '' )
        . $chybi_upozorneni
        ;
    hlaska($veta); 
    goto end; 
  }
  // -------------------------------- pokud vše prošlo zobraz shrnutí a vrať se nebo přihlas
  list($text)= souhrn('kontrola');
  vyber($text,["Odeslat tyto údaje:prihlasit","Upravit údaje před odesláním:"]);
end:  
  debug($chybi,"chybějící údaje");
}
// --------------------------------------------------------------------------------------- přihlásit
function prihlasit() { trace();
# zapíše přihlášku do Answeru
  global $DOM, $vars, $akce, $errors, $TEST;
  // vytvoření pobytu
  $web_changes= 1;  // 1 pro INS pobyt | 4/8 pro INS/UPD osoba | 16/32 pro INS/UPD rodina a tvoří
  $fld= []; // pole s hodnotami pro zápis objednané stravy a dalších položek do pobyt
  log_append_stav('zapis');
  // -------------------------------- funkce na kurzu
  $ucast= 9; // náhradník protože pozdě přihlášený
  if (!$akce->p_pozde) {
    if (typ_akce('M')) {
      $umi_vps= in_array(1,explode(',',get('r','r_umi')));
      if ($umi_vps) { // VPS bereme vždy
        $ucast= isset($vars->pobyt->Xvps) && get('p','Xvps')==1 ? 1 : 0; // VPS nebo účastník
      }
      else {
        $ucast= 13; // přihláška
      }
    } 
    elseif (typ_akce('O') && $akce->p_obnova) {
      if (key($vars->rodina)>0 && byli_na_aktualnim_LK(key($vars->rodina))) {
        $ucast= isset($vars->pobyt->Xvps) && get('p','Xvps')==1 ? 1 : 0; // VPS nebo účastník
      }
      else {
        $ucast= 9; // náhradník 
      }
    } 
    else {
      $ucast= 0; // účastník
    }
  }
  set('p','funkce',$ucast);
  // vytvoříme nový záznam pro pobyt, pokud nejde o opravu
  if (!$vars->pobyt->id_pobyt) {
    db_open_pobyt();
  }
  // ------------------------------ oprav rodinné údaje případně vytvoř rodinu
  if (typ_akce('MOR')) {
    $web_changes|= db_vytvor_nebo_oprav_rodinu();
    if (count($errors)) goto db_end;
  }
  // ------------------------------ oprav (případně vytvoř) členy rodiny
  foreach (array_keys($vars->cleni) as $id) {
    // přidání člena rodiny
    if (!isset($vars->klient)) { // pokud je to registarce chybí klient
      if ($id==$vars->ido) 
        $vars->klient= get('o','jmeno',$id).' '.get('o','prijmeni',$id);
    }
    $web_changes|= db_vytvor_nebo_oprav_clena($id);
    if (count($errors)) goto db_end;
  }
  // přidání změn k pobytu
  $fld['web_changes']= $web_changes; 
  // ------------------------------ vyřeš osobní pečovatele
  foreach (array_keys($vars->cleni) as $id_dite) {
    $id_pecoun= get_pecoun($id_dite);
    if ($id_pecoun) {
      db_zapis_pecovani($id_dite,$id_pecoun);
    }
  }

  // ------------------------------ zapiš objednávku stravy do db !!! pouze normální a bezlepkové
  if ($akce->p_strava) { // lze jen pro ceník verze 2, zapíšeme spolu.kat_*
    // podle polozky.options
    $dieta= [0=>'-',1=>'-',2=>'BL'];
    $porce= [0=>'-',1=>'C',2=>'P'];
    $spani= [0=>'-',1=>'L',2=>'S',3=>'Z',4=>'-'];
    foreach (array_keys($vars->cleni) as $idc) {
      if (($ids= get('o','id_spolu',$idc))) {
        $chng= [];
        $noc= 'L'; // defaultní ubytování je na lůžku
        // specifikace noclehu
        if ($akce->p_nocleh) {
          $noc= $spani[get('o','Xnocleh',$idc)];
          $chng[]= (object)['fld'=>'kat_nocleh', 'op'=>'i','val'=>$noc];
        }
        // specifikace jídla
        $chng[]= (object)['fld'=>'kat_dieta', 'op'=>'i','val'=>$dieta[get('o','Xdieta',$idc)]];
        $chng[]= (object)['fld'=>'kat_porce', 'op'=>'i','val'=>$porce[get('o','Xporce',$idc)]];
        // odběr jídla
        $s= get('o','Xstrava_s',$idc) ? 'S' : '-'; 
        $o= get('o','Xstrava_o',$idc) ? 'O' : '-';
        $v= get('o','Xstrava_v',$idc) ? 'V' : '-';
        $chng[]= (object)['fld'=>'kat_dny', 'op'=>'i',
            'val'=>akce_sov2dny("$s$o$v",$vars->id_akce,$noc)];
        // zápis do spolu
        _ezer_qry("UPDATE",'spolu',$ids,$chng);
      }
    }
  }

  // ------------------------------ vše zapiš a uzavři formulář závěrečnou zprávou a mailem
  db_close_pobyt($fld);
  // generování PDF s osobními a citlivými údaji pro Letní kurz
  if (($akce->p_dokument??0) && $TEST<2) {
    $msg= gen_html(1);
    if ($TEST) display($msg);
  }
  log_write_changes(); // po zápisu do pobytu
  $mail_subj= "Potvrzení přijetí přihlášky ($vars->klient) na akci $akce->nazev.";
  list($mail_body,$emails,$ucastnici)= souhrn('mail');
  // mail 
  $emaily= implode(', ',$emails);
  $ok_mail= simple_mail($akce->garant_mail, $emails, $mail_subj,$mail_body,$akce->garant_mail); 
  if ($ok_mail!='ok') { $errors[]= $ok_mail; goto db_end; }
  $DOM->form= ['show',
      "Vaše přihláška byla zaevidována a poslali jsme Vám potvrzující mail na $emaily.
       <br>$akce->garant_jmeno"];
  $idp= $vars->pobyt->id_pobyt;
  append_log("<b style='color:green'>POBYT </b> ... $ucastnici id_pobyt=$idp");
  log_close();
db_end:
  if (count($errors)) {
    log_append_stav('ko');
    log_error(implode('|',$errors));
    $DOM->form= ['show',
        "Při zpracování přihlášky došlo bohužel k chybě. 
         <br>Přihlaste se prosím posláním mailu organizátorům akce
         <br><a href=mailto:'$akce->garant_mail'>$akce->garant_mail</a>"];
  }
} // prihlasit
// ---------------------------------------------------------------------------------------- přerušit
function prerusit($step) { trace();
# přerusit rozepsanou přihlášku
# step=1 ... jako fakt?
# step=2 ... pokyn k obnovení
  global $DOM;
  switch ($step) {
    case 1: // dotaz
      vyber("Chcete přerušit vyplňování a vrátit se k němu později?",
          ["Ano, chci vyplňování přerušit:prerusit,=2","Ne, chci pokračovat:"]);
      break;
    case 2: // info a konec
      $DOM->form= ['show',"
        K vyplňování se můžete vrátit kdykoliv později i na jiném počítači či mobilu. 
        Musíte ale použít stejnou emailovou adresu a potvrdit ji zaslaným pinem.
        Potom následně zvolte z nabízených možností <b>Pokračovat ve vyplňování přihlášky</b>.
        "];
      append_log("  wait ...");
      break;
  }
} // přerušit
// ------------------------------------------------------------------------------- zahodit rozepsané
function zahodit() { trace();
# zrušit rozepsanou přihlášku
  dotaz("Mám smazat rozepsanou přihlášku bez uložení?","start",'');
} // zahodit
// ---------------------------------------------------------------------------------- přidání dítěte
function nove_dite() { trace();
  vytvor_noveho_clena('d',1);
  form_deti(2);
}
// ================================================================================= reakce na změny
// ------------------------------------------------------------------------------- klient změnil DOM
function DOM_zmena($elem_ID,$val) { 
# uloží změnu přihlášky do tabulky prihlaska
# udržuje seznam chybných položek ve form->kontrola
# reaguje na změnu spolu
  global $DOM, $vars;
  $errs= [];
  read_elem($elem_ID,$val,$errs); // <== může volat DOM_zmena_spolu a DOM_zmena_slevy
  if (count($errs)) {
    $msg= implode(', ',$errs);
    if (!in_array($elem_ID,$vars->form->kontrola)) $vars->form->kontrola[]= $elem_ID;
    $DOM->$elem_ID= ['ko'];
    hlaska($msg);
  }
  else {
    $i= array_search($elem_ID,$vars->form->kontrola);
    if ($i!==false) unset($vars->form->kontrola[$i]);
    $DOM->$elem_ID= ['ok'];
  }
  log_write_changes(); 
} // změna v DOM
function DOM_zmena_spolu($idc) { // ---------------------------------------------- změna volby spolu
# volá se z read_elem při změně položky spolu
  global $DOM, $vars, $akce;
  $spolu= get('o','spolu',$idc);
  // ukaž resp. schovej zobrazení osobního pečovatele
  if (isset($vars->cleni[$idc]->o_pecoun)) {
    form_pecoun_show($idc); 
  }
  // zruš zvláštnost stravy člena při změně spolu
  if (typ_akce('MO') && $akce->p_strava) {
    $vars->cleni[$idc]->Xstrava= 0;
    form_strava_hide(); 
  }
  // zruš nevyplněného člena, který nepojede - neprovede se, pokud má roli a nebo b
  if (!$spolu && $idc<0 
      && get('o','jmeno',$idc)=='' && get('o','prijmeni',$idc)=='') {
    if (!get_pecoun($idc) && !in_array(get('o','role',$idc),['a','b'])) {
      unset($vars->cleni[$idc]);
      $clen_ID= "c_$idc"; 
      $DOM->$clen_ID= ['hide'];
    }
  }
} // změna volby spolu
function DOM_zmena_slevy($on) { // ----------------------------------------------- změna volby slevy
# volá se z read_elem při změně položky sleva_zada
  global $DOM;
  $DOM->p_0_sleva_duvod= $on ? 'show' : 'hide';
} // změna volby slevy
function DOM_unknown($ids,$in_function) { // chybějící id v DOM
# pokud je to v kontrolách, tak umožni doplnit položky zobrazené jak jako text 
# jde o: jmeno, prijmeni, rodne, narozeni
  throw new Exception("unknown DOM.id in $in_function: ".implode(',',$ids));
}
// ================================================================================= prvky formuláře
function form_manzele() { trace(); // ----------------------------------------------- zobrazení páru
  global $DOM, $vars, $akce;
  $mis_upozorneni= ['a'=>'','b'=>''];
  $clenove= '';
  foreach (array_keys($vars->cleni) as $id) {
    $clen_ID= "c_$id"; 
    $role= get_role($id);
    if (in_array($role,['a','b'])) {
      if (get('o','umrti',$id)) {
        $clenove.= "<div id='$clen_ID' class='clen'>" 
          . elem_text('o',$id,['jmeno',' ','prijmeni',', *','narozeni',' &dagger;','umrti'])
          . "</div>";
      }
      else {
        $vlastnosti= typ_akce('M') 
          ? elem_input('o',$id,['zamest','vzdelani','zajmy','jazyk',
            'aktivita','cirkev','Xpovaha','Xmanzelstvi','Xocekavani','Xrozveden']) : '';
        $upozorneni= $akce->p_upozorneni
          ? "<p class='souhlas'>"
            . "<input type='checkbox' id='o_{$id}_Xupozorneni' value='' onchange='elem_changed(this);' "
          . (get('o','Xupozorneni',$id) ? 'checked' : '')
          . " {$mis_upozorneni[$role]}><label for='o_{$id}_Xupozorneni' class='souhlas'>"
          . str_replace('@',$role=='b'?'a':'',$akce->upozorneni)
          . "</label></p>"
          : '';
        $clenove.= "<div id='$clen_ID' class='clen'>" 
          . ( $id>0
              ? ''
                . elem_input('o',$id,['spolu']) . '<div>'
                . ( typ_akce('MO') ? '<b> '.($role=='a' ? "Manžel" : "Manželka").' </b>' : '')
                . ( typ_akce('R') ? '<b> '.($role=='a' ? "Táta" : "Máma").' </b>' : '')
                . elem_text_or_input('o',$id,['jmeno',' ','prijmeni']) 
                . ($role=='b' ? elem_text_or_input('o',$id,[' roz. ','rodne']) : '')
                . elem_text_or_input('o',$id,[', ','narozeni','</div>'])
              : ''
                . ( typ_akce('MO') ? '<b> '.($role=='a' ? "Manžel" : "Manželka").' </b>' : '')
                . ( typ_akce('R') ? '<b> '.($role=='a' ? "Táta" : "Máma").' </b>' : '')
                . elem_input('o',$id,['spolu']) . elem_input('o',$id,['jmeno','prijmeni'])
                . ($role=='b' ? elem_input('o',$id,['rodne']) : '')
                . elem_input('o',$id,[',','narozeni'])
                . '<br>'
            )
          . ($akce->p_kontakt ? elem_input('o',$id,['email','telefon']) : '')
          . ($akce->p_obcanky ? elem_input('o',$id,['obcanka']) : '')
//          . $strava
          . '<br>' . $vlastnosti 
          . $upozorneni
          . "</div>";
      }
    }
  }
  $DOM->form_par= ['show',$clenove];
} // form - manželé

function form_deti($detail) {trace(); // -------------------------------------------- zobrazení dětí
  # detail=1 ... tlačítko [zobraz děti]
  # detail=2 ... děti a tlačítko [nové dítě] a tlačítko [zobraz pečouny]
  global $TEXT, $DOM, $vars;
  $part= '';
  if ($detail==1) {
    $part.= "<br><button onclick=\"php2('form_deti,=2');\" >
      <i class='fa fa-eye'></i> zobrazit/zapsat všechny naše děti (i ty, které nebereme na akci)</button>";
    $DOM->form_deti= ['show',$part];
  } // tlačítko
  else { // detail==2
    $part.= "<div id='deti' class='cleni'>";
    $deti= '';
    $deti_nove= '';
    foreach (array_keys($vars->cleni) as $id) {
      // na akce typu R zobrazíme i přátele TODO - zatím ne
      if (!in_array(get_role($id),typ_akce('R') ? ['d'] : ['d'])) continue;
      $pecoun_button= $pecoun_form= '';
      $spolu= get('o','spolu',$id);
      // příprava osobního pečovatele - pokud jsou povoleni 
      if ($vars->form->pecouni ?? 0) { // jsou povoleni
        $display= $spolu ? "style='display:block'" : "style='display:none'";
        if (!get_pecoun($id)) $vars->cleni[$id]->o_pecoun= 0; // o_pecoun nemusí být definováno
        $id_pecoun= get_pecoun($id);
        if ($id_pecoun) { // je osobní pečovatel
          $pecoun= $id_pecoun ? form_pecoun($id_pecoun) : '';
          $pecoun_form= "<div id='f_$id' class='clen' $display>$pecoun</div>";
          $name= "b_{$id}_minus";
        }
        else { // není osobní pečovatel
          $pecoun_form= "<div id='f_$id' class='clen'></div>";
          $name= "b_{$id}_plus";
        }
        $display_plus= $spolu && !$id_pecoun ? "style='display:block'" : "style='display:none'";
        $display_minus= $spolu && $id_pecoun ? "style='display:block'" : "style='display:none'";
        $pecoun_button= 
            "<button id='b_{$id}_plus' $display_plus onclick=\"php2('hledej,=1,=$id');\">
              <i class='fa fa-green fa-plus'></i> osobní pečovatel pro toto dítě</button>
             <button id='b_{$id}_minus' $display_minus onclick=\"php2('form_pecoun_clear,=$id');\">
              <i class='fa fa-red fa-minus'></i> osobní pečovatel pro toto dítě</button>";
      }
      // vlož dítě
      $clen_ID= "c_$id"; 
      if (get('o','umrti',$id)) {
        // zemřelé dítě
        $deti.= "<div id='$clen_ID' class='clen'>" 
          . elem_text('o',$id,['<span>','jmeno',' ','prijmeni',', *','narozeni',' &dagger;','umrti','</span>'])
          . "</div>";
      }
      elseif ($id>0) {
        // dítě
        $deti.= "<div id='$clen_ID' class='clen'>" 
          . $pecoun_button 
          . elem_input('o',$id,['spolu'])
          . elem_text_or_input('o',$id,['<span>','jmeno',' ','prijmeni',', ','narozeni',', ', 'role','</span>'])
          . elem_input('o',$id,['note'])
//          . $strava
          . $pecoun_form
          . "</div>";
      }
      else { // $id<0
//        if (get('o','prijmeni',$id) || get('o','jmeno',$id)) $jsou_deti++;
        $deti_nove.= "<div id='$clen_ID' class='clen'>" 
          . $pecoun_button
          . elem_input('o',$id,['spolu','jmeno','prijmeni','narozeni','note'])
//          . $strava
          . $pecoun_form
          . "</div>";
      }
    }
    if ($deti) {
//      $pozn= "<br>Pokud děti nemáte, nechte všechna pole prázdná a zrušte 'jede na akci'.";
//      $part.= "<p><i>Naše děti (zapište prosím i ty, které necháváte doma). $pozn</i></p>";
      $part.= $TEXT->deti;
    }
    $part.= $deti.$deti_nove;
    $part.= "<br><button onclick=\"php2('nove_dite');\" >
      <i class='fa fa-green fa-plus'></i> chci přidat další dítě</button>";
    $part.= "</div>";
    $DOM->form_deti= ['show',$part];
  } // seznam
  if ($vars->form->deti!=$detail) {
    $vars->form->deti= $detail;
    log_write_changes();
  }
//  $DOM->form_deti= ['show',$part];
} // form - seznam dětí

function form_strava_hide($init=0) { trace(); // ------------------------ tlačítko objednávka stravy
# init=1 vrátí div a tlačítko jako text
# init=0 smaže formálář objednávek a zobrazí jen tlačítko
  global $DOM, $akce, $vars;
  $vars->form->strava= 1; // jen tlačítko
  $nocleh= $akce->p_nocleh ? "a <i class='fa fa-bed'></i> nocleh" : '';
  $button= "<button onclick=\"php2('form_strava_show');\" >"
      . "<i class='fa fa-cutlery'></i> objednat stravu $nocleh</button>";
  if ($init) { // volání jen z form_MO
    return "<div id='strava' class='rodina'>$button</div>";
  }
  else { // při změně, která modifikuje stravy, vrať tlačítko a zruš stravu
    $DOM->strava= ['empty',$button];
  }
}
function form_strava_show() { trace(); // ----------------------------------- seznam strav pro pobyt 
  global $DOM, $TEXT, $vars;
  // ujistíme se, že jsou zapsána jména a data narození
  $chybi= [];
  $opravit= [];
  foreach (array_keys($vars->cleni) as $id) {
    if (get('o','spolu',$id)) {
      $miss= elems_missed('o',$id,['Xupozorneni']);
      if (count($miss)) {
        $chybi= array_merge($chybi,$miss); 
      }
    }
  }
  if (count($chybi)) {
    foreach ($chybi as $name) { $DOM->$name= 'ko'; }
    hlaska('Před zvolením stravy doplňte prosím osobní údaje');
    goto end;
  }
  // a že jsou opravené chybné údaje?
  foreach ($vars->form->kontrola??[] as $name) {
    $opravit[]= $name; $DOM->$name= 'ko'; 
  }
  if (count($opravit)) {
    foreach ($opravit as $name) { $DOM->$name= 'ko'; }
    hlaska('Před zvolením stravy opravte prosím chybně vyplněné údaje');
    goto end;
  }
  // je-li vše v pořádku dovol upravit stravy
  $strava= "<div>$TEXT->strava</div>"; 
  foreach (array_keys($vars->cleni) as $id) {
    if (get('o','spolu',$id)) {
      $strava.= form_strava_osoba($id,0);
    }
  }
  $vars->form->strava= 2; // seznam 
  $DOM->strava= ['empty',$strava];
end:
}
function form_strava_default($id,$cmd) { trace(); // ---------------- default stravu osoby: test|set
# cmd=set nastaví stravu na default
# cmd=not vrátí 1 pokud strava není defaultní
  global $akce;
  $ji= get_vek($id)>=$akce->p_detska_od ? 1 : 0;
  switch ($cmd) {
    case 'set':
      set('o','Xstrava_s',$ji,$id);
      set('o','Xstrava_o',$ji,$id);
      set('o','Xstrava_v',$ji,$id);
      set('o','Xporce', get_vek($id)<$akce->p_detska_do ? 2 : 1,$id); // 1 = celá, 2 = poloviční
      set('o','Xdieta',   1,$id); // 1 je normální strava
      if ($akce->p_nocleh) set('o','Xnocleh',   1,$id); // 1 je lůžko
      break;
    case 'not':
      $not= 0;
      if (get('o','Xstrava_s',$id)!=$ji) $not= 1;
      if (get('o','Xstrava_o',$id)!=$ji) $not= 1;
      if (get('o','Xstrava_v',$id)!=$ji) $not= 1;
      if (get('o','Xporce',$id)!= (get_vek($id)<$akce->p_detska_do ? 2 : 1)) $not= 1;
      if (get('o','Xdieta',$id)!=1) $not= 1;
      if ($akce->p_nocleh) if (get('o','Xnocleh',$id)!=1) $not= 1;
      return $not;
  }
}
function form_strava_osoba($id,$click) { trace(); // ------------------ specifikace stravy osoby
# zobrazí stravu jako rozpis, pokud není defaultní nebo pokud je click; jinak vrátí tlačítko
# pro click=0 vrátí html
# pro click=1 vnutí html přes $DOM
# na vstupu platí, že spolu=1
# pokud je Xstrava=0 doplní default
  global $DOM, $akce, $vars;
  // případně doplň default
  if (!$vars->cleni[$id]->Xstrava) {
    form_strava_default($id,'set');
    $vars->cleni[$id]->Xstrava= 1;
  }
  $rozepsat= $click || form_strava_default($id,'not');
  // pro děti s poloviční porcí zobraz věk
  $vek= get_vek($id);
  $vek_roku= kolik_1_2_5($vek,"rok,roky,roků");
  $polovicni= $vek<$akce->p_detska_do ? 1 : 0;
  $nocleh= $akce->p_nocleh ? " <i class='fa fa-bed'></i>" : '';
  $pro= "<i class='fa fa-cutlery'></i>$nocleh pro " . get('o','jmeno',$id) . ($polovicni ? ", $vek_roku" : ''); 
  $pro= "<b>$pro:</b>";
  // html
  $html= $rozepsat 
      ? "$pro " . elem_input('o',$id,['Xstrava_s','Xstrava_o','Xstrava_v','Xporce','Xdieta'])
        . ($akce->p_nocleh ? elem_input('o',$id,['Xnocleh']) : '')
      : "$pro <button onclick=\"php2('form_strava_osoba,=$id,=1');\" >"
        . "upřesnit objednávku </button>";
  $strava_id= "c_{$id}_strava";
  if ($click) {
    $DOM->$strava_id= ['show',$html];
  }
  else { // dom=0 ... vrácení prvotního formuláře
    return "<div class='strava_rozpis' id='$strava_id'>$html</div>";
  }
} // form strava

function form_pecoun($id) { trace(); // --------------------------------- zobrazení osobního pečouna
# údaje pečouna $id osobně pečujícího o $id_dite
  global $akce, $vars;
  $part= "<i>Osobní pečovatel pro toto dítě bude</i><br>";
  if ($id<0) {
    $part.= elem_input('o',$id,['spolu'],'hide') 
        . elem_input('o',$id,['jmeno','prijmeni']) . elem_input('o',$id,['narozeni']) 
        . elem_input('o',$id,['obcanka','<br>','email','telefon']);
  }
  else { // $id>0
    $part.= elem_input('o',$id,['spolu'],'hide')
        . elem_text_or_input('o',$id,['<div>','jmeno',' ','prijmeni',', ','narozeni','</div>'])
        . elem_input('o',$id,['obcanka','telefon']);
  }
  // doplň mu stravu
  if ($akce->p_strava) {
    $vars->cleni[$id]->Xstrava= 0;
    form_strava_hide(); 
  }
  return $part;
} // form osobní pečoun
function form_pecoun_show($id_dite,$form=null) { trace(); //  ukáže tlačítka a form osobního pečouna
  global $DOM;
  $spolu= get('o','spolu',$id_dite);
  $button_plus= "b_{$id_dite}_plus";
  $button_minus= "b_{$id_dite}_minus";
  $fid= "f_$id_dite";
  $id_pecoun= get_pecoun($id_dite);
  if ($spolu) { // dítě jede
    $DOM->$button_plus= [$id_pecoun ? 'hide' : 'show']; 
    $DOM->$button_minus= [$id_pecoun ? 'show' : 'hide']; 
    $DOM->$fid= $form===null ? ['show'] : ['show',$form];
  }
  else { // dítě nejede  
    $DOM->$button_plus= ['hide'];
    $DOM->$button_minus= ['hide'];
    $DOM->$fid= $form===null ? ['hide'] : ['hide',$form];
    // dítě nejede a pečoun není dítětem přihlašované rodiny
    if (get_role($id_pecoun)=='p') { // spolu pečouna mimo rodinu není vidět
      set('o','spolu',0,$id_pecoun);
    }
  }
} // form a tlačítka pečouna
function form_pecoun_clear($id_dite) { trace(); // --------------------- odstranění osobního pečouna
# odstranění pečouna daného dítěte ve vars i v DOM
  global $DOM, $akce, $vars;
  $id_pecoun= get_pecoun($id_dite);
  set('o','o_pecoun','',$id_dite);
  set('o','spolu',0,$id_pecoun);
  set('o','o_dite',0,$id_pecoun);
  $name= "o_{$id_pecoun}_spolu"; $DOM->$name= [0];
  $name= "f_$id_dite"; $DOM->$name= ['empty'];
  $name= "b_{$id_dite}_minus"; $DOM->$name= ['hide'];
  $name= "b_{$id_dite}_plus"; $DOM->$name= ['show'];
  // zruš mu stravu
  if ($akce->p_strava) {
    $vars->cleni[$id_pecoun]->Xstrava= 0;
    form_strava_hide(); 
  }
} // odstranění osobního pečouna

function form_solo($id) { trace(); // -------------------------------- zobrazení osoby včetně adresy
# údaje osoby $id včetně kontaktů a adresy
  global $akce;
  $clen_ID= "c_$id"; 
  $role= get_role($id);
  $part= "<div id='$clen_ID' class='solo'>"
      . ( $id>0
          ? elem_text_or_input('o',$id,['<div>','jmeno',' ','prijmeni']) 
            . ($role=='b' ? elem_text_or_input('o',$id,[' roz. ','rodne']) : '')
            . elem_text_or_input('o',$id,[', ','narozeni','</div>'])
          : elem_input('o',$id,['jmeno','prijmeni'])
            . ($role=='b' ? elem_input('o',$id,['rodne']) : '')
            . elem_input('o',$id,[',','narozeni'])
            . '<br>'
        )
      . elem_input('o',$id,['email','telefon']) 
      . ($akce->p_obcanky ? elem_input('o',$id,['obcanka']) : '')
      . ($akce->p_oso_adresa ? '<br>'.elem_input('o',$id,['ulice','psc','obec']) : '')
      . ($akce->p_akt_stav || $akce->p_akt_deti ? '<br>' : '')
      . ($akce->p_akt_stav ? elem_input('o',$id,['Xstav']) : '')
      . ($akce->p_akt_deti ? elem_input('o',$id,['Xdeti']) : '')
      . "</div>";
  return $part;
} // form osoba

function form_MO($new) { trace();
# pokud je new=1 nastaví se složky na default
  global $vars, $akce;
  if ($new) {
    // části a počáteční nastavení formuláře
    $vars->form= (object)[
        'kontrola'=>[], // seznam položek s chybou
        'typ'=>$akce->p_typ, // M O R J
        'par'=>1,
        'deti'=>$akce->p_deti ? ($akce->p_deti_but ? 1 : 2 ) : 0, // 0=nic, 1=tlačítko, 2=seznam
        'pecouni'=>$akce->p_pecouni, // 0=nejsou povolení
        'rodina'=>$akce->p_rod_adresa,
        'strava'=>$akce->p_strava,  // 0=akce bez stravy, 1=tlačítko Objednávka, 2=seznam strav
        'pozn'=>1,
        'souhlas'=>$akce->p_souhlas,
    ];
    log_write_changes();  // zapiš počáteční skeleton form
  }
  // -------------------------------------------- úprava rodinné adresy
  $zacatek= '';
  if ($vars->form->rodina) {
    $zacatek= "<p>Zapište, nebo zkontrolujte a případně upravte vaši rodinnou adresu a další údaje:</p>";
    $idr= key($vars->rodina);
    if ($idr<0) { // požadujeme název rodiny
      $zacatek.= elem_input('r',$idr,['nazev']).'<br>';
    }
    $zacatek.= elem_input('r',$idr,['ulice','psc','obec','spz','datsvatba','<br>','r_ms']);
  }
  // -------------------------------------------- poznámky k pobytu
  $pobyt= '';
  if ($vars->form->pozn) {
    $pobyt= elem_input('p',0,['pracovni']);
  }
  // specifika pro VPS na MS
  $je_dotaz_vps= false;
  if (typ_akce('MO')) {
    $umi= get('r','r_umi');
    $umi_vps= in_array(1,explode(',',$umi));
    if ($umi_vps 
        && ($akce->p_obnova && byli_na_aktualnim_LK(key($vars->rodina)) || typ_akce('M'))) {
      $pobyt.= elem_input('p',0,['Xvps']);
      $je_dotaz_vps= true;
    }
  }
  if (!$je_dotaz_vps) {
    unset($vars->pobyt->Xvps);
  }
  // žádost o slevu
  if ($akce->p_sleva) {
    $pobyt.= elem_input('p',0,['sleva_zada']) . elem_input('p',0,['sleva_duvod'],1);
  }
  // -------------------------------------------- strava
  $strava= '';
  if ($akce->p_strava) {
    $strava= form_strava_hide(1); // jen tlačítko uvnitř <div id='strava'>
  }
  // -------------------------------------------- redakce, souhlas a exit později
  $form= <<<__EOF
    <div class='rodina'>
      $zacatek
    </div>
    <p>Poznačte, koho na akci přihlašujete.</p>
    <div id='form_par'></div>
    <div id='form_deti'></div>
    $strava
    <div class='rodina'>$pobyt</div>
__EOF;
  if ($vars->form->par) form_manzele();
  if ($vars->form->deti) form_deti($vars->form->deti);
  return $form;
} // form - základní skeleton pro pár

function form_R($new) { trace();
# pokud je new=1 nastaví se složky na default
  global $vars, $akce;
  if ($new) {
    // části a počáteční nastavení formuláře
    $vars->form= (object)[
        'kontrola'=>[], // seznam položek s chybou
        'typ'=>$akce->p_typ, // = R
        'par'=>1,
        'deti'=>$akce->p_deti ? ($akce->p_deti_but ? 1 : 2 ) : 0, // 0=nic, 1=tlačítko, 2=seznam
        'pecouni'=>$akce->p_pecouni, // 0=nejsou povolení
        'rodina'=>$akce->p_rod_adresa,
        'strava'=>$akce->p_strava,  // 0=akce bez stravy, 1=tlačítko Objednávka, 2=seznam strav
        'pozn'=>1,
        'zadost'=>$akce->p_zadost,
        'souhlas'=>$akce->p_souhlas,
    ];
    log_write_changes();  // zapiš počáteční skeleton form
  }
  // -------------------------------------------- úprava rodinné adresy
  $zacatek= '';
  if ($vars->form->rodina) {
    $zacatek= "<p>Zapište, nebo zkontrolujte a případně upravte vaši rodinnou adresu a další údaje:</p>";
    $idr= key($vars->rodina);
    if ($idr<0) { // požadujeme název rodiny
      $zacatek.= elem_input('r',$idr,['nazev']).'<br>';
    }
    $zacatek.= elem_input('r',$idr,['ulice','psc','obec','spz','datsvatba','<br>','r_ms']);
  }
  // -------------------------------------------- poznámky k pobytu
  $pobyt= '';
  if ($vars->form->pozn) {
    $pobyt.= elem_input('p',0,['pracovni']);
  }
  if ($vars->form->zadost) {
    $pobyt.= elem_input('p',0,['zadost']);
  }
  // žádost o slevu
  if ($akce->p_sleva) {
    $pobyt.= elem_input('p',0,['sleva_zada']) . elem_input('p',0,['sleva_duvod'],1);
  }
  // -------------------------------------------- strava
  $strava= '';
  if ($akce->p_strava) {
    $strava= form_strava_hide(1); // jen tlačítko uvnitř <div id='strava'>
  }
  // -------------------------------------------- redakce, souhlas a exit později
  $form= <<<__EOF
    <div class='rodina'>
      $zacatek
    </div>
    <p>Poznačte, koho na akci přihlašujete.</p>
    <div id='form_par'></div>
    <div id='form_deti'></div>
    $strava
    <div class='rodina'>$pobyt</div>
__EOF;
  if ($vars->form->par) form_manzele(); 
  if ($vars->form->deti) form_deti($vars->form->deti);
  return $form;
} // form R - základní skeleton pro rodinu

function form_J($new) { trace();
# pokud je new=1 nastaví se složky na default
  global $vars, $akce;
  if ($new) {
    // části a počáteční nastavení formuláře
    $vars->form= (object)[
        'typ'=>$akce->p_typ, // M O R J
        'kontrola'=>[], // seznam položek s chybou
        'pozn'=>1,
        'souhlas'=>$akce->p_souhlas,
        'zadost'=>$akce->p_zadost,
//        'stav'=>$akce->p_akt_stav,
//        'deti'=>$akce->p_akt_deti,
    ];
    log_write_changes();  // zapiš počáteční skeleton form
  }
  // -------------------------------------------- účastník
  $osoba= form_solo($vars->ido);
  // -------------------------------------------- poznámky k pobytu
  $pobyt= '';
  if ($vars->form->pozn) {
    $pobyt.= elem_input('p',0,['pracovni']);
  }
  if ($vars->form->zadost) {
    $pobyt.= elem_input('p',0,['zadost']).'<br>';
  }
  // žádost o slevu
  if ($akce->p_sleva) {
    $pobyt.= elem_input('p',0,['sleva_zada']) . elem_input('p',0,['sleva_duvod'],1);
  }
  $form= <<<__EOF
    $osoba
    <div class='rodina'>$pobyt</div>
__EOF;
  return $form;
} // form J - základní skeleton jednotlivce

// ============================================================================ interakce s klientem
function hlaska($text,$continue='') { // --------------------------------- hláška
# zobrazí hlášku s Ok pro ukončení případně na přechod na $continue
  global $DOM;
  $DOM->alertbox= 'show'; $DOM->popup_mask= 'show';
  $DOM->alertbox_back= 'hide';
  $DOM->alertbox_text= $text;
  $off= "jQuery('#alertbox').hide();jQuery('#popup_mask').hide()";
  $cmd= $continue ? "php2('$continue')" : "";
  $DOM->alertbox_butts= "<button onclick=\"$off;$cmd;\">OK</button>";
} // popup s OK
function dotaz($dotaz,$ano,$ne) { // -------------- dotaz s funkcemi pro ano a ne
  global $DOM;
  $DOM->alertbox= 'show'; $DOM->popup_mask= 'show';
  $DOM->alertbox_back= 'hide';
  $DOM->alertbox_text= $dotaz;
  $off= "jQuery('#alertbox').hide();jQuery('#popup_mask').hide()";
  $cmd_ano= $ano ? "php2('$ano')" : "";
  $cmd_ne= $ne ? "php2('$ne')" : "";
  $DOM->alertbox_butts= "
    <button onclick=\"$off;$cmd_ano;\">ANO</button> &nbsp;
    <button onclick=\"$off;$cmd_ne;\">NE</button>
    ";
} // popup s ANO / NE
function vyber($dotaz,$odpovedi,$back=0) { // -------------- výběr z více možností
# $odpovedi= [ text:funkce:parametr:podtext
# $back=1 zobrazí x pro zrušení dialogu ale ponechání modální masky
  global $DOM;
  $DOM->alertbox= 'show'; $DOM->popup_mask= 'show';
  if ($back) {
    $DOM->alertbox_back= ['show',"<button onclick=\"jQuery('#alertbox').hide()\">&times;</button>"];
  }
  $DOM->alertbox_text= ['empty',$dotaz];
  $DOM->alertbox_butts= '';
  $off= "jQuery('#alertbox').hide();jQuery('#popup_mask').hide()";
  foreach ($odpovedi as $odpoved) {
    list($text,$fce,$par,$subtext)= explode(':',$odpoved.':::');
    if ($subtext??0) $subtext= "<br><small>$subtext</small>";
    $par= $par ? ",$par" : '';
    $php2= $fce ? "php2('$fce$par');" : '';
    $DOM->alertbox_butts.= "<button onclick=\"$off;$php2\">$text$subtext</button> &nbsp;";
  }
} // popup s výběrem z více možností
function hledej($faze,$id_dite,$ido=0,$jmeno='',$prijmeni='') { // -------------- hledání osoby
# $fáze=1 ... vyplnění jména a příjmení --> (3,5)
#       2 ... čekání na úplné vyplnění --> (3)
#       3 ... test vyplnění --> (2), nalezení a zobrazení jmenovců --> (4,5,6)
#       4 ... zvolen člen rodiny --> (end)
#       5 ... zvolena osoba na akci, nečlen rodimny --> (end)
#       6 ... zvolena známá osoba která není na kurzu --> (end)
#       7 ... zvoleno vložení nové osoby --> (end)
#       8 ... uvolnění dialogu (end)
  global $DOM, $vars, $akce;
  $DOM->modalbox= 'show'; $DOM->popup_mask= 'show'; 
  switch ($faze) {
    case 1: // ------------------------ primární dialog
      $DOM->modalbox_text= 'Vyplňte prosím jméno a příjmení a potom zvolte Prohledat evidenci';
      $DOM->modalbox_body= "
        <div class='box modal-box'>
          <label class='upper'>jméno<input type='text' id='jmeno' size='10'></label>
          <label class='upper'>příjmení<input type='text' id='prijmeni' size='12'></label>
        </div>
        ";
      $DOM->modalbox_butts= "
        <button onclick=\"php2('hledej,=3,=$id_dite,=0,jmeno,prijmeni');\">Prohledat evidenci</button> &nbsp;
        <button onclick=\"php2('hledej,=8,=$id_dite');\">Zpět</button>
        ";
      break; // primární dialog
    case 2: // ------------------------ wait
      break; // wait
    case 3: // ------------------------ ujistíme se o zadání a pak projdeme jmenovce
      if (!$jmeno || !$prijmeni) {
        hlaska("Zadejte prosím jméno i příjmení","hledej,=2,=$id_dite,=0,jmeno,prijmeni");
        break;
      }
      // nalezení jmenovců
      $DOM->modalbox_body= "
        <div class='box modal-box'>
          <label class='upper'>jméno<input type='text' id='jmeno' size='10' disabled value='$jmeno'></label>
          <label class='upper'>příjmení<input type='text' id='prijmeni' size='12' disabled value='$prijmeni'></label>
        </div>
        ";
      $dotazy= [];
      // nejprve zkusíme hledat pečouna mezi členy rodiny
      $sourozenec= 0;
      foreach (array_keys($vars->cleni) as $id) {
        if ($id<0 && get('o','jmeno',$id)==$jmeno && get('o','prijmeni',$id)==$prijmeni) {
          $dotazy[]= "$jmeno $prijmeni:hledej:=4,=$id_dite,=$id,=$jmeno,=$prijmeni:"
              . "<b class='fa-green'>přihlašovaný sourozenec</b>";
          $sourozenec++;
        }
      }
      if (!count($dotazy)) {
        $ro= pdo_query_2("
          SELECT o.id_osoba,
            ROUND(IF(MONTH(narozeni),DATEDIFF(NOW(),narozeni)/365.2425,YEAR(NOW())-YEAR(narozeni))) AS _vek,
            IF(adresa=1,o.obec,CONCAT('',r.obec)) AS _obec,
            IF(kontakt=1,o.telefon,CONCAT('',r.telefony)) AS _telefon
          FROM osoba AS o
          LEFT JOIN 
          ( SELECT id_osoba,obec,telefony
            FROM tvori JOIN rodina USING (id_rodina)
            WHERE deleted='' 
            GROUP BY id_osoba,role
            ORDER BY role ASC LIMIT 1
          )  AS r ON r.id_osoba=o.id_osoba 
          WHERE o.deleted='' AND umrti='' AND jmeno='$jmeno' AND prijmeni='$prijmeni'
          -- HAVING _vek>15
          ORDER BY _vek
        ");
        while ($ro && (list($id,$vek,$obec)= pdo_fetch_array($ro))) {
          $je_z_rodiny= $vars->idr > 0 // přihlašuje se známá rodina
              ? select1_2("SELECT COUNT(*) FROM tvori "
                  . "WHERE id_rodina=$vars->idr AND id_osoba=$id AND role='d'")
              : 0;
          if (intval($je_z_rodiny)) { 
            $dotazy[]= "$jmeno $prijmeni:hledej:=4,=$id_dite,=$id,=$jmeno,=$prijmeni:"
                . "<b class='fa-green'>$vek let, sourozenec</b>";
          }
          elseif (je_na_teto_akci($id)) { 
            // nejde o člena přihlašované rodiny
            $dotazy[]= "$jmeno $prijmeni:hledej:=5,=$id_dite,=$id,=$jmeno,=$prijmeni:"
                . "<b class='fa-green'>$vek let, na akci v jiné rodině</b>";
          }
          else {
            // není na akci
            $dotazy[]= "$jmeno $prijmeni:hledej:=6,=$id_dite,=$id,=$jmeno,=$prijmeni:$vek let, $obec";
          }
        }     
      } 
      $dotazy[]= "$jmeno $prijmeni:hledej:=7,=$id_dite,=0,=$jmeno,=$prijmeni:"
          . (count($dotazy)>0
            ? "<b class='fa-red'>je to jiný jmenovec</b>" : "<b class='fa-red'>přidat do evidence</b>");
      vyber("Vyberte pečovatele nebo vyplňte údaje nového",$dotazy,1);
      break; // procházení jmenovců + zpět
    case 4: // ------------------------ člen přihlašované rodiny        ... je v cleni
    case 5: // ------------------------ nečlen rodiny ale je na kurzu  
    case 6: // ------------------------ známá osoba která není na kurzu
    case 7: // ------------------------ vytvoření osoby
      // cleni[$ido] bude pečoun
      if (in_array($faze,[5,6])) nacti_clena($ido,'p',1);
      elseif (in_array($faze,[7])) $ido= vytvor_noveho_clena('p',1);
      // propoj dítě a pečouna
      $vars->cleni[$id_dite]->o_pecoun= [0,$ido];
      $vars->cleni[$ido]->o_dite= [0,$id_dite];
      if ($faze!=4) $vars->cleni[$ido]->role= [0,'p']; // u pečouna/člena rodiny neměň roli
      if ($faze==4 && !get('o','spolu',$ido)) { // u pečouna/člena rodiny zajisti přítomnost na kurzu
        set('o','spolu',[0,1],$ido);
        $dom_spolu= "o_{$ido}_spolu";
        $DOM->$dom_spolu= [1];
      }
      if ($faze==7) {
        $vars->cleni[$ido]->jmeno= ['',$jmeno];
        $vars->cleni[$ido]->prijmeni= ['',$prijmeni];
      }
      if ($akce->p_strava) {
        $vars->cleni[$ido]->Xstrava= 0;
        form_strava_hide(); 
      }
      log_write_changes(); 
      form_pecoun_show($id_dite,form_pecoun($ido));
      $DOM->modalbox= 'hide'; $DOM->popup_mask= 'hide';
      break; // propojení pečouna a dítěte
    case 8: // ------------------------ konec
      $DOM->modalbox= 'hide'; $DOM->popup_mask= 'hide';
      break;
  }
} // popup os. pečounů
// =============================================================================== zobrazení stránky
function read_elem($elem_ID,$val,&$errs) { // ------------------------------------------------- read elem
# načte element změněný uživatelem a poslaný z JS
# z hodnoty se odstraní levo i pravostranné mezery
  global $vars, $p_fld, $r_fld, $o_fld;  
  $m= null;
  if (preg_match("/(.)_([\-\d]+)_(.*)/",$elem_ID,$m)) { // t_idt_name
    $tab= $fmt= false;
    $t= $m[1]; $idt= $m[2]; $fld= $m[3];
    $val= trim($val);
    switch ($t) {
      case 'o': $tab= $vars->cleni[$idt]; $fmt= $o_fld[$fld][2]; break;
      case 'r': $tab= $vars->rodina[$idt]; $fmt= $r_fld[$fld][2]; break;
      case 'p': $tab= $vars->pobyt;  $fmt= $p_fld[$fld][2]; break;
      default: return;
    }
    // změna hodnoty
    if (is_array($tab->$fld)) {
      if (isset($tab->$fld[1])) { // změna změněné hodnoty
        $tab->$fld[1]= $val;
      }
      elseif ($val!=$tab->$fld[0]) { // změna dosud nezměněné položky
        $tab->$fld[1]= $val;
//        $vars->kontrola= 0;
      }
    }
    elseif ($tab->$fld!=$val) { // změna dosud nezměněné položky
      $tab->$fld= [$tab->$fld,$val];
    }
    // kontrola hodnoty
    if ($val!==false) {
      $ok= true;
      switch ($fmt) {
        case 'date':
          $ok= check_datum($val,$errs); break;
        case 'mail':
          $err= null;
          foreach (preg_split("/[,;]/",$val) as $val1) {
            if (!trim($val1)) continue;
            $ok1= check_mail($val1,$err);
  //          $ok1= emailIsValid($val1,$err); 
            if (!$ok1) $errs[]= $err;
            $ok= $ok && $ok1;
          }
          break;      
      }
    }
    // reakce na změnu položky spolu
    if ($t=='o' && $fld=='spolu') { 
      DOM_zmena_spolu($idt);
    }
    // reakce na změnu položky sleva
    if ($t=='p' && $fld=='sleva_zada') { 
      DOM_zmena_slevy($val);
    }
  }
} // převod do $vars, může volat DOM_zmena_*
function read_elems($elems,&$errs) { // ------------------------------------------------- read elems
# načte elementy změněné uživatelem a poslané z JS
  foreach ($elems as $elem_ID=>$val) {
    read_elem($elem_ID,$val,$errs);
  }
} // převod do $vars

function page() {
  global $MYSELF, $SID, $_TEST, $TEST, $TEST_mail, $TEXT, $DOM_default, $akce, $rel_root,
      $MINOR, $CORR_JS, $ezer_version, $ORG;
  $if_trace= $TEST ? "style='overflow:auto'" : '';
  $TEST_mail= $TEST_mail??'';
  $hide= "style='display:none'";
  $hide_2002= "style='display:none;z-index:2002'";
  $info= $DOM_default->info=='hide' ? '' : $DOM_default->info;
  // nalezení CSS, IMG, ... podle p_css nebo default
  $p_css= $akce->p_css??'?';
  list($css,$logo)= select_2('hodnota,ikona','_cis',"druh='akce_prihl_css' AND data='$p_css' ");    
  $css= $css??'akce';
  $logo= $logo??'husy_ymca.png';
  $verze= "$MINOR.$CORR_JS";
  echo <<<__EOD
  <!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
  <html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en" dir="ltr">
  <head>
    <meta charset="utf-8" />
    <meta Content-Type="text/html" />
    <meta http-equiv="X-UA-Compatible" content="IE=11" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title>Přihláška na akci $ORG->name</title>
    <link rel="shortcut icon" href="$ORG->icon" />
    <link rel="stylesheet" href="/less/$css$_TEST.css?verze=$verze" type="text/css" media="screen" charset='utf-8'>
    <script src="/ezer$ezer_version/client/licensed/jquery-3.3.1.min.js" type="text/javascript" charset="utf-8"></script>
    <script src="$MYSELF.js?patch=$verze" type="text/javascript" charset="utf-8"></script>
    <link rel="stylesheet" id="customify-google-font-css" href="//fonts.googleapis.com/css?family=Open+Sans%3A300%2C300i%2C400%2C400i%2C600%2C600i%2C700%2C700i%2C800%2C800i&amp;ver=0.3.5" type="text/css" media="all">
    <link rel="stylesheet" href="/ezer$ezer_version/client/licensed/font-awesome/css/font-awesome.min.css?" type="text/css" media="screen" charset="utf-8">
    <script>
      var myself_url= "$rel_root/$MYSELF.php", myself_sid= "$SID";
      window.addEventListener('load', function() { 
        console.log('LOAD');
        php2('start'); 
      });
      </script>
  </head>
  <body $if_trace>
    <div class="wrapper">
      <header>
        <div class="header">
          <div id='info' class='info'>$info</div>
          <a class="logo" href="https://www.setkani.org" target="web" title="" >
            <img src="/img/$logo" alt=""></a>
          <div id='user' class="user"></div>
        </div>
        <div class="intro">Přihláška na akci <b>$akce->nazev ($akce->oddo)</b></div>
      </header>
      <main>
        <!-- ladění ----------------------------------------------------------------------------- -->
        <div $hide id='errorbox' title='errorbox' class='box' style='border-left: 20px solid red'></div>
        <div $hide id='mailbox' title='mailbox' class='box' style='border-left: 20px solid grey'></div>
        <!-- identifikace osoby mailem a pinem ------------------------------------------------- -->
        <div $hide id='usermail' title='usermail' class='box'>
          <p id='usermail_nad'>$TEXT->usermail_nad1</p>
          <input id='email' title='váš email' type="text" size="24" value='$TEST_mail' placeholder='@'>
          <input $hide id='pin' title='doručený PIN' type='text' size="4" >
          <button id='zadost_o_pin' onclick="php('email');">Požádat o PIN</button>
          <button $hide id='kontrola_pinu' onclick="php('pin');">ověřit PIN</button>
          <span $hide id='registrace'>
            <button onclick="php2('start');">zkusím jiný mail</button>
            <button onclick="php2('registrace,=1');">registrace (muž)</button>
            <button onclick="php2('registrace,=2');">registrace (žena)</button>
          </span>
          <p id='usermail_pod'></p>
        </div>
        <!-- formulář -------------------------------------------------------------------------- -->
        <div $hide id='form' title='form' class='box'></div>
        <div class='prosba'>$akce->ohlasit_chybu $akce->opravit_chybu</div>
        <!-- rozloučení ------------------------------------------------------------------------ -->
        <div $hide id='rozlouceni' title='form' class='box'>
          <p id='rozlouceni_text'>$TEXT->rozlouceni1</p>
        </div>
        <!-- popup ----------------------------------------------------------------------------- -->
        <div id='popup_mask'></div>
        <div $hide_2002 id='alertbox' class='popup' title='Upozornění'>
          <span id='alertbox_back'></span>
          <p id='alertbox_text'></p>
          <p id='alertbox_butts'></p>
        </div>
        <div $hide id='modalbox' class='popup' title='Evidence pečovatele'>
          <p id='modalbox_text'></p>
          <p id='modalbox_body'></p>
          <p id='modalbox_butts'></p>
        </div>
      </main>
      <footer>
        <div class="footer" style="display: flex;justify-content: space-between">
          <span>© $ORG->name</span>
          <span>verze $MINOR.$CORR_JS </span>
        </div>
      </footer>
    </div>
    <section id='trace'class='trace'></section>
  </body>
  </html>
__EOD;
}

function connect_db() { // -------------------------------------------------------------- connect db
 global $ezer_server, $dbs, $db, $ezer_db, $USER, $ezer_version, $ORG,
     $kernel, $ezer_path_serv, $mysql_db_track, 
     $ezer_path_root, $abs_root, $answer_db, $mysql_tracked, $totrace, 
     $y; // $y je obecná stavová proměnná Ezer
  global $TEST;
  date_default_timezone_set('Europe/Prague');
  if ($TEST || isset($_GET['err']) && $_GET['err'] ) error_reporting(-1); else error_reporting(0);
  ini_set('display_errors', 'On');
  // prostředí Ezer
  $USER= (object)['abbr'=>'WEB'];
  $kernel= "ezer$ezer_version";
  $ezer_path_serv= "$kernel/server";
  // testovací nebo ostrá databáze a cesty např. $path_files_h
  require_once("../files/$ORG->deep"); 
  $ezer_path_root= $abs_root;
  require_once("$kernel/server/ae_slib.php");
  require_once("$kernel/pdo.inc.php");
  require_once("$kernel/server/ezer_pdo.php");
  require_once("db2/db-cenik.php"); 
  // PDF knihovny
  require_once('tcpdf/tcpdf.php');
  require_once('db2/db2_tcpdf.php');
  // definice zápisů do _track
  $mysql_db_track= true;
  $mysql_tracked= ',akce,pobyt,spolu,osoba,tvori,rodina,_user,';
  # trasování 
  if ($TEST) {
    $totrace= 'Mu';
//    $totrace= 'u';
  }
  $y= (object)[];
  // otevření databáze a redefine OBSOLETE
  if (isset($dbs[$ezer_server])) { 
    $dbs= $dbs[$ezer_server];
    $db= $db[$ezer_server];
  }
  $ezer_db= $dbs;
  ezer_connect($answer_db);
} // napojení na Ezer a log
function read_akce() { // ---------------------------------------------------------------- read akce
  global $akce, $akce_default, $vars, $ORG;
  $msg= '';
  $id_akce= $vars->id_akce; //$_GET['akce'];
  // parametry přihlášky a ověření možnosti přihlášení
  list($ok,$web_online)= select_2("COUNT(*),web_online",'akce',"id_duakce=$id_akce");
  if (!intval($ok) || !$web_online) { 
    $msg= "Na tuto akci se nelze přihlásit online"; goto end; }
  // dekódování web_online
  $akce= json_decode_2($web_online);
  $akce= (object)array_merge($akce_default,(array)$akce);
  if (!$akce || !$akce->p_enable) { 
    $msg= "Na tuto akci se bohužel nelze přihlásit online"; goto end; }
  // doplnění dalších údajů o akci
  list($akce->org,$akce->nazev,$akce->misto,$garant,$od,$do,$rok,$dnu,$strava_oddo,$cv)= select_2( // doplnění informací
      "access,nazev,misto,poradatel,datum_od,datum_do,YEAR(datum_od),DATEDIFF(datum_do,datum_od),strava_oddo,ma_cenik_verze"
      ,'akce',"id_duakce=$id_akce");
  if ($od<=date('Y-m-d')) { 
    $msg= "Akce '$akce->nazev' již proběhla, nelze se na ni přihlásit"; goto end; }
  $akce->oddo= sql2oddo($od,$do,1);
  $akce->od= $od;
  $akce->rok= $rok;
  $akce->dnu= $dnu+1;
  $akce->strava_oddo= $strava_oddo;
  $akce->cenik_verze= $cv; 
//  $MarketaZelinkova= 6849;
  if ($garant) {
  list($akce->garant_jmeno,$akce->garant_telefon,$akce->garant_mail)= // doplnění garanta
      select_2("CONCAT(jmeno,' ',prijmeni),telefon,email",
          "osoba LEFT JOIN _cis ON druh='akce_garant' AND data='$garant'",
          "id_osoba=ikona");
  }
  else {
    $akce->garant_jmeno=   $ORG->info->name;
    $akce->garant_telefon= $ORG->info->tlfn;
    $akce->garant_mail=    $ORG->info->mail;
  }
  list($akce->garant_mail)= preg_split("/[,;]/",str_replace(' ','',$akce->garant_mail));
  $akce->help_kontakt= "$akce->garant_jmeno <a href='mailto:$akce->garant_mail'>$akce->garant_mail</a>"; 
  $akce->form_pata= "Je možné, že se vám během vyplňování objeví nějaká chyba, 
      případně nedojde slíbené potvrzení. 
      <br><br>Přihlaste se prosím v takovém případě mailem zaslaným na $akce->help_kontakt.
      <br><br>Připojte prosím popis závady. Omlouváme se za nepříjemnost s beta-verzí přihlášek.";
  // doplnění konstant
  $akce->id_akce= $id_akce;
  $akce->ohlasit_chybu= "Pokud se Vám během vyplňování přihlášky objeví nějaká chyba, přijměte prosím naši omluvu.";
  $akce->opravit_chybu= "<br>Abychom chybu mohli opravit, napište prosím "
      . "<a target='mail' href='mailto:martin@smidek.eu?subject=Přihláška 2025'>autorovi</a> "
      . " a popište problém. Můžete mu také ještě od počítače zavolat na 603 150 565 (za denního světla, prosím). "
      . "Pomůžete tím těm, kteří se budou přihlašovat po Vás. Děkujeme.";
  $akce->preambule= "Tyto údaje slouží pouze pro vnitřní potřebu organizátorů kurzu MS, 
      nejsou poskytovány cizím osobám ani institucím.<br /> <b>Pro vaši spokojenost během kurzu je 
      nezbytné, abyste dotazník pečlivě a pravdivě vyplnili.</b>";
  $akce->oba= "<p><i>Přihláška obsahuje otázky určené oběma manželům 
      - je potřeba, abyste ji vyplňovali společně.</i></p>";
  $akce->form_souhlas= $ORG->gdpr;
  $akce->upozorneni= "Potvrzuji, že jsem byl@ upozorněn@, že není možné se účastnit pouze části kurzu, 
      že kurz není určen osobám závislým na alkoholu, drogách nebo jiných omamných látkách, ani
      osobám zatíženým neukončenou nevěrou, těžkou duševní nemocí či jiným omezením, která neumožňují 
      zapojit se plně do programu. V případě, že jsem v odborné péči psychologa nebo psychiatra, 
      prohlašuji, že se akce účastním s jeho souhlasem a konzultoval jsem náročnost akce s organizátory. 
      Zatržením prohlašuji, že jsem si plně vědom@, že pořadatel neodpovídá za škody a újmy, které by 
      mně/nám mohly vzniknout v souvislosti s nedodržením těchto zásad účasti na kurzu, a veškerá rizika
      v takovém případě přebíráme na sebe.";
end:    
  if ($msg) {
    die($msg);
  }
} // doplnění infromací o akci
function typ_akce($typs) { // ------------------------------------- vrátí 1 pokud je p_typ v řetězci
  global $akce, $vars;
  return strpos($typs,$vars->typ_akce??$akce->p_typ)===false ? 0 : 1;
} // vrátí 1 pokud je p_typ v řetězci
# ------------------------------------------------------------------------------- formulářové funkce
function get_role($id) { // --------------------------------------------------------------- get role
# vrátí hodnotu pole role
  global $vars;
  $role= '';
  if (isset($vars->cleni[$id]->role)) {
    $role= $vars->cleni[$id]->role;
    $role= is_array($role) ? ($role[1] ?? $role[0]) : $role;
  }
  return $role;
}
function get_vek($id) { // ----------------------------------------------------------------- get věk
# vrátí věk osoby nebo 99
  global $akce;
  $narozeni= get('o','narozeni',$id);
  $vek= $narozeni ? roku_k(sql_date1($narozeni,1),$akce->od) : 99;
  return $vek;
}
function get_pecoun($id_dite) { // ----------------------------------------------------- get pečouna
# vrátí 0 pokud nemá pečouna
  global $vars;
  return isset($vars->cleni[$id_dite]->o_pecoun) ? get('o','o_pecoun',$id_dite) : 0;
}
function get($table,$fld,$id=0) { // ----------------------------------------------------------- get
# vrátí hodnotu v datovém tvaru - pro rodinnou není nutné udávat id
# pokud není definovaná vrátí false
  global $vars;
  if ($table=='r' && !$id) $id= key($vars->rodina);
  $pair= $table=='r' ? $vars->rodina[$id] : ($table=='p' ? $vars->pobyt : $vars->cleni[$id]);
  if (isset($pair->$fld)) {
    $v= trim(is_array($pair->$fld) ? ($pair->$fld[1] ?? $pair->$fld[0]) : $pair->$fld);
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
    if (in_array($typ,['select','sub_select'])) 
      $v= $options[$f][$v] ?? '?';
    $ret->$f= $v;
  }
  return $ret;
}
function get_fmt($table,$fld,$id=0) { // ----------------------------------------------------------- get
# vrátí hodnotu v uživatelském tvaru - pro rodinnou není nutné udávat id
  global $vars, $p_fld, $r_fld, $o_fld, $options;
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
      case 'sub_select':
        $v= $options[$fld][$v] ?? '?';
        break;
      }
    }
  }
  else $v= $fld;
  return $v;
}
function set($table,$fld,$val,$id=0) { // ------------------------------------------------------ set
  global $vars; 
//  $desc= $table=='r' ? $r_fld : ($table=='p' ? $p_fld : $o_fld);
  if ($table=='r' && !$id) $id= key($vars->rodina);
  $pair= $table=='r' ? $vars->rodina[$id] : ($table=='p' ? $vars->pobyt : $vars->cleni[$id]);
  if (is_array($pair->$fld)) 
    $pair->$fld[1]= $val;
  else
    $pair->$fld= $val;
}
function elem_text($table,$id,$flds) { // ------------------------------------------------ elem text
  $html= '';
  foreach ($flds as $fld) {
    $html.= get_fmt($table,$fld,$id);
  }
  return $html;
//  return "<span>$html</span>";
}
function elems_missed($table,$id=0,$but=[]) { // --------------------------------------- elem missed
# $but je seznam neprohledávaných položek
  global $p_fld, $r_fld, $o_fld, $vars;
  $missed= [];
  $prfx= "{$table}_{$id}_";
  if ($table=='p') {
    foreach ($p_fld as $f=>list(,$title,$typ)) {
      $v= $vars->pobyt->$f ?? '';
      if (is_array($v) && substr($title,0,1)=='*') { // je to povinné?
        $v= $v[1] ?? $v[0];
        if ($v=='' || in_array($typ,['check','select','sub_select']) && $v==0) {
          if (!in_array($f,$but)) {
            $missed[]= "$prfx$f";
            display("chybí $table $id $f");
          }
        }
      }
    }
  }
  if ($table=='r') {
    $idr= key($vars->rodina);
    $rodina= $vars->rodina[$idr];
    foreach ($r_fld as $f=>list(,$title,$typ)) {
      if (substr($title,0,1)=='*') { // je to povinné?
        if (is_array($rodina->$f??null)) {
          $v= $rodina->$f[1] ?? $rodina->$f[0];
          if ($v=='' || in_array($typ,['check','select','sub_select']) && $v==0) {
            if (!in_array($f,$but)) {
              $missed[]= "$prfx$f";
              display("chybí $table $id $f");
            }
          }
        }
      }
    }
  }
  if ($table=='o') {
    $clen= $vars->cleni[$id];
    $role= get_role($id);
    foreach ($o_fld as $f=>$desc) {
      list(,$title,$typ,$omez,$no_oblig)= array_pad($desc,5,'');
      if (substr($title,0,1)=='*' 
          && ($no_oblig ? strpos($no_oblig,$role)===false : 1)
          && strpos($omez,$role)!==false) { // je to povinné?
        if (is_array($clen->$f)) { // nekontrolujeme ale načtené jako skalár
          $v= $clen->$f[1] ?? $clen->$f[0];
          if ($v=='' || (in_array($typ,['check','select','sub_select']) && $v==0)) {
            if (!in_array($f,$but)) {
              $missed[]= "$prfx$f";
              display("chybí $table $id $f");
            }
          }
        }
      }
    }
  }
end:
  return $missed;
}
function elem_input($table,$id,$flds,$to_hide='') { // ----------------------------------- elem input
# vytvoř část formuláře - pro vstup
  global $p_fld, $r_fld, $o_fld, $vars, $options;
  $html= '';
  $desc= $table=='r' ? $r_fld             : ($table=='o' ? $o_fld             : $p_fld);
  $pair= $table=='r' ? $vars->rodina[$id] : ($table=='o' ? $vars->cleni[$id]  : $vars->pobyt);
  $prfx= "{$id}_";
//  $prfx= $table=='r' ? ''                 : ($table=='o' ? "{$id}_"           : '');
//  if (!isset($pair->_show_)) $pair->_show_= 1;
  foreach ($flds as $fld) {
    if (!isset($desc[$fld])) {
//      $html.= $fld;
      continue;
    }
    $name= "{$table}_$prfx$fld";
    list($len,$title,$typ,,$no_oblig)= array_pad($desc[$fld],5,'');
    if ($typ=='x') continue;
    // rozpoznání hodnoty příp. změny položky
    $v_chng= false;
    if (!isset($pair->$fld)) {
      continue;
    }
    if (is_array($pair->$fld)) {
      if (isset($pair->$fld[1])) {
        $v= $pair->$fld[1];
        $v_chng= true;
      }
      else {
        $v= $pair->$fld[0];
      }
    }
    else {
      $v= $pair->$fld;
    }
    // pokud je v režimu kontroly zajisti orámování chng
    $chng_css= in_array($name,$vars->form->kontrola) ? 'chng' : 'chng_ok';      
    // rozpoznání povinnosti položky
    if (substr($title,0,1)=='*') { //  && ($table!='o' || $pair->spolu)) {
      $role= get_role($id);
      if ($no_oblig && strpos($no_oblig,$role)!==false) // není výjimečně nepovinné 
        $title= substr($title,1);
      else
        $title=  "<b style='color:red'>*</b>".substr($title,1);
    }
    $oninput= "onchange=\"elem_changed(this);\"";
    $hide= $to_hide ? " style='display:none'" : '';
    switch ($typ) {
    case 'check_souhlas':
    case 'check_spolu':
//    case 'check':
      $x=  $v ? 'checked' : '';
      $html.= "<label class='$typ'$hide>$title"
          . "<input type='checkbox' id='$name' value='x' $x $oninput></label>";
      break;
    case 'check':
    case 'check_sleva':
      $x=  $v ? 'checked' : '';
      $html.= "<div class='$typ'$hide><input type='checkbox' id='$name' value='x' $x $oninput>"
          . "<label for='$name'>$title</label></div>";
      break;
    case 'select':
    case 'sub_select':
      $html.= "<label class='upper'$hide>$title<select id='$name' $oninput>";
      if (isset($options[$fld][''])) {
        $selected= !$v ? 'selected' : '';
        $html.= "<option disabled='disabled' $selected>{$options[$fld]['']}</option>";
      }
      foreach ($options[$fld] as $vo=>$option) {
        if ($vo=='') continue;
        $selected= $v==$vo ? 'selected' : '';
        $html.= "\n  <option value='$vo' $selected>$option</option>";
      }
      $html.= "\n</select></label>";
      break;
    case 'area':
      list($cols,$rows)= explode('/',$len);
      $v= br2nl($v);
      $html.= "<label class='upper-area'$hide>$title"
          . "<textarea rows='$rows' cols='$cols' id='$name' $oninput>"
//          . "oninput=\"this.classList.add('chng');\">"
          . "$v</textarea></label>";
      break;
    case 'number':
      $v= $v?: 0;
    default:
      $x= $v!=='' ? "value='$v'" : ''; // "placeholder='$holder'";
      $c= $v_chng ? " class='$chng_css' " : '';
      $html.= "<label class='upper'$hide>$title"
          . "<input type='text' id='$name' size='$len' $x$c $oninput></label>";
    }
  }
  return $html;
}
function elem_text_or_input($table,$id,$flds) { // ------------ pro definovaný elem text jinak input
  $html= '';
  foreach ($flds as $fld) {
    if (get_fmt($table,$fld,$id)) {
      $html.= elem_text($table,$id,[$fld]);
    }
    else {
      $html.= elem_input($table,$id,[$fld]);
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
  }
}
function vytvor_rodinu() { // -------------------------------------------------------- vytvor rodinu
  global $vars, $r_fld;
  $vars->idr= -1;
  $vars->rodina= [$vars->idr=>(object)[]];
  $rodina= $vars->rodina[$vars->idr];
  foreach ($r_fld as $f=>list(,$title,$typ)) {
    $rodina->$f= substr($title,0,1)=='*' ? [init_value($typ)] : init_value($typ);
  }
  return $vars->idr;
}
function vytvor_clena($ido,$role,$spolu) { // ------------------------------ vytvor clena s daným ID
  // inicializace dat pro dospělou osobu, přidáme roli a že je na akci
  global $akce,$vars,$o_fld;
  $vars->cleni[$ido]= (object)[];
  foreach ($o_fld as $f=>list(,$title,$typ,$omez)) {
//    if ($typ=='x') 
//      continue;
//    else
      if (strpos($omez,$role)!==false)
      $vars->cleni[$ido]->$f= substr($title,0,1)=='*' ? [init_value($typ)] : init_value($typ);
  }
  $vars->cleni[$ido]->role= ['',$role];
  $vars->cleni[$ido]->spolu= [0,$spolu];
  $vars->cleni[$ido]->adresa= $akce->p_oso_adresa ? 1 : 0;
  // pokud je strava tak ji inicializuj
  if ($akce->p_strava) {
    $vars->cleni[$ido]->Xstrava= 0;
    form_strava_hide(); 
  }
}
function vytvor_noveho_clena($role,$spolu) { // -------------------------------- vytvor nového clena
  global $vars;
  $ido= 0;
  foreach (array_keys($vars->cleni) as $is) {
    $ido= min($ido,$is);
  }
  $ido--;
  vytvor_clena($ido,$role,$spolu);
  log_write_changes();
  return $ido;
}
function vytvor_web_json() { // ---------------------------------------------------- vytvor web_json
  global $errors, $vars;
  // příprava položky web_json
  // struktura stejná jako $vars->cleni ale zaznamenávají se jen konečné hodnoty položek začínajících X
  $web_json= (object)['cleni'=>[]]; 
  foreach ($vars->cleni as $id=>$clen) {
    foreach ((array)$clen as $f=>$v) {
      if (!in_array($f,
        ['spolu','Xpovaha','Xmanzelstvi','Xocekavani','Xrozveden','Xupozorneni']) ) continue;
      $v= $v[1]??($v[0]??$v);
      if ($v!=='' || $f=='spolu') {
        if (!isset($web_json->cleni[$id])) $web_json->cleni[$id]= (object)[];
        $web_json->cleni[$id]->$f= $v;
      }
    }
  }
  debug($web_json,"web_json");
  $web_json= json_encode_2($web_json);
  if (!$web_json) $errors[]= "chyba při ukládání: ".json_last_error();
  return $web_json;
}
function init_value($typ) { // ---------------------------------------------------------- init value
  $val= in_array($typ,['select','sub_select','number']) ? 0 : '';
  return $val;
}
# --------------------------------------------------------------------------------- čtení z databáze
function byli_na_aktualnim_LK($rodina) { // ----------------------------------- byli na_aktualnim_LK
# pro pobyt na obnově zjistí, zda rodina byla na jejím LK 
# ... 0 nebyla vůbec | 1 byla jako účastník nebo VPS
  global $ORG, $akce;
  $obnova_mesic= select_2('MONTH(datum_od)','akce',"id_duakce=$akce->id_akce");
  $rok_LK= $obnova_mesic>7 ? date('Y') : date('Y')-1;
  list($byli,$jako)= select_2(
      "SELECT COUNT(*),IFNULL(IF(funkce IN (0,1,2),1,0),0)
       FROM pobyt JOIN akce ON id_akce=id_duakce 
       WHERE akce.druh=1 AND akce.access=$ORG->code AND YEAR(akce.datum_od)=$rok_LK 
         AND pobyt.i0_rodina='$rodina'");
  return intval($byli) ? $jako : 0;
}
function je_na_teto_akci($ido) { // ------------------------------------------------ je na této akci
# zjistí, jestli daná osoba už je an této akci přihlášena
  global $akce;
  $je= select1_2("SELECT COUNT(*) FROM spolu JOIN pobyt USING (id_pobyt)
      WHERE id_osoba=$ido AND id_akce=$akce->id_akce");
  return intval($je);
}
function nacti_rodinu($idr) { // ------------------------------------------------------ nacti rodinu
  global $vars, $r_fld;
  $vars->rodina= [$idr=>(object)[]];
  $rodina= $vars->rodina[$idr];
  // rodinou adresu a kontakt načteme vždy
  $r= select_object_2('*','rodina',"id_rodina=$idr");
  foreach ($r_fld as $f=>list(,$title,$typ)) {
    // nedatabázové položky inicializuj
    if (substr($f,0,1)=='X') 
      $rodina->$f= substr($title,0,1)=='*' ? [init_value($typ)] : init_value($typ);
    // resp. ignoruj
    elseif (!isset($r->$f)) 
      continue;
    // databázové načti s případnou konverzí
    else {
      $v= nl2br_2($r->$f);
      if ($typ=='date') 
        $v= sql2date($v);
      $rodina->$f= substr($title,0,1)=='*' ? [$v] : $v;
    }
  }
}
function nacti_clena($ido,$role,$spolu) { // ------------------------------------------- nacti clena
  // přečteme položky dané osoby, přidáme roli a že je na akci
  global $akce, $vars, $o_fld, $sub_options;
  $clen= $vars->cleni[$ido]= (object)[];
  $o= select_object_2('*','osoba',"id_osoba=$ido");
  if (!$role) 
    $role= $o->sex==2 ? 'b' : 'a';
  foreach ($o_fld as $f=>list(,$title,$typ,$omez)) {
    if (strpos($omez,$role)===false) continue;
    // vyřeš osobní kontakt
    if (!$o->kontakt) {
      $o->telefon= $o->email= '';
    }
    // nedatabázové položky inicializuj
    if (substr($f,0,1)=='X') 
      $clen->$f= substr($title,0,1)=='*' ? [init_value($typ)] : init_value($typ);
    // resp. ignoruj
    elseif (!isset($o->$f)) continue;
    // databázové načti s případnou konverzí
    else {
      $v= nl2br_2($o->$f);
      if ($typ=='date') {
        $v= sql2date($v);
        $clen->$f= substr($title,0,1)=='*' ? [$v] : $v;
      }
      elseif ($typ=='sub_select') {
        $sub_v= $sub_options[$f][$v] ?? 0;
        $clen->$f= [-1=>$v,0=>$sub_v]; // $v je _cis.ikona
      }
      else {
        $clen->$f= substr($title,0,1)=='*' ? [$v] : $v;
      }
    }
  }
  // pokud je rodinná adresa načti ji
  if (!$o->adresa) { // nemá osobní adresu
    if ($vars->idr) {
      list($ulice,$psc,$obec,$stat)= 
          select_2("SELECT ulice,psc,obec,stat FROM rodina WHERE id_rodina=$vars->idr");
      $clen->adresa= 0;
      $clen->ulice= $ulice;
      $clen->psc= $psc;
      $clen->obec= $obec;
      $clen->stat= $stat;
    }
  }
  else {
    $clen->adresa= 1; // bude mít/má vlastní adresu 
  }
  $vars->cleni[$ido]->role= $role;
  $vars->cleni[$ido]->spolu= $o->umrti ? 0 : ($spolu ? [0,1] : 0);
  // pokud je strava tak ji inicializuj
  if ($akce->p_strava) {
    $vars->cleni[$ido]->Xstrava= 0;
    form_strava_hide(); 
  }
}
function db_nacti_cleny_rodiny($idr) { // ------------------------------------ db nacti_cleny_rodiny
  global $vars;
  $roles= []; // role členů rodiny
  $ido_a= $ido_b= 0; // nositelé rolí
  $ro= pdo_query_2(
    "SELECT id_osoba,role
    FROM osoba AS o JOIN tvori USING (id_osoba)
    WHERE id_rodina=$idr AND o.deleted='' AND role IN ('a','b','d','p') 
    ORDER BY role,narozeni ",1);
  while ($ro && (list($ido,$role)= pdo_fetch_array($ro))) {
    $roles[]= $role;
    if ($role=='a') $ido_a= $ido;
    if ($role=='b') $ido_b= $ido;
    // spolu je nastaveno pro klienta a pokud je typ_akce=MO tak i pro partnera
    $spolu= in_array($role,['a','b']) ? (typ_akce('MO') || $ido==$vars->ido ? 1: 0) : 0;
    nacti_clena($ido,$role,$spolu);
  }
  return [$roles,$ido_a,$ido_b];
}
function kompletuj_pobyt_ucastnik($idr,$ido) { // ----- kompletuj jednotlivce a vytvoř prázdný pobyt
# zajisti aby ve vars->cleni byl záznam (byť s prázdnými položkami)
# načti i jeho rodinu, pokud je k dispozici 
  global $akce, $vars;
  if ($ido>0) { // načteme klienta 
    $sex= select_2('sex','osoba',"id_osoba=$ido");
    $role= $sex==2 ? 'b' : 'a';
    nacti_clena($ido,$role,1);
  }
  else { // nový klient - vytvoříme rodinu 
    vytvor_clena($ido,$ido==-1 ? 'a' : 'b',1);
  } // nový klient
  if ($idr>0) { // rodina existuje - načti rodinné údaje 
    nacti_rodinu($idr);        
  } // rodina existuje - jen klient
  // pokud je typ_akce=J a známe rodinu, vypočítej položky p_akt_stav a p_akt_deti
  if (typ_akce('J') && $idr>0 && ($akce->p_akt_stav || $akce->p_akt_deti)) {
    db_nacti_cleny_rodiny($idr);
    $deti= 0; $partner= 0; 
    foreach (array_keys($vars->cleni) as $id) {
      if ($id==$ido) continue;
      if (in_array(get_role($id),['a','b'])) $partner= $id;
      if (get_role($id)=='d') $deti++;
    }
    if ($akce->p_akt_stav) {
      $rozvod= get('r','rozvod');
      $umrti= $partner ? get('o','umrti',$partner) : 0;
      set('o','Xstav',$rozvod ? 3 : ($partner ? ($umrti ? 4 : 2) : 1),$ido);
    }
    if ($akce->p_akt_deti) {
      set('o','Xdeti',$deti?:'',$ido);
    }
  }
  // vytvoř pobyt
  vytvor_pobyt();
}
function kompletuj_pobyt_par($idr,$ido) { // --------------- kompletuj rodinu a vytvoř prázdný pobyt
# zajisti aby ve vars->rodina a cleni byla úplná rodina (byť s prázdnými položkami)
# a byl iniciován resp. načten pobyt 
//  global $vars;
  $copy_adr= function($o,$r) {
    $copied= 0;
    if ($o) {
      list($ulice,$psc,$obec,$stat)= 
          select_2("SELECT ulice,psc,obec,stat FROM osoba WHERE id_osoba=$o AND adresa=1");
      if ($psc) {
        $copied= 1;
        set('r','ulice',$ulice,$r);
        set('r','psc',$psc,$r);
        set('r','obec',$obec,$r);
        set('r','stat',$stat ? ['',$stat] : '',$r); // pokud je stát vnuť jeho zapsání
      }
    }
    return $copied;
  };
  if ($idr>0) { // rodina existuje
    nacti_rodinu($idr);        
    list($roles,$ido_a,$ido_b)= db_nacti_cleny_rodiny($idr);
    // případně do rodiny doplníme druhého z manželů
    if (!in_array('a',$roles)) 
      vytvor_clena(-1,'a',1);
    if (!in_array('b',$roles)) 
      vytvor_clena(-2,'b',1);
    // zkontroluj rodinnou adresu, případně ji doplň
    if (!get('r','psc',$idr)) {
      if (!$copy_adr($ido_a,$idr))
        $copy_adr($ido_b,$idr);
    }
  } // rodina existuje - jen klient
  elseif ($ido>0) { // vytvoříme rodinu a načteme klienta a doplníme druhého z manželů a dítě
    $sex= select_2('sex','osoba',"id_osoba=$ido");
    $role= $sex==2 ? 'b' : 'a';
    $idr= vytvor_rodinu();        
    nacti_clena($ido,$role,1);
    $copy_adr($ido,$idr);
    vytvor_clena(-1,$role=='b' ? 'a' : 'b',1);
  } // rodina neexistuje - jen klient
  else { // nový klient - vytvoříme rodinu 
    vytvor_rodinu();        
    vytvor_clena(-1,'a',1);
    vytvor_clena(-2,'b',1);
  } // nový klient
  // vytvoř pobyt
  vytvor_pobyt();
end:
}
# ================================================================================ zápis do databáze
function db_open_pobyt() { // -------------------------------------------------------- db open_pobyt
# vytvoř pobyt - potřebujeme dále jeho ID 
  global $akce, $vars; 
  $ida= $akce->id_akce;
  $chng= array(
    (object)['fld'=>'id_akce',     'op'=>'i','val'=>$ida]
  );
  $idp= _ezer_qry("INSERT",'pobyt',0,$chng);
  $vars->pobyt->id_pobyt= $idp;
  log_write('id_pobyt',$idp);
  return $idp;
}
function db_vytvor_nebo_oprav_clena($id) { // --------------------------- db vytvor_nebo_oprav_clena
# pokud mají roli=p a jsou noví přidáme je do rodiny, pokud nejsou noví do rodiny se nepřidají
# pokud je oprava v adrese a je adresa=0 realizuj ji v rodině (pokud je vars->idr)
  global $o_fld, $akce, $vars; 
  $rewrite= function($old,$new) use ($vars) { // -------------------------- přepíše o_dite, o_pecoun
    foreach (array_keys($vars->cleni) as $id) {
      if (isset($vars->cleni[$id]->o_dite)) {
        if (get('o','o_dite',$id)==$old) {
          set('o','o_dite',$new,$id);
        }
      }
      if (isset($vars->cleni[$id]->o_pecoun)) {
        if (get('o','o_pecoun',$id)==$old) {
          set('o','o_pecoun',$new,$id);
        }
      }
    }
  };
  // pobyt a rodina už musí být zapsané
  $web_changes= 0;  // 4/8 pro INS/UPD osoba
  $novy= 0; // 1 pokud bude nyní vytvořen
  $idp= $vars->pobyt->id_pobyt;
//  $idr= key($vars->rodina);
  $idr= $vars->idr;
  $clen= $vars->cleni[$id];
  $spolu= get('o','spolu',$id);
  $role= get('o','role',$id);
  $jmeno= get('o','jmeno',$id);
  $prijmeni= get('o','prijmeni',$id);
  $narozeni= date2sql(get('o','narozeni',$id));
  if ($id<0) { // asi nový člen ale zkusíme ho najít v databázi jako $ido
    // pokud je prázdné jméno i příjmení nic nezapisujeme
    if (trim($jmeno)=='' && trim($prijmeni=='')) goto end;
    // člen ještě není v databázi
    $ido= 0;
    // abychom zamezili duplicitám podle jména a data narození zjistíme, jestli už není v evidenci 
    list($pocet,$idx,$access)= select_2('COUNT(*),id_osoba,access','osoba',
        "deleted='' AND jmeno='$jmeno' AND prijmeni='$prijmeni' AND narozeni='$narozeni' ");
    if (intval($pocet)==1) {
      // asi známe - zabráníme opravě jména a narození
      $ido= $idx;
      $vars->cleni[$ido]= $vars->cleni[$id];
      $vars->cleni[$ido]->jmeno= $jmeno;
      $vars->cleni[$ido]->prijmeni= $prijmeni;
      $vars->cleni[$ido]->narozeni= sql_date1($narozeni);
      unset($vars->cleni[$id]);
      // případně vyměníme $id za $ido v _o_dite a o_pecoun
      $rewrite($id,$ido);
      append_log("  <b style='color:blue'>dupl</b> ... pro $jmeno $prijmeni nalezena id_osoba=$ido");
      log_write('id_osoba',$ido);
    }
  } // asi nový člen ale zkusíme ho najít v databázi jako ido
  else { // $id>0
    $ido= $id;
  } // nenašli ido=id
  if ($ido==0) { // nenašli => zapíšeme novou osobu a připojíme ji do rodiny
    // doplníme sex - napřed podle role, potom podle jména
    $sex= 0;
    if (in_array($role,['a','b'])) {
      $sex= $role=='a' ? 1 : 2;
    }
    if (!$sex) {
      $jmeno_= preg_split("/[ \-]/",$jmeno);
      $sex= select_2('sex','_jmena',"jmeno='$jmeno_[0]' LIMIT 1");
      $sex= $sex==1 || $sex==2 ? $sex : 0;
    }
    $kontakt= 0;
    $adresa= 0;
    $chng= array(
      (object)['fld'=>'sex',      'op'=>'i','val'=>$sex],
      (object)['fld'=>'access',   'op'=>'i','val'=>$akce->org]
    );
    foreach ((array)$clen as $f=>$vals) {
      if (!isset($o_fld[$f]) || substr($f,0,1)=='X') continue; // položka začínající X nepatří do tabulky
      if (in_array($f,['spolu','role','o_dite','o_pecoun'])) continue; // nepatří do tabulky
      if (is_array($vals) && (!isset($vals[1]) || (isset($vals[1]) && $vals[1]!=$vals[0]))) {
        $v= $vals[1]??$vals[0];
        if (in_array($f,['telefon','email','nomail'])) {
          $kontakt= 1;
        }
        elseif (in_array($f,['ulice','psc','obec','stat']) ) {
          $adresa= 1;
        }
        elseif ($o_fld[$f][2]=='date') {
          $v= date2sql($v);
        }
        $chng[]= (object)['fld'=>$f, 'op'=>'i','val'=>$v];
      }
    }
    if ($kontakt) $chng[]= (object)['fld'=>'kontakt', 'op'=>'i','val'=>1];
    if ($adresa) $chng[]= (object)['fld'=>'adresa', 'op'=>'i','val'=>1];
    $web_changes|= 4;
    $ido= _ezer_qry("INSERT",'osoba',0,$chng);
    $novy= 1;
    $vars->cleni[$ido]= $vars->cleni[$id];
    if ($vars->ido==$id) { // zapíšeme id klienta
      log_write('id_osoba',$ido);
    }
    unset($vars->cleni[$id]); 
    // případně vyměníme $id za $ido v _o_dite a o_pecoun
    $rewrite($id,$ido);
    
  } // nenašli => zapíšeme novou osobu a připojíme ji do rodiny
  else { // našli => opravíme změněné hodnoty položek existující osoby - adresu možná do rodiny
    $chng= [];
    $kontakt= 0;
    $adresa= 0;
    if ($spolu) {
      // pokud je na akci a je to potřeba, rozšíříme povolení
      $access= intval(select1_2("SELECT access FROM osoba WHERE id_osoba=$ido"));
      if (!($access & intval($akce->org))) {
        $chng[]= (object)array('fld'=>'access', 'op'=>'u','old'=>$access,'val'=>$access|intval($akce->org));
      }
    }
    foreach ((array)$clen as $f=>$vals) {
      if (!isset($o_fld[$f]) || substr($f,0,1)=='X') continue; // položka začínající X nepatří do tabulky
      if (in_array($f,['spolu','role','o_dite','o_pecoun'])) continue; // nepatří do tabulky
      if (is_array($vals) && isset($vals[1]) && $vals[1]!=$vals[0]) {
        if (in_array($f,['telefon','email','nomail']) && ($clen->kontakt[0]??0)==0) {
          $kontakt= 1; // přepnout z rodinného na osobní kontakt 
        }
        $v0= $vals[0];
        $v= $vals[1];
        if ($o_fld[$f][2]=='date') {
          $v0= date2sql($v0);
          $v= date2sql($v);
        }
        elseif ($o_fld[$f][2]=='sub_select' && isset($vals[-1])) {
          $v0= $vals[-1];
        }
        elseif (in_array($f,['ulice','psc','obec','stat']) ) {
          if ($akce->p_oso_adresa && $clen->adresa==0 && $idr) {
            // oprav údaj jako rodinný
            $chngr= [(object)['fld'=>$f, 'op'=>'u','old'=>$v0,'val'=>$v]];
            $web_changes|= 32;
            _ezer_qry("UPDATE",'rodina',$idr,$chngr);
            continue;
          }
          else { // jinak jako osobní
            $adresa= 1;
            $chng[]= (object)['fld'=>$f, 'op'=>'u','old'=>$v0,'val'=>$v];
          }
        }
        else {
          $chng[]= (object)['fld'=>$f, 'op'=>'u','old'=>$v0,'val'=>$v];
        }
      }
    }
    if ($kontakt) $chng[]= (object)['fld'=>'kontakt', 'op'=>'i','val'=>1];
    if ($adresa) $chng[]= (object)['fld'=>'adresa', 'op'=>'i','val'=>1];
    if (count($chng)) {
      $web_changes|= 8;
      _ezer_qry("UPDATE",'osoba',$ido,$chng);
    }
  } // našli => opravíme změněné hodnoty položek existující osoby
  if ($spolu) { // zapojíme do pobytu
    $chng= array(
      (object)['fld'=>'id_pobyt',  'op'=>'i','val'=>$idp],
      (object)['fld'=>'id_osoba',  'op'=>'i','val'=>$ido],
      (object)['fld'=>'s_role',    'op'=>'i','val'=>$role=='d'?2:1]
    );
    $vars->cleni[$ido]->id_spolu= _ezer_qry("INSERT",'spolu',0,$chng);
  } // zapojíme do pobytu
 
  if ($idr) {
    // zapiš, že patří do rodiny -- ale nikoliv pečouny kromě nově vytvořených
    // - pokud do ní ještě nepatří (vzniká při vytvoření nové rodiny a při přidání člena rodiny 
    if ($role!='p' || $novy) {
      $uz_je= select1_2("SELECT COUNT(*) FROM tvori WHERE id_osoba=$ido AND id_rodina=$idr");
      if (!intval($uz_je)) { 
        $chng= array(
          (object)array('fld'=>'id_rodina', 'op'=>'i','val'=>$idr),
          (object)array('fld'=>'id_osoba',  'op'=>'i','val'=>$ido),
          (object)array('fld'=>'role',      'op'=>'i','val'=>$role)
        );
        $web_changes|= 32;
        _ezer_qry("INSERT",'tvori',0,$chng);
      }
    }
  } // zapojíme do rodiny
end:
  return $web_changes;
}
function db_vytvor_nebo_oprav_rodinu() { // ---------------------------- do vytvor_nebo_oprav_rodinu
# oprav rodinné údaje resp. vytvoř novou rodinu
  global $akce, $r_fld, $vars;
  $web_changes= 0;  // 16/32 pro INS/UPD rodina a tvoří
  $id= key($vars->rodina);
  $rodina= $vars->rodina[$id];
  if ($id<0) {
    // musíme vytvořit rodinu 
    $chng= array(
      (object)['fld'=>'access',   'op'=>'i','val'=>$akce->org]
    );
    foreach ((array)$rodina as $f=>$vals) {
      if (!isset($r_fld[$f]) || substr($f,0,1)=='X') continue; // položka začínající X nepatří do tabulky
      if ($r_fld[$f][2]=='number' && isset($vals[1])) $vals[1]= $vals[1]?:0;
      if (is_array($vals) && (!isset($vals[1]) || (isset($vals[1]) && $vals[1]!=$vals[0]))) {
        $v= $vals[1]??$vals[0];
        if ($r_fld[$f][2]=='date') 
          $v= date2sql($v);
        $chng[]= (object)['fld'=>$f, 'op'=>'i','val'=>$v];
      }
    }
    $web_changes|= 16;
    $idr= _ezer_qry("INSERT",'rodina',0,$chng);
    $vars->idr= $idr;
    $vars->rodina[$idr]= $rodina;
    unset($vars->rodina[$id]);
    log_write('id_rodina',$idr);
  }
  else {
    $chng= [];
    $access= intval(select1_2("SELECT access FROM rodina WHERE id_rodina=$id"));
    // pokud je to potřeba, rozšíříme povolení
    if (!($access & intval($akce->org))) {
      $chng[]= (object)array('fld'=>'access', 'op'=>'u','old'=>$access,'val'=>$access|intval($akce->org));
    }
    foreach ($rodina as $f=>$vals) {
      if (!isset($r_fld[$f]) || substr($f,0,1)=='X') continue; // položka začínající X nepatří do tabulky
      if ($r_fld[$f][2]=='number' && isset($vals[1])) $vals[1]= $vals[1]?:0;
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
      $web_changes|= 32;
      _ezer_qry("UPDATE",'rodina',$id,$chng);
    }
  }
  return $web_changes;
}
function db_zapis_pecovani($id_dite,$id_pecoun) { // ----------------------------- db zapis_pecovani
# propoj dítě s pečounem 
# dítě má spolu.s_role=2
# pečoun má spolu.s_role=5 a spolu.pecovane=id_dite  
  global $vars;
  $ida= $vars->id_akce;
  $idp= $vars->pobyt->id_pobyt;
  $s_dite= select1_2("SELECT id_spolu FROM spolu "
      . "WHERE id_pobyt=$idp AND id_osoba=$id_dite");
  $s_pecoun= select1_2("SELECT id_spolu FROM spolu JOIN pobyt USING (id_pobyt)"
      . "WHERE id_akce=$ida AND id_osoba=$id_pecoun");
  if (!$s_pecoun) {
    log_error("Nastal problém s navázáním osobního pečovatele");
  }
  else {
    query_track_2("UPDATE spolu SET s_role=3 WHERE id_spolu=$s_dite");
    query_track_2("UPDATE spolu SET s_role=5,pecovane=$id_dite WHERE id_spolu=$s_pecoun");
  }
}
function db_close_pobyt($fld_plus) { // --------------------------------------------- db close_pobyt
# fld_plus ... zápis hodnot mimo těch funkce polozky - např. strava
# polozka pracovni se pro MO zapisuje do pobyt.pracovni pro RJ do pobyt.poznamka
  global $p_fld, $vars;
  // úschova pobyt
  $idr= key($vars->rodina);
  $web_json= vytvor_web_json();
  $chng= array(
    (object)['fld'=>'funkce',      'op'=>'i','val'=>get('p','funkce')],
    (object)['fld'=>'web_json',    'op'=>'i','val'=>$web_json],
  );
  if (typ_akce('MOR')) {
    $chng[]= (object)['fld'=>'i0_rodina',   'op'=>'i','val'=>$idr];
  }
  foreach ($vars->pobyt as $f=>$vals) {
    if (!isset($p_fld[$f]) || substr($f,0,1)=='X') continue; // položka začínající X nepatří do tabulky
    if (in_array($f,['funkce'])) continue; // dávají se vždy
    if (is_array($vals) && isset($vals[1]) && $vals[1]!=$vals[0]) {
      if ($f=='pracovni' && typ_akce('RJ')) $f= 'poznamka';
      $chng[]= (object)['fld'=>$f, 'op'=>'i','val'=>$vals[1]];
    }
  } 
  foreach ($fld_plus as $f=>$val) {
    $chng[]= (object)['fld'=>$f, 'op'=>'i','val'=>$val];
  }
  _ezer_qry("UPDATE",'pobyt',$vars->pobyt->id_pobyt,$chng);
  // poznamenání souhlasu se zpracováním osobních údajů
  if ($vars->chk_souhlas??0) {
    $ted= date("Y-m-d H:i:s");
    foreach ($vars->cleni as $id=>$clen) {
      if ($clen->spolu && in_array(get_role($id),['a','b'])) {
        _ezer_qry("UPDATE",'osoba',$id,[(object)['fld'=>'web_souhlas','op'=>'i','val'=>$ted]]);
      }
    }
  }
}
# ------------------------------------------------------------------------------------ log prihlaska
function log_open($email) { // ------------------------------------------------------------ log open
  // vytvoří přihlášku a vloží informaci do logu a do _track
  global $TEST, $AKCE, $VERZE, $MINOR, $akce, $vars;
  if (!isset($_SESSION[$AKCE]->id_prihlaska)) {
    $ip= $_SERVER['HTTP_X_REAL_IP'] ?? $_SERVER['REMOTE_ADDR'];
    $email= pdo_real_escape_string($email);
    $ida= $akce->id_akce;
    $abbr= $version= $platform= null;
    ezer_browser($abbr,$version,$platform);
    $res= pdo_query_2("INSERT INTO prihlaska SET verze='$VERZE.$MINOR',open=NOW(),IP='$ip',"
        . "browser='$platform $abbr $version',id_akce=$ida,email='$email' ",1);
    if ($res!==false) {
      $_SESSION[$AKCE]->id_prihlaska= $id= $TEST<2 ? pdo_insert_id() : 1;
      $vars->id_prihlaska= $id;
      pdo_query_2("INSERT INTO _track (kdy,kdo,kde,klic,op,fld,val) "
          . "VALUE (NOW(),'WEB','prihlaska',$id,'i','id_akce',$ida)",1);
      session_write_close();
      session_start(); 
//      session_start(['cookie_lifetime'=>60*60*24*2]); // dva dny
    }
  }
} 
function log_write($clmn,$value) { // ---------------------------------------------------- log write
  global $AKCE, $TRACE;
  if (($id= ($_SESSION[$AKCE]->id_prihlaska??0))) {
    $val= $value==='NOW()' ? 'NOW()' : "'".pdo_real_escape_string($value)."'";
    $res= pdo_query_2("UPDATE prihlaska SET $clmn=$val WHERE id_prihlaska=$id",1);
    if ($res===false && $TRACE)
      display("LOG_WRITE fail for:$clmn=$val");
  }
  elseif ($TRACE)
      display("LOG_WRITE fail for:$clmn=$val - no sesssion");
}
function log_append_stav($novy) { // ---------------------------------------------------- log write
  global $AKCE, $TRACE;
  if (($id= ($_SESSION[$AKCE]->id_prihlaska??0))) {
    $res= pdo_query_2("UPDATE prihlaska SET stav=IF(stav='','$novy',CONCAT(stav,'-','$novy')) "
        . "WHERE id_prihlaska=$id",1);
    if ($res===false && $TRACE)
      display("LOG_APPEND_STAV fail for:$novy");
  }
  elseif ($TRACE)
      display("LOG_APPEND_STAV fail for:$novy - no sesssion");
}
function log_write_changes() { // ------------------------------------------------ log write_changes
  global $AKCE, $vars, $TRACE;
  if (($idw= ($_SESSION[$AKCE]->id_prihlaska??0))) {
    $changes= (object)[];
    foreach ((array)$vars as $name=>$val0) {
      // zapiš hodnoty s indexem 1 tzn. změněné
      if (in_array($name,['cleni','rodina'])) {
        if (!is_array($val0)) continue;
        foreach ($val0 as $id=>$val1) {
          if (!is_object($val1)) continue;
          foreach ($val1 as $fld=>$val2) {
            if (is_array($val2) && isset($val2[1])) {
              if (!isset($changes->$name)) $changes->$name= [];
              if (!isset($changes->$name[$id])) $changes->$name[$id]= (object)[];
              // u hodnot typu sub_select je třeba předat i původní hodnotu z db u indexu -1
              if (isset($val2[-1])) 
                ($changes->$name[$id])->$fld= [$val2[-1],$val2[1]];
              else // jinak je hodnota skalární
                ($changes->$name[$id])->$fld= $val2[1];
            }
          }
        }
      }
      if (in_array($name,['pobyt'])) {
        if (!is_object($val0)) continue;
        foreach ($val0 as $fld=>$val2) {
          $id= $val0->id_pobyt;
          if (is_array($val2) && isset($val2[1])) {
            if (!isset($changes->$name)) $changes->$name= (object)[];
            if (!isset($changes->$name->$id)) $changes->$name->$id= (object)[];
            ($changes->$name->$id)->$fld= $val2[1];
          }
        }
      }
    }
    // přidej aktuální skeleton formuláře
    $changes->form= $vars->form;
    $val= json_encode_2($changes);
    $res= pdo_query_2("UPDATE prihlaska SET save=NOW(),vars_json='$val' WHERE id_prihlaska=$idw",1);
    if ($res===false && $TRACE)
      display("LOG_WRITE_CHANGES fail");
  }
  elseif ($TRACE)
      display("LOG_WRITE_VARS fail - no sesssion");
} // zapíše změněné $vars před zobrazením formuláře 
function log_find_saved($email) { // ------------------------------------------------ log find_saved
  global $vars;
  // zkusíme najít poslední verzi přihlášky - je ve fázi (c)
  $found= '';
  list($idp,$idw)= select_2("SELECT id_pobyt,id_prihlaska FROM prihlaska "
      . "WHERE id_akce=$vars->id_akce AND email='$email' "
//      . "WHERE id_pobyt!=0 AND id_akce=$vars->id_akce AND email='$email' "
      . "ORDER BY id_prihlaska DESC LIMIT 1");
  if ($idp) {
    $vars->idw_old= $idw;
    goto end;  
  } // už se povedlo přihlásit
  list($idpr,$open)= select_2("SELECT id_prihlaska,open FROM prihlaska 
      WHERE id_pobyt=0 AND id_akce=$vars->id_akce AND email='$email' AND vars_json!='' 
      AND id_prihlaska>110 ORDER BY id_prihlaska DESC LIMIT 1");
  if (!$idpr) goto end;
  $vars->continue= $idpr;
  list($vars->ido,$vars->idr)= select_2(
      "SELECT id_osoba,id_rodina FROM prihlaska WHERE id_prihlaska=$idpr");
  $found= sql_time1($open);
end:
  return $found;
} // načtení data uloženého stavu 
function log_load_changes() { // -------------------------------------------------- log load_changes
  global $vars;
  // najdeme změněná data 
  $idw= $vars->continue;
  $vars_json= select_2('vars_json','prihlaska',"id_prihlaska=$idw");
  if (!$vars_json) goto end;
  $chngs= json_decode_2($vars_json);
  foreach ($chngs as $name=>$val0) {
    if ($name=='form') {
      $vars->form= $val0;
      continue;
    }
    foreach ($val0 as $id=>$val1) {
      foreach ($val1 as $fld=>$val2) {
        if ($name=='pobyt') {
          $vars->$name->$fld= 
            [is_array($vars->$name->$fld) ? $vars->$name->$fld[0] : $vars->$name->$fld
            ,$val2];
        }
        else {
          if (!isset($vars->$name[$id])) {
            if ($name=='cleni') {
              if ($id<0)
                vytvor_clena($id,$val1->role??'',$val1->spolu);
              else 
                nacti_clena($id,$val1->role??'',$val1->spolu);
            }
          }
          if (!isset($vars->$name[$id]->$fld)) $vars->$name[$id]->$fld= '';
          if (is_array($val2)) { 
            // pouze v případě sub_select je předáno [původní hodnota,změněná hodnota]
            $vars->$name[$id]->$fld= [-1=>$val2[0],$vars->$name[$id]->$fld[0],$val2[1]];
          }
          else {
            // jindy je vždy předána skalární hodnota
            $vars->$name[$id]->$fld= 
              [is_array($vars->$name[$id]->$fld) ? $vars->$name[$id]->$fld[0] : $vars->$name[$id]->$fld
              ,$val2];
          }
        }
      }
    }
  }
  log_append_stav("reload_$idw");
end:
} // načtení uloženého stavu $vars
function log_error($msg) { // ---------------------------------------------------- log error
  global $AKCE, $TRACE;
  if (($id= ($_SESSION[$AKCE]->id_prihlaska??0))) {
    $val= "'".pdo_real_escape_string($msg)."'";
    $res= pdo_query_2("UPDATE prihlaska SET errors=CONCAT(errors,'|',$val) WHERE id_prihlaska=$id",0);
    if ($res===false && $TRACE)
      display("LOG_ERROR fail");
  }
  elseif ($TRACE)
      display("LOG_ERROR fail - no sesssion");
  // vložení do souboru prihlaska_2025.log.php
  append_log("<b style='color:red'>ERROR</b> $msg");
}
function log_close() { // ---------------------------------------------------------------- log close
  global $VERZE, $MINOR, $CORR_JS, $TEST, $akce, $vars;
  log_write('close','NOW()');
  // pokud v logu není aktuální akce přidáme její popis
  $file= "prihlaska.log.php";
  $nazev_akce= 0;
  if (file_exists($file)) {
    $lines= file($file);
    foreach ($lines as $line) {
      if (substr($line,9,6)=="$vars->id_akce =") {
        $nazev_akce= 1;
        break;
      }
    }
    if (!$nazev_akce) { // aktuální akce ještě nemá popis
      $ok= select_2('COUNT(*)','akce',
          "id_duakce='$vars->id_akce' AND datum_od>NOW()");
      if ($ok) {
        $x= $TEST==2 ? " TEST=2 " : "$VERZE.$MINOR/$CORR_JS";
        $url= "prihlaska.log.php?itsme=1&akce=$vars->id_akce";
        $msg= "$x $vars->id_akce = <a style='color:green;font-weight:bold' href='$url'>"
            . "$akce->nazev ($akce->oddo)</a>";
        file_put_contents($file, "$msg\n", FILE_APPEND);
      }
    }
  }
}
function append_log($msg) { // ------------------------------------------------------ append error
  global $AKCE, $VERZE, $MINOR, $CORR_JS, $TEST, $ezer_version;
  $file= "prihlaska.log.php";
  $idw= $_SESSION[$AKCE]->id_prihlaska??'?';
  $email= $_SESSION[$AKCE]->email??'?';
  $ip= $_SERVER['HTTP_X_REAL_IP'] ?? $_SERVER['REMOTE_ADDR'];
  $x= $TEST==2 ? " TEST=2 " : "$VERZE.$MINOR/$CORR_JS";
  $ida= str_pad(substr($AKCE,2),4,' ',STR_PAD_LEFT);
  $msg= "$x $ida ".date('Y-m-d H:i:s').str_pad($idw,5,' ',STR_PAD_LEFT)." $msg mail=$email ip=$ip";
  if (!file_exists($file)) {
    global $MYSELF;
    $prefix= 
<<<__EOS
<?php 
session_start(); 
if(!(\$_SESSION['ast']['user_id']??0) && !(\$_SESSION['db2']['user_id']??0) && !(\$_SESSION['dbt']['user_id']??0) && !isset(\$_GET['itsme'])) exit; 
\$akce=\$_GET['akce']??''; echo "
<html><head><title>přihlášky</title>
<link rel='shortcut icon' href='img/log_icon.png'>
<script src='/ezer$ezer_version/client/licensed/jquery-3.3.1.min.js' type='text/javascript' charset='utf-8'></script>
<script src='$MYSELF.js?corr=$CORR_JS' type='text/javascript' charset='utf-8'></script>
<script type='text/javascript'>window.addEventListener('load', function() { pretty_log('\$akce');});</script>  
</script>";
?>
</head><body><pre id="log"
><b>VERZE/JS  AKCE DATUM      ČAS      PŘIHLÁŠKA       KLIENT </b>\n
__EOS;
      file_put_contents($file, $prefix);
  }
  file_put_contents($file, "$msg\n", FILE_APPEND);
}
# ============================================================================= vytváření PDF obrazu
function souhrn($ucel) {
# ucel = kontrola | dopis
  global $akce, $vars, $options;
  // akce
  $na= "na akci <b>$akce->nazev, $akce->misto</b> $akce->oddo";
  // účastníci
  $ucastnici= ''; $del= ''; $jidlo= ''; $vps= ''; $vzkazy= ''; $pecovani= ''; $pece= [];
  $emails= [$vars->email]; 
  foreach (array_keys($vars->cleni) as $id) {
    $spolu= get('o','spolu',$id);
    $role= get_role($id);
    if (!$spolu) continue;
    $jmeno= get('o','jmeno',$id);
    $prijmeni= get('o','prijmeni',$id);
    $ucastnici.= "$del$jmeno $prijmeni"; $del= ', ';
    
    // přehled stravování případně ubytování
    if ($akce->p_strava) { 
      $vek= get_vek($id);
      $vek= in_array(get_role($id),['d','p']) ? ' ('.kolik_1_2_5($vek,"rok,roky,roků").')' : '';
      // jmenovitá objednávka
      $jidlo.= "<li><b>$jmeno $prijmeni</b>$vek ";
      // strava
      $ns= get('o','Xstrava_s',$id)?:0; 
      $no= get('o','Xstrava_o',$id)?:0; 
      $nv= get('o','Xstrava_v',$id)?:0; 
      // redakce stravy
      if ($ns+$no+$nv==0) {
        $jidlo.= "bez stravy";
      }
      elseif ($ns+$no+$nv==3) {
        $jidlo.= "snídaně, obědy, večeře: ";
      }
      else {
        $chod= $ns ? "snídaně" : '';
        $chod.= ($chod && $no ? ', ' : '') . ($no ? "obědy" : '');
        $chod.= ($chod && $nv ? ', ' : '') . ($nv ? "večeře" : '');
        $jidlo.= "jen $chod:";
      }
      if ($ns+$no+$nv > 0) {
        // dieta
        $it= get('o','Xdieta',$id); 
        if ($it<=1) {
          $jidlo.= " normální";
        }
        else {
          $jidlo.= ' dieta '.$options['Xdieta'][$it];
        }
        // porce
        $ip= get('o','Xporce',$id); 
        $jidlo.= ', porce '.$options['Xporce'][$ip];
      }
      // ubytování ?
      if ($akce->p_nocleh) {
        $in= get('o','Xnocleh',$id); 
        $jidlo.= ', ubytování '.$options['Xnocleh'][$in];
      }
      $jidlo.= '</li>';
    } // strava

    // shromáždění osobního pečování
    if ($role=='d' && typ_akce('MO') && $akce->p_pecouni) {
      $id_pecoun= get('o','o_pecoun',$id);
      if ($id_pecoun) {
        $pecoun= get('o','jmeno',$id_pecoun).' '.get('o','prijmeni',$id_pecoun);
        if ($role=='d' && $id_pecoun) {
          $pece[]= "$jmeno bude mít osobního pečovatele: <b>$pecoun</b>";
        }
      }
    }

    // shromáždění mailů
    if (!in_array($role,['a','b'])) continue;
    $ems= preg_split('/[,;]/',get('o','email',$id));
    foreach ($ems as $email) {
      $email= trim($email);
      if ($email && !in_array($email,$emails)) 
        $emails[]= $email;
    }
  } 
  // osobní pečování
  if (count($pece)) {
    $pecovani= '<p>'.implode(', ',$pece).'</p>';
  }
  // varianty pro stravu / ne stravu
  if ($jidlo) {
    $jidlo= "<ul style='text-align:left'>$jidlo</ul>";
  }
  // doplnění poděkování za přijetí služby VPS
  if (typ_akce('MO') && isset($vars->pobyt->Xvps)) {
    $vps= get('p','Xvps')==1 ? "<p>Děkujeme, že přijímáte službu VPS.</p>" : '';
  }
  // případné žádosti, poznámky a žádosti o slevu
  $pozn= get('p','pracovni');
  if ($akce->p_zadost && get('p','zadost')) {
    $pozn= $pozn ? "$akce->veta_zadost, $pozn" : $akce->veta_zadost;
  }
  // přípony plurál/singulár
  $eme= $ucel=='kontrola'? (typ_akce('J') ? 'i' : 'eme') : 'ete'; 
  $ame= $ucel=='kontrola'? (typ_akce('J') ? 'ám' : 'áme') : 'áte'; 
  // přípony podle my-vy
  $vzkazy.= $pozn ? "<p>Organizátorům vzkazuj$eme: $pozn</p>" : '';
  if (get('p','sleva_zada')) {
    $vzkazy.= "<p>Žád$ame o slevu, protože: ".get('p','sleva_duvod').'</p>';
  }
  // redakce
  $veta= $akce->veta_potvrzeni??"";
  $html= $ucel=='kontrola'
    // text ke kontrole po vyplnění
    ? "Přihlašuj$eme se $na"
      . ( $jidlo 
        ? " a objednáv$ame pro $jidlo " 
        : " jako $ucastnici."
        )
      . $pecovani
      . $vzkazy
    // text zaslaný mailem po přihlášení
    : "Dobrý den,<p>dostali jsme vaši přihlášku $na,"
      . ( $jidlo
        ? " ve které pro účastníky objednáváte $jidlo"
        : " na kterou se přihlašujete jako $ucastnici."
        )
      . $pecovani
      . $vzkazy
      . $vps
      . "<p>$veta</p>"
//      . "<p>Zaslané údaje zpracujeme a do týdne vám pošleme odpověď.</p>"
      . "<p>S přáním hezkého dne<br>$akce->garant_jmeno"
      . "<br><a href=mailto:'$akce->garant_mail'>$akce->garant_mail</a>"
      . "<br>$akce->garant_telefon</p>"
      . "<p><i>Tato odpověď je vygenerována automaticky</i></p>";
  // konec: text, maily, úščastníci
  return [$html,$emails,$ucastnici];
} // souhrn přihlášky pro kontrolu a vložení do mailu
function gen_html($to_save=0) {
# vygeneruje textový tvar přihlášky, pro to_save=1 uloží do pobyt to_save=2 uloží do prihlasky
  global $akce, $vars;
  $inits= function($table) { // --     ---------------------------------------------------------- init s
  # vrátí iniciální hodnotu všech položek jako objekt
    global $p_fld, $r_fld, $o_fld, $options;
    $ret= (object)[];
    $desc= $table=='r' ? $r_fld : ($table=='p' ? $p_fld : $o_fld);
    foreach ($desc as $f=>list(,,$typ)) {
      $v= init_value($typ);
      if (in_array($typ,['select','sub_select'])) 
        $v= $options[$f][0] ?? '?';
      $ret->$f= $v;
    }
    return $ret;
  };
  $ted= date("j.n.Y H:i:s");
  $html= '';
  $html.= "<h3 style=\"text-align:center;\">Údaje z online přihlášky na akci \"$akce->nazev\"</h3>";
  $html.= "<p style=\"text-align:center;\"><i>vyplněné $ted a doplněné dříve svěřenými osobními údaji</i></p>";
  // odlišení muže, ženy a dětí
  $sebou= []; $deti= $del_d= ''; $chuvy= '';
  $r= gets('r');
  $m= $inits('o');
  $z= $inits('o');
  foreach (array_keys($vars->cleni) as $id) {
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
          $pecuje_o= get('o','jmeno',$o->o_dite).' '.get('o','prijmeni',$o->o_dite);
          $jmeno= "$o->jmeno $o->prijmeni";
          $adresa= isset($o->psc) ? "bydliště: $o->ulice, $o->psc $o->obec," : '';
          $chuvy.= "<br>$jmeno, $o->narozeni, $adresa <b>pečuje o: $pecuje_o</b>";
        }
        break;
    }
  }
  // zjištění předcozích účastí na MS 
  $ucasti= ''; $del= ''; $n= 0; $n_max= 3;
  $idr= $vars->idr??0;
  if ($idr) {
    $ru= pdo_query("SELECT YEAR(datum_od) AS _rok 
      FROM akce JOIN pobyt ON id_akce=id_duakce JOIN _cis ON _cis.druh='ms_akce_funkce' AND data=funkce
      WHERE i0_rodina=$idr AND akce.druh=1 AND ikona=0 AND zruseno=0 AND datum_od<NOW() AND akce.access=1
      ORDER BY _rok DESC");
    while ($ru && (list($rok)= pdo_fetch_array($ru))) {
      $n++;
      if ($n<=$n_max) { 
        $ucasti.= "$del$rok"; $del= ', ';
      }
    }
  }
  $ucasti= 'Účast na LK YS: ' . ($n>$n_max ? "$ucasti, ... celkem $n x" : ($n ? "$ucasti" : 'ne'))
      . ' + mimo YS: ' . ($r->r_ms ? "$r->r_ms" : 'ne');
  display($ucasti);
  // redakce osobních údajů
  $m_roz= $m->rodne??0 ? "roz. $m->rodne" : '';
  $z_roz= $z->rodne??0 ? "roz. $z->rodne" : '';
  $udaje= [
    ['Jméno a příjmení', "$m->jmeno $m->prijmeni $m_roz", "$z->jmeno $z->prijmeni $z_roz"], 
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
  $jm= $m->jazyk; $jm= $jm ? "; $jm" : '';
  $jz= $z->jazyk; $jz= $jz ? "; $jz" : '';
  $udaje= [
    ['Vzdělání',              $m->vzdelani, $z->vzdelani],
    ['Povolání, zaměstnání',  $m->zamest, $z->zamest],
    ['Zájmy; znalost jazyků',"$m->zajmy$jm", "$z->zajmy$jz"],
    ['Popiš svoji povahu',    $m->Xpovaha, $z->Xpovaha],
    ['Vyjádři se o vašem manželství', $m->Xmanzelstvi, $z->Xmanzelstvi],
    ['Co od účasti očekávám', $m->Xocekavani, $z->Xocekavani],
    ['Příslušnost k církvi',  $m->cirkev, $z->cirkev],
    ['Aktivita v církvi, společnosti', $m->aktivita, $z->aktivita],
  ];
  $th= "th colspan=\"2\"";
  $html.= "<table $table_attr><tr><th></th><$th>Muž</th><$th>Žena</th></tr>";
  $td= "td colspan=\"2\"";
  foreach ($udaje as $u) {
    $html.= "<tr><th>$u[0]</th><$td>$u[1]</td><$td>$u[2]</td></tr>";
  }
  $html.= "<tr><th>Děti (jméno + datum narození)</th><td colspan=\"4\">$deti</td></tr>";
  $html.= "<tr>
    <th>Rodinné údaje</th><td>SPZ auta na kurzu: <br>$r->spz</td>
    <td>Datum svatby: $r->datsvatba</td>
    <td>Předchozí manželství? <br>muž: "
      .($m->Xrozveden?:'-').", žena: ".($z->Xrozveden?:'-')."</td>
    <td>$ucasti</td></tr>";
  $html.= "</table>";
  if ($akce->p_upozorneni)
    $html.= "<p><i>Souhlas obou manželů s podmínkami účasti na kurzu byl potvrzen $ted.</i></p>";
  // vložit přihlášku jako PDF do záložky Dokumenty daného pobytu
  $html= strtr($html,['&quot;'=>'"',"&apos;"=>"'"]);
  if ($to_save) {
    global $path_files_h;
    $foot= '';
    if ($to_save==1) {
      $fname= "online-prihlaska.pdf";
      $idp= $vars->pobyt->id_pobyt;
      $f_abs= "{$path_files_h}pobyt/{$fname}_$idp";
      tc_html($f_abs,$html,$foot);
      $html= "Přihláška byla vložena do záložky Dokumenty jako soubor $fname ";
    }
    else {
      global $AKCE;
      $idp= $vars->id_prihlaska;
      $fname= "{$AKCE}_prihlaska_$idp";
      $fdir= "{$path_files_h}prihlaska";
      if (file_exists($fdir)) {
        $f_abs= "$fdir/{$fname}.pdf";
        if (file_exists($f_abs)) {
          rename($f_abs,"$f_abs.pdf");
        }
        tc_html($f_abs,$html,$foot);
        $html= "Přihláška byla skrytě vložena do ../files/(root)/prihlaska jako soubor {$fname}_$idp ";
      }
      else {
        log_error("nepřístupná složka '$fdir'");
      }
    }
  }
  return $html;
}
function do_session_restart() { // ---------------------------------------------- do session_restart
  global $AKCE;
  unset($_SESSION[$AKCE]);
  unset($_SESSION['A_akce']);
  session_write_close();
  session_start();
//  session_start(['cookie_lifetime'=>60*60*24*2]); // dva dny
}
# -------------------------------------------------------------------------------------- simple mail
function simple_mail($replyto,$address,$subject,$body,$cc='') { 
# odeslání mailu
# $MAIL=0 zabrání odeslání, jen zobrazí mail v trasování
# $_TEST zabrání posílání na garanta přes replyTo 
  global $abs_root, $MAIL, $TEST, $_TEST, $DOM, $ezer_version, $ORG;
  $msg= 'ok';
  $serverConfig= get_smtp($ORG->smtp);
  if ($ORG->code==1) $serverConfig->files_path= __DIR__.'/../files/setkani4';
  $serverConfig->FromName= $ORG->name;
  if ($TEST>1 || !$MAIL) {
    $DOM->mailbox= ['show',
        "<h3>Simulace odeslání mailu z adresy $serverConfig->Username &lt;$serverConfig->FromName&gt;</h3>"
        . "<b>pro:</b>  "
        . (is_array($address) ? implode(', ',$address) : $address)
        . "<br><b>předmět:</b> $subject"
        . "<p><b>text:</b> $body</p>"];
    $msg= 'ok'; // TEST bez odeslání
  }
  else {
    require_once "$abs_root/ezer$ezer_version/server/ezer_mailer.php";
    $mail= new Ezer_PHPMailer($serverConfig);
    if ( $mail->Ezer_error ) { 
      $msg= $mail->Ezer_error;
      goto end;
    }
    $mail->SetFrom($mail->From,$serverConfig->FromName);
    if (!$_TEST) {
      $mail->addReplyTo($replyto);
    }
    if (is_array($address)) {
      foreach ($address as $adr) {
        $mail->AddAddress($adr);   
      }
    }
    else 
      $mail->AddAddress($address);   
    if ($cc!='') $mail->AddCC($cc);
    $mail->Subject= $subject;
    $mail->Body= $body;
    try { 
      $msg= $mail->Ezer_Send(); // vrací 'ok' nebo chybovou hlášku
    } 
    catch(Exception $e) { 
      $msg= "CHYBA při odesílání mailu:" . $e->getMessage(); 
    }
  }
end:  
  if ($msg!='ok') 
    log_error($msg);
  return $msg;
}
# získání parametrizace SMTP 
function get_smtp($i_smtp) {
  $smtp= (object)['err'=>'','json'=>null];
  $smtp_json= select1_2('hodnota','_cis',"druh='smtp_srv' AND data=$i_smtp");
  $smtp= json_decode($smtp_json);
  if ( json_last_error() != JSON_ERROR_NONE ) {
    $smtp->err= "chyba ve volbe SMTP serveru" . json_last_error_msg();
  }
  return $smtp;
}
// <editor-fold defaultstate="collapsed" desc=" ------------------------------------------------------ pomocné funkce + modifikovaná volání Ezer">
function x($msg) {
  global $TEST;
  if ($TEST) echo(" $msg");
}
function array2object(array $array) {
  $object = new stdClass();
  foreach($array as $key => $value) {
    if(is_array($value)) {
      $object->$key = array2object($value);
    }
    else {
      $object->$key = $value;
    }
  }
  return $object;
}
function json_encode_2($s) { // ------------------------------------------------------ json encode_2
# korektní json encode
  $s= json_encode($s,JSON_UNESCAPED_UNICODE | JSON_HEX_QUOT | JSON_HEX_APOS);
  $s= strtr($s,['u0022'=>'&quot;','u0027'=>"&apos;"]);
  $sbr= nl2br_2($s);
  return $sbr;
}
function json_decode_2($s) {
  $s= str_replace("\n", "\\n", $s);
  $json= json_decode($s,false,512,JSON_INVALID_UTF8_IGNORE); // JSON objects will be returned as objects
  if (json_last_error() !== JSON_ERROR_NONE) {
    $msg= 'json_decode in line '.(__LINE__ - 2). ':' . json_last_error_msg();
    log_error($msg);
    throw new Exception($msg);
  }
  return $json;
}
function nl2br_2($s) { // ---------------------------------------------------------------- nl 2 br_2
  $sbr= str_replace(["\r\n","\r","\n"], "<br>",$s);
  return $sbr;
}
function br2nl($s) { // ------------------------------------------------------------------ br 2 nl_2
  return preg_replace('/\<br(\s*)?\/?\>/i', PHP_EOL, $s);
}
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
function sql2oddo($x1,$x2,$vnutit_rok=0) { // ------------------------------------------- sql 2 oddo
  $letos= date('Y');
  $d1= 0+substr($x1,8,2);
  $d2= 0+substr($x2,8,2);
  $m1= 0+substr($x1,5,2);
  $m2= 0+substr($x2,5,2);
  $r1= 0+substr($x1,0,4); 
  $r2= 0+substr($x2,0,4);
  if ( $x1==$x2 ) {  //zacatek a konec je stejny den
    $datum= "$d1. $m1" . ($r1!=$letos ? ". $r1" : '');
  }
  elseif ( $r1==$r2 ) {
    if ( $m1==$m2 ) { //zacatek a konec je stejny mesic
      $datum= "$d1. - $d2. $m1"  . ($r1!=$letos || $vnutit_rok ? ". $r1" : '');
    }
    else { //ostatni pripady
      $datum= "$d1. $m1 - $d2. $m2"  . ($r1!=$letos || $vnutit_rok ? ". $r1" : '');
    }
  }
  else { //ostatni pripady
    $datum= "$d1. $m1. $r1 - $d2. $m2. $r2";
  }
  return $datum;
}
function check_mail($mail,&$err) { // ----------------------------------------------- check mail
  $ok= filter_var(trim($mail), FILTER_VALIDATE_EMAIL);
  if ($ok===false) $err= "'$mail' je chybný email";
  return $ok!==false;
}
function check_datum($d_val,&$neuplne) { // -------------------------------------------- check datum
  $ok= 1;
  if (isset($d_val)) {
    $d= is_array($d_val) ? ($d_val[1] ?? $d_val[0]) : $d_val;
    $datum= str_replace(' ','',$d);
    $dmy= explode('.',$datum);
    if (!preg_match('/^\d+\.\d+\.\d+$/',$datum)) {
      $neuplne[]= "napište datum ve tvaru den.měsíc.rok (měsíc zapište číslem)";
      $ok= 0;
    }
    else {
      if (!checkdate($dmy[1],$dmy[0],$dmy[2])) {
        $neuplne[]= "opravte prosím datum (den.měsíc.rok) - je nějaké divné";
      $ok= 0;
      }
      elseif (date('Y')-$dmy[2] > 99 || date('Y')-$dmy[2] < 0) {
        $neuplne[]= "opravte prosím datum - nevypadá pravděpodobně";
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
      '86.49.254.42','86.49.254.138','80.95.103.170','217.64.3.170', // GAN
      '95.82.145.32'] // JZE
      );
}
function zvyraznit($msg,$ok=0) { // ------------------------------------------------------ zvyraznit
  $color= $ok ? 'green' : 'red';
  return "<b style='color:$color'>$msg</b>";
}
# --------------------------------------------------------------------------------------- ezer qry_2
function _ezer_qry($op,$table,$cond_key,$chng) { // --------------------------------------- ezer qry
# _ezer_qry = ezer_qry ALE
# $TEST>0 zapne trasování
# $TEST>1 nezapíše do db, vrací jako hodnotu 1
  global $USER;
  $ok= 1;
  $USER->abbr= 'WEB';
  $ok= ezer_qry_2($op,$table,$cond_key,$chng,(object)['soft_u'=>1]);
  return $ok;
}
function ezer_qry_2 ($op,$table,$cond_key,$zmeny,$par=null) { 
# oproti Ezer verzi netestuje old
# záznam změn do tabulky _track
# 1. ezer_qry("INSERT",$table,$x->key,$zmeny[,$key_id]);       -- vložení 1 záznamu
# 2. ezer_qry("UPDATE",$table,$x->key,$zmeny[,$key_id]);       -- oprava 1 záznamu
#     zmeny= [ zmena,...]
#     zmena= { fld:field, op:a|p|d|c, val:value, row:n }          -- pro chat
#          | { fld:field, op:u,   val:value, [old:value] }        -- pro opravu
#          | { fld:field, op:i,   val:value }                     -- pro vytvoření
# 3. ezer_qry("UPDATE_keys",$table,$keys,$zmeny[,$key_id]);    -- hromadná oprava pro key IN ($keys)
#     zmeny= { fld:field, op:m|p|a, val:value}                    -- SET fld=value
# verze 2:
# par =  { id_key, soft_u }
  global $ezer_db, $mysql_db, $mysql_db_track, $mysql_tracked, $USER, $TEST;
//                                                         debug($zmeny,"qry_update($op,$table,$cond_key)");
  $key_id=    $par->key_id??'';
  // 0 testuje se table.old při rozdílu se vyvolá chyba | 1 do track se vlozi zmeny.old bez testu
  $soft_u=    $par->soft_u??0;      
  $quiet=     $par->quiet??0;      
  $result= 0;
  $tracked= array();
  $keys= '???';                 // seznam klíčů
  $db_name= (isset($ezer_db[$mysql_db][5]) && $ezer_db[$mysql_db][5]!='') 
      ? $ezer_db[$mysql_db][5] : $mysql_db;

  $tab= str_replace("$db_name.",'',$table);
  if ( !$key_id ) $key_id= $tab=='pdenik' ? 'id_pokl' : str_replace('__','_',"id_$tab");
  $user= $USER->abbr;
//  user_test();
  // zpracování parametrů -- jen pro UPDATE
  switch ( $op ) {
  case 'INSERT':
    // vytvoření INTO a VALUES
    $flds= ''; $vals= ''; $del= '';
    $tracked[0]= array();
    if ( $zmeny ) {
      foreach ($zmeny as $zmena) {
        $fld= $zmena->fld;
        if ( $fld!='zmena_kdo' && $fld!='zmena_kdy' ) $tracked[0][]= $zmena;
//        if ( $fld=='id_cis' ) $id_cis= $zmena->val;
        $val= pdo_real_escape_string($zmena->val);
        $flds.= "$del$fld";
        $vals.= "$del'$val'";
        $del= ',';
      }
    }
    // provedení INSERT
    $key_val= 0;
    $qry= "INSERT INTO $table ($flds) VALUES ($vals)";
    $res= pdo_query_2($qry,$quiet);
    $result= $TEST<2 ? ($res===false ? 0 : pdo_insert_id()) : 2;
    $keys= $result;
    break;
  case 'UPDATE':
    // vytvoření SET a doplnění WHERE
    $set= ''; $and= ''; $del= '';
    $tracked[0]= array();
    foreach ($zmeny as $zmena) {
      $fld= $zmena->fld;
      if ( $fld!='zmena_kdo' && $fld!='zmena_kdy' ) $tracked[0][]= $zmena;
      $val= pdo_real_escape_string($zmena->val);
      switch ( $zmena->op ) {
      case 'u':
        $set.= "$del$fld='$val'";
        if ( isset($zmena->old) && !$soft_u ) {
          $old= pdo_real_escape_string($zmena->old);
          $and.= " AND $fld='$old'";
        }
        break;
      case 'i':
        $set.= "$del$fld='$val'";
        break;
      }
      $del= ',';
    }
    if ($soft_u) {
     // provedení UPDATE pro jeden záznam BEZX kontroly starých hodnot položek
      $key_val= $cond_key;
      $qry= "UPDATE $table SET $set WHERE $key_id=$key_val";
      $result= pdo_query_2($qry,$quiet);
//      $result= 1;
    }
    else {
      // provedení UPDATE pro jeden záznam s kontrolou starých hodnot položek
      $key_val= $cond_key;
      $qry= "SELECT $key_id FROM $table WHERE $key_id=$key_val $and ";
      if ( pdo_query_2($qry,$quiet) )  {
        $qry= "UPDATE $table SET $set WHERE $key_id=$key_val $and ";
        $result= pdo_query_2($qry,$quiet);
//        $result= 1;
      }
    }
    $keys= $key_val;
    break;
  }
  // zápis změn do _track
  if (strpos($table,".")!==false) {
    $table= explode('.',$table);
    $table= $table[count($table)-1];
  }
  if ( $mysql_db_track && count($tracked)>0 && strpos($mysql_tracked,",$table,")!==false ) {
    $qry= "";
    $now= date("Y-m-d H:i:s");
    $del= '';
    foreach (explode(',',$keys) as $i => $key) {
      $qry_prefix= "INSERT INTO _track (kdy,kdo,kde,klic,fld,op,old,val) VALUES ('$now','$user','$tab',$key";
      foreach ($tracked[$i] as $zmena) {
        $fld= $zmena->fld;
        $op= $zmena->op;
        switch ($op) {
        case 'd':
          $val= '';
          $old= pdo_real_escape_string($zmena->old_val);
          break;
        case 'c':
          $val= pdo_real_escape_string($zmena->val);
          $old= pdo_real_escape_string($zmena->old_val);
          break;
        default:
          // zmena->pip je definovaná ve form_save v případech zápisu hodnoty přes sql_pipe
          $val= pdo_real_escape_string($zmena->val);
          $old= isset($zmena->old) ? pdo_real_escape_string($zmena->old) : (
                isset($zmena->pip) ? pdo_real_escape_string($zmena->pip) : '');
          break;
        }
        $qry= "$qry_prefix,'$fld','$op','$old','$val'); ";
        $res= pdo_query_2($qry,1);
      }
    }
  }
//  elseif ( $table=='clen') fce_error("ezer_qry/no track for clen");
end:
  return $result;
}
function pdo_query_2($query,$quiet=false) { // ------------------------------- tudy jdou všechny SQL
  global $ezer_db, $curr_db, $y, $TEST, $totrace;
  $err= '';   
  $ok= 'ok';
  $insert_update= 0;
  $myqry= strtr($query,array('"'=>"'","<="=>'&le;',"<"=>'&lt;'));
  $pdo= $ezer_db[$curr_db][0];
//  try {
    $m= null;
    if ( preg_match('/^\s*(?:INSERT INTO|UPDATE)\s+(\w+)/i',$query,$m) ) {
      $insert_update= $m[1];
      if ($TEST<2) {
        $res= $pdo->exec($query);
        if ($res===false) $err= $pdo->errorInfo()[0];
      }
      else 
        $res= 1;
    }
    else {
      $res= $pdo->query($query);
      if ($res===false) $err= $pdo->errorInfo()[0];
    }
    if ( $err ) { 
      // význam SQLSTATE viz https://en.wikipedia.org/wiki/SQLSTATE
      $ok= 'ko';
      $err= "SQL error $err in $query";
      if ($quiet) {
        if ( isset($y) ) $y->error= (isset($y->error) ? $y->error : '').$err;
        log_error($err);
      }
      else {
        log_error($err);
        throw new Exception($err);
      }
    }
    // trasování
    if ( $TEST && $totrace && strpos($totrace,'M')!==false && $insert_update!=='_track') {
      $pretty= trim($myqry);
      if ($insert_update!='prihlaska')
        $pretty= "<b style='color:red'>$pretty</b>";
      if ( isset($y) ) $y->qry= (isset($y->qry)?"$y->qry\n":'')."* $ok \"$pretty\"\n ";
    }
//  }
//  catch (Exception $e) {
//    $msg= $e->getMessage();
//    if ($quiet) // aby nedošlo k zacyklení s log_error
//      log_error($msg);
//    else
//      throw new Exception($err);
//    $res= false;
//  }
  return $res;
} // <== tudy jdou všechny SQL 
function select_2($expr,$table='',$cond='') { // ------------------------------------------------ select 2
# navrácení hodnoty jednoduchého dotazu
# pokud je jediný argument je to celý dotaz
  if ( !$table ) {
    $result= array();
    $qry= $expr;
    $res= pdo_query_2($qry,1);
    if ( $res ) $result= pdo_fetch_row($res);
  }
# pokud $expr obsahuje čárku, vrací pole hodnot, pokud $expr je hvězdička vrací objekt, 
  elseif ( strstr($expr,",") ) {
    $result= array();
    $qry= "SELECT $expr FROM $table WHERE $cond";
//    display($qry);
    $res= pdo_query_2($qry,1);
    if ( $res ) $result= pdo_fetch_row($res);
  }
  elseif ( $expr=='*' ) {
    $result= array();
    $qry= "SELECT * FROM $table WHERE $cond";
    $res= pdo_query_2($qry,1);
    if ( $res ) $result= pdo_fetch_object($res);
  }
  else {
    $result= '';
    $qry= "SELECT $expr AS _result_ FROM $table WHERE $cond";
//    display($qry);
    $res= pdo_query_2($qry,1);
    if ( $res ) {
      $o= pdo_fetch_object($res);
      $result= $o ? $o->_result_ : '';
    }
  }
//                                                 debug($result,"select");
  return $result;
}
function select1_2($expr,$table='',$cond='') { // ---------------------------------------------- select1 2
# navrácení hodnoty jednoduchého dotazu - $expr musí vracet jednu hodnotu
# pokud je jediný argument je to celý dotaz
  $result= '';
  $qry= $table ? "SELECT $expr AS _result_ FROM $table WHERE $cond" : $expr;
  $res= pdo_query_2($qry,1);
  if ( $res ) {
    $o= pdo_fetch_row($res);
    $result= $o ? $o[0] : '';
  }
  return $result;
}
function select_object_2($expr,$table,$cond) { // ---------------------------------- select object_2
# navrácení hodnot jednoduchého jednoznačného dotazu jako objektu (funkcí pdo_fetch_object)
  $result= array();
  $qry= "SELECT $expr FROM $table WHERE $cond";
  $res= pdo_query_2($qry,1);
  if ( $res ) $result= pdo_fetch_object($res);
  return $result;
}
function query_track_2($qry,$quiet=false) { // --------------------------------------- query track_2
# oproti Ezer verzi netestuje old
# provede některá SQL včetně zápisu do _track
#   INSERT INTO tab (f1,f2,...) VALUES (v1,v2,...) ... vrací ID nového záznamu
#   UPDATE tab SET f1=v1, f2=v2, ... WHERE id_tab=v0
# kde vi jsou jednoduché hodnoty: číslo nebo string uzavřený v apostorfech 
# trasovaná tabulka musí být uvedena v $mysql_tracked, jeji klíč musí být buďto ve tvaru id_tab
# nebo být uveden v $mysql_tracked_id jako tab=>id
  global $mysql_db_track, $mysql_tracked, $mysql_tracked_id, $TEST;
  // rozklad výrazu: 1:table, 2:field list, 3:values list
  $res= 0;
  $m= null;
  $ok= preg_match('/(INSERT)\s+INTO\s+([\w\.]+)\s+\(([,\s\w]+)\)\s+VALUE(?:S|)\s+\(((?:.|\s)+)\)$/',$qry,$m)
    || preg_match('/(UPDATE)\s+([\w\.]+)\s+SET\s+(.*)\s+WHERE\s+([\w]+)\s*=\s*(.*)\s*/m',$qry,$m)
    || preg_match('/(DELETE)\s+FROM\s+([\w\.]+)\s+WHERE\s+([\w]+)\s*=\s*(.*)\s*/m',$qry,$m)
  ;
//  debug($m);
  $fce= $m[1] ?: '';
  $tab= $m[2] ?: '';
  if ( $mysql_db_track && strpos($mysql_tracked,",$tab,")!==false ) {
    global $USER;
    $abbr= isset($USER->abbr) ? $USER->abbr : 'WEB';
    if ($ok && $fce=='INSERT') {
      $fld= explode_csv($m[3]); 
      $val= explode_csv($m[4]); 
      $res= pdo_query_2($qry,$quiet);
      $key_val= $TEST<2 ? pdo_insert_id() : 3;
      for ($i= 0; $i<count($fld); $i++) {
        $f= $fld[$i];
        $v= $val[$i];
//        if ($v[0]=="'") $v= substr($v,1,-1);
//        $v= pdo_real_escape_string($v);
        pdo_query_2("INSERT INTO _track (kdy,kdo,kde,klic,op,fld,val) "
            . "VALUE (NOW(),'$abbr','$tab',$key_val,'I','$f',$v)",$quiet);
      }
      $res= $key_val;
    }
    elseif ($ok && $fce=='UPDATE') {
  //    debug($m);
      $sets= explode_csv($m[3]); 
      $key_id= $m[4];
      $key_val= $m[5];
      // kontrola podmínky
      $ok= $key_id=="id_$tab" || $key_id==$mysql_tracked_id[$tab];
      if ($ok) {
        foreach ($sets as $set) {
          list($fld,$val)= explode('=',$set,2);
          if ($val[0]=="'") $val= substr($val,1,-1);
          $val= pdo_real_escape_string($val);
          pdo_query_2("INSERT INTO _track (kdy,kdo,kde,klic,op,fld,val) "
            . "VALUE (NOW(),'$abbr','$tab',$key_val,'u','$fld','$val')",$quiet);
        }
        $res= pdo_query_2($qry,$quiet);
      }
    }
    elseif ($ok && $fce=='DELETE') {
      $key_id= $m[3];
      $key_val= $m[4];
      // kontrola podmínky
      $ok= $key_id=="id_$tab" || $key_id==$mysql_tracked_id[$tab];
      if ($ok) {
        pdo_query_2("INSERT INTO _track (kdy,kdo,kde,klic,op) "
            . "VALUE (NOW(),'$abbr','$tab',$key_val,'x')",$quiet);
        $res= pdo_query_2("DELETE FROM $tab WHERE $key_id=$key_val",$quiet);
      }
    }
    else {
      $ok= 0;
    }
    if (!$ok) {
      log_error("funkce query-track nemá předepsaný tvar argumentu ale $qry");
    }
  }
  else {
    $res= pdo_query_2($qry,$quiet);
  }
end:
  return $res;
} 
function map_cis_2($druh,$val='zkratka',$order='poradi') { // ---------------------------- map cis_2
# zjištění hodnot číselníku a vrácení jako překladového pole
#   array (data => $val, ...)
  global $mysql_db, $ezer_db;
  $db= $mysql_db;
  if ( isset($ezer_db[$db][5])) {
    $db= $ezer_db[$db][5];
  }
  $cis= array();
  $qry= "SELECT * FROM $db._cis WHERE druh='$druh' ORDER BY $order";
  $res= pdo_query_2($qry,1);
  while ( $res && $row= pdo_fetch_assoc($res) ) {
    $cis[$row['data']]= $row[$val];
  }
  return $cis;
}
// </editor-fold>
