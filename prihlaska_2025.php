<?php
/*
  (c) 2025 Martin Smidek <martin@smidek.eu>

  3. verze online přihlašování pro YMCA Setkání (jen obnovy a LK MS YS)
  debuger je lokálne nastaven pro verze PHP: 7.2.33 - musí být ručně spuštěn Chrome

 */
$MYSELF= 'prihlaska_2025';
$VERZE= '2025.1'; // verze přihlášek: rok.release

session_start(['cookie_lifetime'=>60*60*24*2]); // dva dny
error_reporting(E_ALL);
ini_set('display_errors', 'On');

// detekce varianty: normální nebo testovací 
$_TEST=  preg_match('/-test/',$_SERVER["SERVER_NAME"]) ? '_test' : '';
$_ANSWER= $_SESSION[$_TEST?'dbt':'db2']['user_id']??0;
     
//$TEST_mail= 'martin@smidek.eu';               // v létě nebyli
//$TEST_mail= 'martin.smidek@gmail.com';
//$TEST_mail= 'marie@smidkova.eu';
//$TEST_mail= 'jakub@smidek.eu';
//$TEST_mail= 'kancelar@setkani.org';           // v létě dělali VPS
//$TEST_mail= 'zahradnicek@fnusa.cz';
//$TEST_mail= 'petr.janda@centrum.cz';
//$TEST_mail= 'p.kvapil@kvapil.cz';
//$TEST_mail= 'bucek@fem.cz';
$TEST_mail= 'hanasmidkova@seznam.cz';
//$TEST_mail= 'j-novotny@centrum.cz';
//$TEST_mail= 'jslachtova@seznam.cz';
//$TEST_mail= 'z.krtek@seznam.cz';
//$TEST_mail= 'sequens@seznam.cz';              // oba osobní ale ten stejný
//$TEST_mail= 'nemec_pavel@hotmail.com';        // oba jen rodinný
//$TEST_mail= 'pavel.bajer@volny.cz';           // bezdětní
//$TEST_mail= 'milada.barotova@gmail.com';      // vdova
//$TEST_mail= 'lina.ondra@gmail.com';           // úmrtí dítěte
//$TEST_mail= 'jandevaty9@seznam.cz';           // jedno dítě
//$TEST_mail= '';

$errors= [];

// ========================================================================== parametrizace aplikace
// texty a nastavení položky jsou popsány ve funkci položky - jsou pozměněny podle načtené akce
$TEXT= (object)[
  'usermail_nad1' => 
      'Abychom ověřili, že se přihlašujete právě vy, napište svůj mail, pošleme na něj přihlašovací PIN.',  
  'usermail_pod1' => 
      '<i>Přihláška obsahuje otázky určené oběma manželům - je potřeba, abyste ji vyplňovali společně.</i>',  
  'usermail_nad2' => 
      'Na uvedený mail vám byl zaslán PIN, opište jej vedle své mailové adresy.
       <br><i>(pokud PIN nedošel, podívejte se i složek Promoakce, Aktualizace, Spam, ...)</i>',  
  'usermail_nad3' => 
      'Tento mail v evidenci YMCA Setkání nemáme, tato akce předpokládá, že jste se již nějaké naší 
        akce zúčastnil/a, přihlaste se prosím pomocí toho, který jste tehdy použil/a',
  'usermail_nad4' => 
      'Tento mail v evidenci YMCA Setkání nemáme, pokud jste se již nějaké naší akce zúčastnil/a, 
       přihlaste se prosím pomocí toho, který jste tehdy použil/a',
  'usermail_nad4' => 
      'Tento mail máme na základě předchozích přihlášek a účastí na našich akcích uvedený 
       ve více souvislostech - zvolte prosím správnou možnost.',
  'osoby_nad1' => 
      'Poznačte, koho na akci přihlašujete. Zkontrolujte a případně upravte zobrazené údaje.',
  'rozlouceni1' => 
      'Přejeme Vám hezký den.',
  'rozlouceni2' => 
      'Přejeme Vám příjemný pobyt.',
];
$akce_default= [ // položky které aplikace umí
//  'p_pozde'       =>  0, // od teď přihlášené brát jen jako náhradníky
  'p_rodina'      =>  0, // rodinné přihlášení
  'p_deti'        =>  0, // ... s dětmi
  'p_pecouni'     =>  0, // ... mohou mít pečouny
  'p_pro_LK'      =>  0, // pro manželský pár s dětmi a osobními pečovateli na LK MS
  'p_rod_adresa'  =>  0, // umožnit kontrolu a úpravu rodinné adresy 
  'p_obcanky'     =>  0, // umožnit kontrolu a úpravu číslo obč. průkazu
  'p_kontakt'     =>  0, // umožnit kontrolu a úpravu telefonu a emailu
  'p_souhlas'     =>  0, // vyžadovat souhlas (GDPR) 
  'p_ukladat'     =>  0, // povolit znovunačtení při přihlášení
  'p_kontrola'    =>  0, // vynutit kontrolu dat před uložením
// -- jen pro obnovy MS
  'p_obnova'      =>  0, // OBNOVA MS: neúčastníky aktuálního LK brát jako náhradníky
  'p_vps'         =>  0, // OBNOVA MS: nastavit funkci VPS podle letního kurzu
// -- jen pro LK MS
  'p_upozorneni'  =>  0, // LETNÍ KURZ MS: vyžadovat akceptaci upozornění
//  'p_dokument'    =>  0, // LETNÍ KURZ MS: vytvořit PDF a uložit jako dokument k pobytu
]; 
function polozky() { // -------------------------------------------------------------------- položky
  global $akce, $options, $sub_options, $p_fld, $r_fld, $o_fld;
  $options= [
      'role'      => [''=>'vztah k rodině?','a'=>'manžel','b'=>'manželka','d'=>'dítě','p'=>'jiný vztah'],
      'cirkev'    => [''=>'něco prosím vyberte',23=>'křesťan',1=>'katolická',2=>'evangelická',7=>'bratrská',
                      4=>'apoštolská',19=>'husitská',22=>'metodistická',18=>'baptistická',5=>'adventistická',
                      24=>'jiná',21=>'hledající',3=>'bez příslušnosti',16=>'nevěřící'],
      'vzdelani'  => [''=>'něco prosím vyberte',1=>'ZŠ',4=>'vyučen/a',2=>'SŠ',33=>'VOŠ',3=>'VŠ',16=>'VŠ student'],
      'funkce'    => map_cis_2('ms_akce_funkce','zkratka'),
      'Xvps'      => [''=>'něco prosím vyberte',1=>'počítáme se službou VPS',
                      2=>'raději bychom byli v "odpočinkové" skupince'],
    ];
  $options['cirkev']['']= 'něco prosím vyberte';
  $options['vzdelani']['']= 'něco prosím vyberte';
  // v $sub_options je převodní tabulka plné->zúžené podle _cis.ikona
  $sub_options= [
      'cirkev'    => map_cis_2('ms_akce_cirkev','ikona'),
      'vzdelani'  => map_cis_2('ms_akce_vzdelani','ikona'),
    ];
  // definice obsahuje:  položka => [ délka , popis , formát, u osob možné role ]
  //   X => pokud jméno položky začíná X, nebude se ukládat, jen zapisovat do PDF
  //   * => pokud popis začíná hvězdičkou bude se údaj vyžadovat (hvězdička za zobrazí červeně)
  //        je to ale nutné pro každou položku naprogramovat 
  $p_fld= [ // zobrazené položky tabulky POBYT, nezobrazené: id_pobyt
      'pracovni'    =>['64/4','sem prosím napište vzkaz organizátorům, např. informace, které nebylo možné nikam napsat','area'],
      'funkce'      =>[0,'funkce na akci','select'],
      'Xvps'        =>[15,'* služba na kurzu','select'], // bude vložena jen pro neodpočívající VPS
    ];
  $r_fld= array_merge(
    [ // položky tabulky RODINA
      'nazev'     =>[15,'* název rodiny',''],
      'ulice'     =>[15,'* ulice a č.or. NEBO č.p.',''],
      'psc'       =>[ 5,'* PSČ',''],
      'obec'      =>[20,'* obec/město',''],
      'spz'       =>[12,'SPZ auta na akci','']],
    $akce->p_pro_LK || $akce->p_obnova ? [
      'r_umi'      =>[ 0,'seznam odborností','x'], // podle answer_umi např. 1=VPS
    ] : [],
    $akce->p_pro_LK ? [
      'datsvatba' =>[ 9,'* datum svatby','date'],
      'r_ms'       =>[12,'počet účastí na jiném kurzu MS než YMCA Setkání či YMCA Familia','number'],
      'r_umi'      =>[ 0,'seznam odborností','x'], // podle answer_umi např. 1=VPS
    ] : []
  );
  $o_fld= array_merge(
    [ // položky tabulky OSOBA
      'spolu'     =>[ 0,'pojede<br />na akci','check_spolu','abdp'],
      'o_pecoun'  =>[ 0,'','x','d'],  // =0 tlačítko, >0 id osobního pečovatele
      'o_dite'    =>[ 0,'','x','p'],  // id opečovávaného dítěte
      'jmeno'     =>[ 7,'* jméno','','abdp'],
      'prijmeni'  =>[10,'* příjmení','','abdp'],
      'rodne'     =>[10,'rozená','','ab'],
      'narozeni'  =>[10,'* datum narození','date','abdp'],
      'umrti'     =>[10,'rok úmrtí','','abdp'],
      'role'      =>[ 9,'vztah k rodině?','select','abdp'],
      'vztah'     =>[ 9,'manžel/maželka','select','ab'],
      'note'      =>['70/2','poznámka (léky, alergie, apod.)','area','d']],
    $akce->p_kontakt ? [
      'telefon'   =>[15,'telefon','','abp'],
      'email'     =>[35,'* e-mailová adresa','mail','ab']
      ] : [],
    $akce->p_obcanky ? [
      'obcanka'   =>[11,'číslo OP nebo pasu','','abp'],
      ] : [],
    $akce->p_pro_LK ? [
      'vzdelani'  =>[20,'* vzdělání','sub_select','ab'],
      'zamest'    =>[35,'* povolání, obor ve kterém pracujete/budete pracovat','','ab'],
      'zajmy'     =>[35,'* zájmy','','ab'],
      'jazyk'     =>[20,'znalost jazyků (Aj, Nj, ...)','','ab'],
      'aktivita'  =>[35,'aktivita v církvi, ve společnosti','','ab'],
      'cirkev'    =>[25,'* vztah ke křesťanství/církev','select','ab'],
      'Xpecuje_o' =>[12,'* bude pečovat o ...','','p'],
      'Xpovaha'    =>['70/2','* popiš svoji povahu','area','ab'],
      'Xmanzelstvi'=>['70/2','* vyjádři se o vašem manželství','area','ab'],
      'Xocekavani' =>['70/2','* co očekáváš od účasti na MS','area','ab'],
      'Xrozveden'  =>[20,'* předchozí manželství? (ne, počet)','','ab'],
      'Xupozorneni'=>[ 0,'*'.$akce->upozorneni,'check','ab'],
    ] : []
  );
  // případné opravy podle akce
  foreach ($akce as $key=>$val) {
    if ($key[0]=='t') {
      $fld= substr($key,3);
      $x_fld= null;
      switch ($key[1]) {
        case 'o': $x_fld= &$o_fld; break;
        case 'r': $x_fld= &$r_fld; break;
        case 'p': $x_fld= &$p_fld; break;
      }
      if ($x_fld && isset($x_fld[$fld])) {
        $x_fld[$fld][1]= $val;
      }
    }
  }
} // definice položek formuláře
// ========================================================================================== .MAIN.
// rozlišení na volání z příkazové řádky - úvodní s prázdným SESSION nebo po ctrl-r
// a na volání přes AJAX z klientské části

