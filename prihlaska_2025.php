<?php
/*
  (c) 2025 Martin Smidek <martin@smidek.eu>
   
  3. verze online přihlašování pro YMCA Setkání (jen obnovy a LK MS YS)
  debuger je lokálne nastaven pro verze PHP: 7.2.33 - musí být ručně spuštěn Chrome

 */

session_start(['cookie_lifetime'=>60*60*24*2]); // dva dny
error_reporting(E_ALL);
ini_set('display_errors', 'On');

// detekce varianty: normální nebo testovací 
$_TEST=  preg_match('/-test/',$_SERVER["SERVER_NAME"]) ? '_test' : '';
$_ANSWER= $_SESSION[$_TEST?'dbt':'db2']['user_id']??0;
     
//$TEST_mail= 'martin@smidek.eu';
//$TEST_mail= 'zahradnicek@fnusa.cz';
//$TEST_mail= 'petr.janda@centrum.cz';
//$TEST_mail= 'p.kvapil@kvapil.cz';
//$TEST_mail= 'bucek@fem.cz';
//$TEST_mail= 'hanasmidkova@seznam.cz';
//$TEST_mail= 'j-novotny@centrum.cz';
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
      'Tento mail v evidenci YMCA Setkání nemáme, pokud jste se již nějaké naší akce zúčastnil/a, 
       přihlaste se prosím pomocí toho, který jste tehdy použil/a',
  'usermail_nad4' => 
      'Tento mail používá více osob ($jmena), 
      <br>přihlaste se prosím pomocí jiného svého mailu (nebo mailem manžela/ky).',
  'osoby_nad1' => 
      'Poznačte, koho na akci přihlašujete. Zkontrolujte a případně upravte zobrazené údaje.',
  'rozlouceni1' => 
      'Přejeme Vám hezký den.',
  'rozlouceni2' => 
      'Přejeme Vám příjemný pobyt.',
];
$akce_default= [ // pro DC Střelice
  'p_pozde'       =>  0, // od teď přihlášené brát jen jako náhradníky
  'p_rodina'      =>  0, // rodinné přihlášení
  'p_deti'        =>  0, // ... s dětmi
  'p_pro_par'     =>  0, // pro manželský pár, výjimečně s dítětem a jeho pečovatelem
  'p_pro_LK'      =>  0, // pro manželský pár s dětmi a osobními pečovateli na LK MS
  'p_rod_adresa'  =>  0, // umožnit kontrolu a úpravu rodinné adresy 
  'p_obcanky'     =>  0, // umožnit kontrolu a úpravu číslo obč. průkazu
  'p_jednotlivec' =>  0, // přihlášení jednotlivce
  'p_oso_adresa'  =>  0, // osobní adresa
  'p_registrace'  =>  0, // povolit registraci s novým mailem
  'p_souhlas'     =>  0, // vyžadovat souhlas (GDPR) 
  'p_ukladat'     =>  0, // povolit průběžné ukládání přihlášky + znovunačtení při přihlášení
  'p_kontrola'    =>  0, // vynutit kontrolu dat před uložením
  'p_oprava'      =>  0, // povolit načtení a opravu již uložené přihlášky
// jen pro obnovy MS
  'p_obnova'      =>  0, // OBNOVA MS: neúčastníky aktuálního LK brát jako náhradníky
  'p_vps'         =>  0, // OBNOVA MS: nastavit funkci VPS podle letního kurzu
// jen pro LK MS
  'p_upozorneni'  =>  0, // LETNÍ KURZ MS: vyžadovat akceptaci upozornění
  'p_dokument'    =>  0, // LETNÍ KURZ MS: vytvořit PDF a uložit jako dokument k pobytu
]; 
// ========================================================================================== .MAIN.
// rozlišení na volání z příkazové řádky - úvodní s prázdným SESSION nebo po ctrl-r
// a na volání přes AJAX z klientské části

