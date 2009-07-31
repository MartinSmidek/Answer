<?php # (c) 2007-2009 Martin Smidek <martin@smidek.eu>
# -------------------------------------------------------------------------------------------------- ucet_menu
function ucet_menu($k1,$k2,$k3) {
  return menu_definition($k1,$k2,$k3,<<<__END
    [
      {group:'Účetní deník',entries:[
        {title:'Rok 2008'                   ,entry:'aktivni',keys:['u-show','2008']},
        {title:'Rok 2009'                   ,entry:'aktivni',keys:['u-show','2009']},
        {title:'Import deníku a osnovy'     ,entry:'aktivni',keys:['u-load',''],skill:'yui'}
      ]},
      {group:'Projekty MPSV',entries:[
        {title:'MS 2008'                    ,entry:'aktivni',keys:['u-proj','2008,MS']},
        {title:'DS 2009'                    ,entry:'aktivni',keys:['u-proj','2008,DS']},
        {title:'MS 2008'                    ,entry:'aktivni',keys:['u-proj','2009,MS']},
        {title:'DS 2009'                    ,entry:'aktivni',keys:['u-proj','2009,DS']}
      ]},
      {group:'Přehledy',entries:[
        {title:'náklady a výnosy akcí'      ,entry:'aktivni',keys:['u-akce','1']},
        {title:'náklady a výnosy (1)'       ,entry:'aktivni',keys:['u-surv','1']},
        {title:'náklady a výnosy (2)'       ,entry:'aktivni',keys:['u-surv','2']},
        {title:'náklady a výnosy (3)'       ,entry:'aktivni',keys:['u-surv','3']},
        {title:'náklady a výnosy (4)'       ,entry:'aktivni',keys:['u-surv','4']},
        {title:'náklady a výnosy (5)'       ,entry:'aktivni',keys:['u-surv','5']},
        {title:'náklady a výnosy s osnovou' ,entry:'aktivni',keys:['u-surv','6']},
        {title:'-"- loni a letos'           ,entry:'aktivni',keys:['u-srov','6']}
//       ]},
//       {group:'MS & DS',entries:[
//         {title:'bilance MS & DS'         ,entry:'aktivni',keys:['msds','1']}
      ]},
      {group:'Měsíční náklady',entries:[
        {title:'celkové náklady'            ,entry:'aktivni',keys:['u-mesice','%']},
        {title:'náklady MS'                 ,entry:'aktivni',keys:['u-mesice','MS']},
        {title:'náklady DS'                 ,entry:'aktivni',keys:['u-mesice','DS']},
        {title:'náklady DH'                 ,entry:'aktivni',keys:['u-mesice','DH']},
        {title:'náklady nezařazené'         ,entry:'aktivni',keys:['u-mesice','x']},
        {title:'celkové náklady vers. loni' ,entry:'aktivni',keys:['u-mesice','+%']},
        {title:'náklady MS vers. loni'      ,entry:'aktivni',keys:['u-mesice','+MS']},
        {title:'náklady DS vers. loni'      ,entry:'aktivni',keys:['u-mesice','+DS']},
        {title:'náklady DH vers. loni'      ,entry:'aktivni',keys:['u-mesice','+DH']},
        {title:'nezařazené vers. loni'      ,entry:'aktivni',keys:['u-mesice','+x']}
      ]},
      {group:'Měsíční příjmy',entries:[
        {title:'celkové příjmy'             ,entry:'aktivni',keys:['u+mesice','%']},
        {title:'příjmy MS'                  ,entry:'aktivni',keys:['u+mesice','MS']},
        {title:'příjmy DS'                  ,entry:'aktivni',keys:['u+mesice','DS']},
        {title:'příjmy DH'                  ,entry:'aktivni',keys:['u+mesice','DH']},
        {title:'příjmy nezařazené'          ,entry:'aktivni',keys:['u+mesice','x']},
        {title:'celkové příjmy vers. loni'  ,entry:'aktivni',keys:['u+mesice','+%']},
        {title:'příjmy MS vers. loni'       ,entry:'aktivni',keys:['u+mesice','+MS']},
        {title:'příjmy DS vers. loni'       ,entry:'aktivni',keys:['u+mesice','+DS']},
        {title:'příjmy DH vers. loni'       ,entry:'aktivni',keys:['u+mesice','+DH']},
        {title:'nezařazené vers. loni'      ,entry:'aktivni',keys:['u+mesice','+x']}
      ]}
    ]
__END
  );
}
# -------------------------------------------------------------------------------------------------- ucet_todo
function ucet_todo($k1,$k2,$k3) {
  global $mesice, $mesice_attr;
  global $tisice;
  $tisice= true;
  $html= "<div class='CSection CMenu'>";
  $cond= " 1 ";
  $rok= $_SESSION['rok'];
                                                        display("rok={$_SESSION['rok']}");
  $mesice= array('1'=>'leden','2'=>'únor','3'=>'březen','4'=>'duben','5'=>'květen','6'=>'červen','7'=>'červenec','8'=>'srpen','9'=>'září','10'=>'říjen','11'=>'listopad','12'=>'prosinec');
  $mesice_attr= array('1'=>'right','2'=>'right','3'=>'right','4'=>'right','5'=>'right','6'=>'right','7'=>'right','8'=>'right','9'=>'right','10'=>'right','11'=>'right','12'=>'right');
  switch ( $k2 ) {
  case 'u-show':
    $html.= "<h3 class='CTitle'>Účetní deník YMCA Setkání pro rok $k3</h3>";
    $_SESSION['rok']= $k3;
                                                        display("{$_SESSION['rok']}:=$k3");
    $cond= " id_udenik BETWEEN {$k3}00000 AND {$k3}99999 ";
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
    $html.= ucet_surv_diff(2008,2009);
    break;
//   case 'msds':
//     $html.= "<h3 class='CTitle'>bilance MS & DS roku $rok</h3>";
//     $html.= ucet_surv($rok,"5","6",6,'Cinnost');
//     break;
  case 'u-akce':
    $html.= "<h3 class='CTitle'>Účetní přehledy akcí roku $rok</h3>";
    $html.= ucet_akce($rok);
    break;
  case 'u-mesice':
    if ( substr($k3,0,1)=='+' ) {
      $loni= $rok-1;
      $html.= "<h3 class='CTitle'>Měsíční přehledy výdajů roku $rok oproti $loni</h3>";
      $html.= naklady_mesic_diff($rok,$loni,substr($k3,1));
    }
    else {
      $html.= "<h3 class='CTitle'>Měsíční přehledy výdajů roku $rok</h3>";
      $html.= naklady_mesic($rok,$k3);
    }
    break;
  case 'u+mesice':
    if ( substr($k3,0,1)=='+' ) {
      $loni= $rok-1;
      $html.= "<h3 class='CTitle'>Měsíční přehledy příjmů roku $rok oproti $loni</h3>";
      $html.= prijmy_mesic_diff($rok,$loni,substr($k3,1));
    }
    else {
      $html.= "<h3 class='CTitle'>Měsíční přehledy výdajů roku $rok</h3>";
      $html.= prijmy_mesic($rok,$k3);
    }
    break;
  case 'u*mesice':
    if ( substr($k3,0,1)=='+' ) {
      $loni= $rok-1;
      $html.= "<h3 class='CTitle'>Přehledy měsíčních bilancí roku $rok oproti $loni</h3>";
      $html.= bilance_mesic_diff($rok,$loni,substr($k3,1));
    }
    else {
      $html.= "<h3 class='CTitle'>Měsíční přehledy výdajů roku $rok</h3>";
      $html.= bilance_mesic($rok,$k3);
    }
    break;
  case 'u-proj':
    list($rok,$cast)= explode(',',$k3);
    $html.= "<h3 class='CTitle'>Tabulka nákladů jednotlivých projektů v roce $rok</h3>";
    $html.= proj_export($rok,$cast);
    break;
  }
  $html.= "</div>";
  $result= (object)array('html'=>$html,'cond'=>$cond,'year'=>$_SESSION['rok']);
  return $result;
}
# ================================================================================================== PROJEKT
# -------------------------------------------------------------------------------------------------- proj_export
function proj_export($rok,$co) { trace();
  global $proj_tab, $ucty_tab, $tisice;
  $clmn_tab= array('MS/MPSV-MS'=>'MS.MPSV'
                  ,'MS/A1P1'=>'MS.MŠ1','MS/A1P2A'=>'MS.MŠ2a','MS/A1P2T'=>'MS.MŠ2t','MS/A1P3'=>'MS.MŠ3'
                  ,'MS/'=>'MS'
                  ,'DS/MPSV-DS'=>'DS.MPSV'
                  ,'DS/A1P2A'=>'DS.MŠ'
                  ,'DH/'=>'DH'
                  ,'DS/'=>'DS'
                  ,'x/'=>'');
  $ucty_tab= array();
  $proj_tab= array(
//
    "celkový objem" => array(
      "" => (object)array(),
      "a) osobní náklady" => array(
         "mzdové náklady" => array(
           "hrubé mzdy" => (object)array(              't'=>5212, 'u'=>array('521201|521202|521200')),
           "OON na DPČ" => (object)array(              't'=>5212, 'u'=>array('')),
           "OON na DPP" => (object)array(              't'=>5212, 'u'=>array('521221|521222|521220')),
           "jiné mzdové náklady" => (object)array(     't'=>5212, 'u'=>array())
         ),
         "odvody soc.,zdrav. poj." => array(
           "pojistné ke mzdám" => (object)array(       't'=>5242, 'u'=>array('524201|524221|524202|524222|524200|524220')),
           "pojistné k DPČ" => (object)array(          't'=>5242, 'u'=>array()),
           "jiné pojistné" => (object)array(           't'=>5242, 'u'=>array('527220'))
         ),
         "ostatní osobní náklady" => /*array (
             "" =>*/ (object)array(                    't'=>52,   'u'=>array()/*)*/
         )
      ),
      "b) provozní náklady" => array(
         "materiálové náklady" => array(
           "potraviny" => (object)array(               't'=>5012, 'u'=>array('501220|501221')),
           "kancelářské potřeby" => (object)array(     't'=>5012, 'u'=>array('501241|501240|501242')),
           "vyb. DDHM do 40 tKč" => (object)array(     't'=>5012, 'u'=>array('501231|501232|501230')),
           "pohonné hmoty" => (object)array(           't'=>5012, 'u'=>array('501251|501261|501250|501252|501260')),
           "výtvarný materiál" => (object)array(       't'=>5012, 'u'=>array('501204')),
           "jiný materiál" => (object)array(           't'=>5012, 'u'=>array('501200|501203'))
         ),
         "nemateriálové náklady" => array(
           "energie" => /*array(
             "" =>*/ (object)array(                    't'=>5022, 'u'=>array('502211|502210')/*)*/
           ),
           "opravy a udržování" => /*array(
             "" =>*/ (object)array(                    't'=>5112, 'u'=>array('511201|511211|511210|511221|511213|511200|511220')/*),*/
           ),
           "cestovné" => array(
             "cestovné zaměstnanců" => (object)array(),
             "jiné cestovné" => (object)array(         't'=>5122, 'u'=>array('512201|512202|512200'))
           ),
           "ostatní služby" => array(
             "spoje celkem" => (object)array(          't'=>5182, 'u'=>array('518214|518261|518215|518260|518210|518218')),
             "nájemné" => (object)array(               't'=>5182, 'u'=>array('518212|518216|518211|518217')),
             "právní a ek. služby" => (object)array(   't'=>5182, 'u'=>array('518241|518242|518240|518243')),
             "školení a kurzy" => (object)array(       't'=>5182, 'u'=>array('518291|518290')),
             "pořízení DNM do 60 tKč" => (object)array('t'=>5182, 'u'=>array('518231')),
             "tisk materiálů" => (object)array(        't'=>5182, 'u'=>array('518281|518280')),
             "ubytování a stravování" => (object)array('t'=>5189, 'u'=>array('518900|518901')),
             "svoz odpadu" => (object)array(           't'=>5182, 'u'=>array('518271|518270')),
             "jiné služby" => (object)array(           't'=>5182, 'u'=>array('518200|518202|518201'))
           ),
           "ostatní náklady" => array(
             "jiné ostatní náklady" => (object)array(  't'=>5, 'u'=>array('538200|549200|531100|532200|544100|549000|549300|581300|549999')),
             "odpisy" =>  (object)array(               't'=>5512, 'u'=>array('551200'))
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
  $html.= "<table class='stat' style='width:700px'><tr><th style='text-align:right'>$v</th><th>tř.</th>";
  foreach($clmn_tab as $clmn)
    $html.= "<th style='width:40px;text-align:right'>$clmn</th>";
  $html.= "</tr>";
  $html.= proj_print($proj_tab);
  $html.= "</table>";
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
function proj_make_vect($rok,$co,&$tab) { trace();
  global $proj_tab;
  $out= (object)array();
  if ( is_object($tab) ) {
    if ( $tab->u ) {
      // projdi seznam skupin účtů
      $tab->xu= $out->xu= array();
      for ($i= 0; $i<count($tab->u); $i++) {
        $out->xu[$i]= $tab->xu[$i]= naklad_vzor($rok,$co,$tab->u[$i]);
      }
    }
  }
  else if ( is_array($tab) ) {
    $n= 0; $m= 0;
    $xu= array();
    foreach($tab as $name=>$line) {
      $line_out= proj_make_vect($rok,$co,&$tab[$name]);
      $n++;
      $m= max($m,count($line_out->xu));
      for ($i= 0; $i<$m; $i++) {
        if ( !isset($xu[$i]) ) $xu[$i]= 0;
        $xu[$i]+= $line_out->xu[$i] ? $line_out->xu[$i] : 0;
      }
    }
    if ( !isset($tab[""]) ) $tab[""]= (object)array('xu'=>array());
    for ($i= 0; $i<$m; $i++) {
      $tab[""]->xu[$i]= $xu[$i];
      $out->xu[$i]= $xu[$i];
    }
  }
  return $out;
}
# -------------------------------------------------------------------------------------------------- proj_print
function proj_print($tab,$depth=0) { trace();
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
# $clmn_tab= array('MS/MPSV-MS','MS','DS/MPSV-DS','DS','DH','x');
function naklad_vzor($rok,$vzor,$clmn_tab) { trace();
  $suma= array();
  foreach ($clmn_tab as $indx=>$ii) $suma[$indx]= 0;
  if ( $vzor ) {
    $qry= "SELECT sum(castka) as suma,Zakazka,Stredisko
           FROM udenik WHERE md RLIKE '$vzor' AND id_udenik BETWEEN {$rok}00000 AND {$rok}99999
           GROUP BY Zakazka,Stredisko";
    $res= mysql_qry($qry);
    while ( $res && $u= mysql_fetch_object($res) ) {
      $indx= "{$u->Zakazka}/{$u->Stredisko}";
      if ( !isset($suma[$indx]) )
         fce_error("kombinace Zakazka/Stredisko=$indx - s tím se nepočítá");
      $suma[$indx]= $u->suma;
    }
                                                debug($suma,"$vzor/$co");
  }
  return $suma;
}
# ================================================================================================== MĚSÍC
# -------------------------------------------------------------------------------------------------- ucet_mesic
# měsíční přehled daného roku - může být buď '5..','' nebo '','6..'
function ucet_mesic($rok,$co='%',$presnost=3,$md='5',$dal='') { trace();
  $tab= array();
  if ( $md )
    $qry= "SELECT left(md,$presnost) as ucet,sum(castka) as suma,month(datum) as mesic
           FROM udenik WHERE md LIKE '$md%' AND id_udenik BETWEEN {$rok}00000 AND {$rok}99999
           AND zakazka LIKE '$co'
           GROUP BY left(md,$presnost),month(datum)";
  else
    $qry= "SELECT left(dal,$presnost) as ucet,sum(castka) as suma,month(datum) as mesic
           FROM udenik WHERE dal LIKE '$dal%' AND id_udenik BETWEEN {$rok}00000 AND {$rok}99999
           AND zakazka LIKE '$co'
           GROUP BY left(md,$presnost),month(datum)";
  $res= mysql_qry($qry);
  while ( $res && $u= mysql_fetch_object($res) ) {
    $tab[$u->ucet][$u->mesic]= $u->suma;
    $tab['*celkem'][$u->mesic]+= $u->suma;
  }
  ksort($tab);
  return $tab;
}
# -------------------------------------------------------------------------------------------------- naklady_mesic
# měsíční přehled nákladů
function naklady_mesic($rok,$co='%',$presnost=6) { trace();
  global $mesice, $mesice_attr;
  $tab= ucet_mesic($rok,$co,$presnost,'5');
  $html= tab_show($tab,'výdaje'.substr($rok,-2,2),$mesice,$mesice_attr,'stat');
  return $html;
}
# ================================================================================================== BILANCE
# -------------------------------------------------------------------------------------------------- prijmy_mesic
# měsíční přehled příjmů
function prijmy_mesic($rok,$co='%',$presnost=3) { trace();
  global $mesice, $mesice_attr;
  $tab= ucet_mesic($rok,$co,$presnost,'','6');
  $html= tab_show($tab,'příjmy'.substr($rok,-2,2),$mesice,$mesice_attr,'stat');
  return $html;
}
# -------------------------------------------------------------------------------------------------- naklady_mesic_diff
# měsíční přehled nákladů dvou roků
function naklady_mesic_diff($rok1,$rok2,$co='%',$presnost=3) { trace();
  $html= naklady_mesic($rok1,$co,$presnost);
  $html.= "<br><br>";
  $html.= naklady_mesic($rok2,$co,$presnost);
  return $html;
}
# -------------------------------------------------------------------------------------------------- prijmy_mesic_diff
# měsíční přehled příjmů dvou roků
function prijmy_mesic_diff($rok1,$rok2,$co='%',$presnost=3) { trace();
  $html= prijmy_mesic($rok1,$co,$presnost);
  $html.= "<br><br>";
  $html.= prijmy_mesic($rok2,$co,$presnost);
  return $html;
}
# -------------------------------------------------------------------------------------------------- ucet_suma
# součet částek
function ucet_suma($rok,$cond,$query) { trace();
  $where= $cond . ($query ? " AND $query " : '')." AND id_udenik BETWEEN {$rok}00000 AND {$rok}99999";
  // náklady
  $qry= "SELECT sum(castka) as s FROM udenik WHERE $where AND left(md,1)='5' ";
  $row= mysql_row($qry);
  $nak= $row['s'];
  $naklad= number_format($s= $nak, 0, '.', ' ');
  // výnosy
  $qry= "SELECT sum(castka) as s FROM udenik WHERE $where AND left(dal,1)='6' ";
  $row= mysql_row($qry);
  $vyn= $row['s'];
  $vynos= number_format($s= $vyn, 0, '.', ' ');
  // peníze
  $qry= "SELECT sum(castka) as s FROM udenik WHERE $where AND left(dal,1)='2' ";
  $row= mysql_row($qry);
  $plus= $row['s'];
  $qry= "SELECT sum(castka) as s FROM udenik WHERE $where AND left(md,1)='2' AND dal!=961000";
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
function ucet_surv($rok,$md,$dal,$presnost) { trace();
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
function ucet_surv_diff($rok1,$rok2) { trace();
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
function ucet_akce($rok) { trace();
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
function tab_show($tab,$row,$clmn,$align,$class='') {
  // nadpis
  $class= $class ? "class='$class'" : '';
  $t= "<table bgcolor='#fff' $class><tr><th>$row</th>";
  foreach ($clmn as $j => $nazev) $t.= "<th>$nazev</th>";
  $t.= "</tr>";
  // tělo
  foreach ($tab as $i => $row) if ( substr($i,0,1)!='*' ) {
    $t.= tab_show_tr($i,$row,$clmn,$align);
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
function tab_show_tr($i,$row,$clmn,$align) {
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
    $t.= "<$td $atr>$val</$td>";
  }
  $t.= "</tr>";
  return $t;
}
# ================================================================================================== IMPORTY
# -------------------------------------------------------------------------------------------------- ucet_load_osnova
# import osnovy
function ucet_load_osnova($rok) { trace();
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
# import číselníku akcí
function ucet_load_akce($rok) {  trace();
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
      $qry= "INSERT INTO uakce VALUES $values;";
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
# -------------------------------------------------------------------------------------------------- ucet_load_denik
# import účetního deníku a osnovy
function ucet_load_denik($rok) { trace();
  // import účetního deníku
  global $ezer_path_docs;
  $fname= "$ezer_path_docs/{$rok}_udenik.txt";
  $f= fopen($fname, "r");
  if ( $f ) {
    $html.= "importuji ze souboru $fname ... ";
    $line= 0;
    $values= ''; $del= '';
    while (($data= fgetcsv($f, 1000, ";")) !== false) {
//                                                   debug($data);
      $line++;
      if ( $line==1 ) continue; // vynechání hlaviček
      $key= sprintf("%04d%05d",$rok,$line);
      $num= count($data);
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
          $val= substr(str_replace(",",".",$val),0,-3); break;
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
//       if ( $line>13 ) break;
    }
    $html.= "ok <br>";
    fclose($f);
    // smazání starých
    $qry= "DELETE FROM udenik WHERE id_udenik BETWEEN {$rok}00000 AND {$rok}99999;";
    $res= mysql_qry($qry);
    if ( $res ) {
      $html.= "rok $rok smazán<br>";
      // vložení nových
      $qry= "INSERT INTO udenik VALUES $values;";
      $res= mysql_qry($qry);
      $n= mysql_affected_rows();
      if ( $res ) $html.= "pro rok $rok vloženo $n řádků<br>";
    }
  }
  else fce_error("importní soubor $fname neexistuje");
//   $html.= nl2br("<br>qry=\n$qry<br>");
  $result= (object)array('html'=>$html);
  return $result;
}
?>
