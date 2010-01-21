<?php # (c) 2009 Martin Smidek <martin@smidek.eu>
# -------------------------------------------------------------------------------------------------- dt
# na datum na str�nce z timestamp v tabulce
function dt($x,$user2sql=0) { #trace('','win1250');
  if ( $user2sql ) {
    // p�eve� u�ivatelskou podobu na sql tvar
    $y= sql2stamp($x);
  }
  else {
    // p�eve� sql tvar na u�ivatelskou podobu (default)
    $y= date("j.n.Y", $x);
  }
  return $y;
}
# -------------------------------------------------------------------------------------------------- ds_compare_list
function ds_compare_list($orders) {  #trace('','win1250');
  $errs= 0;
  $html= "<dl>";
  $n= 0;
  if ( $orders ) {
    foreach (explode(',',$orders) as $order) {
      $x= ds_compare($order);
      $html.= wu("<dt>Objedn�vka <b>$order</b> {$x->pozn}</dt>");
      $html.= "<dd>{$x->html}</dd>";
      $errs+= $x->err;
      $n++;
    }
  }
  $html.= "</dl>";
  $msg= "V tomto obdob� je celkem $n objedn�vek";
  $msg.= $errs ? ", z toho $errs ne�pln�." : "." ;
  $result= (object)array('html'=>$html,'msg'=>wu($msg));
  return $result;
}
# -------------------------------------------------------------------------------------------------- ds_compare
function ds_compare($order) {  #trace('','win1250');
  ezer_connect('setkani');
  // �daje z objedn�vky
  $qry= "SELECT * FROM setkani.tx_gnalberice_order WHERE uid=$order";
  $res= mysql_qry($qry);
  if ( !$res ) fce_error(wu("$order nen� platn� ��slo objedn�vky"));
  $o= mysql_fetch_object($res);
  // projit� seznamu
  $qry= "SELECT * FROM setkani.ds_osoba WHERE id_order=$order ";
  $reso= mysql_qry($qry);
  $n= $n_0= $n_3= $n_9= $n_15= $n_a= $noroom= 0;
  while ( $reso && $u= mysql_fetch_object($reso) ) {
    // rozd�len� podle v�ku
    $n++;
    $vek= ds_vek($u->narozeni,$o->fromday);
    if ( $vek>15 ) $n_a++;
    elseif ( $vek>9 ) $n_15++;
    elseif ( $vek>3 ) $n_9++;
    elseif ( $vek>0 ) $n_3++;
    else $n_0++;
    // kdo nebydl�?
    if ( !$u->pokoj ) $noroom++;
  }
  // posouzen� po�t�
  $no= $o->adults + $o->kids_10_15 + $o->kids_3_9 + $o->kids_3;
  $age= $n_a==$o->adults && $n_15==$o->kids_10_15 && $n_9==$o->kids_3_9 && $n_3==$o->kids_3;
  // zhodnocen� �plnosti
  $err= $n==0 || $n>0 && $n!=$no || $noroom || $n_0 || $n>0 && !$age ? 1 : 0;
  // textov� zpr�va
  $html= '';
  $html.= $n==0 ? "Seznam ��astn�k� je pr�zdn�. " : '';
  $html.= $n>0 && $n!=$no ? "Seznam ��astn�k� nen� �pln�. " : '';
  $html.= $noroom ? "Jsou zde neubytovan� host�. " : '';
  $html.= $n_0 ? "N�kte�� host� nemaj� vypln�no datum narozen�. " : '';
  $html.= $n>0 && !$age ? "St��� host� se li�� od p�edpoklad� objedn�vky." : '';
  if ( !$html ) {
    $html= "Seznam ��astn�k� odpov�d� objedn�vce.";
    $pozn= " - <b style='color:green'>ok</b> ";
  }
  else {
    $pozn= $n ? " - aspo� n�co" : " - nic";
  }
  $form= (object)array('adults'=>$n_a,'kids_10_15'=>$n_15,'kids_3_9'=>$n_9,'kids_3'=>$n_3,
    'nevek'=>$n_0, 'noroom'=>$noroom);
  $result= (object)array('html'=>wu($html),'form'=>$form,'err'=>$err,'pozn'=>$pozn);
  return $result;
}
# ================================================================================================== HOST�
# -------------------------------------------------------------------------------------------------- ds_kli_menu
# vygeneruje menu pro lo�sk�, leto�n� a p��t� rok ve tvaru objektu pro ezer2 pro zobrazen� klient�
# ur�uj�c� je datum zah�jen� pobytu v objedn�vce
function ds_kli_menu() {
  ezer_connect('setkani');
  $the= '';                     // prvn� v tomto m�s�ci �i pozd�ji
  $rok= date('Y');
  $ted= date('Ym');
  $mesice= array(1=>'leden','�nor','b�ezen','duben','kv�ten','�erven',
    '�ervenec','srpen','z���','��jen','listopad','prosinec');
  $mn= (object)array('type'=>'menu.left'
      ,'options'=>(object)array(),'part'=>(object)array());
  $letos= date('Y');
  for ($y= 0; $y<=0; $y++) {
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
# ================================================================================================== OBJEDN�VKY
# -------------------------------------------------------------------------------------------------- ds_obj_menu
# vygeneruje menu pro lo�sk�, leto�n� a p��t� rok ve tvaru objektu pro ezer2 pro zobrazen� objedn�vek
# ur�uj�c� je datum zah�jen� pobytu v objedn�vce
function ds_obj_menu() {
  global $mysql_db;
  $stav= map_cis('ds_stav');
  $the= '';                     // prvn� objedn�vka v tomto m�s�ci �i pozd�ji
//                                                                 debug($stav,'ds_obj_menu',(object)array('win1250'=>1));
  $mesice= array(1=>'leden','�nor','b�ezen','duben','kv�ten','�erven',
    '�ervenec','srpen','z���','��jen','listopad','prosinec');
  $mn= (object)array('type'=>'menu.left'
      ,'options'=>(object)array(),'part'=>(object)array());
  $letos= date('Y');
  $ted= date('Ym');
  ezer_connect('setkani');
  for ($y= 0; $y<=0; $y++) {
    for ($m= 1; $m<=12; $m++) {
      $mm= sprintf('%02d',$m);
      $yyyy= $letos+$y;
      $group= "$yyyy$mm";
      $gr= (object)array('type'=>'menu.group'
        ,'options'=>(object)array('title'=>wu($mesice[$m])." $yyyy"),'part'=>(object)array());
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
# -------------------------------------------------------------------------------------------------- pin_make
# vytvo� PIN k ��slu x
function pin_make ($x) {
  // jedin� kolize pro ��sla 1..2000
  $str= str_pad($x,6,'0',STR_PAD_LEFT);
  $pin= substr(sprintf("%u",crc32(strrev($str))),-6);
  return $pin;
}
# -------------------------------------------------------------------------------------------------- pin_test
# test PIN - dopln�me ho nulami zleva
function pin_test ($x,$pin) {
  $str= str_pad($x,6,'0',STR_PAD_LEFT);
  $strpin= str_pad($pin,6,'0',STR_PAD_LEFT);
  $xpin= substr(sprintf("%u",crc32(strrev($str))),-6);
  return $strpin==$xpin?1:0;
}
# -------------------------------------------------------------------------------------------------- ds_ucast_objednatele
function ds_ucast_objednatele($id_order,$ano) {  #trace('','win1250');
  global $x;
  if ( $ano ) {
    ezer_connect('setkani');
    $qry= "SELECT * FROM setkani.tx_gnalberice_order WHERE uid=$id_order";
    $res= mysql_qry($qry);
    if ( $res && $o= mysql_fetch_object($res) ) {
      if ( $jmeno= $o->firstname ) $prijmeni= $o->name;
      else list($jmeno,$prijmeni)= explode(' ',$o->name);
      if ( !$prijmeni ) { $prijmeni= $jmeno; $jmeno= ''; }
      $qry= "INSERT INTO setkani.ds_osoba (
        id_order,rodina,prijmeni,jmeno,psc,obec,ulice,email,telefon) VALUES (
        $id_order,'','$prijmeni','$jmeno',
        '{$o->zip}','{$o->city}','{$o->address}','{$o->email}','{$o->telephone}')";
      $res= mysql_qry($qry);
    }
  }
  return $ano;
}
# ================================================================================================== RODINA
# -------------------------------------------------------------------------------------------------- pary
# test autocomplete
function pary($patt) {  #trace('','win1250');
  $a= array();
  $limit= 10;
  $n= 0;
  // rodi�e
  $qry= "SELECT source,cislo AS _key,concat(jmeno,' ',jmeno_m,' a ',jmeno_z,' - ',mesto) AS _value
         FROM ms_pary
         WHERE jmeno LIKE '$patt%' ORDER BY jmeno LIMIT $limit";
  $res= mysql_qry($qry);
  while ( $res && $t= mysql_fetch_object($res) ) {
    if ( ++$n==$limit ) break;
    $key= "{$t->_key}".($t->source=='L'?0:1);
    $a[$key]= "{$t->source}:{$t->_value}";
  }
  // obecn� polo�ky
  if ( !$n )
    $a[0]= wu("... ��dn� jm�no neza��n� '")."$patt'";
  elseif ( $n==$limit )
    $a[-999999]= wu("... a dal��");
                                                                debug($a,$patt,(object)array('win1250'=>1));
  return $a;
}
# -------------------------------------------------------------------------------------------------- rodina
# test autocomplete
function rodina($xcislo) {  #trace('','win1250');
  $rod= array();
  // rodi�e
  $source= $xcislo % 2 ? 'M' : 'L';
  $cislo= round($xcislo/10);
  $qry= "SELECT * FROM ms_pary WHERE source='$source' AND cislo=$cislo";
  $res= mysql_qry($qry);
  if ( $res && $p= mysql_fetch_object($res) ) {
    rodina_add(&$rod,$p->prijmeni_m,$p->jmeno_m,$p->rodcislo_m,$p->telefon,$p->email,$p);
//                                                         display("{$p->prijmeni_m}:{$p->rodcislo_m}:$narozeni");
    rodina_add(&$rod,$p->prijmeni_z,$p->jmeno_z,$p->rodcislo_z,$p->telefon,$p->email,$p);
  }
  // d�ti
  $qry= "SELECT * FROM ms_deti WHERE source='$source' AND cislo=$cislo";
  $res= mysql_qry($qry);
  while ( $res && $d= mysql_fetch_object($res) ) {
    $prijmeni= rc2man($d->rodcislo) ? $p->prijmeni_m : $p->prijmeni_z;
    rodina_add(&$rod,$prijmeni,$d->jmeno,$d->rodcislo,' ',' ',$p);
  }
//                                                                 debug($rod,$cislo,(object)array('win1250'=>1));
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
         $r<=4        ? "roky" : "rok�" )));
  return wu("$r $roku, r�:$rc");
}
# ================================================================================================== CENY
# -------------------------------------------------------------------------------------------------- ds_xls_hoste
# kopie cen�ku
# par:
#   .op='kopie'
#   .z=star� rok
#   .na=nov� rok
function ds_ceny_uprava($par) { #trace('','win1250');
  $html= "";
  if ( $par->op=='kopie' ) {
    $html.= "Zkopirovano";
  }
  return $html;
}
# ================================================================================================== EXPORTY DO EXCELU
# -------------------------------------------------------------------------------------------------- ds_xls_hoste
# definice Excelovsk�ho listu - seznam host�
function ds_xls_hoste($orders,$mesic_rok) {  #trace('','win1250');
  $x= ds_hoste($orders,substr($mesic_rok,-4));
  $name= cz2ascii("hoste_$mesic_rok");
  $mesic_rok= uw($mesic_rok);
  $xls= <<<__XLS
    |open $name
    |sheet hoste;;L;page
    |columns A=6,B=10,C=13,D=40,E=15,F=13,G=30,H=12,I=12
    |A1 Seznam host� zahajuj�c�ch pobyt v obdob� $mesic_rok ::bold size=14
    |A3 akce    |B3 jm�no |C3 p��jmen� |D3 adresa  |E3 datum narozen� ::right date
    |F3 telefon |G3 email |H3 term�n   |I3 rekr.popl. ::right
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
  $inf= Excel5(wu($xls),1);
  if ( $inf ) {
    $html= " se nepoda�ilo vygenerovat - viz za��tek chybov� hl�ky";
    fce_error(wu($inf));
  }
  else
    $html= " Byl vygenerov�n seznam host� ve form�tu <a href='docs/$name.xls' target='xls'>Excel</a>.";
  return wu($html);
}
# -------------------------------------------------------------------------------------------------- ds_hoste
# vytvo�� seznam host�
# cen�k beer podle p�edan�ho roku
# {table:id,obdobi:str,hoste:[[jmeno,prijmeni,adresa,narozeni,telefon,email,termin,poplatek]...]}
function ds_hoste($orders,$rok) {  #trace('','win1250');
  global $ds_cena, $ezer_path_serv;
  require_once "$ezer_path_serv/licensed/xls2/Classes/PHPExcel/Calculation/Functions.php";
  ds_cenik($rok);
//                                                                 debug($ds_cena,'ds_cena',(object)array('win1250'=>1));
  $x= (object)array();
  $x->table= "klienti_$obdobi";
  $x->hoste= array();
  ezer_connect('setkani');
  // zji�t�n� klient� zahajuj�c�ch pobyt v dan�m obdob�
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
    // rekrea�n� poplatek
    if ( $vek>=18 || $vek<0 )
      $popl= $ds_cena['ubyt_C']->cena + $ds_cena['ubyt_S']->cena;
    else
      $popl= $ds_cena['ubyt_P']->cena;
    // p�ips�n� ��dku
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
//                                                                 debug($x,'hoste',(object)array('win1250'=>1));
  return $x;
}
# ================================================================================================== Z�LOHOV� FAKTURA
# ASK
# -------------------------------------------------------------------------------------------------- ds_xls_zaloha
# definice Excelovsk�ho listu - z�lohov� faktury
function ds_xls_zaloha($order) {  #trace('','win1250');
  global $ezer_path_serv;
  $name= "zal_$order";
  // vytvo�en� se�itu s fakturou
  $xls= "|open  $name|";
  $x= ds_zaloha($order);
  $xls.= ds_faktura('zalohova_faktura','Z�LOHOV� FAKTURA',$order,$x->polozky,$x->platce,50,
    "T��me se na V� pobyt v Dom� setk�n�");
  $xls.= "|close|";
  $inf= Excel5(wu($xls),1);
  if ( $inf ) {
    $html= " nastala chyba";
    fce_error(wu($inf));
  }
  else
    $html= " <a href='docs/$name.xls' target='xls'>z�lohov� faktura</a>.";
  return wu($html);
}
# -------------------------------------------------------------------------------------------------- ds_zaloha
# data z�lohov� faktury
# {objednavka:n,
#  platce:[nazev,adresa,telefon,ic]
#  polozky:[[nazev,cena,dph,pocet]...]
# }
function ds_zaloha($order) {  #trace('','win1250');
  global $ds_cena;
  $x= (object)array();
  // zji�t�n� �daj� objedn�vky
  ezer_connect('setkani');
  $qry= "SELECT * FROM tx_gnalberice_order WHERE uid=$order";
  $res= mysql_qry($qry);
  if ( $res && $o= mysql_fetch_object($res) ) {
    $obdobi= date('j.n',$o->fromday).' - '.date('j.n.Y',$o->untilday);
    $dnu= ($o->untilday-$o->fromday)/(60*60*24);
//                                                         display("pocet dnu=$dnu");
    // p�e�ten� cen�ku dan�ho roku
    ds_cenik(date('Y',$o->untilday));
    // �daje o pl�tci: $ic,$dic,$adresa,$akce
    $platce= array();
    $platce[]= $o->ic ? $o->ic : '';
    $platce[]= $o->dic ? $o->dic : '';
    $platce[]= ($o->org ? "{$o->org}{}" : '')."{$o->firstname} {$o->name}{}".
              "{$o->address}{}{$o->zip} {$o->city}";
    $platce[]= $o->akce;
    $platce[]= $obdobi;
    $x->platce= $platce;
    // polo�ky z�lohov� faktury
    // ubytov�n� m��e m�t slevu
    $polozky= array();
    $sleva= $o->sleva ? $o->sleva/100 : '';
    $x->polozky[]= ds_c('noc_L',$dnu*($o->adults+$o->kids_10_15+$o->kids_3_9),$sleva);
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
//                                                                 debug($x,'zaloha',(object)array('win1250'=>1));
  }
  return $x;
}
# ================================================================================================== KONE�N� FAKTURA
# -------------------------------------------------------------------------------------------------- ds_xls_faktury
# ASK
# definice Excelovsk�ho listu - faktury podle seznamu ��astn�k�
# polo�ka,po�et osob,po�et noc�,po�et nocol��ek,cena jednotkov�,celkem,DPH 9%,DPH 19%,z�klad
# *ubytov�n�
function ds_xls_faktury($order) {  trace('','win1250');
  global $ds_cena;
  $test= 1;
  $x= ds_faktury($order);
  $ds_cena['zzz_zzz']= 0;    // p�id�n� pr�zdn�ho ��dku
  ksort($ds_cena);

//                                                                 debug($ds_cena,'ds_cena',(object)array('win1250'=>1));
//                                                                 debug($x,'faktura',(object)array('win1250'=>1));
  // barvy
  $c_edit= "ffffffaa";
  // �vodn� list
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
    # ---------------------------------------------------------------- �lenov� rodiny a polo�ky faktury
    $i= 0;
    $clmn= "A=10,B=13,C=-30,D=-15,E=4,F=-20,G=-30,H=20,I=5,J=6";
    $tit= "|A3 jm�no |B3 p��jmen� |C3 adresa |D3 narozen/a ::right |E3 v�k |F3 telefon "
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
      // sou�ty
    }
    $tit.= "|A$n:$B$n bcolor=ffaaaaaa |";
    $xls.= "\n\n|sheet $sheet_rodina;;L;page|columns $clmn |$tit |";
    $skupiny= count($x->rodiny)==1 ? '' : ($rodina=='' ? "neozna�en�ch host�" : "host� ozna�en�ch '$rodina'");
    $xls.= "|A1 Kone�n� vy��tov�n� $skupiny v r�mci objedn�vky $order ::bold size=14|";
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
      // polo�ky
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
            $row.= $druh0=='noc' ? "|$B$n =(1-$A$n)*($suma) ::kc" : "|$B$n =$suma ::kc";
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
    // polo�ky faktury
    $polozky= array();
    foreach($ds_cena as $dc=>$cena) {
      if ( $cena ) {    // bez pr�zdn�ho ��dku
        list($druh,$cast)= explode('_',$dc);
        $sleva= $druh=='noc' ? "=$An_sleva" : '';
        $zaloha= "=$An_zaloha";
        $polozky[]= ds_c($dc,$cena->pocet,$sleva,$zaloha);        // id,pocet => n�zev,cena,dph%,pocet,druh
      }
    }
    // vytvo�en� listu
    $xls.= ds_rozpis_faktura($sheet_rozpis,$sheet_faktura,'FAKTURA',$order,$x,$polozky,$platce,100,
      "T��me se na V� dal�� pobyt v Dom� setk�n�",$zaloha,&$suma);
    $faktury.= "";
  }
  // ------------------------------------------------------------------ cen�k
