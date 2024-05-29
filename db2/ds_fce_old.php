<?php # (c) 2009-2015 Martin Smidek <martin@smidek.eu>
/** ======================================================================================> OBSOLETE **/
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
  dum_cenik($rok);
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
      query("TRUNCATE TABLE ds_db");
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
            MAX(d.ulice),MAX(d.psc),MAX(d.obec),MAX(d.telefon),MAX(d.email),
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
      $neucasti= select1("GROUP_CONCAT(data)",'_cis',"druh='ms_akce_funkce' AND ikona=1");

//      $stav= map_cis('ds_stav');
      $ro= pdo_qry("
        SELECT akce,uid,state,note,name,
          DATE(FROM_UNIXTIME(fromday)) AS _from, DATE(FROM_UNIXTIME(untilday)) AS _to
        FROM $setkani_db.tx_gnalberice_order
        WHERE deleted=0 AND fromday>0 AND YEAR(FROM_UNIXTIME(fromday))>=$od_roku
        -- AND YEAR(FROM_UNIXTIME(fromday)) IN (2023,2024)
        -- AND fromday=1706227200 AND untilday=1706400000
        -- AND uid=2477
        -- GROUP BY _from,_to,akce
        ORDER BY _from,uid
      ");
      while ($ro && (list($kod,$idd,$state,$note,$name,$od,$do)= pdo_fetch_array($ro))) {  
        $rok= substr($od,0,4);
        $rok_mesic= substr($od,0,7);
        $spojeno= 0;
        $osob= select('COUNT(*)',"$setkani_db.ds_osoba","id_order=$idd");
        if ($osob==0 && strcmp($od,'2024-04-00')==-1) continue;
        $no++;
        if ($state==3) {  // akce YMCA==3
          // nejprve zkusíme podle kódu
          list($ida,$oda,$doa)= select('id_akce,datum_od,datum_do',
              'join_akce JOIN akce ON id_akce=id_duakce',
              "g_rok=$rok AND g_kod='$kod'");
          if ($ida && ($od!=$oda && $do==$doa || $od==$oda && $do!=$doa))
            $err.= "<br>+ $rok_mesic: objednávka $idd '$note' je typu 'akce YMCA' s kódem $kod ale liší se data od-do $od!=$oda || $do!=$doa";
          elseif ($od!=$oda && $do!=$doa)
            $ida= 0;
          // objednávka je typu "akce YMCA" ale bez kódu - dohledáme podle data 
          if (!$ida) {
            list($ida,$oda,$doa)= select('id_duakce,datum_od,datum_do',
                "akce","(datum_od='$od' OR datum_do='$do') AND misto='Albeřice' AND access=1");
            if ($ida && ($od!=$oda && $do==$doa || $od==$oda && $do!=$doa))
              $err.= "<br>+ $rok_mesic: objednávka $idd '$note' je typu 'akce YMCA' s nepochopeným kódem $kod a liší se data od-do $od!=$oda || $do!=$doa";
            elseif ($od!=$oda && $do!=$doa)
              $ida= 0;
          }
          if (!$ida) {
            $err.= "<br>- $rok_mesic: objednávka $idd '$note' je typu 'akce YMCA' ale nebylo možné najít odpovídající akci ani podle data ani podle kódu";
          }
          else {
            $na++;
            $spojeno= 1;
            query("UPDATE akce SET ma_cenik=2 WHERE id_duakce=$ida");
            // doplnění pokojů
            // 1. byly pokoje definovány v ds_osoba?
            /*DS*/list($osob1,$pokoju1)= select("COUNT(*),IF(SUM(pokoj)>0,COUNT(DISTINCT pokoj),0)",
                "$setkani_db.ds_osoba","id_order=$idd");
            /*YS*/list($osob2,$pokoju2)= select("COUNT(*),IF(SUM(p.pokoj)>0,COUNT(DISTINCT p.pokoj),0)",
                "pobyt AS p JOIN spolu USING (id_pobyt)",
                "id_akce=$ida AND funkce NOT IN ($neucasti)");
            // zjkistíme, kde se spolehlivě nachází rozpis pokojů?
            $ds_info= ($pokoju2==0 && $pokoju1>0) ? 'ds' : (
                   ($osob1==$osob2 && $osob1==0) ? '-' : (
                   ($pokoju1==1 && $pokoju2>1) ? 'ys' : '?')); 
            $html.= "<br>$rok: objednávka $idd typu 'akce YMCA' má OBSAZENOST $idd:$ida "
                . "= $ds_info = DS:$osob1/$pokoju1 YS:$osob2/$pokoju2 -- '$note' ";
            query("UPDATE $setkani_db.tx_gnalberice_order "
                . "SET id_akce=$ida,DS2024=\"{'typ':'YMCA','pokoje':'$ds_info'}\" WHERE uid=$idd");
            if ($ds_info=='DS') { // zapíšeme pokoje do pobyt.spolu
              $pokoje= []; // pobyt->[pokoje]
              $rs= pdo_qry("
                  SELECT s.id_spolu,id_pobyt,x.pokoj
                  FROM $setkani_db.ds_osoba AS x 
                  JOIN ds_db AS d ON d.ds_osoba=x.id_osoba
                  JOIN spolu AS s ON s.id_osoba=d.id_osoba
                  JOIN pobyt AS p USING (id_pobyt)
                  WHERE p.id_akce=$ida AND x.id_order=$idd
                  ORDER BY x.pokoj
                ");
              while ($rs && (list($ids,$idp,$pokoj)= pdo_fetch_array($rs))) {
                query("UPDATE spolu SET pokoj='$pokoj' WHERE id_spolu=$ids");
                $pokoje[$idp]= isset($pokoje[$idp]) ? "{$pokoje[$idp]},$pokoj" : $pokoj; 
              }
              foreach ($pokoje as $idp=>$str) {
                query("UPDATE pobyt SET pokoj='$str' WHERE id_pobyt=$idp");
              }
            }
          }
        }
        if (!$spojeno) {
//          continue;
          // každé objednávce která je v roce 2023 a dřív a má nějaké ds_osoba vytvoříme akci
          $tit= "$idd - $name";
          $ida= query_track("INSERT INTO akce (access,druh,nazev,misto,datum_od,datum_do,ma_cenik) "
              . "VALUE ($org_ds,64,'$tit','Dům setkání','$od','$do',2)");
          // objednávka je fakticky pobytová ale vytvoříme záznam v tabulce akce
          $npo= 0;
          $rp= pdo_qry("
            SELECT rodina,GROUP_CONCAT(DISTINCT pokoj ORDER BY pokoj)
            FROM $setkani_db.ds_osoba 
            WHERE id_order=$idd 
            GROUP BY rodina ORDER BY rodina");
          while ($rp && (list($rod,$pokoje)= pdo_fetch_array($rp))) {  
            // pro každou "rodinu" přidáme pobyt, pokud má aspoň jednodho hosta
            $n= select('COUNT(*)',"$setkani_db.ds_osoba","id_order=$idd AND rodina='$rod'");
            if (!$n) continue;
            $idp= query_track("INSERT INTO pobyt (id_akce,funkce,pracovni,pokoj)"
              . " VALUE ($ida,7,'objednávka:$idd, rodina=$rod, stav=$state: $note','$pokoje')");
//              display("!! POBYT=$idp/$ida: -$rod- $note");
            $np++;
            $npo++;
            $rs= pdo_qry("
              SELECT ds_db.id_osoba,ds_osoba,pokoj 
              FROM $setkani_db.ds_osoba AS d
                JOIN ds_db ON ds_db.ds_osoba=d.id_osoba
              WHERE id_order=$idd AND rodina='$rod'
            ");
            while ($rs && (list($ido,$idds,$pokoj)= pdo_fetch_array($rs))) {  
              // přidáme hosty
              $ids= query_track("INSERT INTO spolu (id_pobyt,id_osoba,pokoj) 
                VALUE ($idp,$ido,'$pokoj')");
              query("UPDATE ds_db SET id_spolu=$ids WHERE id_osoba=$ido AND ds_osoba=$idds");
              $ns++;
            }
          }
          // svážeme objednávku s akcí
          $ds_info= $npo ? 'ds' : '-';
          query("UPDATE $setkani_db.tx_gnalberice_order "
              . "SET id_akce=$ida,DS2024=\"{'typ':'hoste','pokoje':'$ds_info'}\" WHERE uid=$idd");
        }
      }
      $html.= "<br><br>Do ezer_db2 bylo přidáno $no objednávek jako akce s $np pobyty $ns osob ($na akcí už v databázi je)
        - převedeny objednávky v Domě setkání od roku $od_roku";
      if ($err) 
        $html.= "<hr>$err";
      break;
  }
  return $html;
}
