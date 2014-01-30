<?php # (c) 2007-2008 Martin Smidek <martin@smidek.eu>
# -------------------------------------------------------------------------------------------------- pipe_pdenik_typ
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
# -------------------------------------------------------------------------------------------------- pipe_pdenik_org
// 1=N 2=R 3=P 4=G
function pipe_pdenik_org ($x,$save=0) {
  if ( $save ) {     // převeď zobrazení na uložení
    $z= $x=='N' ? 1 : ($x=='R' ? 2 : ($x=='P' ? 3 : ($x=='G' ? 4 : 0)));
  }
  else {             // převeď uložení na zobrazení
    $z= $x==1 ? 'N' : ($x==2 ? 'R' : ($x==3 ? 'P' : ($x==4 ? 'G' : '?')));
  }
  return $z;
}
# -------------------------------------------------------------------------------------------------- psc
// doplnění mezery do PSČ
function psc ($psc,$user2sql=0) {
  if ( $user2sql )                            // převeď uživatelskou podobu na sql tvar
    $text= str_replace(' ','',$psc);
  else {                                      // převeď sql tvar na uživatelskou podobu (default)
    $psc= str_replace(' ','',$psc);
    $text= substr($psc,0,3).' '.substr($psc,3);
  }
  return $text;
}
# ================================================================================================== SYSTEM
# -------------------------------------------------------------------------------------------------- ys_import_dat
# import dat pro YS podle $par->cmd==
# 'survay'    -- počty záznamů tabulek OSOBA, TVORI, RODINA, POBYT, SPOLU
# 'MS-pec'    -- EXPORT-pecouni.csv => naplnění OSOBA pečovateli a zařazení na kurz
function ys_import_dat($opt) {  trace();
  # funkce načtení kurzů
  function kurzy(&$err,&$msg,$yy=false,$od_roku=1995) {
    $kurz= array();
    for ($rok= $od_roku; $rok<=2012; $rok++) {
      $qa= "SELECT id_duakce,YEAR(datum_od) AS _rok FROM akce
            WHERE YEAR(datum_od)=$rok AND druh=1";
      $ra= mysql_qry($qa); if ( !$ra ) { $err= "tabulka akcí"; goto end; }
      if ( mysql_num_rows($ra)!=1 ) { $err= "kurz $rok není jednoznačný"; goto end; }
      $oa= mysql_fetch_object($ra);
      $kurz[$yy ? substr($oa->_rok,-2) : $oa->_rok]= $oa->id_duakce;
    }
  end:
    if ( $err ) {
      $msg.= "<br><br>CHYBA: $err";
    }
    return $kurz;
  }
  global $ezer_path_root;
  $msg= $err= '';
  mb_internal_encoding('UTF-8');
  setlocale(LC_ALL, 'en_US.UTF-8');
  switch ($opt->cmd) {
  case 'survey':
    foreach(array('osoba', 'tvori', 'rodina', 'pobyt', 'spolu') as $db) {
      $qt= "SELECT COUNT(*) AS _pocet_ FROM $db";
      $rt= mysql_qry($qt); if ( !$rt ) { $err= "tabulka $db"; goto end; }
      $ot= mysql_fetch_object($rt);
      $msg.= "tabulka $db má {$ot->_pocet_} záznamů<br>";
    }
    break;
  case 'MS-pec': // ------------------------------------------------------------------------ pečouni
    $fname= "$ezer_path_root/ys/data/EXPORT-pecouni.csv";
    $f= fopen($fname, "r");
    if ( !$f ) { $err= "importní soubor $fname neexistuje"; goto end; }
    // načtení kurzů
    $kurz= kurzy($err,$msg,false,2000); if ( $err ) goto end;
    // importní soubor
    $msg.= "Import ze souboru $fname ... ";
//     $poradatel= "CR";
    $poradatel= "YS";
    $line= 0;
    $values= ''; $del= '';
    $akce= 0;
    $o= $n= $p= $i= 0;
//     $min= 1;                    // ladění !!!!!!!!!! od kterého importovat
//     $max= 1;                    // ladění !!!!!!!!!! kolik maximálně importovat
    while (($d= fgetcsv($f, 1000, ";")) !== false) {
      $line++;
      if ( $line<2 ) continue;
                                                        debug($d,$line);
//       if ( $line<$min ) continue; // ladění !!!!!!!!!!
      // 0        1     2     3    4   5  6        7    8   9      10      11
      // příjmení;jméno;ulice;obec;psč;rč;narození;kurz;rok;funkce;telefon;email
      // určení akce, je-li tohoto pořadatele
      if ( $poradatel!=$d[7] ) continue;
//       if ( $n>$max ) break;     // ladění !!!!!!!!!!
      $n++;
      // zjištění akce a společného pobytu pečovatelů
      if ( $akce!=$kurz[$d[8]] ) {
        $akce= $kurz[$d[8]];
        $qp= "SELECT id_pobyt FROM pobyt WHERE id_akce=$akce AND funkce=99";
        $rp= @mysql_qry($qp,0,'-'); if ( !$rp ) { $err= "select pobyt"; goto end; }
        if ( mysql_num_rows($rp)==0 ) {
          // vytvoření pobytu
          $qi= "INSERT INTO pobyt (id_akce,funkce) VALUES ($akce,99)";
          $ri= @mysql_qry($qi,0,'-'); if ( !$ri ) { $err= "insert POBYT"; goto end; }
          $p++;
          $pobyt= mysql_insert_id();
        }
        else {
          $op= mysql_fetch_object($rp);
          $pobyt= $op->id_pobyt;
        }
      }
      // osobní údaje
      $prijmeni= trim($d[0]);
      $jmeno= trim($d[1]);
      $ulice= trim($d[2]);
      $obec= trim($d[3]);
      // psč a stát
      list($stat,$psc)= explode('-',str_replace(" ",'',$d[4]));
      if ( !$psc ) { $psc= $stat; $stat= ''; }
      // rč, narozeni, sex
      $narozeni= $sex= '';
      $rc0= trim($d[5]);
      if ( $rc0 ) {
        if ( strlen($rc0)==10 )
          $rc0= substr($rc0,0,6).'/'.substr($rc0,6,4);
        list($rc,$xxxx)= explode('/',$rc0);
        $narozeni= datum_rc($rc0);
        if ( $rc[2]=='5'||$rc[2]=='6' ) $sex= 2;
                                                        display("narozeni1=$narozeni, rc=$xxxx, sex=$sex");
      }
      elseif ( $d[6] ) {
        $narozeni= sql_date(trim($d[6]),true);
                                                        display("narozeni2=$narozeni");
      }
      if ( !$sex ) {
        $sex= mb_substr($prijmeni,-1)=='á' ? 2 : 1;
      }
      // telefon, email
      $telefon= str_replace(" ",'',$d[10]);
      $email= str_replace(" ",'',$d[11]);
      // funkce
      $funkce= trim($d[9]);
      if ( $funkce ) $funkce= "Personál: $funkce";
      // nalezení v OSOBA
      $qo= "SELECT count(*) AS _pocet,id_osoba,jmeno,prijmeni,rodne,telefon,email,ulice,psc,obec
            FROM osoba LEFT JOIN spolu USING(id_osoba)
            WHERE narozeni='$narozeni' AND jmeno='$jmeno'
            GROUP BY id_osoba ORDER BY _pocet DESC";
      $ro= mysql_qry($qo); if ( !$ro ) { $err= "CHYBA $jmeno $prijmeni"; goto end; }
      if ( $ro && mysql_num_rows($ro)>=1 ) {
        $oo= mysql_fetch_object($ro);
        $o++;
        $msg.= "<br>NALEZEN: $jmeno $prijmeni jako {$oo->jmeno} {$oo->prijmeni}";
        if ( $prijmeni!=$oo->prijmeni && levenshtein($prijmeni,$oo->prijmeni)>2 )
          $msg.= " --- ROZENÁ: $prijmeni (".levenshtein($prijmeni,$oo->prijmeni).")";
        $osoba= $oo->id_osoba;
        $msg.= " -osoba=$osoba";
        $set= array();
        // přidání případných chybějících údajů (rodné,telefon, mail, ...)
        if ( $prijmeni!=$oo->prijmeni && !$oo->rodne ) $set[]= "rodne='$prijmeni'";
        if ( $telefon && !$oo->telefon )        $set[]= "telefon='$telefon'";
        if ( $email && !$oo->email )            $set[]= "email='$email'";
        if ( $ulice && !$oo->ulice )            $set[]= "ulice='$ulice'";
        if ( $psc && !$oo->psc )                $set[]= "psc='$psc'";
        if ( $obec && !$oo->obec )              $set[]= "obec='$obec'";
        // přidání informací
                                                        debug($set,"$prijmeni $jmeno");
        if ( count($set) ) {
          $set= implode(",",$set);
          $qu= "UPDATE osoba SET $set WHERE id_osoba=$osoba";
          $ru= mysql_qry($qu);
        }
      }
      else {
        // vložení do OSOBA, RODINA, TVORI
        $qi= "INSERT INTO osoba (jmeno,prijmeni,sex,ulice,psc,obec,stat,telefon,email,narozeni,rc_xxxx)
              VALUES ('$jmeno','$prijmeni',$sex,'$ulice','$psc','$obec','$stat','$telefon','$email',"
                    ."'$narozeni','$xxxx')";
        $ri= mysql_qry($qi,0,'-'); if ( !$ri ) { $err= "insert OSOBA $jmeno $prijmeni:$qi"; goto end; }
        $i++;
        $osoba= mysql_insert_id();
        $msg.= "<br>VLOŽEN: $jmeno $prijmeni ";
        $msg.= " +osoba=$osoba";
      }
      // vložení SPOLU
      $qi= "INSERT INTO spolu (id_pobyt,id_osoba) VALUES ($pobyt,$osoba)";
      $ri= mysql_qry($qi,0,'-'); if ( !$ri ) { $err= "insert SPOLU $jmeno $prijmeni"; goto end; }
      $spolu= mysql_insert_id();
      $msg.= " spolu=$spolu";
    }
    $msg.= "<br><br>zpracováno $n účastí pečounů z toho $o bylo nalezeno $i vloženo";
    $msg.= "<br><br>vloženo $p pobytů";
    fclose($f);
    break;
  default:
    $msg= "není ještě implementováno";
  }
end:
  if ( $err ) {
    $msg.= "<br><br>CHYBA: $err";
  }
  return $msg;
}
# ================================================================================================== SOUBORY
# -------------------------------------------------------------------------------------------------- google_sheet
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
    // nalezení tabulky
    $gdClient= new Zend_Gdata_Spreadsheets($httpClient);
    $keys->service= $gdClient;
    $feed= $gdClient->getSpreadsheetFeed();
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
# -------------------------------------------------------------------------------------------------- sou_lst
# obsluha menu
function sou_lst($k1,$k2,$k3) {
  global $EZER;
  $title= "Soubory ve složce";
  $html= '';
  $path= '';
  switch ( $k1 ) {
  case 'posta':                                                                     // SOUBORY
    $path= "{$EZER->options->docs_path}/$k3/$k2";
    $ref= "{$EZER->options->docs_ref}/$k3/$k2";
    break;
  }
  $html= "<div class='CSection CMenu'><h3 class='CTitle'>$title</h3>$html</div>";
  $result= (object)array('html'=>$html,'path'=>$path,'ref'=>$ref);
  return $result;
}
# -------------------------------------------------------------------------------------------------- sou_upd_info
# obsluha menu
function sou_upd_info($dir,$file,$info) {
                                                display("sou_upd_info($dir,$file,$info)=$dir/$file.info");
  $length= file_put_contents("$dir/$file.info",$info);
  return $length;
}
# -------------------------------------------------------------------------------------------------- sou_kal
# obsluha menu
function sou_kal($k1,$k2,$k3) {
  $title= "Kalendář";
  $html= '';
  $path= '';
  switch ( $k3 ) {
  case 'ms':
    $html.= '<iframe src="//www.google.com/calendar/embed?height=500&amp;wkst=2&amp;bgcolor=%23FFFFFF&amp;src=martin.smidek%40gmail.com&amp;color=%23A32929&amp;src=iqc1cp54d6trv46pftbalg7qco%40group.calendar.google.com&amp;color=%235229A3&amp;src=ht3jlfaac5lfd6263ulfh4tql8%40group.calendar.google.com&amp;color=%237A367A&amp;src=czech__cs%40holiday.calendar.google.com&amp;color=%23B1365F&amp;ctz=Europe%2FPrague" style=" border-width:0 " width="645" height="500" frameborder="0" scrolling="no"></iframe>';
    break;
  case 'ys':
    $html.= '<iframe src="//www.google.com/calendar/embed?height=500&amp;wkst=2&amp;bgcolor=%23FFFFFF&amp;src=iqc1cp54d6trv46pftbalg7qco%40group.calendar.google.com&amp;color=%235229A3&amp;ctz=Europe%2FPrague" style=" border-width:0 " width="645" height="500" frameborder="0" scrolling="no"></iframe>';
    break;
  }
  $html= "<div class='CSection CMenu'><h3 class='CTitle'>$title</h3>$html</div>";
  $result= (object)array('html'=>$html,'path'=>$path);
  return $result;
}
# ================================================================================================== KASA
# -------------------------------------------------------------------------------------------------- p_pdenik_vytisten
# ask
# doklad je označen jako vytištěný
function p_pdenik_vytisten($id_pokl) {
  $qry= "UPDATE pdenik SET vytisten=1 WHERE id_pokl=$id_pokl";
  $res= mysql_qry($qry);
  return "1";
}
# -------------------------------------------------------------------------------------------------- p_pdenik_insert
# form_make
# $cislo==0 způsobí nalezení nového čísla dokladu
function p_pdenik_insert($typ,$org,$org_abbr,$datum) {
  global $x,$y;
  // převzetí hodnot
                                                          debug(Array($typ,$org,$cislo,$datum),'p_denik_insert');
  $db= $x->db;
  $select= array();
  make_get(&$set,&$select,&$fields);
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
# -------------------------------------------------------------------------------------------------- kasa_menu
# vygeneruje menu pro danou kapitolu ve formátu pro menu_fill
# values:[{group:id,entries:[{entry:id,keys:[k1,...]}, ...]}, ...]
# $chapters (seznam jmen), $section, $class udávají počáteční stav
function kasa_menu($k1,$k2,$k3) {
  return menu_definition($k1,$k2,$k3,<<<__END
    [ {group:'Stavy pokladen',entries:[
        {title:'Aktuální stav pokladen'         ,entry:'aktualne',keys:['stav','aktualne']},
        {title:'Stav pokladen k datu
                aktuálního řádku'               ,entry:'k_datu',keys:['stav','k_datu']},
        {title:'Stav podle nastaveného filtru'  ,entry:'s_filtrem',keys:['stav','s_filtrem']}
      ]},
      {group:'Exporty',entries:[
        {title:'letošní pokladní deník'         ,entry:'exporty',  keys:['export','letos']}
      ]}]
__END
  );
}
# -------------------------------------------------------------------------------------------------- kasa_menu_show
# ki - menu
# $cond = podmínka pro pdenik nastavená ve fis_kasa.ezer
# $day =  má formát d.m.yyyy
function kasa_menu_show($k1,$k2,$k3,$cond=1,$day='') {
  $html= "<div class='CSection CMenu'>";
  switch ( "$k2 $k3" ) {
  case 'stav aktualne':
    $dnes= date('j.n.Y');
    $dnes_mysql= date('Y-m-d');
    $html.= "<h3 class='CTitle'>Aktuální stav pokladen ke dni $dnes</h3>";
    $year= date('Y');
    $interval= " datum BETWEEN '$year-01-01' AND '$dnes_mysql'";
    $html.= kasa_menu_comp($interval);
    break;
  case 'stav s_filtrem':
    $html.= "<h3 class='CTitle'>Stav pokladen podle nastavení </h3>";
    $html.= kasa_menu_comp($cond);
    $html.= "<p><i>filtr: $cond</i></p>";
    break;
  case 'stav k_datu':
    $html.= "<h3 class='CTitle'>Stav pokladen ke dni $day </h3>";
    $until= sql_date1($day,1);
    $year= substr($until,0,4);
    $interval= " datum BETWEEN '$year-01-01' AND '$until'";
    $html.= kasa_menu_comp($interval);
    break;
  case 'export letos':
    $rok= date('Y');
    $html.= "<h3 class='CTitle'>Export pokladních deníků roku $rok</h3>";
    $cond= " datum BETWEEN '$rok-01-01' AND '$rok-12-31'";
    $html.= kasa_export($cond,"pokladna_{$rok}");
    break;
  case 'export vloni':
    $rok= date('Y')-1;
    $html.= "<h3 class='CTitle'>Export pokladních deníků roku $rok</h3>";
    $cond= " datum BETWEEN '$rok-01-01' AND '$rok-12-31'";
    $html.= kasa_export($cond,"pokladna_{$rok}");
    break;
  }
  $html.= "</div>";
  return $html;
}
# -------------------------------------------------------------------------------------------------- kasa_export
function kasa_export($cond,$file) {
                                                display("kasa_export($cond,$file)");
  global $ezer_path_serv, $ezer_path_docs;
  require_once("$ezer_path_serv/licensed/xls/OLEwriter.php");
  require_once("$ezer_path_serv/licensed/xls/BIFFwriter.php");
  require_once("$ezer_path_serv/licensed/xls/Worksheet.php");
  require_once("$ezer_path_serv/licensed/xls/Workbook.php");
  $table= "$file.xls";
  $wb= new Workbook("docs/$table");
  $qry_p= "SELECT * FROM pokladna ";
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
    $qry= "SELECT * FROM pdenik WHERE $cond AND pdenik.org={$p->id_pokladna} ORDER BY datum";
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
# -------------------------------------------------------------------------------------------------- kasa_menu_comp
function kasa_menu_comp($cond) {
  $celkem= 0;
  $html= "<table>";
  $qry= "SELECT nazev, sum(if(typ=2,castka,-castka)) as s, abbr FROM pdenik
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
?>