//                                                                 debug($ds_cena,'ds_cena',(object)array('win1250'=>1));
  $xls.= <<<__XLS
  \n\n|sheet cenik;;P;page
    |columns A=35,B=20,C=20
    |A1 Seznam ��tovateln�ch polo�ek ::bold size=14
    |A3 polo�ka |B3 cena v�.DPH ::right |C3 DPH ::right proc
    |A3:C3 bcolor=ffaaaaaa
__XLS;
  $n= 4;
  foreach ($ds_cena as $i=>$cena) {
    if ( $cena ) {    // bez pr�zdn�ho ��dku
      $dph= $cena->dph/100;
      $xls.= <<<__XLS
        |A$n {$cena->polozka} |B$n {$cena->cena} :: kc |C$n $dph :: proc
        |A$n:C$n border=,,h,
__XLS;
      $n++;
    }
  }
  # ------------------------------------------------------------------ seznam rodin (jako prvn�)
  $nf1= $nf+1;
  $name= "fak_$order";
  $final_xls= <<<__XLS
  \n|open $name
  \n\n|sheet rodiny;;L;page
    |columns A=20,B=13,C=10,D:F=16
    |A1 Seznam rodin (skupin), kter�m fakturujeme pobyt v r�mci objedn�vky $order ::bold size=14
    |A3 rodina (skupina)|B3 osob ::right |C3 sleva ::right |D3 celkov� cena ::right
    |E3 z�loha ::right|F3 doplatit ::right
    |A3:F3 bcolor=ffaaaaaa
    |$faktury
    |A$nf1 CELKEM ::right bold|B$nf1 =SUM(B4:B$nf) ::bold|D$nf1 =SUM(D4:D$nf) ::kc bold
    |E$nf1 =SUM(E4:E$nf) ::kc bold|F$nf1 =SUM(F4:F$nf) ::kc bold
