<?php

# -------------------------------------------------------------------------- tisk2 sestava noclehy
# generování sestavy "Noclehy, program" - dedikovaná funkce pro ASC
# sloupce pocet1..pocet5 se čtou přímo z tabulky pobyt
function tisk2_sestava_noclehy($akce,$par,$title,$vypis,$export=false) { trace();
  global $tisk_hnizdo;
  $tit= isset($par->tit) ? $par->tit : '';
  $fld= $par->fld;
  $cnd= $par->cnd ? "($par->cnd)" : '1';
  if ( $tisk_hnizdo ) $cnd.= " AND hnizdo=$tisk_hnizdo ";
  $ord= isset($par->ord) ? $par->ord : "r_nazev";
  $par_note= $par->note??'';
  $n= 0;
  // dekódování parametrů
  $tits= explode(',',$tit);
  $flds= explode(',',$fld);
  // získání dat
  $clmn= array();
  $r_fld= "id_rodina,nazev,ulice,psc,obec,stat,note,emaily,telefony,spz";
  $qry= "
    SELECT p.id_pobyt, p.zadost, p.poznamka, p.funkce,
      p.pocet1, p.pocet2, p.pocet3, p.pocet4, p.pocet5,
      IFNULL(r2.nazev,r1.nazev) AS r_nazev,
      IF(o.adresa,o.obec,IFNULL(r2.obec,r1.obec)) AS obec,
      GROUP_CONCAT(DISTINCT o.prijmeni ORDER BY o.prijmeni SEPARATOR ', ') AS _prijmeni,
      GROUP_CONCAT(o.jmeno ORDER BY o.prijmeni,o.jmeno SEPARATOR ', ') AS _jmena,
      COUNT(o.id_osoba) AS _pocet
    FROM pobyt AS p
      JOIN spolu AS s USING(id_pobyt)
      JOIN osoba AS o ON o.id_osoba=s.id_osoba AND o.deleted=''
      -- r1=rodina, kde je dítětem
      LEFT JOIN ( SELECT id_osoba,role,$r_fld
        FROM tvori JOIN rodina USING(id_rodina))
        AS r1 ON r1.id_osoba=o.id_osoba AND r1.role NOT IN ('a','b')
      -- r2=rodina, kde je rodičem
      LEFT JOIN ( SELECT id_osoba,role,$r_fld
        FROM tvori JOIN rodina USING(id_rodina))
        AS r2 ON r2.id_osoba=o.id_osoba AND r2.role IN ('a','b')
    WHERE p.id_akce=$akce AND $cnd AND p.funkce NOT IN (9,10,13,14,15)
      GROUP BY p.id_pobyt
      ORDER BY $ord";
  $res= pdo_qry($qry);
  while ( $res && ($x= pdo_fetch_object($res)) ) {
    $n++;
    $clmn[$n]= array();
    foreach($flds as $f) {
      $c= '';
      switch ($f) {
      case '^id_pobyt': $c= $x->id_pobyt; break;
      case 'prijmeni':  $c= $x->r_nazev ?: $x->_prijmeni; break;
      case 'jmena':     $c= $x->_jmena; break;
      case 'obec':      $c= $x->obec; break;
      case '_pocet':    $c= $x->_pocet; break;
      case 'pocet1':    $c= $x->pocet1; break;
      case 'pocet2':    $c= $x->pocet2; break;
      case 'pocet3':    $c= $x->pocet3; break;
      case 'pocet4':    $c= $x->pocet4; break;
      case 'pocet5':    $c= $x->pocet5; break;
      case 'zadost':    $c= $x->zadost ? 'x' : ''; break;
      case 'poznamka':  $c= $x->poznamka; break;
      default:          $c= $x->$f; break;
      }
      $clmn[$n][$f]= $c;
    }
  }
  $res= tisk2_table($tits,$flds,$clmn,$export);
  if ($par_note) $res->html= "$par_note {$res->html}";
  return $res;
}
