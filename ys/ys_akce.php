<?php # (c) 2009-2010 Martin Smidek <martin@smidek.eu>
# ================================================================================================== SYSTEM-DATA
# -------------------------------------------------------------------------------------------------- akce_foxpro_data
# dokončení transformace z my_mysql.prg naplněním id_pary
function akce_foxpro_data() {  #trace('');
  $n= 0;
  // přidání id_pary
  $qry= "SELECT id_pary,cislo FROM ms_pary ";
  $res= mysql_qry($qry);
  while ( $res && ($x= mysql_fetch_object($res)) ) {
    $n++;
    // doplň id_pary do ms_kurs a ms_deti
    mysql_qry("UPDATE ms_kurs SET id_pary={$x->id_pary} WHERE cislo={$x->cislo} ");
    mysql_qry("UPDATE ms_deti SET id_pary={$x->id_pary} WHERE cislo={$x->cislo} ");
    mysql_qry("UPDATE ms_kursdeti SET id_pary={$x->id_pary} WHERE cislo={$x->cislo} ");
  }
  $html= "Do tabulek ms_kurs, ms_deti, ms_kursdeti byly {$n}x přidány hodnoty klíče id_pary";
  // přidání id_akce
  $n= 0;
  $qry= "SELECT id_akce,source,akce FROM ms_akce ";
  $res= mysql_qry($qry);
  while ( $res && ($x= mysql_fetch_object($res)) ) {
    $n++;
    // doplň id_pary do ms_kurs a ms_deti
    mysql_qry("UPDATE ms_kurs SET id_akce={$x->id_akce} WHERE akce={$x->akce} AND source='{$x->source}'");
    mysql_qry("UPDATE ms_kursdeti SET id_akce={$x->id_akce} WHERE akce={$x->akce} AND source='{$x->source}'");
    mysql_qry("UPDATE uakce SET id_akce={$x->id_akce} WHERE ms_akce={$x->akce} AND ms_source='{$x->source}'");
  }
  $html.= "<br>Do tabulek ms_kurs, ms_kursdeti, uakce byly {$n}x přidány hodnoty klíče id_akce";
  // oprava dětí
  $n= 0;
  $qry= "SELECT id_deti,id_pary,jmeno FROM ms_deti ";
  $res= mysql_qry($qry);
  while ( $res && ($x= mysql_fetch_object($res)) ) {
    $n++;
    // doplň id_deti do ms_kursdeti
    mysql_qry("UPDATE ms_kursdeti SET id_deti={$x->id_deti} WHERE id_pary={$x->id_pary} AND jmeno='{$x->jmeno}'");
  }
  $html.= "<br>Do tabulky ms_kursdeti byly {$n}x přidány hodnoty klíče id_deti";
  return $html;
}
# ================================================================================================== VÝPISY
# -------------------------------------------------------------------------------------------------- akce_sestava
# generování sestavy pro účastníky $akce
#   $typ = jeden | par
#   $fld = seznam položek s prefixem
#   $cnd = podmínka
function akce_sestava($akce,$par,$title,$vypis,$export=false) {
  $result= (object)array();
  $typ= $par->typ;
  $tit= $par->tit;
  $fld= $par->fld;
  $cnd= $par->cnd;
  $html= '';
  $href= '';
  $n= 0;
  // dekódování parametrů
  $tits= explode(',',$tit);
  $join= "JOIN ms_pary AS mp ON mp.id_pary=mk.id_pary ";
  $group= '';
  $fields= ",CONCAT(jmeno_m,' a ',jmeno_z) AS jmena";
  $order= 'mp.jmeno';
  switch ($typ) {
  case 'j':                             // jednotlivci
    $fn= explode(';',$fld);
    $flds= array(explode(',',$fn[0]),explode(',',$fn[1]));
    break;
  case 'p':                             // páry
    $flds= explode(',',$fld);
    break;
  case 'd':                             // děti
    $fields= ",md.jmeno AS jmeno_d";
    $join.= "JOIN ms_deti AS md ON md.id_pary=mp.id_pary
             JOIN ms_kursdeti AS mkd ON mkd.id_deti=md.id_deti AND mkd.id_akce=mk.id_akce ";
//     $group= "GROUP BY mp.id_pary";
    $flds= explode(',',$fld);
    break;
  }
  $cond= 1;
  switch ($cnd) {
  case 'vps':                           // jen VPS
    $cond= 'funkce=1';
    break;
  case 2:                               // nikoliv VPS
    $cond= 'funkce=0';
    break;
  }
  // získání dat - podle $kdo
  $clmn= array();
//   $qry= "SELECT *
//          FROM ms_kurs AS mk
//          JOIN ms_akce AS ma ON ma.id_akce=mk.id_akce
//          JOIN ms_pary AS mp ON mp.id_pary=mk.id_pary
//          LEFT JOIN ms_kurs AS mks ON mks.id_akce=mk.id_akce AND mks.skupina=mk.skupina
//          JOIN ms_pary AS mps ON mps.id_pary=mks.id_pary
//          LEFT JOIN ms_deti AS md ON md.id_pary=mp.id_pary
//          LEFT JOIN ms_kursdeti AS mkd ON mkd.id_deti=md.id_deti AND mkd.id_akce=mk.id_akce
//          WHERE mk.id_akce=$akce
//          GROUP BY mp.id_pary
//          ORDER BY mp.jmeno";
  // páry kurzu
  $qry= "SELECT * $fields
         FROM ms_kurs AS mk
         $join
         WHERE mk.id_akce=$akce AND $cond
         $group
         ORDER BY $order";
  $res= mysql_qry($qry);
  while ( $res && ($x= mysql_fetch_object($res)) ) {
    switch ($typ) {
    case 'j':                             // jednotlivci
      $n++;
      $clmn[$n]= array();
      foreach($flds[0] as $f) {
        $clmn[$n][$f]= $x->$f;
      }
      $n++;
      $clmn[$n]= array();
      foreach($flds[1] as $f) {
        $clmn[$n][$f]= $x->$f;
      }
      break;
    case 'p':                             // páry
      $n++;
      $clmn[$n]= array();
      foreach($flds as $f) {
        $clmn[$n][$f]= $x->$f;
      }
      break;
    case 'd':                             // děti
      $n++;
      $clmn[$n]= array();
      $x->rodcislo_d= $x->rodcislo;
      $holka= $x->rodcislo_d && substr($x->rodcislo_d,2,1)>4 ? 1 : 0;
      $x->jmeno_d= $x->jmeno;
      $x->prijmeni_d= $holka ? $x->prijmeni_z : $x->prijmeni_m;
      foreach($flds as $f) {
        $clmn[$n][$f]= $x->$f;
      }
      break;
    }
  }
                                        debug($clmn,"sestava pro $akce,$typ,$fld,$cnd");
  // zobrazení tabulkou
  $tab= '';
  $thd= '';
  if ( $export ) {
    $result->tits= $tits;
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
      foreach ($c as $id=>$val) {
        $tab.= "<td style='text-align:left'>$val</td>";
      }
      $tab.= "</tr>";
    }
    $result->html= "<table class='stat'><tr>$ths</tr>$tab</table>";
    $result->href= $href;
  }
  return $result;
}
# ================================================================================================== GOOGLE
# -------------------------------------------------------------------------------------------------- akce_roku_id
# definuj klíč dané akce jeko klíč akce z aplikace MS.EXE
function akce_roku_id($kod,$rok,$source,$akce) {
  if ( $akce ) {
    mysql_qry("INSERT join_akce (source,akce,g_kod,g_rok) VALUES ('$source',$akce,$kod,$rok)");
    mysql_qry("UPDATE ms_akce SET ciselnik_akce=$kod,ciselnik_rok=$rok WHERE source='$source' AND akce=$akce");
  }
  return 1;
}
# -------------------------------------------------------------------------------------------------- akce_roku_update
# přečtení listu $rok z tabulky ciselnik_akci a zapsání dat do tabulky
# načítají se jen řádky ve kterých typ='a'
function akce_roku_update($rok) {  trace();
  $n= 0;
  $cells= google_sheet($rok,"ciselnik_akci",'answer@smidek.eu');
  if ( $cells ) {
    list($max_A,$max_n)= $cells['dim'];
                                                debug($cells,"akce $rok");
    // zrušení daného roku v GAKCE
    $qry= "DELETE FROM g_akce WHERE g_rok=$rok";
    $res= mysql_qry($qry);
    // výběr a-záznamů a zápis do GAKCE
    $values= ''; $del= '';
    for ($i= 1; $i<$max_n; $i++) {
      if ( $cells['A'][$i]=='a' ) {
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
        $values.= "$del($id,$rok,'$kod',\"$nazev\",'$od','$do','$uc','$typ','$kap')";
        $del= ',';
      }
    }
    $qry= "INSERT INTO g_akce (id_gakce,g_rok,g_kod,g_nazev,g_od,g_do,g_ucast,g_typ,g_kap) VALUES $values";
    $res= mysql_qry($qry);
  }
  // konec
  return $n;
}
# ================================================================================================== ÚČASTNÍCI
# -------------------------------------------------------------------------------------------------- akce_strava_denne
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
# -------------------------------------------------------------------------------------------------- akce_strava_denne_save
# zapsání výjimek z providelné stravy - pokud není výjimka zapíše prázdný string
function akce_strava_denne_save($id_kurs,$dnu,$cela,$cela_def,$cela_str,$polo,$polo_def,$polo_str) {  trace('');
  $cela_ruzna= $polo_ruzna= 0;
  for ($i= 2; $i<3*$dnu-1; $i++) {
    if ( substr($cela,$i,1)!=$cela_def ) $cela_ruzna= 1;
    if ( substr($polo,$i,1)!=$polo_def ) $polo_ruzna= 1;
  }
  if ( !$cela_ruzna ) $cela= '';
  if ( !$polo_ruzna ) $polo= '';
  // příprava update
  $set= '';
  if ( $cela!=$cela_str ) $set.= "cstrava_cel='$cela'";
  if ( $polo!=$polo_str ) $set.= ($set?',':'')."cstrava_pol='$polo'";
  if ( $set ) {
    $qry= "UPDATE ms_kurs SET $set WHERE id_kurs=$id_kurs";
    $res= mysql_qry($qry);
  }
                                                display("akce_strava_denne_save(($id_kurs,$dnu,$cela,$cela_def,$polo,$polo_def) $set");
  return 1;
}
# ================================================================================================== PRIDEJ JMENEM
# -------------------------------------------------------------------------------------------------- akce_auto_jmena
# SELECT autocomplete - výběr z akcí
function akce_auto_jmena($patt) {  #trace();
  $a= array();
  $limit= 20;
  $n= 0;
  // rodiče
  $qry= "SELECT id_pary AS _key,CONCAT(jmeno,' ',jmeno_m,' a ',jmeno_z) AS _value
         FROM ms_pary
         WHERE jmeno LIKE '$patt%' ORDER BY jmeno,jmeno_m,jmeno_z LIMIT $limit";
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
# -------------------------------------------------------------------------------------------------- akce_auto_jmenovci
# formátování autocomplete
function akce_auto_jmenovci($id_pary) {  #trace();
  $pary= array();
  // páry na akci
  $qry= "SELECT * FROM ms_pary WHERE id_pary=$id_pary ORDER BY jmeno";
  $res= mysql_qry($qry);
  while ( $res && $p= mysql_fetch_object($res) ) {
    $nazev= "{$p->jmeno} {$p->jmeno_m} a {$p->jmeno_z}, {$p->mesto} ({$p->id_pary})";
    $pary[]= (object)array('id_pary'=>$p->id_pary,'nazev'=>$nazev);
  }
//                                                                 debug($pary,$id_akce);
  return $pary;
}
# ================================================================================================== PRIDEJ z AKCE
# -------------------------------------------------------------------------------------------------- akce_auto_akce
# SELECT autocomplete - výběr z akcí
function akce_auto_akce($patt) {  #trace();
  $a= array();
  $limit= 20;
  $n= 0;
  // rodiče
  $qry= "SELECT id_akce AS _key,concat(nazev,' - ',YEAR(datum_od)) AS _value
         FROM ms_akce
         WHERE nazev LIKE '$patt%' ORDER BY datum_od DESC LIMIT $limit";
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
//                                                                 debug($a,$patt);
  return $a;
}
# -------------------------------------------------------------------------------------------------- akce_auto_ucast
# formátování autocomplete
function akce_auto_ucast($id_akce) {  #trace();
  $pary= array();
  // páry na akci
  $qry= "SELECT * FROM ms_kurs JOIN ms_pary USING(id_pary) WHERE id_akce=$id_akce ORDER BY jmeno";
  $res= mysql_qry($qry);
  while ( $res && $p= mysql_fetch_object($res) ) {
    $nazev= "{$p->jmeno} {$p->jmeno_m} a {$p->jmeno_z}, {$p->mesto}";
    $pary[]= (object)array('id_pary'=>$p->id_pary,'nazev'=>$nazev);
  }
//                                                                 debug($pary,$id_akce);
  return $pary;
}
# ================================================================================================== VYPISY
# obsluha různých forem výpisů
# -------------------------------------------------------------------------------------------------- akce_vyp_excel
# přečtení mailu
function akce_vyp_excel($akce,$par,$title,$vypis) {  trace();
  $result= (object)array('_error'=>0);
  $html= '';
  // získání dat
  $tab= akce_sestava($akce,$par,$title,$vypis,true);
  // vlastní export do Excelu
  $name= cz2ascii("vypis_").date("Ymd_Hi");
  $xls= <<<__XLS
    |open $name
    |sheet vypis;;L;page
    |A1 $title ::bold size=14 |A2 $vypis ::bold size=12
__XLS;
  // titulky
  $n= 4;
  $lc= 0;
  $clmns= $del= '';
  foreach ($tab->tits as $idw) {
    $A= Excel5_n2col($lc);
    list($id,$w)= explode(':',$idw);
    $xls.= "|$A$n $id";
    if ( $w ) {
      $clmns.= "$del$A=$w";
      $del= ',';
    }
    $lc++;
  }
  if ( $clmns ) $xls.= "\n|columns $clmns ";
  $xls.= "\n|A$n:$A$n bcolor=ffaaaaaa \n";
  $n= 5;
  // datové řádky
  foreach ($tab->clmn as $i=>$c) {
    $lc= 0;
    foreach ($c as $id=>$val) {
      $A= Excel5_n2col($lc);
      $xls.= "|$A$n $val";
      $lc++;
    }
    $n++;
  }
  $xls.= <<<__XLS
    |close
__XLS;
  // výstup
                                                                display($xls);
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
# ================================================================================================== EMAILY
# jednotlivé maily posílané v sadách příložitostně skupinám
#   DOPIS(id_dopis=key,id_davka=1,druh='@',nazev=předmět,datum=datum,obsah=obsah,komu=komu(číselník),
#         nw=min(MAIL.stav,nh=max(MAIL.stav)})
#   MAIL(id_mail=key,id_davka=1,id_dopis=DOPIS.id_dopis,znacka='@',id_clen=clen,email=adresa,
#         stav={0:nový,3:rozesílaný,4:ok,5:chyba})
# formát zápisu dotazu v číselníku viz fce dop_mai_qry
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
                                                        debug($d,"dop_mai_text($id_dopis)");
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
# -------------------------------------------------------------------------------------------------- dop_mai_pocet
# zjistí počet adresátů pro rozesílání a sestaví dotaz pro confirm
# pokud _cis.data=9999 jde o speciální seznam definovaný funkcí dop_mai_skupina
function dop_mai_pocet($id_dopis) {  trace();
  $result= (object)array('_error'=>0, '_count'=> 0, '_cond'=>false);
  $result->_html= 'Rozesílání mailu nemá určené adresáty, stiskni ZRUŠIT';
  $count= 0;
  // zjisti výběrovou podmínku
  $qry= "SELECT _cis.hodnota AS _cond,data,zkratka,nazev
         FROM dopis JOIN _cis ON _cis.data=komu AND _cis.druh='am_komu'
         WHERE id_dopis=$id_dopis ";
  $res= mysql_qry($qry);
  if ( $res && ($d= mysql_fetch_object($res)) && $d->_cond ) {
    if ( substr($d->zkratka,0,5)=='spec.' ) {
      // zjisti počet funkcí dop_mai_skupina
      $res= dop_mai_skupina($d->_cond);
      $count= count($res->adresy);
      $result->_adresy= $res->adresy;
    }
    else {
      // zjisti počet pole výběrové podmínky
      $result->_cond= $d->_cond;
      $qry= dop_mai_qry($result->_cond);
      $res= mysql_qry($qry);
      if ( $res ) $count= mysql_num_rows($res);
    }
    $result->_html= $count>0
      ? "Opravdu rozeslat mail '{$d->nazev}' na $count adres? ({$d->_cond})"
      : "Mail '{$d->nazev}' nemá žádného adresáta, stiskni ZRUŠIT ({$d->_cond})";
    $result->_count= $count;
  }
  return $result;
}
# -------------------------------------------------------------------------------------------------- dop_mai_posli
# do tabulky MAIL dá seznam emailových adres pro rozeslání (je volána po dop_mai_pocet)
function dop_mai_posli($id_dopis,$info) {  trace();
  $num= 0;
                                                        debug($info);
  // smaž starý seznam
  $qry= "DELETE FROM mail WHERE id_dopis=$id_dopis ";
  $res= mysql_qry($qry);
  if ( !$res ) fce_error("dop_mai_smaz: mazání rozesílání mailu No.'$id_dopis' se nepovedlo");
  if ( $info->_adresy ) {
    // pokud jsou přímo známy adresy, pošli na ně
    foreach ( $info->_adresy as $email ) {
      // vlož do MAIL
      $qr= "INSERT mail (id_davka,znacka,stav,id_dopis,id_clen,email)
            VALUE (1,'@',0,$id_dopis,0,'$email')";
      $rs= mysql_qry($qr);
      $num+= mysql_affected_rows();
    }
  }
  else {
    // jinak zjisti adresy z databáze
    $qry= dop_mai_qry($info->_cond);
    $res= mysql_qry($qry);
    while ( $res && $c= mysql_fetch_object($res) ) {
      // vlož do MAIL
      $qr= "INSERT mail (id_davka,znacka,stav,id_dopis,id_clen,email)
            VALUE (1,'@',0,$id_dopis,{$c->id_clen},'{$c->email}')";
      $rs= mysql_qry($qr);
      $num+= mysql_affected_rows();
    }
  }
  // oprav počet v DOPIS
  $qr= "UPDATE dopis SET pocet=$num WHERE id_dopis=$id_dopis";
  $rs= mysql_qry($qr);
  return true;
}
# -------------------------------------------------------------------------------------------------- dop_mai_info
# informace o členovi
function dop_mai_info($id_clen,$email) {  trace();
  $html= '';
  if ( $id_clen ) {
    $qry= "SELECT * FROM clen WHERE id_clen=$id_clen ";
    $res= mysql_qry($qry);
    if ( $res && $c= mysql_fetch_object($res) ) {
      $html.= "{$c->id_clen}: {$c->titul} {$c->jmeno} {$c->prijmeni}<br>";
      $html.= "{$c->ulice}, {$c->psc} {$c->obec}<br><br>";
      if ( $c->telefony )
        $html.= "Telefon: {$c->telefony}<br>";
      if ( $c->aktivita==6 )
        $html.= "Kapr od roku ".substr($c->ka_od,0,4);
      if ( $c->aktivita==4 )
        $html.= "Člen od roku ".substr($c->clen_od,0,4);
    }
  }
  else {
    $html.= $email;
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
function dop_mai_stav($id_mail,$stav) {  trace();
  $qry= "UPDATE mail SET stav=$stav WHERE id_mail=$id_mail ";
  $res= mysql_qry($qry);
  if ( !$res ) fce_error("dop_mai_stav: změna stavu mailu No.'$id_mail' se nepovedla");
  return true;
}
# -------------------------------------------------------------------------------------------------- dop_mai_send
# ASK
# odešli dávku $kolik mailů ($kolik=0 znamená testovací poslání)
function dop_mai_send($id_dopis,$kolik=0) { trace();
  global $ezer_path_serv;
  require_once("$ezer_path_serv/licensed/phpmailer/class.phpmailer.php");
  $result= (object)array('_error'=>0);
  // přečtení rozesílaného mailu
  $qry= "SELECT * FROM dopis WHERE id_dopis=$id_dopis ";
  $res= mysql_qry($qry,1,null,1);
  $d= mysql_fetch_object($res);
  // napojní na mailer
  $html= '';
//   $klub= "klub@proglas.cz";
  $martin= "martin@smidek.eu";
  $jarda= "cerny.vavrovice@seznam.cz";
//   $jarda= $martin;
  // poslání mailů
  $mail= new PHPMailer;
  $mail->Host= "192.168.1.1";
  $mail->CharSet = "utf-8";
  $mail->From= $jarda;
  $mail->AddReplyTo($jarda);
//   $mail->ConfirmReadingTo= $jarda;
  $mail->FromName= "YMCA Setkání";
  $mail->Subject= $d->nazev;
  $mail->Body= $d->obsah;
  $mail->IsHTML(true);
  $mail->Mailer= "smtp";
  if ( $d->prilohy ) {
    foreach ( explode(',',$d->prilohy) as $fname ) {
      $fpath= "docs/".trim($fname);
      $mail->AddAttachment($fpath);
    }
  }
  if ( $kolik==0 ) {
    // testovací poslání sobě
    $mail->AddAddress($martin);   // pošli sám sobě
    // pošli
    if ( $mail->Send() )
      $html.= "<br><b><font color='#070'>Byl odeslán mail na $martin - je zapotřebí zkontrolovat obsah</font></b>";
    else {
      $html.= "<br><b><font color='#700'Při odesílání mailu došlo k chybě: {$mail->ErrorInfo}</font></b>";
      $result->_error= 1;
    }
  }
  else {
    // poslání dávky $kolik mailů
    $n= $nko= 0;
    $qry= "SELECT * FROM mail WHERE id_dopis=$id_dopis AND stav=0";
    $res= mysql_qry($qry);
    while ( $res && ($z= mysql_fetch_object($res)) ) {
      // posílej mail za mailem
      if ( $n>=$kolik ) break;
      $n++;
      $i= 0;
      $mail->ClearAddresses();
      $mail->ClearCCs();
      foreach(explode(',',$z->email) as $adresa) {
        if ( !$i++ )
          $mail->AddAddress($adresa);   // pošli na 1. adresu
        else                            // na další jako kopie
          $mail->AddCC($adresa);
      }
//       $mail->AddBCC($klub);
      // zkus poslat mail
      if ( !($ok= $mail->Send()) ) {
        $ident= $z->id_clen ? $z->id_clen : $adresa;
        $html.= "<br><b><font color='#700'Při odesílání mailu pro $ident došlo k chybě: "
          . "{$mail->ErrorInfo}</font></b>";
        $result->_error= 1;
        $nko++;
      }
      // zapiš výsledek do tabulky
      $stav= $ok ? 4 : 5;
      $msg= $ok ? '' : $mail->ErrorInfo;
      $qry1= "UPDATE mail SET stav=$stav,msg=\"$msg\" WHERE id_mail={$z->id_mail}";
      $res1= mysql_qry($qry1);
    }
    $html.= "<br><b><font color='#070'>Bylo odesláno $n emailů ";
    $html.= $nko ? "s $nko chybami " : "bez chyb";
    $html.= "</font></b>";
  }
  // zpráva o výsledku
  $result->_html= $html;
  return $result;
}
# -------------------------------------------------------------------------------------------------- dop_mai_skupina
# ASK
# odešli dávku $kolik mailů ($kolik=0 znamená testovací poslání)
function dop_mai_skupina($skupina) { trace();
  global $dop_mai_v2010;
  $result= (object)array('_error'=>0);
  $adresy= array();
  // výběr mailů do pole $adresy a naplnění $html
  switch ($skupina) {
  # výbor
  case 'martin':
  case 'vybor':
    $t= $dop_mai_v2010[$skupina];
    $n= $nt= $nx= 0;
    foreach($t as $adr) {
      if ( $adr[0]!= '-' && emailIsValid($adr) ) {
        $adresy[]= $adr;
        $nt++;
      }
      else {
        $nx++;
      }
    }
    $html.= "<h3>$nt ve skupině: $skupina</h3>".implode('<br>',$adresy);
    $html.= "<h3>$nx bylo vyřazeno</h3>";
    break;
  # vánoce 2010
  case 'vanoce2010':
    $j= $dop_mai_v2010['jarda'];
    $k= $dop_mai_v2010['konf'];
    $n= $nj= $nk= $nx= 0;
    $in_jk= $not_in_k= $not_in_j= array();
    foreach($k as $adr) {
      if ( $adr[0]!= '-' && emailIsValid($adr) ) {
        if ( in_array($adr,$j) ) {
          $in_jk[]= $adr;
          $n++;
        }
        else {
          $not_in_j[]= $adr;
          $nj++;
        }
      }
      else {
        $nx++;
      }
    }
    $html.= "<h3>$n je v JARDA i KONF</h3>".implode('<br>',$in_jk);
    $html.= "<h3>$nj není v JARDA</h3>".implode('<br>',$not_in_j);
    $nk= 0;
    foreach($j as $adr) {
      if ( $adr[0]!= '-' && emailIsValid($adr) ) {
        if ( !in_array($adr,$k) ) {
          $not_in_k[]= $ok.$adr;
          $nk++;
        }
      }
      else {
        $nx++;
      }
    }
    $html.= "<h3>$nk není v KONF</h3>".implode('<br>',$not_in_k);
    $html.= "<h3>$nx bylo vyřazeno</h3>";
    $adresy= array_merge($in_jk,$not_in_j,$not_in_k);
  }
  // zápis pole $adresa
  $adresy= array_unique($adresy);
  sort($adresy);
  $html= "<h3>".count($adresy)." adres bude použito jako '$skupina'</h3>".implode('<br>',$adresy);
  $result->_html= $html;
  $result->adresy= $adresy;
  return $result;
}
# -------------------------------------------------------------------------------------------------- emailIsValid
# emailIsValid - http://www.kirupa.com/forum/showthread.php?t=323018
# args:  string - proposed email address
# ret:   bool
# about: tells you if an email is in the correct form or not
function emailIsValid($email) {
   $isValid = true;
   $atIndex = strrpos($email, "@");
   if (is_bool($atIndex) && !$atIndex)    {
      $isValid = false;
   }
   else    {
      $domain    = substr($email, $atIndex+1);
      $local     = substr($email, 0, $atIndex);
      $localLen  = strlen($local);
      $domainLen = strlen($domain);
      if ($localLen < 1 || $localLen > 64)       {
         // local part length exceeded
         $isValid = false;
      }
      else if ($domainLen < 1 || $domainLen > 255)       {
         // domain part length exceeded
         $isValid = false;
      }
      else if ($local[0] == '.' || $local[$localLen-1] == '.')       {
         // local part starts or ends with '.'
         $isValid = false;
      }
      else if (preg_match('/\\.\\./', $local))  {
         // local part has two consecutive dots
         $isValid = false;
      }
      else if (!preg_match('/^[A-Za-z0-9\\-\\.]+$/', $domain))   {
         // character not valid in domain part
         $isValid = false;
      }
      else if (preg_match('/\\.\\./', $domain))  {
         // domain part has two consecutive dots
         $isValid = false;
      }
      else if (!preg_match('/^(\\\\.|[A-Za-z0-9!#%&`_=\\/$\'*+?^{}|~.-])+$/', str_replace("\\\\","",$local)))   {
         // character not valid in local part unless
         // local part is quoted
         if (!preg_match('/^"(\\\\"|[^"])+"$/',
             str_replace("\\\\","",$local)))            {
            $isValid = false;
         }
      }
      if ( $domain!='proglas.cz' && $domain!='setkani.org' ) {
        if ($isValid && !(checkdnsrr($domain,"MX") || checkdnsrr($domain,"A")))      {
           // domain not found in DNS
           $isValid = false;
        }
      }
   }
   return $isValid;
}
$dop_mai_v2010= array(
'martin'=> array(
//   "michalec.zdenek@inset.com",
  "smidek@proglas.cz",
  "martin.smidek@gmail.com",
  "gandi@volny.cz",
  "martin.smidek@gmail.com",
  "error@smidek.eu",
  "martin.smidek@gmail.com",
  "martin@smidek.eu"
),
'vybor'=> array(
  "ymca@setkani.org",
  "cerny.vavrovice@seznam.cz",
  "j.kvapil@kvapil-elektro.cz",
  "svika.petr@seznam.cz",
  "martin@smidek.eu"
),
'konf'=> array(
  "2martin.kolar@gmail.com",
  "a.asana@seznam.cz",
  "a.m.malerovi@worldonline.cz",
  "abrahamovakatka@seznam.cz",
  "adamik@cbox.cz",
  "agi@volny.cz",
  "ajazbilovic@seznam.cz",
  "akasicky@csas.cz",
  "al.kubik@volny.cz",
  "alena.zmrzla@seznam.cz",
  "alorenc@nbox.cz",
  "ambrozek.ladislav@muhodonin.cz",
  "ambrozek@quick.cz",
  "anka@volny.cz",
  "anna.bouskova@centrum.cz",
  "annasodomkova@seznam.cz",
  "annastrzelcova@centrum.cz",
  "---anneli@portman.it",
  "antonin.koudelka@seznam.cz",
  "antonin.tesacek@seznam.cz",
  "arnost.bass@volny.cz",
  "artpetra@artpetra.cz",
  "audiodan@centrum.cz",
  "babicek@centroprojekt.cz",
  "bajapavlaskova@seznam.cz",
  "---balazia@eurolex.sk",
  "bammarkovi@quick.cz",
  "barta@cbnet.cz",
  "Bartova.Blanka@seznam.cz",
  "---BAUR@proglas.cz",
  "---bbrezina@gity.cz",
  "bednar@datasys.cz",
  "beranci@seznam.cz",
  "---berankova@proglas.cz",
  "bernardchrastecky@centrum.cz",
  "betak@burgmann.com",
  "bezouska.v@seznam.cz",
  "bhorka@ksoud.unl.justice.cz",
  "blanka@kubicekairtex.cz",
  "blazek.iv@atlas.cz",
  "---blaziova@nextra.sk",
  "bocan@trakce.cz",
  "bohca@post.cz",
  "brothanek@seznam.cz",
  "btriska@zpmvcr.cz",
  "bucek@fem.cz",
  "bujnovsky@quick.cz",
  "bures.j@mujbox.cz",
  "---bystrik.sliace@post.sk",
  "cagala@volny.cz",
  "canda.stefan@seznam.cz",
  "castul@seznam.cz",
  "cerny.vavrovice@seznam.cz",
  "cerny@proglas.cz",
  "cestr@knihkrnov.cz",
  "cichonjosef@seznam.cz",
  "cpr@doo.cz",
  "cprop@doo.cz",
  "cprvyzva@doo.cz",
  "d.gebauer@email.cz",
  "dag.blahova@seznam.cz",
  "dag123@centrum.cz",
  "dagmar.kolarova@gmail.com",
  "dagmar.wormova@seznam.cz",
  "dagmar_foltova@centrum.cz",
  "dalimil.barton@meac.cz",
  "danahalbrstatova@tiscali.cz",
  "darina.brothankova@seznam.cz",
  "das.dental@tiscali.cz",
  "devetter@upb.cas.cz",
  "didimos@seznam.cz",
  "dobrakniha@wo.cz",
  "dolezal.jura@seznam.cz",
  "dolezelova@biskupstvi.cz",
  "doming@volny.cz",
  "dvorak.1968@tiscali.cz",
  "dvorak@mikulovice.cz",
  "e.mercl@seznam.cz",
  "editmoravcova@email.cz",
  "eduard.strzelec@centrum.cz",
  "educos@mbox.vol.cz",
  "---ekondela@izaqua.sk",
  "---ekonomix_hujo@nextra.sk",
  "emgkl@tiscali.cz",
  "erika.domasikova@caritas.cz",
  "erika.domasikova@tiscali.cz",
  "esvoboda@seznam.cz",
  "eva.merclova@centrum.cz",
  "evakalvinska@seznam.cz",
  "f.hellebrand@seznam.cz",
  "f.podskubka@vela.cz",
  "f.zajicek@med.muni.cz",
  "fanduli@atlas.cz",
  "---feketovam@pobox.sk",
  "fiser.projekce@quick.cz",
  "fmoravcik@satos.cz",
  "frantisek.ruzicka@mukrupka.cz",
  "frantisek.vomacka@unex.cz",
  "frantisekchvatik@muzlin.cz",
  "fuis@fme.vutbr.cz",
  "fuis@seznam.cz",
  "fusekle@centrum.cz",
  "gajdova@hlucin.cz",
  "gargalici@tiscali.cz",
  "genzerm@atlas.cz",
  "genzerovap@atlas.cz",
  "geocart@c-mail.cz",
  "georgeII@seznam.cz",
  "gita.vyletalova@email.cz",
  "glogar@centrum.cz",
  "gras_servis@post.cz",
  "grebikjosef@seznam.cz",
  "gregor.ludek@vol.cz",
  "gregor@cmg.prostejov.cz",
  "gutu@centrum.cz",
  "hajt.marketa@seznam.cz",
  "hamrikovi@volny.cz",
  "hana.dalikova@email.cz",
  "hana.malcova@centrum.cz",
  "hana.pistorova@familia.cz",
  "hana.michalcova@centrum.cz",
  "hannybuch@seznam.cz",
  "---hauserova@proglas.cz",
  "havelka.jirka@centrum.cz",
  "hazy@centrum.cz",
  "hcajankova@seznam.cz",
  "hej_rup@volny.cz",
  "helena.im@seznam.cz",
  "hesta@hesta.cz",
  "hlisnikovsky.j@seznam.cz",
  "HMa@seznam.cz",
  "hndh@volny.cz",
  "hnizda.kamil@seznam.cz",
  "horacek.buk@seznam.cz",
  "horakova.vlasta@seznam.cz",
  "horakovam@atlas.cz",
  "horakovia.h@email.cz",
  "horska@nettown.cz",
  "horsky@nettown.cz",
  "HOSEK.7@seznam.cz",
  "houskaj@post.cz",
  "HPerinova@seznam.cz",
  "hstefek@meding.cz",
  "hukovi@centrum.cz",
  "chemingstav@seznam.cz",
  "chrpa.pavla@seznam.cz",
  "i.nejezchlebova@quick.cz",
  "ificzka@seznam.cz",
  "info@ruff.cz",
  "ing.jiri.brtnik@seznam.cz",
  "ipro-pm@volny.cz",
  "irena.smekalova@centrum.cz",
  "iva.kasikova@centrum.cz",
  "iva-kasparkova@seznam.cz",
  "ivan.kolos@vsb.cz",
  "ivana.jenistova@caritas.cz",
  "ivanpodest@seznam.cz",
  "ivo.kalvinsky@seznam.cz",
  "j.babicek@seznam.cz",
  "j.brauner@volny.cz",
  "j.ejem@seznam.cz",
  "j.fidrmuc@volny.cz",
  "j.kvapil@kvapil-elektro.cz",
  "j.orlik@seznam.cz",
  "j.solovsky@centrum.cz",
  "j.v.zajickovi@tiscali.cz",
  "j.zelinka@centrum.cz",
  "jafra@quick.cz",
  "jakub.david@seznam.cz",
  "jan.bucha@quick.cz",
  "jan.eyer@centrum.cz",
  "jan.havlicek@spvs.cz",
  "jan.chrastecky@siemens.com",
  "jan.janoska@volny.cz",
  "jan.juran@volny.cz",
  "JAN.LOUCKA1959@seznam.cz",
  "jan.mantl@tiscali.cz",
  "jan.mikolas@volny.cz",
  "jan.petkov@opava.cz",
  "jan.rotter@seznam.cz",
  "jan.rychtar@tiscali.cz",
  "jan.straka@centrum.cz",
  "jana.jarolimova@email.cz",
  "jana.kaluzova@rwe.cz",
  "jana.praisova@doo.cz",
  "jana.vodakova@centrum.cz",
  "janajagerova@seznam.cz",
  "janakadr@centrum.cz",
  "janakopriva@seznam.cz",
  "janavcelova@centrum.cz",
  "janjager@seznam.cz",
  "jarekkriz@volny.cz",
  "jarholka@seznam.cz",
  "jarko@tiscali.cz",
  "jaro.slavte@seznam.cz",
  "jaromir.kvapil@iol.cz",
  "jaromir_sevela@rutronik.com",
  "---jatom@jatom.cz",
  "jelinekjosef@volny.cz",
  "jerabkovam@email.cz",
  "jforbelsky@ic-energo.cz",
  "jhutar@volny.cz",
  "Jidelna.MSUkrajinska@seznam.cz",
  "jindrich.honek@seznam.cz",
  "jiri.holik@volny.cz",
  "jiri.linart@centrum.cz",
  "jiri.malec@volny.cz",
  "jiri.satke@cewood.cz",
  "jiri.slimarik@volny.cz",
  "jiri.smahel@gmail.com",
  "tkac.jiri@inset.com",
  "jiri@doffek.cz",
  "jirkalachman@tiscali.cz",
  "jirky.peska@tiscali.cz",
  "jitahory@seznam.cz",
  "jitka_vodickova@kb.cz",
  "jitkakozak@seznam.cz",
  "jjgoth@razdva.cz",
  "jjsebek@volny.cz",
  "jkasicka@csas.cz",
  "JKristkova@ksoud.brn.justice.cz",
  "jkuncar@cpdirect.cz",
  "jledl@quick.cz",
  "jmalasek@volny.cz",
  "josef.cervenka@volny.cz",
  "josef.fritschka@technodat.cz",
  "josef.liberda@mujes.cz",
  "josef.mori@zdas.cz",
  "josef.neruda@dalkia.cz",
  "josefka.koutna@seznam.cz",
  "joshavlik@centrum.cz",
  "jprokopova@seznam.cz",
  "jslachtova@seznam.cz",
  "jura.stransky@seznam.cz",
  "---juraj.cerven@softec.sk",
  "jurankova@proglas.cz",
  "just.t@seznam.cz",
  "juty@seznam.cz",
  "jvpsimon@seznam.cz",
  "jzich@atlas.cz",
  "kabatovi@gmail.com",
  "kafonkova@centrum.cz",
  "kalabova@tiscali.cz",
  "kamajafr@quick.cz",
  "karel.audit@centrum.cz",
  "karel.bartos@centrum.cz",
  "Karel.Cyrus@fei.com",
  "karel.rysavy@post.cz",
  "kaspic@seznam.cz",
  "kastovskyj@seznam.cz",
  "katerina.remesova@seznam.cz",
  "katka.k@aschool.cz",
  "katkadol@seznam.cz",
  "kintrova@proglas.cz",
  "kk@brno.kdu.cz",
  "klaber@volny.cz",
  "klanov@seznam.cz",
  "Klasek.Robert@uhul.cz",
  "klimes@portal.cz",
  "Knopf.Stanislav@seznam.cz",
  "kodek.petruj@centrum.cz",
  "kodek@iol.cz",
  "kohoutek127@seznam.cz",
  "korbel@gvmyto.cz",
  "koronthalyova@seznam.cz",
  "kostrhon@seznam.cz",
  "kovo.sujan@seznam.cz",
  "kpejchalova@seznam.cz",
  "kreces1.edu@mail.cez.cz",
  "krizalkovicova@seznam.cz",
  "krizvlastimil@seznam.cz",
  "kstepanek@email.cz",
  "---kubes@adda.sk.",
  "---kufova@osobnifinanceplus.cz",
  "kuchar@flux.cz",
  "kulihrasek.jiri@seznam.cz",
  "kuncarovi@seznam.cz",
  "kutna.hora@cb.cz",
  "---kvapil@PSP.cz",
  "kvapilovajaroslava@seznam.cz",
  "l.danys@centrum.cz",
  "l.Kabatova@quick.cz",
  "---lacop@merina.sk",
  "lachman@msmt.cz",
  "leni25@seznam.cz",
  "lenihandzlova@centrum.cz",
  "lenka.ryzova@centrum.cz",
  "lenka_sevelova@post.cz",
  "lesakova.ls157@lesycr.cz",
  "lhorsak@itczlin.cz",
  "Libor.Jarolim@seznam.cz",
  "libor.kabat@power.alstom.com",
  "libuse.popelkova@seznam.cz",
  "lidajetlebova@seznam.cz",
  "lidkacerna@seznam.cz",
  "limail@seznam.cz",
  "limramovsky@nbox.cz",
  "---ljarolim@elmath.cz",
  "Ljuba.Stranska@seznam.cz",
  "lmp@volny.cz",
  "lnenicka.Jiri@seznam.cz",
  "louckova.marie@seznam.cz",
  "lraus@tenza.cz",
  "lubomir@cmail.cz",
  "lucie.borakova@seznam.cz",
  "ludek@bouska.info",
  "ludmila.liberdova@mujes.cz",
  "Ludmila.Lnenickova@seznam.cz",
  "ludmila.loksova@seznam.cz",
  "ludva@hegrlik.cz",
  "ludvikmichlovsky@seznam.cz",
  "lukl@iol.cz",
  "m.a.markova@volny.cz",
  "m.novotna@post.cz",
  "m.stula@worldonline.cz",
  "m.tvrda@seznam.cz",
  "m.zelinkova@centrum.cz",
  "maba@o2active.cz",
  "majka.feketeova@gmail.com",
  "majkace@volny.cz",
  "majkasimonova@seznam.cz",
  "makovnici@zrnka.net",
  "marcbo@seznam.cz",
  "Marcela.Hoskova@seznam.cz",
  "marek.milan@centrum.cz",
  "marek.pospisil@seznam.cz",
  "marek_janca@quick.cz",
  "marie.wawraczova@volny.cz",
  "martamo@seznam.cz",
  "martin.busina@seznam.cz",
  "martin.cajanek@osu.cz",
  "martin.ds@seznam.cz",
  "martin.chromjak@tiscali.cz",
  "martin@smidek.eu",
  "martina.babickova@seznam.cz",
  "---martinek@pmgastro.cz",
  "martinka.koudelkova@seznam.cz",
  "martinka.petr@seznam.cz",
  "martinka.stepanek@olomouc.cz",
  "---marusiak@sponit.cz",
  "mbrez@seznam.cz",
  "medium@email.cz",
  "metodej.chrastecky@seznam.cz",
  "mezulanik@proglas.cz",
  "mholdik@volny.cz",
  "mholikova@volny.cz",
  "mhubacekza@volny.cz",
  "michal@garden114.cz",
  "michalec.zdenek@inset.com",
  "mika@diamo.cz",
  "mila.havrdova@seznam.cz",
  "---milada.barotova@racek.org",
  "milada.n@centrum.cz",
  "milan.barot@gmail.com",
  "---milan.barot@racek.org",
  "milan.bily@volny.cz",
  "milan.duben@gist.cz",
  "milan.jebavy@tiscali.cz",
  "milan.kantor@quick.cz",
  "milan.strakos@click.cz",
  "milansoldan@muzlin.cz",
  "milence@centrum.cz",
  "milos.vyletal@email.cz",
  "miloslav.kopriva@svi.hk.ds.mfcr.cz",
  "mira.svec@wo.cz",
  "mirek@kadrnozka.cz",
  "mirek_dvorak@volny.cz",
  "mirekp@tiscali.cz",
  "mirf@volny.cz",
  "miroslav.borak@T-mobile.cz",
  "miroslav.sot@centrum.cz",
  "miroslav-kotek@seznam.cz",
  "MJarolim@seznam.cz",
  "---mkapustova@szm.sk",
  "mkotek@ic-energo.eu",
  "modry.slon@volny.cz",
  "moni.dol@volny.cz",
  "mpolak@centrum.cz",
  "mrazkova14@seznam.cz",
  "mrozek@techfloor.cz",
  "MSujanova@seznam.cz",
  "mudr.eyerova@volny.cz",
  "mv@martinvana.net",
  "nadabetakova@seznam.cz",
  "nagl@arcibol.cz",
  "necas@anete.com",
  "nedvedova.zdislava@pontis.cz",
  "nejez@seznam.cz",
  "nejezchleb@crytur.cz",
  "nerudv1@feld.cvut.cz",
  "norin@email.cz",
  "novak@ivysehrad.cz",
  "novakpm@volny.cz",
  "novotny.p@kr-ustecky.cz",
  "ohral@iol.cz",
  "ohralova@email.cz",
  "oldtom@t-email.cz",
  "olgaolivova@centrum.cz",
  "ondracekm@seznam.cz",
  "ondranicka@seznam.cz",
  "ondrej.mrazek@schiedel.cz",
  "ondrejremes@atlas.cz",
  "osikora@seznam.cz",
  "oslama@fnbrno.cz",
  "oto.worm@seznam.cz",
  "p.e.t.r.f@seznam.cz",
  "p.folta@centrum.cz",
  "p.janoskova@seznam.cz",
  "p.kvapil@kvapil-elektro.cz",
  "p.ne@seznam.cz",
  "p.patterman@centrum.cz",
  "p_blaha@kb.cz",
  "pa.vaclav@seznam.cz",
  "palisek@bnzlin.cz",
  "pase@seznam.cz",
  "pastor@marianskohorska.cz",
  "pavcerny@volny.cz",
  "pavel.folta@charita.cz",
  "pavel.chladek@vasbo.cz",
  "pavel.klimes@email.cz",
  "pavel.kyska@volny.cz",
  "pavel.nemec@zs-majakovskeho.cz",
  "pavel.obluk@dchoo.caritas.cz",
  "pavel.samek@mora.cz",
  "pavel.smolka@post.cz",
  "pavel.vagunda@atlas.cz",
  "pavel.vit@tycoelectronics.com",
  "pavel@pneuprochazka.cz",
  "pavelcejnek@seznam.cz",
  "pavelsevcik76@seznam.cz",
  "pavelsmolko@yahoo.com",
  "pavelzeleny@seznam.cz",
  "pavla.rybova@caritas.cz",
  "pavla1.ticha@seznam.cz",
  "pavlik@intext.cz",
  "pavlinahajna@centrum.cz",
  "pdaniela@centrum.cz",
  "pebursik@volny.cz",
  "pek@redis.cz",
  "pepa.ondracek@seznam.cz",
  "pepethesailor@volny.cz",
  "pesek@it.cas.cz",
  "peta.dolik@seznam.cz",
  "peter.telekes@post.cz",
  "peterescu@seznam.cz",
  "---petlanova@proglas.cz",
  "petr.brich@quick.cz",
  "petr.d@volny.cz",
  "Petr.Janda@centrum.cz",
  "petr.klasek@seznam.cz",
  "petr.otr@worldonline.cz",
  "petr.schlemmer@nemspk.cz",
  "petr.schlemmer@seznam.cz",
  "petr.wajda@centrum.cz",
  "petr_bezpalec@volny.cz",
  "petra.vin@seznam.cz",
  "petra@doffek.cz",
  "petra@ibp.cz",
  "petrprokop@seznam.cz",
  "Petsti@email.cz",
  "pgadas@razdva.cz",
  "pgholubovi@iol.cz",
  "phranac@seznam.cz",
  "pchalenka@seznam.cz",
  "pilnam@seznam.cz",
  "pilny.spp@volny.cz",
  "pipvovo@mail.ru",
  "pjhlustik@gmail.com",
  "PLECHACP@fnplzen.cz",
  "polak@synerga.cz",
  "---polakovicovci@chello.sk",
  "policer@seznam.cz",
  "ponizil@agritec.cz",
  "ponizil@salvo.zlin.cz",
  "---portman@promo.it",
  "ppejchal@seznam.cz",
  "ppodsednik@razdva.cz",
  "ppr@doo.cz",
  "---prais@premie.cz",
  "premek.hruby@centrum.cz",
  "PriessnitzJan@seznam.cz",
  "prihodova@inform.cz",
  "proenvi@proenvi.cz",
  "prochazkovak@email.cz",
  "pstefkova@meding.cz",
  "pstoklasa@krok-hranice.cz",
  "ptacnik@taxnet.cz",
  "pwawracz@volny.cz",
  "r.b@seznam.cz",
  "r.barabas@seznam.cz",
  "r.hadraba@seznam.cz",
  "r.komarek@volny.cz",
  "radim.sotkovsky@siemens.com",
  "RadovanHolik@seznam.cz",
  "rakhana@quick.cz",
  "raksim@centrum.cz",
  "rasticova@volny.cz",
  "---rastislav.pocubay@st.nicolaus.sk",
  "RausovaRut@seznam.cz",
  "rbrazda@infotech.cz",
  "rcajanek@seznam.cz",
  "rek@seznam.cz",
  "rhodesian@centrum.cz",
  "rlap@volny.cz",
  "rodina.tomsova@worldonline.cz",
  "---rodina@arcibol.",
  "roman.mokrosz@post.cz",
  "roman.zima@tiscali.cz",
  "rosovaradka@seznam.cz",
  "rostislav.kulisan@cirkevnizs.hradecnm.indos.cz",
  "rp.barton@seznam.cz",
  "rpavelkova@dpmb.cz",
  "ruckovi@seznam.cz",
  "rybasvatopluk@seznam.cz",
  "s.stranak@quick.cz",
  "sandholzova@seznam.cz",
  "sapak.vojtech@volny.cz",
  "saranch@seznam.cz",
  "sdostal@doo.cz",
  "seifriedovi@seznam.cz",
  "selucka.monika@seznam.cz",
  "sequens@seznam.cz",
  "sevros@centrum.cz",
  "schnirch@volny.cz",
  "simerda@vues.cz",
  "simici@mybox.cz",
  "simtec@post.cz",
  "sintal@izolacezlin.cz",
  "siskovi@centrum.cz",
  "skoloud.p@seznam.cz",
  "skrlata@tiscali.cz",
  "sladek@opr.ova.cd.cz",
  "slachtajan@seznam.cz",
  "slavomir.mrozek@seznam.cz",
  "slivka@signalbau.cz",
  "smidek@proglas.cz",
  "smidkova@proglas.cz",
  "snejdar@seznam.cz",
  "sobechleby@seznam.cz",
  "solano@centrum.cz",
  "soptikkamil@post.cz",
  "sotkovskyr@centrum.cz",
  "sotola@hlinsko.cz",
  "sotovi@tiscali.cz",
  "soubusta@sloup.upol.cz",
  "soucek@vukrom.cz",
  "srandyskova@seznam.cz",
  "srandyskova@seznam.cz",
  "srsnovi@email.cz",
  "st.mach@volny.cz",
  "---stacho2@tele2.cz",
  "standa.skricka@volny.cz",
  "stanek.p@volny.cz",
  "stary.misa@quick.cz",
  "stevenix@seznam.cz",
  "stoklaskovi.tas@seznam.cz",
  "strakova.misa@centrum.cz",
  "sujanovaZora@seznam.cz",
  "sujanovi@tiscali.cz",
  "svarservis@svarservis.cz",
  "sypena@quick.cz",
  "t.jakubicek@seznam.cz",
  "tetra.jurka@seznam.cz",
  "tholik@volny.cz",
  "---tichy@ornela.cz",
  "tomaluk@centrum.cz",
  "tomas@vichr.net",
  "tomis@kvados.cz",
  "tomsarnost@seznam.cz",
  "tomsvob@med.muni.cz",
  "tondastrnad@volny.cz",
  "trdlicka@tiscali.cz",
  "tschoster@seznam.cz",
  "uca@seznam.cz",
  "uhlirovi.vlcice@wo.cz",
  "v.art@seznam.cz",
  "v.paliskova@centrum.cz",
  "vaclav.tymocko@seznam.cz",
  "vaclav.wagner@degu.cz",
  "vaclavsky@mujmejl.cz",
  "vapch@seznam.cz",
  "VCurylo@seznam.cz",
  "vejmelek@yahoo.com",
  "vendula.zimova@volny.cz",
  "veraschlemmerova@seznam.cz",
  "vhana@iol.cz",
  "vhranacova@seznam.cz",
  "vit.albrecht@cmail.cz",
  "vit.grec@tiscali.cz",
  "vit.hamala@kleibl.cz",
  "vit.stepanek@olomouc.cz",
  "vitezslavkares@medatron.cz",
  "vitnec@seznam.cz",
  "vkoronthaly@seznam.cz",
  "vlacilpavel@seznam.cz",
  "vladimirvecera@email.cz",
  "---vlado.zelik@apsoft.sk",
  "vlastuse@centrum.cz",
  "vlcek@vz.cz",
  "vodak@familycoaching.cz",
  "vojtech.brazdil@cmss-oz.cz",
  "vojtech.vrana@hella.com",
  "vojtechryza@quick.cz",
  "---vojtekj@piar.gtn.sk",
  "vrandysek@tiscali.cz",
  "vrandysek@tiscali.cz",
  "vuk@email.cz",
  "we.805@bauhaus.cz",
  "ymca@setkani.org",
  "Z.Krtek@seznam.cz",
  "zaboj@arcibol.cz",
  "zbynek.d@email.cz",
  "zbynek.kral@tiscali.cz",
  "zdena@hegrlik.cz",
  "zdenek.sychra@mybox.cz",
  "zdrahal@pod.cz",
  "zhabr@csas.cz",
  "zpetruj@qgir.cz",
  "zuzana.kolosova@seznam.cz",
  "zuzana.kostrhonova@seznam.cz",
  "zuzka.vlcek@seznam.cz",
  "zverinovi@raz-dva.cz"
),
'jarda'=> array(
  "1daf@seznam.cz",
  "2martin.kolar@seznam.cz",
  "a.asana@seznam.cz",
  "a.m.malerovi@worldonline.cz",
  "adamik@cbox.cz",
  "ajazbilovic@seznam.cz",
  "akasicky@csas.cz",
  "al.kubik@volny.cz",
  "alena.zmrzla@seznam.cz",
  "alenahusakova@seznam.cz",
  "alorenc@nbox.cz",
  "angio@vol.cz",
  "anka@volny.cz",
  "anna.eis@post.cz",
  "anna.eyerova@seznam.cz",
  "annastrzelcova@centrum.cz",
  "antonin.koudelka@seznam.cz",
  "antonin.tesacek@seznam.cz",
  "audiodan@centrum.cz",
  "babicek@centroprojekt.cz",
  "bajapavlaskova@seznam.cz",
  "bammarkovi@quick.cz",
  "Bartova.Blanka@seznam.cz",
  "Bartova.Blanka@seznam.cz",
  "---bbrezina@gity.cz",
  "bednar@datasys.cz",
  "bednarikoval@seznam.cz",
  "Belmondo1@seznam.cz",
  "beranci@seznam.cz",
  "Bernadetta@email.cz",
  "bezouska.v@seznam.cz",
  "blahmarie@centrum.cz",
  "blanka@kubicekairtex.cz",
  "blazek.iv@atlas.cz",
  "bohca@post.cz",
  "bohunka_jirka@volny.cz",
  "bonaventura@kapucini.cz",
  "Bortel@alve.cz",
  "brothanek@seznam.cz",
  "btriska@zpmvcr.cz",
  "bucek@fem.cz",
  "bures.j@mujbox.cz",
  "cagala@volny.cz",
  "castul@seznam.cz",
  "cerny.vavrovice@seznam.cz",
  "cerny@proglas.cz",
  "cichonjosef@seznam.cz",
  "cpr@doo.cz",
  "cprop@doo.cz",
  "cprvyzva@doo.cz",
  "cyrusova@gmail.cz",
  "dag.blahova@seznam.cz",
  "dag123@centrum.cz",
  "dagmar.kolarova@gmail.com",
  "Dagmar.Sera@seznam.cz",
  "dagmar.wormova@seznam.cz",
  "dagmar_foltova@centrum.cz",
  "dalimil.barton@meac.cz",
  "dana@hydahesi.cz",
  "daniel.bednarik@seznam.cz",
  "daniel@exo.cz",
  "darina.brothankova@seznam.cz",
  "devetter@upb.cas.cz",
  "dobes-pavel@seznam.cz",
  "dobrakniha@wo.cz",
  "dobrovolny@biskupstvi.cz",
  "dolezal.jura@seznam.cz",
  "dolezal.jura@seznam.cz",
  "dolezelova@biskupstvi.cz",
  "doming@volny.cz",
  "Drimalova.Marie@seznam.cz",
  "---dům@setkani.org,katka.k@aschool.cz",
  "dvorak@mikulovice.cz",
  "e.mercl@seznam.cz",
  "editmoravcova@email.cz",
  "eduard.strzelec@centrum.cz",
  "educos@mbox.vol.cz",
  "---ekonomix_hujo@nextra.sk",
  "emgkl@tiscali.cz",
  "emil_vodicka@kb.cz",
  "EmilieZichova@seznam.cz",
  "erika.domasikova@caritas.cz",
  "erika.domasikova@tiscali.cz",
  "esvoboda@seznam.cz",
  "eva.merclova@centrum.cz",
  "eva.pazourkova@seznam.cz",
  "evahut@seznam.cz",
  "evakalvinska@seznam.cz",
  "evanevolova@seznam.cz",
  "f.hellebrand@seznam.cz",
  "f.zajicek@med.muni.cz",
  "fanduli@atlas.cz",
  "fara.zeleznice@centrum.cz",
  "fara.zeleznice@centrum.cz",
  "farnost@sdb.cz",
  "---feketovam@pobox.sk",
  "fiser.projekce@quick.cz",
  "fmoravcik@satos.cz",
  "frantisek.koudelka@post.cz",
  "frantisek.vomacka@unex.cz",
  "frantisekchvatik@muzlin.cz",
  "fryc@pmbs.cz",
  "fuis@fme.vutbr.cz",
  "fuis@seznam.cz",
  "fuisova.miroslava@brno.cz",
  "fusekle@centrum.cz",
  "gargalici@tiscali.cz",
  "genzerm@atlas.cz",
  "genzerovap@atlas.cz",
  "geocart@c-mail.cz",
  "georgeII@seznam.cz",
  "gita.vyletalova@email.cz",
  "glogar@centrum.cz",
  "glogar@odry.cz",
  "grebikjosef@seznam.cz",
  "gregor.ludek@vol.cz",
  "gregor@cmg.prostejov.cz",
  "gutu@centrum.cz",
  "h.vyslouzilova@centrum.cz",
  "hajt.marketa@seznam.cz",
  "hamrikovi@volny.cz",
  "hana.dalikova@email.cz",
  "hana.malcova@centrum.cz",
  "hana.pistorova@familia.cz",
  "hanadrahomir@seznam.cz",
  "hanka.barankova@centrum.cz",
  "hanka.brichova@centrum.cz",
  "hannybuch@seznam.cz",
  "havelka.jirka@centrum.cz",
  "hazy@centrum.cz",
  "hcajankova@seznam.cz",
  "hej_rup@volny.cz",
  "hesta@hesta.cz",
  "himramovska@gmail.com",
  "HMa@seznam.cz",
  "hndh@volny.cz",
  "hnizda.kamil@seznam.cz",
  "horacek.buk@seznam.cz",
  "horacek@mendelu.cz",
  "horakova.vlasta@seznam.cz",
  "horakovia.h@email.cz",
  "hosek.7@seznam.cz",
  "hostickova.j@seznam.cz",
  "houskaj@post.cz",
  "HPerinova@seznam.cz",
  "hstefek@meding.cz",
  "cho.zdislava@caritas.cz",
  "chrpa.pavla@seznam.cz",
  "---i.šalek@kvapil-elektro.cz",
  "ificzka@seznam.cz",
  "info@ruff.cz",
  "ing.jiri.brtnik@seznam.cz",
  "irena.hi@centrum.cz",
  "irena.smekalova@centrum.cz",
  "iva-kasparkova@seznam.cz",
  "ivan.kolos@seznam.cz",
  "ivana.jenistova@caritas.cz",
  "ivanamatusu@seznam.cz",
  "ivanpodest@seznam.cz",
  "ivo.kalvinsky@seznam.cz",
  "j.babicek@seznam.cz",
  "j.baletka@kvapil-elektro.cz",
  "j.brauner@volny.cz",
  "j.fidrmuc@volny.cz",
  "j.kordik1@tiscali.cz",
  "j.kordik1@tiscali.cz",
  "j.kvapil@kvapil-elektro.cz",
  "j.kvetakova@seznam.cz",
  "j.orlik@seznam.cz",
  "j.solovsky@centrum.cz",
  "j.zelinka@centrum.cz",
  "jafra@quick.cz",
  "jakubectruhlarstvi@seznam.cz",
  "jan.eis@post.cz",
  "jan.eyer@centrum.cz",
  "jan.havlicek@spvs.cz",
  "jan.chrastecky@siemens.com",
  "jan.janoska@volny.cz",
  "jan.juran@velkabystrice.cz",
  "JAN.LOUCKA1959@seznam.cz",
  "jan.mantl@tiscali.cz",
  "jan.mikolas@volny.cz",
  "jan.rotter@seznam.cz",
  "jan.straka@centrum.cz",
  "jana.jarolimova@email.cz",
  "jana.steinocherova@vzp.cz",
  "jana.tobolikova@seznam.cz",
  "jana.vodakova@centrum.cz",
  "jana@svika.eu",
  "janajagerova@seznam.cz",
  "janakadr@centrum.cz",
  "janakopriva@seznam.cz",
  "janavcelova@centrum.cz",
  "Janda.Jakub@seznam.cz",
  "jane396@email.cz",
  "janjager@seznam.cz",
  "---jankoatonka@centrum.sk",
  "jarekkriz@volny.cz",
  "jaro.slavte@seznam.cz",
  "jaromir.kvapil@iol.cz",
  "jaromir_sevela@rutronik.com",
  "jaroslava.randyskova@seznam.cz",
  "jelinekjosef@volny.cz",
  "jerabkovam@email.cz",
  "jforbelsky@ic-energo.cz",
  "jhutar@volny.cz",
  "jic-havlikova@centrum.cz",
  "jidelna.msukrajinska@seznam.cz",
  "jindra.sandmark@kolumbus.fi",
  "jindra.sandmark@kolumbus.fi",
  "jindrich.honek@seznam.cz",
  "jiri.holik@volny.cz",
  "jiri.linart@centrum.cz",
  "jiri.malec@volny.cz",
  "jiri.satke@cewood.cz",
  "jiri.slimarik@volny.cz",
  "jiri.smahel@gmail.com",
  "jiri.stuchly@vitkovice.cz",
  "jiri@doffek.cz",
  "jirkalachman@tiscali.cz",
  "jirky.peska@tiscali.cz",
  "jitahory@seznam.cz",
  "jitka_vodickova@kb.cz",
  "jjgoth@razdva.cz",
  "jjsebek@volny.cz",
  "jjvavrovi@centrum.cz",
  "jkasicka@csas.cz",
  "JKristkova@ksoud.brn.justice.cz",
  "jkuncar@cpdirect.cz",
  "jledl@quick.cz",
  "jmaisnerova@seznam.cz",
  "jmalasek@volny.cz",
  "josef.cervenka@volny.cz",
  "josef.hutar@golemfinance.cz",
  "josef.liberda@mujes.cz",
  "josef.neruda@dalkia.cz",
  "josef_havlik@centrum.cz",
  "josefka.koutna@seznam.cz",
  "joshavlik@centrum.cz",
  "jprokopova@seznam.cz",
  "jslachtova@seznam.cz",
  "jura.stransky@seznam.cz",
  "jurankova@proglas.cz",
  "just.t@seznam.cz",
  "just@seznam.cz",
  "juty@seznam.cz",
  "jvpsimon@seznam.cz",
  "jzich@atlas.cz",
  "k.stepanek@email.cz",
  "ka.urbanova@seznam.cz",
  "kabatovi@gmail.com",
  "kaja@skritci.com",
  "kalabova@tiscali.cz",
  "karel.audit@centrum.cz",
  "karel.bartos@centrum.cz",
  "karel.rysavy@post.cz",
  "kaspic@seznam.cz",
  "katerina.remesova@seznam.cz",
  "katkadol@seznam.cz",
  "katkadol@seznam.cz",
  "kintrova@proglas.cz",
  "klanov@seznam.cz",
  "Klasek.Robert@uhul.cz",
  "klihavcovi@seznam.cz",
  "kmj.friedl@seznam.cz",
  "Knopf.Stanislav@seznam.cz",
  "kodek.petruj@centrum.cz",
  "kodek@iol.cz",
  "kohoutek127@seznam.cz",
  "komfort-jc@seznam.cz",
  "kostrhon@seznam.cz",
  "kpejchalova@seznam.cz",
  "krajca.f@seznam.cz",
  "kreces1.edu@mail.cez.cz",
  "krejc.rce@seznam.cz",
  "kristkovajana@seznam.cz",
  "krizalkovicova@seznam.cz",
  "krizvlastimil@seznam.cz",
  "krsekjaroslav@seznam.cz",
  "kstepanek@gity.cz",
  "kulihrasek.jiri@seznam.cz",
  "kutna.hora@cb.cz",
  "kvapilovajaroslava@seznam.cz",
  "kytienka@seznam.cz",
  "l.hudcova@gmail.com",
  "l.Kabatova@qmail.cz",
  "---lacop@merina.sk",
  "lachman@msmt.cz",
  "lakosilova@laksmanna.cz",
  "lakosilova@laksmanna.cz",
  "lancelot@demdaal.cz",
  "leni25@seznam.cz",
  "lenka.ryzova@centrum.cz",
  "lenka_sevelova@post.cz",
  "lenkadrabek@centrum.cz",
  "Lhrad@seznam.cz",
  "---libuse.fiserova@unimilts.cz",
  "lidajetlebova@seznam.cz",
  "lidkacerna@seznam.cz",
  "liduska127@seznam.cz",
  "limail@seznam.cz",
  "limramovsky@gmail.com",
  "Ljuba.Stranska@seznam.cz",
  "lmp@volny.cz",
  "lnenicka.Jiri@seznam.cz",
  "louckova.marie@seznam.cz",
  "lraus@tenza.cz",
  "ltyrnerova@seznam.cz",
  "lubomir.zacek@rwe.cz",
  "lubomir@cmail.cz",
  "lucie.borakova@seznam.cz",
  "ludmila.liberdova@mujes.cz",
  "Ludmila.Lnenickova@setkani.org",
  "ludmila.loksova@seznam.cz",
  "ludva@hegrlik.cz",
  "ludvikmichlovsky@seznam.cz",
  "lukcas@seznam.cz",
  "lukl@iol.cz",
  "m.a.markova@volny.cz",
  "m.stula@worldonline.cz",
  "m.tvrda@seznam.cz",
  "m.zelinkova@centrum.cz",
  "maba@o2active.cz",
  "majka.feketeova@gmail.com",
  "majkace@volny.cz",
  "majkasimonova@seznam.cz",
  "makoudelkova@seznam.cz",
  "mamb@seznam.cz",
  "Marcela.Hoskova@seznam.cz",
  "marek.milan@centrum.cz",
  "marek.pospisil@seznam.cz",
  "marek_janca@quick.cz",
  "maria.cerna@seznam.cz",
  "marie.sevcik@seznam.cz",
  "marie.stavinohova@gmail.com",
  "marie.wawraczova@volny.cz",
  "mariedanek@centrum.cz",
  "mariegajduskova@gmail.com",
  "mariekanovska@seznam.cz",
  "market.dvorakova@gmail.cz",
  "marketa.vit@email.cz",
  "martaluzarova@seznam.cz",
  "martamo@seznam.cz",
  "martin.busina@seznam.cz",
  "martin.cajanek@osu.cz",
  "martin.chromjak@tiscali.cz",
  "martin@smidek.eu",
  "martina.babickova@seznam.cz",
  "martina.friedlova@upol.cz",
  "---martinek@pmgastro.cz",
  "martinka.koudelkova@seznam.cz",
  "martinka.petr@seznam.cz",
  "martinka.stepanek@olomouc.cz",
  "MartinVareka@seznam.cz",
  "MartinVareka@seznam.cz",
  "mbcko@centrum.cz",
  "medium@email.cz",
  "mezulanik@proglas.cz",
  "mfridrichova@prorodiny.cz",
  "mgraffinger@gmail.com",
  "mholikova@volny.cz",
  "mhubacekza@volny.cz",
  "michal@garden114.cz",
  "michalcova.hana@centrum.cz",
  "michalec.zdenek@inset.com",
  "mika@diamo.cz",
  "milada.barotova@gmail.com",
  "Milada.bortlova@cpzp.cz",
  "milada.maliskova@centrum.cz",
  "milada.n@centrum.cz",
  "milan.barot@gmail.com",
  "milan.bily@volny.cz",
  "milan.duben@gist.cz",
  "milan.jebavy@tiscali.cz",
  "milan.svojanovsky@seznam.cz",
  "milana.vykydalova@centrum.cz",
  "milansoldan@muzlin.cz",
  "milenapchalkova@seznam.cz",
  "milence@centrum.cz",
  "milos.vyletal@email.cz",
  "mira.svec@wo.cz",
  "mirek@kadrnozka.cz",
  "mirek_dvorak@volny.cz",
  "mirf@volny.cz",
  "miriam.louckova@seznam.cz",
  "miroslav.borak@T-mobile.cz",
  "miroslav.sot@centrum.cz",
  "miroslav-kotek@seznam.cz",
  "MJarolim@seznam.cz",
  "mkotek@ic-energo.eu",
  "modry.slon@volny.cz",
  "moni.dol@volny.cz",
  "mosikora@centrum.cz",
  "mpolak@centrum.cz",
  "mrazek.ondra@seznam.cz",
  "mrazkova14@seznam.cz",
  "mrozek@techfloor.cz",
  "mrozkova.agata@seznam.cz",
  "MSujanova@seznam.cz",
  "mujbracha@gmail.com",
  "mv@martinvana.net",
  "mzatecky@orcz.cz",
  "mzatecky@seznam.cz",
  "nagl@arcibol.cz",
  "necas@anete.com",
  "nedvedova.zdislava@pontis.cz",
  "nemec_pavel@hotmail.com",
  "nerudv1@feld.cvut.cz",
  "norin@email.cz",
  "novakpm@volny.cz",
  "novotny_jena@centrum.cz",
  "novsla@seznam.cz",
  "ohral@iol.cz",
  "ohralova@email.cz",
  "oldtom@t-email.cz",
  "olgaolivova@centrum.cz",
  "ondracekm@seznam.cz",
  "ondranicka@seznam.cz",
  "ondrej.mrazek@schiedel.cz",
  "ondrejremes@atlas.cz",
  "osikora@seznam.cz",
  "oslama@fnbrno.cz",
  "oto.worm@seznam.cz",
  "p.braunerova@seznam.cz",
  "p.folta@centrum.cz",
  "p.hudec@email.cz",
  "p.janoskova@seznam.cz",
  "p.kvapil@kvapil-elektro.cz",
  "p.ne@seznam.cz",
  "p_blaha@kb.cz",
  "pa.vaclav@seznam.cz",
  "pase@seznam.cz",
  "pavcerny@volny.cz",
  "pavel.fiser@ubcz.cz",
  "pavel.kyska@volny.cz",
  "pavel.obluk@dchoo.caritas.cz",
  "pavel.vagunda@atlas.cz",
  "pavel.vanicek@gsagency.cz",
  "pavel.vit@tycoelectronics.com",
  "pavel@pneuprochazka.cz",
  "pavelcejnek@seznam.cz",
  "paveldobe@seznam.cz",
  "pavelhranac@gmail.com",
  "pavel-ryska@centrum.cz",
  "pavelsevcik76@seznam.cz",
  "pavelsmolko@yahoo.com",
  "pavla.rybova@caritas.cz",
  "pavla1.ticha@seznam.cz",
  "pavlik@intext.cz",
  "pavlinahajna@centrum.cz",
  "pdaniela@centrum.cz",
  "pebursik@volny.cz",
  "pek@redis.cz",
  "pepa.ondracek@seznam.cz",
  "pesek@it.cas.cz",
  "peta.dolik@seznam.cz",
  "peterescu@seznam.cz",
  "petr.brich@centrum.cz",
  "petr.d@volny.cz",
  "Petr.Janda@centrum.cz",
  "petr.klasek@seznam.cz",
  "petr.otr@worldonline.cz",
  "petr.polansky@email.cz",
  "petr.schlemmer@nemspk.cz",
  "petr.schlemmer@seznam.cz",
  "petr.wajda@centrum.cz",
  "petr@skritci.com",
  "petr_bezpalec@volny.cz",
  "petra.krupickova@seznam.cz",
  "petra.vin@seznam.cz",
  "petra.vyn@centrum.cz",
  "petra@doffek.cz",
  "petra@ibp.cz",
  "petrkvetak@seznam.cz",
  "petrmatula@atlas.cz",
  "petrprokop@seznam.cz",
  "Petsti@email.cz",
  "pgadas@razdva.cz",
  "pchalek.tomas@seznam.cz",
  "pchalenka@seznam.cz",
  "pilnam@seznam.cz",
  "pilny.spp@volny.cz",
  "pipvovo@mail.ru",
  "PLECHACP@fnplzen.cz",
  "polak@synerga.cz",
  "policer@seznam.cz",
  "ponizil@agritec.cz",
  "ponizil@salvo.zlin.cz",
  "porkertova@seznam.cz",
  "ppejchal@seznam.cz",
  "ppr@doo.cz",
  "---prais@premie.cz",
  "PriessnitzJan@seznam.cz",
  "prihojana@seznam.cz",
  "prochazkova.petra@volny.cz",
  "prochazkovak@email.cz",
  "psmola@email.cz",
  "pstefkova@meding.cz",
  "pstoklasa@krok-hranice.cz",
  "ptacnik@taxnet.cz",
  "putzlachers@centrum.cz",
  "pvaclav@centrum.cz",
  "pwawracz@volny.cz",
  "r.b@seznam.cz",
  "r.hadraba@seznam.cz",
  "rad.dost@seznam.cz",
  "Radka.And@seznam.cz",
  "radka@fischerovi.cz",
  "radka_hazova@mik-bohemia.cz",
  "radonbob@seznam.cz",
  "RadovanHolik@seznam.cz",
  "rasticova@volny.cz",
  "---rastislav.pocubay@st.nicolaus.sk",
  "RausovaRut@seznam.cz",
  "rbrazda@infotech.cz",
  "rcajanek@seznam.cz",
  "rek@seznam.cz",
  "rene@fischerovi.eu",
  "rodina.tomsova@worldonline.cz",
  "rodiny.prerov@seznam.cz",
  "roman.mokrosz@post.cz",
  "roman.strossa@o2active.cz",
  "roman.strossa@o2active.cz",
  "rosovaradka@seznam.cz",
  "rostislav.kulisan@cirkevnizs.hradecnm.indos.cz",
  "rp.barton@seznam.cz",
  "rpavelkova@dpmb.cz",
  "ruckovi@seznam.cz",
  "rybasvatopluk@seznam.cz",
  "sakul208@email.cz",
  "sandholzova@seznam.cz",
  "sapak.vojtech@volny.cz",
  "saranch@seznam.cz",
  "sdostal@doo.cz",
  "seifriedovi@seznam.cz",
  "selucka.monika@seznam.cz",
  "sequens@seznam.cz",
  "sevros@centrum.cz",
  "shorackova@centrum.cz",
  "schnirch@volny.cz",
  "SimeckovaAndrea@seznam.cz",
  "simerda@vues.cz",
  "simona.hybsova@centrum.cz",
  "simtec@post.cz",
  "siskovi@centrum.cz",
  "skoloud.p@seznam.cz",
  "sladek@opr.ova.cd.cz",
  "slachtajan@seznam.cz",
  "slavomir.mrozek@seznam.cz",
  "slivka@signalbau.cz",
  "smidkova@proglas.cz",
  "snejdar@seznam.cz",
  "sojovejrizek@gmail.com",
  "solano@centrum.cz",
  "sotkovskyr@centrum.cz",
  "sotovi@tiscali.cz",
  "soubusta@sloup.upol.cz",
  "soucek@vukrom.cz",
  "srandyskova@seznam.cz",
  "srsnovi@email.cz",
  "ssmolova@email.cz",
  "stanek.p@volny.cz",
  "stanislav.foltyn@o2.com",
  "stastnik@volny.cz",
  "stastnikovah@seznam.cz",
  "stevenix@seznam.cz",
  "stoklaskovi.tas@seznam.cz",
  "strakova.misa@centrum.cz",
  "stredisko.catarina@centrum.cz",
  "stuchlamarie@seznam.cz",
  "stuchly.rudolf@seznam.cz",
  "stykar@mendelu.cz",
  "stykar@mendelu.cz",
  "sujan.michl@seznam.cz",
  "sujanovaZora@seznam.cz",
  "sujanovi@tiscali.cz",
  "svarservis@svarservis.cz",
  "svecjarda@atlas.cz",
  "svika.petr@seznam.cz",
  "sykorova7@seznam.cz",
  "szymikovi@volny.cz",
  "szymikovi@volny.cz",
  "terezie.gilgova@email.cz",
  "tetra.jurka@seznam.cz",
  "tholik@volny.cz",
  "tkacovi@seznam.cz",
  "tobolik.petr@seznam.cz",
  "tomaluk@centrum.cz",
  "tomas.urban@btm.cz",
  "tomas@vichr.net",
  "tomasgilg@seznam.cz",
  "tomecek.pavel@centrum.cz",
  "tomsvob@med.muni.cz",
  "tschoster@seznam.cz",
  "tykadlik@iex.cz",
  "uhlirovi.vlcice@wo.cz",
  "v.art@seznam.cz",
  "v.zdrahal@seznam.cz",
  "vaclav.marsik@bolid-m.cz",
  "vaclav.tymocko@seznam.cz",
  "vaclav.vacek@wo.cz",
  "vaclav.vacek@wo.cz",
  "vaclav.wagner@degu.cz",
  "vaclavsky@mujmejl.cz",
  "vanaHana@seznam.cz",
  "vapch@seznam.cz",
  "vavrajan@centrum.cz",
  "VCurylo@seznam.cz",
  "vecerova@arcibol.cz",
  "vejmelek@yahoo.com",
  "vera.zackova@eon.cz",
  "veraschlemmerova@seznam.cz",
  "vhana@iol.cz",
  "vhranacova@seznam.cz",
  "vit.albrecht@cmail.cz",
  "vit.stepanek@olomouc.cz",
  "vita.ham@seznam.cz",
  "vitezslava.sujanova@seznam.cz",
  "vitnec@seznam.cz",
  "vlacilpavel@seznam.cz",
  "vladimirkana@seznam.cz",
  "vladimirvecera@email.cz",
  "vlastuse@centrum.cz",
  "vodak@familycoaching.cz",
  "vojtech.ryza@centrum.cz",
  "vojtech.vrana@hella.com",
  "---vojtekj@piar.gtn.sk",
  "---vojtekovcija@mail.t-com.sk",
  "vpazourek@seznam.cz",
  "vrandysek@tiscali.cz",
  "VSAI@seznam.cz",
  "vuk@email.cz",
  "vydlak@agrocs.cz",
  "we.805@bauhaus.cz",
  "XKay@seznam.cz",
  "ymca@setkani.org",
  "Z.Krtek@seznam.cz",
  "zaclonka98@gmail.com",
  "zajicek.honza@seznam.cz",
  "zaoral@zast.cz",
  "zaoralova@zast.cz",
  "zbynek.d@email.cz",
  "zdena@hegrlik.cz",
  "zdena@hegrlik.cz",
  "zdenka.wajdova@centrum.cz",
  "zdislava.nedvedova@post.cz",
  "---zelik@apsoft.sk",
  "zhabr@csas.cz",
  "zlamalo367@seznam.cz",
  "zuzana.kolosova@seznam.cz",
  "zuzana.kostrhonova@seznam.cz",
  "zverinovi@raz-dva.cz"
)
);



?>
