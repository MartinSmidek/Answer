<?php # (c) 2007-2009 Martin Smidek <martin@smidek.eu>
# -------------------------------------------------------------------------------------------------- lide_spolu
# přehled
function lide_spolu($rok) { trace();
  $html= "<div class='CSection CMenu'>";
  $html.= "<h3 class='CTitle'>Staré skupinky účastníků kurzu $rok</h3><dl>";
  // letošní účastníci
  $letos= array();
  $qry= "SELECT cislo,akce,skupina,jmeno,mesto,nazev,year(datum_od) as rok
          FROM ms_kurs
          JOIN ms_pary USING (cislo)
          JOIN ms_druhakce USING(akce)
          WHERE druh=1
          HAVING rok=$rok
          ORDER BY jmeno";
  $res= mysql_qry($qry);
  while ( $res && ($u= mysql_fetch_object($res)) ) {
    $letos[$u->cislo]= $u;
  }
  $letosni= implode(',',array_keys($letos));
  // doplnění nejednoznačných příjmení o město
  $odkud= array();
  $qry= "
        SELECT jmeno,count(jmeno) as xx, group_concat(cislo) as cisla, group_concat(mesto) as mesta
        FROM ms_kurs
        JOIN ms_pary USING (cislo)
        JOIN ms_druhakce USING(akce)
        WHERE druh=1 and year(datum_od)=2009 GROUP BY jmeno HAVING xx>1
        ORDER BY jmeno
    ";
  $res= mysql_qry($qry);
  while ( $res && ($d= mysql_fetch_object($res)) ) {
    $mesta= explode(',',$d->mesta);
    $cisla= explode(',',$d->cisla);
    foreach($cisla as $i=>$cislo) {
      $odkud[$cislo]= " ({$mesta[$i]})";
    }
  }
//                                                         debug($odkud);
  // tisk
  foreach ($letos as $par=>$info) {
    $ze= isset($odkud[$par]) ? $odkud[$par] : '';
    $ucastnik= "{$info->jmeno} $ze";
    // minulé účasto
    $qry= "
            SELECT akce,skupina,nazev,year(datum_od) as rok
            FROM ms_kurs
            JOIN ms_druhakce USING(akce)
            WHERE druh=1 and cislo={$par} and skupina>0
            ORDER BY datum_od DESC
        ";
    $res= mysql_qry($qry);
    $ucasti= '';
    while ( $res && ($r= mysql_fetch_object($res)) ) {
      // minulé skupinky
      $qry_s= "
            SELECT akce, cislo, skupina,funkce,jmeno,mesto,nazev,year(datum_od)
            FROM ms_kurs
            JOIN ms_pary USING (cislo)
            JOIN ms_druhakce USING(akce)
            WHERE akce={$r->akce} and skupina={$r->skupina} and find_in_set(cislo,'$letosni')
            ORDER BY datum_od DESC
        ";
      $res_s= mysql_qry($qry_s);
      $spolu= '';
      while ( $res_s && ($s= mysql_fetch_object($res_s)) ) if ( $s->cislo!=$par ) {
        $ze= isset($odkud[$s->cislo]) ? $odkud[$s->cislo] : '';
        $spolu.= "{$s->jmeno} $ze ";
      }
      if ( $spolu ) {
        $ucasti.= "<u>{$r->rok}</u>: $spolu";
      }
    }
    if ( $ucasti )
      $html.= "<dt><b>$ucastnik</b></dt><dd>$ucasti</dd>";
  }
  $html.= "</dl></div>";
  return $html;
}
# -------------------------------------------------------------------------------------------------- lide_kurs
# přehled
function lide_kurs($rok) { trace();
  $html= "<h3>Účastníci kurzu $rok</h3>";
  // letošní účastníci
  $qry= "SELECT cislo,akce,skupina,jmeno,mesto,nazev,year(datum_od) as rok
          FROM ms_kurs
          JOIN ms_pary USING (cislo)
          JOIN ms_druhakce USING(akce)
          WHERE druh=1
          HAVING rok=$rok
          ORDER BY jmeno";
  $res= mysql_qry($qry);
  while ( $res && ($u= mysql_fetch_object($res)) ) {
    $html.= "<br>{$u->jmeno} {$s->mesto}  ({$u->cislo})";
  }
  return $html;
}
?>
