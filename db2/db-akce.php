<?php # (c) 2008-2025 Martin Smidek <martin@smidek.eu>
//define("EZER_VERSION","3.3");  

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
# --------------------------------------------------------------------------------------- akce2 info
# rozšířené informace o akci
# text=1 v textové verzi, text=0 jako objekt s počty
# pobyty=1 pokud chceme jména pro přehledové tabulky - má smysl jen pro text=0 - potom
#   info.pobyty= [ {idp:id_pobyt, prijmeni, jmena, hnizdo:n, typ:vps|nov|rep|tym|nah, 
#                   deti:seznam věků dětí a chův - těch s §}, ...]
#   info.pecouni= [ {ids, hnizdo, prijmeni, jmeno}, ... ]
function akce2_info($id_akce,$text=1,$pobyty=1,$id_order=0) { trace(); 
  global $setkani_db;
  $nf= function($n) { return number_format($n, 0, '.', '&nbsp;'); };
  $html= '';
  $access= $uid= 0; // org_ds (64) znamená čístě jen pobyt na Domu setkání
  $info= (object)array('muzi'=>0,'zeny'=>0,'deti'=>0,'peco'=>0,'rodi'=>0,'skup'=>0,'title'=>array());
  $zpusoby= map_cis('ms_akce_platba','zkratka');  // způsob => částka
  $stavy=   map_cis('ms_platba_stav','zkratka');     // stav úhrady
//  debug($stavy,'map uhrada_stav');
  $funkce=  map_cis('ms_akce_funkce','zkratka');  // funkce na akci
  $pfunkce= map_cis('ms_akce_pfunkce','zkratka'); // funkce pečovatele na akci
  $bad= "<b style='color:red'>!!!</b>";
  $uhrady= $uhrady_d= $fces= $pob= array();
  $pfces= array_fill(0,9,0);
  $celkem= $uhrady_celkem= $uhrady_odhlasenych= $uhradit_celkem= $vratit_celkem= 0;
  $vraceno= $dotace_celkem= $dary_celkem= 0;
  $aviz= 0;
  $_hnizda= '';  $hnizda= array(); $hnizdo= array(); $a_bez_hnizd= 1;
  $soubeh= 0; // hlavní nebo souběžná akce
  $pro_pary= 0;
  if ( $id_akce ) {
    $ucasti= $rodiny= $dosp= $muzi= $mrop= $zeny= $chuvy_d= $chuvy_p= $deti= $pecounu= $pp= $po= $pg= $web= 0;
    $vic_ucasti= $divna_rodina= '';
    $err= $err2= $err3= $err4= $err_h= $err_r= 0;
    $odhlaseni= $neprijati= $neprijeli= $nahradnici= $nahradnici_osoby= 0;
    $akce= $chybi_nar= $chybi_sex= '';
    // web_changes= 1/2 pro INSERT/UPDATE pobyt a spolu | 4/8 pro INSERT/UPDATE osoba
    $web_online= 0;     // web_changes>0
    $web_novi= 0;       // web_changes&4
    $web_kalendar= $web_obsazeno= $web_anotace= $web_url= '';
    // pro akce typu MS budeme podrobnější (a delší)
    list($druh,$a_hnizda)= select('druh,hnizda','akce',"id_duakce=$id_akce");
    $a_hnizda= explode(',',$a_hnizda);
//                          debug($a_hnizda,"hnízda podle akce: ");
    $a_bez_hnizd= count($a_hnizda)==1;
    $akce_ms= $druh==1||$druh==2 ? 1 : 0;
    // zjistíme hodnoty hnízd u účastníků a 
    // pokud akce není v hnízdech simulujeme je - kvůli opravám (mohla být plánována do hnízd)
    $p_hnizda= select('GROUP_CONCAT(DISTINCT hnizdo ORDER BY hnizdo)','pobyt',"id_akce='$id_akce'");
    $p_hnizda= explode(',',$p_hnizda);
//                          debug($p_hnizda,"hnízda podle pobytů: ");
    if (count($a_hnizda)!=count($p_hnizda)) {
      $err_h++;
    }
    // zjistíme násobnou přítomnost osob (jako detekci chyby)
    $rn= pdo_qry("
      SELECT COUNT(DISTINCT id_pobyt) AS _n,MIN(funkce) AS _f1,MAX(funkce) AS _f2,prijmeni,jmeno
      FROM spolu AS s
      LEFT JOIN pobyt AS p USING (id_pobyt)
      LEFT JOIN osoba AS o USING (id_osoba)
      WHERE id_akce='$id_akce'
      GROUP BY id_osoba HAVING _n>1
    ");
    while ( $rn && (list($n,$f1,$f2,$prijmeni,$jmeno)= pdo_fetch_row($rn)) ) {
      $err3++;
      $vic_ucasti.= ", $jmeno $prijmeni";
      if ( $f1<99 && $f2==99 ) $vic_ucasti.= " (jako dítě a jako pečovatel)";
    }
    // pokud chceme jména pro přehledové tabulky - má smysl jen pro tisk=0
    $fld_pobyty= $pobyty
        ? "IFNULL(r.nazev,o.prijmeni) AS _prijmeni,
           IF(ISNULL(r.id_rodina),o.jmeno,GROUP_CONCAT(IF(t.role IN ('a','b'),
             o.jmeno,'') ORDER BY t.role SEPARATOR ' ')) AS _jmena,
           IF(ISNULL(r.id_rodina),'',GROUP_CONCAT(IF(t.role NOT IN ('a','b'),CONCAT(
               IF(s.s_role=5 AND t.role='d','*',''),
               IF(s.s_role=5 AND t.role='p','§',''),
               YEAR(a.datum_od)-YEAR(o.narozeni)-(DATE_FORMAT(a.datum_od,'%m%d')<DATE_FORMAT(o.narozeni,'%m%d')))
             ,'') ORDER BY o.narozeni DESC)) AS _vekdeti"
        : '1';
    // projdeme pobyty
    $fce_neucast= select('GROUP_CONCAT(data)','_cis',"druh='ms_akce_funkce' AND ikona=1");
    $ms_ucasti= $akce_ms
        ? "r_ms+( SELECT COUNT(*) FROM pobyt AS xp JOIN akce AS xa ON xp.id_akce=xa.id_duakce 
             WHERE xp.i0_rodina=p.i0_rodina AND xa.druh=1 AND zruseno=0
               AND xp.id_pobyt!=p.id_pobyt AND xp.funkce NOT IN ($fce_neucast)) AS _ucasti_ms"
        : '0 AS _ucasti_ms';
    $JOIN_tvori= $pobyty 
        ? "LEFT JOIN tvori AS t USING (id_osoba,id_rodina)" : '';
    $JOIN_rodina= $akce_ms || $pobyty
        ? "LEFT JOIN rodina AS r ON r.id_rodina=p.i0_rodina" : '';
    $fld_dum= $setkani_db ? 'uid,' : '';
    $JOIN_dum= $setkani_db ? "LEFT JOIN $setkani_db.tx_gnalberice_order AS d ON d.id_akce=a.id_duakce" : '';
    $qry= "SELECT $fld_dum a.access, a.nazev, a.datum_od, a.datum_do, now() as _ted,i0_rodina,funkce,p.web_zmena,web_changes,
             COUNT(id_spolu) AS _clenu,IF(c.ikona=2,1,0) AS _pro_pary,a.hnizda,p.hnizdo,
         --  SUM(IF(ROUND(IF(MONTH(o.narozeni),DATEDIFF(a.datum_od,o.narozeni)/365.2425,YEAR(a.datum_od)-YEAR(o.narozeni)),1)<18,1,0)) AS _deti,
             SUM(IF(t.role='d',1,0)) AS _deti,
             SUM(IF(t.role='b',1,0)) AS _manzelky, SUM(IF(t.role='a',1,0)) AS _manzele,
             r.nazev AS _nazev_rodiny,
             SUM(IF(CEIL(IF(MONTH(o.narozeni),DATEDIFF(a.datum_od,o.narozeni)/365.2425,YEAR(a.datum_od)-YEAR(o.narozeni)))<=3,1,0)) AS _kocar,
             SUM(IF(ROUND(IF(MONTH(o.narozeni),DATEDIFF(a.datum_od,o.narozeni)/365.2425,YEAR(a.datum_od)-YEAR(o.narozeni)),1)>=18 AND sex=1,1,0)) AS _muzu,
             SUM(IF(iniciace>0,1,0)) AS _mrop,
             SUM(IF(ROUND(IF(MONTH(o.narozeni),DATEDIFF(a.datum_od,o.narozeni)/365.2425,YEAR(a.datum_od)-YEAR(o.narozeni)),1)>=18 AND sex=2,1,0)) AS _zen,
         --  SUM(IF(s.s_role=5 AND s.pfunkce=0,1,0)) AS _chuv,
             SUM(IF(s.s_role=5 AND s.pfunkce=0 AND t.role='d',1,0)) AS _chuv_d,
             SUM(IF(s.s_role=5 AND s.pfunkce=0 AND t.role='p',1,0)) AS _chuv_p,
             SUM(IF(s.s_role=5 AND s.pfunkce=5,1,0)) AS _po,
             SUM(IF(s.s_role=4 AND s.pfunkce=4,1,0)) AS _pp,
             SUM(IF(               s.pfunkce=8,1,0)) AS _pg,
             SUM(IF(s.pfunkce=1,1,0)) AS _p1,
             SUM(IF(s.pfunkce=2,1,0)) AS _p2,
             SUM(IF(s.pfunkce=3,1,0)) AS _p3,
             SUM(IF(s.pfunkce=4,1,0)) AS _p4,
             SUM(IF(s.pfunkce=5,1,0)) AS _p5,
             SUM(IF(s.pfunkce=6,1,0)) AS _p6,
             SUM(IF(s.pfunkce=7,1,0)) AS _p7,
             SUM(IF(s.pfunkce=8,1,0)) AS _p8,
             SUM(IF(o.narozeni='0000-00-00',1,0)) AS _err,
             GROUP_CONCAT(IF(o.narozeni='0000-00-00',CONCAT(', ',jmeno,' ',prijmeni),'') SEPARATOR '') AS _kdo,
             SUM(IF(o.sex NOT IN (1,2),1,0)) AS _err2,
             GROUP_CONCAT(IF(o.sex NOT IN (1,2),CONCAT(', ',jmeno,' ',prijmeni),'') SEPARATOR '') AS _kdo2,
             -- avizo,platba,datplatby,zpusobplat,
             p.platba1+p.platba2+p.platba3+p.platba4 AS _platit,
             vratka1+vratka2+vratka3+vratka4 AS _vratit,
             p.platba4-vratka4 AS _dotace,
             IFNULL(sa.id_duakce,0) AS _soubezna, a.id_hlavni AS _hlavni,
             a.web_kalendar,a.web_anotace, a.web_url, a.web_obsazeno, $ms_ucasti,
             p.id_pobyt,$fld_pobyty
           FROM akce AS a
           LEFT JOIN akce AS sa ON sa.id_hlavni=a.id_duakce
           JOIN pobyt AS p ON a.id_duakce=p.id_akce
           JOIN spolu AS s ON p.id_pobyt=s.id_pobyt
           JOIN osoba AS o USING (id_osoba) -- ON s.id_osoba=o.id_osoba
           $JOIN_rodina
           $JOIN_tvori
           LEFT JOIN _cis AS c ON c.druh='ms_akce_typ' AND c.data=a.druh
           $JOIN_dum
           WHERE a.id_duakce='$id_akce' AND o.deleted=''
             -- AND p.id_pobyt IN (59240,59318)
           GROUP BY p.id_pobyt";
    $res= pdo_qry($qry);
    while ( $res && $p= pdo_fetch_object($res) ) {
      $access= $p->access;
      $uid= $p->uid;
      $pobyt= null;
      // diskuse souběhu: 0=normální akce, 1=hlavní akce, 2=souběžná akce
      $soubeh= $p->_soubezna ? 1 : ( $p->_hlavni ? 2 : 0);
      $fce= $p->funkce;
      if ($pobyty && !in_array($fce,array(10,13,14,99)))
        $pobyt= (object)array('idp'=>$p->id_pobyt,'prijmeni'=>$p->_prijmeni,'jmena'=>$p->_jmena,
            'deti'=>preg_replace("~,,~",',',preg_replace("~^,*|,*$~",'',$p->_vekdeti)),
            'hnizdo'=>$p->hnizdo, 'fce'=>$fce);
      $pro_pary= $p->_pro_pary;
      $web_kalendar= $p->web_kalendar;
      $web_anotace= $p->web_anotace;
      $web_obsazeno= $p->web_obsazeno;
      $web_url= $p->web_url;
      // kontrola rolí
      if ($p->_manzelky>1 || $p->_manzele>1) {
        $divna_rodina.= ", $p->_nazev_rodiny";
        $err_r++;
      }
      // počty v hnízdech
      $hn= $a_bez_hnizd ? 1 : $p->hnizdo;
      if (!isset($hnizdo[$hn]))
        $hnizdo[$hn]= array('vps'=>0, 'nov'=>0, 'rep'=>0, 'nah'=>0,
            'pec'=>0, 'det'=>0, 'koc'=>0, 'dos'=>0, 'chu_d'=>0, 'chu_p'=>0);
      if (!in_array($fce,array(10,13,14,15,99))) { // 15+14+10+13 neúčast, 99 pečouni, 9 náhradník
        $hnizdo[$hn]['vps']+= ($fce==1||$fce==2) ? 1 : 0;
        $hnizdo[$hn]['nov']+= $fce==0 && $p->_ucasti_ms==0 ? 1 : 0;
        $hnizdo[$hn]['rep']+= $fce==0 && $p->_ucasti_ms>0 ? 1 : 0;
        $hnizdo[$hn]['dos']+= $p->_muzu+$p->_zen;
        $hnizdo[$hn]['chu_d']+= $p->_chuv_d;
        $hnizdo[$hn]['chu_p']+= $p->_chuv_p;
        $hnizdo[$hn]['det']+= $p->_deti;
        $hnizdo[$hn]['koc']+= $p->_kocar;
        $hnizdo[$hn]['nah']+= $fce==9 ? 1 : 0;
        if ($pobyty && $pobyt!==null) {
          $pobyt->typ= ($fce==1||$fce==2) ? 'vps' : ($fce==0 
              ? ($p->_ucasti_ms==0 ? 'nov' : 'rep') : ($fce==9 ? 'nah' : 'tym'));
        }
      }
      // online přihlášky
      if ( $p->web_zmena!='0000-00-00' || $p->web_changes ) {
        $web++;
        if ( $p->web_changes )   $web_online++;
        if ( $p->web_changes&4 ) $web_novi++;
      }
/*      
      // sčítání úhrad 
      $uhradit= $p->_platit - $p->_vratit;
      $vratit_celkem+= $p->_vratit;
      $uhradit_celkem+= $uhradit;
      $dotace_celkem+= $p->_dotace;
      $zaplaceno= 0;
      $ru= pdo_qry("SELECT u_castka,u_zpusob,u_stav,u_za FROM uhrada WHERE id_pobyt=$p->id_pobyt");
      while ( $ru && list($u_castka,$u_zpusob,$u_stav,$u_za)= pdo_fetch_row($ru) ) {
        $zaplaceno+= $u_castka;
        if ($u_za) {
          if (!isset($uhrady_d[$u_zpusob][$u_stav])) $uhrady_d[$u_zpusob][$u_stav]= 0;
          $uhrady_d[$u_zpusob][$u_stav]+= $u_castka;
        }
        else {
          if (!isset($uhrady[$u_zpusob][$u_stav])) $uhrady[$u_zpusob][$u_stav]= 0;
          $uhrady[$u_zpusob][$u_stav]+= $u_castka;
        }
        $uhrady_celkem+= $u_castka;
        if (in_array($fce,array(9,10,13,14,15) )) 
          $uhrady_odhlasenych+= $u_castka;
        if ($u_stav==4) 
          $vraceno+= $u_castka;
      }
      if ($uhradit)
        $dary_celkem+= $zaplaceno > $uhradit ? $zaplaceno - $uhradit : 0;
 * 
 */
      // diskuse funkce=odhlášen/14 a funkce=nepřijel/10 a funkce=náhradník/9 a nepřijat/13
      if ( in_array($fce,array(9,10,13,14,15) ) ) {
        $neprijati+= $fce==13 ? 1 : 0;
        $odhlaseni+= $fce==14 ? 1 : 0;
        $neprijeli+= $fce==10 ? 1 : 0;
        $nahradnici+= $fce==9 ? 1 : 0;
        $nahradnici_osoby+= $fce==9 ? $p->_clenu : 0;
      }
      // diskuse pečouni
      else if ( $fce==99 ) {
//        $hnizdo[$hn]['pec']+= $p->_clenu;
        $pecounu+= $p->_clenu;
        $pfces[1]+= $p->_p1;
        $pfces[2]+= $p->_p2;
        $pfces[3]+= $p->_p3;
        $pfces[4]+= $p->_p4;
        $pfces[5]+= $p->_p5;
        $pfces[6]+= $p->_p6;
        $pfces[7]+= $p->_p7;
        $pfces[8]+= $p->_p8;
        // pro akci v hnízdech zjisti strukturu pečounů
//        if ( $p->hnizda ) {
          $info->pecouni= array();
          $rp= pdo_qry("
            SELECT id_spolu,s_hnizdo,jmeno,prijmeni FROM spolu JOIN osoba USING (id_osoba)
            WHERE id_pobyt=$p->id_pobyt 
          ");
          while ( $rp && (list($ids,$s_hnizdo,$jmeno,$prijmeni)= pdo_fetch_row($rp)) ) {
            $info->pecouni[]= (object)array('ids'=>$ids,
                'hnizdo'=>$s_hnizdo,'prijmeni'=>$prijmeni,'jmeno'=>$jmeno);
            $s_hnizdo= $a_bez_hnizd ? 1 : $s_hnizdo;
            $hnizdo[$s_hnizdo]['pec']++;
          }
//        }
      }
      else if ( !in_array($fce,array(0,1,2,5) ) ) {
        if (!isset($fces[$fce])) $fces[$fce]= 0;
        $fces[$fce]++;
        $muzi+= $p->_muzu;
        $mrop+= $p->_mrop;
        $zeny+= $p->_zen;
      }
      else {
        // údaje účastníků jednoho pobytu
        $ucasti++;
        $muzi+= $p->_muzu;
        $mrop+= $p->_mrop;
        $zeny+= $p->_zen;
        $chuvy_d+= $p->_chuv_d;
        $chuvy_p+= $p->_chuv_p;
        $pp+= $p->_pp;
        $po+= $p->_po;
        $pg+= $p->_pg;
        $deti+= $p->_deti;
        $err+= $p->_err;
        $err2+= $p->_err2;
        $rodiny+= $p->i0_rodina && $p->_clenu>1 ? 1 : 0;
        $chybi_nar.= $p->_kdo;
        $chybi_sex.= $p->_kdo2;
      }
      // údaje akce
      $akce= $p->nazev;
      $cas1= $p->_ted>$p->datum_od ? "byla" : "bude";
      $cas2= $p->_ted>$p->datum_od ? "Akce se zúčastnilo" : "Na akci je přihlášeno";
      $od= sql_date1($p->datum_od);
      $do= sql_date1($p->datum_do);
      $dne= $p->datum_od==$p->datum_do ? "dne $od" : "ve dnech $od do $do";
      if ($akce_ms && !$p->hnizda && !$hnizda) {
        // simulace hnízd
        $hnizda= array(1); 
        $info->hnizda= $hnizda;
      }
      elseif ( $p->hnizda && !$hnizda ) {
        $hnizda= explode(',',$p->hnizda);
        $n_h= count($hnizda);
        $_hnizda= " <b>ve $n_h hnízdech</b>";
        array_unshift($hnizda,"<i>nezařazeno</i>"); 
        $info->hnizda= $hnizda;
      }
      // pokud se chtějí pobyty
      if ($pobyty && $pobyt) {
        $pob[]= $pobyt;
      }
    }
    if ( $chybi_nar ) $chybi_nar= substr($chybi_nar,2);
    if ( $chybi_sex ) $chybi_sex= substr($chybi_sex,2);
    if ( $vic_ucasti ) $vic_ucasti= substr($vic_ucasti,2);
    if ( $divna_rodina ) $divna_rodina= substr($divna_rodina,2);
    display("dicná rodina: $divna_rodina");
    $dosp+= $muzi + $zeny;
                              display("$dosp+= $muzi + $zeny");
    $skupin= $ucasti;
//    if ( $text ) {
      // čeština
      $_skupin=    je_1_2_5($skupin,"skupina,skupiny,skupin");
      $_pecounu=   je_1_2_5($pecounu,"pečoun,pečouni,pečounů");
      $_pp=        je_1_2_5($pp,"pom.pečoun,pom.pečouni,pom.pečounů");
      $_po=        je_1_2_5($po,"os.pečoun,os.pečouni,os.pečounů");
      $_pg=        je_1_2_5($pg,"člen G,členi G,členů G");
      $_dospelych= je_1_2_5($dosp,"dospělý,dospělí,dospělých");
      $_muzu=      je_1_2_5($muzi,"muž,muži,mužů");
      $_iniciovani=je_1_2_5($mrop,"iniciovaný muž,iniciovaní muži,iniciovaných mužů");
      $_zen=       je_1_2_5($zeny,"žena,ženy,žen");
      $_chuv_d=    je_1_2_5($chuvy_d,"chůva,chůvy,chův");
      $_chuv_p=    je_1_2_5($chuvy_p,"chůva,chůvy,chův");
      $_deti=      je_1_2_5($deti,"dítě,děti,dětí");
      $_osob=      je_1_2_5($dosp+$deti,"osoba,osoby,osob");
      $_err=       je_1_2_5($err,"osoby,osob,osob");
      $_err2=      je_1_2_5($err2,"osoby,osob,osob");
      $_err3=      je_1_2_5($err3,"osoba je,osoby jsou,osob je");
      $_rodiny=    je_1_2_5($rodiny,"rodina,rodiny,rodin");
      $_pobyt_o=   je_1_2_5($odhlaseni,"pobyt,pobyty,pobytů");
      $_pobyt_r=   je_1_2_5($neprijati,"přihláška,přihlášky,přihlášek");
      $_pobyt_x=   je_1_2_5($neprijeli,"pobyt,pobyty,pobytů");
      $_pobyt_n=   je_1_2_5($nahradnici,"přihláška,přihlášky,přihlášek");
      $_pobyt_no=  je_1_2_5($nahradnici_osoby,"osoba,osoby,osob");
      $_aviz=      je_1_2_5($aviz,"avízo,avíza,avíz");
      $_web_onln=  je_1_2_5($web_online,"pobyt,pobyty,pobytů");
      $_web_novi=  je_1_2_5($web_novi,"nová osoba,nové osoby,nových osob");
      // sloužili
      $sluzba= ''; $del= '<br>a dále '; 
      foreach ($fces as $f=>$fn) {
        $sluzba.= "$del $fn x ".$funkce[$f];
        $del= " a ";
      }
      // typy pečounů
      $sluzba= ''; $del= ' z toho '; 
      foreach ($pfces as $f=>$fn) { if ( $fn ) {
        $sluzba.= "$del $fn x ".$pfunkce[$f];
        $del= " a ";
      }}
      $sluzba2= ''; $del= ''; 
      if ( $pp ) { $sluzba2.= "$del $_pp"; $del= ' a '; }
      if ( $po ) { $sluzba2.= "$del $_po"; $del= ' a '; }
      if ( $pg ) { $sluzba2.= "$del $_pg"; $del= ' a '; }
      // jsou děti pečouni ok?
      $prehled= akce2_text_prehled($id_akce,'');
      if ( $prehled->pozor ) $err4++; 
      // html
      $html= $dosp+$deti>0
       ? $html.= "<h3 style='margin:0px 0px 3px 0px;'>$akce</h3>akce $cas1 $dne $_hnizda<br><hr>$cas2"
       . ($skupin ? " $_skupin účastníků"
           .($rodiny ? ($rodiny==$ucasti ? " (všechny jako rodiny)" : " (z toho $_rodiny)") :''):'')
       . ($pecounu ? " ".($skupin?" a ":'')."$_pecounu" : '')
       . $sluzba
       . ($pp+$po+$pg ? " + do skupiny pečounů patří navíc $sluzba2" : '')
       . ",<br>v počtu $_dospelych ($_muzu, $_zen)"
       . ($chuvy_d ? " a $_deti (z toho $_chuv_d)," : " a $_deti")
       . ($chuvy_p ? " a $_chuv_p dospělých," : '')
       . "<br><b>celkem $_osob</b>"
      . ( $web_online ? "<hr>přihlášky z webu: $_web_onln".($web_novi ? ", z toho $_web_novi" : '') : '')
       : "Akce byla vložena do databáze ale nemá zatím žádné účastníky";
      if ( $akce_ms && ($_hnizda || $a_bez_hnizd) ) {
        $html.= (isset($p->hnizda) && $p->hnizda ? ", takto rozřazených do hnízd" : "") . " - stiskem zobraz
          <button onclick=\"Ezer.fce.href('akce2.lst.ukaz_hnizda/$id_akce')\">
          jmenný seznam</button>";
        $html.= $a_bez_hnizd ? '' : "<ul>";
//                                                                debug($hnizda); 
//                                                                debug($hnizdo); 
        for ($h= 0; $h<count($hnizda); $h++) {
          $hn= $a_bez_hnizd ? 1 : ($h+1 % count($hnizda)-1);
          // počty a odhad
          $n_vps= $hnizdo[$hn]['vps'];
          $n_nov= $hnizdo[$hn]['nov'];
          $n_rep= $hnizdo[$hn]['rep'];
          $n_dos= $hnizdo[$hn]['dos'];
          // úvaha o pečounech: potřebujeme na 1 kočárek = 1 pečoun; na 3 nekočárky = 1 pečoun
          $n_det= $hnizdo[$hn]['det'];
          $n_koc= $hnizdo[$hn]['koc'];
          $n_chu_d= $hnizdo[$hn]['chu_d'];
          $n_chu_p= $hnizdo[$hn]['chu_p'];
          $n_pec= $hnizdo[$hn]['pec'];
          // chůvy napřed spotřebujeme na kočárky
          $koc_chu= min($n_koc,$n_pec);
          $koc= $n_koc-$koc_chu;
          $chu_navic= $n_chu_d + $n_chu_p - $koc_chu;
          $det= $n_det-$koc_chu; // neohlídaných dětí
          $nekoc= $det-$koc; // dětí bez kočárku
          $pec_odhad= $koc + round($nekoc/3) - $chu_navic;
          $x_pec= $pec_odhad>$n_pec ? $pec_odhad-$n_pec : 0;
          // počty česky
          $n_nov= je_1_2_5($n_nov,"nováček,nováčci,nováčků");
          $n_rep= je_1_2_5($n_rep,"repetent,repetenti,repetentů");
          $n_pec= $n_pec ? 'a '.je_1_2_5($n_pec,"pečoun,pečouni,pečounů") : '';
          $x_pec= $x_pec ? '(chybí asi '.je_1_2_5($x_pec,"pečoun,pečouni,pečounů").')' : '';
          $n_dos= je_1_2_5($n_dos,"dospělý,dospělí,dospělých");
          $n_det= $n_det!==''
              ? 'a '.je_1_2_5($n_det,"dítě,děti,dětí")
                .($n_chu_d ? " (z toho ".je_1_2_5($n_chu_d,"chůva,chůvy,chův").')' : '')
              : '';
          $n_dos= je_1_2_5($n_dos,"dospělý,dospělí,dospělých");
          // text
          $hniz= $h ? "<b>$hnizda[$h]</b>" : $hnizda[$h];
          $h_text= ($a_bez_hnizd ? '' : "$hniz - ")."$n_dos $n_det $n_pec $x_pec";
          if ($pobyty) {
            $info->title[$h]= "$h_text";
          }
          $html.= $a_bez_hnizd ? '<br>' : "<li>";
          $html.= $h_text;
          $html.= "<br>páry: $n_vps VPS, $n_nov, $n_rep";
        }
        $html.= $a_bez_hnizd ? '' : "</ul>";
      }
      if ( $odhlaseni + $neprijati + $neprijeli + $nahradnici > 0 ) {
        $html.= "<br><hr>";
        $msg= array();
        if ( $neprijati ) $msg[]= "nepřijato: $_pobyt_r";
        if ( $odhlaseni ) $msg[]= "odhlášeno: $_pobyt_o (bez storna)";
        if ( $neprijeli ) $msg[]= "zrušeno: $_pobyt_x (nepřijeli, aplikovat storno)";
        if ( $nahradnici ) $msg[]= "náhradníci: $_pobyt_n, celkem $_pobyt_no";
        $html.= implode('<br>',$msg);
      }
      if ( $err + $err2 + $err3 + $err4 + $err_h + $err_r > 0 ) {
        $html.= "<br><hr><b style='color:red'>POZOR:</b> ";
        $html.= $err_h>0 ? "<br>nesouhlasí počet hnízd v definici akce a v pobytech" : '';
        $html.= $err>0  ? "<br>u $_err chybí datum narození: <i>$chybi_nar</i>" : '';
        $html.= $err2>0 ? "<br>u $_err2 chybí údaj muž/žena: <i>$chybi_sex</i>" : '';
        $html.= $err_r>0 ? "<br>rodiny $divna_rodina jsou divné" : '';
        $html.= $err3>0 ? "<br>$bad $_err3 na akci vícekrát: <i>$vic_ucasti</i>" : '';
        $html.= $err4>0 ? "<br>{$prehled->pozor} (po nastavení akce jako pracovní kliknutí na odkaz ukáže rodinu)" : '';
        $html.= "<br><br>(kvůli nesprávným údajům budou počty, výpisy a statistiky také nesprávné)";
      }
      $html.= $deti ? "<hr>Poznámka: jako děti se počítají osoby, které v době zahájení akce nemají 18 let" : '';
      $html.= $mrop ? ", na akci je $_iniciovani." : '';
      if ( $pro_pary ) {
        $html.= akce2_info_par($id_akce);
      }
      // kalendář na webu
      if ( $web_kalendar ) {
        $odkaz= $web_url ? "na <a href='$web_url' target='web'>pozvánku</a>" : ' na home-page';
        $html.= "<hr><i class='fa fa-calendar-check-o'></i> 
            Akce $cas1 zobrazena v kalendáři <b>chlapi.online</b>
            s odkazem $odkaz";
        if ( $web_obsazeno )
          $html.= "<br> &nbsp;&nbsp; &nbsp; jako obsazená";
        if ( $web_anotace ) 
          $html.= "<hr>$web_anotace";
      }
//    }
//    else {
      $info->muzi= $muzi;
      $info->zeny= $zeny;
      $info->deti= $deti;
      $info->peco= $pecounu;
      $info->rodi= $rodiny;
      $info->skup= $skupin;
      $info->_pp=  $pp;
      $info->_po=  $po;
      $info->_pg=  $pg;
//    }
/*    
    // zobrazení přehledu plateb
//    debug($uhrady,"úhrady celkem $uhrady_celkem");
    $help= '';
    if ( /+!$soubeh &&+/ ($uhrady_celkem || $uhradit_celkem) ) {
      $st= "style='border-top:1px solid black'";
      if ($dary_celkem)
        $help.= "Hodnota darů bude správně až budou vráceny storna a přeplatky.<br>";
      $tab= $av= '';
      foreach ($stavy as $j=>$stav) {
        foreach ($zpusoby as $i=>$zpusob) {
          if ( isset($uhrady[$i][$j]) && $uhrady[$i][$j] )
            $tab.= "<tr><td>$stav $zpusob</td><td align='right'>".$nf($uhrady[$i][$j])."</td></tr>";
          if ( isset($uhrady_d[$i][$j]) && $uhrady_d[$i][$j] )
            $tab.= "<tr><td>$stav za děti $zpusob</td><td align='right'>".$nf($uhrady_d[$i][$j])."</td></tr>";
        }
      }
      display("vrátit=$vratit_celkem, vráceno=$vraceno");
      if ($vratit_celkem!=-$vraceno) 
        fce_warning("má být vráceno $vratit_celkem ale bankou bylo vráceno=$vraceno");
      if ($vratit_celkem && $vratit_celkem!=-$vraceno)
        $help.= "<br>Správnost vratek za storna je třeba kontrolovat v <b>Platba za akci</b>";
      else
        $help.= "<br>... nyní se to zdá být v pořádku (předpisy storna se rovnají jejich platbám)";
      if ($uhradit_celkem) {
        $bilance= $uhrady_celkem - $uhradit_celkem;
        $bilance_slev= $dary_celkem + $dotace_celkem;
        $sgn1= $bilance>0 ? '+' : '';
        $sgn2= $bilance_slev>0 ? '+' : '';
        $sgn3= $uhrady_odhlasenych>0 ? '+' : '';
        $av= $aviz ? "<td $st> a $_aviz platby</td>" : '<td></td>';
        $tab.= "<tr><td $st>ZAPLACENO</td><td align='right' $st><b>".$nf($uhrady_celkem)."</b></td>$av
          <td>dary</td><td align='right'>".$nf($dary_celkem)."</td></tr>";
        $tab.= "<tr><td>požadováno</td><td align='right'><b>".$nf($uhradit_celkem)."</b></td><td></td>
          <td>slevy</td><td align='right'>".$nf($dotace_celkem)."</td></tr>";
        $tab.= "<tr><td>rozdíl</td><td align='right' $st>$sgn1".$nf($bilance)."</td><td></td>
          <td>bilance</td><td align='right' $st><b>$sgn2".$nf($bilance_slev)."</b></td></tr>";
        if ($uhrady_odhlasenych) 
          $tab.= "<tr><td>(v tom odhlášení atp.</td><td align='right'>$sgn3".$nf($uhrady_odhlasenych)."</td>
            <td>)</td><td></td><td></td></tr>";
      }
      else {
        $tab.= "<tr><td $st>ZAPLACENO</td><td align='right' $st><b>".$nf($uhrady_celkem)."</b></td>$av</tr>";
      }
      if ($soubeh)
        $help.= "<br><br>Pro akce s tzv. souběhem to bude chtít výsledek konzultovat s Carlosem :-)";
      $html.= "<hr style='clear:both;'>
        <div style='float:right;width:150px'><i>$help</i></div>
        <h3 style='margin-bottom:3px;'>Přehled plateb za akci (z karty Účastníci)</h3>
        <table>$tab</table>
        <i>
          <br>'ZAPLACENO' se počítá z pravého sloupce karty Účastníci/Platby za akci
          <br>'požadováno' je částka požadovaná po účastnících po odpočtu vratek
          <br>'rozdíl' je hrubý rozdíl (obsahuje i nevrácené platby odhlášených atp.)
          <br>'dary' jsou přeplatky přítomných na akci, po odečtení slev dostaneme 'bilanci'
        </i>
          ";
    }
*/    
  }
  elseif (!$uid && !$id_order) {
    $html= "Tato akce ještě nebyla vložena do databáze
            <br><br>Vložení se provádí dvojklikem
            na řádek s akcí";
  }
  // pokud se chtějí pobyty
  if ($pobyty) {
    $info->pobyty= $pob;
//                                                                  debug($info,"info s pobyty");
  }
  // pokud je to pobyt na Domě setkání
  if ($uid || $id_order) {
    $html= dum_objednavka_info($id_order ?: $uid,$id_akce,$html,$id_order?1:0);
  }
  return $text ? $html : $info;
}
# --------------------------------------------------------------------------------------- akce2 id2a
# vrácení hodnot akce
function akce2_id2a($id_akce) {  //trace();
  global $USER;
  $a= (object)array('title'=>'?','cenik'=>0,'cena'=>0,'soubeh'=>0,'hlavni'=>0,'soubezna'=>0);
  list($a->title,$a->rok,$a->cenik,$a->cenik_verze,$a->cena,$a->hlavni,$a->soubezna,$a->org,
      $a->ms,$a->druh,$a->hnizda,$a->web_wordpress,$a->mezinarodni,$poradatel,$a->tym,
      $a->datum_od, $a->datum_do, $a->strava_oddo)=
    select("a.nazev,YEAR(a.datum_od),a.ma_cenik,a.ma_cenik_verze,a.cena,a.id_hlavni,"
      . "IFNULL(s.id_duakce,0),a.access,IF(a.druh IN (1,2),1,0),a.druh,a.hnizda,a.web_wordpress,"
      . "a.mezinarodni,a.poradatel,a.tym,a.datum_od,a.datum_do,a.strava_oddo",
      "akce AS a
       LEFT JOIN akce AS s ON s.id_hlavni=a.id_duakce",
      "a.id_duakce=$id_akce");
  # diskuse souběhu: 0=normální akce, 1=hlavní akce, 2=souběžná akce
  $a->soubeh= $a->soubezna ? 1 : ( $a->hlavni ? 2 : 0);
  $a->rok= $a->rok ?: date('Y');
  $a->title.= ", {$a->rok}";
  // zjištění, zda uživatel je 1) garantem 2) garantem této akce
  $a->garant= 0;
  $skills= explode(' ',$USER->skills);
  if (in_array('g',$skills)) { // g-uživatel musí mít v číselníku akce_garant svoje id_user
    $idu= $USER->id_user;
    $data= select('data','_cis',"druh='akce_garant' AND ikona='$idu'");
    if ($data) {
      $a->garant= $poradatel==$data ? 2 : 1;
    }
  }
  // doplnění případného pobytu v Domě setkání
  global $setkani_db;
  if ($setkani_db && ($a->org==1 || $a->org & org_ds)) {
//  if ($setkani_db && $a->org==1) {
    $a->id_order= select('uid',"$setkani_db.tx_gnalberice_order","id_akce=$id_akce");
  }
//                                                                 debug($a,"akce $id_akce user {$USER->id_user}");
  return $a;
}
# ------------------------------------------------------------------------------ akce2 soubeh_nastav
# nastavení akce jako souběžné s jinou (která musí mít stejné datumy a místo konání)
function akce2_soubeh_nastav($id_akce,$nastavit=1) {  trace();
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
# ----------------------------------------------------------------------------- akce2 delete_confirm
# dotazy před zrušením akce
function akce2_delete_confirm($id_akce) {  trace();
  $ret= (object)array('zrusit'=>0,'ucastnici'=>'');
  // fakt zrušit?
  list($nazev,$misto,$datum)=
    select("nazev,misto,DATE_FORMAT(datum_od,'%e.%m.%Y')",'akce',"id_duakce=$id_akce");
  $ret->zrusit= "Opravdu smazat akci '$nazev, $misto' začínající $datum?";
  if ( !$nazev ) goto end;
  // má účastníky
  $ucastnici= select('COUNT(*)','pobyt',"id_akce=$id_akce");
  $pecouni= select('COUNT(*)','pobyt LEFT JOIN spolu USING (id_pobyt)',"id_akce=$id_akce AND funkce=99");
  $p= $pecouni ? " a $pecouni pečounů" : '';
  $ret->ucastnici= $ucastnici
    ? "Tato akce má již zapsáno $ucastnici účastníků$p. Mám akci označit jako zrušenou?"
    : '';
  // zjistíme, zda existuje pohled 
  global $answer_db;
  $existuje= select('COUNT(*)','information_schema.VIEWS',
      "TABLE_SCHEMA='$answer_db' AND TABLE_NAME='ds_order'");
  if ($existuje) {
    $idd= select1('IFNULL(id_order,0)','ds_order',"id_akce=$id_akce AND deleted=0");
    if ($idd) $ret->zrusit.= "<br>Současně bude zrušena i objednávka č.$idd v Domě setkání.";
  }
end:
  return $ret;
}
# ------------------------------------------------------------------------------------- akce2 delete
# zrušení akce
function akce2_delete($id_akce,$ret,$jen_zrušit=0) {  trace();
  $msg2= '.';
  // zjistíme, zda existuje pohled 
  global $answer_db;
  $existuje= select('COUNT(*)','information_schema.VIEWS',
      "TABLE_SCHEMA='$answer_db' AND TABLE_NAME='ds_order'");
  if ($existuje) {
    $idd= select1('IFNULL(id_order,0)','ds_order',"id_akce=$id_akce AND deleted=0");
    if ($idd) {
      query("UPDATE ds_order SET deleted=1,id_akce=0 WHERE id_akce=$id_akce");
      $msg2= ", objednávka č.$idd byla zrušena.";
    }
  }
  $nazev= select("nazev",'akce',"id_duakce=$id_akce");
  if ($jen_zrušit) {
    query("UPDATE akce SET zruseno=1 WHERE id_duakce=$id_akce");
    $msg= "Akce '$nazev' byla zrušena$msg2";
    goto end;
  }
  if ( $ret->ucastnici ) {
    // napřed zrušit účasti na akci
    $rs= query("DELETE FROM spolu USING spolu JOIN pobyt USING(id_pobyt) WHERE id_akce=$id_akce");
    $s= pdo_affected_rows($rs);
    $rs= query("DELETE FROM pobyt WHERE id_akce=$id_akce");
    $p= pdo_affected_rows($rs);
  }
  $rs= query("DELETE FROM akce WHERE id_duakce=$id_akce");
  $a= pdo_affected_rows($rs);
  $msg= $a
    ? "Akce '$nazev' byla smazána" . ( $p+$s ? ", včetně $p účastí $s účastníků" : '')
    : "CHYBA: akce '$nazev' nebyla smazána";
  $msg.= $msg2;
end:
  return $msg;
}
# -------------------------------------------------------------------------------- akce2 hnizda_test
# seznam hnízd musí začínat různým písmenem
function akce2_hnizda_test($hnizda_seznam) {  
  $msg= '';
  $hnizda= preg_split("/\s*,\s*/",$hnizda_seznam);
  if ( !$hnizda_seznam || count($hnizda)<2 ) {
    $msg= "zadejte jména hnízd jako seznam oddělený čárkami"; goto end;
  }
  $h= array();
  foreach($hnizda as $hnizdo) {
    $h0= strtoupper($hnizdo[0]);
    if ( !strlen($h0) ) {
      $msg= "v seznamu hnízd je čárka navíc"; goto end;
    }
    if ( in_array($h0,$h) ) {
      $msg= "hnízda musí mít různá první písmena"; goto end;
    }
    $h[]= $h0;
  }
end:  
  return $msg;
}
# ----------------------------------------------------------------------------- akce2 hnizda_options
# vrátí definici mapy: hnizdo=klíč,zkratka,nazev
# spoléhá na korektní definici
# pokud x=1 přidá všichni+nezařazení, pokud x=0 bude 0-nezařazení, pokud x=2 bude 0-všichni
function akce2_hnizda_options($hnizda_seznam,$x=0) {  
  if (!$hnizda_seznam) return '';
  $hnizda= preg_split("/\s*,\s*/",$hnizda_seznam);
  $text= $x==1 ? "99::všichni,0:-:nezařazení" : ($x==0 ? '0:?:nezařazení' : '0::všichni');
  foreach($hnizda as $i=>$hnizdo) {
    $h0= strtoupper($hnizdo[0]);
    $i1= $i+1;
    $text.= ",$i1:$h0:$hnizdo";
  }
  return $text;
}
# ==================================================================================> . mrop firming
# ------------------------------------------------------------------------------- akce2 confirm_mrop
# zjištění, zda lze účastníků akce zapsat běžný rok jako datum iniciace
# zapsání roku iniciace účastníkům akce (write=1)
function akce2_confirm_mrop($ida,$write=0) {  trace();
  $ret= (object)array('ok'=>0,'msg'=>'ERROR');
  $letos= date('Y');
  if ( !$write ) {
    // jen sestavení confirm
    $ra= pdo_qry("
      SELECT nazev, mrop, YEAR(datum_od) AS _rok,
        SUM((SELECT IF(COUNT(*)>0,1,0) FROM spolu JOIN osoba USING (id_osoba)
          WHERE id_pobyt=p.id_pobyt AND funkce=0 AND iniciace IN (0,$letos))) as _mladi,
        SUM((SELECT IF(COUNT(*)>0,1,0) FROM spolu JOIN osoba USING (id_osoba)
          WHERE id_pobyt=p.id_pobyt AND funkce=0 AND iniciace NOT IN (0,$letos))) as _starsi
      FROM akce AS a
      LEFT JOIN pobyt AS p ON p.id_akce=a.id_duakce
      WHERE id_duakce=$ida
      GROUP BY id_duakce
    ");
    if ( !$ra ) goto end;
    list($nazev,$mrop,$rok,$mladi,$starsi)= pdo_fetch_array($ra);
    $ret->ok= $mrop && $rok==$letos && !$starsi && $mladi;
    $ret->msg= $ret->ok
      ? "Opravdu mám pro $mladi účastníků akce <b>\"$nazev/$rok\"</b>
        potvrdit rok $letos jako rok jejich iniciace (MROP)?"
      : "CHYBA";
  }
  else {
    // zápis roku iniciace, včetně záznamu do _track
    global $USER;
    $user= $USER->abbr;
    $now= date("Y-m-d H:i:s");
    $n= $n1= $n2= 0;
    $ra= pdo_qry("
      SELECT COUNT(*),GROUP_CONCAT(id_osoba)
      FROM pobyt AS p
      JOIN spolu USING (id_pobyt)
      JOIN osoba USING (id_osoba)
      WHERE id_akce=$ida AND funkce=0 AND iniciace IN (0)
      GROUP BY id_akce
    ");
    if ( !$ra ) goto end;
    list($n,$ids)= pdo_fetch_array($ra);
    if ( $n ) {
      $rs= query("UPDATE osoba SET iniciace=$letos WHERE id_osoba IN ($ids)");
      $n1= pdo_affected_rows($rs);
      // zápis do _track
      foreach ( explode(',',$ids) as $ido) {
        $rs= query("INSERT INTO _track (kdy,kdo,kde,klic,fld,op,old,val)
               VALUES ('$now','$user','osoba',$ido,'iniciace','u','0','$letos')");
        $n2+= pdo_affected_rows($rs);
      }
    }
    $ret->ok= $n>0 && $n==$n1 && $n==$n2;
    $ret->msg= $ret->ok
      ? "$n účastníkům byl zapsán rok $letos jako rok jejich iniciace"
      : "ERROR ($n,$n1,$n2)";
  }
end:
  return $ret;
}
# ------------------------------------------------------------------------------- akce2 confirm_firm
# zjištění, zda lze účastníků akce zapsat datum posledního firmingu
# zapsání roku posledního firmingu účastníkům akce (write=1) + hosdpodáři + lektoři
function akce2_confirm_firm($ida,$write=0) {  trace();
  $ret= (object)array('ok'=>0,'msg'=>'ERROR');
  if ( !$write ) {                                                            
    // jen sestavení confirm
    $ra= pdo_qry("
      SELECT nazev, firm, YEAR(datum_od) AS _rok,
        SUM((SELECT IF(COUNT(*)>0,1,0) FROM spolu JOIN osoba USING (id_osoba)
          WHERE id_pobyt=p.id_pobyt AND funkce IN (0,5,12))) as _sloni
      FROM akce AS a
      LEFT JOIN pobyt AS p ON p.id_akce=a.id_duakce
      WHERE id_duakce=$ida
      GROUP BY id_duakce
    ");
    if ( !$ra ) goto end;
    list($nazev,$firm,$rok,$sloni)= pdo_fetch_array($ra);
    $ret->ok= $firm && $sloni;
    $ret->msg= $ret->ok
      ? "Opravdu mám pro $sloni účastníků akce <b>\"$nazev/$rok\"</b>
        zapsat rok $rok jako účast na firmingu?"
      : "CHYBA";
  }
  else {
    // zápis roku firmingu, včetně záznamu do _track
    global $USER;
    $user= $USER->abbr;
    $now= date("Y-m-d H:i:s");
    $n= $n1= $n2= 0;
    $ra= pdo_qry("
      SELECT COUNT(*),GROUP_CONCAT(id_osoba), YEAR(a.datum_od) AS _rok
      FROM pobyt AS p
      JOIN akce AS a ON p.id_akce=a.id_duakce
      JOIN spolu USING (id_pobyt)
      JOIN osoba USING (id_osoba)
      WHERE id_akce=$ida AND funkce IN (0,5,12) AND firming!=YEAR(a.datum_od)
      GROUP BY id_akce
    ");
    if ( !$ra ) goto end;
    list($n,$ids,$rok)= pdo_fetch_array($ra);
    if ( $n ) {
      $rs= query("UPDATE osoba SET firming=$rok WHERE id_osoba IN ($ids)");
      $n1= pdo_affected_rows($rs);
      // zápis do _track
      foreach ( explode(',',$ids) as $ido) {
        $rs= query("INSERT INTO _track (kdy,kdo,kde,klic,fld,op,old,val)
               VALUES ('$now','$user','osoba',$ido,'firming','u','0','$rok')");
        $n2+= pdo_affected_rows($rs);
      }
    }
    else 
      $n= 0;
//    $ret->ok= $n>0 && $n==$n1 && $n==$n2;
    $ret->msg= "$n účastníkům byl doplněn rok $rok jako rok účasti na firmingu";
//      : "ERROR ($n,$n1,$n2)";
  }
end:
  return $ret;
}
# ====================================================================================> . ceník akce
# ------------------------------------------------------------------------------- akce2 select_cenik
# seznam akcí s ceníkem pro select
function akce2_select_cenik($id_akce) {  trace();
  $max_nazev= 30;
  mb_internal_encoding('UTF-8');
  $org= select('access','akce',"id_duakce=$id_akce");
  $options= 'neměnit:0';
  if ( $id_akce ) {
    $qa= "SELECT id_duakce, nazev, YEAR(datum_od) AS _rok FROM akce
          WHERE id_duakce!=$id_akce AND access=$org AND ma_cenik=1 ORDER BY datum_od DESC";
    $ra= pdo_qry($qa);
    while ($ra && $a= pdo_fetch_object($ra) ) {
      $nazev= "{$a->_rok} - ";
      $nazev.= strtr($a->nazev,array(','=>' ',':'=>' '));
      if ( mb_strlen($nazev) >= $max_nazev )
        $nazev= mb_substr($nazev,0,$max_nazev-3).'...';
      $options.= ",$nazev:{$a->id_duakce}";
    }
  }
  return $options;
}
# ------------------------------------------------------------------------------- akce2 change_cenik
# změnit ceník akce za vybraný
# fáze= dotaz | proved
# 1. kontrola parametrů 
function akce2_change_cenik($faze,$id_akce,$id_akce_vzor) {  trace();
  if ( !$id_akce || !$id_akce_vzor ) { $msg= "nebyl vybrán zdroj ceníku!"; goto end; }
  $hnizda= select('hnizda','akce',"id_duakce=$id_akce_vzor");
  if ( $hnizda ) { $msg= "kopírovat ceník z hnízd do hnízd zatím neumím!"; goto end; }
  $hnizda= select('hnizda','akce',"id_duakce=$id_akce");
  if ($faze=='dotaz') {
    $msg= $hnizda 
        ? "opravdu nastavit ceníky ve všech hnízdech akce jako stejné podle ceníku vybrané akce?" 
        : "opravdu nastavit ceník akce za cením vybrané akce?";
    goto end;
  }
  // 'proved' kopii, napřed vymaž starý ceník
  query("DELETE FROM cenik WHERE id_akce=$id_akce");
  // kopie ze vzoru
  $flds= "poradi,druh,polozka,cena,krat,za,t,n,p,od,do,typ,dph";
  if ($hnizda) { // do hnízd
    $n_h= count(explode(',',$hnizda));
    for ($h=1; $h<=$n_h; $h++) {
      query("INSERT INTO cenik (id_akce,hnizdo,$flds)
          SELECT $id_akce,$h,$flds
          FROM cenik WHERE id_akce=$id_akce_vzor");
    }
  }
  else { // bez hnízd
    query("INSERT INTO cenik (id_akce,$flds)
          SELECT $id_akce,$flds
          FROM cenik WHERE id_akce=$id_akce_vzor");
  }
   $id_new= pdo_insert_id();
  $msg= "hotovo, nezapomeňte jej upravit (ceny,DPH)";
end:
  return $msg;
}
# ------------------------------------------------------------------------------- akce2 tabulka
# generování tabulky účastníků $akce typu LK
function akce2_hnizda($akce,$par=null,$title='',$vypis='',$export=false) { trace();
  global $VPS, $tisk_hnizdo;
//                                         debug($par,"akce2_tabulka");
  $map_fce= map_cis('ms_akce_funkce','zkratka');
  $res= (object)array('html'=>'...');
  $info= akce2_info($akce,0,1);
//                                           debug($info,"info o akci"); //goto end;
  $clmn= $info->pobyty;
  if (!$clmn) return $res;
  // seřazení podle příjmení
  usort($clmn,function($a,$b) { return mb_strcasecmp($a->prijmeni,$b->prijmeni); });
  // odstranění jednoznačných jmen
  $clmn[-1]= (object)array('prijmeni'=>''); // zarážka
  $clmn[count($clmn)]= (object)array('prijmeni'=>''); // zarážka
  for ($i= 0; $i<count($clmn); $i++) {
    if ( $clmn[$i-1]->prijmeni != $clmn[$i]->prijmeni
      && $clmn[$i+1]->prijmeni != $clmn[$i]->prijmeni ) {
      $clmn[$i]->jmena= '';
    }
  }
  unset($clmn[-1]); unset($clmn[count($clmn)]);
  // zkrácení zbylých jmen
  for ($i= 0; $i<count($clmn); $i++) {
    if ( $clmn[$i]->jmena ) {
      list($m,$z)= explode(' ',$clmn[$i]->jmena);
      $clmn[$i]->jmena= mb_substr($m,0,1).'+'.mb_substr($z,0,1);
    }
  }
//                                           debug($clmn,"akce2_pobyty");
  $titl= array($VPS,'nováčci','repetenti','pečouni','organizace akce','náhradníci');
  foreach ($info->title as $h=>$hnizdo) {
    $tables.= "<h3>$hnizdo</h3>";
    $s_pary= array(0,0,0,0,0,0);
    $s_deti= array(0,0,0,0,0,0);
    $s_chuvy_d= array(0,0,0,0,0,0);
    $s_chuvy_p= array(0,0,0,0,0,0);
    $i= array(0,0,0,0,0,0);
    $tds= array();
    foreach ($clmn as $x) {
      if ($x->hnizdo==$h) {
        $sl= $x->typ=='vps' ? 0 : ($x->typ=='nov'?1:($x->typ=='rep'?2:($x->typ=='tym'?4:5)));
        $k= $i[$sl];
        $tds[$k][$sl]= $x->prijmeni;
        $s_pary[$sl]++;
        $x_deti= str_replace(',,',',',$x->deti);
        if ($x_deti!=='') {
          $tds[$k][$sl].= " ($x_deti)";
          $s_chuvy_d[$sl]+= substr_count($x_deti,'*');
          $s_chuvy_p[$sl]+= $_sp= substr_count($x_deti,'§');
          $s_deti[$sl]+= substr_count($x_deti,',') + 1 - $_sp;
        }
        if ($x->typ=='tym') {
          $tds[$k][$sl].= " / ".$map_fce[$x->fce];
        }
        $i[$sl]++;
      }
    }
    // doplníme pečouny
    $i3= 0;
    if ($info->pecouni) foreach ($info->pecouni as $x) {
      if ($x->hnizdo==$h) {
        $tds[$i3++][3]= "{$x->prijmeni} {$x->jmeno}";
      }
    }
    $i[3]= $i3;
    $ths= '';
    foreach ($titl as $k=>$t) {
      $tit= $k==3 ? je_1_2_5($i3,"pečoun,pečouni,pečounů") : (
          $k==4 ? $t : (je_1_2_5($s_pary[$k],"pár,páry,párů")." $t"));
      $ths.= "<th>$tit "
          . ($s_deti[$k] ? "+ {$s_deti[$k]} dětí ".($s_chuvy_d[$k] ? " ({$s_chuvy_d[$k]}* chůviček) " : '' ) : '' )
          . ($s_chuvy_p[$k] ? "+ {$s_chuvy_p[$k]}§ chův" : '' )
          . '</th>';
    }
    $trs= '';
    for ($j= 0; $j<max($i); $j++) {
      $trs.= '<tr>';
      for ($k= 0; $k<=5; $k++) {
        $trs.= '<td>'.($tds[$j][$k]).'</td>';
      }
      $trs.= '</tr>';
    }
    $tables.= "<div class='stat'><table class='stat'><tr>$ths</tr>$trs</table></div>";                                           
  }
end:  
  $legenda= "U páru je v závorce uveden věk dětí a chův, které berou na akci 
    <br>(§ označuje chůvu s rolí 'p' tj. snad dospělou, * označuje vlastní dítě sloužící coby chůvička). 
    <br>Odhad chybějících pečounů bere v úvahu chůvy a přihlášené pečouny a pro zbytek dětí 
    je počítán podle vzorce: 
    <br>1 pečoun na dítě do 3 let + na každé 3 děti jeden pečoun.";
  $res->html= "$legenda<br><br>$tables<br><br><br>"; 
  return $res;
}
# ---------------------------------------------------------------------------------------- akce roky
# vytvoří číselník akcí danéo roku jako XLSX a vrátí jeho relativní adresu
function akce_ciselnik($rok) {
  // začátek XLSX
  $dnes= date('j.n.Y');
  $title= "Číselník akcí YMCA Setkání k $dnes";
  $file= "ciselnik_YS$rok";
  $xls= "|open $file";
  $xls.= "\n|sheet vypis;;L;page";
  $xls.= "\n|A1 $title::size=13 bold";
  // hlavička
  $fields= explode(',','Kód akce:10,Název akce:40,Dům Setkání:12,Od:12,Do:12');
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
  $ra= pdo_qry("SELECT IF(ciselnik_akce,ciselnik_akce,'') AS kod, a.nazev, 
        IFNULL(id_order,'') AS ord, datum_od, datum_do, IFNULL(d.id_akce,0) AS d_akce
      FROM akce AS a 
      LEFT JOIN ds_order AS d ON a.id_duakce=d.id_akce
      WHERE a.access & 65 AND YEAR(datum_od)=$rok 
      HAVING d_akce OR kod
      ORDER BY IF(kod,kod,999),ord");
  while ($ra && (list($kod,$nazev,$ord,$od,$do)= pdo_fetch_array($ra))) {
    $n++;
    $od= sql2xls($od);
    $do= sql2xls($do);
    $xls.= "\n|A$n $kod|B$n $nazev|C$n $ord|D$n $od::right|E$n $do::right";
  }
  $xls.= "\n|close";
                                      display($xls);
  require_once "ezer".EZER_VERSION."/server/vendor/autoload.php";
  $inf= Excel2007($xls);
  if ( $inf ) {
    $html.= "Číselník se nepovedlo vygenerovat ($inf)";
  }
  else {
    $html.= "Číselník akcí roku $rok lze stáhnout <a href='docs/$file.xlsx' target='xls'>zde</a>";
  }
                                      display($html);
  return $html;
}
# ---------------------------------------------------------------------------------------- akce roky
# vrátí seznam roků všech akcí a objednávek
function akce_roky() {
//  ';
  $res= pdo_query("SHOW TABLES LIKE 'ds_order'");
  $UNION= $res->num_rows
    ? "UNION
        SELECT DISTINCT YEAR(FROM_UNIXTIME(fromday)) AS rok FROM ds_order
        WHERE deleted=0 AND fromday IS NOT NULL AND fromday>0"
    : '';
  $obj= sql_query("
    SELECT GROUP_CONCAT(DISTINCT rok ORDER BY rok DESC) AS roky FROM (
        SELECT DISTINCT YEAR(datum_od) AS rok FROM akce
        WHERE datum_od IS NOT NULL AND YEAR(datum_od)>0
      $UNION
    ) AS roky_subquery");
  return $obj->roky;
}
# --------------------------------------------------------------------------------------- akce clone
# save=0 zjistí zda akce s tímto naázvem již neexistuje
# save=1 vytvoří kopii akce v daném roce
function akce_clone($ida,$rok,$save=0) {
  $ret= (object)['warn'=>'','msg'=>''];
  $old= select_object('*','akce',"id_duakce=$ida");
  if (!$save) {
    $uz1= select('COUNT(*)','akce',
        "YEAR(datum_od)=$rok AND nazev='{$old->nazev}' AND access={$old->access}");
    $uz2= $old->ciselnik_akce ? select('COUNT(*)','akce',
        "YEAR(datum_od)=$rok AND ciselnik_akce='{$old->ciselnik_akce}' AND access={$old->access}") : 0;
    if ($uz1) 
      $ret->warn= "POZOR: v roce $rok již akce s názvem '{$old->nazev}' založena je.";
    elseif ($uz2) 
      $ret->warn= "POZOR: v roce $rok již akce s účetním kódem '{$old->ciselnik_akce}' založena je.";
    else $ret->msg= "Mám založit akci s názvem '{$old->nazev}' v roce $rok?";
  }
  else { // založ akci
    $od= $rok.substr($old->datum_od,4);
    $do= $rok.substr($old->datum_do,4);
    $same= "access,ma_cenik,ma_cenik_verze,ma_cenu,cena,spec,mrop,firm,nazev,misto,"
        . "druh,statistika,poradatel,tym,strava_oddo,ciselnik_akce";
    query("INSERT INTO akce (datum_od,datum_do,$same) "
        . "SELECT '$od','$do',$same FROM akce WHERE id_duakce=$ida ");
    $id_new= pdo_insert_id();
    $ret->msg= "Byla vytvořena kopie akce '{$old->nazev}' v roce $rok";
    // zjistíme, zda existuje pohled 
    global $answer_db;
    $existuje= select('COUNT(*)','information_schema.VIEWS',
        "TABLE_SCHEMA='$answer_db' AND TABLE_NAME='ds_order'");
    if ($existuje) {
      // pokud byla v Domě setkání vytvoř i objednávku
      $idd= select('id_order','ds_order',"id_akce=$ida");
      if ($idd) {
         dum_objednavka_make($id_new,$idd);
         $ret->msg.= ", a byla k ní založena objednávka v Domě setkání";
      }
    }
    $ret->msg.= ". <hr><b>Nezapomeň upravit datum, vyměnil jsem jen rok.</b>";
  }
  return $ret;
}
# ----------------------------------------------------------------------------------- akce ucastnici
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
/** ===================================================================================> FILEBROWSER */
# ------------------------------------------------------------------------------------ tut ma_archiv
# SHOW LOAD
// je volané metodou show.load - vrátí informace, zda existuje archiv akce v Synology
function tut_ma_archiv ($table,$idkey,$keys,$root) {
  $values= array();
  foreach ($keys as $key) {
    list($kod,$rok)= select(
//g        'IF(ga2.g_kod,ga2.g_kod,da2.ciselnik_akce),YEAR(datum_od)',
        'da2.ciselnik_akce,YEAR(datum_od)',
        "akce AS da2 "
//g        . "LEFT JOIN join_akce AS ja2 ON ja2.id_akce=da2.id_duakce "
//g        . "LEFT JOIN g_akce AS ga2 USING(g_rok,g_kod) "
        ,"$idkey=$key");
    $y= tut_dir_find ($root,$rok,$kod);
    $values[]= $y->ok ? 1 : 0;
  }
  return $values;
}
# ---------------------------------------------------------------------------------------- tut mkdir
// vytvoří adresář
function tut_mkdir ($root,$rok,$kod,$slozka,$podslozka='') {  trace();
  $base= "{$root}Agenda MS/Akce MS/$rok";
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
  $patt= "{$root}Agenda MS/Akce MS/$rok/$kod*";
  $fs= simple_glob($patt);
//                                                debug($fs,$patt);
  if (!$fs) { $y->ok= 0; goto end; }
  $file= $fs[0];
  if ( count($fs)==1 ) {
    if ( stristr(PHP_OS,'WIN') && substr(PHP_VERSION_ID,0,1)=='5' ) // windows
      $file= iconv("Windows-1250","UTF-8",$file);  
    $y->aroot= "{$root}Agenda MS/Akce MS/$rok/";
    $y->droot= mb_substr(strrchr($file,'/'),1);
  }
  else {
    $y->ok= count($fs);
  }
//                                                debug($y,strrchr($fs[0],'/'));
end:
  return $y;
}
# ---------------------------------------------------------------------------------------- tut files
// vrátí soubory adresáře
function tut_files ($root,$rel_path) {  trace();
  global $ezer_root;
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
            $cmd= "Ezer.run.$._call(0,'$ezer_root.akce2.lst.page.files.Menu','viewer','$file','$abs_path');";
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
