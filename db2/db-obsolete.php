<?php
# ======================================================================================> ceník v.1

# ---------------------------------------------------------------------------------- ==> . konstanty
global $diety,$diety_,$jidlo_;
 $diety= array('','_bm','_bl');  // postfix položek strava_cel,cstrava_cel,strava_pol,cstrava_pol
 $diety_= array(''=>'normal','_bm'=>'veget','_bl'=>'bezlep');
// bezmasá dieta na Pavlákové od roku 2017 není (ale pro Pražáky je)
//$diety= array('','_bl');  // postfix položek strava_cel,cstrava_cel,strava_pol,cstrava_pol
//$diety_= array(''=>'normal','_bl'=>'bezlep');
$jidlo_= array('sc'=>'snídaně celá','sp'=>'snídaně dětská','oc'=>'oběd celý',
               'op'=>'oběd dětský','vc'=>'večeře celá','vp'=>'večeře dětská');

# ------------------------------------------------------------------------------- akce2 strava_denne
# vrácení výjimek z providelné stravy jako pole
function akce2_strava_denne($od,$dnu,$cela,$polo) {  #trace('');
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
# -------------------------------------------------------------------------- akce2 strava_denne_save
# zapsání výjimek z pravidelné stravy - pokud není výjimka zapíše prázdný string
#   $x= ''|'_bm'|'bl'  - kód typu diety
#   $prvni - kód první stravy na akci
function akce2_strava_denne_save($id_pobyt,$dnu,$x,
    $cela,$cela_def,$cela_str,$polo,$polo_def,$polo_str,$prvni) {  trace('');
  $cela_ruzna= $polo_ruzna= 0;
  $i0= $prvni=='s' ? 0 : ($prvni=='o' ? 1 : ($prvni=='v' ? 2 : 2));
  // zjístíme, zda je vůbec nějaká výjimka
  for ($i= $i0; $i<3*$dnu-1; $i++) {
    if ( substr($cela,$i,1)!=$cela_def ) $cela_ruzna= 1;
    if ( substr($polo,$i,1)!=$polo_def ) $polo_ruzna= 1;
  }
  if ( !$cela_ruzna ) $cela= '';
  if ( !$polo_ruzna ) $polo= '';
  // příprava update
  $set= '';
  if ( ";$cela"!=";$cela_str" ) $set.= "cstrava_cel$x='$cela'";     // ; jako ochrana pro pochopení jako čísla
  if ( ";$polo"!=";$polo_str" ) $set.= ($set?',':'')."cstrava_pol$x='$polo'";
  if ( $set ) {
    $qry= "UPDATE pobyt SET $set WHERE id_pobyt=$id_pobyt";
    $res= pdo_qry($qry);
  }
//                                                 display("akce_strava_denne_save(($id_pobyt,$dnu,$cela,$cela_def,$polo,$polo_def) $set");
  return 1;
}
# ------------------------------------------------------------------------- akce2 pobyt_default_vsem
# provedení akce2_pobyt_default pro všechny
function akce2_pobyt_default_vsem($id_akce) {  trace();
  $warn= $zmeny= '';
  $a= akce2_id2a($id_akce);
  $ro= pdo_qry("
    SELECT id_pobyt,prijmeni,
      CONCAT(cstrava_cel,cstrava_cel_bm,cstrava_cel_bl,
             cstrava_pol,cstrava_pol_bm,cstrava_pol_bl) AS _c
    FROM pobyt 
    JOIN spolu USING (id_pobyt)
    JOIN osoba USING (id_osoba)
    WHERE id_akce=$id_akce AND funkce!=99 
    -- AND id_pobyt IN (54153)
    GROUP BY id_pobyt
  ");
  while ( $ro && (list($id_pobyt,$prijmeni,$spec_strava)= pdo_fetch_row($ro)) ) {
    // test prázdnosti speciálních strav tj. cstrava_cel*,cstrava_pol*
    if ( $spec_strava ) {
      $warn.= " $prijmeni má nastavenu speciální stravu ";
    }
    $x= akce2_pobyt_default($id_akce,$id_pobyt,1,1);
    $warn.= $x->warn;
    // pokud nebylo varování - zápis do pobytu 
    if ( !$x->warn ) {
      query("UPDATE pobyt SET luzka=$x->luzka,kocarek=$x->kocarek,strava_cel=$x->strava_cel,
        strava_pol=$x->strava_pol,pocetdnu=$x->pocetdnu,svp=$x->svp,vzorec=$x->vzorec 
        WHERE id_pobyt=$id_pobyt");
      // aplikace vzorce
      if ( $a->soubeh ) {
        $c= akce2_vzorec_soubeh($id_pobyt,$id_akce,$a->soubezna); 
        if ( $c->err ) $warn.= " {$c->err} (pobyt $id_pobyt)";
      }
      else {
        $c= akce2_vzorec($id_pobyt);
//                                              debug($c,"akce2_vzorec($id_pobyt)");
      }
      if ( !isset($c->err) || !$c->err ) {
        // zápis ceny
        query("UPDATE pobyt SET 
          platba1='{$c->c_nocleh}',platba2='{$c->c_strava}',platba3='{$c->c_program}',
          platba4='{$c->c_sleva}',poplatek_d='{$c->poplatek_d}',naklad_d='{$c->naklad_d}'
          WHERE id_pobyt=$id_pobyt");
      }
      // informace o změnách kategorie dětí do warn
      if ( $x->zmeny_kat) 
        $zmeny.= "<br>$x->zmeny_kat";
    }
  }
  $info= $warn 
      ? "$warn<hr>výše uvedeným nebyly platby předepsány" 
      : 'byly předepsány všechny platby' ;
  $info.= $zmeny
      ? "<hr>následujícím dětem byla změněny kategorie $zmeny"
      : " nebyla změněna kategorie žádnému dítěti";
  return $info;
}
# ------------------------------------------------------------------------------ akce2 pobyt_default
# definice položek v POBYT podle počtu a věku účastníků - viz akce_vzorec_soubeh
# 150216 při vyplnění dite_kat budou stravy počítány podle _cis/ms_akce_dite_kat.barva
# 130522 údaje za chůvu budou připsány na rodinu chovaného dítěte
# 130524 oživena položka SVP
# 190501 pokud je $zapsat=1 budou dětem stanoveny kategorie podle věku
# 210510 hnízda
# 250224 nastavení spolu.pulstrava má přednost před kategorií
function akce2_pobyt_default($id_akce,$id_pobyt,$zapsat=0) {  //trace();
  $warn= '';
  $zmeny_kat= array(); // pro zapsat==1 bude obsahovat provedené změny kategorie u dětí
  $dite_kat= xx_akce_dite_kat($id_akce);
  $akce_dite_kat_Lp=  map_cis($dite_kat,'barva'); // {L|P|-},{c|p} = lůžko/pristylka/bez, celá/poloviční
  $akce_dite_kat_vek= map_cis($dite_kat,'ikona'); // od-do
  $akce_dite_kat_zkr= map_cis($dite_kat,'zkratka'); // zkratka
  $akce_funkce= map_cis('ms_akce_funkce','zkratka');
  // projítí společníků v pobytu
  $dosp= $deti= $koje= $noci= $sleva= $fce= $svp= 0;
  $luzka= $pristylky= $bez= $cela= $polo= 0;
  $msg= '';
  $qo= "SELECT o.prijmeni,o.jmeno,o.narozeni,a.datum_od,DATEDIFF(datum_do,datum_od) AS _noci,p.funkce,
         s.pecovane,s.s_role,s.dite_kat,id_spolu,s.pulstrava,
         (SELECT CONCAT(osoba.id_osoba,',',pobyt.id_pobyt)
          FROM pobyt
          JOIN spolu ON spolu.id_pobyt=pobyt.id_pobyt
          JOIN osoba ON osoba.id_osoba=spolu.id_osoba
          WHERE pobyt.id_akce=a.id_duakce AND spolu.pecovane=o.id_osoba) AS _chuva
        FROM spolu AS s JOIN osoba AS o USING(id_osoba) JOIN pobyt AS p USING(id_pobyt)
        JOIN akce AS a ON p.id_akce=a.id_duakce
        WHERE id_pobyt=$id_pobyt";
  $ro= pdo_qry($qo);
  while ( $ro && ($o= pdo_fetch_object($ro)) ) {
    if ( $o->_chuva ) {
      $dosp++; $luzka++; $cela++;       // platíme za chůvu vlastního dítěte
      $svp++;                           // ale ne za obecného pečouna
    }
    if ( $o->pecovane) {                // za dítě-chůvu platí rodič pečovaného dítěte
      $dosp--; $luzka--; $cela--;
    }
    $noci= $o->_noci;
    $fce= $o->funkce;
    $jmeno= "<i>{$o->prijmeni} {$o->jmeno}</i>";
    $_fce= $akce_funkce[$fce];
    if ( $_fce=='-' ) $_fce= 'účastník';
    $vek0= narozeni2roky(sql2stamp($o->narozeni),sql2stamp($o->datum_od));
    $vek= roku_k($o->narozeni,$o->datum_od);
//                                        display("$_fce $jmeno vek=$vek ($vek0)");
    $msg.= " {$o->jmeno}:$vek";
    // s-role: 2,3,4=dítě, s peč. ,pom.peč. - v tom případě je otevřena volba dite-kat
    if ( in_array($o->s_role,array(2,3,4)) ) {
      $ktg= $o->dite_kat;
      // pokud prepsat=1 => kategorie dítěte bude stanovena podle věku
      // a informace o změně bude zapsána do zmeny_kat[]
      if ( $zapsat ) {
        $ok= 0;
        foreach ($akce_dite_kat_vek as $kat=>$veky) {
          list($od,$do)= explode('-',$veky);
          if ( $vek>=$od && $vek<$do) {
            if ($kat!=$ktg) {
              $zmeny_kat[]= array($o->id_spolu,"$o->prijmeni $o->jmeno",$ktg,$kat);
              query("UPDATE spolu SET dite_kat=$kat WHERE id_spolu={$o->id_spolu} ");
            }
            $ok= 1;
            break;
          }
        }
        if ( !$ok ) 
          $warn.= " $_fce $jmeno nemá dětský věk, ";
      }
      if ( $ktg ) {
        // pokud je definována kategorie podle _cis/akce_dite_kat ALE dítě není pečoun
        $deti++;
        list($spani,$strava)= explode(',',$akce_dite_kat_Lp[$ktg]);
        // lůžka
        if ( $spani=='L' )      $luzka++;
        elseif ( $spani=='P' )  $pristylky++;
        elseif ( $spani=='-' )  $bez++;
        else $err+= "chybná kategorie dítěte";
        // strava
        if ( $strava=='c' )     $cela++;
        elseif ( $strava=='p' ) $polo++;
        else $err+= "chybná kategorie dítěte";
      }
      else {
        $warn.= "$_fce $jmeno nemá nastavenou kategorii, ";
      }
    }
    else {
      if ( $vek>18 || in_array($o->s_role,array(0,5)) ) {
        $dosp++;  $luzka++; $cela++; // dospělý lůžko celá
      }
      else {
        $warn.= " $_fce $jmeno nemá 18 let, ";
      }
//      // jinak se orientujeme podle věkových hranic: 0-3-10-18
//      if     ( $vek<3  ) { $koje++;  $bez++; }                  // dítě bez lůžka a stravy
//      elseif ( $vek<10 ) { $deti++;  $luzka++; $polo++; }       // dítě lůžko poloviční
//      elseif ( $vek<18 ) { $deti++;  $luzka++; $cela++; }       // dítě lůžko celá
//      else               { $dosp++;  $luzka++; $cela++; }       // dospělý lůžko celá
    }
  }
  // určení vzorce
  $vzorec= 
      in_array($fce,array(1,2)) ?   1 : (
      in_array($fce,array(5)) ?     2 : (
      in_array($fce,array(3,4,6)) ? 3 : 0));      
  // vrácení hodnot
  $ret= (object)array('luzka'=>$luzka,'pristylky'=>$pristylky,'kocarek'=>$bez,'pocetdnu'=>$noci,'svp'=>$svp,
                      'strava_cel'=>$cela,'strava_pol'=>$polo,'vzorec'=>$vzorec,'vek'=>$vek,
                      'warn'=>$warn);
  if ($zapsat) { 
//                        if (count($zmeny_kat)) debug($zmeny_kat,"změny kategorie dětí");
    $zmeny= $del= '';
    foreach ($zmeny_kat as $z) {
      // z~array($o->id_spolu,"o.prijmeni o.jmeno",$ktg,$kat);
      $zmeny.= "$del$z[1] - místo {$akce_dite_kat_zkr[$z[2]]} je {$akce_dite_kat_zkr[$z[3]]}";
      $del= ', ';
    }
    $ret->zmeny_kat= $zmeny;    
  }
//                                                debug($ret,"osob:$koje,$deti,$dosp $msg fce=$fce");
  return $ret;
}
# -------------------------------------------------------------------------------- akce2 vzorec_expr
# test výpočtu platby za pobyt na akci pro ceník verze 2017
# $expr = {n}*{n2}..*pro.za + ...  kde N je písmeno znamenající počet nocí, O počet obědů
function akce2_vzorec_expr($id_akce,$hnizdo,$expr) {  trace();
  $expr= str_replace(' ','',$expr);
  $html= '';
  // akce
  list($ma_cenik,$noci,$strava_oddo,$hnizda)=
    select("ma_cenik,DATEDIFF(datum_do,datum_od),strava_oddo,hnizda","akce","id_duakce=$id_akce");
  $obedu= $noci + ($strava_oddo=='oo' ? 1 : 0);
  if ( !$ma_cenik ) { $html= 'akce nemá ceník'; goto end; }
  // ceník
  $AND_hnizdo= $hnizda ? "AND hnizdo=$hnizdo" : '';
  $cenik= array();
  $ra= pdo_qry("SELECT cena,pro,za FROM cenik WHERE id_akce=$id_akce AND za!='' $AND_hnizdo");
  while ( $ra && (list($cena,$pro,$za)= pdo_fetch_row($ra)) ) {
    foreach (str_split($pro) as $prox) {
      $cenik[$prox.$za]= $cena;
    }
  }
//                                                 debug($cenik);
  // výpočet
  $cena= 0;
  $terms= preg_split("/([+-])/m",$expr,-1,PREG_SPLIT_DELIM_CAPTURE);
  $count= count($terms);
  for ($j= 0; $j<$count; $j= $j+2 ) {
    $term= $terms[$j];
    $sign= $j ? $terms[$j-1] : '+';
    $n= explode('*',$term);
    $last= count($n)-1;
    list($pro,$vek)= explode('/',$pro);
    if ( !isset($cenik[$n[$last]]) ) {
      $html= "cena pro {$n[$last]} není v ceníku definovaná"; goto end;
    }
    $n[$last]= $cenik[$n[$last]];
    $ns= 1;
    for ($i= 0; $i<=$last; $i++) {
      $x=           $n[$i]=='N'
        ? $noci : ( $n[$i]=='O'
        ? $obedu
        : $n[$i]);
      $ns*= $x;
    }
    $cena+= $sign=='-' ? -$ns : $ns;
                                                display(" $sign $term=$ns ... $cena ");
  }
//                                                 debug($terms,$count);
  $html= "$expr = <b>$cena,-</b> <br><br><i>(N=$noci je počet nocí a menu, O=$obedu je počet obědů)</i>";
end:
  return $html;
}
//# --------------------------------------------------------------------------- akce2 vzorec_expr_2017
//# test výpočtu platby za pobyt na akci pro ceník verze 2017
//# $expr = {n}*{n2}..*pro.za + ...
//#   kde věk je věk, N je písmeno znamenající počet nocí, O počet obědů
//function akce2_vzorec_expr_2017($id_akce,$expr,$vek) {  trace();
//  $expr= str_replace(' ','',$expr);
//  $html= $err= '';
//  // akce
//  list($ma_cenik,$noci,$strava_oddo)=
//    select("ma_cenik,DATEDIFF(datum_do,datum_od),strava_oddo","akce","id_duakce=$id_akce");
//  $obedu= $noci + ($strava_oddo=='oo' ? 1 : 0);
//  if ( !$ma_cenik ) { $err= 'akce nemá ceník'; goto end; }
//  // výpočet
//  $cena= 0;
//  $terms= preg_split("/([+-])/m",$expr,-1,PREG_SPLIT_DELIM_CAPTURE);
//  $count= count($terms);
//  for ($j= 0; $j<$count; $j= $j+2 ) {
//    // rozdělení na sčítance
//    $term= $terms[$j];
//    $sign= $j ? $terms[$j-1] : '+';
//    // rozdělení na součinitele
//    $n= explode('*',$term);
//    $last= count($n)-1;
//    $proza= $n[$last];
//    // rozbor posledního pro.za.vek
//    list($pro,$za)= str_split($proza);
//    // zjištění ceny z ceníku
//    $AND= isset($vek) ? "AND IF(od,$vek>=od,1) AND IF(do,$vek<do,1)" : '';
//    $res= pdo_qry("
//      SELECT cena,GROUP_CONCAT(poradi),COUNT(*)
//      FROM cenik WHERE id_akce=$id_akce AND pro LIKE BINARY '%$pro%' AND '$za'= BINARY za $AND
//    ");
//    list($n[$last],$poradi,$pocet)= pdo_fetch_row($res);
//    if ( $pocet==0 ) {
//      $err= "cena pro $proza není v ceníku definovaná"; goto end;
//    }
//    elseif ( $pocet>1 ) {
//      $err= "cena pro $proza není v ceníku jednoznačná (řádky $poradi)"; goto end;
//    }
//    $ns= 1;
//    for ($i= 0; $i<=$last; $i++) {
//      $x=           $n[$i]=='N'
//        ? $noci : ( $n[$i]=='O'
//        ? $obedu
//        : $n[$i]);
//      $ns*= $x;
//    }
//    $cena+= $sign=='-' ? -$ns : $ns;
//                                                display(" $sign $term=$ns ... $cena ");
//  }
////                                                 debug($terms,$count);
//end:
//  $html= "<br><br>$expr (věk $vek let) ";
//  $html.= $err
//    ? "<div style='color:red'>$err</div>"
//    : "= <b>$cena,-</b> <br><br><i>(N=$noci je počet nocí a menu, O=$obedu je počet obědů)</i>";
//  return $html;
//}
# -------------------------------------------------------------------------------- akce2 vzorec_test
# test výpočtu platby za pobyt na akci 
function akce2_vzorec_test($id_akce,$hnizdo=0,$nu=2,$nD=0,$nd=0,$nk=0,$np=0,$table_class='') {  trace();
  $ret= (object)array('navrh'=>'','cena'=>0,'err'=>'');
  $map_typ= map_cis('ms_akce_ubytovan','zkratka');
  $types= select("GROUP_CONCAT(DISTINCT typ ORDER BY typ)","cenik",
      "id_akce=$id_akce AND hnizdo=$hnizdo GROUP BY id_akce");
  if (!$types) $types= '0';
  // obecné info o akci
  list($ma_cenik,$noci,$strava_oddo)=
    select("ma_cenik,DATEDIFF(datum_do,datum_od),strava_oddo","akce","id_duakce=$id_akce");
                                                display("$ma_cenik,$noci,$strava_oddo - typy:$types ");
  if ( !$ma_cenik ) { $html= "akce nemá ceník"; goto end; }
  // definované položky
  $o= $strava_oddo=='oo' ? 1 : 0;       // oběd navíc
  $cenik= array(
    //            u p D d k noci oo plus
    'Nl' => array(1,0,1,0,0,   1, 0,  1),
    'Np' => array(0,1,1,1,0,   1, 0,  1),
    'K'  => array(1,0,0,0,0,   1, 0,  1),
    'P'  => array(1,0,0,0,0,   0, 0,  1),
    'PD' => array(0,0,1,0,0,   0, 0,  1),
    'Pd' => array(0,0,0,1,0,   0, 0,  1),
    'Pk' => array(0,0,0,0,1,   0, 0,  1),
    'Su' => array(1,0,0,0,0,   0, 0, -1),
    'Sk' => array(0,0,0,0,1,   0, 0, -1),
    'sc' => array(1,1,0,0,0,   1, 0,  1),
    'oc' => array(1,1,0,0,0,   1,$o,  1),
    'vc' => array(1,1,0,0,0,   1, 0,  1),
    'sp' => array(0,0,1,1,0,   1, 0,  1),
    'op' => array(0,0,1,1,0,   1,$o,  1),
    'vp' => array(0,0,1,1,0,   1, 0,  1),
  );
  // výpočet ceny podle parametrů jednotlivých typů (jsou-li)
  foreach(explode(',',$types) as $typ) {
                                                display("typ:$typ, hnízdo:$hnizdo ");
    $title= $typ ? "<h3>ceny pro ".$map_typ[$typ]."</h3>" : '';
    $cena= 0;
    $html.= "$title<table class='$table_class'>";
    $ra= pdo_qry("SELECT * FROM cenik 
      WHERE id_akce=$id_akce AND hnizdo=$hnizdo AND za!='' AND typ='$typ' ORDER BY poradi");
    while ( $ra && ($a= pdo_fetch_object($ra)) ) {
      $acena= $a->cena;
      list($za_u,$za_up,$za_D,$za_d,$za_k,$za_noc,$oo,$plus)= $cenik[$a->za];
      $nx= $nu*$za_u + $np*$za_up + $nD*$za_D + $nd*$za_d + $nk*$za_k;
      $cena+= $cc= $nx * ($za_noc?$noci+$oo:1) * $acena * $plus;
      if ( $cc ) {
        $pocet= $za_noc?" * ".($noci+$oo):'';
        $html.= "<tr>
          <td>{$a->polozka} ($nx$pocet * $acena)</td>
          <td align='right'>$cc</td></tr>";
      }
    }
    $html.= "<tr><td><b>Celkem</b></td><td align='right'><b>$cena</b></td></tr>";
    $html.= "</table>";
  }
  // návrat
end:
  $ret->cena= $cena;
  $ret->navrh= $html;
  return $ret;
}
# ------------------------------------------------------------------------------ akce2 vzorec_soubeh
# výpočet platby za pobyt na hlavní akci, včetně platby za souběžnou akci (děti)
# pokud je $id_pobyt=0 provede se výpočet podle dodaných hodnot (dosp+koje)
function akce2_vzorec_soubeh($id_pobyt,$id_hlavni,$id_soubezna,$dosp=0,$deti=0,$koje=0) { trace();
  // načtení ceníků
  $sleva= 0;
  $ret= (object)array('navrh'=>'','err'=>'','naklad_d'=>0,'poplatek_d'=>0);
  $dite_kat= xx_akce_dite_kat($id_hlavni);
  $map_kat= map_cis($dite_kat,'zkratka');
    $Kc= "&nbsp;Kč";
  $hnizdo= 0;
  if ( $id_pobyt ) {
    // zjištění parametrů pobytu podle hlavní akce
    $qp= "SELECT * FROM pobyt AS p JOIN akce AS a ON p.id_akce=a.id_duakce WHERE id_pobyt=$id_pobyt";
    $rp= pdo_qry($qp);
    if ( $rp && ($p= pdo_fetch_object($rp)) ) {
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
    $n= $ndeti= $chuv= 0;
    $qo= "SELECT o.jmeno,s.dite_kat,p.funkce, t.role, p.ubytovani, narozeni, p.funkce, p.hnizdo,
           s.pecovane,(SELECT CONCAT(osoba.prijmeni,',',osoba.jmeno,',',pobyt.id_pobyt)
            FROM pobyt
            JOIN spolu ON spolu.id_pobyt=pobyt.id_pobyt
            JOIN osoba ON osoba.id_osoba=spolu.id_osoba
            WHERE pobyt.id_akce=p.id_akce AND spolu.pecovane=o.id_osoba) AS _chuva
          FROM spolu AS s
          JOIN osoba AS o ON s.id_osoba=o.id_osoba
          JOIN pobyt AS p USING(id_pobyt)
          LEFT JOIN tvori AS t ON t.id_osoba=o.id_osoba AND t.id_rodina=p.i0_rodina
          WHERE id_pobyt=$id_pobyt";
    $ro= pdo_qry($qo);
    while ( $ro && ($o= pdo_fetch_object($ro)) ) {
      $hnizdo= $o->hnizdo;
      $vek= narozeni2roky(sql2stamp($o->narozeni),sql2stamp($datum_od));
      $kat= $o->dite_kat;
      $pps= $o->funkce==1;
      if ( $o->role=='d' ) {
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
        if ( $o->_chuva ) {
          list($prijmeni,$jmeno,$pobyt)= explode(',',$o->_chuva);
          if ( $pobyt!=$id_pobyt ) {
            // chůva nebydlí s námi ale platíme ji
            $chuv++;
          }
          else {
            // chůva bydlí s námi a platíme ji
            $chuv++;
          }
        }
      }
      $n++;
    }
    // kontrola počtu
    if ( $dosp + $chuv + $ndeti != $n ) {
      $ret->err.= "<br>chyba v počtech: dospělí $dosp + chůvy $chuv + děti $ndeti není celkem $n";
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
  $dosp_chuv= $dosp+$chuv;
//                                         debug($deti_kat,"dětí");
  // načtení ceníků
  $cenik_dosp= $cenik_deti= array();
  akce2_nacti_cenik($id_hlavni,$hnizdo,$cenik_dosp,$ret->navrh);   if ( $ret->navrh ) goto end;
  akce2_nacti_cenik($id_soubezna,$hnizdo,$cenik_deti,$ret->navrh); if ( $ret->navrh ) goto end;
  // redakce textu k ceně dospělých
  $Kc= "&nbsp;Kč";
  $html.= "<b>Rozpis platby za účast dospělých na jejich akci</b><table>";
  $cena= 0;
  $ubytovani= $strava= $program= $slevy= '';
  foreach($cenik_dosp as $za=>$a) {
    $c= $a->c; $txt= $a->txt;
    switch ($za) {
    case 'Nl':
      $cena+= $cc= $dosp_chuv * $pocetdnu * $c;
      if ( !$cc ) break;
      $ret->c_nocleh+= $cc;
      $ubytovani.= "<tr><td>".($dosp_chuv*$pocetdnu)." x $txt ($c$Kc)</td><td align='right'>$cc$Kc</td></tr>";
      break;
    case 'P':
      $cena+= $cc= $c * $dosp;
      if ( !$cc ) break;
      $ret->c_program+= $cc;
      $program.= "<tr><td>$dosp x $txt ($c$Kc)</td><td align='right'>$cc$Kc</td></tr>";
      break;
    case 'Su':
      if ( $pps ) continue 2;
      $cena+= $cc= - $c * $dosp;
      if ( !$cc ) break;
      $ret->c_sleva+= $cc;
      $slevy.= "<tr><td>$dosp x $txt ($c$Kc)</td><td align='right'>$cc$Kc</td></tr>";
      break;
    case 'Sp':
    case 'Sv':
      if ( !$pps ) continue 2;
      $cena+= $cc= - $c * $dosp;
      if ( !$cc ) break;
      $ret->c_sleva+= $cc;
      $slevy.= "<tr><td>$dosp x $txt ($c$Kc)</td><td align='right'>$cc$Kc</td></tr>";
      break;
    case 'sc': case 'oc': case 'vc':
      $strav= $dosp_chuv * ($pocetdnu + ($za=='oc' && $strava_oddo=='oo' ? 1 : 0)); // případně oběd navíc
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
  $sleva= "";
  if ( count($deti_kat) ) {
    $html.= "<br><b>Rozpis platby za účast dětí na jejich akci</b><table>";
    $cena= 0;
    ksort($deti_kat);
    foreach($deti_kat as $kat=>$n) {
      $a= $cenik_deti["p$kat"]; $c= $a->c; $txt= $a->txt;
      $cena+= $cc= $c * $n;
      $ret->naklad_d+= $cc;
      $html.= "<tr><td>$n x $txt </td><td align='right'>$cc$Kc</td></tr>";
      $a= $cenik_deti["d$kat"]; $c= $a->c; $txt= $a->txt;
      $cena+= $cc= - $c * $n;
      $ret->poplatek_d+= $cc;
      $sleva.= "<tr><td>$n x $txt </td><td align='right'>$cc$Kc</td></tr>";
    }
    $ret->poplatek_d+= $ret->naklad_d;
    $html.= "<tr><th>sleva</th></tr>$sleva";
    $html.= "<tr><th>Celkem za děti</th><th align='right'>$cena$Kc</th></tr>";
    $html.= "</table>";
  }
end:
  if ( $ret->err ) $ret->navrh.= "<b style='color:red'>POZOR! neúplná platba:</b>{$ret->err}<hr>";
  $ret->navrh.= $html;
  $ret->mail= "<div style='background-color:#eeeeee;margin-left:15px'>$html</div>";
//                                                         debug($ret,"akce_vzorec_soubeh");
  return $ret;
}
# -------------------------------------------------------------------------------- akce2 nacti_cenik
# lokální pro akce2_vzorec_soubeh a tisk2_sestava_lidi
function akce2_nacti_cenik($id_akce,$hnizdo,&$cenik,&$html) {
  $qa= "SELECT * FROM cenik WHERE id_akce=$id_akce AND hnizdo=$hnizdo ORDER BY poradi";
  $ra= pdo_qry($qa);
  if ( !pdo_num_rows($ra) ) {
    $html.= "akce $id_akce nemá ceník";
  }
  else {
    $cenik= array();
    while ( $ra && ($a= pdo_fetch_object($ra)) ) {
      $za= $a->za;
      if ( !$za ) continue;
      $cc= (object)array();
      if ( isset($cenik[$za]) ) $html.= "v ceníku se opakují kódy za=$za";
      $cenik[$za]= (object)array('c'=>$a->cena,'txt'=>$a->polozka);
    }
//                                                        debug($cenik,"ceník pro $id_akce");
  }
}
# ------------------------------------------------------------------------------------- akce2 vzorec
# výpočet platby za pobyt na akci
# od 130416 přidána položka CENIK.typ - pokud je 0 tak nemá vliv,
#                                       pokud je nenulová pak se bere hodnota podle POBYT.ubytovani
function akce2_vzorec($id_pobyt) {  //trace();
  // případné přepnutí na ceník verze 2017
  list($id_akce,$cenik_verze,$ma_cenik,$ma_cenu)= select(
    "id_akce,ma_cenik_verze,ma_cenik,ma_cenu",
    "pobyt JOIN akce ON id_akce=id_duakce","id_pobyt=$id_pobyt");
//  if ( $cenik_verze==1 ) return akce2_vzorec_2017($id_pobyt,$id_akce,2017);
  $ok= true;
  $ret= (object)array(
      'navrh'=>'cenu nelze spočítat',
      'c_sleva'=>0,
      'eko'=>(object)array(
          'vzorec'=>(object)array(),
          'slevy'=>(object)array('kc'=>0)
      ));
  if (!$ma_cenik && !$ma_cenu) {
    $ret->navrh= "akce nemá ani ceník ani jednotnou cenu (karta AKCE)";
    goto end; // další výpočet nemá smysl
  }
  // parametry pobytu
  $x= (object)array();
  $ubytovani= 0;
  $qp= "SELECT * FROM pobyt AS p
        JOIN akce AS a ON p.id_akce=a.id_duakce WHERE id_pobyt=$id_pobyt";
  $rp= pdo_qry($qp);
  if ( $rp && ($p= pdo_fetch_object($rp)) ) {
    $id_akce= $p->id_akce;
    $x->nocoluzka+= $p->luzka * $p->pocetdnu;
    $x->nocoprist+= $p->pristylky * $p->pocetdnu;
    $ucastniku= $p->pouze ? 1 : 2;
    $vzorec= $p->vzorec;
    $ubytovani= $p->ubytovani;
    $sleva= $p->sleva;
    $svp= $p->svp;
    $neprijel= $p->funkce==10 || $p->funkce==14;
    $datum_od= $p->datum_od;
    $hnizda= $p->hnizda ? 1 : 0;
  }
  // podrobné parametry, ubytovani ma hodnoty z číselníku ms_akce_ubytovan
  // děti: koje=do 3 let | male=od 3 do 6 | velke=nad 6
  $dosp= $deti_male= $deti_velke= $koje= $chuv= $dite_male_chovane= $dite_velke_chovane= $koje_chovany= 0;
  $chuvy= $del= '';
  $qo= "SELECT o.jmeno,o.narozeni,p.funkce,t.role, p.ubytovani,p.hnizdo,
         s.pecovane,(SELECT CONCAT(osoba.prijmeni,',',osoba.jmeno,',',pobyt.id_pobyt)
          FROM pobyt
          JOIN spolu ON spolu.id_pobyt=pobyt.id_pobyt
          JOIN osoba ON osoba.id_osoba=spolu.id_osoba
          WHERE pobyt.id_akce=p.id_akce AND spolu.pecovane=o.id_osoba) AS _chuva
        FROM spolu AS s
        JOIN osoba AS o ON s.id_osoba=o.id_osoba
        JOIN pobyt AS p USING(id_pobyt)
        LEFT JOIN tvori AS t ON t.id_osoba=o.id_osoba AND t.id_rodina=p.i0_rodina
        WHERE id_pobyt=$id_pobyt
        GROUP BY o.id_osoba";
  $ro= pdo_qry($qo);
  while ( $ro && ($o= pdo_fetch_object($ro)) ) {
    $vek= narozeni2roky(sql2stamp($o->narozeni),sql2stamp($datum_od));
    if ( $o->role=='d' ) {
      if ( $o->pecovane ) {
        $chuv++;
      }
      elseif ( $vek<3 ) {
        $koje++;
        if ( $o->_chuva ) $koje_chovany++;
      }
      elseif ( $vek<6 ) {
        $deti_male++;
        if ( $o->_chuva ) $dite_male_chovane++;
      }
      else {
        $deti_velke++;
        if ( $o->_chuva ) $dite_velke_chovane++;
      }
      if ( $o->_chuva ) {
        list($prijmeni,$jmeno,$pobyt)= explode(',',$o->_chuva);
        if ( $pobyt!=$id_pobyt ) {
          // chůva nebydlí s námi ale platíme ji
          $chuvy= "$del$jmeno $prijmeni";
          $del= ' a ';
        }
        else {
          // chůva bydlí s námi a platíme ji
        }
      }
    }
    elseif ($vek>18) {
      $dosp++;
    }
  }
//                                                         debug($x,"pobyt");
  // zpracování strav
//  $strava= akce2_strava_pary($id_akce,'','','',true,$id_pobyt);
  $strava= akce2_strava($id_akce,(object)array(),'','',true,0,$id_pobyt);
//                                                         debug($strava,"strava"); 
  $jidel= (object)array();
  foreach ($strava->suma as $den_jidlo=>$pocet) {
    list($den,$jidlo)= explode(' ',$den_jidlo);
    $jidlo= substr($jidlo,0,2);
    $jidel->$jidlo+= $pocet;
  }
//                                                         debug($jidel,"strava"); goto end;
  // načtení cenového vzorce a ceníku
  $vzor= array();
  $qry= "SELECT * FROM _cis WHERE druh='ms_cena_vzorec' AND data=$vzorec";
  $res= pdo_qry($qry);
  if ( $res && $c= pdo_fetch_object($res) ) {
    $vzor= $c;
    $vzor->slevy= json_decode($vzor->ikona);
    $ret->eko->slevy= $vzor->slevy;
  }
//                                                         debug($vzor);
  // načtení ceníku do pole $cenik s případnou specifikací podle typu ubytování
  $AND_hnizdo= $hnizda ? "AND hnizdo=$p->hnizdo" : '';
  $qa= "SELECT * FROM cenik WHERE id_akce=$id_akce $AND_hnizdo ORDER BY poradi";
  $ra= pdo_qry($qa);
  $n= $ra ? pdo_num_rows($ra) : 0;
  if ( !$n ) {
    $html.= "akce {$pobyt->id_akce} nemá cenový vzorec";
    $ok= false;
  }
  else {
    $cenik= array();
    $cenik_typy= false;
    $nazev_ceniku= '';
    while ( $ra && ($a= pdo_fetch_object($ra)) ) {
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
          $cena+= $cc;
          $ret->c_nocleh+= $cc;
          $html.= "<tr><td>{$a->txt} ($nl*{$a->c})</td><td align='right'>$cc</td></tr>";
          break;
        case 'Np':
          $cc= $np * $a->c;
          $cena+= $cc;
          $ret->c_nocleh+= $cc;
          $html.= "<tr><td>{$a->txt} ($np*{$a->c})</td><td align='right'>$cc</td></tr>";
          break;
        case 'K':
          $poplatku= $dosp * $p->pocetdnu;
          $cc= $poplatku * $a->c;
          $cena+= $cc;
          $ret->c_nocleh+= $cc;
          $html.= "<tr><td>{$a->txt} ($poplatku*{$a->c})</td><td align='right'>$cc</td></tr>";
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
        case 'Pd': // pro děti malé 3-6 let
          if ( $deti_male - $dite_male_chovane > 0 ) {
            $cc= $a->c * ($deti_male-$dite_male_chovane);
            $cena+= $cc;
            $ret->c_program+= $cc;
            $ret->eko->vzorec->{$a->za}+= $cc;
            $html.= "<tr><td>{$a->txt}</td><td align='right'>$cc</td></tr>";
          }
          break;
        case 'PD': // pro děti velké > 6 let
          if ( $deti_velke - $dite_velke_chovane > 0 ) {
            $cc= $a->c * ($deti_velke-$dite_velke_chovane);
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
//        if ( !isset($ret->eko) ) $ret->eko= (object)array();
//        if ( !isset($ret->eko->slevy) ) $ret->eko->slevy= (object)array();
        if ( !isset($ret->eko->slevy->kc) ) $ret->eko->slevy->kc= 0;
//        if ( !isset($ret->eko->slevy->kc) ) { debug($ret); return $ret; }
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
end:  
  return $ret;
}//akce2_vzorec
# ---------------------------------------------------------------------------------- akce2 stravenky
function akce2_strava($akce,$par,$title,$vypis,$export=false,$hnizdo=0,$id_pobyt=0) { trace();
  global $diety,$diety_,$jidlo_;
  $dny= array('ne','po','út','st','čt','pá','so');
  $jidlo= array();
  $datum_od= select('datum_od','akce',"id_duakce=$akce");
  $den1= sql2stamp($datum_od);             // začátek akce ve formátu mktime
  $vylet= select1('DATE_FORMAT(ADDDATE(datum_od,2),"%e/%c")','akce',"id_duakce=$akce");
//   $diety= array('','_bm','_bl');                             -- globální nastavení
  foreach ($diety as $d) {
    foreach (array('vj','vjp') as $par_typ) {
    // sběr počtu jídel pro konkrétní dietu (normální strava=dieta 0)
      $par->dieta= $d;
      $par->typ= $par_typ;
      $res= akce2_stravenky_diety($akce,$par,"$title {$diety_[$d]}","$vypis$d",$export,$hnizdo,$id_pobyt);
      foreach ($res->tab_i as $den) {
        foreach ($den as $datum=>$jidla) {
          foreach ($jidla as $sov=>$cp) {
            foreach ($cp as $x=>$pocet) {
              if (!isset($jidlo[$datum][$d][$sov][$x])) $jidlo[$datum][$d][$sov][$x]= 0;
              $jidlo[$datum][$d][$sov][$x]+= $pocet;
            }
          }
        }
      }
    }
  }
  ksort($jidlo);
  $days= array_keys($jidlo);
  $days_fmt= array();
  // redakce
  $result= (object)array('html'=>'','href'=>'');
  // získání dat - podle $kdo
  $clmn= array();       // pro hodnoty
  $expr= array();       // pro výrazy
  $suma= array();       // pro sumy sloupců id:::s
  $fmts= array();       // pro formáty sloupců id::f:
  $flds= array();

  $tits[0]= "den:15";
  $flds[0]= "day";
  foreach (explode(',','s,o,v') as $jidlo1) {
    foreach ($diety as $dieta) {
      foreach (explode(',','c,p') as $porce) {
        $jidlox= $jidlo1.$porce;
        $tits[]= "{$jidlo_[$jidlox]} {$diety_[$dieta]}:8:r:s";
        $flds[]= "$jidlox $dieta";
      }
    }
  }
//                                                         debug($tits);
//                                                         debug($flds);
//                                                         debug($jidlo,'suma');
  // součet přes lidi
//  goto end;
  $d= 0;
  $ths= $tab= $href= '';
  foreach ($days as $day) {
    $d++;
    $mkden= mktime(0, 0, 0, date("n", $den1), date("j", $den1)+$day, date("Y", $den1));
    $po_ne= "{$dny[date('w',$mkden)]} ";
    $den= date("j/n",$mkden);
    $days_fmt[$day]= $den;
//    display("$mkden==$vylet");
    $clmn[$den]['day']= $po_ne . $den . ($den==$vylet ? " odečíst výlet" : '');
    foreach (explode(',','s,o,v') as $jidlo1) {
      foreach ($diety as $dieta) {
        foreach (explode(',','c,p') as $porce) {
          $jidlox= $jidlo1.$porce;
          $fld= "$den $jidlox $dieta";
          $fld2= "$den $jidlox$dieta";
          $sum= isset($jidlo[$day][$dieta][$jidlo1][$porce]) ? $jidlo[$day][$dieta][$jidlo1][$porce] : 0;
          $clmn[$den][$fld]= $sum;
          $suma[$fld2]+= $sum;
        }
      }
    }
  }
//                                                         debug($clmn,'clmn');
//  goto end;
  // zobrazení a export
  if ( $export ) {
    $result->tits= $tits;
    $result->flds= $flds;
    $result->clmn= $clmn;
    $result->expr= $expr;
    $result->suma= $suma;
    $result->days= $days_fmt;
  }
  else {
    // titulky
    foreach ($tits as $idw) {
      list($id)= explode(':',$idw);
      $ths.= "<th>$id</th>";
    }
    // data
    foreach ($clmn as $c) {
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
      foreach ($flds as $f) {
        $val= isset($suma[$f]) ? $suma[$f] : '';
        $sum.= "<th style='text-align:right'>$val</th>";
      }
      $sum.= "</tr>";
    }
  }
end:  
  $result->html.= "<h3>Souhrn strav podle dnů, rozdělený podle typů stravy vč. diet</h3>";
  $result->html.= "<div class='stat'><table class='stat'><tr>$ths</tr>$sum$tab</table></div>";
  $result->href= $href;
  return $result;
}
# ---------------------------------------------------------------------------------- akce2 stravenky
# generování stravenek účastníky $akce - rodinu ($par->typ=='vj') resp. pečouny ($par->typ=='vjp')
#   $cnd = podmínka
# počítané položky
#   manzele = rodina.nazev muz, zena a děti
# generované vzorce
#   platit = součet předepsaných plateb
# výstupy
#   note = pro pečouny seznam jmen, pro které nejsou stravenky, protože nemají funkci
#          (tzn. asi nejsou na celý pobyt)
function akce2_stravenky($akce,$par,$title,$vypis,$export=false,$hnizdo=0,$id_pobyt=0) { trace();
                                      debug($par,"akce2_stravenky($akce,...,$title,$vypis,$export,$hnizdo,$id_pobyt)");
  global $diety,$diety_,$jidlo_;
  $res_all= (object)array('res'=>array(),'html'=>'','jidel'=>array(),'max_jidel'=>0);
//   $diety= array('','_bm','_bl');                             -- globální nastavení
  foreach ($diety as $i=>$d) {
    // generování stravenek pro konkrétní dietu (normální strava=dieta 0)
    $par->dieta= $d;
    $res= akce2_stravenky_diety($akce,$par,"$title {$diety_[$d]}","$vypis$d",$export,$hnizdo,$id_pobyt);
    $res->dieta= $d;
    $res->nazev_diety= $diety_[$d];
    $res_all->res[]= $res;
    $res_all->html.= "<h3>Strava {$diety_[$d]}</h3>";
    $res_all->html.= $res->html;
    $res_all->html.= $res->note;
    // celkový počet jídel bez ohledu na dietu
    $res_all->max_jidel= max($res_all->max_jidel,$res->max_jidel);
    if (count($res_all->jidel)) {
      foreach (array_keys($res_all->jidel) as $jidlo) {
        $res_all->jidel[$jidlo]+= $res->jidel[$jidlo];
      }
    }
    else $res_all->jidel= $res->jidel;
  }
                                                debug($res_all->jidel,"celkem jídel - maximum=$res_all->max_jidel");
  return $res_all;
}
# ---------------------------------------------------------------------------- akce2 stravenky_diety
# bezmasá dieta na Pavlákové od roku 2017 nevaří
# proto se u pečounů mapuje bezmasá dieta na normální
function akce2_stravenky_diety($akce,$par,$title,$vypis,$export=false,$hnizdo=0,$id_pobyt=0) { //trace();
//                                 debug($par,"akce_stravenky_diety($akce,,$title,$vypis,$export)");
  global $diety,$diety_,$jidlo_;  // $diety= array(''/*,'_bm'*/,'_bl')
  $ord= isset($par->ord) ? $par->ord : "IF(funkce<=2,1,funkce),IF(pouze=0,r.nazev,o.prijmeni)";
  $result= (object)array();
  $jidel= array('sc'=>0,'sp'=>0,'oc'=>0,'op'=>0,'vc'=>0,'vp'=>0,);
  $cnd= $par->cnd;
  if ( $hnizdo ) $cnd.= " AND IF(funkce=99,s_hnizdo=$hnizdo,hnizdo=$hnizdo)";
  $dieta= $par->dieta;
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
  $resa= pdo_qry($qrya);
  if ( $resa && ($a= pdo_fetch_object($resa)) ) {
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
  $cond= $id_pobyt ? "p.id_pobyt=$id_pobyt" : $cnd;
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
  // data akce
  $akce_data= (object)array();
  $dny= array('ne','po','út','st','čt','pá','so');
  if ( $par->typ=='vjp' ) { // pečouni
    switch ($dieta) {
    case '':
      $AND= "AND o.dieta!=1";
      break;
    case '_bm':
      $AND= "AND o.dieta=4";
//       fce_error("nepodporovaná dieta");
      break;
    case '_bl':
      $AND= "AND o.dieta=1";
      break;
    default:
      fce_error("nepodporovaná dieta");
    }
    $qry="SELECT o.prijmeni,o.jmeno,s.pfunkce,YEAR(datum_od) AS _rok,
            a.nazev AS akce_nazev, YEAR(a.datum_od) AS akce_rok, a.misto AS akce_misto,
            p_od_pobyt, p_od_strava, p_do_pobyt, p_do_strava
          FROM pobyt AS p
          JOIN akce  AS a ON p.id_akce=a.id_duakce
          JOIN spolu AS s USING(id_pobyt)
          JOIN osoba AS o ON s.id_osoba=o.id_osoba
          WHERE p.id_akce='$akce' AND $cond AND p.funkce=99 AND s_rodici=0 AND p_kc_strava=0 $AND
            -- AND id_spolu IN (137569,137010)
          ORDER BY o.prijmeni,o.jmeno";
  }
  else { // rodiny
    $qry="SELECT /*akce2_stravenky_diety*/ strava_cel$dieta AS strava_cel,strava_pol$dieta AS strava_pol,
            cstrava_cel$dieta AS cstrava_cel,cstrava_pol$dieta AS cstrava_pol,
            p.pouze,
            IF(p.i0_rodina,CONCAT(r.nazev,' ',
              GROUP_CONCAT(po.jmeno ORDER BY role SEPARATOR ' a '))
             ,GROUP_CONCAT(DISTINCT CONCAT(pso.prijmeni,' ',pso.jmeno)
               ORDER BY role SEPARATOR ' a ')) as _jm,
            a.nazev AS akce_nazev, YEAR(a.datum_od) AS akce_rok, a.misto AS akce_misto,
            a.hnizda AS akce_hnizda
          FROM pobyt AS p
          JOIN akce AS a ON p.id_akce=a.id_duakce
          LEFT JOIN rodina AS r ON r.id_rodina=p.i0_rodina
          JOIN spolu AS ps ON ps.id_pobyt=p.id_pobyt
          LEFT JOIN tvori AS pt ON pt.id_rodina=p.i0_rodina
            AND role IN ('a','b') AND ps.id_osoba=pt.id_osoba
          LEFT JOIN osoba AS po ON po.id_osoba=pt.id_osoba
          JOIN osoba AS pso ON pso.id_osoba=ps.id_osoba
          WHERE p.id_akce='$akce' AND $cond AND p.funkce NOT IN (9,10,13,14,15)
          GROUP BY p.id_pobyt
          ORDER BY _jm";
  }
//   $qry.=  " LIMIT 1";
  $res= pdo_qry($qry);
  // stravenky - počty po dnech
  $str= array();  // $strav[kdo][den][jídlo][typ]=počet   kdo=jména,den=datum,jídlo=s|o|v, typ=c|p
  $str_i= array();  // $strav[kdo][den][jídlo][typ]=počet   kdo=jména,den=pořadí,jídlo=s|o|v, typ=c|p
  // s uvážením $oo='sv' - první jídlo prvního dne a poslední jídlo posledního dne
  $jidlo= array('s','o','v');
  $xjidlo= array('s'=>0,'o'=>1,'v'=>2);
  $jidlo_1= $xjidlo[$oo[0]];
  $jidlo_n= $xjidlo[$oo[1]];
  while ( $res && ($x= pdo_fetch_object($res)) ) {
    if ( $par->typ=='vjp' && $x->pfunkce==0 && $x->_rok>2012 ) {        // !!!!!!!!!!!!!! od roku 2013
      $note.= "$delnote{$x->prijmeni} {$x->jmeno}";
      $delnote= ", ";
      continue;
    }
    $n++;
    $akce_data->nazev= $x->akce_nazev;
    $akce_data->rok=   $x->akce_rok;
    $akce_data->misto= $x->akce_misto;
    $akce_data->hnizda= $x->akce_hnizda;
    $str_kdo= array();
    $str_kdo_i= array();
    $clmn[$n]= array();
    $stravnik= $par->typ=='vjp' ? "{$x->prijmeni} {$x->jmeno}" : $x->_jm;
    $clmn[$n]['manzele']= $stravnik;
    // stravy
    if ( $par->typ=='vjp' ) { // pečoun => podle p_od* a p_do* nastav csc a csp
      $sc= $sp= 0; $csp= ''; // nemůže být použito
      // vytvoření řetězce cstrava_cel pro danou dietu
      $csc= str_repeat('0',3*($nd+1));
      $od_pobyt= $x->p_od_pobyt;
      $od_strava= $x->p_od_strava ? $x->p_od_strava - 1 : $jidlo_1;
      $do_pobyt= $nd - $x->p_do_pobyt;
      $do_strava= $x->p_do_strava ? $x->p_do_strava - 1 : $jidlo_n;
      for ($i= 3*$od_pobyt+$od_strava; $i<=3*$do_pobyt+$do_strava; $i++) {
        $csc[$i]= '1';
      }
//      display("$stravnik '$csc'  $od_pobyt..$do_pobyt ($nd)"); debug($x);
    }
    else {
      $sc= $x->strava_cel;
      $sp= $x->strava_pol;
      $csc= $x->cstrava_cel;
      $csp= $x->cstrava_pol;
    }
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
        $str_kdo_i[$i]= $str_den;
      }
    }
    $kdo= $stravnik;
    $str[$kdo]= $str_kdo;
    $str_i[$kdo]= $str_kdo_i;
  }
//                                                         debug($str,"stravenky");
//                                                         debug($suma,"sumy");
  // titulky
  $ths= $tab= '';
  foreach ($tits as $idw) {
    list($id)= explode(':',$idw);
    $ths.= "<th>$id</th>";
  }
  // data
  $radku= 0;
  foreach ($clmn as $i=>$c) {
    $pocet= 0;
    foreach ($c as $val) {
      if (!is_numeric($val)) continue;
      $pocet+= $val;
    }
    if ( $pocet ) {
      $tab.= "<tr>";
      foreach ($c as $id=>$val) {
        $style= akce2_sestava_td_style($fmts[$id]);
        $tab.= "<td$style>$val</td>";
      }
      $tab.= "</tr>";
      $radku++;
    }
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
    // celkový počet jídel
    $max_jidel= 0;
    foreach ($suma as $den_jidlo=>$pocet) {
      $jidlo= mb_substr($den_jidlo,4,2);
      $jidel[$jidlo]+= $pocet;
      $max_jidel= max($max_jidel,$pocet);
    }
  }
  $result->html= "Seznam má $radku řádků<br><br>";
  $result->html.= "<div class='stat'><table class='stat'><tr>$ths</tr>$sum$tab</table></div>";
  $result->html.= "</br>";
  $result->href= $href;
  $result->jidel= $jidel; // celkový počet jídel
  $result->max_jidel= $max_jidel; // celkový počet jídel
  $result->tab= $str;
  $result->tab_i= $str_i;
  $result->akce= $akce_data;
  $result->note= $note ? "(bez $note, kteří nemají vyjasněnou funkci)" : '';
//  $result->suma= $suma;
//                                                      debug($jidel,"celkem jídel - max = $max_jidel - $dieta");
  return $result;
}
# ------------------------------------------------------------------------------- akce2 strava_vylet
# generování sestavy přehledu strav pro účastníky $akce - páry
#   $cnd = podmínka
#   $id_pobyt -- je-li udáno, počítá se jen pro tento jeden pobyt (jedněch účastníků)
# počítané položky
#   manzele = rodina.nazev muz a zena
# generované vzorce
#   platit = součet předepsaných plateb
function akce2_strava_vylet($akce,$par,$title,$vypis,$export=false,$id_pobyt=0) { //trace();
  global $diety,$diety_,$jidlo_,$EZER, $tisk_hnizdo;
  // ofsety v atributech členů pobytu
  global $i_osoba_jmeno, $i_osoba_vek, $i_osoba_role, $i_osoba_prijmeni, $i_adresa, 
  $i_osoba_kontakt, $i_osoba_telefon, $i_osoba_email, $i_osoba_note, $i_key_spolu, $i_spolu_note;
//                                                                 debug($par,"akce2_strava_souhrn");
  $result= (object)array('html'=>'');
  // zjistíme datum dne výletu tj. třetího dne LK
  $vylet= select1('DATE_FORMAT(ADDDATE(datum_od,2),"%e/%c")','akce',"id_duakce=$akce");
  // projdeme páry s dětmi ve věku nad 3 roky a děti sečteme
  $pocet_deti= $pocet_cele= $pocet_polo= 0;
  $cnd= "p.funkce NOT IN (9,10,13,14,15,99) AND p.hnizdo=$tisk_hnizdo ";
  $browse_par= (object)array(
    'cmd'=>'browse_load','cond'=>"$cnd AND p.id_akce=$akce",'having'=>'','order'=>'a _nazev',
    'sql'=>"SET @akce:=$akce,@soubeh:=0,@app:='{$EZER->options->root}';");
  $y= ucast2_browse_ask($browse_par,true);
  # rozbor výsledku browse/ask
  $sum= 0;
  $tab= '';
  array_shift($y->values);
  foreach ($y->values as $x) {
    // údaje pobytu $x->pobyt
    $xs= explode('≈',$x->r_cleni);
    $vek_deti= array();
    $deti_nad3= $cel= $pol= $chuv= 0;
    foreach ($xs as $i=>$xi) {
      $o= explode('~',$xi);
      if ( $o[$i_key_spolu] && $x->key_rodina ) {
        if ( $o[$i_osoba_role]=='p' ) {
          $chuv++;
        }
        if ( $o[$i_osoba_role]=='d' ) {
          $vek= $o[$i_osoba_vek];
          if ( $vek<3 ) break;
          $vek_deti[]= $vek;
          $deti_nad3++;
          $pocet_deti++;
        }
      }
    }
    if ( !$deti_nad3 ) continue;
//     $test= array(48838,49080,48553);
//    $test= array(48673);
//     if ( in_array($x->key_pobyt,$test) ) {
//       $tab.= "<br>{$x->key_pobyt} {$x->_nazev} (děti nad 3 mají roků:".implode(',',$vek_deti).") ";
      $tab.= "<br>{$x->_nazev} (věk dětí starších 3 let: ".implode(',',$vek_deti).") ";
//      $ret= akce2_strava_pary($akce,$par,$title,$vypis,$export,$x->key_pobyt);
      $ret= akce2_strava($akce,(object)array(),'',$vypis,true,0,$x->key_pobyt);
      foreach ($diety as $dieta) {
        $cel+= $ret->suma["$vylet oc$dieta"];
        $pol+= $ret->suma["$vylet op$dieta"];
//                                                         debug($ret->suma,"$vylet op$dieta = $pol");
      }
//       $tab.= "... cele=$cel polo=$pol";
      // odečteme stravu rodičů - asi cc cp pp
      if ( $cel+$pol >= 2+$chuv ) {
        // něco zůstane na děti
        if ( $cel>=2+$chuv ) { $cel-= 2+$chuv; }
        elseif ( $cel==1 ) { $cel--; $pol--; }
        else { $pol-= 2; }
      }
      $tab.= "... objednali asi cele=$cel polo=$pol";
      // pokud je víc strav jak děti3 tak asi je i to 3 leté
      $pod3= ($cel+$pol) - $deti_nad3;
      if ( $pod3 > 0 ) {
        // tak je odečteme ... spolehneme se, že namá celou
        if ( $pol >= $pod3 ) { $pol-= $pod3; }
        $tab.= "...oprava: cele=$cel polo=$pol  ($deti_nad3,$pod3)";
      }
//     }//test
    $pocet_cele+= $cel;
    $pocet_polo+= $pol;
  }
  $sum.= "<p> Dětí nad 3 roky je $pocet_deti ... mají  objednaných asi
    $pocet_cele celých obědů a $pocet_polo polovičních</p>";
end:
  $result->html.= "<h3>Odhad obědů objednaných pro děti nad 3 roky na den $vylet</h3>";
  $result->html.= "$sum<br><hr>protože si myslím, že <br>$tab";
  return $result;
}
# ----------------------------------------------------------------------------------- akce2 text_eko
function akce2_text_eko($akce,$par,$title,$vypis,$export=false) { trace();
  global $tisk_hnizdo;
  $result= (object)array();
  $html= '';
  // zjištění, zda má akce nastavený ceník
  if (!select('COUNT(*)','cenik',"id_akce=$akce")) { 
    $html= "Tato akce nemá nastavený ceník, ekonomické ukazatele nelze tedy spočítat";
    goto end;
  }
  $prijmy= 0;
  $vydaje= 0;
  $pary= 0;
  $prijem= array();
  // zjištění mimořádných pečovatelů
  $qm="SELECT id_spolu FROM pobyt AS p  JOIN akce  AS a ON p.id_akce=a.id_duakce
      JOIN spolu AS s USING(id_pobyt) WHERE p.id_akce='$akce' AND p.funkce=99 AND s.pfunkce=6 ";
  $rm= pdo_qry($qm);
  $n_mimoradni= pdo_num_rows($rm);
//   $mimoradni= $n_mimoradni ? "platba za stravu a ubytování $n_mimoradni mimořádných pečovatelů, kterou uhradili" : '';

  // -------------------------------------------- příjmy od účastníků na pečouny
//                                                        display("příjmy od účastníků");
  $test_n= $test_kc= 0;
  $limit= '';
//   $limit= "AND id_pobyt IN (17957,18258,18382)";
  $qp=  "SELECT id_pobyt,funkce FROM pobyt WHERE id_akce='$akce' AND funkce IN (0,1,2,5,6) $limit ";
  $rp= pdo_qry($qp);
  while ( $rp && ($p= pdo_fetch_object($rp)) ) {
    $pary++;
    $ret= akce2_vzorec($p->id_pobyt); // bere do úvahy hnízda
    if ( $ret->err ) { $html= $ret->err; goto end; }
//                                                         if ($ret->eko->slevy) {
//                                                         debug($ret->eko->slevy,"sleva pro fce={$p->funkce}");
//                                                         goto end; }
    if ( $ret->eko->vzorec ) {
//                                                         debug($ret->eko,"vzorec {$p->id_pobyt}");
      foreach ($ret->eko->vzorec as $x=>$kc) {
        if ( !isset($prijem[$x]) ) $prijem[$x]= (object)array();
        $prijem[$x]->vzorec+= $kc;
        $prijem[$x]->pocet++;
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
//  /**/                                                  debug($prijem,"prijem");
  // zobrazení příjmů
  $rows_vydaje= '';
  $rows_prijmy= '';
  $qc= "SELECT GROUP_CONCAT(DISTINCT polozka) AS polozky, za
        FROM cenik
        WHERE id_akce='$akce' AND za!=''
        GROUP BY za ORDER BY poradi ASC";
  $rc= pdo_qry($qc);
  while ( $rc && ($c= pdo_fetch_object($rc)) ) {
    if ( $prijem[$c->za]->vzorec ) {
      $cena= $platba= '';
      if ( $prijem[$c->za]->vzorec ) $cena= $prijem[$c->za]->vzorec;
      if ( $prijem[$c->za]->platba ) $platba= $prijem[$c->za]->platba;
      $_cena= number_format($cena, 0, '.', ' ');
      $platba= number_format($platba, 0, '.', ' ');
//       $rows_prijmy.= "<tr><th>{$c->polozky}</th><td align='right'>$cena</td><td align='right'>$platba</td></tr>";
      $rows_prijmy.= "<tr><td>{$c->polozky}</td><td align='right'>$_cena</td></tr>";
      if ( $c->za=='P' ) {
        $solid= $pary*200;
        $prijmy+= $solid;
        $_solid= number_format($solid, 0, '.', ' ');
        $rows_prijmy.= "<tr><td>... z toho solidárně po 100Kč na děti</td><td align='right'>$_solid</td></tr>";
      }
      else 
        $prijmy+= $cena;
    }
  }
//  /**/                                                    display("cena=$cena, platba=$platba");
//  /**/                                                    display("výdaj za pečouny");
  // --------------------------------------- náklad na stravu pečounů
  // nově podle počtu vydaných stravenek BEZ HNIZD!!
  $hnizda= select('hnizda','akce',"id_duakce=$akce");    
  if ($hnizda) fce_error("pro hnízda odhad nefunguje");
  $hnizdici= 0;
  $vydaje= $radni_vydaje= $mimoradni_vydaje= 0;
  $rows_vydaje= '';
  $radni= akce2_stravenky($akce,(object)array('typ'=>'vjp','cnd'=>'pfunkce!=6','zmeny'=>0),'','');
  $max_radni= $radni->max_jidel;
  $ra= pdo_qry("SELECT za,cena FROM cenik "
      . "WHERE id_akce=$akce AND za IN ('sc','sp','oc','op','vc','vp')");
  $mimoradni= akce2_stravenky($akce,(object)array('typ'=>'vjp','cnd'=>'pfunkce=6','zmeny'=>0),'','');
  $max_mimoradni= $mimoradni->max_jidel;
  $ra= pdo_qry("SELECT za,cena FROM cenik "
      . "WHERE id_akce=$akce AND za IN ('sc','sp','oc','op','vc','vp')");
  while ( $ra && (list($za,$cena)= pdo_fetch_array($ra)) ) {
    $pocet= $radni->jidel[$za];
    $radni_vydaje+= $pocet*$cena;
    $pocet= $mimoradni->jidel[$za];
    $mimoradni_vydaje+= $pocet*$cena;
  }
  $vydaje+= $radni_vydaje+$mimoradni_vydaje;
  $vydaje_f= number_format($vydaje, 0, '.', ' ');
  $radni_vydaje_f= number_format($radni_vydaje, 0, '.', ' ');
  $mimoradni_vydaje_f= number_format($mimoradni_vydaje, 0, '.', ' ');
  $prijmy+= $mimoradni_vydaje;
  $rows_vydaje.= "<tr><td>stravenky řádní pečovatelé (max. současně $max_radni)</td>"
      . "<td align='right'>$radni_vydaje_f</td></tr>";
  $rows_vydaje.= "<tr><td>stravenky mimořádní pečovatelé (max. současně $max_mimoradni)</td>"
      . "<td align='right'>$mimoradni_vydaje_f</td></tr>";
  $rows_vydaje.= "<tr><td>celkem</td><td align='right'>$vydaje_f</td></tr>";
  $rows_prijmy.= "<tr><td>stravenky $n_mimoradni mimořádní peč.</td><td align='right'>$mimoradni_vydaje_f</td></tr>";
  $pecounu= select('COUNT(*)','pobyt JOIN spolu USING(id_pobyt)',
      "id_akce='$akce' AND funkce=99 AND s_rodici=0");
  
/*  
  // postaru - kteří mají funkci a nemají zaškrtnuto "platí rodiče"
  // podle hnízd nebo celkově
  $pecounu= select('COUNT(*)','pobyt JOIN spolu USING(id_pobyt)',
      "id_akce='$akce' AND funkce=99 AND s_rodici=0");
  $hnizda= select('hnizda','akce',"id_duakce=$akce");    
  $pecouni= array();
  $hnizdici= 0;
  $vydaje= 0;
  if ($hnizda) {
    foreach (explode(',',$hnizda) AS $i=>$h) {
      $ih= $i+1;
      $nh= select('COUNT(*)','pobyt JOIN spolu USING(id_pobyt)',
              "id_akce='$akce' AND funkce=99 AND s_hnizdo=$ih AND s_rodici=0");
      $vzorec= akce2_vzorec_test($akce,$ih,0,0,0,0,$nh,'stat');
      $pecouni[]= (object)array('nazev'=>trim($h),'pocet'=>$nh,html=>$vzorec->navrh);
      $hnizdici+= $nh;
      $vydaje+= $vzorec->cena;
    }
  }
  else {
    $vzorec= akce2_vzorec_test($akce,0,0,0,0,0,$pecounu,'stat');
    $pecouni[]= (object)array('nazev'=>'','pocet'=>$pecounu,'html'=>$vzorec->navrh);
    $vydaje= $vzorec->cena;
  }
*/
//  /**/                                                  debug($pecouni,"celkem $pecounu/$hnizdici");
  // odhad příjmů za mimořádné pečouny - přičtení k příjmům
//  if ( $n_mimoradni ) {
//    $cena_mimoradni= $vydaje*$n_mimoradni/$pecounu;
//    $prijmy+= $cena_mimoradni;
//    $cena= number_format($cena_mimoradni, 0, '.', ' ');
//    $rows_prijmy.= "<tr><td>ubytování a strava $n_mimoradni mimoř.peč.</td>
//      <td align='right'>$cena</td></tr>";
//  }
  // formátování odpovědi dle ceníku akce
  $h= $tisk_hnizdo ? "(souhrně za všechna hnízda)" : '';
  $html.= "<h3>Příjmy za akci $h podle aktuální skladby účastníků</h3>";
  $html.= "Pozn. pokud jsou někteří pečovatelé tzv. mimořádní, předpokládá se, že jejich pobyt 
    <br>je uhrazen mimo pečovatelský rozpočet.<br>";
//   $html.= "Pozn. pro přehled se počítá také cena s uplatněnou procentní slevou (např. VPS)<br>";
//   $html.= "(příjmy pro pečovatele se počítají z plné tzn. vyšší ceny)<br>";
//   $html.= "<br><table class='stat'><td>položky</td><th>cena bez slev</th><th>cena po slevě</th></tr>";
  $html.= "<br><table class='stat'>";
//  $html.= "<tr><td>položky</td><th>cena</th></tr>";
  $html.= "$rows_prijmy</table>";
  $html.= "<h3>Výdaje za stravu pro $pecounu pečovatelů </h3>";
  $html.= "V tomto počtu nejsou zahrnuti pomocní a osobní pečovatelé, jejichž náklady hradí rodiče
           <br>(to je třeba v evidenční kartě pečovatele zapsat zaškrtnutím políčka pod poznámkou)
           <br>(od roku 2024 se zohledňují částečné pobyty a poloviční porce)";
  // stravenky nejsou vytištěny pro $note, kteří nemají jasnou funkci -- pfunkce=0
//  $html.= $ret->note ? "{$ret->note}<br>" : '';
//  $html.= "<br><table class='stat'><td>položky</td><th>cena</td></tr>";
//  $html.= "$rows_vydaje</table>";
  
  $html.= "<br><br><table class='stat'>$rows_vydaje</table>";

//  foreach ($pecouni as $hnizdo) {
//    if (!$hnizdo->pocet) continue;
//    $html.= $hnizdo->nazev 
//        ? "<h3>... hnízdo {$hnizdo->nazev} - {$hnizdo->pocet} pečovatelů</h3>" : '<br><br>';
//    $html.= $hnizdo->html;
//  }
  
  $html.= "<h3>Shrnutí pro pečovatele</h3>";
  $obrat= $prijmy - $vydaje;
  $prijmy= number_format($prijmy, 0, '.', ' ')."&nbsp;Kč";
  $vydaje= number_format($vydaje, 0, '.', ' ')."&nbsp;Kč";
  $obrat= number_format($obrat, 0, '.', ' ')."&nbsp;Kč";
  $html.= "Účastníci přispějí na děti a pečovatele částkou $prijmy, 
    <br>přímé náklady na stravu pečovatelů činí $vydaje, 
    <br>celkem <b>$obrat</b> zůstává na program dětí a pečovatelů.";
  $html.= "<br><br><br><span style='color:red'><b>DISCLAIMER</b>: "
      . "výpočet vychází pouze z údajů evidovaných v Answeru"
      . "<br><br>Neumí proto zahrnout"
      . "<br>příjmy: částka ušetřená za odřeknuté stravy, ..."
      . "<br>výdaje: ubytování pečovatelů, vicenáklady pečounů (pokoje se sprchami, ...), ..."
      . "<br><br>"
      . "mohl by umět ale neumí: přímé vyplacení stravy některým pečounům t.b.d."
      . "</span>";
end:
  // předání výsledku
  $result->html= $html;
  return $result;
}
# ------------------------------------------------------------------------------- akce2 sestava_noci
# generování sestavy přehledu člověkonocí pro účastníky $akce - páry
#   $cnd = podmínka
# počítané položky
#   manzele = rodina.nazev muz a zena
# generované vzorce
#   člověkolůžka, člověkopřistýlky
function akce2_sestava_noci($akce,$par,$title,$vypis,$export=false) { trace();
  global $tisk_hnizdo;
  $jen_hnizdo= $tisk_hnizdo ? " AND hnizdo=$tisk_hnizdo " : '';
  // definice sloupců
  $result= (object)array();
  $tit= "Manželé:25,pokoj:8:r,dnů:5:r,nocí:5:r,lůžek:5:r:s,dětí 3-6:5:r:s,lůžko nocí:5:r:s,přis týlek:5:r:s,přis týlko nocí:5:r:s";
  $fld= "manzele,pokoj,pocetdnu,=noci,luzka,=deti_3_6,=luzkonoci,pristylky,=pristylkonoci";
  $ord= isset($par->ord) ? $par->ord : "IF(funkce<=2,1,funkce),IF(pouze=0,r.nazev,o.prijmeni)";
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
    list($id,$w,$f,$sum)= array_merge(explode(':',$idw),array_fill(0,4,''));
    if ( $sum=='s' ) $suma[$fld]= 0;
    if ( isset($f) ) $fmts[$fld]= $f;
  }
  // data akce
  $datum_od= select("datum_od","akce","id_duakce=$akce");
  $qry=  "SELECT
            ( SELECT GROUP_CONCAT(o.narozeni) FROM spolu JOIN osoba USING (id_osoba)
              WHERE id_pobyt=p.id_pobyt GROUP BY id_pobyt ) AS _naroz,
            pokoj,luzka,pristylky,pocetdnu,
            r.id_rodina,prijmeni,jmeno,
            GROUP_CONCAT(DISTINCT IF(t.role='a',o.prijmeni,'') SEPARATOR '') as prijmeni_m,
            GROUP_CONCAT(DISTINCT IF(t.role='a',o.jmeno,'')    SEPARATOR '') as jmeno_m,
            GROUP_CONCAT(DISTINCT IF(t.role='b',o.prijmeni,'') SEPARATOR '') as prijmeni_z,
            GROUP_CONCAT(DISTINCT IF(t.role='b',o.jmeno,'')    SEPARATOR '') as jmeno_z,
            p.pouze,r.nazev
          FROM pobyt AS p
          JOIN spolu AS s USING(id_pobyt)
          JOIN osoba AS o ON s.id_osoba=o.id_osoba
          LEFT JOIN tvori AS t ON t.id_osoba=o.id_osoba
          LEFT JOIN rodina AS r ON r.id_rodina=IF(i0_rodina,i0_rodina,t.id_rodina)
          WHERE p.id_akce='$akce' AND funkce NOT IN (9,10,13,14,15,99) AND $cond $jen_hnizdo
          GROUP BY id_pobyt
          ORDER BY $ord";
//   $qry.=  " LIMIT 1";
  $res= pdo_qry($qry);
  while ( $res && ($x= pdo_fetch_object($res)) ) {
//                                         debug($x,"hodnoty");
    $n++;
    $clmn[$n]= array();
    foreach($flds as $f) {
      $exp= ''; $val= 0;
      $deti36= 0;
      foreach ( explode(',',$x->_naroz) as $narozeni) {
        $vek= $narozeni!='0000-00-00' ? roku_k($narozeni,$datum_od) : 0; // výpočet věku
        if ( $vek>=3 && $vek<6 )
          $deti36++;
      }

      if ( substr($f,0,1)=='=' ) {
        switch ($f) {
        case '=deti_3_6':     $val= $deti36; break;
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
             : ($x->id_rodina ? "{$x->nazev} {$x->jmeno_m} a {$x->jmeno_z}"
             : "{$x->prijmeni} {$x->jmeno}"));
          break;
        case 'jmena':
          $val= $x->pouze==1
              ? $x->jmeno_m : ($x->pouze==2 ? $x->jmeno_z : "{$x->jmeno_m} a {$x->jmeno_z}");
          break;
        case 'prijmeni':
          $val= $x->pouze==1 ? $x->prijmeni_m : ($x->pouze==2 ? $x->prijmeni_z : ($x->id_rodina ? $x->nazev : $x->prijmeni));
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
  // doplnění počtu pečovatelů do poznámky
  $pecounu= select1("COUNT(*)","pobyt JOIN spolu USING (id_pobyt)","id_akce='$akce' AND funkce IN (99)");
  $note= $pecounu ? "K údajům v tabulce je třeba přičíst ubytování <b>$pecounu</b> pečounů<br><br>" : "";
  // zobrazení tabulkou
  $tab= '';
  $ths= '';
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
    $result->html= "$note<div class='stat'><table class='stat'><tr>$ths</tr>$sum$tab</table></div>";
    $result->html.= "</br>";
    $result->href= $href;
  }
  return $result;
}
# ------------------------------------------------------------------------------- akce2 vyuctov_pary
# generování sestavy vyúčtování pro účastníky $akce - páry
#   $fld = seznam položek s prefixem
#   $cnd = podmínka
# počítané položky
#   manzele = rodina.nazev muz a zena
# generované vzorce
#   platit = součet předepsaných plateb
function akce2_vyuctov_pary($akce,$par,$title,$vypis,$export=false) { trace();
  global $tisk_hnizdo;
  $ord= isset($par->ord) ? $par->ord : "IF(funkce<=2,1,IF(funkce IN (10,14),3,2)),_jm";
  $result= (object)array();
  $tit= "Manželé:25"
      // . ",id_pobyt"
      . ",pokoj:7,dětí:5:r,lůžka:5:r:s,přis týlky:5:r:s,kočá rek:5:r:s,nocí:5:r:s"
      . ",str. celá:5:r:s,str. pol.:5:r:s"
      . ",platba ubyt.:7:r:s,platba strava:7:r:s,platba režie:7:r:s,sleva:7:r:s,CD:6:r:s,celkem:7:r:s"
      . ",na účet:7:r:s,datum platby:10:s"
      . ",nedo platek:6:r:s,člen. nedo platek:6:r:s,pokladna:6:r:s,datum platby:10:s,"
      . "přepl.:6:r:s,vrátit:6:r:s,datum vratky:10:s,důvod:7,poznámka:50,SPZ:9,.:7"
      . ",ubyt.:8:r:s,DPH:6:r:s,strava:8:r:s,DPH:6:r:s,režie:8:r:s,zapla ceno:8:r:s"
      . ",dota ce:6:r:s,nedo platek:6:r:s,dárce:25,dar:7:r:s,rozpočet organizace:10:r:s"
      . "";
  $fld= "=jmena"
      // . ",id_pobyt"
      . ",pokoj,_deti,luzka,pristylky,kocarek,=pocetnoci,strava_cel,strava_pol"
      . ",platba1,platba2,platba3,platba4,=cd,=platit"
      . ",=uctem,datucet"
      . ",=nedoplatek,=prispevky,=pokladna,datpokl,"
      . "=preplatek,=vratka,datvrat,duvod,poznamka,spz,"
      . ",=ubyt,=ubytDPH,=strava,=stravaDPH,=rezie,=zaplaceno,"
      . "=dotace,=nedopl,=darce,=dar,=naklad"
      . "";
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
    list($id,$w,$f,$sum)= array_merge(explode(':',$idw),array_fill(0,4,''));
    if ( $sum=='s' ) $suma[$fld]= 0;
    if ( isset($f) ) $fmts[$fld]= $f;
  }
  // data akce
  $qry=  "SELECT
            id_pobyt,pokoj,luzka,pristylky,kocarek,pocetdnu,
            strava_cel+strava_cel_bl+strava_cel_bm AS strava_cel,
            strava_pol+strava_pol_bl+strava_pol_bm AS strava_pol,
            platba1-vratka1 AS platba1,
            platba2-vratka2 AS platba2,
            platba3-vratka3 AS platba3,
            platba4-vratka4 AS platba4,
            -- vratka1,vratka2,vratka3,vratka4,
            -- c.ikona as pokladnou,platba,zpusobplat,datplatby,
            ( SELECT SUM(-u_castka) FROM uhrada AS u WHERE u.id_pobyt=p.id_pobyt AND u.u_stav=4) AS vratka,
            CASE funkce WHEN 14 THEN 'odhlášeni' WHEN 10 THEN 'nepřijeli' ELSE '' END AS duvod,
            ( SELECT SUM(u_castka) FROM uhrada AS u 
              WHERE u.id_pobyt=p.id_pobyt AND u.u_stav IN (1,2,3) AND u.u_zpusob!=3) AS uctem,
            ( SELECT SUM(u_castka) FROM uhrada AS u 
              WHERE u.id_pobyt=p.id_pobyt AND u.u_stav IN (1,2,3) AND u.u_zpusob=3) AS pokladnou,
            ( SELECT GROUP_CONCAT(DISTINCT DATE_FORMAT(u_datum,'%e/%c') SEPARATOR ', ') FROM uhrada AS u 
              WHERE u.id_pobyt=p.id_pobyt AND u.u_datum!='0000-00-00' AND u.u_zpusob!=3 AND u.u_stav!=4) AS datucet,
            ( SELECT GROUP_CONCAT(DISTINCT DATE_FORMAT(u_datum,'%e/%c') SEPARATOR ', ') FROM uhrada AS u 
              WHERE u.id_pobyt=p.id_pobyt AND u.u_datum!='0000-00-00' AND u.u_zpusob=3 AND u.u_stav!=4) AS datpokl,
            ( SELECT GROUP_CONCAT(DISTINCT DATE_FORMAT(u_datum,'%e/%c') SEPARATOR ', ') FROM uhrada AS u 
              WHERE u.id_pobyt=p.id_pobyt AND u.u_datum!='0000-00-00' AND u.u_stav=4) AS datvrat,
            -- SUM(IF(u_stav IN (1,2,3) AND u_zpusob=3,u_castka,0)) AS platba_pokl,
            -- SUM(IF(u_stav IN (1,2,3) AND u_zpusob!=3,u_castka,0)) AS platba_ucet,
            -- GROUP_CONCAT(DISTINCT u_datum SEPARATOR ', ') AS datplatby,
            cd,p.poznamka,r.nazev as nazev,r.spz,
            SUM(IF(t.role='d',1,0)) as _deti,
            IF(p.i0_rodina
              ,CONCAT(r.nazev,' ',GROUP_CONCAT(IF(role IN ('a','b'),o.jmeno,'') ORDER BY role SEPARATOR ' '))
              ,GROUP_CONCAT(DISTINCT CONCAT(so.prijmeni,' ',so.jmeno) SEPARATOR ' ')) as _jm,
            COUNT(dc.id_dar) AS _clenstvi,
            0+RIGHT(SUM(DISTINCT CONCAT(d.id_dar,LPAD(d.castka,10,0))),10) AS prispevky,
            GROUP_CONCAT(DISTINCT IF(t.role='a',CONCAT(so.prijmeni,' ',so.jmeno),'') SEPARATOR '') as _darce
          FROM pobyt AS p
            -- JOIN uhrada AS u USING (id_pobyt)
            JOIN spolu AS s USING (id_pobyt)
            JOIN osoba AS o ON s.id_osoba=o.id_osoba
            JOIN osoba AS so ON so.id_osoba=s.id_osoba
            LEFT JOIN rodina AS r ON r.id_rodina=i0_rodina
            LEFT JOIN tvori AS t ON t.id_osoba=o.id_osoba AND t.id_rodina=i0_rodina
            JOIN akce AS a ON a.id_duakce=p.id_akce
            LEFT JOIN dar AS d ON d.id_osoba=s.id_osoba AND d.ukon='p' AND d.deleted=''
              AND YEAR(a.datum_do) BETWEEN YEAR(d.dat_od) AND YEAR(d.dat_do)
            LEFT JOIN dar AS dc ON dc.id_osoba=s.id_osoba AND dc.ukon='c' AND dc.deleted=''
              AND YEAR(a.datum_do)>=YEAR(dc.dat_od)
              AND (YEAR(a.datum_do) <= YEAR(dc.dat_do) OR !YEAR(dc.dat_do))
            -- JOIN _cis AS c ON c.druh='ms_akce_platba' AND c.data=zpusobplat
          WHERE p.id_akce='$akce' AND p.hnizdo=$tisk_hnizdo AND $cond AND p.funkce!=99
            -- AND p.funkce NOT IN (9,10,13,14,15,99) 
            -- AND id_pobyt IN (59318,59296,59317)
          GROUP BY id_pobyt
          ORDER BY $ord";
  $res= pdo_qry($qry);
  while ( $res && ($x= pdo_fetch_object($res)) ) {
//                                         debug($x,"hodnoty");
    $n++;
    $clmn[$n]= array();
    $DPH1= 0.15; $DPH1_koef= 0.1304;
    $DPH2= 0.21; $DPH2_koef= 0.1736;
    foreach($flds as $f) {
      $exp= ''; $val= 0;
      if ( substr($f,0,1)=='=' ) {
        //            ubyt.         strava        režie         sleva
        $predpis= $x->platba1 + $x->platba2 + $x->platba3 + $x->platba4;
        $platba= $x->uctem + $x->pokladnou;
        $vratka= 0 + $x->vratka;
        $preplatek= $platba > $predpis ? $platba - $predpis : 0;
        $nedoplatek= $platba < $predpis ? $predpis - $platba : 0;
//        $preplatek= $x->platba > $predpis ? $x->platba - $predpis : '';
//        $nedoplatek= $x->platba < $predpis ? $predpis - $x->platba : '';
        $naklad= $predpis - $x->platba4;
        switch ($f) {
        case '=pocetnoci':  $val= max(0,$x->pocetdnu);
                            break;
        case '=platit':     $val= $predpis;
                            $exp= "=[platba1,0]+[platba2,0]+[platba3,0]+[platba4,0]"; break;
        case '=preplatek':  $val= $preplatek ?: '';
                            $exp= "=IF([=pokladna,0]+[=uctem,0]>[=platit,0],[=pokladna,0]+[=uctem,0]-[=platit,0],0)"; break;
        case '=nedoplatek': $val= $nedoplatek ?: '';
                            $exp= "=IF([=zaplaceno,0]<[=platit,0],[=platit,0]-[=zaplaceno,0],0)"; break;
        case '=uctem':      $val= 0+$x->uctem; break;
//        case '=uctem':      $val= $x->pokladnou ? '' : 0+$x->platba; break;
//        case '=datucet':    $val= $x->pokladnou ? '' : $x->datplatby; break;
        case '=pokladna':   $val= 0+$x->pokladnou; break;
//        case '=datpokl':    $val= $x->pokladnou ? $x->datplatby : ''; break;
        case '=cd':         $val= 100.00*$x->cd; break;
        // nedoplatek členského příspěvku činného člena
        case '=prispevky':  $val= ($x->_clenstvi && $x->prispevky!=100*$x->_clenstvi 
                                ? 100*$x->_clenstvi-$x->prispevky : '-'); break;
        case '=ubyt':       $val= round($x->platba1 - $x->platba1*$DPH1_koef);
                            $exp= "=[platba1,0]-[=ubytDPH,0]"; break;
        case '=ubytDPH':    $val= round($x->platba1*$DPH1_koef);
                            $exp= "=ROUND([platba1,0]*$DPH1_koef,0)"; break;
        case '=strava':     $val= round($x->platba2 - $x->platba2*$DPH2_koef);
                            $exp= "=[platba2,0]-[=stravaDPH,0]"; break;
        case '=stravaDPH':  $val= round($x->platba2*$DPH2_koef);
                            $exp= "=ROUND([platba2,0]*$DPH2_koef,0)"; break;
        case '=rezie':      $val= 0+$x->platba3;
                            $exp= "=[platba3,0]"; break;
        case '=vratka':     $val= $vratka; break;
        case '=zaplaceno':  $val= 0+$platba-$vratka;
                            $exp= "=[=uctem,0]+[=pokladna,0]-[=vratka,0]"; break;
//        case '=zaplaceno':  $val= 0+$x->platba;
//                            $exp= "=[=uctem,0]+[=pokladna,0]"; break;
        case '=dotace':     $val= -$x->platba4;
                            $exp= "=-[platba4,0]"; break;
        case '=nedopl':     $val= $nedoplatek;
                            $exp= "=IF([=zaplaceno,0]<[=platit,0],[=platit,0]-[=zaplaceno,0],0)"; break;
        case '=darce':      $val= $preplatek-$vratka ? "dar - {$x->_darce}" : ''; break;
        case '=dar':        $val= $preplatek-$vratka;
                            $exp= "=IF([=zaplaceno,0]>[=platit,0],[=zaplaceno,0]-[=platit,0],0)"; break;
        case '=naklad':     $val= $naklad;
                            $exp= "=[=platit,0]-[platba4,0]"; break;
        case '=jmena':      $val= $x->_jm; break;
        default:            $val= '???'; break;
        }
        $clmn[$n][$f]= $val;
        if ( $exp ) $expr[$n][$f]= $exp;
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
//                                         debug($clmn,"sestava pro $akce,$typ,$fld,$cnd");
//                                         debug($expr,"vzorce pro $akce,$typ,$fld,$cnd");
//                                         debug($suma,"sumy pro $akce B");
  // zobrazení tabulkou
  $tab= '';
  $ths= '';
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
    $result->html= "<div class='stat'><table class='stat'><tr>$ths</tr>$sum$tab</table></div>";
    $result->html.= "</br>";
    $result->href= $href;
  }
  return $result;
}
# ------------------------------------------------------------------------------ akce2 pdf_stravenky
# generování štítků se stravenkami pro rodinu účastníka a pro pečouny do PDF
# pomocí tisk2_sestava se do objektu $x->tab vygeneruje pole s elementy pro tisk stravenky
function akce2_pdf_stravenky($akce,$par,$report_json,$hnizdo) {  trace();
  $res_all= (object)array('_error'=>0);
  $res_all->html= "<br>Stravenky jsou v souborech: ";
  // získání dat
  $res_vse= tisk2_sestava($akce,$par,'$title','$vypis',true,$hnizdo);
  foreach ($res_vse->res as $x) {
//                                                         if ( $x->dieta != '_bl' ) continue;
    $x->nazev= $x->nazev_diety=='normální' ? '' : $x->nazev_diety;
    $res= akce2_pdf_stravenky_dieta($x,$par,$report_json,$hnizdo);
    if ( $res->_error )
      fce_warning("{$x->nazev_diety} - {$res->_error}");
    else
      $res_all->html.= " {$res->href} - strava {$x->nazev_diety}, ";
  }
  return $res_all;
}
# ------------------------------------------------------------------------ akce2 pdf_stravenky_dieta
# generování štítků se stravenkami pro rodinu účastníka a pro pečouny do PDF
# pomocí tisk2_sestava se do objektu $x->tab vygeneruje pole s elementy pro tisk stravenky
function akce2_pdf_stravenky_dieta($x,$par,$report_json,$hnizdo) {  trace();
//                                                 debug($x,"akce2_pdf_stravenky_dieta");
// function akce2_pdf_stravenky_dieta($akce,$par,$report_json) {  trace();
  global $ezer_path_docs, $EZER, $USER;
  $result= (object)array('_error'=>0);
  $html= '';
  // získání dat
//   $x= tisk2_sestava($akce,$par,$title,$vypis,true);
//  $org= $USER->org==1 ? "YMCA Setkání" : "YMCA Familia"; // moc dlouhé
  $org= "YMCA";
  if (isset($x->akce->hnizda) && $hnizdo>0) {
    $hnizda= explode(',',$x->akce->hnizda);
    $header= "$org, {$hnizda[$hnizdo-1]} {$x->akce->rok}";
  }
  else {
    $misto= isset($x->akce->misto) ? $x->akce->misto : '';
    $rok= isset($x->akce->rok) ? $x->akce->rok : '';
    $header= "$org, $misto $rok";
  }
  $sob= array('s'=>'snídaně','o'=>'oběd','v'=>'večeře');
  $cp=  array('c'=>'1','p'=>'1/2');
  // projdi vygenerované záznamy
  $n= 0;
  $parss= array();
  foreach ( $x->tab as $jmeno=>$dny ) {
    // zjistíme, zda nějaké stravenky má - pokud ne, řádek netiskneme
    $ma= 0;
    foreach ( $dny as $den=>$jidla ) {
      foreach ( $jidla as $jidlo=>$porce ) {
        foreach ( $porce as $velikost=>$pocet ) {
          $ma+= $pocet;
        }
      }
    }
    if ( !$ma ) continue;
    // vynechání prázdných míst, aby jméno bylo v prvním sloupci ze 4
    $k= 4*ceil($n/4)-$n;
    for ($i= 0; $i<$k; $i++) {
      $parss[$n]= (object)array();
      $parss[$n]->header= $parss[$n]->line1= $parss[$n]->line2= $parss[$n]->line3= '';
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
    $parss[$n]->line3= '';
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
              $parss[$n]->line3= '';
              $parss[$n]->rect= $parss[$n]->ram= $parss[$n]->end= '';
              $n++;
            }
            // text stravenky na jedno jídlo
            $jid= $sob[$jidlo];
            $parss[$n]= (object)array();
            $parss[$n]->header= $header;
            $parss[$n]->line1= "$den";
            $parss[$n]->line2= "<b>$jid</b>";
            $parss[$n]->line3= "<small>{$x->nazev}</small>";
            if ( $velikost=='c' 
              || $jid=='snídaně' && isset($par->snidane) && $par->snidane=='c' ) {
              // celá porce
              $parss[$n]->ram= '<img src="db2/img/stravenky-rastr-2.png"'
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
    $parss[$n]->line3= " ";
    $parss[$n]->rect= $parss[$n]->ram= '';
    $parss[$n]->end= ' ';
    $n++;
  }
  // předání k tisku
//                                         debug($parss,"akce_pdf_stravenky");
//                                         debug($report_json,"report");
//                                         return $result;
  $fname= "stravenky{$x->dieta}_".date("Ymd_Hi");
  $fpath= "$ezer_path_docs/$fname.pdf";
  $err= dop_rep_ids($report_json,$parss,$fpath);
  if ( $err )
    $result->_error= $err;
  else
    $result->href= "<a href='docs/$fname.pdf' target='pdf'>PDF{$x->dieta}</a>";
  return $result;
}
# ----------------------------------------------------------------------------- akce2 pdf_stravenky0
# generování stránky stravenek pro ruční vyplnění do PDF
function akce2_pdf_stravenky0($akce,$par,$report_json) {  trace();
  global $ezer_path_docs, $EZER, $USER;
  $result= (object)array('_error'=>0);
  $html= '';
  // získání dat o akci
  $qa="SELECT nazev, YEAR(datum_od) AS akce_rok, misto
       FROM akce WHERE id_duakce='$akce' ";
  $ra= pdo_qry($qa);
  $a= pdo_fetch_object($ra);
  $org= $USER->org==1 ? "YMCA Setkání" : "YMCA Familia";
  $header= "$org, {$a->misto} {$a->akce_rok}";
  // projdi vygenerované záznamy
  $n= 0;
  $parss= array();
  $pocet= 4*12;
  for ($i= 1; $i<=$pocet; $i++) {
    // text stravenky na jedno jídlo
    $parss[$n]= (object)array();
    $parss[$n]->header= $header;
    $parss[$n]->line1= ""; //$den";
    $parss[$n]->line2= "";
    $parss[$n]->line3= "";
    $parss[$n]->rect=  "";
    $parss[$n]->end= '';
    $parss[$n]->ram= '<img src="db2/img/stravenky-rastr-2.png" style="width:48mm;height:23mm" border="0" />';
    $n++;
  }
  for ($i= 1; $i<=$pocet; $i++) {
    // text stravenky na jedno jídlo
    $parss[$n]= (object)array();
    $parss[$n]->header= $header;
    $parss[$n]->line1= ""; //$den";
    $parss[$n]->line2= "";
    $parss[$n]->line3= "";
    $parss[$n]->rect=  "<b>1/2</b>";
    $parss[$n]->end= '';
    $parss[$n]->ram= ' ';
    $n++;
  }
  // předání k tisku
//                                         debug($parss,"akce2_pdf_stravenky0");
  $fname= 'stravenky_'.date("Ymd_Hi");
  $fpath= "$ezer_path_docs/$fname.pdf";
//                                         return $result;
  $err= dop_rep_ids($report_json,$parss,$fpath);
  $result->html= $err ? $err
    : " Výpis byl vygenerován ve formátu <a href='docs/$fname.pdf' target='pdf'>PDF</a>.";
  return $result;
}




# ======================================================================================> GROUPS
# ----------------------------------------------------------------------------------------- grp_read
# par.file = stáhnutý soubor
function grp_read($par) {  trace(); //debug($par);
  mb_internal_encoding("UTF-8");
  $html= $msg= '';
  $r= " align='right'";
  $y= " style='background-color:yellow'";
  $sav= false;
  $max= 4;
  $max= 999999;
  $mesice= array('ledna'=>1,'února'=>2,'března'=>3,'dubna'=>4,'května'=>5,'června'=>6,
                 'července'=>7,'srpna'=>8,'září'=>9,'října'=>10,'listopadu=>11','prosince'=>12);

  switch ($par->meth) {
  # -------------------------------------------------------------------------------------- INFORMACE
  case 'ana':
    list($zprav,$posledni,$prvni)= select("COUNT(*),MAX(datum),MIN(datum)","gg_mbox","1");
    $prvni= sql_date1($prvni);
    $posledni= sql_date1($posledni);
    $html.= "Následné analýzy platí pro $zprav zpráv konference chlapi-iniciace,
             napsaných v období $prvni až $posledni";
    break;

  # ----------------------------------------------------------------------------------- ANA: ANALÝZY
  #
  case 'ana_unknown':   # ------------------------------------------------------- ANA: neznámé
    $html= "<table class='stat'><tr><th>email</th><th>aktivita</th><th>od</th><th>do</th></tr>";
    $rh= pdo_qry("
      SELECT email,zprav,YEAR(prvni),YEAR(posledni)
      FROM gg_osoba WHERE id_osoba=0
      ORDER BY zprav DESC
    ");
    while ( $rh && (list($email,$aktivita,$prvni,$posledni)= pdo_fetch_row($rh)) ) {
      $html.= "<tr><td>$email</td><td>$aktivita</td><td>$prvni</td><td>$posledni</td></tr>";
    }
    $html.= "</table>";
    break;

  case 'ana_activity':  # ------------------------------------------------------- ANA: aktivní
    $mez= 0;
    $html= "<table class='stat'><tr><th>email</th><th>účastník</th><th>zpráv</th><th>iniciace</th>
            <th>od</th><th>do</th></tr>";
    $rh= pdo_qry("
      SELECT LEFT(GROUP_CONCAT(g.email),100),SUM(zprav) AS _zprav,iniciace,
        MIN(YEAR(prvni)),MAX(YEAR(posledni)),jmeno,prijmeni
      FROM gg_osoba AS g
      LEFT JOIN osoba AS o USING (id_osoba)
      GROUP BY IF(id_osoba,id_osoba,g.email) HAVING _zprav>$mez
      ORDER BY _zprav DESC
    ");
    while ( $rh
      && (list($email,$aktivita,$mrop,$prvni,$posledni,$jmeno,$prijmeni)= pdo_fetch_row($rh)) ) {
      $style1= !$mrop
            ? " style='background-color:yellow'" : '';
      $style2= $mrop<2007 && $prvni>2007 || $mrop>=2007 && $prvni!=$mrop
            ? " style='background-color:yellow'" : '';
      $html.= "<tr><td>$jmeno $prijmeni</td><td$style1>$email</td><td>$aktivita</td><td>$mrop</td>
               <td$style2>$prvni</td><td>$posledni</td></tr>";
    }
    $html.= "</table>";
    break;

  case 'ana_vlakna':    # ------------------------------------------------------- ANA: vlákna
    $html= "<table class='stat'><tr><th>rok</th><th>příspěvků</th><th>diskutujících</th>
            <th>předmět</th></tr>";
    $rh= pdo_qry("
      SELECT COUNT(*) AS _pocet,COUNT(DISTINCT email),MIN(YEAR(datum)),MAX(YEAR(datum)),
        LEFT(nazev,50),COUNT(DISTINCT root)
      FROM gg_mbox
      -- WHERE root!=0
      GROUP BY nazev -- root
      ORDER BY _pocet DESC
    ");
    while ( $rh && (list($delka,$lidi,$od,$do,$nazev,$roots)= pdo_fetch_row($rh)) ) {
      $roky= $od.($do!=$od? "-$do" : '');
      $flame= $do-$od<2 && $delka>10 && $delka>1.8*$lidi && $roots==1 ? $y : '';
      $html.= "<tr><td>$roky</td><td>$delka</td><td$flame>$lidi</td><td>$nazev</td>
        <td>$roots</td></tr>";
    }
    $html.= "</table>";
    break;

  case 'ana_roky':      # ------------------------------------------------------- ANA: roky
    $html= "<table class='stat'><tr><th>rok</th><th>diskutujících</th><th>příspěvků</th>
      <th>vláken</th><th>nejdelší</th><th>název vlákna</th><th>vláken II</th></tr>";
    $rh= pdo_qry("
      SELECT YEAR(datum) AS _rok,COUNT(*) AS _pocet,SUM(IF(back=0,1,0)),COUNT(DISTINCT email),
        MAX(CONCAT(LPAD(reakci,4,'0'),nazev)),COUNT(DISTINCT nazev)
      FROM gg_mbox
      GROUP BY _rok
      ORDER BY _rok DESC
    ");
    while ( $rh && (list($rok,$prisp,$vlakna,$lidi,$nazev,$nazvu)= pdo_fetch_row($rh)) ) {
      $max= substr($nazev,0,4)+0;
      $nazev= substr($nazev,4);
      $html.= "<tr><td>$rok</td><td$r>$lidi</td><td$r>$prisp</td><td$r>$vlakna</td>
        <td$r>$max</td><td>$nazev</td><td>$nazvu</td></tr>";
    }
    $html.= "</table>";
    break;

  case 'ana_mesice':    # ------------------------------------------------------- ANA: měsíce
    $old= 0;
    $html= "<table class='stat'><tr><th>rok</th><th>diskutujících</th><th>příspěvků</th><th>vláken</th>
      <th>nejdelší</th><th>název vlákna</th></tr>";
    $rh= pdo_qry("
      SELECT LEFT(datum,7) AS _rok,COUNT(*) AS _pocet,SUM(IF(back=0,1,0)),COUNT(DISTINCT email),
        MAX(CONCAT(LPAD(reakci,4,'0'),nazev))
      FROM gg_mbox
      GROUP BY _rok
      ORDER BY _rok DESC
    ");
    while ( $rh && (list($mesic,$prisp,$vlakna,$lidi,$nazev)= pdo_fetch_row($rh)) ) {
      $rok= substr($mesic,0,4);
      $mesic= substr($mesic,5);
      if ( $rok==$old ) $cas= $mesic;
      else { $old= $rok; $cas= "$rok/$mesic"; }
      $max= substr($nazev,0,4)+0;
      $nazev= substr($nazev,4);
      $html.= "<tr><td>$cas</td><td$r>$lidi</td><td$r>$prisp</td><td$r>$vlakna</td>
        <td$r>$max</td><td>$nazev</td></tr>";
    }
    $html.= "</table>";
    break;

//   case 'ana_lidi_y':    # ------------------------------------------------------- ANA: lidi
//     $html= "<table class='stat'><tr><th>rok</th><th>aktivních účastníků</th></tr>";
//     $rh= pdo_qry("
//       SELECT COUNT(*) AS _pocet,YEAR(prvni) AS _rocnik
//       FROM gg_osoba
//       GROUP BY _rocnik
//       ORDER BY _rocnik DESC
//     ");
//     while ( $rh && (list($_pocet,$_rocnik)= pdo_fetch_row($rh)) ) {
//       $html.= "<tr><td>$_rocnik</td><td>$_pocet</td></tr>";
//     }
//     $html.= "</table>";
//     break;
//
//   case 'ana_prispevky_y': # ----------------------------------------------------- ANA: příspěvky
//     $html= "<table class='stat'><tr><th>rok</th><th>příspěvků</th></tr>";
//     $rh= pdo_qry("
//       SELECT COUNT(*) AS _pocet,YEAR(datum) AS _rocnik
//       FROM gg_mbox
//       GROUP BY _rocnik
//       ORDER BY _rocnik DESC
//     ");
//     while ( $rh && (list($_pocet,$_rocnik)= pdo_fetch_row($rh)) ) {
//       $html.= "<tr><td>$_rocnik</td><td>$_pocet</td></tr>";
//     }
//     $html.= "</table>";
//     break;

  # ------------------------------------------------------------------------------------ ZÍSKÁNÍ DAT

  case 'upd_copy': # ------------------------------------------------------------ UPD: kopie gg_iOSOBA
//     // zjednodušená kopie z Answer
//     query("TRUNCATE TABLE gg_iosoba");
//     // extrakce osoby, osobního mailu a gmailu
//     query("INSERT INTO gg_iosoba (id_osoba,jmeno,prijmeni,email,iniciace)
//            SELECT id_osoba,jmeno,prijmeni,CONCAT(IF(kontakt,email,''),',',gmail),iniciace
//            FROM osoba WHERE iniciace!=0 AND deleted=''");
    // spojovací rekordy mezi maily a osoby
    query("TRUNCATE TABLE gg_osoba");
    query("INSERT INTO gg_osoba (email,zprav,prvni,posledni)
           SELECT LCASE(email),COUNT(*),MIN(datum),MAX(datum) FROM gg_mbox
           WHERE email!='chlapi-iniciace+noreply@googlegroups.com' GROUP BY email");
    // vytvoření tabulky mailu, gmailu, případně rodinného mailu --> id_osoba
    $id= array();
    $rh= pdo_qry("
      SELECT id_osoba,kontakt,IFNULL(LCASE(emaily),''),LCASE(email),LCASE(gmail)
      FROM osoba AS o
      LEFT JOIN tvori AS t USING (id_osoba)
      LEFT JOIN rodina AS r USING (id_rodina)
      WHERE iniciace!=0 AND o.deleted='' AND IFNULL(r.deleted='',1)
    ");
    while ( $rh && (list($ido,$kontakt,$emaily,$email,$gmail)= pdo_fetch_row($rh)) ) {
      if ( $gmail )
        foreach(explode(',',$gmail) as $e) if ( !isset($id[$e]) ) $id[$e]= $ido;
      if ( $email )
        foreach(explode(',',$email) as $e) if ( !isset($id[$e]) ) $id[$e]= $ido;
      if ( !$kontakt && $emaily )
        foreach(explode(',',$emaily) as $e) if ( !isset($id[$e]) ) $id[$e]= $ido;
    }
//                                                 debug($id);
    $rh= pdo_qry("SELECT email FROM gg_osoba");
    while ( $rh && (list($email)= pdo_fetch_row($rh)) ) {
      if ( isset($id[$email]) ) {
        query("UPDATE gg_osoba SET id_osoba={$id[$email]} WHERE email='{$email}'");
      }
    }
//     query("UPDATE gg_osoba AS e JOIN gg_iosoba AS i ON e.email=i.email SET e.id_osoba=i.id_osoba");
//     query("UPDATE gg_osoba AS e JOIN gg_iosoba AS i ON i.email RLIKE e.email SET e.id_osoba=i.id_osoba
//            WHERE e.id_osoba=0");
    break;

  case 'imap_db': # ------------------------------------------------------------- IMAP: uložit do db
    // vyprázdnit tabulku
    query("TRUNCATE gg_mbox");
    $sav= true;

  case 'imap': # ---------------------------------------------------------------- IMAP: test
    if ( $par->serv=='proglas' ) {
      $authhost= '{imap.proglas.cz:143}'.$par->mbox;
      $user="smidek@proglas.cz";
      $pass="************";
    }
    else { // gmail
      $authhost= '{imap.gmail.com:993/imap/ssl}'.$par->mbox;
      $user="***********";
      $pass="**********";
    }
    $mails= array();
    $mbox= @imap_open($authhost,$user,$pass);
    if ( !$mbox) { $msg.= print_r(imap_errors()); break; }
    $obj= imap_check($mbox);
//                                                 debug($obj);
    // zpracování vlákna
    $tree= imap_thread($mbox,SE_UID);
//                                                 debug($tree,count($tree));
    $num= $child= $parent= $next= $prev= $is= array();
    foreach ($tree as $key => $uid) {
      list($k,$type) = explode('.',$key);
      switch($type){
      case 'num':
        $is[$uid]= $k; $num[$k]= $uid; $mails[$uid]= (object)array();
        break;
      }
    }
    foreach ($tree as $key => $i) {
      list($k,$type) = explode('.',$key);
      switch($type){
      case 'next':   if ( $i ) { $child[$k]= $i; $parent[$i]= $k; } break;
      case 'branch': if ( $i ) { $next[$k]= $i; $prev[$i]= $k; } break;
      }
    }
    // najdeme kořen
    $first= -1;
    foreach ($num as $i=>$uid) {
      if ( !$parent[$i] && !$prev[$i] ) {
        $first= $i;
        break;
      }
    }
//                                                 display("first=$first");
    // poskládáme strukturu
    $root= $first;
    while ( $root>=0 ) {
      $mails[$num[$root]]->root= $num[$root];
      $root= isset($next[$root]) ? $next[$root] : -1;
    }
    foreach ($num as $root => $uid) {
      $family= array();
      $i= $root;
      $otec= -1;
      while ( $i>=0 && !isset($mails[$num[$i]]->root) ) {
        $otec= (isset($parent[$i]) ? $parent[$i] : -1);
        $k= isset($prev[$i]) ? $prev[$i] : $otec;
//                                                 display("$i - $k");
        if ( $k>=0 ) {
          $mails[$num[$i]]->back= $num[$k];
          $family[]= $i;
        }
        $i= $k;
      }
//                                                 debug($family,"root=$root");
      if ( $otec>=0 ) {
        foreach ($family as $i) {
          $mails[$num[$i]]->xroot= $num[$otec];
        }
      }
    }
    // spočítání odkazů
    foreach ($mails as $uid=>$mail) {
      if ( isset($mail->xroot) ) {
        $mails[$mail->xroot]->zprav++;
      }
    }
//                                                 debug($num,'uid ');
//                                                 debug($child,'child');
//                                                 debug($parent,'parent');
//                                                 debug($next,'next sibling');
//                                                 debug($prev,'prev sibling');
//                                                 debug($mails,'mails');


    // výběr
    //$cond= 'FROM "martin@smidek.eu" SINCE "10-Apr-2016"';
    $cond= 'ALL';
    //$cond= 'SINCE "15-Apr-2016"';
    $idms= imap_search($mbox,$cond,SE_UID);
//                                                 debug($idms);
//

    foreach ($idms as $idm) {
      $im= imap_msgno($mbox,$idm);
      $overview= imap_fetch_overview($mbox,$idm,FT_UID);
      // získání data
      $d= $overview[0]->date;
      $datum= strlen($d)==30 ? substr($d,5) : $d;
//                                                 display($datum);
      $utime= strtotime($datum);
      $datum= date("d.m.Y H:i:s",$utime);
      $ymdhis= date("Y-m-d H:i:s",$utime);
      // očištění from
      preg_match('/.*\<(.*)>/',$overview[0]->from,$m);
      $from= $m[1] ?: $overview[0]->from;
      // očištění subject
      $subj= mb_decode_mimeheader($overview[0]->subject);
      if ( strpos($subj,'=')!==false ) {
        $subj= "=?iso-8859-2?Q?$subj";
        $subj= mb_decode_mimeheader($subj);
      }
      $subj= str_replace("_"," ",$subj);
      $subj= preg_replace("/Re\:|re\:|RE\:|Fwd\:|fwd\:|FWD\:/i", '', $subj);
      $subj= preg_replace("/\[chlapi-iniciace]|\[chlapi-vsichni]|\[chlapi-informace]/i", '', $subj);
      $subj= trim($subj,"- \t\n\r\0\x0B");


//                                                 display($is[$idm]."/$idm: $datum from $from $subj");
      // zapiš do mails
      if ( isset($mails[$idm]->root) ) {
        $mails[$idm]->subj= $subj;
      }
      $mails[$idm]->date= $ymdhis;
      $mails[$idm]->from= $from;
      if ( !$sav ) {
//                                                debug($overview);
      }
    }
    if ( !$sav ) {
//                                                debug($mails,'mails');
    }
    if ( $sav ) {
      foreach ($mails as $uid=>$mail) {
        if ( $uid && $mail!='chlapi-iniciace+noreply@googlegroups.com' ) {
          // uložení do db
          $root=  isset($mail->root) ? $mail->root : $mail->xroot;
          $zprav= isset($mail->zprav) ? $mail->zprav : 0;
          $back=  isset($mail->back) ? $mail->back : 0;
          $subj=  isset($mail->subj) ? $mail->subj : '';
          query("INSERT INTO gg_mbox (uid,root,back,reakci,datum,email,nazev)
            VALUES ($uid,$root,$back,$zprav,'$mail->date','$mail->from','$subj')");
        }
      }
    }
    // konec
    imap_close($mbox);
    break;

  }
end:
  return $msg ? "ERROR: $msg<hr>$html" : $html;
}

