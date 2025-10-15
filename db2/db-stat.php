<?php

# --------------------------------------------------------------------------- akce2 info_par
# charakteristika účastníků z hlediska páru,
# počítáme pouze v případě, když je definované i0_pobyt
function akce2_info_par($ida,$idp=0,$tab_only=0) { trace();
  $html= $tab= '';
  $typy= array(''=>0,'a'=>0,'b'=>0,'s'=>0,'as'=>0,'bs'=>0,'abs'=>0,'bas'=>0,);
  $neucasti= select1("GROUP_CONCAT(data)",'_cis',"druh='ms_akce_funkce' AND ikona=1");
  // projdeme pobyty a vybereme role 'a' a 'b' - pokud nejsou oba, nelze nic spočítat
  $cond= $idp ? "id_pobyt=$idp " : "1";
  $rp= pdo_qry("
    SELECT GROUP_CONCAT(CONCAT(t.role,s.id_osoba)) AS _par,i0_rodina,id_pobyt
    FROM pobyt AS p
    JOIN spolu AS s USING (id_pobyt)
    LEFT JOIN tvori AS t ON t.id_rodina=i0_rodina AND t.id_osoba=s.id_osoba
    WHERE funkce IN (0,1,2) AND id_akce=$ida AND $cond AND t.role IN ('a','b')
    GROUP BY id_pobyt
  ");
  while ( $rp && $p= pdo_fetch_object($rp) ) {
    if ( !strpos($p->_par,',') ) continue;
    $par= array();
    $ids= '';
    foreach (explode(',',$p->_par) as $r_id) {
      $id= substr($r_id,1);
      $par[$id]= substr($r_id,0,1);
      $ids.= ($ids ? ',' : '').$id;
    }
//                                                 debug($par,count($par)==2);
    $typ= '';
    // probereme účasti na akcích (nepočítáme účasti < 18 let) postupně od nejstarších
//    ezer_connect('ezer_db2',true);
    $rx= pdo_qry("
      SELECT a.id_duakce as ida,p.id_pobyt as idp,
        a.datum_od,a.nazev as akce,p.funkce as fce,a.typ,a.druh,
        GROUP_CONCAT(s.id_osoba) AS _ucast
      FROM akce AS a
      JOIN pobyt AS p ON a.id_duakce=p.id_akce
      JOIN spolu AS s USING (id_pobyt)
      JOIN osoba AS o USING (id_osoba)
      WHERE a.spec=0 AND zruseno=0 AND s.id_osoba IN ($ids) 
        AND YEAR(a.datum_od)-YEAR(o.narozeni)>18
        AND p.funkce NOT IN ($neucasti)
      GROUP BY id_pobyt
      ORDER BY datum_od
    ");
    while ( $rx && $x= pdo_fetch_object($rx) ) {
      // určení účasti na akci: m|z|s
      $ucast= explode(',',$x->_ucast);
      if ( count($ucast)==2 ) {
        $typ.= 's';
        break;
      }
      $ab= $par[$ucast[0]];
//                                                   display($ab);
      // doplnění do typu
      if ( strpos($typ,$ab)===false )
        $typ.= $ab;
    }
//     $html.= "<br>{$p->id_pobyt}:$typ";
    $typy[$typ]++;
  }
  if ( $tab_only ) {
    $ret= $typy;
  }
  else {
    $pocty= 0;
    $tab.= "<div><table class='stat' style='float:left;margin-right:5px;'>";
    $tab.= "<tr><th>postup účastí</th><th> párů </th></tr>";
    foreach ($typy as $typ=>$pocet) {
      $pocty+= $pocet;
      $tab.= "<tr><th>$typ</th><td>$pocet</td></tr>";
    }
    $tab.= "<hr></table>";
    $tab.= "Význam řádků s..bas
        <br>s = již první akce byla společná
        <br>as = napřed byl na nějaké akci muž, pak byli na společné
        <br>bs = napřed byla na nějaké akci žena, pak byli na společné
        <br>abs = napřed byl muž, potom žena, pak společně
        <br>bas = napřed byla žena, potom muž, pak společně
    </div>";
    if ( $pocty ) {
      $html.= $tab;
    }
    $ret= $html;
  }
//                                                 debug($typy);
  return $ret;
}
# -------------------------------------------------------------------------------==> . sta2 mrop vek
# roční statistika účastníků: průměrný věk, byl předtím na MS
function sta2_mrop_vek($par,$export=false) {
  $msg= "<h3>Kolik jich je a jací jsou</h3><i>Poznámka: starší, zjednodušená verze bez CPR aj.</i><br><br>";
  $AND= '';
//   $AND= "AND iniciace=2002 AND o.id_osoba=5877";
  $celkem= 0;
  $styl= " style='text-align:right'";
  $tab= "<div class='stat'><table class='stat'>
         <tr><th>rok</th><th>účastníci</th><th>bylo na MS</th><th>%</th><th>prům. věk</th></tr>";
  $mr= pdo_qry("
    SELECT iniciace,COUNT(*) AS _kolik,SUM(IF(IFNULL(m._ms,0),1,0)) AS _ms, -- _roky,
      ROUND(AVG(IF(MONTH(o.narozeni),DATEDIFF(a.datum_od,o.narozeni)/365.2425,YEAR(a.datum_od)-YEAR(o.narozeni))),1) AS _vek
    FROM osoba AS o
    LEFT JOIN akce AS a ON mrop=1 AND YEAR(datum_od)=iniciace
    LEFT JOIN
    (SELECT mo.id_osoba,datum_od,COUNT(*) AS _ms
      -- ,GROUP_CONCAT(YEAR(datum_od) ORDER BY datum_od) AS _roky
      FROM akce AS ma
      JOIN pobyt AS mp ON mp.id_akce=ma.id_duakce
      JOIN spolu AS ms USING (id_pobyt)
      JOIN osoba AS mo USING (id_osoba)
       WHERE ma.druh=1
        AND YEAR(datum_od)<=iniciace
        -- AND ROUND(DATEDIFF(ma.datum_od,mo.narozeni)/365.2425,1)>18
        AND ROUND(IF(MONTH(mo.narozeni),DATEDIFF(ma.datum_od,mo.narozeni)/365.2425,YEAR(ma.datum_od)-YEAR(mo.narozeni)),1)>18
      GROUP BY id_osoba
      ) AS m ON m.id_osoba=o.id_osoba  -- AND m.datum_od<a.datum_od
    WHERE deleted='' AND iniciace>0 $AND
    GROUP BY iniciace
  ");
  while ( $mr && list($mrop,$ucast,$ms,$vek)= pdo_fetch_row($mr) ) {
    $celkem+= $ucast;
    $pms= round(100*$ms/$ucast);
    $tab.= "<tr><th>$mrop</th><td$styl>$ucast</td><td$styl>$ms</td><td$styl>$pms%</td><td$styl>$vek</td></tr>";
  }
  $tab.= "<tr><th>&Sigma;</th><th>$celkem</th></tr>";
  $tab.= "</table></div>";
  // kontrola položky iniciace a úžasti a akci
  $ehm= '';
  $mr= pdo_qry("
    SELECT id_osoba,YEAR(datum_od) AS _nesmer,iniciace,jmeno,prijmeni
    FROM akce AS a
    LEFT JOIN pobyt AS p ON p.id_akce=a.id_duakce AND funkce=0
    JOIN spolu AS s USING (id_pobyt)
    JOIN osoba AS o USING (id_osoba)
    WHERE a.mrop=1 AND deleted=''
    AND YEAR(datum_od)!=iniciace
  ");
  while ( $mr && list($ido,$nesmer,$iniciace,$jmeno,$prijmeni)= pdo_fetch_row($mr) ) {
    $ehm.= "<br>$jmeno $prijmeni ($ido) byl účastník MROP $nesmer ale má zapsáno jako iniciaci rok $iniciace";
  }
  if ( $ehm ) {
    $tab.= "<br>V datech jsou problémy:<br>$ehm";
  }
  return $msg.$tab;
}
# ------------------------------------------------------------------------------==> . sta2 mrop vliv
# rozbor podle navštěvovaných akcí
function sta2_mrop_vliv($par,$export=false) {
  $msg= "<h3>Odkud přicházejí a kam jdou</h3><i>Poznámka: starší, zjednodušená verze bez CPR aj.</i><br><br>";
  $limit= $AND= '';
//   $AND= "AND iniciace=2002 AND id_osoba=5877";
  // seznam
  $ms= array();
  $mr= pdo_qry("
    SELECT id_osoba,prijmeni,iniciace,COUNT(*)
    FROM osoba
    LEFT JOIN spolu USING (id_osoba)
    WHERE deleted='' AND iniciace>0 $AND
    -- AND id_osoba=6689
    GROUP BY id_osoba
  ");
  while ( $mr && list($ido,$name,$mrop,$spolu)= pdo_fetch_row($mr) ) {
    $ms[$ido]= (object)array('name'=>$name,'mrop'=>$mrop, 'akci'=>$spolu, 'ucast'=>0);
  }
  // vlastnosti
  $akce_muzi= "24,5,11";
  foreach ($ms as $ido=>$m) {
    $ma= pdo_qry("
      SELECT
        CASE WHEN druh=1 THEN 100 WHEN druh IN ($akce_muzi) THEN 10 ELSE 1 END AS _druh,
        COUNT(*),
        IF(MIN(IFNULL(YEAR(datum_od),9999))<=iniciace,1,0) AS _pred,
        IF(MAX(IFNULL(YEAR(datum_od),0))>iniciace,1,0) AS _po
      FROM pobyt AS p
      LEFT JOIN akce AS a ON id_akce=id_duakce
      LEFT JOIN spolu AS s USING (id_pobyt)
      JOIN osoba AS o USING (id_osoba)
      WHERE id_osoba=$ido AND spec=0 AND mrop=0 AND zruseno=0
      GROUP BY _druh ORDER BY _druh DESC
    ");
    while ( $ma && list($druh,$kolikrat,$pred,$po)= pdo_fetch_row($ma) ) {
      $m->ucast+= $druh;
      switch ($druh) {
      case 100: $m->ms_pred= $pred*$druh; $m->ms_po= $po*$druh;  break; // MS
      case  10: $m->m_pred=  $pred*$druh; $m->m_po=  $po*$druh;  break; // muži, otcové
      case   1: $m->j_pred=  $pred*$druh; $m->j_po=  $po*$druh;  break; // jiné
      case   0:   break; // žádné
      }
    }
    // první účast
    $m->pred= $m->ms_pred + $m->m_pred + $m->j_pred;
    $m->po=   $m->ms_po   + $m->m_po   + $m->j_po;
  }
//                                                         debug($ms);
  // statistický souhrn
  $muzu= count($ms);
  $ucast= $pred= $po= array();
  foreach ($ms as $ido=>$m) {
    // účastníci
    $ucast[$m->ucast]++;
    $pred[$m->pred]++;
    $po[$m->po]++;
  }
//                                                        debug($ucast,'ucast');
//                                                        debug($pred,'před');
//                                                        debug($po,'po');
  $c_pred= $c_po= $c_ucast= 0;
  $styl= " style='text-align:right'";
  $tab= "<div class='stat'><table class='stat'>
         <tr><th>typ akce</th><th>před MROP</th><th>po MROP</th><th>mimo MROP</th></tr>";
  foreach (
    array(111=>'MS+M+J',110=>'MS+M',101=>'MS+J',100=>'MS',11=>'M+J',10=>'M',1=>'J',0=>'žádná') as $k=>$i) {
    $tab.= "<tr><th>$i</th><td$styl>{$pred[$k]}</td><td$styl>{$po[$k]}</td><td$styl>{$ucast[$k]}</td></tr>";
    $c_pred+= $pred[$k];
    $c_po+= $po[$k];
    $c_ucast+= $ucast[$k];
  }
  $tab.= "<tr><th>&Sigma;</th><th$styl>$c_pred</th><th$styl>$c_po</th><th$styl>$c_ucast</th></tr>";
  $tab.= "</table>";

  $msg.= "Celkem $muzu iniciovaných mužů<br><br>";
  $msg.= $tab;
  $msg.= "<br><br>MS znamená účast na Manželských setkání, M účast na akci pro muže nebo otce,
         J účast na jiné akci";
  return $msg;
}
# ====================================================================================> . sta2 cesty
# tabulka struktury kurzu (noví,podruhé,vícekrát,odpočívající VPS,VPS)
# par.od= rok počátku statistik
function sta2_cesty($org,$par,$title,$export=false) {
  $od_roku= isset($par->od) ? $par->od : 0;
  $par->fld= 'nazev';
  $par->tit= 'nazev';
//                                                   debug($par,"sta2_cesty(,$title,$export)");
  $clmn= $suma= array();
  $tit= "rok,rodin,s,as,bs,abs,bas";
  $tits= explode(',',$tit);
  $fld= "rr,u,s,as,bs,abs,bas";
  $flds= explode(',',$fld);
  $flds_rr= explode(',',substr($fld,3));
  for ($rrrr=date('Y');$rrrr>=1990;$rrrr--) {
    if ( $rrrr<$od_roku ) continue;
    $rr= substr($rrrr,-2);
    $clmn[$rr]= array('rr'=>$rrrr,'u'=>0);
    $ida= select1("id_duakce","akce","druh=1 AND spec=0 AND zruseno=0 
      AND YEAR(datum_od)=$rrrr AND access&$org");
    if (!$ida) continue;
    $tab= akce2_info_par($ida,0,1);
    foreach (explode(',',"s,as,bs,abs,bas") as $i) {
      $clmn[$rr]['u']+= $tab[$i];
      $clmn[$rr][$i]= $tab[$i];
    }
//     $clmn[$rr]['u']+= $ida;
  }
  $par->tit= $tit;
  $par->fld= $fld;
  $par->grf= "u:n,as,bs,abs,bas";
  $par->txt= "Pozn. Graficky je znázorněn absolutní počet." //relativní počet vzhledem k počtu párů.;
    . "<br>Pokud v nějakém roce bylo více běhů je zobrazen jejich součet."
    . "<br><br>Význam sloupců s..bas
        <br>s = již první akce byla společná
        <br>as = napřed byl na nějaké akci muž, pak byli na společné
        <br>bs = napřed byla na nějaké akci žena, pak byli na společné
        <br>abs = napřed byl muž, potom žena, pak společně
        <br>bas = napřed byla žena, potom muž, pak společně"
    ;
  return sta2_table_graph($par,$tits,$flds,$clmn,$export);
}
# ================================================================================> . sta2 struktura
# tabulka struktury kurzu (noví,podruhé,vícekrát,odpočívající VPS,VPS)
# par.od= rok počátku statistik, parg.graf=1 ukázat graficky
function sta2_struktura($org,$par,$title,$export=false) {
  $od_roku= isset($par->od) ? $par->od : 0;
  $mez_k= 3.0;
  $par->fld= 'nazev';
  $par->tit= 'nazev';
  $tab= sta2_akcnost_vps($org,$par,$title,true);
//                                                    debug($tab,"evid_sestava_v(,$title,$export)");
  $clmn= $suma= array();
  $tit= "rok,rodin,u nás - noví,podruhé,vícekrát,vps - odpočívající,ve službě,
        celkem pečounů,+pp,+po,+pg,
        dětí na kurzu,placených kočárků,dětí<$mez_k let s sebou,dětí<18 let doma,
        manželství,věk muže,věk ženy";
  $tits= explode(',',$tit);
  $fld= "rr,u,n,p,v,vo,vs,pec,pp,po,pg,d,K,k,x,m,a,b";
  $flds= explode(',',$fld);
  $flds_rr= explode(',',substr($fld,3));
  for ($rrrr=date('Y');$rrrr>=1990;$rrrr--) {
    if ( $rrrr<$od_roku ) continue;
    $rr= substr($rrrr,-2);
    $clmn[$rr]= array('rr'=>$rrrr,'u'=>0, 'n'=>0, 'p'=>0, 'v'=>0, 'vo'=>0, 'vs'=>0);
    $rows= count($tab->clmn);
    for ($n= 1; $n<=$rows; $n++) {
      if ( ($xrr= $tab->clmn[$n][$rr]) ) {
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
      $suma[$rr]+= isset($clmn[$rr][$fld]) ? $clmn[$rr][$fld] : 0;
    }
  }
  // doplnění informací o rodinách
  $rod= sta2_rodiny($org,$od_roku,$mez_k);
  // doplnění informací o pečounech
  $pecs= sta2_pecouni_simple($org);
//                                         debug($rod,"rodiny");
  foreach ($rod as $rok=>$r) {
    if ( $rok<$od_roku ) continue;
    $rr= substr($rok,-2);
    $clmn[$rr]['u']= $r['r'];
    $clmn[$rr]['d']= $r['d'];
    $clmn[$rr]['k']= $r['k'];
    $clmn[$rr]['K']= $r['K'];
    $clmn[$rr]['x']= $r['x'];
    $clmn[$rr]['m']= $r['m'];
    $clmn[$rr]['a']= $r['a'];
    $clmn[$rr]['b']= $r['b'];
    $clmn[$rr]['pec']= isset($pecs[$rok]['p']) ? $pecs[$rok]['p'] : 0;
    $clmn[$rr]['pp']=  isset($pecs[$rok]['pp']) ? $pecs[$rok]['pp'] : 0;
    $clmn[$rr]['po']=  isset($pecs[$rok]['po']) ? $pecs[$rok]['po'] : 0;
    $clmn[$rr]['pg']=  isset($pecs[$rok]['pq']) ? $pecs[$rok]['pg'] : 0;
  }
  // smazání prázdných
  foreach ($clmn as $r=>$c) {
    if ( !isset($c['x']) ) unset($clmn[$r]);
  }

//                                         debug($suma,"součty");
//                                                         debug($clmn,"evid_sestava_s:$tit;$fld");
  // Popis sloupců a jejich datových zdrojů
  // - kočárků - pobyt.kocarek tzn. z plateb
  // - pečounů - 
  $par->tit= $tit;
  $par->fld= $fld;
  if ( $par->graf ) {
    $par->grf= "u:n,p,v,vo,vs,pec,d";
    $par->txt= "Graficky je znázorněn absolutní počet."; //relativní počet vzhledem k počtu párů.;
  }
  if (!isset($par->txt)) $par->txt= '';
  $par->txt.= "<br><br>Zkratky názvů sloupců: pp=pomocní pečovatelé, po=osobní pečovatelé, pg=děti skupiny G";
  $par->txt.= "<br><br>Pokud v nějakém roce bylo více běhů je zobrazen jejich součet.";
  return sta2_table_graph($par,$tits,$flds,$clmn,$export);
}
# -------------------------------------------------------------------------==> . sta2 pecouni_simple
function sta2_pecouni_simple($org) { trace();
  $clmn= array();
  $qry= " SELECT p.funkce, s.pfunkce, YEAR(datum_od)
          FROM pobyt AS p
          JOIN spolu AS s USING (id_pobyt)
          JOIN akce  AS a ON a.id_duakce=p.id_akce
          WHERE (p.funkce=99 OR (p.funkce NOT IN (9,10,13,14,15,99) AND s.pfunkce IN (4,5,8))) 
            AND a.druh=1 AND a.access & $org";
  $res= pdo_qry($qry);
  while ( $res && (list($f,$pf,$rok)= pdo_fetch_row($res)) ) {
    if (!isset($clmn[$rok])) $clmn[$rok]= array('p'=>0,'pp'=>0,'po'=>0,'pg'=>0);
    $clmn[$rok]['p']+=  $f==99 ? 1 : 0;
    $clmn[$rok]['pp']+= $pf==4 ? 1 : 0;
    $clmn[$rok]['po']+= $pf==5 ? 1 : 0;
    $clmn[$rok]['pg']+= $pf==8 ? 1 : 0;
  }
  return $clmn;
}
# --------------------------------------------------------------------------------- sta2 akcnost_vps
# generování přehledu akčnosti VPS
function sta2_akcnost_vps($org,$par,$title,$export=false) {  trace();
  // dekódování parametrů
  $roky= $froky= '';
  for ($r=1990;$r<=date('Y');$r++) {
    $roky.= ','.substr($r,-2);
    $froky.= ','.substr($r,-2).':3';
  }
  $tits= explode(',',$tit= $par->tit.$froky);
  $flds= explode(',',$fld= $par->fld.$roky);
  $HAVING= isset($par->hav) ? "HAVING {$par->hav}" : '';
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
  $res= pdo_qry($qry);
  while ( $res && ($x= pdo_fetch_object($res)) ) {
    $n++;
    $clmn[$n]= array();
    foreach($flds as $f) {
      switch ( $f ) {
      case '_jmeno':                            // kolektivní člen
        $clmn[$n][$f]= "{$x->nazev} {$x->jmeno_m} a {$x->jmeno_z}";
        break;
      default:
        $clmn[$n][$f]= isset($x->$f) ? $x->$f : '';
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
# --------------------------------------------------------------------------------- sta2 table_graph
# pokud je $par->grf= a:b,c,... pak se zobrazí grafy normalizované podle sloupce a
# pokud je $par->txt doplní se pod tabulku
function sta2_table_graph($par,$tits,$flds,$clmn,$export=false) {
  global $ezer_root;
  $result= (object)array('par'=>$par);
  if ( isset($par->grf) ) {
    list($norm,$grf)= explode(':',$par->grf);
  }
  $skin= $_SESSION[$ezer_root]['skin'];
  // zobrazení tabulkou
  $tab= '';
  $ths= '';
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
        if ( isset($par->grf) && strpos(",$grf,",",$f,")!==false ) {
          //$w= $c[$norm] ? round(100*($c[$f]/$c[$norm]),0) : 0;     -- relativní počet
          $w= isset($c[$f]) ? $c[$f] : 0;
          $g= "<div class='curr_akce' style='height:4px;width:{$w}px;float:left;margin-top:5px'>";
        }
        if (isset($c[$f])) {
          $align= is_numeric($c[$f]) || preg_match("/\d+\.\d+\.\d+/",$c[$f]) ? "right" : "left";
          $tab.= "<td style='text-align:$align'>{$c[$f]}$g</td>";
        }
        else {
          $tab.= "<td>$g</td>";
        }
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
# ---------------------------------------------------------------------------------==> . sta2 rodiny
# clmn: rok -> r:rodin, d:dětí na akci, x:dětí<18 doma, m:délka manželství, a,b:věk muže, ženy
# - $mez_k je věková hranice dělící děti na (asi) kočárkové resp. postýlkové
function sta2_rodiny($org,$rok=0,$mez_k=2.0) { trace();
  $clmn= array();
  $ms= array();
  // ms => r=rodin, d=dětí na akci, D=dětí mladších 18 v rodině,
  //       va=věk muže, na=počet mužů s věkem, vb=věk ženy, nb=.., vm=délka manželství, nm=..
//  $rok= 2016; // *****************************************
  $HAVING= $rok ? "HAVING _rok>=$rok" : '';
  $rx= pdo_qry("
    SELECT id_akce, YEAR(datum_od) AS _rok,
      COUNT(id_osoba) AS _clenu, COUNT(id_spolu) AS _spolu,
      SUM(IF(/*t.role='d' AND*/ IF(MONTH(o.narozeni),DATEDIFF(a.datum_od,o.narozeni)/365.2425,YEAR(a.datum_od)-YEAR(o.narozeni)) < 18 AND id_spolu,1,0)) AS _sebou,
      SUM(IF(/*t.role='d' AND*/ IF(MONTH(o.narozeni),DATEDIFF(a.datum_od,o.narozeni)/365.2425,YEAR(a.datum_od)-YEAR(o.narozeni)) < 18,1,0)) AS _deti,
      SUM(IF(/*t.role='d' AND*/ IF(MONTH(o.narozeni),DATEDIFF(a.datum_od,o.narozeni)/365.2425,YEAR(a.datum_od)-YEAR(o.narozeni)) < $mez_k 
        AND id_spolu,1,0)) AS _sebou_k, kocarek,
      SUM(IF(t.role='a',IF(MONTH(o.narozeni),DATEDIFF(a.datum_od,o.narozeni)/365.2425,YEAR(a.datum_od)-YEAR(o.narozeni)),0)) AS _vek_a,
      SUM(IF(t.role='b',IF(MONTH(o.narozeni),DATEDIFF(a.datum_od,o.narozeni)/365.2425,YEAR(a.datum_od)-YEAR(o.narozeni)),0)) AS _vek_b,
      -- IF(r.datsvatba,DATEDIFF(a.datum_od,r.datsvatba)/365.2425,
        -- IF(r.svatba,YEAR(a.datum_od)-svatba,0)) AS _vek_m
      IF(r.datsvatba,IF(MONTH(r.datsvatba),DATEDIFF(a.datum_od,r.datsvatba)/365.2425,YEAR(a.datum_od)-YEAR(r.datsvatba)),
        IF(r.svatba,YEAR(a.datum_od)-svatba,0)) AS _vek_m
    FROM pobyt AS p
    JOIN akce AS a ON id_akce=id_duakce
    JOIN rodina AS r ON id_rodina=i0_rodina
    JOIN tvori AS t USING (id_rodina)
    JOIN osoba AS o USING (id_osoba)
    LEFT JOIN spolu USING (id_pobyt,id_osoba)
    WHERE a.druh=1 AND (a.access & $org) AND p.funkce IN (0,1,2,5) -- AND p.funkce IN (0,1) 
    --  AND id_pobyt=50904
    GROUP BY id_pobyt $HAVING
  ");
  while ( $rx && ($x= pdo_fetch_object($rx)) ) {
    $r= $x->_rok;
    if (!isset($ms[$r])) 
      $ms[$r]= array('r'=>0,'d'=>0,'k'=>0,'K'=>0,'D'=>0,'va'=>0,'na'=>0,'vb'=>0,'nb'=>0,'vm'=>0,'nm'=>0);
    $ms[$r]['r']++;
    $ms[$r]['d']+= $x->_sebou;
    $ms[$r]['k']+= $x->_sebou_k;
    $ms[$r]['K']+= $x->kocarek;
    $ms[$r]['D']+= $x->_deti;
    if ( $x->_vek_a && $x->_vek_a<100 ) {
      $ms[$r]['va']+= $x->_vek_a;
      $ms[$r]['na']++;
    }
    if ( $x->_vek_b && $x->_vek_b<100 ) {
      $ms[$r]['vb']+= $x->_vek_b;
      $ms[$r]['nb']++;
    }
    if ( $x->_vek_m && $x->_vek_m!=0 ) {
      $ms[$r]['vm']+= $x->_vek_m;
      $ms[$r]['nm']++;
    }
  }
  foreach (array_keys($ms) as $r) {
    $clmn[$r]['r']= $ms[$r]['r'];
    $clmn[$r]['d']= $ms[$r]['d'];
    $clmn[$r]['k']= $ms[$r]['k'];
    $clmn[$r]['K']= $ms[$r]['K'];
    $clmn[$r]['x']= $ms[$r]['D'] - $ms[$r]['d'];
    $clmn[$r]['m']= round($ms[$r]['nm'] ? $ms[$r]['vm']/$ms[$r]['nm'] : 0);
    $clmn[$r]['a']= round($ms[$r]['na'] ? $ms[$r]['va']/$ms[$r]['na'] : 0);
    $clmn[$r]['b']= round($ms[$r]['nb'] ? $ms[$r]['vb']/$ms[$r]['nb'] : 0);
  }
//                                                         debug($clmn,"sta2_rodiny($org,$rok)");
  return $clmn;
}
# --------------------------------------------------------------------------------==> . sta2 pecouni
function sta2_pecouni($org) { trace();
//   case 'ms-pecouni': // -------------------------------------==> .. ms-pecouni
  # _pec,_sko,_proc
  $clmn= array();
  list($od,$do)= select("MAX(YEAR(datum_od)),MIN(YEAR(datum_od))","akce","druh=1 AND access&$org");
//  $od=$do=2018;
  for ($rok=$od; $rok>=$do; $rok--) {
    $kurz= select1("id_duakce","akce","druh=1 AND YEAR(datum_od)=$rok AND access&$org");
    $akci= select1("COUNT(*)","akce","druh=7 AND YEAR(datum_od)=$rok AND access&$org");
    $akci= $akci ? "$akci školení" : '';
    $info= akce2_info($kurz,0,1); //muzi,zeny,deti,peco,rodi,skup,pp,po,pg
    // získání dat
    $_pec= $_sko= $_proc= $_pecN= $_skoN= $_procN= 0;
    $data= array();
    _akce2_sestava_pecouni($data,$kurz);
//    $_pec= count($data);
//    if ( !$_pec ) continue;
    if ( !count($data) ) continue;
    foreach ($data as $d) {
      $skoleni= 0;
      $sko= array_unique(preg_split("/\s+/",$d['_skoleni'], -1, PREG_SPLIT_NO_EMPTY));
      $slu= array_unique(preg_split("/\s+/",$d['_sluzba'],  -1, PREG_SPLIT_NO_EMPTY));
      $ref= array_unique(preg_split("/\s+/",$d['_reflexe'], -1, PREG_SPLIT_NO_EMPTY));
      $leto= $slu[0];
      // počty různých typů pečounů
      $_pec++;
      // výpočet školení všech
      $skoleni+= count($sko);
      foreach ($ref as $r) {
        if ( $r<$leto ) 
          $skoleni++;
      }
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
    // aserce na pečouny
    $err= $_pec!=$info->peco+$info->_pp+$info->_po+$info->_pg
        ? "$_pec &ne; {$info->peco}+{$info->_pp}+{$info->_po}+{$info->_pg}" : '';
    // zobrazení výsledků
    $clmn[]= array('_rok'=>$rok,'_rodi'=>$info->rodi,'_deti'=>$info->deti,
      '_pec'=>$info->peco,'_sko'=>$_sko,'_proc'=>$_proc,
      '_pp'=>$info->_pp,'_po'=>$info->_po,'_pg'=>$info->_pg,'_celk'=>$_pec,
      '_pecN'=>$_pecN,'_skoN'=>$_skoN,'_procN'=>$_procN,'_note'=>$note,'chyba'=>$err);
//       if ( $rok==2014) break;
  }
  return $clmn;
}
# ==================================================================================> . sta2 sestava
# sestavy pro evidenci
function sta2_sestava($org,$title,$par,$export=false) { trace();
//                                                 debug($par,"sta2_sestava($title,...,$export)");
  $ret= (object)array('html'=>'','err'=>0);
  $note_before= '';
  // dekódování parametrů
  $tits= $par->tit ? explode(',',$par->tit) : array();
  $flds= $par->fld ? explode(',',$par->fld) : array();
  $clmn= array();
  $expr= array();       // pro výrazy
  // získání dat
  switch ($par->typ) {

  # Sestava údajů o akcích: LK MS, Glotrach, Křižanov, Nesměř ap.
  #  item {title:'Přehled větších vícedenních akcí'  ,par:°{typ:'4roky',rok:0,xls:'Excel',pdf:0
  #    ,dsc:'Sestava ukazuje údaje o účastnících na vybraných akcích za uplynulé 4 roky.<br>'
  #    ,tit:'věk:4,účastníků:10,pečovatelů:10',fld:'_vek,_uca,_pec',ord:'_vek'}}
  case '4roky':     // -----------------------------------==> .. 4 roky velkých akcí
    $roky= (date('Y')-4)." AND ".(date('Y')-1);
    $tits= explode(',',
      'rok:6,dnů:6,R/J:6,místo akce:12,název akce:22,celkem účastníků a dětí (bez týmu a pečounů a chův):10,'
     . 'průměrný věk dospělých:10,dospělých mužů:10,dospělých žen:10,'
     . 'dětí na akci:8,~ průměrně na rodinu:10,dětí doma (do 18):8,celkem mají účastníci dětí,~ průměrně na rodinu:10,'
     . '+ počet chův na akci:8,+ počet pečounů na akci:8,průměrný věk pečounů:9,(SS):5,(ID):5');
    $flds= explode(',',
      'rok,dnu,rj,misto,nazev,n_all,a_vek,muzu,zen,'
    . 'deti,p_deti,r_dit18,r_deti,pr_deti,n_chu,n_pec,a_vek_pec,ucet,ID');
    // kritéria akcí
    $druh_r= $org==2 ? '200,230'          : '412';                      // MS
    $druh_j= $org==2 ? '300,301,310,410'  : '302';                      // muži, ženy
    $druh= $druh_r . ( $druh_j ? ",$druh_j" : '');
//g    $ss=     $org==2 ? "ciselnik_akce"    : "g_kod";
    $ss=     "ciselnik_akce";
    $test= "1";
//     $test= "id_akce=694";
//     $test= "id_akce IN (694,738)";
//     $test= "YEAR(a.datum_od)=2013";
    $rx= pdo_qry("
      SELECT id_duakce,a.datum_od,$ss,nazev,misto,YEAR(a.datum_od),DATEDIFF(a.datum_do,a.datum_od),
        x.n_all,a_vek,n_mzu,n_zen,n_dti,n_ote,n_mat,n_dit,n_chu,n_pec,a_vek_pec,n_nul,_rr
      FROM akce AS a
      LEFT JOIN join_akce AS aj ON aj.id_akce=a.id_duakce
      LEFT JOIN (
        SELECT id_akce, COUNT(*) AS n_all, GROUP_CONCAT(DISTINCT xp.i0_rodina) AS _rr,
          ROUND(SUM(IF(funkce IN (0,1) AND ROUND(
              IF(MONTH(xo.narozeni),DATEDIFF(xa.datum_od,xo.narozeni)/365.2425,YEAR(xa.datum_od)-YEAR(xo.narozeni)),1)>=18,
              IF(MONTH(xo.narozeni),DATEDIFF(xa.datum_od,xo.narozeni)/365.2425,YEAR(xa.datum_od)-YEAR(xo.narozeni)),0))
              / SUM(funkce IN (0,1) AND IF(ROUND(IF(MONTH(xo.narozeni),DATEDIFF(xa.datum_od,xo.narozeni)/365.2425,YEAR(xa.datum_od)-YEAR(xo.narozeni)),1)>=18,1,0)))
            AS a_vek,
          SUM(IF(ROUND(IF(MONTH(xo.narozeni),DATEDIFF(xa.datum_od,xo.narozeni)/365.2425,YEAR(xa.datum_od)-YEAR(xo.narozeni)),1)<18,1,0)) AS n_dti,
          SUM(IF(ROUND(IF(MONTH(xo.narozeni),DATEDIFF(xa.datum_od,xo.narozeni)/365.2425,YEAR(xa.datum_od)-YEAR(xo.narozeni)),1)>=18 AND xo.sex=1,1,0)) AS n_mzu,
          SUM(IF(ROUND(IF(MONTH(xo.narozeni),DATEDIFF(xa.datum_od,xo.narozeni)/365.2425,YEAR(xa.datum_od)-YEAR(xo.narozeni)),1)>=18 AND xo.sex=2,1,0)) AS n_zen,
          SUM(IF(xst.role='a',1,0)) AS n_ote, SUM(IF(xst.role='b',1,0)) AS n_mat,
          SUM(IF(xst.role='d',1,0)) AS n_dit, SUM(IF(xst.role NOT IN ('a','b','d'),1,0)) AS n_chu,
          SUM(IF(ISNULL(xst.role),1,0)) AS n_nul,
          SUM(IF(funkce=99,1,0)) AS n_pec,
          ROUND(SUM(IF(funkce=99,IF(MONTH(xo.narozeni),DATEDIFF(xa.datum_od,xo.narozeni)/365.2425,YEAR(xa.datum_od)-YEAR(xo.narozeni)),0)) / SUM(IF(funkce=99,1,0)))
            AS a_vek_pec
        FROM pobyt AS xp
        JOIN akce  AS xa ON xa.id_duakce=xp.id_akce
        JOIN spolu AS xs USING (id_pobyt)
        JOIN osoba AS xo ON xo.id_osoba=xs.id_osoba
        LEFT JOIN tvori  AS xst ON xst.id_osoba=xs.id_osoba AND IF(xp.i0_rodina,xst.id_rodina=xp.i0_rodina,0)
        WHERE funkce IN (0,1,99)
        GROUP BY id_akce
      ) AS x ON x.id_akce=id_duakce
      WHERE a.access=$org AND $ss IN ($druh)
        AND YEAR(a.datum_od) BETWEEN $roky AND DATEDIFF(a.datum_do,a.datum_od)>0
        AND $test
      ORDER BY a.datum_od,ciselnik_akce
    ");
    while ( $rx && (list(
        $ida,$datum_od,$ucet,$nazev,$misto,$rok,$dnu,
        $n_all,$a_vek,$n_mzu,$n_zen,$n_dti,$n_ote,$n_mat,$n_dit,$n_chu,$n_pec,$a_vek_pec,$n_nul,$rr
      )= pdo_fetch_row($rx)) ) {
      $r_deti= $r_deti18= 0;
      // rozhodnutí o typu akce: rodiny / jednotlivci
      if ( strpos(" $druh_r",$ucet) ) {
        // dopočet údajů rodin
        if ( $rr ) {
          $rs= pdo_qry("
            SELECT SUM(IF(role='d',1,0)),
              -- SUM(IF(ROUND(DATEDIFF('$datum_od',o.narozeni)/365.2425,1)<18,1,0)) AS _deti
              SUM(IF(ROUND(IF(MONTH(o.narozeni),DATEDIFF('$datum_od',o.narozeni)/365.2425,YEAR('$datum_od')-YEAR(o.narozeni)),1)<18,1,0)) AS _deti
            FROM rodina AS r
              JOIN tvori AS t USING (id_rodina)
              JOIN osoba AS o USING (id_osoba)
            WHERE id_rodina IN ($rr)
            GROUP BY id_rodina
          ");
          while ( $rs && (list($deti,$deti18)= pdo_fetch_row($rs)) ) {
            $r_deti+= $deti;
            $r_deti18+= $deti18;
          }
        }
        $p_deti= round($n_mat ? $n_dit/$n_mat : 0,2);
        $pr_deti= round($n_mat ? $r_deti/$n_mat : 0,2);;
        $clmn[$ida]= array( // rodiny
          'rok'=>$rok, 'dnu'=>$dnu, 'rj'=>'R', 'nazev'=>"$nazev", 'misto'=>$misto,
          'n_all'=>$n_all-$n_pec-$n_chu, 'a_vek'=>$a_vek,
          'muzu'=>$n_ote, 'zen'=>$n_mat, 'deti'=>$n_dit, 'p_deti'=>$p_deti,
          'n_chu'=>$n_chu, 'n_pec'=>$n_pec, 'a_vek_pec'=>$a_vek_pec,
          'n_nul'=>$n_nul,
          'r_deti'=>$r_deti, 'pr_deti'=>$pr_deti, 'r_dit18'=>$r_deti18-$n_dti,
          'a_vek'=>$a_vek,
          'ucet'=>$ucet, 'ID'=>$ida
        );
      }
      else {
        $clmn[$ida]= array( // jednotlivci
          'rok'=>$rok, 'dnu'=>$dnu, 'rj'=>'J', 'nazev'=>"$nazev", 'misto'=>$misto,
          'a_vek'=>$a_vek,
          'n_all'=>$n_all,
          'muzu'=>$n_mzu, 'zen'=>$n_zen, 'deti'=>$n_dti,
          'n_chu'=>$n_chu, 'n_nul'=>$n_nul,
          'a_vek'=>$a_vek,
          'ucet'=>$ucet, 'ID'=>$ida
        );
      }
    }
    // náhrada nul a test součtu muzu+zen+deti=n_all
    $note_before= "";
    foreach($clmn as $j=>$row) {
      $suma= $row['muzu']+$row['zen']+$row['deti'];
      $pocet= $row['n_all'];
      if ( $suma != $pocet ) {
        $note_before.= "<br>U akce {$row['ID']} nesouhlasí počet mužů+žen+dětí ($suma) s účastníky celkem ($pocet)";
      }
      foreach($row as $i=>$value) {
        if ( !$value ) $clmn[$j][$i]= '';
      }
    }
    if ( $note_before ) $note_before= "POZOR!$note_before<br><br>";
//                                                debug($clmn,"clmn");
    break;

  # Sestava pro export jmen a emailů všech evidovaných
  case 'maily':     // -------------------------------------==> .. maily
    $tits= array("jmeno:15","prijmeni:15","email:30","neposílat:10");
    $flds= array('jmeno','prijmeni','_email','nomail');
    $rx= pdo_qry("SELECT
        o.id_osoba,jmeno,prijmeni,
        IF(o.kontakt,o.email,IFNULL(r.emaily,'')) AS _email,nomail
      FROM osoba AS o
      LEFT JOIN tvori AS t USING (id_osoba)
      LEFT JOIN rodina AS r USING (id_rodina)
      GROUP BY o.id_osoba
      HAVING _email!=''
      ORDER BY o.prijmeni,o.jmeno
      -- LIMIT 10
      ");
    while ( $rx && ($x= pdo_fetch_object($rx)) ) {
      $clmn[]= $x;
    }
    break;

  # Sestava pečounů na letních kurzech, rok= před kolika lety naposledy ve funkci (0=jen letos)
  case 'pecujici':     // -----------------------------------==> .. pecujici
    $cert= array(); // certifikát rok=>poslední číslo
    $rok= date('Y');
    $hranice= date('Y') - $par->parm;
    $tits= array("pečovatel:20","certifikát:20","poprvé:10","kolikrát:10","naposledy:10","1.školení:10",
                 "č.člen od:10","bydliště:25","narození:10","(ID osoby)");
    $flds= array('jm','cert','od','n','do','vps_i','clen','byd','nar','^id_osoba');
    $rx= pdo_qry("SELECT
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
    while ( $rx && ($x= pdo_fetch_object($rx)) ) {
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

  # Sestava historie VPS - varianta pro YS
  case 'slouzici2':    // -------------------------------------==> .. slouzici2
    return vps_historie($org,$par,$export);
    break;
  
  # Sestava sloužících na letních kurzech, rok= před kolika lety naposledy ve funkci (0=jen letos)
  case 'slouzici':     // -------------------------------------==> .. slouzici
    global $VPS;
    $cert= array(); // certifikát rok=>poslední číslo
    $rok= date('Y');
    $hranice= date('Y') - $par->parm;
    $vps1= $org==1 ? '3,17' : '3';
    $order= 'r.nazev';
    if ( $par->podtyp=='pary' ) {
      $tits= array("pár:26","poprvé:10","kolikrát:10","naposledy:10",
                 $org==1?"VPS I:10":"1.školení:10","č.člen od:10","(ID)");
      $flds= array('jm','od','n','do','vps_i','clen','^id_rodina');
    }
    else if ( $par->podtyp=='skupinky' ) {
      $behy= isset($par->behy) ? $par->behy : 1;
      $tits= array("pár:26","poprvé:10","kolikrát:10","naposledy:10",
                 $org==1?"VPS I:10":"1.školení:10");
      $flds= array('jm','od','n','do','vps_i');
    }
    else if ( $par->podtyp=='kulatiny' ) {
      $tits= array("jméno:20","poprvé:10","kolikrát:10","naposledy:10",
                 $org==1?"VPS I:10":"1.školení:10","narození:10:d","svatba:10:d","roků:7",
                    "telefon:20","email:30","(ID)");
      $flds= array('jm','od','n','do','vps_i','nar','svatba','roku','telefon','email','^id_osoba');
      $letos= date('Y');
      $kulate= substr($letos,3,1);
      $pulkulate= ($kulate+5) % 10;
      $order= 'MONTH(o.narozeni),DAY(o.narozeni)';
    }
    else { // osoby
      $tits= array("jméno:20","certifikát:20","poprvé:10","kolikrát:10","naposledy:10",
                 $org==1?"VPS I:10":"1.školení:10","č.člen od:10","bydliště:25","narození:10","(ID)");
      $flds= array('jm','cert','od','n','do','vps_i','clen','byd','nar','^id_osoba');
    }
    $rx= pdo_qry("SELECT
        r.id_rodina,r.nazev,r.svatba,r.datsvatba,r.rozvod,
        GROUP_CONCAT(DISTINCT IF(t.role='a',o.umrti,'') SEPARATOR '') as umrti_m,
        GROUP_CONCAT(DISTINCT IF(t.role='a',o.id_osoba,'') SEPARATOR '') as id_m,
        GROUP_CONCAT(DISTINCT IF(t.role='a',o.jmeno,'') SEPARATOR '') as jmeno_m,
        GROUP_CONCAT(DISTINCT IF(t.role='a',o.prijmeni,'') SEPARATOR '') as prijmeni_m,
        GROUP_CONCAT(DISTINCT IF(t.role='a',o.narozeni,'') SEPARATOR '') as narozeni_m,
        GROUP_CONCAT(DISTINCT IF(t.role='a',IF(o.adresa,o.obec,r.obec),'') SEPARATOR '') as obec_m,
        GROUP_CONCAT(DISTINCT IF(t.role='b',o.umrti,'') SEPARATOR '') as umrti_z,
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
      WHERE spec=0 AND zruseno=0 AND o.deleted=''
        AND r.id_rodina=i0_rodina AND a.access&$org
        -- AND r.nazev LIKE 'Šmí%'
        AND druh IN (1,$vps1) 
      GROUP BY r.id_rodina
      -- HAVING -- bereme vše kvůli číslům certifikátů - vyřazuje se až při průchodu
        -- VPS_I<9999 AND
        -- DO>=$hranice
      ORDER BY $order");
    while ( $rx && ($x= pdo_fetch_object($rx)) ) {
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
      elseif ( $par->podtyp=='skupinky' ) {
        $clmn[]= array(
          'jm'=>"{$x->jmeno_m} a {$x->jmeno_z} {$x->nazev}",
          'od'=>$x->OD,'n'=>$x->Nx,'do'=>$x->DO,
          'vps_i'=>$skola ?: '-'
        );
      }
      elseif ( $par->podtyp=='kulatiny' ) {
        // zjistíme telefon
        $muz= db2_osoba_kontakt($x->id_m);
        $zena= db2_osoba_kontakt($x->id_z);
        if (substr($x->narozeni_m,3,1)==$kulate && !$x->umrti_m) {
          $roku= $letos - substr($x->narozeni_m,0,4);
          $kdy= sql_date1($x->narozeni_m);
          $order= substr($x->narozeni_m,5,2).substr($x->narozeni_m,8,2);
          $clmn[]= array('order'=>$order,
            'jm'=>"{$x->prijmeni_m} {$x->jmeno_m}",'od'=>$x->OD,'n'=>$x->Nx,'do'=>$x->DO,
            'nar'=>$kdy, 'roku'=>$roku, 'telefon'=>$muz->telefon, 'email'=>$muz->email, 
            '^id_osoba'=>$x->id_m
          );
        }
        if ($x->narozeni!='0000-00-00' && in_array(substr($x->narozeni_z,3,1),[$kulate,$pulkulate]) && !$x->umrti_z) {
          $roku= $letos - substr($x->narozeni_z,0,4);
          $kdy= sql_date1($x->narozeni_z);
          $order= substr($x->narozeni_z,5,2).substr($x->narozeni_z,8,2);
          $clmn[]= array('order'=>$order,
            'jm'=>"{$x->prijmeni_z} {$x->jmeno_z}",'od'=>$x->OD,'n'=>$x->Nx,'do'=>$x->DO,
            'nar'=>$kdy, 'roku'=>$roku, 'telefon'=>$zena->telefon, 'email'=>$zena->email, 
            '^id_osoba'=>$x->id_z
          );
        }
        if ($x->datsvatba!='0000-00-00' && in_array(substr($x->datsvatba,3,1),[$kulate,$pulkulate]) && !$x->rozvod && !$x->umrti_m && !$x->umrti_z ) {
          $tel= $muz->telefon==$zena->telefon ? $muz->telefon
              : "{$muz->telefon}, {$zena->telefon}";
          $mai= $muz->email==$zena->email ? $muz->email
              : "{$muz->email}, {$zena->email}";
          $roku= $letos - substr($x->datsvatba,0,4);
          $kdy= sql_date1($x->datsvatba);
          $order= substr($x->datsvatba,5,2).substr($x->datsvatba,8,2);
          $clmn[]= array('order'=>$order,
            'jm'=>"{$x->jmeno_m} a {$x->jmeno_z} {$x->nazev}",'od'=>$x->OD,'n'=>$x->Nx,'do'=>$x->DO,
            'svatba'=>$kdy, 'roku'=>$roku, 'telefon'=>$tel, 'email'=>$mai,
            '^id_osoba'=>$x->id_z
          );
        }
        usort($clmn,function($a,$b){return $a['order']>$b['order'];});
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
      $rx= pdo_qry("SELECT prednasi,YEAR(a.datum_od) AS _rok,
          GROUP_CONCAT(DISTINCT IF(t.role='a',o.prijmeni,'') SEPARATOR '') as prijmeni_m,
          GROUP_CONCAT(DISTINCT IF(t.role='a',o.jmeno,'')    SEPARATOR '') as jmeno_m,
          GROUP_CONCAT(DISTINCT IF(t.role='b',o.prijmeni,'') SEPARATOR '') as prijmeni_z,
          GROUP_CONCAT(DISTINCT IF(t.role='b',o.jmeno,'')    SEPARATOR '') as jmeno_z,
          p.pouze,r.nazev
        FROM pobyt AS p
        JOIN spolu AS s USING(id_pobyt)
        JOIN osoba AS o ON s.id_osoba=o.id_osoba
        LEFT JOIN tvori AS t ON t.id_osoba=o.id_osoba
        LEFT JOIN rodina AS r ON r.id_rodina=IF(i0_rodina,i0_rodina,t.id_rodina)
        JOIN akce AS a ON a.id_duakce=p.id_akce
        WHERE a.druh=1 AND p.prednasi=$pr AND YEAR(a.datum_od) BETWEEN $od AND $do AND a.access&$org
        GROUP BY id_pobyt -- ,_rok
        ORDER BY _rok DESC");
      while ( $rx && ($x= pdo_fetch_object($rx)) ) {
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

  # Sestava ukazuje celkový počet účastníků resp. pečovatelů na akcích letošního roku,
  # rozdělený podle věku. Účastník resp. pečovatel je započítán jen jednou,
  # bez ohledu na počet akcí, jichž se zúčastnil
  case 'ucast-vek': // ---------------------------------------------==> .. ucast-vek
    $rok= date('Y')-$par->rok;
    $rx= pdo_qry("
      SELECT YEAR(a.datum_od)-YEAR(o.narozeni) AS _vek,MAX(p.funkce) AS _fce
      FROM osoba AS o
      JOIN spolu AS s USING(id_osoba)
      JOIN pobyt AS p USING (id_pobyt)
      JOIN akce  AS a ON id_akce=id_duakce
      WHERE o.deleted='' AND YEAR(datum_od)=$rok AND a.access&$org
      GROUP BY o.id_osoba
      ORDER BY $par->ord
      ");
    while ( $rx && ($x= pdo_fetch_object($rx)) ) {
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
    $rx= pdo_qry("
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
        JOIN akce  AS a ON id_akce=id_duakce AND spec=0 AND zruseno=0 
      WHERE o.deleted='' AND YEAR(narozeni)<$rok18 AND a.access&$org
        AND YEAR(datum_od)>=$rok AND spec=0 AND zruseno=0 
        -- AND o.id_osoba IN(3726,3727,5210)
        -- AND o.id_osoba IN(4537,13,14,3751)
        -- AND o.id_osoba IN(4503,4504,4507,679,680,3612,4531,4532,206,207)
        -- AND id_duakce=394
      GROUP BY o.id_osoba HAVING _role!='p' $AND
      ORDER BY _order
      -- LIMIT 10
      ");
    while ( $rx && ($x= pdo_fetch_object($rx)) ) {
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
    return sta2_table($tits,$flds,$clmn,$export,null,$note_before);
}
# -----------------------------------------------------------------------------------=> . sta2 table
function sta2_table($tits,$flds,$clmn,$export=false,$row_numbers=false,$note='') {  trace();
  $ret= (object)array('html'=>'');
  // zobrazení tabulkou
  $tab= '';
  $ths= '';
  $n= 0;
  if ( $export ) {
    $ret->tits= $tits;
    $ret->flds= $flds;
    $ret->clmn= $clmn;
  }
  else {
    $fmt= array();
    // písmena sloupců
    if ( $row_numbers ) {
      $ths.= "<th> </th>";
      for ($a= 0; $a<count($tits); $a++) {
        $id= chr(ord('A')+$a);
        $ths.= "<th>$id</th>";
      }
      $ths.= "</tr><tr>";
    }
    // titulky
    if ( $row_numbers )
      $ths.= "<th>1</th>";
    foreach ($tits as $i=>$idw) {
      $id_len_f= explode(':',$idw);
      $id= $id_len_f[0];
      $f= isset($id_len_f[2]) ? $id_len_f[2] : '';
      $ths.= "<th>$id</th>";
      if ( $f ) $fmt[$flds[$i]]= $f;
    }
    foreach ($clmn as $i=>$c) {
      $c= (array)$c;
      $tab.= "<tr>";
      if ( $row_numbers )
        $tab.= "<th>".($i+2)."</th>";
      foreach ($flds as $f) {
        if ( $f=='id_osoba' || $f=='^id_osoba' )
          $tab.= "<td style='text-align:right'>".tisk2_ukaz_osobu($c[$f])."</td>";
        elseif ( $f=='^id_rodina' )
          $tab.= "<td style='text-align:right'>".tisk2_ukaz_rodinu($c['^id_rodina'])."</td>";
        elseif ( $f=='^id_pobyt' )
          $tab.= "<td style='text-align:right'>".tisk2_ukaz_pobyt($c['^id_pobyt'])."</td>";
        elseif ( is_numeric($c[$f]) || $fmt[$f]=='d' )
          $tab.= "<td style='text-align:right'>{$c[$f]}</td>";
        else {
          $tab.= "<td style='text-align:left'>{$c[$f]}</td>";
        }
      }
      $tab.= "</tr>";
      $n++;
    }
    $ret->html= "{$note}Seznam má $n řádků<br><br><div class='stat'>
      <table class='stat'><tr>$ths</tr>$tab</table></div>";
  }
  return $ret;
}
# obsluha různých forem výpisů karet AKCE
# ---------------------------------------------------------------------------------------- sta2 excel
# ASK
# generování statistické sestavy do excelu
function sta2_excel($org,$title,$par,$tab=null) {       trace();
//                                                         debug($par,"sta2_excel($org,$title,...)");
  // získání dat
  if ( !$tab ) {
    $tab= sta2_sestava($org,$title,$par,true);
    $title= $par->title ?: $title;
  }
  // vlastní export do Excelu
  return sta2_excel_export($title,$tab);
}
# ---------------------------------------------------------------------------==> . sta2 excel_export
# local
# generování tabulky do excelu
function sta2_excel_export($title,$tab) {  //trace();
//                                         debug($tab,"sta2_excel_export($title,tab)");
  global $xA, $xn, $ezer_version;
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
    list($id,$w,$f,$s)= array_merge(explode(':',$idw),array('','',''));      // název sloupce : šířka : formát : suma
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
    $c= (array)$c;
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
          case 't': $format.= ' text'; break;
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
  require_once "ezer$ezer_version/server/vendor/autoload.php";
  $inf= Excel2007($xls,1);
//   $inf= Excel5($xls,1);
  if ( $inf ) {
    $html= " se nepodařilo vygenerovat - viz začátek chybové hlášky";
    fce_error($inf);
  }
  else {
    $html= " Výpis byl vygenerován ve formátu <a href='docs/$name.xlsx' target='xlsx'>Excel</a>.";
  }
  $result->html= $html;
  return $result;
}
# ---------------------------------------------------- sta2 excel_subst
function sta2_excel_subst($matches) { trace();
  global $xA, $xn;
//                                                 debug($xA);
//                                                 debug($matches);
  if ( !isset($xA[$matches[1]]) ) fce_error("sta2_excel_subst: chybný název sloupce '{$matches[1]}'");
  $A= $xA[$matches[1]];
  $n= $xn+$matches[2];
  return "$A$n";
}
/** =========================================================================================> CHART */
# ----------------------------------------------------------------------------------==> . chart typs
# převod řetězce popis:field na {sel:'popis:index,...',typ:[field,...]}
// název:field:graf:z
function chart_typs($x) { 
  $x= preg_replace('/\s*;\s*/', ';', trim($x,"; \n\r\t\v\x00"));
  $x= preg_replace('/\s*:\s*/', ':', $x);
  $graf= $graf_tit= $graf_y= $graf_z= array();
  $sel= $del= '';
  foreach ( explode(';',$x) as $i=>$pf) {
    list($p,$y,$g,$z)= array_merge(explode(':',$pf),array_fill(0,3,''));
    $sel.= "$del$p:$i"; $del= ',';
    $graf[]= $g; $graf_tit[]= $p; $graf_y[]= $y; $graf_z[]= $z;
  }
  $ret= (object)array('sel'=>$sel,'graf'=>$graf,'y'=>$graf_y,'z'=>$graf_z,'tit'=>$graf_tit);
//  debug($ret,$x);
  return $ret;
}
# ---------------------------------------------------------------------------------==> . chart akce2
# infografika údajů o LK podle db resp. pro YS podle dotazníku
# graf=line|bar|bar%|pie, x=od-do, y=vek|pocet [,z=typ-ucasti]
function chart_akce2($par) { debug($par,'chart_akce2');
  $y= (object)array('err'=>'','note'=>'');
  $chart= (object)array('chart'=>(object)array());
  $regression= 0;
  switch ($par->graf) {
    case 'spline/regression':
      $chart->chart= 'spline';
      $regression= 1;
      break;
    case 'column':
      $chart->chart= 'column';
      $chart->plotOptions= (object)array();
      $chart->plotOptions->column= (object)array('stacking'=>$par->prc ? 'percent' : 'value');
      break;
    case 'pie':
      $chart->chart= 'pie';
      break;
    default:
      $chart->chart= $par->graf;
      break;
  }
  $org= $par->org; //255;
  // názvy kategorií
  $VPS= $org==1 ? 'VPS' : 'PPS';
  $names= array(
      'deti'        => array('detiLK'=>'nezletilé děti na kurzu','detiD'=>'nezletilé děti doma'),
      'typ-ucasti'  => array('vps'=>"věk $VPS",'ucast'=>"věk účastníků bez $VPS"),
      'cirkev'      => array(''=>'neuvedeno','bez'=>'bez vyznání','kat'=>"katolíci",'eva'=>"nekatolíci"),
      '0-5'         => array('nevyužil jsem','1=velmi líbilo','2=spíše líbilo','3=přijatelné','4=spíše nelíbilo','5=velmi nelíbilo'),
      'prinos'      => array('no comment','1=Ano, velmi významně','2=Ano, částečně','3=Nevím, to se uvidí','4=Ne, nevidím změnu','5=Ne, spíše naopak'),
      '1-3'         => array(0,'ANO','stejné','NE'),
      'temata'      => array('malé děti','dospívající','matka-dítě','otec-dítě','mezigen.','duchovní','jiné'),
  );
  $temata= explode(',',"tema_male,tema_dosp,tema_matka,tema_otec,tema_mezigen,tema_duchovni,tema_jine");
  $colors= array(
      'deti'        => array('detiLK'=>'green','detiD'=>'red'),
      'typ-ucasti'  => array('vps'=>'','ucast'=>''),
      'cirkev'      => array(''=>'silver','eva'=>'#00cc00','bez'=>'orange','kat'=>'#0000ee'),
      'prinos'      => array('silver','#00cc00','#0000ee','darkorange','red','black'),
      '0-5'         => array('silver','#00cc00','#0000ee','darkorange','red','black'),
      '1-3'         => array(0,'#00cc00','silver','#0000ee'),
  );
  $notes= array(
      'deti'        => "Poznámka: jsou zahrnuty jen rodiny s nezletilými dětmi",
      'typ-ucasti'  => "",
  );
  $y->note= $notes[$par->z] ?: ' ';
  // zobrazený interval
  $chart->series= array();
  if ($par->rok=='od-do') { $od= $par->od; $do= $par->do; }
  else $od= $do= date('Y');
  // pořadí řad
  $nuly= array_fill(0,1+$do-$od,0);
  switch ("$par->y/$par->z") {
    case 'pocet/cirkev':
      $data= array('kat'=>$nuly,'eva'=>$nuly,'bez'=>$nuly,''=>$nuly);
      break;
    default:
      $data= array();
      break;
  }
//  debug($data,"data-nuly $do-$od");
  // popis os
  list($yTitle,$yMin,$yMax,$yTicks)= explode(',',$par->yaxis);
  $roky= array();
  $r= -1;
  if ($par->dotaznik) {
    // projdeme dotazník
    $hodnoceni= $par->y;
    if ($hodnoceni=='temata') {
      $data_temata= array_fill(0,6,0);
      $rp= pdo_qry(" SELECT * FROM dotaz WHERE $par->dotaznik ");
      while ( $rp && ($x= pdo_fetch_object($rp)) ) {
        foreach ($temata as $i=>$tema) {
          if ($x->$tema) $data_temata[$i]++;
        }
      }
//      debug($data_temata);
      foreach ($data_temata as $i=>$pocet) {
        $name= isset($names['temata'][$i]) ? $names['temata'][$i] : $i;
        $desc= (object)array('name'=>$name,'y'=>$pocet);
//        if ($color) $desc->color= $color;
        $data[$hodnoceni][]= $desc;
      }
    }
    else {
      $rp= pdo_qry("
        SELECT $hodnoceni,COUNT(*)
        FROM dotaz
        WHERE $par->dotaznik
        GROUP BY $hodnoceni
      ");
      while ( $rp && (list($znamka,$pocet)= pdo_fetch_array($rp)) ) {
        $color= $colors[$par->z][$znamka] ? $colors[$par->z][$znamka] : null;
        $name= isset($names[$par->z][$znamka]) ? $names[$par->z][$znamka] : $znamka;
        $desc= (object)array('name'=>$name,'y'=>$pocet);
        if ($color) $desc->color= $color;
        $data[$hodnoceni][]= $desc;
      }
    }
//    debug($data);
  }
  else {
    for ($rok= $od; $rok<=$do; $rok++) {
      $ida= select('id_duakce','akce',"access&$org AND druh=1 AND zruseno=0 AND YEAR(datum_od)=$rok");
      if (!$ida) { 
        $r1= $r+1;
        display("$r1.rok ($rok) - nejsou data");
        foreach ($data as $id=>$serie) {
          array_splice($data[$id],$r1,1);
        }
        continue; 
      }
      $r++;
      $roky[$r]= $rok;
      // projdeme účastníky
      $n= 0;
      $n_vps= $n_ucast= $vek_vps= $vek_ucast= 0;
      $n_rodicu= $pocet_deti= $pocet_detiLK= 0;
      $rp= pdo_qry("
        SELECT 
          ROUND(IF(r.datsvatba,IF(MONTH(r.datsvatba),DATEDIFF(a.datum_od,r.datsvatba)/365.2425,YEAR(a.datum_od)-YEAR(r.datsvatba)),
            IF(r.svatba,YEAR(a.datum_od)-svatba,0)),1) AS _manz,
          ROUND(IF(MONTH(o.narozeni),DATEDIFF(a.datum_od,o.narozeni)/365.2425,YEAR(a.datum_od)-YEAR(o.narozeni)),1) AS _vek,
          IF(o.narozeni,1,0) AS _vek_ok,
          sex,cirkev,IF(funkce IN (1,2),1,0) AS _vps,
          (SELECT IFNULL(SUM(IF(dt.role='d',1,0)),0) FROM tvori AS dt JOIN osoba AS do USING (id_osoba)
          WHERE dt.id_rodina=r.id_rodina AND IF(MONTH(do.narozeni),DATEDIFF(a.datum_od,do.narozeni)/365.2425,
          YEAR(a.datum_od)-YEAR(do.narozeni))<18) AS _deti,
          (SELECT SUM(IF(dt.role='d',1,0)) FROM tvori AS dt JOIN spolu AS ds USING (id_osoba)
          WHERE dt.id_rodina=r.id_rodina AND ds.id_pobyt=p.id_pobyt) AS _detiLK
        FROM pobyt AS p
        JOIN akce AS a ON id_akce=id_duakce
        JOIN spolu AS s USING (id_pobyt)
        LEFT JOIN rodina AS r ON r.id_rodina=p.i0_rodina
        JOIN tvori AS t USING (id_rodina,id_osoba)
        JOIN osoba AS o USING (id_osoba)
        WHERE id_akce=$ida AND p.funkce IN (0,1,2) 
          AND t.role IN ('a','b') -- AND s_role=1 
  -- AND id_rodina IN (3329,1875)        
        GROUP BY id_osoba HAVING _vek>18
        ");
      while ( $rp && (list($manz,$vek,$vek_ok,$sex,$cirkev,$vps,$deti,$detiLK)= pdo_fetch_array($rp)) ) {
        $n+= 0.5;
        switch ("$par->y/$par->z") {
          case 'vek/typ-ucasti':
            if ($vps && $vek_ok) { $n_vps++; $vek_vps= ($vek_vps*($n_vps-1)+$vek)/$n_vps; }
            elseif ($vek_ok) { $n_ucast++; $vek_ucast= ($vek_ucast*($n_ucast-1)+$vek)/$n_ucast; }
            break;
          case 'pocet/deti':
            if ($deti) {
              $n_rodicu+= 0.5;
              $pocet_deti+= $deti/2;
              $pocet_detiLK+= $detiLK/2;
            }
            break;
          case 'pocet/cirkev':
            if (in_array($cirkev,array(0)))
              $data[''][$r]++; 
            elseif (in_array($cirkev,array(3,16)))
              $data['bez'][$r]++; 
            elseif (in_array($cirkev,array(1,13)))
              $data['kat'][$r]++;
            else
              $data['eva'][$r]++;
            break;
        }
      }
      $vek_vps= round($vek_vps,1);
      $vek_ucast= round($vek_ucast,1);
      switch ("$par->y/$par->z") {
        case 'vek/typ-ucasti':
          $data['vps'][]= $vek_vps; 
          $data['ucast'][]= $vek_ucast; 
          break;
        case 'pocet/deti':
          $data['detiD'][]= $n_rodicu ? round(($pocet_deti-$pocet_detiLK)/$n_rodicu,2) : 0; 
          $data['detiLK'][]= $n_rodicu ? round($pocet_detiLK/$n_rodicu,2) : 0; 
          break;
      }
      display("rok $rok: účastníků=$n rodičů=$n_rodicu dětí na LK=$pocet_detiLK");
    }
  }  
  foreach ($data as $id=>$serie) {
    $name= $names[$par->z][$id];
    $color= $colors[$par->z][$id] ? $colors[$par->z][$id] : null;
    $desc= (object)array('name'=>$name,'data'=>$serie);
    if ($color) $desc->color= $color;
    $chart->series[]= $desc;
    if ($regression && count($roky)>1) {
      $last_x= count($serie)-1;
      $lr= linear_regression(range(0,$last_x),$serie); $m= $lr['m']; $b= $lr['b']; 
      $desc= (object)array('name'=>"$name - trend", 
          'dashStyle'=>'Dash',
          'marker'=>(object)array('enabled'=>0),
          'data'=>array(array(0,round($b,1)),array($last_x,round($b+$m*$last_x,1))), 'color'=>'grey');
      if ($color) $desc->color= $color;
      $chart->series[]= $desc; 
    }
  }
  $chart->title= $par->title;
  switch ($chart->chart) {
    case 'column':
      $chart->tooltip= (object)array(
        'pointFormat'=>"<span>{series.name}</span>: <b>{point.y}</b> ({point.percentage:.0f}%)<br/>");
    case 'line':
    case 'spline':
      $chart->xAxis= (object)array('categories'=>$roky,
          'title'=>(object)array('text'=>'rok kurzu '));  
      $chart->yAxis= (object)array(
          'title'=>(object)array('text'=>$yTitle),
          'min'=>$yMin,'max'=>$yMax,'tickAmount'=>$yTicks);
      break;
    case 'pie':
      $chart->tooltip= (object)array(
        'pointFormat'=>"<span>{series.name}</span>: <b>{point.y}</b> ({point.percentage:.0f}%)<br/>");
      break;
  }
  $y->chart= $chart;
//  debug($y);
end:
  return $y;
}
# ----------------------------------------------------------------------------------==> . chart akce
# agregace údajů o MROP, FIRMING a MS pro grafické znázornění
function chart_akce($par) { // debug($par,'chart_akce');
  $y= (object)array('err'=>'','note'=>' ');
  $org= $par->org; //255;
  $letos= date('Y');
  $mrop_vek= $mrop_joins= ''; // pro odlišení cases vek_rel a vek_abs
  $chart= $par->chart ?: (object)array();
  if (!isset($chart->series))
    $chart->series= array();
  switch ($par->type) {
    // --------------------------------------------------------------- obecné
    case 'prihlasky':
      $pro= $par->pro;
      $od= $par->od;
      $do= $par->do ?: $letos;
//      $od= 2014;
//      $do= $letos;
      $chart= (object)array('chart'=>'line');
      $chart->title= "tempo (zápisu) přihlašování na "
          .($pro=='mrop'?'MROP':($pro=='firm'?'FIRMING':($pro=='erop'?'EROP':'LK MS')));
      $akce= $pro=='ms' ? "druh=1 && access&$org" : ($pro=='erop' ? "nazev LIKE 'EROP%'" : "$pro=1");
      $funkce= $pro=='ms' ? "funkce IN (0,1,2,5,9,13,15)" : "funkce IN (0,1)";
      for ($rok= $od; $rok<=$do; $rok++) {
        $ida= select('id_duakce','akce',"$akce AND zruseno=0 AND YEAR(datum_od)=$rok");
        if (!$ida) continue;
        $mesic_akce= select('MONTH(datum_do)','akce',"$akce AND zruseno=0 AND YEAR(datum_od)=$rok");
        $data= array(0,0,0,0,0,0,0,0,0,0,0,0,0);
        $qp=  "SELECT id_pobyt,
             (SELECT DATE(kdy) FROM _track 
              WHERE kde='pobyt' AND klic=id_pobyt ORDER BY id_track LIMIT 1) AS mesic
          FROM pobyt JOIN akce ON id_akce=id_duakce
          WHERE id_akce='$ida' AND $funkce ";
        $rp= pdo_qry($qp);
        while ( $rp && (list($idp,$datum)= pdo_fetch_row($rp)) ) {
          if (substr($datum,0,4)==$rok)
            $data[0+substr($datum,5,2)]++;
          else
            $data[0]++;
        }
        for ($m=12; $m>$mesic_akce; $m--) {
          unset($data[$m]);
        }
        // pokud chceme integrál
        if ($par->ukaz=='celkem') {
          for ($m=1; $m<=$mesic_akce; $m++) {
            if (isset($data[$m]))
              $data[$m]+= $data[$m-1];
          }
        }
//        debug($data,"$rok");
        if ($rok==$letos) {
          for ($m= date('m'); $m<=12; $m++) {
            unset($data[$m]);
          }
        }
        $serie= (object)array('name'=>$rok,'data'=>implode(',',$data));
        $chart->series[]= $serie;
      }
      $chart->yAxis= (object)array('title'=>(object)array(
          'text'=>'počet zapsaných přihlášek'));
      $chart->xAxis= (object)array(
          'categories'=>array('dříve','leden','únor','březen','duben','květen','červen',
            'červenec','srpen','září'),//,'říjen','listopad','prosinec'),
          'title'=>(object)array('text'=>'měsíc'));
      $prihlasky= $pro=='ms'
          ? "přihlášky účastníků, VPS, hospodářů včetně náhradníků - ale  bez odhlášených a bez kněží, psychologů, ..."
          : "přihlášky účastníků - bez týmu, starších, odhlášených";
      $nejdriv= $pro=='ms' ? "od roku 2011" : "od roku 2014";
      $y->note= "<i><b>Poznámky:</b><ol>
        <li>měsíc podání přihlášky se bere podle data zápisu do Answeru
        <li>zobrazují se jen $prihlasky
        <li>graf lze zobrazit $nejdriv
        </ol>";
      break;
    // --------------------------------------------------------------- specifické pro MS
    case 'skupinky': 
      $chart->title= 'Počty skupinek v daném roce podle velikosti';
      $x= $roky= $tri= $ctyri= $divna= array();
      $ix= $je_divna= 0;
      $od= $par->od;
      $do= $par->do;
      for ($r= $od; $r<=$do; $r++) { 
        // zjistíme, jestli v daném roce by LK
        $lk= select('COUNT(*)','akce',"access&$org AND druh=1 AND zruseno=0 AND YEAR(datum_od)=$r");
        if (!$lk) continue;
        $roky[]= $r;
        $x[]= $ix++;
        $r3= $r4= $rx= 0;
        $rs= pdo_qry("SELECT COUNT(*),skupina
          FROM pobyt AS p
          JOIN akce AS a ON id_akce=id_duakce
          WHERE YEAR(datum_od)=$r AND druh=1 AND access&$org AND zruseno=0
            AND skupina>0
          GROUP BY access,skupina ");
        while ($rs && (list($paru,$skupina)= pdo_fetch_row($rs))) {
          if ($paru==3) $r3++;
          elseif ($paru==4) $r4++;
          else {
            $rx++;
            $je_divna++;
            display("clash: skupinka $skupina má $paru párů");
          }
        }
        $tri[$r]= $r3;
        $ctyri[$r]= $r4;
        $divna[$r]= $rx;
      }
      $chart->series= array(
        (object)array('type'=>'scatter','name'=>'tříparová','data'=>implode(',',$tri)),
        (object)array('type'=>'scatter','name'=>'čtyřpárová','data'=>implode(',',$ctyri))
      );
      if ($je_divna) {
        $chart->series[]=
          (object)array('type'=>'scatter','name'=>'?','data'=>implode(',',$divna),'color'=>'red');
      }
      $chart->xAxis= (object)array('categories'=>$roky,
          'title'=>(object)array('text'=>'rok kurzu '));
      $y->note= "<i><b>Poznámka:</b> 
        <br>zobrazení grafu předpokládá, že je v databázi zapsáno složení skupinek.
        <br>Pokud má nějaká skupinka jiný počet párů než tři nebo čtyři, přidá se červená série.
        </i>";
      break;
    case 'histogram': // ignoruje $par->do
      $chart->title= 'Vstup do manželství účastníků kurzu';
      $rok= $par->od;
      $dot_par= (object)array('zdroj'=>'akce','par1'=>'rok','step_man'=>1,'org'=>$org);
      $x= dot_prehled($rok,$dot_par);
//      debug($x,'x');
      $data= (array)$x->know->man_vek; 
      $data= array_merge($data);
//      $data= implode(',',$man_vek);
      $chart->series_done= array(
        (object)array('type'=>'histogram','xAxis'=>1,'yAxis'=>1,'baseSeries'=>1,'z-index'=>-1),
        (object)array('type'=>'scatter','data'=>$data,'marker'=>(object)array('radius'=>1.5))        
      );
//        debug($chart); $y->err= '.'; goto end;
      $chart->yAxis= array((object)array(),(object)array());
      $chart->xAxis= array((object)array(),(object)array());
      break;
    case 'novacci':
      $chart->title= 'Délka manželství před první účastí na kurzu';
      $od= $par->od;
      $do= $par->do ?: date('Y');
      $dot_par= (object)array('zdroj'=>'akce','par1'=>'rok','step_man'=>1,'org'=>$org);
      for ($rok= $od; $rok<=$do; $rok++) {
        $lk= select('COUNT(*)','akce',"access&$org AND druh=1 AND zruseno=0 AND YEAR(datum_od)=$rok");
        if (!$lk) continue;
        $x= dot_prehled($rok,$dot_par);
        $man_s= $x->know->man_n; /*array_pop($man_s);*/ $man_s= array_reverse($man_s); 
        $man_y= $x->know->man_y; array_pop($man_y); $man_y= array_reverse($man_y); 
        $man_s= array_map(function($x){return $x/2;},$man_s);
        $data= implode(',',$man_s);
        $serie= (object)array('name'=>$rok,'data'=>$data);
        $chart->series[]= $serie;
      }
      $chart->yAxis= (object)array('title'=>(object)array('text'=>'počet manželství na letním kurzu'));
      $chart->xAxis= (object)array('categories'=>$man_y,
          'title'=>(object)array('text'=>'délka manželství v přihlášce '));
      $y->note= "<i><b>Poznámka:</b> 
        <br>korektnost grafu předpokládá, že je v databázi správně zapsána i počet účastí 
        na kurzech neevidovaných touto databází (pole 'MS mimo' na evidenční kartě).
        </i>";
      break;
    case 'skladba':
      $chart->title= 'Skladba účastníků letního kurzu';
      $od= $par->od;
      $do= $par->do ?: date('Y');
      $dot_par= (object)array('zdroj'=>'akce','par1'=>'rok','step_man'=>1,'skladba'=>1,'org'=>$org);
      //           VPS       novi
      $kurz= array(array(),array(),array(),array(),array());
      $roky= array();
      for ($rok= $od; $rok<=$do; $rok++) {
        // zjistíme, jestli v daném roce by LK
        $lk= select('COUNT(*)','akce',"access&$org AND druh=1 AND zruseno=0 AND YEAR(datum_od)=$rok");
        if (!$lk) continue;
//        if ($rok==2020 && $org==1) continue;
        $roky[]= $rok;
        $x= dot_prehled($rok,$dot_par);
//        $y->err= '.'; goto end;
        $kurz_x= $x->know->kurz_x ?: array(); 
//        debug($kurz_x,$rok);
        $kurz_y= $x->know->kurz_y; 
        $kurz_x= array_map(function($x){return $x/2;},$kurz_x);
        for ($x= 0; $x<=4; $x++) {
          $kurz[$x][$rok]= isset($kurz_x[$x]) ? $kurz_x[$x] : 0;
        }
      }
//      debug($kurz,"$od-$do");
      $color= array('navy','cyan','grey','orange','lightgreen');
      for ($x= 0; $x<=4; $x++) {
        $data= implode(',',$kurz[$x]);
        $serie= (object)array('name'=>$kurz_y[$x],'data'=>$data,'color'=>$color[$x]);
        $chart->series[$x]= $serie;
      }
      $chart->yAxis= (object)array('title'=>(object)array('text'=>'účast na kurzu jako'),
          'tickInterval'=>10,'min'=>0); // 'categories'=>$kurz_y);
      $chart->xAxis= (object)array('categories'=>$roky,
          'title'=>(object)array('text'=>'rok kurzu '));
      if (isset($chart->plotOptions->column->stacking)){
        $chart->tooltip= (object)array(
          'pointFormat'=>"<span>{series.name}</span>: <b>{point.y}</b> ({point.percentage:.0f}%)<br/>");
        if ($chart->plotOptions->column->stacking=='value' && $par->prc) {
          $chart->plotOptions->column->stacking= 'percent';
        }
      }
      break;
    // --------------------------------------------------------------- specifické pro EROP
    case 'erop':
      $chart->title= 'odhad počtu iniciovaných pozvatelných na EROP';
      // převzetí parametrů a inicializace
      $od= $par->od;
      $do= $par->do ?: date('Y');
      $kat_od= 0;
      $kat_do= array(49,59,87); 
      // tabulka úmrtnosti vzata z ČSÚ 6 Úmrtnost sloupec muži/2019
      $umrtnost_2019= array(
          '20-29' => 0.0007,  '30-34' => 0.0009,  '35-39' => 0.0012,  '40-44' => 0.0018, 
          '45-49' => 0.0030,  '50-54' => 0.0053,  '55-59' => 0.0083,  '60-64' => 0.0149,  
          '65-69' => 0.0232,  '70-74' => 0.0355,  '75-79' => 0.0536,  '80-84' => 0.0881, 
          '85-89' => 0.1544,  '90-94' => 0.2800,  '95-99' => 0.4000
      );
      $iniciovani= $kmeni= $umrti= $prestarli= 0;
      $umrtnost= array();
      foreach ($umrtnost_2019 as $uodudo=>$promile) {
        list($uod,$udo)= explode('-',$uodudo);
        for ($u= $uod; $u<=$udo; $u++) {
          $umrtnost[$u]= $promile;
        }
      }
//      debug($umrtnost_2019);
//      debug($umrtnost);
      $max_vek= $kat_do[count($kat_do)-1]+1;
      $color= array('green','grey','blue');
      $popis= array();
      for ($x= 0; $x<count($kat_do); $x++) {
        $popis[]= $kat_od ? "$kat_od ... $kat_do[$x]" : "... $kat_do[$x]";
        $kat_od= $kat_do[$x] + 1;
      }
      $roky= array();
      $mrop= array();
      // vložení skutečného počtu iniciovaných mužů [rok,kategorie]
      $mr= pdo_qry("
        SELECT iniciace,iniciace-YEAR(narozeni) AS _vek,COUNT(*)
        FROM osoba WHERE iniciace>0 AND YEAR(narozeni) BETWEEN 1920 AND 2020
          -- AND iniciace=2004
          -- AND iniciace=2002
          -- AND iniciace BETWEEN 2004 AND 2005
        GROUP BY iniciace,YEAR(narozeni)
        ORDER BY _vek
      ");
      while ($mr && (list($iniciace,$vek,$pocet)= pdo_fetch_row($mr))) {
        $mrop[$iniciace][$vek]= $pocet;
        $iniciovani+= $pocet;
      }
//      debug($erop,"EROP"); 
      // zestárnutí o rok: $mrop[$rok][$vek] => $mrop[$rok+1][$vek-1]
      for ($rok= $od; $rok<=$do; $rok++) {
        // připočti iniciované předešlého roku do dalšího roku jako o rok starší
        if (isset($mrop[$rok-1])) {
          foreach ($mrop[$rok-1] as $vek=>$pocet) {
            $mrop[$rok][$vek+1]+= $pocet;
          }
        }
        // aplikuj pravděpodobnost úmrtí s ořezáním věku
        if (isset($mrop[$rok])) {
          foreach ($mrop[$rok] as $vek=>$pocet) {
            $pocet= $mrop[$rok][$vek];
            if ($vek>$max_vek) {
              $pocet= 0;
              $prestarli++;
            }
            else {
              $pocet-= round($umrtnost[$vek] * $pocet);
              $mrop[$rok][$vek]= $pocet;
            }
          }
        }
      }
      // zjištění absolventů EROP a jejich odečtení od disponibilních iniciovaných chlapů
      $erop= array();
      $mr= pdo_qry("
        SELECT YEAR(datum_od),YEAR(datum_od)-YEAR(narozeni) AS _vek,COUNT(*)
        FROM pobyt
        JOIN akce ON id_akce=id_duakce
        JOIN spolu USING (id_pobyt)
        JOIN osoba USING (id_osoba)
        WHERE nazev LIKE 'EROP%'
          AND deleted='' AND funkce IN (0,1) AND iniciace>0
          AND YEAR(narozeni) BETWEEN 1920 AND 2020 AND prislusnost IN ('','CZ','SK')
        GROUP BY YEAR(datum_od),YEAR(narozeni)
        ORDER BY _vek
      ");
      while ($mr && (list($rok_erop,$vek,$pocet)= pdo_fetch_row($mr))) {
        $erop[$rok_erop][$vek]= $pocet;
        $mrop[$rok_erop][$vek]-= $pocet;
        $kmeti+= $pocet;
      }
//      debug($mrop,"... MROP"); 
      // zobrazení podle věkových kategorií: $mrop[$rok][$vek] => $stav[kategorie][rok]
      $stav= array();
      for ($rok= $od; $rok<=$do; $rok++) {
        $roky[]= $rok;
        for ($x= 0; $x<count($kat_do); $x++) {
          if (!isset($stav[$x][$rok])) $stav[$x][$rok]= 0;
        }
      }
      for ($rok= $od; $rok<=$do; $rok++) {
        if (!isset($mrop[$rok])) continue;
        foreach ($mrop[$rok] as $vek=>$pocet) {
          for ($x= 0; $x<count($kat_do); $x++) {
            if ($vek <= $kat_do[$x]) {
              $stav[$x][$rok]+= round($pocet);
              $ok_vek++;
              break;
            }
          }
        }
      }
      $_iniciovani= 0;
      foreach ($mrop[$do] as $vek=>$pocet) {
//        display("$vek = $pocet");
        $_iniciovani+= round($pocet);
      }
      $umrti= round($iniciovani - $_iniciovani);
      display("celkem iniciovaní= $iniciovani, kmeti= $kmeti, starší $max_vek let= $prestarli, pravděpodobně zemřelo= $umrti");
//      debug($stav,"stav"); 
//      break; // ------------------------------------------------------------------ BREAK -----
      for ($x= 0; $x<count($kat_do); $x++) {
        $data= implode(',',$stav[$x]);
        $serie= (object)array('name'=>$popis[$x],'data'=>$data,'color'=>$color[$x]);
        $chart->series[$x]= $serie;
      }
      $chart->yAxis= (object)array('title'=>(object)array('text'=>'počet možných účastníků EROP v daném roce'),
          'tickInterval'=>10,'min'=>0); // 'categories'=>$mrop_y,
      $chart->xAxis= (object)array('categories'=>$roky,//'labels'=>(object)array('min'=>'5'),
          'title'=>(object)array('text'=>'rok konání MROP '));
      if (isset($chart->plotOptions->series->stacking)){
        $chart->tooltip= (object)array(
          'pointFormat'=>"<span>{series.name}</span>: <b>{point.y}</b> ({point.percentage:.0f}%)<br/>");
        if ($chart->plotOptions->series->stacking=='normal' && $par->prc) {
          $chart->plotOptions->series->stacking= 'percent';
        }
        $chart->chart= 'bar';
      }
      break;
    case 'vek_abs_nemrop': // věk nyní - neiniciovaní účastníci jiných akcí
      $now= date('Y-m-d');
      $k_datu= "'$now'";
      $mrop_k= sql_date1($now);
      $po= $par->po ?: 10;
      $data= array(array(),array(),array()); // stáří non-firming, firming
      $od= $par->od ?: 2004;
      $do= $par->do ?: $letos;
      $roku= $do - $od + 1;
      $od_vek= 9999; $do_vek= 0;
      $celkem= 0;
      $qv= pdo_qry("
        SELECT 
          FLOOR(IF(MONTH(narozeni),DATEDIFF($k_datu,narozeni)/365.2425,
            YEAR($k_datu)-YEAR(narozeni))) AS _vek,
          ( SELECT BIT_OR(IF(druh IN (1),1,IF(statistika IN (1,2,3,4),2,0))) 
            FROM pobyt
            LEFT JOIN spolu USING (id_pobyt)
            LEFT JOIN osoba AS x USING (id_osoba) 
            LEFT JOIN akce AS a ON id_akce=a.id_duakce 
            WHERE a.firm=0 AND a.mrop=0 AND a.zruseno=0 AND a.spec=0
              AND YEAR(a.datum_od) BETWEEN $od AND $do
              AND x.id_osoba=osoba.id_osoba
              AND x.deleted='' AND funkce IN (0,1,2) AND s_role IN (0,1)
          ) AS _akce,
          COUNT(*) AS _pocet
        FROM osoba 
        WHERE deleted='' AND narozeni AND umrti=0 AND sex=1
          AND iniciace=0 
        GROUP BY FLOOR(_vek/$po),_akce
        HAVING _akce>0 AND _vek BETWEEN 20 AND 99
        ORDER BY _vek
      ");
      while ($qv && (list($vek,$akce,$pocet)=pdo_fetch_row($qv))) {
        $vek_po= floor($vek/$po);
        $akce--; // 0 - jen MS, 1 - jen chlapi, 2 - oboje
        if (!isset($data[$akce][$vek_po*$po])) $data[$akce][$vek_po*$po]= 0;
        $data[$akce][$vek_po*$po]+= $pocet;
        $od_vek= min($od_vek,$vek_po);
        $do_vek= max($do_vek,$vek_po);
        $celkem+= $pocet;
      }
      display("meze $od_vek..$do_vek");
      // nedefinované hodnoty nahradíme nulou
      for ($i= 0; $i<=2; $i++) {
        for ($v= $od_vek; $v<=$do_vek; $v+=$po) {
          if (!isset($data[$i][$v*$po])) $data[$i][$v*$po]= 0;
        }
        ksort($data[$i]);
      }
      $celkem= round($celkem);
//      debug($data,'data');
      $roky= array(); // názvy intervalů 20..
      for ($interval= $od_vek; $interval<=$do_vek; $interval++) {
        $roky[]= $interval*$po . ($po>1 ? '...' : '');
      }
//      debug($kurz,"$od-$do");
      $color= array('grey','darkgreen','blue');
      $ucastnik= array('účast pouze na MS','... pouze na akcích pro muže','oboje');
      for ($x= 0; $x<=count($color)-1; $x++) {
        $datax= implode(',',$data[$x]);
        $serie= (object)array('name'=>$ucastnik[$x],'data'=>$datax,'color'=>$color[$x]);
        $chart->series[$x]= $serie;
      }
      $chart->title= "Neiniciovaní muži ($celkem) z akcí v letech $od-$do ... "."skladba a věk k $mrop_k";
      $chart->yAxis= (object)array('title'=>(object)array('text'=>'účastníků'),'tickInterval'=>10);
//      $chart->yAxis= (object)array('title'=>(object)array('text'=>'účastník'),
//          'categories'=>$ucastnik);
      $chart->xAxis= (object)array('categories'=>$roky,
          'title'=>(object)array('text'=>'stáří '));
      if (isset($chart->plotOptions->column->stacking)){
        $chart->tooltip= (object)array(
          'pointFormat'=>"<span>{series.name}</span>: <b>{point.y}</b> ({point.percentage:.0f}%)<br/>");
        if ($chart->plotOptions->column->stacking=='value' && $par->prc) {
          $chart->plotOptions->column->stacking= 'percent';
        }
      }
      break;
    // --------------------------------------------------------------- specifické pro MROP
    case 'vek_rnd': // modelování věkové struktury - po 5 letech
      $po= $par->po ?: 10;
      $od= $par->od ?: 2004;
      $do= $par->do ?: $letos;
      $od_vek= 9999; $do_vek= 0; // pozice v intervalu
      $celkem= 0;
      $starych= array();
      // modelace počtu účastníků podle stáří: vek -> pocet
      //            25   30   35   40  45  50  55 60  65  70   75   80 85  
      $dist= array(4.7,12.6,20.4,17.3, 15,9.8,4.1, 3,1.2,0.5, 0.5, 0.1, 0);
      $dist_od= 25; $dist_do= 89; $dist_po= 5;
      $last= 0;
      for ($i= 0; $i<count($dist); $i++) {
        $pocet= $dist[$i];
        $vek= $dist_od+$dist_po*$i;
        $delta= ($pocet-$last)/$dist_po;
        for ($r= $vek; $r<$vek+$dist_po; $r++) {
          $starych[$r]= ($pocet+$delta)/$dist_po;
        }
        $last= $pocet;
      }
//      debug($starych,'starych');
      // přepočet na intervaly dat po $po
      $data= array(array()); // stáří 
      for ($i= 0; $i<100/$po; $i++) {
        $pocet= 0;
        for ($r= $i*$po; $r<($i+1)*$po; $r++) {
          $pocet+= $starych[$r];
        }
        if ($pocet) {
          $celkem+= $data[0][$i*$po]= round($pocet,0);
          $od_vek= min($od_vek,$i);
          $do_vek= max($do_vek,$i);
        }
      }
      $roky= array(); // názvy intervalů 20..
      for ($interval= $od_vek; $interval<$do_vek; $interval++) {
        $roky[]= $interval*$po . ($po>1 ? '...' : '');
      }
//      debug($data,'data');
      $color= array('grey');
      $osay= array();
      $ucastnik= array('odhad mrop');
      for ($x= 0; $x<=count($color)-1; $x++) {
        $datax= implode(',',$data[$x]);
        $serie= (object)array('name'=>$ucastnik[$x],'data'=>$datax,'color'=>$color[$x]);
        $chart->series[$x]= $serie;
      }
      $chart->title= "Předpoklad iniciovaných ($celkem)"; // v letech $od-$do ... "."skladba a věk k $mrop_k";
      $chart->yAxis= (object)array('title'=>(object)array('text'=>'účastníků'),'tickInterval'=>10);
//      $chart->yAxis= (object)array('title'=>(object)array('text'=>'účastníků'),
//          'categories'=>$osay);
      $chart->xAxis= (object)array('categories'=>$roky,
          'title'=>(object)array('text'=>'stáří '));
      if (isset($chart->plotOptions->column->stacking)){
        $chart->tooltip= (object)array(
          'pointFormat'=>"<span>{series.name}</span>: <b>{point.y}</b> ({point.percentage:.0f}%)<br/>");
        if ($chart->plotOptions->column->stacking=='value' && $par->prc) {
          $chart->plotOptions->column->stacking= 'percent';
        }
      }
      break;
    case 'vek_rel': // věk v době iniciace
      $mrop_vek= "CONCAT(iniciace,'-09-25')";
      $mrop_joins= "
        JOIN spolu USING (id_osoba) 
        JOIN pobyt USING (id_pobyt)
        JOIN akce ON id_akce=id_duakce AND YEAR(datum_od)=iniciace AND mrop=1";
      $mrop_k= "datu iniciace";
    case 'vek_abs': // věk nyní
      if (!$mrop_joins) {
        $now= date('Y-m-d');
        $mrop_vek= "'$now'";
        $mrop_k= sql_date1($now);
      }
      $po= $par->po ?: 10;
      $data= array(array(),array(),array()); // stáří non-firming, firming
      $od= $par->od ?: 2004;
      $do= $par->do ?: $letos;
      $roku= $do - $od + 1;
      $od_vek= 9999; $do_vek= 0;
      $celkem= 0;
      $qv= pdo_qry("
        SELECT 
          FLOOR(IF(MONTH(narozeni),DATEDIFF($mrop_vek,narozeni)/365.2425,
            YEAR($mrop_vek)-YEAR(narozeni))/$po) AS _vek,
          IF(firming>0,1,IF(
            ( SELECT COUNT(*) FROM pobyt
              LEFT JOIN spolu USING (id_pobyt)
              LEFT JOIN osoba AS x USING (id_osoba) 
              LEFT JOIN akce AS a ON id_akce=a.id_duakce 
              WHERE a.firm=0 AND a.mrop=0 AND a.zruseno=0 AND a.spec=0
                AND YEAR(a.datum_od)>=x.iniciace 
                AND x.id_osoba=osoba.id_osoba
                AND x.deleted=''
            )>0,2,0)
          ) AS _firm,
          COUNT(*) AS _pocet
        FROM osoba $mrop_joins
        WHERE deleted='' AND iniciace BETWEEN $od AND $do
        GROUP BY _vek,_firm
        HAVING _vek BETWEEN 0 AND 100/$po
        ORDER BY _vek
      ");
      while ($qv && (list($vek_po,$firm,$pocet)=pdo_fetch_row($qv))) {
//        $pocet= $pocet/$roku; 
//        $firm= 0;
        if (!isset($data[$firm][$vek_po*$po])) $data[$firm][$vek_po*$po]= 0;
        $data[$firm][$vek_po*$po]+= $pocet;
        $od_vek= min($od_vek,$vek_po);
        $do_vek= max($do_vek,$vek_po);
        $celkem+= $pocet;
      }
      display("meze $od_vek..$do_vek");
      // nedefinované hodnoty nahradíme nulou
      for ($i= 0; $i<=2; $i++) {
        for ($v= $od_vek; $v<=$do_vek; $v+=$po) {
          if (!isset($data[$i][$v*$po])) $data[$i][$v*$po]= 0;
        }
        ksort($data[$i]);
      }
      $celkem= round($celkem);
//      debug($data,'data');
      $roky= array(); // názvy intervalů 20..
      for ($interval= $od_vek; $interval<=$do_vek; $interval++) {
        $roky[]= $interval*$po . ($po>1 ? '...' : '');
      }
//      debug($kurz,"$od-$do");
      $color= array('grey','darkgreen','blue');
      $ucastnik= array('jen mrop','potom firming','pak naše akce');
      for ($x= 0; $x<=count($color)-1; $x++) {
        $datax= implode(',',$data[$x]);
        $serie= (object)array('name'=>$ucastnik[$x],'data'=>$datax,'color'=>$color[$x]);
        $chart->series[$x]= $serie;
      }
      $chart->title= "Iniciovaní ($celkem) v letech $od-$do ... "."skladba a věk k $mrop_k";
      $chart->yAxis= (object)array('title'=>(object)array('text'=>'účastníků'),'tickInterval'=>10);
//      $chart->yAxis= (object)array('title'=>(object)array('text'=>'účastník'),
//          'categories'=>$ucastnik);
      $chart->xAxis= (object)array('categories'=>$roky,
          'title'=>(object)array('text'=>'stáří '));
      if (isset($chart->plotOptions->column->stacking)){
        $chart->tooltip= (object)array(
          'pointFormat'=>"<span>{series.name}</span>: <b>{point.y}</b> ({point.percentage:.0f}%)<br/>");
        if ($chart->plotOptions->column->stacking=='value' && $par->prc) {
          $chart->plotOptions->column->stacking= 'percent';
        }
      }
      break;
    case 'vek_fir': // věk nyní
      $mrop_vek= "CONCAT(firming,'-09-25')";
      $mrop_joins= "
        JOIN spolu USING (id_osoba) 
        JOIN pobyt USING (id_pobyt)
        JOIN akce ON id_akce=id_duakce AND YEAR(datum_od)=firming AND firm=1";
      $mrop_k= "datu firmingu";
      $po= $par->po ?: 10;
      $data= array(array(),array(),array()); // stáří non-firming, firming
      $od= $par->od ?: 2004;
      $do= $par->do ?: $letos;
      $roku= $do - $od + 1;
      $od_vek= 9999; $do_vek= 0;
      $celkem= 0;
      $qv= pdo_qry("
        SELECT 
          FLOOR(IF(MONTH(narozeni),DATEDIFF($mrop_vek,narozeni)/365.2425,
            YEAR($mrop_vek)-YEAR(narozeni))/$po) AS _vek,
          IF(firming>0,1,IF(
            ( SELECT COUNT(*) FROM pobyt
              LEFT JOIN spolu USING (id_pobyt)
              LEFT JOIN osoba AS x USING (id_osoba) 
              LEFT JOIN akce AS a ON id_akce=a.id_duakce 
              WHERE a.firm=0 AND a.mrop=0 AND a.zruseno=0 AND a.spec=0
                AND YEAR(a.datum_od)>=x.iniciace 
                AND x.id_osoba=osoba.id_osoba
                AND x.deleted=''
            )>0,2,0)
          ) AS _firm,
          COUNT(*) AS _pocet
        FROM osoba $mrop_joins
        WHERE deleted='' AND iniciace BETWEEN $od AND $do
        GROUP BY _vek,_firm
        HAVING _vek BETWEEN 0 AND 100/$po
        ORDER BY _vek
      ");
      while ($qv && (list($vek_po,$firm,$pocet)=pdo_fetch_row($qv))) {
        $firm= 0;
        if (!isset($data[$firm][$vek_po*$po])) $data[$firm][$vek_po*$po]= 0;
        $data[$firm][$vek_po*$po]+= $pocet;
        $od_vek= min($od_vek,$vek_po);
        $do_vek= max($do_vek,$vek_po);
        $celkem+= $pocet;
      }
      display("meze $od_vek..$do_vek");
      // nedefinované hodnoty nahradíme nulou
      for ($i= 0; $i<=0; $i++) {
        for ($v= $od_vek; $v<=$do_vek; $v+=$po) {
          if (!isset($data[$i][$v*$po])) $data[$i][$v*$po]= 0;
        }
        ksort($data[$i]);
      }
      $celkem= round($celkem);
//      debug($data,'data');
      $roky= array(); // názvy intervalů 20..
      for ($interval= $od_vek; $interval<=$do_vek; $interval++) {
        $roky[]= $interval*$po . ($po>1 ? '...' : '');
      }
//      debug($kurz,"$od-$do");
      $color= array('darkgreen');
      $ucastnik= array('jen mrop','pak firming','pak naše akce');
      for ($x= 0; $x<=count($color)-1; $x++) {
        $datax= implode(',',$data[$x]);
        $serie= (object)array('name'=>$ucastnik[$x],'data'=>$datax,'color'=>$color[$x]);
        $chart->series[$x]= $serie;
      }
      $chart->title= "Účastníci firmingu ($celkem) v letech $od-$do ... "."skladba a věk k $mrop_k";
      $chart->yAxis= (object)array('title'=>(object)array('text'=>'účastníků'),'tickInterval'=>10);
//      $chart->yAxis= (object)array('title'=>(object)array('text'=>'účastník'),
//          'categories'=>$ucastnik);
      $chart->xAxis= (object)array('categories'=>$roky,
          'title'=>(object)array('text'=>'stáří '));
      if (isset($chart->plotOptions->column->stacking)){
        $chart->tooltip= (object)array(
          'pointFormat'=>"<span>{series.name}</span>: <b>{point.y}</b> ({point.percentage:.0f}%)<br/>");
        if ($chart->plotOptions->column->stacking=='value' && $par->prc) {
          $chart->plotOptions->column->stacking= 'percent';
        }
      }
      break;
    case 'pred_mrop':
    case 'po_mrop':
      $chart->title= $par->type=='pred_mrop'
          ? 'účastníci MROP s vyznačením absolventů MS (případně jiných akcí) před MROP'
          : 'účastníci MROP s vyznačením pokračujících na MS (případně jiných akcí) po MROP';
      $od= $par->od;
      $do= $par->do ?: date('Y');
      //           MS před  X před  MROP
      //           MROP     MS po   X po    
      $mrop= array(array(),array(),array());
      $mrop_y= $par->type=='pred_mrop' 
          ? array('MROP je první akcí','byl na MS před MROP','nebyl na MS, na jiné akci ano')
          : array('zatím na MS ne, jinak ano','pokračuje na MS','MROP je zatím poslední akcí');
      $roky= array();
      for ($rok= $od; $rok<=$do; $rok++) {
        // zjistíme, jestli v daném roce byl MROP
        $ok= select('COUNT(*)','akce',"mrop=1 AND zruseno=0 AND YEAR(datum_od)=$rok");
        $datum_od= select('datum_od','akce',"mrop=1 AND zruseno=0 AND YEAR(datum_od)=$rok");
        if (!$ok) continue;
        $mrop[0][$rok]= 0;
        $mrop[1][$rok]= 0;
        $mrop[2][$rok]= 0;
        $roky[]= $rok;
        $mr= pdo_qry("
          SELECT 
            IF(ms_pred+lk_pred>0,1,0),IF(ms_po+lk_po>0,1,0),
            IF(m_pred+j_pred>0,1,0),IF(m_po+j_po>0,1,0),
            md5_osoba,id_osoba
          FROM `#stat` WHERE mrop=$rok
          ");
        while ($mr && (list($ms_pred,$ms_po,$j_pred,$j_po,$md5o,$ido)= pdo_fetch_row($mr))) {
          if ($par->type=='pred_mrop' ) {
            if ($ms_pred) $mrop[1][$rok]++;
            elseif ($j_pred) $mrop[2][$rok]++;
            else $mrop[0][$rok]++;
            // doplníme informaci z ezer_answer
            if (!$ms_pred && $md5o) {
              $lk_first= select('YEAR(lk_first)','ezer_answer.db_osoba',
                  "db!=3 AND md5_osoba='$md5o' AND YEAR(lk_first)<=$rok");
              if ($lk_first) {
                display("$ido $rok/$lk_first");
                $mrop[1][$rok]++;
              }
            }
          }
          else {
            if ($ms_po) $mrop[1][$rok]++;
            elseif ($j_po) $mrop[0][$rok]++;
            else $mrop[2][$rok]++;
            // doplníme informaci z ezer_answer
            if (!$ms_po && $md5o) {
              $lk_last= select('YEAR(lk_last)','ezer_answer.db_osoba',
                  "db!=3 AND md5_osoba='$md5o' AND lk_last>'$datum_od' ");
              if ($lk_last) {
                display("$ido $rok/$lk_last");
                $mrop[1][$rok]++;
              }
            }
          }
        }
      }
//      debug($mrop,"$od-$do");
      $color= $par->type=='pred_mrop' 
          ? array('orange','blue','navy')
          : array('navy','blue','orange');
      for ($x= 0; $x<=2; $x++) {
        $data= implode(',',$mrop[$x]);
        $serie= (object)array('name'=>$mrop_y[$x],'data'=>$data,'color'=>$color[$x]);
        $chart->series[$x]= $serie;
      }
      $chart->yAxis= (object)array('title'=>(object)
          ['text'=>($par->prc ? 'procento':'počet').' účastníků MROP v daném roce'],
          'tickInterval'=>10,'min'=>0); 
      $chart->xAxis= (object)array('categories'=>$roky,//'labels'=>(object)array('min'=>'5'),
          'title'=>(object)array('text'=>'rok konání MROP '));
      if (isset($chart->plotOptions->series->stacking)){
        $chart->tooltip= (object)array(
          'pointFormat'=>"<span>{series.name}</span>: <b>{point.y}</b> ({point.percentage:.0f}%)<br/>");
        if ($chart->plotOptions->series->stacking=='normal' && $par->prc) {
          $chart->plotOptions->series->stacking= 'percent';
        }
        $chart->chart= 'bar';
      }
      break;
    case 'ys_fa':
      $chart->title= 'účastníci MROP s vyznačením účastí na akcích YS/FA před MROP';
      $od= $par->od;
      $do= $par->do ?: date('Y');
      //           MROP     FA YS+FA YS
      $mrop= array(array(),array(),array());
      $mrop_y= array('MROP je první akcí',
          'byl na akcích Familia','byl tam i tam','byl na akcích Setkání'
          ,'ani tam ani tam, jinde ano'
          );
      $roky= array();
      for ($rok= $od; $rok<=$do; $rok++) {
        // zjistíme, jestli v daném roce byl MROP
        $ok= select('COUNT(*)','akce',"mrop=1 AND zruseno=0 AND YEAR(datum_od)=$rok");
        if (!$ok) continue;
        $mrop[0][$rok]= 0;
        $mrop[1][$rok]= 0;
        $mrop[2][$rok]= 0;
        $mrop[3][$rok]= 0;
        $roky[]= $rok;
        $mr= pdo_qry("
          SELECT ys_pred,fa_pred
          FROM `#stat` WHERE mrop=$rok
          ");
        while ($mr && (list($ys,$fa)= pdo_fetch_row($mr))) {
          if ($ys && $fa) $mrop[2][$rok]++;
          elseif ($ys) $mrop[3][$rok]++;
          elseif ($fa) $mrop[1][$rok]++;
          else $mrop[0][$rok]++;
        }
      }
//      debug($mrop,"$od-$do");
      $color= array('orange','blue','cyan','green');
      for ($x= 0; $x<=3; $x++) {
        $data= implode(',',$mrop[$x]);
        $serie= (object)array('name'=>$mrop_y[$x],'data'=>$data,'color'=>$color[$x]);
        $chart->series[$x]= $serie;
      }
      $chart->yAxis= (object)array('title'=>(object)
          ['text'=>($par->prc ? 'procento':'počet').' účastníků MROP v daném roce'],
          'tickInterval'=>10,'min'=>0); 
      $chart->xAxis= (object)array('categories'=>$roky,//'labels'=>(object)array('min'=>'5'),
          'title'=>(object)array('text'=>'rok konání MROP '));
      if (isset($chart->plotOptions->series->stacking)){
        $chart->tooltip= (object)array(
          'pointFormat'=>"<span>{series.name}</span>: <b>{point.y}</b> ({point.percentage:.0f}%)<br/>");
        if ($chart->plotOptions->series->stacking=='normal' && $par->prc) {
          $chart->plotOptions->series->stacking= 'percent';
        }
        $chart->chart= 'bar';
      }
      break;
    case 'y_x pred':
      $chart->title= 'účastníci MROP s vyznačením účastí na MS akcích YMCA/CPR+ŠM před MROP';
      $od= $par->od;
      $do= $par->do ?: date('Y');
      //           MROP     FA YS+FA YS
      $mrop= array(array(),array(),array());
      $mrop_y= array('MROP je první akcí',
          'byl na akcích CPR či ŠM','byl tam i tam','byl na akcích YMCA');
      $roky= array();
      for ($rok= $od; $rok<=$do; $rok++) {
        // zjistíme, jestli v daném roce byl MROP
        $ok= select('COUNT(*)','akce',"mrop=1 AND zruseno=0 AND YEAR(datum_od)=$rok");
        if (!$ok) continue;
        $mrop[0][$rok]= 0;
        $mrop[1][$rok]= 0;
        $mrop[2][$rok]= 0;
        $mrop[3][$rok]= 0;
        $roky[]= $rok;
        $mr= pdo_qry("
          SELECT ys_pred+fa_pred,md5_osoba
          FROM `#stat` WHERE mrop=$rok
          ");
        while ($mr && (list($ymca,$md5o)= pdo_fetch_row($mr))) {
          // aktivita v CPR a ŠM
          $jinde= select('YEAR(lk_first)','ezer_answer.db_osoba',
              "db!=3 AND md5_osoba='$md5o' AND YEAR(lk_first)<=$rok");
          if ($jinde) {
            display("$ido $rok/$jinde");
          }
          // redakce
          if ($ymca && $jinde) $mrop[2][$rok]++;
          elseif ($ymca) $mrop[3][$rok]++;
          elseif ($jinde) $mrop[1][$rok]++;
          else $mrop[0][$rok]++;
        }
      }
//      debug($mrop,"$od-$do");
      $color= array('orange','red','violet','blue');
      for ($x= 0; $x<=3; $x++) {
        $data= implode(',',$mrop[$x]);
        $serie= (object)array('name'=>$mrop_y[$x],'data'=>$data,'color'=>$color[$x]);
        $chart->series[$x]= $serie;
      }
      $chart->yAxis= (object)array('title'=>(object)
          ['text'=>($par->prc ? 'procento':'počet').' účastníků MROP v daném roce'],
          'tickInterval'=>10,'min'=>0); 
      $chart->xAxis= (object)array('categories'=>$roky,//'labels'=>(object)array('min'=>'5'),
          'title'=>(object)array('text'=>'rok konání MROP '));
      if (isset($chart->plotOptions->series->stacking)){
        $chart->tooltip= (object)array(
          'pointFormat'=>"<span>{series.name}</span>: <b>{point.y}</b> ({point.percentage:.0f}%)<br/>");
        if ($chart->plotOptions->series->stacking=='normal' && $par->prc) {
          $chart->plotOptions->series->stacking= 'percent';
        }
        $chart->chart= 'bar';
      }
      break;
    case 'y_x po':
      $chart->title= 'účastníci MROP s vyznačením účastí na MS akcích YMCA/CPR+ŠM po MROP';
      $od= $par->od;
      $do= $par->do ?: date('Y');
      //           MROP     FA YS+FA YS
      $mrop= array(array(),array(),array());
      $mrop_y= array('byl na akcích CPR či ŠM','byl tam i tam','byl na akcích YMCA',
          'MROP je zatím poslední akcí');
      $roky= array();
      for ($rok= $od; $rok<=$do; $rok++) {
        // zjistíme, jestli v daném roce byl MROP
        $datum_od= select('datum_od','akce',"mrop=1 AND zruseno=0 AND YEAR(datum_od)=$rok");
        if (!$datum_od) continue;
        $mrop[0][$rok]= 0;
        $mrop[1][$rok]= 0;
        $mrop[2][$rok]= 0;
        $mrop[3][$rok]= 0;
        $roky[]= $rok;
        $mr= pdo_qry("
          SELECT ms_po+lk_po,md5_osoba
          FROM `#stat` WHERE mrop=$rok
          ");
        while ($mr && (list($ymca,$md5o)= pdo_fetch_row($mr))) {
          // aktivita v CPR a ŠM
          $jinde= select('YEAR(lk_last)','ezer_answer.db_osoba',
              "db!=3 AND md5_osoba='$md5o' AND lk_last>'$datum_od'");
          if ($jinde) {
            display("$ido $rok/$jinde");
          }
          // redakce
          if ($ymca && $jinde) $mrop[1][$rok]++;
          elseif ($ymca) $mrop[2][$rok]++;
          elseif ($jinde) $mrop[0][$rok]++;
          else $mrop[3][$rok]++;
        }
      }
//      debug($mrop,"$od-$do");
      $color= array('red','violet','blue','orange');
      for ($x= 0; $x<=3; $x++) {
        $data= implode(',',$mrop[$x]);
        $serie= (object)array('name'=>$mrop_y[$x],'data'=>$data,'color'=>$color[$x]);
        $chart->series[$x]= $serie;
      }
      $chart->yAxis= (object)array('title'=>(object)
          ['text'=>($par->prc ? 'procento':'počet').' účastníků MROP v daném roce'],
          'tickInterval'=>10,'min'=>0); 
      $chart->xAxis= (object)array('categories'=>$roky,//'labels'=>(object)array('min'=>'5'),
          'title'=>(object)array('text'=>'rok konání MROP '));
      if (isset($chart->plotOptions->series->stacking)){
        $chart->tooltip= (object)array(
          'pointFormat'=>"<span>{series.name}</span>: <b>{point.y}</b> ({point.percentage:.0f}%)<br/>");
        if ($chart->plotOptions->series->stacking=='normal' && $par->prc) {
          $chart->plotOptions->series->stacking= 'percent';
        }
        $chart->chart= 'bar';
      }
      break;
    case 'hist_vzd': // --------------------------------------------------- vzdělání
    case 'hist_vir': // --------------------------------------------------- církev
      $akce= $par->akce;
      $type= $par->type;
      $chart->title= "tuzemští účastníci $akce a jejich ";
      $od= $par->od;
      $do= $par->do ?: date('Y');
      if ($type=='hist_vzd') {
        $chart->title.= 'vzdělání';
        $mrop= array(array(),array(),array());
        $mrop_y= array('neuvedl','ZŠ','SŠ','VŠ');        
        $druh= 'ms_akce_vzdelani';
        $data= 'vzdelani';
      }
      else if ($type=='hist_vir') {
        $chart->title.= 'vztah k církvi';
        $mrop= array(array(),array(),array());
        $mrop_y= array('neuvedl','bez vyznání','nekatolík','katolík');        
        $druh= 'ms_akce_cirkev';
        $data= 'cirkev';
      }
      $roky= array();
      for ($rok= $od; $rok<=$do; $rok++) {
        // zjistíme, jestli v daném roce byl MROP/EROP
        $akce_cond= $akce=='MROP' ? "mrop=1" : "id_duakce=1501"; // zatím EROP podle ID
        $datum_od= select('datum_od','akce',"$akce_cond AND zruseno=0 AND YEAR(datum_od)=$rok");
        if (!$datum_od) continue;
        $mrop[0][$rok]= 0;
        $mrop[1][$rok]= 0;
        $mrop[2][$rok]= 0;
        $mrop[3][$rok]= 0;
        $roky[]= $rok;
        $akce_ucast= $akce=='MROP' ? "iniciace=$rok" 
            : "id_akce=1501 AND funkce IN (0,1)"; // zatím EROP podle ID
        $mr= pdo_qry("
          SELECT $data,_cis.ikona
          FROM osoba "
          . ($akce=='EROP' ? "JOIN spolu USING (id_osoba) JOIN pobyt USING (id_pobyt) " : "") .
          " LEFT JOIN _cis ON druh='$druh' AND data=$data
          WHERE $akce_ucast AND deleted='' AND stat IN ('','CZ')
          ");
        while ($mr && (list($kod,$cir)= pdo_fetch_row($mr))) {
          // 0 - neuvedl, 1-základní, 2-střední, 3-vysokoškolské)
          // 0 - neuvedl, 1 - bez vyznání, 2 - nekatolík, 3 - katolík
          if ($type=='hist_vir') {
            // redukce církví 
            $cir= $kod==0 ? 0 : ($cir==3 ? 1 : ($cir==1 ? 3 : 2));
          }
          $mrop[$cir][$rok]++;
        }
      }
//      debug($mrop,"$od-$do");
      $color= array('silver','red','green','blue');
      for ($x= 0; $x<=3; $x++) {
        $data= implode(',',$mrop[$x]);
        $serie= (object)array('name'=>$mrop_y[$x],'data'=>$data,'color'=>$color[$x]);
        $chart->series[$x]= $serie;
      }
      $chart->yAxis= (object)array('title'=>(object)
          ['text'=>($par->prc ? 'procento':'počet')." účastníků $akce v daném roce"],
          'tickInterval'=>10,'min'=>0); 
      $chart->xAxis= (object)array('categories'=>$roky,//'labels'=>(object)array('min'=>'5'),
          'title'=>(object)array('text'=>"rok konání $akce "));
      if (isset($chart->plotOptions->series->stacking)){
        $chart->tooltip= (object)array(
          'pointFormat'=>"<span>{series.name}</span>: <b>{point.y}</b> ({point.percentage:.0f}%)<br/>");
        if ($chart->plotOptions->series->stacking=='normal' && $par->prc) {
          $chart->plotOptions->series->stacking= 'percent';
        }
        $chart->chart= 'bar';
      }
      break;
    case 'hist_vek': // --------------------------------------------------- věk MROP/EROP
      $akce= $par->akce;
      $chart->title= "tuzemští účastníci $akce a jejich věk";
      $od= $par->od;
      $do= $par->do ?: date('Y');
      $po= $par->po ?: 10;
      // histogram
      $mrop= [];
      $mrop_y= [];
      $last_y= '...';
      for ($i= 0; $i<=100/$po; $i++) {
        $mrop[$i]= [];
        $mrop_y[$i]= "$last_y - ";
        $last_y= $i*$po;
        $mrop_y[$i].= $last_y;
      }
      $roky= array();
      $i_vek_do= 0;
      $i_vek_od= 999;
      for ($rok= $od; $rok<=$do; $rok++) {
        // zjistíme, jestli v daném roce byl MROP/EROP
        $akce_cond= $akce=='MROP' ? "mrop=1" : "id_duakce=1501"; // zatím EROP podle ID
        $datum_od= select('datum_od','akce',"$akce_cond AND zruseno=0 AND YEAR(datum_od)=$rok");
        if (!$datum_od) continue;
        for ($i= 0; $i<=100/$po; $i++) {
          $mrop[$i][$rok]= 0;
        }
        $roky[]= $rok;
        $akce_ucast= $akce=='MROP' ? "iniciace=$rok" 
            : "id_akce=1501 AND funkce IN (0,1)"; // zatím EROP podle ID
        $mr= pdo_qry("
          SELECT CEIL(($rok-YEAR(narozeni))/$po) AS _vek,COUNT(*),id_osoba
          FROM osoba "
          . ($akce=='EROP' ? "JOIN spolu USING (id_osoba) JOIN pobyt USING (id_pobyt) " : "") .
          "WHERE $akce_ucast AND deleted='' AND narozeni!='0000-00-00'
            AND stat IN ('','CZ')
          GROUP BY _vek
          ");
        while ($mr && (list($i_vek,$n,$ido)= pdo_fetch_row($mr))) {
          if ($i_vek>90) { display("!!! $ido má $i_vek tzn.".$i_vek*$po); continue; } 
          $mrop[$i_vek][$rok]= $n;
          if ($n>0 && $i_vek>$i_vek_do) $i_vek_do= $i_vek;
          if ($n>0 && $i_vek<$i_vek_od) $i_vek_od= $i_vek;
        }
      }
//      debug($mrop,"$od-$do");
      for ($i= 0; $i<=100/$po; $i++) {
        if ($i<$i_vek_od) continue;
        if ($i>$i_vek_do) {
          unset($mrop_y[$i]);
          continue;
        }
        $data= implode(',',$mrop[$i]);
        $serie= (object)array('name'=>$mrop_y[$i],'data'=>$data);
        $chart->series[$i]= $serie;
      }
//      debug($mrop_y,"$i_vek_od-$i_vek_do");
      $chart->series= array_reverse($chart->series);
      $chart->yAxis= (object)array('title'=>(object)
          ['text'=>($par->prc ? 'procento':'počet')." účastníků $akce v daném roce"],
          'tickInterval'=>10,'min'=>0); 
      $chart->xAxis= (object)array('categories'=>$roky,
          'title'=>(object)array('text'=>"rok konání $akce "));
      if (isset($chart->plotOptions->series->stacking)){
        $chart->tooltip= (object)array(
          'pointFormat'=>"<span>{series.name}</span>: <b>{point.y}</b> ({point.percentage:.0f}%)<br/>");
        if ($chart->plotOptions->series->stacking=='normal' && $par->prc) {
          $chart->plotOptions->series->stacking= 'percent';
        }
        $chart->chart= 'bar';
      }
      break;
  }
  $y->chart= $chart;
//  debug($y);
end:
  return $y;
}
# --------------------------------------------------------------------------------==> . sta2 ms stat
# agregace údajů o účastích a účastnících MS
# typ=0 - účasti    => věkové průměry, počty dětí, ročníky MS
# typ=1 - účastníci => geo-info, příslušnost ke kurzu, iniciace muže ... výhledově cesta akcemi
function sta2_ms_stat($par) {
  $msg= '?';  
  switch ($par->op) {
  case 'gen': // ------------------------------ účasti
    // vynulování pracovní tabulky #stat
    query("TRUNCATE TABLE `#stat_ms`");
  // získání individuálních a rodinných údajů
    $mr= pdo_qry("
      SELECT id_rodina,funkce,YEAR(datsvatba),
        -- GROUP_CONCAT(CONCAT(t.role,'~',ROUND(DATEDIFF(datum_od,narozeni)/365.2425))) AS _inf,
        GROUP_CONCAT(CONCAT(t.role,'~',ROUND(IF(MONTH(narozeni),DATEDIFF(datum_od,narozeni)/365.2425,YEAR(datum_od)-YEAR(narozeni))))) AS _inf,
        MAX(iniciace) AS _mrop,
        r.psc,r.stat,a.access,YEAR(datum_od),
        r.nazev AS _note
      FROM pobyt
      JOIN akce AS a ON id_akce=id_duakce
      JOIN rodina AS r ON r.id_rodina=i0_rodina
      JOIN tvori AS t USING (id_rodina)
      JOIN osoba AS o USING (id_osoba)
      WHERE druh=1 AND spec=0 AND zruseno=0 
      -- AND id_pobyt>56600
      GROUP BY id_pobyt
      ORDER BY id_rodina;
    ");
    while ( $mr && 
        list($idr,$vps,$svatba,$veky,$mrop,$psc,$stat,$access,$rok,$nazev)
        = pdo_fetch_row($mr) ) {
      $deti= $vek_a= $vek_b= 0;
      foreach (explode(',',$veky) as $role_vek) {
        list($role,$vek)= explode('~',$role_vek);
        switch ($role) {
        case 'a': $vek_a= $vek; break;  
        case 'b': $vek_b= $vek; break;  
        case 'd': $deti++; break;  
        }
      }
      query("INSERT INTO `#stat_ms` (typ,id_rodina,access,stat,psc,vek_a,vek_b,ms,svatba,mrop,deti,vps,note) 
        VALUE (0,$idr,$access,'$stat','$psc','$vek_a','$vek_b',$rok,$svatba,$mrop,$deti,$vps,'$nazev')");
    }
    // přepočet účastí na účastníky
    $mr= pdo_qry("
      SELECT id_rodina,MAX(vps),MAX(mrop),psc,stat,BIT_OR(access),note
      FROM `#stat_ms`
      GROUP BY id_rodina;
    ");
    while ( $mr && 
        list($idr,$vps,$mrop,$psc,$stat,$access,$nazev)
        = pdo_fetch_row($mr) ) {
      query("INSERT INTO `#stat_ms` (typ,id_rodina,access,stat,psc,mrop,vps,note) 
        VALUE (1,$idr,$access,'$stat','$psc',$mrop,$vps,'$nazev')");
    }
    break;
  case 'see':   // ------------------------------ statistiky
    $note= '';
    $msg= sta2_ms_stat_see($par,$note);
    break;
  case 'see-o': // ------------------------------ statistiky podle organizací
    $note= '';
    $par->org= 1;
    $msg1= sta2_ms_stat_see($par,$note);
    $note= '';
    $par->org= 2;
    $msg2= sta2_ms_stat_see($par,$note);
    $msg= "<table><tr><td>$msg1</td><td>$msg2</td></tr></table>$note";
    break;
  case 'see-t': // ------------------------------ statistiky po letech
    $delta= 10;
    $letos= date('Y');
    $ths= $tds= '';
    for ($od= 2004; $od<$letos; $od+= $delta) {
      $do= $rok+$delta-1;
      $ths.= "<th>$od .. $do</th>";
      $tds.= sta2_ms_stat_see($par);
    }
    $msg= "<table><tr>$ths</tr><tr>$tds</tr></table>";
    break;
  }  
  return $msg;
}
# --------------------------------------------------------------------------==> . sta2 mrop stat gen
# interpretace údajů o účastnících MS
# par.typ = posloupnost písmen   g=geo informace s=statistika  
# par.org = výběr organizace (3=obě)
function sta2_ms_stat_see($par,&$note) { trace();
  $typ= isset($par->typ) ? $par->typ : '';
  $org= isset($par->org) ? $par->org : 3;
  $pro= array('','YS','FA','YS+FA')[$org];
  $msg= '';  
  // proměnné pro účasti    typ=0
  $vsichni_0= 0;
  $deti= $deti3plus= 0;
  // proměnné pro účastníky typ=1
  $vsichni_1= 0;
  $ms_org= array(0,0,0,0,0);
  $okres= $kraj= $pscs= $mss= $miss= array();
  // výpočet pro účasti
  $sr= pdo_qry("
    SELECT id_rodina,deti
    FROM `#stat_ms` 
    WHERE stat='CZ' AND typ=0 AND access & $org
  ");
  while ( $sr && 
      list($idr,$det)
      = pdo_fetch_row($sr) ) {
    // sumy
    $vsichni_0++;
    $deti+=     $det ? 1 : 0;
    $deti3plus+=$det>=3 ? 1 : 0;
  }
  // výpočet pro účastníky
  $sr= pdo_qry("
    SELECT id_rodina,(vek_a+vek_b)/2,access,stat,psc
    FROM `#stat_ms` 
    WHERE stat='CZ' AND typ=1 AND access & $org
  ");
  while ( $sr && 
      list($idr,$vek,$access,$stat,$psc)
      = pdo_fetch_row($sr) ) {
    $vsichni_1++;
    // sumy
    $ms_org[$access]++;
    // výpočty
    if ( $psc ) {
      // geo informace
      if ( !isset($pscs[$psc]) ) $pscs[$psc]= 0;
      $pscs[$psc]++;
      list($k_okres,$k_kraj)= select('kod_okres,kod_kraj','`#psc`',"psc=$psc");
      if ( !$k_kraj ) {
        if ( !in_array($psc,$miss) )
          $miss[]= $psc;
      }
      else {
        if ( !isset($okres[$k_okres])) $okres[$k_okres]= 0;
        $okres[$k_okres]++;
        if ( !isset($kraj[$k_kraj])) $kraj[$k_kraj]= 0;
        $kraj[$k_kraj]++;
      }
    }
  }
  if ( count($miss)) {
    $msg.= "<br>neznáme následující PSČ: ";
    sort($miss);
    foreach ($miss as $pcs) {
      $msg.= " $pcs";
    }
    $msg.= "<br><br>";
  }
  // ------------------------------ statistické informce YS + FA
  if ( strstr($typ,'s')) {
    $msg.= "<h3>Celkem $vsichni_1 párů z ČR</h3>
      <b>účastnilo se kurzu</b>
      <br>... YS = $ms_org[1] párů
      <br>... FA = $ms_org[2] párů
      <br>... YS i FA = $ms_org[3] párů
      <br>
      <h3>Celkem $vsichni_0 účastí párů z ČR</h3>
      <b>děti</b>
      <br>$deti párů mají děti, z toho $deti3plus jich mají 3 a více
     ";
  }
  // ------------------------------ podle věku - účasti
  if ( strstr($typ,'v')) {
    $meze= array(10,20,30,40,50,60,70,80,90,99);
    $meze= array(15,20,25,30,35,40,45,50,55,60,65,70,75,80,85,90,95,99);
    $stari= array();
    $s_ucasti= 0;
    // zobrazení
    $msg.= "<b>Celkem $vsichni_0 účastí z ČR</b>";
    $msg.= "<h3>Účasti podle průměru stáří manželů pro $pro</h3>
      <table class='stat'><tr><th>věk</th><th>počet</th><th>%</th></tr>";
    for ($i=0; $i<count($meze)-1; $i++) {
      $m= select('COUNT(*)','`#stat_ms`',
          "typ=0 AND access & $org AND stat='CZ' AND (vek_a+vek_b)/2 >= $meze[$i] "
          . "AND (vek_a+vek_b)/2 < {$meze[$i+1]}");
      $stari[$i]= $m;
      $s_ucasti+= $m;
      $od= $i==0 ? '.' : $meze[$i]+1;
      $do= $i==count($meze)-2 ? '.' : $meze[$i+1];
      $pm= $stari[$i] ? round(100*$stari[$i]/$vsichni_0) : '-';
      $msg.= "<tr><th>$od..$do</th><td align='right'>$stari[$i]</td>
        <td align='right'>&nbsp;&nbsp;&nbsp;&nbsp;$pm</td>
      </tr>";
    }
    $pm= round(100*$s_ucasti/$vsichni_0);
    $msg.= "<tr><th>celkem</th><th align='right'>$s_ucasti</th>
      <th align='right'>&nbsp;&nbsp;&nbsp;&nbsp;$pm</th>
    </tr>";
    $msg.= "</table>";
  }
  // ------------------------------ podle velikosti obcí - účastníci
  if ( strstr($typ,'o')) {
    $meze= array(0,1,10,100,1000,10000,100000,1000000);
    $meze= array(0,300,1000,3000,10000,30000,100000,300000,1000000);
    $meze= array(0,290,576,1084,2230,4650,9700,21200,46600,300000,1000000);
    $meze= array(0,580,1152,2168,4460,9300,19400,42400,93200,600000,2000000);
    $lidi= $obce= $ms= array();
    $s_lidi= $s_obce= $s_ucasti= 0;
    $msg.= "<h3>Účastníci podle velikosti obce pro $pro</h3>
      <table class='stat'><tr>
      <th>velikost obce</th>
      <th>počet obcí</th>
      <th>počet párů</th>
      <th>%</th>
      <th> ‰ </th>
      <th>obyvatel</th></tr>";
    // výpočet počtu lidí v obcích dané velikosti
    for ($i=count($meze)-2; $i>=0; $i--) {
      list($m,$o)= select('SUM(muzi+zeny),COUNT(*)','`#obec`',
          "(muzi+zeny) BETWEEN $meze[$i] AND {$meze[$i+1]}");
      $s_lidi+= $lidi[$i]= $m;
      $s_obce+= $obce[$i]= $o;
    }
    // výpočet počtu účastníků v obcích dané velikosti
    for ($i=count($meze)-2; $i>=0; $i--) {
      $od= $i==0 ? '.' : $meze[$i];
      $do= $i==count($meze)-2 ? '.' : $meze[$i+1];
      // výpočet 
      $ucasti= 0;
      foreach ($pscs as $psc=>$n) {
        $ok= select('COUNT(*)','`#psc` JOIN `#obec` USING (kod_obec)',
            "psc=$psc AND (muzi+zeny) BETWEEN $meze[$i] AND {$meze[$i+1]}");
        if ( $ok ) {
          $ucasti+= $n;
          $s_ucasti+= $n;
        }
      }
      $pi= $ucasti ? round(100*$ucasti/$vsichni_1) : '?';
      $ppm= $lidi[$i] ? round(1000*2*$ucasti/$lidi[$i],2) : '?';
      $msg.= "<tr><th>$od..$do</th>
        <td align='right'>$obce[$i]</td>
        <td align='right'>$ucasti</td>
        <td align='right'>$pi</td>
        <td>&nbsp;&nbsp;&nbsp;&nbsp;$ppm</td>
        <td align='right'>$lidi[$i]</td>
      </tr>";
    }
    $ppm= round(1000*2*$s_ucasti/$s_lidi,2);
    $msg.= "<tr><th>celkem</th>
      <th align='right'>$s_obce</th>
      <th align='right'>$s_ucasti</th>
      <th>100</th>
      <th align='right'>&nbsp;&nbsp;&nbsp;&nbsp;$ppm</th>
      <th align='right'>$s_lidi</th>
    </tr>";
    $msg.= "</table>";
    $note.= "<br><br><i>Poznámka: 
      sloupec % vyjadřuje procento z celkového počtu účastníků, sloupec ‰ vyjadřuje promile vůči počtu obyvatel
      <br>odchylka v počtu lidí podle obcí a PSČ vznikla tím,
      že tabulka okresu je z roku 2018, tabulka PSČ a obcí z roku 2020 </i>";
  }
  // ------------------------------ celkově geo
  if ( strstr($typ,'g') || strstr($typ,'o')) {
    $cr_lidi= 0;
    $kraj_lidi= $kraj_ppn= array();
    $okres_lidi= $okres_ppn= array();
    $sr= pdo_qry("SELECT kod_kraj,kod_okres,lidi FROM `#okres`");
    while ( $sr && list($k_kraj,$k_okres,$lidi)= pdo_fetch_row($sr)) {
      if ( !isset($kraj_lidi[$k_kraj])) $kraj_lidi[$k_kraj]= 0;
      $cr_lidi+= $lidi;
      $kraj_lidi[$k_kraj]+= $lidi;
      $okres_lidi[$k_okres]= $lidi;
    }
    foreach ($kraj as $k_kraj=>$n) {
      $kraj_ppn[$k_kraj]= round(1000*$n/$kraj_lidi[$k_kraj],2);
    }
    arsort($kraj_ppn);
  }
  // ------------------------------ účasti z okresů a krajů
  if ( strstr($typ,'g')) {
    // geo tabulka krajů
    $msg.= "<h3>Účasti v krajích ČR pro $pro</h3>
      <table class='stat'><tr><th>kraj</th><th>účasti</th><th> ‰ obyvatel</th></tr>";
    foreach ($kraj_ppn as $k_kraj=>$ppn) {
      $nazev= select('nazev','`#kraj`',"kod_kraj=$k_kraj");
      $n= $kraj[$k_kraj];
      $msg.= "<tr><th>$nazev</th><td align='right'>$n</td><td>&nbsp;&nbsp;&nbsp;&nbsp;$ppn</td></tr>";
    }
    $msg.= "</table>";
    // geo info - okresy
    foreach ($okres as $k_okres=>$n) {
      $nazev= select('nazev','`#kraj`',"kod_kraj=$k_kraj");
      $okres_ppn[$k_okres]= round(1000*$n/$okres_lidi[$k_okres],2);
    }
    arsort($okres_ppn);
    $msg.= "<h3>Účasti v okresech ČR</h3>
      <table class='stat'><tr><th>okres</th><th>účasti</th><th>‰ obyvatel</th></tr>";
    foreach ($okres_ppn as $k_okres=>$ppm) {
      $nazev= select('nazev','`#okres`',"kod_okres=$k_okres");
      $n= $okres[$k_okres];
      $msg.= "<tr><th>$nazev</th><td align='right'>$n</td><td>&nbsp;&nbsp;&nbsp;&nbsp;$ppm</td></tr>";
    }
    $msg.= "</table>";
  }
  return $msg;
}
# ------------------------------------------------------------------------------==> . sta2 mrop stat
# agregace údajů o absolventech MROP
function sta2_mrop_stat($par) {
  global $ezer_path_docs;
  $msg= '';  
  switch ($par->op) {
  case 'gen':
    $msg= sta2_mrop_stat_gen($par);
    break;
  case 'see':   // ---------------------------- statistiky
    $msg= sta2_mrop_stat_see($par,$title);
    $msg= $title.$msg;
    break;
  case 'see2':  // ---------------------------- statistiky verze 2.0
    $msg= sta2_mrop_stat2_see($par,$title);
    $msg= $title.$msg;
    break;
  case 'see-t': // ---------------------------- statistiky po letech
    $delta= 8;
    $delta= 4;
//    $delta= 2;
//    $delta= 1;
    $letos= date('Y');
    $title= '';
    $ths= $tds= '';
    for ($od= 2004; $od<$letos; $od+= $delta) {
      $do= $od+$delta-1;
      $par->od= $od;
      $par->do= $do;
      $ths.= "<th><h3>$od .. $do</h3></th>";
      $tds.= "<td style='vertical-align:top'>".sta2_mrop_stat_see($par,$title)."</td>";
    }
    $msg= "<div>$title</div><table><tr>$ths</tr><tr>$tds</tr></table>";
    break;
  // načtení tabulky #psc   (psc,kod_obec,kod_okres,kod_kraj)
  // a tabulky       #okres (kod_okres,kod_kraj,nazev)
  // a tabulky       #kraj  (kod_kraj,nazev,muzi,lidi)
  // ze souboru staženého z https://www.ceskaposta.cz/ke-stazeni/zakaznicke-vystupy#1
  //                  2 psc 3 kod_obec      5 kod_okres         7 kod_kraj
  // (kodcobce,nazcobce,psc,kodobce,nazobce,kodokresu,nazokresu,kodkraj,nazevkraj,kodmomc,nazmomc,kodpobvod,nazpobvod)
  // a souboru zkombinovaného z 
  //   http://eagri.cz/public/app/eagricis/Forms/Lists/Nuts/NutsListsPage.aspx (kod a nuts)
  //   a https://www.czso.cz/csu/czso/pocet-obyvatel-v-obcich-see2a5tx8j (obyvatelstvo okresů a krajů)
  // (kodokresu,nuts_okresu,nazev,obyvatel,muzu) k 1.1.2018     
  // 
  // načtení tabulky #obce  (kod_obec,nazev,muzi,muzi15,zeny,zeny15)
  // ze souboru staženého z https://www.mvcr.cz/clanek/informativni-pocty-obyvatel-v-obcich.aspx
  case 'psc':
    $pscs= $okress= $krajs= $muzi= $lidi= array();
    // vynulování pracovní tabulky #okres $kraj
    query("TRUNCATE TABLE `#okres`");
    query("TRUNCATE TABLE `#kraj`");
    // načtení lidnatosti okresů
    $fullname= "$ezer_path_docs/import/psc/lidnatost_okresu_2018.csv";
    if ( !file_exists($fullname) ) { $msg.= "soubor $fullname neexistuje "; goto end; }
    $f= @fopen($fullname, "r");
    if ( !$f ) { $msg.= "soubor $fullname nelze otevřít"; goto end; }
    $line= fgets($f, 1000); // hlavička
    while (($line= fgets($f, 1000)) !== false) {
      $data= str_getcsv($line,';'); 
      $muzi[$data[0]]= $data[4];
      $lidi[$data[0]]= $data[3];
    }
    // vynulování pracovní tabulky #psc
    query("TRUNCATE TABLE `#psc`");
    $fullname= "$ezer_path_docs/import/psc/zv_cobce_psc.csv";
    if ( !file_exists($fullname) ) { $msg.= "soubor $fullname neexistuje "; goto end; }
    $f= @fopen($fullname, "r");
    if ( !$f ) { $msg.= "soubor $fullname nelze otevřít"; goto end; }
    $line= fgets($f, 1000); // hlavička
    while (($line= fgets($f, 1000)) !== false) {
      $line= win2utf($line,1);
      $data= str_getcsv($line,';'); 
      $psc= $data[2];
      $okres= $data[5];
      $kraj= $data[7];
      if ( in_array($psc,$pscs)) continue;
      $pscs[]= $psc;
      query("INSERT INTO `#psc` (psc,kod_obec,kod_okres,kod_kraj) 
              VALUE ($psc,$data[3],$okres,$kraj)");
      if ( !in_array($okres,$okress) ) {
        $okress[]= $okres;
        query("INSERT INTO `#okres` (kod_okres,kod_kraj,nazev,muzi,lidi) 
                VALUE ($okres,$kraj,'$data[6]',$muzi[$okres],$lidi[$okres])");
      }
      if ( !in_array($kraj,$krajs) ) {
        $krajs[]= $kraj;
        query("INSERT INTO `#kraj` (kod_kraj,nazev) 
                VALUE ($kraj,'$data[8]')");
      }
    }
    fclose($f); $f= null;
    $msg.= "<br>načteno ".count($pscs)." PSČ";
    // vynulování pracovní tabulky #obce
    query("TRUNCATE TABLE `#obec`");
    $n= 0;
    $fullname= "$ezer_path_docs/import/psc/lidnatost_obci_2020.csv";
    if ( !file_exists($fullname) ) { $msg.= "soubor $fullname neexistuje "; goto end; }
    $f= @fopen($fullname, "r");
    if ( !$f ) { $msg.= "soubor $fullname nelze otevřít"; goto end; }
    $line= fgets($f, 1000); // hlavička
    while (($line= fgets($f, 1000)) !== false) {
      $data= str_getcsv($line,';'); 
      $obec= $data[0];
      $nazev= $data[1];
      $muzi= str_replace(' ','',$data[2]);
      $mu15= str_replace(' ','',$data[3]);
      $zeny= str_replace(' ','',$data[4]);
      $ze15= str_replace(' ','',$data[5]);
      query("INSERT INTO `#obec` (kod_obec,nazev,muzi,muzi15,zeny,zeny15) 
              VALUE ($obec,'$nazev',$muzi,$mu15,$zeny,$ze15)");
      $n++;
    }
    fclose($f); $f= null;
    $msg.= "<br>načteno $n obcí";
    break;
  }
end:
  return $msg;
}
# -------------------------------------------------------------------------==> . sta2 mrop stat2 gen
# interpretace údajů o absolventech MROP
# par.typ = posloupnost písmen   g=geo informace s=statistika
# par.od-do = pokud je zadáno, omezuje to statistiku na období <od,do)
# do title se píše univerzální nadpis a poznámka (společná pro vývooj v čase)
function sta2_mrop_stat2_see($par,&$title) { trace();
  $typ= isset($par->typ) ? $par->typ : '';
  $msg= '';  
  // ------------------------------ podle velikosti obcí
  if ( strstr($typ,'o')) {
    $meze= array(0,1,10,100,1000,10000,100000,1000000);
    $meze= array(0,300,1000,3000,10000,30000,100000,300000,1000000);
    $meze= array(0,290,576,1084,2230,4650,9700,21200,46600,300000,1000000);
    $muzi= $obce= $ms= array();
    $s_muzi= $s_inic= $s_obce= $s_ms= 0;
    $title= "<h2>Iniciovaní podle velikosti obce - verze 2.0</h2><i>
      sloupec <b>obce podle počtu mužů</b> zobrazuje meze počtu mužů žijících v obci
      <br>sloupec <b>počet</b> je počet takových obcí
      <br>sloupec <b>inic.</b> je počet iniciovaných v takových obcích
      <br>žlutý sloupec <b>%</b> je procento z celkem iniciovaných (v daném období)
      <br>sloupec <b>‰</b> je promile iniciovaných mužů v takových obcích
      <br>sloupec <b>z mužů</b> je počet mužů v takových obcích (meze jsou proto tak kostrbaté aby byly počty srovnatelné)
      <br>sloupec <b>% MS</b> je procento absolventů MS (je jedno jestli před nebo po iniciaci)
      <br><br>Poznámka: odchylka v počtu mužů podle obcí a PSČ vznikla tím,
      že tabulka okresu je z roku 2018, tabulka PSČ a obcí z roku 2020 </i>
      <br><br>";
    $msg.= "<br><table class='stat'><tr><th></th><th>velikost obce</th><th>počet</th>
      <th>inic.</th><th>%</th>
      <th> ‰ </th><th>z mužů</th><th>% MS</th></tr>";
    // celkem iniciovaných
    $cr_inic= select('COUNT(*)','osoba',"iniciace>0");
    // výpočet počtu mužů v obcích dané velikosti
    for ($i=count($meze)-2; $i>=0; $i--) {
      list($m,$o)= select('SUM(muzi),COUNT(*)','`#obec`',
          "muzi BETWEEN $meze[$i] AND {$meze[$i+1]}");
      $s_muzi+= $muzi[$i]= $m;
      $s_obce+= $obce[$i]= $o;
    }
//                                                debug($obce,$s_obce);
    // výpočet počtu iniciovaných v obcích dané velikosti
    $ai= $am= array(); // A-J -> iniciovaní, MS
    $A= ord('A');
    for ($i=count($meze)-2; $i>=0; $i--) {
      $od= $i==0 ? '.' : $meze[$i];
      $do= $i==count($meze)-2 ? '.' : $meze[$i+1];
      // výpočet 
      $inic= $ms_inic= 0;
      $max= array(); // mez -> počet 
      $n= select('COUNT(*)','osoba JOIN osoba_geo USING (id_osoba) JOIN `#obec` USING (kod_obec)',
          "iniciace>0 AND muzi BETWEEN $meze[$i] AND {$meze[$i+1]}");
      $inic+= $n;
      $s_inic+= $n;
      $max[chr($A)]= max($n,$max[chr($A)]);
//      foreach ($pscs as $psc=>$n) {
//        $ok= select('COUNT(*)','`#psc` JOIN `#obec` USING (kod_obec)',
//            "psc=$psc AND muzi BETWEEN $meze[$i] AND {$meze[$i+1]}");
//        if ( $ok ) {
//          $inic+= $n;
//          $s_inic+= $n;
//          $max[chr($A)]= max($n,$max[chr($A)]);
//          if ( isset($mss[$psc])) {
//            $ms_inic+= $mss[$psc];
//            $s_ms+= $mss[$psc];
//          }
//        }
//      }
      $ppm= $muzi[$i] ? round(1000*$inic/$muzi[$i],2) : '?';
      $pms= $inic ? round(100*$ms_inic/$inic) : '?';
      $pi= $inic ? round(100*$inic/$cr_inic) : '?';
      $msg.= "<tr><th>".chr($A)."</th><th>$od..$do</th><td align='right'>$obce[$i]</td>
        <td align='right'>$inic</td>
        <td align='right' $main>$pi</td>
        <td>&nbsp;&nbsp;&nbsp;&nbsp;$ppm</td>
        <td align='right'>$muzi[$i]</td>
        <td>&nbsp;&nbsp;&nbsp;&nbsp;$pms</td>
      </tr>";
      $ai[$A]= $pi;
      $am[$A]= $pms;
      $A++;
    }
//                                                  debug($max);
    $ppm= round(1000*$s_inic/$s_muzi,2);
    $pms= round(100*$s_ms/$s_inic);
    $msg.= "<tr><th></th><th>celkem</th><th align='right'>$s_obce</th>
      <th align='right'>$s_inic</th><th>100</th>
      <th align='right'>&nbsp;&nbsp;&nbsp;&nbsp;$ppm</th><th align='right'>$s_muzi</th>
      <th align='right'>&nbsp;&nbsp;&nbsp;&nbsp;$pms</th>  
    </tr>";
    $msg.= "</table>";
    // pokus o graf
    $tr1= $tr2= '';
    $styl= "vertical-align:bottom;display:inline-block";
    for ($i= ord('A'); $i<$A; $i++) {
      $hi= $ai[$i]*5;
      $hm= $am[$i]*2;
      $w= 15;
//      $wtd= 2*$w+2;
      $wtd= $w;
      $idiv= "<div class='curr_akce' style='height:{$hi}px;width:{$w}px;$styl'></div>";
//      $mdiv= "<div style='background:orange;height:{$hm}px;width:{$w}px;$styl'></div>";
      $tr1.= "<td style='height:170px;width:{$wtd}px;vertical-align:bottom'>$idiv$mdiv</td>";
      $tr2.= "<th>".chr($i)."</th>";
    }
    $msg.= "<br><table class='stat'><tr>$tr1</tr><tr>$tr2</tr></table>";
  }
  return $msg;
}
# --------------------------------------------------------------------------==> . sta2 mrop stat gen
# interpretace údajů o absolventech MROP
# par.typ = posloupnost písmen   g=geo informace s=statistika
# par.od-do = pokud je zadáno, omezuje to statistiku na období <od,do)
# do title se píše univerzální nadpis a poznámka (společná pro vývooj v čase)
function sta2_mrop_stat_see($par,&$title) { trace();
  $typ= isset($par->typ) ? $par->typ : '';
  $msg= '';  
  $main= "style='background:yellow'"; // styl hlavního sloupce
  $AND= isset($par->od) ? (
      $par->od==$par->do ? "AND $par->od=mrop" : "AND $par->od<=mrop AND mrop<$par->do") : '';
  if ( $typ!='x') {
    $n= 0;
    $cr_inic= $vsichni= $veky= 0;
    $pred= $po= $bez_ms= $firms= 0;
    $s_ms_pred= $s_ms_po= $jen_pred= $jen_po= $jen_mrop= $pred_i_po= 0;
    $zenati= $nezenati= $nezenati_znami= $jen_mrop_zenati= 0;
    $svatba_po= $svatba_pred= 0;
    $deti= $deti3plus= 0;
    $ms_org= array(0,0,0,0,0);
    $cizinci= 0;
    $okres= $kraj= $pscs= $mss= array();
    $sr= pdo_qry("
      SELECT id_osoba,vek,mrop,stat,psc,svatba,deti,ms,lk_pred,lk_po,ms_pred,ms_po,m_pred,m_po,j_pred,j_po,firm
      FROM `#stat` WHERE 1 $AND
    ");
    while ( $sr && 
        list($ido,$vek,$mrop,$stat,$psc,$svatba,$det,$ms,
        $lk_pred,$lk_po,$ms_pred,$ms_po,$m_pred,$m_po,$j_pred,$j_po,$firm)
        = pdo_fetch_row($sr) ) {
      $n++;
      $vsichni++;
      // výpočty
      if ( $stat=='CZ' && $psc ) {
        // napřed na MS? nebo až potom
        $s_ms_pred+= $lk_pred ? 1 : 0;
        $s_ms_po+=   $lk_po ? 1 : 0;
        // další
        $x_pred=    $lk_pred+$ms_pred+$m_pred+$j_pred;
        $x_po=      $lk_po+$ms_po+$m_po+$j_po;
        // sumy
        $veky+= $vek;
        $zenat=     $svatba ? 1 : ($det ? 1 : 0);
        $zenati+=   $zenat;
        $firms+= $firm>0 ? 1 : 0;
        if ( !$zenat )
          $nezenati++;
        $bez_ms+=   $lk_pred+$ms_pred+$lk_po+$ms_po ? 0 : $zenat;
        if ( $ms )
          $ms_org[$ms]++;
        $deti+=     $det ? 1 : 0;
        $deti3plus+=$det>=3 ? 1 : 0;
        $nezenati_znami+=   $zenat ? 0 : ( $x_pred+$x_po ? 1 : 0);
        $pred+=     $x_pred;
        $po+=       $x_po;
        $jen_pred+= $x_po   ? 0 : ($x_pred ? 1 : 0);
        $jen_po+=   $x_pred ? 0 : ($x_po   ? 1 : 0);
        $jen_mrop+= $x_pred+$x_po ? 0 : 1;
        $jen_mrop_zenati+= $x_pred+$x_po ? 0 : ($svatba ? 1 : 0);
        $pred_i_po+= $x_pred && !$x_po || !$x_pred && $po ? 1 : 0;
        $svatba_po+=   $svatba<2222 && $svatba>$mrop ? 1 : 0;
        $svatba_pred+= $svatba>0 && $svatba<=$mrop ? 1 : 0;
        $svatba_nevime+= $svatba ? 0 : ($det ? 1 : 0);
        // geo informace
        if ( !isset($pscs[$psc]) ) $pscs[$psc]= 0;
        $pscs[$psc]++;
        if ( $ms ) {
          if ( !isset($mss[$psc]) ) $mss[$psc]= 0;
          $mss[$psc]++;
        }
        $cr_inic++;
        list($k_obec,$k_okres,$k_kraj)= select('kod_obec,kod_okres,kod_kraj','`#psc`',"psc=$psc");
        if ( !$k_kraj ) {
          $msg.= "<br>PSČ $psc neznáme id=".tisk2_ukaz_osobu($ido);
        }
        else {
          if ( !isset($okres[$k_okres])) $okres[$k_okres]= 0;
          $okres[$k_okres]++;
          if ( !isset($kraj[$k_kraj])) $kraj[$k_kraj]= 0;
          $kraj[$k_kraj]++;
        }
      }
      else $cizinci++;
    }
  }
  // ------------------------------ podle věku 
  // údaj % MS znamená byl na MS před iniciací
  if ( strstr($typ,'v')) {
    $meze= array(10,20,30,40,50,60,70,80,90,99);
    $meze= array(15,20,25,30,35,40,45,50,55,60,65,70,75,80,85,90,95,99);
    $stari= array();
    $s_inic= $s_ms= 0;
    // titulek
    $title= "<h3>Iniciovaní podle věku</h3><i>
      sloupec % je procento z celkem iniciovaných, 
      <br>sloupec %MS jen procento těch, kteří před iniciací byli účastníky MS
      </i><br><br>";
    // zobrazení
    $prumer= round($veky/$cr_inic);
    $msg.= "<b>$cr_inic inic. z ČR - $prumer roků</b><br>
      <table class='stat'><tr><th>věk</th><th>počet</th><th>%</th><th>% MS</th></tr>";
    for ($i=0; $i<count($meze)-1; $i++) {
      list($m,$m2)= select('COUNT(*),SUM(IF(lk_pred>0,1,0))','`#stat`',
          "stat='CZ' AND vek >= $meze[$i] AND vek < {$meze[$i+1]} $AND ");
      $s_inic+= $stari[$i]= $m;
      $s_ms+= $m2;
      $od= $i==0 ? '.' : $meze[$i]+1;
      $do= $i==count($meze)-2 ? '.' : $meze[$i+1];
      $pm= $stari[$i] ? round(100*$stari[$i]/$cr_inic) : '-';
      $pm2= $pm < 2 ? '(-)'
          : ($stari[$i] ? round(100*$m2/$stari[$i]) : '-');
      $msg.= "<tr><th>$od..$do</th><td align='right'>$stari[$i]</td>
        <td align='right' $main>&nbsp;&nbsp;&nbsp;&nbsp;$pm</td>
        <td align='right'>&nbsp;&nbsp;&nbsp;&nbsp;$pm2</td>
      </tr>";
    }
    $pm= $cr_inic ? round(100*$s_inic/$cr_inic) : '-';
    $pm2= $s_inic ? round(100*$s_ms/$s_inic) : '-';
    $msg.= "<tr><th>celkem</th><th align='right'>$s_inic</th>
      <th align='right'>&nbsp;&nbsp;&nbsp;&nbsp;$pm</th>
      <th align='right'>&nbsp;&nbsp;&nbsp;&nbsp;$pm2</th>
    </tr>";
    $msg.= "</table>";
  }
  // ------------------------------ statistické informace CPR
  $no_ms= 0;
  $ms_cpr= 0;
  if ( strstr($typ,'c') || strstr($typ,'s')) {
    $sr= pdo_qry("
      SELECT id_osoba,vek,mrop,note,svatba
      FROM `#stat`
      WHERE stat='CZ' AND ms_pred=0 AND ms_po=0 $AND
    ");
    while ( $sr && list($ido,$vek,$mrop,$note,$svatba)= pdo_fetch_row($sr) ) {
      $no_ms++;
      list($cpr_vek,$cpr_svatba)= select('YEAR(narozeni),YEAR(datsvatba)',
          'ezer_cr.osoba JOIN ezer_cr.tvori USING (id_osoba) JOIN ezer_cr.rodina USING (id_rodina) ',
          "CONCAT(jmeno,' ',prijmeni)='$note' AND role='a'");
      $x= $mrop-$vek;
      if ( $cpr_vek && abs($cpr_vek-$x)<2 ) {
        $ms_cpr++;
        $ms_org[4]++;
        display("$note/$cpr_vek..$x $cpr_svatba/$svatba tj. ".($cpr_svatba?($cpr_svatba>$mrop?'po':'před'):'?'));
      }
    }
    if ( strstr($typ,'c') ) {
      $msg.= "$no_ms (asi) ženatých mužů z ČR nebyli na MS pořádaných YS nebo FA
        <br>ale $ms_cpr mohlo podle shody jména být na MS pořádaném CPR
       ";
    }
    $bez_ms-= $ms_cpr;
  }
  // ------------------------------ statistické informce YS + FA + (CPR)
  if ( strstr($typ,'s')) {
    $x1= $nezenati-$nezenati_znami;
    $msg.= "<b>Celkem $n iniciovaných mužů z toho $cizinci cizinci</b>
      <br>další údaje jsou bez cizinců<br>
      <br><b>ženatí</b>
      <br>celkem ženatí = $zenati
      <br>... z toho před MROP = $svatba_pred
      <br>... z toho po MROP = $svatba_po
      <br>... kdy byla svatba nevíme = $svatba_nevime
      <br>... nebyl na MS YS nebo FA nebo CPR = $bez_ms
      <br>... nebyl na žádné akci = $jen_mrop_zenati
      <br>celkem neženatí = $nezenati
      <br>... nebyl na žádné akci = $x1
      <br>
      <br><b>účasti na MS</b>
      <br>... YS = $ms_org[1]
      <br>... FA = $ms_org[2]
      <br>... CPR = $ms_org[4]
      <br>... YS i FA = $ms_org[3]
      <br>
      <br><b>kdy na MS</b>
      <br>... před iniciací = $s_ms_pred
      <br>... po iniciaci = $s_ms_po
      <br>
      <br><b>děti</b>
      <br>mají děti = $deti
      <br>... 3 a více = $deti3plus
      <br>
      <br><b>akce</b>
      <br>byl pouze na akcích před MROP = $jen_pred 
      <br>... pouze na akcích po MROP = $jen_po 
      <br>... byl na akcích před i po = $pred_i_po
      <br>... byl na firmingu = $firms
      <br>nebyl na žádné akci mimo MROP = $jen_mrop
      <br>";
    $title= "<h2>Iniciovaní muži - přehled</h2><i>Poznámka: 
      akcí se rozumí akce pořádaná YS a MS (mimo MROP), případně MS pořádané CPR</i>
     ";
//      <br>celkem účastí na akcích před = $pred 
//      <br>celkem účastí na akcí po = $po
  }
  // ------------------------------ celkově geo
  if ( strstr($typ,'g') || strstr($typ,'o')) {
    $cr_muzi= 0;
    $kraj_muzi= $kraj_ppn= array();
    $okres_muzi= $okres_ppn= array();
    $sr= pdo_qry("SELECT kod_kraj,kod_okres,muzi FROM `#okres`");
    while ( $sr && list($k_kraj,$k_okres,$muzu)= pdo_fetch_row($sr)) {
      $n= isset($okres[$k_okres]) ? $okres[$k_okres] : 0;
      if ( !isset($kraj_muzi[$k_kraj])) $kraj_muzi[$k_kraj]= 0;
      $cr_muzi+= $muzu;
      $kraj_muzi[$k_kraj]+= $muzu;
      $okres_muzi[$k_okres]= $muzu;
    }
    foreach ($kraj as $k_kraj=>$n) {
      $kraj_ppn[$k_kraj]= round(1000*$n/$kraj_muzi[$k_kraj],2);
    }
    arsort($kraj_ppn);
    // geo ČR
    $ppm= round(1000*$cr_inic/$cr_muzi,2);
    $msg.= "<div>celkem $cr_inic tj. $ppm ‰ iniciovaných</div>";
  }
  // ------------------------------ podle velikosti obcí
  if ( strstr($typ,'o')) {
    $meze= array(0,1,10,100,1000,10000,100000,1000000);
    $meze= array(0,300,1000,3000,10000,30000,100000,300000,1000000);
    $meze= array(0,290,576,1084,2230,4650,9700,21200,46600,300000,1000000);
    $muzi= $obce= $ms= array();
    $s_muzi= $s_inic= $s_obce= $s_ms= 0;
    $title= "<h2>Iniciovaní podle velikosti obce</h2><i>
      sloupec <b>obce podle počtu mužů</b> zobrazuje meze počtu mužů žijících v obci
      <br>sloupec <b>počet</b> je počet takových obcí
      <br>sloupec <b>inic.</b> je počet iniciovaných v takových obcích
      <br>žlutý sloupec <b>%</b> je procento z celkem iniciovaných (v daném období)
      <br>sloupec <b>‰</b> je promile iniciovaných mužů v takových obcích
      <br>sloupec <b>z mužů</b> je počet mužů v takových obcích (meze jsou proto tak kostrbaté aby byly počty srovnatelné)
      <br>sloupec <b>% MS</b> je procento absolventů MS (je jedno jestli před nebo po iniciaci)
      <br><br>Poznámka: odchylka v počtu mužů podle obcí a PSČ vznikla tím,
      že tabulka okresu je z roku 2018, tabulka PSČ a obcí z roku 2020 </i>
      <br><br>";
    $msg.= "<br><table class='stat'><tr><th></th><th>velikost obce</th><th>počet</th>
      <th>inic.</th><th>%</th>
      <th> ‰ </th><th>z mužů</th><th>% MS</th></tr>";
    // výpočet počtu mužů v obcích dané velikosti
    for ($i=count($meze)-2; $i>=0; $i--) {
      list($m,$o)= select('SUM(muzi),COUNT(*)','`#obec`',
          "muzi BETWEEN $meze[$i] AND {$meze[$i+1]}");
      $s_muzi+= $muzi[$i]= $m;
      $s_obce+= $obce[$i]= $o;
    }
//                                                debug($obce,$s_obce);
    // výpočet počtu iniciovaných v obcích dané velikosti
    $ai= $am= array(); // A-J -> iniciovaní, MS
    $A= ord('A');
    for ($i=count($meze)-2; $i>=0; $i--) {
      $od= $i==0 ? '.' : $meze[$i];
      $do= $i==count($meze)-2 ? '.' : $meze[$i+1];
      // výpočet 
      $inic= $ms_inic= 0;
      $max= array(); // mez -> počet 
      foreach ($pscs as $psc=>$n) {
        $ok= select('COUNT(*)','`#psc` JOIN `#obec` USING (kod_obec)',
            "psc=$psc AND muzi BETWEEN $meze[$i] AND {$meze[$i+1]}");
        if ( $ok ) {
          $inic+= $n;
          $s_inic+= $n;
          $max[chr($A)]= max($n,$max[chr($A)]);
          if ( isset($mss[$psc])) {
            $ms_inic+= $mss[$psc];
            $s_ms+= $mss[$psc];
          }
        }
      }
      $ppm= $muzi[$i] ? round(1000*$inic/$muzi[$i],2) : '?';
      $pms= $inic ? round(100*$ms_inic/$inic) : '?';
      $pi= $inic ? round(100*$inic/$cr_inic) : '?';
      $msg.= "<tr><th>".chr($A)."</th><th>$od..$do</th><td align='right'>$obce[$i]</td>
        <td align='right'>$inic</td>
        <td align='right' $main>$pi</td>
        <td>&nbsp;&nbsp;&nbsp;&nbsp;$ppm</td>
        <td align='right'>$muzi[$i]</td>
        <td>&nbsp;&nbsp;&nbsp;&nbsp;$pms</td>
      </tr>";
      $ai[$A]= $pi;
      $am[$A]= $pms;
      $A++;
    }
//                                                  debug($max);
    $ppm= round(1000*$s_inic/$s_muzi,2);
    $pms= round(100*$s_ms/$s_inic);
    $msg.= "<tr><th></th><th>celkem</th><th align='right'>$s_obce</th>
      <th align='right'>$s_inic</th><th>100</th>
      <th align='right'>&nbsp;&nbsp;&nbsp;&nbsp;$ppm</th><th align='right'>$s_muzi</th>
      <th align='right'>&nbsp;&nbsp;&nbsp;&nbsp;$pms</th>  
    </tr>";
    $msg.= "</table>";
    // pokus o graf
    $tr1= $tr2= '';
    $styl= "vertical-align:bottom;display:inline-block";
    for ($i= ord('A'); $i<$A; $i++) {
      $hi= $ai[$i]*5;
      $hm= $am[$i]*2;
      $w= 15;
//      $wtd= 2*$w+2;
      $wtd= $w;
      $idiv= "<div class='curr_akce' style='height:{$hi}px;width:{$w}px;$styl'></div>";
//      $mdiv= "<div style='background:orange;height:{$hm}px;width:{$w}px;$styl'></div>";
      $tr1.= "<td style='height:170px;width:{$wtd}px;vertical-align:bottom'>$idiv$mdiv</td>";
      $tr2.= "<th>".chr($i)."</th>";
    }
    $msg.= "<br><table class='stat'><tr>$tr1</tr><tr>$tr2</tr></table>";
  }
  // ------------------------------ iniciovaní okresů a krajů
  if ( strstr($typ,'g')) {
    // titulek
    $title= "<h2>Iniciovaní v krajích a okresech ČR</h2><i>
      sloupec ‰ je promile iniciovaných z mužů v daném kraji nebo okresu, 
      <br>podle tohoto údaje je tabulka také seřazena (v ČR žije $cr_muzi mužů)
      </i><br><br>";
    // geo tabulka krajů
    $msg.= "<table class='stat'><tr><th>kraj</th><th>iniciovaní</th><th> ‰ mužů kraje</th></tr>";
    foreach ($kraj_ppn as $k_kraj=>$ppn) {
      $nazev= select('nazev','`#kraj`',"kod_kraj=$k_kraj");
      $n= $kraj[$k_kraj];
      $msg.= "<tr><th>$nazev</th><td align='right'>$n</td><td>&nbsp;&nbsp;&nbsp;&nbsp;$ppn</td></tr>";
    }
    $msg.= "</table>";
    // geo info - okresy
    foreach ($okres as $k_okres=>$n) {
      $nazev= select('nazev','`#kraj`',"kod_kraj=$k_kraj");
      $okres_ppn[$k_okres]= round(1000*$n/$okres_muzi[$k_okres],2);
    }
    arsort($okres_ppn);
    $msg.= "<h3>Iniciovaní v okresech ČR</h3>
      <table class='stat'><tr><th>okres</th><th>iniciovaní</th><th>‰ mužů okresu</th></tr>";
    foreach ($okres_ppn as $k_okres=>$ppm) {
      $nazev= select('nazev','`#okres`',"kod_okres=$k_okres");
      $n= $okres[$k_okres];
      $msg.= "<tr><th>$nazev</th><td align='right'>$n</td><td>&nbsp;&nbsp;&nbsp;&nbsp;$ppm</td></tr>";
    }
    $msg.= "</table>";
  }
  if ( strstr($typ,'x')) {
    $ido_test= 0;
//    $ido_test= 5877; // Já
    // titulek
    $title= "<h2>Tabulka vzájemného vlivu účasti na akci</h2><i>
      zkratky znamenají typ akce: 
      <br><b>i</b> - iniciace, <b>m</b> - manželáky, <b>k</b> - akce typu Křižanov, 
      <br><b>a</b> - akce typu Albeřice, <b>f</b> - akce typu Fišerka, <b>o</b> - akce pro otce s dětmi, 
      <br><b>r</b> - akce pro staré slony (firming, podpora iniciovaných)
      <br>
      <br>matice A vyjadřuje relaci <b>byl na nějaké ... dříve než na jakékoliv ...</b>
      <br>matice B vyjadřuje relaci <b>byl na ... a následně na ... </b>
      <br>údaje v maticích A% a B% jsou vztaženy k počtu iniciovaných
      <br>
      <br>sloupec &sum; je součet iniciovaných účastných i na akci daného typu
      </i><br><br>";
    // typy akcí
    $ido_cond= $ido_test ? "id_osoba=$ido_test" : '1';
    $akce= str_split('imkafor');
    $_akce= select("GROUP_CONCAT(DISTINCT akce SEPARATOR '')",' `#stat`',"$ido_cond $AND");
    $_akce= strtolower($_akce);
    $_akce= count_chars($_akce,3);    
    $_akce= str_split($_akce);
    // vyhoď neexistující typ
    foreach($akce as $i=>$a) {
      if ( !in_array($a,$_akce) ) unset($akce[$i]);
    }
    foreach(array('A%','B%','A','B') as $AB) {
      // matice A
      $m= $s= array();
      foreach($akce as $x) {
        $s[$x]= 0;
        foreach($akce as $y) {
          $m[$x][$y]= 0;
        }
      }
      // probrání #stat
      $sr= pdo_qry("SELECT akce,id_osoba,note FROM `#stat` WHERE akce!='' AND $ido_cond $AND");
      while ( $sr && list($xy,$ido,$name)= pdo_fetch_row($sr)) {
        if ( $ido_test && $ido!=$ido_test ) continue;
        if ($ido_test) display("$name: $xy");
        foreach($akce as $x) {
          $ix= stripos($xy,$x);
          if ( $ix!==false ) {
            $s[$x]++;
          }
          $offset= $AB[0]=='A' ? 0 : min($ix+1,strlen($xy)-1); 
          foreach($akce as $y) {
            $iy= stripos($xy,$y,$offset);
            if ( $ix!==false && $iy!==false && $ix<$iy) {
              $m[$x][$y]++;
            }
          }
        }
      }
      // převod na procenta pro tabulku X%
      if ( $AB[1]=='%') {
        $si= $s['i'];
        foreach($akce as $x) {
          foreach($akce as $y) {
            $m[$x][$y]= $m[$x][$y] ? round(100*$m[$x][$y]/$si) : '-';
          }
          $s[$x]= $s[$x] ? round(100*$s[$x]/$si) : '-';
        }
      }
      // tabulka 
      $tab= "<table class='stat'>";
      $th=  '';
      foreach($akce as $y) {
        $th.= "<th>$y</th>";
      }
      $tab.= "<tr><th>$AB</th><th>&sum;</th>$th</tr>";
      foreach($akce as $x) {
        $td= '';
        foreach($akce as $y) {
          $style= $y=='i' ? $main : '';
          $tx= $x==$y ? 'th' : 'td';
          $td.= "<$tx align='right' $style>{$m[$x][$y]}</$tx>";
        }
        $tab.= "<tr><th>$x</th><th align='right'>{$s[$x]}</th>$td</tr>";
      }
      $tab.= "</table>";
      $msg.= "<h3>Matice $AB</h3>$tab";
    }
    // trasování
    $dbg= array();
    $sr= pdo_qry("SELECT COUNT(*) AS _pocet,akce FROM `#stat` WHERE $ido_cond 
      GROUP BY akce ORDER BY _pocet DESC");
    while ( $sr && list($n,$xy)= pdo_fetch_row($sr)) {
      $dbg[$xy]= $n;
    }
//    debug($dbg);
  }
  return $msg;
}
# --------------------------------------------------------------------------==> . sta2 mrop stat gen
# agregace údajů o absolventech MROP
# pokud je par.x=a jen doplní položku akce
function sta2_mrop_stat_gen($par) {
  $ido_test= 0;
//  $ido_test= 5877; // Já
//  $ido_test= 26;  // Ludva
  $msg= "";
  $par_x= isset($par->x) ? $par->x : 'x';
  switch ($par_x) {
  case 'x': // --------------------------------------- osobní a staré pred/po položky
    // vynulování pracovní tabulky #stat
    query("TRUNCATE TABLE `#stat`");
    // seznam
    $mrops= array( // year => datum ... 2001,2002 nejsou v akcích
      2001=>'2001-08-01',2002=>'2002-09-01');
    $ms= array();
    // získání data mrop
    $mr= pdo_qry("
      SELECT YEAR(a.datum_od) AS _rok,a.datum_od FROM akce AS a 
      WHERE a.mrop=1 
      --  AND id_duakce=1373
      ORDER BY _rok
    ");
    while ( $mr && list($rok,$datum)= pdo_fetch_row($mr) ) {
      $mrops[$rok]= $datum;
    }
//    $mrops= array(2021=>'2021-09-15');
    // získání individuálních a rodinných údajů
    foreach ($mrops as $mrop=>$datum) {
      $n_iniciovani= select('COUNT(*)','osoba',"iniciace=$mrop");
      if ($n_iniciovani) {
        // dokončený ročník
        $AND= "AND o.iniciace=$mrop";
        $JOIN= '';
      }
      else {
        // iniciace v běhu
        $ida= select('id_duakce','akce',"mrop=1 AND YEAR(datum_od)='$mrop'");
        $AND= "AND o.iniciace=0 AND p.funkce=0";
        $JOIN= "JOIN pobyt AS p ON id_akce=$ida JOIN spolu AS s USING (id_osoba,id_pobyt)";
      }
//      $AND.= " AND o.id_osoba=4881";
      $mr= pdo_qry("
        SELECT o.id_osoba,
          IF(o.kontakt AND o.email!='',MD5(REGEXP_SUBSTR(UPPER(TRIM(o.email)),'^[A-Z0-9._%-]+@[A-Z0-9.-]+\.[A-Z]+')),''),
          o.access,CONCAT(o.jmeno,' ',o.prijmeni),
          -- ROUND(DATEDIFF('$datum',o.narozeni)/365.2425) AS _vek,
          ROUND(IF(MONTH(o.narozeni),DATEDIFF('$datum',o.narozeni)/365.2425,YEAR('$datum')-YEAR(o.narozeni))) AS _vek,
          IFNULL(svatba,0) AS _s1,IFNULL(YEAR(datsvatba),0) AS _s2,
          MIN(IFNULL(YEAR(od.narozeni),0)) AS _s3,
          SUM(IF(td.id_osoba,1,0)) AS _d,
          IFNULL(tb.id_osoba,0) AS _ido_z,
          IF(o.adresa=1,o.stat,r.stat) AS _stat,
          IF(o.adresa=1,o.psc,r.psc) AS _psc,
          o.firming
        FROM osoba AS o
        $JOIN
        LEFT JOIN tvori AS ta ON ta.id_osoba=o.id_osoba AND ta.role='a'
        LEFT JOIN rodina AS r ON r.id_rodina=ta.id_rodina
        LEFT JOIN tvori AS tb ON r.id_rodina=tb.id_rodina AND tb.role='b'
        LEFT JOIN tvori AS td ON r.id_rodina=td.id_rodina AND td.role='d'
        LEFT JOIN osoba AS od ON od.id_osoba=td.id_osoba
        WHERE o.deleted='' $AND
        GROUP BY o.id_osoba
      ");
      while ( $mr && 
          list($ido,$md5o,$access,$name,$vek,$sv1,$sv2,$sv3,$deti,$ido_z,$stat,$psc,$firm)
          = pdo_fetch_row($mr) ) {
        if ( $ido_test && $ido!=$ido_test ) continue;
        $ms[$ido]= (object)array('name'=>$name,'mrop'=>$mrop);
        // rozbor bydliště
        $stat= str_replace(' ','',$stat);
        $psc= str_replace(' ','',$psc);
        if ( $psc && is_numeric($psc)) {
          if ( $stat=='' && in_array($psc[0],array(1,2,3,4,5,6,7)) ) {
            $stat= 'CZ';
          }
          elseif ( $stat=='' && in_array($psc[0],array(0,8,9)) ) {
            $stat= 'SK';
          }
        }
        elseif ( $stat=='' ) {
          $stat= '?';
        }
        // zápis do #stat
        $sv= max($sv1,$sv2);
        if ( !$sv && $ido_z ) {
          $sv= $sv3 ? $sv3-1 : 9999;
        }
        query("INSERT INTO `#stat` (id_osoba,md5_osoba,access,mrop,firm,stat,psc,vek,svatba,deti,note) 
          VALUE ($ido,'$md5o',$access,$mrop,$firm,'$stat','$psc','$vek',$sv,$deti,'$name')");
      }
    }
    // získání informací o akcích- staré
    $akce_muzi= "24,5,11";
    $akce_manzele= "2,3,4,17,18,22"; // 18 je lektoři & vedoucí MS !!! TODO
    foreach ($ms as $ido=>$m) {
      if ( $ido_test && $ido!=$ido_test ) continue;
      $ma= pdo_qry("
        SELECT IF(datum_od<CONCAT({$m->mrop},'-09-11'),'_pred','_po'),
          CASE WHEN druh IN (1) THEN 'lk' 
               WHEN druh IN ($akce_manzele) THEN 'ms' 
               WHEN druh IN ($akce_muzi) THEN 'm' 
               ELSE 'j' END,
          statistika,mrop,firm,a.access
        FROM pobyt AS p
        LEFT JOIN akce AS a ON id_akce=id_duakce
        LEFT JOIN spolu AS s USING (id_pobyt)
        JOIN osoba AS o USING (id_osoba)
        WHERE id_osoba=$ido AND spec=0 AND mrop=0 AND zruseno=0 
        ORDER BY datum_od
      ");
      while ( $ma && list($kdy,$druh,$stat,$mrop,$firm,$org)= pdo_fetch_row($ma) ) {
        // zápis do #stat - mimo iniciaci
        $org_pred= $kdy=='_pred'
            ? ($org==1 ? ',ys_pred=ys_pred+1' : ($org==2 ? ',fa_pred=fa_pred+1' : '')) : '';
        query("UPDATE `#stat` SET $druh$kdy=1+$druh$kdy$org_pred WHERE id_osoba=$ido");
      }
      // kde byl na MS
      $ma= pdo_qry("
        SELECT COUNT(*),BIT_OR(a.access)
        FROM pobyt AS p
        LEFT JOIN akce AS a ON id_akce=id_duakce
        LEFT JOIN spolu AS s USING (id_pobyt)
        JOIN osoba AS o USING (id_osoba)
        WHERE id_osoba=$ido AND a.druh IN (1,2) AND spec=0 AND zruseno=0 
      ");
      while ( $ma && list($n,$org)= pdo_fetch_row($ma) ) {
        // zápis do #stat
        if ( $n )
          query("UPDATE `#stat` SET ms=$org WHERE id_osoba=$ido");
      }
    }
    $msg= "vytvořena tabulka #stat";
    break;
  case 'a': // ---------------------------------------- posloupnost akcí
    // vymaž starou statistiku akcí
    query("UPDATE `#stat` SET akce='' ");
    // vypočítej novou statistiku akcí
    $sr= pdo_qry("SELECT id_osoba,note FROM `#stat` ");
    while ( $sr && list($ido,$name)= pdo_fetch_row($sr) ) {
      if ( $ido_test && $ido!=$ido_test ) continue;
      $akce= $last_a= $opak= '';
      $ma= pdo_qry("
        SELECT statistika,druh,IF(funkce>0,0,mrop),firm
        FROM pobyt AS p
        LEFT JOIN akce AS a ON id_akce=id_duakce
        LEFT JOIN spolu AS s USING (id_pobyt)
        JOIN osoba AS o USING (id_osoba)
        WHERE id_osoba=$ido AND spec=0 AND zruseno=0 
        ORDER BY datum_od
      ");
      while ( $ma && list($stat,$druh,$mrop,$firm)= pdo_fetch_row($ma) ) {
        // tvorba posloupnosti akcí - mrop a firm zapíšeme jen první
        $a=  $mrop    ? 'i' : '';
        $a.= $stat==1 ? 'k' : '';
        $a.= $stat==2 ? 'a' : '';
        $a.= $stat==3 ? 'f' : '';
        $a.= $stat==4 ? 'o' : '';
        $a.= ($stat==5 || $firm) ? 'r' : '';
        $a.= $druh==1 ? 'm' : '';
        if ( $a ) {
            $akce.= $a;
//          if ( $a!=$last_a) {
//            $akce.= ($opak ? strtoupper($last_a) : $last_a);
//            $last_a= $a;
//            $opak= 0;
//          }
//          else {
//            $opak++;
//          }
        }
      }
      $akce.= $last_a ? ($opak ? strtoupper($last_a) : $last_a) : '';
      query("UPDATE `#stat` SET akce='$akce' WHERE id_osoba=$ido");
      if ($ido_test) display("$name: $akce");
    }
    $msg= "přepočítána položka #stat.akce";
    break;
  }
end:  
  return $msg;
}
/** ===========================================================================================> VPS **/
# ------------------------------------------------------------------------------------- vps historie
# 
function vps_historie ($org,$par,$export) {
  $cert= array(); // certifikát rok=>poslední číslo
  $letos= date('Y');
  list($mez1,$mez2)= explode(',',$par->parm);
  $hrana1= $letos - $mez1;
  $hrana2= $letos - $mez2;
  $vps1= $org==1 ? '3,17' : '3';
  // pole pro tabulku
  $clmn= $css= array();
// sloupce
  $tits= array("pár:26","poprvé:10","kolikrát:10","naposledy:10",
            $org==1?"VPS1:10":"1.školení:10",
            "VPS2");
  $flds= array('jm','od','n','do','vps_i','vps2');
  // sloupce
  $tits= array("pár:26","kolikrát:10","VPS2",'skupinky');
  $flds= array('jm','n','vps2','hodn');
  // doplnění nadpisů historie
  for ($r=$letos; $r>=$hrana2; $r--) {
    $r2= $r%100;
    array_push($tits,"$r2");
    array_push($flds,$r2);
  }
  // seznam VPS a základní údaje
  $rx= pdo_qry("SELECT
      r.id_rodina,r.nazev,
      GROUP_CONCAT(DISTINCT IF(t.role='a',o.jmeno,'') SEPARATOR '') as jmeno_m,
      GROUP_CONCAT(DISTINCT IF(t.role='b',o.jmeno,'') SEPARATOR '') as jmeno_z,
      MIN(IF(druh=1 AND funkce=1,YEAR(datum_od),9999)) AS OD,
      CEIL(CHAR_LENGTH(
        GROUP_CONCAT(DISTINCT IF(druh=1 AND funkce=1,YEAR(datum_od),'') SEPARATOR ''))/4) AS Nx,
      MAX(IF(druh=1 AND funkce=1,YEAR(datum_od),0)) AS DO,
      MIN(IF(druh IN ($vps1),YEAR(datum_od),9999)) as VPS_I,
      1
    FROM rodina AS r
    JOIN pobyt AS p
    JOIN akce as a ON id_akce=id_duakce
    JOIN tvori AS t USING (id_rodina)
    JOIN osoba AS o USING (id_osoba)
    WHERE spec=0 AND zruseno=0 AND r.id_rodina=i0_rodina AND a.access&$org
      AND druh IN (1,$vps1)
      --  AND r.id_rodina=3329 
    GROUP BY r.id_rodina
    ORDER BY r.nazev");
  while ( $rx && ($x= pdo_fetch_object($rx)) ) {
    $idr= $x->id_rodina;
    // číslování certifikátů
    $skola= $x->VPS_I==9999 ? 0 : $x->VPS_I;
    $c1= $c2= '';
    if ( $skola ) {
      if ( !isset($cert[$skola]) ) $cert[$skola]= 0;
      $cert[$skola]++; $c1= ($org==1?'vps':'pps')."_$skola/{$cert[$skola]}";
      $cert[$skola]++; $c2= ($org==1?'vps':'pps')."_$skola/{$cert[$skola]}";
    }
    // ohlídání období
    if ( $x->DO<$hrana1 ) continue;
    $cclen= $_cinny_od ?: '-';
    // odpověď 1
    $cl= array(
      'jm'=>"{$x->jmeno_m} a {$x->jmeno_z} {$x->nazev}",
      'od'=>$x->OD,'n'=>$x->Nx,'do'=>$x->DO,
      'vps_i'=>$skola ?: '-'
    );
    // doplnění odpověďi podle mez2+1 běhů
    if ( $org==1 
//        && $idr==3329 
        ) {
      // celkový počet účastí na akcích pro činné členy
      $cl['vps2']= select('COUNT(*)','akce JOIN pobyt ON id_akce=id_duakce',
          "akce.druh=3 AND nazev RLIKE 'VPS *(2|II)' AND i0_rodina=$idr");
    }
    $clmn[$idr]= $cl;
  }
  // doplnění průběhu ročních běhů
  for ($r=$letos; $r>=$hrana2; $r--) {
    $r2= $r%100; $lk= "$r2/L"; $a[0]= "$r2/P"; $a[1]= "$r2/J"; $r1= $r+1;
    $ida_lk= select('id_duakce','akce',"access=$org && druh=1 AND YEAR(datum_od)=$r");
    $ida[0]= select('id_duakce','akce',"access=$org && druh=2 AND datum_od BETWEEN '$r-09-01' AND '$r-12-31'")?:0;
    $ida[1]= select('id_duakce','akce',"access=$org && druh=2 AND datum_od BETWEEN '$r1-01-01' AND '$r1-05-31'")?:0;
    // získej skupinky LK daného roku pro sledované VPS
    $skups= array(); // idr_vps -> [idr_ucastnik,...]
    $rs= pdo_qry("
      SELECT GROUP_CONCAT(i0_rodina ORDER BY IF(funkce=1,1,0) DESC) AS _skup
      FROM pobyt AS p
      WHERE id_akce=$ida_lk AND skupina>0
      GROUP BY skupina
      ORDER BY skupina");
    while ( $rs && (list($skup)= pdo_fetch_array($rs)) ) {
      $idrs= explode(',',$skup);
      $vps= array_shift($idrs);
      if ( isset($clmn[$vps]) )
        $skups[$vps]= $idrs;
    }
    // ohodnoť přítomnost na obnovách: 3=všichni; 2=někteří; 1=žádní
    $ox= array();
    for ($i= 0; $i<=1; $i++ ) {
      $idrs= select("GROUP_CONCAT(i0_rodina)",'pobyt',"id_akce=$ida[$i]");
      $ucast= explode(',',$idrs);
      foreach ($skups as $vps => $skup) {
        if ( $idrs ) {
          $jo= $ne= 0;
          foreach ($skup as $idr) {
            if ( in_array($idr,$ucast) ) 
              $jo++;
            else 
              $ne++;
          }
          $clmn[$vps][$r2].= 
              $jo && !$ne ? 3 : ( $jo && $ne ? 2 : (!$jo && $ne ? 1 : 0));
        }
        else {
          $clmn[$vps][$r2].= '0'; 
        }
      }
    }
  }
  // pokus o bodovací systém
  $bodys= array(
      'A' => '3:33,32,23,13,30',
      'B' => '2:22,12,20',
      'C' => '1:11,21,10',
      'X' => '0:00'
  );
  // pokus o bodovací systém
  // 3=celá skupinka, 2=částečná, 1=nikdo, 0=obnova ještě nebyla nebo VPS nepřijela
  $note= "Písmena ABC charakterizují účast na podzimní a letní obnově, jsou určena takto:";
  $bodys= array(
      'A' => '3:33,23,13,30',
      'B' => '2:32,22,12,20',
      'C' => '1:11,21,31,10',
      'X' => '0:00'
  );
  $body= $hodn= array(); // 32 -> 'B' ... 2
  foreach ($bodys as $b=>$hvs) {
    list($h,$vs)= explode(':',$hvs);
    $note.= "<br>$b = ".strtr($vs,array(3=>'c',2=>'č',1=>'n',0=>'-',','=>' ... '));
    foreach (explode(',',$vs) as $v) {
      $body[$v]= $b;
      $hodn[$v]= $h;
    }
  }
  $note.= "<br>kde c znamená, že na obnově byla celá skupinka, č znamená částečná, "
      . "n znamená nikdo nepřijel, - znamená nepřijela VPS<br><br>"
      . "Ve sloupci 'skupinky' je jakási průměrná známka - POZOR! na účast párů často VPS nemůže mít vliv (nemoc, těhotenství,...)<br><br>";
  foreach ($clmn as $vps => $cl) {
    $n= $h= 0; 
    for ($r=$letos; $r>=$hrana2; $r--) {
      $r2= $r%100; 
      if ( isset($cl[$r2]) ) {
        $n++;
        $h+= $hodn[$clmn[$vps][$r2]];
        $clmn[$vps][$r2]= strtr($clmn[$vps][$r2],$body);
      }
    }
    if ( $n ) {
      $znamka= 4-round($h/$n,1);
      $clmn[$vps]['hodn']= $znamka;
//      if ( $znamka > 2 ) {
//        $css[$vps]['hodn']= 'yellow';
//      }
    }
  }
  return vps_table($tits,$flds,$clmn,'hodn',$export,false,$note);
}
# ------------------------------------------------------------------------------------=> . vps table
# $css_or_sort = string znamená řazení podle toho sloupce, pole znamená aplikaci css
function vps_table($tits,$flds,$clmn,$css_or_sort,$export=false,$row_numbers=false,$note='') {  
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
    // případné řazení
    if ( is_string($css_or_sort)) {
      usort($clmn, function($a, $b) use ($css_or_sort) {
        return ($a[$css_or_sort] < $b[$css_or_sort]) ? -1 : 1;
      });
    }
    $fmt= array();
    // písmena sloupců
    if ( $row_numbers ) {
      $ths.= "<th> </th>";
      for ($a= 0; $a<count($tits); $a++) {
        $id= chr(ord('A')+$a);
        $ths.= "<th>$id</th>";
      }
      $ths.= "</tr><tr>";
    }
    // titulky
    if ( $row_numbers )
      $ths.= "<th>1</th>";
    foreach ($tits as $i=>$idw) {
      list($id,$len,$f)= explode(':',$idw);
      $ths.= "<th>$id</th>";
      if ( $f ) $fmt[$flds[$i]]= $f;
    }
    foreach ($clmn as $i=>$c) {
      $c= (array)$c;
      $tab.= "<tr>";
      if ( $row_numbers )
        $tab.= "<th>".($i+2)."</th>";
      foreach ($flds as $f) {
        $class= is_array($css_or_sort) && isset($css_or_sort[$i][$f]) 
            ? "class='{$css_or_sort[$i][$f]}'" : '';
        if ( $f=='id_osoba' || $f=='^id_osoba' )
          $tab.= "<td style='text-align:right'>".tisk2_ukaz_osobu($c[$f])."</td>";
        elseif ( $f=='^id_rodina' )
          $tab.= "<td style='text-align:right'>".tisk2_ukaz_rodinu($c['^id_rodina'])."</td>";
        elseif ( $f=='^id_pobyt' )
          $tab.= "<td style='text-align:right'>".tisk2_ukaz_pobyt($c['^id_pobyt'])."</td>";
        elseif ( is_numeric($c[$f]) || $fmt[$f]=='d' ) 
          $tab.= "<td $class style='text-align:right'>{$c[$f]}</td>";
        else {
          $tab.= "<td $class style='text-align:left'>{$c[$f]}</td>";
        }
      }
      $tab.= "</tr>";
      $n++;
    }
    $ret->html= "{$note}Seznam má $n řádků<br><br><div class='stat'>
      <table class='stat'><tr>$ths</tr>$tab</table></div>";
  }
  return $ret;
}
