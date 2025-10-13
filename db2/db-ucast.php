<?php

# -------------------------------------------------------------------------------------- akce2 zmeny
# vrácení klíčů pobyt u kterých došlo ke změně po daném datu a čase
function akce2_zmeny($id_akce,$h) {  trace();
  $ret= (object)array('errs'=>'','pobyt'=>'','chngs'=>array(),'osoby'=>array());
  // přebrání parametrů
  $time= date_sub(date_create(), date_interval_create_from_date_string("$h hours"));
  $ret->kdy= date_format($time, 'Y-m-d H:i');
  // získání sledovaných klíčů tabulek spolu, osoba, tvori, rodina
  $pobyt= $osoba= $osoby= $rodina= $spolu= $spolu_osoba= $tvori= array();
  $rp= pdo_qry("
    SELECT id_pobyt,id_spolu,o.id_osoba,id_tvori,id_rodina
    FROM pobyt AS p
    JOIN spolu AS s USING(id_pobyt)
    JOIN osoba AS o ON s.id_osoba=o.id_osoba
    LEFT JOIN tvori AS t ON t.id_osoba=o.id_osoba
    LEFT JOIN rodina AS r USING(id_rodina)
    WHERE id_akce=$id_akce
  ");
  while ( $rp && ($p= pdo_fetch_object($rp)) ) {
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
  $rt= pdo_qry("SELECT kde,klic,fld,kdo,kdy FROM _track WHERE kdy>'{$ret->kdy}'");
  while ( $rt && ($t= pdo_fetch_object($rt)) ) {
    $k= $t->klic;
    $pid= 0;
    switch ( $t->kde ) {
    case 'pobyt':  $pid= $k; break;
    case 'spolu':  if ( ($pid= $spolu[$k]) ) $osoby[$spolu_osoba[$k]]= 1; break;
    case 'osoba':  if ( ($pid= $osoba[$k]) ) $osoby[$k]= 1; break;
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
//                                        debug($ret,"$n změn po ... sql_time={$ret->kdy}");
  return $ret;
}
# --------------------------------------------------------------------------------- xx akce_dite_kat
# vrátí ys_akce_dite_kat nebo fa_akce_dite_kat podle akce
function xx_akce_dite_kat($id_akce) {  //trace();
  $org= select1("access","akce","id_duakce=$id_akce");
  return 
      $org==1 ? 'ys_akce_dite_kat' : (
      $org==2 ? 'fa_akce_dite_kat' : (
      $org>=4 ? 'ms_akce_dite_kat' : ''));
}
function akce_test_dite_kat($kat,$narozeni,$id_akce) {  trace();
  $ret= (object)array('ok'=>0,'vek'=>0.0);
  $dite_kat= xx_akce_dite_kat($id_akce);
  $od_do= select1("ikona","_cis","druh='$dite_kat' AND data=$kat"); // věk od-do
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
# --------------------------------------------------------------------------------- ucast2 clipboard
# vrácení mailů dospělých členů rodiny
function ucast2_clipboard($idp) {
  $y= (object)array('clip'=>'','msg'=>'funguje jen pro pobyt manželů s maily');
  $idr= select("i0_rodina","pobyt","id_pobyt=$idp");
  if ( !$idr ) goto end;
  list($n,$emaily)= select("COUNT(DISTINCT role),GROUP_CONCAT(DISTINCT IF(kontakt,email,''))",
      "pobyt JOIN spolu USING (id_pobyt) JOIN osoba USING (id_osoba) JOIN tvori USING (id_osoba)",
      "i0_rodina=$idr AND id_rodina=$idr AND role IN ('a','b') GROUP BY id_rodina");
  if ( $n==2 ) {
    if ( $_SESSION['browser']=='CH') {
      $y->clip= $emaily;
      $y->msg= "osobní maily byly zkopírovány do schránky:<br><br>$emaily<br><br>";
    }
    else {
      $y->msg= "osobní maily manželů (do schránky si zkopíruj):<br><br>$emaily<br><br>";
    }
  }
end:
//                                                        debug($y,$idp);
  return $y;
}
# ------------------------------------------------------------------------------ ucast2_pobyt_access
# ==> . chain rod
# účastníci pobytu a případná rodina budou mít daný access (3)
function ucast2_pobyt_access($idp,$access=3) {
  // změna pro rodinu
  $idr= select("i0_rodina","pobyt","id_pobyt=$idp");
  if ( $idr ) {
    $r_access= select("access","rodina","id_rodina=$idr");
    if ( $r_access!=$access ) {
      ezer_qry("UPDATE",'rodina',$idr,array(
        (object)array('fld'=>'access', 'op'=>'u','val'=>$access,'old'=>$r_access)
      ));
    }
  }
  // změna pro účastníky
  $qo= pdo_qry("SELECT access,id_osoba FROM spolu JOIN osoba USING (id_osoba) WHERE id_pobyt=$idp");
  while ( $qo && ($o= pdo_fetch_object($qo)) ) {
    $ido= $o->id_osoba;
    $o_access= $o->access;
    if ( $o_access!=$access ) {
      ezer_qry("UPDATE",'osoba',$ido,array(
        (object)array('fld'=>'access', 'op'=>'u','val'=>$access,'old'=>$o_access)
      ));
    }
  }
  return 1;
}
# --------------------------------------------------------------------------------- ucast2_chain_rod
# ==> . chain rod
# upozorní na pravděpodobnost duplicity rodiny
function ucast2_chain_rod($idro) { trace();
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
  $qr= pdo_qry("SELECT $flds_r FROM rodina WHERE id_rodina=$idro");
  while ( $qr && ($rx= pdo_fetch_object($qr)) ) {
    $ro= $rx;
    $nazev= $rx->nazev;
    $emaily= $items2array($rx->emaily);
    $telefony= $items2array($rx->telefony);
    $qc= pdo_qry("SELECT $flds_o FROM osoba JOIN tvori USING (id_osoba)
      WHERE id_rodina={$rx->id_rodina}");
    while ( $qc && ($cx= pdo_fetch_object($qc)) ) {
      $ro->cleni[]= $cx;
      $jmena[]= $cx->jmeno;
      if ( $cx->narozeni!='0000-00-00' ) $narozeni[]= $cx->narozeni;
      if ( $cx->kontakt ) {
        foreach($items2array($cx->email) as $em) {
          if ( !in_array($em,$emaily) )
            $emaily[]= $em;
        }
        foreach($items2array($cx->telefon,'^\d') as $tf) {
          if ( !in_array($tf,$telefony) )
            $telefony[]= $tf;
        }
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
  $qr= pdo_qry("SELECT $flds_r FROM rodina
    WHERE nazev='$nazev' AND id_rodina!=$idro AND deleted='' $ruzne");
  while ( $qr && ($rx= pdo_fetch_object($qr)) ) {
    $rs[$rx->id_rodina]= $rx;
    $qc= pdo_qry("SELECT $flds_o FROM osoba JOIN tvori USING (id_osoba)
      WHERE id_rodina={$rx->id_rodina}");
    while ( $qc && ($cx= pdo_fetch_object($qc)) ) {
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
  $orgs= (int)$ro->access;
  foreach ($x as $i=>$xi) {
    $xi->asi.= $xi->svatba   ? 'S' : '';
    $xi->asi.= $xi->bydliste ? 'B' : '';
    $xi->asi.= $xi->cleni    ? 'C' : '';
    $xi->asi.= $xi->narozeni ? 'N' : '';
    $xi->asi.= $xi->kontakty ? 'K' : '';
    if ( !strlen($xi->asi) || $xi->asi=='C' ) { unset($x[$i]); continue; }
    $orgs|= (int)$xi->org;
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
  $flds= "id_osoba,o.access,prijmeni,jmeno,narozeni,kontakt,email,telefon,adresa,o.obec,sex";
  // dotazovaná osoba
  $jmena= $telefony= $emaily= array();
  $narozeni= '';
  $qo= pdo_qry("SELECT $flds FROM osoba AS o WHERE id_osoba=$idoo");
  if ( !$qo ) { $msg= "$idoo není osoba"; goto end; }
  $o= pdo_fetch_object($qo);
  $jmeno= $o->jmeno;
  $prijmeni= $o->prijmeni;
  if ( !trim($prijmeni) ) { goto end; }
  $obec= $o->adresa ? $o->obec : '';
  $emaily= $items2array($ox->email);
  $narozeni= $o->narozeni=='0000-00-00' ? '' : $o->narozeni;
  $narozeni_yyyy= substr($o->narozeni,0,4);
  $narozeni_11= substr($o->narozeni,5,5)=="01-01" ? $narozeni_yyyy : '';
  $sex= $o->sex;
  $emaily= $telefony= array();
  if ( $o->kontakt ) {
    $emaily= $items2array($o->email);
    $telefony= $items2array($o->telefon,'^\d');
  }
//                                                 debug($o,"originál");
  // . vzory faktorů
  // podobné osoby tzn. stejné příjmení
  $qo= pdo_qry("
    SELECT $flds,r.obec,
      SUBSTR(MIN(CONCAT(IF(role='','?',role),id_rodina)),2) AS _kmen
    FROM osoba AS o
      JOIN tvori USING(id_osoba)
      JOIN rodina AS r USING(id_rodina)
    WHERE (prijmeni='{$o->prijmeni}' /*OR rodne='{$o->prijmeni}'*/) AND id_osoba!=$idoo AND o.deleted=''
    GROUP BY id_osoba");
  while ( $qo && ($xo= pdo_fetch_object($qo)) ) {
    $xo_jmeno= trim($xo->jmeno);
//                                                 display($xo_jmeno);
    if ( $jmeno=='' || $xo_jmeno=='' ) continue;
    if ( strpos($xo_jmeno,$jmeno)===false && strpos($jmeno,$xo_jmeno)===false ) continue;
    // vymazání chybných údajů
    if ( !$xo->adresa ) $xo->obec= '?';
    if ( !$xo->kontakt ) $xo->telefon= $xo->email= '?';
    // doplnění rodinných údajů z kmenové rodiny, je-li
    if ( (!$xo->adresa || !$xo->kontakt) && $xo->_kmen) {
      $qr= pdo_qry("
        SELECT obec,telefony,emaily
        FROM rodina AS r WHERE id_rodina={$xo->_kmen}");
      if ( $qr && ($r= pdo_fetch_object($qr)) ) {
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
  // se stejným pohlavím
  $narozeni_od_do= ($narozeni_yyyy-1).','.($narozeni_yyyy).','.($narozeni_yyyy+1);
  if ( $idr ) {
    $qc= pdo_qry("
      SELECT id_osoba,prijmeni,jmeno,narozeni FROM osoba JOIN tvori USING (id_osoba)
      WHERE id_rodina=$idr AND deleted='' AND id_osoba!=$idoo AND sex=$sex
        AND (narozeni='$narozeni' OR DAY(narozeni)=1 AND MONTH(narozeni)=1
          AND YEAR(narozeni) IN ($narozeni_od_do))");
    while ( $qc && ($xc= pdo_fetch_object($qc)) ) {
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
//                                                 display("$ido:{$ox->jmeno}/$idoo:$jmeno: {$ox->narozeni}/$narozeni => {$xi->narozeni}");
    // organizace
    $xi->org= $ox->access;
    // zápis
    $x[$ido]= $xi;
  }
  // míra podobnosti osob
  $nx0= count($x);
  $i0= 0;
  $orgs= (int)$ro->access;
  foreach ($x as $i=>$xi) {
    $xi->asi.= $xi->bydliste ? 'b' : '';
    $xi->asi.= $xi->narozeni ? 'n' : '';
    $xi->asi.= $xi->kontakty ? 'k' : '';
    if ( !strlen($xi->asi) || $xi->asi=='b' ) { unset($x[$i]); continue; }
    $orgs|= (int)$xi->org;
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
    // ujistíme se, že nebyli oznámeni jako různé tzv. _track(klic=idoo,op=r,val=idc)
    $r= select("COUNT(*)","_track","klic=$idoo AND op='r' AND val=$ido");
    if ( !$r ) {
      $dup= $x[$i0]->asi;
      $msg= "$idoo je pravděpodobně ($dup) kopie osoby $ido '$prijmeni $jmeno'"
            . ($xi->org==1 ? " z YS" : ($xi->org==2 ? " z FA" : ''));
      $keys= $i0;
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
/** ------------------------------------------------------------------------------ ucast2 browse_ask **/
# BROWSE ASK
# obsluha browse s optimize:ask
#                                       !!! při změně je třeba ošetřit použití v sestavách
# x->order= {a|d} polozka
# x->show=  {polozka:[formát,vzor/1,...],...} pro položky s neprázdným vzorem
#                                             kde formát=/ = # $ % @ * .
# x->cond= podmínka   - pokud obsahuje /*duplicity*/ přidá se sloupec _dup
#                       pokud obsahuje /*dokumenty*/ přidá se do sloupce _docs 'd'
#                       pokud obsahuje /*css*/ bude se barvit _nazev,cleni.jmeno,rodiny
# -- x->atr=  pole jmen počítaných atributů:  [_ucast]
# pokud je tisk=true jsou oddělovače řádků '≈' (oddělovač sloupců zůstává '~')
function ucast2_browse_ask($x,$tisk=false) {
  global $test_clmn,$test_asc, $y;
  // ofsety v atributech členů pobytu - definice viz níže
  global $i_osoba_jmeno, $i_osoba_vek, $i_osoba_role, $i_osoba_prijmeni, $i_adresa, 
      $i_osoba_kontakt, $i_osoba_telefon, $i_osoba_email, $i_osoba_note, $i_key_spolu, 
      $i_spolu_note, $i_osoba_obcanka, $i_spolu_dite_kat, $i_osoba_dieta, $i_osoba_geo,
      $i_spolu_cenik2, $i_spolu_role;
  $i_osoba_jmeno=     4;
  $i_osoba_vek=       6;
  $i_osoba_role=      9;
  $i_osoba_prijmeni= 15;
  $i_adresa=         18;
  $i_osoba_kontakt=  23;
  $i_osoba_telefon=  24;
  $i_osoba_email=    26;
  $i_osoba_obcanka=  32;
  $i_osoba_dieta=    40;
  $i_osoba_note=     42;
  $i_osoba_geo=      44;
  $i_key_spolu=      45;
  $i_spolu_role=     47;
  $i_spolu_dite_kat= 48;
  $i_spolu_note=     49;
  $i_spolu_cenik2=   57; //  kat_nocleh, kat_dny, kat_porce, kat_dieta

  $delim= $tisk ? '≈' : '~';
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
  $neucasti= select1("GROUP_CONCAT(data)",'_cis',"druh='ms_akce_funkce' AND ikona=1");
  foreach(explode(',','cmd,rows,quiet,key_id,oldkey') as $i) {
    $y->$i= isset($x->$i) ? $x->$i : '';
  }
  switch ($x->cmd) {
  case 'browse_load':  # -----------------------------------==> . browse_load
  default:
    # vnořené SQL definující @akce, @soubeh, @app
    if ( $x->sql ) 
      pdo_qry($x->sql);
    else 
      fce_error("browse_load - missing kontext");
    $pobyt= array();              // $pobyt[id_pobyt]             vše
    $skup= array();               // $skup[skupina]               seznam id_pobyt
    $osoba= array();              // $osoba[id_osoba]             atributy osob na akci
    $cleni= "";
    $osoby= "";
    $rodina= array();             // $rodina[id_rodina]           atributy rodin na akci
    $rodina_pobyt= array();       // $rodina[i0_rodina]=id_pobyt  pobyt rodiny (je-li rodinný)
    $rodiny= "";
    $spolu= array();              // $spolu[id_osoba]             id_pobyt
    # ladění
    $AND= "";
//     $AND= "AND p.id_pobyt IN (44285,44279,44280,44281) -- prázdná rodina a pobyt";
//     $AND= "AND p.id_pobyt IN (55772,55689) -- test";
//     $AND= "AND p.id_pobyt IN (43387,32218,32024) -- test";
//     $AND= "AND p.id_pobyt IN (43113,43385,43423) -- test Šmídkovi+Nečasovi+Novotní/LK2015";
//     $AND= "AND p.id_pobyt IN (43423) -- test Novotní/LK2015";
//     $AND= "AND p.id_pobyt IN (60350) -- Kordík";
//     $AND= "AND p.id_pobyt IN (20488,20344) -- Bajerovi a Kubínovi";
//     $AND= "AND p.id_pobyt IN (60594,60192) -- Němečkovi,Sapákovi";
//     $AND= "AND p.id_pobyt IN (60192) -- Sapákovi";
    # pro browse_row přidáme klíč
    if ( isset($x->subcmd) && $x->subcmd=='browse_row' ) {
      $AND= "AND p.id_pobyt={$y->oldkey}";
    }
    # duplicity, dokumenty, css?
    $duplicity= strstr($x->cond,'/*duplicity*/') ? 1 : 0;
    $dokumenty= strstr($x->cond,'/*dokumenty*/') ? 1 : 0;
    $barvit=    strstr($x->cond,'/*css*/')       ? 1 : 0;
    # podmínka
    $cond= $x->cond ?: 1;
    # atributy akce
    $qa= pdo_qry("
      SELECT @akce,@soubeh AS soubeh,@app,druh IN (1,2) AS _ms,
        datum_od,DATEDIFF(a.datum_do,a.datum_od)+1 AS dnu,ma_cenik,ma_cenu,cena
      FROM akce AS a
      WHERE a.id_duakce=@akce ");
    $akce= pdo_fetch_object($qa);
    # atributy pobytu
    $cond_p= str_replace("role IN ('a','b')","1",$cond);
    $ms1= $akce->_ms ? ",IFNULL(_ucasti._n,0)+IFNULL(r.r_ms,0) AS x_ms" : '';
    $ms2= $akce->_ms ? "
      LEFT JOIN (SELECT COUNT(*) AS _n,px.i0_rodina
        FROM pobyt AS px
        JOIN akce AS ax ON ax.id_duakce=px.id_akce
        WHERE ax.datum_od<='{$akce->datum_od}' AND ax.druh=1 AND ax.spec=0 AND ax.zruseno=0
          AND px.funkce NOT IN ($neucasti)
        GROUP BY  px.i0_rodina
      ) AS _ucasti ON _ucasti.i0_rodina=p.i0_rodina AND p.i0_rodina
    " : '';
    $uhrada1= $akce->ma_cenik==2 // cením Domu setkání
        ? "IFNULL(SUM(castka),0)"
        : "IFNULL(SUM(u_castka),0)";
    $uhrada2= $akce->ma_cenik==2 // cením Domu setkání
        ? "LEFT JOIN platba AS u ON id_pob=id_pobyt"
        : "LEFT JOIN uhrada AS u USING (id_pobyt)";
    $qp= pdo_qry("
      SELECT p.*,$uhrada1 AS uhrada,
        FLOOR(IFNULL(id_prihlaska,0)/10) AS id_prihlaska,IFNULL(id_prihlaska,0)%10 AS prijata $ms1
      FROM pobyt AS p
      $uhrada2
      LEFT JOIN rodina AS r ON r.id_rodina=p.i0_rodina
      -- LEFT JOIN prihlaska AS pr USING (id_pobyt)
      LEFT JOIN (
        SELECT MAX(id_prihlaska*10+IF(prijata,1,0)) AS id_prihlaska,id_pobyt
        FROM prihlaska GROUP BY id_pobyt) AS pr USING (id_pobyt)
      $ms2
      WHERE $cond_p $AND
      GROUP BY p.id_pobyt
    ");
    while ( $qp && ($p= pdo_fetch_object($qp)) ) {
      $pobyt[$p->id_pobyt]= $p;
      $i0r= $p->i0_rodina;
      if ( $i0r ) {
        $rodina_pobyt[$i0r]= $p->id_pobyt;
        $pobyt[$p->id_pobyt]->access= $p;
        if ( !strpos(",$rodiny,",",$i0r,") )
          $rodiny.= ",$i0r";
      }
    }
//                                                         debug($pobyt[59518],"pobyt");
//                                                         debug($rodina_pobyt[2473],"rodina_pobyt");
    # seznam účastníků akce - podle podmínky
    $qu= pdo_qry("
      SELECT GROUP_CONCAT(id_pobyt) AS _ids_pobyt,s.*,o.narozeni,
        MIN(CONCAT(IF(IFNULL(role,'')='','?',role),id_rodina)) AS _role,o_umi,prislusnost
      FROM osoba AS o
      JOIN spolu AS s USING (id_osoba)
      JOIN pobyt AS p USING (id_pobyt)
      -- LEFT JOIN tvori AS t USING (id_osoba)
      LEFT JOIN tvori AS t ON t.id_osoba=s.id_osoba AND t.id_rodina=p.i0_rodina
      WHERE o.deleted='' AND $cond $AND
      GROUP BY id_osoba
    ");
    while ( $qu && ($u= pdo_fetch_object($qu)) ) {
      $cleni.= ",{$u->id_osoba}";
      $idr= substr($u->_role,1);
      if ( $idr && !strpos(",$rodiny,",",$idr,") )
        $rodiny.= ",$idr";
      foreach (explode(',',$u->_ids_pobyt) as $idp) {
        $pobyt[$idp]->cleni[$u->id_osoba]= $u;
      }
      $spolu[$u->id_osoba]= $u->id_pobyt;
      // doplnění osobního umí - malým
      $pobyt[$u->id_pobyt]->x_umi= '';
      if ( isset($u->o_umi) && $u->o_umi ) {
        $pobyt[$u->id_pobyt]->x_umi.= strtolower($umi($u->o_umi));
      }
    }
    $osoby.= $cleni;
    # seznam rodinných příslušníků
    $qp= pdo_qry("
      SELECT id_pobyt,id_rodina,id_tvori,id_osoba,role,o_umi,o.narozeni
      FROM pobyt AS p
      JOIN tvori AS t ON t.id_rodina=p.i0_rodina
      JOIN osoba AS o USING(id_osoba)
      WHERE o.deleted='' AND $cond $AND
    ");
    while ( $qp && ($p= pdo_fetch_object($qp)) ) {
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
    $qr= pdo_qry("
      SELECT r.*,IF(g.stav=1,'ok','') AS _geo  
      FROM rodina AS r 
      LEFT JOIN rodina_geo AS g USING(id_rodina)
      WHERE deleted='' AND id_rodina IN (0$rodiny)");
    while ( $qr && ($r= pdo_fetch_object($qr)) ) {
      $r->datsvatba= sql_date_year($r->datsvatba);                  // svatba d.m.r
      $r->r_geo_ok= $r->_geo;                                       // ok|
      if ( $r->r_umi && $rodina_pobyt[$r->id_rodina] ) {
        // umí-li něco rodina a je na pobytu - velkým
        $pobyt[$rodina_pobyt[$r->id_rodina]]->x_umi=
          strtoupper($umi($r->r_umi)).' '.$pobyt[$rodina_pobyt[$r->id_rodina]]->x_umi;
      }
      $rodina[$r->id_rodina]= $r;
    }
    # atributy osob
    $qo= pdo_qry("
      SELECT o.*,IF(g.stav=1,'ok','') AS _geo 
      FROM osoba AS o 
      LEFT JOIN osoba_geo AS g USING(id_osoba)
      WHERE deleted='' AND id_osoba IN (0$osoby)");
    while ( $qo && ($o= pdo_fetch_object($qo)) ) {
      $o->access_web= (int)$o->access | ($o->web_zmena=='0000-00-00' ? 0 : 16);
      $osoba[$o->id_osoba]= $o;
    }
    # seznam rodin osob
    $css= $barvit
        ? "IF(r.access=1,':ezer_ys',IF(r.access=2,':ezer_fa',IF(r.access=3,':ezer_db','')))" : "''";
    $qor= pdo_qry("
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
    while ( $qor && ($or= pdo_fetch_object($qor)) ) {
      if ( !isset($osoba[$or->id_osoba]) ) $osoba[$or->id_osoba]= (object)array('_fotky'=>0);
      $osoba[$or->id_osoba]->_rody= $or->_rody;
      $kmen= $or->_kmen;
      $osoba[$or->id_osoba]->_kmen= $kmen;
      if ( !isset($osoba[$or->id_osoba]->_rfotky) )
        $osoba[$or->id_osoba]->_rfotky= 0;
      $osoba[$or->id_osoba]->_rfotky+= $or->_rfotky;
      foreach (explode(',',$or->_rody) as $rod) {
        list($role,$idr,$css)= explode(':',$rod);
        if ( !isset($rodina[$idr]) ) {
          # doplnění (potřebných) rodinných údajů pro kmenové rodiny
//                                                         display("{$or->id_osoba} - $kmen");
          $qr= pdo_qry("
            SELECT * -- id_rodina,nazev,ulice,obec,psc,stat,telefony,emaily
            FROM rodina AS r WHERE id_rodina=$idr");
          while ( $qr && ($r= pdo_fetch_object($qr)) ) {
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
           . "keys_rodina='',id_prihlaska,prijata,c_suma,rozpis_poslan,platba=uhrada,potvrzeno,x_ms,xfunkce=funkce,"
           . "funkce,xhnizdo=hnizdo,hnizdo,skupina,xstat,dluh,web_color");
//           . "funkce,xhnizdo=hnizdo,hnizdo,skupina,xstat,dluh,web_changes,web_color");
//           . "keys_rodina='',c_suma,platba,potvrzeno,x_ms,xfunkce=funkce,funkce,xhnizdo=hnizdo,hnizdo,skupina,dluh,web_changes");
    $fakce= ucast2_flds("dnu,datum_od");
    $frod=  ucast2_flds("fotka,r_access=access,p_access,r_spz=spz,"
//    $frod=  ucast2_flds("fotka,r_access=access,p_access_web,r_access_web=access_web,r_spz=spz,"
          . "r_svatba=svatba,r_datsvatba=datsvatba,r_rozvod=rozvod,"
          . "r_ulice=ulice,r_psc=psc,r_obec=obec,r_stat=stat,r_geo_ok,"
          . "r_telefony=telefony,r_emaily=emaily,r_ms,r_umi,r_note=note");
    $fpob2= ucast2_flds("p_poznamka=poznamka,p_pracovni=pracovni,pokoj,budova,prednasi,luzka,pristylky,kocarek,pocetdnu"
          . ",strava_cel,strava_cel_bm,strava_cel_bl,strava_pol,strava_pol_bm,strava_pol_bl,"
          . "c_nocleh=platba1,c_strava=platba2,c_program=platba3,c_sleva=platba4,"
          . "v_nocleh=vratka1,v_strava=vratka2,v_program=vratka3,v_sleva=vratka4," /*datplatby,*/
          . "cstrava_cel,cstrava_cel_bm,cstrava_cel_bl,cstrava_pol,cstrava_pol_bm,cstrava_pol_bl,"
          . "svp,zpusobplat,naklad_d,poplatek_d,platba_d,potvrzeno_d"
          . ",zpusobplat_d,datplatby_d,ubytovani,cd,avizo,sleva,sleva_zada,sleva_duvod"
          . ",vzorec,duvod_typ,duvod_text,x_umi");
    //      id_osoba,jmeno,_vek,id_tvori,id_rodina,role,_rody,rc,narozeni,web_souhlas
    $fos=   ucast2_flds("umrti,prijmeni,rodne,sex,adresa,ulice,psc,obec,stat,kontakt,telefon,nomail"
          . ",email,gmail"
          . ",iniciace,firming,uvitano,clen,obcanka,rc_xxxx,cirkev,vzdelani,titul,zamest,zajmy,jazyk,dieta"
          . ",aktivita,note,_kmen,_geo");
    $fspo=  ucast2_flds("id_spolu,_barva,s_role,dite_kat,poznamka,pecovane,pfunkce,pece_jm,pece_id"
          . ",o_umi,prislusnost,skupinka,"
        . "kat_nocleh,kat_dny,kat_porce,kat_dieta");

    # 1. průchod - kompletace údajů mezi pobyty
    $skup= array();
    foreach ($pobyt as $idp=>$p) {
      if ( !$p->cleni || !count($p->cleni) ) continue;
      # seřazení členů podle přítomnosti, role, věku
      uasort($p->cleni,function($a,$b) {
        $arole= isset($a->role) ? $a->role : '';
        $brole= isset($b->role) ? $b->role : '';
        $wa= isset($a->id_spolu) && $a->id_spolu==0 ? 4 : ( $arole=='a' ? 1 : ( $arole=='b' ? 2 : 3));
        $wb= isset($b->id_spolu) && $b->id_spolu==0 ? 4 : ( $brole=='a' ? 1 : ( $brole=='b' ? 2 : 3));
        return $wa == $wb ? ($a->narozeni==$b->narozeni ? 0 : ($a->narozeni > $b->narozeni ? 1 : -1))
                          : ($wa==$wb ? 0 : ($wa > $wb ? 1 : -1));
      });
      # skupinky
      if ( $p->skupina ) {
        $skup[$p->skupina][]= $idp;
      }
      # osobní pečování
      foreach ($p->cleni as $ido=>$s) {
        if ( isset($s->id_spolu) && $s->id_spolu && ($idop= $s->pecovane) ) {
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
//      $p-access_web= $p->web_zmena=='0000-00-00' || $p->prijata>=0 ? 0 : 16;
//      $p_access_web= 0;
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
      if ( $p->cleni && count($p->cleni) ) {
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
            if ( isset($o->rfotka) && $o->fotka ) $o_fotek++;
            if ( isset($o->_rfotky) && $o->_rfotky ) $r_fotek++;
            # spočítání účastníků kvůli platbě
            $clenu++;
            # první 2 členi na pobytu
            if ( !$_ido1 )
              $_ido1= $ido;
            elseif ( !$_ido2 )
              $_ido2= $ido;
            # výpočet jmen pobytu
            $_jmena.= str_replace(' ','-',trim($o->jmeno?:'?'))." ";
            if ( !$idr ) {
              # výpočet názvu pobyt
              $prijmeni= $o->prijmeni;
              if ( !in_array(trim($prijmeni),$nazev) ) $nazev[]= trim($prijmeni);
            }
            # barva
            if ( !isset($s->_barva) )
              $s->_barva= isset($s->id_tvori) && $s->id_tvori ? 1 : 2; // barva: 1=člen rodiny, 2=nečlen
            # barva nerodinného pobytu
            $p_access|= (int)$o->access;
//            $p_access_web|= (int)$o->access; //(int)$o->access_web;
//            display("$o->id_osoba: $p-access_web|= (int)$o->access_web");
            if (!$p->xstat && $o->stat!='CZ') $p->xstat= $o->prislusnost;
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
          $sid_tvori= isset($s->id_tvori) ? $s->id_tvori : 0;
          $sid_rodina= isset($s->id_rodina) ? $s->id_rodina : 0;
          $srole= isset($s->role) ? $s->role : '';
          $cleni.= "$del$ido~$keys~$o->access~$o->access_web~$jmeno~$dup~$vek~$sid_tvori~$sid_rodina~$srole";
          $cleni.= '~'.rodcis($o->narozeni,$o->sex);
          $del= $delim;
          # ==> .. rodiny a kmenová rodina
          $rody= isset($o->_rody) ? explode(',',$o->_rody) : array();
          $r= "-:0:nerodina"; $kmen= '';
          foreach($rody as $rod) {
            list($role,$ir,$access)= explode(':',$rod);
            $naz= $rodina[$ir]->nazev;
            $kmen= $kmen ? ($role=='a' || $role=='b' ? $naz : $kmen) : $naz;
//                                                 display("$o->jmeno/$role: $kmen ($naz,$ir)");
            $r.= ",$naz:$ir:$access";
          }
          $cleni.= "~$r";                                           // rody
          $id_kmen= isset($o->_kmen) && $o->_kmen ? $o->_kmen : 0;
          $o->_kmen= "$kmen/$id_kmen";
          $cleni.= "~" . sql_date_year($o->narozeni);                   // narozeniny d.m.r
          $cleni.= "~" . sql_date1($o->web_souhlas);                // souhlas d.m.r
          # doplnění textů z kmenové rodiny pro zobrazení rodinných adres (jako disabled)
//                                                 debug($o,"browse - o");
//                                                 debug($rodina[$id_kmen],"browse - kmen=$id_kmen");
          if ( !$o->adresa && $id_kmen ) {
            $o->ulice= "®".$rodina[$id_kmen]->ulice;
            $o->psc=   "®".$rodina[$id_kmen]->psc;
            $o->obec=  "®".$rodina[$id_kmen]->obec;
            $o->stat=  "®".$rodina[$id_kmen]->stat;
          }
          if ( !$o->kontakt && $id_kmen  ) {
            $o->email=   "®".$rodina[$id_kmen]->emaily;
            $o->telefon= "®".$rodina[$id_kmen]->telefony;
          }
          # informace z osoba
          foreach($fos as $f=>$filler) {
            $cleni.= "~{$o->$f}";
          }
          # informace ze spolu
          foreach($fspo as $f=>$filler) {
            $cleni.= isset($s->$f) ? "~{$s->$f}" : '~';
          }
        }
      }
  problem:
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
      // pokud není cena předepsána individuálně, podívej se na nastavení akce
      if ($p->c_suma==0 && $akce->ma_cenu)
        $p->c_suma= $clenu * $akce->cena;
      $p->dluh= $p->funkce==99 ? 0 : (
                $akce->soubeh==1 && $akce->ma_cenik
        ? ( $p->c_suma == 0 ? 2 : ( $p->c_suma > $p->uhrada ? 1 : 0 ) )
        // není to pečoun a není to souběžná akce
        : ( $akce->ma_cenik
          ? ( $platba1234 == 0 ? 2 : ( $platba1234 > $p->uhrada ? 1 : 0) )
          : ( $akce->ma_cenu 
            ? ( ($platba1234 ?: $clenu*$akce->cena) > $p->uhrada ? 1 : 0) : 0 )
          ));
      // ezer_cms3: web_changes= 1/2 pro INSERT/UPDATE pobyt a spolu | 4/8 pro INSERT/UPDATE osoba
      // prihlaska: web_changes= 1/2 pro INS/UPD pobyt+spolu | 4/8 pro INS/UPD osoba | 16/32 pro INS/UPD rodina,tvori
      $p->web_color= $p->web_changes&4 ? 2 : ($p->web_changes ? 1 : 0);
//      $p->web_color= $p-web_changes;
      $p->web_changes= 0;
      if ($p->id_prihlaska && substr($akce->datum_od,0,4)<2025) $p->prijata= 1;
      if ($p->prijata==0) $p->web_color+= 4;
//                                   if ($idp==69706) { debug($p); }
      # pobyt I
      foreach($fpob1 as $fz=>$fp) { $z->$fz= isset($p->$fp) ? $p->$fp : ''; }
      # akce
      foreach($fakce as $fz=>$fp) { $z->$fz= isset($akce->$fp) ? $akce->$fp : ''; }
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
      $z->_dupl= 0;
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
        $z->_dupl= $_keys ? 1 : 0;
      }
      # rodina
      foreach($frod as $fz=>$fr) { $z->$fz= isset($rodina[$idr]->$fr) ? $rodina[$idr]->$fr : ''; }
      $z->r_access= intval($z->r_access);
      # ... oprava obarvení
      if ( $p_access )
        $z->r_access|= (int)$p_access;
//      $z->r _access_web= !$idr ? 0 
//          : (int)$rodina[$idr]->access | ($rodina[$idr]->web_zmena=='0000-00-00' ? 0 : 16);
      $z->p_access= $p_access | (!$idr ? 0 : (int)$rodina[$idr]->access);
//      $z->p_access= (int)$p_access_web | (int)$z_r_access_web;
//      display("$z->r _access_web= !$idr ? 0 : (int){$rodina[$idr]->access} ; $z->p_access_web= (int)$p-access_web | (int)$z->r _access_web");
      # členové
      $z->r_cleni= $cleni;
      # pobyt II
      foreach($fpob2 as $fz=>$fp) { $z->$fz= $p->$fp; }
      $z->vratka= $p->vratka1 + $p->vratka2 + $p->vratka3 + $p->vratka4;
      $z->key_spolu= 0;
      $z->ido1= $_ido1 ?: $_ido01;
      $z->ido2= $_ido2; // ?: $_ido02;
//                                                 display("$idr {$z->ido1} + {$z->ido2}");
//      $z->datplatby= sql_date1($z->datplatby);                   // d.m.r
//      $z->datplatby_d= sql_date1($z->datplatby_d);               // d.m.r
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
        $skupinka= array();
        foreach($skup[$sk] as $ip) {
          $skupinka[]= array($ip,$zz[$ip]->_nazev,$zz[$ip]->funkce==1?1:0);
        }
        usort($skupinka,function($a,$b){return $b[2]-$a[2];});
        foreach($skupinka as $par) {
          $s.= "$del$par[0]~$par[1]";
          $del= $delim;
        }
      }
      if ( !isset($zz[$idp]) ) $zz[$idp]= (object)array();
      $zz[$idp]->skup= $s;
    }
    # případný výběr - zjednodušeno na show=[*,vzor]
    if ( isset($x->show) ) foreach ( $x->show as $fld => $show) {
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
      $numeric= in_array($test_clmn,array('skupina','x_ms','vratka','c_suma','platba'));
      if ( $numeric ) {
//                                         display("usort $test_clmn $test_asc/numeric");
        usort($zz,function($a,$b) {
          global $test_clmn,$test_asc;
          $c= $a->$test_clmn == $b->$test_clmn ? 0 : ($a->$test_clmn > $b->$test_clmn ? 1 : -1);
          return $test_asc * $c;
        });
      }
      else {
        $collator= new Collator('cs_CZ'); // Nastavení českého jazyka
        usort($zz,function($a,$b) use ($collator) {
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
            $c= $test_asc * $collator->compare($a->$test_clmn,$b->$test_clmn);
          }
          return $c;
        });
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
//                                                 debug($pobyt[60350],'pobyt');
//                                                 debug($rodina,'rodina');
//                                                 debug($osoba[7039],'osoba');
//                                                 debug($y->values);
  return $y;
}
# dekódování seznamu položek na pole ...x,y=z... na [...x=>x,y=>z...]
function ucast2_flds($fstr) {
  $fp= array();
  foreach(explode(',',$fstr) as $fzp) {
    list($fz,$f)= explode('=',$fzp.'=');
    $fp[$fz]= $f ?: $fz ;
  }
  return $fp;
}
# ========================================================================================> . platby
# záložka Platba za akci
# ------------------------------------------------------------------------------------- akce2 uhrada
//# transformace osoba.platba* --> uhrada
//function akce2_uhrada() {  trace();
//  query("TRUNCATE TABLE uhrada");
//  query("INSERT INTO uhrada (id_pobyt,u_poradi,u_castka,u_datum,u_zpusob,u_stav,u_za) 
//  /*ok*/ SELECT id_pobyt,0,platba,datplatby,zpusobplat,IF(potvrzeno=1,3,IF(avizo=1,1,2)),0
//      FROM pobyt WHERE platba!=0");
//  query("INSERT INTO uhrada (id_pobyt,u_poradi,u_castka,u_datum,u_zpusob,u_stav,u_za) 
//  /*ok*/ SELECT id_pobyt,IF(platba=0,0,1),platba_d,datplatby_d,zpusobplat_d,IF(potvrzeno=1,3,2),1
//      FROM pobyt WHERE platba_d!=0");
//}
# -------------------------------------------------------------------------- akce2 platba_prispevek1
# členské příspěvky - zjištění zda jsou dospělí co jsou na pobytu členy a mají-li zaplaceno
function akce2_platba_prispevek1($id_pobyt) {  trace();
  $ret= (object)array('msg'=>'nejsou členy','platit'=>0);
  // jsou členy?
  $cleni= 0;
  $qp= "SELECT COUNT(*) AS _jsou, GROUP_CONCAT(jmeno) AS _jmena
        FROM pobyt AS p
        JOIN spolu AS s USING(id_pobyt)
        JOIN osoba AS o ON o.id_osoba=s.id_osoba
        LEFT JOIN dar AS d ON d.id_osoba=o.id_osoba AND d.deleted=''
        WHERE id_pobyt=$id_pobyt AND ukon='c'
        GROUP BY id_pobyt ";
  $rp= pdo_qry($qp);
  if ( $rp && $p= pdo_fetch_object($rp) ) {
    $cleni= $p->_jsou;
    $ret->msg= $p->_jmena." jsou členy ";
  }
  if ( $cleni ) {
    $qp= "SELECT COUNT(*) AS _maji, MAX(dat_do) AS _do, GROUP_CONCAT(jmeno) AS _jmena
          FROM pobyt AS p
          JOIN spolu AS s USING(id_pobyt)
          JOIN osoba AS o ON o.id_osoba=s.id_osoba
          LEFT JOIN dar AS d ON d.id_osoba=o.id_osoba AND d.deleted=''
          WHERE id_pobyt=$id_pobyt AND ukon='p' AND YEAR(dat_do)>=YEAR(NOW()) 
          GROUP BY id_pobyt";
    $rp= pdo_qry($qp);
    if ( $rp && $p= pdo_fetch_object($rp)) {
      $jmena= $p->_jmena;
      if ( $p->_maji && $p->_maji==$cleni ) {
        $ret->platit= 0;
        $ret->msg.= "a mají zaplaceno do ".sql_date1($p->_do);
      }
      elseif ( $p->_maji ) { // < cleni
        $kolik= 
        $ret->platit= 1;
        $ret->msg.= "ale jen $jmena má zaplaceno do ".sql_date1($p->_do)
            .". Doplatí to nyní na akci do pokladny?";
      }
      else {
        $ret->platit= 1;
        $ret->msg.= "a nemají letos zaplaceno, zaplatí nyní na akci do pokladny?";
      }
    }
  }
  return $ret;
}
# -------------------------------------------------------------------------- akce2 platba_prispevek2
# členské příspěvky vložení platby do dar
function akce2_platba_prispevek2($id_pobyt) {  trace();
  $ret= (object)array('msg'=>'');
  $osoby= array();
  $prispevek= 100;
  $nazev= $rok= '';
  $values= $del= $jmena= '';
  $celkem= 0;
  $qp= "SELECT IFNULL(dp.castka,0) AS _ma,
          o.id_osoba,o.jmeno,a.datum_do,a.nazev,YEAR(a.datum_do) AS _rok,a.access
        FROM pobyt AS p
        JOIN akce AS a ON a.id_duakce=p.id_akce
        JOIN spolu AS s USING (id_pobyt)
        JOIN osoba AS o USING (id_osoba)
        LEFT JOIN dar AS dc 
          ON dc.id_osoba=o.id_osoba AND dc.deleted='' AND dc.ukon='c'
        LEFT JOIN dar AS dp 
          ON dp.id_osoba=o.id_osoba AND dp.deleted='' AND dp.ukon='p' 
            AND YEAR(dp.dat_do)>=YEAR(NOW()) 
        WHERE id_pobyt=$id_pobyt ";
  $rp= pdo_qry($qp);
  while ( $rp && $p= pdo_fetch_object($rp) ) {
    if ($p->_ma) continue;
    $osoba= $p->id_osoba;
    $rok= $p->_rok;
    $datum= $p->datum_do;
    $nazev= "$rok - {$p->nazev}";
    $values.= "$del({$p->access},$osoba,'p',$prispevek,'$datum','$rok-12-31','$nazev')";
    $jmena.= "$del{$p->jmeno}";
    $del= ', ';
    $celkem+= $prispevek;
  }
//                                                         display($values);
  $qi= "INSERT dar (access,id_osoba,ukon,castka,dat_od,dat_do,note) VALUES $values";
  $ri= pdo_qry($qi);
  display("SQL: $qi");
  // odpověď
  $ret->msg= "Za člena $jmena vlož do pokladny $celkem,- Kč";
  return $ret;
}
# -------------------------------------------------------------------------------- akce2 uhrady_load
# načtení úhrad za pobyt pro podkary Platba za akcu
# doplněná pro akce2_vyuctov_pary_cv2
#    platba_ucet = suma pro u_stav 1,2,3 a u_zpusob != 3 ... datum_ucet
#    platba_pokl = suma pro u_stav 1,2,3 a u_zpusob = 3
#    vratka = suma pro u_stav 4 ... datum_vratka
function akce2_uhrady_load($id_pobyt) { 
  $ret= (object)array('pocet'=>0,'seznam'=>array(),
      'platba_ucet'=>0,'platba_pokl'=>0,'datum_ucet'=>0,'vratka'=>0);
  $rp= pdo_qry("SELECT u_poradi,u_castka,u_datum,u_zpusob,u_stav,u_za FROM uhrada
          WHERE id_pobyt=$id_pobyt ORDER BY u_poradi");
  while ( $rp && $p= pdo_fetch_object($rp) ) {
    $ret->pocet++;
    $ret->seznam[]= $p;
    // údaje pro volání z akce2_vyuctov_pary_cv2
    if ($p->u_stav==4) {
      $ret->vratka+= $p->u_castka;
      $ret->datum_vratka= max($p->u_datum,$ret->datum_vratka);
    }
    else { // platby
      if ($p->u_zpusob==3) {
        $ret->platba_pokl+= $p->u_castka;
      }
      else {
        $ret->platba_ucet+= $p->u_castka;
        $ret->datum_ucet= max($p->u_datum,$ret->datum_ucet);
      }
    }
    // úprava data
    $p->u_datum= sql_date1($p->u_datum);
  }
  $ret->datum_vratka= sql_date1($ret->datum_vratka);
  $ret->datum_ucet= sql_date1($ret->datum_ucet);
//  debug($ret,"akce2_uhrady_load($id_pobyt)");
  return $ret;
}
# --------------------------------------------------------------------------------- akce2 uhrady_new
# vrátí u_poradi pro novou úhradu
function akce2_uhrady_new($id_pobyt) { 
  $u_poradi= select('MAX(u_poradi)','uhrada',"id_pobyt=$id_pobyt");
  return ($u_poradi ?: 0) + 1;
}
# -------------------------------------------------------------------------------- akce2 uhrady_save
# uložení změn úhrad za pobyt
function akce2_uhrady_save($id_pobyt,$uhrady) { 
//  debug($uhrady,"akce2_uhrady_save($id_pobyt)");
  foreach ($uhrady as $new) {
    $new->u_datum= sql_date1($new->u_datum,1);
    $old= select_object('u_poradi,u_castka,u_datum,u_zpusob,u_stav,u_za','uhrada',
        "id_pobyt=$id_pobyt AND u_poradi=$new->u_poradi");
    if ($old) {
      foreach ($new as $fld=>$val) {
//        if ($fld=='u_index') {
//          $fld= 'u_poradi';
//          $val--;
//        }
        if ($old->$fld != $val) {
          query("UPDATE uhrada SET $fld='$val' WHERE id_pobyt=$id_pobyt AND u_poradi=$new->u_poradi");
        }
      }
    }
    else {
      query("INSERT INTO uhrada (id_pobyt,u_poradi,u_castka,u_datum,u_zpusob,
          u_stav,u_za) 
        VALUES ($id_pobyt,$new->u_poradi,'$new->u_castka','$new->u_datum',$new->u_zpusob,
          $new->u_stav,$new->u_za)");
    }
  }
}
# ====================================================================================> . autoselect
# ---------------------------------------------------------------------------------- akce2 auto_deti
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
         LEFT JOIN tvori AS t ON s.id_osoba=t.id_osoba AND t.id_rodina=p.i0_rodina
         WHERE o.deleted='' AND prijmeni LIKE '$patt%' AND role='d' AND id_akce='{$par->akce}'
           AND s_role=3
         ORDER BY prijmeni,jmeno LIMIT $limit";
  $res= pdo_qry($qry);
  while ( $res && $t= pdo_fetch_object($res) ) {
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
# ------------------------------------------------------------------------------- ucast2_auto_jmeno2
# test autocomplete - hledá jména ve všech organizacích, použité v příjmení
function ucast2_auto_jmeno2($patt,$par) {  trace();
  $a= (object)array();
  $limit= 10;
  $n= 0;
  if ( $par->prefix ) {
    $patt= "{$par->prefix}$patt";
  }
  // zpracování vzoru
  $qry= "SELECT TRIM(jmeno) AS _value
         FROM osoba
         WHERE prijmeni='{$par->prijmeni}' AND TRIM(jmeno)!='' AND jmeno LIKE '$patt%'
         GROUP BY _value
         ORDER BY _value LIMIT $limit
         ";
  $res= pdo_qry($qry);
  while ( $res && $t= pdo_fetch_object($res) ) {
    ++$n;
    if ( $n==$limit ) break;
    $a->{$n}= $t->_value;
  }
  // obecné položky
  if ( !$n )
    $a->{0}= "... nic nezačíná $patt";
  elseif ( $n==$limit )
    $a->{999999}= "... a další";
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
    $res= pdo_qry($qry);
    while ( $res && $t= pdo_fetch_object($res) ) {
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
    $res= pdo_qry($qry);
    while ( $res && $t= pdo_fetch_object($res) ) {
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
# ======================================================================================> . SKUPINKY
# --------------------------------------------------------------------------------- akce2 skup_check
# zjištění konzistence skupinek podle příjmení VPS/PPS
function akce2_skup_check($akce) {
  $ret= akce2_skup_get($akce,1,$err);
  return $ret->msg;
}
# ------------------------------------------------------------------ akce2 skup_get
# zjištění skupinek podle příjmení VPS/PPS
# pokud je par.mark=LK vrátí se skupinky z letního kurzu s informací, jestli jsou na této akci
# pokud je par.mark=PO vrátí se skupinky z předchozí obnovy s informací, jestli jsou na této akci
function akce2_skup_get($akce,$kontrola,&$err,$par=null) { trace();
//                                                         debug($par,"akce2_skup_get");
  global $VPS, $tisk_hnizdo;
  $ret= (object)array('lk'=>0);
  $msg= array();
  $jen_hnizdo= $tisk_hnizdo ? " AND hnizdo=$tisk_hnizdo " : '';
  $skupiny= array();
  // přechod na LK pro par->mark=LK ... a pokud jde o obnovu
  if ( isset($par->mark) && ($par->mark=='LK' || $par->mark=='PO') ) {
    list($access,$druh,$kdy)= select('access,druh,datum_od','akce',"id_duakce=$akce");
    if ( $druh==2 /*MS obnova*/ ) {
      $pred_druh= $par->mark=='LK' ? 1 : 2;
      $akce= select('id_duakce','akce',
        "access=$access AND druh=$pred_druh AND datum_od<'$kdy' ORDER BY datum_od DESC LIMIT 1");
      $ret->lk= $akce;
    }
    else { $msg[]= "tento výpis má smysl jen pro obnovy MS"; $err= -1; $kontrola= 1; goto end; }
  }
  $celkem= select('count(*)','pobyt',"id_akce=$akce AND funkce IN (0,1,2) $jen_hnizdo AND skupina!=-1");
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
      WHERE p.id_akce=$akce $jen_hnizdo AND skupina>0
      GROUP BY skupina ";
  $res= pdo_qry($qry);
  while ( $res && ($s= pdo_fetch_object($res)) ) {
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
          WHERE p.id_pobyt IN ({$s->_skupina}) $jen_hnizdo
          GROUP BY id_pobyt
          ORDER BY IF(funkce IN (1,2),1,2), nazev";
      }
      elseif ( $par && $par->verze=='MS' ) {
        $qryu= "
          SELECT p.id_pobyt,skupina,nazev,pokoj,i0_rodina,
            GROUP_CONCAT(o.id_osoba) as ids_osoba,
            GROUP_CONCAT(o.id_osoba) as id_osoba_m,
            CONCAT(nazev,' ',GROUP_CONCAT(o.jmeno ORDER BY t.role SEPARATOR ' a ')) AS _nazev
          FROM pobyt AS p
          JOIN spolu AS s USING(id_pobyt)
          JOIN osoba AS o ON s.id_osoba=o.id_osoba
          LEFT JOIN tvori AS t ON t.id_osoba=o.id_osoba AND id_rodina=i0_rodina
          LEFT JOIN rodina AS r USING(id_rodina)
          WHERE p.id_pobyt IN ({$s->_skupina}) AND funkce IN (0,1,2,5,6) AND t.role IN ('a','b') 
            $jen_hnizdo
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
          WHERE p.id_pobyt IN ({$s->_skupina}) $jen_hnizdo
          GROUP BY id_pobyt
          ORDER BY IF(funkce IN (1,2),1,2), nazev";
      }
      $resu= pdo_qry($qryu);
      while ( $resu && ($u= pdo_fetch_object($resu)) ) {
        $mark= '';
        if ( $par && $par->mark=='novic' ) {
          // minulé účasti
          $ids= $u->ids_osoba;
          $rqry= "SELECT count(*) as _pocet
                  FROM akce AS a
                  JOIN pobyt AS p ON a.id_duakce=p.id_akce
                  JOIN spolu AS s USING(id_pobyt)
                  WHERE a.druh=1 AND s.id_osoba IN ($ids) AND p.id_akce!=$akce $jen_hnizdo";
          $rres= pdo_qry($rqry);
          if ( $rres && ($r= pdo_fetch_object($rres)) ) {
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
//  debug($all,'ALL');
  // řazení - v PHP nelze udělat
  if ( count($all) ) {
    $qryo= "SELECT GROUP_CONCAT(DISTINCT CONCAT(id_pobyt,'|',nazev) ORDER BY nazev) as _o
            FROM pobyt AS p
            JOIN rodina AS r ON id_rodina=i0_rodina
            WHERE id_pobyt IN (".implode(',',$all).") $jen_hnizdo ";
//    $qryo= "SELECT GROUP_CONCAT(DISTINCT CONCAT(id_pobyt,'|',nazev) ORDER BY nazev) as _o
//            FROM pobyt AS p
//            JOIN spolu AS s USING(id_pobyt)
//            JOIN osoba AS o ON s.id_osoba=o.id_osoba
//            LEFT JOIN tvori AS t ON t.id_osoba=o.id_osoba
//            LEFT JOIN rodina AS r USING(id_rodina)
//            WHERE id_pobyt IN (".implode(',',$all).") $jen_hnizdo ";
    $reso= pdo_qry($qryo);
    while ( $reso && ($o= pdo_fetch_object($reso)) ) {
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
end:
  $ret->skupiny= $skup;
  $ret->msg= implode(",<br>",$msg);
  return $ret;
}

# --------------------------------------------------------------------------------- akce2 skup_renum
# přečíslování skupinek podle příjmení VPS/PPS
function akce2_skup_renum($akce) {
  $err= 0;
  $msg= '';
  $par= (object)array('verze'=>'MS');
//  $par= (object)array('verze'=>'DS');
  $ret= akce2_skup_get($akce,0,$err,$par);
//  debug($ret); goto end;
  $skupiny= $ret->skupiny;
  if ( $err>1 ) {
    $msg= "skupinky nejsou dobře navrženy - ještě je nelze přečíslovat";
  }
  else {
    $n= 1;
    foreach($skupiny as $ivps=>$skupina) {
      $cleni= implode(',',array_keys($skupina));
      $qryu= "UPDATE pobyt SET skupina=$n WHERE id_pobyt IN ($cleni) ";
      $resu= pdo_qry($qryu);
//                                                         display("$n: $qryu");
      $n++;
    }
    $msg= "bylo přečíslováno $n skupinek";
  }
end:
  return $msg;
}
# ------------------------------------------------------------------------------- akce2 osoba_rodiny
# vrátí rodiny dané osoby ve formátu pro select (název:id_rodina;...)
function akce2_osoba_rodiny($id_osoba) {
  $rodiny= select1("GROUP_CONCAT(CONCAT(nazev,':',id_rodina) SEPARATOR ',')",
    "rodina JOIN tvori USING(id_rodina)","id_osoba=$id_osoba");
  $rodiny= "-:0".($rodiny ? ',' : '').$rodiny;
                                                display("akce_osoba_rodiny($id_osoba)=$rodiny");
  return $rodiny;
}
# ------------------------------------------------------------------------------ akce2 pobyt_rodinny
# definuje pobyt jako rodinný
function akce2_pobyt_rodinny($id_pobyt,$id_rodina) { trace();
  query("UPDATE pobyt SET i0_rodina=$id_rodina WHERE id_pobyt=$id_pobyt");
  return 1;
}
# ---------------------------------------------------------------------------------- akce2 save_role
# zapíše roli - je to netypická číselníková položka definovaná jako VARCHAR(1)
function akce2_save_role($id_tvori,$role) { //trace();
  return pdo_qry("UPDATE tvori SET role='$role' WHERE id_tvori=$id_tvori");
}
# ------------------------------------------------------------------------------- akce2 save_pfunkce
# ASK
# zapíše spolu.pfunkce - funkce pro hlavouny
function akce2_save_pfunkce($ids,$pfunkce) { //trace();
  ezer_qry("UPDATE","spolu",$ids,array(
    (object)array('fld'=>'pfunkce', 'op'=>'u','val'=>$pfunkce)
  ));
  return 1;
}
# ------------------------------------------------------------------------------------ akce2 osoba2x
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
    $ret->o_adresa->$f= isset($o->$f) ? $o->$f : '';
    $ret->r_adresa->$f= empty($r) ? '' : ($f=='noadresa'||$f=='stat'?'':'®').$r->$f;
  }
  foreach(explode(',',$kontakt) as $f) { $fy= $f.'y';
    $ret->o_kontakt->$f= isset($o->$f) ? $o->$f : '';
    $ret->r_kontakt->$f= empty($r) ? '' : ($f=='nomail'?'':'®').$r->$fy;
  }
//                                                         debug($ret,"akce2__osoba2x");
  return $ret;
}
# ------------------------------------------------------------------------------------ akce2 ido2idp
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
  $qo= pdo_qry("SELECT id_osoba,o.access,r.access AS r_access FROM rodina AS r
                  LEFT JOIN tvori USING (id_rodina) LEFT JOIN osoba AS o USING (id_osoba)
                  WHERE id_rodina=$idr");
  while ( $qo && ($o= pdo_fetch_object($qo)) ) {
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
function ucast2_pridej_rodinu($id_akce,$id_rodina,$hnizdo=0) { trace();
  $ret= (object)array('idp'=>0,'msg'=>'');
  // kontrola definice rodiny
  if ( !$id_rodina ) { $ret->msg= "Nelze přidat pobyt neznámé rodiny"; goto end; }
  // kontrola nepřítomnosti
  $jsou= select1('COUNT(*)','pobyt',"id_akce=$id_akce AND i0_rodina='$id_rodina'");
  if ( $jsou ) { // už jsou na akci
    $ret->msg= "... rodina již je přihlášena na akci";
  }
  else {
    // vložení nového pobytu
    $rod= $pouze==0 && $info->rod ? $info->rod : 0;
    // přidej k pobytu
    $chng= array(
      (object)array('fld'=>'id_akce',   'op'=>'i','val'=>$id_akce),
      (object)array('fld'=>'i0_rodina', 'op'=>'i','val'=>$id_rodina)
    );
    if ( $hnizdo )
      $chng[]= (object)array('fld'=>'hnizdo', 'op'=>'i','val'=>$hnizdo);
    $ret->idp= ezer_qry("INSERT",'pobyt',0,$chng);
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
# spolupracuje s číselníky: ms_akce_s_role, ys_akce_dite_kat a fa_akce_dite_kat
#   podle stáří resp. role odhadne hodnotu SPOLU.s_role a SPOLU.dite_kat
#  vrací
#   ret.spolu,tvori - klíče vytvořených záznamů stejnojmenných tabulek nebo 0
#   ret.pobyt - parametr nebo nové vytvořený pobyt
function ucast2_pridej_osobu($ido,$access,$ida,$idp,$idr=0,$role=0,$hnizdo=0) { trace();
  $ret= (object)array('pobyt'=>$idp,'spolu'=>0,'tvori'=>0,'msg'=>'');
  list($narozeni,$old_access)= select("narozeni,access","osoba","id_osoba=$ido");
  # případné vytvoření pobytu
  if ( !$idp ) {
    $chng= array(
      (object)array('fld'=>'id_akce',   'op'=>'i','val'=>$ida),
      (object)array('fld'=>'i0_rodina', 'op'=>'i','val'=>$idr)
    );
    if ( $hnizdo )
      $chng[]= (object)array('fld'=>'hnizdo', 'op'=>'i','val'=>$hnizdo);
    $idp= $ret->pobyt= ezer_qry("INSERT",'pobyt',0,$chng);
  }
  # přidání k pobytu
  list($je,$funkce)=
    select("COUNT(*),funkce","pobyt JOIN spolu USING(id_pobyt)","id_akce=$ida AND id_osoba=$ido");
  if ( $je ) {
    $ret->msg.= "osoba už na této akci je".($funkce==99 ? ' jako pečovatel.
      Pokud má být na akci jako pomocný nebo osobní pečovatel nebo bude ve skupině G, je třeba ji
      na stránce Pečouni zrušit (zapamatovat poznámku k akci!) a odsud znovu zařadit.':'');
    goto end;
  }
  // pokud na akci ještě není, zjisti pro děti (<18 let) s_role a dite_kat
  list($datum_od,$ma_cenik)= select("datum_od,ma_cenik","akce","id_duakce=$ida");
  $vek= roku_k($narozeni,$datum_od);
  $kat= 0; $srole= 1;                                         // default= účastník, nedítě
  if     ( $role=='p' )                         { $kat= 0; $srole= 5; }   // osob.peč.
  elseif ( $vek>=18 || $narozeni=='0000-00-00') { $kat= 0; $srole= 1; }   // účastník
  elseif ( !$role || $role=='d' ) {                                       // děti podle čísleníku
    // odhad typu účasti dítěte podle stáří, role a organizace
    $dite_kat= xx_akce_dite_kat($ida);
    // {L|-},{c|p},{D|d|p|C} = lůžko/bez, celá/poloviční, s_role podle ms_akce_s_role
    $akce_dite_kat= map_cis($dite_kat,'data'); 
    $akce_dite_kat_LpD= map_cis($dite_kat,'barva'); 
    $akce_dite_kat_vek= map_cis($dite_kat,'ikona'); // od-do
    foreach ($akce_dite_kat as $kat) {
      list($od,$do)= explode('-',$akce_dite_kat_vek[$kat]);
      if ( $vek>=$od && $vek<$do) {
        $LpD= explode(',',$akce_dite_kat_LpD[$kat]);
        $srole= select('data','_cis',"druh='ms_akce_s_role' AND ikona='$LpD[2]' ");
        break;
      }
    }      
  }
  // přidej k pobytu
  $chng= array(
    (object)array('fld'=>'id_pobyt', 'op'=>'i','val'=>$idp),
    (object)array('fld'=>'id_osoba', 'op'=>'i','val'=>$ido),
    (object)array('fld'=>'s_role',   'op'=>'i','val'=>$srole),
    (object)array('fld'=>'dite_kat', 'op'=>'i','val'=>$kat)
  );
  $ret->spolu= ezer_qry("INSERT",'spolu',0,$chng);
  # přidání do rodiny
  if ( $idr && $role ) {
    $je= select("COUNT(*)","tvori","id_rodina=$idr AND id_osoba=$ido");
    if ( $je ) { $ret->msg.= "osoba už v této rodině je"; goto end; }
    // pokud v rodině ještě není, přidej
    $ret->tvori= ezer_qry("INSERT",'tvori',0,array(
      (object)array('fld'=>'id_osoba', 'op'=>'i','val'=>$ido),
      (object)array('fld'=>'id_rodina','op'=>'i','val'=>$idr),
      (object)array('fld'=>'role',     'op'=>'i','val'=>$role)
    ));
  }
  # úprava access, je-li třeba
  if ( $access && $access!=$old_access) {
    ezer_qry("UPDATE",'osoba',$ido,array(
      (object)array('fld'=>'access', 'op'=>'u','val'=>$access,'old'=>$old_access)
    ));
  }
  // POKUD je to akce s pobytem hrazeným podle ceníku Domu setkání - vytvoříme vzorec 
  if ($ma_cenik==2) {
//    dum_update_host($ret->spolu);
  }
end:
//                                                 debug($ret,'ucast2_pridej_osobu / $vek $kat $srole');
  return $ret;
}
# ---------------------------------------------------------------------------------- akce2 skup_copy
# zruší skupinky dané akce
function akce2_skup_del($ida) { trace();
  $n= 0;  
  $rs= query("UPDATE pobyt SET skupina=0
    WHERE id_akce=$ida AND skupina!=0");
  $n+= pdo_affected_rows($rs);
  return $n ? "Bylo zrušeno $n zařazení do skupinek" : "Na akci nejsou žádné skupinky";
}
# ---------------------------------------------------------------------------------- akce2 skup_copy
# upraví skupinky 
# podle=LK z posledního letního kurzu, podle=PO přenese z PO do JO, podle=XX zruší skupinky
function akce2_skup_copy($obnova,$podle) { trace();
  $msg= "Kopii nelze provést";
  // najdeme LK
  list($access,$druh,$kdy)= select('access,druh,datum_od','akce',"id_duakce=$obnova");
  if ( $druh==2 /*MS obnova*/ ) {
    if ($podle=='LK') {
      $lk= select('id_duakce','akce',
        "access=$access AND druh=1 /*MS LK*/ AND datum_od<'$kdy' ORDER BY datum_od DESC LIMIT 1");
    }
    else {
      // má smysl jen pro jarní
      if (substr($kdy,5,2)>7) { $msg= "tato operace nemá smysl pro PO"; goto end; }
      $lk= select('id_duakce','akce',
        "access=$access AND druh=2 /*MS PO */ AND datum_od<'$kdy' ORDER BY datum_od DESC LIMIT 1");
    }
  }
  else { $msg= "Skupinky z LK lze zkopírovat jen pro obnovy MS"; goto end; }
  // nesmí být rozpracované skupinky
  $skupinky= select('COUNT(*)','pobyt',"skupina!=0 AND id_akce=$obnova");
  if ( $skupinky ) {
    $msg= "Skupinky na této obnově jsou již částečně navrženy, kopii z $podle nelze provést";
    goto end;
  }
  // vše ok, provedeme přenos ... šlo by to i čistě v SQL
  //UPDATE pobyt AS jo
  //JOIN pobyt AS lk ON jo.i0_rodina=lk.i0_rodina AND lk.id_akce=1131 AND lk.skupina!=0
  //SET jo.skupina=lk.skupina
  //WHERE jo.id_akce=1255 AND jo.skupina=0  
  $n= 0;
  $rr= pdo_qry("SELECT lk.i0_rodina,lk.skupina,r.nazev
      FROM pobyt AS lk 
      JOIN pobyt AS jo ON jo.id_akce=$obnova AND jo.i0_rodina=lk.i0_rodina
      JOIN rodina AS r ON r.id_rodina=lk.i0_rodina
      WHERE lk.id_akce=$lk AND lk.skupina!=0
      ORDER BY skupina");
  while ( $rr && (list($idr,$skupina,$nazev)= pdo_fetch_array($rr)) ) {
    display("$skupina = $nazev ($idr)");
//    $n++;
    $rs= query("UPDATE pobyt SET skupina=$skupina 
      WHERE id_akce=$obnova AND i0_rodina=$idr AND skupina=0 AND funkce IN (0,1,2,5) ");
    $n+= pdo_affected_rows($rs);
  }
  $msg= "Na obnovu bylo pro $n párů zkopírováno číslo skupinky z $podle (pokud ještě číslo neměli)";
end:
  return $msg;
}
# ---------------------------------------------------------------------------------- akce2 skup_paru
# přehled všech skupinek letního kurzu MS daného páru (rodiny) -> html
# počet let aktivního VPSkování -> sluzba, počet odpočinkových skupinek -> odpocinek
# počet účastí na MS -> ucasti
function akce2_skup_paru($idr) { //trace();
  $html= '';
  $sluzba= $odpocinek= $vps= 0;
  $n_ms= 0;
  // projdi LK ve kterých byli ve skupince
  $ru= pdo_qry("
    SELECT id_akce,skupina,YEAR(datum_od) as rok
    FROM akce AS a
    JOIN pobyt AS p ON a.id_duakce=p.id_akce AND i0_rodina=$idr
    WHERE a.druh=1 AND skupina!=0
    GROUP BY rok
    ORDER BY datum_od DESC
  ");
  while ( $ru && (list($ida,$skup,$rok)= pdo_fetch_array($ru)) ) {
    $html.= "<br>&nbsp;&nbsp;&nbsp;<b>$rok</b> ";
    $n_ms++;
    // prober skupinku
    $rs= pdo_qry("
      SELECT funkce,nazev,id_rodina
      FROM pobyt AS p
      JOIN rodina AS r ON r.id_rodina=p.i0_rodina
      WHERE p.id_akce=$ida AND skupina=$skup
      ORDER BY IF(p.funkce IN (1,2),'',nazev)
    ");
    while ( $rs && (list($fce,$nazev,$ir)= pdo_fetch_array($rs)) ) {
      if ( $ir==$idr && $fce==1  ) 
        $vps++;
      if ( $ir==$idr && !$odpocinek ) {
        if ( $fce==1  ) 
          $sluzba++;
        else
          $odpocinek= $rok;
      }
      $par= " $nazev".($fce==1||$fce==2 ? ' (VPS) ' : '');
      $html.= $ir==$idr ? "<i>$par</i>" : $par;
    }
  }
  if ( !$n_ms ) $html= "nebyli na MS v žádné skupince";
  return (object)array('sluzba'=>$sluzba,'odpocinek'=>$odpocinek,'vps'=>$vps,
      'ucasti'=>$n_ms,'html'=>$html);
}
# -------------------------------------------------------------------------------- evid2 deti_access
# ==> . chain rod
# účastníci pobytu a případná rodina budou mít daný access (3)
function evid2_deti_access($idr,$access=3) {
  // zjištění access rodičů
  list($min,$max)= select("MIN(o.access),MAX(o.access)",
                          "rodina JOIN tvori USING (id_rodina) JOIN osoba AS o USING (id_osoba)",
                          "id_rodina=$idr AND role IN ('a','b') GROUP BY id_rodina");
  if ( $min==$access && $max==$access ) {
    // změna dětí v rodině
    $qo= pdo_qry("SELECT access,id_osoba FROM tvori JOIN osoba USING (id_osoba)
                    WHERE id_rodina=$idr AND role='d' ");
    while ( $qo && ($o= pdo_fetch_object($qo)) ) {
      $ido= $o->id_osoba;
      $o_access= $o->access;
      if ( $o_access!=$access ) {
        ezer_qry("UPDATE",'osoba',$ido,array(
          (object)array('fld'=>'access', 'op'=>'u','val'=>$access,'old'=>$o_access)
        ));
      }
    }
    // změna pro rodinu
    $r_access= select("access","rodina","id_rodina=$idr");
    if ( $r_access!=$access ) {
      ezer_qry("UPDATE",'rodina',$idr,array(
        (object)array('fld'=>'access', 'op'=>'u','val'=>$access,'old'=>$r_access)
      ));
    }
  }
  return 1;
}
# --------------------------------------------------------------------------------------- track_like
# vrátí změny podobné předané (stejný uživatel, tabulka, čas +-10s)
if(!function_exists("track_like")) {
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
  $xr= pdo_qry(
    "SELECT COUNT(*) AS _pocet,GROUP_CONCAT(id_track) AS _ids
     FROM _track
     WHERE kdo='$kdo' AND kde='$kde' AND klic='$klic' AND op='$op'
       AND kdy BETWEEN DATE_ADD('$kdy',INTERVAL -$diff) AND DATE_ADD('$kdy',INTERVAL $diff)");
  $x= pdo_fetch_object($xr);
  $ret->ids= $x->_ids;
  if ( $x->_pocet > 10 ) {
    $ret->msg= "pozor je příliš mnoho změn - {$x->_pocet}";
    $ret->ok= 0;
  }
end:
  return $ret;
}
}
# ------------------------------------------------------------------------------------- track_revert
# pokusí se vrátit učiněné změny - $ids je seznam id_track
if(!function_exists("track_revert")) {
function track_revert($ids) {  trace();
  global $USER;
  user_test();
  $now= date("Y-m-d H:i:s");
  $user= $USER->abbr;
  $ret= (object)array('ok'=>1);
  $xr= pdo_qry("SELECT * FROM _track WHERE id_track IN ($ids)");
  while ( $xr && ($x= pdo_fetch_object($xr)) ) {
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
//          case 'platba':
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
}
# ------------------------------------------------------------------------------------- rodcis
function rodcis($nar,$sex) {
  $m= substr($nar,5,2);
  $m= str_pad($m + ($sex==2 ? 50 : 0), 2, '0', STR_PAD_LEFT);
  $rc= substr($nar,2,2).$m.substr($nar,8,2);
  return $rc;
}
# --------------------------------------------------------------------------------- ucast clip_paste
# vloží účastníky seznamu pobytů (zadaných jako seznam id_pobyt do zvolené akce
# zachová pobyt.funkce a spolu.s_role
function ucast_clip_paste($idps,$ida) {
  $radky= "<ol style='overflow-x: auto;height: 250px'>"; 
  $np= $no= 0;
  $po= pdo_qry("
      SELECT id_akce,i0_rodina,IFNULL(nazev,''),
        GROUP_CONCAT(CONCAT(id_osoba,',',funkce,',',s_role,',',
          REGEXP_REPLACE(CONCAT(prijmeni,' ',jmeno),'[,;]','.')) SEPARATOR ';')
      FROM pobyt
      LEFT JOIN rodina ON id_rodina=i0_rodina
      JOIN spolu USING (id_pobyt)
      JOIN osoba USING (id_osoba)
      WHERE id_pobyt IN ($idps)
      GROUP BY id_pobyt
      ORDER BY IF(i0_rodina,nazev,''),prijmeni
      ");
  while ($po && (list($akce,$idr,$rodina,$osoby)= pdo_fetch_array($po))) {
    if ($akce==$ida) {
      $msg= "POZOR: nebyla vybrána jiná akce, do které mají být zapamatovaní účastníci vloženi.";
      goto end;
    }
    if ($idr) {
    $jetam= select('COUNT(*)','pobyt',"id_akce=$ida AND i0_rodina=$idr");
      if ($jetam) {
        $radky.= "<li><i>$rodina</i>: POZOR vynecháno rodina už na akci je!</li>";
        continue;
      }
    }
    $np++;
    $radky.= "<li>"; $idos= [];
    foreach (explode(';',$osoby) as $osoba) {
      $no++;
      list($ido,$fce,$srole,$jm)= explode(',',$osoba);
      display("$akce,$idr,$ido,$fce,$srole,$jm");
      $jetam= select('COUNT(*)','pobyt JOIN spolu USING (id_pobyt)',"id_akce=$ida AND id_osoba=$ido");
      if ($jetam) {
        $radky.= "<br><i>$jm</i> POZOR vynecháno: už na akci je!<br>";
        continue;
      }
      $idos[]= [$ido,$srole];
      $radky.= " $jm - vloženo";
    }    
    $radky.= "</li>";
    // pokud je co, vlož to
    if (count($idos)) {
      $qry= "INSERT INTO pobyt (id_akce,funkce,i0_rodina) VALUE ($ida,$fce,$idr)";
      display($qry);
      $idp= query_track($qry);
      foreach ($idos as $idrole) {
        $qry= "INSERT INTO spolu (id_pobyt,id_osoba,s_role) VALUE ($idp,$idrole[0],$idrole[1])";
        display($qry);
        query_track($qry);
      }
    }
  } 
end:
  return "$msg$radky";
}
# ---------------------------------------------------------------------------------- ucast clip_show
# zobrazí jména účastníků pobytů zadaných jako seznam id_pobyt
function ucast_clip_show($idps) {
  $radky= "<ol style='overflow-x: auto;height: 250px'>"; 
  $n= 0;
  $po= pdo_qry("
      SELECT IFNULL(nazev,''),GROUP_CONCAT(CONCAT(prijmeni,' ',jmeno) SEPARATOR ', ') 
      FROM pobyt
      LEFT JOIN rodina ON id_rodina=i0_rodina
      JOIN spolu USING (id_pobyt)
      JOIN osoba USING (id_osoba)
      WHERE id_pobyt IN ($idps)
      GROUP BY id_pobyt
      ORDER BY IF(i0_rodina,nazev,''),prijmeni
      ");
  while ($po && (list($rod,$jmena)= pdo_fetch_array($po))) {
    $radky.= "<li>".($rod ? "<i>$rod</i>: " : '')."$jmena</li>";
    $n++;
  }
  $radky.= '</ol>'; 
  $radky= "<div style='text-align:center' class='curr_akce'>Seznam členů $n zapamatovaných pobytů</div>$radky";
  return $radky;
}
# ----------------------------------------------------------------------------- ucast presun_do_akce
# přesune pobyt s účastníky do dané akce
#  make=0 žádost o souhlas do msg, chybovou hlášku do err
#  make=1 provede kopii do akce [Zpět]
function ucast_presun_do_akce($idp,$ida_goal,$make=0) { 
  $ret= (object)['msg'=>'','err'=>''];
  // příprava dotazu na souhlas s provedením 
  if (!$make) { 
    $pobyt= ucast_popis($idp);
    $nazev_goal= select1("CONCAT(nazev,' ',YEAR(datum_od))",'akce',"id_duakce=$ida_goal");
    if ($nazev_goal)
      $ret->msg= "Mám přesunout '$pobyt' na akci '$nazev_goal' ? ";
    else 
      $ret->err= "POZOR to není ID akce!";
  }
  // proveď přesun
  elseif ($make) { 
    query_track("UPDATE pobyt SET id_akce=$ida_goal WHERE id_pobyt=$idp");
  }
  return $ret;
}
# ------------------------------------------------------------------------------ ucast kopie_do_akce
# zkopíruje účastníky jednoho pobytu do akce [Zpět]
#  make=0 žádost o souhlas do msg, chybovou hlášku do err
#  make=1 provede kopii do akce [Zpět]
function ucast_kopie_do_akce($idp,$ida_goal,$make=0) { 
  $ret= (object)['msg'=>'','err'=>''];
  $idr= select('i0_rodina','pobyt',"id_pobyt=$idp");
  $nazev_goal= select1("CONCAT(nazev,' ',YEAR(datum_od))",'akce',"id_duakce=$ida_goal");
  // příprava dotazu na souhlas s provedením 
  if (!$make) { 
    $pobyt= ucast_popis($idp);
    $ret->msg= "Mám zkopírovat '$pobyt' na akci '$nazev_goal' ? ";
  }
  // proveď přesun
  elseif ($make) { 
    $idp_goal= query_track("INSERT INTO pobyt (id_akce,i0_rodina) VALUE ($ida_goal,$idr)");
    $rs= pdo_query("SELECT id_osoba FROM spolu WHERE id_pobyt=$idp");
    while ($rs && (list($ido)= pdo_fetch_array($rs))) {
      query_track("INSERT INTO spolu (id_pobyt,id_osoba) VALUE ($idp_goal,$ido)");
    }
  }
  return $ret;
}
# ------------------------------------------------------------------------------------- ucast presun
# přesune účastníky jednoho pobytu do vybraného ... 
#  make=0 žádost o souhlas do msg, chybovou hlášku do err
#  make=1 provede přesun
function ucast_presun($idp,$idp_goal,$make=0) { 
  $ret= (object)['msg'=>'','err'=>''];
  $ida= select('id_akce','pobyt',"id_pobyt=$idp");
  $ida_goal= $idp_goal ? select('id_akce','pobyt',"id_pobyt='$idp_goal'") : 0;
  // id_pobyt může být v tabulkách: faktura, mail, pobyt_wp, prihlaska, uhrada
  $konflikt=[];
  foreach (['faktura', 'mail', 'pobyt_wp', 'prihlaska', 'uhrada'] as $tab) {
    if (select('id_pobyt',$tab,"id_pobyt=$idp")) $konflikt[]= $tab;
  }
  display("ucast_presun: $idp z $ida, $idp_goal z $ida_goal, konflikt=".implode(',',$konflikt));
  // kontrola označení
  if (!$idp_goal) {
    $ret->err= "Označ pomocí klávesy Insert cílový pobyt";
  }
  elseif (strpos($idp_goal,',')) {
    $ret->err= "Proveď v kontextovém menu 'zrušit výběr' a označ pomocí klávesy Insert jediný cílový pobyt";
  }
  elseif ($idp==$idp_goal) {
    $ret->err= "Pomocí klávesy Insert je označen cílový pobyt, ten přesouvaný musí být na jiném řádku";
  }
  elseif ($ida!=$ida_goal) {
    $ret->err= "POZOR chyba programu: Cílový pobyt je v jiné akci, proveď v kontextovém menu 'zrušit výběr' ";
  }
  // příprava dotazu na souhlas s provedením 
  elseif (!$make) { 
    $pobyt= ucast_popis($idp);
    $goal= ucast_popis($idp_goal);
    $konflikt= implode(',',$konflikt);
    $ret->msg= "Mám přesunout '$pobyt' do cílového pobytu s účastníky '$goal' ? "
        . "<hr>POZOR údaje ve 'společné údaje o pobytu' u přesouvaného pobytu budou zapomenuty."
        . ($konflikt ? "<hr>Vazba přes '$konflikt' bude přidána k cílovému pobytu." : '');
  }
  // proveď přesun
  elseif ($make) { 
    foreach ($konflikt as $tab) {
      query("UPDATE $tab SET id_pobyt=$idp_goal WHERE id_pobyt=$idp");
    }
    $rs= pdo_query("SELECT id_spolu FROM spolu WHERE id_pobyt=$idp");
    while ($rs && (list($ids)= pdo_fetch_array($rs))) {
      query_track("UPDATE spolu SET id_pobyt=$idp_goal WHERE id_spolu=$ids");
    }
    global $USER;
    query("INSERT INTO _track (kdy,kdo,kde,klic,op,fld,old) "
        . "VALUE (NOW(),'$USER->abbr','pobyt',$idp,'x','id_akce',$ida)");
    query("DELETE FROM pobyt WHERE id_pobyt=$idp");

  }
  return $ret;
}
# ------------------------------------------------------------------------------- ucast presun_cleny
# vysune označené členy z daného pobytu a vytvoří pro ně nový pobyt nebo jen přesune jinam
#  kam='' vrátí varianty do kam, případně chybovou hlášku do err
#  kam='jiny' provede přesun do pobytu idps
#  kam='novy' vytvoří nový pobyt a přesune
function ucast_presun_cleny($idp,$idos,$idps,$kam='0') { 
  $ret= (object)['ask'=>'','buts'=>'','idp'=>0,'err'=>''];
  display("ucast_presun_cleny($idp,'$idos','$idps',$kam)");
  switch ($kam) {
    case '0': // formulace počátečního dotazu
      $jiny= $idps && $idps!=$idp && strstr($idps,',')===false; // označen právě jeden jiný pobyt
      if (!$idos) { $ret->err= 'pomocí Insert označ přesunované členy pobytu'; goto end; }
      $cleni= select('GROUP_CONCAT(jmeno)',
          "osoba AS o LEFT JOIN spolu AS s ON s.id_osoba=o.id_osoba AND s.id_pobyt=$idp",
          "o.id_osoba IN ($idos) AND NOT(ISNULL(s.id_osoba))");
      $ret->ask= "Do kterého pobytu mám přesunout označené členy: $cleni?";
      $ret->buts= "do nově vytvořeného:novy";
      if ($jiny) {
        $pobyt= dum_pobyt_nazev($idps,'rodina');
        $ret->buts.= ",přidat k $pobyt:jiny";
      }
      $ret->buts.= ",nic nepřesouvat:nikam";
      break;
    case 'novy': // požadavek - přesunout $idos/$idp do nově vytvořeného pobytu 
      $ida= select('id_akce','pobyt',"id_pobyt=$idp");
      $idps= query_track("INSERT INTO pobyt (id_akce) VALUE ($ida)");
    case 'jiny': // požadavek - přesunout $idos/$idp do označeného pobytu $idps
      $rs= pdo_qry("SELECT id_spolu FROM spolu WHERE id_pobyt=$idp AND id_osoba IN ($idos)");
      while ($rs && (list($ids)= pdo_fetch_array($rs))) {
        query_track("UPDATE spolu SET id_pobyt=$idps WHERE id_spolu=$ids");
      }
      $ret->idp= $idps;
      break;
  }
end:
  return $ret;
}
# -------------------------------------------------------------------------------------- ucast popis
# vrátí popis účastníků pobytu 
function ucast_popis($idp) {
  $txt= select1("GROUP_CONCAT(CONCAT(prijmeni,' ',jmeno) SEPARATOR ' + ')",
      'osoba JOIN spolu USING (id_osoba)',"id_pobyt=$idp");
  return $txt;
}
# ---------------------------------------------------------------------------- akce2 rodina_z_pobytu
# vrátí rodiny dané osoby ve formátu pro select (název:id_rodina;...)
function ucast2_rodina_z_pobytu($idp) {
  $idr= 0; // název rodiny podle nejstaršího člena pobytu
  $a= 'a'; $b= 'b'; // po přidělení bude změněno na 'd'
  $res= pdo_qry("SELECT id_osoba, a.access, TRIM(prijmeni), sex, ulice, psc, obec,
          ROUND(IF(MONTH(narozeni),
            DATEDIFF(datum_od,narozeni)/365.2425,YEAR(datum_od)-YEAR(narozeni)),1) AS _vek
         FROM osoba 
           JOIN spolu USING (id_osoba) JOIN pobyt USING (id_pobyt) 
           JOIN akce AS a ON id_akce=id_duakce 
         WHERE id_pobyt=$idp 
         ORDER BY narozeni");
  while ( $res && (list($ido,$access,$prijmeni,$sex,$ulice,$psc,$obec,$vek)= pdo_fetch_array($res)) ) {
    if (!$idr) { 
      // vytvoř rodinu podle nejstaršího
      $nazev= preg_replace('~ová$~','',$prijmeni).'ovi';
      $idr= query_track("INSERT INTO rodina (nazev,access,ulice,psc,obec) "
          . "VALUE ('$nazev',$access,'$ulice','$psc','$obec')");
    }
    // a přidávej členy rodiny
    $role= $vek<18 ? 'd' : ($sex==1 ? $a : $b);
    if ($role=='a')  $a= 'd';
    if ($role=='b')  $b= 'd';
    query_track("INSERT INTO tvori (id_osoba,id_rodina,role) VALUE ($ido,$idr,'$role')");
  }
  return $idr;
}
# ------------------------------------------------------------------------ akce2 pridat_clena_rodine
# přidá osobu jako nového člena rodiny, vrátí navržený text role
function ucast2_pridat_clena_rodine($ido,$idr) {
  list($vek,$sex)= select1("TIMESTAMPDIFF(YEAR,narozeni,NOW()),sex",'osoba',"id_osoba=$ido");
  $roles= select1("GROUP_CONCAT(role)",'tvori',"id_rodina=$idr");
  // a přidávej členy rodiny
  if ($vek<20)
    $r= 'd';
  else {
    $r= $sex==1 ? 'a' : 'b';
    if (strchr($roles,$r)!==false) $r= 'p';
  }
  display("vek=$vek, sex=$sex, r=$r");
  query_track("INSERT INTO tvori (id_osoba,id_rodina,role) VALUE ($ido,$idr,'$r')");
  $role_text= str_replace('-','jako',select('hodnota','_cis',
      "druh='ms_akce_t_role' AND zkratka='$r' "));
  return $role_text;
}