// pro první volání nebo ctrl-r nastav akci
// hodnoty pro test a mail musí být navržené přes GET - uplatní se jen při během přihlášení do Answeru
//   $MAIL:  1 - maily se posílají | 0 - mail se jen ukáže - lze nastavit url&mail=0
//   $TEST:  0 - bez testování | 1 - výpis stavu a sql | 2 - neukládat | 3 - login s testovacím mailem
if (!count($_POST)) {
  if (!isset($_GET['akce']) ) 
    die("Online přihlašování není k dispozici."); 
  else {
    $TEST= $_GET['test']??0 ? ($_ANSWER?(0+$_GET['test']):0) : 0;
    $MAIL= $_GET['mail']??1 ? 1 : ($_ANSWER?0:1);
    initialize($_GET['akce']); // přenese TEST i MAIL
  }
}
$AKCE= "A_{$_SESSION['akce']}";
$vars= $_SESSION[$AKCE]??(object)[];
$TEST= $vars->TEST;
$MAIL= $vars->MAIL;
$TEST= 1;
$MAIL= 0;

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
      'kontrola'=>0,                  // ???
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
  global $DOM, $TEXT, $vars, $akce;
  if ($pin!=$vars->pin) { // chyba pinu
    $DOM->usermail_pod= zvyraznit("<p>Do mailu jsme poslali odlišný PIN, podívejte se prosím pozorně</p>");
  }
  else { // pin je ok - založíme záznam v tabulce prihlaska
    $vars->email= $email; // pro korespondenci
    log_open($email);  // email je ověřený 
    // zjistíme, zda jej máme v databázi
    $regexp= "REGEXP '(^|[;,\\\\s]+)$email($|[;,\\\\s]+)'";
    list($pocet,$ido,$idr,$jmena,$rodiny,$kontakty)= select_2(
        "COUNT(id_osoba),id_osoba,IFNULL(id_rodina,0),GROUP_CONCAT(CONCAT(jmeno,' ',prijmeni))"
        . ",COUNT(DISTINCT IFNULL(id_rodina,0)),GROUP_CONCAT(DISTINCT kontakt)",
        'osoba AS o LEFT JOIN tvori USING (id_osoba) LEFT JOIN rodina USING (id_rodina)',
        "o.deleted='' AND (role IN ('a','b') OR ISNULL(role))"
        . "AND (kontakt=1 AND email $regexp OR kontakt=0 AND emaily $regexp)");
    display("$pocet,$ido,$idr,$jmena,$rodiny,$kontakty");
//    // zjistíme, zda to může být rozpracovaná přihláška
//    $open= log_find_saved($email); // uložená přihláška a nejsou přihlášení
//    if ($open) { // -------- nalezena rozepsaná přihláška
//      $DOM->user= "<i class='fa fa-user'></i> $jmena<br>$email";
//      $vars->ido= $ido;
//      $vars->idr= $idr;
//      dotaz("Chcete pokračovat ve vyplňování přihlášky uložené $open?",'rozepsana','prihlaska');
//    } // nalezena rozepsaná přihláška
    if ($pocet==1) { // -------------------------------- známý a jednoznačný mail
      $DOM->user= "<i class='fa fa-user'></i> $jmena<br>$email";
      $vars->klient= $jmena;
      $vars->ido= $ido;
      $vars->idr= $idr;
      // osobu známe  - zjistíme zda již není přihlášen
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
        $DOM->usermail= 'hide';
        return prihlaska();
      }
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
      $DOM->usermail_nad= str_replace('$jmena',$jmena,$TEXT->usermail_nad4);
      $DOM->email= 'enable';
      $DOM->kontrola_pinu= "hide";
      $DOM->pin= "hide";
      $DOM->zadost_o_pin= "show";
      $DOM->usermail_pod= '';
    } // nejednoznačný mail      
  }
} // overit pin
// ----------------------------------------------------------------------------------------- kontrola
function kontrola($all) { 
# zkontroluje formát dat
  debug($all,'kontrola');
//  prihlaska();
} // kontrola
// ------------------------------------------------------------------------------------- ulozit stav
function ulozit_stav() { 
# uloží stav přihlášky do tabulky prihlaska
  prihlaska();
} // uložit stav
// -------------------------------------------------------------------------------------- přihlas se
function prihlaska() { 
# připrav prázdný formulář přihlášení osob
# doplň DOM o položky osob
  global $DOM, $vars, $akce;
  // načti data
  kompletuj_pobyt($vars->idr,$vars->ido);
  // nastavení formuláře
  $vars->form= (object)[
      'pass'=>0, // inicializovat pozici pro 0
      'par'=>1,'deti'=>$akce->p_deti?2:0,'pecouni'=>$akce->p_deti?1:0, // 1=tlačítko, 2=seznam
      'rodina'=>$akce->p_rod_adresa,'pozn'=>1,'souhlas'=>0,
      'oprava'=>0,    // 1 => byla načtena již uložená přihláška a je možné ji opravit
      'todo'=>0,      // označit červeně chybějící povinné údaje po kontrole formuláře
      'exit'=>0,      // 1 => první stisk 
  ];
  $form= form();
  // zzměny zobrazení
  $DOM->usermail= 'hide';
  $DOM->form= ['show',$form];
} // prihlaska
// --------------------------------------------------------------------------------------- přihlásit
function prihlasit($elems) { 
# zapíše přihlášku do Answeru
  global $DOM, $vars, $akce, $errors;
  debug($elems,'prihlasit');
  read_elems($elems);
  $spolu= 0;
  foreach (array_keys($vars->cleni) as $id) {
    $spolu+= get('c','spolu',$id);
  }
  if (!$spolu) {
    hlaska('Přihlaste prosím na akci aspoň jednu osobu');
  }
  else {
    // vytvoření pobytu
    log_append_stav('zapis');
    // účast jako ¨účastník' pokud není p_obnova => neúčast na LK znamená "náhradník"
    $ucast= 0; // = účastník
    if ($akce->p_obnova) {
      $jako= byli_na_aktualnim_LK(key($vars->rodina));
      if (!$jako) $ucast= 9; // = náhradník
      elseif ($jako==2 && $akce->p_vps) $ucast= 1; // VPS
    }
    elseif ($akce->p_pro_LK) {
      $sluzba= get('p','Xvps');
      $ucast= $sluzba==1 ? 1 : 13;
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
    db_close_pobyt();
    log_write_vars(); // po zápisu do pobytu
    // ------------------------------ uzavři formulář závěrečnou zprávou a mailem
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
         <br>Těšíme se na setkání na akci
         <br>$akce->garant_jmeno"];
db_end:
    if (count($errors)) {
      log_append_stav('ko');
      log_error(implode('|',$errors));
      $DOM->form= ['show',
          "Při zpracování přihlášky došlo bohužel k chybě. 
           <br>Přihlaste se prosím posláním mailu organizátorům akce
           <br><a href=mailto:'$akce->garant_mail'>$akce->garant_mail</a>"];
    }
  }
} // uložit
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

// ================================================================================= prvky formuláře
function form() { trace();
  global $vars, $akce;
  $msg= '';
  $mis_souhlas= '';
  $mis_upozorneni= ['a'=>'','b'=>''];
//  $button= "button onclick='save_position()' type='submit'";
  $red_x= 'fa fa-times fa-red';
  $clenove= '';
  if ($vars->form->par) { // -------------------------------------------------------- zobrazení páru
    foreach ($vars->cleni as $id=>$clen) {
      $role= get_role($id);
      if (in_array($role,['a','b'])) {
        $upozorneni= $akce->p_upozorneni
          ? "<p class='souhlas'><input type='checkbox' id='{$id}_Xupozorneni' value=''  "
          . (get('o','Xupozorneni',$id) ? 'checked' : '')
          . " {$mis_upozorneni[$role]}><label for='{$id}_Xupozorneni' class='souhlas'>"
          . str_replace('@',$role=='b'?'a':'',$akce->upozorneni)
          . "</label></p>"
          : '';
//        $clenove.= "<div class='clen paru'>" 
        $clenove.= "<div class='clen'>" 
//            . elem_input('o',$id,['spolu'])
            . ( $id>0
                ? elem_input('o',$id,['spolu']) . elem_text('o',$id,['jmeno','prijmeni']) 
                  . ($role=='b' ? elem_text('o',$id,['roz. ','rodne']) : '')
                  . elem_text('o',$id,[', ','narozeni',',','role'])
//                  . elem_text('o',$id,[' ... TEST: ','vzdelani','|','cirkev'])
                : elem_input('o',$id,['spolu']) . elem_input('o',$id,['jmeno','prijmeni'])
                  . ($role=='b' ? elem_input('o',$id,['rodne']) : '')
                  . elem_input('o',$id,[',','narozeni'])
                  . elem_text('o',$id,['role']))
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
  if ($vars->form->deti) { // ------------------------------------------------------- zobrazení dětí
    $clenove.= "<div id='deti' class='cleni'>";
    $deti= '';
    $jsou_deti= 0;
    foreach ($vars->cleni as $id=>$clen) {
      if ($id<0 || get_role($id)!='d') continue;
      $jsou_deti++;
      if (get('o','umrti',$id)) 
        $deti.= "<div class='clen'>" 
          . elem_input('o',$id,['spolu'])
          . elem_text('o',$id,['jmeno','prijmeni',', *','narozeni',' &dagger;','umrti'])
          . "</div>";
      else
        $deti.= "<div class='clen'>" 
          . elem_input('o',$id,['spolu'])
          . elem_text('o',$id,['jmeno','prijmeni',',','narozeni',',','role'])
          . elem_input('o',$id,['note'])
          . "</div>";
    }
    foreach ($vars->cleni as $id=>$clen) {
      if ($id>0 || get_role($id)!='d') continue;
      if (get('o','prijmeni',$id) || get('o','jmeno',$id)) $jsou_deti++;
        $deti.= "<div class='clen'>" 
          . elem_input('o',$id,['spolu','jmeno','prijmeni','narozeni','note'])
          . "</div>";
    }
    if ($deti) {
      $pozn= $jsou_deti ? '' 
          : "<br>Pokud děti nemáte, nechte všechna pole prázdná a zrušte 'jede na akci'.";
      $clenove.= "<p><i>Naše děti (zapište prosím i ty, které necháváte doma). $pozn</i></p>";
    }
    $clenove.= $deti;
    $clenove.= "<br><button name='cmd_dalsi_dite'>
      <i class='fa fa-green fa-plus'></i> chci přidat další dítě</button>";
    $clenove.= "</div>";
  }
  if ($vars->form->pecouni) { // ------------------------------------------------- zobrazení pečounů
    // pokud jsou nějací členové s role=p tak zobraz napřed je, teprve na další stisk přidej prázdné
    // form->pecouni: 1=jen tlačítko 2=jen existující 3=prázdná pole
    if ($vars->form->pecouni==1 ) {
      $clenove.= "<br><button name='cmd_dalsi_pecoun'>
        <i class='fa fa-green fa-plus'></i> chci přihlásit osobního pečovatele</button>";
    }
    if ($vars->form->pecouni==2) {
      $clenove.= "<div id='pecouni' class='cleni'>";
      $clenove.= '<p><i>Volba osobního pečovatele</i></p>';
      foreach ($vars->cleni as $id=>$clen) {
        if ($id<0 || get_role($id)!='p') continue;
        $spolu= get('o','spolu',$id);
        $clenove.= "<div class='clen'>" 
            . elem_input('o',$id,['spolu'])
            . elem_text('o',$id,['jmeno','prijmeni',', ','narozeni'])
            . ($spolu ? '<br>'.elem_input('o',$id,['obcanka','telefon','Xpecuje_o']) : '' )            
            . "</div>";
      }
      foreach ($vars->cleni as $id=>$clen) {
        if ($id>0 || get_role($id)!='p') continue;
        $clenove.= "<div class='clen'>" 
            . elem_input('o',$id,['spolu','jmeno','prijmeni','narozeni'])
            . ($clen->spolu ? '<br>'.elem_input('o',$id,['obcanka','telefon','Xpecuje_o']) : '' )            
            . "</div>";
      }
      $clenove.= "<br><button name='cmd_dalsi_pecoun'>
        <i class='fa fa-green fa-plus'></i> chci přihlásit dalšího osobního pečovatele</button>";
      $clenove.= "</div>";
    }
  }
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
  if ($akce->p_pro_LK) {
    $umi= get('r','r_umi');
    if ($umi && in_array(1,explode(',',$umi))) {
      $pobyt.= elem_input('p',0,['Xvps']);
    }
  }
  // -------------------------------------------- souhlas
  $souhlas= '';
  if ($vars->form->souhlas) {
      $souhlas= "<p class='souhlas'><input type='checkbox' id='chk_souhlas' value=''  "
      . ($vars->chk_souhlas ? 'checked' : '')
      . " $mis_souhlas><label for='chk_souhlas' class='souhlas'>$akce->form_souhlas</label>"
      . "</p>";
  }
  // -------------------------------------------- zapnout kontrolu dat
  $kontrola= '';
  if ($akce->p_kontrola) {
    $kontrola= "<button id='kontrola' onclick='php(\"*\");'><i class='fa fa-question'></i> 
      zkontrolovat před odesláním (nutné, lze opakovat)</button>";
    $enable_send= $vars->kontrola ? '' : 'disabled title="před odesláním je nutné zkontrolovat korektnost vyplněných údajů tlačítkem vlevo" ';
    $kontrola_txt= 'Zkontrolujte a případně upravte zobrazené údaje.';
  }
  else {
    $enable_send= '';
    $kontrola_txt= '';
  }
  // -------------------------------------------- průběžně ukládat
  $ulozit= $akce->p_ukladat
    ? "<div class='clen paru'><i>Neodeslanou přihlášku lze průběžně 
        <button id='ulozit_stav' onclick='php();'> ukládat </button>. 
        Lze prohlížeč zavřít a pokračovat po přihlášení.</i></div>"
    : '';
  // -------------------------------------------- redakce formuláře
  $enable_green= $vars->kontrola ? 'fa-green' : '';
  $odeslat= $vars->form->oprava??0 ? "uložit opravu" : "odeslat přihlášku";
  $exit= $vars->form->exit 
      ? "<button name='cmd_exit'><i class='$red_x'></i> smazat rozepsanou přihlášku bez uložení</button>
         <button name='cmd_exit_no'> ... pokračovat v úpravách</button>"
      : "$kontrola
        <button id='prihlasit' onclick='php(\"*\");' $enable_send><i class='fa $enable_green fa-send-o'></i>
           $odeslat</button>
         <button id='zahodit' onclick='php();'><i class='$red_x'></i> neposílat</button>";
  $form= <<<__EOF
    $ulozit
    <p>Poznačte, koho na akci přihlašujete. $kontrola_txt</p>
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
}
function hlaska($text,$continue='') { // --------------------------------- hláška
  global $DOM;
  $DOM->alertbox= 'show'; $DOM->popup_mask= 'show';
  $DOM->alertbox_text= $text;
  $cmd= $continue ? "php2('$continue');" : "jQuery('.popup').hide();jQuery('#popup_mask').hide();";
  $DOM->alertbox_butts= "
    <button onclick=\"$cmd\">OK</button>";
} // popup s OK
function dotaz($dotaz,$ano,$ne) { // -------------- dotaz s funkcemi pro ano a ne
  global $DOM;
  $DOM->alertbox= 'show'; $DOM->popup_mask= 'show';
  $DOM->alertbox_text= $dotaz;
  $cmd_ano= $ano ? "php2('$ano');" : "jQuery('.popup').hide();jQuery('#popup_mask').hide();";
  $cmd_ne= $ne ? "php2('$ne');" : "jQuery('.popup').hide();jQuery('#popup_mask').hide();";
  $DOM->alertbox_butts= "
    <button onclick=\"$cmd_ano\">ANO</button> &nbsp;
    <button onclick=\"$cmd_ne\">NE</button>
    ";
} // popup s ANO / NE
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
function read_elems($elems) { // -------------------------------------------------------- read elems
  global $vars;  
  foreach ($elems as $id=>$val) {
    $m= null;
    if (preg_match("/(.)_([\-\d]+)_(.*)/",$id,$m)) { // t_idt_name
      $t= $m[1]; $idt= $m[2]; $fld= $m[3];
      switch ($t) {
        case 'o': $tab= $vars->cleni[$idt]; break;
        case 'r': $tab= $vars->rodina[$idt]; break;
        case 'p': $tab= $vars->pobyt; break;
        default: continue 2;
      }
      if (is_array($tab->$fld)) {
        if ($val!=$tab->$fld[0]) {
          $tab->$fld[1]= $val;
          $vars->kontrola= 0;
        }
        else {
          unset($tab->$fld[1]);
        }
      }
      elseif ($tab->$fld!=$val) {
        $tab->$fld= [$tab->$fld,$val];
      }
    }
  }
} // převod do $vars

// =============================================================================== zobrazení stránky
function page() {
  global $_TEST, $TEST, $TEST_mail, $TEXT, $akce, $rel_root;
  $if_trace= $TEST ? "style='overflow:auto'" : '';
  $TEST_mail= $TEST_mail??'';
  $icon= "akce$_TEST.png";
  $hide= "style='display:none'";
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
    <script src="prihlaska_2025.js" type="text/javascript" charset="utf-8"></script>
    <link rel="stylesheet" id="customify-google-font-css" href="//fonts.googleapis.com/css?family=Open+Sans%3A300%2C300i%2C400%2C400i%2C600%2C600i%2C700%2C700i%2C800%2C800i&amp;ver=0.3.5" type="text/css" media="all">
    <link rel="stylesheet" href="/ezer3.2/client/licensed/font-awesome/css/font-awesome.min.css?" type="text/css" media="screen" charset="utf-8">
    <script>
      var myself_url= "$rel_root/prihlaska_2025.php";
    </script>
  </head>
  <body onload="php2('start')" $if_trace>
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
        <div $hide id='alertbox' class='popup' title='Upozornění'>
          <p id='alertbox_text'></p>
          <p id='alertbox_butts'></p>
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
//    $totrace= 'Mu';
    $totrace= 'u';
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
  if (!($vars->form??null)) {
    $vars->form= (object)[
        'pass'=>0, // inicializovat pozici pro 0
        'par'=>1,'deti'=>2,'pecouni'=>1, // 1=tlačítko, 2=seznam
        'rodina'=>$akce->p_rod_adresa,'pozn'=>1,'souhlas'=>$akce->p_souhlas,
        'oprava'=>0,    // 1 => byla načtena již uložená přihláška a je možné ji opravit
        'todo'=>0,      // označit červeně chybějící povinné údaje po kontrole formuláře
        'exit'=>0,      // 1 => první stisk 
    ];
  }
end:    
//  global $trace;
//  $trace.= debugx($akce,'hodnoty web_online');
  if ($msg) {
    die($msg);
  }
} // doplnění infromací o akci
function polozky() { // -------------------------------------------------------------------- polozky
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
  // definice obsahuje:  položka => [ délka , popis , formát ]
  //   X => pokud jméno položky začíná X, nebude se ukládat, jen zapisovat do PDF
  //   * => pokud popis začíná hvězdičkou bude se údaj vyžadovat (hvězdička za zobrazí červeně)
  //        je to ale nutné pro každou položku naprogramovat 
  $p_fld= [ // zobrazené položky tabulky POBYT, nezobrazené: id_pobyt, web_changes
      'pracovni'    =>['64/4','sem prosím napište vzkaz organizátorům, např. informace, které nebylo možné nikam napsat','area'],
      'funkce'      =>[0,'funkce na akci','select'],
      'web_changes' =>[0,'indikátor změn','x'],
      'Xvps'        =>[15,'* služba na kurzu','select'],
    ];
  $r_fld= array_merge(
    [ // položky tabulky RODINA
      'nazev'     =>[15,'* název rodiny',''],
      'ulice'     =>[15,'* ulice a č.or. NEBO č.p.',''],
      'psc'       =>[ 5,'* PSČ',''],
      'obec'      =>[20,'* obec/město',''],
      'spz'       =>[12,'SPZ auta na akci','']],
    $akce->p_pro_LK ? [
      'datsvatba' =>[ 9,'* datum svatby','date'],
      'r_ms'       =>[12,'počet účastí na jiném kurzu MS než YMCA Setkání či YMCA Familia','number'],
      'r_umi'      =>[ 0,'seznam odborností','x'], // podle answer_umi např. 1=VPS
    ] : []
  );
  $o_fld= array_merge(
    [ // položky tabulky OSOBA
      'spolu'     =>[ 0,'pojede<br />na akci','check_spolu','abdp'],
      'jmeno'     =>[ 7,'* jméno','','abdp'],
      'prijmeni'  =>[10,'* příjmení','','abdp'],
      'rodne'     =>[10,'rozená','','ab'],
      'narozeni'  =>[10,'* datum narození','date','abdp'],
      'umrti'     =>[10,'rok úmrtí','','abdp'],
      'role'      =>[ 9,'vztah k rodině?','select','abdp'],
      'vztah'     =>[ 9,'manžel/maželka','select','ab'],
      'note'      =>['70/2','poznámka (léky, alergie, apod.)','area','d']],
    $akce->p_obcanky ? [
      'obcanka'   =>[11,'* číslo OP nebo pasu','','abp'],
      'telefon'   =>[15,'* telefon','','abp'],
      'email'     =>[35,'* e-mailová adresa','','ab']] : [],
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
} // definice položek formuláře
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
    $html.= ' '.get_fmt($table,$fld,$id);
  }
  return "<span>$html</span>";
}
function elems_missed($table,$id=0,$but=[]) { // --------------------------------------- elem missed
  global $p_fld, $r_fld, $o_fld, $vars;
  $missed= 0;
  if ($table=='p') {
    foreach ($p_fld as $f=>list(,$title,$typ)) {
      $v= $vars->pobyt->$f ?? '';
      if (is_array($v) && substr($title,0,1)=='*') {
        $v= $v[1] ?? $v[0];
        if ($v=='' || in_array($typ,['select','sub_select']) && $v==0) {
          if (!in_array($id,$but)) {
            $missed= 1;
            display("chybí $table $id $f");
            goto end;
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
              $missed= 1;
              display("chybí $table $id $f");
              goto end;
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
              $missed= 1;
              display("chybí $table $id $f");
              goto end;
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
  if (!isset($pair->_show_)) $pair->_show_= 1;
  foreach ($flds as $fld) {
    if (!isset($desc[$fld])) {
//      $html.= $fld;
      continue;
    }
    $name= "{$table}_$prfx$fld";
    list($len,$title,$typ)= $desc[$fld];
    if ($typ=='x') continue;
    // rozpoznání povinnosti položky
    $v= is_array($pair->$fld) ? ($pair->$fld[1] ?? $pair->$fld[0]) : $pair->$fld;
    $todo= '';
    if (substr($title,0,1)=='*') { //  && ($table!='o' || $pair->spolu)) {
      $title=  "<b style='color:red'>*</b>".substr($title,1);
      if ($vars->form->todo 
        && ($v=='' || in_array($typ,['select','sub_select']) && $v==0 || isset($pair->_corr_->$fld))) {
        $todo= " class='missing'";
      }
    }
    switch ($typ) {
    case 'check_spolu':
    case 'check':
      $x=  $v ? 'checked' : '';
      $html.= "<label class='$typ'>$title<input type='checkbox' id='$name' value='x' $x$todo></label>";
      break;
    case 'select':
    case 'sub_select':
      $html.= "<label class='upper'>$title<select$todo id='$name'>";
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
      $html.= "<label class='upper-area'>$title<textarea rows='$rows' cols='$cols' id='$name'$todo>$v</textarea></label>";
      break;
    case 'number':
      $v= $v?: 0;
    default:
      $x= $v ? "value='$v'" : ''; // "placeholder='$holder'";
      $html.= "<label class='upper'>$title<input type='text' id='$name' size='$len' $x$todo></label>";
    }
  }
  return $html;
}
function elem_check(&$errs,$case,$title,$table,$fld,$id=0) {
  global $vars;
  $ok= 1;
  $val= get($table,$fld,$id);
  if ($val!==false) {
    switch ($case) {
      case 'date':
        $ok= check_datum($val,$title,$errs); break;
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
  if ($table=='r' && !$id) $id= key($vars->rodina);
  $pair= $table=='r' ? $vars->rodina[$id] : ($table=='p' ? $vars->pobyt : $vars->cleni[$id]);
  if (!isset($pair->_corr_)) $pair->_corr_= (object)[];
  if ($ok)
    unset($pair->_corr_->$fld);
  else
    $pair->_corr_->$fld= 1;
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
function vytvor_clena($ido,$role,$spolu) { // ----------------------------------------- vytvor clena
  // inicializace dat pro dospělou osobu, přidáme roli a že je na akci
  global $o_fld;
  $vars->cleni[$ido]= (object)[];
  foreach ($o_fld as $f=>list(,$title,$typ,$omez)) {
    if (strpos($omez,$role)!==false)
      $vars->cleni[$ido]->$f= substr($title,0,1)=='*' ? [init_value($typ)] : init_value($typ);
  }
  $vars->cleni[$ido]->role= $role;
  $vars->cleni[$ido]->spolu= $spolu;
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
function nacti_clena($ido,$role,$spolu) { // -------------------------------------------------- nacti clena
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
  $vars->cleni[$ido]->spolu= $o->umrti ? 0 : $spolu;
}
function db_nacti_cleny_rodiny($idr,$prvni_ido) { // ------------------------- db nacti_cleny_rodiny
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
  // web_changes= 1/2 pro INS/UPD pobyt+spolu | 4/8 pro INS/UPD osoba | 16/32 pro INS/UPD rodina,tvori
  $ida= $akce->id_akce;
  $chng= array(
    (object)['fld'=>'id_akce',     'op'=>'i','val'=>$ida],
    (object)['fld'=>'web_zmena',   'op'=>'i','val'=>date('Y-m-d')]
  );
  $idp= _ezer_qry("INSERT",'pobyt',0,$chng);
  if (!$idp) $errors[]= "Nastala chyba při zápisu do databáze (p)"; 
  $vars->pobyt->id_pobyt= $idp;
  log_write('id_pobyt',$idp);
  set('p','web_changes',1);
  return $idp;
}
function db_vytvor_nebo_oprav_clena($id) { // --------------------------- db vytvor_nebo_oprav_clena
  global $errors, $o_fld, $akce, $vars; 
  // web_changes= 1/2 pro INS/UPD pobyt+spolu | 4/8 pro INS/UPD osoba | 16/32 pro INS/UPD rodina,tvori
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
      set('p','web_changes',get('p','web_changes')|2);
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
    set('p','web_changes',get('p','web_changes')|4);
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
      set('p','web_changes',get('p','web_changes')|16);
    }
  } // nenašli => zapíšeme novou osobu a připojíme ji do rodiny
  else { // našli => opravíme změněné hodnoty položek existující osoby
    $chng= [];
    $kontakt= 0;
    foreach ((array)$clen as $f=>$vals) {
      if (!isset($o_fld[$f]) || substr($f,0,1)=='X') continue; // položka začínající X nepatří do tabulky
      if (in_array($f,['spolu','role','vztah'])) continue; // nepatří do tabulky
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
      set('p','web_changes',get('p','web_changes')|8);
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
    set('p','web_changes',get('p','web_changes')|8);
  } // zapojíme do pobytu
end:
  // konec
}
function db_vytvor_nebo_oprav_rodinu() { // ---------------------------- do vytvor_nebo_oprav_rodinu
# oprav rodinné údaje resp. vytvoř novou rodinu
  global $akce, $r_fld, $vars, $errors;
  // web_changes= 1/2 pro INS/UPD pobyt+spolu | 4/8 pro INS/UPD osoba | 16/32 pro INS/UPD rodina,tvori
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
    set('p','web_changes',get('p','web_changes')|16);
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
      set('p','web_changes',get('p','web_changes')|32);
    }
  }
}
function db_close_pobyt() { // ------------------------------------------------------ db close_pobyt
  global $errors, $p_fld, $vars;
  // úschova pobyt
  $idr= key($vars->rodina);
  $web_json= vytvor_web_json();
  $chng= array(
    (object)['fld'=>'i0_rodina',   'op'=>'i','val'=>$idr],
    (object)['fld'=>'web_changes', 'op'=>'i','val'=>get('p','web_changes')],
    (object)['fld'=>'funkce',      'op'=>'i','val'=>get('p','funkce')],
    (object)['fld'=>'web_json',    'op'=>'i','val'=>$web_json],
  );
  foreach ($vars->pobyt as $f=>$vals) {
    if (!isset($p_fld[$f]) || substr($f,0,1)=='X') continue; // položka začínající X nepatří do tabulky
    if (in_array($f,['web_changes','funkce'])) continue; // dávají se vždy
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
        set('p','web_changes',get('p','web_changes')|8);
      }
    }
  }
}
# ------------------------------------------------------------------------------------ log prihlaska
function log_open($email) { // ------------------------------------------------------------ log open
  global $AKCE, $akce;
  if (!isset($_SESSION[$AKCE]->id_prihlaska)) {
    $ip= $_SERVER['HTTP_X_REAL_IP'] ?? $_SERVER['REMOTE_ADDR'];
    $email= pdo_real_escape_string($email);
    $ida= $akce->id_akce;
    $abbr= $version= $platform= null;
    ezer_browser($abbr,$version,$platform);
    $res= pdo_query_2("INSERT INTO prihlaska SET open=NOW(),IP='$ip',"
        . "browser='$platform $abbr $version',id_akce=$ida,email='$email' ",1);
    if ($res!==false) {
      $_SESSION[$AKCE]->id_prihlaska= pdo_insert_id();
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
function log_write_vars() { // ------------------------------------------------------ log write_vars
  global $AKCE, $vars, $TRACE;
  if (($id= ($_SESSION[$AKCE]->id_prihlaska??0))) {
    $val= json_encode_2($vars);
    $res= pdo_query_2("UPDATE prihlaska SET vars_json='$val' WHERE id_prihlaska=$id",1);
    if ($res===false && $TRACE)
      display("LOG_WRITE_VARS fail");
  }
  elseif ($TRACE)
      display("LOG_WRITE_VARS fail - no sesssion");
} // zapíše $vars před zobrazením formuláře 
function log_find_saved($email) { // ------------------------------------------------ log find_saved
  global $akce, $vars;
  // zkusíme najít poslední verzi přihlášky - je ve fázi (c)
  $found= '';
  $idp= select_2('id_pobyt','prihlaska',
      "id_pobyt!=0 AND id_akce=$akce->id_akce AND email='$email' ");
  if ($idp) goto end; // už se povedlo přihlásit
  list($idpr,$open)= select_2('id_prihlaska,open','prihlaska',
      "id_pobyt=0 AND id_akce=$akce->id_akce AND email='$email' AND vars_json!='' "
    . " AND id_prihlaska>110 " // předtím není zohledněno UTF-8
    . " ORDER BY id_prihlaska DESC LIMIT 1");
  if (!$idpr) goto end;
  $vars->continue= $idpr;
  $found= sql_time1($open);
//  die('end');
end:
  return $found;
} // načtení data uloženého stavu 
function log_load_vars($email) { // -------------------------------------------------- log read_vars
  global $akce, $o_fld, $vars;
  // najdeme poslední verzi přihlášky - je ve fázi (c)
  list($idx,$vars_json)= select_2('id_prihlaska,vars_json','prihlaska',
      "id_pobyt=0 AND id_akce=$akce->id_akce AND email='$email' AND vars_json!='' "
    . "ORDER BY id_prihlaska DESC LIMIT 1");
  if (!$idx) goto end;
  $id_new= $vars->id_prihlaska;
  $vars= json_decode($vars_json,false); // JSON objects will be returned as objects
  $vars->cleni= (array)$vars->cleni;
  $vars->rodina= (array)$vars->rodina;
  $vars->history= 'x';
  $vars->id_prihlaska=$id_new;
  foreach ($vars->cleni as $clen) {
    foreach ($o_fld as $f=>list(,,$typ,)) {
      if (isset($clen->$f) && $typ=='sub_select' && is_object($clen->$f)) {
        $clen->$f= (array)$clen->$f;
      }
    }
  }
  log_append_stav("reload_$idx");
//  die('end');
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
function sql2oddo($x1,$x2,$vnutit_rok=0) { // --------------------------------------------------------- sql 2 oddo
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
      $datum= "$d1 - $d2. $m1"  . ($r1!=$letos || $vnutit_rok ? ". $r1" : '');
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
      '86.49.254.42','86.49.254.138','80.95.103.170','217.64.3.170', // GAN
      '95.82.145.32'] // JZE
      );
}
//function form_stamp() { // -------------------------------------------------------------- form stamp
//  global $AKCE, $vars;
//  $stamp= date("i:s");
//  $_SESSION[$AKCE]->stamp= $stamp;
//  session_write_close();
//  display("STAMP {$vars->faze}: $stamp ... {$_SESSION[$AKCE]->stamp}");
//  return $stamp;
//}
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
  global $TEST, $trace, $USER;
  $ok= 1;
  if ($TEST<2) {
    $USER->abbr= 'WEB';
    $ok= ezer_qry_2($op,$table,$cond_key,$chng,(object)['soft_u'=>1]);
  }
  if ($TEST) {
    $trace.= debugx($chng,"$op $table.$cond_key = $ok (test=$TEST)");
  }
  return $ok;
}
function ezer_qry_2 ($op,$table,$cond_key,$zmeny,$par=null) { // oproti Ezer verzi netestuje old
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
  global $ezer_db, $mysql_db, $mysql_db_track, $mysql_tracked, $USER;
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
//    $result= $tab=="_cis" ?  $id_cis : pdo_insert_id();
    $result= $res===false ? 0 : pdo_insert_id();
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
//      case 'a':
//        $set.= "$del$fld=concat($fld,'$val')";
//        break;
//      case 'p':
//        $set.= "$del$fld=concat('$val',$fld)";
//        break;
//      case 'd': // delete záznam row v chat
//        $va= explode('|',$zmena->old);
//        $old= pdo_real_escape_string($zmena->old);
//        $zmena->old_val= "{$va[2*$zmena->row-2]}|{$va[2*$zmena->row-1]}";
//        unset($va[2*$zmena->row-2],$va[2*$zmena->row-1]);
//        $vn= pdo_real_escape_string(implode('|',$va));
//        $set.= "$del$fld='$vn'";
//        $and.= " AND $fld='$old'";
//        break;
//      case 'c': // change záznam row v chat
//        $old= pdo_real_escape_string($zmena->old);
//        $va= explode('|',$old);
//        $zmena->old_val= "{$va[2*$zmena->row-2]}|{$va[2*$zmena->row-1]}";
//        $va[2*$zmena->row-1]= $val;
//        $vn= implode('|',$va);
//        $set.= "$del$fld='$vn'";
//        $and.= " AND $fld='$old'";
//        break;
      case 'u':
//      case 'U': // určeno pro hromadné změny
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
//  case 'UPDATE_keys':
////                                                         debug($zmeny,"qry_update($op,$table,$cond_key)");
//    $akeys= explode(',',$cond_key);
//    sort($akeys);
//    foreach ($akeys as $i => $key) {
//      $tracked[$i][0]= $zmeny;
//      $tracked[$i][0]->key= $key;
//    }
//    $keys= implode(',',$akeys);
//    $fld= $zmeny->fld;
//    $val= pdo_real_escape_string($zmeny->val);
//    switch ( $zmeny->op ) {
//    case 'm':
//      // zjištění starých hodnot podle seznamu klíčů
//      $qry= "SELECT GROUP_CONCAT($fld SEPARATOR '|') as $fld FROM $table WHERE $key_id IN ($keys)";
//      $res= pdo_query_2($qry,1);
//      if ( $res ) {
//        $row= pdo_fetch_assoc($res);
//        foreach (explode('|',$row[$fld]) as $i => $old) {
//          $tracked[$i][0]->old= $old;
//        }
//      }
//      $qry= "UPDATE $table SET $fld='$val' WHERE $key_id IN ($keys)";
//      break;
//    case 'a':
//    case 'p':
//      $concat= $zmeny->op=='a' ? "concat($fld,'$val')" : "concat('$val',$fld)";
//      $qry= "UPDATE $table SET $fld=$concat WHERE $key_id IN ($keys)";
//      break;
//    case 'd':
//    case 'c':
//      fce_error("ezer_qry: hromadná operace {$zmeny->op} neimplementována");
//      break;
//    }
//    // provedení UPDATE pro záznamy podle seznamu klíčů
////                                                         display($qry);
//    pdo_query_2($qry,1);
//    break;
//  default:
//    fce_error("ezer_qry: operace $op neimplementována");
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
function pdo_query_2($query,$quiet=false) {
  global $ezer_db, $curr_db, $y, $TEST, $totrace;
  $err= '';   
  $ok= 'ok';
  $myqry= strtr($query,array('"'=>"'","<="=>'&le;',"<"=>'&lt;'));
  $pdo= $ezer_db[$curr_db][0];
  try {
    if ( preg_match('/^\s*(INSERT|UPDATE)/',$query) ) {
      $res= $pdo->exec($query);
      if ($res===false) $err= $pdo->errorInfo()[2];
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
    if ( $TEST && $totrace && strpos($totrace,'M')!==false ) {
      $pretty= trim($myqry);
      if ( strpos($pretty,"\n")===false )
        $pretty= preg_replace("/(WHERE|GROUP)/","\n\t\$1",$pretty);
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
}
function select_2($expr,$table,$cond) { // ------------------------------------------------ select 2
# navrácení hodnoty jednoduchého dotazu
# pokud $expr obsahuje čárku, vrací pole hodnot, pokud $expr je hvězdička vrací objekt, 
  if ( strstr($expr,",") ) {
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
function select1_2($expr,$table,$cond) { // ---------------------------------------------- select1 2
# navrácení hodnoty jednoduchého dotazu - $expr musí vracet jednu hodnotu
  $result= '';
  $qry= "SELECT $expr AS _result_ FROM $table WHERE $cond";
  $res= pdo_query_2($qry,1);
  if ( $res ) {
    $o= pdo_fetch_object($res);
    $result= $o ? $o->_result_ : '';
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
