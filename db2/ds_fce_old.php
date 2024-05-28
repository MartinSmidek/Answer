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
