<?php

# -------------------------------------------------------------------------------- fio platba_pobytu
# 
function fio_platba_pobytu($id_platba,$to_save=0) {
  $ret= (object)['ida'=>0,'warn'=>''];
  list($idp,$castka,$datum)= select('id_pob,castka,datum','platba',"id_platba=$id_platba");
  list($ret->ida,$ma_cenik)= select('id_akce,ma_cenik',
      'pobyt JOIN akce ON id_akce=id_duakce',"id_pobyt=$idp");
  if ($ma_cenik==2) {
    $ret->ida= 0;
  }
  elseif ($to_save) {
    if (select('COUNT(*)','uhrada',"id_pobyt=$idp AND u_castka=$castka AND u_datum='$datum' ")) {
      $ret->warn= "tato platba je již u pobytu zapsána";
    }
    else {
      $n= select1('IFNULL(MAX(u_poradi),-1)','uhrada',"id_pobyt=$idp");
      $n++;
      query("INSERT INTO uhrada (id_pobyt,u_castka,u_datum,u_zpusob,u_stav,u_poradi) 
          VALUE ($idp,$castka,'$datum',2,2,$n)");
    }
  }
  return $ret;
}
# ------------------------------------------------------------------------------- ds2 fio_filtr_akce
# vytvoření filtru pro výběr plateb podle SS, SS2
# a vrácení nalezené platby k id_platba
function ds2_fio_filtr_akce($id_pobyt) {
  $days_plus= 10; $days_minus= 30;
  list($kod,$ida,$od,$do)= select('ciselnik_akce,id_akce,datum_od,datum_do',
      "pobyt JOIN akce ON id_akce=id_duakce",
      "id_pobyt=$id_pobyt");
//g  list($kod,$ida,$od,$do)= select('g_kod,id_akce,datum_od,datum_do',
//g      "pobyt JOIN akce ON id_akce=id_duakce LEFT JOIN join_akce USING (id_akce)",
//g      "id_pobyt=$id_pobyt");
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
  $evi= $ucast= $dum= $fak= '';
  $a_jmeno= $e_jmeno= $a_akce= $d_jmeno= '';
  // akce
  if (($ida= $c->lst->akce)) {
    list($a_akce,$kod)= select("CONCAT(nazev,' ',YEAR(datum_od)),ciselnik_akce",
        'akce',"id_duakce=$ida");
//g    list($a_akce,$kod)= select("CONCAT(nazev,' ',YEAR(datum_od)),IFNULL(g_kod,'')",
//g        'akce LEFT JOIN join_akce ON id_akce=id_duakce',"id_duakce=$ida");
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
  // Dům setkání - objednávky pod AKCE
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
  }
  // Dům setkání - faktura
  if (($idf= $c->dum->faktura)) {
    list($nazev,$typ,$castka,$zaloha)= select('nazev,typ,castka,zaloha','faktura',"id_faktura=$idf");
    $fak.= " faktura $nazev";
    $doplatek= $castka - $zaloha;
    $fak_text= $typ==1
        ? "záloha $zaloha Kč" : ( 
          $zaloha>0 ? "doplatek $doplatek Kč (zálohově $zaloha Kč)" : "částka $castka Kč");
  }
  $ret= (object)['evi'=>$evi,'evi_text'=>$e_jmeno,
      'ucast'=>$ucast,'ucast_text'=>"$a_akce, $a_jmeno",
      'dum'=>$dum,'dum_text'=>"$d_jmeno",'dum_mmyyyy'=>$mmyyyy,
      'fak'=>$fak,'fak_text'=>$fak_text];
//  debug($ret);
  return $ret;
}
# ------------------------------------------------------------------------------------------ ds2 fio
# zapsání informace do platby
#    pobyt - c=id_pobyt
function ds2_corr_platba($id_platba,$typ,$on,$c=null) {
  $stav= select('stav','platba',"id_platba=$id_platba");
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
      if (in_array($stav,[5,10]))
        query_track("UPDATE platba SET stav=11 WHERE id_platba=$id_platba");
      break;
    case 'auto':
      if (in_array($stav,[1,6,8,10]))
        query_track("UPDATE platba SET stav=stav+1 WHERE id_platba=$id_platba");
      break;
    case 'akce':
      if ($c->ucast->osoba && $c->ucast->pobyt)
        query_track("UPDATE platba SET id_oso={$c->ucast->osoba},id_pob={$c->ucast->pobyt}, stav=6
        WHERE id_platba=$id_platba");
      break;
    case 'evi':
      if ($c->evi->osoba)
        query_track("UPDATE platba SET id_oso={$c->evi->osoba}, stav=7 WHERE id_platba=$id_platba");
      break;
    case 'order':
      if ($c->dum->order)
        query_track("UPDATE platba SET id_ord={$c->dum->order}, stav=8 WHERE id_platba=$id_platba");
      break;
    case 'faktura':
      $idf= $c->dum->faktura;
      if ($idf) {
        $idjp= select('id_join_platba','join_platba',"id_platba=$id_platba");
        if ($idjp) {
          query_track("UPDATE join_platba SET id_faktura=$idf WHERE id_join_platba=$idjp");
        }
        else { // nebylo propojení
          query_track("INSERT INTO join_platba (id_platba,id_faktura) VALUE ($id_platba,$idf)");
        }
      }
      break;
  }
}
# ------------------------------------------------------------------------------------------ ds2 fio
# spolupráce s FIO bankou
function ds2_fio($cmd) {
  global $api_fio_ds, $api_fio_ys;
  $y= (object)['html'=>$cmd->fce,'err'=>''];
//                                                    debug($cmd,'ds2_fio'); 
//                                                    return $y;
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
//      $url= "https://www.fio.cz/ib_api/rest/periods/$token/$od/$do/transactions.$format";
      $url= "https://fioapi.fio.cz/ib_api/rest/periods/$token/$od/$do/transactions.$format";
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
      $cmd_od= ($cmd->od??'*')=='*' ? date('Y').'-01-01' : $cmd->od;
      $cmd_do= ($cmd->do??'*')=='*' ? date('Y').'-12-31' : $cmd->do;
      $omezeni= $cmd->platba
          ? "id_platba=$cmd->platba" 
          : "datum BETWEEN '$cmd_od' AND '$cmd_do'";
      $back= ($cmd->back??0) ?: 0; // návrat k odhadu =  ignoruje id_oso, id_pob, id_ord
      if ($back && ($cmd->platba??0)) {
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
          query_track("UPDATE platba SET id_oso=$ido WHERE id_platba=$id_platba");
          $nu++;
        }
      }
      // platby za akce YS + DS
      $rp= pdo_qry("
        SELECT id_platba,id_osoba,id_pobyt,id_oso
        FROM platba AS p
        -- //g JOIN join_akce AS ja ON ja.g_kod=IF(p.ss2,p.ss2,p.ss) AND YEAR(p.datum)=g_rok
        -- //g JOIN akce AS a ON ja.id_akce=id_duakce
        JOIN akce AS a ON ciselnik_akce=IF(p.ss2,p.ss2,p.ss) AND YEAR(p.datum)=YEAR(datum_od)
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
        query_track("UPDATE platba SET ".($o ? "id_oso=$ido," : '')." id_pob=$idp, stav=6 
          WHERE id_platba=$id_platba");
        $na++;
      }
      // platby za faktury vydané DS
      if ($cmd->fce=='join-ds') {
        $rf= pdo_qry("
          SELECT /* ------------------------------------------------ */
            id_platba,id_faktura,id_order,id_pobyt
          FROM platba AS p 
          JOIN faktura AS f ON f.vs=p.vs AND f.ss=IF(p.ss2,p.ss2,p.ss) AND f.deleted=''
          LEFT JOIN join_platba AS j USING (id_platba,id_faktura)
          WHERE ucet=2 AND ISNULL(j.id_faktura) AND p.castka IN (f.castka,f.zaloha) AND typ!=2
            AND $omezeni 
            AND datum BETWEEN vystavena AND DATE_ADD(vystavena, INTERVAL 2 MONTH)");
        while ($rf && (list($idp,$idf,$ido,$idpbt)= pdo_fetch_array($rf))) {
          query_track("INSERT INTO join_platba (id_platba,id_faktura) VALUE ($idp,$idf)");
          $pobyt= $idpbt ? ",id_pob=$idpbt" : '';
          query_track("UPDATE platba SET id_ord=$ido,stav=8$pobyt WHERE id_platba=$idp");
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
          query_track("UPDATE platba SET id_oso=$ido WHERE id_platba=$id_platba");
          $nv++;
        }
        if ($ido && $dar) {
          query_track("UPDATE platba SET stav=10 WHERE id_platba=$id_platba");
          $nd++;
        }
      }
      $y->html= "Rozpoznáno $na plateb za akce, $nd darů, $nu osob podle účtu, "
          . "$nv podle VS a jména, $nf podle faktury";
      break; // přiřazení plateb
  }
end:  
  return $y;
}
