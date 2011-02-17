<?php # (c) 2007-2009 Martin Smidek <martin@smidek.eu>
# ================================================================================================== LIDÉ-UNIFY
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
  // ------------------------------------------------------------- MS_DRUHAKCE, DU_AKCE
  // inicializace tabulky DU_AKCE => 0
  case 'akce_init':
    $qry= "TRUNCATE TABLE du_akce";
    $res= mysql_qry($qry);
    $qry= "UPDATE ms_druhakce SET id_duakce=0";
    $res= mysql_qry($qry);
    $qry= "UPDATE du_kurs SET id_duakce=0";
    $res= mysql_qry($qry);
    $html.= "byla inicializována tabulka zjištěných duplicit akcí";
    break;
  // seskupení stejných názvů a zahájení => 10
  case 'akce_nazev':
    $qry= "SELECT GROUP_CONCAT(id_akce) AS _ids, count(*) AS _pocet FROM ms_druhakce
           GROUP BY nazev,datum_od ORDER BY datum_od, nazev DESC";
    $res= mysql_qry($qry);
    while ( $res && ($p= mysql_fetch_object($res)) ) {
      $ids= $p->_ids;
      if ( $p->_pocet>0 ) {
        $qryd= "INSERT INTO du_akce (typ) VALUES (10)";
        $resd= mysql_qry($qryd);
        $id_duakce= mysql_insert_id();
        $qryu= "UPDATE ms_druhakce SET id_duakce=$id_duakce WHERE id_akce IN ($ids)";
        $resu= mysql_qry($qryu);
        $n++;
      }
    }
    $html.= "bylo nalezeno $n záznamů M/L se shodou názvů";
    break;
  // seskupení stejných údajů => 12345679
  case 'akce_udaje':
    $matches= array(
    //"10,název,nazev:datum_od",
      "02,údaje,ciselnik_akce:ciselnik_rok:poradatel:datum_do:misto"
    );
    $qryd= "SELECT id_duakce FROM du_akce WHERE typ=10 ORDER BY id_duakce /*LIMIT 1*/";
    $resd= mysql_qry($qryd);
    while ( $resd && ($d= mysql_fetch_object($resd)) ) {
      $id_duakce= $d->id_duakce;
      foreach ($matches as $match) {
        list($incr,$name,$flds)= explode(',',$match);
        $ask= "CONCAT(".str_replace(":",",':',",$flds).")";
        $qryp= "SELECT $ask AS _ask,id_akce FROM ms_druhakce WHERE id_duakce=$id_duakce LIMIT 1";
        $resp= mysql_qry($qryp);
        if ( $resp && $p= mysql_fetch_object($resp) ) {
          $id_akce= $p->id_akce;
          $ans= mysql_real_escape_string($p->_ask);
          $qryu= "UPDATE du_akce JOIN ms_druhakce USING(id_duakce)
                  SET typ=typ+$incr WHERE id_duakce=$id_duakce AND id_akce!=$id_akce AND $ask='$ans'";
          $resu= mysql_qry($qryu);
          $n+= mysql_affected_rows();
        }
      }
    }
    $html.= "bylo doplněno $n shod ve sledovaných položkách";
    break;
  // ------------------------------------------------------------- MS_PARY, DU_PARY
  // inicializace tabulky DU_PARY => 0
  case 'pary_init':
    $qry= "TRUNCATE TABLE du_pary";
    $res= mysql_qry($qry);
    $qry= "UPDATE ms_pary SET id_dupary=0";
    $res= mysql_qry($qry);
    $qry= "UPDATE du_kurs SET id_dupary=0";
    $res= mysql_qry($qry);
    $html.= "byla inicializována tabulka zjištěných duplicit párů";
    break;
  // seskupení stejných jmen => 100000000
  case 'pary_jmena':
    $qry= "SELECT GROUP_CONCAT(id_pary) AS _ids, count(*) AS _pocet FROM ms_pary
           GROUP BY jmeno,prijmeni_m,jmeno_m,prijmeni_z,jmeno_z ";
    $res= mysql_qry($qry);
    while ( $res && ($p= mysql_fetch_object($res)) ) {
      $ids= $p->_ids;
      $typ= $p->_pocet>1 ? 100000000 : 123456789;
      if ( $p->_pocet>0 ) {
        $qryd= "INSERT INTO du_pary (typ) VALUES ($typ)";
        $resd= mysql_qry($qryd);
        $id_dupary= mysql_insert_id();
        $qryu= "UPDATE ms_pary SET id_dupary=$id_dupary WHERE id_pary IN ($ids)";
        $resu= mysql_qry($qryu);
        $n++;
      }
    }
    $html.= "bylo nalezeno $n záznamů M/L se shodou jmen";
    break;
  // seskupení stejných údajů => 12345679
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
                  SET typ=typ+$incr WHERE id_dupary=$id_dupary AND id_pary!=$id_pary AND $ask='$ans'";
          $resu= mysql_qry($qryu);
          $n+= mysql_affected_rows();
        }
      }
    }
    $html.= "bylo doplněno $n shod ve sledovaných položkách";
    break;
  // ------------------------------------------------------------- MS_KURS, DU_KURS
  // inicializace tabulky DU_KURS => 0
  case 'kurs_init':
    $qry= "TRUNCATE TABLE du_kurs";
    $res= mysql_qry($qry);
    $qry= "UPDATE ms_kurs SET id_dukurs=0";
    $res= mysql_qry($qry);
    $html.= "byla inicializována tabulka účastí";
    break;
  // seskupení účastí na akcích do DU_KURS => nastavení jen pro 10000
  case 'kurs_duplo':
    $qryk= "SELECT id_dupary,id_duakce,GROUP_CONCAT(id_kurs) AS _ids,du_pary.typ
            FROM ms_kurs
            LEFT JOIN ms_pary USING(id_pary)
            LEFT JOIN du_pary USING(id_dupary)
            LEFT JOIN ms_druhakce USING(id_akce)
            LEFT JOIN du_akce USING(id_duakce)
            WHERE /*du_pary.typ=123456789 AND*/ NOT ISNULL(id_duakce)
            GROUP BY ms_pary.id_dupary,ms_druhakce.id_duakce,du_pary.typ";
    $resk= mysql_qry($qryk);
    while ( $resk && ($k= mysql_fetch_object($resk)) ) {
      $ids= $k->_ids;
      $id_dupary= $k->id_dupary;
      $id_duakce= $k->id_duakce;
      $typ= $k->typ;
      $qryd= "INSERT INTO du_kurs (id_dupary,id_duakce,typ) VALUES ($id_dupary,$id_duakce,$typ)";
      $resd= mysql_qry($qryd);
      $id_dukurs= mysql_insert_id();
      $qryu= "UPDATE ms_kurs SET id_dukurs=$id_dukurs WHERE id_kurs IN ($ids)";
      $resu= mysql_qry($qryu);
      $n++;
    }
    $html.= "bylo naplněno $n neduplicitních účastí";
    break;
  // seskupení stejných údajů => 12345
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
          $n+= mysql_affected_rows();
        }
      }
    }
    $html.= "byly definováno $n ohodnocení rozdílů v duplicitách účastí";
  }
  return $html;
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
         JOIN ms_druhakce USING(akce,source)
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
          JOIN ms_druhakce USING(akce,source)
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
        JOIN ms_druhakce USING(akce)
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
            JOIN ms_druhakce USING(akce,source)
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
            JOIN ms_druhakce USING(akce,source)
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
          JOIN ms_druhakce USING(akce,source)
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
          JOIN ms_druhakce USING(akce,source)
          WHERE druh=1 AND ms_kurs.source='L'
                                                        /*AND ms_pary.id_pary=1439*/
          HAVING rok=$rok
          ORDER BY jmeno ";
  $res= mysql_qry($qry);
  while ( $res && ($u= mysql_fetch_object($res)) ) {
    // minulé účasti
    $rqry= " SELECT COUNT(*) AS _pocet /*GROUP_CONCAT(RIGHT(year(datum_od),2)) as _ucast*/
            FROM ms_kurs
            JOIN ms_druhakce USING(akce,source)
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
