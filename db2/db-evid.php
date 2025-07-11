<?php

# -------------------------------------------------------------------------------- akce2 auto_jmena1
# SELECT autocomplete - výběr z dospělých osob, pokud je par.deti=1 i z deti
function akce2_auto_jmena1($patt,$par) {  #trace();
  $a= array();
  $limit= 20;
  $n= 0;
  if ( $par->patt!='whole' ) {
    $is= strpos($patt,' ');
    $patt= $is ? substr($patt,0,$is) : $patt;
  }
  // osoby
  $AND= $par->deti ? '' 
      : "AND (narozeni='0000-00-00' OR IF(MONTH(narozeni),DATEDIFF(NOW(),narozeni)/365.2425,YEAR(NOW())-YEAR(narozeni))>15)";
  $qry= "SELECT prijmeni, jmeno, id_osoba AS _key
         FROM osoba
         LEFT JOIN tvori USING(id_osoba)
         WHERE deleted='' AND concat(trim(prijmeni),' ',jmeno) LIKE '$patt%' AND prijmeni!='' $AND
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
# ------------------------------------------------------------------------------- akce2 auto_jmena1L
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
  $res= pdo_qry($qry);
  if ( $res && $p= pdo_fetch_object($res) ) {
    $nazev= "$p->prijmeni $p->jmeno / $p->rok, $p->obec, $p->ulice, $p->email, $p->telefon";
    $osoba[]= (object)array('nazev'=>$nazev,'id'=>$id_osoba,'role'=>$p->role);
  }
//                                                                 debug($osoba,$id_akce);
  return $osoba;
}
# ------------------------------------------------------------------------------------- evid2 delete
# zjistí, zda lze osobu smazat: dar, platba, spolu, tvori
# cmd= conf_oso|conf_rod|del_oso|del_rod
function evid2_upd_gmail($id_osoba,$orig,$gmail) { trace();
  ezer_qry("UPDATE",'osoba',$id_osoba,array(
    (object)array('fld'=>'gmail', 'op'=>'u','val'=>$gmail,'old'=>$orig)
  ));
  return 1;
}
# ------------------------------------------------------------------------------------- evid2 delete
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
//    $x= select1('SUM(castka)','platba',"id_osoba=$id_osoba");
//    if ( $x) $duvod[]= "zaplatil$a $x Kč";
    $xr= pdo_qry("SELECT COUNT(*) AS _x_ FROM spolu JOIN pobyt USING (id_pobyt)
                    JOIN akce ON id_akce=id_duakce WHERE id_osoba=$id_osoba AND spec=0 AND zruseno=0");
    list($x)= pdo_fetch_array($xr);
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
    $rs= query("UPDATE osoba JOIN tvori USING (id_osoba) SET deleted='D' WHERE id_rodina=$id_rodina");
    $no= pdo_affected_rows($rs);
    $rs= query("UPDATE rodina SET deleted='D' WHERE id_rodina=$id_rodina");
    $nr= pdo_affected_rows($rs);
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
# --------------------------------------------------------------------------------------- evid2 cleni
# hledání a) osoby a jejích rodin b) rodiny (pokud je id_osoba=0)
function evid2_cleni($id_osoba,$id_rodina,$filtr) { //trace();
  global $USER;
  $access= 0 + $USER->access;
  $msg= '';
  $cleni= "";
  $rodiny= array();
  $rodina= $rodina1= $id_rodina;
  // pouze při použití filtru na služby během pobytu přidej tabulky spolu, pobyt
  $join_pobyt= strpos($filtr,"AND funkce=")!==false 
      ? "LEFT JOIN spolu AS os ON os.id_osoba=o.id_osoba
        LEFT JOIN pobyt AS op USING (id_pobyt)"
      : "";
//   $id_osoba ? "o.id_osoba=$id_osoba" : "r.id_rodina=$id_rodina";
  if ( $id_osoba ) { // ------------------------ osoby
    $clen= array();
    $css= array('','ezer_ys','ezer_fa','ezer_db');
    $qc= pdo_qry("
      SELECT rto.id_osoba,rto.jmeno,rto.prijmeni,rto.narozeni,rto.access AS o_access,
        rt.id_tvori,rt.role,o.deleted,of.id_rodina,nazev,of.access AS r_access
      FROM osoba AS o
        JOIN tvori AS ot ON ot.id_osoba=o.id_osoba
        LEFT JOIN dar AS od ON od.id_osoba=o.id_osoba AND od.deleted=''
        JOIN rodina AS of ON of.id_rodina=ot.id_rodina -- AND of.access & $access
        JOIN tvori AS rt ON rt.id_rodina=of.id_rodina
        JOIN osoba AS rto ON rto.id_osoba=rt.id_osoba
        $join_pobyt 
      WHERE o.id_osoba=$id_osoba AND $filtr -- AND rto.access & $access
      ORDER BY rt.role,rto.narozeni
    ");
    while ( $qc && ($c= pdo_fetch_object($qc)) ) {
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
    $qc= pdo_qry("
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
      WHERE r.id_rodina=$id_rodina AND $filtr 
        -- AND (rto.access & $access OR rto.access=0) // MSM povolil 240628
      GROUP BY id_osoba
      ORDER BY rt.role,rto.narozeni
    ");
    while ( $qc && ($c= pdo_fetch_object($qc)) ) {
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
# ----------------------------------------------------------------------------- evid2 browse_act_ask
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
    $qp= pdo_qry("
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
    while ( $qp && ($p= pdo_fetch_object($qp)) ) {
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
# ---------------------------------------------------------------------------- evid2 pridej_k_rodine
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
      $ret->tvori= pdo_insert_id();
      $ret->ok= 1;
    }
    else  $ret->msg= 'chyba při vkládání';
  }
  return $ret;
}
# -------------------------------------------------------------------------- evid2 spoj_osoba_rodina
# ASK přidání do dané rodiny, pokud ještě osoba v rodině není
# pokud idr, ido nejsou jediné klíče, vrátí ok=0
# pro spoj = 0 vrátí otázku
function evid2_spoj_osoba_rodina($ido1,$ido2,$idr1,$idr2,$spoj) { trace();
  $ret= (object)array('ok'=>0,'msg'=>'');
  $ido= $ido1 ?: $ido2;
  $idr= $idr1 ?: $idr2;
                                                display("ido=$ido,idr=$idr");
  if ( !is_numeric($idr) || !is_numeric($ido) ) goto end;
  $je= select("COUNT(*)","tvori","id_rodina=$idr AND id_osoba=$ido");
  if ( $je ) goto end;
  // jinak je spoj, nebo se poptej
  $ret->ok= 1;
  if ( $spoj ) {
    query("INSERT INTO tvori (id_rodina,id_osoba,role) VALUE ($idr,$ido,'d')");
  }
  else {
    $o= select1("concat(prijmeni,' ',jmeno,'/',id_osoba)",'osoba',"id_osoba=$ido");
    $r= select1("concat(nazev,'/',id_rodina)",'rodina',"id_rodina=$idr");
    $ret->msg= "Opravdu mám spojit rodinu $r a osobu $o? (role bude nastavena jako 'd' - nutno upravit)";
  }
end:
  return $ret;
}
# ==========================================================================================> . YMCA
# ---------------------------------------------------------------------------==> .. evid2 ymca darci
# správa dárců pro YS
function evid2_ymca_darci($org,$kdy='loni') {
  $html= '';
  $min= 4999;
  $rok= $kdy=='loni' ? date('Y')-1 : date('Y');
  $tits= explode(',','jméno:20,dar:10,činný č.:8,telefon:12,mail:40,ID');
              debug($tits,'1');
  $flds= explode(',','jmeno,dar,cc,telefon,mail,id_osoba');
  $clmn= array();
  $rd= pdo_qry("SELECT id_osoba,CONCAT(jmeno,' ',prijmeni),kontakt,email,telefon,
      SUM(IF(ukon='d',castka,0)) AS _dar,SUM(IF(ukon='p',1,0))
    FROM dar JOIN osoba USING (id_osoba) 
    WHERE ukon IN ('d','p') AND YEAR(dat_od)=$rok AND dar.access=$org
    GROUP BY id_osoba HAVING _dar>$min
    ORDER BY _dar DESC
  ");
  $html.= "<table>";
  while ($rd && list($ido,$jmeno,$k,$email,$telefon,$dar,$cc)= pdo_fetch_array($rd)) {
    $clmn[]= array('jmeno'=>$jmeno,'dar'=>$dar,'cc'=>$cc==1?'ano':'ne',
        'telefon'=>$k==1?$telefon:'','mail'=>$k==1?$email:'','id_osoba'=>$ido);
  }
  // tisk přes sta2_excel_export
  $ret= sta2_table($tits,$flds,$clmn);
  $tab= (object)array('tits'=>$tits,'flds'=>$flds,'clmn'=>$clmn);
  $rete= sta2_excel_export("Štědří dárci roku $rok",$tab);
  return "$rete->html<br><br>$ret->html";
}
# --------------------------------------------------------------------------==> .. evid2 ymca sprava
# správa členů pro YS
function evid2_ymca_sprava($org,$par,$title,$export=false,$akce=0) {
  $ret= (object)array('error'=>0,'html'=>'');
  switch ($par->op) {
  case 'hlaseni':
  case 'letos':
  case 'loni':
    $ret= evid2_ymca_sestava($org,$par,$title,$export);
//                                                         debug($ret,"evid2_ymca_sestava");
    break;
  case 'Valna-schuze':
    $ret= evid2_ymca_sestava($org,$par,$title,0,$akce);
    $ret1= evid2_ymca_sestava($org,$par,$title,1,$akce);
    $ret2= sta2_excel_export($title,$ret1);
    $ret->html= "{$ret2->html}<br>{$ret->html}";
    break;
  case 'zmeny':
    $ret= evid2_recyklace_pecounu($org,$par,$title,0);
    break;
  }
  // případně export do Excelu
  return $export ? sta2_excel_export($title,$ret) : $ret;
//   return $ret;
}
# ------------------------------------------------------------------------------- evid2 ymca_sestava
# generování přehledu členstva
#   $fld = seznam položek s prefixem
#   $cnd = podmínka
# _clen_od,_cinny_od,_prisp,_dary
function evid2_ymca_sestava($org,$par,$title,$export=false,$akce=0) {
  $ret= (object)array('html'=>'','err'=>'');
  $rok= date('Y') - (isset($par->rok) ? $par->rok : 0);
  // dekódování parametrů
  $tits= explode(',',$par->tit);
  $flds= explode(',',$par->fld);
  $jen_cinni= $par->jen_cinni ? 1 : 0;
  $jen_akce= $akce ? "id_akce=$akce" : '1';
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
  $clenu= $cinnych= $cinnych_cr= $prispevku= $daru= $dobrovolniku= $novych= $novych_cr= 0;
  $msg= $akce ? '' : "<br><br>Ve výběru jsou účastníci akcí roku $rok";
  $qry= "SELECT
           MAX(IF(YEAR(datum_od)=$rok AND p.funkce=1,1,0)) AS _vps,
           MAX(IF(YEAR(datum_od)=$rok AND p.funkce=99,1,0)) AS _pec,
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
           AND os.access&$org AND od.access&$org AND a.access&$org AND $jen_akce
        -- AND os.id_osoba=91 -- Jan Baletka
         GROUP BY os.id_osoba HAVING {$par->hav}
         ORDER BY os.prijmeni";
  $res= pdo_qry($qry);
  while ( $res && ($x= pdo_fetch_object($res)) ) {
    $id_osoba= $x->id_osoba;
    $honorabilis= 0;
    // rozbor úkonů
    $_clen_od= $_cinny_od= $_cinny_cr_od= $_prisp= $prisp_letos= $_dary= 0;
    foreach(explode('|',$x->_ukony) as $uddc) {
      list($u,$d1,$d2,$c)= explode(':',$uddc);
      switch ($u) {
      case 'p': if ( $d1==$rok ) $_prisp+= $c; break;
      case 'd': if ( $d1==$rok ) $_dary+= $c; break;
      case 'H': $honorabilis++; 
      case 'b': if ( $d2<=$rok && (!$_clen_od && $d1<=$rok || $d1<$_clen_od) ) $_clen_od= $d1; break;
      case 'c': if ( $d2<=$rok && (!$_cinny_od && $d1<=$rok || $d1<$_cinny_od) ) $_cinny_od= $d1; break;
      case 'Y': if ( $d2<=$rok && (!$_cinny_cr_od && $d1<=$rok || $d1<$_cinny_cr_od) ) $_cinny_cr_od= $d1; break;
      }
    }
//                                 display("$x->prijmeni $x->jmeno: clen_od=$_clen_od, cinny_od=$_cinny_od, cinny_cr_od=$_cinny_cr_od, prisp=$_prisp, dary=$_dary");
    $prispevku+= $_prisp;
    $daru+= $_dary;
    if ( !($_clen_od || $_cinny_od || $_cinny_cr_od)) continue;
    if ($jen_cinni && !($_cinny_od || $_cinny_cr_od)) continue;
    $clenu+= $_clen_od ? 1 : 0;
    $cinnych+= $_cinny_od ? 1 : 0;
    $cinnych_cr+= $_cinny_cr_od ? 1 : 0;
    $dobrovolniku+= $x->_vps && $_cinny_od>0 || $_cinny_cr_od>0 || $x->_pec ? 1 : 0;
    $novych+= $_cinny_od==$rok ? 1 : 0;
    $novych_cr+= $_cinny_cr_od==$rok ? 1 : 0;
    // pokračujeme jen s členy
    $n++;
    $clmn[$n]= array();
    foreach($flds as $f) {
//  '1,prijmeni,jmeno,ulice,obec,psc,_naroz,_b_c,_prisp,_YS,_email,_cinny_letos,_dobro'}}
      switch ( $f ) {
      // export
      case '1':         $clmn[$n][$f]= 1; break;
      case '_naroz_ymd':$clmn[$n][$f]= substr($x->narozeni,2,2).substr($x->narozeni,5,2).substr($x->narozeni,8,2); break;
      case '_b_c':      $clmn[$n][$f]= $honorabilis ? 'H' : ($_cinny_cr_od>0 ? 'Y' : ($_cinny_od>0 ? 'č' : 'b')); break;
      case '_YS':       $clmn[$n][$f]= 'YMCA Setkání'; break;
      case '_cinny_letos':$clmn[$n][$f]= $_cinny_od==$rok ? 1 : ''; break;
      case '_cinny_cr_letos':$clmn[$n][$f]= $_cinny_cr_od==$rok ? 1 : ''; break;
      case '_dobro':    $clmn[$n][$f]= $x->_vps && $_cinny_od>0  || $x->_pec ? 1 : ''; break;
      // přehled
      case '_clen_od':  $clmn[$n][$f]= $_clen_od; break;
      case '_cinny_od': $clmn[$n][$f]= $_cinny_od; break;
      case '_cinny_cr_od': $clmn[$n][$f]= $_cinny_cr_od; break;
      case '_prisp':    $clmn[$n][$f]= $_prisp; break;
      case '_dary':     $clmn[$n][$f]= $_dary; break;
      case '_naroz':    $clmn[$n][$f]= sql_date1($x->narozeni); break;
        $clmn[$n][$f]= $del;
        break;
      default:
        $clmn[$n][$f]= $x->$f;
      }
    }
//                    debug($clmn,"$x->prijmeni $x->jmeno");
  }
  // přidání sumarizace
  if (!$akce) {
    $n++;
    $clmn[$n]['obec']= '.SUMA:.';
    $clmn[$n]['_clen_od']= $clenu;
    $clmn[$n]['_cinny_od']= $cinnych;
    $clmn[$n]['_cinny_cr_od']= $cinnych_cr;
    $clmn[$n]['_prisp']= $prispevku;
    $clmn[$n]['_dary']= $daru;
    $clmn[$n]['_dobro']= $dobrovolniku;
    $clmn[$n]['_cinny_letos']= $novych;
    $clmn[$n]['_cinny_cr_letos']= $novych_cr;
//                      debug($clmn,"SUMA");
  }
//   $ret= sta2_table_graph($par,$tits,$flds,$clmn,$export);
  $ret= sta2_table($tits,$flds,$clmn,$export);
end:
  $ret->html.= $msg;
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
  $pr= pdo_qry("
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
  while ( $pr && ($p= pdo_fetch_object($pr)) ) {
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
//                                                        debug($x,"evid2_browse_mailist");
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
    $qs= "SELECT psc,lat,lng FROM psc_axy GROUP BY psc";
    $rs= pdo_qry($qs);
    while ( $rs && ($s= pdo_fetch_object($rs)) ) {
      $psc= $s->psc;
      $lat[$psc]= $s->lat;
      $lng[$psc]= $s->lng;
    }
    # pokus se získat podmínku - zjednodušeno na show=[*,vzor]
    $whr= $hav= array();
    if ( $x->show ) foreach ( $x->show as $fld => $show) {
      $i= 0; $typ= $show->$i;
      $i= 1; $vzor= $show->$i;
      $beg= '^';
      if ($typ!='*') break;
      $end= substr($vzor,-1)=='$' ?'$' : '.*';
      $not= substr($vzor,0,1)=='-';
      if ( $not ) $vzor= substr($vzor,1);
      $vzor= strtr($vzor,array('?'=>'.','*'=>'.*','$'=>''));
      if (in_array($fld,array('psc','obec'))) {
        $hav[]= "_$fld REGEXP '$beg$vzor$end' ";
      }
      elseif (in_array($fld,array('prijmeni','jmeno'))) {
        $whr[]= "o.$fld REGEXP '$beg$vzor$end' ";
      }
    }
//                                                                debug($whr,"WHERE");
//                                                                debug($hav,"HAVING");
    # získej sexpr z mailistu id=c.cond
    list($nazev,$qo,$komu)= select('ucel,sexpr,komu','mailist',"id_mailist={$x->cond}");
    // přidej případnou podmínku 
    if ( count($whr) ) {
      $cond= implode(' AND ',$whr);
      $qo= str_replace("WHERE","WHERE $cond AND ",$qo);
    }
    if ( count($hav) ) {
      $cond= implode(' AND ',$hav);
      $qo= str_replace("ORDER BY","HAVING $cond ORDER BY",$qo);
    }
//                                                                display($qo);
    $ro= pdo_qry($qo);
    while ( $ro && ($o= pdo_fetch_object($ro)) ) {
      $id= $komu=='o' ? $o->_id : $o->_idr;
      if ( $x->selected && !in_array($id,$selected) ) continue;
      if ( $komu=='o' ) {
        list($prijmeni,$jmeno)= explode(' ',$o->_name);
      }
      else {
        $jm= strpos($o->_name,' ');
        $prijmeni= substr($o->_name,0,$jm);
        $jmeno= substr($o->_name,$jm+1);
      }
      $psc= $o->_psc;
      $_lat= isset($lat[$psc]) ? $lat[$psc] : 0;
      $_lng= isset($lng[$psc]) ? $lng[$psc] : 0;
      if ($komu=='o') {
        list($lt,$lg)= select('lat,lng','osoba_geo',"id_osoba=$id");
        if ($lt) { $_lat= $lt; $_lng= $lg; }
      }
      $zz[]= (object)array(
      'id_o'=>$id,
      'lat'=> $_lat,
      'lng'=> $_lng,
      'access'=>$o->access,
      'prijmeni'=>$prijmeni,
      'jmeno'=>$jmeno,
      'ulice'=>$o->_ulice,
      'psc'=>$psc,
      'obec'=>$o->_obec,
      '_vek'=> $komu=='o' ? $o->_vek : $o->_spolu,
      'mail'=>$o->_email,
      'telefon'=>$o->_telefon,
      '_id_o'=>$id
      );
    }
    # ==> ... případný výběr - zjednodušeno na show=[*,vzor]
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
    # ==> ... řazení
    if ( $x->order && count($zz)>0 ) {
      $test_clmn= substr($x->order,2);
      $test_asc= substr($x->order,0,1)=='a' ? 1 : -1;
      // výběr řazení: numerické | alfanumerické
      $numeric= in_array($test_clmn,array('_id_o'));
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
