<?php
# =============================================================================> . geos = OpenStreet
# ------------------------------------------------------------------------------------- geos prehled
// přehled tabulek geo
function geos_prehled($cmd) { 
  switch ($cmd) {
    case 'again':
      query("UPDATE osoba_geo  SET lat=0,lng=0,stav=0 WHERE stav<0");
      query("UPDATE rodina_geo SET lat=0,lng=0,stav=0 WHERE stav<0");
      break;
    case 'delete':
      query("DELETE FROM osoba_geo  WHERE lat=0 OR lng=0 OR stav<=0");
      query("DELETE FROM rodina_geo WHERE lat=0 OR lng=0 OR stav<=0");
      break;
  }
  $o_ok= select1('COUNT(*)','osoba_geo',"stav>0");
  $o_td= select1('COUNT(*)','osoba_geo',"stav=0");
  $o_ko= select1('COUNT(*)','osoba_geo',"stav<0");
  $r_ok= select1('COUNT(*)','rodina_geo',"stav>0");
  $r_ko= select1('COUNT(*)','rodina_geo',"stav<0");
  $html= "<b>tabulka osoba_geo</b> má $o_ok lokalizovaných osob, $o_ko chyb lokalizace, $o_td připraveno k opravě"
      . "<br><b>tabulka rodina_geo</b> má $r_ok lokalizovaných rodin, $r_ko chyb lokalizace";
  return $html;
}
# -------------------------------------------------------------------------------------- geos remove
// zkusí zrušit geo-informaci dané osoby, vrací 2 pokud bylo co rušit
function geos_remove($ido) { 
  $ok= query("DELETE FROM osoba_geo WHERE id_osoba=$ido");
  return $ok+1;
}
# ------------------------------------------------------------------------------------- geos refresh
# nalezení polohy osoby, její vrácení a zápis, pokud neleží daleko od polohy podle psč
# vstup
#   pokud je ctx číslo je to id_osoba  foplní potřebný kontext
#   {ido,idr,adresa,ulice,psc,obec,stat} adresa=1 znamená osobní adresu jinak rodinnou
# navrací obohacený kontext o
#   ok: 0=nenalezeno - poloha zrušena, 1=uloženo, 2=detekováno ale neuloženo - mimo psč
#   seek: hledaná (upravená) adresa
#   found: nalezená adresa
#   lat, lon: poloha adresy nebo poloha psč nebo 0
#   rect: oblast psč (jen pokud ok=2)
function geos_refresh($ctx) {
  if (is_numeric($ctx)) {
    $ctx= pdo_fetch_object(pdo_qry(
     "SELECT o.id_osoba as ido,r.id_rodina as idr,o.adresa,
        IF(adresa=1,o.ulice,r.ulice) AS ulice,
        IF(adresa=1,o.psc,r.psc) AS psc,
        IF(adresa=1,o.obec,r.obec) AS obec,
        IF(adresa=1,o.stat,r.stat) AS stat
      FROM osoba AS o
        LEFT JOIN (
          SELECT id_osoba, id_rodina, role, id_tvori
            FROM (SELECT t.*,ROW_NUMBER() OVER (PARTITION BY t.id_osoba ORDER BY t.role) AS rn FROM tvori t) x
            WHERE x.rn = 1
          ) AS ot ON ot.id_osoba = o.id_osoba
        LEFT JOIN rodina AS r USING (id_rodina)
      WHERE o.id_osoba=$ctx"));
  }
  $ctx->ok= 0;
  $ctx->lat= 0;
  $ctx->lon= 0;
  $ctx->found= '';
  $ctx->rect= '';
  $x= geocode_nominatim($ctx);
  $ctx->seek= $x->seek;
  if ($x->lat) {
    $ctx->found= "$x->class $x->type $x->name $x->display_name";
    $ctx->lat= $x->lat;
    $ctx->lon= $x->lon;
    $b= geocode_nominatim("CZ$ctx->psc");
    if ($b->lat) {
      // je $x uvnitř obdélníku kolem polohy psč?
      $dlat= 0.03; $dlon= 0.05;
      $ctx->rect= ($b->lat+$dlat).','.($b->lon-$dlon).';'.($b->lat-$dlat).','.($b->lon+$dlon);
      if (abs($x->lat - $b->lat)<$dlat && abs($x->lon - $b->lon)<$dlon) {
        // ano je uvnitř => zápis do tabulky osoba_geo nebo rodina_geo, stav=1
        geos_manual($ctx->adresa?$ctx->ido:$ctx->idr,$x->lat,$x->lon,$ctx->adresa?'osoba':'rodina',1);
        $ctx->ok= 1;
      }
      else 
        $ctx->ok= 2;
    }
  }
  return $ctx;
}
# ----------------------------------------------------------------------------------------- geo fill
// y je paměť procesu, který bude krok za krokem prováděn lokalizaci adres
// y.par.par.cond - omezení na tabulku osoba WHERE
// y.par.par.have - omezení na tabulku osoba HAVING
// y.par.par.corr - pouze pro stav=0
// y.todo - celkový počet kroků - omezený na MAX=100
//        - pro MAX=-1 pouze spočítá počet kroků a neprovede ten první
// y.done - počet provedených kroků 
// y.error = text chyby, způsobí konec
function geos_fill ($y,$MAX= 100) { //debug($y,'geos_fill');
  $AND= isset($y->par->par->cond) ? "AND {$y->par->par->cond}" : ''; 
  $having= isset($y->par->par->have) ? "HAVING {$y->par->par->have}" : ''; 
  $go_stav= isset($y->par->par->corr) ? "go.stav" : "IFNULL(go.stav,0)";
  $gr_stav= isset($y->par->par->corr) ? "gr.stav" : "IFNULL(gr.stav,0)";
  $sql_zbyva= "id_osoba AS ido,IF(adresa=1,'',id_rodina) AS idr,adresa,
      IF(adresa=1,o.ulice,r.ulice) AS ulice,
      IF(adresa=1,o.psc,r.psc) AS psc,
      IF(adresa=1,o.obec,r.obec) AS obec,
      IF(adresa=1,o.stat,r.stat) AS stat,
      IF(kontakt=1,o.email,r.emaily) AS email
    FROM osoba AS o
      LEFT JOIN osoba_geo AS go USING (id_osoba)
      LEFT JOIN tvori USING (id_osoba)
      LEFT JOIN rodina AS r USING (id_rodina)
      LEFT JOIN rodina_geo AS gr USING (id_rodina)
    WHERE o.deleted='' AND o.umrti=0 $AND
      AND IF(o.adresa=1,o.psc!='' AND IFNULL(go.lat,0)=0 AND $go_stav=0
        ,IFNULL(r.psc,'')!='' AND IFNULL(gr.lat,0)=0 AND $gr_stav=0 AND role IN ('a','b'))
    GROUP BY IF(adresa=1,id_osoba,id_rodina) $having
    ORDER BY prijmeni,jmeno
    ";
  if ( !$y->todo ) {
    // pokud je y.todo=0 zjistíme kolik toho bude
    list($todo)= select("COUNT(*) FROM (SELECT $sql_zbyva) AS ch");
    // a pro MAX=-1 tento počet jen vrátíme
    if ($MAX==-1) {
      $y->todo= $todo;
      goto end;
    }
    $y->todo= min($todo,$MAX);
    $y->last_id= 0;
//    display("TODO {$y->todo}");
  }
  if ( $y->error ) { goto end; }
  if ( $y->done >= $y->todo ) { $y->done= $y->todo; $y->msg= 'konec+'; goto end; }
  // ------------------------------- vlastní proces
  if ( $y->par->y!=='-' ) {
    $x= pdo_fetch_object(pdo_qry("SELECT CONCAT(jmeno,' ',prijmeni) AS jmeno,$sql_zbyva LIMIT 1"));
//    debug($x,">geos_refresh");
    if (!$x->ido) goto end; 
    $y->last_id= $x->ido;

    $g= geos_refresh($x);
    $oks= [0=>'---',1=>'OK',2=>'??? daleko'];
    display("$x->jmeno: ido=$x->ido, idr=$x->idr {$oks[$g->ok]} ... $g->seek");
//    debug($g,"geos_refresh>");
    
    // pro ok=1 zápis proběhl v geos_refresh
    // jinak zapíšeme polohu 0,0 a stav=-5
    if ($g->ok!=1) 
      geos_manual($x->adresa?$x->ido:$x->idr,0,0,$x->adresa?'osoba':'rodina',-5);
  }
  $y->done++;
  // zpráva
  $y->msg= $y->done==$y->todo ? 'konec' : "ještě ".($y->todo-$y->done); 
//  $y->error= "au";
end:  
  return $y;
}
# --------------------------------------------------------------------------------------- geo manual
// zapíše resp. opraví souřadnice v tabulce osoba_geo resp. do rodina_geo
// stav nastaví na 2
function geos_manual($id,$lat,$lon,$table,$stav) { 
  $msg= "";
  if (select("id_$table","{$table}_geo","id_$table=$id")) {
    query("UPDATE {$table}_geo SET lat='$lat',lng='$lon',stav=$stav WHERE id_$table=$id");
    $msg= "GPS upraveno v $table.$id";
  }
  else {
    query("INSERT INTO {$table}_geo (id_$table,lat,lng,stav) VALUES ($id,'$lat','$lon',$stav)");
    $msg= "GPS vloženo do $table.$id";
  }
  return $msg;
}
# ==========================================================================================> . ...
# --------------------------------------------------------------------------------------- akce2 mapa
# získání seznamu souřadnic bydlišť účastníků akce 
# s případným filtrem - např. bez pečounů: pobyt.funkce!=99
function akce2_mapa($akce,$filtr='') {  trace();
  global $ezer_version;
  // dotaz
  $psc= $obec= array();
  $AND= $filtr ? " AND $filtr" : '';
  $qo=  "
    SELECT prijmeni,adresa,REPLACE(psc, ' ', '') AS psc,obec,
      (SELECT MIN(CONCAT(role,RPAD(REPLACE(psc, ' ', ''),5,' '),'x',obec))
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
  $ret= mapa2_psc($psc,$obec,0,$icon); // vrací (object)array('mark'=>$marks,'n'=>$n,'err'=>$err);
//  debug($ret);
  return $ret;
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
      $qs= "SELECT psc,lat,lng AS lon FROM psc_axy WHERE psc='$p'";
      $rs= pdo_qry($qs);
      if ( $rs && ($s= pdo_fetch_object($rs)) ) {
        $n++;
        $o= isset($obec[$p]) ? $obec[$p] : $p;
        $title= str_replace(',','',"$o:$tit");
        $id= $psc_as_id ? $p : $n;
        if ( is_array($icon) )
          $ic= ",{$icon[$p]}";
        $marks.= "{$del}$id,{$s->lat},{$s->lon},$title$ic"; $del= ';';
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
function mapa2_psc_set($psc,$latlon) {  trace();
  list($lat,$lon)= preg_split("/,\s*/",$latlon);
  if ( !$psc || !$lat || !$lon ) goto end;
  $ex= select("COUNT(*)",'psc_axy',"psc='$psc'");
  if ($ex) {
    query("UPDATE psc_axy SET lat='$lat',lng='$lon' WHERE psc='$psc'");
  }
  else {
    query("INSERT INTO psc_axy (psc,lat,lng) VALUE ('$psc','$lat','$lon')");
  }
end:  
  return 1;
}
# ------------------------------------------------------------------------------==> .. mapa2 ctverec
# ASK
# čtverec kolem středu +- $dist (km) na všechny strany
# vrací objekt {
#   err:  0/1
#   rect: omezující obdélník jako SW;NE
#   poly: omezující polygon zmenšený o $perc % oproti obdélníku
function mapa2_ctverec($lat0,$lon0,$dist,$perc) {  trace();
  $ret= (object)array();
  // čtverec  SW;NE
  $delta_lat= 0.0089913097;
  $delta_lon= 0.0137464041;
  $N= $lat0-$dist*$delta_lat;
  $S= $lat0+$dist*$delta_lat;
  $W= $lon0-$dist*$delta_lon;
  $E= $lon0+$dist*$delta_lon;
  $ret->rect= "$N,$W;$S,$E";
  // polygon
  $d_lat= abs($N-$S)*$perc/300;
  $d_lon= abs($W-$E)*$perc/100;
  $N= $N-$d_lat;
  $S= $S+$d_lat;
  $W= $W+$d_lon;
  $E= $E-$d_lon;
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
    $hpoloha= "IFNULL(lat,0) BETWEEN $nw[0] AND $se[0]
           AND IFNULL(lon,0) BETWEEN $se[1] AND $nw[1]";
  }
  else {
    $poloha= "lat!=0 AND lng!=0";
  }
  $qo= in_array($mode,array('o','h','m')) ? "
    SELECT id_osoba,
      IF(o.adresa=1,IFNULL(go.lat,0),IFNULL(gr.lng,0)) AS geo,
      IF(o.adresa=1,IF(ISNULL(go.lat),a.lat,go.lat),IF(ISNULL(gr.lat),a.lat,gr.lat)) AS lat,
      IF(o.adresa=1,IF(ISNULL(go.lng),a.lng,go.lng),IF(ISNULL(gr.lng),a.lng,gr.lng)) AS lon
    FROM osoba AS o
      LEFT JOIN osoba_geo AS go USING (id_osoba)
      LEFT JOIN tvori AS t USING (id_osoba)
      LEFT JOIN rodina AS r USING (id_rodina)
      LEFT JOIN rodina_geo AS gr USING (id_rodina)
      LEFT JOIN psc_axy AS a ON a.psc=IF(o.adresa,o.psc,r.psc)
    WHERE id_osoba IN ($ids)
    GROUP BY id_osoba HAVING $hpoloha
    ORDER BY role -- LIMIT 1  "
   : // r|f
     "SELECT id_rodina,IFNULL(g.lat,0),
        IF(ISNULL(g.lat),a.lat,g.lat) AS lat,IF(ISNULL(g.lng),a.lng,g.lng) AS lon
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
      while ( $ro && list($id,$geo,$lat,$lon)= pdo_fetch_row($ro) ) {
        $ret->ids.= "$del$id"; 
        $color= $geo ? 'green' : 'gold';
        $ret->marks.= "$semi$id,$lat,$lon,$id,./ezer$ezer_version/client/img/circle_{$color}_15x15.png,7,7"; 
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
  $lat_min= $lon_min= 999;
  $lon_max= $lon_max= 0;
  $x= $y= array();
  foreach ( explode(';',$poly) as $bod) {
    list($lat,$lon)= explode(',',$bod);
    $lat_min= min($lat_min,$lat);
    $lat_max= max($lat_max,$lat);
    $lon_min= min($lon_min,$lon);
    $lon_max= max($lon_max,$lon);
    $x[]= floatval($lat); 
    $y[]= floatval($lon);
  }
  // uzavři polygon pokud není 
  $n= count($x);
  if ( $x[0]!=$x[n-1] || $y[0]!=$y[n-1] ) {
    $x[$n]= $x[0];
    $y[$n]= $y[0];
  }
  // dotaz na ohraničující obdélník
  $poloha= "lat BETWEEN $lat_min AND $lat_max AND lng BETWEEN $lon_min AND $lon_max";
  $qo= "SELECT psc, lat, lng AS lon
      FROM psc_axy 
      WHERE $poloha ";
  $ro= pdo_qry($qo);
  if ( $ro ) {
    $ret->pocet= pdo_num_rows($ro);
    $del= '';
    while ( $ro && list($psc,$lat,$lon)= pdo_fetch_row($ro) ) {
      // zjistíme, zda leží uvnitř polygonu
      if ( maps_poly_cross($lat,$lon,$x,$y) ) {
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
  $lat_min= $lon_min= 999;
  $lon_max= $lon_max= 0;
  $x= $y= array();
  foreach ( explode(';',$poly) as $bod) {
    list($lat,$lon)= explode(',',$bod);
    $lat_min= min($lat_min,$lat);
    $lat_max= max($lat_max,$lat);
    $lon_min= min($lon_min,$lon);
    $lon_max= max($lon_max,$lon);
    $x[]= floatval($lat); 
    $y[]= floatval($lon);
  }
  // uzavři polygon pokud není 
  $n= count($x);
  if ( $x[0]!=$x[n-1] || $y[0]!=$y[n-1] ) {
    $x[$n]= $x[0];
    $y[$n]= $y[0];
  }
  // dotaz na ohraničující obdélník
  $poloha= "IF(ISNULL(g.lat),a.lat,g.lat) BETWEEN $lat_min AND $lat_max 
    AND IF(ISNULL(g.lng),a.lng,g.lng) BETWEEN $lon_min AND $lon_max";
  $qo= in_array($mode,array('o','h','m')) 
   ? "SELECT id_osoba,IFNULL(g.lat,0),
        IF(ISNULL(g.lat),a.lat,g.lat) AS lat,IF(ISNULL(g.lng),a.lng,g.lng) AS lon
      FROM osoba AS o
      LEFT JOIN osoba_geo AS g USING (id_osoba)
      LEFT JOIN tvori AS t USING (id_osoba)
      LEFT JOIN rodina AS r USING (id_rodina)
      LEFT JOIN psc_axy AS a ON a.psc=IF(o.adresa,o.psc,r.psc)
      WHERE id_osoba IN ($ids) AND $poloha "
   : "SELECT id_rodina,IFNULL(g.lat,0),
        IF(ISNULL(g.lat),a.lat,g.lat) AS lat,IF(ISNULL(g.lng),a.lng,g.lng) AS lon
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
      while ( $ro && list($id,$geo,$lat,$lon)= pdo_fetch_row($ro) ) {
        // zjistíme, zda leží uvnitř polygonu
        if ( maps_poly_cross($lat,$lon,$x,$y) ) {
          $ret->ids.= "$del$id"; 
          $color= $geo ? 'green' : 'gold';
          $ret->marks.= "$semi$id,$lat,$lon,$id,./ezer$ezer_version/client/img/circle_{$color}_15x15.png,7,7"; 
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
