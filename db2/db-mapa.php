<?php

# --------------------------------------------------------------------------------------- akce2 mapa
# získání seznamu souřadnic bydlišť účastníků akce 
# s případným filtrem - např. bez pečounů: pobyt.funkce!=99
function akce2_mapa($akce,$filtr='') {  trace();
  global $ezer_version;
  // dotaz
  $psc= $obec= array();
  $AND= $filtr ? " AND $filtr" : '';
  $qo=  "
    SELECT prijmeni,adresa,psc,obec,
      (SELECT MIN(CONCAT(role,RPAD(psc,5,' '),'x',obec))
       FROM tvori AS ot JOIN rodina AS r USING (id_rodina)
       WHERE ot.id_osoba=o.id_osoba 
      ) AS r_psc
    FROM pobyt
    JOIN spolu USING (id_pobyt)
    JOIN osoba AS o USING (id_osoba)
    WHERE id_akce='$akce' $AND
    GROUP BY id_osoba
    ";
  // najdeme použitá PSČ
  $ro= pdo_qry($qo);
  while ( $ro && ($o= pdo_fetch_object($ro)) ) {
    $p= $o->adresa ? $o->psc : substr($o->r_psc,1,5);
    $m= $o->adresa ? $o->obec : substr($o->r_psc,7);
    $psc[$p].= "$o->prijmeni ";
    $obec[$p]= $obec[$p] ?: $m;
  }
//                                         debug($psc);
  $icon= "./ezer$ezer_version/client/img/circle_gold_15x15.png,7,7";
  return mapa2_psc($psc,$obec,0,$icon); // vrací (object)array('mark'=>$marks,'n'=>$n,'err'=>$err);
}
# ---------------------------------------------------------------------------==> .. mapa2 geo_export
# vrátí strukturu pro gmap
function mapa2_geo_export($par) {
  // pro doplnění o LOC_CITY_DISTR_CODE|LOC_GEO_LATITUDE|LOC_GEO_LONGITUDE
  $tab= $par->tab;
  $html= '';
  $n= 0;
  $fpath= "docs/geo-$tab.csv";
  $flds= "id;ulice;psc;obec;code;lat;lng";
  $f= @fopen($fpath,'w');
  fputs($f, chr(0xEF).chr(0xBB).chr(0xBF));  // BOM pro Excel
  fputcsv($f,explode(';',$flds),';');
  switch ($tab) {
  case 'osoba': 
    $mr= pdo_qry("
      SELECT id_osoba,ulice,psc,obec
      FROM osoba 
      WHERE deleted='' AND adresa=1 AND stat IN ('','CZ') AND (ulice!='' OR obec!='')
      ORDER BY id_osoba
    ");
    while ( $mr && list($id,$ulice,$psc,$obec)= pdo_fetch_row($mr) ) {
      fputcsv($f,array($id,$ulice,$psc,$obec,0,0,0),';');
      $n++;
    }
    break;
  case 'rodina': 
    $mr= pdo_qry("
      SELECT id_rodina,ulice,psc,obec
      FROM rodina
      WHERE deleted='' AND stat IN ('','CZ') AND (ulice!='' OR obec!='')
      ORDER BY id_rodina
    ");
    while ( $mr && list($id,$ulice,$psc,$obec)= pdo_fetch_row($mr) ) {
      fputcsv($f,array($id,$ulice,$psc,$obec,0,0,0),';');
      $n++;
    }
    break;
  }
  fclose($f);
  $html.= "tabulka $tab má $n relevantních záznamů pro geolokaci - jsou zde ke "
      . "<a href='$fpath'>stáhnutí</a>";
  return $html;
}
# ------------------------------------------------------------------------------------ mapa2 skupiny
# přečtení seznamu skupin
function mapa2_skupiny() {  trace();
//  global $json;
  $goo= "https://docs.google.com/spreadsheets/d";
  $key= "1mp-xXrF1I0PAAXexDH5FA-n5L71r5y0Qsg75cU82X-4";         // Seznam skupin - kontakty
  $prefix= "google.visualization.Query.setResponse(";           // přefix json objektu
  $sheet= "List 1";
  $x= file_get_contents("$goo/$key/gviz/tq?tqx=out:json"); //&sheet=$sheet");
//                                         display($x);
  $xi= strpos($x,$prefix);
  $xl= strlen($prefix);
//                                         display("xi=$xi,$xl");
  $x= substr(substr($x,$xi+$xl),0,-2);
//                                         display($x);
  $tab= json_decode($x)->table;
//                                         debug($tab,$sheet);
  // projdeme získaná data
  $psc= $note= $clmns= array();
  $n= 0;
  $msg= '';
  if ( $tab ) {
    foreach ($tab->rows as $crow) {
      $row= $crow->c;
      if ( $row[0]->v=="ZVLÁŠTNÍ SKUPINY:" ) break;     // konec seznamu
      $skupina= $row[0]->v;
      $p= $row[1]->v;
      $p= strtr($p,array(' '=>'','?'=>'',"\n"=>''));
      $aktual= $row[2]->v;
      if ( preg_match("/(\d+),(\d+),(\d+)/",$x,$m) )
        $aktual= "$m[3].$m[2].$m[1]";
      $kontakt= $row[3]->v;
      $email= $row[4]->v;
      $pozn= $row[5]->v;
      if ( strlen($p)==5 ) {
        $psc[$p]= $pozn;
        $note[$p]= $skupina;
        $n++;
        // podrobnosti do pole $clmns
        $clmns[$p]=
          "<h3>$skupina</h3><p>Kontakt:$kontakt, <b>$email</b></p>"
        . "<p>$pozn</p><p style='text-align:right'><i>aktualizováno: $aktual</i></p>";
      }
      else {
//                                         debug($crow,"problém");
        $msg.= " $p";
      }
    }
  }
  // konec
end:
  $ret= mapa2_psc($psc,$note,1);
  $msg= $msg ? "<br><br>Problém nastal pro PSČ: $msg" : '';
  $msg.= $ret->err ? "<br><br>$ret->err" : '';
  $ret->err= '';
  $ret->clmns= $clmns;
  $ret->msg= "Je zobrazeno $n skupin z tabulky <b>Seznam skupin - kontakty</b>$msg";
  return $ret;
}
# -----------------------------------------------------------------------------==> .. mapa2 psc_list
# vrátí strukturu pro gmap
function mapa2_psc_list($psc_lst) {
  $psc= $obec= array();
  foreach (explode(',',$psc_lst) as $p) {
    $psc[$p]= $p;
  }
  return mapa2_psc($psc,$obec);
}
# ----------------------------------------------------------------------------------==> .. mapa2 psc
# vrátí strukturu pro gmap
# icon = CIRCLE[,scale:1-10][,ontop:1]|cesta k bitmapě nebo pole psc->icon
function mapa2_psc($psc,$obec,$psc_as_id=0,$icon='') {
//                                                debug($psc,"mapa2_psc");
  // k PSČ zjistíme LAN,LNG
  $ret= (object)array('mark'=>'','n'=>0);
  $ic= '';
  if ( $icon ) {
    if ( !is_array($icon) )
      $ic=",$icon";
  }
  $marks= $err= '';
  $mis_psc= array();
  $err_psc= array();
  $chybi= array();
  $n= 0; $del= '';
  foreach ($psc as $p=>$tit) {
    $p= trim($p);
    if ( preg_match('/\d\d\d\d\d/',$p) ) {
      $qs= "SELECT psc,lat,lng FROM psc_axy WHERE psc='$p'";
      $rs= pdo_qry($qs);
      if ( $rs && ($s= pdo_fetch_object($rs)) ) {
        $n++;
        $o= isset($obec[$p]) ? $obec[$p] : $p;
        $title= str_replace(',','',"$o:$tit");
        $id= $psc_as_id ? $p : $n;
        if ( is_array($icon) )
          $ic= ",{$icon[$p]}";
        $marks.= "{$del}$id,{$s->lat},{$s->lng},$title$ic"; $del= ';';
      }
      else {
        $err_psc[$p].= " $p";
        if ( !in_array($p,$chybi) ) 
          $chybi[]= $p;
      }
    }
    else {
      $mis_psc[$p].= " $p";
    }
  }
  // zjištění chyb
  if ( count($err_psc) || count($mis_psc) ) {
    if ( ($ne= count($mis_psc)) ) {
      $err= "$ne PSČ chybí nebo má špatný formát. Týká se to: ".implode(' a ',$mis_psc);
    }
    if ( ($ne= count($err_psc)) ) {
      $err.= "<br>$ne PSČ se nepovedlo lokalizovat. Týká se to: ".implode(' a ',$err_psc);
    }
  }
  $ret= (object)array('mark'=>$marks,'n'=>$n,'err'=>$err,'chybi'=>$chybi);
//                                                    debug($chybi,"chybějící PSČ");
  return $ret;
}
# ------------------------------------------------------------------------------==> .. mapa2 psc_set
# ASK
function mapa2_psc_set($psc,$latlng) {  trace();
  list($lat,$lng)= preg_split("/,\s*/",$latlng);
  if ( !$psc || !$lat || !$lng ) goto end;
  $ex= select("COUNT(*)",'psc_axy',"psc='$psc'");
  if ($ex) {
    query("UPDATE psc_axy SET lat='$lat',lng='$lng' WHERE psc='$psc'");
  }
  else {
    query("INSERT INTO psc_axy (psc,lat,lng) VALUE ('$psc','$lat','$lng')");
  }
end:  
  return 1;
}
# ------------------------------------------------------------------------------==> .. mapa2 ctverec
# ASK
# obsah čtverce $clen +- $dist (km) na všechny strany
# vrací objekt {
#   err:  0/1
#   msg:  text chyby
#   rect: omezující obdélník jako SW;NE
#   poly: omezující polygon zmenšený o $perc % oproti obdélníku
function mapa2_ctverec($mode,$id,$dist,$perc) {  trace();
  $ret= (object)array('err'=>0,'msg'=>'');
  // zjištění polohy člena
  $lat0= $lng0= 0;
  $qc= in_array($mode,array('o','h','m'))
   ? "SELECT IF(ISNULL(g.lat),a.lat,g.lat) AS lat,IF(ISNULL(g.lng),a.lng,g.lng) AS lng
      FROM osoba AS o
      LEFT JOIN osoba_geo AS g USING (id_osoba)
      LEFT JOIN tvori AS t USING (id_osoba)
      LEFT JOIN rodina AS r USING (id_rodina)
      LEFT JOIN psc_axy AS a ON a.psc=IF(o.adresa,o.psc,r.psc)
      WHERE id_osoba=$id"
   : "SELECT lat,lng
      FROM rodina AS r
      LEFT JOIN psc_axy AS a ON a.psc=r.psc
      WHERE id_rodina=$id";
  $rc= pdo_qry($qc);
  if ( $rc && $c= pdo_fetch_object($rc) ) {
    $lat0= $c->lat;
    $lng0= $c->lng;
  }
  if ( !$lat0 ) { $ret->msg= "nelze najít polohu $mode/$id"; $ret->err++; goto end; }
  // čtverec  SW;NE
  $delta_lat= 0.0089913097;
  $delta_lng= 0.0137464041;
  $N= $lat0-$dist*$delta_lat;
  $S= $lat0+$dist*$delta_lat;
  $W= $lng0-$dist*$delta_lng;
  $E= $lng0+$dist*$delta_lng;
  $ret->rect= "$N,$W;$S,$E";
  // polygon
  $d_lat= abs($N-$S)*$perc/300;
  $d_lng= abs($W-$E)*$perc/100;
  $N= $N-$d_lat;
  $S= $S+$d_lat;
  $W= $W+$d_lng;
  $E= $E-$d_lng;
  $ret->poly= "$N,$W;$N,$E;$S,$E;$S,$W";
end:
//                                                 debug($ret,"geo_get_ctverec");
  return $ret;
}
# --------------------------------------------------------------------------------- mapa2 ve_ctverci
# ASK
# vrátí jako seznam id_$tab bydlících v oblasti dané obdélníkem 'x,y;x,y'
# podmnožinu předaných ids a k tomu řetezec definující značky v mapě
# pokud je rect prázdný - vrátí vše, co lze lokalizovat
# pokud by seznam byl delší než MAX, vrátí chybu
function mapa2_ve_ctverci($mode,$rect,$ids,$max=5000) { trace();
  global $ezer_version;
  $ret= (object)array('err'=>'','rect'=>$rect,'ids'=>'','marks'=>'','pocet'=>0);
  if ( $rect ) {
    list($sell,$nwll)= explode(';',$rect);
    $se= explode(',',$sell);
    $nw= explode(',',$nwll);
    $poloha= "IF(ISNULL(g.lat),a.lat,g.lat) BETWEEN $nw[0] AND $se[0]
      AND IF(ISNULL(g.lng),a.lng,g.lng) BETWEEN $se[1] AND $nw[1]";
  }
  else {
    $poloha= "lat!=0 AND lng!=0";
  }
  $qo= in_array($mode,array('o','h','m'))
   ? "SELECT id_osoba,IFNULL(g.lat,0),
        IF(ISNULL(g.lat),a.lat,g.lat) AS lat,IF(ISNULL(g.lng),a.lng,g.lng) AS lng
      FROM osoba AS o
      LEFT JOIN osoba_geo AS g USING (id_osoba)
      LEFT JOIN tvori AS t USING (id_osoba)
      LEFT JOIN rodina AS r USING (id_rodina)
      LEFT JOIN psc_axy AS a ON a.psc=IF(o.adresa,o.psc,r.psc)
      WHERE id_osoba IN ($ids) AND $poloha "
   : // r|f
     "SELECT id_rodina,IFNULL(g.lat,0),
        IF(ISNULL(g.lat),a.lat,g.lat) AS lat,IF(ISNULL(g.lng),a.lng,g.lng) AS lng
      FROM rodina AS r
      LEFT JOIN rodina_geo AS g USING (id_rodina)
      LEFT JOIN psc_axy AS a ON a.psc=r.psc
      WHERE id_rodina IN ($ids) AND $poloha ";
  $ro= pdo_qry($qo);
  if ( $ro ) {
    $ret->pocet= pdo_num_rows($ro);
    if ( $max && $ret->pocet > $max ) {
      $ret->err= ($rect ? "Ve výřezu mapy je" : "Je požadováno"). " příliš mnoho bodů "
        . "({$ret->pocet} nejvíc lze $max)";
    }
    else {
      $del= $semi= '';
      while ( $ro && list($id,$geo,$lat,$lng)= pdo_fetch_row($ro) ) {
        $ret->ids.= "$del$id"; 
        $color= $geo ? 'green' : 'gold';
        $ret->marks.= "$semi$id,$lat,$lng,$id,./ezer$ezer_version/client/img/circle_{$color}_15x15.png,7,7"; 
        $del= ','; $semi= ';';
      }
    }
  }
end:  
  return $ret;
}
# ----------------------------------------------------------------------------- mapa2 psc_v_polygonu
# ASK
# vrátí jako seznam PSČ ležící v oblasti dané polygonem 'x,y;...'
# pokud by seznam byl delší než MAX, vrátí chybu
function mapa2_psc_v_polygonu($poly) { trace();
  $ret= (object)array('err'=>'','pscs'=>'','pocet'=>0);
  // nalezneme ohraničující obdélník a převedeme polygon do interního tvaru
  $lat_min= $lng_min= 999;
  $lng_max= $lng_max= 0;
  $x= $y= array();
  foreach ( explode(';',$poly) as $bod) {
    list($lat,$lng)= explode(',',$bod);
    $lat_min= min($lat_min,$lat);
    $lat_max= max($lat_max,$lat);
    $lng_min= min($lng_min,$lng);
    $lng_max= max($lng_max,$lng);
    $x[]= floatval($lat); 
    $y[]= floatval($lng);
  }
  // uzavři polygon pokud není 
  $n= count($x);
  if ( $x[0]!=$x[n-1] || $y[0]!=$y[n-1] ) {
    $x[$n]= $x[0];
    $y[$n]= $y[0];
  }
  // dotaz na ohraničující obdélník
  $poloha= "lat BETWEEN $lat_min AND $lat_max AND lng BETWEEN $lng_min AND $lng_max";
  $qo= "SELECT psc, lat, lng
      FROM psc_axy 
      WHERE $poloha ";
  $ro= pdo_qry($qo);
  if ( $ro ) {
    $ret->pocet= pdo_num_rows($ro);
    $del= '';
    while ( $ro && list($psc,$lat,$lng)= pdo_fetch_row($ro) ) {
      // zjistíme, zda leží uvnitř polygonu
      if ( maps_poly_cross($lat,$lng,$x,$y) ) {
        $ret->pscs.= "$del$psc"; $del= ',';
      }
    }
  }
  return $ret;
}
# --------------------------------------------------------------------------------- mapa2 v_polygonu
# ASK
# vrátí jako seznam id_$tab bydlící v oblasti dané polygonem 'x,y;...'
# pokud by seznam byl delší než MAX, vrátí chybu
function mapa2_v_polygonu($mode,$poly,$ids,$max=5000) { trace();
  global $ezer_version;
  $ret= (object)array('err'=>'','poly'=>$poly,'ids'=>'','pocet'=>0);
  // nalezneme ohraničující obdélník a přvedeme polygon do interního tvaru
  $lat_min= $lng_min= 999;
  $lng_max= $lng_max= 0;
  $x= $y= array();
  foreach ( explode(';',$poly) as $bod) {
    list($lat,$lng)= explode(',',$bod);
    $lat_min= min($lat_min,$lat);
    $lat_max= max($lat_max,$lat);
    $lng_min= min($lng_min,$lng);
    $lng_max= max($lng_max,$lng);
    $x[]= floatval($lat); 
    $y[]= floatval($lng);
  }
  // uzavři polygon pokud není 
  $n= count($x);
  if ( $x[0]!=$x[n-1] || $y[0]!=$y[n-1] ) {
    $x[$n]= $x[0];
    $y[$n]= $y[0];
  }
  // dotaz na ohraničující obdélník
  $poloha= "IF(ISNULL(g.lat),a.lat,g.lat) BETWEEN $lat_min AND $lat_max 
    AND IF(ISNULL(g.lng),a.lng,g.lng) BETWEEN $lng_min AND $lng_max";
  $qo= in_array($mode,array('o','h','m')) 
   ? "SELECT id_osoba,IFNULL(g.lat,0),
        IF(ISNULL(g.lat),a.lat,g.lat) AS lat,IF(ISNULL(g.lng),a.lng,g.lng) AS lng
      FROM osoba AS o
      LEFT JOIN osoba_geo AS g USING (id_osoba)
      LEFT JOIN tvori AS t USING (id_osoba)
      LEFT JOIN rodina AS r USING (id_rodina)
      LEFT JOIN psc_axy AS a ON a.psc=IF(o.adresa,o.psc,r.psc)
      WHERE id_osoba IN ($ids) AND $poloha "
   : "SELECT id_rodina,IFNULL(g.lat,0),
        IF(ISNULL(g.lat),a.lat,g.lat) AS lat,IF(ISNULL(g.lng),a.lng,g.lng) AS lng
      FROM rodina AS r
      LEFT JOIN rodina_geo AS g USING (id_rodina)
      LEFT JOIN psc_axy AS a ON a.psc=r.psc
      WHERE id_rodina IN ($ids) AND $poloha ";
  $ro= pdo_qry($qo);
  if ( $ro ) {
    $ret->pocet= pdo_num_rows($ro);
    if ( $max && $ret->pocet > $max ) {
      $ret->err= "Je požadováno příliš mnoho bodů {$ret->pocet} nejvíc lze $max";
    }
    else {
      $del= $semi= '';
      while ( $ro && list($id,$geo,$lat,$lng)= pdo_fetch_row($ro) ) {
        // zjistíme, zda leží uvnitř polygonu
        if ( maps_poly_cross($lat,$lng,$x,$y) ) {
          $ret->ids.= "$del$id"; 
          $color= $geo ? 'green' : 'gold';
          $ret->marks.= "$semi$id,$lat,$lng,$id,./ezer$ezer_version/client/img/circle_{$color}_15x15.png,7,7"; 
          $del= ','; $semi= ';';
        }
      }
    }
  }
  return $ret;
}
# ---------------------------------------------------------------------------------- maps_poly_cross
# crossing number test for a point in a polygon
# input:   P = a point $x0,$y0,
#        V[] = n-1 vertex points of a polygon with V[n]=V[0], V[i]=$x[i],$y[i]
# returns: 0 = outside, 1 = inside
# This code is patterned after [Franklin, 2000]
# http://softsurfer.com/Archive/algorithm_0103/algorithm_0103.htm
function maps_poly_cross($x0,$y0,$x,$y) {
  $N= count($x)-1;
  $cn= 0;                                               // the crossing number counter
  // loop through all edges of the polygon
  for ($i=0; $i<$N; $i++) {                             // edge from V[i] to V[i+1]
    if ( (($y[$i] <= $y0) && ($y[$i+1] > $y0))          // an upward crossing
      || (($y[$i] > $y0) && ($y[$i+1] <= $y0)) ) {      // a downward crossing
      // compute the actual edge-ray intersect x-coordinate
      $vt= ($y0 - $y[$i]) / ($y[$i+1] - $y[$i]);
//                                                 display("$i:vt=$vt, $x0 ? ".($x[$i] + $vt * ($x[$i+1] - $x[$i])));
      if ( $x0 < ($x[$i] + $vt * ($x[$i+1] - $x[$i])) ) { // P.x < intersect
        $cn++;                                          // a valid crossing of y=P.y right of P.x
      }
    }
  }
//                                                 display("cross($x0,$y0,X,Y)=$cn");
  return $cn & 1;                                         // 0 if even (out), and 1 if odd (in)
}
# --------------------------------------------------------------------------------------- mail2 mapa
# ASK
# získání seznamu souřadnic bydlišť adresátů mailistu
function mail2_mapa($id_mailist) {  trace();
  $psc= $obec= array();
  // dotaz
  $gq= select("sexpr","mailist","id_mailist=$id_mailist");
//                                         display($gq);
  $gq= str_replace('&gt;','>',$gq);
  $gq= str_replace('&lt;','<',$gq);
  $gr= @pdo_qry($gq);
  if ( !$gr ) {
    $html= pdo_error()."<hr>".nl2br($gq);
    goto end;
  }
  else while ( $gr && ($g= pdo_fetch_object($gr)) ) {
    // najdeme použitá PSČ
    $p= $g->_psc;
    list($prijmeni,$jmeno)= explode(' ',$g->_name);
    $psc[$p].= "$prijmeni ";
    $obec[$p]= $obec[$p] ?: $g->_obec;
  }
//                                         debug($psc);
end:
  return mapa2_psc($psc,$obec); // vrací (object)array('mark'=>$marks,'n'=>$n,'err'=>$err);
}
# --------------------------------------------------------------------------------------- geo remove
// zapíše osobě geolokaci z mapy.cz (kopie GPS)
// 50.6176686N, 15.6191003E
function geo_manual($ido,$gps) { 
  $msg= "";
  $m= null;
  $ok= preg_match("/([0-9\.]+)N,\s*([0-9\.]+)E/",$gps,$m);
  if ($ok) {
    if (select('id_osoba','osoba_geo',"id_osoba=$ido")) {
      query("UPDATE osoba_geo SET lat='$m[1]',lng='$m[2]',stav=1 ");
      $msg= "GPS upraveno";
    }
    else {
      query("INSERT INTO osoba_geo (id_osoba,lat,lng,stav) VALUES ($ido,'$m[1]','$m[2]',1)");
      $msg= "GPS vloženo";
    }
  }
  return $ok ? $msg : 'nepochopená forma GPS';
}
# --------------------------------------------------------------------------------------- geo remove
// zkusí zrušit geo-informaci dané osoby, vrací 2 pokud bylo co rušit
function geo_remove($ido) { 
  $ok= query("DELETE FROM osoba_geo WHERE id_osoba=$ido");
  return $ok+1;
}
# -------------------------------------------------------------------------------------- geo refresh
// pokusí se zjistit dané osobě polohu a zapsat ji
// vrátí {ok:0/1
function geo_refresh($ido) { 
  $geo= (object)array('ok'=>0,'note'=>'');
  $x= (object)array('todo'=>1,'done'=>0,'last_id'=>0,'par'=>(object)array(
      'y'=>'+',
      'par'=>(object)array('cond'=>"id_osoba=$ido")));
  $y= geo_fill($x); // error, msg, note
  debug($y,"výsledek geo_fill pro $ido");
  $geo->ok= isset($y->error) ? 0 : 1;
  $geo->note= $y->note;
  $geo->warning= $y->warning;
  return $geo;
}
# ----------------------------------------------------------------------------------------- geo fill
// y je paměť procesu, který bude krok za krokem prováděn lokalizaci adres
// y.todo - celkový počet kroků
// y.done - počet provedených kroků 
// y.error = text chyby, způsobí konec
function geo_fill ($y) { debug($y,'geo_fill');
  if ( !$y->todo ) {
    // pokud je y.todo=0 zjistíme kolik toho bude
    $y->todo= select('COUNT(*)',
        'osoba AS o LEFT JOIN osoba_geo USING (id_osoba) 
          LEFT JOIN tvori AS t USING (id_osoba) LEFT JOIN rodina AS r USING (id_rodina)',
        "o.deleted='' AND o.umrti=0 AND IF(o.adresa,o.psc!='',r.psc!='') AND IFNULL(stav,0)!=-99
          AND IF(o.adresa,o.stat,r.stat) IN ('','CZ') AND IF(adresa=0,t.role IN ('a','b'),1)
          AND {$y->par->par->cond} ORDER BY id_osoba");
    $y->last_id= 0;
//    display("TODO {$y->todo}");
  }
  if ( $y->error ) { goto end; }
  if ( $y->done >= $y->todo ) { $y->done= $y->todo; $y->msg= 'konec+'; goto end; }
  // vlastní proces
  if ( $y->par->y!=='-' ) {
    list($ido,$stav)= select('id_osoba,IFNULL(stav,0)',
        'osoba AS o LEFT JOIN osoba_geo USING (id_osoba) 
          LEFT JOIN tvori AS t USING (id_osoba) LEFT JOIN rodina AS r USING (id_rodina)',
        "o.deleted='' AND o.umrti=0 AND IF(o.adresa,o.psc!='',r.psc!='') AND IFNULL(stav,0)!=-99
          AND IF(o.adresa,o.stat,r.stat) IN ('','CZ') AND IF(adresa=0,t.role IN ('a','b'),1)
          AND id_osoba>{$y->last_id} AND {$y->par->par->cond} ORDER BY id_osoba LIMIT 1");
    if (!$ido) goto end; 
    $y->last_id= $ido;
    $idox= tisk2_ukaz_osobu($ido);
    if ($stav<=0) {
      $geo= geo_get_osoba($ido);
      debug($geo,'po geo_get_osoba');
      geo_set_osoba($ido,$geo);
      if ($geo->error) {
        $lineadr= urlencode($geo->address);
        $url= "http://ags.cuzk.cz/arcgis/rest/services/RUIAN/Vyhledavaci_sluzba_nad_daty_RUIAN/"
            . "MapServer/exts/GeocodeSOE/findAddressCandidates?SingleLine={$lineadr}&magicKey="
            . "&outSR=&maxLocations=&outFields=&searchExtent=&f=html";
//        $mapycz= "http://api4.mapy.cz/geocode?query=$geo->address";
        $y->note= "{$geo->error} OSOBA $idox 
          <a href='{$geo->url}' target='url'>VDP ČÚZK</a>
          <a href='$url' target='url'>AGS ČÚZK</a> 
          <!-- a href='$mapycz' target='url'>mapy.</a --> 
          {$geo->address}
        ";
      }
      elseif ($y->par->y=='+') {
        debug($geo->adresa);
        $y->note= "byla zadána adresa <br> {$geo->address} <br> mám opravit na <br> "
            .implode(', ',$geo->adresa).' ?';
        $y->warning= "rozeznaná adresa je: ".implode(', ',$geo->adresa);
      }
      else {
        $y->note= "+ OSOBA $idox {$geo->address} ==> ".implode(', ',$geo->adresa);
      }
    }
    else {
      $y->note= "- OSOBA $idox";
    }
  }
  $y->done++;
  // zpráva
  $y->msg= $y->done==$y->todo ? 'konec' : "ještě ".($y->todo-$y->done); 
//  $y->error= "au";
end:  
  return $y;
}
# ------------------------------------------------------------------------------------ geo set_osoba
# zapiš polohu dané osobě
function geo_set_osoba($ido,$geo) {  trace();
  if ($geo->wgs) {
    $kodm= isset($geo->kod_mista) ? $geo->kod_mista : 0;
    $kodo= isset($geo->kod_obce) ? $geo->kod_obce : 0;
    query("REPLACE osoba_geo (id_osoba,kod_misto,kod_obec,lat,lng,stav) 
      VALUE ($ido,$kodm,$kodo,'{$geo->wgs->lat}','{$geo->wgs->lng}',1)");
  }
  else 
    query("REPLACE osoba_geo (id_osoba,stav) 
      VALUE ($ido,-{$geo->error})");

}
# ----------------------------------------------------------------------------------- geo set_rodina
# zapiš polohu dané rodině
function geo_set_rodina($idr,$geo) {  trace();
  if ($geo->wgs) {
    $kodm= isset($geo->kod_mista) ? $geo->kod_mista : 0;
    $kodo= isset($geo->kod_obce) ? $geo->kod_obce : 0;
    query("REPLACE rodina_geo (id_rodina,kod_misto,kod_obec,lat,lng,stav) 
      VALUE ($idr,$kodm,$kodo,'{$geo->wgs->lat}','{$geo->wgs->lng}',1)");
  }
  else 
    query("REPLACE rodina_geo (id_rodina,stav) 
      VALUE ($idr,-{$geo->error})");

}
# ------------------------------------------------------------------------------------ geo get_osoba
# určí polohu podle RUIAN podle údajů v OSOBA nebo podle zadané adresy
function geo_get_osoba($ido,$adr='') {  trace();
  display("------------------------------------------------------ $ido");
  $geo= (object)array('full'=>"neznámá adresa v RUIAN",'ok'=>0);
  $rc= pdo_qry("SELECT id_osoba,adresa,
          IF(adresa,o.ulice,r.ulice) AS ulice,
          IF(adresa,o.psc,r.psc) AS psc, 
          IF(adresa,o.obec,r.obec) AS obec,
          okres.nazev AS nazokr
        FROM osoba AS o
        LEFT JOIN tvori AS t USING (id_osoba)
        LEFT JOIN rodina AS r USING (id_rodina)
        LEFT JOIN `#psc` AS p ON p.psc=IF(adresa,o.psc,r.psc)
        LEFT JOIN `#okres` AS okres USING (kod_okres) 
        WHERE IF(adresa,o.stat,r.stat) IN ('','CZ') 
          AND IF(adresa=0,t.role IN ('a','b'),1) AND id_osoba=$ido ");
  if ( !$rc ) {
    $geo->ok= 0;
    $geo->error= 9;
    goto end;
  }
  $c= pdo_fetch_object($rc);
  if ( !$c->id_osoba ) {
    $geo->ok= 0;
    $geo->error= 8;
    goto end;
  }
  $m= null;
  $ma_cislo= preg_match('~^(.*)\s*(\d[\w\/]*)\s*$~uU',$c->ulice,$m);
  if ($ma_cislo) {
    $ulice= $m[1];
    $cislo= $m[2];
  }
  else {
    $ulice= $c->ulice;
    $cislo= '';
  }
  $obec= $c->obec;
  $psc= $c->psc;
  $adr= (object)array('ulice'=>$ulice,'cislo'=>$cislo,'obec'=>$obec,'psc'=>$psc);
//  debug($adr);
  $geo= ruian_adresa((object)array('ulice'=>$ulice,'cislo'=>$cislo,'obec'=>$c->obec,'psc'=>$c->psc));
  $geo->address= "$ulice $cislo, $psc $obec";
  $geo->full= isset($geo->adresa) ? "{$geo->adresa[0]}, {$geo->adresa[1]}, {$geo->adresa[2]}" : '';
end:
//                                                        debug($geo);
  display("------------------------------------------------------ $ido END");
  return $geo;
}
# ----------------------------------------------------------------------------------- geo get_rodina
# určí polohu podle RUIAN podle údajů v RODINA nebo podle zadané adresy
function geo_get_rodina($idr,$adr='') {  trace();
  display("------------------------------------------------------ $ido");
  $geo= (object)array('full'=>"neznámá adresa v RUIAN",'ok'=>0);
  $rc= pdo_qry("SELECT id_rodina,r.ulice,r.psc,r.obec,
          okres.nazev AS nazokr
        FROM rodina AS r
        LEFT JOIN `#psc` AS p ON p.psc=r.psc
        LEFT JOIN `#okres` AS okres USING (kod_okres) 
        WHERE r.stat IN ('','CZ') AND id_rodina=$idr ");
  if ( !$rc ) {
    $geo->ok= 0;
    $geo->error= 9;
    goto end;
  }
  $c= pdo_fetch_object($rc);
  if ( !$c->id_rodina ) {
    $geo->ok= 0;
    $geo->error= 8;
    goto end;
  }
  $m= null;
  $ma_cislo= preg_match('~^(.*)\s*(\d[\w\/]*)\s*$~uU',$c->ulice,$m);
  if ($ma_cislo) {
    $ulice= $m[1];
    $cislo= $m[2];
  }
  else {
    $ulice= $c->ulice;
    $cislo= '';
  }
  $obec= $c->obec;
  $psc= $c->psc;
  $adr= (object)array('ulice'=>$ulice,'cislo'=>$cislo,'obec'=>$obec,'psc'=>$psc);
//  debug($adr);
  $geo= ruian_adresa((object)array('ulice'=>$ulice,'cislo'=>$cislo,'obec'=>$c->obec,'psc'=>$c->psc));
  $geo->address= "$ulice $cislo, $psc $obec";
  $geo->full= isset($geo->adresa) ? "{$geo->adresa[0]}, {$geo->adresa[1]}, {$geo->adresa[2]}" : '';
end:
//                                                        debug($geo);
  display("------------------------------------------------------ $ido END");
  return $geo;
}
