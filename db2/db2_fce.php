<?php # (c) 2009-2015 Martin Smidek <martin@smidek.eu>
/** ===========================================================================================> DB2 */
# ------------------------------------------------------------------------------------- db2_rod_show
# BROWSE ASK
# načtení návrhu rodiny pro Účastníci2
# vrátí objekt {n:int,next:bool,back:bool,css:string,rod:položky z rodina nebo null}
function db2_rod_show($nazev,$n) {
  $ret= (object)array('n'=>0,'next'=>0,'back'=>0,'css'=>'','rod'=>null);
  $css= array('','ezer_ys','ezer_fa','ezer_db');
  $nazev= trim($nazev);
  $rod= array(null);
  // seznamy položek pro browse_fill kopírované z ucast2_browse_ask
  $fos=   ucast2_flds("umrti,prijmeni,rodne,sex,adresa,ulice,psc,obec,stat,kontakt,telefon,nomail,email"
        . ",iniciace,uvitano,clen,obcanka,rc_xxxx,cirkev,vzdelani,titul,zamest,zajmy,jazyk,dieta"
        . ",aktivita,note,_kmen");
  $fspo=  ucast2_flds("id_spolu,_barva,s_role,dite_kat,poznamka,pecovane,pfunkce,pece_jm,pece_id,o_umi");
  // načtení rodin
  $qr= mysql_qry("SELECT id_rodina AS key_rodina,ulice AS r_ulice,psc AS r_psc,obec AS r_obec,
      telefony AS r_telefony,emaily AS r_emaily,spz AS r_spz,datsvatba,access AS r_access
    FROM rodina WHERE deleted='' AND nazev='$nazev'");
  while ( $qr && ($r= mysql_fetch_object($qr)) ) {
    $r->r_datsvatba= sql_date1($r->datsvatba);
    $rod[]= $r;
  }
//                                                         debug($rod,count($rod));
  // diskuse
  $ret->last= count($rod)-1;
  if ( isset($rod[$n]) ) {
    $idr= $rod[$n]->id_rodina;
    $ret->n= $n;
    $ret->rod= $rod[$n];
    $ret->back= $n>1 ?1:0;
    $ret->next= $n<count($rod)-1 ?1:0;
    $ret->css= $css[$ret->rod->r_access];

    # ==> .. duplicity
    $rr= ucast2_chain_rod($idr);
    $_dups= $rr->dup;
    $_keys= $rr->keys;

    // seznam členů rodiny
    $cleni= $del= '';
    $idr= $ret->rod->key_rodina;
    $qc= mysql_qry("
      SELECT id_tvori,role,o.*
      FROM osoba AS o
      JOIN tvori AS t USING(id_osoba)
      WHERE t.id_rodina=$idr
      ORDER BY role,narozeni
    ");
    while ( $qc && ($o= mysql_fetch_object($qc)) ) {
      $ido= $o->id_osoba;

      # ==> .. duplicita členů
      $rc= ucast2_chain_oso($ido);
      $dup= $rc->dup;
      $keys= $ret->keys;
      if ( $dup ) {
        $_dups.= $dup;
        $_keys.= ";$ido,{$s->_keys}";
      }
      $vek= $o->narozeni!='0000-00-00' ? roku_k($o->narozeni) : '?'; // výpočet věku
      $cleni.= "$del$ido~$keys~{$o->access}~{$o->jmeno}~$dup~$vek~{$o->id_tvori}~$idr~{$o->role}";
      $cleni.= "~~" . sql_date1($o->narozeni);
      if ( !$o->adresa ) {
        $o->ulice= "®".$rod[$n]->r_ulice;
        $o->psc=   "®".$rod[$n]->r_psc;
        $o->obec=  "®".$rod[$n]->r_obec;
      }
      if ( !$o->kontakt ) {
        $o->email=   "®".$rod[$n]->r_emaily;
        $o->telefon= "®".$rod[$n]->r_telefony;
      }
      # informace z osoba
      foreach($fos as $f=>$filler) {
        $cleni.= "~{$o->$f}";
      }
      # informace ze spolu
      $o->_spolu= 0;
      foreach($fspo as $f=>$filler) {
        $cleni.= "~{$o->$f}";
      }
      $cleni.= "~";
    }
    $ret->cleni= $cleni;
    $ret->_docs.= count_chars($_dups,3);
    $ret->keys_rodina= $_keys;
  }
//                                                         debug($ret,'db2_rod_show');
  return $ret;
}
# ------------------------------------------------------------------------------------- db2_oso_show
# vrátí objekt {n:int,next:bool,back:bool,css:string,oso:položky z osoba nebo null}
function db2_oso_show($prijmeni,$jmeno,$n) {
  $ret= (object)array('n'=>0,'next'=>0,'back'=>0,'css'=>'','oso'=>null);
  $css= array('','ezer_ys','ezer_fa','ezer_db');
  $prijmeni= trim($prijmeni);
  $jmeno= trim($jmeno);
  $oso= array(null);
  // načtení rodin
  $qr= mysql_qry("SELECT id_osoba AS key_osoba,access,rodne,sex,narozeni,umrti,
      adresa,ulice,psc,obec,kontakt,telefon,email
    FROM osoba WHERE deleted='' AND prijmeni='$prijmeni' AND jmeno='$jmeno' ");
  while ( $qr && ($r= mysql_fetch_object($qr)) ) {
    $r->r_datsvatba= sql_date1($r->datsvatba);
    $oso[]= $r;
  }
//                                                         debug($oso,count($oso));
  // diskuse
  $ret->last= count($oso)-1;
  if ( isset($oso[$n]) ) {
    $ret->n= $n;
    $ret->oso= $oso[$n];
    $ret->back= $n>1 ?1:0;
    $ret->next= $n<count($oso)-1 ?1:0;
    $ret->css= $css[$ret->oso->access];
  }
//                                                         debug($ret,'db2_oso_show');
  return $ret;
}
/** =========================================================================================> AKCE2 */
# --------------------------------------------------------------------------------------- akce2_mapa
# získání seznamu souřadnic bydlišť účastníků akce
function akce2_mapa($akce) {  trace();
  // dotaz
  $psc= $obec= array();
  $qo=  "
    SELECT prijmeni,adresa,psc,obec,
      (SELECT MIN(CONCAT(role,psc,'x',obec))
       FROM tvori AS ot JOIN rodina AS r USING (id_rodina)
       WHERE ot.id_osoba=o.id_osoba
      ) AS r_psc
    FROM pobyt
    JOIN spolu USING (id_pobyt)
    JOIN osoba AS o USING (id_osoba)
    WHERE id_akce='$akce'
    GROUP BY id_osoba
    ";
  // najdeme použitá PSČ
  $ro= mysql_qry($qo);
  while ( $ro && ($o= mysql_fetch_object($ro)) ) {
    $p= $o->adresa ? $o->psc : substr($o->r_psc,1,5);
    $m= $o->adresa ? $o->obec : substr($o->r_psc,7);
    $psc[$p].= "$o->prijmeni ";
    $obec[$p]= $obec[$p] ?: $m;
  }
//                                         debug($psc);
  return mapa2_psc($psc,$obec); // vrací (object)array('mark'=>$marks,'n'=>$n,'err'=>$err);
}
# --------------------------------------------------------------------------------------- akce2_info
# rozšířené informace o akci
# text=1 v textové verzi, text=0 jako objekt s počty
function akce2_info($id_akce,$text=1) { // trace();
  $html= '';
  $info= (object)array('muzi'=>0,'zeny'=>0,'deti'=>0,'peco'=>0,'rodi'=>0,'skup'=>0);
  $zpusoby= map_cis('ms_akce_platba','zkratka'); // způsob => částka
  $platby= array();
  $celkem= 0;
  $aviz= 0;
  if ( $id_akce ) {
    $ucasti= $rodiny= $dosp= $muzi= $zeny= $deti= $pecounu= $err= $err2= 0;
    $odhlaseni= $neprijeli= $nahradnici= $nahradnici_osoby= 0;
    $akce= $chybi_nar= $chybi_sex= '';
    $qry= "SELECT nazev, datum_od, datum_do, now() as _ted,i0_rodina,funkce,
             COUNT(id_spolu) AS _clenu,
             SUM(IF(ROUND(DATEDIFF(a.datum_od,o.narozeni)/365.2425,1)<18,1,0)) AS _deti,
             SUM(IF(ROUND(DATEDIFF(a.datum_od,o.narozeni)/365.2425,1)>=18 AND sex=1,1,0)) AS _muzu,
             SUM(IF(ROUND(DATEDIFF(a.datum_od,o.narozeni)/365.2425,1)>=18 AND sex=2,1,0)) AS _zen,
             SUM(IF(o.narozeni='0000-00-00',1,0)) AS _err,
             GROUP_CONCAT(IF(o.narozeni='0000-00-00',CONCAT(', ',jmeno,' ',prijmeni),'') SEPARATOR '') AS _kdo,
             SUM(IF(o.sex NOT IN (1,2),1,0)) AS _err2,
             GROUP_CONCAT(IF(o.sex NOT IN (1,2),CONCAT(', ',jmeno,' ',prijmeni),'') SEPARATOR '') AS _kdo2,
             avizo,platba,datplatby,zpusobplat
           FROM akce AS a
           JOIN pobyt AS p ON a.id_duakce=p.id_akce
           JOIN spolu AS s ON p.id_pobyt=s.id_pobyt
           JOIN osoba AS o ON s.id_osoba=o.id_osoba
           -- LEFT JOIN tvori AS t ON t.id_osoba=o.id_osoba
           WHERE id_duakce='$id_akce'
           GROUP BY p.id_pobyt";
    $res= mysql_qry($qry);
    while ( $res && $p= mysql_fetch_object($res) ) {
      $fce= $p->funkce;
      // záznam plateb
      if ( $p->platba ) {
        $celkem+= $p->platba;
        $platby[$p->zpusobplat]+= $p->platba;
      }
      if ( $p->avizo ) {
        $aviz++;
      }
      // diskuse funkce=odhlášen/14 a funkce=nepřijel/10 a funkce=náhradník/9
      if ( in_array($fce,array(14,10,9) ) ) {
        $odhlaseni+= $fce==14 ? 1 : 0;
        $neprijeli+= $fce==10 ? 1 : 0;
        $nahradnici+= $fce==9 ? 1 : 0;
        $nahradnici_osoby+= $fce==9 ? $p->_clenu : 0;
      }
      else {
        // údaje účastníků jednoho pobytu
        $ucasti++;
        $muzi+= $p->_muzu;
        $zeny+= $p->_zen;
        $deti+= $p->_deti;
        $err+= $p->_err;
        $err2+= $p->_err2;
        $rodiny+= i0_rodina && $p->_clenu>1 ? 1 : 0;
        $chybi_nar.= $p->_kdo;
        $chybi_sex.= $p->_kdo2;
        if ( $p->funkce==99 )
          $pecounu+= $p->_clenu;
      }
      // údaje akce
      $akce= $p->nazev;
      $cas1= $p->_ted>$p->datum_od ? "byla" : "bude";
      $cas2= $p->_ted>$p->datum_od ? "Akce se zúčastnilo" : "Na akci je přihlášeno";
      $od= sql_date1($p->datum_od);
      $do= sql_date1($p->datum_do);
      $dne= $p->datum_od==$p->datum_do ? "dne $od" : "ve dnech $od do $do";
    }
    if ( $chybi_nar ) $chybi_nar= substr($chybi_nar,2);
    if ( $chybi_sex ) $chybi_sex= substr($chybi_sex,2);
    $dosp+= $muzi + $zeny;
    $skupin= $ucasti - ( $pecounu ? 1 : 0 );
    if ( $text ) {
      // čeština
      $_skupin=    je_1_2_5($skupin,"skupina,skupiny,skupin");
      $_pecounu=   je_1_2_5($pecounu,"pečoun,pečouni,pečounů");
      $_dospelych= je_1_2_5($dosp,"dospělý,dospělí,dospělých");
      $_muzu=      je_1_2_5($muzi,"muž,muži,mužů");
      $_zen=       je_1_2_5($zeny,"žena,ženy,žen");
      $_deti=      je_1_2_5($deti,"dítě,děti,dětí");
      $_osob=      je_1_2_5($dosp+$deti,"osoba,osoby,osob");
      $_err=       je_1_2_5($err,"osoby,osob,osob");
      $_err2=      je_1_2_5($err2,"osoby,osob,osob");
      $_rodiny=    je_1_2_5($rodiny,"rodina,rodiny,rodin");
      $_pobyt_o=   je_1_2_5($odhlaseni,"pobyt,pobyty,pobytů");
      $_pobyt_x=   je_1_2_5($neprijeli,"pobyt,pobyty,pobytů");
      $_pobyt_n=   je_1_2_5($nahradnici,"přihláška,přihlášky,přihlášek");
      $_pobyt_no=  je_1_2_5($nahradnici_osoby,"osoba,osoby,osob");
      $_aviz=      je_1_2_5($aviz,"avízo,avíza,avíz");
      // html
      $html= $dosp+$deti>0
       ? $html.= "<h3 style='margin:0px 0px 3px 0px;'>$akce</h3>akce $cas1 $dne<br><hr>$cas2"
       . ($skupin ? " $_skupin účastníků"
           .($rodiny ? ($rodiny==$ucasti ? " (všechny jako rodiny)" : " (z toho $_rodiny)") :''):'')
       . ($pecounu ? " ".($skupin?" a ":'')."$_pecounu" : '')
       . ",<br><br>tj. $_dospelych ($_muzu, $_zen) a $_deti,"
       . "<br><b>celkem $_osob</b>"
       : "Akce byla vložena do databáze ale nemá zatím žádné účastníky";
      if ( $odhlaseni + $neprijeli + $nahradnici > 0 ) {
        $html.= "<br><hr>";
        $msg= array();
        if ( $odhlaseni ) $msg[]= "odhlášeno: $_pobyt_o (bez storna)";
        if ( $neprijeli ) $msg[]= "zrušeno: $_pobyt_x (nepřijeli, aplikovat storno)";
        if ( $nahradnici ) $msg[]= "náhradníci: $_pobyt_n, celkem $_pobyt_no";
        $html.= implode('<br>',$msg);
      }
      if ( $err + $err2 > 0 ) {
        $html.= "<br><hr>POZOR: ";
        $html.= $err>0  ? "<br>u $_err chybí datum narození: <i>$chybi_nar</i>" : '';
        $html.= $err2>0 ? "<br>u $_err2 chybí údaj muž/žena: <i>$chybi_sex</i>" : '';
        $html.= "<br>(kvůli chybějícím údajům mohou být počty divné)";
      }
      $html.= $deti ? "<hr>Poznámka: jako děti se počítají osoby, které v době zahájení akce nemají 18 let" : '';
    }
    else {
      $info->muzi= $muzi;
      $info->zeny= $zeny;
      $info->deti= $deti;
      $info->peco= $pecounu;
      $info->rodi= $rodiny;
      $info->skup= $skupin;
    }
    // zobrazení přehledu plateb
    if ( $celkem ) {
      $html.= "<h3 style='margin-bottom:3px;'>Přehled plateb za akci</h3><table>";
      foreach ($zpusoby as $i=>$zpusob) {
        if ( $platby[$i] )
          $html.= "<tr><td>$zpusob</td><td align='right'>{$platby[$i]}</td></tr>";
      }
      $st= "style='border-top:1px solid black'";
      $av= $aviz ? "<td $st> a $_aviz platby</td>" : '';
      $html.= "<tr><td $st>CELKEM</td><td align='right' $st><b>$celkem</b></td>$av</tr>";
      $html.= "</table>";
    }
  }
  else {
    $html= "Tato akce ještě nebyla vložena do databáze
            <br><br>Vložení se provádí dvojklikem
            na řádek s akcí";
  }
  return $text ? $html : $info;
}
# --------------------------------------------------------------------------- je_1_2_5
# výběr správného tvaru slova podle množství a tabulky tvarů pro 1,2-4,více jak 5
# např. je_1_2_5($dosp,"dospělý,dospělí,dospělých")
function je_1_2_5($kolik,$tvary) {
  list($tvar1,$tvar2,$tvar5)= explode(',',$tvary);
  return $kolik>4 ? "$kolik $tvar5" : (
         $kolik>1 ? "$kolik $tvar2" : (
         $kolik>0 ? "1 $tvar1"      : "0 $tvar5"));
}
# --------------------------------------------------------------------------------------- akce2_id2a
# vrácení hodnot akce
function akce2_id2a($id_akce) {  //trace();
  $a= (object)array('title'=>'?','cenik'=>0,'cena'=>0,'soubeh'=>0,'hlavni'=>0,'soubezna'=>0);
  list($a->title,$a->rok,$a->cenik,$a->cena,$a->hlavni,$a->soubezna,$a->org)=
    select("a.nazev,YEAR(a.datum_od),a.ma_cenik,a.cena,a.id_hlavni,IFNULL(s.id_duakce,0),a.access",
      "akce AS a
       LEFT JOIN akce AS s ON s.id_hlavni=a.id_duakce",
      "a.id_duakce=$id_akce");
  # diskuse souběhu: 0=normální akce, 1=hlavní akce, 2=souběžná akce
  $a->soubeh= $a->soubezna ? 1 : ( $a->hlavni ? 2 : 0);
  $a->rok= $a->rok ?: date('Y');
  $a->title.= ", {$a->rok}";
//                                                                 debug($a,$id_akce);
  return $a;
}
# ------------------------------------------------------------------------------ akce2_soubeh_nastav
# nastavení akce jako souběžné s jinou (která musí mít stejné datumy a místo konání)
function akce2_soubeh_nastav($id_akce,$nastavit=1) {  trace();
  $msg= "";
  if ( $nastavit ) {
    list($hlavni,$nazev,$nic,$pocet)=
      select("h.id_duakce,h.nazev,s.id_hlavni,COUNT(*)","akce AS h JOIN akce AS s ON s.misto=h.misto",
        "s.id_duakce=$id_akce AND s.id_duakce!=h.id_duakce AND s.datum_od=h.datum_od");
    if ( !$pocet ) {
      $msg= "k souběžné akci musí napřed existovat hlavní akce se stejným začátkem a místem konání";
      goto end;
    }
    elseif ( $pocet>1 ) {
      $msg= "Souběžná akce již existuje (tzn. již jsou $počet akce se stejným začátkem a místem konání)";
      goto end;
    }
    elseif ( $nic ) {
      $msg= "Tato akce již je souběžná k hlavní akci (se stejným začátkem a místem konání)";
      goto end;
    }
    // vše je v pořádku $hlavni může být hlavní akcí k $id_akce
    $ok= query("UPDATE akce SET id_hlavni=$hlavni WHERE id_duakce=$id_akce");
    $msg.= $ok ? "akce byla přiřazena k hlavní akci '$nazev'" : "CHYBA!";
  }
  else {
    // zrušit nastavení
    $ok= query("UPDATE akce SET id_hlavni=0 WHERE id_duakce=$id_akce");
    $msg.= $ok ? "akce je nadále vedena jako samostatná" : "CHYBA!";
  }
end:
  return $msg;
}
# ----------------------------------------------------------------------------- akce2_delete_confirm
# dotazy před zrušením akce
function akce2_delete_confirm($id_akce) {  trace();
  $ret= (object)array('zrusit'=>0,'ucastnici'=>'','platby'=>'');
  // fakt zrušit?
  list($nazev,$misto,$datum)=
    select("nazev,misto,DATE_FORMAT(datum_od,'%e.%m.%Y')",'akce',"id_duakce=$id_akce");
  $ret->zrusit= "Opravdu smazat akci '$nazev, $misto' začínající $datum?";
  if ( !$nazev ) goto end;
  // má účastníky
  $ucastnici= select('COUNT(*)','pobyt',"id_akce=$id_akce");
  $ret->ucastnici= $ucastnici
    ? "Tato akce má již zapsáno $ucastnici účastníků. Má se jejich účast zrušit a potom smazat akci?"
    : '';
  // jsou evidovány platby
  $platby= select('COUNT(*)','platba',"id_duakce=$id_akce");
  $ret->platby= $platby
    ? "S touto akcí jsou již svázány $platby platby. Akci nelze smazat."
    : '';
end:
  return $ret;
}
# ------------------------------------------------------------------------------------- akce2_delete
# zrušení akce
function akce2_delete($id_akce,$ret) {  trace();
  list($nazev)= select("nazev",'akce',"id_duakce=$id_akce");
  if ( $ret->ucastnici ) {
    // napřed zrušit účasti na akci
    query("DELETE FROM spolu USING spolu JOIN pobyt USING(id_pobyt) WHERE id_akce=$id_akce");
    $s= mysql_affected_rows();
    query("DELETE FROM pobyt WHERE id_akce=$id_akce");
    $p= mysql_affected_rows();
  }
  query("DELETE FROM akce WHERE id_duakce=$id_akce");
  $a= mysql_affected_rows();
  $msg= $a
    ? "Akce '$nazev' byla smazána" . ( $p+$s ? ", včetně $p účastí $s účastníků" : '.')
    : "CHYBA: akce '$nazev' nebyla smazána";
end:
  return $msg;
}
# -------------------------------------------------------------------------------------- akce2_zmeny
# vrácení klíčů pobyt u kterých došlo ke změně po daném datu a čase
function akce2_zmeny($id_akce,$h) {  trace();
  $ret= (object)array('errs'=>'','pobyt'=>'','chngs'=>array(),'osoby'=>array());
  // přebrání parametrů
  $time= date_sub(date_create(), date_interval_create_from_date_string("$h hours"));
  $ret->kdy= date_format($time, 'Y-m-d H:i');
  // získání sledovaných klíčů tabulek spolu, osoba, tvori, rodina
  $pobyt= $osoba= $osoby= $rodina= $spolu= $spolu_osoba= $tvori= array();
  $rp= mysql_qry("
    SELECT id_pobyt,id_spolu,o.id_osoba,id_tvori,id_rodina
    FROM pobyt AS p
    JOIN spolu AS s USING(id_pobyt)
    JOIN osoba AS o ON s.id_osoba=o.id_osoba
    LEFT JOIN tvori AS t ON t.id_osoba=o.id_osoba
    LEFT JOIN rodina AS r USING(id_rodina)
    WHERE id_akce=$id_akce
  ");
  while ( $rp && ($p= mysql_fetch_object($rp)) ) {
    $pid= $p->id_pobyt;
    $spolu[$p->id_spolu]= $pid;
    $spolu_osoba[$p->id_spolu]= $p->id_osoba;
    $osoba[$p->id_osoba]= $pid;
    if ( $p->id_tvori ) $tvori[$p->id_tvori]= $pid;
    if ( $p->id_rodina ) $rodina[$p->id_rodina]= $pid;
  }
//                                                         debug($rodina);
  // projití _track
  $n= 0;
  $rt= mysql_qry("SELECT kde,klic,fld,kdo,kdy FROM _track WHERE kdy>'{$ret->kdy}'");
  while ( $rt && ($t= mysql_fetch_object($rt)) ) {
    $k= $t->klic;
    $pid= 0;
    switch ( $t->kde ) {
    case 'pobyt':  $pid= $k; break;
    case 'spolu':  if ( $pid= $spolu[$k] ) $osoby[$spolu_osoba[$k]]= 1; break;
    case 'osoba':  if ( $pid= $osoba[$k] ) $osoby[$k]= 1; break;
    case 'tvori':  $pid= $tvori[$k]; break;
    case 'rodina': $pid= $rodina[$k]; break;
    }
    if ( $pid ) {
      if ( !in_array($pid,$pobyt) )
        $pobyt[]= $pid;
      // vygenerování změnového objektu pro obarvení změn [[table,key,field],...]
      $kdy= sql_time1($t->kdy);
      $ret->chngs[]= array($t->kde,$k,$t->fld,$t->kdo,$kdy);
      $n++;
    }
  }
  // shrnutí změn
  $ret->osoby= implode(',',array_keys($osoby));
  $ret->pobyt= implode(',',$pobyt);
                                        debug($ret,"$n změn po ... sql_time={$ret->kdy}");
  return $ret;
}
# ------------------------------------------------------------------------------- akce_test_dite_kat
# testuje, zda je kategorie dítěte v souladu s rozmezím věku v číselníku
# narozeni=d.m.Y
function akce_test_dite_kat($kat,$narozeni,$id_akce) {  trace();
  $ret= (object)array('ok'=>0,'vek'=>0.0);
  $od_do= select1("ikona","_cis","druh='ms_akce_dite_kat' AND data=$kat");
  list($od,$do)= explode('-',$od_do);
  $akce_od= select1("datum_od","akce","id_duakce=$id_akce");
  $narozeni= sql_date1($narozeni,1);
  $date1 = new DateTime($narozeni);
  $date2 = new DateTime($akce_od);
  $diff= $date2->diff($date1,1);
  $x= $diff->y . " years, " . $diff->m." months, ".$diff->d." days ";
  $roku= $diff->y;
  $vek= $diff->y+($diff->m+$diff->d/30)/12;
//   $d= array($diff->y,$diff->m,$diff->d,$diff->days);
//                                               debug($d,"$vek: $x, narozen:$narozeni, akce:$akce_od");
  $ret->vek= round($vek,1);
  $ret->ok= $vek>=$od && $vek<$do ? 1 : 0;
  return $ret;
}
# ------------------------------------------------------------------------------- akce2_strava_denne
# vrácení výjimek z providelné stravy jako pole
function akce2_strava_denne($od,$dnu,$cela,$polo) {  #trace('');
  $dny= array('neděle','pondělí','úterý','středa','čtvrtek','pátek','sobota');
  $strava= array();
  $den0= sql2stamp($od);
  for ($i= 0; $i<3*$dnu; $i+= 3) {
    $t= $den0+($i/3)*60*60*24;
    $den= date('d.m.Y ',$t);
    $den.= $dny[date('w',$t)];
    $strava[]= (object)array(
      'den'=> $den,
      'sc' => substr($cela,$i+0,1),
      'sp' => substr($polo,$i+0,1),
      'oc' => substr($cela,$i+1,1),
      'op' => substr($polo,$i+1,1),
      'vc' => substr($cela,$i+2,1),
      'vp' => substr($polo,$i+2,1)
    );
  }
//                                                 debug($strava,"akce_strava_denne($od,$dnu,$cela,$polo) $den0");
  return $strava;
}
# -------------------------------------------------------------------------- akce2_strava_denne_save
# zapsání výjimek z providelné stravy - pokud není výjimka zapíše prázdný string
#   $prvni - kód první stravy na akci
function akce2_strava_denne_save($id_pobyt,$dnu,$cela,$cela_def,$cela_str,$polo,$polo_def,$polo_str,$prvni) {  #trace('');
  $cela_ruzna= $polo_ruzna= 0;
  $i0= $prvni=='s' ? 0 : ($prvni=='o' ? 1 : ($prvni=='v' ? 2 : 2));
  for ($i= $i0; $i<3*$dnu-1; $i++) {
    if ( substr($cela,$i,1)!=$cela_def ) $cela_ruzna= 1;
    if ( substr($polo,$i,1)!=$polo_def ) $polo_ruzna= 1;
  }
  if ( !$cela_ruzna ) $cela= '';
  if ( !$polo_ruzna ) $polo= '';
  // příprava update
  $set= '';
  if ( ";$cela"!=";$cela_str" ) $set.= "cstrava_cel='$cela'";           // ; jako ochrana pro pochopení jako čísla
  if ( ";$polo"!=";$polo_str" ) $set.= ($set?',':'')."cstrava_pol='$polo'";
  if ( $set ) {
    $qry= "UPDATE pobyt SET $set WHERE id_pobyt=$id_pobyt";
    $res= mysql_qry($qry);
  }
//                                                 display("akce_strava_denne_save(($id_pobyt,$dnu,$cela,$cela_def,$polo,$polo_def) $set");
  return 1;
}
# ======================================================================================> . intranet
# -------------------------------------------------------------------------------- akce2_roku_update
# přečtení listu $rok (>2010) z tabulky ciselnik_akci a zapsání dat do tabulky
# načítají se jen řádky ve kterých typ='a'
# funguje pro "nové tabulky google" - od dubna 2015
function akce2_roku_update($rok) {  trace();
  global $json;
  $key= "1RKnvU7EJG7YtBDjnSpQfwg3kjOCBEV_w8bMlJcdV8Nc";         // ciselnik_akci
  $prefix= "google.visualization.Query.setResponse(";           // přefix json objektu
  $sheet= $rok>2010 ? $rok-1997 : ($rok==2010 ? 10 : -1);
  $x= file_get_contents("https://docs.google.com/spreadsheets/d/$key/gviz/tq?tqx=out:json&gid=$sheet");
  $xi= strpos($x,$prefix);
  $xl= strlen($prefix);
//                                         display("xi=$xi,$xl");
  $x= substr(substr($x,$xi+$xl),0,-2);
//                                         display($x);
  $tab= $json->decode($x)->table;
//                                         debug($tab,$sheet);
  // projdeme získaná data
  $n= 0;
  if ( $tab ) {
    // zrušení daného roku v GAKCE
    $qry= "DELETE FROM g_akce WHERE g_rok=$rok";
    $res= mysql_qry($qry);
    // výběr a-záznamů a zápis do G_AKCE
    $values= ''; $del= '';
    foreach ($tab->rows as $crow) {
      $row= $crow->c;
      $kat= $row[0]->v;
      if ( strpos(' au',$kat) ) {
//                                                         debug($row,$row[2]->v);
        $n++;
        $kod= $row[1]->f;
        $id= 1000*rok+$kod;
        $nazev= mysql_real_escape_string($row[2]->v);
                                                        display("$kod:$nazev");
        // data akce - jen je-li syntax ok
        $od= $do= '';
        $x= $row[3]->f;
        if ( preg_match("/\d+\.\d+\.\d+/",$x) )
          $od= sql_date($x,1);
        $x= $row[4]->f;
        if ( preg_match("/\d+\.\d+\.\d+/",$x) )
          $do= sql_date($x,1);
        $uc=  $row[5]->f;
        $typ= $row[6]->f;
        $kap= $row[7]->f;
        $values.= "$del($id,$rok,'$kod',\"$nazev\",'$od','$do','$uc','$typ','$kap','$kat')";
        $del= ',';
      }
    }
    $qry= "INSERT INTO g_akce (id_gakce,g_rok,g_kod,g_nazev,g_od,g_do,g_ucast,g_typ,g_kap,g_kat)
           VALUES $values";
    $res= mysql_qry($qry);
  }
  // konec
end:
  return $n;
}
# ====================================================================================> . ceník akce
# ------------------------------------------------------------------------------- akce2_select_cenik
# seznam akcí s ceníkem pro select
function akce2_select_cenik($id_akce) {  trace();
  $max_nazev= 20;
  mb_internal_encoding('UTF-8');
  $options= 'neměnit:0';
  if ( $id_akce ) {
    $qa= "SELECT id_duakce, nazev, YEAR(datum_od) AS _rok FROM akce
          WHERE id_duakce!=$id_akce AND ma_cenik>0 ORDER BY datum_od DESC";
    $ra= mysql_qry($qa);
    while ($ra && $a= mysql_fetch_object($ra) ) {
      $nazev= strtr($a->nazev,array(','=>' ',':'=>' '));
      if ( mb_strlen($nazev) >= $max_nazev )
        $nazev= mb_substr($nazev,0,$max_nazev-3).'...';
      $nazev.= "/{$a->_rok}";
      $options.= ",$nazev:{$a->id_duakce}";
    }
  }
  return $options;
}
# ------------------------------------------------------------------------------- akce2_change_cenik
# změnit ceník akce za vybraný
function akce2_change_cenik($id_akce,$id_akce_vzor) {  trace();
  $err= '';
  if ( !$id_akce || !$id_akce_vzor ) { $err= "chybné použití změny - ceník nezměněn"; goto end; }
  // výmaz položek v ceníku
  $qa= "DELETE FROM cenik WHERE id_akce=$id_akce";
  $ra= mysql_qry($qa);
  if ( !$ra ) { $err= "chyba MySQL"; goto end; }
  // kopie ze vzoru
  $qa= "INSERT INTO cenik (id_akce,poradi,polozka,za,typ,od,do,cena,dph)
          SELECT $id_akce,poradi,polozka,za,typ,od,do,cena,dph
          FROM cenik WHERE id_akce=$id_akce_vzor";
  $ra= mysql_qry($qa);
  if ( !$ra ) { $err= "chyba MySQL"; goto end; }
end:
  return $err ? $err : "hotovo, nezapomeňte jej upravit (ceny,DPH)";
}
# --------------------------------------------------------------------------------- akce2_platby_xls
function akce2_platby_xls($id_akce) {  trace();
  $ret= (object)array('ok'=>0,'xhref'=>'','msg'=>'');
  $test= 0;
  $name= "cenik_$id_akce";
  list($nazev,$rok)= select("nazev,YEAR(datum_do)","akce","id_duakce=$id_akce");
  // vytvoření sešitu s ceníkem
  $n= 1;
  $xls= <<<__XLS
  |open  $name|\n\n|sheet cenik|columns A=40
    |A$n Ceník pro akci $nazev $rok::bold size=16\n
__XLS;
  // výpis ceníku podle typu ubytování
  $tit= "|B* cena|C* DPH|A*:C* bcolor=ffcccccc bold\n";
  $qa= "SELECT * FROM cenik WHERE id_akce=$id_akce ORDER BY poradi";
  $ra= mysql_qry($qa);
  if ( !$ra || !mysql_num_rows($ra) ) {
    $ret->msg.= "akce {$pobyt->id_akce} nemá cenový vzorec";
    goto end;
  }
  while ( $ra && ($a= mysql_fetch_object($ra)) ) {
    $pol= $a->polozka;
    $bc= '';
    if ( substr($pol,0,2)=='--' ) {
      $n+= 2;
      $xls.= "|A$n ".substr($pol,2).str_replace('*',$n,$tit);
    }
    elseif ( substr($pol,0,1)=='-' ) {
      $n+= 3;
      $xls.= "|A$n ".substr($pol,1)."|A$n:C$n bcolor=ffaaaaff bold size=14\n";
    }
    else {
      $n++;
      $za= $a->za;
      $cena= $a->cena;
      $dph= $a->dph;
      $xls.= "|A$n $pol ($za)|B$n $cena|C$n $dph\n";
    };
  }
  // konec
  $xls.= "|close|";
  $ret->msg= Excel5($xls,!$test);
  if ( $ret->msg ) goto end;
  $ret->ok= 1;
  $ret->xhref= " zde lze stáhnout <a href='docs/$name.xls' target='xls'>$name.xls</a>.";
//                                                         if ($test) display(($xls));
end:
//                                                         debug($ret);
  return $ret;
}
# ------------------------------------------------------------------------------ akce2_pobyt_default
# definice položek v POBYT podle počtu a věku účastníků - viz akce_vzorec_soubeh
# 150216 při vyplnění dite_kat budou stravy počítány podle _cis/ms_akce_dite_kat.barva
# 130522 údaje za chůvu budou připsány na rodinu chovaného dítěte
# 130524 oživena položka SVP
function akce2_pobyt_default($id_pobyt,$zapsat=0) {  trace();
  $ms_akce_dite_kat= map_cis('ms_akce_dite_kat','barva'); // {L|-},{c|p} = lůžko/bez, celá/poloviční
  // projítí společníků v pobytu
  $dosp= $deti= $koje= $noci= $sleva= $fce= $svp= 0;
  $luzka= $bez= $cele= $polo= 0;
  $msg= '';
  $qo= "SELECT o.jmeno,o.narozeni,a.datum_od,DATEDIFF(datum_do,datum_od) AS _noci,p.funkce,
         s.pecovane,s.s_role,s.dite_kat,(SELECT CONCAT(osoba.id_osoba,',',pobyt.id_pobyt)
          FROM pobyt
          JOIN spolu ON spolu.id_pobyt=pobyt.id_pobyt
          JOIN osoba ON osoba.id_osoba=spolu.id_osoba
          WHERE pobyt.id_akce=a.id_duakce AND spolu.pecovane=o.id_osoba) AS _chuva
        FROM spolu AS s JOIN osoba AS o USING(id_osoba) JOIN pobyt AS p USING(id_pobyt)
        JOIN akce AS a ON p.id_akce=a.id_duakce
        WHERE id_pobyt=$id_pobyt";
  $ro= mysql_qry($qo);
  while ( $ro && ($o= mysql_fetch_object($ro)) ) {
    if ( $o->_chuva ) {
      $dosp++; $luzka++; $cela++;       // platíme za chůvu vlastního dítěte
      $svp++;                           // ale ne za obecného pečouna
    }
    if ( $o->pecovane) {                // za dítě-chůvu platí rodič pečovaného dítěte
      $dosp--; $luzka--; $cela--;
    }
    $noci= $o->_noci;
    $fce= $o->funkce;
    $vek= narozeni2roky(sql2stamp($o->narozeni),sql2stamp($o->datum_od));
    $msg.= " {$o->jmeno}:$vek";
    if ( in_array($o->s_role,array(2,3,4)) && $o->dite_kat ) {
      // pokud je definována kategorie podle _cis/ms_akce_dite_kat ALE dítě není pečoun
      $dite++;
      list($spani,$strava)= explode(',',$ms_akce_dite_kat[$o->dite_kat]);
      // lůžka
      if ( $spani=='L' )      $luzka++;
      elseif ( $spani=='-' )  $bez++;
      else $err+= "chybná kategorie dítěte";
      // strava
      if ( $strava=='c' )     $cela++;
      elseif ( $strava=='p' ) $polo++;
      else $err+= "chybná kategorie dítěte";
    }
    else {
      // jinak se orientujeme podle věkových hranic: 0-3-10-18
      if     ( $vek<3  ) { $koje++;  $bez++; }                  // dítě bez lůžka a stravy
      elseif ( $vek<10 ) { $deti++;  $luzka++; $polo++; }       // dítě lůžko poloviční
      elseif ( $vek<18 ) { $deti++;  $luzka++; $cela++; }       // dítě lůžko celá
      else               { $dosp++;  $luzka++; $cela++; }       // dospělý lůžko celá
    }
  }
  // zápis do pobytu
  if ( $zapsat ) {
//     query("UPDATE pobyt SET luzka=".($dosp+$deti).",kocarek=$koje,strava_cel=$dosp,strava_pol=$deti,
//              pocetdnu=$noci,svp=$svp WHERE id_pobyt=$id_pobyt");
    query("UPDATE pobyt SET luzka=$luzka,kocarek=$bez,strava_cel=$cela,strava_pol=$polo,
             pocetdnu=$noci,svp=$svp WHERE id_pobyt=$id_pobyt");
  }
  //$ret= (object)array('luzka'=>$dosp+$deti,'kocarek'=>$koje,'pocetdnu'=>$noci,'svp'=>$svp,
  //                    'strava_cel'=>$dosp,'strava_pol'=>$deti,'vzorec'=>$fce);
  $ret= (object)array('luzka'=>$luzka,'kocarek'=>$bez,'pocetdnu'=>$noci,'svp'=>$svp,
                      'strava_cel'=>$cela,'strava_pol'=>$polo,'vzorec'=>$fce,'vek'=>$vek);
                                                debug($ret,"osob:$koje,$deti,$dosp $msg fce=$fce");
  return $ret;
}
# --------------------------------------------------------------------------------- akce2_vzorec_test
# test výpočtu platby za pobyt na akci
function akce2_vzorec_test($id_akce,$nu=2,$nd=0,$nk=0) {  trace();
  $ret= (object)array('navrh'=>'','err'=>'');
  $map_typ= map_cis('ms_akce_ubytovan','zkratka');
  $types= select("GROUP_CONCAT(DISTINCT typ ORDER BY typ)","cenik","id_akce=$id_akce GROUP BY id_akce");
  // obecné info o akci
  list($ma_cenik,$noci,$strava_oddo)=
    select("ma_cenik,DATEDIFF(datum_do,datum_od),strava_oddo","akce","id_duakce=$id_akce");
                                                display("$ma_cenik,$noci,$strava_oddo - typy:$types ");
  if ( !$ma_cenik ) { $html= "akce nemá ceník"; goto end; }
  // definované položky
  $o= $strava_oddo=='oo' ? 1 : 0;       // oběd navíc
  $cenik= array(
    //            u d k noci oo plus
    'Nl' => array(1,1,0,   1, 0,  1),
    'P'  => array(1,0,0,   0, 0,  1),
    'Pd' => array(0,1,0,   0, 0,  1),
    'Pk' => array(0,0,1,   0, 0,  1),
    'Su' => array(1,0,0,   0, 0, -1),
    'Sk' => array(0,0,1,   0, 0, -1),
    'sc' => array(1,0,0,   1, 0,  1),
    'oc' => array(1,0,0,   1,$o,  1),
    'vc' => array(1,0,0,   1, 0,  1),
    'sp' => array(0,1,0,   1, 0,  1),
    'op' => array(0,1,0,   1,$o,  1),
    'vp' => array(0,1,0,   1, 0,  1),
  );
  // výpočet ceny podle parametrů jednotlivých typů (jsou-li)
  foreach(explode(',',$types) as $typ) {
    $title= $typ ? "<h3>ceny pro ".$map_typ[$typ]."</h3>" : '';
    $cena= 0;
    $html.= "$title<table>";
    $ra= mysql_qry("SELECT * FROM cenik WHERE id_akce=$id_akce AND za!='' AND typ=$typ ORDER BY poradi");
    while ( $ra && ($a= mysql_fetch_object($ra)) ) {
      $acena= $a->cena;
      list($za_u,$za_d,$za_k,$za_noc,$oo,$plus)= $cenik[$a->za];
      $nx= $nu*$za_u + $nd*$za_d + $nk*$za_k;
      $cena+= $cc= $nx * ($za_noc?$noci+$oo:1) * $acena * $plus;
      if ( $cc ) {
        $pocet= $za_noc?" * ".($noci+$oo):'';
        $html.= "<tr>
          <td>{$a->polozka} ($nx$pocet * $acena)</td>
          <td align='right'>$cc</td></tr>";
      }
    }
    $html.= "<tr><td><b>Celkem</b></td><td align='right'><b>$cena</b></td></tr>";
    $html.= "</table>";
  }
  // návrat
end:
  $ret->navrh.= $html;
  return $ret;
}
# ------------------------------------------------------------------------------ akce2_vzorec_soubeh
# výpočet platby za pobyt na hlavní akci, včetně platby za souběžnou akci (děti)
# pokud je $id_pobyt=0 provede se výpočet podle dodaných hodnot (dosp+koje)
function akce2_vzorec_soubeh($id_pobyt,$id_hlavni,$id_soubezna,$dosp=0,$deti=0,$koje=0) { trace();
  // načtení ceníků
  $sleva= 0;
  $ret= (object)array('navrh'=>'','err'=>'','naklad_d'=>0,'poplatek_d'=>0);
  akce2_nacti_cenik($id_hlavni,$cenik_dosp,$ret->navrh);   if ( $html ) goto end;
  akce2_nacti_cenik($id_soubezna,$cenik_deti,$ret->navrh); if ( $html ) goto end;
  $map_kat= map_cis('ms_akce_dite_kat','zkratka');
  if ( $id_pobyt ) {
    // zjištění parametrů pobytu podle hlavní akce
    $qp= "SELECT * FROM pobyt AS p JOIN akce AS a ON p.id_akce=a.id_duakce WHERE id_pobyt=$id_pobyt";
    $rp= mysql_qry($qp);
    if ( $rp && ($p= mysql_fetch_object($rp)) ) {
      $pocetdnu= $p->pocetdnu;
      $strava_oddo= $p->strava_oddo;
      $dosp= $p->pouze ? 1 : 2;
      $sleva= $p->sleva;
  //     $svp= $p->svp;
  //     $neprijel= $p->funkce==10;
      $datum_od= $p->datum_od;
    }
    // pokud mají děti označenou kategorii X, určuje se cena podle pX ceníku souběžné akce
    // cena pro dospělé se určí podle ceníku hlavní akce - děti bez kategorie se nesmí
    $deti_kat= array();
    $n= $ndeti= $chuv= 0;
    $qo= "SELECT o.jmeno,s.dite_kat,p.funkce, t.role, p.ubytovani, narozeni, p.funkce,
           s.pecovane,(SELECT CONCAT(osoba.prijmeni,',',osoba.jmeno,',',pobyt.id_pobyt)
            FROM pobyt
            JOIN spolu ON spolu.id_pobyt=pobyt.id_pobyt
            JOIN osoba ON osoba.id_osoba=spolu.id_osoba
            WHERE pobyt.id_akce=p.id_akce AND spolu.pecovane=o.id_osoba) AS _chuva
          FROM spolu AS s
          JOIN osoba AS o ON s.id_osoba=o.id_osoba
          JOIN pobyt AS p USING(id_pobyt)
          LEFT JOIN tvori AS t ON t.id_osoba=o.id_osoba AND t.id_rodina=p.i0_rodina
          WHERE id_pobyt=$id_pobyt";
    $ro= mysql_qry($qo);
    while ( $ro && ($o= mysql_fetch_object($ro)) ) {
      $vek= narozeni2roky(sql2stamp($o->narozeni),sql2stamp($datum_od));
      $kat= $o->dite_kat;
      $pps= $o->funkce==1;
      if ( $o->role=='d' ) {
        if ( $kat ) {
          // dítě - speciální cena
          $deti_kat[$map_kat[$kat]]++;
          $ndeti++;
        }
        elseif ( $vek<18 ) {
          // dítě bez kategorie
          $ret->err.= "<br>Chybí kategorie u dítěte: {$o->jmeno}";
          $ndeti++;
        }
        if ( $o->_chuva ) {
          list($prijmeni,$jmeno,$pobyt)= explode(',',$o->_chuva);
          if ( $pobyt!=$id_pobyt ) {
            // chůva nebydlí s námi ale platíme ji
            $chuv++;
          }
          else {
            // chůva bydlí s námi a platíme ji
            $chuv++;
          }
        }
      }
      $n++;
    }
    // kontrola počtu
    if ( $dosp + $chuv + $ndeti != $n ) {
      $ret->err.= "<br>chyba v počtech: dospělí $dosp + chůvy $chuv + děti $ndeti není celkem $n";
    }
  }
  elseif ( $id_hlavni ) {
    // zjištění parametrů testovacího "pobytu" podle ceníku dané akce
    list($pocetdnu,$strava_oddo)=
      select("DATEDIFF(datum_do,datum_od),strava_oddo","akce","id_duakce=$id_hlavni");
    // doplnění dětí do tabulky jako kojenců
    if ( $koje ) $deti_kat['F']= $koje;
    if ( $deti ) $deti_kat['B']= $deti;
  }
  $dosp_chuv= $dosp+$chuv;
//                                         debug($deti_kat,"dětí");
  $Kc= "&nbsp;Kč";
  // redakce textu k ceně dospělých
  $html.= "<b>Rozpis platby za účast dospělých na jejich akci</b><table>";
  $cena= 0;
  $ubytovani= $strava= $program= $slevy= '';
  foreach($cenik_dosp as $za=>$a) {
    $c= $a->c; $txt= $a->txt;
    switch ($za) {
    case 'Nl':
      $cena+= $cc= $dosp_chuv * $pocetdnu * $c;
      if ( !$cc ) break;
      $ret->c_nocleh+= $cc;
      $ubytovani.= "<tr><td>".($dosp_chuv*$pocetdnu)." x $txt ($c$Kc)</td><td align='right'>$cc$Kc</td></tr>";
      break;
    case 'P':
      $cena+= $cc= $c * $dosp;
      if ( !$cc ) break;
      $ret->c_program+= $cc;
      $program.= "<tr><td>$dosp x $txt ($c$Kc)</td><td align='right'>$cc$Kc</td></tr>";
      break;
    case 'Su':
      if ( $pps ) continue;
      $cena+= $cc= - $c * $dosp;
      if ( !$cc ) break;
      $ret->c_sleva+= $cc;
      $slevy.= "<tr><td>$dosp x $txt ($c$Kc)</td><td align='right'>$cc$Kc</td></tr>";
      break;
    case 'Sp':
      if ( !$pps ) continue;
      $cena+= $cc= - $c * $dosp;
      if ( !$cc ) break;
      $ret->c_sleva+= $cc;
      $slevy.= "<tr><td>$dosp x $txt ($c$Kc)</td><td align='right'>$cc$Kc</td></tr>";
      break;
    case 'sc': case 'oc': case 'vc':
      $strav= $dosp_chuv * ($pocetdnu + ($za=='oc' && $strava_oddo=='oo' ? 1 : 0)); // případně oběd navíc
      $cena+= $cc= $strav * $c;
      if ( !$cc ) break;
      $ret->c_strava+= $cc;
      $html.= "<tr><td>$strav x $txt ($c$Kc)</td><td align='right'>$cc$Kc</td></tr>";
      break;
    default:
      $ret->err.= "<br>cenu za $za nelze vypočítat";
    }
  }
  // doplnění slev
  if ( $sleva ) {
    $cena-= $sleva;
    $ret->c_sleva-= $sleva;
    $slevy.= "<tr><td>zvláštní sleva</td><td align='right'>$sleva$Kc</td></tr>";
  }
  // konečná redakce textu k ceně dospělých
  if ( $ubytovani ) $html.= "<tr><th>ubytování</th></tr>$ubytovani";
  if ( $strava )    $html.= "<tr><th>strava</th></tr>$strava";
  if ( $program )   $html.= "<tr><th>program</th></tr>$program";
  if ( $slevy )     $html.= "<tr><th>sleva</th></tr>$slevy";
  $html.= "<tr><th>Celkem za dospělé</th><th align='right'>$cena$Kc</th></tr>";
  $html.= "</table>";
  // redakce textu k ceně dětí
  $sleva= "";
  if ( count($deti_kat) ) {
    $html.= "<br><b>Rozpis platby za účast dětí na jejich akci</b><table>";
    $cena= 0;
    ksort($deti_kat);
    foreach($deti_kat as $kat=>$n) {
      $a= $cenik_deti["p$kat"]; $c= $a->c; $txt= $a->txt;
      $cena+= $cc= $c * $n;
      $ret->naklad_d+= $cc;
      $html.= "<tr><td>$n x $txt </td><td align='right'>$cc$Kc</td></tr>";
      $a= $cenik_deti["d$kat"]; $c= $a->c; $txt= $a->txt;
      $cena+= $cc= - $c * $n;
      $ret->poplatek_d+= $cc;
      $sleva.= "<tr><td>$n x $txt </td><td align='right'>$cc$Kc</td></tr>";
    }
    $ret->poplatek_d+= $ret->naklad_d;
    $html.= "<tr><th>sleva</th></tr>$sleva";
    $html.= "<tr><th>Celkem za děti</th><th align='right'>$cena$Kc</th></tr>";
    $html.= "</table>";
  }
end:
  if ( $ret->err ) $ret->navrh.= "<b style='color:red'>POZOR! neúplná platba:</b>{$ret->err}<hr>";
  $ret->navrh.= $html;
  $ret->mail= "<div style='background-color:#eeeeee;margin-left:15px'>$html</div>";
//                                                         debug($ret,"akce_vzorec_soubeh");
  return $ret;
}
# -------------------------------------------------------------------------------- akce2_nacti_cenik
# lokální pro akce_vzorec_soubeh a akce_sestava_lidi
function akce2_nacti_cenik($id_akce,&$cenik,&$html) {
  $qa= "SELECT * FROM cenik WHERE id_akce=$id_akce ORDER BY poradi";
  $ra= mysql_qry($qa);
  if ( !mysql_num_rows($ra) ) {
    $html.= "akce $id_akce nemá ceník";
  }
  else {
    $cenik= array();
    while ( $ra && ($a= mysql_fetch_object($ra)) ) {
      $za= $a->za;
      if ( !$za ) continue;
      $cc= (object)array();
      if ( isset($cenik[$za]) ) $html.= "v ceníku se opakují kódy za=$za";
      $cenik[$za]= (object)array('c'=>$a->cena,'txt'=>$a->polozka);
    }
                                                        debug($cenik,"ceník pro $id_akce");
  }
}
# ------------------------------------------------------------------------------------- akce2_vzorec
# výpočet platby za pobyt na akci
# od 130416 přidána položka CENIK.typ - pokud je 0 tak nemá vliv,
#                                       pokud je nenulová pak se bere hodnota podle POBYT.ubytovani
function akce2_vzorec($id_pobyt) {  trace();
  $id_akce= 0;
  $ok= true;
  $ret= (object)array('navrh'=>'cenu nelze spočítat','eko'=>(object)array('vzorec'=>(object)array()));
  // parametry pobytu
  $x= (object)array();
  $ubytovani= 0;
  $qp= "SELECT * FROM pobyt AS p
        JOIN akce AS a ON p.id_akce=a.id_duakce WHERE id_pobyt=$id_pobyt";
  $rp= mysql_qry($qp);
  if ( $rp && ($p= mysql_fetch_object($rp)) ) {
    $id_akce= $p->id_akce;
    $x->nocoluzka+= $p->luzka * $p->pocetdnu;
    $x->nocoprist+= $p->pristylky * $p->pocetdnu;
    $ucastniku= $p->pouze ? 1 : 2;
    $vzorec= $p->vzorec;
    $ubytovani= $p->ubytovani;
    $sleva= $p->sleva;
    $svp= $p->svp;
    $neprijel= $p->funkce==10;
    $datum_od= $p->datum_od;
  }
  // podrobné parametry, ubytovani ma hodnoty z číselníku ms_akce_ubytovan
  $deti= $koje= $chuv= $dite_chovane= $koje_chovany= 0;
  $chuvy= $del= '';
  $qo= "SELECT o.jmeno,o.narozeni,p.funkce,t.role, p.ubytovani,
         s.pecovane,(SELECT CONCAT(osoba.prijmeni,',',osoba.jmeno,',',pobyt.id_pobyt)
          FROM pobyt
          JOIN spolu ON spolu.id_pobyt=pobyt.id_pobyt
          JOIN osoba ON osoba.id_osoba=spolu.id_osoba
          WHERE pobyt.id_akce=p.id_akce AND spolu.pecovane=o.id_osoba) AS _chuva
        FROM spolu AS s
        JOIN osoba AS o ON s.id_osoba=o.id_osoba
        JOIN pobyt AS p USING(id_pobyt)
        LEFT JOIN tvori AS t ON t.id_osoba=o.id_osoba AND t.id_rodina=p.i0_rodina
        WHERE id_pobyt=$id_pobyt
        GROUP BY o.id_osoba";
  $ro= mysql_qry($qo);
  while ( $ro && ($o= mysql_fetch_object($ro)) ) {
    if ( $o->role=='d' ) {
      $vek= narozeni2roky(sql2stamp($o->narozeni),sql2stamp($datum_od));
      if ( $vek<3 ) {
        $koje++;
        if ( $o->_chuva ) $koje_chovany++;
      }
      else {
        $deti++;
        if ( $o->_chuva ) $dite_chovane++;
      }
      if ( $o->_chuva ) {
        list($prijmeni,$jmeno,$pobyt)= explode(',',$o->_chuva);
        if ( $pobyt!=$id_pobyt ) {
          // chůva nebydlí s námi ale platíme ji
          $chuvy= "$del$jmeno $prijmeni";
          $del= ' a ';
        }
        else {
          // chůva bydlí s námi a platíme ji
        }
      }
      if ( $o->pecovane ) {
        $chuv++;
      }
    }
  }
//                                                         debug($x,"pobyt");
  // zpracování strav
  $strava= akce2_strava_pary($id_akce,'','','',true,$id_pobyt);
//                                                         debug($strava,"strava");
  $jidel= (object)array();
  foreach ($strava->suma as $den_jidlo=>$pocet) {
    list($den,$jidlo)= explode(' ',$den_jidlo);
    $jidel->$jidlo+= $pocet;
  }
//                                                         debug($jidel,"strava");
  // načtení cenového vzorce a ceníku
  $vzor= array();
  $qry= "SELECT * FROM _cis WHERE druh='ms_cena_vzorec' AND data=$vzorec";
  $res= mysql_qry($qry);
  if ( $res && $c= mysql_fetch_object($res) ) {
    $vzor= $c;
    $vzor->slevy= json_decode($vzor->ikona);
    $ret->eko->slevy= $vzor->slevy;
  }
//                                                         debug($vzor);
  // načtení ceníku do pole $cenik s případnou specifikací podle typu ubytování
  $qa= "SELECT * FROM cenik WHERE id_akce=$id_akce ORDER BY poradi";
  $ra= mysql_qry($qa);
  $n= $ra ? mysql_num_rows($ra) : 0;
  if ( !$n ) {
    $html.= "akce {$pobyt->id_akce} nemá cenový vzorec";
    $ok= false;
  }
  else {
    $cenik= array();
    $cenik_typy= false;
    $nazev_ceniku= '';
    while ( $ra && ($a= mysql_fetch_object($ra)) ) {
      $cc= (object)array();
      // diskuse pole typ - je-li nenulové, ignorují se hodnoty různé od POBYT.ubytovani
      if ( $a->typ!=0 && $a->typ!=$ubytovani ) continue;
      $cc->typ= $a->typ;
      if ( $a->typ && !$cenik_typy ) {
        // ceník má typy a tedy pobyt musí mít definované nenulové ubytování
        if ( !$ubytovani ) {
          $html.= "účastník nemá definován typ ubytování, ačkoliv to ceník požaduje";
          $ok= false;
        }
        $cenik_typy= true;
      }
      $pol= $a->polozka;
      if ( $cenik_typy && substr($pol,0,1)=='-' && substr($pol,1,1)!='-' ) {
        // název typu ceníku
        $nazev_ceniku= substr($pol,1);
      }

      $cc->txt= $pol;
      $cc->za= $a->za;
      $cc->c= $a->cena;
      $cc->j= $a->za ? $jidel->{$a->za} : '';
      $cenik[]= $cc;
    }
//                                                         debug($cenik,"ceník pro typ $ubytovani");
  }
  // výpočty
  if ( $ok ) {
    $nl= $x->nocoluzka;
    $np= $x->nocoprist;
    $u= $ucastniku;
    $cena= 0;
    $html.= "<table>";
    // ubytování
    $html.= "<tr><th>ubytování $nazev_ceniku</th></tr>";
    $ret->c_nocleh= 0;
    if ( $vzorec && $vzor->slevy->ubytovani===0 ) {
      $html.= "<tr><td>zdarma</td><td align='right'>0</td></tr>";
    }
    elseif ( $neprijel ) {
      $html.= "<tr><td>storno</td><td align='right'>0</td></tr>";
    }
    else {
      foreach ($cenik as $a) {
      switch ($a->za) {
        case 'Nl':
          $cc= $nl * $a->c;
          if ( !$cc ) break;
          $cena+= $cc;
          $ret->c_nocleh+= $cc;
          $html.= "<tr><td>{$a->txt} ($nl*{$a->c})</td><td align='right'>$cc</td></tr>";
          break;
        case 'Np':
          $cc= $np * $a->c;
          if ( !$cc ) break;
          $cena+= $cc;
          $ret->c_nocleh+= $cc;
          $html.= "<tr><td>{$a->txt} ($np*{$a->c})</td><td align='right'>$cc</td></tr>";
          break;
        }
      }
      $html.= "<tr><td></td><td></td><th align='right'>{$ret->c_nocleh}</th></tr>";
    }
    // strava
    $html.= "<tr><th>strava</th></tr>";
    $ret->c_strava= 0;
    if ( $vzorec && $vzor->slevy->strava===0 ) {
      $html.= "<tr><td>zdarma</td><td align='right'>0</td></tr>";
    }
    else {
      foreach ($cenik as $a) {
        if ( $a->j ) switch ($a->za) {
        case 'sc': case 'sp': case 'oc':
        case 'op': case 'vc': case 'vp':
          $cc= $a->j * $a->c;
          if ( !$cc ) break;
          $cena+= $cc;
          $ret->c_strava+= $cc;
          $html.= "<tr><td>{$a->txt} ({$a->j}*{$a->c})</td><td align='right'>$cc</td></tr>";
          break;
        }
      }
      $html.= "<tr><td></td><td></td><th align='right'>{$ret->c_strava}</th></tr>";
    }
    // program
    $html.= "<tr><th>program</th></tr>";
    $ret->c_program= 0;
    if ( $vzorec && $vzor->slevy->program===0 ) {
      $html.= "<tr><td>program</td><td align='right'>0</td></tr>";
    }
    elseif ( $neprijel ) {
      $html.= "<tr><td>storno</td><td align='right'>0</td></tr>";
    }
    else {
      foreach ($cenik as $a) {
        switch ($a->za) {
        case 'P':
          $cc= $a->c * $u;
          $cena+= $cc;
          $ret->c_program+= $cc;
          $ret->eko->vzorec->{$a->za}+= $cc;
          $html.= "<tr><td>{$a->txt}</td><td align='right'>$cc</td></tr>";
          break;
        case 'Pd':
          if ( $deti - $dite_chovane - $chuv > 0 ) {
            $cc= $a->c * ($deti-$dite_chovane-$chuv);
            $cena+= $cc;
            $ret->c_program+= $cc;
            $ret->eko->vzorec->{$a->za}+= $cc;
            $html.= "<tr><td>{$a->txt}</td><td align='right'>$cc</td></tr>";
          }
          break;
        case 'Pk':
          if ( $koje - $koje_chovany > 0 ) {
            $cc= $a->c * ($koje-$koje_chovany);
            $cena+= $cc;
            $ret->c_program+= $cc;
            $ret->eko->vzorec->{$a->za}+= $cc;
            $html.= "<tr><td>{$a->txt}</td><td align='right'>$cc</td></tr>";
          }
          break;
        }
      }
      $html.= "<tr><td></td><td></td><th align='right'>{$ret->c_program}</th></tr>";
    }
    // případné slevy
    $ret->c_sleva= 0;
    $sleva_cenik= 0;
    $sleva_cenik_html= '';
    foreach ($cenik as $a) {
      switch ($a->za) {
      case 'Su':        // sleva na dospělého účastníka
        $sleva_cenik+= $u * $a->c;
        $sleva_cenik_html.= '';
        break;
      case 'Sk':        // sleva na kojence
        $sleva_cenik+= $koje * $a->c;
        $sleva_cenik_html.= '';
        break;
      }
    }
    $sleva+= $sleva_cenik;
    if ( !$neprijel && ($sleva!=0 || isset($vzor->slevy->procenta) || isset($vzor->slevy->za)) ) {
      $html.= "<tr><th>slevy</th></tr>";
      if ( $sleva!=0 ) {
        $cena-= $sleva;
        $ret->c_sleva-= $sleva;
        if ( !isset($ret->eko->slevy) ) $ret->eko->slevy= (object)array();
        $ret->eko->slevy->kc+= $sleva;
        $html.= "<tr><td>sleva z ceny</td><td align='right'>$sleva</td></tr>";
      }
      if ( isset($vzor->slevy->procenta) ) {
        $cc= -round($cena * $vzor->slevy->procenta/100,-1);
        $cena+= $cc;
        $ret->c_sleva+= $cc;
        $html.= "<tr><td>{$vzor->zkratka} {$vzor->slevy->procenta}%</td><td align='right'>$cc</td></tr>";
      }
      if ( isset($vzor->slevy->castka) ) {
        $cc= -$vzor->slevy->castka;
        $cena+= $cc;
        $ret->c_sleva+= $cc;
        $html.= "<tr><td>{$vzor->zkratka} {$vzor->slevy->castka},-</td><td align='right'>$cc</td></tr>";
      }
      if ( isset($vzor->slevy->za) ) {
        $cc= 0;
        foreach ($cenik as $radek) {
          if ( $radek->za==$vzor->slevy->za ) {
            $cc= -$radek->c;
            break;
          }
        }
        $cena+= $cc;
        $ret->c_sleva+= $cc;
        $html.= "<tr><td>{$vzor->zkratka} </td><td align='right'>$cc</td></tr>";
      }
      $html.= "<tr><td></td><td></td><th align='right'>{$ret->c_sleva}</th></tr>";
    }
    $html.= "<tr><th>celkový poplatek</th><td></td><th align='right'>$cena</th></tr>";
    if ( $chuvy ) {
      $html.= "<tr><td colspan=3>(Cena obsahuje náklady na vlastního pečovatele: $chuvy)</td></tr>";
    }
    $html.= "</table>";
    $ret->navrh= $html;
    $ret->mail= "<div style='background-color:#eeeeee;margin-left:15px'>$html</div>";
  }
  else {
    $ret->navrh.= ", protože $html";
  }
  return $ret;
}
/** ========================================================================================> UCAST2 */
# --------------------------------------------------------------------------------- ucast2_chain_rod
# ==> . chain rod
# upozorní na pravděpodobnost duplicity rodiny
function ucast2_chain_rod($idro) {
  $items2array= function($items,$omit='\s') {
    $items= preg_replace("/[$omit]/",'',$items);
    $arr= preg_split("/,;/",trim($items,",;"),-1,PREG_SPLIT_NO_EMPTY);
    return $arr;
  };
  $ret= (object)array('msg'=>'');
  if ( !$idro ) goto end;
  $nr= 0;
  $ro= (object)array();
  $rs= $x= array();
  $nazev= '';
  // srovnávané prvky
  $flds_r= "id_rodina,access,nazev,obec,emaily,telefony,datsvatba";
  $flds_o= "jmeno,kontakt,email,telefon,narozeni";
  // dotazovaná rodina
  $jmena= $telefony= $emaily= $narozeni= array();
  $qr= mysql_qry("SELECT $flds_r FROM rodina WHERE id_rodina=$idro");
  while ( $qr && ($rx= mysql_fetch_object($qr)) ) {
    $ro= $rx;
    $nazev= $rx->nazev;
    $emaily= $items2array($rx->emaily);
    $telefony= $items2array($rx->telefony);
    $qc= mysql_qry("SELECT $flds_o FROM osoba JOIN tvori USING (id_osoba)
      WHERE id_rodina={$rx->id_rodina}");
    while ( $qc && ($cx= mysql_fetch_object($qc)) ) {
      $ro->cleni[]= $cx;
      $jmena[]= $cx->jmeno;
      if ( $cx->narozeni!='0000-00-00' ) $narozeni[]= $cx->narozeni;
      if ( $cx->kontakt ) {
        foreach($items2array($cx->email) as $em) if ( !in_array($em,$emaily) )
          $emaily[]= $em;
        foreach($items2array($cx->telefon,'^\d') as $tf) if ( !in_array($tf,$telefony) )
          $telefony[]= $tf;
      }
      $ro->telefony= $telefony;
      $ro->emaily= $emaily;
    }
  }
//                                                 debug($ro);
//   goto end;
  // . vzory faktorů
  // vyloučené shody
  $ids= select1("GROUP_CONCAT(DISTINCT val)","_track","kde='rodina' AND op='r' AND klic='$idro'");
  $ruzne= $ids ? "AND id_rodina NOT IN ($ids)" : "";
  // podobné rodiny
  $qr= mysql_qry("SELECT $flds_r FROM rodina
    WHERE nazev='$nazev' AND id_rodina!=$idro AND deleted='' $ruzne");
  while ( $qr && ($rx= mysql_fetch_object($qr)) ) {
    $rs[$rx->id_rodina]= $rx;
    $qc= mysql_qry("SELECT $flds_o FROM osoba JOIN tvori USING (id_osoba)
      WHERE id_rodina={$rx->id_rodina}");
    while ( $qc && ($cx= mysql_fetch_object($qc)) ) {
      $rs[$rx->id_rodina]->cleni[]= $cx;
    }
  }
//                                                 debug($rs);
  // porovnání
  $nr= 0;
  foreach ($rs as $idr=>$rx) {
    $xi= (object)array('idr'=>$idr,'asi'=>'','orgs'=>0);
    $nt= $n_jmeno= $n_kontakt= $n_narozeni= 0;
    // . členové rodiny
    if ( $rx->cleni ) foreach ($rx->cleni as $cx) {
      $nt++;
      // .. jména
      if ( in_array($cx->jmeno,$jmena) )  $n_jmeno++;
      // .. kontakty
      if ( $cx->kontakt ) {
        foreach($items2array($cx->email) as $em) if ( in_array($em,$emaily) ) $n_kontakt++;
        foreach($items2array($cx->telefon,'^\d') as $tf) if ( in_array($tf,$telefony) ) $n_kontakt++;
      }
      // .. narození
      if ( in_array($cx->narozeni,$narozeni) )  $n_narozeni++;
    }
    $xi->cleni=    $n_jmeno;
    // . narození členů +1 * n
    $xi->narozeni= $n_narozeni;
    // . bydliště rodiny +5
    $xi->bydliste= $rx->obec==$ro->obec ? 1 : 0;
    // . svatba +10
    $xi->svatba= $rx->datsvatba!='0000-00-00' && $rx->datsvatba==$ro->datsvatba ? 1 : 0;
    // . kontakty +1 * n
    foreach($items2array($rx->email) as $em) if ( in_array($em,$emaily) ) $n_kontakt++;
    foreach($items2array($rx->telefon,'^\d') as $tf) if ( in_array($tf,$telefony) ) $n_kontakt++;
    $xi->kontakty= $n_kontakt;
    // organizace
    $xi->org= $rx->access;
    // zápis
    $x[$idr]= $xi;
    $nr++;
  }
  // míra podobnosti rodin
  $nx0= count($x);
  $idr= 0;
  $orgs= $ro->access;
  foreach ($x as $i=>$xi) {
    $xi->asi.= $xi->svatba   ? 'S' : '';
    $xi->asi.= $xi->bydliste ? 'B' : '';
    $xi->asi.= $xi->cleni    ? 'C' : '';
    $xi->asi.= $xi->narozeni ? 'N' : '';
    $xi->asi.= $xi->kontakty ? 'K' : '';
    if ( !strlen($xi->asi) || $xi->asi=='C' ) { unset($x[$i]); continue; }
    $orgs|= $xi->org;
    $idr= $i;
  }
//                                                 debug($x,"podobné rodiny");
  $nx= count($x);
  $msg= $dup= $keys= '';
  if ( $nx==0 ) {
    // není duplicita
    $msg= ($nx0 ? "asi " : "určitě ")." není duplicitní";
  }
  elseif ( $nx==1 ) {
    // jednoznačná duplicita
    $keys= $idr;
    $dup= $x[$idr]->asi;
    $msg= "pravděpodobně ($dup) duplicitní s rodinou $idr "
          . ($xi->org==1 ? " z YS" : ($xi->org==2 ? " z FA" : ''));
  }
  else {
    // více možností
    uasort($x,function($a,$b) { return $a->asi > $b->asi ? -1 : +1; });
    $msg= "je $nx pravděpodobných duplicit:";
    $del= '';
    foreach ($x as $i=>$xi) {
      $dupi= $xi->asi;
      if ( !$dup ) $dup= $dupi;
      $mira= strpos($dupi,'S')!==false || strpos($dupi,'K')!==false ? "pravděpodobně" : "možná";
      $msg.= "<br>$mira ($dupi) duplicitní s rodinou $i"
          . ($xi->org==1 ? " z YS" : ($xi->org==2 ? " z FA" : ''));
      $keys.= "$del$i";
      $del= ',';
    }
  }
//                                                 debug($x);
  $ret->msg= $msg;
  $ret->dup= $dup;
  $ret->keys= $keys;
end:
  return $ret;
}
# --------------------------------------------------------------------------------- ucast2_chain_oso
# ==> . chain oso
# upozorní na pravděpodobnost duplicity osoby
# pokud je zadáno $idr doplní i pravděpodobné duplicity v této rodině - na základě narození a jmen
function ucast2_chain_oso($idoo,$idr=0) {
  $items2array= function($items,$omit='\s') {
    $items= preg_replace("/[$omit]/",'',$items);
    $arr= preg_split("/,;/",trim($items,",;"),-1,PREG_SPLIT_NO_EMPTY);
    return $arr;
  };
  $ret= (object)array('msg'=>'','dup'=>'');
  $nr= 0;
  $o= (object)array();
  $os= $x= array();
  $nazev= '';
  // srovnávané prvky
  $flds= "id_osoba,o.access,prijmeni,jmeno,narozeni,kontakt,email,telefon,adresa,o.obec";
  // dotazovaná osoba
  $jmena= $telefony= $emaily= array();
  $narozeni= '';
  $qo= mysql_qry("SELECT $flds FROM osoba AS o WHERE id_osoba=$idoo");
  if ( !$qo ) { $msg= "$idoo není osoba"; goto end; }
  $o= mysql_fetch_object($qo);
  $jmeno= $o->jmeno;
  $prijmeni= $o->prijmeni;
  if ( !trim($prijmeni) ) { goto end; }
  $obec= $o->adresa ? $o->obec : '';
  $emaily= $items2array($ox->email);
  $narozeni= $o->narozeni=='0000-00-00' ? '' : $o->narozeni;
  $narozeni_yyyy= substr($o->narozeni,0,4);
  $narozeni_11= substr($o->narozeni,5,5)=="01-01" ? $narozeni_yyyy : '';
  $emaily= $telefony= array();
  if ( $o->kontakt ) {
    $emaily= $items2array($o->email);
    $telefony= $items2array($o->telefon,'^\d');
  }
//                                                 debug($o,"originál");
  // . vzory faktorů
  // podobné osoby tzn. stejné příjmení
  $qo= mysql_qry("
    SELECT $flds,r.obec,
      SUBSTR(MIN(CONCAT(IF(role='','?',role),id_rodina)),2) AS _kmen
    FROM osoba AS o
      JOIN tvori USING(id_osoba)
      JOIN rodina AS r USING(id_rodina)
    WHERE (prijmeni='{$o->prijmeni}' /*OR rodne='{$o->prijmeni}'*/) AND id_osoba!=$idoo AND o.deleted=''
    GROUP BY id_osoba");
  while ( $qo && ($xo= mysql_fetch_object($qo)) ) {
    $xo_jmeno= trim($xo->jmeno);
//                                                 display($xo_jmeno);
    if ( $jmeno=='' || $xo_jmeno=='' ) continue;
    if ( strpos($xo_jmeno,$jmeno)===false && strpos($jmeno,$xo_jmeno)===false ) continue;
    // vymazání chybných údajů
    if ( !$xo->adresa ) $xo->obec= '?';
    if ( !$xo->kontakt ) $xo->telefon= $xo->email= '?';
    // doplnění rodinných údajů z kmenové rodiny, je-li
    if ( (!$xo->adresa || !$xo->kontakt) && $xo->_kmen) {
      $qr= mysql_qry("
        SELECT obec,telefony,emaily
        FROM rodina AS r WHERE id_rodina={$xo->_kmen}");
      if ( $qr && ($r= mysql_fetch_object($qr)) ) {
        if ( !$xo->adresa ) $xo->obec= $r->obec;
        if ( !$xo->kontakt ) {
          $xo->telefon= $r->telefony;
          $xo->email= $r->emaily;
        }
//                                                 debug($r,"kmen");
      }
    }
    $os[$xo->id_osoba]= $xo;
  }
  // podezřelí členi rodiny: jsou se stejným datem narození (nebo 1.1.rok kde rok=+-1 )
  $narozeni_od_do= ($narozeni_yyyy-1).','.($narozeni_yyyy).','.($narozeni_yyyy+1);
  if ( $idr ) {
    $qc= mysql_qry("
      SELECT id_osoba,prijmeni,jmeno,narozeni FROM osoba JOIN tvori USING (id_osoba)
      WHERE id_rodina=$idr AND deleted='' AND id_osoba!=$idoo AND (narozeni='$narozeni'
        OR DAY(narozeni)=1 AND MONTH(narozeni)=1 AND YEAR(narozeni) IN ($narozeni_od_do))");
    while ( $qc && ($xc= mysql_fetch_object($qc)) ) {
      $idc= $xc->id_osoba;
      if ( !isset($os[$idc]) ) $os[$idc]= (object)array('id_osoba'=>$idc);
      $os[$idc]->narozeni= $xc->narozeni;
    }
  }
//                                                 debug($os,"kopie $idoo");
  // porovnání
  $nr= 0;
  foreach ($os as $ido=>$ox) {
    $xi= (object)array('ido'=>$ido,'asi'=>'','orgs'=>0);
    // .. kontakty
    $xi->kontakty= 0;
    if ( $ox->kontakt ) {
      foreach($items2array($ox->email) as $em) if ( in_array($em,$emaily) ) $xi->kontakty++;
      foreach($items2array($ox->telefon,'^\d') as $tf) if ( in_array($tf,$telefony) ) $xi->kontakty++;
    }
    // .. bydliste
    $xi->bydliste= $ox->adresa && $ox->obec==$obec && $obec ? 1 : 0;
    // .. narození
    $rok= substr($ox->narozeni,0,4);
    $xi->narozeni= $ox->narozeni==$narozeni
                || $rok==$narozeni_11
                || substr($ox->narozeni,5,5)=="01-01" && abs($narozeni_yyyy-$rok)<=1
                 ? 1 : 0;
//                                                 display("{$ox->jmeno}/$jmeno: {$ox->narozeni}/$narozeni => {$xi->narozeni}");
    // organizace
    $xi->org= $ox->access;
    // zápis
    $x[$ido]= $xi;
  }
  // míra podobnosti osob
  $nx0= count($x);
  $i0= 0;
  $orgs= $ro->access;
  foreach ($x as $i=>$xi) {
    $xi->asi.= $xi->bydliste ? 'b' : '';
    $xi->asi.= $xi->narozeni ? 'n' : '';
    $xi->asi.= $xi->kontakty ? 'k' : '';
    if ( !strlen($xi->asi) || $xi->asi=='b' ) { unset($x[$i]); continue; }
    $orgs|= $xi->org;
    $i0= $i;
  }
//                                                 debug($x,"podobné osoby");
//                                                 goto end;
  $nx= count($x);
  $msg= $dup= $keys= '';
  if ( $nx==0 ) {
    // není duplicita
//     $msg= ($nx0 ? "asi " : "určitě ")." není duplicitní";
  }
  elseif ( $nx==1 ) {
    // jednoznačná duplicita
    $keys= $i0;
    // ujistíme se, že nebyli oznámeni jako různé tzv. _track(klic=idoo,op=r,val=idc)
    $r= select("COUNT(*)","_track","klic=$idoo AND op='r' AND val=$ido");
    if ( !$r ) {
      $dup= $x[$i0]->asi;
      $msg= "$idoo je pravděpodobně ($dup) kopie osoby $ido "
            . ($xi->org==1 ? " z YS" : ($xi->org==2 ? " z FA" : ''));
    }
  }
  else {
    // více možností
    uasort($x,function($a,$b) { return $a->asi > $b->asi ? -1 : +1; });
    $msg= '';
    $del= '';
    $nx= 0;
    foreach ($x as $i=>$xi) {
      // ujistíme se, že nebyli oznámeni jako různé tzv. _track(klic=idoo,op=r,val=idc)
      $r= select("COUNT(*)","_track","klic=$idoo AND op='r' AND val=$i");
      if ( !$r ) {
        $nx++;
        $dupi= $xi->asi;
        if ( !$dup ) $dup= $dupi;
        $mira= strpos($dupi,'n')!==false || strpos($dupi,'k')!==false ? "pravděpodobně" : "možná";
        $msg.= "$del je $mira ($dupi) kopie  osoby $i"
            . ($xi->org==1 ? " z YS" : ($xi->org==2 ? " z FA" : ''));
        $keys.= "$del$i";
        $del= ',';
      }
    }
    $msg= $nx ? ( $nx==1 ? "$idoo$msg" : "$idoo má $nx pravděpodobných kopií:$msg" ) : '';
  }
  $ret->msg= $msg;
                                                if ( $msg ) display($msg);
  $ret->dup= $dup;
  $ret->keys= $keys;
end:
//                                                 debug($ret,$idoo);
  return $ret;
}
/** ------------------------------------------------------------------------------ ucast2_browse_ask **/
# BROWSE ASK
# obsluha browse s optimize:ask
# x->order= {a|d} polozka
# x->show=  {polozka:[formát,vzor/1,...],...} pro položky s neprázdným vzorem
#                                             kde formát=/ = # $ % @ * .
# x->cond= podmínka   - pokud obsahuje /*duplicity*/ přidá se sloupec _dup
#                       pokud obsahuje /*dokumenty*/ přidá se do sloupce _docs 'd'
#                       pokud obsahuje /*css*/ bude se barvit _nazev,cleni.jmeno,rodiny
# -- x->atr=  pole jmen počítaných atributů:  [_ucast]
# pokud je tisk=true jsou je oddělovače řádků použit znak '≈' (oddělovač sloupců zůstává '~')
function ucast2_browse_ask($x,$tisk=false) {
  $delim= $tisk ? '≈' : '~';
  global $test_clmn,$test_asc, $y;
  $map_umi= map_cis('answer_umi','zkratka','poradi','ezer_answer');
//                                                         debug($map_umi,"map_umi");
  $umi= function ($xs) use ($map_umi) {
    $y= '';
    if ( $xs ) foreach (explode(',',$xs) as $x) {
      $y.= $map_umi[$x];
    }
    return $y;
  };
//                                                         debug($x,"akce_browse_ask");
//                                                         return;
  $y= (object)array('ok'=>0);
  foreach(explode(',','cmd,rows,quiet,key_id,oldkey') as $i) $y->$i= $x->$i;
  switch ($x->cmd) {
  case 'browse_load':  # -----------------------------------==> . browse_load
  default:
    # vnořené SQL definující @akce, @soubeh, @app
    if ( $x->sql ) mysql_qry($x->sql);
    $pobyt= array();              // $pobyt[id_pobyt]             vše
    $skup= array();               // $skup[skupina]               seznam id_pobyt
    $osoba= array();              // $osoba[id_osoba]             atributy osob na akci
    $cleni= "";
    $osoby= "";
    $rodina= array();             // $rodina[id_rodina]           atributy rodin na akci
    $rodina_pobyt= array();       // $rodina[i0_rodina]=id_pobyt  pobyt rodiny (je-li rodinný)
    $rodiny= "";
    $spolu= array();              // $spolu[id_osoba]             id_pobyt
    $tvori= array();              // $tvori[id_pobyt,id_osoba]    id_tvori,id_rodina,role,rodiny
    # ladění
    $AND= "";
//     $AND= "AND p.id_pobyt IN (44285,44279,44280,44281) -- prázdná rodina a pobyt";
//    $AND= "AND p.id_pobyt IN (44381) -- test";
//     $AND= "AND p.id_pobyt IN (43387,32218,32024) -- test";
//     $AND= "AND p.id_pobyt IN (43113,43385,43423) -- test Šmídkovi+Nečasovi+Novotní/LK2015";
//     $AND= "AND p.id_pobyt IN (43423) -- test Novotní/LK2015";
//     $AND= "AND p.id_pobyt IN (20487) -- Baklík Baklíková";
//     $AND= "AND p.id_pobyt IN (20488,20344) -- Bajerovi a Kubínovi";
//     $AND= "AND p.id_pobyt IN (20568,20793) -- Šmídkovi + Nečasovi";
    # pro browse_row přidáme klíč
    if ( $x->subcmd=='browse_row' ) {
      $AND= "AND p.id_pobyt={$y->oldkey}";
    }
    # kontext dotazu
    if ( !$x ) $q0= mysql_qry("SET @akce:=422,@soubeh:=0,@app:='ys';");
    # duplicity, dokumenty, css?
    $duplicity= strstr($x->cond,'/*duplicity*/') ? 1 : 0;
    $dokumenty= strstr($x->cond,'/*dokumenty*/') ? 1 : 0;
    $barvit=    strstr($x->cond,'/*css*/')       ? 1 : 0;
    # podmínka
    $cond= $x->cond ?: 1;
    # atributy akce
    $qa= mysql_qry("
      SELECT @akce,@soubeh AS soubeh,@app,
        datum_od,DATEDIFF(a.datum_do,a.datum_od)+1 AS dnu,ma_cenik,ma_cenu,cena
      FROM akce AS a
      WHERE a.id_duakce=@akce ");
    $akce= mysql_fetch_object($qa);
    # atributy pobytu
    $qp= mysql_qry("
      SELECT *
      FROM pobyt AS p
      WHERE $cond $AND ");
    while ( $qp && ($p= mysql_fetch_object($qp)) ) {
      $pobyt[$p->id_pobyt]= $p;
      $i0r= $p->i0_rodina;
      if ( $i0r ) {
        $rodina_pobyt[$i0r]= $p->id_pobyt;
        $pobyt[$p->id_pobyt]->access= $p;
        if ( !strpos(",$rodiny,",",$i0r,") )
          $rodiny.= ",$i0r";
      }
    }
//                                                         debug($rodina_pobyt,"rodina_pobyt");
    # seznam účastníků akce - podle podmínky
    $qu= mysql_qry("
      SELECT s.*,o.narozeni,MIN(CONCAT(IF(role='','?',role),id_rodina)) AS _role,o_umi
      FROM osoba AS o
      JOIN spolu AS s USING (id_osoba)
      JOIN pobyt AS p USING (id_pobyt)
      LEFT JOIN tvori AS t USING (id_osoba)
      WHERE o.deleted='' AND $cond $AND
      GROUP BY id_osoba
    ");
    while ( $qu && ($u= mysql_fetch_object($qu)) ) {
      $cleni.= ",{$u->id_osoba}";
      $idr= substr($u->_role,1);
      if ( $idr && !strpos(",$rodiny,",",$idr,") )
        $rodiny.= ",$idr";
      $pobyt[$u->id_pobyt]->cleni[$u->id_osoba]= $u;
      $spolu[$u->id_osoba]= $u->id_pobyt;
      // doplnění osobního umí - malým
      if ( $u->o_umi ) {
        $pobyt[$u->id_pobyt]->x_umi.= strtolower($umi($u->o_umi));
      }
    }
    $osoby.= $cleni;
    # seznam rodinných příslušníků
    $qp= mysql_qry("
      SELECT id_pobyt,id_rodina,id_tvori,id_osoba,role,o_umi,o.narozeni
      FROM pobyt AS p
      JOIN tvori AS t ON t.id_rodina=p.i0_rodina
      JOIN osoba AS o USING(id_osoba)
      WHERE o.access&@access AND $cond $AND
    ");
    while ( $qp && ($p= mysql_fetch_object($qp)) ) {
      $osoby.= ",{$p->id_osoba}";
      $idr= $p->id_rodina;
      if ( $idr && !strpos(",$rodiny,",",$idr,") )
        $rodiny.= ",$idr";
      if ( !isset($pobyt[$p->id_pobyt]->cleni[$p->id_osoba]) )
        $pobyt[$p->id_pobyt]->cleni[$p->id_osoba]= (object)array();
      $pobyt[$p->id_pobyt]->cleni[$p->id_osoba]->id_tvori= $p->id_tvori;
      $pobyt[$p->id_pobyt]->cleni[$p->id_osoba]->id_rodina= $idr;
      $pobyt[$p->id_pobyt]->cleni[$p->id_osoba]->role= $p->role;
      $pobyt[$p->id_pobyt]->cleni[$p->id_osoba]->o_umi= $p->o_umi;
      $pobyt[$p->id_pobyt]->cleni[$p->id_osoba]->narozeni= $p->narozeni;
    }
    # atributy rodin
    $qr= mysql_qry("SELECT * FROM rodina AS r WHERE deleted='' AND id_rodina IN (0$rodiny)");
    while ( $qr && ($r= mysql_fetch_object($qr)) ) {
      $r->datsvatba= sql_date1($r->datsvatba);                  // svatba d.m.r
      if ( $r->r_umi && $rodina_pobyt[$r->id_rodina] ) {
        // umí-li něco rodina a je na pobytu - velkým
        $pobyt[$rodina_pobyt[$r->id_rodina]]->x_umi=
          strtoupper($umi($r->r_umi)).' '.$pobyt[$rodina_pobyt[$r->id_rodina]]->x_umi;
      }
      $rodina[$r->id_rodina]= $r;
    }
    # atributy osob
    $qo= mysql_qry("SELECT * FROM osoba AS o WHERE deleted='' AND id_osoba IN (0$osoby)");
    while ( $qo && ($o= mysql_fetch_object($qo)) ) {
      $osoba[$o->id_osoba]= $o;
    }
    # seznam rodin osob
    $css= $barvit
        ? "IF(r.access=1,':ezer_ys',IF(r.access=2,':ezer_fa',IF(r.access=3,':ezer_db','')))" : "''";
    $qor= mysql_qry("
      SELECT id_osoba,
        IFNULL(GROUP_CONCAT(CONCAT(role,':',id_rodina,$css) SEPARATOR ','),'') AS _rody,
        SUBSTR(MIN(CONCAT(IF(role='','?',role),id_rodina)),2) AS _kmen,
        SUM(IF(r.fotka='',0,1)) AS _rfotky
      FROM osoba AS o
      JOIN tvori USING(id_osoba)
      JOIN rodina AS r USING(id_rodina)
      WHERE o.deleted='' AND id_osoba IN (0$osoby)
      GROUP BY id_osoba
    ");
    while ( $qor && ($or= mysql_fetch_object($qor)) ) {
      if ( !isset($osoba[$or->id_osoba]) ) $osoba[$or->id_osoba]= (object)array('_fotky'=>0);
      $osoba[$or->id_osoba]->_rody= $or->_rody;
      $kmen= $or->_kmen;
      $osoba[$or->id_osoba]->_kmen= $kmen;
      $osoba[$or->id_osoba]->_rfotky+= $or->_rfotky;
      foreach (explode(',',$or->_rody) as $rod) {
        list($role,$idr,$css)= explode(':',$rod);
        if ( !$rodina[$idr] ) {
          # doplnění (potřebných) rodinných údajů pro kmenové rodiny
//                                                         display("{$or->id_osoba} - $kmen");
          $qr= mysql_qry("
            SELECT * -- id_rodina,nazev,ulice,obec,psc,stat,telefony,emaily
            FROM rodina AS r WHERE id_rodina=$idr");
          while ( $qr && ($r= mysql_fetch_object($qr)) ) {
            $rodina[$idr]= $r;
          }
        }
      }
    }
//                                                         display("rodiny:$rodiny");
//                                                         debug($rodina,$rodiny);
//                                                         debug($osoba,'osoby po _rody');
    # seznamy položek
    $fpob1= ucast2_flds("key_pobyt=id_pobyt,_empty=0,key_akce=id_akce,key_osoba,key_spolu,key_rodina=i0_rodina,"
           . "keys_rodina='',c_suma,platba,xfunkce=funkce,funkce,skupina,dluh");
    $fakce= ucast2_flds("dnu,datum_od");
    $frod=  ucast2_flds("fotka,r_access=access,r_spz=spz,r_svatba=svatba,r_datsvatba=datsvatba,r_rozvod=rozvod,r_ulice=ulice,r_psc=psc,"
          . "r_obec=obec,r_stat=stat,r_telefony=telefony,r_emaily=emaily,r_umi,r_note=note");
    $fpob2= ucast2_flds("p_poznamka=poznamka,pokoj,budova,prednasi,luzka,pristylky,kocarek,pocetdnu"
          . ",strava_cel,strava_pol,c_nocleh=platba1,c_strava=platba2,c_program=platba3,c_sleva=platba4"
          . ",datplatby,cstrava_cel,cstrava_pol,svp,zpusobplat,naklad_d,poplatek_d,platba_d"
          . ",zpusobplat_d,datplatby_d,ubytovani,cd,avizo,sleva,vzorec,duvod_typ,duvod_text,x_umi");
    //      id_osoba,jmeno,_vek,id_tvori,id_rodina,role,_rody,narozeni
    $fos=   ucast2_flds("umrti,prijmeni,rodne,sex,adresa,ulice,psc,obec,stat,kontakt,telefon,nomail,email"
          . ",iniciace,uvitano,clen,obcanka,rc_xxxx,cirkev,vzdelani,titul,zamest,zajmy,jazyk,dieta"
          . ",aktivita,note,_kmen");
    $fspo=  ucast2_flds("id_spolu,_barva,s_role,dite_kat,poznamka,pecovane,pfunkce,pece_jm,pece_id,o_umi");

    # 1. průchod - kompletace údajů mezi pobyty
    $skup= array();
    foreach ($pobyt as $idp=>$p) {
      if ( !count($p->cleni) ) continue;
      # seřazení členů podle přítomnosti, role, věku
      uasort($p->cleni,function($a,$b) {
        $wa= $a->id_spolu==0 ? 4 : ( $a->role=='a' ? 1 : ( $a->role=='b' ? 2 : 3));
        $wb= $b->id_spolu==0 ? 4 : ( $b->role=='a' ? 1 : ( $b->role=='b' ? 2 : 3));
        return $wa == $wb ? ($a->narozeni==$b->narozeni ? 0 : ($a->narozeni > $b->narozeni ? 1 : -1))
                          : ($wa==$wb ? 0 : ($wa > $wb ? 1 : -1));
      });
      # skupinky
      if ( $p->skupina ) {
        $skup[$p->skupina][]= $idp;
      }
      # osobní pečování
      foreach ($p->cleni as $ido=>$s) {
        if ( $s->id_spolu && ($idop= $s->pecovane) ) {
          # pecujici
          $o2= $osoba[$idop];
          $s->pece_id= $o2->id_osoba;
          $s->pece_jm= $o2 ? $o2->prijmeni.' '.$o2->jmeno : '???';
          $s->s_role= 5;
          $s->_barva= 5;                        // barva: 5=osobně pečující, pfunkce=95
          # pečované
          $o1= $osoba[$ido];
          $s2= $pobyt[$spolu[$idop]]->cleni[$idop];
          if ( $s2 ) {
            $s2->pece_id= $o1->id_osoba;
            $s2->pece_jm= $o1 ? $o1->prijmeni.' '.$o1->jmeno : '???';
            $s2->s_role= 3;
            $s2->_barva= 3;                       // barva: 3=osobně pečované, pfunkce=92
          }
        }
      }
    }
    # 2. průchod - kompletace pobytu pro browse_load/ask
    $zz= array();
    foreach ($pobyt as $idp=>$p) {
      $p_access= 0;
      $idr= $p->i0_rodina ?: 0;
      $p->access= 5;
      $z= (object)array();
      $_ido01= $_ido02= $_ido1= $_ido2= 0;
      # agregace informací z členů pobytu
      $nazev= array();
      $_jmena= "";
      $clenu= 0;
      $fotek= 0;
      $o_fotek= $r_fotek= 0;
      $cleni= ""; $del= ""; $pecouni= 0;
      if ( count($p->cleni) ) {
        foreach ($p->cleni as $ido=>$s) {
          $o= $osoba[$ido];
          if ( $p->funkce==99 ) $pecouni++;
          # první 2 členi v rodině
          if ( !$_ido01 )
            $_ido01= $ido;
          elseif ( !$_ido02 )
            $_ido02= $ido;
          if ( $s->id_spolu ) {
            # spočítání fotek
//             if ( $o->fotka || $o->_fotky ) $fotek++;
            if ( $o->fotka ) $o_fotek++;
            if ( $o->_rfotky ) $r_fotek++;
            # spočítání účastníků kvůli platbě
            $clenu++;
            # první 2 členi na pobytu
            if ( !$_ido1 )
              $_ido1= $ido;
            elseif ( !$_ido2 )
              $_ido2= $ido;
            # výpočet jmen pobytu
            $_jmena.= str_replace(' ','-',trim($o->jmeno))." ";
            if ( !$idr ) {
              # výpočet názvu pobyt
              $prijmeni= $o->prijmeni;
              if ( !in_array(trim($prijmeni),$nazev) ) $nazev[]= trim($prijmeni);
            }
            # barva
            if ( !$s->_barva )
              $s->_barva= $s->id_tvori ? 1 : 2;               // barva: 1=člen rodiny, 2=nečlen
            # barva nerodinného pobytu
            $p_access|= $o->access;
          }
          else {
            # neúčastník
            $s->_barva= 0;
          }
          # ==> .. duplicita členů
          $keys= $dup= '';
          if ( $duplicity ) {
            $ret= ucast2_chain_oso($ido,$idr);
            $dup= $s->_dup= $ret->dup;
            $keys= $s->_keys= $ret->keys;
          }
          # ==> .. seznam členů pro browse_fill
          $vek= $o->narozeni!='0000-00-00' ? roku_k($o->narozeni,$akce->datum_od) : '?'; // výpočet věku
          $jmeno= $p->funkce==99 ? "{$o->prijmeni} {$o->jmeno}" : $o->jmeno ;
          $cleni.= "$del$ido~$keys~{$o->access}~$jmeno~$dup~$vek~{$s->id_tvori}~{$s->id_rodina}~{$s->role}";
          $del= $delim;
          # ==> .. rodiny a kmenová rodina
          $rody= explode(',',$o->_rody);
          $r= "-:0:nerodina"; $kmen= '';
          foreach($rody as $rod) {
            list($role,$ir,$access)= explode(':',$rod);
            $naz= $rodina[$ir]->nazev;
            $kmen= $kmen ? ($role=='a' || $role=='b' ? $naz : $kmen) : $naz;
//                                                 display("$o->jmeno/$role: $kmen ($naz,$ir)");
            $r.= ",$naz:$ir:$access";
          }
          $cleni.= "~$r";                                           // rody
          $id_kmen= $o->_kmen;
          $o->_kmen= "$kmen/$id_kmen";
          $cleni.= "~" . sql_date1($o->narozeni);                   // narozeniny d.m.r
          # doplnění textů z kmenové rodiny pro zobrazení rodinných adres (jako disabled)
//                                                 debug($o,"browse - o");
//                                                 debug($rodina[$id_kmen],"browse - kmen=$id_kmen");
          if ( !$o->adresa ) {
            $o->ulice= "®".$rodina[$id_kmen]->ulice;
            $o->psc=   "®".$rodina[$id_kmen]->psc;
            $o->obec=  "®".$rodina[$id_kmen]->obec;
          }
          if ( !$o->kontakt ) {
            $o->email=   "®".$rodina[$id_kmen]->emaily;
            $o->telefon= "®".$rodina[$id_kmen]->telefony;
          }
          # informace z osoba
          foreach($fos as $f=>$filler) {
            $cleni.= "~{$o->$f}";
          }
          # informace ze spolu
          foreach($fspo as $f=>$filler) {
            $cleni.= "~{$s->$f}";
          }
        }
      }
      # vynechání prázdného pobytu pečounů
      if ( $p->funkce==99 && !$pecouni ) {
        unset($pobyt[$idp]);
        continue;
      }
//                                                   debug($p->cleni,"členi");
//                                                   display($cleni);
      $_nazev= $p->funkce==99 ? "(pečouni)" : (
               $idr ? $rodina[$idr]->nazev : (
               $nazev ? implode(' ',$nazev) : '(pobyt bez členů)'));
      $_jmena= $p->funkce==99 ? "(celkem $pecouni)" : $_jmena;
      # zjištění dluhu
      $platba1234= $p->platba1 + $p->platba2 + $p->platba3 + $p->platba4;
      $p->c_suma= $platba1234 + $p->poplatek_d;
      $p->dluh= $p->funkce==99 ? 0 : (
                $akce->soubeh==1 && $akce->ma_cenik
        ? ( $p->c_suma == 0 ? 2 : ( $p->c_suma > $p->platba+$p->platba_d ? 1 : 0 ) )
        : ( $akce->ma_cenik
          ? ( $platba1234 == 0 ? 2 : ( $platba1234 > $p->platba ? 1 : 0) )
          : ( $akce->ma_cenu ? ( $clenu * $akce->cena > $p->platba ? 1 : 0) : 0 )
          ));
//                                                         if ($idp==15826) { debug($akce);debug($p,"platba1234=$platba1234"); }
      # pobyt I
      foreach($fpob1 as $fz=>$fp) { $z->$fz= $p->$fp; }
      # akce
      foreach($fakce as $fz=>$fp) { $z->$fz= $akce->$fp; }
      $z->_nazev= $_nazev;
      $z->_jmena= $_jmena;
      # ==> .. dokumenty
      #        ucast2 musí měnit složku aplikace
      $z->_docs= '';
      if ( $dokumenty ) $z->_docs.= drop_find("pobyt/","(.*)_$idp",'H:') ? 'd' : '';
      # ==> .. fotky
      if ( $r_fotek ) { $z->_docs.= 'F'; }
      if ( $o_fotek ) { $z->_docs.= 'f'; }
      # ==> .. duplicity
      if ( $duplicity ) {
        if ( $r_fotek || $o_fotek ) { $z->_docs.= ' '; }
        $del= "|";
        if ( $p->funkce==99 ) {
          $_dups= '';
          $_keys= '';
        }
        else {
          $ret= ucast2_chain_rod($idr);
          $_dups= $idr ? $ret->dup : '';
          $_keys= $ret->keys;
        }
        if ( $p->cleni ) foreach ($p->cleni as $ido=>$s) {
          if ( $s->_dup ) {
            $_dups.= $s->_dup;
            $_keys.= "$del$ido,{$s->_keys}";
            $del= ";";
          }
        }
        $z->_docs.= count_chars($_dups,3);
        $z->keys_rodina= $_keys;
      }
      # rodina
      foreach($frod as $fz=>$fr) { $z->$fz= $rodina[$idr]->$fr; }
      # ... oprava obarvení
      $z->r_access|= $p_access;
      # členové
      $z->r_cleni= $cleni;
      # pobyt II
      foreach($fpob2 as $fz=>$fp) { $z->$fz= $p->$fp; }
      $z->key_spolu= 0;
      $z->ido1= $_ido1 ?: $_ido01;
      $z->ido2= $_ido2; // ?: $_ido02;
      $z->datplatby= sql_date1($z->datplatby);                   // d.m.r
      $z->datplatby_d= sql_date1($z->datplatby_d);               // d.m.r
      # ok
      $zz[$idp]= $z;
      continue;
//     p_end: // varianta pro prázdný pobyt - definování položky _empty:1
//       $zz[$idp]= (object)array('key_pobyt'=>$idp,'_empty'=>1);
    }
    # 3. průchod - kompletace údajů mezi pobyty
    foreach ($pobyt as $idp=>$p) {
      # doplnění skupinek
      $s= $del= '';
      if ( ($sk= $p->skupina) && $skup[$sk]) {
        foreach($skup[$sk] as $ip) {
          $s.= "$del$ip~{$zz[$ip]->_nazev}";
          $del= $delim;
        }
      }
      if ( !isset($zz[$idp]) ) $zz[$idp]= (object)array();
      $zz[$idp]->skup= $s;
    }
    # případný výběr - zjednodušeno na show=[*,vzor]
    if ( $x->show ) foreach ( $x->show as $fld => $show) {
      $i= 0; $typ= $show->$i;
      $i= 1; $vzor= $show->$i;
      $beg= '^';
      switch ($typ) {
      case '%':
        $beg= '';
      case '*':
        $end= substr($vzor,-1)=='$' ?'$' : '.*';
        $not= substr($vzor,0,1)=='-';
        if ( $not ) $vzor= substr($vzor,1);
        $vzor= strtr($vzor,array('?'=>'.','*'=>'.*','$'=>''));
        foreach ($zz as $i=>$z) {
          $v= trim($z->$fld);
          $m= preg_match("/$beg$vzor$end/ui",$v);
//                                           display("/^$vzor$end/ui ? '$v' = $m");
          $off= $not && $m || !$not && !$m;
          if ( $off ) unset($zz[$i]);
        }
        break;
      case '=':
      case '#':
        foreach ($zz as $i=>$z) {
          $v= $z->$fld;
          $ok= $z->$fld == $vzor;
//                                           display("'$vzor'='$v' = $ok");
          if ( !$ok ) unset($zz[$i]);
        }
        break;
      default:
        display("show->{$fld}[0]='$typ' - N.Y.I");
      }
    }
    # ==> .. řazení
    if ( $x->order && count($zz)>0 ) {
      $test_clmn= substr($x->order,2);
      $test_asc= substr($x->order,0,1)=='a' ? 1 : -1;
      // výběr řazení: numerické | alfanumerické
      $numeric= in_array($test_clmn,array('skupina'));
      if ( $numeric ) {
//                                         display("usort $test_clmn $test_asc/numeric");
        usort($zz,function($a,$b) {
          global $test_clmn,$test_asc;
          $c= $a->$test_clmn == $b->$test_clmn ? 0 : ($a->$test_clmn > $b->$test_clmn ? 1 : -1);
          return $test_asc * $c;
        });
      }
      else {
        // alfanumerické je řazení podle operačního systému
        $asi_windows= preg_match('/^\w+\.ezer|192.168/',$_SERVER["SERVER_NAME"]);
        if ( $asi_windows ) {
          // asi Windows
          setlocale(LC_ALL, "cs_CZ.utf8","Czech");
          usort($zz,function($a,$b) {
            global $test_clmn,$test_asc;
            $ax= utf2win($a->$test_clmn,1); $bx= utf2win($b->$test_clmn,1);
            $c= $test_asc * strcoll($ax,$bx);
            return $c;
          });
        }
        else {
          // asi Linux
          setlocale(LC_ALL, "cs_CZ.utf8","Czech");
          usort($zz,function($a,$b) {
            global $test_clmn,$test_asc;
            $a0= mb_substr($a->$test_clmn,0,1);
            $b0= mb_substr($b->$test_clmn,0,1);
            if ( $a0=='(' ) {
              $c= -$test_asc;
            }
            elseif ( $b0=='(' ) {
              $c= $test_asc;
            }
            else {
              $c= $test_asc * strcoll($a->$test_clmn,$b->$test_clmn);
            }
            return $c;
          });
        }
      }
//                                                 debug($zz);
    }
    # předání pro browse
    $y->values= $zz;
    $y->from= 0;
    $y->cursor= 0;
    $y->rows= count($zz);
    $y->count= count($zz);
    $y->ok= 1;
    array_unshift($y->values,null);
  }
//                                                 debug($pobyt[21976],'pobyt');
//                                                 debug($rodina,'rodina');
//                                                 debug($osoba[3506],'osoba');
//                                                  debug($y->values);
  return $y;
}
# dekódování seznamu položek na pole ...x,y=z... na [...x=>x,y=>z...]
function ucast2_flds($fstr) {
  $fp= array();
  foreach(explode(',',$fstr) as $fzp) {
    list($fz,$f)= explode('=',$fzp);
    $fp[$fz]= $f ?: $fz ;
  }
  return $fp;
}
# ========================================================================================> . platby
# záložka Platba za akci
# -------------------------------------------------------------------------- akce2_platba_prispevek1
# členské příspěvky - zjištění zda jsou dospělí co jsou na pobytu členy a mají-li zaplaceno
function akce2_platba_prispevek1($id_pobyt) {  trace();
  $ret= (object)array('msg'=>'nejsou členy','platit'=>0);
  // jsou členy?
  $cleni= 0;
  $qp= "SELECT COUNT(*) AS _jsou, GROUP_CONCAT(jmeno) AS _jmena
        FROM pobyt AS p
        JOIN spolu AS s USING(id_pobyt)
        JOIN osoba AS o ON o.id_osoba=s.id_osoba
        LEFT JOIN dar AS d ON d.id_osoba=o.id_osoba
        WHERE id_pobyt=$id_pobyt AND ukon='c'
        GROUP BY id_pobyt ";
  $rp= mysql_qry($qp);
  if ( $rp && $p= mysql_fetch_object($rp) ) {
    $cleni= $p->_jsou;
    $ret->platit= 1;
    $ret->msg= $p->_jmena." jsou členy ";
  }
  if ( $cleni ) {
    $qp= "SELECT COUNT(*) AS _maji, MAX(dat_do) AS _do
          FROM pobyt AS p
          JOIN spolu AS s USING(id_pobyt)
          JOIN osoba AS o ON o.id_osoba=s.id_osoba
          LEFT JOIN dar AS d ON d.id_osoba=o.id_osoba
          WHERE id_pobyt=$id_pobyt AND ukon='p' AND YEAR(dat_do)>=YEAR(NOW()) ";
    $rp= mysql_qry($qp);
    if ( $rp && $p= mysql_fetch_object($rp)) {
      if ( $p->_maji ) {
        $ret->platit= 0;
        $ret->msg.= "a mají zaplaceno do ".sql_date1($p->_do);
      }
      else
        $ret->msg.= "a nemají letos zaplaceno";
    }
  }
  return $ret;
}
# -------------------------------------------------------------------------- akce2_platba_prispevek2
# členské příspěvky vložení platby do dar
function akce2_platba_prispevek2($id_pobyt) {  trace();
  $ret= (object)array('msg'=>'');
  $osoby= array();
  $prispevek= 100;
  $nazev= $rok= '';
  $values= $del= $jmena= '';
  $celkem= 0;
  $qp= "SELECT o.id_osoba,a.nazev,datum_do,YEAR(datum_do) AS _rok,jmeno
          FROM pobyt AS p
          JOIN akce AS a ON a.id_duakce=p.id_akce
          JOIN spolu AS s ON p.id_pobyt=s.id_pobyt
          JOIN osoba AS o ON s.id_osoba=o.id_osoba
          LEFT JOIN tvori AS t ON t.id_osoba=o.id_osoba
          WHERE p.id_pobyt=$id_pobyt AND t.role IN ('a','b') ";
  $rp= mysql_qry($qp);
  while ( $rp && $p= mysql_fetch_object($rp) ) {
    $osoba= $p->id_osoba;
    $rok= $p->_rok;
    $datum= $p->datum_do;
    $nazev= "$rok - {$p->nazev}";
    $values.= "$del($osoba,'p',$prispevek,'$datum','$rok-12-31','$nazev')";
    $jmena.= "$del{$p->jmeno}";
    $del= ', ';
    $celkem+= $prispevek;
  }
//                                                         display($values);
  $qi= "INSERT dar (id_osoba,ukon,castka,dat_od,dat_do,note) VALUES $values";
  $ri= mysql_qry($qi);
  // odpověď
  $ret->msg= "Za členy $jmena je potřeba vložit do pokladny $celkem,- Kč";
  return $ret;
}
# ====================================================================================> . autoselect
# ---------------------------------------------------------------------------------- akce2_auto_deti
# SELECT autocomplete - výběr z dětí na akci=par->akce
function akce2_auto_deti($patt,$par) {  #trace();
//                                                                 debug($par,$patt);
  $a= array();
  $limit= 20;
  $n= 0;
  if ( $par->patt!='whole' ) {
    $is= strpos($patt,' ');
    $patt= $is ? substr($patt,0,$is) : $patt;
  }
  // děti na akci
  $qry= "SELECT prijmeni, jmeno, s.id_osoba AS _key
         FROM osoba AS o
         JOIN spolu AS s USING(id_osoba)
         JOIN pobyt AS p USING(id_pobyt)
         LEFT JOIN tvori AS t ON s.id_osoba=t.id_osoba
         WHERE o.deleted='' AND prijmeni LIKE '$patt%' AND role='d' AND id_akce='{$par->akce}'
         ORDER BY prijmeni,jmeno LIMIT $limit";
  $res= mysql_qry($qry);
  while ( $res && $t= mysql_fetch_object($res) ) {
    if ( ++$n==$limit ) break;
    $key= $t->_key;
    $a[$key]= "{$t->prijmeni} {$t->jmeno}";
  }
  // obecné položky
  if ( !$n )
    $a[0]= "... žádné příjmení nezačíná '$patt'";
  elseif ( $n==$limit )
    $a[-999999]= "... a další";
//                                                                 debug($a,$patt);
  return $a;
}
# -------------------------------------------------------------------------------- ucast2_auto_jmeno
# test autocomplete
function ucast2_auto_jmeno($patt,$par) {  trace();
  $a= (object)array();
  $limit= 10;
  $n= 0;
  if ( !$patt ) {
    $a->{0}= "... zadejte jméno";
  }
  else {
    if ( $par->prefix ) {
      $patt= "{$par->prefix}$patt";
    }
    // zpracování vzoru
    $qry= "SELECT id_jmena AS _key,jmeno AS _value
           FROM _jmena
           WHERE jmeno LIKE '$patt%' ORDER BY jmeno LIMIT $limit";
    $res= mysql_qry($qry);
    while ( $res && $t= mysql_fetch_object($res) ) {
      if ( ++$n==$limit ) break;
      $a->{$t->_key}= $t->_value;
    }
    // obecné položky
    if ( !$n )
      $a->{0}= "... nic nezačíná $patt";
    elseif ( $n==$limit )
      $a->{999999}= "... a další";
  }
  return $a;
}
# ----------------------------------------------------------------------------- ucast2_auto_prijmeni
# SELECT autocomplete - výběr ze jmen rodin
function ucast2_auto_prijmeni($patt,$par) {  #trace();
  $a= (object)array();
  $limit= 11;
  $n= 0;
  if ( !$patt ) {
    $a->{0}= "... zadávejte jméno osoby";
  }
  else {
    if ( $par->prefix ) {
      $patt= "{$par->prefix}$patt";
    }
    // zpracování vzoru
    $qry= "SELECT prijmeni AS _value, id_osoba AS _key
           FROM osoba
           WHERE deleted='' AND prijmeni LIKE '$patt%'
           GROUP BY prijmeni
           ORDER BY prijmeni
           LIMIT $limit";
    $res= mysql_qry($qry);
    while ( $res && $t= mysql_fetch_object($res) ) {
      if ( ++$n==$limit ) break;
      $key= $t->_key;
      $a->{$t->_key}= $t->_value;
    }
    // obecné položky
    if ( !$n )
      $a->{0}= "... žádná osoba nezačíná '$patt'";
    elseif ( $n==$limit )
      $a->{999999}= "... a další";
  }
//                                                                 debug($a,$patt);
  return $a;
}
# ------------------------------------------------------------------------------- ucast2_auto_rodiny
# SELECT autocomplete - výběr ze jmen rodin
function ucast2_auto_rodiny($patt,$par) {  #trace();
  $a= (object)array();
  $limit= 11;
  $n= 0;
  if ( !$patt ) {
    $a->{0}= "... zadávejte jméno rodiny";
  }
  else {
    if ( $par->prefix ) {
      $patt= "{$par->prefix}$patt";
    }
    // zpracování vzoru
    $qry= "SELECT nazev AS _value, id_rodina AS _key
           FROM rodina
           WHERE deleted='' AND nazev LIKE '$patt%'
           GROUP BY nazev
           ORDER BY nazev
           LIMIT $limit";
    $res= mysql_qry($qry);
    while ( $res && $t= mysql_fetch_object($res) ) {
      if ( ++$n==$limit ) break;
      $key= $t->_key;
      $a->{$t->_key}= $t->_value;
    }
    // obecné položky
    if ( !$n )
      $a->{0}= "... žádná rodina nezačíná '$patt'";
    elseif ( $n==$limit )
      $a->{999999}= "... a další";
  }
//                                                                 debug($a,$patt);
  return $a;
}
# -------------------------------------------------------------------------------- akce2_auto_jmena1
# SELECT autocomplete - výběr z dospělých osob, pokud je par.deti=1 i z deti
function akce2_auto_jmena1($patt,$par) {  #trace();
  $a= array();
  $limit= 20;
  $dnes= date("Y-m-d");
  $n= 0;
  if ( $par->patt!='whole' ) {
    $is= strpos($patt,' ');
    $patt= $is ? substr($patt,0,$is) : $patt;
  }
  // osoby
  $AND= $par->deti ? '' : "AND (narozeni='0000-00-00' OR DATEDIFF('$dnes',narozeni)/365.2425>18)";
  $qry= "SELECT prijmeni, jmeno, id_osoba AS _key
         FROM osoba
         LEFT JOIN tvori USING(id_osoba)
         WHERE deleted='' AND concat(trim(prijmeni),' ',jmeno) LIKE '$patt%' AND prijmeni!='' $AND
         ORDER BY prijmeni,jmeno LIMIT $limit";
  $res= mysql_qry($qry);
  while ( $res && $t= mysql_fetch_object($res) ) {
    if ( ++$n==$limit ) break;
    $key= $t->_key;
    $a[$key]= "{$t->prijmeni} {$t->jmeno}";
  }
  // obecné položky
  if ( !$n )
    $a[0]= "... žádné příjmení nezačíná '$patt'";
  elseif ( $n==$limit )
    $a[-999999]= "... a další";
//                                                                 debug($a,$patt);
  return $a;
}
# ------------------------------------------------------------------------------- akce2_auto_jmena1L
# formátování autocomplete
function akce2_auto_jmena1L ($id_osoba) {  #trace();
  $osoba= array();
  $qry= "SELECT prijmeni, jmeno, id_osoba, YEAR(narozeni) AS rok, role,
           IF(adresa,o.ulice,r.ulice) AS ulice,
           IF(adresa,o.psc,r.psc) AS psc, IF(adresa,o.obec,r.obec) AS obec,
           IF(kontakt,o.telefon,r.telefony) AS telefon, IF(kontakt,o.email,r.emaily) AS email
         FROM osoba AS o
         LEFT JOIN tvori AS t USING(id_osoba)
         LEFT JOIN rodina AS r USING(id_rodina)
         WHERE id_osoba='$id_osoba'
         ORDER BY role";                                // preference 'a' či 'b'
  $res= mysql_qry($qry);
  if ( $res && $p= mysql_fetch_object($res) ) {
    $nazev= "$p->prijmeni $p->jmeno / $p->rok, $p->obec, $p->ulice, $p->email, $p->telefon";
    $osoba[]= (object)array('nazev'=>$nazev,'id'=>$id_osoba,'role'=>$p->role);
  }
//                                                                 debug($osoba,$id_akce);
  return $osoba;
}
# -------------------------------------------------------------------------------- akce2_auto_jmena3
# SELECT autocomplete - výběr z pečounů
# $par->cond může obsahovat dodatečnou podmínku např. 'funkce=99' pro zúžení na pečouny
function akce2_auto_jmena3($patt,$par) {  #trace();
  $a= array();
  $limit= 20;
  $n= 0;
  if ( $par->patt!='whole' ) {
    $is= strpos($patt,' ');
    $patt= $is ? substr($patt,0,$is) : $patt;
  }
  $AND= $par->cond ? "AND {$par->cond}" : '';
  // páry
  $qry= "SELECT prijmeni, jmeno, osoba.id_osoba AS _key
         FROM osoba
         JOIN spolu USING(id_osoba)
         JOIN pobyt USING(id_pobyt)
         WHERE deleted='' AND concat(trim(prijmeni),' ',jmeno) LIKE '$patt%' AND prijmeni!='' $AND
         GROUP BY id_osoba
         ORDER BY prijmeni,jmeno LIMIT $limit";
  $res= mysql_qry($qry);
  while ( $res && $t= mysql_fetch_object($res) ) {
    if ( ++$n==$limit ) break;
    $key= $t->_key;
    $a[$key]= "{$t->prijmeni} {$t->jmeno}";
  }
  // obecné položky
  if ( !$n )
    $a[0]= "... žádné příjmení nezačíná '$patt'";
  elseif ( $n==$limit )
    $a[-999999]= "... a další";
//                                                                 debug($a,$qry);
  return $a;
}
# ------------------------------------------------------------------------------- akce2_auto_jmena3L
# formátování autocomplete
function akce2_auto_jmena3L($id_osoba) {  #trace();
  $pecouni= array();
  // páry
  $qry= "SELECT id_osoba, prijmeni, jmeno, obec, email, telefon, YEAR(narozeni) AS rok
         FROM osoba AS o
         WHERE id_osoba='$id_osoba' ";
  $res= mysql_qry($qry);
  while ( $res && $p= mysql_fetch_object($res) ) {
    $nazev= "{$p->prijmeni} {$p->jmeno} / {$p->rok}, {$p->obec}, {$p->email}, {$p->telefon}";
    $pecouni[]= (object)array('nazev'=>$nazev,'id'=>$p->id_osoba);
  }
//                                                                 debug($pecouni,$id_akce);
  return $pecouni;
}
# --------------------------------------------------------------------------------- akce2_auto_akceL
# formátování autocomplete
function akce2_auto_akceL($id_akce) {  #trace();
  $pary= array();
  // páry na akci
  $qry= "SELECT
           IFNULL(r.nazev,o.prijmeni) as _nazev,r.id_rodina,
           GROUP_CONCAT(DISTINCT IF(tx.role='a',ox.jmeno,'')    SEPARATOR '') AS _muz,
           GROUP_CONCAT(DISTINCT IF(tx.role='a',ox.prijmeni,'') SEPARATOR '') AS _muzp,
           GROUP_CONCAT(DISTINCT IF(tx.role='a',ox.id_osoba,'') SEPARATOR '') AS _muz_id,
           GROUP_CONCAT(DISTINCT IF(tx.role='b',ox.jmeno,'')    SEPARATOR '') AS _zena,
           GROUP_CONCAT(DISTINCT IF(tx.role='b',ox.prijmeni,'') SEPARATOR '') AS _zenap,
           GROUP_CONCAT(DISTINCT IF(tx.role='b',ox.id_osoba,'') SEPARATOR '') AS _zena_id,
           r.obec
         FROM pobyt AS p
         JOIN spolu AS s ON p.id_pobyt=s.id_pobyt
         JOIN osoba AS o ON s.id_osoba=o.id_osoba
         LEFT JOIN tvori AS t ON t.id_osoba=o.id_osoba AND t.role!='d'
         LEFT JOIN rodina AS r USING(id_rodina)
         LEFT JOIN tvori AS tx ON r.id_rodina=tx.id_rodina
         LEFT JOIN osoba AS ox ON tx.id_osoba=ox.id_osoba
         WHERE id_akce=$id_akce
	 GROUP BY p.id_pobyt ORDER BY _nazev";
  $res= mysql_qry($qry);
  while ( $res && $p= mysql_fetch_object($res) ) {
    $nazev= $p->_muz && $p->_zena
      ? "{$p->_nazev} {$p->_muz} a {$p->_zena}"
      : ( $p->_muz ? "{$p->_muzp} {$p->_muz}" : "{$p->_zenap} {$p->_zena}" );
    $pary[]= (object)array(
      'nazev'=>$nazev,'muz'=>$p->_muz_id,'zen'=>$p->_zena_id,'rod'=>$p->id_rodina);
  }
//                                                                 debug($pary,$id_akce);
  return $pary;
}
# ---------------------------------------------------------------------------------- akce2_auto_pece
# SELECT autocomplete - výběr z akcí na kterých byli pečouni
function akce2_auto_pece($patt) {  #trace();
  $a= array();
  $limit= 20;
  $patt= substr($patt,-7,2)==' (' && substr($patt,-1)==')' ? substr($patt,0,-7) : $patt;
  $n= 0;
  // výběr akce
  $qry= "SELECT id_duakce AS _key,concat(nazev,' (',YEAR(datum_od),')') AS _value
         FROM akce
         JOIN pobyt ON akce.id_duakce=pobyt.id_akce
         WHERE nazev LIKE '$patt%' AND funkce=99
         ORDER BY datum_od DESC LIMIT $limit";
  $res= mysql_qry($qry);
  while ( $res && $t= mysql_fetch_object($res) ) {
    if ( ++$n==$limit ) break;
    $key= $t->_key;
    $a[$key]= $t->_value;
  }
  // obecné položky
  if ( !$n )
    $a[0]= "... žádná jméno akce nezačíná '$patt'";
  elseif ( $n==$limit )
    $a[-999999]= "... a další";
//                                                                 debug($a,$qry);
  return $a;
}
# ======================================================================================> . SKUPINKY
# --------------------------------------------------------------------------------- akce2_skup_check
# zjištění konzistence skupinek podle příjmení VPS/PPS
function akce2_skup_check($akce) {
  return akce2_skup_get($akce,1,$err);
}
# ------------------------------------------------------------------ akce2_skup_get
# zjištění skupinek podle příjmení VPS/PPS
function akce2_skup_get($akce,$kontrola,&$err,$par=null) { trace();
  global $VPS;
  $msg= array();
  $skupiny= array();
  $celkem= select('count(*)','pobyt',"id_akce=$akce AND funkce IN (0,1,2) AND skupina!=-1");
  $n= 0;
  $err= 0;
  $order= $all= array();
  $qry= "
      SELECT skupina,
        SUM(IF(funkce=2,1,0)) as _n_svps,
        SUM(IF(funkce=1,1,0)) as _n_vps,
        GROUP_CONCAT(DISTINCT IF(funkce=2,id_pobyt,'') SEPARATOR '') as _svps,
        GROUP_CONCAT(DISTINCT IF(funkce=1,id_pobyt,'') SEPARATOR '') as _vps,
        GROUP_CONCAT(DISTINCT id_pobyt) as _skupina
      FROM akce AS a
      JOIN pobyt AS p ON a.id_duakce=p.id_akce
      WHERE p.id_akce=$akce AND skupina>0
      GROUP BY skupina ";
  $res= mysql_qry($qry);
  while ( $res && ($s= mysql_fetch_object($res)) ) {
    if ( $s->_n_svps==1 || $s->_n_vps==1 ) {
      $skupina= array();
      if ( $par && $par->verze=='DS' ) {
        $qryu= "
          SELECT p.id_pobyt,skupina,nazev,pokoj,
            GROUP_CONCAT(o.id_osoba) as ids_osoba,
            GROUP_CONCAT(o.id_osoba) as id_osoba_m,
            GROUP_CONCAT(CONCAT(o.prijmeni,' ',o.jmeno,'')) AS _nazev
          FROM pobyt AS p
          JOIN spolu AS s USING(id_pobyt)
          JOIN osoba AS o ON s.id_osoba=o.id_osoba
          LEFT JOIN tvori AS t ON t.id_osoba=o.id_osoba
          LEFT JOIN rodina AS r USING(id_rodina)
          WHERE p.id_pobyt IN ({$s->_skupina})
          GROUP BY id_pobyt
          ORDER BY IF(funkce IN (1,2),1,2), nazev";
      }
      elseif ( $par && $par->verze=='MS' ) {
        $qryu= "
          SELECT p.id_pobyt,skupina,nazev,pokoj,
            GROUP_CONCAT(o.id_osoba) as ids_osoba,
            GROUP_CONCAT(o.id_osoba) as id_osoba_m,
            CONCAT(nazev,' ',GROUP_CONCAT(o.jmeno SEPARATOR ' a ')) AS _nazev
          FROM pobyt AS p
          JOIN spolu AS s USING(id_pobyt)
          JOIN osoba AS o ON s.id_osoba=o.id_osoba
          LEFT JOIN tvori AS t ON t.id_osoba=o.id_osoba AND id_rodina=i0_rodina
          LEFT JOIN rodina AS r USING(id_rodina)
          WHERE p.id_pobyt IN ({$s->_skupina}) AND t.role IN ('a','b')
          GROUP BY id_pobyt
          ORDER BY IF(funkce IN (1,2),1,2), nazev";
      }
      else {
        $qryu= "
          SELECT p.id_pobyt,skupina,nazev,pokoj,
            GROUP_CONCAT(DISTINCT IF(t.role IN ('a','b'),o.id_osoba,'')) as ids_osoba,
            GROUP_CONCAT(DISTINCT IF(t.role='a',o.id_osoba,'') SEPARATOR '') as id_osoba_m,
            CASE WHEN pouze=0 THEN
              CONCAT(nazev,' ',
                GROUP_CONCAT(DISTINCT IF(t.role='a',o.jmeno,'') SEPARATOR ''),' a ',
                GROUP_CONCAT(DISTINCT IF(t.role='b',o.jmeno,'') SEPARATOR ''))
            WHEN pouze=1 THEN
              CONCAT(
                GROUP_CONCAT(DISTINCT IF(t.role='a',o.prijmeni,'') SEPARATOR ''),' ',
                GROUP_CONCAT(DISTINCT IF(t.role='a',o.jmeno,'') SEPARATOR ''))
            WHEN pouze=2 THEN
              CONCAT(
                GROUP_CONCAT(DISTINCT IF(t.role='b',o.prijmeni,'') SEPARATOR ''),' ',
                GROUP_CONCAT(DISTINCT IF(t.role='b',o.jmeno,'') SEPARATOR ''))
            END AS _nazev
          FROM pobyt AS p
          JOIN spolu AS s USING(id_pobyt)
          JOIN osoba AS o ON s.id_osoba=o.id_osoba
          LEFT JOIN tvori AS t ON t.id_osoba=o.id_osoba
          LEFT JOIN rodina AS r USING(id_rodina)
          WHERE p.id_pobyt IN ({$s->_skupina})
          GROUP BY id_pobyt
          ORDER BY IF(funkce IN (1,2),1,2), nazev";
      }
      $resu= mysql_qry($qryu);
      while ( $resu && ($u= mysql_fetch_object($resu)) ) {
        $mark= '';
        if ( $par && $par->mark=='novic' ) {
          // minulé účasti
          $ids= $u->ids_osoba;
          $rqry= "SELECT count(*) as _pocet
                  FROM akce AS a
                  JOIN pobyt AS p ON a.id_duakce=p.id_akce
                  JOIN spolu AS s USING(id_pobyt)
                  WHERE a.druh=1 AND s.id_osoba IN ($ids) AND p.id_akce!=$akce";
          $rres= mysql_qry($rqry);
          if ( $rres && ($r= mysql_fetch_object($rres)) ) {
            $mark= $r->_pocet;
          }
          $mark= $mark==0 ? '* ' : '';
        }
        $u->_nazev= "$mark {$u->_nazev}";
        $skupina[$u->id_pobyt]= $u;
        $n++;
      }
      $vps= $s->_svps ? $s->_svps : $s->_vps;
      $skupiny[$vps]= $skupina;
      $all[]= $vps;
    }
    elseif ( $s->_vps || $s->_vps ) {
      $msg[]= "skupinka {$s->skupina} má nejednoznačnou $VPS";
      $err+= 2;
    }
    else {
      $msg[]= "skupinka {$s->skupina} nemá $VPS";
      $err+= 4;
    }
  }
  // řazení - v PHP nelze udělat
  if ( count($all) ) {
    $qryo= "SELECT GROUP_CONCAT(DISTINCT CONCAT(id_pobyt,'|',nazev) ORDER BY nazev) as _o
            FROM pobyt AS p
            JOIN spolu AS s USING(id_pobyt)
            JOIN osoba AS o ON s.id_osoba=o.id_osoba
            LEFT JOIN tvori AS t ON t.id_osoba=o.id_osoba
            LEFT JOIN rodina AS r USING(id_rodina)
            WHERE id_pobyt IN (".implode(',',$all).") ";
    $reso= mysql_qry($qryo);
    while ( $reso && ($o= mysql_fetch_object($reso)) ) {
      foreach (explode(',',$o->_o) as $pair) {
        list($id,$name)= explode('|',$pair);
        $order[$id]= $name;
      }
    }
  }
//                                                         debug($order,"order");
  $skup= array();
  foreach($order as $i=>$nam) {
    $skup[$i]= $skupiny[$i];
  }
//                                                         debug($skup,"skupiny");
  // redakce chyb
  if ( $celkem>$n ) {
    $msg[]= ($celkem-$n)." účastníků není zařazeno do skupinek";
    $err+= 1;
  }
  elseif ( $celkem<$n ) {
    $msg[]= ($n-$celkem)." je zařazeno do skupinek navíc (třeba hospodáři?)";
    $err+= 1;
  }
  if ( count($msg) && !$kontrola )
    fce_warning(implode(",<br>",$msg));
  elseif ( !count($msg) && $kontrola )
    $msg[]= "Vše je ok";
  // konec
  return $kontrola ? implode(",<br>",$msg) : $skup;
}

# --------------------------------------------------------------------------------- akce2_skup_renum
# přečíslování skupinek podle příjmení VPS/PPS
function akce2_skup_renum($akce) {
  $err= 0;
  $msg= '';
  $skupiny= akce2_skup_get($akce,0,$err);
  if ( $err>1 ) {
    $msg= "skupinky nejsou dobře navrženy - ještě je nelze přečíslovat";
  }
  else {
    $n= 1;
    foreach($skupiny as $ivps=>$skupina) {
      $cleni= implode(',',array_keys($skupina));
      $qryu= "UPDATE pobyt SET skupina=$n WHERE id_pobyt IN ($cleni) ";
      $resu= mysql_qry($qryu);
//                                                         display("$n: $qryu");
      $n++;
    }
    $msg= "bylo přečíslováno $n skupinek";
  }
  return $msg;
}
# =======================================================================================> . pomocné
# ------------------------------------------------------------------------------- akce2_osoba_rodiny
# vrátí rodiny dané osoby ve formátu pro select (název:id_rodina;...)
function akce2_osoba_rodiny($id_osoba) {
  $rodiny= select1("GROUP_CONCAT(CONCAT(nazev,':',id_rodina) SEPARATOR ',')",
    "rodina JOIN tvori USING(id_rodina)","id_osoba=$id_osoba");
  $rodiny= "-:0".($rodiny ? ',' : '').$rodiny;
                                                display("akce_osoba_rodiny($id_osoba)=$rodiny");
  return $rodiny;
}
# ------------------------------------------------------------------------------ akce2_pobyt_rodinny
# definuje pobyt jako rodinný
function akce2_pobyt_rodinny($id_pobyt,$id_rodina) { trace();
  query("UPDATE pobyt SET i0_rodina=$id_rodina WHERE id_pobyt=$id_pobyt");
  return 1;
}
# ---------------------------------------------------------------------------------- akce2_save_role
# zapíše roli - je to netypická číselníková položka definovaná jako VARCHAR(1)
function akce2_save_role($id_tvori,$role) { //trace();
  return mysql_qry("UPDATE tvori SET role='$role' WHERE id_tvori=$id_tvori");
}
# ------------------------------------------------------------------------------------ akce2_osoba2x
# ASK volané z formuláře _osoba2x při onchange.adresa a onchange.kontakt
# v ret vrací o_kontakt, r_kontakt, o_adresa, r_adresa
# pokud je id_osoba=0 tzn. jde o novou osobu, vrací se v r_* údaje pro id_rodina
function akce2_osoba2x($id_osoba,$id_rodina=0) { trace();
  $rets= "o_kontakt,r_kontakt,o_adresa,r_adresa";
  $adresa= "ulice,psc,obec,stat,noadresa";
  $kontakt= "telefon,email,nomail";         // rodina s -y na konci
  $kontakty= "telefony,emaily,nomaily";
  $ret= (object)array();
  if ( $id_osoba ) {
    $k= sql_query("
      SELECT IFNULL(SUBSTR(
        (SELECT MIN(CONCAT(role,id_rodina))
          FROM tvori AS ot JOIN rodina AS r USING (id_rodina) WHERE ot.id_osoba=o.id_osoba
        ),2),0) AS id_rodina
      FROM osoba AS o
      WHERE o.id_osoba='$id_osoba'
      GROUP BY o.id_osoba");
    $o= sql_query("SELECT $adresa,$kontakt FROM osoba WHERE id_osoba='$id_osoba'");
    $r= sql_query("SELECT $adresa,$kontakty FROM rodina WHERE id_rodina='$k->id_rodina'");
  //                                                         debug($k,"kmen");
  //                                                         debug($r,"rodina");
  //                                                         debug($o,"osoba ".(empty($o)?'e':'f'));
    foreach(explode(',',$rets) as $f) {
      $ret->$f= (object)array();
    }
    foreach(explode(',',$adresa) as $f) {
      $ret->o_adresa->$f= empty($o) ? '' : $o->$f;
      $ret->r_adresa->$f= empty($r) ? '' : ($f=='noadresa'||$f=='stat'?'':'®').$r->$f;
    }
    foreach(explode(',',$kontakt) as $f) { $fy= $f.'y';
      $ret->o_kontakt->$f= empty($o) ? '' : $o->$f;
      $ret->r_kontakt->$f= empty($r) ? '' : ($f=='nomail'?'':'®').$r->$fy;
    }
  }
  elseif ( $id_rodina ) {
    $o= (object)array();
    $r= sql_query("SELECT $adresa,$kontakty FROM rodina WHERE id_rodina='$id_rodina'");
  }
  foreach(explode(',',$rets) as $f) {
    $ret->$f= (object)array();
  }
  foreach(explode(',',$adresa) as $f) {
    $ret->o_adresa->$f= empty($o) ? '' : $o->$f;
    $ret->r_adresa->$f= empty($r) ? '' : ($f=='noadresa'||$f=='stat'?'':'®').$r->$f;
  }
  foreach(explode(',',$kontakt) as $f) { $fy= $f.'y';
    $ret->o_kontakt->$f= empty($o) ? '' : $o->$f;
    $ret->r_kontakt->$f= empty($r) ? '' : ($f=='nomail'?'':'®').$r->$fy;
  }
//                                                         debug($ret,"akce2__osoba2x");
  return $ret;
}
# ------------------------------------------------------------------------------------ akce2_ido2idp
# ASK
# získání pobytu účastníka na akci
function akce2_ido2idp($id_osoba,$id_akce) {
  $idp= select("id_pobyt","spolu JOIN pobyt USING (id_pobyt)",
    "spolu.id_osoba=$id_osoba AND id_akce=$id_akce");
  return $idp;
}
# ----------------------------------------------------------------------------- ucast2_rodina_access
# ASK přidání access členům rodiny
function ucast2_rodina_access($idr,$access) {
  $qo= mysql_qry("SELECT id_osoba,o.access,r.access AS r_access FROM rodina AS r
                  LEFT JOIN tvori USING (id_rodina) LEFT JOIN osoba AS o USING (id_osoba)
                  WHERE id_rodina=$idr");
  while ( $qo && ($o= mysql_fetch_object($qo)) ) {
    $r_access= $o->r_access;
    # úprava access členů
    if ( $access!=$o->access) {
      ezer_qry("UPDATE",'osoba',$o->id_osoba,array(
        (object)array('fld'=>'access', 'op'=>'u','val'=>$access,'old'=>$o->access)
      ));
    }
  }
  # úprava access rodiny
  if ( $access!=$o->access) {
    ezer_qry("UPDATE",'rodina',$idr,array(
      (object)array('fld'=>'access', 'op'=>'u','val'=>$access,'old'=>$r_access)
    ));
  }
  return 1;
}
# ----------------------------------------------------------------------------- ucast2_pridej_rodinu
# ASK přidání rodinného pobytu do akce (pokud ještě nebyla rodina přidána)
function ucast2_pridej_rodinu($id_akce,$id_rodina) { trace();
  $ret= (object)array('idp'=>0,'msg'=>'');
  // kontrola nepřítomnosti
  $jsou= select1('COUNT(*)','pobyt',"id_akce=$id_akce AND i0_rodina=$id_rodina");
  if ( $jsou ) { // už jsou na akci
    $ret->msg= "... rodina již je přihlášena na akci";
  }
  else {
    // vložení nového pobytu
    $rod= $pouze==0 && $info->rod ? $info->rod : 0;
    // přidej k pobytu
    $ret->idp= ezer_qry("INSERT",'pobyt',0,array(
      (object)array('fld'=>'id_akce',   'op'=>'i','val'=>$id_akce),
      (object)array('fld'=>'i0_rodina', 'op'=>'i','val'=>$id_rodina)
    ));
  }
end:
//                                                 debug($ret,'ucast2_pridej_rodinu');
  return $ret;
}
# ------------------------------------------------------------------------------ ucast2_pridej_osobu
# ASK přidání osoby k pobytu, případně k rodině a upraví access
#   je-li zadáno access, opraví je v OSOBA
#   není-li zadán pobyt, vytvoří nový, přidá SPOLU - hlídá duplicity
#   je-li zadána rodina, přidá TVORI s rolí - hlídá duplicity
# spolupracuje s číselníky: ms_akce_s_role,ms_akce_dite_kat
#   podle stáří resp. role odhadne hodnotu SPOLU.s_role a SPOLU.dite_kat
#  vrací
#   ret.spolu,tvori - klíče vytvořených záznamů stejnojmenných tabulek nebo 0
#   ret.pobyt - parametr nebo nové vytvořený pobyt
function ucast2_pridej_osobu($ido,$access,$ida,$idp,$idr=0,$role=0) { trace();
  $ret= (object)array('pobyt'=>$idp,'spolu'=>0,'tvori'=>0,'msg'=>'');
  list($narozeni,$old_access)= select("narozeni,access","osoba","id_osoba=$ido");
  # případné vytvoření pobytu
  if ( !$idp ) {
    $idp= $ret->pobyt= ezer_qry("INSERT",'pobyt',0,array(
    (object)array('fld'=>'id_akce',   'op'=>'i','val'=>$ida),
    (object)array('fld'=>'i0_rodina', 'op'=>'i','val'=>$idr)
  ));
  }
  # přidání k pobytu
  $je= select("COUNT(*)","pobyt JOIN spolu USING(id_pobyt)","id_akce=$ida AND id_osoba=$ido");
  if ( $je ) { $ret->msg.= "osoba už na této akci je"; goto end; }
  // pokud na akci ještě není, zjisti pro děti (<18 let) s_role a dite_kat
  $datum_od= select("datum_od","akce","id_duakce=$ida");
  $vek= roku_k($narozeni,$datum_od);
  $kat= 0; $srole= 1;                                         // default= účastník, nedítě
  // odhad typu účasti podle stáří a role
  if     ( $role=='p' )                         { $kat= 0; $srole= 5; }   // osob.peč.
  elseif ( $vek>=18 || $narozeni=='0000-00-00') { $kat= 0; $srole= 1; }   // účastník
  elseif ( (!$role || $role=='d') && $vek>=17 ) { $kat= 1; $srole= 2; }   // dítě - A|G
  elseif ( (!$role || $role=='d') && $vek>=13 ) { $kat= 1; $srole= 2; }   // dítě - A
  elseif ( (!$role || $role=='d') && $vek>=3 )  { $kat= 3; $srole= 2; }   // dítě - C
  elseif ( (!$role || $role=='d') && $vek>=2 )  { $kat= 5; $srole= 2; }   // dítě - E
  elseif ( (!$role || $role=='d') && $vek>=0 )  { $kat= 6; $srole= 3; }   // dítě - F
  // přidej k pobytu
  $ret->spolu= ezer_qry("INSERT",'spolu',0,array(
    (object)array('fld'=>'id_pobyt', 'op'=>'i','val'=>$idp),
    (object)array('fld'=>'id_osoba', 'op'=>'i','val'=>$ido),
    (object)array('fld'=>'s_role',   'op'=>'i','val'=>$srole),
    (object)array('fld'=>'dite_kat', 'op'=>'i','val'=>$kat)
  ));
  # přidání do rodiny
  if ( $idr && $role ) {
    $je= select("COUNT(*)","tvori","id_rodina=$idr AND id_osoba=$ido");
    if ( $je ) { $ret->msg.= "osoba už v této rodině je"; goto end; }
    // pokud v rodině ještě není, přidej
    $ret->tvori= ezer_qry("INSERT",'tvori',0,array(
      (object)array('fld'=>'id_osoba', 'op'=>'i','val'=>$ido),
      (object)array('fld'=>'id_rodina','op'=>'i','val'=>$idr),
      (object)array('fld'=>'role',     'op'=>'i','val'=>"'$role'")
    ));
  }
  # úprava access, je-li třeba
  if ( $access && $access!=$old_access) {
    ezer_qry("UPDATE",'osoba',$ido,array(
      (object)array('fld'=>'access', 'op'=>'u','val'=>$access,'old'=>$old_access)
    ));
  }
end:
//                                                 debug($ret,'ucast2_pridej_osobu / $vek $kat $srole');
  return $ret;
}
/** ==========================================================================================> TISK */
# ------------------------------------------------------------------------------------ tisk2_sestava
# generování sestav
function tisk2_sestava($akce,$par,$title,$vypis,$export=false) { trace();
  return 0 ? 0
     : ( $par->typ=='p'    ? tisk2_sestava_pary($akce,$par,$title,$vypis,$export)
     : ( $par->typ=='P'    ? akce2_sestava_pobyt($akce,$par,$title,$vypis,$export)
     : ( $par->typ=='j'    ? tisk2_sestava_lidi($akce,$par,$title,$vypis,$export)
     : ( $par->typ=='vs'   ? akce2_strava_pary($akce,$par,$title,$vypis,$export)  // bez náhradníků
     : ( $par->typ=='vv'   ? tisk2_text_vyroci($akce,$par,$title,$vypis,$export)
     : ( $par->typ=='vi'   ? akce2_text_prehled($akce,$par,$title,$vypis,$export)
     : ( $par->typ=='ve'   ? akce2_text_eko($akce,$par,$title,$vypis,$export)
     : ( $par->typ=='vn'   ? akce2_sestava_noci($akce,$par,$title,$vypis,$export)
     : ( $par->typ=='vp'   ? akce2_vyuctov_pary($akce,$par,$title,$vypis,$export)
     : ( $par->typ=='vp2'  ? akce2_vyuctov_pary2($akce,$par,$title,$vypis,$export)
     : ( $par->typ=='vj'   ? akce2_stravenky($akce,$par,$title,$vypis,$export)
     : ( $par->typ=='vjp'  ? akce2_stravenky($akce,$par,$title,$vypis,$export)
     : ( $par->typ=='d'    ? akce2_sestava_pecouni($akce,$par,$title,$vypis,$export)
     : ( $par->typ=='ss'   ? tisk2_pdf_plachta($akce,$export)
     : ( $par->typ=='s0'   ? tisk2_pdf_plachta0($export)
     : ( $par->typ=='skpl' ? akce2_plachta($akce,$par,$title,$vypis,$export)
     : ( $par->typ=='skpr' ? akce2_skup_hist($akce,$par,$title,$vypis,$export)
     : ( $par->typ=='skti' ? akce2_skup_tisk($akce,$par,$title,$vypis,$export)
     : ( $par->typ=='12'   ? akce2_jednou_dvakrat($akce,$par,$title,$vypis,$export)
     : ( $par->typ=='sd'   ? akce2_skup_deti($akce,$par,$title,$vypis,$export)
     : ( $par->typ=='cz'   ? akce2_cerstve_zmeny($akce,$par,$title,$vypis,$export)
     : (object)array('html'=>"<i>Tato sestava zatím není převedena do nové verze systému,
          <a href='mailto:martin@smidek.eu'>upozorněte mě</a>, že ji už potřebujete</i>")
     )))))))))))))))))))));
}
# =======================================================================================> . seznamy
# ------------------------------------------------------------------------------- tisk2_sestava_pary
# generování sestavy pro účastníky $akce - rodiny
#   $fld = seznam položek s prefixem
#   $cnd = podmínka
function tisk2_sestava_pary($akce,$par,$title,$vypis,$export=false) { trace();
  global $EZER;
  $result= (object)array();
  $typ= $par->typ;
  $tit= $par->tit;
  $fld= $par->fld;
  $cnd= $par->cnd ? $par->cnd : 1;
  $hav= $par->hav ? "HAVING {$par->hav}" : '';
  $ord= $par->ord ? $par->ord : "a _nazev";
  $fil= $par->filtr ?: null;
  $html= '';
  $href= '';
  $n= 0;
  // číselníky
  $c_ubytovani= map_cis('ms_akce_ubytovan','zkratka');  $c_ubytovani[0]= '?';
  $c_prednasi= map_cis('ms_akce_prednasi','hodnota');  $c_ubytovani[0]= '?';
  $c_platba= map_cis('ms_akce_platba','zkratka');  $c_ubytovani[0]= '?';
  // dekódování parametrů
  $tits= explode(',',$tit);
  $flds= explode(',',$fld);
  $cond= $cnd;
  // získání dat - podle $kdo
  $clmn= array();
  $expr= array();       // pro výrazy
  # diskuse souběhu: 0=normální akce, 1=hlavní akce, 2=souběžná akce
  list($hlavni,$soubezna)= select("a.id_hlavni,IFNULL(s.id_duakce,0)",
      "akce AS a LEFT JOIN akce AS s ON s.id_hlavni=a.id_duakce",
      "a.id_duakce=$akce");
  $soubeh= $soubezna ? 1 : ( $hlavni ? 2 : 0);
  $browse_par= (object)array(
    'cmd'=>'browse_load','cond'=>"$cnd AND p.id_akce=$akce",'having'=>$hav,'order'=>$ord,
    'sql'=>"SET @akce:=$akce,@soubeh:=$soubeh,@app:='{$EZER->options->root}';");
  $y= ucast2_browse_ask($browse_par,true);
  # rozbor výsledku browse/ask
  $i_adresa=         15;
  $i_key_spolu=      39;
  $i_spolu_note=     43;
  $i_osoba_jmeno=     3;
  $i_osoba_prijmeni= 12;
  $i_osoba_role=      8;
  $i_osoba_note=     36;
  $i_osoba_kontakt=  20;
  $i_osoba_telefon=  21;
  $i_osoba_email=    23;
  array_shift($y->values);
  foreach ($y->values as $x) {
//     $test_p= 43593;
//     if ( !in_array($x->key_pobyt,array($test_p)) ) continue; else debug($x,"$x->key_pobyt");
    // aplikace neosobních filtrů
    if ( $fil && $fil->r_umi ) {
      $umi= explode(',',$x->r_umi);
      if ( !in_array($fil->r_umi,$umi) ) continue;
    }
    if ( $fil && $fil->ucasti_ms ) {
      $ru= mysql_qry("SELECT COUNT(*) as _pocet FROM akce AS a
              JOIN pobyt AS p ON a.id_duakce=p.id_akce
              WHERE a.druh=1 AND p.i0_rodina={$x->key_rodina} AND a.datum_od<='{$x->datum_od}'");
      $xu= mysql_fetch_object($ru);
      if ( $xu->_pocet!=$fil->ucasti_ms ) continue;
    }
    // pokračování, pokud záznam vyhověl filtrům
    $n++;
    # rozbor osobních údajů: adresa nebo základní kontakt se získá 3 způsoby
    # 1. první osoba má osobní údaje - ty se použijí
    # 2. první osoba má rodinné údaje, které se shodují s i0_rodina - použijí se ty z i0_rodina
    # 3. první osoba má rodinné údaje, které se neshodují s i0_rodina - použijí se tedy její
    $telefony= $emaily= array();
    if ( $x->r_telefony ) $telefony[]= trim($x->r_telefony,",; ");
    if ( $x->r_emaily )   $emaily[]=   trim($x->r_emaily,",; ");
    # rozšířené spojení se získá slepením údajů všech účastníků
    $xs= explode('≈',$x->r_cleni);
//                                                         debug($xs,"xs");
    $pocet= 0;
    $spolu_note= "";
    $osoba_note= "";
    $rodice= array();
//                                                         if ( $x->key_pobyt==32146 ) debug($x);
    foreach ($xs as $i=>$xi) {
      $o= explode('~',$xi);
//                                                         if ( $x->key_pobyt==32146 ) debug($o,"xi/$i");
      if ( $o[$i_key_spolu] ) {
        $pocet++;
        $jmeno= str_replace(' ','-',$o[$i_osoba_jmeno]);
        if ( $o[$i_spolu_note] ) $spolu_note.= " + $jmeno:$o[$i_spolu_note]";
        if ( $o[$i_osoba_note] ) $osoba_note.= " + $jmeno:$o[$i_osoba_note]";
        if ( $o[$i_osoba_kontakt] && $o[$i_osoba_telefon] )
          $telefony[]= trim($o[$i_osoba_telefon],",; ");
        if ( $o[$i_osoba_kontakt] && $o[$i_osoba_email] )
          $emaily[]= trim($o[$i_osoba_email],",; ");
        if ( $x->key_rodina ) {
          if ( $o[$i_osoba_role]=='a' || $o[$i_osoba_role]=='b' ) {
            $rodice[$o[$i_osoba_role]]['jmeno']= trim($o[$i_osoba_jmeno]);
            $rodice[$o[$i_osoba_role]]['prijmeni']= trim($o[$i_osoba_prijmeni]);
          }
        }
        else {
            $rodice['a']['jmeno']= trim($o[$i_osoba_jmeno]);
            $rodice['a']['prijmeni']= trim($o[$i_osoba_prijmeni]);
        }
      }
    }
//                                                         debug($rodice);
    $o= explode('~',$xs[0]);
    // show: adresa, ulice, psc, obec, stat, kontakt, telefon, nomail, email
    $io= $i_adresa;
    $adresa=  $o[$io++]; $ulice= $o[$io++]; $psc= $o[$io++]; $obec= $o[$io++]; $stat= $o[$io++];
    $kontakt= $o[$io++]; $telefon= $o[$io++]; $nomail= $o[$io++]; $email= $o[$io++];
    // úpravy
    $emaily= count($emaily) ? implode(', ',$emaily).';' : '';
    $email=  trim($kontakt ? $email   : substr($email,$r),",; ") ?: $emaily;
    $emaily= $emaily ?: $email;
    $telefony= count($telefony) ? implode(', ',$telefony).';' : '';
    $telefon=  trim($kontakt ? $telefon : substr($telefon,$r),",; ") ?: $telefony;
    $telefony= $telefony ?: $telefon;
//                                                         if ( $x->key_pobyt==22141 )
//                                                         display("email=$email, emaily=$emaily, telefon=$telefon, telefony=$telefony");
    // přepsání do výstupního pole
    $clmn[$n]= array();
    $r= 0; // 1 ukáže bez (r)
    foreach($flds as $f) {          // _pocet,poznamka,note
      switch ($f) {
      case '^id_pobyt': $c= $x->key_pobyt; break;
      case 'prijmeni':  $c= $x->_nazev; break;
      case 'jmena':     $c= $x->_jmena; break;
      case 'rodice':    $c= count($rodice)==2 && strpos($x->_nazev,'-')
                          ? "{$rodice['a']['jmeno']} {$rodice['a']['prijmeni']}
                             a {$rodice['b']['jmeno']} {$rodice['b']['prijmeni']}" : (
        count($rodice)==2 ? "{$rodice['a']['jmeno']} a {$rodice['b']['jmeno']} {$x->_nazev}" : (
             $rodice['a'] ? "{$rodice['a']['jmeno']} {$rodice['a']['prijmeni']}" : (
             $rodice['b'] ? "{$rodice['b']['jmeno']} {$rodice['b']['prijmeni']}"
                          : $x->_nazev )));
                        break;
      case 'jmena2':    $c= explode(' ',$x->_jmena);
                        $c= $c[0].' '.$c[1]; break;
      case 'ulice':     $c= $adresa  ? $ulice   : substr($ulice,$r); break;
      case 'psc':       $c= $adresa  ? $psc     : substr($psc,$r);   break;
      case 'obec':      $c= $adresa  ? $obec    : substr($obec,$r);  break;
      case 'stat':      $c= $adresa  ? $stat    : substr($stat,$r);
                        if ( $c=='CZ' ) $c= '';
                        break;
      case 'telefon':   $c= $telefon;  break;
      case 'telefony':  $c= $telefony; break;
      case 'email':     $c= $email;  break;
      case 'emaily':    $c= $emaily; break;
      case '_pocet':    $c= $pocet; break;
      case 'poznamka':  $c= $x->p_poznamka . ($spolu_note ?: ''); break;
      case 'note':      $c= $x->r_note . ($osoba_note ?: ''); break;
      default:          $c= $x->$f; break;
      }
      $clmn[$n][$f]= $c;
    }
  }
//                                         debug($clmn,"sestava pro $akce,$typ,$fld,$cnd");
  return tisk2_table($tits,$flds,$clmn,$export);
}
# -------------------------------------------------------------------------------- tisk_sestava_lidi
# generování sestavy pro účastníky $akce - jednotlivce
#   $fld = seznam položek s prefixem
#   $cnd = podmínka
#   $par->sel = seznam id_pobyt
function tisk2_sestava_lidi($akce,$par,$title,$vypis,$export=false) { trace();
  $result= (object)array();
  $typ= $par->typ;
  $tit= $par->tit;
  $fld= $par->fld;
  $cnd= $par->cnd;
  $hav= $par->hav ? "HAVING {$par->hav}" : '';
  $ord= $par->ord ? $par->ord : "IF(funkce<=2,1,funkce),o.prijmeni,o.jmeno";
  $html= '';
  $href= '';
  $n= 0;
  // dekódování parametrů
  $tits= explode(',',$tit);
  $flds= explode(',',$fld);
  // číselníky
  $funkce= map_cis('ms_akce_funkce','zkratka');  $funkce[0]= '';
  $pfunkce= map_cis('ms_akce_pfunkce','zkratka');  $pfunkce[0]= '?';
  $dieta= map_cis('ms_akce_dieta','zkratka');  $dieta[0]= '';
  $dite_kat= map_cis('ms_akce_dite_kat','zkratka');  $dite_kat[0]= '?';
  // načtení ceníku pro dite_kat, pokud se chce _poplatek
  if ( strpos($fld,"_poplatek") ) {
    $soubezna= select("id_duakce","akce","id_hlavni=$akce");
    akce2_nacti_cenik($soubezna,$cenik,$html);
  }
  // získání dat - podle $kdo
  $clmn= array();
  $expr= array();       // pro výrazy
  // případné zvláštní řazení
  switch ($ord) {
  case '_zprava':
    $ord= "CASE WHEN _vek<6 THEN 1 WHEN _vek<18 THEN 2 WHEN _vek<26 THEN 3 ELSE 9 END,prijmeni";
    break;
  }
  // případné omezení podle selected na seznam pobytů
  if ( $par->sel && $par->selected ) {
                                                display("i.par.sel=$par->sel");
    $cnd.= " AND p.id_pobyt IN ($par->selected)";
  }
  // data akce
  $r_fld= "id_rodina,nazev,ulice,psc,obec,stat,note,emaily,telefony,spz";
  $qry=  "
    SELECT
      p.pouze,p.poznamka,p.platba,p.funkce,
      o.prijmeni,o.jmeno,o.narozeni,o.rc_xxxx,o.note,o.obcanka,o.clen,o.dieta,
      IFNULL(r2.id_rodina,r1.id_rodina) AS id_rodina,
      IFNULL(r2.nazev,r1.nazev) AS r_nazev,
      IFNULL(r2.spz,r1.spz) AS r_spz,
      IF(o.adresa,o.ulice,IFNULL(r2.ulice,r1.ulice)) AS ulice,
      IF(o.adresa,o.psc,IFNULL(r2.psc,r1.psc)) AS psc,
      IF(o.adresa,o.obec,IFNULL(r2.obec,r1.obec)) AS obec,
      IF(o.adresa,o.stat,IFNULL(r2.stat,r1.stat)) AS stat,
      IF(o.kontakt,o.telefon,IFNULL(r2.telefony,r1.telefony)) AS telefony,
      IF(o.kontakt,o.email,IFNULL(r2.emaily,r1.emaily)) AS emaily,
      s.poznamka AS s_note,s.pfunkce,s.dite_kat,
      IFNULL(r2.note,r1.note) AS r_note,
      IFNULL(r2.role,r1.role) AS r_role,
      ROUND(DATEDIFF(a.datum_od,o.narozeni)/365.2425,1) AS _vek,
      (SELECT GROUP_CONCAT(prijmeni,' ',jmeno)
        FROM akce JOIN pobyt ON id_akce=akce.id_duakce
        JOIN spolu ON spolu.id_pobyt=pobyt.id_pobyt
        JOIN osoba ON osoba.id_osoba=spolu.id_osoba
        WHERE spolu.pecovane=o.id_osoba AND id_akce=$akce) AS _chuva,
      (SELECT CONCAT(prijmeni,' ',jmeno) FROM osoba
        WHERE s.pecovane=osoba.id_osoba) AS _chovany
    FROM pobyt AS p
      JOIN spolu AS s USING(id_pobyt)
      JOIN osoba AS o ON o.id_osoba=s.id_osoba AND o.deleted=''
      -- r1=rodina, kde je dítětem
      LEFT JOIN ( SELECT id_osoba,role,$r_fld
        FROM tvori JOIN rodina USING(id_rodina))
      AS r1 ON r1.id_osoba=o.id_osoba AND r1.role NOT IN ('a','b')
      -- r2=rodina, kde je rodičem
      LEFT JOIN ( SELECT id_osoba,role,$r_fld
        FROM tvori JOIN rodina USING(id_rodina))
      AS r2 ON r2.id_osoba=o.id_osoba AND r2.role IN ('a','b')
      -- akce
      JOIN akce AS a ON a.id_duakce=p.id_akce
    WHERE p.id_akce=$akce AND $cnd
      GROUP BY o.id_osoba $hav
      ORDER BY $ord";
  $res= mysql_qry($qry);
  while ( $res && ($x= mysql_fetch_object($res)) ) {
    $n++;
    $clmn[$n]= array();
    // doplnění počítaných položek
    $x->narozeni_dmy= sql_date1($x->narozeni);
    foreach($flds as $f) {
      switch ($f) {
      case '1':                                                       // 1
        $clmn[$n][$f]= 1;
        break;
      case 'dieta':                                                   // osoba: dieta
        $clmn[$n][$f]= $dieta[$x->$f];
        break;
      case 'dite_kat':                                                // osoba: kategorie dítěte
        $clmn[$n][$f]= $dite_kat[$x->$f];
        break;
      case '_1':
        $clmn[$n][$f]= 1;
        break;
      case '_ar_note':                                                // k akci: rodina/osoba
        $clmn[$n][$f]= "{$x->poznamka} / {$x->s_note}";
        break;
      case '_tr_note':                                                // trvalá: rodina/osoba
        $clmn[$n][$f]= "{$x->r_note} / {$x->note}";
        break;
      case '_a_note':                                                 // k akci: osoba
        $clmn[$n][$f]= $x->s_note;
//         $clmn[$n][$f]= $x->s_note ? $x->$f.' / '.$x->s_note : $x->$f;
        break;
      case '_t_note':                                                 // trvalá: osoba
        $clmn[$n][$f]= $x->note;
//         $clmn[$n][$f]= $x->s_note ? $x->$f.' / '.$x->s_note : $x->$f;
        break;
      case '_narozeni6':
        $nar= $x->narozeni;
        $clmn[$n][$f]= "'".substr($nar,2,2).substr($nar,5,2).substr($nar,8,2);
        break;
      case '_ymca':
        $clmn[$n][$f]= $x->clen ? $x->clen : '';
        break;
      case '_funkce':
        $clmn[$n][$f]= $funkce[$x->funkce];
        break;
      case 'pfunkce':
        $pf= $x->$f;
        $clmn[$n][$f]= !$pf ? 'skupinka' : (
            $pf==4 ? 'pomocný p.' : (
            $pf==5 || $pf==95 ? "os.peč. pro: {$x->_chovany}" : (
            $pf==8 ? 'skupina G' : (
            $pf==92 ? "os.peč. je: {$x->_chuva}" : '?'))));
        break;
      case '_poplatek':                                               // poplatek/dítě dle číselníku
        $kat= $dite_kat[$x->dite_kat];             // $cenik[p$kat|d$kat]= {c:cena,txt:popis}
        $clmn[$n][$f]= $kat=="?" ? "?" : $cenik["p$kat"]->c - $cenik["d$kat"]->c;
        break;
        // ---------------------------------------------------------- pro YMCA v ČR
      case '_jmenoY':
        $clmn[$n][$f]= "$x->jmeno $x->prijmeni";
        break;
      case '_adresaY':
        $clmn[$n][$f]= "$x->ulice, $x->psc, $x->obec";
        break;
      case '_narozeniY':
        $clmn[$n][$f]= $x->narozeni;
        break;
      default: $clmn[$n][$f]= $x->$f;
      }
    }
  }
//                                         debug($clmn,"sestava pro $akce,$typ,$fld,$cnd");
  return tisk2_table($tits,$flds,$clmn,$export);
}
# ---------------------------------------------------------------------------- akce2_sestava_pecouni
# generování sestavy pro účastníky $akce - pečouny
#   $fld = seznam položek s prefixem
#   $cnd = podmínka
function akce2_sestava_pecouni($akce,$par,$title,$vypis,$export=false) { trace();
  $result= (object)array();
  $typ= $par->typ;
  $tit= $par->tit;
  $fld= $par->fld;
  $cnd= $par->cnd;
  $ord= $par->ord ? $par->ord : "CONCAT(o.prijmeni,' ',o.jmeno)";
  $html= '';
  $href= '';
  $n= 0;
  // získání dat - podle $kdo
  $clmn= array();
  $expr= array();       // pro výrazy
  _akce2_sestava_pecouni($clmn,$akce,$fld,$cnd,$ord);
  // dekódování parametrů
  $flds= explode(',',$fld);
  $tits= explode(',',$tit);
  return tisk2_table($tits,$flds,$clmn,$export);
}
# ------------------------------------------------------------------------------ akce2_sestava_pobyt
# generování sestavy pro účastníky $akce se stejným pobytem
#   $fld = seznam položek s prefixem
#   $cnd = podmínka
function akce2_sestava_pobyt($akce,$par,$title,$vypis,$export=false) { trace();
  function otoc($s) {
    mb_internal_encoding("UTF-8");
    $s= mb_strtolower($s);
    $x= '';
    for ($i= mb_strlen($s); $i>=0; $i--) {
      $xi= mb_substr($s,$i,1);
      $xi= mb_strtoupper($xi);
      $x.= $xi;
    }
    return $x;
  }
  $result= (object)array();
  $typ= $par->typ;
  $tit= $par->tit;
  $fld= $par->fld;
  $cnd= $par->cnd ? $par->cnd : 1;
  $hav= $par->hav ? "HAVING {$par->hav}" : '';
  $ord= $par->ord ? $par->ord : "nazev";
  $html= '';
  $href= '';
  $n= 0;
  // číselníky
  $c_ubytovani= map_cis('ms_akce_ubytovan','zkratka');  $c_ubytovani[0]= '?';
  $c_prednasi= map_cis('ms_akce_prednasi','hodnota');  $c_ubytovani[0]= '?';
  $c_platba= map_cis('ms_akce_platba','zkratka');  $c_ubytovani[0]= '?';
  // dekódování parametrů
  $tits= explode(',',$tit);
  $flds= explode(',',$fld);
  $cond= $cnd;
  // získání dat - podle $kdo
  $clmn= array();
  $expr= array();       // pro výrazy
  // data akce
  $qry=  "SELECT
            r.nazev as nazev,p.pouze as pouze,p.poznamka,p.platba,p.datplatby,p.zpusobplat,
            COUNT(o.id_osoba) AS _pocet,
            SUM(IF(t.role IN ('a','b'),1,0)) AS _pocetA,
            GROUP_CONCAT(o.prijmeni ORDER BY t.role DESC) as _prijmeni,
            GROUP_CONCAT(o.jmeno    ORDER BY t.role DESC) as _jmena,
            GROUP_CONCAT(o.email    ORDER BY t.role DESC SEPARATOR ';') as _emaily,
            GROUP_CONCAT(o.telefon  ORDER BY t.role DESC SEPARATOR ';') as _telefony,
            ( SELECT count(DISTINCT cp.id_pobyt) FROM pobyt AS cp
              JOIN akce AS ca ON ca.id_duakce=cp.id_akce
              JOIN spolu AS cs ON cp.id_pobyt=cs.id_pobyt
              JOIN osoba AS co ON cs.id_osoba=co.id_osoba
              LEFT JOIN tvori AS ct ON ct.id_osoba=co.id_osoba
              LEFT JOIN rodina AS cr ON cr.id_rodina=ct.id_rodina
              WHERE ca.druh=1 AND cr.id_rodina=r.id_rodina ) AS _ucasti,
            SUM(IF(t.role='d',1,0)) as _deti,
            r.ulice,r.psc,r.obec,r.telefony,r.emaily,r.note/* AS r_note*/,p.skupina,
            p.ubytovani,p.budova,p.pokoj,
            p.luzka,p.kocarek,p.pristylky,p.strava_cel,p.strava_pol,
            GROUP_CONCAT(IFNULL((SELECT CONCAT(osoba.jmeno,' ',osoba.prijmeni)
              FROM pobyt
              JOIN spolu ON spolu.id_pobyt=pobyt.id_pobyt
              JOIN osoba ON osoba.id_osoba=spolu.id_osoba
              WHERE pobyt.id_akce='$akce' AND spolu.pecovane=o.id_osoba),'') SEPARATOR ' ') AS _chuvy,
            prednasi
          FROM pobyt AS p
          JOIN spolu AS s USING(id_pobyt)
          JOIN osoba AS o ON s.id_osoba=o.id_osoba
          LEFT JOIN tvori AS t ON t.id_osoba=o.id_osoba
          -- LEFT JOIN rodina AS r USING(id_rodina)
          LEFT JOIN rodina AS r ON r.id_rodina=IF(p.i0_rodina,p.i0_rodina,t.id_rodina)
          WHERE p.id_akce='$akce' AND $cnd
          GROUP BY id_pobyt $hav
          ORDER BY $ord";
  $res= mysql_qry($qry);
  while ( $res && ($x= mysql_fetch_object($res)) ) {
    $x->prijmeni= $x->pouze==0 ? $x->nazev : $x->_prijmeni;
    $x->jmena=    $x->_jmena;
    $x->_pocet=   $x->_pocet;
    // podle číselníku
    $x->ubytovani= $c_ubytovani[$x->ubytovani];
    $x->prednasi= $c_prednasi[$x->prednasi];
    $x->zpusobplat= $c_platba[$x->zpusobplat];
    // další
    $n++;
    $clmn[$n]= array();
    foreach($flds as $f) {
      switch ($f) {
      case '_pocetD':   $clmn[$n][$f]= $x->_pocet - $x->_pocetA; break;
      case '=par':      $clmn[$n][$f]= "{$x->prijmeni} {$x->jmena}"; break;
      // fonty: ISOCTEUR, Tekton Pro
      case '=pozpatku': $clmn[$n][$f]= otoc("{$x->prijmeni} {$x->jmena}"); break;
      default:          $clmn[$n][$f]= $x->$f; break;
      }
    }
  }
//                                         debug($clmn,"sestava pro $akce,$typ,$fld,$cnd");
  return tisk2_table($tits,$flds,$clmn,$export);
}
# --------------------------------------------------------------------------- _akce2_sestava_pecouni
# výpočet pro generování sestavy pro účastníky $akce - pečouny a pro statistiku
function _akce2_sestava_pecouni(&$clmn,$akce,$fld='_skoleni,_sluzba,_reflexe',$cnd=1,$ord=1) {
  $flds= explode(',',$fld);
  // číselníky                 akce.druh = ms_akce_typ:pečovatelé=7,kurz=1
  $pfunkce= map_cis('ms_akce_pfunkce','zkratka');  $pfunkce[0]= '?';
  // data akce
  $rel= '';
  $rel= "-YEAR(narozeni)";
  $qry= " SELECT o.prijmeni,o.jmeno,o.narozeni,o.rc_xxxx,o.ulice,o.psc,o.obec,o.telefon,o.email,
            id_osoba,s.skupinka as skupinka,s.pfunkce,
            IF(o.note='' AND s.poznamka='','',CONCAT(o.note,' / ',s.poznamka)) AS _poznamky,
            GROUP_CONCAT(IF(xa.druh=7 AND MONTH(xa.datum_od)<=7,YEAR(xa.datum_od)$rel,'')
              ORDER BY xa.datum_od DESC SEPARATOR ' ') AS _skoleni,
            GROUP_CONCAT(IF(xa.druh=1,YEAR(xa.datum_od)$rel,'')
              ORDER BY xa.datum_od DESC SEPARATOR ' ') AS _sluzba,
            GROUP_CONCAT(IF(xa.druh=7 AND MONTH(xa.datum_od)>7,YEAR(xa.datum_od)$rel,'')
              ORDER BY xa.datum_od DESC SEPARATOR ' ') AS _reflexe,
            YEAR(narozeni)+18 AS _18
          FROM pobyt AS p
          JOIN spolu AS s USING (id_pobyt)
          JOIN osoba AS o USING (id_osoba)
          JOIN akce  AS a ON a.id_duakce=p.id_akce
          JOIN spolu AS xs USING (id_osoba)
          JOIN pobyt AS xp ON xp.id_pobyt=xs.id_pobyt -- AND xp.funkce=99
          JOIN akce  AS xa ON xa.id_duakce=xp.id_akce AND YEAR(xa.datum_od)<=YEAR(a.datum_od)
          -- JOIN join_akce AS xg ON xg.id_akce=xp.id_akce
          WHERE (p.funkce=99 OR s.pfunkce IN (4,5,8)) AND p.id_akce='$akce' AND $cnd
          GROUP BY id_osoba
          ORDER BY $ord";
  $res= mysql_qry($qry);
  while ( $res && ($x= mysql_fetch_object($res)) ) {
    $n++;
    $clmn[$n]= array();
    foreach($flds as $f) {
      // sumáře
      switch ($f) {
      case 'pfunkce':   $clmn[$n][$f]= $pfunkce[$x->$f]; break;
      case '^id_osoba': $clmn[$n][$f]= $x->id_osoba; break;
      case '_skoleni':
      case '_sluzba':
      case '_reflexe':
        $lst= preg_split("/\s+/",$x->$f, -1, PREG_SPLIT_NO_EMPTY);
        $lst= array_unique($lst);
        $clmn[$n][$f]= ' '.implode(' ',$lst);
        //$clmn[$n][$f]= ' '.trim(str_replace('  ',' ',$x->$f));
        break;
      default:          $clmn[$n][$f]= $x->$f;
      }
    }
  }
}
# ========================================================================================> . strava
# ---------------------------------------------------------------------------------- akce2_stravenky
# generování stravenek účastníky $akce - rodinu ($par->typ=='vj') resp. pečouny ($par->typ=='vjp')
#   $cnd = podmínka
# počítané položky
#   manzele = rodina.nazev muz, zena a děti
# generované vzorce
#   platit = součet předepsaných plateb
# výstupy
#   note = pro pečouny seznam jmen, pro které nejsou stravenky, protože nemají funkci
#          (tzn. asi nejsou na celý pobyt)
function akce2_stravenky($akce,$par,$title,$vypis,$export=false) { trace();
//                                                         debug($par,"akce_stravenky($akce,,$title,$vypis,$export)");
  $ord= $par->ord ? $par->ord : "IF(funkce<=2,1,funkce),IF(pouze=0,r.nazev,o.prijmeni)";
  $result= (object)array();
  $cnd= $par->cnd;
  $note= $delnote= $html= $href= '';
  $n= 0;
  // zjištění sloupců (0=ne)
  $tit= $par->typ=='vjp' ? "Pečovatel:25" : "Manželé:25";
  $fld= "manzele";
  $dny= array('ne','po','út','st','čt','pá','so');
  $dny= array('n','p','ú','s','č','p','s');
  $qrya= "SELECT strava_oddo,datum_od,datum_do,DATEDIFF(datum_do,datum_od) AS _dnu
            ,DAYOFWEEK(datum_od)-1 AS _den1
          FROM akce WHERE id_duakce=$akce ";
  $resa= mysql_qry($qrya);
  if ( $resa && ($a= mysql_fetch_object($resa)) ) {
//                                                         debug($a,"akce {$a->_dnu}");
    $oo= $a->strava_oddo ? $a->strava_oddo : 'vo';
    $nd= $a->_dnu;
    $den1= sql2stamp($a->datum_od);             // začátek akce ve formátu mktime
    for ($i= 0; $i<=$nd; $i++) {
      $deni= mktime(0, 0, 0, date("n", $den1), date("j", $den1) + $i, date("Y", $den1));
      $den= $dny[($a->_den1+$i)%7].date('d',$deni).' ';
      if ( $i>0 || $oo[0]=='s' ) {
        $tit.= ",{$den}sc:4:r:s";
        $tit.= ",{$den}sp:4:r:s";
        $fld.= ",{$den}sc,{$den}sp";
      }
      if ( $i>0 && $i<$nd
        || $i==0   && ($oo[0]=='s' || $oo[0]=='o')
        || $i==$nd && ($oo[1]=='o' || $oo[1]=='v') ) {
        $tit.= ",{$den}oc:4:r:s";
        $tit.= ",{$den}op:4:r:s";
        $fld.= ",{$den}oc,{$den}op";
      }
      if ( $i<$nd || $oo[1]=='v' ) {
        $tit.= ",{$den}vc:4:r:s";
        $tit.= ",{$den}vp:4:r:s";
        $fld.= ",{$den}vc,{$den}vp";
      }
    }
//                                                         display($tit);
  }
  // dekódování parametrů
  $tits= explode(',',$tit);
  $flds= explode(',',$fld);
  $cond= $cnd;
  // získání dat - podle $kdo
  $clmn= array();       // pro hodnoty
  $expr= array();       // pro výrazy
  $suma= array();       // pro sumy sloupců id:::s
  $fmts= array();       // pro formáty sloupců id::f:
  for ($i= 0; $i<count($tits); $i++) {
    $idw= $tits[$i];
    $fld= $flds[$i];
    list($id,$w,$f,$sum)= explode(':',$idw);
    if ( $sum=='s' ) $suma[$fld]= 0;
    if ( isset($f) ) $fmts[$fld]= $f;
  }
  // data akce
  $akce_data= (object)array();
  $dny= array('ne','po','út','st','čt','pá','so');
  if ( $par->typ=='vjp' )
    $qry="SELECT o.prijmeni,o.jmeno,s.pfunkce,YEAR(datum_od) AS _rok,
            a.nazev AS akce_nazev, YEAR(a.datum_od) AS akce_rok, a.misto AS akce_misto
          FROM pobyt AS p
          JOIN akce  AS a ON p.id_akce=a.id_duakce
          JOIN spolu AS s USING(id_pobyt)
          JOIN osoba AS o ON s.id_osoba=o.id_osoba
          WHERE p.id_akce='$akce' AND p.funkce=99 AND s_rodici=0
          ORDER BY o.prijmeni,o.jmeno";
  else
//     $qry="SELECT r.nazev as nazev,strava_cel,strava_pol,cstrava_cel,cstrava_pol,p.pouze,
//             GROUP_CONCAT(DISTINCT IF(t.role='a',o.prijmeni,'') SEPARATOR '') as prijmeni_m,
//             GROUP_CONCAT(DISTINCT IF(t.role='a',o.jmeno,'')    SEPARATOR '') as jmeno_m,
//             GROUP_CONCAT(DISTINCT IF(t.role='b',o.prijmeni,'') SEPARATOR '') as prijmeni_z,
//             GROUP_CONCAT(DISTINCT IF(t.role='b',o.jmeno,'')    SEPARATOR '') as jmeno_z,
//             a.nazev AS akce_nazev, YEAR(a.datum_od) AS akce_rok, a.misto AS akce_misto
//           FROM pobyt AS p
//           JOIN akce  AS a ON p.id_akce=a.id_duakce
//           JOIN spolu AS s USING(id_pobyt)
//           JOIN osoba AS o ON s.id_osoba=o.id_osoba
//           LEFT JOIN tvori AS t ON t.id_osoba=o.id_osoba
//           LEFT JOIN rodina AS r USING(id_rodina)
//           WHERE p.id_akce='$akce' AND $cond
//           GROUP BY id_pobyt
//           ORDER BY $ord";
    $qry="SELECT strava_cel,strava_pol,cstrava_cel,cstrava_pol,p.pouze,
            IF(p.i0_rodina,CONCAT(r.nazev,' ',
              GROUP_CONCAT(po.jmeno ORDER BY role SEPARATOR ' a '))
             ,GROUP_CONCAT(DISTINCT CONCAT(pso.prijmeni,' ',pso.jmeno)
               ORDER BY role SEPARATOR ' a ')) as _jm,
            a.nazev AS akce_nazev, YEAR(a.datum_od) AS akce_rok, a.misto AS akce_misto
          FROM pobyt AS p
          JOIN akce AS a ON p.id_akce=a.id_duakce
          LEFT JOIN rodina AS r ON r.id_rodina=p.i0_rodina
          JOIN spolu AS ps ON ps.id_pobyt=p.id_pobyt
          LEFT JOIN tvori AS pt ON pt.id_rodina=p.i0_rodina
            AND role IN ('a','b') AND ps.id_osoba=pt.id_osoba
          LEFT JOIN osoba AS po ON po.id_osoba=pt.id_osoba
          JOIN osoba AS pso ON pso.id_osoba=ps.id_osoba
          WHERE p.id_akce='$akce' AND $cond
          GROUP BY p.id_pobyt
          ORDER BY _jm";
//   $qry.=  " LIMIT 1";
  $res= mysql_qry($qry);
  // stravenky - počty po dnech
  $str= array();  // $strav[kdo][den][jídlo][typ]=počet   kdo=jména,den=datum,jídlo=s|o|v, typ=c|p
  // s uvážením $oo='sv' - první jídlo prvního dne a poslední jídlo posledního dne
  $jidlo= array('s','o','v');
  $xjidlo= array('s'=>0,'o'=>1,'v'=>2);
  $jidlo_1= $xjidlo[$oo[0]];
  $jidlo_n= $xjidlo[$oo[1]];
  while ( $res && ($x= mysql_fetch_object($res)) ) {
    if ( $par->typ=='vjp' && $x->pfunkce==0 && $x->_rok>2012 ) {        // !!!!!!!!!!!!!! od roku 2013
      $note.= "$delnote{$x->prijmeni} {$x->jmeno}";
      $delnote= ", ";
      continue;
    }
    $n++;
    $akce_data->nazev= $x->akce_nazev;
    $akce_data->rok=   $x->akce_rok;
    $akce_data->misto= $x->akce_misto;
    $str_kdo= array();
    $clmn[$n]= array();
    $stravnik=
         $par->typ=='vjp' ? "{$x->prijmeni} {$x->jmeno}"
       : ($x->pouze==1 ? "{$x->prijmeni_m} {$x->jmeno_m}"
       : ($x->pouze==2 ? "{$x->prijmeni_z} {$x->jmeno_z}"
       : "{$x->nazev} {$x->jmeno_m} a {$x->jmeno_z}"));
    $stravnik= $par->typ=='vjp' ? "{$x->prijmeni} {$x->jmeno}" : $x->_jm;
    $clmn[$n]['manzele']= $stravnik;
    // stravy
    $sc= $par->typ=='vjp' ? 1 : $x->strava_cel;
    $sp= $x->strava_pol;
    $csc= $x->cstrava_cel;
    $csp= $x->cstrava_pol;
    $k= 0;
    for ($i= 0; $i<=$nd; $i++) {
      // projdeme dny akce
      $str_den= array();
      $j0= $i==0 ? $jidlo_1 : 0;
      $jn= $i==$nd ? $jidlo_n : 2;
      if ( 0<=$j0 && $j0<=$jn && $jn<=2 ) {
        for ($j= $j0; $j<=$jn; $j++) {
          $str_cel= $csc ? $csc[3*$i+$j] : $sc;
          $str_pol= $csp ? $csp[3*$i+$j] : $sp;
          if ( $str_cel ) $str_den[$jidlo[$j]]['c']= $str_cel;
          if ( $str_pol ) $str_den[$jidlo[$j]]['p']= $str_pol;
        }
      }
      else
        fce_error("Tisk stravenek selhal: chybné meze stravování v nastavení akce: $j0-$jn");
      if ( $i>0 || $oo[0]=='s' ) {
        // snídaně od druhého dne pobytu nebo začíná-li pobyt snídaní
        $k++; $suma[$flds[$k]]+= $clmn[$n][$flds[$k]]= $csc ? $csc[3*$i+0] : $sc;
        $k++; $suma[$flds[$k]]+= $clmn[$n][$flds[$k]]= $csp ? $csp[3*$i+0] : $sp;
      }
      if ( $i>0 && $i<$nd
        || $i==0   && ($oo[0]=='s' || $oo[0]=='o')
        || $i==$nd && ($oo[1]=='o' || $oo[1]=='v') ) {
        // obědy od druhého do předposledního dne akce nebo prvního či posledního dne
        $k++; $suma[$flds[$k]]+= $clmn[$n][$flds[$k]]= $csc ? $csc[3*$i+1] : $sc;
        $k++; $suma[$flds[$k]]+= $clmn[$n][$flds[$k]]= $csp ? $csp[3*$i+1] : $sp;
      }
      if ( $i<$nd || $oo[1]=='v' ) {
        // večeře do předposledního dne akce nebo končí-li pobyt večeří
        $k++; $suma[$flds[$k]]+= $clmn[$n][$flds[$k]]= $csc ? $csc[3*$i+2] : $sc;
        $k++; $suma[$flds[$k]]+= $clmn[$n][$flds[$k]]= $csp ? $csp[3*$i+2] : $sp;
      }
      if ( count($str_den) ) {
        $mkden= mktime(0, 0, 0, date("n", $den1), date("j", $den1)+$i, date("Y", $den1));
        $den= "<b>{$dny[date('w',$mkden)]}</b> ".date("j.n",$mkden);
        $str_kdo[$den]= $str_den;
      }
    }
    $kdo= $par->typ=='vjp' ? "{$x->prijmeni}|{$x->jmeno}"
        : ($x->pouze==1 ? "{$x->prijmeni_m}|{$x->jmeno_m}"
        : ($x->pouze==2 ? "{$x->prijmeni_z}|{$x->jmeno_z}"
        : "{$x->nazev}|{$x->jmeno_m} a {$x->jmeno_z}"));
    $kdo= $stravnik;
    $str[$kdo]= $str_kdo;
  }
//                                                         debug($str,"stravenky");
//                                                         debug($suma,"sumy");
  // titulky
  foreach ($tits as $idw) {
    list($id)= explode(':',$idw);
    $ths.= "<th>$id</th>";
  }
  // data
  foreach ($clmn as $i=>$c) {
    $tab.= "<tr>";
    foreach ($c as $id=>$val) {
      $style= akce2_sestava_td_style($fmts[$id]);
      $tab.= "<td$style>$val</td>";
    }
    $tab.= "</tr>";
  }
  // sumy
  $sum= '';
  if ( count($suma)>0 ) {
    $sum.= "<tr>";
    foreach ($flds as $f) {
      $val= isset($suma[$f]) ? $suma[$f] : '';
      $sum.= "<th style='text-align:right'>$val</th>";
    }
    $sum.= "</tr>";
  }
  $result->html= "Seznam má $n řádků<br><br>";
  $result->html.= "<div class='stat'><table class='stat'><tr>$ths</tr>$sum$tab</table></div>";
  $result->html.= "</br>";
  $result->href= $href;
  $result->tab= $str;
  $result->akce= $akce_data;
  $result->note= $note ? "(bez $note, kteří nemají vyjasněnou funkci)" : '';
  return $result;
}
# -------------------------------------------------------------------------------- akce2_strava_pary
# generování sestavy přehledu strav pro účastníky $akce - páry
#   $cnd = podmínka
#   $id_pobyt -- je-li udáno, počítá se jen pro tento jeden pobyt (jedněch účastníků)
# počítané položky
#   manzele = rodina.nazev muz a zena
# generované vzorce
#   platit = součet předepsaných plateb
function akce2_strava_pary($akce,$par,$title,$vypis,$export=false,$id_pobyt=0) { trace();
  $ord= $par->ord ? $par->ord : "IF(funkce<=2,1,funkce),IF(pouze=0,r.nazev,o.prijmeni)";
  $result= (object)array();
  $cnd= 1;
  $html= '';
  $href= '';
  $n= 0;
  // zjištění sloupců (0=ne)
  $tit= "Manželé a pečouni:25";  // bude opraveno podle skutečnosti před exportem
  $fld= "manzele";
  $dny= array('ne','po','út','st','čt','pá','so');
  $dny= array('n','p','ú','s','č','p','s');
  $qrya= "SELECT strava_oddo,datum_od,datum_do,DATEDIFF(datum_do,datum_od) AS _dnu
            ,DAYOFWEEK(datum_od)-1 AS _den1
          FROM akce WHERE id_duakce=$akce ";
  $resa= mysql_qry($qrya);
  if ( $resa && ($a= mysql_fetch_object($resa)) ) {
//                                                         debug($a,"akce {$a->_dnu}");
    $oo= $a->strava_oddo ? $a->strava_oddo : 'vo';
    $nd= $a->_dnu;
    for ($i= 0; $i<=$nd; $i++) {
      $den= $dny[($a->_den1+$i)%7].date('d',sql2stamp($a->datum_od)+$i*60*60*24).' ';
      if ( $i>0 || $oo[0]=='s' ) {
        $tit.= ",{$den}sc:4:r:s";
        $tit.= ",{$den}sp:4:r:s";
        $fld.= ",{$den}sc,{$den}sp";
      }
      if ( $i>0 && $i<$nd
        || $i==0   && ($oo[0]=='s' || $oo[0]=='o')
        || $i==$nd && ($oo[1]=='o' || $oo[1]=='v') ) {
        $tit.= ",{$den}oc:4:r:s";
        $tit.= ",{$den}op:4:r:s";
        $fld.= ",{$den}oc,{$den}op";
      }
      if ( $i<$nd || $oo[1]=='v' ) {
        $tit.= ",{$den}vc:4:r:s";
        $tit.= ",{$den}vp:4:r:s";
        $fld.= ",{$den}vc,{$den}vp";
      }
    }
//                                                         display($tit);
  }
  // dekódování parametrů
  $tits= explode(',',$tit);
  $flds= explode(',',$fld);
  $cond= $cnd;
  // získání dat - podle $kdo
  $clmn= array();       // pro hodnoty
  $expr= array();       // pro výrazy
  $suma= array();       // pro sumy sloupců id:::s
  $fmts= array();       // pro formáty sloupců id::f:
  for ($i= 0; $i<count($tits); $i++) {
    $idw= $tits[$i];
    $fld= $flds[$i];
    list($id,$w,$f,$sum)= explode(':',$idw);
    if ( $sum=='s' ) $suma[$fld]= 0;
    if ( isset($f) ) $fmts[$fld]= $f;
  }
  // pokud není id_pobyt tak vyloučíme náhradníky
  $cond.= $id_pobyt ? " AND p.id_pobyt=$id_pobyt" : " AND funkce NOT IN (9)";
  $jsou_pecouni= false;
  // data akce
  $res= tisk2_qry('pobyt_dospeli_ucastnici',
    "COUNT(*) AS _pocet,funkce,pfunkce,strava_cel,strava_pol,cstrava_cel,cstrava_pol",
    "p.id_akce='$akce' AND IF(funkce=99,s_rodici=0 AND pfunkce,1) AND $cond",
    "","_jm");
  while ( $res && ($x= mysql_fetch_object($res)) ) {
//                                                         debug($x,"hodnoty");
    $n++;
    $clmn[$n]= array();
    if ( $x->funkce==99 && $x->pfunkce ) {
      // stravy pro pečouny - mají jednotně celou stravu - (s_rodici=0,pfunkce!=0 viz SQL)
      $jsou_pecouni= true;
      $clmn[$n]['manzele']= 'PEČOUNI';
      $sc= $x->_pocet;
      $sp= 0;
      $k= 0;
      for ($i= 0; $i<=$nd; $i++) {
        if ( $i>0 || $oo[0]=='s' ) {
          $k++; $suma[$flds[$k]]+= $clmn[$n][$flds[$k]]= $sc;
          $k++; $suma[$flds[$k]]+= $clmn[$n][$flds[$k]]= $sp;
        }
        if ( $i>0 && $i<$nd
          || $i==0   && ($oo[0]=='s' || $oo[0]=='o')
          || $i==$nd && ($oo[1]=='o' || $oo[1]=='v') ) {
          $k++; $suma[$flds[$k]]+= $clmn[$n][$flds[$k]]= $sc;
          $k++; $suma[$flds[$k]]+= $clmn[$n][$flds[$k]]= $sp;
        }
        if ( $i<$nd || $oo[1]=='v' ) {
          $k++; $suma[$flds[$k]]+= $clmn[$n][$flds[$k]]= $sc;
          $k++; $suma[$flds[$k]]+= $clmn[$n][$flds[$k]]= $sp;
        }
      }
    }
    elseif ( $x->funkce!=99 ) {
      // stravy pro manžele
      $clmn[$n]['manzele']= $x->_jm;
//             $x->pouze==1 ? "{$x->prijmeni_m} {$x->jmeno_m}"
//          : ($x->pouze==2 ? "{$x->prijmeni_z} {$x->jmeno_z}"
//          : "{$x->nazev} {$x->jmeno_m} a {$x->jmeno_z}");
      $sc= $x->strava_cel;
      $sp= $x->strava_pol;
      $csc= $x->cstrava_cel;
      $csp= $x->cstrava_pol;
      $k= 0;
      for ($i= 0; $i<=$nd; $i++) {
        if ( $i>0 || $oo[0]=='s' ) {
          $k++; $suma[$flds[$k]]+= $clmn[$n][$flds[$k]]= $csc ? $csc[3*$i+0] : $sc;
          $k++; $suma[$flds[$k]]+= $clmn[$n][$flds[$k]]= $csp ? $csp[3*$i+0] : $sp;
        }
        if ( $i>0 && $i<$nd
          || $i==0   && ($oo[0]=='s' || $oo[0]=='o')
          || $i==$nd && ($oo[1]=='o' || $oo[1]=='v') ) {
          $k++; $suma[$flds[$k]]+= $clmn[$n][$flds[$k]]= $csc ? $csc[3*$i+1] : $sc;
          $k++; $suma[$flds[$k]]+= $clmn[$n][$flds[$k]]= $csp ? $csp[3*$i+1] : $sp;
        }
        if ( $i<$nd || $oo[1]=='v' ) {
          $k++; $suma[$flds[$k]]+= $clmn[$n][$flds[$k]]= $csc ? $csc[3*$i+2] : $sc;
          $k++; $suma[$flds[$k]]+= $clmn[$n][$flds[$k]]= $csp ? $csp[3*$i+2] : $sp;
        }
      }
    }
  }
//                                                         debug($suma,"sumy");
  $tits[0]= $jsou_pecouni ? "Manželé a pečouni:25" : "Manželé:25";
  if ( $export ) {
    $result->tits= $tits;
    $result->flds= $flds;
    $result->clmn= $clmn;
    $result->expr= $expr;
    $result->suma= $suma;
  }
  else {
    // titulky
    foreach ($tits as $idw) {
      list($id)= explode(':',$idw);
      $ths.= "<th>$id</th>";
    }
    // data
    foreach ($clmn as $i=>$c) {
      $tab.= "<tr>";
      foreach ($c as $id=>$val) {
        $style= akce2_sestava_td_style($fmts[$id]);
        $tab.= "<td$style>$val</td>";
      }
      $tab.= "</tr>";
    }
    // sumy
    $sum= '';
    if ( count($suma)>0 ) {
      $sum.= "<tr>";
      foreach ($flds as $f) {
        $val= isset($suma[$f]) ? $suma[$f] : '';
        $sum.= "<th style='text-align:right'>$val</th>";
      }
      $sum.= "</tr>";
    }
    $result->html.= "<h3>Počty strav včetně pečounů</h3>";
    $result->html.= "nejsou započteni pečouni, kteří mají prázdný sloupec funkce (asi jsou jen dočasní)";
    $result->html.= "<br><br><div class='stat'><table class='stat'><tr>$ths</tr>$sum$tab</table></div>";
    $result->html.= "</br>";
    $result->href= $href;
  }
  return $result;
}
# ----------------------------------------------------- akce2_sestava_td_style
# $fmt= r|d
function akce2_sestava_td_style($fmt) {
  $style= array();
  switch ($fmt) {
  case 'r': $style[]= 'text-align:right'; break;
  case 'd': $style[]= 'text-align:right'; break;
  case '!': $style[]= 'color:red'; break;
  }
  return count($style)
    ? " style='".implode(';',$style)."'" : '';
}
# ======================================================================================> . přehledy
# ------------------------------------------------------------------------------ akce2_cerstve_zmeny
# generování seznamu změn v pobytech na akci od par-datetime
function akce2_cerstve_zmeny($akce,$par,$title,$vypis,$export=false) { trace();
  $result= (object)array('html'=>'');
  $od= $par->datetime= "2013-09-25 18:00";
  $p_flds= "'luzka','pokoj','pristylky','pocetdnu'";
  //
  $tits= explode(',',"_ucastnik,kdy,fld,old,val,kdo,id_track");
  $flds= $tits;
  $clmn= array();
  $n= 0;
  $ord= "
    CASE
      WHEN pouze=0 THEN r.nazev
      WHEN pouze=1 THEN GROUP_CONCAT(DISTINCT IF(t.role='a',o.prijmeni,'') SEPARATOR '')
      WHEN pouze=2 THEN GROUP_CONCAT(DISTINCT IF(t.role='b',o.prijmeni,'') SEPARATOR '')
    END";
  $rz= mysql_qry("
    SELECT id_track,kdy,kdo,klic,fld,op,old,val,
      GROUP_CONCAT(DISTINCT IF(t.role='a',o.prijmeni,'') SEPARATOR '') as prijmeni_m,
      GROUP_CONCAT(DISTINCT IF(t.role='a',o.jmeno,'')    SEPARATOR '') as jmeno_m,
      GROUP_CONCAT(DISTINCT IF(t.role='b',o.prijmeni,'') SEPARATOR '') as prijmeni_z,
      GROUP_CONCAT(DISTINCT IF(t.role='b',o.jmeno,'')    SEPARATOR '') as jmeno_z,
      r.nazev as nazev,p.pouze as pouze
    FROM _track
    JOIN pobyt AS p ON p.id_pobyt=klic
    JOIN akce AS a ON a.id_duakce=p.id_akce
    JOIN spolu AS s USING(id_pobyt)
    JOIN osoba AS o ON s.id_osoba=o.id_osoba
    LEFT JOIN tvori AS t ON t.id_osoba=o.id_osoba
    LEFT JOIN rodina AS r USING(id_rodina)
    WHERE kde='pobyt' AND kdy>='$od' AND id_akce=$akce AND fld IN ($p_flds) AND old!=val AND old!=''
    GROUP BY id_track,id_pobyt
    ORDER BY $ord,kdy
  ");
  while ( $rz && ($z= mysql_fetch_object($rz)) ) {
    $prijmeni= $z->pouze==1 ? $z->prijmeni_m : ($z->pouze==2 ? $z->prijmeni_z : $z->nazev);
    $jmena=    $z->pouze==1 ? $z->jmeno_m    : ($z->pouze==2 ? $z->jmeno_z : "{$z->jmeno_m} a {$z->jmeno_z}");
    $clmn[$n]= array();
    foreach($flds as $f) {
      switch ($f) {
      case '_ucastnik': $clmn[$n][$f]= "$prijmeni $jmena"; break;
      default:          $clmn[$n][$f]= $z->$f;
      }
    }
    $n++;
  }
  $result->html= "od $od bylo provedeno $n změn";
//                                         debug($clmn,"sestava pro $akce,$typ,$fld,$cnd");
  return tisk2_table($tits,$flds,$clmn,$export);
}
# ----------------------------------------------------------------------------------- akce2_text_eko
function akce2_text_eko($akce,$par,$title,$vypis,$export=false) { trace();
  $result= (object)array();
  $html= '';
  $prijmy= 0;
  $vydaje= 0;
  $prijem= array();
  // zjištění mimořádných pečovatelů
  $qm="SELECT id_spolu FROM pobyt AS p  JOIN akce  AS a ON p.id_akce=a.id_duakce
      JOIN spolu AS s USING(id_pobyt) WHERE p.id_akce='$akce' AND p.funkce=99 AND s.pfunkce=6 ";
  $rm= mysql_qry($qm);
  $n_mimoradni= mysql_num_rows($rm);
//   $mimoradni= $n_mimoradni ? "platba za stravu a ubytování $n_mimoradni mimořádných pečovatelů, kterou uhradili" : '';
  // příjmy od účastníků na pečouny
  $limit= '';
//   $limit= "AND id_pobyt IN (17957,18258,18382)";
  $qp=  "SELECT id_pobyt,funkce FROM pobyt WHERE id_akce='$akce' $limit ";
  $rp= mysql_qry($qp);
  while ( $rp && ($p= mysql_fetch_object($rp)) ) {
    $ret= akce2_vzorec($p->id_pobyt);
//                                                         if ($ret->eko->slevy)
//                                                         debug($ret->eko->slevy,"sleva pro fce={$p->funkce}");
    if ( $ret->eko->vzorec ) {
      foreach ($ret->eko->vzorec as $x=>$kc) {
        if ( !isset($prijem[$x]) ) $prijem[$x]= (object)array();
        $prijem[$x]->vzorec+= $kc;
        $corr= false;
        $slevy= $ret->eko->slevy;
        if ( $slevy ) {
          if ( $slevy->procenta ) {
            $prijem[$x]->platba+= round(($kc * (100 - $slevy->procenta)/100),-1);
            $corr= true;
          }
        }
        if ( !$corr ) {
          $prijem[$x]->platba+= $kc;
        }
      }
    }
  }
  // výdaje za pečouny (mimo osobních a pomocných)
  $rows_vydaje= '';
  $rows_prijmy= '';
  $qc= "SELECT GROUP_CONCAT(polozka) AS polozky, za
        FROM /*ezer_ys.*/cenik
        WHERE id_akce='$akce' AND za!=''
        GROUP BY za ORDER BY poradi ASC";
  $rc= mysql_qry($qc);
  while ( $rc && ($c= mysql_fetch_object($rc)) ) {
    if ( $prijem[$c->za]->vzorec ) {
      $cena= $platba= '';
      if ( $prijem[$c->za]->vzorec ) $cena= $prijem[$c->za]->vzorec;
      if ( $prijem[$c->za]->platba ) $platba= $prijem[$c->za]->platba;
      if ( $c->za != 'P' ) $prijmy+= $cena;
      $cena= number_format($cena, 0, '.', ' ');
      $platba= number_format($platba, 0, '.', ' ');
//       $rows_prijmy.= "<tr><th>{$c->polozky}</th><td align='right'>$cena</td><td align='right'>$platba</td></tr>";
      $rows_prijmy.= "<tr><th>{$c->polozky}</th><td align='right'>$cena</td></tr>";
    }
  }
  // náklad na stravu pečounů - kteří mají funkci a nemají zaškrtnuto "platí rodiče"
  $par= (object)array('typ'=>'vjp');
  $ret= akce2_stravenky($akce,$par,'','',true);
//                                                         debug($ret->tab);
  $ham= array('sc'=>0,'oc'=>0,'vc'=>0);
  $pecounu= 0;
  $noci= -1;
  foreach ($ret->tab as $jmeno=>$dny) {
//                                                         debug($dny,"DNY");
    $pecounu++;
    foreach ( $dny as $den=>$jidla ) {
      if ( $pecounu==1 ) $noci++;
      foreach ( $jidla as $jidlo=>$porce ) {
        foreach ( $porce as $velikost=>$pocet ) {
          $ham["$jidlo$velikost"]+= $pocet;
        }
      }
    }
  }
  $qc= "SELECT GROUP_CONCAT(polozka) AS polozky, za, SUM(cena) AS _cena_
        FROM /*ezer_ys.*/cenik
        WHERE id_akce='$akce' AND za IN ('sc','oc','vc','Np')
        GROUP BY za ORDER BY poradi ASC";
  $rc= mysql_qry($qc);
  while ( $rc && ($c= mysql_fetch_object($rc)) ) {
    $cena= $c->za=='Np'
      ? $noci * $pecounu * $c->_cena_
      : $ham[$c->za] * $c->_cena_;
    $vydaje+= $cena;
    $cena= number_format($cena, 0, '.', ' ');
    $rows_vydaje.= "<tr><th>{$c->polozky}</th><td align='right'>$cena</td></tr>";
  }
  // odhad příjmů za mimořádné pečouny - přičtení k příjmům
  if ( $n_mimoradni ) {
    $cena_mimoradni= $vydaje*$n_mimoradni/$pecounu;
    $prijmy+= $cena_mimoradni;
    $cena= number_format($cena_mimoradni, 0, '.', ' ');
    $rows_prijmy.= "<tr><th>ubytování a strava $n_mimoradni mimoř.peč.</th>
      <td align='right'>$cena</td></tr>";
  }
//                                                         debug($prijem,"EKONOMIKA AKCE celkem");
  // formátování odpovědi dle ceníku akce
  $html.= "<h3>Příjmy za akci podle aktuální skladby účastníků</h3>";
//   $html.= "Pozn. pro přehled se počítá také cena s uplatněnou procentní slevou (např. VPS)<br>";
//   $html.= "(příjmy pro pečovatele se počítají z plné tzn. vyšší ceny)<br>";
//   $html.= "<br><table class='stat'><td>položky</td><th>cena bez slev</th><th>cena po slevě</th></tr>";
  $html.= "<br><table class='stat'><td>položky</td><th>cena</th></tr>";
  $html.= "$rows_prijmy</table>";
  $html.= "<h3>Výdaje za stravu a ubytování pro $pecounu pečovatelů ($noci nocí)</h3>";
  $html.= "V tomto počtu nejsou zahrnuti pomocní a osobní pečovatelé, jejichž náklady hradí rodiče<br>";
  $html.= "(to je třeba v evidenční kartě pečovatele zapsat zaškrtnutím políčka pod poznámkou)<br>";
  // stravenky nejsou vytištěny pro $note, kteří nemají jasnou funkci -- pfunkce=0
  $html.= $ret->note ? "{$ret->note}<br>" : '';
  $html.= "<br><table class='stat'><td>položky</td><th>cena</td></tr>";
  $html.= "$rows_vydaje</table>";
  $html.= "<h3>Shrnutí pro pečovatele</h3>";
  $obrat= $prijmy - $vydaje;
  $prijmy= number_format($prijmy, 0, '.', ' ')."&nbsp;Kč";
  $vydaje= number_format($vydaje, 0, '.', ' ')."&nbsp;Kč";
  $obrat= number_format($obrat, 0, '.', ' ')."&nbsp;Kč";
  $html.= "Účastníci přispějí na pečovatele částkou $prijmy, přímé náklady na pobyt a stravu
    činí $vydaje, <br>celkem <b>$obrat</b> je tedy možné použít na programové výdaje
    pečovatelů na akci a během roku.";
  // předání výsledku
  $result->html= $html;
  return $result;
}
# -------------------------------------------------------------------------------- tisk2_text_vyroci
function tisk2_text_vyroci($akce,$par,$title,$vypis,$export=false) { trace();
  $result= (object)array('_error'=>0);
  $html= '';
  // data akce
  $vyroci= array();
  // narozeniny
  $res= tisk2_qry('ucastnik','prijmeni,jmeno,narozeni,role',
    "id_akce=$akce AND CONCAT(YEAR(datum_od),SUBSTR(narozeni,5,6)) BETWEEN datum_od AND datum_do",
    "","SUBSTR(narozeni,5,6)");
  while ( $res && ($x= mysql_fetch_object($res)) ) {
    $vyroci[$x->role=='d'?'d':'a'][]= "{$x->prijmeni} {$x->jmeno}|".sql_date1($x->narozeni);
  }
  // výročí
  $res= tisk2_qry('pobyt_dospeli_ucastnici','datsvatba',
    "id_akce=$akce AND CONCAT(YEAR(datum_od),SUBSTR(datsvatba,5,6)) BETWEEN datum_od AND datum_do",
    "","SUBSTR(datsvatba,5,6)");
  while ( $res && ($x= mysql_fetch_object($res)) ) {
    $vyroci['s'][]= "$x->_jm|".sql_date1($x->datsvatba);
  }
  // nepřivítané děti mladší 2 let
  $res= tisk2_qry('ucastnik','prijmeni,jmeno,narozeni,role,ROUND(DATEDIFF(a.datum_od,o.narozeni)/365.2425,1) AS _vek',
    "id_akce=$akce AND role='d' AND o.uvitano=0","_vek<2","prijmeni");
  while ( $res && ($x= mysql_fetch_object($res)) ) {
    $vyroci['v'][]= "{$x->prijmeni} {$x->jmeno}|".sql_date1($x->narozeni);
  }
  // redakce
  if ( count($vyroci['s']) ) {
    $html.= "<h3>Výročí svatby během akce</h3><table>";
    foreach($vyroci['s'] as $txt) {
      list($kdo,$kdy)= explode('|',$txt);
      $html.= "<tr><td>$kdy</td><td>$kdo</td></tr>";
    }
    $html.= "</table>";
  }
  else $html.= "<h3>Na akci nemá žádný pár výročí svatby</h3>";
  if ( count($vyroci['a']) ) {
    $html.= "<h3>Narozeniny dopělých na akci</h3><table>";
    foreach($vyroci['a'] as $txt) {
      list($kdo,$kdy)= explode('|',$txt);
      $html.= "<tr><td>$kdy</td><td>$kdo</td></tr>";
    }
    $html.= "</table>";
  }
  else $html.= "<h3>Na akci nemá žádný dospělý účastník narozeniny</h3>";
  if ( count($vyroci['d']) ) {
    $html.= "<h3>Narozeniny dětí na akci</h3><table>";
    foreach($vyroci['d'] as $txt) {
      list($kdo,$kdy)= explode('|',$txt);
      $html.= "<tr><td>$kdy</td><td>$kdo</td></tr>";
    }
    $html.= "</table>";
  }
  else $html.= "<h3>Na akci nemá žádné dítě narozeniny</h3>";
  if ( count($vyroci['v']) ) {
    $html.= "<h3>Nepřivítané děti mladší 2 let na akci</h3><table>";
    foreach($vyroci['v'] as $txt) {
      list($kdo,$kdy)= explode('|',$txt);
      $html.= "<tr><td>$kdy</td><td>$kdo</td></tr>";
    }
    $html.= "</table>";
  }
  else $html.= "<h3>Na akci nebudeme vítat žádné dítě</h3>";
  $result->html= $html;
  return $result;
}
# ------------------------------------------------------------------------------- akce2_text_prehled
function akce2_text_prehled($akce,$par,$title,$vypis,$export=false) { trace();
  $pocet= 0;
  # naplní histogram podle $cond
  $akce_text_prehled_x= function ($akce,$cond,$uvest_jmena=false,$bez_tabulky=false) use (&$pocet) {
    $html= '';
    // data akce
    $veky= $kluci= $holky= array();
    $nveky= $nkluci= $nholky= 0;
    $jmena= $deljmena= '';
    $bez= $del= '';
    // histogram věku dětí parametrizovaný přes $cond
    $qo=  "SELECT prijmeni,jmeno,narozeni,IFNULL(role,0),a.datum_od,o.sex,id_pobyt
           FROM akce AS a
           JOIN pobyt AS p ON a.id_duakce=p.id_akce
           JOIN spolu AS s USING(id_pobyt)
           JOIN osoba AS o ON s.id_osoba=o.id_osoba
           LEFT JOIN tvori AS t ON t.id_osoba=o.id_osoba AND IF(p.i0_rodina,t.id_rodina=p.i0_rodina,1)
           WHERE a.id_duakce='$akce' AND $cond AND funkce NOT IN (9,10,13,14) ORDER BY prijmeni ";
    $ro= mysql_qry($qo);
    while ( $ro && ($o= mysql_fetch_object($ro)) ) {
      $pocet++;
      $vek= narozeni2roky_sql($o->narozeni,$o->datum_od);
      $sex= $o->sex;
      $veky[$vek]++;
      $nveky++;
      if ( $sex==1 ) { $kluci[$vek]++; $nkluci++; }
      elseif ( $sex==2 ) { $holky[$vek]++; $nholky++; }
      else { $bez.= "$del{$o->prijmeni} {$o->jmeno}"; $del= ", "; }
      if ( $uvest_jmena ) {
        $jmena.= $deljmena;
        $jmena.= tisk2_ukaz_pobyt($o->id_pobyt,"{$o->prijmeni} {$o->jmeno}/$vek");
        $deljmena= ", ";
      }
    }
    ksort($veky);
    // formátování výsledku
    if ( !$bez_tabulky ) {
      $html.= "<table class='stat'>";
      $r1= $r2= $r3= $r4= '';
      foreach($veky as $v=>$n) {
        $r1.= "<th align='right' width='20'>$v</th>";
        $style= $n==$kluci[$v]+$holky[$v] ? '' : " style='background-color:yellow'";
        $r2.= "<td align='right'$style>$n</td>";
        $r3.= "<td align='right'>{$kluci[$v]}</td>";
        $r4.= "<td align='right'>{$holky[$v]}</td>";
      }
      $r1.= "<th align='right'>celkem</th>";
      $style= $nveky==$nkluci+$nholky ? '' : " style='background-color:yellow'";
      $r2.= "<td align='right'$style>$nveky</td>";
      $r3.= "<td align='right'>$nkluci</td>";
      $r4.= "<td align='right'>$nholky</td>";
      $html.= "<tr><th>věk</th>$r1</tr><tr><th>počet</th>$r2</tr><tr>"
            . "<th>kluci</th>$r3</tr><tr><th>holky</th>$r4</tr></table>";
    }
    // jména
    if ( $jmena ) $html.= "<b>($jmena)</b>";
    // upozornění
    if ( $bez ) $html.= ($jmena?"<br>":'')."<i>(ani holka ani kluk: $bez)</i>";
    // předání výsledku
    return $html;
  };
  $result= (object)array();
  $html= '';
  $nedeti= $akce_text_prehled_x($akce,"t.role='d' AND p.funkce!=99 AND s.s_role NOT IN (2,3,4,5)",1,1);
  if ( $pocet>0 )
    $html.= "<h3 style='color:red'>POZOR! Děti vedené chybně jako účastníci nebo hosté</h3>$nedeti";
  // pfunkce: 0 4 5 8 92 95
  // pfunkce: 1=hlavoun, 2=instruktor, 3=pečovatel, 4=pomocný, 5=osobní, 6=mimořádný, 7=team, 8=člen G
  // funkce=99  -- pečoun, funkce=9,10,13,14 -- není na akci
  // s_role=2   -- dítě, s_role=3  -- dítě s os.peč, s_role=4  -- pom.peč, s_role=5  -- os.peč
  // dite_kat=7 -- skupina G
  // děti
  $html.= "<h2>Informace z karty Účastníci2 (bez náhradníků)</h2><h3>Celkový počet dětí na akci podle stáří (v době začátku akce) - bez os.pečounů včetně pom.pečounů</h3>";
  $html.= $akce_text_prehled_x($akce,"t.role='d' AND p.funkce!=99 AND s.s_role IN (0,1,2,3,4)");
  $html.= "<h3>Děti ve skupinkách (mimo G a osobně opečovávaných)</h3>";
  $html.= $akce_text_prehled_x($akce,"t.role='d' AND p.funkce!=99 AND s.s_role IN (2,4) AND s.dite_kat!=7");
  $html.= "<h3>Děti v péči osobního pečovatele</h3>";
  $html.= $akce_text_prehled_x($akce,"t.role='d' AND p.funkce!=99 AND s.s_role IN (3)",1);
  $html.= "<h3>Děti ve skupině G</h3>";
  $html.= $akce_text_prehled_x($akce,"t.role='d' AND p.funkce!=99 AND s.dite_kat=7",true);
  $html.= "<h3>Pomocní pečovatelé</h3>";
  $html.= $akce_text_prehled_x($akce,"t.role='d' AND p.funkce!=99 AND s.s_role IN (4)",1);
  $html.= "<h3>Osobní pečovatelé (nezařazení mezi Pečovatele)</h3>";
  $html.= $akce_text_prehled_x($akce,"p.funkce!=99 AND s.s_role IN (5) AND s.pfunkce NOT IN (5)",true);
  // osobní mezi pečouny
  $html.= "<br><hr><h3>Osobní pečovatelé (zařazení mezi Pečovatele)</h3>";
  $html.= $akce_text_prehled_x($akce,"p.funkce!=99 AND s.pfunkce IN (5)",true);
  // pečouni
  $html.= "<br><hr><h2>Informace z karty Pečouni</h2><h3>Řádní pečovatelé</h3>";
  $html.= $akce_text_prehled_x($akce,"p.funkce=99 AND s.pfunkce IN (1,2,3) ");
  $html.= "<h3>Mimořádní pečovatelé</h3>";
  $html.= $akce_text_prehled_x($akce,"p.funkce=99 AND s.pfunkce=6 ",true);
  $html.= "<h3>Team pečovatelů (s touto funkcí)</h3>";
  $html.= $akce_text_prehled_x($akce,"p.funkce=99 AND s.pfunkce IN (7) ",true);
  $html.= "<h3>Team pečovatelů (bez přiřazené funkce)</h3>";
  $html.= $akce_text_prehled_x($akce,"p.funkce=99 AND s.pfunkce IN (0) ",true);
  $result->html= "$html<br><br>";
  return $result;
}
# =======================================================================================> . pomocné
# obsluha různých forem výpisů karet AKCE
# --------------------------------------------------------------------------------- tisk2_ukaz_osobu
# zobrazí odkaz na osobu v evidenci
function tisk2_ukaz_osobu($ido,$barva='') {
  $style= $barva ? "style='color:$barva'" : '';
  return "<b><a $style href='ezer://akce2.evi.evid_osoba/$ido'>$ido</a></b>";
}
# -------------------------------------------------------------------------------- tisk2_ukaz_rodinu
# zobrazí odkaz na rodinu v evidenci
function tisk2_ukaz_rodinu($idr,$barva='') {
  $style= $barva ? "style='color:$barva'" : '';
  return "<b><a $style href='ezer://akce2.evi.evid_rodina/$idr'>$idr</a></b>";
}
# --------------------------------------------------------------------------------- tisk2_ukaz_pobyt
# zobrazí odkaz na řádek s pobytem
function tisk2_ukaz_pobyt($idp,$barva='') {
  $style= $barva ? "style='color:$barva'" : '';
  return "<b><a $style href='ezer://akce2.ucast.ucast_pobyt/$idp'>$idp</a></b>";
}
# -------------------------------------------------------------------------------- narozeni2roky_sql
# zjistí aktuální věk v rocích z data narození (typu mktime) zjištěného třeba rc2time          ?????
# pokud je předáno $now(jako timestamp) bere se věk k tomu
function narozeni2roky_sql($time_sql,$now_sql=0) {
  $time= sql2stamp($time_sql);
  $now= $now_sql ? sql2stamp($now_sql) : time();
  $roky= floor((date("Ymd",$now) - date("Ymd", $time)) / 10000);
  return $roky;
}
# ---------------------------------------------------------------------------------------- tisk2_qry
# frekventované SQL dotazy s parametry
# pobyt_dospeli_ucastnici => _jm=jména dospělých účastníků (GROUP BY id_pobyt)
# ucastnik                => každý účastník zvlášť
# pobyt_rodiny            => _jmena, _adresa, _telefony, _emaily
function tisk2_qry($typ,$flds='',$where='',$having='',$order='') { trace();
  $where=  $where  ? " WHERE $where " : '';
  $having= $having ? " HAVING $having " : '';
  $order=  $order  ? " ORDER BY $order " : '';
  switch ($typ) {
  case 'ucastnik':
    $qry= "
      SELECT $flds
      FROM akce AS a
        JOIN pobyt AS p ON a.id_duakce=p.id_akce
        JOIN spolu AS s USING(id_pobyt)
        JOIN osoba AS o ON s.id_osoba=o.id_osoba AND o.deleted=''
        LEFT JOIN tvori AS t ON t.id_osoba=o.id_osoba AND t.id_rodina=p.i0_rodina
      $where
      GROUP BY o.id_osoba $having $order
    ";
    break;
  case 'pobyt_rodiny':
    $qry= "
      SELECT id_pobyt,i0_rodina,pr.obec
        ,COUNT(pso.id_osoba) AS _ucastniku
        ,CONCAT(IF(pr.telefony!='',CONCAT(pr.telefony,','),''),
           GROUP_CONCAT(IF(pso.kontakt,pso.telefon,''))) AS _telefony
        ,CONCAT(IF(pr.emaily!='',CONCAT(pr.emaily,','),''),
           GROUP_CONCAT(IF(pso.kontakt,pso.email,''))) AS _emaily
        ,IF(i0_rodina,CONCAT(pr.nazev,' ',GROUP_CONCAT(REPLACE(TRIM(pso.jmeno),' ','-') ORDER BY role SEPARATOR ' a '))
          ,GROUP_CONCAT(DISTINCT CONCAT(pso.prijmeni,' ',REPLACE(TRIM(pso.jmeno),' ','-')) ORDER BY role SEPARATOR ' a ')) as _jmena
        ,GROUP_CONCAT(CONCAT(ps.id_spolu,'|',REPLACE(TRIM(jmeno),' ','-'),'|',prijmeni,'|',adresa,'|',pso.obec,'|'
          ,IFNULL(( SELECT CONCAT(id_tvori,'/',role)
             FROM tvori
             JOIN rodina USING (id_rodina)
             WHERE id_osoba=pso.id_osoba AND role IN ('a','b')
             GROUP BY id_osoba ),'-')
        )) AS _o
      FROM pobyt
        LEFT JOIN rodina AS pr ON pr.id_rodina=i0_rodina
        JOIN spolu AS ps USING (id_pobyt)
        JOIN osoba AS pso USING (id_osoba)
        LEFT JOIN (
          SELECT id_osoba,role,id_rodina
            FROM tvori
            JOIN rodina USING (id_rodina)
            GROUP BY id_osoba
        ) AS rto ON rto.id_osoba=pso.id_osoba AND rto.role IN ('a','b')
      $where
      -- WHERE id_pobyt IN (15209,15217,15213,15192,15199)
      GROUP BY id_pobyt $having $order
      ";
    break;
  case 'pobyt_dospeli_ucastnici': // a i nedospělí pečouni
    $flds=  $flds  ? " $flds," : '';
    $qry= "
      SELECT $flds
        IF(p.i0_rodina,CONCAT(pr.nazev,' ',GROUP_CONCAT(po.jmeno ORDER BY role SEPARATOR ' a '))
          ,GROUP_CONCAT(DISTINCT CONCAT(pso.prijmeni,' ',pso.jmeno) ORDER BY role SEPARATOR ' a ')) as _jm
      FROM pobyt AS p
        JOIN akce AS a ON p.id_akce=a.id_duakce
        LEFT JOIN rodina AS pr ON pr.id_rodina=p.i0_rodina
        JOIN spolu AS ps ON ps.id_pobyt=p.id_pobyt
        LEFT JOIN tvori AS pt ON pt.id_rodina=p.i0_rodina AND role IN ('a','b') AND ps.id_osoba=pt.id_osoba
        LEFT JOIN osoba AS po ON po.id_osoba=pt.id_osoba
        JOIN osoba AS pso ON pso.id_osoba=ps.id_osoba
      $where AND IF(funkce=99,1,DATEDIFF(a.datum_od,pso.narozeni)/365.2425>18)
      GROUP BY p.id_pobyt $having $order
    ";
    break;
  }
  $res= mysql_qry($qry);
  return $res;
}
# -------------------------------------------------------------------------------------- tisk2_table
function tisk2_table($tits,$flds,$clmn,$export=false) {  trace();
  $ret= (object)array('html'=>'');
  // zobrazení tabulkou
  $tab= '';
  $thd= '';
  $n= 0;
  if ( $export ) {
    $ret->tits= $tits;
    $ret->flds= $flds;
    $ret->clmn= $clmn;
  }
  else {
    // titulky
    foreach ($tits as $idw) {
      list($id)= explode(':',$idw);
      $ths.= "<th>$id</th>";
    }
    foreach ($clmn as $i=>$c) {
      $tab.= "<tr>";
      foreach ($flds as $f) {
        if ( $f=='id_osoba' || $f=='^id_osoba' )
          $tab.= "<td style='text-align:right'>".tisk2_ukaz_osobu($c[$f])."</td>";
        elseif ( $f=='^id_rodina' )
          $tab.= "<td style='text-align:right'>".tisk2_ukaz_rodinu($c['^id_rodina'])."</td>";
        elseif ( $f=='^id_pobyt' )
          $tab.= "<td style='text-align:right'>".tisk2_ukaz_pobyt($c['^id_pobyt'])."</td>";
        else {
//                                 debug($c,$f); return $ret;
          $tab.= "<td style='text-align:left'>{$c[$f]}</td>";
        }
      }
      $tab.= "</tr>";
      $n++;
    }
    $ret->html= "Seznam má $n řádků<br><br><div class='stat'><table class='stat'><tr>$ths</tr>$tab</table></div>";
  }
  return $ret;
}
# ======================================================================================> . skupinky
# ----------------------------------------------------------------------------- akce2_jednou_dvakrat
# generování seznamu jedno- a dvou-ročáků spolu s mailem na VPS
#   $fld = seznam položek s prefixem
#   $cnd = podmínka
function akce2_jednou_dvakrat($akce,$par,$title,$vypis,$export=false) { trace();
  $result= (object)array('html'=>'');
  $vps= array();
  $n= 0;
  $qry=  "SELECT
            r.nazev as nazev,
            ( SELECT CONCAT(nazev,' ',emaily,' ',email,'/',funkce )
              FROM rodina
              JOIN tvori ON rodina.id_rodina=tvori.id_rodina
              JOIN osoba ON osoba.id_osoba=tvori.id_osoba
              JOIN spolu ON spolu.id_osoba=osoba.id_osoba
              JOIN pobyt ON pobyt.id_pobyt=spolu.id_pobyt
              WHERE pobyt.id_akce='$akce' AND skupina=p.skupina AND role='a'
              ORDER BY funkce DESC LIMIT 1) as skup,
            GROUP_CONCAT(DISTINCT IF(t.role='a',o.jmeno,'')    SEPARATOR '') as jmeno_m,
            GROUP_CONCAT(DISTINCT IF(t.role='b',o.jmeno,'')    SEPARATOR '') as jmeno_z,
            ( SELECT count(DISTINCT cp.id_pobyt) FROM pobyt AS cp
              JOIN akce AS ca ON ca.id_duakce=cp.id_akce
              JOIN spolu AS cs ON cp.id_pobyt=cs.id_pobyt
              JOIN osoba AS co ON cs.id_osoba=co.id_osoba
              LEFT JOIN tvori AS ct ON ct.id_osoba=co.id_osoba
              LEFT JOIN rodina AS cr ON cr.id_rodina=ct.id_rodina
              WHERE ca.druh=1 AND cr.id_rodina=r.id_rodina ) AS _ucasti
          FROM pobyt AS p
          JOIN spolu AS s USING(id_pobyt)
          JOIN osoba AS o ON s.id_osoba=o.id_osoba
          LEFT JOIN tvori AS t ON t.id_osoba=o.id_osoba
          LEFT JOIN rodina AS r USING(id_rodina)
          WHERE p.id_akce='$akce' AND p.skupina!=0
          GROUP BY id_pobyt HAVING _ucasti IN (1,2)
          ORDER BY nazev";
  $res= mysql_qry($qry);
  while ( $res && ($x= mysql_fetch_object($res)) ) {
    $par= "{$x->_ucasti}x {$x->jmeno_m} a {$x->jmeno_z} {$x->nazev}";
    $vps[$x->skup][]= $par;
    $n++;
  }
  ksort($vps);
//                                         debug($vps,"jednou - dvakrát");
  $html= "<h3>Pracovní seznam párů, kteří jsou na MS poprvé (1x) nebo podruhé (2x), spolu s jejich $VPS</h3>";
  foreach ($vps as $v => $ps) {
    $html.= "<p><b>$v</b>";
    foreach ($ps as $p) {
      $html.= "<br>$p";
    }
    $html.= "</p>";
  }
  $result->html= $html;
  return $result;
}
# ---------------------------------------------------------------------------------- akce2_skup_tisk
# tisk skupinek akce
function akce2_skup_tisk($akce,$par,$title,$vypis,$export) {
  global $VPS;
  $result= (object)array();
  $html= "<table>";
  $skupiny= akce2_skup_get($akce,0,$err,$par);
  $n= 0;
  if ( $export ) {
    $clmn= array();
    foreach ($skupiny as $i=>$s) {
      foreach ($s as $c) {
        $clmn[$n]['skupina']= $i==$c->id_pobyt ? $c->skupina : '';
        $clmn[$n]['jmeno']= $c->_nazev;
        $clmn[$n]['pokoj']= $i==$c->id_pobyt ? $c->pokoj : '';
        $n++;
      }
      $clmn[$n]['skupina']= $clmn[$n]['jmeno']= $clmn[$n]['pokoj']= '';
      $n++;
    }
    $result->tits= explode(',',"skupinka:10,jméno:30,pokoj $VPS:10:r");
    $result->flds= explode(',',"skupina,jmeno,pokoj");
    $result->clmn= $clmn;
    $result->expr= null;
  }
  else {
    foreach ($skupiny as $i=>$s) {
      $tab= "<table>";
      foreach ($s as $c) {
        if ( $i==$c->id_pobyt )
          $tab.= "<tr><th>{$c->skupina}</th><th>{$c->_nazev}</th><th>{$c->pokoj}</th></tr>";
        else
          $tab.= "<tr><td></td><td>{$c->_nazev}</td><td></td></tr>";
      }
      $tab.= "</table>";
      if ( $n%2==0 )
        $html.= "<tr><td>&nbsp;</td></tr><tr><td valign='top'>$tab</td>";
      else
        $html.= "<td valign='top'>$tab</td></tr>";
      $n++;
    }
    if ( $n%2==1 )
      $html.= "<td></td></tr>";
    $html.= "</table>";
    $result->html= $html;
  }
  return $result;
}
# ---------------------------------------------------------------------------------- akce2_skup_hist
# přehled starých skupinek letního kurzu MS účastníků této akce
function akce2_skup_hist($akce,$par,$title,$vypis,$export) { trace();
  $result= (object)array();
  // letošní účastníci
  $letos= array();
  $qry=  "SELECT skupina,r.nazev,r.obec,year(datum_od) as rok,p.funkce as funkce,
            GROUP_CONCAT(DISTINCT IF(t.role='a',o.id_osoba,'') SEPARATOR '') as id_osoba_m,
            GROUP_CONCAT(DISTINCT IF(t.role='a',o.jmeno,'')    SEPARATOR '') as jmeno_m,
            GROUP_CONCAT(DISTINCT IF(t.role='b',o.jmeno,'')    SEPARATOR '') as jmeno_z,
            id_pobyt
          FROM pobyt AS p
          JOIN akce  AS a ON a.id_duakce=p.id_akce
          JOIN spolu AS s USING(id_pobyt)
          JOIN osoba AS o ON s.id_osoba=o.id_osoba
          LEFT JOIN tvori AS t ON t.id_osoba=o.id_osoba
          LEFT JOIN rodina AS r USING(id_rodina)
          WHERE id_akce=$akce AND p.funkce IN (0,1,2,5)
          GROUP BY id_pobyt
          ORDER BY IF(pouze=0,r.nazev,o.prijmeni) ";
  $res= mysql_qry($qry);
  while ( $res && ($u= mysql_fetch_object($res)) ) {
    $muz= $u->id_osoba_m;
    $letos[$muz]= $u;
    $letos[$muz]->_nazev= $u->nazev;
    $rok= $u->rok;
  }
//                                                         debug($letos);
  $letosni= implode(',',array_keys($letos));
  // doplnění nejednoznačných příjmení o město
  $old= 0; $old_nazev= '';
  foreach ($letos as $muz=>$info) {
    if ( $old_nazev==$info->_nazev ) {
      $letos[$old]->_nazev= $letos[$old]->nazev.' '.$letos[$old]->jmeno_m.' '.$letos[$old]->jmeno_z;
      $letos[$muz]->_nazev= $letos[$muz]->nazev.' '.$letos[$muz]->jmeno_m.' '.$letos[$muz]->jmeno_z;
    }
    $old= $muz;
    $old_nazev= $info->_nazev;
  }
//                                                         debug($odkud);
  // tisk
  $html= "";
  foreach ($letos as $muz=>$info) {
    // minulé účasti
    $n= 0;
    $qry= " SELECT p.id_akce,skupina,year(datum_od) as rok
            FROM akce AS a
            JOIN pobyt AS p ON a.id_duakce=p.id_akce
            JOIN spolu AS s USING(id_pobyt)
            WHERE a.druh=1 AND s.id_osoba='$muz' AND p.id_akce!=$akce AND skupina!=0
            ORDER BY datum_od DESC ";
    $res= mysql_qry($qry);
    $ucasti= '';
    $html.= "\n<p>";
    while ( $res && ($r= mysql_fetch_object($res)) ) {
      $n++;
      // minulé skupinky
      $qry_s= "
            SELECT GROUP_CONCAT(DISTINCT IF(t.role='a',o.id_osoba,'') SEPARATOR '') as id_osoba_m
            FROM akce AS a
            JOIN pobyt AS p ON a.id_duakce=p.id_akce
            JOIN spolu AS s USING(id_pobyt)
            JOIN osoba AS o ON s.id_osoba=o.id_osoba
            LEFT JOIN tvori AS t ON t.id_osoba=o.id_osoba
            WHERE p.id_akce={$r->id_akce} AND skupina={$r->skupina}
            GROUP BY id_pobyt HAVING FIND_IN_SET(id_osoba_m,'$letosni')
            ORDER BY datum_od DESC ";
      $res_s= mysql_qry($qry_s);
      $spolu= ''; $del= '';
      while ( $res_s && ($s= mysql_fetch_object($res_s)) ) if ( $s->id_osoba_m!=$muz ) {
        $spolu.= "$del{$letos[$s->id_osoba_m]->_nazev}";
        $del= ', ';
      }
      if ( $spolu ) {
        $ucasti.= " <u>{$r->rok}</u>: $spolu";
      }
    }
//     if ( $ucasti )
//       $html.= "<dt><b>{$info->_nazev}</b> $n&times;</dt><dd>$ucasti</dd>";
//     elseif ( $n )
//       $html.= "<dt><b>{$info->_nazev}</b> $n&times;</dt>";
//     else
//       $html.= "<dt><b>{$info->_nazev}</b> - bude poprvé</dt>";
    if ( $ucasti )
      $html.= "<b>{$info->_nazev}</b> $n&times;<br>$ucasti";
    elseif ( $n )
      $html.= "<b>{$info->_nazev}</b> $n&times;";
    else
      $html.= "<b>{$info->_nazev}</b> - bude poprvé";
    $html.= "</p>";
  }
  $note= "Abecední seznam účastníků letního kurzu roku $rok doplněný seznamem členů jeho starších
          skupinek na letních kurzech. <br>Ve skupinkách jsou uvedení jen účastníci
          kurzu roku $rok. (Pro tisk je nejjednodušší označit jako blok a vložit do Wordu.)";
  $html= "<i>$note</i><br>$html";
  //$result->html= nl2br(htmlentities($html));
  $result->html= $html;
  return $result;
}
# ---------------------------------------------------------------------------------- akce2_skup_deti
# tisk skupinek akce dětí
function akce2_skup_deti($akce,$par,$title,$vypis,$export) {
  global $VPS;
  $result= (object)array();
  // celkový počet dětí na kurzu
  $qry= "SELECT SUM(IF(t.role='d',1,0)) AS _deti,SUM(IF(funkce=99,1,0)) AS _pecounu
         FROM akce AS a
         JOIN pobyt AS p ON a.id_duakce=p.id_akce
         JOIN spolu AS s ON p.id_pobyt=s.id_pobyt
         JOIN osoba AS o ON s.id_osoba=o.id_osoba
         LEFT JOIN tvori AS t ON t.id_osoba=o.id_osoba
         WHERE id_duakce='$akce'
         GROUP BY id_duakce ";
  $res= mysql_qry($qry);
  $pocet= mysql_fetch_object($res);
//                                                         debug($pocet,"počty");
  // zjištění skupinek
  $skupiny= array();   // [ skupinka => [{fce,příjmení,jméno},....], ...]
  $qry="SELECT id_pobyt,skupinka,funkce,prijmeni,jmeno,narozeni,rc_xxxx,datum_od
        FROM osoba AS o
        JOIN spolu AS s USING(id_osoba)
        JOIN pobyt AS p USING(id_pobyt)
        JOIN akce  AS a ON id_duakce='$akce'
        WHERE  id_akce='$akce' AND skupinka!=0
        ORDER BY skupinka,IF(funkce=99,0,1),prijmeni,jmeno ";
  $res= mysql_qry($qry);
  while ( $res && ($o= mysql_fetch_object($res)) ) {
    $o->_vek= narozeni2roky_sql($o->narozeni,$o->datum_od);
    $skupiny[$o->skupinka][]= $o;
  }
//                                                         debug($skupiny,"skupiny");
  $n= 0;
  $deti_in= $pecouni_in= 0;
  if ( $export ) {
    $clmn= array();
    foreach ($skupiny as $i=>$s) {
      foreach ($s as $c) {
//                                                         debug($c,"$i");
        $clmn[$n]['skupinka']= $c->funkce==99 ? $c->skupinka : '';
        $clmn[$n]['prijmeni']= $c->prijmeni;
        $clmn[$n]['jmeno']= $c->jmeno;
        $clmn[$n]['vek']= $c->_vek;
        $n++;
      }
      $clmn[$n]['skupinka']= $clmn[$n]['jmeno']= $clmn[$n]['prijmeni']= $clmn[$n]['vek']= '';
      $n++;
    }
    $result->tits= explode(',',"skupinka:10,příjmení:20,jméno:20,věk:4:r");
    $result->flds= explode(',',"skupinka,prijmeni,jmeno,vek");
    $result->clmn= $clmn;
    $result->expr= null;
  }
  else {
    $tab= '';
    $r= "style='text-align:right'";
    foreach ($skupiny as $i=>$s) {
      $tab.= "<table class='stat'>";
      $tab.= "<tr><th width=200 colspan=3>Skupinka $i</th></tr>";
      foreach ($s as $o) {
        if ( $o->funkce==99 ) {
          $tab.= "<tr><th>{$o->prijmeni}</th><th>{$o->jmeno}</th><th $r>{$o->_vek}</th></tr>";
          $pecouni_in++;
        }
        else {
          $tab.= "<tr><td>{$o->prijmeni}</td><td>{$o->jmeno}</td><td $r>{$o->_vek}</td></tr>";
          $deti_in++;
        }
      }
      $tab.= "</table><br/>";
    }
    $deti_out= $pocet->_deti - $deti_in;
    $pecouni_out= $pocet->_pecounu - $pecouni_in;
    $msg= $deti_out>0 ? "Celkem $deti_out dětí není zařazeno do skupinek": '';
    if ( $deti_out>0 ) fce_warning($msg);
    $msg.= $pecouni_out>0 ? "<br>Celkem $pecouni_out pečounů není zařazeno do skupinek<br><br>": '';
    $result->html= "$msg$tab";
  }
//                                                         debug($result,"result");
  return $result;
}
# =======================================================================================> . plachta
# ------------------------------------------------------------------------------- tisk2_pdf_plachta0
# generování pomocných štítků
function tisk2_pdf_plachta0($report_json=0) {  trace();
  global $ezer_path_docs;
  $result= (object)array('_error'=>0);
  $n= 0;
  if ( $report_json) {
    $parss= array();
    for ($i= 1; $i<=30; $i++ ) {
      // definice pole substitucí
      $parss[$n]= (object)array();  // {cislo]
      $fs= 20;
      $s1= "font-size:{$fs}mm;font-weight:bold";
      $bg1= ";color:#00aa00";
      $ii= $i<10 ? "&nbsp;$i" : $i;
      $parss[$n]->prijmeni= "<span style=\"$s1$bg1\">$ii</span>";
      $parss[$n]->jmena= '';
      $n++;
    }
    for ($i= 1; $i<=12; $i++ ) {
      // definice pole substitucí
      $parss[$n]= (object)array();  // {cislo]
      $fs= 20;
      $s1= "font-size:{$fs}mm;font-weight:bold";
      $bg1= ";color:#aa0000";
      $ii= $i<10 ? "&nbsp;$i" : "&nbsp;&nbsp;&nbsp;";
      $ia= chr(ord('a')+$i-1);
      $parss[$n]->prijmeni= "<span style=\"$s1$bg1\">$ii &nbsp;&nbsp;  $ia</span>";
      $parss[$n]->jmena= '';
      $n++;
    }
//                                         debug($parss,"tisk_pdf_plachta..."); return $result;
    // předání k tisku
    $fname= 'stitky_'.date("Ymd_Hi");
    $fpath= "$ezer_path_docs/$fname.pdf";
    dop_rep_ids($report_json,$parss,$fpath);
    $result->html= " Výpis byl vygenerován ve formátu <a href='docs/$fname.pdf' target='pdf'>PDF</a>.";
  }
  else {
    $result->html= "pomocné šítky";
  }
  return $result;
}
# ------------------------------------------------------------------------------------ akce2_plachta
# podklad pro tvorbu skupinek
function akce2_plachta($akce,$par,$title,$vypis,$export=0) { trace();
  $result= (object)array();
  // číselníky
  $c_vzdelani= map_cis('ms_akce_vzdelani','zkratka');  $c_vzdelani[0]= '?';
  $c_cirkev= map_cis('ms_akce_cirkev','zkratka');      $c_cirkev[0]= '?';  $c_cirkev[1]= 'kat';
  $letos= date('Y');
  $html= "";
  $err= "";
  $excel= array();
//   $html.= "<table class='vypis'>";
  // letošní účastníci
  $qry=  "SELECT
          datum_od,
          FIND_IN_SET(1,r_umi) AS _umi_vps,o.jmeno AS jmeno_o,o.prijmeni AS prijmeni_o,
          r.nazev as jmeno,p.pouze as pouze,r.obec as mesto,svatba,datsvatba,p.funkce as funkce,
          GROUP_CONCAT(DISTINCT IF(t.role='a',o.id_osoba,'') SEPARATOR '') as id_osoba_m,
          GROUP_CONCAT(DISTINCT IF(t.role='a',o.prijmeni,'') SEPARATOR '') as prijmeni_m,
          GROUP_CONCAT(DISTINCT IF(t.role='a',o.jmeno,'')    SEPARATOR '') as jmeno_m,
          GROUP_CONCAT(DISTINCT IF(t.role='a',o.narozeni,'') SEPARATOR '') as narozeni_m,
          GROUP_CONCAT(DISTINCT IF(t.role='a',o.vzdelani,'') SEPARATOR '') as vzdelani_m,
          GROUP_CONCAT(DISTINCT IF(t.role='a',o.cirkev,'')   SEPARATOR '') as cirkev_m,
          GROUP_CONCAT(DISTINCT IF(t.role='a',o.aktivita,'') SEPARATOR '') as aktivita_m,
          GROUP_CONCAT(DISTINCT IF(t.role='a',o.zajmy,'')    SEPARATOR '') as zajmy_m,
          GROUP_CONCAT(DISTINCT IF(t.role='a',o.zamest,'')   SEPARATOR '') as zamest_m,
          GROUP_CONCAT(DISTINCT IF(t.role='b',o.id_osoba,'') SEPARATOR '') as id_osoba_z,
          GROUP_CONCAT(DISTINCT IF(t.role='b',o.prijmeni,'') SEPARATOR '') as prijmeni_z,
          GROUP_CONCAT(DISTINCT IF(t.role='b',o.jmeno,'')    SEPARATOR '') as jmeno_z,
          GROUP_CONCAT(DISTINCT IF(t.role='b',o.narozeni,'') SEPARATOR '') as narozeni_z,
          GROUP_CONCAT(DISTINCT IF(t.role='b',o.vzdelani,'') SEPARATOR '') as vzdelani_z,
          GROUP_CONCAT(DISTINCT IF(t.role='b',o.cirkev,'')   SEPARATOR '') as cirkev_z,
          GROUP_CONCAT(DISTINCT IF(t.role='b',o.aktivita,'') SEPARATOR '') as aktivita_z,
          GROUP_CONCAT(DISTINCT IF(t.role='b',o.zajmy,'')    SEPARATOR '') as zajmy_z,
          GROUP_CONCAT(DISTINCT IF(t.role='b',o.zamest,'')   SEPARATOR '') as zamest_z,
          ( SELECT COUNT(*)
            FROM osoba JOIN tvori USING(id_osoba)
            WHERE id_rodina=t.id_rodina AND role='d' ) AS deti,
          ( SELECT MIN(narozeni)
            FROM osoba JOIN tvori USING(id_osoba)
            WHERE id_rodina=t.id_rodina AND role='d' ) AS maxdeti,
          ( SELECT MAX(narozeni)
            FROM osoba JOIN tvori USING(id_osoba)
            WHERE id_rodina=t.id_rodina AND role='d' ) AS mindeti
          FROM pobyt AS p
          JOIN spolu AS s USING(id_pobyt)
          JOIN osoba AS o ON s.id_osoba=o.id_osoba
          LEFT JOIN tvori AS t ON t.id_osoba=o.id_osoba
          LEFT JOIN rodina AS r USING(id_rodina)
          JOIN akce AS a ON a.id_duakce=p.id_akce
          WHERE id_akce=$akce AND p.funkce IN (0,1,2,5)  -- včetně hospodářů, bývají hosty skupinky
          GROUP BY id_pobyt
          ORDER BY IF(pouze=0,r.nazev,o.prijmeni) ";
//   $qry.= " LIMIT 1";
  $res= mysql_qry($qry);
  while ( $res && ($u= mysql_fetch_object($res)) ) {
    $muz= $u->id_osoba_m;
    $zen= $u->id_osoba_z;
    if ( !$muz || !$zen ) {
      $jmena= "{$u->jmeno_o} {$u->prijmeni_o}";
      $err.= "<b style='color:darkred'>POZOR: účastník akce pro páry $jmena není pár
             - z tabulky je vynechán(a)</b><br><br>";
      continue;
    }
    // minulé účasti
    $rqry= "SELECT count(*) as _pocet
            FROM akce AS a
            JOIN pobyt AS p ON a.id_duakce=p.id_akce
            JOIN spolu AS s USING(id_pobyt)
            WHERE a.druh=1 AND s.id_osoba=$muz AND p.id_akce!=$akce";
    $rres= mysql_qry($rqry);
    while ( $rres && ($r= mysql_fetch_object($rres)) ) {
      $u->ucasti= $r->_pocet ? "  {$r->_pocet}x" : '';
    }
    // věk
    $vek_m= sql2stari($u->narozeni_m,$u->datum_od);
    $vek_z= sql2stari($u->narozeni_z,$u->datum_od);
    $vek= abs($vek_m-$vek_z)<5 ? $vek_m : "$vek_m/$vek_z";
    // spolu
    $spolu= '?';
    if ( $u->datsvatba ) {
      $spolu= sql2roku($u->datsvatba);
    }
    elseif ( $u->svatba ) {
      $spolu= $letos-$u->svatba;
    }
    // děti
    $deti= $u->deti;
    if ( $deti ) {
      if ( $u->mindeti!='0000-00-00' && $u->maxdeti!='0000-00-00' ) {
        $deti.= "(".sql2roku($u->mindeti);
        if ( $deti>1 )
          $deti.= "-".sql2roku($u->maxdeti);
        $deti.= ")";
      }
      else
        $deti.= "(?)";
    }
    // vzdělání
    $vzdelani_muze= mb_substr($c_vzdelani[$u->vzdelani_m],0,2,"UTF-8");
    $vzdelani_zeny= mb_substr($c_vzdelani[$u->vzdelani_z],0,2,"UTF-8");
    $vzdelani= $vzdelani_muze==$vzdelani_zeny ? $vzdelani_muze : "$vzdelani_muze/$vzdelani_zeny";
//                                                         display("$vek_m/$vek_z=$vek");
    // konfese
    $cirkev= $u->cirkev_m==$u->cirkev_z
      ? ($u->cirkev_m==1 ? '' : ", {$c_cirkev[$u->cirkev_m]}")
      : ", {$c_cirkev[$u->cirkev_m]}/{$c_cirkev[$u->cirkev_z]}";
    // --------------------------------------------------------------  pro PDF
    $vps= ($u->funkce==1||$u->funkce==2 ? '* ' : ($u->_umi_vps ? '+ ' : ''));
    $key= str_pad($vzdelani_muze,2,' ',STR_PAD_LEFT).str_pad($vek_m,2,'0',STR_PAD_LEFT).$vps.$u->jmeno;
    list($prijmeni1,$etc)= explode(' ',$u->jmeno);
    if ( $etc ) $prijmeni1.= " ...";
    $result->pdf[$key]= array(
      'vps'=>$vps,'prijmeni'=>$prijmeni1,'jmena'=>"{$u->jmeno_m} a {$u->jmeno_z}");
    // --------------------------------------------------------------  pro XLS
    $r1= "$vps{$u->jmeno} {$u->jmeno_m} a {$u->jmeno_z} {$u->ucasti}";
    $r2= "věk:$vek, spolu:$spolu, dětí:$deti, {$u->mesto}, $vzdelani $cirkev";
    // atributy
    $r31= $u->aktivita_m;
    $r32= $u->aktivita_z;
    $r41= $u->zajmy_m;
    $r42= $u->zajmy_z;
    $r51= $u->zamest_m;
    $r52= $u->zamest_z;
    // listing
    $html.= "<table class='vypis' style='width:300px'>";
    $html.= "<tr><td colspan=2><b>$r1</b></td></tr>";
    $html.= "<tr><td colspan=2>$r2</td></tr>";
    $html.= "<tr><td>$r31</td><td>$r32</td></tr>";
    $html.= "<tr><td>$r41</td><td>$r42</td></tr>";
    $html.= "<tr><td>$r51</td><td>$r52</td></tr>";
    $html.= "</table><br/>";
    if ( $export ) {
      $excel[]= array($r1,$r2,$r31,$r41,$r51,$r32,$r42,$r52,$vzdelani_muze,$vek_m);
    }
  }
//                                                 debug($excel);
  if ( $export ) {
    $result->xhref= akce2_plachta_export($excel,'plachta');
  }
end:
  $result->html= "$err$html";
  return $result;
}
// ---------------------------------------------- roku
// vrací zaokrouhlený počet roku od narození poteď
function sql2roku($narozeni) {
  $roku= '';
  if ( $narozeni && $narozeni!='0000-00-00' ) {
    list($y,$m,$d)= explode('-',$narozeni);
    $now= time();
    $nar= mktime(0,0,0,$m,$d,$y)+1;
//     $roku= ($now-$nar)/(60*60*24*365.2425);
    $roku= ceil(($now-$nar)/(60*60*24*365.2425));
  }
  return $roku;
};
// ---------------------------------------------- stari
// vrací stáří v letech k danému datu (vše ve formátu sql
function sql2stari($narozeni,$datum) {
  $datum= $datum ?: date('Y-m-d');
  $roku= '';
  if ( $narozeni && $narozeni!='0000-00-00' ) {
    list($dy,$dm,$dd)= explode('-',$narozeni);
    list($ky,$km,$kd)= explode('-',$datum);
    $roku= ($km<$dm || ($km==$dm && $kd<$dd)) ? $ky-$dy-1 : $ky-$dy;
  }
  return $roku;
};
# ----------------------------------------------------------------------------- akce2_plachta_export
function akce2_plachta_export($line,$file) { trace();
  require_once('./ezer2.2/server/licensed/xls/OLEwriter.php');
  require_once('./ezer2.2/server/licensed/xls/BIFFwriter.php');
  require_once('./ezer2.2/server/licensed/xls/Worksheet.php');
  require_once('./ezer2.2/server/licensed/xls/Workbook.php');
  global $ezer_path_root;
  chdir($ezer_path_root);
  $name= cz2ascii("vypis_").date("Ymd_Hi");
  $table= "docs/$name.xls";
  try {
    $wb= new Workbook($table);
    // formáty
    $format_hd= $wb->add_format();
    $format_hd->set_bold();
    $format_hd->set_pattern();
    $format_hd->set_fg_color('silver');
    $format_dec= $wb->add_format();
    $format_dec->set_num_format("# ##0.00");
    $format_dat= $wb->add_format();
    $format_dat->set_num_format("d.m.yyyy");
    // list LK
    $ws= $wb->add_worksheet("Hodnoty");
    // hlavička
    $fields= explode(',','r1:20,r2:20,r31:20,r41:20,r51:20,r32:20,r42:20,r52:20,skola:8,vek:8');
    $sy= 0;
    foreach ($fields as $sx => $fa) {
      list($title,$width)= explode(':',$fa);
      $ws->set_column($sx,$sx,$width);
      $ws->write_string($sy,$sx,utf2win_sylk($title,true),$format_hd);
    }
    // data
    foreach($line as $x) {
      $sy++; $sx= 0;
      $ws->write_string($sy,$sx++,utf2win_sylk($x[0],true));
      $ws->write_string($sy,$sx++,utf2win_sylk($x[1],true));
      $ws->write_string($sy,$sx++,utf2win_sylk($x[2],true));
      $ws->write_string($sy,$sx++,utf2win_sylk($x[3],true));
      $ws->write_string($sy,$sx++,utf2win_sylk($x[4],true));
      $ws->write_string($sy,$sx++,utf2win_sylk($x[5],true));
      $ws->write_string($sy,$sx++,utf2win_sylk($x[6],true));
      $ws->write_string($sy,$sx++,utf2win_sylk($x[7],true));
      $ws->write_string($sy,$sx++,utf2win_sylk($x[8],true));
      $ws->write_number($sy,$sx++,$x[9]);
    }
    $wb->close();
    $html= " Výpis byl vygenerován ve formátu <a href='docs/$name.xls' target='xls'>Excel</a>.";
    $html.= " <br>Vygenerovaným listem <b>Hodnoty</b> je třeba nahradit stejnojmenný list v sešitu";
    $html.= " <b>doc/plachta14.xls</b> a dále postupovat podle návodu v listu <b>Návod</b>.";
  }
  catch (Exception $e) {
    $html.= nl2br("Chyba: ".$e->getMessage()." na ř.".$e->getLine());
  }
  return $html;
}
# ====================================================================================> . vyúčtování
# ------------------------------------------------------------------------------- akce2_sestava_noci
# generování sestavy přehledu člověkonocí pro účastníky $akce - páry
#   $cnd = podmínka
# počítané položky
#   manzele = rodina.nazev muz a zena
# generované vzorce
#   člověkolůžka, člověkopřistýlky
function akce2_sestava_noci($akce,$par,$title,$vypis,$export=false) { trace();
  // definice sloupců
  $result= (object)array();
  $tit= "Manželé:25,pokoj:8:r,dnů:5:r,nocí:5:r,lůžek:5:r:s,lůžko nocí:5:r:s,přis týlek:5:r:s,přis týlko nocí:5:r:s";
  $fld= "manzele,pokoj,pocetdnu,=noci,luzka,=luzkonoci,pristylky,=pristylkonoci";
  $ord= $par->ord ? $par->ord : "IF(funkce<=2,1,funkce),IF(pouze=0,r.nazev,o.prijmeni)";
  $cnd= $par->cnd;
  $html= '';
  $href= '';
  $n= 0;
  // dekódování parametrů
  $tits= explode(',',$tit);
  $flds= explode(',',$fld);
  $cond= 1;
  // získání dat - podle $kdo
  $clmn= array();       // pro hodnoty
  $expr= array();       // pro výrazy
  $suma= array();       // pro sumy sloupců id:::s
  $fmts= array();       // pro formáty sloupců id::f:
  for ($i= 0; $i<count($tits); $i++) {
    $idw= $tits[$i];
    $fld= $flds[$i];
    list($id,$w,$f,$sum)= explode(':',$idw);
    if ( $sum=='s' ) $suma[$fld]= 0;
    if ( isset($f) ) $fmts[$fld]= $f;
  }
  // data akce
  $qry=  "SELECT
            pokoj,luzka,pristylky,pocetdnu,
            r.id_rodina,prijmeni,jmeno,
            GROUP_CONCAT(DISTINCT IF(t.role='a',o.prijmeni,'') SEPARATOR '') as prijmeni_m,
            GROUP_CONCAT(DISTINCT IF(t.role='a',o.jmeno,'')    SEPARATOR '') as jmeno_m,
            GROUP_CONCAT(DISTINCT IF(t.role='b',o.prijmeni,'') SEPARATOR '') as prijmeni_z,
            GROUP_CONCAT(DISTINCT IF(t.role='b',o.jmeno,'')    SEPARATOR '') as jmeno_z,
            p.pouze,r.nazev
          FROM pobyt AS p
          JOIN spolu AS s USING(id_pobyt)
          JOIN osoba AS o ON s.id_osoba=o.id_osoba
          LEFT JOIN tvori AS t ON t.id_osoba=o.id_osoba
          LEFT JOIN rodina AS r ON r.id_rodina=IFNULL(i0_rodina,t.id_rodina)
          WHERE p.id_akce='$akce' AND funkce NOT IN (9,10,99) AND $cond
          GROUP BY id_pobyt
          ORDER BY $ord";
//   $qry.=  " LIMIT 1";
  $res= mysql_qry($qry);
  while ( $res && ($x= mysql_fetch_object($res)) ) {
//                                         debug($x,"hodnoty");
    $n++;
    $clmn[$n]= array();
    foreach($flds as $f) {
      $exp= ''; $val= 0;
      if ( substr($f,0,1)=='=' ) {
        switch ($f) {
        case '=noci':         $val= $x->pocetdnu;
                              $exp= "=[pocetdnu,0]"; break;
//         case '=noci':         $val= max(0,$x->pocetdnu-1);
//                               $exp= "=max(0,[pocetdnu,0]-1)"; break;
        case '=luzkonoci':    $val= ($x->pocetdnu)*$x->luzka;
                              $exp= "=[=noci,0]*[luzka,0]"; break;
        case '=pristylkonoci':$val= ($x->pocetdnu)*$x->pristylky;
                              $exp= "=[=noci,0]*[pristylky,0]"; break;
        default:              $val= '???'; break;
        }
        $clmn[$n][$f]= $val;
        if ( $exp ) $expr[$n][$f]= $exp;
      }
      else {
        switch ($f) {
        case 'manzele':
          $val= $x->pouze==1 ? "{$x->prijmeni_m} {$x->jmeno_m}"
             : ($x->pouze==2 ? "{$x->prijmeni_z} {$x->jmeno_z}"
             : ($x->id_rodina ? "{$x->nazev} {$x->jmeno_m} a {$x->jmeno_z}"
             : "{$x->prijmeni} {$x->jmeno}"));
          break;
        case 'jmena':
          $val= $x->pouze==1
              ? $x->jmeno_m : ($x->pouze==2 ? $x->jmeno_z : "{$x->jmeno_m} a {$x->jmeno_z}");
          break;
        case 'prijmeni':
          $val= $x->pouze==1 ? $x->prijmeni_m : ($x->pouze==2 ? $x->prijmeni_z : ($x->id_rodina ? $x->nazev : $x->prijmeni));
          break;
        default:
          $val= $f ? $x->$f : '';
          break;
        }
        if ( $f ) $clmn[$n][$f]= $val; else $clmn[$n][]= $val;
      }
      // případný výpočet sumy
      if ( isset($suma[$f]) ) {
         $suma[$f]+= $val;
      }
    }
  }

//                                         debug($clmn,"sestava pro $akce,$typ,$fld,$cnd");
//                                         debug($expr,"vzorce pro $akce,$typ,$fld,$cnd");
//                                         debug($suma,"sumy pro $akce B");
  // doplnění počtu pečovatelů do poznámky
  $pecounu= select1("COUNT(*)","pobyt JOIN spolu USING (id_pobyt)","id_akce='$akce' AND funkce IN (99)");
  $note= $pecounu ? "K údajům v tabulce je třeba přičíst ubytování <b>$pecounu</b> pečounů<br><br>" : "";
  // zobrazení tabulkou
  $tab= '';
  $thd= '';
  if ( $export ) {
    $result->tits= $tits;
    $result->flds= $flds;
    $result->clmn= $clmn;
    $result->expr= $expr;
  }
  else {
    // titulky
    foreach ($tits as $idw) {
      list($id)= explode(':',$idw);
      $ths.= "<th>$id</th>";
    }
    // data
    foreach ($clmn as $i=>$c) {
      $tab.= "<tr>";
      foreach ($c as $id=>$val) {
        $style= akce2_sestava_td_style($fmts[$id]);
        $tab.= "<td$style>$val</td>";
      }
      $tab.= "</tr>";
    }
    // sumy
    $sum= '';
    if ( count($suma)>0 ) {
      $sum.= "<tr>";
      foreach ($flds as $f) {
        $val= isset($suma[$f]) ? $suma[$f] : '';
        $sum.= "<th style='text-align:right'>$val</th>";
      }
      $sum.= "</tr>";
    }
    $result->html= "$note<div class='stat'><table class='stat'><tr>$ths</tr>$sum$tab</table></div>";
    $result->html.= "</br>";
    $result->href= $href;
  }
  return $result;
}
# ------------------------------------------------------------------------------- akce2_vyuctov_pary
# generování sestavy vyúčtování pro účastníky $akce - páry
#   $fld = seznam položek s prefixem
#   $cnd = podmínka
# počítané položky
#   manzele = rodina.nazev muz a zena
# generované vzorce
#   platit = součet předepsaných plateb
function akce2_vyuctov_pary($akce,$par,$title,$vypis,$export=false) { trace();
  $ord= $par->ord ? $par->ord : "IF(funkce<=2,1,funkce),IF(pouze=0,r.nazev,o.prijmeni)";
  $result= (object)array();
  $tit= "Manželé:25"
//       . ",id_pobyt"
      . ",pokoj:7,dětí:5:r,lůžka:5:r:s,přis týlky:5:r:s,kočá rek:5:r:s,nocí:5:r:s"
      . ",str. celá:5:r:S,str. pol.:5:r:s"
      . ",platba ubyt.:7:r:s,platba strava:7:r:s,platba režie:7:r:s,sleva:7:r:s,CD:6:r:s,celkem:7:r:s"
      . ",na účet:7:r:s,datum platby:10:d"
      . ",nedo platek:6:r:s,č.příspěvky:6,pokladna:6:r:s,datum platby:10:d,přepl.:6:r:s,poznámka:50,SPZ:9,.:7"
      . ",ubyt.:8:r:s,DPH:6:r:s,strava:8:r:s,DPH:6:r:s,režie:8:r:s,zapla ceno:8:r:s"
      . ",dota ce:6:r:s,nedo platek:6:r:s,dar:7:r:s,rozpočet organizace:10:r:s"
      . "";
  $fld= "manzele"
//       . ",id_pobyt"
      . ",pokoj,_deti,luzka,pristylky,kocarek,=pocetnoci,strava_cel,strava_pol"
      . ",platba1,platba2,platba3,platba4,=cd,=platit,=uctem,=datucet"
      . ",=nedoplatek,prispevky,=pokladna,=datpokl,=preplatek,poznamka,spz,"
      . ",=ubyt,=ubytDPH,=strava,=stravaDPH,=rezie,=zaplaceno,=dotace,=nedopl,=dar,=naklad"
      . "";
  $cnd= 1;
  $html= '';
  $href= '';
  $n= 0;
  // dekódování parametrů
  $tits= explode(',',$tit);
  $flds= explode(',',$fld);
  $cond= 1;
  // získání dat - podle $kdo
  $clmn= array();       // pro hodnoty
  $expr= array();       // pro výrazy
  $suma= array();       // pro sumy sloupců id:::s
  $fmts= array();       // pro formáty sloupců id::f:
  for ($i= 0; $i<count($tits); $i++) {
    $idw= $tits[$i];
    $fld= $flds[$i];
    list($id,$w,$f,$sum)= explode(':',$idw);
    if ( $sum=='s' ) $suma[$fld]= 0;
    if ( isset($f) ) $fmts[$fld]= $f;
  }
  // data akce
  $qry=  "SELECT id_pobyt,
          p.pouze,pokoj,luzka,pristylky,kocarek,pocetdnu,strava_cel,strava_pol,
            platba1,platba2,platba3,platba4,
            platba,zpusobplat,c.ikona as pokladnou,datplatby,
            cd,p.poznamka,
          r.nazev as nazev,r.ulice,r.psc,r.obec,r.telefony,r.emaily,r.spz,
          SUM(IF(t.role='d',1,0)) as _deti,
          GROUP_CONCAT(DISTINCT IF(t.role='a',o.clen,'')     SEPARATOR '') as clen_m,
          GROUP_CONCAT(DISTINCT IF(t.role='a',o.prijmeni,'') SEPARATOR '') as prijmeni_m,
          GROUP_CONCAT(DISTINCT IF(t.role='a',o.jmeno,'')    SEPARATOR '') as jmeno_m,
          GROUP_CONCAT(DISTINCT IF(t.role='a',o.narozeni,'') SEPARATOR '') as narozeni_m,
          GROUP_CONCAT(DISTINCT IF(t.role='a',o.rc_xxxx,'')  SEPARATOR '') as rc_xxxx_m,
          GROUP_CONCAT(DISTINCT IF(t.role='b',o.prijmeni,'') SEPARATOR '') as prijmeni_z,
          GROUP_CONCAT(DISTINCT IF(t.role='b',o.jmeno,'')    SEPARATOR '') as jmeno_z,
          GROUP_CONCAT(DISTINCT IF(t.role='b',o.narozeni,'') SEPARATOR '') as narozeni_z,
          GROUP_CONCAT(DISTINCT IF(t.role='b',o.rc_xxxx,'')  SEPARATOR '') as rc_xxxx_z,
          IF(MAX(clen)>0,SUM(d.castka),'-') AS prispevky
          FROM pobyt AS p
          JOIN spolu AS s USING(id_pobyt)
          JOIN osoba AS o ON s.id_osoba=o.id_osoba
          LEFT JOIN tvori AS t ON t.id_osoba=o.id_osoba
          LEFT JOIN rodina AS r USING(id_rodina)
          JOIN akce AS a ON a.id_duakce=p.id_akce
          LEFT JOIN dar AS d ON d.id_osoba=s.id_osoba AND d.ukon='p'
            AND YEAR(a.datum_do) BETWEEN YEAR(d.dat_od) AND YEAR(d.dat_do)
          JOIN _cis AS c ON c.druh='ms_akce_platba' AND c.data=zpusobplat
          WHERE p.id_akce='$akce' AND funkce!=99 AND $cond
          GROUP BY id_pobyt
          ORDER BY $ord";
//   $qry.=  " LIMIT 10";
  $res= mysql_qry($qry);
  while ( $res && ($x= mysql_fetch_object($res)) ) {
//                                         debug($x,"hodnoty");
    $n++;
    $clmn[$n]= array();
    $DPH1= 0.1;
    $DPH2= 0.2;
    foreach($flds as $f) {
      $exp= ''; $val= 0;
      if ( substr($f,0,1)=='=' ) {
        //            ubyt.         strava        režie         sleva
        $predpis= $x->platba1 + $x->platba2 + $x->platba3 + $x->platba4;
        $preplatek= $x->platba > $predpis ? $x->platba - $predpis : '';
        $nedoplatek= $x->platba < $predpis ? $predpis - $x->platba : '';
        $naklad= $predpis - $x->platba4;
        switch ($f) {
        case '=pocetnoci':  $val= max(0,$x->pocetdnu);
                            break;
        case '=platit':     $val= $predpis;
                            $exp= "=[platba1,0]+[platba2,0]+[platba3,0]+[platba4,0]"; break;
        case '=preplatek':  $val= $preplatek;
                            $exp= "=IF([=pokladna,0]+[=uctem,0]>[=platit,0],[=pokladna,0]+[=uctem,0]-[=platit,0],0)"; break;
        case '=nedoplatek': $val= $nedoplatek;
                            $exp= "=IF([=zaplaceno,0]<[=platit,0],[=platit,0]-[=zaplaceno,0],0)"; break;
        case '=uctem':      $val= $x->pokladnou ? '' : 0+$x->platba; break;
        case '=datucet':    $val= $x->pokladnou ? '' : $x->datplatby; break;
        case '=pokladna':   $val= $x->pokladnou ? 0+$x->platba : ''; break;
        case '=datpokl':    $val= $x->pokladnou ? $x->datplatby : ''; break;
        case '=cd':         $val= 100.00*$x->cd; break;
        case '=ubyt':       $val= round($x->platba1/(1+$DPH1));
                            $exp= "=ROUND([platba1,0]/(1+$DPH1),0)"; break;
        case '=ubytDPH':    $val= round($x->platba1*$DPH1/(1+$DPH1));
                            $exp= "=[platba1,0]-[=ubyt,0]"; break;
        case '=strava':     $val= round($x->platba2/(1+$DPH2));
                            $exp= "=ROUND([platba2,0]/(1+$DPH2),0)"; break;
        case '=stravaDPH':  $val= round($x->platba2*$DPH2/(1+$DPH2));
                            $exp= "=[platba2,0]-[=strava,0]"; break;
        case '=rezie':      $val= 0+$x->platba3;
                            $exp= "=[platba3,0]"; break;
        case '=zaplaceno':  $val= 0+$x->platba;
                            $exp= "=[=uctem,0]+[=pokladna,0]"; break;
        case '=dotace':     $val= -$x->platba4;
                            $exp= "=-[platba4,0]"; break;
        case '=nedopl':     $val= $nedoplatek;
                            $exp= "=IF([=zaplaceno,0]<[=platit,0],[=platit,0]-[=zaplaceno,0],0)"; break;
        case '=dar':        $val= $preplatek;
                            $exp= "=IF([=zaplaceno,0]>[=platit,0],[=zaplaceno,0]-[=platit,0],0)"; break;
        case '=naklad':     $val= $naklad;
                            $exp= "=[=platit,0]-[platba4,0]"; break;
        default:            $val= '???'; break;
        }
        $clmn[$n][$f]= $val;
        if ( $exp ) $expr[$n][$f]= $exp;
      }
      else {
        switch ($f) {
        case 'manzele':
          $val= $x->pouze==1 ? "{$x->prijmeni_m} {$x->jmeno_m}"
             : ($x->pouze==2 ? "{$x->prijmeni_z} {$x->jmeno_z}"
             : "{$x->nazev} {$x->jmeno_m} a {$x->jmeno_z}");
          break;
        case 'jmena':
          $val= $x->pouze==1
              ? $x->jmeno_m : ($x->pouze==2 ? $x->jmeno_z : "{$x->jmeno_m} a {$x->jmeno_z}");
          break;
        case 'prijmeni':
          $val= $x->pouze==1 ? $x->prijmeni_m : ($x->pouze==2 ? $x->prijmeni_z : $x->nazev);
          break;
        default:
          $val= $f ? $x->$f : '';
          break;
        }
        if ( $f ) $clmn[$n][$f]= $val; else $clmn[$n][]= $val;
      }
      // případný výpočet sumy
      if ( isset($suma[$f]) ) {
         $suma[$f]+= $val;
      }
    }
  }
//                                         debug($clmn,"sestava pro $akce,$typ,$fld,$cnd");
//                                         debug($expr,"vzorce pro $akce,$typ,$fld,$cnd");
//                                         debug($suma,"sumy pro $akce B");
  // zobrazení tabulkou
  $tab= '';
  $thd= '';
  if ( $export ) {
    $result->tits= $tits;
    $result->flds= $flds;
    $result->clmn= $clmn;
    $result->expr= $expr;
    $result->DPH= array(
      "základ","=[=ubyt,s]+[=strava,s]+[=rezie,s]"
     ,"DPH ".($DPH2*100)."%","=[=stravaDPH,s]"
     ,"DPH ".($DPH1*100)."%","=[=ubytDPH,s]"
     ,"předpis celkem","=[=ubyt,s]+[=strava,s]+[=rezie,s]+[=stravaDPH,s]+[=ubytDPH,s]"
   );
  }
  else {
    // titulky
    foreach ($tits as $idw) {
      list($id)= explode(':',$idw);
      $ths.= "<th>$id</th>";
    }
    // data
    foreach ($clmn as $i=>$c) {
      $tab.= "<tr>";
      foreach ($c as $id=>$val) {
        $style= akce2_sestava_td_style($fmts[$id]);
        $tab.= "<td$style>$val</td>";
      }
      $tab.= "</tr>";
    }
    // sumy
    $sum= '';
    if ( count($suma)>0 ) {
      $sum.= "<tr>";
      foreach ($flds as $f) {
        $val= isset($suma[$f]) ? $suma[$f] : '';
        $sum.= "<th style='text-align:right'>$val</th>";
      }
      $sum.= "</tr>";
    }
    $result->html= "<div class='stat'><table class='stat'><tr>$ths</tr>$sum$tab</table></div>";
    $result->html.= "</br>";
    $result->href= $href;
  }
  return $result;
}
# ------------------------------------------------------------------------------ akce2_vyuctov_pary2
# generování sestavy vyúčtování pro účastníky $akce - bez DPH
#   $fld = seznam položek s prefixem
#   $cnd = podmínka
# počítané položky
#   manzele = rodina.nazev muz a zena
# generované vzorce
#   platit = součet předepsaných plateb
function akce2_vyuctov_pary2($akce,$par,$title,$vypis,$export=false) { trace();
  $ord= $par->ord ? $par->ord : "IF(funkce<=2,1,funkce),IF(pouze=0,r.nazev,o.prijmeni)";
  $result= (object)array();
  $tit= "Manželé:25"
      . ",pokoj:7,dětí:5:r,lůžka:5:r:s,přis týlky:5:r:s,kočá rek:5:r:s,nocí:5:r:s"
      . ",str. celá:5:r:S,str. pol.:5:r:s"
      . ",poplatek dospělí:8:r:s"
      . ",na účet:7:r:s,datum platby:10:d"
      . ",poplatek děti:8:r:s,na účet děti:7:r:s,datum platby děti:10:d"
      . ",nedo platek:7:r:s,pokladna:6:r:s,přepl.:6:r:s,poznámka:50,SPZ:9"
      . ",rozpočet dospělí:10:r:s,rozpočet děti:10:r:s"
      . "";
  $fld= "manzele"
      . ",pokoj,_deti,luzka,pristylky,kocarek,=pocetnoci,strava_cel,strava_pol"
      . ",=platit,platba,datplatby"
      . ",poplatek_d,platba_d,datplatby_d"
      . ",=nedoplatek,=pokladna,=preplatek,poznamka,spz"
      . ",=naklad,naklad_d"
      . "";
  $cnd= 1;
  $html= '';
  $href= '';
  $n= 0;
  // dekódování parametrů
  $tits= explode(',',$tit);
  $flds= explode(',',$fld);
  $cond= 1;
  // získání dat - podle $kdo
  $clmn= array();       // pro hodnoty
  $expr= array();       // pro výrazy
  $suma= array();       // pro sumy sloupců id:::s
  $fmts= array();       // pro formáty sloupců id::f:
  for ($i= 0; $i<count($tits); $i++) {
    $idw= $tits[$i];
    $fld= $flds[$i];
    list($id,$w,$f,$sum)= explode(':',$idw);
    if ( $sum=='s' ) $suma[$fld]= 0;
    if ( isset($f) ) $fmts[$fld]= $f;
  }
  // data akce
  $qry=  "SELECT id_pobyt,platba_d,datplatby_d,poplatek_d,naklad_d,
          p.pouze,pokoj,luzka,pristylky,kocarek,pocetdnu,strava_cel,strava_pol,
            platba1,platba2,platba3,platba4,platba,datplatby,cd,p.poznamka,
          r.nazev as nazev,r.ulice,r.psc,r.obec,r.telefony,r.emaily,r.spz,
          SUM(IF(t.role='d',1,0)) as _deti,
          GROUP_CONCAT(DISTINCT IF(t.role='a',o.prijmeni,'') SEPARATOR '') as prijmeni_m,
          GROUP_CONCAT(DISTINCT IF(t.role='a',o.jmeno,'')    SEPARATOR '') as jmeno_m,
          GROUP_CONCAT(DISTINCT IF(t.role='a',o.narozeni,'') SEPARATOR '') as narozeni_m,
          GROUP_CONCAT(DISTINCT IF(t.role='a',o.rc_xxxx,'')  SEPARATOR '') as rc_xxxx_m,
          GROUP_CONCAT(DISTINCT IF(t.role='b',o.prijmeni,'') SEPARATOR '') as prijmeni_z,
          GROUP_CONCAT(DISTINCT IF(t.role='b',o.jmeno,'')    SEPARATOR '') as jmeno_z,
          GROUP_CONCAT(DISTINCT IF(t.role='b',o.narozeni,'') SEPARATOR '') as narozeni_z,
          GROUP_CONCAT(DISTINCT IF(t.role='b',o.rc_xxxx,'')  SEPARATOR '') as rc_xxxx_z
          FROM pobyt AS p
          JOIN spolu AS s USING(id_pobyt)
          JOIN osoba AS o ON s.id_osoba=o.id_osoba
          LEFT JOIN tvori AS t ON t.id_osoba=o.id_osoba
          LEFT JOIN rodina AS r ON r.id_rodina=IFNULL(i0_rodina,t.id_rodina)
          WHERE p.id_akce='$akce' AND funkce!=99 AND $cond
          GROUP BY id_pobyt
          ORDER BY $ord";
//   $qry.=  " LIMIT 10";
  $res= mysql_qry($qry);
  while ( $res && ($x= mysql_fetch_object($res)) ) {
//                                         debug($x,"hodnoty");
    $n++;
    $clmn[$n]= array();
    $DPH1= 0.1;
    $DPH2= 0.2;
    foreach($flds as $f) {
      $exp= ''; $val= 0;
      if ( substr($f,0,1)=='=' ) {
        //            ubyt.         strava        režie         sleva
        $predpis= $x->platba1 + $x->platba2 + $x->platba3 + $x->platba4;
        $predpis_d= $predpis + $x->poplatek_d;
        $platba= $x->platba + $x->platba_d;
        $preplatek= $platba > $predpis_d ? $platba - $predpis_d : '';
        $nedoplatek= $platba < $predpis_d ? $predpis_d - $platba : '';
        $naklad= $predpis - $x->platba4;
        switch ($f) {
        case '=pocetnoci':  $val= max(0,$x->pocetdnu);
                            break;
        case '=platit':     $val= $predpis;
//                             $exp= "=[platba1,0]+[platba2,0]+[platba3,0]+[platba4,0]";
                            break;
        case '=preplatek':  $val= $preplatek;
                            $exp= "=IF([platba,0]+[platba_d,0]>[=platit,0]+[poplatek_d,0]"
                                . ",[platba,0]+[platba_d,0]-[=platit,0]-[poplatek_d,0],0)"; break;
        case '=nedoplatek': $val= $nedoplatek;
                            $exp= "=IF([platba,0]+[platba_d,0]<[=platit,0]+[poplatek_d,0]"
                                . ",[=platit,0]+[poplatek_d,0]-[platba,0]-[platba_d,0],0)"; break;
        case '=pokladna':   $val= ''; break;
        case '=cd':         $val= 100.00*$x->cd; break;
        case '=ubyt':       $val= round($x->platba1/(1+$DPH1));
                            $exp= "=ROUND([platba1,0]/(1+$DPH1),0)"; break;
//         case '=ubytDPH':    $val= round($x->platba1*$DPH1/(1+$DPH1));
//                             $exp= "=[platba1,0]-[=ubyt,0]"; break;
        case '=strava':     $val= round($x->platba2/(1+$DPH2));
                            $exp= "=ROUND([platba2,0]/(1+$DPH2),0)"; break;
//         case '=stravaDPH':  $val= round($x->platba2*$DPH2/(1+$DPH2));
//                             $exp= "=[platba2,0]-[=strava,0]"; break;
        case '=rezie':      $val= 0+$x->platba3;
                            $exp= "=[platba3,0]"; break;
        case '=zaplaceno':  $val= 0+$x->platba;
                            $exp= "=[platba,0]+[=pokladna,0]"; break;
        case '=dotace':     $val= -$x->platba4;
                            $exp= "=-[platba4,0]"; break;
//         case '=nedopl':     $val= $nedoplatek;
//                             $exp= "=IF([platba,0]<[=platit,0],[=platit,0]-[=zaplaceno,0],0)"; break;
        case '=dar':        $val= $preplatek;
                            $exp= "=IF([=zaplaceno,0]>[=platit,0],[=zaplaceno,0]-[=platit,0],0)"; break;
        case '=naklad':     $val= $naklad; break;
        default:            $val= '???'; break;
        }
        $clmn[$n][$f]= $val;
        if ( $exp ) $expr[$n][$f]= $exp;
      }
      else {
        switch ($f) {
        case 'manzele':
          $val= $x->pouze==1 ? "{$x->prijmeni_m} {$x->jmeno_m}"
             : ($x->pouze==2 ? "{$x->prijmeni_z} {$x->jmeno_z}"
             : "{$x->nazev} {$x->jmeno_m} a {$x->jmeno_z}");
          break;
        case 'jmena':
          $val= $x->pouze==1
              ? $x->jmeno_m : ($x->pouze==2 ? $x->jmeno_z : "{$x->jmeno_m} a {$x->jmeno_z}");
          break;
        case 'prijmeni':
          $val= $x->pouze==1 ? $x->prijmeni_m : ($x->pouze==2 ? $x->prijmeni_z : $x->nazev);
          break;
        default:
          $val= $f ? $x->$f : '';
          break;
        }
        if ( $f ) $clmn[$n][$f]= $val; else $clmn[$n][]= $val;
      }
      // případný výpočet sumy
      if ( isset($suma[$f]) ) {
         $suma[$f]+= $val;
      }
    }
  }
//                                         debug($clmn,"sestava pro $akce,$typ,$fld,$cnd");
//                                         debug($expr,"vzorce pro $akce,$typ,$fld,$cnd");
//                                         debug($suma,"sumy pro $akce B");
  // zobrazení tabulkou
  $tab= '';
  $thd= '';
  if ( $export ) {
    $result->tits= $tits;
    $result->flds= $flds;
    $result->clmn= $clmn;
    $result->expr= $expr;
    $result->X= array(
      "Přehled dotace"
      ,"dotace dospělí","=[=naklad,s]-[=platit,s]"
      ,"dotace děti","=[naklad_d,s]-[poplatek_d,s]"
   );
  }
  else {
    // titulky
    foreach ($tits as $idw) {
      list($id)= explode(':',$idw);
      $ths.= "<th>$id</th>";
    }
    // data
    foreach ($clmn as $i=>$c) {
      $tab.= "<tr>";
      foreach ($c as $id=>$val) {
        $style= akce2_sestava_td_style($fmts[$id]);
        $tab.= "<td$style>$val</td>";
      }
      $tab.= "</tr>";
    }
    // sumy
    $sum= '';
    if ( count($suma)>0 ) {
      $sum.= "<tr>";
      foreach ($flds as $f) {
        $val= isset($suma[$f]) ? $suma[$f] : '';
        $sum.= "<th style='text-align:right'>$val</th>";
      }
      $sum.= "</tr>";
    }
    $result->html= "<div class='stat'><table class='stat'><tr>$ths</tr>$sum$tab</table></div>";
    $result->html.= "</br>";
    $result->href= $href;
  }
  return $result;
}
# =====================================================================================> . XLS tisky
# ---------------------------------------------------------------------------------- tisk2_vyp_excel
# generování tabulky do excelu
function tisk2_vyp_excel($akce,$par,$title,$vypis,$tab=null) {  trace();
  global $xA, $xn;
  $result= (object)array('_error'=>0);
  $html= '';
  // získání dat
  $title= str_replace('&nbsp;',' ',$title);
  if ( !$tab )
    $tab= tisk2_sestava($akce,$par,$title,$vypis,true);
  // vlastní export do Excelu
  $name= cz2ascii("vypis_").date("Ymd_Hi");
  $xls= <<<__XLS
    |open $name
    |sheet vypis;;L;page
    |A1 $title ::bold size=14 |A2 $vypis ::bold size=12
__XLS;
  // titulky a sběr formátů
  $fmt= $sum= array();
  $n= 4;
  $lc= 0;
  $clmns= $del= '';
  $xA= array();                                 // překladová tabulka: název sloupce => písmeno
  if ( $tab->flds ) foreach ($tab->flds as $f) {
    $A= Excel5_n2col($lc);
    $xA[$f]= $A;
    $lc++;
  }
  $lc= 0;
  if ( $tab->tits ) foreach ($tab->tits as $idw) {
    if ( $idw=='^' ) continue;
    $A= Excel5_n2col($lc);
    list($id,$w,$f,$s)= explode(':',$idw);      // název sloupce : šířka : formát : suma
    if ( $f ) $fmt[$A]= $f;
    if ( $s ) $sum[$A]= true;
    $xls.= "|$A$n $id";
    if ( $w ) {
      $clmns.= "$del$A=$w";
      $del= ',';
    }
    $lc++;
  }
  if ( $clmns ) $xls.= "\n|columns $clmns ";
  $xls.= "\n|A$n:$A$n bcolor=ffc0e2c2 wrap border=+h|A$n:$A$n border=t\n";
  $n1= $n= 5;                                   // první řádek dat (pro sumy)
  // datové řádky
  if ( $tab->clmn ) foreach ($tab->clmn as $i=>$c) {
    $xls.= "\n";
    $lc= 0;
    foreach ($c as $id=>$val) {
      if ( $id[0]=='^' ) continue;
      $A= Excel5_n2col($lc);
      $format= '';
      if (isset($tab->expr[$i][$id]) ) {
        // buňka obsahuje vzorec
        $val= $tab->expr[$i][$id];
        $format.= ' bcolor=ffdddddd';
        $xn= $n;
        $val= preg_replace_callback("/\[([^,]*),([^\]]*)\]/","akce_vyp_subst",$val);
      }
      else {
        // buňka obsahuje hodnotu
        $val= strtr($val,array("\n\r"=>"  ","®"=>""));
        if ( isset($fmt[$A]) ) {
          switch ($fmt[$A]) {
          // aplikace formátů
          case 'l':                      $format.= ' left'; break;
          case 'd': $val= sql2xls($val); $format.= ' right date'; break;
          }
        }
      }
      $format= $format ? "::$format" : '';
      $val= str_replace("\n","{}",$val);        // ochrana proti řádkům v hodnotě - viz ae_slib
      $xls.= "|$A$n $val $format";
      $lc++;
    }
    $n++;
  }
  $n--;
  $xls.= "\n|A$n1:$A$n border=+h|A$n1:$A$n border=t";
  // sumy sloupců
  if ( count($sum) ) {
    $xls.= "\n";
    $nn= $n;
    $ns= $n+2;
    foreach ($sum as $A=>$x) {
      $xls.= "|$A$ns =SUM($A$n1:$A$nn) :: bcolor=ffdddddd";
    }
  }
  // tabulka DPH, pokud je
  if ( $tab->DPH ) {
    $n+= 3;
    $nd1= $n;
    $xls.= "\n|A$n Tabulka DPH :: bcolor=ffc0e2c2 |A$n:B$n merge center\n";
    $n++;
    $nd= $n;
    for($i= 0; $i<count($tab->DPH); $i+= 2) {
      $lab= $tab->DPH[$i];
      $exp= $tab->DPH[$i+1];
      $xn= $ns;
      $exp= preg_replace_callback("/\[([^,]*),([^\]]*)\]/","akce_vyp_subst",$exp);
      $xls.= "|A$n $lab ::right|B$n $exp :: bcolor=ffdddddd";
      $n++;
    }
    $n--;
    $xls.= "\n|A$nd:B$n border=+h|A$nd1:B$n border=t";
  }
  // tabulka X, pokud je
  if ( $tab->X ) {
    $n+= 3;
    $nd1= $n;
    $xls.= "\n|A$n {$tab->X[0]} :: bcolor=ffc0e2c2 |A$n:B$n merge center\n";
    $n++;
    $nd= $n;
    for($i= 1; $i<count($tab->X); $i+= 2) {
      $lab= $tab->X[$i];
      $exp= $tab->X[$i+1];
      $xn= $ns;
      $exp= preg_replace_callback("/\[([^,]*),([^\]]*)\]/","akce_vyp_subst",$exp);
      $xls.= "|A$n $lab ::right|B$n $exp :: bcolor=ffdddddd";
      $n++;
    }
    $n--;
    $xls.= "\n|A$nd:B$n border=+h|A$nd1:B$n border=t";
  }
  // časová značka
  $kdy= date("j. n. Y v H:i");
  $n+= 2;
  $xls.= "|A$n Výpis byl vygenerován $kdy :: italic";
  // konec
  $xls.= <<<__XLS
    \n|close
__XLS;
  // výstup
                                                                display($xls);
  $inf= Excel5($xls,1);
  if ( $inf ) {
    $html= " se nepodařilo vygenerovat - viz začátek chybové hlášky";
    fce_error($inf);
  }
  else {
    $html= " Výpis byl vygenerován ve formátu <a href='docs/$name.xls' target='xls'>Excel</a>.";
  }
  $result->html= $html;
  return $result;
}
# ---------------------------------------------------- akce_vyp_subst
function akce_vyp_subst($matches) { trace();
  global $xA, $xn;
//                                                 debug($xA);
//                                                 debug($matches);
  if ( !isset($xA[$matches[1]]) ) fce_error("akce_vyp_excel: chybný název sloupce '{$matches[1]}'");
  $A= $xA[$matches[1]];
  $n= $xn+$matches[2];
  return "$A$n";
}
# ---------------------------------------------------- sql2xls
// datum bez dne v týdnu
function sql2xls($datum) {
  // převeď sql tvar na uživatelskou podobu (default)
  $text= ''; $del= '.';
  if ( $datum && substr($datum,0,10)!='0000-00-00' ) {
    $y=substr($datum,0,4);
    $m=substr($datum,5,2);
    $d=substr($datum,8,2);
    $text.= date("j{$del}n{$del}Y",strtotime($datum));
  }
  return $text;
}
# =====================================================================================> . PDF tisky
# ------------------------------------------------------------------------------- tisk2_pdf_jmenovky
# vygenerování PDF s vizitkami s rozměrem 55x90 na rozstříhání
#   $the_json obsahuje  title:'{jmeno}<br>{prijmeni}'
function tisk2_pdf_jmenovky($akce,$par,$title,$vypis,$report_json) {  trace();
  global $ezer_path_docs;
  $result= (object)array('_error'=>0);
  $html= '';
  // získání dat
  mb_internal_encoding('UTF-8');
  $tab= tisk2_sestava($akce,$par,$title,$vypis,true);
//                                         display($report_json);
//                                         debug($tab,"tisk2_sestava($akce,...)"); //return;
  $report_json= "{'format':'A4:15,10,90,55','boxes':["
    . "{'type':'text','left':0,'top':0,'width':90,'height':55,'id':'ram','style':'1,L,LTRB:0.05 dotted 250',txt:' '},"
    . "{'type':'text','left':10,'top':10,'width':80,'height':40,'id':'jmeno','txt':'{jmeno}<br />{prijmeni}','style':'30,L'}]}";
  $report_json= "{'format':'A4:15,10,90,55','boxes':["
    . "{'type':'text','left':0,'top':0,'width':90,'height':55,'id':'ram','style':'1,L,LTRB:0.05 dotted',txt:' '},"
    . "{'type':'text','left':10,'top':10,'width':80,'height':40,'id':'jmeno','txt':'{jmeno}<br />{prijmeni}','style':'30,L'}]}";
//                                         display($report_json);
  // projdi vygenerované záznamy
  $n= 0;
  $parss= array();
  foreach ( $tab->clmn as $xa ) {
    // definice pole substitucí
    $x= (object)$xa;
    $parss[$n]= (object)array();
    $fsize= mb_strlen($x->jmeno)>9 ? 12 : (mb_strlen($x->jmeno)>8 ? 13 : 14);
    $parss[$n]->jmeno= "<span style=\"font-size:{$fsize}mm;font-weight:bold\">{$x->jmeno}</span>";
    list($prijmeni)= explode(' ',$x->prijmeni);
    $fsize= mb_strlen($prijmeni)>10 ? 10 : 12;
    $parss[$n]->prijmeni= "<span style=\"font-size:{$fsize}mm;font-weight:bold\">{$prijmeni}</span>";
    $n++;
  }
  // předání k tisku
  $fname= 'jmenovky_'.date("Ymd_Hi");
  $fpath= "$ezer_path_docs/$fname.pdf";
  dop_rep_ids($report_json,$parss,$fpath);
  $result->html= " Výpis byl vygenerován ve formátu <a href='docs/$fname.pdf' target='pdf'>PDF</a>.";
  return $result;
}
# --------------------------------------------------------------------------------- akce2_pdf_stitky
# vygenerování PDF se samolepkami - adresními štítky
#   $the_json obsahuje  title:'{jmeno_postovni}<br>{adresa_postovni}'
function akce2_pdf_stitky($akce,$par,$report_json) { trace();
  global $ezer_path_docs;
  $ret= (object)array('_error'=>0,'html'=>'testy');
  $par->fld= "prijmeni,rodice,ulice,psc,obec,stat";
  // projdi požadované adresy rodin
  $tab= tisk2_sestava_pary($akce,$par,'PDF',$vypis,true);
//                                                         debug($par);
//                                                         debug($tab->clmn); //goto end;
  $parss= array(); $n= 0;
  foreach ($tab->clmn as $x) {
    $jmena= $x['rodice'];
    $ulice= str_replace('®','',$x['ulice']);
    $psc=   str_replace('®','',$x['psc']);
    $obec=  str_replace('®','',$x['obec']);
    $stat=  str_replace('®','',$x['stat']);
    $stat= $stat=='CZ' ? '' : $stat;
    // definice pole substitucí
    $parss[$n]= (object)array();
    $parss[$n]->jmeno_postovni= $jmena;
    $parss[$n]->adresa_postovni= "$ulice<br/>$psc  $obec".( $stat ? "<br/>        $stat" : "");
    $n++;
  }
//                                                         debug($parss);
  // předání k tisku
  $fname= 'stitky_'.date("Ymd_Hi");
  $fpath= "$ezer_path_docs/$fname.pdf";
  dop_rep_ids($report_json,$parss,$fpath);
  $ret->html= " Výpis byl vygenerován ve formátu <a href='docs/$fname.pdf' target='pdf'>PDF</a>.";
end:
  return $ret;
}
# --------------------------------------------------------------------------------- akce2_pdf_stitky
# vygenerování PDF se samolepkami - adresními štítky
#   $the_json obsahuje  title:'{jmeno_postovni}<br>{adresa_postovni}'
function xxx_akce2_pdf_stitky($cond,$report_json) { trace();
  global $json, $ezer_path_docs;
  $result= (object)array('_error'=>0);
  // projdi požadované adresy rodin
  $n= 0;
  $parss= array();
  $qry=  "SELECT
          r.nazev as nazev,p.pouze as pouze,
          GROUP_CONCAT(DISTINCT IF(t.role='a',o.prijmeni,'') SEPARATOR '') as prijmeni_m,
          GROUP_CONCAT(DISTINCT IF(t.role='a',o.jmeno,'')    SEPARATOR '') as jmeno_m,
          GROUP_CONCAT(DISTINCT IF(t.role='a',o.narozeni,'') SEPARATOR '') as narozeni_m,
          GROUP_CONCAT(DISTINCT IF(t.role='a',o.rc_xxxx,'')  SEPARATOR '') as rc_xxxx_m,
          GROUP_CONCAT(DISTINCT IF(t.role='b',o.prijmeni,'') SEPARATOR '') as prijmeni_z,
          GROUP_CONCAT(DISTINCT IF(t.role='b',o.jmeno,'')    SEPARATOR '') as jmeno_z,
          GROUP_CONCAT(DISTINCT IF(t.role='b',o.narozeni,'') SEPARATOR '') as narozeni_z,
          GROUP_CONCAT(DISTINCT IF(t.role='b',o.rc_xxxx,'')  SEPARATOR '') as rc_xxxx_z,
          r.ulice,r.psc,r.obec,r.stat,r.telefony,r.emaily,p.poznamka
          FROM pobyt AS p
          JOIN spolu AS s USING(id_pobyt)
          JOIN osoba AS o ON s.id_osoba=o.id_osoba
          LEFT JOIN tvori AS t ON t.id_osoba=o.id_osoba
          LEFT JOIN rodina AS r USING(id_rodina)
          WHERE $cond
          GROUP BY id_pobyt
          ORDER BY IF(funkce<=2,1,funkce),IF(pouze=0,r.nazev,o.prijmeni)";
  $res= mysql_qry($qry);
  while ( $res && ($x= mysql_fetch_object($res)) ) {
    $x->prijmeni= $x->pouze==1 ? $x->prijmeni_m : ($x->pouze==2 ? $x->prijmeni_z : $x->nazev);
    $x->jmena=    $x->pouze==1 ? $x->jmeno_m    : ($x->pouze==2 ? $x->jmeno_z : "{$x->jmeno_m} a {$x->jmeno_z}");
    // formátované PSČ (tuzemské a slovenské)
    $psc= (!$x->stat||$x->stat=='CZ'||$x->stat=='SK')
      ? substr($x->psc,0,3).' '.substr($x->psc,3,2)
      : $x->psc;
    $stat= $x->stat=='CZ' ? '' : $x->stat;
    // definice pole substitucí
    $parss[$n]= (object)array();
    $parss[$n]->jmeno_postovni= "{$x->jmena} {$x->prijmeni}";
    $parss[$n]->adresa_postovni= "{$x->ulice}<br/>$psc  {$x->obec}".( $stat ? "<br/>        $stat" : "");
    $n++;
  }
  // předání k tisku
  $fname= 'stitky_'.date("Ymd_Hi");
  $fpath= "$ezer_path_docs/$fname.pdf";
  dop_rep_ids($report_json,$parss,$fpath);
  $result->html= " Výpis byl vygenerován ve formátu <a href='docs/$fname.pdf' target='pdf'>PDF</a>.";
  return $result;
}
# --------------------------------------------------------------------------------- tisk2_pdf_prijem
# generování štítků se stručnými informace k nalepení na obálku účastníka do PDF
function tisk2_pdf_prijem($akce,$par,$report_json) {  trace();
  global $ezer_path_docs;
  $result= (object)array('_error'=>0);
  $html= '';
  // získání dat
  $tab= tisk2_sestava_pary($akce,$par,$title,$vypis,true);
//                                         debug($tab,"tisk2_sestava_pary($akce,...)"); //return;
  // projdi vygenerované záznamy
  $n= 0;
  $parss= array();
  foreach ( $tab->clmn as $xa ) {
    // definice pole substitucí
    $x= (object)$xa;
    $parss[$n]= (object)array();
    $parss[$n]->line1= "<b>{$x->prijmeni} {$x->jmena}</b>";
    $parss[$n]->line2= ($x->skupina?"skupinka <b>{$x->skupina}</b> ":'')
                     . ($x->pokoj?"pokoj <b>{$x->pokoj}</b>":'');
    $parss[$n]->line3= $x->luzka || $x->pristylky || $x->kocarek ? (
                       ($x->luzka?"lůžka <b>{$x->luzka}</b> ":'')
                     . ($x->pristylky?"přistýlky <b>{$x->pristylky} </b>":'')
                     . ($x->kocarek?"kočárek <b>{$x->kocarek}</b>":'')
                       ) : "bez ubytování";
    $parss[$n]->line4= $x->strava_cel || $x->strava_pol ? ( "strava: "
                     . ($x->strava_cel?"celá <b>{$x->strava_cel}</b> ":'')
                     . ($x->strava_pol?"poloviční <b>{$x->strava_pol}</b>":'')
                       ) : "bez stravy";
    $n++;
  }
  // předání k tisku
  $fname= 'stitky_'.date("Ymd_Hi");
  $fpath= "$ezer_path_docs/$fname.pdf";
  dop_rep_ids($report_json,$parss,$fpath);
  $result->html= " Výpis byl vygenerován ve formátu <a href='docs/$fname.pdf' target='pdf'>PDF</a>.";
  return $result;
}
# -------------------------------------------------------------------------------- tisk2_pdf_plachta
# generování štítků se jmény párů
function tisk2_pdf_plachta($akce,$report_json=0) {  trace();
  global $ezer_path_docs;
  $result= (object)array('_error'=>0);
  $html= '';
  // získání dat
  $tab= akce2_plachta($akce,$par,$title,$vypis,0);
  unset($tab->xhref);
  unset($tab->html);
  ksort($tab->pdf);
  // projdi vygenerované záznamy
  $n= 0;
  if ( $report_json) {
    $parss= array();
    foreach ( $tab->pdf as $xa ) {
      // definice pole substitucí
      $x= (object)$xa;
      $parss[$n]= (object)array();  // {prijmeni}<br>{jmena}
      $prijmeni= $x->prijmeni;
      $len= mb_strlen($prijmeni);
      $xlen= round(tc_StringWidth($prijmeni,'B',15));
      $fs= 20;
      if ( $xlen<20 ) {
        $fw= 'condensed';
      }
      elseif ( $xlen<27 ) {
        $fw= 'condensed';
      }
      elseif ( $xlen<37 ) {
        $fw= 'extra-condensed';
      }
      else {
        $fw= 'ultra-condensed';
      }
      $s1= "font-stretch:$fw;font-size:{$fs}mm;font-weight:bold;text-align:center";
      $bg1= $x->vps=='* ' ? "background-color:gold" : ($x->vps=='+ ' ? "background-color:lightblue" : '');
      $s2= "font-size:5mm;text-align:center";
      $bg2= $x->vps=='+ ' ? "background-color:#eeeeee" : '';
      $bg2= '';
      $parss[$n]->prijmeni= "<span style=\"$s1;$bg1\">$prijmeni</span>";

      $parss[$n]->jmena= "<span style=\"$s2;$bg2\"><br>{$x->jmena}</span>";
      $n++;
    }
//                                         debug($parss,"tisk2_pdf_plachta..."); return $result;
    // předání k tisku
    $fname= 'stitky_'.date("Ymd_Hi");
    $fpath= "$ezer_path_docs/$fname.pdf";
    dop_rep_ids($report_json,$parss,$fpath);
    $result->html= " Výpis byl vygenerován ve formátu <a href='docs/$fname.pdf' target='pdf'>PDF</a>.";
  }
  else {
    $result= tisk2_table(array('příjmení','jména'),array('prijmeni','jmena'),$tab->pdf);
  }
  return $result;
}
# ------------------------------------------------------------------------------ akce2_pdf_stravenky
# generování štítků se stravenkami pro rodinu účastníka a pro pečouny do PDF
# pomocí tisk2_sestava se do objektu $x->tab vygeneruje pole s elementy pro tisk stravenky
function akce2_pdf_stravenky($akce,$par,$report_json) {  trace();
  global $ezer_path_docs, $EZER, $USER;
  $result= (object)array('_error'=>0);
  $html= '';
  // získání dat
  $x= tisk2_sestava($akce,$par,$title,$vypis,true);
  $org= $USER->org==1 ? "YMCA Setkání" : "YMCA Familia";
  $header= "$org, {$x->akce->misto} {$x->akce->rok}";
  $sob= array('s'=>'snídaně','o'=>'oběd','v'=>'večeře');
  $cp=  array('c'=>'1','p'=>'1/2');
  // projdi vygenerované záznamy
  $n= 0;
  $parss= array();
  foreach ( $x->tab as $jmeno=>$dny ) {
    // vynechání prázdných míst, aby jméno bylo v prvním sloupci ze 4
    $k= 4*ceil($n/4)-$n;
    for ($i= 0; $i<$k; $i++) {
      $parss[$n]= (object)array();
      $parss[$n]->header= $parss[$n]->line1= $parss[$n]->line2= '';
      $parss[$n]->rect= $parss[$n]->ram= $parss[$n]->end= '';
      $n++;
    }
    // stravenky pro účastníka
    list($prijmeni,$jmena)= explode('|',$jmeno);
//                                                         if ( $prijmeni!="Bučkovi" ) continue;
    $parss[$n]= (object)array();
    $parss[$n]->header= $header;
    $parss[$n]->line1= "<b>$prijmeni</b>";
    $parss[$n]->line2= "$jmena";
    $parss[$n]->rect= '';
    $parss[$n]->ram= ' ';
    $parss[$n]->end= '';
    $n++;
    foreach ( $dny as $den=>$jidla ) {
      // stravenky na jeden den
      foreach ( $jidla as $jidlo=>$porce ) {
        // denní jídlo
        foreach ( $porce as $velikost=>$pocet ) {
          // porce
          for ($i= 1; $i<=$pocet; $i++) {
            // na začátku stránky dej příznak pokračování
            if ( ($n % (4*12) )==0 ) {
              $parss[$n]= (object)array();
              $parss[$n]->header= $header;
              $parss[$n]->line1= "<b>... $prijmeni</b>";
              $parss[$n]->line2= "... $jmena";
              $parss[$n]->rect= $parss[$n]->ram= $parss[$n]->end= '';
              $n++;
            }
            // text stravenky na jedno jídlo
            $parss[$n]= (object)array();
            $parss[$n]->header= $header;
            $parss[$n]->line1= "$den";
            $parss[$n]->line2= "<b>{$sob[$jidlo]}</b>";
            if ( $velikost=='c' ) {
              // celá porce
              $parss[$n]->ram= '<img src="db/img/stravenky-rastr-1.png"'
                             . ' style="width:48mm" border="0" />';
              $parss[$n]->rect=  " ";
            }
            else {
              // poloviční porce
              $parss[$n]->ram= '';
              $parss[$n]->rect=  "<b>1/2</b>";
            }
            $parss[$n]->end= '';
            $n++;
          }
        }
      }
    }
    // na konec dej koncovou značku
    $parss[$n]= (object)array();
    $parss[$n]->header= $header;
    $parss[$n]->line1= "<b>$prijmeni</b>";
    $parss[$n]->line2= "(konec stravenek)";
    $parss[$n]->rect= $parss[$n]->ram= '';
    $parss[$n]->end= ' ';
    $n++;
  }
  // předání k tisku
//                                         debug($parss,"akce_pdf_stravenky");
//                                         debug($report_json,"report");
//                                         return $result;
  $fname= 'stravenky_'.date("Ymd_Hi");
  $fpath= "$ezer_path_docs/$fname.pdf";
  dop_rep_ids($report_json,$parss,$fpath);
  $result->html= " Výpis byl vygenerován ve formátu <a href='docs/$fname.pdf' target='pdf'>PDF</a>.";
  return $result;
}
# ----------------------------------------------------------------------------- akce2_pdf_stravenky0
# generování stránky stravenek pro ruční vyplnění do PDF
function akce2_pdf_stravenky0($akce,$par,$report_json) {  trace();
  global $ezer_path_docs, $EZER, $USER;
  $result= (object)array('_error'=>0);
  $html= '';
  // získání dat o akci
  $qa="SELECT nazev, YEAR(datum_od) AS akce_rok, misto
       FROM akce WHERE id_duakce='$akce' ";
  $ra= mysql_qry($qa);
  $a= mysql_fetch_object($ra);
  $org= $USER->org==1 ? "YMCA Setkání" : "YMCA Familia";
  $header= "$org, {$a->misto} {$a->akce_rok}";
  // projdi vygenerované záznamy
  $n= 0;
  $parss= array();
  $pocet= 4*12;
  for ($i= 1; $i<=$pocet; $i++) {
    // text stravenky na jedno jídlo
    $parss[$n]= (object)array();
    $parss[$n]->header= $header;
    $parss[$n]->line1= "$den";
    $parss[$n]->line2= "";
    $parss[$n]->rect=  "";
    $parss[$n]->end= '';
    $parss[$n]->ram= '<img src="db/img/stravenky-rastr-1.png" style="width:48mm;height:23mm" border="0" />';
    $n++;
  }
  for ($i= 1; $i<=$pocet; $i++) {
    // text stravenky na jedno jídlo
    $parss[$n]= (object)array();
    $parss[$n]->header= $header;
    $parss[$n]->line1= "$den";
    $parss[$n]->line2= "";
    $parss[$n]->rect=  "<b>1/2</b>";
    $parss[$n]->end= '';
    $parss[$n]->ram= ' ';
    $n++;
  }
  // předání k tisku
//                                         debug($parss,"akce2_pdf_stravenky0");
  $fname= 'stravenky_'.date("Ymd_Hi");
  $fpath= "$ezer_path_docs/$fname.pdf";
//                                         return $result;
  dop_rep_ids($report_json,$parss,$fpath);
  $result->html= " Výpis byl vygenerován ve formátu <a href='docs/$fname.pdf' target='pdf'>PDF</a>.";
  return $result;
}
# -------------------------------------------------------------------------------------- dop_rep_ids
# LOCAL
# vytvoření dopisů se šablonou pomocí TCPDF podle parametrů
# $parss  - pole obsahující substituce parametrů pro $text
# vygenerované dopisy ve tvaru souboru PDF se umístí do ./docs/$fname
# případná chyba se vrátí jako Exception
function dop_rep_ids($report_json,$parss,$fname) { trace();
  global $json;
  $err= 0;
  // transformace $parss pro strtr
  $subst= array();
  for ($i=0; $i<count($parss); $i++) {
    $subst[$i]= array();
    foreach($parss[$i] as $x=>$y) {
      $subst[$i]['{'.$x.'}']= $y;
    }
  }
  $report= $json->decode($report_json);
//                                                         debug($report,"dop_rep_ids");
  // vytvoření $texty - seznam
  $texty= array();
  for ($i=0; $i<count($parss); $i++) {
    $texty[$i]= (object)array();
    foreach($report->boxes as $box) {
      $id= $box->id;
      if ( !$id) fce_error("dop_rep_ids: POZOR: box reportu musí být pojmenován");
      $texty[$i]->$id= strtr($box->txt,$subst[$i]);
    }
  }
//                                                         debug($texty,'dop_rep_ids');
//                                                         return null;
  tc_report($report,$texty,$fname);
}
/** =========================================================================================> EVID2 */
# ------------------------------------------------------------------------------------- evid2_delete
# zjistí, zda lze osobu smazat: dar, platba, spolu, tvori
# cmd= conf_oso|conf_rod|del_oso|del_rod
function evid2_delete($id_osoba,$id_rodina,$cmd='confirm') { trace();
  global $USER;
  user_test();
  $ret= (object)array('html'=>'','ok'=>1);
  $user= $USER->abbr;
  $now= date("Y-m-d H:i:s");
  $duvod= array();
  list($name,$sex)= select("CONCAT(prijmeni,' ',jmeno),sex",'osoba',"id_osoba=$id_osoba");
  $a= $sex==2 ? 'a' : '';
  $nazev= select("nazev",'rodina',"id_rodina=$id_rodina");
  switch ($cmd) {
  case 'conf_oso':
    $x= select1('SUM(castka)','dar',"id_osoba=$id_osoba");
    if ( $x) $duvod[]= "je dárcem $x Kč";
    $x= select1('SUM(castka)','platba',"id_osoba=$id_osoba");
    if ( $x) $duvod[]= "zaplatil$a $x Kč";
    $xr= mysql_qry("SELECT COUNT(*) AS _x_ FROM spolu JOIN pobyt USING (id_pobyt)
                    JOIN akce ON id_akce=id_duakce WHERE id_osoba=$id_osoba AND spec=0");
    list($x)= mysql_fetch_array($xr);
    if ( $x) $duvod[]= "se zúčastnil$a $x akcí";
    $x= select1('COUNT(*)','tvori',"id_osoba=$id_osoba AND id_rodina!=$id_rodina");
    if ( $x) $duvod[]= "je členem dalších $x rodin";
    $ret->ok= count($duvod) ? 0 : 1;
    if ( $ret->ok ) {                   // lze smazat, nezůstane ale rodina prázdná?
      $x= select1('COUNT(*)','tvori',"id_rodina=$id_rodina");
      $ret->html= $x==1 ? "$name je jediným členem své rodiny, smazat i tu?" : "Opravdu smazat $name ?";
      $ret->ok= $x==1 ? 2 : 1;
    }
    else {                              // nelze smazat - existují odkazy
      $ret->html= "$name nejde smazat, protože ".implode(',',$duvod);
    }
    break;
  case 'conf_mem':
//     $x= select1('COUNT(*)','tvori',"id_osoba=$id_osoba AND id_rodina!=$id_rodina");
//     if ( !$x ) $duvod[]= "není členem žádné další rodiny";
//     $ret->ok= count($duvod) ? 0 : 1;
    if ( $ret->ok ) {                   // lze vyjmout, nezůstane ale rodina prázdná?
      $x= select1('COUNT(*)','tvori',"id_rodina=$id_rodina");
      $ret->html= $x==1 ? "$name je jediným členem rodiny $nazev, smazat ji?"
        : "Opravdu vyjmout $name z $nazev?";
      $ret->ok= $x==1 ? 2 : 1;
    }
    else {                              // nelze smazat - existují odkazy
      $ret->html= "$name nejde vyjmout z $nazev, protože ".implode(',',$duvod);
    }
    break;
  case 'conf_rod':
    $x= select1('COUNT(*)','tvori',"id_rodina=$id_rodina");
    $ret->html= $x==0 ? "Opravdu smazat prázdnou rodinu $nazev?"
      : "Rodinu $nazev nelze smazat, protože obsahuje $x členů - nejprve je třeba je vyjmout nebo vymazat";
    $ret->ok= $x==0 ? 1 : 0;
    break;
  case 'del_mem':
    $ret->ok= query("DELETE FROM tvori WHERE id_osoba=$id_osoba AND id_rodina=$id_rodina") ? 1 : 0;
    $ret->html= "$name byl$a vyjmut$a z $nazev";
    break;
  case 'del_oso':
    $ret->ok= query("UPDATE osoba SET deleted='D' WHERE id_osoba=$id_osoba") ? 1 : 0;
    query("INSERT INTO _track (kdy,kdo,kde,klic,fld,op,old,val)
           VALUES ('$now','$user','osoba',$id_osoba,'','x','','')");
    query("DELETE FROM tvori WHERE id_osoba=$id_osoba AND id_rodina=$id_rodina");
    $ret->html= "$name byl$a smazán$a";
    break;
  case 'undel_oso':
    $ret->ok= query("UPDATE osoba SET deleted='' WHERE id_osoba=$id_osoba") ? 1 : 0;
    query("INSERT INTO _track (kdy,kdo,kde,klic,fld,op,old,val)
           VALUES ('$now','$user','osoba',$id_osoba,'','o','','')");    // o=obnova
    $ret->html= "$name byl$a obnoven$a";
    break;
  case 'del_rod':
    query("UPDATE osoba JOIN tvori USING (id_osoba) SET deleted='D' WHERE id_rodina=$id_rodina");
    $no= mysql_affected_rows();
    query("UPDATE rodina SET deleted='D' WHERE id_rodina=$id_rodina");
    $nr= mysql_affected_rows();
    query("INSERT INTO _track (kdy,kdo,kde,klic,fld,op,old,val)
           VALUES ('$now','$user','rodina',$id_rodina,'','x','','')");
    query("DELETE FROM tvori WHERE id_rodina=$id_rodina");
    $ret->ok= $no && $nr ? 1 : 0;
    $ami= $no==1 ? "ou" : "ami";
    $ret->html= "Byla smazána rodina s $no osob$ami";
    break;
  case 'undel_rod':
    $ret->ok= query("UPDATE rodina SET deleted='' WHERE id_rodina=$id_rodina") ? 1 : 0;
    query("INSERT INTO _track (kdy,kdo,kde,klic,fld,op,old,val)
           VALUES ('$now','$user','rodina',$id_rodina,'','o','','')");    // o=obnova
    $ret->html= "rodina $nazev byla obnovena";
    break;
  }
  return $ret;
}
# --------------------------------------------------------------------------------------- evid2_cleni
# hledání a) osoby a jejích rodin b) rodiny (pokud je id_osoba=0)
function evid2_cleni($id_osoba,$id_rodina,$filtr) { //trace();
  global $USER;
  $access= $USER->access;
  $msg= '';
  $cleni= "";
  $rodiny= array();
  $rodina= $rodina1= $id_rodina;
//   $id_osoba ? "o.id_osoba=$id_osoba" : "r.id_rodina=$id_rodina";
  if ( $id_osoba ) { // ------------------------ osoby
    $clen= array();
    $css= array('','ezer_ys','ezer_fa','ezer_db');
    $qc= mysql_qry("
      SELECT rto.id_osoba,rto.jmeno,rto.prijmeni,rto.narozeni,rto.access AS o_access,
        rt.id_tvori,rt.role,o.deleted,r.id_rodina,nazev,r.access AS r_access
      FROM osoba AS o
        JOIN tvori AS ot ON ot.id_osoba=o.id_osoba
        JOIN rodina AS r ON r.id_rodina=ot.id_rodina -- AND r.access & $access
        JOIN tvori AS rt ON rt.id_rodina=r.id_rodina
        JOIN osoba AS rto ON rto.id_osoba=rt.id_osoba
      WHERE o.id_osoba=$id_osoba AND $filtr -- AND rto.access & $access
      ORDER BY rt.role,rto.narozeni
    ");
    while ( $qc && ($c= mysql_fetch_object($qc)) ) {
      $ido= $c->id_osoba;
      $idr= $c->id_rodina;
      $clen[$idr][$ido]= $c;
      $style= ($c->o_access & $access ? '' : '_');
      $clen[0][$ido].= ",{$c->nazev}:$idr:{$css[$c->r_access]}";
      $clen[$idr][$ido]->_vek= $c->narozeni=='0000-00-00' ? '?' : roku_k($c->narozeni);
      // určení zobrazené rodiny
      if ( !$rodina ) $rodina1=  $c->id_rodina;
      if ( !$rodina && $ido==$id_osoba && ($c->role=='a' || $c->role=='b'))  $rodina= $c->id_rodina;
    }
    if ( !$rodina ) $rodina= $rodina1;
//                                                 debug($clen,"rodina=$rodina");
    if ($clen[$rodina]) foreach($clen[$rodina] as $ido=>$c) {
      if ( $rodina && ($c->id_rodina==$rodina ||$c->id_osoba==$id_osoba)) {
        $rodiny= substr($clen[0][$ido],1);
        $role= $c->role;
        $barva= $c->deleted ? 0 : ($c->o_access & $access ? 1 : 2);  // smazaný resp. nedostupný
        $cleni.= "|$ido|$c->id_tvori|$barva|$rodiny|$c->o_access|$c->prijmeni $c->jmeno|$c->_vek|$role";
      }
    }
  }
  else { // ------------------------------------ rodiny
    $qc= mysql_qry("
      SELECT rto.id_osoba,rto.jmeno,rto.prijmeni,rto.narozeni,rto.access,
        rt.id_tvori,rt.role,r.id_rodina,r.nazev,r.access AS r_access,
        GROUP_CONCAT(CONCAT(otr.nazev,/*'-',otr.access,*/':',otr.id_rodina,
          IF(otr.access=1,':ezer_ys',IF(otr.access=2,':ezer_fa',IF(otr.access=3,':ezer_db','')))))
          AS _rodiny,rto.deleted
      FROM rodina AS r
        JOIN tvori AS rt ON rt.id_rodina=r.id_rodina
        JOIN osoba AS rto ON rto.id_osoba=rt.id_osoba
        JOIN tvori AS ot ON ot.id_osoba=rto.id_osoba
        JOIN rodina AS otr ON otr.id_rodina=ot.id_rodina -- AND otr.access & $access
      WHERE r.id_rodina=$id_rodina AND $filtr AND rto.access & $access
      GROUP BY id_osoba
      ORDER BY rt.role,rto.narozeni
    ");
    while ( $qc && ($c= mysql_fetch_object($qc)) ) {
      if ( !isset($rodiny[$c->id_rodina]) ) {
        $rodiny[$c->id_rodina]= "{$c->nazev}:{$c->id_rodina}";
        if ( !$rodina ) $rodina= $c->id_rodina;
      }
      if ( $c->id_rodina!=$rodina ) continue;
      $vek= $c->narozeni=='0000-00-00' ? '?' : roku_k($c->narozeni);
      $barva= $c->deleted=='';  // nesmazaný
      $cleni.= "|$c->id_osoba|$c->id_tvori|$barva|$c->_rodiny|$c->access|$c->prijmeni $c->jmeno|$vek|$c->role";
//                                                         display("{$c->jmeno} {$c->narozeni} $vek");
    }
    $msg= $cleni ? '' : "rodina neobsahuje žádné členy";
  }
  $ret= (object)array('cleni'=>$cleni ? substr($cleni,1) : '','rodina'=>$rodina,'msg'=>$msg);
//                                                         debug($ret);
  return $ret;
}
# ----------------------------------------------------------------------------- evid2_browse_act_ask
# obsluha browse s optimize:ask pro seznam akcí dané osoby
# x->order= {a|d} polozka
# x->show=  {polozka:[formát,vzor/1,...],...} pro položky s neprázdným vzorem
#                                             kde formát=/ = # $ % @ * .
# x->cond= podmínka
function evid2_browse_act_ask($x) {
  global $y;
  $y= (object)array('ok'=>0);
  foreach(explode(',','cmd,rows,quiet,key_id,oldkey') as $i) $y->$i= $x->$i;
  switch ($x->cmd) {
  case 'browse_load':  # ------------------------------------- browse_load
    $n= 0;
    $order= $x->order[0]=='a' ? substr($x->order,2).' ASC,' : (
            $x->order[0]=='d' ? substr($x->order,2).' DESC,' : '');
    $y->from= 0;
    $y->cursor= 0;
    $y->values= array();
    $qp= mysql_qry("
      SELECT a.id_duakce as ida,p.id_pobyt as idp,s.id_spolu as ids,p.funkce as fce,
        YEAR(a.datum_od) as rok,a.nazev as akce,p.funkce as _fce,narozeni,datum_od,a.access AS org
      FROM akce AS a
      JOIN pobyt AS p ON a.id_duakce=p.id_akce
      JOIN spolu AS s USING(id_pobyt)
      JOIN osoba AS o USING(id_osoba)
      WHERE $x->cond
      ORDER BY $order a.id_duakce
      -- LIMIT 0,50
    ");
    while ( $qp && ($p= mysql_fetch_object($qp)) ) {
      $n++;
      $p->_vek= $p->narozeni!='0000-00-00' ? roku_k($p->narozeni,$p->datum_od) : '?';      // výpočet věku
      if ( $p->_vek<18 ) { $p->fce= 0; $p->_fce= '_'; }
      unset($p->datum_od,$p->narozeni);
      $y->values[]= $p;
    }
    array_unshift($y->values,null);
    $y->count= $n;
    $y->rows= $n;
    $y->ok= 1;
    break;
  default:
    fce_warning("N.Y.I. evid_browse_act_ask/{$x->cmd}");
    $y->ok= 0;
    break;
  }
  return $y;
}
# ---------------------------------------------------------------------------- evid2_pridej_k_rodine
# ASK přidání do dané rodiny, pokud ještě osoba v rodině není
# spolupracuje s: akce2_auto_jmena1,akce2_auto_jmena1L
# info = {id,nazev,role}
function evid2_pridej_k_rodine($id_rodina,$info,$cnd='') { trace();
  $ret= (object)array('tvori'=>0,'msg'=>'');
  $ido= $info->id;
  $je= select("COUNT(*)","tvori","id_rodina=$id_rodina AND id_osoba=$ido");
  if ( $je ) {
    $ret->msg= "$info->nazev už v rodině je";
  }
  else {
    if ( query("INSERT INTO tvori (id_rodina,id_osoba,role) VALUE ($id_rodina,$ido,'p')") ) {
      $ret->tvori= mysql_insert_id();
      $ret->ok= 1;
    }
    else  $ret->msg= 'chyba při vkládání';
  }
  return $ret;
}
# ==========================================================================================> . YMCA
# --------------------------------------------------------------------------==> .. evid2_ymca_sprava
# správa členů pro YS
function evid2_ymca_sprava($org,$par,$title,$export=false) {
  $ret= (object)array('error'=>0,'html'=>'');
  switch ($par->op) {
  case 'hlaseni':
  case 'letos':
  case 'loni':
    $ret= evid2_ymca_sestava($org,$par,$title,$export);
    break;
  case 'zmeny':
    $ret= evid2_recyklace_pecounu($org,$par,$title,0);
    break;
  }
  return $ret;
}
# ------------------------------------------------------------------------------- evid2_ymca_sestava
# generování přehledu členstva
#   $fld = seznam položek s prefixem
#   $cnd = podmínka
# _clen_od,_cinny_od,_prisp,_dary
function evid2_ymca_sestava($org,$par,$title,$export=false) {
  $ret= (object)array('html'=>'','err'=>'');
  $rok= date('Y') - $par->rok;
  // dekódování parametrů
  $tits= explode(',',$par->tit);
  $flds= explode(',',$par->fld);
  // test korektnosti organizace
  $organizace= $org==1 ? 'YMCA Setkání' : ( $org==2 ? "YMCA Familia" : '');
  if ( !$organizace ) {
    $ret->err= "Musí být zvolena organizace (barva aplikace)";
    goto end;
  }
  // získání dat
  $n= 0;
  $clmn= array();
  $expr= array();       // pro výrazy
  $clenu= $cinnych= $prispevku= $daru= $dobrovolniku= $novych= 0;
  $qry= "SELECT
           MAX(IF(YEAR(datum_od)=$rok AND p.funkce=1,1,0)) AS _vps,
           os.id_osoba,os.prijmeni,os.jmeno,os.narozeni,os.sex,
           IF(os.obec='',r.obec,os.obec) AS obec,
           IF(os.ulice='',r.ulice,os.ulice) AS ulice,
           IF(os.psc='',r.psc,os.psc) AS psc,
           IF(os.email='',r.emaily,os.email) AS email,
           GROUP_CONCAT(DISTINCT od.ukon ORDER BY od.ukon SEPARATOR '') as rel,
           GROUP_CONCAT(DISTINCT CONCAT(ukon,':',YEAR(dat_od),':',YEAR(dat_do),':',castka) ORDER BY dat_od DESC SEPARATOR '|') AS _ukony
         FROM osoba AS os
         LEFT JOIN tvori AS ot ON os.id_osoba=ot.id_osoba
         LEFT JOIN rodina AS r USING(id_rodina)
         LEFT JOIN dar AS od ON os.id_osoba=od.id_osoba AND od.deleted=''
         LEFT JOIN spolu AS s ON s.id_osoba=os.id_osoba
         LEFT JOIN pobyt AS p USING (id_pobyt)
         LEFT JOIN akce AS a ON a.id_duakce=p.id_akce
         WHERE os.deleted='' AND {$par->cnd} AND (dat_do='0000-00-00' OR YEAR(dat_do)>=$rok)
           AND os.access&$org AND r.access&$org AND od.access&$org AND a.access&$org
         GROUP BY os.id_osoba HAVING {$par->hav}
         ORDER BY os.prijmeni";
  $res= mysql_qry($qry);
  while ( $res && ($x= mysql_fetch_object($res)) ) {
    $id_osoba= $x->id_osoba;
    // rozbor úkonů
    $_clen_od= $_cinny_od= $_prisp= $prisp_letos= $_dary= 0;
    foreach(explode('|',$x->_ukony) as $uddc) {
      list($u,$d1,$d2,$c)= explode(':',$uddc);
      switch ($u) {
      case 'p': if ( $d1==$rok ) $_prisp+= $c; break;
      case 'd': if ( $d1==$rok ) $_dary+= $c; break;
      case 'b': if ( $d2<=$rok && (!$_clen_od && $d1<=$rok || $d1<$_clen_od) ) $_clen_od= $d1; break;
      case 'c': if ( $d2<=$rok && (!$_cinny_od && $d1<=$rok || $d1<$_cinny_od) ) $_cinny_od= $d1; break;
      }
    }
//                                 display("$x->jmeno $_clen_od= $_cinny_od= $_prisp= $_dary");
    $prispevku+= $_prisp;
    $daru+= $_dary;
    if ( !$_clen_od && !$_cinny_od ) continue;
    $clenu+= $_clen_od ? 1 : 0;
    $cinnych+= $_cinny_od ? 1 : 0;
    $dobrovolniku+= $x->_vps && $_cinny_od>0 ? 1 : 0;
    $novych+= $_cinny_od==2014 ? 1 : 0;
    // pokračujeme jen s členy
    $n++;
    $clmn[$n]= array();
    foreach($flds as $f) {
//  '1,prijmeni,jmeno,ulice,obec,psc,_naroz,_b_c,_prisp,_YS,_email,_cinny_letos,_dobro'}}
      switch ( $f ) {
      // export
      case '1':         $clmn[$n][$f]= 1; break;
      case '_naroz_ymd':$clmn[$n][$f]= substr($x->narozeni,2,2).substr($x->narozeni,5,2).substr($x->narozeni,8,2); break;
      case '_b_c':      $clmn[$n][$f]= $_cinny_od>0 ? 'č' : 'b'; break;
      case '_YS':       $clmn[$n][$f]= 'YMCA Setkání'; break;
      case '_cinny_letos':$clmn[$n][$f]= $_cinny_od==2014 ? 1 : ''; break;
      case '_dobro':    $clmn[$n][$f]= $x->_vps && $_cinny_od>0 ? 1 : ''; break;
      // přehled
      case '_clen_od':  $clmn[$n][$f]= $_clen_od; break;
      case '_cinny_od': $clmn[$n][$f]= $_cinny_od; break;
      case '_prisp':    $clmn[$n][$f]= $_prisp; break;
      case '_dary':     $clmn[$n][$f]= $_dary; break;
      case '_naroz':    $clmn[$n][$f]= sql_date1($x->narozeni); break;
      case '_zrus':     $del= $_clen_od<2014 && !$_cinny_od && !$_prisp ? 'x' : '';
        if ( $par->del && $del=='x' ) {
          // výjimky
          if ( in_array(substr($x->prijmeni,0,4),array("Fisc","Gada","Hora","Horo","Jaku","Ulri")) )
            $del= '';
          // SMAZAT!
          if ( $del=='x' ) {
            $qd= "UPDATE dar SET dat_do='2013-12-30',note=CONCAT(note,' inventura 2014') /* $x->prijmeni */ WHERE id_osoba=$id_osoba AND ukon IN ('c','b') AND dat_do='0000-00-00'";
                                                display($qd);
            $ok= query($qd);
            if ( !$ok ) $del= "X?";
          }
        }
        $clmn[$n][$f]= $del;
        break;
      default:
        $clmn[$n][$f]= $x->$f;
      }
    }
  }
  // přidání sumarizace
  $n++;
  $clmn[$n]['obec']= '.SUMA:.';
  $clmn[$n]['_clen_od']= $clenu;
  $clmn[$n]['_cinny_od']= $cinnych;
  $clmn[$n]['_prisp']= $prispevku;
  $clmn[$n]['_dary']= $daru;
  $clmn[$n]['_dobro']= $dobrovolniku;
  $clmn[$n]['_cinny_letos']= $novych;
  $ret= sta2_table_graph($par,$tits,$flds,$clmn,$export);
end:
  return $ret;
}
# --------------------------------------------------------------------==> .. evid2_recyklace_pecounu
# generování přehledu pečounů pro recyklaci
# - předpokládá se spuštění ve stejném roce jako byl letní kurz pokud par.rok=0
# - nebo v loňském roce, pokud je rok=1
function evid2_recyklace_pecounu($org,$par,$title,$provest) {
  $ret= (object)array('html'=>'','err'=>'');
  // test korektnosti organizace
  $organizace= $org==1 ? 'YMCA Setkání' : ( $org==2 ? "YMCA Familia" : '');
  if ( !$organizace ) {
    $ret->err= "Musí být zvolena organizace (barva aplikace)";
    goto end;
  }
  $letos= date('Y') - $par->rok;
  $rok_od= $letos;
  $rok_do= $rok_od-1;
  $den_do= "$rok_do-12-31";
  $den_od= "$rok_od-01-01";
  $html= '';
  $pryc= $nechat= $novi= array();
  // průzkum pečounů
  $pr= mysql_qry("
    SELECT id_osoba,prijmeni,jmeno,ukon,pfunkce,
      MIN(YEAR(dat_od)) AS _od,
      MAX(YEAR(dat_do)) AS _do,
      MIN(YEAR(a.datum_od)) AS _poprve,
      MAX(YEAR(a.datum_od)) AS _naposled,
      GROUP_CONCAT(DISTINCT ukon SEPARATOR '') AS _ukony,
      SUM(IF(YEAR(a.datum_od)=$rok_do,1,0)) AS _loni,
      SUM(IF(YEAR(a.datum_od)=$rok_od,1,0)) AS _letos,
      MAX(pfunkce) AS _pfunkce,
      narozeni
    FROM osoba AS o
    LEFT JOIN dar AS d USING (id_osoba)
    JOIN spolu AS s USING (id_osoba)
    JOIN pobyt AS p USING (id_pobyt)
    JOIN akce AS a ON id_akce=id_duakce
    WHERE IFNULL(d.deleted,'')='' AND funkce=99
      AND o.access&$org AND IFNULL(d.access,3)&$org AND a.access&$org
    GROUP BY id_osoba
    ORDER BY prijmeni");
  while ( $pr && ($p= mysql_fetch_object($pr)) ) {
    $pec= (object)array('id'=>$p->id_osoba,'jmeno'=>"{$p->prijmeni} {$p->jmeno}");
    // přeskočit činné členy a team
    if ( strpos($p->_ukony,'c')!==false ) continue;
    if ( $p->_pfunkce==7 ) continue;
    if ( $p->_poprve==$rok_od && strpos($p->_ukony,'b')===false
    || ( $p->_naposled==$rok_od && strpos($p->_ukony,'b')===false )
    ) {
      $novi[]= $pec;
    }
    else if ( $p->_naposled<$rok_od && strpos($p->_ukony,'b')!==false && $p->_do==0 ) {
      $pryc[]= $pec;
    }
    else if ( $p->_naposled==$rok_od ) {
      $nechat[]= $pec;
    }
  }
//                                                 debug($novi,"noví");
//                                                 debug($pryc,"pryč");
//                                                 debug($nechat,"nechat");
  // noví pečouni
  $html.= "<h3>Úprava členství pečounů v $organizace</h2>";
  $html.= "<h3>Noví členové tj. letošní pečouni, kteří nejsou členy</h3>";
  $del= '';
  foreach ($novi as $nov) {
    $html.= "$del{$nov->jmeno}"; $del= ', ';
    if ( $provest ) {
      query("INSERT INTO dar (access,id_osoba,ukon,dat_od,note)
             VALUES ($org,$nov->id,'b','$den_od','pečoun $rok_od')");
    }
  }
  // staří pečouni
  $html.= "<h3>Ponechaní členové tj. dříve i letos aktivní pečouni, kteří jsou již členy</h3>";
  $del= '';
  foreach ($nechat as $nec) {
    $html.= "$del{$nec->jmeno}"; $del= ', ';
  }
  // nečinní pečouni
  $html.= "<h3>Členové, kteří budou vyřazeni tj. letos už neaktivní pečouni, kteří jsou dosud členy</h3>";
  $del= '';
  foreach ($pryc as $pry) {
    $html.= "$del{$pry->jmeno}"; $del= ', ';
    if ( $provest ) {
      query("UPDATE dar SET dat_do='$den_do'
             WHERE access=$org AND id_osoba={$pry->id} AND ukon='b' AND dat_do='0000-00-00'");
    }
  }
  // návrat
  $ret->html= $html;
end:
  return $ret;
}
# =======================================================================================> . MAILIST
# -----------------------------------------------------------------------==> .. evid2_browse_mailist
# BROWSE ASK
# obsluha browse s optimize:ask
# x->cond = id_mailist
# x->order= {a|d} polozka
# x->show=  {polozka:[formát,vzor/1,...],...} pro položky s neprázdným vzorem
#                                             kde formát=/ = # $ % @ * .
# x->cond= podmínka   - pokud obsahuje /*duplicity*/ přidá se sloupec _dup
# x->selected= null | seznam key_id, které mají být předány - použití v kombinaci se selected(use)
function evid2_browse_mailist($x) {
  global $test_clmn,$test_asc, $y;
//                                                         debug($x,"evid2_browse_mailist");
//                                                         return;
  $y= (object)array('ok'=>0);
  foreach(explode(',','cmd,rows,quiet,key_id,oldkey') as $i) $y->$i= $x->$i;
  // předání selected
  if ( $x->selected ) {
    $selected= explode(',',$x->selected);
  }
  switch ($x->cmd) {
  case 'browse_export':
  case 'browse_load':
    $zz= array();
    # získej pozice PSČ
    $lat= $lng= array();
    $qs= "SELECT psc,lat,lng FROM uir_adr.psc_axy GROUP BY psc";
    $rs= mysql_qry($qs);
    while ( $rs && ($s= mysql_fetch_object($rs)) ) {
      $psc= $s->psc;
      $lat[$psc]= $s->lat;
      $lng[$psc]= $s->lng;
    }
    # získej sexpr z mailistu id=c.cond
    list($nazev,$qo)= select('ucel,sexpr','mailist',"id_mailist={$x->cond}");
//                                                                 display($qo);
    $ro= mysql_qry($qo);
    while ( $ro && ($o= mysql_fetch_object($ro)) ) {
      $id= $o->_id;
      if ( $x->selected && !in_array($id,$selected) ) continue;
      list($prijmeni,$jmeno)= explode(' ',$o->_name);
      $psc= $o->_psc;
      $zz[]= (object)array(
      'id_o'=>$id,
      'lat'=> isset($lat[$psc]) ? $lat[$psc] : 0,
      'lng'=> isset($lng[$psc]) ? $lng[$psc] : 0,
      'access'=>$o->access,
      'prijmeni'=>$prijmeni,
      'jmeno'=>$jmeno,
      'ulice'=>$o->_ulice,
      'psc'=>$psc,
      'obec'=>$o->_obec,
      '_vek'=>$o->_vek,
      'mail'=>$o->_email,
      'telefon'=>$o->_telefon,
      '_id_o'=>$id
      );
    }
    # ==> ... řazení
    if ( $x->order && count($zz)>0 ) {
      $test_clmn= substr($x->order,2);
      $test_asc= substr($x->order,0,1)=='a' ? 1 : -1;
      // výběr řazení: numerické | alfanumerické
      $numeric= in_array($test_clmn,array('_id_o'));
      if ( $numeric ) {
                                        display("usort $test_clmn $test_asc/numeric");
        usort($zz,function($a,$b) {
          global $test_clmn,$test_asc;
          $c= $a->$test_clmn == $b->$test_clmn ? 0 : ($a->$test_clmn > $b->$test_clmn ? 1 : -1);
          return $test_asc * $c;
        });
      }
      else {
        // alfanumerické je řazení podle operačního systému
        $asi_windows= preg_match('/^\w+\.ezer|192.168/',$_SERVER["SERVER_NAME"]);
        if ( $asi_windows ) {
          // asi Windows
          setlocale(LC_ALL, "cs_CZ.utf8","Czech");
          usort($zz,function($a,$b) {
            global $test_clmn,$test_asc;
            $ax= utf2win($a->$test_clmn,1); $bx= utf2win($b->$test_clmn,1);
            $c= $test_asc * strcoll($ax,$bx);
            return $c;
          });
        }
        else {
          // asi Linux
          setlocale(LC_ALL, "cs_CZ.utf8","Czech");
          usort($zz,function($a,$b) {
            global $test_clmn,$test_asc;
            $a0= mb_substr($a->$test_clmn,0,1);
            $b0= mb_substr($b->$test_clmn,0,1);
            if ( $a0=='(' ) {
              $c= -$test_asc;
            }
            elseif ( $b0=='(' ) {
              $c= $test_asc;
            }
            else {
              $c= $test_asc * strcoll($a->$test_clmn,$b->$test_clmn);
            }
            return $c;
          });
        }
      }
    }
    if ( $x->cmd=='browse_load' ) {
      # předání pro browse
      $y->values= $zz;
      $y->from= 0;
      $y->cursor= 0;
      $y->rows= count($zz);
      $y->count= count($zz);
      $y->ok= 1;
      array_unshift($y->values,null);
    }
    else if ( $x->cmd=='browse_export' ) { #==> ... browse_export
      // transformace dat
      $clmn= array();
      foreach($zz as $z) {
        $row= array(
          'jmeno'  => "{$z->jmeno} {$z->prijmeni}",
          'ulice'  => $z->ulice,
          'psc'    => $z->psc,
          'obec'   => $z->obec,
          'telefon'=> $z->telefon,
          'mail'   => $z->mail
        );
        $clmn[]= $row;
      }
      // tisk přes sta2_excel_export
      $tab= (object)array(
        'tits'=>explode(',',"jmeno:30,ulice:20,psc:6,obec:20,telefon:25,mail:30"),
        'flds'=>explode(',',"jmeno,ulice,psc,obec,telefon,mail"),
        'clmn'=>$clmn
      );
      $ret= sta2_excel_export("Vybrané kontakty ze seznamu '$nazev'",$tab);
      $y->par= $x->par;
      $y->par->html= $ret->html;
    }
    break;
  default:
    fce_error("metoda {$x->cmd} není podporována");
  }
//                                                              debug($y);
  return $y;
}
# ==========================================================================================> . MAPA
# ----------------------------------------------------------------------------------==> .. mapa2_psc
# vrátí strukturu pro gmap
function mapa2_psc($psc,$obec) {
  // k PSČ zjistíme LAN,LNG
  $ret= (object)array('mark'=>'','n'=>0);
  $marks= $err= '';
  $err_psc= array();
  $n= 0; $del= '';
  foreach ($psc as $p=>$tit) {
    $qs= "SELECT psc,lat,lng FROM uir_adr.psc_axy WHERE psc='$p'";
    $rs= mysql_qry($qs);
    if ( $rs && ($s= mysql_fetch_object($rs)) ) {
      $n++;
      $o= $obec[$p];
      $title= str_replace(',','',"$o:$tit");
      $marks.= "{$del}$n,{$s->lat},{$s->lng},$title"; $del= ';';
    }
    else {
      $err_psc[$p].= " $tit";
    }
  }
  // zjištění chyb
  if ( ($ne= count($err_psc)) ) {
    $err= "$ne PSČ se nepovedlo lokalizovat. Týká se to: ".implode(' a ',$err_psc);
//                                         debug($err_psc,"CHYBY");
  }
  $ret= (object)array('mark'=>$marks,'n'=>$n,'err'=>$err);
//                                         debug(explode(';',$ret->mark),"mapa_akce");
  return $ret;
}
# ------------------------------------------------------------------------------==> .. mapa2_ctverec
# ASK
# obsah čtverce $clen +- $dist (km) na všechny strany
# vrací objekt {
#   err:  0/1
#   msg:  text chyby
#   rect: omezující obdélník jako SW;NE
#   ryby: [geo_clen,...]
function mapa2_ctverec($ido,$dist) {  trace();
  $ret= (object)array('err'=>0,'msg'=>'');
  // zjištění polohy člena
  $lat0= $lng0= 0;
  $qc= "SELECT lat,lng
        FROM osoba AS o
        LEFT JOIN tvori AS t USING (id_osoba)
        LEFT JOIN rodina AS r USING (id_rodina)
        LEFT JOIN uir_adr.psc_axy AS a ON a.psc=IF(o.adresa,o.psc,r.psc)
        WHERE id_osoba=$ido";
  $rc= mysql_qry($qc);
  if ( $rc && $c= mysql_fetch_object($rc) ) {
    $lat0= $c->lat;
    $lng0= $c->lng;
  }
  if ( !$lat0 ) { $ret->msg= "nelze najít polohu osoby $ido"; $ret->err++; goto end; }
  // čtverec  SW;NE
  $ret->rect=($lat0-$dist*0.0089913097).",".($lng0-$dist*0.0137464041)
        .";".($lat0+$dist*0.0089913097).",".($lng0+$dist*0.0137464041);
end:
//                                                 debug($ret,"geo_get_ctverec");
  return $ret;
}
# --------------------------------------------------------------------------------- mapa2_ve_ctverci
# ASK
# vrátí jako seznam id_osoba bydlících v oblasti dané obdélníkem 'x,y;x,y'
# podmnožinu předaných ids, pokud je rect prázdný - vrátí vše, co lze lokalizovat
# pokud by seznam byl delší než MAX, vrátí chybu
function mapa2_ve_ctverci($rect,$ids,$max=5000) { trace();
  $ret= (object)array('err'=>'','rect'=>$rect,'ids'=>'','pocet'=>0);
  if ( $rect ) {
    list($sell,$nwll)= explode(';',$rect);
    $se= explode(',',$sell);
    $nw= explode(',',$nwll);
    $poloha= "lat BETWEEN $se[0] AND $nw[0] AND lng BETWEEN $se[1] AND $nw[1]";
  }
  else {
    $poloha= "lat!=0 AND lng!=0";
  }
  $qo= "SELECT id_osoba, lat, lng
        FROM osoba AS o
        LEFT JOIN tvori AS t USING (id_osoba)
        LEFT JOIN rodina AS r USING (id_rodina)
        LEFT JOIN uir_adr.psc_axy AS a ON a.psc=IF(o.adresa,o.psc,r.psc)
        WHERE id_osoba IN ($ids) AND $poloha ";
  $ro= mysql_qry($qo);
  if ( $ro ) {
    $ret->pocet= mysql_num_rows($ro);
    if ( $max && $ret->pocet > $max ) {
      $ret->err= ($rect ? "Ve výřezu mapy je" : "Je požadováno"). " příliš mnoho bodů "
        . "({$ret->pocet} nejvíc lze $max)";
    }
    else {
      $del= '';
      while ( $ro && $o= mysql_fetch_object($ro) ) {
        $ret->ids.= "$del{$o->id_osoba}"; $del= ',';
      }
    }
  }
  return $ret;
}
# ------------------------------------------------------------------------------- mapa2_mimo_ctverec
# ASK
# vrátí jako seznam id_osoba bydlících mimo oblast danou obdélníkem 'x,y;x,y'
# podmnožinu předaných ids
# pokud by seznam byl delší než MAX, vrátí chybu
function mapa2_mimo_ctverec($rect,$ids,$max=5000) { trace();
  $ret= (object)array('err'=>'','rect'=>$rect,'ids'=>'','pocet'=>0);
  list($sell,$nwll)= explode(';',$rect);
  $se= explode(',',$sell);
  $nw= explode(',',$nwll);
  $qo= "SELECT id_osoba, lat, lng
        FROM osoba AS o
        LEFT JOIN tvori AS t USING (id_osoba)
        LEFT JOIN rodina AS r USING (id_rodina)
        LEFT JOIN uir_adr.psc_axy AS a ON a.psc=IF(o.adresa,o.psc,r.psc)
        WHERE id_osoba IN ($ids)
          AND NOT (lat BETWEEN $se[0] AND $nw[0] AND lng BETWEEN $se[1] AND $nw[1]) ";
  $ro= mysql_qry($qo);
  if ( $ro ) {
    $ret->pocet= mysql_num_rows($ro);
    if ( $max && $ret->pocet > $max ) {
      $ret->err= "Ve výřezu mapy je příliš mnoho bodů "
        . "({$ret->pocet} nejvíc lze $max)";
    }
    else {
      $del= '';
      while ( $ro && $o= mysql_fetch_object($ro) ) {
        $ret->ids.= "$del{$o->id_osoba}"; $del= ',';
      }
    }
  }
  return $ret;
}
/** ==========================================================================================> STA2 */
# ================================================================================> . sta2_struktura
# tabulka struktury kurzu (noví,podruhé,vícekrát,odpočívající VPS,VPS)
# par.od= rok počátku statistik
function sta2_struktura($org,$par,$title,$export=false) {
  $od_roku= $par->od ?: 0;
  $par->fld= 'nazev';
  $par->tit= 'nazev';
  $tab= sta2_akcnost_vps($org,$par,$title,true);
//                                                    debug($tab,"evid_sestava_v(,$title,$export)");
  $clmn= $suma= array();
  $tit= "rok,rodin,u nás - noví,podruhé,vícekrát,vps - odpočívající,ve službě,pečounů,"
      . "dětí na kurzu,dětí<18 let nechaných doma,manželství,věk muže,věk ženy";
  $tits= explode(',',$tit);
  $fld= "rr,u,n,p,v,vo,vs,pec,d,x,m,a,b";
  $flds= explode(',',$fld);
  $flds_rr= explode(',',substr($fld,3));
  for ($rrrr=date('Y');$rrrr>=1990;$rrrr--) {
    if ( $rrrr<$od_roku ) continue;
    $rr= substr($rrrr,-2);
    $clmn[$rr]= array('rr'=>$rrrr,'u'=>0);
    $rows= count($tab->clmn);
    for ($n= 1; $n<=$rows; $n++) {
      if ( $xrr= $tab->clmn[$n][$rr] ) {
        $vps= 0;
        $ucast= 0;
        for ($yyyy= $rrrr; $yyyy>=1990; $yyyy--) {
          $yy= substr($yyyy,-2);
          if ( $tab->clmn[$n][$yy] ) $ucast++;
          if ( $tab->clmn[$n][$yy]=='v' ) $vps++;
        }
        // zhodnocení minulosti
        $clmn[$rr]['n']+= !$vps && $ucast==1 ? 1 : 0;
        $clmn[$rr]['p']+= !$vps && $ucast==2 ? 1 : 0;
        $clmn[$rr]['v']+= !$vps && $ucast>2  ? 1 : 0;
        $clmn[$rr]['vo']+= $vps && $xrr=='o' ? 1 : 0;
        $clmn[$rr]['vs']+= $vps && $xrr=='v' ? 1 : 0;
      }
    }
    // přepočty v daném roce
    $suma[$rr]= 0;
    foreach($flds_rr as $fld) {
      $suma[$rr]+= $clmn[$rr][$fld];
    }
  }
  // doplnění informací o rodinách
  $rod= sta2_rodiny($org);
//                                         debug($rod,"rodiny");
  foreach ($rod as $rok=>$r) {
    if ( $rok<$od_roku ) continue;
    $rr= substr($rok,-2);
    $clmn[$rr]['u']= $r['r'];
    $clmn[$rr]['d']= $r['d'];
    $clmn[$rr]['x']= $r['x'];
    $clmn[$rr]['m']= $r['m'];
    $clmn[$rr]['a']= $r['a'];
    $clmn[$rr]['b']= $r['b'];
  }
  // doplnění počtu pečounů
  $pec= sta2_pecouni($org);
//                                         debug($pec,"pečouni");
  foreach ($pec as $p) {
    $rrrr= $p['_rok'];
    if ( $rrrr<$od_roku ) continue;
    $rr= substr($rrrr,-2);
    $clmn[$rr]['pec']= $p['_pec'];
//                                         debug($p,"pečouni {$p->_rok}=$rr");
//     break;
  }
  // smazání prázdných
  foreach ($clmn as $r=>$c) {
    if ( !$c['x'] ) unset($clmn[$r]);
  }

//                                         debug($suma,"součty");
//                                                         debug($clmn,"evid_sestava_s:$tit;$fld");
  $par->tit= $tit;
  $par->fld= $fld;
  $par->grf= "u:n,p,v,vo,vs,pec,d,x";
  $par->txt= "Pozn. Graficky je znázorněn absolutní počet." //relativní počet vzhledem k počtu párů.;
    . "<br>Pokud v nějakém roce bylo více běhů je zobrazen jejich součet.";
  return sta2_table_graph($par,$tits,$flds,$clmn,$export);
}
# --------------------------------------------------------------------------------- sta2_akcnost_vps
# generování přehledu akčnosti VPS
function sta2_akcnost_vps($org,$par,$title,$export=false) {
  // dekódování parametrů
  $roky= '';
  for ($r=1990;$r<=date('Y');$r++) {
    $roky.= ','.substr($r,-2);
    $froky.= ','.substr($r,-2).':3';
  }
  $tits= explode(',',$tit= $par->tit.$froky);
  $flds= explode(',',$fld= $par->fld.$roky);
  $HAVING= $par->hav ? "HAVING {$par->hav}" : '';
  $fce= "ovxkphmx";
  // získání dat
  $n= 0;
  $clmn= array();
  $expr= array();       // pro výrazy
  $qry="SELECT COUNT(*) AS _ucasti, r.nazev,r.obec,
          SUM(p.funkce) AS _vps,
          GROUP_CONCAT(DISTINCT CONCAT(YEAR(a.datum_od),':',p.funkce) ORDER BY datum_od SEPARATOR '|') AS _x,
          GROUP_CONCAT(DISTINCT IF(t.role='a',o.jmeno,'') SEPARATOR '') AS jmeno_m,
          GROUP_CONCAT(DISTINCT IF(t.role='b',o.jmeno,'') SEPARATOR '') AS jmeno_z
        FROM pobyt AS p
        JOIN spolu AS s USING(id_pobyt)
        JOIN osoba AS o ON s.id_osoba=o.id_osoba
        LEFT JOIN tvori AS t ON t.id_osoba=o.id_osoba
        LEFT JOIN rodina AS r USING(id_rodina)
        JOIN akce AS a ON a.id_duakce=p.id_akce
        WHERE a.druh=1 AND p.funkce IN (0,1) AND a.access & $org
        GROUP BY r.id_rodina $HAVING
        ORDER BY r.nazev
        ";
  $res= mysql_qry($qry);
  while ( $res && ($x= mysql_fetch_object($res)) ) {
    $n++;
    $clmn[$n]= array();
    foreach($flds as $f) {
      switch ( $f ) {
      case '_jmeno':                            // kolektivní člen
        $clmn[$n][$f]= "{$x->nazev} {$x->jmeno_m} a {$x->jmeno_z}";
        break;
      default:
        $clmn[$n][$f]= $x->$f;
      }
    }
    // rozbor let
    foreach(explode('|',$x->_x) as $rf) {
      list($xr,$xf)= explode(':',$rf);
      $clmn[$n][substr($xr,-2)]= $xf < strlen($fce) ? substr($fce,$xf,1) : '?';
    }
  }
//                                                 debug($clmn,"clmn");
  $par->tit= $tit;
  $par->fld= $fld;
  return sta2_table_graph($par,$tits,$flds,$clmn,$export);
}
# --------------------------------------------------------------------------------- sta2_table_graph
# pokud je $par->grf= a:b,c,... pak se zobrazí grafy normalizované podle sloupce a
# pokud je $par->txt doplní se pod tabulku
function sta2_table_graph($par,$tits,$flds,$clmn,$export=false) {
  $result= (object)array('par'=>$par);
  if ( $par->grf ) {
    list($norm,$grf)= explode(':',$par->grf);
  }
  $skin= $_SESSION['skin'];
  // zobrazení tabulkou
  $tab= '';
  $thd= '';
  $n= 0;
  if ( $export ) {
    $result->tits= $tits;
    $result->flds= $flds;
    $result->clmn= $clmn;
  }
  else {
    // titulky
    foreach ($tits as $idw) {
      list($id)= explode(':',$idw);
      $ths.= "<th>$id</th>";
    }
    foreach ($clmn as $i=>$c) {
      $tab.= "<tr>";
      foreach ($flds as $f) {
        // přidání grafu
        $g= '';
        if ( strpos(",$grf,",",$f,")!==false ) {
          //$w= $c[$norm] ? round(100*($c[$f]/$c[$norm]),0) : 0;     -- relativní počet
          $w= $c[$f];
          $g= "<div class='curr_akce' style='height:4px;width:{$w}px;float:left;margin-top:5px'>";
        }
        $align= is_numeric($c[$f]) || preg_match("/\d+\.\d+\.\d+/",$c[$f]) ? "right" : "left";
        $tab.= "<td style='text-align:$align'>{$c[$f]}$g</td>";
      }
      $tab.= "</tr>";
      $n++;
    }
    $result->html= "<div class='stat'><table class='stat'><tr>$ths</tr>$tab</table>
      $n řádků<br><br>{$par->txt}</div>";
  }
//                                                 debug($result);
  return $result;
}
# ---------------------------------------------------------------------------------==> . sta2_rodiny
# clmn: rok -> r:rodin, d:dětí na akci, x:dětí<18 doma, m:délka manželství, a,b:věk muže, ženy
function sta2_rodiny($org,$rok=0) {
  $clmn= array();
  $ms= array();
  // ms => r=rodin, d=dětí na akci, D=dětí mladších 18 v rodině,
  //       va=věk muže, na=počet mužů s věkem, vb=věk ženy, nb=.., vm=délka manželství, nm=..
  $HAVING= $rok ? "HAVING _rok=$rok" : '';
  $rx= mysql_qry("
    SELECT id_akce, YEAR(datum_od) AS _rok,
      COUNT(id_osoba) AS _clenu, COUNT(id_spolu) AS _spolu,
      SUM(IF(t.role='d' AND id_spolu,1,0)) AS _sebou,
      SUM(IF(t.role='d' AND DATEDIFF(a.datum_od,o.narozeni)/365.2425 < 18,1,0)) AS _deti,
      SUM(IF(t.role='a',DATEDIFF(a.datum_od,o.narozeni)/365.2425,0)) AS _vek_a,
      SUM(IF(t.role='b',DATEDIFF(a.datum_od,o.narozeni)/365.2425,0)) AS _vek_b,
      IF(r.datsvatba,DATEDIFF(a.datum_od,r.datsvatba)/365.2425,
        IF(r.svatba,YEAR(a.datum_od)-svatba,0)) AS _vek_m
    FROM pobyt AS p
    JOIN akce AS a ON id_akce=id_duakce
    JOIN rodina AS r ON id_rodina=i0_rodina
    JOIN tvori AS t USING (id_rodina)
    JOIN osoba AS o USING (id_osoba)
    LEFT JOIN spolu USING (id_pobyt,id_osoba)
    WHERE a.druh=1 AND p.funkce IN (0,1) AND a.access & $org
    GROUP BY id_pobyt $HAVING
  ");
  while ( $rx && ($x= mysql_fetch_object($rx)) ) {
    $r= $x->_rok;
    $ms[$r]['r']++;
    $ms[$r]['d']+= $x->_sebou;
    $ms[$r]['D']+= $x->_deti;
    if ( $x->_vek_a ) {
      $ms[$r]['va']+= $x->_vek_a;
      $ms[$r]['na']++;
    }
    if ( $x->_vek_b ) {
      $ms[$r]['vb']+= $x->_vek_b;
      $ms[$r]['nb']++;
    }
    if ( $x->_vek_m ) {
      $ms[$r]['vm']+= $x->_vek_m;
      $ms[$r]['nm']++;
    }
  }
  foreach (array_keys($ms) as $r) {
    $clmn[$r]['r']= $ms[$r]['r'];
    $clmn[$r]['d']= $ms[$r]['d'];
    $clmn[$r]['x']= $ms[$r]['D'] - $ms[$r]['d'];
    $clmn[$r]['m']= round($ms[$r]['nm'] ? $ms[$r]['vm']/$ms[$r]['nm'] : 0);
    $clmn[$r]['a']= round($ms[$r]['na'] ? $ms[$r]['va']/$ms[$r]['na'] : 0);
    $clmn[$r]['b']= round($ms[$r]['nb'] ? $ms[$r]['vb']/$ms[$r]['nb'] : 0);
  }
//                                                         debug($clmn,"sta2_rodiny($org,$rok)");
  return $clmn;
}
# --------------------------------------------------------------------------------==> . sta2_pecouni
function sta2_pecouni($org) {
//   case 'ms-pecouni': // -------------------------------------==> .. ms-pecouni
  # _pec,_sko,_proc
  $clmn= array();
  list($od,$do)= select("MAX(YEAR(datum_od)),MIN(YEAR(datum_od))","akce","druh=1 AND access&$org");
  for ($rok=$od; $rok>=$do; $rok--) {
    $kurz= select1("id_duakce","akce","druh=1 AND YEAR(datum_od)=$rok AND access&$org");
    $akci= select1("COUNT(*)","akce","druh=7 AND YEAR(datum_od)=$rok AND access&$org");
    $akci= $akci ? "$akci školení" : '';
    $info= akce2_info($kurz,0); //muzi,zeny,deti,peco,rodi,skup
    // získání dat
    $_pec= $_sko= $_proc= $_pecN= $_skoN= $_procN= 0;
    $data= array();
    _akce2_sestava_pecouni($data,$kurz);
    $_pec= count($data);
    if ( !$_pec ) continue;
    foreach ($data as $d) {
      $skoleni= 0;
      $sko= array_unique(preg_split("/\s+/",$d['_skoleni'], -1, PREG_SPLIT_NO_EMPTY));
      $slu= array_unique(preg_split("/\s+/",$d['_sluzba'],  -1, PREG_SPLIT_NO_EMPTY));
      $ref= array_unique(preg_split("/\s+/",$d['_reflexe'], -1, PREG_SPLIT_NO_EMPTY));
      $leto= $slu[0];
      // výpočet školení všech
      $skoleni+= count($sko);
      foreach ($ref as $r) if ( $r<$leto ) $skoleni++;
      $_sko+= $skoleni>0 ? 1 : 0;
      // noví
      if ( count($slu)==1 ) {
        $_pecN++;
        $_skoN+= $skoleni>0 ? 1 : 0;
      }
    }
    $_proc= $_pec ? round(100*$_sko/$_pec).'%' : '';
    $_procN= $_pecN ? round(100*$_skoN/$_pecN).'%' : '';
    $note= $akci;
    $ratio= round($info->deti/$_pec,1);
    $note.= ", $ratio";
    // zobrazení výsledků
    $clmn[]= array('_rok'=>$rok,'_rodi'=>$info->rodi,'_deti'=>$info->deti,
      '_pec'=>$_pec,'_sko'=>$_sko,'_proc'=>$_proc,
      '_pecN'=>$_pecN,'_skoN'=>$_skoN,'_procN'=>$_procN,'_note'=>$note);
//       if ( $rok==2014) break;
  }
  return $clmn;
}
# ==================================================================================> . sta2_sestava
# sestavy pro evidenci
function sta2_sestava($org,$title,$par,$export=false) {
//                                                 debug($par,"sta2_sestava($title,...,$export)");
  $ret= (object)array('html'=>'','err'=>0);
  // dekódování parametrů
  $tits= $par->tit ? explode(',',$par->tit) : array();
  $flds= $par->fld ? explode(',',$par->fld) : array();
  $clmn= array();
  $expr= array();       // pro výrazy
  // získání dat
  switch ($par->typ) {
  # Sestava pečounů na letních kurzech, rok= před kolika lety naposledy ve funkci (0=jen letos)
  case 'pecujici':     // -----------------------------------==> .. pecujici
    $cert= array(); // certifikát rok=>poslední číslo
    $rok= date('Y');
    $hranice= date('Y') - $par->parm;
    $tits= array("pečovatel:20","certifikát:20","poprvé:10","kolikrát:10","naposledy:10","1.školení:10",
                 "č.člen od:10","bydliště:25","narození:10","(ID osoby)");
    $flds= array('jm','cert','od','n','do','vps_i','clen','byd','nar','^id_osoba');
    $rx= mysql_qry("SELECT
        o.id_osoba,jmeno,prijmeni,o.obec,narozeni,
        MIN(CONCAT(t.role,IF(o.adresa,o.obec,r.obec))) AS _obec,
        MIN(IF(druh=1,YEAR(datum_od),9999)) AS OD,
        MAX(IF(druh=1,YEAR(datum_od),0)) AS DO,
        CEIL(CHAR_LENGTH(
          GROUP_CONCAT(DISTINCT IF(druh=1 AND funkce=99,YEAR(datum_od),'') SEPARATOR ''))/4) AS Nx,
        MIN(IF(druh=7,YEAR(datum_od),9999)) AS _skoleni,
        GROUP_CONCAT(DISTINCT od.ukon ORDER BY od.ukon SEPARATOR '') as rel,
        GROUP_CONCAT(DISTINCT CONCAT(ukon,':',YEAR(dat_od),':',YEAR(dat_do),':',castka)
          ORDER BY dat_od DESC SEPARATOR '|') AS _ukony
      FROM osoba AS o
      JOIN spolu AS s USING (id_osoba)
      JOIN pobyt AS p USING (id_pobyt)
      JOIN akce as a ON id_akce=id_duakce
      LEFT JOIN dar AS od ON o.id_osoba=od.id_osoba AND od.deleted=''
      LEFT JOIN tvori AS t ON t.id_osoba=o.id_osoba
      LEFT JOIN rodina AS r ON t.id_rodina=r.id_rodina
      WHERE p.funkce=99 AND a.access&$org
        -- AND o.prijmeni LIKE 'D%'
        AND druh IN (1,7)
      GROUP BY o.id_osoba
      -- HAVING
        -- _skoleni<9999 AND
        -- DO>=$hranice
      ORDER BY o.prijmeni");
    while ( $rx && ($x= mysql_fetch_object($rx)) ) {
      // číslování certifikátů
      $skola= $x->_skoleni==9999 ? 0 : $x->_skoleni;
      $c1= '';
      if ( $skola ) {
        if ( !isset($cert[$skola]) ) $cert[$skola]= 0;
        $cert[$skola]++; $c1= "pec_$skola/{$cert[$skola]}";
      }
      // ohlídání období
      if ( $x->DO<$hranice ) continue;
      // rozbor úkonů
      $_clen_od= $_cinny_od= $_prisp= $prisp_letos= $_dary= 0;
      foreach(explode('|',$x->_ukony) as $uddc) {
        list($u,$d1,$d2,$c)= explode(':',$uddc);
        switch ($u) {
        case 'p': if ( $d1==$rok ) $_prisp+= $c; break;
        case 'd': if ( $d1==$rok ) $_dary+= $c; break;
        case 'b': if ( $d2<=$rok && (!$_clen_od && $d1<=$rok || $d1<$_clen_od) ) $_clen_od= $d1; break;
        case 'c': if ( $d2<=$rok && (!$_cinny_od && $d1<=$rok || $d1<$_cinny_od) ) $_cinny_od= $d1; break;
        }
      }
      $cclen= $_cinny_od ?: '-';
      // odpověď
      $clmn[]= array(
        'jm'=>"{$x->prijmeni} {$x->jmeno}",'od'=>$x->OD,'n'=>$x->Nx,'do'=>$x->DO,
        'vps_i'=>$skola ?: '-', 'cert'=>$c1,
        'clen'=>$cclen,
        'byd'=>$x->_obec ? substr($x->_obec,1) : $x->obec,
        'nar'=>substr($x->narozeni,2,2).substr($x->narozeni,5,2).substr($x->narozeni,8,2),
        '^id_osoba'=>$x->id_osoba
      );
    }
//                                                 debug($clmn,"$hranice");
    break;
  # Sestava sloužících na letních kurzech, rok= před kolika lety naposledy ve funkci (0=jen letos)
  case 'slouzici':     // -------------------------------------==> .. slouzici
    global $VPS;
    $cert= array(); // certifikát rok=>poslední číslo
    $rok= date('Y');
    $hranice= date('Y') - $par->parm;
    $vps1= $org==1 ? '3,17' : '3';
    if ( $par->podtyp=='pary' ) {
      $tits= array("pár:26","poprvé:10","kolikrát:10","naposledy:10",
                 $org==1?"VPS I:10":"1.školení:10","č.člen od:10","(ID)");
      $flds= array('jm','od','n','do','vps_i','clen','^id_rodina');
    }
    else { // osoby
      $tits= array("jméno:20","certifikát:20","poprvé:10","kolikrát:10","naposledy:10",
                 $org==1?"VPS I:10":"1.školení:10","č.člen od:10","bydliště:25","narození:10","(ID)");
      $flds= array('jm','cert','od','n','do','vps_i','clen','byd','nar','^id_osoba');
    }
    $rx= mysql_qry("SELECT
        r.id_rodina,r.nazev,
        GROUP_CONCAT(DISTINCT IF(t.role='a',o.id_osoba,'') SEPARATOR '') as id_m,
        GROUP_CONCAT(DISTINCT IF(t.role='a',o.jmeno,'') SEPARATOR '') as jmeno_m,
        GROUP_CONCAT(DISTINCT IF(t.role='a',o.prijmeni,'') SEPARATOR '') as prijmeni_m,
        GROUP_CONCAT(DISTINCT IF(t.role='a',o.narozeni,'') SEPARATOR '') as narozeni_m,
        GROUP_CONCAT(DISTINCT IF(t.role='a',IF(o.adresa,o.obec,r.obec),'') SEPARATOR '') as obec_m,
        GROUP_CONCAT(DISTINCT IF(t.role='b',o.id_osoba,'') SEPARATOR '') as id_z,
        GROUP_CONCAT(DISTINCT IF(t.role='b',o.jmeno,'') SEPARATOR '') as jmeno_z,
        GROUP_CONCAT(DISTINCT IF(t.role='b',o.prijmeni,'') SEPARATOR '') as prijmeni_z,
        GROUP_CONCAT(DISTINCT IF(t.role='b',o.narozeni,'') SEPARATOR '') as narozeni_z,
        GROUP_CONCAT(DISTINCT IF(t.role='b',IF(o.adresa,o.obec,r.obec),'') SEPARATOR '') as obec_z,
        MIN(IF(druh=1 AND funkce=1,YEAR(datum_od),9999)) AS OD,
        CEIL(CHAR_LENGTH(
          GROUP_CONCAT(DISTINCT IF(druh=1 AND funkce=1,YEAR(datum_od),'') SEPARATOR ''))/4) AS Nx,
        MAX(IF(druh=1 AND funkce=1,YEAR(datum_od),0)) AS DO,
        MIN(IF(druh IN ($vps1),YEAR(datum_od),9999)) as VPS_I,
        GROUP_CONCAT(DISTINCT od.ukon ORDER BY od.ukon SEPARATOR '') as rel,
        GROUP_CONCAT(DISTINCT CONCAT(ukon,':',YEAR(dat_od),':',YEAR(dat_do),':',castka)
          ORDER BY dat_od DESC SEPARATOR '|') AS _ukony
      FROM rodina AS r
      JOIN pobyt AS p
      JOIN akce as a ON id_akce=id_duakce
      JOIN tvori AS t USING (id_rodina)
      JOIN osoba AS o USING (id_osoba)
      LEFT JOIN dar AS od ON o.id_osoba=od.id_osoba AND od.deleted=''
      WHERE spec=0 AND r.id_rodina=i0_rodina AND a.access&$org
        -- AND r.nazev LIKE 'Šmí%'
        AND druh IN (1,$vps1)
      GROUP BY r.id_rodina
      -- HAVING -- bereme vše kvůli číslům certifikátů - vyřazuje se až při průchodu
        -- VPS_I<9999 AND
        -- DO>=$hranice
      ORDER BY r.nazev");
    while ( $rx && ($x= mysql_fetch_object($rx)) ) {
      // číslování certifikátů
      $skola= $x->VPS_I==9999 ? 0 : $x->VPS_I;
      $c1= $c2= '';
      if ( $skola ) {
        if ( !isset($cert[$skola]) ) $cert[$skola]= 0;
        $cert[$skola]++; $c1= ($org==1?'vps':'pps')."_$skola/{$cert[$skola]}";
        $cert[$skola]++; $c2= ($org==1?'vps':'pps')."_$skola/{$cert[$skola]}";
      }
      // ohlídání období
      if ( $x->DO<$hranice ) continue;
      // rozbor úkonů
      $_clen_od= $_cinny_od= $_prisp= $prisp_letos= $_dary= 0;
      foreach(explode('|',$x->_ukony) as $uddc) {
        list($u,$d1,$d2,$c)= explode(':',$uddc);
        switch ($u) {
        case 'p': if ( $d1==$rok ) $_prisp+= $c; break;
        case 'd': if ( $d1==$rok ) $_dary+= $c; break;
        case 'b': if ( $d2<=$rok && (!$_clen_od && $d1<=$rok || $d1<$_clen_od) ) $_clen_od= $d1; break;
        case 'c': if ( $d2<=$rok && (!$_cinny_od && $d1<=$rok || $d1<$_cinny_od) ) $_cinny_od= $d1; break;
        }
      }
      $cclen= $_cinny_od ?: '-';
      // odpověď
      if ( $par->podtyp=='pary' ) {
        $clmn[]= array(
          'jm'=>"{$x->jmeno_m} a {$x->jmeno_z} {$x->nazev}",
          'od'=>$x->OD,'n'=>$x->Nx,'do'=>$x->DO,
          'vps_i'=>$skola ?: '-',
          'clen'=>$cclen,'^id_rodina'=>$x->id_rodina
        );
      }
      else { // osoby
        $clmn[]= array(
          'jm'=>"{$x->prijmeni_m} {$x->jmeno_m}",'od'=>$x->OD,'n'=>$x->Nx,'do'=>$x->DO,
          'vps_i'=>$skola ?: '-', 'cert'=>$c1, 'clen'=>$cclen, 'byd'=>$x->obec_m,
          'nar'=>substr($x->narozeni_m,2,2).substr($x->narozeni_m,5,2).substr($x->narozeni_m,8,2),
          '^id_osoba'=>$x->id_m
        );
        $clmn[]= array(
          'jm'=>"{$x->prijmeni_z} {$x->jmeno_z}",'od'=>$x->OD,'n'=>$x->Nx,'do'=>$x->DO,
          'vps_i'=>$skola ?: '-', 'cert'=>$c2, 'clen'=>$cclen, 'byd'=>$x->obec_z,
          'nar'=>substr($x->narozeni_z,2,2).substr($x->narozeni_z,5,2).substr($x->narozeni_z,8,2),
          '^id_osoba'=>$x->id_z
        );
      }
    }
//                                                 debug($clmn,"$hranice");
    break;
  # Sestava přednášejících na letních kurzech, rok= kolik let dozadu (0=jen letos)
  case 'prednasejici': // -----------------------------------==> .. prednasejici
    $do= date('Y');
    $od= $do - $par->parm + 1;
    $tits[]= "přednáška:20";
    $flds[]= 1;
    for ($rok= $do; $rok>=$od; $rok--) {
      $tits[]= "$rok:26";
      $flds[]= $rok;
    }
    $prednasky= map_cis('ms_akce_prednasi','zkratka');
    foreach ($prednasky as $pr=>$prednaska) {
      $clmn[$pr][1]= $prednaska;
      $rx= mysql_qry("SELECT prednasi,YEAR(a.datum_od) AS _rok,
          GROUP_CONCAT(DISTINCT IF(t.role='a',o.prijmeni,'') SEPARATOR '') as prijmeni_m,
          GROUP_CONCAT(DISTINCT IF(t.role='a',o.jmeno,'')    SEPARATOR '') as jmeno_m,
          GROUP_CONCAT(DISTINCT IF(t.role='b',o.prijmeni,'') SEPARATOR '') as prijmeni_z,
          GROUP_CONCAT(DISTINCT IF(t.role='b',o.jmeno,'')    SEPARATOR '') as jmeno_z,
          p.pouze,r.nazev
        FROM pobyt AS p
        JOIN spolu AS s USING(id_pobyt)
        JOIN osoba AS o ON s.id_osoba=o.id_osoba
        LEFT JOIN tvori AS t ON t.id_osoba=o.id_osoba
        LEFT JOIN rodina AS r ON r.id_rodina=IFNULL(i0_rodina,t.id_rodina)
        JOIN akce AS a ON a.id_duakce=p.id_akce
        WHERE a.druh=1 AND p.prednasi=$pr AND YEAR(a.datum_od) BETWEEN $od AND $do AND a.access&$org
        GROUP BY id_pobyt -- ,_rok
        ORDER BY _rok DESC");
      while ( $rx && ($x= mysql_fetch_object($rx)) ) {
        $jm= $x->pouze==1 ? "{$x->prijmeni_m} {$x->jmeno_m}"
           : ($x->pouze==2 ? "{$x->prijmeni_z} {$x->jmeno_z}"
           : "{$x->nazev} {$x->jmeno_m} a {$x->jmeno_z}");
        if ( isset($clmn[$pr][$x->_rok]) ) {
          $xx= "{$prednasky[$x->prednasi]}/{$x->_rok}";
          fce_warning("POZOR: přednáška $xx má více přednášejících");
        }
        $clmn[$pr][$x->_rok].= "$jm ";
      }
    }
//                                                 debug($clmn,"$od - $do");
    break;
  # Sestava ukazuje letní kurzy
  # fld:'_rok,_pec,_sko,_proc,_pecN,_skoN,_procN,_note'
  case 'ms-pecouni': // -------------------------------------==> .. ms-pecouni
    # _pec,_sko,_proc
    $clmn= sta2_pecouni($org);
    break;
    list($od,$do)= select("MAX(YEAR(datum_od)),MIN(YEAR(datum_od))","akce","druh=1 AND access&$org");
    for ($rok=$od; $rok>=$do; $rok--) {
      $kurz= select1("id_duakce","akce","druh=1 AND YEAR(datum_od)=$rok AND access&$org");
      $akci= select1("COUNT(*)","akce","druh=7 AND YEAR(datum_od)=$rok AND access&$org");
      $akci= $akci ? "$akci školení" : '';
      $info= akce2_info($kurz,0); //muzi,zeny,deti,peco,rodi,skup
      // získání dat
      $_pec= $_sko= $_proc= $_pecN= $_skoN= $_procN= 0;
      $data= array();
      _akce2_sestava_pecouni($data,$kurz);
      $_pec= count($data);
      if ( !$_pec ) continue;
      foreach ($data as $d) {
        $skoleni= 0;
        $sko= array_unique(preg_split("/\s+/",$d['_skoleni'], -1, PREG_SPLIT_NO_EMPTY));
        $slu= array_unique(preg_split("/\s+/",$d['_sluzba'],  -1, PREG_SPLIT_NO_EMPTY));
        $ref= array_unique(preg_split("/\s+/",$d['_reflexe'], -1, PREG_SPLIT_NO_EMPTY));
        $leto= $slu[0];
        // výpočet školení všech
        $skoleni+= count($sko);
        foreach ($ref as $r) if ( $r<$leto ) $skoleni++;
        $_sko+= $skoleni>0 ? 1 : 0;
        // noví
        if ( count($slu)==1 ) {
          $_pecN++;
          $_skoN+= $skoleni>0 ? 1 : 0;
        }
      }
      $_proc= $_pec ? round(100*$_sko/$_pec).'%' : '';
      $_procN= $_pecN ? round(100*$_skoN/$_pecN).'%' : '';
      $note= $akci;
      $ratio= round($info->deti/$_pec,1);
      $note.= ", $ratio";
      // zobrazení výsledků
      $clmn[]= array('_rok'=>$rok,'_rodi'=>$info->rodi,'_deti'=>$info->deti,
        '_pec'=>$_pec,'_sko'=>$_sko,'_proc'=>$_proc,
        '_pecN'=>$_pecN,'_skoN'=>$_skoN,'_procN'=>$_procN,'_note'=>$note);
//       if ( $rok==2014) break;
    }
    break;
  # Sestava ukazuje celkový počet účastníků resp. pečovatelů na akcích letošního roku,
  # rozdělený podle věku. Účastník resp. pečovatel je započítán jen jednou,
  # bez ohledu na počet akcí, jichž se zúčastnil
  case 'ucast-vek': // ---------------------------------------------==> .. ucast-vek
    $rok= date('Y')-$par->rok;
    $rx= mysql_qry("
      SELECT YEAR(a.datum_od)-YEAR(o.narozeni) AS _vek,MAX(p.funkce) AS _fce
      FROM osoba AS o
      JOIN spolu AS s USING(id_osoba)
      JOIN pobyt AS p USING (id_pobyt)
      JOIN akce  AS a ON id_akce=id_duakce
      WHERE o.deleted='' AND YEAR(datum_od)=$rok AND a.access&$org
      GROUP BY o.id_osoba
      ORDER BY $par->ord
      ");
    while ( $rx && ($x= mysql_fetch_object($rx)) ) {
      $vek= $x->_vek==$rok ? '?' : $x->_vek;    // ošetření nedefinovaného data narození
      if ( !isset($clmn[$vek]) ) $clmn[$vek]= array('_vek'=>$vek,'_uca'=>0,'_pec'=>0);
      if ( $x->_fce==99 )
        $clmn[$vek]['_pec']++;
      else
        $clmn[$vek]['_uca']++;
    }
    break;
  # Seznam obsahuje účastníky akcí v posledních letech (parametr 'parm' určuje počet let zpět) —
  case 'adresy': // -------------------------------------------------------==> .. adresy
    $rok= date('Y') - $par->parm;
    $rok18= date('Y')-18;
    $AND= $par->cnd ? " AND $par->cnd " : '';
    // úprava title pro případný export do xlsx
    $par->title= $title.($par->rok ? " akcí za poslední ".($par->rok+1)." roky" : " letošních akcí");
    $idr0= -1; $ido= 0;
    $jmena= $role= $prijmeni= $akce= array();
    $adresa= '';
    $mrop= $pps= 0;
    // funkce pro přidání nové adresy do clmn: jmena,ulice,psc,obec,stat,akce,prijmeni,_clenu,id_osoba
    $add_address= function() use (&$clmn,&$jmena,&$role,&$prijmeni,&$adresa,&$akce,&$mrop,&$ido,&$pps) {
      list($pr,$ul,$ps,$ob,$st)= explode('—',$adresa);
      $cl= count($jmena);
      if ( $cl==1 ) {                             // nahrazení názvu příjmením u jediného člena
        $jm= "$jmena[0] $prijmeni[0]";
      }
      else {                                      // klasická rodina
        $xy= preg_match("/\w+[\s\-]+\w+/u",$pr);   //   a rodina s různým příjmením
//                                                 display("$pr = $xy");
        $jm= $pr1= $del= ''; $n= 0;
        for ($i= 0; $i<count($jmena); $i++) {
          if ( $role[$i]=='a' || $role[$i]=='b' ) {
            $n++;
            $pr1= $prijmeni[$i];
            $jm.= "$del $jmena[$i]".($xy ? " $prijmeni[$i]" : '');
            $del= ' a ';
          }
        }
        $jm.= $n==1 ? " $pr1" : ($xy ? '' : " $pr");
      }
      $jc= implode(', ',$jmena);
      $ak= implode(' a ',$akce);
      $mr= $mrop?:'';
      $pp= $pps?:'';
      $clmn[]= array('jmena'=>$jm,'ulice'=>$ul,'psc'=>$ps,'obec'=>$ob,'stat'=>$st,
                     'prijmeni'=>$pr,'_cleni'=>$jc,'akce'=>$ak,'_mrop'=>$mr,'_pps'=>$pp,'_clenu'=>$cl,'id_osoba'=>$ido);
    };
    $rx= mysql_qry("
      SELECT
        IFNULL(IF(adresa=0,SUBSTR(MIN(CONCAT(t.role,r.nazev,'—')),2),prijmeni),prijmeni) AS _order,
        IFNULL(IF(adresa=0,SUBSTR(MIN(CONCAT(t.role,id_rodina)),2),0),0) AS _idr,
        IFNULL(IF(adresa=0,MIN(t.role),'-'),'-') AS _role,
        IFNULL(IF(adresa=0,SUBSTR(MIN(
          CONCAT(t.role,r.nazev,'—',r.ulice,'—',r.psc,'—',r.obec,'—',r.stat)),2),''),'') AS _rodina,
        id_osoba,prijmeni,jmeno,adresa,iniciace,
        MAX(IF(t.role IN ('a','b') AND p.funkce=1,YEAR(datum_od),0)) as _pps,
        -- IF(roleMAX(CONCAT(YEAR(datum_od),' - ',a.nazev)) as _akce,
        MAX(CONCAT(datum_od,' - ',a.nazev)) as _akce,
        IF(ISNULL(id_rodina) OR adresa=1,CONCAT(o.ulice,'—',o.psc,'—',o.obec,'—',o.stat),'') AS _osoba,
        IF(ISNULL(id_rodina) OR adresa=1,o.psc,r.psc) AS _psc,
        IF(ISNULL(id_rodina) OR adresa=1,o.stat,r.stat) AS _stat
      FROM osoba AS o
        LEFT JOIN tvori AS t USING(id_osoba)
        LEFT JOIN rodina AS r USING (id_rodina)
        JOIN spolu AS s USING(id_osoba)
        JOIN pobyt AS p USING (id_pobyt)
        JOIN akce  AS a ON id_akce=id_duakce AND spec=0
      WHERE o.deleted='' AND YEAR(narozeni)<$rok18 AND a.access&$org
        AND YEAR(datum_od)>=$rok AND spec=0
        -- AND o.id_osoba IN(3726,3727,5210)
        -- AND o.id_osoba IN(4537,13,14,3751)
        -- AND o.id_osoba IN(4503,4504,4507,679,680,3612,4531,4532,206,207)
        -- AND id_duakce=394
      GROUP BY o.id_osoba HAVING _role!='p' $AND
      ORDER BY _order
      -- LIMIT 10
      ");
    while ( $rx && ($x= mysql_fetch_object($rx)) ) {
      $idr= $x->_idr;
      if ( $idr0 && $idr0==$idr ) {
        // zůstává rodina a tedy stejná adresa - jen zapamatuj další jméno, příjmení a akci
        $jmena[]= $x->jmeno;
        $role[]= $x->_role;
        $prijmeni[]= $x->prijmeni;
        $akce[]= substr($x->_akce,0,4).substr($x->_akce,10);
        $mrop= max($mrop,$x->iniciace);
        $pps= max($pps,$x->_pps);
      }
      else {
        // uložíme rodinu
        if ( $idr0!=-1 ) $add_address();
        // inicializace údajů další rodiny
        $ido= $x->id_osoba;
        $jmena= array($x->jmeno);
        $role= array($x->_role);
        $prijmeni= array($x->prijmeni);
        $akce= array(substr($x->_akce,0,4).substr($x->_akce,10));
        $mrop= $x->iniciace;
        $pps= $x->_pps;
        $adresa= $x->_osoba ? "{$x->prijmeni}—$x->_osoba" : $x->_rodina;
        $idr0= $idr;
      }
    }
    $add_address();
    break;
  default:
    $ret->err= $ret->html= 'N.Y.I.';
    break;
  }
end:
  if ( $ret->err )
    return $ret;
  else
    return sta2_table($tits,$flds,$clmn,$export);
}
# --------------------------------------------------------------------------------- sta2_excel_subst
function sta2_sestava_adresy_fill($matches) { trace();
  global $xA, $xn;
//                                                 debug($xA);
//                                                 debug($matches);
  if ( !isset($xA[$matches[1]]) ) fce_error("sta2_excel_subst: chybný název sloupce '{$matches[1]}'");
  $A= $xA[$matches[1]];
  $n= $xn+$matches[2];
  return "$A$n";
}
# -----------------------------------------------------------------------------------=> . sta2_table
function sta2_table($tits,$flds,$clmn,$export=false) {  trace();
  $ret= (object)array('html'=>'');
  // zobrazení tabulkou
  $tab= '';
  $thd= '';
  $n= 0;
  if ( $export ) {
    $ret->tits= $tits;
    $ret->flds= $flds;
    $ret->clmn= $clmn;
  }
  else {
    // titulky
    foreach ($tits as $idw) {
      list($id)= explode(':',$idw);
      $ths.= "<th>$id</th>";
    }
    foreach ($clmn as $i=>$c) {
      $tab.= "<tr>";
      foreach ($flds as $f) {
        if ( $f=='id_osoba' || $f=='^id_osoba' )
          $tab.= "<td style='text-align:right'>".tisk2_ukaz_osobu($c[$f])."</td>";
        elseif ( $f=='^id_rodina' )
          $tab.= "<td style='text-align:right'>".tisk2_ukaz_rodinu($c['^id_rodina'])."</td>";
        elseif ( $f=='^id_pobyt' )
          $tab.= "<td style='text-align:right'>".tisk2_ukaz_pobyt($c['^id_pobyt'])."</td>";
        else {
//                                 debug($c,$f); return $ret;
          $tab.= "<td style='text-align:left'>{$c[$f]}</td>";
        }
      }
      $tab.= "</tr>";
      $n++;
    }
    $ret->html= "Seznam má $n řádků<br><br><div class='stat'><table class='stat'><tr>$ths</tr>$tab</table></div>";
  }
  return $ret;
}
# obsluha různých forem výpisů karet AKCE
# ---------------------------------------------------------------------------------------- sta2_excel
# ASK
# generování statistické sestavy do excelu
function sta2_excel($org,$title,$par,$tab=null) {
  // získání dat
  if ( !$tab ) {
    $tab= sta2_sestava($org,$title,$par,true);
    $title= $par->title ?: $title;
  }
  // vlastní export do Excelu
  return sta2_excel_export($title,$tab);
}
# ---------------------------------------------------------------------------==> . sta2_excel_export
# local
# generování tabulky do excelu
function sta2_excel_export($title,$tab) {  //trace();
//                                         debug($tab,"sta2_excel_export($title,tab)");
  global $xA, $xn;
  $result= (object)array('_error'=>0);
  $html= '';
  $title= str_replace('&nbsp;',' ',$title);
  $subtitle= "ke dni ".date("j. n. Y");
  $name= cz2ascii("vypis_").date("Ymd_Hi");
  $xls= <<<__XLS
    |open $name
    |sheet vypis;;L;page
    |A1 $title ::bold size=14 |A2 $subtitle ::bold size=12
__XLS;
  // titulky a sběr formátů
  $fmt= $sum= array();
  $n= 4;
  $lc= 0;
  $clmns= $del= '';
  $xA= array();                                 // překladová tabulka: název sloupce => písmeno
  if ( $tab->flds ) foreach ($tab->flds as $f) {
    $A= Excel5_n2col($lc);
    $xA[$f]= $A;
    $lc++;
  }
  $lc= 0;
  if ( $tab->tits ) foreach ($tab->tits as $idw) {
    $A= Excel5_n2col($lc);
    list($id,$w,$f,$s)= explode(':',$idw);      // název sloupce : šířka : formát : suma
    if ( $f ) $fmt[$A]= $f;
    if ( $s ) $sum[$A]= true;
    $xls.= "|$A$n $id";
    if ( $w ) {
      $clmns.= "$del$A=$w";
      $del= ',';
    }
    $lc++;
  }
  if ( $clmns ) $xls.= "\n|columns $clmns ";
  $xls.= "\n|A$n:$A$n bcolor=ffffbb00 wrap border=+h|A$n:$A$n border=t\n";
  $n1= $n= 5;                                   // první řádek dat (pro sumy)
  // datové řádky
  if ( $tab->clmn ) foreach ($tab->clmn as $i=>$c) {
    $xls.= "\n";
    $lc= 0;
//     foreach ($c as $id=>$val) { -- míchalo sloupce
    foreach ($tab->flds as $id) {
      $val= $c[$id];
      $A= Excel5_n2col($lc);
      $format= '';
      if (isset($tab->expr[$i][$id]) ) {
        // buňka obsahuje vzorec
        $val= $tab->expr[$i][$id];
        $format.= ' bcolor=ffdddddd';
        $xn= $n;
        $val= preg_replace_callback("/\[([^,]*),([^\]]*)\]/","sta2_excel_subst",$val);
      }
      else {
        // buňka obsahuje hodnotu
        $val= strtr($val,"\n\r","  ");
        if ( isset($fmt[$A]) ) {
          switch ($fmt[$A]) {
          // aplikace formátů
          case 'd': $val= sql2xls($val); $format.= ' right date'; break;
          }
        }
      }
      $format= $format ? "::$format" : '';
      $xls.= "|$A$n $val $format";
      $lc++;
    }
    $n++;
  }
  $n--;
  $xls.= "\n|A$n1:$A$n border=+h|A$n1:$A$n border=t";
  // sumy sloupců
  if ( count($sum) ) {
    $xls.= "\n";
    $nn= $n;
    $ns= $n+2;
    foreach ($sum as $A=>$x) {
      $xls.= "|$A$ns =SUM($A$n1:$A$nn) :: bcolor=ffdddddd";
    }
  }
  // konec
  $xls.= <<<__XLS
    \n|close
__XLS;
  // výstup
//   $inf= Excel2007($xls,1);
  $inf= Excel5($xls,1);
  if ( $inf ) {
    $html= " se nepodařilo vygenerovat - viz začátek chybové hlášky";
    fce_error($inf);
  }
  else {
    $html= " Výpis byl vygenerován ve formátu <a href='docs/$name.xls' target='xlsx'>Excel</a>.";
  }
  $result->html= $html;
  return $result;
}
# ---------------------------------------------------- sta2_excel_subst
function sta2_excel_subst($matches) { trace();
  global $xA, $xn;
//                                                 debug($xA);
//                                                 debug($matches);
  if ( !isset($xA[$matches[1]]) ) fce_error("sta2_excel_subst: chybný název sloupce '{$matches[1]}'");
  $A= $xA[$matches[1]];
  $n= $xn+$matches[2];
  return "$A$n";
}
/** =========================================================================================> ELIM2 **/
# --------------------------------------------------------------------------------- elim2_split_keys
# ASK
# rozdělí klíče v řetězci pro elim_rodiny na dvě půlky
function elim2_split_keys($keys) {
  $ret= (object)array('c1'=>'','c2'=>'');
  $k1= $k2= array();
  foreach (explode(';',$keys) as $cs) {
    $cs= explode(',',$cs);
    $c0= array_shift($cs);
//                                                         debug($cs,$c0);
    if ( !in_array($c0,$k2) ) {
      $k1[]= $c0;
    }
    foreach ($cs as $c) {
      if ( !in_array($c,$k1) && !in_array($c,$k2) ) {
        $k2[]= $c;
      }
    }
  }
  $ret->c1= implode(',',$k1);
  $ret->c2= implode(',',$k2);
//                                                         debug($ret);
  return $ret;
}
# ------------------------------------------------------------------------------------- elim2_differ
# do _track potvrdí, že $id_orig,$id_copy jsou různé osoby nebo rodiny
function elim2_differ($id_orig,$id_copy,$table) { trace();
  global $USER;
  user_test();
  $ret= (object)array('err'=>'');
  $now= date("Y-m-d H:i:s");
  // zápis o neztotožnění osob/rodin do _track jako op=d (duplicita)
  $user= $USER->abbr;
  $ok= query("INSERT INTO _track (kdy,kdo,kde,klic,fld,op,old,val)
         VALUES ('$now','$user','$table',$id_orig,'','r','různé od',$id_copy)");    // r=různost
  $ok= query("INSERT INTO _track (kdy,kdo,kde,klic,fld,op,old,val)
         VALUES ('$now','$user','$table',$id_copy,'','r','různé od',$id_orig)");    // r=různost
end:
  return $ret;
}
# ---------------------------------------------------------------------------------==> . elim2_osoba
# zamění všechny výskyty kopie za originál v TVORI, SPOLU, DAR, PLATBA, MAIL
# a kopii poznačí jako smazanou
function elim2_osoba($id_orig,$id_copy) { //trace();
  global $USER;
  user_test();
  $ret= (object)array('err'=>'');
  $now= date("Y-m-d H:i:s");
  // tvori
  $tvori= select("GROUP_CONCAT(id_tvori)","tvori", "id_osoba=$id_copy");
  query("UPDATE tvori  SET id_osoba=$id_orig WHERE id_osoba=$id_copy");
  // spolu
  $spolu= select("GROUP_CONCAT(id_spolu)","spolu","id_osoba=$id_copy");
  query("UPDATE spolu  SET id_osoba=$id_orig WHERE id_osoba=$id_copy");
  // dar
  $dar=   select("GROUP_CONCAT(id_dar)",  "dar",  "id_osoba=$id_copy");
  query("UPDATE dar    SET id_osoba=$id_orig WHERE id_osoba=$id_copy");
  // platba
  $platba= select("GROUP_CONCAT(id_platba)","platba","id_osoba=$id_copy");
  query("UPDATE platba SET id_osoba=$id_orig WHERE id_osoba=$id_copy");
  // mail
  $mail= select("GROUP_CONCAT(id_mail)","mail","id_clen=$id_copy");
  query("UPDATE mail SET id_clen=$id_orig WHERE id_clen=$id_copy");
  // smazání kopie
  query("UPDATE osoba SET deleted='D osoba=$id_orig' WHERE id_osoba=$id_copy");
  // opravy v originálu
  $access_orig= select("access","osoba","id_osoba=$id_orig");
  $access_copy= select("access","osoba","id_osoba=$id_copy");
  $access= $access_orig | $access_copy;
  query("UPDATE osoba SET access=$access WHERE id_osoba=$id_orig");
  // zápis o ztotožnění osob do _track jako op=d (duplicita)
  $user= $USER->abbr;
  $info= "access:$access_orig;tvori:$tvori;spolu:$spolu;dar:$dar;platba:$platba;mail:$mail";
  query("INSERT INTO _track (kdy,kdo,kde,klic,fld,op,old,val)
         VALUES ('$now','$user','osoba',$id_orig,'osoba','d','$info',$id_copy)");    // d=duplicita
  // zápis o smazání kopie do _track jako op=x (eXtract)
  query("INSERT INTO _track (kdy,kdo,kde,klic,fld,op,old,val)
         VALUES ('$now','$user','osoba',$id_copy,'','x','smazaná kopie',$id_orig)");    // x=smazání
end:
  return $ret;
}
# ----------------------------------------------------------------------------------==> . elim2_clen
# zamění všechny výskyty kopie za originál v TVORI, SPOLU, DAR, PLATBA, MAIL a kopii smaže
function elim2_clen($id_rodina,$id_orig,$id_copy) { trace();
  global $USER;
  user_test();
  $ret= (object)array('err'=>'');
  $now= date("Y-m-d H:i:s");
  // tvori - vymazat => do _track napsat id_rodina.role
  $tvori= select1("CONCAT(id_rodina,'.',role)","tvori", "id_rodina=$id_rodina AND id_osoba=$id_copy");
  query("DELETE FROM tvori WHERE id_rodina=$id_rodina AND id_osoba=$id_copy");
  // spolu
  $spolu= select("GROUP_CONCAT(id_spolu)","spolu","id_osoba=$id_copy");
  query("UPDATE spolu  SET id_osoba=$id_orig WHERE id_osoba=$id_copy");
  // dar
  $dar=   select("GROUP_CONCAT(id_dar)",  "dar",  "id_osoba=$id_copy");
  query("UPDATE dar    SET id_osoba=$id_orig WHERE id_osoba=$id_copy");
  // platba
  $platba= select("GROUP_CONCAT(id_platba)","platba","id_osoba=$id_copy");
  query("UPDATE platba SET id_osoba=$id_orig WHERE id_osoba=$id_copy");
  // mail
  $mail= select("GROUP_CONCAT(id_mail)","mail","id_clen=$id_copy");
  query("UPDATE mail SET id_clen=$id_orig WHERE id_clen=$id_copy");
  // smazání kopie
  query("UPDATE osoba SET deleted='D osoba=$id_orig' WHERE id_osoba=$id_copy");
  // opravy v originálu
  $access_orig= select("access","osoba","id_osoba=$id_orig");
  $access_copy= select("access","osoba","id_osoba=$id_copy");
  $access= $access_orig | $access_copy;
  query("UPDATE osoba SET access=$access WHERE id_osoba=$id_orig");
  // zápis o ztotožnění osob do _track jako op=d (duplicita)
  $info= "access:$access_orig;xtvori:$tvori;spolu:$spolu;dar:$dar;platba:$platba;mail:$mail";
  $user= $USER->abbr;
  query("INSERT INTO _track (kdy,kdo,kde,klic,fld,op,old,val)
         VALUES ('$now','$user','osoba',$id_orig,'clen','d','$info',$id_copy)");
  // zápis o smazání kopie do _track jako op=x (eXtract)
  query("INSERT INTO _track (kdy,kdo,kde,klic,fld,op,old,val)
         VALUES ('$now','$user','osoba',$id_copy,'','x','smazaná kopie',$id_orig)");
end:
  return $ret;
}
# --------------------------------------------------------------------------------==> . elim2_rodina
# ASK
# zamění všechny výskyty kopie za originál v POBYT, TVORI, DAR, PLATBA a kopii smaže
function elim2_rodina($id_orig,$id_copy) {
  global $USER;
  user_test();
  $ret= (object)array('err'=>'');
  if ( $id_orig!=$id_copy ) {
    $now= date("Y-m-d H:i:s");
    // pobyt
    $pobyt= select("GROUP_CONCAT(id_pobyt)","pobyt", "i0_rodina=$id_copy");
    query("UPDATE pobyt  SET i0_rodina=$id_orig WHERE i0_rodina=$id_copy");
    // tvori
    $tvori= select("GROUP_CONCAT(id_tvori)","tvori", "id_rodina=$id_copy");
    query("UPDATE tvori  SET id_rodina=$id_orig WHERE id_rodina=$id_copy");
    // dar
    $dar=   select("GROUP_CONCAT(id_dar)",  "dar",  "id_rodina=$id_copy");
    query("UPDATE dar    SET id_rodina=$id_orig WHERE id_rodina=$id_copy");
    // platba
    $platba= select("GROUP_CONCAT(id_platba)","platba","id_rodina=$id_copy");
    query("UPDATE platba SET id_rodina=$id_orig WHERE id_rodina=$id_copy");
    // smazání kopie
    query("UPDATE rodina SET deleted='D rodina=$id_orig' WHERE id_rodina=$id_copy");
    // opravy v originálu
    $access_orig= select("access","rodina","id_rodina=$id_orig");
    $access_copy= select("access","rodina","id_rodina=$id_copy");
    $access= $access_orig | $access_copy;
    query("UPDATE rodina SET access=$access WHERE id_rodina=$id_orig");
    // zápis o ztotožnění rodin do _track jako op=d (duplicita)
    $info= "access:$access_orig;pobyt:$pobyt;tvori:$tvori;dar:$dar;platba:$platba";
    $user= $USER->abbr;
    query("INSERT INTO _track (kdy,kdo,kde,klic,fld,op,old,val)
           VALUES ('$now','$user','rodina',$id_orig,'','d','$info',$id_copy)");
    // zápis o smazání kopie do _track jako op=x (eXtract)
    query("INSERT INTO _track (kdy,kdo,kde,klic,fld,op,old,val)
           VALUES ('$now','$user','rodina',$id_copy,'rodina','x','smazaná kopie',$id_orig)");
  }
  // odstranění duplicit v tabulce TVORI
  $qt= mysql_qry("
    SELECT COUNT(*) AS _n,GROUP_CONCAT(id_tvori ORDER BY id_tvori) AS _ids FROM tvori
    WHERE id_rodina=$id_orig GROUP BY id_osoba,role HAVING _n>1");
  while (($t= mysql_fetch_object($qt))) {
    $idts= explode(',',$t->_ids);
    for ($i= 1; $i<count($idts); $i++) {
      query("DELETE FROM tvori WHERE id_tvori={$idts[$i]}");
    }
  }
end:
  return $ret;
}
# ----------------------------------------------------------------------------- elim2_recovery_osoba
# obnoví smazanou osobu se záznamem v _track
function elim2_recovery_osoba($ido) { trace();
  global $USER;
  user_test();
  $deleted= select('deleted','osoba',"id_osoba=$ido");
  if ( $deleted ) {
    // obnovení
    query("UPDATE osoba SET deleted='' WHERE id_osoba=$ido");
    // zápis o obnovení smazaného záznamu op='r' (recovery)
    $now= date("Y-m-d H:i:s");
    query("INSERT INTO _track (kdy,kdo,kde,klic,fld,op,old,val)
           VALUES ('$now','{$USER->abbr}','osoba',$ido,'','r','','$deleted')");
  }
  return $deleted;
}
# ---------------------------------------------------------------------------- elim2_recovery_rodina
# obnoví smazanou rodinu se záznamem v _track
function elim2_recovery_rodina($idr) { trace();
  global $USER;
  user_test();
  $deleted= select('deleted','rodina',"id_rodina=$idr");
  if ( $deleted ) {
    // obnovení
    query("UPDATE rodina SET deleted='' WHERE id_rodina=$idr");
    // zápis o obnovení smazaného záznamu op='r' (recovery)
    $now= date("Y-m-d H:i:s");
    query("INSERT INTO _track (kdy,kdo,kde,klic,fld,op,old,val)
           VALUES ('$now','{$USER->abbr}','rodina',$idr,'','r','','$deleted')");
  }
  return $deleted;
}
# --------------------------------------------------------------------------------- elim2_data_osoba
# načte data OSOBA+TVORI včetně záznamů v _track
# cond může omezit čas barvení změn
function elim2_data_osoba($ido,$cond='') {  //trace();
  $ret= (object)array();
  // načtení změn
  $chng_kdy= $chng_kdo= $chng_val= array();
  $max_kdy= '';
  $AND_kdy= $cond ? "AND $cond" : '';
  $zs= mysql_qry("
    SELECT fld,kdo,kdy,val,op
    FROM _track
    WHERE kde='osoba' AND klic=$ido $AND_kdy
  ");
  while (($z= mysql_fetch_object($zs))) {
    $fld= $z->fld;
    $kdy= $z->kdy;
    $kdo= $z->kdo;
    $op=  $z->op;
    $val= $z->val;
    $max_kdy= max($max_kdy,substr($kdy,0,10));
    if ( !isset($chng_kdy[$fld]) || isset($chng_kdy[$fld]) && strcmp($chng_kdy[$fld],$kdy)<0 ) {
      $chng_kdy[$fld]= $kdy;
      $chng_kdo[$fld]= "$kdo/$op: ".sql_date1($kdy);
      $chng_val[$fld]= $val;
    }
  }
  $ret->last_chng= $max_kdy;
  $ret->chng= $chng_kdo;
  // načtení hodnot
  $os= mysql_qry("
    SELECT MAX(CONCAT(datum_od,':',a.nazev)) AS _last,
      prijmeni,jmeno,sex,narozeni,rc_xxxx,psc,obec,ulice,email,telefon,o.note
    FROM osoba AS o
    LEFT JOIN spolu AS s USING(id_osoba)
    LEFT JOIN pobyt AS p USING(id_pobyt)
    LEFT JOIN akce AS a ON p.id_akce=a.id_duakce
    WHERE id_osoba=$ido GROUP BY id_osoba
  ");
  $o= mysql_fetch_object($os);
  foreach($o as $fld=>$val) {
    if ( $chng_kdy[$fld] && $chng_val[$fld]!=$val ) {
      $ret->diff[$fld]= $chng_val[$fld];
      $ret->chng[$fld]= "!{$ret->chng[$fld]}: {$chng_val[$fld]}";
    }
  }
  $ret->last_akce= $o->_last;
  // zjištění kmenové rodiny
  $kmen= ''; $idk= 0;
  $rs= mysql_qry("
    SELECT id_rodina,role,nazev
    FROM osoba AS o
    LEFT JOIN tvori AS t USING(id_osoba)
    LEFT JOIN rodina AS r USING(id_rodina)
    WHERE id_osoba=$ido
  ");
  while (($r= mysql_fetch_object($rs))) {
    if ( !$kmen || $r->role=='a' || $r->role=='b' ) {
      $kmen= $r->nazev;
      $idk= $r->id_rodina;
    }
  }
  $ret->kmen= $kmen;
  $ret->id_kmen= $idk;
//                                                         debug($ret,"elim_data_osoba");
  return $ret;
}
# -------------------------------------------------------------------------------- elim2_data_rodina
# načte data RODINA včetně záznamů v _track
# cond může omezit čas barvení změn
function elim2_data_rodina($idr,$cond='') {  //trace();
  $ret= (object)array();
  // načtení změn
  $chng_kdy= $chng_kdo= $chng_val= array();
  $max_kdy= '';
  $AND_kdy= $cond ? "AND $cond" : '';
  $zs= mysql_qry("
    SELECT fld,kdo,kdy,val,op
    FROM _track
    WHERE kde='rodina' AND klic=$idr $AND_kdy
  ");
  while (($z= mysql_fetch_object($zs))) {
    $fld= $z->fld;
    $kdy= $z->kdy;
    $kdo= $z->kdo;
    $op=  $z->op;
    $val= $z->val;
    $max_kdy= max($max_kdy,substr($kdy,0,10));
    if ( !isset($chng_kdy[$fld]) || isset($chng_kdy[$fld]) && strcmp($chng_kdy[$fld],$kdy)<0 ) {
      $chng_kdy[$fld]= $kdy;
      $chng_kdo[$fld]= "$kdo/$op: ".sql_date1($kdy);
      $chng_val[$fld]= $val;
    }
  }
  $ret->last_chng= sql_date1($max_kdy);
  $ret->chng= $chng_kdo;
  // načtení hodnot
  $os= mysql_qry("
    SELECT r.*, MAX(CONCAT(datum_od,': ',a.nazev)) AS _last
    FROM rodina AS r
    LEFT JOIN tvori AS t USING (id_rodina)
    LEFT JOIN spolu AS s USING (id_osoba)
    LEFT JOIN pobyt AS p USING (id_pobyt)
    LEFT JOIN akce AS a ON id_akce=id_duakce
    WHERE id_rodina=$idr
    GROUP BY id_rodina
  ");
  $o= mysql_fetch_object($os);
  foreach($o as $fld=>$val) {
    $ret->$fld= $val;
    if ( $chng_kdy[$fld] && $chng_val[$fld]!=$val ) {
      $ret->diff[$fld]= $chng_val[$fld];
      $ret->chng[$fld]= "!{$ret->chng[$fld]}: {$chng_val[$fld]}";
    }
  }
  $ret->datsvatba= sql_date1($ret->datsvatba);                  // svatba d.m.r
  $ret->last_akce= sql_date1(substr($o->_last,0,10)).substr($o->_last,10);
//                                                         debug($ret,"elim_data_rodina");
  return $ret;
}
/** =========================================================================================> MAIL2 **/
# =======================================================================================> . mailist
# ---------------------------------------------------------------------------------- mail2_lst_using
# vrátí informaci o použití mailistu
function mail2_lst_using($id_mailist) {
  $dopisy= $poslane= $neposlane= $err= 0;
  $rs= mysql_qry("
    SELECT COUNT(DISTINCT id_dopis) AS _dopisy,SUM(IF(m.stav=4,1,0)) AS _poslane,
      IF(sexpr LIKE '%access%',0,1) AS _ok
    FROM mailist AS l
    LEFT JOIN dopis AS d USING (id_mailist)
    LEFT JOIN mail AS m USING (id_dopis)
    WHERE id_mailist=$id_mailist
    GROUP BY id_dopis");
  while ($rs && ($s= mysql_fetch_object($rs))) {
    $dopisy= $s->_dopisy;
    $poslane+= $s->_poslane ? 1 : 0;
    $neposlane+= $s->_poslane ? 0 : 1;
    $err+= $s->_ok;
  }
  $html= "Použití: v $dopisy dopisech, z toho <br>$poslane rozeslané a $neposlane nerozeslané";
  $html.= $err ? "<br><br><span style='background-color:yellow'>POZOR - nutno znovu uložit</span>" : '';
  return $html;
}
# ------------------------------------------------------------------------------------ mail2_mailist
# vrátí daný mailist ve tvaru pro selects
function mail2_mailist($access) {
  $sel= '';
  $mr= mysql_qry("SELECT id_mailist,ucel,access FROM mailist WHERE access=$access");
  while ($mr && ($m= mysql_fetch_object($mr))) {
    $a= $m->access;
    $css= $a==1 ? 'ezer_ys' : ($a==2 ? 'ezer_fa' : ($a==3 ? 'ezer_db' : ''));
    $sel.= ",{$m->ucel}:{$m->id_mailist}:$css";
  }
  return $sel ? substr($sel,1) : '';
}
# --------------------------------------------------------------------------------- mail2_lst_delete
# ASK
# zjisté možnost smazání mailistu (to_delete=0) tzn. že na něj není vázán žádný dopis
# a pro to_delete=1 jej smaže
function mail2_lst_delete($id_mailist,$to_delete=0) {
  $ret= (object)array('ok'=>0,'msg'=>'');
  if ( !$to_delete ) {
    if ( $id_mailist<=3 ) {
      $ret->msg= "testovací mail-listy nelze smazat";
    }
    else {
      $n= select('COUNT(*)','mailist JOIN dopis USING (id_mailist)',"id_mailist=$id_mailist");
      if ( $n )
        $ret->msg= "nelze smazat, je použit v $n dopisech
          <br>(nejprve je třeba smazat všechny dopisy)";
      else
        $ret->ok= 1;
    }
  }
  else {
    $ret->ok= query("DELETE FROM mailist WHERE id_mailist=$id_mailist");
  }
  return $ret;
}
# --------------------------------------------------------------------------------- mail2_lst_access
# vrátí údaje daného maillistu (ZRUŠENO: s provedenou substitucí podle access uživatele)
function mail2_lst_access($id_mailist) {  trace();
  global $USER;                                         // debug($USER);
  $ml= select_object('*','mailist',"id_mailist=$id_mailist");
//   if ( !strpos($ml->sexpr,'[HAVING_ACCESS]') ) {
//     fce_warning("dotaz zatím není uzpůsoben pro obě databáze - stačí jej znovu uložit");
//     $ml->warning= 1;
//     goto end;
//   }
  $ml->sexpr= str_replace('&lt;','<',str_replace('&gt;','>',$ml->sexpr));
//   // doplnění práv uživatele
//   $ml->sexpr= str_replace('[HAVING_ACCESS]',"HAVING o.access&{$USER->access}",$ml->sexpr);
end:
  return $ml;
}
# --------------------------------------------------------------------------- mail2_lst_confirm_spec
# spočítá maily podle daného maillistu
function mail2_lst_confirm_spec($id_mailist,$id_dopis) {  trace();
  $ret= (object)array('specialni'=>0, 'prepsat'=>'', 'pocet'=>'');
  // speciální?
  list($ret->specialni,$qry)= select('specialni,sexpr','mailist',"id_mailist=$id_mailist");
  if ( !$ret->specialni ) goto end;
  // jsou už vygenerované maily
  $ret->prepsat= select('COUNT(*)','mail',"id_dopis=$id_dopis")
    ? "Opravdu přepsat předchozí maily?" : '';
  // počet nově vygenerovaných
  $res= mysql_qry($qry);
  $ret->pocet= "Opravdu vygenerovat maily na ".mysql_num_rows($res)." adres?";
end:
  return $ret;
}
# ----------------------------------------------------------------------------- mail2_lst_posli_spec
# vygeneruje sadu mailů podle daného maillistu s nastaveným specialni a parms
function mail2_lst_posli_spec($id_dopis) {  trace();
  $ret= (object)array('msg'=>'');
  $id_mailist= select('id_mailist','dopis',"id_dopis=$id_dopis");
  $ml= mail2_lst_access($id_mailist);
  $parms= json_decode($ml->parms);
  switch ($parms->specialni) {
  case 'potvrzeni':
    // smaž starý seznam
    mysql_qry("DELETE FROM mail WHERE id_dopis=$id_dopis");
    $num= 0;
    $nomail= array();
    $rok= date('Y')+$parms->rok;
    // projdi všechny relevantní dárce podle dotazu z maillistu
    $os= mysql_qry($ml->sexpr);
    while ($os && ($o= mysql_fetch_object($os))) {
      $email= $o->email;
      if ( $email ) {
        // vygeneruj PDF s potvrzením do $x->path
        $x= mail2_mai_potvr("Pf",$o,$rok);
        // vlož mail
        mysql_qry(
          "INSERT mail (id_davka,znacka,stav,id_dopis,id_clen,email,priloha)
             VALUE (1,'@',0,$id_dopis,$o->id_osoba,'$email','{$x->fname}')");
        $num+= mysql_affected_rows();
      }
      else {
        $nomail[]= "{$o->jmeno} {$o->prijmeni}";
      }
    }
    // oprav počet v DOPIS
    mysql_qry("UPDATE dopis SET pocet=$num WHERE id_dopis=$id_dopis");
    // informační zpráva
    $ret->msg= "Bylo vygenerováno $num mailů";
    if ( count($nomail) ) $ret->msg.= ", emailovou adresu nemají:<hr>".implode(', ',$nomail);
    break;
  default:
    fce_error("není implemntováno");
  }
//                                                         debug($ret,"mail2_lst_posli_spec end");
  return $ret;
}
# ----------------------------------------------------------------------------------- mail2_lst_read
# převod parm do objektu
function mail2_lst_read($parm) { //trace();
  global $json;
  $obj= $json->decode($parm);
  $obj= isset($obj->ano_akce) ? $obj : 0;
//                                                 debug($obj,"mail2_lst_read($parm)");
  return $obj;
}
# --------------------------------------------------------------------------------------- mail2_mapa
# ASK
# získání seznamu souřadnic bydlišť adresátů mailistu
function mail2_mapa($id_mailist) {  trace();
  $psc= $obec= array();
  // dotaz
  $gq= select("sexpr","mailist","id_mailist=$id_mailist");
//                                         display($gq);
  $gq= str_replace('&gt;','>',$gq);
  $gq= str_replace('&lt;','<',$gq);
  $gr= @mysql_qry($gq);
  if ( !$gr ) {
    $html= mysql_error()."<hr>".nl2br($gq);
    goto end;
  }
  else while ( $gr && ($g= mysql_fetch_object($gr)) ) {
    // najdeme použitá PSČ
    $p= $g->_psc;
    list($prijmeni,$jmeno)= explode(' ',$g->_name);
    $psc[$p].= "$prijmeni ";
    $obec[$p]= $obec[$p] ?: $g->_obec;
  }
//                                         debug($psc);
end:
  return mapa2_psc($psc,$obec); // vrací (object)array('mark'=>$marks,'n'=>$n,'err'=>$err);
}
# ------------------------------------------------------------------------------------ mail2_lst_try
# mode=0 -- spustit a ukázat dotaz a také výsledek
# mode=1 -- zobrazit argument jako html
function mail2_lst_try($gq,$mode=0) { trace();
//   global $USER;                                         // debug($USER);
//   $access= $USER->access;
  $html= $del= '';
  if ( !$gq ) {
    $html= "mail-list nebyl uložen";
    goto end;
  }
  switch ($mode) {
  case 0:
    $n= $nw= $nm= $nx= 0;
    $gq= str_replace('&gt;','>',$gq);
    $gq= str_replace('&lt;','<',$gq);
    // ZRUŠENO: doplnění práv uživatele
    // $gq= str_replace('[HAVING_ACCESS]',"HAVING o.access&$access",$gq);
    $gr= @mysql_qry($gq);
    if ( !$gr ) {
      $html= mysql_error()."<hr>".nl2br($gq);
      goto end;
    }
    else while ( $gr && ($g= mysql_fetch_object($gr)) ) {
      $n++;
      $name= str_replace(' ','&nbsp;',$g->_name);
      if ( !$g->_email ) {
        $nw++;
        $name= "<span style='color:darkred'>$name</span>";
      }
      if ( $g->nomail ) {
        $nm++;
        $name= "<span style='background-color:yellow'>$name</span>";
      }
      if ( $g->_email[0]=='*' ) {
        // vyřazený mail
        $nx++;
        $name= "<strike><b>$name</b></strike>";
      }
      $html.= "$del$name";
      $del= ', ';
    }
    $warn= $nw+$nm+$nx ? " (" : '';
    $warn.= $nw ? "$nw <span style='color:darkred'>nemá email</span> ani rodinný" : '';
    $warn.= $nw && $nm ? ", " : '';
    $warn.= $nm ? "$nm <span style='background-color:yellow'>nechce hromadné</span> informace
      - budou vyňati z mail-listu" : '';
    $warn.= ($nw||$nm) && $nx ? ", " : '';
    $warn.= $nx ? "$nx má <strike>zneplatněný email</strike>" : '';
    $warn.= $nw+$nm+$nx ? ")" : '';
    $html= "<b>Nalezeno $n adresátů$warn:</b><br>$html";
    break;
  case 1:
    $html= nl2br($gq);
    break;
  }
end:
  return $html;
}
# ========================================================================================> . emaily
# jednotlivé maily posílané v sadách příložitostně skupinám
#   DOPIS(id_dopis=key,id_davka=1,druh='@',nazev=předmět,datum=datum,obsah=obsah,komu=komu(číselník),
#         nw=min(MAIL.stav,nh=max(MAIL.stav)})
#   MAIL(id_mail=key,id_davka=1,id_dopis=DOPIS.id_dopis,znacka='@',id_clen=clen,email=adresa,
#         stav={0:nový,3:rozesílaný,4:ok,5:chyba})
# formát zápisu dotazu v číselníku viz fce mail2_mai_qry
# ---------------------------------------------------------------------------------- mail2_mai_potvr
# vygeneruje PDF s daňovým potvrzením s výsledkem
# ret->fname - jméno vygenerovaného PDF souboru
# ret->href  - odkaz na soubor
# ret->fpath - úplná lokální cesta k souboru
# ret->log   - log
function mail2_mai_potvr($druh,$o,$rok) {  trace();
  $ret= (object)array('msg'=>'');
  // report
  $d= select("*","dopis","typ='$druh'");
  $vzor= $d->obsah;
  $sablona= $d->sablona;
  $texty= array();
  $parss= array();
  // výpočet proměnných použitých v dopisu
  $is_vars= preg_match_all("/[\{]([^}]+)[}]/",$vzor,$list);
  $vars= array_merge($list[1],array('vyrizeno'));
//                                                         debug($vars,"vars");
  $dary= json_decode($o->pars);
  $data= $dary->data;
  $castka= number_format($o->castka, 0, '.', ' ');
  $id_osoba= $o->id_osoba;
  $prijmeni= $o->prijmeni;
  $jmeno= $o->jmeno;
  $sex= $o->sex;
  $ulice= $o->ulice;
  $psc= $o->psc;
  $obec= $o->obec;
  $osloveni= $sex==1 ? "pan" : ($sex==2 ? "paní" : "");
  $Osloveni= $sex==1 ? "Pan" : ($sex==2 ? "Paní" : "");
  $adr= "$osloveni,$prijmeni,$jmeno,$sex,$ulice,$psc,$obec";
  $html= "<table>";
  $html.= "<tr><td>$id</td><td>$castka</td><td>$adr</td><td>$data</td></tr>";
  // definice parametrů pro potvrzující dopis
  $parss[$n]= (object)array();
  $parss[$n]->dar_datum= $data;
  $parss[$n]->dar_castka= str_replace(' ','&nbsp;',$castka);
  $parss[$n]->darce= "$osloveni <b>$jmeno $prijmeni</b>";
  $parss[$n]->darce_a= $sex==2 ? "a" : "";
  $parss[$n]->vyrizeno= date('j. n. Y');
  // substituce v 'text' a 'odeslano'
  $text= $vzor;
  $odeslano= select('obsah','dopis_cast',"name='odeslano'");
  if ( $is_vars ) foreach ($vars as $var ) {
    $text= str_replace('{'.$var.'}',$parss[$n]->$var,$text);
    $odeslano= str_replace('{'.$var.'}',$parss[$n]->$var,$odeslano);
  }
  // úprava lámání textu kolem jednopísmenných předložek a přilepení Kč k částce
  $text= preg_replace(array('/ ([v|k|z|s|a|o|u|i]) /u','/ Kč/u'),array(' \1&nbsp;','&nbsp;Kč'),$text);
  $texty[$n]= (object)array();
  $texty[$n]->adresa= "<b>$Osloveni<br>$jmeno $prijmeni<br>$ulice<br>$psc $obec</b>";
  $texty[$n]->odeslano= $odeslano;
  $texty[$n]->text= $text;
  $n++;
  $html.= "<hr>$text";
  $html.= "</table>";
  // předání k tisku
//                                                 debug($parss);
//                                                 display($html);
  global $ezer_path_docs, $ezer_root;
  $ret->fname= "potvrzeni_{$rok}_$id_osoba.pdf";
  $ret->fpath= "$ezer_path_docs/$ezer_root/{$ret->fname}";
  $dlouhe= tc_dopisy($texty,$ret->fpath,'','_user',$listu);
  $ret->href= "<a href='docs/$ezer_root/{$ret->fname}' target='pdf'>{$ret->fname}</a>";
//   $html.= " Bylo vygenerováno $listu potvrzení do $href.";
  // konec
  $ret->log= $html;
  return $ret;
}
# ----------------------------------------------------------------------------------- mail2_mai_text
# přečtení mailu
function mail2_mai_text($id_dopis) {  //trace();
  $d= null;
  try {
    $qry= "SELECT * FROM dopis WHERE id_dopis=$id_dopis ";
    $res= mysql_qry($qry,1,null,1);
    $d= mysql_fetch_object($res);
  }
  catch (Exception $e) { display($e); fce_error("mail2_mai_text: průběžný dopis No.'$id_dopis' nebyl nalezen"); }
  $html.= "<b>{$d->nazev}</b><br/><hr/>{$d->obsah}";
  // příloha?
  if ( $d->prilohy ) {
    foreach ( explode(',',$d->prilohy) as $priloha ) {
      $priloha= $priloha;
      $html.= "<hr/><b>Příloha:</b> $priloha";
      $typ= strtolower(substr($priloha,-4));
      if ( $typ=='.jpg' || $typ=='.gif' || $typ=='.png' ) {
        $html.= "<img src='docs/$priloha' />";
      }
    }
  }
//                                                         debug($d,"mail2_mai_text($id_dopis)");
  return $html;
}
# -------------------------------------------------------------------------------- mail2_mai_prazdny
# zjistí zda neexistuje starý seznam adresátů
function mail2_mai_prazdny($id_dopis) {  trace();
  $result= array('_error'=>0, '_prazdny'=> 1);
  // ověř prázdnost MAIL
  $qry= "SELECT * FROM mail WHERE id_dopis=$id_dopis";
  $res= mysql_qry($qry);
  if ( mysql_num_rows($res)>0 ) {
    $result['_html']= "Rozesílací seznam pro tento mail již existuje, stiskni OK pokud má být přepsán novým";
    $result['_prazdny']= 0;
  }
  return $result;
}
# ------------------------------------------------------------------------------------ mail2_mai_qry
# sestaví SQL dotaz podle položky DOPIS.komu
# formát zápisu dotazu v číselníku:  A[|D[|cond]]
#   kde A je seznam aktivit oddělený čárkami
#   a D=1 pokud mají být začleněni pouze letošní a loňští dárci
#   a cond je obecná podmínka na položky tabulky CLEN
function mail2_mai_qry($komu) {  trace();
  list($aktivity,$is_dary,$cond)= explode('|',$komu);
  $and= $aktivity=='*' ? '' : "AND FIND_IN_SET(aktivita,'$aktivity')";
  if ( $cond ) $and.= " AND $cond";
  $letos= date('Y'); $loni= $letos-1;
  $qry= $is_dary
    ? "SELECT id_clen, email,
         BIT_OR(IF((YEAR(datum) BETWEEN $loni AND $letos) AND LEFT(dar.deleted,1)!='D'
           AND castka>0 AND akce='G',1,0)) AS is_darce
       FROM clen LEFT JOIN dar USING (id_clen)
       WHERE LEFT(clen.deleted,1)!='D' AND umrti=0 AND aktivita!=9 AND email!='' $and
       GROUP BY id_clen HAVING is_darce=1"
    : "SELECT id_clen, email FROM clen
       WHERE left(deleted,1)!='D' AND umrti=0 AND email!='' $and";
  return $qry;
}
# ---------------------------------------------------------------------------------- mail2_mai_omitt
# v tabulce MAIL(id_dopis=$dopis) označí jako neposlatelné emailu z MAIL($id_dopis=$vynech)
# to je funkce určená k zamezení duplicit
function mail2_mai_omitt($id_dopis,$ids_vynech) {  trace();
  $msg= "Z mailů podle dopisu $id_dopis budou vynechány adresy z mailů podle dopisu $ids_vynech";
  // seznam vynechaných adres
  $vynech= array();
  $qv= "SELECT email FROM mail WHERE id_dopis IN ($ids_vynech) ";
  $rv= mysql_qry($qv);
  while ( $rv && ($v= mysql_fetch_object($rv)) ) {
    foreach(explode(',',str_replace(';','',str_replace(' ','',$v->email))) as $em) {
      $vynech[]= $em;
    }
  }
//                                                         debug($vynech,"vynechané adresy");
  $msg.= "<br>podezřelých je ".count($vynech)." adres";
  // probírka adresátů
  $n= 0;
  $qd= "SELECT id_mail,email FROM mail WHERE id_dopis=$id_dopis ";
  $rd= mysql_qry($qd);
  while ( $rd && ($d= mysql_fetch_object($rd)) ) {
    $emaily= $d->email;
    foreach(explode(',',str_replace(';','',str_replace(' ','',$emaily))) as $em) {
      if ( in_array($em,$vynech) ) {
        $n++;
        $qu= "UPDATE mail SET stav=5,msg='- $ids_vynech' WHERE id_mail={$d->id_mail} ";
        $ru= mysql_qry($qu);
      }
    }
  }
  $msg.= "<br>označeno bylo $n adres";
  return $msg;
}
# --------------------------------------------------------------------------------- mail2_mai_omitt2
# v tabulce MAIL(id_dopis=$dopis) označí jako neposlatelné emaily $vynech (čárkami oddělený seznam)
function mail2_mai_omitt2($id_dopis,$lst_vynech) {  trace();
  // seznam vynechaných adres
  $vynech= explode(',',str_replace(' ','',$lst_vynech));
  $msg= "Z mailů podle dopisu $id_dopis bude vynecháno ".count($vynech)." adres";
//                                                         debug($vynech,"vynechané adresy");
  // probírka adresátů
  $n= 0;
  $qd= "SELECT id_mail,email FROM mail WHERE id_dopis=$id_dopis ";
  $rd= mysql_qry($qd);
  while ( $rd && ($d= mysql_fetch_object($rd)) ) {
    $emaily= $d->email;
    foreach(explode(',',str_replace(';','',str_replace(' ','',$emaily))) as $em) {
//                                         display("'$em'=".(in_array($em,$vynech)?1:0));
      if ( in_array($em,$vynech) ) {
        $n++;
        $qu= "UPDATE mail SET stav=5,msg='viz' WHERE id_mail={$d->id_mail} ";
        $ru= mysql_qry($qu);
      }
    }
  }
  $msg.= "<br>označeno bylo $n adres";
  return $msg;
}
# ---------------------------------------------------------------------------------- mail2_mai_pocet
# zjistí počet adresátů pro rozesílání a sestaví dotaz pro confirm
# $dopis_var určuje zdroj adres
#   'U' - rozeslat účastníkům akce dopis.id_duakce ukazující do akce
#         do seznamu se dostanou pouze účastnící s funkcí:0,1,2,6 (-,VPS,SVPS,hospodář)
#   'U2'- rozeslat účastníkům akce dopis.id_duakce ukazující do akce
#         do seznamu se dostanou pouze organizující účastnící s funkcí:1,2,6 (VPS,SVPS,hospodář)
#   'U3'- rozeslat účastníkům akce dopis.id_duakce ukazující do akce
#         do seznamu se dostanou pouze dlužníci (bez avíza)
#   'Q' - rozeslat na adresy vygenerované dopis.cis_skupina => hodnota
#   'G' - rozeslat podle mailistu
# pokud _cis.data=9999 jde o speciální seznam definovaný funkcí mail2_mai_skupina - ZRUŠENO
# $cond = dodatečná podmínka POUZE pro volání z mail2_mai_stav
function mail2_mai_pocet($id_dopis,$dopis_var,$cond='',$recall=false) {  trace();
  $result= (object)array('_error'=>0, '_count'=> 0, '_cond'=>false);
  $result->_html= 'Rozesílání mailu nemá určené adresáty, stiskni ZRUŠIT';
  $html= '';
  $emaily= $ids= $jmena= $pobyty= array();
  $spatne= $nema= $mimo= $nomail= '';
  $n= $ns= $nt= $nx= $mx= $nm= 0;
  $dels= $deln= $delm= $delnm= '';
  $nazev= '';
  switch ($dopis_var) {
  // --------------------------------------------------- mail-list
  case 'G':
    $html.= "Vybraných adresátů ";
    $id_mailist= select('id_mailist','dopis',"id_dopis=$id_dopis");
//     list($qry,$ucel)= select('sexpr,ucel','mailist',"id_mailist=$id");
    $ml= mail2_lst_access($id_mailist);
    // SQL dotaz z mail-listu obsahuje _email,_nazev,_id
    $res= mysql_qry($ml->sexpr);
    while ( $res && ($d= mysql_fetch_object($res)) ) {
      $n++;
      $nazev= "'{$ml->ucel}'";
      if ( $d->nomail ) {
        // nechce dostávat maily
        $nomail.= "$delnm{$d->_name}"; $delnm= ', '; $nm++;
        continue;
      }
      if ( $d->_email ) {
        // přidej každý mail zvlášť do seznamu
        foreach(preg_split('/\s*[,;]\s*/',trim($d->_email,",; \n\r"),0,PREG_SPLIT_NO_EMPTY) as $adr) {
          // pokud tam ještě není
          if ( $adr && !in_array($adr,$emaily) ) {
            if ( $adr[0]=='*' ) {
              // vyřazený mail
              $mimo.= "$delm{$d->_name}"; $delm= ', '; $mx++;
            }
            else {
              $emaily[]= $adr;
              $ids[]= $d->_id;
              $jmena[]= $d->_name;
            }
          }
        }
      }
      else {
        $nema.= "$deln{$d->_name}"; $deln= ', ';
        $nx++;
      }
    }
    break;
  // --------------------------------------------------- obecný SQL dotaz - skupina
  case 'Q':
    $html.= "Vybraných adresátů ";
    $qryQ= "SELECT _cis.hodnota,_cis.zkratka FROM dopis
           JOIN _cis ON _cis.data=dopis.cis_skupina AND _cis.druh='db_maily_sql'
           WHERE id_dopis=$id_dopis ";
    $resQ= mysql_qry($qryQ);
    if ( $resQ && ($q= mysql_fetch_object($resQ)) ) {
      $qry= $q->hodnota;
      $qry= db_mail_sql_subst($qry);
      $res= mysql_qry($qry);
      while ( $res && ($d= mysql_fetch_object($res)) ) {
        $n++;
        $nazev= "Členů {$q->zkratka}";
        $jm= "{$d->prijmeni} {$d->jmeno}";
        if ( $d->nomail ) {
          // nechce dostávat maily
          $nomail.= "$delnm$jm"; $delnm= ', '; $nm++;
          continue;
        }
        if ( $d->_email ) {
          // přidej každý mail zvlášť do seznamu
          foreach(preg_split('/\s*[,;]\s*/',trim($d->_email,",; \n\r"),0,PREG_SPLIT_NO_EMPTY) as $adr) {
            // pokud tam ještě není
            if ( $adr && !in_array($adr,$emaily) ) {
              if ( $adr[0]=='*' ) {
                // vyřazený mail
                $mimo.= "$delm$jm"; $delm= ', '; $mx++;
              }
              else {
                $emaily[]= $adr;
                $ids[]= $d->_id;
                $jmena[]= $jm;
              }
            }
          }
        }
        else {
          $nema.= "$deln{$d->prijmeni} {$d->jmeno}"; $deln= ', ';
          $nx++;
        }
      }
    }
    break;
  // --------------------------------------------------- účastníci akce
  case 'U3':    // dlužníci
  case 'U2':    // sloužící
  case 'U':
    $html.= "Obeslaných účastníků ";
    $AND= $cond ? "AND $cond" : '';
    $AND.= $dopis_var=='U'  ? " AND p.funkce IN (0,1,2,5)" : (
           $dopis_var=='U2' ? " AND p.funkce IN (1,2,5)"   : (
           $dopis_var=='U3' ?
             " AND p.funkce IN (0,1,2,5) AND
               IF(a.ma_cenu AND p.avizo=0,
                 IF(p.platba1+p.platba2+p.platba3+p.platba4>0,
                   p.platba1+p.platba2+p.platba3+p.platba4,
                   IF(pouze>0,1,2)*a.cena)>platba,
                 p.platba1+p.platba2+p.platba3+p.platba4+p.poplatek_d>platba+platba_d)"
         : " --- chybné komu --- " ));
    // využívá se toho, že role rodičů 'a','b' jsou před dětskou 'd', takže v seznamech
    // GROUP_CONCAT jsou rodiče, byli-li na akci. Emaily se ale vezmou ode všech, mají-li osobní
    $qry= "SELECT a.nazev,id_pobyt,pouze,COUNT(*) AS _na_akci,avizo,
             GROUP_CONCAT(DISTINCT o.id_osoba ORDER BY t.role) AS _id,
             GROUP_CONCAT(DISTINCT CONCAT(prijmeni,' ',jmeno) ORDER BY t.role) AS _jm,
             GROUP_CONCAT(DISTINCT IF(o.kontakt,o.email,'')) AS email,
             GROUP_CONCAT(DISTINCT r.emaily) AS emaily
           FROM dopis AS d
           JOIN akce AS a ON d.id_duakce=a.id_duakce
           JOIN pobyt AS p ON d.id_duakce=p.id_akce
           JOIN spolu AS s USING(id_pobyt)
           JOIN osoba AS o ON s.id_osoba=o.id_osoba
           LEFT JOIN tvori AS t ON t.id_osoba=o.id_osoba
           LEFT JOIN rodina AS r USING (id_rodina)
           WHERE id_dopis=$id_dopis $AND GROUP BY id_pobyt";
    $res= mysql_qry($qry);
    while ( $res && ($d= mysql_fetch_object($res)) ) {
      $n++;
      $nazev= "Účastníků {$d->nazev}";
      list($jm)= explode(',',$d->_jm);
      // kontrola vyřazených mailů
      $eo= $d->email;
      if ( strpos($eo,'*')!==false ) { $mimo.= "$delm$jm"; $delm= ', '; $mx++; $eo= ''; }
      $er= $d->emaily;
      if ( strpos($er,'*')!==false ) { $mimo.= "$delm$jm"; $delm= ', '; $mx++; $er= ''; }
      // pokud je na akci pouze jeden, pošli jen na jeho mail - pokud oba, pošli na všechny maily
      if ( $eo!='' || $er!='' ) {
//         $em= $d->pouze && $eo!='' ? $eo : (             // na akci pouze jeden => osobní mail
        $em= $d->_na_akci==1 && $eo!='' ? $eo : (       // na akci pouze jeden => osobní mail
          $eo!='' && $er!='' ? "$eo,$er" : $eo.$er      // jinak cokoliv půjde
        );
        $emaily[]= $em;
        $pobyty[]= $d->id_pobyt;
        list($ids[])= explode(',',$d->_id);
        list($jmena[])= explode(',',$d->_jm);
      }
      else {
        $nema.= "$deln$jm"; $deln= ', ';
        $nx++;
      }
    }
    break;
  }
  // --------------------------------------------------- projdi a verifikuj adresy
//                                                 debug($emaily,"emaily");
  for ($i= 0; $i<count($ids); $i++) {
    $email= ''; $del= '';
    foreach(preg_split('/\s*[,;]\s*/',$emaily[$i],0,PREG_SPLIT_NO_EMPTY) as $adr) {
//                                                 debug(preg_split('/\s*[,;]\s*/',$emaily[$i],0,PREG_SPLIT_NO_EMPTY),$emaily[$i]); break;
      $chyba= '';
//                                                 display("$adr");
      if ( emailIsValid($adr,$chyba) ) {
        $email.= $del.$adr;                     // první dobrý bude adresou
        $del= ',';                              // zbytek pro CC
      }
    }
    if ( $email ) {
      $emaily[$i]= $email;
      $nt++;
    }
    else {                                      // žádný nebyl ok
      $spatne.= "$dels{$jmena[$i]}"; $dels= ', ';
      unset($emaily[$i],$ids[$i],$jmena[$i],$pobyty[$i]);
      $ns++;
    }
  }
  $result->_adresy= $emaily;
  $result->_pobyty= $pobyty;
  $result->_ids= $ids;
  $html.= "$nazev je $n celkem\n";
  $html.= $ns ? "$ns má chybný mail ($spatne)\n" : '';
  $html.= $nx ? "$nx nemají mail ($nema)\n" : '';
  $html.= $nm ? "$nm nechtějí hromadné informace ($nomail)\n" : '';
  $html.= $mx ? "$mx mají mail označený '*' jako nedostupný ($mimo)" : '';
  $result->_html= "$html<br><br>" . ($nt>0
    ? "Opravdu vygenerovat seznam pro rozeslání\n'$nazev'\nna $nt adres?"
    : "Mail '$nazev' nemá žádného adresáta, stiskni ZRUŠIT");
  $result->_count= $nt;
  if ( !$recall ) {
    // pro delší seznamy
    $result->_dopis_var= $dopis_var;
    $result->_cond= $cond ? $cond : '';
    $result->_adresy= array();
    $result->_ids= array();
  }
//                                                 debug($result,"mail2_mai_pocet.result");
  return $result;
}
# ---------------------------------------------------------------------------------- mail2_mai_posli
# do tabulky MAIL dá seznam emailových adres pro rozeslání (je volána po mail2_mai_pocet)
# $id_dopis => dopis(&pocet)
# $info = {_adresy,_ids[,_cond]}   _cond
function mail2_mai_posli($id_dopis,$info) {  trace();
  $num= 0;
  $err= '';
//                                                         debug($info);
  // smaž starý seznam
  $qry= "DELETE FROM mail WHERE id_dopis=$id_dopis ";
//                                                         fce_log("mail2_mai_posli: $qry");
  $res= mysql_qry($qry);
  if ( !$res ) fce_error("mail2_mai_smaz: mazání rozesílání mailu No.'$id_dopis' se nepovedlo");

  if ( isset($info->_dopis_var) ) {
    // přepočítej adresy
    $info= mail2_mai_pocet($id_dopis,$info->_dopis_var,$info->_cond,true);
//     $info->_adresy= $result->_adresy;
  }
  if ( isset($info->_adresy) ) {
    // zjisti text dopisu a jestli obsahuje proměnné
    $obsah= select('obsah','dopis',"id_dopis=$id_dopis");
    $is_vars= preg_match_all("/[\{]([^}]+)[}]/",$obsah,$list);
    $vars= $list[1];
//                                                                 debug($vars);
    // pokud jsou přímo známy adresy, pošli na ně
    $ids= array();
    foreach($info->_ids as $i=>$id) $ids[$i]= $id;
    if ( $info->_pobyty ) foreach($info->_pobyty as $i=>$pobyt) $pobyty[$i]= $pobyt;
    foreach ($info->_adresy as $i=>$email) {
      $id= $ids[$i];
      // vlož do MAIL - pokud nezačíná *
      if ( $email[0]!='*' ) {
        $id_pobyt= isset($pobyty[$i]) ? $pobyty[$i] : 0;
        // pokud dopis obsahuje proměnné, personifikuj obsah
        $body= $is_vars ? mail2_personify($obsah,$vars,$id_pobyt,$err) : '';
        $qr= "INSERT mail (id_davka,znacka,stav,id_dopis,id_clen,id_pobyt,email,body)
              VALUE (1,'@',0,$id_dopis,$id,$id_pobyt,'$email','$body')";
//                                         display("$i:$qr");
        $rs= mysql_qry($qr);
        $num+= mysql_affected_rows();
      }
    }
  }
  else {
    // jinak zjisti adresy z databáze
    $qry= mail2_mai_qry($info->_cond);
    $res= mysql_qry($qry);
    while ( $res && $c= mysql_fetch_object($res) ) {
      // vlož do MAIL
      if ( $c->email[0]!='*' ) {
        $qr= "INSERT mail (id_davka,znacka,stav,id_dopis,id_clen,email)
              VALUE (1,'@',0,$id_dopis,{$c->id_clen},'{$c->email}')";
        $rs= mysql_qry($qr);
        $num+= mysql_affected_rows();
      }
    }
  }
  // oprav počet v DOPIS
  $qr= "UPDATE dopis SET pocet=$num WHERE id_dopis=$id_dopis";
//                                                         fce_log("mail2_mai_posli: UPDATE");
  $rs= mysql_qry($qr);
  return $err;
}
# ---------------------------------------------------------------------------------- mail2_personify
# spočítá proměnné podle id_pobyt a dosadí do textu dopisu
# vrátí celý text
function mail2_personify($obsah,$vars,$id_pobyt,&$err) {
  $text= $obsah;
  list($duvod_typ,$duvod_text,$id_hlavni,$id_soubezna,
       $platba1,$platba2,$platba3,$platba4,$poplatek_d)=
    select('duvod_typ,duvod_text,IFNULL(id_hlavni,0),id_duakce,
      platba1,platba2,platba3,platba4,poplatek_d',
    "pobyt LEFT JOIN akce ON id_hlavni=pobyt.id_akce",
    "id_pobyt=$id_pobyt");
  foreach($vars as $var) {
    $val= '';
    switch ($var) {
    case 'akce_cena':
      // zjisti, zda je cena stanovena
      if ($platba1+$platba2+$platba3+$platba4+$poplatek_d==0) {
        // není :-(
        $err.= "<br>POZOR: všichni účastníci nemají stanovenu cenu (pobyt=$id_pobyt)";
        break;
      }
      if ( $duvod_typ ) {
        $val= $duvod_text;
      }
      elseif ( $id_hlavni ) {
        $ret= akce_vzorec_soubeh($id_pobyt,$id_hlavni,$id_soubezna);
        $val= $ret->mail;
      }
      else {
        $ret= akce_vzorec($id_pobyt);
        $val= $ret->mail;
      }
      break;
    }
    $text= str_replace('{'.$var.'}',$val,$text);
  }
  $text= mysql_real_escape_string($text);
  return $text;
}
# ----------------------------------------------------------------------------------- mail2_mai_info
# informace o členovi
# $id - klíč osoby nebo chlapa
# $zdroj určuje zdroj adres
#   'U','U2','U3' - rozeslat účastníkům akce dopis.id_duakce ukazující do akce
#   'C' - rozeslat účastníkům akce dopis.id_duakce ukazující do ch_ucast
#   'Q' - rozeslat na adresy vygenerované dopis.cis_skupina => hodnota
#   'G' - maillist
function mail2_mai_info($id,$email,$id_dopis,$zdroj,$id_mail) {  trace();
  $html= '';
  switch ($zdroj) {
  case 'C':                     // chlapi
    $qry= "SELECT * FROM ezer_ys.chlapi WHERE id_chlapi=$id ";
    $res= mysql_qry($qry);
    if ( $res && $c= mysql_fetch_object($res) ) {
      $html.= "{$c->prijmeni} {$c->jmeno}<br>";
      $html.= "{$c->ulice}, {$c->psc} {$c->obec}<br><br>";
      if ( $c->telefon )
        $html.= "Telefon: {$c->telefon}<br>";
    }
    break;
  case 'Q':                     // číselník
    $qryQ= "SELECT _cis.hodnota,_cis.zkratka,_cis.barva FROM dopis
           JOIN _cis ON _cis.data=dopis.cis_skupina AND _cis.druh='db_maily_sql'
           WHERE id_dopis=$id_dopis ";
    $resQ= mysql_qry($qryQ);
    if ( $resQ && ($q= mysql_fetch_object($resQ)) ) {
      if ( $q->barva==1 ) {
        // databáze CHLAPI
        $qry= "SELECT * FROM ezer_ys.chlapi WHERE id_chlapi=$id ";
        $res= mysql_qry($qry);
        if ( $res && $c= mysql_fetch_object($res) ) {
          $html.= "{$c->prijmeni} {$c->jmeno}<br>";
          $html.= "{$c->ulice}, {$c->psc} {$c->obec}<br><br>";
          if ( $c->telefon )
            $html.= "Telefon: {$c->telefon}<br>";
        }
      }
      elseif ( $q->barva==4 ) {
        // kopie databáze DS = ds_osoba_copy
        $qry= "SELECT * FROM ds_osoba_copy WHERE id_osoba=$id ";
        $res= mysql_qry($qry);
        if ( $res && $c= mysql_fetch_object($res) ) {
          $html.= "{$c->prijmeni} {$c->jmeno}<br>";
          $html.= "{$c->ulice}, {$c->psc} {$c->obec}<br><br>";
          if ( $c->telefon )
            $html.= "Telefon: {$c->telefon}<br>";
        }
      }
      elseif ( $q->barva==2 ) {
        // databáze osob
        $qry= "SELECT * FROM osoba WHERE id_osoba=$id ";
        $res= mysql_qry($qry);
        if ( $res && $c= mysql_fetch_object($res) ) {
          $html.= "{$c->prijmeni} {$c->jmeno}<br>";
          $html.= "{$c->ulice}, {$c->psc} {$c->obec}<br><br>";
          if ( $c->telefon )
            $html.= "Telefon: {$c->telefon}<br>";
        }
      }
      else {
        // databáze MS
        // SELECT vrací (_id,prijmeni,jmeno,ulice,psc,obec,email,telefon)
        $qry= $q->hodnota;
        $qry= db_mail_sql_subst($qry);
        if ( strpos($qry,"GROUP BY") ) {
          if ( strpos($qry,"HAVING") )
            $qry= str_replace("HAVING","HAVING _id=$id AND ",$qry);
          else
            $qry= str_replace("GROUP BY","GROUP BY _id HAVING _id=$id AND ",$qry);
          // zatém jen pro tuto větev
          $res= mysql_qry($qry);
          while ( $res && ($c= mysql_fetch_object($res)) ) {
            $html.= "{$c->prijmeni} {$c->jmeno}<br>";
            $html.= "{$c->ulice}, {$c->psc} {$c->obec}<br><br>";
            if ( $c->telefon )
              $html.= "Telefon: {$c->telefon}<br>";
          }
        }
        else {
          // způsobuje chybu  GROUP BY vyžaduje nějakou agregační funkci
//           $qry.= " GROUP BY _id HAVING _id=$id ";
        }
      }
    }
    break;
  case 'U':                     // účastníci akce
  case 'U2':                    // sloužící účastníci akce
  case 'U3':                    // dlužníci
    $qry= "SELECT * FROM osoba WHERE id_osoba=$id ";
    $res= mysql_qry($qry);
    if ( $res && $c= mysql_fetch_object($res) ) {
      $html.= "{$c->id_osoba}: {$c->jmeno} {$c->prijmeni}<br>";
      $html.= "{$c->ulice}, {$c->psc} {$c->obec}<br><br>";
      if ( $c->telefony )
        $html.= "Telefon: {$c->telefony}<br>";
    }
    break;
  case 'G':                     // mail-list
    $make_href= function ($fnames) {
      global $ezer_root;
      $href= array();
      foreach(explode(',',$fnames) as $fnamesize) {
        list($fname)= explode(':',$fnamesize);
        $href[]= "<a href='docs/$ezer_root/$fname' target='pdf'>$fname</a>";
      }
      return implode(', ',$href);
    };
    list($obsah,$prilohy)= select('obsah,prilohy','dopis',"id_dopis=$id_dopis");
    $priloha= select('priloha','mail',"id_mail=$id_mail");
    $c= select("*",'osoba',"id_osoba=$id");
    $html.= "{$c->id_osoba}: {$c->jmeno} {$c->prijmeni}<br>";
    $html.= "{$c->ulice}, {$c->psc} {$c->obec}<br><br>";
    if ( $c->telefony )
      $html.= "Telefon: {$c->telefony}<br>";
    // přílohy ke kontrole
    if ( $prilohy )
      $html.= "<br>Společné přílohy: ".$make_href($prilohy);
    if ( $priloha )
      $html.= "<br>Vlastní přílohy: ".$make_href($priloha);
    break;
  }
  return $html;
}
# ----------------------------------------------------------------------------------- mail2_mai_smaz
# smazání mailu v DOPIS a jeho rozesílání v MAIL
function mail2_mai_smaz($id_dopis) {  trace();
  $qry= "DELETE FROM dopis WHERE id_dopis=$id_dopis ";
  $res= mysql_qry($qry);
  if ( !$res ) fce_error("mail2_mai_smaz: mazání mailu No.'$id_dopis' se nepovedlo");
  $qry= "DELETE FROM mail WHERE id_dopis=$id_dopis ";
  $res= mysql_qry($qry);
  if ( !$res ) fce_error("mail2_mai_smaz: mazání rozesílání mailu No.'$id_dopis' se nepovedlo");
  return true;
}
# ----------------------------------------------------------------------------------- mail2_mai_stav
# úprava stavu mailové adresy
# ZATIM BEZ: (maže maily - nutné zohlednit i id_clen==id_osoba aj.) včetně znovuzískání mailové adresy s karty účastníka
function mail2_mai_stav($id_mail,$stav) {  trace();

  list($id_dopis,$id_pobyt)= select("id_dopis,id_pobyt","mail","id_mail=$id_mail");
  $novy_mail= '';
//   if ( $id_pobyt) {
//     $oprava= mail2_mai_pocet($id_dopis,'U',$cond="id_pobyt=$id_pobyt");
//     $emaily= $oprava->_adresy[0];
//     $novy_mail= ",email='$emaily'";
//                                                   debug($oprava,"mail2_mai_stav:$emaily.");
//   }
  $qry= "UPDATE mail SET stav=$stav$novy_mail WHERE id_mail=$id_mail ";
  $res= mysql_qry($qry);
  if ( !$res ) fce_error("mail2_mai_stav: změna stavu mailu No.'$id_mail' se nepovedla");
  return true;
}
# -------------------------------------------------------------------------------------------------- mail2_mai_send
# ASK
# odešli dávku $kolik mailů ($kolik=0 znamená testovací poslání)
# $from,$fromname = From,ReplyTo
# $test = 1 mail na tuto adresu (pokud je $kolik=0)
# pokud je definováno $id_mail s definovaným text MAIL.body, použije se - jinak DOPIS.obsah
function mail2_mai_send($id_dopis,$kolik,$from,$fromname,$test='',$id_mail=0) { trace();
  // připojení případné přílohy
  $attach= function($mail,$fname) {
    global $ezer_root;
    if ( $fname ) {
      foreach ( explode(',',$fname) as $fnamesb ) {
        list($fname,$bytes)= explode(':',$fnamesb);
        $fpath= "docs/$ezer_root/".trim($fname);
        $mail->AddAttachment($fpath);
  } } };
  //
  global $ezer_path_serv, $ezer_root;
  $phpmailer_path= "$ezer_path_serv/licensed/phpmailer";
  require_once("$phpmailer_path/class.phpmailer.php");
  $result= (object)array('_error'=>0);
  $pro= '';
  // přečtení rozesílaného mailu
  $qry= "SELECT * FROM dopis WHERE id_dopis=$id_dopis ";
  $res= mysql_qry($qry,1,null,1);
  $d= mysql_fetch_object($res);
  // napojení na mailer
  $html= '';
//   $klub= "klub@proglas.cz";
  $martin= "martin@smidek.eu";
//   $jarda= "cerny.vavrovice@seznam.cz";
//   $jarda= $martin;
  // poslání mailů
  $mail= new PHPMailer;
  $mail->SetLanguage('cz',"$phpmailer_path/language/");
  $mail->Host= "192.168.1.1";
  $mail->CharSet = "UTF-8";
  $mail->From= $from;
  $mail->AddReplyTo($from);
//   $mail->ConfirmReadingTo= $jarda;
  $mail->FromName= "$fromname";
  $mail->Subject= $d->nazev;
//                                         display($mail->Subject);
  $mail->IsHTML(true);
  $mail->Mailer= "smtp";
  $attach($mail,$d->prilohy);
//   if ( $d->prilohy ) {
//     foreach ( explode(',',$d->prilohy) as $fnamesb ) {
//       list($fname,$bytes)= explode(':',$fnamesb);
//       $fpath= "docs/$ezer_root/".trim($fname);
//       $mail->AddAttachment($fpath);
//     }
//   }
  if ( $kolik==0 ) {
    // testovací poslání sobě
    if ( $id_mail ) {
      // přečtení personifikace rozesílaného mailu
      $qry= "SELECT * FROM mail WHERE id_mail=$id_mail ";
      $res= mysql_qry($qry,1,null,1);
      $m= mysql_fetch_object($res);
      if ( $m->body ) {
        $obsah= $m->body;
        $pro= "s personifikací pro {$m->email}";
      }
      else {
        // jinak obecný z DOPIS
        $obsah= $d->obsah;
        $pro= '';
      }
      $attach($mail,$m->priloha);
    }
    $mail->Body= $obsah;
    $mail->AddAddress($test);   // pošli sám sobě
    // pošli
    $ok= $mail->Send();
    if ( $ok  )
      $html.= "<br><b style='color:#070'>Byl odeslán mail na $test $pro - je zapotřebí zkontrolovat obsah</b>";
    else {
      $err= $mail->ErrorInfo;
      $html.= "<br><b style='color:#700'>Při odesílání mailu došlo k chybě: $err</b>";
      $result->_error= 1;
    }
//                                                 display($html);
  }
  else {
    // poslání dávky $kolik mailů
    $n= $nko= 0;
    $qry= "SELECT * FROM mail WHERE id_dopis=$id_dopis AND stav IN (0,3)";
    $res= mysql_qry($qry);
    while ( $res && ($z= mysql_fetch_object($res)) ) {
      // posílej mail za mailem
      if ( $n>=$kolik ) break;
      $n++;
      $i= 0;
      $mail->ClearAddresses();
      $mail->ClearCCs();
      if ( $z->body ) {
        // pokud má mail definován obsah (personifikovaný mail) ber z MAIL
        $obsah= $z->body;
      }
      else {
        // jinak obecný z DOPIS
        $obsah= $d->obsah;
      }
      // přílohy - pokud jsou vlastní, pak je třeba staré vymazat a vše vložit
      if ( $z->priloha ) {
        $mail->ClearAttachments();
        $attach($mail,$d->prilohy);
        $attach($mail,$z->priloha);
      }
      $mail->Body= $obsah;
      foreach(preg_split("/,\s*|;\s*|\s+/",trim($z->email," ,;"),-1,PREG_SPLIT_NO_EMPTY) as $adresa) {
        if ( !$i++ )
          $mail->AddAddress($adresa);   // pošli na 1. adresu
        else                            // na další jako kopie
          $mail->AddCC($adresa);
      }
//       $mail->AddBCC($klub);
      // zkus poslat mail
      try { $ok= $mail->Send(); } catch(Exception $e) { $ok= false; }
      if ( !$ok  ) {
        $ident= $z->id_clen ? $z->id_clen : $adresa;
        $err= $mail->ErrorInfo;
        $html.= "<br><b style='color:#700'Při odesílání mailu pro $ident došlo k chybě: $err</b>";
        $result->_error= 1;
        $nko++;
      }
      // zapiš výsledek do tabulky
      $stav= $ok ? 4 : 5;
      $msg= $ok ? '' : $mail->ErrorInfo;
      $qry1= "UPDATE mail SET stav=$stav,msg=\"$msg\" WHERE id_mail={$z->id_mail}";
      $res1= mysql_qry($qry1);
    }
    $html.= "<br><b style='color:#070'>Bylo odesláno $n emailů ";
    $html.= $nko ? "s $nko chybami " : "bez chyb";
    $html.= "</b>";
  }
  // zpráva o výsledku
  $result->_html= $html;
//                                                 debug($result,"mail2_mai_send");
  return $result;
}
# --------------------------------------------------------------------------------- mail2_mai_attach
# přidá další přílohu k mailu (soubor je v docs/$ezer_root)
function mail2_mai_attach($id_dopis,$f) { trace();
  // nalezení záznamu v tabulce a přidání názvu souboru
  $names= select('prilohy','dopis',"id_dopis=$id_dopis");
  $names= ($names ? "$names," : '')."{$f->name}:{$f->size}";
  query("UPDATE dopis SET prilohy='$names' WHERE id_dopis=$id_dopis");
  return 1;
}
# ----------------------------------------------------------------------------- mail2_mai_detach_all
# odstraní všechny přílohy mailu
function mail2_mai_detach_all($id_dopis) { trace();
  query("UPDATE dopis SET prilohy='' WHERE id_dopis=$id_dopis");
  return 1;
}
# --------------------------------------------------------------------------------- mail2_mai_detach
# odebere soubor z příloh
function mail2_mai_detach($id_dopis,$name) { trace();
  // nalezení záznamu v tabulce a odebrání názvu souboru
  $names= select('prilohy','dopis',"id_dopis=$id_dopis");
  $as= explode(',',$names);
  $as2= array();
  foreach($as as $a) {
    list($an,$ab)= explode(':',$a);
    if ( $an!=$name )$as2[]= $a;
  }
  $names2= implode(',',$as2);
  query("UPDATE dopis SET prilohy='$names2' WHERE id_dopis=$id_dopis");
  return 1;
}
# =====================================================================================> . SQL maily
# vytváření a testování SQL dotazů pro definici mailů
# ------------------------------------------------------------------------------------ mail2_copy_ds
# ASK - kopie tabulky SETKANI.DS_OSOBA do EZER_YS.DS_OSOBA_COPY
# vrací {id_cis,data,query}
function mail2_copy_ds() {  trace();
  global $ezer_db;
  $html= 'kopie se nepovedla';
  // smazání staré kopie
  $qry= "TRUNCATE TABLE /*ezer_ys.*/ds_osoba_copy ";
  $ok= mysql_qry($qry);
  if ( $ok ) {
    $html= "inicializace ds_osoba_copy ok";
    ezer_connect('setkani');
    $qrs= "SELECT * FROM setkani.ds_osoba WHERE email!='' ";
    $res= mysql_qry($qrs);
    while ( $res && ($s= mysql_fetch_object($res)) ) {
//                                                         debug($s,'s',(object)array('win1250'=>1));
      $ids= $vals= $del= '';
      foreach($s as $id=>$val) {
        $ids.= "$del$id";
        $vals.= "$del'".mysql_real_escape_string(wu($val))."'";
        $del= ',';
      }
      $qry= "INSERT INTO /*ezer_ys.*/ds_osoba_copy ($ids) VALUES ($vals)";
      $ok= mysql_query($qry,$ezer_db['ezer_ys'][0]);
//                                                         display("$ok:$qry");
      if ( !$ok ) {
        $html.= "\nPROBLEM ".mysql_error();
      }
    }
    if ( $ok ) {
      $html.= "\nkopie do ds_osoba_copy ok";
    }
  }
  return $html;
}
# ------------------------------------------------------------------------------------ mail2_sql_new
# ASK - vytvoření SQL dotazů pro definici mailů
# vrací {id_cis,data,query}
function mail2_sql_new() {  #trace();
  $id= select("MAX(0+id_cis)","_cis","druh='db_maily_sql'");
  $data= select("MAX(0+data)","_cis","druh='db_maily_sql'");
  $result= (object)array(
    'id'=>$id+1, 'data'=>$data+1,
    'qry'=>"SELECT id_... AS _id,prijmeni,jmeno,ulice,psc,obec,email,telefon FROM ...");
  return $result;
}
# ---------------------------------------------------------------------------------- mail2_sql_subst
# ASK - parametrizace SQL dotazů pro definici mailů, vrací modifikovaný dotaz
# nebo pokud je prázdný tak přehled možných parametrizací dotazu
function mail2_sql_subst($qry='') {  trace();
  // parametry
  $parms= array (
   'letos' => array (date('Y'),'letošní rok'),
   'vloni' => array (date('Y')-1,'loňský rok'),
   'pred2' => array (date('Y')-2,'předloni'),
   'pred3' => array (date('Y')-3,'před 3 lety'),
   'pred4' => array (date('Y')-4,'před 4 lety'),
   'pred5' => array (date('Y')-5,'před 5 lety'),
   'pred6' => array (date('Y')-6,'před 6 lety')
  );
  if ( $qry=='' ) {
    // help
    $del= '';
    foreach ($parms as $parm=>$value) {
      $qry.= "$del\$$parm = {$value[1]} ({$value[0]})";
      $del= '<br>';
    }
  }
  else {
    // substituce
    foreach ($parms as $parm=>$value) {
      $qry= str_replace("\$$parm",$value[0],$qry);
    }
  }
  return $qry;
}
# ------------------------------------------------------------------------------------ mail2_sql_try
# ASK - vytvoření SQL dotazů pro definici mailů
# vrací {id_cis,data,query}
function mail2_sql_try($qry,$vsechno=0) {  trace();
  $html= $head= $tail= '';
  $emails= array();
  try {
    // substituce
    $qry= mail2_sql_subst($qry);
    // dotaz
    $time_start= getmicrotime();
    $res= mysql_qry($qry);
    $time= round(getmicrotime() - $time_start,4);
    if ( !$res ) {
      $html.= "<span style='color:darkred'>ERROR ".mysql_error()."</span>";
    }
    else {
      $nmax= $vsechno ? 99999 : 200;
      $num= mysql_num_rows($res);
      $head.= "Výběr obsahuje <b>$num</b> emailových adresátů, nalezených během $time ms, ";
      $head.= $num>$nmax ? "následuje prvních $nmax adresátů" : "následují všichni adresáti";
      $tail.= "<br><br><table class='stat'>";
      $tail.= "<tr><th>prijmeni jmeno</th><th>email</th><th>telefon</th>
        <th>ulice psc obec</th><th>x</th><th>y</th><th>z</th></tr>";
      $n= $nmax;
      while ( $res && ($c= mysql_fetch_object($res)) ) {
        if ( $n ) {
          $tail.= "<tr><td>{$c->prijmeni} {$c->jmeno}</td><td>{$c->_email}</td><td>{$c->telefon}</td>
            <td>{$c->ulice} {$c->psc} {$c->obec}</td><td>{$c->_x}</td><td>{$c->_y}</td><td>{$c->_z}</td></tr>";
          $n--;
        }
        // počítání mailů
        $es= preg_split('/[,;]/',str_replace(' ','',$c->_email));
        foreach($es as $e) {
          if ( $e!='' && !in_array($e,$emails) ) $emails[]= $e;
        }
      }
      $tail.= "</table>";
      $tail.= $num>$nmax ? "..." : "";
    }
  }
  catch (Exception $e) { $html.= "<span style='color:red'>FATAL ".mysql_error()."</span>";  }
  $head.= "<br>Adresáti mají <b>".count($emails)."</b> různých emailových adres";
  $html= $html ? $html : $head.$tail;
//                                                 debug($emails,"db_mail_sql_try");
  return $html;
}
# =================================================================================> . Generátor SQL
# ---------------------------------------------------------------------------------- mail2_gen_excel
# vygeneruje do Excelu seznam adresátů
function mail2_gen_excel($gq,$nazev) { trace();
  global $ezer_root;
  $href= "CHYBA!";
  // úprava dotazu
  $gq= str_replace('&gt;','>',$gq);
  $gq= str_replace('&lt;','<',$gq);
//                                                         display($gq);
  // export do Excelu
  // zahájení exportu
  $ymd_hi= date('Ymd_Hi');
  $dnes= date('j. n. Y');
  $t= "mail-list $nazev, stav ke dni $dnes";
  $file= "maillist_$ymd_hi";
  $type= 'xls';
  $par= (object)array('dir'=>$ezer_root,'file'=>$file,'type'=>$type,'title'=>$t,'color'=>'aac0cae2');
  $clmns= "_name:příjmení jméno,_email:email,_ulice:ulice,_psc:PSČ,_obec:obec,_stat:stát,_ucasti:účastí";
  if ( preg_match("/iniciace/i",$gq) ) {
    // přidání sloupce iniciace, pokud se vyskytuje v dotazu
    $clmns.= ",iniciace:iniciace";
  }
  $titles= $del= '';
  $fields= $values= array();
  foreach (explode(',',$clmns) as $clmn) {
    list($field,$title)= explode(':',trim($clmn));
    $title= $title ? $title : $field;
    $titles.= "$del$title";
    $fields[]= $field;
    $values[$field]= "";
    $del= ',';
  }
  $pipe= array('narozeni'=>'sql_date1');
  export_head($par,$titles,":: bcolor=ffc0e2c2 wrap border=+h");
  // dotaz
  $gr= @mysql_query($gq);
  if ( !$gr ) { fce_warning(mysql_error()); goto end; }
  while ( $gr && ($g= mysql_fetch_object($gr)) ) {
                                                display('');
    foreach ($g as $f => $val) {
      if ( in_array($f,$fields) ) {
        $a= $val;
        if ( isset($pipe[$f]) ) $a= $pipe[$f]($a);
        $values[$f]= $a;
                                                display_("$a ");
      }
    }
    export_row($values,":: border=+h");
  }
  export_tail();
//                                                 display(export_tail(1));
  // odkaz pro stáhnutí
  $href= "soubor pro <a href='docs/$ezer_root/$file.$type' target='xls'>Excel</a>";
end:
  return $href;
}
/** ==========================================================================================> FOTO */
# funkce pro práce se seznamem fotek:
#   set=přidání na konec, get=vrácení n-té, del=smazání n-té a vrácení n-1 nebo n+1
# ---------------------------------------------------------------------------------------- foto2_get
# vrátí n-tou fotografii ze seznamu (-1 znamená poslední) spolu s informacemi:
# ret =>
#   html  = kód pro zobrazení miniatury (s href na původní velikost) nebo chybové hlášení
#   left  = pořadí předchozí fotky nebo 0
#   right = pořadí následující fotky nebo 0
# ? out   = seznam s vynecháním
function foto2_get($table,$id,$n,$w,$h) {  trace();
  global $ezer_path_root;
  $ret= (object)array('img'=>'','left'=>0,'right'=>0,'msg'=>'','tit'=>'');
  $fotky= '';
  // názvy fotek
  $osobnich= 0;
  if ( $table=='rodina' ) {
    list($fotky,$rodinna)= select('fotka,nazev','rodina',"id_$table=$id");
  }
  elseif ( $table=='osoba' ) {
    $rf= mysql_qry("
      SELECT GROUP_CONCAT(r.fotka) AS fotky,r.nazev,
        o.fotka,o.prijmeni,o.jmeno
      FROM rodina AS r JOIN tvori USING (id_rodina) JOIN osoba AS o USING (id_osoba)
      WHERE id_osoba=$id AND r.fotka!='' ");
    $x= mysql_fetch_object($rf);
    $rodinna= $x->nazev;
    $fotky= $x->fotky;
    $fotka= $x->fotka;
    if ( $fotka ) {
      $osobnich= substr_count($fotka,',')+1;
      $osobni= "{$x->prijmeni} {$x->jmeno}";
      $fotky= $fotky ? "$fotka,$fotky" : $fotka;
    }
  }
  if ( $fotky=='' ) { $ret->html= "žádná fotka"; goto end; }
  $nazvy= explode(',',$fotky);
  // název n-té fotky
  $n= $n==-1 ? count($nazvy) : $n;
//                                         display("fotky='$fotky', n=$n");
  if ( !(1<=$n && $n<=count($nazvy)) ) { $ret->html= "$n je chybné pořadí fotky"; goto end; }
  // výpočet left, right, out
  $ret->left= $n-1;
  $ret->right= $n >= count($nazvy) ? 0 : $n+1;
  // zpracování
  $nazev= $nazvy[$n-1];
  $orig= "$ezer_path_root/fotky/$nazev";
//                                         display("file_exists($orig)=".file_exists($orig));
  if ( !file_exists($orig) ) {
    $ret->html= "fotka <b>$nazev</b> není dostupná";
    goto end;
  }
  // zmenšení na požadovanou velikost, pokud již není
  $dest= "$ezer_path_root/fotky/copy/$nazev";
  if ( !file_exists($dest) ) {
    $ok= foto2_resample($orig,$dest,$w,$h,0,1);
    if ( !$ok ) { $ret->html= "fotka </b>$nazev</b> nešla zmenšit ($ok)"; goto end; }
  }
  // html-kód s žádostí o zaostření na straně klienta
  $ret->nazev= $nazev;
  $jmeno= $n>$osobnich ? $rodinna : $osobni;
  $ret->jmeno= "<span style='font-weight:bold;font-size:120%'>$jmeno</span>";
//                                                 display("$n>$osobnich ? $rodinna : $osobni");
  $ret->html= "<a href='fotky/$nazev' target='_album' title='$jmeno'><img src='fotky/copy/$nazev'
    onload='var x=arguments[0];img_filter(x.target,\"sharpen\",0.7,1);'/></a>";
end:
//                                                 debug($ret,"album_get2($table,$id,$n,$w,$h)");
  return $ret;
}
# ---------------------------------------------------------------------------------------- foto2_add
# přidá fotografii do seznamu (rodina|osoba) podle ID na konec a vrátí nové názvy
function foto2_add($table,$id,$fileinfo) { trace();
  global $ezer_path_root, $ezer_root;
  $name= "{$ezer_root}_{$table}_{$id}.".utf2ascii($fileinfo->name);
  $path= "$ezer_path_root/fotky/$name";
  $data= $fileinfo->text;
  // test korektnosti fotky
  if ( substr($data,0,23)=="data:image/jpeg;base64," ) {
    // uložení fotky na disk
    $data= base64_decode(substr("$data==",23));
    $bytes= file_put_contents($path,$data);
    // přidání názvu fotky do záznamu v tabulce
    $fotky= select('fotka',$table,"id_$table=$id");
    $fotky.= $fotky ? ",$name" : $name;
    query("UPDATE $table SET fotka='$fotky' WHERE id_$table=$id");
  }
  else {
    $name= '';          // tiché oznámení chyby
  }
  return $name;
}
# -------------------------------------------------------------------------------------- foto_delete
# zruší n-tou fotografii ze seznamu v albu a vrátí tu nyní n-tou nebo předchozí
function foto_delete($table,$id,$n) { trace();
  global $ezer_path_root;
  $ok= 0;
  // nalezení seznamu názvů fotek
  $fotky= explode(',',select('fotka',$table,"id_$table=$id"));
  if ( 1<=$n && $n<=count($fotky) ) {
    $nazev= $fotky[$n-1];
    unset($fotky[$n-1]);
    $nazvy= implode(',',$fotky);
    query("UPDATE $table SET fotka='$nazvy' WHERE id_$table=$id");
    // smazání fotky a miniatury
    $ok= unlink("$ezer_path_root/fotky/$nazev");
//                                         display("unlink('$ezer_path_root/fotky/$name')=$ok");
    $ok&= unlink("$ezer_path_root/fotky/copy/$nazev");
//                                         display("unlink('$ezer_path_root/fotky/copy/$name')=$ok");
  }
  return $ok;
}
# ----------------------------------------------------------------------------------- foto2_resample
function foto2_resample($source, $dest, &$width, &$height,$copy_bigger=0,$copy_smaller=1) { #trace();
  global $CONST;
  $maxWidth= $width;
  $maxHeight= $height;
  $ok= 1;
  // zjistime puvodni velikost obrazku a jeho typ: 1 = GIF, 2 = JPG, 3 = PNG
  list($origWidth, $origHeight, $type)=@ getimagesize($source);
//                                                 debug(array($origWidth, $origHeight, $type),"album_resample($source, $dest, &$width, &$height,$copy_bigger)");
  if ( !$type ) $ok= 0;
  if ( $ok ) {
    if ( !$maxWidth ) $maxWidth= $origWidth;
    if ( !$maxHeight ) $maxHeight= $origHeight;
    // nyni vypocitam pomer změny
    $pw= $maxWidth / $origWidth;
    $ph= $maxHeight / $origHeight;
    $p= min($pw, $ph);
    // vypocitame vysku a sirku změněného obrazku - vrátíme ji do výstupních parametrů
    $newWidth = (int)($origWidth * $p);
    $newHeight = (int)($origHeight * $p);
    $width= $newWidth;
    $height= $newHeight;
//                                                 display("p=$p, copy_smaller=$copy_smaller");
    if ( ($pw == 1 && $ph == 1) || ($copy_bigger && $p<1) || ($copy_smaller && $p>1) ) {
//                                                 display("kopie");
      // jenom zkopírujeme
      copy($source,$dest);
    }
    else {
//                                                 display("úprava");
      // zjistíme velikost cíle - abychom nedělali zbytečnou práci
      $destWidth= $destHeight= -1; $ok= 2; // ok=2 -- nic se nedělalo
      if ( file_exists($dest) ) list($destWidth, $destHeight)= getimagesize($dest);
      if ( $destWidth!=$newWidth || $destHeight!=$newHeight ) {
        // vytvorime novy obrazek pozadovane vysky a sirky
        $image_p= ImageCreateTrueColor($newWidth, $newHeight);
        // otevreme puvodni obrazek se souboru
        switch ($type) {
        case 1: $image= ImageCreateFromGif($source); break;
        case 2: $image= ImageCreateFromJpeg($source); break;
        case 3: $image= ImageCreateFromPng($source); break;
        }
        // okopirujeme zmenseny puvodni obrazek do noveho
        if ( $maxWidth || $maxHeight )
          ImageCopyResampled($image_p, $image, 0, 0, 0, 0, $newWidth, $newHeight, $origWidth, $origHeight);
        else
          $image_p= $image;
        // ulozime
        $ok= 0;
        switch ($type) {
        case 1: /*ImageColorTransparent($image_p);*/ $ok= ImageGif($image_p, $dest);  break;
        case 2: $ok= ImageJpeg($image_p, $dest);  break;
        case 3: $ok= ImagePng($image_p, $dest);  break;
        }
      }
    }
  }
  return $ok;
}
/** =========================================================================================> DATA2 **/
# ----------------------------------------------------------------------------- data2_transform_2014
# transformace na schema 2014
# par.cmd = seznam transformací
# par.akce = id_akce | 0
# par.pobyt = id_pobyt | 0
function data2_transform_2014($par) { trace();
  global $ezer_root;
  $html= '';
  $updated= 0;
  foreach (explode(',',$par->cmd) as $cmd ) {
    $update= false;
    switch ($cmd ) {
    // ---------------------------------------------- rodina: r_umi VPS
    // opraví chybějící údaj v r_umi
    case 'vps_test':
      $qr= mysql_qry("
        SELECT nazev,YEAR(datum_od) AS _rok
        FROM pobyt
        JOIN akce ON id_akce=id_duakce
        WHERE funkce IN (1,2) AND i0_rodina=0
        GROUP BY id_akce
      ");
      while ( $qr && ($r= mysql_fetch_object($qr)) ) {
        $n++;
        $html.= "{$r->nazev}/{$r->_rok}<br>";
      }
      $html.= "Nalezeno $n akcí";
      break;
    // opraví chybějící údaj v r_umi
    case 'vps_updt':
      $qr= mysql_qry("
        SELECT i0_rodina,funkce,r_umi
        FROM pobyt
        JOIN rodina ON id_rodina=i0_rodina!=0
        WHERE funkce IN (1,2) AND NOT FIND_IN_SET(1,r_umi)
        GROUP BY i0_rodina
      ");
      while ( $qr && ($r= mysql_fetch_object($qr)) ) {
        $n++;
        $ok= query("UPDATE rodina SET r_umi=IF(r_umi,CONCAT('1,',r_umi),'1') WHERE id_rodina={$r->i0_rodina}");
        $updated+= $ok ? 1 : 0;
      }
      $html.= "Nalezeno $n rodin s funkcí VPS a u $updated doplněna tato schopnost";
      break;
    // ---------------------------------------------- rodina: r_umi Přednáší
    // opraví chybějící údaj v r_umi
    case 'lec_test':
      $qr= mysql_qry("
        SELECT nazev,YEAR(datum_od) AS _rok
        FROM pobyt
        JOIN akce ON id_akce=id_duakce
        WHERE prednasi AND i0_rodina=0
        GROUP BY id_akce
      ");
      while ( $qr && ($r= mysql_fetch_object($qr)) ) {
        $n++;
        $html.= "{$r->nazev}/{$r->_rok}<br>";
      }
      $html.= "Nalezeno $n akcí";
      break;
    // opraví chybějící údaj v r_umi
    case 'lec_updt':
      $qr= mysql_qry("
        SELECT i0_rodina,funkce,r_umi
        FROM pobyt
        JOIN rodina ON id_rodina=i0_rodina!=0
        WHERE prednasi AND NOT FIND_IN_SET(2,r_umi)
        GROUP BY i0_rodina
      ");
      while ( $qr && ($r= mysql_fetch_object($qr)) ) {
        $n++;
        $ok= query("UPDATE rodina SET r_umi=IF(r_umi,CONCAT('2,',r_umi),'1') WHERE id_rodina={$r->i0_rodina}");
        $updated+= $ok ? 1 : 0;
      }
      $html.= "Nalezeno $n rodin, které přednáší a u $updated doplněna tato schopnost";
      break;
    // ---------------------------------------------- rodina,osoba: stat
    // doplní do adresy chybějící stát
    case 'stat+':
      $update= true;
    // zobrazení počtu rodin bez státu
    case 'stat':
      $AND= $par->cnd ? " AND $par->cnd" : "";
      $n= 0;
      $qo= mysql_qry("
        SELECT r.id_rodina,stat,obec,psc,ulice
        FROM rodina AS r
        WHERE r.deleted='' AND obec!='' AND psc!='' AND stat='' $AND
      ");
      while ( $qo && ($o= mysql_fetch_object($qo)) ) {
        $n++;
        $adresa= "$o->ulice,$o->psc $o->obec";
        $html.= "<br>$o->id_rodina:$adresa";
        if ( $update ) {
          $stat= adresa2stat($adresa,$o->psc);
          $html.= "=$stat";
          $ok= query("UPDATE rodina SET stat='$stat' WHERE deleted='' AND obec='$o->obec' AND psc='$o->psc' AND stat='' ");
          $updated+= mysql_affected_rows();
          $ok= query("UPDATE osoba SET stat='$stat' WHERE deleted='' AND adresa=1 AND obec='$o->obec' AND psc='$o->psc' AND stat='' ");
          $updated+= mysql_affected_rows();
        }
        if ( $n==5 ) break;
      }
      $html.= $update
            ? ($updated ? "<br> opraveno $updated údajů<br>" : "<br>beze změn<br>")
            : "<hr>rodin bez státu je $n";
      break;
    // ---------------------------------------------- rodina: nazev
    // doplní chybějící název rodiny z hlavního člena
    case 'nazvy+':
      $update= true;
    // zobrazení počtu rodin bez názvu
    case 'nazvy':
      $n= 0;
      $qo= mysql_qry("
        SELECT r.id_rodina,SUBSTR(MIN(CONCAT(role,prijmeni)),2) AS _hlava
        FROM rodina AS r
        JOIN tvori AS t USING(id_rodina)
        JOIN osoba AS o USING(id_osoba)
        WHERE r.deleted='' AND TRIM(nazev)='' $AND
        GROUP BY r.id_rodina
      ");
      while ( $qo && ($o= mysql_fetch_object($qo)) ) {
        $n++;
        if ( $update ) {
          $updated+= data_update('rodina',$o->id_rodina,"$o->_hlava:nazev");
        }
      }
      $html.= "rodin bez názvu je $n";
      $html.= $update ? "<br> opraveno $updated údajů<br>" : (
              $n      ? "<br>provést $n změn údajů?<br>"
                      : '<br>bez možných automatických úprav, přesto zkusit?<br>');
      break;
    // ---------------------------------------------- osoba: kontakty
    // opraví pole osoba.kontakt
    case 'kontakty+':
      $update= true;
    // zobrazí přehled kontaktů
    case 'kontakty':
      //  0   1   2   3   4   5
      // -.- x.- -.x x.x x.y x.0   osobní.rodinná; single=0,clen=1
      $tab= array(array(0,0,0,0,0,0),array(0,0,0,0,0,0));
      $tos= array(array(0,0,0,0,0,0),array(0,0,0,0,0,0));           // počet osobních
      $xta= array(array(0,0,0,0,0,0),array(0,0,0,0,0,0));           // změny
      $n= $k= 0;
      $AND= $par->akce ? " AND id_akce={$par->akce}" : "";
      $qo= mysql_qry("
        SELECT o.id_osoba,t.role,o.kontakt,r.id_rodina,
          COUNT(DISTINCT r.id_rodina) AS _rodin, COUNT(rt.id_tvori) AS _clenu,
          REPLACE(REPLACE(CONCAT(o.telefon,o.email),' ',''),';',',') AS _kontakt_o,
          IFNULL(MIN(CONCAT(t.role,REPLACE(REPLACE(CONCAT(r.telefony,r.emaily),' ',''),';',','))),'')
            AS _kontakt_r
        FROM osoba AS o
        LEFT JOIN tvori AS t USING(id_osoba)
        LEFT JOIN rodina AS r ON r.id_rodina=t.id_rodina
        LEFT JOIN tvori AS rt ON rt.id_rodina=r.id_rodina
        WHERE o.deleted='' AND IFNULL(r.deleted='',1) $AND
        GROUP BY o.id_osoba
      ");
      while ( $qo && ($o= mysql_fetch_object($qo)) ) {
        $n++;
        if ( $o->_rodin>1 ) {
          $k++;                                 //continue; ??????????????
        }
        $stav= $o->_clenu>1 ? 1 : 0;
        $id_osoba= $o->id_osoba;
        $kontakt= $o->kontakt;
        $r_kontakt= substr($o->_kontakt_r,1);
        $o_kontakt= $o->_kontakt_o;
        //
        if ( !$o->_rodin ) {                                    // x.0    -- nemá rodinu
          $tab[$stav][5]++;
          $tos[$stav][5]+= $kontakt;
          if ( !$kontakt ) {
            $xta[$stav][5]++;
            if ( $update ) $updated+= data_update('osoba',$id_osoba,'1:kontakt');
          }
        }
        elseif ( $o_kontakt=='' && $r_kontakt=='' ) {           // -.-
          $tab[$stav][0]++;
          $tos[$stav][0]+= $kontakt;
          if ( $kontakt ) {
            $xta[$stav][0]++;
            if ( $update ) $updated+= data_update('osoba',$id_osoba,'0:kontakt');
          }
        }
        elseif ( $o_kontakt!='' && $r_kontakt=='' ) {           // x.-
          $tab[$stav][1]++;
          $tos[$stav][1]+= $kontakt;
          if ( !$kontakt ) {
            $xta[$stav][1]++;
            if ( $update ) $updated+= data_update('osoba',$id_osoba,'1:kontakt');
          }
        }
        elseif ( $o_kontakt=='' && $r_kontakt!='' ) {           // -.x
          $tab[$stav][2]++;
          $tos[$stav][2]+= $kontakt;
          if ( $kontakt ) {
            $xta[$stav][2]++;
            if ( $update ) $updated+= data_update('osoba',$id_osoba,'0:kontakt');
          }
        }
        elseif ( $o_kontakt==$r_kontakt ) {                     // x.x
          $tab[$stav][3]++;
          $tos[$stav][3]+= $kontakt;
          if ( $stav==0 ) {
            // pro singla
            if ( !$kontakt ) {
              // vnutíme kontakt jako osobní
              $xta[$stav][3]++;
              if ( $update ) $updated+= data_update('osoba',$id_osoba,'1:kontakt');
            }
            if ( $o->_rodin==1 ) {
              // je-li rodina jednoznačná smažeme kontakt v rodině
              $xta[$stav][3]++;
              if ( $update ) $updated+= data_update('rodina',$o->id_rodina,':telefony,emaily;0:nomaily');
            }
          }
          else if ( $o->_rodin==1 ) {
            // pro člena vícečlenné a jedinečné rodiny smažeme (duplikovaný) kontakt v osobě
            $xta[$stav][3]++;
            if ( $update ) $updated+= data_update('osoba',$id_osoba,':telefon,email;0:kontakt,nomail');
          }
        }
        elseif ( $o_kontakt!=$r_kontakt ) {                     // x.y
          // u odlišného osobního od rodinného dáme přednost osobnímu
          $tab[$stav][4]++;
          $tos[$stav][4]+= $kontakt;
          if ( !$kontakt ) {
            $xta[$stav][4]++;
            if ( $update ) $updated+= data_update('osoba',$id_osoba,'1:kontakt');
          }
        }
        else fce_warning("?");
      }
//                                                           debug($tos);
      // formátování
      $zmen= 0;
      $hr= array('single','člen rodiny');
      $hd= array('-.-','x.-','-.x','x.x','x.y','x.0');
      $hdr= "kontakty $ezer_root";
      $t= "<table class='stat'><tr><th>$hdr</th><th colspan=6>osoba.rodina</th></tr><tr><th></th>";
      foreach($hd as $i=>$clmn) {
        $t.= "<th>$clmn</th>";
      }
      $t.= "<th>&Sigma;</th></tr>";
      foreach($tab as $s=>$row) {
        $t.= "<tr><th>{$hr[$s]}</th>";
        $sum= 0;
        foreach($row as $i=>$clmn) {
          $sum+= $clmn;
          $zmen+= $xta[$s][$i];
          $style= $xta[$s][$i] ? " style='background-color:yellow'" : '';
          $t.= "<td align='right'$style>$clmn</td>";
        }
        $t.= "<th align='right'>$sum</th></tr>";
        $row= $tos[$s];
        $t.= "<tr><th>... osobní</th>";
        $sum= 0;
        foreach($row as $i=>$clmn) {
          $sum+= $clmn;
          $t.= "<td align='right'>$clmn</td>";
        }
        $t.= "<th align='right'>$sum</th></tr>";
      }
      $t.= "</table>";
      $html.= "<br>probráno $n osob, z toho $k je ve více rodinách $t";
      $html.= $update ? "<br> opraveno $updated údajů<br>" : (
              $zmen   ? "<br>provést $zmen změn údajů ve žlutých polích?<br>"
                      : '<br>bez možných automatických úprav, přesto zkusit?<br>');
      break;
    // ---------------------------------------------- osoba: adresy
    // opraví pole osoba.adresa
    case 'adresy+':
      $update= true;
    // zobrazí přehled adres
    case 'adresy':
      //  0   1   2   3   4   5
      // -.- x.- -.x x.x x.y x.0    osobní.rodinná; single=0,clen=1
      $tab= array(array(0,0,0,0,0,0),array(0,0,0,0,0,0));
      $xta= array(array(0,0,0,0,0,0),array(0,0,0,0,0,0));           // změny
      $n= $k= 0;
      $tos= array(array(0,0,0,0,0,0),array(0,0,0,0,0,0));           // počet osobních
      $AND= $par->akce ? " AND id_akce={$par->akce}" : "";
      $qo= mysql_qry("
        SELECT o.id_osoba,o.adresa,r.id_rodina,
          COUNT(DISTINCT r.id_rodina) AS _rodin, COUNT(rt.id_tvori) AS _clenu,
          TRIM(CONCAT(o.ulice,o.psc,o.obec,o.stat)) AS _adresa_o,
          IFNULL(SUBSTR(MIN(CONCAT(t.role,TRIM(CONCAT(r.ulice,r.psc,r.obec,r.stat)))),2),'') AS _adresa_r
        FROM osoba AS o
        LEFT JOIN tvori AS t USING(id_osoba)
        LEFT JOIN rodina AS r ON r.id_rodina=t.id_rodina
        LEFT JOIN tvori AS rt ON rt.id_rodina=r.id_rodina
        WHERE o.deleted='' AND IFNULL(r.deleted='',1) $AND
        GROUP BY o.id_osoba
      ");
      while ( $qo && ($o= mysql_fetch_object($qo)) ) {
        $n++;
        if ( $o->_rodin>1 ) {
          $k++;                                 //continue; ????????????????
        }
        $stav= $o->_clenu>1 ? 1 : 0;            //0: singl, 1: netriviální rodina
        $id_osoba= $o->id_osoba;
        $adresa= $o->adresa;
        $r_adresa= $o->_adresa_r;
        $r_adresa= $r_adresa=="CZ" ? "" : $r_adresa;
        $o_adresa= $o->_adresa_o=="CZ" ? "" : $o->_adresa_o;
        //
        if ( !$o->_rodin ) {                                    // x.0      -- nemá rodinu
          $tab[$stav][5]++;
          $tos[$stav][5]+= $adresa;
          // změny
          $xta[$stav][5]+= !$adresa;
          if ( $update && !$adresa ) {
            if ( $update ) $updated+= data_update('osoba',$id_osoba,'1:adresa');
          }
        }
        elseif ( $o_adresa=='' && $r_adresa=='' ) {             // -.-
          $tab[$stav][0]++;
          $tos[$stav][0]+= $adresa;
          if ( $adresa ) {
            $xta[$stav][0]++;
            if ( $update ) $updated+= data_update('osoba',$id_osoba,'0:adresa');
          }
        }
        elseif ( $o_adresa!='' && $r_adresa=='' ) {             // x.-
          $tab[$stav][1]++;
          $tos[$stav][1]+= $adresa;
          if ( !$adresa ) {
            $xta[$stav][1]++;
            if ( $update ) $updated+= data_update('osoba',$id_osoba,'1:adresa');
          }
        }
        elseif ( $o_adresa=='' && $r_adresa!='' ) {             // -.x
          $tab[$stav][2]++;
          $tos[$stav][2]+= $adresa;
          if ( $adresa ) {
            $xta[$stav][2]++;
            if ( $update ) $updated+= data_update('osoba',$id_osoba,'0:adresa');
          }
        }
        elseif ( $o_adresa==$r_adresa ) {                       // x.x
          $tab[$stav][3]++;
          $tos[$stav][3]+= $adresa;
          if ( $stav==0 ) {
          // pro singla
            if ( !$adresa ) {
              // vnutíme adresu jako osobní
              $xta[$stav][3]++;
              if ( $update ) $updated+= data_update('osoba',$id_osoba,"1:adresa");
            }
            if ( $o->_rodin==1 ) {
              // je-li rodina jedinečná smažeme adresu v rodině
              $xta[$stav][3]++;
              if ( $update ) $updated+= data_update('rodina',$o->id_rodina,':ulice,psc,obec,stat;0:noadresa');
            }
          }
          else if ( $o->_rodin==1 ) {
            // pro člena vícečlenné a jedinečné rodiny smažeme (duplikovanou) adresu v osobě
              $xta[$stav][3]++;
              if ( $update ) $updated+= data_update('osoba',$id_osoba,':ulice,psc,obec,stat;0:adresa,noadresa');
          }
        }
        elseif ( $o_adresa!=$r_adresa ) {                       // x.y
          $tab[$stav][4]++;
          $tos[$stav][4]+= $adresa;
        }
        else fce_warning("?");
      }
//                                                           debug($xta);
      // formátování
      $zmen= 0;
      $hr= array('single','člen rodiny');
      $hd= array('-.-','x.-','-.x','x.x','x.y','x.0');
      $hdr= "adresy $ezer_root";
      $t= "<table class='stat'><tr><th>$hdr</th><th colspan=6>osoba.rodina</th></tr><tr><th></th>";
      foreach($hd as $i=>$clmn) {
        $t.= "<th>$clmn</th>";
      }
      $t.= "<th>&Sigma;</th></tr>";
      foreach($tab as $s=>$row) {
        $t.= "<tr><th>{$hr[$s]}</th>";
        $sum= 0;
        foreach($row as $i=>$clmn) {
          $sum+= $clmn;
          $zmen+= $xta[$s][$i];
          $style= $xta[$s][$i] ? " style='background-color:yellow'" : '';
          $t.= "<td align='right'$style>$clmn</td>";
        }
        $t.= "<th align='right'>$sum</th></tr>";
        $row= $tos[$s];
        $t.= "<tr><th>... osobní</th>";
        $sum= 0;
        foreach($row as $i=>$clmn) {
          $sum+= $clmn;
          $t.= "<td align='right'>$clmn</td>";
        }
        $t.= "<th align='right'>$sum</th></tr>";
      }
      $t.= "</table>";
      $html.= "<br>probráno $n osob, z toho $k je ve více rodinách $t";
      $html.= $update ? "<br> opraveno $updated údajů<br>" : (
              $zmen   ? "<br>provést $zmen změn údajů ve žlutých polích?<br>"
                      : '<br>bez možných automatických úprav, přesto zkusit?<br>');
      break;
    // ---------------------------------------------- osoba: adresa
    // nastaví osoba.adresa=1 pokud je adresa osobní tj. různá od rodinné
    // (pokud je rodina nejednoznačná, zatím nic pak bere se ta s rolí a|b)
    // podle osoba --> tvori.role --> rodina
    case 'adresa':
      $n= 0;
      $AND= $par->akce ? " AND id_akce={$par->akce}" : "";
      $AND.= $par->pobyt ? " AND id_pobyt={$par->pobyt}" : "";
  //     // 1) osobní a rodinná jsou totožné => adresa=rodinná
  //     $qo= mysql_qry("
  //       SELECT id_osoba,adresa,COUNT(id_rodina) AS _rodin,
  //         CONCAT(o.ulice,o.psc,o.obec,o.stat) AS _adresa_o,
  //         CONCAT(r.ulice,r.psc,r.obec,r.stat) AS _adresa_r
  //       FROM osoba AS o
  //       JOIN tvori AS t USING(id_osoba)
  //       JOIN rodina AS r USING(id_rodina)
  //       GROUP BY id_rodina
  //       HAVING _rodin=1 AND _adresa_o!='' AND _adresa_o!='CZ' AND _adresa_o=_adresa_r
  //     ");
  //     while ( $qo && ($o= mysql_fetch_object($qo)) ) {
  //       $r_adresa= $o->ulice,$o->psc,$o->obec,$o->stat
  //       if ( $pouze ) {
  //         $n++;
  //         display("$roles:mysql_qry(\"UPDATE pobyt SET pouze=$pouze WHERE id_pobyt={$p->id_pobyt}\")");
  //       }
  //     }
      $html.= "<br>doplněno $n x osoba.adresa=1";
      break;
    // ---------------------------------------------- pobyt: pouze
    // doplní pouze=1|2 v akcích s nastaveným i0_rodina podle role=a|b
    // podle spolu --> osoba.role
    case 'pouze':
      $n= 0;
      $AND= $par->akce ? " AND id_akce={$par->akce}" : "";
      $AND.= $par->pobyt ? " AND id_pobyt={$par->pobyt}" : "";
      $qp= mysql_qry("
        SELECT CONCAT(pouze,GROUP_CONCAT(role ORDER BY role SEPARATOR '')) AS _roles,id_pobyt,
          a.nazev,YEAR(a.datum_od)
        FROM akce AS a
        JOIN pobyt AS p ON a.id_duakce=p.id_akce
        JOIN spolu AS s USING (id_pobyt)
        JOIN rodina AS r ON r.id_rodina=i0_rodina
        JOIN tvori AS t ON t.id_osoba=s.id_osoba AND t.id_rodina=i0_rodina
        WHERE i0_rodina!=0
        GROUP BY id_pobyt HAVING
          (LEFT(_roles,1)='0' AND LEFT(_roles,3)!='0ab')
          OR (LEFT(_roles,1)='1' AND LEFT(_roles,2)!='1a')
          OR (LEFT(_roles,1)='2' AND LEFT(_roles,2)!='2b')
      ");
      while ( $qp && ($p= mysql_fetch_object($qp)) ) {
        $n++;
      }
      $html.= "<br>zjištěno $n rozporů mezi spolu a pouze";
      break;
    // ---------------------------------------------- pobyt: i0_rodina ... do starých
    // doplní i0_rodina pokud rodina má jméno a je jednoznačná pro všechny osoby pobytu
    //   pokud jich je >1
    // podle spolu --> osoba --> tvori --> rodina.nazev,id_rodina
    case 'i0_rodina':
      $n= 0;
      $AND= $par->akce ? " AND id_akce={$par->akce}" : "";
      $qp= mysql_qry("
        SELECT COUNT(*) AS _ucastniku,COUNT(DISTINCT id_rodina) AS _pocet,id_pobyt,id_rodina
        FROM akce AS a
        JOIN pobyt AS p ON a.id_duakce=p.id_akce
        JOIN spolu AS s USING (id_pobyt)
        JOIN tvori AS t USING(id_osoba)
        JOIN rodina AS r USING(id_rodina)
        WHERE i0_rodina=0 AND r.nazev!='' $AND
        GROUP BY id_pobyt HAVING _ucastniku>1 AND _pocet=1 ");
      while ( $qp && ($p= mysql_fetch_object($qp)) ) {
        $n++;
        mysql_qry("UPDATE pobyt SET i0_rodina={$p->id_rodina} WHERE id_pobyt={$p->id_pobyt}");
      }
      $html.= "<br>doplněno $n x pobyt.i0_rodina";
      break;
    // ---------------------------------------------- osobni_pece
    case 'osobni_pece':
      $html.= "<h3>kopie spolu.pecovane na spolu.pecujici</h3>Zatím ne ...";
  //       SELECT s1.id_spolu,s2.id_spolu
  //       FROM spolu AS s1
  //       JOIN pobyt AS p1 ON p1.id_pobyt=s1.id_pobyt AND p1.id_akce=
  //       JOIN spolu AS s2 ON s2.id_osoba=s1.pecovane
  //       JOIN pobyt AS p2 ON p2.id_pobyt=s2.id_pobyt AND p2.id_akce=
  //       WHERE
  //        id_akce=394 AND s1.pecovane!=0
      break;
    default:
      fce_error("transformaci $cmd neumím");
    }
  }
  return $html;
}
/** ========================================================================================> SYSTEM **/
# ---------------------------------------------------------------------------==> . db2_sys_transform
# transformace na schema 2015
# par.cmd = seznam transformací
# par.akce = id_akce | 0
# par.pobyt = id_pobyt | 0
function db2_sys_transform($par) { trace();
  global $ezer_root, $ezer_path_root;
  $html= '';
  $updated= 0;
  $fs= array(
    // aplikace
    'akce' =>    array('access','id_duakce','id_hlavni'),
    'g_akce' =>  array(         'id_gakce'),
    'cenik' =>   array(         'id_cenik','id_akce'),
    'dar' =>     array('access','id_dar','id_osoba','id_rodina'),
    'dopis' =>   array('access','id_dopis','id_duakce','id_mailist'),
    'join_akce'=>array(         'id_akce'),
    'mailist' => array('access','id_mailist'),
    'mail' =>    array(         'id_mail','id_dopis','id_pobyt','id_clen'),
    'osoba' =>   array('access','id_osoba'),
    'platba' =>  array(         'id_platba','id_osoba','id_rodina','id_duakce','id_pokl'),
    'pobyt' =>   array(         'id_pobyt','id_akce','i0_rodina'),
    'rodina' =>  array('access','id_rodina'),
    'spolu' =>   array(         'id_spolu','id_pobyt','id_osoba','pecovane'),
    'tvori' =>   array(         'id_tvori','id_rodina','id_osoba'),
    // systém
    '_user' =>  array('id_user'),
    //'_track' => array('id_track','klic'), -- zvláštní běh imp_track
  );
  $ds= array(
    // dokumenty s _id^ na konci
    'pobyt' =>  array('modi'),
    // dokumenty odkázané původním jménem s ^ na konci
    'akce' =>   array('copy')
  );
  foreach (explode(',',$par->cmd) as $cmd ) {
    $update= false;
    $limit= "LIMIT 3";
    $db= 'ezer_fa';
    $root= 'fa';
    $ok= 1;
    $html.= "<br><b>$cmd</b>";
    switch ($cmd ) {
    // ---------------------------------------------- import: imp_clear
    // vyčistí databázi ezer_db2, založí uživatele GAN
    case 'imp_clear':
      // vyprázdnění tabulek
      foreach ($fs as $tab => $keys) {
        if ( $ok ) $ok= mysql_qry("TRUNCATE TABLE ezer_db2.$tab");
        if ( $ok ) $ok= mysql_qry("ALTER TABLE ezer_db2.rodina
          CHANGE ulice ulice tinytext COLLATE 'utf8_czech_ci' NOT NULL AFTER fotka,
          CHANGE obec obec tinytext COLLATE 'utf8_czech_ci' NOT NULL AFTER psc");
        if ( $ok ) $html.= "<br>$tab: vymazáno";
      }
      if ( $ok ) $ok= mysql_qry("DROP TABLE IF EXISTS ezer_db2._skill");
      if ( $ok ) $ok= mysql_qry("CREATE TABLE ezer_db2._skill LIKE ezer_ys._skill");
      if ( $ok ) $ok= mysql_qry("INSERT INTO ezer_db2._skill SELECT * FROM ezer_ys._skill");
      if ( $ok ) $html.= "<br>_skill: zkopírováno";
      break;
    // ---------------------------------------------- dokumenty: YS
    // vloží zástupce dokumentů do files/db/... podle files/ys/... (klíče na dvojnásobek+1)
    case 'doc_YS':
      $root= 'ys';
    // ---------------------------------------------- dokumenty: FA
    // vloží zástupce dokumentů do files/db/... podle files/fa/... (klíče na dvojnásobek)
    case 'doc_FA':
      $files= substr($ezer_path_root,0,strrpos($ezer_path_root,'/'))."/files";
      foreach ( $ds as $sub=>$par) {
        list($stg)= $par;
        switch ($stg) {
        case 'modi':                          // dokumenty s modifikovaným klíčem _id^ na konci
          $dir= "$files/$root/$sub";
//                                                         display($dir);
          if ($dh= opendir($dir)) {
            while (($file= readdir($dh)) !== false) {
              if ( $file=='.' || $file=='..')  continue;
              // vyrob soubor s odkazem
              preg_match("@^(.*)_(\d+)$@",$file,$m);
//                                                         debug($m,$file);
              $key= $m[2];
              $key2= $cmd=='doc_FA' ? $key*2 : $key*2+1;
              $path2= "$files/db/$sub/{$m[1]}_$key2^";
//                                                 display("$path2:$file");
              file_put_contents($path2,"$root/$sub/$file");
//               break;
            }
          }
          break;
        case 'copy':                          // dokumenty bez klíče s ^ na konci
          $source= "$files/$root/$sub";
          if ( !file_exists($source) ) break;
          $dest= "$files/db/$sub";
          foreach ( $iterator= new RecursiveIteratorIterator(
              new RecursiveDirectoryIterator($source, RecursiveDirectoryIterator::SKIP_DOTS),
              RecursiveIteratorIterator::SELF_FIRST) as $item ) {
            $subpath= str_replace("\\","/",$iterator->getSubPathName());
            $path= "$dest/$subpath";
            if ($item->isDir() ) {
              if ( !file_exists($path) ) {
                                                                display("mkdir $path");
                mkdir($path);
              }
            }
            else {
                                display("file_put_contents($path^,$root/$sub/$subpath");
              file_put_contents("$path^","$root/$sub/$subpath");
//               break;
            }
          }
          break;
        }
      }
      break;
    // ---------------------------------------------- import: YS
    // provede import z ezer_ys=>ezer_db (klíče na dvojnásobek+1 pro nenulové,access=1)
    case 'imp_YS':
      $db= 'ezer_ys';
      $root= 'ys';
    // ---------------------------------------------- import: FA
    // provede import z ezer_fa=>ezer_db (klíče na dvojnásobek,access=2)
    case 'imp_FA':
      foreach ($fs as $tab => $keys) {
        if ( $ok ) $ok= mysql_qry("DROP TABLE IF EXISTS ezer_db2._tmp_");
        if ( $ok ) $ok= mysql_qry("CREATE TABLE ezer_db2._tmp_ LIKE $db.$tab");
        if ( $ok ) $ok= mysql_qry("INSERT INTO ezer_db2._tmp_ SELECT * FROM $db.$tab");
        if ( $ok ) {
          $updt= ''; $main= '';
          foreach ($keys as $key) {
            if ( $key=='access' ) {
              $updt.= ($cmd=='imp_FA' ? ',access=2':',access=1');
            }
            else {
              $updt.= $cmd=='imp_FA' ? ",$key=$key*2" : ",$key=IF($key,$key*2+1,0)";
              $main= $main ?: $key;
            }
          }
          // aplikace změn
          $updt= substr($updt,1);
          $ok= mysql_qry("UPDATE ezer_db2._tmp_ SET $updt ORDER BY $main DESC");
          $nr= mysql_affected_rows();
        }
        if ( $ok ) $ok= mysql_qry("INSERT INTO ezer_db2.$tab SELECT * FROM ezer_db2._tmp_");
        if ( $ok ) $html.= "<br>$tab: vloženo $nr záznamů";
        mysql_qry("DROP TABLE IF EXISTS ezer_db2._tmp_");
      }
      break;
    // ---------------------------------------------- import: cor_mailist
    // pročištění mailist
    case 'cor_mailist':
      //mysql_qry("DELETE mailist FROM ezer_db2.mailist LEFT JOIN ezer_db2.dopis USING (id_mailist)
      //           WHERE ISNULL(id_dopis)");
      mysql_qry("UPDATE ezer_db2.mailist SET parms=REPLACE(parms,'\"akey\":3','\"akey\":5')");
      mysql_qry("UPDATE ezer_db2.mailist SET parms=REPLACE(parms,'\"akey\":4','\"akey\":6')");
      // 3 testovací mailisty
      $json= '{"ano_akce":{"typy":"1","keys":"1","cas":[{"akey":3}]},'
           . '"ne_akce":0,"ucasti":{"funkce":1,"fce_keys":"3,4","muzi":1,"zeny":1,'
           . '"ucasti":{"ucast":0},"mrop":0,"email":1}}';
      $sql= function ($acc) { return '"'.str_replace('  ',' ',str_replace("\n"," ",
         "SELECT o.access,COUNT(*) AS _ucasti,iniciace,o.id_osoba AS _id,
          CONCAT(prijmeni,' ',jmeno) AS _name,
          IF(o.adresa,o.psc,IFNULL(r.psc,'')) AS _psc,
          IF(o.adresa,o.ulice,IFNULL(r.ulice,'')) AS _ulice,
          IF(o.adresa,o.obec,IFNULL(r.obec,'')) AS _obec,
          IF(o.adresa,o.stat,IFNULL(r.stat,'')) AS _stat,
          IF(o.kontakt,o.email,IFNULL(r.emaily,'')) AS _email,nomail
          FROM pobyt AS p JOIN spolu AS s USING (id_pobyt)
          JOIN osoba AS o ON s.id_osoba=o.id_osoba JOIN akce AS a ON a.id_duakce=p.id_akce
          LEFT JOIN tvori AS t ON t.id_osoba=o.id_osoba LEFT JOIN rodina AS r USING (id_rodina)
          WHERE o.deleted='' AND TRUE AND (IF(o.kontakt,o.email!='',FALSE) OR r.emaily!='') AND
          IFNULL(t.role,'?')!='d' AND p.funkce IN (3,4) AND 1 AND p.id_akce IN
            (SELECT id_duakce FROM akce
            WHERE spec=0 AND access&$acc
            AND spec=0 AND YEAR(datum_od)=YEAR(CURDATE()) AND druh IN (1))
          GROUP BY o.id_osoba
          ORDER BY prijmeni")).'"'; };
      mysql_qry("DELETE FROM ezer_db2.mailist WHERE id_mailist<=3");
      mysql_qry("DELETE FROM ezer_db2.dopis WHERE id_mailist BETWEEN 1 AND 3");
      mysql_qry("INSERT INTO ezer_db2.mailist (id_mailist,access,ucel,parms,sexpr)
         VALUES (1,1,'testovací mailist Setkání','$json',".$sql(1).")");
      mysql_qry("INSERT INTO ezer_db2.mailist (id_mailist,access,ucel,parms,sexpr)
         VALUES (2,2,'testovací mailist Familia','$json',".$sql(2).")");
      mysql_qry("INSERT INTO ezer_db2.mailist (id_mailist,access,ucel,parms,sexpr)
         VALUES (3,3,'testovací mailist společný','$json',".$sql(3).")");
      break;
    // ---------------------------------------------- import: imp_clear_track
    // vyprázdní tabulku ezer_db2._track
    case 'imp_clear_track':
      if ( $ok ) $ok= mysql_qry("TRUNCATE TABLE ezer_db2._track");
      if ( $ok ) $html.= "<br>_track: vyprázdněno";
      break;
    // ---------------------------------------------- import: YS (_track)
    // provede import z ezer_ys=>ezer_db (klíče na dvojnásobek+1 pro vybrané kombinace)
    case 'imp_YS_track':
      $db= 'ezer_ys';
      $root= 'ys';
    // ---------------------------------------------- import: FA (_track)
    // provede import z ezer_fa=>ezer_db (klíče na dvojnásobek pro vybrané kombinace)
    case 'imp_FA_track':
      $tab= '_track';
      if ( $ok ) $ok= mysql_qry("DROP TABLE IF EXISTS ezer_db2._tmp_");
      if ( $ok ) $ok= mysql_qry("CREATE TABLE ezer_db2._tmp_ LIKE $db.$tab");
      if ( $ok ) $ok= mysql_qry("INSERT INTO ezer_db2._tmp_ SELECT * FROM $db.$tab");
      if ( $ok ) {
        // id_track, klic
        $key= 'id_track';
        $updt= $cmd=='imp_FA_track' ? "$key=$key*2" : "$key=IF($key,$key*2+1,0)";
        $key= 'klic';
        $updt.= $cmd=='imp_FA_track' ? ",$key=$key*2" : ",$key=IF($key,$key*2+1,0)";
        $ok= mysql_qry("UPDATE ezer_db2._tmp_ SET $updt ORDER BY id_track DESC");
        // *.r,*.d, tvori, spolu, pobyt
        $key= 'val';
        $updt= $cmd=='imp_FA_track' ? "$key=$key*2" : "$key=IF($key,$key*2+1,0)";
        $ok= mysql_qry("UPDATE ezer_db2._tmp_ SET $updt
          WHERE op='r'
             OR (op='d' AND old!='chlapi')
             OR (kde='spolu' AND fld IN ('id_osoba','id_pobyt','pecovane'))
             OR (kde='pobyt' AND fld IN ('i0_rodina','id_akce'))
             OR (kde='tvori' AND fld IN ('id_rodina','id_osoba'))
          ");
        $nr= mysql_affected_rows();
      }
      if ( $ok ) $ok= mysql_qry("INSERT INTO ezer_db2.$tab SELECT * FROM ezer_db2._tmp_");
      if ( $ok ) $html.= "<br>$root $tab: upraveno $nr záznamů";
      mysql_qry("DROP TABLE IF EXISTS ezer_db2._tmp_");
      break;
    // ---------------------------------------------- import: imp_user
    // vyčistí databázi ezer_db2, založí uživatele GAN a upraví ZMI,HAN,MSM
    case 'imp_user':
      // výmaz GAN/1,2 a ZMI/1
      if ( $ok ) $ok= mysql_qry("DELETE FROM ezer_db2._user
        WHERE abbr='GAN' OR (abbr='ZMI' AND skills LIKE '% y %')");
      // nový uživatel GAN
      if ( $ok ) $ok= mysql_qry("INSERT INTO ezer_db2._user
        (id_user,abbr,username,password,state,org,access,forename,surname,skills) VALUES
        (1,'GAN','gandi','radost','+-Uu',1,3,'Martin','Šmídek',
          'a ah f fa faa faa+ faa:c faan fad fae fam fam famg fams d m mg r sp spk spv y yp yd yaed')");
      //  úprava skill a access pro MSM,HAN,ZMI
      if ( $ok ) $ok= mysql_qry("UPDATE ezer_db2._user SET org=1,access=1,skills=CONCAT('d ',skills)
                                 WHERE abbr='MSM' AND skills NOT LIKE 'd %'");
      if ( $ok ) $ok= mysql_qry("UPDATE ezer_db2._user SET org=2,access=2,skills=CONCAT('d ',skills)
                                 WHERE abbr='HAN' AND skills NOT LIKE 'd %'");
      if ( $ok ) $ok= mysql_qry("UPDATE ezer_db2._user SET org=2,access=3,skills=CONCAT('d ',skills)
                                 WHERE abbr='ZMI' AND skills NOT LIKE 'd %'");
      // vymazat přístup přes IP
      if ( $ok ) $ok= mysql_qry("UPDATE ezer_db2._user SET ips=''");
      // doplnit skill d a sjednotit FA a YS skills
      if ( $ok ) {
        if ( !select('COUNT(*)','ezer_db2._skill',"skill_abbr='d'") ) {
          $ok= mysql_qry("INSERT INTO ezer_db2._skill (skill_abbr, skill_desc) VALUES ('d', 'DB2')");
        }
        $qs= mysql_qry("SELECT skill_abbr, skill_desc FROM ezer_fa._skill");
        while ( $qs && ($s= mysql_fetch_object($qs)) ) {
          if ( !select('COUNT(*)','ezer_db2._skill',"skill_abbr='{$s->skill_abbr}'") ) {
            $ok= mysql_qry("INSERT INTO ezer_db2._skill (skill_abbr, skill_desc)
                            VALUES ('{$s->skill_abbr}', '{$s->skill_desc}')");
          }
        }
      }
      break;
    // ---------------------------------------------- import: imp_cis
    // pročistí číselníky pro DB2, specifické číselníky pro YS2 dáme do tabulky _cis_ys
    case 'imp_cis':
      $nc= $nt= 0;
      // * je třeba rozdělit ys:data*2+1, fa:data*2
      // = jsou stejné
      // 1 použít YS
      // 2 použít FA
      // ! individuální úprava
      // ? promyslet
      $cis_db2_mai= "*db_maily_sql";
      // pro kopii
      $cis_ys= "s_todo_cast,s_todo_email,s_todo_stav,s_todo_typ,evi_vyber_o,evi_vyber_r,db_maily_tab,"
             . "ms_akce_dieta,ms_akce_pfunkce,ms_akce_prednasi,ms_akce_s_role,"
             . "ms_akce_sex,ms_akce_t_role,ms_akce_titul,ms_akce_vzdelani,"
             . "ms_akce_cirkev,ms_akce_dite_kat,ms_cena_vzorec";
      $cis_fa= "ms_akce_clen,ms_akce_platba";
      $cis_xx= array(
                 "ms_akce_funkce"=> array(
                   "ys" => "2",
                   "fa" => "0,1,3,4,5,6,7,8,9,10,12,13,14,99"
                 ),
                 "ms_akce_typ" => array(
                   "tables" => "akce.druh,mailist",
                   "ys" => "1,2,3,5,6,7,8,9,11,17,18,19,20,21,22,23",
                   "fa" => "4,5-24,8-25,9-26,10,12,13,14,15,16"
                 ),
                 "akce_slozka" => array(
                   "ys" => "",
                   "fa" => "1-3"
                 ),
                 "db_maily_sql" => array(
                   "tables" => "dopis.cis_skupina",
                   "ys" => "",
                   "fa" => "1-28,2-29,3-30"
                 )
               );
      // kopie do ezer_db2._cis
      query("TRUNCATE ezer_db2._cis;");
      query("INSERT INTO ezer_db2._cis SELECT * FROM ezer_ys._cis
             WHERE FIND_IN_SET(druh,'$cis_ys') OR (druh='_meta_' AND FIND_IN_SET(zkratka,'$cis_ys'))");
      $nc+= mysql_affected_rows();
      query("INSERT INTO ezer_db2._cis SELECT * FROM ezer_fa._cis
             WHERE FIND_IN_SET(druh,'$cis_fa') OR (druh='_meta_' AND FIND_IN_SET(zkratka,'$cis_fa'))");
      $nc+= mysql_affected_rows();
      // zvláštní úpravy
      if ( !select("COUNT(*)","ezer_db2._cis","id_cis=5803") ) {
      query("INSERT INTO ezer_db2._cis (id_cis,druh,data,zkratka,poradi)
             VALUES ('5803','ms_akce_clen','3','klub','3')");
      $nc+= mysql_affected_rows();
      }
      foreach($cis_xx as $cis=>$desc) {
        // vložení meta
        query("INSERT INTO ezer_db2._cis SELECT * FROM ezer_ys._cis
               WHERE druh='_meta_' AND zkratka='$cis'");
        $nc+= mysql_affected_rows();
        // kopie číselníku
        foreach($desc as $org=>$keys) if ($org=="ys" || $org=="fa") {
          if ( $keys ) {
            foreach(explode(',',$keys) as $key_12) {
              list($key1,$key2)= explode('-',$key_12);
              $xr= mysql_qry("SELECT * FROM ezer_$org._cis WHERE druh='$cis' AND data=$key1");
              $x= mysql_fetch_object($xr);
              $data= $key2 ?: $x->data;
              $id_cis= 100*floor($x->id_cis/100) + $data;
              query("INSERT INTO ezer_db2._cis
                (id_cis,druh,data,hodnota,zkratka,popis,poradi,ikona,barva) VALUES
                ($id_cis,'$x->druh',$data,\"$x->hodnota\",'$x->zkratka','$x->popis',
                 $x->poradi,'$x->ikona','$x->barva')");
              $nc+= mysql_affected_rows();
              // transformace hodnoty v tabulkách
              if ( $key2 && isset($desc['tables']) ) {
                foreach(explode(',',$desc['tables']) as $tab_fld) {
                  if ( $tab_fld=='mailist' ) {
                    $acc= $org=='fa' ? 2 : 1;
                    query("UPDATE ezer_db2.mailist
                           SET parms=REPLACE(parms,'\"keys\":\"$key1\"','\"keys\":\"$key2\"')
                           WHERE access=$acc");
                    $nk= mysql_affected_rows();
                    $html.= "<br>mailist/$org: oprava $key1 na $key2 - $nk x";
                  }
                  else {
                    $nt= 0;
                    list($tab,$fld)= explode('.',$tab_fld);
                    $access= $org=="ys" ? 1 : 2;
                    query("UPDATE ezer_db2.$tab SET $fld=$key2
                           WHERE $fld=$key1 AND access=$access");
                    $nt+= mysql_affected_rows();
                    $html.= "<br>$tab: upraveno $nt záznamů ($org: $fld=$key1=>$key2)";
                  }
                }
              }
            }
          }
          else { // kopie všeho
            query("INSERT INTO ezer_db2._cis SELECT * FROM ezer_$org._cis
                   WHERE FIND_IN_SET(druh,'$cis') ");
            $nc+= mysql_affected_rows();
          }
        }
      }
      $html.= "<br>_cis: upraveno $nc záznamů";
      break;
    default:
      fce_error("transformaci $cmd neumím");
    }
  }
  return $html;
}
# ---------------------------------------------------------------------------==> . datové statistiky
# ----------------------------------------------------------------------------------------- db2_stav
function db2_stav($db) {
  global $ezer_root,$ezer_db,$USER;
  $tabs= array(
    '_user'  => (object)array('cond'=>"deleted=''"      ,'obe'=>1),
    'rodina' => (object)array('cond'=>"deleted=''"      ,'obe'=>1),
    'osoba'  => (object)array('cond'=>"deleted=''"      ,'obe'=>1)
  );
  $html= '';
  // přehled tabulek podle access
  $html= "<h3>Seznam tabulek s rozdělením podle příslušnosti k organizacím</h3>";
  $html.= "<div class='stat'><table class='stat'>";
  $html.= "<tr><th>tabulka</th>
    <th style='background-color:#af8'>Setkání</th>
    <th style='background-color:#acf'>Familia</th>
    <th style='background-color:#aff'>sdíleno</th>
    <th style='background-color:#aaa'>smazáno</th></tr>";
  foreach ($tabs as $tab=>$desc) {
    $html.= "<tr><th>$tab</th>";
    $obe= 0;
    $rt= mysql_qry("
      SELECT access,COUNT(*) AS _pocet FROM ezer_$db.$tab
      WHERE {$desc->cond} GROUP BY access ORDER BY access");
    while ( $rt && ($t= mysql_fetch_object($rt)) ) {
      $html.= "<td style='text-align:right' title='{$t->access}'>{$t->_pocet}</td>";
      if ( $t->access==3 ) $obe= 1;
    }
    if ( !$desc->obe ) {
      $html.= "<td style='text-align:right' title='nemá smysl'>-</td>";
    }
    elseif ( !$obe ) {
      $html.= "<td style='text-align:right' title='3'>0</td>";
    }
    $rt= mysql_qry("
      SELECT COUNT(*) AS _pocet FROM ezer_$db._track
      WHERE op='x' AND kde='$tab' AND old='smazaná kopie' ");
    if ( $rt && ($t= mysql_fetch_object($rt)) ) {
      $html.= "<td style='text-align:right'>{$t->_pocet}</td>";
    }
    $html.= "</tr>";
  }
  $html.= "</table></div>";
  $vidi= array('ZMI','GAN');
  if ( in_array($USER->abbr,$vidi) ) {
    $html.= "<br><hr><h3>Sjednocování podrobněji (informace pro ".implode(',',$vidi).")</h3>";
    $html.= db2_stav_kdo($db,"kdy > '2015-12-01'",
      "Od prosince 2015 - (převážně) sjednocování Setkání & Familia");
    $html.= db2_stav_kdo($db,"kdy <= '2015-12-01'",
      "<br><br>Do prosince 2015 - sjednocení v oddělených databázích");
  }
  // technický stav
  $dbs= array();
  foreach ($ezer_db as $db=>$desc) {
    $dbs[$db]= $desc[5];
  }
  $stav= array(
    "ezer_root"=>$ezer_root,
    "dbs"=>$dbs
  );
                                        debug($stav);
  return $html;
}
function db2_stav_kdo($db,$desc,$tit) {
  // sjednotitelé - výpočet
  $sje= array();
  $rt= mysql_qry("
    SELECT kdo,
      SUM(IF(kde='osoba',IF(op='d',1,-1),0)) AS _osob,
      SUM(IF(kde='rodina',IF(op='d',1,-1),0)) AS _rodin
    FROM ezer_$db._track WHERE op IN ('d','V') AND $desc
    GROUP BY kdo");
  while ( $rt && ($t= mysql_fetch_object($rt)) ) {
    $sje[$t->kdo]['o']+= $t->_osob;
    $sje[$t->kdo]['r']+= $t->_rodin;
  }
  // sjednotitelé - řazení
  uasort($sje,function($a,$b) {
    return $a['o']==$b['o'] ? 0 : ($a['o']<$b['o'] ? 1 : -1);
  });
  // sjednotitelé - zobrazení
  $html= "$tit<br><br>";
  $html.= "<div class='stat'><table class='stat'>";
  $html.= "<tr><th>kdo</th><th>rodin</th><th>osob</th></tr>";
  foreach ($sje as $s=>$or) {
    $html.= "<tr><th>$s</th>";
    $html.= "<td style='text-align:right'>{$or['r']}</td>";
    $html.= "<td style='text-align:right'>{$or['o']}</td>";
    $html.= "</tr>";
  }
  $html.= "</table></div>";
  return $html;
}
# --------------------------------------------------------------------------------==> . testovací db
# --------------------------------------------------------------------------------- db2_copy_test_db
# zkopíruje důležité tabulky z ezer_$db do ezer_$db_test
function db2_copy_test_db($db) {  trace();
  $ok= mysql_qry("SET sql_mode = 'NO_AUTO_VALUE_ON_ZERO'");
  // tabulka¨, která se má jen vytvořit, má před jménem hvězdičku
  $tabs= explode(',',
//     "_user,_skill,"
    "_help,_cis,"
  . "*_touch,_track,*_todo,ezer_doc2,"
  . "akce,cenik,pobyt,spolu,osoba,tvori,rodina,g_akce,join_akce,"
  . "dar,platba,"
  . "dopis,mail,mailist"
  );
  foreach ($tabs as $xtab ) {
    $tab= $xtab;
    if ( $tab[0]=='*' ) $tab= substr($tab,1);
    if ( $ok ) $ok= mysql_qry("DROP TABLE IF EXISTS ezer_{$db}_test.$tab");
    if ( $ok ) $ok= mysql_qry("CREATE TABLE ezer_{$db}_test.$tab LIKE ezer_{$db}.$tab");
    if ( $xtab[0]!='*' )
      if ( $ok ) $ok= mysql_qry("INSERT INTO ezer_{$db}_test.$tab SELECT * FROM ezer_{$db}.$tab");
  }
  ezer_connect("ezer_{$db}");   // jinak zůstane přepnuté na test
  return $ok ? 'ok' : 'ko';
}
# -----------------------------------------------------------------------------------==> . track ops
# --------------------------------------------------------------------------------------- track_like
# vrátí změny podobné předané (stejný uživatel, tabulka, čas +-10s)
function track_like($id) {  trace();
  $ret= (object)array('ok'=>1);
  $ids= $id;
  list($kdo,$kde,$kdy,$klic,$op)= select('kdo,kde,kdy,klic,op','_track',"id_track=$id");
  if ( strpos("uU",$op)===false ) {
    $ret->ok= 0;
    $ret->msg= "Bohužel, vrácení úprav typu '$op' není podporováno";
    goto end;
  }
  // nalezení podobných
  $diff= "10 SECOND";
  $xr= mysql_qry(
    "SELECT COUNT(*) AS _pocet,GROUP_CONCAT(id_track) AS _ids
     FROM _track
     WHERE kdo='$kdo' AND kde='$kde' AND klic='$klic' AND op='$op'
       AND kdy BETWEEN DATE_ADD('$kdy',INTERVAL -$diff) AND DATE_ADD('$kdy',INTERVAL $diff)");
  $x= mysql_fetch_object($xr);
  $ret->ids= $x->_ids;
  if ( $x->_pocet > 10 ) {
    $ret->msg= "pozor je příliš mnoho změn - {$x->_pocet}";
    $ret->ok= 0;
  }
end:
  return $ret;
}
# ------------------------------------------------------------------------------------- track_revert
# pokusí se vrátit učiněné změny - $ids je seznam id_track
function track_revert($ids) {  trace();
  global $USER;
  user_test();
  $now= date("Y-m-d H:i:s");
  $user= $USER->abbr;
  $ret= (object)array('ok'=>1);
  $xr= mysql_qry("SELECT * FROM _track WHERE id_track IN ($ids)");
  while ( $xr && ($x= mysql_fetch_object($xr)) ) {
    $table= $x->kde; $id= $x->klic; $op= $x->op; $fld= $x->fld;
    $old= $x->old; $val= $x->val;
    $ret->tab= $table;
    $ret->klic= $id;
    switch ($op ) {
    case 'd': // ------------------------------- vrácení sjednocení
      if ( $table=='osoba' || $table=='rodina' ) {
        $chngs= explode(';',$old); // seznam změn k vrácení pro id_osoba=$val
        foreach ($chngs as $chng) {
          list($fld,$lst)= explode(':',$chng);
          $_id= $fld=='pobyt' ? 'i0_rodina' : (
                $fld=='mail'  ? 'id_clen'   :  "id_$table");
          switch ($fld) {
          case 'access': // -------------------- zápis o vrácení a obnově
            // obnov smazanou osobu nebo rodinu
            query("UPDATE $table SET deleted='' WHERE id_$table=$val");
            query("INSERT INTO _track (kdy,kdo,kde,klic,fld,op,old,val)
                   VALUES ('$now','$user','$table',$val,'','o','obnovená kopie','$id')");     // o=obnova
            // zapiš info o vrácení jako V
            query("UPDATE $table SET access=$lst WHERE id_$table=$id");
            query("INSERT INTO _track (kdy,kdo,kde,klic,fld,op,old,val)
                   VALUES ('$now','$user','$table',$id,'$table','V','vrácené sjednocení','$val')");  // V=vrácení
            break;
          case 'pobyt':
          case 'mail':
          case 'tvori':
          case 'spolu':
          case 'dar':
          case 'platba':
            if ( $lst ) {
              query("UPDATE $fld SET $_id=$val WHERE id_$fld IN ($lst)");
            }
            break;
          case 'xtvori':
            list($idr,$role)= explode('.',$lst);
            query("INSERT INTO tvori (id_rodina,id_osoba,role) VALUES ($idr,$val,'$role')");
            break;
          default:
            $ret->ok= 0;
            $ret->msg= "Bohužel, vrácení úprav typu '$op/$fld' není podporováno";
          }
        }
      }
      else {
        $ret->ok= 0;
        $ret->msg= "Bohužel, toto sjednocení již nelze vrátit";
      }
      break;
    case 'u': // ------------------------------- vrácení změny
    case 'U':
      $curr= select($fld,$table,"id_$table=$id");
      if ( $curr==$val )
        ezer_qry("UPDATE",$table,$id,array(
          (object)array('fld'=>$fld, 'op'=>'u','val'=>$old,'old'=>$curr)
        ));
      break;
    default:
      $ret->ok= 0;
      $ret->msg= "Vrácení úprav typu '$op' není podporováno";
    }
  }
  return $ret;
}
?>
