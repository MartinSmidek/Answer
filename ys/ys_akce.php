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
  $qry= "SELECT id_akce,source,akce FROM ms_druhakce ";
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
function akce_sestava($akce,$par) {
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
//          JOIN ms_druhakce AS ma ON ma.id_akce=mk.id_akce
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
  // titulky
  foreach ($tits as $id) {
    $ths.= "<th>$id</th>";
  }
  foreach ($clmn as $i=>$c) {
    $tab.= "<tr>";
    foreach ($c as $id=>$val) {
      $tab.= "<td>$val</td>";
    }
    $tab.= "</tr>";
  }
  $html.= "<table class='stat'><tr>$ths</tr>$tab</table>";
  return (object)array(html=>$html,href=>$href);
}
# ================================================================================================== GOOGLE
# -------------------------------------------------------------------------------------------------- akce_roku_id
# definuj klíč dané akce jeko klíč akce z aplikace MS.EXE
function akce_roku_id($akce,$rok,$id_akce) {
  if ( $id_akce ) {
    mysql_qry("UPDATE ms_druhakce SET ciselnik_akce=$akce,ciselnik_rok=$rok WHERE id_akce=$id_akce");
  }
  return 1;
}
# -------------------------------------------------------------------------------------------------- akce_roku_update
# přečtení listu $rok z tabulky ciselnik_akci a zapsání dat do tabulky
# načítají se jen řádky ve kterých typ='a'
function akce_roku_update($rok) {  trace();
  $n= 0;
  require_once 'Zend/Loader.php';
  Zend_Loader::loadClass('Zend_Http_Client');
  Zend_Loader::loadClass('Zend_Gdata');
  Zend_Loader::loadClass('Zend_Gdata_ClientLogin');
  Zend_Loader::loadClass('Zend_Gdata_Spreadsheets');
  // autentizace
  $authService= Zend_Gdata_Spreadsheets::AUTH_SERVICE_NAME;
  $httpClient= Zend_Gdata_ClientLogin::getHttpClient('martin@smidek.eu', 'radost', $authService);
//   $httpClient= Zend_Gdata_ClientLogin::getHttpClient('web@setkani.org', 'radost', $authService);
  // nalezení tabulky ciselnik_akci
  $gdClient= new Zend_Gdata_Spreadsheets($httpClient);
  $feed= $gdClient->getSpreadsheetFeed();
  $table= firstFeed($feed,"ciselnik_akci");
  if ( $table ) {
    // pokud tabulka existuje
    $table_id= split('/', $table->id->text);
    $table_key= $table_id[5];
    // listy
    $query= new Zend_Gdata_Spreadsheets_DocumentQuery();
    $query->setSpreadsheetKey($table_key);
    $feed= $gdClient->getWorksheetFeed($query);
    $ws= firstFeed($feed,$rok);
  }
  if ( $table && $ws ) {
    // pokud list tabulky existuje
    $ws_id= split('/', $ws->id->text);
    $ws_key= $ws_id[8];
    // načti buňky
    $query= new Zend_Gdata_Spreadsheets_CellQuery();
    $query->setSpreadsheetKey($table_key);
    $query->setWorksheetId($ws_key);
    $feed= $gdClient->getCellFeed($query);
    $max_n= 0;
    foreach($feed->entries as $entry) {
      if ($entry instanceof Zend_Gdata_Spreadsheets_CellEntry) {
        $An= $entry->title->text;
        $A= substr($An,0,1); $n= substr($An,1); $max_n= max($max_n,$n);
        $cells[$A][$n]= $entry->content->text;
      }
    }
//                                                 debug($cells,"akce $rok");
    // zrušení daného roku v GAKCE
    $qry= "DELETE FROM gakce WHERE grok=$rok";
    $res= mysql_qry($qry);
    // výběr a-záznamů a zápis do GAKCE
    $values= ''; $del= '';
    for ($i= 1; $i<$max_n; $i++) {
      if ( $cells['A'][$i]=='a' ) {
        $n++;
        $akce= $cells['B'][$i];
        $nazev= mysql_real_escape_string($cells['C'][$i]);
        $values.= "$del($rok,$akce,\"$nazev\")";
        $del= ',';
      }
    }
    $qry= "INSERT INTO gakce (grok,gakce,gnazev) VALUES $values";
    $res= mysql_qry($qry);
  }
  // konec
  return $n;
}
# -------------------------------------------------------------------------------------------------- akce_roku
# přečtení listu $rok z tabulky ciselnik_akci ve formátu pro browse_fill
# načítají se jen řádky ve kterých typ='a'
function akce_roku($rok) {  trace();
  $fill= '';
  require_once 'Zend/Loader.php';
  Zend_Loader::loadClass('Zend_Http_Client');
  Zend_Loader::loadClass('Zend_Gdata');
  Zend_Loader::loadClass('Zend_Gdata_ClientLogin');
  Zend_Loader::loadClass('Zend_Gdata_Spreadsheets');
  // autentizace
  $authService= Zend_Gdata_Spreadsheets::AUTH_SERVICE_NAME;
  $httpClient= Zend_Gdata_ClientLogin::getHttpClient('martin@smidek.eu', 'radost', $authService);
//   $httpClient= Zend_Gdata_ClientLogin::getHttpClient('web@setkani.org', 'radost', $authService);
  // nalezení tabulky ciselnik_akci
  $gdClient= new Zend_Gdata_Spreadsheets($httpClient);
  $feed= $gdClient->getSpreadsheetFeed();
  $table= firstFeed($feed,"ciselnik_akci");
  if ( $table ) {
    // pokud tabulka existuje
    $table_id= split('/', $table->id->text);
    $table_key= $table_id[5];
    // listy
    $query= new Zend_Gdata_Spreadsheets_DocumentQuery();
    $query->setSpreadsheetKey($table_key);
    $feed= $gdClient->getWorksheetFeed($query);
    $ws= firstFeed($feed,$rok);
  }
  if ( $table && $ws ) {
    // pokud list tabulky existuje
    $ws_id= split('/', $ws->id->text);
    $ws_key= $ws_id[8];
    // načti buňky
    $query= new Zend_Gdata_Spreadsheets_CellQuery();
    $query->setSpreadsheetKey($table_key);
    $query->setWorksheetId($ws_key);
    $feed= $gdClient->getCellFeed($query);
    $max_n= 0;
    foreach($feed->entries as $entry) {
      if ($entry instanceof Zend_Gdata_Spreadsheets_CellEntry) {
        $An= $entry->title->text;
        $A= substr($An,0,1); $n= substr($An,1); $max_n= max($max_n,$n);
        $cells[$A][$n]= $entry->content->text;
      }
    }
    // spojení s tabulkou ms_druhakce
    $ids_akce= array();
    $qry= "SELECT id_akce,ciselnik_akce FROM ms_druhakce
           WHERE ciselnik_rok=$rok ";
    $res= mysql_qry($qry);
    while ( $res && $a= mysql_fetch_object($res) ) {
      $ids_akce[$a->ciselnik_akce]= $a->id_akce;
    }
    // výběr a-záznamů a doplnění id_akce
    $del= '';
    for ($i= 1; $i<$max_n; $i++) {
      if ( $cells['A'][$i]=='a' ) {
        $akce= $cells['B'][$i];
        $id_akce= isset($ids_akce[$akce]) ? $ids_akce[$akce] : 0;
        //$id_uakce= $rok==2009 && $akce==413 ? 74 : 0;
        $fill.= "$del$id_akce|$rok|$akce|{$cells['C'][$i]}";
        $del= "|";
      }
    }
  }
  // konec
  return $fill;
}
# --------------------
function firstFeed($feed,$id=null) {
  $entry= null;
  foreach($feed->entries as $e) {
    if ( $id ) {
      if ( $e->title->text==$id ) {
        $entry= $e;
        break;
      }
    }
    else {
      $entry= $e;
      break;
    }
  }
  return $entry;
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
         FROM ms_druhakce
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
?>