__XLS;
  // vysv�tlivky
  $n= $nf1+3;
  $final_xls.= "|A$n Upozorn�n�: v tomto se�itu je mo�n� upravovat pouze �lut� podlo�en� pol��ka, ".
    "zm�ny jin�ch pol��ek pravd�podobn� po�kod� v�po�et cen a DPH. Pokud je t�eba m�nit �daje, ".
    "kter� nejsou �lut� podlo�eny, je pot�eba to ud�lat v syst�mu Ans(w)er a znovy vygenerovat tento ".
    "se�it.";
  $final_xls.= "|A$n:F$n merge italic top wrap
    |rows $n=60
    |$xls|close 1";
//                                                                 display("rodiny=$faktury");
//                                                                 display(nl2br(wu($xls)));
  if ( $test )
    file_put_contents("xls.txt",$final_xls);
  time_mark('ds_xls_faktury Excel5');
  $inf= Excel5(wu($final_xls),1);
  if ( $inf ) {
    $html= " nastala chyba";
    fce_error(wu($inf));
  }
  else
    $html= " <a href='docs/$name.xls' target='xls'>kone�n� faktura</a>.";
  // p��padn� testovac� v�pis
  time_mark('ds_xls_faktury end');
  return wu($html);
}
# -------------------------------------------------------------------------------------------------- ds_faktury
# podklady ke kone�n� fakturaci
# {platce:[ic,dic,adresa,akce],faktury:[[rodina,po�et,sleva]...],rodiny:[<host>...],<cen�k>,<chyby>}
#    <host> :: {host:[jmeno,prijmeni,adresa1,adresa2,narozeni,vek,telefon,email,od,do,<ubyt>],
#               cena:{<ubyt>,<strava>,<spec>,<popl>}
#    <ubyt> :: noci:n,pokoj:1..16,pokoj_typ:P|S|B,luzko_typ:L|P|B
#  <strava> :: CC:n,CP:n,PC:n,PP:n
#    <spec> :: postylka:n,zvire_noci:n
#    <popl> :: popl:CS|P
#   <cen�k> :: cenik:... viz global $ds_cena
#   <chyby> :: chyby:[[text,...]...]
#
function ds_faktury($order) {  trace('','win1250');
  global $ds_cena;
  $x= (object)array('faktury'=>array(),'rodiny'=>array());
  // ��seln�ky                    1   2   3   4   5   6   7   8   9   10  11  12  13  14  15  16
  $luzko_pokoje= array(0=>'?',1=>'L','L','L','L','L','L','L','S','S','L','L','L','S','S','A','A');
  $ds_luzko=  map_cis('ds_luzko','zkratka');  $ds_luzko[0]=  '?';
  $ds_strava= map_cis('ds_strava','zkratka'); $ds_strava[0]= '?';
//                                                                 debug($ds_strava,(object)array('win1250'=>1));
  // kontrola objedn�vky
  ezer_connect('setkani');
  $qry= "SELECT * FROM setkani.tx_gnalberice_order WHERE uid=$order";
  $res= mysql_qry($qry);
  if ( $res && $o= mysql_fetch_object($res) ) {
    $obdobi= date('j.n',$o->fromday).' - '.date('j.n.Y',$o->untilday);
    // �daje o pl�tci: $ic,$dic,$adresa
    $platce= array();
    $platce[]= $o->ic ? $o->ic : '';
    $platce[]= $o->dic ? $o->dic : '';
    $platce[]= ($o->org ? "{$o->org}{}" : '')."{$o->firstname} {$o->name}{}".
              "{$o->address}{}{$o->zip} {$o->city}";
    $platce[]= $o->akce;
    $platce[]= $obdobi;
    $x->objednavka= array($obdobi);
    $x->platce= $platce;
    // p�e�ten� cen�ku dan�ho roku
    ds_cenik(date('Y',$o->untilday));
    // zji�t�n� po�tu faktur za akci
    $qry= "SELECT rodina,count(*) as pocet FROM setkani.ds_osoba
           WHERE id_order=$order GROUP BY rodina ORDER BY if(rodina='','zzzzzz',rodina)";
    $res= mysql_qry($qry);
    while ( $res && $r= mysql_fetch_object($res) ) {
      // seznam faktur
      $rid= $r->rodina ? $r->rodina : 'ostatni';
      $x->faktury[]= array($rid,$r->pocet,$o->sleva/100);
      // �lenov� jedn� rodiny s �daji
      $hoste= array();
      $err= array();
      $qry= "SELECT * FROM setkani.ds_osoba
             WHERE id_order=$order AND rodina='{$r->rodina}' ORDER BY narozeni DESC";
      $reso= mysql_qry($qry);
      while ( $reso && $h= mysql_fetch_object($reso) ) {
        $hf= sql2stamp($h->fromday); $hu= sql2stamp($h->untilday);
        $od_ts= $hf ? $hf : $o->fromday;  $od= date('j.n',$od_ts);
        $do_ts= $hu ? $hu : $o->untilday; $do= date('j.n',$do_ts);
        $vek= ds_vek($h->narozeni,$o->fromday);
        $narozeni= $h->narozeni ? sql_date1($h->narozeni): '';
        $strava= $h->strava ? $h->strava : $o->board;
        // p�ips�n� ��dku
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
        // polo�ky hosta
        $pol= (object)array();
        $pol->test= "{$h->strava} : {$o->board} - $strava = {$ds_strava[$strava]}";
                                                display(wu("{$h->jmeno} {$h->prijmeni} $pol->test}"));
        $noci= round(($do_ts-$od_ts)/(60*60*24));
        $pol->vek= $vek;
        $pol->noci= $noci;
        $pol->pokoj= $h->pokoj;
        // ubytov�n�
        $luzko= trim($ds_luzko[$h->luzko]);     // L|P|B
//                                                                 debug($ds_luzko,"ds_luzko {$luzko_pokoje[$h->pokoj]}",(object)array('win1250'=>1));
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
          $pol->ubyt_C= $noci;
          $pol->ubyt_S= $noci;
        }
        else {
          $pol->ubyt_P= $noci;
        }
        $hoste[]= (object)array('host'=>$host,'cena'=>$pol);
      }
      $x->rodiny[]= $hoste;
      $x->chyby[]= $err;
    }
  }
  else
    fce_error(wu("ne�pln� objedn�vka $order"));
