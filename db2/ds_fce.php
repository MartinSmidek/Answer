<?php # (c) 2009-2015 Martin Smidek <martin@smidek.eu>
/** ==================================================================================> TRANSFORMACE **/
# transformace DS do Answer
define('org_ds',64);
# ------------------------------------------------------------------------------------------- ds2 kc
function ds2_trnsf_osoba($par) {
  global $setkani_db, $ezer_root;
  $org_ds= org_ds;
  $html= '';
  switch ($par->mode) {
    // ========================================================================== automatické úpravy
    case 'final':             // --------------------------------------------- trvalý zápis změn (x)
      $n= 0;
      // zapiš údaje ze spojky do ds_osoba
      $ro= pdo_qry("SELECT id_osoba,ds_osoba FROM ds_db");
      while ($ro && (list($ido,$idh)= pdo_fetch_array($ro))) {  
        $n++;
        query("UPDATE $setkani_db.ds_osoba SET ys_osoba=$ido WHERE id_osoba=$idh");
      }
      // zruš spojky
      query("TRUNCATE TABLE ds_db");
      // oprav autoincrement tabulek pokud došlo k mazání přidaných
      foreach (['pobyt','spolu','osoba'] as $tab) {
        $max_id= select("MAX(id_$tab)",$tab,'1') + 1;
        query("ALTER TABLE $tab AUTO_INCREMENT=$max_id");
      }
      $html.= "Byla dokončena transformace dat Domu setkání"
          . "<br> V ds_osoba bylo přímo zapsána vazba na $n osob."
          . "<br>byly vráceny hodnoty autoincrement za poslední";
      break;
    case 'backtrack':         // -------------------------------------------------- vrácení změn (x)
      $nd= $nu= $na= $np= $ns= 0;
      // oprav nebo vymaž vše přidané v osobách
      $ro= pdo_qry("SELECT id_osoba,access FROM osoba WHERE access&$org_ds");
      while ($ro && (list($ido,$acs)= pdo_fetch_array($ro))) {  
        if ($acs==$org_ds) {
          query("DELETE FROM osoba WHERE id_osoba=$ido");
          $nd++;
        }
        else {
          query("UPDATE osoba SET access=access-$org_ds WHERE id_osoba=$ido");
          $nu++;
        }
      }
      // také přidané akce a jejich pobyty vč. spolu
      $ra= pdo_qry("SELECT id_duakce FROM akce WHERE access=$org_ds");
      while ($ra && (list($ida)= pdo_fetch_array($ra))) {  
        $na++;
        $rp= pdo_qry("SELECT id_pobyt FROM pobyt WHERE id_akce=$ida");
        while ($rp && (list($idp)= pdo_fetch_array($rp))) {  
          $np++;
          $ns+= query("DELETE FROM spolu WHERE id_pobyt=$idp");
          query("DELETE FROM pobyt WHERE id_pobyt=$idp");
        }
        query("DELETE FROM akce WHERE id_duakce=$ida");
      }
      // zruš spojky
      query("TRUNCATE TABLE ds_db");
      // zkrať _track
      $last_track= $_SESSION[$ezer_root]['last_track'] ?? 999999;
      query("DELETE FROM _track WHERE id_track>$last_track");
      // oprav autoincrement tabulek pokud došlo k mazání přidaných
      foreach (['pobyt','spolu','osoba','akce','pobyt','spolu','_track'] as $tab) {
        $id= $tab=='akce' ? 'id_duakce' : ($tab[0]=='_' ? "id$tab" : "id_$tab");
        $max_id= select("MAX($id)",$tab,'1') + 1;
        query("ALTER TABLE $tab AUTO_INCREMENT=$max_id");
      }
      $html.= "Byly vráceny tyto změny:<br>"
          . "<br> - osoba: bylo zrušeno $nu doplněných access a $nd osoba s access=$org_ds bylo vymazáno"
          . "<br> - akce: bylo vymazáno $na doplněných akcí, $np pobytů a $ns spolu´častí"
          . "<br><br>byly vráceny hodnoty autoincrement za poslední";
      break;
    case 'osoba_clr':         // ------------------------- pročištění ds_osoba a zápis last_track(1) 
      $_SESSION[$ezer_root]['last_track']= select("MAX(id_track)",'_track','1');
      $n= 0;
      $idos= '';
      // zrušíme ys_osoba podkud vede na smazanou osobu
      $ro= pdo_qry("
        SELECT o.id_osoba,d.id_osoba 
        FROM $setkani_db.ds_osoba AS d 
        LEFT JOIN osoba AS o ON o.id_osoba=ys_osoba
        WHERE ys_osoba>0 AND (ISNULL(o.deleted) OR o.deleted!='')
        ORDER BY ys_osoba
      ");
      while ($ro && (list($ido,$idh)= pdo_fetch_array($ro))) {  
        $idos.= "$ido ";
        $n++;
        query("UPDATE $setkani_db.ds_osoba SET ys_osoba=0 WHERE id_osoba=$idh");
      }
      $html.= "<br>V ds_osoba bylo zrušeno $n vazeb na smazané osoby v DB: <br><br>$idos";
      break;
    case 'ds_db':         // ------------------------------------- vytvoření spojovacích záznamů (2)
      $n= 0;
      $rd= pdo_qry("
        SELECT MIN(o.id_osoba) AS _id_osoba,d.id_osoba,
          MAX(TRIM(prijmeni)),MAX(TRIM(jmeno)),MAX(narozeni)
        FROM $setkani_db.ds_osoba AS d
        JOIN osoba AS o USING (prijmeni,jmeno,narozeni)
        WHERE deleted='' AND d.narozeni!='0000-00-00' AND d.id_osoba>0
        GROUP BY prijmeni,jmeno,narozeni,d.id_osoba
        HAVING d.jmeno!='' AND d.prijmeni!=''
        ORDER BY prijmeni
--        LIMIT 20  
      ");
      while ($rd && (list($ido,$ydo,$prijmeni,$jmeno,$narozeni)= pdo_fetch_array($rd))) {
        $n++; 
        query("INSERT INTO ds_db (ds_osoba,id_osoba,prijmeni,jmeno,narozeni) 
          VALUE ($ydo,$ido,'$prijmeni','$jmeno','$narozeni')");
      }
      $html.= "tabulka ds_db obsahuje $n záznamů propojující osoby YS+FA s DS mající shodu prijmeni+jmeno+narozeni";
      break;
    case 'osoba_upd':         // ---------------------------------------- propojení existujících (3)
      $n= $nd= 0;
      // upravíme existující, pokud není definováno ys_osoba
      $ro= pdo_qry("SELECT id_osoba,ds_osoba FROM ds_db GROUP BY id_osoba");
      while ($ro && (list($ido,$idh)= pdo_fetch_array($ro))) {  
        $n++;
        $access= select('access','osoba',"id_osoba=$ido ");
        $access|= $org_ds;
        query_track("UPDATE osoba SET access=$access WHERE id_osoba=$ido");
      }
      $html.= "V ezer_db2 bylo změněno $n osob (access|=$org_ds) a propojeno s ds_osoba přes ds_db.";
      break;
    case 'osoba_new':         // ---------------------- přidání neexistujících a doplnění spojky (4)
      $od_roku= $par->from ?: 2023;
      // obrana proti opakovanému vytvoření
      $n= select('COUNT(*)','osoba',"access=$org_ds");
      if ($n>0) {
        $html.= "POZOR patrně již byl tento krok proveden a nevrácen !!!";
        break;
      }
      $n= 0;
      // vytvoříme nové, pokud není definováno ys_osoba
      $ro= pdo_qry("
          SELECT GROUP_CONCAT(d.id_osoba) AS idhs,TRIM(d.prijmeni),TRIM(d.jmeno),d.narozeni,
            d.ulice,d.psc,d.obec,d.telefon,d.email,
          MAX(id_order) AS _obj,MAX(YEAR(FROM_UNIXTIME(obj.fromday))) AS _last
          FROM $setkani_db.ds_osoba AS d
          LEFT JOIN ds_db AS x ON x.ds_osoba=d.id_osoba
          JOIN $setkani_db.tx_gnalberice_order AS obj ON obj.uid=d.id_order
          WHERE ISNULL(x.id_osoba) AND d.id_osoba>0
          GROUP BY TRIM(d.prijmeni),TRIM(d.jmeno),narozeni HAVING _last>=$od_roku
          ORDER BY _obj DESC
      ");
      while ($ro && (list($idhs,$prijmeni,$jmeno,$narozeni,$ulice,$psc,$obec,$telefon,$email)
          = pdo_fetch_array($ro))) {  
        $n++;
        $sex= select('sex','_jmena',"jmeno='$jmeno' LIMIT 1");
//        display("$jmeno:$sex");
        $sex= $sex==1 || $sex==2 ? $sex : 0;
        $ido= query_track("INSERT INTO osoba (access,prijmeni,jmeno,narozeni,sex,adresa,ulice,psc,obec,kontakt,telefon,email) "
            . "VALUE ($org_ds,'$prijmeni','$jmeno','$narozeni',$sex,1,'$ulice','$psc','$obec',1,'$telefon','$email')");
        foreach (explode(',',$idhs) as $idh) {
          query("INSERT INTO ds_db (ds_osoba,id_osoba,prijmeni,jmeno,narozeni) 
            VALUE ($idh,$ido,'$prijmeni','$jmeno','$narozeni')");
          $nd++;
        }
      }
      $html.= "Do ezer_db2 bylo přidáno $n osob (access=$org_ds) a propojeno přes ds_db s ds_osoba "
          . "s posledním pobytem od roku $od_roku";
      break;
    case 'pobyty':         // ---------------------------- vytvoření struktury pobyt-spolu-osoba (5)
      $od_roku= $par->from ?: 2024;
      $no= $na= $np= $ns= 0;
      $err= '';
      $ro= pdo_qry("
        SELECT COUNT(*),akce, 
          GROUP_CONCAT(uid ORDER BY uid) AS _uids,
          GROUP_CONCAT(state ORDER BY uid) AS _states,
          GROUP_CONCAT(CONCAT('\"',note,'\"') ORDER BY uid) AS _notes,
          DATE(FROM_UNIXTIME(fromday)) AS _from, DATE(FROM_UNIXTIME(untilday)) AS _to
        FROM $setkani_db.tx_gnalberice_order
        WHERE deleted=0 AND fromday>0 AND YEAR(FROM_UNIXTIME(fromday))>=$od_roku
        --  AND MONTH(FROM_UNIXTIME(fromday))=2
        --  AND fromday=1706227200 AND untilday=1706400000
        GROUP BY _from,_to,akce
        ORDER BY _from DESC
      ");
      while ($ro && (list($nos,$kod,$idfs,$states,$notes,$od,$do)= pdo_fetch_array($ro))) {  
        $rok= substr($od,0,4);
        $no++;
        if ($kod) {
          if ($nos!=1) {
            $err.= "<br>$nos akcí YMCA v jednom termínu? Objednávky $idfs '$notes', kód akce $kod roku $rok";
            $kod= 0;
          }
          // objednávka je fakticky typu "akce YMCA" a je jen jedna 
          list($ida,$access)= select('id_akce,access',
              "join_akce JOIN akce ON id_duakce=id_akce","g_kod=$kod AND g_rok=$rok");
          if (!$ida) {
            $err.= "<br>objednávka $idfs '$notes' směřuje na divný kód akce $kod roku $rok";
            $kod= 0;
          }
          else {
            $na++;
//            $access|= $org_ds;
//            query_track("UPDATE akce SET id_order=$idfs,access=$access WHERE id_duakce=$ida");
            query("UPDATE $setkani_db.tx_gnalberice_order SET id_akce=$ida WHERE uid=$idfs");
            query("UPDATE pobyt SET id_order=$idfs WHERE id_akce=$ida");
          }
        }
        if (!$kod) {
          // z $nos objednávek ve stejnou dobu NEuděláme jednu akci 
          $ida= query_track("INSERT INTO akce (access,note,druh,nazev,misto,datum_od,datum_do,ma_cenik) "
              . "VALUE ($org_ds,'$idfs',64,'Objednávky: $idfs','Dům setkání','$od','$do',2)");
          display("!!! AKCE=$ida:$idfs");
          // obsahující všechny pobyty
          $_idfs= explode(',',$idfs);
          $_states= explode(',',$states);
          $_notes= explode_csv($notes);
          for ($i= 0; $i<$nos; $i++) {
            $idf= $_idfs[$i];
            // svážeme objednávku s akcí
            query("UPDATE $setkani_db.tx_gnalberice_order SET id_akce=$ida WHERE uid=$idf");
            $state= $_states[$i];
            $note= $_notes[$i];
            // objednávka je fakticky pobytová ale vytvoříme záznam v tabulce akce
            $rp= pdo_qry("
              SELECT rodina,GROUP_CONCAT(DISTINCT pokoj ORDER BY pokoj)
              FROM $setkani_db.ds_osoba 
              WHERE id_order=$idf 
              GROUP BY rodina ORDER BY rodina");
            while ($rp && (list($rod,$pokoje)= pdo_fetch_array($rp))) {  
              // pro každou "rodinu" přidáme pobyt, pokud má aspoň jednodho hosta
              $n= select('COUNT(*)',"$setkani_db.ds_osoba","id_order=$idf AND rodina='$rod'");
              if (!$n) continue;
              $idp= query_track("INSERT INTO pobyt (id_akce,id_order,funkce,pracovni,pokoj)"
                . " VALUE ($ida,$idf,7,'objednávka:$idf, rodina=$rod, stav=$state: $note','$pokoje')");
              display("!! POBYT=$idp/$ida: -$rod- $note");
              $np++;
              $rs= pdo_qry("
                SELECT ds_db.id_osoba,ds_osoba,pokoj 
                FROM $setkani_db.ds_osoba AS d
                  JOIN ds_db ON ds_db.ds_osoba=d.id_osoba
                WHERE id_order=$idf AND rodina='$rod'
              ");
              while ($rs && (list($ido,$idds,$pokoj)= pdo_fetch_array($rs))) {  
                // přidáme hosty
                $ids= query_track("INSERT INTO spolu (id_pobyt,id_osoba,pokoj) 
                  VALUE ($idp,$ido,'$pokoj')");
                query("UPDATE ds_db SET id_spolu=$ids WHERE id_osoba=$ido AND ds_osoba=$idds");
                $ns++;
              }
            }
          }
        }
      }
      $html.= "Do ezer_db2 bylo přidáno $no objednávek jako akce s $np pobyty $ns osob ($na akcí už v databázi je)
        - převedeny objednávky v Domě setkání od roku $od_roku";
      if ($err) 
        $html.= "<hr>$err";
      break;
    case 'ds_vzorec':
      global $ds2_cena, $setkani_db;
      $od_roku= $par->from ?: 2024;
      $rok_ceniku= 0;
      $ds_strava= map_cis('ds_strava','zkratka');
      // přepočet kategorie pokoje na typ ubytování v ceníku    
      $luzko_pokoje= ds2_cat_typ();
      // defaultní parametrizace SPOLU
      $rs= pdo_qry("
        SELECT id_spolu,narozeni,IF(s.pokoj,s.pokoj,p.pokoj),board,state,
          DATEDIFF(FROM_UNIXTIME(untilday),FROM_UNIXTIME(fromday)),
          DATE(FROM_UNIXTIME(fromday)),DATE(FROM_UNIXTIME(untilday)),YEAR(FROM_UNIXTIME(fromday))
        FROM spolu AS s 
          JOIN pobyt AS p USING (id_pobyt)
          JOIN osoba AS o USING (id_osoba)
          JOIN $setkani_db.tx_gnalberice_order AS d ON uid=p.id_order
        WHERE YEAR(FROM_UNIXTIME(fromday))>=$od_roku
          -- AND uid=2477  ");
      while ($rs && (
          list($ids,$narozeni,$pokoj,$board,$state,$noci,$od,$do,$rok)= pdo_fetch_array($rs))) {
        if ($rok!=$rok_ceniku) {
          $rok_ceniku= $rok;
          ds2_cenik($rok_ceniku);
        }
        $vek= roku_k($narozeni,$od);
//        $_od= str_replace(".$rok",'',sql_date1($od));
//        $_do= str_replace(".$rok",'',sql_date1($do));
        $vzorec= [];
        // strava
        $strava= 'strava_'.$ds_strava[$board];
        if ($vek>=$ds2_cena[$strava.'D']->od && $vek<$ds2_cena[$strava.'D']->do) {
          $vzorec[]= "{$strava}D:$noci";
        }
        if ($vek>=$ds2_cena[$strava.'C']->od && $vek<$ds2_cena[$strava.'C']->do) {
          $vzorec[]= "{$strava}C:$noci";
        }
        // ubytování a poplatky 
        list($pokoj)= explode(',',$pokoj);
        $noc= 'noc_'.$luzko_pokoje[$pokoj];
        $poplatky= ['ubyt_P','ubyt_C','ubyt_S',$noc];
        if ($state==3) { // akce YMCA má poplatek za program
          array_push($poplatky,'prog_C','prog_P');
        }
        foreach ($poplatky as $x) {
//          debug($ds2_cena[$x],"pokoj $pokoj,$x");
          if ($vek>=$ds2_cena[$x]->od && $vek<$ds2_cena[$x]->do && $ds2_cena[$x]->cena) {
            $vzorec[]= "$x:$noci";
          }
        }
        // redakce
        array_push($vzorec,"od:$od","do:$do");
        $vzorec= implode(',',$vzorec);
        query("UPDATE spolu SET ds_vzorec='$vzorec' WHERE id_spolu=$ids");
      }
//                                                 debug($luzko_pokoje,"ds2_cat_typ()");
//                                                 debug($ds2_cena,"ds2_cenik($rok_ceniku)");
      // přenesení do POBYT
      // přenesení do AKCE, pokud má zapnutou souhrnou fakturaci
      break;
    // =============================================================================== ruční úparvy
    case 'ds_osoba':      // ------------------------------------------------ převod ds_osoba->osoba
    case 'ds_osoba_plus': // převod ds_osoba->osoba včetně uvážení telefonu a mailu
      $n= $nys= $nx= $nx0= $nh= $nh_t= $nh_t1= 0;
      $pairs= $telfs= $idos= '';
      $rd= pdo_qry("
        SELECT d.id_osoba,MIN(IFNULL(o.id_osoba,0)) AS _id_osoba,d.ys_osoba,
          TRIM(d.prijmeni),TRIM(d.jmeno),TRIM(d.ulice),TRIM(d.psc),TRIM(d.obec),d.narozeni,
          GROUP_CONCAT(DISTINCT REPLACE(d.telefon,' ','')) AS _telefon,
          GROUP_CONCAT(DISTINCT TRIM(d.email)) AS _email,
          COUNT(d.id_osoba) AS _num_ds,COUNT(DISTINCT o.id_osoba) _num_ys
        FROM $setkani_db.ds_osoba AS d
        LEFT JOIN osoba AS o USING (prijmeni,jmeno,narozeni)
        LEFT JOIN ds_db ON ds_db.ds_osoba=d.id_osoba
        WHERE ISNULL(ds_db.ds_osoba) AND IFNULL(o.deleted,'')='' AND d.narozeni!='0000-00-00'
        GROUP BY d.prijmeni,d.jmeno,d.narozeni
-- HAVING _telefon!='' AND _id_osoba=0
-- HAVING _email!='' AND _id_osoba=0
        ORDER BY d.prijmeni
--        LIMIT 20  
      ");
      while ($rd && (list($dido,$ido,$ydo,$prijmeni,$jmeno,$ulice,$psc,$obec,,$telefon,$email)
          = pdo_fetch_array($rd))) {
        $n++; 
        if ($ido) $nys++;
        if ($ydo && $ido!=$ydo) { 
          $o= db2_osobni_udaje($ido);
          if (!$o->id_osoba) {
            $idos.= "<br>{$dido}D: $prijmeni $jmeno, $ulice, $psc $obec "
                . "| {$ido}Y: $o->prijmeni $o->jmeno, $o->ulice, $o->psc $o->obec";
            $nx0++;
          }
          else {
            $nx++;
            $pairs.= "<br>{$ydo}D: $prijmeni $jmeno, $ulice, $psc $obec "
                . "| {$ido}Y: $o->prijmeni $o->jmeno, $o->ulice, $o->psc $o->obec";
          }
        }
        if ($par->mode!='ds_osoba_plus') continue;
        $br= '';
        if (!$ydo && !$ido) {
          $nh++;
          if ($telefon) {
            $nh_t++;
            $ds_telefony= preg_split("/[,;]/",$telefon);
            foreach ($ds_telefony as $ds_t) {
              $ds_t= trim($ds_t);
              if ($ds_t=='') continue;
              $o= db2_jen_osobni_udaje(0,"telefon LIKE '%{$ds_t}%'");
              if ($o->id_osoba) {
                $nh_t1++;
                $vic= $o->n>1 ? "<b style='color:red'>$o->n</b>" : '';
                $telfs.= "<br>$vic{$dido}D: $prijmeni $jmeno, $ulice, $psc $obec -- $telefon"
                    . "| {$o->id_osoba}Y: $o->prijmeni $o->jmeno, $o->ulice, $o->psc $o->obec -- $o->telefon";
                $br= '<br>';
              }
            }
          }
          if ($email) {
            $nh_t++;
            $ds_emaily= preg_split("/[,;]/",$email);
            foreach ($ds_emaily as $ds_e) {
              $ds_e= trim($ds_e);
              if ($ds_e=='') continue;
              $o= db2_jen_osobni_udaje(0,"email LIKE '%{$ds_e}%'");
              if ($o->id_osoba) {
                $nh_t1++;
                debug($o,$prijmeni);
                $vic= $o->n > 1 ? "<b style='color:red'> $o->n </b>" : '';
                $telfs.= "<br>$vic{$dido}D: $prijmeni $jmeno, $ulice, $psc $obec -- $email"
                    . "| {$o->id_osoba}Y: $o->prijmeni $o->jmeno, $o->ulice, $o->psc $o->obec -- $o->email";
                $br= '<br>';
              }
            }
          }
          $telfs.= $br;
        }
      }
      $html.= "projdeno $n záznamů se stejným prijmeni+jmeno+narozeni z ds_osoba"
          . "<br><br>z nich je $nys v ezer_db2.osoba"
          . "<br><br>a z nich je $nx0 divných:<br>$idos "
          . "<br><br>a $nx podezřelých, jsou to:<br>$pairs";
      if ($par->mode!='ds_osoba_plus') {
        $html.= "<br><br>ze zbytku $nh hostí jich "
        . "$nh_t má v DS telefon z nichž $nh_t1 známe jako:<br>$telfs";
      }
      break;
  }
  return $html;
}
function TEST() {
  $qry= "INSERT INTO osoba (access,prijmeni,jmeno,narozeni,sex,
            adresa,ulice,psc,obec,kontakt,telefon,email)
          VALUE (64,'Randová','Eliška','2009-08-04',2,
            1,'Východní 1185','0','Mladá Boleslav',1,'','')";
  return query_track($qry);
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
  debug($m);
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
# --------------------------------------------------------------------------------- db2 osobni_udaje
function db2_osobni_udaje($ido,$or_cond='') {
  $os= (object)[];
  $n= 0;
  $cond= $or_cond ? '' : "AND id_osoba=$ido";
  $HAVING= $or_cond ? "HAVING $or_cond" : '';
  $rp= pdo_qry("
    SELECT id_osoba,TRIM(prijmeni) AS prijmeni,TRIM(jmeno) AS jmeno,narozeni,
      TRIM(IF(adresa,o.ulice,r.ulice)) AS ulice,
      TRIM(IF(adresa,o.psc,r.psc)) AS psc, 
      TRIM(IF(adresa,o.obec,r.obec)) AS obec,
      TRIM(IF(kontakt,o.email,r.emaily)) AS email,
      REPLACE(IF(kontakt,o.telefon,r.telefony),' ','') AS telefon,
      TRIM(IFNULL(nazev,prijmeni)) AS rod,role
    FROM osoba AS o 
    LEFT JOIN tvori AS t USING (id_osoba)
    LEFT JOIN rodina AS r USING (id_rodina)
    WHERE o.deleted='' $cond
    $HAVING
    ORDER BY role 
    LIMIT 10
  ");
  while ($rp && $o= pdo_fetch_object($rp)) {  
    $n++;
    if ($n==1) $os= $o;
  }
  $os->n= $n;
  return $os;
}
function db2_jen_osobni_udaje($ido,$or_cond='') {
  $os= (object)[];
  $n= 0;
  $cond= $or_cond ? '' : "AND id_osoba=$ido";
  $HAVING= $or_cond ? "HAVING $or_cond" : '';
  $rp= pdo_qry("
    SELECT id_osoba,TRIM(prijmeni) AS prijmeni,TRIM(jmeno) AS jmeno,narozeni,
      TRIM(ulice) AS ulice,
      TRIM(psc) AS psc, 
      TRIM(obec) AS obec,
      TRIM(email) AS email,
      REPLACE(telefon,' ','') AS telefon
    FROM osoba 
    WHERE deleted='' $cond
    $HAVING
    ORDER BY id_osoba
    LIMIT 10
  ");
  while ($rp && $o= pdo_fetch_object($rp)) {  
    $n++;
    if ($n==1) $os= $o;
  }
  $os->n= $n;
  return $os;
}
/** =======================================================================================> FAKTURY **/
# typ:T|I, zarovnání:L|C|R, písmo, l, t, w, h, border:LRTB
$ds2_faktura_dfl= 'T,L,3.5,10,10,0,0,,1.5';
$ds2_faktura_fld= [
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
  'objednavkaR' => [',R,4.5,178,90,20,30',"
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
# -------------------------------------------------------------------------------------- ds2 faktura
# par.typ = konečná | záloha
function ds2_faktura($par) {  //debug($par,'ds2_faktura');
  global $ds2_faktura_dfl, $ds2_faktura_fld;
  // získání parametrů
  $show= $par->show??0;
  $save= $par->save??0;
  if ($par->typ=='konečná') {
    $ds2_faktura_fld['faktura'][1]= "<b>Faktura $par->num</b>";
    $ds2_faktura_fld['za_co'][1]= "Za pobyt v Domě setkání ve dnech {obdobi} Vám fakturujeme:";
    $vals= ds2_faktura_data($par);
  }
  else { // záloha
    $ds2_faktura_fld['faktura'][1]= "<b>Zálohová faktura $par->num</b>";
    $ds2_faktura_fld['za_co'][1]= "Fakturujeme Vám zálohu na pobyt v Domě setkání ve dnech {obdobi}:";
    $vals= ds2_faktura_data($par);
  }
  // QR platba
  $vals['{QR-IBAN}']= 'CZ1520100000002000465448'; // Dům setkání: 2000465448 / 2010
  $vals['{QR-ds}']= urlencode('YMCA Setkání');
  $vals['{QR-vs}']= $vals['{VS}']= $par->vs;
  $vals['{QR-ss}']= $vals['{SS}']= $par->ss;
  $vals['{QR-pozn}']= urlencode("platba za pobyt v Domě setkání");
  // doplnění obecných fakturačních údajů
  $vals['{datum1}']= date('j. n. Y');
  $vals['{datum2}']= date('j. n. Y',strtotime("+14 days"));
  // redakce faktury
  $lheight_tabulka= $vals['{polozek}']>7 ? 1.5 : 2;
  $html= '';
  if ($show) {
    $html.= "<div class='PDF' style='scale:83%;position:absolute'>";
    $html.= "<style>.PDF div{padding-top:1mm}</style>";
    $html.= "<div style='position:absolute;width:210mm;height:297mm;border:1px solid grey'>";
    $j= 'mm';
  }
  // zobrazení
  if ($save) {
    tc_page_open();
  }
  $x_dfl= explode(',',$ds2_faktura_dfl);
  foreach ($ds2_faktura_fld as $jmeno=>$cast) {
    $x= $x_dfl; 
  // doplnění podle defaultu
    foreach (explode(',',$cast[0]) as $i=>$c) {
      if ($c) $x[$i]= $c;
    }
//    debug($x,'$typ,$align,$fsize,$l,$t,$w,$h,$border');
    list($typ,$align,$fsize,$l,$t,$w,$h,$border,$lheight)= $x;
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
      if ($typ=='T') {
        $elem= "<div style='position:absolute;"
            . "left:{$l}$j;top:{$t}$j;width:{$w}$j;height:{$h}$j;line-height:$lheight;"
            . "font-size:{$fsize}$j$bord$algn'>$text</div>";
  //      display(htmlentities($elem));
        $html.= $elem;
      }
      elseif ($typ=='I') {
        $elem= "<img src='$text' style='position:absolute;"
            . "left:{$l}$j;top:{$t}$j;width:{$w}$j;height:{$h}$j'>";
//        display(htmlentities($elem));
        $html.= $elem;
      }
      elseif ($typ=='QR') {
        $castka= ds2_kc($vals['{QR-castka}']);
        $elem= "<div style='position:absolute;"
            . "left:{$l}$j;top:{$t}$j;width:{$w}$j;height:{$h}$j;"
            . "font-size:{$fsize}$j;border:5px dotted black;text-align:center'>"
                . "<br>QR platba<br><br><b>$castka</b><br><br>bude zobrazena<br>v PDF</div>";
        $html.= $elem;
      }
    }
    if ($save) {
      tc_page_cell($text,$typ,$align,$fsize*2.4,$l,$t,$w,$h,$border,$lheight);
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
# --------------------------------------------------------------------------------- ds2 faktura_data
# vrátí data podle typu faktury
# zálohová: objednavka => odberatel, adresa, obdobi, QR-castka, tabulka
# konečná:  objednávka,osoba => adresa, obdobi, QR-castka, tabulka
function ds2_faktura_data($par) { 
  global $ds2_cena,$ds2_sazby;
  $order= $par->obj;
  $vals= [];
  $ds2_sazby= [];
  $sleva= 0;
  // položky faktury
  $polozky= array();
  if ($par->typ=='záloha') {
    // ------------------------------------------------------------ výpočty pro zálohovou fakturu
    $o= select_object('*','tx_gnalberice_order',"uid=$order",'setkani');
    $vals['{obdobi}']= date('j.n',$o->fromday).' - '.date('j.n.Y',$o->untilday);
    $vals['{ic_dic}']= ($o->ic ? "IČ: $o->ic" : '').($o->dic ? "    DIČ: $o->dic" : '');
    $vals['{adresa}']= wu(($o->org ? "$o->org" : '')
        . "<br>$o->firstname $o->name"
        . "<br>$o->address"
        . "<br>$o->zip $o->city");
    $dnu= round(($o->untilday-$o->fromday)/(60*60*24));
    // přečtení ceníku daného roku
    $rok= date('Y',$o->untilday);
    ds2_cenik($rok);
    if ( !count($ds2_cena) ) { fce_err("není ceník pro $rok"); goto end; }
    // ubytování může mít slevu
    $sleva= $o->sleva ? $o->sleva/100 : '';
    $polozky[]= ds2_c('noc_L',$dnu*($o->adults + $o->kids_10_15 + $o->kids_3_9),$sleva);
//    $polozky[]= ds2_c('noc_A',0,$sleva,1);
//    $polozky[]= ds2_c('noc_B',0,$sleva,1);
//    $polozky[]= ds2_c('noc_P',0,$sleva,1);
//    $polozky[]= ds2_c('noc_S',0,$sleva,1);
//    $polozky[]= ds2_c('noc_Z',0,$sleva,1);
    $polozky[]= ds2_c('ubyt_C',$dnu*($o->adults));
    $polozky[]= ds2_c('ubyt_S',$dnu*($o->adults));
    $n= $o->kids_10_15 + $o->kids_3_9 + $o->kids_3;
    if ($n) $polozky[]= ds2_c('ubyt_P',$dnu*$n);
    $n= $o->kids_3;
    if ($n) $polozky[]= ds2_c('noc_B',$dnu*$n,$sleva);
    switch ( $o->board ) {
    case 1:     // penze
      $polozky[]= ds2_c('strava_CC',$dnu*($o->adults+$o->kids_10_15));
      $n= $o->kids_3_9;
      if ($n) $polozky[]= ds2_c('strava_CD',$dnu*$n);
      break;
    case 2:     // polopenze
      $polozky[]= ds2_c('strava_PC',$dnu*($o->adults+$o->kids_10_15));
      $n= $o->kids_3_9;
      if ($n) $polozky[]= ds2_c('strava_PD',$dnu*$o->kids_3_9);
      break;
    }
  }
  else { // $par->typ=='konečná'
    // ---------------------------------------------------------------- výpočty pro konečnou fakturu
    $pobyt= ds2_cena_pobytu($order,$par->idos,0,2); // pro rodinu hosta
//    debug($pobyt,"ds2_cena_pobytu($order,$par->idos,0,2)");
    $fakt= $pobyt->fakt[2];
//    $host= $pobyt->host->host;
    $vals['{adresa}']= $pobyt->fields->adresa;
    $obdobi= $pobyt->obdobi;
    foreach ((array)$fakt as $pol=>list(,$pocet,)) {
      $polozky[]= ds2_c($pol,$pocet,$sleva);
    }
//    debug($polozky,'konečná - položky');
    $vals['{ic_dic}']= '';
  }
  // ------------------------------------------------------------------------------- redakce tabulky
  $celkem= 0;
  foreach ($polozky as $polozka) {
    $celkem+= $polozka[7];
  }
  $width= $sleva ? 67 : 67+12;
  $popisy= explode(',',
      "Položka:$width,Počet:12,Cena položky vč. DPH:26,"
      . ($sleva ? 'Sleva %:12,' : '')
      . 'Sazba DPH:14,DPH:25,Cena bez DPH:28');
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
  for ($i= 0; $i<=6; $i++) {
    if (!$sleva && $i==3) continue;
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
  $colspan= $sleva ? 'colspan="7"' : 'colspan="6"';
  $tab.= "<tr><td $colspan><br><br></td></tr>";
  if ($par->typ=='záloha') {
    $soucty= ['Celková cena s DPH'=>$celkem, 'Zaplaťte zálohu 50%'=>$celkem/2];
    $bold= 0;
    $koef= 0.5;
    $platit= $celkem*$koef;
  }
  else { // konečná
    $platit= $celkem - ($par->zaloha?:0);
    $soucty= ['Celková cena s DPH'=>$celkem, 'Zaplaceno zálohou'=>$par->zaloha?:0, 'Zbývá k zaplacení'=>$platit];
    $bold= 0;
    $koef= 1;
  }
  foreach ($soucty as $popis=>$castka) {
    $castka= ds2_kc($castka);
    if ($bold) {
      $popis= "<b>$popis</b>";
      $castka= "<b>$castka</b>";
    }
    $colspan= $sleva ? 'colspan="5"' : 'colspan="4"';
    $tab.= "<tr><td $colspan style=\"text-align:right\">$popis</td>"
      . "<td colspan=\"2\" align=\"right\" style=\"$lrtb\">$castka</td></tr>";
    $bold++;
  }
  // rozpisová tabulka DPH
  $rozpis= [-1=>['<b>Sazba</b>','<b>Daň</b>','<b>Základ</b>']];
  foreach ($ds2_sazby as $d=>$c) {
    $dan= round($c*$d/100,2);
    $rozpis[]= ["$d%",ds2_kc($dan*$koef),ds2_kc($c*$koef)];
  }
  $colspan= $sleva ? 'colspan="7"' : 'colspan="6"';
  $tab.= "<tr><td $colspan><br></td></tr>";
  $colspan= $sleva ? 'colspan="4"' : 'colspan="3"';
  $tab.= "<tr><td $colspan></td><td colspan=\"3\"><b>Rozpis DPH</b></td></tr>";
  $colspan= $sleva ? 'colspan="4"' : 'colspan="3"';
  foreach ($rozpis as $c) {
    $tab.= "<tr><td $colspan></td>"
      . "<td align=\"right\" style=\"$lrtb\">$c[0]</td>"
      . "<td align=\"right\" style=\"$lrtb\">$c[1]</td>"
      . "<td align=\"right\" style=\"$lrtb\">$c[2]</td>"
      . "</tr>";
  }
  $tab.= '</table>';
//  display($tab);
//                                              debug($x,'zaloha');
end:
  $vals['{obj}']= $order;
  $vals['{obdobi}']= $obdobi;
  $vals['{tabulka}']= $tab;
  $vals['{QR-castka}']= round($platit,2);
  // počet zúčtovaných položek ceníku kvůli řádkování tabulky
  $polozek= 0;
  foreach($polozky as $p) {
    if ($p) $polozek++;
  }
  $vals['{polozek}']= $polozek; 

//                                              debug($vals,'fakturujeme');
  return $vals;
}
# -------------------------------------------------------------------------------------------- ds2 c
# položka faktury
# id,pocet => název,cena,dph%,pocet
# inuly - zapsat do faktury i nuly
function ds2_c ($id,$pocet,$sleva='') { //trace();
  global $ds2_cena,$ds2_sazby;
  $kolik= $ds2_cena[$id]->polozka;
  $cena= $ds2_cena[$id]->cena;
  $sazba= $ds2_cena[$id]->dph;
  $x_dph=  round($pocet * ($cena - $cena / (1 + $sazba/100)),2);
  $x_cena= round($pocet*$cena - $x_dph,2);
  if (!isset($ds2_sazby[$sazba])) $ds2_sazby[$sazba]= 0;
  $ds2_sazby[$sazba]+= $x_cena;
  $c= [
    $kolik,
    $pocet,
    ds2_kc($cena),
    $sleva,
    $sazba.'%',
    ds2_kc($x_dph),
    ds2_kc($x_cena),
    $cena*$pocet, // 7: celková cena bez DPH
  ];
  return $cena*$pocet ? $c : null;
}
# ------------------------------------------------------------------------------------------- ds2 kc
function ds2_kc($c) {
  return number_format($c,2,'.',' ').' Kč';
}
/** ===========================================================================================> DŮM **/
# --------------------------------------------------------------------------------- dum faktura_save
# par.typ = konečná | záloha
function dum_faktura_save($parm) {
  $x= array_merge((array)$parm); $x['html']= "...";
  debug($x,"dum_faktura_save(...)");
  // uložení do tabulky
  $p= $parm->parm;
  $rok=    $p->udaje->fld->rok; display($rok);
  $num=    $p->num;  display($num);
  $typ=    $p->typ;  display($typ);
  $ord=    $p->udaje->fld->order;  display($ord);
  $cel=    $p->udaje->cena->celkem;  display($cel);
  $jso= pdo_real_escape_string($parm->parm_json); display($jso);
  $htm= pdo_real_escape_string($parm->html); display($htm);
  query("INSERT INTO faktura (rok,num,typ,id_order,id_pobyt,castka,parm_json,html) VALUES "
      . "($rok,$num,'$typ',$ord,0,$cel,'$jso','$htm')");
}
# -------------------------------------------------------------------------------------- dum faktura
# par.typ = konečná | záloha
function dum_faktura($par) {  //debug($par,'ds2_faktura');
  global $ds2_faktura_dfl, $ds2_faktura_fld, $ds2_cena;
  // získání parametrů
  $show= $par->show??0;
  $save= $par->save??0;
  // společné údaje
  $o= $par->udaje->fld;
  $vals['{obdobi}']= $o->oddo;
  $vals['{ic_dic}']= ($o->ic ? "IČ: $o->ic" : '').($o->dic ? "    DIČ: $o->dic" : '');
  $vals['{adresa}']= ($o->org ? "$o->org" : '')
      . "<br>$o->firstname $o->name"
      . "<br>$o->address"
      . "<br>$o->zip $o->city";
  $vals['{datum1}']= date('j. n. Y');
  $vals['{datum2}']= date('j. n. Y',strtotime("+14 days"));
  $vals['{obj}']= $o->order;
  $vals['{vyrizuje}']= $par->vyrizuje;
  // QR platba
  $vals['{QR-IBAN}']= 'CZ1520100000002000465448'; // Dům setkání: 2000465448 / 2010
  $vals['{QR-ds}']= urlencode('YMCA Setkání');
  $vals['{QR-vs}']= $vals['{VS}']= $par->vs;
  $vals['{QR-ss}']= $vals['{SS}']= $par->ss;
  $vals['{QR-pozn}']= urlencode("platba za pobyt v Domě setkání");
  // podle typu faktury
  $roknum= ($o->rok-2000).str_pad($par->num,4,'0',STR_PAD_LEFT);
  if ($par->typ=='konečná') {
    $ds2_faktura_fld['faktura'][1]= "<b>Faktura $roknum</b>";
    $ds2_faktura_fld['za_co'][1]= "Za pobyt v Domě setkání ve dnech {obdobi} Vám fakturujeme:";
  }
  else { // záloha
    $ds2_faktura_fld['faktura'][1]= "<b>Zálohová faktura $roknum</b>";
    $ds2_faktura_fld['za_co'][1]= "Fakturujeme Vám zálohu na pobyt v Domě setkání ve dnech {obdobi}:";
    $zaloha= $par->zaloha;
  }
  // ------------------------------------------------------------------------------- redakce tabulky
  // redakce položek pro zobrazení ve sloupcích
  $celkem= 0;
  $sleva= 0;
  $polozky= [];
  ds2_cenik($par->udaje->fld->rok);
  $udaje= $par->typ=='konečná' ? (object)$par->udaje->ucet : (object)$par->udaje->cena;
  debug($udaje);
  foreach ($udaje->rozpis as $id=>$pocet) {
    //$polozky[]= ds2_c($pol,$pocet,$sleva);
    $zaco= $ds2_cena[$id]->polozka;
    $cena= $ds2_cena[$id]->cena;
    $sazba= $ds2_cena[$id]->dph;
    $x_dph=  round($pocet * ($cena - $cena / (1 + $sazba/100)),2);
//    $x_cena= round($pocet*$cena - $x_dph,2);
//      if (!isset($ds2_sazby[$sazba])) $ds2_sazby[$sazba]= 0;
//      $ds2_sazby[$sazba]+= $x_cena;
    $celkem+= $cena*$pocet;
    $polozky[]= [
      $zaco,
      $pocet,
      ds2_kc($cena),
      $sleva,
      $sazba.'%',
      ds2_kc($x_dph),
//      ds2_kc($x_cena),
      ds2_kc($pocet*$cena),
      $cena*$pocet, // 7: celková cena vč. DPH
    ];
  }
//    debug($polozky,'konečná - položky');

  // nadpisy položek
  $width= $sleva ? 67 : 67+12;
  $popisy= explode(',',
      "Položka:$width,Počet:12,Cena položky vč. DPH:26,"
      . ($sleva ? 'Sleva %:12,' : '')
      . 'Sazba DPH:14,DPH:25,Cena vč. DPH:28');
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
  for ($i= 0; $i<=6; $i++) {
    if (!$sleva && $i==3) continue;
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
  $colspan= $sleva ? 'colspan="7"' : 'colspan="6"';
  $tab.= "<tr><td $colspan><br><br></td></tr>";
  if ($par->typ=='záloha') {
    $soucty= ['Celková cena s DPH'=>$celkem, 'Zaplaťte zálohu'=>$zaloha];
    $bold= 0;
    $koef= $zaloha/$celkem;
    $platit= $celkem*$koef;
  }
  else { // konečná
    $platit= $celkem - ($par->zaloha?:0);
    $soucty= ['Celková cena s DPH'=>$celkem, 'Zaplaceno zálohou'=>$par->zaloha?:0, 'Zbývá k zaplacení'=>$platit];
    $bold= 0;
    $koef= 1;
  }
  foreach ($soucty as $popis=>$castka) {
    $castka= ds2_kc($castka);
    if ($bold) {
      $popis= "<b>$popis</b>";
      $castka= "<b>$castka</b>";
    }
    $colspan= $sleva ? 'colspan="5"' : 'colspan="4"';
    $tab.= "<tr><td $colspan style=\"text-align:right\">$popis</td>"
      . "<td colspan=\"2\" align=\"right\" style=\"$lrtb\">$castka</td></tr>";
    $bold++;
  }
  // rozpisová tabulka DPH
  $rozpis= [-1=>['<b>Sazba</b>','<b>Daň</b>','<b>Základ</b>']];
  foreach ($udaje->dph as $d=>$c) {
    $dan= round($c*$d/100,2);
    $rozpis[]= ["$d%",ds2_kc($dan*$koef),ds2_kc($c*$koef)];
  }
  $colspan= $sleva ? 'colspan="7"' : 'colspan="6"';
  $tab.= "<tr><td $colspan><br></td></tr>";
  $colspan= $sleva ? 'colspan="4"' : 'colspan="3"';
  $tab.= "<tr><td $colspan></td><td colspan=\"3\"><b>Rozpis DPH</b></td></tr>";
  $colspan= $sleva ? 'colspan="4"' : 'colspan="3"';
  foreach ($rozpis as $c) {
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
  $x_dfl= explode(',',$ds2_faktura_dfl);
  foreach ($ds2_faktura_fld as $jmeno=>$cast) {
    $x= $x_dfl; 
  // doplnění podle defaultu
    foreach (explode(',',$cast[0]) as $i=>$c) {
      if ($c) $x[$i]= $c;
    }
//    debug($x,'$typ,$align,$fsize,$l,$t,$w,$h,$border');
    list($typ,$align,$fsize,$l,$t,$w,$h,$border,$lheight)= $x;
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
      if ($typ=='T') {
        $elem= "<div style='position:absolute;"
            . "left:{$l}$j;top:{$t}$j;width:{$w}$j;height:{$h}$j;line-height:$lheight;"
            . "font-size:{$fsize}$j$bord$algn'>$text</div>";
  //      display(htmlentities($elem));
        $html.= $elem;
      }
      elseif ($typ=='I') {
        $elem= "<img src='$text' style='position:absolute;"
            . "left:{$l}$j;top:{$t}$j;width:{$w}$j;height:{$h}$j'>";
//        display(htmlentities($elem));
        $html.= $elem;
      }
      elseif ($typ=='QR') {
        $castka= ds2_kc($vals['{QR-castka}']);
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
      tc_page_cell($text,$typ,$align,$fsize*2.4,$l,$t,$w,$h,$border,$lheight);
    }
  }
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
    $ref= "Fakturu lze stáhnout <a target='pdf' href='$f_rel'>zde</a>";
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
  
  return (object)array('html'=>$html_exp,'ref'=>$ref,'parm_json'=>json_encode($par),'parm'=>$par,'err'=>'');
}
# ------------------------------------------------------------------------------ dum objednavky_akce
# seznam uplatněných položek ceníků
function dum_objednavky_akce($id_akce) { 
  global $setkani_db;
  $x= (object)['sel'=>'','key'=>0, 'count'=>0];
  $ro= pdo_qry("
    SELECT o.uid,o.note,name,datum_od,datum_do,IFNULL(g_kod,0),_cis.zkratka
    FROM akce AS a
    LEFT JOIN _cis ON _cis.druh='akce_garant' AND data=poradatel 
    LEFT JOIN pobyt AS p ON id_duakce=id_akce
    LEFT JOIN join_akce USING (id_akce)
    JOIN $setkani_db.tx_gnalberice_order AS o 
        ON uid=IFNULL(p.id_order,0) OR o.id_akce=id_duakce OR a.id_order=uid
      WHERE id_duakce=$id_akce
    GROUP BY uid ORDER BY uid
  ");
  $del= ''; 
  while ($ro && (list($ido,$note,$name,$od,$do,$kod,$kdo)= pdo_fetch_array($ro))) {
    $item= str_replace([',',':'],' ',"$ido - $note ($name)");
    $x->sel.= "$del$item:$ido"; $del= ',';
    if (!$x->key) $x->key= $ido;
    if (!$x->oddo) $x->oddo= datum_oddo($od,$do);
    $x->kod= $kod;
    $x->kdo= $kdo;
    $x->count++;
  }
  debug($x,"dum_objednavky_akce($id_akce)");
  return $x;
}
# ------------------------------------------------------------------------------ dum objednavka_save
# objednávka pobytu
function dum_objednavka_save($id_order,$changed) { 
  global $setkani_db;
  $set= "SET "; $del= '';
  foreach($changed as $fld=>$val) {
    $val= pdo_real_escape_string($val);
    $set.= "$del$fld='$val'";
    $del= ',';
  }
  query("UPDATE $setkani_db.tx_gnalberice_order $set WHERE uid=$id_order");
}
# ----------------------------------------------------------------------------------- dum objednavka
# objednávka pobytu
function dum_objednavka($id_order) { 
  global $setkani_db;
  $x= (object)['err'=>'','rozpis'=>[],'cena'=>[],'fld'=>[]];
  // shromáždění údajů z objednávky
  $f= select_object('state,fromday AS od,untilday AS do,note,rooms1,'
      . 'adults,kids_10_15,kids_3_9,kids_3,board,'
      . 'org,ic,name,firstname,dic,email,telephone,address,zip,city,'
      . 'DATEDIFF(FROM_UNIXTIME(untilday),FROM_UNIXTIME(fromday)) AS noci,akce AS id_akce'
      ,"$setkani_db.tx_gnalberice_order","uid=$id_order");
  $f->id_order= $id_order;
  $f->rok= date('Y',$f->od);
  $f->oddo= datum_oddo(date('Y-m-d',$f->od),date('Y-m-d',$f->do));
  $f->od= date('j.n.Y',$f->od);
  $f->do= date('j.n.Y',$f->do);
  // již vystavená zálohová faktura na objednávku nebo návrh čísla faktury
  $num= select('num','faktura',"id_order=$id_order AND typ=1")
     ?: select1('IFNULL(MAX(num)+1,1)','faktura',"rok=$f->rok");
  $f->fakt_num= $num;
  $x->fld= $f;
  // výpočet ceny pro zálohovou fakturu
  $rozpis= dum_objednavka_zaloha($x->fld);
  $x->cena= dum_objednavka_cena($rozpis);
  // zjištění skutečně spotřebovaných osobonocí, pokojů, stravy, poplatků, ...
  $y= dum_browse_order((object)['cmd'=>'browse_load','cond'=>"p.id_order=$id_order"]);
  $x->ucet= $y->suma;  
  debug($x,"dum_objednavka($id_order)");
  return $x;
}
# ------------------------------------------------------------------------------ dum objednavka_cena
# k položkám ceníku přidá spotřebu
function dum_objednavka_cena($rozpis) { 
  global $ds2_cena;
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
  global $ds2_cena;
  ds2_cenik($x->rok);
  $cena= [];
//  debug($ds2_cena);  
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
# -------------------------------------------------------------------------------- ds2 cena_pobyt_ds
# seznam uplatněných položek ceníků
function ds2_cena_polozky($id_pobyt) { trace();
  $list= '';
  $items= (object)[];
  $rp= pdo_qry("
    SELECT datum_od, narozeni,
      DATEDIFF(datum_do,datum_od) AS noci
    FROM osoba JOIN spolu USING (id_osoba) JOIN pobyt USING (id_pobyt) JOIN akce ON id_akce=id_duakce
    WHERE id_pobyt=$id_pobyt
    ");
  while ($rp && (list($od,$narozeni,$noci)= pdo_fetch_array($rp))) {
    $items->noci+= $noci;
    $vek= roku_k($narozeni,$od);
    $items->vek.= $vek;
  }
  $del= '';
  foreach ($items as $item=>$count) {
    $list.= "$del$item:$count"; $del= ', ';
  }
  return $list;
}
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
# ==================================================================================> objednávky NEW
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
  global $answer_db, $setkani_db, $ds2_cena, $y; // y je zde globální kvůli možnosti trasovat SQL dotazy
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
        'dph'   =>[],
        'pokoj' =>[],'pokoje'=>'',
        'rozpis'=>[],
        'hoste' =>(object)['adults'=>0,'kids_10_15'=>0,'kids_3_9'=>0,'kids_3'=>0]]; 
    $luzko_pokoje= ds2_cat_typ();
    $ds_strava= map_cis('ds_strava','zkratka');
    $rok_ceniku= 0;
    // c.ikona=1 pokud nebyl na akci
    ezer_connect($answer_db,true);
    $rp= pdo_qry("
      SELECT id_pobyt,c.ikona,prijmeni,datum_od,datum_od,DATEDIFF(datum_do,datum_od),YEAR(datum_od),
        GROUP_CONCAT(CONCAT(id_spolu,'~',prijmeni,'~',jmeno,'~',narozeni,
            '~',0,'~',IF(s.pokoj,s.pokoj,p.pokoj),'~',s.ds_vzorec,
            '~',0,'~',0,'~',0,'~',0,'~',0) 
          ORDER BY IF(narozeni='0000-00-00','9999-99-99',narozeni) 
          SEPARATOR '~' ) AS cleni,d.state,d.board,p.ds_vzorec
      FROM osoba AS o 
        JOIN spolu AS s USING (id_osoba) 
        JOIN pobyt AS p USING (id_pobyt) 
        JOIN akce AS a ON id_akce=id_duakce 
        JOIN _cis AS c ON c.druh='ms_akce_funkce' AND c.data=p.funkce
        JOIN $setkani_db.tx_gnalberice_order AS d ON uid=p.id_order
      WHERE $x->cond
      GROUP BY id_pobyt
      ORDER BY prijmeni
    ");
    $i_vek= 3; $i_noci= 4; $i_pokoj= 5; $i_vzorec= 6;
    while ($rp && (list(
        $idp,$nebyl,$prijmeni,$od,$do,$noci,$rok,$cleni,$state,$board,$vzorec)= pdo_fetch_array($rp))) {
      if ($rok!=$rok_ceniku) {
        $rok_ceniku= $rok;
        ds2_cenik($rok_ceniku);
      }
      // projdeme členy a spočteme cenu
      $celkem= 0;
//      $noci= date_diff(date_create($od),date_create($do))->format('%a');
      $c= explode('~',$cleni);
      for ($i= 0; $i<count($c); $i+=12) {
        $vek= roku_k($c[$i+$i_vek],$od); // věk ns začátku akce
        $c[$i+$i_vek]= $vek;
        $c[$i+$i_noci]= $noci;
        // doplníme počty do SUMA - jen pokud nebyla zrušena účast
        $rozpis= [];
        if ($nebyl==0) {
          $pokoj= $c[$i+$i_pokoj];
          $ps= explode(',',$pokoj);
          foreach ($ps as $p) {
            $suma->pokoj[$p]+= 1/count($ps); 
            $pokoj= $p;
          }
//          foreach (explode(',',$c[$i+$i_vzorec]) as $ip) {
//            list($zaco,$pocet)= explode(':',$ip);
//            // trvání pobytu
//            if ($zaco=='od') $od= $pocet;
//            elseif ($zaco=='do') $do= $pocet;
////            else $suma->rozpis[$zaco]+= $pocet;
//          }      
          // člověkonoci
          $suma->clovekonoci+= $noci;
          // ubytování osob podle věku
          $noc= 'noc_'.$luzko_pokoje[$pokoj];
          // poplatky podle věku
          $poplatky= ['ubyt_P','ubyt_C','ubyt_S','noc_B',$noc];
          if ($state==3) { // akce YMCA má poplatek za program
            array_push($poplatky,'prog_C','prog_P');
          }
          foreach ($poplatky as $x) {
  //          debug($ds2_cena[$x],"pokoj $pokoj,$x");
            if ($vek>=$ds2_cena[$x]->od && $vek<$ds2_cena[$x]->do && $ds2_cena[$x]->cena) {
              $rozpis[$x]+= $noci;
              $suma->rozpis[$x]+= $noci;
            }
          }
          // strava osob podle věku
          $strava= 'strava_'.$ds_strava[$board];
          if ($vek>=$ds2_cena[$strava.'D']->od && $vek<$ds2_cena[$strava.'D']->do) {
            $rozpis["{$strava}D"]+= $noci;
            $suma->rozpis["{$strava}D"]+= $noci;
          }
          if ($vek>=$ds2_cena[$strava.'C']->od && $vek<$ds2_cena[$strava.'C']->do) {
            $rozpis["{$strava}C"]+= $noci;
            $suma->rozpis["{$strava}C"]+= $noci;
          }
          // počty osob podle věku
          if ($vek<3) $suma->hoste->kids_3++;
          elseif ($vek<10) $suma->hoste->kids_3_9++;
          elseif ($vek<15) $suma->hoste->kids_10_15++;
          else $suma->hoste->adults++;
          // zápis nového vzorce
          $vzorec_new= $del= '';
          foreach ($rozpis as $item=>$val) {
            $vzorec_new.= "$del$item:$val";
            $del= ',';
          }
          $c[$i+$i_vzorec]= $vzorec_new; //."od:$od,do:$do,noci:$noci";
          // doplníme ceny
          $cena= dum_cena($vzorec_new);
          $celkem+= $c[$i+7]= $cena['celkem'];
          $c[$i+8]= $cena['druh']['ubytování']??0;
          $c[$i+9]= $cena['druh']['strava']??0;
          $c[$i+10]= $cena['druh']['poplatek obci']??0;
          $c[$i+11]= $cena['druh']['program']??0;
        }
      }
      $cleni= implode('~',$c);
      // doplníme pobyt
      $z[$idp]->cleni= $cleni;
      $z[$idp]->idp= $idp;
      $z[$idp]->nazev= $prijmeni;
      $z[$idp]->cena= $celkem;
      $z[$idp]->vzorec= $vzorec;
    }
    # předání pro browse
    $y->values= $z;
    $y->from= 0;
    $y->cursor= 0;
    $y->rows= count($z);
    $y->count= count($z);
    $y->quiet= 0;
    $y->ok= 1;
    // dopočet sumy přehled a účtování
//    debug($suma->rozpis,"dum_browse_order/rozpis = ");
    $cena= dum_cena($suma->rozpis);
//    debug($cena);
    $suma->celkem= $cena['celkem'];
    $suma->druh= $cena['druh'];
    $suma->dph= $cena['dph'];
//    debug($suma);
    ksort($suma->pokoj);
    $suma->pokoje= implode(',',array_keys($suma->pokoj));
    $y->suma= $suma;
    array_unshift($y->values,null);
  }
//  debug($y->suma,"dum_browse_order/suma = ");
//  debug($y->values,"dum_browse_order/values = ");
  return $y;  
}
function dum_cena($vzorec,$dotovana=0) {
  global $ds2_cena; // předpokládá, že je již vypočteno pro správný rok
  $cena= ['celkem'=>0,'druh'=>[],'abbr'=>[],'dph'=>[]/*,'rozpis'=>$rozpis*/]; // rozpis ceny podle druhu a dph
  foreach (is_string($vzorec) ? explode(',',$vzorec) : $vzorec as $zaco=>$ip) {
    if (is_string($vzorec))
      list($zaco,$pocet)= explode(':',$ip);
    else 
      $pocet= $ip;
    $d= $ds2_cena[$zaco];
    $kc= $dotovana ? $d->dotovana : $d->cena;
    $cena['celkem']+= $kc * $pocet;
    $cena['druh'][$d->druh]+= $kc * $pocet;
    $cena['abbr'][$d->druh_abbr]+= $kc * $pocet;
    $cena['dph'][$d->dph]+= ($kc * $pocet) / ((100 + $d->dph) / 100);
  }
  return $cena;
}
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
        . "nazev='Objednavky $new_order (kopie $id_order)',note='$new_order' "
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
function ds2_cenik($rok) {  
  global $ds2_cena;
  $ds2_cena= array();
  ezer_connect('setkani');
  $qry2= "SELECT * FROM ds_cena WHERE rok=$rok ORDER BY druh,typ";
  $res2= pdo_qry($qry2);
  while ( $res2 && $c= pdo_fetch_object($res2) ) {
    $wc= $c;
    $wc->polozka= wu($c->polozka);
    $wc->druh= wu($c->druh);
    $ds2_cena[$c->typ]= $wc;
  }
//                                                 debug($ds2_cena,"ds2_cenik($rok)");
}
# ----------------------------------------------------------------------------------- ds2 cenik_list
# vrátí seznam položek ceníku Domu setkání zadaného roku (default je letošní platný)
# pokud je zadaný host vrátí také počet objednaných instancí položek ceníku
# ve kterém zohlední aktuální opravy podle položky ds_osoba.oprava
function ds2_cenik_list($cenik_roku=0,$order=0,$host=0) { trace();
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
//    $cen= (object)array();
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
//      debug($host_pol,"ds2_polozky_hosta pro $order/$host");
      foreach ($host_pol->cena as $field=>$value) {
        $pol->$field= $value;
      }
      // ceny za položky
      $one= ds2_platba_hosta($cenik_roku,$host_pol->cena,$fields,'',true);
//      debug($fields,"ds2 platba_hosta");
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
//                                                debug($y,'ds2 cenik_list');
  return $y;
}
# ---------------------------------------------------------------------------------- ds2 cena_pobytu
# ASK
# vypočítá cenu pobytu účastníka (1), rodiny (2), akce (3)
# $id_osoba je z tabulky ds_osoba obsahující osobo-dny 
function ds2_cena_pobytu($order,$idos,$cenik_roku,$pro=0) { trace();
  $y= (object)array('fields'=>(object)array(),'rows'=>array());
  // číselníky 
  $ds_luzko=  map_cis('ds_luzko','zkratka');  $ds_luzko[0]=  '?';
  $ds_strava= map_cis('ds_strava','zkratka'); $ds_strava[0]= '?';
  ezer_connect('setkani');
  // přepočet kategorie pokoje na typ ubytování v ceníku    
  $luzko_pokoje= ds2_cat_typ();
  // společná data
  list($order,$jmeno,$prijmeni,$rodina,$ulice,$psc,$obec)= 
      select('id_order,jmeno,prijmeni,rodina,ulice,psc,obec','ds_osoba',"id_osoba=$idos",'setkani');
  $y->order= $order;
  $y->fields->jmeno= wu($jmeno);
  $y->fields->prijmeni= wu($prijmeni);
  $y->fields->adresa= wu("$jmeno $prijmeni<br>$ulice<br>$psc $obec");
  $y->fields->rodina= wu($rodina);
  $ob= select_object('*','tx_gnalberice_order',"uid=$order",'setkani');
  $cenik_roku= $cenik_roku?: date('Y',$ob->untilday);
  $y->obdobi= date('j.n',$ob->fromday).' - '.date('j.n.Y',$ob->untilday);
  ds2_cenik($cenik_roku);
  
  // údaje pro fakturaci
  $y->fakt= [1=>[],2=>[],3=>[]]; // pro -> polozka -> [pocet,suma,dph]
  global $ds2_cena;
  foreach ($ds2_cena as $typ=>$desc) {
    for ($i= 1; $i<=3; $i++) {
      if ($pro && $i!=$pro) continue;
      $y->fakt[$i][$typ]= [0,0,$desc->dph];
    }
  }
  
  // sběr a kontrola dat pro hosta, rodinu, celou objednávku
  foreach (array(1=>"id_osoba=$idos",2=>"rodina='$rodina'",3=>"1") as $i=>$cond) {
    if ($pro && $i!=$pro) continue;
    $fields= (object)array();
    $ros= pdo_qry("SELECT * FROM ds_osoba WHERE id_order=$order AND $cond");
    while ( $ros && $os= pdo_fetch_object($ros) ) {
      if ( !$os->pokoj ) { $y->err= "není zapsán pokoj pro $y->prijmeni $y->jmeno "; goto end; }
      $host_pol= ds2_polozky_hosta($ob,$os,$luzko_pokoje,$ds_luzko,$ds_strava);
      debug($host_pol,"ds2 polozky_hosta");
      foreach ($host_pol->cena as $field=>$pocet) {
        if (isset($y->fakt[$i][$field]) && $pocet) {
          $y->fakt[$i][$field][1]+= $pocet;
        }
      }
      if ( $i==1 ) {
        $y->host= $host_pol;
      }
      ds2_platba_hosta($cenik_roku,$host_pol->cena,$fields,$i,true);
      debug($fields,"ds2 platba_hosta ");
      foreach ($fields as $field=>$value) {
        $y->fields->$field+= $value;
        if (isset($y->fakt[$i][$field])) {
          $y->fakt[$i][$field][0]+= $value;
        }
      }
    }
  }
end:  
                                                    debug($y,"ds2 cena_pobytu($idos,$cenik_roku)");
  return $y;
}
# -------------------------------------------------------------------------------------- ds2 cat_typ
# přepočet kategorie pokoje na typ ubytování v ceníku    
function ds2_cat_typ() {
  global $setkani_db;
  $cat_typ= array('C'=>'A','B'=>'L','A'=>'S');
  $luzko_pokoje[0]= 0;
  $rr= pdo_qry("SELECT number,category FROM $setkani_db.tx_gnalberice_room WHERE version=1");
  while ( $rr && list($pokoj,$typ)= pdo_fetch_row($rr) ) {
    $luzko_pokoje[$pokoj]= $cat_typ[$typ];
  }
  return $luzko_pokoje;  
}
# -----------------------------------------------------------------------------==> ds2 polozky_hosta
# výpočet položek hosta
function ds2_polozky_hosta ($o,$h,$luzko_pokoje,$ds_luzko,$ds_strava) { trace();
  global $ds2_cena;
  // výpočet
  $hf= sql2stamp($h->fromday); $hu= sql2stamp($h->untilday);
  $od_ts= $hf ? $hf : $o->fromday;  
//  $od= date('j.n',$od_ts);
  $do_ts= $hu ? $hu : $o->untilday; 
//  $do= date('j.n',$do_ts);
  $vek= ds2_vek($h->narozeni,$o->fromday);
//  $narozeni= $h->narozeni ? sql_date1($h->narozeni): '';
  $strava= $h->strava ? $h->strava : $o->board;
  // připsání řádku
  $host= array();
//  $host[]= wu($h->rodina);
//  $host[]= wu($h->jmeno);
//  $host[]= wu($h->prijmeni);
//  $host[]= wu($h->ulice);
//  $host[]= wu("{$h->psc} {$h->obec}");
//  $host[]= $narozeni;
//  $host[]= $vek;
//  $host[]= $h->telefon;
//  $host[]= $h->email;
//  $host[]= $od;
//  $host[]= $do;
  // položky hosta
  $pol= (object)array();
//  $pol->test= "{$h->strava} : {$o->board} - $strava = {$ds_strava[$strava]}";
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
  // zvíře za noc
  if ($h->zvire)
    $pol->noc_Z= $noci * $h->zvire;
  // strava
  $pol->strava_CC= $ds_strava[$strava]=='C' && $vek>=$ds2_cena['strava_CC']->od ? $noci : '';
  $pol->strava_CD= $ds_strava[$strava]=='C' && $vek>=$ds2_cena['strava_CD']->od
                                            && $vek< $ds2_cena['strava_CD']->do ? $noci : '';
  $pol->strava_PC= $ds_strava[$strava]=='P' && $vek>=$ds2_cena['strava_PC']->od ? $noci : '';
  $pol->strava_PD= $ds_strava[$strava]=='P' && $vek>=$ds2_cena['strava_PD']->od
                                            && $vek< $ds2_cena['strava_PD']->do ? $noci : '';
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
  // program pouze pro akce YMCA
//  debug($o);
  if ($o->state==3) {
    $pol->prog_C= $vek>=$ds2_cena['prog_C']->od  ? $noci : 0;
    $pol->prog_P= $vek>=$ds2_cena['prog_P']->od && $vek<$ds2_cena['prog_P']->do ? $noci : 0;
  }
  return (object)array('host'=>$host,'cena'=>$pol);
}        
# ------------------------------------------------------------------------------==> ds2 platba_hosta
# výpočet ceny za položky hosta jako ubyt,strav,popl,prog,celk
function ds2_platba_hosta ($cenik_roku,$polozky,$platba,$i='',$podrobne=false) { trace();
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
        $za_noc= in_array($typ_,array('noc','strava','ubyt','prog'));
        $cena= $za_noc ? $cena*$polozky->noci : $cena;
        $platba->$druh+= $cena;
        if ( $podrobne ) {
          $platba->$typ+= $cena;
        }
      }
    }
    $platba->$celki+= $platba->$druh;
  }
//                          debug($one,"ds2 platba_hosta ($cenik_roku,polozky,platba,$i,$podrobne)");
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
    $vek= ds2_vek($o->narozeni,time());
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
    list($jmeno,$prijmeni,$adresa,$narozeni,$telefon,$email,$termin,$poplatek,$akce,)= (array)$host;
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
  global $ds2_cena, $ezer_path_serv;
  require_once "$ezer_path_serv/licensed/xls2/Classes/PHPExcel/Calculation/Functions.php";
  ds2_cenik($rok);
//                                      debug($ds2_cena,'ds_cena',(object)array('win1250'=>1));
  $x= (object)array();
//  $x->table= "klienti_$obdobi";
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
    $vek= ds2_vek($h->narozeni,$pf ? $h->_pf : $h->_of);
    if ( $h->narozeni ) {
      list($y,$m,$d)= explode('-',$h->narozeni);
      $time= gmmktime(0,0,0,$m,$d,$y);
      $narozeni= PHPExcel_Shared_Date::PHPToExcel($time);
    }
    else $narozeni= 0;
    // rekreační poplatek
    if ( $vek>=18 || $vek<0 )
      $popl= $ds2_cena['ubyt_C']->cena + $ds2_cena['ubyt_S']->cena;
    else
      $popl= $ds2_cena['ubyt_P']->cena;
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
    case 'join-ys': // ----------------------------------------------------- přiřazení plateb
      $na= $nd= $nu= $nv= 0;
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
      // platby za akce
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
      $y->html= "Rozpoznáno $na plateb za akce, $nd darů, $nu osob podle účtu, $nv podle VS a jména";
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
