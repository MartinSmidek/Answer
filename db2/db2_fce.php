<?php # (c) 2009-2010 Martin Smidek <martin@smidek.eu>
/** ===========================================================================================> DB2 **/
# ------------------------------------------------------------------------------------- db2_rod_show
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
/** ========================================================================================> UCAST2 **/
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

  // podobné rodiny
  $qr= mysql_qry("SELECT $flds_r FROM rodina WHERE nazev='$nazev' AND id_rodina!=$idro AND deleted=''");
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
function ucast2_chain_oso($idoo) {
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
  // dotazovaná rodina
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
  $emaily= $telefony= array();
  if ( $o->kontakt ) {
    $emaily= $items2array($o->email);
    $telefony= $items2array($o->telefon,'^\d');
  }
//                                                 debug($o);
  // . vzory faktorů
  // podobné osoby
  $qo= mysql_qry("
    SELECT $flds,r.obec,
      SUBSTR(MIN(CONCAT(IF(role='','?',role),id_rodina)),2) AS _kmen
    FROM osoba AS o
      JOIN tvori USING(id_osoba)
      JOIN rodina AS r USING(id_rodina)
    WHERE (prijmeni='{$o->prijmeni}' OR rodne='{$o->prijmeni}') AND id_osoba!=$idoo AND o.deleted=''
    GROUP BY id_osoba");
  while ( $qo && ($xo= mysql_fetch_object($qo)) ) {
    $xo_jmeno= trim($xo->jmeno);
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
//                                                 debug($os,"os");
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
    $xi->narozeni= $ox->narozeni==$narozeni ? 1 : 0;
    // organizace
    $xi->org= $ox->access;
    // zápis
    $x[$ido]= $xi;
  }
  // míra podobnosti osob
  $nx0= count($x);
  $idr= 0;
  $orgs= $ro->access;
  foreach ($x as $i=>$xi) {
    $xi->asi.= $xi->bydliste ? 'b' : '';
    $xi->asi.= $xi->narozeni ? 'n' : '';
    $xi->asi.= $xi->kontakty ? 'k' : '';
    if ( !strlen($xi->asi) || $xi->asi=='b' ) { unset($x[$i]); continue; }
    $orgs|= $xi->org;
    $idr= $i;
  }
//                                                 debug($x,"podobné osoby");
//                                                 goto end;
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
    $msg= "pravděpodobně ($dup) duplicitní s osobou $ido "
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
      $mira= strpos($dupi,'n')!==false || strpos($dupi,'k')!==false ? "pravděpodobně" : "možná";
      $msg.= "<br>$mira ($dupi) duplicitní s osobou $i"
          . ($xi->org==1 ? " z YS" : ($xi->org==2 ? " z FA" : ''));
      $keys.= "$del$i";
      $del= ',';
    }
  }
  $ret->msg= $msg;
  $ret->dup= $dup;
  $ret->keys= $keys;
