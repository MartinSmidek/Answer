<?php # (c) 2007-2009 Martin Smidek <martin@smidek.eu>
/** ================================================================================================ ŠABLONY */
# -------------------------------------------------------------------------------------------------- dop_sab_text
# přečtení běžného dopisu daného typu
function dop_sab_text($dopis) { //trace();
  $d= null;
  try {
    $qry= "SELECT id_dopis,obsah FROM dopis WHERE typ='$dopis' AND id_davka=1 ";
    $res= mysql_qry($qry,1,null,1);
    $d= mysql_fetch_object($res);
  }
  catch (Exception $e) { display($e); fce_error("dop_sab_text: průběžný dopis '$dopis' nebyl nalezen"); }
  return $d;
}
# -------------------------------------------------------------------------------------------------- dop_sab_cast
# přečtení části šablony
function dop_sab_cast($druh,$cast) { //trace();
  $d= null;
  try {
    $qry= "SELECT id_dopis_cast,obsah FROM dopis_cast WHERE druh='$druh' AND name='$cast' ";
    $res= mysql_qry($qry,1,null,1);
    $d= mysql_fetch_object($res);
  }
  catch (Exception $e) { display($e); fce_error("dop_sab_cast: část '$cast' sablony nebyla nalezena"); }
  return $d;
}
# -------------------------------------------------------------------------------------------------- dop_sab_nahled
# ukázka šablony
function dop_sab_nahled($k3) { trace();
  global $ezer_path_docs;
  $html= '';
  $fname= "sablona.pdf";
  $f_abs= "$ezer_path_docs/$fname";
  $f_rel= "docs/$fname";
  $html= tc_sablona($f_abs,'','D');                 // jen části bez označení v dopis_cast.pro
  $date= @filemtime($f_abs);
  $href= "<a target='dopis' href='$f_rel'>$fname</a>";
  $html.= "Byl vygenerován PDF soubor: $href (verze ze ".date('d.m.Y H:i',$date).")";
  $html.= "<br><br>Jméno za 'vyřizuje' se bere z osobního nastavení přihlášeného uživatele.";
  return $html;
}
/** ================================================================================================ POTVRZENÍ */
# -------------------------------------------------------------------------------------------------- ucet_potv
# přehled podle tabulky 'prijate dary' na intranetu
function ucet_potv($par) { trace();
  $html= '';
  $darce= array();
  $xls= "prijate_dary";
  $max= 9999;
//   $max= 2;
  $rok= date('Y')+$par->rok;
  $let18= date('Y')-18;
  $cells= google_sheet($rok,$xls,'answer@smidek.eu',$google);
  if ( !$cells ) { $html.= "Tabulka <b>$xls/$rok</b> nebyla v intranetu nalezena"; goto end; }
  list($max_A,$max_n)= $cells['dim'];
  // výběr záznamů o darech
  $prblm1= $prblm2= '';
  $jmeno_prvni= array();  // ke klíči $prijmeni$jmeno dá řádek s prvním výskytem
  $jmeno_id= array();     // ke klíči $prijmeni$jmeno dá id nebo 0
  $nalezeno= 0;
  for ($i= 2; $i<=$max_n; $i++) {
    $datum= $cells['A'][$i];
    $dar_jmeno= $cells['B'][$i];
    $castka= $cells['C'][$i];
    $ref= $cells['D'][$i];              // 4
    $auto= $cells['E'][$i];             // 5
    $manual= $cells['F'][$i];
    list($prijmeni,$jmeno)= explode(' ',substr($dar_jmeno,6));
    $opakovane= $jmeno_prvni["$prijmeni$jmeno"] ?: 0;
    if ( !$datum ) break;
    // zapiš opakujícímu se dárci odkaz na řádek s jeho prvním darem
    if ( !$opakovane ) {
      $jmeno_prvni["$prijmeni$jmeno"]= $i;
    }
    if ( !$ref && $opakovane ) {
      $updatedCell= $google->service->updateCell($i,4,$opakovane,$google->sskey,$google->wskey);
    }
    // doplnění intranetové tabulky a střádání darů do tabulky $darce
    if ( !$auto && !$manual && !$opakovane ) {
      // pokusíme se nalézt dárce
      $idss= array();
      $qo= mysql_qry("
        SELECT id_osoba FROM osoba AS o
        WHERE jmeno='$jmeno' AND prijmeni='$prijmeni'
          AND IF(narozeni!='0000-00-00',YEAR(narozeni)<$let18,1)
      ");
      while ($qo && ($o= mysql_fetch_object($qo))) {
        $idss[]= $o->id_osoba;
      }
      if ( count($idss) ) {
        $ids= implode(', ',$idss);
        // zápis do auto
        $updatedCell= $google->service->updateCell($i,5,$ids,$google->sskey,$google->wskey);
        if ( count($idss)==1 ) {
          $jmeno_id["$prijmeni$jmeno"]= $ids;
        }
        else
          $prblm1.= ($prblm1?"<br>":'')."? $datum $jmeno $prijmeni $castka ($ids)";
      }
      else {
        $prblm2.= ($prblm2?"<br>":'')."? $datum $jmeno $prijmeni $castka ($ids)";
      }
    }
    elseif ( strpos($auto,',') && !$manual && !$ref ) {
      $prblm1.= ($prblm1?"<br>":'')."? $datum $jmeno $prijmeni $castka ($auto)";
    }
    elseif ( $manual ) {
      if ( $manual=='x' )
        $prblm3.=  ($prblm3?"<br>":'')."x $dar_jmeno $castka";
      else
        $jmeno_id["$prijmeni$jmeno"]= $manual;
    }
    elseif ( $auto && strpos($auto,',')===false ) {
      $jmeno_id["$prijmeni$jmeno"]= $auto;
    }
    // střádání darů od jednoznačně určeného dárce
    $id= $jmeno_id["$prijmeni$jmeno"];
    if ( $id ) {
      if ( !isset($darce[$id]) ) {
        $darce[$id]= (object)array('data'=>array(),'castka'=>0,'jmeno'=>"$prijmeni $jmeno");
      }
      list($d,$m,$y)= preg_split("/[\/\.]/",$datum);
      $m= 0+$m; $d= 0+$d;
      $darce[$id]->data[]= "$d. $m.";
      $darce[$id]->castka+= $castka;
    }
    if ( --$max <= 0 ) break;
  }
  $reseni= "<br><br>doplň v intranetovém sešitu <b>$xls</b> v listu <b>$rok</b> do sloupce <b>F</b>
            správné osobní číslo dárce (zjistí se v Evidenci), je do prvního výskytu dárce";
  if ( $prblm1 ) $html.= "<h3>Nejednoznačná jména v rámci evidence YS</h3>$prblm1$reseni";
  if ( $prblm2 ) $html.= "<h3>Neznámá jména v rámci evidence YS</h3>$prblm2$reseni";
  if ( $prblm3 ) $html.= "<h3>Ručně napsaná potvrzení</h3>$prblm3";
  if ( !$prblm1 && !$prblm2 ) $html.= "<h3>Ok</h3>všichni dárci byli jednoznačně identifikováni :-)";
  // zápis do tabulky dar, pokud se to chce
  if ( $druh= $par->save ) {
    // smazání záznamů o účetních darech
    query("DELETE FROM dar WHERE YEAR(dat_od)=$rok AND zpusob='u'");
    // zápis zjištěných darů
    $n= 0;
    foreach ($darce as $id=>$dary) {
      $data= implode(', ',$dary->data)." $rok";
      $pars= ezer_json_encode((object)array('data'=>$data));
      $oki= query("INSERT INTO dar (id_osoba,ukon,zpusob,castka,dat_od,note,pars)
        VALUES ($id,'d','u',{$dary->castka},'$rok-12-31','daňové potvrzení','$pars')");
      $n+= $oki ? mysql_affected_rows () : 0;
    }
    $html.= "<br><br>vloženo $n dárců k potvrzování za rok $rok";
  }
  elseif ( $druh= $par->corr ) {
    // oprava záznamů o účetních darech
    $n1= $n2= $n3= 0;
    foreach ($darce as $id=>$dary) {
      $data= implode(', ',$dary->data)." $rok";
      $pars= ezer_json_encode((object)array('data'=>$data));
      // zjištění výše zaznamenaného daru
      $castka2= $dary->castka;
      list($id_dar,$castka1)= select("id_dar,castka","dar","id_osoba=$id AND ukon='d' AND zpusob='u'
        AND dat_od='$rok-12-31' AND note='daňové potvrzení'");
      if ( $castka2==$castka1 ) {
        $n1++;
      }
      elseif ( $castka2 >= 400 ) {
        $pars= ezer_json_encode((object)array('data'=>$data,'bylo'=>$castka1));
                                        display("{$dary->jmeno} $castka1 - $castka2");
        $oku= query("UPDATE dar
          SET castka=$castka2, note='2.daňové potvrzení', pars='$pars'
          WHERE id_dar=$id_dar");
        $n2+= $oku ? mysql_affected_rows () : 0;
      }
      else {
        $n3++;
      }
    }
    $html.= "<br><br>opraveno $n2 dárců za rok $rok, bez opravy jich je $n1, $n3 pod 400 Kč";
  }
end:
  return (object)array('html'=>$html,'href'=>$href);
}
# ================================================================================================== DENIK
# -------------------------------------------------------------------------------------------------- ucet_note
function ucet_note() {
  global $ucet_month_min, $ucet_month_max, $ucet_month_max_odhad;
  $rok= $_SESSION['rok'];
  if ( $ucet_month_min < $ucet_month_max )
    $html.= "<br><br>účetní data: $ucet_month_min/$rok - $ucet_month_max/$rok";
  if ( $ucet_month_max_odhad )
    $html.= "<br><br><span class='odhad'>účetní odhad:</span> ".
      ($ucet_month_max+1)."/$rok - $ucet_month_max_odhad/$rok";
  return $html;
}
# -------------------------------------------------------------------------------------------------- ucet_todo
function ucet_todo($k1,$k2,$k3=2009,$par=null) {
  global $mesice, $mesice_attr;
  global $tisice;
  $tisice= true;
  $html= "<div class='CSection CMenu'>";
  $cond= " 1 ";
  $rok= $_SESSION['rok'];
//                                                         debug($par,"rok={$_SESSION['rok']}");
  $mesice= array('1'=>'leden','2'=>'únor','3'=>'březen','4'=>'duben','5'=>'květen','6'=>'červen','7'=>'červenec','8'=>'srpen','9'=>'září','10'=>'říjen','11'=>'listopad','12'=>'prosinec');
  $mesice_attr= array('1'=>'right','2'=>'right','3'=>'right','4'=>'right','5'=>'right','6'=>'right','7'=>'right','8'=>'right','9'=>'right','10'=>'right','11'=>'right','12'=>'right');
  switch ( $k2 ) {
  case 'u-show':
    $html.= "<h3 class='CTitle'>Účetní deník YMCA Setkání pro rok $k3</h3>";
    $_SESSION['rok']= $k3;
                                                        display("{$_SESSION['rok']}:=$k3");
    $cond= " id_udenik BETWEEN {$k3}00000 AND {$k3}99999 ";
    break;
  case 'e-show':
    $html.= "<h3 class='CTitle'>Účetní odhad YMCA Setkání pro rok $k3</h3>";
    $_SESSION['rok']= $k3;
                                                        display("{$_SESSION['rok']}:=$k3");
    $cond= " year(datum)={$k3} ";
    break;
  case 'u-load':
    $html.= "<h3 class='CTitle'>Import deníku a osnovy pro rok $rok</h3>";
    break;
  case 'u-surv':
    $html.= "<h3 class='CTitle'>Účetní přehledy roku $rok - $k3</h3>";
    $html.= ucet_surv($rok,"5","6",$k3);
    break;
  case 'u-srov':
    $html.= "<h3 class='CTitle'>Účetní přehledy více let</h3>";
    $letos= date('Y');
    $html.= ucet_surv_diff($letos-1,$letos);
    break;
//   case 'msds':
//     $html.= "<h3 class='CTitle'>bilance MS & DS roku $rok</h3>";
//     $html.= ucet_surv($rok,"5","6",6,'Cinnost');
//     break;
//   case 'u-akce':
//     $html.= "<h3 class='CTitle'>Účetní přehledy akcí roku $rok</h3>";
//     $html.= ucet_akce($rok);
//     break;
  case 'u-mesice':
    if ( substr($k3,0,1)=='+' ) {
      $loni= $rok-1;
      $html.= "<h3 class='CTitle'>Měsíční přehledy nákladů roku $rok oproti $loni</h3>";
      $html.= naklady_mesic_diff($rok,$loni,substr($k3,1));
    }
    else {
      global $ucet_month_min, $ucet_month_max, $ucet_month_max_odhad;
      $ucet_month_min= 1;
      $ucet_month_max= 12;
      if ( $_SESSION['rok']==date('Y') ) {
        $ucet_month_max= 8;
        $ucet_month_max_odhad= 12;
      }
      $html.= "<h3 class='CTitle'>Měsíční přehledy nákladů roku $rok</h3>";
      $html.= naklady_mesic($rok,$k3);
      if ( $ucet_month_max<12 ) {
        $html.= ucet_note();
      }
    }
    break;
  case 'u+mesice':
    if ( substr($k3,0,1)=='+' ) {
      $loni= $rok-1;
      $html.= "<h3 class='CTitle'>Měsíční přehledy výnosů roku $rok oproti $loni</h3>";
      $html.= prijmy_mesic_diff($rok,$loni,substr($k3,1));
    }
    else {
      $html.= "<h3 class='CTitle'>Měsíční přehledy výnosů roku $rok</h3>";
      $html.= prijmy_mesic($rok,$k3,6);
    }
    break;
  case 'u*mesice':
    if ( substr($k3,0,1)=='+' ) {
      $loni= $rok-1;
      $html.= "<h3 class='CTitle'>Přehledy měsíčních bilancí roku $rok oproti $loni</h3>";
      $html.= bilance_mesic_diff($rok,$loni,substr($k3,1));
    }
    else {
      $html.= "<h3 class='CTitle'>Měsíční přehledy nákladů roku $rok</h3>";
      $html.= bilance_mesic($rok,$k3);
    }
    break;
  case 'u-proj':
    global $ucet_month_min, $ucet_month_max, $ucet_month_max_odhad;
    $ucet_month_min= $par->od;
    $ucet_month_max= $par->do;
    $ucet_month_max_odhad= $par->odhad ? $par->odhad : 0;
    list($rok,$cast)= explode(',',$k3);
    $html.= "<h3 class='CTitle'>Tabulka nákladů jednotlivých projektů v roce $rok</h3>";
    $html.= proj_export($rok,$cast);
    break;
  case 'naklad':
    global $ucet_month_min, $ucet_month_max, $ucet_month_max_odhad;
    $ucet_month_min= $par->od;
    $ucet_month_max= $par->do;
    $ucet_month_max_odhad= $par->odhad ? $par->odhad : 0;
    $html.= "<h3 class='CTitle'>Tabulka nákladů v roce $rok</h3>";
    $html.= rok_naklad($par);
    break;
  }
  $html.= "</div>";
  $result= (object)array('html'=>$html,'cond'=>$cond,'year'=>$_SESSION['rok']);
  return $result;
}
# ================================================================================================== STRUKTURA
# -------------------------------------------------------------------------------------------------- rok_struktura
function rok_struktura($par) { #trace();
  $result= (object)array();
  $rok= $par->rok;
  $prijmy= $par->prijmy==1;
  $html= "";
  $ratio= 5000;
  $elem= 130;
  $bottom= 600;
  $suma= $msuma= 0;
  $r= array(                                     //rgb
    '?' => (object)array('L'=>0,'W'=>5,'H'=>0, C=>'f00','Bx'=>'',       N=>'neurčené'),
    's' => (object)array('L'=>0,'W'=>5,'H'=>0, C=>'aaa','Bx'=>'?',      N=>'společné'),
    'dp'=> (object)array('L'=>0,'W'=>2,'H'=>0, C=>'ff8','Bx'=>'?,s',    N=>'DS režie'),
    'dh'=> (object)array('L'=>0,'W'=>1,'H'=>0, C=>'bfb','Bx'=>'?,s,dp', N=>'DS hosté'),
    'da'=> (object)array('L'=>1,'W'=>1,'H'=>0, C=>'6f6','Bx'=>'?,s,dp', N=>'DS akce'),
    'a' => (object)array('L'=>2,'W'=>1,'H'=>0, C=>'aff','Bx'=>'?,s',    N=>'akce'),
    'v' => (object)array('L'=>3,'W'=>2,'H'=>0, C=>'ff0','Bx'=>'?,s',    N=>'VPS'),
    'p' => (object)array('L'=>3,'W'=>1,'H'=>0, C=>'fa6','Bx'=>'?,s,v',  N=>'pečouni'),
    'ml'=> (object)array('L'=>3,'W'=>1,'H'=>0, C=>'f66','Bx'=>'?,s,v,p',N=>'letní kurz'),
    'mo'=> (object)array('L'=>4,'W'=>1,'H'=>0, C=>'aaf','Bx'=>'?,s,v',  N=>'obnovy')
  );
  $and= $prijmy ? "AND left(DAL,1)='6' " : "AND left(MD,1)='5' AND md!='551200'";
  $ucel= $prijmy ?
   "(CASE WHEN left(dal,2)='69' THEN 'dotace'
          WHEN LEFT(dal,2)='68' THEN 'dary'
          ELSE 'ostatní' END) " :
   "(CASE WHEN left(md,2)='52' THEN 'mzdy'
          WHEN (LEFT(md,4)='5122' OR LEFT(md,5)='50125') THEN 'cesty'
          WHEN LEFT(md,4)='5182' THEN 'služby'
          WHEN LEFT(md,5)='50122' THEN 'potraviny'
          WHEN LEFT(md,5)='50221' THEN 'energie'
          WHEN LEFT(md,4)='5112' THEN 'opravy'
          WHEN LEFT(md,3)='546' THEN 'dary'
          ELSE 'ostatní' END) ";
  $qry= "SELECT a.kapitola as kap, $ucel as ucel,akce,id_udenik,
    /*count(*) as pocet,*/
    sum(Castka) as castka
    /*,group_concat(distinct md) as ucty,
    group_concat(distinct o.nazev) as nazvy*/
    FROM udenik AS d
    LEFT JOIN uosnova AS o ON ucet=md AND o.rok=left(id_udenik,4)
    LEFT JOIN uakce AS a ON a.rok=left(id_udenik,4) AND akce=d.Cinnost
    WHERE id_udenik BETWEEN {$rok}00000 AND {$rok}99999 $and
    GROUP BY kapitola,ucel";
  $res= mysql_qry($qry);
  while ( $res && $u= mysql_fetch_object($res) ) {
    $castka= round($u->castka,0);
    $kap= $u->kap ? $u->kap : '?';
    $suma+= $castka;
    if ( isset($r[$kap]) ) {
      if ( $prijmy ) {
        if ( $u->ucel=='dary' || $u->ucel=='ostatní' )
          $r[$kap]->H+= $castka;
        elseif ( $u->ucel=='dotace' ) {
          $r[$kap]->M+= $castka;
          $msuma+= $castka;
        }
        else fce_error("{$u->ucel} je neznámý účel");
      }
      else {
        $r[$kap]->H+= $castka;
        if ( $u->ucel=='mzdy' ) {
          $r[$kap]->M+= $castka;
          $msuma+= $castka;
        }
      }
    }
    else fce_error("{$u->kap} je neznámá kapitola (akce={$u->akce},denik={$u->id_udenik})");
  }
  // doplnění B podle Bx
  foreach ($r as $i=>$d) {
    $b= 0;
    if ( $d->Bx ) foreach(explode(',',$d->Bx) as $x) {
//       $b+= $r[$x]->H/$r[$x]->W;
      $b+= ($r[$x]->H+$r[$x]->M)/$r[$x]->W;
    }
    $r[$i]->B= $b;
  }
                                                debug($r,'r');
  // nakreslení grafu
  $graf= "<div style='position:absolute;height:{$bottom}px;'>";
  foreach ($r as $i=>$d) {
    $l= $elem*$d->L;
    $b= round($d->B/$ratio,0);
    $w= $elem*$d->W;
//     $h= round(($d->H/$ratio)/$d->W,0);
    $h= round((($d->H+$d->M)/$ratio)/$d->W,0);
    $c= $d->C;
    $tkc= round($d->H/1000,0);
    $mtkc= round($d->M/1000,0);
    $t= " title=' $tkc'";
    $lwbh= "left:{$l}px;width:{$w}px;bottom:{$b}px;height:{$h}px";
    $graf.= "<div class='dia' $t style='$lwbh;background-color:#{$c};'>$tkc ($mtkc) {$d->N}/$i</div>";
  }
  $graf.= "</div>";
  $html.= $graf;
  $tkc= round($suma/1000,0);
  $mtkc= round($msuma/1000,0);
  $html.= "<br/></br>Celkem $tkc ($mtkc)";
  return $html;
}
# ================================================================================================== ODHADY
# -------------------------------------------------------------------------------------------------- rok_naklad
function rok_naklad($par) { #trace();
  global $proj_tab, $ucty_tab, $tisice, $ucet_month_min, $ucet_month_max, $ucet_month_max_odhad;
  $rok= $par->c;
  $clmn_tab= array('MS/*'=>'MS','DS/*'=>'DS','DH/*'=>'DH','*/*'=>'?');
  $ucty_tab= array();
  $proj_tab= array(
//
    "celkový objem" => array(
      "" => (object)array(),
      "a) osobní náklady" => array(
         "mzdové náklady" => array(
           "hrubé mzdy" => (object)array(          'i'=>'o_mzdy',  't'=>5212, 'u'=>array('521201|521202|521200')),
           "OON na DPČ" => (object)array(          'i'=>'o_dpc',   't'=>5212, 'u'=>array('')),
           "OON na DPP" => (object)array(          'i'=>'o_dpp',   't'=>5212, 'u'=>array('521221|521222|521220')),
           "jiné mzdové náklady" => (object)array(                 't'=>5212, 'u'=>array())
         ),
         "odvody soc.,zdrav. poj." => array(
           "pojistné ke mzdám" => (object)array(   'i'=>'o_pmzdy', 't'=>5242, 'u'=>array('524201|524221|524202|524222|524200|524220')),
           "pojistné k DPČ" => (object)array(      'i'=>'o_pdpc',  't'=>5242, 'u'=>array()),
           "jiné pojistné" => (object)array(                       't'=>5242, 'u'=>array('527220'))
         ),
         "ostatní osobní náklady" => /*array (
             "" =>*/ (object)array(                                't'=>52,   'u'=>array()/*)*/
         )
      ),
      "b) provozní náklady" => array(
         "materiálové náklady" => array(
           "potraviny" => (object)array(           'i'=>'m_potr',  't'=>5012, 'u'=>array('501220|501221')),
           "kancelářské potřeby" => (object)array( 'i'=>'m_kanc',  't'=>5012, 'u'=>array('501241|501240|501242')),
           "vyb. DDHM do 40 tKč" => (object)array( 'i'=>'m_ddhm',  't'=>5012, 'u'=>array('501231|501232|501230')),
           "pohonné hmoty" => (object)array(       'i'=>'m_phm',   't'=>5012, 'u'=>array('501251|501261|501250|501252|501260')),
           "výtvarný materiál" => (object)array(   'i'=>'m_vytv',  't'=>5012, 'u'=>array('501204')),
           "jiný materiál" => (object)array(       'i'=>'m_jiny',  't'=>5012, 'u'=>array('501200|501203'))
         ),
         "nemateriálové náklady" => array(
           "energie" => /*array(
             "" =>*/ (object)array(                'i'=>'n_eng',   't'=>5022, 'u'=>array('502211|502210')/*)*/
           ),
           "opravy a udržování" => /*array(
             "" =>*/ (object)array(                'i'=>'n_opr',   't'=>5112, 'u'=>array('511201|511211|511210|511221|511213|511200|511220')/*),*/
           ),
           "cestovné" => array(
             "cestovné zaměstnanců" => (object)array(),
             "jiné cestovné" => (object)array(     'i'=>'n_ces',   't'=>5122, 'u'=>array('512201|512202|512200'))
           ),
           "ostatní služby" => array(
             "spoje celkem" => (object)array(      'i'=>'n_spo',   't'=>5182, 'u'=>array('518214|518261|518215|518260|518210|518218')),
             "nájemné" => (object)array(           'i'=>'n_naj',   't'=>5182, 'u'=>array('518212|518216|518211|518217')),
             "právní a ek. služby"=> (object)array('i'=>'n_eko',   't'=>5182, 'u'=>array('518241|518242|518240|518243')),
             "školení a kurzy" => (object)array(   'i'=>'n_sko',   't'=>5182, 'u'=>array('518291|518290')),
             "pořízení DNM do 60 tKč" => (object)array(
                                                   'i'=>'n_dnm',   't'=>5182, 'u'=>array('518231')),
             "tisk materiálů" => (object)array(    'i'=>'n_tis',   't'=>5182, 'u'=>array('518281|518280')),
             "ubytování a stravování" => (object)array(
                                                   'i'=>'n_uby',   't'=>5189, 'u'=>array('518900|518901')),
             "ubytování a stravování" => (object)array(
                                                   'i'=>'n_uby',   't'=>5189, 'u'=>array('518900|518901')),
             "svoz odpadu" => (object)array(       'i'=>'n_odpad', 't'=>5182, 'u'=>array('518271|518270')),
             "jiné služby" => (object)array(       'i'=>'n_slu',   't'=>5182, 'u'=>array('518200|518202|518201'))
           ),
           "ostatní náklady" => array(
             "jiné ostatní náklady" => (object)array(
                                                   'i'=>'n_ost',   't'=>5,    'u'=>array('538200|549200|531100|532200|544100|549000|549300|581300|549999')),
             "odpisy" =>  (object)array(           'i'=>'n_odpis', 't'=>5512, 'u'=>array('551200'))
           )
         )
      )
    )
  );
//   $html.= "Prázdná tabulka:";;
//   $html.= "<div id='proj'>".debugx($proj_tab)."</div>";
  // vyplnění tabulky
  $sum= proj_make_x($rok,&$proj_tab,$clmn_tab);
//                                         $html.= "<div id='proj'>".debugx($proj_tab)."</div>";
  // celkový objem
  $v= 0;
  foreach ($proj_tab["celkový objem"]['']->xu as $clmn) {
    $v+= $clmn;
  }
  $v= number_format($tisice ? $v/1000 : $v, 0, '.', ' ');
  // tisk tabulky
  $width= ""; //"style='width:700px'";
  $html.= "<table class='stat' $width><tr><th style='text-align:right'>$v</th><th>tř.</th>";
  // hlavička
  foreach($clmn_tab as $clmn)
    $html.= "<th style='width:40px;text-align:right'>$clmn</th>";
  $html.= "<th style='width:40px;text-align:right'>součet</th>";
  $html.= "</tr>";
  // tělo
  $html.= proj_print_x($proj_tab);
  $html.= "</table>";
  $html.= ucet_note();
  return $html;
}
# -------------------------------------------------------------------------------------------------- proj_make_x
# když účty jsou skalár a sloupce jsou tvořeny podle zakazka/stredisko=clmn_tab
function proj_make_x($rok,&$tab,$clmn_tab) { trace();
  global $proj_tab, $ucty_tab;
  $out= (object)array();
  if ( is_object($tab) ) {
    if ( $tab->u ) {
      // projdi skupinu účtů pro všechny kombinace clmn_tab
      $out->xu= $tab->xu= naklad_vzor_x($rok,$tab->u[0],$clmn_tab);
      // kontrola účtů
      $us= explode('|',$tab->u[0]);
      foreach($us as $u) {
        if ( isset($ucty_tab[$u]) ) fce_error("duplicita pro $u");
        $ucty_tab[$u]= 1;
      }
    }
    if ( $tab->i ) {
      // přidej odhad za zbývající měsíce
//       $out->xu[1]= $tab->xu[1]= 1000;
    }
  }
  else if ( is_array($tab) ) {
    $n= 0; $m= 0;
    $xu= array();
    foreach($tab as $name=>$line) {
      $line_out= proj_make_x($rok,&$tab[$name],$clmn_tab);
      $n++;
      $m= max($m,count($line_out->xu));
      foreach ($clmn_tab as $i=>$ii) {
        if ( !isset($xu[$i]) ) $xu[$i]= 0;
        $xu[$i]+= $line_out->xu[$i] ? $line_out->xu[$i] : 0;
      }
    }
    if ( !isset($tab[""]) ) $tab[""]= (object)array('xu'=>array());
    foreach ($clmn_tab as $i=>$ii) {
      $tab[""]->xu[$i]= $xu[$i];
      $out->xu[$i]= $xu[$i];
    }
  }
  return $out;
}
# -------------------------------------------------------------------------------------------------- naklad_vzor_x
# výše nákladu definovaná pomocí rexpr nad MD a zakázkou
# Stredisko=MPSV-$co
# $clmn_tab= array('MS/MPSV-MS','MS','DS/MPSV-DS','DS','DH','x') nebo
# $clmn_tab= array('*/*')
function naklad_vzor_x($rok,$vzor,$clmn_tab) { #trace();
  global $ucet_month_min, $ucet_month_max, $ucet_month_max_odhad;
  foreach ($clmn_tab as $indx=>$ii) $suma[$indx]= 0;
  if ( $vzor ) {
    $qry= "SELECT sum(castka) as suma,Zakazka,Stredisko,
           min(month(datum)) as _od, max(month(datum)) as _do
           FROM udenik WHERE md RLIKE '$vzor' AND id_udenik BETWEEN {$rok}00000 AND {$rok}99999
           AND month(datum)<=$ucet_month_max AND month(datum)>=$ucet_month_min
           GROUP BY Zakazka,Stredisko";
    $res= mysql_qry($qry);
    while ( $res && $u= mysql_fetch_object($res) ) {
      $z= $u->Zakazka;
      $s= $u->Stredisko;
      if ( isset($suma["$z/$s"]) )
        $suma["$z/$s"]+= $u->suma;
      elseif ( isset($suma["*/$s"]) )
        $suma["*/$s"]+= $u->suma;
      elseif ( isset($suma["$z/*"]) )
        $suma["$z/*"]+= $u->suma;
      elseif ( isset($suma["*/*"]) )
        $suma["*/*"]+= $u->suma;
      else
        fce_error("kombinace Zakazka/Stredisko=$s/$z - s tím se nepočítá/a");
    }
    // přidej odhady
    if ( $ucet_month_max_odhad > $ucet_month_max ) {
      $ucet_month_min_odhad= $ucet_month_min;
      $qry= "SELECT sum(castka) as suma,Zakazka,Stredisko,
             min(month(datum)) as _od, max(month(datum)) as _do
             FROM uodhad WHERE md RLIKE '$vzor'
             AND month(datum)<=$ucet_month_max_odhad AND month(datum)>=$ucet_month_min_odhad
             GROUP BY Zakazka,Stredisko";
      $res= mysql_qry($qry);
      while ( $res && $u= mysql_fetch_object($res) ) {
        $indx= "{$u->Zakazka}/{$u->Stredisko}";
        if ( !isset($suma[$indx]) )
           fce_error("kombinace Zakazka/Stredisko=$indx - s tím se nepočítá/b");
        $suma[$indx]+= $u->suma;
      }
    }
//                                                 debug($suma,"$vzor/$co");
  }
  return $suma;
}
# -------------------------------------------------------------------------------------------------- proj_print_x
# tisk těla tabulky - poslední sloupec je suma
function proj_print_x($tab,$depth=0) { #trace();
  global $tisice;
  $htm= "";
  $ind= str_repeat(' &tilde; ',$depth);
  if ( is_array($tab) ) {
    foreach($tab as $name=>$line) if ( $name ) {
      if ( is_object($line) && $line->xu ) {
        $htm.= "<tr><th>$ind$name</th><th>{$line->t}</th>";
        $s= 0;
        foreach ($line->xu as $v) {
          $v= $tisice ? $v/1000 : $v;
          $s+= $v;
          $v= number_format($v, 0, '.', ' ');
          $v= str_replace(' ','&nbsp;',$v);
          $htm.= "<td align='right'>$v</td>";
        }
        $s= number_format($s, 0, '.', ' ');
        $htm.= "<td align='right'><i>$s</i></td>";
        $htm.= "</tr>";
      }
      else {
        if ( is_array($line) && $line[''] && $line['']->xu ) {
          $htm.= "<tr><th>$ind$name</th><th>{$line->t}</th>";
          $s= 0;
          foreach ($line['']->xu as $v) {
            $v= $tisice ? $v/1000 : $v;
            $s+= $v;
            $v= number_format($v, 0, '.', ' ');
            $v= str_replace(' ','&nbsp;',$v);
            $htm.= "<th style='text-align:right'>$v</th>";
          }
          $s= number_format($s, 0, '.', ' ');
          $htm.= "<th style='text-align:right'><i>$s</i></th>";
          $htm.= "</tr>";
        }
        $htm.= proj_print_x($line,$depth+1);
      }
    }
  }
  return $htm;
}
# ================================================================================================== PROJEKT
# -------------------------------------------------------------------------------------------------- proj_export
function proj_export($rok,$co) { #trace();
  global $proj_tab, $ucty_tab, $tisice, $ucet_month_min, $ucet_month_max, $ucet_month_max_odhad;
  global $clmn_tab_subst;
  $clmn_tab= array('MS/MPSV-MS'=>'MPSV'
                  ,'MS/A1P1'=>'MŠ1','MS/A1P2A'=>'MŠ2a','MS/A1P2T'=>'MŠ2t','MS/A1P3'=>'MŠ3'
                  ,'MS/A1A'=>'MS.MŠ','MS/A1P'=>'MS.MŠ','MS/A1T'=>'MS.MŠ','MS/A1V'=>'MS.MŠ'
                  ,'DS/A1A'=>'DS.MŠ','DS/A1P'=>'DS.MŠ','DS/A1T'=>'DS.MŠ','DS/A1V'=>'DS.MŠ'
                  ,'MS/'=>'MS'
                  ,'DS/MPSV-DS'=>'DS.MPSV'
                  ,'DS/A1P2A'=>'MŠ'
                  ,'DH/'=>'DH'
                  ,'DS/'=>'DS'
                  ,'DS/Racek'=>'DS/Racek'
                  ,'x/'=>'');
  $clmn_tab= array('MS/MPSV-MS'=>'MPSV'
                  ,'MS/MŠ'=>'MS.MŠ'
                  ,'DS/MŠ'=>'DS.MŠ'
                  ,'MS/'=>'MS'
                  ,'DS/MPSV-DS'=>'DS.MPSV'
                  ,'DH/'=>'DH'
                  ,'DS/'=>'DS'
                  ,'DS/Racek'=>'DS/Racek'
                  ,'x/'=>'');
  $clmn_tab_subst= array(
                   'MS/A1P1'=>'MS/MŠ','MS/A1P2A'=>'MS/MŠ','MS/A1P2T'=>'MS/MŠ','MS/A1P3'=>'MS/MŠ'
                  ,'DS/A1P2A'=>'DS/MŠ'
                  ,'MS/A1A'=>'MS/MŠ','MS/A1P'=>'MS/MŠ','MS/A1T'=>'MS/MŠ','MS/A1V'=>'MS/MŠ'
                  ,'DS/A1A'=>'DS/MŠ','DS/A1P'=>'DS/MŠ','DS/A1T'=>'DS/MŠ','DS/A1V'=>'DS/MŠ'
                  );
  $ucty_tab= array();
  $proj_tab= array(
//
    "celkový objem" => array(
      "" => (object)array(),
      "a) osobní náklady" => array(
         "mzdové náklady" => array(
           "hrubé mzdy" => (object)array(          'i'=>'o_mzdy',  't'=>5212, 'u'=>array('521201|521202|521200')),
           "OON na DPČ" => (object)array(          'i'=>'o_dpc',   't'=>5212, 'u'=>array('')),
           "OON na DPP" => (object)array(          'i'=>'o_dpp',   't'=>5212, 'u'=>array('521221|521222|521220')),
           "jiné mzdové náklady" => (object)array(                 't'=>5212, 'u'=>array())
         ),
         "odvody soc.,zdrav. poj." => array(
           "pojistné ke mzdám" => (object)array(   'i'=>'o_pmzdy', 't'=>5242, 'u'=>array('524201|524221|524202|524222|524200|524220')),
           "pojistné k DPČ" => (object)array(      'i'=>'o_pdpc',  't'=>5242, 'u'=>array()),
           "jiné pojistné" => (object)array(                       't'=>5242, 'u'=>array('527220'))
         ),
         "ostatní osobní náklady" => /*array (
             "" =>*/ (object)array(                                't'=>52,   'u'=>array()/*)*/
         )
      ),
      "b) provozní náklady" => array(
         "materiálové náklady" => array(
           "potraviny" => (object)array(           'i'=>'m_potr',  't'=>5012, 'u'=>array('501220|501221')),
           "kancelářské potřeby" => (object)array( 'i'=>'m_kanc',  't'=>5012, 'u'=>array('501241|501240|501242')),
           "vyb. DDHM do 40 tKč" => (object)array( 'i'=>'m_ddhm',  't'=>5012, 'u'=>array('501231|501232|501230')),
           "pohonné hmoty" => (object)array(       'i'=>'m_phm',   't'=>5012, 'u'=>array('501251|501261|501250|501252|501260')),
           "výtvarný materiál" => (object)array(   'i'=>'m_vytv',  't'=>5012, 'u'=>array('501204')),
           "jiný materiál" => (object)array(       'i'=>'m_jiny',  't'=>5012, 'u'=>array('501200|501203'))
         ),
         "nemateriálové náklady" => array(
           "energie" => /*array(
             "" =>*/ (object)array(                'i'=>'n_eng',   't'=>5022, 'u'=>array('502211|502210')/*)*/
           ),
           "opravy a udržování" => /*array(
             "" =>*/ (object)array(                'i'=>'n_opr',   't'=>5112, 'u'=>array('511201|511211|511210|511221|511213|511200|511220')/*),*/
           ),
           "cestovné" => array(
             "cestovné zaměstnanců" => (object)array(),
             "jiné cestovné" => (object)array(     'i'=>'n_ces',   't'=>5122, 'u'=>array('512201|512202|512200'))
           ),
           "ostatní služby" => array(
             "spoje celkem" => (object)array(      'i'=>'n_spo',   't'=>5182, 'u'=>array('518214|518261|518215|518260|518210|518218')),
             "nájemné" => (object)array(           'i'=>'n_naj',   't'=>5182, 'u'=>array('518212|518216|518211|518217')),
             "právní a ek. služby"=> (object)array('i'=>'n_eko',   't'=>5182, 'u'=>array('518241|518242|518240|518243')),
             "školení a kurzy" => (object)array(   'i'=>'n_sko',   't'=>5182, 'u'=>array('518291|518290')),
             "pořízení DNM do 60 tKč" => (object)array(
                                                   'i'=>'n_dnm',   't'=>5182, 'u'=>array('518231')),
             "tisk materiálů" => (object)array(    'i'=>'n_tis',   't'=>5182, 'u'=>array('518281|518280')),
             "ubytování a stravování" => (object)array(
                                                   'i'=>'n_uby',   't'=>5189, 'u'=>array('518900|518901')),
             "ubytování a stravování" => (object)array(
                                                   'i'=>'n_uby',   't'=>5189, 'u'=>array('518900|518901')),
             "svoz odpadu" => (object)array(       'i'=>'n_odpad', 't'=>5182, 'u'=>array('518271|518270')),
             "jiné služby" => (object)array(       'i'=>'n_slu',   't'=>5182, 'u'=>array('518200|518202|518201'))
           ),
           "ostatní náklady" => array(
             "jiné ostatní náklady" => (object)array(
                                                   'i'=>'n_ost',   't'=>5,    'u'=>array('538200|549200|531100|532200|544100|549000|549300|581300|549999')),
             "odpisy" =>  (object)array(           'i'=>'n_odpis', 't'=>5512, 'u'=>array('551200'))
           )
         )
      )
    )
  );
//   $html.= "Prázdná tabulka:";;
//   $html.= "<div id='proj'>".debugx($proj_tab)."</div>";
  // vyplnění tabulky
  $sum= proj_make($rok,&$proj_tab,$clmn_tab);
//                                         $html.= "<div id='proj'>".debugx($proj_tab)."</div>";
  // celkový objem
  $v= 0;
  foreach ($proj_tab["celkový objem"]['']->xu as $clmn) {
    $v+= $clmn;
  }
  $v= number_format($tisice ? $v/1000 : $v, 0, '.', ' ');
  // tisk tabulky
  $width= ""; //"style='width:700px'";
  $html.= "<table class='stat' $width><tr><th style='text-align:right'>$v</th><th>tř.</th>";
  foreach($clmn_tab as $clmn)
    $html.= "<th style='width:40px;text-align:right'>$clmn</th>";
  $html.= "</tr>";
  $html.= proj_print($proj_tab);
  $html.= "</table>";
  $html.= ucet_note();
  return $html;
}
# -------------------------------------------------------------------------------------------------- proj_make
# když účty jsou skalár a sloupce jsou tvořeny podle zakazka/stredisko=clmn_tab
function proj_make($rok,&$tab,$clmn_tab) { trace();
  global $proj_tab, $ucty_tab;
  $out= (object)array();
  if ( is_object($tab) ) {
    if ( $tab->u ) {
      // projdi skupinu účtů pro všechny kombinace clmn_tab
      $out->xu= $tab->xu= naklad_vzor($rok,$tab->u[0],$clmn_tab);
      // kontrola účtů
      $us= explode('|',$tab->u[0]);
      foreach($us as $u) {
        if ( isset($ucty_tab[$u]) ) fce_error("duplicita pro $u");
        $ucty_tab[$u]= 1;
      }
    }
    if ( $tab->i ) {
      // přidej odhad za zbývající měsíce
//       $out->xu[1]= $tab->xu[1]= 1000;
    }
  }
  else if ( is_array($tab) ) {
    $n= 0; $m= 0;
    $xu= array();
    foreach($tab as $name=>$line) {
      $line_out= proj_make($rok,&$tab[$name],$clmn_tab);
      $n++;
      $m= max($m,count($line_out->xu));
      foreach ($clmn_tab as $i=>$ii) {
        if ( !isset($xu[$i]) ) $xu[$i]= 0;
        $xu[$i]+= $line_out->xu[$i] ? $line_out->xu[$i] : 0;
      }
    }
    if ( !isset($tab[""]) ) $tab[""]= (object)array('xu'=>array());
    foreach ($clmn_tab as $i=>$ii) {
      $tab[""]->xu[$i]= $xu[$i];
      $out->xu[$i]= $xu[$i];
    }
  }
  return $out;
}
# -------------------------------------------------------------------------------------------------- proj_make_vect
# když naklad_vzor vrací jen číslo a účty jsou dány jako pole
// function proj_make_vect($rok,$co,&$tab) { #trace();
//   global $proj_tab;
//   $out= (object)array();
//   if ( is_object($tab) ) {
//     if ( $tab->u ) {
//       // projdi seznam skupin účtů
//       $tab->xu= $out->xu= array();
//       for ($i= 0; $i<count($tab->u); $i++) {
//         $out->xu[$i]= $tab->xu[$i]= naklad_vzor($rok,$co,$tab->u[$i]);
//       }
//     }
//   }
//   else if ( is_array($tab) ) {
//     $n= 0; $m= 0;
//     $xu= array();
//     foreach($tab as $name=>$line) {
//       $line_out= proj_make_vect($rok,$co,&$tab[$name]);
//       $n++;
//       $m= max($m,count($line_out->xu));
//       for ($i= 0; $i<$m; $i++) {
//         if ( !isset($xu[$i]) ) $xu[$i]= 0;
//         $xu[$i]+= $line_out->xu[$i] ? $line_out->xu[$i] : 0;
//       }
//     }
//     if ( !isset($tab[""]) ) $tab[""]= (object)array('xu'=>array());
//     for ($i= 0; $i<$m; $i++) {
//       $tab[""]->xu[$i]= $xu[$i];
//       $out->xu[$i]= $xu[$i];
//     }
//   }
//   return $out;
// }
# -------------------------------------------------------------------------------------------------- proj_print
function proj_print($tab,$depth=0) { #trace();
  global $tisice;
  $htm= "";
  $ind= str_repeat(' &tilde; ',$depth);
  if ( is_array($tab) ) {
    foreach($tab as $name=>$line) if ( $name ) {
      if ( is_object($line) && $line->xu ) {
        $htm.= "<tr><th>$ind$name</th><th>{$line->t}</th>";
        foreach ($line->xu as $v) {
          $v= $tisice ? $v/1000 : $v;
          $v= number_format($v, 0, '.', ' ');
          $v= str_replace(' ','&nbsp;',$v);
          $htm.= "<td align='right'>$v</td>";
        }
        $htm.= "</tr>";
      }
      else {
        if ( is_array($line) && $line[''] && $line['']->xu ) {
          $htm.= "<tr><th>$ind$name</th><th>{$line->t}</th>";
          foreach ($line['']->xu as $v) {
            $v= $tisice ? $v/1000 : $v;
            $v= number_format($v, 0, '.', ' ');
            $v= str_replace(' ','&nbsp;',$v);
            $htm.= "<th style='text-align:right'>$v</th>";
          }
          $htm.= "</tr>";
        }
        $htm.= proj_print($line,$depth+1);
      }
    }
  }
  return $htm;
}
# -------------------------------------------------------------------------------------------------- naklad_vzor
# výše nákladu definovaná pomocí rexpr nad MD a zakázkou
# Stredisko=MPSV-$co
# $clmn_tab= array('MS/MPSV-MS','MS','DS/MPSV-DS','DS','DH','x') nebo
# $clmn_tab= array('*/*'
function naklad_vzor($rok,$vzor,$clmn_tab) { #trace();
  global $ucet_month_min, $ucet_month_max, $ucet_month_max_odhad;
  global $clmn_tab_subst;
  foreach ($clmn_tab as $indx=>$ii) $suma[$indx]= 0;
  if ( $vzor ) {
    $qry= "SELECT sum(castka) as suma,Zakazka,Stredisko,min(id_udenik) as id,
           min(month(datum)) as _od, max(month(datum)) as _do
           FROM udenik WHERE md RLIKE '$vzor' AND id_udenik BETWEEN {$rok}00000 AND {$rok}99999
           AND month(datum)<=$ucet_month_max AND month(datum)>=$ucet_month_min
           GROUP BY Zakazka,Stredisko";
    $res= mysql_qry($qry);
    while ( $res && $u= mysql_fetch_object($res) ) {
      $indx= strtr("{$u->Zakazka}/{$u->Stredisko}",$clmn_tab_subst);
      if ( !isset($suma[$indx]) )
         fce_error("kombinace Zakazka/Stredisko=$indx - s tím se nepočítá (id_udenik={$u->id})/c");
      $suma[$indx]= $u->suma;
    }
    // přidej odhady
    if ( $ucet_month_max_odhad > $ucet_month_max ) {
      $ucet_month_min_odhad= $ucet_month_min;
      $qry= "SELECT sum(castka) as suma,Zakazka,Stredisko,
             min(month(datum)) as _od, max(month(datum)) as _do
             FROM uodhad WHERE md RLIKE '$vzor'
             AND month(datum)<=$ucet_month_max_odhad AND month(datum)>=$ucet_month_min_odhad
             GROUP BY Zakazka,Stredisko";
      $res= mysql_qry($qry);
      while ( $res && $u= mysql_fetch_object($res) ) {
        $indx= "{$u->Zakazka}/{$u->Stredisko}";
        if ( !isset($suma[$indx]) )
           fce_error("kombinace Zakazka/Stredisko=$indx - s tím se nepočítá/d");
        $suma[$indx]+= $u->suma;
      }
    }
//                                                 debug($suma,"$vzor/$co");
  }
  return $suma;
}
# ================================================================================================== MĚSÍC
# -------------------------------------------------------------------------------------------------- ucet_mesic
# měsíční přehled daného roku - může být buď '5..','' nebo '','6..'
function ucet_mesic($rok,$co='%',$presnost=3,$md='5',$dal='') { #trace();
  $tab= array();
  if ( $md )
    $qry= "SELECT left(md,$presnost) as ucet,sum(castka) as suma,month(datum) as mesic, nazev
           FROM udenik JOIN uosnova AS u ON ucet=md AND rok=$rok
           WHERE md LIKE '$md%' AND id_udenik BETWEEN {$rok}00000 AND {$rok}99999
           AND zakazka LIKE '$co'
           GROUP BY left(md,$presnost),month(datum)";
  else
    $qry= "SELECT left(dal,$presnost) as ucet,sum(castka) as suma,month(datum) as mesic, nazev
           FROM udenik JOIN uosnova AS u ON ucet=dal AND rok=$rok
           WHERE dal LIKE '$dal%' AND id_udenik BETWEEN {$rok}00000 AND {$rok}99999
           AND zakazka LIKE '$co'
           GROUP BY left(dal,$presnost),month(datum)";
  $res= mysql_qry($qry);
  while ( $res && $u= mysql_fetch_object($res) ) {
    $tab[$u->ucet][$u->mesic]= $u->suma;
    $tab[$u->ucet]['nazev']= $u->nazev;
    $tab['*celkem'][$u->mesic]+= $u->suma;
  }
  ksort($tab);
  return $tab;
}
# -------------------------------------------------------------------------------------------------- naklady_mesic_odhad
# měsíční přehled odhadu nákladů daného roku
function naklady_mesic_odhad($rok,$co='%',$presnost=3,&$tab,&$tab_class) { #trace();
  global $ucet_month_min, $ucet_month_max, $ucet_month_max_odhad;
  if ( $ucet_month_max_odhad > $ucet_month_max ) {
    $ucet_month_min_odhad= $ucet_month_max+1;
    $qry= "SELECT left(md,$presnost) as ucet,sum(castka) as suma,month(datum) as mesic
           FROM uodhad WHERE md LIKE '$md%' AND year(datum)=$rok
           AND month(datum)<=$ucet_month_max_odhad AND month(datum)>=$ucet_month_min_odhad
           AND zakazka LIKE '$co'
           GROUP BY left(md,$presnost),month(datum)";
    $res= mysql_qry($qry);
    while ( $res && $u= mysql_fetch_object($res) ) {
      $tab[$u->ucet][$u->mesic]= $u->suma;
      $tab_class[$u->ucet][$u->mesic]= 'odhad';
      $tab['*celkem'][$u->mesic]+= $u->suma;
    }
  }
  ksort($tab);
}
# -------------------------------------------------------------------------------------------------- naklady_mesic
# měsíční přehled nákladů
function naklady_mesic($rok,$co='%',$presnost=6) { #trace();
  global $mesice, $mesice_attr;
  $tab= ucet_mesic($rok,$co,$presnost,'5');
  $tab_class= array();
  naklady_mesic_odhad($rok,$co,$presnost,$tab,$tab_class);
  if ( $presnost==6 ) {
    $sloupce= $mesice;
    $sloupce['nazev']= 'účet';
    $sloupce_attr= $mesice_attr;
    $sloupce_attr['nazev']= 'th';
    $html= tab_show($tab,"náklady $rok",$sloupce,$sloupce_attr,'stat',$tab_class);
  }
  else
    $html= tab_show($tab,"náklady $rok",$mesice,$mesice_attr,'stat',$tab_class);
  return $html;
}
# ================================================================================================== BILANCE
# -------------------------------------------------------------------------------------------------- prijmy_mesic
# měsíční přehled výnosů
function prijmy_mesic($rok,$co='%',$presnost=3) { #trace();
  global $mesice, $mesice_attr;
  $tab= ucet_mesic($rok,$co,$presnost,'','6');
  if ( $presnost==6 ) {
    $sloupce= $mesice;
    $sloupce['nazev']= 'účet';
    $sloupce_attr= $mesice_attr;
    $sloupce_attr['nazev']= 'th';
    $html= tab_show($tab,"výnosy $rok",$sloupce,$sloupce_attr,'stat');
  }
  else
    $html= tab_show($tab,"výnosy $rok",$mesice,$mesice_attr,'stat');
  return $html;
}
# -------------------------------------------------------------------------------------------------- naklady_mesic_diff
# měsíční přehled nákladů dvou roků
function naklady_mesic_diff($rok1,$rok2,$co='%',$presnost=3) { #trace();
  $html= naklady_mesic($rok1,$co,$presnost);
  $html.= "<br><br>";
  $html.= naklady_mesic($rok2,$co,$presnost);
  return $html;
}
# -------------------------------------------------------------------------------------------------- prijmy_mesic_diff
# měsíční přehled výnosů dvou roků
function prijmy_mesic_diff($rok1,$rok2,$co='%',$presnost=3) { #trace();
  $html= prijmy_mesic($rok1,$co,$presnost);
  $html.= "<br><br>";
  $html.= prijmy_mesic($rok2,$co,$presnost);
  return $html;
}
# -------------------------------------------------------------------------------------------------- ucet_suma
# součet částek
function ucet_suma($rok,$cond,$query) { #trace();
  $where= $cond . ($query ? " AND $query " : '')." AND id_udenik BETWEEN {$rok}00000 AND {$rok}99999";
  $join= " LEFT JOIN uakce AS a ON a.rok=left(id_udenik,4) AND akce=d.Cinnost";
  // náklady
  $qry= "SELECT sum(castka) as s FROM udenik AS d $join WHERE $where AND left(md,1)='5' ";
  $row= mysql_row($qry);
  $nak= $row['s'];
  $naklad= number_format($s= $nak, 0, '.', ' ');
  // výnosy
  $qry= "SELECT sum(castka) as s FROM udenik AS d $join WHERE $where AND left(dal,1)='6' ";
  $row= mysql_row($qry);
  $vyn= $row['s'];
  $vynos= number_format($s= $vyn, 0, '.', ' ');
  // peníze
  $qry= "SELECT sum(castka) as s FROM udenik AS d $join WHERE $where AND left(dal,1)='2' ";
  $row= mysql_row($qry);
  $plus= $row['s'];
  $qry= "SELECT sum(castka) as s FROM udenik AS d $join WHERE $where AND left(md,1)='2' AND dal!=961000";
  $row= mysql_row($qry);
  $minus= $row['s'];
  $penize= number_format($s= $plus-$minus, 0, '.', ' ');
  // obrat
  $obrat= number_format($s= $vyn-$nak, 0, '.', ' ');
  $result= (object)array('naklad'=>$naklad,'vynos'=>$vynos,'penize'=>$penize,'obrat'=>$obrat);
  return $result;
}
# -------------------------------------------------------------------------------------------------- ucet_surv
# přehled MS a DS
function ucet_surv($rok,$md,$dal,$presnost) { #trace();
  $tab1= array();
  $tab2= array();
  $html.= "<div class='CClass'>";
  if ( $presnost==6 ) {
    $qry= "SELECT left(md,$presnost) as ucet,sum(castka) as suma, nazev, u.cinnost as cin
           FROM udenik LEFT JOIN uosnova AS u ON ucet=md AND rok=$rok
           WHERE md LIKE '$md%' AND id_udenik BETWEEN {$rok}00000 AND {$rok}99999
           GROUP BY left(md,$presnost)";
    $res= mysql_qry($qry);
    while ( $res && $u= mysql_fetch_object($res) ) {
      $tab1[$u->ucet]['nazev']= $u->nazev;
      $tab1[$u->ucet]['md']= $u->suma;
      $tab1[$u->ucet]['info']= $u->cin;
    }
    ksort($tab1);
    $html.= "<h3 class='CTitle'>Náklady</h3>";
    $html.= tab_show($tab1,'účet',array('nazev'=>'název','md'=>'MD')
      ,array('nazev'=>'th','md'=>'right','dal'=>'right'),'stat');
    $qry= "SELECT left(dal,$presnost) as ucet,sum(castka) as suma, nazev, u.cinnost as cin
           FROM udenik JOIN uosnova AS u ON ucet=dal AND rok=$rok
           WHERE dal LIKE '$dal%' AND id_udenik BETWEEN {$rok}00000 AND {$rok}99999
           GROUP BY left(dal,$presnost)";
    $res= mysql_qry($qry);
    while ( $res && $u= mysql_fetch_object($res) ) {
      $tab2[$u->ucet]['nazev']= $u->nazev;
      $tab2[$u->ucet]['dal']= $u->suma;
      $tab2[$u->ucet]['info']= $u->cin;
    }
    ksort($tab2);
    $html.= "<h3 class='CTitle'>Výnosy</h3>";
    $html.= tab_show($tab2,'účet',array('nazev'=>'název','dal'=>'DAL')
      ,array('nazev'=>'th','md'=>'right','dal'=>'right'),'stat');
  }
  else {
    $qry= "SELECT left(md,$presnost) as ucet,sum(castka) as suma
           FROM udenik WHERE md LIKE '$md%' AND id_udenik BETWEEN {$rok}00000 AND {$rok}99999
           GROUP BY left(md,$presnost)";
    $res= mysql_qry($qry);
    while ( $res && $u= mysql_fetch_object($res) ) {
      $tab1[$u->ucet]['md']= $u->suma;
    }
    ksort($tab1);
    $html.= "<h3 class='CTitle'>Náklady</h3>";
    $html.= tab_show($tab1,'účet',array('md'=>'MD')
      ,array('md'=>'right','dal'=>'right'),'stat');
    $qry= "SELECT left(dal,$presnost) as ucet,sum(castka) as suma
           FROM udenik WHERE dal LIKE '$dal%' AND id_udenik BETWEEN {$rok}00000 AND {$rok}99999
           GROUP BY left(dal,$presnost)";
    $res= mysql_qry($qry);
    while ( $res && $u= mysql_fetch_object($res) ) {
      $tab2[$u->ucet]['dal']= $u->suma;
    }
    ksort($tab2);
    $html.= "<h3 class='CTitle'>Výnosy</h3>";
    $html.= tab_show($tab2,'účet',array('dal'=>'DAL')
      ,array('md'=>'right','dal'=>'right'),'stat');
  }
  $html.= "</div>";
  return $html;
}
# -------------------------------------------------------------------------------------------------- ucet_surv_diff
# přehled MS a DS
function ucet_surv_diff($rok1,$rok2) { #trace();
  $tab1= array();
  $tab2= array();
  $html.= "<div class='CClass'>";
  $qry= "SELECT md as ucet,sum(castka) as suma, nazev, left(id_udenik,4) as rok
         FROM udenik LEFT JOIN uosnova AS u ON ucet=md AND rok=$rok2
         WHERE md LIKE '5%' AND id_udenik BETWEEN {$rok1}00000 AND {$rok2}99999
         GROUP BY md,left(id_udenik,4)";
  $res= mysql_qry($qry);
  while ( $res && $u= mysql_fetch_object($res) ) {
    $tab1[$u->ucet]['nazev']= $u->nazev;
    $tab1[$u->ucet][$u->rok]= $u->suma;
    $tab1[$u->ucet]['info']= $u->cin;
  }
  ksort($tab1);
  $html.= "<h3 class='CTitle'>Náklady</h3>";
  $html.= tab_show($tab1,'účet',array('nazev'=>'název',$rok1=>$rok1,$rok2=>$rok2)
    ,array('nazev'=>'th',$rok1=>'right',$rok2=>'right'),'stat');
  $qry= "SELECT dal as ucet,sum(castka) as suma, nazev, left(id_udenik,4) as rok
         FROM udenik JOIN uosnova AS u ON ucet=dal AND rok=$rok2
         WHERE dal LIKE '6%' AND id_udenik BETWEEN {$rok1}00000 AND {$rok2}99999
         GROUP BY dal,left(id_udenik,4)";
  $res= mysql_qry($qry);
  while ( $res && $u= mysql_fetch_object($res) ) {
    $tab2[$u->ucet]['nazev']= $u->nazev;
    $tab2[$u->ucet][$u->rok]= $u->suma;
    $tab2[$u->ucet]['info']= $u->cin;
  }
  ksort($tab2);
  $html.= "<h3 class='CTitle'>Výnosy</h3>";
  $html.= tab_show($tab2,'účet',array('nazev'=>'název',$rok1=>$rok1,$rok2=>$rok2)
    ,array('nazev'=>'th',$rok1=>'right',$rok2=>'right'),'stat');
  $html.= "</div>";
  return $html;
}
# ================================================================================================== AKCE
# -------------------------------------------------------------------------------------------------- ucet_akce
# přehled
function ucet_akce($rok) { #trace();
  $tab= array();
  $tab_sum= array();
  $head= array('akce'=>'akce');
  $nazvy= array('typ'=>'typ','od'=>'od','dnu'=>'dnů','nazev'=>'název akce'
    ,'osob'=>'osob','naklady'=>'náklady'
    ,'vynosy'=>'výnosy','nedot'=>'bez dotací','dotace'=>'dotace','vysledek'=>'výsledek');
  $align= array('typ'=>'left bold th','od'=>'th rtxt','dnu'=>'th rtxt','nazev'=>'left th'
    ,'osob'=>'rtxt th','naklady'=>'right','dotace'=>'right'
    ,'vynosy'=>'right','vysledek'=>'right bold sign','nedot'=>'right bold sign');
  // náklady
  $qry= "SELECT Cinnost,stredisko,sum(castka) as suma,typ,uakce.datum,dnu,nazev_akce,osob
         FROM udenik LEFT JOIN uakce ON uakce.rok=$rok AND akce=Cinnost
         WHERE left(md,1)='5' AND id_udenik BETWEEN {$rok}00000 AND {$rok}99999
         GROUP BY Cinnost,Stredisko ORDER BY Cinnost";
  $res= mysql_qry($qry);
  while ( $res && $u= mysql_fetch_object($res) ) {
    $od= $u->datum;
    if ( $od && $od!='0000-00-00' ) {
      $m= 0+substr($od,5,2);
      $d= 0+substr($od,8,2);
      $od= "$d/$m";
    }
    else $od= '';
    $tab[$u->Cinnost]['typ']= $u->typ;
    $tab[$u->Cinnost]['od']= $od;
    $tab[$u->Cinnost]['dnu']= $u->dnu ? $u->dnu : '';
    $tab[$u->Cinnost]['osob']= $u->osob ? $u->osob : '';
    $tab[$u->Cinnost]['nazev']= $u->nazev_akce ? $u->nazev_akce : '?';
    $tab[$u->Cinnost]['ndotace']+= $u->stredisko=='' ? 0 : $u->suma;
    $tab[$u->Cinnost]['naklady']+= $u->suma;
    $tab_sum[$u->typ]['ndotace']+= $u->stredisko=='' ? 0 : $u->suma;
    $tab_sum[$u->typ]['naklady']+= $u->suma;
    $tab_sum['*celkem']['ndotace']+= $u->stredisko=='' ? 0 : $u->suma;
    $tab_sum['*celkem']['naklady']+= $u->suma;
  }
  // výnosy
  $qry= "SELECT Cinnost,stredisko,sum(castka) as suma,typ,nazev_akce
         FROM udenik LEFT JOIN uakce ON uakce.rok=$rok AND akce=Cinnost
         WHERE left(dal,1)='6' AND id_udenik BETWEEN {$rok}00000 AND {$rok}99999
         GROUP BY Cinnost,Stredisko ORDER BY Cinnost";
  $res= mysql_qry($qry);
  while ( $res && $u= mysql_fetch_object($res) ) {
    $tab[$u->Cinnost]['typ']= $u->typ;
    $tab[$u->Cinnost]['nazev']= $u->nazev_akce ? $u->nazev_akce : '?';
    $tab[$u->Cinnost]['vdotace']+= $u->stredisko=='' ? 0 : $u->suma;
    $tab[$u->Cinnost]['vynosy']+= $u->stredisko=='' ? $u->suma : 0;
    $tab_sum[$u->typ]['vdotace']+= $u->stredisko=='' ? 0 : $u->suma;
    $tab_sum[$u->typ]['vynosy']+= $u->stredisko=='' ? $u->suma : 0;
    $tab_sum['*celkem']['vdotace']+= $u->stredisko=='' ? 0 : $u->suma;
    $tab_sum['*celkem']['vynosy']+= $u->stredisko=='' ? $u->suma : 0;
  }
  // doplnění názvů typů
  $qry= "SELECT * FROM uakce WHERE rok=$rok AND NOT akce REGEXP '[0-9]+'";
  $res= mysql_qry($qry);
  while ( $res && $u= mysql_fetch_object($res) ) {
    $tab_sum[$u->akce]['nazev']= $u->nazev_akce;
  }
  // výpočet výsledku
  foreach($tab as $i => $row) {
    $tab[$i]['nedot']= $row['vynosy']-$row['naklady'];
    $tab[$i]['dotace']= $row['ndotace'];
    $tab[$i]['vysledek']= $row['vynosy']+$row['ndotace']-$row['naklady'];
  }
  foreach($tab_sum as $i => $row) {
    $tab_sum[$i]['nedot']= $row['vynosy']-$row['naklady'];
    $tab_sum[$i]['dotace']= $row['ndotace'];
    $tab_sum[$i]['vysledek']= $row['vynosy']+$row['ndotace']-$row['naklady'];
  }
  $tab_sum['*celkem']['nedot']= $tab_sum['*celkem']['vynosy']-$tab_sum['*celkem']['naklady'];
  // upozornění na chybu zaúčtování
  if ( $tab['']['vynosy'] || $tab['']['naklady'] )
    $tab['']['nazev']= '(nedefinovaná akce)';
  // seřazení
  ksort($tab);
  ksort($tab_sum);
//                                                           debug($tab_sum);
  // tisk tabulek
  // akce
  $html.= "<h3 class='CTitle'>Jednotlivé akce roku $rok</h3>";
  $html.= tab_show($tab,'akce',$nazvy,$align,'stat');
  $html.= "</div>";
  // přehled
  $prehled= "<div class='CClass'>";
  $prehled.= "<h3 class='CTitle'>Přehled podle typů akcí pro rok $rok</h3>";
  unset($nazvy['typ'],$nazvy['od'],$nazvy['dnu'],$nazvy['osob']);
  $prehled.= tab_show($tab_sum,'typ',$nazvy,$align,'stat');
  return $prehled.$html;
}
# -------------------------------------------------------------------------------------------------- tab_show
# ukáže tabulku ve formátu HTML
function tab_show($tab,$row,$clmn,$align,$class='',$tab_class=null) {
                                                                debug($tab_class,'odhad');
  // nadpis
  $class= $class ? "class='$class'" : '';
  $t= "<table bgcolor='#fff' $class><tr><th>$row</th>";
  foreach ($clmn as $j => $nazev) $t.= "<th>$nazev</th>";
  $t.= "</tr>";
  // tělo
  foreach ($tab as $i => $row) if ( substr($i,0,1)!='*' ) {
    $t.= tab_show_tr($i,$row,$clmn,$align,$tab_class);
  }
  // patička (index začíná *)
  foreach ($tab as $i => $row) if ( substr($i,0,1)=='*' ) {
    $t.= tab_show_tr(substr($i,1),$row,$clmn,$align);
  }
  // redakce
  $t.= "</table>";
  return $t;
}
# ------------------------------------------------------------------------------ tab_show_tr
function tab_show_tr($i,$row,$clmn,$align,$tab_class=null) {
  global $tisice;
  $t.= "<tr><th>$i</th>";
  foreach ($clmn as $j => $nic) {
    $cislo= strstr($align[$j],'right')!==false;
    $rtxt= strstr($align[$j],'rtxt')!==false;
    $v= $row[$j];
    $num= $val= $v;
    $atr= '';
    if ( $cislo || $rtxt ) {
      $atr= "style='text-align:right'";
    }
    if ( $cislo ) {
      if ( $tisice && !$one ) $val= $val/1000;
      $val= number_format(round($val), 0, '.', ' ');
    }
    $td= strstr($align[$j],'th')===false ? 'td' : 'th';
    if ( strstr($align[$j],'sign')!==false && $num<0 ) $val= "<font color='red'>$val</font>";
    if ( strstr($align[$j],'bold')!==false ) $val= "<b>$val</b>";
    if ( $tab_class && isset($tab_class[$i][$j]) )
      $atr.= " class='{$tab_class[$i][$j]}'";
    $t.= "<$td $atr>$val</$td>";
  }
  $t.= "</tr>";
  return $t;
}
# ================================================================================================== IMPORTY
# -------------------------------------------------------------------------------------------------- ucet_load_osnova
# import osnovy
function ucet_load_osnova($rok) { #trace();
  // import účetního deníku
  global $ezer_path_docs;
  $fname= "$ezer_path_docs/{$rok}_osnova.txt";
  $f= fopen($fname, "r");
  if ( $f ) {
    $html.= "importuji ze souboru $fname ... ";
    $line= 0;
    $values= ''; $del= '';
    while (($data= fgetcsv($f, 1000, ";")) !== false) {
//                                                   debug($data);
      $line++;
      if ( $line==1 ) continue; // vynechání hlaviček
      $num= 12; // count($data);
      if ( !$num ) break;
      $value= ''; $del1= '';
      $empty= true;
      for ($clmn= 0; $clmn < $num; $clmn++) {
        $val= $data[$clmn];
        if ( $val && $val!='@' && $val!='0' ) $empty= false;
        switch ($clmn) {
        case 3: case 5: case 7: case 9:          // názvy
          $val= win2utf($val,true);
        }
        $value.= "$del1 \"$val\"";
        $del1= ',';
      }
      // přidat jen neprázdné řádky
      if ( !$empty ) {
        $values.= "$del\n($rok,$value)";
        $del= ',';
      }
//       if ( $line>13 ) break;
    }
    $html.= "ok <br>";
    fclose($f);
    // smazání starých
    $qry= "DELETE FROM uosnova WHERE rok=$rok;";
    $res= mysql_qry($qry);
    if ( $res ) {
      $html.= "stará osnova roku $rok smazána<br>";
      // vložení nových
      $qry= "INSERT INTO uosnova VALUES $values;";
      $res= mysql_qry($qry);
      $n= mysql_affected_rows();
      if ( $res ) $html.= "vloženo $n řádků účetní osnovy<br>";
    }
  }
  else fce_error("importní soubor $fname neexistuje");
//   $html.= nl2br("<br>qry=\n$qry<br>");
  $result= (object)array('html'=>$html);
  return $result;
}
# -------------------------------------------------------------------------------------------------- ucet_load_akce
# import číselníku akcí ze souboru (před rokem 2010)
function ucet_load_akce($rok) {  #trace();
  global $ezer_path_docs;
  $fname= "$ezer_path_docs/{$rok}_ciselnik_akci.txt";
  $f= fopen($fname, "r");
  if ( $f ) {
    $html.= "importuji ze souboru $fname ... ";
    $line= 0;
    $values= ''; $del= '';
    while (($data= fgetcsv($f, 1000, ";")) !== false) {
      $values.= "$del ($rok,\"{$data[0]}\",\"$data[1]\",\"$data[2]\",\"$data[3]\",\"$data[4]\",\"$data[5]\")";
      $del= ',';
    }
    $html.= "ok <br>";
    fclose($f);
    // smazání starých
    $qry= "DELETE FROM uakce WHERE rok=$rok;";
    $res= mysql_qry($qry);
    if ( $res ) {
      $html.= "starý číselník akcí smazán<br>";
      // vložení nového
      $qry= "INSERT INTO uakce (rok,akce,nazev_akce,datum,dnu,osob,typ) VALUES $values;";
      $res= mysql_qry($qry);
      $n= mysql_affected_rows();
      if ( $res ) $html.= "vloženo $n popisů akcí<br>";
    }
  }
  else fce_error("importní soubor $fname neexistuje");
//   $html.= nl2br("<br>qry=\n$qry<br>");
  $result= (object)array('html'=>$html);
  return $result;
}
# -------------------------------------------------------------------------------------------------- ucet_load_akce2
# import číselníku akcí z intranetu (od roku 2010)
function ucet_load_akce2($rok) {  #trace();
  $n= 0;
  $cells= google_sheet($rok,"ciselnik_akci",'answer@smidek.eu');
  if ( $cells ) {
    list($max_A,$max_n)= $cells['dim'];
//                                                 debug($cells,"akce $rok");
    // zrušení daného roku v UAKCE
    $qry= "DELETE FROM uakce WHERE rok=$rok";
    $res= mysql_qry($qry);
    if ( $res ) {
      $html.= "starý číselník akcí smazán<br>";
      // výběr a-záznamů a zápis do GAKCE
      $values= ''; $del= '';
      for ($i= 1; $i<$max_n; $i++) {
        $x= $cells['A'][$i];
        if ( strpos(' aru',$x) ) {
          $n++;
          $od= $dnu= $typ= $kap= '';
          $akce= $cells['B'][$i];
          $nazev= mysql_real_escape_string($cells['C'][$i]);
          if ( $cells['D'][$i] ) {
            $od= $cells['D'][$i];
            if ( $do= $cells['E'][$i] ) {
              $dt1= stamp_date($od,1);
              $dt2= stamp_date($do,1);
              $dnu= ($dt2-$dt1)/(60*60*24);
//                                         display("$od=$dt1,$do=$dt2,$dnu=".($dt2-$dt1));
            }
            $od= sql_date1($od,1);
          }
          $typ= $cells['G'][$i];
          $kap= $cells['H'][$i];
          $values.= "$del('$akce','$rok',\"$nazev\",'$od','$dnu','$typ','$kap')";
          $del= ',';
        }
      }
      $qry= "INSERT INTO uakce (akce,rok,nazev_akce,datum,dnu,typ,kapitola) VALUES $values";
      $res= mysql_qry($qry);
      $n= mysql_affected_rows();
      if ( $res ) $html.= "vloženo $n popisů akcí<br>";
    }
  }
  else fce_error("číselník akcí pro rok $rok nelze na intranetu najít");
  $result= (object)array('html'=>$html);
  return $result;
}
# -------------------------------------------------------------------------------------------------- ucet_load_denik
# import účetního deníku a osnovy
function ucet_load_denik($rok) { #trace();
  // import účetního deníku
  global $ezer_path_docs;
  $err= "";
  $sloupcu= 38;
  $fname= "$ezer_path_docs/{$rok}_udenik.txt";
  $f= fopen($fname, "r");
  if ( !$f ) { $err= "importní soubor $fname neexistuje"; goto end; }
  $html.= "import ze souboru $fname ... ";
  $line= 0;
  $values= ''; $del= '';
  while (($data= fgetcsv($f, 1000, ";")) !== false) {
//                                                   debug($data,$line+1);
    $line++;
    $num= count($data);
    if ( $line==1 ) {
      // kontrola hlaviček
                                                        debug($data);
      if ( $num!=$sloupcu ) { // problém
        $err= "soubor $fname má $num sloupců - očekává se $sloupcu";
        goto end_read;
      }
      continue; // vynechání hlaviček
    }
    $key= sprintf("%04d%05d",$rok,$line);
    if ( !$num ) break;
    $value= '';
    $empty= true;
    for ($clmn= 0; $clmn < $num; $clmn++) {
      $val= $data[$clmn];
      if ( $val && $val!='@' && $val!='0' ) $empty= false;
      switch ($clmn) {
      case 4: case 5:                   // datum
        $val= substr(sql_time($val,1),0,10);
        break;
      case 14: case 20:                 // Kč
        $val= strtr($val,array(","=>"."," "=>""));
        break;
//         $val= substr(str_replace(",",".",$val),0,-3); break;
      case 24:                          // dotace
        if ( substr($val,2,7)=='Zak0000' ) {
          $n= substr($val,-1,1);
          $val= strtr($n,array('1'=>'MS','2'=>'DS','3'=>'DH'));
        }
        else if ( $val=='' ) {
          $val= 'x';
        }
        break;
      }
      $value.= ', "'.win2utf($val,true).'"';
    }
    // přidat jen neprázdné řádky
    if ( !$empty ) {
      $values.= "$del\n(\"$key\"$value)";
      $del= ',';
    }
//     if ( $line>20 ) break;
  }
  $html.= "ok <br>";
end_read:
  fclose($f);
  if ( $err ) goto end;
  // smazání starých
  $qry= "DELETE FROM udenik WHERE id_udenik BETWEEN {$rok}00000 AND {$rok}99999;";
  $res= mysql_qry($qry);
  if ( $res ) {
    $html.= "rok $rok smazán<br>";
    // vložení nových
    $qry= "INSERT INTO udenik VALUES $values;";
    $res= @mysql_qry($qry,0,'-'); if ( !$res ) { $err= mysql_error(); goto end; }
    $n= mysql_affected_rows();
    if ( $res ) $html.= "pro rok $rok vloženo $n řádků<br>";
  }
end:
  if ( $err ) {
    $html.= "<br><br><div style='color:red'>IMPORT SKONČIL CHYBOU: $err</div>";
  }
  $result= (object)array('html'=>$html);
  return $result;
}
# -------------------------------------------------------------------------------------------------- ucet_load_odhad
# import účetního odhadu
function ucet_load_odhad($rok) { #trace();
  // import účetního deníku
  global $ezer_path_docs;
  $fname= "$ezer_path_docs/{$rok}_uodhad.csv";
  $f= fopen($fname, "r");
  if ( $f ) {
    $html.= "importuji ze souboru $fname ... ";
    $line= 0;
    $values= ''; $del= '';
    while (($data= fgetcsv($f, 1000, ";")) !== false) {
//                                                   debug($data);
      $line++;
      if ( $line==1 ) continue; // vynechání hlaviček
      $num= count($data);
      if ( !$num ) break;
      $value= '';
      $empty= true;
      // načtení masky odhadu
      $typ=     $data[0];                 // m=měsíční náklad, j=jednorázový náklad, x...= vynechat
      if ( substr($typ,0,1)=='x' ) continue;
      $akce=    $data[1];                 // zařazení podle číselníku akcí
      list($day,$month,$year)= explode('.',$data[2]);
      $castka=  $data[3];
      $md=      $data[4];
      $Zakazka= $data[5];
      $Stredisko= $data[6];
      $poznamka= win2utf($data[7],true);
      // vložení podle typu
      $count= $typ=='j' ? 1 : (13-$month);
      for ($i= 0; $i<$count; $i++) {
        $datum= sprintf("%04d-%02d-%02d", $year, $month+$i, $day);
        $values.= "$del\n('$datum',$castka,'$md','$Zakazka','$Stredisko','$poznamka')";
        $del= ',';
      }
    }
    $html.= "ok <br>";
    fclose($f);
    // smazání starých
    $qry= "DELETE FROM uodhad WHERE year(datum)=$rok";
    $res= mysql_qry($qry);
    if ( $res ) {
      $html.= "rok $rok smazán<br>";
      // vložení nových
      $qry= "INSERT INTO uodhad (datum,castka,md,Zakazka,Stredisko,poznamka) VALUES $values;";
      $res= mysql_qry($qry);
      $n= mysql_affected_rows();
      if ( $res ) $html.= "pro odhad roku $rok vloženo $n řádků<br>";
    }
  }
  else fce_error("importní soubor $fname neexistuje");
//   $html.= nl2br("<br>qry=\n$qry<br>");
  $result= (object)array('html'=>$html);
  return $result;
}
?>
