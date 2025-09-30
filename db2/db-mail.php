<?php # (c) 2008-2025 Martin Smidek <martin@smidek.eu>
define("EZER_VERSION","3.3");  

# =====================================================================================> . potvrzení
# ---------------------------------------------------------------------------------------- ucet_potv
# přehled podle tabulky 'prijate dary' na intranetu
function ucet_potv($par,$access) { trace();
  $html= $href= '';
  $mez_daru= 500;       // pod tuto částku neposíláme potvrzení - je třeba opravit v mailist.sexpr
  $darce= array();
  if ( $access!=1  ) {
    $html.= "POZOR: přehled darů na GoogleDisk je (zatím) pouze pro Setkání";
    goto end;
  }
  $max= 9999;
//   $max= 30;
  $rok= date('Y')+$par->rok;
  $let18= date('Y')-18;

//  global $json; 
//  $key= "1KG943OiuVeb_S7FuCZdhPWF3fNu44hBiR2DZIsp3Pok";         // prijate_dary
  $key= "1mwdOhCV3LLAhwbUAl2fxzwjselZjs1lWqMQXJRxLpF8";         // prijate_dary_pro_potvrzeni
  $prefix= "google.visualization.Query.setResponse(";           // přefix json objektu
  $sheet= $rok;
//   $sheet= "Test$rok";
  $prijate_dary= "<a target='xls' href='https://drive.google.com/open?id=$key'>prijate_dary_pro_potvrzeni</a>";
  $url= "https://docs.google.com/spreadsheets/d/$key/gviz/tq?tqx=out:json&sheet=$sheet";
                                        display($url);
  $x= file_get_contents($url);
//                                         display($x);
  $xi= strpos($x,$prefix);
  $xl= strlen($prefix);
//                                         display("xi=$xi,$xl");
  $x= substr(substr($x,$xi+$xl),0,-2);
//                                         display($x);
  $goo= json_decode($x)->table;
//                                         debug($goo,$sheet);
  // výběr záznamů o darech
  $tab= $clmn= array();
  $prblm1= $prblm2= '';
  $jmeno_prvni= array();  // ke klíči $prijmeni$jmeno dá řádek s prvním výskytem
  $jmeno_id= array();     // ke klíči $prijmeni$jmeno dá id nebo 0
  $nalezeno= 0;
  $radku= count($goo->rows);
  //$radku= 300;
  for ($i= 1; $i<$radku; $i++) {
    $i1= $i+1;
    $grow= $goo->rows[$i]->c;
    $row= (object)array();
    $datum=     $row->a= $grow[0]->v;
    $dar_jmeno= $row->b= $grow[1]->v;
    $castka=    $row->c= $grow[2]->v;
    $ref=       $row->d= $grow[3]->v;
    $auto=      $row->e= $grow[4]->v;
    $manual=    $row->f= $grow[5]->v=='0' ? 'O' : $grow[5]->v;
    $oprava=    $row->g= $grow[6]->v;
    $filler=    $row->h= $grow[7]->v;
    $pozn=      $row->i= $grow[8]->v;
//                                         debug($goo->rows[$i],"dar=$dar_jmeno");
//                                         debug($row,"dar=$dar_jmeno");
    // transformace do $tab[]=$row
    $jmeno= substr($dar_jmeno,0,6)=='dar - ' ? substr($dar_jmeno,6) : 
      ( substr($dar_jmeno,0,4)=='dar ' ? substr($dar_jmeno,4) : $dar_jmeno);
    list($prijmeni,$jmeno)= explode(' ',$jmeno);
//                                         display("'$jmeno' '$prijmeni'");
    $opakovane= $jmeno_prvni["$prijmeni$jmeno"] ?: 0;
    if ( !$datum ) break;
    // zapiš opakujícímu se dárci odkaz na řádek s jeho prvním darem
    if ( !$opakovane ) {
      $jmeno_prvni["$prijmeni$jmeno"]= $i+1;
    }
    if ( !$ref && $opakovane ) {
//       $updatedCell= $google->service->updateCell($i,4,$opakovane,$google->sskey,$google->wskey);
      $row->d= $opakovane;
    }
    // doplnění intranetové tabulky a střádání darů do tabulky $darce
    $ido= 0;
    if ( !$auto && !$manual && !$opakovane ) {
      // pokusíme se nalézt dárce
      $idss= array();
      $ids= '';
      $qo= pdo_qry("
        SELECT id_osoba FROM osoba AS o
        WHERE jmeno='$jmeno' AND prijmeni='$prijmeni' AND deleted='' AND access&1
          AND IF(narozeni!='0000-00-00',YEAR(narozeni)<$let18,1)
      ");
      while ($qo && ($o= pdo_fetch_object($qo))) {
        $ido= $o->id_osoba;
        $idss[]= tisk2_ukaz_osobu($o->id_osoba);
      }
      if ( count($idss) ) {
        $ids= implode(', ',$idss);
//                                         display("$jmeno $prijmeni = $ids");
        // zápis do auto
//         $updatedCell= $google->service->updateCell($i,5,$ids,$google->sskey,$google->wskey);
        $row->e= $ids;
        if ( count($idss)==1 ) {
          $jmeno_id["$prijmeni$jmeno"]= $ids;
        }
        else {
          $ido= 0;
          $prblm1.= ($prblm1?"<br>":'')."$i1: $datum $prijmeni $jmeno $castka ($ids)";
        }
      }
      else {
        $prblm2.= ($prblm2?"<br>":'')."$i1: $datum $prijmeni $jmeno $castka";
      }
    }
    elseif ( strpos($auto,',') && !$manual && !$ref ) {
      $prblm1.= ($prblm1?"<br>":'')."$i1: $datum $prijmeni $jmeno $castka ($auto)";
    }
    elseif ( $manual ) {
      if ( $manual=='O' )
        $prblm3.=  ($prblm3?"<br>":'')."0 $dar_jmeno $castka";
      else {
        $jmeno_id["$prijmeni$jmeno"]= tisk2_ukaz_osobu($manual);
        $ido= $manual;
      }
    }
    elseif ( $auto && strpos($auto,',')===false ) {
      $jmeno_id["$prijmeni$jmeno"]= tisk2_ukaz_osobu($auto);
      $ido= $auto;
    }
    // střádání darů od jednoznačně určeného dárce
    $id= $jmeno_id["$prijmeni$jmeno"];
    if ( $id && $castka ) {
      if ( !isset($darce[$id]) ) {
        $darce[$id]= (object)array('data'=>array(),'castka'=>0,'jmeno'=>"$prijmeni $jmeno",'ido'=>$ido);
        $id1= tisk2_ukaz_osobu($ido);
        if ( $ido && $id!=$id1 ) fce_error("ucet_potv: chyba indexu $id!=$id1");
      }
      list($d,$m,$y)= preg_split("/[\/\.]/",$datum);
      $m= 0+$m; $d= 0+$d;
      $darce[$id]->data[]= "$d. $m.";
      $darce[$id]->castka+= $castka;
    }
    $clmn[]= $row;
  }
                                        ksort($jmeno_id); 
//                                        debug($jmeno_id,'jmeno_id');
//                                        debug($darce,'dárce');
//                                         debug($clmn,'clmn');
  // -------------------- vytvoření tabulky pro zobrazení a tisk
  $tab= (object)array(
    'tits'=>explode(',',"datum:10:d,dárce:20,částka,stejný jako ř.:7,ID auto:15,ID ručně,"
                      . "oprava,zapsán,účetnictví:17"),
    'flds'=>explode(',',"a,b,c,d,e,f,g,h,i"),
    'clmn'=>$clmn
  );
  $html.= sta2_table($tab->tits,$tab->flds,$clmn,0,2)->html;
  $html.= "<br><br>";
  $html.= sta2_excel_export("Dárci '$rok'",$tab)->html;
  $reseni= "<br><br>doplň v intranetovém sešitu <b>$prijate_dary</b> v listu <b>$rok</b> do sloupce <b>F</b>
            správné osobní číslo dárce (zjistí se v Evidenci), jen do prvního výskytu dárce<br><br>";
  // -------------------- přehledy
  $rucne= $malo= 0;
  $chibi= array();
  $n_clmn= count($clmn);
  foreach ($clmn as $i=>$row) if ( !$row->d ) {
    $rucne+= $row->f ? 1 : 0;
    if ( !$row->e && !$row->f ) {
      $chibi[]= $i+2;
    }
  }
  $n_jmeno_id= count($jmeno_id);
  $n_darce= count($darce);
  foreach ($darce as $d) {
    $malo+= $d->castka<$mez_daru ? 1 : 0;
  }
  $html.= "<h3>Přehled</h3>celkem je
    $n_clmn darů,
    $n_darce/$n_jmeno_id ($vic) dárců (z toho $rucne poznáno ručně),
    $malo dárců dalo méně jak $mez_daru";
  if ( count($chibi) ) $html.= "<h3>Chybně označené řádky</h3> ve sloupci D,E,F musí být aspoň
    jeden údaj - aspoň '0'. Týká se to řádků: ".implode(', ',$chibi);
  if ( $prblm1 ) $html.= "<h3>Nejednoznačná jména v rámci evidence YS</h3>$prblm1$reseni";
  if ( $prblm2 ) $html.= "<h3>Neznámá jména v rámci evidence YS</h3>$prblm2$reseni";
  if ( $prblm3 ) $html.= "<h3>Vynechaná potvrzení (později ručně napsaná)</h3>$prblm3";
  if ( !$prblm1 && !$prblm2 ) $html.= "<h3>Ok</h3>všichni dárci byli jednoznačně identifikováni :-)";

  // -------------------- zápis do tabulky dar, pokud se to chce
  if ( $druh= $par->save ) {
    // smazání záznamů o účetních darech
    query("DELETE FROM dar WHERE YEAR(dat_od)=$rok AND zpusob='u'");
    // zápis zjištěných darů
    $n= 0;
    foreach ($darce as $ido=>$dary) {
      $ido= $dary->ido;
      $data= implode(', ',$dary->data)." $rok";
      $pars= ezer_json_encode((object)array('data'=>$data));
      $oki= query("INSERT INTO dar (access,id_osoba,ukon,zpusob,castka,dat_od,note,pars)
        VALUES ($access,$ido,'d','u',{$dary->castka},'$rok-12-31','daňové potvrzení','$pars')");
      $n+= $oki ? pdo_affected_rows($oki) : 0;
    }
    $html.= "<br><br>vloženo $n dárců k potvrzování za rok $rok";
  }
  elseif ( $druh= $par->corr ) {
    // oprava záznamů o účetních darech
    $n1= $n2= $n3= $n4= 0;
    foreach ($darce as $id=>$dary) {
      $data= implode(', ',$dary->data)." $rok";
      $pars= ezer_json_encode((object)array('data'=>$data));
      // zjištění výše zaznamenaného daru
      $castka2= $dary->castka;
      list($id_dar,$castka1)= select("id_dar,castka","dar","id_osoba=$id AND ukon='d' AND zpusob='u'
        AND dat_od='$rok-12-31' AND note='daňové potvrzení'");
      if ( $castka2==$castka1 ) {
        $n1++;
      }
      elseif ( $id_dar && $castka2 >= $mez_daru ) {
        $pars= ezer_json_encode((object)array('data'=>$data,'bylo'=>$castka1));
                                        display("{$dary->jmeno} $castka1 - $castka2");
        $oku= query("UPDATE dar
          SET castka=$castka2, note='2.daňové potvrzení', pars='$pars'
          WHERE id_dar=$id_dar");
        $n2+= $oku ? pdo_affected_rows($oku) : 0;
      }
      elseif ( !$id_dar && $castka2 >= $mez_daru ) {
        $ido= $dary->ido;
        $oki= query("INSERT INTO dar (id_osoba,ukon,zpusob,castka,dat_od,note,pars)
          VALUES ($ido,'d','u',$castka2,'$rok-12-31','2.daňové potvrzení','$pars')");
        $n4+= $oki ? pdo_affected_rows($oki) : 0;
      }
      else {
        $n3++;
      }
    }
    $html.= "<br><br>dárců za rok $rok: přidáno $n4, opraveno $n2, bez opravy $n1, $n3 pod $mez_daru Kč";
  }
end:
  return (object)array('html'=>$html,'href'=>$href);
}
# --------------------------------------------------------------------------------- mail2 mai_export
# vygeneruje tabulku adresátů (id_osoba, prijmeni jmeno, email)) pro Excel a vrátí na ni odkaz
function mail2_mai_export($idd) {  trace();
  $tab= (object)array(
      'html'=>'',
      'tits' => array('příjmení:15','jméno:10','email:30','id:6'),
      'flds' => array('prijmeni','jmeno','email','id'),
      'clmn' => array()
      );
  $nazev= '';
  $rs= pdo_qry("
    SELECT id_clen,prijmeni,jmeno,m.email,d.nazev
    FROM mail AS m
    JOIN dopis AS d USING (id_dopis)
    JOIN osoba AS o ON o.id_osoba=m.id_clen
    WHERE id_dopis=$idd AND stav=4
    ORDER BY prijmeni,jmeno
    ");
  while ( $rs && (list($idc,$prijmeni,$jmeno,$email,$n)= pdo_fetch_array($rs)) ) {
    if ( !$nazev ) $nazev= $n;
    $tab->clmn[]= array('prijmeni'=>$prijmeni,'jmeno'=>$jmeno,'email'=>$email,'id'=>$idc);
  }
  $ret= sta2_excel_export("Seznam adresátů mailu $nazev",$tab);
  return $ret->html;
}
# ------------------------------------------------------------------------------------ mail2 lst_try
# mode=0 -- spustit a ukázat dotaz a také výsledek
# mode=1 -- zobrazit argument jako html
function mail2_lst_try($gq,$mode=0) { trace();
//   global $USER;                                         // debug($USER);
//   $access= $USER->access;
  $html= $del= '';
  if ( !$gq ) {
    $html= "mail-list nebyl uložen";
    goto end;
  }
  switch ($mode) {
  case 0:
    $n= $nw= $nm= $nx= $nu= $nr= 0;
    $gq= str_replace('&gt;','>',$gq);
    $gq= str_replace('&lt;','<',$gq);
    // ZRUŠENO: doplnění práv uživatele
    // $gq= str_replace('[HAVING_ACCESS]',"HAVING o.access&$access",$gq);
    $gr= @pdo_qry($gq);
    if ( !$gr ) {
      $html= pdo_error()."<hr>".nl2br($gq);
      goto end;
    }
    else while ( $gr && ($g= pdo_fetch_object($gr)) ) {
      $n++;
      $name= str_replace(' ','&nbsp;',$g->_name);
      if ( !$g->_email ) {
        $nw++;
        $name= "<span style='color:darkred'>$name</span>";
      }
      if ( $g->_umrti ) {
        $nu++;
        $name= "<span style='background:silver'>+ $name</span>";
      }
      if ( $g->rozvod ) {
        $nr++;
        $name= "<span style='background:yellow'>x $name</span>";
      }
      if ( $g->nomail ) {
        $nm++;
        $name= "<span style='background-color:orange'>$name</span>";
      }
      if ( $g->_email[0]=='*' ) {
        // vyřazený mail
        $nx++;
        $name= "<strike><b>$name</b></strike>";
      }
      $html.= "$del$name";
      $del= ', ';
    }
    $warn= $nw+$nm+$nx+$nu+$nr ? " (" : '';
    $warn.= $nw ? "$nw <span style='color:darkred'>nemá email</span> ani rodinný" : '';
    $warn.= $nw && $nm ? ", " : '';
    $warn.= $nm ? "$nm <span style='background-color:yellow'>nechce hromadné</span> informace
      - budou vyňati z mail-listu" : '';
    $warn.= ($nw||$nm) && $nx ? ", " : '';
    $warn.= $nx ? "$nx má <strike>zneplatněný email</strike>" : '';
    $warn.= ($nw||$nm||$nx) && $nu ? ", " : '';
    $warn.= $nu ? "$nu  <strike>zemřelo</strike>" : '';
    $warn.= ($nw||$nm||$nx||$nu) && $nr ? ", " : '';
    $warn.= $nr ? "$nr  <strike>rozvedeno</strike>" : '';
    $warn.= $nw+$nm+$nx+$nu+$nr ? ")" : '';
    $html= "<b>Nalezeno $n adresátů$warn:</b><br>$html";
    break;
  case 1:
    $html= nl2br($gq);
    break;
  }
end:
  return $html;
}
/** ========================================================================================> DOPISY */
# =======================================================================================> . šablony
# ------------------------------------------------------------------------------------- dop sab_text
# přečtení běžného dopisu daného typu
function dop_sab_text($dopis) { //trace();
  $d= null;
  try {
    $qry= "SELECT id_dopis,obsah FROM dopis WHERE typ='$dopis' AND id_davka=1 ";
    $res= pdo_qry($qry,1,null,1);
    $d= pdo_fetch_object($res);
  }
  catch (Exception $e) { display($e); fce_error("dop_sab_text: průběžný dopis '$dopis' nebyl nalezen"); }
  return $d;
}
# ------------------------------------------------------------------------------------- dop sab_cast
# přečtení části šablony
function dop_sab_cast($druh,$cast) { //trace();
  $d= null;
  try {
    $qry= "SELECT id_dopis_cast,obsah FROM dopis_cast WHERE druh='$druh' AND name='$cast' ";
    $res= pdo_qry($qry,1,null,1);
    $d= pdo_fetch_object($res);
  }
  catch (Exception $e) { display($e); fce_error("dop_sab_cast: část '$cast' sablony nebyla nalezena"); }
  return $d;
}
# ----------------------------------------------------------------------------------- dop sab_nahled
# ukázka šablony
function dop_sab_nahled($k3) { trace();
  global $ezer_path_docs;
  $html= '';
  $fname= "sablona.pdf";
  $f_abs= "$ezer_path_docs/$fname";
  $f_rel= "docs/$fname";
//   ezer_connect('ezer_ys');
  $html= tc_sablona($f_abs,'','D');                 // jen části bez označení v dopis_cast.pro
  $date= @filemtime($f_abs);
  $href= "<a target='dopis' href='$f_rel'>$fname</a>";
  $html.= "Byl vygenerován PDF soubor: $href (verze ze ".date('d.m.Y H:i',$date).")";
  $html.= "<br><br>Jméno za 'vyřizuje' se bere z osobního nastavení přihlášeného uživatele.";
  return $html;
}
# ========================================================================================> . emaily
# jednotlivé maily posílané v sadách příložitostně skupinám
#   DOPIS(id_dopis=key,id_davka=1,druh='@',nazev=předmět,datum=datum,obsah=obsah,komu=komu(číselník),
#         nw=min(MAIL.stav,nh=max(MAIL.stav)})
#   MAIL(id_mail=key,id_davka=1,id_dopis=DOPIS.id_dopis,znacka='@',id_clen=clen,email=adresa,
#         stav={0:nový,3:rozesílaný,4:ok,5:chyba})
# formát zápisu dotazu v číselníku viz fce mail2_mai_qry
# ---------------------------------------------------------------------------------- mail2 mai_potvr
# vygeneruje PDF s daňovým potvrzením s výsledkem
# ret->fname - jméno vygenerovaného PDF souboru
# ret->href  - odkaz na soubor
# ret->fpath - úplná lokální cesta k souboru
# ret->log   - log
function mail2_mai_potvr($druh,$o,$rok) {  //trace();
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
  $id_osoba= $o->_id;
                                        display("mail2_mai_potvr $id_osoba {$o->castka}");
  $prijmeni= $o->prijmeni;
  $jmeno= $o->jmeno;
  $sex= $o->sex;
  $ulice= $o->_ulice;
  $psc= $o->_psc;
  $obec= $o->_obec;
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
  $texty[$n]= (object)array();
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
# ----------------------------------------------------------------------------------- mail2 mai_text
# přečtení mailu
# pokud je $akce=1 vrátí název a rok akce nebo název mailistu
function mail2_mai_text($id_dopis,$akce=0) {  //trace();
  $ret= (object)array('html'=>'');
  $d= null;
  $prilohy= $obsah= '';
  $elem= !$akce ? '' 
      : ",CASE "
        . "WHEN id_duakce!=0 THEN CONCAT(a.nazev,', ',YEAR(datum_od)) "
        . "WHEN id_mailist!=0 THEN m.ucel "
        . "WHEN cis_skupina!=0 THEN c.zkratka "
        . "ELSE '???' END AS _adrs,"
      . "COUNT(*) AS _pocet";
  $join= !$akce ? ''
      : "LEFT JOIN akce AS a USING (id_duakce) "
      . "LEFT JOIN mailist AS m USING (id_mailist) "
      . "LEFT JOIN mail USING (id_dopis)"
      . "LEFT JOIN _cis AS c ON data=cis_skupina AND c.druh='db_maily_sql'";
  $group= !$akce ? ''
      : "GROUP BY id_dopis";
  try {
    $qry= "SELECT d.nazev,obsah,prilohy,id_duakce,id_mailist,cis_skupina $elem "
        . "FROM dopis AS d $join WHERE id_dopis=$id_dopis $group";
    $res= pdo_qry($qry);
    $d= pdo_fetch_object($res);
  }
  catch (Exception $e) { 
    display($e); fce_error("mail2_mai_text: průběžný dopis No.'$id_dopis' nebyl nalezen"); 
  }
  $predmet= $d->nazev;
  $obsah= $d->obsah;
  // příloha?
  if ( $d->prilohy ) {
    foreach ( explode(',',$d->prilohy) as $priloha ) {
      if ( $akce ) {
        list($file)= explode(':',$priloha);
        $prilohy.= " <a target='docs' href='/docs/db2/$file'>$priloha</a>, ";
      }
      else {
        $prilohy.= "<hr/><b>Příloha:</b> $priloha";
        $typ= strtolower(substr($priloha,-4));
        if ( $typ=='.jpg' || $typ=='.gif' || $typ=='.png' ) {
          $prilohy.= "<img src='docs/$priloha' />";
        }
      }
    }
  }
  if ( $akce ) {
    $komu= $d->id_duakce ? "<b>AKCE:</b> $d->_adrs" : ( 
           $d->id_mailist ? "<b>MAILIST:</b> $d->_adrs" : (
           $d->cis_skupina ? "<b>SKUPINA:</b> $d->_adrs" : 
           $d->_adrs ));
    $pocet= "<hr><b>ADRESÁTŮ:</b> $d->_pocet";
    $prilohy= $prilohy ? "<hr><b>PŘÍLOHY:</b> $prilohy" : '';
    $ret->html= "$komu$pocet<hr><b>PŘEDMĚT:</b> $predmet$prilohy<hr>$obsah";
  }
  else {
    $ret->html= "<b>$predmet</b><hr>$obsah$prilohy";
  }
  return $ret;
}
# -------------------------------------------------------------------------------- mail2 mai_prazdny
# zjistí zda neexistuje starý seznam adresátů
function mail2_mai_prazdny($id_dopis) {  trace();
  $result= array('_error'=>0, '_prazdny'=> 1);
  // ověř prázdnost MAIL
  $qry= "SELECT * FROM mail WHERE id_dopis=$id_dopis";
  $res= pdo_qry($qry);
  if ( pdo_num_rows($res)>0 ) {
    $result['_html']= "Rozesílací seznam pro tento mail již existuje, stiskni ANO pokud má být přepsán novým";
    $result['_prazdny']= 0;
  }
  return $result;
}
# ------------------------------------------------------------------------------------ mail2 mai_qry
# sestaví SQL dotaz podle položky DOPIS.komu
# formát zápisu dotazu v číselníku:  A[|D[|cond]]
#   kde A je seznam aktivit oddělený čárkami
#   a D=1 pokud mají být začleněni pouze letošní a loňští dárci
#   a cond je obecná podmínka na položky tabulky CLEN
function mail2_mai_qry($komu) {  trace();
  list($aktivity,$is_dary,$cond)= explode('|',$komu);
  $and= $aktivity=='*' ? '' : "AND FIND_IN_SET(aktivita,'$aktivity')";
  if ( $cond ) $and.= " AND $cond";
  $letos= date('Y'); $loni= $letos-1;
  $qry= $is_dary
    ? "SELECT id_clen, email,
         BIT_OR(IF((YEAR(datum) BETWEEN $loni AND $letos) AND LEFT(dar.deleted,1)!='D'
           AND castka>0 AND akce='G',1,0)) AS is_darce
       FROM clen LEFT JOIN dar USING (id_clen)
       WHERE LEFT(clen.deleted,1)!='D' AND umrti=0 AND aktivita!=9 AND email!='' $and
       GROUP BY id_clen HAVING is_darce=1"
    : "SELECT id_clen, email FROM clen
       WHERE left(deleted,1)!='D' AND umrti=0 AND email!='' $and";
  return $qry;
}
# ---------------------------------------------------------------------------------- mail2 mai_omitt
# v tabulce MAIL(id_dopis=$dopis) označí jako neposlatelné emailu z MAIL($id_dopis=$vynech)
# to je funkce určená k zamezení duplicit
function mail2_mai_omitt($id_dopis,$ids_vynech) {  trace();
  $msg= "Z mailů podle dopisu $id_dopis chci vynechat adresy z mailů podle dopisu $ids_vynech";
  // seznam vynechaných adres
  $vynech= array();
  $qv= "SELECT email FROM mail WHERE id_dopis IN ($ids_vynech) ";
  $rv= pdo_qry($qv);
  while ( $rv && ($v= pdo_fetch_object($rv)) ) {
    foreach(explode(',',str_replace(';','',str_replace(' ','',$v->email))) as $em) {
      $vynech[]= $em;
    }
  }
//                                                         debug($vynech,"vynechané adresy");
  $msg.= "<br>podezřelých je ".count($vynech)." adres";
  // probírka adresátů
  $n= 0;
  $qd= "SELECT id_mail,email FROM mail WHERE id_dopis=$id_dopis ";
  $rd= pdo_qry($qd);
  while ( $rd && ($d= pdo_fetch_object($rd)) ) {
    $emaily= $d->email;
    foreach(explode(',',str_replace(';','',str_replace(' ','',$emaily))) as $em) {
      if ( in_array($em,$vynech) ) {
        $n++;
        $qu= "UPDATE mail SET stav=5,msg='- $ids_vynech' WHERE id_mail={$d->id_mail} ";
        $ru= pdo_qry($qu);
      }
    }
  }
  $msg.= "<br>označeno bylo $n řádků";
  return $msg;
}
# --------------------------------------------------------------------------------- mail2 mai_omitt2
# v tabulce MAIL(id_dopis=$dopis) označí jako neposlatelné emaily $vynech (čárkami oddělený seznam)
function mail2_mai_omitt2($id_dopis,$lst_vynech) {  trace();
  // seznam vynechaných adres
  $vynech= explode(',',str_replace(' ','',$lst_vynech));
  $msg= "Z mailů podle dopisu $id_dopis chci vynechat ".count($vynech)." adres";
//                                                         debug($vynech,"vynechané adresy");
  // probírka adresátů
  $n= 0;
  $qd= "SELECT id_mail,email FROM mail WHERE id_dopis=$id_dopis ";
  $rd= pdo_qry($qd);
  while ( $rd && ($d= pdo_fetch_object($rd)) ) {
    $emaily= $d->email;
    foreach(explode(',',str_replace(';','',str_replace(' ','',$emaily))) as $em) {
//                                         display("'$em'=".(in_array($em,$vynech)?1:0));
      if ( in_array($em,$vynech) ) {
        $n++;
        $qu= "UPDATE mail SET stav=5,msg='viz' WHERE id_mail={$d->id_mail} ";
        $ru= pdo_qry($qu);
      }
    }
  }
  $msg.= "<br>označeno bylo $n adres";
  return $msg;
}
# --------------------------------------------------------------------------------- mail2 mai_omitt3
# v tabulce MAIL(id_dopis=$dopis) označí jako neposlatelné emaily $vynech (čárkami oddělený seznam)
# obsahující id_osoba adresátů
function mail2_mai_omitt3($id_dopis,$lst_vynech) {  trace();
  // seznam vynechaných adres
  $lst_vynech= str_replace(' ','',$lst_vynech);
  $vynech= explode(',',$lst_vynech);
  $msg= "Ze seznamu adresátů dopisu $id_dopis chci vynechat ".count($vynech)." řádků";
//                                                         debug($vynech,"vynechané adresy");
  // probírka adresátů
  $qu= "UPDATE mail SET stav=5,msg='dle ID' WHERE id_dopis=$id_dopis AND id_clen IN ($lst_vynech) ";
  $ru= pdo_qry($qu);
  $n= pdo_affected_rows($ru);
  $msg.= "<br>označeno bylo $n řádků";
  return $msg;
}
# -------------------------------------------------------------------------------- mail2 mai_doplnit
# zjistí počet adresátů pro doplnění rozesílání a sestaví dotaz pro confirm
# pokud $doplnit=1 tak přímo doplní tabulku mail
function mail2_mai_doplnit($id_dopis,$id_akce,$doplnit) {  trace();
  $ret= (object)array('err'=>1, 'html'=>'?');
  // zjistíme dopis.komu= 0:všem, 1:VPS..., 2:dlužníci, 3:OP
  list($komu,$obsah)= select('komu,obsah','dopis',"id_dopis=$id_dopis AND id_duakce=$id_akce");
  // zjistíme počet - POZOR KOPIE KÓDU SQL z mail2_mai_pocet
  $AND= $komu==0 ? " AND p.funkce IN (0,1,2,5)" : (
        $komu==1 ? " AND p.funkce IN (1,2,5)"   : (
        $komu==2 ? " AND p.funkce IN (0,1,2,5) " : (
        $komu==3 ?
           " AND IF(IFNULL(role,'a') IN ('a','b'),REPLACE(o.obcanka,' ','') NOT RLIKE '^[0-9]{9}$',0)"
       : " --- chybné komu --- " )));
  $HAVING= $komu==2 ? "HAVING _uhrada<_poplatek" : "";
  // využívá se toho, že role rodičů 'a','b' jsou před dětskou 'd', takže v seznamech
  // GROUP_CONCAT jsou rodiče, byli-li na akci. Emaily se ale vezmou ode všech, mají-li osobní
  $n_neobeslani= $n_novi= $n_pridano= $n_err= 0; $err= $dele= '';
  $x_pm= array(); // pole mailů pro daný pobyt
  $x_om= array(); // pole platných mailů dané osoby
  $o_jm= array(); // jména osob
  $x_po= array(); // osoby daného pobytu
  $rr= pdo_qry("
    SELECT s.id_osoba,id_pobyt,
    --  a.nazev,pouze,
      COUNT(*) AS _na_akci,
    --  avizo,
    --  GROUP_CONCAT(DISTINCT o.id_osoba ORDER BY t.role) AS _id,
      GROUP_CONCAT(DISTINCT CONCAT(prijmeni,' ',jmeno)) AS _jm,
      GROUP_CONCAT(DISTINCT IF(o.kontakt,o.email,'')) AS email,
      IF(o.kontakt,'-',IFNULL(GROUP_CONCAT(DISTINCT r.emaily),'')) AS emaily,
      SUM(u.u_castka) AS _uhrada,
      platba,p.platba1+p.platba2+p.platba3+p.platba4-vratka1-vratka2-vratka3-vratka4 AS _poplatek
    FROM dopis AS d
      JOIN akce AS a ON d.id_duakce=a.id_duakce
      JOIN pobyt AS p ON d.id_duakce=p.id_akce
      JOIN spolu AS s USING (id_pobyt)
      JOIN osoba AS o USING (id_osoba)
      LEFT JOIN mail AS m USING (id_pobyt,id_dopis)
      LEFT JOIN rodina AS r ON r.id_rodina=p.i0_rodina
      LEFT JOIN uhrada AS u USING (id_pobyt)
      LEFT JOIN tvori AS t USING (id_osoba,id_rodina)
    WHERE d.id_dopis=$id_dopis AND ISNULL(id_mail) $AND
    GROUP BY id_pobyt $HAVING");
  while ( $rr && (list($ido,$idp,$na_akci,$jm,$email,$emaily)= pdo_fetch_array($rr)) ) {
    // osoby pobytu
    if ( !isset($x_po[$idp]) ) $x_po[$idp]= array();
    $x_po[$idp][]= $ido;
    $o_jm[$ido]= $jm;
    // zjištění mailů jednotlivých osob
    $x_om[$ido]= array();
    foreach(preg_split('/\s*[,;]\s*/',$email,0,PREG_SPLIT_NO_EMPTY) as $m)
      $x_om[$ido][]= $m;
    if ( !count($x_om[$ido]) && $emaily!='-' )
      foreach(preg_split('/\s*[,;]\s*/',$emaily,0,PREG_SPLIT_NO_EMPTY) as $m)
        $x_om[$ido][]= $m;
  }
  $n_neobeslani= count($x_po);
  // očištění mailů
  foreach ($x_po as $idp=>$idos) {
    $ms= $delm= '';
    foreach ($idos as $ido) {
      foreach ($x_om[$ido] as $im=>$m) {
        if ( strpos($m,'*')===false ) {
          if ( emailIsValid($m,$chyba) ) {
            $ms.= "$delm$m"; $delm= ',';
          }
          else {
            $err.= "$dele{$o_jm[$ido]} má chybnou adresu $m ($chyba)"; $dele= ', ';
            unset($x_om[$ido][$im]);
          }
        }
        else {
          $err.= "$dele{$o_jm[$ido]} má zneplatněnou adresu $m"; $dele= ', ';
          unset($x_om[$ido][$im]);
        }
      }
    }
    $x_pm[$idp]= $ms;
    if ( $ms ) {
      $n_novi++;
    }
    else {
      $n_err++;
      $err.= "$dele{$o_jm[$ido]} nemá žádnou adresu"; $dele= ', ';
    }
  }
//                                                         debug($x_om); debug($x_po);
  if ( $doplnit ) {
    // zjisti jestli text dopisu obsahuje proměnné
    $is_vars= preg_match_all("/[\{]([^}]+)[}]/",$obsah,$list);
    $vars= $list[1];
    // projdi všechny pobyty s alespoň jedním mailem
    foreach ($x_po as $idp=>$idos) { if ( $x_pm[$idp] ) {
      // pokud dopis obsahuje proměnné, personifikuj obsah
      $priloha= null;
      $body= $is_vars ? mail2_personify($obsah,$vars,$idp,$priloha,$err) : '';
      // a vytvoř mail
      $qr= "INSERT mail (id_davka,znacka,stav,id_dopis,id_clen,id_pobyt,email,body,priloha)
            VALUE (1,'@',0,$id_dopis,{$x_po[$idp][0]},$idp,'{$x_pm[$idp]}','$body','$priloha')";
      $rs= pdo_qry($qr);
      $n_pridano+= pdo_affected_rows($rs);
//                                                         display($qr);
    }}
  }
  // čeština
  $_pobytu= je_1_2_5($n_neobeslani,"pobyt,pobyty,pobytů");
  $ret->html= "Dosud neobeslaných jsou $_pobytu";
  if ( $doplnit ) {
    $_mailu=  je_1_2_5($n_pridano,"mail,maily,mailů");
    $ret->html.= "<br><br><b>Bylo přidáno $_mailu pro dosud neobeslané pobyty?</b>";
    if ( $err ) {
      $_pobytu= je_1_2_5($n_err,"pobyt,pobyty,pobytů");
      $ret->html.= "<br><br><i>Bohužel $_pobytu doplnit nešlo.<br><br>$err</i>";
    }
  }
  else {
    $_mailu=  je_1_2_5($n_novi,"mail,maily,mailů");
    $ret->html.= "<br><br><b>Mám doplnit $_mailu pro neobeslané pobyty?</b>";
    if ( $err ) {
      $_pobytu= je_1_2_5($n_err,"pobyt,pobyty,pobytů");
      $ret->html.= "<br><br><i>Bohužel $_pobytu doplnit nepůjde.<br><br>Problémy: $err</i>";
    }
  }
  return $ret;
}
# ---------------------------------------------------------------------------------- mail2 mai_pocet
# zjistí počet adresátů pro rozesílání a sestaví dotaz pro confirm
# $dopis_var určuje zdroj adres
#   'U' - (komu=0) rozeslat všem účastníkům akce a hnízda dopis.id_duakce ukazující do akce
#         do seznamu se dostanou pouze účastnící s funkcí:0,1,2,6 (-,VPS,SVPS,hospodář)
#   'U1'- (komu=4) rozeslat účastníkům akce a hnízda dopis.id_duakce ukazující do akce
#         do seznamu se dostanou pouze organizující účastníci bez funkce
#   'U2'- (komu=1) rozeslat účastníkům akce a hnízda dopis.id_duakce ukazující do akce
#         do seznamu se dostanou pouze organizující účastnící s funkcí:1,2,6 (VPS,SVPS,hospodář)
#   'U3'- (komu=2) rozeslat účastníkům akce a hnízda dopis.id_duakce ukazující do akce
#         do seznamu se dostanou pouze dlužníci (bez avíza)
#   'U4'- (komu=3) rozeslat účastníkům akce a hnízda dopis.id_duakce ukazující do akce
#         do seznamu se dostanou pouze dospělí s chybějícím nebo zjevně starým OP
#   'U5'- (komu=5) rozeslat všem na akci přítomným vyjma pečounů
#   'U6'- (komu=6) jako U5 ale jen pro prislusnost=CZ/SK
#   'U7'- (komu=7) jako U5 ale jen pro prislusnost!=CZ/SK
#   'U8'- (komu=8) kněží, psycho
#   'Q' - rozeslat na adresy vygenerované dopis.cis_skupina => hodnota
#   'G' - rozeslat podle mailistu - varianta osoba/rodina
# pokud _cis.data=9999 jde o speciální seznam definovaný funkcí mail2_mai_skupina - ZRUŠENO
# $cond = dodatečná podmínka POUZE pro volání z mail2_mai_stav
function mail2_mai_pocet($id_dopis,$dopis_var,$cond='',$recall=false) {  trace();
  $result= (object)array('_error'=>0, '_count'=> 0, '_cond'=>false);
  $result->_html= 'Rozesílání mailu nemá určené adresáty, stiskni NE';
  $html= '';
  $emaily= $ids= $jmena= $pobyty= array();
  $spatne= $nema= $mimo= $nomail= $umrti= '';
  $n= $ns= $nt= $nx= $mx= $nm= $nu= 0;
  $dels= $deln= $delm= $delnm= '';
  $nazev= '';
  switch ($dopis_var) {
  // --------------------------------------------------- mail-list
  case 'G':
    $html.= "Vybraných adresátů ";
    $id_mailist= select('id_mailist','dopis',"id_dopis=$id_dopis");
//     list($qry,$ucel)= select('sexpr,ucel','mailist',"id_mailist=$id");
    $ml= mail2_lst_access($id_mailist);
    $result->komu= $komu= $ml->komu;
    // SQL dotaz z mail-listu obsahuje _email,_umrti,_nazev,_id (=id_osoba nebo id_rodina podle komu)
    $res= pdo_qry($ml->sexpr);
    while ( $res && ($d= pdo_fetch_object($res)) ) {
      $n++;
      $nazev= "'{$ml->ucel}'";
      if ( $d->nomail ) {
        // nechce dostávat maily
        $nomail.= "$delnm{$d->_name}"; $delnm= ', '; $nm++;
        continue;
      }
      if (  $d->_umrti ) {
        // nemůže dostávat maily
        $umrti.= "$delnm{$d->_name}"; $delnm= ', '; $nu++;
        continue;
      }
      if ( $d->_email ) {
        if ( $komu=='o' ) {
          // pro osoby přidej každý mail zvlášť do seznamu
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
          // pro rodiny vytvoř seznamy rodiných mailů
          $r_emaily= array();
          foreach(preg_split('/\s*[,;]\s*/',trim($d->_email,",; \n\r"),0,PREG_SPLIT_NO_EMPTY) as $adr) {
            if ( $adr[0]=='*' ) {
              // vyřazený mail
              $mimo.= "$delm{$d->_name}"; $delm= ', '; $mx++;
            }
            else {
              $r_emaily[]= $adr;
            }
          }
          $emaily[]= implode(',',$r_emaily);
          $ids[]= isset($d->_idr) ? $d->_idr : 0;
          $jmena[]= $d->_name;
        }
      }
      else {
        $nema.= "$deln{$d->_name}"; $deln= ', ';
        $nx++;
      }
    }
    break;
  // --------------------------------------------------- obecný SQL dotaz - skupina
  case 'Q':
    $html.= "Vybraných adresátů ";
    $qryQ= "SELECT _cis.hodnota,_cis.zkratka FROM dopis
           JOIN _cis ON _cis.data=dopis.cis_skupina AND _cis.druh='db_maily_sql'
           WHERE id_dopis=$id_dopis ";
    $resQ= pdo_qry($qryQ);
    if ( $resQ && ($q= pdo_fetch_object($resQ)) ) {
      $qry= $q->hodnota;
      $qry= mail2_sql_subst($qry);
      $res= pdo_qry($qry);
      while ( $res && ($d= pdo_fetch_object($res)) ) {
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
  // --------------------------------------------------- účastníci akce
  case 'U8':    // kněží, psychologové
  case 'U7':    // všichni zahraniční přítomní 
  case 'U6':    // všichni tuzemští přítomní 
  case 'U5':    // všichni přítomní (mimo pečounů)
  case 'U4':    // divný OP
  case 'U3':    // dlužníci
  case 'U2':    // sloužící
  case 'U1':    // nesloužící
  case 'U':
    $html.= "Obeslaných účastníků ";
    // zjisti údaje o akci
    list($a_nazev,$ma_cenu,$cena,$hnizda)= select('akce.nazev,ma_cenu,cena,hnizda',
        'dopis JOIN akce USING (id_duakce)',"id_dopis=$id_dopis");
    // POZOR KOPIE KÓDU z mail2_mai_doplnit
    $AND= $cond ? "AND $cond" : '';
    $AND.= $hnizda ? "AND (d.hnizdo=99 || d.hnizdo=p.hnizdo)" : '';
    $AND.= $dopis_var=='U'  ? " AND p.funkce IN (0,1,2,5)" : (
           $dopis_var=='U1' ? " AND p.funkce=0"   : (
           $dopis_var=='U2' ? " AND p.funkce IN (1,2,5)"   : (
           $dopis_var=='U3' ? " AND p.funkce IN (0,1,2,5) " : (
           $dopis_var=='U4' ?
             " AND IF(IFNULL(role,'a') IN ('a','b'),REPLACE(o.obcanka,' ','') NOT RLIKE '^[0-9]{9}$',0)" : (
           $dopis_var=='U5' ? " AND p.funkce NOT IN (9,10,13,14,15,99) " : (
           $dopis_var=='U6' ? " AND p.funkce NOT IN (9,10,13,14,15) AND o.prislusnost IN ('','CZ','SK') " : (
           $dopis_var=='U7' ? " AND p.funkce NOT IN (9,10,13,14,15) AND o.prislusnost NOT IN ('','CZ','SK') " : (
           $dopis_var=='U8' ? " AND p.funkce IN (3,4)" 
         : " --- chybné komu --- " ))))))));
//    $HAVING= $dopis_var=='U3' ? "HAVING _uhrada/_na_akci<cena" : "";
    $HAVING= $dopis_var=='U3' ? ( $ma_cenu 
          ? "HAVING _uhrada/_na_akci<$cena" 
          : "HAVING _uhrada<_poplatek")
        : "";
    // využívá se toho, že role rodičů 'a','b' jsou před dětskou 'd', takže v seznamech
    // GROUP_CONCAT jsou rodiče, byli-li na akci. Emaily se ale vezmou ode všech, mají-li osobní
    $qry= "SELECT p.id_pobyt,pouze,COUNT(DISTINCT s.id_osoba) AS _na_akci, 
             (SELECT IFNULL(SUM(u_castka),0) FROM uhrada WHERE id_pobyt=p.id_pobyt) AS _uhrada,
             p.poplatek_d, 
             p.platba1+p.platba2+p.platba3+p.platba4 -vratka1-vratka2-vratka3-vratka4 AS _poplatek,
             GROUP_CONCAT(DISTINCT o.id_osoba ORDER BY t.role) AS _id,
             GROUP_CONCAT(DISTINCT CONCAT(prijmeni,' ',jmeno) ORDER BY t.role) AS _jm,
             GROUP_CONCAT(DISTINCT IF(o.kontakt,TRIM(o.email),'')) AS email,
             GROUP_CONCAT(DISTINCT TRIM(r.emaily)) AS emaily
           FROM dopis AS d
           JOIN pobyt AS p ON d.id_duakce=p.id_akce
           JOIN spolu AS s USING (id_pobyt)
           JOIN osoba AS o ON s.id_osoba=o.id_osoba
           LEFT JOIN tvori AS t ON t.id_osoba=o.id_osoba AND id_rodina=i0_rodina
           LEFT JOIN rodina AS r USING (id_rodina)
           LEFT JOIN uhrada AS u USING (id_pobyt)
           WHERE id_dopis=$id_dopis $AND 
             AND IF(i0_rodina,IF(ISNULL(t.role),0,t.role IN ('a','b')),1)
             GROUP BY id_pobyt $HAVING";
    $res= pdo_qry($qry);
    while ( $res && ($d= pdo_fetch_object($res)) ) {
      $n++;
      $nazev= "Účastníků {$a_nazev}";
      list($jm)= explode(',',$d->_jm);
      // kontrola vyřazených mailů
      $eo= $d->email;
      if ( strpos($eo,'*')!==false ) { $mimo.= "$delm$jm"; $delm= ', '; $mx++; $eo= ''; }
      $er= $d->emaily;
      if ( strpos($er,'*')!==false ) { $mimo.= "$delm$jm"; $delm= ', '; $mx++; $er= ''; }
      // pokud je na akci pouze jeden, pošli jen na jeho mail - pokud oba, pošli na všechny maily
      if ( $eo!='' || $er!='' ) {
//         $em= $d->pouze && $eo!='' ? $eo : (             // na akci pouze jeden => osobní mail
        $em= $d->_na_akci==1 && $eo!='' ? $eo : (       // na akci pouze jeden => osobní mail
          $eo!='' && $er!='' ? "$eo,$er" : $eo.$er      // jinak cokoliv půjde
        );
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
  // --------------------------------------------------- projdi a verifikuj adresy
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
  $html.= $nu ? "$nu zemřelo ($umrti)" : '';
  $result->_html= "$html<br><br>" . ($nt>0
    ? "Opravdu vygenerovat seznam pro rozeslání\n'$nazev'\nna $nt adres?"
    : "Mail '$nazev' nemá žádného adresáta, stiskni NE");
  $result->_count= $nt;
  if ( !$recall ) {
    // pro delší seznamy
    $result->_dopis_var= $dopis_var;
    $result->_cond= $cond ? $cond : '';
    $result->_adresy= array();
    $result->_ids= array();
  }
//                                                debug($result,"mail2_mai_pocet.result");
  return $result;
}
# ---------------------------------------------------------------------------------- mail2 mai_posli
# do tabulky MAIL dá seznam emailových adres pro rozeslání (je volána po mail2_mai_pocet)
# $id_dopis => dopis(&pocet)
# $info = {_adresy,_ids[,_cond]}   _cond
function mail2_mai_posli($id_dopis,$info) {  trace();
  $num= 0;
  $err= '';
//                                                         debug($info);
  // smaž starý seznam
  $qry= "DELETE FROM mail WHERE id_dopis=$id_dopis ";
//                                                         fce_log("mail2_mai_posli: $qry");
  $n= pdo_qry($qry);
  if ( $n ) fce_warning("bylo smazáno $n dříve vygenerovaných mailů");

  if ( isset($info->_dopis_var) ) {
    // přepočítej adresy
    $info= mail2_mai_pocet($id_dopis,$info->_dopis_var,$info->_cond,true);
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
    foreach($info->_ids as $i=>$id) {$ids[$i]= $id;}
    if ( $info->_pobyty ) foreach($info->_pobyty as $i=>$pobyt) {$pobyty[$i]= $pobyt;}
    foreach ($info->_adresy as $i=>$email) {
      $id= $ids[$i];
      // vlož do MAIL - pokud nezačíná *
      if ( $email[0]!='*' ) {
        $id_pobyt= isset($pobyty[$i]) ? $pobyty[$i] : 0;
        // pokud dopis obsahuje proměnné, personifikuj obsah
        $priloha= null;
        $body= $is_vars ? mail2_personify($obsah,$vars,$id_pobyt,$priloha,$err) : '';
        $qr= "INSERT mail (id_davka,znacka,stav,id_dopis,id_clen,id_pobyt,email,body,priloha)
              VALUE (1,'@',0,$id_dopis,$id,$id_pobyt,'$email','$body','$priloha')";
//                                         display("$i:$qr");
        $num+= pdo_query($qr);
      }
    }
  }
  else {
    // jinak zjisti adresy z databáze
    $qry= mail2_mai_qry($info->_cond);
    $res= pdo_qry($qry);
    while ( $res && $c= pdo_fetch_object($res) ) {
      // zjisti adresy (oddělené ,;) a vyřaď ty uvozené *
      $poslat= array();
      $maily= preg_grep('/,;/', $c->email);
      foreach ($maily as $mail) {
        if (trim($mail)[0]!='*') $poslat[]= $mail;
      }
      // vlož do MAIL
      $poslat= implode(',',$poslat);
      if ( $poslat ) {
        $qr= "INSERT mail (id_davka,znacka,stav,id_dopis,id_clen,email)
              VALUE (1,'@',0,$id_dopis,{$c->id_clen},'{$c->email}')";
        $rs= pdo_qry($qr);
        $num+= pdo_affected_rows($rs);
      }
    }
  }
  // oprav počet v DOPIS
  $qr= "UPDATE dopis SET pocet=$num WHERE id_dopis=$id_dopis";
//                                                         fce_log("mail2_mai_posli: UPDATE");
  $rs= pdo_qry($qr);
  // získání případného omezení použitého SMTP
  global $ezer_root;
  $idu= $_SESSION[$ezer_root]['user_id'];
  $i_smtp= sys_user_get($idu,'opt','smtp');
  if (!$i_smtp) {
    $err.= "<hr>Není přístupný žádný odesílací SMTP server"; 
    goto end;
  }
  $max_per_day= select1('ikona','_cis',"druh='smtp_srv' AND data='$i_smtp'");
  // případné upozornění na maximum
  if ( $max_per_day && $info->_count>$max_per_day ) {
    $err.= "<hr>Přes GMail lze denně lze posílat maximálně $max_per_day mailů - 
        před dosažením maxima bude odesílání přerušeno (před reakcí GMailu). 
        Pokračujte potom prosím v odesílání další den (nebo po určité době).
        Informace od Google naleznete 
        <a href='https://support.google.com/mail/answer/22839?hl=cs' target='hlp'>zde</a>.";
  }
end:  
  return $err;
}
# ---------------------------------------------------------------------------------- mail2 personify
# spočítá proměnné podle id_pobyt a dosadí do textu dopisu
# vrátí celý text
function mail2_personify($obsah,$vars,$id_pobyt,&$priloha,&$err) { 
    debug($vars,"mail2_personify(...,$vars,$id_pobyt,...) ");
  global $ezer_path_root;
  $text= $obsah;
  $priloha= '';
  list($duvod_typ,$duvod_text,$id_hlavni,$id_soubezna,$ma_cenik_verze,
       $rok_akce,$nazev_akce,$ss_akce,$skupina,$idr)=
    select('duvod_typ,duvod_text,IFNULL(id_hlavni,0),id_duakce,ma_cenik_verze,
      YEAR(datum_od),nazev,ciselnik_akce,skupina,i0_rodina',
    "pobyt LEFT JOIN akce ON id_duakce=pobyt.id_akce",
    "id_pobyt=$id_pobyt");
  foreach($vars as $var) {
    $val= '';
    switch ($var) {
    case 'foto_z_akce':
      $fotky= select('fotka','rodina',"id_rodina=$idr");
      if ($fotky) {
        $del= '';
        $nazvy= explode(',',$fotky);
        foreach ($nazvy as $nazev) {
          $foto= "$ezer_path_root/fotky/$nazev";
          if (file_exists($foto) ) {
            $date= filemtime($foto);
            $ymd= date('Y-m-d',$date);
            $na_akci= select('COUNT(*)','akce JOIN pobyt ON id_akce=id_duakce',
                "id_pobyt=$id_pobyt AND '$ymd' BETWEEN datum_od AND datum_do");
            if ($na_akci) {
              $priloha.= "{$del}fotky/$nazev"; $del=',';
            }
          }
        }
      }
      break;
    case 'pratele':
      $val= $idr ? select('nazev','rodina',"id_rodina=$idr") : 'přátelé';
      break;
    case 'akce_cena':
//      // zjisti, zda je cena stanovena
//      if (($platba1+$platba2+$platba3+$platba4+$poplatek_d)==0) {
//        // není :-(
//        $err.= "<br>POZOR: všichni účastníci nemají stanovenu cenu (pobyt=$id_pobyt)";
//        break;
//      }
      if ( $duvod_typ ) {
        $val= $duvod_text;
      }
      elseif ( $id_hlavni ) {
        $ret= akce2_vzorec_soubeh($id_pobyt,$id_hlavni,$id_soubezna);
        $val= $ret->mail;
      }
      elseif ( $ma_cenik_verze==2 ) {
        $ret= akce2_vzorec2_pobyt($id_pobyt);
        $tab= $ret->mail;
        $amount= (float)array_sum($ret->rozpis);
        $account= "000000-2400465447/2010";
        $account= "CZ2420100000002400465447"; // IBAN
        $ss= $ss_akce;
        $vs= '';
        $message= $nazev_akce;
        debug($ret,$amount);
        // zkusíme najít datum narození muže
        $ro= pdo_query("SELECT narozeni FROM osoba JOIN spolu USING (id_osoba)
            WHERE id_pobyt=$id_pobyt AND narozeni!='0000-00-00' AND $rok_akce-YEAR(narozeni)>18
            ORDER BY sex,narozeni ");
        while ($ro && (list($narozeni)= pdo_fetch_array($ro))) {
          $vs= substr($narozeni,2,2).substr($narozeni,5,2).substr($narozeni,8,2);
          break;
        }
        display("akce_qr_platby($id_pobyt,$account,$amount,$ss,$vs,$message)");
        $qr= akce_qr_platby($id_pobyt,$account,$amount,$ss,$vs,$message);
//        display($qr);
//        $val= "<div><div style='float:right'>$qr</div>$tab</div>";
        $val= $val.$qr;
      }
      else {
        $ret= akce2_vzorec($id_pobyt);
        $val= $ret->mail;
      }
      // zjisti, zda je cena stanovena
      if (!$val) {
        $nazev= select('nazev','rodina',"id_rodina=$idr");
        $err.= "<br>POZOR: všichni účastníci nemají stanovenu cenu (pobyt=$id_pobyt, $nazev)";
      }
      break; 
    case 'skupinka_chlapi':
      if ($skupina) {
        $ida= select('id_akce','pobyt',"id_pobyt=$id_pobyt");
        $res= akce2_starsi_mrop_pdf($ida,$id_pobyt);
        $val= "<div style='background-color:#eeeeee;margin-left:15px'>$res->skupina</div>";
      }
      else {
        $val= "<div style='background-color:#eeeeee;margin-left:15px'>Skupinka ještě není vybrána</div>";
      }
      break;
    case 'skupinka_popo':
      if ($skupina) {
        $ida= select('id_akce','pobyt',"id_pobyt=$id_pobyt");
        $tab= "<table>";
        $s= pdo_qry("
            SELECT 
              CONCAT(nazev,' ',GROUP_CONCAT(o.jmeno ORDER BY t.role SEPARATOR ' a ')) AS _nazev,
              GROUP_CONCAT(IF(kontakt,IF(o.telefon!='',o.telefon,'?'),r.telefony) 
                ORDER BY t.role SEPARATOR ' a ') AS telefon, 
              GROUP_CONCAT(IF(kontakt,IF(o.email!='',o.email,'?'),r.emaily) 
                ORDER BY t.role SEPARATOR ' a ') AS email
            FROM pobyt AS p
            JOIN spolu AS s USING(id_pobyt)
            JOIN osoba AS o ON s.id_osoba=o.id_osoba
            LEFT JOIN tvori AS t ON t.id_osoba=o.id_osoba AND id_rodina=i0_rodina
            LEFT JOIN rodina AS r USING(id_rodina)
            WHERE p.id_akce=$ida AND skupina=$skupina AND IF(ISNULL(r.id_rodina),1,t.role IN ('a','b'))
            GROUP BY id_pobyt
            ORDER BY IF(funkce IN (1,2),1,2), _nazev        
          ");
        while ($s && (list($par,$tel,$mail)= pdo_fetch_row($s))) {
          $tab.= "<tr><th>$par</th><td>$tel</td><td>$mail</td></tr>";
        }
        $tab.= "</table>";
        $val= "<big><b><u>SKUPINKA $skupina</u></b></big> $tab";
        $val= "<div style='background-color:#eeeeee;margin-left:15px'>$val</div>";
      }
      else {
        $val= "<div style='background-color:#eeeeee;margin-left:15px'>Skupinka ještě není vybrána</div>";
      }
      break;
    }
    $text= str_replace('{'.$var.'}',$val,$text);
  }
  $text= pdo_real_escape_string($text);
  return $text;
}
# ----------------------------------------------------------------------------- mail2 personify_help
# vrátí popis možných personifikací
function mail2_personify_help() {
  $html= "
    <b>{pratele}</b> vloží název rodiny, pokud na akci není rodina, vloží slovo 'přátelé'<br>
    <b>{akce_cena}</b> pokud má akce definovaný ceník, vloží rozpis platby účastníka<br><br>
    <b>{skupinka_popo}</b> pro VPS vloží seznam členů skupiny s maily a telefony<br>
    <b>{skupinka_chlapi}</b> pro staršího/stokera vloží seznam členů skupiny s maily a telefony<br>
    <b>{foto_z_akce}</b> pokud byla na akci vložena fotografie rodiny, přidá ji jako přílohu<br>
    <br>
    ";
//    <b>{mistnost_popo}</b> pro VPS vloží odkaz na konrefenční místnost<br>
  return $html;
}
# ----------------------------------------------------------------------------------- mail2 mai_info
# informace o členovi
# $id - klíč osoby nebo chlapa
# $zdroj určuje zdroj adres
#   'U','U1','U2','U3','U4','U5','U6','U7','U8' - rozeslat účastníkům akce dopis.id_duakce ukazující do akce
#   'C' - rozeslat účastníkům akce dopis.id_duakce ukazující do ch_ucast
#   'Q' - rozeslat na adresy vygenerované dopis.cis_skupina => hodnota
#   'G' - maillist
function mail2_mai_info($id,$email,$id_dopis,$zdroj,$id_mail) {  //trace();
  $html= '';
  $make_href= function ($fnames) {
    global $ezer_root;
    $href= array();
    foreach(explode(',',$fnames) as $fnamesize) {
      list($fname)= explode(':',$fnamesize);
      $has_dir= strrpos($fname,'/');
      $path= $has_dir ? $fname : "docs/$ezer_root/";
      if ($has_dir) $fname= substr($fname,$has_dir+1);
      $href[]= "<a href='$path' target='pdf'>$fname</a>";
    }
    return implode(', ',$href);
  };
  switch ($zdroj) {
  case 'C':                     // chlapi
    $qry= "SELECT * FROM ezer_ys.chlapi WHERE id_chlapi=$id ";
    $res= pdo_qry($qry);
    if ( $res && $c= pdo_fetch_object($res) ) {
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
    $resQ= pdo_qry($qryQ);
    if ( $resQ && ($q= pdo_fetch_object($resQ)) ) {
//       if ( $q->barva==1 ) {
//         // databáze CHLAPI
//         $qry= "SELECT * FROM ezer_ys.chlapi WHERE id_chlapi=$id ";
//         $res= pdo_qry($qry);
//         if ( $res && $c= pdo_fetch_object($res) ) {
//           $html.= "{$c->prijmeni} {$c->jmeno}<br>";
//           $html.= "{$c->ulice}, {$c->psc} {$c->obec}<br><br>";
//           if ( $c->telefon )
//             $html.= "Telefon: {$c->telefon}<br>";
//         }
//       }
//       else
      if ( $q->barva==4 ) {
        // kopie databáze DS = ds_osoba_copy
        $qry= "SELECT * FROM ds_osoba_copy WHERE id_osoba=$id ";
        $res= pdo_qry($qry);
        if ( $res && $c= pdo_fetch_object($res) ) {
          $html.= "{$c->prijmeni} {$c->jmeno}<br>";
          $html.= "{$c->ulice}, {$c->psc} {$c->obec}<br><br>";
          if ( $c->telefon )
            $html.= "Telefon: {$c->telefon}<br>";
        }
      }
      elseif ( $q->barva==2 ) {
        // databáze osob
        $qry= "SELECT role, prijmeni,jmeno,
            IF(kontakt,telefon,IFNULL(r.telefony,'?')) AS telefon,
            IF(adresa,o.ulice,IFNULL(r.ulice,'?')) AS ulice,
            IF(adresa,o.psc,IFNULL(r.psc,'?')) AS psc,
            IF(adresa,o.obec,IFNULL(r.obec,'?')) AS obec,
            IF(adresa,o.stat,IFNULL(r.stat,'?')) AS stat
          FROM osoba AS o LEFT JOIN tvori AS t USING (id_osoba)
          LEFT JOIN rodina AS r USING (id_rodina) WHERE id_osoba=$id
          ORDER BY t.role  LIMIT 1 ";
        $res= pdo_qry($qry);
        if ( $res && $c= pdo_fetch_object($res) ) {
          $html.= "{$c->prijmeni} {$c->jmeno}<br>";
          $html.= "{$c->ulice}, {$c->psc} {$c->obec}, {$c->stat}<br><br>";
          if ( $c->telefon )
            $html.= "Telefon: {$c->telefon}<br>";
        }
      }
      else {
        // databáze MS
        // SELECT vrací (_id,prijmeni,jmeno,ulice,psc,obec,email,telefon)
        $qry= $q->hodnota;
        $qry= mail2_sql_subst($qry);
        if ( strpos($qry,"GROUP BY") ) {
          if ( strpos($qry,"HAVING") )
            $qry= str_replace("HAVING","HAVING _id=$id AND ",$qry);
          else
            $qry= str_replace("GROUP BY","GROUP BY _id HAVING _id=$id AND ",$qry);
          // zatém jen pro tuto větev
          $res= pdo_qry($qry);
          while ( $res && ($c= pdo_fetch_object($res)) ) {
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
  case 'U8':                    // kněží, psycho
  case 'U5':                    // všichni přítomní vyjma pečounů
  case 'U6':                    // všichni tuzemští přítomní 
  case 'U7':                    // všichni zahraniční přítomní 
  case 'U1':                    // nesloužící účastníci akce
  case 'U2':                    // sloužící účastníci akce
  case 'U3':                    // dlužníci
  case 'U4':                    // divný OP
    $qry= "SELECT * FROM osoba WHERE id_osoba=$id ";
    $res= pdo_qry($qry);
    if ( $res && $c= pdo_fetch_object($res) ) {
      $html.= "{$c->id_osoba}: {$c->jmeno} {$c->prijmeni}<br>";
      $html.= "{$c->ulice}, {$c->psc} {$c->obec}<br><br>";
      if ( $c->telefony )
        $html.= "Telefon: {$c->telefony}<br>";
    }
    $prilohy= select('prilohy','dopis',"id_dopis=$id_dopis");
    $priloha= select('priloha','mail',"id_mail=$id_mail");
    // přílohy ke kontrole
    if ( $prilohy )
      $html.= "<br>Společné přílohy: ".$make_href($prilohy);
    if ( $priloha )
      $html.= "<br>Vlastní přílohy: ".$make_href($priloha);
    break;
  case 'G':                     // mail-list
    list($obsah,$prilohy,$komu)= select('obsah,prilohy,mailist.komu',
      'dopis JOIN mailist USING (id_mailist)',"id_dopis=$id_dopis");
    $priloha= select('priloha','mail',"id_mail=$id_mail");
    if ( $komu=='o' ) {
      $c= select("*",'osoba',"id_osoba=$id");
      $html.= "{$c->id_osoba}: {$c->jmeno} {$c->prijmeni}";
      $html.= $c->adresa ? "<br>{$c->ulice}, {$c->psc} {$c->obec}" : '';
      if ( $c->kontakt && $c->telefon ) $html.= $c->kontakt ? "<br>Telefon: {$c->telefon}<br>" : '';
    }
    else { // rodina
      $c= select("*",'rodina',"id_rodina=$id");
      $html.= "{$c->id_rodina}: {$c->nazev}";
      $html.= "<br>{$c->ulice}, {$c->psc} {$c->obec}";
      if ( $c->telefony ) $html.= "<br>Telefon: {$c->telefony}<br>";
    }
    // přílohy ke kontrole
    if ( $prilohy )
      $html.= "<br>Společné přílohy: ".$make_href($prilohy);
    if ( $priloha )
      $html.= "<br>Vlastní přílohy: ".$make_href($priloha);
    break;
  }
  return $html;
}
# ----------------------------------------------------------------------------------- mail2 mai_smaz
# smazání mailu v DOPIS a jeho rozesílání v MAIL
function mail2_mai_smaz($id_dopis) {  trace();
  query("DELETE FROM dopis WHERE id_dopis=$id_dopis ");
  query("DELETE FROM mail WHERE id_dopis=$id_dopis ");
  return true;
}
# ----------------------------------------------------------------------------------- mail2 mai_stav
# úprava stavu mailové adresy
# ZATIM BEZ: (maže maily - nutné zohlednit i id_clen==id_osoba aj.) včetně znovuzískání mailové adresy s karty účastníka
function mail2_mai_stav($id_mail,$stav) {  trace();

  list($id_dopis,$id_pobyt)= select("id_dopis,id_pobyt","mail","id_mail=$id_mail");
  $novy_mail= '';
//   if ( $id_pobyt) {
//     $oprava= mail2_mai_pocet($id_dopis,'U',$cond="id_pobyt=$id_pobyt");
//     $emaily= $oprava->_adresy[0];
//     $novy_mail= ",email='$emaily'";
//                                                   debug($oprava,"mail2_mai_stav:$emaily.");
//   }
  $qry= "UPDATE mail SET stav=$stav$novy_mail WHERE id_mail=$id_mail ";
  $res= pdo_qry($qry);
  if ( !$res ) fce_error("mail2_mai_stav: změna stavu mailu No.'$id_mail' se nepovedla");
  return true;
}
# ------------------------------------------------------------------------------ mail2 new_PHPMailer
# nastavení parametrů pro SMTP server podle user.options.smtp
# nebo přímo zadáním parametrů
function mail2_new_PHPMailer($smtp=null) {  
//  global $ezer_path_serv;
  global $ezer_root;
  $mail= null;
  if (!$smtp) {
    // získání parametrizace SMTP
    $idu= $_SESSION[$ezer_root]['user_id'];
    $i_smtp= sys_user_get($idu,'opt','smtp');
    $smtp_json= select1('hodnota','_cis',"druh='smtp_srv' AND data=$i_smtp");
    $smtp= json_decode($smtp_json);
    if ( json_last_error() != JSON_ERROR_NONE ) {
      fce_warning("chyba ve volbe SMTP serveru" . json_last_error_msg());
      goto end;
    }
    $smtp->files_path= __DIR__.'/../../files/setkani4';
//    debug($smtp,"mailer config");
  }
  // inicializace Ezer_PHPMailer
  global $abs_root;
  $server= "$abs_root/ezer".EZER_VERSION."/server";
  require_once "$server/ezer_mailer.php";
  $mail= new Ezer_PHPMailer($smtp);
end:  
  return $mail;
}
# -------------------------------------------------------------------------------- mail2 mai_sending
// y je paměť procesu, který bude krok za krokem prováděn 
// y.todo - celkový počet kroků
// y.done - počet provedených kroků 
// y.sent - počet skutečně odeslaných mailů
// y.error = text chyby, způsobí konec
function mail2_mai_sending($y) { 
  global $ezer_root;
  // získání případného omezení použitého SMTP
  $idu= $_SESSION[$ezer_root]['user_id'];
  $i_smtp= sys_user_get($idu,'opt','smtp');
  $max_per_day= select1('ikona','_cis',"druh='smtp_srv' AND data=$i_smtp");
  // pokud je y.todo=0 provede se inicializace procesu podle y.par
  if ( $y->todo==0 ) {
    $_SESSION[$ezer_root]['mail_par']= $y->par;
    $y->done= 0;
    $n= select('COUNT(*)','mail',"id_dopis={$y->par->id_dopis} AND stav IN (0,3)");
    $y->todo= $y->par->davka ? ceil($n/$y->par->davka) : 0;
    $y->last= 0; // poslední poslaný id_mail
    $y->sent= 0; // počet poslaných
    $y->error= '';
    unset($y->par);
  }
  if ( $y->error ) { goto end; }
  if ( $y->done >= $y->todo ) { $y->done= $y->todo; $y->msg= 'konec+'; goto end; }
  $par= (object)$_SESSION[$ezer_root]['mail_par'];
  // pokud by odeslání překročilo omezení ukonči je
   if ( $max_per_day && ($y->sent+$par->davka)>$max_per_day ) {
     $res->max= $max_per_day;
   } 
  // vlastní proces
  $res= mail2_mai_send($par->id_dopis,$par->davka,$par->from,$par->name,'',0,$par->foot);
  $y->done++;
  $y->sent= $res->_sent;
  // zpráva
  $y->msg= $y->done==$y->todo ? 'konec' : "ještě ".($y->todo-$y->done)." x {$par->davka}"; 
  // poslední mail pro refresh
  $y->last= $res->_last;
  if ( $res->_error ) {
    if ($res->_over_quota) {
      $y->error= "<b style='color:#700;background:#ff0'>Byla překročena kvóta pro odesílání GMailů. 
        Pokračujte zítra.</b>";
    }
    else {
      $y->error= $res->_html;
    }
    goto end;
  }
  // před skončením počkej 1s aby šlo velikostí dávky řídit zátěž
  sleep(1);
end:  
  return $y;
}
# ----------------------------------------------------------------------------------- mail2 mai_send
# ASK
# odešli dávku $kolik mailů ($kolik=0 znamená testovací poslání)
# $from,$fromname = From,ReplyTo
# $test = 1 mail na tuto adresu (pokud je $kolik=0)
# pokud je definováno $id_mail s definovaným text MAIL.body, použije se - jinak DOPIS.obsah
# pokud je definováno $foot tj. patička, připojí se na konec
# použije se SMTP server podle SESSION
function mail2_mai_send($id_dopis,$kolik,$from,$fromname,$test='',$id_mail=0,$foot='') { trace();
  $TEST= 0;
  // připojení případné přílohy
  $attach= function($mail,$fname) {
    global $ezer_root;
    if ( $fname ) {
      foreach ( explode(',',$fname) as $fnamesb ) {
        list($fname)= explode(':',$fnamesb);
        $fname= trim($fname);
        $has_dir= strrpos($fname,'/');
        $fpath= $has_dir ? $fname : "docs/$ezer_root/$fname";
        $mail->AddAttachment($fpath);
  } } };
  //
  $result= (object)array('_error'=>0,'_sent'=>0,'_over_quota'=>0);
  $pro= '';
  // přečtení rozesílaného mailu
  $qry= "SELECT * FROM dopis WHERE id_dopis=$id_dopis ";
  $res= pdo_qry($qry,1,null,1);
  $d= pdo_fetch_object($res);
  // napojení na mailer
  $html= '';
  // poslání mailů
  try {
    $mail= mail2_new_PHPMailer();
    display("mail2_new_PHPMailer() ok");
  
    if ( $mail->Ezer_error ) { 
      $result->_html.= "<br><b style='color:#700'>tato odesílací adresa nelze použít ($mail->Ezer_error)</b>";
      $result->_error= 1;
      goto end;
    }
    $mail->AddReplyTo($from);
    $mail->SetFrom($mail->From,$fromname);
    $mail->Subject= $d->nazev;
    $attach($mail,$d->prilohy);
  }
  catch (Exception $e) {
    $result->_html.= "<br><b style='color:#700'>tato odesílací adresa nelze použít (".$e->getMessage().")</b>";
    $result->_error= 1;
    goto end;
  }
  if ( $kolik==0 ) { // ---------------------- testovací mail
    // testovací poslání sobě
    if ( $id_mail ) {
      // přečtení personifikace rozesílaného mailu
      $qry= "SELECT * FROM mail WHERE id_mail=$id_mail ";
      $res= pdo_qry($qry,1,null,1);
      $m= pdo_fetch_object($res);
      if ( $m->body ) {
        $obsah= $m->body;
        $pro= "s personifikací pro {$m->email}";
      }
      else {
        // jinak obecný z DOPIS
        $obsah= $d->obsah;
        $pro= '';
      }
      $attach($mail,$m->priloha);
    }
    $mail->Body= $obsah . $foot;
    $mail->AddAddress($test);   // pošli sám sobě
    // pošli
    if ( $TEST ) {
      $ok= 1;
    }
    else {
      // zkus poslat mail
      try { $ok= $mail->Ezer_Send(); } 
      catch(Exception $e) { 
        $ok= false; 
      }
    }
    if ( $ok=='ok'  )
      $html.= "<br><b style='color:#070'>Byl odeslán mail na $test $pro - je zapotřebí zkontrolovat obsah</b>";
    else {
      $ze= isset($mail->Username) ? $mail->Username : '?';
      $html.= "<br><b style='color:#700'>Při odesílání mailu přes '$ze' $ok</b>";
      display("Send failed: $ok<br>username={$mail->Username}");
      $result->_error= 1;
    }
//                                                 display($html);
  }
  else { // ---------------------------------- poslání dávky $kolik mailů
    $n= $nko= 0;
    $qry= "SELECT * FROM mail WHERE id_dopis=$id_dopis AND stav IN (0,3) ORDER BY email";
    $res= pdo_qry($qry);
    while ( $res && ($z= pdo_fetch_object($res)) ) {
      // posílej mail za mailem
      if ( $n>=$kolik ) break;
      $result->_last= $z->id_mail; // pro refresh
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
        $attach($mail,$d->prilohy);
        $attach($mail,$z->priloha);
      }
      $mail->Body= $obsah . $foot;
      foreach(preg_split("/,\s*|;\s*|\s+/",trim($z->email," ,;"),-1,PREG_SPLIT_NO_EMPTY) as $adresa) {
        if ( !$i++ )
          $mail->AddAddress($adresa);   // pošli na 1. adresu
        else                            // na další jako kopie
          $mail->AddCC($adresa);
      }
       if ( $TEST ) {
         $ok= 'ok';
                                          display("jakože odeslaný mail pro $adresa");
       }
       else {
        // zkus poslat mail
        try { $ok= $mail->Ezer_Send(); } catch(Exception $e) { $ok= 'CHYBA nezachycená'; }
      }
      if ( $ok!='ok' ) {
        $ident= $z->id_clen ? $z->id_clen : $adresa;
        $html.= "<br><b style='color:#700'>neodeslání mailu pro $ident - $ok</b>";
        $result->_error= 1;
        $nko++;
      }
      else {
        $n++;
      }
      // zapiš výsledek do tabulky
      $stav= $ok=='ok' ? 4 : 5;
      $msg= $ok=='ok' ? '' : $ok;
      if ($msg && preg_match("/Daily user sending quota exceeded/",$msg)) {
        $result->_over_quota= 1;
      }
      else {
        $qry1= "UPDATE mail SET stav=$stav,msg=\"$msg\" WHERE id_mail={$z->id_mail}";
        pdo_qry($qry1);
      }
      // po chybě přeruš odesílání
      if ( $ok!='ok' ) break;
    }
    $result->_sent= $n;
    $html.= "<br><b style='color:#070'>Bylo odesláno $n emailů ";
    $html.= $nko ? "s $nko chybami " : "bez chyb";
    $html.= "</b>";
  }
  // zpráva o výsledku
  $result->_html= $html;
//                                                 debug($result,"mail2_mai_send");
end:  
  return $result;
}
# --------------------------------------------------------------------------------- mail2 mai_attach
# přidá další přílohu k mailu (soubor je v docs/$ezer_root)
function mail2_mai_attach($id_dopis,$f) { trace();
  // nalezení záznamu v tabulce a přidání názvu souboru
  $names= select('prilohy','dopis',"id_dopis=$id_dopis");
  $names= ($names ? "$names," : '')."{$f->name}:{$f->size}";
  query("UPDATE dopis SET prilohy='$names' WHERE id_dopis=$id_dopis");
  return 1;
}
# ----------------------------------------------------------------------------- mail2 mai_detach_all
# odstraní všechny přílohy mailu
function mail2_mai_detach_all($id_dopis) { trace();
  query("UPDATE dopis SET prilohy='' WHERE id_dopis=$id_dopis");
  return 1;
}
# --------------------------------------------------------------------------------- mail2 mai_detach
# odebere soubor z příloh
function mail2_mai_detach($id_dopis,$name) { trace();
  // nalezení záznamu v tabulce a odebrání názvu souboru
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
# =====================================================================================> . SQL maily
# vytváření a testování SQL dotazů pro definici mailů
# ------------------------------------------------------------------------------------ mail2 copy_ds
# ASK - kopie tabulky SETKANI.DS_OSOBA do EZER_YS.DS_OSOBA_COPY
# vrací {id_cis,data,query}
function mail2_copy_ds() {  trace();
  global $ezer_db;
  $html= 'kopie se nepovedla';
  // smazání staré kopie
  $qry= "TRUNCATE TABLE /*ezer_ys.*/ds_osoba_copy ";
  $ok= pdo_qry($qry);
  if ( $ok ) {
    $html= "inicializace ds_osoba_copy ok";
    ezer_connect('setkani');
    $qrs= "SELECT * FROM ds_osoba WHERE email!='' ";
    $res= pdo_qry($qrs);
    while ( $res && ($s= pdo_fetch_object($res)) ) {
//                                                         debug($s,'s',(object)array('win1250'=>1));
      $ids= $vals= $del= '';
      foreach($s as $id=>$val) {
        $ids.= "$del$id";
        $vals.= "$del'".pdo_real_escape_string(wu($val))."'";
        $del= ',';
      }
      $qry= "INSERT INTO /*ezer_ys.*/ds_osoba_copy ($ids) VALUES ($vals)";
      $ok= pdo_query($qry,$ezer_db['ezer_ys'][0]);
//                                                         display("$ok:$qry");
      if ( !$ok ) {
        $html.= "\nPROBLEM ".pdo_error();
      }
    }
    if ( $ok ) {
      $html.= "\nkopie do ds_osoba_copy ok";
    }
  }
  return $html;
}
# ------------------------------------------------------------------------------------ mail2 sql_new
# ASK - vytvoření SQL dotazů pro definici mailů
# vrací {id_cis,data,query}
function mail2_sql_new() {  #trace();
  $id= select("MAX(0+id_cis)","_cis","druh='db_maily_sql'");
  $data= select("MAX(0+data)","_cis","druh='db_maily_sql'");
  $result= (object)array(
    'id'=>$id+1, 'data'=>$data+1,
    'qry'=>"SELECT id_... AS _id,prijmeni,jmeno,ulice,psc,obec,email,telefon FROM ...");
  return $result;
}
# ---------------------------------------------------------------------------------- mail2 sql_subst
# ASK - parametrizace SQL dotazů pro definici mailů, vrací modifikovaný dotaz
# nebo pokud je prázdný tak přehled možných parametrizací dotazu
function mail2_sql_subst($qry='') {  trace();
  global $USER;
  $org= $USER->org==1 ? "1 /*YMCA Setkání*/" : "2 /*YMCA Familia*/";
  // parametry
  $parms= array (
   'org'   => array ($org,'moje organizace'),
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
# ------------------------------------------------------------------------------------ mail2 sql_try
# ASK - vytvoření SQL dotazů pro definici mailů
# vrací {id_cis,data,query}
function mail2_sql_try($qry,$vsechno=0,$export=0) {  trace();
  $html= $head= $tail= '';
  $emails= array();
  try {
    // export?
    $href= '';
    if ( $export ) {
      $fname= "skupina_".date("Ymd_Hi").".csv";
      $fpath= "docs/$fname";
      $flds= "příjmení jméno;email;telefon;ulice, psč obec;v;w;x;y;z";
      $f= @fopen($fpath,'w');
      if ( !$f ) fce_error("soubor '$fpath' nelze vytvořit");
      fputs($f,chr(0xEF).chr(0xBB).chr(0xBF));
      fputcsv($f,explode(';',$flds),';','"');
      $href= ". Seznam <a href='$fpath'>$fname</a> lze stáhnout do Excelu ve formátu CSV";
    }
    // substituce
    $qry= mail2_sql_subst($qry);
    // dotaz
    $time_start= getmicrotime();
    $res= pdo_qry($qry);
    $time= round(getmicrotime() - $time_start,4);
    if ( !$res ) {
      $html.= "<span style='color:darkred'>ERROR ".pdo_error()."</span>";
    }
    else {
      $nmax= $vsechno ? 99999 : 200;
      $num= pdo_num_rows($res);
      $head.= "Výběr obsahuje <b>$num</b> emailových adresátů, nalezených během $time ms, ";
      $head.= $num>$nmax ? "následuje prvních $nmax adresátů" : "následují všichni adresáti";
      $tail.= "<br><br><table class='stat'>";
      $tail.= "<tr><th>prijmeni jmeno</th><th>email</th><th>telefon</th>
        <th>ulice psc obec</th><th>v</th><th>w</th><th>x</th><th>y</th><th>z</th></tr>";
      $n= $nmax;
      while ( $res && ($c= pdo_fetch_object($res)) ) {
        if ( $n ) {
          $tail.= "<tr><td>{$c->prijmeni} {$c->jmeno}</td><td>{$c->_email}</td><td>{$c->telefon}</td>
            <td>{$c->ulice}, {$c->psc} {$c->obec}</td>
            <td>{$c->_v}</td><td>{$c->_w}</td><td>{$c->_x}</td><td>{$c->_y}</td><td>{$c->_z}</td></tr>";
          if ( $export ) {
            fputcsv($f,array("$c->prijmeni $c->jmeno",$c->_email,$c->telefon,
                "{$c->ulice}, {$c->psc} {$c->obec}",$c->_v,$c->_w,$c->_x,$c->_y,$c->_z),';','"');
          }
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
    if ( $export ) {
      fclose($f);  
    }
  }
  catch (Exception $e) { $html.= "<span style='color:red'>FATAL ".pdo_error()."</span>";  }
  $head.= "<br>Adresáti mají <b>".count($emails)."</b> různých emailových adres $href";
  $html= $html ? $html : $head.$tail;
//                                                 debug($emails,"db_mail_sql_try");
  return $html;
}
# =================================================================================> . Generátor SQL
# ---------------------------------------------------------------------------------- mail2 gen_excel
# vygeneruje do Excelu seznam adresátů
function mail2_gen_excel($gq,$nazev) { trace();
  global $ezer_root, $ezer_version;
  require_once "ezer$ezer_version/server/vendor/autoload.php";
  $href= "CHYBA!";
  // úprava dotazu
  $gq= str_replace('&gt;','>',$gq);
  $gq= str_replace('&lt;','<',$gq);
//                                                         display($gq);
  // export do Excelu
  // zahájení exportu
  $ymd_hi= date('Ymd_Hi');
  $dnes= date('j. n. Y');
  $t= "mail-list $nazev, stav ke dni $dnes";
  $file= "maillist_$ymd_hi";
  $type= 'xls';
  $par= (object)array('dir'=>$ezer_root,'file'=>$file,'type'=>$type,'title'=>$t,'color'=>'aac0cae2');
  $clmns= "_name:příjmení jméno,_email:email,_ulice:ulice,_psc:PSČ,_obec:obec,_stat:stát,_ucasti:účastí";
  if ( preg_match("/iniciace/i",$gq) ) {
    // přidání sloupce iniciace, pokud se vyskytuje v dotazu
    $clmns.= ",iniciace:iniciace";
  }
  $clmns.= ",_id:ID";
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
  $gr= @pdo_query($gq);
  if ( !$gr ) { fce_warning(pdo_error()); goto end; }
  while ( $gr && ($g= pdo_fetch_object($gr)) ) {
//                                                 display('');
    foreach ($g as $f => $val) {
      if ( in_array($f,$fields) ) {
        $a= $val;
        if ( isset($pipe[$f]) ) $a= $pipe[$f]($a);
        $values[$f]= $a;
                                                display_("$a ");
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
# --------------------------------------------------------------------------------- mail2 vzor_pobyt
# pošle mail daného typu účastníkovi pobytu - zatím typ=potvrzeni_platby
#                                                                         !!! + platba souběžné akce
function mail2_vzor_pobyt2($id_pobyt,$typ,$u_poradi,$from,$vyrizuje,$varianta,$poslat=0) {
//  global $ezer_root;
  $ret= (object)array();

  // načtení a kontrola pobytu + mail + nazev akce
  $p= (object)array();
  $rm= pdo_qry("
    SELECT id_uhrada,
     IFNULL(u_castka,0),u_datum,u_stav,
     GROUP_CONCAT(DISTINCT IF(o.kontakt,o.email,'')),IFNULL(GROUP_CONCAT(DISTINCT r.emaily),''),
     a.nazev,a.access,a.hnizda,p.hnizdo
    FROM pobyt AS p
    LEFT JOIN uhrada AS u USING (id_pobyt)
    JOIN akce AS a ON p.id_akce=a.id_duakce
    LEFT JOIN akce AS x ON x.id_hlavni=a.id_duakce
    JOIN spolu AS s USING(id_pobyt)
    JOIN osoba AS o ON s.id_osoba=o.id_osoba
    LEFT JOIN tvori AS t ON t.id_osoba=o.id_osoba AND IF(p.i0_rodina,t.id_rodina=p.i0_rodina,1)
    LEFT JOIN rodina AS r USING (id_rodina)
    WHERE id_pobyt=$id_pobyt AND u_poradi=$u_poradi AND IFNULL(t.role IN ('a','b'),true)
  ");
  list($id_uhrada,$castka,$dne,$potvrzeno,
    $omaily,$rmaily,$p->platba_akce,$access,$hnizda,$hnizdo)= pdo_fetch_row($rm);
  if ( !$id_uhrada ) { $ret->err= "CHYBA platba č.$u_poradi neexistuje"; goto end; }
  if ( !$castka ) { $ret->err= "CHYBA: není zapsána částka"; goto end; }
  if ( $dne=='0000-00-00' ) { $ret->err= "CHYBA: není zapsáno datum platby"; goto end; }
  if ( $castka && $potvrzeno==3 ) { $ret->err= "CHYBA: platba již byla potvrzena"; goto end; }
//  }

  // naplnění proměnných mailu
  $p->platba_den= sql_date1($dne);
  $p->platba_castka= number_format($castka, 0, '.', '&nbsp;')."&nbsp;Kč";
  $p->vyrizuje= $vyrizuje;
  // doplnění názvu hnízda, má-li smysl
  if ($hnizda && $hnizdo) {
    $hnizda= explode(',',$hnizda);
    if (isset($hnizda[$hnizdo-1])) {
      $p->platba_akce.= ", hnízdo {$hnizda[$hnizdo-1]}";
    }
  }
//  list($nazev,$obsah,$vars)=
//     select('nazev,obsah,var_list','dopis',"typ='potvrzeni_platby' AND access=$access");

  // načtení vzoru dopisu - verze podle $varianta
  $vzor= array();
  $rv= pdo_qry("SELECT nazev,obsah,var_list FROM dopis WHERE typ='potvrzeni_platby' AND access=$access");
  while ($rv && ($v= pdo_fetch_row($rv))) {
    $vzor[]= $v;  
  } 
//  debug($vzor);
  if (!count($vzor)) { $ret->err= "CHYBA: nebyl nalezen vzor dopisu 'potvrzeni_platby' "; goto end; }
  else if ($varianta>count($vzor)) $varianta= 1; // cyklické opakování
  $ret->next= $varianta+1;
  list($nazev,$obsah,$vars)= $vzor[$varianta-1];
  // personifikace
  foreach ( explode(',',$vars) as $var ) {
    $var= trim($var);
    $obsah= str_replace('{'.$var.'}',$p->$var,$obsah);
  }
  // extrakce adresy
  $maily= trim(str_replace(';',',',"$omaily,$rmaily")," ,");
                                                              display("maily=$maily");
  if ( !$maily ) { $ret->err= "CHYBA účastníci nemají uvedené maily"; goto end; }
  $report= "<hr>Od:$vyrizuje &lt;$from&gt;<br>Komu:$maily<br>Předmět:$nazev<hr>$obsah";

  if ( $poslat ) {
    // poslání mailu - při úspěchu zápis o potvrzení
    $mail= mail2_new_PHPMailer();
    if ( $mail->Ezer_error ) { 
      $ze= isset($mail->Username) ? $mail->Username : '?';
      $ret->err= "CHYBA při odesílání mailu z '$ze' - odesílací adresa nelze použít ($mail->Ezer_error)";
      goto end;
    }
    $mail->AddReplyTo($from);
    $mail->SetFrom($mail->From,$vyrizuje);
    foreach(preg_split("/,\s*|;\s*|\s+/",trim($maily," ,;"),-1,PREG_SPLIT_NO_EMPTY) as $adresa) {
      $mail->AddAddress($adresa);   // pošli na 1. adresu
    }
    $mail->Subject= $nazev;
    $mail->Body= $obsah;
    $ok= $mail->Ezer_Send();
    if ( $ok=='ok' ) {
      // zápis o potvrzení
      $ret->msg= "Byl odeslán mail$report";
      query("UPDATE uhrada SET u_stav=3 WHERE id_uhrada=$id_uhrada");
    }
    else {
      $ze= isset($mail->Username) ? $mail->Username : '?';
      $ret->err= "CHYBA při odesílání mailu z '$ze' $ok";
      goto end;
    }
  }
  else {
    $ret->msg= "Je připraven mail - mám ho poslat?$report";
    $ret->butt= "poslat:send,neposílat:quit,jiný dopis:next";
  }
end:
  return $ret;
}
# =======================================================================================> . mailist
# ---------------------------------------------------------------------------------- mail2 lst_using
# vrátí informaci o použití mailistu
function mail2_lst_using($id_mailist) {
  $dopisy= $poslane= $neposlane= $err= 0;
  $rs= pdo_qry("
    SELECT COUNT(DISTINCT id_dopis) AS _dopisy,SUM(IF(m.stav=4,1,0)) AS _poslane,
      IF(sexpr LIKE '%access%',0,1) AS _ok
    FROM mailist AS l
    LEFT JOIN dopis AS d USING (id_mailist)
    LEFT JOIN mail AS m USING (id_dopis)
    WHERE id_mailist=$id_mailist
    GROUP BY id_dopis");
  while ($rs && ($s= pdo_fetch_object($rs))) {
    $dopisy= $s->_dopisy;
    $poslane+= $s->_poslane ? 1 : 0;
    $neposlane+= $s->_poslane ? 0 : 1;
    $err+= $s->_ok;
  }
  $html= "Použití: v $dopisy dopisech, z toho <br>$poslane rozeslané a $neposlane nerozeslané";
  $html.= $err ? "<br><br><span style='background-color:yellow'>POZOR - nutno znovu uložit</span>" : '';
  return $html;
}
# ------------------------------------------------------------------------------------ mail2 mailist
# vrátí daný mailist ve tvaru pro selects
# pokud je uvedeno par.typ='o' pro osoby, 'r' pro rodiny
function mail2_mailist($access,$par=null) {
  $sel= '';
  $AND=  $par && $par->komu ? "AND komu='{$par->komu}'" : '';
  $AND.= $par && $par->ucel ? "AND ucel LIKE '{$par->ucel}'" : '';
  $mr= pdo_qry("SELECT id_mailist,ucel,access FROM mailist WHERE access&$access $AND 
    ORDER BY UPPER(ucel)");
  while ($mr && ($m= pdo_fetch_object($mr))) {
    $a= $m->access;
//    $css= $a==1 ? 'ezer_ys' : ($a==2 ? 'ezer_fa' : ($a==3 ? 'ezer_db' : ''));
    $css= ($a&1 && $a&2) ? 'ezer_db' : ($a&2 ? 'ezer_fa' : ($a&1 ? 'ezer_ys' : ''));
    if ($a&64) $css.= " ezer_ds";
    $sel.= ",{$m->ucel}:{$m->id_mailist}:$css";
  }
  display("SELECTS:$sel");
  return $sel ? substr($sel,1) : '';
}
# --------------------------------------------------------------------------------- mail2 lst_delete
# ASK
# zjisté možnost smazání mailistu (to_delete=0) tzn. že na něj není vázán žádný dopis
# a pro to_delete=1 jej smaže
function mail2_lst_delete($id_mailist,$to_delete=0) {
  $ret= (object)array('ok'=>0,'msg'=>'');
  if ( !$to_delete ) {
    if ( $id_mailist<=3 ) {
      $ret->msg= "testovací mail-listy nelze smazat";
    }
    else {
      $n= select('COUNT(*)','mailist JOIN dopis USING (id_mailist)',"id_mailist=$id_mailist");
      if ( $n )
        $ret->msg= "nelze smazat, je použit v $n dopisech
          <br>(nejprve je třeba smazat všechny dopisy)";
      else
        $ret->ok= 1;
    }
  }
  else {
    $ret->ok= query("DELETE FROM mailist WHERE id_mailist=$id_mailist");
  }
  return $ret;
}
# --------------------------------------------------------------------------------- mail2 lst_access
# vrátí údaje daného maillistu (ZRUŠENO: s provedenou substitucí podle access uživatele)
function mail2_lst_access($id_mailist) {  trace();
  global $USER;                                         // debug($USER);
  $ml= select_object('*','mailist',"id_mailist=$id_mailist");
//   if ( !strpos($ml->sexpr,'[HAVING_ACCESS]') ) {
//     fce_warning("dotaz zatím není uzpůsoben pro obě databáze - stačí jej znovu uložit");
//     $ml->warning= 1;
//     goto end;
//   }
  $ml->sexpr= str_replace('&lt;','<',str_replace('&gt;','>',$ml->sexpr));
//   // doplnění práv uživatele
//   $ml->sexpr= str_replace('[HAVING_ACCESS]',"HAVING o.access&{$USER->access}",$ml->sexpr);
end:
  return $ml;
}
# --------------------------------------------------------------------------- mail2 lst_confirm_spec
# spočítá maily podle daného maillistu
function mail2_lst_confirm_spec($id_mailist,$id_dopis) {  trace();
  $ret= (object)array('specialni'=>0, 'prepsat'=>'', 'pocet'=>'');
  // speciální?
  list($ret->specialni,$qry)= select('specialni,sexpr','mailist',"id_mailist=$id_mailist");
  if ( !$ret->specialni ) goto end;
  // jsou už vygenerované maily
  $ret->prepsat= select('COUNT(*)','mail',"id_dopis=$id_dopis")
    ? "Opravdu přepsat předchozí maily?" : '';
  // počet nově vygenerovaných
  $res= pdo_qry($qry);
  $ret->pocet= "Opravdu vygenerovat maily na ".pdo_num_rows($res)." adres?";
end:
  return $ret;
}
# ----------------------------------------------------------------------------- mail2 lst_regen_spec
# přegeneruje 1 daný mail s nastaveným specialni a parms
# pokud je definováno corr přepíše proměnné v dopise
# corr= {darce}
function mail2_lst_regen_spec($id_dopis,$id_mail,$id_osoba,$corr=null) {  debug($corr,"mail2_lst_regen_spec");
  $ret= (object)array('msg'=>'','err'=>'');
  $id_mailist= select('id_mailist','dopis',"id_dopis=$id_dopis");
  $ml= mail2_lst_access($id_mailist);
  $parms= json_decode($ml->parms);
  switch ($parms->specialni) {
  case 'potvrzeni':
    $rok= date('Y')+$parms->rok;
    $qry= $ml->sexpr;
    $qry= str_replace("GROUP BY","AND id_osoba=$id_osoba GROUP BY",$qry);
    $os= pdo_qry($qry);
    if ( !$os ) {  $ret->msg= "přegenerování se nepovedlo"; goto end; }
    $o= pdo_fetch_object($os);
    // přegeneruj PDF s potvrzením do $x->path
    if ($corr) {
      // uplatni korekci, pokud je definována 
      $o->sex= $corr->sex;
      $o->jmeno= $corr->jmeno;
      $o->prijmeni= $corr->prijmeni;
      $o->_ulice= $corr->_ulice;
      $o->_psc= $corr->_psc;
      $o->_obec= $corr->_obec;
    }
    $x= mail2_mai_potvr("Pf",$o,$rok);
    // oprav mail
    $rs= pdo_qry("UPDATE mail SET stav=3,email='{$o->_email}' WHERE id_mail=$id_mail");
    $num+= pdo_affected_rows($rs);
    // informační zpráva
    $ret->msg= "Mail pro {$o->_name} včetně potvrzení {$x->fname} byl přegenerován";
    break;
  default:
    fce_error("není implementováno");
  }
end:
//                                                         debug($ret,"mail2_lst_posli_spec end");
  return $ret;
}
# ----------------------------------------------------------------------------- mail2 lst_posli_spec
# PROCESS
# vygeneruje sadu mailů podle daného maillistu s nastaveným specialni a parms
# davka = {todo,done,step,msg,error,mails} pokud done=0 jde o zahájení procesu
function mail2_lst_posli_spec($davka) {  trace();
  global $ezer_path_docs;
  $davka->msg= '';
  $id_mailist= select('id_mailist','dopis',"id_dopis={$davka->dopis}");
  $ml= mail2_lst_access($id_mailist);
  $parms= json_decode($ml->parms);
  switch ($parms->specialni) {
  case 'potvrzeni':
    $rok= date('Y')+$parms->rok;
    if ($davka->todo==0) { // --------------------------------- zahájení
      // smaž starý seznam a stará potvrzení
      pdo_qry("DELETE FROM mail WHERE id_dopis={$davka->dopis}");
      if ( is_dir("$ezer_path_docs/db2") ) {
        $files= glob("$ezer_path_docs/db2/potvrzeni_{$rok}*.pdf");
        foreach ($files as $file) {
          unlink($file);
        }
      }
      // a zjisti celkový počet
      $davka->done= 0;
      $davka->error= '';
      $os= pdo_qry($ml->sexpr);
      while ($os && ($o= pdo_fetch_object($os))) {
        $davka->todo++;
      }
      if (!$davka->todo) { $davka->msg= "nejsou žádná potrzení"; break; }
      // rychle skončíme, aby se nastavil termometr
    }
    else { // ------------------------------------------------- generování STEP potvrzení
      $step= 0;
      // projdi všechny relevantní dárce podle dotazu z maillistu
      // a vytvoř davka.step ještě nevytvořených mailů
      $os= pdo_qry($ml->sexpr);
      while ($os && ($o= pdo_fetch_object($os))) {
        // pokud již má mail, přeskoč ho
        $ma= select('id_mail','mail',"id_dopis={$davka->dopis} AND id_clen={$o->_id}");
        if ($ma) continue;
        // pokud nema, pokračujeme 
        $email= $o->_email ?: '*';
        // vygeneruj PDF s potvrzením do $x->path
        $x= mail2_mai_potvr("Pf",$o,$rok);
        $pdf= $x->fname;
        // vlož mail
        query("INSERT mail (id_davka,znacka,stav,id_dopis,id_clen,email,priloha)
               VALUE (1,'@',0,{$davka->dopis},{$o->_id},'$email','$pdf')");
        if ( preg_match("/[*]/",$email) ) 
          $davka->msg.= " {$o->jmeno} {$o->prijmeni} nemá mail ";
        $step++;
        $davka->done++;
        // pokud jsme neprošli davka.step osob, pokračujeme 
        if ($step >= $davka->step) {
          break;
        }
      }
      if ($davka->done>=$davka->todo) {
        // oprav počet v DOPIS
        query("UPDATE dopis SET pocet={$davka->done} WHERE id_dopis={$davka->dopis}");
        // informační zpráva
        $davka->msg= "Bylo vygenerováno {$davka->done} mailů {$davka->msg}";
      }
    }
    break;
  default:
    fce_error("není implemntováno");
  }
//                                                         debug($ret,"mail2_lst_posli_spec end");
  return $davka;
}
# ----------------------------------------------------------------------------------- mail2 lst_read
# převod parm do objektu
function mail2_lst_read($parm) { //trace();
//  global $json;
  $obj= json_decode($parm);
  $obj= isset($obj->ano_akce) ? $obj : 0;
//                                                 debug($obj,"mail2_lst_read($parm)");
  return $obj;
}
# ----------------------------------------------------------------------------------- kasa send_mail
# pošle systémový mail, pokud není určen adresát či odesílatel jde o mail správci aplikace
# $to může být seznam adres oddělený čárkou
function kasa_send_mail($subject,$html,$from='',$to='',$fromname='',$typ='',$replyto='',$lognote='') { //trace();
  
  global $abs_root;
  $msg= 'ok';
  $smtp= (object)[
    'Host'       => 'smtp.gmail.com',
    'Username'   => 'answer@setkani.org',
    'files_path' => __DIR__.'/../../files/setkani4'
  ];
  require_once "$abs_root/ezer".EZER_VERSION."/server/ezer_mailer.php";
  $mail= new Ezer_PHPMailer($smtp);
  if ( $mail->Ezer_error ) { 
    $msg= $mail->Ezer_error;
  }
  else {
    $mail->SetFrom($mail->From,$fromname);
    $mail->AddReplyTo($from);
    foreach (explode(',',$to) as $to1) {
      $mail->AddAddress($to1);
    }
    $mail->Subject= $subject;
    $mail->Body= $html;
    // pošli mail
    try { 
      $msg= $mail->Ezer_Send();     
    } 
    catch(Exception $e) { 
      $msg= "CHYBA odesílání mailu:" . $e->getMessage(); 
    }
  }
  // zápis do stamp
  $dt= date('Y-m-d H:i:s');
  $msg= strtr($msg,["'"=>"&spos;"]);
  if ($lognote) $subject.= " ... $lognote";
  query("INSERT INTO stamp (typ,kdy,pozn) VALUES ('$typ','$dt','$subject - $msg')");
  return $msg=='ok';
}