end:
//                                                 debug($ret,$idoo);
  return $ret;
}
/** ------------------------------------------------------------------------------ ucast2_browse_ask **/
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
      WHERE $cond $AND
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
        SUBSTR(MIN(CONCAT(IF(role='','?',role),id_rodina)),2) AS _kmen
      FROM osoba AS o
      JOIN tvori USING(id_osoba)
      JOIN rodina AS r USING(id_rodina)
      WHERE o.deleted='' AND id_osoba IN (0$osoby)
      GROUP BY id_osoba
    ");
    while ( $qor && ($or= mysql_fetch_object($qor)) ) {
      if ( !isset($osoba[$or->id_osoba]) ) $osoba[$or->id_osoba]= (object)array();
      $osoba[$or->id_osoba]->_rody= $or->_rody;
      $kmen= $or->_kmen;
      $osoba[$or->id_osoba]->_kmen= $kmen;
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
            if ( 1 ) {
              $p_access|= $o->access;
            }
          }
          # ==> .. duplicita členů
          $keys= $dup= '';
          if ( $duplicity ) {
            $ret= ucast2_chain_oso($ido);
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
      if ( $idr && !$duplicity && $rodina[$idr]->fotka ) { $z->_docs.= 'f'; }
      # ==> .. duplicity
      if ( $duplicity ) {
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
    # případné řazení
    if ( $x->order && count($zz)>0 ) {
      $test_clmn= substr($x->order,2);
      $test_asc= substr($x->order,0,1)=='a' ? 1 : -1;
      // výběr řazení: numerické | alfanumerické
      $numeric= in_array($test_clmn,array('skupina'));
      if ( $numeric ) {
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
            $c= $test_asc * strcoll($a->$test_clmn,$b->$test_clmn);
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
//                                                 debug($y->values);
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
# ====================================================================================> . autoselect
# ------------------------------------------------------------------------------- ucast2_auto_rodiny
# SELECT autocomplete - výběr ze jmen rodin
function ucast2_auto_rodiny($patt,$par) {  #trace();
  $a= (object)array();
  $limit= 20;
  $dnes= date("Y-m-d");
  $n= 0;
//   if ( $par->patt!='whole' ) {
//     $is= strpos($patt,' ');
//     $patt= $is ? substr($patt,0,$is) : $patt;
//   }
  // jména rodin
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
                                                                debug($a,$patt);
  return $a;
}
# =======================================================================================> . pomocné
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
  if ( $je ) { $ret->msg= "osoba už na této akci je"; goto end; }
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
    if ( $je ) { $ret->msg= "osoba už v této rodině je"; goto end; }
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
/** =========================================================================================> EVID2 **/
# --------------------------------------------------------------------------------------- evid2_cleni
# hledání a) osoby a jejích rodin b) rodiny (pokud je id_osoba=0)
function evid2_cleni($id_osoba,$id_rodina,$filtr) { trace();
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
/** ==========================================================================================> STA2 **/
# ================================================================================> . sta2_struktura
# tabulka struktury kurzu (noví,podruhé,vícekrát,odpočívající VPS,VPS)
function sta2_struktura($org,$par,$title,$export=false) {
  $par->fld= 'nazev';
  $par->tit= 'nazev';
  $tab= sta2_akcnost_vps($org,$par,$title,true);
//                                                    debug($tab,"evid_sestava_v(,$title,$export)");
  $clmn= $suma= array();
  $tit= "rok,rodin,u nás - noví,podruhé,vícekrát,vps - odpočívající,ve službě,dětí na kurzu";
  $tits= explode(',',$tit);
  $fld= "rr,x,n,p,v,vo,vs,d";
  $flds= explode(',',$fld);
  $flds_rr= explode(',',substr($fld,3));
  for ($rrrr=date('Y');$rrrr>=1990;$rrrr--) {
    $rr= substr($rrrr,-2);
    $clmn[$rr]= array('rr'=>$rrrr,'x'=>0);
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
  // doplnění počtů dětí
  $qry= "SELECT YEAR(datum_od) AS _rok,id_akce,count(DISTINCT id_pobyt) AS _pary,
           SUM(IF(role='d',1,0)) AS _deti
         FROM pobyt AS p
         JOIN spolu AS s USING(id_pobyt)
         JOIN osoba AS o ON o.id_osoba=s.id_osoba
         JOIN tvori AS t ON t.id_osoba=o.id_osoba
         JOIN akce AS a ON a.id_duakce=p.id_akce
         WHERE a.druh=1 AND p.funkce IN (0,1) AND spec=0 AND a.access & $org
         GROUP BY id_akce";
  $res= mysql_qry($qry);
  while ( $res && ($x= mysql_fetch_object($res)) ) {
    $rr= substr($x->_rok,-2);
    $clmn[$rr]['d']+= $x->_deti;
    $clmn[$rr]['x']+= $x->_pary;
  }
  // smazání prázdných
  foreach ($clmn as $r=>$c) {
    if ( !$c['x'] ) unset($clmn[$r]);
  }

//                                         debug($suma,"součty");
//                                                         debug($clmn,"evid_sestava_s:$tit;$fld");
  $par->tit= $tit;
  $par->fld= $fld;
  $par->grf= "x:n,p,v,vo,vs,d";
  $par->txt= "Pozn. Graficky je znázorněn relativní počet vzhledem k počtu párů.;
    <br>Pokud v nějakém roce bylo více běhů je zobrazen jejich součet.";
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
          $g= $c[$norm] ? round(100*($c[$f]/$c[$norm]),0) : 0;
          $g= "<img src='skins/$skin/pixel.png'
            style='height:4px;width:{$g}px;float:left;margin-top:5px'>";
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
# ---------------------------------------------------------------------------------- sta2_ukaz_osobu
# zobrazí odkaz na osobu v evidenci
function sta2_ukaz_osobu($ido,$barva='') {
  $style= $barva ? "style='color:$barva'" : '';
  return "<b><a $style href='ezer://db2.evi.evid_osoba/$ido'>$ido</a></b>";
}
# --------------------------------------------------------------------------------- sta2_ukaz_rodinu
# zobrazí odkaz na rodinu v evidenci
function sta2_ukaz_rodinu($idr,$barva='') {
  $style= $barva ? "style='color:$barva'" : '';
  return "<b><a $style href='ezer://db2.evi.evid_rodina/$idr'>$idr</a></b>";
}
# ---------------------------------------------------------------------------------- sta2_ukaz_pobyt
# zobrazí odkaz na řádek s pobytem
function sta2_ukaz_pobyt($idp,$barva='') {
  $style= $barva ? "style='color:$barva'" : '';
  return "<b><a $style href='ezer://db2.ucast.ucast_pobyt/$idp'>$idp</a></b>";
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
# --------------------------------------------------------------------------------------- sta2_table
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
          $tab.= "<td style='text-align:right'>".sta2_ukaz_osobu($c[$f])."</td>";
        elseif ( $f=='^id_rodina' )
          $tab.= "<td style='text-align:right'>".sta2_ukaz_rodinu($c['^id_rodina'])."</td>";
        elseif ( $f=='^id_pobyt' )
          $tab.= "<td style='text-align:right'>".sta2_ukaz_pobyt($c['^id_pobyt'])."</td>";
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
# generování tabulky do excelu
function sta2_excel($org,$title,$par,$tab=null) {  trace();
  global $xA, $xn;
  $result= (object)array('_error'=>0);
  $html= '';
  // získání dat
  $title= str_replace('&nbsp;',' ',$title);
  $subtitle= "ke dni ".date("j. n. Y");
  if ( !$tab ) {
    $tab= sta2_sestava($org,$title,$par,true);
    $title= $par->title ?: $title;
  }
  // vlastní export do Excelu
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
# rozdělí klíče v řetězci pro elim_rodiny na dvě půlky
function elim2_split_keys($keys) { trace();
  $ret= (object)array('c1'=>'','c2'=>'');
  $k1= $k2= array();
  foreach (explode(';',$keys) as $cs) {
    $cs= explode(',',$cs);
    $c0= array_shift($cs);
                                                        debug($cs,$c0);
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
                                                        debug($ret);
  return $ret;
}
# ------------------------------------------------------------------------------------- elim2_differ
# do _track potvrdí, že $id_orig,$id_copy jsou různé osoby nebo rodiny
function elim2_differ($id_orig,$id_copy,$table) { trace();
  global $USER;
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
# -------------------------------------------------------------------------------------- elim2_osoba
# zamění všechny výskyty kopie za originál v TVORI, SPOLU, DAR, PLATBA, MAIL a kopii smaže
function elim2_osoba($id_orig,$id_copy) { //trace();
  global $USER;
  $ret= (object)array('err'=>'');
  $now= date("Y-m-d H:i:s");
  query("UPDATE tvori  SET id_osoba=$id_orig WHERE id_osoba=$id_copy");
  query("UPDATE spolu  SET id_osoba=$id_orig WHERE id_osoba=$id_copy");
  query("UPDATE dar    SET id_osoba=$id_orig WHERE id_osoba=$id_copy");
  query("UPDATE platba SET id_osoba=$id_orig WHERE id_osoba=$id_copy");
  // smazání kopie
  query("UPDATE osoba SET deleted='D osoba=$id_orig' WHERE id_osoba=$id_copy");
  // opravy v originálu
  $access_orig= select("access","osoba","id_osoba=$id_orig");
  $access_copy= select("access","osoba","id_osoba=$id_copy");
  $access= $access_orig | $access_copy;
  query("UPDATE osoba SET access=$access WHERE id_osoba=$id_orig");
  // zápis o ztotožnění osob do _track jako op=d (duplicita)
  $user= $USER->abbr;
  query("INSERT INTO _track (kdy,kdo,kde,klic,fld,op,old,val)
         VALUES ('$now','$user','osoba',$id_orig,'','d','osoba',$id_copy)");    // d=duplicita
  // zápis o smazání kopie do _track jako op=x (eXtract)
  query("INSERT INTO _track (kdy,kdo,kde,klic,fld,op,old,val)
         VALUES ('$now','$user','osoba',$id_copy,'','x','kopie',$id_orig)");    // x=smazání
end:
  return $ret;
}
# --------------------------------------------------------------------------------------- elim2_clen
# zamění všechny výskyty kopie za originál v TVORI, SPOLU, DAR, PLATBA, MAIL a kopii smaže
function elim2_clen($id_rodina,$id_orig,$id_copy) { trace();
  global $USER;
  $ret= (object)array('err'=>'');
  $now= date("Y-m-d H:i:s");
  query("DELETE FROM tvori WHERE id_rodina=$id_rodina AND id_osoba=$id_copy");
  query("UPDATE spolu  SET id_osoba=$id_orig WHERE id_osoba=$id_copy");
  query("UPDATE dar    SET id_osoba=$id_orig WHERE id_osoba=$id_copy");
  query("UPDATE platba SET id_osoba=$id_orig WHERE id_osoba=$id_copy");
  // smazání kopie
  query("UPDATE osoba SET deleted='D osoba=$id_orig' WHERE id_osoba=$id_copy");
  // opravy v originálu
  $access_orig= select("access","osoba","id_osoba=$id_orig");
  $access_copy= select("access","osoba","id_osoba=$id_copy");
  $access= $access_orig | $access_copy;
  query("UPDATE osoba SET access=$access WHERE id_osoba=$id_orig");
  // zápis o ztotožnění osob do _track jako op=d (duplicita)
  $user= $USER->abbr;
  query("INSERT INTO _track (kdy,kdo,kde,klic,fld,op,old,val)
         VALUES ('$now','$user','osoba',$id_orig,'','d','osoba',$id_copy)");
  // zápis o smazání kopie do _track jako op=x (eXtract)
  query("INSERT INTO _track (kdy,kdo,kde,klic,fld,op,old,val)
         VALUES ('$now','$user','osoba',$id_copy,'','x','kopie',$id_orig)");
end:
  return $ret;
}
# ------------------------------------------------------------------------------------- elim2_rodina
# zamění všechny výskyty kopie za originál v POBYT, TVORI, DAR, PLATBA, MAIL a kopii smaže
function elim2_rodina($id_orig,$id_copy) { trace();
  global $USER;
  $ret= (object)array('err'=>'');
  if ( $id_orig!=$id_copy ) {
    $now= date("Y-m-d H:i:s");
    query("UPDATE pobyt  SET i0_rodina=$id_orig WHERE i0_rodina=$id_copy");
    query("UPDATE tvori  SET id_rodina=$id_orig WHERE id_rodina=$id_copy");
    query("UPDATE dar    SET id_rodina=$id_orig WHERE id_rodina=$id_copy");
    query("UPDATE platba SET id_rodina=$id_orig WHERE id_rodina=$id_copy");
    // smazání kopie
    query("UPDATE rodina SET deleted='D rodina=$id_orig' WHERE id_rodina=$id_copy");
    // opravy v originálu
    $access_orig= select("access","rodina","id_rodina=$id_orig");
    $access_copy= select("access","rodina","id_rodina=$id_copy");
    $access= $access_orig | $access_copy;
    query("UPDATE rodina SET access=$access WHERE id_rodina=$id_orig");
    // zápis o ztotožnění rodin do _track jako op=d (duplicita)
    $user= $USER->abbr;
    query("INSERT INTO _track (kdy,kdo,kde,klic,fld,op,old,val)
           VALUES ('$now','$user','rodina',$id_orig,'','d','orig',$id_copy)");
    // zápis o smazání kopie do _track jako op=x (eXtract)
    query("INSERT INTO _track (kdy,kdo,kde,klic,fld,op,old,val)
           VALUES ('$now','$user','rodina',$id_copy,'','x','kopie',$id_orig)");
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
function elim2_data_osoba($ido) {  //trace();
  $ret= (object)array();
  // načtení změn
  $chng_kdy= $chng_kdo= $chng_val= array();
  $max_kdy= '';
  $zs= mysql_qry("
    SELECT fld,kdo,kdy,val,op
    FROM _track
    WHERE kde='osoba' AND klic=$ido
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
function elim2_data_rodina($idr) {  //trace();
  $ret= (object)array();
  // načtení změn
  $chng_kdy= $chng_kdo= $chng_val= array();
  $max_kdy= '';
  $zs= mysql_qry("
    SELECT fld,kdo,kdy,val,op
    FROM _track
    WHERE kde='rodina' AND klic=$idr
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
# =======================================================================================> . MAILIST
# --------------------------------------------------------------------------------- mail2_lst_access
# vrátí údaje daného maillistu s provedenou substitucí podle access uživatele
function mail2_lst_access($id_mailist) {  trace();
  global $USER;                                         // debug($USER);
  $ml= select_object('*','mailist',"id_mailist=$id_mailist");
  if ( !strpos($ml->sexpr,'[HAVING_ACCESS]') ) {
    fce_warning("dotaz zatím není uzpůsoben pro obě databáze - stačí jej znovu uložit");
    $ml->warning= 1;
    goto end;
  }
  $ml->sexpr= str_replace('&lt;','<',str_replace('&gt;','>',$ml->sexpr));
  // doplnění práv uživatele
  $ml->sexpr= str_replace('[HAVING_ACCESS]',"HAVING o.access&{$USER->access}",$ml->sexpr);
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
function mail2_lst_read($parm) { trace();
  global $json;
  $obj= $json->decode($parm);
  $obj= isset($obj->ano_akce) ? $obj : 0;
  return $obj;
}
# ------------------------------------------------------------------------------------ mail2_lst_try
# mode=0 -- spustit a ukázat dotaz a také výsledek
# mode=1 -- zobrazit argument jako html
function mail2_lst_try($gq,$mode=0) { trace();
  global $USER;                                         // debug($USER);
  $access= $USER->access;
  $html= $del= '';
  switch ($mode) {
  case 0:
    $n= $nw= $nm= $nx= 0;
    $gq= str_replace('&gt;','>',$gq);
    $gq= str_replace('&lt;','<',$gq);
    // doplnění práv uživatele
    $gq= str_replace('[HAVING_ACCESS]',"HAVING o.access&$access",$gq);
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
# ========================================================================================> . EMAILY
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
function mail2_mai_text($id_dopis) {  trace();
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
  $emaily= $ids= $jmena= $pobyty= array();
  $spatne= $nema= $mimo= $nomail= '';
  $n= $ns= $nt= $nx= $mx= $nm= 0;
  $dels= $deln= $delm= $delnm= '';
  $nazev= '';
  switch ($dopis_var) {
  // mail-list
  case 'G':
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
  // obecný SQL dotaz - skupina
  case 'Q':
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
  // účastníci akce
  case 'U3':    // dlužníci
  case 'U2':    // sloužící
  case 'U':
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
  // projdi adresy
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
  $result->_html= $nt>0
    ? "Opravdu vygenerovat seznam pro rozeslání\n'$nazev'\nna $nt adres?"
    : "Mail '$nazev' nemá žádného adresáta, stiskni ZRUŠIT";
  $result->_html.= "\n\n$html";
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
    function href($fnames) {
      global $ezer_root;
      $href= array();
      foreach(explode(',',$fnames) as $fnamesize) {
        list($fname)= explode(':',$fnamesize);
        $href[]= "<a href='docs/$ezer_root/$fname' target='pdf'>$fname</a>";
      }
      return implode(', ',$href);
    }
    list($obsah,$prilohy)= select('obsah,prilohy','dopis',"id_dopis=$id_dopis");
    $priloha= select('priloha','mail',"id_mail=$id_mail");
    $c= select("*",'osoba',"id_osoba=$id");
    $html.= "{$c->id_osoba}: {$c->jmeno} {$c->prijmeni}<br>";
    $html.= "{$c->ulice}, {$c->psc} {$c->obec}<br><br>";
    if ( $c->telefony )
      $html.= "Telefon: {$c->telefony}<br>";
    // přílohy ke kontrole
    if ( $prilohy )
      $html.= "<br>Společné přílohy: ".href($prilohy);
    if ( $priloha )
      $html.= "<br>Vlastní přílohy: ".href($priloha);
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
  $t= "$nazev, stav ke dni $dnes";
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
    foreach ($g as $f => $val) {
      if ( in_array($f,$fields) ) {
        $a= $val;
        if ( isset($pipe[$f]) ) $a= $pipe[$f]($a);
        $values[$f]= $a;
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
    'mailist' => array(         'id_mailist'),
    'osoba' =>   array('access','id_osoba'),
    'platba' =>  array(         'id_platba','id_osoba','id_rodina','id_duakce','id_pokl'),
    'pobyt' =>   array(         'id_pobyt','id_akce','i0_rodina'),
    'rodina' =>  array('access','id_rodina'),
    'spolu' =>   array(         'id_spolu','id_pobyt','id_osoba'),
    'tvori' =>   array(         'id_tvori','id_rodina','id_osoba'),
    // systém
    '_user' =>  array('id_user'),
    '_track' => array('id_track','klic'),
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
          $updt= substr($updt,1);
          $ok= mysql_qry("UPDATE ezer_db2._tmp_ SET $updt ORDER BY $main DESC");
          $nr= mysql_affected_rows();
        }
        if ( $ok ) $ok= mysql_qry("INSERT INTO ezer_db2.$tab SELECT * FROM ezer_db2._tmp_");
        if ( $ok ) {
          $html.= "<br>$tab: vloženo $nr záznamů";
        }
        mysql_qry("DROP TABLE IF EXISTS ezer_db2._tmp_");
      }
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
          'a ah f fa faa faa+ faa:c faan fad fae fam fam famg fams d m mg r sp spk spv test')");
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
    default:
      fce_error("transformaci $cmd neumím");
    }
  }
  return $html;
}
?>
