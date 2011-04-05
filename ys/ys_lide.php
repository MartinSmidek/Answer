<?php # (c) 2007-2009 Martin Smidek <martin@smidek.eu>
# ================================================================================================== LIDÉ-UNIFY
# -------------------------------------------------------------------------------------------------- lide_survey
# přehled zpracování
function lide_survey() { trace();
  $html= "<table class='stat'>";
$tabs= array('ms_akce','ms_pary','ms_deti','ms_kurs','ms_kursdeti');
  $sources= array('L','M','Y');
  // napisy
  $html.= "<tr><th></th>";
  foreach ($tabs as $tab) $html.= "<th class='r'>$tab</th>";
  $html.= "</tr>";
  // tělo
  foreach ($sources as $source) {
    $html.= "<tr><th class='r'>$source</th>";
    foreach ($tabs as $tab) {
      $n= select("count(*)",$tab,"source='$source'");
      $html.= "<td class='r'>$n</td>";
    }
  }
  $html.= "<tr><th class='r'>du_</th>";
  foreach ($tabs as $tab) {
    $ab= substr($tab,3);
    $n= select("count(*)","du_$ab",1);
    $html.= "<td class='r'>$n</td>";
  }
  $html.= "</table>";
  return $html;
}
# -------------------------------------------------------------------------------------------------- lide_duplo
# redukce duplicit v M a L záznamech
#      0 -- nic
#      1 -- jméno a příjmení muže
#      2 -- bydliště
#      3 -- telefon
#      4 -- email
#      5 -- karta muže
#      6 -- karta ženy
#      7 -- poznámka
#      8 -- datum svatby
#      9 -- spz
#    512 -- akce
function lide_duplo($par) { trace();
  $html= "";
  $n= 0;
  switch ( $par->fce ) {
  // ========================================================================== MS_DRUHAKCE, DU_AKCE
  // inicializace tabulky DU_AKCE => 0
  case 'akce_init':
    $qry= "TRUNCATE TABLE du_akce";
    $res= mysql_qry($qry);
    $qry= "UPDATE ms_akce SET id_duakce=0";
    $res= mysql_qry($qry);
    $qry= "UPDATE du_kurs SET id_duakce=0";
    $res= mysql_qry($qry);
    $qry= "DELETE FROM ms_akce WHERE id_akce IN (460,195,208,153,357)";
    $res= mysql_qry($qry);
    $qry= "UPDATE ms_akce SET poradatel=1 WHERE id_akce IN (27,34,41,43,62,63,77,79,82,96,99,111,112,
      117,118,131,136,156,168,176,187,191,193,194,206,222,237,244,253,272,273,287,289,292,306,309,
      321,322,327,328,360,387,424,432,462,96)";
    $res= mysql_qry($qry);
    $html.= "byla inicializována tabulka zjištěných duplicit akcí, smazány prázdné akce,
      poznačeny neakce (příležitostné seznamy)";
    break;
  // ----------------------------- seskupení stejných názvů a zahájení => 10
  // seskupení stejných akcí pod du_akce
  case 'akce_nazev':
    $qry= "SELECT GROUP_CONCAT(id_akce) AS _ids, /*GROUP_CONCAT(source ORDER BY source) AS _lm,*/
           count(*) AS _pocet FROM ms_akce
           GROUP BY nazev,datum_od /*HAVING _pocet>1 AND _lm='L,M'*/
           ORDER BY datum_od, nazev DESC";
    $res= mysql_qry($qry);
    while ( $res && ($p= mysql_fetch_object($res)) ) {
      $ids= $p->_ids;
      $qryd= "INSERT INTO du_akce (typ) VALUES (10)";
      $resd= mysql_qry($qryd);
      $id_duakce= mysql_insert_id();
      $qryu= "UPDATE ms_akce SET id_duakce=$id_duakce WHERE id_akce IN ($ids)";
      $resu= mysql_qry($qryu);
      $qryu= "UPDATE ms_kurs SET id_duakce=$id_duakce WHERE id_akce IN ($ids)";
      $resu= mysql_qry($qryu);
      $qryu= "UPDATE ms_kursdeti SET id_duakce=$id_duakce WHERE id_akce IN ($ids)";
      $resu= mysql_qry($qryu);
      $n++;
    }
    $html.= "bylo nalezeno $n záznamů M/L se shodou názvů";
    break;
  // ----------------------------- naplnění du_akce
  case 'akce_new':
    $qryp= "SELECT m.id_duakce,m.datum_od,m.datum_do,m.nazev,m.ciselnik_rok,m.ciselnik_akce,
            CONCAT (m.source,',',m.akce,',',m.misto) AS _note
            FROM du_akce AS d JOIN ms_akce AS m USING(id_duakce)";
    $resp= mysql_qry($qryp);
    while ( $resp && $p= mysql_fetch_object($resp) ) {
      $qryu= "UPDATE du_akce SET nazev='{$p->nazev}',note='{$p->_note}',
                datum_od='{$p->datum_od}',datum_do='{$p->datum_do}',
                ciselnik_akce='{$p->ciselnik_akce}',ciselnik_rok='{$p->ciselnik_rok}'
              WHERE id_duakce={$p->id_duakce}";
      $resu= mysql_qry($qryu);
    }
    break;
// -----------------------------   // seskupení stejných údajů => 12345679     -- zbytečné
//   case 'akce_udaje':
//     $matches= array(
//     //"10,název,nazev:datum_od",
//       "02,údaje,ciselnik_akce:ciselnik_rok:poradatel:datum_do:misto"
//     );
//     $qryd= "SELECT id_duakce FROM du_akce WHERE typ=10 ORDER BY id_duakce /*LIMIT 1*/";
//     $resd= mysql_qry($qryd);
//     while ( $resd && ($d= mysql_fetch_object($resd)) ) {
//       $id_duakce= $d->id_duakce;
//       foreach ($matches as $match) {
//         list($incr,$name,$flds)= explode(',',$match);
//         $ask= "CONCAT(".str_replace(":",",':',",$flds).")";
//         $qryp= "SELECT $ask AS _ask,id_akce FROM ms_akce WHERE id_duakce=$id_duakce LIMIT 1";
//         $resp= mysql_qry($qryp);
//         if ( $resp && $p= mysql_fetch_object($resp) ) {
//           $id_akce= $p->id_akce;
//           $ans= mysql_real_escape_string($p->_ask);
//           $qryu= "UPDATE du_akce JOIN ms_akce USING(id_duakce)
//                   SET typ=typ+$incr WHERE id_duakce=$id_duakce AND id_akce!=$id_akce AND $ask='$ans'";
//           $resu= mysql_qry($qryu);
//           $n+= mysql_affected_rows();
//         }
//       }
//     }
//     $html.= "bylo doplněno $n shod ve sledovaných položkách";
//     break;
  // ============================================================================== MS_PARY, DU_PARY
  // inicializace tabulky DU_PARY => 0
  case 'pary_init':
    // mazání chyb
    mysql_qry("DELETE FROM ms_pary WHERE id_pary IN (1510,1344,1435,1233,1411)");
    mysql_qry("DELETE FROM ms_deti WHERE id_pary IN (1510,1344,1435,1233,1411)");
    mysql_qry("DELETE FROM ms_kurs WHERE id_pary IN (1510,1344,1435,1233,1411)");
    mysql_qry("DELETE FROM ms_kursdeti WHERE id_pary IN (1510,1344,1435,1233,1411)");
    // mazání vložených
    $qry= "TRUNCATE TABLE du_pary";             $res= mysql_qry($qry);
    $qry= "UPDATE ms_pary SET id_dupary=0";     $res= mysql_qry($qry);
    $qry= "UPDATE du_kurs SET id_dupary=0";     $res= mysql_qry($qry);
    $qry= "DELETE FROM osoba WHERE origin='p'"; $res= mysql_qry($qry);
    $qry= "DELETE FROM tvori WHERE role='m' ";  $res= mysql_qry($qry);
    $html.= "byla inicializována tabulka zjištěných duplicit párů";
    break;
  // ----------------------------- seskupení stejných jmen => 100000000  a tip nejnovější informace
  case 'pary_jmena':
    $qry= "SELECT GROUP_CONCAT(msp.id_pary) AS _ids, count(*) AS _pocet
           FROM ms_pary AS msp
           GROUP BY jmeno,prijmeni_m,jmeno_m,prijmeni_z,jmeno_z";
    $res= mysql_qry($qry);
    while ( $res && ($p= mysql_fetch_object($res)) ) {
      $ids= $p->_ids;
      $typ= $p->_pocet>1 ? 100000000 : 123456789;
      if ( $p->_pocet>0 ) {
        $id_L= 0;
        // nalezneme nejčerstvější účast na skutečné akci (poradatel=0) nebo na jakékoliv "akci"
        $qryL= "SELECT id_pary
                FROM ms_kurs JOIN ms_pary USING (id_pary)
                JOIN ms_akce USING(id_akce) WHERE id_pary IN ($ids)
                ORDER BY poradatel ASC,datum_od DESC LIMIT 1";
        $resL= mysql_qry($qryL);
        if ( $resL && ($L= mysql_fetch_object($resL)) ) {
          $id_L= $L->id_pary;
        }
        $qryd= "INSERT INTO du_pary (typ,pocet,id_pary) VALUES ($typ,$p->_pocet,$id_L)";
        $resd= mysql_qry($qryd);
        $id_dupary= mysql_insert_id();
        $qryu= "UPDATE ms_pary SET id_dupary=$id_dupary WHERE id_pary IN ($ids)";
        $resu= mysql_qry($qryu);
        $qryu= "UPDATE ms_deti SET id_dupary=$id_dupary WHERE id_pary IN ($ids)";
        $resu= mysql_qry($qryu);
        $n++;
      }
    }
    $html.= "bylo nalezeno $n záznamů M/L se shodou jmen";
    break;
  // ----------------------------- seskupení stejných údajů => 12345679
  case 'pary_udaje':
    $matches= array(
    //"100000000,jména,jmeno:prijmeni_m:jmeno_m:prijmeni_z:jmeno_z",
      "020000000,bydliště,adresa:psc:mesto",
      "003000000,telefon,telefon",
      "000400000,email,email",
      "000050000,karta muže,rodcislo_m:cirkev_m:vzdelani_m:zamest_m:zajmy_m:jazyk_m:aktivita_m:cislo_m:clen_m",
      "000006000,karta ženy,rodcislo_z:cirkev_z:vzdelani_z:zamest_z:zajmy_z:jazyk_z:aktivita_z:cislo_z:clen_z",
      "000000700,poznámka,poznamka",
      "000000080,datum svatby,datsvatba",
      "000000009,spz,spz");
    $qryd= "SELECT id_dupary FROM du_pary WHERE typ=100000000 ORDER BY id_dupary /*LIMIT 1*/";
    $resd= mysql_qry($qryd);
    while ( $resd && ($d= mysql_fetch_object($resd)) ) {
      $id_dupary= $d->id_dupary;
      foreach ($matches as $match) {
        list($incr,$name,$flds)= explode(',',$match);
        $ask= "CONCAT(".str_replace(":",",':',",$flds).")";
        $qryp= "SELECT $ask AS _ask,id_pary FROM ms_pary WHERE id_dupary=$id_dupary LIMIT 1";
        $resp= mysql_qry($qryp);
        if ( $resp && $p= mysql_fetch_object($resp) ) {
          $id_pary= $p->id_pary;
          $ans= mysql_real_escape_string($p->_ask);
          $qryu= "UPDATE du_pary JOIN ms_pary USING(id_dupary)
                  SET typ=typ+$incr
                  WHERE id_dupary=$id_dupary AND ms_pary.id_pary!=$id_pary AND $ask='$ans'";
          $resu= mysql_qry($qryu);
          $n+= mysql_affected_rows();
        }
      }
    }
    $html.= "bylo doplněno $n shod ve sledovaných položkách";
    $n= select("count(*)","du_pary","typ!=123456789");
    $html.= "<br>bylo zjištěno $n rozdílů v duplicitách účastí";
    break;
  // ----------------------------- vytvoření osoba,tvori,rodina z ms_pary,du_pary
  case 'pary_new':
    // zrušení staré verze
    $qry= "TRUNCATE TABLE osoba"; $res= mysql_qry($qry);
    $qry= "TRUNCATE TABLE tvori"; $res= mysql_qry($qry);
    $qry= "TRUNCATE TABLE rodina"; $res= mysql_qry($qry);
    // vytvoření nové
    $n1= $n2= $n3= 0;
    $qryd= "SELECT * FROM du_pary ";
    $resd= mysql_qry($qryd);
    while ( $resd && $d= mysql_fetch_object($resd) ) {
      if ( $d->pocet==1 ) {
        // jen jeden výskyt
        $qryp= "SELECT * FROM ms_pary WHERE id_dupary={$d->id_dupary}";
        $resp= mysql_qry($qryp);
                                                        display("$resp:$qryp");
        while ( $resp && $p= mysql_fetch_assoc($resp) ) {
                                                        display("$resp:call");
          $r= rodina_insert($p);
//           if ( $p['jmeno_m'] ) {
//             $o= osoba_insert($p,1,'_m','p');
//             tvori_insert($r,$o,'m');
//           }
//           if ( $p['jmeno_z'] ) {
//             $o= osoba_insert($p,2,'_z','p');
//             tvori_insert($r,$o,'m');
//           }
//           if ( !$p['jmeno_m'] && !$p['jmeno_z'] )        // 7 případů
//             $html.= "neosoba id_pary={$p['id_pary']}, ";
                                                        break; //!!!!!!!!!!!!!!!!!!
        }
        $n1++;
      }
                                                        break; //!!!!!!!!!!!!!!!!!!
//       else if ( $d->typ=='123456789' ) {
//         // jednoduché duplicity - vezmeme prvního
//         $qryp= "SELECT * FROM ms_pary WHERE id_dupary={$d->id_dupary}";
//         $resp= mysql_qry($qryp);
//         if ( $resp && $p= mysql_fetch_assoc($resp) ) {
//           $r= rodina_insert($p);
//           if ( $p['jmeno_m'] ) {
//             $o= osoba_insert($p,1,'_m','p');
//             tvori_insert($r,$o,'m');
//           }
//           if ( $p['jmeno_z'] ) {
//             $o= osoba_insert($p,2,'_z','p');
//             tvori_insert($r,$o,'m');
//           }
//           if ( !$p['jmeno'] )                               // nenastává
//             $html.= "nerodina id_pary={$p['id_pary']}, ";
//           if ( !$p['jmeno_m'] && !$p['jmeno_z'] )           // nenastává
//             $html.= "neosoba id_pary={$p['id_pary']}, ";
//         }
//         $n2++;
//       }
//       else {
//         // složité duplicity - vezmeme jako základ ten nejnovější (du_pary.id_pary)
//         if ( !$d->id_pary ) {
//           // pokud nešel určit (pár nebyl použit v ms_kurs)
//           $d->id_pary= select("id_pary","ms_pary","id_dupary={$d->id_dupary}");
//         }
//         $r= $om= $oz= 0;
//         $qryp= "SELECT * FROM ms_pary WHERE id_pary={$d->id_pary}";
//         $resp= mysql_qry($qryp);
//         if ( $resp && $p= mysql_fetch_assoc($resp) ) {
//           $r= rodina_insert($p) ;
//           if ( $p['jmeno_m'] ) {
//             $om= osoba_insert($p,1,'_m','p');
//             tvori_insert($r,$om,'m');
//           }
//           if ( $p['jmeno_z'] ) {
//             $oz= osoba_insert($p,2,'_z','p');
//             tvori_insert($r,$oz,'m');
//           }
//           if ( !$p['jmeno'] )                               // nenastává
//             $html.= "nerodina id_pary={$p['id_pary']}, ";
//           if ( !$p['jmeno_m'] && !$p['jmeno_z'] )           // nenastává
//             $html.= "neosoba id_pary={$p['id_pary']}, ";
//         }
//         else fce_error("složité duplicity:{$d->id_dupary}.{$d->id_pary}");
//         // a ostatní přidáme do poznámky
//         $qryp= "SELECT * FROM ms_pary WHERE id_dupary={$d->id_dupary} AND id_pary!={$d->id_pary}";
//         $resp= mysql_qry($qryp);
//         while ( $resp && $p= mysql_fetch_assoc($resp) ) {
//           rodina_update($r,$p) ;
//           if ( $om )
//             osoba_update($om,$p,'_m');
//           if ( $oz )
//             osoba_update($oz,$p,'_z');
//         }
//         $n3++;
//       }
    }
    $html.= "<br>$n1, $n2, $n3";
    break;
  // ======================================================= MS_DETI, DU_DETI --> OSOBA,TVORI,RODINA
  // inicializace tabulky DU_DETI => 0
  case 'deti_init':
    $qry= "TRUNCATE TABLE du_deti";             $res= mysql_qry($qry);
    $qry= "UPDATE ms_deti SET id_dudeti=0";     $res= mysql_qry($qry);
    $qry= "DELETE FROM osoba WHERE origin='d'"; $res= mysql_qry($qry);
    $qry= "DELETE FROM tvori WHERE role='d' ";  $res= mysql_qry($qry);
    $html.= "byla inicializována tabulka zjištěných duplicit dětí";
    break;
  // ----------------------------- seskupení stejných jmen => 100  a tip nejnovější informace
  case 'deti_jmena':
    $qry= "SELECT GROUP_CONCAT(id_deti) AS _ids, count(*) AS _pocet,id_dupary
           FROM ms_deti GROUP BY jmeno,id_dupary";
    $res= mysql_qry($qry);
    while ( $res && ($p= mysql_fetch_object($res)) ) {
      $ids= $p->_ids;
      $typ= $p->_pocet>1 ? 100 : 123;
      if ( $p->_pocet>0 ) {
        $id_L= 0;
        // nalezneme nejčerstvější účast na skutečné akci (poradatel=0) nebo na jakékoliv "akci"
        $qryL= "SELECT id_deti
                FROM ms_kursdeti JOIN ms_deti USING (id_deti)
                JOIN ms_akce USING(id_akce) WHERE id_deti IN ($ids)
                ORDER BY poradatel ASC,datum_od DESC LIMIT 1";
        $resL= mysql_qry($qryL);
        if ( $resL && ($L= mysql_fetch_object($resL)) ) {
          $id_L= $L->id_deti;
        }
        $qryd= "INSERT INTO du_deti (id_dupary,typ,pocet,id_deti)
                VALUES ($p->id_dupary,$typ,$p->_pocet,$id_L)";
        $resd= mysql_qry($qryd);
        $id_dudeti= mysql_insert_id();
        $qryu= "UPDATE ms_deti SET id_dudeti=$id_dudeti WHERE id_deti IN ($ids)";
        $resu= mysql_qry($qryu);
        $qryu= "UPDATE ms_kursdeti SET id_dudeti=$id_dudeti WHERE id_deti IN ($ids)";
        $resu= mysql_qry($qryu);
        $n++;
      }
    }
    $html.= "bylo nalezeno $n záznamů M/L se shodou jmen";
    break;
  // ----------------------------- seskupení stejných údajů => 123
  case 'deti_udaje':
    $matches= array(
    //"100,jméno,jmeno",
      "020,rč,rodcislo",
      "003,poznámka,poznamka");
    $qryd= "SELECT id_dudeti FROM du_deti WHERE typ=100 ORDER BY id_dudeti /*LIMIT 1*/";
    $resd= mysql_qry($qryd);
    while ( $resd && ($d= mysql_fetch_object($resd)) ) {
      $id_dudeti= $d->id_dudeti;
      foreach ($matches as $match) {
        list($incr,$name,$flds)= explode(',',$match);
        $ask= "CONCAT(".str_replace(":",",':',",$flds).")";
        $qryp= "SELECT $ask AS _ask,id_deti FROM ms_deti WHERE id_dudeti=$id_dudeti LIMIT 1";
        $resp= mysql_qry($qryp);
        if ( $resp && $p= mysql_fetch_object($resp) ) {
          $id_deti= $p->id_deti;
          $ans= mysql_real_escape_string($p->_ask);
          $qryu= "UPDATE du_deti JOIN ms_deti USING(id_dudeti)
                  SET typ=typ+$incr
                  WHERE id_dudeti=$id_dudeti AND ms_deti.id_deti!=$id_deti AND $ask='$ans'";
          $resu= mysql_qry($qryu);
          $n+= mysql_affected_rows();
        }
      }
    }
    $html.= "bylo doplněno $n shod ve sledovaných položkách";
    break;
  // ----------------------------- vytvoření osoba,tvori,rodina z ms_deti,du_deti
  case 'deti_new':
    // zrušení staré verze
    $qry= "DELETE FROM osoba WHERE origin='d'"; $res= mysql_qry($qry);
    $qry= "DELETE FROM tvori WHERE role='d' ";  $res= mysql_qry($qry);
    // vytvoření nové
    $n1= $n2= $n3= 0;
    $qryd= "SELECT * FROM du_deti ";
    $resd= mysql_qry($qryd);
    while ( $resd && $d= mysql_fetch_object($resd) ) {
                                                display("id_dudeti={$d->id_dudeti}");
      // najdi rodinu
      $r= select("id_rodina","rodina","id_dupary={$d->id_dupary}");
      if ( !$r ) fce_error("není rodina pro {$d->id_dupary}");
      if ( $d->typ=='123' ) {
        // jednoduché duplicity - vezmeme prvního
        $qryp= "SELECT * FROM ms_deti WHERE id_dudeti={$d->id_dudeti}";
        $resp= mysql_qry($qryp);
        if ( $resp && $p= mysql_fetch_assoc($resp) ) {
          $o= dite_insert($p);
          tvori_insert($r,$o,'d');
        }
        $n2++;
      }
      else {
        // složité duplicity - vezmeme jako základ ten nejnovější (du_deti.id_deti)
        if ( !$d->id_deti ) {
          // pokud nešel určit (pár nebyl použit v ms_kurs)
          $d->id_deti= select("id_deti","ms_deti","id_dudeti={$d->id_dudeti}");
        }
        $qryp= "SELECT * FROM ms_deti WHERE id_deti={$d->id_deti}";
        $resp= mysql_qry($qryp);
        if ( $resp && $p= mysql_fetch_assoc($resp) ) {
          $o= dite_insert($p);
          tvori_insert($r,$o,'d');
        }
        else fce_error("složité duplicity:{$d->id_dudeti}.{$d->id_deti}");
        // a ostatní přidáme do poznámky
        $qryp= "SELECT * FROM ms_deti WHERE id_dudeti={$d->id_dudeti} AND id_deti!={$d->id_deti}";
        $resp= mysql_qry($qryp);
        while ( $resp && $p= mysql_fetch_assoc($resp) ) {
          dite_update($o,$p);
        }
        $n3++;
      }
    }
    $html.= "<br>$n2, $n3";
    break;
  // =============================================================================================== ZJEDNODUŠENÍ
  case 'elim_nerodiny':
    $n= 0;
    $qryr= "SELECT id_rodina,count(*) AS _pocet FROM tvori GROUP BY id_rodina HAVING _pocet=1";
    $resr= mysql_qry($qryr);
    while ( $resr && $r= mysql_fetch_object($resr) ) {
      $n++;
      $qry= "DELETE FROM tvori  WHERE id_rodina={$r->id_rodina}"; $res= mysql_qry($qry);
      $qry= "DELETE FROM rodina WHERE id_rodina={$r->id_rodina}"; $res= mysql_qry($qry);
    }
    $html.= "<br>bylo smazáno $n jednoprvkových rodin (ti co je tvořili zůstali)";
    break;
  // =============================================================================================== MS_KURS, DU_KURS
  // inicializace tabulky DU_KURS => 0
  case 'kurs_init':
    mysql_qry("TRUNCATE TABLE du_kurs");
    mysql_qry("TRUNCATE TABLE du_kursdeti");
    mysql_qry("UPDATE ms_kurs SET id_dukurs=0");
    $res= mysql_qry("DELETE FROM ms_kurs WHERE id_kurs IN
             (20293,20294,20295,20296,20297,20298,20299,20300,20301,20302,20303)");
    $k= mysql_affected_rows();
    $res= mysql_qry("DELETE FROM ms_kursdeti WHERE id_akce=0 OR id_deti=0");
    $d= mysql_affected_rows();
    $html.= "byla inicializována tabulka účastí, smazáno $k údajů o kurzech, a $d o dětech ";
    break;
  // ----------------------------- seskupení účastí na akcích do DU_KURS => nastavení jen pro 10000
  case 'kurs_duplo':
    $n= 0;
    $qryk= "SELECT ms_pary.id_dupary,ms_akce.id_duakce,GROUP_CONCAT(id_kurs) AS _ids,
              count(*) AS _pocet
            FROM ms_kurs
            LEFT JOIN ms_pary USING(id_pary)
            LEFT JOIN ms_akce USING(id_akce)
            GROUP BY ms_pary.id_dupary,ms_akce.id_duakce";
    $resk= mysql_qry($qryk);
    while ( $resk && ($k= mysql_fetch_object($resk)) ) {
      $ids= $k->_ids;
      $id_dupary= $k->id_dupary;
      $id_duakce= $k->id_duakce;
      $typ= $k->_pocet==1 ? 12345 : 10000;
      $qryd= "INSERT INTO du_kurs (id_dupary,id_duakce,typ,pocet) VALUES
                ($id_dupary,$id_duakce,$typ,{$k->_pocet})";
      $resd= mysql_qry($qryd);
      $id_dukurs= mysql_insert_id();
      $qryu= "UPDATE ms_kurs SET id_dukurs=$id_dukurs WHERE id_kurs IN ($ids)";
      $resu= mysql_qry($qryu);
      $n++;
    }
    $qryk= "SELECT id_dupary,GROUP_CONCAT(id_pary) AS _ids FROM ms_pary GROUP BY id_dupary";
    $resk= mysql_qry($qryk);
    while ( $resk && ($k= mysql_fetch_object($resk)) ) {
      $qryu= "UPDATE ms_kurs SET id_dupary={$k->id_dupary} WHERE id_pary IN ({$k->_ids})";
      $resu= mysql_qry($qryu);
    }
    $html.= "<br>bylo naplněno $n neduplicitních účastí dospělých";
    // děti
    $n= 0;
    $qryk= "SELECT ms_deti.id_dudeti,ms_akce.id_duakce,GROUP_CONCAT(id_kursdeti) AS _ids,
              count(*) AS _pocet
            FROM ms_kursdeti
            LEFT JOIN ms_deti USING(id_deti)
            LEFT JOIN ms_akce USING(id_akce)
            GROUP BY ms_deti.id_dudeti,ms_akce.id_duakce";
    $resk= mysql_qry($qryk);
    while ( $resk && ($k= mysql_fetch_object($resk)) ) {
      $ids= $k->_ids;
      $id_dudeti= $k->id_dudeti;
      $id_duakce= $k->id_duakce;
      $typ= $k->_pocet==1 ? 12 : 10;
      $qryd= "INSERT INTO du_kursdeti (id_dudeti,id_duakce,typ,pocet) VALUES
                ($id_dudeti,$id_duakce,$typ,{$k->_pocet})";
      $resd= mysql_qry($qryd);
      $id_dukursdeti= mysql_insert_id();
      $qryu= "UPDATE ms_kursdeti SET id_dukursdeti=$id_dukursdeti WHERE id_kursdeti IN ($ids)";
      $resu= mysql_qry($qryu);
      $n++;
    }
    $html.= "<br>bylo naplněno $n neduplicitních účastí dětí";
    break;
  // ----------------------------- seskupení stejných údajů => 12345
  case 'kurs_udaje':
    $matches= array(
    //"10000,jména,jmeno:prijmeni_m:jmeno_m:prijmeni_z:jmeno_z",
      "02000,účast,skupina:funkce:aktivita:dorazil",
      "00300,bydlení,budova:pokoj:kocarek:pecovatel:poznamka:pristylky:pocetdnu:svp:pouze",
      "00040,jídlo,strava_cel:strava_pol,cstrava_cel:cstrava_pol",
      "00005,prachy,platba:platba1:platba2:platba3:platba4"
    );
    $qryd= "SELECT id_dukurs FROM du_kurs WHERE typ=10000 ORDER BY id_dukurs /*LIMIT 1*/";
    $resd= mysql_qry($qryd);
    while ( $resd && ($d= mysql_fetch_object($resd)) ) {
      $id_dukurs= $d->id_dukurs;
      foreach ($matches as $match) {
        list($incr,$name,$flds)= explode(',',$match);
        $ask= "CONCAT(".str_replace(":",",':',",$flds).")";
        $qryp= "SELECT $ask AS _ask,id_kurs FROM ms_kurs WHERE id_dukurs=$id_dukurs LIMIT 1";
        $resp= mysql_qry($qryp);
        if ( $resp && $p= mysql_fetch_object($resp) ) {
          $id_kurs= $p->id_kurs;
          $ans= mysql_real_escape_string($p->_ask);
          $qryu= "UPDATE du_kurs JOIN ms_kurs USING(id_dukurs)
                  SET typ=typ+$incr WHERE id_dukurs=$id_dukurs AND id_kurs!=$id_kurs AND $ask='$ans'";
          $resu= mysql_qry($qryu);
          $n-= mysql_affected_rows();
        }
      }
    }
    $n= select("count(*)","du_kurs","typ!=12345");
    $html.= "bylo zjištěno $n rozdílů v duplicitách účastí";
    break;
  // ----------------------------- vytvoření pobyt,bydli
  case 'kurs_new':
    // zrušení staré verze
    mysql_qry("TRUNCATE TABLE pobyt");
    mysql_qry("TRUNCATE TABLE bydli");
    // vytvoření nové
    $n1= $n2= 0;
    $qryd= "SELECT * FROM du_kurs ";
//     $qryd.= "WHERE id_dukurs=14047";
    $resd= mysql_qry($qryd);
    while ( $resd && $d= mysql_fetch_object($resd) ) {
      $qryk= "SELECT * FROM ms_kurs WHERE id_dukurs={$d->id_dukurs} LIMIT 1";
      $resk= mysql_qry($qryk);
      if ( $resk && $k= mysql_fetch_object($resk) ) {
        $ids= $vals= ''; $del= '';
        foreach(explode(',',"id_dukurs,skupina,funkce,aktivita,
                  datplatby,zpusobplat,platba,platba1,platba2,platba3,platba4,
                  budova,pokoj,luzka,strava_cel,cstrava_cel,strava_pol,cstrava_pol,
                  kocarek,pecovatel,poznamka,pristylky,pocetdnu,svp,dorazil,pouze") as $id) {
          $id= trim($id);
          $ids.= "$del$id";
          $vals.= "$del'{$k->$id}'";
          $del= ',';
        }
        $qryp= "INSERT INTO pobyt ($ids,id_akce) VALUES ($vals,{$k->id_duakce})";
        $resp= mysql_qry($qryp);
        $id_pobyt= mysql_insert_id();
        // doplnění "spolubydlících" manželů - podle položky ms_kurs.pouze
        switch ($k->pouze) {
        case 0: // oba
          $o= select("id_osoba","osoba","id_dupary={$d->id_dupary} AND sex=1");
          if ( $o )
            bydli_insert($id_pobyt,$o);
          else
            $html.= "<br>spor v pouze pro id_dukurs={$d->id_dukurs} /oba-muž není";
          $o= select("id_osoba","osoba","id_dupary={$d->id_dupary} AND sex=2");
          if ( $o )
            bydli_insert($id_pobyt,$o);
          else
            $html.= "<br>spor v pouze pro id_dukurs={$d->id_dukurs} /oba-žena není";
          break;
        case 1: // jen muž
        case 2: // jen žena
          $o= select("id_osoba","osoba","id_dupary={$d->id_dupary} AND sex={$k->pouze}");
          if ( $o )
            bydli_insert($id_pobyt,$o);
          else
            $html.= "<br>spor v pouze pro id_dukurs={$d->id_dukurs} ";
          break;
        }
        // doplnění "spolubydlících" dětí z ms_kursdeti
        $qryo= "SELECT id_osoba FROM du_kursdeti
                JOIN du_deti as d USING(id_dudeti)
                JOIN osoba USING(id_dudeti)
                WHERE id_duakce={$k->id_duakce} AND d.id_dupary={$d->id_dupary} ";
        $reso= mysql_qry($qryo);
        while ( $reso && $o= mysql_fetch_object($reso) ) {
          bydli_insert($id_pobyt,$o->id_osoba);
        }
      }
//       break;
    }
    break;
  }
  return $html;
}
# -------------------------------------------------------------------------------------------------- pomocné
# ------------------------------------------------------------- rc2ymd
# převod rodného čísla na datum narození ve formátu d.m.Y s opravou chyb
# (zjednodušené)
function rc2ymd($rodcis) {
  $dmy= '0000-00-00';
  if ( (int)$rodcis!=0 && preg_match('~^([0-9]{2})([0-9]{2})([0-9]{2})~', $rodcis, $match)) {
    $y= ($match[1] >= 12 ? "19" : "20") . $match[1];
    $m= $match[2] % 50; $m= $m=='00' ? '01' : $m;
    $d= $match[3];      $d= $d=='00' ? '01' : $d;
    $ymd= "$y-$m-$d";
  }
  return $ymd;
}
# ------------------------------------------------------------- bydli_insert
function bydli_insert($p,$o) {
  $qryo= "INSERT INTO bydli (id_pobyt,id_osoba) VALUES ($p,$o)";
  $reso= mysql_qry($qryo);
}
# ------------------------------------------------------------- tvori_insert
function tvori_insert($r,$o,$role) {
  $qryo= "INSERT INTO tvori (id_rodina,id_osoba,role)
          VALUES ($r,$o,'$role')";
  $reso= mysql_qry($qryo);
}
# ------------------------------------------------------------- rodina_insert
function rodina_insert($p) {  trace();
  $id_dupary=$p["id_dupary"];
  $nazev=    $p["jmeno"];
  $ulice   = strtr($p["adresa"],"'","’");
  $psc     = $p["psc"];
  $os=       $p["mesto"];
  list($o,$s)= explode(',',$os);
  switch(trim($s)) {
  case 'Slovensko':
  case 'Sloven.':
  case 'Slov.':
  case 'SR':
  case 'SK':         $obec= $o;  $stat= 'SK';  break;
  case 'Finland':    $obec= $o;  $stat= 'FIN'; break;
  case 'Ukrajina':   $obec= $o;  $stat= 'UA';  break;
  case 'Bulharsko':  $obec= $o;  $stat= 'BG';  break;
  case 'Polsko':     $obec= $o;  $stat= 'PL';  break;
  case 'Holland':    $obec= $o;  $stat= 'NL';  break;
  case 'Canada ON':  $obec= $o;  $stat= 'CDN'; break;
  default:           $obec= $os; $stat= 'CZ';  break;
  }
  $telefony= $p["telefon"];
  $emaily=   $p["email"];
  $spz=      $p["spz"];
  $datsvatba=$p["datsvatba"];
  $svatba=   $p["svatba"];
  $note=     strtr($p["poznamka"],"'","’");
  $qryo= "INSERT INTO rodina (ulice,psc,obec,stat,
          id_dupary,nazev,telefony,emaily,spz,datsvatba,svatba,origin,note) VALUES (
          '$ulice','$psc','$obec','$stat',
          '$id_dupary','$nazev','$telefony','$emaily','$spz','$datsvatba','$svatba','p','$note')";
                                display("$reso:$qryo");
//   $reso= mysql_qry($qryo);
//   return mysql_insert_id();
}
# ------------------------------------------------------------- rodina_update
function rodina_update($r,$p) {
  $tag= $p["source"];
  // čtení základu
  $qry= "SELECT * FROM rodina WHERE id_rodina=$r";
  $res= mysql_qry($qry);
  if ( $res ) $x= mysql_fetch_assoc($res); else fce_error("rodina_update($r)");
  // vytvoření změn
  $set= '';
  if ( $x["nazev"]!=    $p["jmeno"]     ) $set.= "$tag:jmeno|{$p["jmeno"]}|";
  $ulice= strtr($p["adresa"],"'","’");
  if ( $x["ulice"]   != $ulice          ) $set.= "$tag:ulice|{$ulice}|";
  if ( $x["psc"]     != $p["psc"]       ) $set.= "$tag:psc|{$p["psc"]}|";
  if ( $x["obec"]    != $p["mesto"]     ) $set.= "$tag:obec|{$p["mesto"]}|";
  if ( $x["telefony"]!= $p["telefon"]   ) $set.= "$tag:telefon|{$p["telefon"]}|";
  if ( $x["emaily"]!=   $p["email"]     ) $set.= "$tag:email|{$p["email"]}|";
  if ( $x["spz"]!=      $p["spz"]       ) $set.= "$tag:spz|{$p["spz"]}|";
  if ( $x["datsvatba"]!=$p["datsvatba"] ) $set.= "$tag:svatba|{$p["datsvatba"]}|";
  if ( $x["svatba"]!=   $p["svatba"]    ) $set.= "$tag:svatba|{$p["svatba"]}|";
  $note= strtr($p["poznamka"],"'","’");
  if ( $x["note"]!=     $note           ) $set.= "$tag:poznamka|{$note}|";
  if ( $set ) {
    $qryo= "UPDATE rodina SET historie=CONCAT(historie,'$set') WHERE id_rodina=$r";
    $reso= mysql_qry($qryo);
  }
}
# ------------------------------------------------------------- dite_insert
function dite_insert($p) {
  $id_dudeti=$p["id_dudeti"];
  $jmeno=    $p["jmeno"];
  $rc=       $p["rodcislo"];
  $narozeni= rc2ymd($rc);
  $rc_xxxx = strlen($rc)>6 ? substr($rc,6) : '';
  $poznamka= strtr($p["poznamka"],"'","’");
  $historie= '';
  $qryo= "INSERT INTO osoba (
          id_dudeti,jmeno,note,origin,historie) VALUES (
          '$id_dudeti','$jmeno','$poznamka','d','$historie')";
  $reso= mysql_qry($qryo);
  return mysql_insert_id();
}
# ------------------------------------------------------------- dite_update
function dite_update($o,$p) {
  $tag= $p["source"];
  // čtení základu
  $qry= "SELECT * FROM osoba WHERE id_osoba=$o";
  $res= mysql_qry($qry);
  if ( $res ) $x= mysql_fetch_assoc($res); else fce_error("osoba_update($o)");
  // vytvoření změn
  $set= '';
  $rc= $p["rodcislo$_m"];
  $narozeni= rc2ymd($rc);
  $rc_xxxx = strlen($rc)>6 ? substr($rc,6) : '';
  if ( $x["rc_xxxx"] != $rc_xxxx           ) $set.= "$tag:rc_xxxx|{$rc_xxxx}|";
  if ( $x["narozeni"]!= $narozeni          ) $set.= "$tag:narozeni|{$narozeni}|";
  $poznamka= strtr($p["poznamka"],"'","’");
  if ( $x["note"]    != $poznamka          ) $set.= "$tag:poznamka|{$poznamka}|";
  if ( $set ) {
    $qryo= "UPDATE osoba SET historie=CONCAT(historie,'$set') WHERE id_osoba=$o";
    $reso= mysql_qry($qryo);
  }
}
# ------------------------------------------------------------- osoba_insert
function osoba_insert($p,$sex,$_m,$orig) {
  $id_dupary=$p["id_dupary"];
  $jmeno=    $p["jmeno$_m"];
  $prijmeni= $p["prijmeni$_m"] ? $p["prijmeni$_m"] : $p["jmeno"];
  $ulice   = strtr($p["adresa"],"'","’");
  $psc     = $p["psc"];
  $os=       $p["mesto"];
  list($o,$s)= explode(',',$os);
  switch(trim($s)) {
  case 'Slovensko':
  case 'Sloven.':
  case 'Slov.':
  case 'SR':
  case 'SK':         $obec= $o;  $stat= 'SK';  break;
  case 'Finland':    $obec= $o;  $stat= 'FIN'; break;
  case 'Ukrajina':   $obec= $o;  $stat= 'UA';  break;
  case 'Bulharsko':  $obec= $o;  $stat= 'BG';  break;
  case 'Polsko':     $obec= $o;  $stat= 'PL';  break;
  case 'Holland':    $obec= $o;  $stat= 'NL';  break;
  case 'Canada ON':  $obec= $o;  $stat= 'CDN'; break;
  default:           $obec= $os; $stat= 'CZ';  break;
  }
  $rc=       $p["rodcislo$_m"];
  $narozeni= rc2ymd($rc);
  $rc_xxxx = strlen($rc)>6 ? substr($rc,6) : '';
  $obcanka = $p["obcanka$_m"];
  $vzdelani= $p["vzdelani$_m"];
  $zamest  = $p["zamest$_m"];
  $zajmy   = $p["zajmy$_m"];
  $jazyk   = $p["jazyk$_m"];
  $cirkev  = $p["cirkev$_m"];
  $aktivita= $p["aktivita$_m"];
  $clen    = $p["clen$_m"];
  $historie= '';
  $qryo= "INSERT INTO osoba (
          id_dupary,jmeno,prijmeni,ulice,psc,obec,stat,narozeni,rc_xxxx,
          obcanka,vzdelani,zamest,zajmy,jazyk,cirkev,aktivita,clen,origin,historie,sex) VALUES (
          '$id_dupary','$jmeno','$prijmeni','$ulice','$psc','$obec','$stat','$narozeni','$rc_xxxx',
          '$obcanka','$vzdelani','$zamest','$zajmy','$jazyk','$cirkev','$aktivita',
          '$clen','$orig','$historie',$sex)";
  $reso= mysql_qry($qryo);
  return mysql_insert_id();
}
# ------------------------------------------------------------- osoba_update
function osoba_update($o,$p,$_m) {
  $tag= $p["source"];
  // čtení základu
  $qry= "SELECT * FROM osoba WHERE id_osoba=$o";
  $res= mysql_qry($qry);
  if ( $res ) $x= mysql_fetch_assoc($res); else fce_error("osoba_update($o)");
  // vytvoření změn
  $set= '';
  if ( $x["jmeno"]   != $p["jmeno$_m"]     ) $set.= "$tag:jmeno|{$p["jmeno$_m"]}|";
  if ( $x["prijmeni"]!= $p["prijmeni$_m"]  ) $set.= "$tag:prijmeni|{$p["prijmeni$_m"]}|";
  $ulice= strtr($p["adresa"],"'","’");
  if ( $x["ulice"]   != $ulice             ) $set.= "$tag:ulice|{$ulice}|";
  if ( $x["psc"]     != $p["psc"]          ) $set.= "$tag:psc|{$p["psc"]}|";
  if ( $x["obec"]    != $p["mesto"]        ) $set.= "$tag:obec|{$p["mesto"]}|";
  $rc= $p["rodcislo$_m"];
  $narozeni= rc2ymd($rc);
  $rc_xxxx = strlen($rc)>6 ? substr($rc,6) : '';
  if ( $x["rc_xxxx"] != $rc_xxxx           ) $set.= "$tag:rc_xxxx|{$rc_xxxx}|";
  if ( $x["narozeni"]!= $narozeni          ) $set.= "$tag:narozeni|{$narozeni}|";
  if ( $x["obcanka"] != $p["obcanka$_m"]   ) $set.= "$tag:obcanka|{$p["obcanka$_m"]}|";
  if ( $x["vzdelani"]!= $p["vzdelani$_m"]  ) $set.= "$tag:vzdelani|{$p["vzdelani$_m"]}|";
  if ( $x["zamest"]  != $p["zamest$_m"]    ) $set.= "$tag:zamest|{$p["zamest$_m"]}|";
  if ( $x["zajmy"]   != $p["zajmy$_m"]     ) $set.= "$tag:zajmy|{$p["zajmy$_m"]}|";
  if ( $x["jazyk"]   != $p["jazyk$_m"]     ) $set.= "$tag:jazyk|{$p["jazyk$_m"]}|";
  if ( $x["cirkev"]  != $p["cirkev$_m"]    ) $set.= "$tag:cirkev|{$p["cirkev$_m"]}|";
  if ( $x["aktivita"]!= $p["aktivita$_m"]  ) $set.= "$tag:aktivita|{$p["aktivita$_m"]}|";
  if ( $x["clen"]    != $p["clen$_m"]      ) $set.= "$tag:clen|{$p["clen$_m"]}|";
  if ( $set ) {
    $qryo= "UPDATE osoba SET historie=CONCAT(historie,'$set') WHERE id_osoba=$o";
    $reso= mysql_qry($qryo);
  }
}
# ================================================================================================== LIDÉ
# -------------------------------------------------------------------------------------------------- lide_cleni_kurs
# přehled
function lide_cleni_kurs($rok,$export=0) { trace();
  $html= "";
  // letošní účastníci
  // Příjmení	Jméno	Ulice	Město	PSČ	Datum nar.	Členství
  $rodin= $deti= 0;
  $qry= "SELECT
           cislo,akce,funkce,
           ms_pary.jmeno as p_jmeno,
           prijmeni_m,jmeno_m,rodcislo_m,prijmeni_z,jmeno_z,rodcislo_z,
           adresa,mesto,psc,
           year(datum_od) as rok,
           ms_deti.jmeno as jmeno_d,rodcislo
         FROM ms_kurs
         JOIN ms_pary USING (cislo,source)
         JOIN ms_akce USING(akce,source)
         JOIN ms_deti USING(cislo,source)
         WHERE druh=1 AND ms_kurs.source='L'
         HAVING rok=$rok
         ORDER BY cislo,rodcislo";
  $res= mysql_qry($qry);
  $par= 0;
  $line= array();
  while ( $res && ($u= mysql_fetch_object($res)) ) {
    if ( $par!=$u->cislo ) {
      // další rodina
      $rodin++;
      $par= $u->cislo;
      $style= $u->funkce ? "style=background-color:yellow" : '';
      $roky_m= vek($u->rodcislo_m);
      $roky_z= vek($u->rodcislo_z);
      $html.= "<br /><b $style>{$u->p_jmeno} - {$u->mesto}</b>: {$u->jmeno_m}/$roky_m, {$u->jmeno_z}/$roky_z";
      $line[]= (object)array(
        'p'=>$u->prijmeni_m,'j'=>$u->jmeno_m,'u'=>$u->adresa,'m'=>$u->mesto,'ps'=>$u->psc,
        'rc'=>$u->rodcislo_m,'r'=>$roky_m,'c'=>$u->funkce?'č':'b','cp'=>200);
      $line[]= (object)array(
        'p'=>$u->prijmeni_z,'j'=>$u->jmeno_z,'u'=>$u->adresa,'m'=>$u->mesto,'ps'=>$u->psc,
        'rc'=>$u->rodcislo_z,'r'=>$roky_z,'c'=>$u->funkce?'č':'b');
      if ( $u->jmeno_d ) {
        $deti++;
        $roky= vek($u->rodcislo);
        $html.= ", {$u->jmeno_d}/$roky";
        $prijmeni= substr($u->rodcislo,2,1)>4 ? $u->prijmeni_z : $u->prijmeni_m;
        $line[]= (object)array(
          'p'=>$prijmeni,'j'=>$u->jmeno_d,'u'=>$u->adresa,'m'=>$u->mesto,'ps'=>$u->psc,
          'rc'=>$u->rodcislo,'r'=>$roky,'c'=>'b');
      }
    }
    else {
      $deti++;
      $roky= vek($u->rodcislo);
      $html.= ", {$u->jmeno_d}/$roky";
      $prijmeni= substr($u->rodcislo,2,1)>4 ? $u->prijmeni_z : $u->prijmeni_m;
      $line[]= (object)array(
        'p'=>$prijmeni,'j'=>$u->jmeno_d,'u'=>$u->adresa,'m'=>$u->mesto,'ps'=>$u->psc,
        'rc'=>$u->rodcislo,'r'=>$roky,'c'=>'b');
    }
  }
//                                                         debug($line);
  $note= "Seznam účastníků letního kurzu roku $rok včetně dětí, doplněný věkem.
          <br />Seznam je možné vyexportovat do Excelu spolu s doplňujícími údaji pro
          přehled o členské základně.";
  $note.= "<br />Letního kurzu se účastnilo celkem $rodin rodin s $deti dětmi.";
  $html= "<p><i>$note</i></p>$html";
  if ( $export ) {
    // export
    $html= lide_export($line,"clenove_$rok",$rok);
  }
  return $html;
}
function vek ($rc) {
  $let= 0;
  if ( $rc ) {
    $yy_rc= substr($rc,0,2);
    $yy= date('y');
    $let= $yy_rc==$yy ? 1 : ( $yy_rc<$yy ? $yy-$yy_rc : $yy+(100-$yy_rc) );
  }
  return $let;
}
# -------------------------------------------------------------------------------------------------- lide_export
function lide_export($line,$file,$rok) { trace();
  require_once('./licensed/xls/OLEwriter.php');
  require_once('./licensed/xls/BIFFwriter.php');
  require_once('./licensed/xls/Worksheet.php');
  require_once('./licensed/xls/Workbook.php');
  global $ezer_path_root;
  chdir($ezer_path_root);
  $table= "$file.xls";
  try {
    $wb= new Workbook($table);
    // formáty
    $format_hd= $wb->add_format();
    $format_hd->set_bold();
    $format_hd->set_pattern();
    $format_hd->set_fg_color('silver');
    $format_dec= $wb->add_format();
    $format_dec->set_num_format("# ##0.00");
    $format_dat= $wb->add_format();
    $format_dat->set_num_format("d.m.yyyy");
    // list LK
    $ws= $wb->add_worksheet("LK $rok");
    // hlavička
    $fields= explode(',','-15:3,-18:3,-26:3,-30:3,-35:3,-99:3,příjmení:12,jméno:10,ulice:20,město:20,psč:6,datum nar.:11,věk:4,členství:8,čl.přísp.:8,KČ:8,:5,stáří:10,počet:8');
    $sy= 0;
    foreach ($fields as $sx => $fa) {
      list($title,$width)= explode(':',$fa);
      $ws->set_column($sx,$sx,$width);
      $ws->write_string($sy,$sx,utf2win_sylk($title,true),$format_hd);
    }
    // data
    foreach($line as $x) {
      $sy++; $sx= 0;
      $ws->write_number($sy,$sx++,$x->r>=0  && $x->r<=15 ? 1 : 0);
      $ws->write_number($sy,$sx++,$x->r>=16 && $x->r<=18 ? 1 : 0);
      $ws->write_number($sy,$sx++,$x->r>=19 && $x->r<=26 ? 1 : 0);
      $ws->write_number($sy,$sx++,$x->r>=27 && $x->r<=30 ? 1 : 0);
      $ws->write_number($sy,$sx++,$x->r>=31 && $x->r<=35 ? 1 : 0);
      $ws->write_number($sy,$sx++,$x->r>35               ? 1 : 0);
      $ws->write_string($sy,$sx++,utf2win_sylk($x->p,true));
      $ws->write_string($sy,$sx++,utf2win_sylk($x->j,true));
      $ws->write_string($sy,$sx++,utf2win_sylk($x->u,true));
      $ws->write_string($sy,$sx++,utf2win_sylk($x->m,true));
      $ws->write_string($sy,$sx++,utf2win_sylk($x->ps,true));
      $ws->write_string($sy,$sx++,utf2win_sylk($x->rc,true));
      $ws->write_number($sy,$sx++,$x->r);
      $ws->write_string($sy,$sx++,utf2win_sylk($x->c,true));
      $ws->write_number($sy,$sx++,$x->cp?$x->cp:0);
      $ws->write_string($sy,$sx++,utf2win_sylk("Setkání",true));
    }
    // součty
    $sy++; $sx= 0;
    $s= 'A'; $r= "{$s}2:$s$sy"; $ws->write_formula($sy,$sx++,"=SUM($r)");
    $s++   ; $r= "{$s}2:$s$sy"; $ws->write_formula($sy,$sx++,"=SUM($r)");
    $s++   ; $r= "{$s}2:$s$sy"; $ws->write_formula($sy,$sx++,"=SUM($r)");
    $s++   ; $r= "{$s}2:$s$sy"; $ws->write_formula($sy,$sx++,"=SUM($r)");
    $s++   ; $r= "{$s}2:$s$sy"; $ws->write_formula($sy,$sx++,"=SUM($r)");
    $s++   ; $r= "{$s}2:$s$sy"; $ws->write_formula($sy,$sx++,"=SUM($r)");
    $wb->close();
    $html.= "Byl vygenerován soubor pro Excel: <a href='$table'>$table</a>";
  }
  catch (Exception $e) {
    $html.= nl2br("Chyba: ".$e->getMessage()." na ř.".$e->getLine());
  }
  return $html;
}
# -------------------------------------------------------------------------------------------------- lide_spolu
# přehled
function lide_spolu($rok) { trace();
  $html= "<dl>";
  // letošní účastníci
  $letos= array();
  $qry= "SELECT cislo,akce,skupina,jmeno,mesto,nazev,year(datum_od) as rok
          FROM ms_kurs
          JOIN ms_pary USING (cislo,source)
          JOIN ms_akce USING(akce,source)
          WHERE druh=1 AND ms_kurs.source='L'
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
        JOIN ms_pary USING (cislo,source)
        JOIN ms_akce USING(akce)
        WHERE druh=1 and year(datum_od)=2009 AND ms_kurs.source='L'
        GROUP BY jmeno HAVING xx>1
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
    // minulé účasti
    $qry= "
            SELECT akce,skupina,nazev,year(datum_od) as rok
            FROM ms_kurs
            JOIN ms_akce USING(akce,source)
            WHERE druh=1 and cislo={$par} and skupina>0 AND ms_kurs.source='L'
            ORDER BY datum_od DESC
        ";
    $res= mysql_qry($qry);
    $ucasti= '';
    while ( $res && ($r= mysql_fetch_object($res)) ) {
      // minulé skupinky
      $qry_s= "
            SELECT akce, cislo, skupina,funkce,jmeno,mesto,nazev,year(datum_od)
            FROM ms_kurs
            JOIN ms_pary USING (cislo,source)
            JOIN ms_akce USING(akce,source)
            WHERE akce={$r->akce} and skupina={$r->skupina} and find_in_set(cislo,'$letosni')
              AND ms_kurs.source='L'
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
  $html.= "</dl>";
  $note= "Abecední seznam účastníků letního kurzu roku $rok doplněný seznamem členů jeho starších
          skupinek na letních kurzech. Ve skupinkách jsou uvedení jen účastníci
          kurzu roku $rok.";
  return "<p><i>$note</i></p>$html";
}
# -------------------------------------------------------------------------------------------------- lide_kurs
# přehled
function lide_kurs($rok) { trace();
  $html= "";
  // letošní účastníci
  $qry= "SELECT cislo,akce,skupina,jmeno,mesto,nazev,year(datum_od) as rok, jmeno_m, jmeno_z
          FROM ms_kurs
          JOIN ms_pary USING (cislo,source)
          JOIN ms_akce USING(akce,source)
          WHERE druh=1 AND ms_kurs.source='L'
          HAVING rok=$rok
          ORDER BY jmeno";
  $res= mysql_qry($qry);
  while ( $res && ($u= mysql_fetch_object($res)) ) {
    $html.= "<br><b>{$u->jmeno}</b> {$u->jmeno_m} a {$u->jmeno_z} - {$u->mesto}";
  }
  $note= "Abecední seznam účastníků letního kurzu roku $rok.";
  return "<p><i>$note</i></p>$html";
  return $html;
}
# -------------------------------------------------------------------------------------------------- lide_plachta
# podklad pro tvorbu skupinek
function lide_plachta($rok,$export=0) { trace();
  // číselníky
  $c_vzdelani= map_cis('ms_akce_vzdelani','zkratka');  $c_vzdelani[0]= '?';
  $c_cirkev= map_cis('ms_akce_cirkev','zkratka');      $c_cirkev[0]= '?';  $c_cirkev[1]= 'kat';
  $letos= date('Y');
  $html= "";
  $excel= array();
//   $html.= "<table class='vypis'>";
  // letošní účastníci
  $qry= "SELECT cislo,akce,skupina,jmeno,mesto,nazev,year(datum_od) as rok, jmeno_m, jmeno_z,
            svatba, funkce, vzdelani_m, vzdelani_z, cirkev_m, cirkev_z, rodcislo_m, rodcislo_z,
            aktivita_m, aktivita_z, zajmy_m, zajmy_z, zamest_m, zamest_z
          FROM ms_kurs
          JOIN ms_pary USING (cislo,source)
          JOIN ms_akce USING(akce,source)
          WHERE druh=1 AND ms_kurs.source='L'
                                                        /*AND ms_pary.id_pary=1439*/
          HAVING rok=$rok
          ORDER BY jmeno ";
  $res= mysql_qry($qry);
  while ( $res && ($u= mysql_fetch_object($res)) ) {
    // minulé účasti
    $rqry= " SELECT COUNT(*) AS _pocet /*GROUP_CONCAT(RIGHT(year(datum_od),2)) as _ucast*/
            FROM ms_kurs
            JOIN ms_akce USING(akce,source)
            WHERE druh=1 and cislo={$u->cislo} and skupina>0 AND ms_kurs.source='L'
            ORDER BY datum_od DESC ";
    $rres= mysql_qry($rqry);
    while ( $rres && ($r= mysql_fetch_object($rres)) ) {
      $u->ucasti= $r->_pocet ? "  {$r->_pocet}x" : '';
    }
    // počet dětí
    $dqry= "SELECT COUNT(*) as _pocet
           FROM ms_deti
           WHERE cislo={$u->cislo} AND source='L'";
    $dres= mysql_qry($dqry);
    while ( $dres && ($d= mysql_fetch_object($dres)) ) {
      $u->deti= $d->_pocet;
    }
//                                                         debug($u);
    // věk
    $vek_m= rc2roky($u->rodcislo_m);
    $vek_z= rc2roky($u->rodcislo_z);
    $vek= abs($vek_m-$vek_z)<5 ? $vek_m : "$vek_m/$vek_z";
    // spolu
    $spolu= $u->svatba ? $letos-$u->svatba : '?';
    // děti
    $deti= $u->deti;
    // vzdělání
    $vzdelani_muze= mb_substr($c_vzdelani[$u->vzdelani_m],0,2,"UTF-8");
    $vzdelani_zeny= mb_substr($c_vzdelani[$u->vzdelani_z],0,2,"UTF-8");
    $vzdelani= $vzdelani_muze==$vzdelani_zeny ? $vzdelani_muze : "$vzdelani_muze/$vzdelani_zeny";
//                                                         display("$vek_m/$vek_z=$vek");
    // konfese
    $cirkev= $u->cirkev_m==$u->cirkev_z
      ? ($u->cirkev_m==1 ? '' : ", {$c_cirkev[$u->cirkev_m]}")
      : ", {$c_cirkev[$u->cirkev_m]}/{$c_cirkev[$u->cirkev_z]}";
    // agregace
    $r1= ($u->funkce==1 ? '* ' : '')."{$u->jmeno} {$u->jmeno_m} a {$u->jmeno_z} {$u->ucasti}";
    $r2= "věk:$vek, spolu:$spolu, dětí:$deti, {$u->mesto}, $vzdelani $cirkev";
    // atributy
    $r31= $u->aktivita_m;
    $r32= $u->aktivita_z;
    $r41= $u->zajmy_m;
    $r42= $u->zajmy_z;
    $r51= $u->zamest_m;
    $r52= $u->zamest_z;
    // listing
    $html.= "<table class='vypis' style='width:300px'>";
    $html.= "<tr><td colspan=2><b>$r1</b></td></tr>";
    $html.= "<tr><td colspan=2>$r2</td></tr>";
    $html.= "<tr><td>$r31</td><td>$r32</td></tr>";
    $html.= "<tr><td>$r41</td><td>$r42</td></tr>";
    $html.= "<tr><td>$r51</td><td>$r52</td></tr>";
    $html.= "</table><br/>";
//     $html.= "<tr><td>$r1</td><td>$r2</td><td>$r31</td><td>$r32</td>";
//     $html.= "<td>$r41</td><td>$r42</td>";
//     $html.= "<td>$r51</td><td>$r52</td></tr>";
    if ( $export ) {
      $excel[]= array($r1,$r2,$r31,$r41,$r51,$r32,$r42,$r52,$vzdelani_muze,$vek_m);
    }
  }
                                                debug($excel);
//   $html.= "</table";
    if ( $export ) {
      $html= lide_plachta_export($excel,'plachta');
    }
  return $html;
}
# -------------------------------------------------------------------------------------------------- lide_plachta_export
function lide_plachta_export($line,$file) { trace();
  require_once('./ezer2/server/licensed/xls/OLEwriter.php');
  require_once('./ezer2/server/licensed/xls/BIFFwriter.php');
  require_once('./ezer2/server/licensed/xls/Worksheet.php');
  require_once('./ezer2/server/licensed/xls/Workbook.php');
  global $ezer_path_root;
  chdir($ezer_path_root);
  $table= "$file.xls";
  try {
    $wb= new Workbook($table);
    // formáty
    $format_hd= $wb->add_format();
    $format_hd->set_bold();
    $format_hd->set_pattern();
    $format_hd->set_fg_color('silver');
    $format_dec= $wb->add_format();
    $format_dec->set_num_format("# ##0.00");
    $format_dat= $wb->add_format();
    $format_dat->set_num_format("d.m.yyyy");
    // list LK
    $ws= $wb->add_worksheet("LK $rok");
    // hlavička
    $fields= explode(',','r1:20,r2:20,r31:20,r41:20,r51:20,r32:20,r42:20,r52:20,skola:8,vek:8');
    $sy= 0;
    foreach ($fields as $sx => $fa) {
      list($title,$width)= explode(':',$fa);
      $ws->set_column($sx,$sx,$width);
      $ws->write_string($sy,$sx,utf2win_sylk($title,true),$format_hd);
    }
    // data
    foreach($line as $x) {
      $sy++; $sx= 0;
      $ws->write_string($sy,$sx++,utf2win_sylk($x[0],true));
      $ws->write_string($sy,$sx++,utf2win_sylk($x[1],true));
      $ws->write_string($sy,$sx++,utf2win_sylk($x[2],true));
      $ws->write_string($sy,$sx++,utf2win_sylk($x[3],true));
      $ws->write_string($sy,$sx++,utf2win_sylk($x[4],true));
      $ws->write_string($sy,$sx++,utf2win_sylk($x[5],true));
      $ws->write_string($sy,$sx++,utf2win_sylk($x[6],true));
      $ws->write_string($sy,$sx++,utf2win_sylk($x[7],true));
      $ws->write_string($sy,$sx++,utf2win_sylk($x[8],true));
      $ws->write_number($sy,$sx++,$x[9]);
    }
    $wb->close();
    $html.= "Byl vygenerován soubor pro Excel: <a href='$table'>$table</a>";
  }
  catch (Exception $e) {
    $html.= nl2br("Chyba: ".$e->getMessage()." na ř.".$e->getLine());
  }
  return $html;
}
?>
