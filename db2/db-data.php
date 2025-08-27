<?php

# ----------------------------------------------------------------------------------------- db2 info
# par.cmd=show - vypíše počet záznamů v důležitých tabulkách a zkotroluje $access pokud je $org!=0
# par.cmd=save - uloží anonymizované údaje do ezer_answer.db_osoba
function db2_info($par) {
  global $ezer_root,$ezer_db,$USER;
  $org= isset($par->org) ? $par->org : 0;
  // přehled tabulek
  $tabs= array(
    'akce'   => (object)array('cond'=>"1",'access'=>$org),
    'cenik'  => (object)array('cond'=>"deleted=''",'access'=>0),
    'rodina' => (object)array('cond'=>"1",'access'=>$org),
    'tvori'  => (object)array('cond'=>"1",'access'=>0),
    'osoba'  => (object)array('cond'=>"deleted=''",'access'=>$org),
    'spolu'  => (object)array('cond'=>"1",'access'=>0),
    'pobyt'  => (object)array('cond'=>"1",'access'=>0),
    'dopis'  => (object)array('cond'=>"1",'access'=>$org),
    'mailist'=> (object)array('cond'=>"1",'access'=>$org),
    'mail'   => (object)array('cond'=>"1",'access'=>0),
    '_user'  => (object)array('cond'=>"deleted=''",'access'=>$org)
   );
  $html= '';
  $db= select('DATABASE()','DUAL',1);
  $tdr= "td style='text-align:right'";
  switch ($par->cmd) {
    case 'show':
      // přehled tabulek podle access
      $html= "<h3>Přehled tabulek $db</h3>";
      $html.= $org ? 
          "<p>POZOR: pokud je v druhém sloupci <b style='background:lightpink'>červený údaj</b>, 
            jedná se chybu, kterou je třeba řešit</p>" : '';
      $html.= "<div class='stat'><table class='stat'>";
      $th= $org ? '<th>skryté?</th>' : '';
      $html.= "<tr><th>tabulka</th><th>záznamů</th>$th</tr>";
      foreach ($tabs as $tab=>$desc) {
        $tab_= strtoupper($tab);
        if ($desc->access) {
          list($pocet,$access)= select("COUNT(*),SUM(IF(access&$org,0,1))","$db.$tab",$desc->cond);
          $red= $access ? ";background:lightpink" : '';
          $td= $org ? "<td style='text-align:right$red'>$access</td>" : '';
        }
        else {
          $pocet= select("COUNT(*)","$db.$tab",1);
          $td= $org ? '<td></td>' : '';
        }
        $html.= "<tr><th>$tab_</th><td style='text-align:right'>$pocet</td>$td</tr>";
      }
      $html.= "</table></div>";
      // úplnost osobních a rodinných údajů
      list($pocet,$tlf,$eml,$nar,$geo1,$geo_)= select("
          COUNT(*),SUM(IF(kontakt=1 AND telefon,1,0)),SUM(IF(kontakt=1 AND email!='',1,0)),
          SUM(IF(narozeni!='0000-00-00',1,0)), 
          SUM(IF(IFNULL(stav,0)=1,1,0)), SUM(IF(IFNULL(stav,0)<0,1,0))
        ",'osoba LEFT JOIN osoba_geo USING (id_osoba)',"deleted=''"); 
      list($r_pocet,$r_geo1,$r_geo_)= select("
          COUNT(*),
          SUM(IF(IFNULL(stav,0)=1,1,0)), SUM(IF(IFNULL(stav,0)<0,1,0))
        ",'rodina LEFT JOIN rodina_geo USING (id_rodina)',"deleted=''"); 
      $dtlf= db2_info_dupl('osoba','telefon',"deleted='' AND kontakt=1 AND telefon");
      $deml= db2_info_dupl('osoba','UPPER(TRIM(email))',"deleted='' AND kontakt=1 AND email!=''");
      $xeml= db2_info_dupl('osoba','MD5(UPPER(TRIM(email)))',"deleted='' AND kontakt=1 AND email!=''");
      $html.= "<h3>Úplnost rodinných a osobních údajů</h3>";
      $html.= "<div class='stat'><table class='stat'>";
      $html.= "<tr><th>tabulka</th><th>záznamů</th><th>geolokace</th>
        <th>telefon</th><th>... dupl</th><th>email</th><th>... dupl</th><th>... kód</th></tr>";
      $html.= "<tr><th>RODINA</th><$tdr>$r_pocet</td><$tdr>$r_geo1 / $r_geo_</td>
        <$tdr>-</td><$tdr>-</td><$tdr>-</td><$tdr>-</td><$tdr>-</td></tr>";
      $html.= "<tr><th>OSOBA</th><$tdr>$pocet</td><$tdr>$geo1 / $geo_</td>
        <$tdr>$tlf</td><$tdr>$dtlf</td><$tdr>$eml</td><$tdr>$deml</td><$tdr>$xeml</td></tr>";
      $html.= "</table></div>";
      // záznamy v jiných databázích
      $tit=   $org==8 
          ? array('8'=>'jen ŠM','3,8'=>'také YMCA','4,8'=>'také CPR','3,4,8'=>'YMCA i CPR') : (
              $org==4 
          ? array('4'=>'jen CPR','3,4'=>'také YMCA','4,8'=>'také ŠM','3,4,8'=>'YMCA i ŠM') : (
              $org==3 
          ? array('3'=>'jen YMCA','3,4'=>'také CPR','3,8'=>'také ŠM','3,4,8'=>'CPR i ŠM') : null));
      $itits= array_keys($tit);
      $val= array('dupl'=>0);
      $rdb= pdo_qry("
        SELECT COUNT(*),_join FROM (
          SELECT COUNT(*) AS _pocet,md5_osoba,GROUP_CONCAT(db ORDER BY db) AS _join
          FROM ezer_answer.db_osoba
          GROUP BY md5_osoba 
          ORDER BY _join
        ) AS _x
        WHERE FIND_IN_SET($org,_join)
        GROUP BY _join
        ORDER BY _join");
      while ($rdb && list($n,$jn)=pdo_fetch_row($rdb)) {
        if (isset($tit[$jn]))
          $val[$jn]= $n;
        else 
          $val['dupl']+= $n;
      }
//      debug($itits);
//      debug($val);
      $ths= $tds= '';
      foreach ($tit as $itit=>$t) {
        $n= $val[$itit]?:0;
        $ths.= "<th>$t</th>";
        $tds.= "<$tdr>$n</td>";
      }
      $ths.= "<th>duplicity</th>";
      $tds.= "<$tdr>{$val['dupl']}</td>";
      $html.= "<h3>Záznamy osob v jiných databázích</h3>
        <div class='stat'><table class='stat'><tr>$ths</tr><tr>$tds</tr></table></div>
        <br><b>Poznámka</b>: Týká se to jen záznamů s vyplněnou emailovou adresou, 
        <br>jejíž MD5 je použito jako anonymizovaný identifikátor osoby";      
      break;
    // úschova anonymizovaných údajů do ezer_answer.db_osoba 
    case 'save': 
      query("DELETE FROM ezer_answer.db_osoba WHERE db=$org");
      query("INSERT INTO ezer_answer.db_osoba(md5_osoba,db,lk_first,lk_last) 
        SELECT MD5(REGEXP_SUBSTR(UPPER(TRIM(email)),'^[A-Z0-9._%-]+@[A-Z0-9.-]+\.[A-Z]+')),$org,MIN(datum_od),MAX(datum_od)
          FROM $db.osoba  
          LEFT JOIN $db.spolu USING (id_osoba)
          LEFT JOIN $db.pobyt USING (id_pobyt)
          LEFT JOIN $db.akce ON id_akce=id_duakce
          WHERE deleted='' AND kontakt=1 AND email!='' AND spec=0 AND druh=1
          GROUP BY id_osoba");
      $n= select('COUNT(*)','ezer_answer.db_osoba',"db=$org");
      $html.= "Uloženy anonymizované údaje pro $n osob ";
      break;
  }
  return $html;
}
// zjistí počet duplicitních hodnot v dané položce 
function db2_info_dupl($tab,$fld,$cond) {
  $dup= pdo_qry("SELECT COUNT(*) FROM (
      SELECT COUNT(*) AS _pocet
        FROM $tab WHERE deleted='' AND $cond
      GROUP BY $fld HAVING _pocet>1
    ) AS _dupl");
  list($dup)= pdo_fetch_row($dup);
  return $dup;
}
# ----------------------------------------------------------------------------------------- db2 stav
function db2_stav($db) {
  global $ezer_root,$ezer_db,$USER;
  $tabs= array(
    '_user'  => (object)array('cond'=>"deleted=''"      ,'obe'=>1),
    'rodina' => (object)array('cond'=>"deleted=''"      ,'obe'=>1),
    'osoba'  => (object)array('cond'=>"deleted=''"      ,'obe'=>1)
  );
  $html= '';
  // přehled tabulek podle access
  $html= "<h3>Seznam tabulek s rozdělením podle příslušnosti k organizacím</h3>";
  $html.= "<div class='stat'><table class='stat'>";
  $html.= "<tr><th>tabulka</th>
    <th style='background-color:#f77'>access=0</th>
    <th style='background-color:#af8'>Setkání</th>
    <th style='background-color:#acf'>Familia</th>
    <th style='background-color:#aff'>sdíleno</th>
    <th style='background-color:#aaa'>smazáno</th></tr>";
  foreach ($tabs as $tab=>$desc) {
    $html.= "<tr><th>$tab</th>";
    $obe= 0;
    $rt= pdo_qry("
      SELECT access,COUNT(*) AS _pocet FROM ezer_$db.$tab
      WHERE access=0 AND {$desc->cond} GROUP BY access ORDER BY access");
    if ( $rt && ($t= pdo_fetch_object($rt)) ) {
      $html.= "<td style='text-align:right' title='{$t->access}'>{$t->_pocet}</td>";
    }
    else {
      $html.= "<td style='text-align:right' title='0'>0</td>";
    }
    $rt= pdo_qry("
      SELECT access,COUNT(*) AS _pocet FROM ezer_$db.$tab
      WHERE access>0 AND {$desc->cond} GROUP BY access ORDER BY access");
    while ( $rt && ($t= pdo_fetch_object($rt)) ) {
      $html.= "<td style='text-align:right' title='{$t->access}'>{$t->_pocet}</td>";
      if ( $t->access==3 ) $obe= 1;
    }
    if ( !$desc->obe ) {
      $html.= "<td style='text-align:right' title='nemá smysl'>-</td>";
    }
    elseif ( !$obe ) {
      $html.= "<td style='text-align:right' title='3'>0</td>";
    }
    $rt= pdo_qry("
      SELECT COUNT(*) AS _pocet FROM ezer_$db._track
      WHERE op='x' AND kde='$tab' AND old='smazaná kopie' ");
    if ( $rt && ($t= pdo_fetch_object($rt)) ) {
      $html.= "<td style='text-align:right'>{$t->_pocet}</td>";
    }
    $html.= "</tr>";
  }
  $html.= "</table></div>";
  $vidi= array('ZMI','GAN','HAN');
  if ( in_array($USER->abbr,$vidi) ) {
    $html.= "<br><hr><h3>Sjednocování podrobněji (informace pro ".implode(',',$vidi).")</h3>";
//     $html.= db2_stav_kdo($db,"kdy > '2015-12-01'",
//       "Od prosince 2015 - (převážně) sjednocování Setkání & Familia");
    $html.= db2_prubeh_kdo($db,'2015-11',
      "Sjednocování Setkání & Familia - od teď do prosince 2015");
    $html.= db2_stav_kdo($db,"kdy <= '2015-12-01'",
      "<br><br>... a do prosince 2015 - sjednocení v oddělených databázích");
  }
  // technický stav
  $dbs= array();
  foreach ($ezer_db as $db=>$desc) {
    $dbs[$db]= $desc[5];
  }
  $stav= array(
    "ezer_root"=>$ezer_root,
    "dbs"=>$dbs
  );
//                                        debug($stav);
  return $html;
}
function db2_prubeh_kdo($db,$od,$tit) {
  // sjednotitelé - seznam
  $kdos= $kolik= array();
  $rt= pdo_qry("
    SELECT kdo,SUM(IF(kde IN ('osoba','rodina'),IF(op='d',1,-1),0)) AS _osob
    FROM ezer_$db._track WHERE op IN ('d','V') AND kdy>'$od'
    GROUP BY kdo ORDER BY _osob DESC
  ");
  while ( $rt && (list($kdo,$celkem)= pdo_fetch_row($rt)) ) {
    $kdos[]= $kdo;
    $kolik[$kdo]= $celkem;
  }
  // sjednotitelé - výpočet
  $sje= $mes= array();
  $rt= pdo_qry("
    SELECT kdo,LEFT(kdy,7) as _ym,
      SUM(IF(kde='osoba',IF(op='d',1,-1),0)) AS _osob,
      SUM(IF(kde='rodina',IF(op='d',1,-1),0)) AS _rodin
    FROM ezer_$db._track WHERE op IN ('d','V') AND kdy>'$od'
    GROUP BY kdo,_ym ORDER BY _ym ASC
  ");
  while ( $rt && (list($kdo,$kdy,$osob,$rodin)= pdo_fetch_row($rt)) ) {
    $sje[$kdy][$kdo]= "$osob ($rodin)";
    $mes[$kdy]+= $osob + $rodin;
  }
  // maxima :-)
  $kdys= array_keys($sje);
  $maxi= array();
  foreach ($kdys as $kdy) {
    $max= $maxi[$kdy]= -100;
    foreach ($kdos as $kdo) {
      list($o,$r)= explode(' / ',$sje[$kdy][$kdo]);
      if ( $o+$r > $max ) {
        $max= $o+$r;
        $maxi[$kdy]= $kdo;
      }
    }
  }
  // čas
  $do= date("Y-m");
  foreach ($kdos as $kdo) {
    $grf= "<tr><td style='border:0'></td>";
    $top= "<tr><th>osob (rodin)</th>";
    $row.= "<tr><th>$kdo</th>";
    for ($y=substr($do,0,4); $y>= 2015; $y--) {
      for ($m= 12; $m>=1; $m--) {
        $ym= "$y-".str_pad($m,2,'0',STR_PAD_LEFT);
        if ( $od<$ym && $ym<=$do ) {
          $styl= $maxi[$ym]==$kdo ? " style='background-color:yellow'" : '';
          $h= $mes[$ym] / 5;
          $g= "<div class='curr_akce' style='height:{$h}px;width:30px;'>";
          $grf.= "<td style='vertical-align:bottom;border:0'>$g</td>";
          $top.= "<th>$y.$m</th>";
          $row.= "<td align='right'$styl>{$sje[$ym][$kdo]}</td>";
        }
      }
    }
    $row.= "</tr>";
    $top.= "</tr>";
    $grf.= "</tr>";
  }
  $html.= "$tit<br><br>";
  $html.= "<div class='stat'><table class='stat'>$grf$top$row</table></div>";
  return $html;
}
function db2_stav_kdo($db,$desc,$tit) {
  // sjednotitelé - výpočet
  $sje= array();
  $rt= pdo_qry("
    SELECT kdo,
      SUM(IF(kde='osoba',IF(op='d',1,-1),0)) AS _osob,
      SUM(IF(kde='rodina',IF(op='d',1,-1),0)) AS _rodin
    FROM ezer_$db._track WHERE op IN ('d','V') AND $desc
    GROUP BY kdo");
  while ( $rt && ($t= pdo_fetch_object($rt)) ) {
    $sje[$t->kdo]['o']+= $t->_osob;
    $sje[$t->kdo]['r']+= $t->_rodin;
  }
  // sjednotitelé - řazení
  uasort($sje,function($a,$b) {
    return $a['o']==$b['o'] ? 0 : ($a['o']<$b['o'] ? 1 : -1);
  });
  // sjednotitelé - zobrazení
  $html= "$tit<br><br>";
  $html.= "<div class='stat'><table class='stat'>";
  $html.= "<tr><th>kdo</th><th>rodin</th><th>osob</th></tr>";
  foreach ($sje as $s=>$or) {
    $html.= "<tr><th>$s</th>";
    $html.= "<td style='text-align:right'>{$or['r']}</td>";
    $html.= "<td style='text-align:right'>{$or['o']}</td>";
    $html.= "</tr>";
  }
  $html.= "</table></div>";
  return $html;
}
# --------------------------------------------------------------------------------==> . testovací db
# --------------------------------------------------------------------------------- db2 copy_test_db
# zkopíruje důležité tabulky a soubory z ezer_$db do ezer_$db_test
# pro $db=db2 zkopíruje také setkani4 do setkani4_test
function db2_copy_test_db($db) {  trace();
  $msg= ''; $del= '';
  query("SET sql_mode = 'NO_AUTO_VALUE_ON_ZERO'");
  // tabulka, ze které se kopíruje jen posledních $max záznamů, má před jménem hvězdičku
  $max= 5000;
  $tabs= explode(',',
//     "_user,_skill,"
    "*_touch,*_todo,*_track,*mail,"
  . "_help,_cis,ezer_doc2,"
  . "akce,cenik,pobyt,spolu,osoba,tvori,rodina,"
//a  . "g_akce,join_akce,"
  . "prihlaska,"
  . "dar,uhrada,"
  . "dopis,mailist,"
  . "pdenik,person,pokladna,"
  . "faktura,platba,join_platba"
  );
  $msg.= "<h3>Kopie databáze ezer_{$db} do ezer_{$db}_test</h3>";
  foreach ($tabs as $xtab ) {
    $tab= $xtab;
    if ( $tab[0]=='*' ) $tab= substr($tab,1);
    $je= select('COUNT(*)','information_schema.tables',
        "table_schema='ezer_{$db}' AND table_name='$tab' ");
    if (!$je) continue;
    query("DROP TABLE IF EXISTS ezer_{$db}_test.$tab");
    query("CREATE TABLE ezer_{$db}_test.$tab LIKE ezer_{$db}.$tab");
    $LIMIT= $ORDER= '';
    if ( $xtab[0]=='*' ) {
      $count= select('COUNT(*)',$tab);
//      if ($count>$max) $LIMIT= "LIMIT ".($count-$max).", $max";
      if ($count>$max) $LIMIT= "LIMIT $max";
      $ORDER= "ORDER BY id".($tab[0]=='_' ? $tab : "_$tab").' DESC';
    }
//    $MAX= $xtab[0]=='*' ? "WHERE YEAR(kdy)=YEAR(NOW())" : '';
    $n= query("INSERT INTO ezer_{$db}_test.$tab SELECT * FROM ezer_{$db}.$tab $ORDER $LIMIT");
    $msg.= "{$del}COPY ezer_{$db}_test.$tab ... $n záznamů $LIMIT";
    $del= '<br>';
  }

  // kopie logu přihlášek
  $log= "prihlaska.log.php";
  if (file_exists($log)) {
    $KB= round(filesize($log)/1024); 
    $ok= copy($log,"../answer-test/$log") ? '' : 'failed';
    $msg.= "<h3>Kopie logu přihlášek</h3>COPY prihlaska.log.php ... $KB KB $ok";
  } 

  // kopie pro Dům setkání a přihlášek
  if ($db=='db2') {
    $del= '';
    $msg.= "<h3>Kopie databáze setkani4 do setkani4_test</h3>";
    // tabulka¨, která se má jen vytvořit, má před jménem hvězdičku
    $tabs= explode(',',
      "*_touch,*_todo,_track,"
    . "_help,_cis,"
    . "ds_cena,ds_osoba,tx_gnalberice_order,tx_gnalberice_room,tx_gncase,tx_gncase_part"
    );
    foreach ($tabs as $xtab ) {
      $tab= $xtab;
      if ( $tab[0]=='*' ) $tab= substr($tab,1);
      query("DROP TABLE IF EXISTS setkani4_test.$tab");
      query("CREATE TABLE setkani4_test.$tab LIKE setkani4.$tab");
      if ( $xtab[0]!='*' ) {
        $n= query("INSERT INTO setkani4_test.$tab SELECT * FROM setkani4.$tab");
        $msg.= "{$del}COPY setkani4_test.$tab ... $n záznamů";
      }
      else {
        $msg.= "{$del}INIT setkani4_test.$tab";
      }
      $del= '<br>';
    }
    // poznámka k VIEW
    $msg.= "<h3>Zůstávají zachovány definice VIEW z databáze ezer_setkani4_test do ezer_db2_test</h3>
      VIEW ds_order
      <br>VIEW objednávka";
  }
  // end
  ezer_connect("ezer_{$db}");   // jinak zůstane přepnuté na test
  return $msg;
}
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
# =======================================================================> db2 kontrola a oprava dat
# --------------------------------------------------------------------------- db2 kontrola_dat_spolu
# kontrola vazby rodina-tvori-osoba
function db2_kontrola_spolu($par) { trace();
//  global $USER;
  user_test();
//  $now= date("Y-m-d H:i:s");
//  $user= $USER->abbr;
  $html= '';
  $auto= " <b>LZE OPRAVIT AUTOMATICKY</b>";
//  $uziv= " <b>NUTNO OPRAVIT RUČNĚ</b>";
  $n= 0;
  $opravit= $par->opravit ? true : false;

  // ----------------------------------------------==> .. pobyty bez členů
  // pobyty s funkce=99 pro akce MS ponecháváme
  $msg= '';
  $ok= '';
  $res= pdo_qry("SELECT id_akce,id_pobyt,nazev,YEAR(datum_od) AS _rok
          FROM pobyt LEFT JOIN spolu USING (id_pobyt) JOIN akce ON id_akce=id_duakce
          WHERE ISNULL(id_spolu) AND (funkce!=99 OR druh NOT IN (1,2,3,18))
          -- AND YEAR(datum_od)=2024
          ORDER BY id_akce DESC");
  while ( $res && ($x= pdo_fetch_object($res)) ) {
    $n++;
    if ( $opravit ) {
      $deleted= pdo_qry("DELETE FROM pobyt WHERE id_pobyt={$x->id_pobyt}",1);
      $ok= $deleted ? " = SMAZÁNO" : ' !!!!!CHYBA při mazání' ;
    }
    $msg.= "<dd>pobyt ($x->id_pobyt) v {$x->nazev} {$x->_rok} je bez účastníků $ok</dd>";
  }
  $html.= "<dt style='margin-top:5px'> tabulka <b>pobyt</b>: prázdné pobyty"
    .($msg?"$auto$msg":"<dd>ok</dd>")."</dt>";
  // ----------------------------------------------==> .. nulové klíče ve SPOLU
  $msg= '';
  $ok= '';
  $cond= "id_pobyt=0 OR spolu.id_osoba=0 ";
  $qry=  "SELECT id_spolu,spolu.id_osoba,spolu.id_pobyt,
            CONCAT(a.nazev,' ',YEAR(datum_od)) AS nazev,prijmeni,jmeno
          FROM spolu
          LEFT JOIN pobyt AS p USING(id_pobyt)
          LEFT JOIN akce  AS a ON a.id_duakce=p.id_akce
          LEFT JOIN osoba AS o ON o.id_osoba=spolu.id_osoba
          WHERE $cond";
  $res= pdo_qry($qry);
  while ( $res && ($x= pdo_fetch_object($res)) ) {
    $n++;
    if ( $opravit ) {
      $deleted= pdo_qry("DELETE FROM spolu WHERE id_spolu={$x->id_spolu} AND ($cond)",1);
      $ok= $deleted ? " = SMAZÁNO" : ' !!!!!CHYBA při mazání' ;
    }
    if ( !$x->id_pobyt && !$x->id_osoba )
      $msg.= "<dd>záznam spolu={$x->id_spolu} je nulový$ok</dd>";
    if ( !$x->id_osoba )
      $msg.= "<dd>osoba=0 v záznamu spolu={$x->id_spolu} pobytu={$x->id_pobyt} akce {$x->nazev}$ok</dd>";
    if ( !$x->id_pobyt )
      $msg.= "<dd>pobyt=0 v záznamu spolu={$x->id_spolu} osoby {$x->prijmeni} {$x->jmeno}$ok</dd>";
  }
  $html.= "<dt style='margin-top:5px'> tabulka <b>spolu</b>: nulové klíče osoby nebo pobytu"
    .($msg?"$auto$msg":"<dd>ok</dd>")."</dt>";
  # -----------------------------------------==> .. spolu vede na smazanou osobu
  $msg= '';
  $ok= '';
  $rr= pdo_qry("
    SELECT id_spolu,id_osoba,id_pobyt,CONCAT(jmeno,' ',prijmeni),o.deleted,
      CONCAT(a.nazev,' ',YEAR(datum_od)) AS nazev
    FROM spolu JOIN osoba AS o USING (id_osoba) JOIN pobyt AS p USING (id_pobyt)
      LEFT JOIN akce  AS a ON a.id_duakce=p.id_akce
    WHERE o.deleted!=''
    ORDER BY id_pobyt
  ");
  while ( $rr && (list($ids,$ido,$idp,$jm,$od,$nazev)= pdo_fetch_row($rr) ) ) {
    $ok= '';
    $sod= $od ? "smazaný" : '';
    if ( $opravit ) {
      $deleted= pdo_qry("DELETE FROM spolu WHERE id_spolu=$ids",1);
      $ok.= $deleted ? " = SMAZÁNO" : ' !!!!!CHYBA při mazání' ;
    }
    $msg.= "<dd>v pobytu $nazev/$idp je $sod člen $jm/$ido$ok</dd>";
  }
  $html.= "<dt style='margin-top:5px'>tabulka <b>spolu</b>: vazba na smazané osoby"
    .($msg?"$auto$msg":"<dd>ok</dd>")."</dt>";
  // ----------------------------------------------==> .. násobné SPOLU
  $msg= '';
  $ok= '';
  $qry=  "SELECT GROUP_CONCAT(id_spolu) AS _ss,id_pobyt,s.id_osoba,count(*) AS _pocet_,
            CONCAT(a.nazev,' ',YEAR(datum_od)) AS nazev,prijmeni,jmeno
          FROM spolu AS s
          LEFT JOIN pobyt AS p USING(id_pobyt)
          LEFT JOIN akce  AS a ON a.id_duakce=p.id_akce
          LEFT JOIN osoba AS o ON o.id_osoba=s.id_osoba
          GROUP BY s.id_osoba,id_pobyt HAVING _pocet_>1
          ORDER BY id_akce";
  $res= pdo_qry($qry);
  while ( $res && ($x= pdo_fetch_object($res)) ) {
    $n++;
    $ok= '';
    if ( $opravit ) {
      $ss= explode(',',$x->_ss);
      unset($ss[0]);
      if ( count($ss) ) {
        $ss= implode(',',$ss);
        $deleted= pdo_qry("DELETE FROM spolu WHERE id_spolu IN ($ss)");
        $ok= $deleted ? " = spolu SMAZÁNO $deleted x" : ' CHYBA při mazání spolu' ;
      }
    }
    $ido= tisk2_ukaz_osobu($x->id_osoba);
    $msg.= "<dd>násobný pobyt záznamy spolu={$x->_ss} na akci <b>{$x->nazev}</b>
      osoby $ido:{$x->prijmeni} {$x->jmeno} $ok</dd>";
  }
  $html.= "<dt style='margin-top:5px'>tabulka <b>spolu</b>: zdvojení osoby ve stejném pobytu"
    .($msg?"$auto$msg":"<dd>ok</dd>")."</dt>";
  
end:
  // konec
  $html= $n
    ? "<h3>Nalezeno $n inkonzistencí v datech</h3><dl>$html</dl>"
    : "<h3>Následující tabulky jsou konzistentní</h3>$html";
  return $html;
}
# --------------------------------------------------------------------------- db2 kontrola_dat_tvori
# kontrola vazby rodina-tvori-osoba
function db2_kontrola_tvori($par) { trace();
//  global $USER;
  user_test();
//  $now= date("Y-m-d H:i:s");
//  $user= $USER->abbr;
  $html= '';
  $auto= " <b>LZE OPRAVIT AUTOMATICKY</b>";
  $uziv= " <b>NUTNO OPRAVIT RUČNĚ</b>";
  $n= 0;
  $opravit= $par->opravit ? true : false;
//  $msg= '';
  $ok= '';
  // ---------------------------------------==> .. nulové hodnoty v tabulce TVORI
tvori:
//  $msg= '';
  $cond= "tvori.id_rodina=0 OR tvori.id_osoba=0 OR ISNULL(o.id_osoba) OR ISNULL(r.id_rodina)";
  $qry=  "SELECT id_tvori,role,tvori.id_osoba,tvori.id_rodina,r.nazev,prijmeni,jmeno,
             IFNULL(o.id_osoba,0) AS _o_ido, IFNULL(r.id_rodina,0) AS _r_idr
          FROM tvori
          LEFT JOIN rodina AS r USING(id_rodina)
          LEFT JOIN osoba AS o ON o.id_osoba=tvori.id_osoba
          WHERE $cond ";
  $res= pdo_qry($qry);
  while ( $res && ($x= pdo_fetch_object($res)) ) {
    $n++;
//     if ( $opravit ) {
//       $ok= pdo_qry("DELETE FROM tvori WHERE id_tvori={$x->id_tvori} AND ($cond)",1)
//          ? " = SMAZÁNO" : ' !!!!!CHYBA při mazání' ;
//     }
    if ( !$x->id_pobyt && !$x->id_osoba )
      $msg.= "<dd>záznam tvori={$x->id_tvori} je nulový</dd>";
    if ( !$x->id_osoba )
      $msg.= "<dd>osoba=0 v záznamu tvori={$x->id_tvori} rodiny={$x->id_rodina} {$x->nazev}$ok</dd>";
    if ( !$x->id_rodina )
      $msg.= "<dd>rodina=0 v záznamu tvori={$x->id_tvori} osoby {$x->prijmeni} {$x->jmeno}$ok</dd>";
    if ( !$x->_o_ido ) {
      $idr= tisk2_ukaz_rodinu($x->id_rodina);
      $track= db2_track_osoba($x->id_osoba);
      $idt= db2_smaz_tvori($x->id_tvori);
      $msg.= "<dd>neexistující osoba id=$track v záznamu tvori=$idt
              rodiny $idr:{$x->nazev} $ok</dd>";
    }
    if ( !$x->_r_idr ) {
      $ido= tisk2_ukaz_osobu($x->id_osoba);
      $track= db2_track_rodina($x->id_rodina);
      $idt= db2_smaz_tvori($x->id_tvori);
      $msg.= "<dd>neexistující rodina id=$track v záznamu tvori=$idt
              osoby $ido:{$x->prijmeni} {$x->jmeno}$ok</dd>";
    }
  }
  $html.= "<dt style='margin-top:5px'>tabulka <b>tvori</b>: nulové a neexistující hodnoty v tabulce"
    .($msg?"$uziv$msg":"<dd>ok</dd>")."</dt>";
// goto end;
  # -----------------------------------------==> .. tvori vede na smazanou osobu/rodinu
  $msg= '';
  $rr= pdo_qry("
    SELECT id_tvori,id_osoba,id_rodina,r.nazev,CONCAT(jmeno,' ',prijmeni),o.deleted,r.deleted,
      IF(role NOT IN ('a','b','d','p'),1,0)
    FROM tvori JOIN osoba AS o USING (id_osoba) JOIN rodina AS r USING (id_rodina)
    WHERE o.deleted!='' OR r.deleted!=''
    ORDER BY id_rodina
  ");
  while ( $rr && (list($idt,$ido,$idr,$nazev,$jm,$od,$rd,$norole)= pdo_fetch_row($rr) ) ) {
    $ok= '';
    $sod= $od ? "smazaný" : '';
    $srd= $rd ? "smazané" : '';
    if ( $opravit ) {
      $deleted= pdo_qry("DELETE FROM tvori WHERE id_tvori=$idt",1);
      $ok.= $deleted ? " = SMAZÁNO" : ' !!!!!CHYBA při mazání' ;
    }
    $msg.= "<dd>v $srd rodině $nazev/$idr je $sod člen $jm/$ido$ok</dd>";
  }
  $html.= "<dt style='margin-top:5px'>tabulka <b>tvori</b>: vazby mezi smazanými záznamy"
    .($msg?"$auto$msg":"<dd>ok</dd>")."</dt>";
  # -----------------------------------------==> .. tvori s nekorektní rolí
  $msg= '';
  $rr= pdo_qry("
    SELECT id_tvori,id_osoba,id_rodina,r.nazev,CONCAT(jmeno,' ',prijmeni),role
    FROM tvori JOIN osoba AS o USING (id_osoba) JOIN rodina AS r USING (id_rodina)
    WHERE role NOT IN ('a','b','d','p')
    ORDER BY id_rodina
  ");
  while ( $rr && (list($idt,$ido,$idr,$nazev,$jm,$role)= pdo_fetch_row($rr) ) ) {
    $ok= '';
    $idr= tisk2_ukaz_rodinu($idr);
    $msg.= "<dd>v $srd rodině $idr:$nazev je $sod člen $jm/$ido s nekorektní rolí</dd>";
  }
  $html.= "<dt style='margin-top:5px'>tabulka <b>tvori</b>: kontrola rodinných rolí $role"
    .($msg?"$uziv$msg":"<dd>ok</dd>")."</dt>";
  # -----------------------------------------==> .. násobné členství v rodině
  $msg= '';
  $qry=  "SELECT GROUP_CONCAT(id_tvori) AS _ts,count(*) AS _pocet_,GROUP_CONCAT(DISTINCT role) AS _role_,
            tvori.id_osoba,tvori.id_rodina,r.nazev,prijmeni,jmeno
          FROM tvori
          LEFT JOIN rodina AS r USING (id_rodina)
          LEFT JOIN osoba AS o ON o.id_osoba=tvori.id_osoba
          GROUP BY id_osoba,id_rodina HAVING _pocet_>1
          ORDER BY id_rodina ";
  $res= pdo_qry($qry);
  while ( $res && ($x= pdo_fetch_object($res)) ) {
    $n++;
    $ok= '';
    $ts= explode(',',$x->_ts);
    if ( $opravit && strlen($x->_role_)==1 ) {
      $deleted= pdo_qry("DELETE FROM tvori WHERE id_tvori={$ts[0]}",1);
      $ok.= $deleted ? " = SMAZÁNO" : ' !!!!!CHYBA při mazání' ;
    }
    $ido= tisk2_ukaz_osobu($x->id_osoba);
    $idr= tisk2_ukaz_rodinu($x->id_rodina);
    $msg.= "<dd>násobné členství záznamem tvori=({$x->_ts}) v rodině $idr:{$x->nazev}
      osoby $ido:{$x->prijmeni} {$x->jmeno} v roli {$x->_role_}  $ok</dd>";
  }
  $html.= "<dt style='margin-top:5px'>tabulka <b>tvori</b>: násobné členství osoby v rodině"
    .($msg?"{$auto} pokud osoba nemá více rolí$msg":"<dd>ok</dd>")."</dt>";
  # -----------------------------------------==> .. mnoho otců/matek
  $msg= '';
  $qry=  "SELECT SUM(IF(role='a',1,0)) AS _otcu, GROUP_CONCAT(IF(role='a',id_tvori,'')) AS _otci,
            SUM(IF(role='b',1,0)) AS _matek, GROUP_CONCAT(IF(role='b',id_tvori,'')) AS _matky,
            SUM(IF(role='d',1,0)) AS _deti,
            id_rodina, nazev
          FROM tvori
          LEFT JOIN rodina AS r USING (id_rodina)
          LEFT JOIN osoba AS o USING (id_osoba)
          WHERE r.deleted='' AND o.deleted=''
          GROUP BY id_rodina HAVING _otcu>1 OR _matek>1 OR (_otcu=0 AND _matek=0)
          ORDER BY id_rodina ";
  $res= pdo_qry($qry);
  while ( $res && ($x= pdo_fetch_object($res)) ) {
    $n++;
    $idr= tisk2_ukaz_rodinu($x->id_rodina);
    $otci= trim(str_replace(',,',',',$x->_otci),',');
    $matky= trim(str_replace(',,',',',$x->_matky),',');
    if ( $x->_otcu>1 )
      $msg.= "<dd>{$x->_otcu} muži v roli 'a' v rodině $idr:{$x->nazev} ($otci)</dd>";
    if ( $x->_matek>1 )
      $msg.= "<dd>{$x->_matek} ženy v roli 'b' v rodině $idr:{$x->nazev} ($matky)</dd>";
    if ( !$x->_matek && !$x->_otcu )
      $msg.= "<dd>{$x->_deti} dětí v roli 'd' bez rodičů v rodině $idr:{$x->nazev} </dd>";
  }
  $html.= "<dt style='margin-top:5px'>tabulka <b>tvori</b>: nestandardní počet otců='a', matek='b' v rodině"
    .($msg?"$uziv$msg":"<dd>ok</dd>")."</dt>";

end:
  // konec
  $html= $n
    ? "<h3>Nalezeno $n inkonzistencí v datech</h3><dl>$html</dl>"
    : "<h3>Následující tabulky jsou konzistentní</h3>$html";
  return $html;
}
/*
# --------------------------------------------------------------------------------- db2 kontrola_dat
# kontrola dat
function db2_kontrola_dat($par) { trace();
  global $USER;
  user_test();
  $now= date("Y-m-d H:i:s");
  $user= $USER->abbr;
  $html= '';
  $auto= " <b>LZE OPRAVIT AUTOMATICKY</b>";
  $uziv= " <b>NUTNO OPRAVIT RUČNĚ</b>";
  $n= 0;
  $opravit= $par->opravit ? true : false;
  $msg= '';
  $ok= '';
  // testy nových kontrol
  // ----------------------------------------------==> .. testy
  if ( isset($par->test) ) {
    switch ($par->test) {
    case 'test':
  # -----------------------------------------==> .. spolu vede na smazanou osobu
  $msg= '';
  $rr= pdo_qry("
    SELECT id_spolu,id_osoba,id_pobyt,CONCAT(jmeno,' ',prijmeni),o.deleted,a.nazev
    FROM spolu JOIN osoba AS o USING (id_osoba) JOIN pobyt AS p USING (id_pobyt)
      LEFT JOIN akce  AS a ON a.id_duakce=p.id_akce
    WHERE o.deleted!=''
    ORDER BY id_pobyt
  ");
  while ( $rr && (list($ids,$ido,$idp,$jm,$od,$nazev)= pdo_fetch_row($rr) ) ) {
    $ok= '';
    $sod= $od ? "smazaný" : '';
    if ( $opravit ) {
      $ok.= pdo_qry("DELETE FROM spolu WHERE id_spolu=$ids",1)
         ? " = SMAZÁNO" : ' !!!!!CHYBA při mazání' ;
    }
    $msg.= "<dd>v $srd pobytu $nazev/$idp je $sod člen $jm/$ido$ok</dd>";
  }
  $html.= "<dt style='margin-top:5px'>tabulka <b>spolu</b>: vazba na smazané osoby"
    .($msg?"$auto$msg":"<dd>ok</dd>")."</dt>";
      break;
    }
    goto end;
  }
//   goto access;
  // kontrola nenulovosti klíčů ve spojovacích záznamech
  // ----------------------------------------------==> .. nulové klíče ve SPOLU
  $cond= "id_pobyt=0 OR spolu.id_osoba=0 ";
  $qry=  "SELECT id_spolu,spolu.id_osoba,spolu.id_pobyt,
            CONCAT(a.nazev,' ',YEAR(datum_od)) AS nazev,prijmeni,jmeno
          FROM spolu
          LEFT JOIN pobyt AS p USING(id_pobyt)
          LEFT JOIN akce  AS a ON a.id_duakce=p.id_akce
          LEFT JOIN osoba AS o ON o.id_osoba=spolu.id_osoba
          WHERE $cond";
  $res= pdo_qry($qry);
  while ( $res && ($x= pdo_fetch_object($res)) ) {
    $n++;
    if ( $opravit ) {
      $ok= pdo_qry("DELETE FROM spolu WHERE id_spolu={$x->id_spolu} AND ($cond)",1)
         ? " = SMAZÁNO" : ' !!!!!CHYBA při mazání' ;
    }
    if ( !$x->id_pobyt && !$x->id_osoba )
      $msg.= "<dd>záznam spolu={$x->id_spolu} je nulový$ok</dd>";
    if ( !$x->id_osoba )
      $msg.= "<dd>osoba=0 v záznamu spolu={$x->id_spolu} pobytu={$x->id_pobyt} akce {$x->nazev}$ok</dd>";
    if ( !$x->id_pobyt )
      $msg.= "<dd>pobyt=0 v záznamu spolu={$x->id_spolu} osoby {$x->prijmeni} {$x->jmeno}$ok</dd>";
  }
  $html.= "<dt style='margin-top:5px'> tabulka <b>spolu</b>: nulové klíče osoby nebo pobytu"
    .($msg?"$auto$msg":"<dd>ok</dd>")."</dt>";
  # -----------------------------------------==> .. spolu vede na smazanou osobu
  $msg= '';
  $rr= pdo_qry("
    SELECT id_spolu,id_osoba,id_pobyt,CONCAT(jmeno,' ',prijmeni),o.deleted,
      CONCAT(a.nazev,' ',YEAR(datum_od)) AS nazev
    FROM spolu JOIN osoba AS o USING (id_osoba) JOIN pobyt AS p USING (id_pobyt)
      LEFT JOIN akce  AS a ON a.id_duakce=p.id_akce
    WHERE o.deleted!=''
    ORDER BY id_pobyt
  ");
  while ( $rr && (list($ids,$ido,$idp,$jm,$od,$nazev)= pdo_fetch_row($rr) ) ) {
    $ok= '';
    $sod= $od ? "smazaný" : '';
    if ( $opravit ) {
      $ok.= pdo_qry("DELETE FROM spolu WHERE id_spolu=$ids",1)
         ? " = SMAZÁNO" : ' !!!!!CHYBA při mazání' ;
    }
    $msg.= "<dd>v $srd pobytu $nazev/$idp je $sod člen $jm/$ido$ok</dd>";
  }
  $html.= "<dt style='margin-top:5px'>tabulka <b>spolu</b>: vazba na smazané osoby"
    .($msg?"$auto$msg":"<dd>ok</dd>")."</dt>";
  // ----------------------------------------------==> .. násobné SPOLU
  $msg= '';
  $qry=  "SELECT GROUP_CONCAT(id_spolu) AS _ss,id_pobyt,s.id_osoba,count(*) AS _pocet_,
            CONCAT(a.nazev,' ',YEAR(datum_od)) AS nazev,prijmeni,jmeno
          FROM spolu AS s
          LEFT JOIN pobyt AS p USING(id_pobyt)
          LEFT JOIN akce  AS a ON a.id_duakce=p.id_akce
          LEFT JOIN osoba AS o ON o.id_osoba=s.id_osoba
          GROUP BY s.id_osoba,id_pobyt HAVING _pocet_>1
          ORDER BY id_akce";
  $res= pdo_qry($qry);
  while ( $res && ($x= pdo_fetch_object($res)) ) {
    $n++;
    $ok= '';
    if ( $opravit ) {
      $ss= explode(',',$x->_ss);
      unset($ss[0]);
      $ss= implode(',',$ss);
      if ( count($ss) ) {
        $ok= pdo_qry("DELETE FROM spolu WHERE id_spolu IN ($ss)")
          ? " = spolu SMAZÁNO $ok x" : ' CHYBA při mazání spolu' ;
      }
    }
    $ido= tisk2_ukaz_osobu($x->id_osoba);
    $msg.= "<dd>násobný pobyt záznamy spolu={$x->_ss} na akci <b>{$x->nazev}</b>
      osoby $ido:{$x->prijmeni} {$x->jmeno} $ok</dd>";
  }
  $html.= "<dt style='margin-top:5px'>tabulka <b>spolu</b>: zdvojení osoby ve stejném pobytu"
    .($msg?"$auto$msg":"<dd>ok</dd>")."</dt>";
  // ---------------------------------------==> .. zdvojení osoby v různých pobytech na stejné akci
  $msg= '';
  $qry=  "SELECT s.id_osoba,GROUP_CONCAT(id_pobyt) AS _sp,count(DISTINCT id_pobyt) AS _pocet_,
            CONCAT(a.nazev,' ',YEAR(datum_od)) AS nazev,id_akce,prijmeni,jmeno
          FROM spolu AS s
          LEFT JOIN pobyt AS p USING(id_pobyt)
          LEFT JOIN akce  AS a ON a.id_duakce=p.id_akce
          LEFT JOIN osoba AS o ON o.id_osoba=s.id_osoba
          -- WHERE platba+platba_d=0 AND funkce!=99
          GROUP BY id_osoba,id_akce HAVING _pocet_>1
          ORDER BY id_akce";
  $res= pdo_qry($qry);
  while ( $res && ($x= pdo_fetch_object($res)) ) {
    $n++;
    $ok= '';
    $ido= tisk2_ukaz_osobu($x->id_osoba);
    $pp= $del= '';
    $ida= $x->id_akce;
    foreach (explode(',',$x->_sp) as $idp) {
      $pp.= $del.tisk2_ukaz_pobyt_akce($idp,$ida);
      $del= ", ";
    }
    $msg.= "<dd>násobný pobyt na akci {$x->nazev} - pobyty $pp pro
      osobu $ido:{$x->prijmeni} {$x->jmeno} $ok</dd>";
  }
  $html.= "<dt style='margin-top:5px'>tabulka <b>pobyt</b>: zdvojení osoby v různých pobytech na stejné akci"
    .($msg?"$auto$msg":"<dd>ok</dd>")."</dt>";
  // -------------------------------------------==> .. fantómová osoba
  $msg= '';
  $rx= pdo_qry("
    SELECT o.id_osoba,id_dar,id_platba,s.id_spolu,p.id_pobyt,a.id_duakce,
      a.nazev,id_tvori,r.id_rodina,r.nazev,t.role /+,o.* +/
    FROM osoba AS o
    LEFT JOIN dar    AS d ON d.id_osoba=o.id_osoba
    LEFT JOIN platba AS x ON x.id_osoba=o.id_osoba
    LEFT JOIN spolu  AS s ON s.id_osoba=o.id_osoba
    LEFT JOIN pobyt  AS p ON p.id_pobyt=s.id_pobyt
    LEFT JOIN akce   AS a ON a.id_duakce=p.id_akce
    LEFT JOIN tvori  AS t ON t.id_osoba=o.id_osoba
    LEFT JOIN rodina AS r ON r.id_rodina=t.id_rodina
    WHERE prijmeni='' AND jmeno='' AND o.origin=''
    ORDER BY o.id_osoba DESC
  ");
  while ( $rx && ($x= pdo_fetch_object($rx)) ) {
    $n++;
//     if ( $opravit ) {
//       $ok= '';
//       if ( !$x->id_dar && !$x->id_platba ) {
//         $ok.= pdo_qry("DELETE FROM spolu WHERE id_osoba={$x->id_osoba}")
//            ? (" = spolu SMAZÁNO ".pdo_affected_rows().'x') : ' CHYBA při mazání spolu' ;
//         $ok.= pdo_qry("DELETE FROM osoba WHERE id_osoba={$x->id_osoba}")
//            ? ", osoba SMAZÁNO " : ' CHYBA při mazání osoba ' ;
//       }
//       else
//         $ok= " = vazba na dar či platbu";
//     }
    $ido= tisk2_ukaz_osobu($x->id_osoba);
    $idr= tisk2_ukaz_rodinu($x->id_rodina);
    $msg.= "<dd>fantómová osoba $ido v rodině $idr:{$x->nazev} v roli {$x->role} $ok</dd>";
  }
  $html.= "<dt style='margin-top:5px'>tabulka <b>osoba</b>: fantómové osoby"
    .($msg?"$uziv$msg":"<dd>ok</dd>")."</dt>";
  // ---------------------------------------------==> .. triviální RODINA
  $msg= '';
  $rx= pdo_qry("
    SELECT id_rodina,IFNULL(MAX(id_tvori),0) AS _idt,nazev,COUNT(*) AS _pocet
    FROM rodina AS r LEFT JOIN tvori USING (id_rodina)
    LEFT JOIN dar    AS d USING (id_rodina)
    LEFT JOIN platba AS x USING (id_rodina)
    LEFT JOIN pobyt AS p ON r.id_rodina=p.i0_rodina
    WHERE r.deleted=''
    GROUP BY id_rodina HAVING _idt=0 AND _pocet=1
  ");
  while ( $rx && (list($idr,$idts,$nazev)= pdo_fetch_row($rx)) ) {
    $n++;
    $ok= '';
    if ( $opravit ) {
      $ok= query("UPDATE rodina SET deleted='D' WHERE id_rodina=$idr")
         ? ", SMAZÁNO " : ' CHYBA při mazání rodina ' ;
      query("INSERT INTO _track (kdy,kdo,kde,klic,fld,op,old,val)
             VALUES ('$now','$user','rodina',$idr,'','x','','')");
    }
    $msg.= "<dd>triviální rodina bez závazků $nazev/$idr $ok</dd>";
  }
  $html.= "<dt style='margin-top:5px'>tabulka <b>rodina</b>: rodina bez členů"
    .($msg?"$auto<br>$msg":"<dd>ok</dd>")."</dt>";
  // ------------------------------------------------==> .. triviální POBYT
  $msg= '';
  $rx= pdo_qry("
    SELECT id_pobyt,nazev,YEAR(datum_od) AS _rok
    FROM pobyt AS p
    LEFT JOIN spolu USING (id_pobyt)
    LEFT JOIN akce AS a ON a.id_duakce=p.id_akce
    -- LEFT JOIN mail USING(id_pobyt)
    WHERE ISNULL(id_spolu) AND funkce!=99
    GROUP BY id_pobyt -- HAVING _pocet=0
  ");
  while ( $rx && ($x= pdo_fetch_object($rx)) ) {
    $n++;
    if ( $opravit ) { // ... zkontrolovat mail
      $ok= pdo_qry("DELETE FROM pobyt WHERE id_pobyt={$x->id_pobyt}",1)
         ? " = SMAZÁNO" : ' !!!!!CHYBA při mazání' ;
    }
    $idp= tisk2_ukaz_pobyt($x->id_pobyt);
    $msg.= "<dd>triviální pobyt $idp: rok {$x->_rok} {$x->nazev} $ok</dd>";
  }
  $html.= "<dt style='margin-top:5px'>tabulka <b>pobyt</b>: pobyt bez osob"
    .($msg?"$auto$msg":"<dd>ok</dd>")."</dt>";
  // ------------------------------------------------==> .. POBYT bez akce
  $msg= '';
  $rx= pdo_qry("
    SELECT id_pobyt,id_spolu,id_akce,jmeno,prijmeni
    FROM pobyt JOIN spolu USING(id_pobyt) JOIN osoba USING(id_osoba)
    WHERE id_akce=0
  ");
  while ( $rx && ($x= pdo_fetch_object($rx)) ) {
    $n++;
    $ok= '';
    if ( $opravit ) {
      $ok.= pdo_qry("DELETE FROM spolu WHERE id_spolu={$x->id_spolu}")
        ? " = spolu SMAZÁNO " : ' CHYBA při mazání spolu' ;
      $ok.= pdo_qry("DELETE FROM pobyt WHERE id_pobyt={$x->id_pobyt}")
        ? ", pobyt SMAZÁNO " : ' CHYBA při mazání pobyt ' ;
    }
    $msg.= "<dd>pobyt bez akce - {$x->pobyt} pro osobu {$x->prijmeni} {$x->jmeno} $ok</dd>";
  }
  $html.= "<dt style='margin-top:5px'>tabulka <b>pobyt</b>: nulová akce"
    .($msg?"$auto$msg":"<dd>ok</dd>")."</dt>";
  // ------------------------------------------------==> .. ACCESS=3 ale pobyty tomu neodpovídají
access:
  $msg= $ok= '';
  $osoby_upd= array();
  $rr= pdo_qry("
    SELECT BIT_OR(a.access) AS _aa,r.access,
      GROUP_CONCAT(DISTINCT CONCAT(o.access,':',o.id_osoba)) AS _oas,id_rodina,r.nazev
    FROM rodina AS r JOIN tvori AS t USING (id_rodina)
    JOIN osoba AS o USING (id_osoba) JOIN spolu AS s USING (id_osoba)
    JOIN pobyt AS p USING (id_pobyt) JOIN akce AS a ON id_akce=id_duakce
    WHERE r.access=3
    GROUP BY id_rodina
    HAVING _aa<3
  ");
  while ( $rr && (list($aa,$ra,$oas,$idr,$jm)= pdo_fetch_row($rr) ) ) {
    $n++;
    $osoby_o= $osoby_a= array();
    foreach (explode(',',$oas) as $oa) {
      list($aa1,$oa1)= explode(':',$oa);
      $osoby_o[]= $oa1;
      $osoby_a[]= $aa1;
      $n++;
    }
    if ( $opravit ) {
      ezer_qry("UPDATE","rodina",$idr,array(
        (object)array('fld'=>'access', 'op'=>'U','val'=>$aa,'old'=>$ra)
      ));
      foreach ($osoby_o as $i=>$ido) {
        if ( !in_array($ido,$osoby_upd) ) {
          ezer_qry("UPDATE","osoba",$ido,array(
            (object)array('fld'=>'access', 'op'=>'U','val'=>$aa,'old'=>$osoby_a[$i])
          ));
          $osoby_upd[]= $ido;
        }
      }
      $ok= " OPRAVENO";
    }
    $msg.= "<dd>rodina $jm/$idr jezdí jen na akce $aa i její členové ".implode(', ',$osoby_o)." $ok</dd>";
  }
  $html.= "<dt style='margin-top:5px'>tabulka <b>rodina</b>: je označena jako společná
             ale její členové jezdí býhradně na akce jedné organizace"
        . ($msg?"$auto<br>$msg":"<dd>ok</dd>")."</dt>";

end:
  // konec
  $html= $n
    ? "<h3>Nalezeno $n inkonzistencí v datech</h3><dl>$html</dl>"
    : "<h3>Následující tabulky jsou konzistentní</h3>$html";
  return $html;
}
*/
# -----------------------------------------------------------------------------==> . db2 track_osoba
# zobrazí odkaz na rodinu v evidenci
function db2_track_osoba($ido,$barva='') {
  $style= $barva ? "style='color:$barva'" : '';
  return "<b><a $style href='ezer://syst.nas.track_osoba/$ido'>$ido</a></b>";
}
# ----------------------------------------------------------------------------==> . db2 track_rodina
# zobrazí odkaz na rodinu v evidenci
function db2_track_rodina($idr,$barva='') {
  $style= $barva ? "style='color:$barva'" : '';
  return "<b><a $style href='ezer://syst.nas.track_rodina/$idr'>$idr</a></b>";
}
# ------------------------------------------------------------------------------==> . db2 smaz_tvori
# zobrazí odkaz na rodinu v evidenci
function db2_smaz_tvori($idt,$barva='red') {
  $style= $barva ? "style='color:$barva'" : '';
  return "<b><a $style href='ezer://syst.nas.smaz_tvori/$idt'>$idt</a></b>";
}
