<?php
/*
//# ---------------------------------------------------------------------------------- evid2 elim_tips
//# tipy na duplicitu ve formě CASE ... END, type= 
//#   mrop - pro iniciované muže
//#   mail - lidi se stejným meilem
//function evid2_elim_tips($type) {
//  $ret= (object)array('ids'=>0,'tip'=>"''");
//  if ($type=='mail') {
//    $ret= evid2_elim_fld_tips('email');
//    goto end;
//  }
//  elseif ($type=='telefon') {
//    $ret= evid2_elim_fld_tips('telefon');
//    goto end;
//  }
//  elseif ($type=='narozeni') {
//    $ret= evid2_elim_fld_tips('narozeni','jmeno');
//    goto end;
//  }
//  switch ($type) {
//  case 'mrop': $qry= "
//      SELECT o.id_osoba,GROUP_CONCAT(DISTINCT d.id_osoba) AS _ruzne
//      FROM osoba AS o
//      JOIN osoba AS d USING (prijmeni,jmeno,narozeni)
//      WHERE o.iniciace>0 AND d.iniciace=0 
//        AND o.deleted='' AND d.deleted='' AND o.id_osoba!=d.id_osoba
//      GROUP BY o.id_osoba HAVING LOCATE(',',_ruzne)>0    
//    ";
//    break;
//  case 'narozeni': $qry= "
//      SELECT o.id_osoba,GROUP_CONCAT(DISTINCT d.id_osoba) AS _ruzne
//      FROM osoba AS o
//      JOIN osoba AS d USING (prijmeni,jmeno,narozeni)
//      WHERE o.narozeni!='0000-00-00' AND o.prijmeni!='' AND o.jmeno NOT IN ('','???')
//      --  AND o.iniciace=0 AND d.iniciace=0 
//        AND o.deleted='' AND d.deleted='' AND o.id_osoba!=d.id_osoba
//      GROUP BY o.id_osoba HAVING LOCATE(',',_ruzne)>0    
//    ";
//    break;
//  case 'prijmeni': $qry= "
//      SELECT o.id_osoba,GROUP_CONCAT(DISTINCT d.id_osoba) AS _ruzne
//      FROM osoba AS o
//      JOIN osoba AS d USING (prijmeni,jmeno)
//      WHERE o.narozeni!='0000-00-00' AND o.prijmeni!='' AND o.jmeno NOT IN ('','???')
//      --  AND o.iniciace=0 AND d.iniciace=0 
//        AND o.deleted='' AND d.deleted='' AND o.id_osoba!=d.id_osoba
//      GROUP BY o.id_osoba HAVING LOCATE(',',_ruzne)>0    
//    ";
//    break;
//  case 'mail': $qry= "
//      SELECT o.id_osoba,GROUP_CONCAT(DISTINCT d.id_osoba) AS _maily
//      FROM osoba AS o
//      JOIN osoba AS d USING (email)
//      WHERE o.kontakt=1 AND d.kontakt=1 AND o.email!='' 
//        AND o.deleted='' AND d.deleted='' AND o.id_osoba!=d.id_osoba
//      GROUP BY o.email HAVING LOCATE(',',_maily)>0    
//    ";
//    break;
//  case 'telefon': $qry= "
//      SELECT o.id_osoba,GROUP_CONCAT(DISTINCT d.id_osoba) AS _telefony
//      FROM osoba AS o
//      JOIN osoba AS d USING (telefon)
//      WHERE o.kontakt=1 AND d.kontakt=1 AND o.telefon!='' 
//        AND o.deleted='' AND d.deleted='' AND o.id_osoba!=d.id_osoba
//      GROUP BY o.telefon HAVING LOCATE(',',_telefony)>0    ";
//    break;
//  }
//  if ( !$qry ) goto end;
//  // vlastní prohledání
//  $ids= ""; $del= "(";
//  $tip= "";
//  $zs= pdo_qry($qry);
//  while ($zs && (list($id,$tips)= pdo_fetch_row($zs))) {
//    $ids.= "$del $id,$tips"; $del= ",";
//    $tip.= " WHEN $id THEN '$tips'";
//    foreach (explode(',',$tips) as $tp) {
//      $tip.= " WHEN $tp THEN '$id'";
//    }
//  }
//  $ret->ids= $ids ? "o.id_osoba IN $ids )" : '0';
//  $ret->tip= $tip ? "CASE id_osoba $tip ELSE 0 END" : 0;
//end:
//  return $ret;
//}
*/
# ---------------------------------------------------------------------------------- evid2 elim_tips
# tipy na duplicitu mailů nebo telefonů - vrací seznam
#   mail - lidi se stejným mailem
function evid2_elim_fld_tips($flds) {
  $ret= (object)array('ids'=>0,'tip'=>"''");
  list($fld1,$fld2)= explode(',',$flds);
  $m_os= [];
  $cond= "$fld1!=''".($fld2 ? " AND $fld2!=''" : '');
  $match= $fld2 ? "CONCAT($fld1,$fld2)" : $fld1;
  $zs= pdo_qry("SELECT id_osoba,$match FROM osoba WHERE kontakt=1 AND $cond AND deleted='' "
//      . "AND prijmeni IN ('xDrašnar','Drašnar','Koňarik','Šikulová','Sikulová','Vítková','- Vítková')"
//      . "ORDER BY tip -- id_osoba "
      );
  while ($zs && (list($ido,$mails)= pdo_fetch_row($zs))) {
    foreach (preg_split('/\s*[,;]\s*/',trim($mails," \n\r\t;,#")) as $m) {
      $m= str_replace(' ','',$m);
      if (!isset($m_os[$m])) 
        $m_os[$m]= [$ido];
      else
        $m_os[$m][]= $ido;
    }
  }
  // zkusíme filtr
  // projdeme duplicity
  $n= 0;
  $ids= ""; $del= "(";
  $tip= "";
  foreach ($m_os as $m=>$os) {
    if (count($os)>1) {
      $n++;
      $ids.= $del.implode(',',$os); $del= ",";
      foreach ($os as $tp) {
        $tip.= " WHEN $tp THEN $n ";
      }
    }
  }
  display("CELKEM $n duplicit");
  $ret->ids= $ids ? "o.id_osoba IN $ids )" : '0';
  $ret->tip= $tip ? "CASE id_osoba $tip ELSE 0 END" : 0;
  return $ret;
}
# --------------------------------------------------------------------------------- elim2_split_keys
# ASK
# rozdělí klíče v řetězci pro elim_rodiny na dvě půlky
function elim2_split_keys($keys) {
  $ret= (object)array('c1'=>'','c2'=>'');
  $k1= $k2= array();
  foreach (explode(';',$keys) as $cs) {
    $cs= explode(',',$cs);
    $c0= array_shift($cs);
//                                                         debug($cs,$c0);
    if ( !in_array($c0,$k2) ) {
      $k1[]= $c0;
    }
    foreach ($cs as $c) {
      if ( !in_array($c,$k1) && !in_array($c,$k2) ) {
        $k2[]= $c;
      }
    }
  }
  $ret->c1= implode(',',$k1);
  $ret->c2= implode(',',$k2);
//                                                         debug($ret);
  return $ret;
}
# ------------------------------------------------------------------------------------- elim2_differ
# do _track potvrdí, že $id_orig,$id_copy jsou různé osoby nebo rodiny
function elim2_differ($id_orig,$id_copy,$table) { trace();
  global $USER;
  user_test();
  $ret= (object)array('err'=>'');
  $now= date("Y-m-d H:i:s");
  // zápis o neztotožnění osob/rodin do _track jako op=d (duplicita)
  $user= $USER->abbr;
  $ok= query("INSERT INTO _track (kdy,kdo,kde,klic,fld,op,old,val)
         VALUES ('$now','$user','$table',$id_orig,'','r','různé od',$id_copy)");    // r=různost
  $ok= query("INSERT INTO _track (kdy,kdo,kde,klic,fld,op,old,val)
         VALUES ('$now','$user','$table',$id_copy,'','r','různé od',$id_orig)");    // r=různost
end:
  return $ret;
}
# ---------------------------------------------------------------------------------==> . elim2_osoba
# zamění všechny výskyty kopie za originál v TVORI, SPOLU, DAR, PLATBA, MAIL
# a kopii poznačí jako smazanou
function elim2_osoba($id_orig,$id_copy) { //trace();
  global $USER;
  user_test();
  $ret= (object)array('err'=>'');
  $now= date("Y-m-d H:i:s");
  // tvori
  $tvori= select("GROUP_CONCAT(id_tvori)","tvori", "id_osoba=$id_copy");
  query("UPDATE tvori  SET id_osoba=$id_orig WHERE id_osoba=$id_copy");
  // spolu
  $spolu= select("GROUP_CONCAT(id_spolu)","spolu","id_osoba=$id_copy");
  query("UPDATE spolu  SET id_osoba=$id_orig WHERE id_osoba=$id_copy");
  // dar
  $dar=   select("GROUP_CONCAT(id_dar)",  "dar",  "id_osoba=$id_copy");
  query("UPDATE dar    SET id_osoba=$id_orig WHERE id_osoba=$id_copy");
  // platba
  $platba= select("GROUP_CONCAT(id_platba)","platba","id_oso=$id_copy");
  query("UPDATE platba SET id_oso=$id_orig WHERE id_oso=$id_copy");
  // mail
  $mail= select("GROUP_CONCAT(id_mail)","mail","id_clen=$id_copy");
  query("UPDATE mail SET id_clen=$id_orig WHERE id_clen=$id_copy");
  // smazání kopie
  query("UPDATE osoba SET deleted='D osoba=$id_orig' WHERE id_osoba=$id_copy");
  // opravy v originálu
  $access_orig= select("access","osoba","id_osoba=$id_orig");
  $access_copy= select("access","osoba","id_osoba=$id_copy");
  $access= (int)$access_orig | (int)$access_copy;
  query("UPDATE osoba SET access=$access WHERE id_osoba=$id_orig");
  // zápis o ztotožnění osob do _track jako op=d (duplicita)
  $user= $USER->abbr;
  $info= "access:$access_orig|$access_copy;tvori:$tvori;spolu:$spolu;dar:$dar;platba:$platba;mail:$mail";
  query("INSERT INTO _track (kdy,kdo,kde,klic,fld,op,old,val)
         VALUES ('$now','$user','osoba',$id_orig,'osoba','d','$info',$id_copy)");    // d=duplicita
  // zápis o smazání kopie do _track jako op=x (eXtract)
  query("INSERT INTO _track (kdy,kdo,kde,klic,fld,op,old,val)
         VALUES ('$now','$user','osoba',$id_copy,'','x','smazaná kopie',$id_orig)");    // x=smazání
  // id pro nastavení v browse
  $ret->ido= $id_orig;
  // pokud je orig ve více rodinách navrhni další postup
  $idrs= array();
  $rr= pdo_qry("SELECT id_rodina FROM tvori AS ot JOIN tvori AS tr USING (id_rodina)
                  WHERE ot.id_osoba=$id_orig GROUP BY id_rodina ORDER BY COUNT(*) DESC");
  while ($rr && (list($idr)= pdo_fetch_row($rr)) ) {
    $idrs[]= $idr;
  }
  $nrod= count($idrs);
  if ( $nrod>2 ) { // více jak 2 rodiny
    $ret->msg= "osoba $id_orig se vyskytuje ve $nrod rodinách, to je zapotřebí po 'Zpět' pořešit!";
  }
  elseif ( $nrod==2 ) { // právě 2 rodiny
    $ret->msg= "osoba $id_orig se vyskytuje ve dvou rodinách, nabídnu nástroj k řešení";
    $ret->idr1= $idrs[0];
    $ret->idr2= $idrs[1];
  }
end:
//                                                        debug($ret,"elim2_osoba nrod=$nrod");
  return $ret;
}
# ----------------------------------------------------------------------------------==> . elim2_clen
# zamění všechny výskyty kopie za originál v TVORI, SPOLU, DAR, PLATBA, MAIL a kopii smaže
function elim2_clen($id_rodina,$id_orig,$id_copy) { trace();
  global $USER;
  user_test();
  $ret= (object)array('err'=>'');
  $now= date("Y-m-d H:i:s");
  // tvori - vymazat => do _track napsat id_rodina.role
  $tvori= select1("CONCAT(id_rodina,'.',role)","tvori", "id_rodina=$id_rodina AND id_osoba=$id_copy");
  query("DELETE FROM tvori WHERE id_rodina=$id_rodina AND id_osoba=$id_copy");
  // spolu
  $spolu= select("GROUP_CONCAT(id_spolu)","spolu","id_osoba=$id_copy");
  query("UPDATE spolu  SET id_osoba=$id_orig WHERE id_osoba=$id_copy");
  // dar
  $dar=   select("GROUP_CONCAT(id_dar)",  "dar",  "id_osoba=$id_copy");
  query("UPDATE dar    SET id_osoba=$id_orig WHERE id_osoba=$id_copy");
  // platba
  $platba= select("GROUP_CONCAT(id_platba)","platba","id_oso=$id_copy");
  query("UPDATE platba SET id_oso=$id_orig WHERE id_oso=$id_copy");
  // mail
  $mail= select("GROUP_CONCAT(id_mail)","mail","id_clen=$id_copy");
  query("UPDATE mail SET id_clen=$id_orig WHERE id_clen=$id_copy");
  // smazání kopie
  query("UPDATE osoba SET deleted='D osoba=$id_orig' WHERE id_osoba=$id_copy");
  // opravy v originálu
  $access_orig= select("access","osoba","id_osoba=$id_orig");
  $access_copy= select("access","osoba","id_osoba=$id_copy");
  $access= (int)$access_orig | (int)$access_copy;
  query("UPDATE osoba SET access=$access WHERE id_osoba=$id_orig");
  // zápis o ztotožnění osob do _track jako op=d (duplicita)
  $info= "access:$access_orig;xtvori:$tvori;spolu:$spolu;dar:$dar;platba:$platba;mail:$mail";
  $user= $USER->abbr;
  query("INSERT INTO _track (kdy,kdo,kde,klic,fld,op,old,val)
         VALUES ('$now','$user','osoba',$id_orig,'clen','d','$info',$id_copy)");
  // zápis o smazání kopie do _track jako op=x (eXtract)
  query("INSERT INTO _track (kdy,kdo,kde,klic,fld,op,old,val)
         VALUES ('$now','$user','osoba',$id_copy,'','x','smazaná kopie',$id_orig)");
end:
  return $ret;
}
# --------------------------------------------------------------------------------==> . elim2_rodina
# ASK
# zamění všechny výskyty kopie za originál v POBYT, TVORI, DAR, PLATBA a kopii smaže
function elim2_rodina($id_orig,$id_copy) {
  global $USER;
  user_test();
  $ret= (object)array('err'=>'');
  if ( $id_orig!=$id_copy ) {
    $now= date("Y-m-d H:i:s");
    // pobyt
    $pobyt= select("GROUP_CONCAT(id_pobyt)","pobyt", "i0_rodina=$id_copy");
    query("UPDATE pobyt  SET i0_rodina=$id_orig WHERE i0_rodina=$id_copy");
    // tvori
    $tvori= select("GROUP_CONCAT(id_tvori)","tvori", "id_rodina=$id_copy");
    query("UPDATE tvori  SET id_rodina=$id_orig WHERE id_rodina=$id_copy");
    // dar
    $dar=   select("GROUP_CONCAT(id_dar)",  "dar",  "id_rodina=$id_copy");
    query("UPDATE dar    SET id_rodina=$id_orig WHERE id_rodina=$id_copy");
//    // platba ... stará verze tabulky
//    $platba= select("GROUP_CONCAT(id_platba)","platba","id_rodina=$id_copy");
//    query("UPDATE platba SET id_rodina=$id_orig WHERE id_rodina=$id_copy");
    // smazání kopie
    query("UPDATE rodina SET deleted='D rodina=$id_orig' WHERE id_rodina=$id_copy");
    // opravy v originálu
    $access_orig= select("access","rodina","id_rodina=$id_orig");
    $access_copy= select("access","rodina","id_rodina=$id_copy");
    $access= (int)$access_orig | (int)$access_copy;
    query("UPDATE rodina SET access=$access WHERE id_rodina=$id_orig");
    // zápis o ztotožnění rodin do _track jako op=d (duplicita)
    $info= "access:$access_orig;pobyt:$pobyt;tvori:$tvori;dar:$dar;platba:$platba";
    $user= $USER->abbr;
    query("INSERT INTO _track (kdy,kdo,kde,klic,fld,op,old,val)
           VALUES ('$now','$user','rodina',$id_orig,'','d','$info',$id_copy)");
    // zápis o smazání kopie do _track jako op=x (eXtract)
    query("INSERT INTO _track (kdy,kdo,kde,klic,fld,op,old,val)
           VALUES ('$now','$user','rodina',$id_copy,'rodina','x','smazaná kopie',$id_orig)");
  }
  // odstranění duplicit v tabulce TVORI
  $qt= pdo_qry("
    SELECT COUNT(*) AS _n,GROUP_CONCAT(id_tvori ORDER BY id_tvori) AS _ids FROM tvori
    WHERE id_rodina=$id_orig GROUP BY id_osoba,role HAVING _n>1");
  while (($t= pdo_fetch_object($qt))) {
    $idts= explode(',',$t->_ids);
    for ($i= 1; $i<count($idts); $i++) {
      query("DELETE FROM tvori WHERE id_tvori={$idts[$i]}");
    }
  }
end:
  return $ret;
}
# ----------------------------------------------------------------------------- elim2_recovery_osoba
# obnoví smazanou osobu se záznamem v _track
function elim2_recovery_osoba($ido) { trace();
  global $USER;
  user_test();
  $deleted= select('deleted','osoba',"id_osoba=$ido");
  if ( $deleted ) {
    // obnovení
    query("UPDATE osoba SET deleted='' WHERE id_osoba=$ido");
    // zápis o obnovení smazaného záznamu op='r' (recovery)
    $now= date("Y-m-d H:i:s");
    query("INSERT INTO _track (kdy,kdo,kde,klic,fld,op,old,val)
           VALUES ('$now','{$USER->abbr}','osoba',$ido,'','r','','$deleted')");
  }
  return $deleted;
}
# ---------------------------------------------------------------------------- elim2_recovery_rodina
# obnoví smazanou rodinu se záznamem v _track
function elim2_recovery_rodina($idr) { trace();
  global $USER;
  user_test();
  $deleted= select('deleted','rodina',"id_rodina=$idr");
  if ( $deleted ) {
    // obnovení
    query("UPDATE rodina SET deleted='' WHERE id_rodina=$idr");
    // zápis o obnovení smazaného záznamu op='r' (recovery)
    $now= date("Y-m-d H:i:s");
    query("INSERT INTO _track (kdy,kdo,kde,klic,fld,op,old,val)
           VALUES ('$now','{$USER->abbr}','rodina',$idr,'','r','','$deleted')");
  }
  return $deleted;
}
# --------------------------------------------------------------------------------- elim2_data_osoba
# načte data OSOBA+TVORI včetně záznamů v _track
# cond může omezit čas barvení změn
function elim2_data_osoba($ido,$cond='') {  //trace();
  $ret= (object)array();
  // načtení změn
  $chng_kdy= $chng_kdo= $chng_val= array();
  $max_kdy= '';
  $AND_kdy= $cond ? "AND $cond" : '';
  $zs= pdo_qry("
    SELECT fld,kdo,kdy,val,op
    FROM _track
    WHERE kde='osoba' AND klic=$ido $AND_kdy
  ");
  while (($z= pdo_fetch_object($zs))) {
    $fld= $z->fld;
    $kdy= $z->kdy;
    $kdo= $z->kdo;
    $op=  $z->op;
    $val= $z->val;
    $max_kdy= max($max_kdy,substr($kdy,0,10));
    if ( !isset($chng_kdy[$fld]) || isset($chng_kdy[$fld]) && strcmp($chng_kdy[$fld],$kdy)<0 ) {
      $chng_kdy[$fld]= $kdy;
      $chng_kdo[$fld]= "$kdo/$op: ".sql_date1($kdy);
      $chng_val[$fld]= $val;
    }
  }
  $ret->last_chng= $max_kdy;
  $ret->chng= $chng_kdo;
  // načtení hodnot
  $os= pdo_qry("
    SELECT MAX(CONCAT(datum_od,':',a.nazev)) AS _last,
      prijmeni,jmeno,sex,narozeni,rc_xxxx,psc,obec,ulice,email,telefon,o.note
    FROM osoba AS o
    LEFT JOIN spolu AS s USING(id_osoba)
    LEFT JOIN pobyt AS p USING(id_pobyt)
    LEFT JOIN akce AS a ON p.id_akce=a.id_duakce
    WHERE id_osoba=$ido GROUP BY id_osoba
  ");
  $o= pdo_fetch_object($os);
  foreach($o as $fld=>$val) {
    if ( $chng_kdy[$fld] && $chng_val[$fld]!=$val ) {
      $ret->diff[$fld]= $chng_val[$fld];
      $ret->chng[$fld]= "!{$ret->chng[$fld]}: {$chng_val[$fld]}";
    }
  }
  $ret->last_akce= $o->_last;
  // zjištění kmenové rodiny
  $kmen= ''; $idk= 0;
  $rs= pdo_qry("
    SELECT id_rodina,role,nazev
    FROM osoba AS o
    LEFT JOIN tvori AS t USING(id_osoba)
    LEFT JOIN rodina AS r USING(id_rodina)
    WHERE id_osoba=$ido
  ");
  while (($r= pdo_fetch_object($rs))) {
    if ( !$kmen || $r->role=='a' || $r->role=='b' ) {
      $kmen= $r->nazev;
      $idk= $r->id_rodina;
    }
  }
  $ret->kmen= $kmen;
  $ret->id_kmen= $idk;
//                                                         debug($ret,"elim_data_osoba");
  return $ret;
}
# -------------------------------------------------------------------------------- elim2_data_rodina
# načte data RODINA včetně záznamů v _track
# cond může omezit čas barvení změn
function elim2_data_rodina($idr,$cond='') {  //trace();
  $ret= (object)array();
  // načtení změn
  $chng_kdy= $chng_kdo= $chng_val= array();
  $max_kdy= '';
  $AND_kdy= $cond ? "AND $cond" : '';
  $zs= pdo_qry("
    SELECT fld,kdo,kdy,val,op
    FROM _track
    WHERE kde='rodina' AND klic=$idr $AND_kdy
  ");
  while (($z= pdo_fetch_object($zs))) {
    $fld= $z->fld;
    $kdy= $z->kdy;
    $kdo= $z->kdo;
    $op=  $z->op;
    $val= $z->val;
    $max_kdy= max($max_kdy,substr($kdy,0,10));
    if ( !isset($chng_kdy[$fld]) || isset($chng_kdy[$fld]) && strcmp($chng_kdy[$fld],$kdy)<0 ) {
      $chng_kdy[$fld]= $kdy;
      $chng_kdo[$fld]= "$kdo/$op: ".sql_date1($kdy);
      $chng_val[$fld]= $val;
    }
  }
  $ret->last_chng= sql_date1($max_kdy);
  $ret->chng= $chng_kdo;
  // načtení hodnot
  $os= pdo_qry("
    SELECT r.*, MAX(CONCAT(datum_od,': ',a.nazev)) AS _last
    FROM rodina AS r
    LEFT JOIN tvori AS t USING (id_rodina)
    LEFT JOIN spolu AS s USING (id_osoba)
    LEFT JOIN pobyt AS p USING (id_pobyt)
    LEFT JOIN akce AS a ON id_akce=id_duakce
    WHERE id_rodina=$idr
    GROUP BY id_rodina
  ");
  $o= pdo_fetch_object($os);
  foreach($o as $fld=>$val) {
    $ret->$fld= $val;
    if ( $chng_kdy[$fld] && $chng_val[$fld]!=$val ) {
      $ret->diff[$fld]= $chng_val[$fld];
      $ret->chng[$fld]= "!{$ret->chng[$fld]}: {$chng_val[$fld]}";
    }
  }
  $ret->datsvatba= sql_date1($ret->datsvatba);                  // svatba d.m.r
  $ret->last_akce= sql_date1(substr($o->_last,0,10)).substr($o->_last,10);
//                                                         debug($ret,"elim_data_rodina");
  return $ret;
}
