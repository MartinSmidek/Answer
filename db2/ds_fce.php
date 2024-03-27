<?php # (c) 2009-2015 Martin Smidek <martin@smidek.eu>
/** =======================================================================================> FAKTURY **/
# -------------------------------------------------------------------------------------- ds2 faktura
# typ:T|I, zarovnání:L|C|R, písmo, l, t, w, h, border:LRTB
$ds_faktura_dfl= 'T,L,3.5,10,10,0,0,';
$ds_faktura_fld= [
  'logo' => ['I,,,13,10,20,17',"
      img/YMCA.png"],
  'kontakt' => [',,,40,10,200,50',"
      <b>Dům setkání</b><i>
      <br>Dolní Albeřice 1, 542 26 Horní Maršov
      <br>telefon: 736 537 122
      <br>dum@setkani.org
      <br><a href=\"https://dum.setkani.org\">dum.setkani.org</a></i>"],
  'cislo' => [',R,5,140,25,55,10',"
      <b>Faktura {faktura}</b>"],
  'dodavatel' => [',,,13,42,70,30',"
      <b>Dodavatel</b>
      <br><br>YMCA Setkání, spolek
      <br>Talichova 53, 623 00 Brno
      <br>zaregistrovaný Krajským soudem v Brně
      <br>spisová značka: L 8556
      <br>IČ: 26531135 DIČ: CZ26531135"],
  'odberatel' => [',,,112,42,20,10',"
      <b>Odběratel</b>"],
  'ramecek' => [',,,112,47,83,35,LRTB',""],
  'platce' => [',,4.5,120,56,75,24',"{platce}"],
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
      <br><br>Způsob platby"],
  'datumyR' => [',R,,178,96,20,30',"
      <br><br>{datum1}
      <br>{datum1}
      <br>{datum2}
      <br><br>převod"],
  'pobyt' => [',,,13,124,120,10',"
      Za pobyt v Domě setkání ve dnech {obdobi} Vám fakturujeme:"],
  'tabulka' => [',,,13,132,184,150',"
      {tabulka}"],
    
  'vyrizuje' => [',,,13,270,100,10',"
      <b>Vyřizuje</b>
      <br>{vyrizuje}"],
  'pata' => [',C,,13,285,184,6,T',"
      Těšíme se na Váš další pobyt v Domě setkání"],
];
function ds2_fakturace($idos,$part,$cenik_roku) {
  $pobyt= ds2_cena_pobytu($idos,$cenik_roku);
  $vals= [];
  $vals['{obj}']= $pobyt->order;
  $host= $pobyt->host->host;
  $vals['{platce}']= "$host[1] $host[2]<br>$host[3]<br>$host[4]";
  $vals['{obdobi}']= $pobyt->obdobi;
  $vals['{datum1}']= date('j. n. Y');
  $vals['{datum2}']= date('j. n. Y',strtotime("+14 days"));
  // DPH
  ezer_connect('setkani');
  $dph= (object)[];
  $rd= pdo_qry("SELECT LEFT(druh,4),dph FROM ds_cena WHERE rok=2024 GROUP BY druh");
  while ($rd && (list($druh,$_dph)= pdo_fetch_array($rd))) {
    $dph->$druh= $_dph;
  }
//  debug($dph,'DPH');
  // položky
  $flds= (array)$pobyt->fields;
  $ubyt=  $flds["ubyt$part"];
  $prog=  $flds["prog$part"];
  $strav= $flds["strav$part"];
  $popl=  $flds["popl$part"];
  $celkem= $ubyt + $prog + $strav + $popl;
  $celkem= number_format($celkem,2,',',' ').' Kč';
  $sazba_ubyt=  $dph->ubyt.'%';
  $sazba_prog=  $dph->prog.'%';
  $sazba_strav= $dph->stra.'%';
  $sazba_popl=  $dph->popl.'%';
  $dph_ubyt=  round($ubyt - $ubyt / (1 + $dph->ubyt/100),2);
  $dph_prog=  round($prog - $prog / (1 + $dph->prog/100),2);
  $dph_strav= round($strav - $strav / (1 + $dph->stra/100),2);
  $dph_popl=  round($popl - $popl / (1 + $dph->popl/100),2);
  $ubyt_bez=   number_format($ubyt-$dph_ubyt,2,',',' ').' Kč';
  $prog_bez=   number_format($prog-$dph_prog,2,',',' ').' Kč';
  $strav_bez=  number_format($strav-$dph_strav,2,',',' ').' Kč';
  $popl_bez=   number_format($popl-$dph_popl,2,',',' ').' Kč';
  $ubyt=  number_format($ubyt,2,',',' ').' Kč';
  $prog=  number_format($prog,2,',',' ').' Kč';
  $strav= number_format($strav,2,',',' ').' Kč';
  $popl=  number_format($popl,2,',',' ').' Kč';
  $dph_ubyt=  number_format($dph_ubyt,2,',',' ').' Kč';
  $dph_prog=  number_format($dph_prog,2,',',' ').' Kč';
  $dph_strav= number_format($dph_strav,2,',',' ').' Kč';
  $dph_popl=  number_format($dph_popl,2,',',' ').' Kč';
  // redakce tabulky
  $lrtb= "border:0.1mm dotted black";
  $num= "$lrtb;text-align:right";
  $th= 'td style="text-align:right;font-weight:bold" ';
  $vals['{tabulka}']= <<<__TAB
      <table style="border-collapse:collapse" cellpadding="1mm" >
        <tr>
          <th style="width:78mm;$lrtb">Položka</th><th style="width:34mm;$num">Cena s DPH</th>
          <th style="width:13mm;$num"> Sazba DPH</th><th style="width:28mm;$num">DPH</th>
          <th style="width:28mm;$num">Cena bez DPH</th>
        </tr><tr>
          <td style="$lrtb">ubytování<br>program<br>strava<br>poplatek obci</td>
          <td style="$num">$ubyt<br>$prog<br>$strav<br>$popl</td>
          <td style="$num">$sazba_ubyt<br>$sazba_prog<br>$sazba_strav<br>$sazba_popl</td>
          <td style="$num">$dph_ubyt<br>$dph_prog<br>$dph_strav<br>$dph_popl</td>
          <td style="$num">$ubyt_bez<br>$prog_bez<br>$strav_bez<br>$popl_bez</td>
        </tr><tr>
          <td><br><br></td>
        </tr><tr>
          <$th colspan="3">Celková cena s DPH</td><td colspan="2" style="$num">$celkem</td>
        </tr><tr>
          <$th colspan="3">Zaplaceno zálohou</td><td colspan="2" style="$num">0,00 Kč</td>
        </tr><tr>
          <$th colspan="3">Zbývá k zaplacení</td><td colspan="2" style="$num">$celkem</td>
        </tr>
      </table>
__TAB;
  return $vals;
}
function ds2_faktura($par) {  
  global $ds_faktura_dfl, $ds_faktura_fld;
  // získání parametrů
  $show= $par->show??0;
  $save= $par->save??0;
  $vals= ds2_fakturace($par->idos,2,$par->cenik);
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
  $x_dfl= explode(',',$ds_faktura_dfl);
  foreach ($ds_faktura_fld as $cast) {
    $x= $x_dfl; 
  // doplnění podle defaultu
    foreach (explode(',',$cast[0]) as $i=>$c) {
      if ($c) $x[$i]= $c;
    }
//    debug($x,'$typ,$align,$fsize,$l,$t,$w,$h,$border');
    list($typ,$align,$fsize,$l,$t,$w,$h,$border)= $x;
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
            . "left:{$l}$j;top:{$t}$j;width:{$w}$j;height:{$h}$j;"
            . "font-size:{$fsize}$j$bord$algn'>$text</div>";
  //      display(htmlentities($elem));
        $html.= $elem;
      }
      elseif ($typ=='I') {
        $elem= "<img src='$text' style='position:absolute;"
            . "left:{$l}$j;top:{$t}$j;width:{$w}$j;height:{$h}$j'>";
        display(htmlentities($elem));
        $html.= $elem;
      }
    }
    if ($save) {
      tc_page_cell($text,$typ,$align,$fsize*2.4,$l,$t,$w,$h,$border);
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
# ------------------------------------------------------------------------------------------ ds2 pdf
function ds2_pdf() {  
  global $abs_root, $pdf, $ds_faktura_dfl, $ds_faktura_fld;
  $pdf= new TC_PDF_PAGES('P','mm',PDF_PAGE_FORMAT,true,'UTF-8',false);
  $pdf->SetAutoPageBreak(false);
  $pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);
  $pdf->SetFont('dejavusans', '', 10);
  $pdf->AddPage('','',true);
  // obsah
  $x_dfl= explode(',',$ds_faktura_dfl);
  foreach ($ds_faktura_fld as $cast) {
    $x= $x_dfl; 
    // doplnění podle defaultu
    foreach (explode(',',$cast[0]) as $i=>$c) {
      if ($c) $x[$i]= $c;
    }
    list($typ,$align,$fsize,$l,$t,$w,$h,$border)= $x;
    $patt= trim($cast[1]);
    if ($typ=='T') {
      $bord= $border;
      $fattr= $align;
      $fsize= $fsize * 2.4;
      $pdf->SetFont('dejavusans','',$fsize);
      $pdf->SetXY($l,$t);
      $pdf->writeHTMLCell($w,$h,'','', $patt,$bord,0,0,true,$fattr,true);
    }
    elseif ($typ=='I') {
      display("$typ,$align,$fsize,$l,$t,$w,$h,$border");
      $pdf->Image($patt,$l,$t,$w);
    }
  }
  // zápis
  $fname= "fakt.pdf";
  $f_abs= "$abs_root/docs/$fname";
  $f_rel= "docs/$fname";
  $pdf->Output($f_abs, 'F');
  return (object)['html'=>"Fakturu $fname lze stáhnout <a target='pdf' href='$f_rel'>zde</a>"];
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
  global $ds_cena;
  $ds_cena= array();
  ezer_connect('setkani');
  $qry2= "SELECT * FROM ds_cena WHERE rok=$rok";
  $res2= pdo_qry($qry2);
  while ( $res2 && $c= pdo_fetch_object($res2) ) {
    $wc= $c;
    $wc->polozka= wu($c->polozka);
    $wc->druh= wu($c->druh);
    $ds_cena[$c->typ]= $wc;
  }
//                                                 debug($cena,'cena',(object)array('win1250'=>1));
}
# ----------------------------------------------------------------------------------- ds2 cenik_list
# vrátí seznam položek ceníku Domu setkání zadaného roku (default je letošní platný)
# pokud je zadaný host vrátí také počet objednaných instancí položek ceníku
# ve kterém zohlední aktuální opravy podle položky ds_osoba.oprava
function ds2_cenik_list($cenik_roku=0,$order=0,$host=0) {
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
    $cen= (object)array();
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
      foreach ($host_pol->cena as $field=>$value) {
        $pol->$field= $value;
      }
      // ceny za položky
      $one= ds2_platba_hosta($cenik_roku,$host_pol->cena,$fields,'',true);
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
                                                debug($y,'ds2_cenik_list');
  return $y;
}
# ---------------------------------------------------------------------------------- ds2 cena_pobytu
# ASK
# vypočítá cenu pobytu účastníka (1), rodiny (2), akce (3)
# $id_osoba je z tabulky ds_osoba obsahující osobo-dny 
function ds2_cena_pobytu($idos,$cenik_roku) {
  $y= (object)array('fields'=>(object)array(),'rows'=>array());
  // číselníky 
  $ds_luzko=  map_cis('ds_luzko','zkratka');  $ds_luzko[0]=  '?';
  $ds_strava= map_cis('ds_strava','zkratka'); $ds_strava[0]= '?';
  ezer_connect('setkani');
  // přepočet kategorie pokoje na typ ubytování v ceníku    
  $luzko_pokoje= ds_cat_typ();
  // společná data
  list($order,$jmeno,$prijmeni,$rodina)= 
      select('id_order,jmeno,prijmeni,rodina','ds_osoba',"id_osoba=$idos",'setkani');
  $y->order= $order;
  $y->fields->jmeno= wu($jmeno);
  $y->fields->prijmeni= wu($prijmeni);
  $y->fields->rodina= wu($rodina);
  $ob= select_object('*','tx_gnalberice_order',"uid=$order",'setkani');
  $cenik_roku= $cenik_roku?: date('Y',$ob->untilday);
  $y->obdobi= date('j.n',$ob->fromday).' - '.date('j.n.Y',$ob->untilday);
  ds2_cenik($cenik_roku);
  
  // sběr a kontrola dat pro hosta, rodinu, celou objednávku
  foreach (array(1=>"id_osoba=$idos",2=>"rodina='$rodina'",3=>"1") as $i=>$cond) {
    $fields= (object)array();
    $ros= pdo_qry("SELECT * FROM ds_osoba WHERE id_order=$order AND $cond");
    while ( $ros && $os= pdo_fetch_object($ros) ) {
      if ( !$os->pokoj ) { $y->err= "není zapsán pokoj pro $y->prijmeni $y->jmeno "; goto end; }
      $host_pol= ds2_polozky_hosta($ob,$os,$luzko_pokoje,$ds_luzko,$ds_strava);
      if ( $i==1 ) {
        $y->host= $host_pol;
      }
      ds2_platba_hosta($cenik_roku,$host_pol->cena,$fields,$i);
      foreach ($fields as $field=>$value) {
        $y->fields->$field+= $value;
      }
    }
  }
end:  
                                                    debug($y,"ds2_cena_pobytu($idos,$cenik_roku)");
  return $y;
}
# -----------------------------------------------------------------------------==> ds2 polozky_hosta
# výpočet položek hosta
function ds2_polozky_hosta ($o,$h,$luzko_pokoje,$ds_luzko,$ds_strava) {
  global $ds_cena;
  // výpočet
  $hf= sql2stamp($h->fromday); $hu= sql2stamp($h->untilday);
  $od_ts= $hf ? $hf : $o->fromday;  $od= date('j.n',$od_ts);
  $do_ts= $hu ? $hu : $o->untilday; $do= date('j.n',$do_ts);
  $vek= ds_vek($h->narozeni,$o->fromday);
  $narozeni= $h->narozeni ? sql_date1($h->narozeni): '';
  $strava= $h->strava ? $h->strava : $o->board;
  // připsání řádku
  $host= array();
  $host[]= wu($h->rodina);
  $host[]= wu($h->jmeno);
  $host[]= wu($h->prijmeni);
  $host[]= wu($h->ulice);
  $host[]= wu("{$h->psc} {$h->obec}");
  $host[]= $narozeni;
  $host[]= $vek;
  $host[]= $h->telefon;
  $host[]= $h->email;
  $host[]= $od;
  $host[]= $do;
  // položky hosta
  $pol= (object)array();
  $pol->test= "{$h->strava} : {$o->board} - $strava = {$ds_strava[$strava]}";
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
  // strava
  $pol->strava_CC= $ds_strava[$strava]=='C' && $vek>=$ds_cena['strava_CC']->od ? $noci : '';
  $pol->strava_CD= $ds_strava[$strava]=='C' && $vek>=$ds_cena['strava_CD']->od
                                            && $vek< $ds_cena['strava_CD']->do ? $noci : '';
  $pol->strava_PC= $ds_strava[$strava]=='P' && $vek>=$ds_cena['strava_PC']->od ? $noci : '';
  $pol->strava_PD= $ds_strava[$strava]=='P' && $vek>=$ds_cena['strava_PD']->od
                                            && $vek< $ds_cena['strava_PD']->do ? $noci : '';
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
  // program
  $pol->prog_C= $vek>=$ds_cena['prog_C']->od  ? 1 : 0;
  $pol->prog_P= $vek>=$ds_cena['prog_P']->od && $vek<$ds_cena['prog_P']->do ? 1 : 0;
  return (object)array('host'=>$host,'cena'=>$pol);
}        
# ------------------------------------------------------------------------------==> ds2 platba_hosta
# výpočet ceny za položky hosta jako ubyt,strav,popl,prog,celk
function ds2_platba_hosta ($cenik_roku,$polozky,$platba,$i='',$podrobne=false) {
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
        $za_noc= in_array($typ_,array('noc','strava','ubyt'));
        $cena= $za_noc ? $cena*$polozky->noci : $cena;
        $platba->$druh+= $cena;
        if ( $podrobne ) {
          $platba->$typ+= $cena;
        }
      }
    }
    $platba->$celki+= $platba->$druh;
  }
//                          debug($one,"ds2_platba_hosta ($cenik_roku,polozky,platba,$i,$podrobne)");
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
  global $pdo_db, $ezer_version;
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
    $vek= ds_vek($o->narozeni,time());
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
    list($jmeno,$prijmeni,$adresa,$narozeni,$telefon,$email,$termin,$poplatek,$akce,$vek)= (array)$host;
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
  global $ds_cena, $ezer_path_serv;
  require_once "$ezer_path_serv/licensed/xls2/Classes/PHPExcel/Calculation/Functions.php";
  ds2_cenik($rok);
//                                      debug($ds_cena,'ds_cena',(object)array('win1250'=>1));
  $x= (object)array();
  $x->table= "klienti_$obdobi";
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
    $vek= ds_vek($h->narozeni,$pf ? $h->_pf : $h->_of);
    if ( $h->narozeni ) {
      list($y,$m,$d)= explode('-',$h->narozeni);
      $time= gmmktime(0,0,0,$m,$d,$y);
      $narozeni= PHPExcel_Shared_Date::PHPToExcel($time);
    }
    else $narozeni= 0;
    // rekreační poplatek
    if ( $vek>=18 || $vek<0 )
      $popl= $ds_cena['ubyt_C']->cena + $ds_cena['ubyt_S']->cena;
    else
      $popl= $ds_cena['ubyt_P']->cena;
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