//                                                                 debug($ds_cena,'ds_cena',(object)array('win1250'=>1));
//                                                                 debug($x,'faktura',(object)array('win1250'=>1));
  return $x;
}
# ================================================================================================== FAKTURA OBECN�
# -------------------------------------------------------------------------------------------------- ds_rozpis_faktura
# definice faktury
# typ = Z�lohov� | ''
# zaloha = 0..100  -- pokud je 100 negeneruje se ��dek Z�loha ...
# data z�lohov� faktury
# platce= [nazev,adresa,telefon,ic]
# polozky= [[nazev,cena,dph,pocet,sleva]...]
# }
function ds_rozpis_faktura($listr,$listf,$typ,$order,$x,$polozky,$platce,$zaloha=100,$pata,$zaloha,&$suma) {  trace('','win1250');
  list($ic,$dic,$adresa,$akce,$obdobi)= $platce;
//                                                                 debug($platce,'platce',(object)array('win1250'=>1));
  $vystaveno= Excel5_date(mktime());
  //list($obdobi)= $x->objednavka;
  $ymca_setkani= "YMCA Setk�n�, ob�ansk� sdru�en�{}Talichova 53, 62300 Brno{}".
                 "Zaregistrovan� MV �R 25.4.2001{}pod �.j. VS/1-1/46 887/01-R{}".
                 "I�: 26531135  DI�: CZ26531135";
  $dum_setkani=  "Doln� Albe�ice 1, 542 26 Horn� Mar�ov{}".
                 "telefon: 499 874 152, 736 537 122{}dum@setkani.org{}www.setkani.alberice.org";
  // ------------------------------------------------------------------- vytvo�en� listu s rozpisem
  // pojmenovan� ��dky (P,Q,R,S)
  $P= 10;               // v��et polo�ek
  $Q= 24;               // posledn� polo�ka
  $D= 26;               // rozpis podle druh�
  $S= 32;               // posledn� ��dek
  // parametrizace
  $c_okraj= "ff6495ed";    $S1= $S+1;
  $xls= <<<__XLS
  \n\n|sheet $listr;B2:N$S;P;page
    |columns A=3,B=0.6,C=16,D=3,E=22,F=6,G=16,H=10,I=4,J=6,K=6,L=16,M=0,N=1,O=3
    |rows 1=18,2:44=15,5=30,7=45,9=30,10:30=20,$S=30
    |A1:O$S1 bcolor=$c_okraj |B2:N$S bcolor=ffffffff |//B2:N$S border=h

    |image img/YMCA.png,80,C2,10,0
    |D2 D�m setk�n� :: bold size=14
    |D3 $dum_setkani
    |D3:H5 merge italic top wrap
    |B7 Rozpis ceny za pobyt v Dom� setk�n� ve dnech $obdobi ::bold size=16|B7:L7 merge
__XLS;
  $n= $P-1;
  $xls.= <<<__XLS
    |C$n Polo�ka              ::wrap middle       |C$n:E$n merge bold border=h
    |F$n Po�et                ::wrap middle right |F$n:F$n       bold border=h
    |G$n Druh                 ::wrap middle right |G$n:G$n       bold border=h
    |H$n Cena polo�ky s DPH   ::wrap middle right |H$n:I$n merge bold border=h
    |J$n Sleva %              ::wrap middle right |J$n:J$n       bold border=h
    |K$n Sazba DPH            ::wrap middle right |K$n:K$n       bold border=h
    |L$n Cena s DPH           ::wrap middle right |L$n:L$n       bold border=h
__XLS;
  // ��dky $P-$Q -- polo�ky
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
  // ��dky D... -- rozpis podle druh�
  $n= $D;
  if ( count($druhy) )
  foreach($druhy as $druh=>$dph) {
    $xls.= <<<__XLS
      |H$n $druh::right                |H$n:J$n merge right
      |K$n $dph                        ::proc border=h right
      |L$n =SUMIF(G$P:G$Q;H$n;L$P:L$Q) ::kc   border=h right
__XLS;
    $n++;
  }
  // ------------------------------------------------------------------- vytvo�en� listu s fakturou
  // pojmenovan� ��dky (P,Q,R,S)
  $P= 22;               // v��et polo�ek
  $Q= 34;               // posledn� polo�ka
  $R= 31;               // vy�izuje
  $S= 37;               // posledn� ��dek
  // parametrizace
  $L7_ic=  $ic  ? "L7 I� $ic"   : '';
  $M7_dic= $dic ? "M7 DI� $dic" : '';
  $c_okraj= "ff6495ed";
  $S1= $S+1;
  $xls.= <<<__XLS
  \n\n|sheet $listf;B2:N$S;P;page
    |columns A=3,B=0.6,C=16,D=3,E=22,F=6,G=1,H=10,I=4,J=6,K=6,L:M=16,N=1,O=3
    |rows 1=18,2:44=15,5=30,6=45,9=96,11=35,19=30,20:36=19,21=30,37=30,38:41=19,$S=30
    |A1:O$S1 bcolor=$c_okraj |B2:N$S bcolor=ffffffff |//B2:N$S border=h

    |image img/YMCA.png,80,C2,10,0
    |D2 D�m setk�n� :: bold size=14
    |D3 $dum_setkani
    |D3:H5 merge italic top wrap

    |J4 =CONCATENATE("$typ ",TEXT(E16,"0"),"/",TEXT(3000+YEAR(M15),"0")) :: bold size=16
    |J4:M5 merge right

    |C7 Dodavatel ::bold
    |C9 $ymca_setkani
    |C9:F9 merge top wrap

    |I7 Odb�ratel ::bold         |$L7_ic        |$M7_dic
    |I8:M10 border=h
    |J9 $adresa  |J9:M9 merge middle wrap size=14

    |C13 Pen�n� �stav           |E13 Raiffeisenbank, a.s.
    |C14 ��slo ��tu       ::bold |E14 514048044/5500 ::bold
    |C15 Konstantn� symbol       |E15 558 ::left
    |C16 Variabiln� symbol       |E16 <��slo faktury> ::left bcolor=ffffffaa
    |C17 Specifick� symbol       |E17 $akce ::left bcolor=ffffffaa

    |L12 Objedn�vka ��slo ::bold |M12 $order  ::size=14 bold
    |L13 Dodac� a platebn� podm�nky: s dan�
    |L14 Datum vystaven�         |M14 $vystaveno ::date bcolor=ffffffaa
    |L15 Datum z��tov�n�         |M15 =M14       ::date bcolor=ffffffaa
    |L16 Datum splatnosti ::bold |M16 =M14+14    ::date bcolor=ffffffaa bold
    
    |C19 Za pobyt v Dom� setk�n� ve dnech $obdobi V�m fakturujeme: |C19:M19 merge
__XLS;
  $n= $P-1;
  $xls.= <<<__XLS
    |C$n Polo�ka              ::wrap middle       |C$n:G$n merge bold border=h
    |H$n Cena s DPH           ::wrap middle right |H$n:J$n merge bold border=h
    |K$n Sazba DPH            ::wrap middle right |K$n:K$n       bold border=h
    |L$n DPH                  ::wrap middle right |L$n:L$n       bold border=h
    |M$n Cena bez DPH         ::wrap middle right |M$n:M$n       bold border=h
__XLS;
  // ��dky $P-$Q -- polo�ky
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
  // celkov� cena
  $d= $n;
  $n++;
  $suma= "'$listf'!L$n";
  $xls.= <<<__XLS
    |H$n Celkov� cena s DPH ::middle bold |H$n:K$n merge right
    |L$n =SUM(L$P:M$d)      ::kc          |L$n:M$n merge border=h
__XLS;
  $c= $n;
  $n++;
  $xls.= <<<__XLS
    |H$n Zaplaceno z�lohou  ::middle bold |H$n:K$n merge right
    |L$n $zaloha            ::kc          |L$n:M$n merge border=h
__XLS;
  $z= $n;
  $n++;
  $xls.= <<<__XLS
    |H$n Zb�v� k zaplacen�  ::middle bold |H$n:K$n merge right
    |L$n =L$c-L$z           ::kc          |L$n:M$n merge border=h
__XLS;
  // ��dky R,S (viz v��e) -- spodek faktury
  $n= $R+1;
  $xls.= <<<__XLS
    |C$R vy�izuje ::bold
    |C$n Josef N�prstek, spr�vce Domu setk�n�
    |C$S:M$S border=h,,,
    |C$S $pata | C$S:M$S merge middle center wrap
__XLS;
  return $xls;
}
# -------------------------------------------------------------------------------------------------- ds_faktura
# definice faktury
# typ = Z�lohov� | ''
# zaloha = 0..100  -- pokud je 100 negeneruje se ��dek Z�loha ...
# data z�lohov� faktury
# platce= [nazev,adresa,telefon,ic]
# polozky= [[nazev,cena,dph,pocet,sleva]...]
# }
function ds_faktura($list,$typ,$order,$polozky,$platce,$zaloha=100,$pata='') {  #trace('','win1250');
  list($ic,$dic,$adresa,$akce,$obdobi)= $platce;
  $vystaveno= Excel5_date(mktime());
  $ymca_setkani= "YMCA Setk�n�, ob�ansk� sdru�en�{}Talichova 53, 62300 Brno{}".
                 "Zaregistrovan� MV �R 25.4.2001{}pod �.j. VS/1-1/46 887/01-R{}".
                 "I�: 26531135  DI�: CZ26531135";
  $dum_setkani=  "Doln� Albe�ice 1, 542 26 Horn� Mar�ov{}".
                 "telefon: 499 874 152, 736 537 122{}dum@setkani.org{}www.setkani.alberice.org";
  // pojmenovan� ��dky (P,Q,R,S)
  $P= 22;               // v��et polo�ek
  $Q= 36;               // posledn� polo�ka
  $R= 39;               // rozpis DPH
  $S= 45;               // posledn� ��dek
  // parametrizace
  $L7_ic= $ic ? "L7 I� $ic" : '';
  $M7_dic= $dic ? "M7 DI� $dic" : '';
  $c_okraj= "ff6495ed";
  // vytvo�en� listu s fakturou
  $xls= <<<__XLS
    |sheet $list;B2:N$S;P;page
    |columns A=3,B=0.6,C=16,D=3,E=22,F=6,G=1,H=10,I=4,J=6,K=6,L:M=16,N=1,O=3
    |rows 1=18,2:44=15,5=30,6=45,9=96,11=35,19=30,21=30,22:38=19,39=30,40:43=19,$S=30
    |A1:O46 bcolor=$c_okraj |B2:N$S bcolor=ffffffff |//B2:N$S border=h

    |image img/YMCA.png,80,C2,10,0
    |D2 D�m setk�n� :: bold size=14
    |D3 $dum_setkani
    |D3:H5 merge italic top wrap

    |J4 =CONCATENATE("$typ ",TEXT(3000+YEAR(M15),"0"),"/",TEXT(E16,"0")) :: bold size=16
    |J4:M5 merge right

    |C7 Dodavatel ::bold
    |C9 $ymca_setkani
    |C9:F9 merge top wrap

    |I7 Odb�ratel ::bold         |$L7_ic  |$M7_dic
    |I8:M10 border=h
    |J9 $adresa  |J9:M9 merge middle wrap size=14

    |C13 Pen�n� �stav           |E13 Raiffeisenbank, a.s.
    |C14 ��slo ��tu       ::bold |E14 514048044/5500 ::bold
    |C15 Konstantn� symbol       |E15 558 ::left
    |C16 Variabiln� symbol       |E16 <��slo faktury> ::left bcolor=ffffffaa
    |C17 Specifick� symbol       |E17 $akce ::left bcolor=ffffffaa

    |L12 Objedn�vka ��slo ::bold |M12 $order  ::size=14 bold
    |L13 Dodac� a platebn� podm�nky: s dan�
    |L14 Forma �hrady            |M14 p�evodem
    |L14 Datum vystaven�         |M14 $vystaveno ::date bcolor=ffffffaa
    |L15 Datum z��tov�n�         |M15 =M14       ::date bcolor=ffffffaa
    |L16 Datum splatnosti ::bold |M16 =M14+14    ::date bcolor=ffffffaa bold
    |C19 Fakturujeme v�m z�lohu na pobyt ve dnech $obdobi: |C19:M19 merge
__XLS;
  $n= $P-1;
  $xls.= <<<__XLS
    |C$n Polo�ka              ::wrap middle       |C$n:E$n merge bold border=h
    |F$n Po�et                ::wrap middle right |F$n:G$n merge bold border=h
    |H$n Cena polo�ky v�. DPH ::wrap middle right |H$n:I$n merge bold border=h
    |J$n Sleva %              ::wrap middle right |J$n:J$n       bold border=h
    |K$n Sazba DPH            ::wrap middle right |K$n:K$n       bold border=h
    |L$n DPH                  ::wrap middle right |L$n:L$n       bold border=h
    |M$n Cena bez DPH         ::wrap middle right |M$n:M$n       bold border=h
__XLS;
  // ��dky $P-$Q -- polo�ky
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
  // celkov� cena
  $n= $Q+1;
  $xls.= <<<__XLS
    |H$n Celkov� cena s DPH ::middle bold |H$n:K$n merge right
    |L$n =SUM(L$P:M$Q)      ::middle kc   |L$n:M$n merge border=h
__XLS;
  if ( $zaloha<100 ) {
    $n++;
    $xls.= <<<__XLS
      |H$n Z�loha $zaloha%             ::middle bold |H$n:K$n merge right
      |L$n =SUM(L$P:M$Q)*($zaloha/100) ::middle kc   |L$n:M$n merge border=h
__XLS;
  }
  // ��dky R... -- rozpis DPH
  $n= $R+1;
  $xls.= <<<__XLS
    |K$R Rozpis DPH ::bold
    |K$n Sazba::middle bold border=h right
    |L$n Z�klad  ::middle bold border=h right
    |M$n Da�     ::middle bold border=h right
__XLS;
  sort($sazby_dph);
  foreach($sazby_dph as $sazba) {
    $n++;
    $xls.= <<<__XLS
      |K$n $sazba                                    ::proc border=h right
      |L$n =SUMIF(K$P:K$Q;K$n;M$P:M$Q)*($zaloha/100) ::kc   border=h right
      |M$n =SUMIF(K$P:K$Q;K$n;L$P:L$Q)*($zaloha/100) ::kc   border=h right
__XLS;
  }
  // ��dky R,S (viz v��e) -- spodek faktury
  $n= $R+1;
  $xls.= <<<__XLS
    |C$R vy�izuje ::bold
    |C$n Josef N�prstek, spr�vce Domu setk�n�
    |C$S:M$S border=h,,,
    |C$S $pata | C$S:M$S merge middle center wrap
__XLS;
  return $xls;
}
# -------------------------------------------------------------------------------------------------- ds_cenik
# na�ten� cen�ku pro dan� rok
function ds_cenik($rok) {  #trace('','win1250');
  global $ds_cena;
  $ds_cena= array();
  ezer_connect('setkani');
  $qry2= "SELECT * FROM ds_cena WHERE rok=$rok";
  $res2= mysql_qry($qry2);
  while ( $res2 && $c= mysql_fetch_object($res2) ) {
    $ds_cena[$c->typ]= $c;
  }
//                                                                debug($cena,'cena',(object)array('win1250'=>1));
}
# -------------------------------------------------------------------------------------------------- ds_c
# polo�ka faktury
# id,pocet => n�zev,cena,dph%,pocet
function ds_c ($id,$pocet,$sleva='') {
  global $ds_cena;
  $c= array($ds_cena[$id]->polozka,$ds_cena[$id]->cena,$ds_cena[$id]->dph/100,$pocet,trim($ds_cena[$id]->druh));
  if ( $sleva ) $c[]= $sleva;
  return $c;
}
# -------------------------------------------------------------------------------------------------- ds_vek
# zji�t�n� v�ku v dob� zah�jen� akce
function ds_vek($narozeni,$fromday) {
  if ( $narozeni=='0000-00-00' )
    $vek= -1;
  else {
    $vek= $fromday-sql2stamp($narozeni);
    $vek= round($vek/(60*60*24*365),1);
  }
  return $vek;
}
?>
