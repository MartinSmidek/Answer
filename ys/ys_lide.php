<?php # (c) 2007-2009 Martin Smidek <martin@smidek.eu>
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
         JOIN ms_pary USING (cislo)
         JOIN ms_druhakce USING(akce)
         JOIN ms_deti USING(cislo)
         WHERE druh=1
         HAVING rok=$rok
         ORDER BY cislo";
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
                                                        debug($line);
  $html= "<i>Celkem se účastnilo $rodin rodin s $deti dětmi</i><br />$html";
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
    $let= $yy_rc<$yy ? $yy-$yy_rc : $yy+(100-$yy_rc);
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
  $html.= "</dl>";
  return $html;
}
# -------------------------------------------------------------------------------------------------- lide_kurs
# přehled
function lide_kurs($rok) { trace();
  $html= "";
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
