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
    
# ------------------------------------------ parametry přihlášky
$parm= [
  'akce:id_akce'  => 1554, 
  'akce:typ'      => 'DS', 
  'akce:org'      => 1, // 1:YS, 2:FA
  'akce:na'       => 'akci v Domě setkání', 
  'akce:popis'    => "pro rodiče s dětmi. ",
  'form:pata'     => '<i>Je možné, že se vám během vyplňování objeví nějaká chyba, '
                  . ' případně nedojde slíbené potvrzení. Omlouváme se za nepříjemnost s beta-verzí přihlášek.'
                  . '<br><br>Přihlaste se prosím v takovém případě mailem zaslaným na kancelar@setkani.org.'
                  . '<br>Připojte prosím popis závady.</i>',
  'end'           => '.'
];
init();
todo();
page();
exit;
# ------------------------------------------ stavové proměnné uložené v SESSION
# klient = ID přihlášeného 
# novi = počet uživatelem přidaných členů
# cleni = ID -> {spolu=0/1, jmeno, sex, role, narozeni}  ... ID může být  -<i> pro přidané členy
# ----------------------------------------------------------------------------- inicializace procesu
function init() {
  global $klient, $rodina, $novi, $cleni, $post, $faze, $msg, $OPTIONS;
  global $trace, $totrace, $TEST, $y, $errors, $mysql_db_track, $mysql_tracked;  // $y je obecná stavová proměnná Ezer
  # ------------------------------------------ start
  date_default_timezone_set('Europe/Prague');
  if ($TEST || isset($_GET['err']) && $_GET['err'] ) error_reporting(-1); else error_reporting(0);
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
    $_SESSION['akce']['test']= $TEST;
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
    $_SESSION['akce']['faze']= 'a';
    $_SESSION['akce']['history']= '';
    $_SESSION['akce']['klient']= 0;
    $_SESSION['akce']['rodina']= 0;
    $_SESSION['akce']['novi']= array();
    $_SESSION['akce']['cleni']= array();
    $_SESSION['akce']['POST']= $_POST;
    $index= "prihlaska.php"; $del_index= '?';
    foreach ($OPTIONS as $get=>$val) {
      $index.= "$del_index$get=$val";
      $del_index= '&';
    }
    $_SESSION['akce']['index']= $index;
    $_SESSION['akce']['server']= $ezer_server;
  }