// pro první volání nebo ctrl-r nastav akci
// hodnoty pro test a mail musí být navržené přes GET - uplatní se jen při během přihlášení do Answeru
//   $MAIL:  1 - maily se posílají | 0 - mail se jen ukáže - lze nastavit url&mail=0
//   $TEST:  0 - bez testování | 1 - výpis stavu a sql | 2 - neukládat | 3 - login s testovacím mailem
if (!count($_POST)) {
  if (!isset($_GET['akce']) ) {
    die("Online přihlašování není k dispozici."); 
  }
  else {
    $TEST= $_GET['test']??0 ? ($_ANSWER?(0+$_GET['test']):0) : 0;
    $MAIL= $_GET['mail']??1 ? 1 : ($_ANSWER?0:1);
    initialize($_GET['akce']); // přenese TEST i MAIL
  }
}
if (!isset($_SESSION['akce'])) { session_reset(); }
$AKCE= "A_{$_SESSION['akce']}";
$vars= $_SESSION[$AKCE]??(object)[];
$TEST= $vars->TEST;
$MAIL= $vars->MAIL;

// pouze pro lokální testování
if ($_SERVER["SERVER_NAME"]=='answer-test.bean') {
  $MAIL= 0; // $MAIL=0 zabrání odeslání, jen zobrazí mail v trasování
  $TEST= 1;
}

//$TEST= 2; // ===================================================================================== 2 => potlačení INSERT a UPDATE

connect_db();           // napojení na databázi a na Ezer 
read_akce();            // načtení údajů o akci z Answeru 

$DOM_default= (object)[ // pro start aplikace s prázdným SESSION
  // počáteční stav
  'user'=>'hide',
  'usermail'=>'show', 'email'=>'enable', 'pin'=>'hide', 
  'zadost_o_pin'=>'show', 'kontrola_pinu'=>'hide',
  'usermail_nad'=>$TEXT->usermail_nad1, 'usermail_pod'=>$TEXT->usermail_pod1, 
  'pin'=>'hide', 'kontrola_pinu'=>'hide', 'form'=>'hide',
  // testování
  'info'=> $MAIL ? 'hide' : 'simulace mailů'.($TEST>1 ? ', bez zápisu' : ''),
  'mailbox'=>'hide', 
  'errorbox'=>'hide',
  'alertbox'=>'hide',
];

if ( count($_POST) ) {
  // volání přes AJAX z existující klientské části
  polozky();              // popis získávaných položek
  $fce= $_POST['cmd'];
  $args= $_POST['args']??[];
  if ($TEST) {
    $call= "function <b>$fce</b>";
    foreach ($args as $name=> $value) {
      if ($name=='*')
        $call.= "<br>$name";
      else 
        $call.= "<br>$name=$value";
    }
  }
  $DOM= (object)[];
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
    $dump= debugx($vars);
    $DOM->trace= "$call$trace<hr>$dump";
  }
  // pokračujeme v JS
  header('Content-type: application/json; charset=UTF-8');
  $yjson= json_encode($DOM);
  echo $yjson;
}
else {
  // volání z příkazové řádky vytvoří novou klienstkou část 
  page();
  // po vytvoření klienta je volána funkce start()
}

