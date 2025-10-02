<?php

# ----------------------------------------------------------------------------------------- dot roky
# vrátí dostupné dotazníky Letního kurzu MS YS
function dot_roky () { trace();
  $y= (object)array('roky'=>'2023,2022,2021,2019,2018,2017'); // 2017 je rozjetý - k dispozici je jen statistika
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
//  debug($dotaz,"tipy 0:$n0, 1:$n1");
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
//                                  debug($y);
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
//                                                  debug($value);
          $set= "dotaznik=$rok, id=$id";
          foreach ($value as $fld => $val) {
            $set.= ", $fld='$val'";
          }
          query("INSERT INTO dotaz SET $set");
        }
      }
    }
  }
  elseif ($rok>=2022) {
    // export do json od roku 2022 již nepřenáší textové hodnoty, pokud se očekávají čísla
    // například již nelze "O,r,pecedeti?1;2;3;4;5;péči o děti jsme nevyužili*0"
    // proto je download před CSV
    $LIMIT= 0;
    $def_g= $rok>=2024 ? array(
      "A,x,id?",
      "B,r,sex?Muž*0;Žena*1",
      "C,i,vek?",
      "D,r,deti?1;2;3;žádné*0;více*4",
      "E,i,manzel?",
      "F,r,novic?Ano*1;Ne*0",
      "H,c,od_jine,od_jine_text?Přátelé*od_pratele;Příbuzní*od_pribuzni;Jezdil/a jsem jako pečovatel/ka*od_pecoun;Inzerce*od_inzerce;Chlapské akce*od_chlapi;Akce YMCA Setkání*od_ymca",
      "I,c,proc_jine,proc_jine_text?"
        . "chci zlepšovat naše manželství*proc_zlepsit;byli jsme v krizi*proc_krize;"
        . "jezdíme opakovaně*proc_opak",
      "J,i,prednasky?",
      "K,i,skupinky?",
      "L,i,duchovno?",
      "M,r,ubytovani?1;2;3;4;5;Bez ubytování*0",
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
    )
    : array(
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
    # https://docs.google.com/spreadsheets/d/19OmRzKg00WcheVeyBFFXU_zuogLW0UwhqC5oswOnrVU/edit?usp=sharing
    $goo= "https://docs.google.com/spreadsheets/d";
    $key= $rok==2022 ? "13GuKhM6vwo-zfN97UWazdoDdzKpqGXNmQls7sYTzo6c" : (
          $rok==2023 ? "17E5dotr5EOhlLgOM7dyjTVV8h22OcGwHAYpCuhEUtWs" : (
          $rok==2024 ? "19OmRzKg00WcheVeyBFFXU_zuogLW0UwhqC5oswOnrVU" : ''));
    $url= "$goo/$key/export?format=csv";
    $f= @fopen($url, "r");
    $why= 'ok';
    if (!$f) {
     $why_e= error_get_last();
     $why= $why_e['message'];
    }
    display("DOTAZNIK:$url --- $why");
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
        list($clmn,$typ,$fld,$fld_text)= explode(',',$desc);
        $itms= explode(';',$itms);
//                                                          debug($itms,"clmn $clmn itms $typ,$fld");
        if ($rok==2024 && in_array($clmn,['G','Y','Z'])) continue;
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
//        query("INSERT INTO dotaz SET $set");
        display("INSERT INTO dotaz SET $set"); // DEBUG
      }
      break; // DEBUG
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
