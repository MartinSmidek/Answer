<?php

# ---------------------------------------------------------------------------------- pipe pdenik_typ
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
# ---------------------------------------------------------------------------------- p pdenik_insert
# form_make
# $cislo==0 způsobí nalezení nového čísla dokladu
function p_pdenik_insert($typ,$org,$org_abbr,$datum) {
  global $x,$y;
  // převzetí hodnot
//                                                          debug(Array($typ,$org,$cislo,$datum),'p_denik_insert');
  $db= $x->db;
  $select= array();
  make_get($set,$select,$fields);
  // nalezení nového čísla dokladu (v každé pokladně se zvlášť číslují příjmy a výdaje)
  $year= substr(trim($datum),-4);
  $qry= "SELECT max(cislo) as c FROM $db.pdenik WHERE org=$org AND typ=$typ AND year(datum)=$year";
  $res= pdo_qry($qry);
  if ( $res && $row= pdo_fetch_assoc($res) ) {
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
    $res= pdo_qry($qry);
    $y->key= pdo_insert_id();
  }
}
# ----------------------------------------------------------------------------------- kasa menu_show
# ki - menu
# $cond = podmínka pro pdenik nastavená ve fis_kasa.ezer
# $day =  má formát d.m.yyyy
function kasa_menu_show($k1,$k2,$k3,$cond=1,$day='',$db='') {
  global $answer_db;
  if (!$db) $db= $answer_db;
  $html= '';
  switch ( "$k2 $k3" ) {
  case 'stav aktualne':
    $dnes= date('j.n.Y');
    $dnes_mysql= date('Y-m-d');
    $html.= "<div class='karta'>Aktuální stav pokladen ke dni $dnes</div>";
    $year= date('Y');
    $interval= " datum BETWEEN '$year-01-01' AND '$dnes_mysql'";
    $html.= kasa_menu_comp($interval,$db);
    break;
  case 'stav s_filtrem':
    $html.= "<div class='karta'>Stav pokladen podle nastavení </div>";
    $html.= kasa_menu_comp($cond,$db);
    $html.= "<p><i>filtr: $cond</i></p>";
    break;
  case 'stav k_datu':
    $html.= "<div class='karta'>Stav pokladen ke dni $day </div>";
    $until= sql_date1($day,1);
    $year= substr($until,0,4);
    $interval= " datum BETWEEN '$year-01-01' AND '$until'";
    $html.= kasa_menu_comp($interval,$db);
    break;
  case 'export letos':
    $rok= date('Y');
    $title= "Pokladní deník roku $rok";
    $html.= "<div class='karta'>Export pokladních deníků roku $rok</div>";
    $cond= " datum BETWEEN '$rok-01-01' AND '$rok-12-31'";
    $html.= kasa_export($cond,"pokladna_{$rok}",$db,$title);
    break;
  case 'export vloni':
    $rok= date('Y')-1;
    $title= "Pokladní deník roku $rok";
    $html.= "<div class='karta'>Export pokladních deníků roku $rok</div>";
    $cond= " datum BETWEEN '$rok-01-01' AND '$rok-12-31'";
    $html.= kasa_export($cond,"pokladna_{$rok}",$db,$title);
    break;
  }
  return $html;
}
# -------------------------------------------------------------------------------------- kasa export
function kasa_export($cond,$file,$db,$title) { trace();
  global $ezer_version;
  $xls= "|open $file";
  $qry_p= "SELECT * FROM $db.pokladna ";
  $res_p= pdo_qry($qry_p);
  while ( $res_p && $p= pdo_fetch_object($res_p) ) {
    $xls.= "\n|sheet vypis;;L;page";
    $xls.= "\n|A1 $title::size=13 bold";
    // hlavička
    $fields= explode(',','ident:11,číslo:6,datum:10,příjmy:13,výdaje:13,stav:13,'
        . 'od koho/komu:30,účel:30,př.:5');
    $n= 3; $a= 0; $clmns= $del= '';
    $xls.= "\n";
    foreach ($fields as $fa) {
      list($title,$width)= explode(':',$fa);
      $A= Excel5_n2col($a++);
      $xls.= "|$A$n $title";
      if ( $width ) {
        $clmns.= "$del$A=$width";
        $del= ',';
      }
    }
    if ( $clmns ) $xls.= "\n|columns $clmns ";
    $xls.= "\n|A$n:$A$n bcolor=ffc0e2c2 wrap border=+h|A$n:$A$n border=t";
    // data
    $n0= $n= 4; 
    $qry= "SELECT * FROM $db.pdenik WHERE $cond AND pdenik.org={$p->id_pokladna} ORDER BY datum";
    $res= pdo_qry($qry);
    while ( $res && $d= pdo_fetch_object($res) ) {
      $xls.= "\n|A$n {$d->ident}";
      $xls.= "|B$n {$d->cislo}::right";
        // převod data
      $datum= sql2xls($d->datum);  
      $xls.= "|C$n $datum::right date";
      if ( $d->typ==1 ) {
        $xls.= "|D$n 0";
        $xls.= "|E$n {$d->castka}::kc right";
      } else {
        $xls.= "|D$n {$d->castka}::kc right";
        $xls.= "|E$n 0";
      }
      $n1= $n-1;
      $s= $n==$n0 ? "" : "F$n1+";
      $xls.= "|F$n ={$s}D$n-E$n::kc right";
      $xls.= "|G$n {$d->komu}";
      $xls.= "|H$n {$d->ucel}";
      $xls.= "|I$n {$d->priloh}";
      $n++;
    }
    $n1= $n-1;
    $xls.= "\n|A$n1:$A$n1 border=,,t,";
    $xls.= "\n|C$n CELKEM::right";
    $xls.= "|D$n =SUM(D$n0:D$n1)::kc right";
    $xls.= "|E$n =SUM(E$n0:E$n1)::kc right";
    $xls.= "|F$n =D$n-E$n::kc right";
    $xls.= "|C$n:F$n bold";
  }
  // časová značka
  $kdy= date("j. n. Y");
  $n+= 2;
  $xls.= "|A$n Výpis ze dne $kdy::italic";
  $xls.= "\n|close";
//                                      display($xls);
  require_once "ezer$ezer_version/server/vendor/autoload.php";
  $inf= Excel2007($xls,1);
  if ( $inf ) {
    $html.= "Export se nepovedlo vygenerovat ($inf)";
  }
  else {
    $html.= "Byl vygenerován soubor pro Excel: <a href='docs/$file.xlsx'>$file.xlsx</a>";
  }
  return $html;
}
# ----------------------------------------------------------------------------------- kasa menu_comp
function kasa_menu_comp($cond,$db) {
  $celkem= 0;
  $html= "<table>";
  $qry= "SELECT nazev, sum(if(typ=2,castka,-castka)) as s, abbr FROM $db.pdenik
        LEFT JOIN $db.pokladna ON pdenik.org=id_pokladna WHERE $cond GROUP BY pdenik.org";
  $res= pdo_qry($qry);
  while ( $res && $row= pdo_fetch_assoc($res) ) {
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
# ---------------------------------------------------------------------------------------- kasa send
# pošle dopis pro $who - pokud je to * tak všem
function kasa_send($whos,$to_send=0) {
  $html= '';
  list($adr,$replyto,$par,$subj,$txt)= select('adr,replyto,par,subj,txt','cron',"batch='rr-note'");
  $adresy= (array)json_decode($adr);
  debug($adresy,'adr');
  $subst= (array)json_decode($par);
  debug($subst,'par');
  $n= 0;
  $whos= $whos=='*' ? array_keys($adresy) : explode(',',$whos);
  foreach ($whos as $who) {
    $par= $subst[$who];
    $txt_par= str_replace('{poznamka}',$par,$txt);
    if ($to_send) {
      $ok= kasa_send_mail("Měsíční připomenutí zápisu zůstatků pokladen",$txt_par,
          $replyto,$adresy[$who],'YMCA Setkání','mail');
      if (!$ok) break;
      $n++;
    }
    else {
      $html.= "<table class='stat'>
        <tr><td>ADRESA <b>$who</b>: $adresy[$who]</td></tr>
        <tr><td>REPLY_TO: $replyto</td></tr>
        <tr><td>PŘEDMĚT: $subj</td></tr>";
      $html.= "<tr><td>$txt_par</td></tr></table><br>";
    }
  }
  if ($to_send) $html.= "<br><br>odesláno $n mailů z ".count($whos);
  return $html;
}
# ------------------------------------------------------------------------------------ kasa send_log
# zobrazí časová razítka
function kasa_send_log($typ,$subj='') {
  $html= '<dl>';
  $rs= pdo_qry("SELECT DATE(kdy),GROUP_CONCAT(TIME(kdy)),pozn  
    FROM stamp WHERE typ='$typ' GROUP BY CONCAT(DATE(kdy),pozn) ORDER BY kdy DESC LIMIT 24");
  while ( $rs && (list($den,$cas,$pozn)= pdo_fetch_row($rs)) ) {
    $html.= "<dt>$den $cas</dt><dd>$pozn</dd>";
  }
  $html.= "</dl>";
  return $html;
}
