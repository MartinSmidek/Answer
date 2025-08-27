<?php

# ------------------------------------------------------------------------------ akce2 vzorec2_pobyt
# výpočet platby za pobyt na akci, případně jen pro jednu jeho osobu
# výsledek ovlivňují položky objektu $spec popsaného v akce2_vzorec2
function akce2_vzorec2_pobyt($idp,$ids=0,$spec=null) { // trace();
  $spec->prijmeni= isset($spec->prijmeni) ? $spec->prijmeni : 0; 
  $osoba= []; $i= 0; $ida= 0; $vzorec= ''; $slevy= null; 
  $cond= $ids ? "id_spolu=$ids" : "id_pobyt=$idp";
  $OR_chuvy= '';
  $pozn= []; // poznámky pod čarou
  // zjištění případných chův z jiných pobytů tedy účtovaných tímto pobytem
  if ($idp) {
    $chuvy= [];
    $rch= pdo_qry("SELECT chs.id_spolu
      FROM spolu AS s
      JOIN pobyt AS p USING (id_pobyt)
      LEFT JOIN spolu AS chs ON chs.pecovane=s.id_osoba
      LEFT JOIN pobyt AS chp ON chp.id_pobyt=chs.id_pobyt
      JOIN akce ON p.id_akce=id_duakce
      WHERE chs.pecovane!=0 AND chp.id_akce=p.id_akce AND chp.id_pobyt!=p.id_pobyt
        AND p.id_pobyt=$idp");
    while ($rch && list($ch_spolu)= pdo_fetch_row($rch)) {
      $chuvy[]= $ch_spolu;
    }
    if (count($chuvy)) {
      $OR_chuvy= "OR id_spolu IN (".implode(',',$chuvy).")";
      $pozn[]= "obsahuje cenu pobytu osobního pečovatele ";
    }
  }
  $order= $spec->prijmeni ? "prijmeni,jmeno" : "IFNULL(role,'e'),_vek DESC";
  $rs= pdo_qry("
    SELECT funkce,prijmeni,jmeno,IFNULL(c2.ikona,'{}') AS _vzorec,sleva,id_spolu,pecovane,
      IF(funkce IN (1,2),'V',IF(funkce=0,'U',IF(funkce IN (3,4,5,6),'T','H'))),
      IF(funkce=99,'P',c1.ikona) AS _dite,
      kat_nocleh,kat_dny,kat_porce,kat_dieta,TIMESTAMPDIFF(YEAR,narozeni,datum_od) AS _vek,
      id_akce,DATEDIFF(datum_do,datum_od) AS noci,strava_oddo
    FROM spolu
    JOIN _cis c1 ON c1.druh='ms_akce_s_role' AND c1.data=s_role
    JOIN osoba USING (id_osoba)
    JOIN pobyt USING (id_pobyt)
    LEFT JOIN _cis c2 ON c2.druh='ms_cena_vzorec' AND c2.data=vzorec
    LEFT JOIN tvori ON osoba.id_osoba=tvori.id_osoba AND id_rodina=i0_rodina
    JOIN akce ON id_akce=id_duakce
    WHERE ($cond) $OR_chuvy
    ORDER BY $order
  ");
  while ($rs && (list($funkce,$prijmeni,$jmeno,$vzorec,$sleva,$ids,$pecovane,
         $t1,$t2,$n,$dny,$p,$d,$v,$_ida,$noci,$strava_oddo)
      = pdo_fetch_row($rs))) {
    if (!$ida) $ida= $_ida;
    if (!$slevy) {
      if ($funkce==99) { // pečouni neplatí
        $vzorec= '{"ubytovani":0,"strava":0,"program":0}';
      }
      $slevy= json_decode($vzorec);   // může obsahovat {"ubytovani":0,"strava":0,"program":0}
      $slevy->dotace= $sleva;         // přiznaná individuální sleva 
//      debug($slevy,$vzorec);
    }
    if ($t1=='V' && ($slevy->za??'')!='Sv') { 
      // pokud je to VPS a není sleva za sloužící VPS zaměň 'V' za 'U'
      $t1= 'U';
    }
    $osoba[$i]= (object)['jmeno'=>$spec->prijmeni ? "$prijmeni $jmeno" : $jmeno,
        'ids'=>$ids,
        't'=>$t2=='U' ? $t1 : $t2,
        'n'=>$n,'p'=>$p,'d'=>$d,'v'=>$v];
    if ($dny) {
      // doplnění počtu nocí a strav
      $nsov= akce_dny2sov($ids,$dny);
      $osoba[$i]->xN= $nsov->xN;
      $osoba[$i]->xS= $nsov->xS;
      $osoba[$i]->xO= $nsov->xO;
      $osoba[$i]->xV= $nsov->xV;
      $osoba[$i]->sov= $nsov->sov;
      $osoba[$i]->n= $nsov->n;
    }
    else { // nejsou definovány dny
      $osoba[$i]->undefined= 1;
    }
    // je to pečující chůva
    if ($pecovane) {
      $pd= pdo_qry("SELECT COUNT(*),id_pobyt,id_spolu,nazev,jmeno
          FROM spolu
          JOIN pobyt USING (id_pobyt)
          LEFT JOIN rodina ON id_rodina=i0_rodina
          LEFT JOIN osoba USING (id_osoba)
          WHERE id_osoba=$pecovane AND id_akce=$ida
        ");
      list($ok,$d_idp,$d_ids,$rodina,$dite)= pdo_fetch_row($pd);
      if ($d_idp!=$idp) {
        $osoba[$i]->chuva= 1;
        $pozn[]= "$jmeno pečuje o $dite, platí to $rodina";
      }
//      display("list($ok,$d_idp,$d_ids,$rodina,$dite) => $pozn");
    }
    $i++;
  }
//  debug($osoba,"podklady");
  $res= akce2_vzorec2($ida,$osoba,$slevy,$spec);
  if ($pozn) {
    $res->navrh.= "<br>Poznámka: ".implode('<br>',$pozn);
  }
  $res->mail= "<div style='background-color:#eeeeee;margin-left:15px'>$res->navrh</div>";
  return $res;
}
# ------------------------------------------------------------------------------- akce2 vzorec2_test
# testovací výpočet platby za akci
function akce2_vzorec2_test($ida,$idc) { // trace();
  list($noci,$strava_oddo)= select('DATEDIFF(datum_do,datum_od),strava_oddo','akce',"id_duakce=$ida");
  $test= select('hodnota','_cis',"druh='ms_ceník_testy' AND data=$idc");
  $osoba= [];
  foreach (explode(';',$test) as $i=>$tnpv) {
    $p= explode(',',$tnpv);
    $osoba[$i]= (object)['t'=>$p[0],'n'=>$p[1],'p'=>$p[2],'v'=>$p[3]];
    // doplnění plného počtu nocí a strav
    $osoba[$i]->xN= $noci;
    // meze strav jsou zatím jen oo nebo vo
    $osoba[$i]->xS= $osoba[$i]->xO= $osoba[$i]->xV= $noci;
    if ($strava_oddo=='oo') $osoba[$i]->xO++;
    $osoba[$i]->sov= 'SOV';
  }
  $spec= (object)[];
  return akce2_vzorec2($ida,$osoba,null,$spec);
}
# ------------------------------------------------------------------------------------- akce2 vzorec
# výpočet platby za osoby na akci
# číselník ms_ceník_testy.hodnota= t,n,p,v;... 
# kde t=u|v|d ... účastník, vps, dítě
#     n=L,S,- ... lůžko, spacák, na zemi
#     p=C,P,- ... dospělá, poloviční, bez
#     v=věk
# pokud je do ceny zahrnuto něco s druh=x ohlásí se jako chybná kombinace
# slevy jsou dány jako objekt s nepovinnými položkami
#   ubytovani=0, strava=0, program=0 jsou slevy, uplatněné pokud $spec->funkce_slevy=1
#   sleva=>x      kde x je inidividuální dotace
#   ! sleva na ubytovaní, stravu či program ruší slevu pro VPS
# výpočet je ovlivněn položkami objektu $spec - popis je u akce2_vzorec2_pobyt
#   funkce_slevy=1  do ceny budou započteny slevy podle funkce
#   cena=1        do tabulky je zařazen sloupec s cenou a celkovým součtem cen
#   jako=1        do tabulky je zařazen sloupec s identifikací funkce+s_role
#   prijmeni=0    pokud je 1 bude zobrazeno příjmení člena pobytu
#   back_dny      úplný název ezer funkce vyvolané kliknutím na jméno
#   back_cena     úplný název ezer funkce vyvolané kliknutím na částku
#
function akce2_vzorec2($ida,$osoby,$slevy=null,$spec=null) { // trace();
//  /**/                                                   debug($spec,'akce2-vzorec2 - spec');
  if ($spec===null) $spec= (object)[];
  if (!isset($spec->funkce_slevy)) $spec->funkce_slevy= 1;
  if (!isset($spec->cena)) $spec->cena= 1;
  if (!isset($spec->jako)) $spec->jako= 1;
  if (!isset($spec->prijmeni)) $spec->prijmeni= 0;
  // šířku ovlivňuje zobrazení příjmení
  $w_jmeno= $spec->prijmeni ? 120 : 50;
  $w_table= $spec->prijmeni ? 365 : 295;
  $ret= (object)['navrh'=>'','tabulka'=>'','full'=>[],'rozpis'=>['u'=>0,'s'=>0,'p'=>0,'d'=>0,'bad'=>'']];
  $hd= ['jmeno'=>"jméno &nbsp; &nbsp; :$w_jmeno",
        't'=>'jako?::P-pečovatel, U-účastník (S-storno), V-VPS, H-host, p-pomocný pečovatel, '
          . 'D-dítě ve skupince, C-chůva, d-chované dítě',
        'n'=>'noc','sov'=>'jídla','p'=>'porce','d'=>'dieta','v'=>'věk'];
  if ($spec->cena) $hd['Kc']= 'cena:35';
  if (!$spec->jako) unset($hd['t']);
//  /**/                                                   debug($hd,'akce2-vzorec2 - hd');
  $osoba= []; // i => [id=>val,...]
  $header= $footer= "<tr>";
  foreach ($hd as $hid=>$hname) {
    list($id,$w,$h)= explode(':',$hname);
    $w= $w ? " style='width:{$w}px'" : '';
    $h= $h ? " title='$h'" : '';
    $header.= "<th$w$h>$id</th>";
    $footer.= "<td></td>";
    foreach ($osoby as $i=>$o) {
      $osoba[$i][$hid]= $o->$hid??'';
    }
  }
//  /**/                                                   debug($osoba,'akce2-vzorec2 - osoba');
  $header.= "</tr>";
  $footer.= "</tr>";
  $cena= []; // polozka => iosoba => [pocet,cena]
  $druh= []; // položka =>druh
  $blok= []; // druh => 0=zdarma/1
  $full= []; // položka -> počet
  $rc= pdo_qry("SELECT druh,polozka,cena,krat,za,t,n,p,od,do,poradi "
      . "FROM cenik WHERE id_akce=$ida ORDER BY poradi");
  while ($rc && (list($_druh,$pol,$kc,$co,$za,$t,$n,$p,$od,$do,$ipol)= pdo_fetch_row($rc))) {
    $druh[$pol]= $_druh;
    if (!isset($blok[$_druh])) $blok[$_druh]= 0;
    $pocet= 0;
    foreach ($osoby as $i=>$o) {
      if (isset($o->undefined)) continue; // nejsou definované dny
      if (isset($o->chuva)) continue; // pečuje o dítě jiné rodiny
      $ok= true;
//      $osoba[$i]['jmeno']= $o->jmeno;
      if ($t) $ok&= strpos(",$t,",",$o->t,")!==false; // $t je seznam oddělený čárkami
      if ($n) $ok&= $n== strtoupper($o->n); 
      if ($p) $ok&= $p==$o->p; 
      if ($do) $ok&= $o->v>=$od && $o->v<$do; 
      
      if ($ok) {
        if (!isset($cena[$pol][$i])) $cena[$pol][$i]= [0,$kc];
        switch ($co) {
          case 'xN': $cena[$pol][$i][0]+= $o->xN; $full[$ipol]+= $o->xN; break;
          case 'xS': $cena[$pol][$i][0]+= $o->xS; $full[$ipol]+= $o->xS; break;
          case 'xO': $cena[$pol][$i][0]+= $o->xO; $full[$ipol]+= $o->xO; break;
          case 'xV': $cena[$pol][$i][0]+= $o->xV; $full[$ipol]+= $o->xV; break;
          case 'P':  $cena[$pol][$i][0]++;        $full[$ipol]++; break;
          case 'S':  $cena[$pol][$i][0]++;        $full[$ipol]++; break;
//          case 'S':  isset($slevy->ubytovani) ? 0 : $cena[$pol][$i][0]++; break;
        }
        if ($cena[$pol][$i][0]) $blok[$_druh]++;
      }
    }
  }
  // záznam blokových slev
//  debug($blok,"blok 1");
  if ($spec->funkce_slevy) {
    foreach ($slevy as $nazev=>$nula) {
      if ($nula==0) {
        switch ($nazev) {
          case 'ubytovani': $blok['u']= -1; break;
          case 'strava':    $blok['s']= -1; break;
          case 'program':   $blok['p']= -1; break;
        }
      }
    }
    if ($slevy->dotace??0>0) {
      $blok['d']++;
    }
  }
//  /**/                                                   debug($full,'akce2-vzorec2 - full');
  // redakce
//  debug($blok,"blok 2");
  $celkem= 0;
  $celkem2= 0;
  $nadpis= [];
  foreach ($cena as $pol=>$za_osoby) {
    if ($pol[0]!='-') continue;
    $nadpis[$druh[$pol]]= trim(substr($pol,1));
  }
  $html= "<table>";
  foreach ($blok as $d=>$je) {
    $za_blok= 0;
    if ($je>0) {
      foreach ($cena as $pol=>$za_osoby) {
        if ($pol[0]=='-') continue;
        $pocet= 0;
        $kc= 0;
        if ($druh[$pol]!=$d) continue;
        foreach ($za_osoby as $i=>list($poceti,$kci)) {
          $pocet+= $poceti;
          $kc= $kci;
          $osoba[$i]['Kc']+= $poceti*$kci;
          // pohlídáme chybné kombinace
          if ($d=='x') {
            $za_blok++;
            $osoba[$i]['bad']= 1;
            display("NELZE: $pol");
            $ret->bad.= "$pol ";
          }
        }
        if ($pocet) {
          $nx= $pocet==1 ? '' : "($kc*$pocet)";
          $kc= $pocet*$kc;
          $ret->rozpis[$d]+= $kc;
          $za_blok+= $kc;
          $celkem+= $kc;
          $celkem2+= $kc;
          if ($d=='x') {
            $kc= $nx= '';
          }
          $html.= "<tr><td>$pol $nx</td><td align='right'>$kc</td><td></td></tr>";
        }
      }    
      if ($d=='d' && $spec->funkce_slevy && $slevy->dotace>0) {
        $kc= -$slevy->dotace;
        $ret->rozpis[$d]+= $kc;
        $za_blok+= $kc;
        $celkem+= $kc;
        $html.= "<tr><td>individuální sleva</td><td align='right'>$kc</td><td></td></tr>";
      }
      $kc_blok= $d=='x' ? "$za_blok x" : $za_blok;
      $html.= "<tr><th>$nadpis[$d]</th><td></td><th align='right'>$kc_blok<br></th></tr>";
    }
  }
  $html.= "<tr><th>celkem</th><td></td><th>$celkem</th></tr>";
//  $html.= "<tr><td><b>celkem</b></td><td></td><th>$celkem</th></tr>";
  $html.= "</table>";
//  debug($osoba,"tabulka poplatků za osoby");
  $tab= '';
  $tab= "<style>"
      . ".tab_ceny_osob {table-layout:fixed;}"
      . ".tab_ceny_osob td,th {overflow:hidden;white-space:nowrap;text-align:right}"
      . "</style>";
  $tab.= "<table class='tab_ceny_osob' style='width:{$w_table}px;'>$header";
  foreach ($osoba as $i=>$o) {
    $tab.= "<tr>";
    $ids= $osoby[$i]->ids;
    foreach ($hd as $hid=>$hname) {
      if ($hid=='t' && !$spec->jako) continue;
      if ($hid=='Kc' && $spec->cena) {
        $kc= $o[$hid]?:0;
        if ($spec->back_cena) {
          $href= "ezer://$spec->back_cena/$ids"; // ezer://akce2.ucast.ucast_cena_osoba/$ids
          $tab.= isset($osoby[$i]->undefined) ? "<td style='background:yellow'>???</td>"
            : "<td><a href='$href'>$kc</a></td>";
        }
        else {
          $tab.= isset($osoby[$i]->undefined) ? "<td style='background:yellow'>???</td>"
            : "<td>$kc</td>";
        }
      }
      elseif ($hid=='jmeno') {
        $jmeno= $o['jmeno'];
        $bad= isset($o['bad']) || (!$spec->cena && isset($osoby[$i]->undefined)) 
            ? ";background:yellow" : '';
        if ($spec->back_dny) {
          $href= "ezer://$spec->back_dny/$ids"; // ezer://akce2.ucast.ucast_cena_osoba/$ids
          $tab.= "<td style='text-align:left$bad'>"
              . "<a href='$href'>$jmeno</a></td>";
        }
        else {
          $tab.= "<td style='text-align:left$bad'>$jmeno</td>";
        }
      }
      else {
        $tab.= isset($o[$hid]) ? "<td>$o[$hid]</td>" : '';        
      }
    }
    $tab.= "</tr>";
  }
  if ($spec->cena) {
    $tab.= str_replace("<td></td></tr>","<th>$celkem2</th></tr>",$footer);
  }
  $tab.= "</table>";
//  display($tab);
//  $html.= "<tr><td><b>Celkem</b></td><td></td><th>$celkem</th></tr>";
//  $html.= "</table>";
  $ret->navrh= $html;
  $ret->tabulka= $tab;
  $ret->full= $full;
//  /**/                                                   debug($ret,'akce2-vzorec2 - return');
  return $ret;
}
# ------------------------------------------------------------------------------------- akce sov2dny
# vytvoří kat_dny podle SOV
function akce_sov2dny($sov,$ida) { // trace();
  $dny= '';
  list($noci,$oddo)= select('DATEDIFF(datum_do,datum_od),strava_oddo','akce',"id_duakce=$ida");
  if ($oddo[0]=='o') $dny.= "00".($sov[1]=='-'?"0":"1").($sov[2]=='-'?"0":"1");
  $dny.= str_repeat("1".($sov[0]=='-'?"0":"1").($sov[1]=='-'?"0":"1").($sov[2]=='-'?"0":"1"),$noci-1);
  if ($oddo[1]=='o') $dny.= "1".($sov[0]=='-'?"0":"1").($sov[1]=='-'?"0":"1")."0";
//  display($dny);
  return $dny;
}
# ------------------------------------------------------------------------------------- akce dny2sov
# vytvoří podle kat_dny,kat_noc textovou hodnotu L|S|B a SOV ... částečné hodnoty malým písmenem
function akce_dny2sov($ids,$dny) { //trace();
  $ret= (object)['n'=>'L','sov'=>'SOV','xN'=>0,'xS'=>0,'xO'=>0,'xV'=>0];
  // zjisti default nocí a strav
  list($kn,$noci,$oddo)= select('kat_nocleh,DATEDIFF(datum_do,datum_od),strava_oddo',
      'spolu JOIN pobyt USING (id_pobyt) JOIN akce ON id_akce=id_duakce',
      "id_spolu=$ids");
  $xs_def= $xv_def= $noci;
  $xo_def= $oddo[0]=='o' ? $noci+1 : $noci;
  // přepočítej noci a stravy
  $xn= $xs= $xo= $xv= 0;
  for ($d= 0; $d<strlen($dny); $d+=4) {
    $xn+= $dny[$d];
    $xs+= $dny[$d+1];
    $xo+= $dny[$d+2];
    $xv+= $dny[$d+3];
  }
  // redakce SOV
  $n= $xn==0 ? '-' : ($xn==$noci ? $kn : strtolower($kn));
  $s= $xs==0 ? '-' : ($xs==$xs_def ? 'S' : 's');
  $o= $xo==0 ? '-' : ($xo==$xo_def ? 'O' : 'o');
  $v= $xv==0 ? '-' : ($xv==$xv_def ? 'V' : 'v');
//  display("$n $s$o$v ... $xn, $xs, $xo, $xv <= $dny");
//  query("UPDATE spolu SET kat_dny='$dny' WHERE id_spolu=$ids");
  $ret->necely_pobyt= $xn==$noci ? 0 : 1;
  $ret->n= "$n";
  $ret->sov= "$s$o$v";
  $ret->xN= $xn;
  $ret->xS= $xs;
  $ret->xO= $xo;
  $ret->xV= $xv;
//  debug($ret,"akce_dny2sov($ids,$dny)");
  return $ret;
}
# ------------------------------------------------------------------------------ akce prihlaska_load
# pro spolu nastaví defaultní hodnoty pobytu podle online přihlášky akce a věku
function akce_dny_default($ida,$ids) {
  // získání definice přihlášky kvůli stravě
  list($json,$a_od)= select('web_online,datum_od','akce',"id_duakce=$ida");
  $json= str_replace("\n", "\\n", $json);
  $akce= json_decode($json); // definice přihlášky
  $p_od= $akce->p_detska_od??3;
  $p_do= $akce->p_detska_do??12;
  // získání věku
  $vek= select1("TIMESTAMPDIFF(YEAR,narozeni,'$a_od')",'osoba JOIN spolu USING (id_osoba)',"id_spolu=$ids");
  $kn= $vek<3 ? '-' : 'L';
  $porce= $vek<$p_od ? 0 : ($vek<$p_do ? 2 : 1);
  $kp= $porce==1 ? 'C' : ($porce==2 ? 'P' : '-');
  $kd= "-";
  $dny= akce_sov2dny("SOV",$ida);
  $qry= "UPDATE spolu SET kat_nocleh='$kn',kat_porce='$kp',kat_dieta='$kd',kat_dny='$dny' "
      . "WHERE id_spolu=$ids";
  query($qry);
}
# ========================================================================================> . strava
# výpočet celkového počtu strav pro každý den
# pokud je par.vylet=n bude uvažován jen n-tý den (n=0 ... výlet hned první den)
# --------------------------------------------------------------------------------- akce2 strava_cv2
function akce2_strava_cv2($akce,$par,$title,$vypis,$export=false,$hnizdo=0,$id_pobyt=0) { trace();
//  /**/                                                       debug($par,'akce2_strava_cv2');
  list($noci,$od)= select("DATEDIFF(datum_do,datum_od),datum_od","akce","id_duakce=$akce");
  // omezení na výlet
  $pouze= $par->vylet??0;
  $note= $par->note??'';
  // formátování tabulky
  $clmn= array();       // den -> fld -> počet
  $expr= array();       // den -> fld -> vzorec
  $suma= array();       // pro sumy sloupců id:::s
  $fmts= array();       // pro formáty sloupců id::f:
  $tits= array('den:15');
  $flds= array('day');
  $xj= ['S'=>'snídaně','O'=>'oběd','V'=>'večeře'];
  $xj= ['','snídaně','oběd','večeře'];
  $xp= ['C'=>'celá','P'=>'dětská']; 
  $xd= ['-'=>'normal','BL'=>'bezlep'];
  $dny= array('ne','po','út','st','čt','pá','so');
  $xden= [];
  for ($den= 0; $den<=$noci; $den++) {
    if ($pouze>0 && $den!=$pouze) continue;
    $xden[$den]= $dny[date('w', strtotime("$od+$den days"))];
    $xden[$den].= date(' j/n', strtotime("$od+$den days"));
  }
  foreach (['-','BL'] as $d) {
    foreach ([1,2,3] as $j) {
      foreach (['C','P'] as $p) {
        $f= "$p$j$d";
        $tits[]= "$xj[$j] $xp[$p] $xd[$d]:8:r:s";
        $flds[]= $f;
        $fmts[$f]= 'r';
        for ($den= 0; $den<=$noci; $den++) {
          if ($pouze>0 && $den!=$pouze) continue;
          $clmn[$den]['den']= $xden[$den];
          $clmn[$den][$f]= '';
        }
      }
    }
  }
  $fmts['den']= 'c';
//  /**/                                                       debug($clmn,'clmn init');
//  /**/                                                       debug($tits,'tits');
//  /**/                                                       debug($flds,'flds');
  $par= (object)[
    'tit'=> "Jméno,*",
    'fld'=> "rodice_,strava_dny",
    '_cnd'=> " p.funkce NOT IN (9,10,13,14,15) "  // stravy včetně pečounů
//      . " AND p.id_pobyt IN (69673)" // Czudkovi
//      . " AND p.id_pobyt IN (69466)"
//      . " AND p.id_pobyt IN (69619,69409,69874)"
//      . " AND p.id_pobyt IN (69874)"
    ];
  if ($pouze) {
    $par->jen_deti= 1;
    $par->vek= "3-99";
  }
  $ret= tisk2_sestava_pary($akce,$par,'$title','$vypis',false,true);
//  /**/                                                      debug($ret,'tisk2_sestava_pary');
  // průchod pobyty
  foreach ($ret as $x) {
    for ($den= 0; $den<=$noci; $den++) {
      if ($pouze>0 && $den!=$pouze) continue;
      $jpdn= $x['strava_dny'][$den]; // jidlo -> porce -> dieta ->počet
      foreach ($jpdn as $j=>$pdn) {
        foreach ($pdn as $p=>$dn) {
          if ($p=='-') continue;
          foreach ($dn as $d=>$n) {
            $f= "$p$j$d";
            $clmn[$den][$f]+= $n;
            $suma[$f]+= $n;
          }
        }
      }
    }
  }
//  /**/                                                       debug($clmn,'clmn');
//  /**/                                                       debug($suma,'suma');
  // zobrazení tabulkou
  $tab= '';
  $ths= '';
  $result= (object)[];
  if ( $export ) {
    $result->tits= $tits;
    $result->flds= $flds;
    $result->clmn= $clmn;
    $result->expr= $expr;
    $result->vertical= 1; // vertikální titulky
  }
  else {
    // titulky
    foreach ($tits as $idw) {
      list($id)= explode(':',$idw);
      $ths.= "<th class='vertical-text'>$id</th>";
    }
    // data
    foreach ($clmn as $c) {
      $tab.= "<tr><th>{$c['den']}</th>";
      foreach ($c as $id=>$val) {
        if ($id=='den') continue;
        $style= akce2_sestava_td_style($fmts[$id]);
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
    $result->html= ($pouze ? "Je zobrazen počet strav objednaných pro děti starší jak 3 roky, "
        . "které nemají os. pečovatele.<br>Jsou započítáni i pomocní pečovatelé.<br><br>" : '')
        . ($note ? "$note<br><br>" : '')
        . "<div class='stat'><table class='stat'><tr>$ths</tr>$tab"
        . "$sum</table></div>";
    $result->html.= "</br>";
  }
  return $result;
}
# -------------------------------------------------------------------------- akce2 sestava_cenik_cv2
# generování sestavy přehledu čerpání všech položek ceníku pro účastníky $akce - páry
#   $cnd = podmínka
# počítané položky
#   manzele = rodina.nazev muz a zena
# generované vzorce
#   člověkolůžka, člověkopřistýlky
function akce2_sestava_cenik_cv2($akce,$par,$title='',$vypis='',$export=false,$spec=null) { trace();
  $result= (object)array();
  // vyber položky z ceníku
  $druhy= $par->druhy;
  $note= $par->note??'';
  $dotace= $par->dotace??0;
  $polozky= $nadpisy= '';
  $cenik= []; // poradi -> cena
  $rc= pdo_qry("SELECT poradi,polozka,druh,cena FROM cenik "
      . "WHERE id_akce=$akce AND krat!='' ORDER BY poradi "
//      . "LIMIT 3"
      . "");
  while ($rc && (list($ipol,$polozka,$druh,$cena)= pdo_fetch_row($rc))) {
    if (strpos($druhy,$druh)===false) continue;
//    if (in_array($druh,['x','p','d'])) continue;
    $polozky.= ",$ipol";
    $nadpisy.= ",{$polozka}:7:r:s";
//    if (in_array($druh,['u','s'])) 
        $cenik[$ipol]= $cena;
  }
  $par= (object)[
    'tit'=> "Jméno:25,funkce:5:r"
      . ",pokoj:7,dětí:5:r:s$nadpisy".($dotace?',individuální dotace:7:r:s':''),
    'fld'=> "rodice_,_funkce,pokoj,#deti$polozky".($dotace?',sleva':'')
        // pomocná pole
      . ",key_pobyt,funkce"
      ,
    '_cnd'=> $par->cnd
//      . " AND p.id_pobyt IN (69619,69409,69874)"
//      . " AND p.id_pobyt IN (69874)"
//      ,
    ];
  
  $ret= tisk2_sestava_pary($akce,$par,'$title','$vypis',false,true);
//  /**/                                                   debug($ret,'tisk2_sestava_pary');
  // sežazení podle přítomnosti na akci a podle abecedy
  $collator= new Collator('cs_CZ'); // Nastavení českého jazyka
  usort($ret,function($x,$y) use ($collator) {
    $xin= $x['funkce']<=2 ? 1 : (in_array($x['funkce'],[10,14]) ? 3 : 2);
    $yin= $y['funkce']<=2 ? 1 : (in_array($y['funkce'],[10,14]) ? 3 : 2);
    $srt= $xin <=> $yin ?: $collator->compare($x['rodice_'],$y['rodice_']);
    return $srt;
  });
  // dekódování parametrů
//  $par->fld= str_replace(',key_pobyt,funkce','',$par->fld); // odstranění pomocných polí
  $tits= explode(',',$par->tit);
  $flds= explode(',',$par->fld);
  $last_fld= array_search('key_pobyt',$flds); // index prvního nezobrazovaného pole
  // získání dat - podle $kdo
  $clmn= array();       // pro hodnoty
  $expr= array();       // pro výrazy
  $suma= array();       // pro sumy sloupců id:::s
  $fmts= array();       // pro formáty sloupců id::f:
  for ($i= 0; $i<count($tits); $i++) {
    $idw= $tits[$i];
    $fld= $flds[$i];
    list($id,,$f,$sum)= array_merge(explode(':',$idw),array_fill(0,4,''));
    if ( $sum=='s' ) $suma[$fld]= 0;
    if ( isset($f) ) $fmts[$fld]= $f;
  }
  // průchod pobyty
  $rows= 0;
  foreach ($ret as $n=>$x) {
    $rows++;
    $x= (object)$x;
    // nazveme pečovatele
    if ($x->funkce==99) $x->rodice_= 'pečovatelé celkem';
    // projdeme ceník a přidáme položky
    $cen= akce2_vzorec2_pobyt($x->key_pobyt,0,$spec);
    $cen= $cen->full;
    // vyplnění polí
    foreach($flds as $if=>$f) {
      $val= 0;
      if ($if<$last_fld) { // mimo pomocné sloupce
        if (isset($cen[$f])) {
          $val= $cen[$f];
          $clmn[$n][$f]= $val;
        }
        else {
          $val= $f ? $x->$f : '';
          if ( $f ) $clmn[$n][$f]= $val; else $clmn[$n][]= $val;
        }
        // případný výpočet sumy
        if ( isset($suma[$f]) && is_numeric($val) ) {
           $suma[$f]+= $val;
        }
      }
    }
  }
//  /**/                                           debug($clmn,"sestava pro $akce,$typ,$fld,$cnd");
//  /**/                                           debug($expr,"vzorce pro $akce,$typ,$fld,$cnd");
//  /**/                                           debug($suma,"sumy pro $akce B");
  $result->sumy= $suma;
  // zobrazení tabulkou
  $tab= '';
  $ths= '';
  $cen= '';
  $mul= '';
  if ( $export ) {
    $result->tits= $tits;
    $result->flds= $flds;
    $result->clmn= $clmn;
    $result->expr= $expr;
    $result->koef= $cenik;
    $result->vertical= 1; // vertikální titulky
  }
  else {
    // titulky
    foreach ($tits as $idw) {
      list($id)= explode(':',$idw);
      $ths.= "<th class='vertical-text'>$id</th>";
    }
    // data
    foreach ($clmn as $i=>$c) {
      $tab.= "<tr>";
      foreach ($c as $id=>$val) {
        $style= akce2_sestava_td_style($fmts[$id]);
        $tab.= "<td$style>$val</td>";
      }
      $tab.= "</tr>";
    }
    // sumy
    $sum= '';
    if ( count($suma)>0 ) {
      $sum.= "<tr>";
      foreach ($flds as $if=>$f) {
        if ($if>=$last_fld) break;
        $val= isset($suma[$f]) ? $suma[$f] : '';
        $val_= number_format($val, 0, '.', ' ');
        if ($f=='rodice_') {
          $sum.= "<th style='text-align:right'>celkový počet</th>";
          $cen.= "<th style='text-align:right'>jednotková cena</th>";
          $mul.= "<th style='text-align:right'>celková cena</th>";
        }
        elseif ($f=='sleva') {
          $sum.= "<th style='text-align:right'>$val_</th>";
          $cen.= "<td></td>";
          $kc= -$val;
          $result->cena[$f]= $kc;
          $mul_= number_format($kc, 0, '.', ' ');
          $mul.= "<th style='text-align:right'>$mul_</th>";
        }
        else {
          $sum.= "<th style='text-align:right'>$val_</th>";
          if (isset($cenik[$f])) {
            $cen.= "<th style='text-align:right'>$cenik[$f]</th>";
            $kc= $cenik[$f]*$val;
            $result->cena[$f]= $kc;
            $mul_= number_format($kc, 0, '.', ' ');
            $mul.= "<th style='text-align:right'>$mul_</th>";
          }
          else {
            $cen.= "<td></td>";
            $mul.= "<td></td>";
          }
        }
      }
      $sum.= "</tr>";
    }
    $result->html= $note ? "$note<br>" : '';
    $result->html.= "Sestava má $rows řádků.<br><br>";
    $result->html.= "<div class='stat'><table class='stat'><tr>$ths</tr>$tab"
        . "$sum<tr>$cen</tr><tr>$mul</tr></table></div>";
    $result->html.= "</br>";
  }
  return $result;
}
# ------------------------------------------------------------------------------ akce2 vyuctov_pary2
# generování sestavy vyúčtování pro účastníky $akce - pro ceník verze 2 bez hnízd
#   $fld = seznam položek s prefixem
#   $cnd = podmínka
# počítané položky
#   manzele = rodina.nazev muz a zena
# generované vzorce
#   platit = součet předepsaných plateb
function akce2_vyuctov_pary_cv2($akce,$par,$title,$vypis,$export=false) { trace();
  $DPH1= 0.15; $DPH1_koef= "(15/115)"; $DPH1koef= 15/115; // 0.1304;
  $DPH2= 0.21; $DPH2_koef= "(21/121)"; $DPH2koef= 21/121; // 0.1736;
  $result= (object)array();
  $par= (object)[
    'tit'=> "Jméno:25"
      . ",pokoj:7,dětí:5:r:s,lůžka:5:r:s,spa cáky:5:r:s,bez lůžka:5:r:s,nocí:5:r"
      . ",str. celá:5:r:s,str. pol.:5:r:s"
      . ",cena ubyt.:7:r:s,cena strava:7:r:s,cena prog.:7:r:s,sleva:7:r:s,celkem:7:r:s"
      . ",na účet:7:r:s,datum platby:10:s"
      . ",nedo platek:6:r:s,člen. nedo platek:6:r:s,pokladna:6:r:s,datum platby:10:s,"
      . "přepl.:6:r:s,vrátit:6:r:s,datum vratky:10:s,důvod:7,poznámka:50,SPZ:9,.:7"
      . ",ubyt.:8:r:s,DPH:6:r:s,strava:8:r:s,DPH:6:r:s,režie:8:r:s,zapla ceno:8:r:s"
      . ",dota ce:6:r:s,nedo platek:6:r:s,dárce:25,dar:7:r:s,rozpočet organizace:10:r:s",
    'fld'=> "rodice_"
      . ",pokoj,#deti,luzka,spacaky,nazemi,noci,strava_cel,strava_pol"
      . ",c_nocleh,c_strava,c_program,c_sleva,=platit"
      . ",=uctem,=datucet"
      . ",=nedoplatek,=prispevky,=pokladna,datpokl,"
      . "=preplatek,=vratka,=datvratka,duvod,poznamka,r_spz,"
      . ",=ubyt,=ubytDPH,=strava,=stravaDPH,=rezie,=zaplaceno,"
      . "=dotace,=nedopl,=darce,=dar,=naklad"
        // pomocná pole
      . ",key_pobyt,funkce"
      . ",v_nocleh,v_strava,v_program,v_sleva"
      ,
    '_cnd'=> " p.funkce!=99 "
//      . " AND p.id_pobyt IN (69619,69409)"
    ];
  
  $href= '';
  $ret= tisk2_sestava_pary($akce,$par,'$title','$vypis',false,true);
//  /**/                                                   debug($ret,'tisk2_sestava_pary');
  // sežazení podle přítomnosti na akci a podle abecedy
  $collator= new Collator('cs_CZ'); // Nastavení českého jazyka
  usort($ret,function($x,$y) use ($collator) {
    $xin= $x['funkce']<=2 ? 1 : (in_array($x['funkce'],[10,14]) ? 3 : 2);
    $yin= $y['funkce']<=2 ? 1 : (in_array($y['funkce'],[10,14]) ? 3 : 2);
    $srt= $xin <=> $yin ?: $collator->compare($x['rodice_'],$y['rodice_']);
    return $srt;
  });
  // dekódování parametrů
//  $par->fld= str_replace(',key_pobyt,funkce','',$par->fld); // odstranění pomocných polí
  $tits= explode(',',$par->tit);
  $flds= explode(',',$par->fld);
  $last_fld= array_search('key_pobyt',$flds); // index prvního nezobrazovaného pole
  // získání dat - podle $kdo
  $clmn= array();       // pro hodnoty
  $expr= array();       // pro výrazy
  $suma= array();       // pro sumy sloupců id:::s
  $fmts= array();       // pro formáty sloupců id::f:
  for ($i= 0; $i<count($tits); $i++) {
    $idw= $tits[$i];
    $fld= $flds[$i];
    list($id,,$f,$sum)= array_merge(explode(':',$idw),array_fill(0,4,''));
    if ( $sum=='s' ) $suma[$fld]= 0;
    if ( isset($f) ) $fmts[$fld]= $f;
  }
  // průchod pobyty
  $rows= 0;
  foreach ($ret as $n=>$x) {
    $x= (object)$x;
    // dopočet úhrad
    $u= akce2_uhrady_load($x->key_pobyt);
//  /**/                                                   debug($u,'akce2_uhrady_load');
    $dary= akce2_dary_load($x->key_pobyt);
//  /**/                                                   debug($dary,'akce2_dary_load');
    // dopočet plateb
    $predpis= $x->c_nocleh + $x->c_strava + $x->c_program + $x->c_sleva;
    $predpis-= $x->v_nocleh + $x->v_strava + $x->v_program + $x->v_sleva;
    $platba= $u->platba_ucet + $u->platba_pokl; 
    $vratka= $u->vratka; 
    $preplatek= $platba > $predpis ? $platba - $predpis : 0;
    $nedoplatek= $platba < $predpis ? $predpis - $platba : 0;
    $naklad= $predpis - $x->c_sleva;
    // vynecháme ty, kteří byli odhlášeni, pokud nezaplatili
    if ($x->funkce==14 && $platba==0) {
      continue;
    }
    $rows++;
    // vyplnění polí
    foreach($flds as $if=>$f) {
      $exp= ''; $val= 0;
      if ( substr($f,0,1)=='=' ) {
        switch ($f) {
//        case '=pocetnoci':  $val= $x->noci;
//                            break;
        case '=platit':     $val= $predpis;
                            $exp= "=[c_nocleh,0]+[c_strava,0]+[c_program,0]+[c_sleva,0]"; break;
        case '=preplatek':  $val= $preplatek ?: '';
                            $exp= "=IF([=pokladna,0]+[=uctem,0]>[=platit,0],[=pokladna,0]+[=uctem,0]-[=platit,0],0)"; break;
        case '=nedoplatek': $val= $nedoplatek ?: '';
                            $exp= "=IF([=zaplaceno,0]<[=platit,0],[=platit,0]-[=zaplaceno,0],0)"; break;
        case '=uctem':      $val= $u->platba_ucet; break;
        case '=datucet':    $val= $u->datum_ucet; break;
        case '=pokladna':   $val= $u->platba_pokl; break;
        // nedoplatek členského příspěvku činného člena
        case '=prispevky':  $val= ($dary->clenstvi && $dary->prispevky!=100*$dary->clenstvi 
                                ? 100*$dary->clenstvi-$dary->prispevky : '-'); break;
        case '=ubyt':       $val= round($x->c_nocleh - $x->c_nocleh*$DPH1koef);
                            $exp= "=[c_nocleh,0]-[=ubytDPH,0]"; break;
        case '=ubytDPH':    $val= round($x->c_nocleh*$DPH1koef);
                            $exp= "=ROUND([c_nocleh,0]*$DPH1_koef,0)"; break;
        case '=strava':     $val= round($x->c_strava - $x->c_strava*$DPH2koef);
                            $exp= "=[c_strava,0]-[=stravaDPH,0]"; break;
        case '=stravaDPH':  $val= round($x->c_strava*$DPH2koef);
                            $exp= "=ROUND([c_strava,0]*$DPH2_koef,0)"; break;
        case '=rezie':      $val= 0+$x->c_program;
                            $exp= "=[c_program,0]"; break;
        case '=vratka':     $val= $vratka; break;
        case '=datvratka':  $val= $u->datum_vratka; break;
        case '=zaplaceno':  $val= 0+$platba-$vratka;
                            $exp= "=[=uctem,0]+[=pokladna,0]-[=vratka,0]"; break;
        case '=dotace':     $val= -$x->c_sleva;
                            $exp= "=-[c_sleva,0]"; break;
        case '=nedopl':     $val= $nedoplatek;
                            $exp= "=IF([=zaplaceno,0]<[=platit,0],[=platit,0]-[=zaplaceno,0],0)"; break;
        case '=darce':      $val= $preplatek-$vratka ? "dar - {$dary->darce}" : ''; break;
        case '=dar':        $val= $preplatek-$vratka;
                            $exp= "=IF([=zaplaceno,0]>[=platit,0],[=zaplaceno,0]-[=platit,0],0)"; break;
        case '=naklad':     $val= $naklad;
                            $exp= "=[=platit,0]-[c_sleva,0]"; break;
        case '=jmena':      $val= $x->_jm; break;
        default:            $val= '???'; break;
        }
        $clmn[$n][$f]= $val;
        if ( $exp ) $expr[$n][$f]= $exp;
      }
      elseif ($if<$last_fld) { // $f nezačíná =
        $val= $f ? $x->$f : '';
        if ( $f ) $clmn[$n][$f]= $val; else $clmn[$n][]= $val;
      }
      // případný výpočet sumy
      if ( isset($suma[$f]) && is_numeric($val) ) {
         $suma[$f]+= $val;
      }
    }
  }
//  /**/                                           debug($clmn,"sestava pro $akce,$typ,$fld,$cnd");
//  /**/                                           debug($expr,"vzorce pro $akce,$typ,$fld,$cnd");
//  /**/                                           debug($suma,"sumy pro $akce B");
  // zobrazení tabulkou
  $tab= '';
  $ths= '';
  $flds= array_slice($flds,0,$last_fld);
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
        if ($id==='key_pobyt') break; // jen zobrazovaná pole
        $style= akce2_sestava_td_style($fmts[$id]);
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
    $result->html= "Jsou zobrazeni všichni evidovaní mimo pečounů. "
        . "<br>Sestava má $rows řádků.<br><br>";
    $result->html.= "<div class='stat'><table class='stat'><tr>$ths</tr>$sum$tab</table></div>";
    $result->html.= "</br>";
    $result->href= $href;
  }
  return $result;
}