//  $trace.= debugx($_SESSION['akce'],'$_SESSION[akce] - start');
  $faze= $_SESSION['akce']['faze'];
  $klient= $_SESSION['akce']['klient'];
  $rodina= $_SESSION['akce']['rodina'];
  $cleni= $_SESSION['akce']['cleni'];
  $novi= $_SESSION['akce']['novi'];
  foreach ($_POST as $tag=>$val) {
    if (substr($tag,0,1)=='-') { // položka z $novi
      list($id,$name)= explode('_',$tag);
      $novi[$id]->$name= $val;
    }
  }
  // zpracování hodnot
  $old_post= array_merge([],$_SESSION['akce']['POST']);
  $post= array_merge([],$_POST);
  $post= array_replace($old_post,$post);
  $msg= '';
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
  if ($TEST==2 && $faze=='a') {
    $trace.= 'ladící běh se simulovaným přihlášením na martin@smidek.eu';
    $post= ['email'=>'martin@smidek.eu'];
    $trace.= debugx($post,'$post - start');
    $trace.= '<hr>';
  }
  else {
    $trace.= debugx($_POST,'$_POST - start');
    $trace.= '<hr>';
  }
}
# --------------------------------------------------------------------------------- definice procesu
function todo() {
  global $klient, $rodina, $novi, $cleni, $post, $faze, $msg, $form, $parm;
  global $TEST, $errors;
  global $email, $pin, $note;
  $_SESSION['akce']['history'].= $faze;
  $email= $post['email'] ?? '';                   
  display("todo-begin fáze:$faze, email=$email");
  $pin= $post['pin'] ?? '';
  $note= $post['note'] ?? '';
  $regexp= "REGEXP '(^|[;,\\\\s]+)$email($|[;,\\\\s]+)'";
  $msg= '';

  switch ($faze) {
  // --------------------------------------------- otestování emailu, pokud je ok poslání PIN
  // $post obsahuje: email, zaslany_pin
  //           nově: idr, ido_a, jmeno_a, ido_b, jmeno_b
  case 'a': 
    if ($TEST<2) {
      // při simulovaném přihlášení neruš připravené $post
      clear_post_but("/email|^.$/");
    }
    $chyby= null;
    $ok= emailIsValid($email,$chyby);
    if (!$ok) 
      $chyby= $email ? "Tuto emailová adresu není možné použít:<br>$chyby" : ' ';
    else {
      // zjistíme, zda jej máme v databázi
      list($pocet,$ido,$role,$rodina,$jmena)= select(
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
      $klient= $ido;
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
        $msg= simple_mail('martin@smidek.eu', $email, "PIN ($pin) pro prihlášení na akci",
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
          WHERE id_rodina=$rodina AND o.deleted='' AND role IN ('a','b','d') 
          ORDER BY IF(id_osoba=$klient,'0',narozeni)  ");
        while ($ro && (list($ido,$role,$jmeno,$prijmeni,$sex,$narozeni)=pdo_fetch_array($ro))) {
          $cleni[$ido]= (object)array('spolu'=>$ido==$klient ? 1 : 0,
              'jmeno'=>$jmeno,'prijmeni'=>$prijmeni,'role'=>$role,
              'sex'=>$sex,'narozeni'=>$narozeni);
        }
      }
      $chyby.= "STOP";
      // jdeme dál
      $faze= 'b';
      todo();
      break;
    }
    if ($chyby) {
      $msg= zvyraznit("<p>$chyby</p>");
      $form= <<<__EOF
        <p>Abychom ověřili, že se přihlašujete právě vy, napište svůj mail, pošleme na něj přihlašovací PIN.</p>
        <input type="text" size="24" name='email' value='$email'>
        <input type="submit" value="Zaslat PIN">
        $msg
__EOF;
    }
    break;
  // --------------------------------------------------------------- porovnání zaslaného PINU
  // $post obsahuje: email, zaslany_pin
  //           nově: pin
  case 'b': 
    clear_post_but("/email|zaslany_pin|pin/");
    // zjistíme PIN zapsaný u nositele mailové adresy
    if ($pin && $pin==$post['zaslany_pin']) {
//      $post['a']= $post['b']= 'x'; // default = oba manžel=
      $faze= 'c';
      todo();
    }
    else {
      $msg= $pin ? zvyraznit("<p>Do mailu jsme poslali odlišný PIN</p>") : "<p></p>";
      $form= <<<__EOF
        <p>Na uvedený mail vám byl zaslán PIN, opište jej vedle své mailové adresy.</p>
        <input type="text" size="24" name='email' value='$email' disabled>
        <input type="text" size="4" name='pin' value="$pin">
        <input type="submit" value="ověřit PIN">
        $msg
__EOF;
    }
    $msg= '';
    break;
  // ----------------------------------------------------------------- vyžádání údajů přihlášky
  case 'c':
  // $post obsahuje: email, zaslany_pin, pin
  //           nově: a, b, note, ano, ne
    if (isset($post['nove'])) {
      clear_post_but("/email|zaslany_pin|pin/");
      $id= '-'.(count($novi)+1);
      $novi[$id]= (object)array('spolu'=>1,'jmeno'=>'','prijmeni'=>'',
          'syn'=>'','dcera'=>'','narozeni'=>'');
    }
    if (isset($post['ne'])) {
      clear_post_but("/---/");
//      $faze= 'd';
//      $msg= "Vyplňování přihlášky bylo ukončeno bez jejího odeslání. "
//          . "<br>Na akci jste se tedy nepřihlásili.";
//      todo();
//      break;
    }
    // -------------------------------------------- kontrola hodnot
    if (isset($post['ano']) || isset($post['check'])) {
//      if ($TEST) { $msg= debugx($post,'opis hodnot'); }
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
      elseif (isset($post['check'])) {
        $msg.= zvyraznit("Přihláška vypadá dobře, zašlete nám ji prosím",1);
        if (count($poznamka))
          $msg.= zvyraznit(implode('<br>',$poznamka),1);
      }
      
    // -------------------------------------------- zápis do databáze pokud není $TEST>1
      if (isset($post['ano']) && $zapsat) {
        // vytvoření pobytu
        // web_changes= 1/2 pro INSERT/UPDATE pobyt a spolu | 4/8 pro INSERT/UPDATE osoba
        $chng= array(
          (object)array('fld'=>'id_akce',    'op'=>'i','val'=>$parm['akce:id_akce']),
          (object)array('fld'=>'i0_rodina',  'op'=>'i','val'=>$rodina),
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
        // připrav závěrečnou zprávu
        if (count($errors)) {
          $msg= "Při zpracování přihlášky došlo bohužel k chybě. "
              . "<br>Přihlaste se prosím posláním mailu Markétě Zelinkové";
        }
//        else {
//          $rekapitulace= "pro účastníky: ";
//          if ($post['a']) $rekapitulace.= $post['jmeno_a'];
//          if ($post['b']) $rekapitulace.= ($post['a'] ? ' a ' : '').$post['jmeno_b'];
//          if ($post['note']) $rekapitulace.= "<br>s poznámkou {$post['note']}.";
//          $msg= "<p>Na adresu $email bude posláno potvrzení o přihlášce</p>"
//              . "$rekapitulace"
//              . "<p>Těšíme se na setkání.</p>";
//          $cc= $from= $ezer_server ? "kancelar@setkani.org" : "martin.smidek@gmail.com";
//          simple_mail($from, $post['email'], "Potvrzení přijetí přihlášky", 
//              "Potvrzujeme, že jsme přijali vaši přihlášku na {$parm['akce:na']}"
//              . "<br>$rekapitulace",$cc);
//        }
        // uzavři formulář
        clear_post_but("/---/");
        $faze= 'd';
        todo();
        break;
      }
    }
    // zobraz hodnoty z databáze pokud není ani ANO ani NE
//    $a_role=  isset($post['a']) ? 'checked' : '';
//    $b_role=  isset($post['b']) ? 'checked' : '';

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
    
//      <input type="checkbox" name='a' value="x" $a_role /><label for="a">{$post['jmeno_a']}</label>
//      <br><input type="checkbox" name='b' value="x" $b_role /><label for="b">{$post['jmeno_b']}</label>
    $form= <<<__EOF
      <p>Na zde uvedený mail vám pošleme potvrzení o přijetí přihlášky:</p>
      <input type="text" size="24" name='email' value='$email' disabled>
      <input type="text" size="4" name='pin' value="{$post['zaslany_pin']}" disabled>
      <p>Poznačte prosím, koho na akci přihlašujete:</p>
      $old_cleni
      $new_cleni
      <br><button type="submit" name="nove"><i class="fa fa-green fa-plus"></i>
        chci přihlásit další dítě</button>
      <p>Doplňte případnou poznámku pro organizátory akce:</p>
      <textarea rows="3" cols="46" name='note'>$note</textarea> 
      <br><button type="submit" name="check"><i class="fa fa-question"></i>
        zkontrolovat před odesláním</button>
      <button type="submit" name="ano"><i class="fa fa-green fa-send-o"></i> 
        odeslat přihlášku</button>
      <button type="submit" name="ne"><i class="fa fa-times fa-red"></i> 
        neposílat</button>
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
    session_unset();
    session_destroy();
    session_write_close();
    init();
    break;
  }
  $_SESSION['akce']['faze']= $faze;
  $_SESSION['akce']['cleni']= $cleni;
  $_SESSION['akce']['rodina']= $rodina;
  $_SESSION['akce']['novi']= $novi;
  $_SESSION['akce']['POST']= $post;
  display("todo-end fáze:$faze, email=$email");
end:
  return;
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
    $trace
  </body>
  </html>
__EOD;
}
# ----------------------------------------------------------------------------------- pomocné funkce
function zvyraznit($msg,$ok=0) {
  $color= $ok ? 'green' : 'red';
  return "<b style='color:$color'>$msg</b>";
}
function clear_post_but($flds_match) {
  global $klient, $rodina, $novi,$cleni, $post;
  foreach (array_keys($post) as $fld) {
    if (!preg_match($flds_match,$fld)) {
      unset($post[$fld]);
      unset($_POST[$fld]);
    }
  }
  $_SESSION['akce']['POST']= $post;
  $_SESSION['akce']['cleni']= $cleni;
  $_SESSION['akce']['novi']= $novi;
  $_SESSION['akce']['klient']= $klient;
  $_SESSION['akce']['rodina']= $rodina;
}
function simple_mail($replyto,$address,$subject,$body,$cc='') {
  global $api_gmail_user, $api_gmail_pass, $api_gmail_name, $TEST, $trace;
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
    $trace.= "<hr>MAIL: addr=$address, subj=$subject<br>$body<hr>";
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
