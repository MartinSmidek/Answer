<?php # (c) 2009-2015 Martin Smidek <martin@smidek.eu>
/** ======================================================================================== mapy.cz */
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
//      debug($geo,'po geo_get_osoba');
      geo_set_osoba($ido,$geo);
      if ($geo->error) {
        $lineadr= urlencode($geo->address);
        $url= "http://ags.cuzk.cz/arcgis/rest/services/RUIAN/Vyhledavaci_sluzba_nad_daty_RUIAN/"
            . "MapServer/exts/GeocodeSOE/findAddressCandidates?SingleLine={$lineadr}&magicKey="
            . "&outSR=&maxLocations=&outFields=&searchExtent=&f=html";
        $mapycz= "http://api4.mapy.cz/geocode?query=$geo->address";
        $y->note= "{$geo->error} OSOBA $idox 
          <a href='{$geo->url}' target='url'>VDP ČÚZK</a>
          <a href='$url' target='url'>AGS ČÚZK</a> 
          <a href='$mapycz' target='url'>mapy.</a> 
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
/** ==========================================================================================> AKCE */
# ----------------------------------------------------------------------------------- akce_ucastnici
# vrátí URL přihlášky pro ostrou nebo testovací databázi
function akce_prihlaska($id_akce,$cmd,$par='') {
  global $answer_db;
  $prihlaska= 
      $answer_db=='ezer_db2'      ? 'prihlaska.php' : (
      $answer_db=='ezer_db2_test' ? 'prihlaska.php'   : '???');
  $goal= "$prihlaska?akce=$id_akce$par";
  $url= "{$_SERVER['REQUEST_SCHEME']}://{$_SERVER['HTTP_HOST']}/$goal";
  switch ($cmd) {
    case 'a': $res= "<a href='$url' target='pri'>$goal</a>"; break;
  }
  return $res;
}
# ----------------------------------------------------------------------------------- akce_ucastnici
# import
function akce_ucastnici($akce,$cmd,$par=null) {
  $ret= (object)array('html'=>'');
  switch($cmd) {
//    case 'survey':
//      $sum= (object)array('mrop'=>0,'firm'=>0,'50+'=>0,'50-'=>0);
//      $xs=pdo_qry("
//        SELECT iniciace,firming,
//          ROUND(DATEDIFF(datum_od,narozeni)/365.2425) AS _vek
//        FROM pobyt 
//        JOIN spolu USING (id_pobyt)
//        JOIN osoba USING (id_osoba)
//        JOIN akce ON id_akce=id_duakce
//        WHERE id_akce=$akce AND funkce IN (0,1,2)
//      "); 
//      while ($xs && (list($mrop,$firm,$vek)=pdo_fetch_row($xs))) {
//        if ($mrop) $sum->mrop++;
//        if ($firm) $sum->firm++;
//        if ($vek>50) $sum->{'50+'}++; else $sum->{'50-'}++;
//      }
//      debug($sum);
//      break;
    case 'matrix': // ------------------------------------------------------
      $data= $jmena= array();
      $check= array(1,$par->jine,$par->muzi,$par->mrop,$par->firm);
      for ($i=0; $i<=2; $i++) {
        $data[$i]= array();
        $jmena[$i]= array();
        for ($j=0; $j<=4; $j++) {
          $data[$i][$j]= $check[$j] ? 0 : '-';
          $jmena[$i][$j]= array();
        }
      }
      $org= select('access','akce',"id_duakce=$akce");
      $os=pdo_qry("SELECT id_osoba,funkce IN (1,2),prijmeni
        FROM pobyt JOIN spolu USING (id_pobyt) JOIN osoba USING (id_osoba) 
        WHERE id_akce=$akce AND funkce IN (0,1,2) AND s_role=1
        -- AND id_osoba IN (5877,18653,21586,5861,2225)
        -- AND id_osoba IN (23149,11849)
      "); 
      while ($os && (list($ido,$vps,$jmeno)=pdo_fetch_row($os))) {
        $xs=pdo_qry("
          SELECT 
            SUM(IF(o.firming,1,0)) AS _firm,
            SUM(IF(o.iniciace,1,0)) AS _mrop,
            SUM(IF(statistika IN (1,2,3,4,5),1,0)) AS _muzi,
               SUM(IF(druh IN (1,2,3,17,18),0,1)) AS _jina, -- 1,
            -- SUM(IF(druh IN (1,2) AND funkce IN (1,2),1,0)) AS _vps,
            SUM(IF(druh IN (1,2) AND funkce IN (0),1,0)) AS _ms,
            GROUP_CONCAT(IF(sex=2 AND (statistika>0 OR firm OR mrop),
              CONCAT(nazev,'/',YEAR(datum_od),' '),'') SEPARATOR '') AS _zena
          FROM pobyt 
          JOIN spolu AS s USING (id_pobyt) 
          JOIN osoba AS o USING (id_osoba) 
          JOIN akce AS a ON id_akce=id_duakce 
              AND IF(FLOOR(IF(MONTH(o.narozeni),DATEDIFF(a.datum_od,o.narozeni)/365.2425,
                YEAR(a.datum_od)-YEAR(o.narozeni)))<18,0,1)
          WHERE
            zruseno=0 AND spec=0 AND 
            id_osoba=$ido AND id_akce!=$akce AND funkce IN (0,1,2) AND s_role IN (0,1)
          GROUP BY id_osoba
        "); 
        list($firm,$mrop,$muzi,$jina/*,$vps*/,$ms,$zena)=pdo_fetch_row($xs);
        $i= $vps>0 ? 2 : ($ms>0 ? 1 : 0);
        if     ($check[4] && $firm) $j= 4;
        elseif ($check[3] && $mrop) $j= 3;
        elseif ($check[2] && $muzi) $j= 2;
        elseif ($check[1] && $jina) $j= 1;
        else                        $j= 0;
        $data[$i][$j]++;
        $jmena[$i][$j][]= $jmeno; // "$jmeno/$ido";
        // hlášení anomálií do trasování
//        if ($ido==8370) display("$ido ms=$ms i=$i ($firm,$mrop,$muzi,$jina,$vps,$ms,$zena)");
        if ($zena && $j) display("žena $jmeno na hradě: $zena");
      }
//      debug($jmena,"jména  pro akci pořádanou $org");
      $series= array();
      for ($i=0; $i<=2; $i++) {
        for ($j=0; $j<=4; $j++) {
          $serie= array($i,$j,$data[$i][$j]);
          $series[]= $serie;
          sort($jmena[$i][$j]);
          $jmena[$i][$j]= implode(', ',$jmena[$i][$j]);
        }
      }
      global $VPS;
      $chart= array(
          'chart' =>'heatmap',
          'colorAxis_maxColor'=>$org==1 ? '#2C8931' : ($org==2 ? '#2C4989' : '#AAAAAA'),
          'title_text' =>'účasti na jiných akcích',
          'xAxis_categories'=>array('nováčci','účastníci',"{$VPS}ky"),
          'yAxis_categories'=>array('-','jiná akce','muži,otcové','iniciace','firming'),
          'series_0_data'=>$series,
          'tooltip_data'=>$jmena
        );
//      debug($chart,"chart");
      $ret->chart= $chart;
      break;
    case 'design': // ------------------------------------------------------
      // vymaž skupiny
      query("UPDATE pobyt SET skupina=0,pokoj=0 WHERE id_akce=$akce AND funkce!=1");
      query("UPDATE pobyt SET pokoj=skupina*2-1 WHERE id_akce=$akce AND funkce=1");
      // vytvoř skupiny
      $last_skupina= 0;
      $datum= date('Y-m-d');
      $xs=pdo_qry("
        SELECT id_pobyt,funkce,skupina,
          -- ROUND(DATEDIFF('$datum',narozeni)/365.2425) AS _vek
          ROUND(IF(MONTH(narozeni),DATEDIFF('$datum',narozeni)/365.2425,YEAR('$datum')-YEAR(narozeni))) AS _vek
        FROM pobyt 
        JOIN spolu USING (id_pobyt)
        JOIN osoba USING (id_osoba)
        WHERE id_akce=$akce AND ((funkce=0 AND skupina=0) /*OR funkce=1*/)
        ORDER BY prislusnost,firming DESC,_vek DESC
      "); 
      while ($xs && (list($idp,$fce,$skup,$vek)=pdo_fetch_row($xs))) {
        if (!$skup) {
          $last_skupina= ($last_skupina % 14)+1;
          $skup= $last_skupina;
          query("UPDATE pobyt SET skupina=$skup WHERE id_pobyt=$idp");
        }
        $chata= 2*$skup-1;
        $pocet= select('COUNT(*)','pobyt',"id_akce=$akce AND pokoj=$chata");
        if ($pocet>3) {
          $chata++;
        }
        query("UPDATE pobyt SET pokoj=$chata WHERE id_pobyt=$idp");
      }
      $ret->html= 'ok';
      break;  
  }
end:    
  return $ret;
}
/** ========================================================================================> IMPORT */
# ---------------------------------------------------------------------------------------- ms_import
# import
function ms_import($cmd) {
  global $abs_root;
  return "already imported";
  $msg= '';
  $tabs= array(
//    'AKCE'    => "id_duakce,id_hlavni,nazev,misto,druh,datum_od,datum_do",
//    'POBYT'   => "id_pobyt,id_akce,i0_rodina,typ,skupina",
//    'SPOLU'   => "id_spolu,id_pobyt,id_osoba,s_role",
//    'OSOBA'   => "id_osoba,deleted,jmeno,prijmeni,sex,telefon,email,narozeni",
//    'RODINA'  => "id_rodina,nazev,psc,obec,ulice",
//    'TVORI'   => "id_tvori,id_osoba,id_rodina,role",
  );
  switch ($cmd) {
    case 'truncate':
      foreach ($tabs as $name=>$flds) {
        $tab= strtolower($name);
        query("TRUNCATE TABLE $tab");
      }
      $msg= "truncated";
      break;
    case 'insert':
      foreach ($tabs as $name=>$flds) {
        $tab= strtolower($name);
        $fullname= "$abs_root/ms2/doc/import/$name.csv";
        if ( !file_exists($fullname) ) { $msg.= "soubor $fullname neexistuje "; goto end; }
        $f= @fopen($fullname, "r");
        if ( !$f ) { $msg.= "soubor $fullname nelze otevřít"; goto end; }
        $line= fgets($f, 1000); // hlavička
        while (($line= fgets($f, 1000)) !== false) {
          $data= str_getcsv($line,';'); 
          $vals= ''; $del= '';
          for ($i= 0; $i<=substr_count($flds,','); $i++) {
            $vals.= "$del'$data[$i]'";
            $del= ',';
          }
          query("INSERT INTO $tab ($flds) VALUE ($vals)");
        }
        $msg.= "<br>$name loaded";
      }
      break;
  }
end:    
  return $msg;
}
/** ===========================================================================================> GIT */
# ----------------------------------------------------------------------------------------- git make
# provede git par.cmd>.git.log a zobrazí jej
# fetch pro lokální tj. vývojový server nepovolujeme
function git_make($par) {
  global $abs_root, $ezer_version;
  $bean= preg_match('/bean/',$_SERVER['SERVER_NAME'])?1:0;
  display("ezer$ezer_version, abs_root=$abs_root, bean=$bean");
  if ($ezer_version!='3.1') { fce_error("POZOR není aktivní jádro 3.1 ale $ezer_version"); }
  $cmd= $par->cmd;
  $folder= $par->folder;
  $lines= array();
  $msg= "";
  // nastav složku pro Git
  if ( $folder=='ezer') 
    chdir("./ezer$ezer_version");
  elseif ( $folder=='skins') 
    chdir("./skins");
  elseif ( $folder=='.') 
    chdir(".");
  else
    fce_error('chybná aktuální složka');
  // proveď příkaz Git
  $state= 0;
  $branch= $folder=='ezer' ? ($ezer_version=='3.1' ? 'master' : 'ezer3.2') : 'master';
  switch ($cmd) {
    case 'log':
    case 'status':
      $exec= "git $cmd";
      display($exec);
      exec($exec,$lines,$state);
      $msg.= "$state:$exec\n";
      break;
    case 'pull':
      $exec= "git pull origin $branch";
      display($exec);
      exec($exec,$lines,$state);
      $msg.= "$state:$exec\n";
      break;
    case 'fetch':
      if ( $bean) 
        $msg= "na vývojových serverech (*.bean) příkaz fetch není povolen ";
      else {
        $exec= "git pull origin $branch";
        display($exec);
        exec($exec,$lines,$state);
        $msg.= "$state:$exec\n";
        $exec= "git reset --hard origin/$branch";
        display($exec);
        exec($exec,$lines,$state);
        $msg.= "$state:$exec\n";
      }
      break;
  }
  // případně se vrať na abs-root
  if ( $folder=='ezer'||$folder=='skins') 
    chdir($abs_root);
  // zformátuj výstup
  $msg= nl2br(htmlentities($msg));
  $msg= "<i>Synology: musí být spuštěný Git Server (po aktualizaci se vypíná)</i><hr>$msg";
  $msg.= $lines ? '<hr>'.implode('<br>',$lines) : '';
  return $msg;
}
/** ========================================================================================> online */
# ------------------------------------------------------------------------------- update web_changes
# upraví položku pobyt.web_changes hodnotou
# 1/2 pro INSERT/UPDATE pobyt a spolu | 4/8 pro INSERT/UPDATE osoba
function update_web_changes () {
  global $answer_db;
  ezer_connect($answer_db);
  // pobyt
  $xs=pdo_qry("
    SELECT klic,op 
    FROM _track JOIN pobyt ON id_pobyt=klic 
    WHERE fld='web_zmena' AND kde='pobyt'
  "); 
  while ($xs && (list($idp,$op)=pdo_fetch_row($xs))) {
    $ch= $op=='i' ? 1 : 2;
    query("UPDATE pobyt SET web_changes=web_changes|$ch WHERE id_pobyt=$idp");
  }
  // osoba
  $xs=pdo_qry("
    SELECT klic,op,id_pobyt FROM _track 
    JOIN osoba ON id_osoba=klic 
    JOIN spolu USING (id_osoba)
    JOIN pobyt USING (id_pobyt)
    WHERE fld='web_zmena' AND kde='osoba' AND web_changes>0
  "); 
  while ($xs && (list($ido,$op,$idp)=pdo_fetch_row($xs))) {
    $ch= $op=='i' ? 4 : 8;
    query("UPDATE pobyt SET web_changes=web_changes|$ch WHERE id_pobyt=$idp");
  }
  return 1;
}
/** =========================================================================================> CHART */
# ----------------------------------------------------------------------------------==> . chart typs
# převod řetězce popis:field na {sel:'popis:index,...',typ:[field,...]}
// název:field:graf:z
function chart_typs($x) { 
  $x= preg_replace('/\s*;\s*/', ';', trim($x,"; \n\r\t\v\x00"));
  $x= preg_replace('/\s*:\s*/', ':', $x);
  $graf= $graf_tit= $graf_y= $graf_z= array();
  $sel= $del= '';
  foreach ( explode(';',$x) as $i=>$pf) {
    list($p,$y,$g,$z)= array_merge(explode(':',$pf),array_fill(0,3,''));
    $sel.= "$del$p:$i"; $del= ',';
    $graf[]= $g; $graf_tit[]= $p; $graf_y[]= $y; $graf_z[]= $z;
  }
  $ret= (object)array('sel'=>$sel,'graf'=>$graf,'y'=>$graf_y,'z'=>$graf_z,'tit'=>$graf_tit);
  debug($ret,$x);
  return $ret;
}
# ---------------------------------------------------------------------------------==> . chart akce2
# infografika údajů o LK podle db resp. pro YS podle dotazníku
# graf=line|bar|bar%|pie, x=od-do, y=vek|pocet [,z=typ-ucasti]
function chart_akce2($par) { debug($par,'chart_akce2');
  $y= (object)array('err'=>'','note'=>'');
  $chart= (object)array('chart'=>(object)array());
  $regression= 0;
  switch ($par->graf) {
    case 'spline/regression':
      $chart->chart= 'spline';
      $regression= 1;
      break;
    case 'column':
      $chart->chart= 'column';
      $chart->plotOptions= (object)array();
      $chart->plotOptions->column= (object)array('stacking'=>$par->prc ? 'percent' : 'value');
      break;
    case 'pie':
      $chart->chart= 'pie';
      break;
    default:
      $chart->chart= $par->graf;
      break;
  }
  $org= $par->org; //255;
  // názvy kategorií
  $VPS= $org==1 ? 'VPS' : 'PPS';
  $names= array(
      'deti'        => array('detiLK'=>'nezletilé děti na kurzu','detiD'=>'nezletilé děti doma'),
      'typ-ucasti'  => array('vps'=>"věk $VPS",'ucast'=>"věk účastníků bez $VPS"),
      'cirkev'      => array(''=>'neuvedeno','bez'=>'bez vyznání','kat'=>"katolíci",'eva'=>"nekatolíci"),
      '0-5'         => array('nevyužil jsem','1=velmi líbilo','2=spíše líbilo','3=přijatelné','4=spíše nelíbilo','5=velmi nelíbilo'),
      'prinos'      => array('no comment','1=Ano, velmi významně','2=Ano, částečně','3=Nevím, to se uvidí','4=Ne, nevidím změnu','5=Ne, spíše naopak'),
      '1-3'         => array(0,'ANO','stejné','NE'),
      'temata'      => array('malé děti','dospívající','matka-dítě','otec-dítě','mezigen.','duchovní','jiné'),
  );
  $temata= explode(',',"tema_male,tema_dosp,tema_matka,tema_otec,tema_mezigen,tema_duchovni,tema_jine");
  $colors= array(
      'deti'        => array('detiLK'=>'green','detiD'=>'red'),
      'typ-ucasti'  => array('vps'=>'','ucast'=>''),
      'cirkev'      => array(''=>'silver','eva'=>'#00cc00','bez'=>'orange','kat'=>'#0000ee'),
      'prinos'      => array('silver','#00cc00','#0000ee','darkorange','red','black'),
      '0-5'         => array('silver','#00cc00','#0000ee','darkorange','red','black'),
      '1-3'         => array(0,'#00cc00','silver','#0000ee'),
  );
  $notes= array(
      'deti'        => "Poznámka: jsou zahrnuty jen rodiny s nezletilými dětmi",
      'typ-ucasti'  => "",
  );
  $y->note= $notes[$par->z] ?: ' ';
  // zobrazený interval
  $chart->series= array();
  if ($par->rok=='od-do') { $od= $par->od; $do= $par->do; }
  else $od= $do= date('Y');
  // pořadí řad
  $nuly= array_fill(0,1+$do-$od,0);
  switch ("$par->y/$par->z") {
    case 'pocet/cirkev':
      $data= array('kat'=>$nuly,'eva'=>$nuly,'bez'=>$nuly,''=>$nuly);
      break;
    default:
      $data= array();
      break;
  }
//  debug($data,"data-nuly $do-$od");
  // popis os
  list($yTitle,$yMin,$yMax,$yTicks)= explode(',',$par->yaxis);
  $roky= array();
  $r= -1;
  if ($par->dotaznik) {
    // projdeme dotazník
    $hodnoceni= $par->y;
    if ($hodnoceni=='temata') {
      $data_temata= array_fill(0,6,0);
      $rp= pdo_qry(" SELECT * FROM dotaz WHERE $par->dotaznik ");
      while ( $rp && ($x= pdo_fetch_object($rp)) ) {
        foreach ($temata as $i=>$tema) {
          if ($x->$tema) $data_temata[$i]++;
        }
      }
      debug($data_temata);
      foreach ($data_temata as $i=>$pocet) {
        $name= isset($names['temata'][$i]) ? $names['temata'][$i] : $i;
        $desc= (object)array('name'=>$name,'y'=>$pocet);
//        if ($color) $desc->color= $color;
        $data[$hodnoceni][]= $desc;
      }
    }
    else {
      $rp= pdo_qry("
        SELECT $hodnoceni,COUNT(*)
        FROM dotaz
        WHERE $par->dotaznik
        GROUP BY $hodnoceni
      ");
      while ( $rp && (list($znamka,$pocet)= pdo_fetch_array($rp)) ) {
        $color= $colors[$par->z][$znamka] ? $colors[$par->z][$znamka] : null;
        $name= isset($names[$par->z][$znamka]) ? $names[$par->z][$znamka] : $znamka;
        $desc= (object)array('name'=>$name,'y'=>$pocet);
        if ($color) $desc->color= $color;
        $data[$hodnoceni][]= $desc;
      }
    }
//    debug($data);
  }
  else {
    for ($rok= $od; $rok<=$do; $rok++) {
      $ida= select('id_duakce','akce',"access&$org AND druh=1 AND zruseno=0 AND YEAR(datum_od)=$rok");
      if (!$ida) { 
        $r1= $r+1;
        display("$r1.rok ($rok) - nejsou data");
        foreach ($data as $id=>$serie) {
          array_splice($data[$id],$r1,1);
        }
        continue; 
      }
      $r++;
      $roky[$r]= $rok;
      // projdeme účastníky
      $n= 0;
      $n_vps= $n_ucast= $vek_vps= $vek_ucast= 0;
      $n_rodicu= $pocet_deti= $pocet_detiLK= 0;
      $rp= pdo_qry("
        SELECT 
          ROUND(IF(r.datsvatba,IF(MONTH(r.datsvatba),DATEDIFF(a.datum_od,r.datsvatba)/365.2425,YEAR(a.datum_od)-YEAR(r.datsvatba)),
            IF(r.svatba,YEAR(a.datum_od)-svatba,0)),1) AS _manz,
          ROUND(IF(MONTH(o.narozeni),DATEDIFF(a.datum_od,o.narozeni)/365.2425,YEAR(a.datum_od)-YEAR(o.narozeni)),1) AS _vek,
          IF(o.narozeni,1,0) AS _vek_ok,
          sex,cirkev,IF(funkce IN (1,2),1,0) AS _vps,
          (SELECT IFNULL(SUM(IF(dt.role='d',1,0)),0) FROM tvori AS dt JOIN osoba AS do USING (id_osoba)
          WHERE dt.id_rodina=r.id_rodina AND IF(MONTH(do.narozeni),DATEDIFF(a.datum_od,do.narozeni)/365.2425,
          YEAR(a.datum_od)-YEAR(do.narozeni))<18) AS _deti,
          (SELECT SUM(IF(dt.role='d',1,0)) FROM tvori AS dt JOIN spolu AS ds USING (id_osoba)
          WHERE dt.id_rodina=r.id_rodina AND ds.id_pobyt=p.id_pobyt) AS _detiLK
        FROM pobyt AS p
        JOIN akce AS a ON id_akce=id_duakce
        JOIN spolu AS s USING (id_pobyt)
        LEFT JOIN rodina AS r ON r.id_rodina=p.i0_rodina
        JOIN tvori AS t USING (id_rodina,id_osoba)
        JOIN osoba AS o USING (id_osoba)
        WHERE id_akce=$ida AND p.funkce IN (0,1,2) 
          AND t.role IN ('a','b') -- AND s_role=1 
  -- AND id_rodina IN (3329,1875)        
        GROUP BY id_osoba HAVING _vek>18
        ");
      while ( $rp && (list($manz,$vek,$vek_ok,$sex,$cirkev,$vps,$deti,$detiLK)= pdo_fetch_array($rp)) ) {
        $n+= 0.5;
        switch ("$par->y/$par->z") {
          case 'vek/typ-ucasti':
            if ($vps && $vek_ok) { $n_vps++; $vek_vps= ($vek_vps*($n_vps-1)+$vek)/$n_vps; }
            elseif ($vek_ok) { $n_ucast++; $vek_ucast= ($vek_ucast*($n_ucast-1)+$vek)/$n_ucast; }
            break;
          case 'pocet/deti':
            if ($deti) {
              $n_rodicu+= 0.5;
              $pocet_deti+= $deti/2;
              $pocet_detiLK+= $detiLK/2;
            }
            break;
          case 'pocet/cirkev':
            if (in_array($cirkev,array(0)))
              $data[''][$r]++; 
            elseif (in_array($cirkev,array(3,16)))
              $data['bez'][$r]++; 
            elseif (in_array($cirkev,array(1,13)))
              $data['kat'][$r]++;
            else
              $data['eva'][$r]++;
            break;
        }
      }
      $vek_vps= round($vek_vps,1);
      $vek_ucast= round($vek_ucast,1);
      switch ("$par->y/$par->z") {
        case 'vek/typ-ucasti':
          $data['vps'][]= $vek_vps; 
          $data['ucast'][]= $vek_ucast; 
          break;
        case 'pocet/deti':
          $data['detiD'][]= $n_rodicu ? round(($pocet_deti-$pocet_detiLK)/$n_rodicu,2) : 0; 
          $data['detiLK'][]= $n_rodicu ? round($pocet_detiLK/$n_rodicu,2) : 0; 
          break;
      }
      display("rok $rok: účastníků=$n rodičů=$n_rodicu dětí na LK=$pocet_detiLK");
    }
  }  
  foreach ($data as $id=>$serie) {
    $name= $names[$par->z][$id];
    $color= $colors[$par->z][$id] ? $colors[$par->z][$id] : null;
    $desc= (object)array('name'=>$name,'data'=>$serie);
    if ($color) $desc->color= $color;
    $chart->series[]= $desc;
    if ($regression && count($roky)>1) {
      $last_x= count($serie)-1;
      $lr= linear_regression(range(0,$last_x),$serie); $m= $lr['m']; $b= $lr['b']; 
      $desc= (object)array('name'=>"$name - trend", 
          'dashStyle'=>'Dash',
          'marker'=>(object)array('enabled'=>0),
          'data'=>array(array(0,round($b,1)),array($last_x,round($b+$m*$last_x,1))), 'color'=>'grey');
      if ($color) $desc->color= $color;
      $chart->series[]= $desc; 
    }
  }
  $chart->title= $par->title;
  switch ($chart->chart) {
    case 'column':
      $chart->tooltip= (object)array(
        'pointFormat'=>"<span>{series.name}</span>: <b>{point.y}</b> ({point.percentage:.0f}%)<br/>");
    case 'line':
    case 'spline':
      $chart->xAxis= (object)array('categories'=>$roky,
          'title'=>(object)array('text'=>'rok kurzu '));  
      $chart->yAxis= (object)array(
          'title'=>(object)array('text'=>$yTitle),
          'min'=>$yMin,'max'=>$yMax,'tickAmount'=>$yTicks);
      break;
    case 'pie':
      $chart->tooltip= (object)array(
        'pointFormat'=>"<span>{series.name}</span>: <b>{point.y}</b> ({point.percentage:.0f}%)<br/>");
      break;
  }
  $y->chart= $chart;
  debug($y);
end:
  return $y;
}
# ----------------------------------------------------------------------------------==> . chart akce
# agregace údajů o MROP, FIRMING a MS pro grafické znázornění
function chart_akce($par) { // debug($par,'chart_akce');
  $y= (object)array('err'=>'','note'=>' ');
  $org= $par->org; //255;
  $letos= date('Y');
  $mrop_vek= $mrop_joins= ''; // pro odlišení cases vek_rel a vek_abs
  $chart= $par->chart ?: (object)array();
  if (!isset($chart->series))
    $chart->series= array();
  switch ($par->type) {
    // --------------------------------------------------------------- obecné
    case 'prihlasky':
      $pro= $par->pro;
      $od= $par->od;
      $do= $par->do ?: $letos;
//      $od= 2014;
//      $do= $letos;
      $chart= (object)array('chart'=>'line');
      $chart->title= "tempo (zápisu) přihlašování na "
          .($pro=='mrop'?'MROP':($pro=='firm'?'FIRMING':($pro=='erop'?'EROP':'LK MS')));
      $akce= $pro=='ms' ? "druh=1 && access&$org" : ($pro=='erop' ? "nazev LIKE 'EROP%'" : "$pro=1");
      $funkce= $pro=='ms' ? "funkce IN (0,1,2,5,9,13)" : "funkce IN (0,1)";
      for ($rok= $od; $rok<=$do; $rok++) {
        $ida= select('id_duakce','akce',"$akce AND zruseno=0 AND YEAR(datum_od)=$rok");
        if (!$ida) continue;
        $mesic_akce= select('MONTH(datum_do)','akce',"$akce AND zruseno=0 AND YEAR(datum_od)=$rok");
        $data= array(0,0,0,0,0,0,0,0,0,0,0,0,0);
        $qp=  "SELECT id_pobyt,
             (SELECT DATE(kdy) FROM _track 
              WHERE kde='pobyt' AND klic=id_pobyt ORDER BY id_track LIMIT 1) AS mesic
          FROM pobyt JOIN akce ON id_akce=id_duakce
          WHERE id_akce='$ida' AND $funkce ";
        $rp= pdo_qry($qp);
        while ( $rp && (list($idp,$datum)= pdo_fetch_row($rp)) ) {
          if (substr($datum,0,4)==$rok)
            $data[0+substr($datum,5,2)]++;
          else
            $data[0]++;
        }
        for ($m=12; $m>$mesic_akce; $m--) {
          unset($data[$m]);
        }
        // pokud chceme integrál
        if ($par->ukaz=='celkem') {
          for ($m=1; $m<=$mesic_akce; $m++) {
            if (isset($data[$m]))
              $data[$m]+= $data[$m-1];
          }
        }
//        debug($data,"$rok");
        if ($rok==$letos) {
          for ($m= date('m'); $m<=12; $m++) {
            unset($data[$m]);
          }
        }
        $serie= (object)array('name'=>$rok,'data'=>implode(',',$data));
        $chart->series[]= $serie;
      }
      $chart->yAxis= (object)array('title'=>(object)array(
          'text'=>'počet zapsaných přihlášek'));
      $chart->xAxis= (object)array(
          'categories'=>array('dříve','leden','únor','březen','duben','květen','červen',
            'červenec','srpen','září'),//,'říjen','listopad','prosinec'),
          'title'=>(object)array('text'=>'měsíc'));
      $prihlasky= $pro=='ms'
          ? "přihlášky účastníků, VPS, hospodářů včetně náhradníků - ale  bez odhlášených a bez kněží, psychologů, ..."
          : "přihlášky účastníků - bez týmu, starších, odhlášených";
      $nejdriv= $pro=='ms' ? "od roku 2011" : "od roku 2014";
      $y->note= "<i><b>Poznámky:</b><ol>
        <li>měsíc podání přihlášky se bere podle data zápisu do Answeru
        <li>zobrazují se jen $prihlasky
        <li>graf lze zobrazit $nejdriv
        </ol>";
      break;
    // --------------------------------------------------------------- specifické pro MS
    case 'skupinky': 
      $chart->title= 'Počty skupinek v daném roce podle velikosti';
      $x= $roky= $tri= $ctyri= $divna= array();
      $ix= $je_divna= 0;
      $od= $par->od;
      $do= $par->do;
      for ($r= $od; $r<=$do; $r++) { 
        // zjistíme, jestli v daném roce by LK
        $lk= select('COUNT(*)','akce',"access&$org AND druh=1 AND zruseno=0 AND YEAR(datum_od)=$r");
        if (!$lk) continue;
        $roky[]= $r;
        $x[]= $ix++;
        $r3= $r4= $rx= 0;
        $rs= pdo_qry("SELECT COUNT(*),skupina
          FROM pobyt AS p
          JOIN akce AS a ON id_akce=id_duakce
          WHERE YEAR(datum_od)=$r AND druh=1 AND access&$org AND zruseno=0
            AND skupina>0
          GROUP BY access,skupina ");
        while ($rs && (list($paru,$skupina)= pdo_fetch_row($rs))) {
          if ($paru==3) $r3++;
          elseif ($paru==4) $r4++;
          else {
            $rx++;
            $je_divna++;
            display("clash: skupinka $skupina má $paru párů");
          }
        }
        $tri[$r]= $r3;
        $ctyri[$r]= $r4;
        $divna[$r]= $rx;
      }
      $chart->series= array(
        (object)array('type'=>'scatter','name'=>'tříparová','data'=>implode(',',$tri)),
        (object)array('type'=>'scatter','name'=>'čtyřpárová','data'=>implode(',',$ctyri))
      );
      if ($je_divna) {
        $chart->series[]=
          (object)array('type'=>'scatter','name'=>'?','data'=>implode(',',$divna),'color'=>'red');
      }
      $chart->xAxis= (object)array('categories'=>$roky,
          'title'=>(object)array('text'=>'rok kurzu '));
      $y->note= "<i><b>Poznámka:</b> 
        <br>zobrazení grafu předpokládá, že je v databázi zapsáno složení skupinek.
        <br>Pokud má nějaká skupinka jiný počet párů než tři nebo čtyři, přidá se červená série.
        </i>";
      break;
    case 'histogram': // ignoruje $par->do
      $chart->title= 'Vstup do manželství účastníků kurzu';
      $rok= $par->od;
      $dot_par= (object)array('zdroj'=>'akce','par1'=>'rok','step_man'=>1,'org'=>$org);
      $x= dot_prehled($rok,$dot_par);
      debug($x,'x');
      $data= (array)$x->know->man_vek; 
      $data= array_merge($data);
//      $data= implode(',',$man_vek);
      $chart->series_done= array(
        (object)array('type'=>'histogram','xAxis'=>1,'yAxis'=>1,'baseSeries'=>1,'z-index'=>-1),
        (object)array('type'=>'scatter','data'=>$data,'marker'=>(object)array('radius'=>1.5))        
      );
//        debug($chart); $y->err= '.'; goto end;
      $chart->yAxis= array((object)array(),(object)array());
      $chart->xAxis= array((object)array(),(object)array());
      break;
    case 'novacci':
      $chart->title= 'Délka manželství před první účastí na kurzu';
      $od= $par->od;
      $do= $par->do ?: date('Y');
      $dot_par= (object)array('zdroj'=>'akce','par1'=>'rok','step_man'=>1,'org'=>$org);
      for ($rok= $od; $rok<=$do; $rok++) {
        $lk= select('COUNT(*)','akce',"access&$org AND druh=1 AND zruseno=0 AND YEAR(datum_od)=$rok");
        if (!$lk) continue;
        $x= dot_prehled($rok,$dot_par);
        $man_s= $x->know->man_n; /*array_pop($man_s);*/ $man_s= array_reverse($man_s); 
        $man_y= $x->know->man_y; array_pop($man_y); $man_y= array_reverse($man_y); 
        $man_s= array_map(function($x){return $x/2;},$man_s);
        $data= implode(',',$man_s);
        $serie= (object)array('name'=>$rok,'data'=>$data);
        $chart->series[]= $serie;
      }
      $chart->yAxis= (object)array('title'=>(object)array('text'=>'počet manželství na letním kurzu'));
      $chart->xAxis= (object)array('categories'=>$man_y,
          'title'=>(object)array('text'=>'délka manželství v přihlášce '));
      $y->note= "<i><b>Poznámka:</b> 
        <br>korektnost grafu předpokládá, že je v databázi správně zapsána i počet účastí 
        na kurzech neevidovaných touto databází (pole 'MS mimo' na evidenční kartě).
        </i>";
      break;
    case 'skladba':
      $chart->title= 'Skladba účastníků letního kurzu';
      $od= $par->od;
      $do= $par->do ?: date('Y');
      $dot_par= (object)array('zdroj'=>'akce','par1'=>'rok','step_man'=>1,'skladba'=>1,'org'=>$org);
      //           VPS       novi
      $kurz= array(array(),array(),array(),array(),array());
      $roky= array();
      for ($rok= $od; $rok<=$do; $rok++) {
        // zjistíme, jestli v daném roce by LK
        $lk= select('COUNT(*)','akce',"access&$org AND druh=1 AND zruseno=0 AND YEAR(datum_od)=$rok");
        if (!$lk) continue;
//        if ($rok==2020 && $org==1) continue;
        $roky[]= $rok;
        $x= dot_prehled($rok,$dot_par);
//        $y->err= '.'; goto end;
        $kurz_x= $x->know->kurz_x ?: array(); 
//        debug($kurz_x,$rok);
        $kurz_y= $x->know->kurz_y; 
        $kurz_x= array_map(function($x){return $x/2;},$kurz_x);
        for ($x= 0; $x<=4; $x++) {
          $kurz[$x][$rok]= isset($kurz_x[$x]) ? $kurz_x[$x] : 0;
        }
      }
//      debug($kurz,"$od-$do");
      $color= array('navy','cyan','grey','orange','lightgreen');
      for ($x= 0; $x<=4; $x++) {
        $data= implode(',',$kurz[$x]);
        $serie= (object)array('name'=>$kurz_y[$x],'data'=>$data,'color'=>$color[$x]);
        $chart->series[$x]= $serie;
      }
      $chart->yAxis= (object)array('title'=>(object)array('text'=>'účast na kurzu jako'),
          'tickInterval'=>10,'min'=>0); // 'categories'=>$kurz_y);
      $chart->xAxis= (object)array('categories'=>$roky,
          'title'=>(object)array('text'=>'rok kurzu '));
      if (isset($chart->plotOptions->column->stacking)){
        $chart->tooltip= (object)array(
          'pointFormat'=>"<span>{series.name}</span>: <b>{point.y}</b> ({point.percentage:.0f}%)<br/>");
        if ($chart->plotOptions->column->stacking=='value' && $par->prc) {
          $chart->plotOptions->column->stacking= 'percent';
        }
      }
      break;
    // --------------------------------------------------------------- specifické pro EROP
    case 'erop':
      $chart->title= 'odhad počtu iniciovaných pozvatelných na EROP';
      // převzetí parametrů a inicializace
      $od= $par->od;
      $do= $par->do ?: date('Y');
      $kat_od= 0;
      $kat_do= array(49,59,87); 
      // tabulka úmrtnosti vzata z ČSÚ 6 Úmrtnost sloupec muži/2019
      $umrtnost_2019= array(
          '20-29' => 0.0007,  '30-34' => 0.0009,  '35-39' => 0.0012,  '40-44' => 0.0018, 
          '45-49' => 0.0030,  '50-54' => 0.0053,  '55-59' => 0.0083,  '60-64' => 0.0149,  
          '65-69' => 0.0232,  '70-74' => 0.0355,  '75-79' => 0.0536,  '80-84' => 0.0881, 
          '85-89' => 0.1544,  '90-94' => 0.2800,  '95-99' => 0.4000
      );
      $iniciovani= $kmeni= $umrti= $prestarli= 0;
      $umrtnost= array();
      foreach ($umrtnost_2019 as $uodudo=>$promile) {
        list($uod,$udo)= explode('-',$uodudo);
        for ($u= $uod; $u<=$udo; $u++) {
          $umrtnost[$u]= $promile;
        }
      }
//      debug($umrtnost_2019);
//      debug($umrtnost);
      $max_vek= $kat_do[count($kat_do)-1]+1;
      $color= array('green','grey','blue');
      $popis= array();
      for ($x= 0; $x<count($kat_do); $x++) {
        $popis[]= $kat_od ? "$kat_od ... $kat_do[$x]" : "... $kat_do[$x]";
        $kat_od= $kat_do[$x] + 1;
      }
      $roky= array();
      $mrop= array();
      // vložení skutečného počtu iniciovaných mužů [rok,kategorie]
      $mr= pdo_qry("
        SELECT iniciace,iniciace-YEAR(narozeni) AS _vek,COUNT(*)
        FROM osoba WHERE iniciace>0 AND YEAR(narozeni) BETWEEN 1920 AND 2020
          -- AND iniciace=2004
          -- AND iniciace=2002
          -- AND iniciace BETWEEN 2004 AND 2005
        GROUP BY iniciace,YEAR(narozeni)
        ORDER BY _vek
      ");
      while ($mr && (list($iniciace,$vek,$pocet)= pdo_fetch_row($mr))) {
        $mrop[$iniciace][$vek]= $pocet;
        $iniciovani+= $pocet;
      }
      debug($erop,"EROP"); 
      // zestárnutí o rok: $mrop[$rok][$vek] => $mrop[$rok+1][$vek-1]
      for ($rok= $od; $rok<=$do; $rok++) {
        // připočti iniciované předešlého roku do dalšího roku jako o rok starší
        if (isset($mrop[$rok-1])) {
          foreach ($mrop[$rok-1] as $vek=>$pocet) {
            $mrop[$rok][$vek+1]+= $pocet;
          }
        }
        // aplikuj pravděpodobnost úmrtí s ořezáním věku
        if (isset($mrop[$rok])) {
          foreach ($mrop[$rok] as $vek=>$pocet) {
            $pocet= $mrop[$rok][$vek];
            if ($vek>$max_vek) {
              $pocet= 0;
              $prestarli++;
            }
            else {
              $pocet-= round($umrtnost[$vek] * $pocet);
              $mrop[$rok][$vek]= $pocet;
            }
          }
        }
      }
      // zjištění absolventů EROP a jejich odečtení od disponibilních iniciovaných chlapů
      $erop= array();
      $mr= pdo_qry("
        SELECT YEAR(datum_od),YEAR(datum_od)-YEAR(narozeni) AS _vek,COUNT(*)
        FROM pobyt
        JOIN akce ON id_akce=id_duakce
        JOIN spolu USING (id_pobyt)
        JOIN osoba USING (id_osoba)
        WHERE nazev LIKE 'EROP%'
          AND deleted='' AND funkce IN (0,1) AND iniciace>0
          AND YEAR(narozeni) BETWEEN 1920 AND 2020 AND prislusnost IN ('','CZ','SK')
        GROUP BY YEAR(datum_od),YEAR(narozeni)
        ORDER BY _vek
      ");
      while ($mr && (list($rok_erop,$vek,$pocet)= pdo_fetch_row($mr))) {
        $erop[$rok_erop][$vek]= $pocet;
        $mrop[$rok_erop][$vek]-= $pocet;
        $kmeti+= $pocet;
      }
//      debug($mrop,"... MROP"); 
      // zobrazení podle věkových kategorií: $mrop[$rok][$vek] => $stav[kategorie][rok]
      $stav= array();
      for ($rok= $od; $rok<=$do; $rok++) {
        $roky[]= $rok;
        for ($x= 0; $x<count($kat_do); $x++) {
          if (!isset($stav[$x][$rok])) $stav[$x][$rok]= 0;
        }
      }
      for ($rok= $od; $rok<=$do; $rok++) {
        if (!isset($mrop[$rok])) continue;
        foreach ($mrop[$rok] as $vek=>$pocet) {
          for ($x= 0; $x<count($kat_do); $x++) {
            if ($vek <= $kat_do[$x]) {
              $stav[$x][$rok]+= round($pocet);
              $ok_vek++;
              break;
            }
          }
        }
      }
      $_iniciovani= 0;
      foreach ($mrop[$do] as $vek=>$pocet) {
//        display("$vek = $pocet");
        $_iniciovani+= round($pocet);
      }
      $umrti= round($iniciovani - $_iniciovani);
      display("celkem iniciovaní= $iniciovani, kmeti= $kmeti, starší $max_vek let= $prestarli, pravděpodobně zemřelo= $umrti");
//      debug($stav,"stav"); 
//      break; // ------------------------------------------------------------------ BREAK -----
      for ($x= 0; $x<count($kat_do); $x++) {
        $data= implode(',',$stav[$x]);
        $serie= (object)array('name'=>$popis[$x],'data'=>$data,'color'=>$color[$x]);
        $chart->series[$x]= $serie;
      }
      $chart->yAxis= (object)array('title'=>(object)array('text'=>'počet možných účastníků EROP v daném roce'),
          'tickInterval'=>10,'min'=>0); // 'categories'=>$mrop_y,
      $chart->xAxis= (object)array('categories'=>$roky,//'labels'=>(object)array('min'=>'5'),
          'title'=>(object)array('text'=>'rok konání MROP '));
      if (isset($chart->plotOptions->series->stacking)){
        $chart->tooltip= (object)array(
          'pointFormat'=>"<span>{series.name}</span>: <b>{point.y}</b> ({point.percentage:.0f}%)<br/>");
        if ($chart->plotOptions->series->stacking=='normal' && $par->prc) {
          $chart->plotOptions->series->stacking= 'percent';
        }
        $chart->chart= 'bar';
      }
      break;
    case 'vek_abs_nemrop': // věk nyní - neiniciovaní účastníci jiných akcí
      $now= date('Y-m-d');
      $k_datu= "'$now'";
      $mrop_k= sql_date1($now);
      $po= $par->po ?: 10;
      $data= array(array(),array(),array()); // stáří non-firming, firming
      $od= $par->od ?: 2004;
      $do= $par->do ?: $letos;
      $roku= $do - $od + 1;
      $od_vek= 9999; $do_vek= 0;
      $celkem= 0;
      $qv= pdo_qry("
        SELECT 
          FLOOR(IF(MONTH(narozeni),DATEDIFF($k_datu,narozeni)/365.2425,
            YEAR($k_datu)-YEAR(narozeni))) AS _vek,
          ( SELECT BIT_OR(IF(druh IN (1),1,IF(statistika IN (1,2,3,4),2,0))) 
            FROM pobyt
            LEFT JOIN spolu USING (id_pobyt)
            LEFT JOIN osoba AS x USING (id_osoba) 
            LEFT JOIN akce AS a ON id_akce=a.id_duakce 
            WHERE a.firm=0 AND a.mrop=0 AND a.zruseno=0 AND a.spec=0
              AND YEAR(a.datum_od) BETWEEN $od AND $do
              AND x.id_osoba=osoba.id_osoba
              AND x.deleted='' AND funkce IN (0,1,2) AND s_role IN (0,1)
          ) AS _akce,
          COUNT(*) AS _pocet
        FROM osoba 
        WHERE deleted='' AND narozeni AND umrti=0 AND sex=1
          AND iniciace=0 
        GROUP BY FLOOR(_vek/$po),_akce
        HAVING _akce>0 AND _vek BETWEEN 20 AND 99
        ORDER BY _vek
      ");
      while ($qv && (list($vek,$akce,$pocet)=pdo_fetch_row($qv))) {
        $vek_po= floor($vek/$po);
        $akce--; // 0 - jen MS, 1 - jen chlapi, 2 - oboje
        if (!isset($data[$akce][$vek_po*$po])) $data[$akce][$vek_po*$po]= 0;
        $data[$akce][$vek_po*$po]+= $pocet;
        $od_vek= min($od_vek,$vek_po);
        $do_vek= max($do_vek,$vek_po);
        $celkem+= $pocet;
      }
      display("meze $od_vek..$do_vek");
      // nedefinované hodnoty nahradíme nulou
      for ($i= 0; $i<=2; $i++) {
        for ($v= $od_vek; $v<=$do_vek; $v+=$po) {
          if (!isset($data[$i][$v*$po])) $data[$i][$v*$po]= 0;
        }
        ksort($data[$i]);
      }
      $celkem= round($celkem);
      debug($data,'data');
      $roky= array(); // názvy intervalů 20..
      for ($interval= $od_vek; $interval<=$do_vek; $interval++) {
        $roky[]= $interval*$po . ($po>1 ? '...' : '');
      }
//      debug($kurz,"$od-$do");
      $color= array('grey','darkgreen','blue');
      $ucastnik= array('účast pouze na MS','... pouze na akcích pro muže','oboje');
      for ($x= 0; $x<=count($color)-1; $x++) {
        $datax= implode(',',$data[$x]);
        $serie= (object)array('name'=>$ucastnik[$x],'data'=>$datax,'color'=>$color[$x]);
        $chart->series[$x]= $serie;
      }
      $chart->title= "Neiniciovaní muži ($celkem) z akcí v letech $od-$do ... "."skladba a věk k $mrop_k";
      $chart->yAxis= (object)array('title'=>(object)array('text'=>'účastníků'),'tickInterval'=>10);
//      $chart->yAxis= (object)array('title'=>(object)array('text'=>'účastník'),
//          'categories'=>$ucastnik);
      $chart->xAxis= (object)array('categories'=>$roky,
          'title'=>(object)array('text'=>'stáří '));
      if (isset($chart->plotOptions->column->stacking)){
        $chart->tooltip= (object)array(
          'pointFormat'=>"<span>{series.name}</span>: <b>{point.y}</b> ({point.percentage:.0f}%)<br/>");
        if ($chart->plotOptions->column->stacking=='value' && $par->prc) {
          $chart->plotOptions->column->stacking= 'percent';
        }
      }
      break;
    // --------------------------------------------------------------- specifické pro MROP
    case 'vek_rnd': // modelování věkové struktury - po 5 letech
      $po= $par->po ?: 10;
      $od= $par->od ?: 2004;
      $do= $par->do ?: $letos;
      $od_vek= 9999; $do_vek= 0; // pozice v intervalu
      $celkem= 0;
      $starych= array();
      // modelace počtu účastníků podle stáří: vek -> pocet
      //            25   30   35   40  45  50  55 60  65  70   75   80 85  
      $dist= array(4.7,12.6,20.4,17.3, 15,9.8,4.1, 3,1.2,0.5, 0.5, 0.1, 0);
      $dist_od= 25; $dist_do= 89; $dist_po= 5;
      $last= 0;
      for ($i= 0; $i<count($dist); $i++) {
        $pocet= $dist[$i];
        $vek= $dist_od+$dist_po*$i;
        $delta= ($pocet-$last)/$dist_po;
        for ($r= $vek; $r<$vek+$dist_po; $r++) {
          $starych[$r]= ($pocet+$delta)/$dist_po;
        }
        $last= $pocet;
      }
//      debug($starych,'starych');
      // přepočet na intervaly dat po $po
      $data= array(array()); // stáří 
      for ($i= 0; $i<100/$po; $i++) {
        $pocet= 0;
        for ($r= $i*$po; $r<($i+1)*$po; $r++) {
          $pocet+= $starych[$r];
        }
        if ($pocet) {
          $celkem+= $data[0][$i*$po]= round($pocet,0);
          $od_vek= min($od_vek,$i);
          $do_vek= max($do_vek,$i);
        }
      }
      $roky= array(); // názvy intervalů 20..
      for ($interval= $od_vek; $interval<$do_vek; $interval++) {
        $roky[]= $interval*$po . ($po>1 ? '...' : '');
      }
//      debug($data,'data');
      $color= array('grey');
      $osay= array();
      $ucastnik= array('odhad mrop');
      for ($x= 0; $x<=count($color)-1; $x++) {
        $datax= implode(',',$data[$x]);
        $serie= (object)array('name'=>$ucastnik[$x],'data'=>$datax,'color'=>$color[$x]);
        $chart->series[$x]= $serie;
      }
      $chart->title= "Předpoklad iniciovaných ($celkem)"; // v letech $od-$do ... "."skladba a věk k $mrop_k";
      $chart->yAxis= (object)array('title'=>(object)array('text'=>'účastníků'),'tickInterval'=>10);
//      $chart->yAxis= (object)array('title'=>(object)array('text'=>'účastníků'),
//          'categories'=>$osay);
      $chart->xAxis= (object)array('categories'=>$roky,
          'title'=>(object)array('text'=>'stáří '));
      if (isset($chart->plotOptions->column->stacking)){
        $chart->tooltip= (object)array(
          'pointFormat'=>"<span>{series.name}</span>: <b>{point.y}</b> ({point.percentage:.0f}%)<br/>");
        if ($chart->plotOptions->column->stacking=='value' && $par->prc) {
          $chart->plotOptions->column->stacking= 'percent';
        }
      }
      break;
    case 'vek_rel': // věk v době iniciace
      $mrop_vek= "CONCAT(iniciace,'-09-25')";
      $mrop_joins= "
        JOIN spolu USING (id_osoba) 
        JOIN pobyt USING (id_pobyt)
        JOIN akce ON id_akce=id_duakce AND YEAR(datum_od)=iniciace AND mrop=1";
      $mrop_k= "datu iniciace";
    case 'vek_abs': // věk nyní
      if (!$mrop_joins) {
        $now= date('Y-m-d');
        $mrop_vek= "'$now'";
        $mrop_k= sql_date1($now);
      }
      $po= $par->po ?: 10;
      $data= array(array(),array(),array()); // stáří non-firming, firming
      $od= $par->od ?: 2004;
      $do= $par->do ?: $letos;
      $roku= $do - $od + 1;
      $od_vek= 9999; $do_vek= 0;
      $celkem= 0;
      $qv= pdo_qry("
        SELECT 
          FLOOR(IF(MONTH(narozeni),DATEDIFF($mrop_vek,narozeni)/365.2425,
            YEAR($mrop_vek)-YEAR(narozeni))/$po) AS _vek,
          IF(firming>0,1,IF(
            ( SELECT COUNT(*) FROM pobyt
              LEFT JOIN spolu USING (id_pobyt)
              LEFT JOIN osoba AS x USING (id_osoba) 
              LEFT JOIN akce AS a ON id_akce=a.id_duakce 
              WHERE a.firm=0 AND a.mrop=0 AND a.zruseno=0 AND a.spec=0
                AND YEAR(a.datum_od)>=x.iniciace 
                AND x.id_osoba=osoba.id_osoba
                AND x.deleted=''
            )>0,2,0)
          ) AS _firm,
          COUNT(*) AS _pocet
        FROM osoba $mrop_joins
        WHERE deleted='' AND iniciace BETWEEN $od AND $do
        GROUP BY _vek,_firm
        HAVING _vek BETWEEN 0 AND 100/$po
        ORDER BY _vek
      ");
      while ($qv && (list($vek_po,$firm,$pocet)=pdo_fetch_row($qv))) {
//        $pocet= $pocet/$roku; 
//        $firm= 0;
        if (!isset($data[$firm][$vek_po*$po])) $data[$firm][$vek_po*$po]= 0;
        $data[$firm][$vek_po*$po]+= $pocet;
        $od_vek= min($od_vek,$vek_po);
        $do_vek= max($do_vek,$vek_po);
        $celkem+= $pocet;
      }
      display("meze $od_vek..$do_vek");
      // nedefinované hodnoty nahradíme nulou
      for ($i= 0; $i<=2; $i++) {
        for ($v= $od_vek; $v<=$do_vek; $v+=$po) {
          if (!isset($data[$i][$v*$po])) $data[$i][$v*$po]= 0;
        }
        ksort($data[$i]);
      }
      $celkem= round($celkem);
      debug($data,'data');
      $roky= array(); // názvy intervalů 20..
      for ($interval= $od_vek; $interval<=$do_vek; $interval++) {
        $roky[]= $interval*$po . ($po>1 ? '...' : '');
      }
//      debug($kurz,"$od-$do");
      $color= array('grey','darkgreen','blue');
      $ucastnik= array('jen mrop','potom firming','pak naše akce');
      for ($x= 0; $x<=count($color)-1; $x++) {
        $datax= implode(',',$data[$x]);
        $serie= (object)array('name'=>$ucastnik[$x],'data'=>$datax,'color'=>$color[$x]);
        $chart->series[$x]= $serie;
      }
      $chart->title= "Iniciovaní ($celkem) v letech $od-$do ... "."skladba a věk k $mrop_k";
      $chart->yAxis= (object)array('title'=>(object)array('text'=>'účastníků'),'tickInterval'=>10);
//      $chart->yAxis= (object)array('title'=>(object)array('text'=>'účastník'),
//          'categories'=>$ucastnik);
      $chart->xAxis= (object)array('categories'=>$roky,
          'title'=>(object)array('text'=>'stáří '));
      if (isset($chart->plotOptions->column->stacking)){
        $chart->tooltip= (object)array(
          'pointFormat'=>"<span>{series.name}</span>: <b>{point.y}</b> ({point.percentage:.0f}%)<br/>");
        if ($chart->plotOptions->column->stacking=='value' && $par->prc) {
          $chart->plotOptions->column->stacking= 'percent';
        }
      }
      break;
    case 'vek_fir': // věk nyní
      $mrop_vek= "CONCAT(firming,'-09-25')";
      $mrop_joins= "
        JOIN spolu USING (id_osoba) 
        JOIN pobyt USING (id_pobyt)
        JOIN akce ON id_akce=id_duakce AND YEAR(datum_od)=firming AND firm=1";
      $mrop_k= "datu firmingu";
      $po= $par->po ?: 10;
      $data= array(array(),array(),array()); // stáří non-firming, firming
      $od= $par->od ?: 2004;
      $do= $par->do ?: $letos;
      $roku= $do - $od + 1;
      $od_vek= 9999; $do_vek= 0;
      $celkem= 0;
      $qv= pdo_qry("
        SELECT 
          FLOOR(IF(MONTH(narozeni),DATEDIFF($mrop_vek,narozeni)/365.2425,
            YEAR($mrop_vek)-YEAR(narozeni))/$po) AS _vek,
          IF(firming>0,1,IF(
            ( SELECT COUNT(*) FROM pobyt
              LEFT JOIN spolu USING (id_pobyt)
              LEFT JOIN osoba AS x USING (id_osoba) 
              LEFT JOIN akce AS a ON id_akce=a.id_duakce 
              WHERE a.firm=0 AND a.mrop=0 AND a.zruseno=0 AND a.spec=0
                AND YEAR(a.datum_od)>=x.iniciace 
                AND x.id_osoba=osoba.id_osoba
                AND x.deleted=''
            )>0,2,0)
          ) AS _firm,
          COUNT(*) AS _pocet
        FROM osoba $mrop_joins
        WHERE deleted='' AND iniciace BETWEEN $od AND $do
        GROUP BY _vek,_firm
        HAVING _vek BETWEEN 0 AND 100/$po
        ORDER BY _vek
      ");
      while ($qv && (list($vek_po,$firm,$pocet)=pdo_fetch_row($qv))) {
        $firm= 0;
        if (!isset($data[$firm][$vek_po*$po])) $data[$firm][$vek_po*$po]= 0;
        $data[$firm][$vek_po*$po]+= $pocet;
        $od_vek= min($od_vek,$vek_po);
        $do_vek= max($do_vek,$vek_po);
        $celkem+= $pocet;
      }
      display("meze $od_vek..$do_vek");
      // nedefinované hodnoty nahradíme nulou
      for ($i= 0; $i<=0; $i++) {
        for ($v= $od_vek; $v<=$do_vek; $v+=$po) {
          if (!isset($data[$i][$v*$po])) $data[$i][$v*$po]= 0;
        }
        ksort($data[$i]);
      }
      $celkem= round($celkem);
      debug($data,'data');
      $roky= array(); // názvy intervalů 20..
      for ($interval= $od_vek; $interval<=$do_vek; $interval++) {
        $roky[]= $interval*$po . ($po>1 ? '...' : '');
      }
//      debug($kurz,"$od-$do");
      $color= array('darkgreen');
      $ucastnik= array('jen mrop','pak firming','pak naše akce');
      for ($x= 0; $x<=count($color)-1; $x++) {
        $datax= implode(',',$data[$x]);
        $serie= (object)array('name'=>$ucastnik[$x],'data'=>$datax,'color'=>$color[$x]);
        $chart->series[$x]= $serie;
      }
      $chart->title= "Účastníci firmingu ($celkem) v letech $od-$do ... "."skladba a věk k $mrop_k";
      $chart->yAxis= (object)array('title'=>(object)array('text'=>'účastníků'),'tickInterval'=>10);
//      $chart->yAxis= (object)array('title'=>(object)array('text'=>'účastník'),
//          'categories'=>$ucastnik);
      $chart->xAxis= (object)array('categories'=>$roky,
          'title'=>(object)array('text'=>'stáří '));
      if (isset($chart->plotOptions->column->stacking)){
        $chart->tooltip= (object)array(
          'pointFormat'=>"<span>{series.name}</span>: <b>{point.y}</b> ({point.percentage:.0f}%)<br/>");
        if ($chart->plotOptions->column->stacking=='value' && $par->prc) {
          $chart->plotOptions->column->stacking= 'percent';
        }
      }
      break;
    case 'pred_mrop':
    case 'po_mrop':
      $chart->title= $par->type=='pred_mrop'
          ? 'účastníci MROP s vyznačením absolventů MS (případně jiných akcí) před MROP'
          : 'účastníci MROP s vyznačením pokračujících na MS (případně jiných akcí) po MROP';
      $od= $par->od;
      $do= $par->do ?: date('Y');
      //           MS před  X před  MROP
      //           MROP     MS po   X po    
      $mrop= array(array(),array(),array());
      $mrop_y= $par->type=='pred_mrop' 
          ? array('MROP je první akcí','byl na MS před MROP','nebyl na MS, na jiné akci ano')
          : array('zatím na MS ne, jinak ano','pokračuje na MS','MROP je zatím poslední akcí');
      $roky= array();
      for ($rok= $od; $rok<=$do; $rok++) {
        // zjistíme, jestli v daném roce byl MROP
        $ok= select('COUNT(*)','akce',"mrop=1 AND zruseno=0 AND YEAR(datum_od)=$rok");
        $datum_od= select('datum_od','akce',"mrop=1 AND zruseno=0 AND YEAR(datum_od)=$rok");
        if (!$ok) continue;
        $mrop[0][$rok]= 0;
        $mrop[1][$rok]= 0;
        $mrop[2][$rok]= 0;
        $roky[]= $rok;
        $mr= pdo_qry("
          SELECT 
            IF(ms_pred+lk_pred>0,1,0),IF(ms_po+lk_po>0,1,0),
            IF(m_pred+j_pred>0,1,0),IF(m_po+j_po>0,1,0),
            md5_osoba,id_osoba
          FROM `#stat` WHERE mrop=$rok
          ");
        while ($mr && (list($ms_pred,$ms_po,$j_pred,$j_po,$md5o,$ido)= pdo_fetch_row($mr))) {
          if ($par->type=='pred_mrop' ) {
            if ($ms_pred) $mrop[1][$rok]++;
            elseif ($j_pred) $mrop[2][$rok]++;
            else $mrop[0][$rok]++;
            // doplníme informaci z ezer_answer
            if (!$ms_pred && $md5o) {
              $lk_first= select('YEAR(lk_first)','ezer_answer.db_osoba',
                  "db!=3 AND md5_osoba='$md5o' AND YEAR(lk_first)<=$rok");
              if ($lk_first) {
                display("$ido $rok/$lk_first");
                $mrop[1][$rok]++;
              }
            }
          }
          else {
            if ($ms_po) $mrop[1][$rok]++;
            elseif ($j_po) $mrop[0][$rok]++;
            else $mrop[2][$rok]++;
            // doplníme informaci z ezer_answer
            if (!$ms_po && $md5o) {
              $lk_last= select('YEAR(lk_last)','ezer_answer.db_osoba',
                  "db!=3 AND md5_osoba='$md5o' AND lk_last>'$datum_od' ");
              if ($lk_last) {
                display("$ido $rok/$lk_last");
                $mrop[1][$rok]++;
              }
            }
          }
        }
      }
      debug($mrop,"$od-$do");
      $color= $par->type=='pred_mrop' 
          ? array('orange','blue','navy')
          : array('navy','blue','orange');
      for ($x= 0; $x<=2; $x++) {
        $data= implode(',',$mrop[$x]);
        $serie= (object)array('name'=>$mrop_y[$x],'data'=>$data,'color'=>$color[$x]);
        $chart->series[$x]= $serie;
      }
      $chart->yAxis= (object)array('title'=>(object)array('text'=>'počet účastníků MROP v daném roce'),
          'tickInterval'=>10,'min'=>0); // 'categories'=>$mrop_y,
      $chart->xAxis= (object)array('categories'=>$roky,//'labels'=>(object)array('min'=>'5'),
          'title'=>(object)array('text'=>'rok konání MROP '));
      if (isset($chart->plotOptions->series->stacking)){
        $chart->tooltip= (object)array(
          'pointFormat'=>"<span>{series.name}</span>: <b>{point.y}</b> ({point.percentage:.0f}%)<br/>");
        if ($chart->plotOptions->series->stacking=='normal' && $par->prc) {
          $chart->plotOptions->series->stacking= 'percent';
        }
        $chart->chart= 'bar';
      }
      break;
    case 'ys_fa':
      $chart->title= 'účastníci MROP s vyznačením účastí na akcích YS/FA před MROP';
      $od= $par->od;
      $do= $par->do ?: date('Y');
      //           MROP     FA YS+FA YS
      $mrop= array(array(),array(),array());
      $mrop_y= array('MROP je první akcí',
          'byl na akcích Familia','byl tam i tam','byl na akcích Setkání'
          ,'ani tam ani tam, jinde ano'
          );
      $roky= array();
      for ($rok= $od; $rok<=$do; $rok++) {
        // zjistíme, jestli v daném roce byl MROP
        $ok= select('COUNT(*)','akce',"mrop=1 AND zruseno=0 AND YEAR(datum_od)=$rok");
        if (!$ok) continue;
        $mrop[0][$rok]= 0;
        $mrop[1][$rok]= 0;
        $mrop[2][$rok]= 0;
        $mrop[3][$rok]= 0;
        $roky[]= $rok;
        $mr= pdo_qry("
          SELECT ys_pred,fa_pred
          FROM `#stat` WHERE mrop=$rok
          ");
        while ($mr && (list($ys,$fa)= pdo_fetch_row($mr))) {
          if ($ys && $fa) $mrop[2][$rok]++;
          elseif ($ys) $mrop[3][$rok]++;
          elseif ($fa) $mrop[1][$rok]++;
          else $mrop[0][$rok]++;
        }
      }
      debug($mrop,"$od-$do");
      $color= array('orange','blue','cyan','green');
      for ($x= 0; $x<=3; $x++) {
        $data= implode(',',$mrop[$x]);
        $serie= (object)array('name'=>$mrop_y[$x],'data'=>$data,'color'=>$color[$x]);
        $chart->series[$x]= $serie;
      }
      $chart->yAxis= (object)array('title'=>(object)array('text'=>'počet účastníků MROP v daném roce'),
          'tickInterval'=>10,'min'=>0); //'categories'=>$mrop_y);
      $chart->xAxis= (object)array('categories'=>$roky,//'labels'=>(object)array('min'=>'5'),
          'title'=>(object)array('text'=>'rok konání MROP '));
      if (isset($chart->plotOptions->series->stacking)){
        $chart->tooltip= (object)array(
          'pointFormat'=>"<span>{series.name}</span>: <b>{point.y}</b> ({point.percentage:.0f}%)<br/>");
        if ($chart->plotOptions->series->stacking=='normal' && $par->prc) {
          $chart->plotOptions->series->stacking= 'percent';
        }
        $chart->chart= 'bar';
      }
      break;
    case 'y_x pred':
      $chart->title= 'účastníci MROP s vyznačením účastí na MS akcích YMCA/CPR+ŠM před MROP';
      $od= $par->od;
      $do= $par->do ?: date('Y');
      //           MROP     FA YS+FA YS
      $mrop= array(array(),array(),array());
      $mrop_y= array('MROP je první akcí',
          'byl na akcích CPR či ŠM','byl tam i tam','byl na akcích YMCA');
      $roky= array();
      for ($rok= $od; $rok<=$do; $rok++) {
        // zjistíme, jestli v daném roce byl MROP
        $ok= select('COUNT(*)','akce',"mrop=1 AND zruseno=0 AND YEAR(datum_od)=$rok");
        if (!$ok) continue;
        $mrop[0][$rok]= 0;
        $mrop[1][$rok]= 0;
        $mrop[2][$rok]= 0;
        $mrop[3][$rok]= 0;
        $roky[]= $rok;
        $mr= pdo_qry("
          SELECT ys_pred+fa_pred,md5_osoba
          FROM `#stat` WHERE mrop=$rok
          ");
        while ($mr && (list($ymca,$md5o)= pdo_fetch_row($mr))) {
          // aktivita v CPR a ŠM
          $jinde= select('YEAR(lk_first)','ezer_answer.db_osoba',
              "db!=3 AND md5_osoba='$md5o' AND YEAR(lk_first)<=$rok");
          if ($jinde) {
            display("$ido $rok/$jinde");
          }
          // redakce
          if ($ymca && $jinde) $mrop[2][$rok]++;
          elseif ($ymca) $mrop[3][$rok]++;
          elseif ($jinde) $mrop[1][$rok]++;
          else $mrop[0][$rok]++;
        }
      }
      debug($mrop,"$od-$do");
      $color= array('orange','red','violet','blue');
      for ($x= 0; $x<=3; $x++) {
        $data= implode(',',$mrop[$x]);
        $serie= (object)array('name'=>$mrop_y[$x],'data'=>$data,'color'=>$color[$x]);
        $chart->series[$x]= $serie;
      }
      $chart->yAxis= (object)array('title'=>(object)array('text'=>'počet účastníků MROP v daném roce'),
          'tickInterval'=>10,'min'=>0); // 'categories'=>$mrop_y);
      $chart->xAxis= (object)array('categories'=>$roky,//'labels'=>(object)array('min'=>'5'),
          'title'=>(object)array('text'=>'rok konání MROP '));
      if (isset($chart->plotOptions->series->stacking)){
        $chart->tooltip= (object)array(
          'pointFormat'=>"<span>{series.name}</span>: <b>{point.y}</b> ({point.percentage:.0f}%)<br/>");
        if ($chart->plotOptions->series->stacking=='normal' && $par->prc) {
          $chart->plotOptions->series->stacking= 'percent';
        }
        $chart->chart= 'bar';
      }
      break;
    case 'y_x po':
      $chart->title= 'účastníci MROP s vyznačením účastí na MS akcích YMCA/CPR+ŠM po MROP';
      $od= $par->od;
      $do= $par->do ?: date('Y');
      //           MROP     FA YS+FA YS
      $mrop= array(array(),array(),array());
      $mrop_y= array('byl na akcích CPR či ŠM','byl tam i tam','byl na akcích YMCA',
          'MROP je zatím poslední akcí');
      $roky= array();
      for ($rok= $od; $rok<=$do; $rok++) {
        // zjistíme, jestli v daném roce byl MROP
        $datum_od= select('datum_od','akce',"mrop=1 AND zruseno=0 AND YEAR(datum_od)=$rok");
        if (!$datum_od) continue;
        $mrop[0][$rok]= 0;
        $mrop[1][$rok]= 0;
        $mrop[2][$rok]= 0;
        $mrop[3][$rok]= 0;
        $roky[]= $rok;
        $mr= pdo_qry("
          SELECT ms_po+lk_po,md5_osoba
          FROM `#stat` WHERE mrop=$rok
          ");
        while ($mr && (list($ymca,$md5o)= pdo_fetch_row($mr))) {
          // aktivita v CPR a ŠM
          $jinde= select('YEAR(lk_last)','ezer_answer.db_osoba',
              "db!=3 AND md5_osoba='$md5o' AND lk_last>'$datum_od'");
          if ($jinde) {
            display("$ido $rok/$jinde");
          }
          // redakce
          if ($ymca && $jinde) $mrop[1][$rok]++;
          elseif ($ymca) $mrop[2][$rok]++;
          elseif ($jinde) $mrop[0][$rok]++;
          else $mrop[3][$rok]++;
        }
      }
      debug($mrop,"$od-$do");
      $color= array('red','violet','blue','orange');
      for ($x= 0; $x<=3; $x++) {
        $data= implode(',',$mrop[$x]);
        $serie= (object)array('name'=>$mrop_y[$x],'data'=>$data,'color'=>$color[$x]);
        $chart->series[$x]= $serie;
      }
      $chart->yAxis= (object)array('title'=>(object)array('text'=>'počet účastníků MROP v daném roce'),
          'tickInterval'=>10,'min'=>0); // 'categories'=>$mrop_y);
      $chart->xAxis= (object)array('categories'=>$roky,//'labels'=>(object)array('min'=>'5'),
          'title'=>(object)array('text'=>'rok konání MROP '));
      if (isset($chart->plotOptions->series->stacking)){
        $chart->tooltip= (object)array(
          'pointFormat'=>"<span>{series.name}</span>: <b>{point.y}</b> ({point.percentage:.0f}%)<br/>");
        if ($chart->plotOptions->series->stacking=='normal' && $par->prc) {
          $chart->plotOptions->series->stacking= 'percent';
        }
        $chart->chart= 'bar';
      }
      break;
  }
  $y->chart= $chart;
//  debug($y);
end:
  return $y;
}
# --------------------------------------------------------------------------------==> . sta2 ms stat
/** https://stackoverflow.com/questions/4563539/how-do-i-improve-this-linear-regression-function
 * linear regression function
 * @param $x array x-coords
 * @param $y array y-coords
 * @returns array() m=>slope, b=>intercept
 */
function linear_regression($x, $y) {
  // calculate number points
  $n = count($x);
  // ensure both arrays of points are the same size
  if ($n != count($y)) {
    trigger_error("linear_regression(): Number of elements in coordinate arrays do not match.", E_USER_ERROR);
  }
  // calculate sums
  $x_sum = array_sum($x);
  $y_sum = array_sum($y);
  $xx_sum = 0;
  $xy_sum = 0;
  for($i = 0; $i < $n; $i++) {
    $xy_sum+=($x[$i]*$y[$i]);
    $xx_sum+=($x[$i]*$x[$i]);
  }
  // calculate slope
  $m = (($n * $xy_sum) - ($x_sum * $y_sum)) / (($n * $xx_sum) - ($x_sum * $x_sum));
  // calculate intercept
  $b = ($y_sum - ($m * $x_sum)) / $n;
  // return result
  return array("m"=>$m, "b"=>$b);
}
/** =======================================================================================> STA2 MS */
# --------------------------------------------------------------------------------==> . sta2 ms stat
# agregace údajů o účastích a účastnících MS
# typ=0 - účasti    => věkové průměry, počty dětí, ročníky MS
# typ=1 - účastníci => geo-info, příslušnost ke kurzu, iniciace muže ... výhledově cesta akcemi
function sta2_ms_stat($par) {
  $msg= '?';  
  switch ($par->op) {
  case 'gen': // ------------------------------ účasti
    // vynulování pracovní tabulky #stat
    query("TRUNCATE TABLE `#stat_ms`");
  // získání individuálních a rodinných údajů
    $mr= pdo_qry("
      SELECT id_rodina,funkce,YEAR(datsvatba),
        -- GROUP_CONCAT(CONCAT(t.role,'~',ROUND(DATEDIFF(datum_od,narozeni)/365.2425))) AS _inf,
        GROUP_CONCAT(CONCAT(t.role,'~',ROUND(IF(MONTH(narozeni),DATEDIFF(datum_od,narozeni)/365.2425,YEAR(datum_od)-YEAR(narozeni))))) AS _inf,
        MAX(iniciace) AS _mrop,
        r.psc,r.stat,a.access,YEAR(datum_od),
        r.nazev AS _note
      FROM pobyt
      JOIN akce AS a ON id_akce=id_duakce
      JOIN rodina AS r ON r.id_rodina=i0_rodina
      JOIN tvori AS t USING (id_rodina)
      JOIN osoba AS o USING (id_osoba)
      WHERE druh=1 AND spec=0 AND zruseno=0 
      -- AND id_pobyt>56600
      GROUP BY id_pobyt
      ORDER BY id_rodina;
    ");
    while ( $mr && 
        list($idr,$vps,$svatba,$veky,$mrop,$psc,$stat,$access,$rok,$nazev)
        = pdo_fetch_row($mr) ) {
      $deti= $vek_a= $vek_b= 0;
      foreach (explode(',',$veky) as $role_vek) {
        list($role,$vek)= explode('~',$role_vek);
        switch ($role) {
        case 'a': $vek_a= $vek; break;  
        case 'b': $vek_b= $vek; break;  
        case 'd': $deti++; break;  
        }
      }
      query("INSERT INTO `#stat_ms` (typ,id_rodina,access,stat,psc,vek_a,vek_b,ms,svatba,mrop,deti,vps,note) 
        VALUE (0,$idr,$access,'$stat','$psc','$vek_a','$vek_b',$rok,$svatba,$mrop,$deti,$vps,'$nazev')");
    }
    // přepočet účastí na účastníky
    $mr= pdo_qry("
      SELECT id_rodina,MAX(vps),MAX(mrop),psc,stat,BIT_OR(access),note
      FROM `#stat_ms`
      GROUP BY id_rodina;
    ");
    while ( $mr && 
        list($idr,$vps,$mrop,$psc,$stat,$access,$nazev)
        = pdo_fetch_row($mr) ) {
      query("INSERT INTO `#stat_ms` (typ,id_rodina,access,stat,psc,mrop,vps,note) 
        VALUE (1,$idr,$access,'$stat','$psc',$mrop,$vps,'$nazev')");
    }
    break;
  case 'see':   // ------------------------------ statistiky
    $note= '';
    $msg= sta2_ms_stat_see($par,$note);
    break;
  case 'see-o': // ------------------------------ statistiky podle organizací
    $note= '';
    $par->org= 1;
    $msg1= sta2_ms_stat_see($par,$note);
    $note= '';
    $par->org= 2;
    $msg2= sta2_ms_stat_see($par,$note);
    $msg= "<table><tr><td>$msg1</td><td>$msg2</td></tr></table>$note";
    break;
  case 'see-t': // ------------------------------ statistiky po letech
    $delta= 10;
    $letos= date('Y');
    $ths= $tds= '';
    for ($od= 2004; $od<$letos; $od+= $delta) {
      $do= $rok+$delta-1;
      $ths.= "<th>$od .. $do</th>";
      $tds.= sta2_ms_stat_see($par);
    }
    $msg= "<table><tr>$ths</tr><tr>$tds</tr></table>";
    break;
  }  
  return $msg;
}
# --------------------------------------------------------------------------==> . sta2 mrop stat gen
# interpretace údajů o účastnících MS
# par.typ = posloupnost písmen   g=geo informace s=statistika  
# par.org = výběr organizace (3=obě)
function sta2_ms_stat_see($par,&$note) { trace();
  $typ= isset($par->typ) ? $par->typ : '';
  $org= isset($par->org) ? $par->org : 3;
  $pro= array('','YS','FA','YS+FA')[$org];
  $msg= '';  
  // proměnné pro účasti    typ=0
  $vsichni_0= 0;
  $deti= $deti3plus= 0;
  // proměnné pro účastníky typ=1
  $vsichni_1= 0;
  $ms_org= array(0,0,0,0,0);
  $okres= $kraj= $pscs= $mss= $miss= array();
  // výpočet pro účasti
  $sr= pdo_qry("
    SELECT id_rodina,deti
    FROM `#stat_ms` 
    WHERE stat='CZ' AND typ=0 AND access & $org
  ");
  while ( $sr && 
      list($idr,$det)
      = pdo_fetch_row($sr) ) {
    // sumy
    $vsichni_0++;
    $deti+=     $det ? 1 : 0;
    $deti3plus+=$det>=3 ? 1 : 0;
  }
  // výpočet pro účastníky
  $sr= pdo_qry("
    SELECT id_rodina,(vek_a+vek_b)/2,access,stat,psc
    FROM `#stat_ms` 
    WHERE stat='CZ' AND typ=1 AND access & $org
  ");
  while ( $sr && 
      list($idr,$vek,$access,$stat,$psc)
      = pdo_fetch_row($sr) ) {
    $vsichni_1++;
    // sumy
    $ms_org[$access]++;
    // výpočty
    if ( $psc ) {
      // geo informace
      if ( !isset($pscs[$psc]) ) $pscs[$psc]= 0;
      $pscs[$psc]++;
      list($k_okres,$k_kraj)= select('kod_okres,kod_kraj','`#psc`',"psc=$psc");
      if ( !$k_kraj ) {
        if ( !in_array($psc,$miss) )
          $miss[]= $psc;
      }
      else {
        if ( !isset($okres[$k_okres])) $okres[$k_okres]= 0;
        $okres[$k_okres]++;
        if ( !isset($kraj[$k_kraj])) $kraj[$k_kraj]= 0;
        $kraj[$k_kraj]++;
      }
    }
  }
  if ( count($miss)) {
    $msg.= "<br>neznáme následující PSČ: ";
    sort($miss);
    foreach ($miss as $pcs) {
      $msg.= " $pcs";
    }
    $msg.= "<br><br>";
  }
  // ------------------------------ statistické informce YS + FA
  if ( strstr($typ,'s')) {
    $msg.= "<h3>Celkem $vsichni_1 párů z ČR</h3>
      <b>účastnilo se kurzu</b>
      <br>... YS = $ms_org[1] párů
      <br>... FA = $ms_org[2] párů
      <br>... YS i FA = $ms_org[3] párů
      <br>
      <h3>Celkem $vsichni_0 účastí párů z ČR</h3>
      <b>děti</b>
      <br>$deti párů mají děti, z toho $deti3plus jich mají 3 a více
     ";
  }
  // ------------------------------ podle věku - účasti
  if ( strstr($typ,'v')) {
    $meze= array(10,20,30,40,50,60,70,80,90,99);
    $meze= array(15,20,25,30,35,40,45,50,55,60,65,70,75,80,85,90,95,99);
    $stari= array();
    $s_ucasti= 0;
    // zobrazení
    $msg.= "<b>Celkem $vsichni_0 účastí z ČR</b>";
    $msg.= "<h3>Účasti podle průměru stáří manželů pro $pro</h3>
      <table class='stat'><tr><th>věk</th><th>počet</th><th>%</th></tr>";
    for ($i=0; $i<count($meze)-1; $i++) {
      $m= select('COUNT(*)','`#stat_ms`',
          "typ=0 AND access & $org AND stat='CZ' AND (vek_a+vek_b)/2 >= $meze[$i] "
          . "AND (vek_a+vek_b)/2 < {$meze[$i+1]}");
      $stari[$i]= $m;
      $s_ucasti+= $m;
      $od= $i==0 ? '.' : $meze[$i]+1;
      $do= $i==count($meze)-2 ? '.' : $meze[$i+1];
      $pm= $stari[$i] ? round(100*$stari[$i]/$vsichni_0) : '-';
      $msg.= "<tr><th>$od..$do</th><td align='right'>$stari[$i]</td>
        <td align='right'>&nbsp;&nbsp;&nbsp;&nbsp;$pm</td>
      </tr>";
    }
    $pm= round(100*$s_ucasti/$vsichni_0);
    $msg.= "<tr><th>celkem</th><th align='right'>$s_ucasti</th>
      <th align='right'>&nbsp;&nbsp;&nbsp;&nbsp;$pm</th>
    </tr>";
    $msg.= "</table>";
  }
  // ------------------------------ podle velikosti obcí - účastníci
  if ( strstr($typ,'o')) {
    $meze= array(0,1,10,100,1000,10000,100000,1000000);
    $meze= array(0,300,1000,3000,10000,30000,100000,300000,1000000);
    $meze= array(0,290,576,1084,2230,4650,9700,21200,46600,300000,1000000);
    $meze= array(0,580,1152,2168,4460,9300,19400,42400,93200,600000,2000000);
    $lidi= $obce= $ms= array();
    $s_lidi= $s_obce= $s_ucasti= 0;
    $msg.= "<h3>Účastníci podle velikosti obce pro $pro</h3>
      <table class='stat'><tr>
      <th>velikost obce</th>
      <th>počet obcí</th>
      <th>počet párů</th>
      <th>%</th>
      <th> ‰ </th>
      <th>obyvatel</th></tr>";
    // výpočet počtu lidí v obcích dané velikosti
    for ($i=count($meze)-2; $i>=0; $i--) {
      list($m,$o)= select('SUM(muzi+zeny),COUNT(*)','`#obec`',
          "(muzi+zeny) BETWEEN $meze[$i] AND {$meze[$i+1]}");
      $s_lidi+= $lidi[$i]= $m;
      $s_obce+= $obce[$i]= $o;
    }
    // výpočet počtu účastníků v obcích dané velikosti
    for ($i=count($meze)-2; $i>=0; $i--) {
      $od= $i==0 ? '.' : $meze[$i];
      $do= $i==count($meze)-2 ? '.' : $meze[$i+1];
      // výpočet 
      $ucasti= 0;
      foreach ($pscs as $psc=>$n) {
        $ok= select('COUNT(*)','`#psc` JOIN `#obec` USING (kod_obec)',
            "psc=$psc AND (muzi+zeny) BETWEEN $meze[$i] AND {$meze[$i+1]}");
        if ( $ok ) {
          $ucasti+= $n;
          $s_ucasti+= $n;
        }
      }
      $pi= $ucasti ? round(100*$ucasti/$vsichni_1) : '?';
      $ppm= $lidi[$i] ? round(1000*2*$ucasti/$lidi[$i],2) : '?';
      $msg.= "<tr><th>$od..$do</th>
        <td align='right'>$obce[$i]</td>
        <td align='right'>$ucasti</td>
        <td align='right'>$pi</td>
        <td>&nbsp;&nbsp;&nbsp;&nbsp;$ppm</td>
        <td align='right'>$lidi[$i]</td>
      </tr>";
    }
    $ppm= round(1000*2*$s_ucasti/$s_lidi,2);
    $msg.= "<tr><th>celkem</th>
      <th align='right'>$s_obce</th>
      <th align='right'>$s_ucasti</th>
      <th>100</th>
      <th align='right'>&nbsp;&nbsp;&nbsp;&nbsp;$ppm</th>
      <th align='right'>$s_lidi</th>
    </tr>";
    $msg.= "</table>";
    $note.= "<br><br><i>Poznámka: 
      sloupec % vyjadřuje procento z celkového počtu účastníků, sloupec ‰ vyjadřuje promile vůči počtu obyvatel
      <br>odchylka v počtu lidí podle obcí a PSČ vznikla tím,
      že tabulka okresu je z roku 2018, tabulka PSČ a obcí z roku 2020 </i>";
  }
  // ------------------------------ celkově geo
  if ( strstr($typ,'g') || strstr($typ,'o')) {
    $cr_lidi= 0;
    $kraj_lidi= $kraj_ppn= array();
    $okres_lidi= $okres_ppn= array();
    $sr= pdo_qry("SELECT kod_kraj,kod_okres,lidi FROM `#okres`");
    while ( $sr && list($k_kraj,$k_okres,$lidi)= pdo_fetch_row($sr)) {
      if ( !isset($kraj_lidi[$k_kraj])) $kraj_lidi[$k_kraj]= 0;
      $cr_lidi+= $lidi;
      $kraj_lidi[$k_kraj]+= $lidi;
      $okres_lidi[$k_okres]= $lidi;
    }
    foreach ($kraj as $k_kraj=>$n) {
      $kraj_ppn[$k_kraj]= round(1000*$n/$kraj_lidi[$k_kraj],2);
    }
    arsort($kraj_ppn);
  }
  // ------------------------------ účasti z okresů a krajů
  if ( strstr($typ,'g')) {
    // geo tabulka krajů
    $msg.= "<h3>Účasti v krajích ČR pro $pro</h3>
      <table class='stat'><tr><th>kraj</th><th>účasti</th><th> ‰ obyvatel</th></tr>";
    foreach ($kraj_ppn as $k_kraj=>$ppn) {
      $nazev= select('nazev','`#kraj`',"kod_kraj=$k_kraj");
      $n= $kraj[$k_kraj];
      $msg.= "<tr><th>$nazev</th><td align='right'>$n</td><td>&nbsp;&nbsp;&nbsp;&nbsp;$ppn</td></tr>";
    }
    $msg.= "</table>";
    // geo info - okresy
    foreach ($okres as $k_okres=>$n) {
      $nazev= select('nazev','`#kraj`',"kod_kraj=$k_kraj");
      $okres_ppn[$k_okres]= round(1000*$n/$okres_lidi[$k_okres],2);
    }
    arsort($okres_ppn);
    $msg.= "<h3>Účasti v okresech ČR</h3>
      <table class='stat'><tr><th>okres</th><th>účasti</th><th>‰ obyvatel</th></tr>";
    foreach ($okres_ppn as $k_okres=>$ppm) {
      $nazev= select('nazev','`#okres`',"kod_okres=$k_okres");
      $n= $okres[$k_okres];
      $msg.= "<tr><th>$nazev</th><td align='right'>$n</td><td>&nbsp;&nbsp;&nbsp;&nbsp;$ppm</td></tr>";
    }
    $msg.= "</table>";
  }
  return $msg;
}
# ----------------------------------------------------------------------------==> . sta2 ms stat map
# zobrazení bydliště
# vrací ret.mark - seznam poloh PSČ
#   .chybi -- seznam chybějících PSČ k interaktivnímu dopl
function sta2_ms_stat_map($par) {
  $ret= (object)array();
  $pscs= $notes= array();
  switch ($par->mapa) {
  // ------------------------------------------------------ clear
  case 'clear':
    $ret= mapa2_psc($pscs,$notes,1);
    $ret->title= "ČR";
    $ret->rewrite= 1;
    $ret->suma= 0;
    break;
  // ------------------------------------------------------ malé obce MS
  case 'malé obce':
    $n= $n2= 0;
    $icons= array();
    $vsichni= select('COUNT(*)','`#stat_ms`',"typ=1 AND stat='CZ'");
    $sr= pdo_qry("
      SELECT psc,nazev
      FROM `#stat_ms` JOIN `#psc` USING(psc) JOIN `#obec` USING (kod_obec) 
      WHERE muzi+zeny>{$par->od} AND muzi+zeny<={$par->do}
    ");
    while ( $sr && list($psc,$nazev)= pdo_fetch_row($sr) ) {
      $n++;
      $icons[$psc]= "CIRCLE,green,green,1,8";
      $pscs[$psc]= "$nazev $psc";
      if ( !isset($notes[$psc]) ) $notes[$psc]= 0;
      $notes[$psc]++;
    }
    $pc= round(100*$n/$vsichni);
    $ret= mapa2_psc($pscs,$notes,1,$icons);
    $ret->title= "Celkem $n ($pc%) účastníků žije v obcích s {$par->od} až {$par->do} muži";
    $ret->rewrite= 0;
    $ret->suma= $n;
    $ret->total= $vsichni;
    break;
  }
end:
  return $ret;
}
/** =====================================================================================> STA2 MROP */
# ------------------------------------------------------------------------------==> . sta2 mrop stat
# agregace údajů o absolventech MROP
function sta2_mrop_stat($par) {
  global $ezer_path_docs;
  $msg= '';  
  switch ($par->op) {
  case 'gen':
    $msg= sta2_mrop_stat_gen($par);
    break;
  case 'see':   // ---------------------------- statistiky
    $msg= sta2_mrop_stat_see($par,$title);
    $msg= $title.$msg;
    break;
  case 'see2':  // ---------------------------- statistiky verze 2.0
    $msg= sta2_mrop_stat2_see($par,$title);
    $msg= $title.$msg;
    break;
  case 'see-t': // ---------------------------- statistiky po letech
    $delta= 8;
    $delta= 4;
//    $delta= 2;
//    $delta= 1;
    $letos= date('Y');
    $title= '';
    $ths= $tds= '';
    for ($od= 2004; $od<$letos; $od+= $delta) {
      $do= $od+$delta-1;
      $par->od= $od;
      $par->do= $do;
      $ths.= "<th><h3>$od .. $do</h3></th>";
      $tds.= "<td style='vertical-align:top'>".sta2_mrop_stat_see($par,$title)."</td>";
    }
    $msg= "<div>$title</div><table><tr>$ths</tr><tr>$tds</tr></table>";
    break;
  // načtení tabulky #psc   (psc,kod_obec,kod_okres,kod_kraj)
  // a tabulky       #okres (kod_okres,kod_kraj,nazev)
  // a tabulky       #kraj  (kod_kraj,nazev,muzi,lidi)
  // ze souboru staženého z https://www.ceskaposta.cz/ke-stazeni/zakaznicke-vystupy#1
  //                  2 psc 3 kod_obec      5 kod_okres         7 kod_kraj
  // (kodcobce,nazcobce,psc,kodobce,nazobce,kodokresu,nazokresu,kodkraj,nazevkraj,kodmomc,nazmomc,kodpobvod,nazpobvod)
  // a souboru zkombinovaného z 
  //   http://eagri.cz/public/app/eagricis/Forms/Lists/Nuts/NutsListsPage.aspx (kod a nuts)
  //   a https://www.czso.cz/csu/czso/pocet-obyvatel-v-obcich-see2a5tx8j (obyvatelstvo okresů a krajů)
  // (kodokresu,nuts_okresu,nazev,obyvatel,muzu) k 1.1.2018     
  // 
  // načtení tabulky #obce  (kod_obec,nazev,muzi,muzi15,zeny,zeny15)
  // ze souboru staženého z https://www.mvcr.cz/clanek/informativni-pocty-obyvatel-v-obcich.aspx
  case 'psc':
    $pscs= $okress= $krajs= $muzi= $lidi= array();
    // vynulování pracovní tabulky #okres $kraj
    query("TRUNCATE TABLE `#okres`");
    query("TRUNCATE TABLE `#kraj`");
    // načtení lidnatosti okresů
    $fullname= "$ezer_path_docs/import/psc/lidnatost_okresu_2018.csv";
    if ( !file_exists($fullname) ) { $msg.= "soubor $fullname neexistuje "; goto end; }
    $f= @fopen($fullname, "r");
    if ( !$f ) { $msg.= "soubor $fullname nelze otevřít"; goto end; }
    $line= fgets($f, 1000); // hlavička
    while (($line= fgets($f, 1000)) !== false) {
      $data= str_getcsv($line,';'); 
      $muzi[$data[0]]= $data[4];
      $lidi[$data[0]]= $data[3];
    }
    // vynulování pracovní tabulky #psc
    query("TRUNCATE TABLE `#psc`");
    $fullname= "$ezer_path_docs/import/psc/zv_cobce_psc.csv";
    if ( !file_exists($fullname) ) { $msg.= "soubor $fullname neexistuje "; goto end; }
    $f= @fopen($fullname, "r");
    if ( !$f ) { $msg.= "soubor $fullname nelze otevřít"; goto end; }
    $line= fgets($f, 1000); // hlavička
    while (($line= fgets($f, 1000)) !== false) {
      $line= win2utf($line,1);
      $data= str_getcsv($line,';'); 
      $psc= $data[2];
      $okres= $data[5];
      $kraj= $data[7];
      if ( in_array($psc,$pscs)) continue;
      $pscs[]= $psc;
      query("INSERT INTO `#psc` (psc,kod_obec,kod_okres,kod_kraj) 
              VALUE ($psc,$data[3],$okres,$kraj)");
      if ( !in_array($okres,$okress) ) {
        $okress[]= $okres;
        query("INSERT INTO `#okres` (kod_okres,kod_kraj,nazev,muzi,lidi) 
                VALUE ($okres,$kraj,'$data[6]',$muzi[$okres],$lidi[$okres])");
      }
      if ( !in_array($kraj,$krajs) ) {
        $krajs[]= $kraj;
        query("INSERT INTO `#kraj` (kod_kraj,nazev) 
                VALUE ($kraj,'$data[8]')");
      }
    }
    fclose($f); $f= null;
    $msg.= "<br>načteno ".count($pscs)." PSČ";
    // vynulování pracovní tabulky #obce
    query("TRUNCATE TABLE `#obec`");
    $n= 0;
    $fullname= "$ezer_path_docs/import/psc/lidnatost_obci_2020.csv";
    if ( !file_exists($fullname) ) { $msg.= "soubor $fullname neexistuje "; goto end; }
    $f= @fopen($fullname, "r");
    if ( !$f ) { $msg.= "soubor $fullname nelze otevřít"; goto end; }
    $line= fgets($f, 1000); // hlavička
    while (($line= fgets($f, 1000)) !== false) {
      $data= str_getcsv($line,';'); 
      $obec= $data[0];
      $nazev= $data[1];
      $muzi= str_replace(' ','',$data[2]);
      $mu15= str_replace(' ','',$data[3]);
      $zeny= str_replace(' ','',$data[4]);
      $ze15= str_replace(' ','',$data[5]);
      query("INSERT INTO `#obec` (kod_obec,nazev,muzi,muzi15,zeny,zeny15) 
              VALUE ($obec,'$nazev',$muzi,$mu15,$zeny,$ze15)");
      $n++;
    }
    fclose($f); $f= null;
    $msg.= "<br>načteno $n obcí";
    break;
  }
end:
  return $msg;
}
# --------------------------------------------------------------------------==> . sta2 mrop stat map
# zobrazení bydliště
# vrací ret.mark - seznam poloh PSČ
#   .chybi -- seznam chybějících PSČ k interaktivnímu dopl
function sta2_mrop_stat_map($par) {
  $ret= (object)array();
  $pscs= $notes= array();
  switch ($par->mapa) {
  // ------------------------------------------------------ clear
  case 'clear':
    $ret= mapa2_psc($pscs,$notes,1);
    $ret->title= "ČR";
    $ret->rewrite= 1;
    $ret->suma= 0;
    break;
  // ------------------------------------------------------ Brno
  case 'Brno':
    $pscs['62300']= "Brno";
    $notes['62300']= "1";
    $ret= mapa2_psc($pscs,$notes,1,"CIRCLE,transparent,red,1,12");
//    $ret= mapa2_psc($pscs,$notes,1,"CIRCLE,scale:12,fillOpacity:0.0,strokeColor:'blue',strokeWeight:2");
    $ret->title= "Je zobrazeno 1 Brno";
    $ret->rewrite= 1;
    $ret->suma= 1;
    break;
  // ------------------------------------------------------ skupiny
  case 'skupiny':
    # přečtení seznamu skupin z tabulky
    # https://docs.google.com/spreadsheets/d/1mp-xXrF1I0PAAXexDH5FA-n5L71r5y0Qsg75cU82X-4/edit#gid=0
    # https://docs.google.com/spreadsheets/d/1mp-xXrF1I0PAAXexDH5FA-n5L71r5y0Qsg75cU82X-4/gviz/tq?tqx=out:json
    # 0 - skupina; 1 - psč[,město,ulice]; 2 - aktualizace; 3 - kontakt; 4 - email; 5 - poznámka; 6 - uzavřená skupina
    $goo= "https://docs.google.com/spreadsheets/d";
    $key= "1mp-xXrF1I0PAAXexDH5FA-n5L71r5y0Qsg75cU82X-4";         // Seznam skupin - kontakty
    $prefix= "google.visualization.Query.setResponse(";           // přefix json objektu
    $x= file_get_contents("$goo/$key/gviz/tq?tqx=out:json"); //&sheet=$sheet");
    $xi= strpos($x,$prefix);
    $xl= strlen($prefix);
    $x= substr(substr($x,$xi+$xl),0,-2);
    $tab= json_decode($x)->table;
    // projdeme získaná data
    $adrs= $geos= $notes= $clmns= $emails= array();
    $n= 0;
    if ( $tab ) {
      foreach ($tab->rows as $crow) {
        $row= $crow->c;
        if ( $row[0]->v=="ZVLÁŠTNÍ SKUPINY:" ) break;     // konec seznamu
        $group= $row[0]->v;
        $adrs= $row[1]->v;
        $adr= strtr($adrs,array(';'=>',','?'=>'',"\n"=>''));
        $psc= substr(strtr(trim(substr($adr,0,10)),array(' '=>'')),0,5);
        $pscs[$psc]= $group;
        $notes[$psc]= $adrs;
        $n++;
      }
    }
    $ret= mapa2_psc($pscs,$notes,1,"CIRCLE,yellow,red,999,12");
    $ret->title= "Je zobrazeno $n skupin z tabulky <b>Seznam skupin - kontakty</b>";
    $ret->rewrite= 0;
    $ret->suma= $n;
    break;
  // ------------------------------------------------------ malé obce
  case 'malé obce':
//    $n= 0;
//    $vsichni= select('COUNT(*)','`#stat`',"stat='CZ'");
//    $sr= pdo_qry("
//      SELECT psc,nazev FROM `#stat` JOIN `#psc` USING(psc) JOIN `#obec` USING (kod_obec) 
//      WHERE muzi>{$par->od} AND muzi<={$par->do}
//    ");
//    while ( $sr && list($psc,$nazev)= pdo_fetch_row($sr) ) {
//      $n++;
//      $pscs[$psc]= $nazev;
//      if ( !isset($notes[$psc]) ) $notes[$psc]= 0;
//      $notes[$psc]++;
//    }
//    $pc= round(100*$n/$vsichni);
//    $ret= mapa2_psc($pscs,$notes,1,"CIRCLE,blue,blue,1,8");
//    $ret->title= "Celkem $n ($pc%) iniciovaných žije v obcích s {$par->od} až {$par->do} muži";
//    $ret->rewrite= 0;
//    $ret->suma= $n;
//    $ret->total= $vsichni;
//    break;
  // ------------------------------------------------------ malé obce MS
  case 'malé obce MS':
    $n= $n2= 0;
    $icons= array();
    $vsichni= select('COUNT(*)','`#stat`',"stat='CZ'");
    $sr= pdo_qry("
      SELECT psc,nazev,ms FROM `#stat` JOIN `#psc` USING(psc) JOIN `#obec` USING (kod_obec) 
      WHERE muzi>{$par->od} AND muzi<={$par->do}
    ");
    while ( $sr && list($psc,$nazev,$ms)= pdo_fetch_row($sr) ) {
      if ( $ms>0) {
        $n2++;
        $icons[$psc]= isset($icons[$psc]) ? "CIRCLE,magenta,magenta,1,8" : "CIRCLE,red,red,1,8";
      }
      else {
        $n++;
        $icons[$psc]= isset($icons[$psc]) ? "CIRCLE,magenta,magenta,1,8" : "CIRCLE,blue,blue,1,8";
      }
      $pscs[$psc]= "$nazev $psc";
      if ( !isset($notes[$psc]) ) $notes[$psc]= 0;
      $notes[$psc]++;
    }
    $pc= round(100*($n+$n2)/$vsichni);
    $ret= mapa2_psc($pscs,$notes,1,$icons);
    $ret->title= "Celkem $n+$n2 ($pc%) iniciovaných žije v obcích s {$par->od} až {$par->do} muži";
    $ret->rewrite= 0;
    $ret->suma= $n+$n2;
    $ret->total= $vsichni;
    break;
  }
end:
  return $ret;
}
# -------------------------------------------------------------------------==> . sta2 mrop stat2 gen
# interpretace údajů o absolventech MROP
# par.typ = posloupnost písmen   g=geo informace s=statistika
# par.od-do = pokud je zadáno, omezuje to statistiku na období <od,do)
# do title se píše univerzální nadpis a poznámka (společná pro vývooj v čase)
function sta2_mrop_stat2_see($par,&$title) { trace();
  $typ= isset($par->typ) ? $par->typ : '';
  $msg= '';  
  // ------------------------------ podle velikosti obcí
  if ( strstr($typ,'o')) {
    $meze= array(0,1,10,100,1000,10000,100000,1000000);
    $meze= array(0,300,1000,3000,10000,30000,100000,300000,1000000);
    $meze= array(0,290,576,1084,2230,4650,9700,21200,46600,300000,1000000);
    $muzi= $obce= $ms= array();
    $s_muzi= $s_inic= $s_obce= $s_ms= 0;
    $title= "<h2>Iniciovaní podle velikosti obce - verze 2.0</h2><i>
      sloupec <b>obce podle počtu mužů</b> zobrazuje meze počtu mužů žijících v obci
      <br>sloupec <b>počet</b> je počet takových obcí
      <br>sloupec <b>inic.</b> je počet iniciovaných v takových obcích
      <br>žlutý sloupec <b>%</b> je procento z celkem iniciovaných (v daném období)
      <br>sloupec <b>‰</b> je promile iniciovaných mužů v takových obcích
      <br>sloupec <b>z mužů</b> je počet mužů v takových obcích (meze jsou proto tak kostrbaté aby byly počty srovnatelné)
      <br>sloupec <b>% MS</b> je procento absolventů MS (je jedno jestli před nebo po iniciaci)
      <br><br>Poznámka: odchylka v počtu mužů podle obcí a PSČ vznikla tím,
      že tabulka okresu je z roku 2018, tabulka PSČ a obcí z roku 2020 </i>
      <br><br>";
    $msg.= "<br><table class='stat'><tr><th></th><th>velikost obce</th><th>počet</th>
      <th>inic.</th><th>%</th>
      <th> ‰ </th><th>z mužů</th><th>% MS</th></tr>";
    // celkem iniciovaných
    $cr_inic= select('COUNT(*)','osoba',"iniciace>0");
    // výpočet počtu mužů v obcích dané velikosti
    for ($i=count($meze)-2; $i>=0; $i--) {
      list($m,$o)= select('SUM(muzi),COUNT(*)','`#obec`',
          "muzi BETWEEN $meze[$i] AND {$meze[$i+1]}");
      $s_muzi+= $muzi[$i]= $m;
      $s_obce+= $obce[$i]= $o;
    }
//                                                debug($obce,$s_obce);
    // výpočet počtu iniciovaných v obcích dané velikosti
    $ai= $am= array(); // A-J -> iniciovaní, MS
    $A= ord('A');
    for ($i=count($meze)-2; $i>=0; $i--) {
      $od= $i==0 ? '.' : $meze[$i];
      $do= $i==count($meze)-2 ? '.' : $meze[$i+1];
      // výpočet 
      $inic= $ms_inic= 0;
      $max= array(); // mez -> počet 
      $n= select('COUNT(*)','osoba JOIN osoba_geo USING (id_osoba) JOIN `#obec` USING (kod_obec)',
          "iniciace>0 AND muzi BETWEEN $meze[$i] AND {$meze[$i+1]}");
      $inic+= $n;
      $s_inic+= $n;
      $max[chr($A)]= max($n,$max[chr($A)]);
//      foreach ($pscs as $psc=>$n) {
//        $ok= select('COUNT(*)','`#psc` JOIN `#obec` USING (kod_obec)',
//            "psc=$psc AND muzi BETWEEN $meze[$i] AND {$meze[$i+1]}");
//        if ( $ok ) {
//          $inic+= $n;
//          $s_inic+= $n;
//          $max[chr($A)]= max($n,$max[chr($A)]);
//          if ( isset($mss[$psc])) {
//            $ms_inic+= $mss[$psc];
//            $s_ms+= $mss[$psc];
//          }
//        }
//      }
      $ppm= $muzi[$i] ? round(1000*$inic/$muzi[$i],2) : '?';
      $pms= $inic ? round(100*$ms_inic/$inic) : '?';
      $pi= $inic ? round(100*$inic/$cr_inic) : '?';
      $msg.= "<tr><th>".chr($A)."</th><th>$od..$do</th><td align='right'>$obce[$i]</td>
        <td align='right'>$inic</td>
        <td align='right' $main>$pi</td>
        <td>&nbsp;&nbsp;&nbsp;&nbsp;$ppm</td>
        <td align='right'>$muzi[$i]</td>
        <td>&nbsp;&nbsp;&nbsp;&nbsp;$pms</td>
      </tr>";
      $ai[$A]= $pi;
      $am[$A]= $pms;
      $A++;
    }
//                                                  debug($max);
    $ppm= round(1000*$s_inic/$s_muzi,2);
    $pms= round(100*$s_ms/$s_inic);
    $msg.= "<tr><th></th><th>celkem</th><th align='right'>$s_obce</th>
      <th align='right'>$s_inic</th><th>100</th>
      <th align='right'>&nbsp;&nbsp;&nbsp;&nbsp;$ppm</th><th align='right'>$s_muzi</th>
      <th align='right'>&nbsp;&nbsp;&nbsp;&nbsp;$pms</th>  
    </tr>";
    $msg.= "</table>";
    // pokus o graf
    $tr1= $tr2= '';
    $styl= "vertical-align:bottom;display:inline-block";
    for ($i= ord('A'); $i<$A; $i++) {
      $hi= $ai[$i]*5;
      $hm= $am[$i]*2;
      $w= 15;
//      $wtd= 2*$w+2;
      $wtd= $w;
      $idiv= "<div class='curr_akce' style='height:{$hi}px;width:{$w}px;$styl'></div>";
//      $mdiv= "<div style='background:orange;height:{$hm}px;width:{$w}px;$styl'></div>";
      $tr1.= "<td style='height:170px;width:{$wtd}px;vertical-align:bottom'>$idiv$mdiv</td>";
      $tr2.= "<th>".chr($i)."</th>";
    }
    $msg.= "<br><table class='stat'><tr>$tr1</tr><tr>$tr2</tr></table>";
  }
  return $msg;
}
# --------------------------------------------------------------------------==> . sta2 mrop stat gen
# interpretace údajů o absolventech MROP
# par.typ = posloupnost písmen   g=geo informace s=statistika
# par.od-do = pokud je zadáno, omezuje to statistiku na období <od,do)
# do title se píše univerzální nadpis a poznámka (společná pro vývooj v čase)
function sta2_mrop_stat_see($par,&$title) { trace();
  $typ= isset($par->typ) ? $par->typ : '';
  $msg= '';  
  $main= "style='background:yellow'"; // styl hlavního sloupce
  $AND= isset($par->od) ? (
      $par->od==$par->do ? "AND $par->od=mrop" : "AND $par->od<=mrop AND mrop<$par->do") : '';
  if ( $typ!='x') {
    $n= 0;
    $cr_inic= $vsichni= $veky= 0;
    $pred= $po= $bez_ms= $firms= 0;
    $s_ms_pred= $s_ms_po= $jen_pred= $jen_po= $jen_mrop= $pred_i_po= 0;
    $zenati= $nezenati= $nezenati_znami= $jen_mrop_zenati= 0;
    $svatba_po= $svatba_pred= 0;
    $deti= $deti3plus= 0;
    $ms_org= array(0,0,0,0,0);
    $cizinci= 0;
    $okres= $kraj= $pscs= $mss= array();
    $sr= pdo_qry("
      SELECT id_osoba,vek,mrop,stat,psc,svatba,deti,ms,lk_pred,lk_po,ms_pred,ms_po,m_pred,m_po,j_pred,j_po,firm
      FROM `#stat` WHERE 1 $AND
    ");
    while ( $sr && 
        list($ido,$vek,$mrop,$stat,$psc,$svatba,$det,$ms,
        $lk_pred,$lk_po,$ms_pred,$ms_po,$m_pred,$m_po,$j_pred,$j_po,$firm)
        = pdo_fetch_row($sr) ) {
      $n++;
      $vsichni++;
      // výpočty
      if ( $stat=='CZ' && $psc ) {
        // napřed na MS? nebo až potom
        $s_ms_pred+= $lk_pred ? 1 : 0;
        $s_ms_po+=   $lk_po ? 1 : 0;
        // další
        $x_pred=    $lk_pred+$ms_pred+$m_pred+$j_pred;
        $x_po=      $lk_po+$ms_po+$m_po+$j_po;
        // sumy
        $veky+= $vek;
        $zenat=     $svatba ? 1 : ($det ? 1 : 0);
        $zenati+=   $zenat;
        $firms+= $firm>0 ? 1 : 0;
        if ( !$zenat )
          $nezenati++;
        $bez_ms+=   $lk_pred+$ms_pred+$lk_po+$ms_po ? 0 : $zenat;
        if ( $ms )
          $ms_org[$ms]++;
        $deti+=     $det ? 1 : 0;
        $deti3plus+=$det>=3 ? 1 : 0;
        $nezenati_znami+=   $zenat ? 0 : ( $x_pred+$x_po ? 1 : 0);
        $pred+=     $x_pred;
        $po+=       $x_po;
        $jen_pred+= $x_po   ? 0 : ($x_pred ? 1 : 0);
        $jen_po+=   $x_pred ? 0 : ($x_po   ? 1 : 0);
        $jen_mrop+= $x_pred+$x_po ? 0 : 1;
        $jen_mrop_zenati+= $x_pred+$x_po ? 0 : ($svatba ? 1 : 0);
        $pred_i_po+= $x_pred && !$x_po || !$x_pred && $po ? 1 : 0;
        $svatba_po+=   $svatba<2222 && $svatba>$mrop ? 1 : 0;
        $svatba_pred+= $svatba>0 && $svatba<=$mrop ? 1 : 0;
        $svatba_nevime+= $svatba ? 0 : ($det ? 1 : 0);
        // geo informace
        if ( !isset($pscs[$psc]) ) $pscs[$psc]= 0;
        $pscs[$psc]++;
        if ( $ms ) {
          if ( !isset($mss[$psc]) ) $mss[$psc]= 0;
          $mss[$psc]++;
        }
        $cr_inic++;
        list($k_obec,$k_okres,$k_kraj)= select('kod_obec,kod_okres,kod_kraj','`#psc`',"psc=$psc");
        if ( !$k_kraj ) {
          $msg.= "<br>PSČ $psc neznáme id=".tisk2_ukaz_osobu($ido);
        }
        else {
          if ( !isset($okres[$k_okres])) $okres[$k_okres]= 0;
          $okres[$k_okres]++;
          if ( !isset($kraj[$k_kraj])) $kraj[$k_kraj]= 0;
          $kraj[$k_kraj]++;
        }
      }
      else $cizinci++;
    }
  }
  // ------------------------------ podle věku 
  // údaj % MS znamená byl na MS před iniciací
  if ( strstr($typ,'v')) {
    $meze= array(10,20,30,40,50,60,70,80,90,99);
    $meze= array(15,20,25,30,35,40,45,50,55,60,65,70,75,80,85,90,95,99);
    $stari= array();
    $s_inic= $s_ms= 0;
    // titulek
    $title= "<h3>Iniciovaní podle věku</h3><i>
      sloupec % je procento z celkem iniciovaných, 
      <br>sloupec %MS jen procento těch, kteří před iniciací byli účastníky MS
      </i><br><br>";
    // zobrazení
    $prumer= round($veky/$cr_inic);
    $msg.= "<b>$cr_inic inic. z ČR - $prumer roků</b><br>
      <table class='stat'><tr><th>věk</th><th>počet</th><th>%</th><th>% MS</th></tr>";
    for ($i=0; $i<count($meze)-1; $i++) {
      list($m,$m2)= select('COUNT(*),SUM(IF(lk_pred>0,1,0))','`#stat`',
          "stat='CZ' AND vek >= $meze[$i] AND vek < {$meze[$i+1]} $AND ");
      $s_inic+= $stari[$i]= $m;
      $s_ms+= $m2;
      $od= $i==0 ? '.' : $meze[$i]+1;
      $do= $i==count($meze)-2 ? '.' : $meze[$i+1];
      $pm= $stari[$i] ? round(100*$stari[$i]/$cr_inic) : '-';
      $pm2= $pm < 2 ? '(-)'
          : ($stari[$i] ? round(100*$m2/$stari[$i]) : '-');
      $msg.= "<tr><th>$od..$do</th><td align='right'>$stari[$i]</td>
        <td align='right' $main>&nbsp;&nbsp;&nbsp;&nbsp;$pm</td>
        <td align='right'>&nbsp;&nbsp;&nbsp;&nbsp;$pm2</td>
      </tr>";
    }
    $pm= $cr_inic ? round(100*$s_inic/$cr_inic) : '-';
    $pm2= $s_inic ? round(100*$s_ms/$s_inic) : '-';
    $msg.= "<tr><th>celkem</th><th align='right'>$s_inic</th>
      <th align='right'>&nbsp;&nbsp;&nbsp;&nbsp;$pm</th>
      <th align='right'>&nbsp;&nbsp;&nbsp;&nbsp;$pm2</th>
    </tr>";
    $msg.= "</table>";
  }
  // ------------------------------ statistické informace CPR
  $no_ms= 0;
  $ms_cpr= 0;
  if ( strstr($typ,'c') || strstr($typ,'s')) {
    $sr= pdo_qry("
      SELECT id_osoba,vek,mrop,note,svatba
      FROM `#stat`
      WHERE stat='CZ' AND ms_pred=0 AND ms_po=0 $AND
    ");
    while ( $sr && list($ido,$vek,$mrop,$note,$svatba)= pdo_fetch_row($sr) ) {
      $no_ms++;
      list($cpr_vek,$cpr_svatba)= select('YEAR(narozeni),YEAR(datsvatba)',
          'ezer_cr.osoba JOIN ezer_cr.tvori USING (id_osoba) JOIN ezer_cr.rodina USING (id_rodina) ',
          "CONCAT(jmeno,' ',prijmeni)='$note' AND role='a'");
      $x= $mrop-$vek;
      if ( $cpr_vek && abs($cpr_vek-$x)<2 ) {
        $ms_cpr++;
        $ms_org[4]++;
        display("$note/$cpr_vek..$x $cpr_svatba/$svatba tj. ".($cpr_svatba?($cpr_svatba>$mrop?'po':'před'):'?'));
      }
    }
    if ( strstr($typ,'c') ) {
      $msg.= "$no_ms (asi) ženatých mužů z ČR nebyli na MS pořádaných YS nebo FA
        <br>ale $ms_cpr mohlo podle shody jména být na MS pořádaném CPR
       ";
    }
    $bez_ms-= $ms_cpr;
  }
  // ------------------------------ statistické informce YS + FA + (CPR)
  if ( strstr($typ,'s')) {
    $x1= $nezenati-$nezenati_znami;
    $msg.= "<b>Celkem $n iniciovaných mužů z toho $cizinci cizinci</b>
      <br>další údaje jsou bez cizinců<br>
      <br><b>ženatí</b>
      <br>celkem ženatí = $zenati
      <br>... z toho před MROP = $svatba_pred
      <br>... z toho po MROP = $svatba_po
      <br>... kdy byla svatba nevíme = $svatba_nevime
      <br>... nebyl na MS YS nebo FA nebo CPR = $bez_ms
      <br>... nebyl na žádné akci = $jen_mrop_zenati
      <br>celkem neženatí = $nezenati
      <br>... nebyl na žádné akci = $x1
      <br>
      <br><b>účasti na MS</b>
      <br>... YS = $ms_org[1]
      <br>... FA = $ms_org[2]
      <br>... CPR = $ms_org[4]
      <br>... YS i FA = $ms_org[3]
      <br>
      <br><b>kdy na MS</b>
      <br>... před iniciací = $s_ms_pred
      <br>... po iniciaci = $s_ms_po
      <br>
      <br><b>děti</b>
      <br>mají děti = $deti
      <br>... 3 a více = $deti3plus
      <br>
      <br><b>akce</b>
      <br>byl pouze na akcích před MROP = $jen_pred 
      <br>... pouze na akcích po MROP = $jen_po 
      <br>... byl na akcích před i po = $pred_i_po
      <br>... byl na firmingu = $firms
      <br>nebyl na žádné akci mimo MROP = $jen_mrop
      <br>";
    $title= "<h2>Iniciovaní muži - přehled</h2><i>Poznámka: 
      akcí se rozumí akce pořádaná YS a MS (mimo MROP), případně MS pořádané CPR</i>
     ";
//      <br>celkem účastí na akcích před = $pred 
//      <br>celkem účastí na akcí po = $po
  }
  // ------------------------------ celkově geo
  if ( strstr($typ,'g') || strstr($typ,'o')) {
    $cr_muzi= 0;
    $kraj_muzi= $kraj_ppn= array();
    $okres_muzi= $okres_ppn= array();
    $sr= pdo_qry("SELECT kod_kraj,kod_okres,muzi FROM `#okres`");
    while ( $sr && list($k_kraj,$k_okres,$muzu)= pdo_fetch_row($sr)) {
      $n= isset($okres[$k_okres]) ? $okres[$k_okres] : 0;
      if ( !isset($kraj_muzi[$k_kraj])) $kraj_muzi[$k_kraj]= 0;
      $cr_muzi+= $muzu;
      $kraj_muzi[$k_kraj]+= $muzu;
      $okres_muzi[$k_okres]= $muzu;
    }
    foreach ($kraj as $k_kraj=>$n) {
      $kraj_ppn[$k_kraj]= round(1000*$n/$kraj_muzi[$k_kraj],2);
    }
    arsort($kraj_ppn);
    // geo ČR
    $ppm= round(1000*$cr_inic/$cr_muzi,2);
    $msg.= "<div>celkem $cr_inic tj. $ppm ‰ iniciovaných</div>";
  }
  // ------------------------------ podle velikosti obcí
  if ( strstr($typ,'o')) {
    $meze= array(0,1,10,100,1000,10000,100000,1000000);
    $meze= array(0,300,1000,3000,10000,30000,100000,300000,1000000);
    $meze= array(0,290,576,1084,2230,4650,9700,21200,46600,300000,1000000);
    $muzi= $obce= $ms= array();
    $s_muzi= $s_inic= $s_obce= $s_ms= 0;
    $title= "<h2>Iniciovaní podle velikosti obce</h2><i>
      sloupec <b>obce podle počtu mužů</b> zobrazuje meze počtu mužů žijících v obci
      <br>sloupec <b>počet</b> je počet takových obcí
      <br>sloupec <b>inic.</b> je počet iniciovaných v takových obcích
      <br>žlutý sloupec <b>%</b> je procento z celkem iniciovaných (v daném období)
      <br>sloupec <b>‰</b> je promile iniciovaných mužů v takových obcích
      <br>sloupec <b>z mužů</b> je počet mužů v takových obcích (meze jsou proto tak kostrbaté aby byly počty srovnatelné)
      <br>sloupec <b>% MS</b> je procento absolventů MS (je jedno jestli před nebo po iniciaci)
      <br><br>Poznámka: odchylka v počtu mužů podle obcí a PSČ vznikla tím,
      že tabulka okresu je z roku 2018, tabulka PSČ a obcí z roku 2020 </i>
      <br><br>";
    $msg.= "<br><table class='stat'><tr><th></th><th>velikost obce</th><th>počet</th>
      <th>inic.</th><th>%</th>
      <th> ‰ </th><th>z mužů</th><th>% MS</th></tr>";
    // výpočet počtu mužů v obcích dané velikosti
    for ($i=count($meze)-2; $i>=0; $i--) {
      list($m,$o)= select('SUM(muzi),COUNT(*)','`#obec`',
          "muzi BETWEEN $meze[$i] AND {$meze[$i+1]}");
      $s_muzi+= $muzi[$i]= $m;
      $s_obce+= $obce[$i]= $o;
    }
//                                                debug($obce,$s_obce);
    // výpočet počtu iniciovaných v obcích dané velikosti
    $ai= $am= array(); // A-J -> iniciovaní, MS
    $A= ord('A');
    for ($i=count($meze)-2; $i>=0; $i--) {
      $od= $i==0 ? '.' : $meze[$i];
      $do= $i==count($meze)-2 ? '.' : $meze[$i+1];
      // výpočet 
      $inic= $ms_inic= 0;
      $max= array(); // mez -> počet 
      foreach ($pscs as $psc=>$n) {
        $ok= select('COUNT(*)','`#psc` JOIN `#obec` USING (kod_obec)',
            "psc=$psc AND muzi BETWEEN $meze[$i] AND {$meze[$i+1]}");
        if ( $ok ) {
          $inic+= $n;
          $s_inic+= $n;
          $max[chr($A)]= max($n,$max[chr($A)]);
          if ( isset($mss[$psc])) {
            $ms_inic+= $mss[$psc];
            $s_ms+= $mss[$psc];
          }
        }
      }
      $ppm= $muzi[$i] ? round(1000*$inic/$muzi[$i],2) : '?';
      $pms= $inic ? round(100*$ms_inic/$inic) : '?';
      $pi= $inic ? round(100*$inic/$cr_inic) : '?';
      $msg.= "<tr><th>".chr($A)."</th><th>$od..$do</th><td align='right'>$obce[$i]</td>
        <td align='right'>$inic</td>
        <td align='right' $main>$pi</td>
        <td>&nbsp;&nbsp;&nbsp;&nbsp;$ppm</td>
        <td align='right'>$muzi[$i]</td>
        <td>&nbsp;&nbsp;&nbsp;&nbsp;$pms</td>
      </tr>";
      $ai[$A]= $pi;
      $am[$A]= $pms;
      $A++;
    }
//                                                  debug($max);
    $ppm= round(1000*$s_inic/$s_muzi,2);
    $pms= round(100*$s_ms/$s_inic);
    $msg.= "<tr><th></th><th>celkem</th><th align='right'>$s_obce</th>
      <th align='right'>$s_inic</th><th>100</th>
      <th align='right'>&nbsp;&nbsp;&nbsp;&nbsp;$ppm</th><th align='right'>$s_muzi</th>
      <th align='right'>&nbsp;&nbsp;&nbsp;&nbsp;$pms</th>  
    </tr>";
    $msg.= "</table>";
    // pokus o graf
    $tr1= $tr2= '';
    $styl= "vertical-align:bottom;display:inline-block";
    for ($i= ord('A'); $i<$A; $i++) {
      $hi= $ai[$i]*5;
      $hm= $am[$i]*2;
      $w= 15;
//      $wtd= 2*$w+2;
      $wtd= $w;
      $idiv= "<div class='curr_akce' style='height:{$hi}px;width:{$w}px;$styl'></div>";
//      $mdiv= "<div style='background:orange;height:{$hm}px;width:{$w}px;$styl'></div>";
      $tr1.= "<td style='height:170px;width:{$wtd}px;vertical-align:bottom'>$idiv$mdiv</td>";
      $tr2.= "<th>".chr($i)."</th>";
    }
    $msg.= "<br><table class='stat'><tr>$tr1</tr><tr>$tr2</tr></table>";
  }
  // ------------------------------ iniciovaní okresů a krajů
  if ( strstr($typ,'g')) {
    // titulek
    $title= "<h2>Iniciovaní v krajích a okresech ČR</h2><i>
      sloupec ‰ je promile iniciovaných z mužů v daném kraji nebo okresu, 
      <br>podle tohoto údaje je tabulka také seřazena (v ČR žije $cr_muzi mužů)
      </i><br><br>";
    // geo tabulka krajů
    $msg.= "<table class='stat'><tr><th>kraj</th><th>iniciovaní</th><th> ‰ mužů kraje</th></tr>";
    foreach ($kraj_ppn as $k_kraj=>$ppn) {
      $nazev= select('nazev','`#kraj`',"kod_kraj=$k_kraj");
      $n= $kraj[$k_kraj];
      $msg.= "<tr><th>$nazev</th><td align='right'>$n</td><td>&nbsp;&nbsp;&nbsp;&nbsp;$ppn</td></tr>";
    }
    $msg.= "</table>";
    // geo info - okresy
    foreach ($okres as $k_okres=>$n) {
      $nazev= select('nazev','`#kraj`',"kod_kraj=$k_kraj");
      $okres_ppn[$k_okres]= round(1000*$n/$okres_muzi[$k_okres],2);
    }
    arsort($okres_ppn);
    $msg.= "<h3>Iniciovaní v okresech ČR</h3>
      <table class='stat'><tr><th>okres</th><th>iniciovaní</th><th>‰ mužů okresu</th></tr>";
    foreach ($okres_ppn as $k_okres=>$ppm) {
      $nazev= select('nazev','`#okres`',"kod_okres=$k_okres");
      $n= $okres[$k_okres];
      $msg.= "<tr><th>$nazev</th><td align='right'>$n</td><td>&nbsp;&nbsp;&nbsp;&nbsp;$ppm</td></tr>";
    }
    $msg.= "</table>";
  }
  if ( strstr($typ,'x')) {
    $ido_test= 0;
//    $ido_test= 5877; // Já
    // titulek
    $title= "<h2>Tabulka vzájemného vlivu účasti na akci</h2><i>
      zkratky znamenají typ akce: 
      <br><b>i</b> - iniciace, <b>m</b> - manželáky, <b>k</b> - akce typu Křižanov, 
      <br><b>a</b> - akce typu Albeřice, <b>f</b> - akce typu Fišerka, <b>o</b> - akce pro otce s dětmi, 
      <br><b>r</b> - akce pro staré slony (firming, podpora iniciovaných)
      <br>
      <br>matice A vyjadřuje relaci <b>byl na nějaké ... dříve než na jakékoliv ...</b>
      <br>matice B vyjadřuje relaci <b>byl na ... a následně na ... </b>
      <br>údaje v maticích A% a B% jsou vztaženy k počtu iniciovaných
      <br>
      <br>sloupec &sum; je součet iniciovaných účastných i na akci daného typu
      </i><br><br>";
    // typy akcí
    $ido_cond= $ido_test ? "id_osoba=$ido_test" : '1';
    $akce= str_split('imkafor');
    $_akce= select("GROUP_CONCAT(DISTINCT akce SEPARATOR '')",' `#stat`',"$ido_cond $AND");
    $_akce= strtolower($_akce);
    $_akce= count_chars($_akce,3);    
    $_akce= str_split($_akce);
    // vyhoď neexistující typ
    foreach($akce as $i=>$a) {
      if ( !in_array($a,$_akce) ) unset($akce[$i]);
    }
    foreach(array('A%','B%','A','B') as $AB) {
      // matice A
      $m= $s= array();
      foreach($akce as $x) {
        $s[$x]= 0;
        foreach($akce as $y) {
          $m[$x][$y]= 0;
        }
      }
      // probrání #stat
      $sr= pdo_qry("SELECT akce,id_osoba,note FROM `#stat` WHERE akce!='' AND $ido_cond $AND");
      while ( $sr && list($xy,$ido,$name)= pdo_fetch_row($sr)) {
        if ( $ido_test && $ido!=$ido_test ) continue;
        if ($ido_test) display("$name: $xy");
        foreach($akce as $x) {
          $ix= stripos($xy,$x);
          if ( $ix!==false ) {
            $s[$x]++;
          }
          $offset= $AB[0]=='A' ? 0 : min($ix+1,strlen($xy)-1); 
          foreach($akce as $y) {
            $iy= stripos($xy,$y,$offset);
            if ( $ix!==false && $iy!==false && $ix<$iy) {
              $m[$x][$y]++;
            }
          }
        }
      }
      // převod na procenta pro tabulku X%
      if ( $AB[1]=='%') {
        $si= $s['i'];
        foreach($akce as $x) {
          foreach($akce as $y) {
            $m[$x][$y]= $m[$x][$y] ? round(100*$m[$x][$y]/$si) : '-';
          }
          $s[$x]= $s[$x] ? round(100*$s[$x]/$si) : '-';
        }
      }
      // tabulka 
      $tab= "<table class='stat'>";
      $th=  '';
      foreach($akce as $y) {
        $th.= "<th>$y</th>";
      }
      $tab.= "<tr><th>$AB</th><th>&sum;</th>$th</tr>";
      foreach($akce as $x) {
        $td= '';
        foreach($akce as $y) {
          $style= $y=='i' ? $main : '';
          $tx= $x==$y ? 'th' : 'td';
          $td.= "<$tx align='right' $style>{$m[$x][$y]}</$tx>";
        }
        $tab.= "<tr><th>$x</th><th align='right'>{$s[$x]}</th>$td</tr>";
      }
      $tab.= "</table>";
      $msg.= "<h3>Matice $AB</h3>$tab";
    }
    // trasování
    $dbg= array();
    $sr= pdo_qry("SELECT COUNT(*) AS _pocet,akce FROM `#stat` WHERE $ido_cond 
      GROUP BY akce ORDER BY _pocet DESC");
    while ( $sr && list($n,$xy)= pdo_fetch_row($sr)) {
      $dbg[$xy]= $n;
    }
//    debug($dbg);
  }
  return $msg;
}
# --------------------------------------------------------------------------==> . sta2 mrop stat gen
# agregace údajů o absolventech MROP
# pokud je par.x=a jen doplní položku akce
function sta2_mrop_stat_gen($par) {
  $ido_test= 0;
//  $ido_test= 5877; // Já
//  $ido_test= 26;  // Ludva
  $msg= "";
  $par_x= isset($par->x) ? $par->x : 'x';
  switch ($par_x) {
  case 'x': // --------------------------------------- osobní a staré pred/po položky
    // vynulování pracovní tabulky #stat
    query("TRUNCATE TABLE `#stat`");
    // seznam
    $mrops= array( // year => datum ... 2001,2002 nejsou v akcích
      2001=>'2001-08-01',2002=>'2002-09-01');
    $ms= array();
    // získání data mrop
    $mr= pdo_qry("
      SELECT YEAR(a.datum_od) AS _rok,a.datum_od FROM akce AS a 
      WHERE a.mrop=1 
      --  AND id_duakce=1373
      ORDER BY _rok
    ");
    while ( $mr && list($rok,$datum)= pdo_fetch_row($mr) ) {
      $mrops[$rok]= $datum;
    }
//    $mrops= array(2021=>'2021-09-15');
    // získání individuálních a rodinných údajů
    foreach ($mrops as $mrop=>$datum) {
      $n_iniciovani= select('COUNT(*)','osoba',"iniciace=$mrop");
      if ($n_iniciovani) {
        // dokončený ročník
        $AND= "AND o.iniciace=$mrop";
        $JOIN= '';
      }
      else {
        // iniciace v běhu
        $ida= select('id_duakce','akce',"mrop=1 AND YEAR(datum_od)='$mrop'");
        $AND= "AND o.iniciace=0 AND p.funkce=0";
        $JOIN= "JOIN pobyt AS p ON id_akce=$ida JOIN spolu AS s USING (id_osoba,id_pobyt)";
      }
//      $AND.= " AND o.id_osoba=4881";
      $mr= pdo_qry("
        SELECT o.id_osoba,
          IF(o.kontakt AND o.email!='',MD5(REGEXP_SUBSTR(UPPER(TRIM(o.email)),'^[A-Z0-9._%-]+@[A-Z0-9.-]+\.[A-Z]+')),''),
          o.access,CONCAT(o.jmeno,' ',o.prijmeni),
          -- ROUND(DATEDIFF('$datum',o.narozeni)/365.2425) AS _vek,
          ROUND(IF(MONTH(o.narozeni),DATEDIFF('$datum',o.narozeni)/365.2425,YEAR('$datum')-YEAR(o.narozeni))) AS _vek,
          IFNULL(svatba,0) AS _s1,IFNULL(YEAR(datsvatba),0) AS _s2,
          MIN(IFNULL(YEAR(od.narozeni),0)) AS _s3,
          SUM(IF(td.id_osoba,1,0)) AS _d,
          IFNULL(tb.id_osoba,0) AS _ido_z,
          IF(o.adresa=1,o.stat,r.stat) AS _stat,
          IF(o.adresa=1,o.psc,r.psc) AS _psc,
          o.firming
        FROM osoba AS o
        $JOIN
        LEFT JOIN tvori AS ta ON ta.id_osoba=o.id_osoba AND ta.role='a'
        LEFT JOIN rodina AS r ON r.id_rodina=ta.id_rodina
        LEFT JOIN tvori AS tb ON r.id_rodina=tb.id_rodina AND tb.role='b'
        LEFT JOIN tvori AS td ON r.id_rodina=td.id_rodina AND td.role='d'
        LEFT JOIN osoba AS od ON od.id_osoba=td.id_osoba
        WHERE o.deleted='' $AND
        GROUP BY o.id_osoba
      ");
      while ( $mr && 
          list($ido,$md5o,$access,$name,$vek,$sv1,$sv2,$sv3,$deti,$ido_z,$stat,$psc,$firm)
          = pdo_fetch_row($mr) ) {
        if ( $ido_test && $ido!=$ido_test ) continue;
        $ms[$ido]= (object)array('name'=>$name,'mrop'=>$mrop);
        // rozbor bydliště
        $stat= str_replace(' ','',$stat);
        $psc= str_replace(' ','',$psc);
        if ( $psc && is_numeric($psc)) {
          if ( $stat=='' && in_array($psc[0],array(1,2,3,4,5,6,7)) ) {
            $stat= 'CZ';
          }
          elseif ( $stat=='' && in_array($psc[0],array(0,8,9)) ) {
            $stat= 'SK';
          }
        }
        elseif ( $stat=='' ) {
          $stat= '?';
        }
        // zápis do #stat
        $sv= max($sv1,$sv2);
        if ( !$sv && $ido_z ) {
          $sv= $sv3 ? $sv3-1 : 9999;
        }
        query("INSERT INTO `#stat` (id_osoba,md5_osoba,access,mrop,firm,stat,psc,vek,svatba,deti,note) 
          VALUE ($ido,'$md5o',$access,$mrop,$firm,'$stat','$psc','$vek',$sv,$deti,'$name')");
      }
    }
    // získání informací o akcích- staré
    $akce_muzi= "24,5,11";
    $akce_manzele= "2,3,4,17,18,22"; // 18 je lektoři & vedoucí MS !!! TODO
    foreach ($ms as $ido=>$m) {
      if ( $ido_test && $ido!=$ido_test ) continue;
      $ma= pdo_qry("
        SELECT IF(datum_od<CONCAT({$m->mrop},'-09-11'),'_pred','_po'),
          CASE WHEN druh IN (1) THEN 'lk' 
               WHEN druh IN ($akce_manzele) THEN 'ms' 
               WHEN druh IN ($akce_muzi) THEN 'm' 
               ELSE 'j' END,
          statistika,mrop,firm,a.access
        FROM pobyt AS p
        LEFT JOIN akce AS a ON id_akce=id_duakce
        LEFT JOIN spolu AS s USING (id_pobyt)
        JOIN osoba AS o USING (id_osoba)
        WHERE id_osoba=$ido AND spec=0 AND mrop=0 AND zruseno=0 
        ORDER BY datum_od
      ");
      while ( $ma && list($kdy,$druh,$stat,$mrop,$firm,$org)= pdo_fetch_row($ma) ) {
        // zápis do #stat - mimo iniciaci
        $org_pred= $kdy=='_pred'
            ? ($org==1 ? ',ys_pred=ys_pred+1' : ($org==2 ? ',fa_pred=fa_pred+1' : '')) : '';
        query("UPDATE `#stat` SET $druh$kdy=1+$druh$kdy$org_pred WHERE id_osoba=$ido");
      }
      // kde byl na MS
      $ma= pdo_qry("
        SELECT COUNT(*),BIT_OR(a.access)
        FROM pobyt AS p
        LEFT JOIN akce AS a ON id_akce=id_duakce
        LEFT JOIN spolu AS s USING (id_pobyt)
        JOIN osoba AS o USING (id_osoba)
        WHERE id_osoba=$ido AND a.druh IN (1,2) AND spec=0 AND zruseno=0 
      ");
      while ( $ma && list($n,$org)= pdo_fetch_row($ma) ) {
        // zápis do #stat
        if ( $n )
          query("UPDATE `#stat` SET ms=$org WHERE id_osoba=$ido");
      }
    }
    $msg= "vytvořena tabulka #stat";
    break;
  case 'a': // ---------------------------------------- posloupnost akcí
    // vymaž starou statistiku akcí
    query("UPDATE `#stat` SET akce='' ");
    // vypočítej novou statistiku akcí
    $sr= pdo_qry("SELECT id_osoba,note FROM `#stat` ");
    while ( $sr && list($ido,$name)= pdo_fetch_row($sr) ) {
      if ( $ido_test && $ido!=$ido_test ) continue;
      $akce= $last_a= $opak= '';
      $ma= pdo_qry("
        SELECT statistika,druh,IF(funkce>0,0,mrop),firm
        FROM pobyt AS p
        LEFT JOIN akce AS a ON id_akce=id_duakce
        LEFT JOIN spolu AS s USING (id_pobyt)
        JOIN osoba AS o USING (id_osoba)
        WHERE id_osoba=$ido AND spec=0 AND zruseno=0 
        ORDER BY datum_od
      ");
      while ( $ma && list($stat,$druh,$mrop,$firm)= pdo_fetch_row($ma) ) {
        // tvorba posloupnosti akcí - mrop a firm zapíšeme jen první
        $a=  $mrop    ? 'i' : '';
        $a.= $stat==1 ? 'k' : '';
        $a.= $stat==2 ? 'a' : '';
        $a.= $stat==3 ? 'f' : '';
        $a.= $stat==4 ? 'o' : '';
        $a.= ($stat==5 || $firm) ? 'r' : '';
        $a.= $druh==1 ? 'm' : '';
        if ( $a ) {
            $akce.= $a;
//          if ( $a!=$last_a) {
//            $akce.= ($opak ? strtoupper($last_a) : $last_a);
//            $last_a= $a;
//            $opak= 0;
//          }
//          else {
//            $opak++;
//          }
        }
      }
      $akce.= $last_a ? ($opak ? strtoupper($last_a) : $last_a) : '';
      query("UPDATE `#stat` SET akce='$akce' WHERE id_osoba=$ido");
      if ($ido_test) display("$name: $akce");
    }
    $msg= "přepočítána položka #stat.akce";
    break;
  }
end:  
  return $msg;
}
/** ===================================================================================> FILEBROWSER */
# ------------------------------------------------------------------------------------ tut ma_archiv
# SHOW LOAD
// je volané metodou show.load - vrátí informace, zda existuje archiv akce v Synology
function tut_ma_archiv ($table,$idkey,$keys,$root) {
  $values= array();
  foreach ($keys as $key) {
    list($kod,$rok)= select(
        'IF(ga2.g_kod,ga2.g_kod,da2.ciselnik_akce),YEAR(datum_od)',
        "akce AS da2 LEFT JOIN join_akce AS ja2 ON ja2.id_akce=da2.id_duakce 
	LEFT JOIN g_akce AS ga2 USING(g_rok,g_kod) ",
        "$idkey=$key");
    $y= tut_dir_find ($root,$rok,$kod);
    $values[]= $y->ok ? 1 : 0;
  }
  return $values;
}
# ---------------------------------------------------------------------------------------- tut mkdir
// vytvoří adresář
function tut_mkdir ($root,$rok,$kod,$slozka,$podslozka='') {  trace();
  $base= "{$root}Akce/$rok";
  if ( !$podslozka ) { 
    // základní složka archivu - odstraň zakázané znaky (Win,Mac,Linux)
    $slozka= strtr($slozka,'\/:*"<>|?%',"----'()---");
    // základní složka
    if ( !is_dir($base)) { 
      // případně založ rok
      mkdir($base);
                                                display("založen rok $base");
    }
    $y= tut_dir_find($root,$rok,$kod);
    if ( $y->ok==0 ) {
      // akce s tímto kódem ještě nemá složku
      $path= "$base/$kod - $slozka";       
    }
    else {
      fce_warning("POZOR: archiv akce již existuje: $base/$kod ...");
      goto end;
    }
  }
  else {
    // podsložka - odstraň zakázané znaky
    $podslozka= strtr($podslozka,'\/:*"<>|?%',"----'()---");
    $path= "$base/$slozka/$podslozka";
  }
  // vlastní vytvoření složky
  if ( stristr(PHP_OS,'WIN') && substr(PHP_VERSION_ID,0,1)=='5' ) 
    // windows a PHP5 používají cp1250
    $path= iconv("UTF-8","Windows-1250",$path);
                                                display("path=$path");
  $ok= mkdir($path) ? 1 : 0;
end:
  return $ok;
}
# ------------------------------------------------------------------------------------- tut dir_find
// nalezne adresář akce
function tut_dir_find ($root,$rok,$kod) {  
  $y= (object)array('ok'=>1);
  $patt= "{$root}Akce/$rok/$kod*";
  $fs= simple_glob($patt);
  if (!$fs) return $y;
  $file= $fs[0];
//                                                debug($fs,$patt);
  if ( count($fs)==1 ) {
    if ( stristr(PHP_OS,'WIN') && substr(PHP_VERSION_ID,0,1)=='5' ) // windows
      $file= iconv("Windows-1250","UTF-8",$file);  
    $y->aroot= "{$root}Akce/$rok/";
    $y->droot= mb_substr(strrchr($file,'/'),1);
  }
  else {
    $y->ok= count($fs);
  }
//                                                debug($y,strrchr($fs[0],'/'));
  return $y;
}
# ---------------------------------------------------------------------------------------- tut files
// vrátí soubory adresáře
function tut_files ($root,$rel_path) {  trace();
  global $ezer_path_root, $rel_root;
  $abs_path= "$root/$rel_path";
  $html= '';
  if ( $rel_path && is_dir($abs_path) ) {
    $files= array();
    $folders= array();
    if (($dh= opendir($abs_path))) {
      while (($file= readdir($dh)) !== false) {
        if ( $file!='.') {
          if ( is_dir("$abs_path/$file") ) {
            if ( stristr(PHP_OS,'WIN') && substr(PHP_VERSION_ID,0,1)=='5' ) // windows
              $file= iconv("Windows-1250","UTF-8",$file);  
            $folders[]= "<li>[$file]</li>";
          }
          else {
            if ( stristr(PHP_OS,'WIN') && substr(PHP_VERSION_ID,0,1)=='5' ) // windows
              $file= iconv("Windows-1250","UTF-8",$file);  
//            $afile= "<a href='$rel_path/$file' target='doc'>$file</a>";
            $cmd= "Ezer.run.$._call(0,'db2.akce2.lst.page.files.Menu','viewer','$file','$abs_path');";
            $onclick= "onclick=\"$cmd; return false;\"";
            $onright= "oncontextmenu=\"Ezer.fce.contextmenu([
              ['stáhnout',function(el){ $cmd }]
              ],arguments[0],null,null,this);return false;\"";
            $files[]= "<li class='file' $onclick $onright>$file</li>";
          }
        }
      }
      closedir($dh);
    }
    $html= 
        "<ul style='list-style-type:none;padding:0;margin:0'>"
          .implode('',array_merge($folders,$files))
        .'</ul>';
  }
  return $html;
}
# ------------------------------------------------------------------------------------- tut file_url
// vrátí soubory adresáře
function tut_file_url ($dir,$name) {  trace();
  global $ezer_root;
  $_SESSION[$ezer_root]['path_file']= $dir;
  $url= "db2/file.php?title=$name";
  return $url;
}
# ------------------------------------------------------------------------------------------ tut dir
// vrátí adresářovou strukturu pro zobrazení metodou area.tree_show
//   node:  {prop:{text:<string>,down:nodes}}
//   nodes: [ node, ... ]
function tut_dir ($base,$folder) {  trace();
  $tree= null;
  if ( $base && $folder ) {
    if ( stristr(PHP_OS,'WIN') && substr(PHP_VERSION_ID,0,1)=='5' ) // windows
      $folder= iconv("UTF-8","Windows-1250",$folder);
    $tree= tut_dir_walk ($base,$folder);
  //                                                  debug($tree);
  }
  return $tree;
}
function tut_dir_walk($base,$root) {  trace();
  $path= $base.$root;
//                                                  display("is_dir($path)=".is_dir($path));
  if ( is_dir($path) ) {
    $files= array();
    if (($dh= opendir($path))) {
      while (($file= readdir($dh)) !== false) {
        if ( $file!='.' && $file!='..' ) {
          $subtree= tut_dir_walk($path.'/',$file);
          if ( $subtree )
            $files[]= $subtree;
        }
      }
      closedir($dh);
    }
    if ( stristr(PHP_OS,'WIN') && substr(PHP_VERSION_ID,0,1)=='5' ) // windows
      $root= iconv("Windows-1250","UTF-8",$root);  
    return (object)array('prop'=>(object)array('id'=>$root),'down'=>$files);
  }
  else {
    return null;
  }
}
/*
# ------------------------------------------------------------------------------------------ session
# getter a setter pro _SESSION
function session($is,$value=null) {
  $i= explode(',',$is);
  if ( is_null($value) ) {
    // getter
    switch (count($i)) {
    case 1: $value= $_SESSION[$i[0]]; break;
    case 2: $value= $_SESSION[$i[0]][$i[1]]; break;
    case 3: $value= $_SESSION[$i[0]][$i[1]][$i[2]]; break;
    }
  }
  else {
    // setter
    switch (count($i)) {
    case 1: $_SESSION[$i[0]]= $value; break;
    case 2: $_SESSION[$i[0]][$i[1]]= $value; break;
    case 3: $_SESSION[$i[0]][$i[1]][$i[2]]= $value; break;
    }
//    session_commit();
    $value= 1;
  }
  return $value;
}
*/
/** ==============================================================================> ONLINE PŘIHLÁŠKY **/
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
# --------------------------------------------------------------------------------------- prihl show
# vrátí tabulku osobních otázek páru
function prihl_show($idp,$idpr) { trace();
  $html= 'pobyt nevznikl online přihláškou';
  list($idr,$json)= select('i0_rodina,web_json','pobyt',"id_pobyt=$idp");
  if (!$json || !$idr) goto end;
  $x= json_decode($json);
  debug($x);
  $m= $z= (object)array();
  foreach ($x->cleni as $ido=>$clen) {
    $role= select('role','tvori',"id_rodina=$idr AND id_osoba=$ido");
    if ($role=='a') { $m= $clen; $idm= $ido; }
    if ($role=='b') { $z= $clen; $idz= $ido; }
  }
  if ($idpr) {
    $vars_json= select('vars_json','prihlaska',"id_prihlaska=$idpr");
    $vars= json_decode($vars_json);
    if ($vars===null) {
      $json_error= json_last_error_msg();
      $m_telefon= $z_telefon= $m_email= $z_email= "";
    }
    else {
      $json_error= '';
      $get= function ($fld,$ido) use ($vars) {
        $pair= $vars->cleni->$ido;
        if (isset($pair->$fld)) {
//          $v= trim(is_array($pair->$fld) ? ($pair->$fld[1] ?? $pair->$fld[0]) : $pair->$fld);
          $v= is_array($pair->$fld) ? ($pair->$fld[1] ?? '') : '';
        }
        else $v= false;
        return $v;
      };
      $m_telefon= $get('telefon',$idm); $z_telefon= $get('telefon',$idz);
      $m_email= $get('email',$idm); $z_email= $get('email',$idz);
    }
  }
  $udaje= [
//    ['- kontakt', $m_kontakt, $z_kontakt],
    ['* email',   $m_email, $z_email],
    ['* telefon', $m_telefon, $z_telefon],
    ['Povaha',    $m->Xpovaha, $z->Xpovaha],
    ['Manželství',$m->Xmanzelstvi, $z->Xmanzelstvi],
    ['Očekávám',  $m->Xocekavani, $z->Xocekavani],
    ['Rozveden',  $m->Xrozveden, $z->Xrozveden],
  ];
  $html= "<table class='stat' style='font-size:12px;height:100%'>
    <tr><th></th><th width='50%'>Muž</th><th width='50%'>Žena</th></tr>";
  if ($json_error)
    $html.= "<tr><th style='color:red'>JSON</th><td colspan=2 align='center'>$json_error</td></tr>";
  foreach ($udaje as $u) {
    if ($u[1]||$u[2])
      $html.= "<tr><th>$u[0]</th><td>$u[1]</td><td>$u[2]</td></tr>";
  }
  $html.= "</table>";
  
end:
  return $html;  
}
# --------------------------------------------------------------------------------------- prihl open
# vrátí seznam otevřených přihlášek dané akce
function prihl_open($ida) { trace();
  $html= $znami= $novi= '';
  $rp= pdo_qry("SELECT LOWER(p.email) AS _email,IFNULL(GROUP_CONCAT(DISTINCT s.id_pobyt),0) AS _naakci
        ,IFNULL(MAX(id_rodina),0) AS _rodina,IFNULL(GROUP_CONCAT(DISTINCT nazev),'?') AS _nazev
        ,IFNULL(MAX(o.id_osoba),0) AS _osoba,IFNULL(CONCAT(o.prijmeni,' ',o.jmeno),'?')
        ,DATE_FORMAT(MIN(open),'<b>%d.%m</b> %H:%i') AS _open
        ,GROUP_CONCAT(DISTINCT stav ORDER BY p.id_prihlaska) AS _stavy
        ,MAX(p.id_prihlaska) AS _id_prihlaska
        ,COUNT(*) AS x
        ,MIN(open) AS _open_
        ,MAX(p.id_pobyt) AS _pobyt
      FROM prihlaska AS p
      LEFT JOIN rodina USING (id_rodina)
      LEFT JOIN osoba AS o ON o.email LIKE CONCAT('%',p.email,'%')
      LEFT JOIN pobyt AS pa ON pa.id_akce=$ida 
      LEFT JOIN spolu AS s ON s.id_osoba=o.id_osoba AND pa.id_pobyt=s.id_pobyt
      WHERE p.id_akce=$ida AND p.email!='' -- AND p.email NOT REGEXP '(smidek)'
      -- AND p.id_prihlaska>110
      GROUP BY _email
      HAVING _stavy NOT REGEXP '^ok|,ok|-ok' AND _naakci=0
      ORDER BY _open_ DESC");
  while ($rp && (list($email,$naakci,$idr,$rodina,$ido,$osoba,$kdy,$stavy)= pdo_fetch_array($rp))) {
    $_ido= $ido ? tisk2_ukaz_osobu($ido) : '';
    $_idr= $idr ? tisk2_ukaz_rodinu($idr) : '';
    $pokusy= substr($stavy,0,50).(substr($stavy,50) ? ' ...' : '');
    $row= "<tr><td>$kdy</td><td>$email</td><td>$osoba $_ido</td><td>$rodina $_idr</td>"
        . "<td title='$stavy'>$pokusy</td></tr>";
    if ($ido) $znami.= "\n$row"; else $novi.= "\n$row";
  }
  $html.= "<h3>Nedokončené přihlášky nováčků</h3><table>$novi</table>";
  $html.= "<h3>Nedokončené přihlášky známých</h3><table>$znami</table>";
  return $html;
}

/* 

//# INVARIANT - k pobytu existuje nejvýše jeden s ním svázaný záznam 
//# -------------------------------------------------------------------------------------- oform start
//# transformace pobyt.entry_id na pobyt_wp
//function oform_start () {
//  global $ezer_db;
//  query("TRUNCATE TABLE pobyt_wp");
//  $fr= pdo_qry("SELECT entry_id,id_pobyt,id_akce
//      FROM ezer_db2.pobyt 
//      WHERE entry_id>0
//    ");
//  while ( $fr && (list($eid,$idp,$ida)= pdo_fetch_array($fr)) ) {
//    query("INSERT INTO pobyt_wp (entry_id,id_akce,id_pobyt,stav,zmeny) VALUE ($eid,$ida,$idp,1,'{}')");
//  }
//}
//# ------------------------------------------------------------------------------------- oform change
//function oform_change ($eid,$stav) {
//  $err= '';
//  if ($stav==1) {
//    // pro změnu na 1 se ubezpeč, že neexistuje jiná pro stejný pobyt
//    $idp= select('id_pobyt','pobyt_wp',"entry_id=$eid"); 
//    if ($idp) {
//      $bound= select('entry_id','pobyt_wp',"entry_id!=$eid AND id_pobyt=$idp AND stav=1"); 
//      if ($bound) {
//        $err= "POZOR pro tento pobyt je již přiřazena jiná přihláška - nejprve ji zneplatni";
//        goto end;
//      }
//    }
//  }
//  query("UPDATE pobyt_wp SET stav=$stav WHERE entry_id=$eid");
//end:  
//  return $err;
//}
//# --------------------------------------------------------------------------------------- oform load
//# načte dosud nenačtené online přihlášky do pobyt_wp
//function oform_load ($ida,$idfs) { trace();
//  $html= ''; // zpráva o doplnění
//  $n= 0;
//  if (!$idfs) goto end;
//  $wr= pdo_qry("SELECT wp.entry_id
//      FROM wordpress.wp_3_wpforms_entries AS wp
//      LEFT JOIN ezer_db2.pobyt_wp AS p_wp USING (entry_id)
//      WHERE form_id IN ($idfs) AND ISNULL(p_wp.entry_id)");
//  while ($wr && (list($eid)= pdo_fetch_row($wr))) {
//    query("INSERT INTO pobyt_wp (entry_id,id_akce,id_pobyt,stav,zmeny) VALUE ($eid,$ida,0,-1,'{}')");
//    $n++;
//  }
//  $html= $n ? "Bylo doplněno $n nových online přihlášek" : "... nic nového";
//end:  
//  return $html;
//}
//# --------------------------------------------------------------------------------------- oform show
//# ukáže obsah online přihlášky a vrátí kontext
//# (k pobytu existuje nejvýše jeden s ním svázaný záznam - a má stav 1)
//function oform_show ($idfs,$idp) { trace();
//  $html= '';
//  $zapsano= 0;
//  $zmena= 0;
//  if (!$idfs) goto end;
//  // doplňované údaje - kopie $x_udaje z oform_save
//  $x_udaje= array(
//    'x_povaha'      => array(84,97),  
//    'x_manzelstvi'  => array(89,101),
//    'x_ocekavam'    => array(88,102),
//  );
//  $udaje_x= array();
//  foreach ($x_udaje as $fld=>list($im,$iz)) {
//    $udaje_x[$im]= 'm'.substr($fld,1);
//    $udaje_x[$iz]= 'z'.substr($fld,1);
//  }
//  debug($udaje_x);
//  // zjistíme, jestli již je svázaný záznam přes pobyt_wp
//  $eid= $stav= '';
//  // stav: 0,1,2 ... -1 zde být nemůže
//  list($eid,$stav)= select('entry_id,stav','pobyt_wp',
//      "id_pobyt=$idp AND stav!=2 ORDER BY stav DESC LIMIT 1"); 
//  if (!$eid) {
//    // pokud ne nalezení formuláře podle jmen
//    $muz= osoba_jmeno($idp,'a');
//    $zena= osoba_jmeno($idp,'b');
//    display("a:$muz b:$zena");
//    $eid= select('entry_id','wordpress.wp_3_wpforms_entry_fields JOIN pobyt_wp USING (entry_id)',
//        "form_id IN ($idfs) AND (value='$muz' OR value='$zena') AND stav!=2
//          -- AND field_id>73 /+ předchozí záznamy byly testovací při vývoji formuláře +/");
//    if ($eid) {
//      query("UPDATE pobyt_wp SET id_pobyt=$idp,stav=0 WHERE entry_id=$eid");
//    }
//  }
//  if ($eid) {  
//    $json= select('fields','wordpress.wp_3_wpforms_entries',"entry_id=$eid ");
//    $flds= json_decode($json);
//    $zmeny= select('zmeny','pobyt_wp',"id_pobyt=$idp AND entry_id=$eid");
//    $zmeny= (array)json_decode($zmeny,true);
//    debug($zmeny);
//    foreach ((array)$flds as $i=>$x) {
////      debug($x);
//      if (isset($zmeny[$udaje_x[$i]]) && $zmeny[$udaje_x[$i]]!=$x->value) {
//        $zpusob= $x->value ? 'změna' : 'doplnění';
//        $html.= "<p style='background:orange'>$zpusob $x->name: <b>{$zmeny[$udaje_x[$i]]}</b></p>";
//        $zmena++;
//      }
//      if (!$x->value) continue;
//      if ($x->value=='Již jsem se kurzu zúčastnil. Mé kontaktní ani ostatní údaje v evidenci se nezměnily.') continue;
//      if ($x->value=='Již jsem se kurzu zúčastnila. Mé kontaktní ani ostatní údaje v evidenci se nezměnily.') continue;
//      if (in_array($x->name,array('zena-jmeno','Adresa bydliště','Datum svatby'))) 
//          $html.= '<hr>';
//      $html.= "<p>$x->name: <b>$x->value</b></p>";
//    }
//    // bylo zapsáno do pobyt?
//    $stav_slovne= $stav==2 ? 'Zrušená' : ($stav==1 ? 'Uložená' : 'Neuložená');
//    $warn= $zmena ? " <span style='background:orange'>změněná</span> " : '';
//    $html= "<p><b><u>$stav_slovne $warn online přihláška č.$eid</u></b></p>$html";
//  }
//  else {
//    $html= "<p><b><u>Online přihláška nenalezena</u></b></p>$html";
//  }
//end:  
//  return (object)array('html'=>$html,'entry_id'=>$eid?:0,'zapsano'=>$zapsano);
//}
//# --------------------------------------------------------------------------------- oform save_zmeny
//# vytvoří obraz přihlášky včetně odsouhlasených údajů z minula podle db
//# cmd= pdf|fld pokud fld, tak vrátí objekt {fld:value,...}
//#                                            kde fld=x_povaha,x_manzelstvi,x_ocekavam a x je m|z
//function oform_save_zmeny ($flds) { trace();
//  $id_pobyt_wp= $flds->id_pobyt_wp; 
//  unset($flds->id_pobyt_wp);
//  $flds2= (object)array();
//  foreach ($flds as $i=>$x) {
//    $flds2->$i= pdo_real_escape_string($x);
//  }
//  $json= json_encode($flds2,JSON_UNESCAPED_UNICODE);
//  query("UPDATE pobyt_wp SET zmeny='$json' WHERE id_pobyt_wp=$id_pobyt_wp ");
//}
//# --------------------------------------------------------------------------------------- oform save
//# vytvoří obraz přihlášky včetně odsouhlasených údajů z minula podle db
//# cmd= pdf|fld pokud fld, tak vrátí objekt {fld:value,...}
//#                                            kde fld=x_povaha,x_manzelstvi,x_ocekavam a x je m|z
//function oform_save ($idfs,$idp,$cmd='pdf') { trace();
//  $html= '';
//  $flds= array();
//  // nalezení akce
//  list($nazev,$rok)= select('nazev,YEAR(datum_od)','pobyt JOIN akce ON id_akce=id_duakce',"id_pobyt=$idp");
//  // nalezení formuláře
//  $idr= select('i0_rodina','pobyt',"id_pobyt=$idp");
//  list($eid,$id_pobyt_wp,$zmeny)= select('entry_id,id_pobyt_wp,zmeny','pobyt_wp',"id_pobyt=$idp AND stav IN (0,1)");
//  if ($eid) {
//    $x= $dn= $pn= array();
//    $m= $z= (object)array();
//    $html.= "<h3 style=\"text-align:center;\">Údaje z online přihlášky na akci \"$nazev $rok\"</h3>";
//    $html.= "<p style=\"text-align:center;\"><i>doplněné dříve svěřenými osobními údaji</i></p>";
//    $qf= pdo_qry("SELECT field_id,value 
//      FROM wordpress.wp_3_wpforms_entry_fields
//      WHERE entry_id=$eid AND form_id IN ($idfs)
//        AND field_id>73 /+ předchozí záznamy byly testovací při vývoji formuláře +/
//    ");
//    while ($qf && ($f= pdo_fetch_object($qf))) {
//      $x[$f->field_id]= $f->value;
//    }
////    debug($x,'X');
//    // zjištění rodinných údajů
//    list($r_adresa,$r_spz,$r_datsvatba)= select("CONCAT(ulice,', ',psc,' ',obec),spz,datsvatba",
//        'rodina',"id_rodina=$idr"); 
//    $r_datsvatba= sql_date1($r_datsvatba);
//    // zjištění osobních údajů
//    $cirkev=  map_cis('ms_akce_cirkev','zkratka');  
//    $vzdelani=  map_cis('ms_akce_vzdelani','zkratka');  
//    $qo= pdo_qry("SELECT role,kontakt,adresa,IF(ISNULL(id_spolu),0,1) AS nakurzu,
//          CONCAT(jmeno,' ',prijmeni,IF(rodne!='',CONCAT(' roz. ',rodne),'')) AS jmeno,
//          narozeni,telefon,email,obcanka,zajmy,jazyk,
//          zamest,cirkev,aktivita,vzdelani,osoba.note
//        FROM rodina
//        JOIN tvori USING (id_rodina) JOIN osoba USING (id_osoba)
//        LEFT JOIN spolu ON spolu.id_osoba=tvori.id_osoba AND id_pobyt=$idp 
//        WHERE id_rodina=$idr
//        ORDER BY narozeni
//      ");
//    while ($qo && ($o= pdo_fetch_object($qo))) {
//      $o->narozeni= sql_date1($o->narozeni);
//      if ($o->cirkev) $o->cirkev= $cirkev[$o->cirkev];
//      if ($o->vzdelani) $o->vzdelani= $vzdelani[$o->vzdelani];
//      $o->zajmy_jazyk= $o->zajmy . ($o->jazyk ? ', ' : '') . $o->jazyk;
//      switch ($o->role) {
//        case 'a': $m= $o; break;
//        case 'b': $z= $o; break;
//        case 'd': $dn[]= $o; break;
//        case 'p': $pn[]= $o; break;
//      }
//    }
//    if (!$m->role || !$z->role) fce_error("neúplný pár");
//    // doplnění db údajů
//    $x_udaje= array(
//      'x_povaha'      => array(84,97),  
//      'x_manzelstvi'  => array(89,101),
//      'x_ocekavam'    => array(88,102),
//      'x_rozvody'     => array(110,112), // nepředává se do možnosti úprav
//    );
//    $flds= json_decode($zmeny, true);
//    $flds['id_pobyt_wp']= $id_pobyt_wp;
//    if ($zmeny!='{}') {
//      foreach ($flds as $fld=>$value) {
//        $mz= substr($fld,0,1);
//        $xfld= 'x'.substr($fld,1);
//        if ($xfld=='x_rozvody') continue; // nebudeme opravovat - 
//        if ($mz=='m') $m->$xfld= $value;
//        if ($mz=='z') $z->$xfld= $value;
//      }
//    }
//    else {
//      foreach ($x_udaje as $fld=>list($im,$iz)) {
//        display("$fld");
//        if (isset($x[$im])) $m->$fld= $x[$im];
//        if (isset($x[$iz])) $z->$fld= $x[$iz];
//        if ($cmd=='fld') {
//          $flds['m'.substr($fld,1)]= isset($x[$im]) ? $x[$im] : '';
//          $flds['z'.substr($fld,1)]= isset($x[$iz]) ? $x[$iz] : '';
//        }
//      }
//    }
////    debug($m,'M'); debug($z,'Z'); debug($dn,'D'); debug($pn,'P'); 
//    // redakce osobních údajů
//    $udaje= array(
//      'Jméno a příjmení'=>'jmeno', 
//      'Datum narozeni'=>'narozeni',
//      'Telefon'=>'telefon',
//      'E-mail'=>'email',
//      'Č. OP nebo cest. dokladu'=>'obcanka'
//    );
//    $html.= "
//      <style>
//        table.prihlaska { width:100%; border-collapse: collapse; }
//        table.prihlaska td { border: 1px solid grey; }
//        table.prihlaska th { text-align:center; }
//      </style>
//      ";
//    $table_attr= "class=\"prihlaska\" cellpadding=\"7\"";
//    $th= "th colspan=\"2\"";
//    $td= "td colspan=\"2\"";
//    $html.= "<table $table_attr><tr><th></th><$th>Muž</th><$th>Žena</th></tr>";
//    foreach ($udaje as $popis=>$fld) {
//      $html.= "<tr><th>$popis</th><$td>{$m->$fld}</td><$td>{$z->$fld}</td></tr>";
//    }
//    $html.= "<tr><th>Adresa, PSČ</th><td colspan=\"4\">$r_adresa</td></tr>";
//    $html.= "</table>";
//    // děti
//    $deti= ''; $nakurzu= 0;
//    if (count($dn)) {
//      $del= '';
//      foreach ($dn as $d) {
//        $deti.= "$del$d->jmeno, $d->narozeni"; $del= '; ';
//        $nakurzu+= $d->nakurzu ? 1 : 0;
//      }
//      if ($nakurzu) {
//        $html.= "<p><i>Na Manželská setkání přihlašujeme i tyto děti:</i></p>";
//        $html.= "<table $table_attr><tr><th>Jméno a příjmení</th><th>Datum narození</th><th>Poznámky (nemoci, alergie apod.)</th></tr>";
//        foreach ($dn as $d) {
//          if (!$d->nakurzu) continue;
//          $html.= "<tr><td>$d->jmeno</td><td>$d->narozeni</td><td>$d->note</td></tr>";
//        }
//        $html.= "</table>";
//      }
//    }
//    $html.= "<p></p>";
//    // redakce citlivých údajů
//    $udaje= array(
//      'Vzdělání'=>'vzdelani', 
//      'Povolání, zaměstnání'=>'zamest',
//      'Zájmy, znalost jazyků'=>'zajmy_jazyk',
//      'Popiš svoji povahu'=>'x_povaha',
//      'Vyjádři se o vašem manželství'=>'x_manzelstvi',
//      'Co od účasti očekávám'=>'x_ocekavam',
//      'Příslušnost k církvi'=>'cirkev',
//      'Aktivita v církvi'=>'aktivita',
//    );
//    $th= "th colspan=\"2\"";
//    $html.= "<table $table_attr><tr><th></th><$th>Muž</th><$th>Žena</th></tr>";
//    $td= "td colspan=\"2\"";
//    foreach ($udaje as $popis=>$fld) {
//      $html.= "<tr><th>$popis</th><$td>{$m->$fld}</td><$td>{$z->$fld}</td></tr>";
//      if ($fld=='aktivita')
//        $html.= "<tr><th>Děti (jméno + datum narození)</th><td colspan=\"4\">$deti</td></tr>";
//    }
//    $html.= "<tr><th>SPZ auta na kurzu</th><td>$r_spz</td><td>Datum svatby: $r_datsvatba</td>
//      <td colspan=\"2\">Předchozí manželství? muž:$m->x_rozvody žena:$z->x_rozvody</td></tr>";
//    $html.= "</table>";
//    $html.= "<p><i>Souhlas obou manželů s přihlášením na kurz byl potvrzen.</i></p>";
//    // přehled o počtu účastí
//    // generování PDF
//    if ($cmd=='pdf') {
//      global $ezer_root;
//      $path_files= trim($_SESSION[$ezer_root]['path_files_h']," '");
//      $path_files= rtrim($path_files,"/");
//      $fname= "online-prihlaska.pdf";
//      $foot= '';
//      $f_abs= "$path_files/pobyt/{$fname}_$idp";
//      tc_html($f_abs,$html,$foot);
//      $html= "Přihláška byla vložena do záložky Dokumenty jako soubor $fname ";
//    }
//  }
//  else {
//    $html= "<span style='background:yellow'>uložit lze jen přihlášku přenesenou do Answeru</span>";
//  }
//  if ($cmd=='pdf') display($html); else debug($flds);
//  return $cmd=='pdf' ? $html : (object)$flds;
//}
//function osoba_jmeno ($idp,$role) {
//  $jmeno= select1("CONCAT(jmeno,' ',prijmeni)",
//      'pobyt JOIN rodina ON id_rodina=i0_rodina JOIN tvori USING (id_rodina) JOIN osoba USING (id_osoba)',
//      "id_pobyt=$idp AND role='$role'");
//  return $jmeno;
//}
*/
/** =====================================================================================> DOTAZNÍKY **/
# ----------------------------------------------------------------------------------------- dot roky
# vrátí dostupné dotazníky Letního kurzu MS YS
function dot_roky () { trace();
  $y= (object)array('roky'=>'2022,2021,2019,2018,2017'); // 2017 je rozjetý - k dispozici je jen statistika
  return $y;
}
# -------------------------------------------------------------------------------------- dot prehled
# statistický přehled o akci, strukturovaný podle dotazníků Letního kurzu MS YS
#   par.zdroj= akce|dotaz
#   par.par1= rok|ida určuje význam prvního parametru
#   par.step_man a par.step_vek určuje podrobnost interval pro délku manželství a věk (default 10,5)
#   par.skladba - slehují se odpočívající VPS
#   par.org - organizátor akce
function dot_prehled ($rok_or_akce,$par,$title='',$vypis='',$export=0,$hnizdo=0) { trace();
  global $VPS;
//  debug($par);
  $y= (object)array('html'=>'');
  $org= isset($par->org) ? $par->org : 1;
  if ( $par->par1=='rok') {
    $rok= $rok_or_akce;
    list($akce,$datum_od)= select('id_duakce,datum_od','akce',
        "access & $org AND druh=1 AND zruseno=0 AND YEAR(datum_od)=$rok");
    $cond1= "a.access & $org AND a.druh=1 AND a.zruseno=0 AND YEAR(a.datum_od)=$rok";
    $cond2= "a.druh=1 AND a.zruseno=0 AND a.datum_od<'$datum_od' "; // minulé účasti i jinde
  }
  else {
//    $akce= $rok_or_akce;
    $cond= "p.id_akce=$rok_or_akce";
    $rok= -1; // je pouze v kombinaci s zdroj=akce
  }
  $no= $n_mn= $n_mo= $n_m= $n_z= 0;
  // struktura kurzu 0-VPS, 1-odpočívající VPS, 4-poprvé, 3-podruhé, 2-vícekrát
  $kurz_y= array("$VPS","odpoč.$VPS",'vicekrát','podruhé','noví');
  $kurz_x= array(0,0,0,0,0);
  // stanovení intervalu 
  $step_man= 10; $step_vek= 10;
  $vek_x= array(61,51,41,31,1,0);
  $vek_m= $vek_z= array(0,0,0,0,0,0);
  // stanovení obecného intervalu délky manželství
  $man_x= array(31,21,11,6,0,-1); // -1 je kvůli neudané, tedy nulové, délce manželství
  $man_y= array('31..','21-30','11-20','6-10','0-5','?');
  $man_s= $man_n= $man_o= array(0,0,0,0,0,0);
  // další
  $kurz= $man_vek= array();
  $step='default';
  if (isset($par->step_man)) {
    $step= $par->step_man; 
    $max= 51;
    $max= $max + ($step-($max-1)%$step) - 1;
    $man_x= $man_y= array();
    $man_s= $man_n= $man_o= array(0);
    $man_x[]= $max; 
    $man_y[]= "$max+"; 
    for ($i= $max-$step; $i>=0; $i-= $step) {
      $man_x[]= $i;
      $man_y[]= $step==1 ? $i : "$i-".($i+$step-1);
      $man_s[]= 0;
      $man_n[]= 0;
      $man_o[]= 0;
    }
    $man_x[]= -1; $man_y[]= '?'; // zarážka
  }
//  debug($man_x,"dělení pro step=$step");
//  debug($man_y,"dělení pro step=$step");
  switch ($par->zdroj) {
  case 'akce':
    $th_color= '';
    $AND_hnizdo= $hnizdo ? "AND p.hnizdo=$hnizdo" : ''; 
    $nadpis= "<h3>Skutečnost (podle údajů v Answeru)</h3>";
    $rp= pdo_qry("
      SELECT 
        -- IF(r.datsvatba,DATEDIFF(a.datum_od,r.datsvatba)/365.2425,
          -- IF(r.svatba,YEAR(a.datum_od)-svatba,0)) AS _vek_m
        IF(r.datsvatba,IF(MONTH(r.datsvatba),DATEDIFF(a.datum_od,r.datsvatba)/365.2425,YEAR(a.datum_od)-YEAR(r.datsvatba)),
          IF(r.svatba,YEAR(a.datum_od)-svatba,0)) AS _vek_m,
        ROUND(IF(MONTH(o.narozeni),DATEDIFF(a.datum_od,o.narozeni)/365.2425,YEAR(a.datum_od)-YEAR(o.narozeni)),1) AS _vek,
        sex,id_osoba,i0_rodina,IF(funkce IN (1,2),1,0),r.r_ms,t.role
      FROM pobyt AS p
      JOIN akce AS a ON id_akce=id_duakce
      JOIN spolu AS s USING (id_pobyt)
      LEFT JOIN rodina AS r ON r.id_rodina=p.i0_rodina
      JOIN tvori AS t USING (id_rodina,id_osoba)
      JOIN osoba AS o USING (id_osoba)
      WHERE /*id_akce=$akce*/ $cond1 $AND_hnizdo AND p.funkce IN (0,1,2) 
        AND t.role IN ('a','b') -- AND s_role=1 
      --  AND i0_rodina IN (3329,6052)
      GROUP BY id_osoba 
      ORDER BY t.role -- důležité pro rozbor
      ");
    while ( $rp && (list($man,$vek,$sex,$ido,$idr,$vps,$r_ms,$role)= pdo_fetch_array($rp)) ) {
      $no++;
      // minulé účasti - ale ne jako děti účastnické rodiny
      $ucasti= select(
          "COUNT(*)",
          "akce AS a
            JOIN pobyt AS p ON a.id_duakce=p.id_akce
            JOIN spolu AS s USING(id_pobyt)",
          "a.druh=1 AND a.spec=0 AND zruseno=0 
            AND s.id_osoba=$ido AND i0_rodina=$idr AND /*p.id_akce!=$akce*/ $cond2");
//                                                  display($ucasti);
      // stáří
      foreach ($vek_x as $ix=>$x) {
        if ( $vek>=$x) {
          if ($role=='a'&&$sex!=1 || $role=='b'&&$sex!=2) display("clash role/sex idr=$idr");
          if ( $sex==1 ) {
            $vek_m[$ix]++;
            $n_m++;
          }
          else {
            $vek_z[$ix]++;
            $n_z++;
          }
          break;
        }
      }
      // délka manželství
      foreach ($man_x as $ix=>$x) {
        if ($man==0) continue;
        if ( $man>=$x) {
          if ( $ucasti ) {
            $man_o[$ix]++;
            $n_mo++;
          }
          else {
            $man_n[$ix]++;
            $n_mn++;
          }
          $man_s[$ix]++;
          break;
        }
      }
      // věk manželů při vstupu do manželství - jen pro nváčky
//      if ($ucasti==0) {
      if ($vek-$man>17)
        $man_vek[$idr]= isset($man_vek[$idr]) ? ($man_vek[$idr] + $vek - $man)/2 : $vek - $man;
      else
        display("$idr: vek=$vek, man=$man");
        //if ($idr==3329) display("$idr: $man_vek[$idr]= $vek - $man");
//      }
      // skladba účastníků
      if ($par->skladba) {
        // zjistíme jestli v minulosti dělali VPS
        $vps_od= select("MIN(YEAR(datum_od))",
            'pobyt JOIN akce AS a ON id_akce=id_duakce',
            "funkce IN (1,2) AND a.druh=1 AND a.spec=0 AND a.zruseno=0 AND i0_rodina=$idr
            GROUP BY i0_rodina");
        $odpociva= $vps_od && $vps_od<$rok ? 1 : 0;
//        if ( $odpociva) display("$idr: $vps_od");
//        // struktura kurzu 1-VPS, 0-nevps, 2-poprvé, 3-podruhé, 4-vícekrát
//        $kurz[$vps ? 0 : ($odpociva ? 1 : ($ucasti==0 ? 2 : ($ucasti==1 ? 3 : 4)))]++;
        // struktura kurzu 1-VPS, 0-nevps, 4-poprvé, 3-podruhé, 2-vícekrát
        $kurz[$vps ? 0 : ($odpociva ? 1 : ($ucasti==0 ? 4 : ($ucasti==1 ? 3 : 2)))]++;
      }
    }
    break;
  case 'dotaz':
    $th_color= " style='background:#fb6'";
    $nadpis= "<h3>Podle odevzdaných dotazníků</h3>";
    $rp= pdo_qry("
      SELECT manzel,vek,sex,IF(novic,0,1)
      FROM dotaz
      WHERE dotaznik=$rok AND duplicita='' 
      ");
    while ( $rp && (list($man,$vek,$sex,$ucasti)= pdo_fetch_array($rp)) ) {
      $no++;
      // stáří
      foreach ($vek_x as $ix=>$x) {
        if ( $vek>=$x) {
          if ( $sex==0 ) {
            $vek_m[$ix]++;
            $n_m++;
          }
          elseif ( $sex==1 ) {
            $vek_z[$ix]++;
            $n_z++;
          }
          break;
        }
      }
      // délka manželství
      foreach ($man_x as $ix=>$x) {
        if ( $man>=$x) {
          if ( $ucasti ) {
            $man_o[$ix]++;
            $n_mo++;
          }
          else {
            $man_n[$ix]++;
            $n_mn++;
          }
          $man_s[$ix]++;
          break;
        }
      }
    }
    if ( !$no ) {
      $tab= "<h3>Dotazník pro rok $rok není dostupný</h3>";
    }
    break;
  }
//                                              debug($man_vek,"vstup do manželství");
//                                              debug($kurz,"struktura kurzu");
//                                              debug($man_s,"manželství");
//                                              debug($man_o,"manželství O");
//                                              debug($man_n,"manželství N");
//                                              debug($vek_m,"věk muže $n_m");
//                                              debug($vek_z,"věk ženy $n_z");
  // tabulka trvání manželství
  if ($no) {
    $subtab= isset($par->know) ? "(zeleně je % odevzdaných v kategorii)" : ' ';
    $tab= "<h3>Přehled délky manželství $subtab</h3>";
    $td= "td align='right'";
    $th= "th align='right'$th_color";
    $th2= "th align='right'";
    $span= 2;
    if (isset($par->know)) {
      $th_n= "<$th2 title='procento odevzdaných dotazníků'>%</th>";
      $th_o= "<$th2 title='procento odevzdaných dotazníků'>%</th>";
      $th_c= "<$th></th>";
      $span= 3;
    }
    $tab.= "<table class='stat'>";
    $tab.= "<tr>
        <$th></th>
        <$th colspan=2>celkový počet</th>
        <$th colspan=$span>noví účastníci</th>
        <$th colspan=$span>opakující ...</th>
      </tr></tr>
        <$th>délka manželství</th>
        <$th>počet</th>
        <$th>%</th>
        <$th>počet</hd>
        <$th>%</th>$th_n
        <$th>počet</th>
        <$th>%</th>$th_o
      </tr>";
    // kategorie
    for ($i= count($man_s)-2; $i>=0; $i--) {
      $s= $man_s[$i]; $n= $man_n[$i]; $o= $man_o[$i]; 
      $ps= $no ? number_format(100*$s/$no,0) : '-';
      $pn= $n_mn ? number_format(100*$n/$n_mn,0) : '-';
      $po= $n_mo ? number_format(100*$o/$n_mo,0) : '-';
      $x= $i==count($man_s)-1 ? "?" : "{$vek_x[$i]}-".($i==0 ? '...' : $vek_x[$i-1]-1).' let';
      $x1= $man_x[$i]; $x2= $i==0 ? '...' : $man_x[$i-1]-1;
      $td_n= $td_o= '';
      if (isset($par->know)) {
        $x_n= $par->know->man_n->$i;
        $x_n= $x_n ? number_format(100*$n/$x_n) : '-';
        $x_o= $par->know->man_o->$i;
        $x_o= $x_o ? number_format(100*$o/$x_o) : '-';
        $td_n= "<$th2>$x_n%</th>";
        $td_o= "<$th2>$x_o%</th>";
      }
      $tab.= "<tr>
          <$th>$x1-$x2 let</th>
          <$td>$s</td>
          <$td>$ps %</td>
          <$td>$n</td>
          <$td>$pn %</td>$td_n
          <$td>$o</td>
          <$td>$po %</td>$td_o
        </tr>";
    }
    $td_n= $td_o= '';
    if (isset($par->know)) {
      $x_n= $par->know->man_n_c;
      $x_n= $x_n ? number_format(100*$n_mn/$x_n) : '-';
      $x_o= $par->know->man_s_c;
      $x_o= $x_o ? number_format(100*$n_mo/$x_o) : '-';
      $td_n= "<$th2>$x_n%</th>";
      $td_o= "<$th2>$x_o%</th>";
    }
    $tab.= "<tr>
        <$th>celkem</th>
        <$th>$no</th>
        <$th></th>
        <$th>$n_mn</th>
        <$th></th>$td_n
        <$th>$n_mo</th>
        <$th></th>$td_o
      </tr>";
    $tab.= "</table>";
  }
  // tabulka stáří účastníků
  if ($no) {
    $tab.= "<h3>Přehled stáří účastníků</h3>";
    $td= "td align='right'";
    $th= "th align='right'$th_color";
    $th2= "th align='right'";
    $th_m= $th_z= $th_c= '';
    $span= 2;
    if (isset($par->know)) {
      $th_m= "<$th2 title='procento odevzdaných dotazníků'>%</th>";
      $th_z= "<$th2 title='procento odevzdaných dotazníků'>%</th>";
      $th_c= "<$th></th>";
      $span= 3;
    }
    $tab.= "<table class='stat'>";
    $tab.= "<tr>
        <th$th_color></th>
        <th colspan=$span$th_color>muži</th>
        <th colspan=$span$th_color>ženy</th>
      </tr></tr>
        <th$th_color>věkové kategorie</th>
        <$th>počet</th>
        <$th>%</th>$th_m
        <$th>počet</hd>
        <$th>%</th>$th_z
      </tr>";
    // kategorie
    for ($i= count($vek_x)-1; $i>=0; $i--) {
      $m= $vek_m[$i]; $z= $vek_z[$i]; 
      $pm= number_format($n_m ? 100*$m/$n_m : 0,0);
      $pz= number_format($n_z ? 100*$z/$n_z : 0,0);
      $x= $i==count($vek_x)-1 ? "?" : "{$vek_x[$i]}-".($i==0 ? '...' : $vek_x[$i-1]-1).' let';
      $td_m= $td_z= '';
      if (isset($par->know)) {
        $x_m= $par->know->muz->$i;
        $x_m= $x_m ? number_format(100*$m/$x_m) : '-';
        $x_z= $par->know->zena->$i;
        $x_z= $x_z ? number_format(100*$z/$x_z) : '-';
        $td_m= "<$th2>$x_m%</th>";
        $td_z= "<$th2>$x_z%</th>";
      }
      $tab.= "<tr>
          <th$th_color>$x</th>
          <$td>$m</td>
          <$td>$pm %</td>$td_m
          <$td>$z</td>
          <$td>$pz %</td>$td_z
        </tr>";
    }
    $td_m= $td_z= '';
    if (isset($par->know)) {
      $x_m= $par->know->muz_c;
      $x_m= $x_m ? number_format(100*$n_m/$x_m) : '-';
      $x_z= $par->know->zena_c;
      $x_z= $x_z ? number_format(100*$n_z/$x_z) : '-';
      $td_m= "<$th2>$x_m%</th>";
      $td_z= "<$th2>$x_z%</th>";
    }
    $tab.= "<tr>
        <th$th_color>celkem</th>
        <$th>$n_m</th>
        <$th></th>$td_m
        <$th>$n_z</th>
        <$th></th>$td_z
      </tr>";
    $tab.= "</table>";
  }
  $y->html.= "$nadpis$tab"; 
  $y->know= (object)array(
      'muz'=>$vek_m,'zena'=>$vek_z,
      'muz_c'=>$n_m,'zena_c'=>$n_z,
      'man_y'=>$man_y,'man_o'=>$man_o,
      'man_n'=>$man_n,'man_s'=>$man_s,
      'man_n_c'=>$n_mn,'man_s_c'=>$n_mo,
      'kurz_y'=>$kurz_y,'kurz_x'=>$kurz,
      'man_vek'=>$man_vek
      );
  return $y;
}
# ------------------------------------------------------------------------------------------ dot spy
# tipy na autory
# kurs = {akce:id_akce,data:[{sex,vek,deti,manz,novic}...] ... data se počítají při prvním průchodu
function dot_spy ($rok,$id) {  //($kurz,$dotaznik,$clmn,$pg,$back) { 
  global $ezer_root;
//  global $i_osoba_jmeno, $i_osoba_vek, $i_osoba_role, $i_osoba_prijmeni, $i_key_spolu;
//  if (!is_object($kurz)) { fce_error("kurz není objekt"); return(null); }
//  debug($kurz,"dot_spy(...,$dotaznik,$clmn,$pg,$back)");
  // nová metoda
  $html= '';
//  if ($clmn=='id') {
    $tips= 
      ( !isset($_SESSION[$ezer_root]['dot_tips']) && is_array($_SESSION[$ezer_root]['dot_tips'])
        || $_SESSION[$ezer_root]['dot_rok']!=$rok)
      ? dot_spy_data($rok)
      : $_SESSION[$ezer_root]['dot_tips'];
    $osoby= $_SESSION[$ezer_root]['dot_osoby'];
    if ($tips) foreach ($tips as $tip) {
      if ($tip->idd==$id) {
        $del= '';
        foreach ($tip->tips as $id=>$w) {
          $o= $osoby[$id];
          // tipneme dotazník partnera
          $ip= isset($osoby[$o->ido_partner]->dotaz) ? $osoby[$o->ido_partner]->dotaz : '';
          // tipneme dotazníky skupinky
          $skup= $o->skup;
          $is= array();
          foreach ($tips as $tip_skup) {
            foreach ($tip_skup->tips as $s_id=>$s_w) {
              if ($s_w<0 && $osoby[$s_id]->skup==$skup) {
                $isx= $osoby[$s_id]->dotaz;
                $is[]= "<a href='ezer://akce2.sta.show_obraz/$isx'>$isx</a>";
              }
            }
          }
          $is= count($is) ? "<br>? skup $skup: ".implode(',',$is) : '';            
          $ip= $ip ? " (<a href='ezer://akce2.sta.show_obraz/$ip'>$ip</a>)" : '';
          $tit= (-$w-1).": věk=$o->vek, děti/LK=$o->deti, manželství=$o->manz, "
              . ($o->novic ? 'poprvé' : 'opakovaně') 
              . ($o->nest ? ", hnízdo=$o->nest" : '');
          $html.= "$del<a href='ezer://akce2.ucast.ucast_pobyt/{$o->idp}' "
              . "title='$tit'>{$o->jmeno}</a> $ip $is";
          $del= "<br>";
        }
      }
    }
    $html.= "<hr>";
//  }
//  goto end;
  // stará metoda
  /*
  $max_n= 0; $n= 0;
//  unset($kurz->data); // vždy přepočítat --------------------------------------------- LADĚNÍ
  if ( !isset($kurz->data) || $kurz->rok!=$dotaznik ) {
    $akce= select('id_duakce','akce',"access=1 AND druh=1 AND YEAR(datum_od)=$kurz->rok");
    $kurz->akce= $akce;
    $zacatek_akce= select('datum_od','akce',"id_duakce=$akce");
    $cnd= "p.funkce IN (0,1,2)";
    $browse_par= (object)array(
      'cmd'=>'browse_load','cond'=>"$cnd AND p.id_akce=$akce",
      'having'=>'','order'=>'a _nazev',
      'sql'=>"SET @akce:=$akce,@soubeh:=0,@app:='{$EZER->options->root}';");
    $z= ucast2_browse_ask($browse_par,true);
    $kurz->data= array();
    foreach($z->values as $par) { if ( $par ) {
      $idp= $par->key_pobyt;
      $nest= $par->hnizdo;
      $n++;
      if ( $max_n && $n>$max_n ) break;
      $novic= $par->x_ms==1 ? 1 : 0;
      $manzele= '?';
      if ( $par->r_datsvatba ) {
        $datsvatba= sql_date1($par->r_datsvatba,1);
        $manzele=  roku_k($datsvatba,$zacatek_akce);
      }
      elseif ( $par->r_svatba ) {
        $manzele= $kurz->rok-$par->r_svatba;
      }
      $nazev= $par->_nazev;
      $cle= explode('≈',$par->r_cleni);
      $m_vek= $z_vek= '?';
      $m_ido= $z_ido= 0;
      $deti= 0;
      foreach($cle as $cl) {
        $c= explode('~',$cl);
        $role= $c[$i_osoba_role];
        switch ($role) {
        case 'a': $m_vek= $c[$i_osoba_vek]; 
                  $m_jmeno= $c[$i_osoba_jmeno]; $m_prijmeni= $c[$i_osoba_prijmeni]; 
                  $m_ido= $c[0]; break;
        case 'b': $z_vek= $c[$i_osoba_vek]; 
                  $z_jmeno= $c[$i_osoba_jmeno]; $z_prijmeni= $c[$i_osoba_prijmeni]; 
                  $z_ido= $c[0]; break;
        case 'd': if ( $c[$i_key_spolu] ) $deti++; break;
        }
      }
      $m= (object)array('sex'=>0,'vek'=>$m_vek,'manz'=>$manzele,'deti'=>$deti,'novic'=>$novic,
          'prijmeni'=>$m_prijmeni,'jmeno'=>$m_jmeno,'idp'=>$idp,'ido'=>$m_ido,'nest'=>$nest);
      $z= (object)array('sex'=>1,'vek'=>$z_vek,'manz'=>$manzele,'deti'=>$deti,'novic'=>$novic,
          'prijmeni'=>$z_prijmeni,'jmeno'=>$z_jmeno,'idp'=>$idp,'ido'=>$z_ido,'nest'=>$nest);
      $kurz->data[]= $m;
      $kurz->data[]= $z;
//      break; 
    }}
  }
  list($sex,$vek,$deti,$manz,$novic,$hnizdo)= 
      select('sex,vek,deti,manzel,novic,IF(hnizdo,hnizdo,0)','dotaz',
        "dotaznik=$dotaznik AND $clmn=$pg");
  // hledáme shody
  $shod= 0; 
  $shod_max= 7;
  $kdo= array();
  $tit= array();
  $pob= array();
  $dpa= array();
  $f= $kurz->filtr;
  $n= 0;
  foreach($kurz->data as $i=>$o) {
    if ( $hnizdo==$o->nest
        && ( $f->sex ? $sex==$o->sex : 1)
        && ( $f->vek ? abs($vek-$o->vek)<=1  : 1)
        && ( $f->det ? $deti==$o->deti  : 1)
        && ( $f->man ? abs($manz-$o->manz)<=1 : 1)
        && ( $f->nov ? $novic==$o->novic : 1)
      ) {
      if ( $shod<=$shod_max ) {
        $kdo[$shod]= "$o->jmeno $o->prijmeni";
        $pob[$shod]= $o->idp;
        $tit[$shod]= "věk=$o->vek, děti/LK=$o->deti, manželství=$o->manz, "
            . ($o->novic ? 'poprvé' : 'opakovaně');
        if ($hnizdo)
          $tit[$shod].= ", hnízdo=$o->nest";
        // zkusíme najít dotazník partnera
        $dpa[$shod]= array();
        $ip= $o->sex ? $i-1 : $i+1;
        $p= is_array($kurz->data) ? $kurz->data[$ip] : $kurz->data->$ip;
        $rp= pdo_qry("SELECT $clmn FROM dotaz WHERE dotaznik={$kurz->rok} AND duplicita='' 
            AND hnizdo=$p->nest
            AND sex=$p->sex 
            AND ABS(vek-$p->vek)<=1 
            AND deti=$p->deti 
            AND ABS(manzel-$p->manz)<=1
            AND novic=$p->novic
        ");
        while ( $rp && (list($ppage)= pdo_fetch_array($rp)) ) {
          $dpa[$shod][]= $ppage;
        }
      }
      $shod++;
    }
  }
//  $kurz->html= '';
//                                                      debug($kdo);
  $del= '';
  for($j=0; $j<count($kdo); $j++) {
    $dp= array();
    foreach($dpa[$j] as $ip) {
      $dp[]= "<a href='ezer://akce2.sta.show_obraz/$ip'>$ip</a>";
    }
    $partner= implode(',',$dp);
    $kurz->html.= "$del<a href='ezer://akce2.ucast.ucast_pobyt/{$pob[$j]}' "
      . "title='{$tit[$j]}'>{$kdo[$j]}</a> ($partner)";
    $del= '<br>';
  }
  $shod= kolik_1_2_5($shod,"shoda,shody,shod");
  $goback= $back ? "<a style='background:orange' href='ezer://akce2.sta.show_obraz/$back' "
      . "title='$back'>&lArr;</a>" : '';
  $kurz->html.= ($shod>$shod_max ? ' ...' : '')."<br><i>celkem je $shod</i>  $goback";
//  unset($kurz->data);
   */
end:  
//                    debug($kurz);
  return $html;
}
# ------------------------------------------------------------------------------------- dot spy_data
# pomocná fce - tipy na autory
function dot_spy_data ($rok) { 
  global $EZER, $ezer_root;
  global $i_osoba_jmeno, $i_osoba_vek, $i_osoba_role, $i_osoba_prijmeni, $i_key_spolu;
  // agragace dat dotazníků
  $osoba= array();
  $max_n= 0; $n= 0;
  list($akce,$zacatek_akce)= select('id_duakce,datum_od','akce',
      "access=1 AND druh=1 AND YEAR(datum_od)=$rok");
  $cnd= "p.funkce IN (0,1,2)";
  $browse_par= (object)array(
    'cmd'=>'browse_load','cond'=>"$cnd AND p.id_akce=$akce",
    'having'=>'','order'=>'a _nazev',
    'sql'=>"SET @akce:=$akce,@soubeh:=0,@app:='{$EZER->options->root}';");
  $z= ucast2_browse_ask($browse_par,true);
  foreach($z->values as $par) { if ( $par ) { 
    $idp= $par->key_pobyt;
    $nest= $par->hnizdo;
    $skup= $par->skupina;
    $n++;
    if ( $max_n && $n>$max_n ) break;
    $novic= $par->x_ms==1 ? 1 : 0;
    $manzele= '?';
    if ( $par->r_datsvatba ) {
      $datsvatba= sql_date1($par->r_datsvatba,1);
      $manzele=  roku_k($datsvatba,$zacatek_akce);
    }
    elseif ( $par->r_svatba ) {
      $manzele= $rok - $par->r_svatba;
    }
    $nazev= $par->_nazev;
    $cle= explode('≈',$par->r_cleni);
    $m_vek= $z_vek= '?';
    $m_ido= $z_ido= 0;
    $deti= 0;
    foreach($cle as $cl) {
      $c= explode('~',$cl);
      $role= $c[$i_osoba_role];
      switch ($role) {
      case 'a': $m_vek= $c[$i_osoba_vek]; 
                $m_jmeno= $c[$i_osoba_jmeno].' '.$c[$i_osoba_prijmeni]; 
                $m_ido= $c[0]; break;
      case 'b': $z_vek= $c[$i_osoba_vek]; 
                $z_jmeno= $c[$i_osoba_jmeno].' '.$c[$i_osoba_prijmeni]; 
                $z_ido= $c[0]; break;
      case 'd': if ( $c[$i_key_spolu] ) $deti++; break;
      }
    }
    $m= (object)array('sex'=>0,'vek'=>$m_vek,'manz'=>$manzele,'deti'=>$deti,'novic'=>$novic,
        'jmeno'=>$m_jmeno,'idp'=>$idp,'ido_partner'=>$z_ido,'ido'=>$m_ido,
        'skup'=>$skup,'nest'=>$nest);
    $z= (object)array('sex'=>1,'vek'=>$z_vek,'manz'=>$manzele,'deti'=>$deti,'novic'=>$novic,
        'jmeno'=>$z_jmeno,'idp'=>$idp,'ido_partner'=>$m_ido,'ido'=>$z_ido,
        'skup'=>$skup,'nest'=>$nest);
    $osoba[$m_ido]= $m;
    $osoba[$z_ido]= $z;
//      break; 
  }}
//  debug($osoba,'fakta z databáze');
  $dotaz= array(); // [{id,tips:[[ido,diff],...]}, ...]
  $max_diff= 2; // maximální odchylka
  $max_n= 0; $n= 0; // omezení testování
  $rd= pdo_qry("SELECT id,sex,vek,deti,manzel,novic,IF(hnizdo,hnizdo,0) 
      FROM dotaz WHERE dotaznik=$rok AND duplicita='' ORDER BY id");
  while ($rd && list($id,$sex,$vek,$deti,$manz,$novic,$hnizdo)= pdo_fetch_row($rd)) {
    $n++;
    if ( $max_n && $n>$max_n ) break;
    $tips= array();
    foreach($osoba as $i=>$o) {
      $diff= 999;
      if ( $hnizdo==$o->nest && $sex==$o->sex && $novic==$o->novic ) {
        $diff= abs($vek-$o->vek) + abs($deti-$o->deti) + ($manz ? abs($manz-$o->manz) : 0);
      }  
      if ($diff <= $max_diff) {
        $tips[$o->ido]= $diff;
      }
    }
    if (count($tips)) {
      asort($tips);
      $dotaz[]= (object)array('idd'=>$id,'tips'=>$tips);
    }
  }
  // výběr nekonfliktních jako jistých
  $filtr= function($width,$goal) use (&$dotaz) {
    $n= 0;
    foreach ($dotaz as $d1) {
      foreach ($d1->tips as $ido1=>$tip1) {
        if ($tip1==$width) {
          $only= 1;
          foreach ($dotaz as $d2) {
            if ($d2->idd!=$d1->idd) {
              foreach ($d2->tips as $ido2=>$tip2) {
                if ($tip2>0 && $tip2<=$width && $ido2==$ido1) {
                  $only= 0;
                  break 2;
                }
              }
            }
          }
          if ($only) {
            $d1->tips= array($ido1=>$goal);
            $n++;
          }
          else {
            $d1->tips= array($ido1=>999);
          }
        }
      }
    }
    return $n;
  };
  $n0= $filtr(0,-1);
  $n1= $filtr(1,-2);
  debug($dotaz,"tipy 0:$n0, 1:$n1");
  // zapíšeme dotazník k osobě
  foreach ($dotaz as $d) {
    foreach ($d->tips as $ido=>$w) {
      if ($w<0) {
        $osoba[$ido]->dotaz= $d->idd;
      }
    }
  }
  // uschováme do session
  $_SESSION[$ezer_root]['dot_rok']= $rok;
  $_SESSION[$ezer_root]['dot_tips']= $dotaz; 
  $_SESSION[$ezer_root]['dot_osoby']= $osoba; 
}
# ----------------------------------------------------------------------------------------- dot show
# zobrazení digitalizovaných dotazníků
# $dirty=1 způsobí kontrolu existence pro offset=0 a případně skok na další
function dot_show ($dotaznik,$clmn,$pg,$offset,$cond,$dirty,$rok) { trace();
  $y= (object)array('html'=>'není zvolen žádný dotazník ','err'=>'','war'=>'','jpg'=>'','none'=>1);
  $tab_class= 'stat dot';
  // posun v dotazech
  $cond1= $dotaznik ? "dotaznik=$dotaznik AND duplicita='' AND " : '';
  $rok_pg= "dotaznik,$clmn";
  switch ($offset) {
  case -2: // začátek
    list($rok,$pg)= select($rok_pg,'dotaz',"$cond1 $cond ORDER BY $clmn ASC LIMIT 1");
    $y->none= 0;
    break;
  case -1: // předchozí
    list($rok,$pg)= select($rok_pg,'dotaz',"$cond1 $cond AND $clmn<$pg ORDER BY $clmn DESC LIMIT 1");
    if ( !$pg ) goto end;
    $y->none= 0;
    break;
  case 0:  // tento
    if ( $dirty ) {
      list($rok,$pg)= select($rok_pg,'dotaz',"$cond1 $cond AND $clmn=$pg");
      $y->none= 0;
    }
    break;
  case 1:  // další
    list($rok,$pg)= select($rok_pg,'dotaz',"$cond1 $cond AND $clmn>$pg ORDER BY $clmn ASC LIMIT 1");
    if ( !$pg ) goto end;
    $y->none= 0;
    break;
  case 2:  // poslední
    list($rok,$pg)= select($rok_pg,'dotaz',"$cond1 $cond ORDER BY $clmn DESC LIMIT 1");
    $y->none= 0;
    break;
  }
  if ( !$pg ) goto end;
  $x= select_object('*','dotaz',"dotaznik=$rok AND $clmn=$pg");
  $y->nazory= (object)array();
  $y->nazory->nazor_kurz= $x->nazor_kurz; 
  $y->nazory->nazor_online= $x->nazor_online; 
  $y->nazory->nazor_cas= $x->nazor_cas; 
  $y->nazory->nazor_ok= $x->nazor_ok;
  $y->nazory->nazor_zapsal= $x->nazor_zapsal;
  $y->page= $x->page;
  // získání obrazu
  $jpg= str_pad($x->page,4,'0',STR_PAD_LEFT).'.jpg';
  $img_path= "docs/import/MS$rok/$jpg";
  $y->id= $x->id;
  $y->rok= $rok;
  if ( file_exists($img_path) ) {
    $y->jpg= "<a href='$img_path' target='img'><img src='$img_path' width='100%'></a>";
  }
  else {
    $y->jpg= " sken dotazníku není k dispozici ";
  }
  $tmpl= array(
    'Statistika' => array(
        'pohlaví'=>'sex', 'věk'=>'vek','dětí'=>'deti','manželství'=>'manzel','poprvé'=>'novic'
    ),
    'dozvěděl se od'=>array(
      'přátel' => 'od_pratele', 'partnera' => 'od_partner', 'příbuzných' => 'od_pribuzni', 
      '(pečoun)' => 'od_pecoun', 'z inzerce' => 'od_inzerce', 'chlapi' => 'od_chlapi', 
      'YMCA' => 'od_ymca', 'jiné' => 'od_jine', ':' => 'od_jine_text'
    ),
    'proč jel'=>array(
      'zlepšit manželství' => 'proc_zlepsit', 'byla krize' => 'proc_krize', 'opakovaně' => 'proc_opak', 
      'jiné' => 'proc_jine', ':' => 'proc_jine_text'
    ),
    'Hodnocení' => array(
      'přednášky'=>'prednasky', 'skupinky'=>'skupinky','duchovno'=>'duchovno',
      'ubytovani'=>'ubytovani', 'strava'=>'strava', 'péče o děti'=>'pecedeti', 
      'motto'=>'motto', 'maturita'=>'maturita', 'hudba'=>'hudba'
    ),
    'Slovně' => array(
      'Líbilo se mi' => 'libilo',
      'Vadilo mi' => 'vadilo',
      'Vzkaz týmu' => 'vzkaz'
    ),
    'Témata'=>array(
      'výchova menších' => 'tema_male', 'výchova dospívajících' => 'tema_dosp', 
      'vztah matka-dítě' => 'tema_matka', 'vztah otec-dítě' => 'tema_otec', 
      'mezigenerační' => 'tema_mezigen', 'duchovní život' => 'tema_duchovni', 
      'jiné' => 'tema_jine', ':' => 'tema_jine_text'
    ),
    'Přínos'=>array(
      'významný' => 'prinos_1', 'částečně' => 'prinos_2', 'uvidí se' => 'prinos_3',
      'beze změny' => 'prinos_4', 'spíš horší' => 'prinos_5'
    )
  );
  foreach ($x as $name=>$val) {
    switch ($name) {
      case 'sex':   $x->sex= array('muž','žena')[$val]; break;
      case 'vek':   $x->vek.= ' let'; break;
      case 'deti':  $x->deti.= ' dětí/LK'; break;
      case 'manzel':$x->manzel.= ' let manž.'; break;
      case 'novic': $x->novic= array('opak.','poprv')[$val]; break;
    }
  }
  // doplnění hnízd
  $hnizdo= '';
  $hnizda= select('hnizda','akce',"YEAR(datum_od)=$rok AND druh=1 AND access=1");
  if ($hnizda) {
    $hnizda= explode(',',$hnizda);
    $hnizdo= $x->hnizdo ? " &nbsp;  hnízdo={$hnizda[$x->hnizdo-1]}" : '';
  }
  // zobrazení duplicitních id
  if ($x->duplicita) {
    $duplicity= "<span style='color:red'> &nbsp; duplicita=$x->duplicita</span>";
  }
  $warning= $x->warning ? "<span style='color:red'>$x->warning</span>" : ''; 
  $tab.= "<p><b>PDF={$x->page}  &nbsp;  XLS={$x->id} &nbsp; rok=$rok $hnizdo$duplicity$warning</b></p>";
  foreach ($tmpl as $row => $clmns) {
    switch ($row) {
    case 'Statistika':
      $tab.= "<table class='$tab_class'><tr><th>$row</th>";
      foreach ($clmns as $name=>$val) {
        $tab.= "<td>{$x->$val}</td>";
      }
      $tab.= "</tr></table>";
      break;
    case 'proč jel':
    case 'dozvěděl se od':
      $tab.= "<table class='$tab_class'><tr><td class='first'>$row</td><td>";
      $plus= '';
      foreach ($clmns as $name=>$val) {
        if ( $x->$val && $name!='jiné' ) {
//          $v= $name==':' ? "jiné = {$x->$val}" : $name;
//          $tab.= "$plus $v";
//          $plus= ' +';
          if ( $name==':' ) {
            $td= $plus ? "</td><td>" : '';
            $tab.= "$td$plus jiné = {$x->$val}";
          }
          else {
            $tab.= "$plus $name";
            $plus= ' +';
          }
        }
      }
      $tab.= "</td></tr></table>";
      break;
    case 'Hodnocení':
      $tab.= "<br>";
      $r1= "<th>$row</th>";
      $r2= "<td></td>";
      foreach ($clmns as $name=>$val) {
        $r1.= "<td class='vert'><p>$name</p></td>";
        $r2.= "<td>{$x->$val}</td>";
      }
      $tab.= "<table class='$tab_class'><tr>$r1</tr><tr>$r2</tr></table>";
      break;
    case 'Slovně':
      $tab.= "<br><table class='$tab_class'>";
      foreach ($clmns as $name=>$val) {
        $tab.= "<tr><th style='height:40px'>$name</th><td>{$x->$val}</td></tr>";
      }
      $tab.= "</table><br>";
      break;
    case 'Témata':
      $tab.= "<table class='$tab_class'><tr><th>$row</th>";
      foreach ($clmns as $name=>$val) {
        if ( $x->$val ) {
          $v= $name==':' ? $x->$val : $name;
          $tab.= "<td>$v</td>";
        }
      }
      $tab.= "</tr></table><br>";
      break;
    case 'Přínos':
      $r1= "<th>$row</th><td class='vert'><p>číslem</p></td>";
      $r2= "<td></td><td>{$x->prinos}</td>";
      foreach ($clmns as $name=>$val) {
        $pr= substr($val,-1,1);
        $r1.= "<td class='vert' title='prinos=$pr'><p>$name</p></td>";
        $r2.= $x->prinos==$pr ? "<td>1</td>" : "<td>-</td>";
      }
      $r3= "<td style='height:40px'>slovně:</td><td colspan='6'>{$x->prinos_text}</td>";
      $tab.= "<table class='$tab_class'><tr>$r1</tr><tr>$r2</tr><tr>$r3</tr></table>";
      break;
    }
  }
  $style= "<style>
table.dot {
  width: 320px;
  margin-top: -1px;
}
table.dot th {
  width: 80px;
}
table.dot td.first {
  width: 76px;
}
table.dot .vert {
  vertical-align: bottom;
  height: 65px;
}
table.dot .vert p {
  transform: rotate(-90deg);
  position: relative;
  width: 18px;
  white-space: nowrap;
}
</style>";
  $y->html= $style.$tab;
end:
//                    debug($y);
  return $y;
}
# --------------------------------------------------------------------------------------- dot nazory
function dot_nazory($rok,$id,$nazory) {
  global $ezer_root;
  $zmena_kdo= $_SESSION[$ezer_root]['user_abbr'];
  $zmena_kdy= date('Y-m-d H:i:s');
  $set= "nazor_zapsal='$zmena_kdo $zmena_kdy'";
  foreach($nazory as $fld=>$value) {
    $set.= ", $fld=".($value ? $value : 0);
  }
  query("UPDATE dotaz SET $set WHERE dotaznik=$rok AND id=$id");
}
# ---------------------------------------------------------------------------------------- dot vyber
# průměrné hodnoty dotazníků
# par = {cond:sql }
function dot_vyber ($par) { trace();
  $y= (object)array('html'=>'','err'=>'','war'=>'','jpg'=>'','celkem'=>0);
  $cond= isset($par->cond) ? $par->cond : 1;
  $cond_roky= preg_match("/(dotaznik IN \([\d,]+\))/",$cond,$m);
  $celkem_roky= select('COUNT(*)','dotaz',"{$m[0]} AND duplicita='' ");
  $tab_class= 'stat dot';
//  $vyber= $rok ? "dotaznik=$rok " : '1';
//  $GROUP= $rok ? "GROUP BY dotaznik" : '';
  $x= select_object('
    COUNT(*) AS celkem,
    ROUND(AVG(100*sex))     AS sex,
    ROUND(AVG(vek),1)       AS vek,
    ROUND(AVG(deti),1)      AS deti,
    ROUND(AVG(manzel),1)    AS manzel,
    ROUND(AVG(100*novic))   AS novic,
    ROUND(AVG(prednasky),1)   AS prednasky,
    ROUND(AVG(skupinky),1)    AS skupinky,
    ROUND(AVG(duchovno),1)    AS duchovno,
    ROUND(AVG(IF(ubytovani=0,NULL,ubytovani)),1)   AS ubytovani,
    ROUND(AVG(IF(strava=0,NULL,strava)),1)      AS strava,
    ROUND(AVG(IF(pecedeti=0,NULL,pecedeti)),1)    AS pecedeti,
    ROUND(AVG(motto),1)       AS motto,
    ROUND(AVG(maturita),1)    AS maturita,
    ROUND(AVG(hudba),1)       AS hudba,
    ROUND(AVG(100*tema_male))     AS tema_male,
    ROUND(AVG(100*tema_dosp))     AS tema_dosp,
    ROUND(AVG(100*tema_matka))    AS tema_matka,
    ROUND(AVG(100*tema_otec))     AS tema_otec,
    ROUND(AVG(100*tema_mezigen))  AS tema_mezigen,
    ROUND(AVG(100*tema_duchovni)) AS tema_duchovni,
    ROUND(AVG(100*tema_jine))     AS tema_jine,
    ROUND(100*AVG(IF(prinos=1,1,0))) AS prinos_1,
    ROUND(100*AVG(IF(prinos=2,1,0))) AS prinos_2,
    ROUND(100*AVG(IF(prinos=3,1,0))) AS prinos_3,
    ROUND(100*AVG(IF(prinos=4,1,0))) AS prinos_4,
    ROUND(100*AVG(IF(prinos=5,1,0))) AS prinos_5,
    ROUND(AVG(prinos),1)             AS prinos,
    SUM(IF(hnizdo=1,1,0)) AS hnizdo_1p,
    SUM(IF(hnizdo=2,1,0)) AS hnizdo_2p,
    SUM(IF(hnizdo=3,1,0)) AS hnizdo_3p,
    ROUND(AVG(100*IF(nazor_kurz>0,1,0)))  AS nazor_kurz_p,
    ROUND(AVG(100*IF(nazor_kurz<0,1,0)))  AS nazor_kurz_m,
    ROUND(AVG(100*IF(nazor_online>0,1,0)))  AS nazor_online_p,
    ROUND(AVG(100*IF(nazor_online<0,1,0)))  AS nazor_online_m,
    ROUND(AVG(100*IF(nazor_cas>0,1,0)))  AS nazor_cas_p,
    ROUND(AVG(100*IF(nazor_cas<0,1,0)))  AS nazor_cas_m,
    ROUND(AVG(100*nazor_ok))  AS nazor_ok
    ','dotaz',"$cond ");
  $tmpl= array(
    'Statistika' => array(
        'pohlaví'=>'sex', 'věk'=>'vek','dětí'=>'deti','manželství'=>'manzel','poprvé'=>'novic'
    ),
    'Hodnocení' => array(
      'přednášky'=>'prednasky', 'skupinky'=>'skupinky','duchovno'=>'duchovno',
      'ubytování'=>'ubytovani', 'strava'=>'strava', 'péče o děti'=>'pecedeti', 
      'motto'=>'motto', 'maturita'=>'maturita', 'hudba'=>'hudba'
    ),
    'Témata'=>array(
      'malé děti' => 'tema_male', 'dospívající' => 'tema_dosp', 
      'matka-dítě' => 'tema_matka', 'otec-dítě' => 'tema_otec', 
      'mezigen.' => 'tema_mezigen', 'duchovní' => 'tema_duchovni', 
      'jiné' => 'tema_jine'
    ),
    'Přínos'=>array(
      'významný' => 'prinos_1', 'částečně' => 'prinos_2', 'uvidí se' => 'prinos_3',
      'beze změny' => 'prinos_4', 'spíš horší' => 'prinos_5'
    ),
    'LK 2021'=>array(
      'líbí komorní' => 'nazor_kurz_m', 'chci velký' => 'nazor_kurz_p', 
      'přenosy ok' => 'nazor_online_p', 'přenosy vadí' => 'nazor_online_m', 
      'času dost' => 'nazor_cas_p', 'času málo' => 'nazor_cas_m'
    )
  );
  if ($x) {
    foreach ($x as $name=>$val) {
      switch ($name) {
        case 'sex':   $x->sex.= '% žen'; break;
        case 'vek':   $x->vek.= ' let'; break;
        case 'deti':  $x->deti.= ' dětí/LK'; break;
        case 'manzel':$x->manzel.= ' let manž.'; break;
        case 'novic': $x->novic.= '% nových'; break;
      }
      $y->celkem= $x->celkem;
    }
  }
  $proc= $celkem_roky ? ' '.round(100*$x->celkem/$celkem_roky).'%' : '';
  $tab.= $x && $x->celkem
      ? "<p>výběru vyhovuje ".kolik_1_2_5($x->celkem,"dotazník,dotazníky,dotazníků")."$proc</p>"
      : "<p>výběru nevyhovuje žádný dotazník</p>";
  foreach ($tmpl as $row => $clmns) {
    switch ($row) {
    case 'LK 2021':
      $y->r21= (object)array();
      if (strpos($par->cond,'dotaznik IN (0,2021)')===false) {
       foreach(array(1,2,3) as $h) {
          $fld= "hnizdo_{$h}p";
          $y->r21->$fld= '';
        } 
        break 2;        
      }
      $nh= select('SUM(IF(hnizdo=1,1,0)),SUM(IF(hnizdo=2,1,0)),SUM(IF(hnizdo=3,1,0))',
          'dotaz',"dotaznik IN (0,2021) AND duplicita='' ");
      $r1= "<th>$row</th><td class='vert'><p>nic nevadí</p></td>";
      $r2= "<td></td><td>{$x->nazor_ok}%</td>";
      foreach ($clmns as $name=>$val) {
        $pr= substr($val,-1,1);
        $r1.= "<td class='vert' title='prinos=$pr'><p>$name</p></td>";
        $r2.= "<td>{$x->$val}%</td>";
      }
      $tab.= "<br><table class='$tab_class'><tr>$r1</tr><tr>$r2</tr></table>";
      foreach(array(1,2,3) as $h) {
        $fld= "hnizdo_{$h}p";
        $y->r21->$fld= round(100*$x->$fld/$nh[$h-1]).'%';
      }
      break;
    case 'Přínos':
      $r1= "<th>$row</th><td class='vert'><p>celkově</p></td>";
      $r2= "<td></td><td>{$x->prinos}</td>";
      foreach ($clmns as $name=>$val) {
        $pr= substr($val,-1,1);
        $r1.= "<td class='vert' title='prinos=$pr'><p>$name</p></td>";
        $r2.= "<td>{$x->$val}%</td>";
      }
      $tab.= "<br><table class='$tab_class'><tr>$r1</tr><tr>$r2</tr></table>";
      break;
    case 'Hodnocení':
      $r1= "<th>$row</th>";
      $r2= "<td></td>";
      foreach ($clmns as $name=>$val) {
        $r1.= "<td title='$val' class='vert'><p>$name</p></td>";
        $r2.= "<td>{$x->$val}</td>";
      }
      $tab.= "<br><table class='$tab_class'><tr>$r1</tr><tr>$r2</tr></table>";
      break;
    case 'Témata':
      $r1= "<th>$row</th>";
      $r2= "<td></td>";
      foreach ($clmns as $name=>$val) {
        $r1.= "<td title='$val' class='vert'><p>$name</p></td>";
        $r2.= "<td>{$x->$val}%</td>";
      }
      $tab.= "<br><table class='$tab_class'><tr>$r1</tr><tr>$r2</tr></table>";
      break;
    case 'Statistika':
      $tab.= "<table class='$tab_class'><tr><th>$row</th>";
      foreach ($clmns as $name=>$val) {
        $tab.= "<td>{$x->$val}</td>";
      }
      $tab.= "</tr></table>";
      break;
    case 'proč jel':
    case 'dozvěděl se od':
      $tab.= "<table class='$tab_class'><tr><td class='first'>$row</td><td>";
      $plus= '';
      foreach ($clmns as $name=>$val) {
        if ( $x->$val && $name!='jiné' ) {
          if ( $name==':' ) {
            $td= $plus ? "</td><td>" : '';
            $tab.= "$td$plus jiné = {$x->$val}";
          }
          else {
            $tab.= "$plus $name";
            $plus= ' +';
          }
        }
      }
      $tab.= "</td></tr></table>";
      break;
    }
  }
  $style= "<style>
table.dot {
  width: 320px;
  margin-top: -1px;
}
table.dot th {
  width: 80px;
}
table.dot td.first {
  width: 76px;
}
table.dot .vert {
  vertical-align: bottom;
  height: 65px;
}
table.dot .vert p {
  transform: rotate(-90deg);
  position: relative;
  width: 18px;
  white-space: nowrap;
}
</style>";
  $y->html= $style.$tab;
                                  debug($y);
  return $y;
}
# --------------------------------------------------------------------------------------- dot import
# import digitalizovaných dotazníků
function dot_import ($rok) { trace();
  global $ezer_path_docs;
  $y= (object)array('html'=>'','err'=>'','war'=>'');
  $n_max= 0;
  $def= array(
    'statistika' => array(
       0 => '=id',
       1 => 'page',
       2 => '=sex', // muz/zena
       3 => 'vek',
       4 => 'deti',
       5 => 'manzel',
       6 => 'novic',
       7 => '=',   // vynechané
       8 => 'od_pratele', 
       9 => 'od_partner', 
      10 => 'od_pribuzni', 
      11 => 'od_pecoun', 
      12 => 'od_inzerce', 
      13 => 'od_chlapi', 
      14 => 'od_ymca', 
      15 => 'od_jine', 
      16 => 'od_jine_text',
      17 => '=',   // vynechané
      18 => 'proc_zlepsit',  
      19 => 'proc_krize',  
      20 => 'proc_opak',  
      21 => 'proc_jine',  
      22 => 'proc_jine_text'  
    ),
    'hodnoceni' => array(
      0 => '=id',
      1 => 'prednasky',
      2 => 'skupinky',
      3 => 'duchovno',
      4 => 'ubytovani',
      5 => 'strava',
      6 => 'pecedeti',
      7 => 'motto',
      8 => 'maturita',
      9 => 'hudba'
    ),
    'temata' => array(
      0 => '=id',
      1 => 'tema_male',
      2 => 'tema_dosp',
      3 => 'tema_matka',
      4 => 'tema_otec',
      5 => 'tema_mezigen',
      6 => 'tema_duchovni',
      7 => 'tema_jine',
      8 => 'tema_jine_text'
    ),
    'prinos' => array(
      0 => '=id',
      1 => 'prinos',
      2 => 'prinos_text'
    ),
    'slovne' => array(
      0 => '=id',
      1 => 'libilo',
      2 => 'vadilo',
      3 => 'vzkaz'
    )
  );
  $values= array(); // id => (value)
  // dotazníky od roku 2021 zpracujeme z živých dat na GDISKu
  if ($rok==2021) {
    $def_g= array(
      "A,x,id?",
      "B,r,hnizdo?Albeřice*3;Kroměříž*1;Olomouc*2",
      "C,r,sex?Muž*0;Žena*1",
      "D,i,vek?",
      "E,r,deti?1;2;3;žádné*0;více*4",
      "F,i,manzel?",
      "G,r,novic?Ano*1;Ne*0",
      "H,c,od_jine,od_jine_text?Přátelé*od_pratele;Příbuzní*od_pribuzni;Jezdil/a jsem jako pečovatel/ka*od_pecoun;Inzerce*od_inzerce;Chlapské akce*od_chlapi;Akce YMCA Setkání*od_ymca",
      "I,c,proc_jine,proc_jine_text?"
        . "chci zlepšovat naše manželství*proc_zlepsit;byli jsme v krizi*proc_krize;"
        . "jezdíme opakovaně*proc_opak",
      "J,i,prednasky?",
      "K,i,skupinky?",
      "L,i,duchovno?",
      "M,r,ubytovani?Bez ubytování*0",
      "N,r,strava?1;2;3;4;5;Bez stravy*0",
      "O,r,pecedeti?1;2;3;4;5;péči o děti jsme nevyužili*0",
      "P,i,motto?",
      "Q,i,maturita?",
      "R,i,hudba?",
      "S,t,libilo?",
      "T,t,vadilo?",
      "U,t,vzkaz?",
      "V,c,tema_jine,tema_jine_text?Výchova menších dětí*tema_male;Výchova dospívajících*tema_dosp;Vztahy  v rodině - matka a děti*tema_matka;Vztahy v rodině - otec a děti*tema_otec;Mezigenerační vztahy - širší rodina*tema_mezigen;Duchovní život*tema_duchovni",
      "W,r,prinos?1 - Ano, velmi významně*1;2 - Ano, částečně*2;3 - Nevím, to se uvidí*3;4 - Ne, nevidím změnu*4;5 - Ne, spíše naopak*5",
      "X,t,prinos_text?"
    );
    # přečtení seznamu skupin z tabulky
    # https://docs.google.com/spreadsheets/d/1dP_p6A8sHKPEStiaqJaeAhGV3kjUYqmjQrvBpvRahUA/edit#gid=1894516411
    $goo= "https://docs.google.com/spreadsheets/d";
    $key= "1dP_p6A8sHKPEStiaqJaeAhGV3kjUYqmjQrvBpvRahUA";         // Seznam dotazníků
    $prefix= "google.visualization.Query.setResponse(";           // přefix json objektu
    $x= file_get_contents("$goo/$key/gviz/tq?tqx=out:json"); //&sheet=$sheet");
    $xi= strpos($x,$prefix);
    $xl= strlen($prefix);
    $x= substr(substr($x,$xi+$xl),0,-2);
    $tab= json_decode($x)->table;
//                                                          debug($tab);
//                                                          debug($tab->cols);
    if ( $tab ) {
      $n= 0;
      // zjistíme, zda se zvýšil počet dotazníků - jinak odmítneme import
      $n_old= select('COUNT(*)','dotaz',"dotaznik=$rok"); // včetně duplicit
      $n_new= count($tab->rows);
      if ($n_old==$n_new) {
        $y->html= "Není žádný nový dotazník z roku $rok";
        goto end;
      }
      elseif ($n_old>$n_new) {
        $y->html= "POZOR: někdo sežral nějaké dotazníky nebo tam zapomněl filtr";
        goto end;
      }
      $y->html= "Přidávám ".kolik_1_2_5($n_new-$n_old,'nový dotazník,nové dotazníky,nových dotazníků');
      // projdeme dotazníky
      foreach ($tab->rows as $line=>$crow) {
        $value= array();
        $row= $crow->c; // odpovědi na otázky
//                                                          debug($row);
        $id= 0;
        foreach ($row as $i => $cols) {
          $d_i= $def_g[$i]; // definice i-té otázky
          $v= $row[$i];     // odpověď na i-tou otázku
          list($desc,$itms)= explode('?',$d_i);
          list(,$typ,$fld,$fld_text)= explode(',',$desc);
          $itms= explode(';',$itms);
//                                                          debug($itms);
          switch ($typ) {
            case 'x': $id= $line+1; break;
            case 'i':
              $value[$fld]= $v->v;
              break;
            case 'c':
              $vv= explode(', ',$v->v);
              foreach($vv as $iv=>$vi) {
                $preddefinovana= 0;
                foreach($itms as $itm_code) {
                  list($itm,$code)= explode('*',$itm_code);
                  if ($vi==$itm) {
                    $value[$code]= 1;
                    $preddefinovana= 1;
                  }
                }
                if (!$preddefinovana) { 
                  // obecná odpověď může obsahovat čárky, je ale vždy na konci
                  $value[$fld]= 1;
                  $value[$fld_text]= implode(', ',array_slice($vv,$iv));
                  break;
                }
              }
              break;
            case 'r':
              foreach($itms as $itm_code) {
                list($itm,$code)= explode('*',$itm_code);
                if ($v->v==$itm) {
                  $value[$fld]= isset($code) ? $code : $itm;
                  break 2;
                }
              }
              $y->war.= "dotazník $id: neznámá odpověď '{$v->v}'<br>";
              break;
            case 't':
              $value[$fld]= $v->v;
              break;
          }
        }
        $n++;
        // doplnění nových do tabulky DOTAZ -- zachová položky nazor_* v existujících
        $exists= select('COUNT(*)','dotaz',"dotaznik=$rok AND id=$id");
        if (!$exists) {
                                                  debug($value);
          $set= "dotaznik=$rok, id=$id";
          foreach ($value as $fld => $val) {
            $set.= ", $fld='$val'";
          }
          query("INSERT INTO dotaz SET $set");
        }
      }
    }
  }
  elseif ($rok==2022 || $rok==2023) {
    // export do json v roce 2022 již nepřenáší textové hodnoty, pokud se očekávají čísla
    // například již nelze "O,r,pecedeti?1;2;3;4;5;péči o děti jsme nevyužili*0"
    // proto je download před CSV
    $LIMIT= 0;
    $def_g= array(
      "A,x,id?",
      "B,r,sex?Muž*0;Žena*1",
      "C,i,vek?",
      "D,r,deti?1;2;3;žádné*0;více*4",
      "E,i,manzel?",
      "F,r,novic?Ano*1;Ne*0",
      "G,c,od_jine,od_jine_text?Přátelé*od_pratele;Příbuzní*od_pribuzni;Jezdil/a jsem jako pečovatel/ka*od_pecoun;Inzerce*od_inzerce;Chlapské akce*od_chlapi;Akce YMCA Setkání*od_ymca",
      "H,c,proc_jine,proc_jine_text?"
        . "chci zlepšovat naše manželství*proc_zlepsit;byli jsme v krizi*proc_krize;"
        . "jezdíme opakovaně*proc_opak",
      "I,i,prednasky?",
      "J,i,skupinky?",
      "K,i,duchovno?",
      "L,r,ubytovani?1;2;3;4;5;Bez ubytování*0",
      "M,r,strava?1;2;3;4;5;Bez stravy*0",
      "N,r,pecedeti?1;2;3;4;5;péči o děti jsme nevyužili*0",
      "O,i,motto?",
      "P,i,maturita?",
      "Q,i,hudba?",
      "R,t,libilo?",
      "S,t,vadilo?",
      "T,t,vzkaz?",
      "U,c,tema_jine,tema_jine_text?Výchova menších dětí*tema_male;Výchova dospívajících*tema_dosp;Vztahy  v rodině - matka a děti*tema_matka;Vztahy v rodině - otec a děti*tema_otec;Mezigenerační vztahy - širší rodina*tema_mezigen;Duchovní život*tema_duchovni",
      "V,r,prinos?1 - Ano, velmi významně*1;2 - Ano, částečně*2;3 - Nevím, to se uvidí*3;4 - Ne, nevidím změnu*4;5 - Ne, spíše naopak*5",
      "W,t,prinos_text?"
    );
    # přečtení seznamu skupin z tabulky
    # https://docs.google.com/spreadsheets/d/1dP_p6A8sHKPEStiaqJaeAhGV3kjUYqmjQrvBpvRahUA/edit#gid=1894516411
    $goo= "https://docs.google.com/spreadsheets/d";
    $key= $rok==2022 ? "13GuKhM6vwo-zfN97UWazdoDdzKpqGXNmQls7sYTzo6c" : (
          $rok==2023 ? "17E5dotr5EOhlLgOM7dyjTVV8h22OcGwHAYpCuhEUtWs" : '');
    $url= "$goo/$key/export?format=csv";
    $f= @fopen($url, "r");
    if ( !$f ) { $y->err= "odkaz $url nelze otevřít"; goto end; }
    $line= fgets($f, 1000); // hlavička
    $cols= fgetcsv($f); 
    $rows= array();
//                                                          debug($cols,'cols');
    $n= 0;
    while (($row= fgetcsv($f)) !== false) {
      $rows[]= $row; 
      $n++;
      if ($LIMIT && $n>=$LIMIT) break;
    }
//                                                          debug($rows,'rows');
//    goto end;
    $n= 0;
    // zjistíme, zda se zvýšil počet dotazníků - jinak odmítneme import
    $n_old= select('COUNT(*)','dotaz',"dotaznik=$rok"); // včetně duplicit
    $n_new= count($rows);
    if ($n_old==$n_new) {
      $y->html= "Není žádný nový dotazník z roku $rok";
      goto end;
    }
    elseif ($n_old>$n_new) {
      $y->html= "POZOR: někdo sežral nějaké dotazníky nebo tam zapomněl filtr";
      goto end;
    }
    $y->html= "Přidávám ".kolik_1_2_5($n_new-$n_old,'nový dotazník,nové dotazníky,nových dotazníků');
    // projdeme dotazníky
    foreach ($rows as $line=>$row) {
      $value= array('warning'=>'');
//      $row= $crow->c; // odpovědi na otázky
//                                                          debug($row,"row $line");
      $id= 0;
//      break;
      foreach ($row as $i => $cols) {
        $d_i= $def_g[$i]; // definice i-té otázky
        $v= $row[$i];     // odpověď na i-tou otázku
        list($desc,$itms)= explode('?',$d_i);
        list(,$typ,$fld,$fld_text)= explode(',',$desc);
        $itms= explode(';',$itms);
//                                                          debug($itms,"itms $typ,$fld");
        switch ($typ) {
          case 'x': $id= $line+1; break;
          case 'i':
            $value[$fld]= $v;
            if (!is_numeric($v)) $value['warning'].= " $fld=`$v`";
            break;
          case 'c':
            $vv= explode(', ',$v);
            foreach($vv as $iv=>$vi) {
              $preddefinovana= 0;
              foreach($itms as $itm_code) {
                list($itm,$code)= explode('*',$itm_code);
                if ($vi==$itm) {
                  $value[$code]= 1;
                  $preddefinovana= 1;
                }
              }
              if (!$preddefinovana) { 
                // obecná odpověď může obsahovat čárky, je ale vždy na konci
                $value[$fld]= 1;
                $value[$fld_text]= implode(', ',array_slice($vv,$iv));
                break;
              }
            }
            break;
          case 'r':
            foreach($itms as $itm_code) {
              list($itm,$code)= explode('*',$itm_code);
              if ($v==$itm) {
                $value[$fld]= isset($code) ? $code : $itm;
                break 2;
              }
            }
            $y->war.= "dotazník $id: neznámá odpověď '{$v}' pro '$fld'<br>";
            $value['warning'].= " $fld='$v'";
            break;
          case 't':
            $value[$fld]= $v;
            break;
        }
      }
      $n++;
      // doplnění nových do tabulky DOTAZ -- zachová položky nazor_* v existujících
      $exists= select('COUNT(*)','dotaz',"dotaznik=$rok AND id=$id");
      if (!$exists) {
//                                                debug($value,'value');
        $set= "dotaznik=$rok, id=$id";
        foreach ($value as $fld => $val) {
          $set.= ", $fld='$val'";
        }
        $set= strtr($set,array(
            "\xf0\x9f\x99\x82"=>"&#x1F642;",
            "\xf0\x9f\x98\x8d"=>"&#x1F60D;",
            "\xf0\x9f\x91\x8d"=>"&#x1F44D;",
            '*'=>'*'));
        if ($value['vek']==48 && $value['sex']==1 && $value['deti']==3 ) debug($value,"$set");
        query("INSERT INTO dotaz SET $set");
      }
    }
  }
  // předchozí roky - bez elektronického vyplňování
  else {
    $fpath= "$ezer_path_docs/import/MS$rok";
    foreach ($def as $fname=>$clmn) {
      $fullname= "$fpath/MS$rok-$fname.csv";
      if ( !file_exists($fullname) ) { $y->war.= "soubor $fullname neexistuje "; goto end; }
      $f= @fopen($fullname, "r");
      if ( !$f ) { $y->err.= "soubor $fullname nelze otevřít"; goto end; }
      $n= 0;
      while (($line= fgets($f, 1000)) !== false) {
        $n++;
        $value= array();
        $id= 0;
        $line= win2utf($line,1);
        $data= str_getcsv($line,';'); 
        foreach ($clmn as $c => $name) {
          if ( $name[0]=='=') {
            $name= substr($name,1);
            switch ($name) {
              case 'id': $id= $data[$c]; break;
              case 'sex':
                $value[$name]= $data[$c]=='muž' ||$data[$c]=='Muž'  ? 0 : (
                               $data[$c]=='žena'||$data[$c]=='Žena' ? 1 : -1); break;
              case '': break;
            }
          }
          else {
            $value[$name]= $data[$c];
          }
        }
        if ( !$id ) {
          $y->war.= "chybí ID/$fname: $line<br>";
          break;
  //        continue;
        }
        // zařazení value
        if ( !isset($values[$id]) ) 
          $values[$id]= (object)array('id'=>$id);
        foreach ($value as $name => $val) {
          $values[$id]->$name= $val;
        }
        if ($n_max && $n>=$n_max) break;
      }
      fclose($f); $f= null;
    }
    // zápis do tabulky DOTAZ
    // starší dotazníky z lokálních tabulek
    query("DELETE FROM dotaz WHERE dotaznik=$rok");
    foreach ($values as $id => $value) {
      $flds= "dotaznik";
      $vals= "$rok";
      foreach ($value as $name => $val) {
        $flds.= ",$name";
        $vals.= ",'$val'";
      }
      query("INSERT INTO dotaz ($flds) VALUE ($vals)");
    }
  }
//                                                         debug($values);
end:  
  display($y->war);
  return $y;
}
/** ===========================================================================================> VPS **/
# ------------------------------------------------------------------------------------- vps historie
# 
function vps_historie ($org,$par,$export) {
  $cert= array(); // certifikát rok=>poslední číslo
  $letos= date('Y');
  list($mez1,$mez2)= explode(',',$par->parm);
  $hrana1= $letos - $mez1;
  $hrana2= $letos - $mez2;
  $vps1= $org==1 ? '3,17' : '3';
  // pole pro tabulku
  $clmn= $css= array();
// sloupce
  $tits= array("pár:26","poprvé:10","kolikrát:10","naposledy:10",
            $org==1?"VPS1:10":"1.školení:10",
            "VPS2");
  $flds= array('jm','od','n','do','vps_i','vps2');
  // sloupce
  $tits= array("pár:26","kolikrát:10","VPS2",'skupinky');
  $flds= array('jm','n','vps2','hodn');
  // doplnění nadpisů historie
  for ($r=$letos; $r>=$hrana2; $r--) {
    $r2= $r%100;
    array_push($tits,"$r2");
    array_push($flds,$r2);
  }
  // seznam VPS a základní údaje
  $rx= pdo_qry("SELECT
      r.id_rodina,r.nazev,
      GROUP_CONCAT(DISTINCT IF(t.role='a',o.jmeno,'') SEPARATOR '') as jmeno_m,
      GROUP_CONCAT(DISTINCT IF(t.role='b',o.jmeno,'') SEPARATOR '') as jmeno_z,
      MIN(IF(druh=1 AND funkce=1,YEAR(datum_od),9999)) AS OD,
      CEIL(CHAR_LENGTH(
        GROUP_CONCAT(DISTINCT IF(druh=1 AND funkce=1,YEAR(datum_od),'') SEPARATOR ''))/4) AS Nx,
      MAX(IF(druh=1 AND funkce=1,YEAR(datum_od),0)) AS DO,
      MIN(IF(druh IN ($vps1),YEAR(datum_od),9999)) as VPS_I,
      1
    FROM rodina AS r
    JOIN pobyt AS p
    JOIN akce as a ON id_akce=id_duakce
    JOIN tvori AS t USING (id_rodina)
    JOIN osoba AS o USING (id_osoba)
    WHERE spec=0 AND zruseno=0 AND r.id_rodina=i0_rodina AND a.access&$org
      AND druh IN (1,$vps1)
      --  AND r.id_rodina=3329 
    GROUP BY r.id_rodina
    ORDER BY r.nazev");
  while ( $rx && ($x= pdo_fetch_object($rx)) ) {
    $idr= $x->id_rodina;
    // číslování certifikátů
    $skola= $x->VPS_I==9999 ? 0 : $x->VPS_I;
    $c1= $c2= '';
    if ( $skola ) {
      if ( !isset($cert[$skola]) ) $cert[$skola]= 0;
      $cert[$skola]++; $c1= ($org==1?'vps':'pps')."_$skola/{$cert[$skola]}";
      $cert[$skola]++; $c2= ($org==1?'vps':'pps')."_$skola/{$cert[$skola]}";
    }
    // ohlídání období
    if ( $x->DO<$hrana1 ) continue;
    $cclen= $_cinny_od ?: '-';
    // odpověď 1
    $cl= array(
      'jm'=>"{$x->jmeno_m} a {$x->jmeno_z} {$x->nazev}",
      'od'=>$x->OD,'n'=>$x->Nx,'do'=>$x->DO,
      'vps_i'=>$skola ?: '-'
    );
    // doplnění odpověďi podle mez2+1 běhů
    if ( $org==1 
//        && $idr==3329 
        ) {
      // celkový počet účastí na akcích pro činné členy
      $cl['vps2']= select('COUNT(*)','akce JOIN pobyt ON id_akce=id_duakce',
          "akce.druh=3 AND nazev RLIKE 'VPS *(2|II)' AND i0_rodina=$idr");
    }
    $clmn[$idr]= $cl;
  }
  // doplnění průběhu ročních běhů
  for ($r=$letos; $r>=$hrana2; $r--) {
    $r2= $r%100; $lk= "$r2/L"; $a[0]= "$r2/P"; $a[1]= "$r2/J"; $r1= $r+1;
    $ida_lk= select('id_duakce','akce',"access=$org && druh=1 AND YEAR(datum_od)=$r");
    $ida[0]= select('id_duakce','akce',"access=$org && druh=2 AND datum_od BETWEEN '$r-09-01' AND '$r-12-31'")?:0;
    $ida[1]= select('id_duakce','akce',"access=$org && druh=2 AND datum_od BETWEEN '$r1-01-01' AND '$r1-05-31'")?:0;
    // získej skupinky LK daného roku pro sledované VPS
    $skups= array(); // idr_vps -> [idr_ucastnik,...]
    $rs= pdo_qry("
      SELECT GROUP_CONCAT(i0_rodina ORDER BY IF(funkce=1,1,0) DESC) AS _skup
      FROM pobyt AS p
      WHERE id_akce=$ida_lk AND skupina>0
      GROUP BY skupina
      ORDER BY skupina");
    while ( $rs && (list($skup)= pdo_fetch_array($rs)) ) {
      $idrs= explode(',',$skup);
      $vps= array_shift($idrs);
      if ( isset($clmn[$vps]) )
        $skups[$vps]= $idrs;
    }
    // ohodnoť přítomnost na obnovách: 3=všichni; 2=někteří; 1=žádní
    $ox= array();
    for ($i= 0; $i<=1; $i++ ) {
      $idrs= select("GROUP_CONCAT(i0_rodina)",'pobyt',"id_akce=$ida[$i]");
      $ucast= explode(',',$idrs);
      foreach ($skups as $vps => $skup) {
        if ( $idrs ) {
          $jo= $ne= 0;
          foreach ($skup as $idr) {
            if ( in_array($idr,$ucast) ) 
              $jo++;
            else 
              $ne++;
          }
          $clmn[$vps][$r2].= 
              $jo && !$ne ? 3 : ( $jo && $ne ? 2 : (!$jo && $ne ? 1 : 0));
        }
        else {
          $clmn[$vps][$r2].= '0'; 
        }
      }
    }
  }
  // pokus o bodovací systém
  $bodys= array(
      'A' => '3:33,32,23,13,30',
      'B' => '2:22,12,20',
      'C' => '1:11,21,10',
      'X' => '0:00'
  );
  // pokus o bodovací systém
  // 3=celá skupinka, 2=částečná, 1=nikdo, 0=obnova ještě nebyla nebo VPS nepřijela
  $note= "Písmena ABC charakterizují účast na podzimní a letní obnově, jsou určena takto:";
  $bodys= array(
      'A' => '3:33,23,13,30',
      'B' => '2:32,22,12,20',
      'C' => '1:11,21,31,10',
      'X' => '0:00'
  );
  $body= $hodn= array(); // 32 -> 'B' ... 2
  foreach ($bodys as $b=>$hvs) {
    list($h,$vs)= explode(':',$hvs);
    $note.= "<br>$b = ".strtr($vs,array(3=>'c',2=>'č',1=>'n',0=>'-',','=>' ... '));
    foreach (explode(',',$vs) as $v) {
      $body[$v]= $b;
      $hodn[$v]= $h;
    }
  }
  $note.= "<br>kde c znamená, že na obnově byla celá skupinka, č znamená částečná, "
      . "n znamená nikdo nepřijel, - znamená nepřijela VPS<br><br>"
      . "Ve sloupci 'skupinky' je jakási průměrná známka - POZOR! na účast párů často VPS nemůže mít vliv (nemoc, těhotenství,...)<br><br>";
  foreach ($clmn as $vps => $cl) {
    $n= $h= 0; 
    for ($r=$letos; $r>=$hrana2; $r--) {
      $r2= $r%100; 
      if ( isset($cl[$r2]) ) {
        $n++;
        $h+= $hodn[$clmn[$vps][$r2]];
        $clmn[$vps][$r2]= strtr($clmn[$vps][$r2],$body);
      }
    }
    if ( $n ) {
      $znamka= 4-round($h/$n,1);
      $clmn[$vps]['hodn']= $znamka;
//      if ( $znamka > 2 ) {
//        $css[$vps]['hodn']= 'yellow';
//      }
    }
  }
  return vps_table($tits,$flds,$clmn,'hodn',$export,false,$note);
}
# ------------------------------------------------------------------------------------=> . vps table
# $css_or_sort = string znamená řazení podle toho sloupce, pole znamená aplikaci css
function vps_table($tits,$flds,$clmn,$css_or_sort,$export=false,$row_numbers=false,$note='') {  
  $ret= (object)array('html'=>'');
  // zobrazení tabulkou
  $tab= '';
  $thd= '';
  $n= 0;
  if ( $export ) {
    $ret->tits= $tits;
    $ret->flds= $flds;
    $ret->clmn= $clmn;
  }
  else {
    // případné řazení
    if ( is_string($css_or_sort)) {
      usort($clmn, function($a, $b) use ($css_or_sort) {
        return ($a[$css_or_sort] < $b[$css_or_sort]) ? -1 : 1;
      });
    }
    $fmt= array();
    // písmena sloupců
    if ( $row_numbers ) {
      $ths.= "<th> </th>";
      for ($a= 0; $a<count($tits); $a++) {
        $id= chr(ord('A')+$a);
        $ths.= "<th>$id</th>";
      }
      $ths.= "</tr><tr>";
    }
    // titulky
    if ( $row_numbers )
      $ths.= "<th>1</th>";
    foreach ($tits as $i=>$idw) {
      list($id,$len,$f)= explode(':',$idw);
      $ths.= "<th>$id</th>";
      if ( $f ) $fmt[$flds[$i]]= $f;
    }
    foreach ($clmn as $i=>$c) {
      $c= (array)$c;
      $tab.= "<tr>";
      if ( $row_numbers )
        $tab.= "<th>".($i+2)."</th>";
      foreach ($flds as $f) {
        $class= is_array($css_or_sort) && isset($css_or_sort[$i][$f]) 
            ? "class='{$css_or_sort[$i][$f]}'" : '';
        if ( $f=='id_osoba' || $f=='^id_osoba' )
          $tab.= "<td style='text-align:right'>".tisk2_ukaz_osobu($c[$f])."</td>";
        elseif ( $f=='^id_rodina' )
          $tab.= "<td style='text-align:right'>".tisk2_ukaz_rodinu($c['^id_rodina'])."</td>";
        elseif ( $f=='^id_pobyt' )
          $tab.= "<td style='text-align:right'>".tisk2_ukaz_pobyt($c['^id_pobyt'])."</td>";
        elseif ( is_numeric($c[$f]) || $fmt[$f]=='d' ) 
          $tab.= "<td $class style='text-align:right'>{$c[$f]}</td>";
        else {
          $tab.= "<td $class style='text-align:left'>{$c[$f]}</td>";
        }
      }
      $tab.= "</tr>";
      $n++;
    }
    $ret->html= "{$note}Seznam má $n řádků<br><br><div class='stat'>
      <table class='stat'><tr>$ths</tr>$tab</table></div>";
  }
  return $ret;
}
/** ======================================================================================> POKLADNA **/
# ---------------------------------------------------------------------------------- pipe pdenik_typ
// 0=V 1=P
function pipe_pdenik_typ ($x,$save=0) {
  if ( $save ) {     // převeď zobrazení na uložení
    $z= $x=='V' ? 1 : 2;
  }
  else {             // převeď uložení na zobrazení
    $z= $x==1 ? 'V' : 'P';
  }
  return $z;
}
# ---------------------------------------------------------------------------------- p pdenik_insert
# form_make
# $cislo==0 způsobí nalezení nového čísla dokladu
function p_pdenik_insert($typ,$org,$org_abbr,$datum) {
  global $x,$y;
  // převzetí hodnot
//                                                          debug(Array($typ,$org,$cislo,$datum),'p_denik_insert');
  $db= $x->db;
  $select= array();
  make_get($set,$select,$fields);
  // nalezení nového čísla dokladu (v každé pokladně se zvlášť číslují příjmy a výdaje)
  $year= substr(trim($datum),-4);
  $qry= "SELECT max(cislo) as c FROM $db.pdenik WHERE org=$org AND typ=$typ AND year(datum)=$year";
  $res= pdo_qry($qry);
  if ( $res && $row= pdo_fetch_assoc($res) ) {
    $cislo= 1+$row['c'];
  }
  if ( $cislo ) {
    $elem= new stdClass;
    $elem->cislo= $cislo;
    $y->load= $elem;
    // vytvoření dokladu
//                                                           debug($set);
    $s= implode(',',$set['pdenik']);
    $ident= $org_abbr.($typ==1?'V':'P').substr($year,2,2).'_'.str_pad($cislo,5,'0',STR_PAD_LEFT);
    $qry= "INSERT INTO $db.pdenik SET $s,org=$org,typ=$typ,cislo=$cislo,ident='$ident'";
    $res= pdo_qry($qry);
    $y->key= pdo_insert_id();
  }
}
# ----------------------------------------------------------------------------------- kasa menu_show
# ki - menu
# $cond = podmínka pro pdenik nastavená ve fis_kasa.ezer
# $day =  má formát d.m.yyyy
function kasa_menu_show($k1,$k2,$k3,$cond=1,$day='',$db='') {
  global $answer_db;
  if (!$db) $db= $answer_db;
  $html= '';
  switch ( "$k2 $k3" ) {
  case 'stav aktualne':
    $dnes= date('j.n.Y');
    $dnes_mysql= date('Y-m-d');
    $html.= "<div class='karta'>Aktuální stav pokladen ke dni $dnes</div>";
    $year= date('Y');
    $interval= " datum BETWEEN '$year-01-01' AND '$dnes_mysql'";
    $html.= kasa_menu_comp($interval,$db);
    break;
  case 'stav s_filtrem':
    $html.= "<div class='karta'>Stav pokladen podle nastavení </div>";
    $html.= kasa_menu_comp($cond,$db);
    $html.= "<p><i>filtr: $cond</i></p>";
    break;
  case 'stav k_datu':
    $html.= "<div class='karta'>Stav pokladen ke dni $day </div>";
    $until= sql_date1($day,1);
    $year= substr($until,0,4);
    $interval= " datum BETWEEN '$year-01-01' AND '$until'";
    $html.= kasa_menu_comp($interval,$db);
    break;
  case 'export letos':
    $rok= date('Y');
    $title= "Pokladní deník roku $rok";
    $html.= "<div class='karta'>Export pokladních deníků roku $rok</div>";
    $cond= " datum BETWEEN '$rok-01-01' AND '$rok-12-31'";
    $html.= kasa_export($cond,"pokladna_{$rok}",$db,$title);
    break;
  case 'export vloni':
    $rok= date('Y')-1;
    $title= "Pokladní deník roku $rok";
    $html.= "<div class='karta'>Export pokladních deníků roku $rok</div>";
    $cond= " datum BETWEEN '$rok-01-01' AND '$rok-12-31'";
    $html.= kasa_export($cond,"pokladna_{$rok}",$db,$title);
    break;
  }
  return $html;
}
# -------------------------------------------------------------------------------------- kasa export
function kasa_export($cond,$file,$db,$title) { trace();
  global $ezer_version;
  $xls= "|open $file";
  $qry_p= "SELECT * FROM $db.pokladna ";
  $res_p= pdo_qry($qry_p);
  while ( $res_p && $p= pdo_fetch_object($res_p) ) {
    $xls.= "\n|sheet vypis;;L;page";
    $xls.= "\n|A1 $title::size=13 bold";
    // hlavička
    $fields= explode(',','ident:11,číslo:6,datum:10,příjmy:13,výdaje:13,stav:13,'
        . 'od koho/komu:30,účel:30,př.:5');
    $n= 3; $a= 0; $clmns= $del= '';
    $xls.= "\n";
    foreach ($fields as $fa) {
      list($title,$width)= explode(':',$fa);
      $A= Excel5_n2col($a++);
      $xls.= "|$A$n $title";
      if ( $width ) {
        $clmns.= "$del$A=$width";
        $del= ',';
      }
    }
    if ( $clmns ) $xls.= "\n|columns $clmns ";
    $xls.= "\n|A$n:$A$n bcolor=ffc0e2c2 wrap border=+h|A$n:$A$n border=t";
    // data
    $n0= $n= 4; 
    $qry= "SELECT * FROM $db.pdenik WHERE $cond AND pdenik.org={$p->id_pokladna} ORDER BY datum";
    $res= pdo_qry($qry);
    while ( $res && $d= pdo_fetch_object($res) ) {
      $xls.= "\n|A$n {$d->ident}";
      $xls.= "|B$n {$d->cislo}::right";
        // převod data
      $datum= sql2xls($d->datum);  
      $xls.= "|C$n $datum::right date";
      if ( $d->typ==1 ) {
        $xls.= "|D$n 0";
        $xls.= "|E$n {$d->castka}::kc right";
      } else {
        $xls.= "|D$n {$d->castka}::kc right";
        $xls.= "|E$n 0";
      }
      $n1= $n-1;
      $s= $n==$n0 ? "" : "F$n1+";
      $xls.= "|F$n ={$s}D$n-E$n::kc right";
      $xls.= "|G$n {$d->komu}";
      $xls.= "|H$n {$d->ucel}";
      $xls.= "|I$n {$d->priloh}";
      $n++;
    }
    $n1= $n-1;
    $xls.= "\n|A$n1:$A$n1 border=,,t,";
    $xls.= "\n|C$n CELKEM::right";
    $xls.= "|D$n =SUM(D$n0:D$n1)::kc right";
    $xls.= "|E$n =SUM(E$n0:E$n1)::kc right";
    $xls.= "|F$n =D$n-E$n::kc right";
    $xls.= "|C$n:F$n bold";
  }
  // časová značka
  $kdy= date("j. n. Y");
  $n+= 2;
  $xls.= "|A$n Výpis ze dne $kdy::italic";
  $xls.= "\n|close";
//                                      display($xls);
  require_once "ezer$ezer_version/server/vendor/autoload.php";
  $inf= Excel2007($xls,1);
  if ( $inf ) {
    $html.= "Export se nepovedlo vygenerovat ($inf)";
  }
  else {
    $html.= "Byl vygenerován soubor pro Excel: <a href='docs/$file.xlsx'>$file.xlsx</a>";
  }
  return $html;
}
# ----------------------------------------------------------------------------------- kasa menu_comp
function kasa_menu_comp($cond,$db) {
  $celkem= 0;
  $html= "<table>";
  $qry= "SELECT nazev, sum(if(typ=2,castka,-castka)) as s, abbr FROM $db.pdenik
        LEFT JOIN $db.pokladna ON pdenik.org=id_pokladna WHERE $cond GROUP BY pdenik.org";
  $res= pdo_qry($qry);
  while ( $res && $row= pdo_fetch_assoc($res) ) {
    $popis= $row['nazev'];
    $u= $row['abbr'];
    $stav= $row['s'];
    $celkem+= $stav;
    $mena= "CZK";
    $html.= "<tr><td align='right'><b>$stav</b></td><td align='right'>$mena v pokladně</td>"
        . "<td><b>$u</b> <i>$popis</i></td></tr>";
  }
  $html.= "<tr><td align='right'><hr/></td><td></td><td></td></tr>";
  $celkem= number_format($celkem,2,'.',' ');
  $html.= "<tr><td align='right'><b>$celkem</b></td><td>CZK celkem </td><td></td></tr>";
  $html.= "</table>";
  return $html;
}
# ---------------------------------------------------------------------------------------- kasa send
# pošle dopis pro $who - pokud je to * tak všem
function kasa_send($whos,$to_send=0) {
  $html= '';
  list($adr,$replyto,$par,$subj,$txt)= select('adr,replyto,par,subj,txt','cron',"batch='rr-note'");
  $adresy= (array)json_decode($adr);
  debug($adresy,'adr');
  $subst= (array)json_decode($par);
  debug($subst,'par');
  $n= 0;
  $whos= $whos=='*' ? array_keys($adresy) : explode(',',$whos);
  foreach ($whos as $who) {
    $par= $subst[$who];
    $txt_par= str_replace('{poznamka}',$par,$txt);
    if ($to_send) {
      $ok= kasa_send_mail("Měsíční připomenutí zápisu zůstatků pokladen",$txt_par,
          $replyto,$adresy[$who],'','mail',$replyto);
      if (!$ok) break;
      $n++;
    }
    else {
      $html.= "<table class='stat'>
        <tr><td>ADRESA <b>$who</b>: $adresy[$who]</td></tr>
        <tr><td>REPLY_TO: $replyto</td></tr>
        <tr><td>PŘEDMĚT: $subj</td></tr>";
      $html.= "<tr><td>$txt_par</td></tr></table><br>";
    }
  }
  if ($to_send) $html.= "<br><br>odesláno $n mailů z ".count($whos);
  return $html;
}
# ------------------------------------------------------------------------------------ kasa send_log
# zobrazí časová razítka
function kasa_send_log($typ,$subj='') {
  $html= '<dl>';
  $rs= pdo_qry("SELECT DATE(kdy),GROUP_CONCAT(TIME(kdy)),pozn  
    FROM stamp WHERE typ='$typ' GROUP BY CONCAT(DATE(kdy),pozn) ORDER BY kdy DESC LIMIT 24");
  while ( $rs && (list($den,$cas,$pozn)= pdo_fetch_row($rs)) ) {
    $html.= "<dt>$den $cas</dt><dd>$pozn</dd>";
  }
  $html.= "</dl>";
  return $html;
}
# ----------------------------------------------------------------------------------- kasa send_mail
# pošle systémový mail, pokud není určen adresát či odesílatel jde o mail správci aplikace
# $to může být seznam adres oddělený čárkou
function kasa_send_mail($subject,$html,$from='',$to='',$fromname='',$typ='',$replyto='',$lognote='') { //trace();
  global $ezer_path_serv, $EZER, $api_gmail_user, $api_gmail_pass;
  $to= $to ? $to : $EZER->options->mail;
  // poslání mailu
  $phpmailer_path= "$ezer_path_serv/licensed/phpmailer";
  require_once("$phpmailer_path/class.smtp.php");
  require_once("$phpmailer_path/class.phpmailer.php");
  // napojení na mailer
  $mail= new PHPMailer;
  $mail->SetLanguage('cs',"$phpmailer_path/language/");
  
  $mail->IsSMTP();
  $mail->Mailer= 'smtp';
  $mail->Host= "smtp.gmail.com";
  $mail->Port= 465;
  $mail->SMTPAuth= 1;
  $mail->SMTPSecure= "ssl";
  $mail->Username= $api_gmail_user;
  $mail->Password= $api_gmail_pass;
  $mail->CharSet = "utf-8";
  $mail->From= $from;
  $mail->AddReplyTo($replyto?:$from);
  $mail->FromName= $fromname;
  foreach (explode(',',$to) as $to1) {
    $mail->AddAddress($to1);
  }
  $mail->Subject= $subject;
  $mail->Body= $html;
  $mail->IsHTML(true);
  // pošli
  $ok= $mail->Send();
  if ( !$ok )
    fce_warning("Selhalo odeslání mailu: $mail->ErrorInfo");
  else {
    // zápis do stamp
    $dt= date('Y-m-d H:i:s');
    if ($lognote) $subject.= " ... $lognote";
    query("INSERT INTO stamp (typ,kdy,pozn) VALUES ('$typ','$dt','$subject')");
  }
  return $ok;
}
/** ===========================================================================================> DŮM **/
# ---------------------------------------------------------------------------------- ds compare_list
function ds_compare_list($orders) {  #trace('','win1250');
  $errs= 0;
  $html= "<dl>";
  $n= 0;
  if ( $orders ) {
    foreach (explode(',',$orders) as $order) {
      $x= ds_compare($order);
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
# --------------------------------------------------------------------------------------- ds compare
function ds_compare($order) {  #trace('','win1250');
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
    $vek= ds_vek($u->narozeni,$o->fromday);
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
# ===========================================================================================> hosté
# -------------------------------------------------------------------------------------- sys db_info
# vygeneruje menu.group pro 7 let 
function ds_ceny_group() { //debug($par);
  global $ezer_version;
  // zjištění nejnovějšího ceníku
  ezer_connect('setkani');
  $nejnovejsi= select('MAX(rok)','setkani4.ds_cena');
  // doplnění leftmenu.group: "item {title:yyyy, par:{rok:yyyy}}" pro yyyy= letos,loni,...
  $itms= array();
  for ($i= 0; $i<7; $i++) {
    $rok= $nejnovejsi-$i;
    $itms["rok_$rok"]= (object)array(
        'type'=>'item',
//        'id'=>"rok_$rok",
        'options'=>(object)array(
          'title'=>$rok,
          'par'=> $ezer_version=='3.2'
            ? (object)array('*'=>(object)array('rok'=>$rok))
            : (object)array('rok'=>$rok)
      ));
  }
  return (object)array('type'=>'menu.group','options'=>(object)array(),'part'=>$itms);
}
# -------------------------------------------------------------------------------------- ds kli_menu
# vygeneruje menu pro loňský, letošní a příští rok ve tvaru objektu pro ezer2 pro zobrazení klientů
# určující je datum zahájení pobytu v objednávce
function ds_kli_menu() {
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
  for ($y= -1; $y<=1; $y++) {
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
# ======================================================================================> objednávky
# ------------------------------------------------------------------------------------ ds cenik_list
# vrátí seznam položek ceníku Domu setkání zadaného roku (default je letošní platný)
# pokud je zadaný host vrátí také počet objednaných instancí položek ceníku
# ve kterém zohlední aktuální opravy podle položky ds_osoba.oprava
function ds_cenik_list($cenik_roku=0,$order=0,$host=0) {
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
    $luzko_pokoje= ds_cat_typ();
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
      $host_pol= ds_polozky_hosta($ob,$os,$luzko_pokoje,$ds_luzko,$ds_strava);
      foreach ($host_pol->cena as $field=>$value) {
        $pol->$field= $value;
      }
      // ceny za položky
      $one= ds_platba_hosta($cenik_roku,$host_pol->cena,$fields,'',true);
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
  return $y;
}
# ----------------------------------------------------------------------------------- ds cena_pobytu
# ASK
# vypočítá cenu pobytu účastníka (1), rodiny (2), akce (3)
# $id_osoba je z tabulky ds_osoba obsahující osobo-dny 
function ds_cena_pobytu($idos,$cenik_roku) {
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
  $y->fields->jmeno= wu($jmeno);
  $y->fields->prijmeni= wu($prijmeni);
  $y->fields->rodina= wu($rodina);
  $ob= select_object('*','tx_gnalberice_order',"uid=$order",'setkani');
  $cenik_roku= $cenik_roku?: date('Y',$ob->untilday);
  ds_cenik($cenik_roku);
  
  // sběr a kontrola dat pro hosta, rodinu, celou objednávku
  foreach (array(1=>"id_osoba=$idos",2=>"rodina='$rodina'",3=>"1") as $i=>$cond) {
    $fields= (object)array();
    $ros= pdo_qry("SELECT * FROM ds_osoba WHERE id_order=$order AND $cond");
    while ( $ros && $os= pdo_fetch_object($ros) ) {
      if ( !$os->pokoj ) { $y->err= "není zapsán pokoj pro $y->prijmeni $y->jmeno "; goto end; }
      $host_pol= ds_polozky_hosta($ob,$os,$luzko_pokoje,$ds_luzko,$ds_strava);
      if ( $i==1 ) {
        $y->host= $host_pol;
      }
      ds_platba_hosta($cenik_roku,$host_pol->cena,$fields,$i);
      foreach ($fields as $field=>$value) {
        $y->fields->$field+= $value;
      }
    }
  }
end:  
  return $y;
}
# -------------------------------------------------------------------------------==> ds platba_hosta
# výpočet ceny za položky hosta jako ubyt,strav,popl,prog,celk
function ds_platba_hosta ($cenik_roku,$polozky,$platba,$i='',$podrobne=false) {
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
  return $one;
}        
# ------------------------------------------------------------------------------------ ds objednavka
# vrátí ID objednávky pokud existuje k této akce
function ds_objednavka($ida) {
  global $answer_db;
  $order= 0;
  list($rok,$kod)= select('g_rok,g_kod','join_akce',"id_akce=$ida",$answer_db);
  if ( $kod ) {
    $order= select('uid','tx_gnalberice_order',
        "akce=$kod AND YEAR(FROM_UNIXTIME(fromday))=$rok",'setkani');
    $order= $order ? $order : 0;
  }
  return $order;
}
# ------------------------------------------------------------------------------------- ds import_ys
# naplní seznam účastníky dané akce
function ds_import_ys($order,$clear=0) {
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
# ------------------------------------------------------------------------------------ ds rooms_help
# vrátí popis pokojů
function ds_rooms_help($version=1) {
  $hlp= array();
  ezer_connect('setkani');
  $qry= "SELECT number,1-hidden AS enable,note
         FROM tx_gnalberice_room
         WHERE NOT deleted AND version=$version";
  $res= pdo_qry($qry);
  while ( $res && $o= pdo_fetch_object($res) ) {
    $hlp[]= (object)array('fld'=>"q$o->number",'hlp'=>wu($o->note),'on'=>$o->enable);
  }
                                                         debug($hlp);
  return $hlp;
}
//# -------------------------------------------------------------------------------------- ds cen_menu
//# vygeneruje menu pro loňský, letošní a příští rok ve tvaru objektu pro menu.group
//# ve tvaru item {title:'2016',par:°{rok:'2016'} }
//function ds_cen_menu($tit='Ceny roku') {
//
//
//  $gr= (object)array(
//    'type'=>'menu.group',
//    'options'=>(object)array('title'=>$tit,'part'=>(object)array())
//  );
//
//    $rok= 2018;
//    $par= (object)array('rok'=>$rok);
//    $tm= (object)array('type'=>'item','options'=>(object)array('title'=>$rok,'par'=>$par));
//    $gr->part->$iid= $tm;
//
//
//
//  $result= (object)array('th'=>$the,'cd'=>$mn);
//  return $result;
//}
# -------------------------------------------------------------------------------------- ds obj_menu
# vygeneruje menu pro loňský, letošní a příští rok ve tvaru objektu pro ezer2 pro zobrazení objednávek
# určující je datum zahájení pobytu v objednávce
# $ym_list = yyyymm,yyyymm,... pro omezení levého menu pro ladění
function ds_obj_menu($ym_list=null) {
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
        $tit= wu("$iid - ").$zkratka.wu(" - {$o->name}");
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
                                                debug($result,"ds_obj_menu");
  return $result;
}
# ----------------------------------------------------------------------------------------- pin_make
# vytvoř PIN k číslu x
function pin_make ($x) {
  // jediná kolize pro čísla 1..2000
  $str= str_pad($x,6,'0',STR_PAD_LEFT);
  $pin= substr(sprintf("%u",crc32(strrev($str))),-6);
  return $pin;
}
# ----------------------------------------------------------------------------------------- pin_test
# test PIN - doplníme ho nulami zleva
function pin_test ($x,$pin) {
  $str= str_pad($x,6,'0',STR_PAD_LEFT);
  $strpin= str_pad($pin,6,'0',STR_PAD_LEFT);
  $xpin= substr(sprintf("%u",crc32(strrev($str))),-6);
  return $strpin==$xpin?1:0;
}
# ==========================================================================================> rodina
# ------------------------------------------------------------------------------------------ lide_ms
# SELECT autocomplete - výběr z databáze db2:rodina+členi
function lide_ms($patt) {  #trace('','win1250');
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
# ------------------------------------------------------------------------------------------ lide_ms
# SELECT autocomplete - výběr z databází MS (Miloš, Lída)
//function xxxlide_ms($patt) {  #trace('','win1250');
//  $a= array();
//  $limit= 10;
//  $n= 0;
//  // rodiče
//  $qry= "SELECT source,cislo AS _key,concat(jmeno,' ',jmeno_m,' a ',jmeno_z,' - ',mesto) AS _value
//         FROM ms_pary
//         WHERE jmeno LIKE '$patt%' ORDER BY jmeno LIMIT $limit";
//  $res= mysql_qry($qry);
//  while ( $res && $t= mysql_fetch_object($res) ) {
//    if ( ++$n==$limit ) break;
//    $key= "{$t->_key}".($t->source=='L'?0:1);
//    $a[$key]= "{$t->source}:{$t->_value}";
//  }
//  // obecné položky
//  if ( !$n )
//    $a[0]= /*w*u*/("... žádné jméno nezačíná '")."$patt'";
//  elseif ( $n==$limit )
//    $a[-999999]= /*w*u*/("... a další");
////                                                                 debug($a,$patt,(object)array('win1250'=>1));
//  return $a;
//}
# ------------------------------------------------------------------------------------------- rodina
# formátování autocomplete - verze pro db2
function rodina($idr) {  #trace('','win1250');
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
function rodcis($nar,$sex) {
  $m= substr($nar,5,2);
  $m= str_pad($m + ($sex==2 ? 50 : 0), 2, '0', STR_PAD_LEFT);
  $rc= substr($nar,2,2).$m.substr($nar,8,2);
  return $rc;
}
function roku($rc) {
  $r= rc2roky($rc);
  $roku= !$r          ? "?" : (
         $r==1        ? "rok" : (
         ($r % 10)==1 ? "let" : (
         $r<=4        ? "roky" : "roků" )));
  return /*w*u*/("$r $roku, rč:$rc");
}
# ------------------------------------------------------------------------------------------ lide_ds
# SELECT autocomplete - výběr z databáze DS
function lide_ds($patt0) {  #trace('','win1250');
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
function klienti($id_osoba) {  #trace('','win1250');
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
                                              debug($rod,$id_osoba,(object)array('win1250'=>1));
  return $rod;
}
# ============================================================================================> ceny
# ------------------------------------------------------------------------------------- ds xls_hoste
# kopie ceníku
# par:
#   .op='kopie'
#   .z=starý rok
#   .na=nový rok
function ds_ceny_uprava($par) { trace('','win1250');
  $html= "";
  if ( $par->op=='kopie' ) {
    ezer_connect('setkani');
    // určení roků
    $z= date('Y')+$par->z;
    $na= date('Y')+$par->na;
    // kontrola prázdnosti nového ceníku
    $qry= "SELECT count(*) as pocet FROM ds_cena  WHERE rok=$na ";
    $res= pdo_qry($qry);
    if ( $res && $c= pdo_fetch_object($res) ) { debug($c);
      if ( $c->pocet>0 )
        $html= "<span style='color:red'>Cenik pro rok $na byl jiz zrejme vygenerovan. Operace prerusena.</span>";
    }
    if ( !$html ) {
      // kopie ceníku
      $qry= "SELECT * FROM ds_cena WHERE rok=$z ORDER BY typ";
      $res= pdo_qry($qry);
      $ok= 1; $n= 0;
      while ( $res && $c= pdo_fetch_object($res) ) {
        $ins= "INSERT INTO ds_cena (rok,polozka,druh,typ,od,do,cena,dph)
               VALUES ($na,'{$c->polozka}','{$c->druh}','{$c->typ}',{$c->od},{$c->do},{$c->cena},{$c->dph})";
        display(/*w*u*/($ins));
        $n++;
        $ires= pdo_qry($ins);
        $ok&= pdo_affected_rows($ires)==1 ? 1 : 0;
      }
      $html.= $ok&&$n ? "Zkopirovano" : "Kopie ceniku se nezdarila. Kontaktuj Martina Smidka";
    }
  }
  return $html;
}
# =========================================================================================> exporty
# ------------------------------------------------------------------------------------- ds xls_hoste
# definice Excelovského listu - seznam hostů
function ds_xls_hoste($orders,$mesic_rok) {  #trace('','win1250');
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
# ----------------------------------------------------------------------------------------- ds hoste
# vytvoří seznam hostů
# ceník beer podle předaného roku
# {table:id,obdobi:str,hoste:[[jmeno,prijmeni,adresa,narozeni,telefon,email,termin,poplatek]...]}
function ds_hoste($orders,$rok) {  #trace('','win1250');
  global $ds_cena, $ezer_path_serv;
  require_once "$ezer_path_serv/licensed/xls2/Classes/PHPExcel/Calculation/Functions.php";
  ds_cenik($rok);
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
# ================================================================================> zálohová faktura
# ASK
# ------------------------------------------------------------------------------------ ds xls_zaloha
# definice Excelovského listu - zálohové faktury
function ds_xls_zaloha($order) {  trace();//'','win1250');
  global $ezer_path_serv, $ezer_version;
  require_once "ezer$ezer_version/server/vendor/autoload.php";
  $html= " nastala chyba";
  $name= "zal_$order";
  // vytvoření sešitu s fakturou
  $xls= "|open  $name|";
  $x= ds_zaloha($order);
  if ( $x->err ) { $html= $x->err; goto end; }
//                                                         debug($x,"ds_zaloha");
  if ( !count((array)$x) ) goto end;
  $xls.= ds_faktura('zalohova_faktura','ZÁLOHOVÁ FAKTURA',$order,$x->polozky,$x->platce,50,
    "Těšíme se na Váš pobyt v Domě setkání");
  $xls.= "|close|";
  $test= 1;
  if ( $test )
    file_put_contents("xls.txt",$xls);
  require_once "ezer$ezer_version/server/vendor/autoload.php";
  $inf= Excel2007(/*w*u*/($xls),1);
  if ( $inf ) {
    $html= " nastala chyba";
    fce_error(/*w*u*/($inf));
  }
  else
    $html= " <a href='docs/$name.xlsx' target='xls'>zálohová faktura</a>.";
end:
  return $html;
}
# ---------------------------------------------------------------------------------------- ds zaloha
# data zálohové faktury
# {objednavka:n,
#  platce:[nazev,adresa,telefon,ic]
#  polozky:[[nazev,cena,dph,pocet]...]
# }
function ds_zaloha($order) {  trace();// '','win1250');
  global $ds_cena;
  $x= (object)array();
  // zjištění údajů objednávky
  ezer_connect('setkani');
  $qry= "SELECT * FROM tx_gnalberice_order WHERE uid=$order";
  $res= pdo_qry($qry);
  if ( $res && $o= pdo_fetch_object($res) ) {
    $o->rooms= $o->rooms1;
    foreach ((array)$o as $on) { if ( strstr($on,'|')!==false ) { // test na |
      fce_warning(/*w*u*/("nepřípustný znak '|' v '$on'"));
      goto end;
    }}
    $obdobi= date('j.n',$o->fromday).' - '.date('j.n.Y',$o->untilday);
    $dnu= ($o->untilday-$o->fromday)/(60*60*24);
//                                                         display("pocet dnu=$dnu");
    // přečtení ceníku daného roku
    $rok= date('Y',$o->untilday);
    ds_cenik($rok);
    if ( !count($ds_cena) ) { $x->err= "není ceník pro $rok"; goto end; }
    // údaje o plátci: $ic,$dic,$adresa,$akce
    $platce= array();
    $platce[]= $o->ic ? $o->ic : '';
    $platce[]= $o->dic ? $o->dic : '';
    $platce[]= wu(($o->org ? "{$o->org}{}" : '')."{$o->firstname} {$o->name}{}".
              "{$o->address}{}{$o->zip} {$o->city}");
    $platce[]= $o->akce;
    $platce[]= $obdobi;
    $x->platce= $platce;
    // položky zálohové faktury
    // ubytování může mít slevu
    $polozky= array();
    $sleva= $o->sleva ? $o->sleva/100 : '';
    $x->polozky[]= ds_c('noc_L',$dnu*($o->adults + $o->kids_10_15 + $o->kids_3_9),$sleva);
    $x->polozky[]= ds_c('noc_A',0,$sleva,1);
    $x->polozky[]= ds_c('noc_B',0,$sleva,1);
    $x->polozky[]= ds_c('noc_P',0,$sleva,1);
    $x->polozky[]= ds_c('noc_S',0,$sleva,1);
    $x->polozky[]= ds_c('noc_Z',0,$sleva,1);
    $x->polozky[]= ds_c('ubyt_C',$dnu*($o->adults));
    $x->polozky[]= ds_c('ubyt_S',$dnu*($o->adults));
    $x->polozky[]= ds_c('ubyt_P',$dnu*($o->kids_10_15 + $o->kids_3_9 + $o->kids_3));
    $x->polozky[]= ds_c('noc_B',$dnu*$o->kids_3,$sleva);
    switch ( $o->board ) {
    case 1:     // penze
      $x->polozky[]= ds_c('strava_CC',$dnu*($o->adults+$o->kids_10_15));
      $x->polozky[]= ds_c('strava_CD',$dnu*$o->kids_3_9);
      break;
    case 2:     // polopenze
      $x->polozky[]= ds_c('strava_PC',$dnu*($o->adults+$o->kids_10_15));
      $x->polozky[]= ds_c('strava_PD',$dnu*$o->kids_3_9);
      break;
    }
//                                              debug($x,'zaloha',(object)array('win1250'=>1));
  }
end:
  return $x;
}
# =================================================================================> konečná faktura
# ----------------------------------------------------------------------------------- ds xls_faktury
# ASK
# definice Excelovského listu - faktury podle seznamu účastníků
# položka,počet osob,počet nocí,počet nocolůžek,cena jednotková,celkem,DPH 9%,DPH 19%,základ
# *ubytování
function ds_xls_faktury($order) {  trace(); //'','win1250');
  global $ds_cena, $ezer_version;
  require_once "ezer$ezer_version/server/vendor/autoload.php";
  $html= " nastala chyba";
  $test= 1;
  $x= ds_faktury($order);
  if ( $x->err ) { $html= $x->err; goto end; }
  if ( !count((array)$x->rodiny) ) goto end;
  $ds_cena['zzz_zzz']= 0;    // přidání prázdného řádku
  ksort($ds_cena);

//                                      debug($ds_cena,'ds_cena',(object)array('win1250'=>1));
//                                      debug($x,'faktura',(object)array('win1250'=>1));
  // barvy
  $c_edit= "ffffffaa";
  // úvodní list
  $xls= '';
  $nf= 3;
  $faktury= "";
  foreach ($x->rodiny as $r=>$hoste) {
    $nf++;
    $rod= $x->faktury[$r];
    list($rodina,$pocet,$sleva)= $rod;
    $prefix= count($x->rodiny)==1 ? '' : ($rodina=='' ? 'ostatni-' : "$rodina-");
    $sheet_rodina=  "{$prefix}hoste";
    $sheet_rozpis= "{$prefix}rozpis";
    $sheet_faktura= "{$prefix}faktura";
    $faktury.= "|A$nf:F$nf border=,,h,|A$nf $rodina|B$nf $pocet|F$nf =D$nf-E$nf ::kc";
    $faktury.= "|C$nf $sleva::proc bcolor=$c_edit|E$nf 0 ::kc bcolor=$c_edit|D$nf =";
    $An_sleva= "'rodiny'!C$nf";
    $An_zaloha= "'rodiny'!E$nf";
    # ------------------------------------------------------------- členové rodiny a položky faktury
    $i= 0;
    $clmn= "A=10,B=13,C=-30,D=-15,E=4,F=-20,G=-30,H=20,I=5,J=6";
    $tit= "|A3 jméno |B3 příjmení |C3 adresa |D3 narozen/a ::right |E3 věk |F3 telefon "
         ."|G3 email |H3 pobyt od-do ::right |I3 noci ::right| J3 pokoj ::right|";
    $lc= ord('J')-ord('A');
    $n= 3;
    $druh0= '';
    $soucty= ''; $ns= $n+count($hoste)+1; $ns1= $ns-1;
    foreach($ds_cena as $dc=>$cena) {
      list($druh,$cast)= explode('_',$dc);
//                                                                 display("$lc,$i,$druh,$druh0,$cast");
      if ( $druh!=$druh0 ) {
        if ( $i ) {
          if ( $druh0=='noc' ) {
            $lc++;
            $A= Excel5_n2col($lc);
            $clmn.= ",$A=6";
            $tit.= "|$A$n sleva ::vert";
          }
          $lc++;
          $B= Excel5_n2col($lc);
          $clmn.= ",$B=12";
          $tit.= "|$B$n cena ::right";
        }
        $druh0= $druh;
      }
      if ( $cena ) {
        $lc++;
        $A= Excel5_n2col($lc);
        $clmn.= ",$A=4";
        $tit.= "|$A$n {$cena->polozka} ::vert";
        $soucty.= "|$A$ns =SUM({$A}4:$A$ns1)";
        $cena->pocet= "='$sheet_rodina'!$A$ns";
      }
      else {
        $lc++;
        $B= Excel5_n2col($lc);
        $clmn.= ",$B=12";
        $tit.= "|$B$n celkem ::right bold";
        $soucty.= "|$B$ns =SUM({$B}4:$B$ns1) ::kc bold";
        $faktury.= "'$sheet_rodina'!$B$ns ::kc";
      }
      $i++;
      // součty
    }
    $tit.= "|A$n:$B$n bcolor=ffaaaaaa |";
    $xls.= "\n\n|sheet $sheet_rodina;;L;page|columns $clmn |$tit |";
    $skupiny= count($x->rodiny)==1 ? '' : ($rodina=='' ? "neoznačených hostů" : "hostů označených '$rodina'");
    $xls.= "|A1 Konečné vyúčtování $skupiny v rámci objednávky $order ::bold size=14|";
    $n= 4;
    foreach ($hoste as $host) {
      list($rodina,$jmeno,$prijmeni,$ulice,$psc_mesto,$narozeni,$vek,$telefon,$email,$od,$do)= $host->host;
      $termin= "$od - $do";
      $c= $host->cena;
      $xls.= <<<__XLS
        |A$n $jmeno |B$n $prijmeni |C$n $ulice, $psc_mesto |D$n $narozeni ::right date |E$n $vek
        |F$n $telefon |G$n $email |H$n $termin ::right
        |I$n {$c->noci}
        |J$n {$c->pokoj}
__XLS;
      // položky
      $lc= ord('J')-ord('A');
      $row= '';
      $i= 0;
      $druh0= '';
      $suma= '0';
      $celkem= '=0';
      foreach($ds_cena as $dc=>$cena) {
        list($druh,$cast)= explode('_',$dc);
        if ( $druh!=$druh0 ) {
          if ( $i ) {
            if ( $druh0=='noc' ) {
              $lc++;
              $A= Excel5_n2col($lc);
              $row.= "|$A$n =$An_sleva::proc";
            }
            $lc++;
            $B= Excel5_n2col($lc);
            if ( $druh0=='noc' ) {
              $row.= "|$B$n =(1-$A$n)*($suma) ::kc\n";
            }
            else {
              $row.= "|$B$n =$suma ::kc\n";
            }
            $suma= '0';
            $celkem.= "+$B$n";
          }
          $druh0= $druh;
        }
        if ( $cena ) {
          $lc++;
          $A= Excel5_n2col($lc);
          $val= isset($c->$dc) && $c->$dc ? $c->$dc : '0';
          $suma.= "+$A$n*{$cena->cena}";
          $row.= "|$A$n $val::bcolor=$c_edit";
        }
        else {
          $lc++;
          $B= Excel5_n2col($lc);
          $row.= "|$B$n $celkem ::kc bold\n";
        }
        $i++;
      }
//                                                                 debug($host,'host',(object)array('win1250'=>1));
      $xls.= "$row|A$n:$B$n border=,,h,|$soucty|\n";
      $n++;
    }
    $xls.= "|$soucty|\n";
    # ---------------------------------------------------------------- faktura
    # platce= [nazev,adresa,telefon,ic]
    # polozky= [[nazev,cena,dph,pocet]...]
    if ( $rodina=='' ) {
      list($rodina,$jmeno,$prijmeni,$ulice,$psc_mesto)= $hoste[0]->host;
      $platce= array();
      $platce[]= '';
      $platce[]= '';
      $platce[]= "$jmeno $prijmeni{}$ulice{}$psc_mesto";
    }
    else {
      $platce= $x->platce;
    }
    // položky faktury
    $polozky= array();
    foreach($ds_cena as $dc=>$cena) {
      if ( $cena ) {    // bez prázdného řádku
        list($druh,$cast)= explode('_',$dc);
        $sleva= $druh=='noc' ? "=$An_sleva" : '';
        $zaloha= "=$An_zaloha";
        $polozky[]= ds_c($dc,$cena->pocet,$sleva,$zaloha);        // id,pocet => název,cena,dph%,pocet,druh
      }
    }
    // vytvoření listu
    $xls.= ds_rozpis_faktura($sheet_rozpis,$sheet_faktura,'FAKTURA',$order,$x,$polozky,$platce,$zaloha,
      "Těšíme se na Váš další pobyt v Domě setkání",$zaloha,$suma);
    $faktury.= "";
  }
  // ------------------------------------------------------------------ ceník
//                                      debug($ds_cena,'ds_cena',(object)array('win1250'=>1));
  $xls.= <<<__XLS
  \n\n|sheet cenik;;P;page
    |columns A=35,B=20,C=20
    |A1 Seznam účtovatelných položek ::bold size=14
    |A3 položka |B3 cena vč.DPH ::right |C3 DPH ::right proc
    |A3:C3 bcolor=ffaaaaaa
__XLS;
  $n= 4;
  foreach ($ds_cena as $i=>$cena) {
    if ( $cena ) {    // bez prázdného řádku
      $dph= $cena->dph/100;
      $xls.= <<<__XLS
        |A$n {$cena->polozka} |B$n {$cena->cena} :: kc |C$n $dph :: proc
        |A$n:C$n border=,,h,
__XLS;
      $n++;
    }
  }
  # ------------------------------------------------------------------ seznam rodin (jako první)
  $nf1= $nf+1;
  $name= "fak_$order";
  $final_xls= <<<__XLS
  \n|open $name
  \n\n|sheet rodiny;;L;page
    |columns A=20,B=13,C=10,D:F=16
    |A1 Seznam rodin (skupin), kterým fakturujeme pobyt v rámci objednávky $order ::bold size=14
    |A3 rodina (skupina)|B3 osob ::right |C3 sleva ::right |D3 celková cena ::right
    |E3 záloha ::right|F3 doplatit ::right
    |A3:F3 bcolor=ffaaaaaa
    |$faktury
    |A$nf1 CELKEM ::right bold|B$nf1 =SUM(B4:B$nf) ::bold|D$nf1 =SUM(D4:D$nf) ::kc bold
    |E$nf1 =SUM(E4:E$nf) ::kc bold|F$nf1 =SUM(F4:F$nf) ::kc bold
__XLS;
  // vysvětlivky
  $n= $nf1+3;
  $final_xls.= "|A$n Upozornění: v tomto sešitu je možné upravovat pouze žlutě podložená políčka, ".
    "změny jiných políček pravděpodobně poškodí výpočet cen a DPH. Pokud je třeba měnit údaje, ".
    "které nejsou žlutě podloženy, je potřeba to udělat v systému Ans(w)er a znovy vygenerovat tento ".
    "sešit.";
  $final_xls.= "|A$n:F$n merge italic top wrap
    |rows $n=60
    |$xls|close 1";
//                                                                 display("rodiny=$faktury");
//                                                                 display(nl2br(/*w*u*/($xls)));
  $test= 1;
  if ( $test )
    file_put_contents("xls.txt",$final_xls);
  time_mark('ds_xls_faktury Excel5');
//  display($final_xls);
  require_once "ezer$ezer_version/server/vendor/autoload.php";
  $inf= Excel2007($final_xls,1);
  if ( $inf ) {
    $html= " nastala chyba";
    fce_error(/*w*u*/($inf));
  }
  else
    $html= " <a href='docs/$name.xlsx' target='xls'>konečná faktura</a>.";
  // případný testovací výpis
  time_mark('ds_xls_faktury end');
end:
  return /*w*u*/($html);
}
# --------------------------------------------------------------------------------------- ds faktury
# podklady ke konečné fakturaci
# {platce:[ic,dic,adresa,akce],faktury:[[rodina,počet,sleva]...],rodiny:[<host>...],<ceník>,<chyby>}
#    <host> :: {host:[jmeno,prijmeni,adresa1,adresa2,narozeni,vek,telefon,email,od,do,<ubyt>],
#               cena:{<ubyt>,<strava>,<spec>,<popl>}
#    <ubyt> :: noci:n,pokoj:1..29,pokoj_typ:P|S|B,luzko_typ:L|P|B
#  <strava> :: CC:n,CP:n,PC:n,PP:n
#    <spec> :: postylka:n,zvire_noci:n
#    <popl> :: popl:CS|P
#   <ceník> :: cenik:... viz global $ds_cena
#   <chyby> :: chyby:[[text,...]...]
#    pokoje :: A:1-2, S:14-17, L:11-13+21-29
#
function ds_faktury($order) {  trace('','win1250');
  global $ds_cena;
  $x= (object)array('faktury'=>array(),'rodiny'=>array());
  // číselníky
  $ds_luzko=  map_cis('ds_luzko','zkratka');  $ds_luzko[0]=  '?';
  $ds_strava= map_cis('ds_strava','zkratka'); $ds_strava[0]= '?';
  ezer_connect('setkani');
  // přepočet kategorie pokoje na typ ubytování v ceníku    
  $luzko_pokoje= ds_cat_typ();
//                                              debug($luzko_pokoje);
//                                              debug($ds_strava,(object)array('win1250'=>1));
  // kontrola objednávky
  $qry= "SELECT * FROM tx_gnalberice_order WHERE uid=$order";
  $res= pdo_qry($qry);
  if ( $res && $o= pdo_fetch_object($res) ) {
    $o->rooms= $o->rooms1;
    foreach ((array)$o as $on) { if ( strstr($on,'|')!==false ) { // test na |
      fce_warning(/*w*u*/("nepřípustný znak '|' v '$on'"));
      goto end;
    }}
    $obdobi= date('j.n',$o->fromday).' - '.date('j.n.Y',$o->untilday);
//    $skoleni= $o->skoleni;
    // údaje o plátci: $ic,$dic,$adresa
    $platce= array();
    $platce[]= $o->ic ? $o->ic : '';
    $platce[]= $o->dic ? $o->dic : '';
    $platce[]= wu(($o->org ? "{$o->org}{}" : '')."{$o->firstname} {$o->name}{}".
              "{$o->address}{}{$o->zip} {$o->city}");
    $platce[]= $o->akce;
    $platce[]= $obdobi;
    $x->objednavka= array($obdobi);
    $x->platce= $platce;
    // přečtení ceníku daného roku
    $rok= date('Y',$o->untilday);
    ds_cenik($rok);
    if ( !count($ds_cena) ) { $x->err= "není ceník pro $rok"; goto end; }
    // úprava ceny programu na této akci
    $ds_cena['prog_C']->cena= $o->prog_cely;
    $ds_cena['prog_P']->cena= $o->prog_polo;
    // zjištění počtu faktur za akci
    $qry= "SELECT rodina,count(*) as pocet FROM ds_osoba
           WHERE id_order=$order GROUP BY rodina ORDER BY if(rodina='','zzzzzz',rodina)";
    $res= pdo_qry($qry);
    while ( $res && $r= pdo_fetch_object($res) ) {
      // seznam faktur
      $rid= $r->rodina ? $r->rodina : 'ostatni';
      $x->faktury[]= array($rid,$r->pocet,$o->sleva/100);
      // členové jedné rodiny s údaji
      $hoste= array();
      $err= array();
      $qry= "SELECT * FROM ds_osoba
             WHERE id_order=$order AND rodina='{$r->rodina}' ORDER BY narozeni DESC";
      $reso= pdo_qry($qry);
      while ( $reso && $h= pdo_fetch_object($reso) ) {
        foreach ((array)$h as $on) { if ( strstr($on,'|')!==false ) { // test na |
          fce_warning(/*w*u*/("nepřípustný znak '|' v '$on'"));
          goto end;
        }}
        $host_pol= ds_polozky_hosta ($o,$h,$luzko_pokoje,$ds_luzko,$ds_strava);
        // konec
        $hoste[]= $host_pol; //(object)array('host'=>$host,'cena'=>$pol);
      }
      $x->rodiny[]= $hoste;
      $x->chyby[]= $err;
    }
  }
  else
    fce_error(/*w*u*/("neúplná objednávka $order"));
//                                      debug($ds_cena,'ds_cena',(object)array('win1250'=>1));
//                                      debug($x,'faktura',(object)array('win1250'=>1));
end:
  return $x;
}
# ------------------------------------------------------------------------------==> ds polozky_hosta
# výpočet položek hosta
function ds_polozky_hosta ($o,$h,$luzko_pokoje,$ds_luzko,$ds_strava) {
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
# --------------------------------------------------------------------------------------- ds cat_typ
# přepočet kategorie pokoje na typ ubytování v ceníku    
function ds_cat_typ() {
  $cat_typ= array('C'=>'A','B'=>'L','A'=>'S');
  $luzko_pokoje[0]= 0;
  $rr= pdo_qry("SELECT number,category FROM tx_gnalberice_room WHERE version=1");
  while ( $rr && list($pokoj,$typ)= pdo_fetch_row($rr) ) {
    $luzko_pokoje[$pokoj]= $cat_typ[$typ];
  }
  return $luzko_pokoje;  
}
# ==================================================================================> faktura obecně
# -------------------------------------------------------------------------------- ds rozpis_faktura
# definice faktury
# typ = Zálohová | ''
# ??? zaloha = 0..100  -- pokud je 100 negeneruje se řádek Záloha ... -- 8/4/2019 změněno na 0
# data zálohové faktury
# platce= [nazev,adresa,telefon,ic]
# polozky= [[nazev,cena,dph,pocet,sleva]...]
# }
function ds_rozpis_faktura($listr,$listf,$typ,$order,$x,$polozky,$platce,$zaloha,$pata,$zaloha2,&$suma) {
                                                //trace('','win1250');
  list($ic,$dic,$adresa,$akce,$obdobi)= $platce;
//                                              debug($platce,'platce',(object)array('win1250'=>1));
  $vystaveno= Excel5_date(time());
  //list($obdobi)= $x->objednavka;
  $ymca_setkani= "YMCA Setkání, spolek{}Talichova 53, 62300 Brno{}".
                 "zaregistrovaný Krajským soudem v Brně{}spisová značka: L 8556{}".
                 "IČ: 26531135  DIČ: CZ26531135";
  $dum_setkani=  "Dolní Albeřice 1, 542 26 Horní Maršov{}".
                 "telefon: 499 874 152, 736 537 122{}dum@setkani.org{}www.alberice.setkani.org";
  // ------------------------------------------------------------------- vytvoření listu s rozpisem
  // pojmenované řádky (P,Q,R,S)
  $P= 10;               // výčet položek
  $Q= 25;               // poslední položka
  $D= 28;               // rozpis podle druhů
  $S= 34;               // poslední řádek
  // parametrizace
  $c_okraj= "ff6495ed";    $S1= $S+1;
  $xls= <<<__XLS
  \n\n|sheet $listr;B2:N$S;P;page
    |columns A=3,B=0.6,C=16,D=3,E=22,F=6,G=16,H=10,I=4,J=6,K=6,L=16,M=0,N=1,O=3
    |rows 1=18,2:44=15,5=30,7=45,9=30,10:32=20,$S=30
    |A1:O$S1 bcolor=$c_okraj |B2:N$S bcolor=ffffffff |//B2:N$S border=h

    |image img/YMCA.png,80,C2,10,0
    |D2 Dům setkání :: bold size=14
    |D3 $dum_setkani
    |D3:H5 merge italic top wrap
    |B7 Rozpis ceny za pobyt v Domě setkání ve dnech $obdobi ::bold size=16|B7:L7 merge
__XLS;
  $n= $P-1;
  $xls.= <<<__XLS
    |C$n Položka              ::wrap middle       |C$n:E$n merge bold border=h
    |F$n Počet                ::wrap middle right |F$n:F$n       bold border=h
    |G$n Druh                 ::wrap middle right |G$n:G$n       bold border=h
    |H$n Cena položky s DPH   ::wrap middle right |H$n:I$n merge bold border=h
    |J$n Sleva %              ::wrap middle right |J$n:J$n       bold border=h
    |K$n Sazba DPH            ::wrap middle right |K$n:K$n       bold border=h
    |L$n Cena s DPH           ::wrap middle right |L$n:L$n       bold border=h
__XLS;
  // řádky $P-$Q -- položky
  $n= $P;
  $sazby_dph= array();
  foreach ($polozky as $i=>$polozka) {
    list($nazev,$cena,$dph,$pocet,$druh,$sleva,$inuly)= $polozka;
    if (!in_array($dph,$sazby_dph) ) $sazby_dph[]= $dph;
    if (!isset($druhy[$druh]) ) $druhy[$druh]= $dph;
    $sleva= $sleva ?: 0;
    if ( $pocet || $inuly ) {
      $xls.= <<<__XLS
        |C$n $nazev                |C$n:E$n merge
        |F$n $pocet
        |G$n $druh
        |H$n $cena         ::kc    |H$n:I$n merge
        |J$n $sleva        ::proc
        |K$n $dph          ::proc
        |L$n =F$n*H$n*(1-J$n) ::kc
__XLS;
      $n++;
    }
  }
  $xls.= <<<__XLS
    |C$P:E$Q border=h    |F$P:F$Q border=h    |G$P:G$Q border=h    |H$P:I$Q border=h
    |J$P:J$Q border=h    |K$P:K$Q border=h    |L$P:L$Q border=h
__XLS;
  // řádky D... -- rozpis podle druhů
  $n= $D;
  if ( count($druhy) )
  foreach($druhy as $druh=>$dph) {
    $xls.= <<<__XLS
      |H$n $druh::right                |H$n:J$n merge right
      |K$n $dph                        ::proc border=h right
      |L$n =SUMIF(G$P:G$Q,H$n,L$P:L$Q) ::kc border=h right
__XLS;
    $n++;
  }
  // ------------------------------------------------------------------- vytvoření listu s fakturou
  // pojmenované řádky (P,Q,R,S)
  $P= 22;               // výčet položek
  $Q= 34;               // poslední položka
  $R= 31;               // vyřizuje
  $S= 37;               // poslední řádek
  // parametrizace
  $L7_ic=  $ic  ? "L7 IČ $ic"   : '';
  $M7_dic= $dic ? "M7 DIČ $dic" : '';
  $c_okraj= "ff6495ed";
  $S1= $S+1;
  $xls.= <<<__XLS
  \n\n|sheet $listf;B2:N$S;P;page
    |columns A=3,B=0.6,C=16,D=3,E=22,F=6,G=1,H=10,I=4,J=6,K=6,L:M=16,N=1,O=3
    |rows 1=18,2:44=15,5=30,6=45,9=96,11=35,19=30,20:36=19,21=30,37=30,38:41=19,$S=30
    |A1:O$S1 bcolor=$c_okraj |B2:N$S bcolor=ffffffff |//B2:N$S border=h

    |image img/YMCA.png,80,C2,10,0
    |D2 Dům setkání :: bold size=14
    |D3 $dum_setkani
    |D3:H5 merge italic top wrap

    |J4 =CONCATENATE("$typ ",TEXT(E16,"0"),"/",TEXT(3000+YEAR(M15),"0")) :: bold size=16
    |J4:M5 merge right

    |C7 Dodavatel ::bold
    |C9 $ymca_setkani
    |C9:F9 merge top wrap

    |I7 Odběratel ::bold         |$L7_ic        |$M7_dic
    |I8:M10 border=h
    |J9 $adresa  |J9:M9 merge middle wrap size=14

    |C13 Peněžní ústav           |E13 Fio banka, a.s.
    |C14 Číslo účtu       ::bold |E14 2000465448/2010 ::bold
    |C15 Konstantní symbol       |E15 558 ::left
    |C16 Variabilní symbol       |E16 <číslo faktury> ::left bcolor=ffffffaa
    |C17 Specifický symbol       |E17 $akce ::left bcolor=ffffffaa

    |J12 Objednávka číslo ::bold |M12 $order  ::size=14 bold
    |J13 Dodací a platební podmínky: s daní
    |J14 Datum vystavení           |M14 $vystaveno ::date bcolor=ffffffaa
    |J15 Datum zdanitelného plnění |M15 =M14       ::date bcolor=ffffffaa
    |J16 Datum splatnosti ::bold   |M16 =M14+14    ::date bcolor=ffffffaa bold
    |J18 Způsob platby             |M18 převod/hotovost ::bcolor=ffffffaa

    |C19 Za pobyt v Domě setkání ve dnech $obdobi Vám fakturujeme: |C19:M19 merge
__XLS;
  $n= $P-1;
  $xls.= <<<__XLS
    |C$n Položka              ::wrap middle       |C$n:G$n merge bold border=h
    |H$n Cena s DPH           ::wrap middle right |H$n:J$n merge bold border=h
    |K$n Sazba DPH            ::wrap middle right |K$n:K$n       bold border=h
    |L$n DPH                  ::wrap middle right |L$n:L$n       bold border=h
    |M$n Cena bez DPH         ::wrap middle right |M$n:M$n       bold border=h
__XLS;
  // řádky $P-$Q -- položky
  $n= $P;
  $d= $D;
  for ($i= 0; $i<count($druhy); $i++ ) {
//  oprava DPH starý výpočet:   |L$n =H$n*'$listr'!O$d ::kc
    $xls.= <<<__XLS
      |C$n ='$listr'!H$d          |C$n:G$n merge
      |H$n ='$listr'!L$d     ::kc |H$n:J$n merge
      |K$n ='$listr'!K$d     ::proc
      |L$n =H$n-H$n/(1+K$n)  ::kc
      |M$n =H$n-L$n          ::kc
__XLS;
//       |M$n =H$n/(1+K$n)    ::kc
    $n++; $d++;
  }
  $n--;
  $xls.= <<<__XLS
    |C$P:G$n border=h    |H$P:J$n border=h
    |K$P:K$n border=h    |L$P:L$n border=h    |M$P:M$n border=h
__XLS;
  // celková cena
  $d= $n;
  $n+= 2;
  $suma= "'$listf'!L$n";
  $xls.= <<<__XLS
    |H$n Celková cena s DPH ::middle bold |H$n:K$n merge right
    |L$n =SUM(L$P:M$d)      ::kc          |L$n:M$n merge border=h
__XLS;
  $c= $n;
  $n++;
  $xls.= <<<__XLS
    |H$n Zaplaceno zálohou  ::middle bold |H$n:K$n merge right
    |L$n $zaloha            ::kc          |L$n:M$n merge border=h
__XLS;
  $z= $n;
  $n++;
  $xls.= <<<__XLS
    |H$n Zbývá k zaplacení  ::middle bold |H$n:K$n merge right
    |L$n =L$c-L$z           ::kc          |L$n:M$n merge border=h
__XLS;
  // řádky R,S (viz výše) -- spodek faktury
  $n= $R+1;
  $xls.= <<<__XLS
    |C$R vyřizuje ::bold
    |C$n Josef Náprstek, správce Domu setkání
    |C$S:M$S border=h,,,
    |C$S $pata | C$S:M$S merge middle center wrap
__XLS;
  return $xls;
}
# --------------------------------------------------------------------------------------- ds faktura
# definice faktury
# typ = Zálohová | ''
# // zaloha = 0..100  -- pokud je 100 negeneruje se řádek Záloha ... -- 8/4/2019 změněno na 0
# data zálohové faktury
# platce= [nazev,adresa,telefon,ic]
# polozky= [[nazev,cena,dph,pocet,sleva]...]
# }
function ds_faktura($list,$typ,$order,$polozky,$platce,$zaloha=0,$pata='') {  trace();//,'win1250');
  list($ic,$dic,$adresa,$akce,$obdobi)= $platce;
  $vystaveno= Excel5_date(time());
  $ymca_setkani= "YMCA Setkání, spolek{}Talichova 53, 62300 Brno{}".
                 "zaregistrovaný Krajským soudem v Brně{}spisová značka: L 8556{}".
                 "IČ: 26531135  DIČ: CZ26531135";
  $dum_setkani=  "Dolní Albeřice 1, 542 26 Horní Maršov{}".
                 "telefon: 736 537 122{}dum@setkani.org{}www.alberice.setkani.org";
  // pojmenované řádky (P,Q,R,S)
  $P= 22;               // výčet položek
  $Q= 36;               // poslední položka
  $R= 39;               // rozpis DPH
  $S= 45;               // poslední řádek
  // parametrizace
  $L7_ic= $ic ? "L7 IČ $ic" : '';
  $M7_dic= $dic ? "M7 DIČ $dic" : '';
  $c_okraj= "ff6495ed";
  // vytvoření listu s fakturou
  $xls= <<<__XLS
    |sheet $list;B2:N$S;P;page
    |columns A=3,B=0.6,C=16,D=3,E=22,F=6,G=1,H=10,I=4,J=6,K=6,L:M=16,N=1,O=3
    |rows 1=18,2:44=15,5=30,6=45,9=96,11=35,19=30,21=30,22:38=19,39=30,40:43=19,$S=30
    |A1:O46 bcolor=$c_okraj |B2:N$S bcolor=ffffffff |//B2:N$S border=h

    |image img/YMCA.png,80,C2,10,0
    |D2 Dům setkání :: bold size=14
    |D3 $dum_setkani
    |D3:H5 merge italic top wrap

    |J4 =CONCATENATE("$typ ",TEXT(3000+YEAR(M15),"0"),"/",TEXT(E16,"0")) :: bold size=16
    |J4:M5 merge right

    |C7 Dodavatel ::bold
    |C9 $ymca_setkani
    |C9:F9 merge top wrap

    |I7 Odběratel ::bold         |$L7_ic  |$M7_dic
    |I8:M10 border=h
    |J9 $adresa  |J9:M9 merge middle wrap size=14

    |C13 Peněžní ústav           |E13 Fio banka, a.s.
    |C14 Číslo účtu       ::bold |E14 2000465448/2010 ::bold
    |C15 Konstantní symbol       |E15 558 ::left
    |C16 Variabilní symbol       |E16 <číslo faktury> ::left bcolor=ffffffaa
    |C17 Specifický symbol       |E17 $akce ::left bcolor=ffffffaa

    |J12 Objednávka číslo ::bold |M12 $order  ::size=14 bold
    |J13 Dodací a platební podmínky: s daní
    |J14 Forma úhrady              |M14 převodem
    |J14 Datum vystavení           |M14 $vystaveno ::date bcolor=ffffffaa
    |J15 Datum zdanitelného plnění |M15 =M14       ::date bcolor=ffffffaa
    |J16 Datum splatnosti ::bold   |M16 =M14+14    ::date bcolor=ffffffaa bold
    |J18 Způsob platby             |M18 převod/hotovost ::bcolor=ffffffaa
    |C19 Fakturujeme vám zálohu na pobyt ve dnech $obdobi: |C19:M19 merge
__XLS;
  $n= $P-1;
  $xls.= <<<__XLS
    |C$n Položka              ::wrap middle       |C$n:E$n merge bold border=h
    |F$n Počet                ::wrap middle right |F$n:G$n merge bold border=h
    |H$n Cena položky vč. DPH ::wrap middle right |H$n:I$n merge bold border=h
    |J$n Sleva %              ::wrap middle right |J$n:J$n       bold border=h
    |K$n Sazba DPH            ::wrap middle right |K$n:K$n       bold border=h
    |L$n DPH                  ::wrap middle right |L$n:L$n       bold border=h
    |M$n Cena bez DPH         ::wrap middle right |M$n:M$n       bold border=h
__XLS;
  // řádky $P-$Q -- položky
  $n= $P;
  $sazby_dph= array();
  foreach ($polozky as $i=>$polozka) {
    list($nazev,$cena,$dph,$pocet,$druh,$sleva,$inuly)= $polozka;
    if (!in_array($dph,$sazby_dph) ) $sazby_dph[]= $dph;
    if ( $pocet || $inuly ) {
      // oprava výpočtu DPH bylo: 
      // |L$n =O$n*$koef    ::kc
      // |M$n =O$n-L$n      ::kc
      // |O$n =F$n*H$n*(1-J$n) ::kc color=$c_okraj
      $xls.= <<<__XLS
        |C$n $nazev                |C$n:E$n merge
        |F$n $pocet                |F$n:G$n merge
        |H$n $cena         ::kc    |H$n:I$n merge
        |J$n $sleva        ::proc
        |K$n $dph          ::proc
        |L$n =F$n*H$n-F$n*H$n/(1+K$n)  ::kc
        |M$n =F$n*H$n-L$n  ::kc
        |O$n ::kc color=$c_okraj
__XLS;
      $n++;
    }
  }
  $xls.= <<<__XLS
    |C$P:E$Q border=h    |F$P:G$Q border=h    |H$P:I$Q border=h
    |J$P:J$Q border=h    |K$P:K$Q border=h    |L$P:L$Q border=h    |M$P:M$Q border=h
__XLS;
  // celková cena
  $n= $Q+1;
  $xls.= <<<__XLS
    |H$n Celková cena s DPH ::middle bold |H$n:K$n merge right
    |L$n =SUM(L$P:M$Q)      ::middle kc   |L$n:M$n merge border=h
__XLS;
//  if ( $zaloha<100 ) {
    $n++;
    $xls.= <<<__XLS
      |H$n Záloha $zaloha%             ::middle bold |H$n:K$n merge right
      |L$n =SUM(L$P:M$Q)*($zaloha/100) ::middle kc   |L$n:M$n merge border=h
__XLS;
//  }
  // řádky R... -- rozpis DPH
  $n= $R+1;
  $xls.= <<<__XLS
    |K$R Rozpis DPH ::bold
    |K$n Sazba::middle bold border=h right
    |L$n Daň     ::middle bold border=h right
    |M$n Základ  ::middle bold border=h right
__XLS;
  sort($sazby_dph);
  foreach($sazby_dph as $sazba) {
    $n++;
    $xls.= <<<__XLS
      |K$n $sazba                                    ::proc border=h right
      |L$n =SUMIF(K$P:K$Q,K$n,L$P:L$Q)*($zaloha/100) ::kc   border=h right
      |M$n =SUMIF(K$P:K$Q,K$n,M$P:M$Q)*($zaloha/100) ::kc   border=h right
__XLS;
  }
  // řádky R,S (viz výše) -- spodek faktury
  $n= $R+1;
  $xls.= <<<__XLS
    |C$R vyřizuje ::bold
    |C$n Josef Náprstek, správce Domu setkání
    |C$S:M$S border=h,,,
    |C$S $pata | C$S:M$S merge middle center wrap
__XLS;
  return $xls;
}
# ----------------------------------------------------------------------------------------- ds cenik
# načtení ceníku pro daný rok
function ds_cenik($rok) {  #trace('','win1250');
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
# --------------------------------------------------------------------------------------------- ds c
# položka faktury
# id,pocet => název,cena,dph%,pocet
# inuly - zapsat do faktury i nuly
function ds_c ($id,$pocet,$sleva='',$inuly=0) { //trace();
  global $ds_cena;
  $c= array($ds_cena[$id]->polozka,$ds_cena[$id]->cena,$ds_cena[$id]->dph/100,
    $pocet,trim($ds_cena[$id]->druh),$sleva,$inuly);
//   if ( $sleva ) $c[]= $sleva;
  return $c;
}
# ------------------------------------------------------------------------------------------- ds vek
# zjištění věku v době zahájení akce
function ds_vek($narozeni,$fromday) {
  if ( $narozeni=='0000-00-00' )
    $vek= -1;
  else {
    $vek= sql2roku($narozeni,date('Y-m-d', $fromday));
//    $vek= $fromday-sql2stamp($narozeni);
//    $vek= round($vek/(60*60*24*365.2425),1);
  }
  return $vek;
}
