<?php

# -------------------------------------------------------------------------------- akce2 auto_jmena3
# SELECT autocomplete - výběr z pečounů
# $par->cond může obsahovat dodatečnou podmínku např. 'funkce=99' pro zúžení na pečouny
function akce2_auto_jmena3($patt,$par) {  #trace();
  $a= array();
  $limit= 20;
  $n= 0;
  if ( $par->patt!='whole' ) {
    $is= strpos($patt,' ');
    $patt= $is ? substr($patt,0,$is) : $patt;
  }
  $AND= $par->cond ? "AND {$par->cond}" : '';
  // páry
  $qry= "SELECT prijmeni, jmeno, osoba.id_osoba AS _key
         FROM osoba
         JOIN spolu USING(id_osoba)
         JOIN pobyt USING(id_pobyt)
         WHERE deleted='' AND concat(trim(prijmeni),' ',jmeno) LIKE '$patt%' AND prijmeni!='' $AND
         GROUP BY id_osoba
         ORDER BY prijmeni,jmeno LIMIT $limit";
  $res= pdo_qry($qry);
  while ( $res && $t= pdo_fetch_object($res) ) {
    if ( ++$n==$limit ) break;
    $key= $t->_key;
    $a[$key]= "{$t->prijmeni} {$t->jmeno}";
  }
  // obecné položky
  if ( !$n )
    $a[0]= "... žádné příjmení nezačíná '$patt'";
  elseif ( $n==$limit )
    $a[-999999]= "... a další";
//                                                                 debug($a,$qry);
  return $a;
}
# ------------------------------------------------------------------------------- akce2 auto_jmena3L
# formátování autocomplete
function akce2_auto_jmena3L($id_osoba) {  #trace();
  $pecouni= array();
  // páry
  $qry= "SELECT id_osoba, prijmeni, jmeno, obec, email, telefon, YEAR(narozeni) AS rok
         FROM osoba AS o
         WHERE id_osoba='$id_osoba' ";
  $res= pdo_qry($qry);
  while ( $res && $p= pdo_fetch_object($res) ) {
    $nazev= "{$p->prijmeni} {$p->jmeno} / {$p->rok}, {$p->obec}, {$p->email}, {$p->telefon}";
    $pecouni[]= (object)array('nazev'=>$nazev,'id'=>$p->id_osoba);
  }
//                                                                 debug($pecouni,$id_akce);
  return $pecouni;
}
