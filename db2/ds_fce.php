<?php # (c) 2009-2015 Martin Smidek <martin@smidek.eu>
/** ==================================================================================> TRANSFORMACE **/
# transformace DS do Answer
define('org_ds',64);
define ('POZOR',"<span style:'color:red;background:yellow'>POZOR</span>");
# ------------------------------------------------------------------------------------------- ds2 kc
function TEST() {
  $table= 'osoba';
  $dnu= 70;
  $od= date('Y-m-d',time()-60*60*24*$dnu);
  $zmen= select('COUNT(*)','_track',"kde='$table' AND DATEDIFF(kdy,'$od')>0");
  return "v $table bylo za $dnu dnu $zmen zmen";
}
/** =======================================================================================> FAKTURY **/
# typ:T|I, zarovnání:L|C|R, písmo, l, t, w, h, border:LRTB
$dum_faktura_dfl= 'T,L,3.5,10,10,0,0,,1.5';
$dum_faktura_fld= [
//  'logo' => ['I,,,15,13,20,17',"
//      img/YMCA.png"],
  'logo' => ['I,,,13,10,25,32',"
      img/logo_ds.jpg"],
  'kontakt' => [',,,42,10,200,50',"
      <b>Dům setkání</b><i>
      <br>Dolní Albeřice 1, 542 26 Horní Maršov
      <br>telefon: 736 537 122
      <br>dum@setkani.org
      <br>https://dum.setkani.org</i>"],
  'faktura' => [',R,5,110,25,85,10',"
      <b>{faktura}</b>"],
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
  'objednavkaR' => [',R,4.5,158,90,40,30',"
    <b>{obj}</b>"],
  'datumyL' => [',,,120,96,80,30',"
      <br>Dodací a platební podmínky: s daní
      <br>Datum vystavení
      {DUZP-text}
      {splatnost-text}
      <br>Způsob platby"],
  'datumyR' => [',R,,170,96,28,30',"
      <br><br>{datum1}
      {DUZP-datum}
      {splatnost-datum}
      <br>bankovní převod"],
  'za_co' => [',,,13,132,184,10',"
      Za pobyt v Domě setkání ve dnech {obdobi} Vám fakturujeme:"],
  'tabulka' => [',,,13,140,184,150,,2',"
      {tabulka}"],
  'QR' => ['QR,,,13,230,40,40',     // viz https://qr-platba.cz/pro-vyvojare/specifikace-formatu/
      "SPD*1.0*ACC:{QR-IBAN}*RN:{QR-ds}*AM:{QR-castka}*CC:CZK*MSG:{QR-pozn}*X-VS:{QR-vs}*X-SS:{QR-ss}"],
    
  'vyrizuje' => [',,,13,275,100,10',"
      <b>Vyřizuje</b> {vyrizuje}"],
  'pata' => [',C,,13,285,184,6,T,2',"
      Těšíme se na Váš další pobyt v Domě setkání"],
];
# ------------------------------------------------------------------------------------------- dum kc
function dum_kc($c) {
  return number_format($c,2,'.',' ').' Kč';
}
# ---------------------------------------------------------------------------------- dum pobyt_nazev
# vrátí tisknutelný název pobytu
# pro format=kniha ve tvaru ids:jméno nejstaršího člena pobytu
function dum_pobyt_nazev($idp,$format='') {
  list($idp,$ids,$jmeno,$prijmeni)= pdo_fetch_array(pdo_qry("
    SELECT id_pobyt,id_spolu,jmeno,prijmeni
    FROM spolu 
      LEFT JOIN osoba USING (id_osoba)
    WHERE id_pobyt=$idp
    ORDER BY narozeni
    LIMIT 1"));
  return !$idp ? ''
    : ($format=='kniha' ? "$ids:$prijmeni $jmeno" : "$idp - $prijmeni");
}
# --------------------------------------------------------------------------------- dum faktura_info
# par.typ = konečná | záloha
function dum_faktura_info($idf) {
  list($ido,$idp,$typ,$duvod)= select('id_order,id_pobyt,typ,duvod_zmeny','faktura',"id_faktura=$idf");
  $typs= $typ==3 ? 'konečná faktura' 
      : ($typ==1 ? 'zálohová faktura' 
      : ($typ==2 ? 'daňový doklad' : '???'));
  $popis= 'Objednávka '.dum_objednavka_nazev($ido).($idp?'<br>pobyt '.dum_pobyt_nazev($idp):'')." - $typs";
  if ($duvod) $popis.= "<hr>důvod smazání: <b>$duvod</b>";
  return (object)['popis'=>$popis];
}
# ------------------------------------------------------------------------------- dum faktura_delete
# poznačí fakturu jako smazanou
function dum_faktura_delete($duvod,$idf) {
  $duvod= pdo_real_escape_string($duvod); 
  $dnes= date('Y-m-d');
  query("UPDATE faktura SET deleted='$dnes',duvod_zmeny='$duvod' WHERE id_faktura=$idf");
}
# --------------------------------------------------------------------------------- dum faktura_save
# pokud je uvedeno idf je třeba zneplatněnou fakturu pozančit jako smazanou, zpsat důvod opravy,
# vytvořit novou fakturu a její id zaměnit za idf v join_platba
function dum_faktura_save($parm,$idf=0) {
  $msg= '';
  $x= array_merge((array)$parm); $x['html']= "...";
//  debug($x,"dum_faktura_save(...)");                                                    /*DEBUG*/
  // uložení do tabulky
  $p= $parm->parm;
  $order= $p->id_order ?? '';
  $pobyt= $p->id_pobyt ?? '';
  $nadpis= pdo_real_escape_string($p->nadpis); 
  $duvod= pdo_real_escape_string($parm->duvod_zmeny).date(' (j.n.Y)'); 
//  $jso= $html= '';
  $p->parm_json= json_encode($p);
  $jso= pdo_real_escape_string($parm->parm_json); 
  $htm= pdo_real_escape_string($parm->html); 
  // 
  $ok= query("INSERT INTO faktura (nazev,rok,num,typ,strucna,vs,ss,id_order,id_pobyt,"
      . "zaloha,castka,ubyt,stra,popl,prog,jine,"
      . "vzorec,nadpis,vyrizuje,vystavena,parm_json,html,soubor) VALUES "
      . "('$p->nazev',$p->rok,$p->num,'$p->typ','$p->strucna','$p->vs','$p->ss','$order','$pobyt',"
      . "'$p->zaloha','$p->celkem','$p->ubyt','$p->stra','$p->popl','$p->prog','$p->jine',"
      . "'$p->ds_vzorec','$nadpis','$p->vyrizuje','$p->vystavena','$jso',\"$htm\",'$p->soubor')");
  if (!$ok) { 
    $msg= POZOR." nepovedlo se vytvoření faktury - kontaktuj Martina<hr>NEPLATÍ, že: "; 
  }
  elseif ($idf) {
    $idf2= pdo_insert_id();
    $dnes= date('Y-m-d');
    query("UPDATE faktura SET deleted='$dnes',duvod_zmeny='$duvod' WHERE id_faktura=$idf");
    $n= query("UPDATE join_platba SET id_faktura=$idf2 WHERE id_faktura=$idf");
    $msg= "Byla vytvořena nová faktura pod stejným číslem, původní byla označena jako smazaná.";
    if ($n) $msg.= POZOR." opravovaná faktura již byla zaplacena. Rozvaž dobře další kroky ...";
  }
  else {
    $msg= "Faktura byla uložena do seznamu faktur.";
  }
end:
  return $msg;
}
# -------------------------------------------------------------------------------------- dum faktura
# par.typ: 1=záloha | 2=daňový doklad k záloze | 3=vyúčtování | 4=výjimečná faktura 
function dum_faktura($par) { // debug($par,'dum_faktura');
  global $dum_faktura_dfl, $dum_faktura_fld; 
  // získání parametrů
  $strucna= $par->strucna ?? 0;
  $show= $par->show ?? 0;
  $save= $par->save ?? 0;
  $typ= $par->typ;
  $adresa= $par->adresa;
  $zaloha= $par->zaloha ?? 0;
  $ic= $par->ic ?? '';
  $dic= $par->dic ?? '';
  $oddo= $par->oddo;
  $rok= $par->rok;
  $num= $par->num;
  $vs= $par->vs;
  $ss= $par->ss;
  $order= $par->id_order;
  $pobyt= $par->id_pobyt; 
  $vyrizuje= $par->vyrizuje;
  $nadpis= $par->nadpis; // ignoruje se pro daňový doklad
  $vystavena= $par->vystavena;
  $date= new DateTime($vystavena); 
  $vystavena= $date->format('j. n. Y');
  $par->vystavena= $date->format('Y-m-d');
  $splatnost= $date->modify('+14 days');
  // společné údaje
  $vals['{obdobi}']= $oddo;
  $vals['{ic_dic}']= ($ic ? "IČ: $ic" : '').($dic ? "    DIČ: $dic" : '');
  $vals['{adresa}']= $adresa;
  $vals['{datum1}']= $vystavena;
  $vals['{obj}']= $order.($pobyt ? ".$pobyt" : '');
  $vals['{vyrizuje}']= $vyrizuje;
  // QR platba
  $vals['{QR-IBAN}']= 'CZ1520100000002000465448'; // Dům setkání: 2000465448 / 2010
  $vals['{QR-ds}']= urlencode('YMCA Setkání');
  $vals['{QR-vs}']= $vals['{VS}']= $vs;
  $vals['{QR-ss}']= $vals['{SS}']= $ss;
  $vals['{QR-pozn}']= urlencode("pobyt v Domě setkání");
  $vyjimecna= 0;
  // podle typu faktury
  if ($typ==3) { // vyúčtování
    $par->nazev= ($rok-2000).'74A'.str_pad($num,4,'0',STR_PAD_LEFT);
    $vals['{faktura}']= "Faktura - daňový doklad $par->nazev";
    $dum_faktura_fld['za_co'][1]= $nadpis; //"Za pobyt v Domě setkání ve dnech {obdobi} Vám fakturujeme:";
    $vals['{DUZP-text}']= '<br>Datum zdanitelného plnění';
    $vals['{DUZP-datum}']= "<br>$vystavena";
    $vals['{splatnost-text}']= '<br><b>Datum splatnosti</b>';
    $vals['{splatnost-datum}']= '<br>'.$splatnost->format('j. n. Y');
  }
  elseif ($typ==4) { // výjimečné vyúčtování zadáním konečných částek
    $vyjimecna= 1;
    $par->nazev= ($rok-2000).'74A'.str_pad($num,4,'0',STR_PAD_LEFT);
    $vals['{faktura}']= "Faktura - daňový doklad $par->nazev";
    $dum_faktura_fld['za_co'][1]= $nadpis; //"Za pobyt v Domě setkání ve dnech {obdobi} Vám fakturujeme:";
    $vals['{DUZP-text}']= '<br>Datum zdanitelného plnění';
    $vals['{DUZP-datum}']= "<br>$vystavena";
    $vals['{splatnost-text}']= '<br><b>Datum splatnosti</b>';
    $vals['{splatnost-datum}']= '<br>'.$splatnost->format('j. n. Y');
  }
  elseif ($typ==1) { // záloha
    $par->nazev= substr($rok,2,2).'08'.str_pad($num,4,'0',STR_PAD_LEFT);
    $vals['{faktura}']= "Zálohová faktura $par->nazev";
    $dum_faktura_fld['za_co'][1]= "Fakturujeme Vám zálohu na pobyt v Domě setkání ve dnech {obdobi}:";
    $vals['{DUZP-text}']= '';
    $vals['{DUZP-datum}']= '';
    $vals['{splatnost-text}']= '<br><b>Datum splatnosti</b>';
    $vals['{splatnost-datum}']= '<br>'.$splatnost->format('j. n. Y');
  }
  else { // $typ==2 daňový doklad 
    $par->nazev= substr($rok,2,2).'08'.str_pad($num,4,'0',STR_PAD_LEFT);
    $vals['{faktura}']= "Daňový doklad $par->nazev";
    $par->nadpis= "Daňový doklad k přijaté platbě <b>$zaloha Kč</b> "
        . "za zálohovou fakturu {$par->nazev}.";
    $dum_faktura_fld['za_co'][1]= $par->nadpis;
    $vals['{DUZP-text}']= '<br>Datum zdanitelného plnění';
    $vals['{DUZP-datum}']= "<br>$vystavena";
    $vals['{splatnost-text}']= '';
    $vals['{splatnost-datum}']= '';
  }
  // ------------------------------------------------------------------------------- redakce tabulky
  $polozky= [];
  $rozpis_dph= []; 
  if ($vyjimecna) { // podrobně - položky ceníku
    $strucna= 1;
    // ds_vzorec má výjimečný formát: ubyt|stra|popl|řádek popisu
    $ubyt= $par->ubyt;
    $stra= $par->stra;
    $popl= $par->popl;
    $prog= $par->prog;
    $jine= $par->jine;
    $celkem= $ubyt + $stra + $popl + $prog + $jine;
    $koef= $zaloha ? ($celkem-$zaloha)/$celkem : 1;
//    display("$celkem $zaloha $koef");
    $ds2_cena= dum_cenik($rok);
    // ubytování
    $sazba_ubyt= $ds2_cena['noc_L']->dph;
    $dph_ubyt= ($ubyt * $koef) / ((100 + $sazba_ubyt) / 100);
    $rozpis_dph[$sazba_ubyt]+= $dph_ubyt;
    // strava
    $sazba_strav= $ds2_cena['strava_CC']->dph;
    $dph_strav= ($stra * $koef) / ((100 + $sazba_strav) / 100);
    $rozpis_dph[$sazba_strav]+= $dph_strav;
    // poplatky
    $sazba_popl= $ds2_cena['ubyt_C']->dph;
    $dph_popl= ($popl * $koef) / ((100 + $sazba_popl) / 100);
    $rozpis_dph[$sazba_popl]+= $dph_popl;
    // program
    $sazba_prog= $ds2_cena['prog_C']->dph;
    $dph_prog= ($prog * $koef) / ((100 + $sazba_prog) / 100);
    $rozpis_dph[$sazba_prog]+= $dph_prog;
    // jiné služby
    $sazba_jine= $ds2_cena['noc_Z']->dph;
    $dph_jine= ($jine * $koef) / ((100 + $sazba_jine) / 100);
    $rozpis_dph[$sazba_jine]+= $dph_jine;
    $polozky= [
      ['ubytování',$sazba_ubyt.'%',dum_kc($dph_ubyt),dum_kc($ubyt),$ubyt],
      ['strava',$sazba_strav.'%',dum_kc($dph_strav),dum_kc($stra),$stra],
      ['poplatky obci',$sazba_popl.'%',dum_kc($dph_popl),dum_kc($popl),$popl]
    ];
    if ($prog) $polozky[]= ['poplatky obci',$sazba_prog.'%',dum_kc($dph_prog),dum_kc($prog),$prog];
    if ($jine) $polozky[]= ['jiné služby',$sazba_jine.'%',dum_kc($dph_jine),dum_kc($jine),$prog];
  }
  else {
    // redakce položek ceny pro zobrazení ve sloupcích
    $cena= dum_vzorec_cena($par->ds_vzorec,$rok);
//    debug($cena,"dum_vzorec_cena($par->ds_vzorec,$rok)");                                 /*DEBUG*/
    $celkem= $cena['celkem'];
    $koef= $zaloha ? $zaloha/$celkem : 1;
    $par->ubyt= $cena['abbr']['ubyt'] * $koef;
    $par->stra= $cena['abbr']['stra'] * $koef;
    $par->popl= $cena['abbr']['popl'] * $koef;
    $par->prog= $cena['abbr']['prog'] * $koef;
    $par->jine= $cena['abbr']['jine'] * $koef;
    if ($strucna==0) { // podrobně - položky ceníku
      ksort($cena['rozpis']);
      foreach ($cena['rozpis'] as $zaco=>$pocet) {
  //      display("$zaco:$pocet");
        $kc_1= $cena['polozka'][$zaco]->cena;
        $kc= $cena['cena'][$zaco];
        $sazba= $cena['polozka'][$zaco]->dph;
        $polozky[]= [
          $cena['polozka'][$zaco]->polozka,
          $pocet,
          dum_kc($kc_1),
          $sazba.'%',
          dum_kc($cena['cena_dph'][$zaco]),
          dum_kc($kc),
          $kc // 7: celková cena vč. DPH
        ];
        $rozpis_dph[$sazba]+= ($kc - $zaloha) / ((100 + $sazba) / 100);
      }
    }
    elseif ($strucna==1) { // jen přehled ubytování - strava - poplatky - jiné
      foreach ($cena['druh2'] as $cc) {
        $kc= $cc['cena'];
        $kc_dph= $typ==2 ? $kc*$koef : $kc;
        $sazba= $cc['sazba'];
        $polozky[]= [
          $cc['druh'],
          $sazba.'%',
          dum_kc($cc['dph']),
          dum_kc($kc),
          $kc 
        ];
        $rozpis_dph[$sazba]+= $kc_dph / ((100 + $sazba) / 100);
      }
    }
  }
//  debug($polozky,'polozky NEW');                                                          /*DEBUG*/
  // zápis rozpisu DPH
//  debug($rozpis_dph,'rozpis DPH');                                                        /*DEBUG*/
  
  // nadpisy položek a šířka polí
  $cs= $strucna==0 ? [6,4,6,3,3] :  [4,2,4,1,1];
  $lrtb= "border:0.1mm dotted black";
  $tab= '<table style="border-collapse:collapse" cellpadding="1mm">';
  if ($typ!=2) {
    $popisy= explode(',', $strucna==0
      ? "Položka:79,Počet:12,Cena položky vč. DPH:26,Sazba DPH:14,DPH:25,Cena vč. DPH:28"
      : "Položka:107,Sazba DPH:24,DPH:25,Cena vč. DPH:28")  ;
    $tab.= "<tr>";
    foreach ($popisy as $i=>$ts) {
      list($t,$s)= explode(':',$ts);
      $align= $i ? 'right' : 'left';
      $tab.= "<td align=\"$align\" style=\"$lrtb;width:{$s}mm\"><b>$t</b></td>";
    }
    $tab.= "</tr>";
    $tab.= "\n<tr>";
    for ($i= 0; $i<=($strucna==0?5:3); $i++) {
  //    if ($i==3) continue;
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
    $colspan= "colspan=\"$cs[0]\"";
    $tab.= "<tr><td $colspan><br><br></td></tr>";
    if ($typ==1) { // záloha
      $soucty= ['Celková cena s DPH'=>$celkem, 'Zaplaťte zálohu'=>$zaloha];
      $bold= 0;
      $koef= $zaloha/$celkem;
      $platit= $celkem*$koef;
    }
    else { // konečná
      $platit= $celkem - $zaloha;
      if ($zaloha) {
        $soucty= ['Celková cena s DPH '=>$celkem, 
            'Zaplaceno zálohou '=>$zaloha?:0, 'Zbývá k zaplacení '=>$platit];
        $bold= 0;
        $koef= 1;
      }
      else {
        $soucty= ['Celková cena s DPH '=>$celkem];
        $bold= 1;
        $koef= 1;
      }
    }
    foreach ($soucty as $popis=>$castka) {
      $castka= dum_kc($castka);
      if ($bold) {
        $popis= "<b>$popis</b>";
        $castka= "<b>$castka</b>";
      }
      $colspan= "colspan=\"$cs[1]\"";
      $tab.= "<tr><td $colspan style=\"text-align:right\">$popis</td>"
        . "<td colspan=\"2\" align=\"right\" style=\"$lrtb\">$castka</td></tr>";
      $bold++;
    }
  }
  else { // daňový doklad k zaplacené záloze
    $css= 107;
    $tab.= "<tr><td colspan=\"1\" style=\"width:{$css}mm\"></td>"
      . "<td style=\"width:24mm\"></td>"
      . "<td style=\"width:25mm\"></td>"
      . "<td style=\"width:28mm\"></td>"
      . "</tr>";    
    $koef= 1;
  }
  // rozpisová tabulka DPH
  $tab_dph= [-1=>['<b>Sazba</b>','<b>Daň</b>','<b>Základ</b>']];
  foreach ($rozpis_dph as $d=>$c) {
    $dan= round($c*$d/100,2);
    $tab_dph[]= ["$d%",dum_kc($dan*$koef),dum_kc($c*$koef)];
  }
  $colspan= "colspan=\"$cs[2]\"";
  $tab.= "<tr><td $colspan><br></td></tr>";
  $colspan= "colspan=\"$cs[3]\"";
  $tab.= "<tr><td $colspan></td><td colspan=\"3\"><b>Rozpis DPH</b></td></tr>";
  $colspan= "colspan=\"$cs[4]\"";
  foreach ($tab_dph as $c) {
    $tab.= "<tr><td $colspan></td>"
      . "<td align=\"right\" style=\"$lrtb\">$c[0]</td>"
      . "<td align=\"right\" style=\"$lrtb\">$c[1]</td>"
      . "<td align=\"right\" style=\"$lrtb\">$c[2]</td>"
      . "</tr>";
  }
  $tab.= '</table>';
//  display(htmlentities($tab));
  // počet zúčtovaných položek ceníku kvůli řádkování tabulky
  $polozek= 0;
  foreach($polozky as $p) {
    if ($p) $polozek++;
  }
  // doplnění vypočítaných fakturačních údajů
  $vals['{tabulka}']= $tab;
  $vals['{QR-castka}']= round($platit,2);
  $vals['{polozek}']= $polozek; 

//                                              debug($vals,'fakturujeme');
//  debug($vals);
//  goto end;
  // redakce faktury
  $lheight_tabulka= $vals['{polozek}']>7 ? 1.5 : 2;
  $html= '';
  if ($show) {
    $html.= "<div class='PDF' style='scale:83%;position:absolute'>";
    $html.= "<style>.PDF div{padding-top:1mm}</style>";
    $html.= "<div style='position:absolute;width:210mm;height:297mm;border:1px solid grey;background:white'>";
    $j= 'mm';
  }
  // zobrazení
  if ($save) {
    tc_page_open();
  }
  $x_dfl= explode(',',$dum_faktura_dfl);
  foreach ($dum_faktura_fld as $jmeno=>$cast) {
    $x= $x_dfl; 
  // doplnění podle defaultu
    foreach (explode(',',$cast[0]) as $i=>$c) {
      if ($c) $x[$i]= $c;
    }
//    debug($x,'$type,$align,$fsize,$l,$t,$w,$h,$border');
    list($type,$align,$fsize,$l,$t,$w,$h,$border,$lheight)= $x;
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
      if ($type=='T') {
        $elem= "<div style='position:absolute;"
            . "left:{$l}$j;top:{$t}$j;width:{$w}$j;height:{$h}$j;line-height:$lheight;"
            . "font-size:{$fsize}$j$bord$algn'>$text</div>";
  //      display(htmlentities($elem));
        $html.= $elem;
      }
      elseif ($type=='I') {
        $elem= "<img src='$text' style='position:absolute;"
            . "left:{$l}$j;top:{$t}$j;width:{$w}$j;height:{$h}$j'>";
//        display(htmlentities($elem));
        $html.= $elem;
      }
      elseif ($type=='QR' && $typ!=2) {
        $castka= dum_kc($vals['{QR-castka}']);
        $qr= "<br>QR platba<br><br><b>$castka</b><br><br>bude zobrazena<br>v PDF";
//        require_once('tcpdf/examples/barcodes/tcpdf_barcodes_2d_include.php');
//        $barcodeobj= new TCPDF2DBarcode($text,'QRCODE,H');
//        $qr= $barcodeobj->getBarcodePNG(6, 6, 'black');        
        $elem= "<div style='position:absolute;"
            . "left:{$l}$j;top:{$t}$j;width:{$w}$j;height:{$h}$j;"
            . "font-size:{$fsize}$j;border:5px dotted black;text-align:center'>$qr</div>";
        $html.= $elem;
      }
    }
    if ($save && ($type!='QR' || $typ!=2)) {
      tc_page_cell($text,$type,$align,$fsize*2.4,$l,$t,$w,$h,$border,$lheight);
    }
  }
  // doplnění par o výpočet
  $par->celkem= $celkem;
  if ($show) {
    $html.= "</div></div>";
  }
  $ref= '';
  if ($save) {
    global $abs_root;
    $dnes= date('Ymd');
    $zkratka= substr(strtr(utf2ascii($adresa),['<br>'=>'',' '=>'','_'=>'']),0,10); 
    $fname= "Dum-setkani_{$order}_{$dnes}_$zkratka.pdf";
    $par->soubor= $fname;
    $dir= "$abs_root/docs/dum_setkani/faktury";
    if (!is_dir($dir)) recursive_mkdir($dir,'/');
    $f_abs= "$dir/$fname";
    $f_rel= "docs/dum_setkani/faktury/$fname";
    tc_page_close($f_abs,$html);
    $ref= "<a target='pdf' href='$f_rel' style='display:inline'>zde</a>";
  }
end:
//  debug($par,"dum_faktura");
  $html_exp= <<<__HTML
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="cs" dir="ltr">
<div style="font-size:11px;font-family:Arial,Helvetica,sans-serif">
$html
</div></html>
__HTML;
//  display($html);
  file_put_contents("fakt.html",$html_exp);
//  debug($par,'dum_faktura ... par');                                                      /*DEBUG*/
  return (object)array('html'=>$html_exp,'ref'=>$ref,'parm_json'=>json_encode($par),
      'parm'=>$par,'err'=>'');
}
/** ====================================================================================> OBJEDNÁVKY **/
# ------------------------------------------------------------------------- dum objednavka_akce_make
# vytvoř akci k objednávce
function dum_objednavka_akce_make($id_order) { 
  global $setkani_db;
  $org_ds= org_ds;
  $tit= dum_objednavka_nazev($id_order);
  list($od,$do)= select('od,do',"objednavka","id_order=$id_order");
  $ida= query_track("INSERT INTO akce (access,druh,nazev,misto,datum_od,datum_do,ma_cenik) "
      . "VALUE ($org_ds,64,'$tit','Dům setkání','$od','$do',2)");
  query("UPDATE $setkani_db.tx_gnalberice_order SET id_akce=$ida WHERE uid=$id_order");
  return $ida;
}
# ----------------------------------------------------------------------------------- dum objednavka
# objednávka pobytu
function dum_objednavka($id_order) { 
  global $answer_db, $setkani_db;
  $x= (object)['err'=>'','vyuziti'=>[],'cena'=>[],'fld'=>[]];
  // shromáždění údajů z objednávky
  $rf= pdo_qry("
      SELECT state,fromday AS od,untilday AS do,d.note,rooms1,adults,kids_10_15,kids_3_9,kids_3,board,
        d.nazev AS d_nazev,IFNULL(a.nazev,'') AS a_nazev,a.typ AS a_typ,
        org,ic,name,firstname,dic,email,telephone,address,zip,city,
        DATEDIFF(FROM_UNIXTIME(untilday),FROM_UNIXTIME(fromday)) AS noci,id_akce,akce 
        -- ,f.num,f.typ,f.vs,f.ss,f.zaloha,f.castka,f.vystavena,p.datum AS zaplacena,f.vyrizuje
      FROM $setkani_db.tx_gnalberice_order AS d
        LEFT JOIN akce AS a ON id_duakce=id_akce
      --  LEFT JOIN faktura AS f ON id_order=uid
      --  LEFT JOIN join_platba AS pf USING (id_faktura) 
      --  LEFT JOIN platba AS p USING (id_platba) 
      WHERE uid=$id_order");
  $f= pdo_fetch_object($rf);
  $f->id_order= $id_order;
  $f->rok= date('Y',$f->od);
  $f->oddo= datum_oddo(date('Y-m-d',$f->od),date('Y-m-d',$f->do));
  $f->od= date('j.n.Y',$f->od);
  $f->do= date('j.n.Y',$f->do);
  // již vystavená zálohová faktura na objednávku nebo návrh čísla faktury
  $f->zal_num= $f->dan_num= select1('IFNULL(MAX(num)+1,1)','faktura',"rok=$f->rok AND typ IN (1,2)");
  $f->fakt_num= select1('IFNULL(MAX(num)+1,1)','faktura',"rok=$f->rok AND typ IN (3,4)");
  //$f->id_akce= select('id_duakce','akce',"id_order=$id_order");
  $f->nazev= $f->d_nazev ?: ($f->a_typ==3 ? $f->a_nazev : $f->note);
//  $z[$uid]->nazev= $d_nazev ?: ($typ==3 ? $a_nazev : $note);
  $x->fld= $f;
  $x->adresa= ($f->org ? "$f->org<br>" : '')
      . "$f->firstname $f->name"
      . "<br>$f->address"
      . "<br>$f->zip $f->city";
  // výpočet ceny pro zálohovou fakturu
  $rozpis= dum_objednavka_zaloha($x->fld);
  $x->vzorec_zal= dum_rozpis2vzorec($rozpis);
  $x->cena= dum_objednavka_cena($rozpis,$f->rok);
  // zjištění skutečně spotřebovaných osobonocí, pokojů, stravy, poplatků, ...
  $y= dum_browse_order((object)['cmd'=>'browse_load','cond'=>"uid=$id_order"]);
  $x->ucet= $y->suma;  
  $x->vzorec_fak= $y->suma->vzorec;
  foreach (explode(',',$x->vzorec_fak) as $ins) {
    list($i,$n,$s)= explode(':',$ins);
    $x->vyuziti[$i]= $s ? "$n/$s" : $n;
  }
//  $x->vzorec_fak= dum_rozpis2vzorec($y->suma->rozpis);
  // a fakturu z tabulky
  $x->faktura= (object)['zal_idf'=>0,'dan_idf'=>0,'fakt_idf'=>0,'vyj_idf'=>0];
  $rf= pdo_qry("
    SELECT IFNULL(id_faktura,0) AS idf,typ,strucna,nadpis,
      rok,num,f.vs,f.ss,duvod_zmeny,vzorec,zaloha,f.castka,vystavena,p.datum AS zaplacena, vyrizuje 
    FROM faktura AS f
      LEFT JOIN join_platba AS pf USING (id_faktura) 
      LEFT JOIN platba AS p USING (id_platba) 
    WHERE deleted='' AND id_order=$id_order AND typ IN (1,2,3,4)",0,0,0,$answer_db);
  while ($rf && ($f= pdo_fetch_object($rf))) {
    if ($f->typ==4) { 
      $x->faktura->vyj_idf= $f->idf;
      $x->faktura->fakt_vyjimka= 1;
      $kcs= explode('|',$f->vzorec);
      list($x->faktura->vyj_ubyt,$x->faktura->vyj_strav,$x->faktura->vyj_popl)= $kcs;
      $x->faktura->vyj_celkem= array_sum($kcs);
    }
    foreach ($f as $fld=>$val) { 
      if ($fld=='idf' && $f->typ==4) continue;
      if ($fld=='vystavena' || $fld=='zaplacena') $val= sql_date1($val);
      $fld= $f->typ==1 ? "zal_$fld" : ($f->typ==2 ? "dan_$fld" : "fakt_$fld");
      $x->faktura->$fld= $val;
    }
  }
//  debug($x,"dum_objednavka($id_order)");                                                  /*DEBUG*/
  return $x;
}
# ------------------------------------------------------------------------------ dum objednavka_akce
# vrátí ID objednávky spojené s akcí nebo 0
function dum_objednavka_akce($id_akce) { 
  global $setkani_db;
  return select1('IFNULL(uid,0)',"$setkani_db.tx_gnalberice_order","id_akce=$id_akce");
}
# ---------------------------------------------------------------------------- dum objednavka_delete
# vrátí ID objednávky spojené s akcí nebo 0
function dum_objednavka_delete($id_order) { 
  global $setkani_db;
  query("DELETE FROM $setkani_db.tx_gnalberice_order WHERE uid=$id_order");
}
# ------------------------------------------------------------------------------ dum objednavka_save
# objednávka pobytu
function dum_objednavka_save($id_order,$changed) { 
  global $answer_db, $setkani_db;
  $set= ""; $del= '';
  $set_akce= ""; $del_akce= '';
  foreach($changed as $fld=>$val) {
    if (in_array($fld,['od','do'])) {
      $ymd= sql_date1($val,1);
      $set_akce.= $del_akce.($fld=='od' ? 'datum_od' : 'datum_do')."='$ymd'";
      $del_akce= ',';
      $val= strtotime($ymd);
      $fld= $fld=='od' ? 'fromday' : 'untilday';
    }
    $val= pdo_real_escape_string($val);
    $set.= "$del$fld='$val'";
    $del= ',';
  }
  query("UPDATE $setkani_db.tx_gnalberice_order SET $set WHERE uid=$id_order");
  if ($set_akce) {
    $ida= select('id_akce',"$setkani_db.tx_gnalberice_order","uid=$id_order");
    if ($ida)
      query("UPDATE $answer_db.akce SET $set_akce WHERE id_duakce=$ida");
    else
      fce_error("dum_objednavka_save: objednávka $id_order nemá nastavenou akce (id_akce)");
  }
}
# ----------------------------------------------------------------------------- dum objednavka_nazev
# vrátí tisknutelný název objednávky
function dum_objednavka_nazev($ido,$format='') {
  $rd= pdo_qry("
    SELECT state,IFNULL(g_kod,''),IFNULL(a.nazev,''),d.nazev,TRIM(CONCAT(jmeno,' ',prijmeni)) AS jm,jmeno,org
    FROM objednavka AS d
    LEFT JOIN join_akce USING (id_akce)
    LEFT JOIN akce AS a ON id_akce=id_duakce
    WHERE d.id_order=$ido
  ");
  if ($rd) list($state,$kod,$akce,$d_nazev,$jm,$org)= pdo_fetch_array($rd);
  $naz= $state==3 ? "$akce ($kod)" : ( $d_nazev ?: ($org ? $org : $jm));
  return $format=='' ? "$ido - $naz" : ($format=='kniha' ? $naz : $ido);
}
# ----------------------------------------------------------------------------- dum objednavka_nazev
# vrátí informaci pro AKCE
function dum_objednavka_info($ido,$ida,$html_akce) { trace();
  global $setkani_db;
  $stav= map_cis('ds_stav','hodnota');
  $o= select_object('*',"$setkani_db.tx_gnalberice_order","uid=$ido");
  $conv= '';
  $conv= str_replace("'",'"',$o->DS2024);
  $conv= $conv ? json_decode($conv) : (object)['typ'=>'neuskutečněná'];
//  debug($conv,$o->DS2024);                                                            /*DEBUG*/
  $conv= debugx($conv);
  $conv= '';
  // 
  $html.= "<h3 style='margin:0px 0px 3px 0px;'>Objednávka pobytu v Domě setkání</h3>";
    $adresa= ($o->org ? "$o->org," : ''). "$o->firstname $o->name, $o->address, $o->zip $o->city";
    $oddo= datum_oddo(date('Y-m-d',$o->fromday),date('Y-m-d',$o->untilday));
    $html.= "<br>Stav: <b>{$stav[$o->state]}</b><br><br>Objednatel: $adresa<br>Termín: $oddo"
        . "<br>Poznámka: $o->note<br>Telefon: $o->telephone<br>Email: $o->email<br>";
  if (!$ida) {
    $html.= "<br><i>Zatím není zařazena jako akce</i><br><br>"
        . " <button onclick=\"Ezer.fce.href('akce2.lst.dum_objednavka/add/$ido')\">"
        . "Vložit objednávku jako akci</button>"
        . " &nbsp; <button onclick=\"Ezer.fce.href('akce2.lst.dum_objednavka/del/$ido')\">"
        . "Smazat objednávku</button>";
  }
  else {
    $html.= "<br><button onclick=\"Ezer.fce.href('akce2.lst.dum_objednavka/show/$ida')\">
            Objednávka a vyúčtování pobytu</button> $conv
            <br><br>$html_akce";
  }
  return $html;
}
# ----------------------------------------------------------------------------- dum clone_objednavka
# udělej klon objednávky
function dum_objednavka_clone($id_order) {  
  global $setkani_db, $answer_db;
  $id_akce= select('id_akce',"$setkani_db.tx_gnalberice_order","uid=$id_order");
  if ($id_akce) {
    $new_akce= clone_row("$answer_db.akce",$id_akce,'id_duakce');
    $new_order= clone_row("$setkani_db.tx_gnalberice_order",$id_order,'uid');
    query("UPDATE $setkani_db.tx_gnalberice_order SET "
        . "id_akce=$new_akce "
        . "WHERE uid=$new_order");
    query("UPDATE $answer_db.akce SET "
        . "nazev='$new_order - kopie $id_order' "
        . "WHERE id_duakce=$new_akce");
    $msg= "Byla vytvořena kopie akce:$new_akce objednávky:$id_order";
  }
  else {
    $msg= "Objednávka $id_order nemá nastavenou akci";
  }
  return $msg;
}
# ------------------------------------------------------------------------------------- dum pokoj_25
# "ubytuje" neubytované na pokoji 25 kvůli tvrobě ceny - JEN STARÉ AKCE
function dum_pokoj_25($id_order) {
  global $answer_db;
  $msg= '';
  $sql_hranice= '2024-06-01'; $hranice= sql_date1($sql_hranice); // pro novější to dělat nebudeme
  ezer_connect($answer_db,true);
  // projdi neubytované přítomné 
  $n= $ne= 0;
  $rp= pdo_qry("
    SELECT id_spolu,s.ds_pokoj,p.pokoj,DATEDIFF('$sql_hranice',datum_od)
    FROM objednavka AS d
      JOIN akce AS a ON id_akce=id_duakce 
      JOIN pobyt AS p USING (id_akce) 
      JOIN spolu AS s USING (id_pobyt) 
      JOIN _cis AS c ON c.druh='ms_akce_funkce' AND c.data=p.funkce
    WHERE id_order=$id_order AND c.ikona=0 
  ");
  while ($rp && (list($ids,$s_pokoj,$p_pokoj,$ok)= pdo_fetch_array($rp))) {
    if ($ok<0) { 
      $msg= "Opravu lze použít jen pro akce před $hranice";
      goto end;
    }
    $n++;
    if (!$s_pokoj && !$p_pokoj) {
      $ne++;
      query("UPDATE spolu SET ds_pokoj=25 WHERE id_spolu=$ids");
    } 
  }
  $msg= "Na akci bylo z $n přítomných $ne neubytovaných";
end:
  return $msg;
}
# ----------------------------------------------------------------------------------- ds2 rooms_help
# vrátí popis pokojů
function dum_rooms_help($version=1) {
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
# ==================================================================================> VZOREC + CENÍK
# ----------------------------------------------------------------------------------- dum ... vzorec
# $spolu je buďto klíč id_spolu
# nebo objekt {vek,pokoj,state,board,noci,dotace}
function dum_osoba_vzorec($s,$rok) { // debug($s,"> dum osoba_vzorec(,$rok)");
  $ds2_cena= dum_cenik($rok);
//  debug($ds2_cena);
  $luzko_pokoje= dum_cat_typ();
  $ds_strava= map_cis('ds_strava','zkratka');
  $rozpis= [];
  // ubytování osob podle věku
  $noc= 'noc_'.($s->ds_pristylka ? 'P' : $luzko_pokoje[$s->pokoj]);
  // poplatky podle věku
  $poplatky= ['ubyt_P','ubyt_C','ubyt_S','noc_B',$noc];
  if ($s->state==3) { // akce YMCA má poplatek za program
    array_push($poplatky,'prog_C','prog_P');
  }
  foreach ($poplatky as $p) {
    if ($s->vek >= $ds2_cena[$p]->od && $s->vek < $ds2_cena[$p]->do && $ds2_cena[$p]->cena) {
      $rozpis[$p]= $s->ds_zdarma ? [0,0,$s->noci] : (
          $s->ds_dotace && $ds2_cena[$p]->dotovana ? [0,$s->noci] : [$s->noci,0]);
    }
  }
  // strava osob podle věku
  $strava= 'strava_'.$ds_strava[$s->board];
  if ($s->vek>=$ds2_cena[$strava.'D']->od && $s->vek<$ds2_cena[$strava.'D']->do) {
    $p= "{$strava}D";
    $rozpis[$p]= $s->ds_zdarma ? [0,0,$s->noci] : (
        $s->ds_dotace && $ds2_cena[$p]->dotovana ? [0,$s->noci] : [$s->noci,0]);
  }
  if ($s->vek>=$ds2_cena[$strava.'C']->od && $s->vek<$ds2_cena[$strava.'C']->do) {
    $p= "{$strava}C";
    $rozpis[$p]= $s->ds_zdarma ? [0,0,$s->noci] : (
        $s->ds_dotace && $ds2_cena[$p]->dotovana ? [0,$s->noci] : [$s->noci,0]);
  }
  // postýlka a zvíře
  if ($s->ds_zvire) $rozpis['noc_Z']= [$s->noci,0];
  if ($s->ds_postylka) $rozpis['pobyt_P']= [1,0];
//  debug($rozpis,"dum osoba_vzorec(,$rok) >");                                             /*DEBUG*/
  $vzorec= dum_rozpis2vzorec($rozpis);
  return $vzorec;
}
# ----------------------------------------------------------------------------------------- dum cena
# dum_vzorec_cena: vzorec -> cena  
# kde vzorec = část (',' část)* 
#     část = položka ':' počet v plné sazbě [ ':' počet v dotované sazbě [ ':' počet zdarma ]]
function dum_vzorec_cena($vzorec,$rok_ceniku) { //trace();
  $ds2_cena= dum_cenik($rok_ceniku);
  // podrobný rozpis ceny podle druhu a dph, včetně typ->polozka
  $cena= ['celkem'=>0,'druh'=>[],'abbr'=>[],'cena'=>[],'cena_dph'=>[],'dph'=>[],'rozpis'=>[],
      'polozka'=>[]]; 
//  $rozpis= is_string($vzorec) ? explode(',',$vzorec) : $vzorec;
  $rozpis= dum_vzorec2rozpis($vzorec);
  foreach ($rozpis as $zaco=>$csz) {
    list($c,$s,$z)= (array)$csz; // cena, sleva, zdarma
    $d= $ds2_cena[$zaco];
    $druh= utf2ascii($d->druh);
    if ($c) { // plná nákladová cena
      $pocet= $c;
      $kc= $d->cena;
      $cena['naklad']+= $kc * $pocet;
      $cena['celkem']+= $kc * $pocet;
      $cena['druh'][$druh]+= $kc * $pocet;
      $cena['abbr'][substr($druh,0,4)]+= $kc * $pocet;
      $cena['cena'][$zaco]= $kc * $pocet;
      $cena['cena_dph'][$zaco]= $dph= $kc * $pocet - ($kc * $pocet) / ((100 + $d->dph) / 100);
      $cena['rozpis'][$zaco]= $pocet;
      $cena['polozka'][$zaco]= (object)['polozka'=>$d->polozka,'cena'=>$kc,'dph'=>$d->dph];
      $cena['druh2'][$druh]['cena']+= $kc * $pocet;
    }
    if ($s) { // dotované
      $pocet= $s;
      $zaco_d= "$zaco/d";
      $kc= $d->dotovana;
      $cena['naklad']+= $d->cena * $pocet;
      $cena['celkem']+= $kc * $pocet;
      $cena['druh'][$druh]+= $kc * $pocet;
      $cena['abbr'][substr($druh,0,4)]+= $kc * $pocet;
      $cena['cena'][$zaco_d]= $kc * $pocet;
      $cena['cena_dph'][$zaco_d]= $dph= $kc * $pocet - ($kc * $pocet) / ((100 + $d->dph) / 100);
      $cena['rozpis'][$zaco_d]= $pocet;
      $cena['polozka'][$zaco_d]= (object)['polozka'=>"$d->polozka ... dotovaná cena",'cena'=>$kc,'dph'=>$d->dph];
      $cena['druh2'][$druh]['cena']+= $kc * $pocet;
    }
    if ($z) { // zadarmo
      $dph= 0;
      $pocet= $z;
      $zaco_z= "$zaco/z";
      $cena['naklad']+= $d->cena * $pocet;
      $cena['rozpis'][$zaco_z]= $pocet;
      $cena['polozka'][$zaco_z]= (object)['polozka'=>"$d->polozka ... zdarma",'cena'=>0,'dph'=>0];
    }
    $cena['druh2'][$druh]['druh']= $d->druh;
    $cena['dph'][$d->dph]+= $dph;
    $cena['druh2'][$druh]['dph']+= $dph;
    $cena['druh2'][$druh]['sazba']= $d->dph;
  }
//  debug($cena,"dum_vzorec_cena($vzorec,$rok_ceniku)");                                    /*DEBUG*/
  return $cena;
}
function dum_rozpis2vzorec($rozpis) {
  $vzorec= ''; $del= '';
  foreach ($rozpis as $i=>$vdz) {
    list($v,$d,$z)= (array)$vdz;
    $vzorec.= "$del$i:$v:$d:$z"; $del= ',';
  }
  return $vzorec;
}
function dum_vzorec2rozpis($vzorec) {
  $rozpis= []; 
  foreach (explode(',',$vzorec) as $iv) {
    list($i,$v,$d,$z)= explode(':',$iv);
    if (!$i) continue;
    $rozpis[$i][0]+= 0+$v;
    $rozpis[$i][1]+= 0+$d??0;
    $rozpis[$i][2]+= 0+$z??0;
  }
  return $rozpis;
}
# ------------------------------------------------------------------------------ dum objednavka_cena
# k položkám ceníku přidá spotřebu
function dum_objednavka_cena($rozpis,$rok_ceniku) { 
  $ds2_cena= dum_cenik($rok_ceniku);
  $cena= ['celkem'=>0,'druh'=>[],'dph'=>[],'rozpis'=>$rozpis]; // rozpis ceny podle druhu a dph
//  debug($ds2_cena);  
  foreach ($rozpis as $zaco=>$pocet) {
    $d= $ds2_cena[$zaco];
    $druh= utf2ascii($d->druh);
    $cena['celkem']+= $d->cena * $pocet;
    $cena['druh'][$druh]+= $d->cena * $pocet;
    $cena['dph'][$d->dph]+= ($d->cena * $pocet) / ((100 + $d->dph) / 100);
  }
  return $cena;
}
# ---------------------------------------------------------------------------- dum objednavka_zaloha
# k položkám ceníku přidá spotřebu
function dum_objednavka_zaloha($x) { 
  $ds2_cena= dum_cenik($x->rok);
  $cena= [];
  foreach (array_keys($ds2_cena) as $zaco) {
    switch ($zaco) {
      case 'noc_L':  
        $cena[$zaco]= $x->noci * ($x->adults + $x->kids_10_15 + $x->kids_3_9); break;
      case 'noc_B':  
        if ($x->kids_3) $cena[$zaco]= $x->noci * $x->kids_3; break;
      case 'strava_CC':  
        if ($x->board==1) $cena[$zaco]= $x->noci * ($x->adults + $x->kids_10_15); break;
      case 'strava_PC':  
        if ($x->board==2) $cena[$zaco]= $x->noci * ($x->adults + $x->kids_10_15); break;
      case 'strava_CD':  
        if ($x->board==1 && $x->kids_3_9) $cena[$zaco]= $x->noci * $x->kids_3_9; break;
      case 'strava_PD':  
        if ($x->board==2 && $x->kids_3_9) $cena[$zaco]= $x->noci * $x->kids_3_9; break;
      case 'ubyt_C':  
        $cena[$zaco]= $x->noci * $x->adults; break;
      case 'prog_C':  
      case 'prog_P':  
        if ($x->state==3) { // akce YS
          $cena[$zaco]= $x->noci * ($zaco=='prog_P' ? $x->kids_3_9 : $x->adults); 
        }
        break;
    } 
  }
  return $cena;
}
# ---------------------------------------------------------------------------------------- ds2 cenik
# načtení ceníku pro daný rok
function dum_cenik($rok) {  //trace();
  global $ds2_cena;
  if (!$rok) {
    fce_warning("pokus zjistit ceny roku 0");
    $rok= 0;
  }
  if (!isset($ds2_cena['rok']) || $ds2_cena['rok']!=$rok) {
    $ds2_cena= array('rok'=>$rok);
    ezer_connect('setkani');
    $qry2= "SELECT * FROM ds_cena WHERE rok=$rok ORDER BY druh,typ";
    $res2= pdo_qry($qry2);
    while ( $res2 && $c= pdo_fetch_object($res2) ) {
      $wc= $c;
      $wc->polozka= wu($c->polozka);
      $wc->druh= wu($c->druh);
      $ds2_cena[$c->typ]= $wc;
    }
  }
//                                                 debug($ds2_cena,"dum_cenik($rok)");
  return $ds2_cena;
}
# -------------------------------------------------------------------------------------- ds2 cat_typ
# přepočet kategorie pokoje na typ ubytování v ceníku    
function dum_cat_typ() {
  global $setkani_db, $dum_luzko_pokoje;
    if (!isset($dum_luzko_pokoje)) {
    $cat_typ= array('C'=>'A','B'=>'L','A'=>'S');
    $dum_luzko_pokoje[0]= 0;
    $rr= pdo_qry("SELECT number,category FROM $setkani_db.tx_gnalberice_room WHERE version=1");
    while ( $rr && list($pokoj,$typ)= pdo_fetch_row($rr) ) {
      $dum_luzko_pokoje[$pokoj]= $cat_typ[$typ];
    }
  }
  return $dum_luzko_pokoje;  
}
# ==================================================================================> obsluha BROWSE
// ------------------------------------------------------------------------------- dum browse_orders
# BROWSE ASK - obsluha browse s optimize:ask
# x->order= {a|d} polozka
# x->show=  {polozka:[formát,vzor/1,...],...} pro položky s neprázdným vzorem
#                                             kde formát=/ = # $ % @ * .
# x->cond= podmínka   - pokud obsahuje /*duplicity*/ přidá se sloupec _dup
#                       pokud obsahuje /*dokumenty*/ přidá se do sloupce _docs 'd'
#                       pokud obsahuje /*css*/ bude se barvit _nazev,cleni.jmeno,rodiny
# -- x->atr=  pole jmen počítaných atributů:  [_ucast]
# pokud je tisk=true jsou oddělovače řádků '≈' (oddělovač sloupců zůstává '~')
function dum_browse_orders($x) {
  global $answer_db, $setkani_db, $y; // y je zde globální kvůli možnosti trasovat SQL dotazy
//  debug($x,"dum_browse_order");
  $y= (object)array('ok'=>0);
  $curr= $x->sql; // předání pracovní akce
  switch ($x->cmd) {
  case 'browse_load':  # -----------------------------------==> . browse_load
    $z= [];
    ezer_connect($answer_db,true);
    $rp= pdo_qry("
      SELECT uid,d.id_akce,a.access,name,d.note,SUM(IF(IFNULL(id_osoba,0),1,0)),
        d.nazev,IFNULL(a.nazev,''),IFNULL(a.typ,0),
        DATE(FROM_UNIXTIME(fromday)),DATE(FROM_UNIXTIME(untilday))
      FROM $setkani_db.tx_gnalberice_order AS d
        LEFT JOIN $answer_db.akce AS a ON id_duakce=id_akce 
        LEFT JOIN pobyt AS p ON p.id_akce=id_duakce
        LEFT JOIN spolu USING (id_pobyt)
        LEFT JOIN osoba USING (id_osoba)
      WHERE $x->cond
      GROUP BY uid
      ORDER BY fromday,uid
    ");
    while ($rp && (list(
        $uid,$ida,$access,$name,$note,$osob,$d_nazev,$a_nazev,$typ,$od,$do)= pdo_fetch_array($rp))) {
      $z[$uid]->id_order= $uid;
      $z[$uid]->id_akce= $ida;
      $z[$uid]->curr= $ida==$curr ? 1 : 0;
      $z[$uid]->access= $access;
      $z[$uid]->nazev= $d_nazev ?: ($typ==3 ? $a_nazev : $note);
      $z[$uid]->objednal= $name;
      $z[$uid]->osob= $osob;
      $z[$uid]->od= sql_date1($od);
      $z[$uid]->do= sql_date1($do);
    }
    # předání pro browse
    $y->from= 0;
    $y->cursor= 0;
    $y->rows= count($z);
    $y->count= count($z);
    $y->quiet= $x->quiet;
    $y->key_id= 'id_order';
    $y->ok= 1;
//    $y->seek= 2278;
//    debug($y,"dum_browse_orders>  ");
    $y->values= $z;
    array_unshift($y->values,null);
  }
  return $y;  
}
// -------------------------------------------------------------------------------- dum browse_order
# BROWSE ASK - obsluha browse s optimize:ask + sumarizace realizace objednávky
# x->order= {a|d} polozka
# x->show=  {polozka:[formát,vzor/1,...],...} pro položky s neprázdným vzorem
#                                             kde formát=/ = # $ % @ * .
# x->cond= podmínka   - pokud obsahuje /*duplicity*/ přidá se sloupec _dup
#                       pokud obsahuje /*dokumenty*/ přidá se do sloupce _docs 'd'
#                       pokud obsahuje /*css*/ bude se barvit _nazev,cleni.jmeno,rodiny
# -- x->atr=  pole jmen počítaných atributů:  [_ucast]
# pokud je tisk=true jsou oddělovače řádků '≈' (oddělovač sloupců zůstává '~')
function dum_browse_order($x) {
  global $answer_db, $setkani_db, $y; // y je zde globální kvůli možnosti trasovat SQL dotazy
//  debug($x,"dum_browse_order");
  $y= (object)array('ok'=>0);
  switch ($x->cmd) {
  case 'browse_load':  # -----------------------------------==> . browse_load
    $z= [];
    // spotřeba 
    // pokoje: pokoj -> hostů
    // polozka: cena.
    $suma= (object)[
        'celkem'=>0,
        'druh'  =>[],
        'abbr'  =>[],
        'dph'   =>[],
        'pokoj' =>[],'pokoje'=>'',
        'neubytovani'=>'zatím nikdo není ubytován',  // případný text varování o neubytovaných
        'rozpis'=>[],
        'hoste' =>(object)['adults'=>0,'kids_10_15'=>0,'kids_3_9'=>0,'kids_3'=>0]]; 
//    $luzko_pokoje= dum_cat_typ();
//    $ds_strava= map_cis('ds_strava','zkratka');
    $neubytovani= [];
    $hostu= 0;
    $vzorec_order= '';
    // c.ikona=1 pokud nebyl na akci
    ezer_connect($answer_db,true);
    $rp= pdo_qry("
      SELECT id_pobyt,c.ikona,prijmeni,/*datum_od,datum_do,DATEDIFF(datum_do,datum_od),*/YEAR(datum_od),
        GROUP_CONCAT(CONCAT(id_spolu,'~',prijmeni,'~',jmeno,'~',narozeni,
            '~',0,'~',IF(ds_od='0000-00-00',datum_od,ds_od),'~',IF(ds_do='0000-00-00',datum_do,ds_do),
            '~',TRIM(IF(s.ds_pokoj,s.ds_pokoj,p.pokoj)),
            '~',s.ds_vzorec,'~',s.ds_zdarma,'~',s.ds_dotace,'~',s.ds_pristylka,'~',s.ds_postylka,
            '~',s.ds_zvire,'~',0,'~',0,'~',0,'~',0,'~',0,'~',0) 
          ORDER BY IF(narozeni='0000-00-00','9999-99-99',narozeni) 
          SEPARATOR '~' ) AS cleni,d.state,d.board,IFNULL(x.zaplaceno,''),IFNULL(x.nx,''),IFNULL(x.platby,'')
      FROM osoba AS o 
        JOIN spolu AS s USING (id_osoba) 
        JOIN pobyt AS p USING (id_pobyt) 
        JOIN akce AS a ON id_akce=id_duakce 
        JOIN _cis AS c ON c.druh='ms_akce_funkce' AND c.data=p.funkce
        JOIN $setkani_db.tx_gnalberice_order AS d ON d.id_akce=id_duakce
        LEFT JOIN (
          SELECT id_pob,SUM(castka) AS zaplaceno,COUNT(*) AS nx,
            GROUP_CONCAT(CONCAT(castka,' (',DATE_FORMAT(datum,'%e.%c'),')') SEPARATOR ' + ') AS platby 
          FROM platba GROUP BY id_pob
        ) AS x ON id_pob=id_pobyt        
      WHERE $x->cond
      GROUP BY id_pobyt
      ORDER BY prijmeni
    ");
    $i_prijmeni= 1; $i_vek= 3; $i_noci= 4; $i_od= 5; $i_do= 6; $i_pokoj= 7; $i_vzorec= 8; 
    $i_zdarma= 9; $i_dotace= 10; $i_pristylka= 11; $i_postylka= 12; $i_zvire= 13; $i_celkem= 14; 
    $i_fix= 19; $i_delta= 20;
    while ($rp && (list(
        $idp,$nebyl,$prijmeni,/*$od,$do,$noci,*/$rok,$cleni,$state,$board,$zaplaceno,$nx,$platby)
        = pdo_fetch_array($rp))) {
      // projdeme členy a spočteme cenu
      $hostu++;
      $rok_ceniku= $rok;
      $celkem= 0;
      $pokoje= [];
      $c= explode('~',$cleni);
      for ($i= 0; $i<count($c); $i+= $i_delta) {
//        $ids= $c[$i];
        
        $od= $c[$i+$i_od];
        $do= $c[$i+$i_do];
        $noci= date_diff(date_create($od),date_create($do))->format('%a');
        $vek= roku_k($c[$i+$i_vek],$od); // věk ns začátku akce
        $c[$i+$i_vek]= $vek;
        $c[$i+$i_noci]= $noci;
        $od= $c[$i+$i_od]= sql_date1($od);
        $do= $c[$i+$i_do]= sql_date1($do);
        // doplníme počty do SUMA - jen pokud nebyla zrušena účast
        if ($nebyl==0) {

          $pokoj= $c[$i+$i_pokoj];
          if (!in_array($pokoj,$pokoje)) $pokoje[]= $pokoj; 
          if (!$pokoj) $neubytovani[]= $c[$i+$i_prijmeni];
          $ps= explode(',',$pokoj);
          foreach ($ps as $p) {
            $suma->pokoj[$p]+= 1/count($ps); 
            $pokoj= $p;
          }
          // osobonoci
          $suma->osobonoci+= $noci;
          // počty osob podle věku
          if ($vek<3) $suma->hoste->kids_3++;
          elseif ($vek<10) $suma->hoste->kids_3_9++;
          elseif ($vek<15) $suma->hoste->kids_10_15++;
          else $suma->hoste->adults++;
          // pokud je naplněna položka ds_vzorec tak se použije místo výpočtu
          $vzorec= $c[$i+$i_vzorec];
          $c[$i+$i_fix]= $vzorec ? 1 : 0;
          $zdarma= $c[$i+$i_zdarma];
          $dotace= $c[$i+$i_dotace];
          $suma->dotace+= $dotace ? 1 : 0;
          $vzorec= $c[$i+$i_vzorec]= $vzorec ?: dum_osoba_vzorec((object)
            ['vek'=>$vek,'pokoj'=>$pokoj,'state'=>$state,'board'=>$board,'noci'=>$noci,
              'ds_zdarma'=>$zdarma,'ds_dotace'=>$dotace,'ds_postylka'=>$c[$i+$i_postylka],
              'ds_pristylka'=>$c[$i+$i_pristylka],'ds_zvire'=>$c[$i+$i_zvire]],
            $rok);
          $cena= dum_vzorec_cena($vzorec,$rok_ceniku);
          $celkem+= $c[$i+$i_celkem]= $cena['celkem'];
          $c[$i+$i_celkem+1]= $cena['druh']['ubytovani']??0;
          $c[$i+$i_celkem+2]= $cena['druh']['strava']??0;
          $c[$i+$i_celkem+3]= $cena['druh']['poplatek_obci']??0;
          $c[$i+$i_celkem+4]= $cena['druh']['program']??0;
          $vzorec_order.= ",$vzorec";
//          display("$prijmeni: $vzorec");
        }
      }
      $cleni= implode('~',$c);
      $pokoje= implode(',',$pokoje);
      // doplníme pobyt s pokoji
      $z[$idp]->pokoje= $pokoje;
      $z[$idp]->cleni= $cleni;
      $z[$idp]->idp= $idp;
      $z[$idp]->nazev= $prijmeni;
      $z[$idp]->cena= $celkem;
      // doplníme platbu
      $z[$idp]->zaplaceno= $zaplaceno;
      $z[$idp]->nx= $nx;
      $z[$idp]->platby= $platby;
    }
    # předání pro browse
    $y->values= $z;
    $y->from= 0;
    $y->cursor= 0;
    $y->rows= count($z);
    $y->count= count($z);
    $y->quiet= $x->quiet;
    $y->ok= 1;
    // dopočet sumy přehled a účtování - pokud jsou hosté
    if ($hostu>0) {
      $suma->vzorec= dum_rozpis2vzorec(dum_vzorec2rozpis($vzorec_order));
      $cena= dum_vzorec_cena($suma->vzorec,$rok_ceniku);
//    debug($cena);
      $suma->celkem= $cena['celkem'];
      $suma->druh= $cena['druh'];
      $suma->abbr= $cena['abbr'];
      $suma->dph= $cena['dph'];
      ksort($suma->pokoj);
      $suma->pokoje= implode(',',array_keys($suma->pokoj));
      // zpráva o neubytovaných
      $suma->neubytovani= '';
      if (count($neubytovani)) {
        $suma->neubytovani= $neubytovani[0].(count($neubytovani)>1 ? ' ... a další' : '');
      }
    }
    $y->suma= $suma;
    array_unshift($y->values,null);
  }
//  debug($y->suma,"dum_browse_order/suma = ");
//  debug($y->values,"dum_browse_order/values = ");
  return $y;  
}
// -------------------------------------------------------------------------------- dum browse_pobyt
# BROWSE ASK - obsluha browse s optimize:ask + sumarizace realizace objednávky
# x->order= {a|d} polozka
# x->show=  {polozka:[formát,vzor/1,...],...} pro položky s neprázdným vzorem
#                                             kde formát=/ = # $ % @ * .
# x->cond= podmínka   - pokud obsahuje /*duplicity*/ přidá se sloupec _dup
#                       pokud obsahuje /*dokumenty*/ přidá se do sloupce _docs 'd'
#                       pokud obsahuje /*css*/ bude se barvit _nazev,cleni.jmeno,rodiny
# -- x->atr=  pole jmen počítaných atributů:  [_ucast]
# pokud je tisk=true jsou oddělovače řádků '≈' (oddělovač sloupců zůstává '~')
function dum_browse_pobyt($x) {
  global $answer_db, $setkani_db; 
//  debug($x,"dum_browse_pobyt>");
  $y= (object)array('ok'=>0);
  switch ($x->cmd) {
  case 'browse_load':  # -----------------------------------==> . browse_load
    global $y;          // y je zde globální kvůli možnosti trasovat SQL dotazy
  case 'suma':
    $z= [];
    // spotřeba 
    // pokoje: pokoj -> hostů
    // polozka: cena.
    $suma= (object)[
        'celkem'=>0,
        'druh'  =>[],
        'abbr'  =>[],
        'dph'   =>[],
        'pokoj' =>[],'pokoje'=>'',
        'neubytovani'=>'',  // případný text varování o neubytovaných
        'rozpis'=>[],
        'vzorec'=>'',
        'adresa'=>'',
        'rok'   =>0,
        'oddo'  =>'',
        'order' =>0,
        'pobyt' =>0,
        'hoste' =>(object)['adults'=>0,'kids_10_15'=>0,'kids_3_9'=>0,'kids_3'=>0]]; 
//    $luzko_pokoje= dum_cat_typ();
//    $ds_strava= map_cis('ds_strava','zkratka');
    $neubytovani= [];
    $vzorec_pobyt= '';
    // c.ikona=1 pokud nebyl na akci
    ezer_connect($answer_db,true);
    $rp= pdo_qry("
      SELECT id_pobyt,IFNULL(id_spolu,0),d.uid,c.ikona,IF(ds_od='0000-00-00',datum_od,ds_od),
        IF(ds_do='0000-00-00',datum_do,ds_do),
        YEAR(datum_od) AS rok,d.state,d.board,prijmeni,jmeno,narozeni,
        TRIM(IF(s.ds_pokoj,s.ds_pokoj,p.pokoj)) AS pokoj,s.ds_vzorec,ds_zdarma,ds_dotace,
        ds_pristylka,ds_postylka,ds_zvire,ulice,psc,obec
      FROM osoba AS o 
        JOIN spolu AS s USING (id_osoba) 
        JOIN pobyt AS p USING (id_pobyt) 
        JOIN akce AS a ON id_akce=id_duakce 
        JOIN _cis AS c ON c.druh='ms_akce_funkce' AND c.data=p.funkce
        JOIN $setkani_db.tx_gnalberice_order AS d ON d.id_akce=id_duakce
     WHERE $x->cond
      -- GROUP BY id_pobyt
      ORDER BY narozeni
    ");
    while ($rp && (list(
          $idp,$ids,$idd,$nebyl,$od,$do,$rok,$state,$board,$prijmeni,$jmeno,$narozeni,$pokoj,
          $vzorec,$zdarma,$dotace,$pristylka,$postylka,$zvire,$ulice,$psc,$obec
        )= pdo_fetch_array($rp))) {
      $rok_ceniku= $rok;
      // od nejstaršího vezmeme adresu a další údaje
      if (!$suma->adresa) {
        $suma->adresa= "$jmeno $prijmeni<br>$ulice<br>$psc $obec";
        $suma->order= $idd;
        $suma->pobyt= $idp;
        $suma->rok= $rok;
        $suma->oddo= datum_oddo($od,$do);
      }
      // projdeme členy a spočteme cenu
      $celkem= 0;
      $noci= date_diff(date_create($od),date_create($do))->format('%a');
      $vek= roku_k($narozeni,$od); // věk ns začátku akce
      // doplníme počty do SUMA - jen pokud nebyla zrušena účast
//      $rozpis= [];
      if ($nebyl==0) {
        $ps= explode(',',$pokoj);
        if (!$pokoj) $neubytovani[]= "$jmeno $prijmeni";
        foreach ($ps as $p) {
          $suma->pokoj[$p]+= 1/count($ps); 
          $pokoj= $p;
        }
        // osobonoci
        $suma->osobonoci+= $noci;
        $suma->dotace+= $dotace ? 1 : 0;
        // počty osob podle věku
        if ($vek<3) $suma->hoste->kids_3++;
        elseif ($vek<10) $suma->hoste->kids_3_9++;
        elseif ($vek<15) $suma->hoste->kids_10_15++;
        else $suma->hoste->adults++;
        // pokud je naplněna položka ds_vzorec tak se použije místo výpočtu
        $fix= $vzorec ? 1 : 0;
        
        $vzorec= $vzorec ?: dum_osoba_vzorec((object)
          ['vek'=>$vek,'pokoj'=>$pokoj,'state'=>$state,'board'=>$board,'noci'=>$noci,
           'ds_zdarma'=>$zdarma,'ds_dotace'=>$dotace,'ds_postylka'=>$postylka,
           'ds_pristylka'=>$pristylka,'ds_zvire'=>$zvire],
          $rok);
        $vzorec_pobyt.= ",$vzorec";
//        display("$jmeno: $vzorec");
//        $rz= dum_vzorec_cena($vzorec,$rok);
//        $suma->rozpis
//        debug($rz,"dum_vzorec_cena($vzorec,$rok)");
        
        
        // doplníme ceny
        if ($x->cmd!='suma') {
          $cena= dum_vzorec_cena($vzorec,$rok_ceniku);
          $celkem+= $z[$ids]['cena']= $cena['celkem'];
          $z[$ids]['ubyt']= $cena['druh']['ubytovani']??0;
          $z[$ids]['str']=  $cena['druh']['strava']??0;
          $z[$ids]['popl']= $cena['druh']['poplatek_obci']??0;
          $z[$ids]['prog']= $cena['druh']['program']??0;
        }
      } // nebyl==0
      // doplníme pobyt
      if ($x->cmd!='suma') {
        $z[$ids]['ids']= $ids;
        $z[$ids]['prijmeni']= $prijmeni;
        $z[$ids]['jmeno']= $jmeno;
        $z[$ids]['vek']= $vek;
        $z[$ids]['ds_pokoj']= $pokoj;
        $z[$ids]['noci']= $noci;
        $z[$ids]['ds_od']= sql_date1($od);
        $z[$ids]['ds_do']= sql_date1($do);
        $z[$ids]['ds_vzorec']= $vzorec;
        $z[$ids]['zamek_spolu']= $fix;
        $z[$ids]['ds_zdarma']= $zdarma;
        $z[$ids]['ds_dotace']= $dotace;
        $z[$ids]['ds_pristylka']= $pristylka;
        $z[$ids]['ds_postylka']= $postylka;
        $z[$ids]['ds_zvire']= $zvire;
      }
    }
    # předání pro browse
    if ($x->cmd!='suma') {
      $y->values= $z;
      $y->from= 0;
      $y->cursor= 0;
      $y->rows= count($z);
      $y->count= count($z);
      $y->quiet= $x->quiet;
      $y->ok= 1;
      array_unshift($y->values,null);
    }
    // dopočet sumy přehled a účtování
//    debug($suma->rozpis,"dum_browse_order/rozpis = ");
    $cena= dum_vzorec_cena($vzorec_pobyt,$rok_ceniku);
//    debug($cena,"*dum_vzorec_cena($vzorec_pobyt,$rok_ceniku)");
    $suma->celkem= $cena['celkem'];
    $suma->druh= $cena['druh'];
    $suma->abbr= $cena['abbr'];
    $suma->dph= $cena['dph'];
    ksort($suma->pokoj);
    $suma->pokoje= implode(',',array_keys($suma->pokoj));
    // zpráva o neubytovaných
    $suma->neubytovani= '';
    if (count($neubytovani)) {
      $suma->neubytovani= $neubytovani[0].(count($neubytovani)>1 ? ' ... a další' : '');
    }
  }
end:
  if ($x->cmd=='suma') {
    // doplníme platbu
    list($platba,$datum)= select("IFNULL(castka,0),IFNULL(datum,'')",
        "pobyt LEFT JOIN platba ON id_pob=id_pobyt",$x->cond);
    $suma->platba=(object)['castka'=>$platba,'datum'=>$datum];
    // a vzorec ze sumy rozpisu
    $suma->vzorec= dum_rozpis2vzorec(dum_vzorec2rozpis($vzorec_pobyt)); //dum_rozpis2vzorec($suma->rozpis);
    // a fakturu z tabulky
    $fakt= null;
    $rf= pdo_qry("
      SELECT IFNULL(id_faktura,0) AS id_faktura,typ,
        rok,num,f.vs,f.ss,duvod_zmeny,vzorec,zaloha,f.castka,zaloha,
        vystavena,p.datum AS zaplacena, vyrizuje 
      FROM faktura AS f
        LEFT JOIN join_platba AS pf USING (id_faktura) 
        LEFT JOIN platba AS p USING (id_platba) 
      WHERE $x->cond"); //,0,0,0,$answer_db);
    if ($rf) $fakt= pdo_fetch_object($rf);
    $suma->faktura= $fakt;
//    debug($suma,"dum_browse_pobyt/suma = ");
    return $suma;      
  }
  else { // browse
//    debug($y->values,">dum_browse_pobyt");
//    debug($y,">dum_browse_pobyt");
    $y->suma= $suma;
    return $y;  
  }
} // dum_browse_pobyt
# =====================================================================================> KNIHA HOSTU
# ---------------------------------------------------------------------------------- dum kniha_hostu
# zobrazí odkaz na osobu v evidenci
function dum_kniha_hostu($par,$export=0) {
  global $clmn_i, $clmn_if, $clmn_in, $clmn_iw, $row_class, $legenda;

//          $row_set('cena',$up->celkem);
//          $row[$clmn_i['cena']]= $up->celkem;
  $time_start= getmicrotime();
  // {err, html, ref: odkaz XLSX, t1: ms generování, t2: ms exportu
  $res= (object)['err'=>'','html'=>'','ref'=>'','t1'=>0,'t2'=>0]; 

  // tab: n -> sloupec -> value, kde sloupec=typ určuje formát řádku
  $tab= []; 
  // sloupec -> ' cc : r : c : název' kde cc je pořadí sloupce, r je řádek, c je pořadí třídy
  $legenda= [
    // k zaplaceno
    'ok'    => '21:2:1:',
    'ok_'   => '22:2:0:zaplaceno přesně:2',
    'víc'   => '21:3:2:',
    'víc_'  => '22:3:0:zaplaceno více:2',
    'pok'   => '21:4:4:',
    'pok_'  => '22:4:0:... zaplaceno více:2',
    'míň'   => '21:5:5:',
    'míň_'  => '22:5:0:zaplaceno méně:2',
    'nic'   => '21:6:6:',
    'nic_'  => '22:6:0:platba nenalezena:2',
    // k ubytování
    'ubyt'  => '13:4:4:',
    'uby_'  => '14:4:0:chybí pokoj ...:2',
  ];
  // sloupec -> ' cc : format : suma : název' 
  // kde cc je pořadí sloupce, 00 znamená vynechání
  //     suma=+ pro sečtení sloupce
  //     suma=* pro sečtení sloupce, pokud objednávka není placena celou fakturou
  $clmn= [ 
    'typ'   => '00', // určuje formát řádku 
    // popis
    'obj'   => '01:n:08: :objed-návka',
    'od'    => '02:d:10: :příjezd',
    'do'    => '03:d:10: :odjezd',
    'kod'   => '04:n:08: :kód YMCA',
    'pobyt' => '05:n:08: :id_pobyt',
    'spolu' => '06:n:08: :id_spolu',
    'nazev' => '07:t:20: :název',
    'druh'  => '08:t:12: :typ objed-návky',
    // ukazatele
    'onoci' => '09:n:08:+:osobo noci',     // osobo-noci
    'dot'   => '10:m:08:+:dotace',    // dotované pobyty (i částečně)
    'nakl'  => '11:k:12: :náklad',    // náklad organizace (tj. bez slev)
    // předpis
    'cena'  => '12:k:12:*:předpis',
    'ubyt'  => '13:k:12:+:ubytování',
    'stra'  => '14:k:12:+:strava',
    'popl'  => '15:k:12:+:poplatky',
    'prog'  => '16:k:12:+:program',
    'jine'  => '17:k:12:+:jiné služby',
    // platba
    #'fio'   => '20',
    'platba'=> '21:k:12:*:zaplaceno',
    'rozdil'=> '22:k:12:*:rozdíl',
    'nx'    => '30:n:05: :x',
    'kdy'   => '31:d:10: :dne',
    'fakt'  => '32:t:12: :faktura',
  ];
  $row_class= [
    1 => [" class='ezer_ys'",' bcolor=aaff88'],  
    2 => [" class='ezer_fa'",' bcolor=aaccff'],  
    3 => [" class='ezer_db'",' bcolor=aaffff'],  
    4 => [" class='warn'",   ' bcolor=ffffaa'],    // žlutá
    5 => [" class='err'",    ' bcolor=ffaa88'],    // červená
    6 => [" class='nic'",    ' bcolor=ffaaff'],    // nachová
  ];
  $clmn_i=  []; // fld -> i
  $clmn_s=  []; // fld -> suma
  $clmn_if= []; // i -> format
  $clmn_in= []; // i -> nazev
  foreach ($clmn as $fld=>$desc) {
    list($i,$f,$w,$s,$n)= explode(':',$desc);
    $clmn_i[$fld]= 0 + $i;
    $clmn_if[0+$i]= $f;
    $clmn_iw[0+$i]= $w;
    $clmn_in[0+$i]= $n;
    $clmn_s[$fld]= $s=='*' ? 2 : ($s=='+' ? 1 : 0);
  }
  $row= [];
  $sum= [];
  $fakturace_akce= 0;
//  debug($clmn_i,'clmn_i');
//  debug($clmn_if,'clmn_if');
//  debug($clmn_in,'clmn_in');
  $funkce= map_cis('ms_akce_funkce','zkratka');
  $row_set= function($fld,$val) use ($clmn_i,&$row,$fakturace_akce,$clmn_s,&$sum) {
    $row[$clmn_i[$fld]]= $val;
    if ($clmn_s[$fld]==1 || $clmn_s[$fld]==2 && !$fakturace_akce) {
      $v= is_array($val) ? $val[0] : $val;
      $sum[$fld]+= $v;
    }
//    debug($row,"ROW");                                                                    /*DEBUG*/
//    debug($sum,"SUM");                                                                    /*DEBUG*/
  };
//  $html= '';
  $n= $nf= 0;
  $rok= $par->rok;
  $AND_MESIC= $par->mes ? " AND MONTH(od)=$par->mes" : '';
  $AND_TEST= $par->obj ? " AND d.id_order=$par->obj" : '';
  // projdeme všechny objednávky
  $ro= pdo_qry("
    SELECT d.id_order,id_akce,IF(NOT ISNULL(id_faktura) AND f.deleted='',id_faktura,0) AS _idf,
      typ,IFNULL(g_kod,''),note,state,od,do
    FROM objednavka AS d
    LEFT JOIN faktura AS f ON f.id_order=d.id_order AND f.deleted=''
    LEFT JOIN join_akce USING (id_akce) 
    WHERE d.deleted=0 AND YEAR(od)=$rok $AND_MESIC $AND_TEST -- AND MONTH(od)<=MONTH(NOW()) 
      -- AND id_order IN (2394,2501,2463,2477,2434) -- YMCA, faktura, záloha, Bednář, Šlachtová
      -- AND id_order=2477 -- Bednář
    -- GROUP BY d.id_order    
    ORDER BY od
  ");
  while ($ro && (list($idd,$ida,$idf,$typ,$kod,$note,$state,$od,$do)= pdo_fetch_array($ro))) {
    $n++;
    $pobyty= 0;
    $row= [];
    $fakturace_akce= 0;
//    $html.= "<br>objednávka $idd, faktura $idf";
//    $udaje= dum_objednavka($idd);
    $row_set('obj',$idd);
    $row_set('od',$od);
    $row_set('do',$do);
//    $row_set('nazev',$udaje->fld->nazev); // dum_objednavka_nazev($idd);
    $row_set('nazev',dum_objednavka_nazev($idd,'kniha'));
    if ($state==3) {
      $pobyty= 1;
      $row[0]= 1;
      $row_set('kod',$kod);
      $row_set('druh',"akce YMCA");
    }
    elseif ($idf) {
      $row[0]= 2;
      $fakturace_akce= 1;
      $row_set('druh',"fakturace");
    }
    else {
      $row[0]= 3;
      $pobyty= 1;
      $row_set('druh',"pobyt");
    }
    dum_kniha_hostu_fakturace($idf,$row);
    $isum= count($tab); 
    $sum= [];
    $tab[]= $row;
    $rows_spolu= [];
    if (1) { // ($pobyty || $par->spolu) {
      $idp_old= 0;
      $rp= pdo_qry(" 
        SELECT p.id_pobyt,p.funkce,IFNULL(f.nazev,'')
        FROM pobyt AS p
        LEFT JOIN platba AS k ON id_pob=p.id_pobyt
        LEFT JOIN faktura AS f ON f.id_pobyt=p.id_pobyt AND f.deleted=''
        LEFT JOIN spolu AS s ON s.id_pobyt=p.id_pobyt
        LEFT JOIN osoba AS o USING (id_osoba)
        WHERE id_akce=$ida 
        --  AND p.id_pobyt=64680
        GROUP BY id_pobyt
        ORDER BY prijmeni
      ");
      while ($rp && (list($idp,$fce,$faktura)= pdo_fetch_array($rp))) {
        $rk= pdo_qry(" 
          SELECT IFNULL(SUM( k.castka),''),COUNT(k.id_platba),
            IF(COUNT(id_platba)>1,IF(COUNT(id_platba)>1,GROUP_CONCAT(DISTINCT CONCAT(DAY(k.datum),'.',
              MONTH(k.datum)) SEPARATOR ', '),IFNULL(k.datum,'')),IFNULL(k.datum,'') )
          FROM pobyt AS p
          LEFT JOIN platba AS k ON id_pob=p.id_pobyt
          WHERE p.id_pobyt=$idp
          GROUP BY id_pobyt
        ");
        list($castka,$nx,$datum)= pdo_fetch_array($rk);
        if ($idp!=$idp_old) {
          // doplň členy k předešlému pobytu
          if (count($rows_spolu)) { 
            $tab= array_merge($tab,$rows_spolu); 
            $rows_spolu= [];             
          }
          $up= dum_browse_pobyt((object)['cmd'=>'suma','cond'=>"id_pobyt=$idp"]);
//          debug($up,"dum_browse_pobyt/suma ... ida=$ida, idp=$idp");                     /*DEBUG*/
          if ($up->osobonoci==0) {
            display("-------------------------- pobyt $idp NEMÁ žádné spolu členy");
            fce_warning("objednávka $idd: pobyt $idp má (pobyt bez členů) VYŘADIT !");
            continue;
          }
          $row= [];
          $row_set('druh',$funkce[$fce]);
          $row_set('pobyt',$idp);
          $row_set('fakt',$faktura);
          list($ids,$jp)= explode(':',dum_pobyt_nazev($idp,'kniha'));
          $row_set('spolu',$ids);        
          $row_set('onoci',$up->osobonoci);        
          $row_set('dot',$up->dotace);        
          $row_set('nazev',$jp);        
          $predpis= $up->celkem;
          $row_set('cena',$fakturace_akce ? 0 : $predpis);
          foreach(explode(',','ubyt,stra,prog,popl,prog,jine') as $fld) {
            $val= $up->abbr[$fld];
            if ($fld=='ubyt') $ne_ubyt= !$val;
            $row_set($fld,$fld=='ubyt' && $ne_ubyt && $up->celkem ? [$val,4] : $val);
          }
          $platba= dum_kniha_castka($up->celkem,$castka,$ne_ubyt);
          $row_set('platba',$fakturace_akce ? 0 : $platba);
          $row_set('rozdil',$fakturace_akce ? 0 : (is_array($platba) ? $platba[0] : $platba) - $predpis);
          $row_set('nx',$nx>1 ? "{$nx}x" : ''); 
          $row_set('kdy',$datum);
          $tab[]= $row;
          // zapamatuj si další členy pobytu
          if ($par->spolu) {
            $rs= pdo_qry("SELECT id_spolu,jmeno,prijmeni FROM osoba JOIN spolu USING (id_osoba)
              WHERE id_pobyt=$idp AND id_spolu!=$ids ORDER BY prijmeni,jmeno");
            while ($rs && (list($ids2,$jmeno,$prijmeni)= pdo_fetch_array($rs))) {
              $row= [];
              $row_set('spolu',$ids2);        
              $row_set('nazev',"$prijmeni $jmeno");
              $rows_spolu[]= $row;
            }
          }
        }
        else { // tyto řádky obsahují jen platby
          $row= [];
          $row_set('platba',$castka);
          $row_set('kdy',$datum);
          $tab[]= $row;
        }
        $idp_old= $idp;
      }
      // doplň členy k poslednímu pobytu
      if (count($rows_spolu)) { 
        $tab= array_merge($tab,$rows_spolu); 
        $rows_spolu= [];             
      }
    }
    // doplň spočítané sumy
//    debug($sum,"SUM end - $isum");                                                       /*DEBUG*/
    foreach ($sum as $fld=>$val) {
      if ($clmn_s[$fld]==1 || $clmn_s[$fld]==2 && !$fakturace_akce)
        $tab[$isum][$clmn_i[$fld]]= $val;
    }
    $nf++;
//    break;
  }
//  debug($tab,'tab');                                                                     /*DEBUG*/
  $res->t1= round(getmicrotime() - $time_start,4);
  $time_start= getmicrotime();
  $kniha= dum_kniha_hostu_tab2html($tab,$export);
  $res->html= $kniha->html;
  $res->ref= $kniha->ref;
  $res->err.= $kniha->err;
  $res->t2= round(getmicrotime() - $time_start,4);
  return $res;
}
function dum_kniha_hostu_fakturace($idf,&$row) {
  global $clmn_i;
  $rk= pdo_qry("
    SELECT IF(typ in (1,2),zaloha,f.castka) AS cena,ubyt,stra,popl,jine,
      p.castka AS platba,p.castka-IF(typ in (1,2),zaloha,f.castka) AS rozdil,
      datum AS kdy,f.nazev AS fakt
    FROM faktura AS f  
	LEFT JOIN join_platba AS pf USING (id_faktura) 
	LEFT JOIN platba AS p USING (id_platba) 
	WHERE deleted='' AND id_faktura=$idf AND f.id_pobyt=0
	ORDER BY f.num DESC,f.id_faktura
  ");
  if ($rk) {
    $f= pdo_fetch_assoc($rk);
    foreach ($f as $fld=>$val) {
      if ($fld=='platba') $val= dum_kniha_castka($f['cena'],$val);
      $i= $clmn_i[$fld];
      $row[$i]= $val;
    }
  }
}
// obarvení částky
function dum_kniha_castka($castka,$platba,$ne_ubyt=0) {
  $platba= !$platba ? ($castka ? [$platba,6] : $platba) : (         
      $castka==$platba ? [$platba,1] : (              // akorát
      $castka<$platba && $castka ? ( 
       $ne_ubyt ? [$platba,4] : [$platba,2] 
      ): [$platba,5]));  // dar | nedoplatek
  return $platba;
}
// konverze tabulky na html
// pokud je zadáno excel udělá XLS
function dum_kniha_hostu_tab2html($tab,$excel) {
  global $clmn_in, $clmn_if, $clmn_iw, $row_class, $legenda;
  $res= (object)['html'=>'','ref'=>'','err'=>''];
  $clmn= $iclmn= []; // nn -> A 
  if ($excel) { // zahájení
    $xls= "|open kniha\nsheet kniha;;L;page\n";
    $r1= 1;
    $c= 1;
  }
  $ic= 0;
  foreach (array_keys($clmn_in) as $i) {
    if ($i==0) continue;
      $iclmn[$i]= $ic++;
      $clmn[$i]= Excel5_n2col($c++);
  } 
  $html= "<table class='stat' style='color:black'>";
  // matice popisů
  $leg= []; // [r][c]= class:text 
  $wleg= []; // [r][c]= colspan 
//  debug($legenda,"legenda");                                                              /*DEBUG*/
//  debug($iclmn,"iclmn");                                                                /*DEBUG*/
  foreach ($legenda as $desc) {
    list($i,$r,$iclass,$popis,$span)= explode(':',$desc);
    $r1= max($r1,$r);
    $class= $row_class[$iclass][0];
    $colspan= $span ? " colspan='".($span+1)."'" : '';
    $noborder= $iclass ? '' : " style='border:none'";
    $leg[$r][$iclmn[$i]]= "<td$class$colspan$noborder>$popis</td>";
    $wleg[$r][$iclmn[$i]]= $span ?? 0;
    if ($excel) { // záhlaví sloupců
      $A= $clmn[$i];
      $attr= $row_class[$iclass][1] ? "::border=t {$row_class[$iclass][1]}" : '';
      $xls.= "\n|$A$r $popis$attr";
    }
  }
//  debug($leg,"legenda");                                                                  /*DEBUG*/
  if ($excel) { // oddělení legendy
    $xls.= "\n\n";
    $r1+= 2;
    $r= $r1; $c= 0;
  }
  // html legenda
  for ($_r= 0; $_r<=$r1; $_r++) {
    $html.= "<tr>";
    for ($_c= 0; $_c<count($iclmn); $_c++) {
      $html.= $leg[$_r][$_c] ?? "<td style='border:none'></td>";
      $_c+= $wleg[$_r][$_c] ?? 0;
    }
    $html.= "</tr>";
  }
  $html.= "<tr><td style='border:none'>&nbsp;</td></tr>";
  // řádek jmen sloupců
  $html.= "<tr>";
  foreach (array_keys($clmn_in) as $i) {
    if ($i==0) continue;
    $html.= "<th>{$clmn_in[$i]}</th>";
    if ($excel) { // záhlaví sloupců
      $A= Excel5_n2col($c++);
      $clmn[$i]= $A; 
      $xls.= "|$A$r {$clmn_in[$i]}|columns $A={$clmn_iw[$i]}";
    }
  } 
  if ($excel) { // obarvení záhlaví sloupců
    $xls.= "\n\n|A$r1:$A$r1 bold bcolor=aaaaff";
  }
  $html.= "</tr>";
  foreach ($tab as $row) {
    $html.= "<tr>";
    if ($excel) { // obarvení záhlaví sloupců
      $r++; $c= 0;
      $xls.= "\n\n";
    }
    foreach (array_keys($clmn_in) as $i) {
      if ($i==0) {
        $class= $row_class[$row[0]][0] ?? ''; // default class
        $xls_color= $row_class[$row[0]][1];
        continue;
      }
      $val= $row[$i] ?? '';
      $cls= $class;
      $xls_clr= $xls_color;
      if (is_array($val)) {  // [hodnota,class]
        $cls= $row_class[$val[1]][0]; // special class
        $xls_clr= $row_class[$val[1]][1];
        $val= $val[0];
      }
      $align= $style= '';
      $xls_fmt= '';
      switch ($clmn_if[$i]) {
        case 'd': 
          // jen SQL datum jako datum - jinak text
          if (strstr($val,'-')!==false) {
            $align= " align='right'";
            $val= sql_date1($val);
            $xls_fmt= '::date right';
          }
          break;
        case 'm': 
          $val= $val ?: '';
        case 'n': 
          $align= " align='right'";
          $xls_fmt= '::right';
          break;
        case 'k': 
          $align= " align='right'";
          $style= $val<0 ? " style='color:red'" : '';
          $xls_fmt= '::kc right';
          break;
        case 't': 
          $xls_fmt= '::left';
          break;
      }
      $html.= "<td$align$cls$style>$val</td>";
      if ($excel) { // obarvení záhlaví sloupců
        $A= Excel5_n2col($c++);
        $xls.= "\n|$A$r $val$xls_fmt$xls_clr";
      }
    } 
    $html.= "</tr>";
  }  
  $html.= "</table>";
  $res->html= $html; 
  // export Excelu
  if ($excel) {
    $r2= $r1+1;
    $xls.= "\n\n|A$r1:$A$r border=+h\n|A$r1:$A$r1 border=t|A$r2:$A$r border=t\n|close";
    file_put_contents("docs/kniha.txt",$xls);
    require_once "ezer3.2/server/vendor/autoload.php";
    $res->err= Excel2007($xls,1);
    if ( !$res->err ) 
      $res->ref= "<a href='docs/kniha.xlsx' target='xls'>zde</a>.";
  }
//  debug([$res->err,$res->ref]);                                                           /*DEBUG*/
  return $res;
}
# ===========================================================================================> RUZNE
# --------------------------------------------------------------------------------- dum spolu_adresa
# vrátí osobní resp. rodinnou adresu
function dum_spolu_adresa($ids) {
  $p= pdo_fetch_object(pdo_qry("
     SELECT prijmeni, jmeno, 
       IF(adresa,o.ulice,r.ulice) AS ulice,
       IF(adresa,o.psc,r.psc) AS psc, IF(adresa,o.obec,r.obec) AS obec
     FROM spolu AS s
     JOIN osoba AS o USING (id_osoba)
     LEFT JOIN tvori AS t USING (id_osoba)
     LEFT JOIN rodina AS r USING (id_rodina)
     WHERE id_spolu='$ids'
     ORDER BY role"));
  $adresa= "$p->jmeno $p->prijmeni<br>$p->ulice<br>$p->psc $p->obec";
  return $adresa;
}
# -------------------------------------------------------------------------------- db2 osoba_kontakt
# vrátí osobní údaje případně evidované jako rodinné jako objekt 
# {jmeno,prijmeni,ulice,psc,obec,stat,telefon,email,adresa}
function db2_osoba_kontakt($ido) {
  $p= pdo_fetch_object(pdo_qry("
     SELECT prijmeni, jmeno, 
       IF(adresa,o.stat,r.stat) AS stat, IF(adresa,o.ulice,r.ulice) AS ulice,
       IF(adresa,o.psc,r.psc) AS psc, IF(adresa,o.obec,r.obec) AS obec,
       IF(kontakt,o.telefon,r.telefony) AS telefon,
       IF(kontakt,o.email,r.emaily) AS email
     FROM osoba AS o 
     LEFT JOIN tvori AS t USING (id_osoba)
     LEFT JOIN rodina AS r USING (id_rodina)
     WHERE id_osoba='$ido'
     ORDER BY role LIMIT 1"));
  $p->adresa= "$p->jmeno $p->prijmeni<br>$p->ulice<br>$p->psc $p->obec";
  return $p;
}
# -------------------------------------------------------------------------------- dum refresh_pokoj
# obnoví pokoj v pobyt podle ds_pokoj ve spolu
function dum_refresh_pokoj($idp) {
  $pokoje= select('GROUP_CONCAT(DISTINCT ds_pokoj ORDER BY ds_pokoj)','spolu',"id_pobyt=$idp");
  if ($pokoje!=select('pokoj','pobyt',"id_pobyt=$idp"))
    query_track("UPDATE pobyt SET pokoj='$pokoje' WHERE id_pobyt=$idp");  
}
# ------------------------------------------------------------------------------ ds2 ukaz_objednavku
# zobrazí odkaz na osobu v evidenci
function ds2_ukaz_objednavku($idx,$barva='',$title='') {
  $style= $barva ? "style='color:$barva'" : '';
  $title= $title ? "title='$title'" : '';
  return "<b><a $style $title href='ezer://ds.dum2.seek_order/$idx'>$idx</a></b>";
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
# ------------------------------------------------------------------------------------------ ds2 vek
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
    $ok= query("UPDATE spolu SET id_pobyt=$idp_goal WHERE id_pobyt=$idp");
    if ($ok) query("DELETE FROM pobyt WHERE id_pobyt=$idp");
  }
  return $ret;
}
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
/** =======================================================================================> BANKY **/
#
# ------------------------------------------------------------------------------- ds2 fio_filtr_akce
# vytvoření filtru pro výběr plateb podle SS, SS2
# a vrácení nalezené platby k id_platba
function ds2_fio_filtr_akce($id_pobyt) {
  $days_plus= 10; $days_minus= 30;
  list($kod,$ida,$od,$do)= select('g_kod,id_akce,datum_od,datum_do',
      "pobyt JOIN akce ON id_akce=id_duakce LEFT JOIN join_akce USING (id_akce)",
      "id_pobyt=$id_pobyt");
  // zjistíme všechny pobyty této akce
  $idps= select1('GROUP_CONCAT(id_pobyt)','pobyt',"id_akce=$ida");
  $idos= select('GROUP_CONCAT(id_osoba)','spolu JOIN pobyt USING (id_pobyt)',"id_pobyt='$id_pobyt'");
  $id_platba= select('id_platba','platba',"id_pob=$id_pobyt");
//  $OR_idos= $idos ? "OR id_oso IN ($idos)" : '';
//  $OR_kod= $kod ? "OR ss='$kod' OR ss2='$kod'" : '';
  list($sel_idos,$first)= select(
      "GROUP_CONCAT(CONCAT(prijmeni,' ',jmeno,':',id_osoba) ORDER BY narozeni),id_osoba",
      'osoba',"id_osoba IN ($idos)");
  $patt= mb_strtolower(mb_substr($sel_idos,0,3));
  $seek= "id_oso IN ($idos) OR LOWER(nazev) RLIKE '(^| |\\\\.)$patt'";
  $ret= (object)[ 'kod'=>$kod,'id_platba'=>$id_platba?:0, 'idos'=>$idos,
      'sel_idos'=>$sel_idos,'sel_first'=>$first, 'seek'=>$seek,
      'filtr'=>"1 "
//        . "AND (1 $OR_kod $OR_idos) "
        . " AND (id_pob=0 OR id_pob IN ($idps))"
        . " AND datum BETWEEN DATE_ADD('$od',INTERVAL - $days_minus DAY) "
          . "AND DATE_ADD('$do',INTERVAL $days_plus DAY)"];
//  debug($ret);
  return $ret;
}
# ------------------------------------------------------------------------------------ ds2 show_curr
# čitelné zobrazení objektu získaného funkcí akce2.curr
function ds2_show_curr($c) {
  $evi= $ucast= $dum= '';
  $a_jmeno= $e_jmeno= $a_akce= $d_jmeno= '';
  // akce
  if (($ida= $c->lst->akce)) {
    list($a_akce,$kod)= select("CONCAT(nazev,' ',YEAR(datum_od)),IFNULL(g_kod,'')",
        'akce LEFT JOIN join_akce ON id_akce=id_duakce',"id_duakce=$ida");
    $ucast.= "akce <span title='ID akce=$ida'>$ida/$kod</span>";
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
  // Dům setkání - objednávvky
//  debug($c,'ds2_show_curr');
  if (($idx= $c->dum->order)) {
    list($jmeno,$prijmeni,$od,$do)= select('firstname,name,fromday,untilday',
        ' tx_gnalberice_order',"uid=$idx",'setkani');
    $dum.= " obj. $idx";
    $mmyyyy= date('mY',$od);
    $od= date('j.n.',$od);
    $do= date('j.n.Y',$do);
    $celkem= $c->dum->celkem ? number_format($c->dum->celkem,2,'.',' ') : '?';
    $d_jmeno.= wu("$jmeno $prijmeni, $od-$do, $celkem").' Kč';
//    display("$dum|$d_jmeno");
  }
  return (object)['evi'=>$evi,'evi_text'=>$e_jmeno,
      'ucast'=>$ucast,'ucast_text'=>"$a_akce, $a_jmeno",
      'dum'=>$dum,'dum_text'=>"$d_jmeno",'dum_mmyyyy'=>$mmyyyy];
}
# ------------------------------------------------------------------------------------------ ds2 fio
# zapsání informace do platby
#    pobyt - c=id_pobyt
function ds2_corr_platba($id_platba,$typ,$on,$c=null) {
  switch ($typ) {
    case 'pobyt':
      // provede spojení platby 
      $what= $on ? "stav=7,id_pob=$c" : "stav=6,id_pob=0";
      query_track("UPDATE platba SET $what WHERE id_platba=$id_platba");
      break;
    case 'osoba':
      // provede spojení účtu s majitelem
      $what= $on ? "id_oso=$c" : "id_oso=0";
      query_track("UPDATE platba SET $what WHERE id_platba=$id_platba");
      break;
    case 'dar':
      query("UPDATE platba SET stav=11
        WHERE id_platba=$id_platba AND stav IN (5,10)");
      break;
    case 'auto':
      query("UPDATE platba SET stav=stav+1
        WHERE id_platba=$id_platba AND stav IN (1,6,8,10)");
      break;
    case 'akce':
      query("UPDATE platba SET id_oso={$c->ucast->osoba},id_pob={$c->ucast->pobyt}, stav=7
        WHERE id_platba=$id_platba");
      break;
    case 'evi':
      query("UPDATE platba SET id_oso={$c->evi->osoba}, stav=7
        WHERE id_platba=$id_platba");
      break;
    case 'order':
      query("UPDATE platba SET id_ord={$c->dum->order}, stav=9
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
//        display($line);
        if (!strncmp($line,'ID pohybu',9)) {
          $decode= 1;
          continue;
        }
        if ($decode) {
          $d= str_getcsv($line,';');
//          debug($d);
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
            $vs= $d[9];
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
      $AND= $cmd->all ? '' : "AND stav NOT IN (1,7,9,11)";
      $n= query("UPDATE platba SET id_oso=0,id_pob=0,id_ord=0,stav=IF(castka>0,5,1), ss2='' 
        WHERE ucet=$ucet AND datum BETWEEN '$od' AND '$do' $AND  ");
      $y->html= "Vymazáno $n přiřazení letošních plateb";
      break; // vymazání přiřazení letošních plateb
//    case 'delete':   // ------------------------------------------- vymazání letošních plateb
//      $n= query("DELETE FROM platba WHERE YEAR(datum)=YEAR(NOW())");
//      $y->html= "Vymazáno $n letošních plateb";
//      break; // vymazání přiřazení letošních plateb
    case 'join-ds': // ----------------------------------------------------- přiřazení plateb DS
    case 'join-ys': // ----------------------------------------------------- přiřazení plateb YS
      $na= $nd= $nu= $nv= $nf= 0;
      $omezeni= $cmd->platba
          ? "id_platba=$cmd->platba" 
          : "datum BETWEEN '$cmd->od' AND '$cmd->do'";
      $back= $cmd->back ?: 0; // návrat k odhadu =  ignoruje id_oso, id_pob, id_ord
      if ($back && $cmd->platba) {
        query("UPDATE platba SET id_oso=0,id_pob=0,id_ord=0,stav=IF(castka>0,5,1) 
          WHERE id_platba=$cmd->platba");
      }
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
      // platby za akce YS + DS
      $rp= pdo_qry("
        SELECT id_platba,id_osoba,id_pobyt,id_oso
        FROM platba AS p
        JOIN join_akce AS ja ON ja.g_kod=IF(p.ss2,p.ss2,p.ss) AND YEAR(p.datum)=g_rok
        JOIN akce AS a ON ja.id_akce=id_duakce
        JOIN pobyt AS po ON po.id_akce=id_duakce
        JOIN spolu AS s USING (id_pobyt) -- ON s.id_pobyt=po.id_pobyt
        JOIN osoba AS o USING (id_osoba) -- ON o.id_osoba=s.id_osoba
        WHERE id_pob=0 AND LENGTH(IF(p.ss2,p.ss2,p.ss))=3 AND $omezeni AND
          (id_oso=id_osoba
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
      // platby za faktury vydané DS
      if ($cmd->fce=='join-ds') {
        $rf= pdo_qry("
          SELECT /* ------------------------------------------------ */
            id_platba,id_faktura,id_order
          FROM platba AS p 
          JOIN faktura AS f USING (ss,vs) 
          LEFT JOIN join_platba AS j USING (id_platba,id_faktura)
          WHERE ucet=2 AND ISNULL(j.id_faktura) AND p.castka IN (f.castka,f.zaloha)
            AND f.deleted='' AND $omezeni AND vystavena BETWEEN '$cmd->od' AND '$cmd->do'");
        while ($rf && (list($idp,$idf,$ido,$yet)= pdo_fetch_array($rf))) {
          query("INSERT INTO join_platba (id_platba,id_faktura) VALUE ($idp,$idf)");
          query("UPDATE platba SET id_ord=$ido WHERE id_platba=$idp");
          $nf++;
        }
      }
      // dary
      $rp= pdo_qry("
        SELECT id_oso,id_platba,vs,IF(p.ss2,p.ss2,p.ss),protiucet,nazev,zprava,
          IF(p.ss2,p.ss2,p.ss) IN (22,222) OR zprava RLIKE 'dar' AS _dar
        FROM platba AS p WHERE $omezeni AND stav IN (5)
          -- AND id_platba=26446381639 ");
      while ($rp && (list($idoso,$id_platba,$vs,$ss,$ucet,$nazev,$zprava,$dar)= pdo_fetch_array($rp))) {
//        // podle dřívější platby
//        $ido= select('id_oso','platba',"protiucet='$ucet' AND id_oso!=0 ");
//        display("$nazev,$ss,'$ido'");
//        if ($ido==false) {
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
//        }
        if ($ido && !$idoso) {
          query("UPDATE platba SET id_oso=$ido WHERE id_platba=$id_platba");
          $nv++;
        }
        if ($ido && $dar) {
          query("UPDATE platba SET stav=10 WHERE id_platba=$id_platba");
          $nd++;
        }
      }
      $y->html= "Rozpoznáno $na plateb za akce, $nd darů, $nu osob podle účtu, 
          $nv podle VS a jména, $nf podle faktury";
      break; // přiřazení plateb
  }
end:  
  return $y;
}
// ========================================================================= doplnění osoba + rodina
// 
function check_access($tab,$id,$access_akce) { 
  display("check_access($tab,$id,$access_akce)");
}
// ========================================================================================> LIBRARY
# -------------------------------------------------------------------------------------- query track
# provede některá SQL včetně zápisu do _track
#   INSERT INTO tab (f1,f2,...) VALUES (v1,v2,...) 
#   UPDATE tab SET f1=v1, f2=v2, ... WHERE id_tab=v0
# kde vi jsou jednoduché hodnoty: číslo nebo string uzavřený v apostorfech 
function query_track($qry) {
  // rozklad výrazu: 1:table, 2:field list, 3:values list
  $res= 0;
  $m= null;
  $ok= preg_match('/(INSERT)\s+INTO\s+([\w\.]+)\s+\(([,\s\w]+)\)\s+VALUE(?:S|)\s+\(((?:.|\s)+)\)$/',$qry,$m)
    || preg_match('/(UPDATE)\s+([\w\.]+)\s+SET\s+(.*)\s+WHERE\s+([\w]+)\s*=\s*(.*)\s*/m',$qry,$m);
//  debug($m);
  if ($ok && $m[1]=='INSERT') {
    $tab= $m[2];
    $fld= explode_csv($m[3]); 
    $val= explode_csv($m[4]); 
    $chng= [];
    for ($i= 0; $i<count($fld); $i++) {
      $v= trim($val[$i],"'");
      $chng[]= (object)['fld'=>$fld[$i],'op'=>'i','val'=>$v];
    }
    $res= ezer_qry("INSERT",$tab,0,$chng);
  }
  elseif ($ok && $m[1]=='UPDATE') {
//    debug($m);
    $tab= $m[2];
    $sets= explode_csv($m[3]); 
    $key_id= $m[4];
    $key_val= $m[5];
    // kontrola podmínky
    $ok= ($tab=='akce' && $key_id=='id_duakce') || $key_id=="id_$tab";
    if ($ok) {
      $chng= [];
      foreach ($sets as $set) {
        list($fld,$val)= explode('=',$set,2);
        $v= trim($val,"'");
        $chng[]= (object)['fld'=>$fld,'op'=>'u','val'=>$v];
      }
      $res= ezer_qry("UPDATE",$tab,$key_val,$chng,$key_id);
    }
  }
  if (!$ok) {
    fce_error("funkce query-track nemá předepsaný tvar argumentu, má $qry");
  }
  return $res;
}
# ---------------------------------------------------------------------------------------- clone row
function clone_row($tab,$id,$idname='') {
  $idname= $idname ?: "id_$tab";  
  $ro= pdo_qry("SELECT * FROM $tab WHERE $idname=$id");
  while ( $ro && $o= pdo_fetch_object($ro) ) {
    $del= '';
    foreach ($o as $i=>$v) {
      if ($i==$idname) continue;
      $v= pdo_real_escape_string($v);
      $set.= "$del$i='$v'"; $del= ' ,';
    }
    query("INSERT INTO $tab SET $set");
    $copy= pdo_insert_id();
    return $copy;
  }
}
# ---------------------------------------------------------------------------------------- clone row
// split CSV s ohledem na závorky a apostrofy
function explode_csv($str, $separator=",", $leftbracket="(", $rightbracket=")", $quote="'", $ignore_escaped_quotes=true ) {
  $buffer = '';
  $stack = array();
  $depth = 0;
  $char= '';
  $betweenquotes = false;
  $len = strlen($str);
  for ($i=0; $i<$len; $i++) {
    $previouschar = $char;
    $char = $str[$i];
    switch ($char) {
      case $separator:
        if (!$betweenquotes) {
          if (!$depth) {
            if ($buffer !== '') {
              $stack[] = $buffer;
              $buffer = '';
            }
            continue 2;
          }
        }
        break;
      case $quote:
        if ($ignore_escaped_quotes) {
          if ($previouschar!="\\") {
            $betweenquotes = !$betweenquotes;
          }
        } else {
          $betweenquotes = !$betweenquotes;
        }
        break;
      case $leftbracket:
        if (!$betweenquotes) {
          $depth++;
        }
        break;
      case $rightbracket:
        if (!$betweenquotes) {
          if ($depth) {
            $depth--;
          } else {
            $stack[] = $buffer.$char;
            $buffer = '';
            continue 2;
          }
        }
        break;
      }
      $buffer .= $char;
  }
  if ($buffer !== '') {
    $stack[] = $buffer;
  }
  return $stack;
}
