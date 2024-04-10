<?php # (c) 2009-2015 Martin Smidek <martin@smidek.eu>
/** =======================================================================================> FAKTURY **/
# typ:T|I, zarovnání:L|C|R, písmo, l, t, w, h, border:LRTB
$ds2_faktura_dfl= 'T,L,3.5,10,10,0,0,,1.5';
$ds2_faktura_fld= [
//  'logo' => ['I,,,15,13,20,17',"
//      img/YMCA.png"],
  'logo' => ['I,,,13,10,25,32',"
      img/logo_ds.jpg"],
  'kontakt' => [',,,42,10,200,50',"
      <b>Dům setkání</b><i>
      <br>Dolní Albeřice 1, 542 26 Horní Maršov
      <br>telefon: 736 537 122
      <br>dum@setkani.org
      <br>https://dum.setkani.org"],
  'faktura' => [',R,5,110,25,85,10',"
      <b>Faktura {faktura}</b>"],
  'dodavatel' => [',,,13,45,70,30',"
      <b>Dodavatel</b>
      <br>YMCA Setkání, spolek
      <br>Talichova 53, 623 00 Brno
      <br>zaregistrovaný Krajským soudem v Brně
      <br>spisová značka: L 8556
      <br>IČ: 26531135 DIČ: CZ26531135"],
  'odberatel' => [',,,112,40,83,10',"
      <b>Odběratel</b>  &nbsp;  {ic_dic}"],
  'ramecek' => [',,,112,47,83,35,LRTB',""],
  'platce' => [',,4.5,120,52,75,24',"{adresa}"],
  'platbaL' => [',,,13,92,40,30',"
      Peněžní ústav
      <br><b>Číslo účtu</b>
      <br>Konstatntní symbol
      <br>Variabilní symbol
      <br>Specifický symbol"],
  'platbaR' => [',,,45,92,70,30',"
      Fio banka, a.s.
      <br><b>2000465448/2010</b>
      <br>558
      <br>{VS}
      <br>{SS}"],
  'objednavkaL' => [',,,120,90,80,30',"
      <b>Objednávka číslo</b>"],
  'objednavkaR' => [',R,4.5,178,90,20,30',"
    <b>{obj}</b>"],
  'datumyL' => [',,,120,96,80,30',"
      <br>Dodací a platební podmínky: s daní
      <br>Datum vystavení
      <br>Datum zdanitelného plnění
      <br><b>Datum splatnosti</b>
      <br>Způsob platby"],
  'datumyR' => [',R,,170,96,28,30',"
      <br><br>{datum1}
      <br>{datum1}
      <br>{datum2}
      <br>bankovní převod"],
  'za_co' => [',,,13,132,120,10',"
      Za pobyt v Domě setkání ve dnech {obdobi} Vám fakturujeme:"],
  'tabulka' => [',,,13,140,184,150,,2',"
      {tabulka}"],
  'QR' => ['QR,,,13,220,40,40',     // viz https://qr-platba.cz/pro-vyvojare/specifikace-formatu/
      "SPD*1.0*ACC:{QR-IBAN}*RN:{QR-ds}*AM:{QR-castka}*CC:CZK*MSG:{QR-pozn}*X-VS:{QR-vs}*X-SS:{QR-ss}"],
    
  'vyrizuje' => [',,,13,270,100,10',"
      <b>Vyřizuje</b>
      <br>{vyrizuje}"],
  'pata' => [',C,,13,285,184,6,T,2',"
      Těšíme se na Váš další pobyt v Domě setkání"],
];
# -------------------------------------------------------------------------------------- ds2 faktura
# par.typ = konečná | záloha
function ds2_faktura($par) {  //debug($par,'ds2_faktura');
  global $ds2_faktura_dfl, $ds2_faktura_fld;
  // získání parametrů
  $show= $par->show??0;
  $save= $par->save??0;
  if ($par->typ=='konečná') {
    $ds2_faktura_fld['faktura'][1]= "<b>Faktura $par->num</b>";
    $ds2_faktura_fld['za_co'][1]= "Za pobyt v Domě setkání ve dnech {obdobi} Vám fakturujeme:";
    $vals= ds2_faktura_data($par);
  }
  else { // záloha
    $ds2_faktura_fld['faktura'][1]= "<b>Zálohová faktura $par->num</b>";
    $ds2_faktura_fld['za_co'][1]= "Fakturujeme Vám zálohu na pobyt v Domě setkání ve dnech {obdobi}:";
    $vals= ds2_faktura_data($par);
  }
  // QR platba
  $vals['{QR-IBAN}']= 'CZ1520100000002000465448'; // Dům setkání: 2000465448 / 2010
  $vals['{QR-ds}']= urlencode('YMCA Setkání');
  $vals['{QR-vs}']= $vals['{VS}']= $par->vs;
  $vals['{QR-ss}']= $vals['{SS}']= $par->ss;
  $vals['{QR-pozn}']= urlencode("platba za pobyt v Domě setkání");
  // doplnění obecných fakturačních údajů
  $vals['{datum1}']= date('j. n. Y');
  $vals['{datum2}']= date('j. n. Y',strtotime("+14 days"));
  // redakce faktury
  $lheight_tabulka= $vals['{polozek}']>7 ? 1.5 : 2;
  $html= '';
  if ($show) {
    $html.= "<div class='PDF' style='scale:85%;position:absolute'>";
    $html.= "<style>.PDF div{padding-top:1mm}</style>";
    $html.= "<div style='position:absolute;width:210mm;height:297mm;border:1px solid grey'>";
    $j= 'mm';
  }
  // zobrazení
  if ($save) {
    tc_page_open();
  }
  $x_dfl= explode(',',$ds2_faktura_dfl);
  foreach ($ds2_faktura_fld as $jmeno=>$cast) {
    $x= $x_dfl; 
  // doplnění podle defaultu
    foreach (explode(',',$cast[0]) as $i=>$c) {
      if ($c) $x[$i]= $c;
    }
//    debug($x,'$typ,$align,$fsize,$l,$t,$w,$h,$border');
    list($typ,$align,$fsize,$l,$t,$w,$h,$border,$lheight)= $x;
    if ($jmeno=='tabulka') $lheight= $lheight_tabulka;
    // parametrizace textu
    $text= strtr(trim($cast[1]),$vals);
    if ($show) {
      $bord= $algn= '';
  //    if ($border=='lrtb') $bord=";border:1px dotted black";
      if ($border) {
        if (strpos($border,'L')!==false) $bord.=";border-left:1px dotted black";
        if (strpos($border,'R')!==false) $bord.=";border-right:1px dotted black";
        if (strpos($border,'T')!==false) $bord.=";border-top:1px dotted black";
        if (strpos($border,'B')!==false) $bord.=";border-bottom:1px dotted black";
      }
      if ($align) $algn= ";text-align:".['L'=>'left','R'=>'right','C'=>'center'][$align];
      if ($typ=='T') {
        $elem= "<div style='position:absolute;"
            . "left:{$l}$j;top:{$t}$j;width:{$w}$j;height:{$h}$j;line-height:$lheight;"
            . "font-size:{$fsize}$j$bord$algn'>$text</div>";
  //      display(htmlentities($elem));
        $html.= $elem;
      }
      elseif ($typ=='I') {
        $elem= "<img src='$text' style='position:absolute;"
            . "left:{$l}$j;top:{$t}$j;width:{$w}$j;height:{$h}$j'>";
//        display(htmlentities($elem));
        $html.= $elem;
      }
      elseif ($typ=='QR') {
        $castka= ds2_kc($vals['{QR-castka}']);
        $elem= "<div style='position:absolute;"
            . "left:{$l}$j;top:{$t}$j;width:{$w}$j;height:{$h}$j;"
            . "font-size:{$fsize}$j;border:5px dotted black;text-align:center'>"
                . "<br>QR platba<br><br><b>$castka</b><br><br>bude zobrazena<br>v PDF</div>";
        $html.= $elem;
      }
    }
    if ($save) {
      tc_page_cell($text,$typ,$align,$fsize*2.4,$l,$t,$w,$h,$border,$lheight);
    }
  }
  if ($show) {
    $html.= "</div></div>";
  }
  if ($save) {
    global $abs_root;
    $fname= "fakt.pdf";
    $f_abs= "$abs_root/docs/$fname";
    $f_rel= "docs/$fname";
    tc_page_close($f_abs,$html);
    $html= "Fakturu lze stáhnout <a target='pdf' href='$f_rel'>zde</a><br>$html";
}
  return (object)array('html'=>$html,'err'=>'');
}
# --------------------------------------------------------------------------------- ds2 faktura_data
# vrátí data podle typu faktury
# zálohová: objednavka => odberatel, adresa, obdobi, QR-castka, tabulka
# konečná:  objednávka,osoba => adresa, obdobi, QR-castka, tabulka
function ds2_faktura_data($par) { 
  global $ds2_cena,$ds2_sazby;
  $order= $par->obj;
  $vals= [];
  $ds2_sazby= [];
  $sleva= 0;
  // položky faktury
  $polozky= array();
  if ($par->typ=='záloha') {
    // ------------------------------------------------------------ výpočty pro zálohovou fakturu
    $o= select_object('*','tx_gnalberice_order',"uid=$order",'setkani');
    $vals['{obdobi}']= date('j.n',$o->fromday).' - '.date('j.n.Y',$o->untilday);
    $vals['{ic_dic}']= ($o->ic ? "IČ: $o->ic" : '').($o->dic ? "    DIČ: $o->dic" : '');
    $vals['{adresa}']= wu(($o->org ? "$o->org" : '')
        . "<br>$o->firstname $o->name"
        . "<br>$o->address"
        . "<br>$o->zip $o->city");
    $dnu= round(($o->untilday-$o->fromday)/(60*60*24));
    // přečtení ceníku daného roku
    $rok= date('Y',$o->untilday);
    ds2_cenik($rok);
    if ( !count($ds2_cena) ) { fce_err("není ceník pro $rok"); goto end; }
    // ubytování může mít slevu
    $sleva= $o->sleva ? $o->sleva/100 : '';
    $polozky[]= ds2_c('noc_L',$dnu*($o->adults + $o->kids_10_15 + $o->kids_3_9),$sleva);
//    $polozky[]= ds2_c('noc_A',0,$sleva,1);
//    $polozky[]= ds2_c('noc_B',0,$sleva,1);
//    $polozky[]= ds2_c('noc_P',0,$sleva,1);
//    $polozky[]= ds2_c('noc_S',0,$sleva,1);
//    $polozky[]= ds2_c('noc_Z',0,$sleva,1);
    $polozky[]= ds2_c('ubyt_C',$dnu*($o->adults));
    $polozky[]= ds2_c('ubyt_S',$dnu*($o->adults));
    $n= $o->kids_10_15 + $o->kids_3_9 + $o->kids_3;
    if ($n) $polozky[]= ds2_c('ubyt_P',$dnu*$n);
    $n= $o->kids_3;
    if ($n) $polozky[]= ds2_c('noc_B',$dnu*$n,$sleva);
    switch ( $o->board ) {
    case 1:     // penze
      $polozky[]= ds2_c('strava_CC',$dnu*($o->adults+$o->kids_10_15));
      $n= $o->kids_3_9;
      if ($n) $polozky[]= ds2_c('strava_CD',$dnu*$n);
      break;
    case 2:     // polopenze
      $polozky[]= ds2_c('strava_PC',$dnu*($o->adults+$o->kids_10_15));
      $n= $o->kids_3_9;
      if ($n) $polozky[]= ds2_c('strava_PD',$dnu*$o->kids_3_9);
      break;
    }
  }
  else { // $par->typ=='konečná'
    // ---------------------------------------------------------------- výpočty pro konečnou fakturu
    $pobyt= ds2_cena_pobytu($order,$par->idos,0,2); // pro rodinu hosta
//    debug($pobyt,"ds2_cena_pobytu($order,$par->idos,0,2)");
    $fakt= $pobyt->fakt[2];
//    $host= $pobyt->host->host;
    $vals['{adresa}']= $pobyt->fields->adresa;
    $obdobi= $pobyt->obdobi;
    foreach ((array)$fakt as $pol=>list(,$pocet,)) {
      $polozky[]= ds2_c($pol,$pocet,$sleva);
    }
//    debug($polozky,'konečná - položky');
    $vals['{ic_dic}']= '';
  }
  // ------------------------------------------------------------------------------- redakce tabulky
  $celkem= 0;
  foreach ($polozky as $polozka) {
    $celkem+= $polozka[7];
  }
  $width= $sleva ? 67 : 67+12;
  $popisy= explode(',',
      "Položka:$width,Počet:12,Cena položky vč. DPH:26,"
      . ($sleva ? 'Sleva %:12,' : '')
      . 'Sazba DPH:14,DPH:25,Cena bez DPH:28');
  $lrtb= "border:0.1mm dotted black";
  $tab= '<table style="border-collapse:collapse" cellpadding="1mm">';
  $tab.= "<tr>";
  foreach ($popisy as $i=>$ts) {
    list($t,$s)= explode(':',$ts);
    $align= $i ? 'right' : 'left';
    $tab.= "<td align=\"$align\" style=\"$lrtb;width:{$s}mm\"><b>$t</b></td>";
  }
  $tab.= "</tr>";
  $tab.= "\n<tr>";
  for ($i= 0; $i<=6; $i++) {
    if (!$sleva && $i==3) continue;
    $align= $i ? 'right' : 'left';
    $nowrap= $i ? '' : ';text-wrap:nowrap';
    $tab.= "<td style=\"$lrtb$nowrap;text-align:$align\">";
    $del= '';
    foreach ($polozky as $polozka) {
      if ($polozka===null) continue;
      $tab.= "$del{$polozka[$i]}";
      $del= '<br>';
    }
    $tab.= "</td>";
  }
  $tab.= '</tr>';
  // součty
  $colspan= $sleva ? 'colspan="7"' : 'colspan="6"';
  $tab.= "<tr><td $colspan><br><br></td></tr>";
  if ($par->typ=='záloha') {
    $soucty= ['Celková cena s DPH'=>$celkem, 'Zaplaťte zálohu 50%'=>$celkem/2];
    $bold= 0;
    $koef= 0.5;
    $platit= $celkem*$koef;
  }
  else { // konečná
    $platit= $celkem - ($par->zaloha?:0);
    $soucty= ['Celková cena s DPH'=>$celkem, 'Zaplaceno zálohou'=>$par->zaloha?:0, 'Zbývá k zaplacení'=>$platit];
    $bold= 0;
    $koef= 1;
  }
  foreach ($soucty as $popis=>$castka) {
    $castka= ds2_kc($castka);
    if ($bold) {
      $popis= "<b>$popis</b>";
      $castka= "<b>$castka</b>";
    }
    $colspan= $sleva ? 'colspan="5"' : 'colspan="4"';
    $tab.= "<tr><td $colspan style=\"text-align:right\">$popis</td>"
      . "<td colspan=\"2\" align=\"right\" style=\"$lrtb\">$castka</td></tr>";
    $bold++;
  }
  // rozpisová tabulka DPH
  $rozpis= [-1=>['<b>Sazba</b>','<b>Daň</b>','<b>Základ</b>']];
  foreach ($ds2_sazby as $d=>$c) {
    $dan= round($c*$d/100,2);
    $rozpis[]= ["$d%",ds2_kc($dan*$koef),ds2_kc($c*$koef)];
  }
  $colspan= $sleva ? 'colspan="7"' : 'colspan="6"';
  $tab.= "<tr><td $colspan><br></td></tr>";
  $colspan= $sleva ? 'colspan="4"' : 'colspan="3"';
  $tab.= "<tr><td $colspan></td><td colspan=\"3\"><b>Rozpis DPH</b></td></tr>";
  $colspan= $sleva ? 'colspan="4"' : 'colspan="3"';
  foreach ($rozpis as $c) {
    $tab.= "<tr><td $colspan></td>"
      . "<td align=\"right\" style=\"$lrtb\">$c[0]</td>"
      . "<td align=\"right\" style=\"$lrtb\">$c[1]</td>"
      . "<td align=\"right\" style=\"$lrtb\">$c[2]</td>"
      . "</tr>";
  }
  $tab.= '</table>';
//  display($tab);
//                                              debug($x,'zaloha');
end:
  $vals['{obj}']= $order;
  $vals['{obdobi}']= $obdobi;
  $vals['{tabulka}']= $tab;
  $vals['{QR-castka}']= round($platit,2);
  // počet zúčtovaných položek ceníku kvůli řádkování tabulky
  $polozek= 0;
  foreach($polozky as $p) {
    if ($p) $polozek++;
  }
  $vals['{polozek}']= $polozek; 

//                                              debug($vals,'fakturujeme');
  return $vals;
}
# -------------------------------------------------------------------------------------------- ds2 c
# položka faktury
# id,pocet => název,cena,dph%,pocet
# inuly - zapsat do faktury i nuly
function ds2_c ($id,$pocet,$sleva='') { //trace();
  global $ds2_cena,$ds2_sazby;
  $kolik= $ds2_cena[$id]->polozka;
  $cena= $ds2_cena[$id]->cena;
  $sazba= $ds2_cena[$id]->dph;
  $x_dph=  round($pocet * ($cena - $cena / (1 + $sazba/100)),2);
  $x_cena= round($pocet*$cena - $x_dph,2);
  if (!isset($ds2_sazby[$sazba])) $ds2_sazby[$sazba]= 0;
  $ds2_sazby[$sazba]+= $x_cena;
  $c= [
    $kolik,
    $pocet,
    ds2_kc($cena),
    $sleva,
    $sazba.'%',
    ds2_kc($x_dph),
    ds2_kc($x_cena),
    $cena*$pocet, // 7: celková cena bez DPH
  ];
  return $cena*$pocet ? $c : null;
}
# ------------------------------------------------------------------------------------------- ds2 kc
function ds2_kc($c) {
  return number_format($c,2,'.',' ').' Kč';
}
/** ===========================================================================================> DŮM **/
# --------------------------------------------------------------------------------- ds2 compare_list
function ds2_compare_list($orders) {  #trace('','win1250');
  $errs= 0;
  $html= "<dl>";
  $n= 0;
  if ( $orders ) {
    foreach (explode(',',$orders) as $order) {
      $x= ds2_compare($order);
      $html.= /*w*u*/("<dt>Objednávka <b>$order</b> {$x->pozn}</dt>");
      $html.= "<dd>{$x->html}</dd>";
      $errs+= $x->err;
      $n++;
    }
  }
  $html.= "</dl>";
  $msg= "V tomto období je celkem $n objednávek";
  $msg.= $errs ? ", z toho $errs neúplné." : "." ;
  $result= (object)array('html'=>$html,'msg'=>/*w*u*/($msg));
  return $result;
}
# -------------------------------------------------------------------------------------- ds2 compare
function ds2_compare($order) {  #trace('','win1250');
  ezer_connect('setkani');
  // údaje z objednávky
  $qry= "SELECT * FROM tx_gnalberice_order WHERE uid=$order";
  $res= pdo_qry($qry);
  if ( !$res ) fce_error(/*w*u*/("$order není platné číslo objednávky"));
  $o= pdo_fetch_object($res);
  // projití seznamu
  $qry= "SELECT * FROM ds_osoba WHERE id_order=$order ";
  $reso= pdo_qry($qry);
  $n= $n_0= $n_3= $n_9= $n_15= $n_a= $noroom= 0;
  while ( $reso && $u= pdo_fetch_object($reso) ) {
    // rozdělení podle věku
    $n++;
    $vek= ds2_vek($u->narozeni,$o->fromday);
    if ( $vek>15 ) $n_a++;
    elseif ( $vek>9 ) $n_15++;
    elseif ( $vek>3 ) $n_9++;
    elseif ( $vek>0 ) $n_3++;
    else $n_0++;
    // kdo nebydlí?
    if ( !$u->pokoj ) $noroom++;
  }
  // posouzení počtů
  $no= $o->adults + $o->kids_10_15 + $o->kids_3_9 + $o->kids_3;
  $age= $n_a==$o->adults && $n_15==$o->kids_10_15 && $n_9==$o->kids_3_9 && $n_3==$o->kids_3;
  // zhodnocení úplnosti
  $err= $n==0 || $n>0 && $n!=$no || $noroom || $n_0 || $n>0 && !$age ? 1 : 0;
  // textová zpráva
  $html= '';
  $html.= $n==0 ? "Seznam účastníků je prázdný. " : '';
  $html.= $n>0 && $n!=$no ? "Seznam účastníků není úplný. " : '';
  $html.= $noroom ? "Jsou zde neubytovaní hosté. " : '';
  $html.= $n_0 ? "Někteří hosté nemají vyplněno datum narození. " : '';
  $html.= $n>0 && !$age ? "Stáří hostů se liší od předpokladů objednávky." : '';
  if ( !$html ) {
    $html= "Seznam účastníků odpovídá objednávce.";
    $pozn= " - <b style='color:green'>ok</b> ";
  }
  else {
    $pozn= $n ? " - aspoň něco" : " - nic";
  }
  $form= (object)array('adults'=>$n_a,'kids_10_15'=>$n_15,'kids_3_9'=>$n_9,'kids_3'=>$n_3,
    'nevek'=>$n_0, 'noroom'=>$noroom);
  $result= (object)array('html'=>/*w*u*/($html),'form'=>$form,'err'=>$err,'pozn'=>$pozn);
  return $result;
}
# ======================================================================================> objednávky
# ---------------------------------------------------------------------------------------- ds2 cenik
# načtení ceníku pro daný rok
function ds2_cenik($rok) {  #trace('','win1250');
  global $ds2_cena;
  $ds2_cena= array();
  ezer_connect('setkani');
  $qry2= "SELECT * FROM ds_cena WHERE rok=$rok";
  $res2= pdo_qry($qry2);
  while ( $res2 && $c= pdo_fetch_object($res2) ) {
    $wc= $c;
    $wc->polozka= wu($c->polozka);
    $wc->druh= wu($c->druh);
    $ds2_cena[$c->typ]= $wc;
  }
//                                                 debug($cena,'cena',(object)array('win1250'=>1));
}
# ----------------------------------------------------------------------------------- ds2 cenik_list
# vrátí seznam položek ceníku Domu setkání zadaného roku (default je letošní platný)
# pokud je zadaný host vrátí také počet objednaných instancí položek ceníku
# ve kterém zohlední aktuální opravy podle položky ds_osoba.oprava
function ds2_cenik_list($cenik_roku=0,$order=0,$host=0) { trace();
  $y= (object)array('list'=>array());
  // najdi platný ceník 
  ezer_connect('setkani');
  $cenik_roku= $cenik_roku ?: date('Y');
  $cenik_roku= select('rok','ds_cena',"rok<=$cenik_roku ORDER BY rok DESC LIMIT 1",'setkani');
  $y->cenik_roku= $cenik_roku;
  // projdi ceník DS
  $rc= pdo_qry("SELECT typ,polozka FROM ds_cena WHERE rok=$cenik_roku ORDER BY druh,typ");
  while ( $rc && list($typ,$pol)= pdo_fetch_row($rc) ) {
    $y->list[]= (object)array('typ'=>$typ,'txt'=>wu($pol));
  }
  if ( $host ) {
    $pol= (object)array();
//    $cen= (object)array();
    $opr= (object)array();
    $fields= (object)array();
    // číselníky 
    $ds_luzko=  map_cis('ds_luzko','zkratka');  $ds_luzko[0]=  '?';
    $ds_strava= map_cis('ds_strava','zkratka'); $ds_strava[0]= '?';
    // přepočet kategorie pokoje na typ ubytování v ceníku    
    $luzko_pokoje= ds2_cat_typ();
    $ob= select_object('*','tx_gnalberice_order',"uid=$order",'setkani');
    // projdeme členy rodiny
    $ros= pdo_qry("SELECT * FROM ds_osoba WHERE id_order=$order AND id_osoba='$host'");
    if ( $ros && $os= pdo_fetch_object($ros) ) {
      if ( !$os->pokoj ) { $y->err= "není zapsán pokoj pro $y->prijmeni $y->jmeno "; goto end; }
      // načtení případné opravy 
      if ( $os->oprava ) {
        // $opravy[0] je rok číselníku - musí se shodovat s aktuálním
        $opravy= explode(',',$os->oprava);
        for ($i= 1; $i<count($opravy); $i++) {
          list($field,$val)= explode(':',$opravy[$i]);
          $opr->$field= (int)$val;
        }
      }
      // počty položek
      $host_pol= ds2_polozky_hosta($ob,$os,$luzko_pokoje,$ds_luzko,$ds_strava);
//      debug($host_pol,"ds2_polozky_hosta pro $order/$host");
      foreach ($host_pol->cena as $field=>$value) {
        $pol->$field= $value;
      }
      // ceny za položky
      $one= ds2_platba_hosta($cenik_roku,$host_pol->cena,$fields,'',true);
//      debug($fields,"ds2 platba_hosta");
      foreach ($one as $field=>$value) {
        $opr->$field= isset($opr->$field) ? $opr->$field : '-';
      }
    }
    $y->pol= $pol;
//    $y->cen= $cen;
    $y->one= $one;
    $y->opr= $opr;
    unset($y->list);
  }
end:  
//                                                debug($y,'ds2 cenik_list');
  return $y;
}
# ---------------------------------------------------------------------------------- ds2 cena_pobytu
# ASK
# vypočítá cenu pobytu účastníka (1), rodiny (2), akce (3)
# $id_osoba je z tabulky ds_osoba obsahující osobo-dny 
function ds2_cena_pobytu($order,$idos,$cenik_roku,$pro=0) { trace();
  $y= (object)array('fields'=>(object)array(),'rows'=>array());
  // číselníky 
  $ds_luzko=  map_cis('ds_luzko','zkratka');  $ds_luzko[0]=  '?';
  $ds_strava= map_cis('ds_strava','zkratka'); $ds_strava[0]= '?';
  ezer_connect('setkani');
  // přepočet kategorie pokoje na typ ubytování v ceníku    
  $luzko_pokoje= ds2_cat_typ();
  // společná data
  list($order,$jmeno,$prijmeni,$rodina,$ulice,$psc,$obec)= 
      select('id_order,jmeno,prijmeni,rodina,ulice,psc,obec','ds_osoba',"id_osoba=$idos",'setkani');
  $y->order= $order;
  $y->fields->jmeno= wu($jmeno);
  $y->fields->prijmeni= wu($prijmeni);
  $y->fields->adresa= wu("$jmeno $prijmeni<br>$ulice<br>$psc $obec");
  $y->fields->rodina= wu($rodina);
  $ob= select_object('*','tx_gnalberice_order',"uid=$order",'setkani');
  $cenik_roku= $cenik_roku?: date('Y',$ob->untilday);
  $y->obdobi= date('j.n',$ob->fromday).' - '.date('j.n.Y',$ob->untilday);
  ds2_cenik($cenik_roku);
  
  // údaje pro fakturaci
  $y->fakt= [1=>[],2=>[],3=>[]]; // pro -> polozka -> [pocet,suma,dph]
  global $ds2_cena;
  foreach ($ds2_cena as $typ=>$desc) {
    for ($i= 1; $i<=3; $i++) {
      if ($pro && $i!=$pro) continue;
      $y->fakt[$i][$typ]= [0,0,$desc->dph];
    }
  }
  
  // sběr a kontrola dat pro hosta, rodinu, celou objednávku
  foreach (array(1=>"id_osoba=$idos",2=>"rodina='$rodina'",3=>"1") as $i=>$cond) {
    if ($pro && $i!=$pro) continue;
    $fields= (object)array();
    $ros= pdo_qry("SELECT * FROM ds_osoba WHERE id_order=$order AND $cond");
    while ( $ros && $os= pdo_fetch_object($ros) ) {
      if ( !$os->pokoj ) { $y->err= "není zapsán pokoj pro $y->prijmeni $y->jmeno "; goto end; }
      $host_pol= ds2_polozky_hosta($ob,$os,$luzko_pokoje,$ds_luzko,$ds_strava);
      debug($host_pol,"ds2 polozky_hosta");
      foreach ($host_pol->cena as $field=>$pocet) {
        if (isset($y->fakt[$i][$field]) && $pocet) {
          $y->fakt[$i][$field][1]+= $pocet;
        }
      }
      if ( $i==1 ) {
        $y->host= $host_pol;
      }
      ds2_platba_hosta($cenik_roku,$host_pol->cena,$fields,$i,true);
      debug($fields,"ds2 platba_hosta ");
      foreach ($fields as $field=>$value) {
        $y->fields->$field+= $value;
        if (isset($y->fakt[$i][$field])) {
          $y->fakt[$i][$field][0]+= $value;
        }
      }
    }
  }
end:  
                                                    debug($y,"ds2 cena_pobytu($idos,$cenik_roku)");
  return $y;
}
# -------------------------------------------------------------------------------------- ds2 cat_typ
# přepočet kategorie pokoje na typ ubytování v ceníku    
function ds2_cat_typ() {
  $cat_typ= array('C'=>'A','B'=>'L','A'=>'S');
  $luzko_pokoje[0]= 0;
  $rr= pdo_qry("SELECT number,category FROM tx_gnalberice_room WHERE version=1");
  while ( $rr && list($pokoj,$typ)= pdo_fetch_row($rr) ) {
    $luzko_pokoje[$pokoj]= $cat_typ[$typ];
  }
  return $luzko_pokoje;  
}
# -----------------------------------------------------------------------------==> ds2 polozky_hosta
# výpočet položek hosta
function ds2_polozky_hosta ($o,$h,$luzko_pokoje,$ds_luzko,$ds_strava) { trace();
  global $ds2_cena;
  // výpočet
  $hf= sql2stamp($h->fromday); $hu= sql2stamp($h->untilday);
  $od_ts= $hf ? $hf : $o->fromday;  
//  $od= date('j.n',$od_ts);
  $do_ts= $hu ? $hu : $o->untilday; 
//  $do= date('j.n',$do_ts);
  $vek= ds2_vek($h->narozeni,$o->fromday);
//  $narozeni= $h->narozeni ? sql_date1($h->narozeni): '';
  $strava= $h->strava ? $h->strava : $o->board;
  // připsání řádku
  $host= array();
//  $host[]= wu($h->rodina);
//  $host[]= wu($h->jmeno);
//  $host[]= wu($h->prijmeni);
//  $host[]= wu($h->ulice);
//  $host[]= wu("{$h->psc} {$h->obec}");
//  $host[]= $narozeni;
//  $host[]= $vek;
//  $host[]= $h->telefon;
//  $host[]= $h->email;
//  $host[]= $od;
//  $host[]= $do;
  // položky hosta
  $pol= (object)array();
//  $pol->test= "{$h->strava} : {$o->board} - $strava = {$ds_strava[$strava]}";
  $noci= round(($do_ts-$od_ts)/(60*60*24));
  $pol->vek= $vek;
  $pol->noci= $noci;
  $pol->pokoj= (int)$h->pokoj;
  // ubytování
  $luzko= trim($ds_luzko[$h->luzko]);     // L|P|B
  if ( $luzko=='L' )
    $luzko= $luzko_pokoje[$h->pokoj];
  if ( $luzko )
    $pol->{"noc_$luzko"}= $noci;
  // zvíře za noc
  if ($h->zvire)
    $pol->noc_Z= $noci * $h->zvire;
  // strava
  $pol->strava_CC= $ds_strava[$strava]=='C' && $vek>=$ds2_cena['strava_CC']->od ? $noci : '';
  $pol->strava_CD= $ds_strava[$strava]=='C' && $vek>=$ds2_cena['strava_CD']->od
                                            && $vek< $ds2_cena['strava_CD']->do ? $noci : '';
  $pol->strava_PC= $ds_strava[$strava]=='P' && $vek>=$ds2_cena['strava_PC']->od ? $noci : '';
  $pol->strava_PD= $ds_strava[$strava]=='P' && $vek>=$ds2_cena['strava_PD']->od
                                            && $vek< $ds2_cena['strava_PD']->do ? $noci : '';
  // pobyt
  if ( $h->postylka ) {
    $pol->pobyt_P= 1;
  }
  // poplatky
  if ( $vek>=18 ) {
    $pol->ubyt_S= $noci;
    if ( !$o->skoleni ) $pol->ubyt_C= $noci;   // rekreační poplatek se neplatí za školení
  }
  else {
    $pol->ubyt_P= $noci;
  }
  // program pouze pro akce YMCA
//  debug($o);
  if ($o->state==3) {
    $pol->prog_C= $vek>=$ds2_cena['prog_C']->od  ? $noci : 0;
    $pol->prog_P= $vek>=$ds2_cena['prog_P']->od && $vek<$ds2_cena['prog_P']->do ? $noci : 0;
  }
  return (object)array('host'=>$host,'cena'=>$pol);
}        
# ------------------------------------------------------------------------------==> ds2 platba_hosta
# výpočet ceny za položky hosta jako ubyt,strav,popl,prog,celk
function ds2_platba_hosta ($cenik_roku,$polozky,$platba,$i='',$podrobne=false) { trace();
  $druhy= array("ubyt$i"=>'noc|pobyt',"strav$i"=>'strava',"popl$i"=>'ubyt',"prog$i"=>'prog');
  $celki= "celk$i";
  // výpočet
  $one= (object)array();
  $platba->$celki= 0;
  foreach ( $druhy as $druh=>$prefix ) {
    $platba->$druh= 0;
    $rc= pdo_qry("SELECT typ,cena,dph FROM ds_cena WHERE rok=$cenik_roku AND typ RLIKE '$prefix' ");
    while ( $rc && list($typ,$cena,$dph)= pdo_fetch_row($rc) ) {
      $one->$typ+= $cena;
      list($typ_)= explode('_',$typ);
      if ( $polozky->$typ ) {
        $za_noc= in_array($typ_,array('noc','strava','ubyt','prog'));
        $cena= $za_noc ? $cena*$polozky->noci : $cena;
        $platba->$druh+= $cena;
        if ( $podrobne ) {
          $platba->$typ+= $cena;
        }
      }
    }
    $platba->$celki+= $platba->$druh;
  }
//                          debug($one,"ds2 platba_hosta ($cenik_roku,polozky,platba,$i,$podrobne)");
  return $one;
}        
# ------------------------------------------------------------------------------------ ds2 import_ys
# naplní seznam účastníky dané akce
function ds2_import_ys($order,$clear=0) {
  global $answer_db;
  $ret= (object)array('html'=>'','conf'=>'');
  list($rok,$kod,$from,$until,$strava)= 
      select('YEAR(FROM_UNIXTIME(fromday)),akce,FROM_UNIXTIME(fromday),FROM_UNIXTIME(untilday),board',
          'tx_gnalberice_order',"uid=$order",'setkani');
  if ( $kod ) {
    // objednávka má definovaný kód akce
    ezer_connect($answer_db,true);
    $ida= select('id_akce',"$answer_db.join_akce","g_kod=$kod AND g_rok=$rok",$answer_db);
    // zjistíme, zda je objednávka bez lidí
    ezer_connect('setkani',true);
    $pocet= select('COUNT(*)','ds_osoba',"id_order=$order",'setkani');
    if ( $pocet && $clear ) {
      query("DELETE FROM ds_osoba WHERE id_order=$order",'setkani');
      $ret->html.= "Seznam účastníků pobytu byl vyprázdněn. ";
    }
    if ( $pocet && !$clear ) {
      $ret->conf= "Seznam účastníků pobytu obsahuje $pocet lidí - mám jej vyprázdnit a načíst 
          z akce YMCA Setkání? (Pozor, případné přiřazení pokojů, lůžek a strav bude zapomenuto)";
      $ret->html= "Seznam účastníků pobytu nebyl změněn";
      goto end;
    }
    // projdeme účastníky v ezer_db2 a přeneseme společné údaje
    // a potom prijmeni,jmeno,narozeni,psc,obec,ulice,email,telefon 
    $uc= array();
    ezer_connect($answer_db,true);
    $rp= pdo_qry("
      SELECT s.id_osoba,prijmeni,jmeno,narozeni,
        IF(adresa,o.psc,r.psc) AS psc, 
        IF(adresa,o.obec,r.obec) AS obec,
        IF(adresa,o.ulice,r.ulice) AS ulice,
        IF(kontakt,o.email,r.emaily) AS email,
        IF(kontakt,o.telefon,r.telefony) AS telefon,
        IFNULL(nazev,prijmeni) AS rod
      FROM pobyt AS p
      JOIN spolu AS s USING (id_pobyt)
      JOIN osoba AS o USING (id_osoba)
      LEFT JOIN tvori AS t USING (id_osoba)
      LEFT JOIN rodina AS r ON r.id_rodina=IF(p.i0_rodina,p.i0_rodina,t.id_rodina)
      WHERE id_akce=$ida 
      GROUP BY id_osoba ORDER BY rod, narozeni
    ");
    while ($rp && $o= pdo_fetch_object($rp)) {
      $uc[]= $o;
    }
    // doplnění účastníků do objednávky
    ezer_connect('setkani',true);
    foreach ( $uc as $o ) {
      $ido= $o->id_osoba;
      $ds_osoba= select('id_osoba','ds_osoba',"ys_osoba=$ido AND id_order=$order",'setkani');
      if ( !$ds_osoba ) {
        $rod= substr(cz2ascii($o->rod),0,3);
        $prijmeni= uw($o->prijmeni);
        $jmeno= uw($o->jmeno);
        $obec=  uw($o->obec);
        $ulice= uw($o->ulice);
        query("INSERT INTO ds_osoba 
          (id_order,ys_osoba,rodina,prijmeni,jmeno,narozeni,psc,obec,
           ulice,email,telefon,fromday,untilday,strava) VALUES
          ($order,$ido,'$rod','$prijmeni','$jmeno','$o->narozeni','$o->psc','$obec',
           '$ulice','$o->email','$o->telefon','$from','$until',$strava)
        ",'setkani');
//        break;
      }
    }
    $ret->html.= "Seznam účastníků pobytu byl načten z akce YMCA Setkání";
  }
  else {
    $ret->html.= "Akce YMCA Setkání musí mít vyplněný kód akce (vedle stavu objednávky)";
  }
end:  
  return $ret;
}
# ----------------------------------------------------------------------------------- ds2 rooms_help
# vrátí popis pokojů
function ds2_rooms_help($version=1) {
  $hlp= array();
  ezer_connect('setkani');
  $qry= "SELECT number,1-hidden AS enable,note
         FROM tx_gnalberice_room
         WHERE NOT deleted AND version=$version";
  $res= pdo_qry($qry);
  while ( $res && $o= pdo_fetch_object($res) ) {
    $hlp[]= (object)array('fld'=>"q$o->number",'hlp'=>wu($o->note),'on'=>$o->enable);
  }
//                                                         debug($hlp);
  return $hlp;
}
# =======================================================================================> LEFT MENU
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
      $qry= "SELECT /*ds_obj_menu*/uid,fromday,untilday,state,name,state FROM tx_gnalberice_order
             WHERE  NOT deleted AND NOT hidden AND untilday>=$from AND $until>fromday";
//              JOIN ezer_ys._cis ON druh='ds_stav' AND data=state
      $res= pdo_qry($qry);
      while ( $res && $o= pdo_fetch_object($res) ) {
        $iid= $o->uid;
        $zkratka= $stav[$o->state];
        $par= (object)array('uid'=>$iid);
        if ($ezer_version=='3.2') 
          $par= (object)array('*'=>$par);
//        $tit= wu("$iid - ").$zkratka.wu(" - {$o->name}");
        $tit= wu("$iid - $zkratka - $o->name");
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
# ==========================================================================================> rodina
# -------------------------------------------------------------------------------------- ds2 lide_ms
# SELECT autocomplete - výběr z databáze db2:rodina+členi
function ds2_lide_ms($patt) {  #trace('','win1250');
  $a= array();
  $limit= 10;
  $n= 0;
  // rodina
  $qry= "SELECT access,id_rodina AS _key,concat(nazev,' - ',obec) AS _value
         FROM rodina
         WHERE nazev LIKE '$patt%' AND deleted='' ORDER BY nazev LIMIT $limit";
  $res= pdo_qry($qry);
  while ( $res && $t= pdo_fetch_object($res) ) {
    if ( ++$n==$limit ) break;
    $key= $t->_key;
    $org= $t->access==1 ? 'S' : ( $t->access==2 ? 'F' : '*');
    $a[$key]= "$org:{$t->_value}";
  }
  // obecné položky
  if ( !$n )
    $a[0]= /*w*u*/("... žádné jméno nezačíná '")."$patt'";
  elseif ( $n==$limit )
    $a[-999999]= /*w*u*/("... a další");
//                                                      debug($a,$patt,(object)array('win1250'=>1));
  return $a;
}
# --------------------------------------------------------------------------------------- ds2 rodina
# formátování autocomplete - verze pro db2
function ds2_rodina($idr) {  #trace('','win1250');
  global $answer_db;
  $rod= array();
  // členové rodiny
  ezer_connect($answer_db);
  $rc= pdo_qry("
    SELECT
      IF(o.adresa,o.ulice,r.ulice) AS _ulice,
      IF(o.adresa,o.psc,r.psc) AS _psc,
      IF(o.adresa,o.obec,r.obec) AS _obec,
      IF(o.adresa,o.stat,r.stat) AS _stat,
      IF(o.kontakt,o.telefon,r.telefony) AS _telefon,
      IF(o.kontakt,o.email,r.emaily) AS _email,
      prijmeni,jmeno,narozeni,rc_xxxx,sex
    FROM rodina AS r
    JOIN tvori AS t USING (id_rodina)
    JOIN osoba AS o USING (id_osoba)
    WHERE id_rodina=$idr AND o.deleted='' AND r.deleted=''
    ORDER BY t.role
  ");
  while ( $rc && $c= pdo_fetch_object($rc) ) {
    $narozeni= sql_date1($c->narozeni);
    $rodcis= rodcis($c->narozeni,$c->sex).$c->rc_xxxx;
    $roky= roku($rodcis);
    $rod[]= (object)array('prijmeni'=>$c->prijmeni,'jmeno'=>$c->jmeno,'stari'=>$roky,
      'psc'=>$c->_psc,'mesto'=>$c->_obec,'ulice'=>$c->_ulice,
      'telefon'=>$c->_telefon,'email'=>$c->_email,'narozeni'=>$narozeni);
  }
  return $rod;
}
# -------------------------------------------------------------------------------------- ds2 lide_ds
# SELECT autocomplete - výběr z databáze DS
function ds2_lide_ds($patt0) {  #trace('','win1250');
  global $ezer_local;
  $a= array();
  $limit= 10;
  $n= 0;
  $patt= $patt0;
  $patt= mb_strtolower($patt,'UTF-8');
  if ( !$ezer_local )
    $patt= utf2win($patt,true);             // POZOR - je určeno jen pro použití na ostrém serveru
  // výběr ze starých dobrých klientů
  ezer_connect('setkani');
  $qry= "SELECT id_osoba AS _key,concat(prijmeni,' ',jmeno,' - ',obec,'/',id_order) AS _value
         FROM ds_osoba
         WHERE lower(prijmeni) LIKE '$patt%'
         GROUP BY _value
         ORDER BY prijmeni
         LIMIT $limit";
  $res= pdo_qry($qry);
  while ( $res && $t= pdo_fetch_object($res) ) {
    if ( ++$n==$limit ) break;
    $key= $t->_key;
    $a[$key]= wu("D:{$t->_value}");
  }
  // obecné položky
  if ( !$n )
    $a[0]= /*w*u*/("... žádné jméno nezačíná '")."$patt0'";//."INFO='$info'";
  elseif ( $n==$limit )
    $a[-999999]= /*w*u*/("... a další");//."INFO='$info0'";
//                                                      debug($a,$patt,(object)array('win1250'=>1));
  return $a;
}
# ------------------------------------------------------------------------------------------- rodina
# formátování autocomplete
function ds2_klienti($id_osoba) {  #trace('','win1250');
  $rod= array();
  // rodiče
  ezer_connect('setkani');
  $qry= "SELECT * FROM ds_osoba WHERE id_osoba=$id_osoba";
  $res= pdo_qry($qry);
  if ( $res && $p= pdo_fetch_object($res) ) {
    $cond= "id_order={$p->id_order} AND obec='{$p->obec}' AND ulice='{$p->ulice}'";
    // vybereme se stejným označením rodiny
    $qry= "SELECT * FROM ds_osoba WHERE $cond
           ORDER BY narozeni";
    $res= pdo_qry($qry);
    while ( $res && $o= pdo_fetch_object($res) ) {
    $vek= ds2_vek($o->narozeni,time());
    $narozeni= sql_date1($o->narozeni);
    $rod[]= (object)array('prijmeni'=>wu($o->prijmeni),'jmeno'=>wu($o->jmeno),'stari'=>$vek,
      'psc'=>$o->psc,'mesto'=>wu($o->obec),'ulice'=>wu($o->ulice),
      'telefon'=>$o->telefon,'email'=>$o->email,'narozeni'=>$narozeni);
    }
  }
//                                              debug($rod,$id_osoba,(object)array('win1250'=>1));
  return $rod;
}
# =========================================================================================> exporty
# ------------------------------------------------------------------------------------ ds2 xls_hoste
# definice Excelovského listu - seznam hostů
function ds2_xls_hoste($orders,$mesic_rok) {  #trace('','win1250');
  $x= ds_hoste($orders,substr($mesic_rok,-4));
  $name= cz2ascii("hoste_$mesic_rok");
  $mesic_rok= uw($mesic_rok);
  $xls= <<<__XLS
    |open $name
    |sheet hoste;;L;page
    |columns A=6,B=10,C=13,D=40,E=15,F=13,G=30,H=12,I=12
    |A1 Seznam hostů zahajujících pobyt v období $mesic_rok ::bold size=14
    |A3 akce    |B3 jméno |C3 příjmení |D3 adresa  |E3 datum narození ::right date
    |F3 telefon |G3 email |H3 termín   |I3 rekr.popl. ::right
    |A3:I3 bcolor=ffaaaaaa
__XLS;
  $n= 4;
  foreach ($x->hoste as $host) {
    list($jmeno,$prijmeni,$adresa,$narozeni,$telefon,$email,$termin,$poplatek,$akce,)= (array)$host;
    $xls.= <<<__XLS
      |A$n $akce    |B$n $jmeno |C$n $prijmeni       |D$n $adresa   |E$n $narozeni ::right date
      |F$n $telefon |G$n $email |H$n $termin ::right |I$n $poplatek
      |A$n:I$n border=,,h,
__XLS;
    $n++;
  }
  $xls.= <<<__XLS
    |close
__XLS;
//                                                                 display(/*w*u*/($xls));
  $test= 1;
  if ( $test )
    file_put_contents("xls.txt",$xls);
  $inf= Excel5(/*w*u*/($xls),1);
  if ( $inf ) {
    $html= " se nepodařilo vygenerovat - viz začátek chybové hlášky";
    fce_error(/*w*u*/($inf));
  }
  else
    $html= " Byl vygenerován seznam hostů ve formátu <a href='docs/$name.xls' target='xls'>Excel</a>.";
  return /*w*u*/($html);
}
# ---------------------------------------------------------------------------------------- ds2 hoste
# vytvoří seznam hostů
# ceník beer podle předaného roku
# {table:id,obdobi:str,hoste:[[jmeno,prijmeni,adresa,narozeni,telefon,email,termin,poplatek]...]}
function ds2_hoste($orders,$rok) {  #trace('','win1250');
  global $ds2_cena, $ezer_path_serv;
  require_once "$ezer_path_serv/licensed/xls2/Classes/PHPExcel/Calculation/Functions.php";
  ds2_cenik($rok);
//                                      debug($ds2_cena,'ds_cena',(object)array('win1250'=>1));
  $x= (object)array();
//  $x->table= "klienti_$obdobi";
  $x->hoste= array();
  ezer_connect('setkani');
  // zjištění klientů zahajujících pobyt v daném období
  $qry= "SELECT *,o.fromday as _of,o.untilday as _ou,p.email as p_email,
         p.fromday as _pf,p.untilday as _pu,akce
         FROM ds_osoba AS p
         JOIN tx_gnalberice_order AS o ON uid=id_order
         WHERE FIND_IN_SET(id_order,'$orders') ORDER BY id_order,rodina,narozeni DESC";
  $res= pdo_qry($qry);
  while ( $res && $h= pdo_fetch_object($res) ) {
    $pf= sql2stamp($h->_pf); $pu= sql2stamp($h->_pu);
    $od_ts= $pf ? $pf : $h->_of;
    $do_ts= $pu ? $pu : $h->_ou;
    $od= date('j.n',$od_ts);
    $do= date('j.n',$do_ts);
//     $od= $pf ? date('j.n',$pf) : date('j.n',$h->_of);
//     $do= $pu ? date('j.n',$pu) : date('j.n',$h->_ou);
    $vek= ds2_vek($h->narozeni,$pf ? $h->_pf : $h->_of);
    if ( $h->narozeni ) {
      list($y,$m,$d)= explode('-',$h->narozeni);
      $time= gmmktime(0,0,0,$m,$d,$y);
      $narozeni= PHPExcel_Shared_Date::PHPToExcel($time);
    }
    else $narozeni= 0;
    // rekreační poplatek
    if ( $vek>=18 || $vek<0 )
      $popl= $ds2_cena['ubyt_C']->cena + $ds2_cena['ubyt_S']->cena;
    else
      $popl= $ds2_cena['ubyt_P']->cena;
    // připsání řádku
    $host= array();
    $host[]= wu($h->jmeno);
    $host[]= wu($h->prijmeni);
    $host[]= wu("{$h->psc} {$h->obec}, {$h->ulice}");
    $host[]= $narozeni;
    $host[]= $h->telefon;
    $host[]= $h->p_email;
    $host[]= "$od - $do";
    $host[]= $popl;
    $host[]= $h->akce;
    $host[]= $vek;
    $x->hoste[]= $host;
  }
//                                              debug($x,'hoste',(object)array('win1250'=>1));
  return $x;
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
/** =======================================================================================> BANKY **/
#
# ------------------------------------------------------------------------------------ ds2 show_curr
# čitelné zobrazení objektu získaného funkcí akce2.curr
function ds2_show_curr($c) {
  $evi= $ucast= '';
  $a_jmeno= $e_jmeno= $a_akce= '';
  // akce
  if (($ida= $c->lst->akce)) {
    list($a_akce,$kod)= select("CONCAT(nazev,' ',YEAR(datum_od)),IFNULL(g_kod,'')",
        'akce LEFT JOIN join_akce ON id_akce=id_duakce',"id_duakce=$ida");
    $ucast.= " <span title='ID akce=$ida'>$kod</span>";
  }
  if (($ido= $c->ucast->osoba)) {
    $nazev= ($idr=$c->ucast->rodina) ? select1("nazev",'rodina',"id_rodina=$idr") : '';
    $ucast.= ' pobyt '.tisk2_ukaz_pobyt_akce($c->ucast->pobyt,$ida,'',$nazev);
    list($a_jmeno,$nar)= select("CONCAT(jmeno,' ',prijmeni),narozeni",'osoba',"id_osoba=$ido");
    $nar= sql_date1($nar);
    $a_jmeno.= " ($nar)";
    $ucast.= ' osoba '.tisk2_ukaz_osobu($ido,'',$a_jmeno);
  }
  // evidence
  if (($ido= $c->evi->osoba)) {
    list($e_jmeno,$nar)= select("CONCAT(jmeno,' ',prijmeni),narozeni",'osoba',"id_osoba=$ido");
    $nar= sql_date1($nar);
    $e_jmeno.= " ($nar)";
    $evi.= ' osoba '.tisk2_ukaz_osobu($ido,'',$e_jmeno);
  }
  if (($idr= $c->evi->rodina)) {
    $nazev= select1("nazev",'rodina',"id_rodina=$idr");
    $evi.= ' rodina '.tisk2_ukaz_rodinu($idr,'',$nazev);
    $e_jmeno.= ", $nazev";
  }
  return (object)['evi'=>$evi,'evi_text'=>$e_jmeno,
      'ucast'=>$ucast,'ucast_text'=>"$a_akce, $a_jmeno"];
}
# ------------------------------------------------------------------------------------------ ds2 fio
# zapsání informace do platby
function ds2_corr_platba($id_platba,$typ,$c=null) {
  switch ($typ) {
    case 'dar':
      query("UPDATE platba SET stav=11
        WHERE id_platba=$id_platba AND stav IN (5,10)");
      break;
    case 'auto':
      query("UPDATE platba SET stav=stav+2
        WHERE id_platba=$id_platba AND stav IN (1,6,8,10)");
      break;
    case 'ucast':
      query("UPDATE platba SET id_oso={$c->ucast->osoba},id_pob={$c->ucast->pobyt}, stav=9
        WHERE id_platba=$id_platba");
      break;
    case 'evi':
      query("UPDATE platba SET id_oso={$c->evi->osoba}, stav=9 
        WHERE id_platba=$id_platba");
      break;
  }
}
# ------------------------------------------------------------------------------------------ ds2 fio
# zjištění věku v době zahájení akce
function ds2_fio($cmd) {
  global $api_fio_ds, $api_fio_ys;
  $y= (object)['html'=>'','err'=>''];
  $y->html= "$cmd->fce<hr>";
  $n= 0;
  $token= $api_fio_ds;
  $ucet= 2;
  switch ($cmd->fce) {
    case 'load-ys': // CSV
      $token= $api_fio_ys;
      $ucet= 1; // načítání plateb YS
    case 'load-ds': // CSV
      $od= $cmd->od;
      if ($od=='*') {
        $od= select('MAX(datum)','platba',"ucet=$ucet");
        if (!$od) {
          $y->err= "tabulka platba neobsahuje žádné položky pro účet $ucet";
          goto end;
        }
      }
      $do= $cmd->do=='*' ? date('Y-m-d') : $cmd->do;
      $format= 'csv';
      $url= "https://www.fio.cz/ib_api/rest/periods/$token/$od/$do/transactions.$format";
      $fp= fopen($url,'r');
//      $data= fgetcsv($f, 1000, ",");
      $decode= 0;
      $dat_max= '';
      $dat_min= '2222-22-22';
      while ($fp && !feof($fp) && ($line= fgets($fp,4096))) {
        display($line);
        if (!strncmp($line,'ID pohybu',9)) {
          $decode= 1;
          continue;
        }
        if ($decode) {
          $d= str_getcsv($line,';');
          debug($d);
          $mame= select('id_platba','platba',"id_platba='$d[0]'");
          if (!$mame) {
            $datum= sql_date1($d[1],1);
            $dat_min= min($dat_min,$datum);
            $dat_max= max($dat_max,$datum);
            $castka= str_replace(',','.',$d[2]);
            $mena= $d[3]=='CZK' ? 0 : 1;
            $proti= "$d[4]/$d[6]";
            $nazev= $d[5];
            $ident= $d[11];
            $zprava= $d[12]==$ident ? '' : $d[12];
            $komentar= $d[16]==$ident ? '' : $d[16];
            $stav= $castka>0 ? 5 : 1;
//            $vs= ltrim($d[9]," 0");
            $ss= ltrim($d[10]," 0");
            query("INSERT INTO platba (id_platba,stav,ucet,datum,castka,mena,protiucet,nazev,"
                . "ks,vs,ss,"
                . "ident,zprava,provedl,upresneni,komentar) VALUES ("
                . "$d[0],$stav,$ucet,'$datum',$castka,$mena,'$proti','$nazev', "
                . "'$d[8]','$vs','$ss',"
                . "'$ident','$zprava','$d[14]','$d[15]','$komentar' )");
            $n++;
          }
        }
      }
      fclose($fp);
//      
      $y->html= "Nahráno $n plateb ";
      if ($n) $y->html.= "- od $dat_min do $dat_max";
//      $y->html.= "<br><br>$url";
      break; // načítání plateb DS
    case 'clear-ys':   // ------------------------------- vymazání přiřazení letošních plateb
      $ucet= 1; // načítání plateb YS
    case 'clear-ds':
      $od= $cmd->od;
      $do= $cmd->do;
      $n= query("UPDATE platba SET id_oso=0,id_pob=0,id_ord=0,stav=5, ss2='' 
        WHERE ucet=$ucet AND datum BETWEEN '$od' AND '$do' AND stav NOT IN (1,7,9,11) ");
      $y->html= "Vymazáno $n přiřazení letošních plateb";
      break; // vymazání přiřazení letošních plateb
//    case 'delete':   // ------------------------------------------- vymazání letošních plateb
//      $n= query("DELETE FROM platba WHERE YEAR(datum)=YEAR(NOW())");
//      $y->html= "Vymazáno $n letošních plateb";
//      break; // vymazání přiřazení letošních plateb
    case 'join-ys': // ----------------------------------------------------- přiřazení plateb
      $na= $nd= $nu= 0;
      $omezeni= $cmd->platba
          ? "id_platba=$cmd->platba" 
          : "datum BETWEEN '$cmd->od' AND '$cmd->do'";
      // rozpoznání osoby podle protiúčtu
      $rp= pdo_qry("
        SELECT id_platba,protiucet FROM platba AS p 
        WHERE id_oso=0 AND $omezeni");
      while ($rp && (list($id_platba,$ucet)= pdo_fetch_array($rp))) {
        $ido= select('id_oso','platba',"protiucet='$ucet' AND id_oso!=0 ");
        if ($ido!=false) {
          query("UPDATE platba SET id_oso=$ido WHERE id_platba=$id_platba");
          $nu++;
        }
      }
      // platby za akce
      $rp= pdo_qry("
        SELECT id_platba,id_osoba,id_pobyt,id_oso
        FROM platba AS p
        JOIN join_akce AS ja ON ja.g_kod=p.ss AND YEAR(p.datum)=g_rok
        JOIN akce AS a ON ja.id_akce=id_duakce
        JOIN pobyt AS po ON po.id_akce=id_duakce
        JOIN spolu AS s USING (id_pobyt) -- ON s.id_pobyt=po.id_pobyt
        JOIN osoba AS o USING (id_osoba) -- ON o.id_osoba=s.id_osoba
        WHERE id_pob=0 AND LENGTH(ss)=3 AND $omezeni AND
          ( id_oso!=0
          OR IF(LENGTH(vs)=6,
              vs=CONCAT(SUBSTR(narozeni,3,2),SUBSTR(narozeni,6,2),SUBSTR(narozeni,9,2))
              OR vs=CONCAT(SUBSTR(narozeni,9,2),SUBSTR(narozeni,6,2),SUBSTR(narozeni,3,2)),
             IF(LENGTH(vs)=8, 
              vs=CONCAT(SUBSTR(narozeni,1,4),SUBSTR(narozeni,6,2),SUBSTR(narozeni,9,2))
              OR vs=CONCAT(SUBSTR(narozeni,9,2),SUBSTR(narozeni,6,2),SUBSTR(narozeni,1,4)),0)
          ))
      ");
      while ($rp && (list($id_platba,$ido,$idp,$idoso)= pdo_fetch_array($rp))) {
        $o= $idoso==0;
        query("UPDATE platba SET ".($o ? "id_oso=$ido," : '')." id_pob=$idp, stav=6 WHERE id_platba=$id_platba");
        $na++;
      }
      // dary
      $rp= pdo_qry("
        SELECT id_platba,vs,ss,protiucet,nazev,zprava,ss IN (22,222) OR zprava RLIKE 'dar' AS _dar
        FROM platba AS p WHERE id_oso=0 AND $omezeni
          -- AND id_platba=26446381639 ");
      while ($rp && (list($id_platba,$vs,$ss,$ucet,$nazev,$zprava,$dar)= pdo_fetch_array($rp))) {
        // podle dřívější platby
        $ido= select('id_oso','platba',"protiucet='$ucet' AND id_oso!=0 ");
//        display("$nazev,$ss,'$ido'");
        if ($ido==false) {
          if ((strlen($vs)==6||strlen($vs)==10) && $vs[2]>1) {
            $vs2= (0+$vs[2]) - 5;
            $vs[2]= $vs2;
          }
          if (strlen($vs)==10) {
            $vs= substr($vs,0,6);
          }
          $ro= pdo_qry("SELECT id_osoba,prijmeni FROM osoba 
            WHERE deleted='' AND prijmeni!='' AND 
              CONCAT('$nazev',' ','$zprava') LIKE CONCAT('%',prijmeni,'%') COLLATE utf8_general_ci 
              AND IF(LENGTH('$vs')=6,
                  '$vs'=CONCAT(SUBSTR(narozeni,3,2),SUBSTR(narozeni,6,2),SUBSTR(narozeni,9,2))
                  OR '$vs'=CONCAT(SUBSTR(narozeni,9,2),SUBSTR(narozeni,6,2),SUBSTR(narozeni,3,2)),
                 IF(LENGTH('$vs')=8, 
                  '$vs'=CONCAT(SUBSTR(narozeni,1,4),SUBSTR(narozeni,6,2),SUBSTR(narozeni,9,2))
                  OR '$vs'=CONCAT(SUBSTR(narozeni,9,2),SUBSTR(narozeni,6,2),SUBSTR(narozeni,1,4)),0)
                )
          ");
          while ($ro && (list($ido,$prijmeni)= pdo_fetch_array($ro))) {
            break;
          }
        }
        if ($ido) {
          $stav= $dar ? ", stav=10" : '';
          query("UPDATE platba SET id_oso=$ido $stav WHERE id_platba=$id_platba");
          $nd++;
        }
      }
      $y->html= "Rozpoznáno $na plateb za akce, $nd darů, $nu osob podle účtu";
      break; // přiřazení plateb
  }
end:  
  return $y;
}
