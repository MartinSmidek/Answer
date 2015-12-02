<?php # (c) 2009-2015 Martin Smidek <martin@smidek.eu>
/** ======================================================================================> POKLADNA **/
# ---------------------------------------------------------------------------------- pipe_pdenik_typ
// 0=V 1=P
function pipe_pdenik_typ ($x,$save=0) {
  if ( $save ) {     // převeď zobrazení na uložení
    $z= $x=='V' ? 1 : 2;
  }
  else {             // převeď uložení na zobrazení
    $z= $x==1 ? 'V' : 'P';
  }
  return $z;
}
# ---------------------------------------------------------------------------------- p_pdenik_insert
# form_make
# $cislo==0 způsobí nalezení nového čísla dokladu
function p_pdenik_insert($typ,$org,$org_abbr,$datum) {
  global $x,$y;
  // převzetí hodnot
                                                          debug(Array($typ,$org,$cislo,$datum),'p_denik_insert');
  $db= $x->db;
  $select= array();
  make_get($set,$select,$fields);
  // nalezení nového čísla dokladu (v každé pokladně se zvlášť číslují příjmy a výdaje)
  $year= substr(trim($datum),-4);
  $qry= "SELECT max(cislo) as c FROM $db.pdenik WHERE org=$org AND typ=$typ AND year(datum)=$year";
  $res= mysql_qry($qry);
  if ( $res && $row= mysql_fetch_assoc($res) ) {
    $cislo= 1+$row['c'];
  }
  if ( $cislo ) {
    $elem= new stdClass;
    $elem->cislo= $cislo;
    $y->load= $elem;
    // vytvoření dokladu
//                                                           debug($set);
    $s= implode(',',$set['pdenik']);
    $ident= $org_abbr.($typ==1?'V':'P').substr($year,2,2).'_'.str_pad($cislo,5,'0',STR_PAD_LEFT);
    $qry= "INSERT INTO $db.pdenik SET $s,org=$org,typ=$typ,cislo=$cislo,ident='$ident'";
    $res= mysql_qry($qry);
    $y->key= mysql_insert_id();
  }
}
# ----------------------------------------------------------------------------------- kasa_menu_show
# ki - menu
# $cond = podmínka pro pdenik nastavená ve fis_kasa.ezer
# $day =  má formát d.m.yyyy
function kasa_menu_show($k1,$k2,$k3,$cond=1,$day='',$db='ezer_ys') {
  $html= "<div class='CSection CMenu'>";
  switch ( "$k2 $k3" ) {
  case 'stav aktualne':
    $dnes= date('j.n.Y');
    $dnes_mysql= date('Y-m-d');
    $html.= "<h3 class='CTitle'>Aktuální stav pokladen ke dni $dnes</h3>";
    $year= date('Y');
    $interval= " datum BETWEEN '$year-01-01' AND '$dnes_mysql'";
    $html.= kasa_menu_comp($interval,$db);
    break;
  case 'stav s_filtrem':
    $html.= "<h3 class='CTitle'>Stav pokladen podle nastavení </h3>";
    $html.= kasa_menu_comp($cond,$db);
    $html.= "<p><i>filtr: $cond</i></p>";
    break;
  case 'stav k_datu':
    $html.= "<h3 class='CTitle'>Stav pokladen ke dni $day </h3>";
    $until= sql_date1($day,1);
    $year= substr($until,0,4);
    $interval= " datum BETWEEN '$year-01-01' AND '$until'";
    $html.= kasa_menu_comp($interval,$db);
    break;
  case 'export letos':
    $rok= date('Y');
    $html.= "<h3 class='CTitle'>Export pokladních deníků roku $rok</h3>";
    $cond= " datum BETWEEN '$rok-01-01' AND '$rok-12-31'";
    $html.= kasa_export($cond,"pokladna_{$rok}",$db);
    break;
  case 'export vloni':
    $rok= date('Y')-1;
    $html.= "<h3 class='CTitle'>Export pokladních deníků roku $rok</h3>";
    $cond= " datum BETWEEN '$rok-01-01' AND '$rok-12-31'";
    $html.= kasa_export($cond,"pokladna_{$rok}",$db);
    break;
  }
  $html.= "</div>";
  return $html;
}
# -------------------------------------------------------------------------------------- kasa_export
function kasa_export($cond,$file,$db) {
                                                display("kasa_export($cond,$file)");
  global $ezer_path_serv, $ezer_path_docs;
  require_once("$ezer_path_serv/licensed/xls/OLEwriter.php");
  require_once("$ezer_path_serv/licensed/xls/BIFFwriter.php");
  require_once("$ezer_path_serv/licensed/xls/Worksheet.php");
  require_once("$ezer_path_serv/licensed/xls/Workbook.php");
  $table= "$file.xls";
  $wb= new Workbook("docs/$table");
  $qry_p= "SELECT * FROM $db.pokladna ";
  $res_p= mysql_qry($qry_p);
  while ( $res_p && $p= mysql_fetch_object($res_p) ) {
    $ws= $wb->add_worksheet($p->abbr);
    // formáty
    $format_hd= $wb->add_format();
    $format_hd->set_bold();
    $format_hd->set_pattern();
    $format_hd->set_fg_color('silver');
    $format_dec= $wb->add_format();
    $format_dec->set_num_format("# ##0.00");
    $format_dat= $wb->add_format();
    $format_dat->set_num_format("d.m.yyyy");
    // hlavička
    $fields= explode(',','ident:11,číslo:6,datum:10,příjmy:10,výdaje:10,stav:10,od koho/komu:30,účel:30,př.:2');
    $sy= 0;
    foreach ($fields as $sx => $fa) {
      list($title,$width)= explode(':',$fa);
      $ws->set_column($sx,$sx,$width);
      $ws->write_string($sy,$sx,utf2win_sylk($title,true),$format_hd);
    }
    // data
    $qry= "SELECT * FROM $db.pdenik WHERE $cond AND pdenik.org={$p->id_pokladna} ORDER BY datum";
    $res= mysql_qry($qry);
    while ( $res && $d= mysql_fetch_object($res) ) {
      $sy++; $sx= 0;
      $ws->write_string($sy,$sx++,utf2win_sylk($d->ident,true));
      $ws->write_number($sy,$sx++,$d->cislo);
      // převod data
      $dat_y=substr($d->datum,0,4);
      $dat_m=substr($d->datum,5,2);
      $dat_d=substr($d->datum,8,2);
      $ws->write_number($sy,$sx++,(mktime(0,0,0,$dat_m,$dat_d,$dat_y)+(70*365+20)*24*60*60-82800)/(60*60*24),$format_dat);
      if ( $d->typ==1 ) {
        $ws->write_blank($sy,$sx++);
        $ws->write_number($sy,$sx++,$d->castka,$format_dec);
      } else {
        $ws->write_number($sy,$sx++,$d->castka,$format_dec);
        $ws->write_blank($sy,$sx++);
      }
      $s= $sy==1 ? "" : "F".($sy)."+";
      $ws->write_formula($sy,$sx++,"={$s}D".($sy+1)."-E".($sy+1),$format_dec);

      $ws->write_string($sy,$sx++,utf2win_sylk($d->komu,true));
      $ws->write_string($sy,$sx++,utf2win_sylk($d->ucel,true));
      $ws->write_number($sy,$sx++,$d->priloh);
    }
    $sy++;
    $sy2= $sy+2;
    $ws->write_string($sy+1,0,utf2win_sylk('CELKEM',true));
    $ws->write_formula($sy+1,3,"=SUM(D2:D$sy)",$format_dec);
    $ws->write_formula($sy+1,4,"=SUM(E2:E$sy)",$format_dec);
    $ws->write_formula($sy+1,5,"=D$sy2-E$sy2",$format_dec);
  }
  $wb->close();
  $html.= "Byl vygenerován soubor pro Excel: <a href='docs/$table'>$table</a>";
  return $html;
}
# ----------------------------------------------------------------------------------- kasa_menu_comp
function kasa_menu_comp($cond,$db) {
  $celkem= 0;
  $html= "<table>";
  $qry= "SELECT nazev, sum(if(typ=2,castka,-castka)) as s, abbr FROM $db.pdenik
        LEFT JOIN $db.pokladna ON pdenik.org=id_pokladna WHERE $cond GROUP BY pdenik.org";
  $res= mysql_qry($qry);
  while ( $res && $row= mysql_fetch_assoc($res) ) {
    $popis= $row['nazev'];
    $u= $row['abbr'];
    $stav= $row['s'];
    $celkem+= $stav;
    $mena= "CZK";
    $html.= "<tr><td align='right'><b>$stav</b></td><td align='right'>$mena v pokladně</td>"
        . "<td><b>$u</b> <i>$popis</i></td></tr>";
  }
  $html.= "<tr><td align='right'><hr/></td><td></td><td></td></tr>";
  $celkem= number_format($celkem,2,'.',' ');
  $html.= "<tr><td align='right'><b>$celkem</b></td><td>CZK celkem </td><td></td></tr>";
  $html.= "</table>";
  return $html;
}
/** ===========================================================================================> DŮM **/
# ---------------------------------------------------------------------------------- ds_compare_list
function ds_compare_list($orders) {  #trace('','win1250');
  $errs= 0;
  $html= "<dl>";
  $n= 0;
  if ( $orders ) {
    foreach (explode(',',$orders) as $order) {
      $x= ds_compare($order);
      $html.= wu("<dt>Objednávka <b>$order</b> {$x->pozn}</dt>");
      $html.= "<dd>{$x->html}</dd>";
      $errs+= $x->err;
      $n++;
    }
  }
  $html.= "</dl>";
  $msg= "V tomto období je celkem $n objednávek";
  $msg.= $errs ? ", z toho $errs neúplné." : "." ;
  $result= (object)array('html'=>$html,'msg'=>wu($msg));
  return $result;
}
# --------------------------------------------------------------------------------------- ds_compare
function ds_compare($order) {  #trace('','win1250');
  ezer_connect('setkani');
  // údaje z objednávky
  $qry= "SELECT * FROM setkani.tx_gnalberice_order WHERE uid=$order";
  $res= mysql_qry($qry);
  if ( !$res ) fce_error(wu("$order není platné číslo objednávky"));
  $o= mysql_fetch_object($res);
  // projití seznamu
  $qry= "SELECT * FROM setkani.ds_osoba WHERE id_order=$order ";
  $reso= mysql_qry($qry);
  $n= $n_0= $n_3= $n_9= $n_15= $n_a= $noroom= 0;
  while ( $reso && $u= mysql_fetch_object($reso) ) {
    // rozdělení podle věku
    $n++;
    $vek= ds_vek($u->narozeni,$o->fromday);
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
  $result= (object)array('html'=>/*wu*/($html),'form'=>$form,'err'=>$err,'pozn'=>$pozn);
  return $result;
}
# ===========================================================================================> hosté
# -------------------------------------------------------------------------------------- ds_kli_menu
# vygeneruje menu pro loňský, letošní a příští rok ve tvaru objektu pro ezer2 pro zobrazení klientů
# určující je datum zahájení pobytu v objednávce
function ds_kli_menu() {
  ezer_connect('setkani');
  $the= '';                     // první v tomto měsíci či později
  $rok= date('Y');
  $ted= date('Ym');
  $mesice= array(1=>'leden','únor','březen','duben','květen','červen',
    'červenec','srpen','září','říjen','listopad','prosinec');
  $mn= (object)array('type'=>'menu.left'
      ,'options'=>(object)array(),'part'=>(object)array());
  $letos= date('Y');
  for ($y= -1; $y<=1; $y++) {
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
      $res= mysql_qry($qry);
      while ( $res && $o= mysql_fetch_object($res) ) {
        $uids.= "$del{$o->uid}"; $del= ',';
        $objednavek++;
        $celkem+= $o->celkem;
      }
      $qryp= "SELECT count(*) as klientu FROM ds_osoba
             WHERE  FIND_IN_SET(id_order,'$uids')";
      $resp= mysql_qry($qryp);
      if ( $resp && $op= mysql_fetch_object($resp) ) {
        $klientu= $op->klientu;
      }
      $tit= wu($mesice[$m])." - $celkem ($klientu)";
      $par= (object)array('od'=>$od,'do'=>$do,
        'celkem'=>$celkem,'klientu'=>$klientu,'objednavek'=>$objednavek,'uids'=>$uids,
        'mesic_rok'=>wu($mesice[$m])." $rok");
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
# ======================================================================================> objednávky
# ------------------------------------------------------------------------------------ ds_rooms_help
# vrátí popis pokojů
function ds_rooms_help($version=1) {
  $hlp= array();
  ezer_connect('setkani');
  $qry= "SELECT number,note
         FROM tx_gnalberice_room
         WHERE  NOT deleted AND NOT hidden AND version=1";
  $res= mysql_qry($qry);
  while ( $res && $o= mysql_fetch_object($res) ) {
    $hlp[]= (object)array('fld'=>"q$o->number",'hlp'=>wu($o->note));
  }
//                                                         debug($hlp);
  return $hlp;
}
# -------------------------------------------------------------------------------------- ds_obj_menu
# vygeneruje menu pro loňský, letošní a příští rok ve tvaru objektu pro ezer2 pro zobrazení objednávek
# určující je datum zahájení pobytu v objednávce
function ds_obj_menu() {
  global $mysql_db;
  $stav= map_cis('ds_stav');
  $the= '';                     // první objednávka v tomto měsíci či později
//                                      debug($stav,'ds_obj_menu',(object)array('win1250'=>1));
  $mesice= array(1=>'leden','únor','březen','duben','květen','červen',
    'červenec','srpen','září','říjen','listopad','prosinec');
  $mn= (object)array('type'=>'menu.left'
      ,'options'=>(object)array(),'part'=>(object)array());
  $start= date('m') <= 6 ? date('Y')-1 : date('Y');
  $ted= date('Ym');
  ezer_connect('setkani');
  for ($y= 0; $y<=1; $y++) {
    for ($m= 1; $m<=12; $m++) {
      $mm= sprintf('%02d',$m);
      $yyyy= $start+$y;
      $group= "$yyyy$mm";
      $gr= (object)array('type'=>'menu.group'
        ,'options'=>(object)array('title'=>($mesice[$m])." $yyyy"),'part'=>(object)array());
      $mn->part->$group= $gr;

      $from= mktime(0,0,0,$m,1,$yyyy);
      $until= mktime(0,0,0,$m+1,1,$yyyy);
      $qry= "/*ds_obj_menu*/SELECT uid,fromday,untilday,state,name,state FROM setkani.tx_gnalberice_order
             WHERE  NOT deleted AND NOT hidden AND untilday>=$from AND $until>fromday";
//              JOIN ezer_ys._cis ON druh='ds_stav' AND data=state
      $res= mysql_qry($qry);
      while ( $res && $o= mysql_fetch_object($res) ) {
        $iid= $o->uid;
        $zkratka= $stav[$o->state];
        $par= (object)array('uid'=>$iid);
        $tit= wu("$iid - ").$zkratka.wu(" - {$o->name}");
        $tm= (object)array('type'=>'item','options'=>(object)array('title'=>$tit,'par'=>$par));
        $gr->part->$iid= $tm;
        if ( !$the && $group>=$ted ) {
          $the= "$group.$iid";
        }
      }
    }
  }
  $result= (object)array('th'=>$the,'cd'=>$mn);
  return $result;
}
# ----------------------------------------------------------------------------------------- pin_make
# vytvoř PIN k číslu x
function pin_make ($x) {
  // jediná kolize pro čísla 1..2000
  $str= str_pad($x,6,'0',STR_PAD_LEFT);
  $pin= substr(sprintf("%u",crc32(strrev($str))),-6);
  return $pin;
}
# ----------------------------------------------------------------------------------------- pin_test
# test PIN - doplníme ho nulami zleva
function pin_test ($x,$pin) {
  $str= str_pad($x,6,'0',STR_PAD_LEFT);
  $strpin= str_pad($pin,6,'0',STR_PAD_LEFT);
  $xpin= substr(sprintf("%u",crc32(strrev($str))),-6);
  return $strpin==$xpin?1:0;
}
# ==========================================================================================> rodina
# ------------------------------------------------------------------------------------------ lide_ms
# SELECT autocomplete - výběr z databází MS (Miloš, Lída)
function lide_ms($patt) {  #trace('','win1250');
  $a= array();
  $limit= 10;
  $n= 0;
  // rodiče
  $qry= "SELECT source,cislo AS _key,concat(jmeno,' ',jmeno_m,' a ',jmeno_z,' - ',mesto) AS _value
         FROM ms_pary
         WHERE jmeno LIKE '$patt%' ORDER BY jmeno LIMIT $limit";
  $res= mysql_qry($qry);
  while ( $res && $t= mysql_fetch_object($res) ) {
    if ( ++$n==$limit ) break;
    $key= "{$t->_key}".($t->source=='L'?0:1);
    $a[$key]= "{$t->source}:{$t->_value}";
  }
  // obecné položky
  if ( !$n )
    $a[0]= wu("... žádné jméno nezačíná '")."$patt'";
  elseif ( $n==$limit )
    $a[-999999]= wu("... a další");
//                                                                 debug($a,$patt,(object)array('win1250'=>1));
  return $a;
}
# ------------------------------------------------------------------------------------------- rodina
# formátování autocomplete
function rodina($xcislo) {  #trace('','win1250');
  $rod= array();
  // rodiče
  $source= $xcislo % 2 ? 'M' : 'L';
  $cislo= round($xcislo/10);
  $qry= "SELECT * FROM ms_pary WHERE source='$source' AND cislo=$cislo";
  $res= mysql_qry($qry);
  if ( $res && $p= mysql_fetch_object($res) ) {
    rodina_add($rod,$p->prijmeni_m,$p->jmeno_m,$p->rodcislo_m,$p->telefon,$p->email,$p);
//                                              display("{$p->prijmeni_m}:{$p->rodcislo_m}:$narozeni");
    rodina_add($rod,$p->prijmeni_z,$p->jmeno_z,$p->rodcislo_z,$p->telefon,$p->email,$p);
  }
  // děti
  $qry= "SELECT * FROM ms_deti WHERE source='$source' AND cislo=$cislo";
  $res= mysql_qry($qry);
  while ( $res && $d= mysql_fetch_object($res) ) {
    $prijmeni= rc2man($d->rodcislo) ? $p->prijmeni_m : $p->prijmeni_z;
    rodina_add($rod,$prijmeni,$d->jmeno,$d->rodcislo,' ',' ',$p);
  }
//                                              debug($rod,$cislo,(object)array('win1250'=>1));
  return $rod;
}
function rodina_add(&$rod,$prijmeni,$jmeno,$rc,$telefon,$email,$p) { trace('','win1250');
  if ( $prijmeni || $jmeno ) {
    $roky= roku($rc);
    $narozeni= rc2dmy($rc);
    $rod[]= (object)array('prijmeni'=>$prijmeni,'jmeno'=>$jmeno,'stari'=>$roky,
      'psc'=>$p->psc,'mesto'=>$p->mesto,'ulice'=>$p->adresa,
      'telefon'=>$telefon,'email'=>$email,'narozeni'=>$narozeni);
  }
}
function roku($rc) {
  $r= rc2roky($rc);
  $roku= !$r          ? "?" : (
         $r==1        ? "rok" : (
         ($r % 10)==1 ? "let" : (
         $r<=4        ? "roky" : "roků" )));
  return wu("$r $roku, rč:$rc");
}
# ------------------------------------------------------------------------------------------ lide_ds
# SELECT autocomplete - výběr z databáze DS
function lide_ds($patt0) {  #trace('','win1250');
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
  $res= mysql_qry($qry);
  while ( $res && $t= mysql_fetch_object($res) ) {
    if ( ++$n==$limit ) break;
    $key= $t->_key;
    $a[$key]= wu("D:{$t->_value}");
  }
  // obecné položky
  if ( !$n )
    $a[0]= wu("... žádné jméno nezačíná '")."$patt0'";//."INFO='$info'";
  elseif ( $n==$limit )
    $a[-999999]= wu("... a další");//."INFO='$info0'";
//                                                      debug($a,$patt,(object)array('win1250'=>1));
  return $a;
}
# ------------------------------------------------------------------------------------------- rodina
# formátování autocomplete
function klienti($id_osoba) {  #trace('','win1250');
  $rod= array();
  // rodiče
  ezer_connect('setkani');
  $qry= "SELECT * FROM ds_osoba WHERE id_osoba=$id_osoba";
  $res= mysql_qry($qry);
  if ( $res && $p= mysql_fetch_object($res) ) {
    $cond= "id_order={$p->id_order} AND obec='{$p->obec}' AND ulice='{$p->ulice}'";
    // vybereme se stejným označením rodiny
    $qry= "SELECT * FROM ds_osoba WHERE $cond
           ORDER BY narozeni";
    $res= mysql_qry($qry);
    while ( $res && $o= mysql_fetch_object($res) ) {
    $vek= ds_vek($o->narozeni,time());
    $narozeni= sql_date1($o->narozeni);
    $rod[]= (object)array('prijmeni'=>wu($o->prijmeni),'jmeno'=>wu($o->jmeno),'stari'=>$vek,
      'psc'=>$o->psc,'mesto'=>wu($o->obec),'ulice'=>wu($o->ulice),
      'telefon'=>$o->telefon,'email'=>$o->email,'narozeni'=>$narozeni);
    }
  }
//                                              debug($rod,$id_osoba,(object)array('win1250'=>1));
  return $rod;
}
# ============================================================================================> ceny
# ------------------------------------------------------------------------------------- ds_xls_hoste
# kopie ceníku
# par:
#   .op='kopie'
#   .z=starý rok
#   .na=nový rok
function ds_ceny_uprava($par) { trace('','win1250');
  $html= "";
  if ( $par->op=='kopie' ) {
    ezer_connect('setkani');
    // určení roků
    $z= date('Y')+$par->z;
    $na= date('Y')+$par->na;
    // kontrola prázdnosti nového ceníku
    $qry= "SELECT count(*) as pocet FROM setkani.ds_cena  WHERE rok=$na ";
    $res= mysql_qry($qry);
    if ( $res && $c= mysql_fetch_object($res) ) {
      if ( $c->pocet>0 )
        $html= "Cenik pro rok $na byl jiz zrejme vygenerovan. Operace prerusena.";
    }
    if ( !$html ) {
      // kopie ceníku
      $qry= "SELECT * FROM ds_cena WHERE rok=$z ORDER BY typ";
      $res= mysql_qry($qry);
      $ok= 1; $n= 0;
      while ( $res && $c= mysql_fetch_object($res) ) {
        $ins= "INSERT INTO ds_cena (rok,polozka,druh,typ,od,do,cena,dph)
               VALUES ($na,'{$c->polozka}','{$c->druh}','{$c->typ}',{$c->od},{$c->do},{$c->cena},{$c->dph})";
        display(wu($ins));
        $n++;
        $ires= mysql_qry($ins);
        $ok&= mysql_affected_rows()==1 ? 1 : 0;
      }
      $html.= $ok&&$n ? "Zkopirovano" : "Kopie ceniku se nezdarila. Kontaktuj Martina Smidka";
    }
  }
  return $html;
}
# =========================================================================================> exporty
# ------------------------------------------------------------------------------------- ds_xls_hoste
# definice Excelovského listu - seznam hostů
function ds_xls_hoste($orders,$mesic_rok) {  #trace('','win1250');
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
    list($jmeno,$prijmeni,$adresa,$narozeni,$telefon,$email,$termin,$poplatek,$akce,$vek)= (array)$host;
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
//                                                                 display(wu($xls));
  $test= 1;
  if ( $test )
    file_put_contents("xls.txt",$xls);
  $inf= Excel5(wu($xls),1);
  if ( $inf ) {
    $html= " se nepodařilo vygenerovat - viz začátek chybové hlášky";
    fce_error(wu($inf));
  }
  else
    $html= " Byl vygenerován seznam hostů ve formátu <a href='docs/$name.xls' target='xls'>Excel</a>.";
  return wu($html);
}
# ----------------------------------------------------------------------------------------- ds_hoste
# vytvoří seznam hostů
# ceník beer podle předaného roku
# {table:id,obdobi:str,hoste:[[jmeno,prijmeni,adresa,narozeni,telefon,email,termin,poplatek]...]}
function ds_hoste($orders,$rok) {  #trace('','win1250');
  global $ds_cena, $ezer_path_serv;
  require_once "$ezer_path_serv/licensed/xls2/Classes/PHPExcel/Calculation/Functions.php";
  ds_cenik($rok);
//                                      debug($ds_cena,'ds_cena',(object)array('win1250'=>1));
  $x= (object)array();
  $x->table= "klienti_$obdobi";
  $x->hoste= array();
  ezer_connect('setkani');
  // zjištění klientů zahajujících pobyt v daném období
  $qry= "SELECT *,o.fromday as _of,o.untilday as _ou,p.email as p_email,
         p.fromday as _pf,p.untilday as _pu,akce
         FROM setkani.ds_osoba AS p
         JOIN tx_gnalberice_order AS o ON uid=id_order
         WHERE FIND_IN_SET(id_order,'$orders') ORDER BY id_order,rodina,narozeni DESC";
  $res= mysql_qry($qry);
  while ( $res && $h= mysql_fetch_object($res) ) {
    $pf= sql2stamp($h->_pf); $pu= sql2stamp($h->_pu);
    $od_ts= $pf ? $pf : $h->_of;
    $do_ts= $pu ? $pu : $h->_ou;
    $od= date('j.n',$od_ts);
    $do= date('j.n',$do_ts);
//     $od= $pf ? date('j.n',$pf) : date('j.n',$h->_of);
//     $do= $pu ? date('j.n',$pu) : date('j.n',$h->_ou);
    $vek= ds_vek($h->narozeni,$pf ? $h->_pf : $h->_of);
    if ( $h->narozeni ) {
      list($y,$m,$d)= explode('-',$h->narozeni);
      $time= gmmktime(0,0,0,$m,$d,$y);
      $narozeni= PHPExcel_Shared_Date::PHPToExcel($time);
    }
    else $narozeni= 0;
    // rekreační poplatek
    if ( $vek>=18 || $vek<0 )
      $popl= $ds_cena['ubyt_C']->cena + $ds_cena['ubyt_S']->cena;
    else
      $popl= $ds_cena['ubyt_P']->cena;
    // připsání řádku
    $host= array();
    $host[]= $h->jmeno;
    $host[]= $h->prijmeni;
    $host[]= "{$h->psc} {$h->obec}, {$h->ulice}";
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
# ================================================================================> zálohová faktura
# ASK
# ------------------------------------------------------------------------------------ ds_xls_zaloha
# definice Excelovského listu - zálohové faktury
function ds_xls_zaloha($order) {  #trace('','win1250');
  global $ezer_path_serv;
  $html= " nastala chyba";
  $name= "zal_$order";
  // vytvoření sešitu s fakturou
  $xls= "|open  $name|";
  $x= ds_zaloha($order);
  if ( !count((array)$x) ) goto end;
  $xls.= ds_faktura('zalohova_faktura','ZÁLOHOVÁ FAKTURA',$order,$x->polozky,$x->platce,50,
    "Těšíme se na Váš pobyt v Domě setkání");
  $xls.= "|close|";
  $test= 1;
  if ( $test )
    file_put_contents("xls.txt",$xls);
  $inf= Excel5(wu($xls),1);
  if ( $inf ) {
    $html= " nastala chyba";
    fce_error(wu($inf));
  }
  else
    $html= " <a href='docs/$name.xls' target='xls'>zálohová faktura</a>.";
end:
  return wu($html);
}
# ---------------------------------------------------------------------------------------- ds_zaloha
# data zálohové faktury
# {objednavka:n,
#  platce:[nazev,adresa,telefon,ic]
#  polozky:[[nazev,cena,dph,pocet]...]
# }
function ds_zaloha($order) {  #trace('','win1250');
  global $ds_cena;
  $x= (object)array();
  // zjištění údajů objednávky
  ezer_connect('setkani');
  $qry= "SELECT * FROM tx_gnalberice_order WHERE uid=$order";
  $res= mysql_qry($qry);
  if ( $res && $o= mysql_fetch_object($res) ) {
    $o->rooms= $o->rooms1;
    foreach ((array)$o as $on) if ( strstr($on,'|')!==false ) { // test na |
      fce_warning(wu("nepřípustný znak '|' v '$on'"));
      goto end;
    }
    $obdobi= date('j.n',$o->fromday).' - '.date('j.n.Y',$o->untilday);
    $dnu= ($o->untilday-$o->fromday)/(60*60*24);
//                                                         display("pocet dnu=$dnu");
    // přečtení ceníku daného roku
    ds_cenik(date('Y',$o->untilday));
    // údaje o plátci: $ic,$dic,$adresa,$akce
    $platce= array();
    $platce[]= $o->ic ? $o->ic : '';
    $platce[]= $o->dic ? $o->dic : '';
    $platce[]= ($o->org ? "{$o->org}{}" : '')."{$o->firstname} {$o->name}{}".
              "{$o->address}{}{$o->zip} {$o->city}";
    $platce[]= $o->akce;
    $platce[]= $obdobi;
    $x->platce= $platce;
    // položky zálohové faktury
    // ubytování může mít slevu
    $polozky= array();
    $sleva= $o->sleva ? $o->sleva/100 : '';
    $x->polozky[]= ds_c('noc_L',$dnu*($o->adults + $o->kids_10_15 + $o->kids_3_9),$sleva);
    $x->polozky[]= ds_c('ubyt_C',$dnu*($o->adults));
    $x->polozky[]= ds_c('ubyt_S',$dnu*($o->adults));
    $x->polozky[]= ds_c('ubyt_P',$dnu*($o->kids_10_15 + $o->kids_3_9 + $o->kids_3));
    $x->polozky[]= ds_c('noc_B',$dnu*$o->kids_3,$sleva);
    switch ( $o->board ) {
    case 1:     // penze
      $x->polozky[]= ds_c('strava_CC',$dnu*($o->adults+$o->kids_10_15));
      $x->polozky[]= ds_c('strava_CD',$dnu*$o->kids_3_9);
      break;
    case 2:     // polopenze
      $x->polozky[]= ds_c('strava_PC',$dnu*($o->adults+$o->kids_10_15));
      $x->polozky[]= ds_c('strava_PD',$dnu*$o->kids_3_9);
      break;
    }
//                                              debug($x,'zaloha',(object)array('win1250'=>1));
  }
end:
  return $x;
}
# =================================================================================> konečná faktura
# ----------------------------------------------------------------------------------- ds_xls_faktury
# ASK
# definice Excelovského listu - faktury podle seznamu účastníků
# položka,počet osob,počet nocí,počet nocolůžek,cena jednotková,celkem,DPH 9%,DPH 19%,základ
# *ubytování
function ds_xls_faktury($order) {  trace('','win1250');
  global $ds_cena;
  $html= " nastala chyba";
  $test= 1;
  $x= ds_faktury($order);
  if ( !count((array)$x->rodiny) ) goto end;
  $ds_cena['zzz_zzz']= 0;    // přidání prázdného řádku
  ksort($ds_cena);

//                                      debug($ds_cena,'ds_cena',(object)array('win1250'=>1));
//                                      debug($x,'faktura',(object)array('win1250'=>1));
  // barvy
  $c_edit= "ffffffaa";
  // úvodní list
  $xls= '';
  $nf= 3;
  $faktury= "";
  foreach ($x->rodiny as $r=>$hoste) {
    $nf++;
    $rod= $x->faktury[$r];
    list($rodina,$pocet,$sleva)= $rod;
    $prefix= count($x->rodiny)==1 ? '' : ($rodina=='' ? 'ostatni-' : "$rodina-");
    $sheet_rodina=  "{$prefix}hoste";
    $sheet_rozpis= "{$prefix}rozpis";
    $sheet_faktura= "{$prefix}faktura";
    $faktury.= "|A$nf:F$nf border=,,h,|A$nf $rodina|B$nf $pocet|F$nf =D$nf-E$nf ::kc";
    $faktury.= "|C$nf $sleva::proc bcolor=$c_edit|E$nf ::kc bcolor=$c_edit|D$nf =";
    $An_sleva= "'rodiny'!C$nf";
    $An_zaloha= "'rodiny'!E$nf";
    # ------------------------------------------------------------- členové rodiny a položky faktury
    $i= 0;
    $clmn= "A=10,B=13,C=-30,D=-15,E=4,F=-20,G=-30,H=20,I=5,J=6";
    $tit= "|A3 jméno |B3 příjmení |C3 adresa |D3 narozen/a ::right |E3 věk |F3 telefon "
         ."|G3 email |H3 pobyt od-do ::right |I3 noci ::right| J3 pokoj ::right|";
    $lc= ord('J')-ord('A');
    $n= 3;
    $druh0= '';
    $soucty= ''; $ns= $n+count($hoste)+1; $ns1= $ns-1;
    foreach($ds_cena as $dc=>$cena) {
      list($druh,$cast)= explode('_',$dc);
//                                                                 display("$lc,$i,$druh,$druh0,$cast");
      if ( $druh!=$druh0 ) {
        if ( $i ) {
          if ( $druh0=='noc' ) {
            $lc++;
            $A= Excel5_n2col($lc);
            $clmn.= ",$A=6";
            $tit.= "|$A$n sleva ::vert";
          }
          $lc++;
          $B= Excel5_n2col($lc);
          $clmn.= ",$B=12";
          $tit.= "|$B$n cena ::right";
        }
        $druh0= $druh;
      }
      if ( $cena ) {
        $lc++;
        $A= Excel5_n2col($lc);
        $clmn.= ",$A=4";
        $tit.= "|$A$n {$cena->polozka} ::vert";
        $soucty.= "|$A$ns =SUM({$A}4:$A$ns1)";
        $cena->pocet= "='$sheet_rodina'!$A$ns";
      }
      else {
        $lc++;
        $B= Excel5_n2col($lc);
        $clmn.= ",$B=12";
        $tit.= "|$B$n celkem ::right bold";
        $soucty.= "|$B$ns =SUM({$B}4:$B$ns1) ::kc bold";
        $faktury.= "'$sheet_rodina'!$B$ns ::kc";
      }
      $i++;
      // součty
    }
    $tit.= "|A$n:$B$n bcolor=ffaaaaaa |";
    $xls.= "\n\n|sheet $sheet_rodina;;L;page|columns $clmn |$tit |";
    $skupiny= count($x->rodiny)==1 ? '' : ($rodina=='' ? "neoznačených hostů" : "hostů označených '$rodina'");
    $xls.= "|A1 Konečné vyúčtování $skupiny v rámci objednávky $order ::bold size=14|";
    $n= 4;
    foreach ($hoste as $host) {
      list($rodina,$jmeno,$prijmeni,$ulice,$psc_mesto,$narozeni,$vek,$telefon,$email,$od,$do)= $host->host;
      $termin= "$od - $do";
      $c= $host->cena;
      $xls.= <<<__XLS
        |A$n $jmeno |B$n $prijmeni |C$n $ulice, $psc_mesto |D$n $narozeni ::right date |E$n $vek
        |F$n $telefon |G$n $email |H$n $termin ::right
        |I$n {$c->noci}
        |J$n {$c->pokoj}
__XLS;
      // položky
      $lc= ord('J')-ord('A');
      $row= '';
      $i= 0;
      $druh0= '';
      $suma= '0';
      $celkem= '=0';
      foreach($ds_cena as $dc=>$cena) {
        list($druh,$cast)= explode('_',$dc);
        if ( $druh!=$druh0 ) {
          if ( $i ) {
            if ( $druh0=='noc' ) {
              $lc++;
              $A= Excel5_n2col($lc);
              $row.= "|$A$n =$An_sleva::proc";
            }
            $lc++;
            $B= Excel5_n2col($lc);
            if ( $druh0=='noc' ) {
              $row.= "|$B$n =(1-$A$n)*($suma) ::kc";
            }
            else {
              $row.= "|$B$n =$suma ::kc";
            }
            $suma= '0';
            $celkem.= "+$B$n";
          }
          $druh0= $druh;
        }
        if ( $cena ) {
          $lc++;
          $A= Excel5_n2col($lc);
          $val= isset($c->$dc) ? $c->$dc : '';
          $suma.= "+$A$n*{$cena->cena}";
          $row.= "|$A$n $val::bcolor=$c_edit";
        }
        else {
          $lc++;
          $B= Excel5_n2col($lc);
          $row.= "|$B$n $celkem ::kc bold";
        }
        $i++;
      }
//                                                                 debug($host,'host',(object)array('win1250'=>1));
      $xls.= "$row|A$n:$B$n border=,,h,|$soucty|";
      $n++;
    }
    $xls.= "|$soucty|";
    # ---------------------------------------------------------------- faktura
    # platce= [nazev,adresa,telefon,ic]
    # polozky= [[nazev,cena,dph,pocet]...]
    if ( $rodina=='' ) {
      list($rodina,$jmeno,$prijmeni,$ulice,$psc_mesto)= $hoste[0]->host;
      $platce= array();
      $platce[]= '';
      $platce[]= '';
      $platce[]= "$jmeno $prijmeni{}$ulice{}$psc_mesto";
    }
    else {
      $platce= $x->platce;
    }
    // položky faktury
    $polozky= array();
    foreach($ds_cena as $dc=>$cena) {
      if ( $cena ) {    // bez prázdného řádku
        list($druh,$cast)= explode('_',$dc);
        $sleva= $druh=='noc' ? "=$An_sleva" : '';
        $zaloha= "=$An_zaloha";
        $polozky[]= ds_c($dc,$cena->pocet,$sleva,$zaloha);        // id,pocet => název,cena,dph%,pocet,druh
      }
    }
    // vytvoření listu
    $xls.= ds_rozpis_faktura($sheet_rozpis,$sheet_faktura,'FAKTURA',$order,$x,$polozky,$platce,100,
      "Těšíme se na Váš další pobyt v Domě setkání",$zaloha,$suma);
    $faktury.= "";
  }
  // ------------------------------------------------------------------ ceník
//                                      debug($ds_cena,'ds_cena',(object)array('win1250'=>1));
  $xls.= <<<__XLS
  \n\n|sheet cenik;;P;page
    |columns A=35,B=20,C=20
    |A1 Seznam účtovatelných položek ::bold size=14
    |A3 položka |B3 cena vč.DPH ::right |C3 DPH ::right proc
    |A3:C3 bcolor=ffaaaaaa
__XLS;
  $n= 4;
  foreach ($ds_cena as $i=>$cena) {
    if ( $cena ) {    // bez prázdného řádku
      $dph= $cena->dph/100;
      $xls.= <<<__XLS
        |A$n {$cena->polozka} |B$n {$cena->cena} :: kc |C$n $dph :: proc
        |A$n:C$n border=,,h,
__XLS;
      $n++;
    }
  }
  # ------------------------------------------------------------------ seznam rodin (jako první)
  $nf1= $nf+1;
  $name= "fak_$order";
  $final_xls= <<<__XLS
  \n|open $name
  \n\n|sheet rodiny;;L;page
    |columns A=20,B=13,C=10,D:F=16
    |A1 Seznam rodin (skupin), kterým fakturujeme pobyt v rámci objednávky $order ::bold size=14
    |A3 rodina (skupina)|B3 osob ::right |C3 sleva ::right |D3 celková cena ::right
    |E3 záloha ::right|F3 doplatit ::right
    |A3:F3 bcolor=ffaaaaaa
    |$faktury
    |A$nf1 CELKEM ::right bold|B$nf1 =SUM(B4:B$nf) ::bold|D$nf1 =SUM(D4:D$nf) ::kc bold
    |E$nf1 =SUM(E4:E$nf) ::kc bold|F$nf1 =SUM(F4:F$nf) ::kc bold
__XLS;
  // vysvětlivky
  $n= $nf1+3;
  $final_xls.= "|A$n Upozornění: v tomto sešitu je možné upravovat pouze žlutě podložená políčka, ".
    "změny jiných políček pravděpodobně poškodí výpočet cen a DPH. Pokud je třeba měnit údaje, ".
    "které nejsou žlutě podloženy, je potřeba to udělat v systému Ans(w)er a znovy vygenerovat tento ".
    "sešit.";
  $final_xls.= "|A$n:F$n merge italic top wrap
    |rows $n=60
    |$xls|close 1";
//                                                                 display("rodiny=$faktury");
//                                                                 display(nl2br(wu($xls)));
  $test= 1;
  if ( $test )
    file_put_contents("xls.txt",$final_xls);
  time_mark('ds_xls_faktury Excel5');
  $inf= Excel5(wu($final_xls),1);
  if ( $inf ) {
    $html= " nastala chyba";
    fce_error(wu($inf));
  }
  else
    $html= " <a href='docs/$name.xls' target='xls'>konečná faktura</a>.";
  // případný testovací výpis
  time_mark('ds_xls_faktury end');
end:
  return wu($html);
}
# --------------------------------------------------------------------------------------- ds_faktury
# podklady ke konečné fakturaci
# {platce:[ic,dic,adresa,akce],faktury:[[rodina,počet,sleva]...],rodiny:[<host>...],<ceník>,<chyby>}
#    <host> :: {host:[jmeno,prijmeni,adresa1,adresa2,narozeni,vek,telefon,email,od,do,<ubyt>],
#               cena:{<ubyt>,<strava>,<spec>,<popl>}
#    <ubyt> :: noci:n,pokoj:1..29,pokoj_typ:P|S|B,luzko_typ:L|P|B
#  <strava> :: CC:n,CP:n,PC:n,PP:n
#    <spec> :: postylka:n,zvire_noci:n
#    <popl> :: popl:CS|P
#   <ceník> :: cenik:... viz global $ds_cena
#   <chyby> :: chyby:[[text,...]...]
#    pokoje :: A:1-2, S:14-17, L:11-13+21-29
#
function ds_faktury($order) {  trace('','win1250');
  global $ds_cena;
  $x= (object)array('faktury'=>array(),'rodiny'=>array());
//// číselníky                    1   2   3   4   5   6   7   8   9   10  11  12  13  14  15  16
//$luzko_pokoje= array(0=>'?',1=>'L','L','L','L','L','L','L','S','S','L','L','L','S','S','A','A');
  $luzko_pokoje[0]= 0;
  for ($i=11; $i<=29; $i++) $luzko_pokoje[$i]= 'L';     // normální pokoje
  for ($i= 1; $i<= 2; $i++) $luzko_pokoje[$i]= 'A';     // apartmán bezbariérový
  for ($i=14; $i<=17; $i++) $luzko_pokoje[$i]= 'S';     // apartmány
  $ds_luzko=  map_cis('ds_luzko','zkratka');  $ds_luzko[0]=  '?';
  $ds_strava= map_cis('ds_strava','zkratka'); $ds_strava[0]= '?';
//                                              debug($ds_strava,(object)array('win1250'=>1));
  // kontrola objednávky
  ezer_connect('setkani');
  $qry= "SELECT * FROM setkani.tx_gnalberice_order WHERE uid=$order";
  $res= mysql_qry($qry);
  if ( $res && $o= mysql_fetch_object($res) ) {
    $o->rooms= $o->rooms1;
    foreach ((array)$o as $on) if ( strstr($on,'|')!==false ) { // test na |
      fce_warning(wu("nepřípustný znak '|' v '$on'"));
      goto end;
    }
    $obdobi= date('j.n',$o->fromday).' - '.date('j.n.Y',$o->untilday);
    $skoleni= $o->skoleni;
    // údaje o plátci: $ic,$dic,$adresa
    $platce= array();
    $platce[]= $o->ic ? $o->ic : '';
    $platce[]= $o->dic ? $o->dic : '';
    $platce[]= ($o->org ? "{$o->org}{}" : '')."{$o->firstname} {$o->name}{}".
              "{$o->address}{}{$o->zip} {$o->city}";
    $platce[]= $o->akce;
    $platce[]= $obdobi;
    $x->objednavka= array($obdobi);
    $x->platce= $platce;
    // přečtení ceníku daného roku
    ds_cenik(date('Y',$o->untilday));
    // úprava ceny programu na této akci
    $ds_cena['prog_C']->cena= $o->prog_cely;
    $ds_cena['prog_P']->cena= $o->prog_polo;
    // zjištění počtu faktur za akci
    $qry= "SELECT rodina,count(*) as pocet FROM setkani.ds_osoba
           WHERE id_order=$order GROUP BY rodina ORDER BY if(rodina='','zzzzzz',rodina)";
    $res= mysql_qry($qry);
    while ( $res && $r= mysql_fetch_object($res) ) {
      // seznam faktur
      $rid= $r->rodina ? $r->rodina : 'ostatni';
      $x->faktury[]= array($rid,$r->pocet,$o->sleva/100);
      // členové jedné rodiny s údaji
      $hoste= array();
      $err= array();
      $qry= "SELECT * FROM setkani.ds_osoba
             WHERE id_order=$order AND rodina='{$r->rodina}' ORDER BY narozeni DESC";
      $reso= mysql_qry($qry);
      while ( $reso && $h= mysql_fetch_object($reso) ) {
        foreach ((array)$h as $on) if ( strstr($on,'|')!==false ) { // test na |
          fce_warning(wu("nepřípustný znak '|' v '$on'"));
          goto end;
        }
        $hf= sql2stamp($h->fromday); $hu= sql2stamp($h->untilday);
        $od_ts= $hf ? $hf : $o->fromday;  $od= date('j.n',$od_ts);
        $do_ts= $hu ? $hu : $o->untilday; $do= date('j.n',$do_ts);
        $vek= ds_vek($h->narozeni,$o->fromday);
        $narozeni= $h->narozeni ? sql_date1($h->narozeni): '';
        $strava= $h->strava ? $h->strava : $o->board;
        // připsání řádku
        $host= array();
        $host[]= $h->rodina;
        $host[]= $h->jmeno;
        $host[]= $h->prijmeni;
        $host[]= $h->ulice;
        $host[]= "{$h->psc} {$h->obec}";
        $host[]= $narozeni;
        $host[]= $vek;
        $host[]= $h->telefon;
        $host[]= $h->email;
        $host[]= $od;
        $host[]= $do;
        // položky hosta
        $pol= (object)array();
        $pol->test= "{$h->strava} : {$o->board} - $strava = {$ds_strava[$strava]}";
//                                                 display(wu("{$h->jmeno} {$h->prijmeni} $pol->test}"));
        $noci= round(($do_ts-$od_ts)/(60*60*24));
        $pol->vek= $vek;
        $pol->noci= $noci;
        $pol->pokoj= $h->pokoj;
        // ubytování
        $luzko= trim($ds_luzko[$h->luzko]);     // L|P|B
//                 debug($ds_luzko,"ds_luzko {$luzko_pokoje[$h->pokoj]}",(object)array('win1250'=>1));
        if ( $luzko=='L' )
          $luzko= $luzko_pokoje[$h->pokoj];
        if ( $luzko )
          $pol->{"noc_$luzko"}= $noci;
        // strava
        $pol->strava_CC= $ds_strava[$strava]=='C' && $vek>=$ds_cena['strava_CC']->od ? $noci : '';
        $pol->strava_CD= $ds_strava[$strava]=='C' && $vek>=$ds_cena['strava_CD']->od
                                                  && $vek< $ds_cena['strava_CD']->do ? $noci : '';
        $pol->strava_PC= $ds_strava[$strava]=='P' && $vek>=$ds_cena['strava_PC']->od ? $noci : '';
        $pol->strava_PD= $ds_strava[$strava]=='P' && $vek>=$ds_cena['strava_PD']->od
                                                  && $vek< $ds_cena['strava_PD']->do ? $noci : '';
        // pobyt
        if ( $h->postylka ) {
          $pol->pobyt_P= 1;
        }
        // poplatky
        if ( $vek>=18 ) {
          $pol->ubyt_S= $noci;
          if ( !$skoleni ) $pol->ubyt_C= $noci;   // rekreační poplatek se neplatí za školení
        }
        else {
          $pol->ubyt_P= $noci;
        }
        // program
        $pol->prog_C= $vek>=$ds_cena['prog_C']->od  ? 1 : 0;
        $pol->prog_P= $vek>=$ds_cena['prog_P']->od && $vek<$ds_cena['prog_P']->do ? 1 : 0;
        // konec
        $hoste[]= (object)array('host'=>$host,'cena'=>$pol);
      }
      $x->rodiny[]= $hoste;
      $x->chyby[]= $err;
    }
  }
  else
    fce_error(wu("neúplná objednávka $order"));
//                                      debug($ds_cena,'ds_cena',(object)array('win1250'=>1));
//                                      debug($x,'faktura',(object)array('win1250'=>1));
end:
  return $x;
}
# ==================================================================================> faktura obecně
# -------------------------------------------------------------------------------- ds_rozpis_faktura
# definice faktury
# typ = Zálohová | ''
# zaloha = 0..100  -- pokud je 100 negeneruje se řádek Záloha ...
# data zálohové faktury
# platce= [nazev,adresa,telefon,ic]
# polozky= [[nazev,cena,dph,pocet,sleva]...]
# }
function ds_rozpis_faktura($listr,$listf,$typ,$order,$x,$polozky,$platce,$zaloha=100,$pata,$zaloha,&$suma) {
                                                trace('','win1250');
  list($ic,$dic,$adresa,$akce,$obdobi)= $platce;
//                                              debug($platce,'platce',(object)array('win1250'=>1));
  $vystaveno= Excel5_date(time());
  //list($obdobi)= $x->objednavka;
  $ymca_setkani= "YMCA Setkání, spolek{}Talichova 53, 62300 Brno{}".
                 "zaregistrovaný Krajským soudem v Brně{}spisová značka: L 8556{}".
                 "IČ: 26531135  DIČ: CZ26531135";
  $dum_setkani=  "Dolní Albeřice 1, 542 26 Horní Maršov{}".
                 "telefon: 499 874 152, 736 537 122{}dum@setkani.org{}www.alberice.setkani.org";
  // ------------------------------------------------------------------- vytvoření listu s rozpisem
  // pojmenované řádky (P,Q,R,S)
  $P= 10;               // výčet položek
  $Q= 26;               // poslední položka
  $D= 28;               // rozpis podle druhů
  $S= 34;               // poslední řádek
  // parametrizace
  $c_okraj= "ff6495ed";    $S1= $S+1;
  $xls= <<<__XLS
  \n\n|sheet $listr;B2:N$S;P;page
    |columns A=3,B=0.6,C=16,D=3,E=22,F=6,G=16,H=10,I=4,J=6,K=6,L=16,M=0,N=1,O=3
    |rows 1=18,2:44=15,5=30,7=45,9=30,10:32=20,$S=30
    |A1:O$S1 bcolor=$c_okraj |B2:N$S bcolor=ffffffff |//B2:N$S border=h

    |image img/YMCA.png,80,C2,10,0
    |D2 Dům setkání :: bold size=14
    |D3 $dum_setkani
    |D3:H5 merge italic top wrap
    |B7 Rozpis ceny za pobyt v Domě setkání ve dnech $obdobi ::bold size=16|B7:L7 merge
__XLS;
  $n= $P-1;
  $xls.= <<<__XLS
    |C$n Položka              ::wrap middle       |C$n:E$n merge bold border=h
    |F$n Počet                ::wrap middle right |F$n:F$n       bold border=h
    |G$n Druh                 ::wrap middle right |G$n:G$n       bold border=h
    |H$n Cena položky s DPH   ::wrap middle right |H$n:I$n merge bold border=h
    |J$n Sleva %              ::wrap middle right |J$n:J$n       bold border=h
    |K$n Sazba DPH            ::wrap middle right |K$n:K$n       bold border=h
    |L$n Cena s DPH           ::wrap middle right |L$n:L$n       bold border=h
__XLS;
  // řádky $P-$Q -- položky
  $n= $P;
  $sazby_dph= array();
  foreach ($polozky as $i=>$polozka) {
    list($nazev,$cena,$dph,$pocet,$druh,$sleva)= $polozka;
    if (!in_array($dph,$sazby_dph) ) $sazby_dph[]= $dph;
    if (!isset($druhy[$druh]) ) $druhy[$druh]= $dph;
    if ( $pocet ) {
      $xls.= <<<__XLS
        |C$n $nazev                |C$n:E$n merge
        |F$n $pocet
        |G$n $druh
        |H$n $cena         ::kc    |H$n:I$n merge
        |J$n $sleva        ::proc
        |K$n $dph          ::proc
        |L$n =F$n*H$n*(1-J$n) ::kc
__XLS;
      $n++;
    }
  }
  $xls.= <<<__XLS
    |C$P:E$Q border=h    |F$P:F$Q border=h    |G$P:G$Q border=h    |H$P:I$Q border=h
    |J$P:J$Q border=h    |K$P:K$Q border=h    |L$P:L$Q border=h
__XLS;
  // řádky D... -- rozpis podle druhů
  $n= $D;
  if ( count($druhy) )
  foreach($druhy as $druh=>$dph) {
    $xls.= <<<__XLS
      |H$n $druh::right                |H$n:J$n merge right
      |K$n $dph                        ::proc border=h right
      |L$n =SUMIF(G$P:G$Q,H$n,L$P:L$Q) ::kc   border=h right
__XLS;
    $n++;
  }
  // ------------------------------------------------------------------- vytvoření listu s fakturou
  // pojmenované řádky (P,Q,R,S)
  $P= 22;               // výčet položek
  $Q= 34;               // poslední položka
  $R= 31;               // vyřizuje
  $S= 37;               // poslední řádek
  // parametrizace
  $L7_ic=  $ic  ? "L7 IČ $ic"   : '';
  $M7_dic= $dic ? "M7 DIČ $dic" : '';
  $c_okraj= "ff6495ed";
  $S1= $S+1;
  $xls.= <<<__XLS
  \n\n|sheet $listf;B2:N$S;P;page
    |columns A=3,B=0.6,C=16,D=3,E=22,F=6,G=1,H=10,I=4,J=6,K=6,L:M=16,N=1,O=3
    |rows 1=18,2:44=15,5=30,6=45,9=96,11=35,19=30,20:36=19,21=30,37=30,38:41=19,$S=30
    |A1:O$S1 bcolor=$c_okraj |B2:N$S bcolor=ffffffff |//B2:N$S border=h

    |image img/YMCA.png,80,C2,10,0
    |D2 Dům setkání :: bold size=14
    |D3 $dum_setkani
    |D3:H5 merge italic top wrap

    |J4 =CONCATENATE("$typ ",TEXT(E16,"0"),"/",TEXT(3000+YEAR(M15),"0")) :: bold size=16
    |J4:M5 merge right

    |C7 Dodavatel ::bold
    |C9 $ymca_setkani
    |C9:F9 merge top wrap

    |I7 Odběratel ::bold         |$L7_ic        |$M7_dic
    |I8:M10 border=h
    |J9 $adresa  |J9:M9 merge middle wrap size=14

    |C13 Peněžní ústav           |E13 Fio banka, a.s.
    |C14 Číslo účtu       ::bold |E14 2000465448/2010 ::bold
    |C15 Konstantní symbol       |E15 558 ::left
    |C16 Variabilní symbol       |E16 <číslo faktury> ::left bcolor=ffffffaa
    |C17 Specifický symbol       |E17 $akce ::left bcolor=ffffffaa

    |J12 Objednávka číslo ::bold |M12 $order  ::size=14 bold
    |J13 Dodací a platební podmínky: s daní
    |J14 Datum vystavení           |M14 $vystaveno ::date bcolor=ffffffaa
    |J15 Datum zdanitelného plnění |M15 =M14       ::date bcolor=ffffffaa
    |J16 Datum splatnosti ::bold   |M16 =M14+14    ::date bcolor=ffffffaa bold
    |J18 Způsob platby             |M18 převod/hotovost ::bcolor=ffffffaa

    |C19 Za pobyt v Domě setkání ve dnech $obdobi Vám fakturujeme: |C19:M19 merge
__XLS;
  $n= $P-1;
  $xls.= <<<__XLS
    |C$n Položka              ::wrap middle       |C$n:G$n merge bold border=h
    |H$n Cena s DPH           ::wrap middle right |H$n:J$n merge bold border=h
    |K$n Sazba DPH            ::wrap middle right |K$n:K$n       bold border=h
    |L$n DPH                  ::wrap middle right |L$n:L$n       bold border=h
    |M$n Cena bez DPH         ::wrap middle right |M$n:M$n       bold border=h
__XLS;
  // řádky $P-$Q -- položky
  $n= $P;
  $d= $D;
  for ($i= 0; $i<count($druhy); $i++ ) {
    $xls.= <<<__XLS
      |C$n ='$listr'!H$d          |C$n:G$n merge
      |H$n ='$listr'!L$d   ::kc   |H$n:J$n merge
      |K$n ='$listr'!K$d   ::proc
      |L$n =H$n-M$n        ::kc
      |M$n =H$n/(1+K$n)    ::kc
__XLS;
    $n++; $d++;
  }
  $xls.= <<<__XLS
    |C$P:G$n border=h    |H$P:J$n border=h
    |K$P:K$n border=h    |L$P:L$n border=h    |M$P:M$n border=h
__XLS;
  // celková cena
  $d= $n;
  $n++;
  $suma= "'$listf'!L$n";
  $xls.= <<<__XLS
    |H$n Celková cena s DPH ::middle bold |H$n:K$n merge right
    |L$n =SUM(L$P:M$d)      ::kc          |L$n:M$n merge border=h
__XLS;
  $c= $n;
  $n++;
  $xls.= <<<__XLS
    |H$n Zaplaceno zálohou  ::middle bold |H$n:K$n merge right
    |L$n $zaloha            ::kc          |L$n:M$n merge border=h
__XLS;
  $z= $n;
  $n++;
  $xls.= <<<__XLS
    |H$n Zbývá k zaplacení  ::middle bold |H$n:K$n merge right
    |L$n =L$c-L$z           ::kc          |L$n:M$n merge border=h
__XLS;
  // řádky R,S (viz výše) -- spodek faktury
  $n= $R+1;
  $xls.= <<<__XLS
    |C$R vyřizuje ::bold
    |C$n Josef Náprstek, správce Domu setkání
    |C$S:M$S border=h,,,
    |C$S $pata | C$S:M$S merge middle center wrap
__XLS;
  return $xls;
}
# --------------------------------------------------------------------------------------- ds_faktura
# definice faktury
# typ = Zálohová | ''
# zaloha = 0..100  -- pokud je 100 negeneruje se řádek Záloha ...
# data zálohové faktury
# platce= [nazev,adresa,telefon,ic]
# polozky= [[nazev,cena,dph,pocet,sleva]...]
# }
function ds_faktura($list,$typ,$order,$polozky,$platce,$zaloha=100,$pata='') {  #trace('','win1250');
  list($ic,$dic,$adresa,$akce,$obdobi)= $platce;
  $vystaveno= Excel5_date(time());
  $ymca_setkani= "YMCA Setkání, spolek{}Talichova 53, 62300 Brno{}".
                 "zaregistrovaný Krajským soudem v Brně{}spisová značka: L 8556{}".
                 "IČ: 26531135  DIČ: CZ26531135";
  $dum_setkani=  "Dolní Albeřice 1, 542 26 Horní Maršov{}".
                 "telefon: 499 874 152, 736 537 122{}dum@setkani.org{}www.alberice.setkani.org";
  // pojmenované řádky (P,Q,R,S)
  $P= 22;               // výčet položek
  $Q= 36;               // poslední položka
  $R= 39;               // rozpis DPH
  $S= 45;               // poslední řádek
  // parametrizace
  $L7_ic= $ic ? "L7 IČ $ic" : '';
  $M7_dic= $dic ? "M7 DIČ $dic" : '';
  $c_okraj= "ff6495ed";
  // vytvoření listu s fakturou
  $xls= <<<__XLS
    |sheet $list;B2:N$S;P;page
    |columns A=3,B=0.6,C=16,D=3,E=22,F=6,G=1,H=10,I=4,J=6,K=6,L:M=16,N=1,O=3
    |rows 1=18,2:44=15,5=30,6=45,9=96,11=35,19=30,21=30,22:38=19,39=30,40:43=19,$S=30
    |A1:O46 bcolor=$c_okraj |B2:N$S bcolor=ffffffff |//B2:N$S border=h

    |image img/YMCA.png,80,C2,10,0
    |D2 Dům setkání :: bold size=14
    |D3 $dum_setkani
    |D3:H5 merge italic top wrap

    |J4 =CONCATENATE("$typ ",TEXT(3000+YEAR(M15),"0"),"/",TEXT(E16,"0")) :: bold size=16
    |J4:M5 merge right

    |C7 Dodavatel ::bold
    |C9 $ymca_setkani
    |C9:F9 merge top wrap

    |I7 Odběratel ::bold         |$L7_ic  |$M7_dic
    |I8:M10 border=h
    |J9 $adresa  |J9:M9 merge middle wrap size=14

    |C13 Peněžní ústav           |E13 Fio banka, a.s.
    |C14 Číslo účtu       ::bold |E14 2000465448/2010 ::bold
    |C15 Konstantní symbol       |E15 558 ::left
    |C16 Variabilní symbol       |E16 <číslo faktury> ::left bcolor=ffffffaa
    |C17 Specifický symbol       |E17 $akce ::left bcolor=ffffffaa

    |J12 Objednávka číslo ::bold |M12 $order  ::size=14 bold
    |J13 Dodací a platební podmínky: s daní
    |J14 Forma úhrady              |M14 převodem
    |J14 Datum vystavení           |M14 $vystaveno ::date bcolor=ffffffaa
    |J15 Datum zdanitelného plnění |M15 =M14       ::date bcolor=ffffffaa
    |J16 Datum splatnosti ::bold   |M16 =M14+14    ::date bcolor=ffffffaa bold
    |J18 Způsob platby             |M18 převod/hotovost ::bcolor=ffffffaa
    |C19 Fakturujeme vám zálohu na pobyt ve dnech $obdobi: |C19:M19 merge
__XLS;
  $n= $P-1;
  $xls.= <<<__XLS
    |C$n Položka              ::wrap middle       |C$n:E$n merge bold border=h
    |F$n Počet                ::wrap middle right |F$n:G$n merge bold border=h
    |H$n Cena položky vč. DPH ::wrap middle right |H$n:I$n merge bold border=h
    |J$n Sleva %              ::wrap middle right |J$n:J$n       bold border=h
    |K$n Sazba DPH            ::wrap middle right |K$n:K$n       bold border=h
    |L$n DPH                  ::wrap middle right |L$n:L$n       bold border=h
    |M$n Cena bez DPH         ::wrap middle right |M$n:M$n       bold border=h
__XLS;
  // řádky $P-$Q -- položky
  $n= $P;
  $sazby_dph= array();
  foreach ($polozky as $i=>$polozka) {
    list($nazev,$cena,$dph,$pocet,$druh,$sleva)= $polozka;
    if (!in_array($dph,$sazby_dph) ) $sazby_dph[]= $dph;
    if ( $pocet ) {
      $xls.= <<<__XLS
        |C$n $nazev                |C$n:E$n merge
        |F$n $pocet                |F$n:G$n merge
        |H$n $cena         ::kc    |H$n:I$n merge
        |J$n $sleva        ::proc
        |K$n $dph          ::proc
        |L$n =O$n-M$n      ::kc
        |M$n =O$n/(1+K$n)  ::kc
        |O$n =F$n*H$n*(1-J$n) ::kc color=$c_okraj
__XLS;
      $n++;
    }
  }
  $xls.= <<<__XLS
    |C$P:E$Q border=h    |F$P:G$Q border=h    |H$P:I$Q border=h
    |J$P:J$Q border=h    |K$P:K$Q border=h    |L$P:L$Q border=h    |M$P:M$Q border=h
__XLS;
  // celková cena
  $n= $Q+1;
  $xls.= <<<__XLS
    |H$n Celková cena s DPH ::middle bold |H$n:K$n merge right
    |L$n =SUM(L$P:M$Q)      ::middle kc   |L$n:M$n merge border=h
__XLS;
  if ( $zaloha<100 ) {
    $n++;
    $xls.= <<<__XLS
      |H$n Záloha $zaloha%             ::middle bold |H$n:K$n merge right
      |L$n =SUM(L$P:M$Q)*($zaloha/100) ::middle kc   |L$n:M$n merge border=h
__XLS;
  }
  // řádky R... -- rozpis DPH
  $n= $R+1;
  $xls.= <<<__XLS
    |K$R Rozpis DPH ::bold
    |K$n Sazba::middle bold border=h right
    |L$n Základ  ::middle bold border=h right
    |M$n Daň     ::middle bold border=h right
__XLS;
  sort($sazby_dph);
  foreach($sazby_dph as $sazba) {
    $n++;
    $xls.= <<<__XLS
      |K$n $sazba                                    ::proc border=h right
      |L$n =SUMIF(K$P:K$Q,K$n,M$P:M$Q)*($zaloha/100) ::kc   border=h right
      |M$n =SUMIF(K$P:K$Q,K$n,L$P:L$Q)*($zaloha/100) ::kc   border=h right
__XLS;
  }
  // řádky R,S (viz výše) -- spodek faktury
  $n= $R+1;
  $xls.= <<<__XLS
    |C$R vyřizuje ::bold
    |C$n Josef Náprstek, správce Domu setkání
    |C$S:M$S border=h,,,
    |C$S $pata | C$S:M$S merge middle center wrap
__XLS;
  return $xls;
}
# ----------------------------------------------------------------------------------------- ds_cenik
# načtení ceníku pro daný rok
function ds_cenik($rok) {  #trace('','win1250');
  global $ds_cena;
  $ds_cena= array();
  ezer_connect('setkani');
  $qry2= "SELECT * FROM ds_cena WHERE rok=$rok";
  $res2= mysql_qry($qry2);
  while ( $res2 && $c= mysql_fetch_object($res2) ) {
    $ds_cena[$c->typ]= $c;
  }
//                                                 debug($cena,'cena',(object)array('win1250'=>1));
}
# --------------------------------------------------------------------------------------------- ds_c
# položka faktury
# id,pocet => název,cena,dph%,pocet
function ds_c ($id,$pocet,$sleva='') {
  global $ds_cena;
  $c= array($ds_cena[$id]->polozka,$ds_cena[$id]->cena,$ds_cena[$id]->dph/100,$pocet,trim($ds_cena[$id]->druh));
  if ( $sleva ) $c[]= $sleva;
  return $c;
}
# ------------------------------------------------------------------------------------------- ds_vek
# zjištění věku v době zahájení akce
function ds_vek($narozeni,$fromday) {
  if ( $narozeni=='0000-00-00' )
    $vek= -1;
  else {
    $vek= $fromday-sql2stamp($narozeni);
    $vek= round($vek/(60*60*24*365.2425),1);
  }
  return $vek;
}
/** ========================================================================================> DOPISY */
# =========================================================================================> šablony
# ------------------------------------------------------------------------------------- dop_sab_text
# přečtení běžného dopisu daného typu
function dop_sab_text($dopis) { //trace();
  $d= null;
  try {
    $qry= "SELECT id_dopis,obsah FROM ezer_ys.dopis WHERE typ='$dopis' AND id_davka=1 ";
    $res= mysql_qry($qry,1,null,1);
    $d= mysql_fetch_object($res);
  }
  catch (Exception $e) { display($e); fce_error("dop_sab_text: průběžný dopis '$dopis' nebyl nalezen"); }
  return $d;
}
# ------------------------------------------------------------------------------------- dop_sab_cast
# přečtení části šablony
function dop_sab_cast($druh,$cast) { //trace();
  $d= null;
  try {
    $qry= "SELECT id_dopis_cast,obsah FROM ezer_ys.dopis_cast WHERE druh='$druh' AND name='$cast' ";
    $res= mysql_qry($qry,1,null,1);
    $d= mysql_fetch_object($res);
  }
  catch (Exception $e) { display($e); fce_error("dop_sab_cast: část '$cast' sablony nebyla nalezena"); }
  return $d;
}
# ----------------------------------------------------------------------------------- dop_sab_nahled
# ukázka šablony
function dop_sab_nahled($k3) { trace();
  global $ezer_path_docs;
  $html= '';
  $fname= "sablona.pdf";
  $f_abs= "$ezer_path_docs/$fname";
  $f_rel= "docs/$fname";
  ezer_connect('ezer_ys');
  $html= tc_sablona($f_abs,'','D');                 // jen části bez označení v dopis_cast.pro
  $date= @filemtime($f_abs);
  $href= "<a target='dopis' href='$f_rel'>$fname</a>";
  $html.= "Byl vygenerován PDF soubor: $href (verze ze ".date('d.m.Y H:i',$date).")";
  $html.= "<br><br>Jméno za 'vyřizuje' se bere z osobního nastavení přihlášeného uživatele.";
  return $html;
}
# =======================================================================================> potvrzení
# ---------------------------------------------------------------------------------------- ucet_potv
# přehled podle tabulky 'prijate dary' na intranetu
function ucet_potv($par) { trace();
  $html= '';
  $darce= array();
  $xls= "prijate_dary";
  $max= 9999;
//   $max= 30;
  $rok= date('Y')+$par->rok;
  $let18= date('Y')-18;
  $cells= google_sheet($rok,$xls,'answer@smidek.eu',$google);
  if ( !$cells ) { $html.= "Tabulka <b>$xls/$rok</b> nebyla v intranetu nalezena"; goto end; }
  list($max_A,$max_n)= $cells['dim'];
  // výběr záznamů o darech
  $prblm1= $prblm2= '';
  $jmeno_prvni= array();  // ke klíči $prijmeni$jmeno dá řádek s prvním výskytem
  $jmeno_id= array();     // ke klíči $prijmeni$jmeno dá id nebo 0
  $nalezeno= 0;
  for ($i= 2; $i<=$max_n; $i++) {
    $datum= $cells['A'][$i];
    $dar_jmeno= $cells['B'][$i];
    $castka= $cells['C'][$i];
    $ref= $cells['D'][$i];              // 4
    $auto= $cells['E'][$i];             // 5
    $manual= $cells['F'][$i];
    list($prijmeni,$jmeno)= explode(' ',substr($dar_jmeno,6));
    $opakovane= $jmeno_prvni["$prijmeni$jmeno"] ?: 0;
    if ( !$datum ) break;
    // zapiš opakujícímu se dárci odkaz na řádek s jeho prvním darem
    if ( !$opakovane ) {
      $jmeno_prvni["$prijmeni$jmeno"]= $i;
    }
    if ( !$ref && $opakovane ) {
      $updatedCell= $google->service->updateCell($i,4,$opakovane,$google->sskey,$google->wskey);
    }
    // doplnění intranetové tabulky a střádání darů do tabulky $darce
    if ( !$auto && !$manual && !$opakovane ) {
      // pokusíme se nalézt dárce
      $idss= array();
      $ids= '';
      $qo= mysql_qry("
        SELECT id_osoba FROM osoba AS o
        WHERE jmeno='$jmeno' AND prijmeni='$prijmeni'
          AND IF(narozeni!='0000-00-00',YEAR(narozeni)<$let18,1)
      ");
      while ($qo && ($o= mysql_fetch_object($qo))) {
        $idss[]= $o->id_osoba;
      }
      if ( count($idss) ) {
        $ids= implode(', ',$idss);
        // zápis do auto
        $updatedCell= $google->service->updateCell($i,5,$ids,$google->sskey,$google->wskey);
        if ( count($idss)==1 ) {
          $jmeno_id["$prijmeni$jmeno"]= $ids;
        }
        else
          $prblm1.= ($prblm1?"<br>":'')."$i: $datum $prijmeni $jmeno $castka ($ids)";
      }
      else {
        $prblm2.= ($prblm2?"<br>":'')."$i: $datum $prijmeni $jmeno $castka";
      }
    }
    elseif ( strpos($auto,',') && !$manual && !$ref ) {
      $prblm1.= ($prblm1?"<br>":'')."$i: $datum $prijmeni $jmeno $castka ($auto)";
    }
    elseif ( $manual ) {
      if ( $manual=='x' )
        $prblm3.=  ($prblm3?"<br>":'')."x $dar_jmeno $castka";
      else
        $jmeno_id["$prijmeni$jmeno"]= $manual;
    }
    elseif ( $auto && strpos($auto,',')===false ) {
      $jmeno_id["$prijmeni$jmeno"]= $auto;
    }
    // střádání darů od jednoznačně určeného dárce
    $id= $jmeno_id["$prijmeni$jmeno"];
    if ( $id && $castka ) {
      if ( !isset($darce[$id]) ) {
        $darce[$id]= (object)array('data'=>array(),'castka'=>0,'jmeno'=>"$prijmeni $jmeno");
      }
      list($d,$m,$y)= preg_split("/[\/\.]/",$datum);
      $m= 0+$m; $d= 0+$d;
      $darce[$id]->data[]= "$d. $m.";
      $darce[$id]->castka+= $castka;
    }
    if ( --$max <= 0 ) break;
  }
  $reseni= "<br><br>doplň v intranetovém sešitu <b>$xls</b> v listu <b>$rok</b> do sloupce <b>F</b>
            správné osobní číslo dárce (zjistí se v Evidenci), jen do prvního výskytu dárce";
  if ( $prblm1 ) $html.= "<h3>Nejednoznačná jména v rámci evidence YS</h3>$prblm1$reseni";
  if ( $prblm2 ) $html.= "<h3>Neznámá jména v rámci evidence YS</h3>$prblm2$reseni";
  if ( $prblm3 ) $html.= "<h3>Ručně napsaná potvrzení</h3>$prblm3";
  if ( !$prblm1 && !$prblm2 ) $html.= "<h3>Ok</h3>všichni dárci byli jednoznačně identifikováni :-)";
  // zápis do tabulky dar, pokud se to chce
  if ( $druh= $par->save ) {
    // smazání záznamů o účetních darech
    query("DELETE FROM dar WHERE YEAR(dat_od)=$rok AND zpusob='u'");
    // zápis zjištěných darů
    $n= 0;
    foreach ($darce as $id=>$dary) {
      $data= implode(', ',$dary->data)." $rok";
      $pars= ezer_json_encode((object)array('data'=>$data));
      $oki= query("INSERT INTO dar (id_osoba,ukon,zpusob,castka,dat_od,note,pars)
        VALUES ($id,'d','u',{$dary->castka},'$rok-12-31','daňové potvrzení','$pars')");
      $n+= $oki ? mysql_affected_rows () : 0;
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
      elseif ( $id_dar && $castka2 >= 400 ) {
        $pars= ezer_json_encode((object)array('data'=>$data,'bylo'=>$castka1));
                                        display("{$dary->jmeno} $castka1 - $castka2");
        $oku= query("UPDATE dar
          SET castka=$castka2, note='2.daňové potvrzení', pars='$pars'
          WHERE id_dar=$id_dar");
        $n2+= $oku ? mysql_affected_rows () : 0;
      }
      elseif ( !$id_dar && $castka2 >= 400 ) {
        $oki= query("INSERT INTO dar (id_osoba,ukon,zpusob,castka,dat_od,note,pars)
          VALUES ($id,'d','u',$castka2,'$rok-12-31','2.daňové potvrzení','$pars')");
        $n4+= $oki ? mysql_affected_rows () : 0;
      }
      else {
        $n3++;
      }
    }
    $html.= "<br><br>dárců za rok $rok: přidáno $n4, opraveno $n2, bez opravy $n1, $n3 pod 400 Kč";
  }
end:
  return (object)array('html'=>$html,'href'=>$href);
}
/** ========================================================================================> GOOGLE */
# ------------------------------------------------------------------------------------- google_sheet
# přečtení listu $list z tabulky $sheet uživatele $user do pole $cell
# $cells['dim']= array($max_A,$max_n)
function google_sheet($list,$sheet,$user='answer@smidek.eu',&$keys) {  trace();
  $keys= (object)array();
  $n= 0;
  $cells= null;
  require_once 'Zend/Loader.php';
  Zend_Loader::loadClass('Zend_Http_Client');
  Zend_Loader::loadClass('Zend_Gdata');
  Zend_Loader::loadClass('Zend_Gdata_ClientLogin');
  Zend_Loader::loadClass('Zend_Gdata_Spreadsheets');
  // autentizace
  $pass= array('answer@smidek.eu'=>'8nswer','martin@smidek.eu'=>'radost2010');
  if ( $pass[$user] ) {
    $authService= Zend_Gdata_Spreadsheets::AUTH_SERVICE_NAME;
    $httpClient= Zend_Gdata_ClientLogin::getHttpClient($user,$pass[$user], $authService);
//                                         display("Answer autorizovan: ".($httpClient?1:0));
    // nalezení tabulky
    $gdClient= new Zend_Gdata_Spreadsheets($httpClient);
//                                         display("new Zend_Gdata_Spreadsheets".($gdClient?1:0));
    $keys->service= $gdClient;
//                                         display("getSpreadsheetFeed - před");
    $feed= $gdClient->getSpreadsheetFeed();
//                                         display("getSpreadsheetFeed - po");
                                        goto end;
    $table= getFirstFeed($feed,$sheet);
    if ( $table ) {
      // pokud tabulka existuje
      $table_id= explode('/', $table->id->text);
      $table_key= $table_id[5];
      $keys->sskey= $table_key;
      // najdi list
      $query= new Zend_Gdata_Spreadsheets_DocumentQuery();
      $query->setSpreadsheetKey($table_key);
      $feed= $gdClient->getWorksheetFeed($query);
      $ws= getFirstFeed($feed,$list);
    }
    if ( $table && $ws ) {
      $cells= array();
      // pokud list tabulky existuje
      $ws_id= explode('/', $ws->id->text);
      $ws_key= $ws_id[8];
      $keys->wskey= $ws_key;
      // načti buňky
      $query= new Zend_Gdata_Spreadsheets_CellQuery();
      $query->setSpreadsheetKey($table_key);
      $query->setWorksheetId($ws_key);
      $feed= $gdClient->getCellFeed($query);
      $max_n= 0;
      foreach($feed->entries as $entry) {
        if ($entry instanceof Zend_Gdata_Spreadsheets_CellEntry) {
          $An= $entry->title->text;
          $A= substr($An,0,1); $n= substr($An,1);
          $cells[$A][$n]= $entry->content->text;
          $max_A= max($max_A,$A);
          $max_n= max($max_n,$n);
        }
      }
      $cells['dim']= array($max_A,$max_n);
    }
  }
end:
  return $cells;
}
# --------------------
function getFirstFeed($feed,$id=null) {
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
?>
