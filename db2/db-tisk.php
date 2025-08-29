<?php

# ---------------------------------------------------------------------------------- akce2 dary_load
# načtení darů a členkých příspěvků vztažených k pobytu
function akce2_dary_load($idp) { 
  $ret= (object)array('clenstvi'=>0,'prispevky'=>0,'darce'=>'');
  $rp= pdo_qry("
      SELECT 
        COUNT(dc.id_dar),
        0+RIGHT(SUM(DISTINCT CONCAT(d.id_dar,LPAD(d.castka,10,0))),10),
        GROUP_CONCAT(DISTINCT IF(t.role='a',CONCAT(so.prijmeni,' ',so.jmeno),'') SEPARATOR '')
      FROM pobyt AS p
      JOIN spolu AS s USING (id_pobyt)
      JOIN osoba AS o ON s.id_osoba=o.id_osoba
      JOIN osoba AS so ON so.id_osoba=s.id_osoba
      LEFT JOIN rodina AS r ON r.id_rodina=i0_rodina
      LEFT JOIN tvori AS t ON t.id_osoba=o.id_osoba AND t.id_rodina=i0_rodina
      JOIN akce AS a ON a.id_duakce=p.id_akce
      LEFT JOIN dar AS d ON d.id_osoba=s.id_osoba AND d.ukon='p' AND d.deleted=''
        AND YEAR(a.datum_do) BETWEEN YEAR(d.dat_od) AND YEAR(d.dat_do)
      LEFT JOIN dar AS dc ON dc.id_osoba=s.id_osoba AND dc.ukon='c' AND dc.deleted=''
        AND YEAR(a.datum_do)>=YEAR(dc.dat_od)
        AND (YEAR(a.datum_do) <= YEAR(dc.dat_do) OR !YEAR(dc.dat_do))
        WHERE id_pobyt=$idp
  ");
  if (!$rp) goto end;
  list($ret->clenstvi,$ret->prispevky,$ret->darce)= pdo_fetch_row($rp);
end:
  return $ret;
}
# ------------------------------------------------------------------------------------ tisk2 sestava
# generování sestav - všechny sestavy s //! vynechávají nepřítomné na akci p.funkce IN (9,10,13,14,15)
function tisk2_sestava($akce,$par,$title,$vypis,$export=false,$hnizdo=0) { debug($par,"tisk2_sestava");
  global $tisk_hnizdo;
  $tisk_hnizdo= $hnizdo;
  $cenik_verze= select('ma_cenik_verze','akce',"id_duakce=$akce"); // verze ceníku
  return 0 ? 0
     : ( $par->typ=='p'    ? tisk2_sestava_pary($akce,$par,$title,$vypis,$export)   //!
     : ( $par->typ=='P'    ? akce2_sestava_pobyt($akce,$par,$title,$vypis,$export)  //!
     : ( $par->typ=='j'    ? tisk2_sestava_lidi($akce,$par,$title,$vypis,$export)   //!
     : ( $par->typ=='vsd2' ? ( $cenik_verze==2 
                             ? akce2_strava_cv2($akce,$par,$title,$vypis,$export)
                             : akce2_strava($akce,$par,$title,$vypis,$export))  //!
     : ( $par->typ=='vsd3' ? ( $cenik_verze==2 
                             ? akce2_strava_cv2($akce,$par,$title,$vypis,$export)
                             : akce2_strava_vylet($akce,$par,$title,$vypis,$export))   //! 3.den děti oběd
     : ( $par->typ=='vv'   ? tisk2_text_vyroci($akce,$par,$title,$vypis,$export)    //!
     : ( $par->typ=='vi'   ? akce2_text_prehled($akce,$title)                       //!
     : ( $par->typ=='ve'   ? ( $cenik_verze==2 
                             ? akce2_text_eko_cv2($akce,$par,$title,$vypis,$export)       //!
                             : akce2_text_eko($akce,$par,$title,$vypis,$export))
     : ( $par->typ=='vn'   ? ( $cenik_verze==2 
                             ? akce2_sestava_cenik_cv2($akce,$par,$title,$vypis,$export)
                             : akce2_sestava_noci($akce,$par,$title,$vypis,$export))   //!
     : ( $par->typ=='vc'   ? akce2_sestava_cenik_cv2($akce,$par,$title,$vypis,$export)   //!
     : ( $par->typ=='vp'   ? ( $cenik_verze==2 
                             ? akce2_vyuctov_pary_cv2($akce,$par,$title,$vypis,$export)
                             : akce2_vyuctov_pary($akce,$par,$title,$vypis,$export))   //!
     : ( $par->typ=='vp2'  ? akce2_vyuctov_pary2($akce,$par,$title,$vypis,$export)  //!
     : ( $par->typ=='vj'   ? akce2_stravenky($akce,$par,$title,$vypis,$export,$hnizdo)      //!
     : ( $par->typ=='vjp'  ? akce2_stravenky($akce,$par,$title,$vypis,$export,$hnizdo)      //!
     : ( $par->typ=='d'    ? akce2_sestava_pecouni($akce,$par,$title,$vypis,$export)//!
     : ( $par->typ=='ss'   ? tisk2_pdf_plachta($akce,$export,$hnizdo)                       //!
     : ( $par->typ=='s0'   ? tisk2_pdf_plachta0($export)                            // pomocné štítky
     : ( $par->typ=='skpl' ? akce2_plachta($akce,$par,$title,$vypis,$export,$hnizdo)//!
     : ( $par->typ=='skpr' ? akce2_skup_hist($akce,$par,$title,$vypis,$export)      //!
     : ( $par->typ=='skpopo'?akce2_skup_popo($akce,$par,$title,$vypis,$export)      //!
     : ( $par->typ=='skti' ? akce2_skup_tisk($akce,$par,$title,$vypis,$export)      //!
     : ( $par->typ=='12'   ? akce2_jednou_dvakrat($akce,$par,$title,$vypis,$export) //!
     : ( $par->typ=='sd'   ? akce2_skup_deti($akce,$par,$title,$vypis,$export)      //!
     : ( $par->typ=='cz'   ? akce2_cerstve_zmeny($akce,$par,$title,$vypis,$export)  // včetně náhradníků
     : ( $par->typ=='tab'  ? akce2_tabulka($akce,$par,$title,$vypis,$export)        //! předává se i typ=tab => náhradníci
     : ( $par->typ=='mrop' ? akce2_tabulka_mrop($akce,$par,$title,$vypis,$export)   //!
     : ( $par->typ=='stat' ? akce2_tabulka_stat($akce,$par,$title,$vypis,$export)   //!
     : ( $par->typ=='dot'  ? dot_prehled($akce,$par,$title,$vypis,$export,$hnizdo)  
     : ( $par->typ=='pok'  ? akce2_pokoje($akce,$par,$title,$vypis,$export,$hnizdo) 
     : ( $par->typ=='pri'  ? akce2_prihlasky($akce,$par,$title,$vypis,$export,$hnizdo) 
     : ( $par->typ=='nut'  ? akce2_hnizda($akce,$par,$title,$vypis,$export)         
     : (object)array('html'=>"<i>Tato sestava zatím není převedena do nové verze systému,
          <a href='mailto:martin@smidek.eu'>upozorněte mě</a>, že ji už potřebujete</i>")
     )))))))))))))))))))))))))))))));
}
# ------------------------------------------------------------------------------- akce2 tabulka
# generování tabulky účastníků $akce typu LK pro přípravu hnízd
# používá se i pro návrh skupinek
function akce2_tabulka($akce,$par,$title,$vypis,$export=false) { trace();
  global $VPS;
  $map_fce= map_cis('ms_akce_funkce','zkratka');
  $res= (object)array('html'=>'...',
      'vps'=>array(),'nevps'=>array(),'novi'=>array(),'druh'=>array(),'vice'=>array(),
      'problem'=>array(),'clmn'=>array());
  $clmn= tisk2_sestava_pary($akce,$par,$title,$vypis,false,true);
  if (!$clmn) return $res;
//                                         debug($clmn,"akce2_tabulka {$clmn[1]['prijmeni']}");
  // seřazení podle příjmení
  usort($clmn,function($a,$b) { return mb_strcasecmp($a['prijmeni'],$b['prijmeni']); });
//                                         debug($clmn,"akce2_tabulka");
  // odstranění jednoznačných jmen
  $clmn[-1]['prijmeni']= $clmn[count($clmn)]['prijmeni']= ''; // zarážky
  for ($i= 0; $i<count($clmn); $i++) {
    if ( $clmn[$i-1]['prijmeni'] != $clmn[$i]['prijmeni']
      && $clmn[$i+1]['prijmeni'] != $clmn[$i]['prijmeni'] ) {
      $clmn[$i]['jmena']= '';
    }
  }
  unset($clmn[-1]); unset($clmn[count($clmn)-1]);
  // zkrácení zbylých jmen
  for ($i= 0; $i<count($clmn); $i++) {
    if ( $clmn[$i]['jmena'] ) {
      list($m,$z)= explode(' ',$clmn[$i]['jmena']);
//      $clmn[$i]['jmena']= $m[0].'+'.$z[0];
      $clmn[$i]['jmena']= mb_substr($m,0,1).'+'.mb_substr($z,0,1);
    }
  }
  // vložení do tabulky
  $tab= array();
  for ($i= 0; $i<count($clmn); $i++) {
    $ci= $clmn[$i];;
    $x= $ci['x_ms'];
    $v= $ci['_vps'];
    $f= $ci['funkce'];
    $c= $f==9 ? 6 : ($f!=0 && $f!=1 && $f!=2 ? 7
     : ($f==1 ? 0 : ($v=='(vps)'||$v=='(pps)' ? 5
     : ($x==1 ? 1 : ($x==2 ? 2 : ($x==3 ? 3 : 4))))));
    $tab[$c][]= $i;
    // definice sloupců v res
//    $ci['key_rodina']= $ci['prijmeni'];
    if ($f==1) $res->vps[]= $ci['^id_pobyt'];
    elseif ($v=='(vps)'||$v=='(pps)' || $f==5) $res->nevps[]= $ci['^id_pobyt']; 
    elseif ($x==1) $res->novi[]= $ci['^id_pobyt'];
    elseif ($x==2) $res->druh[]= $ci['^id_pobyt'];
    elseif ($x>=3) $res->vice[]= $ci['^id_pobyt'];
    elseif ($f<2) $res->problem[]= $ci['^id_pobyt'];
    $res->clmn[$ci['^id_pobyt']]= $ci;
  }
//                                            debug($res,'návrh skupinek');
  // export HTML a do Excelu
  $ids= array(
    "$VPS:22","Prvňáci:14","Druháci:14","Třeťáci:14","Víceročáci:14",
    "$VPS mimo službu:22","Náhradníci:14","Ostatní:26");
  $max_r= 0;
  for ($c= 0; $c<=7; $c++) {
    list($id)= explode(':',$ids[$c]);
    $ths.= "<th>$id (".(isset($tab[$c]) ? count($tab[$c]) : '').")</th>";
    $max_r= max($max_r,isset($tab[$c]) ? count($tab[$c]) : 0);
  }
  for ($r= 0; $r<$max_r; $r++) {
    $trs.= "<tr>";
    for ($c= 0; $c<=7; $c++) {
      if ( isset($tab[$c][$r]) ) {
        $i= $tab[$c][$r];
        $ci= $clmn[$i]; $x= $ci['x_ms']; $v= $ci['_vps']; $f= $ci['funkce']; $idr= $ci['key_rodina'];
        $style= 
            $v   ? " style='background-color:yellow'" : ''; //(
//            $f>1 ? " style='background-color:violet'" : '');
        $ucasti= $c==7 ? "($map_fce[$f])" : ($c==4 ? "($x)" : '');
        // počet služeb a rok odpočinku VPS
        $sluzby= $poprve= '';
        if ( $c==0 || $c==5 ) {
          $akt= akce2_skup_paru($idr);
          $sluzby= "({$akt->sluzba},{$akt->odpocinek})";
          $poprve= $akt->vps==0 ? '* ' : '';
        }
        $prijmeni_plus= "$poprve{$ci['prijmeni']} {$ci['jmena']} $ucasti $sluzby";
        $trs.= "<td$style>$prijmeni_plus</td>";
        $clmn[$i]['prijmeni']= $prijmeni_plus;
      }
      else {
        $trs.= "<td></td>";
      }
    }
    $trs.= "</tr>";
  }
//                                         debug($tab,"akce2_tabulka - tab");
//                                         debug($clmn,"akce2_tabulka - clmn");
//                                         debug($res,"akce2_tabulka - bez html");
  if ( $export ) {
    $rc= $rc_atr= $n= $tit= array();
    for ($c= 0; $c<=7; $c++) {
      $n[$c]= 0;
      for ($r= 0; $r<$max_r; $r++) {
        $rc[$r][$c]= '';
      }
    }
    foreach ($tab as $c => $radky) {
      foreach ($radky as $r=>$ucastnik) {
        $rc[$r][$c]= $clmn[$ucastnik]['prijmeni'];
        if ( $clmn[$ucastnik]['_vps'] )
          $rc_atr[$r][$c]= ' bcolor=ffffff77';
//        elseif ( $clmn[$ucastnik]['funkce'] > 1)
//          $rc_atr[$r][$c]= ' bcolor=ffff77ff';
        $n[$c]++;
      }
    }
    for ($c= 0; $c<=7; $c++) {
      list($id,$len)= explode(':',$ids[$c]);
      $tit[$c]= "$id ($n[$c]):$len";
    }
    $res->tits= $tit;
    $res->flds= explode(',',"0,1,2,3,4,5,6,7");
    $res->clmn= $rc;
    $res->atrs= $rc_atr;
    $res->expr= null;
//                                         debug($res,"akce2_tabulka - res");
  }
  $legenda= "VPS jsou označeny žlutě a hvězdička označuje nové; <br>v závorce je "
      . "u VPS počet služeb bez odpočinku a rok posledního odpočinku, "
      . "u víceročáků počet účastí, "
      . "u ostatních funkce na kurzu";
  $res->html= "$legenda<br><br><div class='stat'><table class='stat'><tr>$ths</tr>$trs</table></div>";
  return $res;
}
# ---------------------------------------------------------------------------- akce2 starsi_mrop_pdf
# generování skupinky MROP - pro starší
# pokud je zadáno id_pobyt jedná se o VPS a navrátí se je grp jeho skupinky (personifikace mailu)
function akce2_starsi_mrop_pdf($akce,$id_pobyt_vps=0,$tj='MROP') { trace();
  global $ezer_path_docs;
  $res= (object)array('html'=>'','err'=>'');
  if ($id_pobyt_vps) {
    $skupina= select('skupina','pobyt',"id_pobyt=$id_pobyt_vps");
    $clenove= "";
  }
  $cond= $id_pobyt_vps ? "skupina=$skupina" : 1;
  $grp= $cht= array();
  // data akce
  list($datum_od,$statistika)= select('datum_od,statistika','akce',"id_duakce=$akce");
  if ($statistika!=1) { $res->err= "tato sestava je jen pro akce typu 'Křižanov' "; goto end; }
  $rok= substr($datum_od,0,4);
  $r_fld= "id_rodina,nazev,ulice,psc,obec,stat,note,emaily,telefony,spz";
  $rg= pdo_qry("
    SELECT
      jmeno,prijmeni,skupina,pokoj,budova,funkce,p.id_pobyt,pracovni,
      -- ROUND(DATEDIFF('$datum_od',o.narozeni)/365.2425,0) AS vek,
      ROUND(IF(MONTH(o.narozeni),DATEDIFF('$datum_od',o.narozeni)/365.2425,YEAR('$datum_od')-YEAR(o.narozeni)),0) AS vek,
      IF(o.adresa,o.ulice,IFNULL(r2.ulice,r1.ulice)) AS ulice,
      IF(o.adresa,o.psc,IFNULL(r2.psc,r1.psc)) AS psc,
      IF(o.adresa,o.obec,IFNULL(r2.obec,r1.obec)) AS obec,
      IF(o.adresa,o.stat,IFNULL(r2.stat,r1.stat)) AS stat,
      TRIM(IF(o.kontakt,o.telefon,IFNULL(r2.telefony,r1.telefony))) AS telefony,
      IF(o.kontakt,o.email,IFNULL(r2.emaily,r1.emaily)) AS emaily
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
    WHERE p.id_akce=$akce AND $cond AND p.funkce IN (0,1,2)
    ORDER BY skupina,p.funkce DESC,jmeno");
  while ( $rg && ($x= pdo_fetch_object($rg)) ) {
    if ($id_pobyt_vps && $tj=='EROP') {
      $nik_missing= $x->pracovni ? '' : " ... <b>KONTAKT?</b>";
      $nik= '';
      if (!$nik_missing) {
        $m= null;
        if (preg_match('~Jméno:\s*(.+)(?:\nSymbol:\s*(.+)|)(?:\nJazyk:\s*(.+)|)~u',$x->pracovni,$m)) {
          $nik= "<i>$m[1]</i> ";
        }
      }
      $obec= $x->obec ?: 'CZ';
      if ($id_pobyt_vps==$x->id_pobyt) {
        $clenove= "Skupina $skupina, stoker $x->jmeno $nik $x->prijmeni <table>".$clenove;
      }
      else {
        $clenove.= "<tr><td>$nik_missing $x->jmeno $nik $x->prijmeni ($x->vek)</td>
          <td>$x->telefony</td><td>$x->emaily</td>
          <td>$x->psc $obec, $x->stat</td></tr>";
      }
    }
    else {
      $grp[$x->skupina][]= $x;
    }
    $chata= $x->pokoj;
    if (!isset($cht[$x->skupina])) $cht[$x->skupina]= array();
    if ($chata) {
      if (!in_array($chata,$cht[$x->skupina])) $cht[$x->skupina][]= $chata;
    }
  }
//  debug($grp,"sestava pro starší");
  if ($id_pobyt_vps) {
    $res->skupina= "$clenove</table>";
//    display($res->skupina);
    goto end;
  }
  // redakce
  $neni= array();
  $fname= "mrop_$rok-skupiny.pdf";
  $fpath= "$ezer_path_docs/$fname";
  $hname= "mrop_$rok-skupiny.html";
  $h= fopen("$ezer_path_docs/$hname",'w');
  fwrite($h,chr(0xEF).chr(0xBB).chr(0xBF)."<html lang='cs'><body>");
  $starsi= "<h3>Adresář starších</h3>";
  $res->html= 
      "Sestava skupin pro tisk a rozdání starším je <a href='docs/$fname' target='pdf'>zde</a>,
      <br>sestava pro ctrl-c/ctrl-v pro vložení do individuálního mailu starším je
      <a href='docs/$hname' target='html'>zde</a><hr>";
  tc_html_open('L');
  $pata= "<i>iniciace $rok</i>";
  foreach ($grp as $g) {
    $g0= $g[0];
    $skupina= $g0->skupina;
    $page= "<h3>Skupina $skupina".($cht[$skupina] ? " má chatky ".implode(', ',$cht[$skupina]):'')." </h3>
      <table style=\"width:29cm\">";
    fwrite($h,"<h3>Skupina $skupina</h3>");
    foreach ($g as $o) {
      if (!$skupina) { $neni[]= "$o->prijmeni $o->jmeno"; }
//      $chata= $o->pokoj ?: '';
      $chata= $o->budova ? "$o->budova $o->pokoj" : ($o->pokoj ?: '');
      $fill= '&nbsp;&nbsp;';
      $stat= $o->stat=='CZ' ? '' : ", $o->stat";
      if ($tj=='EROP') {
        $nik_missing= $o->pracovni ? '' : " ... <b>KONTAKT?</b>";
        $nik= '';
        $jazyk= '';
        if (!$nik_missing) {
          $m= null;
          if (preg_match('~Jméno:\s*(.+)(?:\nSymbol:\s*(.+)|)(?:\nJazyk:\s*(.+)|)~u',$o->pracovni,$m)) {
            $nik= "<i>$m[1]</i> ";
            if ($m[3]) {
              $jazyk.= preg_match('~ang~iu',$m[3]) ? 'A' : '';
              $jazyk.= preg_match('~něm~iu',$m[3]) ? 'N' : '';
              if (!$jazyk) $res->err.= "POZOR - $o->jmeno $o->prijmeni zná divný jazyk<br>";
              else $jazyk= ", $jazyk";
            }
          }
          else {
            $res->err.= "POZOR - $o->jmeno $o->prijmeni má chybně zapsané jméno a symbol<br>";
          }
        }
      }
      $jmeno= $o->funkce 
          ? "<td align=\"right\"><big><b>$o->jmeno</b></big> $nik$o->prijmeni ($o->vek)</td>" 
          : "<td><big><b>$o->jmeno</b></big> $nik$o->prijmeni ($o->vek$jazyk) $nik_missing</td>";
      $page.= "<tr>
          <td width=\"60\">$chata</td>
          $jmeno
          <td>$fill$o->telefony</td>
          <td>$o->emaily$fill</td>
          <td>$o->psc $o->obec $stat<br></td>
        </tr>";
      $adresa= "$o->jmeno $o->prijmeni, $o->telefony, $o->emaily, $o->psc $o->obec $stat<br>";
      fwrite($h,$adresa);
      if ($o->funkce) $starsi.= $adresa;
    }
    $page.= "</table>";
    tc_html_write($page,$pata);
    $res->html.= $page;
  }
  // hlášení neumístěných sojka>11, 
  if (count($neni)) {
//    debug($neni,"sirotci");
    $res->err.= "POZOR - tito chlapi nejsou ve skupině: ".implode(',',$neni);
  }
  // tisk
  tc_html_close($fpath);
  fwrite($h,"$starsi</body></html>");
  fclose($h);
end:  
  return $res;
}
# ------------------------------------------------------------------------------- akce2 tabulka_mrop
# generování tabulky účastníků $akce typu MROP - rozpis chatek
function akce2_tabulka_mrop($akce,$par,$title,$vypis,$export=false) { debug($par,'akce2_tabulka_mrop');
  $tit= $par->tit;
  $fld= $par->fld;
  $cnd= $par->cnd;
  $grp= isset($par->grp) ? $par->grp : '';
  $ord= isset($par->ord) ? $par->ord : '';
  $n= 0;
  // dekódování parametrů
  $tits= explode(',',$tit);
  $flds= explode(',',$fld);
  $clmn= array();
  // pokud je grp sdruž podle chatek
  $GROUP= $grp ? "GROUP BY $grp" : 'GROUP BY id_pobyt';
  $GROUP_CONCAT= $grp ? "GROUP_CONCAT(CONCAT(prijmeni,' ',jmeno) ORDER BY prijmeni) AS jmena," : '';
  // data akce
  $qry=  "
    SELECT $GROUP_CONCAT       
      CONCAT(prijmeni,' ',jmeno) AS pr_jm,jmeno,prijmeni,
      skupina,pokoj,IFNULL(SUM(u_castka),0) AS platba,
      p.poznamka,p.pracovni,o.email,
       IFNULL(SUBSTR((SELECT MIN(CONCAT(role,spz))
        FROM tvori AS ot JOIN rodina AS r USING (id_rodina) WHERE ot.id_osoba=o.id_osoba
      ),2),'') AS spz,'' AS filler
    FROM pobyt AS p
      JOIN spolu AS s USING (id_pobyt)
      JOIN osoba AS o USING (id_osoba)
      LEFT JOIN uhrada AS u USING (id_pobyt)
    WHERE p.id_akce=$akce AND $cnd AND p.funkce NOT IN (9,10,13,14,15)
    $GROUP
    ORDER BY $ord";
  $res= pdo_qry($qry);
  while ( $res && ($x= pdo_fetch_object($res)) ) {
    $n++;
    $clmn[$n]= array();
    foreach($flds as $f) {
      $clmn[$n][$f]= $x->$f;
    }
  }
//                                         debug($clmn,"sestava pro $akce,$typ,$fld,$cnd");
  return tisk2_table($tits,$flds,$clmn,$export);
}
# ------------------------------------------------------------------------------- tisk2 sestava_pary
# generování sestavy pro účastníky $akce - rodiny následně parametrizovaná pomocí položek $par
#   fld         seznam položek s prefixem
#   rodiny=0    pokud 1 pak pouze páry s dětmi
#   jen_deti=0  pokud 1 budou zahrnuty výsledky jen pro děti (bez os. pečovatele) tj. s_role=2,4
#   cnd         podmínka která bude doplněna vyřazením nepřítomných na akci (9,10,13,14,15)
#     tab=1       doplní cnd o náhradníky (10,13,14)
#   _cnd        podmínka, která bude použita bez úpravy, pouze doplněna o akci
#   vek         pokud je vek=a-b budou vybrání členi s a<=vek<b 
# pokud má akce ceník verze=2 bude modifikováno par.fld a par.tit
#   v par.fld místo luzka bude luzka,spacaky,zem; položky pristylky,kocarek budou vynechány
#   v par.tit bude zkopírován popis pro luzka jak spacáky,na zemi se stejnou délkou
#
function tisk2_sestava_pary($akce,$par,$title,$vypis,$export=false,$internal=false) { trace();
  global $EZER, $tisk_hnizdo;
  // údaje o akci
  list($hnizda,$org,$cv2,$noci,$oddo)= 
      select('hnizda,access,ma_cenik_verze,DATEDIFF(datum_do,datum_od),strava_oddo',
          'akce',"id_duakce=$akce");
  // spočítej normální počet stravu a ubytování
  $nj_def= [0,$noci,$oddo[0]=='o' ? $noci+1 : $noci,$noci]; // počet S,O,V
  $hnizda= $hnizda ? explode(',',$hnizda) : null;
  $jen_deti= $par->jen_deti??0;
  $par_note= $par->note??'';
  $cv2= $cv2==2 ? 1 : 0; 
  // $cv2 vyvolá výpočty podle ceníku verze 2
  // zúčastněné proměnné mají prefix $cv2_
  $cv2_prepocitat_luzka= 0;
  $cv2_prepocitat_stravu= 0;
  if ($cv2) {
    // uprav počítané položky
    $fld= explode(',',$par->fld);
    $tit= explode(',',$par->tit);
    // za lůžka přidáme spacáky a nazemi
    $iluzko= array_search('luzka',$fld);
    $cv2_prepocitat_luzka= $iluzko===false ? 0 : 1;
    $ispacaky= array_search('spacaky',$fld);
    if ($iluzko!==false && $ispacaky===false) {
      list(,$w)= explode(':',$tit[$iluzko]);
      array_splice($fld,$iluzko+1,0,['spacaky','nazemi']);
      array_splice($tit,$iluzko+1,0,["spacáky:$w:r","na zemi:$w:r"]);
    }
    // vynecháme položky pristylky kocarek a případně také hnizdo
    $vynechat= ['pristylky','kocarek'];
    if (!$hnizda) $vynechat[]= 'hnizdo';
    foreach ($vynechat as $f) {
      $vynech= array_search($f,$fld);
      if ($vynech!==false) {
        unset($fld[$vynech]);
        unset($tit[$vynech]);
      }
    }
    $par->fld= implode(',',$fld);    
    $par->tit= implode(',',$tit);    
    // pokud je v položkách nějaká strava
    $cv2_prepocitat_stravu= strpos($par->fld,'strava')===false ? 0 : 1;
//    /**/ $cv2_prepocitat_stravu= 1;
  }
  // ofsety v atributech členů pobytu
  global $i_osoba_jmeno, $i_osoba_vek, $i_osoba_role, $i_osoba_prijmeni, $i_adresa, 
      $i_osoba_kontakt, $i_osoba_telefon, $i_osoba_email, $i_osoba_note, $i_key_spolu, $i_spolu_role,
      $i_spolu_note, $i_osoba_obcanka, $i_spolu_dite_kat, $i_osoba_dieta, $i_spolu_cenik2;
  $tit= isset($par->tit) ? $par->tit : '';
  $fld= $par->fld;
  $cnd= $par->cnd ? "id_akce=$akce AND ($par->cnd)" : 1;
  if ( $tisk_hnizdo ) $cnd.= " AND hnizdo=$tisk_hnizdo ";
//  $hav= isset($par->hav) ? "HAVING {$par->hav}" : '';  ucast2_browse_ask to neumí
  $ord= isset($par->ord) ? $par->ord : "a _nazev";
  $fil= isset($par->filtr) ? $par->filtr : null;
  $par_vek= isset($par->vek) ? explode('-',$par->vek) : null;
  $n= 0;
  // číselníky
  $c_ubytovani= map_cis('ms_akce_ubytovan','zkratka');  $c_ubytovani[0]= '?';
  $c_funkce= map_cis('ms_akce_funkce','zkratka');  $c_funkce[0]= '';
  $c_prednasi= map_cis('ms_akce_prednasi','zkratka');  $c_prednasi[0]= '';
  $c_dite_kat= $org==2
      ? map_cis('fa_akce_dite_kat','zkratka') 
      : map_cis('ys_akce_dite_kat','zkratka');  
  $c_akce_dieta= map_cis('ms_akce_dieta','zkratka');  
  // dekódování parametrů
  $tits= explode(',',$tit);
  $flds= explode(',',$fld);
  // získání dat - podle $kdo
  $clmn= array();
  # diskuse souběhu: 0=normální akce, 1=hlavní akce, 2=souběžná akce
  list($hlavni,$soubezna)= select("a.id_hlavni,IFNULL(s.id_duakce,0)",
      "akce AS a LEFT JOIN akce AS s ON s.id_hlavni=a.id_duakce",
      "a.id_duakce=$akce");
  $soubeh= $soubezna ? 1 : ( $hlavni ? 2 : 0);
  $browse_par= (object)array(
    'cmd'=>'browse_load',
    'cond'=>$par->_cnd ? "$par->_cnd AND p.id_akce=$akce" 
      : "$cnd AND p.id_akce=$akce AND p.funkce NOT IN "
        . ($par->typ=='tab' ? "(10,13,14)" : "(9,10,13,14,15)")
//        . " AND p.id_pobyt IN (69619,69665)"
      ,
//    'having'=>$hav,  -- ucast2_browse_ask to neumí
    'order'=>$ord,
    'sql'=>"SET @akce:=$akce,@soubeh:=$soubeh,@app:='{$EZER->options->root}';");
  $y= ucast2_browse_ask($browse_par,true);
//  /**/                                                   debug($y);
  # rozbor výsledku browse/ask po pobytech
  array_shift($y->values);
//  $limit= 1; $offset= 19; $irec= 0; // limit -1 => vše
//  $limit= -1; $offset= 0; $irec= 0; // limit -1 => vše
//  /**/                                                   debug($y->values[$offset]);
  foreach ($y->values as $x) {
//    $irec++;
//    if ($irec<=$offset) continue;
//    if (!$limit--) break;
    // počáteční hodnoty pro ceník verze 2
    $cv2_strava= []; // dny -> pobyt -> S|O|V -> C|P -> dieta -> počet
    $cv2_vyjimka= 0; $cv2_diety= '';
    $cv2_strava_sum= ['C'=>[],'P'=>[]]; // C|P -> dieta -> počet
    $cv2_strava_dny= []; // den -> C|P -> dieta -> počet
    $cv2_noci= 0; $cv2_luzka= 0; $cv2_spacaky= 0; $cv2_nazemi= 0;
    // aplikace neosobních filtrů
    if ( $fil && $fil->r_umi ) {
      $umi= explode(',',$x->r_umi);
      if ( !in_array($fil->r_umi,$umi) ) continue;
    }
    // pokračování, pokud záznam vyhověl filtrům
    # rozbor osobních údajů: adresa nebo základní kontakt se získá 3 způsoby
    # 1. první osoba má osobní údaje - ty se použijí
    # 2. první osoba má rodinné údaje, které se shodují s i0_rodina - použijí se ty z i0_rodina
    # 3. první osoba má rodinné údaje, které se neshodují s i0_rodina - použijí se tedy její
    $telefony= $emaily= array();
    if ( $x->r_telefony ) $telefony[]= trim($x->r_telefony,",; ");
    if ( $x->r_emaily )   $emaily[]=   trim($x->r_emaily,",; ");
    # rozšířené spojení se získá slepením údajů všech účastníků
    $xs= explode('≈',$x->r_cleni);
//    /**/                                                 debug($xs,"členi pobytu");
    $pocet= 0;
    $spolu_note= "";
    $osoba_note= "";
    $cleni= array(); // změna indexu z jmeno na id_spolu
    $deti= array();
    $rodice= array();
    $vek_deti= array();
//                                                         if ( $x->key_pobyt==32146 ) debug($x);
    foreach ($xs as $i=>$xi) {
//    /**/                                                 if ($i!=0) continue; 
      $o= explode('~',$xi);
//      display("{$o[$i_osoba_jmeno]} - {$o[$i_spolu_role]}");
      if ($jen_deti && !in_array($o[$i_spolu_role],[2,4])) continue;
//    /**/                                                 debug($o,"člen pobytu č.$i");
//                                                         if ( $x->key_pobyt==32146 ) debug($o,"xi/$i");
      if ( $o[$i_key_spolu] ) {
        $o_vek= $o[$i_osoba_vek];
//        display("$par_vek[0]<=$o_vek && $o_vek<$par_vek[1] = ".($par_vek[0]<=$o_vek && $o_vek<$par_vek[1]?1:0));
        if ($par_vek && !($par_vek[0]<=$o_vek && $o_vek<$par_vek[1])) continue;
        $pocet++;
        $jmeno= str_replace(' ','-',$o[$i_osoba_jmeno]);
        if ( $o[$i_spolu_note] ) $spolu_note.= " + $jmeno:$o[$i_spolu_note]";
        if ( $o[$i_osoba_note] ) $osoba_note.= " + $jmeno:$o[$i_osoba_note]";
        if ( $o[$i_osoba_kontakt] && $o[$i_osoba_telefon] )
          $telefony[]= trim($o[$i_osoba_telefon],",; ");
        if ( $o[$i_osoba_kontakt] && $o[$i_osoba_email] )
          $emaily[]= trim($o[$i_osoba_email],",; ");
        if ( $x->key_rodina ) {
//          $cleni[$o[$i_osoba_jmeno]]['dieta']= $c_akce_dieta[$o[$i_osoba_dieta]]; 
          $cleni[$o[$i_key_spolu]]['dieta']= $c_akce_dieta[$o[$i_osoba_dieta]]; 
          $cleni[$o[$i_key_spolu]]['jmeno']= $o[$i_osoba_jmeno]; 
          if ( $o[$i_osoba_role]=='a' || $o[$i_osoba_role]=='b' ) {
            $rodice[$o[$i_osoba_role]]['jmeno']= trim($o[$i_osoba_jmeno]);
            $rodice[$o[$i_osoba_role]]['prijmeni']= trim($o[$i_osoba_prijmeni]);
            $rodice[$o[$i_osoba_role]]['telefon']= trim($o[$i_osoba_telefon],",; ");
            $rodice[$o[$i_osoba_role]]['obcanka']= trim($o[$i_osoba_obcanka]);
          }
          if ( $o[$i_osoba_role]=='d' ) {
            $vek_deti[]= $o_vek;
            $deti[$i]['jmeno']= $o[$i_osoba_jmeno];
            $deti[$i]['vek']= $o_vek;
            $deti[$i]['kat']= $c_dite_kat[$o[$i_spolu_dite_kat]]; 
          }
        }
        else {
            $rodice['a']['jmeno']= trim($o[$i_osoba_jmeno]);
            $rodice['a']['prijmeni']= trim($o[$i_osoba_prijmeni]);
        }
//      }
        // výpočet ubytování pro ceník verze 2
        if ($cv2_prepocitat_luzka) {
          // přepočítej noci a stravy - $i_spolu_cenik2 -> kat_nocleh, kat_dny, kat_porce, kat_dieta
          $kat_n= $o[$i_spolu_cenik2+0]; // nocleh
          $dny=   $o[$i_spolu_cenik2+1]; // ... dny 
          $nn= 0;
          for ($d= 0; $d<strlen($dny); $d+=4) {
            $nn+= $dny[$d];
          }
          $cv2_noci= max($nn,$cv2_noci);
          switch ($kat_n) {
            case 'L': $cv2_luzka+= $nn ? 1 : 0; break;
            case 'S': $cv2_spacaky+= $nn ? 1 : 0; break;
            case 'Z': $cv2_nazemi+= $nn ? 1 : 0; break;
          }
        }
        // výpočet stravy pro ceník verze 2
        if ($cv2_prepocitat_stravu) {
          // přepočítej stravy - $i_spolu_cenik2 -> kat_nocleh, kat_dny, kat_porce, kat_dieta
          $dny=   $o[$i_spolu_cenik2+1]; // ... dny 
          $kat_p= $o[$i_spolu_cenik2+2]; // porce
          $kat_d= $o[$i_spolu_cenik2+3]; // dieta
          $nj= [0,0,0]; // počet S,O,V
          $ji= 0;
          for ($d= 0; $d<strlen($dny); $d+=4) {
            for ($j=1; $j<=3; $j++) {
              if ($dny[$d+$j]) {
                $nj[$j]++;
                // zapiš do tabulky strav dny -> pobyt -> S|O|V -> C|P -> dieta -> počet
                $cv2_strava[$d/4][$j][$kat_p][$kat_d]++;
                $cv2_strava_dny[$d/4][$j][$kat_p][$kat_d]++;
                $ji= 1;
              }
            }
          }
          $cv2_strava_sum[$kat_p][$kat_d]+= $ji;
          // detekuj výjimky
          for ($j=1; $j<=3; $j++) {
            if (/*$nj[$j] &&*/ $nj[$j]!=$nj_def[$j]) $cv2_vyjimka= 1;
//            display("if ($nj[$j] && $nj[$j]!=$nj_def[$j]) $cv2_vyjimka= 1");
          }
        }
      }
    }
//    /**/                                                 debug($cv2_strava,"STRAVA $cv2_prepocitat_stravu");
//    /**/                                                 debug($rodice,"RODIČE");
//    /**/                                                 debug($deti,"DĚTI");
//    /**/                                                 debug($cleni,"ČLENI");
    $o= explode('~',$xs[0]);
//    /**/                                                 debug($o,"člen pobytu č.0");
    // show: adresa, ulice, psc, obec, stat, kontakt, telefon, nomail, email
    $io= $i_adresa;
    $adresa=  $o[$io++]; $ulice= $o[$io++]; $psc= $o[$io++]; $obec= $o[$io++]; $stat= $o[$io++];
    $kontakt= $o[$io++]; $telefon= $o[$io++]; $nomail= $o[$io++]; $email= $o[$io++];
    // úpravy
    $emaily= count($emaily) ? implode(', ',$emaily).';' : '';
    $email=  trim($kontakt ? $email   : mb_substr($email,1),",; ") ?: $emaily;
    $emaily= $emaily ?: $email;
    $telefony= count($telefony) ? implode(', ',$telefony).';' : '';
    $telefon=  trim($kontakt ? $telefon : mb_substr($telefon,1),",; ") ?: $telefony;
    $telefony= $telefony ?: $telefon;
//                                                         if ( $x->key_pobyt==22141 )
//                                                         display("email=$email, emaily=$emaily, telefon=$telefon, telefony=$telefony");
    // pokud je omezení na rodiny s dětmi
    if ( $par->rodiny && $pocet<=2 ) {
      continue;
    }  
    // přepsání do výstupního pole
    $n++;
    $clmn[$n]= array();
//    $r= 1; // 1 ukáže bez (r)
    foreach($flds as $f) {          // _pocet,poznamka,note
      $c= '';
      switch ($f) {
      case '^id_pobyt': $c= $x->key_pobyt; break;
      case 'hnizdo':    $c= $hnizda ? ($x->hnizdo ? $hnizda[$x->hnizdo-1] : '?') : '-'; break;
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
      case 'rodice_':   $c= count($rodice)==2 && strpos($x->_nazev,'-')
                          ? "{$rodice['a']['prijmeni']} {$rodice['a']['jmeno']}
                             {$rodice['b']['prijmeni']} {$rodice['b']['jmeno']}" : (
        count($rodice)==2 ? "{$x->_nazev} {$rodice['a']['jmeno']} {$rodice['b']['jmeno']}" : (
             $rodice['a'] ? "{$rodice['a']['prijmeni']} {$rodice['a']['jmeno']}" : (
             $rodice['b'] ? "{$rodice['b']['prijmeni']} {$rodice['b']['jmeno']}"
                          : $x->_nazev )));
                        break;
      case 'jmena2':    $c= explode(' ',$x->_jmena);
                        $c= $c[0].' '.$c[1]; break;
      case 'vek_deti':  $c= ".  ".implode(', ',$vek_deti); break;
      case 'ulice':     $c= $adresa  ? $ulice   : str_replace('®','',$ulice); break;
      case 'psc':       $c= $adresa  ? $psc     : str_replace('®','',$psc); break;
      case 'obec':      $c= $adresa  ? $obec    : str_replace('®','',$obec); break;
      case 'stat':      $c= $adresa  ? $stat    : str_replace('®','',$stat);
                        if ( $c=='CZ' ) $c= '';
                        break;
      case 'telefon':   $c= $telefon;  break;
      case 'telefony':  $c= $telefony; break;
      case '*telefony': foreach($rodice as $X) {
                          if (!$X['telefon']) continue;
                          $c.= "{$X['jmeno']}:{$X['telefon']} ";
                        }
                        break;
      case '*obcanky':  foreach($rodice as $X) {
                          if (!$X['obcanka']) continue;
                          $c.= "{$X['jmeno']}:{$X['obcanka']} ";
                        }
                        break;
      case '*deti':     foreach($deti as $X) {
                          $c.= "{$X['jmeno']}:{$X['vek']}:{$X['kat']} ";
                        }
                        break;
      case '*diety':    foreach($cleni as $X) { 
                          if ($X['dieta']=='-') continue;
                          $c.= "{$X['jmeno']}:{$X['dieta']} ";
                        }
                        break;
      case 'email':     $c= $email;  break;
      case 'emaily':    $c= $emaily; break;
      case '_pocet':    $c= $pocet; break;
      case 'poznamka':  $c= $x->p_poznamka . ($spolu_note ?: ''); break;
      case 'note':      $c= $x->r_note . ($osoba_note ?: ''); break;
      case 'pok1':      list($c)= explode(',',$x->pokoj); $c= trim($c); break;
      case 'pok2':      list($_,$c)= explode(',',$x->pokoj); $c= trim($c); break;
      case 'pok3':      list($_,$_,$c)= explode(',',$x->pokoj); $c= trim($c); break;
      case '_diety':    $c=  $x->strava_cel_bm!=0  || $x->strava_pol_bm!=0 ? '_bm' : '';
                        $c.= $x->strava_cel_bl!=0  || $x->strava_pol_bl!=0 ? '_bl' : ''; break;
      case '_vyjimky':  $c= $cv2 ? $cv2_vyjimka
                        : ( $x->cstrava_cel!=''    || $x->cstrava_pol!=''
                         || $x->cstrava_cel_bm!='' || $x->cstrava_pol_bm!=''
                         || $x->cstrava_cel_bl!='' || $x->cstrava_pol_bl!='' ? 1 : 0); break;
      case '_vps':      $VPS_= $org==1 ? 'VPS' : 'PPS'; $vps_= $org==1 ? '(vps)' : '(pps)';
                        $c= $x->funkce==1 ? $VPS_ : (strpos($x->r_umi,'1')!==false ? $vps_ : ''); break;
      case '_funkce':   $c= $c_funkce[$x->funkce]; break;
      case '_prednasi': $c= $c_prednasi[$x->prednasi]; break;
      // pro ceník verze 2
      case 'noci':      $c= $cv2 ? $cv2_noci : $x->noci;  break;
      case 'luzka':     $c= $cv2 ? $cv2_luzka : $x->luzka;  break;
      case 'spacaky':   $c= $cv2 ? $cv2_spacaky : 0;  break;
      case 'nazemi':    $c= $cv2 ? $cv2_nazemi : 0;  break;
      case '*strava':   $c= $cv2 ? $cv2_strava : null;  break;
      case 'strava_sum':    $c= $cv2 ? $cv2_strava_sum : null;  break;
      case 'strava_dny':    $c= $cv2 ? $cv2_strava_dny : null;  break;
      case 'strava_cel':    $c= $cv2 ? $cv2_strava_sum['C']['-'] : $x->strava_cel;  break;
      case 'strava_pol':    $c= $cv2 ? $cv2_strava_sum['P']['-'] : $x->strava_pol;  break;
      case 'strava_cel_bm': $c= $cv2 ? $cv2_strava_sum['C']['BM'] : $x->strava_cel_bm;  break;
      case 'strava_pol_bm': $c= $cv2 ? $cv2_strava_sum['P']['BM'] : $x->strava_pol_bm;  break;
      case 'strava_cel_bl': $c= $cv2 ? $cv2_strava_sum['C']['BL'] : $x->strava_cel_bl;  break;
      case 'strava_pol_bl': $c= $cv2 ? $cv2_strava_sum['P']['BL'] : $x->strava_pol_bl;  break;
      // pro vyúčtování
      case '#deti':     $c= count($deti);  break;
      // hodnoty z tabulek
      default:          $c= $x->$f; break;
      }
      $clmn[$n][$f]= $c;
    }
  }
//                                         debug($clmn,"sestava pro $akce,$typ,$fld,$cnd");
  $res= $internal ? $clmn : tisk2_table($tits,$flds,$clmn,$export);
  if ($par_note) $res->html= "$par_note {$res->html}";
  return $res;
}
# -------------------------------------------------------------------------------- tisk_sestava_lidi
# generování sestavy pro účastníky $akce - jednotlivce ... jen skutečně na akci
#   $fld = seznam položek s prefixem
#   $cnd = podmínka
#   $par->sel = seznam id_pobyt
#   $par->subtyp = pro EROP se dopčítávají sloupce účast na MS a účast na mužské akci
# pokud má akce ceník verze 2 lze užít další parametry
#   $par->bydli = pokud je na akci ubytován
#
function tisk2_sestava_lidi($akce,$par,$title,$vypis,$export=false) { trace();
  global $tisk_hnizdo;
  $subtyp= isset($par->subtyp) ? $par->subtyp : '';
  $tit= $par->tit;
  $fld= $par->fld;
  $cnd= $par->cnd;
  $note= $par->note??'';
  $cv2= 0;
  if ( $tisk_hnizdo ) $cnd.= " AND IF(funkce=99,s_hnizdo=$tisk_hnizdo,hnizdo=$tisk_hnizdo)";
  $hav= isset($par->hav) ? "HAVING {$par->hav}" : '';
  $ord= isset($par->ord) ? $par->ord : "IF(funkce<=2,1,funkce),o.prijmeni,o.jmeno";
  $html= '';
  $n= 0;
  // dekódování parametrů
  $tits= explode(',',$tit);
  $flds= explode(',',$fld);
  // číselníky
  $cirkev= map_cis('ms_akce_cirkev','zkratka');  $cirkev[0]= '';
  $vzdelani= map_cis('ms_akce_vzdelani','zkratka');  $vzdelani[0]= '';
  $funkce= map_cis('ms_akce_funkce','zkratka');  $funkce[0]= '';
  $pfunkce= map_cis('ms_akce_pfunkce','zkratka');  $pfunkce[0]= '?';
  $dieta= map_cis('ms_akce_dieta','zkratka');  $dieta[0]= '';         // neplatí pro ceník verze 2
  $dite_kat= xx_akce_dite_kat($akce);
  $dite_kat= map_cis($dite_kat,'zkratka');  $dite_kat[0]= '?';
  $s_role= map_cis('ms_akce_s_role','zkratka');  $s_role[0]= '?';
  // načtení ceníku pro dite_kat, pokud se chce _poplatek
  if ( strpos($fld,"_poplatek") ) {
    $soubezna= select("id_duakce","akce","id_hlavni=$akce");
    $cenik= null;
    if ($soubezna) akce2_nacti_cenik($soubezna,$tisk_hnizdo,$cenik,$html);
  }
  // získání dat - podle $kdo
  $clmn= array();
  // případné zvláštní řazení
  switch ($ord) {
  case '_zprava':
    $ord= "CASE WHEN _vek<6 THEN 1 WHEN _vek<18 THEN 2 WHEN _vek<26 THEN 3 ELSE 9 END,prijmeni";
    break;
  }
  // případné omezení podle selected na seznam pobytů
  if ( isset($par->sel) && $par->sel ) {
//                                                 display("i.par.sel=$par->sel");
    $cnd.= $par->selected ? " AND p.id_pobyt IN ($par->selected)" : ' AND 0';
  }
  // data akce
  $r_fld= "id_rodina,nazev,ulice,psc,obec,stat,note,emaily,telefony,spz";
  $qry=  "
    SELECT
      p.pouze,p.poznamka,p.pracovni,/*p.platba - není atribut osoby!,*/p.funkce,p.skupina,p.pokoj,p.budova,s.s_role,
      o.id_osoba,o.prijmeni,o.jmeno,o.narozeni,o.rc_xxxx,o.note,o.prislusnost,o.obcanka,o.clen,
      o.dieta,s.kat_dieta,s.kat_dny,p.luzka,a.ma_cenik,a.ma_cenik_verze,
      IFNULL(r2.id_rodina,r1.id_rodina) AS id_rodina, r3.role AS p_role,
      IFNULL(r2.nazev,r1.nazev) AS r_nazev,
      IFNULL(r2.spz,r1.spz) AS r_spz,
      IF(o.adresa,o.ulice,IFNULL(r2.ulice,r1.ulice)) AS ulice,
      IF(o.adresa,o.psc,IFNULL(r2.psc,r1.psc)) AS psc,
      IF(o.adresa,o.obec,IFNULL(r2.obec,r1.obec)) AS obec,
      IF(o.adresa,o.stat,IFNULL(r2.stat,r1.stat)) AS stat,
      IF(o.kontakt,o.telefon,IFNULL(r2.telefony,r1.telefony)) AS telefony,
      IF(o.kontakt,o.email,IFNULL(r2.emaily,r1.emaily)) AS emaily,
      s.poznamka AS s_note,s.pfunkce,s.dite_kat,s.skupinka,
      IFNULL(r2.note,r1.note) AS r_note,
      IFNULL(r2.role,r1.role) AS r_role,
      IF(MONTH(o.narozeni),TIMESTAMPDIFF(YEAR,o.narozeni,a.datum_od),YEAR(a.datum_od)-YEAR(o.narozeni)) AS _vek,
      IF(MONTH(o.narozeni),ROUND(DATEDIFF(a.datum_od,o.narozeni)/365.2425,1),YEAR(a.datum_od)-YEAR(o.narozeni)) AS _vek_,
    (SELECT GROUP_CONCAT(prijmeni,' ',jmeno)
        FROM akce JOIN pobyt ON id_akce=akce.id_duakce
        JOIN spolu ON spolu.id_pobyt=pobyt.id_pobyt
        JOIN osoba ON osoba.id_osoba=spolu.id_osoba
        WHERE spolu.pecovane=o.id_osoba AND id_akce=$akce) AS _chuva,
      (SELECT CONCAT(prijmeni,' ',jmeno) FROM osoba
        WHERE s.pecovane=osoba.id_osoba) AS _chovany,
        cirkev,vzdelani,zamest,zajmy
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
      -- r3=rodina, která je na akci
      LEFT JOIN ( SELECT id_osoba,id_rodina,role
        FROM tvori JOIN rodina USING (id_rodina))
        AS r3 ON r3.id_osoba=o.id_osoba AND r3.id_rodina=p.i0_rodina
      -- akce
      JOIN akce AS a ON a.id_duakce=p.id_akce
    WHERE p.id_akce=$akce AND $cnd AND p.funkce NOT IN (9,10,13,14,15)
      GROUP BY o.id_osoba $hav
      ORDER BY $ord";
  $res= pdo_qry($qry);
  while ( $res && ($x= pdo_fetch_object($res)) ) {
    // mají se vyloučit nebydlící?
    if ($par->bydli??0) {
      if ($x->ma_cenik_verze==2) {
        // přepočítej noci 
        $xn= 0;
        for ($d= 0; $d<strlen($x->kat_dny); $d+=4) {
          $xn+= $x->kat_dny[$d];
        }
        if (!$xn) continue;
      }
      elseif ($x->ma_cenik && !$x->luzka) continue;
    }
    $n++;
    $clmn[$n]= array();
    // zapamatování verze ceníku
    if (!$cv2) $cv2= $x->ma_cenik_verze;
    // doplnění položek pro subtyp=EROP
    $historie= '';
    if ($subtyp=='EROP') {
      list($akce_ms,$akce_vps,$akce_ch,$iniciace,$firming,$cizi)= select(
          "SUM(IF(druh=1,1,0)),SUM(IF(funkce=1,1,0)),SUM(IF(statistika>1,1,0)),iniciace,firming,prislusnost",
          'osoba JOIN spolu USING (id_osoba) JOIN pobyt USING (id_pobyt) JOIN akce ON id_akce=id_duakce',
          "id_osoba={$x->id_osoba} AND zruseno=0 AND datum_od<'2023-09-01' ");
      $historie= '';
      if (!$cizi) {
        if ($akce_ch) $historie.= " chlapi $akce_ch x";
        if ($akce_ms) $historie.= " MS $akce_ms x";
        if ($akce_vps) $historie.= ' (vps)';
        if ($firming) $historie.= " firming $firming";
        $historie.= " iniciace $iniciace";
      }
      $x->funkce= $x->funkce==1 ? 'stoker' : ($x->funkce==12 ? 'lektor' : ($x->funkce==5 ? 'hospodář' : $x->funkce==1));
    }
    // doplnění počítaných položek
    $x->narozeni_dmy= sql_date_year($x->narozeni);
    foreach($flds as $f) {
      switch ($f) {
      case '_historie':                                               // historie na akcích
        $clmn[$n][$f]= $historie;
        break;
      case '1':                                                       // 1
        $clmn[$n][$f]= 1;
        break;
      case 'prislusnost':                                             // stát.příslušnost: osoba
      case 'stat':                                                    // stát: rodina/osoba
        $clmn[$n][$f]= $x->$f ?: 'CZ';
        break;
      case 'dieta':                                                   // osoba: dieta
        $clmn[$n][$f]= $cv2 ? $x->kat_dieta : $dieta[$x->$f];
        break;
      case 'cirkev':                                                  // osoba: církev
        $clmn[$n][$f]= $cirkev[$x->$f];
        break;
      case 'vzdelani':                                                // osoba: vzdělání
        $clmn[$n][$f]= $vzdelani[$x->$f];
        break;
      case '_n_deti':                                                 // osoba: počet dětí
        $ido= $x->id_osoba;
        list($n_deti,$je_idr)= select('COUNT(*),IFNULL(rodic.id_rodina,-1)',
            'tvori AS rodic
              JOIN rodina AS r ON r.id_rodina=rodic.id_rodina
              JOIN tvori AS dite ON dite.id_rodina=r.id_rodina',
            "rodic.role IN ('a','b') AND dite.role='d' AND rodic.id_osoba=$ido");
        $clmn[$n][$f]= $je_idr==-1 ? '?' : $n_deti;
        break;
      case 'dite_kat':                                                // osoba: kategorie dítěte
        $clmn[$n][$f]= in_array($x->s_role,array(2,3,4)) 
          ? $s_role[$x->s_role].'-'.$dite_kat[$x->$f]
          : $s_role[$x->s_role];
//        $clmn[$n][$f]= $dite_kat[$x->$f];
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
        $clmn[$n][$f]= substr($nar,2,2).substr($nar,5,2).substr($nar,8,2);
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
      case '_typ':                                      // 1: dítě, pečoun  2: zbytek
        $clmn[$n][$f]= $x->funkce==99 ? 1 : (
                       $x->_vek<18 ? 1 : 2);
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
        $clmn[$n][$f]= str_replace('-','/',$x->narozeni);
        break;
      default: $clmn[$n][$f]= $x->$f;
      }
    }
  }
//                                         debug($clmn,"sestava pro $akce,$typ,$fld,$cnd");
  $result= tisk2_table($tits,$flds,$clmn,$export);
  if ($note) $result->html= "$note {$result->html}";
  return $result;
}
# ---------------------------------------------------------------------------- akce2 sestava_pecouni
# generování sestavy pro účastníky $akce - pečouny
#   $fld = seznam položek s prefixem
#   $cnd = podmínka
function akce2_sestava_pecouni($akce,$par,$title,$vypis,$export=false) { trace();
  $result= (object)array();
  $typ= $par->typ;
  $tit= $par->tit;
  $fld= $par->fld;
  $cnd= $par->cnd;
  $ord= isset($par->ord) && $par->ord ? $par->ord : "CONCAT(o.prijmeni,' ',o.jmeno)";
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
# ------------------------------------------------------------------------------ akce2 sestava_pobyt
# generování sestavy pro účastníky $akce se stejným pobytem - jen Dům 
#   $fld = seznam položek s prefixem (platba se nikde nepoužívá)
#   $cnd = podmínka
function akce2_sestava_pobyt($akce,$par,$title,$vypis,$export=false) { debug($par,'akce2_sestava_pobyt');
  $otoc= function ($s) {
    mb_internal_encoding("UTF-8");
    $s= mb_strtolower($s);
    $x= '';
    for ($i= mb_strlen($s); $i>=0; $i--) {
      $xi= mb_substr($s,$i,1);
      $xi= mb_strtoupper($xi);
      $x.= $xi;
    }
    return $x;
  };
  $result= (object)array();
  $typ= $par->typ;
  $tit= $par->tit;
  $fld= $par->fld;
  $cnd= isset($par->cnd) ? $par->cnd : 1;
  $hav= isset($par->hav) ? "HAVING {$par->hav}" : '';
  $ord= isset($par->ord) ? $par->ord : "nazev";
  $html= '';
  $href= '';
  $n= 0;
  // číselníky
  $c_ubytovani= map_cis('ms_akce_ubytovan','zkratka');  $c_ubytovani[0]= '?';
  $c_prednasi= map_cis('ms_akce_prednasi','hodnota');  $c_ubytovani[0]= '?';
//  $c_platba= map_cis('ms_akce_platba','zkratka');  $c_ubytovani[0]= '?';
  // dekódování parametrů
  $tits= explode(',',$tit);
  $flds= explode(',',$fld);
  $cond= $cnd;
  // získání dat - podle $kdo
  $clmn= array();
  $expr= array();       // pro výrazy
  // data akce
  $qry=  "SELECT id_pobyt,
            r.nazev as nazev,p.pouze as pouze,p.poznamka,
            -- p.datplatby,p.zpusobplat,
            COUNT(o.id_osoba) AS _pocet,
            SUM(IF(t.role IN ('a','b'),1,0)) AS _pocetA,
            GROUP_CONCAT(DISTINCT o.prijmeni ORDER BY t.role DESC) as _prijmeni,
            GROUP_CONCAT(IF(o.jmeno='','?',o.jmeno)    ORDER BY t.role DESC) as _jmena,
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
          LEFT JOIN tvori AS t ON t.id_osoba=o.id_osoba AND t.id_rodina=p.i0_rodina
          -- LEFT JOIN rodina AS r USING(id_rodina)
          LEFT JOIN rodina AS r ON r.id_rodina=IF(p.i0_rodina,p.i0_rodina,t.id_rodina)
          WHERE p.id_akce='$akce' AND $cnd AND p.funkce NOT IN (9,10,13,14,15)
          GROUP BY id_pobyt $hav
          ORDER BY $ord";
  $res= pdo_qry($qry);
  while ( $res && ($x= pdo_fetch_object($res)) ) {
    $x->prijmeni= $x->nazev ?: $x->_prijmeni;
    $x->jmena=    $x->_jmena;
    $x->_pocet=   $x->_pocet;
    // podle číselníku
    $x->ubytovani= $c_ubytovani[$x->ubytovani];
    $x->prednasi= $c_prednasi[$x->prednasi];
//    $x->zpusobplat= $c_platba[$x->zpusobplat];
    // ceny DS
    $cena= dum_browse_pobyt((object)['cmd'=>'suma','cond'=>"id_pobyt=$x->id_pobyt"]);
    $abbr= $cena->abbr;
    // další
    $n++;
    $clmn[$n]= array();
    foreach($flds as $f) {
      switch ($f) {
      case 'ubyt': case 'stra': case 'popl': case 'prog': 
                        $clmn[$n][$f]= $abbr[$f]; break;
      case 'celkem':    $clmn[$n][$f]= $cena->$f; break;
      case '_pocetD':   $clmn[$n][$f]= $x->_pocet - $x->_pocetA; break;
      case '=par':      $clmn[$n][$f]= "{$x->prijmeni} {$x->jmena}"; break;
      // fonty: ISOCTEUR, Tekton Pro
      case '=pozpatku': $clmn[$n][$f]= $otoc("{$x->prijmeni} {$x->jmena}"); break;
      default:          $clmn[$n][$f]= $x->$f; break;
      }
    }
  }
//                                         debug($clmn,"sestava pro $akce,$typ,$fld,$cnd");
  return tisk2_table($tits,$flds,$clmn,$export);
}
# --------------------------------------------------------------------------- _akce2 sestava_pecouni
# výpočet pro generování sestavy pro účastníky $akce - pečouny a pro statistiku
function _akce2_sestava_pecouni(&$clmn,$akce,$fld='_skoleni,_sluzba,_reflexe',$cnd=1,$ord=1) { trace();
  global $tisk_hnizdo;
  if ( $tisk_hnizdo ) $cnd.= " AND p.hnizdo=$tisk_hnizdo ";
  $flds= explode(',',$fld);
  // číselníky                 akce.druh = ms_akce_typ:pečovatelé=7,kurz=1
  $pfunkce= map_cis('ms_akce_pfunkce','zkratka');  $pfunkce[0]= '?';
  // data akce
  $rel= '';
  $rel= "-YEAR(narozeni)";
  $r_fld= "id_rodina,nazev,ulice,psc,obec,stat,note,emaily,telefony,spz";
  $n= 0;
  $qry= " SELECT o.prijmeni,o.jmeno,o.narozeni,o.rc_xxxx,
            IFNULL(r2.spz,r1.spz) AS r_spz,
            IF(o.adresa,o.ulice,IFNULL(r2.ulice,r1.ulice)) AS ulice,
            IF(o.adresa,o.psc,IFNULL(r2.psc,r1.psc)) AS psc,
            IF(o.adresa,o.obec,IFNULL(r2.obec,r1.obec)) AS obec,
            IF(o.adresa,o.stat,IFNULL(r2.stat,r1.stat)) AS stat,
            IF(o.kontakt,o.telefon,IFNULL(r2.telefony,r1.telefony)) AS telefon,
            IF(o.kontakt,o.email,IFNULL(r2.emaily,r1.emaily)) AS email,
            o.id_osoba,s.skupinka as skupinka,s.pfunkce,
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
          -- r1=rodina, kde je dítětem
          LEFT JOIN ( SELECT id_osoba,role,$r_fld
            FROM tvori JOIN rodina USING(id_rodina))
            AS r1 ON r1.id_osoba=o.id_osoba AND r1.role NOT IN ('a','b')
          -- r2=rodina, kde je rodičem
          LEFT JOIN ( SELECT id_osoba,role,$r_fld
            FROM tvori JOIN rodina USING(id_rodina))
            AS r2 ON r2.id_osoba=o.id_osoba AND r2.role IN ('a','b')
          WHERE (p.funkce=99 OR (p.funkce NOT IN (9,10,13,14,15,99) AND s.pfunkce IN (4,5,8))) 
            AND p.id_akce='$akce' AND $cnd
          GROUP BY id_osoba
          ORDER BY $ord";
  $res= pdo_qry($qry);
  while ( $res && ($x= pdo_fetch_object($res)) ) {
    $n++;
    $clmn[$n]= array();
    foreach($flds as $f) {
      // sumáře
      switch ($f) {
      case 'pfunkce':   $clmn[$n][$f]= $pfunkce[$x->$f]; break;
//      case '_pp':       $clmn[$n][$f]= $x->pfunkce==4 ? 1 : 0; break;
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
# ----------------------------------------------------- akce2 sestava_td_style
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
# ------------------------------------------------------------------------------- akce2 text_eko_cv2
function akce2_text_eko_cv2($akce,$par,$title='',$vypis='',$export=false) { trace();
  $result= (object)array();
  $html= '';
//  goto bilance;
  // -------------------------------------- program dětí & náklady pečounů
  $kc= function($x) { return number_format($x, 0, '.', ' ')."&nbsp;Kč"; };
  // zjištění příspěvků rodičů na program dětí - dotace přidá sloupce individ.slev=dotací
  $ucast= akce2_sestava_cenik_cv2($akce,(object)['druhy'=>'uspd','dotace'=>1],'','',false);
  // zjištění nákladů 
  $cena1= $ucast->cena;
  $pec= akce2_sestava_cenik_cv2($akce,(object)['druhy'=>'uspd','cnd'=>'funkce=99']);
  $cena2= $pec->cena;
//  /**/                                                    debug($ucast,"akce2_text_eko_cv2 - účastníci");
//  /**/                                                    debug($cena2,"akce2_text_eko_cv2 - pečouni");
  $tab= []; // druh -> cena
  $rc= pdo_qry("SELECT poradi,druh,t FROM cenik WHERE id_akce=$akce");
  while ($rc && (list($poradi,$druh,$t)= pdo_fetch_row($rc))) {
//    display("tab[$druh]+= cena[$poradi]");
    switch ($druh) {
      case 'x': break;
      case 'p': 
        if ($t=='D') 
          $tab[1]['P']+= $cena1[$poradi]; 
        else
          $tab[1][$druh]+= $cena1[$poradi]; 
        break;
      case 'u': 
      case 's': 
        $tab[1][$druh]+= $cena1[$poradi]; 
        $tab[2][$druh]+= $cena2[$poradi]; 
        break;
      default: 
        $tab[1][$druh]+= $cena1[$poradi]; 
    }
  }
  /**/                                                    debug($tab,"akce2_text_eko_cv2 - tab");
  // formátování odpovědi dle ceníku akce
  $p_slev_vps= $tab[1]['d']; 
  $p_prog_deti= $tab[1]['P'];
  $p_prog_deti_= $kc($p_prog_deti);
  $p_prog_pary= $tab[1]['p'];
  $p_prog_pary_= $kc($p_prog_pary);
  $vydaje= $tab[2]['u']+$tab[2]['s'];
  $ubyt_= $kc($tab[2]['u']);
  $stra_= $kc($tab[2]['s']);
  $vydaje_= $kc($vydaje);
  $html.= "<h3>Pokrytí nákladu kolektivu pečovatelů z programového příspěvku rodičů</h3>";
  $html.= "<i><b>Poznámka</b> Pokud jsou někteří pečovatelé pomocní nebo tzv. mimořádní, předpokládá se, že jejich pobyt 
    <br>je uhrazen mimo pečovatelský rozpočet.</i><br>";
  $html.= "<br><table class='stat'>";
  $html.= "<tr><th></th><th>příspěvek rodičů</th><th>náklady pečovatelů</th></tr>";
  $html.= "<tr><th>program dětí</th><td align='right'>$p_prog_deti_</td><td></td></tr>";
  $html.= "<tr><th>ubytování</th><td></td><td align='right'>$ubyt_</td></tr>";
  $html.= "<tr><th>stravování</th><td></td><td align='right'>$stra_</td></tr>";
  $html.= "<tr><th>suma</th><th align='right'>$prijmy_</th><th align='right'>$vydaje_</th></tr>";
  $html.= "</table>";
  $html.= "<p><b>Shrnutí pro pečovatele</b></p>";
  $obrat= $p_prog_deti - $vydaje;
  $obrat= $kc($obrat);
  $html.= "Účastníci přispějí na děti a pečovatele částkou $prijmy_, 
    přímé náklady na pobyt pečovatelů na akci činí $vydaje_, 
    <br>celkem <b>$obrat</b> zůstává na program dětí a roční přípravu pečovatelů.";
  // -------------------------------------- předpis & náklad & dary
  $par= (object)['fld'=>'prijmeni,key_pobyt,platba,c_suma',
//    'cnd'=>" funkce=99" // pečouni
//    'cnd'=>" id_pobyt IN (69684)" // Farářovi    - dar
//    'cnd'=>" id_pobyt IN (69220)" // Katarina    - tým
//    'cnd'=>" id_pobyt IN (69324)" // Brucknerovi - dotace
//    'cnd'=>" id_pobyt IN (69483)" // Baletkovi   - VPS
  ];
  $predpis= $naklad= [];
  $kc_predpis= $kc_naklad= 0;
  $kc_platby= $kc_dary= 0;
  $ret= tisk2_sestava_pary($akce,$par,'$title','$vypis',false,true);
  // průchod pobyty
  $n_platici= $n_platby= 0;
  foreach ($ret as $p) {
    $p= (object)$p;
    $idp= $p->key_pobyt;
    $kc_platby+= $p->platba;
    if ($p->platba) {
      $kc_dary+= $p->platba - $p->c_suma;
      $n_platby++;
    }
    if ($p->c_suma) $n_platici++;
    // projdeme ceník a přidáme položky
    $pre= akce2_vzorec2_pobyt($idp,0,(object)['funkce_slevy'=>1]);
    $nak= akce2_vzorec2_pobyt($idp,0,(object)['funkce_slevy'=>0]);
    foreach (['u','s','p','d'] as $x) {
      $kx= $pre->rozpis[$x];
      $predpis[$x]+= $kx;
      $kc_predpis+= $kx;
      $kx= $nak->rozpis[$x];
      $naklad[$x]+= $kx;
      $kc_naklad+= $kx;
    }
  }
  debug($predpis,"předpis = $kc_predpis");  // d = dotace + slevy podle ceníku tzn. VPS
  debug($naklad,"náklad = $kc_naklad");     // d = slevy podle ceníku tzn. VPS
  display("platby = $kc_platby, dary = $kc_dary, zaplaceno = $n_platby/$n_platici");
  $td= "td align='right'";
  // formátování bilance
  // předpisy
  $p_ubyt= $predpis['u']; $p_ubyt_= $kc($p_ubyt);
  $p_stra= $predpis['s']; $p_stra_= $kc($p_stra);
  $p_prog= $predpis['p']; 
  $p_slev= $predpis['d']; $p_slev_= $kc($p_slev);
  $p_slev= $predpis['d']; $p_slev_= $kc($p_slev);
  $p_suma= $p_ubyt+$p_stra+$p_prog+$p_slev; $p_suma_= $kc($p_suma);
//  $p_prog_par= $p_prog-$p_prog_deti; $p_prog_par_= $kc($p_prog_par);
  $p_slev_dot= $p_slev-$p_slev_vps; $p_slev_dot_= $kc($p_slev_dot);
  $p_slev_vps_= $kc($p_slev_vps);
  // náklady
  $n_ubyt= $naklad['u']; $n_ubyt_= $kc($n_ubyt);
  $n_stra= $naklad['s']; $n_stra_= $kc($n_stra);
  $n_suma= $n_ubyt+$n_stra; $n_suma_= $kc($n_suma);
  $html.= "<h3>Částečná bilance akce</h3>";
  $html.= "<i><b>Poznámka</b> "
      . "Tato částečná bilance zahrnuje pouze náklady na ubytování a stravování (vč. pečounů). "
      . "<br>Nejsou zahrnuty různé nájmy, faktury a DPP placených odborníků a další (tisky, materiál, ...).</i><br>";
  $btab= "<br><table class='stat'>";
  $btab.= "<tr><th></th><th>předpis plateb</th><th>náklady</th></tr>";
  $btab.= "<tr><th>ubytování</th><$td>$p_ubyt_</td><$td>$n_ubyt_</td></tr>";
  $btab.= "<tr><th>stravování</th><$td>$p_stra_</td><$td>$n_stra_</td></tr>";
  $btab.= "<tr><th>program účastníků</th><$td>$p_prog_pary_</td><td></td></tr>";
  $btab.= "<tr><th>program dětí</th><$td>$p_prog_deti_</td><td></td></tr>";
  $btab.= "<tr><th>slevy VPS</th><$td>$p_slev_vps_</td><td></td></tr>";
  $btab.= "<tr><th>individuální dotace</th><$td>$p_slev_dot_</td><td></td></tr>";
  $btab.= "<tr><th>suma</th><th align='right'>$p_suma_</th><th align='right'>$n_suma_</th></tr>";
  $btab.= "</table>";
  // -------------------------------------- platby & dary & vratky
  $kc_platby_= $kc($kc_platby);
  $kc_dary_= $kc($kc_dary);
  $kc_suma_= $kc($kc_platby+$kc_dary);
  $saturace= "$n_platby z $n_platici tj. ".round(100 * $n_platby/$n_platici);
  $dni= date("j/n");
  $ptab= "<br><table class='stat'>";
  $ptab.= "<tr><th></th><th>uhrazeno k $dni</th><th>stav</th></tr>";
  $ptab.= "<tr><th>předepsané platby</th><$td>$kc_platby_</td><$td>$saturace %</td></tr>";
  $ptab.= "<tr><th>přidané dary</th><$td>$kc_dary_</td><$td></td></tr>";
  $ptab.= "<tr><th>suma</th><th align='right'>$kc_suma_</th><th align='right'></th></tr>";
  $ptab.= "</table>";
  // tabulky vedle sebe
  $html.= "<table><tr><td>$btab</td><td>&nbsp;&nbsp;&nbsp;</td>"
      . "<td style='vertical-align:bottom'>$ptab</td></tr></table>";
//  /**/                                                    debug($tab,"akce2_text_eko_cv2 - bilance1");
  // výstup
  $result->html= $html; //.'<hr>'.debugx($tab);
  return $result;
}
# -------------------------------------------------------------------------------- tisk2 text_vyroci
function tisk2_text_vyroci($akce,$par,$title,$vypis,$export=false) { trace();
  global $tisk_hnizdo;
  $jen_hnizdo= $tisk_hnizdo ? " AND hnizdo=$tisk_hnizdo " : '';
  $cond= "id_akce=$akce $jen_hnizdo AND p.funkce NOT IN (9,10,13,14,15)";
  $result= (object)array('_error'=>0);
  $html= '';
  // data akce
  $vyroci= array();
  // narozeniny
  $res= tisk2_qry('ucastnik','prijmeni,jmeno,narozeni,role',
    "$cond AND CONCAT(YEAR(datum_od),SUBSTR(narozeni,5,6)) BETWEEN datum_od AND datum_do",
    "","SUBSTR(narozeni,5,6)");
  while ( $res && ($x= pdo_fetch_object($res)) ) {
    $vyroci[$x->role=='d'?'d':'a'][]= "{$x->prijmeni} {$x->jmeno}|".sql_date1($x->narozeni);
  }
  // výročí
  $res= tisk2_qry('pobyt_dospeli_ucastnici','datsvatba',
    "$cond AND CONCAT(YEAR(datum_od),SUBSTR(datsvatba,5,6)) BETWEEN datum_od AND datum_do",
    "","SUBSTR(datsvatba,5,6)");
  while ( $res && ($x= pdo_fetch_object($res)) ) {
    $vyroci['s'][]= "$x->_jm|".sql_date1($x->datsvatba);
  }
  // nepřivítané děti mladší 2 let
  $res= tisk2_qry('ucastnik','prijmeni,jmeno,narozeni,role,ROUND(IF(MONTH(o.narozeni),DATEDIFF(a.datum_od,o.narozeni)/365.2425,YEAR(a.datum_od)-YEAR(o.narozeni)),1) AS _vek',
    "$cond AND role='d' AND o.uvitano=0","_vek<2","prijmeni");
  while ( $res && ($x= pdo_fetch_object($res)) ) {
    $vyroci['v'][]= "{$x->prijmeni} {$x->jmeno}|".sql_date1($x->narozeni);
  }
  // redakce
  if ( isset($vyroci['s']) && count($vyroci['s']) ) {
    $html.= "<h3>Výročí svatby během akce</h3><table>";
    foreach($vyroci['s'] as $txt) {
      list($kdo,$kdy)= explode('|',$txt);
      $html.= "<tr><td>$kdy</td><td>$kdo</td></tr>";
    }
    $html.= "</table>";
  }
  else $html.= "<h3>Na akci nemá žádný pár výročí svatby</h3>";
  if ( isset($vyroci['a']) && count($vyroci['a']) ) {
    $html.= "<h3>Narozeniny dospělých na akci</h3><table>";
    foreach($vyroci['a'] as $txt) {
      list($kdo,$kdy)= explode('|',$txt);
      $html.= "<tr><td>$kdy</td><td>$kdo</td></tr>";
    }
    $html.= "</table>";
  }
  else $html.= "<h3>Na akci nemá žádný dospělý účastník narozeniny</h3>";
  if ( isset($vyroci['d']) && count($vyroci['d']) ) {
    $html.= "<h3>Narozeniny dětí na akci</h3><table>";
    foreach($vyroci['d'] as $txt) {
      list($kdo,$kdy)= explode('|',$txt);
      $html.= "<tr><td>$kdy</td><td>$kdo</td></tr>";
    }
    $html.= "</table>";
  }
  else $html.= "<h3>Na akci nemá žádné dítě narozeniny</h3>";
  if ( isset($vyroci['v']) && count($vyroci['v']) ) {
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
# ------------------------------------------------------------------------------- akce2 text_prehled
# pokud $title='' negeneruje se html
function akce2_text_prehled($akce,$title) { trace();
  global $USER;
  $org= $USER->org;
  $pocet= 0;
  # naplní histogram podle $cond
  $akce_text_prehled_x= function ($akce,$cond,
      $uvest_jmena=false,$bez_tabulky=false,$jen_pod_18=false) use (&$pocet) {
    $html= '';
    // data akce
    $veky= $kluci= $holky= array_fill(0,99,0);
    $nveky= $nkluci= $nholky= 0;
    $jmena= $deljmena= '';
    $bez= $del= '';
    // histogram věku dětí parametrizovaný přes $cond
    $qo=  "SELECT prijmeni,jmeno,narozeni,IFNULL(role,0),a.datum_od,o.sex,id_pobyt
           FROM akce AS a
           JOIN pobyt AS p ON a.id_duakce=p.id_akce
           JOIN spolu AS s USING(id_pobyt)
           JOIN osoba AS o ON s.id_osoba=o.id_osoba
           LEFT JOIN tvori AS t ON t.id_osoba=o.id_osoba AND IF(p.i0_rodina,t.id_rodina=p.i0_rodina,0)
           WHERE a.id_duakce='$akce' AND $cond AND funkce NOT IN (9,10,13,14,15) 
           GROUP BY o.id_osoba ORDER BY prijmeni ";
    $ro= pdo_qry($qo);
    while ( $ro && ($o= pdo_fetch_object($ro)) ) {
      $vek= narozeni2roky_sql($o->narozeni,$o->datum_od);
      if ($jen_pod_18 && $vek>=18) continue;
      $pocet++;
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
  $result= (object)array('html'=>'','pozor'=>'');
  $html= '';
  $nedeti= $org!=4
    ? $akce_text_prehled_x($akce,"t.role='d' AND p.funkce!=99 AND s.s_role NOT IN (2,3,4,5)",1,1,1)
    : '';
  if ( $pocet>0 ) {
    $html.= "<h3 style='color:red'>POZOR! Děti vedené chybně jako účastníci nebo hosté</h3>$nedeti";
    $result->pozor= "Děti vedené chybně jako účastníci nebo hosté: $nedeti";
  }
  // pfunkce: 0 4 5 8 92 95
  // pfunkce: 1=hlavoun, 2=instruktor, 3=pečovatel, 4=pomocný, 5=osobní, 6=mimořádný, 7=team, 8=člen G
  // funkce=99  -- pečoun, funkce=9,10,13,14,15 -- není na akci
  // s_role=2   -- dítě, s_role=3  -- dítě s os.peč, s_role=4  -- pom.peč, s_role=5  -- os.peč
  // dite_kat=7 -- skupina G
  // děti ...
  if ( $title ) {
    $html.= "<h2>Informace z karty Účastníci (bez náhradníků)</h2>
      <h3>Celkový počet dětí rodin na akci podle stáří (v době začátku akce) - bez os.pečounů včetně pom.pečounů</h3>";
    $html.= $akce_text_prehled_x($akce,"t.role='d' AND p.funkce!=99 AND s.s_role IN (0,1,2,3,4)");
    $html.= "<h3>Děti ve skupinkách (mimo G a osobně opečovávaných)</h3>";
    $html.= $akce_text_prehled_x($akce,"t.role='d' AND p.funkce!=99 AND s.s_role IN (2,4) AND s.dite_kat!=7");
    $html.= "<h3>Děti v péči osobního pečovatele</h3>";
    $html.= $akce_text_prehled_x($akce,"t.role='d' AND p.funkce!=99 AND s.s_role IN (3)",1);
    $html.= "<h3>Děti ve skupině G</h3>";
    $html.= $akce_text_prehled_x($akce,"t.role='d' AND p.funkce!=99 AND s.dite_kat=7",true);
    $html.= "<h3>Pomocní pečovatelé</h3>";
    $html.= $akce_text_prehled_x($akce,"t.role='d' AND p.funkce!=99 AND s.s_role IN (4)",1);
    // pečouni ...
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
  }
  return $result;
}
# ---------------------------------------------------------------------------------------- tisk2 qry
# frekventované SQL dotazy s parametry
# pobyt_dospeli_ucastnici => _jm=jména dospělých účastníků (GROUP BY id_pobyt)
# ucastnik                => každý účastník zvlášť
# pobyt_rodiny            => _jmena, _adresa, _telefony, _emaily
function tisk2_qry($typ,$flds='',$where='',$having='',$order='') { //trace();
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
      $where AND IF(funkce=99,1,IF(MONTH(pso.narozeni),DATEDIFF(a.datum_od,pso.narozeni)/365.2425,YEAR(a.datum_od)-YEAR(pso.narozeni))>18) 
      GROUP BY p.id_pobyt $having $order
    ";
    break;
  }
  $res= pdo_qry($qry);
  return $res;
}
# -------------------------------------------------------------------------------------- tisk2 table
function tisk2_table($tits,$flds,$clmn,$export=false,$prolog='') {  trace();
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
    $frmt= [];
    // titulky
    foreach ($tits as $if=>$idwf) {
      list($id,,$f)= explode(':',$idwf);
      $frmt[$if]= $f??'';
      $ths.= "<th>$id</th>";
    }
    foreach ($clmn as $c) {
      $tab.= "<tr>";
      foreach ($flds as $if=>$f) {
        $align= $frmt[$if]=='r' ? " align='right'" : '';
        if ( $f=='id_osoba' || $f=='^id_osoba' )
          $tab.= "<td style='text-align:right'>".tisk2_ukaz_osobu($c[$f])."</td>";
        elseif ( $f=='^id_rodina' )
          $tab.= "<td style='text-align:right'>".tisk2_ukaz_rodinu($c['^id_rodina'])."</td>";
        elseif ( $f=='^id_pobyt' )
          $tab.= "<td style='text-align:right'>".tisk2_ukaz_pobyt($c['^id_pobyt'])."</td>";
        else {
//                                 debug($c,$f); return $ret;
          $tab.= "<td$align>{$c[$f]}</td>";
        }
      }
      $tab.= "</tr>";
      $n++;
    }
    $prolog= $prolog ?: "Seznam má $n řádků<br><br>";
    $ret->html= "$prolog<div class='stat'><table class='stat'><tr>$ths</tr>$tab</table></div><br>";
  }
  return $ret;
}
# ----------------------------------------------------------------------------- akce2 jednou_dvakrat
# generování seznamu jedno- a dvou-ročáků spolu s mailem na VPS
#   $fld = seznam položek s prefixem
#   $cnd = podmínka
function akce2_jednou_dvakrat($akce,$par,$title,$vypis,$export=false) { trace();
  global $VPS;
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
  $res= pdo_qry($qry);
  while ( $res && ($x= pdo_fetch_object($res)) ) {
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
# ---------------------------------------------------------------------------------- akce2 skup_tisk
# tisk skupinek akce
# pro akce v hnízdech jsou skupinky číslovány lokálně vzestupnou řadou - není-li par->precislovat=0
function akce2_skup_tisk($akce,$par,$title,$vypis,$export) {  trace();
  global $VPS;
  $result= (object)array();
  $html= "<table>";
  $ret= akce2_skup_get($akce,0,$err,$par);
  $hnizda= select('hnizda','akce',"id_duakce=$akce");
                                                       debug($ret);
  $skupiny= $ret->skupiny;
  // pro par.mark=LK zjistíme účasti rodin na obnově
  $lk= 0;
  $na_kurzu= $na_obnove= $nahrada= $vps= $umi_vps= array();     // $nahrada = na obnově náhradnici => id_rodina->1
  if ( isset($par->mark) && ($par->mark=='LK' || $par->mark=='PO') ) {
    // chyba=-1 pro kombinaci par.mark=LK a akce není obnova MS
    if ( $err==-1 ) { $result->html= $ret->msg; display("err=$err");  goto end; }
    $lk= 1;
    // seznam rodin LK či PO - účastníci
    $rr= pdo_qry("SELECT i0_rodina FROM pobyt AS p
    WHERE p.id_akce={$ret->lk} AND funkce IN (0,1,2,5,6) ");
    while ( $rr && (list($idr,$nazev)= pdo_fetch_array($rr)) ) {
      $na_kurzu[$idr]= 1;
    }
    // seznam rodin obnovy
    $lk_nebyli= 0;
    $rr= pdo_qry("
      SELECT i0_rodina,CONCAT(nazev,' ',GROUP_CONCAT(jmeno ORDER BY role SEPARATOR ' a ')),funkce,
        FIND_IN_SET('1',r_umi)
      FROM pobyt AS p
      JOIN rodina AS r ON r.id_rodina=i0_rodina
      JOIN tvori AS t USING (id_rodina)
      JOIN osoba AS o USING (id_osoba)
      WHERE id_akce=$akce AND role IN ('a','b') AND funkce IN (0,1,2,5)
      GROUP BY i0_rodina
      ORDER BY nazev");
    while ( $rr && (list($idr,$nazev,$funkce,$umi)= pdo_fetch_array($rr)) ) {
      $x= '';
      if ( !isset($na_kurzu[$idr]) ) {
        $lk_nebyli++;
        $x= $nazev . ($funkce==9 ? " (náhradníci)" : '');
      }
      $na_obnove[$idr]= $x;
      if ( $funkce==1 ) $vps[$idr]= 1;
      elseif ( $umi )   $umi_vps[$idr]= 1;
      if ( $funkce==9 ) $nahrada[$idr]= 1;
    }
  }
  $n= 0;
  if ( $export ) {
    $clmn= $atrs= array();
    $poradi= 1; $c_skupina= 0;
    foreach ($skupiny as $i=>$s) {
      foreach ($s as $c) {
        $cislo_skupiny= $c->skupina;
        if ($par->precislovat && $c_skupina!=$c->skupina) {
          $cislo_skupiny= $hnizda ? $poradi++ : $c->skupina;
          $c_skupina= $c->skupina;
        }
        $clmn[$n]['skupina']= $i==$c->id_pobyt ? $cislo_skupiny : '';
        $clmn[$n]['jmeno']= $c->_nazev;
        if ( !$lk )
          $clmn[$n]['pokoj']= $i==$c->id_pobyt ? $c->pokoj : '';
        else {
          // pro LK přidáme atribut nezúčastněným
          if ( !isset($na_obnove[$c->i0_rodina]) ) {
            $atrs[$n]['jmeno']= "bcolor=ffdddddd";
            $clmn[$n]['jmeno']= '- '.$clmn[$n]['jmeno'];
          }
          // resp. náhradníkům
          else if ( isset($nahrada[$c->i0_rodina]) ) {
            $atrs[$n]['jmeno']= "bcolor=ffdddddd";
            $clmn[$n]['jmeno']= '+ '.$clmn[$n]['jmeno'];
          }
        }
        $n++;
      }
      $clmn[$n]['skupina']= $clmn[$n]['jmeno']= '';
      if ( !$lk )
        $clmn[$n]['pokoj']= '';
      $n++;
    }
    // pro LK přidáme seznam, co nebyli v létě
    $skup= 'bez LK';
    if ( $lk ) {
      if ( $lk_nebyli ) {
        foreach ($na_obnove as $nazev) {
          if ( $nazev ) {
            $clmn[$n]['skupina']= $skup; $skup= '';
            $clmn[$n]['jmeno']= $nazev;
            $n++;
          }
        }
      }
      else {
        $clmn[$n]['skupina']= $skup;
        $clmn[$n]['jmeno']= '-';
      }
    }
    // předání pro tisk2_vyp_excel
    $result->tits= explode(',',"skupinka:10,jméno:30".($lk ? '' : ",pokoj $VPS:10:r"));
    $result->flds= explode(',',"skupina,jmeno".($lk ? '' : ",pokoj"));
    $result->clmn= $clmn;
    $result->atrs= $atrs;
    $result->expr= null;
  }
  else {
    $xn= 0; $tabulka= '';
    $poradi= 1; $c_skupina= 0;
    foreach ($skupiny as $i=>$s) {
      $tab= "<table>";
      foreach ($s as $c) {
        $cislo_skupiny= $c->skupina;
        if ($par->precislovat && $c_skupina!=$c->skupina) {
          $cislo_skupiny= $hnizda ? "$poradi ($c->skupina)" : $c->skupina;
          $c_skupina= $c->skupina;
          $poradi++;
        }
        $nazev= $c->_nazev.($lk ? (isset($vps[$c->i0_rodina]) 
            ? " - VPS" : (isset($umi_vps[$c->i0_rodina]) ? " (vps)" : '')) : '');
        $pokoj= $lk ? '' : $c->pokoj;
        if ( $lk && !isset($na_obnove[$c->i0_rodina]) ) {
          $nazev= "<s>$nazev</s>";
          $xn++;
        }
        elseif ( $lk && isset($nahrada[$c->i0_rodina]) ) {
          $nazev= "<s>$nazev (náhradníci)</s>";
          $xn++;
        }
        if ( $i==$c->id_pobyt )
          $tab.= "<tr><th>$cislo_skupiny</th><th>$nazev</th><th>$pokoj</th></tr>";
        else
          $tab.= "<tr><td></td><td>$nazev</td><td></td></tr>";
      }
      $tab.= "</table>";
      if ( $n%2==0 )
        $tabulka.= "<tr><td>&nbsp;</td></tr><tr><td valign='top'>$tab</td>";
      else
        $tabulka.= "<td valign='top'>$tab</td></tr>";
      $n++;
    }
    if ( $n%2==1 )
      $tabulka.= "<td></td></tr>";
    $tabulka.= "</table>";
    if ( $lk ) {
      $setkani= $par->mark=='LK' ? 'posledního letního kurzu' : 'poslední obnovy';
      $html.= "<h3>Skupinky z $setkani se škrtnutými (je jich $xn)
        nepřihlášenými na obnovu</h3>$tabulka";
    }
    else 
      $html.= $tabulka;
    // pro mark=LK zobraz ty, co nebyly na kurzu
    if ( $lk ) {
      if ( $lk_nebyli ) {
        $n= 0; $pary= '';
        foreach ($na_obnove as $idr=>$nazev) {
          if ( $nazev ) {
            $pary.= "$nazev".(isset($vps[$idr])?' - VPS':(isset($umi_vps[$idr]) ? " (vps)" : ''))."<br>";
            $n++;
          }
        }
        $posledni= $par->mark=='LK' ? 'posledním letním kurzu' : 'poslední obnově';
        $html.= "<h3>Na $posledni  nebylo $n párů:</h3>$pary";
      }
      else {
        $html.= "<h3>Všichni přihlášení byli na posledním setkání</h3>";
      }
    }
    if ($hnizda && $par->precislovat) {
      $html= "<b>Poznámka</b>: V závorce je vždy uvedeno číslo skupinky v rámci celé akce 
        - tedy údaj u pobytu na panelu Účastníci.".$html;
    }
    $result->html= $html;
  }
end:
//                                                 debug($result,"akce2_skup_tisk($akce,,$title,$vypis,$export)");
  return $result;
}
# ---------------------------------------------------------------------------------- akce2 skup_hist
# přehled starých skupinek letního kurzu MS účastníků této akce
function akce2_skup_hist($akce,$par,$title,$vypis,$export) { trace();
  global $tisk_hnizdo;
  $jen_hnizdo= $tisk_hnizdo ? " AND hnizdo=$tisk_hnizdo " : '';
  $result= (object)array();
  // letošní účastníci
  $letos= array();
  $rok= 0;
  $qry=  "SELECT skupina,r.nazev,r.obec,year(datum_od) as rok,p.funkce as funkce,
            GROUP_CONCAT(DISTINCT IF(t.role='a',o.id_osoba,'') SEPARATOR '') as id_osoba_m,
            LEFT(GROUP_CONCAT(DISTINCT IF(t.role='a',o.jmeno,'') SEPARATOR ''),1) as jmeno_m,
            LEFT(GROUP_CONCAT(DISTINCT IF(t.role='b',o.jmeno,'') SEPARATOR ''),1) as jmeno_z,
            id_pobyt
          FROM pobyt AS p
          JOIN akce  AS a ON a.id_duakce=p.id_akce
          JOIN spolu AS s USING(id_pobyt)
          JOIN osoba AS o ON s.id_osoba=o.id_osoba
          LEFT JOIN tvori AS t ON t.id_osoba=o.id_osoba
          LEFT JOIN rodina AS r USING(id_rodina)
          WHERE id_akce=$akce AND p.funkce IN (0,1,2,5) $jen_hnizdo
          GROUP BY id_pobyt
          ORDER BY IF(pouze=0,r.nazev,o.prijmeni) ";
  $res= pdo_qry($qry);
  while ( $res && ($u= pdo_fetch_object($res)) ) {
    $u->nazev= str_replace(' ','-',$u->nazev);
    $muz= $u->id_osoba_m;
    $letos[$muz]= $u;
    $letos[$muz]->_nazev= $u->nazev;
    $rok= $u->rok;
  }
//                                                         debug($letos);
  $letosni= implode(',',array_keys($letos));
  // doplnění nejednoznačných příjmení o iniciály křestních jmen
  $old= 0; $old_nazev= '';
  foreach ($letos as $muz=>$info) {
    if ( $old_nazev==$info->_nazev ) {
      $inic_old= $letos[$old]->jmeno_m.'+'.$letos[$old]->jmeno_z;
      $inic_muz= $letos[$muz]->jmeno_m.'+'.$letos[$muz]->jmeno_z;
      $letos[$old]->_nazev= $letos[$old]->nazev.'&nbsp;'.$inic_old;
      $letos[$muz]->_nazev= $letos[$muz]->nazev.'&nbsp;'.$inic_muz;
    }
    $old= $muz;
    $old_nazev= $info->_nazev;
  }
//                                                         debug($odkud);
  // tisk
  $td= "td style='border-top:1px dotted grey'";
  $th= "th style='border-top:1px dotted grey;text-align:right'";
  $html= "<table>";
  foreach ($letos as $muz=>$info) {
    // minulé účasti
    $n= 0;
    $qry= " SELECT p.id_akce,skupina,year(datum_od) as rok
            FROM akce AS a
            JOIN pobyt AS p ON a.id_duakce=p.id_akce
            JOIN spolu AS s USING(id_pobyt)
            WHERE a.druh=1 AND s.id_osoba='$muz' AND p.id_akce!=$akce AND skupina!=0
            ORDER BY datum_od DESC ";
    $res= pdo_qry($qry);
    $ucasti= '';
    while ( $res && ($r= pdo_fetch_object($res)) ) {
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
      $res_s= pdo_qry($qry_s);
      $spolu= ''; $del= '';
      while ( $res_s && ($s= pdo_fetch_object($res_s)) ) if ( $s->id_osoba_m!=$muz ) {
        $spolu.= "$del{$letos[$s->id_osoba_m]->_nazev}";
        $del= ',&nbsp;';
      }
      if ( $spolu ) {
        $ucasti.= " <u>{$r->rok}</u>:&nbsp;$spolu";
      }
    }
    if ( $ucasti )
      $html.= "<tr><$th>{$info->_nazev}</th><$th>$n&times;</th><$td>$ucasti</td></tr>";
//    elseif ( $n )
    else
      $html.= "<tr><$th>{$info->_nazev}</th><$th>$n&times;</th><$td>-</td></tr>" ;
  }
  $html.= "</table>";
  $note= "Abecední seznam účastníků letního kurzu roku $rok doplněný seznamem členů jeho starších
          skupinek na letních kurzech. <br>Ve skupinkách jsou uvedení jen účastníci
          kurzu roku $rok. (Pro tisk je nejjednodušší označit jako blok a vložit do Excelu.)";
  $html= "<i>$note</i><br>$html";
  //$result->html= nl2br(htmlentities($html));
  $result->html= $html;
  return $result;
}
# ---------------------------------------------------------------------------------- akce2 skup_popo
# přehled pro tvorbu virtuální obnovy
function akce2_skup_popo($akce,$par,$title,$vypis,$export) { trace();
  $male_dite= 9; // hranice pro upozornění na malé dítě v rodine
  $obnova= 1;     //1=jarní 2=podzimní
  $result= (object)array();
  // letošní účastníci
  $letos= $skup_vps= $znami= $stejny_nazev= array();
  $qry=  "SELECT skupina,r.nazev,r.obec,year(datum_od) as rok,month(datum_od) as mes,
            p.funkce as funkce,
            IF(FIND_IN_SET(1,r_umi),1,0) AS _vps,r_ms,
            GROUP_CONCAT(DISTINCT IF(t.role='a',o.id_osoba,'') SEPARATOR '') as id_osoba_m,
            LEFT(GROUP_CONCAT(DISTINCT IF(t.role='a',o.jmeno,'') SEPARATOR ''),1) as jmeno_m,
            LEFT(GROUP_CONCAT(DISTINCT IF(t.role='b',o.jmeno,'') SEPARATOR ''),1) as jmeno_z,
            ( SELECT CONCAT(COUNT(*),';',
                -- IFNULL(GROUP_CONCAT(ROUND(DATEDIFF(a.datum_od,narozeni)/365.2425) ORDER BY narozeni DESC),''))
                IFNULL(GROUP_CONCAT(ROUND(IF(MONTH(narozeni),DATEDIFF(a.datum_od,narozeni)/365.2425,YEAR(a.datum_od)-YEAR(narozeni))) ORDER BY narozeni DESC),''))
              FROM tvori JOIN osoba USING (id_osoba)
              WHERE id_rodina=i0_rodina AND role='d'
            ) AS _deti,id_pobyt
          FROM pobyt AS p
          JOIN akce  AS a ON a.id_duakce=p.id_akce
          JOIN spolu AS s USING (id_pobyt)
          JOIN osoba AS o ON s.id_osoba=o.id_osoba
          JOIN tvori AS t ON t.id_osoba=o.id_osoba AND t.id_rodina=p.i0_rodina
          JOIN rodina AS r ON r.id_rodina=p.i0_rodina
          WHERE id_akce=$akce AND p.funkce IN (0,1,2,5) 
          GROUP BY id_pobyt
          ORDER BY funkce,_vps,r.nazev ";
  $res= pdo_qry($qry);
  while ( $res && ($u= pdo_fetch_object($res)) ) {
    $u->nazev= str_replace(' ','-',$u->nazev);
    $obnova= $u->mes<7 ? 1 : 2;
    if (isset($stejny_nazev[$u->nazev]))
      $stejny_nazev[$u->nazev]++;
    else
      $stejny_nazev[$u->nazev]= 1;
    $muz= $u->id_osoba_m;
    $letos[$muz]= $u;
    $letos[$muz]->_nazev= $u->nazev;
    $letos[$muz]->ms= $u->r_ms;
    if ($u->funkce==1) {
      $letos[$muz]->vps= 'VPS';
      if ($u->skupina)
        $skup_vps[$u->skupina]= $muz;
    }
    $letos[$muz]->skup= $u->skupina;
    // rozbor dětí
    $deti= '';
    $d_nr= explode(';',$u->_deti);
    if ($d_nr[0]) {
      $d_r= explode(',',$d_nr[1]);
      if ($d_r[0]<=$male_dite) {
        $deti= 'děti';
      }
    }
    $letos[$muz]->deti= $deti;
    // rozbor umí
    if ($u->funkce!=1 && $u->_vps)
      $letos[$muz]->vps= '(vps)';
    $rok= $u->rok;
  }
//                                                         debug($letos);
  $letosni= implode(',',array_keys($letos));
  // doplnění nejednoznačných příjmení o iniciály křestních jmen
  foreach ($letos as $muz=>$info) {
    if ( $stejny_nazev[$info->_nazev]>1 ) {
      $inic= $letos[$muz]->jmeno_m.'+'.$letos[$muz]->jmeno_z;
      $letos[$muz]->_nazev= $letos[$muz]->nazev.'&nbsp;'.$inic;
    }
  }
//                                                         debug($letos);
  foreach ($letos as $muz=>$info) {
    // minulé účasti na LK
    $n= $n_lk= 0;
    $qry= " SELECT p.id_akce,druh,skupina,year(datum_od) as rok,
              IF(a.nazev LIKE 'MLS%','m',IF(druh=2,'o','')) AS _druh
            FROM akce AS a
            JOIN pobyt AS p ON a.id_duakce=p.id_akce
            JOIN spolu AS s USING (id_pobyt)
            JOIN rodina AS r ON r.id_rodina=p.i0_rodina
            WHERE a.druh IN (1,2) AND s.id_osoba='$muz' AND p.id_akce!=$akce AND skupina!=0
            ORDER BY datum_od DESC ";
    $res= pdo_qry($qry);
    $ucasti= '';
    while ( $res && ($r= pdo_fetch_object($res)) ) {
      $n++;
      if ($r->druh==1) $n_lk++;
      // minulé skupinky - včetně obnov
      $qry_s= "
            SELECT GROUP_CONCAT(DISTINCT IF(t.role='a',o.id_osoba,'') SEPARATOR '') as _muz
            FROM akce AS a
            JOIN pobyt AS p ON a.id_duakce=p.id_akce
            JOIN spolu AS s USING(id_pobyt)
            JOIN osoba AS o ON s.id_osoba=o.id_osoba
            LEFT JOIN tvori AS t ON t.id_osoba=o.id_osoba
            WHERE p.id_akce={$r->id_akce} AND skupina={$r->skupina} AND skupina!=0
            GROUP BY id_pobyt HAVING FIND_IN_SET(_muz,'$letosni')
            ORDER BY datum_od DESC ";
      $res_s= pdo_qry($qry_s);
      $spolu= ''; $del= '';
      while ( $res_s && ($s= pdo_fetch_object($res_s)) ) {
        if ( $s->_muz!=$muz ) {
          // vytvoření tipů - vynecháme VPS a ty, co už mají skupinku
          if (!$letos[$s->_muz]->vps && !$letos[$s->_muz]->skup) {
            $s_nazev= $letos[$s->_muz]->_nazev;
            $s_nazev= $r->_druh ? "? $s_nazev" : $s_nazev;
            if (isset($znami[$muz])) {
              if (!in_array($s_nazev,$znami[$muz])) {
                $znami[$muz][]= $s_nazev;
              }
            }
            else {
              $znami[$muz]= array($s_nazev);
            }
          }

          if ($letos[$s->_muz]->vps=='VPS') {
            $spolu.= "$del<b>{$letos[$s->_muz]->_nazev}</b>";
          }
          else {
            $spolu.= "$del{$letos[$s->_muz]->_nazev}";
          }
          $del= ',&nbsp;';
        }
      }
      if ( $spolu ) {
        $ucasti.= " <u>{$r->rok}{$r->_druh}</u>:&nbsp;$spolu";
      }
    }
    // přidáme účasti na jiném kurzu
    $info->ms= $info->ms ? "$n_lk+{$info->ms}" : $n_lk;
    // redakce výpisu
    if ($info->skup && $info->vps!='VPS') {
      $vps= isset($skup_vps[$info->skup]) ? $skup_vps[$info->skup] : '';
      if ($vps) {
        $letos[$vps]->lidi.= " + {$info->_nazev} ";
      }
    }
    $info->ucasti= $ucasti;
  }
//                                                        debug($znami);
  // tisk
  $td= "td style='border-top:1px dotted grey'";
  $th= "th style='border-top:1px dotted grey;text-align:right'";
  $tl= "th style='border-top:1px dotted grey;text-align:left'";
  $cast= 'ucastnici';
  $html= "<h3>Účastníci ... s kým a kdy byli ve skupince 
          (v roce zakončeném: 'o' na obnově, 'm' na mlsu, jinak na letním kurzu)</h3><table>";
  foreach ($letos as $muz=>$info) {
    $skup= $info->skupina ? "{$info->skupina}.&nbsp;skup. " : '';
    if ($cast=='ucastnici' && $info->vps=='(vps)') {
      $cast= '(vps)';
      $html.= "</table><h3>Odpočívající VPS ... s kým a kdy byli ve skupince</h3><table>";
    }
    if (($cast=='(ucastnici'||$cast=='(vps)') && $info->vps=='VPS') {
      $cast= 'VPS';
      $html.= "</table><h3>VPS ve službě ... '+' označuje složení skupinky ... 
        '?' s kým se znají z LK '??' s kým se znají z obnov a mlsů (vše bez VPS)</h3><table>";
    }
    if ($info->vps!='VPS') {
      $html.= "<tr><td>$skup </td><td>{$info->_nazev}</td><$th>{$info->ms}&times;LK</th>
                   <$tl>$info->deti</th><$td>{$info->ucasti}</td></tr>" ;
    }
    else { // VPS
      $tips= $znami[$muz] ? implode(' ?',$znami[$muz]) : '';
      $tips= $tips ? " ... ( ?$tips )" : '';
      $html.= "<tr><th>$skup</th><$tl>{$info->_nazev}</th><$th>{$info->ms}&times;LK</th>
                   <$tl>$info->deti</th><$td>{$info->lidi} $tips</td></tr>" ;
    }
  }
  $html.= "</table>";
  $obnovy= $obnova==1 ? "Jarní virtuální obnovy" : "Podzimní virtuální obnovy";
  $note= "<h3>Pomůcka pro vytvoření $obnovy</h3>
    Zobrazují se údaje <ul>
    <li> skupinka a funkce
    <li> počet účastí na LK 
    <li> poznámka <b>děti</b> pokud mají malé děti (do $male_dite let) 
    <li> seznam lidí, se kterými již v minulosti byli ve skupince (aktuální VPS jsou tučně)
    <li> ve spodní části s VPS jsou zapsány členi skupinky (mají před jménem +) a tipy na ně v závorce
    </ul>
    ";
  $html= "<i>$note</i><br>$html";
  //$result->html= nl2br(htmlentities($html));
  $result->html= $html;
  return $result;
}
# ---------------------------------------------------------------------------------- akce2 skup_deti
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
         LEFT JOIN tvori AS t ON t.id_osoba=o.id_osoba AND t.id_rodina=p.i0_rodina
         WHERE id_duakce='$akce' AND p.funkce NOT IN (9,10,13,14,15)
         GROUP BY id_duakce ";
  $res= pdo_qry($qry);
  $pocet= pdo_fetch_object($res);
  if (!$pocet) return $result;
//                                                         debug($pocet,"počty");
  // zjištění skupinek
  $skupiny= array();   // [ skupinka => [{fce,příjmení,jméno},....], ...]
  $qry="SELECT id_pobyt,skupinka,funkce,prijmeni,jmeno,narozeni,rc_xxxx,datum_od
        FROM osoba AS o
        JOIN spolu AS s USING(id_osoba)
        JOIN pobyt AS p USING(id_pobyt)
        JOIN akce  AS a ON id_duakce='$akce'
        WHERE  id_akce='$akce' AND skupinka!=0 AND p.funkce NOT IN (9,10,13,14,15)
        ORDER BY skupinka,IF(funkce=99,0,1),prijmeni,jmeno ";
  $res= pdo_qry($qry);
  while ( $res && ($o= pdo_fetch_object($res)) ) {
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
function akce2_tabulka_stat($akce,$par,$title,$vypis,$export=0) { trace();
  global $tisk_hnizdo, $ezer_version;
  $result= (object)array();
  $html= "";
  $err= "";
  $pobyt= array();
  // akce
  list($nazev_akce,$datum_od)= select('nazev,datum_od','akce',"id_duakce=$akce");
  // účastníci
  $qry=  "SELECT
          id_pobyt,nazev,svatba,datsvatba,
          ( SELECT GROUP_CONCAT(CONCAT(role,narozeni) ORDER BY role,narozeni DESC)
            FROM osoba JOIN tvori USING (id_osoba)
            WHERE id_rodina=i0_rodina AND role IN ('a','b','d') 
          ) AS _cleni
          FROM pobyt AS p
          JOIN rodina AS r ON r.id_rodina=i0_rodina
          WHERE id_akce=$akce AND p.hnizdo=$tisk_hnizdo AND p.funkce IN (0,1,2,5)  -- včetně hospodářů, bývají hosty skupinky
          -- AND id_pobyt=54030
          GROUP BY id_pobyt
          ORDER BY nazev
          -- LIMIT 1
  ";
//   $qry.= " LIMIT 1";
  $res= pdo_qry($qry);
  while ( $res && (list($idp,$nazev,$sv1,$sv2,$xcleni)= pdo_fetch_row($res)) ) {
    $pobyt[$idp]['name']= $nazev;
    // délka manželství
    $manzelstvi= 
        $sv2 ? sql2stari($sv2,$datum_od) : (
        $sv1 ? sql2stari("$sv1-07-01",$datum_od) : 999);
    $pobyt[$idp]['m'][]= $manzelstvi;
    foreach ( explode(',',$xcleni) as $xclen) {
      $role= substr($xclen,0,1);
      $narozeni= substr($xclen,1);
      $roku= sql2stari($narozeni,$datum_od);
      $pobyt[$idp][$role][]= $roku;
    }
  }
//                                                              debug($pobyt,"1");
  // zpracování podle intervalů
  $kat= array(
    'm' => array('manželství',array(9,20,999)),                 // délka manželství
    'r' => array('věk rodičů',array(30,45,60,75,999)),          // průměrný věk rodičů a/b
    'x' => array('od sebe',   array(5,10,999)),                 // rozdíl věku rodičů a/b
    'd' => array('věk dětí',  array(7,18,30,999)),              // věk dětí d
  );
  $stari= array();
  foreach ($pobyt as $idp=>$cleni) {
    $cleni['r'][]= round(($cleni['a'][0]+$cleni['b'][0])/2); 
    $cleni['x'][]= abs($cleni['a'][0]-$cleni['b'][0]); 
    foreach ($kat as $k=>$xdelims) {
      if ( isset($cleni[$k]) )
      foreach ($cleni[$k] as $stari) {
        foreach ($xdelims[1] as $delim) {
          if ( $stari < $delim) {
            $pobyt[$idp]["-$k"][$delim]++;
            break;
          }
        }
      }
    }
  }
  $title= "Statistika akce $nazev_akce roku ".substr($datum_od,0,4);
  $html.= "<h1>$title</h1><table class='vypis'>";
  $fname= cz2ascii("vypis_").date("Ymd_Hi");
  $xls= "|open $fname|sheet vypis;;L;page\n";
  $_xls= "|A1 $title ::bold size=14 \n|A2 $vypis ::bold size=12\n";
  // hlavička
  $html.= "<tr>";
  $lc= 0;
  $n= 4;
  foreach ($kat as $k=>$xdelims) {
    $cols= count($xdelims[1]);
    $html.= "<th colspan=$cols>{$xdelims[0]}</th>";
    $A= Excel5_n2col($lc);
    $_xls.= "\n|$A$n {$xdelims[0]}";
    $lc+= $cols;
  }
  $_xls.= "\n";
  $n++;
  $html.= "</tr>";
  $html.= "<tr>";
  $lc= 0;
  foreach ($kat as $k=>$xdelims) {
    foreach ($xdelims[1] as $delim) {
      $border= '::border=,,t,';
      if ( $delim==999 ) {
        $delim= '...';
        $border= '::border=,t,t,';
      }
      $html.= "<th>$delim</th>";
      $A= Excel5_n2col($lc++);
      $_xls.= "|$A$n $delim $border";
    }
    $_xls.= "\n";
  }
  $lw= $lc;
  $xls.= "|columns A:$A=5";
  $Aname= Excel5_n2col($lc++);
  $xls.= ",$Aname=25\n";
  $_xls.= "\n|A4:$A$n bcolor=ffc0e2c2 border=t";
//  "|A5:{$A}5 border=t,,t,t\n";
  $xls.= "\n$_xls";
  $n++;
  $html.= "</tr>";
  // data
  foreach ($pobyt as $idp=>$cleni) {
    $html.= "<tr>";
    $lc= 0;
    foreach ($kat as $k=>$xdelims) {
      foreach ($xdelims[1] as $delim) {
        $kn= $pobyt[$idp]["-$k"][$delim];
        $html.= "<td>$kn</td>";
        if ( $kn || $delim==999 ) {
          $A= Excel5_n2col($lc);
          if ( $delim==999 ) $kn.= ' ::border=,t,,';
          $xls.= "\n|$A$n $kn";
        }
        $lc++;
      }
    }
    $name= $pobyt[$idp]['name'];
    $html.= "<th>$name</th>";
    $html.= "</tr>";
    $A= Excel5_n2col($lc++);
    $xls.= "\n|$A$n $name";
    $xls.= "\n";
    $n++;
  }
  $html.= "</table>";
  // časová značka
  $kdy= date("j. n. Y v H:i");
  $n+= 4;
  $xls.= "\n\n|A$n Výpis byl vygenerován $kdy :: italic";
  $xls.= "\n|close";
//                                                                display($xls);
  require_once "ezer$ezer_version/server/vendor/autoload.php";
  $inf= Excel2007($xls);
  $ref= " Statistika byla vygenerován ve formátu <a href='docs/$fname.xlsx' target='xls'>Excel</a>.";
//                                                                debug($pobyt,"2");
end:
  $result->html= "$err$ref$html";
  return $result;
}
# =======================================================================================> . plachta
# ------------------------------------------------------------------------------- tisk2 pdf_plachta0
# generování pomocných štítků
function tisk2_pdf_plachta0($report_json=0) {  trace();
  global $ezer_path_docs;
  $result= (object)array('_error'=>0);
  $n= 0;
  if ( $report_json) {
    $parss= array();
    // čísla skupinek
    for ($i= 1; $i<=30; $i++ ) {
      $parss[$n]= (object)array();  // {cislo]
      $fs= 20;
      $s1= "font-size:{$fs}mm;font-weight:bold";
      $bg1= ";color:#00aa00;line-height:40mm";
      $ii= $i<10 ? "&nbsp;&nbsp;&nbsp;$i" : "&nbsp;$i";
      $parss[$n]->prijmeni= "<div style=\"$s1$bg1\">$ii</div>";
      $parss[$n]->jmena= '';
      $n++;
    }
    // souřadnicový systém 2x
    for ($k= 1; $k<=1; $k++) {
      for ($i= 1; $i<=14; $i+=2 ) {
        // definice pole substitucí
        $parss[$n]= (object)array();  // {cislo]
        $fs= 22;
        $s1= "font-size:{$fs}mm;font-weight:bold";
        $bg1= ";color:#aa0000;line-height:40mm";
        $ii= $i<10 ? "&nbsp;$i&nbsp;" : "$i";
        $ia= chr(ord('A')+$i-1);
        $ib= chr(ord('A')+$i);
        $fill= $i==9 ? "&nbsp;" : '';
        $parss[$n]->prijmeni= "<span style=\"$s1$bg1\">&nbsp;$fill$ia &nbsp;  $fill$ib</span>";
        $parss[$n]->jmena= '';
        $n++;
      }
      for ($i= 1; $i<=8; $i+=2 ) {
        // definice pole substitucí
        $parss[$n]= (object)array();  // {cislo]
        $fs= 22;
        $s1= "font-size:{$fs}mm;font-weight:bold";
        $bg1= ";color:#aa0000;line-height:40mm";
        $ia= $i<10 ? "&nbsp;$i" : "$i";
        $ib= $i+1;
        $ib= $i+1<10 ? "&nbsp;$ib" : "$ib";
        $parss[$n]->prijmeni= "<span style=\"$s1$bg1\">$ia &nbsp;$ib</span>";
        $parss[$n]->jmena= '';
        $n++;
      }
    }
    // předání k tisku
    $fname= 'stitky_'.date("Ymd_Hi");
    $fpath= "$ezer_path_docs/$fname.pdf";
    $err= dop_rep_ids($report_json,$parss,$fpath);
    $result->html= $err ? $err
      : " Výpis byl vygenerován ve formátu <a href='docs/$fname.pdf' target='pdf'>PDF</a>.";
  }
  else {
    $result->html= "pomocné šítky";
  }
  return $result;
}
# ------------------------------------------------------------------------------------ akce2 plachta
# podklad pro tvorbu skupinek
function akce2_plachta($akce,$par,$title,$vypis,$export=0,$hnizdo=0) { trace();
  global $tisk_hnizdo;
  $tisk_hnizdo= $hnizdo;
  $result= (object)array();
  // číselníky
  $c_vzdelani= map_cis('ms_akce_vzdelani','zkratka');  $c_vzdelani[0]= '?';
  $c_cirkev= map_cis('ms_akce_cirkev','zkratka');      $c_cirkev[0]= '?';  $c_cirkev[1]= 'kat';
  $letos= date('Y');
  $html= "";
  $err= "";
  $excel= array();
  // informace
  $par2= (object)array('typ'=>'tab','cnd'=>"p.funkce NOT IN (99)",
      'fld'=>'key_rodina,prijmeni,jmena2,rodice,vek_deti,x_ms,_vps,funkce,^id_pobyt');  
  $c= akce2_tabulka($akce,$par2,'','');
  if (!$c->clmn) return $result;
//                                                debug($c,'akce2_tabulka');
  // získání všech id_pobyt - definice ORDER
  $ids= array_merge($c->vps,$c->nevps,$c->novi,$c->druh,$c->vice);
  $ids= implode(',',$ids);
  $ids_1= implode(',',$c->novi);
  $ids_2= implode(',',$c->druh);
  $ids_3= implode(',',$c->vice);
  $ids_4= implode(',',$c->vps);
  $ids_5= implode(',',$c->nevps);
  $kategorie= "CASE 
      WHEN FIND_IN_SET(id_pobyt,'$ids_1') THEN 1
      WHEN FIND_IN_SET(id_pobyt,'$ids_2') THEN 2
      WHEN FIND_IN_SET(id_pobyt,'$ids_3') THEN 3
      WHEN FIND_IN_SET(id_pobyt,'$ids_4') THEN 4
      WHEN FIND_IN_SET(id_pobyt,'$ids_5') THEN 5
    END  ";
  $vek= "narozeni_m DESC";
  $vzdelani= "_vzdelani";
//  $ids= "2287,3323";
//                                                debug($ids,count($ids));
  $qry=  "SELECT
          id_pobyt,id_rodina,r.nazev as jmeno,
          $kategorie AS _kat,
          r.obec as mesto,svatba,datsvatba,
          SUM(IF(s.s_role IN (2,3),1,0)) AS _detisebou,
          c.hodnota AS _skola,
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
          MAX(IF(t.role IN ('a','b'),c.hodnota,0)) as _vzdelani,
          ( SELECT COUNT(*)
            FROM osoba JOIN tvori USING(id_osoba)
            WHERE id_rodina=t.id_rodina AND role='d' AND umrti=0) AS deti,
          ( SELECT MIN(narozeni)
            FROM osoba JOIN tvori USING(id_osoba)
            WHERE id_rodina=t.id_rodina AND role='d' AND umrti=0) AS maxdeti,
          ( SELECT MAX(narozeni)
            FROM osoba JOIN tvori USING(id_osoba)
            WHERE id_rodina=t.id_rodina AND role='d' AND umrti=0) AS mindeti
          FROM pobyt AS p
          JOIN spolu AS s USING (id_pobyt)
          JOIN osoba AS o ON s.id_osoba=o.id_osoba
          LEFT JOIN tvori AS t ON t.id_osoba=o.id_osoba AND t.id_rodina=i0_rodina
          LEFT JOIN rodina AS r USING (id_rodina)
          LEFT JOIN _cis AS c ON c.druh='ms_akce_vzdelani' AND c.data=o.vzdelani
          WHERE id_pobyt IN ($ids) AND funkce IN (0,1,2,5)
          GROUP BY id_pobyt
          ORDER BY _kat, /*$vzdelani,*/ $vek";
//  $qry.= " LIMIT 1";
  $res= pdo_qry($qry);
  while ( $res && ($u= pdo_fetch_object($res)) ) {
//    debug($u);
    $idp= $u->id_pobyt;

    
    // minulé účasti - ale ne jako děti účastnické rodiny
    $xms= $c->clmn[$idp]['x_ms'];
    $u->ucasti= $xms ? "  {$xms}x" : '';
    // věk
    $vek_m= sql2stari($u->narozeni_m,$u->datum_od)?:0;
    $vek_z= sql2stari($u->narozeni_z,$u->datum_od)?:0;
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
    $sebou= 0;
    if ( $deti ) {
      $sebou= $u->_detisebou;
//      $nesebou= $deti-$sebou;
//      $deti= "$sebou+$nesebou";
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
    $key= str_pad($vzdelani_muze,2,' ',STR_PAD_LEFT).str_pad($vek_m,2,'0',STR_PAD_LEFT).$u->jmeno;
    list($prijmeni1,$etc)= explode(' ',$u->jmeno);
    if ( $etc ) $prijmeni1.= " ...";
    $result->pdf[$key]= array(
      'vps'=>$vps,'prijmeni'=>$prijmeni1,'jmena'=>"{$u->jmeno_m} a {$u->jmeno_z}",'ucasti'=>$u->ucasti);
    // --------------------------------------------------------------  pro XLS
    $majiseboudeti= $sebou ? " +$sebou" : '';
    $ucasti= $u->ucasti ? "... $u->ucasti" : 'NOVÍ';
    $r1= "$vps{$u->jmeno} {$u->jmeno_m} a {$u->jmeno_z}$majiseboudeti $ucasti";
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
      $excel[]= array($r1,$r2,$r31,$r41,$r51,$r32,$r42,$r52,$vzdelani_muze,$vek_m,$u->jmeno,$u->_kat);
    }
  }


  if ( $export ) {
    $result->xhref= akce2_plachta_export($excel,'plachta');
    $result->xhref.= "<br><br>$err<hr>$html";
  }
end:
  $html.= $c->html;
  $result->html= "$err$html";
  return $result;
}
# ----------------------------------------------------------------------------- akce2 plachta_export
function akce2_plachta_export($line,$file) { trace();
  global $ezer_version;
  require_once("./ezer$ezer_version/server/licensed/xls/OLEwriter.php");
  require_once("./ezer$ezer_version/server/licensed/xls/BIFFwriter.php");
  require_once("./ezer$ezer_version/server/licensed/xls/Worksheet.php");
  require_once("./ezer$ezer_version/server/licensed/xls/Workbook.php");
  global $ezer_path_root;
  global $tisk_hnizdo;
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
    $fields= explode(',',
        'r1:20,r2:20,r31:20,r41:20,r51:20,r32:20,r42:20,r52:20,skola:8,vek:4,prijmeni:12,kat:4');
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
      $ws->write_string($sy,$sx++,utf2win_sylk($x[10],true));
      $ws->write_number($sy,$sx++,$x[11]);
    }
    $wb->close();
    $html= " Výpis byl vygenerován ve formátu <a href='docs/$name.xls' target='xls'>Excel</a>.";
    $html.= " <br>Vygenerovaným listem <b>Hodnoty</b> je třeba nahradit stejnojmenný list v sešitu";
    $html.= " <b>doc/plachta17.xls</b> a dále postupovat podle návodu v listu <b>Návod</b>.";
  }
  catch (Exception $e) {
    $html.= nl2br("Chyba: ".$e->getMessage()." na ř.".$e->getLine());
  }
  return $html;
}
# ------------------------------------------------------------------------------ akce2 vyuctov_pary2
# generování sestavy vyúčtování pro účastníky $akce - bez DPH, zato se zvláštími platbami dětí
#   $fld = seznam položek s prefixem
#   $cnd = podmínka
# počítané položky
#   manzele = rodina.nazev muz a zena
# generované vzorce
#   platit = součet předepsaných plateb
function akce2_vyuctov_pary2($akce,$par,$title,$vypis,$export=false) { trace();
  global $tisk_hnizdo;
  $ord= isset($par->ord) ? $par->ord : "IF(funkce<=2,1,funkce),IF(pouze=0,r.nazev,o.prijmeni)";
  $result= (object)array();
  $tit= "Jméno:25"
      . ",pokoj:7,dětí:5:r,lůžka:5:r:s,přis týlky:5:r:s,kočá rek:5:r:s,nocí:5:r:s"
      . ",str. celá:5:r:s,str. pol.:5:r:s"
      . ",poplatek dospělí:8:r:s"
      . ",na účet:7:r:s,datum platby:10:r"
      . ",poplatek děti:8:r:s,na účet děti:7:r:s,datum platby děti:10:r"
      . ",nedo platek:7:r:s,pokladna:6:r:s,přepl.:6:r:s,poznámka:50,SPZ:9"
      . ",rozpočet dospělí:10:r:s,rozpočet děti:10:r:s"
      . "";
  $fld= "=jmena"
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
    list($id,,$f,$sum)= array_merge(explode(':',$idw),array_fill(0,4,''));
    if ( $sum=='s' ) $suma[$fld]= 0;
    if ( isset($f) ) $fmts[$fld]= $f;
  }
  // data akce
  $qry=  "SELECT id_pobyt,
          poplatek_d,naklad_d, -- platba_d,datplatby_d
          p.pouze,pokoj,luzka,pristylky,kocarek,pocetdnu,
          strava_cel+strava_cel_bl+strava_cel_bm AS strava_cel,
          strava_pol+strava_pol_bl+strava_pol_bm AS strava_pol,
          platba1,platba2,platba3,platba4,
            IFNULL((SELECT SUM(u_castka) FROM uhrada AS u 
              WHERE u.id_pobyt=p.id_pobyt AND u.u_stav IN (1,2,3) AND u.u_zpusob!=3 AND u_za=0),0) AS platba,
            IFNULL((SELECT SUM(u_castka) FROM uhrada AS u 
              WHERE u.id_pobyt=p.id_pobyt AND u.u_stav IN (1,2,3) AND u.u_zpusob!=3 AND u_za=1),0) AS platba_d,
            ( SELECT GROUP_CONCAT(DISTINCT DATE_FORMAT(u_datum,'%e/%c') SEPARATOR ', ') FROM uhrada AS u 
              WHERE u.id_pobyt=p.id_pobyt AND u_datum!='0000-00-00' AND u_zpusob!=3 AND u_stav!=4 AND u_za=0) AS datplatby,
            ( SELECT GROUP_CONCAT(DISTINCT DATE_FORMAT(u_datum,'%e/%c') SEPARATOR ', ') FROM uhrada AS u 
              WHERE u.id_pobyt=p.id_pobyt AND u_datum!='0000-00-00' AND u_zpusob!=3 AND u_stav!=4 AND u_za=1) AS datplatby_d,
          cd,p.poznamka, -- platba,datplatby,
          r.nazev as nazev,r.ulice,r.psc,r.obec,r.telefony,r.emaily,r.spz,
          IF(p.i0_rodina
            ,CONCAT(r.nazev,' ',GROUP_CONCAT(IF(role IN ('a','b'),o.jmeno,'') ORDER BY role SEPARATOR ' '))
            ,GROUP_CONCAT(DISTINCT CONCAT(o.prijmeni,' ',o.jmeno) SEPARATOR ' ')) as _jm,
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
          JOIN osoba AS o USING (id_osoba) 
          LEFT JOIN rodina AS r ON r.id_rodina=IF(i0_rodina,i0_rodina,id_rodina)
          LEFT JOIN tvori AS t USING (id_osoba,id_rodina) 
          WHERE p.id_akce='$akce' AND p.hnizdo=$tisk_hnizdo AND p.funkce NOT IN (9,10,13,14,15,99) AND $cond
          GROUP BY id_pobyt
          ORDER BY $ord
          -- LIMIT 3
      ";
//   $qry.=  " LIMIT 10";
  $res= pdo_qry($qry);
  while ( $res && ($x= pdo_fetch_object($res)) ) {
//                                         debug($x,"hodnoty");
    $n++;
    $clmn[$n]= array();
//     $DPH1= 0.1;
//     $DPH2= 0.2;
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
//         case '=ubyt':       $val= round($x->platba1/(1+$DPH1));
//                             $exp= "=ROUND([platba1,0]/(1+$DPH1),0)"; break;
//         case '=ubytDPH':    $val= round($x->platba1*$DPH1/(1+$DPH1));
//                             $exp= "=[platba1,0]-[=ubyt,0]"; break;
//         case '=strava':     $val= round($x->platba2/(1+$DPH2));
//                             $exp= "=ROUND([platba2,0]/(1+$DPH2),0)"; break;
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
        case '=jmena':      $val= $x->_jm; break;
        default:            $val= '???'; break;
        }
        $clmn[$n][$f]= $val;
        if ( $exp ) $expr[$n][$f]= $exp;
      }
      else {
        $val= $f ? $x->$f : '';
        if ( $f ) $clmn[$n][$f]= $val; else $clmn[$n][]= $val;
      }
      // případný výpočet sumy
      if ( isset($suma[$f]) && is_numeric($val)) {
         $suma[$f]+= $val;
      }
    }
  }
//                                         debug($clmn,"sestava pro $akce,$typ,$fld,$cnd");
//                                         debug($expr,"vzorce pro $akce,$typ,$fld,$cnd");
//                                         debug($suma,"sumy pro $akce B");
  // zobrazení tabulkou
  $tab= '';
  $ths= '';
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
# ---------------------------------------------------------------------------------- tisk2 vyp_excel
# generování tabulky do excelu
# tab.tits = názvy sloupců
# tab.flds = názvy položek
# tab.clmn = hodnoty položek
# tab.atrs = formáty
# tab.koef = pokud jsou, budou zaobrazeny pod sumy a pod nimi bude suma*koef
# tab.expr = vzorce
#    .DPH, .X = specifické tabulky
# pokud je $par->vertical==1 budou titulky vertikální
function tisk2_vyp_excel($akce,$par,$title,$vypis,$tab=null,$hnizdo=0) {  trace();
  global $xA, $xn, $tisk_hnizdo, $ezer_version;
  $tisk_hnizdo= $hnizdo;
  $result= (object)array('_error'=>0);
  $html= '';
  // získání dat
  $title= str_replace('&nbsp;',' ',$title);
  if ( !$tab )
    $tab= tisk2_sestava($akce,$par,$title,$vypis,true,$hnizdo);
//                                                    debug($tab,"tisk2_vyp_excel/tab");
  // nová hlavička
  $vertical= $tab->vertical==1 ? '::vert' : '';
  $Z= Excel5_n2col(count($tab->flds)-1);
  list($a_org,$a_misto,$a_druh,$a_od,$a_do,$a_kod)= 
      select("access,misto,IFNULL(zkratka,''),datum_od,datum_do,ciselnik_akce", //a IFNULL(g_kod,'')
        "akce "
//a          . "LEFT JOIN join_akce ON id_akce=id_duakce "
          . "LEFT JOIN _cis ON _cis.druh='ms_akce_typ' AND data=akce.druh",
        "id_duakce=$akce");
  $a_co= ($a_org==1?'YMCA Setkání, ':($a_org==2?'YMCA Familia, ':'')).$a_druh;
  $a_oddo= datum_oddo($a_od,$a_do);
  $a_celkem= count($tab->clmn);
  // vlastní export do Excelu
  $name= cz2ascii("vypis_").date("Ymd_Hi");
  $xls= <<<__XLS
    |open $name
    |sheet vypis;;L;page
    |A1 $title          $a_kod::bold size=14 |A2 $vypis ::bold size=12
    |{$Z}1 $a_co ::bold right size=14
    |{$Z}2 $a_misto, $a_oddo ::bold size=14 right
    |A3 Celkem: $a_celkem ::bold
__XLS;
  // jsou koeficienty?
  $koefs= isset($tab->koef);
  // titulky a sběr formátů
  $fmt= $sum= $koef= array();
  $n= 4;
  $lc= 0;
  $clmns= $del= '';
  $xA= array();                                 // překladová tabulka: název sloupce => písmeno
  if ( $tab->flds ) foreach ($tab->flds as $f) {
    $A= Excel5_n2col($lc);
    $xA[$f]= $A;
    if ($koefs && isset($tab->koef[$f])) {
      $koef[$A]= $tab->koef[$f];
    }
    
    $lc++;
  }
  $lc= 0;
  if ( $tab->tits ) foreach ($tab->tits as $idw) {
    if ( $idw=='^' ) continue;
    $A= Excel5_n2col($lc);
    // název sloupce : šířka : formát : suma
    list($id,$w,$f,$s)= array_merge(explode(':',$idw),array_fill(0,4,''));      
    if ( $f ) $fmt[$A]= $f;
    if ( $s ) $sum[$A]= true;
    $xls.= "|$A$n $id $vertical";
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
          case 't':                      $format.= ' text'; break;
          }
        }
      }
      if (isset($tab->atrs[$i][$id]) ) {
        // buňka má nastavený formát
        $format.= ' '.$tab->atrs[$i][$id];
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
    $nn= $n;
    $ns= $n+2;
    $nf= $n+3;
    $nm= $n+4;
    $xls.= "\n|A$ns součty sloupců :: right bcolor=ffdddddd";
    foreach ($sum as $A=>$x) {
      $xls.= "|$A$ns =SUM($A$n1:$A$nn) :: bcolor=ffdddddd";
    }
    // koeficienty
    if ($koefs) {
      $xls.= "\n|A$nf jednotková cena :: right bcolor=ffdddddd";
      $xls.= "\n|A$nm cena po sloupcích :: right bcolor=ffdddddd";
      foreach ($sum as $A=>$x) {
        if (isset($koef[$A])) {
          $xls.= "|$A$nf {$koef[$A]}";
          $xls.= "|$A$nm =$A$nf*$A$ns  :: bcolor=ffdddddd";
        }
      }
    }
  }
  if ($koef) $n+= 2;
  // tabulka DPH, pokud je
  if ( isset($tab->DPH) ) {
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
  if ( isset($tab->X) ) {
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
  $n+= 4;
  $xls.= "|A$n Výpis byl vygenerován $kdy :: italic";
  // konec
  $xls.= <<<__XLS
    \n|close
__XLS;
  // výstup
//  /**/                                                              display($xls);
  require_once "ezer$ezer_version/server/vendor/autoload.php";
  $inf= Excel2007($xls,1);
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
# =====================================================================================> . PDF tisky
# ----------------------------------------------------------------------------------- tisk2 pdf_mrop
# vygenerování PDF s vizitkami s rozměrem 55x90 na rozstříhání
#   $the_json obsahuje  title:'{jmeno}<br>{prijmeni}'
function tisk2_pdf_mrop($akce,$par,$title,$vypis,$report_json) {  trace(); debug($par,'tisk2_pdf_mrop');
  global $ezer_path_docs;
  $result= (object)array('_error'=>0);
  $html= '';
  // získání dat
  mb_internal_encoding('UTF-8');
  $tab= tisk2_sestava($akce,$par,$title,$vypis,true);
//                                         display($report_json);
//                                        debug($tab,"tisk2_sestava($akce,...)"); //return;
//   $report_json= "
//   {'format':'A4:5,6,70,41','boxes':[
//   {'type':'text','left':2.6,'top':11,'width':60,'height':27.3,'id':'jmeno','txt':'{pr_jm}','style':'16,C'},
//   {'type':'text','left':10,'top':20,'width':15,'height':10,'id':'$100','txt':'skupina','style':'8,C'},
//   {'type':'text','left':10,'top':25,'width':15,'height':20,'id':'skupina','txt':'{skupina}','style':'14,C'},
//   {'type':'text','left':40,'top':20,'width':10,'height':10,'id':'$101','txt':'chata','style':'8,C'},
//   {'type':'text','left':40,'top':25,'width':10,'height':20,'id':'chata','txt':'{chata}','style':'14,C'}]}";
  // projdi vygenerované záznamy
  $n= 0;
  $parss= array();
  foreach ( $tab->clmn as $xa ) {
    // definice pole substitucí
    $x= (object)$xa;
    $parss[$n]= (object)array();
    $parss[$n]->jmena=   strtr($x->jmena,array(','=>'<br>'));
    $parss[$n]->pr_jm=   $x->pr_jm;
    $parss[$n]->chata=   $x->pokoj;
    $parss[$n]->skupina= $x->skupina;
    $parss[$n]->jmeno=   $x->jmeno;
    $parss[$n]->prijmeni=$x->prijmeni;
    $n++;
  }
  // předání k tisku
  $fname= 'jmenovky_'.date("Ymd_Hi");
  $fpath= "$ezer_path_docs/$fname.pdf";
  $err= dop_rep_ids($report_json,$parss,$fpath);
  $result->html= $err ? $err
    : " Výpis byl vygenerován ve formátu <a href='docs/$fname.pdf' target='pdf'>PDF</a>.";
  return $result;
}
# ------------------------------------------------------------------------------- tisk2 pdf_jmenovky
# vygenerování PDF s vizitkami s rozměrem 55x90 na rozstříhání
#   $the_json obsahuje  title:'{jmeno}<br>{prijmeni}'
function tisk2_pdf_jmenovky($akce,$par,$title,$vypis,$report_json,$hnizdo) {  trace();
  global $ezer_path_docs;
  $result= (object)array('_error'=>0);
  $html= '';
  // získání dat
  mb_internal_encoding('UTF-8');
  $tab= tisk2_sestava($akce,$par,$title,$vypis,true,$hnizdo);
//                                         display($report_json);
//                                         debug($tab,"tisk2_sestava($akce,...)"); //return;
  $report_json= 
    '{"format":"A4:15,10,90,55",
      "boxes":[
        {"type":"text",
         "left":0,"top":0,"width":90,"height":55,
         "id":"ram","style":"1,L,LTRB:0.4 dotted","txt":" "
        },
        {"type":"text",
         "left":10,"top":10,"width":80,"height":40,
         "id":"jmeno","txt":"{jmeno}<br>{prijmeni}","style":"30,L"
        }
      ]
    }';
//                                            display($report_json);
  // projdi vygenerované záznamy
  $n= 0;
  $parss= array();
  foreach ( $tab->clmn as $xa ) {
    // definice pole substitucí
    $x= (object)$xa;
    $parss[$n]= (object)array();
    $fsize= mb_strlen($x->jmeno)>9 ? 12 : (mb_strlen($x->jmeno)>8 ? 13 : 14);
    $parss[$n]->jmeno= "<span style=\"font-size:{$fsize}mm;font-weight:bold\">{$x->jmeno}</span>";
    $prijmeni= $x->prijmeni;
//     list($prijmeni)= explode(' ',$x->prijmeni);
    $fsize= mb_strlen($prijmeni)>10 ? 10 : 12;
    $parss[$n]->prijmeni= "<span style=\"font-size:{$fsize}mm;font-weight:bold\">{$prijmeni}</span>";
    $n++;
  }
  // předání k tisku
  $fname= 'jmenovky_'.date("Ymd_Hi");
  $fpath= "$ezer_path_docs/$fname.pdf";
  $err= dop_rep_ids($report_json,$parss,$fpath);
  $result->html= $err ? $err
    : " Výpis byl vygenerován ve formátu <a href='docs/$fname.pdf' target='pdf'>PDF</a>.";
  return $result;
}
# --------------------------------------------------------------------------------- akce2 pdf_stitky
# vygenerování PDF se samolepkami - adresními štítky
#   $the_json obsahuje  title:'{jmeno_postovni}<br>{adresa_postovni}'
function akce2_pdf_stitky($akce,$par,$report_json,$hnizdo) { trace();
  global $ezer_path_docs, $tisk_hnizdo;
  $tisk_hnizdo= $hnizdo;
  $ret= (object)array('_error'=>0,'html'=>'testy');
  $par->fld= "prijmeni,rodice,ulice,psc,obec,stat";
  // projdi požadované adresy rodin
  $tab= tisk2_sestava_pary($akce,$par,'PDF','$vypis',true);
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
  $err= dop_rep_ids($report_json,$parss,$fpath);
  $ret->html= $err ? $err
    : " Výpis byl vygenerován ve formátu <a href='docs/$fname.pdf' target='pdf'>PDF</a>.";
end:
  return $ret;
}
/*
# --------------------------------------------------------------------------------- akce2 pdf_stitky
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
  $res= pdo_qry($qry);
  while ( $res && ($x= pdo_fetch_object($res)) ) {
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
  $err= dop_rep_ids($report_json,$parss,$fpath);
  $result->html= $err ? $err
    : " Výpis byl vygenerován ve formátu <a href='docs/$fname.pdf' target='pdf'>PDF</a>.";
  return $result;
}
*/
# --------------------------------------------------------------------------------- tisk2 pdf_prijem
# generování štítků se stručnými informace k nalepení na obálku účastníka do PDF
# pokud jsou iregularity strav a dietní stravy, generuje se i přetokový soubor
function tisk2_pdf_prijem($akce,$par,$stitky_json,$popis_json,$hnizdo) {  trace();
  global $tisk_hnizdo;
  $tisk_hnizdo= $hnizdo;
  global $ezer_path_docs;
  $result= (object)array('_error'=>0);
  $popisy= false;
  // získání dat
  list($cenik_verze,$datum_od)= select('ma_cenik_verze,datum_od','akce',"id_duakce=$akce");
  $par->fld.= ",*strava";
  $clmn= tisk2_sestava_pary($akce,$par,'$title','$vypis',false,true);
//                                         debug($clmn,"tisk2_sestava_pary($akce,...)"); //return;
  // projdi vygenerované informace o pobytech
  $n= $n2= 0;
  $parss= $parss2= array();
  // omezení pro testy
  foreach ( $clmn as $xa ) {
    $idp= $xa['^id_pobyt'];
    $x= (object)$xa;
    // přehled stravování
    $_diety= $x->strava_cel_bm!=0  || $x->strava_pol_bm!=0 
          || $x->strava_cel_bl!=0  || $x->strava_pol_bl!=0;
    // výpočet strav včetně přetokového souboru na iregularity a diety
    if ( $x->_vyjimky || $_diety ) {
      $par->souhrn= 1;
      if ( !$x->_vyjimky ) {
        // pravidelná strava s dietami
        $strava= "strava: ";
        if ( $x->strava_cel || $x->strava_pol )
          $strava.= " <b>{$x->strava_cel}/{$x->strava_pol}</b>";
        if ( $x->strava_cel_bm || $x->strava_pol_bm )
          $strava.= " veget. <b>{$x->strava_cel_bm}/{$x->strava_pol_bm}</b>";
        if ( $x->strava_cel_bl || $x->strava_pol_bl )
          $strava.= " bezlep. <b>{$x->strava_cel_bl}/{$x->strava_pol_bl}</b>";
      }
      else { // $x->_vyjimky
        $ret= (object)['days'=>[],'suma'=>[]];
        if ($cenik_verze==2) {
//          $as= ['S'=>'s','O'=>'o','V'=>'v'];
          $as= [0,'s','o','v'];
          $ap= ['C'=>'c','P'=>'p'];
          $ad= ['-'=>'','BL'=>'_bl'];
          $xx= '*strava';
          foreach ($x->$xx as $den=>$jpd) {
            $dat= date('j/n', strtotime("$datum_od+$den days"));
            $ret->days[]= $dat;
            foreach ($jpd as $j=>$pd) {
              foreach ($pd as $p=>$dn) {
                foreach ($dn as $d=>$jpdn) {
                  if ($jpdn) {
                    $row= "$dat {$as[$j]}{$ap[$p]}{$ad[$d]}";
                    $ret->suma[$row]= $jpdn;
                  }
                }
              }
            }
          }
//                                                   debug($ret,"výjimky v.2");
        }
        else {
        // nepravidelná strava příp. žádná ... počítáme z vydaných stravenek, pole dieta ignorujeme
          $ret= akce2_strava($akce,(object)array(),'','',true,0,$idp);
//                                                   debug($ret,"výjimky v.1");
//                                                   display("$h");
        }
        $popisy= true;
        $h= tisk2_pdf_prijem_ireg("{$x->prijmeni}: {$x->jmena}",$x,$ret,$par);
        if ( $h ) {
          $strava= "strava: viz popis v obálce";
          $parss2[$n2]= (object)array();
          $parss2[$n2]->tab= $h;
          $n2++;
        }
        else {
          $strava= "strava: neobjednána";
        }
      }
    }
    else {
      // normální pravidelná strava (bez diet)
      $strava= $x->strava_cel || $x->strava_pol ? ( "strava: "
             . ($x->strava_cel?"celá <b>{$x->strava_cel}</b> ":'')
             . ($x->strava_pol?"poloviční <b>{$x->strava_pol}</b>":'')) : "bez stravy";
    }
    // přehled ubytování
    if ($cenik_verze==2) {
      $ubytovani= $x->luzka || $x->spacaky || $x->nazemi ? (
                     ($x->luzka?"lůžka <b>{$x->luzka}</b> ":'')
                   . ($x->spacaky?"spacáky <b>{$x->spacaky} </b>":'')
                   . ($x->nazemi?"bez lůžka <b>{$x->nazemi}</b>":'')
                     ) : "bez ubytování";
    }
    else {
      $ubytovani= $x->luzka || $x->pristylky || $x->kocarek ? (
                     ($x->luzka?"lůžka <b>{$x->luzka}</b> ":'')
                   . ($x->pristylky?"přistýlky <b>{$x->pristylky} </b>":'')
                   . ($x->kocarek?"kočárek <b>{$x->kocarek}</b>":'')
                     ) : "bez ubytování";
    }
    // definice pole substitucí
    $parss[$n]= (object)array();
    $parss[$n]->line1= "<b>{$x->prijmeni}: {$x->jmena}</b>";
    $parss[$n]->line2= ($x->pokoj?"pok. <b>{$x->pokoj}</b> ":'')
                     . ($x->skupina?"skup. <b>{$x->skupina}</b>":'');
    $parss[$n]->line3= $ubytovani;
    $parss[$n]->line4= $strava;
    $n++;
  }
//                                                      debug($parss,"pro tisk");
  // předání k tisku
  $fname= 'stitky_'.date("Ymd_Hi");
  $fpath= "$ezer_path_docs/$fname.pdf";
  $err= dop_rep_ids($stitky_json,$parss,$fpath);
  $result->html= $err ? $err
    : " Výpis byl vygenerován ve formátu <a href='docs/$fname.pdf' target='pdf'>PDF</a>.";
  if ( $popisy ) {
    $fname2= 'popisy_'.date("Ymd_Hi");
    $fpath2= "$ezer_path_docs/$fname2.pdf";
    $err= dop_rep_ids($popis_json,$parss2,$fpath2);
    $result->html.= $err ? $err
      : " a doplněn popisy do obálek ve formátu <a href='docs/$fname2.pdf' target='pdf'>PDF</a>.";
//    $result->html.= "<br><br><br><br><br><br><br><br><br><br><br><br><br><br>$h";
  }
end:
  return $result;
}
# ------------------------------------------------------- tisk2 pdf_prijem_ireg
# generování tabulky s nepravidelnou stravou
# pokud jsou snídaně jen celé (snidane=c) píší se do jediného sloupce
function tisk2_pdf_prijem_ireg($nazev,$x,$ret,$par) {  trace();
//  global $diety,$diety_,$jidlo_;
 $diety= array('','_bl');  // postfix položek strava_cel,cstrava_cel,strava_pol,cstrava_pol
 $diety_= array(''=>'normal','_bl'=>'bezlep');
// bezmasá dieta na Pavlákové od roku 2017 není (ale pro Pražáky je)
//$diety= array('','_bl');  // postfix položek strava_cel,cstrava_cel,strava_pol,cstrava_pol
//$diety_= array(''=>'normal','_bl'=>'bezlep');
$jidlo_= array('sc'=>'snídaně celá','sp'=>'snídaně dětská','oc'=>'oběd celý',
               'op'=>'oběd dětský','vc'=>'večeře celá','vp'=>'večeře dětská');
  $jidel= 0;
  $jidla= explode(',',$par->snidane=='c' ? 'sc,oc,op,vc,vp' : 'sc,sp,oc,op,vc,vp');
  $s= " style=\"background-color:#DDDDDD\"";
  $h= "<h3>$nazev</h3>";
  $h.= '<small><table border="1" cellpadding="2" cellspacing="0" align="center">';
  // nadhlavička
  $chodu= $par->snidane=='c' ? 5 : 6;
  $h.= "<tr><th$s>dieta:</th>";
  foreach ($diety as $dieta) {
    $h.= "<th$s colspan=\"$chodu\">{$diety_[$dieta]}</th>";
  }
  $h.= "</tr>";
  // hlavička
  $h.= "<tr><th$s>den</th>";
  foreach ($diety as $dieta) {
    foreach ($jidla as $jidlo) {
      $chod= $jidlo=='sc' && $par->snidane=='c' ? 'snídaně' : $jidlo_[$jidlo];
      $h.= "<th$s>$chod</th>";
    }
  }
  $h.= "</tr>";
  // dny
  foreach ($ret->days as $day) {
    $h.= "<tr><th$s>$day</th>";
    foreach ($diety as $dieta) {
      foreach ($jidla as $jidlo) {
        $fld= "$day $jidlo$dieta";
        $suma= $ret->suma[$fld];
        if ( $jidlo=='sc' && $par->snidane=='c' ) {
          // pokud jsou snídaně jen celé, přičti objednávky polovičních
          $fld= "{$day}sp $dieta";
          $suma+= $ret->suma[$fld];
        }
        $pocet= $suma ?: '';
        $jidel+= $suma;
        $h.= "<td>$pocet</td>";
      }
    }
    $h.= "</tr>";
  }
  // konec
  $h.= "</table></small>";
  return $jidel ? $h : ''; // "<h3>$nazev</h3> bez stravy";
}
# -------------------------------------------------------------------------------- tisk2 pdf_plachta
# generování štítků se jmény párů
# mezery= řádků;i1+x1,i2+x2,... znamená, že po i-tém štítku bude x prázdných (i je výsledný index)
function tisk2_pdf_plachta($akce,$report_json=0,$hnizdo=0,$_mezery='') {  trace();
  global $ezer_path_docs,$tisk_hnizdo;
  $tisk_hnizdo= $hnizdo;
  setlocale(LC_ALL, 'cs_CZ.utf8');
  $result= (object)array('_error'=>0,'html'=>'?');
//  $_mezery= "4+1";
  $mezery= array();
  $radku= 14;
  if ($_mezery) {
    list($radku,$ixs)= explode(';',$_mezery);
    $ixs= explode(',',$ixs);
    foreach ($ixs as $ix) {
      list($i,$x)= explode('+',$ix);
      $mezery[$i]= $x;
    }
  }
//                                          debug($mezery,'mezery');
  $html= '';
  $A= 'A';
  $n= 1;
  $i= 0;
  // získání dat
  $tab= akce2_plachta($akce,'$par','$title','$vypis',0,$hnizdo);
  if (!isset($tab->pdf)) return $result;
  unset($tab->xhref);
  unset($tab->html);
//  ksort($tab->pdf,SORT_LOCALE_STRING);
//                                               debug($tab->pdf);

    foreach ( $tab->pdf as $par=>$xa ) {
      // započtení mezer, předaných přes $_mezery
      if (isset($mezery[$i])) $i+= $mezery[$i];
      $Ai= $i%$radku;
      $ni= ceil(($i+1)/$radku);
      $tab->pdf[$par]['a1']= chr(ord('A')+$Ai).$ni;
      $i++;
    }
    $result= tisk2_table(array('příjmení','jména','a1','účasti'),array('prijmeni','jmena','a1','ucasti'),$tab->pdf);


  // projdi vygenerované záznamy
  $n= 0;
  $i= 0;
  if ( $report_json) {
    $parss= array();
    foreach ( $tab->pdf as $par=>$xa ) {
      // započtení mezer, předaných přes $_mezery
      if (isset($mezery[$i])) $i+= $mezery[$i];
      $Ai= $i%$radku;
      $ni= ceil(($i+1)/$radku);
      $A1= chr(ord('A')+$Ai).$ni;
      $tab->pdf[$par]['a1']= $A1;
      $i++;
      // definice pole substitucí
      $x= (object)$xa;
      $parss[$n]= (object)array();  // {prijmeni}<br>{jmena}
      $prijmeni= $x->prijmeni;
      $ucasti= trim($x->ucasti);
      $len= mb_strlen($prijmeni);
      $xlen= round(tc_StringWidth($prijmeni,'B',15));
      $fs= 20;
      if ( in_array($prijmeni,array("Beszédešovi","Stanislavovi")) )
                          {     $fw= 'ultra-condensed'; }
      elseif ( $xlen<20 ) {     $fw= 'condensed'; }
      elseif ( $xlen<27 ) {     $fw= 'condensed'; }
      elseif ( $xlen<37 ) {     $fw= 'extra-condensed'; }
      else {                    $fw= 'ultra-condensed'; }
                                                display("$prijmeni ... $xlen / $fw");

      $s1= "font-stretch:$fw;font-size:{$fs}mm;font-weight:bold;text-align:center";
      $bg1= $x->vps=='* ' ? "background-color:gold" : ($x->vps=='+ ' ? "background-color:lightblue" : '');
      $s2= "font-size:5mm;text-align:center";
      $bg2= '';
      $s3= "font-size:8mm;font-weight:bold;text-align:right";
      $bg3= !$ucasti ? "background-color:lightgreen" : (
        $ucasti=='1x' || $ucasti=='2x' ? "background-color:orange"
        : "background-color:silver");
      $parss[$n]->prijmeni= "<span style=\"$s1;$bg1\">$prijmeni</span>";
      $parss[$n]->jmena= "<span style=\"$s3;$bg3\">$A1</span>&nbsp;&nbsp;&nbsp;&nbsp;"
                       . "<span style=\"$s2;$bg2\"><br>{$x->jmena}</span>";
      $n++;
    }
    // předání k tisku
    $fname= 'stitky_'.date("Ymd_Hi");
    $fpath= "$ezer_path_docs/$fname.pdf";
    $err= dop_rep_ids($report_json,$parss,$fpath);
    $html= $err ? $err
      : " Výpis byl vygenerován ve formátu <a href='docs/$fname.pdf' target='pdf'>PDF</a>.";
    $result->html= $html.$result->html;
  }
  else {
//    foreach ( $tab->pdf as $par=>$xa ) {
//      // započtení mezer, předaných přes $_mezery
//      if (isset($mezery[$i])) $i+= $mezery[$i];
//      $Ai= $i%$radku;
//      $ni= ceil(($i+1)/$radku);
//      $tab->pdf[$par]['a1']= chr(ord('A')+$Ai).$ni;
//      $i++;
//    }
//                                                     debug($tab->pdf);
//    $result= tisk2_table(array('příjmení','jména','a1','účasti'),array('prijmeni','jmena','a1','ucasti'),$tab->pdf);
  }
end:
  return $result;
}
# -------------------------------------------------------------------------------------- dop rep_ids
# LOCAL
# vytvoření dopisů se šablonou pomocí TCPDF podle parametrů
# $parss  - pole obsahující substituce parametrů pro $text
# vygenerované dopisy ve tvaru souboru PDF se umístí do ./docs/$fname
# případná chyba se vrátí jako Exception
function dop_rep_ids($report_json,$parss,$fname) { trace();
//  global $json;
  $err= 0;
  // transformace $parss pro strtr
  $subst= array();
  for ($i=0; $i<count($parss); $i++) {
    $subst[$i]= array();
    foreach($parss[$i] as $x=>$y) {
      $subst[$i]['{'.$x.'}']= $y;
    }
  }
  $report= json_decode(str_replace("'",'"',$report_json));
  if ( json_last_error() ) {
    $err= json_last_error_msg();
    display($err);
  }
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
  try {
    tc_report($report,$texty,$fname);
  }
  catch (Exception $e) {
    $err= $e->getMessage();
  }
  return $err;
}
