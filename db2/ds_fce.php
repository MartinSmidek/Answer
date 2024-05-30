<?php # (c) 2009-2015 Martin Smidek <martin@smidek.eu>
/** ==================================================================================> TRANSFORMACE **/
# transformace DS do Answer
define('org_ds',64);
# ------------------------------------------------------------------------------------------- ds2 kc
function TEST() {
  return strcmp('2024-03-14','2024-04-00');
}
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
  'objednavkaR' => [',R,4.5,158,90,40,30',"
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
# ------------------------------------------------------------------------------------------- dum kc
function dum_kc($c) {
  return number_format($c,2,'.',' ').' Kč';
}
# ----------------------------------------------------------------------------- dum objednavka_nazev
# vrátí tisknutelný název pobytu
function dum_objednavka_nazev($ido) {
  $prijmeni= select('prijmeni',"objednavka","id_order=$ido");
  return "$ido - $prijmeni";
}
# ---------------------------------------------------------------------------------- dum pobyt_nazev
# vrátí tisknutelný název pobytu
function dum_pobyt_nazev($idp) {
  list($idp,$prijmeni)= pdo_fetch_array(pdo_qry("
    SELECT id_pobyt,prijmeni
    FROM spolu 
      LEFT JOIN osoba USING (id_osoba)
    WHERE id_pobyt=$idp
    ORDER BY narozeni
    LIMIT 1"));
  return $idp ? "$idp - $prijmeni" : '';
}
# --------------------------------------------------------------------------------- dum faktura_info
# par.typ = konečná | záloha
function dum_faktura_info($idf) {
  list($ido,$idp,$typ)= select('id_order,id_pobyt,typ','faktura',"id_faktura=$idf");
  $typs= $typ==2 ? 'konečná faktura' : ($typ==1 ? 'zálohová faktura' : '???');
  $popis= 'Objednávka '.dum_objednavka_nazev($ido).($idp?'<br>pobyt '.dum_pobyt_nazev($idp):'')." - $typs";
  return (object)['popis'=>$popis];
}
# --------------------------------------------------------------------------------- dum faktura_save
# par.typ = konečná | záloha
function dum_faktura_save($parm) {
  $x= array_merge((array)$parm); $x['html']= "...";
  debug($x,"dum_faktura_save(...)"); //goto end;
  // uložení do tabulky
  $p= $parm->parm;
  $order= $p->id_order ?? '';
  $pobyt= $p->id_pobyt ?? '';
  $jso= $html= '';
  $jso= pdo_real_escape_string($parm->parm_json); 
  $htm= pdo_real_escape_string($parm->html); 
  $ok= query("INSERT INTO faktura (rok,num,typ,strucna,vs,ss,id_order,id_pobyt,zaloha,castka,"
      . "vzorec,vyrizuje,vystavena,parm_json,html) VALUES "
      . "($p->rok,$p->num,'$p->typ','$p->strucna','$p->vs','$p->ss','$order','$pobyt','$p->zaloha','$p->celkem',"
      . "'$p->vzorec','$p->vyrizuje','$p->vystavena','$jso',\"$htm\")");
end:
  return $ok;
}
# -------------------------------------------------------------------------------------- dum faktura
# par.typ = konečná | záloha
function dum_faktura($par) {  debug($par,'dum_faktura');
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
  // společné údaje
  $vals['{obdobi}']= $oddo;
  $vals['{ic_dic}']= ($ic ? "IČ: $ic" : '').($dic ? "    DIČ: $dic" : '');
  $vals['{adresa}']= $adresa;
  $vals['{datum1}']= date('j. n. Y'); 
  $vals['{datum2}']= date('j. n. Y',strtotime("+14 days"));
  $vals['{obj}']= $order.($pobyt ? ".$pobyt" : '');
  $vals['{vyrizuje}']= $vyrizuje;
  // QR platba
  $vals['{QR-IBAN}']= 'CZ1520100000002000465448'; // Dům setkání: 2000465448 / 2010
  $vals['{QR-ds}']= urlencode('YMCA Setkání');
  $vals['{QR-vs}']= $vals['{VS}']= $vs;
  $vals['{QR-ss}']= $vals['{SS}']= $ss;
  $vals['{QR-pozn}']= urlencode("pobyt v Domě setkání");
  // podle typu faktury
  $roknum= ($rok-2000).'74A'.str_pad($num,4,'0',STR_PAD_LEFT);
  if ($typ==2) { // konečná
    $dum_faktura_fld['faktura'][1]= "<b>Faktura $roknum</b>";
    $dum_faktura_fld['za_co'][1]= "Za pobyt v Domě setkání ve dnech {obdobi} Vám fakturujeme:";
  }
  else { // záloha
    $dum_faktura_fld['faktura'][1]= "<b>Zálohová faktura $roknum</b>";
    $dum_faktura_fld['za_co'][1]= "Fakturujeme Vám zálohu na pobyt v Domě setkání ve dnech {obdobi}:";
  }
  // ------------------------------------------------------------------------------- redakce tabulky
  // redakce položek ceny pro zobrazení ve sloupcích
  $cena= dum_vzorec_cena($par->vzorec,$rok);
  debug($cena,"dum_vzorec_cena($par->vzorec,$rok)");
  $celkem= $cena['celkem'];
  $polozky= [];
  $rozpis_dph= []; 
  if ($strucna==0) { // podrobně - položky ceníku
    foreach ($cena['rozpis'] as $zaco=>$pocet) {
      display("$zaco:$pocet");
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
      $rozpis_dph[$sazba]+= $kc / ((100 + $sazba) / 100);
    }
  }
  elseif ($strucna==1) { // jen přehled ubytování - strava - poplatky
    foreach ($cena['druh2'] as $nazev=>$cc) {
      $kc= $cc['cena'];
      $sazba= $cc['sazba'];
      $polozky[]= [
        $nazev,
        $sazba.'%',
        dum_kc($cc['dph']),
        dum_kc($kc),
        $kc 
      ];
      $rozpis_dph[$sazba]+= $kc / ((100 + $sazba) / 100);
    }
  }
  debug($polozky,'polozky NEW');

  // nadpisy položek a šířka polí
  $popisy= explode(',', $strucna==0
    ? "Položka:79,Počet:12,Cena položky vč. DPH:26,Sazba DPH:14,DPH:25,Cena vč. DPH:28"
    : "Položka:107,Sazba DPH:24,DPH:25,Cena vč. DPH:28")  ;
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
  $cs= $strucna==0 ? [6,4,6,3,3] :  [4,2,4,1,1];
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
  display($tab);
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
      elseif ($type=='QR') {
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
    if ($save) {
      tc_page_cell($text,$type,$align,$fsize*2.4,$l,$t,$w,$h,$border,$lheight);
    }
  }
  // doplnění par o výpočet
  $par->celkem= $celkem;
  $par->vystavena= date('Y-m-d');
  $par->typ= $typ;
  if ($show) {
    $html.= "</div></div>";
  }
  $ref= '';
  if ($save) {
    global $abs_root;
    $fname= "fakt.pdf";
    $f_abs= "$abs_root/docs/$fname";
    $f_rel= "docs/$fname";
    tc_page_close($f_abs,$html);
    $ref= "Fakturu lze stáhnout <a target='pdf' href='$f_rel' style='display:inline'>zde</a>";
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
  debug($par,'par');
  return (object)array('html'=>$html_exp,'ref'=>$ref,'parm_json'=>json_encode($par),
      'parm'=>$par,'err'=>'');
}
/** ====================================================================================> OBJEDNÁVKY **/
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
  $zmena_data= 0;
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
# ----------------------------------------------------------------------------------- dum objednavka
# objednávka pobytu
function dum_objednavka($id_order) { 
  global $answer_db, $setkani_db;
  $x= (object)['err'=>'','vyuziti'=>[],'cena'=>[],'fld'=>[]];
  // shromáždění údajů z objednávky
  $rf= pdo_qry("
      SELECT state,fromday AS od,untilday AS do,note,rooms1,adults,kids_10_15,kids_3_9,kids_3,board,
        org,ic,name,firstname,dic,email,telephone,address,zip,city,
        DATEDIFF(FROM_UNIXTIME(untilday),FROM_UNIXTIME(fromday)) AS noci,akce AS id_akce
        -- ,f.num,f.typ,f.vs,f.ss,f.zaloha,f.castka,f.vystavena,p.datum AS zaplacena,f.vyrizuje
      FROM $setkani_db.tx_gnalberice_order 
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
  $num= select1('IFNULL(MAX(num)+1,1)','faktura',"rok=$f->rok");
  $f->fakt_num= $num;
  //$f->id_akce= select('id_duakce','akce',"id_order=$id_order");
  $f->nazev= "$id_order - {$f->name}";
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
  $x->faktura= (object)['fact_idf'=>0,'zal_idf'=>0];
  $rf= pdo_qry("
    SELECT IFNULL(id_faktura,0) AS idf,typ,strucna,
      rok,num,f.vs,f.ss,spec_text,vzorec,zaloha,f.castka,vystavena,p.datum AS zaplacena, vyrizuje 
    FROM faktura AS f
      LEFT JOIN join_platba AS pf USING (id_faktura) 
      LEFT JOIN platba AS p USING (id_platba) 
    WHERE id_order=$id_order AND typ IN (1,2)",0,0,0,$answer_db);
  while ($rf && ($f= pdo_fetch_object($rf))) {
    foreach ($f as $fld=>$val) {
      if ($fld=='vystavena' || $fld=='zaplacena') $val= sql_date1($val);
      $fld= $f->typ==1 ? "zal_$fld" : "fakt_$fld";
      $x->faktura->$fld= $val;
    }
  }
  debug($x,"dum_objednavka($id_order)");
  return $x;
}
# ==================================================================================> VZOREC + CENÍK
# ----------------------------------------------------------------------------------- dum ... vzorec
# $spolu je buďto klíč id_spolu
# nebo objekt {vek,pokoj,state,board,noci,dotace}
function dum_osoba_vzorec($s,$rok) { //debug($s,"dum_osoba_vzorec(,$rok)");
  $ds2_cena= dum_cenik($rok);
//  debug($ds2_cena);
  $luzko_pokoje= dum_cat_typ();
  $ds_strava= map_cis('ds_strava','zkratka');
  $rozpis= [];
  // ubytování osob podle věku
  $noc= 'noc_'.$luzko_pokoje[$s->pokoj];
  // poplatky podle věku
  $poplatky= ['ubyt_P','ubyt_C','ubyt_S','noc_B',$noc];
  if ($s->state==3) { // akce YMCA má poplatek za program
    array_push($poplatky,'prog_C','prog_P');
  }
  foreach ($poplatky as $p) {
//          debug($ds2_cena[$p],"pokoj $pokoj,$p");
    if ($s->vek >= $ds2_cena[$p]->od && $s->vek < $ds2_cena[$p]->do && $ds2_cena[$p]->cena) {
      $rozpis[$p]= $s->dotace && $ds2_cena[$p]->dotovana ? [0,$s->noci] : [$s->noci,0];
//      $suma->rozpis[$p]+= $noci;
    }
  }
  // strava osob podle věku
  $strava= 'strava_'.$ds_strava[$s->board];
  if ($s->vek>=$ds2_cena[$strava.'D']->od && $s->vek<$ds2_cena[$strava.'D']->do) {
    $p= "{$strava}D";
    $rozpis[$p]= $s->dotace && $ds2_cena[$p]->dotovana ? [0,$s->noci] : [$s->noci,0];
//    $rozpis["{$strava}D"]+= $s->noci;
//    $suma->rozpis["{$strava}D"]+= $noci;
  }
  if ($s->vek>=$ds2_cena[$strava.'C']->od && $s->vek<$ds2_cena[$strava.'C']->do) {
    $p= "{$strava}C";
    $rozpis[$p]= $s->dotace && $ds2_cena[$p]->dotovana ? [0,$s->noci] : [$s->noci,0];
//    $rozpis["{$strava}C"]+= $s->noci;
//    $suma->rozpis["{$strava}C"]+= $noci;
  }
  return dum_rozpis2vzorec($rozpis);
}
//function dum_pobyt_vzorec($id_pobyt) {
//  return $vzorec;
//}
//function dum_objednavka_vzorec($id_order,$typ=1) { // typ=1 pro rezervaci, typ=2 pro vyúčtování
//  return $vzorec;
//}
# ----------------------------------------------------------------------------------------- dum cena
# dum_vzorec_cena: vzorec -> cena  
# kde vzorec = část (',' část)* 
#     část = položka ':' počet v plné sazbě [ ':' počet v dotované sazbě ]
function dum_vzorec_cena($vzorec,$rok_ceniku) {
  $ds2_cena= dum_cenik($rok_ceniku);
  // podrobný rozpis ceny podle druhu a dph, včetně typ->polozka
  $cena= ['celkem'=>0,'druh'=>[],'abbr'=>[],'cena'=>[],'cena_dph'=>[],'dph'=>[],'rozpis'=>[],
      'polozka'=>[]]; 
//  $rozpis= is_string($vzorec) ? explode(',',$vzorec) : $vzorec;
  $rozpis= dum_vzorec2rozpis($vzorec);
  foreach ($rozpis as $zaco=>$cs) {
    list($c,$s)= (array)$cs; // cena, sleva
    if ($c) {
      $pocet= $c;
      $d= $ds2_cena[$zaco];
      $kc= $d->cena;
      $cena['celkem']+= $kc * $pocet;
      $cena['druh'][$d->druh]+= $kc * $pocet;
      $cena['abbr'][substr($d->druh,0,4)]+= $kc * $pocet;
      $cena['cena'][$zaco]= $kc * $pocet;
      $cena['cena_dph'][$zaco]= $dph= $kc * $pocet - ($kc * $pocet) / ((100 + $d->dph) / 100);
      $cena['dph'][$d->dph]+= $dph;
      $cena['rozpis'][$zaco]= $pocet;
      $cena['polozka'][$zaco]= (object)['polozka'=>$d->polozka,'cena'=>$kc,'dph'=>$d->dph];
      $cena['druh2'][$d->druh]['cena']+= $kc * $pocet;
      $cena['druh2'][$d->druh]['dph']+= $dph;
      $cena['druh2'][$d->druh]['sazba']= $d->dph;
    }
    if ($s) {
      $pocet= $s;
      $d= $ds2_cena[$zaco];
      $zaco.= '/d';
      $kc= $d->dotovana;
      $cena['celkem']+= $kc * $pocet;
      $cena['druh'][$d->druh]+= $kc * $pocet;
      $cena['abbr'][substr($d->druh,0,4)]+= $kc * $pocet;
      $cena['cena'][$zaco]= $kc * $pocet;
      $cena['cena_dph'][$zaco]= $dph= $kc * $pocet - ($kc * $pocet) / ((100 + $d->dph) / 100);
      $cena['dph'][$d->dph]+= $dph;
      $cena['rozpis'][$zaco]= $pocet;
      $cena['polozka']["$zaco"]= (object)['polozka'=>"$d->polozka ... dotovaná cena",'cena'=>$kc,'dph'=>$d->dph];
      $cena['druh2'][$d->druh]['cena']+= $kc * $pocet;
      $cena['druh2'][$d->druh]['dph']+= $dph;
      $cena['druh2'][$d->druh]['sazba']= $d->dph;
    }
  }
  return $cena;
}
function dum_rozpis2vzorec($rozpis) {
  $vzorec= ''; $del= '';
  foreach ($rozpis as $i=>$vd) {
    list($v,$d)= (array)$vd;
    $vzorec.= "$del$i:$v:$d"; $del= ',';
  }
  return $vzorec;
}
function dum_vzorec2rozpis($vzorec) {
  $rozpis= []; 
  foreach (explode(',',$vzorec) as $iv) {
    list($i,$v,$d)= explode(':',$iv);
    if (!$i) continue;
    $rozpis[$i][0]+= 0+$v;
    $rozpis[$i][1]+= 0+$d??0;
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
    $cena['celkem']+= $d->cena * $pocet;
    $cena['druh'][$d->druh]+= $d->cena * $pocet;
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
    } 
  }
  return $cena;
}
# ==================================================================================> objednávky NEW
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
  debug($x,"dum_browse_order");
  $y= (object)array('ok'=>0);
  $curr= $x->sql; // předání pracovní akce
  switch ($x->cmd) {
  case 'browse_load':  # -----------------------------------==> . browse_load
    $z= [];
    ezer_connect($answer_db,true);
    $rp= pdo_qry("
      SELECT uid,d.id_akce,a.access,name,d.note,SUM(IF(IFNULL(id_osoba,0),1,0)),
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
    while ($rp && (list($uid,$ida,$access,$name,$note,$osob,$od,$do)= pdo_fetch_array($rp))) {
      $z[$uid]->id_order= $uid;
      $z[$uid]->id_akce= $ida;
      $z[$uid]->curr= $ida==$curr ? 1 : 0;
      $z[$uid]->access= $access;
      $z[$uid]->nazev= $name;
      $z[$uid]->note= $note;
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
    debug($y,"dum_browse_orders>  ");
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
        'neubytovani'=>'',  // případný text varování o neubytovaných
        'rozpis'=>[],
        'hoste' =>(object)['adults'=>0,'kids_10_15'=>0,'kids_3_9'=>0,'kids_3'=>0]]; 
//    $luzko_pokoje= dum_cat_typ();
//    $ds_strava= map_cis('ds_strava','zkratka');
    $neubytovani= [];
    $vzorec_order= '';
    // c.ikona=1 pokud nebyl na akci
    ezer_connect($answer_db,true);
    $rp= pdo_qry("
      SELECT id_pobyt,c.ikona,prijmeni,datum_od,datum_od,DATEDIFF(datum_do,datum_od),YEAR(datum_od),
        GROUP_CONCAT(CONCAT(id_spolu,'~',prijmeni,'~',jmeno,'~',narozeni,
            '~',0,'~',TRIM(IF(s.pokoj,s.pokoj,p.pokoj)),'~',s.ds_vzorec,'~',s.ds_dotace,
            '~',0,'~',0,'~',0,'~',0,'~',0,'~',0) 
          ORDER BY IF(narozeni='0000-00-00','9999-99-99',narozeni) 
          SEPARATOR '~' ) AS cleni,d.state,d.board,IFNULL(x.datum,''),IFNULL(x.castka,0)
      FROM osoba AS o 
        JOIN spolu AS s USING (id_osoba) 
        JOIN pobyt AS p USING (id_pobyt) 
        JOIN akce AS a ON id_akce=id_duakce 
        JOIN _cis AS c ON c.druh='ms_akce_funkce' AND c.data=p.funkce
        JOIN $setkani_db.tx_gnalberice_order AS d ON d.id_akce=id_duakce
        LEFT JOIN platba AS x ON id_pob=id_pobyt
      WHERE $x->cond
      GROUP BY id_pobyt
      ORDER BY prijmeni
    ");
    $i_prijmeni= 1; $i_vek= 3; $i_noci= 4; $i_pokoj= 5; $i_vzorec= 6; $i_dotace= 7; $i_fix= 13; 
    $i_delta= 14;
    while ($rp && (list($idp,$nebyl,$prijmeni,$od,$do,$noci,$rok,$cleni,$state,$board,$datum,$platba)
        = pdo_fetch_array($rp))) {
      // projdeme členy a spočteme cenu
      $rok_ceniku= $rok;
      $celkem= 0;
//      $noci= date_diff(date_create($od),date_create($do))->format('%a');
      $c= explode('~',$cleni);
      for ($i= 0; $i<count($c); $i+= $i_delta) {
//        $ids= $c[$i];
        
        $vek= roku_k($c[$i+$i_vek],$od); // věk ns začátku akce
        $c[$i+$i_vek]= $vek;
        $c[$i+$i_noci]= $noci;
        // doplníme počty do SUMA - jen pokud nebyla zrušena účast
        if ($nebyl==0) {

          $pokoj= $c[$i+$i_pokoj];
          if (!$pokoj) $neubytovani[]= $c[$i+$i_prijmeni];
          $ps= explode(',',$pokoj);
          foreach ($ps as $p) {
            $suma->pokoj[$p]+= 1/count($ps); 
            $pokoj= $p;
          }
          // člověkonoci
          $suma->clovekonoci+= $noci;
          // počty osob podle věku
          if ($vek<3) $suma->hoste->kids_3++;
          elseif ($vek<10) $suma->hoste->kids_3_9++;
          elseif ($vek<15) $suma->hoste->kids_10_15++;
          else $suma->hoste->adults++;
          // pokud je naplněna položka ds_vzorec tak se použije místo výpočtu
          $vzorec= $c[$i+$i_vzorec];
          $c[$i+$i_fix]= $vzorec ? 1 : 0;
          
          
          $dotace= $c[$i+$i_dotace];
          $vzorec= $c[$i+$i_vzorec]= $vzorec ?: dum_osoba_vzorec((object)
            ['vek'=>$vek,'pokoj'=>$pokoj,'state'=>$state,'board'=>$board,'noci'=>$noci,'dotace'=>$dotace],
            $rok);
          $cena= dum_vzorec_cena($vzorec,$rok_ceniku);
          $celkem+= $c[$i+8]= $cena['celkem'];
          $c[$i+9]= $cena['druh']['ubytování']??0;
          $c[$i+10]= $cena['druh']['strava']??0;
          $c[$i+11]= $cena['druh']['poplatek obci']??0;
          $c[$i+12]= $cena['druh']['program']??0;
          $vzorec_order.= ",$vzorec";
          display("$prijmeni: $vzorec");
        }
      }
      $cleni= implode('~',$c);
      // doplníme pobyt
      $z[$idp]->cleni= $cleni;
      $z[$idp]->idp= $idp;
      $z[$idp]->nazev= $prijmeni;
      $z[$idp]->cena= $celkem;
      // doplníme platbu
      $z[$idp]->platba= $platba;
      $z[$idp]->datum= $datum;
    }
    # předání pro browse
    $y->values= $z;
    $y->from= 0;
    $y->cursor= 0;
    $y->rows= count($z);
    $y->count= count($z);
    $y->quiet= $x->quiet;
    $y->ok= 1;
    // dopočet sumy přehled a účtování
//    debug($suma->rozpis,"dum_browse_order/rozpis = ");
    $cena= dum_vzorec_cena($suma->rozpis,$rok_ceniku);
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
    $suma->vzorec= dum_rozpis2vzorec(dum_vzorec2rozpis($vzorec_order));
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
  global $answer_db, $setkani_db, $y; // y je zde globální kvůli možnosti trasovat SQL dotazy
  debug($x,"dum_browse_pobyt>");
  $y= (object)array('ok'=>0);
  switch ($x->cmd) {
  case 'suma':
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
      SELECT id_pobyt,id_spolu,d.uid,c.ikona,datum_od,datum_do,DATEDIFF(datum_do,datum_od) AS noci,
        YEAR(datum_od) AS rok,d.state,d.board,prijmeni,jmeno,narozeni,
        TRIM(IF(s.pokoj,s.pokoj,p.pokoj)) AS pokoj,s.ds_vzorec,ds_dotace,ulice,psc,obec
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
        $idp,$ids,$idd,$nebyl,$od,$do,$noci,$rok,$state,$board,$prijmeni,$jmeno,$narozeni,$pokoj,
        $vzorec,$dotace,$ulice,$psc,$obec)= $dump= pdo_fetch_array($rp))) {
//      debug($dump);
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
//      $noci= date_diff(date_create($od),date_create($do))->format('%a');
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
        // člověkonoci
        $suma->clovekonoci+= $noci;
        // počty osob podle věku
        if ($vek<3) $suma->hoste->kids_3++;
        elseif ($vek<10) $suma->hoste->kids_3_9++;
        elseif ($vek<15) $suma->hoste->kids_10_15++;
        else $suma->hoste->adults++;
        // pokud je naplněna položka ds_vzorec tak se použije místo výpočtu
        $fix= $vzorec ? 1 : 0;
        
        $vzorec= $vzorec ?: dum_osoba_vzorec((object)
          ['vek'=>$vek,'pokoj'=>$pokoj,'state'=>$state,'board'=>$board,'noci'=>$noci,'dotace'=>$dotace],
          $rok);
        $vzorec_pobyt.= ",$vzorec";
        display("$jmeno: $vzorec");
//        $rz= dum_vzorec_cena($vzorec,$rok);
//        $suma->rozpis
//        debug($rz,"dum_vzorec_cena($vzorec,$rok)");
        
        
        // doplníme ceny
        if ($x->cmd!='suma') {
          $cena= dum_vzorec_cena($vzorec,$rok_ceniku);
          $celkem+= $z[$ids]['cena']= $cena['celkem'];
          $z[$ids]['ubyt']= $cena['druh']['ubytování']??0;
          $z[$ids]['str']=  $cena['druh']['strava']??0;
          $z[$ids]['popl']= $cena['druh']['poplatek obci']??0;
          $z[$ids]['prog']= $cena['druh']['program']??0;
        }
      } // nebyl==0
      // doplníme pobyt
      if ($x->cmd!='suma') {
        $z[$ids]['ids']= $ids;
        $z[$ids]['prijmeni']= $prijmeni;
        $z[$ids]['jmeno']= $jmeno;
        $z[$ids]['vek']= $vek;
        $z[$ids]['pokoj']= $pokoj;
        $z[$ids]['noci']= $noci;
        $z[$ids]['vzorec_spolu']= $vzorec;
        $z[$ids]['zamek_spolu']= $fix;
        $z[$ids]['dotace_spolu']= $dotace;
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
    debug($cena,"*dum_vzorec_cena($vzorec_pobyt,$rok_ceniku)");
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
        rok,num,f.vs,f.ss,spec_text,vzorec,zaloha,f.castka,zaloha,
        vystavena,p.datum AS zaplacena, vyrizuje 
      FROM faktura AS f
        LEFT JOIN join_platba AS pf USING (id_faktura) 
        LEFT JOIN platba AS p USING (id_platba) 
      WHERE $x->cond"); //,0,0,0,$answer_db);
    if ($rf) $fakt= pdo_fetch_object($rf);
    $suma->faktura= $fakt;
    debug($suma,"dum_browse_pobyt/suma = ");
    return $suma;      
  }
  else { // browse
    debug($y->values,">dum_browse_pobyt");
//    debug($y,">dum_browse_pobyt");
    $y->suma= $suma;
    return $y;  
  }
} // dum_browse_pobyt
# ----------------------------------------------------------------------------- dum clone_objednavka
# načtení ceníku pro daný rok
function dum_clone_objednavka($id_order) {  
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
# ======================================================================================> objednávky
# ---------------------------------------------------------------------------------------- ds2 cenik
# načtení ceníku pro daný rok
function dum_cenik($rok) {  
  global $ds2_cena;
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
# =======================================================================================> LEFT MENU
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
  // Dům setkání - objednávvky
  debug($c,'ds2_show_curr');
  if (($idx= $c->dum->order)) {
    list($jmeno,$prijmeni,$od,$do)= select('firstname,name,fromday,untilday',
        ' tx_gnalberice_order',"uid=$idx",'setkani');
    $dum.= " obj. $idx";
    $mmyyyy= date('mY',$od);
    $od= date('j.n.',$od);
    $do= date('j.n.Y',$do);
    $celkem= $c->dum->celkem ? number_format($c->dum->celkem,2,'.',' ') : '?';
    $d_jmeno.= wu("$jmeno $prijmeni, $od-$do, $celkem").' Kč';
    display("$dum|$d_jmeno");
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
          JOIN faktura AS f USING (ss,vs,castka) 
          LEFT JOIN join_platba AS j USING (id_platba,id_faktura)
          WHERE ucet=2 AND ISNULL(j.id_faktura) 
            AND $omezeni AND vystavena BETWEEN '$cmd->od' AND '$cmd->do'");
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
      $done= false; $nazev= preg_replace('~ová$~','ovi',1,$done,$prijmeni);
      if (!$done)   $nazev= preg_replace('~ová$~','ovi',1,$done,$prijmeni);
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
// ========================================================================= doplnění osoba + rodina
// 
function check_access($tab,$id,$access_akce) { 
  display("check_access($tab,$id,$access_akce)");
}
// ========================================================================= funkce ze StackOverflow
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
