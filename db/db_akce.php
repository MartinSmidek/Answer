<?php # (c) 2009-2010 Martin Smidek <martin@smidek.eu>
# ================================================================================================== TEST
# ------------------------------------------------------------------------------------- test1
function test1() {
//   $x= (object)array('a'=>'ěščřžýáíé',"b=>"=>array("\"ěščřžýáíé\"'radek2'"));
                                                debug($_SESSION);
//   $x= json_encode($x);
//                                                 display($x);
//   $x= json_decode($x);
//                                                 debug($x);
  return $x;
}
# ================================================================================================== DUPL
# ------------------------------------------------------------------------------------- ROLLBACK_ALL
# jen pro ladění - vrácení do původního stavu
function ROLLBACK_ALL() { trace();
  // částečně znova
  query("TRUNCATE TABLE duplo");
  return 1;
  // úplně znova
  $last_pobyt= 12302;
  $last_spolu= 24442;
  $last_duplo= 0;
//   // po sjednocení cs, cr - před sr
//   $last_pobyt= 16562;
//   $last_spolu= 28631;
//   $last_duplo= 1413;
  query($last_duplo ? "DELETE FROM duplo WHERE id_duplo>$last_duplo": "TRUNCATE TABLE duplo");
  query("DELETE FROM ch_fa WHERE tab!='a'");
  query("DELETE FROM pobyt WHERE id_pobyt>$last_pobyt");
  query("DELETE FROM spolu WHERE id_spolu>$last_spolu");
  return 1;
}
# --------------------------------------------------------------------------------------- calc_tvori
function calc_tvori($idt) { trace();
  $ret= (object)array();
  $qd= mysql_qry("
    SELECT id_tvori,id_osoba,CONCAT(prijmeni,' ',jmeno) AS jmena,id_rodina,rodina.nazev,
      id_spolu,id_pobyt,id_akce,akce.nazev AS a_nazev
    FROM tvori JOIN osoba USING(id_osoba) JOIN rodina USING(id_rodina)
    LEFT JOIN spolu USING(id_osoba) LEFT JOIN pobyt USING(id_pobyt)
    LEFT JOIN akce ON id_duakce=id_akce
    WHERE id_tvori=$idt AND akce.spec=0
    ORDER BY akce.datum_od DESC,id_akce DESC LIMIT 1
  ");
  if ( !$qd ) { $ret->err= "ERROR: ".mysql_error(); goto end; }
  if (($d= mysql_fetch_object($qd))) {
    foreach ($d as $fld=>$val) {
      $ret->$fld= $val;
    }
  }
end:
//                                                                 debug($ret,"calc_tvori($idt)");
  return $ret;
}
# --------------------------------------------------------------------------------------- calc_spolu
function calc_spolu($ids) { trace();
  $ret= (object)array();
  $qd= mysql_qry("
    SELECT id_akce,id_pobyt,id_spolu,id_osoba,id_tvori,id_rodina,
      akce.nazev AS a_nazev,CONCAT(prijmeni,' ',jmeno) AS jmena,rodina.nazev
    FROM spolu JOIN pobyt USING(id_pobyt) JOIN akce ON id_duakce=id_akce
    JOIN osoba USING(id_osoba) JOIN tvori USING(id_osoba) JOIN rodina USING(id_rodina)
    WHERE id_spolu=$ids
  ");
  if ( !$qd ) { $ret->err= "ERROR: ".mysql_error(); goto end; }
  if (($d= mysql_fetch_object($qd))) {
    foreach ($d as $fld=>$val) {
      $ret->$fld= $val;
    }
  }
end:
//                                                                 debug($ret,"calc_spolu($idt)");
  return $ret;
}
# --------------------------------------------------------------------------------- data_eli_dupl_sr
# členu rodiny ztotožněném se singlem přepíše informace o akcích a singla zrušíme
# upraví položku DUPLO.rozdily=10 (šedá) v předaném záznamu
# pokud je to poslední duplicita v rámci rodiny tak i v nadřazeném záznamu DUPLO
# faze=2 pro ruční a faze=1 pro automatické ztotožnění
function data_eli_dupl_sr($iddr,$idd,$ids,$idt,$faze=2) { trace();
  $ret= (object)array('err'=>'');
  $ido= select("id_osoba","tvori","id_tvori=$idt");
  # převedení informací o účasti akcích z $ids na $ido
  $qs= mysql_qry("SELECT id_spolu FROM spolu WHERE id_osoba=$ids");
  while (($s= mysql_fetch_object($qs))) {
    query("UPDATE spolu SET id_osoba=$ido WHERE id_spolu={$s->id_spolu}");
  }
  # zápis změn do DUPLO
  $qd= query("UPDATE duplo SET rozdily=10,faze=$faze WHERE id_duplo=$idd");
  if ( !$qd ) { $ret->err= "ERROR: ".mysql_error(); goto end; }
  $n= select("COUNT(*)","duplo","idd=$iddr AND rozdily NOT IN (0,10)");
  if ( !$n ) {
    query("UPDATE duplo SET rozdily=10,faze=$faze WHERE id_duplo=$iddr");
  }
  # zápis změněných údajů z DUPLO.chngs do osoba, jsou-li
  $chngs_s= select("chngs","duplo","id_duplo=$idd");
  $o= select("*","osoba","id_osoba=$ido");
  if ( $chngs_s ) {
    $chngs= json_decode($chngs_s);
//                                                   debug($chngs);
    $zmeny= array();
    foreach ($chngs as $fld=>$chng) {
      $val= is_array($chng) ? $chng[0] : $chng;
      $old= $o->$fld;
      if ( $fld=='narozeni' ) $val= sql_date1($val,1);
      if ( $val != $old && isset($o->$fld) ) {
        $zmeny[]= (object)array('fld'=>$fld,'op'=>'u','val'=>$val,'old'=>$old);
      }
    }
//                                                   debug($zmeny);
    // zápis o ztotožnšní se single do _track jako op=d (duplicita)
    $now= date("Y-m-d H:i:s");
    $user= $USER->abbr;
    query("INSERT INTO _track (kdy,kdo,kde,klic,fld,op,old,val)
          VALUES ('$now','$user','osoba',$ido,'','d','osoba',$ids)");
    // promítnutí změn do OSOBA
    if ( count($zmeny) ) {
      ezer_qry("UPDATE",'osoba',$ido,$zmeny);
    }
    // zneplatnění single
    query("UPDATE osoba SET deleted='D osoba=$ido' WHERE id_osoba=$ids");
  }
end:
  return $ret;
}
# --------------------------------------------------------------------------------- data_eli_izol_cr
# vytvoří z chlapa osobu a připíše informace o chlapských akcích (? a rok iniciace ?)
# upraví položku DUPLO.rozdily=10 (šedá) v předaném záznamu
# pokud je to poslední duplicita v rámci rodiny tak i v nadřazeném záznamu DUPLO
# faze=2 pro ruční a faze=1 pro automatické ztotožnění
function data_eli_izol_cr($iddr,$idd,$idc,$faze=2) { trace();
  $ret= (object)array('err'=>'');
  $flds= explode(',',"jmeno,prijmeni,sex,ulice,psc,obec,telefon,email,narozeni,rc_xxxx,note,nomail");
  $c= select("*","ezer_ys.chlapi","id_chlapi=$idc");
  $zmeny= array();
  foreach ($flds as $fld) {
    if ( $c->$fld ) {
      $zmeny[]= (object)array('fld'=>$fld,'op'=>'i','val'=>$c->$fld);
    }
  }
//                                                 debug($zmeny);
  // vložení do OSOBA,TVORI,RODINA
  $ido= ezer_qry("INSERT",'osoba',0,$zmeny);
  if ( !$ido ) { $ret->err= "ERROR: ".mysql_error(); goto end; }
  $zm_rodina= array(
    (object)array('fld'=>'nazev','op'=>'i','val'=>$c->prijmeni));
  $idr= ezer_qry("INSERT",'rodina',0,$zm_rodina);
  if ( !$idr ) { $ret->err= "ERROR: ".mysql_error(); goto end; }
  $zm_tvori= array(
    (object)array('fld'=>'id_osoba', 'op'=>'i','val'=>$ido),
    (object)array('fld'=>'id_rodina','op'=>'i','val'=>$idr),
    (object)array('fld'=>'role',     'op'=>'i','val'=>$c->sex==1?'a':'b')
  );
  $idt= ezer_qry("INSERT",'tvori',0,$zm_tvori);
  if ( !$idt ) { $ret->err= "ERROR: ".mysql_error(); goto end; }
  // vložení akcí a poznačení v DUPLO
  data_eli_dupl_cs($idd,$idc,$ido,$faze,true);
  // zápis o importu z chlapi do _track jako op=d (duplicita)
  $now= date("Y-m-d H:i:s");
  $user= $USER->abbr;
  query("INSERT INTO _track (kdy,kdo,kde,klic,fld,op,old,val)
        VALUES ('$now','$user','osoba',$ido,'','d','chlapi',$idc)");
  if ( $iddr ) {
    $n= select("COUNT(*)","duplo","idd=$iddr AND rozdily NOT IN (0,10)");
    if ( !$n ) { // poznač, že je sjednocena "rodina"
      query("UPDATE duplo SET rozdily=10,faze=$faze WHERE id_duplo=$iddr");
    }
  }
end:
  return $ret;
}
# --------------------------------------------------------------------------------- data_eli_dupl_cr
# osobě ztotožněné s chlapem připíše informace o chlapských akcích (? a rok iniciace ?)
# upraví položku DUPLO.rozdily=10 (šedá) v předaném záznamu
# pokud je to poslední duplicita v rámci rodiny tak i v nadřazeném záznamu DUPLO
# faze=2 pro ruční a faze=1 pro automatické ztotožnění
function data_eli_dupl_cr($iddr,$idd,$idc,$idt,$faze=2) { trace();
  $ido= select("id_osoba","tvori","id_tvori=$idt");
  $ret= data_eli_dupl_cs($idd,$idc,$ido,$faze);
  if ( !$ret->err ) {
    $n= select("COUNT(*)","duplo","idd=$iddr AND rozdily NOT IN (0,10)");
    if ( !$n ) {
      # poznač, že je sjednocena rodina
      query("UPDATE duplo SET rozdily=10,faze=$faze WHERE id_duplo=$iddr");
    }
  }
  return $ret;
}
# --------------------------------------------------------------------------------- data_eli_dupl_cs
# osobě ztotožněné s chlapem připíše informace o chlapských akcích //a rok iniciace
# upraví položku DUPLO.rozdily=10 (šedá) v předaném záznamu
# faze=2 pro ruční a faze=1 pro automatické ztotožnění
function data_eli_dupl_cs($idd,$idc,$ido,$faze=2,$jen_akce=false) { trace();
  global $USER;
  $ret= (object)array('err'=>'');
  $ok= 0;
  # převedení informací o účasti na chlapské akci do AKCE
  # 1/0=prihl/-   2/0=ucast/-  3=10 nedojel/nedojel    4/5-org/hosp   5/12=lektor/lektor
  $stupen_fce= array(1=>0,2=>0,3=>10,4=>5,5=>12);
  $qc= mysql_qry("
    SELECT idf,id_akce,id_ucast,stupen,u.pozn,iniciace
    FROM ezer_ys.chlapi AS c
    JOIN ezer_ys.ch_ucast AS u USING(id_chlapi)
    LEFT JOIN ch_fa AS fa ON tab='a' AND idc=u.id_akce
    WHERE id_chlapi=$idc
  ");
  if ( !$qc ) { $ret->err= "ERROR: ".mysql_error(); goto end; }
  while (($c= mysql_fetch_object($qc))) {
    $ch_akce= $c->id_akce;
    $akce= $c->idf;
    if ( !$akce ) { $ret->err= "ERROR: neexistující obraz akce $ch_akce"; goto end; }
    $fce= $stupen_fce[$c->stupen];
    $pozn= mysql_real_escape_string($c->pozn);
    query("INSERT INTO pobyt (id_akce,funkce,pouze,poznamka) VALUES ($akce,$fce,1,'$pozn') ");
    $idp= mysql_insert_id();
    query("INSERT INTO spolu (id_pobyt,id_osoba) VALUES ($idp,$ido) ");
    $ids= mysql_insert_id();
    # zápis vztahu id_ucast a id_pobyt
    query("INSERT INTO ch_fa (idc,idf,tab) VALUES ({$c->id_ucast},$idp,'p')");
//     # přenos informace o roku iniciace do OSOBA
//     query("UPDATE osoba SET iniciace={$c->iniciace} WHERE id_osoba=$ido");
  }
  # zápis vztahu id_osoba do id_chlapi
  query("INSERT INTO ch_fa (idc,idf,tab) VALUES ($idc,$ido,'o')");
  if ( $idd ) {
    # zápis do DUPLO
    query("UPDATE duplo SET rozdily=10,faze=$faze WHERE id_duplo=$idd");
    $m= mysql_affected_rows();
    if ( !$jen_akce ) {

      # zápis změněných údajů z DUPLO.chngs do osoba, jsou-li
      $chngs_s= select("chngs","duplo","id_duplo=$idd");
      $o= select("*","osoba","id_osoba=$ido");
      if ( $chngs_s ) {
        $chngs= json_decode($chngs_s);
  //                                                     debug($chngs);
        $zmeny= array();
        foreach ($chngs as $fld=>$chng) {
          $val= is_array($chng) ? $chng[0] : $chng;
          $old= $o->$fld;
          if ( $fld=='narozeni' ) $val= sql_date1($val,1);
          if ( $val != $old && isset($o->$fld) ) {
            $zmeny[]= (object)array('fld'=>$fld,'op'=>'u','val'=>$val,'old'=>$old);
          }
        }
  //                                                     debug($zmeny);
        // zápis o importu z chlapi do _track jako op=d (duplicita)
        $now= date("Y-m-d H:i:s");
        $user= $USER->abbr;
        query("INSERT INTO _track (kdy,kdo,kde,klic,fld,op,old,val)
              VALUES ('$now','$user','osoba',$ido,'','d','chlapi',$idc)");
        // promítnutí změn do OSOBA
        if ( count($zmeny) ) {
          ezer_qry("UPDATE",'osoba',$ido,$zmeny);
        }
      }
    }
  }
end:
  return $ret;
}
# 2. ezer_qry("UPDATE",$table,$x->key,$zmeny[,$key_id]);       -- oprava 1 záznamu
#     zmeny= [ zmena,...]
#     zmena= { fld:field, op:a|p|d|c, val:value, row:n }          -- pro chat
#          | { fld:field, op:u,   val:value, old:value }          -- pro opravu
#          | { fld:field, op:i,   val:value }                     -- pro vytvoření
# -------------------------------------------------------------------------------- data_eli_dupl_all
# pro mode=cs,cr ztožní osoby s chlapem a připíše informace o chlapských akcích pokud DUPLO.asi>=mez
# pro mode=sr ztotožní singly se čůeny rodin a připíše informace o  akcích
# upraví položky DUPLO.rozdily=10 (šedá) v záznamech
function data_eli_dupl_all($mode) { trace();
  $ret= (object)array('err'=>'');
  $mez= 48; // pro aspoň ulice+psc
  switch ($mode) {
  case 'sr':
    $qd= mysql_qry("
      SELECT id_duplo,id_tab1,id_tab2,idd FROM duplo
      WHERE faze=0 AND asi>=$mez AND tab1='s' AND id_tab1>0 AND tab2='t' AND id_tab2>0
    ");
    if ( !$qd ) { $ret->err= "ERROR: ".mysql_error(); goto end; }
    while (($d= mysql_fetch_object($qd))) {
      $ret0= data_eli_dupl_sr($d->idd,$d->id_duplo,$d->id_tab1,$d->id_tab2,1);
      $ret->err.= $ret0->err;
    }
    break;
  case 'cs':
    $qd= mysql_qry("
      SELECT id_duplo,id_tab1,id_tab2 FROM duplo
      WHERE faze=0 AND asi>=$mez AND tab1='c' AND id_tab1>0 AND tab2='o' AND id_tab2>0
    ");
    if ( !$qd ) { $ret->err= "ERROR: ".mysql_error(); goto end; }
    while (($d= mysql_fetch_object($qd))) {
      $ret0= data_eli_dupl_cs($d->id_duplo,$d->id_tab1,$d->id_tab2,1);
      $ret->err.= $ret0->err;
    }
    // a co se nepovedlo, vymažeme
    query("DELETE FROM duplo WHERE faze=0 AND asi<$mez AND tab1='c' AND tab2='o'");
    break;
  case 'cr':
    $qd= mysql_qry("
      SELECT id_duplo,id_tab1,id_tab2,idd FROM duplo
      WHERE faze=0 AND asi>=$mez AND tab1='c' AND id_tab1>0 AND tab2='t' AND id_tab2>0
    ");
    if ( !$qd ) { $ret->err= "ERROR: ".mysql_error(); goto end; }
    while (($d= mysql_fetch_object($qd))) {
      $ret0= data_eli_dupl_cr($d->idd,$d->id_duplo,$d->id_tab1,$d->id_tab2,1);
      $ret->err.= $ret0->err;
    }
    break;
  }
end:
  return $ret;
}
# ------------------------------------------------------------------------------------ data_eli_akce
# vytvoření akcí z CH_AKCE v AKCE  - $insert='insert' způsobí zápis
function data_eli_akce($insert='test') { trace();
  $html= 'již bylo provedeno';
//   $akces= array( // 1                 2      3             4          5          6
//     array( 1,  'GloTraCh 2011',     2200,'2011-05-19','2011-05-22',"Nesměř"  ,0),
//     array( 2,  'MROP 2011',         2900,'2011-09-10','2011-09-14',"Nesměř"  ,0),
//     array( 3,  'Sestup do Jordánu', 1500,'2011-09-15','2011-09-18',"Nesměř"  ,0),
//     array( 4,  'Závěr MROP 2011',   1000,'2011-09-13','2011-09-14',"Nesměř"  ,0),
//     array( 5,  'Křižanov 2012',     2200,'2012-05-24','2012-05-27',"Křižanov",0),
//     array( 6,  'MROP 2012',         2900,'2012-09-12','2012-09-16',"Nesměř"  ,0),
//     array( 7,  'Závěr MROP 2012',    400,'2012-09-15','2012-09-16',"Nesměř"  ,0),
//     array( 8,  'Cross 2012',        1700,'2012-09-15','2012-09-16',"?"       ,0),
//     array( 9,  'MROP 2013',         1500,'2013-09-11','2013-09-15',"Nesměř"  ,369),
//     array(10,  'Závěr MROP 2013',    400,'2013-09-14','2013-09-15',"Nesměř"  ,370)
//   );
//   // nazev=
//   foreach ($akces as $akce) {
//     $idch=  $akce[0];
//     $nazev= $akce[1];
//     $cena=  $akce[2];
//     $od=    $akce[3];
//     $do=    $akce[4];
//     $misto= $akce[5];
//     $idfa=  $akce[6];
//     $sql= "INSERT INTO akce (ma_cenu,cena,nazev,misto,datum_od,datum_do)
//                      VALUES (1,$cena,'$nazev','$misto','$od','$do')";
//     $html.= "<br>$sql";
//     if ( $insert=='insert' ) {
//       if ( !$idfa ) {
//         $res= mysql_qry($sql);
//         $idfa= mysql_insert_id();
//       }
//       $sql= "INSERT INTO ch_fa (idc,idf,tab) VALUES ($idch,$idfa,'a')";
//       $html.= "<br> - $sql";
//       $res= mysql_qry($sql);
//     }
//   }
  return $html;
}
# ----------------------------------------------------------------------------------- data_eli_track
# obnova _track ze zálohy - $insert='insert' způsobí zápis do _track
function data_eli_track($insert='test') { trace();
  $ret= (object)array('html'=>'hotovo');
//   function write_track($ids,$flds,$kdy) {
// //                                                         display("{$ids[0]->id_chlapi} / ".count($ids));
// //                                                         debug($ids,"write_track");
//     $ret= (object)array('html'=>'','sql'=>'');
//     $id_chlapi= $ids[0]->id_chlapi;
//     if ( count($ids)==1 ) {
//       // insert
//       $i= "";
//       foreach($flds as $fld) {
//         $val= mysql_real_escape_string($ids[0]->$fld);
//         if ( $val ) {
//           $i.= " $fld";
//           $ret->sql.= ", ('$kdy','ZMI','chlapi',$id_chlapi,'$fld','i','','$val')";
//         }
//       }
//       $ret->html.= "$id_chlapi - {$ids[0]->prijmeni} insert $i<br>";
//     }
//     else {
//       // update
//       $u= "";
//       foreach($flds as $fld) {
//         $old= mysql_real_escape_string($ids[1]->$fld);
//         $val= mysql_real_escape_string($ids[0]->$fld);
//         if ( $old!=$val ) {
//           $u.= " $fld";
//           $ret->sql.= ", ('$kdy','ZMI','chlapi',$id_chlapi,'$fld','u','$old','$val')";
//         }
//       }
//       $ret->html.= "$id_chlapi - {$ids[0]->prijmeni} update $u<br>";
//     }
//     return $ret;
//   }
//   $tabs= array(
//     "chlapi_130117","chlapi_130304","chlapi_130311","chlapi_130407","chlapi_130416","chlapi_130522",
//     "chlapi_130608","chlapi_130620","chlapi_130624","chlapi_130701","chlapi_130702",
//     "chlapi_130723",
//     "chlapi_130801",
//     "chlapi_130812","chlapi_130901","chlapi_130909",
//     "chlapi_130924","chlapi_131003",
//     "chlapi_131018","chlapi_131127"
//   );
//   // projití seznamu
//   for ($i= 0; $i<=count($tabs)-2; $i++) {
//     $a= $tabs[$i];
//     $b= $tabs[$i+1];
//     $kdy= "20".substr($b,7,2)."-".substr($b,9,2)."-".substr($b,11,2);
//     $ret->html.= "<hr>změny mezi $a a $b ($kdy)<hr>";
//     $fldss= "jmeno,prijmeni,sex,ulice,psc,obec,telefon,email,narozeni,rc_xxxx,pozn";
//     if ( $a>="chlapi_130723" && $b>="chlapi_130723" )
//       $fldss.= ",origin";
//     $flds= explode(',',$fldss);
//     $id= 0;
//     $ids= array();
//     $qc= mysql_qry("
//       SELECT MIN(tableName) as tableName,id_chlapi, $fldss FROM (
//         SELECT 'old' as tableName,id_chlapi, $fldss FROM obnova.$a AS a
//         UNION ALL
//         SELECT 'new' as tableName,id_chlapi, $fldss FROM obnova.$b as b
//       ) AS tmp
//       GROUP BY id_chlapi,$fldss
//       HAVING COUNT(*) = 1
//       ORDER BY id_chlapi,tableName
//     ");
//     if ( !$qc ) { $ret->html= "ERROR: ".mysql_error(); goto end; }
//     while (($c= mysql_fetch_object($qc))) {
//       if ( $c->id_chlapi==$id ) {
//         $ids[]= $c;
//       }
//       else {
//         if ( $id ) {
//           $rt= write_track($ids,$flds,$kdy);
//           $ret->html.= $rt->html;
//           $sql.= "\n".$rt->sql;
//         }
//         $id= $c->id_chlapi;
//         $ids= array($c);
//       }
//     }
//     if ( count($ids) ) {
//       $rt= write_track($ids,$flds,$kdy);
//       $ret->html.= $rt->html;
//       $sql.= "\n".$rt->sql;
//     }
//   }
//   // doplnění do _track
//   $sql= "INSERT INTO ezer_ys._track (kdy,kdo,kde,klic,fld,op,old,val) VALUES ".substr($sql,1).";";
//                                                         display($sql);
//   if ( $insert=='insert' ) {
//     $res= mysql_qry($sql);
//     $n= mysql_affected_rows();
//     $ret->html.= $res ? "<hr>do tabulky _track bylo přidáno $n záznamů"
//       : "<hr>zápis do tabulky _track selhal" ;
//   }
//   $ret->html.= "<hr>$sql";
// end:
  return $ret;
}
# ================================================================================================== DUPLICITY
# ---------------------------------------------------------------------------------- akce_data_single
# načte data OSOBA včetně záznamů v _track (pro chybějici spojku v TVORI)
function akce_data_single($ido) {  trace();
  $ret= (object)array();
  // načtení změn
  $chng_kdy= $chng_kdo= $chng_val= array();
  $zs= mysql_qry("
    SELECT fld,kdo,kdy,val,op
    FROM _track
    WHERE kde='osoba' AND klic=$ido
  ");
  while (($z= mysql_fetch_object($zs))) {
    $fld= $z->fld;
    $kdy= $z->kdy;
    $kdo= $z->kdo;
    $op=  $z->op;
    $val= $z->val;
    if ( !isset($chng_kdy[$fld]) || isset($chng_kdy[$fld]) && strcmp($chng_kdy[$fld],$kdy)<0 ) {
      $chng_kdy[$fld]= $kdy;
      $chng_kdo[$fld]= "$kdo/$op: ".sql_date1($kdy);
      $chng_val[$fld]= $val;
    }
  }
  $ret->chng= $chng_kdo;
  // načtení hodnot
  $os= mysql_qry("
    SELECT MAX(datum_od) AS _last,
      prijmeni,jmeno,sex,narozeni,rc_xxxx,psc,obec,ulice,email,telefon,o.note
    FROM osoba AS o
    LEFT JOIN spolu AS s USING(id_osoba)
    LEFT JOIN pobyt AS p USING(id_pobyt)
    LEFT JOIN akce AS a ON p.id_akce=a.id_duakce
    WHERE id_osoba=$ido GROUP BY id_osoba
  ");
  $o= mysql_fetch_object($os);
  foreach($o as $fld=>$val) {
    if ( $chng_kdy[$fld] && $chng_val[$fld]!=$val ) {
      $ret->diff[$fld]= $chng_val[$fld];
      $ret->chng[$fld]= "<span style='color:red'>{$ret->chng[$fld]}: {$chng_val[$fld]}</span>";
    }
    $ret->posledni= $o->_last;
  }
//                                                         debug($ret,"akce_data_single");
  return $ret;
}
# ---------------------------------------------------------------------------------- akce_data_osoba
# načte data OSOBA+TVORI včetně záznamů v _track
function akce_data_osoba($ido,$idr) {  trace();
  $ret= (object)array();
  // načtení změn
  $chng_kdy= $chng_kdo= $chng_val= array();
  $zs= mysql_qry("
    SELECT fld,kdo,kdy,val,op
    FROM _track
    WHERE kde='osoba' AND klic=$ido
  ");
  while (($z= mysql_fetch_object($zs))) {
    $fld= $z->fld;
    $kdy= $z->kdy;
    $kdo= $z->kdo;
    $op=  $z->op;
    $val= $z->val;
    if ( !isset($chng_kdy[$fld]) || isset($chng_kdy[$fld]) && strcmp($chng_kdy[$fld],$kdy)<0 ) {
      $chng_kdy[$fld]= $kdy;
      $chng_kdo[$fld]= "$kdo/$op: ".sql_date1($kdy);
      $chng_val[$fld]= $val;
    }
  }
  $ret->chng= $chng_kdo;
  // načtení hodnot
  $os= mysql_qry("
    /*SELECT *
    FROM osoba AS o
    JOIN tvori AS t USING(id_osoba)
    WHERE id_rodina=$idr AND id_osoba=$ido*/
    SELECT MAX(datum_od) AS _last,
      prijmeni,jmeno,sex,narozeni,rc_xxxx,psc,obec,ulice,email,telefon,o.note
    FROM osoba AS o
    LEFT JOIN spolu AS s USING(id_osoba)
    LEFT JOIN pobyt AS p USING(id_pobyt)
    LEFT JOIN akce AS a ON p.id_akce=a.id_duakce
    WHERE id_osoba=$ido GROUP BY id_osoba
  ");
  $o= mysql_fetch_object($os);
  foreach($o as $fld=>$val) {
    if ( $chng_kdy[$fld] && $chng_val[$fld]!=$val ) {
      $ret->diff[$fld]= $chng_val[$fld];
      $ret->chng[$fld]= "<span style='color:red'>{$ret->chng[$fld]}: {$chng_val[$fld]}</span>";
    }
  }
    $ret->posledni= $o->_last;
//                                                         debug($ret,"akce_data_osoba");
  return $ret;
}
# --------------------------------------------------------------------------------- akce_data_rodina
# načte data RODINA včetně záznamů v _track
function akce_data_rodina($idr) {  trace();
  $ret= (object)array();
  // načtení změn
  $chng_kdy= $chng_kdo= $chng_val= array();
  $zs= mysql_qry("
    SELECT fld,kdo,kdy,val,op
    FROM _track
    WHERE kde='rodina' AND klic=$idr
  ");
  while (($z= mysql_fetch_object($zs))) {
    $fld= $z->fld;
    $kdy= $z->kdy;
    $kdo= $z->kdo;
    $op=  $z->op;
    $val= $z->val;
    if ( !isset($chng_kdy[$fld]) || isset($chng_kdy[$fld]) && strcmp($chng_kdy[$fld],$kdy)<0 ) {
      $chng_kdy[$fld]= $kdy;
      $chng_kdo[$fld]= "$kdo/$op: ".sql_date1($kdy);
      $chng_val[$fld]= $val;
    }
  }
  $ret->chng= $chng_kdo;
  // načtení hodnot
  $os= mysql_qry("
    SELECT *
    FROM rodina AS o
    WHERE id_rodina=$idr
  ");
  $o= mysql_fetch_object($os);
  foreach($o as $fld=>$val) {
    if ( $chng_kdy[$fld] && $chng_val[$fld]!=$val ) {
      $ret->diff[$fld]= $chng_val[$fld];
      $ret->chng[$fld]= "<span style='color:red'>{$ret->chng[$fld]}: {$chng_val[$fld]}</span>";
//       $ret->chng[$fld].= ": {$chng_val[$fld]}";
    }
  }
//                                                         debug($ret,"akce_data_rodina");
  return $ret;
}
# --------------------------------------------------------------------------------- akce_data_chlapi
# načte data změn v CHLAPI ze záznamů v _track a také seznam akcí
function akce_data_chlapi($idc) {  trace();
  $ret= (object)array('chng'=>array(),'diff'=>array(),'iniciace'=>0,'posledni'=>0);
  // načtení změn
  $chng_kdy= $chng_kdo= $chng_val= array();
  $zs= mysql_qry("
    SELECT fld,kdo,op,kdy,val
    FROM ezer_ys._track
    WHERE kde='chlapi' AND klic=$idc
  ");
  while (($z= mysql_fetch_object($zs))) {
    $fld= $z->fld;
    $kdy= $z->kdy;
    $kdo= $z->kdo;
    $op=  $z->op;
    $val= $z->val;
    if ( !isset($chng_kdy[$fld]) || isset($chng_kdy[$fld]) && strcmp($chng_kdy[$fld],$kdy)<0 ) {
      $chng_kdy[$fld]= $kdy;
      $chng_kdo[$fld]= "$kdo/$op: ".sql_date1($kdy);
      $chng_val[$fld]= $val;
    }
  }
  $ret->chng= $chng_kdo;
  // načtení hodnot
  $cs= mysql_qry("
    SELECT id_chlapi,MAX(datum_od) AS _last,origin,iniciace,
      prijmeni,jmeno,sex,narozeni,rc_xxxx,psc,obec,ulice,email,telefon,note
    FROM ezer_ys.chlapi AS c
    LEFT JOIN ezer_ys.ch_ucast AS u USING(id_chlapi)
    LEFT JOIN ezer_ys.ch_akce AS a USING(id_akce)
    WHERE id_chlapi=$idc
    GROUP BY id_chlapi
  ");
  $c= mysql_fetch_object($cs);
  if ( $c ) {
    foreach($c as $fld=>$val) {
      if ( $chng_kdy[$fld] && $chng_val[$fld]!=$val ) {
        $ret->diff[$fld]= $chng_val[$fld];
        $ret->chng[$fld]= "<span style='color:red'>{$ret->chng[$fld]}: {$chng_val[$fld]}</span>";
      }
    }
    $ret->origin= $c->origin;
    $ret->iniciace= $c->iniciace;
    $ret->posledni= $c->_last;
  }
//                                                         debug($ret,"akce_data_chlapi");
  return $ret;
}
# -------------------------------------------------------------------------------- data_eli_corr_id2
# vymění v DUPLO id_tab2 (tab2 změnit nelze, předává se pro kontrolu),
# kontroluje zda tab2+id_tab2 bude jednoznačné, vynuluje chngs a rozdíly
function data_eli_corr_id2($idd,$tab2,$id2) { trace();
  $ret= (object)array('err'=>'');
  $xidd= select("idd","duplo","tab2='$tab2' AND id_tab2=$id2");
  if ( $xidd ) { $ret->err= "tato osoba je již použita pro id=$xidd"; goto end; }
  else {
    query("UPDATE duplo SET id_tab2=$id2 WHERE id_duplo=$idd");
  }
end:
  return $ret;
}
# ------------------------------------------------------------------------------------ data_eli_tack
# přidá do OSOBA,TVORI,RODINA jednočlenné "rodiny" z neduplicitních chlapů
function data_eli_tack() { trace();
  $ret= (object)array('html'=>'');
  $n= 0;
  $cs= mysql_qry("
    SELECT id_chlapi,prijmeni,jmeno
    FROM ezer_ys.chlapi
    LEFT JOIN duplo ON tab1='c' AND id_tab1=id_chlapi
    LEFT JOIN ch_fa ON idc=id_chlapi
    WHERE ISNULL(tab1) AND deleted='' AND ISNULL(idc) ORDER BY prijmeni
  ");
  while (($c= mysql_fetch_object($cs))) {
    data_eli_izol_cr(null,null,$c->id_chlapi,1);
    $ret->html.= "<b>{$c->prijmeni}</b> {$c->jmeno}, ";
    $n++;
//     $ret->html.= " -- STOP"; break;
  }
  $ret->html= "Přidáno $n chlapů jako triviální rodina<br><br>{$ret->html}";
  return $ret;
}
# --------------------------------------------------------------------------------- data_eli_singles
# přidá do TVORI,RODINA jednočlenné "rodiny" z některých dospělých singles
function data_eli_singles() { trace();
  $ret= (object)array('html'=>'');
  $n= 0;
  $ss= mysql_qry("
    SELECT id_osoba,prijmeni,jmeno,sex,YEAR(narozeni)
    FROM osoba
    LEFT JOIN tvori USING(id_osoba)
    WHERE ISNULL(id_tvori) AND deleted='' AND sex AND YEAR(narozeni)<=1995
    ORDER BY prijmeni
  ");
  while (($s= mysql_fetch_object($ss))) {
    $ido= $s->id_osoba;
    $role= $s->sex==1 ? 'a' : 'b';
    query("INSERT INTO rodina (origin) VALUES ('x') ");
    $idr= mysql_insert_id();
    query("INSERT INTO tvori (id_osoba,id_rodina,role) VALUES ($ido,$idr,'$role') ");
    $ret->html.= "<b>{$s->prijmeni}</b> {$s->jmeno}, ";
    $n++;
//     $ret->html.= " -- STOP"; break;
  }
  $ret->html= "Přidáno $n dosplěých singles jako triviální rodina (origin='x')<br><br>{$ret->html}";
  return $ret;
}
# ------------------------------------------------------------------------------------ data_eli_auto
# $typ = ct
function data_eli_auto($typ,$patt='') { trace();
  global $json, $data_eli_sum;
  // vytvoření $chngs s ohodnocením nejistoty (0 je jistota)
  //   1 - obě hodnoty jsou stejné (až na trim mezer)";
  //   2 - hodnoty se liší překlepem, použita první";
  //   3 - použita první hodnota, druhá chybí";
  //   4 - použita druhá hodnota, první chybí";
  //   5 - hodnoty jsou odlišné, asi duplicita, použita první - novější";
  //   6 - hodnoty jsou odlišné, asi duplicita, použita druhá - novější";
  //   7 - hodnoty jsou odlišné, nepoužita žádná";
  function last_dates_sr($s) { // ------------------------------------- last_dates_sr
    // single
    $s_track= select1("MAX(LEFT(kdy,10))","_track","kde='osoba' AND klic={$s->s_id_osoba}");
    $s_last= select1("MAX(datum_od)","osoba AS o
      LEFT JOIN spolu AS s USING(id_osoba) LEFT JOIN pobyt AS p USING(id_pobyt)
      LEFT JOIN akce AS a ON p.id_akce=a.id_duakce",
      "id_osoba={$s->s_id_osoba}");
    $s->_s_last= max($s_track,$s_last);
    // osoba - člen rodiny
    $o_track= select1("MAX(LEFT(kdy,10))","_track","kde='osoba' AND klic={$s->o_id_osoba}");
    $o_last= select1("MAX(datum_od)","osoba AS o
      LEFT JOIN spolu AS s USING(id_osoba) LEFT JOIN pobyt AS p USING(id_pobyt)
      LEFT JOIN akce AS a ON p.id_akce=a.id_duakce",
      "id_osoba={$s->o_id_osoba}");
    $s->_o_last= max($o_track,$o_last);
//                   display("$s_track $s_last {$s->_s_last} $o_track $o_last {$s->_o_last}");
  }
  function last_dates_co($c) { // ------------------------------------- last_dates_co
    // chlapi
    $c_track= select1("MAX(LEFT(kdy,10))","ezer_ys._track","kde='chlapi' AND klic={$c->id_chlapi}");
    $c_last= select1("MAX(datum_od)","ezer_ys.chlapi AS c
      LEFT JOIN ezer_ys.ch_ucast AS u USING(id_chlapi) LEFT JOIN ezer_ys.ch_akce AS a USING(id_akce)",
      "id_chlapi={$c->id_chlapi}");
    $c->_c_last= max($c_track,$c_last);
    // osoba
    $o_track= select1("MAX(LEFT(kdy,10))","_track","kde='osoba' AND klic={$c->id_osoba}");
    $o_last= select1("MAX(datum_od)","osoba AS o
      LEFT JOIN spolu AS s USING(id_osoba) LEFT JOIN pobyt AS p USING(id_pobyt)
      LEFT JOIN akce AS a ON p.id_akce=a.id_duakce",
      "id_osoba={$c->id_osoba}");
    $c->_o_last= max($o_track,$o_last);
//                   display("$c_track $c_last {$c->_c_last} $o_track $o_last {$c->_o_last}");
  }
  function find_track($tab,$id,$fld,$val) { // ------------------------ find_track
    $kdy= '';
    $db= $tab=='chlapi' ? 'ezer_ys.' : '';
    $val= mysql_real_escape_string($val);
    $kdyval= select1("MAX(CONCAT(kdy,val))","{$db}_track",
      "kde='$tab' AND fld='$fld' AND klic=$id AND val='$val'");
    $kdy= $kdyval ? substr($kdyval,0,19) : '';
//                                 if ( $id==9 || $id==1262 )
//                                 display("kde='$tab' AND fld='$fld' AND klic=$id: $kdyval");
    return $kdy;
  }
  function make_chngs_so($c,&$max_kod) { // --------------------------- make_chngs_so
    global $data_eli_sum;
    // definice polí osoba a kódu polí s odhady
    $flds= explode(',',"jmeno,prijmeni,sex,ulice,psc,obec,telefon,email,narozeni,rc_xxxx,note,role,nomail,iniciace");
    $spec= array('telefon'=>2048,'email'=>1024,'narozeni'=>128,'obec'=>64,'ulice'=>32);
    $copy= explode(',',"role");
    $asi= $c->_asi;
    $xchngs= (object)array();
    $chngs= '{'; $del= '';
    foreach ($flds as $f) {
      // přímá kopie zobrazovaných údajů
      if ( in_array($f,$copy) ) {
        $chngs.= "$del\"$f\":\"{$c->$f}\"";
        $xchngs->$f= $c->$f;
        $del= ',';
      }
      else {
        // zkoumání rozdílů
        $sf= "s_$f";
        $of= "o_$f";
        $kod= 0;
        $telx= false;
        if ( trim($c->$sf)==trim($c->$of)                                                          # 1  =
          || ( $f=='telefon' && $telx=(str_replace($c->$sf,' ','')==str_replace($c->$of,' ','')) )) {
          // stejné
          $kod= 1;
          if ( $telx && strpos($c->$of,' ')!==false ) {
            // raději telefon s mezerami
            $chngs.= "$del\"$f\":\"".trim($c->$of)."\"";
            $xchngs->$f= trim($c->$of);
          }
          else {
            $chngs.= "$del\"$f\":\"".trim($c->$sf)."\"";
            $xchngs->$f= trim($c->$sf);
          }
          $data_eli_sum[$kod]++;
        }
        elseif ( trim($c->$of)==''     && trim($c->$sf)!=''                                        # 3  c
              || $c->$of==0            && $c->$sf!=0
              || $c->$of=='0000-00-00' && $c->$sf!='0000-00-00' ) {
          // jen chlapi
          $kod= 3;
          $val= trim($c->$sf);
          $chngs.= "$del\"$f\":[\"$val\",$kod]";
          $xchngs->$f= array($val,$kod);
          $data_eli_sum[$kod]++;
        }
        elseif ( trim($c->$sf)==''     && trim($c->$of)!=''                                        # 4  o
              || $c->$sf==0            && $c->$of!=0
              || $c->$sf=='0000-00-00' && $c->$of!='0000-00-00' ) {
          // jen osoba
          $kod= 4;
          $val= trim($c->$of);
          $chngs.= "$del\"$f\":[\"$val\",$kod]";
          $xchngs->$f= array($val,$kod);
          $data_eli_sum[$kod]++;
        }
        else {
          $strack= find_track('osoba',$c->s_id_osoba,$f,$c->$sf);
          $otrack= find_track('osoba',$c->o_id_osoba,$f,$c->$of);
          $val= trim($c->$sf);
          if ( $otrack && $strack ) {                                                              # 5,6  _track
            // lze porovnat datum vložení údaje
            $kod= strcmp($strack,$otrack)>=0 ? 5 : 6;
            $chngs.= "$del\"$f\":[\"".($kod==7 ? "" : $val)."\",$kod]";
            $xchngs->$f= array(($kod==7 ? "" : $val),$kod);
            $data_eli_sum[$kod]++;
          }
          elseif ( isset($spec[$f]) && ($asi & $spec[$f]) ) {                                      # 2  ~
            // jde o překlep
            $kod= 2;
            $chngs.= "$del\"$f\":[\"$val\",$kod]";
            $xchngs->$f= array($val,$kod);
            $data_eli_sum[$kod]++;
          }
          else {                                                                                   # 7  x
            // údaje se liší
            $kod= 7;
            $chngs.= "$del\"$f\":[\"$val\",$kod]";
            $xchngs->$f= array($val,$kod);
            $data_eli_sum[$kod]++;
          }
        }
      }
      $del= ',';
      $max_kod= max($max_kod,$kod);
    }
    $chngs.= '}';
    $chngs= mysql_real_escape_string($chngs);
//     $chngs= json_encode($xchngs);
//                                                          display($chngs);
    return $chngs;
  }
  function make_chngs_co($c,&$max_kod) { // --------------------------- make_chngs_co
    global $data_eli_sum;
    // definice polí osoba a kódu polí s odhady
    $flds= explode(',',"jmeno,prijmeni,sex,ulice,psc,obec,telefon,email,narozeni,rc_xxxx,note,iniciace");
    $spec= array('telefon'=>2048,'email'=>1024,'narozeni'=>128,'obec'=>64,'ulice'=>32);
    $copy= explode(',',"role,nomail");
    $asi= $c->_asi;
//                                                         debug($c);
    $xchngs= (object)array();
    $chngs= '{'; $del= '';
    // odstranění variabilního symbolu z chlapi.note
//                                                         display("-- {$c->c_prijmeni}:{$c->c_note}");
    if ( preg_match("/^VS:\s\d{6}\s*(.*)$/m",$c->c_note,$m) ) {
//                                                         debug($m,$c->c_note);
      $c->c_note= $m[1];
    }
    foreach ($flds as $f) {
      // přímá kopie zobrazovaných údajů
      if ( in_array($f,$copy) ) {
        $chngs.= "$del\"$f\":\"{$c->$f}\"";
        $xchngs->$f= $c->$f;
        $del= ',';
      }
      else {
        // zkoumání rozdílů
        $cf= "c_$f";
        $of= "o_$f";
        $kod= 0;
        $telx= false;
        if ( trim($c->$cf)==trim($c->$of)                                                          # 1  =
          || ( $f=='telefon' && $telx=(str_replace($c->$cf,' ','')==str_replace($c->$of,' ','')) )) {
          // stejné
          $kod= 1;
          if ( $telx && strpos($c->$of,' ')!==false ) {
            // raději telefon s mezerami
            $chngs.= "$del\"$f\":\"".trim($c->$of)."\"";
            $xchngs->$f= trim($c->$of);
          }
          else {
            $chngs.= "$del\"$f\":\"".trim($c->$cf)."\"";
            $xchngs->$f= trim($c->$cf);
          }
          $data_eli_sum[$kod]++;
        }
        elseif ( trim($c->$of)==''     && trim($c->$cf)!=''                                        # 3  c
              || $c->$of==0            && $c->$cf!=0
              || $c->$of=='0000-00-00' && $c->$cf!='0000-00-00' ) {
          // jen chlapi
          $kod= 3;
          $chngs.= "$del\"$f\":[\"".trim($c->$cf)."\",$kod]";
          $xchngs->$f= trim($c->$cf);
          $data_eli_sum[$kod]++;
        }
        elseif ( trim($c->$cf)==''     && trim($c->$of)!=''                                        # 4  o
              || $c->$cf==0            && $c->$of!=0
              || $c->$cf=='0000-00-00' && $c->$of!='0000-00-00' ) {
          // jen osoba
          $kod= 4;
          $chngs.= "$del\"$f\":[\"".trim($c->$of)."\",$kod]";
          $xchngs->$f= trim($c->$of);
          $data_eli_sum[$kod]++;
        }
        else {
//           $ctrack= find_track('chlapi',$c->id_chlapi,$f=='note'?'pozn':$f,$c->$cf);
          $ctrack= find_track('chlapi',$c->id_chlapi,$f,$c->$cf);
          $otrack= find_track('osoba',$c->id_osoba,$f,$c->$of);
          $val= trim($c->$cf);
          if ( $otrack && $ctrack ) {                                                            # 5,6  _track
            // lze porovnat datum vložení údaje
            $kod= strcmp($ctrack,$otrack)>=0 ? 5 : 6;
            $chngs.= "$del\"$f\":[\"".($kod==7 ? "" : $val)."\",$kod]";
            $xchngs->$f= array(($kod==7 ? "" : $val),$kod);
            $data_eli_sum[$kod]++;
          }
          elseif ( isset($spec[$f]) && ($asi & $spec[$f]) ) {                                      # 2  ~
            // jde o překlep
            $kod= 2;
            $chngs.= "$del\"$f\":[\"".$val."\",$kod]";
            $xchngs->$f= array($val,$kod);
            $data_eli_sum[$kod]++;
          }
          else {                                                                                   # 7  x
            // údaje se liší
            $kod= 7;
            $chngs.= "$del\"$f\":[\"".$val."\",$kod]";
            $xchngs->$f= array($val,$kod);
            $data_eli_sum[$kod]++;
          }
        }
      }
      $del= ',';
      $max_kod= max($max_kod,$kod);
    }
    $chngs.= '}';
    $chngs= mysql_real_escape_string($chngs);
//                                                         debug($xchngs);
//     $chngs= json_encode($xchngs);
//                                                          display($chngs);
    return $chngs;
  }
  function make_query_sr($cond) { // -------------------------------- make_query_sr
    return "
      SELECT * FROM
      ( SELECT
        -- technické
        s.id_osoba AS s_id_osoba,o.id_osoba AS o_id_osoba,t.id_tvori,t.id_rodina,nazev,
          ( IF(o.telefon!='' AND levenshtein(o.telefon,s.telefon)<=3,2048,0)
          + IF(o.email!='' AND s.email!='' AND (
              FIND_IN_SET(o.email,s.email) OR FIND_IN_SET(s.email,o.email) OR
                levenshtein(s.email,o.email)<=3),1024,0)
          + IF(o.narozeni!='0000-00-00' AND s.narozeni=o.narozeni,512,0)
          + IF(o.narozeni!='0000-00-00' AND YEAR(s.narozeni)=YEAR(o.narozeni),128,0)
          + IF(o.obec!='' AND s.obec!='' AND levenshtein(o.obec,s.obec)<=4,64,0)
          + IF(o.ulice!='' AND s.ulice!='' AND levenshtein(o.ulice,s.ulice)<=6,32,0)
          + IF(o.psc!='' AND o.psc=s.psc,16,0)
          ) AS _asi,
          CONCAT(
            IF(o.telefon!='' AND levenshtein(o.telefon,s.telefon)<=3,'T',''),
            IF(o.email!='' AND s.email!='' AND (
              FIND_IN_SET(o.email,s.email) OR FIND_IN_SET(s.email,o.email) OR
                levenshtein(s.email,o.email)<=3),'E',''),
            IF(o.narozeni!='0000-00-00' AND s.narozeni=o.narozeni,'N',
              IF(o.narozeni!='0000-00-00' AND YEAR(s.narozeni)=YEAR(o.narozeni),'Y','')),
            IF(o.obec!='' AND s.obec!='' AND levenshtein(o.obec,s.obec)<=4,'O',''),
            IF(o.ulice!='' AND s.ulice!='' AND levenshtein(o.ulice,s.ulice)<=6,'U',''),
            IF(o.psc!='' AND o.psc=s.psc,'P','')
          ) AS _x,
        -- OSOBA jako člen rodiny
        o.jmeno AS o_jmeno,o.prijmeni AS o_prijmeni,o.sex AS o_sex,o.ulice AS o_ulice,
        o.psc AS o_psc,o.obec AS o_obec,o.telefon AS o_telefon,o.email AS o_email,
        o.narozeni AS o_narozeni,o.rc_xxxx AS o_rc_xxxx,o.note AS o_note,o.origin AS o_origin,
        o.rodne AS o_rodne,o.fotka AS o_fotka,o.dieta AS o_dieta,o.stat AS o_stat,
        o.nomail AS o_nomail,o.uvitano AS o_uvitano,o.historie AS o_historie,o.umrti AS o_umrti,
        o.obcanka AS o_obcanka,o.vzdelani AS o_vzdelani,o.zamest AS o_zamest,
        o.zajmy AS o_zajmy,o.jazyk AS o_jazyk,o.cirkev AS o_cirkev,o.aktivita AS o_aktivita,
        o.clen AS o_clen,o.iniciace AS o_iniciace,
        -- OSOBA jako single
        s.jmeno AS s_jmeno,s.prijmeni AS s_prijmeni,s.sex AS s_sex,s.ulice AS s_ulice,
        s.psc AS s_psc,s.obec AS s_obec,s.telefon AS s_telefon,s.email AS s_email,
        s.narozeni AS s_narozeni,s.rc_xxxx AS s_rc_xxxx,s.note AS s_note,s.origin AS s_origin,
        s.rodne AS s_rodne,s.fotka AS s_fotka,s.dieta AS s_dieta,s.stat AS s_stat,
        s.nomail AS s_nomail,s.uvitano AS s_uvitano,s.historie AS s_historie,s.umrti AS s_umrti,
        s.obcanka AS s_obcanka,s.vzdelani AS s_vzdelani,s.zamest AS s_zamest,
        s.zajmy AS s_zajmy,s.jazyk AS s_jazyk,s.cirkev AS s_cirkev,s.aktivita AS s_aktivita,
        s.clen AS s_clen,s.iniciace AS s_iniciace,
        -- jen kopírované
        t.role
        FROM osoba AS s
        LEFT JOIN tvori AS st ON st.id_osoba=s.id_osoba
        LEFT JOIN osoba AS o ON LEFT(s.prijmeni,4)=LEFT(o.prijmeni,4) AND
          levenshtein(s.prijmeni,o.prijmeni)<=1 AND (s.jmeno RLIKE o.jmeno OR o.jmeno RLIKE s.jmeno)
        LEFT JOIN tvori AS t ON t.id_osoba=o.id_osoba
        LEFT JOIN rodina AS r ON r.id_rodina=t.id_rodina
        LEFT JOIN duplo AS d ON tab1='s' AND id_tab1=s.id_osoba
        WHERE ISNULL(st.id_tvori) AND t.id_tvori AND
          ISNULL(id_duplo) AND s.deleted='' AND o.deleted='' AND $cond
        ORDER BY _asi DESC
      ) AS _trick
      GROUP BY s_id_osoba;
    ";
  }
  function make_query_ct($cond) { // -------------------------------- make_query_ct
    return "
      SELECT * FROM
      ( SELECT
        -- stejné v obou tabulkách
        o.jmeno AS o_jmeno,o.prijmeni AS o_prijmeni,o.sex AS o_sex,o.ulice AS o_ulice,
        o.psc AS o_psc,o.obec AS o_obec,o.telefon AS o_telefon,o.email AS o_email,
        o.narozeni AS o_narozeni,o.rc_xxxx AS o_rc_xxxx,o.note AS o_note,o.origin AS o_origin,
        c.jmeno AS c_jmeno,c.prijmeni AS c_prijmeni,c.sex AS c_sex,c.ulice AS c_ulice,
        c.psc AS c_psc,c.obec AS c_obec,c.telefon AS c_telefon,c.email AS c_email,
        c.narozeni AS c_narozeni,c.rc_xxxx AS c_rc_xxxx,c.note AS c_note,c.origin AS c_origin,
        c.iniciace AS c_iniciace,
        -- jen v OSOBA
        o.rodne AS o_rodne,o.fotka AS o_fotka,o.dieta AS o_dieta,o.stat AS o_stat,
        o.nomail AS o_nomail,o.uvitano AS o_uvitano,o.historie AS o_historie,o.umrti AS o_umrti,
        o.obcanka AS o_obcanka,o.vzdelani AS o_vzdelani,o.zamest AS o_zamest,
        o.zajmy AS o_zajmy,o.jazyk AS o_jazyk,o.cirkev AS o_cirkev,o.aktivita AS o_aktivita,o.clen AS o_clen,
        o.iniciace AS o_iniciace,
        -- jen kopírované
        role,nomail,
        -- technické
        id_chlapi,id_osoba,id_tvori,id_rodina,nazev,
          ( IF(c.telefon!='' AND levenshtein(c.telefon,o.telefon)<=3,2048,0)
          + IF(c.email!='' AND o.email!='' AND (
              FIND_IN_SET(c.email,o.email) OR FIND_IN_SET(o.email,c.email) OR
                levenshtein(o.email,c.email)<=3),1024,0)
          + IF(c.narozeni!='0000-00-00' AND o.narozeni=c.narozeni,512,0)
          + IF(c.narozeni!='0000-00-00' AND YEAR(o.narozeni)=YEAR(c.narozeni),128,0)
          + IF(c.obec!='' AND o.obec!='' AND levenshtein(c.obec,o.obec)<=4,64,0)
          + IF(c.ulice!='' AND o.ulice!='' AND levenshtein(c.ulice,o.ulice)<=6,32,0)
          + IF(c.psc!='' AND c.psc=o.psc,16,0)
          ) AS _asi,
          CONCAT(
            IF(c.telefon!='' AND levenshtein(c.telefon,o.telefon)<=3,'T',''),
            IF(c.email!='' AND o.email!='' AND (
              FIND_IN_SET(c.email,o.email) OR FIND_IN_SET(o.email,c.email) OR
                levenshtein(o.email,c.email)<=3),'E',''),
            IF(c.narozeni!='0000-00-00' AND o.narozeni=c.narozeni,'N',
              IF(c.narozeni!='0000-00-00' AND YEAR(o.narozeni)=YEAR(c.narozeni),'Y','')),
            IF(c.obec!='' AND o.obec!='' AND levenshtein(c.obec,o.obec)<=4,'O',''),
            IF(c.ulice!='' AND o.ulice!='' AND levenshtein(c.ulice,o.ulice)<=6,'U',''),
            IF(c.psc!='' AND c.psc=o.psc,'P','')
          ) AS _x
        FROM ezer_ys.chlapi AS c
        LEFT JOIN osoba AS o ON LEFT(c.prijmeni,4)=LEFT(o.prijmeni,4) AND
          levenshtein(c.prijmeni,o.prijmeni)<=1 AND (c.jmeno RLIKE o.jmeno OR o.jmeno RLIKE c.jmeno)
        LEFT JOIN tvori AS t USING(id_osoba)
        LEFT JOIN rodina AS r USING(id_rodina)
        LEFT JOIN duplo AS d ON tab1='c' AND id_tab1=c.id_chlapi
        WHERE ISNULL(id_duplo) AND c.deleted='' AND o.deleted='' AND $cond
          /*AND id_chlapi NOT IN (740,749,750,751,756,758,817,779,789,790,792,797,798,799,813,825) neiniciovaní jen na Cross */
          /*AND id_chlapi NOT IN (614,620,622,629,630,646,653,656,671,673,679,686,691,694,703,727,731,737,739,742,757,765,783,805,807,812,816,830,836,863,867,869,875,878,886,895,910,916,931,935,944,948,952,963,966,967,971) -- neiniciovaní bez akce */
                                        /*AND LEFT(c.origin,7)='ezer_ys'*/
        ORDER BY _asi DESC
      ) AS _trick
      GROUP BY id_chlapi;
    ";
  }
  function make_query_c($cond) { // -------------------------------- make_query_c
    return "
      SELECT
        id_chlapi,c.jmeno,c.prijmeni,c.iniciace
        FROM ezer_ys.chlapi AS c
        LEFT JOIN osoba AS o ON LEFT(c.prijmeni,4)=LEFT(o.prijmeni,4) AND
          levenshtein(c.prijmeni,o.prijmeni)<=1 AND (c.jmeno RLIKE o.jmeno OR o.jmeno RLIKE c.jmeno)
        LEFT JOIN duplo AS d ON tab1='c' AND id_tab1=c.id_chlapi
        WHERE ISNULL(id_duplo) AND c.deleted='' AND (ISNULL(id_osoba) OR o.deleted!='')
          AND $cond
        ORDER BY c.prijmeni
    ";
  }
  $ret= (object)array('html'=>'hotovo');
  $data_eli_sum= array(0,0,0,0,0,0,0,0,0,0,0);
  $data_eli_max= array(0,0,0,0,0,0,0,0,0,0,0);
  $m= $n= 0;
  // zjištění podobných chlapů a osob
  switch ($typ) {
  case 'sr': // -------------------------------------------------------- single - rodina
    $qs= mysql_qry(make_query_sr("s.prijmeni RLIKE '^$patt'"));
    if ( !$qs ) { $ret->html= "ERROR: ".mysql_error(); goto end; }
    while (($s= mysql_fetch_object($qs))) {
      $asi= $s->_asi;
      $x= $s->_x;
      $ids= $s->s_id_osoba;
      $pjs= mysql_real_escape_string("{$s->s_prijmeni} [$x] {$s->s_jmeno}");
      $idt= $s->id_tvori;
      $idr= $s->id_rodina;
      $jmr= $s->nazev;
      $s->s_narozeni= sql_date1($s->s_narozeni);
      $s->o_narozeni= sql_date1($s->o_narozeni);
      last_dates_sr($s);        // přepočítej data poslední práce se záznamem
//                                                                 debug($s,"$typ");
      $rozdily= 0;
      $chngs= make_chngs_so($s,$rozdily);
      $rzd= $asi<512 ? 8 : $rozdily;
      // vložení nebo nalezení rodiny
      $idd= select("id_duplo","duplo","tab1='s' AND tab2='r' AND id_tab2='$idr'");
      if ( !$idd ) {
        // vložení nalezené rodiny $idr
        $rr= query("INSERT INTO duplo (znacka,rozdily,asi,tab1,tab2,id_tab2)
                    VALUES (\"$jmr\",$rzd,-1,'s','r',$idr)");
        if ( !$rr ) { $ret->html= "ERROR: ".mysql_error(); goto end; }
        $idd= mysql_insert_id();
      }
      // vložení singla
      $ri= query("INSERT INTO duplo (idd,znacka,rozdily,asi,faze,tab1,id_tab1,last1,tab2,id_tab2,last2,chngs)
             VALUES ($idd,'$pjs',$rzd,$asi,1,'s',$ids,'{$s->_s_last}','t',$idt,'{$s->_o_last}','$chngs')");
      if ( !$ri ) { $ret->html= "ERROR: ".mysql_error(); goto end; }
      $n++;
      $m++;
      $data_eli_max[9]++;
    }
    break;
  case 'cs': // -------------------------------------------------------- chlap - single
    $qc= mysql_qry(make_query_ct("c.prijmeni RLIKE '^$patt' AND ISNULL(id_rodina) AND id_osoba"));
    while (($c= mysql_fetch_object($qc))) {
//                                                                 debug($c,"$typ");
      $asi= $c->_asi;
      $x= $c->_x;
      $idc= $c->id_chlapi;
      $ido= $c->id_osoba;
      $c->o_narozeni= sql_date1($c->o_narozeni);
      $c->c_narozeni= sql_date1($c->c_narozeni);
      // rodina nenalezena ale osoba ano => rozdíly=9 (fialovy)
      last_dates_co($c);        // přepočítej data poslední práce se záznamem
      $rozdily= 0;
      $chngs= make_chngs_co($c,$rozdily);
      $rzd= $asi<512 ? 8 : $rozdily;
      $pj= mysql_real_escape_string("{$c->c_prijmeni} [$x] {$c->c_jmeno}");
      query("INSERT INTO duplo (idd,znacka,rozdily,asi,faze,tab1,id_tab1,last1,tab2,id_tab2,last2,chngs)
             VALUES (0,'$pj',$rzd,$asi,1,'c',$idc,'{$c->_c_last}','o',$ido,'{$c->_o_last}','$chngs')");
      $n++;
      $m++;
      $data_eli_max[9]++;
    }
    break;
  case 'c-': // -------------------------------------------------------- chlap - neznámý
    $qc= mysql_qry(make_query_c("c.prijmeni RLIKE '^$patt'"));
    if ( !$qc ) { $ret->html= "ERROR: ".mysql_error(); goto end; }
    while (($c= mysql_fetch_object($qc))) {
      $idc= $c->id_chlapi;
      $jmr= mysql_real_escape_string("{$c->prijmeni} {$c->jmeno}");
      mysql_qry("
        INSERT INTO duplo (znacka,rozdily,asi,tab1,id_tab1,tab2)
        VALUES ('$jmr','*',9999,'c',$idc,'-')");
      $idd= mysql_insert_id();
      $n++;
      // vytvoření osoby z izolovaného chlapa, včetně zápisu do CH_FA
      data_eli_izol_cr(0,$idd,$idc);
    }
    break;
  case 'ct': // -------------------------------------------------------- chlap - rodina
    $qc= mysql_qry(make_query_ct("c.prijmeni RLIKE '^$patt' AND id_rodina AND id_osoba"));
    while (($c= mysql_fetch_object($qc))) {
      $c->o_narozeni= sql_date1($c->o_narozeni);
      $c->c_narozeni= sql_date1($c->c_narozeni);
      # r,s - rodina nebo single
      last_dates_co($c);        // přepočítej data poslední práce se záznamem
      $asi= $c->_asi;
      $x= $c->_x;
      $idc= $c->id_chlapi;
      $jmo= $c->o_jmeno;
      $idt= $c->id_tvori;
      $idr= $c->id_rodina;
      $jmr= $c->nazev;
//                                                         display("SELECT ... {$c->c_prijmeni} $jmr (rodina $idr) ");
      // vložení nebo nalezení rodiny
      $idd= select("id_duplo","duplo","tab1='-' AND tab2='r' AND id_tab2='$idr'");
//                                                       display("select(...$idr)=$idd");
      if ( $idd ) {
        // rodina již byla vložena - přidej $idt správnému členu
        $rozdily= 0;
        $chngs= make_chngs_co($c,$rozdily);
        // rozdíl při neshodě E,T,N je nesnižitelný
        $rzd= $asi<512 ? 8 : "IF($rozdily>rozdily,$rozdily,rozdily)";
        if ( $asi<32 ) { // pokud se neshoduje ani ulice, necháme formulář prázdný
          $chngs= '';
        }
        mysql_qry("
          UPDATE duplo SET tab1='c',id_tab1=$idc,znacka=CONCAT('{$jmo}[$x] / ',znacka),
            chngs='$chngs',rozdily=$rzd,faze=1
          WHERE tab1='-' AND tab2='r' AND id_tab2=$idt
        ");
      }
      else {
      // vložení rodiny $idr a jejich členů - poznač $idt a vkopíruj chngs
        $rzd= $asi<512 ? 8 : 0;   // rozdíl při neshodě E,T,N je nesnižitelný
        mysql_qry("
          INSERT INTO duplo (znacka,rozdily,asi,tab1,tab2,id_tab2)
          VALUES (\"$jmr\",$rzd,-1,'-','r',$idr)");
        $idd= mysql_insert_id();
        $n++;
        $max_rozdily= 0;
        $qt= mysql_qry("
          SELECT *
          FROM tvori AS t JOIN osoba AS o USING (id_osoba)
          WHERE id_rodina=$idr
        ");
        while (($t= mysql_fetch_object($qt))) {
          $idrt= $t->id_tvori;
          $jmro= "{$t->jmeno}({$t->role})";
          $chngs= "";
          $rozdily= 0;
          if ( $idt == $idrt ) {
            $chngs= make_chngs_co($c,$rozdily);
            $max_rozdily= max($max_rozdily,$rozdily);
            if ( $asi<32 ) { // pokud se neshoduje ani ulice, necháme formulář prázdný
              $chngs= '';
            }
            $data_eli_max[$max_rozdily]++;
            $qi= "INSERT INTO duplo (znacka,idd,rozdily,asi,faze,tab1,id_tab1,last1,tab2,id_tab2,last2,chngs)
                  VALUES ('{$jmo}[$x] / $jmro',$idd,$rozdily,$asi,1,'c',$idc,'{$c->_c_last}',
                                                                  't',$idrt,'{$c->_o_last}','$chngs')";
          }
          else {
            // jen přenesení osoby
            $qi= "INSERT INTO duplo (znacka,idd,rozdily,asi,tab1,tab2,id_tab2)
                  VaLUES ('$jmro',$idd,0,-2,'-','r',$idrt)";
          }
          mysql_qry($qi);
          $m++;
        }
        // úprava maximálního rozdílu v rodině
        mysql_qry("
          UPDATE duplo SET rozdily=IF($max_rozdily>rozdily,$max_rozdily,rozdily)
          WHERE id_duplo=$idd
        ");
      }
//       break;
    }
    break;
  }
  $ret->html= "Bylo vloženo $n rodin s $m členy k eliminaci duplicit";
  $ret->html.= "<br><br><b>počet rozdílů v osobách (číslo je maximální rozdíl v jejich údajích)</b>";
  $ret->html.= "<br>{$data_eli_max[0]} x 0 = chyba ";
  $ret->html.= "<br>{$data_eli_max[1]} x 1 - zelená ";
  $ret->html.= "<br>{$data_eli_max[2]} x 2 - modrá ";
  $ret->html.= "<br>{$data_eli_max[3]} x 3 - žlutá ";
  $ret->html.= "<br>{$data_eli_max[4]} x 4 - žlutá ";
  $ret->html.= "<br>{$data_eli_max[5]} x 5 - oranžová ";
  $ret->html.= "<br>{$data_eli_max[6]} x 6 - oranžová ";
  $ret->html.= "<br>{$data_eli_max[7]} x 7 - růžová ";
  $ret->html.= "<br>{$data_eli_max[8]} x 8 - červená = nejistota identity";
  $ret->html.= "<br>{$data_eli_max[9]} x 9 - fialová = bez rodiny";
  $ret->html.= "<br><br><b>počet rozdílů v údajích</b>";
  $ret->html.= "<br>{$data_eli_sum[1]} x 1 - obě hodnoty jsou stejné (až na trim mezer)";
  $ret->html.= "<br>{$data_eli_sum[2]} x 2 - hodnoty se liší překlepem, použita první";
  $ret->html.= "<br>{$data_eli_sum[3]} x 3 - použita první hodnota, druhá chybí";
  $ret->html.= "<br>{$data_eli_sum[4]} x 4 - použita druhá hodnota, první chybí";
  $ret->html.= "<br>{$data_eli_sum[5]} x 5 - hodnoty jsou odlišné, asi duplicita, použita první - novější";
  $ret->html.= "<br>{$data_eli_sum[6]} x 6 - hodnoty jsou odlišné, asi duplicita, použita druhá - novější";
  $ret->html.= "<br>{$data_eli_sum[7]} x 7 - hodnoty jsou odlišné, nepoužita žádná";
end:
//                                                         debug($ret,"data_eli_auto");
  return $ret;
}
# ----------------------------------------------------------------------------------- akce_eli_navrh
# $typ = rr|sr|cr|ct
function akce_eli_navrh($typ,$id1,$id2) { trace();
  switch ($typ) {
  case 'ct':
    // zjištění rodiny
    $qr= mysql_qry("
      SELECT id_rodina,nazev
      FROM tvori
      JOIN rodina AS r USING (id_rodina)
      WHERE id_tvori=$id2
    ");
    $r= mysql_fetch_object($qr);
    $idr= $r->id_rodina;
    $znacka= $r->nazev;
    // zjištění chlapa
    $jm1= select("jmeno","chlapi","id_chlapi=$id1");
    // vložení rodiny
    mysql_qry("
      INSERT INTO duplo (znacka,tab1,tab2,id_tab2) VaLUES ('$znacka','-','r',$idr)
    ");
    $idd= mysql_insert_id();
    // odkazy na členy rodiny $id2
    $qt= mysql_qry("
      SELECT id_tvori,jmeno,role
      FROM tvori AS t JOIN osoba AS o USING (id_osoba)
      WHERE id_rodina=$idr
    ");
    while (($t= mysql_fetch_object($qt))) {
      $idt= $t->id_tvori;
      $jm2= "{$t->jmeno}({$t->role})";
      $qi= $idt == $id2
       ? "INSERT INTO duplo (znacka,idd,tab1,id_tab1,tab2,id_tab2)
          VaLUES ('$jm1 / $jm2',$idd,'c',$id1,'t',$id2)"
       : "INSERT INTO duplo (znacka,idd,tab1,tab2,id_tab2)
          VaLUES ('$jm2',$idd,'-','t',$idt)";
      mysql_qry($qi);
    }
    break;
  }
end:
  return 1;
}
# ================================================================================================== PF
# ------------------------------------------------------------------------------------------- adresy
# generování adres pro PF2013
function adresy($roku,$patt='') { trace();
  global $adresy_n, $adresy_limit, $adresy_err;
  function out(&$html,&$xls,$prijmeni,$jmeno,$jmena,$obec,$psc,$ulice,$stat,$info,$posledni,$zdroj) { // --- out
    $html.= "<tr><td>$prijmeni</td><td>$jmeno</td><td>$jmena</td>"
           ."<td>$obec</td><td>$psc</td><td>$ulice</td><td>$stat</td>"
           ."<td>$info</td><td>$posledni</td><td>$zdroj</td></tr>";
  }
  function out_sql(&$tab,&$xls,$sql) { // ------------------------------------------------- out_sql
    global $adresy_n, $adresy_limit, $adresy_err;
    $qo= mysql_qry($sql);
    if ( !$qo ) { $adresy_err.= "ERROR: ".mysql_error(); goto end; }
    while (($o= mysql_fetch_object($qo))) {
      if ( !$adresy_limit || $n<$adresy_limit) {
        list($a,$b)= explode(" a ",$o->jmeno);
        $jmena= $b ? "$a a $b" : $a;
        if ( $o->_adr ) {
          list($ulice,$psc,$obec,$stat)= explode('|',$o->_adr);
          if ( $ulice || $obec ) {
            out($tab,$xls,$o->prijmeni,$o->jmeno,$jmena,$obec,$psc,$ulice,$stat,
                          $o->info,$o->_posledni,$o->zdroj);
          }
        }
        elseif ( $o->ulice || $o->obec ) {
          out($tab,$xls,$o->prijmeni,$o->jmeno,$jmena,$o->obec,$o->psc,$o->ulice,$o->stat,
                        $o->info,$o->_posledni,$o->zdroj);
        }
      }
      $adresy_n++;
    }
  end:
  }
  function sql_roles($cond,$hranice) {
    // rodina včetně svých dětí coby pečounů bez různosti adres svých členů
    return "
      SELECT * FROM
        (SELECT
          id_rodina, nazev, GROUP_CONCAT(id_osoba ORDER BY role) AS ids_osoba,
          IF(o.ulice AND o.ulice!=r.ulice OR o.psc AND o.psc!=r.psc OR o.obec AND o.obec!=r.obec,id_rodina,0) AS _jinde,
          GROUP_CONCAT(role ORDER BY role) AS _roles,
          CONCAT(r.ulice,'|',r.psc,'|',r.obec,'|',
            IF(r.stat,r.stat,IF(LEFT(r.psc,1) IN ('0',9,8),'SK',
              IF(LEFT(r.psc,1) IN (1,2,3,4,5,6,7),'CZ','?')))) AS _radr,
          ( SELECT CONCAT(MAX(YEAR(datum_od)),GROUP_CONCAT(IF(id_akce=369,IF(funkce IN (5,12),'team 2013','mrop 2013'),'') SEPARATOR ''))
            FROM osoba JOIN spolu USING(id_osoba)
            JOIN pobyt USING(id_pobyt) JOIN akce ON pobyt.id_akce=akce.id_duakce
            WHERE id_osoba=o.id_osoba AND akce.spec=0) AS _akce
        FROM osoba AS o LEFT JOIN tvori AS t USING(id_osoba) LEFT JOIN rodina AS r USING(id_rodina)
        WHERE (LOWER(nazev) NOT RLIKE 'zemřel|vdov' AND LOWER(o.note) NOT RLIKE 'zemřel|vdov' AND LOWER(o.jmeno) NOT RLIKE 'zemřel|vdov')
        AND ( role IN ('a','b') OR ( SELECT MAX(funkce) FROM osoba JOIN spolu USING(id_osoba)
            LEFT JOIN pobyt USING(id_pobyt) LEFT JOIN akce ON pobyt.id_akce=akce.id_duakce
            WHERE id_osoba=o.id_osoba AND akce.spec=0)=99 )
        AND id_rodina
        GROUP BY id_rodina HAVING LEFT(_akce,4)>$hranice
        ORDER BY _jinde DESC,_roles DESC) AS _rs
      WHERE $cond
    ";
  }
  function sql_roles_non_spec($cond,$hranice) {
    // rodina včetně svých dětí coby pečounů bez různosti adres svých členů
    return "
      SELECT * FROM
        (SELECT
          id_rodina, nazev, GROUP_CONCAT(id_osoba ORDER BY role) AS ids_osoba,
          IF(o.ulice AND o.ulice!=r.ulice OR o.psc AND o.psc!=r.psc OR o.obec AND o.obec!=r.obec,id_rodina,0) AS _jinde,
          GROUP_CONCAT(role ORDER BY role) AS _roles,
          CONCAT(r.ulice,'|',r.psc,'|',r.obec,'|',
            IF(r.stat,r.stat,IF(LEFT(r.psc,1) IN ('0',9,8),'SK',
              IF(LEFT(r.psc,1) IN (1,2,3,4,5,6,7),'CZ','?')))) AS _radr,
          ( SELECT CONCAT(MAX(YEAR(datum_od)),GROUP_CONCAT(IF(id_akce=369,IF(funkce IN (5,12),'team 2013','mrop 2013'),'') SEPARATOR ''))
            FROM osoba JOIN spolu USING(id_osoba)
            JOIN pobyt USING(id_pobyt) JOIN akce ON pobyt.id_akce=akce.id_duakce
            WHERE id_osoba=o.id_osoba AND akce.spec=0) AS _akce,
          ( SELECT MIN(spec)
            FROM osoba JOIN spolu USING(id_osoba)
            JOIN pobyt USING(id_pobyt) JOIN akce ON pobyt.id_akce=akce.id_duakce
            WHERE id_osoba=o.id_osoba AND YEAR(datum_od)<=$hranice) AS _spec
        FROM osoba AS o LEFT JOIN tvori AS t USING(id_osoba) LEFT JOIN rodina AS r USING(id_rodina)
        WHERE (LOWER(nazev) NOT RLIKE 'zemřel|vdov' AND LOWER(o.note) NOT RLIKE 'zemřel|vdov' AND LOWER(o.jmeno) NOT RLIKE 'zemřel|vdov')
        AND ( role IN ('a','b') OR ( SELECT MAX(funkce) FROM osoba JOIN spolu USING(id_osoba)
            LEFT JOIN pobyt USING(id_pobyt) LEFT JOIN akce ON pobyt.id_akce=akce.id_duakce
            WHERE id_osoba=o.id_osoba AND akce.spec=0)=99 )
        AND id_rodina
        GROUP BY id_rodina HAVING LEFT(_akce,4)>$hranice AND _spec=0
        ORDER BY _jinde DESC,_roles DESC) AS _rs
      WHERE $cond
    ";
  }
  function sql_stat($x) {
    return "
        IF({$x}obec RLIKE 'Polska','P',
          IF({$x}obec RLIKE '^A-','A',
            IF(LEFT({$x}psc,1) IN ('0','9','8'),'SK',
              IF(LEFT({$x}psc,1) IN (1,2,3,4,5,6,7),'CZ','?'))))
    ";
  }
  // --------------------------------------------------------------------------------------- .main.
  $html= '';
  $xls= "";
  $adresy_n= 0;
  $hranice= 2013-$roku;
  $cond= $patt ? "prijmeni RLIKE '^$patt'" : "1";
  $tab= "<table class='stat'><tr><th>prijmeni</th><th>jmena</th><th>jmena2</th>
         <th>obec</th><th>psc</th><th>ulice</th><th>stat</th><th>info</th><th>naposled</th><th>zdroj</th></tr>";

  // info: team 2013, mrop 2013
  // nesjednocení chlapi s účastí na chlapské akci
  $stat= sql_stat("");
  out_sql($tab,$xls,"
    SELECT prijmeni,jmeno,ulice,psc,obec,MAX(YEAR(datum_od)) AS _posledni,
      $stat AS stat,
      GROUP_CONCAT(IF(id_akce=9,IF(stupen IN (4,5),'team 2013','mrop 2013'),'') SEPARATOR '') AS info,
      CONCAT('chlapi mimo rodinu - ',id_chlapi) AS zdroj
      -- 'chlapi mimo db' AS zdroj
    FROM ezer_ys.chlapi AS c
    JOIN ezer_ys.ch_ucast USING(id_chlapi)
    JOIN ezer_ys.ch_akce USING(id_akce)
    LEFT JOIN duplo AS d ON tab1='c' AND id_tab1=id_chlapi AND rozdily=10
    WHERE id_akce!=8 /*Cross*/ AND ISNULL(id_duplo) AND $cond
    GROUP BY id_chlapi HAVING _posledni>$hranice
    ORDER BY ulice,obec
  ");
  // nesjednocení singles s účastí na akci
  $stat= sql_stat("");
  out_sql($tab,$xls,"
    SELECT prijmeni,jmeno,ulice,psc,obec,MAX(YEAR(datum_od)) AS _posledni,
      $stat AS stat,
      GROUP_CONCAT(IF(id_akce=369,IF(funkce IN (5,12),'team 2013','mrop 2013'),'') SEPARATOR '') AS info,
      CONCAT('pečouni mimo rodinu - ',id_osoba) AS zdroj
    FROM osoba AS o
    JOIN spolu AS s USING(id_osoba)
    LEFT JOIN pobyt AS p USING(id_pobyt)
    LEFT JOIN akce AS a ON p.id_akce=a.id_duakce
    LEFT JOIN tvori AS t USING(id_osoba)
    LEFT JOIN duplo AS d ON tab1='s' AND id_tab1=id_osoba AND rozdily=10
    WHERE ISNULL(id_tvori) AND ISNULL(id_duplo) AND a.spec=0 AND $cond
    GROUP BY id_osoba HAVING _posledni>$hranice
    ORDER BY ulice,obec
  ");
  // účastnil se pouze jeden člen rodiny
  $qr= mysql_qry(sql_roles("_roles IN ('a','b','d')",$hranice));
  if ( !$qr ) { $adresy_err.= "ERROR: ".mysql_error(); goto end; }
  while (($r= mysql_fetch_object($qr))) {
    $posledni= substr($r->_akce,0,4);
    $info= substr($r->_akce,4);
    $stat= sql_stat("o.");
    out_sql($tab,$xls,"
      SELECT prijmeni,jmeno,
        IF(o.ulice='' AND o.psc='' AND o.obec='' AND o.stat='','{$r->_radr}',
          CONCAT(o.ulice,'|',o.psc,'|',o.obec,'|',$stat)
          ) AS _adr,
        $posledni AS _posledni, '$info' AS info,
        CONCAT('db single - ',id_osoba) AS zdroj
      FROM osoba AS o JOIN tvori AS t USING(id_osoba) JOIN rodina AS r USING(id_rodina)
      WHERE id_osoba={$r->ids_osoba}
    ");
  }
  // účastnilo se více členů z rodiny - bereme rodinnou adresu
  $qr= mysql_qry(sql_roles("_jinde=0 AND _roles RLIKE ','",$hranice));
  if ( !$qr ) { $adresy_err.= "ERROR: ".mysql_error(); goto end; }
  while (($r= mysql_fetch_object($qr))) {
    $posledni= substr($r->_akce,0,4);
    $info= substr($r->_akce,4);
    out_sql($tab,$xls,"
      SELECT \"{$r->nazev}\" AS prijmeni,'{$r->_radr}' AS _adr,
        GROUP_CONCAT(jmeno SEPARATOR ' a ') AS jmeno,
        $posledni AS _posledni, '$info' AS info,
        CONCAT('db rodina - ',{$r->id_rodina}) AS zdroj
      FROM osoba
      WHERE id_osoba IN ({$r->ids_osoba})
    ");
  }
  // účastnilo se více členů z rodiny - bereme rodinnou adresu
  $qr= mysql_qry(sql_roles("_jinde!=0 AND _roles RLIKE ','",$hranice));
  if ( !$qr ) { $adresy_err.= "ERROR: ".mysql_error(); goto end; }
  while (($r= mysql_fetch_object($qr))) {
    $posledni= substr($r->_akce,0,4);
    $info= substr($r->_akce,4);
    out_sql($tab,$xls,"
      SELECT \"{$r->nazev}\" AS prijmeni,'{$r->_radr}' AS _adr,
        GROUP_CONCAT(jmeno SEPARATOR ' a ') AS jmeno,
        $posledni AS _posledni, '$info' AS info,
        CONCAT('db xrodina - ',{$r->id_rodina}) AS zdroj
      FROM osoba
      WHERE id_osoba IN ({$r->ids_osoba})
    ");
  }
//   // účastnilo se více členů z rodiny - bereme rodinnou adresu ... BEZ SPEC
//   $qr= mysql_qry(sql_roles_non_spec("_jinde!=0 AND _roles RLIKE ','",$hranice));
//   if ( !$qr ) { $adresy_err.= "ERROR: ".mysql_error(); goto end; }
//   while (($r= mysql_fetch_object($qr))) {
//     $posledni= substr($r->_akce,0,4);
//     $info= substr($r->_akce,4);
//     out_sql($tab,$xls,"
//       SELECT \"{$r->nazev}\" AS prijmeni,'{$r->_radr}' AS _adr,
//         GROUP_CONCAT(jmeno SEPARATOR ' a ') AS jmeno,
//         $posledni AS _posledni, '$info' AS info,
//         CONCAT('db xrodina - ',{$r->id_rodina}) AS zdroj
//       FROM osoba
//       WHERE id_osoba IN ({$r->ids_osoba})
//     ");
//   }
  $tab.= "</table>";
  $html.= "Našlo se $adresy_n adres<br>$tab";
end:
  return $html;
}
# ================================================================================================== DUPLICITY - OLDIES
# ---------------------------------------------------------------------------------- akce_data_duplo
function akce_data_duplo($par,$ids=null) {  trace();
  # vrácení počtu záznamů DUPLO
  function duplo_state() {
    return select("COUNT(*)","duplo");
  }
  # přidání členů rodin k rodinám specifikovaným $cond v DUPLO
  function pridej_cleny($cond) {
    $err=  '';
    $ds= mysql_qry("
      SELECT id_duplo,ids_tab FROM duplo WHERE tab='r' AND $cond
    ");
    if ( !$ds ) { $err.= mysql_error(); goto end; }
    while (($d= mysql_fetch_object($ds))) {
      $id_duplo= $d->id_duplo;
      $idrs= $d->ids_tab;
      list($r1,$r2)= explode(',',$idrs);
      $os= mysql_qry("
        SELECT GROUP_CONCAT(CONCAT(id_tvori,'/',t.id_rodina)) AS _ids
        FROM osoba AS o
        JOIN tvori AS t USING(id_osoba)
        WHERE id_rodina IN ($r1,$r2)
        GROUP BY role,jmeno
      ");
      if ( !$os ) { $err.= mysql_error(); goto end; }
      while (($o= mysql_fetch_object($os))) {
        list($tr1,$tr2)= explode(',',$o->_ids);
        list($tr1t,$tr1r)= explode('/',$tr1);
        list($tr2t,$tr2r)= explode('/',$tr2);
        $id1= $tr1r==$r1 ? $tr1t : ( $tr2r==$r1 ? $tr2t : 0);
        $id2= $tr2r==$r2 ? $tr2t : ( $tr1r==$r2 ? $tr1t : 0);
        // asi ekvivalentní osoby v rodině idR
        if ( !mysql_qry("
          INSERT INTO duplo (ids_duplo,tab,id_tab,ids_tab)
            VaLUES ($id_duplo,'o',0,'$id1,$id2')
        ")) { $err.= mysql_error(); goto end; }
      }
    }
  end:
    return $err;
  }
  # vlastní tělo funkce
//                                                         debug($par,"akce_data_duplo(...,$ids)");
  $ret= (object)array('ok'=>0,'msg'=>'');
  $err= $msg= "";
  foreach (explode(',',$par->duplo) as $do) {
    $msg.= "#DUPLO= ".duplo_state()."<br>";
    switch ($do) {
    case 'truncate': # -------------------------------- + vyprázdnění DUPLO
      $msg.= "inicializace DUPLO: ";
      $ok= mysql_qry("TRUNCATE TABLE duplo ");
      if ( !$ok ) { $err.= mysql_error(); goto end; }
      $msg.= "ok";
      break;
    case 'dupl-rodiny': # ----------------------------- + ruční přidání duplicitních výskytů 2 rodin
      $msg.= "přidání osob: ";
      $n= count(explode(',',$ids));
      if ( $n!=2 ) {
        $err.= "sjednocovat lze jen 2 rodiny"; goto end;
      }
      if ( !mysql_qry("
        INSERT INTO duplo (ids_duplo,tab,id_tab,ids_tab)
          VaLUES ('','r',0,'$ids')
      ")) { $err.= mysql_error(); goto end; }
      $ret->id= mysql_insert_id();
      $err= pridej_cleny("id_duplo={$ret->id}"); if ( $err ) goto end;
      break;
    case '+osoby': # ----------------------------------- vybrání O-kandidátů
      $msg.= "přidání osob: ";
      $ok= mysql_qry("
        SELECT
          CONCAT(prijmeni,' ',jmeno),
          COUNT(id_osoba) AS _pocet,GROUP_CONCAT(id_osoba) AS ids_osoba,
          GROUP_CONCAT((SELECT COUNT(id_spolu) FROM spolu WHERE id_osoba=o.id_osoba)) AS _ucasti,
          GROUP_CONCAT(CONCAT(IFNULL(id_rodina,'-'),IFNULL(t.role,''))) AS ids_rodina,
          COUNT(DISTINCT narozeni) AS _nar,MIN(narozeni) AS _nar_min,MAX(narozeni) AS _nar_max,
          COUNT(DISTINCT o.obec) AS _obci,GROUP_CONCAT(DISTINCT o.obec) AS _obce
        FROM osoba AS o
        LEFT JOIN tvori AS t USING(id_osoba)
        LEFT JOIN rodina AS r USING(id_rodina)
        WHERE jmeno!='' AND prijmeni!=''
        GROUP BY CONCAT(prijmeni,' ',jmeno) HAVING _pocet>1
        AND (
          ( _obci=1 AND YEAR(_nar_max)=YEAR(_nar_min) )
          OR
          ( _obci=2 AND YEAR(_nar_max)=YEAR(_nar_min) AND _nar_max!='0000-00-00' )
        )
      ");
      if ( !$ok ) { $err.= mysql_error(); goto end; }
      while (($o= mysql_fetch_object($ok))) {
        list($id1,$id2)= explode(',',$o->ids_osoba);
        $n= select("COUNT(*)","duplo","id_1 IN($id1,$id2) OR id_2 IN($id1,$id2)");
        if ( !$n ) {
          if ( !mysql_qry("
            INSERT INTO duplo (faze,ids_duplo,tab,typ,id_1,id_2)
              VaLUES (0,'$id1,$id2','o',1,$id1,$id2)
          ")) { $err.= mysql_error(); goto end; }
        }
      }
      $msg.= "ok";
      break;
    case '+rodiny': # ----------------------------------- doplnění eRodin
      $msg.= "přidání rodin: ";
      $ok= mysql_qry("
        SELECT COUNT(id_rodina) AS _pocet,GROUP_CONCAT(id_rodina) AS ids_rodina,
          GROUP_CONCAT(DISTINCT nazev) AS _nazev,
          COUNT(DISTINCT obec) AS _obci,GROUP_CONCAT(DISTINCT obec) AS _obce,
          GROUP_CONCAT(
            (SELECT GROUP_CONCAT(CONCAT(role,jmeno) ORDER BY role)
             FROM rodina JOIN tvori USING(id_rodina) JOIN osoba USING(id_osoba)
             WHERE id_rodina=r.id_rodina) SEPARATOR '\n') AS _cleni
        FROM rodina AS r
        WHERE nazev!='' AND LEFT(nazev,1)!='-'
        GROUP BY CONCAT(nazev,obec,ulice) HAVING _pocet BETWEEN 2 AND 5
      ");
      if ( !$ok ) { $err.= mysql_error(); goto end; }
      while (($r= mysql_fetch_object($ok))) {
        list($id1,$id2)= explode(',',$r->ids_rodina);
        if ( !mysql_qry("
          INSERT INTO duplo (faze,ids,tab,typ,id_1,id_2)
            VaLUES (0,'{$r->ids_rodina}','r',1,$id1,$id2)
        ")) { $err.= mysql_error(); goto end; }
      }
      $msg.= "ok";
      break;
    case '+eOsoby': # ----------------------------------- doplnění eOsob k eRodinám
      $msg.= "přidání eOsob k eRodinám: ";
      $msg.= "ok";
      $err= pridej_cleny(1); if ( $err ) goto end;
      break;
    default:
      $err.= "požadavek $od N.Y.I.";
      break;
    }
    $msg.= "<br>#DUPLO= ".duplo_state()."<hr>";
  }
end:
  if ( $err ) $msg= "<br><br>CHYBA: $err<hr>$msg";
  $ret->msg= $msg;
//                                                         debug($ret,"akce_data_duplo");
  return $ret;
}
# --------------------------------------------------------------------------------------- dupl_osoba
# zkusí vyřešit duplicitu 2 osob - klíče z tabulky SPOLU
function dupl_osoba($ids_osoba,$to_change=0) { trace();
  if ( substr_count($ids_osoba,',')!=1 ) { $html= "nejsou vybrány 2 osoby"; goto end; }
  $html= dupl_meth1($ids_osoba,$to_change);
end:
  return $html;
}
# --------------------------------------------------------------------------------------- dupl_spolu
# zkusí vyřešit duplicitu 2 osob - klíče z tabulky SPOLU
function dupl_spolu($ids_spolu,$to_change=0) { trace();
  if ( substr_count($ids_spolu,',')!=1 ) { $html= "nejsou vybrány 2 osoby"; goto end; }
  $html= dupl_meth1(select("GROUP_CONCAT(id_osoba)","spolu","id_spolu IN ($ids_spolu)"),$to_change);
end:
  return $html;
}
# --------------------------------------------------------------------------------------- dupl_meth1
# zkusí vyřešit duplicitu 2 osob
function dupl_meth1($ids_osoba,$to_change=0) { trace();
//                                                         debug($ids_osoba,"osoby");
  $html= '';
  // pomocné
  $omitt= array('osoba'=>array('id_osoba','id_dupary','id_dudeti','origin','historie'));
  $cisla= array('osoba'=>array('vzdelani','cirkev','rc_xxxx'));
  $ths.= "<th>ID:</th><th>$id_osoba</th>";
  $id= $os= array();
  $i= 0;
  foreach (explode(',',$ids_osoba) as $id_osoba) {
    $i++; $idi= "id$i";
    $$idi= $id_osoba;
    $ths.= "<th>$id_osoba</th>";
    $qo= "SELECT * FROM osoba WHERE id_osoba=$id_osoba ";
    $ro= mysql_qry($qo);
    while ( $ro && ($o= mysql_fetch_object($ro)) ) {
      foreach ($o as $fld=>$val) {
        if ( !in_array($fld,$omitt['osoba']) ) {
          $os[$id_osoba][$fld]= $val;
        }
      }
    }
  }
//                                                         display("$id1,$id2");
  // posouzení rozdílů v instancích osoby - případně nalezení hlavní identity
  $trs= "";
  $smery= array();
  foreach ($os[$id1] as $fld=>$val1) {
    $val1= str_replace(' ','',$val1);
    $val2= str_replace(' ','',$os[$id2][$fld]);
    if ( $val1!=$val2 ) {
      $smer= $val1!='' && ($val2=='' || in_array($fld,$cisla['osoba']) && $val2=='0') ? '>' : (
             $val2!='' && ($val1=='' || in_array($fld,$cisla['osoba']) && $val1=='0')? '<' : 'X');
      $trs.= "<tr><th>$fld</th><th>$smer</th><td>$val1</td><td>$val2</td></tr>";
      if ( !in_array($smer,$smery) ) $smery[]= $smer;
    }
  }
  $table= "<table><tr>$ths</tr>$trs</table>";
  $lze= "NELZE";
  if ( count($smery)==1 && $smery[0]!='X' ) {
    $idx= $smery[0]=='>' ? $id1 : $id2;
    $idy= $smery[0]=='<' ? $id1 : $id2;
    $lze= "LZE PONECHAT $idx a zrušit $idy";
  }
  $html.= "<h3>různé hodnoty - $lze</h3>$table";

  // přehled počtu odkazů v tabulkách - případně přepnutí na hlavní identitu
  $trs= "";
  foreach (array('tvori','spolu','pobyt','dar','platba') as $tab) {
    $trs.= "<tr><th></th><th>$tab</th>";
    foreach (explode(',',$ids_osoba) as $id_osoba) {
      list($n,$ids)= select("COUNT(*),GROUP_CONCAT(id_$tab)",$tab,"id_osoba=$id_osoba");
      if ( $to_change && $lze ) {
        $ok= query("UPDATE $tab SET id_osoba=$idx WHERE id_osoba=$idy");
        $m= mysql_affected_rows();
        $trs.= "<td>$n/$m</td>";
      }
      else {
        $trs.= "<td>$n:$ids</td>";
      }
    }
    $trs.= "</tr>";
  }
  $table= "<table><tr>$ths</tr>$trs</table>";
  $html.= "<h3>odkazy na výskyty</h3>$table";
end:
  return $html;
}
# ================================================================================================== ALBUM
# ---------------------------------------------------------------------------------------- album_set
# přidá fotografii do alba
function album_set($id_rodina,$fileinfo,$nazev) {
  global $ezer_path_root;
  $nazev= utf2ascii($nazev);
  $name= "$nazev.{$fileinfo->name}";
  $path= "$ezer_path_root/fotky/$name";
  $data= $fileinfo->text;
//                         $bytes= file_put_contents("$path.dmp",$data);  // debug info
  // test korektnosti fotky
  if ( substr($data,0,23)=="data:image/jpeg;base64," ) {
    // uložení fotky
    $data= base64_decode(substr("$data==",23));
//                          debug($fileinfo,"album_set ".strlen($fileinfo->text)."/".strlen($data));
    $bytes= file_put_contents($path,$data);
    // nalezení rodiny a poznamenání názvu fotky
    query("UPDATE rodina SET fotka='$name' WHERE id_rodina=$id_rodina");
  }
  else {
    $name= '';          // tiché oznámení chyby
  }
  return $name;
}
# ------------------------------------------------------------------------------------- album_delete
# zruší fotografii z alba
function album_delete($id_rodina) { #trace();
  global $ezer_path_root;
  $ok= 0;
  // nalezení rodiny a poznamenání názvu fotky
  $name= select('fotka','rodina',"id_rodina=$id_rodina");
//                                         display("name=$name");
  if ( $name ) {
    query("UPDATE rodina SET fotka='' WHERE id_rodina=$id_rodina");
    // smazání fotky
    $ok= unlink("$ezer_path_root/fotky/$name");
//                                         display("unlink('$ezer_path_root/fotky/$name')=$ok");
    $ok&= unlink("$ezer_path_root/fotky/copy/$name");
//                                         display("unlink('$ezer_path_root/fotky/copy/$name')=$ok");
  }
  return $ok;
}
# ---------------------------------------------------------------------------------------- album_get
# zobrazí fotografii z alba
function album_get($name,$w,$h,$msg="Fotografie ještě není k dispozici - lze ji sem přidat myší") {
  global $ezer_path_root;
  if ( $name ) {
    $dest= "$ezer_path_root/fotky/copy/$name";
    $orig= "$ezer_path_root/fotky/$name";
    if ( !file_exists($dest) ) {
      // zmenšení na požadovanou velikost
      $ok= album_resample($orig,$dest,$w,$h,0,1);
    }
    $src= "fotky/copy/$name";
    $html= "<a href='fotky/$name' target='_album'><img src='fotky/copy/$name'
      width='$w' onload='var x=arguments[0];img_filter(x.target,\"sharpen\",0.7,1);'/></a>";

  //   $data= "iVBORw0"."KGgoAAAANSUhEUgAAACAAAAAFCAYAAAAkG+5xAAAABGdBTUEAANbY1E9YMgAAABl0RVh0U29mdHdhcm"
  //        . "UAQWRvYmUgSW1hZ2VSZWFkeXHJZTwAAABTSURBVHjarJJRCgAgCEM9nHfy+K+fKBKzjATBDZUxFUAuU5mhnWPT"
  //        . "SzK7YCkkQR3tsM5bImjgVwE3HIED6vFvB4w17CC4dILdD5AIwvX5OW0CDAAH+Qok/eTdBgAAAABJRU5E"."rkJggg";
  //   $html= "<img alt='Embedded Image' src='data:image/png;base64,$data' />";
  }
  else {
    $html.= $msg;
  }
  return $html;
}
# ----------------------------------------------------------------------------------- album_resample
function album_resample($source, $dest, &$width, &$height,$copy_bigger=0,$copy_smaller=1) { #trace();
  global $CONST;
  $maxWidth= $width;
  $maxHeight= $height;
  $ok= 1;
  // zjistime puvodni velikost obrazku a jeho typ: 1 = GIF, 2 = JPG, 3 = PNG
  list($origWidth, $origHeight, $type)=@ getimagesize($source);
//                                                 debug(array($origWidth, $origHeight, $type),"album_resample($source, $dest, &$width, &$height,$copy_bigger)");
  if ( !$type ) $ok= 0;
  if ( $ok ) {
    if ( !$maxWidth ) $maxWidth= $origWidth;
    if ( !$maxHeight ) $maxHeight= $origHeight;
    // nyni vypocitam pomer změny
    $pw= $maxWidth / $origWidth;
    $ph= $maxHeight / $origHeight;
    $p= min($pw, $ph);
    // vypocitame vysku a sirku změněného obrazku - vrátíme ji do výstupních parametrů
    $newWidth = (int)($origWidth * $p);
    $newHeight = (int)($origHeight * $p);
    $width= $newWidth;
    $height= $newHeight;
//                                                 display("p=$p, copy_smaller=$copy_smaller");
    if ( ($pw == 1 && $ph == 1) || ($copy_bigger && $p<1) || ($copy_smaller && $p>1) ) {
//                                                 display("kopie");
      // jenom zkopírujeme
      copy($source,$dest);
    }
    else {
//                                                 display("úprava");
      // zjistíme velikost cíle - abychom nedělali zbytečnou práci
      $destWidth= $destHeight= -1; $ok= 2; // ok=2 -- nic se nedělalo
      if ( file_exists($dest) ) list($destWidth, $destHeight)= getimagesize($dest);
      if ( $destWidth!=$newWidth || $destHeight!=$newHeight ) {
        // vytvorime novy obrazek pozadovane vysky a sirky
        $image_p= ImageCreateTrueColor($newWidth, $newHeight);
        // otevreme puvodni obrazek se souboru
        switch ($type) {
        case 1: $image= ImageCreateFromGif($source); break;
        case 2: $image= ImageCreateFromJpeg($source); break;
        case 3: $image= ImageCreateFromPng($source); break;
        }
        // okopirujeme zmenseny puvodni obrazek do noveho
        if ( $maxWidth || $maxHeight )
          ImageCopyResampled($image_p, $image, 0, 0, 0, 0, $newWidth, $newHeight, $origWidth, $origHeight);
        else
          $image_p= $image;
        // ulozime
        $ok= 0;
        switch ($type) {
        case 1: /*ImageColorTransparent($image_p);*/ $ok= ImageGif($image_p, $dest);  break;
        case 2: $ok= ImageJpeg($image_p, $dest);  break;
        case 3: $ok= ImagePng($image_p, $dest);  break;
        }
      }
    }
  }
  return $ok;
}
# ================================================================================================== KONTROLY
# --------------------------------------------------------------------------------- akce_data_survey
# přehled dat
# 'clear-all' -- výmaz tabulek OSOBA, TVORI, RODINA, POBYT, SPOLU
# 'MS-ucast'  -- EXPORT-ucastnici_1995-2012.csv => naplnění akcí "Letní kurz MS" účastníky
# 'MS-skup'   -- EXPORT-skupinky.csv => naplnění akcí "Letní kurz MS" skupinkami a VPS
# 'MS-pec'    -- EXPORT-pecouni.csv => naplnění OSOBA pečovateli a zařazení na kurz
function akce_data_survey($opt) {  trace();
    foreach(array('osoba', 'tvori', 'rodina', 'pobyt', 'spolu') as $db) {
      $qt= "SELECT COUNT(*) AS _pocet_ FROM $db";
      $rt= mysql_qry($qt); if ( !$rt ) { $err= "tabulka $db"; goto end; }
      $ot= mysql_fetch_object($rt);
      $msg.= "tabulka $db má {$ot->_pocet_} záznamů<br>";
    }
end:
  if ( $err ) $msg.= "<br><br>CHYBA: $err";
  return $msg;
}
# ------------------------------------------------------------------------------ akce_data_note_pece
# doplnění poznámek z ms_pece do osoba
function akce_data_note_pece($par) { trace();
  $html= '';
  $qry= "SELECT id_osoba,cislo,krestni,ms_pece.jmeno,poznamka,note FROM ms_pece
         JOIN osoba ON origin='c' AND prijmeni=ms_pece.jmeno AND osoba.jmeno=krestni
         WHERE cislo NOT IN (504,505,473) AND ms_pece.jmeno!=''  ";
  $res= mysql_qry($qry);
  while ( $res && ($x= mysql_fetch_object($res)) ) {
    $set= "id_dupary={$x->cislo}";
    if ( $x->poznamka ) {
      $pozn= $x->note ? "{$x->poznamka}, {$x->note}" : $x->poznamka;
      $pozn= mysql_real_escape_string($x->poznamka);
      $set.= ",note='$pozn'";
    }
    mysql_qry("UPDATE osoba SET $set WHERE id_osoba={$x->id_osoba} ");
  }
  $html.= "hotovo";
  return $html;
}
# -------------------------------------------------------------------------------- akce_kontrola_dat
# kontrola dat
#  -  nulové klíče
function akce_kontrola_dat($par) { trace();
  $html= '';
  $n= 0;
  $opravit= $par->opravit ? true : false;
  // kontrola nenulovosti klíčů ve spojovacích záznamech
  // ---------------------------------------------- tabulka SPOLU I
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
  $res= mysql_qry($qry);
  while ( $res && ($x= mysql_fetch_object($res)) ) {
    $n++;
    if ( $opravit ) {
      $ok= mysql_qry("DELETE FROM spolu WHERE id_spolu={$x->id_spolu} AND ($cond)",1)
         ? " = SMAZÁNO" : ' !!!!!CHYBA při mazání' ;
    }
    if ( !$x->id_pobyt && !$x->id_osoba )
      $msg.= "<dd>záznam spolu={$x->id_spolu} je nulový$ok</dd>";
    if ( !$x->id_osoba )
      $msg.= "<dd>osoba=0 v záznamu spolu={$x->id_spolu} pobytu={$x->id_pobyt} akce {$x->nazev}$ok</dd>";
    if ( !$x->id_pobyt )
      $msg.= "<dd>pobyt=0 v záznamu spolu={$x->id_spolu} osoby {$x->prijmeni} {$x->jmeno}$ok</dd>";
  }
  # tabulka SPOLU II
  $qry=  "SELECT id_spolu,count(*) AS _pocet_,s.id_osoba,o.id_osoba,
            CONCAT(a.nazev,' ',YEAR(datum_od)) AS nazev,prijmeni,jmeno
          FROM spolu AS s
          LEFT JOIN pobyt AS p USING(id_pobyt)
          LEFT JOIN akce  AS a ON a.id_duakce=p.id_akce
          LEFT JOIN osoba AS o ON o.id_osoba=s.id_osoba
          GROUP BY s.id_osoba,id_pobyt HAVING _pocet_>1
          ORDER BY id_akce";
  $res= mysql_qry($qry);
  while ( $res && ($x= mysql_fetch_object($res)) ) {
    $n++;
    $msg.= "<dd>násobný pobyt záznamem spolu={$x->id_spolu} na akci {$x->nazev}
      osoby {$x->prijmeni} {$x->jmeno} $ok</dd>";
  }
  $html.= "<dt style='margin-top:5px'>tabulka <b>spolu</b>".($msg?$msg:"<dd>ok</dd>")."</dt>";
  // ----------------------------------------- tabulka SPOLU III
  $msg= '';
  $qry=  "SELECT GROUP_CONCAT(id_pobyt) AS _p,count(DISTINCT id_pobyt) AS _pocet_,s.id_osoba,o.id_osoba,
            CONCAT(a.nazev,' ',YEAR(datum_od)) AS nazev,prijmeni,jmeno
          FROM spolu AS s
          LEFT JOIN pobyt AS p USING(id_pobyt)
          LEFT JOIN akce  AS a ON a.id_duakce=p.id_akce
          LEFT JOIN osoba AS o ON o.id_osoba=s.id_osoba
          GROUP BY s.id_osoba,id_akce HAVING _pocet_>1
          ORDER BY id_akce";
  $res= mysql_qry($qry);
  while ( $res && ($x= mysql_fetch_object($res)) ) {
    $n++;
    $msg.= "<dd>násobný pobyt na akci {$x->nazev} - záznamy pobyt {$x->_p} pro
      osobu {$x->prijmeni} {$x->jmeno} $ok</dd>";
  }
  $html.= "<dt style='margin-top:5px'>tabulka <b>spolu</b>".($msg?$msg:"<dd>ok</dd>")."</dt>";
  // --------------------------------------- tabulka TVORI I
  $msg= '';
  $cond= "tvori.id_rodina=0 OR tvori.id_osoba=0 ";
  $qry=  "SELECT id_tvori,role,tvori.id_osoba,tvori.id_rodina,r.nazev,prijmeni,jmeno
          FROM tvori
          LEFT JOIN rodina AS r USING(id_rodina)
          LEFT JOIN osoba AS o ON o.id_osoba=tvori.id_osoba
          WHERE $cond ";
  $res= mysql_qry($qry);
  while ( $res && ($x= mysql_fetch_object($res)) ) {
    $n++;
    if ( $opravit ) {
      $ok= mysql_qry("DELETE FROM tvori WHERE id_tvori={$x->id_tvori} AND ($cond)",1)
         ? " = SMAZÁNO" : ' !!!!!CHYBA při mazání' ;
    }
    if ( !$x->id_pobyt && !$x->id_osoba )
      $msg.= "<dd>záznam tvori={$x->id_tvori} je nulový</dd>";
    if ( !$x->id_osoba )
      $msg.= "<dd>osoba=0 v záznamu tvori={$x->id_spolu} rodiny={$x->id_rodina} {$x->nazev}$ok</dd>";
    if ( !$x->id_pobyt )
      $msg.= "<dd>rodina=0 v záznamu tvori={$x->id_tvori} osoby {$x->prijmeni} {$x->jmeno}$ok</dd>";
  }
  # tabulka TVORI II
  $qry=  "SELECT id_tvori,count(*) AS _pocet_,GROUP_CONCAT(role) AS _role_,
            tvori.id_osoba,tvori.id_rodina,r.nazev,prijmeni,jmeno
          FROM tvori
          LEFT JOIN rodina AS r USING(id_rodina)
          LEFT JOIN osoba AS o ON o.id_osoba=tvori.id_osoba
          GROUP BY id_osoba,id_rodina HAVING _pocet_>1
          ORDER BY id_rodina ";
  $res= mysql_qry($qry);
  while ( $res && ($x= mysql_fetch_object($res)) ) {
    $n++;
    if ( $opravit && strlen($x->_role_)==1 ) {
      $ok= mysql_qry("DELETE FROM tvori WHERE id_tvori={$x->id_tvori} AND ($cond)",1)
         ? " = SMAZÁNO" : ' !!!!!CHYBA při mazání' ;
    }
    $msg.= "<dd>násobné členství záznamem tvori={$x->id_tvori} v rodině {$x->nazev}
      osoby {$x->prijmeni} {$x->jmeno} v roli {$x->_role} $ok</dd>";
  }
  $html.= "<dt style='margin-top:5px'>tabulka <b>tvori</b>".($msg?$msg:"<dd>ok</dd>")."</dt>";
  // ------------------------------------------------------ tabulka OSOBA, SPOLU, TVORI
  $msg= '';
  $rx= mysql_qry("
    SELECT o.id_osoba,id_dar,id_platba,s.id_spolu,p.id_pobyt,a.id_duakce,
      a.nazev,id_tvori,r.id_rodina,r.nazev,t.role /*,o.* */
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
  while ( $rx && ($x= mysql_fetch_object($rx)) ) {
    $n++;
    $msg.= "<dd>fantómová osoba {$x->id_osoba} v rodině {$x->id_rodina} {$x->nazev} v roli {$x->role} $ok</dd>";
    if ( $opravit ) {
      $ok= '';
      if ( !$x->id_dar && !$x->id_platba ) {
        $ok.= mysql_qry("DELETE FROM spolu WHERE id_osoba={$x->id_osoba}")
           ? (" = spolu SMAZÁNO ".mysql_affected_rows().'x') : ' CHYBA při mazání spolu' ;
        $ok.= mysql_qry("DELETE FROM osoba WHERE id_osoba={$x->id_osoba}")
           ? ", osoba SMAZÁNO " : ' CHYBA při mazání osoba ' ;
      }
      else
        $ok= " = vazba na dar či platbu";
    }
  }
  $html.= "<dt style='margin-top:5px'>tabulka <b>osoba</b>".($msg?$msg:"<dd>ok</dd>")."</dt>";
  // ---------------------------------------------------- triviální RODINA
  $msg= '';
  $rx= mysql_qry("
    SELECT id_tvori,COUNT(*) AS _pocet,nazev,id_dar,id_platba
    FROM rodina JOIN tvori USING (id_rodina)
    LEFT JOIN dar    AS d USING(id_rodina)
    LEFT JOIN platba AS x USING(id_rodina)
    GROUP BY id_rodina HAVING _pocet=0
  ");
  while ( $rx && ($x= mysql_fetch_object($rx)) ) {
    $n++;
    $msg.= "<dd>triviální rodina {$x->nazev} $ok</dd>";
     # if ( $opravit ) ... zkontrolovat dar,platba
  }
  $html.= "<dt style='margin-top:5px'>tabulka <b>rodina</b>".($msg?$msg:"<dd>ok</dd>")."</dt>";
  // ---------------------------------------------------- triviální POBYT
  $msg= '';
  $rx= mysql_qry("
    SELECT id_spolu,COUNT(*) AS _pocet
    FROM pobyt AS p JOIN spolu USING (id_pobyt)
    LEFT JOIN akce AS a ON a.id_duakce=p.id_akce
    /*LEFT JOIN mail USING(id_pobyt)*/
    GROUP BY id_pobyt HAVING _pocet=0
  ");
  while ( $rx && ($x= mysql_fetch_object($rx)) ) {
    $n++;
    $msg.= "<dd>triviální pobyt {$x->nazev} $ok</dd>";
    # if ( $opravit ) ... zkontrolovat mail
  }
  $html.= "<dt style='margin-top:5px'>tabulka <b>pobyt</b>".($msg?$msg:"<dd>ok</dd>")."</dt>";
  // konec
  $html= $n
    ? "<h3>Nalezeno $n inkonzistencí v datech</h3><dl>$html</dl>"
    : "<h3>Následující tabulky jsou konzistentní</h3>$html";
  return $html;
}
# ================================================================================================== AKCE
# ---------------------------------------------------------------------------------------- akce_id2a
# vrácení hodnot akce
function akce_id2a($id_akce) {  trace();
  $a= (object)array('title'=>'?','cenik'=>0,'cena'=>0,'soubeh'=>0,'hlavni'=>0,'soubezna'=>0);
  list($a->title,$a->rok,$a->cenik,$a->cena,$a->hlavni,$a->soubezna)=
    select("a.nazev,YEAR(a.datum_od),a.ma_cenik,a.cena,a.id_hlavni,IFNULL(s.id_duakce,0)",
      "akce AS a
       LEFT JOIN akce AS s ON s.id_hlavni=a.id_duakce",
      "a.id_duakce=$id_akce");
  # diskuse souběhu: 0=normální akce, 1=hlavní akce, 2=souběžná akce
  $a->soubeh= $a->soubezna ? 1 : ( $a->hlavni ? 2 : 0);
  $a->rok= $a->rok ?: date('Y');
                                                                debug($a,$id_akce);
  return $a;
}
# ------------------------------------------------------------------------------- akce_soubeh_nastav
# nastavení akce jako souběžné s jinou (která musí mít stejné datumy a místo konání)
function akce_soubeh_nastav($id_akce,$nastavit=1) {  trace();
  $msg= "";
  if ( $nastavit ) {
    list($hlavni,$nazev,$nic,$pocet)=
      select("h.id_duakce,h.nazev,s.id_hlavni,COUNT(*)","akce AS h JOIN akce AS s ON s.misto=h.misto",
        "s.id_duakce=$id_akce AND s.id_duakce!=h.id_duakce AND s.datum_od=h.datum_od");
    if ( !$pocet ) {
      $msg= "k souběžné akci musí napřed existovat hlavní akce se stejným začátkem a místem konání";
      goto end;
    }
    elseif ( $pocet>1 ) {
      $msg= "Souběžná akce již existuje (tzn. již jsou $počet akce se stejným začátkem a místem konání)";
      goto end;
    }
    elseif ( $nic ) {
      $msg= "Tato akce již je souběžná k hlavní akci (se stejným začátkem a místem konání)";
      goto end;
    }
    // vše je v pořádku $hlavni může být hlavní akcí k $id_akce
    $ok= query("UPDATE akce SET id_hlavni=$hlavni WHERE id_duakce=$id_akce");
    $msg.= $ok ? "akce byla přiřazena k hlavní akci '$nazev'" : "CHYBA!";
  }
  else {
    // zrušit nastavení
    $ok= query("UPDATE akce SET id_hlavni=0 WHERE id_duakce=$id_akce");
    $msg.= $ok ? "akce je nadále vedena jako samostatná" : "CHYBA!";
  }
end:
  return $msg;
}
# ------------------------------------------------------------------------------ akce_delete_confirm
# dotazy před zrušením akce
function akce_delete_confirm($id_akce) {  trace();
  $ret= (object)array('zrusit'=>0,'ucastnici'=>'','platby'=>'');
  // fakt zrušit?
  list($nazev,$misto,$datum)=
    select("nazev,misto,DATE_FORMAT(datum_od,'%e.%m.%Y')",'akce',"id_duakce=$id_akce");
  $ret->zrusit= "Opravdu smazat akci '$nazev, $misto' začínající $datum?";
  if ( !$nazev ) goto end;
  // má účastníky
  $ucastnici= select('COUNT(*)','pobyt',"id_akce=$id_akce");
  $ret->ucastnici= $ucastnici
    ? "Tato akce má již zapsáno $ucastnici účastníků. Má se jejich účast zrušit a potom smazat akci?"
    : '';
  // jsou evidovány platby
  $platby= select('COUNT(*)','platba',"id_duakce=$id_akce");
  $ret->platby= $platby
    ? "S touto akcí jsou již svázány $platby platby. Akci nelze smazat."
    : '';
end:
  return $ret;
}
# -------------------------------------------------------------------------------------- akce_delete
# zrušení akce
function akce_delete($id_akce,$ret) {  trace();
  list($nazev)= select("nazev",'akce',"id_duakce=$id_akce");
  if ( $ret->ucastnici ) {
    // napřed zrušit účasti na akci
    query("DELETE FROM spolu USING spolu JOIN pobyt USING(id_pobyt) WHERE id_akce=$id_akce");
    $s= mysql_affected_rows();
    query("DELETE FROM pobyt WHERE id_akce=$id_akce");
    $p= mysql_affected_rows();
  }
  query("DELETE FROM akce WHERE id_duakce=$id_akce");
  $a= mysql_affected_rows();
  $msg= $a
    ? "Akce '$nazev' byla smazána" . ( $p+$s ? ", včetně $p účastí $s účastníků" : '.')
    : "CHYBA: akce '$nazev' nebyla smazána";
end:
  return $msg;
}
# ======================================================================================= CENÍK AKCE
function akce_platby_xls($id_akce) {  trace();
  $ret= (object)array('ok'=>0,'xhref'=>'','msg'=>'');
  $test= 0;
  $name= "cenik_$id_akce";
  list($nazev,$rok)= select("nazev,YEAR(datum_do)","akce","id_duakce=$id_akce");
  // vytvoření sešitu s ceníkem
  $n= 1;
  $xls= <<<__XLS
  |open  $name|\n\n|sheet cenik|columns A=40
    |A$n Ceník pro akci $nazev $rok::bold size=16\n
__XLS;
  // výpis ceníku podle typu ubytování
  $tit= "|B* cena|C* DPH|A*:C* bcolor=ffcccccc bold\n";
  $qa= "SELECT * FROM cenik WHERE id_akce=$id_akce ORDER BY poradi";
  $ra= mysql_qry($qa);
  if ( !$ra || !mysql_num_rows($ra) ) {
    $ret->msg.= "akce {$pobyt->id_akce} nemá cenový vzorec";
    goto end;
  }
  while ( $ra && ($a= mysql_fetch_object($ra)) ) {
    $pol= $a->polozka;
    $bc= '';
    if ( substr($pol,0,2)=='--' ) {
      $n+= 2;
      $xls.= "|A$n ".substr($pol,2).str_replace('*',$n,$tit);
    }
    elseif ( substr($pol,0,1)=='-' ) {
      $n+= 3;
      $xls.= "|A$n ".substr($pol,1)."|A$n:C$n bcolor=ffaaaaff bold size=14\n";
    }
    else {
      $n++;
      $za= $a->za;
      $cena= $a->cena;
      $dph= $a->dph;
      $xls.= "|A$n $pol ($za)|B$n $cena|C$n $dph\n";
    };
  }
  // konec
  $xls.= "|close|";
  $ret->msg= Excel5($xls,!$test);
  if ( $ret->msg ) goto end;
  $ret->ok= 1;
  $ret->xhref= " zde lze stáhnout <a href='docs/$name.xls' target='xls'>$name.xls</a>.";
//                                                         if ($test) display(($xls));
end:
//                                                         debug($ret);
  return $ret;
}
# -------------------------------------------------------------------------------------- akce_platby
# nalezení platby za pobyt na akci
function akce_platby($id_pobyt) {  trace();
  $suma= 0;
  $html.= '';
  $id_akce= select("id_akce","pobyt","id_pobyt=$id_pobyt");
  // procházení osob
  $qo= "SELECT id_osoba FROM spolu WHERE id_pobyt=$id_pobyt";
  $ro= mysql_qry($qo);
  while ( $ro && ($o= mysql_fetch_object($ro)) ) {
    // hledání plateb této osoby
    $qp= "SELECT * FROM platba WHERE id_duakce=$id_akce AND id_osoba={$o->id_osoba}";
    $rp= mysql_qry($qp);
    while ( $rp && ($p= mysql_fetch_object($rp)) ) {
      $den= sql_date1($p->datum);
      $html.= "$den {$p->castka}Kč: {$p->poznamka}, {$p->ucet_nazev}";
      $suma+= $p->castka;
    }
  }
  return $html;
}
# ------------------------------------------------------------------------------- akce_pobyt_default
# definice položek v POBYT podle počtu a věku účastníků
# 130522 údaje za chůvu budou připsány na rodinu chovaného dítěte
# 130524 oživena položka SVP
function akce_pobyt_default($id_pobyt,$zapsat=0) {  trace();
  // projítí společníků v pobytu
  $dosp= $deti= $koje= $noci= $sleva= $fce= $svp= 0;
  $msg= '';
  $qo= "SELECT o.jmeno,o.narozeni,a.datum_od,DATEDIFF(datum_do,datum_od) AS _noci,p.funkce,
         s.pecovane,(SELECT CONCAT(osoba.id_osoba,',',pobyt.id_pobyt)
          FROM pobyt
          JOIN spolu ON spolu.id_pobyt=pobyt.id_pobyt
          JOIN osoba ON osoba.id_osoba=spolu.id_osoba
          WHERE pobyt.id_akce=a.id_duakce AND spolu.pecovane=o.id_osoba) AS _chuva
        FROM spolu AS s JOIN osoba AS o USING(id_osoba) JOIN pobyt AS p USING(id_pobyt)
        JOIN akce AS a ON p.id_akce=a.id_duakce
        WHERE id_pobyt=$id_pobyt";
  $ro= mysql_qry($qo);
  while ( $ro && ($o= mysql_fetch_object($ro)) ) {
    if ( $o->_chuva ) {
      $dosp++;                          // platíme za chůvu vlastního dítěte
      $svp++;                           // ale ne za obecného pečouna
    }
    if ( $o->pecovane) $dosp--;         // za dítě-chůvu platí rodič pečovaného dítěte
    $noci= $o->_noci;
    $fce= $o->funkce;
    $vek= narozeni2roky(sql2stamp($o->narozeni),sql2stamp($o->datum_od));
    $msg.= " {$o->jmeno}:$vek";
    if     ( $vek<3  ) $koje++;
    elseif ( $vek<10 ) $deti++;
    else               $dosp++;
  }
  // zápis do pobytu
  if ( $zapsat ) {
    query("UPDATE pobyt SET luzka=".($dosp+$deti).",kocarek=$koje,strava_cel=$dosp,strava_pol=$deti,
             pocetdnu=$noci,svp=$svp WHERE id_pobyt=$id_pobyt");
  }
  $ret= (object)array('luzka'=>$dosp+$deti,'kocarek'=>$koje,'pocetdnu'=>$noci,'svp'=>$svp,
                      'strava_cel'=>$dosp,'strava_pol'=>$deti,'vzorec'=>$fce);
//                                                 debug($ret,"osob:$koje,$deti,$dosp $msg fce=$fce");
  return $ret;
}
# --------------------------------------------------------------------------------- akce_vzorec_test
# test výpočtu platby za pobyt na akci
function akce_vzorec_test($id_akce,$nu=2,$nd=0,$nk=0) {  trace();
  $ret= (object)array('navrh'=>'','err'=>'');
  // obecné info o akci
  list($ma_cenik,$noci,$strava_oddo)=
    select("ma_cenik,DATEDIFF(datum_do,datum_od),strava_oddo","akce","id_duakce=$id_akce");
                                                display("$ma_cenik,$noci,$strava_oddo");
  if ( !$ma_cenik ) { $html= "akce nemá ceník"; goto end; }
  // definované položky
  $o= $strava_oddo=='oo' ? 1 : 0;       // oběd navíc
  $cenik= array(
    //            u d k noci oo plus
    'Nl' => array(1,1,0,   1, 0,  1),
    'P'  => array(1,0,0,   0, 0,  1),
    'Pd' => array(0,1,0,   0, 0,  1),
    'Pk' => array(0,0,1,   0, 0,  1),
    'Su' => array(1,0,0,   0, 0, -1),
    'Sk' => array(0,0,1,   0, 0, -1),
    'sc' => array(1,0,0,   1, 0,  1),
    'oc' => array(1,0,0,   1,$o,  1),
    'vc' => array(1,0,0,   1, 0,  1),
    'sp' => array(0,1,0,   1, 0,  1),
    'op' => array(0,1,0,   1,$o,  1),
    'vp' => array(0,1,0,   1, 0,  1),
  );
  // výpočet ceny podle parametrů
  $cena= 0;
  $html= "<table>";
  $ra= mysql_qry("SELECT * FROM cenik WHERE id_akce=$id_akce AND za!='' ORDER BY poradi");
  while ( $ra && ($a= mysql_fetch_object($ra)) ) {
    $acena= $a->cena;
    list($za_u,$za_d,$za_k,$za_noc,$oo,$plus)= $cenik[$a->za];
    $nx= $nu*$za_u + $nd*$za_d + $nk*$za_k;
    $cena+= $cc= $nx * ($za_noc?$noci:1) * $acena * $plus;
    if ( $cc ) {
      $pocet= $za_noc?" * ".($noci+$oo):'';
      $html.= "<tr>
        <td>{$a->polozka} ($nx$pocet * $acena)</td>
        <td align='right'>$cc</td></tr>";
    }
  }
  $html.= "<tr><td><b>Celkem</b></td><td align='right'><b>$cena</b></td></tr>";
  $html.= "</table>";
  // návrat
end:
  $ret->navrh.= $html;
  return $ret;
}
# ------------------------------------------------------------------------------- akce_vzorec_soubeh
# výpočet platby za pobyt na hlavní akci, včetně platby za souběžnou akci (děti)
# pokud je $id_pobyt=0 provede se výpočet podle dodaných hodnot (dosp+koje)
function akce_vzorec_soubeh($id_pobyt,$id_hlavni,$id_soubezna,$dosp=0,$deti=0,$koje=0) { trace();
  $sleva= 0;
  // načtení ceníků
  function nacti_cenik($id_akce,&$cenik,&$html) {
    $qa= "SELECT * FROM cenik WHERE id_akce=$id_akce ORDER BY poradi";
    $ra= mysql_qry($qa);
    if ( !mysql_num_rows($ra) ) {
      $html.= "akce $id_akce nemá ceník";
    }
    else {
      $cenik= array();
      while ( $ra && ($a= mysql_fetch_object($ra)) ) {
        $za= $a->za;
        if ( !$za ) continue;
        $cc= (object)array();
        if ( isset($cenik[$za]) ) $html.= "v ceníku se opakují kódy za=$za";
        $cenik[$za]= (object)array('c'=>$a->cena,'txt'=>$a->polozka);
      }
//                                                         debug($cenik,"ceník pro $id_akce");
    }
  }
  $ret= (object)array('navrh'=>'','err'=>'');
  nacti_cenik($id_hlavni,$cenik_dosp,$ret->navrh);   if ( $html ) goto end;
  nacti_cenik($id_soubezna,$cenik_deti,$ret->navrh); if ( $html ) goto end;
  $map_kat= map_cis('ms_akce_dite_kat','zkratka');
  if ( $id_pobyt ) {
    // zjištění parametrů pobytu podle hlavní akce
    $qp= "SELECT * FROM pobyt AS p JOIN akce AS a ON p.id_akce=a.id_duakce WHERE id_pobyt=$id_pobyt";
    $rp= mysql_qry($qp);
    if ( $rp && ($p= mysql_fetch_object($rp)) ) {
      $pocetdnu= $p->pocetdnu;
      $strava_oddo= $p->strava_oddo;
      $dosp= $p->pouze ? 1 : 2;
      $sleva= $p->sleva;
  //     $svp= $p->svp;
  //     $neprijel= $p->funkce==10;
      $datum_od= $p->datum_od;
    }
    // pokud mají děti označenou kategorii X, určuje se cena podle pX ceníku souběžné akce
    // cena pro dospělé se určí podle ceníku hlavní akce - děti bez kategorie se nesmí
    $deti_kat= array();
    $n= $ndeti= 0;
    $chuvy= $del= '';
    $qo= "SELECT o.jmeno,s.dite_kat,p.funkce, t.role, p.ubytovani, narozeni,
           s.pecovane,(SELECT CONCAT(osoba.prijmeni,',',osoba.jmeno,',',pobyt.id_pobyt)
            FROM pobyt
            JOIN spolu ON spolu.id_pobyt=pobyt.id_pobyt
            JOIN osoba ON osoba.id_osoba=spolu.id_osoba
            WHERE pobyt.id_akce=p.id_akce AND spolu.pecovane=o.id_osoba) AS _chuva
          FROM spolu AS s
          JOIN osoba AS o ON s.id_osoba=o.id_osoba
          JOIN pobyt AS p USING(id_pobyt)
          LEFT JOIN tvori AS t ON t.id_osoba=o.id_osoba
          WHERE id_pobyt=$id_pobyt";
    $ro= mysql_qry($qo);
    while ( $ro && ($o= mysql_fetch_object($ro)) ) {
      $vek= narozeni2roky(sql2stamp($o->narozeni),sql2stamp($datum_od));
      $kat= $o->dite_kat;
      if ( $kat ) {
        // dítě - speciální cena
        $deti_kat[$map_kat[$kat]]++;
        $ndeti++;
      }
      elseif ( $vek<18 ) {
        // dítě bez kategorie
        $ret->err.= "<br>Chybí kategorie u dítěte: {$o->jmeno}";
        $ndeti++;
      }
      $n++;
    }
    // kontrola počtu
    if ( $dosp + $ndeti != $n ) {
      $ret->err.= "<br>chyba v počtech: dospělí=$dosp, $deti=$ndeti ale celkem $n";
    }
  }
  elseif ( $id_hlavni ) {
    // zjištění parametrů testovacího "pobytu" podle ceníku dané akce
    list($pocetdnu,$strava_oddo)=
      select("DATEDIFF(datum_do,datum_od),strava_oddo","akce","id_duakce=$id_hlavni");
    // doplnění dětí do tabulky jako kojenců
    if ( $koje ) $deti_kat['F']= $koje;
    if ( $deti ) $deti_kat['B']= $deti;
  }
//                                         debug($deti_kat,"dětí");
  $Kc= "&nbsp;Kč";
  // redakce textu k ceně dospělých
  $html.= "<b>Rozpis platby za účast dospělých na jejich akci</b><table>";
  $cena= 0;
  $ubytovani= $strava= $program= $slevy= '';
  foreach($cenik_dosp as $za=>$a) {
    $c= $a->c; $txt= $a->txt;
    switch ($za) {
    case 'Nl':
      $cena+= $cc= $dosp * $pocetdnu * $c;
      if ( !$cc ) break;
      $ret->c_nocleh+= $cc;
      $ubytovani.= "<tr><td>".($dosp*$pocetdnu)." x $txt ($c$Kc)</td><td align='right'>$cc$Kc</td></tr>";
      break;
    case 'P':
      $cena+= $cc= $c * $dosp;
      if ( !$cc ) break;
      $ret->c_program+= $cc;
      $program.= "<tr><td>$dosp x $txt ($c$Kc)</td><td align='right'>$cc$Kc</td></tr>";
      break;
    case 'Su':
      $cena-= $cc= $c * $dosp;
      if ( !$cc ) break;
      $ret->c_sleva+= $cc;
      $slevy.= "<tr><td>$dosp x $txt ($c$Kc)</td><td align='right'>$cc$Kc</td></tr>";
      break;
    case 'sc': case 'oc': case 'vc':
      $strav= $dosp * ($pocetdnu + ($za=='oc' && $strava_oddo=='oo' ? 1 : 0)); // případně oběd navíc
      $cena+= $cc= $strav * $c;
      if ( !$cc ) break;
      $ret->c_strava+= $cc;
      $html.= "<tr><td>$strav x $txt ($c$Kc)</td><td align='right'>$cc$Kc</td></tr>";
      break;
    default:
      $ret->err.= "<br>cenu za $za nelze vypočítat";
    }
  }
  // doplnění slev
  if ( $sleva ) {
    $cena-= $sleva;
    $ret->c_sleva-= $sleva;
    $slevy.= "<tr><td>zvláštní sleva</td><td align='right'>$sleva$Kc</td></tr>";
  }
  // konečná redakce textu k ceně dospělých
  if ( $ubytovani ) $html.= "<tr><th>ubytování</th></tr>$ubytovani";
  if ( $strava )    $html.= "<tr><th>strava</th></tr>$strava";
  if ( $program )   $html.= "<tr><th>program</th></tr>$program";
  if ( $slevy )     $html.= "<tr><th>sleva</th></tr>$slevy";
  $html.= "<tr><th>Celkem za dospělé</th><th align='right'>$cena$Kc</th></tr>";
  $html.= "</table>";
  // redakce textu k ceně dětí
  if ( count($deti_kat) ) {
    $html.= "<br><b>Rozpis platby za účast dětí na jejich akci</b><table>";
    $cena= 0;
    ksort($deti_kat);
    foreach($deti_kat as $kat=>$n) {
      $a= $cenik_deti["p$kat"]; $c= $a->c; $txt= $a->txt;
      $cena+= $cc= $c * $n;
      $html.= "<tr><td>$n x $txt </td><td align='right'>$cc$Kc</td></tr>";
    }
    $html.= "<tr><th>Celkem za děti</th><th align='right'>$cena$Kc</th></tr>";
    $html.= "</table>";
  }
end:
  if ( $ret->err ) $ret->navrh.= "<b style='color:red'>POZOR! neúplná platba:</b>{$ret->err}<hr>";
  $ret->navrh.= $html;
                                                        debug($ret,"akce_vzorec_soubeh");
  return $ret;
}
# -------------------------------------------------------------------------------------- akce_vzorec
# výpočet platby za pobyt na akci
# od 130416 přidána položka CENIK.typ - pokud je 0 tak nemá vliv,
#                                       pokud je nenulová pak se bere hodnota podle POBYT.ubytovani
function akce_vzorec($id_pobyt) {  trace();
  $id_akce= 0;
  $ok= true;
  $ret= (object)array('navrh'=>'cenu nelze spočítat','eko'=>(object)array());
  // parametry pobytu
  $x= (object)array();
  $ubytovani= 0;
  $qp= "SELECT * FROM pobyt AS p
        JOIN akce AS a ON p.id_akce=a.id_duakce WHERE id_pobyt=$id_pobyt";
  $rp= mysql_qry($qp);
  if ( $rp && ($p= mysql_fetch_object($rp)) ) {
    $id_akce= $p->id_akce;
    $x->nocoluzka+= $p->luzka * $p->pocetdnu;
    $x->nocoprist+= $p->pristylky * $p->pocetdnu;
    $ucastniku= $p->pouze ? 1 : 2;
    $vzorec= $p->vzorec;
    $ubytovani= $p->ubytovani;
    $sleva= $p->sleva;
    $svp= $p->svp;
    $neprijel= $p->funkce==10;
    $datum_od= $p->datum_od;
  }
  // podrobné parametry, ubytovani ma hodnoty z číselníku ms_akce_ubytovan
  $deti= $koje= $chuv= $dite_chovane= $koje_chovany= 0;
  $chuvy= $del= '';
  $qo= "SELECT o.jmeno,o.narozeni,p.funkce,t.role, p.ubytovani,
         s.pecovane,(SELECT CONCAT(osoba.prijmeni,',',osoba.jmeno,',',pobyt.id_pobyt)
          FROM pobyt
          JOIN spolu ON spolu.id_pobyt=pobyt.id_pobyt
          JOIN osoba ON osoba.id_osoba=spolu.id_osoba
          WHERE pobyt.id_akce=p.id_akce AND spolu.pecovane=o.id_osoba) AS _chuva
        FROM spolu AS s
        JOIN osoba AS o ON s.id_osoba=o.id_osoba
        JOIN pobyt AS p USING(id_pobyt)
        LEFT JOIN tvori AS t ON t.id_osoba=o.id_osoba
        WHERE id_pobyt=$id_pobyt";
  $ro= mysql_qry($qo);
  while ( $ro && ($o= mysql_fetch_object($ro)) ) {
    if ( $o->role=='d' ) {
      $vek= narozeni2roky(sql2stamp($o->narozeni),sql2stamp($datum_od));
      if ( $vek<3 ) {
        $koje++;
        if ( $o->_chuva ) $koje_chovany++;
      }
      else {
        $deti++;
        if ( $o->_chuva ) $dite_chovane++;
      }
      if ( $o->_chuva ) {
        list($prijmeni,$jmeno,$pobyt)= explode(',',$o->_chuva);
        if ( $pobyt!=$id_pobyt ) {
          $chuvy= "$del$jmeno $prijmeni";
          $del= ' a ';
        }
      }
      if ( $o->pecovane ) {
        $chuv++;
      }
    }
  }
//                                                         debug($x,"pobyt");
  // zpracování strav
  $strava= akce_strava_pary($id_akce,'','','',true,$id_pobyt);
//                                                         debug($strava,"strava");
  $jidel= (object)array();
  foreach ($strava->suma as $den_jidlo=>$pocet) {
    list($den,$jidlo)= explode(' ',$den_jidlo);
    $jidel->$jidlo+= $pocet;
  }
//                                                         debug($jidel,"strava");
  // načtení cenového vzorce a ceníku
  $vzor= array();
  $qry= "SELECT * FROM _cis WHERE druh='ms_cena_vzorec' AND data=$vzorec";
  $res= mysql_qry($qry);
  if ( $res && $c= mysql_fetch_object($res) ) {
    $vzor= $c;
    $vzor->slevy= json_decode($vzor->ikona);
    $ret->eko->slevy= $vzor->slevy;
  }
//                                                         debug($vzor);
  // načtení ceníku do pole $cenik s případnou specifikací podle typu ubytování
  $qa= "SELECT * FROM cenik WHERE id_akce=$id_akce ORDER BY poradi";
  $ra= mysql_qry($qa);
  $n= $ra ? mysql_num_rows($ra) : 0;
  if ( !$n ) {
    $html.= "akce {$pobyt->id_akce} nemá cenový vzorec";
    $ok= false;
  }
  else {
    $cenik= array();
    $cenik_typy= false;
    $nazev_ceniku= '';
    while ( $ra && ($a= mysql_fetch_object($ra)) ) {
      $cc= (object)array();
      // diskuse pole typ - je-li nenulové, ignorují se hodnoty různé od POBYT.ubytovani
      if ( $a->typ!=0 && $a->typ!=$ubytovani ) continue;
      $cc->typ= $a->typ;
      if ( $a->typ && !$cenik_typy ) {
        // ceník má typy a tedy pobyt musí mít definované nenulové ubytování
        if ( !$ubytovani ) {
          $html.= "účastník nemá definován typ ubytování, ačkoliv to ceník požaduje";
          $ok= false;
        }
        $cenik_typy= true;
      }
      $pol= $a->polozka;
      if ( $cenik_typy && substr($pol,0,1)=='-' && substr($pol,1,1)!='-' ) {
        // název typu ceníku
        $nazev_ceniku= substr($pol,1);
      }

      $cc->txt= $pol;
      $cc->za= $a->za;
      $cc->c= $a->cena;
      $cc->j= $a->za ? $jidel->{$a->za} : '';
      $cenik[]= $cc;
    }
//                                                         debug($cenik,"ceník pro typ $ubytovani");
  }
  // výpočty
  if ( $ok ) {
    $nl= $x->nocoluzka;
    $np= $x->nocoprist;
    $u= $ucastniku;
    $cena= 0;
    $html.= "<table>";
    // ubytování
    $html.= "<tr><th>ubytování $nazev_ceniku</th></tr>";
    $ret->c_nocleh= 0;
    if ( $vzorec && $vzor->slevy->ubytovani===0 ) {
      $html.= "<tr><td>zdarma</td><td align='right'>0</td></tr>";
    }
    elseif ( $neprijel ) {
      $html.= "<tr><td>storno</td><td align='right'>0</td></tr>";
    }
    else {
      foreach ($cenik as $a) {
      switch ($a->za) {
        case 'Nl':
          $cc= $nl * $a->c;
          if ( !$cc ) break;
          $cena+= $cc;
          $ret->c_nocleh+= $cc;
          $html.= "<tr><td>{$a->txt} ($nl*{$a->c})</td><td align='right'>$cc</td></tr>";
          break;
        case 'Np':
          $cc= $np * $a->c;
          if ( !$cc ) break;
          $cena+= $cc;
          $ret->c_nocleh+= $cc;
          $html.= "<tr><td>{$a->txt} ($np*{$a->c})</td><td align='right'>$cc</td></tr>";
          break;
        }
      }
      $html.= "<tr><td></td><td></td><th align='right'>{$ret->c_nocleh}</th></tr>";
    }
    // strava
    $html.= "<tr><th>strava</th></tr>";
    $ret->c_strava= 0;
    if ( $vzorec && $vzor->slevy->strava===0 ) {
      $html.= "<tr><td>zdarma</td><td align='right'>0</td></tr>";
    }
    else {
      foreach ($cenik as $a) {
        if ( $a->j ) switch ($a->za) {
        case 'sc': case 'sp': case 'oc':
        case 'op': case 'vc': case 'vp':
          $cc= $a->j * $a->c;
          if ( !$cc ) break;
          $cena+= $cc;
          $ret->c_strava+= $cc;
          $html.= "<tr><td>{$a->txt} ({$a->j}*{$a->c})</td><td align='right'>$cc</td></tr>";
          break;
        }
      }
      $html.= "<tr><td></td><td></td><th align='right'>{$ret->c_strava}</th></tr>";
    }
    // program
    $html.= "<tr><th>program</th></tr>";
    $ret->c_program= 0;
    if ( $vzorec && $vzor->slevy->program===0 ) {
      $html.= "<tr><td>program</td><td align='right'>0</td></tr>";
    }
    elseif ( $neprijel ) {
      $html.= "<tr><td>storno</td><td align='right'>0</td></tr>";
    }
    else {
      foreach ($cenik as $a) {
        switch ($a->za) {
        case 'P':
          $cc= $a->c * $u;
          $cena+= $cc;
          $ret->c_program+= $cc;
          $ret->eko->vzorec->{$a->za}+= $cc;
          $html.= "<tr><td>{$a->txt}</td><td align='right'>$cc</td></tr>";
          break;
        case 'Pd':
          if ( $deti - $dite_chovane - $chuv > 0 ) {
            $cc= $a->c * ($deti-$dite_chovane-$chuv);
            $cena+= $cc;
            $ret->c_program+= $cc;
            $ret->eko->vzorec->{$a->za}+= $cc;
            $html.= "<tr><td>{$a->txt}</td><td align='right'>$cc</td></tr>";
          }
          break;
        case 'Pk':
          if ( $koje - $koje_chovany > 0 ) {
            $cc= $a->c * ($koje-$koje_chovany);
            $cena+= $cc;
            $ret->c_program+= $cc;
            $ret->eko->vzorec->{$a->za}+= $cc;
            $html.= "<tr><td>{$a->txt}</td><td align='right'>$cc</td></tr>";
          }
          break;
        }
      }
      $html.= "<tr><td></td><td></td><th align='right'>{$ret->c_program}</th></tr>";
    }
    // případné slevy
    $ret->c_sleva= 0;
    $sleva_cenik= 0;
    $sleva_cenik_html= '';
    foreach ($cenik as $a) {
      switch ($a->za) {
      case 'Su':        // sleva na dospělého účastníka
        $sleva_cenik+= $u * $a->c;
        $sleva_cenik_html.= '';
        break;
      case 'Sk':        // sleva na kojence
        $sleva_cenik+= $koje * $a->c;
        $sleva_cenik_html.= '';
        break;
      }
    }
    $sleva+= $sleva_cenik;
    if ( !$neprijel && ($sleva!=0 || isset($vzor->slevy->procenta) || isset($vzor->slevy->za)) ) {
      $html.= "<tr><th>slevy</th></tr>";
      if ( $sleva!=0 ) {
        $cena-= $sleva;
        $ret->c_sleva-= $sleva;
        $ret->eko->slevy->kc+= $sleva;
        $html.= "<tr><td>sleva z ceny</td><td align='right'>$sleva</td></tr>";
      }
      if ( isset($vzor->slevy->procenta) ) {
        $cc= -round($cena * $vzor->slevy->procenta/100,-1);
        $cena+= $cc;
        $ret->c_sleva+= $cc;
        $html.= "<tr><td>{$vzor->zkratka} {$vzor->slevy->procenta}%</td><td align='right'>$cc</td></tr>";
      }
      if ( isset($vzor->slevy->castka) ) {
        $cc= -$vzor->slevy->castka;
        $cena+= $cc;
        $ret->c_sleva+= $cc;
        $html.= "<tr><td>{$vzor->zkratka} {$vzor->slevy->castka},-</td><td align='right'>$cc</td></tr>";
      }
      if ( isset($vzor->slevy->za) ) {
        $cc= 0;
        foreach ($cenik as $radek) {
          if ( $radek->za==$vzor->slevy->za ) {
            $cc= -$radek->c;
            break;
          }
        }
        $cena+= $cc;
        $ret->c_sleva+= $cc;
        $html.= "<tr><td>{$vzor->zkratka} </td><td align='right'>$cc</td></tr>";
      }
      $html.= "<tr><td></td><td></td><th align='right'>{$ret->c_sleva}</th></tr>";
    }
    $html.= "<tr><th>celkový poplatek</th><td></td><th align='right'>$cena</th></tr>";
    if ( $chuvy ) {
      $html.= "<tr><td colspan=3>(Cena obsahuje náklady na vlastního pečovatele: $chuvy)</td></tr>";
    }
    $html.= "</table>";
    $ret->navrh= $html;
    $ret->mail= "<div style='background-color:#eeeeee;margin-left:15px'>$html</div>";
  }
  else {
    $ret->navrh.= ", protože $html";
  }
  return $ret;
}
# ================================================================================================== PDF
# ------------------------------------------------------------------------------ akce_pdf_stravenky0
# generování stránky stravenek pro ruční vyplnění do PDF
function akce_pdf_stravenky0($akce,$par,$report_json) {  trace();
  global $json, $ezer_path_docs, $EZER;
  $result= (object)array('_error'=>0);
  $html= '';
  // získání dat o akci
  $qa="SELECT nazev, YEAR(datum_od) AS akce_rok, misto
       FROM akce WHERE id_duakce='$akce' ";
  $ra= mysql_qry($qa);
  $a= mysql_fetch_object($ra);
  $header= "{$EZER->options->org}, {$a->misto} {$a->akce_rok}";
  // projdi vygenerované záznamy
  $n= 0;
  $parss= array();
  $pocet= 4*12;
  for ($i= 1; $i<=$pocet; $i++) {
    // text stravenky na jedno jídlo
    $parss[$n]= (object)array();
    $parss[$n]->header= $header;
    $parss[$n]->line1= "$den";
    $parss[$n]->line2= "";
    $parss[$n]->rect=  "";
    $parss[$n]->end= '';
    $parss[$n]->ram= '<img src="db/img/stravenky-rastr-1.png" style="width:48mm;height:23mm" border="0" />';
    $n++;
  }
  for ($i= 1; $i<=$pocet; $i++) {
    // text stravenky na jedno jídlo
    $parss[$n]= (object)array();
    $parss[$n]->header= $header;
    $parss[$n]->line1= "$den";
    $parss[$n]->line2= "";
    $parss[$n]->rect=  "<b>1/2</b>";
    $parss[$n]->end= '';
    $parss[$n]->ram= ' ';
    $n++;
  }
  // předání k tisku
//                                         debug($parss,"akce_pdf_stravenky");
  $fname= 'stravenky_'.date("Ymd_Hi");
  $fpath= "$ezer_path_docs/$fname.pdf";
  dop_rep_ids($report_json,$parss,$fpath);
  $result->html= " Výpis byl vygenerován ve formátu <a href='docs/$fname.pdf' target='pdf'>PDF</a>.";
  return $result;
}
# ------------------------------------------------------------------------------- akce_pdf_stravenky
# generování štítků se stravenkami pro rodinu účastníka a pro pečouny do PDF
# pomocí akce_sestava se do objektu $x->tab vygeneruje pole s elementy pro tisk stravenky
function akce_pdf_stravenky($akce,$par,$report_json) {  trace();
  global $json, $ezer_path_docs, $EZER;
  $result= (object)array('_error'=>0);
  $html= '';
  // získání dat
  $x= akce_sestava($akce,$par,$title,$vypis,true);
  $header= "{$EZER->options->org}, {$x->akce->misto} {$x->akce->rok}";
  $sob= array('s'=>'snídaně','o'=>'oběd','v'=>'večeře');
  $cp=  array('c'=>'1','p'=>'1/2');
  // projdi vygenerované záznamy
  $n= 0;
  $parss= array();
  foreach ( $x->tab as $jmeno=>$dny ) {
    // vynechání prázdných míst, aby jméno bylo v prvním sloupci ze 4
    $k= 4*ceil($n/4)-$n;
    for ($i= 0; $i<$k; $i++) {
      $parss[$n]= (object)array();
      $parss[$n]->header= $parss[$n]->line1= $parss[$n]->line2= '';
      $parss[$n]->rect= $parss[$n]->ram= $parss[$n]->end= '';
      $n++;
    }
    // stravenky pro účastníka
    list($prijmeni,$jmena)= explode('|',$jmeno);
//                                                         if ( $prijmeni!="Bučkovi" ) continue;
    $parss[$n]= (object)array();
    $parss[$n]->header= $header;
    $parss[$n]->line1= "<b>$prijmeni</b>";
    $parss[$n]->line2= "$jmena";
    $parss[$n]->rect= '';
    $parss[$n]->ram= ' ';
    $parss[$n]->end= '';
    $n++;
    foreach ( $dny as $den=>$jidla ) {
      // stravenky na jeden den
      foreach ( $jidla as $jidlo=>$porce ) {
        // denní jídlo
        foreach ( $porce as $velikost=>$pocet ) {
          // porce
          for ($i= 1; $i<=$pocet; $i++) {
            // na začátku stránky dej příznak pokračování
            if ( ($n % (4*12) )==0 ) {
              $parss[$n]= (object)array();
              $parss[$n]->header= $header;
              $parss[$n]->line1= "<b>... $prijmeni</b>";
              $parss[$n]->line2= "... $jmena";
              $parss[$n]->rect= $parss[$n]->ram= $parss[$n]->end= '';
              $n++;
            }
            // text stravenky na jedno jídlo
            $parss[$n]= (object)array();
            $parss[$n]->header= $header;
            $parss[$n]->line1= "$den";
            $parss[$n]->line2= "<b>{$sob[$jidlo]}</b>";
            if ( $velikost=='c' ) {
              // celá porce
              $parss[$n]->ram= '<img src="db/img/stravenky-rastr-1.png"'
                             . ' style="width:48mm" border="0" />';
              $parss[$n]->rect=  " ";
            }
            else {
              // poloviční porce
              $parss[$n]->ram= '';
              $parss[$n]->rect=  "<b>1/2</b>";
            }
            $parss[$n]->end= '';
            $n++;
          }
        }
      }
    }
    // na konec dej koncovou značku
    $parss[$n]= (object)array();
    $parss[$n]->header= $header;
    $parss[$n]->line1= "<b>$prijmeni</b>";
    $parss[$n]->line2= "(konec stravenek)";
    $parss[$n]->rect= $parss[$n]->ram= '';
    $parss[$n]->end= ' ';
    $n++;
  }
  // předání k tisku
//                                         debug($parss,"akce_pdf_stravenky");
  $fname= 'stravenky_'.date("Ymd_Hi");
  $fpath= "$ezer_path_docs/$fname.pdf";
  dop_rep_ids($report_json,$parss,$fpath);
  $result->html= " Výpis byl vygenerován ve formátu <a href='docs/$fname.pdf' target='pdf'>PDF</a>.";
  return $result;
}
# ---------------------------------------------------------------------------------- akce_pdf_prijem
# generování štítků se stručnými informace k nalepení na obálku účastníka do PDF
function akce_pdf_prijem($akce,$par,$report_json) {  trace();
  global $json, $ezer_path_docs;
  $result= (object)array('_error'=>0);
  $html= '';
  // získání dat
  $tab= akce_sestava($akce,$par,$title,$vypis,true);
//                                         debug($tab,"akce_sestava($akce,...)"); //return;
  // projdi vygenerované záznamy
  $n= 0;
  $parss= array();
  foreach ( $tab->clmn as $xa ) {
    // definice pole substitucí
    $x= (object)$xa;
    $parss[$n]= (object)array();
    $parss[$n]->line1= "<b>{$x->prijmeni} {$x->jmena}</b>";
    $parss[$n]->line2= ($x->skupina?"skupinka <b>{$x->skupina}</b> ":'')
                     . ($x->pokoj?"pokoj <b>{$x->pokoj}</b>":'');
    $parss[$n]->line3= $x->luzka || $x->pristylky || $x->kocarek ? (
                       ($x->luzka?"lůžka <b>{$x->luzka}</b> ":'')
                     . ($x->pristylky?"přistýlky <b>{$x->pristylky} </b>":'')
                     . ($x->kocarek?"kočárek <b>{$x->kocarek}</b>":'')
                       ) : "bez ubytování";
    $parss[$n]->line4= $x->strava_cel || $x->strava_pol ? ( "strava: "
                     . ($x->strava_cel?"celá <b>{$x->strava_cel}</b> ":'')
                     . ($x->strava_pol?"poloviční <b>{$x->strava_pol}</b>":'')
                       ) : "bez stravy";
    $n++;
  }
  // předání k tisku
  $fname= 'stitky_'.date("Ymd_Hi");
  $fpath= "$ezer_path_docs/$fname.pdf";
  dop_rep_ids($report_json,$parss,$fpath);
  $result->html= " Výpis byl vygenerován ve formátu <a href='docs/$fname.pdf' target='pdf'>PDF</a>.";
  return $result;
}
# ---------------------------------------------------------------------------------- akce_pdf_stitky
# vygenerování PDF se samolepkami - adresními štítky
#   $the_json obsahuje  title:'{jmeno_postovni}<br>{adresa_postovni}'
function akce_pdf_stitky($cond,$report_json) { trace();
  global $json, $ezer_path_docs;
  $result= (object)array('_error'=>0);
  // projdi požadované adresy rodin
  $n= 0;
  $parss= array();
  $qry=  "SELECT
          r.nazev as nazev,p.pouze as pouze,
          GROUP_CONCAT(DISTINCT IF(t.role='a',o.prijmeni,'') SEPARATOR '') as prijmeni_m,
          GROUP_CONCAT(DISTINCT IF(t.role='a',o.jmeno,'')    SEPARATOR '') as jmeno_m,
          GROUP_CONCAT(DISTINCT IF(t.role='a',o.narozeni,'') SEPARATOR '') as narozeni_m,
          GROUP_CONCAT(DISTINCT IF(t.role='a',o.rc_xxxx,'')  SEPARATOR '') as rc_xxxx_m,
          GROUP_CONCAT(DISTINCT IF(t.role='b',o.prijmeni,'') SEPARATOR '') as prijmeni_z,
          GROUP_CONCAT(DISTINCT IF(t.role='b',o.jmeno,'')    SEPARATOR '') as jmeno_z,
          GROUP_CONCAT(DISTINCT IF(t.role='b',o.narozeni,'') SEPARATOR '') as narozeni_z,
          GROUP_CONCAT(DISTINCT IF(t.role='b',o.rc_xxxx,'')  SEPARATOR '') as rc_xxxx_z,
          r.ulice,r.psc,r.obec,r.stat,r.telefony,r.emaily,p.poznamka
          FROM pobyt AS p
          JOIN spolu AS s USING(id_pobyt)
          JOIN osoba AS o ON s.id_osoba=o.id_osoba
          LEFT JOIN tvori AS t ON t.id_osoba=o.id_osoba
          LEFT JOIN rodina AS r USING(id_rodina)
          WHERE $cond
          GROUP BY id_pobyt
          ORDER BY IF(funkce<=2,1,funkce),IF(pouze=0,r.nazev,o.prijmeni)";
  $res= mysql_qry($qry);
  while ( $res && ($x= mysql_fetch_object($res)) ) {
    $x->prijmeni= $x->pouze==1 ? $x->prijmeni_m : ($x->pouze==2 ? $x->prijmeni_z : $x->nazev);
    $x->jmena=    $x->pouze==1 ? $x->jmeno_m    : ($x->pouze==2 ? $x->jmeno_z : "{$x->jmeno_m} a {$x->jmeno_z}");
    // formátované PSČ (tuzemské a slovenské)
    $psc= (!$x->stat||$x->stat=='CZ'||$x->stat=='SK')
      ? substr($x->psc,0,3).' '.substr($x->psc,3,2)
      : $x->psc;
    $stat= $x->stat=='CZ' ? '' : $x->stat;
    // definice pole substitucí
    $parss[$n]= (object)array();
    $parss[$n]->jmeno_postovni= "{$x->jmena} {$x->prijmeni}";
    $parss[$n]->adresa_postovni= "{$x->ulice}<br/>$psc  {$x->obec}".( $stat ? "<br/>        $stat" : "");
    $n++;
  }
  // předání k tisku
  $fname= 'stitky_'.date("Ymd_Hi");
  $fpath= "$ezer_path_docs/$fname.pdf";
  dop_rep_ids($report_json,$parss,$fpath);
  $result->html= " Výpis byl vygenerován ve formátu <a href='docs/$fname.pdf' target='pdf'>PDF</a>.";
  return $result;
}
# -------------------------------------------------------------------------------- akce_pdf_jmenovky
# vygenerování PDF s vizitkami s rozměrem 55x90 na rozstříhání
#   $the_json obsahuje  title:'{jmeno}<br>{prijmeni}'
function akce_pdf_jmenovky($akce,$par,$report_json) {  trace();
  global $json, $ezer_path_docs;
  $result= (object)array('_error'=>0);
  $html= '';
  // získání dat
  mb_internal_encoding('UTF-8');
  $tab= akce_sestava($akce,$par,$title,$vypis,true);
//                                         display($report_json);
//                                         debug($tab,"akce_sestava($akce,...)"); //return;
  $report_json= "{'format':'A4:15,10,90,55','boxes':["
    . "{'type':'text','left':0,'top':0,'width':90,'height':55,'id':'ram','style':'1,L,LTRB:0.05 dotted 250',txt:' '},"
    . "{'type':'text','left':10,'top':10,'width':80,'height':40,'id':'jmeno','txt':'{jmeno}<br />{prijmeni}','style':'30,L'}]}";
  $report_json= "{'format':'A4:15,10,90,55','boxes':["
    . "{'type':'text','left':0,'top':0,'width':90,'height':55,'id':'ram','style':'1,L,LTRB:0.05 dotted',txt:' '},"
    . "{'type':'text','left':10,'top':10,'width':80,'height':40,'id':'jmeno','txt':'{jmeno}<br />{prijmeni}','style':'30,L'}]}";
//                                         display($report_json);
  // projdi vygenerované záznamy
  $n= 0;
  $parss= array();
  foreach ( $tab->clmn as $xa ) {
    // definice pole substitucí
    $x= (object)$xa;
    $parss[$n]= (object)array();
    $fsize= mb_strlen($x->jmeno)>8 ? 13 : 14;
    $parss[$n]->jmeno= "<span style=\"font-size:{$fsize}mm;font-weight:bold\">{$x->jmeno}</span>";
    list($prijmeni)= explode(' ',$x->prijmeni);
    $fsize= mb_strlen($prijmeni)>10 ? 10 : 12;
    $parss[$n]->prijmeni= "<span style=\"font-size:{$fsize}mm;font-weight:bold\">{$prijmeni}</span>";
    $n++;
  }
  // předání k tisku
  $fname= 'jmenovky_'.date("Ymd_Hi");
  $fpath= "$ezer_path_docs/$fname.pdf";
  dop_rep_ids($report_json,$parss,$fpath);
  $result->html= " Výpis byl vygenerován ve formátu <a href='docs/$fname.pdf' target='pdf'>PDF</a>.";
  return $result;
}
# -------------------------------------------------------------------------------------- dop_rep_ids
# LOCAL
# vytvoření dopisů se šablonou pomocí TCPDF podle parametrů
# $parss  - pole obsahující substituce parametrů pro $text
# vygenerované dopisy ve tvaru souboru PDF se umístí do ./docs/$fname
# případná chyba se vrátí jako Exception
function dop_rep_ids($report_json,$parss,$fname) { trace();
  global $json;
  $err= 0;
  // transformace $parss pro strtr
  $subst= array();
  for ($i=0; $i<count($parss); $i++) {
    $subst[$i]= array();
    foreach($parss[$i] as $x=>$y) {
      $subst[$i]['{'.$x.'}']= $y;
    }
  }
  $report= $json->decode($report_json);
//                                                         debug($report,"dop_rep_ids");
  // vytvoření $texty - seznam
  $texty= array();
  for ($i=0; $i<count($parss); $i++) {
    $texty[$i]= (object)array();
    foreach($report->boxes as $box) {
      $id= $box->id;
      if ( !$id) fce_error("dop_rep_ids: POZOR: box reportu musí být pojmenován");
      $texty[$i]->$id= strtr($box->txt,$subst[$i]);
    }
  }
//                                                         debug($texty,'dop_rep_ids');
  tc_report($report,$texty,$fname);
}
# ================================================================================================== SYSTEM-DATA
# ================================================================================================== VÝPISY
# výběr generátoru sestavy
# ------------------------------------------------------------------------------------ akce_sestava2
# generování sestav
#   $typ = j | p | vp | vs | vn | vv | vj | sk | sd | d | fs | ...
function akce_sestava($akce,$par,$title,$vypis,$export=false) {
  return $par->typ=='p'  ? akce_sestava_pary($akce,$par,$title,$vypis,$export)
     : ( $par->typ=='j'  ? akce_sestava_lidi($akce,$par,$title,$vypis,$export)
     : ( $par->typ=='vp' ? akce_vyuctov_pary($akce,$par,$title,$vypis,$export)
     : ( $par->typ=='vs' ? akce_strava_pary($akce,$par,$title,$vypis,$export)  // bez náhradníků
     : ( $par->typ=='vj' ? akce_stravenky($akce,$par,$title,$vypis,$export)
     : ( $par->typ=='vjp'? akce_stravenky($akce,$par,$title,$vypis,$export)
     : ( $par->typ=='vn' ? akce_sestava_noci($akce,$par,$title,$vypis,$export)
     : ( $par->typ=='vv' ? akce_text_vyroci($akce,$par,$title,$vypis,$export)
     : ( $par->typ=='vi' ? akce_text_prehled($akce,$par,$title,$vypis,$export)
     : ( $par->typ=='ve' ? akce_text_eko($akce,$par,$title,$vypis,$export)
     : ( $par->typ=='sk' ? akce_skupinky($akce,$par,$title,$vypis,$export)
     : ( $par->typ=='sd' ? akce_skup_deti($akce,$par,$title,$vypis,$export)
     : ( $par->typ=='d'  ? akce_sestava_pecouni($akce,$par,$title,$vypis,$export)
     : ( $par->typ=='fs' ? akce_fotoseznam($akce,$par,$title,$vypis,$export)
     : ( $par->typ=='fx' ? akce_sestava_spec($akce,$par,$title,$vypis,$export)
     : ( $par->typ=='12' ? akce_jednou_dvakrat($akce,$par,$title,$vypis,$export)
     : ( $par->typ=='cz' ? akce_cerstve_zmeny($akce,$par,$title,$vypis,$export)
                         : fce_error("akce_sestava: N.Y.I.") ))))))))))))))));
}
# --------------------------------------------------------------------------------------- akce_table
function akce_table($tits,$flds,$clmn,$export=false) {
  $result= (object)array();
  // zobrazení tabulkou
  $tab= '';
  $thd= '';
  $n= 0;
  if ( $export ) {
    $result->tits= $tits;
    $result->flds= $flds;
    $result->clmn= $clmn;
  }
  else {
    // titulky
    foreach ($tits as $idw) {
      list($id)= explode(':',$idw);
      $ths.= "<th>$id</th>";
    }
    foreach ($clmn as $i=>$c) {
      $tab.= "<tr>";
      foreach ($flds as $f) {
        $tab.= "<td style='text-align:left'>{$c[$f]}</td>";
      }
      $tab.= "</tr>";
      $n++;
    }
    $result->html= "<div class='stat'><table class='stat'><tr>$ths</tr>$tab</table>$n řádků</div>";
  }
  return $result;
}
# -------------------------------------------------------------------------------- akce_sestava_spec
# generování technických seznamů: emaily
function akce_sestava_spec($akce,$par,$title,$vypis,$export=false) { trace();
  $result= (object)array('html'=>'');
  $ems= array();
  switch($par->subtyp) {
  case 'emails':
    // získání seznamu emailů
    $qry=  "SELECT
              r.emaily, p.pouze,
              GROUP_CONCAT(DISTINCT IF(t.role='a',o.email,'') SEPARATOR ',') as email_m,
              GROUP_CONCAT(DISTINCT IF(t.role='b',o.email,'') SEPARATOR ',') as email_z
            FROM pobyt AS p
            JOIN spolu AS s USING(id_pobyt)
            JOIN osoba AS o ON s.id_osoba=o.id_osoba
            LEFT JOIN tvori AS t ON t.id_osoba=o.id_osoba
            LEFT JOIN rodina AS r USING(id_rodina)
            WHERE p.id_akce='$akce'
            GROUP BY id_pobyt";
    $res= mysql_qry($qry);
    while ( $res && ($x= mysql_fetch_object($res)) ) {
      // extrakce emailů
      $a_emaily_m= preg_split("/,\s*|;\s*/",trim($x->email_m," ,;"),-1,PREG_SPLIT_NO_EMPTY);
//                                                 debug($a_emaily_m,'m');
      $a_emaily_z= preg_split("/,\s*|;\s*/",trim($x->email_z," ,;"),-1,PREG_SPLIT_NO_EMPTY);
//                                                 debug($a_emaily_z,'z');
      $a_emaily= preg_split("/,\s*|;\s*/",trim($x->emaily," ,;"),-1,PREG_SPLIT_NO_EMPTY);
//                                                 debug($a_emaily,'r');
      if ( count($a_emaily)) $ems= array_merge($ems,$a_emaily);
      if ( (!$x->pouze || $x->pouze==1) && count($a_emaily_m)>0 ) $ems= array_merge($ems,$a_emaily_m);
      if ( (!$x->pouze || $x->pouze==2) && count($a_emaily_z)>0 ) $ems= array_merge($ems,$a_emaily_z);
    }
    $ems= array_unique($ems);
    $result->html= implode(', ',$ems);
    break;
  }
//                                                 debug($ems,count($ems));
  return $result;
}
# ------------------------------------------------------------------------------ akce_jednou_dvakrat
# generování seznamu změn v pobytech na akci od par-datetime
function akce_cerstve_zmeny($akce,$par,$title,$vypis,$export=false) { trace();
  $result= (object)array('html'=>'');
  $od= $par->datetime= "2013-09-25 18:00";
  $p_flds= "'luzka','pokoj','pristylky','pocetdnu'";
  //
  $tits= explode(',',"_ucastnik,kdy,fld,old,val,kdo,id_track");
  $flds= $tits;
  $clmn= array();
  $n= 0;
  $ord= "
    CASE
      WHEN pouze=0 THEN r.nazev
      WHEN pouze=1 THEN GROUP_CONCAT(DISTINCT IF(t.role='a',o.prijmeni,'') SEPARATOR '')
      WHEN pouze=2 THEN GROUP_CONCAT(DISTINCT IF(t.role='b',o.prijmeni,'') SEPARATOR '')
    END";
  $rz= mysql_qry("
    SELECT id_track,kdy,kdo,klic,fld,op,old,val,
      GROUP_CONCAT(DISTINCT IF(t.role='a',o.prijmeni,'') SEPARATOR '') as prijmeni_m,
      GROUP_CONCAT(DISTINCT IF(t.role='a',o.jmeno,'')    SEPARATOR '') as jmeno_m,
      GROUP_CONCAT(DISTINCT IF(t.role='b',o.prijmeni,'') SEPARATOR '') as prijmeni_z,
      GROUP_CONCAT(DISTINCT IF(t.role='b',o.jmeno,'')    SEPARATOR '') as jmeno_z,
      r.nazev as nazev,p.pouze as pouze
    FROM _track
    JOIN pobyt AS p ON p.id_pobyt=klic
    JOIN akce AS a ON a.id_duakce=p.id_akce
    JOIN spolu AS s USING(id_pobyt)
    JOIN osoba AS o ON s.id_osoba=o.id_osoba
    LEFT JOIN tvori AS t ON t.id_osoba=o.id_osoba
    LEFT JOIN rodina AS r USING(id_rodina)
    WHERE kde='pobyt' AND kdy>='$od' AND id_akce=$akce AND fld IN ($p_flds) AND old!=val AND old!=''
    GROUP BY id_track,id_pobyt
    ORDER BY $ord,kdy
  ");
  while ( $rz && ($z= mysql_fetch_object($rz)) ) {
    $prijmeni= $z->pouze==1 ? $z->prijmeni_m : ($z->pouze==2 ? $z->prijmeni_z : $z->nazev);
    $jmena=    $z->pouze==1 ? $z->jmeno_m    : ($z->pouze==2 ? $z->jmeno_z : "{$z->jmeno_m} a {$z->jmeno_z}");
    $clmn[$n]= array();
    foreach($flds as $f) {
      switch ($f) {
      case '_ucastnik': $clmn[$n][$f]= "$prijmeni $jmena"; break;
      default:          $clmn[$n][$f]= $z->$f;
      }
    }
    $n++;
  }
  $result->html= "od $od bylo provedeno $n změn";
//                                         debug($clmn,"sestava pro $akce,$typ,$fld,$cnd");
  return akce_table($tits,$flds,$clmn,$export);
}
# ------------------------------------------------------------------------------ akce_jednou_dvakrat
# generování seznamu jedno- a dvou-ročáků spolu s mailem na VPS
#   $fld = seznam položek s prefixem
#   $cnd = podmínka
function akce_jednou_dvakrat($akce,$par,$title,$vypis,$export=false) { trace();
  $result= (object)array('html'=>'');
  $vps= array();
  $n= 0;
  $qry=  "SELECT
            r.nazev as nazev,
            ( SELECT CONCAT(nazev,' ',emaily,' ',email,'/',funkce )
              FROM rodina
              JOIN tvori ON rodina.id_rodina=tvori.id_rodina
              JOIN osoba ON osoba.id_osoba=tvori.id_osoba
              JOIN spolu ON spolu.id_osoba=osoba.id_osoba
              JOIN pobyt ON pobyt.id_pobyt=spolu.id_pobyt
              WHERE pobyt.id_akce='$akce' AND skupina=p.skupina AND role='a'
              ORDER BY funkce DESC LIMIT 1) as skup,
            GROUP_CONCAT(DISTINCT IF(t.role='a',o.jmeno,'')    SEPARATOR '') as jmeno_m,
            GROUP_CONCAT(DISTINCT IF(t.role='b',o.jmeno,'')    SEPARATOR '') as jmeno_z,
            ( SELECT count(DISTINCT cp.id_pobyt) FROM pobyt AS cp
              JOIN akce AS ca ON ca.id_duakce=cp.id_akce
              JOIN spolu AS cs ON cp.id_pobyt=cs.id_pobyt
              JOIN osoba AS co ON cs.id_osoba=co.id_osoba
              LEFT JOIN tvori AS ct ON ct.id_osoba=co.id_osoba
              LEFT JOIN rodina AS cr ON cr.id_rodina=ct.id_rodina
              WHERE ca.druh=1 AND cr.id_rodina=r.id_rodina ) AS _ucasti
          FROM pobyt AS p
          JOIN spolu AS s USING(id_pobyt)
          JOIN osoba AS o ON s.id_osoba=o.id_osoba
          LEFT JOIN tvori AS t ON t.id_osoba=o.id_osoba
          LEFT JOIN rodina AS r USING(id_rodina)
          WHERE p.id_akce='$akce' AND p.skupina!=0
          GROUP BY id_pobyt HAVING _ucasti IN (1,2)
          ORDER BY nazev";
  $res= mysql_qry($qry);
  while ( $res && ($x= mysql_fetch_object($res)) ) {
    $par= "{$x->_ucasti}x {$x->jmeno_m} a {$x->jmeno_z} {$x->nazev}";
    $vps[$x->skup][]= $par;
    $n++;
  }
  ksort($vps);
//                                         debug($vps,"jednou - dvakrát");
  $html= "<h3>Pracovní seznam párů, kteří jsou na MS poprvé (1x) nebo podruhé (2x), spolu s jejich $VPS</h3>";
  foreach ($vps as $v => $ps) {
    $html.= "<p><b>$v</b>";
    foreach ($ps as $p) {
      $html.= "<br>$p";
    }
    $html.= "</p>";
  }
  $result->html= $html;
  return $result;
}
# ---------------------------------------------------------------------------------- akce_fotoseznam
# generování HTML kódu pro zobrazování fotek na CD akce
function akce_fotoseznam($akce,$par,$title,$vypis,$export=false) { trace();
  global $ezer_path_root;
  $result= (object)array();
  $cnd= $par->cnd;
  $ord= $par->ord ? $par->ord : "IF(funkce<=2,1,funkce),IF(pouze=0,r.nazev,o.prijmeni)";
  $fotky= "";
  // získání seznamu fotek a jmen
  $qry=  "SELECT
            p.pouze as pouze, r.nazev AS nazev,
            GROUP_CONCAT(DISTINCT IF(t.role='a',o.prijmeni,'') SEPARATOR '') as prijmeni_m,
            GROUP_CONCAT(DISTINCT IF(t.role='a',o.jmeno,'')    SEPARATOR '') as jmeno_m,
            GROUP_CONCAT(DISTINCT IF(t.role='b',o.prijmeni,'') SEPARATOR '') as prijmeni_z,
            GROUP_CONCAT(DISTINCT IF(t.role='b',o.jmeno,'')    SEPARATOR '') as jmeno_z,
            r.fotka
          FROM pobyt AS p
          JOIN spolu AS s USING(id_pobyt)
          JOIN osoba AS o ON s.id_osoba=o.id_osoba
          LEFT JOIN tvori AS t ON t.id_osoba=o.id_osoba
          LEFT JOIN rodina AS r USING(id_rodina)
          WHERE p.id_akce='$akce' AND $cnd AND fotka!=''
          GROUP BY id_pobyt
          ORDER BY $ord";
  $res= mysql_qry($qry);
  while ( $res && ($x= mysql_fetch_object($res)) ) {
    $prijmeni= $x->pouze==1 ? $x->prijmeni_m : ($x->pouze==2 ? $x->prijmeni_z : $x->nazev);
    $jmena=    $x->pouze==1 ? $x->jmeno_m    : ($x->pouze==2 ? $x->jmeno_z : "{$x->jmeno_m} a {$x->jmeno_z}");
    $fotka=    $x->fotka;
    $fotky.= "\n      '$fotka','$prijmeni $jmena',";
  }
  // generování skriptu
  $script= <<<__EOD
    <script language='JavaScript'>
      var portrety= [
        $fotky
        0
      ];
      var portret= portrety.length/2;
      function showNextP() {
        if (portret<portrety.length-2) portret+= 2;
        showPortret(portret);
      }
      function showPrevP() {
        if (portret>0) portret-= 2;
        showPortret(portret);
      }
      function showPortret(f) {
        portret= f; image= portrety[f]; text= portrety[f+1];
        portret_img.src="fotky/" + image;
        portret_title.innerHTML= "<b><big>" + text + "</big></b>";
        portret_number.innerHTML= "<b>" + (1+f/2) + "/" + (portrety.length/2) + "</b>";
        portret_prior.src= "img/left.gif"; portret_next.src= "img/right.gif";
        if (portret==0) portret_prior.src= 'img/clear.gif';
        if (portret==portrety.length-2) portret_next.src= 'img/clear.gif' ;
      }
      function showThumbP(f) {
        image= portrety[f];
        text= "<font color=#d0b090>" + portrety[f+1] + "</font>";
        document.write("<img border=0  src='fotky/copy/" + image + "' "
        + " onClick=\"showPortret(" + f + ");\" "
        + "  style='cursor:pointer;' title='klikni pro zvětšení'><div style='width:120px;text-align:center'>"
        + text+"</div>");
      }
    </script>
    <table height=640 border=0 style="background-color:#aa6633;color:#004080">
     <tr>
      <td width=165 align=center>
        <div style="overflow:auto;height:630px;width:155px;background-image:url('img/film120b.gif')">
          <script><!--
            for (f=0;f<portrety.length;f+=2) showThumbP(f);
          --></script>
        </div>
      </td>
      <td width=820 valign=top>
        <table width=820 style='color:#004080;font-weight:bold;' border=0>
         <tr>
          <td width=420 id='portret_title'></td>
          <td width=30><img  id=portret_prior src='img/left.gif' style="cursor:pointer"
            onClick="javascript:showPrevP();"></td>
          <td width=60 id=portret_number align=center>n/m</td>
          <td width=30><img  id=portret_next src='img/right.gif' style="cursor:pointer"
            onClick="javascript:showNextP();"></td>
         </tr>
         <tr>
          <td width=540 colspan=4>
            <a href=#><img border=0 name='portret_img' class=image></a>
            <script>showPortret(portret);</script>
          </td>
         </tr>
        </table>
      </td>
     </tr>
    </table>
__EOD;
  $fname= 'fotoseznam'.date("-Ymd_Hi").'.htm';
  $path= "$ezer_path_root/docs/$fname";
  file_put_contents($path,$script);
  $result->html= "Tento skript lze stáhnout <a href='docs/$fname' target='fotoseznam'>zde</a>.
    Fotky je třeba resamplovat na velikost 800x600 a miniatury na 120x90 <br><br> $script";
  return $result;
}
# ----------------------------------------------------------------------------- akce_sestava_pecouni
# generování sestavy pro účastníky $akce - pečouny
#   $fld = seznam položek s prefixem
#   $cnd = podmínka
function akce_sestava_pecouni($akce,$par,$title,$vypis,$export=false) { trace();
  $result= (object)array();
  $typ= $par->typ;
  $tit= $par->tit;
  $fld= $par->fld;
  $cnd= $par->cnd;
  $ord= $par->ord ? $par->ord : "CONCAT(o.prijmeni,' ',o.jmeno)";
  $html= '';
  $href= '';
  $n= 0;
  // dekódování parametrů
  $tits= explode(',',$tit);
  $flds= explode(',',$fld);
  // číselníky
  $pfunkce= map_cis('ms_akce_pfunkce','zkratka');  $pfunkce[0]= '?';
  // získání dat - podle $kdo
  $clmn= array();
  $expr= array();       // pro výrazy
  // data akce
  $qry= " SELECT o.prijmeni,o.jmeno,o.narozeni,o.rc_xxxx,o.ulice,o.psc,o.obec,o.telefon,o.email,
            s.skupinka as skupinka,s.pfunkce,
            IF(o.note='' AND s.poznamka='','',CONCAT(o.note,' / ',s.poznamka)) AS _poznamky
          FROM pobyt AS p
          JOIN spolu AS s USING(id_pobyt)
          JOIN osoba AS o ON s.id_osoba=o.id_osoba
          WHERE p.funkce=99 AND p.id_akce='$akce' AND $cnd
          ORDER BY $ord";
  $res= mysql_qry($qry);
  while ( $res && ($x= mysql_fetch_object($res)) ) {
    $n++;
    $clmn[$n]= array();
    foreach($flds as $f) {
      switch ($f) {
      case 'pfunkce':   $clmn[$n][$f]= $pfunkce[$x->$f]; break;
      default:          $clmn[$n][$f]= $x->$f;
      }
    }
  }
//                                         debug($clmn,"sestava pro $akce,$typ,$fld,$cnd");
  return akce_table($tits,$flds,$clmn,$export);
}
# -------------------------------------------------------------------------------- akce_sestava_lidi
# generování sestavy pro účastníky $akce - jednotlivce
#   $fld = seznam položek s prefixem
#   $cnd = podmínka
function akce_sestava_lidi($akce,$par,$title,$vypis,$export=false) { trace();
  $result= (object)array();
  $typ= $par->typ;
  $tit= $par->tit;
  $fld= $par->fld;
  $cnd= $par->cnd;
  $ord= $par->ord ? $par->ord : "IF(funkce<=2,1,funkce),IF(pouze=0,r.nazev,o.prijmeni)";
  $html= '';
  $href= '';
  $n= 0;
  // dekódování parametrů
  $tits= explode(',',$tit);
  $flds= explode(',',$fld);
  // číselníky
  $pfunkce= map_cis('ms_akce_pfunkce','zkratka');  $pfunkce[0]= '?';
  // získání dat - podle $kdo
  $clmn= array();
  $expr= array();       // pro výrazy
  // případné zvláštní řazení
  switch ($ord) {
  case '_zprava':
    $ord= "CASE WHEN _vek<6 THEN 1 WHEN _vek<18 THEN 2 WHEN _vek<26 THEN 3 ELSE 9 END,prijmeni";
    break;
  }
  // data akce
  $qry=  "SELECT
            p.pouze,p.poznamka,
            o.prijmeni,o.jmeno,o.narozeni,o.rc_xxxx,o.note,o.obcanka,o.clen,
            IF(o.telefon='',r.telefony,o.telefon) AS telefon,
            IF(o.email='',r.emaily,o.email) AS email,
            IF(o.ulice='',r.ulice,o.ulice) AS ulice,
            IF(o.psc='',r.psc,o.psc) AS psc,
            IF(o.obec='',r.obec,o.obec) AS obec,
            s.poznamka AS s_note,s.pfunkce,
            r.note AS r_note,
            ROUND(DATEDIFF(a.datum_od,o.narozeni)/365.2425,1) AS _vek,
            (SELECT GROUP_CONCAT(prijmeni,' ',jmeno)
              FROM akce JOIN pobyt ON id_akce=akce.id_duakce
              JOIN spolu ON spolu.id_pobyt=pobyt.id_pobyt
              JOIN osoba ON osoba.id_osoba=spolu.id_osoba
              WHERE spolu.pecovane=o.id_osoba AND id_akce=$akce) AS _chuva,
            (SELECT CONCAT(prijmeni,' ',jmeno) FROM osoba
              WHERE s.pecovane=osoba.id_osoba) AS _chovany
          FROM pobyt AS p
          JOIN spolu AS s USING(id_pobyt)
          JOIN osoba AS o ON o.id_osoba=s.id_osoba
          LEFT JOIN tvori AS t ON t.id_osoba=o.id_osoba
          LEFT JOIN rodina AS r USING(id_rodina)
          JOIN akce AS a ON a.id_duakce=p.id_akce
          WHERE p.id_akce='$akce' AND $cnd
          GROUP BY o.prijmeni,o.jmeno,o.narozeni
          ORDER BY $ord";
  $res= mysql_qry($qry);
  while ( $res && ($x= mysql_fetch_object($res)) ) {
    $n++;
    $clmn[$n]= array();
    // doplnění počítaných položek
    $x->narozeni_dmy= sql_date1($x->narozeni);
    foreach($flds as $f) {
      switch ($f) {
      case '_1':
        $clmn[$n][$f]= 1;
        break;
      case '_ar_note':                                                // k akci: rodina/osoba
        $clmn[$n][$f]= "{$x->poznamka} / {$x->s_note}";
        break;
      case '_tr_note':                                                // trvalá: rodina/osoba
        $clmn[$n][$f]= "{$x->r_note} / {$x->note}";
        break;
      case '_a_note':                                                 // k akci: osoba
        $clmn[$n][$f]= $x->s_note;
//         $clmn[$n][$f]= $x->s_note ? $x->$f.' / '.$x->s_note : $x->$f;
        break;
      case '_t_note':                                                 // trvalá: osoba
        $clmn[$n][$f]= $x->note;
//         $clmn[$n][$f]= $x->s_note ? $x->$f.' / '.$x->s_note : $x->$f;
        break;
      case '_narozeni6':
        $nar= $x->narozeni;
        $clmn[$n][$f]= "'".substr($nar,2,2).substr($nar,5,2).substr($nar,8,2);
        break;
      case '_ymca':
        $clmn[$n][$f]= $x->clen ? $x->clen : '';
        break;
      case 'pfunkce':
        $pf= $x->$f;
        $clmn[$n][$f]= !$pf ? 'skupinka' : (
            $pf==4 ? 'pomocný p.' : (
            $pf==5 || $pf==95 ? "os.peč. pro: {$x->_chovany}" : (
            $pf==8 ? 'skupina G' : (
            $pf==92 ? "os.peč. je: {$x->_chuva}" : '?'))));
        break;
      default: $clmn[$n][$f]= $x->$f;
      }
    }
  }
//                                         debug($clmn,"sestava pro $akce,$typ,$fld,$cnd");
  return akce_table($tits,$flds,$clmn,$export);
}
# -------------------------------------------------------------------------------- akce_sestava_pary
# generování sestavy pro účastníky $akce - páry
#   $fld = seznam položek s prefixem
#   $cnd = podmínka
function akce_sestava_pary($akce,$par,$title,$vypis,$export=false) { trace();
  $result= (object)array();
  $typ= $par->typ;
  $tit= $par->tit;
  $fld= $par->fld;
  $cnd= $par->cnd ? $par->cnd : 1;
  $hav= $par->hav ? "HAVING {$par->hav}" : '';
  $ord= $par->ord ? $par->ord : "
    CASE
      WHEN pouze=0 THEN r.nazev
      WHEN pouze=1 THEN GROUP_CONCAT(DISTINCT IF(t.role='a',o.prijmeni,'') SEPARATOR '')
      WHEN pouze=2 THEN GROUP_CONCAT(DISTINCT IF(t.role='b',o.prijmeni,'') SEPARATOR '')
    END";
//   IF(funkce<=2,1,funkce),IF(pouze=0,r.nazev,o.prijmeni)";
  $html= '';
  $href= '';
  $n= 0;
  // číselníky
  $c_ubytovani= map_cis('ms_akce_ubytovan','zkratka');  $c_ubytovani[0]= '?';
  // dekódování parametrů
  $tits= explode(',',$tit);
  $flds= explode(',',$fld);
  $cond= $cnd;
  // získání dat - podle $kdo
  $clmn= array();
  $expr= array();       // pro výrazy
  // data akce
  $qry=  "SELECT
            r.nazev as nazev,p.pouze as pouze,p.poznamka,
            GROUP_CONCAT(DISTINCT IF(t.role='a',o.prijmeni,'') SEPARATOR '') as prijmeni_m,
            GROUP_CONCAT(DISTINCT IF(t.role='a',o.jmeno,'')    SEPARATOR '') as jmeno_m,
            GROUP_CONCAT(DISTINCT IF(t.role='a',o.narozeni,'') SEPARATOR '') as narozeni_m,
            GROUP_CONCAT(DISTINCT IF(t.role='a',o.rc_xxxx,'')  SEPARATOR '') as rc_xxxx_m,
            GROUP_CONCAT(DISTINCT IF(t.role='a',o.email,'')    SEPARATOR '') as email_m,
            GROUP_CONCAT(DISTINCT IF(t.role='a',o.telefon,'')  SEPARATOR '') as telefon_m,
            GROUP_CONCAT(DISTINCT IF(t.role='b',o.prijmeni,'') SEPARATOR '') as prijmeni_z,
            GROUP_CONCAT(DISTINCT IF(t.role='b',o.jmeno,'')    SEPARATOR '') as jmeno_z,
            GROUP_CONCAT(DISTINCT IF(t.role='b',o.narozeni,'') SEPARATOR '') as narozeni_z,
            GROUP_CONCAT(DISTINCT IF(t.role='b',o.rc_xxxx,'')  SEPARATOR '') as rc_xxxx_z,
            GROUP_CONCAT(DISTINCT IF(t.role='b',o.email,'')    SEPARATOR '') as email_z,
            GROUP_CONCAT(DISTINCT IF(t.role='b',o.telefon,'')  SEPARATOR '') as telefon_z,
            ( SELECT count(DISTINCT cp.id_pobyt) FROM pobyt AS cp
              JOIN akce AS ca ON ca.id_duakce=cp.id_akce
              JOIN spolu AS cs ON cp.id_pobyt=cs.id_pobyt
              JOIN osoba AS co ON cs.id_osoba=co.id_osoba
              LEFT JOIN tvori AS ct ON ct.id_osoba=co.id_osoba
              LEFT JOIN rodina AS cr ON cr.id_rodina=ct.id_rodina
              WHERE ca.druh=1 AND cr.id_rodina=r.id_rodina ) AS _ucasti,
            SUM(IF(t.role='d',1,0)) as _deti,
            r.ulice,r.psc,r.obec,r.telefony,r.emaily,r.note/* AS r_note*/,p.skupina,
            p.ubytovani,p.budova,p.pokoj,
            p.luzka,p.kocarek,p.pristylky,p.strava_cel,p.strava_pol,
            GROUP_CONCAT(IFNULL((SELECT CONCAT(osoba.jmeno,' ',osoba.prijmeni)
              FROM pobyt
              JOIN spolu ON spolu.id_pobyt=pobyt.id_pobyt
              JOIN osoba ON osoba.id_osoba=spolu.id_osoba
              WHERE pobyt.id_akce='369' AND spolu.pecovane=o.id_osoba),'') SEPARATOR ' ') AS _chuvy
          FROM pobyt AS p
          JOIN spolu AS s USING(id_pobyt)
          JOIN osoba AS o ON s.id_osoba=o.id_osoba
          LEFT JOIN tvori AS t ON t.id_osoba=o.id_osoba
          LEFT JOIN rodina AS r USING(id_rodina)
          WHERE p.id_akce='$akce' AND $cnd
          GROUP BY id_pobyt $hav
          ORDER BY $ord";
  $res= mysql_qry($qry);
  while ( $res && ($x= mysql_fetch_object($res)) ) {
    $x->prijmeni= $x->pouze==1 ? $x->prijmeni_m : ($x->pouze==2 ? $x->prijmeni_z : $x->nazev);
    $x->jmena=    $x->pouze==1 ? $x->jmeno_m    : ($x->pouze==2 ? $x->jmeno_z : "{$x->jmeno_m} a {$x->jmeno_z}");
    $x->_pocet= ($x->pouze?" 1":" 2").($x->_deti?"+{$x->_deti}":'');
    // emaily
    $a_emaily_m= preg_split("/,\s*|;\s*/",trim($x->email_m ? $x->email_m : $x->emaily," ,;"));
    $a_emaily_z= preg_split("/,\s*|;\s*/",trim($x->email_z ? $x->email_z : $x->emaily," ,;"));
    $a_emaily= preg_split("/,\s*|;\s*/",trim($x->emaily," ,;"));
    $emaily= implode(';',array_diff(array_unique(array_merge($a_emaily,$a_emaily_m,$a_emaily_z)),array('')));
    $emaily_m= implode(';',$a_emaily_m);
    $emaily_z= implode(';',$a_emaily_z);
    $x->emaily= $x->pouze==1 ? $emaily_m  : ($x->pouze==2 ? $emaily_z : $emaily);
    $x->emaily.= $x->emaily ? ';' : '';
    // telefony
    $a_telefony_m= preg_split("/,\s*|;\s*/",trim($x->telefon_m ? $x->telefon_m : $x->telefony," ,;"));
    $a_telefony_z= preg_split("/,\s*|;\s*/",trim($x->telefon_z ? $x->telefon_z : $x->telefony," ,;"));
    $a_telefony= preg_split("/,\s*|;\s*/",trim($x->telefony," ,;"));
    $telefony= implode(';',array_diff(array_unique(array_merge($a_telefony,$a_telefony_m,$a_telefony_z)),array('')));
    $telefony_m= implode(';',$a_telefony_m);
    $telefony_z= implode(';',$a_telefony_z);
    $x->telefony= $x->pouze==1 ? $telefony_m  : ($x->pouze==2 ? $telefony_z : $telefony);
    // podle číselníku
    $x->ubytovani= $c_ubytovani[$x->ubytovani];
    // další
    $n++;
    $clmn[$n]= array();
    foreach($flds as $f) {
//       $clmn[$n][$f]= $f=='poznamka' && $x->r_note ? ($x->$f.' / '.$x->r_note) : $x->$f;
      $clmn[$n][$f]= $x->$f;
    }
//     break;
  }
//                                         debug($clmn,"sestava pro $akce,$typ,$fld,$cnd");
  return akce_table($tits,$flds,$clmn,$export);
}
# ================================================================================================== TEXTY
# ------------------------------------------------------------------------------------ akce_text_eko
function akce_text_eko($akce,$par,$title,$vypis,$export=false) { trace();
  $html= '';
  $prijmy= 0;
  $vydaje= 0;
  $prijem= array();
  // zjištění mimořádných pečovatelů
  $qm="SELECT id_spolu FROM pobyt AS p  JOIN akce  AS a ON p.id_akce=a.id_duakce
      JOIN spolu AS s USING(id_pobyt) WHERE p.id_akce='$akce' AND p.funkce=99 AND s.pfunkce=6 ";
  $rm= mysql_qry($qm);
  $n_mimoradni= mysql_num_rows($rm);
//   $mimoradni= $n_mimoradni ? "platba za stravu a ubytování $n_mimoradni mimořádných pečovatelů, kterou uhradili" : '';
  // příjmy od účastníků na pečouny
  $limit= '';
//   $limit= "AND id_pobyt IN (17957,18258,18382)";
  $qp=  "SELECT id_pobyt,funkce FROM pobyt WHERE id_akce='$akce' $limit ";
  $rp= mysql_qry($qp);
  while ( $rp && ($p= mysql_fetch_object($rp)) ) {
    $ret= akce_vzorec($p->id_pobyt);
//                                                         if ($ret->eko->slevy)
//                                                         debug($ret->eko->slevy,"sleva pro fce={$p->funkce}");
    if ( $ret->eko->vzorec ) {
      foreach ($ret->eko->vzorec as $x=>$kc) {
        $prijem[$x]->vzorec+= $kc;
        $corr= false;
        $slevy= $ret->eko->slevy;
        if ( $slevy ) {
          if ( $slevy->procenta ) {
            $prijem[$x]->platba+= round(($kc * (100 - $slevy->procenta)/100),-1);
            $corr= true;
          }
        }
        if ( !$corr ) {
          $prijem[$x]->platba+= $kc;
        }
      }
    }
  }
  // výdaje za pečouny (mimo osobních a pomocných)
  $rows_vydaje= '';
  $rows_prijmy= '';
  $qc= "SELECT GROUP_CONCAT(polozka) AS polozky, za
        FROM ezer_ys.cenik
        WHERE id_akce='$akce' AND za!=''
        GROUP BY za ORDER BY poradi ASC";
  $rc= mysql_qry($qc);
  while ( $rc && ($c= mysql_fetch_object($rc)) ) {
    if ( $prijem[$c->za]->vzorec ) {
      $cena= $platba= '';
      if ( $prijem[$c->za]->vzorec ) $cena= $prijem[$c->za]->vzorec;
      if ( $prijem[$c->za]->platba ) $platba= $prijem[$c->za]->platba;
      if ( $c->za != 'P' ) $prijmy+= $cena;
      $cena= number_format($cena, 0, '.', ' ');
      $platba= number_format($platba, 0, '.', ' ');
//       $rows_prijmy.= "<tr><th>{$c->polozky}</th><td align='right'>$cena</td><td align='right'>$platba</td></tr>";
      $rows_prijmy.= "<tr><th>{$c->polozky}</th><td align='right'>$cena</td></tr>";
    }
  }
  // náklad na stravu pečounů - kteří mají funkci a nemají zaškrtnuto "platí rodiče"
  $par= (object)array('typ'=>'vjp');
  $ret= akce_stravenky($akce,$par,'','',true);
//                                                         debug($ret->tab);
  $ham= array('sc'=>0,'oc'=>0,'vc'=>0);
  $pecounu= 0;
  $noci= -1;
  foreach ($ret->tab as $jmeno=>$dny) {
//                                                         debug($dny,"DNY");
    $pecounu++;
    foreach ( $dny as $den=>$jidla ) {
      if ( $pecounu==1 ) $noci++;
      foreach ( $jidla as $jidlo=>$porce ) {
        foreach ( $porce as $velikost=>$pocet ) {
          $ham["$jidlo$velikost"]+= $pocet;
        }
      }
    }
  }
  $qc= "SELECT GROUP_CONCAT(polozka) AS polozky, za, SUM(cena) AS _cena_
        FROM ezer_ys.cenik
        WHERE id_akce='$akce' AND za IN ('sc','oc','vc','Np')
        GROUP BY za ORDER BY poradi ASC";
  $rc= mysql_qry($qc);
  while ( $rc && ($c= mysql_fetch_object($rc)) ) {
    $cena= $c->za=='Np'
      ? $noci * $pecounu * $c->_cena_
      : $ham[$c->za] * $c->_cena_;
    $vydaje+= $cena;
    $cena= number_format($cena, 0, '.', ' ');
    $rows_vydaje.= "<tr><th>{$c->polozky}</th><td align='right'>$cena</td></tr>";
  }
  // odhad příjmů za mimořádné pečouny - přičtení k příjmům
  if ( $n_mimoradni ) {
    $cena_mimoradni= $vydaje*$n_mimoradni/$pecounu;
    $prijmy+= $cena_mimoradni;
    $cena= number_format($cena_mimoradni, 0, '.', ' ');
    $rows_prijmy.= "<tr><th>ubytování a strava $n_mimoradni mimoř.peč.</th>
      <td align='right'>$cena</td></tr>";
  }
//                                                         debug($prijem,"EKONOMIKA AKCE celkem");
  // formátování odpovědi dle ceníku akce
  $html.= "<h3>Příjmy za akci podle aktuální skladby účastníků</h3>";
//   $html.= "Pozn. pro přehled se počítá také cena s uplatněnou procentní slevou (např. VPS)<br>";
//   $html.= "(příjmy pro pečovatele se počítají z plné tzn. vyšší ceny)<br>";
//   $html.= "<br><table class='stat'><td>položky</td><th>cena bez slev</th><th>cena po slevě</th></tr>";
  $html.= "<br><table class='stat'><td>položky</td><th>cena</th></tr>";
  $html.= "$rows_prijmy</table>";
  $html.= "<h3>Výdaje za stravu a ubytování pro $pecounu pečovatelů ($noci nocí)</h3>";
  $html.= "V tomto počtu nejsou zahrnuti pomocní a osobní pečovatelé, jejichž náklady hradí rodiče<br>";
  $html.= "(to je třeba v evidenční kartě pečovatele zapsat zaškrtnutím políčka pod poznámkou)<br>";
  // stravenky nejsou vytištěny pro $note, kteří nemají jasnou funkci -- pfunkce=0
  $html.= $ret->note ? "{$ret->note}<br>" : '';
  $html.= "<br><table class='stat'><td>položky</td><th>cena</td></tr>";
  $html.= "$rows_vydaje</table>";
  $html.= "<h3>Shrnutí pro pečovatele</h3>";
  $obrat= $prijmy - $vydaje;
  $prijmy= number_format($prijmy, 0, '.', ' ')."&nbsp;Kč";
  $vydaje= number_format($vydaje, 0, '.', ' ')."&nbsp;Kč";
  $obrat= number_format($obrat, 0, '.', ' ')."&nbsp;Kč";
  $html.= "Účastníci přispějí na pečovatele částkou $prijmy, přímé náklady na pobyt a stravu
    činí $vydaje, <br>celkem <b>$obrat</b> je tedy možné použít na programové výdaje
    pečovatelů na akci a během roku.";
  // předání výsledku
  $result->html= $html;
  return $result;
}
# -------------------------------------------------------------------------------- akce_text_prehled
function akce_text_prehled($akce,$par,$title,$vypis,$export=false) { trace();
  function akce_text_prehled_x($akce,$cond,$uvest_jmena=false) {
    $html= '';
    // data akce
    $veky= $kluci= $holky= array();
    $nveky= $nkluci= $nholky= 0;
    $jmena= $deljmena= '';
    $bez= $del= '';
    // histogram věku dětí pozor na vynechání "dědečků a tet" tzn. spolu.pfunkce=95
    $qo=  "SELECT prijmeni,jmeno,narozeni,role,a.datum_od,o.sex
           FROM akce AS a
           JOIN pobyt AS p ON a.id_duakce=p.id_akce
           JOIN spolu AS s USING(id_pobyt)
           JOIN osoba AS o ON s.id_osoba=o.id_osoba
           LEFT JOIN tvori AS t ON t.id_osoba=o.id_osoba
           WHERE a.id_duakce='$akce' AND $cond ORDER BY prijmeni ";
    $ro= mysql_qry($qo);
    while ( $ro && ($o= mysql_fetch_object($ro)) ) {
      $vek= narozeni2roky_sql($o->narozeni,$o->datum_od);
      $sex= $o->sex;
      $veky[$vek]++;
      $nveky++;
      if ( $sex==1 ) { $kluci[$vek]++; $nkluci++; }
      elseif ( $sex==2 ) { $holky[$vek]++; $nholky++; }
      else { $bez.= "$del{$o->prijmeni} {$o->jmeno}"; $del= ", "; }
      if ( $uvest_jmena ) {
        $jmena.= "$deljmena{$o->prijmeni} {$o->jmeno}"; $deljmena= ", ";
      }
    }
    ksort($veky);
    // formátování výsledku
    $html.= "<table class='stat'>";
    $r1= $r2= $r3= $r4= '';
    foreach($veky as $v=>$n) {
      $r1.= "<th align='right' width='20'>$v</th>";
      $style= $n==$kluci[$v]+$holky[$v] ? '' : " style='background-color:yellow'";
      $r2.= "<td align='right'$style>$n</td>";
      $r3.= "<td align='right'>{$kluci[$v]}</td>";
      $r4.= "<td align='right'>{$holky[$v]}</td>";
    }
    $r1.= "<th align='right'>celkem</th>";
    $style= $nveky==$nkluci+$nholky ? '' : " style='background-color:yellow'";
    $r2.= "<td align='right'$style>$nveky</td>";
    $r3.= "<td align='right'>$nkluci</td>";
    $r4.= "<td align='right'>$nholky</td>";
    $html.= "<tr><th>věk</th>$r1</tr><tr><th>počet</th>$r2</tr><tr>"
          . "<th>kluci</th>$r3</tr><tr><th>holky</th>$r4</tr></table>";
    // jména
    if ( $jmena ) $html.= "<b>($jmena)</b>";
    // upozornění
    if ( $bez ) $html.= ($jmena?"<br>":'')."<i>(ani holka ani kluk: $bez)</i>";
    // předání výsledku
    return $html;
  }
  $html= '';
  // pfunkce: 0 4 5 8 92 95
  $html.= "<h3>Celkový počet dětí na akci podle stáří (v době začátku akce)</h3>";
  $html.= akce_text_prehled_x($akce,"t.role='d' AND s.pfunkce!=95 ");
  $html.= "<h3>Děti ve skupinkách (mimo G a osobně opečovávaných)</h3>";
  $html.= akce_text_prehled_x($akce,"t.role='d' AND s.pfunkce IN (0)");
  $html.= "<h3>Děti v péči osobního pečovatele</h3>";
  $html.= akce_text_prehled_x($akce,"t.role='d' AND s.pfunkce=92",true);
  $html.= "<h3>Děti ve skupině G</h3>";
  $html.= akce_text_prehled_x($akce,"t.role='d' AND s.pfunkce=8",true);
  $html.= "<h3>Pomocní pečovatelé</h3>";
  $html.= akce_text_prehled_x($akce,"t.role='d' AND s.pfunkce=4",true);
  $html.= "<h3>Osobní pečovatelé (zařazení mezi Pečovatele)</h3>";
  $html.= akce_text_prehled_x($akce,"t.role='d' AND s.pfunkce IN (5)",true);
  $html.= "<h3>Osobní pečovatelé (nezařazení mezi Pečovatele)</h3>";
  $html.= akce_text_prehled_x($akce,"t.role='d' AND s.pfunkce IN (95)",true);
  $html.= "<br><hr><h3>Řádní pečovatelé</h3>";
  $html.= akce_text_prehled_x($akce,"p.funkce=99 AND s.pfunkce IN (1,2,3) ");
  $html.= "<h3>Mimořádní pečovatelé</h3>";
  $html.= akce_text_prehled_x($akce,"p.funkce=99 AND s.pfunkce=6 ",true);
  $html.= "<h3>Team pečovatelů (s touto funkcí)</h3>";
  $html.= akce_text_prehled_x($akce,"p.funkce=99 AND s.pfunkce IN (7) ",true);
  $html.= "<h3>Team pečovatelů (bez přiřazené funkce)</h3>";
  $html.= akce_text_prehled_x($akce,"p.funkce=99 AND s.pfunkce IN (0) ",true);
  $result->html= "$html<br><br>";
  return $result;
}
# --------------------------------------------------------------------------------- akce_text_vyroci
function akce_text_vyroci($akce,$par,$title,$vypis,$export=false) { trace();
  $html= '';
  // data akce
  $vyroci= array();
  // narozeniny
  $qry=  "SELECT
          prijmeni,jmeno,narozeni,role
          FROM akce AS a
          JOIN pobyt AS p ON a.id_duakce=p.id_akce
          JOIN spolu AS s USING(id_pobyt)
          JOIN osoba AS o ON s.id_osoba=o.id_osoba
          LEFT JOIN tvori AS t ON t.id_osoba=o.id_osoba
          WHERE a.id_duakce='$akce' AND
            CONCAT(YEAR(datum_od),SUBSTR(narozeni,5,6)) BETWEEN datum_od AND datum_do
          ORDER BY SUBSTR(narozeni,5,6) ";
  $res= mysql_qry($qry);
  while ( $res && ($x= mysql_fetch_object($res)) ) {
    $vyroci[$x->role=='d'?'d':'a'][]= "{$x->prijmeni} {$x->jmeno}|".sql_date1($x->narozeni);
  }
  // výročí
  $qry=  "SELECT
            r.nazev,datsvatba,
            GROUP_CONCAT(DISTINCT IF(t.role='a',o.jmeno,'')    SEPARATOR '') as jmeno_m,
            GROUP_CONCAT(DISTINCT IF(t.role='b',o.jmeno,'')    SEPARATOR '') as jmeno_z
          FROM akce AS a
          JOIN pobyt AS p ON a.id_duakce=p.id_akce
          JOIN spolu AS s USING(id_pobyt)
          JOIN osoba AS o ON s.id_osoba=o.id_osoba
          LEFT JOIN tvori AS t ON t.id_osoba=o.id_osoba
          LEFT JOIN rodina AS r USING(id_rodina)
          WHERE a.id_duakce='$akce' AND pouze=0 AND
            CONCAT(YEAR(datum_od),SUBSTR(datsvatba,5,6)) BETWEEN datum_od AND datum_do
          GROUP BY id_pobyt
          ORDER BY SUBSTR(datsvatba,5,6) ";
  $res= mysql_qry($qry);
  while ( $res && ($x= mysql_fetch_object($res)) ) {
    $vyroci['s'][]= "{$x->nazev} {$x->jmeno_m} a {$x->jmeno_z}|".sql_date1($x->datsvatba);
  }
  // nepřivítané děti mladší 2 let
  $qry=  "SELECT prijmeni,jmeno,narozeni,role,
            ROUND(DATEDIFF(a.datum_od,o.narozeni)/365.2425,1) AS _vek
          FROM akce AS a
          JOIN pobyt AS p ON a.id_duakce=p.id_akce
          JOIN spolu AS s USING(id_pobyt)
          JOIN osoba AS o ON s.id_osoba=o.id_osoba
          LEFT JOIN tvori AS t ON t.id_osoba=o.id_osoba
          WHERE a.id_duakce='$akce' AND role='d' AND o.uvitano=0
          GROUP BY o.id_osoba HAVING _vek<2";
  $res= mysql_qry($qry);
  while ( $res && ($x= mysql_fetch_object($res)) ) {
    $vyroci['v'][]= "{$x->prijmeni} {$x->jmeno}|".sql_date1($x->narozeni);
  }
  // redakce
  if ( count($vyroci['s']) ) {
    $html.= "<h3>Výročí svatby během akce</h3><table>";
    foreach($vyroci['s'] as $txt) {
      list($kdo,$kdy)= explode('|',$txt);
      $html.= "<tr><td>$kdy</td><td>$kdo</td></tr>";
    }
    $html.= "</table>";
  }
  else $html.= "<h3>Na akci nemá žádný pár výročí svatby</h3>";
  if ( count($vyroci['a']) ) {
    $html.= "<h3>Narozeniny dopělých na akci</h3><table>";
    foreach($vyroci['a'] as $txt) {
      list($kdo,$kdy)= explode('|',$txt);
      $html.= "<tr><td>$kdy</td><td>$kdo</td></tr>";
    }
    $html.= "</table>";
  }
  else $html.= "<h3>Na akci nemá žádný dospělý účastník narozeniny</h3>";
  if ( count($vyroci['d']) ) {
    $html.= "<h3>Narozeniny dětí na akci</h3><table>";
    foreach($vyroci['d'] as $txt) {
      list($kdo,$kdy)= explode('|',$txt);
      $html.= "<tr><td>$kdy</td><td>$kdo</td></tr>";
    }
    $html.= "</table>";
  }
  else $html.= "<h3>Na akci nemá žádné dítě narozeniny</h3>";
  if ( count($vyroci['v']) ) {
    $html.= "<h3>Nepřivítané děti mladší 2 let na akci</h3><table>";
    foreach($vyroci['v'] as $txt) {
      list($kdo,$kdy)= explode('|',$txt);
      $html.= "<tr><td>$kdy</td><td>$kdo</td></tr>";
    }
    $html.= "</table>";
  }
  else $html.= "<h3>Na akci nebudeme vítat žádné dítě</h3>";
  $result->html= $html;
  return $result;
}
# ================================================================================================== VYÚČTOVÁNÍ ETC.
# -------------------------------------------------------------------------------- akce_sestava_noci
# generování sestavy přehledu člověkonocí pro účastníky $akce - páry
#   $cnd = podmínka
# počítané položky
#   manzele = rodina.nazev muz a zena
# generované vzorce
#   člověkolůžka, člověkopřistýlky
function akce_sestava_noci($akce,$par,$title,$vypis,$export=false) { trace();
  // definice sloupců
  $tit= "Manželé:25,pokoj:8:r,dnů:5:r,nocí:5:r,lůžek:5:r:s,lůžko nocí:5:r:s,přis týlek:5:r:s,přis týlko nocí:5:r:s";
  $fld= "manzele,pokoj,pocetdnu,=noci,luzka,=luzkonoci,pristylky,=pristylkonoci";
  $ord= $par->ord ? $par->ord : "IF(funkce<=2,1,funkce),IF(pouze=0,r.nazev,o.prijmeni)";
  $cnd= $par->cnd;
  $html= '';
  $href= '';
  $n= 0;
  // dekódování parametrů
  $tits= explode(',',$tit);
  $flds= explode(',',$fld);
  $cond= 1;
  // získání dat - podle $kdo
  $clmn= array();       // pro hodnoty
  $expr= array();       // pro výrazy
  $suma= array();       // pro sumy sloupců id:::s
  $fmts= array();       // pro formáty sloupců id::f:
  for ($i= 0; $i<count($tits); $i++) {
    $idw= $tits[$i];
    $fld= $flds[$i];
    list($id,$w,$f,$sum)= explode(':',$idw);
    if ( $sum=='s' ) $suma[$fld]= 0;
    if ( isset($f) ) $fmts[$fld]= $f;
  }
  // data akce
  $qry=  "SELECT
            pokoj,luzka,pristylky,pocetdnu,
            GROUP_CONCAT(DISTINCT IF(t.role='a',o.prijmeni,'') SEPARATOR '') as prijmeni_m,
            GROUP_CONCAT(DISTINCT IF(t.role='a',o.jmeno,'')    SEPARATOR '') as jmeno_m,
            GROUP_CONCAT(DISTINCT IF(t.role='b',o.prijmeni,'') SEPARATOR '') as prijmeni_z,
            GROUP_CONCAT(DISTINCT IF(t.role='b',o.jmeno,'')    SEPARATOR '') as jmeno_z,
            p.pouze,r.nazev as nazev
          FROM pobyt AS p
          JOIN spolu AS s USING(id_pobyt)
          JOIN osoba AS o ON s.id_osoba=o.id_osoba
          LEFT JOIN tvori AS t ON t.id_osoba=o.id_osoba
          LEFT JOIN rodina AS r USING(id_rodina)
          WHERE p.id_akce='$akce' AND funkce NOT IN (9,10) AND $cond
          GROUP BY id_pobyt
          ORDER BY $ord";
//   $qry.=  " LIMIT 1";
  $res= mysql_qry($qry);
  while ( $res && ($x= mysql_fetch_object($res)) ) {
//                                         debug($x,"hodnoty");
    $n++;
    $clmn[$n]= array();
    foreach($flds as $f) {
      $exp= ''; $val= 0;
      if ( substr($f,0,1)=='=' ) {
        switch ($f) {
        case '=noci':         $val= $x->pocetdnu;
                              $exp= "=[pocetdnu,0]"; break;
//         case '=noci':         $val= max(0,$x->pocetdnu-1);
//                               $exp= "=max(0,[pocetdnu,0]-1)"; break;
        case '=luzkonoci':    $val= ($x->pocetdnu)*$x->luzka;
                              $exp= "=[=noci,0]*[luzka,0]"; break;
        case '=pristylkonoci':$val= ($x->pocetdnu)*$x->pristylky;
                              $exp= "=[=noci,0]*[pristylky,0]"; break;
        default:              $val= '???'; break;
        }
        $clmn[$n][$f]= $val;
        if ( $exp ) $expr[$n][$f]= $exp;
      }
      else {
        switch ($f) {
        case 'manzele':
          $val= $x->pouze==1 ? "{$x->prijmeni_m} {$x->jmeno_m}"
             : ($x->pouze==2 ? "{$x->prijmeni_z} {$x->jmeno_z}"
             : "{$x->nazev} {$x->jmeno_m} a {$x->jmeno_z}");
          break;
        case 'jmena':
          $val= $x->pouze==1
              ? $x->jmeno_m : ($x->pouze==2 ? $x->jmeno_z : "{$x->jmeno_m} a {$x->jmeno_z}");
          break;
        case 'prijmeni':
          $val= $x->pouze==1 ? $x->prijmeni_m : ($x->pouze==2 ? $x->prijmeni_z : $x->nazev);
          break;
        default:
          $val= $f ? $x->$f : '';
          break;
        }
        if ( $f ) $clmn[$n][$f]= $val; else $clmn[$n][]= $val;
      }
      // případný výpočet sumy
      if ( isset($suma[$f]) ) {
         $suma[$f]+= $val;
      }
    }
  }

//                                         debug($clmn,"sestava pro $akce,$typ,$fld,$cnd");
//                                         debug($expr,"vzorce pro $akce,$typ,$fld,$cnd");
//                                         debug($suma,"sumy pro $akce B");
  // zobrazení tabulkou
  $tab= '';
  $thd= '';
  if ( $export ) {
    $result->tits= $tits;
    $result->flds= $flds;
    $result->clmn= $clmn;
    $result->expr= $expr;
  }
  else {
    // titulky
    foreach ($tits as $idw) {
      list($id)= explode(':',$idw);
      $ths.= "<th>$id</th>";
    }
    // data
    foreach ($clmn as $i=>$c) {
      $tab.= "<tr>";
      foreach ($c as $id=>$val) {
        $style= akce_sestava_td_style($fmts[$id]);
        $tab.= "<td$style>$val</td>";
      }
      $tab.= "</tr>";
    }
    // sumy
    $sum= '';
    if ( count($suma)>0 ) {
      $sum.= "<tr>";
      foreach ($flds as $f) {
        $val= isset($suma[$f]) ? $suma[$f] : '';
        $sum.= "<th style='text-align:right'>$val</th>";
      }
      $sum.= "</tr>";
    }
    $result->html= "<div class='stat'><table class='stat'><tr>$ths</tr>$sum$tab</table></div>";
    $result->html.= "</br>";
    $result->href= $href;
  }
  return $result;
}
# ----------------------------------------------------------------------------------- akce_stravenky
# generování stravenek účastníky $akce - rodinu ($par->typ=='vj') resp. pečouny ($par->typ=='vjp')
#   $cnd = podmínka
# počítané položky
#   manzele = rodina.nazev muz, zena a děti
# generované vzorce
#   platit = součet předepsaných plateb
# výstupy
#   note = pro pečouny seznam jmen, pro které nejsou stravenky, protože nemají funkci
#          (tzn. asi nejsou na celý pobyt)
function akce_stravenky($akce,$par,$title,$vypis,$export=false) { trace();
//                                                         debug($par,"akce_stravenky($akce,,$title,$vypis,$export)");
  $ord= $par->ord ? $par->ord : "IF(funkce<=2,1,funkce),IF(pouze=0,r.nazev,o.prijmeni)";
  $result= (object)array();
  $cnd= $par->cnd;
  $note= $delnote= $html= $href= '';
  $n= 0;
  // zjištění sloupců (0=ne)
  $tit= $par->typ=='vjp' ? "Pečovatel:25" : "Manželé:25";
  $fld= "manzele";
  $dny= array('ne','po','út','st','čt','pá','so');
  $dny= array('n','p','ú','s','č','p','s');
  $qrya= "SELECT strava_oddo,datum_od,datum_do,DATEDIFF(datum_do,datum_od) AS _dnu
            ,DAYOFWEEK(datum_od)-1 AS _den1
          FROM akce WHERE id_duakce=$akce ";
  $resa= mysql_qry($qrya);
  if ( $resa && ($a= mysql_fetch_object($resa)) ) {
//                                                         debug($a,"akce {$a->_dnu}");
    $oo= $a->strava_oddo ? $a->strava_oddo : 'vo';
    $nd= $a->_dnu;
    $den1= sql2stamp($a->datum_od);             // začátek akce ve formátu mktime
    for ($i= 0; $i<=$nd; $i++) {
      $deni= mktime(0, 0, 0, date("n", $den1), date("j", $den1) + $i, date("Y", $den1));
      $den= $dny[($a->_den1+$i)%7].date('d',$deni).' ';
      if ( $i>0 || $oo[0]=='s' ) {
        $tit.= ",{$den}sc:4:r:s";
        $tit.= ",{$den}sp:4:r:s";
        $fld.= ",{$den}sc,{$den}sp";
      }
      if ( $i>0 && $i<$nd
        || $i==0   && ($oo[0]=='s' || $oo[0]=='o')
        || $i==$nd && ($oo[1]=='o' || $oo[1]=='v') ) {
        $tit.= ",{$den}oc:4:r:s";
        $tit.= ",{$den}op:4:r:s";
        $fld.= ",{$den}oc,{$den}op";
      }
      if ( $i<$nd || $oo[1]=='v' ) {
        $tit.= ",{$den}vc:4:r:s";
        $tit.= ",{$den}vp:4:r:s";
        $fld.= ",{$den}vc,{$den}vp";
      }
    }
//                                                         display($tit);
  }
  // dekódování parametrů
  $tits= explode(',',$tit);
  $flds= explode(',',$fld);
  $cond= $cnd;
  // získání dat - podle $kdo
  $clmn= array();       // pro hodnoty
  $expr= array();       // pro výrazy
  $suma= array();       // pro sumy sloupců id:::s
  $fmts= array();       // pro formáty sloupců id::f:
  for ($i= 0; $i<count($tits); $i++) {
    $idw= $tits[$i];
    $fld= $flds[$i];
    list($id,$w,$f,$sum)= explode(':',$idw);
    if ( $sum=='s' ) $suma[$fld]= 0;
    if ( isset($f) ) $fmts[$fld]= $f;
  }
  // data akce
  $akce_data= (object)array();
  $dny= array('ne','po','út','st','čt','pá','so');
  if ( $par->typ=='vjp' )
    $qry="SELECT o.prijmeni,o.jmeno,s.pfunkce,YEAR(datum_od) AS _rok,
            a.nazev AS akce_nazev, YEAR(a.datum_od) AS akce_rok, a.misto AS akce_misto
          FROM pobyt AS p
          JOIN akce  AS a ON p.id_akce=a.id_duakce
          JOIN spolu AS s USING(id_pobyt)
          JOIN osoba AS o ON s.id_osoba=o.id_osoba
          WHERE p.id_akce='$akce' AND p.funkce=99 AND s_rodici=0
          ORDER BY o.prijmeni,o.jmeno";
  else
    $qry="SELECT r.nazev as nazev,strava_cel,strava_pol,cstrava_cel,cstrava_pol,p.pouze,
            GROUP_CONCAT(DISTINCT IF(t.role='a',o.prijmeni,'') SEPARATOR '') as prijmeni_m,
            GROUP_CONCAT(DISTINCT IF(t.role='a',o.jmeno,'')    SEPARATOR '') as jmeno_m,
            GROUP_CONCAT(DISTINCT IF(t.role='b',o.prijmeni,'') SEPARATOR '') as prijmeni_z,
            GROUP_CONCAT(DISTINCT IF(t.role='b',o.jmeno,'')    SEPARATOR '') as jmeno_z,
            a.nazev AS akce_nazev, YEAR(a.datum_od) AS akce_rok, a.misto AS akce_misto
          FROM pobyt AS p
          JOIN akce  AS a ON p.id_akce=a.id_duakce
          JOIN spolu AS s USING(id_pobyt)
          JOIN osoba AS o ON s.id_osoba=o.id_osoba
          LEFT JOIN tvori AS t ON t.id_osoba=o.id_osoba
          LEFT JOIN rodina AS r USING(id_rodina)
          WHERE p.id_akce='$akce' AND $cond
          GROUP BY id_pobyt
          ORDER BY $ord";
//   $qry.=  " LIMIT 1";
  $res= mysql_qry($qry);
  // stravenky - počty po dnech
  $str= array();  // $strav[kdo][den][jídlo][typ]=počet   kdo=jména,den=datum,jídlo=s|o|v, typ=c|p
  // s uvážením $oo='sv' - první jídlo prvního dne a poslední jídlo posledního dne
  $jidlo= array('s','o','v');
  $xjidlo= array('s'=>0,'o'=>1,'v'=>2);
  $jidlo_1= $xjidlo[$oo[0]];
  $jidlo_n= $xjidlo[$oo[1]];
  while ( $res && ($x= mysql_fetch_object($res)) ) {
    if ( $par->typ=='vjp' && $x->pfunkce==0 && $x->_rok>2012 ) {        // !!!!!!!!!!!!!! od roku 2013
      $note.= "$delnote{$x->prijmeni} {$x->jmeno}";
      $delnote= ", ";
      continue;
    }
    $n++;
    $akce_data->nazev= $x->akce_nazev;
    $akce_data->rok=   $x->akce_rok;
    $akce_data->misto= $x->akce_misto;
    $str_kdo= array();
    $clmn[$n]= array();
    $clmn[$n]['manzele']=
         $par->typ=='vjp' ? "{$x->prijmeni} {$x->jmeno}"
       : ($x->pouze==1 ? "{$x->prijmeni_m} {$x->jmeno_m}"
       : ($x->pouze==2 ? "{$x->prijmeni_z} {$x->jmeno_z}"
       : "{$x->nazev} {$x->jmeno_m} a {$x->jmeno_z}"));
    // stravy
    $sc= $par->typ=='vjp' ? 1 : $x->strava_cel;
    $sp= $x->strava_pol;
    $csc= $x->cstrava_cel;
    $csp= $x->cstrava_pol;
    $k= 0;
    for ($i= 0; $i<=$nd; $i++) {
      // projdeme dny akce
      $str_den= array();
      $j0= $i==0 ? $jidlo_1 : 0;
      $jn= $i==$nd ? $jidlo_n : 2;
      if ( 0<=$j0 && $j0<=$jn && $jn<=2 ) {
        for ($j= $j0; $j<=$jn; $j++) {
          $str_cel= $csc ? $csc[3*$i+$j] : $sc;
          $str_pol= $csp ? $csp[3*$i+$j] : $sp;
          if ( $str_cel ) $str_den[$jidlo[$j]]['c']= $str_cel;
          if ( $str_pol ) $str_den[$jidlo[$j]]['p']= $str_pol;
        }
      }
      else
        fce_error("Tisk stravenek selhal: chybné meze stravování v nastavení akce: $j0-$jn");
      if ( $i>0 || $oo[0]=='s' ) {
        // snídaně od druhého dne pobytu nebo začíná-li pobyt snídaní
        $k++; $suma[$flds[$k]]+= $clmn[$n][$flds[$k]]= $csc ? $csc[3*$i+0] : $sc;
        $k++; $suma[$flds[$k]]+= $clmn[$n][$flds[$k]]= $csp ? $csp[3*$i+0] : $sp;
      }
      if ( $i>0 && $i<$nd
        || $i==0   && ($oo[0]=='s' || $oo[0]=='o')
        || $i==$nd && ($oo[1]=='o' || $oo[1]=='v') ) {
        // obědy od druhého do předposledního dne akce nebo prvního či posledního dne
        $k++; $suma[$flds[$k]]+= $clmn[$n][$flds[$k]]= $csc ? $csc[3*$i+1] : $sc;
        $k++; $suma[$flds[$k]]+= $clmn[$n][$flds[$k]]= $csp ? $csp[3*$i+1] : $sp;
      }
      if ( $i<$nd || $oo[1]=='v' ) {
        // večeře do předposledního dne akce nebo končí-li pobyt večeří
        $k++; $suma[$flds[$k]]+= $clmn[$n][$flds[$k]]= $csc ? $csc[3*$i+2] : $sc;
        $k++; $suma[$flds[$k]]+= $clmn[$n][$flds[$k]]= $csp ? $csp[3*$i+2] : $sp;
      }
      if ( count($str_den) ) {
        $mkden= mktime(0, 0, 0, date("n", $den1), date("j", $den1)+$i, date("Y", $den1));
        $den= "<b>{$dny[date('w',$mkden)]}</b> ".date("j.n",$mkden);
        $str_kdo[$den]= $str_den;
      }
    }
    $kdo= $par->typ=='vjp' ? "{$x->prijmeni}|{$x->jmeno}"
        : ($x->pouze==1 ? "{$x->prijmeni_m}|{$x->jmeno_m}"
        : ($x->pouze==2 ? "{$x->prijmeni_z}|{$x->jmeno_z}"
        : "{$x->nazev}|{$x->jmeno_m} a {$x->jmeno_z}"));
    $str[$kdo]= $str_kdo;
  }
//                                                         debug($str,"stravenky");
//                                                         debug($suma,"sumy");
  // titulky
  foreach ($tits as $idw) {
    list($id)= explode(':',$idw);
    $ths.= "<th>$id</th>";
  }
  // data
  foreach ($clmn as $i=>$c) {
    $tab.= "<tr>";
    foreach ($c as $id=>$val) {
      $style= akce_sestava_td_style($fmts[$id]);
      $tab.= "<td$style>$val</td>";
    }
    $tab.= "</tr>";
  }
  // sumy
  $sum= '';
  if ( count($suma)>0 ) {
    $sum.= "<tr>";
    foreach ($flds as $f) {
      $val= isset($suma[$f]) ? $suma[$f] : '';
      $sum.= "<th style='text-align:right'>$val</th>";
    }
    $sum.= "</tr>";
  }
  $result->html= "<div class='stat'><table class='stat'><tr>$ths</tr>$sum$tab</table></div>";
  $result->html.= "</br>";
  $result->href= $href;
  $result->tab= $str;
  $result->akce= $akce_data;
  $result->note= $note ? "(bez $note, kteří nemají vyjasněnou funkci)" : '';
  return $result;
}
# --------------------------------------------------------------------------------- akce_strava_pary
# generování sestavy přehledu strav pro účastníky $akce - páry
#   $cnd = podmínka
#   $id_pobyt -- je-li udáno, počítá se jen pro tento jeden pobyt (jedněch účastníků)
# počítané položky
#   manzele = rodina.nazev muz a zena
# generované vzorce
#   platit = součet předepsaných plateb
function akce_strava_pary($akce,$par,$title,$vypis,$export=false,$id_pobyt=0) { trace();
  $ord= $par->ord ? $par->ord : "IF(funkce<=2,1,funkce),IF(pouze=0,r.nazev,o.prijmeni)";
  $result= (object)array();
  $cnd= 1;
  $html= '';
  $href= '';
  $n= 0;
  // zjištění sloupců (0=ne)
  $tit= "Manželé a pečouni:25";  // bude opraveno podle skutečnosti před exportem
  $fld= "manzele";
  $dny= array('ne','po','út','st','čt','pá','so');
  $dny= array('n','p','ú','s','č','p','s');
  $qrya= "SELECT strava_oddo,datum_od,datum_do,DATEDIFF(datum_do,datum_od) AS _dnu
            ,DAYOFWEEK(datum_od)-1 AS _den1
          FROM akce WHERE id_duakce=$akce ";
  $resa= mysql_qry($qrya);
  if ( $resa && ($a= mysql_fetch_object($resa)) ) {
//                                                         debug($a,"akce {$a->_dnu}");
    $oo= $a->strava_oddo ? $a->strava_oddo : 'vo';
    $nd= $a->_dnu;
    for ($i= 0; $i<=$nd; $i++) {
      $den= $dny[($a->_den1+$i)%7].date('d',sql2stamp($a->datum_od)+$i*60*60*24).' ';
      if ( $i>0 || $oo[0]=='s' ) {
        $tit.= ",{$den}sc:4:r:s";
        $tit.= ",{$den}sp:4:r:s";
        $fld.= ",{$den}sc,{$den}sp";
      }
      if ( $i>0 && $i<$nd
        || $i==0   && ($oo[0]=='s' || $oo[0]=='o')
        || $i==$nd && ($oo[1]=='o' || $oo[1]=='v') ) {
        $tit.= ",{$den}oc:4:r:s";
        $tit.= ",{$den}op:4:r:s";
        $fld.= ",{$den}oc,{$den}op";
      }
      if ( $i<$nd || $oo[1]=='v' ) {
        $tit.= ",{$den}vc:4:r:s";
        $tit.= ",{$den}vp:4:r:s";
        $fld.= ",{$den}vc,{$den}vp";
      }
    }
//                                                         display($tit);
  }
  // dekódování parametrů
  $tits= explode(',',$tit);
  $flds= explode(',',$fld);
  $cond= $cnd;
  // získání dat - podle $kdo
  $clmn= array();       // pro hodnoty
  $expr= array();       // pro výrazy
  $suma= array();       // pro sumy sloupců id:::s
  $fmts= array();       // pro formáty sloupců id::f:
  for ($i= 0; $i<count($tits); $i++) {
    $idw= $tits[$i];
    $fld= $flds[$i];
    list($id,$w,$f,$sum)= explode(':',$idw);
    if ( $sum=='s' ) $suma[$fld]= 0;
    if ( isset($f) ) $fmts[$fld]= $f;
  }
  // pokud není id_pobyt tak vyloučíme náhradníky
  $cond.= $id_pobyt ? " AND id_pobyt=$id_pobyt" : " AND funkce NOT IN (9)";
  $jsou_pecouni= false;
  // data akce
  $qry=  "SELECT COUNT(*) AS _pocet,funkce,pfunkce,
            r.nazev as nazev,strava_cel,strava_pol,cstrava_cel,cstrava_pol,p.pouze,
            GROUP_CONCAT(DISTINCT IF(t.role='a',o.prijmeni,'') SEPARATOR '') as prijmeni_m,
            GROUP_CONCAT(DISTINCT IF(t.role='a',o.jmeno,'')    SEPARATOR '') as jmeno_m,
            GROUP_CONCAT(DISTINCT IF(t.role='b',o.prijmeni,'') SEPARATOR '') as prijmeni_z,
            GROUP_CONCAT(DISTINCT IF(t.role='b',o.jmeno,'')    SEPARATOR '') as jmeno_z
          FROM pobyt AS p
          JOIN spolu AS s USING(id_pobyt)
          JOIN osoba AS o ON s.id_osoba=o.id_osoba
          LEFT JOIN tvori AS t ON t.id_osoba=o.id_osoba
          LEFT JOIN rodina AS r USING(id_rodina)
          WHERE p.id_akce='$akce' AND IF(funkce=99,s_rodici=0 AND pfunkce,1) AND $cond
          GROUP BY id_pobyt
          ORDER BY $ord";
//   $qry.=  " LIMIT 5";
  $res= mysql_qry($qry);
  while ( $res && ($x= mysql_fetch_object($res)) ) {
//                                                         debug($x,"hodnoty");
    $n++;
    $clmn[$n]= array();
    if ( $x->funkce==99 && $x->pfunkce ) {
      // stravy pro pečouny - mají jednotně celou stravu - (s_rodici=0,pfunkce!=0 viz SQL)
      $jsou_pecouni= true;
      $clmn[$n]['manzele']= 'PEČOUNI';
      $sc= $x->_pocet;
      $k= 0;
      for ($i= 0; $i<=$nd; $i++) {
        if ( $i>0 || $oo[0]=='s' ) {
          $k++; $suma[$flds[$k]]+= $clmn[$n][$flds[$k]]= $sc;
          $k++; $suma[$flds[$k]]+= $clmn[$n][$flds[$k]]= $sp;
        }
        if ( $i>0 && $i<$nd
          || $i==0   && ($oo[0]=='s' || $oo[0]=='o')
          || $i==$nd && ($oo[1]=='o' || $oo[1]=='v') ) {
          $k++; $suma[$flds[$k]]+= $clmn[$n][$flds[$k]]= $sc;
          $k++; $suma[$flds[$k]]+= $clmn[$n][$flds[$k]]= $sp;
        }
        if ( $i<$nd || $oo[1]=='v' ) {
          $k++; $suma[$flds[$k]]+= $clmn[$n][$flds[$k]]= $sc;
          $k++; $suma[$flds[$k]]+= $clmn[$n][$flds[$k]]= $sp;
        }
      }
    }
    elseif ( $x->funkce!=99 ) {
      // stravy pro manžele
      $clmn[$n]['manzele']=
            $x->pouze==1 ? "{$x->prijmeni_m} {$x->jmeno_m}"
         : ($x->pouze==2 ? "{$x->prijmeni_z} {$x->jmeno_z}"
         : "{$x->nazev} {$x->jmeno_m} a {$x->jmeno_z}");
      $sc= $x->strava_cel;
      $sp= $x->strava_pol;
      $csc= $x->cstrava_cel;
      $csp= $x->cstrava_pol;
      $k= 0;
      for ($i= 0; $i<=$nd; $i++) {
        if ( $i>0 || $oo[0]=='s' ) {
          $k++; $suma[$flds[$k]]+= $clmn[$n][$flds[$k]]= $csc ? $csc[3*$i+0] : $sc;
          $k++; $suma[$flds[$k]]+= $clmn[$n][$flds[$k]]= $csp ? $csp[3*$i+0] : $sp;
        }
        if ( $i>0 && $i<$nd
          || $i==0   && ($oo[0]=='s' || $oo[0]=='o')
          || $i==$nd && ($oo[1]=='o' || $oo[1]=='v') ) {
          $k++; $suma[$flds[$k]]+= $clmn[$n][$flds[$k]]= $csc ? $csc[3*$i+1] : $sc;
          $k++; $suma[$flds[$k]]+= $clmn[$n][$flds[$k]]= $csp ? $csp[3*$i+1] : $sp;
        }
        if ( $i<$nd || $oo[1]=='v' ) {
          $k++; $suma[$flds[$k]]+= $clmn[$n][$flds[$k]]= $csc ? $csc[3*$i+2] : $sc;
          $k++; $suma[$flds[$k]]+= $clmn[$n][$flds[$k]]= $csp ? $csp[3*$i+2] : $sp;
        }
      }
    }
  }
//                                                         debug($suma,"sumy");
  $tits[0]= $jsou_pecouni ? "Manželé a pečouni:25" : "Manželé:25";
  if ( $export ) {
    $result->tits= $tits;
    $result->flds= $flds;
    $result->clmn= $clmn;
    $result->expr= $expr;
    $result->suma= $suma;
  }
  else {
    // titulky
    foreach ($tits as $idw) {
      list($id)= explode(':',$idw);
      $ths.= "<th>$id</th>";
    }
    // data
    foreach ($clmn as $i=>$c) {
      $tab.= "<tr>";
      foreach ($c as $id=>$val) {
        $style= akce_sestava_td_style($fmts[$id]);
        $tab.= "<td$style>$val</td>";
      }
      $tab.= "</tr>";
    }
    // sumy
    $sum= '';
    if ( count($suma)>0 ) {
      $sum.= "<tr>";
      foreach ($flds as $f) {
        $val= isset($suma[$f]) ? $suma[$f] : '';
        $sum.= "<th style='text-align:right'>$val</th>";
      }
      $sum.= "</tr>";
    }
    $result->html.= "<h3>Počty strav včetně pečounů</h3>";
    $result->html.= "nejsou započteni pečouni, kteří mají prázdný sloupec funkce (asi jsou jen dočasní)";
    $result->html.= "<br><br><div class='stat'><table class='stat'><tr>$ths</tr>$sum$tab</table></div>";
    $result->html.= "</br>";
    $result->href= $href;
  }
  return $result;
}
# -------------------------------------------------------------------------------- akce_vyuctov_pary
# generování sestavy vyúčtování pro účastníky $akce - páry
#   $fld = seznam položek s prefixem
#   $cnd = podmínka
# počítané položky
#   manzele = rodina.nazev muz a zena
# generované vzorce
#   platit = součet předepsaných plateb
function akce_vyuctov_pary($akce,$par,$title,$vypis,$export=false) { trace();
  $ord= $par->ord ? $par->ord : "IF(funkce<=2,1,funkce),IF(pouze=0,r.nazev,o.prijmeni)";
  $result= (object)array();
  $tit= "Manželé:25"
      . ",pokoj:7,dětí:5:r,lůžka:5:r:s,přis týlky:5:r:s,kočá rek:5:r:s,nocí:5:r:s"
      . ",str. celá:5:r:S,str. pol.:5:r:s"
      . ",platba ubyt.:7:r:s,platba strava:7:r:s,platba režie:7:r:s,sleva:7:r:s,CD:6:r:s,celkem:7:r:s"
      . ",na účet:7:r:s,datum platby:10:d"
      . ",nedo platek:6:r:s,pokladna:6:r:s,přepl.:6:r:s,poznámka:50,SPZ:9,.:7"
      . ",ubyt.:8:r:s,DPH:6:r:s,strava:8:r:s,DPH:6:r:s,režie:8:r:s,zapla ceno:8:r:s"
      . ",dota ce:6:r:s,nedo platek:6:r:s,dar:7:r:s,rozpočet organizace:10:r:s"
      . "";
  $fld= "manzele"
      . ",pokoj,_deti,luzka,pristylky,kocarek,=pocetnoci,strava_cel,strava_pol"
      . ",platba1,platba2,platba3,platba4,=cd,=platit,platba,datplatby"
      . ",=nedoplatek,=pokladna,=preplatek,poznamka,spz,"
      . ",=ubyt,=ubytDPH,=strava,=stravaDPH,=rezie,=zaplaceno,=dotace,=nedopl,=dar,=naklad"
      . "";
  $cnd= 1;
  $html= '';
  $href= '';
  $n= 0;
  // dekódování parametrů
  $tits= explode(',',$tit);
  $flds= explode(',',$fld);
  $cond= 1;
  // získání dat - podle $kdo
  $clmn= array();       // pro hodnoty
  $expr= array();       // pro výrazy
  $suma= array();       // pro sumy sloupců id:::s
  $fmts= array();       // pro formáty sloupců id::f:
  for ($i= 0; $i<count($tits); $i++) {
    $idw= $tits[$i];
    $fld= $flds[$i];
    list($id,$w,$f,$sum)= explode(':',$idw);
    if ( $sum=='s' ) $suma[$fld]= 0;
    if ( isset($f) ) $fmts[$fld]= $f;
  }
  // data akce
  $qry=  "SELECT
          p.pouze,pokoj,luzka,pristylky,kocarek,pocetdnu,strava_cel,strava_pol,
            platba1,platba2,platba3,platba4,platba,datplatby,cd,p.poznamka,
          r.nazev as nazev,r.ulice,r.psc,r.obec,r.telefony,r.emaily,r.spz,
          SUM(IF(t.role='d',1,0)) as _deti,
          GROUP_CONCAT(DISTINCT IF(t.role='a',o.prijmeni,'') SEPARATOR '') as prijmeni_m,
          GROUP_CONCAT(DISTINCT IF(t.role='a',o.jmeno,'')    SEPARATOR '') as jmeno_m,
          GROUP_CONCAT(DISTINCT IF(t.role='a',o.narozeni,'') SEPARATOR '') as narozeni_m,
          GROUP_CONCAT(DISTINCT IF(t.role='a',o.rc_xxxx,'')  SEPARATOR '') as rc_xxxx_m,
          GROUP_CONCAT(DISTINCT IF(t.role='b',o.prijmeni,'') SEPARATOR '') as prijmeni_z,
          GROUP_CONCAT(DISTINCT IF(t.role='b',o.jmeno,'')    SEPARATOR '') as jmeno_z,
          GROUP_CONCAT(DISTINCT IF(t.role='b',o.narozeni,'') SEPARATOR '') as narozeni_z,
          GROUP_CONCAT(DISTINCT IF(t.role='b',o.rc_xxxx,'')  SEPARATOR '') as rc_xxxx_z
          FROM pobyt AS p
          JOIN spolu AS s USING(id_pobyt)
          JOIN osoba AS o ON s.id_osoba=o.id_osoba
          LEFT JOIN tvori AS t ON t.id_osoba=o.id_osoba
          LEFT JOIN rodina AS r USING(id_rodina)
          WHERE p.id_akce='$akce' AND $cond
          GROUP BY id_pobyt
          ORDER BY $ord";
//   $qry.=  " LIMIT 10";
  $res= mysql_qry($qry);
  while ( $res && ($x= mysql_fetch_object($res)) ) {
//                                         debug($x,"hodnoty");
    $n++;
    $clmn[$n]= array();
    $DPH1= 0.1;
    $DPH2= 0.2;
    foreach($flds as $f) {
      $exp= ''; $val= 0;
      if ( substr($f,0,1)=='=' ) {
        //            ubyt.         strava        režie         sleva
        $predpis= $x->platba1 + $x->platba2 + $x->platba3 + $x->platba4;
        $preplatek= $x->platba > $predpis ? $x->platba - $predpis : '';
        $nedoplatek= $x->platba < $predpis ? $predpis - $x->platba : '';
        $naklad= $predpis - $x->platba4;
        switch ($f) {
        case '=pocetnoci':  $val= max(0,$x->pocetdnu);
                            break;
        case '=platit':     $val= $predpis;
                            $exp= "=[platba1,0]+[platba2,0]+[platba3,0]+[platba4,0]"; break;
        case '=preplatek':  $val= $preplatek;
                            $exp= "=IF([platba,0]>[=platit,0],[platba,0]-[=platit,0],0)"; break;
        case '=nedoplatek': $val= $nedoplatek; break;
                            $exp= "=IF([=zaplaceno,0]<[=platit,0],[=platit,0]-[=zaplaceno,0],0)"; break;
        case '=pokladna':   $val= ''; break;
        case '=cd':         $val= 100.00*$x->cd; break;
        case '=ubyt':       $val= round($x->platba1/(1+$DPH1));
                            $exp= "=ROUND([platba1,0]/(1+$DPH1),0)"; break;
        case '=ubytDPH':    $val= round($x->platba1*$DPH1/(1+$DPH1));
                            $exp= "=[platba1,0]-[=ubyt,0]"; break;
        case '=strava':     $val= round($x->platba2/(1+$DPH2));
                            $exp= "=ROUND([platba2,0]/(1+$DPH2),0)"; break;
        case '=stravaDPH':  $val= round($x->platba2*$DPH2/(1+$DPH2));
                            $exp= "=[platba2,0]-[=strava,0]"; break;
        case '=rezie':      $val= 0+$x->platba3;
                            $exp= "=[platba3,0]"; break;
        case '=zaplaceno':  $val= 0+$x->platba;
                            $exp= "=[platba,0]+[=pokladna,0]"; break;
        case '=dotace':     $val= -$x->platba4;
                            $exp= "=-[platba4,0]"; break;
        case '=nedopl':     $val= $nedoplatek;
                            $exp= "=IF([=zaplaceno,0]<[=platit,0],[=platit,0]-[=zaplaceno,0],0)"; break;
        case '=dar':        $val= $preplatek;
                            $exp= "=IF([=zaplaceno,0]>[=platit,0],[=zaplaceno,0]-[=platit,0],0)"; break;
        case '=naklad':     $val= $naklad;
                            $exp= "=[=platit,0]-[platba4,0]"; break;
        default:            $val= '???'; break;
        }
        $clmn[$n][$f]= $val;
        if ( $exp ) $expr[$n][$f]= $exp;
      }
      else {
        switch ($f) {
        case 'manzele':
          $val= $x->pouze==1 ? "{$x->prijmeni_m} {$x->jmeno_m}"
             : ($x->pouze==2 ? "{$x->prijmeni_z} {$x->jmeno_z}"
             : "{$x->nazev} {$x->jmeno_m} a {$x->jmeno_z}");
          break;
        case 'jmena':
          $val= $x->pouze==1
              ? $x->jmeno_m : ($x->pouze==2 ? $x->jmeno_z : "{$x->jmeno_m} a {$x->jmeno_z}");
          break;
        case 'prijmeni':
          $val= $x->pouze==1 ? $x->prijmeni_m : ($x->pouze==2 ? $x->prijmeni_z : $x->nazev);
          break;
        default:
          $val= $f ? $x->$f : '';
          break;
        }
        if ( $f ) $clmn[$n][$f]= $val; else $clmn[$n][]= $val;
      }
      // případný výpočet sumy
      if ( isset($suma[$f]) ) {
         $suma[$f]+= $val;
      }
    }
  }
//                                         debug($clmn,"sestava pro $akce,$typ,$fld,$cnd");
//                                         debug($expr,"vzorce pro $akce,$typ,$fld,$cnd");
//                                         debug($suma,"sumy pro $akce B");
  // zobrazení tabulkou
  $tab= '';
  $thd= '';
  if ( $export ) {
    $result->tits= $tits;
    $result->flds= $flds;
    $result->clmn= $clmn;
    $result->expr= $expr;
    $result->DPH= array(
      "základ","=[=ubyt,s]+[=strava,s]+[=rezie,s]"
     ,"DPH ".($DPH2*100)."%","=[=stravaDPH,s]"
     ,"DPH ".($DPH1*100)."%","=[=ubytDPH,s]"
     ,"předpis celkem","=[=ubyt,s]+[=strava,s]+[=rezie,s]+[=stravaDPH,s]+[=ubytDPH,s]"
   );
  }
  else {
    // titulky
    foreach ($tits as $idw) {
      list($id)= explode(':',$idw);
      $ths.= "<th>$id</th>";
    }
    // data
    foreach ($clmn as $i=>$c) {
      $tab.= "<tr>";
      foreach ($c as $id=>$val) {
        $style= akce_sestava_td_style($fmts[$id]);
        $tab.= "<td$style>$val</td>";
      }
      $tab.= "</tr>";
    }
    // sumy
    $sum= '';
    if ( count($suma)>0 ) {
      $sum.= "<tr>";
      foreach ($flds as $f) {
        $val= isset($suma[$f]) ? $suma[$f] : '';
        $sum.= "<th style='text-align:right'>$val</th>";
      }
      $sum.= "</tr>";
    }
    $result->html= "<div class='stat'><table class='stat'><tr>$ths</tr>$sum$tab</table></div>";
    $result->html.= "</br>";
    $result->href= $href;
  }
  return $result;
}
# ----------------------------------------------------- akce_sestava_td_style
# $fmt= r|d
function akce_sestava_td_style($fmt) {
  $style= array();
  switch ($fmt) {
  case 'r': $style[]= 'text-align:right'; break;
  case 'd': $style[]= 'text-align:right'; break;
  case '!': $style[]= 'color:red'; break;
  }
  return count($style)
    ? " style='".implode(';',$style)."'" : '';
}
# ================================================================================================== SKUPINKY DĚTÍ
# -------------------------------------------------------------------------------- narozeni2roky_sql
# zjistí aktuální věk v rocích z data narození (typu mktime) zjištěného třeba rc2time          ?????
# pokud je předáno $now(jako timestamp) bere se věk k tomu
function narozeni2roky_sql($time_sql,$now_sql=0) {
  $time= sql2stamp($time_sql);
  $now= $now_sql ? sql2stamp($now_sql) : time();
  $roky= floor((date("Ymd",$now) - date("Ymd", $time)) / 10000);
  return $roky;
}
# ----------------------------------------------------------------------------------- akce_skup_deti
# tisk skupinek akce dětí
function akce_skup_deti($akce,$par,$title,$vypis,$export) {
  global $VPS;
  $result= (object)array();
  // celkový počet dětí na kurzu
  $qry= "SELECT SUM(IF(t.role='d',1,0)) AS _deti,SUM(IF(funkce=99,1,0)) AS _pecounu
         FROM akce AS a
         JOIN pobyt AS p ON a.id_duakce=p.id_akce
         JOIN spolu AS s ON p.id_pobyt=s.id_pobyt
         JOIN osoba AS o ON s.id_osoba=o.id_osoba
         LEFT JOIN tvori AS t ON t.id_osoba=o.id_osoba
         WHERE id_duakce='$akce'
         GROUP BY id_duakce ";
  $res= mysql_qry($qry);
  $pocet= mysql_fetch_object($res);
//                                                         debug($pocet,"počty");
  // zjištění skupinek
  $skupiny= array();   // [ skupinka => [{fce,příjmení,jméno},....], ...]
  $qry="SELECT id_pobyt,skupinka,funkce,prijmeni,jmeno,narozeni,rc_xxxx,datum_od
        FROM osoba AS o
        JOIN spolu AS s USING(id_osoba)
        JOIN pobyt AS p USING(id_pobyt)
        JOIN akce  AS a ON id_duakce='$akce'
        WHERE  id_akce='$akce' AND skupinka!=0
        ORDER BY skupinka,IF(funkce=99,0,1),prijmeni,jmeno ";
  $res= mysql_qry($qry);
  while ( $res && ($o= mysql_fetch_object($res)) ) {
    $o->_vek= narozeni2roky_sql($o->narozeni,$o->datum_od);
    $skupiny[$o->skupinka][]= $o;
  }
//                                                         debug($skupiny,"skupiny");
  $n= 0;
  $deti_in= $pecouni_in= 0;
  if ( $export ) {
    $clmn= array();
    foreach ($skupiny as $i=>$s) {
      foreach ($s as $c) {
//                                                         debug($c,"$i");
        $clmn[$n]['skupinka']= $c->funkce==99 ? $c->skupinka : '';
        $clmn[$n]['prijmeni']= $c->prijmeni;
        $clmn[$n]['jmeno']= $c->jmeno;
        $clmn[$n]['vek']= $c->_vek;
        $n++;
      }
      $clmn[$n]['skupinka']= $clmn[$n]['jmeno']= $clmn[$n]['prijmeni']= $clmn[$n]['vek']= '';
      $n++;
    }
    $result->tits= explode(',',"skupinka:10,příjmení:20,jméno:20,věk:4:r");
    $result->flds= explode(',',"skupinka,prijmeni,jmeno,vek");
    $result->clmn= $clmn;
    $result->expr= null;
  }
  else {
    $tab= '';
    $r= "style='text-align:right'";
    foreach ($skupiny as $i=>$s) {
      $tab.= "<table class='stat'>";
      $tab.= "<tr><th width=200 colspan=3>Skupinka $i</th></tr>";
      foreach ($s as $o) {
        if ( $o->funkce==99 ) {
          $tab.= "<tr><th>{$o->prijmeni}</th><th>{$o->jmeno}</th><th $r>{$o->_vek}</th></tr>";
          $pecouni_in++;
        }
        else {
          $tab.= "<tr><td>{$o->prijmeni}</td><td>{$o->jmeno}</td><td $r>{$o->_vek}</td></tr>";
          $deti_in++;
        }
      }
      $tab.= "</table><br/>";
    }
    $deti_out= $pocet->_deti - $deti_in;
    $pecouni_out= $pocet->_pecounu - $pecouni_in;
    $msg= $deti_out>0 ? "Celkem $deti_out dětí není zařazeno do skupinek": '';
    if ( $deti_out>0 ) fce_warning($msg);
    $msg.= $pecouni_out>0 ? "<br>Celkem $pecouni_out pečounů není zařazeno do skupinek<br><br>": '';
    $result->html= "$msg$tab";
  }
//                                                         debug($result,"result");
  return $result;
}
# ================================================================================================== SKUPINKY
# ------------------------------------------------------------------------------------ akce_skupinky
# generování pomocných sestav pro tvorbu skupinek
#   $par->fce = plachta | prehled
function akce_skupinky($akce,$par,$title,$vypis,$export=false) {
  return $par->fce=='plachta'  ? akce_plachta($akce,$par,$title,$vypis,$export)
     : ( $par->fce=='prehled'  ? akce_skup_hist($akce,$par,$title,$vypis,$export)
     : ( $par->fce=='tisk'     ? akce_skup_tisk($akce,$par,$title,$vypis,$export)
                               : (object)array('html'=>'sestava ještě není hotova') ));
}
# ---------------------------------------------------------------------------------- akce_skup_check
# zjištění konzistence skupinek podle příjmení VPS/PPS
function akce_skup_check($akce) {
  return akce_skup_get($akce,1,$err);
}
# ------------------------------------------------------------------------------------ akce_skup_get
# zjištění skupinek podle příjmení VPS/PPS
function akce_skup_get($akce,$kontrola,&$err,$par=null) { trace();
  global $VPS;
  $msg= array();
  $skupiny= array();
  $celkem= select('count(*)','pobyt',"id_akce=$akce AND funkce IN (0,1,2)");
  $n= 0;
  $err= 0;
  $order= $all= array();
  $qry= "
      SELECT skupina,
        SUM(IF(funkce=2,1,0)) as _n_svps,
        SUM(IF(funkce=1,1,0)) as _n_vps,
        GROUP_CONCAT(DISTINCT IF(funkce=2,id_pobyt,'') SEPARATOR '') as _svps,
        GROUP_CONCAT(DISTINCT IF(funkce=1,id_pobyt,'') SEPARATOR '') as _vps,
        GROUP_CONCAT(DISTINCT id_pobyt) as _skupina
      FROM akce AS a
      JOIN pobyt AS p ON a.id_duakce=p.id_akce
      WHERE p.id_akce=$akce AND skupina!=0
      GROUP BY skupina ";
  $res= mysql_qry($qry);
  while ( $res && ($s= mysql_fetch_object($res)) ) {
    if ( $s->_n_svps==1 || $s->_n_vps==1 ) {
      $skupina= array();
      $qryu= "
          SELECT p.id_pobyt,skupina,nazev,pokoj,
            GROUP_CONCAT(DISTINCT IF(t.role IN ('a','b'),o.id_osoba,'')) as ids_osoba,
            GROUP_CONCAT(DISTINCT IF(t.role='a',o.id_osoba,'') SEPARATOR '') as id_osoba_m,
            CASE WHEN pouze=0 THEN
              CONCAT(nazev,' ',
                GROUP_CONCAT(DISTINCT IF(t.role='a',o.jmeno,'') SEPARATOR ''),' a ',
                GROUP_CONCAT(DISTINCT IF(t.role='b',o.jmeno,'') SEPARATOR ''))
            WHEN pouze=1 THEN
              CONCAT(
                GROUP_CONCAT(DISTINCT IF(t.role='a',o.prijmeni,'') SEPARATOR ''),' ',
                GROUP_CONCAT(DISTINCT IF(t.role='a',o.jmeno,'') SEPARATOR ''))
            WHEN pouze=2 THEN
              CONCAT(
                GROUP_CONCAT(DISTINCT IF(t.role='b',o.prijmeni,'') SEPARATOR ''),' ',
                GROUP_CONCAT(DISTINCT IF(t.role='b',o.jmeno,'') SEPARATOR ''))
            END AS _nazev
          FROM pobyt AS p
          JOIN spolu AS s USING(id_pobyt)
          JOIN osoba AS o ON s.id_osoba=o.id_osoba
          LEFT JOIN tvori AS t ON t.id_osoba=o.id_osoba
          LEFT JOIN rodina AS r USING(id_rodina)
          WHERE p.id_pobyt IN ({$s->_skupina})
          GROUP BY id_pobyt
          ORDER BY IF(funkce IN (1,2),1,2), nazev";
      $resu= mysql_qry($qryu);
      while ( $resu && ($u= mysql_fetch_object($resu)) ) {
        $mark= '';
        if ( $par && $par->mark=='novic' ) {
          // minulé účasti
          $ids= $u->ids_osoba;
          $rqry= "SELECT count(*) as _pocet
                  FROM akce AS a
                  JOIN pobyt AS p ON a.id_duakce=p.id_akce
                  JOIN spolu AS s USING(id_pobyt)
                  WHERE a.druh=1 AND s.id_osoba IN ($ids) AND p.id_akce!=$akce";
          $rres= mysql_qry($rqry);
          if ( $rres && ($r= mysql_fetch_object($rres)) ) {
            $mark= $r->_pocet;
          }
          $mark= $mark==0 ? '* ' : '';
        }
        $u->_nazev= "$mark {$u->_nazev}";
        $skupina[$u->id_pobyt]= $u;
        $n++;
      }
      $vps= $s->_svps ? $s->_svps : $s->_vps;
      $skupiny[$vps]= $skupina;
      $all[]= $vps;
    }
    elseif ( $s->_vps || $s->_vps ) {
      $msg[]= "skupinka {$s->skupina} má nejednoznačnou $VPS";
      $err+= 2;
    }
    else {
      $msg[]= "skupinka {$s->skupina} nemá $VPS";
      $err+= 4;
    }
  }
  // řazení - v PHP nelze udělat
  if ( count($all) ) {
    $qryo= "SELECT GROUP_CONCAT(DISTINCT CONCAT(id_pobyt,'|',nazev) ORDER BY nazev) as _o
            FROM pobyt AS p
            JOIN spolu AS s USING(id_pobyt)
            JOIN osoba AS o ON s.id_osoba=o.id_osoba
            LEFT JOIN tvori AS t ON t.id_osoba=o.id_osoba
            LEFT JOIN rodina AS r USING(id_rodina)
            WHERE id_pobyt IN (".implode(',',$all).") ";
    $reso= mysql_qry($qryo);
    while ( $reso && ($o= mysql_fetch_object($reso)) ) {
      foreach (explode(',',$o->_o) as $pair) {
        list($id,$name)= explode('|',$pair);
        $order[$id]= $name;
      }
    }
  }
//                                                         debug($order,"order");
  $skup= array();
  foreach($order as $i=>$nam) {
    $skup[$i]= $skupiny[$i];
  }
//                                                         debug($skup,"skupiny");
  // redakce chyb
  if ( $celkem!=$n ) {
    $msg[]= ($celkem-$n)." účastníků není zařazeno do skupinek";
    $err+= 1;
  }
  if ( count($msg) && !$kontrola )
    fce_warning(implode(",<br>",$msg));
  elseif ( !count($msg) && $kontrola )
    $msg[]= "Vše je ok";
  // konec
  return $kontrola ? implode(",<br>",$msg) : $skup;
}
# ---------------------------------------------------------------------------------- akce_skup_renum
# přečíslování skupinek podle příjmení VPS/PPS
function akce_skup_renum($akce) {
  $err= 0;
  $msg= '';
  $skupiny= akce_skup_get($akce,0,$err);
  if ( $err>1 ) {
    $msg= "skupinky nejsou dobře navrženy - ještě je nelze přečíslovat";
  }
  else {
    $n= 1;
    foreach($skupiny as $ivps=>$skupina) {
      $cleni= implode(',',array_keys($skupina));
      $qryu= "UPDATE pobyt SET skupina=$n WHERE id_pobyt IN ($cleni) ";
      $resu= mysql_qry($qryu);
//                                                         display("$n: $qryu");
      $n++;
    }
    $msg= "bylo přečíslováno $n skupinek";
  }
  return $msg;
}
# ----------------------------------------------------------------------------------- akce_skup_tisk
# tisk skupinek akce
function akce_skup_tisk($akce,$par,$title,$vypis,$export) {
  global $VPS;
  $result= (object)array();
  $html= "<table>";
  $skupiny= akce_skup_get($akce,0,$err,$par);
  $n= 0;
  if ( $export ) {
    $clmn= array();
    foreach ($skupiny as $i=>$s) {
      foreach ($s as $c) {
        $clmn[$n]['skupina']= $i==$c->id_pobyt ? $c->skupina : '';
        $clmn[$n]['jmeno']= $c->_nazev;
        $clmn[$n]['pokoj']= $i==$c->id_pobyt ? $c->pokoj : '';
        $n++;
      }
      $clmn[$n]['skupina']= $clmn[$n]['jmeno']= $clmn[$n]['pokoj']= '';
      $n++;
    }
    $result->tits= explode(',',"skupinka:10,jméno:30,pokoj $VPS:10:r");
    $result->flds= explode(',',"skupina,jmeno,pokoj");
    $result->clmn= $clmn;
    $result->expr= null;
  }
  else {
    foreach ($skupiny as $i=>$s) {
      $tab= "<table>";
      foreach ($s as $c) {
        if ( $i==$c->id_pobyt )
          $tab.= "<tr><th>{$c->skupina}</th><th>{$c->_nazev}</th><th>{$c->pokoj}</th></tr>";
        else
          $tab.= "<tr><td></td><td>{$c->_nazev}</td><td></td></tr>";
      }
      $tab.= "</table>";
      if ( $n%2==0 )
        $html.= "<tr><td>&nbsp;</td></tr><tr><td valign='top'>$tab</td>";
      else
        $html.= "<td valign='top'>$tab</td></tr>";
      $n++;
    }
    if ( $n%2==1 )
      $html.= "<td></td></tr>";
    $html.= "</table>";
    $result->html= $html;
  }
  return $result;
}
# ----------------------------------------------------------------------------------- akce_skup_hist
# přehled starých skupinek letního kurzu MS účastníků této akce
function akce_skup_hist($akce,$par,$title,$vypis,$export) { trace();
  $result= (object)array();
  $html= "<dl>";
  // letošní účastníci
  $letos= array();
  $qry=  "SELECT skupina,r.nazev,r.obec,year(datum_od) as rok,p.funkce as funkce,
            GROUP_CONCAT(DISTINCT IF(t.role='a',o.id_osoba,'') SEPARATOR '') as id_osoba_m,
            GROUP_CONCAT(DISTINCT IF(t.role='a',o.jmeno,'')    SEPARATOR '') as jmeno_m,
            GROUP_CONCAT(DISTINCT IF(t.role='b',o.jmeno,'')    SEPARATOR '') as jmeno_z,
            id_pobyt
          FROM pobyt AS p
          JOIN akce  AS a ON a.id_duakce=p.id_akce
          JOIN spolu AS s USING(id_pobyt)
          JOIN osoba AS o ON s.id_osoba=o.id_osoba
          LEFT JOIN tvori AS t ON t.id_osoba=o.id_osoba
          LEFT JOIN rodina AS r USING(id_rodina)
          WHERE id_akce=$akce AND p.funkce IN (0,1,2,5)
          GROUP BY id_pobyt
          ORDER BY IF(pouze=0,r.nazev,o.prijmeni) ";
  $res= mysql_qry($qry);
  while ( $res && ($u= mysql_fetch_object($res)) ) {
    $muz= $u->id_osoba_m;
    $letos[$muz]= $u;
    $letos[$muz]->_nazev= $u->nazev;
    $rok= $u->rok;
  }
//                                                         debug($letos);
  $letosni= implode(',',array_keys($letos));
  // doplnění nejednoznačných příjmení o město
  $old= 0; $old_nazev= '';
  foreach ($letos as $muz=>$info) {
    if ( $old_nazev==$info->_nazev ) {
      $letos[$old]->_nazev= $letos[$old]->nazev.' '.$letos[$old]->jmeno_m.' '.$letos[$old]->jmeno_z;
      $letos[$muz]->_nazev= $letos[$muz]->nazev.' '.$letos[$muz]->jmeno_m.' '.$letos[$muz]->jmeno_z;
    }
    $old= $muz;
    $old_nazev= $info->_nazev;
  }
//                                                         debug($odkud);
  // tisk
  foreach ($letos as $muz=>$info) {
    // minulé účasti
    $n= 0;
    $qry= " SELECT p.id_akce,skupina,year(datum_od) as rok
            FROM akce AS a
            JOIN pobyt AS p ON a.id_duakce=p.id_akce
            JOIN spolu AS s USING(id_pobyt)
            WHERE a.druh=1 AND s.id_osoba='$muz' AND p.id_akce!=$akce AND skupina!=0
            ORDER BY datum_od DESC ";
    $res= mysql_qry($qry);
    $ucasti= '';
    while ( $res && ($r= mysql_fetch_object($res)) ) {
      $n++;
      // minulé skupinky
      $qry_s= "
            SELECT GROUP_CONCAT(DISTINCT IF(t.role='a',o.id_osoba,'') SEPARATOR '') as id_osoba_m
            FROM akce AS a
            JOIN pobyt AS p ON a.id_duakce=p.id_akce
            JOIN spolu AS s USING(id_pobyt)
            JOIN osoba AS o ON s.id_osoba=o.id_osoba
            LEFT JOIN tvori AS t ON t.id_osoba=o.id_osoba
            WHERE p.id_akce={$r->id_akce} AND skupina={$r->skupina}
            GROUP BY id_pobyt HAVING FIND_IN_SET(id_osoba_m,'$letosni')
            ORDER BY datum_od DESC ";
      $res_s= mysql_qry($qry_s);
      $spolu= ''; $del= '';
      while ( $res_s && ($s= mysql_fetch_object($res_s)) ) if ( $s->id_osoba_m!=$muz ) {
        $spolu.= "$del{$letos[$s->id_osoba_m]->_nazev}";
        $del= ', ';
      }
      if ( $spolu ) {
        $ucasti.= " <u>{$r->rok}</u>: $spolu";
      }
    }
    if ( $ucasti )
      $html.= "<dt><b>{$info->_nazev}</b> $n&times;<dd>$ucasti</dd></dt>";
    elseif ( $n )
      $html.= "<dt><b>{$info->_nazev}</b> $n&times;</dt>";
    else
      $html.= "<dt><b>{$info->_nazev}</b> - bude poprvé</dt>";
  }
  $html.= "</dl>";
  $note= "Abecední seznam účastníků letního kurzu roku $rok doplněný seznamem členů jeho starších
          skupinek na letních kurzech. <br>Ve skupinkách jsou uvedení jen účastníci
          kurzu roku $rok. (Pro tisk je nejjednodušší označit jako blok a vložit do Wordu.)";
  $html= "<i>$note</i><br>$html";
  $result->html= $html;
  return $result;
}
# ------------------------------------------------------------------------------------- akce_plachta
# podklad pro tvorbu skupinek
function akce_plachta($akce,$par,$title,$vypis,$export=0) { trace();
  // číselníky
  $c_vzdelani= map_cis('ms_akce_vzdelani','zkratka');  $c_vzdelani[0]= '?';
  $c_cirkev= map_cis('ms_akce_cirkev','zkratka');      $c_cirkev[0]= '?';  $c_cirkev[1]= 'kat';
  $letos= date('Y');
  $html= "";
  $excel= array();
//   $html.= "<table class='vypis'>";
  // letošní účastníci
  $qry=  "SELECT
          r.nazev as jmeno,p.pouze as pouze,r.obec as mesto,svatba,datsvatba,p.funkce as funkce,
          GROUP_CONCAT(DISTINCT IF(t.role='a',o.id_osoba,'') SEPARATOR '') as id_osoba_m,
          GROUP_CONCAT(DISTINCT IF(t.role='a',o.prijmeni,'') SEPARATOR '') as prijmeni_m,
          GROUP_CONCAT(DISTINCT IF(t.role='a',o.jmeno,'')    SEPARATOR '') as jmeno_m,
          GROUP_CONCAT(DISTINCT IF(t.role='a',o.narozeni,'') SEPARATOR '') as narozeni_m,
          GROUP_CONCAT(DISTINCT IF(t.role='a',o.vzdelani,'') SEPARATOR '') as vzdelani_m,
          GROUP_CONCAT(DISTINCT IF(t.role='a',o.cirkev,'')   SEPARATOR '') as cirkev_m,
          GROUP_CONCAT(DISTINCT IF(t.role='a',o.aktivita,'') SEPARATOR '') as aktivita_m,
          GROUP_CONCAT(DISTINCT IF(t.role='a',o.zajmy,'')    SEPARATOR '') as zajmy_m,
          GROUP_CONCAT(DISTINCT IF(t.role='a',o.zamest,'')   SEPARATOR '') as zamest_m,
          GROUP_CONCAT(DISTINCT IF(t.role='b',o.id_osoba,'') SEPARATOR '') as id_osoba_z,
          GROUP_CONCAT(DISTINCT IF(t.role='b',o.prijmeni,'') SEPARATOR '') as prijmeni_z,
          GROUP_CONCAT(DISTINCT IF(t.role='b',o.jmeno,'')    SEPARATOR '') as jmeno_z,
          GROUP_CONCAT(DISTINCT IF(t.role='b',o.narozeni,'') SEPARATOR '') as narozeni_z,
          GROUP_CONCAT(DISTINCT IF(t.role='b',o.vzdelani,'') SEPARATOR '') as vzdelani_z,
          GROUP_CONCAT(DISTINCT IF(t.role='b',o.cirkev,'')   SEPARATOR '') as cirkev_z,
          GROUP_CONCAT(DISTINCT IF(t.role='b',o.aktivita,'') SEPARATOR '') as aktivita_z,
          GROUP_CONCAT(DISTINCT IF(t.role='b',o.zajmy,'')    SEPARATOR '') as zajmy_z,
          GROUP_CONCAT(DISTINCT IF(t.role='b',o.zamest,'')   SEPARATOR '') as zamest_z,
          ( SELECT COUNT(*)
            FROM osoba JOIN tvori USING(id_osoba)
            WHERE id_rodina=t.id_rodina AND role='d' ) AS deti,
          ( SELECT MIN(narozeni)
            FROM osoba JOIN tvori USING(id_osoba)
            WHERE id_rodina=t.id_rodina AND role='d' ) AS maxdeti,
          ( SELECT MAX(narozeni)
            FROM osoba JOIN tvori USING(id_osoba)
            WHERE id_rodina=t.id_rodina AND role='d' ) AS mindeti
          FROM pobyt AS p
          JOIN spolu AS s USING(id_pobyt)
          JOIN osoba AS o ON s.id_osoba=o.id_osoba
          LEFT JOIN tvori AS t ON t.id_osoba=o.id_osoba
          LEFT JOIN rodina AS r USING(id_rodina)
          WHERE id_akce=$akce AND p.funkce IN (0,1,2,5)
          GROUP BY id_pobyt
          ORDER BY IF(pouze=0,r.nazev,o.prijmeni) ";
//   $qry.= " LIMIT 1";
  $res= mysql_qry($qry);
  while ( $res && ($u= mysql_fetch_object($res)) ) {
    $muz= $u->id_osoba_m;
    // minulé účasti
    $rqry= "SELECT count(*) as _pocet
            FROM akce AS a
            JOIN pobyt AS p ON a.id_duakce=p.id_akce
            JOIN spolu AS s USING(id_pobyt)
            WHERE a.druh=1 AND s.id_osoba=$muz AND p.id_akce!=$akce";
    $rres= mysql_qry($rqry);
    while ( $rres && ($r= mysql_fetch_object($rres)) ) {
      $u->ucasti= $r->_pocet ? "  {$r->_pocet}x" : '';
    }
    // věk
    $vek_m= sql2roku($u->narozeni_m);
    $vek_z= sql2roku($u->narozeni_z);
    $vek= abs($vek_m-$vek_z)<5 ? $vek_m : "$vek_m/$vek_z";
    // spolu
    $spolu= '?';
    if ( $u->datsvatba ) {
      $spolu= sql2roku($u->datsvatba);
    }
    elseif ( $u->svatba ) {
      $spolu= $letos-$u->svatba;
    }
    // děti
    $deti= $u->deti;
    if ( $deti ) {
      if ( $u->mindeti!='0000-00-00' && $u->maxdeti!='0000-00-00' ) {
        $deti.= "(".sql2roku($u->mindeti);
        if ( $deti>1 )
          $deti.= "-".sql2roku($u->maxdeti);
        $deti.= ")";
      }
      else
        $deti.= "(?)";
    }
    // vzdělání
    $vzdelani_muze= mb_substr($c_vzdelani[$u->vzdelani_m],0,2,"UTF-8");
    $vzdelani_zeny= mb_substr($c_vzdelani[$u->vzdelani_z],0,2,"UTF-8");
    $vzdelani= $vzdelani_muze==$vzdelani_zeny ? $vzdelani_muze : "$vzdelani_muze/$vzdelani_zeny";
//                                                         display("$vek_m/$vek_z=$vek");
    // konfese
    $cirkev= $u->cirkev_m==$u->cirkev_z
      ? ($u->cirkev_m==1 ? '' : ", {$c_cirkev[$u->cirkev_m]}")
      : ", {$c_cirkev[$u->cirkev_m]}/{$c_cirkev[$u->cirkev_z]}";
    // agregace
    $r1= ($u->funkce==1||$u->funkce==2 ? '* ' : '')."{$u->jmeno} {$u->jmeno_m} a {$u->jmeno_z} {$u->ucasti}";
    $r2= "věk:$vek, spolu:$spolu, dětí:$deti, {$u->mesto}, $vzdelani $cirkev";
    // atributy
    $r31= $u->aktivita_m;
    $r32= $u->aktivita_z;
    $r41= $u->zajmy_m;
    $r42= $u->zajmy_z;
    $r51= $u->zamest_m;
    $r52= $u->zamest_z;
    // listing
    $html.= "<table class='vypis' style='width:300px'>";
    $html.= "<tr><td colspan=2><b>$r1</b></td></tr>";
    $html.= "<tr><td colspan=2>$r2</td></tr>";
    $html.= "<tr><td>$r31</td><td>$r32</td></tr>";
    $html.= "<tr><td>$r41</td><td>$r42</td></tr>";
    $html.= "<tr><td>$r51</td><td>$r52</td></tr>";
    $html.= "</table><br/>";
    if ( $export ) {
      $excel[]= array($r1,$r2,$r31,$r41,$r51,$r32,$r42,$r52,$vzdelani_muze,$vek_m);
    }
  }
//                                                 debug($excel);
  if ( $export ) {
    $result->xhref= akce_plachta_export($excel,'plachta');
  }
  $result->html= $html;
  return $result;
}
// ---------------------------------------------- roku
// vrací zaokrouhlený počet roku od narození poteď
function sql2roku($narozeni) {
  $roku= '';
  if ( $narozeni && $narozeni!='0000-00-00' ) {
    list($y,$m,$d)= explode('-',$narozeni);
    $now= time();
    $nar= mktime(0,0,0,$m,$d,$y)+1;
//     $roku= ($now-$nar)/(60*60*24*365.2425);
    $roku= ceil(($now-$nar)/(60*60*24*365.2425));
  }
  return $roku;
};
# ------------------------------------------------------------------------------ akce_plachta_export
function akce_plachta_export($line,$file) { trace();
  require_once('./ezer2/server/licensed/xls/OLEwriter.php');
  require_once('./ezer2/server/licensed/xls/BIFFwriter.php');
  require_once('./ezer2/server/licensed/xls/Worksheet.php');
  require_once('./ezer2/server/licensed/xls/Workbook.php');
  global $ezer_path_root;
  chdir($ezer_path_root);
  $name= cz2ascii("vypis_").date("Ymd_Hi");
  $table= "docs/$name.xls";
  try {
    $wb= new Workbook($table);
    // formáty
    $format_hd= $wb->add_format();
    $format_hd->set_bold();
    $format_hd->set_pattern();
    $format_hd->set_fg_color('silver');
    $format_dec= $wb->add_format();
    $format_dec->set_num_format("# ##0.00");
    $format_dat= $wb->add_format();
    $format_dat->set_num_format("d.m.yyyy");
    // list LK
    $ws= $wb->add_worksheet("Hodnoty");
    // hlavička
    $fields= explode(',','r1:20,r2:20,r31:20,r41:20,r51:20,r32:20,r42:20,r52:20,skola:8,vek:8');
    $sy= 0;
    foreach ($fields as $sx => $fa) {
      list($title,$width)= explode(':',$fa);
      $ws->set_column($sx,$sx,$width);
      $ws->write_string($sy,$sx,utf2win_sylk($title,true),$format_hd);
    }
    // data
    foreach($line as $x) {
      $sy++; $sx= 0;
      $ws->write_string($sy,$sx++,utf2win_sylk($x[0],true));
      $ws->write_string($sy,$sx++,utf2win_sylk($x[1],true));
      $ws->write_string($sy,$sx++,utf2win_sylk($x[2],true));
      $ws->write_string($sy,$sx++,utf2win_sylk($x[3],true));
      $ws->write_string($sy,$sx++,utf2win_sylk($x[4],true));
      $ws->write_string($sy,$sx++,utf2win_sylk($x[5],true));
      $ws->write_string($sy,$sx++,utf2win_sylk($x[6],true));
      $ws->write_string($sy,$sx++,utf2win_sylk($x[7],true));
      $ws->write_string($sy,$sx++,utf2win_sylk($x[8],true));
      $ws->write_number($sy,$sx++,$x[9]);
    }
    $wb->close();
    $html= " Výpis byl vygenerován ve formátu <a href='docs/$name.xls' target='xls'>Excel</a>.";
    $html.= " <br>Vygenerovaným listem <b>Hodnoty</b> je třeba nahradit stejnojmenný list v sešitu";
    $html.= " <b>doc/plachta11.xls</b> a dále postupovat podle návodu v listu <b>Návod</b>.";
  }
  catch (Exception $e) {
    $html.= nl2br("Chyba: ".$e->getMessage()." na ř.".$e->getLine());
  }
  return $html;
}
# ================================================================================================== BANKA
# ------------------------------------------------------------------------------------- akce_rb_urci
# pokus o určení plátce a účelu platby
function akce_rb_urci($vs,$ss,$datum) {  trace();
  $result= (object)array('id_rodina'=>0,'id_osoba'=>0,'tipy'=>'');
  // určení osoby a rodiny
  $tipy= array();
  $presne= false;
  $narozeni= rc2ymd($vs);
  $rc_xxxx= strlen($vs)==10 ? substr($vs,6,4) : '0000';
  $AND= strlen($vs)==10 ? " AND rc_xxxx='".substr($vs,6,4)."'" : '';
  $qry= "SELECT id_osoba,id_rodina,prijmeni,jmeno,rc_xxxx FROM osoba
         LEFT JOIN tvori AS t USING(id_osoba)
         WHERE narozeni='$narozeni' ";
  $res= mysql_qry($qry);
  $n= mysql_num_rows($res);
  if ( !$n ) {
    $html.= "plátce nenalezen";
  }
  else {
    while ( $res && $o= mysql_fetch_object($res) ) {
      if ( $o->rc_xxxx==$rc_xxxx ) {
        $presne= true;
        $html.= " {$o->prijmeni} {$o->jmeno} - {$o->rc_xxxx}";
        $result->id_rodina= $o->id_rodina;
        $result->id_osoba= $o->id_osoba;
        break;
      }
      $tipy[]= $o;
    }
    if ( !$presne ) {
      foreach($tipy as $o) {
        $result->tipy.= "{$o->id_osoba}|{$o->prijmeni} {$o->jmeno}|";
        $html.= "<br>{$o->id_osoba}:{$o->prijmeni} {$o->jmeno} - {$o->rc_xxxx}";
      }
    }
  }
  // určení akce podle SS a roku (zjednodušení)
  $rok= substr($datum,0,4);
  $qa= "SELECT id_duakce,g_kod,g_rok
        FROM ezer_ys.akce AS da2
        LEFT JOIN join_akce AS ja2 ON ja2.id_akce=da2.id_duakce
        LEFT JOIN g_akce AS ga2 USING(g_rok,g_kod)
        WHERE g_rok=$rok AND g_kod='$ss' ";
  $ra= mysql_qry($qa);
  $n= mysql_num_rows($ra);
  if ( $n==1 ) {
    $a= mysql_fetch_object($ra);
    $result->id_duakce= $a->id_duakce;
  }
  // konec
  $result->html= $html;
//                                                 debug($result,"akce_rb_urci($vs,$ss)");
  return $result;
}
# ----------------------------------------------------------------------------------- akce_rb_platby
# přečtení pohybů na transparentních účtech RB
function akce_rb_platby() {  trace();
  $html= '';
  $html.= akce_rb_ucet("514048001","M");
  $html.= "<br>";
  $html.= akce_rb_ucet("514048044","D");
//   $html.= "<br>";
//   $html.= akce_rb_ucet("514048052","D");
  return $html;
}
# ------------------------------------------------------------------------------------- akce_rb_ucet
# přečtení pohybů na transparentním účtu RB
function akce_rb_ucet($cislo,$nazev) {  trace();
  $n= 0;
  $html= '';
  $dom= new DOMDocument();
  $page= file_get_contents("http://www.rb.cz/firemni-finance/transparentni-ucty/?root=firemni-finance"
     . "&item1=transparentni-ucty&tr_acc=vypis&account_number=$cislo" );
  $ok= @$dom->loadHTML($page);
  if ( $ok ) {
    // kontrola hlavičky
    $thead= $dom->getElementsByTagName('thead');                // DOMNodeList
    if ( $thead->length ) {
      $trs= $thead->item(0)->getElementsByTagName('tr');        // DOMNode DOMNodeList
      for ($i= 0; $i < $trs->length; $i++) {
        $tds= $trs->item($i)->getElementsbyTagName('th');       // DOMNode DOMNodeList
        for ($j= 0; $j < $tds->length; $j++) {
          $typ= $tds->item($j)->nodeType;                       // DOMNode
          $wh[$i][$j]= dom_shownode($tds->item($j));
        }
      }
      // test hlavičky
      $head= array("Datum|Čas","Poznámky|název účtu","Datum odepsání|valuta|typ",
        "Variabilní symbol|konstantní symbol|specifický symbol","|částka|","Poplatek|směna|zpráva");
      $ok= true;
      for ($j= 0; $j<count($head); $j++) {
        if ($wh[0][$j]!=$head[$j] ) $ok= false;
      }
      if ( !$ok ) fce_warning("změna formátu hlavička tabulky");
    }
    else fce_warning("chybí hlavička tabulky");
  }
  if ( $ok ) {
    // datové řádky
    $tbody= $dom->getElementsByTagName('tbody');
    if ( $tbody->length ) {
      $trs= $tbody->item(0)->getElementsByTagName('tr');
      for ($i= 0; $i < $trs->length; $i++) {
        $n++;
        $tds= $trs->item($i)->getElementsbyTagName('td');
        for ($j= 0; $j < $tds->length; $j++) {
          $wh[$i+1][$j].= dom_shownode($tds->item($j));
        }
//                                                 break;
      }
    }
    else fce_warning("chybí tabulka");
//                                                 debug($wh,"platby",(object)array('html'=>1));
  }
  if ( $ok ) {
    $nove= 0; $lst= '';
    // zpracování tabulky
    for ($i= 1; $i<=$n; $i++) {
      list($datum,$cas)=                explode('|',$wh[$i][0]);
      list($pozn,$ucet)=                explode('|',$wh[$i][1]);
      list($datum2,$valuta,$typ)=       explode('|',$wh[$i][2]);
      list($vs,$ks,$ss)=                explode('|',$wh[$i][3]);
      list($castka)=                    explode('|',$wh[$i][4]);
      list($poplatek,$smena,$zprava)=   explode('|',$wh[$i][5]);
      // transformace
      $datum= substr($datum,0,10);
      // výběr informace
      if ( $castka>0 ) {
        // vložení nových informací do tabulky PLATBA - datum,castka,ucet,vs,ks,ss
        $qry= "SELECT * FROM platba
               WHERE ucet='$nazev' AND datum='$datum' AND castka='$castka' AND ucet_nazev='$ucet'
                 AND vs='$vs' AND ks='$ks' AND ss='$ss'";
        $res= mysql_qry($qry);
        if ( !mysql_num_rows($res) ) {
          // vložení nové platby
          $qryu= "INSERT INTO platba (
            ucet,castka,datum,poznamka,zpusob,ucet_nazev,vs,ks,ss) VALUES (
            '$nazev','$castka','$datum','$pozn',1,'$ucet','$vs','$ks','$ss')";
          $resu= mysql_qry($qryu);
          $nove++;
          $lst.= "<br>$castka $pozn $ucet";
        }
      }
    }
    $html.= $nove ? "Vloženo $nove nových plateb z $ucet:<br>$lst" : "Na $cislo nepřišly nové platby";
  }
  if ( !$ok ) {
    $html.= "Při zpracování plateb účtu $cislo došlo k chybě";
  }
  return $html;
}
# ---------------------------------------------------------------- xml funkce
function dom_shownode($x) {
  $txt= '';
  foreach ($x->childNodes as $p)
    if ( dom_hasChild($p) ) {
      $txt.= dom_shownode($p);
    }
    elseif ($p->nodeType == XML_ELEMENT_NODE)
      $txt.= "|";
    elseif ($p->nodeType == XML_TEXT_NODE)
      $txt.= trim($p->nodeValue);
  return $txt;
}
function dom_hasChild($p) {
  if ($p->hasChildNodes()) {
    foreach ($p->childNodes as $c) {
      if ($c->nodeType == XML_ELEMENT_NODE)
        return true;
    }
  }
  return false;
}
# ================================================================================================== GOOGLE
# -------------------------------------------------------------------------------- akce_google_cleni
# přečtení listu "Kroměříž 10" z tabulky "ČlenovéYS_2010-2011"
# načítají se jen řádky ve kterých typ je číslo
function akce_google_cleni() {  trace();
  $n= 0;
  $html= '';
  $cells= google_sheet($ws="Kroměříž 10",$wt="ČlenovéYS_2010-2011",'answer@smidek.eu',$google);
  if ( $cells ) {
    list($max_A,$max_n)= $cells['dim'];
//                                                 debug($cells,"přehled členů");
    for ($i= 1; $i<$max_n; $i++) {
      if ( is_numeric($cells['A'][$i]) ) {
        $par= $cells['B'][$i];
        $vps= $cells['C'][$i];
        $castka= $cells['G'][$i];
        list($m,$d,$y)= explode('/',$cells['H'][$i]);
        $dne= "$d.$m.$y";
        $do_dne= "31.12.$y";
        $note= $cells['I'][$i];
//                                                 display("dne=$dne,platba=$platba");
//                                                 break;
        $qry= "SELECT r.nazev,
               GROUP_CONCAT(DISTINCT IF(t.role='a',o.jmeno,'')    SEPARATOR '') as jmeno_m,
               GROUP_CONCAT(DISTINCT IF(t.role='a',o.id_osoba,'') SEPARATOR '') as id_m,
               GROUP_CONCAT(DISTINCT IF(t.role='b',o.jmeno,'')    SEPARATOR '') as jmeno_z,
               GROUP_CONCAT(DISTINCT IF(t.role='b',o.id_osoba,'') SEPARATOR '') as id_z
               FROM osoba AS o
               JOIN tvori AS t USING(id_osoba)
               JOIN rodina AS r USING(id_rodina)
               GROUP BY id_rodina
               HAVING CONCAT(nazev,' ',jmeno_m,' a ',jmeno_z)='$par' ";
        $res= mysql_qry($qry);
        if ( $res ) {
          $n= mysql_num_rows($res);
          if ( $n==0 ) {
            $html.= "<br>{$par} nenalezen";
          }
          elseif ( $n>1 ) {
            $html.= "<br>{$par} je nejednoznačný";
          }
          elseif ( $p= mysql_fetch_object($res) ) {
            $id_m= $p->id_m;
            $id_z= $p->id_z;
            $clen= $vps ? 'c' : 'b';
            $qryo= "SELECT o.id_osoba
               FROM osoba AS o
               JOIN tvori AS t ON t.id_osoba=o.id_osoba AND id_rodina=1
               WHERE o.id_osoba='$id_m' ";
            $reso= mysql_qry($qryo);
            if ( $reso && !mysql_num_rows($reso) ) {
              // přidej mezi členy
              $qryu= "INSERT dar(id_osoba,ukon,dat_od,note) VALUES
                      ('$id_m','$clen','2011-01-01','$note'),('$id_z','$clen','2011-01-01','$note')";
              $resu= mysql_qry($qryu);
//               $html.= "<br>{$par} přidáni mezi členy";
              if ( $castka ) {
              // vlož platbu členského příspěvku
                $sql_dne= sql_date($dne,1);
                $sql_do_dne= sql_date($do_dne,1);
                $castka2= $castka/2;
                $qryu= "INSERT dar(id_osoba,ukon,castka,dat_od,dat_do,note) VALUES
                        ('$id_m','p',$castka2,'$sql_dne','$sql_do_dne','$note')
                       ,('$id_z','p',$castka2,'$sql_dne','$sql_do_dne','$note')";
                $resu= mysql_qry($qryu);
//                 $html.= "<br>{$par} příspěvek";
              }
//                                                 break;
            }
            else {
//             $html.= "<br>{$par} členy jsou";
//             $html.= "<br>{$par} mají {$p->_pocet} id_dupary={$p->id_dupary} $vps $platba $dne $pozn";
            }
          }
        }
      }
    }
  }
  return "Přečtena tabulka $wt.$ws jako A1:$max_A{$max_n}<br>$html";
}
# ------------------------------------------------------------------------------------- akce_roku_id
# definuj klíč dané akce jeko klíč akce z aplikace MS.EXE
function akce_roku_id($id_akce,$kod,$rok) {
  // smazání starých spojek
  $r1= mysql_qry("DELETE FROM join_akce WHERE g_rok=$rok AND g_kod=$kod ");
  // vložení nové
  $r2= mysql_qry("INSERT join_akce (id_akce,g_rok,g_kod) VALUES ('$id_akce',$rok,$kod) ");
  return "$r1,$r2";
}
// # ---------------------------------------------------------------------------------- akce_roku_id
// # definuj klíč dané akce jeko klíč akce z aplikace MS.EXE
// function akce_roku_id($kod,$rok,$source,$akce) {
//   if ( $akce ) {
//     mysql_qry("INSERT join_akce (source,akce,g_kod,g_rok) VALUES ('$source',$akce,$kod,$rok)");
//     mysql_qry("UPDATE ms_akce SET ciselnik_akce=$kod,ciselnik_rok=$rok WHERE source='$source' AND akce=$akce");
//   }
//   return 1;
// }
# --------------------------------------------------------------------------------- akce_roku_update
# přečtení listu $rok z tabulky ciselnik_akci a zapsání dat do tabulky
# načítají se jen řádky ve kterých typ='a'
function akce_roku_update($rok) {  trace();
  $n= 0;
  $cells= google_sheet($rok,"ciselnik_akci",'answer@smidek.eu',$google);
//                                                 debug($cells,"akce $rok");
  if ( $cells ) {
    list($max_A,$max_n)= $cells['dim'];
    // zrušení daného roku v GAKCE
    $qry= "DELETE FROM g_akce WHERE g_rok=$rok";
    $res= mysql_qry($qry);
    // výběr a-záznamů a zápis do GAKCE
    $values= ''; $del= '';
    for ($i= 1; $i<$max_n; $i++) {
      $kat= $cells['A'][$i];
      if ( strpos(' au',$kat) ) {
//       if ( strpos(' a',$kat) ) {
        $n++;
        $kod= $cells['B'][$i];
        $id= 1000*rok+$kod;
        $nazev= mysql_real_escape_string($cells['C'][$i]);
        // data akce - jen je-li syntax ok
        $od= $do= '';
        $x= $cells['D'][$i];
        if ( preg_match("/\d+\.\d+\.\d+/",$x) )
          $od= sql_date($x,1);
        $x= $cells['E'][$i];
        if ( preg_match("/\d+\.\d+\.\d+/",$x) )
          $do= sql_date($x,1);
        $uc= $cells['F'][$i];
        $typ= $cells['G'][$i];
        $kap= $cells['H'][$i];
        $values.= "$del($id,$rok,'$kod',\"$nazev\",'$od','$do','$uc','$typ','$kap','$kat')";
        $del= ',';
      }
    }
    $qry= "INSERT INTO g_akce (id_gakce,g_rok,g_kod,g_nazev,g_od,g_do,g_ucast,g_typ,g_kap,g_kat)
           VALUES $values";
    $res= mysql_qry($qry);
  }
  // konec
  return $n;
}
# ---------------------------------------------------------------------------------------- akce_mapa
# získání seznamu souřadnic bydlišť účastníků akce
function akce_mapa($akce) {  trace();
  global $ezer_root;
  // dotaz
  $marks= $del= ''; $n= 0;
  $qry=  "SELECT psc,lat,lng,count(*) AS _pocet,GROUP_CONCAT(o.prijmeni SEPARATOR ' ') AS _jmena,
            obec FROM pobyt AS p
          JOIN spolu AS s USING(id_pobyt)
          JOIN osoba AS o ON o.id_osoba=s.id_osoba
          JOIN uir_adr.psc_axy USING(psc)
          WHERE p.id_akce='$akce'
          GROUP BY psc";
  $res= mysql_qry($qry);
  while ( $res && ($s= mysql_fetch_object($res)) ) {
    $n++;
    $title= str_replace(',','',"{$s->obec}:{$s->_jmena}");
    $marks.= "$del{$s->lat},{$s->lng},$title"; $del= ';';
  }
  $ret= (object)array('mark'=>$marks,'n'=>$n);
//                                                 debug($ret,"mapa_akce");
  return $ret;
}
# ================================================================================================== ÚČASTNÍCI
# ------------------------------------------------------------------------------- akce_test_dite_kat
# testuje, zda je kategorie dítěte v souladu s rozmezím věku v číselníku
# narozeni=d.m.Y
function akce_test_dite_kat($kat,$narozeni,$id_akce) {  trace();
  $ret= (object)array('ok'=>0,'vek'=>0.0);
  $od_do= select1("ikona","_cis","druh='ms_akce_dite_kat' AND data=$kat");
  list($od,$do)= explode('-',$od_do);
  $akce_od= select1("datum_od","akce","id_duakce=$id_akce");
  $narozeni= sql_date1($narozeni,1);
  $date1 = new DateTime($narozeni);
  $date2 = new DateTime($akce_od);
  $diff= $date2->diff($date1,1);
  $x= $diff->y . " years, " . $diff->m." months, ".$diff->d." days ";
  $vek= $diff->y+($diff->m+$diff->d/30)/12;
//   $d= array($diff->y,$diff->m,$diff->d,$diff->days);
//                                               debug($d,"$vek: $x, narozen:$narozeni, akce:$akce_od");
  $ret->vek= round($vek,1);
  $ret->ok= $vek>=$od && $vek<$do ? 1 : 0;
  return $ret;
}
# -------------------------------------------------------------------------------- akce_strava_denne
# vrácení výjimek z providelné stravy jako pole
function akce_strava_denne($od,$dnu,$cela,$polo) {  #trace('');
  $dny= array('neděle','pondělí','úterý','středa','čtvrtek','pátek','sobota');
  $strava= array();
  $den0= sql2stamp($od);
  for ($i= 0; $i<3*$dnu; $i+= 3) {
    $t= $den0+($i/3)*60*60*24;
    $den= date('d.m.Y ',$t);
    $den.= $dny[date('w',$t)];
    $strava[]= (object)array(
      'den'=> $den,
      'sc' => substr($cela,$i+0,1),
      'sp' => substr($polo,$i+0,1),
      'oc' => substr($cela,$i+1,1),
      'op' => substr($polo,$i+1,1),
      'vc' => substr($cela,$i+2,1),
      'vp' => substr($polo,$i+2,1)
    );
  }
//                                                 debug($strava,"akce_strava_denne($od,$dnu,$cela,$polo) $den0");
  return $strava;
}
# --------------------------------------------------------------------------- akce_strava_denne_save
# zapsání výjimek z providelné stravy - pokud není výjimka zapíše prázdný string
#   $prvni - kód první stravy na akci
function akce_strava_denne_save($id_pobyt,$dnu,$cela,$cela_def,$cela_str,$polo,$polo_def,$polo_str,$prvni) {  #trace('');
  $cela_ruzna= $polo_ruzna= 0;
  $i0= $prvni=='s' ? 0 : ($prvni=='o' ? 1 : ($prvni=='v' ? 2 : 2));
  for ($i= $i0; $i<3*$dnu-1; $i++) {
    if ( substr($cela,$i,1)!=$cela_def ) $cela_ruzna= 1;
    if ( substr($polo,$i,1)!=$polo_def ) $polo_ruzna= 1;
  }
  if ( !$cela_ruzna ) $cela= '';
  if ( !$polo_ruzna ) $polo= '';
  // příprava update
  $set= '';
  if ( ";$cela"!=";$cela_str" ) $set.= "cstrava_cel='$cela'";           // ; jako ochrana pro pochopení jako čísla
  if ( ";$polo"!=";$polo_str" ) $set.= ($set?',':'')."cstrava_pol='$polo'";
  if ( $set ) {
    $qry= "UPDATE pobyt SET $set WHERE id_pobyt=$id_pobyt";
    $res= mysql_qry($qry);
  }
//                                                 display("akce_strava_denne_save(($id_pobyt,$dnu,$cela,$cela_def,$polo,$polo_def) $set");
  return 1;
}
# ================================================================================================== CHLAPI AKCE
# funkce pro kartu CHLAPI
# ------------------------------------------------------------------------------- chlapi_mrop_export
# export iniciovaných chlapů do Excelu
function chlapi_mrop_export($cond="iniciace!=''") {  #trace();
  global $ezer_path_docs;
  // zahájení exportu
  $ymd= date('Ymd');
  $dnes= date('j. n. Y');
  $t= "Iniciovaní chlapi, stav ke dni $dnes";
  $file= "mrop_$ymd";
  $type= 'xls';
  $par= (object)array('file'=>$file,'type'=>$type,'title'=>$t,'color'=>'aac0cae2');
  $clmns= "prijmeni:příjmení,jmeno:jméno,ulice,psc:psč,obec,iniciace";
  $titles= $fields= $del= '';
  foreach (explode(',',$clmns) as $clmn) {
    list($field,$title)= explode(':',trim($clmn));
    $title= $title ? $title : $field;
    $titles.= "$del$title";
    $fields.= "$del$field";
    $del= ',';
  }
  $pipe= array('narozeni'=>'sql_date1');
  export_head($par,$titles);
  $qry= "SELECT $fields FROM ezer_ys.chlapi WHERE $cond ";
  $res= mysql_qry($qry);
  // projití záznamů
  $values= array();
  while ( $res && $row= mysql_fetch_assoc($res) ) {
    foreach ($row as $f => $val) {
      $a= $val;
      if ( isset($pipe[$f]) ) $a= $pipe[$f]($a);
      $values[$f]= $a;
    }
    export_row($values);
  }
   export_tail();
//                                                 display(export_tail(1));
  // odkaz pro stáhnutí
  $ref= "seznam ve formátu <a href='docs/$file.$type'>Excel</a>";
  return $ref;
}
# ------------------------------------------------------------------------------- chlapi_spec_export
# export vybraných chlapů do Excelu jako kontaktní seznam
function chlapi_spec_export($cond) {  #trace();
  global $ezer_path_docs;
  // zahájení exportu
  $ymd= date('Ymd');
  $dnes= date('j. n. Y');
  $t= "stav ke dni $dnes";
  $file= "spec_$ymd";
  $type= 'xls';
  $par= (object)array('file'=>$file,'type'=>$type,'title'=>$t,'color'=>'aac0cae2');
  $clmns= "prijmeni:příjmení,jmeno:jméno,obec,ulice,telefon,email";
  $titles= $fields= $del= '';
  foreach (explode(',',$clmns) as $clmn) {
    list($field,$title)= explode(':',trim($clmn));
    $title= $title ? $title : $field;
    $titles.= "$del$title";
    $fields.= "$del$field";
    $del= ',';
  }
  $pipe= array('narozeni'=>'sql_date1');
  export_head($par,$titles);
  $qry= "SELECT $fields FROM ezer_ys.chlapi WHERE $cond ";
  $res= mysql_qry($qry);
  // projití záznamů
  $values= array();
  while ( $res && $row= mysql_fetch_assoc($res) ) {
    foreach ($row as $f => $val) {
      $a= $val;
      if ( isset($pipe[$f]) ) $a= $pipe[$f]($a);
      $values[$f]= $a;
    }
    export_row($values);
  }
   export_tail();
//                                                 display(export_tail(1));
  // odkaz pro stáhnutí
  $ref= "seznam ve formátu <a href='docs/$file.$type'>Excel</a>";
  return $ref;
}
# ------------------------------------------------------------------------------- chlapi_akce_export
# export účastníků akce do Excelu
function chlapi_akce_export($id_akce,$nazev) {  #trace();
  function narozeni2vs ($dat) {
    $vs= substr($dat,2,2).substr($dat,5,2).substr($dat,8,2);
    return $vs;
  }
  global $ezer_path_docs;
  // zahájení exportu
  $ymd= date('Ymd');
  $dnes= date('j. n. Y');
  $t= "$nazev, stav ke dni $dnes";
  $file= "akce_{$id_akce}_$ymd";
  $type= 'xls';
  $par= (object)array('file'=>$file,'type'=>$type,'title'=>$t,'color'=>'aac0cae2');
  $clmns= "prijmeni:příjmení,jmeno:jméno,narozeni:narození,ulice,psc:psč,obec,email,telefon,
           iniciace,c.note AS c_pozn:poznámka,u.pozn:... k akci,u.cena:cena,
           narozeni AS _vs:VS,u.avizo:avizo,u.uctem:účtem,u.pokladnou:pokladnou";
  $titles= $fields= $del= '';
  foreach (explode(',',$clmns) as $clmn) {
    list($field,$title)= explode(':',trim($clmn));
    $title= $title ? $title : $field;
    $titles.= "$del$title";
    $fields.= "$del$field";
    $del= ',';
  }
  $pipe= array('narozeni'=>'sql_date1','_vs'=>'narozeni2vs');
  export_head($par,$titles);
  $qry= "SELECT $fields
         FROM ezer_ys.ch_ucast AS u JOIN ezer_ys.chlapi AS c USING(id_chlapi) WHERE id_akce=$id_akce ";
  $res= mysql_qry($qry);
  // projití záznamů
  $values= array();
  while ( $res && $row= mysql_fetch_assoc($res) ) {
    foreach ($row as $f => $val) {
      $a= $val;
      if ( isset($pipe[$f]) ) $a= $pipe[$f]($a);
      $values[$f]= $a;
    }
    export_row($values);
//                                                         debug($values);
  }
   export_tail();
//                                                 display(export_tail(1));
  // odkaz pro stáhnutí
  $ref= "seznam ve formátu <a href='docs/$file.$type'>Excel</a>";
  return $ref;
}
# ------------------------------------------------------------------------------------ chlapi_delete
# bezpečné smazání chlapa s kontrolou, zda není zařazen v nějaké akci
function chlapi_delete($id_chlapi) {  #trace();
  $ans= '';
  $qry= "SELECT GROUP_CONCAT(nazev) AS _a, prijmeni, jmeno
         FROM ezer_ys.ch_ucast
         JOIN ezer_ys.ch_akce USING(id_akce)
         JOIN ezer_ys.chlapi USING(id_chlapi)
         WHERE id_chlapi='$id_chlapi' GROUP BY id_chlapi ";
  $res= mysql_qry($qry);
  if ( $res && $a= mysql_fetch_object($res) ) {
    $ans= $a->_a
      ? "Nelze smazat, protože '{$a->prijmeni} {$a->jmeno}' se se zúčastní těchto akcí: {$a->_a}"
      : '';
  }
  if ( $res && !$ans ) {
    // vymaž ho
    global $USER;
    $dnes= date('Y-m-d');
    $zmeny= array((object)array('fld'=>'deleted','op'=>'u','val'=>"D {$USER->abbr} $dnes"));
    $ok= ezer_qry("UPDATE",'ezer_ys.chlapi',$id_chlapi,$zmeny,'id_chlapi');
  }
  return $ans;
}
# ------------------------------------------------------------------------------ chlapi_akce_prehled
# souhrn akce
function chlapi_akce_prehled($id_akce) {  #trace();
  $html= '';
  $tab= '';
  $n= 0;
  // základní údaje
  $qry= "SELECT * FROM ezer_ys.ch_akce WHERE id_akce='$id_akce' ";
  $res= mysql_qry($qry);
  $x= mysql_fetch_object($res);
  $od= sql_date1($x->datum_od);
  $do= sql_date1($x->datum_do);
  $html.= "<h3>{$x->nazev}, $od - $do</h3>";
  // účastníci
  $qry= "SELECT zkratka AS _x, count(*) AS _n FROM ezer_ys.ch_ucast JOIN ezer_ys.chlapi USING(id_chlapi)
         JOIN _cis ON druh='akce_ucast' AND data=stupen WHERE id_akce='$id_akce' GROUP BY stupen";
  $res= mysql_qry($qry);
  while ( $res && $a= mysql_fetch_object($res) ) {
    $n+= $a->_n;
    $tab.= "<tr><td>{$a->_x}</td><td align='right'>{$a->_n}</td></tr>";
  }
  $html.= "<b>Celkem $n účastníků, z toho:</b>";
  $html.= "<br/><br/><table>$tab</table>";
  // cena
  $qry= "SELECT sum(cena) AS c, sum(IF(avizo,cena,0)) AS a, sum(uctem) AS u, sum(pokladnou) AS p
         FROM ezer_ys.ch_ucast WHERE id_akce='$id_akce' ";
  $res= mysql_qry($qry);
  $c= mysql_fetch_object($res);
  $html.= "<br/><b>Cena akce pro účastníky: {$x->cena}</b>";
  $html.= "<br/><br/><table>";
  $html.= "<tr><td>celkem předepsaná cena:</td><td align='right'>{$c->c}</td></tr>";
  $html.= "<tr><td>přišlo avízo platby:</td><td align='right'>{$c->a}</td></tr>";
  $html.= "<tr><td>zatím zaplaceno účtem:</td><td align='right'>{$c->u}</td></tr>";
  $html.= "<tr><td>zatím zaplaceno pokladnou:</td><td align='right'>{$c->p}</td></tr>";
  $html.= "</table>";
  return $html;
}
# --------------------------------------------------------------------------------- chlapi_akce_mapa
# získání seznamu souřadnic bydlišť účastníků akce
# nebo iniciovaných chlapů
function chlapi_akce_mapa($id_akce) {  trace();
  global $ezer_root;
  // dotaz
  $FROM= $id_akce
    ? "FROM ezer_ys.ch_ucast JOIN ezer_ys.chlapi USING(id_chlapi)"
    : "FROM ezer_ys.chlapi";
  $WHERE= $id_akce
    ? "WHERE id_akce=$id_akce "
    : "WHERE iniciace!='' ";
  $marks= $del= ''; $n= 0;
  $qry=  "SELECT psc,lat,lng,count(*) AS _pocet,GROUP_CONCAT(prijmeni SEPARATOR ' ') AS _jmena,obec
          $FROM
          JOIN uir_adr.psc_axy USING(psc)
          $WHERE
          GROUP BY psc";
  $res= mysql_qry($qry);
  while ( $res && ($s= mysql_fetch_object($res)) ) {
    $n++;
    $title= str_replace(',','',"{$s->obec}:{$s->_jmena}");
    $marks.= "$del{$s->lat},{$s->lng},$title"; $del= ';';
  }
  $ret= (object)array('mark'=>$marks,'n'=>$n);
//                                                 debug($ret,"mapa_akce");
  return $ret;
}
# ============================================================================= PRIDEJ JMENEM CHLAPA
# funkce pro spolupráci se select
# -------------------------------------------------------------------------------- chlapi_auto_jmena
# kontrola, zda chlap ještě na akci není
function chlapi_pridej($id_akce,$cena,$a) {
//                                                         debug($a,"chlapi_pridej($id_akce,$cena,...");
  $ret= (object)array('ok'=>0,'err'=>'');
  $id_chlapi= 0;
  $chlap= "{$a->jmeno} {$a->prijmeni}";
  if ( $a->_db=='chlapi' ) {
    $id_chlapi= $a->id_chlapi;
    $qry= "SELECT id_ucast FROM ezer_ys.ch_ucast JOIN ezer_ys.chlapi USING(id_chlapi)
           WHERE id_akce='$id_akce' AND id_chlapi=$id_chlapi ";
    $res= mysql_qry($qry);
    if ( mysql_num_rows($res) ) { $ret->err= "'$chlap' už je přihlášen"; goto end; }
  }
  else {
    // zkontroluj jednoznačnost jména
    $cond= "prijmeni='{$a->prijmeni}' AND jmeno='{$a->jmeno}' ";
    $qry= "SELECT id_chlapi FROM ezer_ys.chlapi
           WHERE prijmeni='{$a->prijmeni}' AND jmeno='{$a->jmeno}' ";
    $res= mysql_qry($qry);
    if ( mysql_num_rows($res) ) { $ret->err= "'$chlap' už je v databázi Chlapi"; goto end; }
    // zkopíruj údaje
    $origin= "{$a->_db},{$a->_id},".date("Y-m-d");
    $qc= "INSERT INTO ezer_ys.chlapi (prijmeni,jmeno,sex,narozeni,rc_xxxx,psc,
                              obec,ulice,email,telefon,pozn,origin)
          VALUE ('$a->prijmeni','$a->jmeno','$a->sex','$a->narozeni','$a->rc_xxxx','$a->psc',
                 '$a->obec','$a->ulice','$a->email','$a->telefon','$a->pozn','$origin') ";
    $rc= mysql_qry($qc);
    if ( !$rc ) { $ret->err= "'$chlap' nejde zkopírovat"; goto end; }
    $id_chlapi= mysql_insert_id();
  }
  // zapoj do akce
  $qu= "INSERT INTO ch_ucast (id_akce,id_chlapi,stupen,cena) VALUE ($id_akce,$id_chlapi,1,$cena) ";
  $ru= mysql_qry($qu);
  $ret->id_ucast= mysql_insert_id();
  $ret->ok= 1;
end:
  return $ret;
}
# -------------------------------------------------------------------------------- chlapi_auto_jmena
# kontrola, zda chlap ještě na akci není
function chlapi_auto_not_yet($id_akce,$id_chlapi) {
  $qry= "SELECT count(*) AS _pocet
         FROM ch_ucast
         WHERE id_akce='$id_akce' AND id_chlapi='$id_chlapi' ";
  $res= mysql_qry($qry);
  $t= mysql_fetch_object($res);
  return $t->_pocet ? 0 : 1;
}
# -------------------------------------------------------------------------------- chlapi_auto_jmena
# SELECT autocomplete - výběr z akcí
function chlapi_auto_jmena($patt,$par) {  #trace();
  $a= array();
  $limit= 20;
  $db= $par->db ? $par->db : '';
  $n= 0;
  // dotaz podle databáze
  switch($db) {
  case 'chlapi':
    $qry= "SELECT id_chlapi AS _key,CONCAT(prijmeni,' ',jmeno) AS _value
           FROM ezer_ys.chlapi WHERE prijmeni LIKE '$patt%' ORDER BY prijmeni,jmeno LIMIT $limit";
    break;
  case 'ezer_ys':
  case 'ezer_fa':
    $qry= "SELECT id_osoba AS _key,CONCAT(prijmeni,' ',jmeno) AS _value
           FROM $db.osoba WHERE sex=1 AND prijmeni LIKE '$patt%'
           ORDER BY prijmeni,jmeno LIMIT $limit";
    break;
  }
  $res= mysql_qry($qry);
  while ( $res && $t= mysql_fetch_object($res) ) {
    if ( ++$n==$limit ) break;
    $key= $t->_key;
    $a[$key]= $t->_value;
  }
  // obecné položky
  if ( !$n )
    $a[0]= "... žádné jméno nezačíná '$patt'";
  elseif ( $n==$limit )
    $a[-999999]= "... a další";
//                                                                 debug($a,$patt);
  return $a;
}
# ----------------------------------------------------------------------------- chlapi_auto_jmenovci
# formátování autocomplete
function chlapi_auto_jmenovci($id,$db) {  #trace();
  $a= array();
  // dotaz podle databáze
  switch($db) {
  case 'chlapi':
    $qry= "SELECT *,id_chlapi AS _id FROM ezer_ys.chlapi WHERE id_chlapi=$id ";
    break;
  case 'ezer_ys':
  case 'ezer_fa':
    $qry= "SELECT prijmeni,jmeno,sex,narozeni,rc_xxxx,psc,obec,ulice,email,telefon,
             note AS pozn,id_osoba AS _id FROM $db.osoba WHERE id_osoba=$id ";
    break;
  }
  $res= mysql_qry($qry);
  while ( $res && $p= mysql_fetch_object($res) ) {
    $a[0]= (array)$p;
    $a[0]['_nazev']= "{$p->prijmeni} {$p->jmeno}, {$p->obec}, {$p->email} ({$p->_id})";
//     $a[]= (object)array('id_chlapi'=>$p->_id,'nazev'=>$nazev);
  }
  $a[0]['_db']= $db;
//                                                                 debug($a,$id_chlapi);
  return $a;
}
# ================================================================================================== PRIDEJ
# funkce pro spolupráci se select
# --------------------------------------------------------------------------------- akce_auto_jmena2
# ASK přidání pobytu do akce, pokud ještě nebyly tyto osoby/osoba přidány
function akce_pridej($id_akce,$id_muz,$id_zena,$cnd='',$note='') { trace();
  $ret= (object)array('ok'=>0,'msg'=>'chyba při vkládání');
  // zjištění, kdo se přihlašuje na akci
  if ( $id_muz && $id_zena ) {
    $pouze= 0;
    $cond=  "s.id_osoba IN ('$id_muz','$id_zena')";
  }
  elseif ( $id_muz ) {
    $pouze= 1;
    $cond=  "s.id_osoba=$id_muz";
  }
  elseif ( $id_zena ) {
    $pouze= 2;
    $cond=  "s.id_osoba=$id_zena";
  }
  else {
    $ret->msg= "dítě zatím nemůže jet bez rodiče: $note";
    goto end;
  }
  $cond.= $cnd ? " AND $cnd" : '';
  // kontrola přítomnosti
  $qp= "SELECT id_pobyt
        FROM pobyt AS p
        JOIN spolu AS s USING(id_pobyt)
        WHERE id_akce=$id_akce AND $cond";
  $rp= mysql_qry($qp);
  $jsou= mysql_num_rows($rp);
  if ( $jsou==2 || $jsou==1 && $pouze==0 ) {
    // už tam jsou oba
    $ret->msg= "... již jsou mezi účastníky akce";
  }
  elseif ( $jsou==1 && $pouze>0 ) {
    // už tam je
    $ret->msg= "... již je mezi účastníky akce";
  }
  else {
    // vložení nového pobytu
    $qi= "INSERT pobyt (id_akce,pouze) VALUES ($id_akce,$pouze)";
    $ri= mysql_qry($qi); if ( !$ri ) goto end;
    $ret->pobyt= mysql_insert_id();
    if ( $id_muz ) {
      // vložení muže
      $qi= "INSERT spolu (id_pobyt,id_osoba) VALUES ({$ret->pobyt},$id_muz)";
      $ri= mysql_qry($qi); if ( !$ri ) goto end;
    }
    if ( $id_zena ) {
      // vložení ženy
      $qi= "INSERT spolu (id_pobyt,id_osoba) VALUES ({$ret->pobyt},$id_zena)";
      $ri= mysql_qry($qi); if ( !$ri ) goto end;
    }
    $ret->ok= 1;
    $ret->msg= "... vloženo";
  }
end:
//                                                         debug($ret,"akce_pridej");
  return $ret;
}
# --------------------------------------------------------------------------------- akce_auto_jmena2
# SELECT autocomplete - výběr z párů
function akce_auto_jmena2($patt) {  #trace();
  $a= array();
  $limit= 20;
  $n= 0;
  $is= strpos($patt,' ');
  $patt= $is ? substr($patt,0,$is) : $patt;
  // páry
  $qry= "SELECT nazev, r.obec, id_rodina AS _key,
           GROUP_CONCAT(DISTINCT IF(t.role='a',o.jmeno,'') SEPARATOR '') AS _muz,
           GROUP_CONCAT(DISTINCT IF(t.role='b',o.jmeno,'') SEPARATOR '') AS _zena
         FROM rodina AS r
         JOIN tvori AS t USING(id_rodina)
         JOIN osoba AS o USING(id_osoba)
         WHERE nazev LIKE '$patt%'
         GROUP BY id_rodina HAVING _muz!='' AND _zena!=''
         ORDER BY nazev,_muz,_zena
         LIMIT $limit";
  $res= mysql_qry($qry);
  while ( $res && $t= mysql_fetch_object($res) ) {
    if ( ++$n==$limit ) break;
    $key= $t->_key;
    $a[$key]= "{$t->nazev} {$t->_muz} a {$t->_zena}";
  }
  // obecné položky
  if ( !$n )
    $a[0]= "... žádné jméno nezačíná '$patt'";
  elseif ( $n==$limit )
    $a[-999999]= "... a další";
//                                                                 debug($a,$patt);
  return $a;
}
# --------------------------------------- akce_auto_jmena2L
# formátování autocomplete
function akce_auto_jmena2L($id_rodina) {  #trace();
  $pary= array();
  // páry
  $qry= "SELECT nazev,
           IFNULL(r.nazev,o.prijmeni) as _nazev,
           GROUP_CONCAT(DISTINCT IF(t.role='a',o.jmeno,'')    SEPARATOR '') AS _muz,
           GROUP_CONCAT(DISTINCT IF(t.role='a',o.prijmeni,'') SEPARATOR '') AS _muzp,
           GROUP_CONCAT(DISTINCT IF(t.role='a',o.id_osoba,'') SEPARATOR '') AS _muz_id,
           GROUP_CONCAT(DISTINCT IF(t.role='b',o.jmeno,'')    SEPARATOR '') AS _zena,
           GROUP_CONCAT(DISTINCT IF(t.role='b',o.prijmeni,'') SEPARATOR '') AS _zenap,
           GROUP_CONCAT(DISTINCT IF(t.role='b',o.id_osoba,'') SEPARATOR '') AS _zena_id,
           r.obec
         FROM rodina AS r
         LEFT JOIN tvori AS t USING(id_rodina)
         LEFT JOIN osoba AS o USING(id_osoba)
         WHERE id_rodina='$id_rodina'
	 GROUP BY id_rodina ORDER BY nazev";
  $res= mysql_qry($qry);
  while ( $res && $p= mysql_fetch_object($res) ) {
    $nazev= $p->_muz && $p->_zena
      ? "{$p->_nazev} {$p->_muz} a {$p->_zena}"
      : ( $p->_muz ? "{$p->_muzp} {$p->_muz}" : "{$p->_zenap} {$p->_zena}" );
    $nazev.= ", {$p->obec}";
    $pary[]= (object)array('nazev'=>$nazev,'muz'=>$p->_muz_id,'zen'=>$p->_zena_id);
  }
//                                                                 debug($pary,$id_akce);
  return $pary;
}
# --------------------------------------------------------------------------------- akce_auto_jmena1
# SELECT autocomplete - výběr z dospělých jednotlivců
function akce_auto_jmena1($patt,$par) {  #trace();
  $a= array();
  $limit= 20;
  $n= 0;
  if ( $par->patt!='whole' ) {
    $is= strpos($patt,' ');
    $patt= $is ? substr($patt,0,$is) : $patt;
  }
  // páry
  $qry= "SELECT prijmeni, jmeno, id_osoba AS _key
         FROM osoba
         LEFT JOIN tvori USING(id_osoba)
         WHERE concat(trim(prijmeni),' ',jmeno) LIKE '$patt%' AND prijmeni!=''
           AND (ISNULL(role) OR role!='d')
         ORDER BY prijmeni,jmeno LIMIT $limit";
  $res= mysql_qry($qry);
  while ( $res && $t= mysql_fetch_object($res) ) {
    if ( ++$n==$limit ) break;
    $key= $t->_key;
    $a[$key]= "{$t->prijmeni} {$t->jmeno}";
  }
  // obecné položky
  if ( !$n )
    $a[0]= "... žádné příjmení nezačíná '$patt'";
  elseif ( $n==$limit )
    $a[-999999]= "... a další";
//                                                                 debug($a,$patt);
  return $a;
}
# --------------------------------------- akce_auto_jmena1L
# formátování autocomplete
function akce_auto_jmena1L($id_osoba) {  #trace();
  $pary= array();
  // páry
  $qry= "SELECT prijmeni, jmeno, id_osoba, o.obec, o.ulice, telefon, email, YEAR(narozeni) AS rok,
           r.obec AS r_obec, r.ulice AS r_ulice,
           GROUP_CONCAT(DISTINCT IF(t.role='a',o.jmeno,'')    SEPARATOR '') AS _muz,
           GROUP_CONCAT(DISTINCT IF(t.role='a',o.prijmeni,'') SEPARATOR '') AS _muzp,
           GROUP_CONCAT(DISTINCT IF(t.role='a',o.id_osoba,'') SEPARATOR '') AS _muz_id,
           GROUP_CONCAT(DISTINCT IF(t.role='b',o.jmeno,'')    SEPARATOR '') AS _zena,
           GROUP_CONCAT(DISTINCT IF(t.role='b',o.prijmeni,'') SEPARATOR '') AS _zenap,
           GROUP_CONCAT(DISTINCT IF(t.role='b',o.id_osoba,'') SEPARATOR '') AS _zena_id
         FROM osoba AS o
         LEFT JOIN tvori AS t USING(id_osoba)
         LEFT JOIN rodina AS r USING(id_rodina)
         WHERE id_osoba='$id_osoba'
         GROUP BY id_rodina";
  $res= mysql_qry($qry);
  while ( $res && $p= mysql_fetch_object($res) ) {
    $nazev= "{$p->prijmeni} {$p->jmeno} / {$p->rok}";
    $nazev.= $p->obec ? ", {$p->obec}" : ", {$p->r_obec}";
    $nazev.= $p->ulice ? ", {$p->ulice}" : ", {$p->r_ulice}";
    $nazev.= $p->email ? ", {$p->email}" : '';
    $nazev.= $p->telefon ? ", {$p->telefon}" : '';
    $pary[]= (object)array('nazev'=>$nazev,'muz'=>$p->_muz_id,'zen'=>$p->_zena_id,'id'=>$id_osoba);
  }
                                                                debug($pary,$id_akce);
  return $pary;
}
# ----------------------------------------------------------------------------------- akce_auto_deti
# SELECT autocomplete - výběr z dětí na akci=par->akce
function akce_auto_deti($patt,$par) {  #trace();
//                                                                 debug($par,$patt);
  $a= array();
  $limit= 20;
  $n= 0;
  if ( $par->patt!='whole' ) {
    $is= strpos($patt,' ');
    $patt= $is ? substr($patt,0,$is) : $patt;
  }
  // děti na akci
  $qry= "SELECT prijmeni, jmeno, s.id_osoba AS _key
         FROM osoba AS o
         JOIN spolu AS s USING(id_osoba)
         JOIN pobyt AS p USING(id_pobyt)
         LEFT JOIN tvori AS t ON s.id_osoba=t.id_osoba
         WHERE prijmeni LIKE '$patt%' AND role='d' AND id_akce='{$par->akce}'
         ORDER BY prijmeni,jmeno LIMIT $limit";
  $res= mysql_qry($qry);
  while ( $res && $t= mysql_fetch_object($res) ) {
    if ( ++$n==$limit ) break;
    $key= $t->_key;
    $a[$key]= "{$t->prijmeni} {$t->jmeno}";
  }
  // obecné položky
  if ( !$n )
    $a[0]= "... žádné příjmení nezačíná '$patt'";
  elseif ( $n==$limit )
    $a[-999999]= "... a další";
//                                                                 debug($a,$patt);
  return $a;
}
# --------------------------------------------------------------------------------- akce_auto_jmena3
# SELECT autocomplete - výběr z pečounů
# $par->cond může obsahovat dodatečnou podmínku např. 'funkce=99' pro zúžení na pečouny
function akce_auto_jmena3($patt,$par) {  #trace();
  $a= array();
  $limit= 20;
  $n= 0;
  if ( $par->patt!='whole' ) {
    $is= strpos($patt,' ');
    $patt= $is ? substr($patt,0,$is) : $patt;
  }
  $AND= $par->cond ? "AND {$par->cond}" : '';
  // páry
  $qry= "SELECT prijmeni, jmeno, osoba.id_osoba AS _key
         FROM osoba
         JOIN spolu USING(id_osoba)
         JOIN pobyt USING(id_pobyt)
         WHERE concat(trim(prijmeni),' ',jmeno) LIKE '$patt%' AND prijmeni!='' $AND
         GROUP BY id_osoba
         ORDER BY prijmeni,jmeno LIMIT $limit";
  $res= mysql_qry($qry);
  while ( $res && $t= mysql_fetch_object($res) ) {
    if ( ++$n==$limit ) break;
    $key= $t->_key;
    $a[$key]= "{$t->prijmeni} {$t->jmeno}";
  }
  // obecné položky
  if ( !$n )
    $a[0]= "... žádné příjmení nezačíná '$patt'";
  elseif ( $n==$limit )
    $a[-999999]= "... a další";
//                                                                 debug($a,$qry);
  return $a;
}
# --------------------------------------- akce_auto_jmena3L
# formátování autocomplete
function akce_auto_jmena3L($id_osoba) {  #trace();
  $pecouni= array();
  // páry
  $qry= "SELECT id_osoba, prijmeni, jmeno, obec, email, telefon, YEAR(narozeni) AS rok
         FROM osoba AS o
         WHERE id_osoba='$id_osoba' ";
  $res= mysql_qry($qry);
  while ( $res && $p= mysql_fetch_object($res) ) {
    $nazev= "{$p->prijmeni} {$p->jmeno} / {$p->rok}, {$p->obec}, {$p->email}, {$p->telefon}";
    $pecouni[]= (object)array('nazev'=>$nazev,'id'=>$p->id_osoba);
  }
//                                                                 debug($pecouni,$id_akce);
  return $pecouni;
}
# ==================================================================================== PRIDEJ z AKCE
# ----------------------------------------------------------------------------------- akce_auto_akce
# SELECT autocomplete - výběr z akcí
function akce_auto_akce($patt) {  #trace();
  $a= array();
  $limit= 20;
  $patt= substr($patt,-7,2)==' (' && substr($patt,-1)==')' ? substr($patt,0,-7) : $patt;
  $n= 0;
  // výběr akce
  $qry= "SELECT id_duakce AS _key,concat(nazev,' (',YEAR(datum_od),')') AS _value
         FROM akce
         WHERE nazev LIKE '$patt%'
         ORDER BY datum_od DESC LIMIT $limit";
  $res= mysql_qry($qry);
  while ( $res && $t= mysql_fetch_object($res) ) {
    if ( ++$n==$limit ) break;
    $key= $t->_key;
    $a[$key]= $t->_value;
  }
  // obecné položky
  if ( !$n )
    $a[0]= "... žádná jméno akce nezačíná '$patt'";
  elseif ( $n==$limit )
    $a[-999999]= "... a další";
//                                                                 debug($a,$qry);
  return $a;
}
# ---------------------------------------- akce_auto_akceL
# formátování autocomplete
function akce_auto_akceL($id_akce) {  #trace();
  $pary= array();
  // páry na akci
  $qry= "SELECT
           IFNULL(r.nazev,o.prijmeni) as _nazev,
           GROUP_CONCAT(DISTINCT IF(tx.role='a',ox.jmeno,'')    SEPARATOR '') AS _muz,
           GROUP_CONCAT(DISTINCT IF(tx.role='a',ox.prijmeni,'') SEPARATOR '') AS _muzp,
           GROUP_CONCAT(DISTINCT IF(tx.role='a',ox.id_osoba,'') SEPARATOR '') AS _muz_id,
           GROUP_CONCAT(DISTINCT IF(tx.role='b',ox.jmeno,'')    SEPARATOR '') AS _zena,
           GROUP_CONCAT(DISTINCT IF(tx.role='b',ox.prijmeni,'') SEPARATOR '') AS _zenap,
           GROUP_CONCAT(DISTINCT IF(tx.role='b',ox.id_osoba,'') SEPARATOR '') AS _zena_id,
           r.obec
         FROM pobyt AS p
         JOIN spolu AS s ON p.id_pobyt=s.id_pobyt
         JOIN osoba AS o ON s.id_osoba=o.id_osoba
         LEFT JOIN tvori AS t ON t.id_osoba=o.id_osoba AND t.role!='d'
         LEFT JOIN rodina AS r USING(id_rodina)
         LEFT JOIN tvori AS tx ON r.id_rodina=tx.id_rodina
         LEFT JOIN osoba AS ox ON tx.id_osoba=ox.id_osoba
         WHERE id_akce=$id_akce
	 GROUP BY p.id_pobyt ORDER BY _nazev";
  $res= mysql_qry($qry);
  while ( $res && $p= mysql_fetch_object($res) ) {
    $nazev= $p->_muz && $p->_zena
      ? "{$p->_nazev} {$p->_muz} a {$p->_zena}"
      : ( $p->_muz ? "{$p->_muzp} {$p->_muz}" : "{$p->_zenap} {$p->_zena}" );
    $pary[]= (object)array('nazev'=>$nazev,'muz'=>$p->_muz_id,'zen'=>$p->_zena_id);
  }
//                                                                 debug($pary,$id_akce);
  return $pary;
}
# ----------------------------------------------------------------------------------- akce_auto_pece
# SELECT autocomplete - výběr z akcí na kterých byli pečouni
function akce_auto_pece($patt) {  #trace();
  $a= array();
  $limit= 20;
  $patt= substr($patt,-7,2)==' (' && substr($patt,-1)==')' ? substr($patt,0,-7) : $patt;
  $n= 0;
  // výběr akce
  $qry= "SELECT id_duakce AS _key,concat(nazev,' (',YEAR(datum_od),')') AS _value
         FROM akce
         JOIN pobyt ON akce.id_duakce=pobyt.id_akce
         WHERE nazev LIKE '$patt%' AND funkce=99
         ORDER BY datum_od DESC LIMIT $limit";
  $res= mysql_qry($qry);
  while ( $res && $t= mysql_fetch_object($res) ) {
    if ( ++$n==$limit ) break;
    $key= $t->_key;
    $a[$key]= $t->_value;
  }
  // obecné položky
  if ( !$n )
    $a[0]= "... žádná jméno akce nezačíná '$patt'";
  elseif ( $n==$limit )
    $a[-999999]= "... a další";
//                                                                 debug($a,$qry);
  return $a;
}
# ---------------------------------------- akce_pece_akceL
# formátování výběru pečounů dané akce
function akce_auto_peceL($id_akce) {  #trace();
  $pecouni= array();
  // páry na akci
  $qry= "SELECT o.id_osoba,jmeno,prijmeni,obec, YEAR(narozeni) AS rok, email, telefon
         FROM pobyt AS p
         JOIN spolu AS s ON p.id_pobyt=s.id_pobyt
         JOIN osoba AS o ON s.id_osoba=o.id_osoba
         WHERE id_akce=$id_akce AND p.funkce=99
	 ORDER BY prijmeni,jmeno";
  $res= mysql_qry($qry);
  while ( $res && $p= mysql_fetch_object($res) ) {
    $nazev= "{$p->prijmeni} {$p->jmeno} / {$p->rok}, {$p->obec}, {$p->email}, {$p->telefon}";
    $pecouni[]= (object)array('nazev'=>$nazev,'id'=>$p->id_osoba);
  }
//                                                                 debug($pecouni,$id_akce);
  return $pecouni;
}
# ================================================================================================== PLATBY
# záložka Platba za akci
# --------------------------------------------------------------------------- akce_platba_prispevek1
# členské příspěvky - zjištění zda jsou dospělí co jsou na pobytu členy a mají-li zaplaceno
function akce_platba_prispevek1($id_pobyt) {  trace();
  $ret= (object)array('msg'=>'nejsou členy','platit'=>0);
  // jsou členy?
  $cleni= 0;
  $qp= "SELECT COUNT(*) AS _jsou, GROUP_CONCAT(jmeno) AS _jmena
        FROM pobyt AS p
        JOIN spolu AS s USING(id_pobyt)
        JOIN osoba AS o ON o.id_osoba=s.id_osoba
        LEFT JOIN dar AS d ON d.id_osoba=o.id_osoba
        WHERE id_pobyt=$id_pobyt AND ukon='c'
        GROUP BY id_pobyt ";
  $rp= mysql_qry($qp);
  if ( $rp && $p= mysql_fetch_object($rp) ) {
    $cleni= $p->_jsou;
    $ret->platit= 1;
    $ret->msg= $p->_jmena." jsou členy ";
  }
  if ( $cleni ) {
    $qp= "SELECT COUNT(*) AS _maji, MAX(dat_do) AS _do
          FROM pobyt AS p
          JOIN spolu AS s USING(id_pobyt)
          JOIN osoba AS o ON o.id_osoba=s.id_osoba
          LEFT JOIN dar AS d ON d.id_osoba=o.id_osoba
          WHERE id_pobyt=$id_pobyt AND ukon='p' AND YEAR(dat_do)>=YEAR(NOW()) ";
    $rp= mysql_qry($qp);
    if ( $rp && $p= mysql_fetch_object($rp)) {
      if ( $p->_maji ) {
        $ret->platit= 0;
        $ret->msg.= "a mají zaplaceno do ".sql_date1($p->_do);
      }
      else
        $ret->msg.= "a nemají letos zaplaceno";
    }
  }
  return $ret;
}
# ---------------------------------------------------------------------------- akce_platba_prispevek
# členské příspěvky vložení platby do dar
function akce_platba_prispevek2($id_pobyt) {  trace();
  $ret= (object)array('msg'=>'');
  $osoby= array();
  $prispevek= 100;
  $nazev= $rok= '';
  $values= $del= $jmena= '';
  $celkem= 0;
  $qp= "SELECT o.id_osoba,a.nazev,datum_do,YEAR(datum_do) AS _rok,jmeno
          FROM pobyt AS p
          JOIN akce AS a ON a.id_duakce=p.id_akce
          JOIN spolu AS s ON p.id_pobyt=s.id_pobyt
          JOIN osoba AS o ON s.id_osoba=o.id_osoba
          LEFT JOIN tvori AS t ON t.id_osoba=o.id_osoba
          WHERE p.id_pobyt=$id_pobyt AND t.role IN ('a','b') ";
  $rp= mysql_qry($qp);
  while ( $rp && $p= mysql_fetch_object($rp) ) {
    $osoba= $p->id_osoba;
    $rok= $p->_rok;
    $datum= $p->datum_do;
    $nazev= "$rok - {$p->nazev}";
    $values.= "$del($osoba,'p',$prispevek,'$datum','$rok-12-31','$nazev')";
    $jmena.= "$del{$p->jmeno}";
    $del= ', ';
    $celkem+= $prispevek;
  }
//                                                         display($values);
  $qi= "INSERT dar (id_osoba,ukon,castka,dat_od,dat_do,note) VALUES $values";
  $ri= mysql_qry($qi);
  // odpověď
  $ret->msg= "Za členy $jmena je potřeba vložit do pokladny $celkem,- Kč";
  return $ret;
}
# ================================================================================================== INFORMACE
# výpisy informací o akci
# -------------------------------------------------------------------------------- akce_select_cenik
# seznam akcí s ceníkem pro select
function akce_select_cenik($id_akce) {  trace();
  $max_nazev= 20;
  mb_internal_encoding('UTF-8');
  $options= 'neměnit:0';
  if ( $id_akce ) {
    $qa= "SELECT id_duakce, nazev, YEAR(datum_od) AS _rok FROM akce
          WHERE id_duakce!=$id_akce AND ma_cenik>0 ORDER BY datum_od DESC";
    $ra= mysql_qry($qa);
    while ($ra && $a= mysql_fetch_object($ra) ) {
      $nazev= strtr($a->nazev,array(','=>' ',':'=>' '));
      if ( mb_strlen($nazev) >= $max_nazev )
        $nazev= mb_substr($nazev,0,$max_nazev-3).'...';
      $nazev.= "/{$a->_rok}";
      $options.= ",$nazev:{$a->id_duakce}";
    }
  }
  return $options;
}
# -------------------------------------------------------------------------------- akce_change_cenik
# změnit ceník akce za vybraný
function akce_change_cenik($id_akce,$id_akce_vzor) {  trace();
  $err= '';
  if ( !$id_akce || !$id_akce_vzor ) { $err= "chybné použití změny - ceník nezměněn"; goto end; }
  // výmaz položek v ceníku
  $qa= "DELETE FROM cenik WHERE id_akce=$id_akce";
  $ra= mysql_qry($qa);
  if ( !$ra ) { $err= "chyba MySQL"; goto end; }
  // kopie ze vzoru
  $qa= "INSERT INTO cenik (id_akce,poradi,polozka,za,od,do,cena,dph)
          SELECT $id_akce,poradi,polozka,za,od,do,cena,dph
          FROM cenik WHERE id_akce=$id_akce_vzor";
  $ra= mysql_qry($qa);
  if ( !$ra ) { $err= "chyba MySQL"; goto end; }
end:
  return $err ? $err : "hotovo, nezapomeňte jej upravit (ceny,DPH)";
}
# ---------------------------------------------------------------------------------------- akce_info
# základní informace a obsazenost
function akce_info($id_akce) {  trace();
  $html= '';
  if ( $id_akce ) {
      $dosp= $deti= 0;
      $akce= '';
      $qry= "SELECT nazev, COUNT(DISTINCT p.id_pobyt) AS _rodin, datum_od, datum_do, now() as _ted,
               SUM(IF(t.role='a',1,0)) AS _muzu,
               SUM(IF(t.role='b',1,0)) AS _zen,
               SUM(IF(t.role='d',1,0)) AS _deti
             FROM akce AS a
             JOIN pobyt AS p ON a.id_duakce=p.id_akce
             JOIN spolu AS s ON p.id_pobyt=s.id_pobyt
             JOIN osoba AS o ON s.id_osoba=o.id_osoba
             LEFT JOIN tvori AS t ON t.id_osoba=o.id_osoba
             WHERE id_duakce='$id_akce'
             GROUP BY id_duakce ";
      $res= mysql_qry($qry);
      if ( $res && $p= mysql_fetch_object($res) ) {
        $dosp= $p->_muzu + $p->_zen;
        $deti= $p->_deti;
        $rod= $p->_rodin;
        $akce= $p->nazev;
        $cas1= $p->_ted>$p->datum_od ? "byla" : "bude";
        $cas2= $p->_ted>$p->datum_od ? "Akce se zúčastnilo" : "Na akci je přihlášeno";
        $od= sql_date1($p->datum_od);
        $do= sql_date1($p->datum_do);
        $dne= $p->datum_od==$p->datum_do ? "dne $od" : "ve dnech $od do $do";
      }
      $html= $rod>0
       ? "Akce <b>$akce</b><br>$cas1 $dne<br><br>$cas2
         <br>$dosp dospělých ({$p->_muzu} mužů + {$p->_zen} žen) a<br>$deti dětí, tvořících<br>$rod rodin"
       : "Akce byla vložena do databáze<br>ale nemá zatím žádné účastníky";
  }
  else {
    $html= "Tato akce ještě nebyla
            <br>vložena do databáze
            <br><br>Vložení se provádí dvojklikem
            <br>na řádek s akcí";
  }
  return $html;
}
# ================================================================================================== EVIDENCE
# obsluha karet EVIDENCE
# -------------------------------------------------------------------------------------------------- evid_separ_emaily
# separace emailů
#   $typ = M | MZ | MMZ | Z
#   $op  = confirm | change
function evid_separ_emaily($id_rodina,$typ,$op='confirm') { trace();
  $html= 'ok';
  $r= sql_query(
    "SELECT emaily,
       GROUP_CONCAT(DISTINCT IF(t.role='a',o.jmeno,'')    SEPARATOR '') AS _muz,
       GROUP_CONCAT(DISTINCT IF(t.role='a',o.id_osoba,'') SEPARATOR '') AS _muz_id,
       GROUP_CONCAT(DISTINCT IF(t.role='a',o.email,'')    SEPARATOR '') AS _muz_email,
       GROUP_CONCAT(DISTINCT IF(t.role='b',o.jmeno,'')    SEPARATOR '') AS _zena,
       GROUP_CONCAT(DISTINCT IF(t.role='b',o.id_osoba,'') SEPARATOR '') AS _zena_id,
       GROUP_CONCAT(DISTINCT IF(t.role='b',o.email,'')    SEPARATOR '') AS _zena_email
     FROM rodina AS r
     LEFT JOIN tvori AS t USING(id_rodina)
     LEFT JOIN osoba AS o USING(id_osoba)
     WHERE id_rodina=$id_rodina
     GROUP BY id_rodina ");
  $emaily= trim($r->emaily,"\n\r\t ,;");
  $muz_email= trim($r->_muz_email,"\n\r\t ,;");
  $zena_email= trim($r->_zena_email,"\n\r\t ,;");
  $m= preg_split("/\s*,\s*|\s*;\s*/",$emaily);
  switch($typ) {
  case 'M':   $muz= $emaily; $zena= ''; break;
  case 'MZ':  $muz= $m[0]; $zena= $m[1]; break;
  case 'MMZ': $muz= "{$m[0]},{$m[1]}"; $zena= $m[2]; break;
  case 'Z':   $muz= ''; $zena= $emaily; break;
  }
  if ( $op=='confirm' ) {
    $me= $muz_email ? "\nmá: $muz_email" : '';
    $ze= $zena_email ? "\nmá: $zena_email" : '';
    $html= "Mám rozdělit maily takto?\n\n{$r->_muz}:\n$muz$me\n\n{$r->_zena}:\n$zena$ze\n";
  }
  elseif ( $op=='change' ) {
    if ( $r->emaily != '' )
      ezer_qry("UPDATE",'rodina',$id_rodina,array((object)array(
        'fld'=>'emaily','op'=>'u','val'=>'','old'=>$r->emaily)));
    if ( $r->_muz_email != $muz )
      ezer_qry("UPDATE",'osoba',$r->_muz_id,array((object)array(
        'fld'=>'email','op'=>'u','val'=>$muz,'old'=>$r->_muz_email)));
    if ( $r->_zena_email != $zena )
      ezer_qry("UPDATE",'osoba',$r->_zena_id,array((object)array(
        'fld'=>'email','op'=>'u','val'=>$zena,'old'=>$r->_zena_email)));
  }
  return $html;
}
# ================================================================================================== VYPISY EVIDENCE
# obsluha různých forem výpisů karty EVIDENCE
# -------------------------------------------------------------------------------------------------- evid_sestava
# generování sestav
#   $typ = e-j | e-vps
function evid_sestava($par,$title,$export=false) {
  return $par->typ=='e-j' ? evid_sestava_j($par,$title,$export)
     : ( $par->typ=='e-v' ? evid_sestava_v($par,$title,$export)
     : ( $par->typ=='e-s' ? evid_sestava_s($par,$title,$export)
     : ( $par->typ=='e-p' ? evid_sestava_Q($par,$title,$export,
         "SELECT nazev AS `název akce`,YEAR(datum_od) AS rok,count(*) AS pečounů
          FROM spolu JOIN pobyt USING (id_pobyt) JOIN akce ON id_akce=id_duakce
          WHERE funkce=99
          GROUP BY id_akce ORDER BY datum_od DESC")
     : ( $par->typ=='e-x' ? evid_sestava_x($par,$title,$export)
     : fce_error("evid_sestava: N.Y.I.") ))));
}
# -------------------------------------------------------------------------------------------------- evid_vyp_excel
# generování tabulky do excelu
function evid_vyp_excel($par,$title) {  trace();
  $tab= evid_sestava($par,$title,true);
  $subtitle= "ke dni ".date("d. m. Y");
//                                                         debug($tab,"evid_vyp_excel");
  return akce_vyp_excel("",$tab->par,$title,$subtitle,$tab);
}
# -------------------------------------------------------------------------------------------------- evid_table
# pokud je $par->grf= a:b,c,... pak se zobrazí grafy normalizované podle sloupce a
# pokud je $par->txt doplní se pod tabulku
function evid_table($par,$tits,$flds,$clmn,$export=false) {
  $result= (object)array('par'=>$par);
  if ( $par->grf ) {
    list($norm,$grf)= explode(':',$par->grf);
  }
  $skin= $_SESSION['skin'];
  // zobrazení tabulkou
  $tab= '';
  $thd= '';
  $n= 0;
  if ( $export ) {
    $result->tits= $tits;
    $result->flds= $flds;
    $result->clmn= $clmn;
  }
  else {
    // titulky
    foreach ($tits as $idw) {
      list($id)= explode(':',$idw);
      $ths.= "<th>$id</th>";
    }
    foreach ($clmn as $i=>$c) {
      $tab.= "<tr>";
      foreach ($flds as $f) {
        // přidání grafu
        $g= '';
        if ( strpos(",$grf,",",$f,")!==false ) {
          $g= $c[$norm] ? round(100*($c[$f]/$c[$norm]),0) : 0;
          $g= "<img src='skins/$skin/pixel.png'
            style='height:4px;width:{$g}px;float:left;margin-top:5px'>";
        }
        $align= is_numeric($c[$f]) ? "right" : "left";
        $tab.= "<td style='text-align:$align'>{$c[$f]}$g</td>";
      }
      $tab.= "</tr>";
      $n++;
    }
    $result->html= "<div class='stat'><table class='stat'><tr>$ths</tr>$tab</table>
      $n řádků<br><br>{$par->txt}</div>";
  }
//                                                 debug($result);
  return $result;
}
# -------------------------------------------------------------------------------------------------- evid_sestava_s
# tabulka struktury kurzu (noví,podruhé,vícekrát,odpočívající VPS,VPS)
function evid_sestava_s($par,$title,$export=false) {
  $par->fld= 'nazev';
  $par->tit= 'nazev';
  $tab= evid_sestava_v($par,$title,true);
//                                                         debug($tab,"evid_sestava_v(,$title,$export)");
  $clmn= $suma= array();
  $tit= "rok,párů,u nás - noví,podruhé,vícekrát,vps - odpočívající,ve službě,dětí na kurzu";
  $tits= explode(',',$tit);
  $fld= "rr,x,n,p,v,vo,vs,d";
  $flds= explode(',',$fld);
  $flds_rr= explode(',',substr($fld,3));
  for ($rrrr=1990;$rrrr<=date('Y');$rrrr++) {
    $rr= substr($rrrr,-2);
    $clmn[$rr]= array('rr'=>$rr,'x'=>0);
    $rows= count($tab->clmn);
    for ($n= 1; $n<=$rows; $n++) {
      if ( $xrr= $tab->clmn[$n][$rr] ) {
        $vps= 0;
        $ucast= 0;
        for ($yyyy= $rrrr; $yyyy>=1990; $yyyy--) {
          $yy= substr($yyyy,-2);
          if ( $tab->clmn[$n][$yy] ) $ucast++;
          if ( $tab->clmn[$n][$yy]=='v' ) $vps++;
        }
        // zhodnocení minulosti
        $clmn[$rr]['n']+= !$vps && $ucast==1 ? 1 : 0;
        $clmn[$rr]['p']+= !$vps && $ucast==2 ? 1 : 0;
        $clmn[$rr]['v']+= !$vps && $ucast>2  ? 1 : 0;
        $clmn[$rr]['vo']+= $vps && $xrr=='o' ? 1 : 0;
        $clmn[$rr]['vs']+= $vps && $xrr=='v' ? 1 : 0;
      }
    }
    // přepočty v daném roce
    $suma[$rr]= 0;
    foreach($flds_rr as $fld) {
      $suma[$rr]+= $clmn[$rr][$fld];
    }
  }
  // doplnění počtů dětí
  $qry= "SELECT YEAR(datum_od) AS _rok,id_akce,count(DISTINCT id_pobyt) AS _pary,
           SUM(IF(role='d',1,0)) AS _deti
         FROM pobyt AS p
         JOIN spolu AS s USING(id_pobyt)
         JOIN osoba AS o ON o.id_osoba=s.id_osoba
         JOIN tvori AS t ON t.id_osoba=o.id_osoba
         JOIN akce AS a ON a.id_duakce=p.id_akce
         WHERE a.druh=1 AND p.funkce IN (0,1)
         GROUP BY id_akce";
  $res= mysql_qry($qry);
  while ( $res && ($x= mysql_fetch_object($res)) ) {
    $rr= substr($x->_rok,-2);
    $clmn[$rr]['d']+= $x->_deti;
    $clmn[$rr]['x']+= $x->_pary;
  }
//                                         debug($suma,"součty");
//                                                         debug($clmn,"evid_sestava_s:$tit;$fld");
  $par->tit= $tit;
  $par->fld= $fld;
  $par->grf= "x:n,p,v,vo,vs,d";
  $par->txt= "Pozn. Graficky je znázorněn relativní počet vzhledem k počtu párů.;
    <br>Pokud v nějakém roce bylo více běhů je zobrazen jejich součet.";
  return evid_table($par,$tits,$flds,$clmn,$export);
}
# -------------------------------------------------------------------------------------------------- evid_sestava_v
# generování přehledu akčnosti VPS
function evid_sestava_v($par,$title,$export=false) {
  // dekódování parametrů
  $roky= '';
  for ($r=1990;$r<=date('Y');$r++) {
    $roky.= ','.substr($r,-2);
    $froky.= ','.substr($r,-2).':3';
  }
  $tits= explode(',',$tit= $par->tit.$froky);
  $flds= explode(',',$fld= $par->fld.$roky);
  $HAVING= $par->hav ? "HAVING {$par->hav}" : '';
  $fce= "ovxkphmx";
  // získání dat
  $n= 0;
  $clmn= array();
  $expr= array();       // pro výrazy
  $qry="SELECT COUNT(*) AS _ucasti, r.nazev,r.obec,
          SUM(p.funkce) AS _vps,
          GROUP_CONCAT(DISTINCT CONCAT(YEAR(a.datum_od),':',p.funkce) ORDER BY datum_od SEPARATOR '|') AS _x,
          GROUP_CONCAT(DISTINCT IF(t.role='a',o.jmeno,'') SEPARATOR '') AS jmeno_m,
          GROUP_CONCAT(DISTINCT IF(t.role='b',o.jmeno,'') SEPARATOR '') AS jmeno_z
        FROM pobyt AS p
        JOIN spolu AS s USING(id_pobyt)
        JOIN osoba AS o ON s.id_osoba=o.id_osoba
        LEFT JOIN tvori AS t ON t.id_osoba=o.id_osoba
        LEFT JOIN rodina AS r USING(id_rodina)
        JOIN akce AS a ON a.id_duakce=p.id_akce
        WHERE a.druh=1 AND p.funkce IN (0,1)  /* AND LEFT(r.nazev,3)='Šmí' */
        GROUP BY r.id_rodina $HAVING
        ORDER BY r.nazev
        ";
  $res= mysql_qry($qry);
  while ( $res && ($x= mysql_fetch_object($res)) ) {
    $n++;
    $clmn[$n]= array();
    foreach($flds as $f) {
      switch ( $f ) {
      case '_jmeno':                            // kolektivní člen
        $clmn[$n][$f]= "{$x->nazev} {$x->jmeno_m} a {$x->jmeno_z}";
        break;
      default:
        $clmn[$n][$f]= $x->$f;
      }
    }
    // rozbor let
    foreach(explode('|',$x->_x) as $rf) {
      list($xr,$xf)= explode(':',$rf);
      $clmn[$n][substr($xr,-2)]= $xf < strlen($fce) ? substr($fce,$xf,1) : '?';
    }
  }
//                                                 debug($clmn,"clmn");
  $par->tit= $tit;
  $par->fld= $fld;
  return evid_table($par,$tits,$flds,$clmn,$export);
}
# -------------------------------------------------------------------------------------------------- evid_sestava_j
# generování dat sestavy - zatím jen jednotlivci  $par->typ = j
#   $fld = seznam položek s prefixem
#   $cnd = podmínka
function evid_sestava_j($par,$title,$export=false) {
  // dekódování parametrů
  $tits= explode(',',$par->tit);
  $flds= explode(',',$par->fld);
  // získání dat
  $n= 0;
  $clmn= array();
  $expr= array();       // pro výrazy
  $qry= "SELECT
           os.prijmeni,os.jmeno,os.narozeni,os.sex,
           os.obec,os.ulice,os.psc,os.email,r.emaily,
           GROUP_CONCAT(DISTINCT od.ukon ORDER BY od.ukon SEPARATOR '') as rel,
           GROUP_CONCAT(CONCAT(ukon,':',dat_od,':',castka) ORDER BY dat_od DESC SEPARATOR '|') AS _dar
         FROM osoba AS os
         JOIN tvori AS ot ON os.id_osoba=ot.id_osoba
         JOIN rodina AS r USING(id_rodina)
         LEFT JOIN dar AS od ON os.id_osoba=od.id_osoba AND od.deleted=''
         WHERE os.deleted='' AND {$par->cnd}
         GROUP BY os.id_osoba HAVING {$par->hav}
         ORDER BY os.id_osoba";
  $res= mysql_qry($qry);
  while ( $res && ($x= mysql_fetch_object($res)) ) {
    $n++;
    $clmn[$n]= array();
    foreach($flds as $f) {
      switch ( $f ) {
      case '_kc':                               // kolektivní člen
        $clmn[$n][$f]= 'YMCA Setkání';
        break;
      case '_email':                            // osobní mail nebo první rodinný
        $e= $x->email;
        if ( !$e ) {
          list($em,$ez)= preg_split('/[,;]/',$x->emaily);
          $e= trim($x->sex==1 ? $em : $ez);
        }
        $clmn[$n][$f]= $e;
        break;
      case '_rok': break;                       // rokd posledního členského příspěvku ... viz níže
      case '_prisp':                            // poslední členský příspěvek
        $p= $r= '';
        if ( $x->_dar ) {
          foreach(explode('|',$x->_dar) as $udc) {
            list($u,$d,$c)= explode(':',$udc);
            if ( $u=='p' ) {
              $p= $c;
              $r= substr($d,0,4);
              break;
            }
          }
        }
        $clmn[$n][$f]= $p;
        if ( in_array('_rok',$flds) ) $clmn[$n]['_rok']= $r;
        break;
      case '_clen':                             // druh členství
        $clmn[$n][$f]= strpos($x->rel,'c')!==false ? 'č' : (
                       strpos($x->rel,'b')!==false ? 'b' : (
                       strpos($x->rel,'k')!==false ? 'k' : '-'));
        break;
      case '_naroz':                            // narozeni yymmdd
        $clmn[$n][$f]= substr($x->narozeni,2,2).substr($x->narozeni,5,2).substr($x->narozeni,8,2);
        break;
      default:
        $clmn[$n][$f]= $x->$f;
      }
    }
  }
  return evid_table($par,$tits,$flds,$clmn,$export);
}
# -------------------------------------------------------------------------------------------------- evid_sestava_Q
# generování obecné sestavy zobrazující SQL dotaz
function evid_sestava_Q($par,$title,$export,$qry) {
  // dekódování parametrů
  // získání dat
  $n= 0;
  $clmn= array();
  $expr= array();       // pro výrazy
  $tits= $flds= array();
  $res= mysql_qry($qry);
  while ( $res && ($x= mysql_fetch_object($res)) ) {
    if ( $n==0 ) foreach($x as $f=>$v) {
      $tits[]= $f;
      $flds[]= $f;
    }
    $n++;
    $clmn[$n]= array();
    foreach($x as $f=>$v) {
      $clmn[$n][$f]= $v;
    }
  }
//                                                 debug(array('par'=>$par,'tits'=>$tits,'flds'=>$flds,'clmn'=>$clmn,'export'=>$export),"evid_table");
  $par->tit= $tit;
  $par->fld= $fld;
  return evid_table($par,$tits,$flds,$clmn,$export);
}
# -------------------------------------------------------------------------------------------------- evid_sestava_x
# generování sestavy - podkladů k projektu
# do HTML počet a do Excelu seznam: jména + počet dětí + kraj
# rodin, které a) mají 3 a více dětí
# rodin, které b) mají 1-2 děti, ale není jim ještě 40let
function evid_sestava_x($par,$title,$export) {
  $od= 3;
  // počet akcí
  list($_akci,$_od)= select("count(*),YEAR(CURDATE())-$od","akce","YEAR(datum_od)>=YEAR(CURDATE())-$od");
//                                                 debug($ans);
  // jejich účastníci
  $notes=",(SELECT GROUP_CONCAT(IF(osoba.note,CONCAT(osoba.jmeno,':',osoba.note),''))
    FROM tvori JOIN osoba USING(id_osoba)
    WHERE tvori.id_rodina=r.id_rodina AND tvori.role='d') AS `poznámky k dětem`";
  $result= evid_sestava_Q($par,$title,$export,"
    SELECT COUNT(DISTINCT id_duakce) AS `účastí`, r.nazev AS rodina,
      (SELECT COUNT(*) FROM tvori WHERE tvori.id_rodina=r.id_rodina AND tvori.role='d') AS `dětí`,
      (SELECT COUNT(*) FROM tvori WHERE tvori.id_rodina=r.id_rodina AND tvori.role IN ('a','b')) AS `rodičů`,
      MIN(ROUND(DATEDIFF(NOW(),o.narozeni)/365.2425,0)) AS `věk`, uk.zkratka AS kraj, uk.nuts3,
      IF(ISNULL(uk.nuts3),r.psc,'') AS `?PSČ`$notes
    FROM pobyt AS p JOIN spolu AS s USING(id_pobyt)
    JOIN osoba AS o ON s.id_osoba=o.id_osoba JOIN akce AS a ON a.id_duakce=p.id_akce
    LEFT JOIN tvori AS t ON t.id_osoba=o.id_osoba
    LEFT JOIN rodina AS r USING(id_rodina)
    LEFT JOIN uir_adr.uir_psc AS up ON r.psc=up.psc
    LEFT JOIN uir_adr.kraj AS uk USING(nuts3)
    WHERE t.role IN ('a','b')
      AND p.id_akce IN (SELECT id_duakce FROM akce WHERE 1 AND YEAR(datum_od)>=YEAR(CURDATE())-$od)
    GROUP BY id_rodina /*HAVING `účastí`>30*/
    ORDER BY nuts3,r.nazev
  ");
  $result->html= "Seznam účastníků $_akci akcí, pořádaných od roku $_od<br><br>"
    ."<b>Poznámky</b>:<ul>
      <li>věk je toho mladšího z manželů,
      <li>pokud není určen kraj je v posledním sloupci uvedeno PSČ, ze kterého se to nedalo poznat
      <li>do Excelu se to dostane tlačítkem [Excel] vpravo nahoře
    </ul><br><hr>"
    .$result->html;
  return $result;
}
# ================================================================================================== VYPISY AKCE
# obsluha různých forem výpisů karet AKCE
# -------------------------------------------------------------------------------------------------- akce_vyp_excel
# generování tabulky do excelu
function akce_vyp_excel($akce,$par,$title,$vypis,$tab=null) {  trace();
  global $xA, $xn;
  $result= (object)array('_error'=>0);
  $html= '';
  // získání dat
  $title= str_replace('&nbsp;',' ',$title);
  if ( !$tab )
    $tab= akce_sestava($akce,$par,$title,$vypis,true);
//                                         debug($tab,"akce_sestava($akce,...)"); return;
  // vlastní export do Excelu
  $name= cz2ascii("vypis_").date("Ymd_Hi");
  $xls= <<<__XLS
    |open $name
    |sheet vypis;;L;page
    |A1 $title ::bold size=14 |A2 $vypis ::bold size=12
__XLS;
  // titulky a sběr formátů
  $fmt= $sum= array();
  $n= 4;
  $lc= 0;
  $clmns= $del= '';
  $xA= array();                                 // překladová tabulka: název sloupce => písmeno
  if ( $tab->flds ) foreach ($tab->flds as $f) {
    $A= Excel5_n2col($lc);
    $xA[$f]= $A;
    $lc++;
  }
  $lc= 0;
  if ( $tab->tits ) foreach ($tab->tits as $idw) {
    $A= Excel5_n2col($lc);
    list($id,$w,$f,$s)= explode(':',$idw);      // název sloupce : šířka : formát : suma
    if ( $f ) $fmt[$A]= $f;
    if ( $s ) $sum[$A]= true;
    $xls.= "|$A$n $id";
    if ( $w ) {
      $clmns.= "$del$A=$w";
      $del= ',';
    }
    $lc++;
  }
  if ( $clmns ) $xls.= "\n|columns $clmns ";
  $xls.= "\n|A$n:$A$n bcolor=ffc0e2c2 wrap border=+h|A$n:$A$n border=t\n";
  $n1= $n= 5;                                   // první řádek dat (pro sumy)
  // datové řádky
  if ( $tab->clmn ) foreach ($tab->clmn as $i=>$c) {
    $xls.= "\n";
    $lc= 0;
    foreach ($c as $id=>$val) {
      $A= Excel5_n2col($lc);
      $format= '';
      if (isset($tab->expr[$i][$id]) ) {
        // buňka obsahuje vzorec
        $val= $tab->expr[$i][$id];
        $format.= ' bcolor=ffdddddd';
        $xn= $n;
        $val= preg_replace_callback("/\[([^,]*),([^\]]*)\]/","akce_vyp_subst",$val);
      }
      else {
        // buňka obsahuje hodnotu
        $val= strtr($val,"\n\r","  ");
        if ( isset($fmt[$A]) ) {
          switch ($fmt[$A]) {
          // aplikace formátů
          case 'd': $val= sql2xls($val); $format.= ' right date'; break;
          }
        }
      }
      $format= $format ? "::$format" : '';
      $xls.= "|$A$n $val $format";
      $lc++;
    }
    $n++;
  }
  $n--;
  $xls.= "\n|A$n1:$A$n border=+h|A$n1:$A$n border=t";
  // sumy sloupců
  if ( count($sum) ) {
    $xls.= "\n";
    $nn= $n;
    $ns= $n+2;
    foreach ($sum as $A=>$x) {
      $xls.= "|$A$ns =SUM($A$n1:$A$nn) :: bcolor=ffdddddd";
    }
  }
  // tabulka DPH, pokud je
  if ( $tab->DPH ) {
    $n+= 3;
    $nd1= $n;
    $xls.= "\n|A$n Tabulka DPH :: bcolor=ffc0e2c2 |A$n:B$n merge center\n";
    $n++;
    $nd= $n;
    for($i= 0; $i<count($tab->DPH); $i+= 2) {
      $lab= $tab->DPH[$i];
      $exp= $tab->DPH[$i+1];
      $xn= $ns;
      $exp= preg_replace_callback("/\[([^,]*),([^\]]*)\]/","akce_vyp_subst",$exp);
      $xls.= "|A$n $lab ::right|B$n $exp :: bcolor=ffdddddd";
      $n++;
    }
    $n--;
    $xls.= "\n|A$nd:B$n border=+h|A$nd1:B$n border=t";
  }
  // konec
  $xls.= <<<__XLS
    \n|close
__XLS;
  // výstup
//                                                                 display($xls);
  $inf= Excel5($xls,1);
  if ( $inf ) {
    $html= " se nepodařilo vygenerovat - viz začátek chybové hlášky";
    fce_error($inf);
  }
  else {
    $html= " Výpis byl vygenerován ve formátu <a href='docs/$name.xls' target='xls'>Excel</a>.";
  }
  $result->html= $html;
  return $result;
}
function akce_vyp_subst($matches) { trace();
  global $xA, $xn;
//                                                 debug($xA);
//                                                 debug($matches);
  if ( !isset($xA[$matches[1]]) ) fce_error("akce_vyp_excel: chybný název sloupce '{$matches[1]}'");
  $A= $xA[$matches[1]];
  $n= $xn+$matches[2];
  return "$A$n";
}
# ---------------------------------------------------- sql2xls
// datum bez dne v týdnu
function sql2xls($datum) {
  // převeď sql tvar na uživatelskou podobu (default)
  $text= ''; $del= '.';
  if ( $datum && substr($datum,0,10)!='0000-00-00' ) {
    $y=substr($datum,0,4);
    $m=substr($datum,5,2);
    $d=substr($datum,8,2);
    $text.= date("j{$del}n{$del}Y",strtotime($datum));
  }
  return $text;
}
# ================================================================================================== EMAILY - SQL
# vytváření a testování SQL dotazů pro definici mailů
# -------------------------------------------------------------------------------------------------- db_mail_copy_ds
# ASK - kopie tabulky SETKANI.DS_OSOBA do EZER_YS.DS_OSOBA_COPY
# vrací {id_cis,data,query}
function db_mail_copy_ds() {  trace();
  global $ezer_db;
  $html= 'kopie se nepovedla';
  // smazání staré kopie
  $qry= "TRUNCATE TABLE ezer_ys.ds_osoba_copy ";
  $ok= mysql_qry($qry);
  if ( $ok ) {
    $html= "inicializace ds_osoba_copy ok";
    ezer_connect('setkani');
    $qrs= "SELECT * FROM setkani.ds_osoba WHERE email!='' ";
    $res= mysql_qry($qrs);
    while ( $res && ($s= mysql_fetch_object($res)) ) {
//                                                         debug($s,'s',(object)array('win1250'=>1));
      $ids= $vals= $del= '';
      foreach($s as $id=>$val) {
        $ids.= "$del$id";
        $vals.= "$del'".mysql_real_escape_string(wu($val))."'";
        $del= ',';
      }
      $qry= "INSERT INTO ezer_ys.ds_osoba_copy ($ids) VALUES ($vals)";
      $ok= mysql_query($qry,$ezer_db['ezer_ys'][0]);
//                                                         display("$ok:$qry");
      if ( !$ok ) {
        $html.= "\nPROBLEM ".mysql_error();
      }
    }
    if ( $ok ) {
      $html.= "\nkopie do ds_osoba_copy ok";
    }
  }
  return $html;
}
# -------------------------------------------------------------------------------------------------- db_mail_sql_new
# ASK - vytvoření SQL dotazů pro definici mailů
# vrací {id_cis,data,query}
function db_mail_sql_new() {  #trace();
  $id= select("MAX(0+id_cis)","_cis","druh='db_maily_sql'");
  $data= select("MAX(0+data)","_cis","druh='db_maily_sql'");
  $result= (object)array(
    'id'=>$id+1, 'data'=>$data+1,
    'qry'=>"SELECT id_... AS _id,prijmeni,jmeno,ulice,psc,obec,email,telefon FROM ...");
  return $result;
}
# -------------------------------------------------------------------------------------------------- db_mail_sql_subst
# ASK - parametrizace SQL dotazů pro definici mailů, vrací modifikovaný dotaz
# nebo pokud je prázdný tak přehled možných parametrizací dotazu
function db_mail_sql_subst($qry='') {  trace();
  // parametry
  $parms= array (
   'letos' => array (date('Y'),'letošní rok'),
   'vloni' => array (date('Y')-1,'loňský rok'),
   'pred2' => array (date('Y')-2,'předloni'),
   'pred3' => array (date('Y')-3,'před 3 lety'),
   'pred4' => array (date('Y')-4,'před 4 lety'),
   'pred5' => array (date('Y')-5,'před 5 lety'),
   'pred6' => array (date('Y')-6,'před 6 lety')
  );
  if ( $qry=='' ) {
    // help
    $del= '';
    foreach ($parms as $parm=>$value) {
      $qry.= "$del\$$parm = {$value[1]} ({$value[0]})";
      $del= '<br>';
    }
  }
  else {
    // substituce
    foreach ($parms as $parm=>$value) {
      $qry= str_replace("\$$parm",$value[0],$qry);
    }
  }
  return $qry;
}
# -------------------------------------------------------------------------------------------------- db_mail_sql_try
# ASK - vytvoření SQL dotazů pro definici mailů
# vrací {id_cis,data,query}
function db_mail_sql_try($qry,$vsechno=0) {  trace();
  $html= $head= $tail= '';
  $emails= array();
  try {
    // substituce
    $qry= db_mail_sql_subst($qry);
    // dotaz
    $time_start= getmicrotime();
    $res= mysql_qry($qry);
    $time= round(getmicrotime() - $time_start,4);
    if ( !$res ) {
      $html.= "<span style='color:darkred'>ERROR ".mysql_error()."</span>";
    }
    else {
      $nmax= $vsechno ? 99999 : 200;
      $num= mysql_num_rows($res);
      $head.= "Výběr obsahuje <b>$num</b> emailových adresátů, nalezených během $time ms, ";
      $head.= $num>$nmax ? "následuje prvních $nmax adresátů" : "následují všichni adresáti";
      $tail.= "<br><br><table class='stat'>";
      $tail.= "<tr><th>prijmeni jmeno</th><th>email</th><th>telefon</th>
        <th>ulice psc obec</th><th>x</th><th>y</th><th>z</th></tr>";
      $n= $nmax;
      while ( $res && ($c= mysql_fetch_object($res)) ) {
        if ( $n ) {
          $tail.= "<tr><td>{$c->prijmeni} {$c->jmeno}</td><td>{$c->_email}</td><td>{$c->telefon}</td>
            <td>{$c->ulice} {$c->psc} {$c->obec}</td><td>{$c->_x}</td><td>{$c->_y}</td><td>{$c->_z}</td></tr>";
          $n--;
        }
        // počítání mailů
        $es= preg_split('/[,;]/',str_replace(' ','',$c->_email));
        foreach($es as $e) {
          if ( $e!='' && !in_array($e,$emails) ) $emails[]= $e;
        }
      }
      $tail.= "</table>";
      $tail.= $num>$nmax ? "..." : "";
    }
  }
  catch (Exception $e) { $html.= "<span style='color:red'>FATAL ".mysql_error()."</span>";  }
  $head.= "<br>Adresáti mají <b>".count($emails)."</b> různých emailových adres";
  $html= $html ? $html : $head.$tail;
//                                                 debug($emails,"db_mail_sql_try");
  return $html;
}
# ================================================================================================== EMAILY
# podpora přihlášek do Klubu
# -------------------------------------------------------------------------------------------------- db_mail_confirm_yes
# ASK
# přijetí potvrzení kliknutím na $url&conf=$id_webform&veri=md5
function db_mail_confirm_yes($id_webform,$md5) {  trace();
  // vyzvednutí údajů z přihlášky
  $potvrzeno= '?';
  $qry= "SELECT * FROM webform WHERE id_webform='$id_webform' ";
  $res= mysql_qry($qry);
  if ( $res && ($w= mysql_fetch_object($res)) ) {
    // kontrola vyplněných položek
    $kod= md5($id_webform.$w->jmeno.$w->prijmeni.$w->email);
    $potvrzeno= $w->potvrzeno;
    $potvrzeno.= date("d.m.Y H:i ").($kod==$md5 ? "ok" : "?");
    $qryu= "UPDATE webform SET potvrzeno='$potvrzeno|' WHERE id_webform='$id_webform' ";
    $resr= mysql_qry($qryu);
  }
  return "$potvrzeno ($id_webform,$md5)";
}
# -------------------------------------------------------------------------------------------------- db_mail_confirm_ask
# ASK
# zaslání emailu s žádostí o potvrzení kliknutím na $url&conf=$id_webform&veri=md5
function db_mail_confirm_ask($id_webform,$url) {  trace();
  $from= "cerny.vavrovice@seznam.cz";
  $from= "martin@smidek.eu";
  // vyzvednutí údajů z přihlášky
  $qry= "SELECT * FROM webform WHERE id_webform='$id_webform' ";
  $res= mysql_qry($qry);
  if ( $res && ($w= mysql_fetch_object($res)) ) {
    // rekapitulace vyplněných položek
    $flds= "Jméno: {$w->title} {$w->jmeno} {$w->prijmeni}";
    if ( $w->ulice || $w->psc || $w->obec )
      $flds.="<br>Bydliště: {$w->ulice} {$w->psc} {$w->obec}";
    if ( $w->email || $w->telefon )
      $flds.="<br>Kontakt: {$w->email}; {$w->telefon}";
    // vytvoření potvrzující adresy
    global $path_url;
    $kod= md5($id_webform.$w->jmeno.$w->prijmeni.$w->email);
    $url= "$url&conf=$id_webform&veri=$kod";
    // kompozice těla mailu
    $text= <<<__EOD
    <html><body>
      <p>Děkujeme Vám za vyplnění přihlášky do <i>Klubu přátel YMCA Setkání</i></p>
      Potvrďte prosím správnost uvedených údajů
      <blockquote>$flds</blockquote>
      <br>kliknutím na tento odkaz: $url
      <br>Tím bude vaše přihláška po formální stránce ukončena.
      <p>Těšíme se na naši další spolupráci</p>
    </body></html>
__EOD;
    $obj= db_mail_send($from,$w->email,"Přihláška do Klubu přátel YMCA Setkání",$text);
    $html= $obj->_html;
  }
  else {
    $html= "Nebyly nalezeny údaje ...";
  }
  return $html;
}
# -------------------------------------------------------------------------------------------------- db_mail_send
# ASK
# odešli dávku $kolik mailů ($kolik=0 znamená testovací poslání)
function db_mail_send($from,$to,$subj,$text) { trace();
  global $ezer_path_serv;
  require_once("$ezer_path_serv/licensed/phpmailer/class.phpmailer.php");
  $result= (object)array('_error'=>0);
  $html= '';
  // napojení na mailer
  $mail= new PHPMailer;
  $mail->Host= "192.168.1.1";
  $mail->CharSet = "utf-8";
  $mail->From= $from;
  $mail->AddReplyTo($from);
  $mail->FromName= "YMCA Setkání";
  $mail->AddAddress($to);
  $mail->Subject= $subj;
  $mail->Body= $text;
  $mail->IsHTML(true);
  $mail->Mailer= "smtp";
  try { $ok= $mail->Send(); } catch(Exception $e) { $ok= false; }
  if ( $ok  )
    $html.= "<br><b style='color:#070'>Byl odeslán mail pro $to - je zapotřebí zkontrolovat obsah</b>";
  else {
    $html.= "<br><b style='color:#700'>Při odesílání mailu došlo k chybě: {$mail->ErrorInfo}</b>";
    $result->_error= 1;
  }
  // zpráva o výsledku
  $result->_html= $html;
  return $result;
}
# ================================================================================================== EMAILY
# jednotlivé maily posílané v sadách příložitostně skupinám
#   DOPIS(id_dopis=key,id_davka=1,druh='@',nazev=předmět,datum=datum,obsah=obsah,komu=komu(číselník),
#         nw=min(MAIL.stav,nh=max(MAIL.stav)})
#   MAIL(id_mail=key,id_davka=1,id_dopis=DOPIS.id_dopis,znacka='@',id_clen=clen,email=adresa,
#         stav={0:nový,3:rozesílaný,4:ok,5:chyba})
# formát zápisu dotazu v číselníku viz fce dop_mai_qry
# -------------------------------------------------------------------------------------------------- dop_mai_pocet_spec
# spočítá maily podle daného maillistu
function dop_mai_confirm_spec($id_mailist,$id_dopis) {  trace();
  $ret= (object)array('specialni'=>0, 'prepsat'=>'', 'pocet'=>'');
  // speciální?
  list($ret->specialni,$qry)= select('specialni,sexpr','mailist',"id_mailist=$id_mailist");
  if ( !$ret->specialni ) goto end;
  // jsou už vygenerované maily
  $ret->prepsat= select('COUNT(*)','mail',"id_dopis=$id_dopis")
    ? "Opravdu přepsat předchozí maily?" : '';
  // počet nově vygenerovaných
  $res= mysql_qry($qry);
  $ret->pocet= "Opravdu vygenerovat maily na ".mysql_num_rows($res)." adres?";
end:
  return $ret;
}
# -------------------------------------------------------------------------------------------------- dop_mai_posli_spec
# vygeneruje sadu mailů podle daného maillistu s nastaveným specialni a parms
function dop_mai_posli_spec($id_dopis) {  trace();
  $ret= (object)array('msg'=>'');
  $id_mailist= select('id_mailist','dopis',"id_dopis=$id_dopis");
  list($spec,$sparms,$qry,$ucel)= select('specialni,parms,sexpr,ucel','mailist',"id_mailist=$id_mailist");
//                                                         debug($parms,$ucel);
  $parms= json_decode($sparms);
  switch ($parms->specialni) {
  case 'potvrzeni':
    // smaž starý seznam
    mysql_qry("DELETE FROM mail WHERE id_dopis=$id_dopis");
    $num= 0;
    $nomail= array();
    $rok= date('Y')+$parms->rok;
    // projdi všechny relevantní dárce podle dotazu z maillistu
    $os= mysql_qry($qry);
    while ($os && ($o= mysql_fetch_object($os))) {
      $email= $o->email;
      if ( $email ) {
        // vygeneruj PDF s potvrzením do $x->path
        $x= dop_mai_potvr("Pf",$o,$rok);
        // vlož mail
        mysql_qry(
          "INSERT mail (id_davka,znacka,stav,id_dopis,id_clen,email,priloha)
             VALUE (1,'@',0,$id_dopis,$o->id_osoba,'$email','{$x->fname}')");
        $num+= mysql_affected_rows();
      }
      else {
        $nomail[]= "{$o->jmeno} {$o->prijmeni}";
      }
    }
    // oprav počet v DOPIS
    mysql_qry("UPDATE dopis SET pocet=$num WHERE id_dopis=$id_dopis");
    // informační zpráva
    $ret->msg= "Bylo vygenerováno $num mailů";
    if ( count($nomail) ) $ret->msg.= ", emailovou adresu nemají:<hr>".implode(', ',$nomail);
    break;
  default:
    fce_error("není implemntováno");
  }
//                                                         debug($ret,"dop_mai_posli_spec end");
  return $ret;
}
# -------------------------------------------------------------------------------------------------- dop_mai_potvr
# vygeneruje PDF s daňovým potvrzením s výsledkem
# ret->fname - jméno vygenerovaného PDF souboru
# ret->href  - odkaz na soubor
# ret->fpath - úplná lokální cesta k souboru
# ret->log   - log
function dop_mai_potvr($druh,$o,$rok) {  trace();
  $ret= (object)array('msg'=>'');
  // report
  $d= select("*","dopis","typ='$druh'");
  $vzor= $d->obsah;
  $sablona= $d->sablona;
  $texty= array();
  $parss= array();
  // výpočet proměnných použitých v dopisu
  $is_vars= preg_match_all("/[\{]([^}]+)[}]/",$vzor,$list);
  $vars= array_merge($list[1],array('vyrizeno'));
//                                                         debug($vars,"vars");
  $dary= json_decode($o->pars);
  $data= $dary->data;
  $castka= number_format($o->castka, 0, '.', ' ');
  $id_osoba= $o->id_osoba;
  $prijmeni= $o->prijmeni;
  $jmeno= $o->jmeno;
  $sex= $o->sex;
  $ulice= $o->ulice;
  $psc= $o->psc;
  $obec= $o->obec;
  $osloveni= $sex==1 ? "pan" : ($sex==2 ? "paní" : "");
  $Osloveni= $sex==1 ? "Pan" : ($sex==2 ? "Paní" : "");
  $adr= "$osloveni,$prijmeni,$jmeno,$sex,$ulice,$psc,$obec";
  $html= "<table>";
  $html.= "<tr><td>$id</td><td>$castka</td><td>$adr</td><td>$data</td></tr>";
  // definice parametrů pro potvrzující dopis
  $parss[$n]= (object)array();
  $parss[$n]->dar_datum= $data;
  $parss[$n]->dar_castka= str_replace(' ','&nbsp;',$castka);
  $parss[$n]->darce= "$osloveni <b>$jmeno $prijmeni</b>";
  $parss[$n]->darce_a= $sex==2 ? "a" : "";
  $parss[$n]->vyrizeno= date('j. n. Y');
  // substituce v 'text' a 'odeslano'
  $text= $vzor;
  $odeslano= select('obsah','dopis_cast',"name='odeslano'");
  if ( $is_vars ) foreach ($vars as $var ) {
    $text= str_replace('{'.$var.'}',$parss[$n]->$var,$text);
    $odeslano= str_replace('{'.$var.'}',$parss[$n]->$var,$odeslano);
  }
  // úprava lámání textu kolem jednopísmenných předložek a přilepení Kč k částce
  $text= preg_replace(array('/ ([v|k|z|s|a|o|u|i]) /u','/ Kč/u'),array(' \1&nbsp;','&nbsp;Kč'),$text);
  $texty[$n]->adresa= "<b>$Osloveni<br>$jmeno $prijmeni<br>$ulice<br>$psc $obec</b>";
  $texty[$n]->odeslano= $odeslano;
  $texty[$n]->text= $text;
  $n++;
  $html.= "<hr>$text";
  $html.= "</table>";
  // předání k tisku
//                                                 debug($parss);
//                                                 display($html);
  global $ezer_path_docs, $ezer_root;
  $ret->fname= "potvrzeni_{$rok}_$id_osoba.pdf";
  $ret->fpath= "$ezer_path_docs/$ezer_root/{$ret->fname}";
  $dlouhe= tc_dopisy($texty,$ret->fpath,'','_user',$listu);
  $ret->href= "<a href='docs/$ezer_root/{$ret->fname}' target='pdf'>{$ret->fname}</a>";
//   $html.= " Bylo vygenerováno $listu potvrzení do $href.";
  // konec
  $ret->log= $html;
  return $ret;
}
# -------------------------------------------------------------------------------------------------- dop_mai_text
# přečtení mailu
function dop_mai_text($id_dopis) {  trace();
  $d= null;
  try {
    $qry= "SELECT * FROM dopis WHERE id_dopis=$id_dopis ";
    $res= mysql_qry($qry,1,null,1);
    $d= mysql_fetch_object($res);
  }
  catch (Exception $e) { display($e); fce_error("dop_mai_text: průběžný dopis No.'$id_dopis' nebyl nalezen"); }
  $html.= "<b>{$d->nazev}</b><br/><hr/>{$d->obsah}";
  // příloha?
  if ( $d->prilohy ) {
    foreach ( explode(',',$d->prilohy) as $priloha ) {
      $priloha= $priloha;
      $html.= "<hr/><b>Příloha:</b> $priloha";
      $typ= strtolower(substr($priloha,-4));
      if ( $typ=='.jpg' || $typ=='.gif' || $typ=='.png' ) {
        $html.= "<img src='docs/$priloha' />";
      }
    }
  }
//                                                         debug($d,"dop_mai_text($id_dopis)");
  return $html;
}
# -------------------------------------------------------------------------------------------------- dop_mai_prazdny
# zjistí zda neexistuje starý seznam adresátů
function dop_mai_prazdny($id_dopis) {  trace();
  $result= array('_error'=>0, '_prazdny'=> 1);
  // ověř prázdnost MAIL
  $qry= "SELECT * FROM mail WHERE id_dopis=$id_dopis";
  $res= mysql_qry($qry);
  if ( mysql_num_rows($res)>0 ) {
    $result['_html']= "Rozesílací seznam pro tento mail již existuje, stiskni OK pokud má být přepsán novým";
    $result['_prazdny']= 0;
  }
  return $result;
}
# -------------------------------------------------------------------------------------------------- dop_mai_qry
# sestaví SQL dotaz podle položky DOPIS.komu
# formát zápisu dotazu v číselníku:  A[|D[|cond]]
#   kde A je seznam aktivit oddělený čárkami
#   a D=1 pokud mají být začleněni pouze letošní a loňští dárci
#   a cond je obecná podmínka na položky tabulky CLEN
function dop_mai_qry($komu) {  trace();
  list($aktivity,$is_dary,$cond)= explode('|',$komu);
  $and= $aktivity=='*' ? '' : "AND FIND_IN_SET(aktivita,'$aktivity')";
  if ( $cond ) $and.= " AND $cond";
  $letos= date('Y'); $loni= $letos-1;
  $qry= $is_dary
    ? "SELECT id_clen, email,
         BIT_OR(IF((YEAR(datum) BETWEEN $loni AND $letos) AND LEFT(dar.deleted,1)!='D'
           AND castka>0 AND akce='G',1,0)) AS is_darce
       FROM clen LEFT JOIN dar USING (id_clen)
       WHERE LEFT(clen.deleted,1)!='D' AND umrti='0000-00-00' AND aktivita!=9 AND email!='' $and
       GROUP BY id_clen HAVING is_darce=1"
    : "SELECT id_clen, email FROM clen
       WHERE left(deleted,1)!='D' AND umrti='0000-00-00' AND email!='' $and";
  return $qry;
}
# -------------------------------------------------------------------------------------------------- dop_mai_omitt
# v tabulce MAIL(id_dopis=$dopis) označí jako neposlatelné emailu z MAIL($id_dopis=$vynech)
# to je funkce určená k zamezení duplicit
function dop_mai_omitt($id_dopis,$ids_vynech) {  trace();
  $msg= "Z mailů podle dopisu $id_dopis budou vynechány adresy z mailů podle dopisu $ids_vynech";
  // seznam vynechaných adres
  $vynech= array();
  $qv= "SELECT email FROM mail WHERE id_dopis IN ($ids_vynech) ";
  $rv= mysql_qry($qv);
  while ( $rv && ($v= mysql_fetch_object($rv)) ) {
    foreach(explode(',',str_replace(';','',str_replace(' ','',$v->email))) as $em) {
      $vynech[]= $em;
    }
  }
//                                                         debug($vynech,"vynechané adresy");
  $msg.= "<br>podezřelých je ".count($vynech)." adres";
  // probírka adresátů
  $n= 0;
  $qd= "SELECT id_mail,email FROM mail WHERE id_dopis=$id_dopis ";
  $rd= mysql_qry($qd);
  while ( $rd && ($d= mysql_fetch_object($rd)) ) {
    $emaily= $d->email;
    foreach(explode(',',str_replace(';','',str_replace(' ','',$emaily))) as $em) {
      if ( in_array($em,$vynech) ) {
        $n++;
        $qu= "UPDATE mail SET stav=5,msg='- $ids_vynech' WHERE id_mail={$d->id_mail} ";
        $ru= mysql_qry($qu);
      }
    }
  }
  $msg.= "<br>označeno bylo $n adres";
  return $msg;
}
# -------------------------------------------------------------------------------------------------- dop_mai_omitt
# v tabulce MAIL(id_dopis=$dopis) označí jako neposlatelné emaily $vynech (čárkami oddělený seznam)
function dop_mai_omitt2($id_dopis,$lst_vynech) {  trace();
  // seznam vynechaných adres
  $vynech= explode(',',str_replace(' ','',$lst_vynech));
  $msg= "Z mailů podle dopisu $id_dopis bude vynecháno ".count($vynech)." adres";
//                                                         debug($vynech,"vynechané adresy");
  // probírka adresátů
  $n= 0;
  $qd= "SELECT id_mail,email FROM mail WHERE id_dopis=$id_dopis ";
  $rd= mysql_qry($qd);
  while ( $rd && ($d= mysql_fetch_object($rd)) ) {
    $emaily= $d->email;
    foreach(explode(',',str_replace(';','',str_replace(' ','',$emaily))) as $em) {
//                                         display("'$em'=".(in_array($em,$vynech)?1:0));
      if ( in_array($em,$vynech) ) {
        $n++;
        $qu= "UPDATE mail SET stav=5,msg='viz' WHERE id_mail={$d->id_mail} ";
        $ru= mysql_qry($qu);
      }
    }
  }
  $msg.= "<br>označeno bylo $n adres";
  return $msg;
}
# -------------------------------------------------------------------------------------------------- dop_mai_pocet
# zjistí počet adresátů pro rozesílání a sestaví dotaz pro confirm
# $dopis_var určuje zdroj adres
#   'U' - rozeslat účastníkům akce dopis.id_duakce ukazující do akce
#         do seznamu se dostanou pouze účastnící s funkcí:0,1,2,6 (-,VPS,SVPS,hospodář)
#   'U2'- rozeslat účastníkům akce dopis.id_duakce ukazující do akce
#         do seznamu se dostanou pouze organizující účastnící s funkcí:1,2,6 (VPS,SVPS,hospodář)
#   'U3'- rozeslat účastníkům akce dopis.id_duakce ukazující do akce
#         do seznamu se dostanou pouze dlužníci
#   'Q' - rozeslat na adresy vygenerované dopis.cis_skupina => hodnota
# pokud _cis.data=9999 jde o speciální seznam definovaný funkcí dop_mai_skupina - ZRUŠENO
# $cond = dodatečná podmínka POUZE pro volání z dop_mai_stav
function dop_mai_pocet($id_dopis,$dopis_var,$cond='',$recall=false) {  trace();
  $result= (object)array('_error'=>0, '_count'=> 0, '_cond'=>false);
  $result->_html= 'Rozesílání mailu nemá určené adresáty, stiskni ZRUŠIT';
  $emaily= $ids= $jmena= $pobyty= array();
  $spatne= $nema= $mimo= $nomail= '';
  $n= $ns= $nt= $nx= $mx= $nm= 0;
  $dels= $deln= $delm= $delnm= '';
  $nazev= '';
  switch ($dopis_var) {
  // mail-list
  case 'G':
    $id= select('id_mailist','dopis',"id_dopis=$id_dopis");
    list($qry,$ucel)= select('sexpr,ucel','mailist',"id_mailist=$id");
    // SQL dotaz z mail-listu obsahuje _email,_nazev,_id
    $res= mysql_qry($qry);
    while ( $res && ($d= mysql_fetch_object($res)) ) {
      $n++;
      $nazev= "'$ucel'";
      if ( $d->nomail ) {
        // nechce dostávat maily
        $nomail.= "$delnm{$d->_name}"; $delnm= ', '; $nm++;
        continue;
      }
      if ( $d->_email ) {
        // přidej každý mail zvlášť do seznamu
        foreach(preg_split('/\s*[,;]\s*/',trim($d->_email,",; \n\r"),0,PREG_SPLIT_NO_EMPTY) as $adr) {
          // pokud tam ještě není
          if ( $adr && !in_array($adr,$emaily) ) {
            if ( $adr[0]=='*' ) {
              // vyřazený mail
              $mimo.= "$delm{$d->_name}"; $delm= ', '; $mx++;
            }
            else {
              $emaily[]= $adr;
              $ids[]= $d->_id;
              $jmena[]= $d->_name;
            }
          }
        }
      }
      else {
        $nema.= "$deln{$d->_name}"; $deln= ', ';
        $nx++;
      }
    }
    break;
  // obecný SQL dotaz - skupina
  case 'Q':
    $qryQ= "SELECT _cis.hodnota,_cis.zkratka FROM dopis
           JOIN _cis ON _cis.data=dopis.cis_skupina AND _cis.druh='db_maily_sql'
           WHERE id_dopis=$id_dopis ";
    $resQ= mysql_qry($qryQ);
    if ( $resQ && ($q= mysql_fetch_object($resQ)) ) {
      $qry= $q->hodnota;
      $qry= db_mail_sql_subst($qry);
      $res= mysql_qry($qry);
      while ( $res && ($d= mysql_fetch_object($res)) ) {
        $n++;
        $nazev= "Členů {$q->zkratka}";
        $jm= "{$d->prijmeni} {$d->jmeno}";
        if ( $d->nomail ) {
          // nechce dostávat maily
          $nomail.= "$delnm$jm"; $delnm= ', '; $nm++;
          continue;
        }
        if ( $d->_email ) {
          // přidej každý mail zvlášť do seznamu
          foreach(preg_split('/\s*[,;]\s*/',trim($d->_email,",; \n\r"),0,PREG_SPLIT_NO_EMPTY) as $adr) {
            // pokud tam ještě není
            if ( $adr && !in_array($adr,$emaily) ) {
              if ( $adr[0]=='*' ) {
                // vyřazený mail
                $mimo.= "$delm$jm"; $delm= ', '; $mx++;
              }
              else {
                $emaily[]= $adr;
                $ids[]= $d->_id;
                $jmena[]= $jm;
              }
            }
          }
        }
        else {
          $nema.= "$deln{$d->prijmeni} {$d->jmeno}"; $deln= ', ';
          $nx++;
        }
      }
    }
    break;
  // účastníci akce
  case 'U3':    // dlužníci
  case 'U2':    // sloužící
  case 'U':
    $AND= $cond ? "AND $cond" : '';
    $AND.= $dopis_var=='U'  ? " AND p.funkce IN (0,1,2,5)" : (
           $dopis_var=='U2' ? " AND p.funkce IN (1,2,5)"   : (
           $dopis_var=='U3' ?
             " AND p.funkce IN (0,1,2,5) AND
               IF(a.ma_cenu,
                 IF(p.platba1+p.platba2+p.platba3+p.platba4>0,
                   p.platba1+p.platba2+p.platba3+p.platba4,
                   IF(pouze>0,1,2)*a.cena)>platba,
                 p.platba1+p.platba2+p.platba3+p.platba4>platba)"
         : " --- chybné komu --- " ));
    // využívá se toho, že role rodičů 'a','b' jsou před dětskou 'd', takže v seznamech
    // GROUP_CONCAT jsou rodiče, byli-li na akci. Emaily se ale vezmou ode všech
    $qry= "SELECT a.nazev,id_pobyt,
           GROUP_CONCAT(DISTINCT o.id_osoba ORDER BY t.role) AS _id,
           GROUP_CONCAT(DISTINCT CONCAT(prijmeni,' ',jmeno) ORDER BY t.role) AS _jm,
           GROUP_CONCAT(DISTINCT o.email) AS email, GROUP_CONCAT(DISTINCT r.emaily) AS emaily
           FROM dopis AS d
           JOIN akce AS a ON d.id_duakce=a.id_duakce
           JOIN pobyt AS p ON d.id_duakce=p.id_akce
           JOIN spolu AS s USING(id_pobyt)
           JOIN osoba AS o ON s.id_osoba=o.id_osoba
           JOIN tvori AS t ON t.id_osoba=o.id_osoba
           JOIN rodina AS r USING (id_rodina)
           WHERE id_dopis=$id_dopis $AND GROUP BY id_pobyt";
    $res= mysql_qry($qry);
    while ( $res && ($d= mysql_fetch_object($res)) ) {
      $n++;
      $nazev= "Účastníků {$d->nazev}";
      list($jm)= explode(',',$d->_jm);
      if ( $d->nomail ) {
        // nechce dostávat maily
        $nomail.= "$delnm$jm"; $delnm= ', '; $nm++;
        continue;
      }
      if ( $d->email!='' || $d->emaily!='' ) {
        $em= "{$d->email},{$d->emaily}";
        if ( strpos($em,'*')!==false ) {
          // vyřazený mail
          $mimo.= "$delm$jm"; $delm= ', '; $mx++;
          continue;
        }
        $emaily[]= $em;
        $pobyty[]= $d->id_pobyt;
        list($ids[])= explode(',',$d->_id);
        list($jmena[])= explode(',',$d->_jm);
      }
      else {
        $nema.= "$deln$jm"; $deln= ', ';
        $nx++;
      }
    }
    break;
  }
  // projdi adresy
//                                                 debug($emaily,"emaily");
  for ($i= 0; $i<count($ids); $i++) {
    $email= ''; $del= '';
    foreach(preg_split('/\s*[,;]\s*/',$emaily[$i],0,PREG_SPLIT_NO_EMPTY) as $adr) {
//                                                 debug(preg_split('/\s*[,;]\s*/',$emaily[$i],0,PREG_SPLIT_NO_EMPTY),$emaily[$i]); break;
      $chyba= '';
//                                                 display("$adr");
      if ( emailIsValid($adr,$chyba) ) {
        $email.= $del.$adr;                     // první dobrý bude adresou
        $del= ',';                              // zbytek pro CC
      }
    }
    if ( $email ) {
      $emaily[$i]= $email;
      $nt++;
    }
    else {                                      // žádný nebyl ok
      $spatne.= "$dels{$jmena[$i]}"; $dels= ', ';
      unset($emaily[$i],$ids[$i],$jmena[$i],$pobyty[$i]);
      $ns++;
    }
  }
  $result->_adresy= $emaily;
  $result->_pobyty= $pobyty;
  $result->_ids= $ids;
  $html.= "$nazev je $n celkem\n";
  $html.= $ns ? "$ns má chybný mail ($spatne)\n" : '';
  $html.= $nx ? "$nx nemají mail ($nema)\n" : '';
  $html.= $nm ? "$nm nechtějí hromadné informace ($nomail)\n" : '';
  $html.= $mx ? "$mx mají mail označený '*' jako nedostupný ($mimo)" : '';
  $result->_html= $nt>0
    ? "Opravdu vygenerovat seznam pro rozeslání\n'$nazev'\nna $nt adres?"
    : "Mail '$nazev' nemá žádného adresáta, stiskni ZRUŠIT";
  $result->_html.= "\n\n$html";
  $result->_count= $nt;
  if ( !$recall ) {
    // pro delší seznamy
    $result->_dopis_var= $dopis_var;
    $result->_cond= $cond ? $cond : '';
    $result->_adresy= array();
    $result->_ids= array();
  }
//                                                 debug($result,"dop_mai_pocet.result");
  return $result;
}
# -------------------------------------------------------------------------------------------------- dop_mai_posli
# do tabulky MAIL dá seznam emailových adres pro rozeslání (je volána po dop_mai_pocet)
# $id_dopis => dopis(&pocet)
# $info = {_adresy,_ids[,_cond]}   _cond
function dop_mai_posli($id_dopis,$info) {  trace();
  $num= 0;
//                                                         debug($info);
  // smaž starý seznam
  $qry= "DELETE FROM mail WHERE id_dopis=$id_dopis ";
//                                                         fce_log("dop_mai_posli: $qry");
  $res= mysql_qry($qry);
  if ( !$res ) fce_error("dop_mai_smaz: mazání rozesílání mailu No.'$id_dopis' se nepovedlo");

  if ( isset($info->_dopis_var) ) {
    // přepočítej adresy
    $info= dop_mai_pocet($id_dopis,$info->_dopis_var,$info->_cond,true);
//     $info->_adresy= $result->_adresy;
  }
  if ( isset($info->_adresy) ) {
    // zjisti text dopisu a jestli obsahuje proměnné
    $obsah= select('obsah','dopis',"id_dopis=$id_dopis");
    $is_vars= preg_match_all("/[\{]([^}]+)[}]/",$obsah,$list);
    $vars= $list[1];
//                                                                 debug($vars);
    // pokud jsou přímo známy adresy, pošli na ně
    $ids= array();
    foreach($info->_ids as $i=>$id) $ids[$i]= $id;
    if ( $info->_pobyty ) foreach($info->_pobyty as $i=>$pobyt) $pobyty[$i]= $pobyt;
    foreach ($info->_adresy as $i=>$email) {
      $id= $ids[$i];
      // vlož do MAIL - pokud nezačíná *
      if ( $email[0]!='*' ) {
        $id_pobyt= isset($pobyty[$i]) ? $pobyty[$i] : 0;
        // pokud dopis obsahuje proměnné, personifikuj obsah
        $body= $is_vars ? dop_mail_personify($obsah,$vars,$id_pobyt) : '';
        $qr= "INSERT mail (id_davka,znacka,stav,id_dopis,id_clen,id_pobyt,email,body)
              VALUE (1,'@',0,$id_dopis,$id,$id_pobyt,'$email','$body')";
//                                         display("$i:$qr");
        $rs= mysql_qry($qr);
        $num+= mysql_affected_rows();
      }
    }
  }
  else {
    // jinak zjisti adresy z databáze
    $qry= dop_mai_qry($info->_cond);
    $res= mysql_qry($qry);
    while ( $res && $c= mysql_fetch_object($res) ) {
      // vlož do MAIL
      if ( $c->email[0]!='*' ) {
        $qr= "INSERT mail (id_davka,znacka,stav,id_dopis,id_clen,email)
              VALUE (1,'@',0,$id_dopis,{$c->id_clen},'{$c->email}')";
        $rs= mysql_qry($qr);
        $num+= mysql_affected_rows();
      }
    }
  }
  // oprav počet v DOPIS
  $qr= "UPDATE dopis SET pocet=$num WHERE id_dopis=$id_dopis";
//                                                         fce_log("dop_mai_posli: UPDATE");
  $rs= mysql_qry($qr);
  return true;
}
# -------------------------------------------------------------------------------------------------- dop_mail_personify
# spočítá proměnné podle id_pobyt a dosadí do textu dopisu
# vrátí celý text
function dop_mail_personify($obsah,$vars,$id_pobyt) {
  $text= $obsah;
  $p= select('*','pobyt',"id_pobyt=$id_pobyt");
  foreach($vars as $var) {
    $val= '';
    switch ($var) {
    case 'akce_cena':
      if ( $p->duvod_typ ) {
        $val= $p->duvod_text;
      }
      else {
        $ret= akce_vzorec($id_pobyt);
        $val= $ret->mail;
      }
      break;
    }
    $text= str_replace('{'.$var.'}',$val,$text);
  }
  $text= mysql_real_escape_string($text);
  return $text;
}
# -------------------------------------------------------------------------------------------------- dop_mai_info
# informace o členovi
# $id - klíč osoby nebo chlapa
# $zdroj určuje zdroj adres
#   'U','U2','U3' - rozeslat účastníkům akce dopis.id_duakce ukazující do akce
#   'C' - rozeslat účastníkům akce dopis.id_duakce ukazující do ch_ucast
#   'Q' - rozeslat na adresy vygenerované dopis.cis_skupina => hodnota
#   'G' - maillist
function dop_mai_info($id,$email,$id_dopis,$zdroj,$id_mail) {  trace();
  $html= '';
  switch ($zdroj) {
  case 'C':                     // chlapi
    $qry= "SELECT * FROM ezer_ys.chlapi WHERE id_chlapi=$id ";
    $res= mysql_qry($qry);
    if ( $res && $c= mysql_fetch_object($res) ) {
      $html.= "{$c->prijmeni} {$c->jmeno}<br>";
      $html.= "{$c->ulice}, {$c->psc} {$c->obec}<br><br>";
      if ( $c->telefon )
        $html.= "Telefon: {$c->telefon}<br>";
    }
    break;
  case 'Q':                     // číselník
    $qryQ= "SELECT _cis.hodnota,_cis.zkratka,_cis.barva FROM dopis
           JOIN _cis ON _cis.data=dopis.cis_skupina AND _cis.druh='db_maily_sql'
           WHERE id_dopis=$id_dopis ";
    $resQ= mysql_qry($qryQ);
    if ( $resQ && ($q= mysql_fetch_object($resQ)) ) {
      if ( $q->barva==1 ) {
        // databáze CHLAPI
        $qry= "SELECT * FROM ezer_ys.chlapi WHERE id_chlapi=$id ";
        $res= mysql_qry($qry);
        if ( $res && $c= mysql_fetch_object($res) ) {
          $html.= "{$c->prijmeni} {$c->jmeno}<br>";
          $html.= "{$c->ulice}, {$c->psc} {$c->obec}<br><br>";
          if ( $c->telefon )
            $html.= "Telefon: {$c->telefon}<br>";
        }
      }
      elseif ( $q->barva==4 ) {
        // kopie databáze DS = ds_osoba_copy
        $qry= "SELECT * FROM ds_osoba_copy WHERE id_osoba=$id ";
        $res= mysql_qry($qry);
        if ( $res && $c= mysql_fetch_object($res) ) {
          $html.= "{$c->prijmeni} {$c->jmeno}<br>";
          $html.= "{$c->ulice}, {$c->psc} {$c->obec}<br><br>";
          if ( $c->telefon )
            $html.= "Telefon: {$c->telefon}<br>";
        }
      }
      elseif ( $q->barva==2 ) {
        // databáze osob
        $qry= "SELECT * FROM osoba WHERE id_osoba=$id ";
        $res= mysql_qry($qry);
        if ( $res && $c= mysql_fetch_object($res) ) {
          $html.= "{$c->prijmeni} {$c->jmeno}<br>";
          $html.= "{$c->ulice}, {$c->psc} {$c->obec}<br><br>";
          if ( $c->telefon )
            $html.= "Telefon: {$c->telefon}<br>";
        }
      }
      else {
        // databáze MS
        // SELECT vrací (_id,prijmeni,jmeno,ulice,psc,obec,email,telefon)
        $qry= $q->hodnota;
        $qry= db_mail_sql_subst($qry);
        if ( strpos($qry,"GROUP BY") ) {
          if ( strpos($qry,"HAVING") )
            $qry= str_replace("HAVING","HAVING _id=$id AND ",$qry);
          else
            $qry= str_replace("GROUP BY","GROUP BY _id HAVING _id=$id AND ",$qry);
          // zatém jen pro tuto větev
          $res= mysql_qry($qry);
          while ( $res && ($c= mysql_fetch_object($res)) ) {
            $html.= "{$c->prijmeni} {$c->jmeno}<br>";
            $html.= "{$c->ulice}, {$c->psc} {$c->obec}<br><br>";
            if ( $c->telefon )
              $html.= "Telefon: {$c->telefon}<br>";
          }
        }
        else {
          // způsobuje chybu  GROUP BY vyžaduje nějakou agregační funkci
//           $qry.= " GROUP BY _id HAVING _id=$id ";
        }
      }
    }
    break;
  case 'U':                     // účastníci akce
  case 'U2':                    // sloužící účastníci akce
  case 'U3':                    // dlužníci
    $qry= "SELECT * FROM osoba WHERE id_osoba=$id ";
    $res= mysql_qry($qry);
    if ( $res && $c= mysql_fetch_object($res) ) {
      $html.= "{$c->id_osoba}: {$c->jmeno} {$c->prijmeni}<br>";
      $html.= "{$c->ulice}, {$c->psc} {$c->obec}<br><br>";
      if ( $c->telefony )
        $html.= "Telefon: {$c->telefony}<br>";
    }
    break;
  case 'G':                     // mail-list
    function href($fnames) {
      global $ezer_root;
      $href= array();
      foreach(explode(',',$fnames) as $fnamesize) {
        list($fname)= explode(':',$fnamesize);
        $href[]= "<a href='docs/$ezer_root/$fname' target='pdf'>$fname</a>";
      }
      return implode(', ',$href);
    }
    list($obsah,$prilohy)= select('obsah,prilohy','dopis',"id_dopis=$id_dopis");
    $priloha= select('priloha','mail',"id_mail=$id_mail");
    $c= select("*",'osoba',"id_osoba=$id");
    $html.= "{$c->id_osoba}: {$c->jmeno} {$c->prijmeni}<br>";
    $html.= "{$c->ulice}, {$c->psc} {$c->obec}<br><br>";
    if ( $c->telefony )
      $html.= "Telefon: {$c->telefony}<br>";
    // přílohy ke kontrole
    if ( $prilohy )
      $html.= "<br>Společné přílohy: ".href($prilohy);
    if ( $priloha )
      $html.= "<br>Vlastní přílohy: ".href($priloha);
    break;
  }
  return $html;
}
# -------------------------------------------------------------------------------------------------- dop_mai_smaz
# smazání mailu v DOPIS a jeho rozesílání v MAIL
function dop_mai_smaz($id_dopis) {  trace();
  $qry= "DELETE FROM dopis WHERE id_dopis=$id_dopis ";
  $res= mysql_qry($qry);
  if ( !$res ) fce_error("dop_mai_smaz: mazání mailu No.'$id_dopis' se nepovedlo");
  $qry= "DELETE FROM mail WHERE id_dopis=$id_dopis ";
  $res= mysql_qry($qry);
  if ( !$res ) fce_error("dop_mai_smaz: mazání rozesílání mailu No.'$id_dopis' se nepovedlo");
  return true;
}
# -------------------------------------------------------------------------------------------------- dop_mai_stav
# úprava stavu mailové adresy
# ZATIM BEZ: (maže maily - nutné zohlednit i id_clen==id_osoba aj.) včetně znovuzískání mailové adresy s karty účastníka
function dop_mai_stav($id_mail,$stav) {  trace();

  list($id_dopis,$id_pobyt)= select("id_dopis,id_pobyt","mail","id_mail=$id_mail");
  $novy_mail= '';
//   if ( $id_pobyt) {
//     $oprava= dop_mai_pocet($id_dopis,'U',$cond="id_pobyt=$id_pobyt");
//     $emaily= $oprava->_adresy[0];
//     $novy_mail= ",email='$emaily'";
//                                                   debug($oprava,"dop_mai_stav:$emaily.");
//   }
  $qry= "UPDATE mail SET stav=$stav$novy_mail WHERE id_mail=$id_mail ";
  $res= mysql_qry($qry);
  if ( !$res ) fce_error("dop_mai_stav: změna stavu mailu No.'$id_mail' se nepovedla");
  return true;
}
# -------------------------------------------------------------------------------------------------- dop_mai_send
# ASK
# odešli dávku $kolik mailů ($kolik=0 znamená testovací poslání)
# $from,$fromname = From,ReplyTo
# $test = 1 mail na tuto adresu (pokud je $kolik=0)
# pokud je definováno $id_mail s definovaným text MAIL.body, použije se - jinak DOPIS.obsah
function dop_mai_send($id_dopis,$kolik,$from,$fromname,$test='',$id_mail=0) { trace();
  // připojení případné přílohy
  function attach($mail,$fname) {
    global $ezer_root;
    if ( $fname ) {
      foreach ( explode(',',$fname) as $fnamesb ) {
        list($fname,$bytes)= explode(':',$fnamesb);
        $fpath= "docs/$ezer_root/".trim($fname);
        $mail->AddAttachment($fpath);
  } } }
  //
  global $ezer_path_serv, $ezer_root;
  $phpmailer_path= "$ezer_path_serv/licensed/phpmailer";
  require_once("$phpmailer_path/class.phpmailer.php");
  $result= (object)array('_error'=>0);
  $pro= '';
  // přečtení rozesílaného mailu
  $qry= "SELECT * FROM dopis WHERE id_dopis=$id_dopis ";
  $res= mysql_qry($qry,1,null,1);
  $d= mysql_fetch_object($res);
  // napojení na mailer
  $html= '';
//   $klub= "klub@proglas.cz";
  $martin= "martin@smidek.eu";
//   $jarda= "cerny.vavrovice@seznam.cz";
//   $jarda= $martin;
  // poslání mailů
  $mail= new PHPMailer;
  $mail->SetLanguage('cz',"$phpmailer_path/language/");
  $mail->Host= "192.168.1.1";
  $mail->CharSet = "utf-8";
  $mail->From= $from;
  $mail->AddReplyTo($from);
//   $mail->ConfirmReadingTo= $jarda;
  $mail->FromName= "$fromname";
  $mail->Subject= $d->nazev;
  $mail->IsHTML(true);
  $mail->Mailer= "smtp";
  attach($mail,$d->prilohy);
//   if ( $d->prilohy ) {
//     foreach ( explode(',',$d->prilohy) as $fnamesb ) {
//       list($fname,$bytes)= explode(':',$fnamesb);
//       $fpath= "docs/$ezer_root/".trim($fname);
//       $mail->AddAttachment($fpath);
//     }
//   }
  if ( $kolik==0 ) {
    // testovací poslání sobě
    if ( $id_mail ) {
      // přečtení personifikace rozesílaného mailu
      $qry= "SELECT * FROM mail WHERE id_mail=$id_mail ";
      $res= mysql_qry($qry,1,null,1);
      $m= mysql_fetch_object($res);
      if ( $m->body ) {
        $obsah= $m->body;
        $pro= "s personifikací pro {$m->email}";
      }
      else {
        // jinak obecný z DOPIS
        $obsah= $d->obsah;
        $pro= '';
      }
      attach($mail,$m->priloha);
    }
    $mail->Body= $obsah;
    $mail->AddAddress($test);   // pošli sám sobě
    // pošli
    $ok= $mail->Send();
    if ( $ok  )
      $html.= "<br><b style='color:#070'>Byl odeslán mail na $test $pro - je zapotřebí zkontrolovat obsah</b>";
    else {
      $err= $mail->ErrorInfo;
      $html.= "<br><b style='color:#700'>Při odesílání mailu došlo k chybě: $err</b>";
      $result->_error= 1;
    }
//                                                 display($html);
  }
  else {
    // poslání dávky $kolik mailů
    $n= $nko= 0;
    $qry= "SELECT * FROM mail WHERE id_dopis=$id_dopis AND stav IN (0,3)";
    $res= mysql_qry($qry);
    while ( $res && ($z= mysql_fetch_object($res)) ) {
      // posílej mail za mailem
      if ( $n>=$kolik ) break;
      $n++;
      $i= 0;
      $mail->ClearAddresses();
      $mail->ClearCCs();
      if ( $z->body ) {
        // pokud má mail definován obsah (personifikovaný mail) ber z MAIL
        $obsah= $z->body;
      }
      else {
        // jinak obecný z DOPIS
        $obsah= $d->obsah;
      }
      // přílohy - pokud jsou vlastní, pak je třeba staré vymazat a vše vložit
      if ( $z->priloha ) {
        $mail->ClearAttachments();
        attach($mail,$d->prilohy);
        attach($mail,$z->priloha);
      }
      $mail->Body= $obsah;
      foreach(preg_split("/,\s*|;\s*|\s+/",trim($z->email," ,;"),-1,PREG_SPLIT_NO_EMPTY) as $adresa) {
        if ( !$i++ )
          $mail->AddAddress($adresa);   // pošli na 1. adresu
        else                            // na další jako kopie
          $mail->AddCC($adresa);
      }
//       $mail->AddBCC($klub);
      // zkus poslat mail
      try { $ok= $mail->Send(); } catch(Exception $e) { $ok= false; }
      if ( !$ok  ) {
        $ident= $z->id_clen ? $z->id_clen : $adresa;
        $err= $mail->ErrorInfo;
        $html.= "<br><b style='color:#700'Při odesílání mailu pro $ident došlo k chybě: $err</b>";
        $result->_error= 1;
        $nko++;
      }
      // zapiš výsledek do tabulky
      $stav= $ok ? 4 : 5;
      $msg= $ok ? '' : $mail->ErrorInfo;
      $qry1= "UPDATE mail SET stav=$stav,msg=\"$msg\" WHERE id_mail={$z->id_mail}";
      $res1= mysql_qry($qry1);
    }
    $html.= "<br><b style='color:#070'>Bylo odesláno $n emailů ";
    $html.= $nko ? "s $nko chybami " : "bez chyb";
    $html.= "</b>";
  }
  // zpráva o výsledku
  $result->_html= $html;
//                                                 debug($result,"dop_mai_send");
  return $result;
}
# ----------------------------------------------------------------------------------- dop_mai_attach
# přidá další přílohu k mailu (soubor je v docs/$ezer_root)
function dop_mai_attach($id_dopis,$f) { trace();
  // nalezení záznamu v tabulce a přidání názvu souboru
  $names= select('prilohy','dopis',"id_dopis=$id_dopis");
  $names= ($names ? "$names," : '')."{$f->name}:{$f->size}";
  query("UPDATE dopis SET prilohy='$names' WHERE id_dopis=$id_dopis");
  return 1;
}
# ------------------------------------------------------------------------------- dop_mai_detach_all
# odstraní všechny přílohy mailu
function dop_mai_detach_all($id_dopis) { trace();
  query("UPDATE dopis SET prilohy='' WHERE id_dopis=$id_dopis");
  return 1;
}
# ----------------------------------------------------------------------------------- dop_mai_detach
# odebere soubor z příloh
function dop_mai_detach($id_dopis,$name) { trace();
  // nalezení záznamu v tabulce a přidání názvu souboru
  $names= select('prilohy','dopis',"id_dopis=$id_dopis");
  $as= explode(',',$names);
  $as2= array();
  foreach($as as $a) {
    list($an,$ab)= explode(':',$a);
    if ( $an!=$name )$as2[]= $a;
  }
  $names2= implode(',',$as2);
  query("UPDATE dopis SET prilohy='$names2' WHERE id_dopis=$id_dopis");
  return 1;
}
# ================================================================================================== Generátor SQL
# -------------------------------------------------------------------------------------- dop_gen_pdf
# vygenerování PDF se samolepkami - adresními štítky
#   $the_json obsahuje  title:'{jmeno_postovni}<br>{adresa_postovni}'
function dop_gen_pdf($gq,$report_json) { trace();
  global $ezer_root, $json, $ezer_path_docs;
  $href= "CHYBA!";
  // projdi požadované adresy rodin
  $n= 0;
  $parss= array();
  $qry=  "SELECT
          r.nazev as nazev,p.pouze as pouze,
          GROUP_CONCAT(DISTINCT IF(t.role='a',o.prijmeni,'') SEPARATOR '') as prijmeni_m,
          GROUP_CONCAT(DISTINCT IF(t.role='a',o.jmeno,'')    SEPARATOR '') as jmeno_m,
          GROUP_CONCAT(DISTINCT IF(t.role='a',o.narozeni,'') SEPARATOR '') as narozeni_m,
          GROUP_CONCAT(DISTINCT IF(t.role='a',o.rc_xxxx,'')  SEPARATOR '') as rc_xxxx_m,
          GROUP_CONCAT(DISTINCT IF(t.role='b',o.prijmeni,'') SEPARATOR '') as prijmeni_z,
          GROUP_CONCAT(DISTINCT IF(t.role='b',o.jmeno,'')    SEPARATOR '') as jmeno_z,
          GROUP_CONCAT(DISTINCT IF(t.role='b',o.narozeni,'') SEPARATOR '') as narozeni_z,
          GROUP_CONCAT(DISTINCT IF(t.role='b',o.rc_xxxx,'')  SEPARATOR '') as rc_xxxx_z,
          r.ulice,r.psc,r.obec,r.stat,r.telefony,r.emaily,p.poznamka
          FROM pobyt AS p
          JOIN spolu AS s USING(id_pobyt)
          JOIN osoba AS o ON s.id_osoba=o.id_osoba
          LEFT JOIN tvori AS t ON t.id_osoba=o.id_osoba
          LEFT JOIN rodina AS r USING(id_rodina)
          WHERE $cond
          GROUP BY id_pobyt
          ORDER BY IF(funkce<=2,1,funkce),IF(pouze=0,r.nazev,o.prijmeni)";
  $res= mysql_qry($qry);
  while ( $res && ($x= mysql_fetch_object($res)) ) {
    $x->prijmeni= $x->pouze==1 ? $x->prijmeni_m : ($x->pouze==2 ? $x->prijmeni_z : $x->nazev);
    $x->jmena=    $x->pouze==1 ? $x->jmeno_m    : ($x->pouze==2 ? $x->jmeno_z : "{$x->jmeno_m} a {$x->jmeno_z}");
    // formátované PSČ (tuzemské a slovenské)
    $psc= (!$x->stat||$x->stat=='CZ'||$x->stat=='SK')
      ? substr($x->psc,0,3).' '.substr($x->psc,3,2)
      : $x->psc;
    $stat= $x->stat=='CZ' ? '' : $x->stat;
    // definice pole substitucí
    $parss[$n]= (object)array();
    $parss[$n]->jmeno_postovni= "{$x->jmena} {$x->prijmeni}";
    $parss[$n]->adresa_postovni= "{$x->ulice}<br/>$psc  {$x->obec}".( $stat ? "<br/>        $stat" : "");
    $n++;
  }
  // předání k tisku
  $fname= 'stitky_'.date("Ymd_Hi");
  $fpath= "$ezer_path_docs/$ezer_root/$fname.pdf";
  dop_rep_ids($report_json,$parss,$fpath);
  $href= "soubor v <a href='docs/$ezer_root/$fname.pdf' target='pdf'>PDF</a>.";
  return $result;
}
# ------------------------------------------------------------------------------------ dop_gen_excel
# vygeneruje do Excelu seznam adresátů
function dop_gen_excel($gq,$nazev) { trace();
  global $ezer_root;
  $href= "CHYBA!";
  // úprava dotazu
  $gq= str_replace('&gt;','>',$gq);
  $gq= str_replace('&lt;','<',$gq);
                                                        display($gq);
  // export do Excelu
  // zahájení exportu
  $ymd_hi= date('Ymd_Hi');
  $dnes= date('j. n. Y');
  $t= "$nazev, stav ke dni $dnes";
  $file= "maillist_$ymd_hi";
  $type= 'xls';
  $par= (object)array('dir'=>$ezer_root,'file'=>$file,'type'=>$type,'title'=>$t,'color'=>'aac0cae2');
  $clmns= "_name:příjmení jméno,_email:email,_ulice:ulice,_psc:PSČ,_obec:obec,_ucasti:účastí";
  $titles= $del= '';
  $fields= $values= array();
  foreach (explode(',',$clmns) as $clmn) {
    list($field,$title)= explode(':',trim($clmn));
    $title= $title ? $title : $field;
    $titles.= "$del$title";
    $fields[]= $field;
    $values[$field]= "";
    $del= ',';
  }
  $pipe= array('narozeni'=>'sql_date1');
  export_head($par,$titles,":: bcolor=ffc0e2c2 wrap border=+h");
  // dotaz
  $gr= @mysql_query($gq);
  if ( !$gr ) { fce_warning(mysql_error()); goto end; }
  while ( $gr && ($g= mysql_fetch_object($gr)) ) {
    foreach ($g as $f => $val) {
      if ( in_array($f,$fields) ) {
        $a= $val;
        if ( isset($pipe[$f]) ) $a= $pipe[$f]($a);
        $values[$f]= $a;
      }
    }
    export_row($values,":: border=+h");
  }
  export_tail();
//                                                 display(export_tail(1));
  // odkaz pro stáhnutí
  $href= "soubor pro <a href='docs/$ezer_root/$file.$type' target='xls'>Excel</a>";
end:
  return $href;
}
# -------------------------------------------------------------------------------------- dop_gen_try
# mode=0 -- spustit a ukázat dotaz a také výsledek
# mode=1 -- zobrazit argument jako html
function dop_gen_try($gq,$mode=0) { trace();
  $html= $del= '';
  switch ($mode) {
  case 0:
    $n= $nw= $nm= $nx= 0;
    $gq= str_replace('&gt;','>',$gq);
    $gq= str_replace('&lt;','<',$gq);
    $gr= @mysql_query($gq);
    if ( !$gr ) {
      $html= mysql_error()."<hr>".nl2br($gq);
      goto end;
    }
    else while ( $gr && ($g= mysql_fetch_object($gr)) ) {
      $n++;
      $name= str_replace(' ','&nbsp;',$g->_name);
      if ( !$g->_email ) {
        $nw++;
        $name= "<span style='color:darkred'>$name</span>";
      }
      if ( $g->nomail ) {
        $nm++;
        $name= "<span style='background-color:yellow'>$name</span>";
      }
      if ( $g->_email[0]=='*' ) {
        // vyřazený mail
        $nx++;
        $name= "<strike><b>$name</b></strike>";
      }
      $html.= "$del$name";
      $del= ', ';
    }
    $warn= $nw+$nm+$nx ? " (" : '';
    $warn.= $nw ? "$nw <span style='color:darkred'>nemá email</span> ani rodinný" : '';
    $warn.= $nw && $nm ? ", " : '';
    $warn.= $nm ? "$nm <span style='background-color:yellow'>nechce hromadné</span> informace
      - budou vyňati z mail-listu" : '';
    $warn.= ($nw||$nm) && $nx ? ", " : '';
    $warn.= $nx ? "$nx má <strike>zneplatněný email</strike>" : '';
    $warn.= $nw+$nm+$nx ? ")" : '';
    $html= "<b>Nalezeno $n adresátů$warn:</b><br>$html";
    break;
  case 1:
    $html= nl2br($gq);
    break;
  }
end:
  return $html;
}
# ------------------------------------------------------------------------------------- dop_gen_json
# test json
function dop_gen_json($js) { //trace();
  global $json;
//                                                         display($js);
  $obj= $json->decode($js);
//                                                         debug($obj);
  $obj= json_decode($js);
//                                                         debug($obj);
  return 1;
}
# ------------------------------------------------------------------------------------- dop_gen_read
# převod parm do objektu
function dop_gen_read($parm) { trace();
  global $json;
//                                                         display($parm);
  $obj= $json->decode($parm);
//                                                         debug($obj);
//   $obj= json_decode($js);
//                                                         debug($obj);
  return $obj;
}
?>
