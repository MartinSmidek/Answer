<?php

# ---------------------------------------------------------------------------------- akce2 zmeny_web
# vrácení položek daného pobytu u kterých došlo ke změně uživatelem WEB
function akce2_zmeny_web($idp) {  trace();
  // získání sledovaných klíčů tabulek spolu, osoba, tvori, rodina
  $n= 0;
  $keys= (object)array('osoba'=>array(),'tvori'=>array(),'spolu'=>array()); // table -> [id_table]
  $flds= (object)array();
  $idr= 0;
  $rp= pdo_qry("
    SELECT id_rodina,id_tvori,o.id_osoba,t.id_osoba,id_spolu
    FROM pobyt AS p
    JOIN akce ON id_akce=id_duakce
    JOIN spolu AS s USING (id_pobyt)
    JOIN osoba AS o ON s.id_osoba=o.id_osoba
    LEFT JOIN tvori AS t ON t.id_rodina=i0_rodina
    WHERE id_pobyt=$idp
  ");
  while ( $rp && (list($_idr,$idt,$ido1,$ido2,$ids)= pdo_fetch_array($rp)) ) {
    if (!in_array($ido1,$keys->osoba)) $keys->osoba[]= $ido1;
    if ($ido2 && !in_array($ido2,$keys->osoba)) $keys->osoba[]= $ido2;
    if (!in_array($idt,$keys->tvori)) $keys->tvori[]= $idt;
    if (!in_array($ids,$keys->spolu)) $keys->spolu[]= $ids;
//    $keys->rodina= $idr;
    $idr= $_idr;
  }
  $idos= implode(',',$keys->osoba);
  $idts= implode(',',$keys->tvori);
  $idss= implode(',',$keys->spolu);
//                                                         debug($keys,'klíče');
  // projití _track - zjištění vzniku pobytu
  $start= select('kdy','_track',"kde='pobyt' AND klic='$idp' ORDER BY kdy LIMIT 1");
  // posbírání pozdějších změn 
  $n= 0;
  $rt= pdo_qry("SELECT kde,klic,fld,GROUP_CONCAT(kdo ORDER BY kdy DESC) FROM _track
      WHERE kdy>='$start' AND (
        (kde='pobyt' AND klic=$idp AND fld NOT IN ('id_akce','i0_rodina','web_zmena','web_changes','web_json') )"
       . ($idr  ? " OR (kde='rodina' AND klic=$idr AND fld NOT IN ('access','web_zmena') )" : '')
       . ($idos ? " OR (kde='osoba' AND klic IN ($idos) AND fld NOT IN ('access','web_zmena') )" : '')
       . ($idts ? " OR (kde='tvori' AND klic IN ($idts) AND fld NOT IN ('id_rodina','id_osoba') )" : '')
       . ($idss ? " OR (kde='spolu' AND klic IN ($idss) AND fld NOT IN ('id_pobyt','id_osoba') )" : '')
      .")
      GROUP BY kde,klic,fld  "
      );
  while ( $rt && (list($kde,$klic,$fld,$kdo)= pdo_fetch_array($rt)) ) {
    if (substr($kdo,0,3)!='WEB') continue; // barvíme jen změny z webu
    switch ( $kde ) {
    case 'pobyt':  $flds->pobyt[$klic][]= $fld; $n++; break;
    case 'rodina': $flds->rodina[$klic][]= $fld; $n++; break;
    case 'osoba':  $flds->osoba[$klic][]= $fld; $n++; break;
    case 'tvori':  $flds->tvori[$klic][]= $fld; $n++; break;
    case 'spolu':  $flds->spolu[$klic][]= $fld; $n++; break;
    }
  }
                                        debug($flds,"'položky - $n změn");
  return $flds;
}
# --------------------------------------------------------------------------------- akce2 platby_xls
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
  $ra= pdo_qry($qa);
  if ( !$ra || !pdo_num_rows($ra) ) {
    $ret->msg.= "akce {$pobyt->id_akce} nemá cenový vzorec";
    goto end;
  }
  while ( $ra && ($a= pdo_fetch_object($ra)) ) {
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
    $res= pdo_qry($qry);
    while ( $res && $t= pdo_fetch_object($res) ) {
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
# --------------------------------------------------------------------------------- akce2 auto_akceL
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
  $res= pdo_qry($qry);
  while ( $res && $p= pdo_fetch_object($res) ) {
    $nazev= $p->_muz && $p->_zena
      ? "{$p->_nazev} {$p->_muz} a {$p->_zena}"
      : ( $p->_muz ? "{$p->_muzp} {$p->_muz}" : "{$p->_zenap} {$p->_zena}" );
    $pary[]= (object)array(
      'nazev'=>$nazev,'muz'=>$p->_muz_id,'zen'=>$p->_zena_id,'rod'=>$p->id_rodina);
  }
//                                                                 debug($pary,$id_akce);
  return $pary;
}
# ---------------------------------------------------------------------------------- akce2 auto_pece
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
  $res= pdo_qry($qry);
  while ( $res && $t= pdo_fetch_object($res) ) {
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
# ---------------------------------------------------------------------------------- akce2 prihlasky
# přehled přihlášek na akci
function akce2_prihlasky($akce,$par,$title,$vypis,$export=false) { 
  global $EZER;
  $res= (object)array('html'=>'');
  
  $limit= ''; // "LIMIT 1";
  $po= isset($par->po) ? $par->po : 7;
  $tydnech= "týdnech";
  $tydnu= "týdnu";
  $tyden= "týden";
  if ($po==30) {
    $tydnech= "měsících";
    $tydnu= "měsíci";
    $tyden= "měsíc";
  }
  $dny_a= $dny_b= $dny_x= array(); 
  $max= 0;
  $pob= 0;
  $qp=  "SELECT id_pobyt,funkce,
       (SELECT DATEDIFF(datum_od,kdy) FROM _track 
        WHERE kde='pobyt' AND klic=id_pobyt ORDER BY id_track LIMIT 1) AS kde
    FROM pobyt JOIN akce ON id_akce=id_duakce
    WHERE id_akce='$akce' AND funkce!=99 $limit ";
  $rp= pdo_qry($qp);
  while ( $rp && (list($idp,$fce,$dif)= pdo_fetch_row($rp)) ) {
    $pob++;
    $max= max($dif,$max);
    if ($fce==1) {
      if (!isset($dny_a[$dif])) $dny_a[$dif]= 0;
      $dny_a[$dif]++;
    }
    elseif (in_array($fce,array(9,10,13,14,15))) {
      if (!isset($dny_x[$dif])) $dny_x[$dif]= 0;
      $dny_x[$dif]++;
    }
    else {
      if (!isset($dny_b[$dif])) $dny_b[$dif]= 0;
      $dny_b[$dif]++;
    }
  }
  ksort($dny_a);
  ksort($dny_b);
  ksort($dny_x);
  // zhuštění výsledku
  $na= $nb= $nx= 0;
  $hist_a= $hist_b= $hist_x= array();
  $last_h= -1;
  for ($d= 0; $d<=$max; $d++) {
    $ya= isset($dny_a[$d]) ? $dny_a[$d] : 0;
    $yb= isset($dny_b[$d]) ? $dny_b[$d] : 0;
    $yx= isset($dny_x[$d]) ? $dny_x[$d] : 0;
    $na+= $ya;
    $nb+= $yb;
    $nx+= $yx;
    $h= floor($d/$po);
//    display("$d / $po = $h");
    if ($h==$last_h) {
      $hist_a[$h]+= $ya;
      $hist_b[$h]+= $yb;
      $hist_x[$h]+= $yx;
    }
    else {
      $last_h= $h;
      $hist_a[$h]= $ya;
      $hist_b[$h]= $yb;
      $hist_x[$h]= $yx;
    }
  }
  // integrál
  $hist_z= array(0,0);
  $hist[count($hist_a)]= 0;
  for ($h= count($hist_a)-1; $h>=0; $h--) {
    $hist_z[$h]+= $hist_z[$h+1]+$hist_a[$h]+$hist_b[$h]+$hist_x[$h];
  }
    /**/                                                 debug($hist_z,'integrál');
//    /**/                                                 debug($hist_a,'funkce');
//    /**/                                                 debug($hist_b,'bez funkce');
//    /**/                                                 debug($dny_a,'funkce');
//    /**/                                                 debug($dny_b,'bez funkce');
  
  // výsledek
  $res->html= "<h3>Viz grafické znázornění na Databáze / Grafy / Infografika LK MS </h3>
      <h3>Přehled data zápisu $pob přihlášek na akci</h3>    
      <i>přehled se zobrazuje podle <u>dne zapsání</u> přihlášky v součtu po $tydnech, vlevo je $tyden konání akce
      <br>zeleně jsou účastníci bez funkce ($nb), oranžově jsou VPS $na, černě jsou ti, co na akci nakonec nebyli ($nx)
      </i><br><br>";
  $x= $y= $z= '';
  $ratio= 5;
  for ($h= 1; $h<count($hist_a); $h++) {
    $xx= $h<10 ? "0$h" : $h;
    $ya= isset($hist_a[$h]) ? $hist_a[$h] : 0;
    $yb= isset($hist_b[$h]) ? $hist_b[$h] : 0;
    $yx= isset($hist_x[$h]) ? $hist_x[$h] : 0;
    $pocty= "$yb<br>$yx<br>$ya";
    $ya*= $ratio;
    $yb*= $ratio;
    $yx*= $ratio;
    $img= "<div class='curr_akce' style='height:{$yb}px;width:12px;margin-top:5px'></div>";
    $img.= "<div style='background:black;height:{$yx}px;width:12px;margin-top:5px'></div>";
    $img.= "<div class='parm' style='height:{$ya}px;width:12px;margin-top:5px'></div>";
    $x.= "<td>$xx</td>";
    $y.= "<td style='vertical-align:bottom'>$pocty $img </td>";
    $z.= "<td>{$hist_z[$h]}</td>";
  }
  $res->html.= "<table>
    <tr><td align='right' style='vertical-align:bottom'>v $tydnu zapsáno:<br></td>$y</tr>
    <tr><td align='right'>n-tý $tyden před akcí:</td>$x</tr>
    <tr><td align='right'>celkem přihlášek:</td>$z</tr></table>";
  return $res;
}
# ------------------------------------------------------------------------------------- akce2 pokoje
# odhad počtu potřebných pokojů, založený na následujících úvahách
#  - rodiny tj. "pobyty" spolu nesdílí pokoje
#  - dítě do 3 let spí ve své postýlce v pokoji rodičů
#  - dvě děti mezi 3-6 lety spolu mohou sdílet jednu dospělou postel 
#    $par->max_vek_spolu přitom určuje maximální věk dítětě, které se snese s mladším na lůžku
function akce2_pokoje($akce,$par,$title,$vypis,$export=false) { 
  global $EZER;
  // ofsety v atributech členů pobytu
  global $i_osoba_jmeno, $i_osoba_vek, $i_osoba_role, $i_osoba_prijmeni, $i_adresa, 
      $i_osoba_kontakt, $i_osoba_telefon, $i_osoba_email, $i_osoba_note, $i_key_spolu, 
      $i_spolu_note, $i_osoba_obcanka, $i_spolu_dite_kat, $i_osoba_dieta;
//                                            debug($par,"akce2_pokoje");
  $max_vek_spolu= isset($par->max_vek_spolu) ? $par->max_vek_spolu : 0;
  $res= (object)array('html'=>'');
  $org= select1("access","akce","id_duakce=$akce");
  $c_dite_lcd= $org==2
      ? map_cis('fa_akce_dite_kat','barva') 
      : map_cis('ys_akce_dite_kat','barva');  
  # diskuse souběhu: 0=normální akce, 1=hlavní akce, 2=souběžná akce
  list($hlavni,$soubezna)= select("a.id_hlavni,IFNULL(s.id_duakce,0)",
      "akce AS a LEFT JOIN akce AS s ON s.id_hlavni=a.id_duakce",
      "a.id_duakce=$akce");
  $soubeh= $soubezna ? 1 : ( $hlavni ? 2 : 0);
  $browse_par= (object)array(
    'cmd'=>'browse_load',
    'cond'=>"p.id_akce=$akce AND p.funkce NOT IN (9,10,13,14,15)",  // jen přítomní
//    'having'=>$hav,
    'order'=>'a__nazev',
    'sql'=>"SET @akce:=$akce,@soubeh:=$soubeh,@app:='{$EZER->options->root}';");
  $y= ucast2_browse_ask($browse_par,true);
//  /**/                                                   debug($y);
  # rozbor výsledku browse/ask
  array_shift($y->values);
  $pokoj= array();
  $celkem= 0;
  foreach ($y->values as $x) {
    $xs= explode('≈',$x->r_cleni);
//    /**/                                                 debug($x);
    $pocet= 0;
    $deti= array();
    $male= 0;
    $celkem++;
//                                                         if ( $x->key_pobyt==32146 ) debug($x);
    foreach ($xs as $i=>$xi) {
      $o= explode('~',$xi);
//    /**/                                                 debug($o);
//                                                         if ( $x->key_pobyt==32146 ) debug($o,"xi/$i");
      if ( $o[$i_key_spolu] ) {
        $pocet++;
        if ( $o[$i_osoba_role]=='d' ) {
          $deti[$i]['jmeno']= $o[$i_osoba_jmeno];
          $vek= $deti[$i]['vek']= $o[$i_osoba_vek];
          $deti[$i]['lcd']= $c_dite_lcd[$o[$i_spolu_dite_kat]]; 
          // dítě do 3 let nepotřebuje postel
          if ($vek<3) 
            $pocet--;
          elseif ($vek<$max_vek_spolu) {
            $male++;
          }
        }
      }
    }
//    /**/                                                 debug($deti,"DĚTI, $male");
    // rozbor případů
    $posteli= $pocet;
    if ($male>=2) $posteli--;
    /**/                                                 display("$posteli - $x->_nazev");
    if (!isset($pokoj[$posteli])) $pokoj[$posteli]= 0;
    $pokoj[$posteli]++;
  }
  // výsledek
  $sdileni= $max_vek_spolu 
      ? "<li><i>dvě děti mezi 3 a $max_vek_spolu lety budou sdílet dospělé lůžko (ale ne vždy to jde)</i>" 
      : '';
  $res->html.= "<h3>Hrubý odhad počtu pokojů pro akci</h3>
    Za předpokladu, že:<ul>
    <li> rodiny nesdílí pokoje (někdy ale starší děti ze spřátelených rodin chtějí)
    <li> dítě do 3 let spí v dovezené postýlce s rodiči
    $sdileni
    </ul>
    tak potřebujeme celkem <b>$celkem</b> pokojů v těchto velikostech:<br><br><table>
    ";
  ksort($pokoj);
  foreach ($pokoj as $posteli=>$pocet) {
    $res->html.= "<tr><td align='right' style='width:30px'><b>$pocet</b></td>
      <td>&nbsp;&nbsp;&nbsp;$posteli-lůžkových pokojů</td></tr>";
  }
  $res->html.= "</table><br>Pochopitelně např. 5-lůžkový pokoj lze nahradit dvěma menšími ";
    /**/                                                 debug($pokoj,"pokoje");
  return $res;
}
# ------------------------------------------------------------------------------ akce2 cerstve_zmeny
# generování seznamu změn v pobytech na akci od par-datetime
function akce2_cerstve_zmeny($akce,$par,$title,$vypis,$export=false) { 
//                                            debug($par,"akce2_cerstve_zmeny");
  $result= (object)array('html'=>'');
//  $od= $par->datetime= "2013-09-25 18:00";
  $od= '';
  if ( $par->zmeny ) {
    $delta= strtotime("$par->zmeny hours ago");
    $od= date("Y-m-d H:i",$delta);
  }
//  $p_flds= "'luzka','pokoj','pristylky','pocetdnu'";
  $par->fld= 'luzka,kocarek,pokoj,budova,pristylky,pocetdnu';
  $p_flds= array();
  foreach (explode(',',$par->fld) as $fld) { $p_flds[]= "'$fld'"; }
  $p_flds= implode(',',$p_flds);
  $p_tabs= array();
  foreach (explode(',',$par->tab) as $tab) { $p_tabs[]= "'$tab'"; }
  $p_tabs= implode(',',$p_tabs);
  //
  $tits= explode(',',"_ucastnik,kdy,fld,old,val,kdo,id_track");
  $flds= $tits;
  $clmn= array();
  $n= 0;
  $ord= "IF(i0_rodina,r.nazev,o.prijmeni)";
//  $ord= "
//    CASE
//      WHEN pouze=0 THEN r.nazev
//      WHEN pouze=1 THEN GROUP_CONCAT(DISTINCT IF(t.role='a',o.prijmeni,'') SEPARATOR '')
//      WHEN pouze=2 THEN GROUP_CONCAT(DISTINCT IF(t.role='b',o.prijmeni,'') SEPARATOR '')
//    END";
  $AND_fld_in= $par->tab=='pobyt' ? "AND fld IN ($p_flds)" : "AND fld NOT IN ($p_flds)";
  $JOIN= $par->tab=='pobyt' ? "
    JOIN pobyt AS p ON p.id_pobyt=klic
    JOIN akce AS a ON a.id_duakce=p.id_akce
    JOIN spolu AS s USING(id_pobyt)
    JOIN osoba AS o ON s.id_osoba=o.id_osoba
    LEFT JOIN rodina AS r ON r.id_rodina=p.i0_rodina
    LEFT JOIN tvori AS t ON t.id_osoba=o.id_osoba AND t.id_rodina=r.id_rodina
    " : "
    ";
  $rz= pdo_qry("
    SELECT id_track,kdy,kdo,klic,fld,op,old,val,
      GROUP_CONCAT(DISTINCT IF(t.role='a',o.jmeno,'')    SEPARATOR '') as jmeno_m,
      GROUP_CONCAT(DISTINCT IF(t.role='b',o.jmeno,'')    SEPARATOR '') as jmeno_z,
      p.i0_rodina,r.nazev as nazev,o.prijmeni,o.jmeno
    FROM _track $JOIN
    WHERE kde IN ($p_tabs) AND kdy>='$od' AND id_akce=$akce $AND_fld_in AND old!=val -- AND old!=''
      AND NOT ISNULL(p.id_pobyt)
    GROUP BY id_track,id_pobyt
--  ORDER BY $ord,kdy
    ORDER BY kdy DESC
  ");
  while ( $rz && ($z= pdo_fetch_object($rz)) ) {
    $nazev= $z->i0_rodina ? "$z->nazev $z->jmeno_m a $z->jmeno_z" : "$z->prijmeni $z->jmeno";
//    $prijmeni= $z->pouze==1 ? $z->prijmeni_m : ($z->pouze==2 ? $z->prijmeni_z : $z->nazev);
//    $jmena=    $z->pouze==1 ? $z->jmeno_m    : ($z->pouze==2 ? $z->jmeno_z : "{$z->jmeno_m} a {$z->jmeno_z}");
    $clmn[$n]= array();
    foreach($flds as $f) {
      switch ($f) {
      case '_ucastnik': $clmn[$n][$f]= $nazev; break; // "$prijmeni $jmena"; break;
      default:          $clmn[$n][$f]= $z->$f;
      }
    }
    $n++;
  }
//                                         debug($clmn,"sestava pro $akce,$typ,$fld,$cnd");
  $bylo= $od ? "Od $od (podle nastavení na kartě Účastníci/obarvit změněné údaje) bylo " : "Bylo";
//  $kde= $p_tabs=='pobyt'
  $nadpis= "$bylo provedeno $n změn ... nahoře jsou nejnovější
    <hr>sledují se změny jen v položkách: <b>$par->fld</b>
    ... podle potřeby to lze změnit - řekni Martinovi<br><br>
    ";
//  $nadpis= "$bylo provedeno $n změn
//    <hr>sledují se jen <b>změny</b> (tzn. nikoliv nově zadané hodnoty) v položkách: $par->fld
//    <br>.. podle přání lze toto změnit - řekni Martinovi<br><br>
//    ";
  $result= tisk2_table($tits,$flds,$clmn,$export,$nadpis);
  return $result;
}
# ====================================================================================> . sta2 mrop
# tabulka struktury účastníků MROP
function sta2_mrop($par,$export=false) {
  $msg= "";
  $msg.= sta2_mrop_vek($par);
  $msg.= sta2_mrop_vliv($par);
  return $msg;
}
# --------------------------------------------------------------------------------- sta2 excel_subst
function sta2_sestava_adresy_fill($matches) { trace();
  global $xA, $xn;
//                                                 debug($xA);
//                                                 debug($matches);
  if ( !isset($xA[$matches[1]]) ) fce_error("sta2_excel_subst: chybný název sloupce '{$matches[1]}'");
  $A= $xA[$matches[1]];
  $n= $xn+$matches[2];
  return "$A$n";
}
# ------------------------------------------------------------------------------------- mail2 footer
function mail2_footer($op,$access,$access_name,$idu,$change='') { trace();
//  global $json;
  $ans= '';
  $org= $access_name->$access;
  $s_options= select("options","_user","id_user='$idu'",'ezer_system');
  $options= json_decode($s_options);
  switch ($op) {
  case 'show':
    $ans= is_array($options->email_foot) && isset($options->email_foot[$access])
        ? $options->email_foot[$access] 
        : "<i>patička pro $org nebyla ještě vyplněna</i>";
    break;
  case 'load':
    $ans= is_array($options->email_foot) && isset($options->email_foot[$access])
        ? $options->email_foot[$access] 
        : "";
    break;
  case 'save':
    if ( !is_array($options->email_foot) ) {
      $options->email_foot= array("","","","","");
    }
    $options->email_foot[$access]= $change;
    $options_s= ezer_json_encode($options);
    query("UPDATE _user SET options='$options_s' WHERE id_user='$idu'",'ezer_system');
    break;
  }
  return $ans;
}
# -------------------------------------------------------------------------------------- data_update
# provede změny v dané tabulce pro dané položky a naplní tabulku _track informací o změně
#   $chngs = val1:fld11,fld12,...;val2:...
function data_update ($tab,$id_tab,$chngs) { trace();
  global $USER;
  $now= date("Y-m-d H:i:s");
  $user= $USER->abbr;
  $updated= 0;
  foreach (explode(';',$chngs) as $val_flds) {
    list($val,$flds)= explode(':',$val_flds);
    foreach (explode(',',$flds) as $fld) {
      $old= select($fld,$tab,"id_{$tab}=$id_tab");
      if ( $old!=$val ) {
        $ok= query("INSERT INTO _track (kdy,kdo,kde,klic,fld,op,old,val)
                    VALUES ('$now','$user','$tab',$id_tab,'$fld','U','$old','$val')");
        if ( !$ok ) goto end;
        $ok= query("UPDATE $tab SET $fld='$val' WHERE id_$tab=$id_tab");
        $updated+= $ok ? 1 : 0;
      }
    }
  }
  goto end;
err: fce_error("ERROR IN: data_update ($tab,$id_tab,$chngs)");
end: return $updated;
}
# ---------------------------------------------------------------------------------------- prihl add
# doplní id_prihlaska
function prihl_add() { trace();
  $n= 0;
  $rp= pdo_query("SELECT id_prihlaska,id_pobyt,vars_json,email
      FROM prihlaska 
      WHERE stav REGEXP 'ok$'
      ORDER BY id_prihlaska DESC");
  while ($rp && (list($idpr,$json,$email)= pdo_fetch_array($rp))) {
    $vars= json_decode($json);
    $idp= $vars->pobyt->id_pobyt??0;
    display("$email $idpr $idp");
    if ($idp) {
      $old_idpr= select('id_prihlaska','pobyt',"id_pobyt=$idp");
      if (!$old_idpr)
        $n+= query("UPDATE pobyt SET id_prihlaska=$idpr WHERE id_pobyt=$idp");
    }
  }
  return "doplněno $n ID přihlášek";
}
# ------------------------------------------------------------------------------------ ds objednavka
# vrátí 1 pokud k této akci existuje objednávka, jinak 2
function ds_objednavka($ida) {
  global $answer_db;
  $order= select('id_order','ds_order',"id_akce=$ida") ? 1 : 2;
//g  list($rok,$kod)= select('g_rok,g_kod','join_akce',"id_akce=$ida",$answer_db);
//  list($rok,$kod)= select('YEAR(datum_od),ciselnik_akce','akce',"id_duakce=$ida",$answer_db);
//  if ( $kod ) {
//    $order= select('uid','tx_gnalberice_order',
//        "akce=$kod AND YEAR(FROM_UNIXTIME(fromday))=$rok",'setkani');
//    $order= $order ? $order : 0;
//  }
  return $order;
}
# ------------------------------------------------------------------------------------------- ds2 kc
function TEST() {
  $table= 'osoba';
  $dnu= 70;
  $od= date('Y-m-d',time()-60*60*24*$dnu);
  $zmen= select('COUNT(*)','_track',"kde='$table' AND DATEDIFF(kdy,'$od')>0");
  return "v $table bylo za $dnu dnu $zmen zmen";
}
# ------------------------------------------------------------------------------------------- ds2 kc
function CORR() {
  $n= 0;
  $ro= pdo_qry("
    SELECT id_osoba, prijmeni,jmeno,COUNT(*) AS _n,
      GROUP_CONCAT(id_dar) AS _dar,
      GROUP_CONCAT(id_akce) AS _akce,
      GROUP_CONCAT(t.id_rodina) AS _rodina,
      GROUP_CONCAT(id_platba) AS _platba
    --  ,kdo,kdy
    FROM osoba AS o
    LEFT JOIN dar USING (id_osoba)
    LEFT JOIN spolu USING (id_osoba)
    LEFT JOIN pobyt USING (id_pobyt)
    LEFT JOIN tvori AS t USING (id_osoba)
    LEFT JOIN platba ON  id_oso=o.id_osoba
    -- LEFT JOIN _track ON kde='osoba' AND klic=o.id_osoba AND fld='access'
    WHERE o.deleted='' AND o.access=64 AND id_osoba<27500
    GROUP BY id_osoba 
    HAVING ISNULL(_dar) AND ISNULL(_akce) AND ISNULL(_rodina) AND ISNULL(_platba)
  ");
  while ($ro && (list($ido,$prijmeni,$jmeno)= pdo_fetch_array($ro))) {
    $n++;
    display("CORR $ido: $prijmeni $jmeno");
    query_track("UPDATE osoba SET deleted='H 2025-01-30' WHERE id_osoba=$ido");  
//    break;
  }    
  return "$n skryto";
}
# ----------------------------------------------------------------------------------------- clenstvo
# doplní typ členství Y všem činným před $od
function clenstvo($doit=0,$od='2017-03-04') {
  $n= 0;
  $ro= pdo_qry("
    SELECT id_osoba,prijmeni,jmeno,dat_od,dat_od>'$od' AS neni
    FROM dar JOIN osoba USING (id_osoba)
    WHERE ukon='c' AND dat_od!='0000-00-00' AND dat_do='0000-00-00' 
      AND dar.deleted='' AND osoba.deleted=''
    ORDER BY prijmeni,jmeno
  ");
  while ($ro && (list($ido,$prijmeni,$jmeno,$d1,$neni)= pdo_fetch_array($ro))) {
    $n++;
    $x= $neni ? " ==> není činný v YMCA v ČR" : '';
    display("$n: od $d1 ... $prijmeni $jmeno $x");
    if ($doit && !$neni) {
      query("INSERT INTO dar (access,id_osoba,ukon,dat_od,note) 
        VALUES (1,$ido,'Y','$d1','VPS1 uznáno')");
    }
//    break;
  }    
}
# ------------------------------------------------------------------------------------- ds2 obj_menu
# vygeneruje menu pro loňský, letošní a příští rok ve tvaru objektu pro ezer2 pro zobrazení objednávek
# určující je datum zahájení pobytu v objednávce
# $ym_list = yyyymm,yyyymm,... pro omezení levého menu pro ladění
function ds2_obj_menu($ym_list=null) {
  global $ezer_version;
  $omezeni= false;
  if ( $ym_list ) {
    $omezeni= explode(',',$ym_list);
  }
  $the= $the_last= '';                     // první objednávka v tomto měsíci či později
//                                      debug($stav,'ds_obj_menu',(object)array('win1250'=>1));
  $mesice= array(1=>'leden','únor','březen','duben','květen','červen',
    'červenec','srpen','září','říjen','listopad','prosinec');
  $mn= (object)array('type'=>'menu.left'
      ,'options'=>(object)array(),'part'=>(object)array());
  $start= date('m') <= 6 ? date('Y')-1 : date('Y');
  $ted= date('Ym');
  ezer_connect('setkani');
  $stav= map_cis('ds_stav');
  for ($y= 0; $y<=2; $y++) {
    for ($m= 1; $m<=12; $m++) {
      $mm= sprintf('%02d',$m);
      $yyyy= $start+$y;
      $group= "$yyyy$mm";
      if ( $omezeni && in_array($group,$omezeni)===false ) continue;
      $gr= (object)array('type'=>'menu.group'
        ,'options'=>(object)array('title'=>($mesice[$m])." $yyyy"),'part'=>(object)array());
      $mn->part->$group= $gr;

      $from= mktime(0,0,0,$m,1,$yyyy);
      $until= mktime(0,0,0,$m+1,1,$yyyy);
      $qry= "SELECT /*ds_obj_menu*/uid,fromday,untilday,state,name,state,nazev 
             FROM tx_gnalberice_order
             WHERE  NOT deleted AND NOT hidden AND untilday>=$from AND $until>fromday";
//              JOIN ezer_ys._cis ON druh='ds_stav' AND data=state
      $res= pdo_qry($qry);
      while ( $res && $o= pdo_fetch_object($res) ) {
        $iid= $o->uid;
        $zkratka= $stav[$o->state];
        $par= (object)array('uid'=>$iid,'grp'=>$group);
        if ($ezer_version=='3.2') 
          $par= (object)array('*'=>$par);
//        $tit= wu("$iid - ").$zkratka.wu(" - {$o->name}");
        $tit= $o->nazev ? wu("$iid - $zkratka - $o->nazev") : wu("$iid - $zkratka - $o->name");
        $tm= (object)array('type'=>'item','options'=>(object)array('title'=>$tit,'par'=>$par));
        $gr->part->$iid= $tm;
        $the_last= "$group.$iid";
        if ( !$the && $group>=$ted ) {
          $the= "$group.$iid";
        }
      }
    }
  }
  $the= $the ?: $the_last;
  $result= (object)array('th'=>$the,'cd'=>$mn);
//                                                debug($result,"ds_obj_menu");
  return $result;
}
# ------------------------------------------------------------------------------------- ds2 kli_menu
# vygeneruje menu pro loňský, letošní a příští rok ve tvaru objektu pro ezer2 pro zobrazení klientů
# určující je datum zahájení pobytu v objednávce
function ds2_kli_menu($rok_od=-1,$rok_do=1) {
  global $ezer_version;
  ezer_connect('setkani');
  $the= '';                     // první v tomto měsíci či později
  $rok= date('Y');
  $ted= date('Ym');
  $mesice= array(1=>'leden','únor','březen','duben','květen','červen',
    'červenec','srpen','září','říjen','listopad','prosinec');
  $mn= (object)array('type'=>'menu.left'
      ,'options'=>(object)array(),'part'=>(object)array());
  $letos= date('Y');
  for ($y= $rok_od; $y<=$rok_do; $y++) { // nastavení intervalu
    $yyyy= $letos+$y;
    $group= $letos+$y;
    $gr= (object)array('type'=>'menu.group'
      ,'options'=>(object)array('title'=>$group),'part'=>(object)array());
    $mn->part->$group= $gr;
    for ($m= 1; $m<=12; $m++) {
      $mm= sprintf('%02d',$m);
      $yyyymm= "$yyyy$mm";
      $od= "$group-".sprintf('%02d',$m)."-01";
      $do= "$group-".sprintf('%02d',$m)."-".date('t',mktime(0,0,0,$m,1,$group));
      $from= mktime(0,0,0,$m,1,$yyyy);
      $until= mktime(0,0,0,$m+1,1,$yyyy);
      $uids= ''; $del= ''; $celkem= $objednavek= $klientu= 0;
      $qry= "SELECT uid,(adults+kids_10_15+kids_3_9+kids_3) as celkem
             FROM tx_gnalberice_order
             WHERE  NOT deleted AND NOT hidden AND untilday>=$from AND $until>fromday";
      $res= pdo_qry($qry);
      while ( $res && $o= pdo_fetch_object($res) ) {
        $uids.= "$del{$o->uid}"; $del= ',';
        $objednavek++;
        $celkem+= $o->celkem;
      }
      $qryp= "SELECT count(*) as klientu FROM ds_osoba
             WHERE  FIND_IN_SET(id_order,'$uids')";
      $resp= pdo_qry($qryp);
      if ( $resp && $op= pdo_fetch_object($resp) ) {
        $klientu= $op->klientu;
      }
      $tit= /*w*u*/($mesice[$m])." - $celkem ($klientu)";
      $par= (object)array('od'=>$od,'do'=>$do,
        'celkem'=>$celkem,'klientu'=>$klientu,'objednavek'=>$objednavek,'uids'=>$uids,
        'mesic_rok'=>/*w*u*/($mesice[$m])." $rok");
      if ($ezer_version=='3.2') 
        $par= (object)array('*'=>$par);
      $tm= (object)array('type'=>'item','options'=>(object)array('title'=>$tit,'par'=>$par));
      $gr->part->$m= $tm;
      if ( !$the && $yyyymm>=$ted ) {
        $the= "$group.$m";
      }
    }
  }
  $result= (object)array('th'=>$the,'cd'=>$mn);
  return $result;
}
# ------------------------------------------------------------------------------------------ ds2 vek
# zjištění věku v době zahájení akce
function ds2_vek($narozeni,$fromday) {
  if ( $narozeni=='0000-00-00' )
    $vek= -1;
  else {
    $vek= sql2roku($narozeni,date('Y-m-d', $fromday));
//    $vek= $fromday-sql2stamp($narozeni);
//    $vek= round($vek/(60*60*24*365.2425),1);
  }
  return $vek;
}
// ========================================================================= doplnění osoba + rodina
// 
function check_access($tab,$id,$access_akce) { 
  display("check_access($tab,$id,$access_akce)");
}