// -------------------------------------------------------------zahájení nebo pokračování po ctrl-r
function start() { 
# zobrazí id.mail a cmd.zadost_o_pin, skryje vše ostatní
  initialize($_SESSION['akce']??0);
}
// -------------------------------------------------------------------- obnova počátečního nastavení
function initialize($id_akce) { 
# zobrazí id.mail a cmd.zadost_o_pin, skryje vše ostatní a zapomene všechny hodnoty
  global $DOM, $DOM_default, $AKCE, $vars, $TEST, $MAIL;
  do_session_restart();
  if ($id_akce) {
    $_SESSION['akce']= $id_akce;
    $AKCE= "A_$id_akce"; // ID akce pro SESSION
    $_SESSION[$AKCE]= (object)[
      'id_akce'=>$id_akce,
      'TEST'=>$TEST, 'MAIL'=>$MAIL,
      'ido'=>0, 'idr'=>0, 'pin'=>'',  // ověřený klient
      'form'=>(object)[],
  //    'DOM'=>$DOM_default,
      'cleni'=>[],
      'rodina'=>[],
      'pobyt'=>(object)[],
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


// ============================================================================== reakce na tlačítka
// každá z následujících funkcí je reakcí na kliknutí na nějaké tlačítko
// a dostává parametry specifikované u tlačítka. 
// Po ukončení funkce odevzdá změněné DOM a čeká se na další reakci uživatele
// ------------------------------------------------------------------------------------ zadost o pin
function zadost_o_pin($email) { trace();
# pro korektní id.email pošle mail s PINem a zobrazí cmd.kontrola_pinu
# pro nekorektní id.email zobrazí chybu a opakuje
  global $DOM, $TEST, $TEXT, $vars, $akce;
  $chyby= '';
  $ok= check_mail($email,$chyby);
  if ($ok) {
    // zašleme PIN 
    $pin= rand(1000,9999);
    $msg= simple_mail($akce->garant_mail, $email, "PIN ($pin) pro prihlášení na akci",
        "V přihlášce na akci napiš vedle svojí mailové adresy $pin a pokračuj tlačítkem [Ověřit PIN]");
    if ( $msg!='ok' ) {
      $chyby.= "Litujeme, mail s PINem se nepovedlo odeslat, přihlas se prosím na akci jiným způsobem."
          . "<br>($msg)";
    }
    if ( $msg=='ok' ) { // simple_mail může doplnit DOM->mailbox
      $vars->pin= $pin;
      $DOM->zadost_o_pin= "hide";
      $DOM->email= "disable";
      $DOM->pin= $TEST ? ["show",$pin] : "show"; 
      $DOM->kontrola_pinu= "show";
      $DOM->usermail_nad= $TEXT->usermail_nad2;
      $DOM->usermail_pod= "Byl vám poslán mail";
    }
  }
  if ($chyby) {
    $DOM->usermail_pod= zvyraznit("<p>$chyby</p>");
  }
} // zadost o pin
// ----------------------------------------------------------------------------------- kontrola pinu
function kontrola_pinu($email,$pin) { 
# pokud je nesprávný pin ???
# pokud je správný pin zjisti jestli je email známý a jednoznačný
# pokud je známý a jednoznačný vyplň user a připrav formulář přihlášení osob
# pokud je neznámý umožni zadání jiného
# pokud je nejednoznačný umožni zadání jiného
  global $DOM, $TEXT, $vars;
  if ($pin!=$vars->pin) { // chyba pinu
    $DOM->usermail_pod= zvyraznit("<p>Do mailu jsme poslali odlišný PIN, podívejte se prosím pozorně</p>");
  }
  else { // pin je ok - založíme záznam v tabulce prihlaska
    $vars->email= $email; // pro korespondenci
//    log_open($email);  // email je ověřený  <==== posunuto až do klient()
    
    // zjistíme, zda to může být rozpracovaná přihláška
    $open= log_find_saved($email); // uložená přihláška a nejsou přihlášení
    if ($open) { // -------- nalezena rozepsaná přihláška
      // doplní vars: continue, ido, idr
      $DOM->user= ["show","<i class='fa fa-user'></i> <br>$email"];
      vyber("Chcete pokračovat ve vyplňování přihlášky uložené $open?",
          ["ANO:klient:=$vars->ido/$vars->idr,=0","NE:klient:=$vars->ido/$vars->idr,=1"]);
      goto end;
    } // nalezena rozepsaná přihláška
    
    // jinak zjistíme, zda jej máme v databázi
    $regexp= "REGEXP '(^|[;,\\\\s]+)$email($|[;,\\\\s]+)'";
    list($pocet,$idors)= select_2(
       "SELECT COUNT(id_osoba),GROUP_CONCAT(CONCAT(id_osoba,'/',IFNULL(id_rodina,0),'/',IFNULL(role,'-')))
        FROM osoba LEFT JOIN tvori USING (id_osoba)
        WHERE deleted='' AND kontakt=1 AND email $regexp ORDER BY IFNULL(role,'')");
    display("osoba => $pocet: $idors");
    
    if ($pocet==0) {
      list($pocet,$idors)= select_2(
         "SELECT COUNT(id_osoba),GROUP_CONCAT(CONCAT(id_osoba,'/',id_rodina,'/',role))
          FROM osoba AS o JOIN tvori USING (id_osoba) JOIN rodina AS r USING (id_rodina)
          WHERE o.deleted='' AND r.deleted='' AND kontakt=0 AND emaily $regexp ORDER BY role");
      display("rodina => $pocet: $idors");
    }

    if ($pocet==1) { // -------------------------------- známý a jednoznačný mail
      list($ido,$idr)= explode('/',$idors);
      klient("$ido/$idr",1);
    } // známý a jednoznačný mail
    elseif ($pocet==0) { // -------------------------------- neznámý mail
      $DOM->usermail_nad= $TEXT->usermail_nad3;
      $DOM->email= 'enable';
      $DOM->kontrola_pinu= "hide";
      $DOM->pin= "hide";
      $DOM->zadost_o_pin= "show";
      $DOM->usermail_pod= '';
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
        foreach ($cleni as $clen) {
          list($ido,$idr,$role)= explode('/',$clen);
          if (count($rodic)>1 && !in_array($role,['a','b'])) continue;
          $rodina= $idr ? 'rodina '.select('nazev','rodina',"id_rodina=$idr") : 'bez rodiny';
          list($jmeno,$prijmeni)= select('jmeno,prijmeni','osoba',"id_osoba=$ido");
          $dotazy[]= "$jmeno $prijmeni:klient:=$ido/$idr,1:$rodina";
        }
        vyber($TEXT->usermail_nad4,$dotazy);
      }
    } // nejednoznačný mail      
  }
end:  
} // overit pin
// ------------------------------------------------------------------------------------------- kient
function klient($idor,$nova_prihlaska=1) { 
# $id je nositelem přihlašovacího mailu
  global $DOM, $TEXT, $vars, $akce;
  list($ido,$idr)= explode('/',$idor);
  list($jmena)= select_2("SELECT CONCAT(jmeno,' ',prijmeni) FROM osoba WHERE id_osoba=$ido");
  // osobu známe  - zjistíme zda již není přihlášen
  $DOM->user= ["show","<i class='fa fa-user'></i> $jmena<br>$vars->email"];
  list($idp,$kdy,$kdo/*,$web_json*/)= select_2("id_pobyt,IFNULL(kdy,''),IFNULL(kdo,''),web_json",
      "pobyt JOIN spolu USING (id_pobyt) "
      . "LEFT JOIN _track ON klic=id_pobyt AND kde='pobyt' AND fld='id_akce' ",
      "(id_osoba={$ido} OR i0_rodina=$idr) AND id_akce=$akce->id_akce "
      . "ORDER BY id_pobyt DESC LIMIT 1");
  if ($idp) { // ------------------------------- už jsou zapsaní 
    $od_kdy= $kdy ? ' od '.sql_time1($kdy) : '';
    $kym= $kdo=='WEB' ? ' online přihláškou.' : '';
    if ($kdo && $kdo!='WEB') {
      list($jmeno,$prijmeni)= select_2('forename,surname','_user',"abbr='$kdo'");
      $kym= ". $jmeno $prijmeni";
    } 
    log_write('id_pobyt',$idp);
    $DOM->usermail= "hide";
    $DOM->rozlouceni_text= $TEXT->rozlouceni2;
    hlaska("Na tuto akci jste již $od_kdy přihlášeni$kym","prazdna");
  }
  else {
    log_open($vars->email);  // email je ověřený 
    $DOM->usermail= 'hide';
    $vars->klient= $jmena;
    $vars->ido= $ido;
    $vars->idr= $idr;
    log_write('id_osoba',$vars->ido);
    log_write('id_rodina',$vars->idr);
    return prihlaska($nova_prihlaska);
  }
} // klient
// -------------------------------------------------------------------------------------- přihlas se
function prihlaska($nova=1) { 
# připrav prázdný formulář přihlášení osob
# doplň DOM o položky osob
  global $DOM, $vars, $akce;
  // podle ido,idr nastav počáteční informace o klientovi
  kompletuj_pobyt($vars->idr,$vars->ido);
  // nastavení formuláře
  if (($vars->continue??0) && $nova==0) {
    log_load_changes();   // z uchované přihlášky
    log_write_changes();  // do současné
  }
  else {
    // počáteční 
    $vars->form= (object)[
        'kontrola'=>[], // seznam položek s chybou
        'pass'=>0,      // inicializovat pozici pro 0
        'par'=>1,
        'deti'=>$akce->p_deti, // 0=nic, 1=tlačítko, 2=seznam
        'pecouni'=>$akce->p_pecouni, // 0=nejsou povolení
        'rodina'=>$akce->p_rod_adresa,'pozn'=>1,'souhlas'=>0,
//        'oprava'=>0,    // 1 => byla načtena již uložená přihláška a je možné ji opravit
//        'todo'=>0,      // označit červeně chybějící povinné údaje po kontrole formuláře
//        'exit'=>0,      // 1 => první stisk 
    ];
    log_write_changes();  // zapiš počáteční skeleton form
  }
  $form= form();
  // změny zobrazení
  $DOM->usermail= 'hide';
  $DOM->form= ['show',$form];
  if ($vars->form->par) form_manzele();
  if ($vars->form->deti) form_deti($vars->form->deti);
} // prihlaska
// ---------------------------------------------------------------------------- zkontrolovat úplnost
function kontrolovat() { 
# zkontroluje bezchybnost a úplnost přihlášky
  global $DOM, $vars;
  $chybi= [];
  $spolu= 0;
  // ------------------------------ je aspoň jeden přihlášený?
  foreach (array_keys($vars->cleni) as $id) {
    $spolu+= get('c','spolu',$id);
  }
  if (!$spolu) {
    hlaska('Přihlaste prosím na akci aspoň jednu osobu');
    goto end;
  }
  // ------------------------------ jsou vyplněné všechny údaje?
  // osobní údaje přítomných
  foreach (array_keys($vars->cleni) as $id) {
    $spolu= get('o','spolu',$id);
    if ($spolu) { $chybi= array_merge($chybi,elems_missed('o',$id)); }
  }
  // rodinné údaje
  $idr= key($vars->rodina);
  $chybi= array_merge($chybi,elems_missed('r',$idr));
  // údaje k pobytu
  $chybi= array_merge($chybi,elems_missed('p',0));  
  if ($chybi) {
    foreach ($chybi as $name) { $DOM->$name= 'ko'; }
    hlaska('Doplňte označené rodinné údaje a osobní alespoň u těch, které přihlašujete na akci ...'); 
    goto end; 
  }
  // ------------------------------ jsou opravené chybné údaje?
  foreach ($vars->form->kontrola??[] as $name) {
    $chybi[]= $name; $DOM->$name= 'ko'; 
  }
  if (count($chybi)) { 
    hlaska('opravte označené chybné údaje'); 
    goto end;   
  }
  // -------------------------------- pokud vše prošlo pošli přihlášku
  prihlasit();
end:  
  debug($chybi,"chybějící ID");
}
// --------------------------------------------------------------------------------------- přihlásit
function prihlasit() { 
# zapíše přihlášku do Answeru
  global $DOM, $vars, $akce, $errors;
  // vytvoření pobytu
  log_append_stav('zapis');
  // účast jako ¨účastník' pokud není p_obnova => neúčast na LK znamená "náhradník"
  $ucast= 0; // = účastník
  if (isset($vars->pobyt->Xvps)) { // volba VPS (ne)sloužit
    $sluzba= get('p','Xvps');
    $ucast= $sluzba==1 ? 1 : 0;
  }
  set('p','funkce',$ucast);
  // vytvoříme nový záznam pro pobyt, pokud nejde o opravu
  if (!$vars->pobyt->id_pobyt) 
    db_open_pobyt();
  // ------------------------------ oprav rodinné údaje případně vytvoř rodinu
  db_vytvor_nebo_oprav_rodinu();
  if (count($errors)) goto db_end;
  
  // ------------------------------ přidej (případně vytvoř) členy rodiny
  foreach (array_keys($vars->cleni) as $id) {
    // přidání člena rodiny
    db_vytvor_nebo_oprav_clena($id);
    if (count($errors)) goto db_end;
  }
  
  // ------------------------------ vyřeš osobní pečovatele
  foreach (array_keys($vars->cleni) as $id_dite) {
    $id_pecoun= get_pecoun($id_dite);
    if ($id_pecoun) {
      db_zapis_pecovani($id_dite,$id_pecoun);
    }
  }

  // ------------------------------ vše zapiš a uzavři formulář závěrečnou zprávou a mailem
  db_close_pobyt();
  log_write_changes(); // po zápisu do pobytu
  $ucastnici= ''; $del= ''; 
  $emails= [$vars->email]; 
  foreach (array_keys($vars->cleni) as $id) {
    $spolu= get('o','spolu',$id);
    if (!$spolu) continue;
    $jmeno= get('o','jmeno',$id);
    $prijmeni= get('o','prijmeni',$id);
    $ucastnici.= "$del$jmeno $prijmeni"; 
    $del= ', ';
    if (!in_array(get_role($id),['a','b'])) continue;
    $ems= preg_split('/[,;]/',get('o','email',$id));
    foreach ($ems as $email) {
      $email= trim($email);
      if ($email && !in_array($email,$emails)) 
        $emails[]= $email;
    }
  }
  // mail 
  $emaily= implode(', ',$emails);
  $mail_subj= "Potvrzení přijetí přihlášky ($vars->klient) na akci $akce->nazev.";
  $mail_body= "Dobrý den,<p>dostali jsme vaši přihlášku na akci "
  . "<b>$akce->nazev, $akce->misto</b> $akce->oddo pro účastníky $ucastnici."
  . "<br>Zaslané údaje zpracujeme a do 5 dnů vám pošleme odpověď. "
  . "<p>S přáním hezkého dne<br>$akce->garant_jmeno"
  . "<br><a href=mailto:'$akce->garant_mail'>$akce->garant_mail</a>"
  . "<br>$akce->garant_telefon (v podvečerních hodinách)</p>"
  . "<p><i>Tato odpověď je vygenerována automaticky</i></p>";
  $ok_mail= simple_mail($akce->garant_mail, $emails, $mail_subj,$mail_body,$akce->garant_mail); 
  if ($ok_mail!='ok') { $errors[]= $ok_mail; goto db_end; }
  $DOM->form= ['show',
      "Vaše přihláška byla zaevidována a poslali jsme Vám potvrzující mail na $emaily.
       <br>$akce->garant_jmeno"];
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
// ------------------------------------------------------------------------------- zahodit rozepsané
function zahodit() { 
# zrušit rozepsanou přihlášku
  dotaz("Mám smazat rozepsanou přihlášku bez uložení?","prazdna",'');
} // zahodit
// --------------------------------------------------------------------------------- prázdná stránka
function prazdna() { 
# jen template stránky
  global $DOM;
  initialize(0);
  $DOM->rozlouceni= 'show';
} // prazdna
// ---------------------------------------------------------------------------------- přidání dítěte
function nove_dite() { 
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
  read_elem($elem_ID,$val,$errs); // <== může volat DOM_zmena_spolu
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

function DOM_zmena_spolu($idc) { // ----------------------------------------------- změna volby spolu
# volá se z read_elem při změně položky spolu
  global $DOM, $vars;
  // ukaž resp. schovej zobrazení osobního pečovatele
  if (isset($vars->cleni[$idc]->o_pecoun)) {
    form_pecoun_show($idc); 
  }
  // zruš nevyplněného člena, který nepojede
  if (!get('o','spolu',$idc) && $idc<0 
      && get('o','jmeno',$idc)=='' && get('o','prijmeni',$idc)=='') {
    if (!get_pecoun($idc)) {
      unset($vars->cleni[$idc]);
      $clen_ID= "c_$idc"; 
      $DOM->$clen_ID= ['hide'];
    }
  }
} // změna volby spolu

// ================================================================================= prvky formuláře
function form_manzele() { // -------------------------------------------------------- zobrazení páru
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
        $upozorneni= $akce->p_upozorneni
          ? "<p class='souhlas'><input type='checkbox' id='{$id}_Xupozorneni' value=''  "
          . (get('o','Xupozorneni',$id) ? 'checked' : '')
          . " {$mis_upozorneni[$role]}><label for='{$id}_Xupozorneni' class='souhlas'>"
          . str_replace('@',$role=='b'?'a':'',$akce->upozorneni)
          . "</label></p>"
          : '';
        $clenove.= "<div id='$clen_ID' class='clen'>" 
          . ( $id>0
              ? elem_input('o',$id,['spolu']) . elem_text('o',$id,['<div>','jmeno',' ','prijmeni']) 
                . ($role=='b' ? elem_text('o',$id,[' roz. ','rodne']) : '')
                . elem_text('o',$id,[', ','narozeni', ', ','role','</div>'])
//                  . elem_text('o',$id,[' ... TEST: ','vzdelani','|','cirkev'])
              : elem_input('o',$id,['spolu']) . elem_input('o',$id,['jmeno','prijmeni'])
                . ($role=='b' ? elem_input('o',$id,['rodne']) : '')
                . elem_input('o',$id,[',','narozeni'])
                . elem_text('o',$id,['role']) . '<br>'
            )
          . ($akce->p_kontakt ? elem_input('o',$id,['email','telefon']) : '')
          . ($akce->p_obcanky ? elem_input('o',$id,['obcanka']) : '')
          . '<br>'
          . elem_input('o',$id,['zamest','vzdelani','zajmy','jazyk',
              'aktivita','cirkev','Xpovaha','Xmanzelstvi','Xocekavani','Xrozveden']) 
          . $upozorneni
          . "</div>";
      }
    }
  }
  $DOM->form_par= ['show',$clenove];
} // form - manželé

function form_deti($detail) { // ---------------------------------------------------- zobrazení dětí
  # detail=1 ... tlačítko [zobraz děti]
  # detail=2 ... děti a tlačítko [nové dítě] a tlačítko [zobraz pečouny]
  global $DOM, $vars;
  $part= '';
  if ($detail==1) {
    $part.= "<br><button onclick=\"php2('form_deti,=2');\" >
      <i class='fa fa-eye'></i> zobrazit naše děti</button>";
    $DOM->form_deti= ['show',$part];
  } // tlačítko
  else { // detail==2
    $part.= "<div id='deti' class='cleni'>";
    $deti= '';
    $deti_nove= '';
    foreach (array_keys($vars->cleni) as $id) {
      if (get_role($id)!='d') continue;
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
              <i class='fa fa-green fa-plus'></i> pečovatel pro toto dítě</button>
             <button id='b_{$id}_minus' $display_minus onclick=\"php2('form_pecoun_clear,=$id');\">
              <i class='fa fa-red fa-minus'></i> pečovatel pro toto dítě</button>";
      }
      // vlož dítě
      $clen_ID= "c_$id"; 
      if (get('o','umrti',$id)) {
        // zemřelé dítě
        $deti.= "<div id='$clen_ID' class='clen'>" 
//          . elem_input('o',$id,['spolu'])
          . elem_text('o',$id,['jmeno',' ','prijmeni',', *','narozeni',' &dagger;','umrti'])
          . "</div>";
      }
      elseif ($id>0) {
        // dítě
        $deti.= "<div id='$clen_ID' class='clen'>" 
          . $pecoun_button
          . elem_input('o',$id,['spolu'])
          . elem_text('o',$id,['jmeno',' ','prijmeni',', ','narozeni',', ', 'role'])
          . elem_input('o',$id,['note'])
          . $pecoun_form
          . "</div>";
      }
      else { // $id<0
//        if (get('o','prijmeni',$id) || get('o','jmeno',$id)) $jsou_deti++;
        $deti_nove.= "<div id='$clen_ID' class='clen'>" 
          . $pecoun_button
          . elem_input('o',$id,['spolu','jmeno','prijmeni','narozeni','note'])
          . $pecoun_form
          . "</div>";
      }
    }
    if ($deti) {
      $pozn= "<br>Pokud děti nemáte, nechte všechna pole prázdná a zrušte 'jede na akci'.";
      $part.= "<p><i>Naše děti (zapište prosím i ty, které necháváte doma). $pozn</i></p>";
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

function form_pecoun($id) { // ------------------------------------------ zobrazení osobního pečouna
# údaje pečouna $id osobně pečujícího o $id_dite
  $part= "<i>Osobní pečovatel pro toto dítě bude</i><br>";
  if ($id<0) {
    $part.= '' //elem_input('o',$id,['spolu']) 
        . elem_input('o',$id,['jmeno','prijmeni']) . elem_input('o',$id,['narozeni']) . '<br>'
        . elem_input('o',$id,['obcanka','telefon']);
  }
  else { // $id>0
    $part.= '' //elem_input('o',$id,['spolu'])
        . elem_text('o',$id,['<div>','jmeno',' ','prijmeni',', ','narozeni','</div>'])
        . elem_input('o',$id,['obcanka','telefon']);
  }
  return $part;
} // form osobní pečoun
function form_pecoun_show($id_dite,$form=null) { // -------------- ukáže tlačítka a form osobního pečouna
  global $DOM;
  $spolu= get('o','spolu',$id_dite);
  $button_plus= "b_{$id_dite}_plus";
  $button_minus= "b_{$id_dite}_minus";
  $fid= "f_$id_dite";
  if ($spolu) { // dítě jede
    $DOM->$button_plus= [get_pecoun($id_dite) ? 'hide' : 'show']; 
    $DOM->$button_minus= [get_pecoun($id_dite) ? 'show' : 'hide']; 
    $DOM->$fid= $form===null ? ['show'] : ['show',$form];
  }
  else { // dítě nejede  
    $DOM->$button_plus= ['hide'];
    $DOM->$button_minus= ['hide'];
    $DOM->$fid= $form===null ? ['hide'] : ['hide',$form];
  }
} // form a tlačítka pečouna
function form_pecoun_clear($id_dite) { // ------------------------------ odstranění osobního pečouna
# odstranění pečouna daného dítěte ve vars i v DOM
  global $DOM, $vars;
  $id_pecoun= get_pecoun($id_dite);
  set('o','o_pecoun','',$id_dite);
  unset($vars->cleni[$id_pecoun]);
  $name= "f_$id_dite"; $DOM->$name= ['empty'];
  $name= "b_{$id_dite}_minus"; $DOM->$name= ['hide'];
  $name= "b_{$id_dite}_plus"; $DOM->$name= ['show'];
} // odstranění osobního pečouna

function form() { trace();
  global $vars, $akce;
  $msg= '';
  $mis_souhlas= '';
  $red_x= 'fa fa-times fa-red';
  $clenove= '';
  // -------------------------------------------- úprava rodinné adresy
  $rod_adresa= '';
  if ($vars->form->rodina) {
    $rod_adresa= "<p>Zapište, nebo zkontrolujte a případně upravte vaši rodinnou adresu a další údaje:</p>";
    $idr= key($vars->rodina);
    if ($idr<0) { // požadujeme název rodiny
      $rod_adresa.= elem_input('r',$idr,['nazev']).'<br>';
    }
    $rod_adresa.= elem_input('r',$idr,['ulice','psc','obec','spz','datsvatba','<br>','r_ms']);
  }
  // -------------------------------------------- poznánky k pobytu
  $pobyt= '';
  if ($vars->form->pozn) {
    $pobyt= elem_input('p',0,['pracovni']);
  }
  // specifika pro VPS na MS
  $je_dotaz_vps= false;
  if ($akce->p_pro_LK || $akce->p_obnova) {
    $umi= get('r','r_umi');
    $umi_vps= in_array(1,explode(',',$umi));
    if ($umi_vps 
        && ($akce->p_obnova && byli_na_aktualnim_LK(key($vars->rodina)) || $akce->p_pro_LK)) {
      $pobyt.= elem_input('p',0,['Xvps']);
      $je_dotaz_vps= true;
    }
  }
  if (!$je_dotaz_vps) {
    unset($vars->pobyt->Xvps);
  }
  // -------------------------------------------- souhlas
  $souhlas= '';
  if ($vars->form->souhlas) {
      $souhlas= "<p class='souhlas'><input type='checkbox' id='chk_souhlas' value=''  "
      . ($vars->chk_souhlas ? 'checked' : '')
      . " $mis_souhlas><label for='chk_souhlas' class='souhlas'>$akce->form_souhlas</label>"
      . "</p>";
  }
//  // -------------------------------------------- zapnout kontrolu dat <=== je automatická
//  $kontrola= '';
//  if ($akce->p_kontrola) {
//    $kontrola= "<button id='kontrola' onclick='php(\"*\");'><i class='fa fa-question'></i> 
//      zkontrolovat před odesláním (nutné, lze opakovat)</button>";
//    $enable_send= $vars->kontrola ? '' : 'disabled title="před odesláním je nutné zkontrolovat korektnost vyplněných údajů tlačítkem vlevo" ';
//    $kontrola_txt= 'Zkontrolujte a případně upravte zobrazené údaje.';
//  }
//  else {
//    $enable_send= '';
//    $kontrola_txt= '';
//  }
  // -------------------------------------------- redakce formuláře
//  $enable_green= $vars->kontrola ? 'fa-green' : '';
//  $odeslat= $vars->form->oprava??0 ? "uložit opravu" : "odeslat přihlášku";
//  $exit= $vars->form->exit 
//      ? "<button name='cmd_exit'><i class='$red_x'></i> smazat rozepsanou přihlášku bez uložení</button>
//         <button name='cmd_exit_no'> ... pokračovat v úpravách</button>"
//      : "$kontrola
//        <button id='prihlasit' onclick='php();' $enable_send><i class='fa $enable_green fa-send-o'></i>
//           $odeslat</button>
//         <button id='zahodit' onclick='php();'><i class='$red_x'></i> neposílat</button>";
  $exit= "<button onclick=\"clear_css('chng');php2('kontrolovat');\"><i class='fa fa-green fa-send-o'></i>
           odeslat přihlášku</button>
         <button id='zahodit' onclick='php();'><i class='$red_x'></i> neposílat</button>";
  $kontrola_txt= '';
//  $exit= '';
  $form= <<<__EOF
    <p>Poznačte, koho na akci přihlašujete. $kontrola_txt</p>
    <div id='form_par'></div>
    <div id='form_deti'></div>
    $clenove
    <div class='rodina'>
      $rod_adresa
      $pobyt
    </div>
    $souhlas
    $exit
    <p id="vyplneni_msg">$msg</p>
__EOF;
  return $form;
} // form - základní skeleton

function hlaska($text,$continue='') { // --------------------------------- hláška
# zobrazí hlášku s Ok pro ukončení případně na přechod na $continue
  global $DOM;
  $DOM->alertbox= 'show'; $DOM->popup_mask= 'show';
  $DOM->alertbox_text= $text;
  $off= "jQuery('#alertbox').hide();jQuery('#popup_mask').hide();";
  $cmd= $continue ? "php2('$continue')" : "";
  $DOM->alertbox_butts= "
    <button onclick=\"$cmd;$off\">OK</button>";
} // popup s OK
function dotaz($dotaz,$ano,$ne) { // -------------- dotaz s funkcemi pro ano a ne
  global $DOM;
  $DOM->alertbox= 'show'; $DOM->popup_mask= 'show';
  $DOM->alertbox_text= $dotaz;
  $off= "jQuery('#alertbox').hide();jQuery('#popup_mask').hide();";
  $cmd_ano= $ano ? "php2('$ano')" : "";
  $cmd_ne= $ne ? "php2('$ne')" : "";
  $DOM->alertbox_butts= "
    <button onclick=\"$cmd_ano;$off\">ANO</button> &nbsp;
    <button onclick=\"$cmd_ne;$off\">NE</button>
    ";
} // popup s ANO / NE
function vyber($dotaz,$odpovedi) { // -------------- výběr z více možností
# $odpovedi= [ text:funkce:parametr:podtext
  global $DOM;
  $DOM->alertbox= 'show'; $DOM->popup_mask= 'show';
  $DOM->alertbox_text= $dotaz;
  $DOM->alertbox_butts= '';
  $off= "jQuery('#alertbox').hide();jQuery('#popup_mask').hide();";
  foreach ($odpovedi as $odpoved) {
    list($text,$fce,$par,$subtext)= explode(':',$odpoved.':::');
    if ($subtext??0) $subtext= "<br><small>$subtext</small>";
    $DOM->alertbox_butts.= "
      <button onclick=\"php2('$fce,$par');$off\">"
        . "$text$subtext</button> &nbsp;
    ";
  }
} // popup s výběrem z více možností

function hledej($faze,$id_dite,$ido=0,$jmeno='',$prijmeni='') { // -------------- hledání osoby
# $fáze=1 ... vyplnění jména a příjmení --> (3,5)
#       2 ... čekání na úplné vyplnění --> (3)
#       3 ... test vyplnění --> (2), nalezení stejnojmenných a zobrazení jako radiobuttons --> (4,5)
#       4 ... pokud bylo vráceno ID tak vložení --> (5)
#       5 ... pokud bylo zvoleno vložení nového --> (5)
#       6 ... uvolnění dialogu, exit
  global $DOM, $vars;
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
        <button onclick=\"php2('hledej,=6,=$id_dite');\">Zpět</button>
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
        HAVING _vek>15
        ORDER BY _vek
      ");
      while ($ro && (list($id,$vek,$obec)= pdo_fetch_array($ro))) {
        if (je_na_teto_akci($id)) { 
          $dotazy[]= "$jmeno $prijmeni:hledej:=4,=$id_dite,=$id,=$jmeno,=$prijmeni:"
              . "<b class='fa-green'>$vek let, na akci s rodiči</b>";
        }
        else {
          // zjistíme, zda nejde o člena přihlašované rodiny
          $idr= $vars->idr;
          $je_z_rodiny= $idr>0 
              ? select1_2("SELECT COUNT(*) FROM tvori WHERE id_rodina=$idr AND id_osoba=$id AND role='d'")
              : 0;
          $dotazy[]= "$jmeno $prijmeni:hledej:=5,=$id_dite,=$id,=$jmeno,=$prijmeni:"
              . ( $je_z_rodiny 
                ? "<b class='fa-green'>$vek let, sourozenec</b>" 
                : "$vek let, $obec");
        }
      }      
      $dotazy[]= "$jmeno $prijmeni:hledej:=6,=$id_dite,=0,=$jmeno,=$prijmeni:"
          . "<b class='fa-red'>chci přidat do evidence</b>";
      vyber("Vyberte pečovatele nebo vyplňte údaje nového",$dotazy);
      break; // procházení jmenovců
    case 4: // ------------------------ vložení zvolené osoby, která je na kurzu
      nacti_clena($ido,'p',0);
      $vars->cleni[$id_dite]->o_pecoun= [0,$ido];
      $vars->cleni[$ido]->o_dite= [0,$id_dite];
      $vars->cleni[$ido]->role= [0,'p'];
      log_write_changes(); 
      $div= "f_$id_dite";
      form_pecoun_show($id_dite,form_pecoun($ido));
      $DOM->modalbox= 'hide'; $DOM->popup_mask= 'hide';
      break; // vložení zvolené osoby, která je na kurzu
    case 5: // ------------------------ vložení zvolené osoby
      nacti_clena($ido,'p',1);
      $vars->cleni[$id_dite]->o_pecoun= [0,$ido];
      $vars->cleni[$ido]->o_dite= [0,$id_dite];
      $vars->cleni[$ido]->role= [0,'p'];
      log_write_changes(); 
      $div= "f_$id_dite";
      form_pecoun_show($id_dite,form_pecoun($ido));
      $DOM->modalbox= 'hide'; $DOM->popup_mask= 'hide';
      break; // vložení zvolené osoby
    case 6: // ------------------------ vytvoření zvolené osoby
      $ido= vytvor_noveho_clena('p',1);
      $vars->cleni[$id_dite]->o_pecoun= [0,$ido];
      $vars->cleni[$ido]->o_dite= [0,$id_dite];
      $vars->cleni[$ido]->role= [0,'p'];
      $vars->cleni[$ido]->jmeno= ['',$jmeno];
      $vars->cleni[$ido]->prijmeni= ['',$prijmeni];
      log_write_changes(); 
      $div= "f_$id_dite";
      form_pecoun_show($id_dite,form_pecoun($ido));
      $DOM->modalbox= 'hide'; $DOM->popup_mask= 'hide';
      break; // vytvoření zvolené osoby
    case 7: // ------------------------ konec
      $DOM->modalbox= 'hide'; $DOM->popup_mask= 'hide';
      break;
  }
} // popup pro nalezení nebo vložení osoby

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
  }
} // převod do $vars
function read_elems($elems,&$errs) { // ------------------------------------------------- read elems
# načte elementy změněné uživatelem a poslané z JS
  foreach ($elems as $elem_ID=>$val) {
    read_elem($elem_ID,$val,$errs);
  }
} // převod do $vars

// =============================================================================== zobrazení stránky
function page() {
  global $MYSELF, $_TEST, $TEST, $TEST_mail, $TEXT, $akce, $rel_root;
  $if_trace= $TEST ? "style='overflow:auto'" : '';
  $TEST_mail= $TEST_mail??'';
  $icon= "akce$_TEST.png";
  $hide= "style='display:none'";
  $hide_2002= "style='display:none;z-index:2002'";
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
    <link rel="stylesheet" href="/less/akce$_TEST.css?verze=3" type="text/css" media="screen" charset='utf-8'>
    <script src="/ezer3.2/client/licensed/jquery-3.3.1.min.js" type="text/javascript" charset="utf-8"></script>
    <script src="$MYSELF.js" type="text/javascript" charset="utf-8"></script>
    <link rel="stylesheet" id="customify-google-font-css" href="//fonts.googleapis.com/css?family=Open+Sans%3A300%2C300i%2C400%2C400i%2C600%2C600i%2C700%2C700i%2C800%2C800i&amp;ver=0.3.5" type="text/css" media="all">
    <link rel="stylesheet" href="/ezer3.2/client/licensed/font-awesome/css/font-awesome.min.css?" type="text/css" media="screen" charset="utf-8">
    <script>
      var myself_url= "$rel_root/$MYSELF.php";
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
          <div id='info' class='info'></div>
          <a class="logo" href="https://www.setkani.org" target="web" title="" >
            <img src="/img/husy_ymca.png" alt=""></a>
          <div id='user' class="user"></div>
        </div>
        <div class="intro">Přihláška na akci <b>$akce->nazev ($akce->oddo)</b></div>
      </header>
      <main>
        <!-- ladění ----------------------------------------------------------------------------- -->
        <div $hide id='errorbox' title='errorbox' class='box' style='border-left: 20px solid red'></div>
        <div $hide id='mailbox' title='mailbox' class='box' style='border-left: 20px solid grey'></div>
        <!-- identifikace osoby mailem a pinem ------------------------------------------------- -->
        <div id='usermail' title='usermail' class='box'>
          <p id='usermail_nad'>$TEXT->usermail_nad1</p>
          <input id='email' title='váš email' type="text" size="24" value='$TEST_mail' placeholder='@'>
          <input $hide id='pin' title='doručený PIN' type='text' size="4" >
          <button id='zadost_o_pin' onclick="php('email');">Požádat o PIN</button>
          <button $hide id='kontrola_pinu' onclick="php('email,pin');">ověřit PIN</button>
          <p id='usermail_pod'>$TEXT->usermail_pod1</p>
        </div>
        <!-- formulář -------------------------------------------------------------------------- -->
        <div $hide id='form' title='form' class='box'></div>
        <div class='prosba'>$akce->ohlasit_chybu</div>
        <!-- rozloučení ------------------------------------------------------------------------ -->
        <div $hide id='rozlouceni' title='form' class='box'>
          <p id='rozlouceni_text'>$TEXT->rozlouceni1</p>
        </div>
        <!-- popup ----------------------------------------------------------------------------- -->
        <div id='popup_mask'></div>
        <div $hide_2002 id='alertbox' class='popup' title='Upozornění'>
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
        <div class="footer">
          © YMCA Setkání
        </div>
      </footer>
    </div>
    <section id='trace'class='trace'></section>
  </body>
  </html>
__EOD;
}

/* 
 * 
 * 
 *                          funkce z prihlaska.php
 * 
 * 
 */

function connect_db() { // -------------------------------------------------------------- connect db
 global $_TEST, $ezer_server, $dbs, $db, $ezer_db, $USER, $kernel, $ezer_path_serv, $mysql_db_track, 
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
  // testovací databáze a cesty např. $path_files_h
  require_once($_TEST ? "$deep_root/dbt.dbs.php" : "$deep_root/db2.dbs.php"); // testovací nebo ostrá
  $ezer_path_root= $abs_root;
  require_once("$kernel/server/ae_slib.php");
  require_once("$kernel/pdo.inc.php");
  require_once("$kernel/server/ezer_pdo.php");
  require_once("db2/db2_fce3.php");
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
  if (isset($dbs[$ezer_server])) $dbs= $dbs[$ezer_server];
  if (isset($db[$ezer_server])) $db= $db[$ezer_server];
  $ezer_db= $dbs;
  ezer_connect($answer_db);
} // napojení na Ezer a log
function read_akce() { // ---------------------------------------------------------------- read akce
  global $akce, $akce_default, $vars;
  $msg= '';
  $id_akce= $vars->id_akce; //$_GET['akce'];
  // parametry přihlášky a ověření možnosti přihlášení
  list($ok,$web_online)= select_2("COUNT(*),web_online",'akce',"id_duakce=$id_akce");
  if (!$ok || !$web_online) { 
    $msg= "Na tuto akci se nelze přihlásit online"; goto end; }
  // dekódování web_online
  $akce= json_decode($web_online,false); // JSON objects will be returned as objects
  $akce= (object)array_merge($akce_default,(array)$akce);
//  foreach (['p_rod_adresa','p_souhlas','p_pro_LK','p_obcanky','p_upozorneni'] as $_par) {
//    if (!isset($akce->$_par)) $akce->$_par= 0;
//  }
//  debug($akce,"web_online");
  if (!$akce || !$akce->p_enable) { 
    $msg= "Na tuto akci se bohužel nelze přihlásit online"; goto end; }
  // doplnění dalších údajů o akci
  list($akce->org,$akce->nazev,$akce->misto,$garant,$od,$do,$rok)= // doplnění garanta
      select_2("access,nazev,misto,poradatel,datum_od,datum_do,YEAR(datum_od)",'akce',"id_duakce=$id_akce");
  if ($od<=date('Y-m-d')) { 
    $msg= "Akce '$akce->nazev' již proběhla, nelze se na ni přihlásit"; goto end; }
  $akce->oddo= sql2oddo($od,$do,1);
  $akce->rok= $rok;
  $MarketaZelinkova= 6849;
  list($ok,$akce->garant_jmeno,$akce->garant_telefon,$akce->garant_mail)= // doplnění garanta
      select_2("COUNT(*),CONCAT(jmeno,' ',prijmeni),telefon,email",
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
  $akce->ohlasit_chybu= "<p>Pokud se Vám během vyplňování přihlášky objeví nějaká chyba, přijměte prosím naši omluvu."
      . " Abychom jí mohli opravit, napište prosím "
      . "<a target='mail' href='mailto:martin@smidek.eu?subject=Přihláška 2025'>autorovi</a> "
      . " a popište problém. Můžete mu také ještě od počítače zavolat na 603 150 565 (za denního světla, prosím). "
      . "<br>Pomůžete tím těm, kteří se budou přihlašovat po Vás. Děkujeme. </p>";
  $akce->preambule= "Tyto údaje slouží pouze pro vnitřní potřebu organizátorů kurzu MS, 
      nejsou poskytovány cizím osobám ani institucím.<br /> <b>Pro vaši spokojenost během kurzu je 
      nezbytné, abyste dotazník pečlivě a pravdivě vyplnili.</b>";
  $akce->oba= "<p><i>Přihláška obsahuje otázky určené oběma manželům 
      - je potřeba, abyste ji vyplňovali společně.</i></p>";
  $akce->form_souhlas= " Vyplněním této přihlášky dáváme výslovný souhlas s použitím uvedených 
      osobních údajů pro potřeby organizace akcí YMCA Setkání v souladu s Nařízením 
      Evropského parlamentu a Rady (EU) 2016/679 ze dne 27. dubna 2016 o ochraně 
      fyzických osob (GDPR) a zákonem č. 110/2019 Sb. ČR. Na našem webu naleznete 
      <a href='https://www.setkani.org/ymca-setkani/5860#anchor5860' target='show'>
      podrobnou informací o zpracování osobních údajů v YMCA Setkání</a>.";
  $akce->upozorneni= "Potvrzuji, že jsem byl@ upozorněn@, že není možné se účastnit pouze části kurzu, 
      že kurz není určen osobám závislým na alkoholu, drogách nebo jiných omamných látkách, ani
      osobám zatíženým neukončenou nevěrou, těžkou duševní nemocí či jiným omezením, která neumožňují 
      zapojit se plně do programu. V případě, že jsem v odborné péči psychologa nebo psychiatra, 
      prohlašuji, že se akce účastním s jeho souhlasem a konzultoval jsem náročnost akce s organizátory. 
      Zatržením prohlašuji, že jsem si plně vědom@, že pořadatel neodpovídá za škody a újmy, které by 
      mně/nám mohly vzniknout v souvislosti s nedodržením těchto zásad účasti na kurzu, a veškerá rizika
      v takovém případě přebíráme na sebe.";
  // -------------------------------------------- počáteční nastavení formuláře
//  if (!($vars->form??null)) {
//    $vars->form= (object)[
//        'pass'=>0, // inicializovat pozici pro 0
//        'par'=>1,'deti'=>2,'pecouni'=>1, // 1=tlačítko, 2=seznam
//        'rodina'=>$akce->p_rod_adresa,'pozn'=>1,'souhlas'=>$akce->p_souhlas,
////        'oprava'=>0,    // 1 => byla načtena již uložená přihláška a je možné ji opravit
////        'todo'=>0,      // označit červeně chybějící povinné údaje po kontrole formuláře
////        'exit'=>0,      // 1 => první stisk 
//    ];
//  }
end:    
//  global $trace;
//  $trace.= debugx($akce,'hodnoty web_online');
  if ($msg) {
    die($msg);
  }
} // doplnění infromací o akci
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
function inits($table) { // --     ---------------------------------------------------------- init s
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
  global $p_fld, $r_fld, $o_fld, $vars;
  $missed= [];
  $prfx= "{$table}_{$id}_";
  if ($table=='p') {
    foreach ($p_fld as $f=>list(,$title,$typ)) {
      $v= $vars->pobyt->$f ?? '';
      if (is_array($v) && substr($title,0,1)=='*') {
        $v= $v[1] ?? $v[0];
        if ($v=='' || in_array($typ,['select','sub_select']) && $v==0) {
          if (!in_array($id,$but)) {
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
      if (substr($title,0,1)=='*') {
        if (is_array($rodina->$f)) {
          $v= $rodina->$f[1] ?? $rodina->$f[0];
          if ($v=='' || in_array($typ,['select','sub_select']) && $v==0) {
            if (!in_array($id,$but)) {
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
    foreach ($o_fld as $f=>list(,$title,$typ,$omez)) {
      if (substr($title,0,1)=='*' && strpos($omez,get_role($id))!==false) {
        if (is_array($clen->$f)) {
          $v= $clen->$f[1] ?? $clen->$f[0];
          if ($v=='' || (in_array($typ,['select','sub_select']) && $v==0)) {
            if (!in_array($id,$but)) {
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
function elem_input($table,$id,$flds) { // ---------------------------------------------- elem input
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
    list($len,$title,$typ)= $desc[$fld];
    if ($typ=='x') continue;
    // rozpoznání hodnoty příp. změny položky
    $v_chng= false;
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
    $todo= '';
    // rozpoznání povinnosti položky
    if (substr($title,0,1)=='*') { //  && ($table!='o' || $pair->spolu)) {
      $title=  "<b style='color:red'>*</b>".substr($title,1);
//      if ($vars->form->todo 
//        && ($v=='' || in_array($typ,['select','sub_select']) && $v==0 || isset($pair->_corr_->$fld))) {
//        $todo= " class='missing'";
//      }
    }
    $oninput= "onchange=\"elem_changed(this);\"";
    switch ($typ) {
    case 'check_spolu':
    case 'check':
      $x=  $v ? 'checked' : '';
      $html.= "<label class='$typ'>$title"
          . "<input type='checkbox' id='$name' value='x' $x$todo $oninput></label>";
      break;
    case 'select':
    case 'sub_select':
      $html.= "<label class='upper'>$title<select$todo id='$name' $oninput>";
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
      $html.= "<label class='upper-area'>$title"
          . "<textarea rows='$rows' cols='$cols' id='$name'$todo $oninput>"
//          . "oninput=\"this.classList.add('chng');\">"
          . "$v</textarea></label>";
      break;
    case 'number':
      $v= $v?: 0;
    default:
      $x= $v ? "value='$v'" : ''; // "placeholder='$holder'";
      $c= $v_chng ? " class='$chng_css' " : '';
      $html.= "<label class='upper'>$title<input type='text' id='$name' size='$len' $x$c$todo $oninput></label>";
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
  $idr= -1;
  $vars->rodina= [$idr=>(object)[]];
  $rodina= $vars->rodina[$idr];
  foreach ($r_fld as $f=>list(,$title,$typ)) {
    $rodina->$f= substr($title,0,1)=='*' ? [init_value($typ)] : init_value($typ);
  }
}
function vytvor_clena($ido,$role,$spolu) { // ------------------------------ vytvor clena s daným ID
  // inicializace dat pro dospělou osobu, přidáme roli a že je na akci
  global $vars,$o_fld;
  $vars->cleni[$ido]= (object)[];
  foreach ($o_fld as $f=>list(,$title,$typ,$omez)) {
    if ($typ=='x') 
      continue;
    elseif (strpos($omez,$role)!==false)
      $vars->cleni[$ido]->$f= substr($title,0,1)=='*' ? [init_value($typ)] : init_value($typ);
  }
  $vars->cleni[$ido]->role= ['',$role];
  $vars->cleni[$ido]->spolu= [0,$spolu];
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
# ... 0 nebyla vůbec | 1 jako účastníci | 2 jako sloužící VPS
  global $akce;
  $obnova_mesic= select_2('MONTH(datum_od)','akce',"id_duakce=$akce->id_akce");
  $rok_LK= $obnova_mesic>7 ? date('Y') : date('Y')-1;
  $byli= select1_2('IFNULL(IF(funkce=1,2,1),0)','pobyt JOIN akce ON id_akce=id_duakce',
      "akce.druh=1 AND YEAR(akce.datum_od)=$rok_LK AND pobyt.i0_rodina='$rodina'");
  return $byli;
}
function je_na_teto_akci($ido) { // ------------------------------------------------ je na této akci
# zjistí, jestli daná osoba už je an této akci přihlášena
  global $akce;
  $je= select1_2("SELECT COUNT(*) FROM spolu JOIN pobyt USING (id_pobyt)
      WHERE id_osoba=$ido AND id_akce=$akce->id_akce");
  return $je;
}
function nacti_pobyt($idp) { trace();// -------------------------------------------------------- nacti pobyt
  global $vars, $p_fld;
  $vars->pobyt= (object)['id_pobyt'=>$idp];
  $p= select_object_2('*','pobyt',"id_pobyt=$idp");
  foreach ($p_fld as $f=>list(,$title,$typ)) {
    // nedatabázové položky inicializuj
    if (substr($f,0,1)=='X') 
      $vars->pobyt->$f= substr($title,0,1)=='*' ? [init_value($typ)] : init_value($typ);
    // resp. ignoruj
    elseif (!isset($p->$f)) 
      continue;
    // databázové načti s případnou konverzí
    else {
      $v= $p->$f;
      if ($typ=='date') 
        $v= sql2date($v);
      $vars->pobyt->$f= substr($title,0,1)=='*' ? [$v] : $v;
    }
  }
}
function nacti_rodinu($idr) { // ------------------------------------------------------ nacti rodinu
  global $akce, $vars, $r_fld;
  $vars->rodina= [$idr=>(object)[]];
  $rodina= $vars->rodina[$idr];
  if ($akce->p_rod_adresa) {
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
}
function nacti_clena($ido,$role,$spolu) { // ------------------------------------------- nacti clena
  // přečteme položky dané osoby, přidáme roli a že je na akci
  global $vars, $o_fld, $sub_options;
  $clen= $vars->cleni[$ido]= (object)[];
  $o= select_object_2('*','osoba',"id_osoba=$ido");
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
        $clen->$f= substr($title,0,1)=='*' ? [-1=>$v,0=>$sub_v] : [-1=>$v,0=>$sub_v];
      }
      else {
        $clen->$f= substr($title,0,1)=='*' ? [$v] : $v;
      }
    }
  }
  $vars->cleni[$ido]->role= $role;
  $vars->cleni[$ido]->spolu= $o->umrti ? 0 : ($spolu ? [0,1] : 0);
}
function db_nacti_cleny_rodiny($idr,$prvni_ido) { // ------------------------- db nacti_cleny_rodiny
  global $vars;
  $roles= []; // role členů rodiny
  $ro= pdo_query_2(
    "SELECT id_osoba,role
    FROM osoba AS o JOIN tvori USING (id_osoba)
    WHERE id_rodina=$idr AND o.deleted='' AND role IN ('a','b','d','p') 
    ORDER BY IF(id_osoba=$prvni_ido,'0',narozeni)  ",1);
  while ($ro && (list($ido,$role)= pdo_fetch_array($ro))) {
    $roles[]= $role;
    nacti_clena($ido,$role,in_array($role,['a','b'])?1:0);
  }
  // pokud ten $prvni_ido nemá email je mu vnucen ten přihlašovací
  if (isset($vars->cleni[$prvni_ido])) {
    if (!get('o','email',$prvni_ido)) {
      set('o','email',$vars->email,$prvni_ido);
    }
  }
  return $roles;
}
function kompletuj_pobyt($idr,$ido,$idp=0) { // ------------------------------------ kompletuj pobyt
# zajisti aby ve vars->rodina a cleni byla úplná rodina (byť s prázdnými položkami)
  // a byl iniciován resp. načten pobyt 
  if ($idr) { // rodina existuje
    nacti_rodinu($idr);        
    $roles= db_nacti_cleny_rodiny($idr,$ido);
    // případně do rodiny doplníme druhého z manželů
    if (!in_array('a',$roles)) 
      vytvor_clena(-1,'a',1);
    if (!in_array('b',$roles)) 
      vytvor_clena(-2,'b',1);
    if (!in_array('d',$roles)) 
      vytvor_clena(-3,'d',0);
  }
  elseif ($ido) { // vytvoříme rodinu a načteme klienta a doplníme druhého z manželů a dítě
    $role= select_2('sex','osoba',"id_osoba=$ido");
    vytvor_rodinu();        
    nacti_clena($ido,$role,1);
    vytvor_clena(-1,$role=='b' ? 'a' : 'b',1);
    vytvor_clena(-2,'d',0);
  }
  else { // vytvoříme rodinu včetně dítěte
    vytvor_rodinu();        
    vytvor_clena(-1,'a',1);
    vytvor_clena(-2,'b',1);
    vytvor_clena(-3,'d',0);
  }
  // vytvoř nebo načti pobyt
  if ($idp) {
    nacti_pobyt($idp);
  } // načti pobyt - pokud je povolena oprava uloženého
  else {
    vytvor_pobyt();
  } // vytvoř pobyt
end:
}
# ================================================================================ zápis do databáze
function db_open_pobyt() { // -------------------------------------------------------- db open_pobyt
# vytvoř pobyt - potřebujeme dále jeho ID 
  global $errors, $akce, $vars; 
  $ida= $akce->id_akce;
  $chng= array(
    (object)['fld'=>'id_akce',     'op'=>'i','val'=>$ida],
    (object)['fld'=>'web_zmena',   'op'=>'i','val'=>date('Y-m-d')]
  );
  $idp= _ezer_qry("INSERT",'pobyt',0,$chng);
  if (!$idp) $errors[]= "Nastala chyba při zápisu do databáze (p)"; 
  $vars->pobyt->id_pobyt= $idp;
  log_write('id_pobyt',$idp);
  return $idp;
}
function db_vytvor_nebo_oprav_clena($id) { // --------------------------- db vytvor_nebo_oprav_clena
# pokud mají roli=p a jsou noví přidáme je do rodiny, pokud nejsou noví do rodiny se nepřidají
  global $errors, $o_fld, $akce, $vars; 
  // pobyt a rodina už musí být zapsané
  $idp= $vars->pobyt->id_pobyt;
  $idr= key($vars->rodina);
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
    if ($pocet==1) {
      // asi známe - zabráníme opravě jména a narození
      $ido= $idx;
      $vars->cleni[$ido]= $vars->cleni[$id];
      $vars->cleni[$ido]->jmeno= $jmeno;
      $vars->cleni[$ido]->prijmeni= $prijmeni;
      $vars->cleni[$ido]->narozeni= $narozeni;
      unset($vars->cleni[$id]);
      // pokud je to potřeba, rozšíříme povolení
      if (!((0+$access) & (0+$akce->org))) {
        $chng[]= (object)array('fld'=>'access', 'op'=>'u','old'=>$access,'val'=>$access|$akce->org);
      }
    }
  } // asi nový člen ale zkusíme ho najít v databázi 
  else { // nenašli
    $ido= $id;
  } // nenašli
  if ($ido==0) { // nenašli => zapíšeme novou osobu a připojíme ji do rodiny
    $jmeno_= preg_split("/[ \-]/",$jmeno);
    $sex= select_2('sex','_jmena',"jmeno='$jmeno_[0]' LIMIT 1");
    $sex= $sex==1 || $sex==2 ? $sex : 0;
    $kontakt= 0;
    $chng= array(
      (object)['fld'=>'sex',      'op'=>'i','val'=>$sex],
      (object)['fld'=>'access',   'op'=>'i','val'=>$akce->org],
      (object)['fld'=>'web_zmena','op'=>'i','val'=>date('Y-m-d')]
    );
    foreach ((array)$clen as $f=>$vals) {
      if (!isset($o_fld[$f]) || substr($f,0,1)=='X') continue; // položka začínající X nepatří do tabulky
      if (is_array($vals) && (!isset($vals[1]) || (isset($vals[1]) && $vals[1]!=$vals[0]))) {
        $v= $vals[1]??$vals[0];
        if (in_array($f,['telefon','email','nomail'])) {
          $kontakt= 1;
        }
        if ($o_fld[$f][2]=='date') 
          $v= date2sql($v);
        $chng[]= (object)['fld'=>$f, 'op'=>'i','val'=>$v];
      }
    }
    if ($kontakt) $chng[]= (object)['fld'=>'kontakt', 'op'=>'i','val'=>1];
    $ido= _ezer_qry("INSERT",'osoba',0,$chng);
    if (!$ido) $errors[]= "Nastala chyba při zápisu do databáze (o)"; 
    $vars->cleni[$ido]= $vars->cleni[$id];
    unset($vars->cleni[$id]);
    log_write('id_osoba',$ido);
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
  } // nenašli => zapíšeme novou osobu a připojíme ji do rodiny
  else { // našli => opravíme změněné hodnoty položek existující osoby
    $chng= [];
    $kontakt= 0;
    foreach ((array)$clen as $f=>$vals) {
      if (!isset($o_fld[$f]) || substr($f,0,1)=='X') continue; // položka začínající X nepatří do tabulky
      if (in_array($f,['spolu','role','vztah','o_dite','o_pecoun'])) continue; // nepatří do tabulky
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
        elseif ($o_fld[$f][2]=='sub_select') {
          $v0= $vals[-1];
        }
        $chng[]= (object)['fld'=>$f, 'op'=>'u','old'=>$v0,'val'=>$v];
      }
    }
    if ($kontakt) $chng[]= (object)['fld'=>'kontakt', 'op'=>'i','val'=>1];
    if (count($chng)) {
      if (!_ezer_qry("UPDATE",'osoba',$ido,$chng)) 
        $errors[]= "Nastala chyba při zápisu do databáze (o)"; 
    }
  } // našli => opravíme změněné hodnoty položek existující osoby
  if ($spolu) { // zapojíme do pobytu
    $chng= array(
      (object)['fld'=>'id_pobyt',  'op'=>'i','val'=>$idp],
      (object)['fld'=>'id_osoba',  'op'=>'i','val'=>$ido],
      (object)['fld'=>'s_role',    'op'=>'i','val'=>$role=='d'?2:1]
    );
    $ids= _ezer_qry("INSERT",'spolu',0,$chng);
    if (!$ids) $errors[]= "Nastala chyba při zápisu do databáze (cs)"; 
  } // zapojíme do pobytu
end:
  // konec
}
function db_vytvor_nebo_oprav_rodinu() { // ---------------------------- do vytvor_nebo_oprav_rodinu
# oprav rodinné údaje resp. vytvoř novou rodinu
  global $akce, $r_fld, $vars, $errors;
  $id= key($vars->rodina);
  $rodina= $vars->rodina[$id];
  if ($id<0) {
    // musíme vytvořit rodinu 
    $chng= array(
//      (object)['fld'=>'nazev',    'op'=>'i','val'=>$nazev],
      (object)['fld'=>'access',   'op'=>'i','val'=>$akce->org],
      (object)['fld'=>'web_zmena',  'op'=>'i','val'=>date('Y-m-d')]
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
    $idr= _ezer_qry("INSERT",'rodina',0,$chng);
    if (!$idr) $errors[]= "Nastala chyba při zápisu do databáze (o)"; 
    $vars->rodina[$idr]= $rodina;
    unset($vars->rodina[$id]);
    log_write('id_rodina',$idr);
  }
  else {
    $chng= [];
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
      if (!_ezer_qry("UPDATE",'rodina',$id,$chng)) 
        $errors[]= "Nastala chyba při zápisu do databáze (r)"; 
    }
  }
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
    query_track_2("UPDATE spolu SET s_role=2 WHERE id_spolu=$s_dite");
    query_track_2("UPDATE spolu SET s_role=5,pecovane=$id_dite WHERE id_spolu=$s_pecoun");
  }
}
function db_close_pobyt() { // ------------------------------------------------------ db close_pobyt
  global $errors, $p_fld, $vars;
  // úschova pobyt
  $idr= key($vars->rodina);
  $web_json= vytvor_web_json();
  $chng= array(
    (object)['fld'=>'i0_rodina',   'op'=>'i','val'=>$idr],
    (object)['fld'=>'funkce',      'op'=>'i','val'=>get('p','funkce')],
    (object)['fld'=>'web_json',    'op'=>'i','val'=>$web_json],
  );
  foreach ($vars->pobyt as $f=>$vals) {
    if (!isset($p_fld[$f]) || substr($f,0,1)=='X') continue; // položka začínající X nepatří do tabulky
    if (in_array($f,['funkce'])) continue; // dávají se vždy
    if (is_array($vals) && isset($vals[1]) && $vals[1]!=$vals[0]) {
      $chng[]= (object)['fld'=>$f, 'op'=>'i','val'=>$vals[1]];
    }
  } 
  if (!_ezer_qry("UPDATE",'pobyt',$vars->pobyt->id_pobyt,$chng))  
    $errors[]= "Nastala chyba při zápisu do databáze (p)"; 
  // poznamenání souhlasu se zpracováním osobních údajů
  if ($vars->chk_souhlas??0) {
    $ted= date("Y-m-d H:i:s");
    foreach ($vars->cleni as $id=>$clen) {
      if ($clen->spolu && in_array(get_role($id),['a','b'])) {
        if (!_ezer_qry("UPDATE",'osoba',$id,[(object)['fld'=>'web_souhlas','op'=>'i','val'=>$ted]])) 
          $errors[]= "Nastala chyba při zápisu do databáze (o)"; 
      }
    }
  }
}
# ------------------------------------------------------------------------------------ log prihlaska
function log_open($email) { // ------------------------------------------------------------ log open
  // vytvoří přihlášku a vloží informaci do logu a do _track
  global $TEST, $AKCE, $VERZE, $akce;
  if (!isset($_SESSION[$AKCE]->id_prihlaska)) {
    $ip= $_SERVER['HTTP_X_REAL_IP'] ?? $_SERVER['REMOTE_ADDR'];
    $email= pdo_real_escape_string($email);
    $ida= $akce->id_akce;
    $abbr= $version= $platform= null;
    ezer_browser($abbr,$version,$platform);
    $res= pdo_query_2("INSERT INTO prihlaska SET verze='$VERZE',open=NOW(),IP='$ip',"
        . "browser='$platform $abbr $version',id_akce=$ida,email='$email' ",1);
    if ($res!==false) {
      $_SESSION[$AKCE]->id_prihlaska= $id= $TEST<2 ? pdo_insert_id() : 1;
      pdo_query_2("INSERT INTO _track (kdy,kdo,kde,klic,op,fld,val) "
          . "VALUE (NOW(),'WEB','prihlaska',$id,'i','id_akce',$ida)",1);
      session_write_close();
      session_start(['cookie_lifetime'=>60*60*24*2]); // dva dny
    }
  }
} 
function log_write($clmn,$value) { // ---------------------------------------------------- log write
  global $AKCE, $TRACE;
  if (($id= ($_SESSION[$AKCE]->id_prihlaska??0))) {
    $val= $value=='NOW()' ? 'NOW()' : "'".pdo_real_escape_string($value)."'";
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
    $res= pdo_query_2("UPDATE prihlaska SET stav=IF(stav='','$novy',CONCAT(stav,'-','$novy')) WHERE id_prihlaska=$id",1);
    if ($res===false && $TRACE)
      display("LOG_APPEND_STAV fail for:$novy");
  }
  elseif ($TRACE)
      display("LOG_APPEND_STAV fail for:$novy - no sesssion");
}
function log_write_changes() { // ------------------------------------------------ log write_changes
  global $AKCE, $vars, $changes, $TRACE;
  if (($idw= ($_SESSION[$AKCE]->id_prihlaska??0))) {
    $changes= (object)[];
    foreach ((array)$vars as $name=>$val0) {
      // zapiš hodnoty s indexem 1 tzn. změněné
      if (in_array($name,['cleni','rodina'])) {
        if (!is_array($val0)) continue;
        foreach ($val0 as $id=>$val1) {
          if (!is_object($val1)) continue;
          foreach ($val1 as $fld=>$val2) {
            if (is_array($val2) && count($val2)==2) {
              if (!isset($changes->$name)) $changes->$name= [];
              if (!isset($changes->$name[$id])) $changes->$name[$id]= (object)[];
              ($changes->$name[$id])->$fld= $val2[1];
            }
          }
        }
      }
      if (in_array($name,['pobyt'])) {
        if (!is_object($val0)) continue;
        $id= $val0->id_pobyt;
        foreach ($val0 as $fld=>$val2) {
          if (is_array($val2) && count($val2)==2) {
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
  global $akce, $vars;
  // zkusíme najít poslední verzi přihlášky - je ve fázi (c)
  $found= '';
  $idp= select_2('id_pobyt','prihlaska',
      "id_pobyt!=0 AND id_akce=$akce->id_akce AND email='$email' ");
  if ($idp) goto end; // už se povedlo přihlásit
  list($idpr,$open)= select_2("SELECT id_prihlaska,open FROM prihlaska 
      WHERE id_pobyt=0 AND id_akce=$akce->id_akce AND email='$email' AND vars_json!='' 
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
  $chngs= json_decode($vars_json,false); // JSON objects will be returned as objects
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
          $vars->$name[$id]->$fld= 
            [is_array($vars->$name[$id]->$fld) ? $vars->$name[$id]->$fld[0] : $vars->$name[$id]->$fld
            ,$val2];
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
}
function log_close() { // ---------------------------------------------------------------- log close
  log_write('close','NOW()');
}
# ============================================================================= vytváření PDF obrazu
function gen_html($to_save=0) {
# vygeneruje textový tvar přihlášky, pro to_save=1 uloží do pobyt to_save=2 uloží do prihlasky
  global $akce, $vars;
  $ted= date("j.n.Y H:i:s");
  $html= '';
  $html.= "<h3 style=\"text-align:center;\">Údaje z online přihlášky na akci \"$akce->nazev\"</h3>";
  $html.= "<p style=\"text-align:center;\"><i>vyplněné $ted a doplněné dříve svěřenými osobními údaji</i></p>";
  // odlišení muže, ženy a dětí
  $sebou= []; $deti= $del_d= ''; $chuvy= '';
  $r= gets('r');
  $m= inits('o');
  $z= inits('o');
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
          $jmeno= "$o->jmeno $o->prijmeni";
          $adresa= "$o->ulice, $o->psc $o->obec";
          $chuvy.= "<br>$jmeno, $o->narozeni, bydliště: $adresa, <b>pečuje o: $o->Xpecuje_o</b>";
        }
        break;
    }
  }
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
    <td>Účast na LK MS mimo YS, YF: $r->r_ms</td></tr>";
  $html.= "</table>";
  if ($akce->p_upozorneni)
    $html.= "<p><i>Souhlas obou manželů s podmínkami účasti na kurzu byl potvrzen $ted.</i></p>";
  // vložit přihlášku jako PDF do záložky Dokumenty daného pobytu
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
  unset($_SESSION['akce']);
  session_write_close();
  session_start(['cookie_lifetime'=>60*60*24*2]); // dva dny
}
# ----------------------------------------------------------------------------------- pomocné funkce
function json_encode_2($s) { // ------------------------------------------------------ json encode_2
# korektní json encode
  $s= json_encode($s,JSON_UNESCAPED_UNICODE | JSON_HEX_QUOT);
  $sbr= str_replace('\r\n','<br>',$s);
  return $sbr;
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
    if (count($dmy)!=3) {
      $neuplne[]= "napište datum ve tvaru den.měsíc.rok ";
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
function simple_mail($replyto,$address,$subject,$body,$cc='') { // --------------------- simple mail
# odeslání mailu
# $MAIL=0 zabrání odeslání, jen zobrazí mail v trasování
# $_TEST zabrání posílání na garanta přes replyTo 
  global $api_gmail_user, $api_gmail_pass, $api_gmail_name, $MAIL, $TEST, $_TEST, $DOM;
//  global $trace;
  $msg= '';
  if ($TEST>1 || !$MAIL) {
    $DOM->mailbox= ['show',
        "<h3>Simulace odeslání mailu z adresy $api_gmail_name &lt;$api_gmail_user&gt;</h3>"
        . "<b>pro:</b>  "
        . (is_array($address) ? implode(', ',$address) : $address)
        . "<br><b>předmět:</b> $subject"
        . "<p><b>text:</b> $body</p>"];
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
    if (!$_TEST) {
      $mail->addReplyTo($replyto);
    }
    if ($cc) {
      $mail->AddCC($cc);
    }
    $mail->FromName= $api_gmail_name;
    if (is_array($address)) {
      foreach ($address as $adr) {
        $mail->AddAddress($adr);   
      }
    }
    else $mail->AddAddress($address);   
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
    $ok= false; 
    if (empty($mail->ErrorInfo)) {
      $ok= $mail->Send();
    }
    if ( $ok  ) {
      $msg= "ok";
    }
    else {
      $msg= "CHYBA při odesílání mailu došlo k chybě: $mail->ErrorInfo";
    }
  }
end:
  return $msg;
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
      pdo_query_2($qry,$quiet);
      $result= 1;
    }
    else {
      // provedení UPDATE pro jeden záznam s kontrolou starých hodnot položek
      $key_val= $cond_key;
      $qry= "SELECT $key_id FROM $table WHERE $key_id=$key_val $and ";
      if ( pdo_query_2($qry,$quiet) )  {
        $qry= "UPDATE $table SET $set WHERE $key_id=$key_val $and ";
        pdo_query_2($qry,$quiet);
        $result= 1;
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
  try {
    $m= null;
    if ( preg_match('/^\s*(?:INSERT INTO|UPDATE)\s+(\w+)/i',$query,$m) ) {
      $insert_update= $m[1];
      if ($TEST<2) {
        $res= $pdo->exec($query);
        if ($res===false) $err= $pdo->errorInfo()[2];
      }
      else 
        $res= 1;
    }
    else {
      $res= $pdo->query($query);
      if ($res===false) $err= $pdo->errorInfo()[2];
    }
    if ( $err ) { 
      $ok= 'ko';
      if ($quiet) {
        if ( isset($y) ) $y->error= (isset($y->error) ? $y->error : '').$err;
        log_error($err);
      }
      else log_error($err);
    }
    // trasování
    if ( $TEST && $totrace && strpos($totrace,'M')!==false && $insert_update!='_track') {
      $pretty= trim($myqry);
      if ($insert_update!='prihlaska')
        $pretty= "<b style='color:red'>$pretty</b>";
      if ( isset($y) ) $y->qry= (isset($y->qry)?"$y->qry\n":'')."* $ok \"$pretty\"\n ";
    }
  }
  catch (Exception $e) {
    $msg= $e->getMessage();
    if ($quiet) // aby nedošlo k zacyklení s log_error
      log_error($msg);
    else
      throw new Exception($err);
    $res= false;
  }
  return $res;
} // <== tudy jdou všechny SQL
function select_2($expr,$table='',$cond='') { // ------------------------------------------------ select 2
# navrácení hodnoty jednoduchého dotazu
# pokud je jediný argument je to celý dotaz
  if ( !$table ) {
    $result= array();
    $qry= $expr;
    $res= pdo_query_2($qry,1);
    if ( !$res ) log_error(wu("chyba funkce select:$qry/".pdo_error()));
    else $result= pdo_fetch_row($res);
  }
# pokud $expr obsahuje čárku, vrací pole hodnot, pokud $expr je hvězdička vrací objekt, 
  elseif ( strstr($expr,",") ) {
    $result= array();
    $qry= "SELECT $expr FROM $table WHERE $cond";
    $res= pdo_query_2($qry,1);
    if ( !$res ) log_error(wu("chyba funkce select:$qry/".pdo_error()));
    else $result= pdo_fetch_row($res);
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
    $result= $o[0];
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
