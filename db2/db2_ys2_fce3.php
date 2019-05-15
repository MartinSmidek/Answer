<?php # (c) 2009-2015 Martin Smidek <martin@smidek.eu>
//function clear_platby_lk2019() {
//  $ida= 1242;
//  query("
//    UPDATE pobyt SET
//      luzka=0,strava_cel=0,cstrava_cel='',strava_pol=0,cstrava_pol='',
//      kocarek=0,pristylky=0,svp=0,pocetdnu=0,sleva=0,vzorec=0,
//      platba1=0,platba2=0,platba3=0,platba4=0,platba=0
//    WHERE id_akce=1242
//    ");
//}
# ------------------------------------------------------------------------------- update web_changes
# upraví položku pobyt.web_changes hodnotou
# 1/2 pro INSERT/UPDATE pobyt a spolu | 4/8 pro INSERT/UPDATE osoba
function update_web_changes () {
  ezer_connect('ezer_db2');
  // pobyt
  $xs=pdo_qry("
    SELECT klic,op 
    FROM _track JOIN pobyt ON id_pobyt=klic 
    WHERE fld='web_zmena' AND kde='pobyt'
  "); 
  while ($xs && (list($idp,$op)=pdo_fetch_row($xs))) {
    $ch= $op=='i' ? 1 : 2;
    query("UPDATE pobyt SET web_changes=web_changes|$ch WHERE id_pobyt=$idp");
  }
  // osoba
  $xs=pdo_qry("
    SELECT klic,op,id_pobyt FROM _track 
    JOIN osoba ON id_osoba=klic 
    JOIN spolu USING (id_osoba)
    JOIN pobyt USING (id_pobyt)
    WHERE fld='web_zmena' AND kde='osoba' AND web_changes>0
  "); 
  while ($xs && (list($ido,$op,$idp)=pdo_fetch_row($xs))) {
    $ch= $op=='i' ? 4 : 8;
    query("UPDATE pobyt SET web_changes=web_changes|$ch WHERE id_pobyt=$idp");
  }
  return 1;
}
/** ===================================================================================> FILEBROWSER */
# ------------------------------------------------------------------------------------ tut ma_archiv
// je volané metodou show.load - vrátí informace, zda existuje archiv akce v Synology
function tut_ma_archiv ($table,$idkey,$keys,$root) {
  $values= array();
  foreach ($keys as $key) {
    list($kod,$rok)= select(
        'IF(ga2.g_kod,ga2.g_kod,da2.ciselnik_akce),YEAR(datum_od)',
        "akce AS da2 LEFT JOIN join_akce AS ja2 ON ja2.id_akce=da2.id_duakce 
	LEFT JOIN g_akce AS ga2 USING(g_rok,g_kod) ",
        "$idkey=$key");
    $y= tut_dir_find ($root,$rok,$kod);
    $values[]= $y->ok ? 1 : 0;
  }
  return $values;
}
# ---------------------------------------------------------------------------------------- tut mkdir
// vytvoří adresář
function tut_mkdir ($root,$rok,$kod,$slozka,$podslozka='') {  trace();
  $base= "{$root}Akce/$rok";
  if ( !$podslozka ) { 
    // základní složka archivu - odstraň zakázané znaky (Win,Mac,Linux)
    $slozka= strtr($slozka,'\/:*"<>|?%',"----'()---");
    // základní složka
    if ( !is_dir($base)) { 
      // případně založ rok
      mkdir($base);
                                                display("založen rok $base");
    }
    $y= tut_dir_find($root,$rok,$kod);
    if ( $y->ok==0 ) {
      // akce s tímto kódem ještě nemá složku
      $path= "$base/$kod - $slozka";       
    }
    else {
      fce_warning("POZOR: archiv akce již existuje: $base/$kod ...");
      goto end;
    }
  }
  else {
    // podsložka - odstraň zakázané znaky
    $podslozka= strtr($podslozka,'\/:*"<>|?%',"----'()---");
    $path= "$base/$slozka/$podslozka";
  }
  // vlastní vytvoření složky
  if ( stristr(PHP_OS,'WIN') && substr(PHP_VERSION_ID,0,1)=='5' ) 
    // windows a PHP5 používají cp1250
    $path= iconv("UTF-8","Windows-1250",$path);
                                                display("path=$path");
  $ok= mkdir($path) ? 1 : 0;
end:
  return $ok;
}
# ------------------------------------------------------------------------------------- tut dir_find
// nalezne adresář akce
function tut_dir_find ($root,$rok,$kod) {  trace();
  $y= (object)array('ok'=>1);
  $patt= "{$root}Akce/$rok/$kod*";
  $fs= simple_glob($patt);
  $file= $fs[0];
                                                debug($fs,$patt);
  if ( count($fs)==1 ) {
    if ( stristr(PHP_OS,'WIN') && substr(PHP_VERSION_ID,0,1)=='5' ) // windows
      $file= iconv("Windows-1250","UTF-8",$file);  
    $y->aroot= "{$root}Akce/$rok/";
    $y->droot= mb_substr(strrchr($file,'/'),1);
  }
  else {
    $y->ok= count($fs);
  }
                                                debug($y,strrchr($fs[0],'/'));
  return $y;
}
# ---------------------------------------------------------------------------------------- tut files
// vrátí soubory adresáře
function tut_files ($root,$rel_path) {  trace();
  global $ezer_path_root, $rel_root;
  $abs_path= "$root/$rel_path";
  $html= '';
  if ( $rel_path && is_dir($abs_path) ) {
    $files= array();
    $folders= array();
    if (($dh= opendir($abs_path))) {
      while (($file= readdir($dh)) !== false) {
        if ( $file!='.') {
          if ( is_dir("$abs_path/$file") ) {
            if ( stristr(PHP_OS,'WIN') && substr(PHP_VERSION_ID,0,1)=='5' ) // windows
              $file= iconv("Windows-1250","UTF-8",$file);  
            $folders[]= "<li>[$file]</li>";
          }
          else {
            if ( stristr(PHP_OS,'WIN') && substr(PHP_VERSION_ID,0,1)=='5' ) // windows
              $file= iconv("Windows-1250","UTF-8",$file);  
//            $afile= "<a href='$rel_path/$file' target='doc'>$file</a>";
            $cmd= "Ezer.run.$._call(0,'db2.akce2.lst.page.files.Menu','viewer','$file','$abs_path');";
            $onclick= "onclick=\"$cmd; return false;\"";
            $onright= "oncontextmenu=\"Ezer.fce.contextmenu([
              ['stáhnout',function(el){ $cmd }]
              ],arguments[0],null,null,this);return false;\"";
            $files[]= "<li class='file' $onclick $onright>$file</li>";
          }
        }
      }
      closedir($dh);
    }
    $html= 
        "<ul style='list-style-type:none;padding:0;margin:0'>"
          .implode('',array_merge($folders,$files))
        .'</ul>';
  }
  return $html;
}
# ------------------------------------------------------------------------------------- tut file_url
// vrátí soubory adresáře
function tut_file_url ($dir,$name) {  trace();
  global $ezer_root;
  $_SESSION[$ezer_root]['path_file']= $dir;
  $url= "db2/file.php?title=$name";
  return $url;
}
# ------------------------------------------------------------------------------------------ tut dir
// vrátí adresářovou strukturu pro zobrazení metodou area.tree_show
//   node:  {prop:{text:<string>,down:nodes}}
//   nodes: [ node, ... ]
function tut_dir ($base,$folder) {  trace();
  $tree= null;
  if ( $base && $folder ) {
    if ( stristr(PHP_OS,'WIN') && substr(PHP_VERSION_ID,0,1)=='5' ) // windows
      $folder= iconv("UTF-8","Windows-1250",$folder);
    $tree= tut_dir_walk ($base,$folder);
  //                                                  debug($tree);
  }
  return $tree;
}
function tut_dir_walk($base,$root) {  trace();
  $path= $base.$root;
//                                                  display("is_dir($path)=".is_dir($path));
  if ( is_dir($path) ) {
    $files= array();
    if (($dh= opendir($path))) {
      while (($file= readdir($dh)) !== false) {
        if ( $file!='.' && $file!='..' ) {
          $subtree= tut_dir_walk($path.'/',$file);
          if ( $subtree )
            $files[]= $subtree;
        }
      }
      closedir($dh);
    }
    if ( stristr(PHP_OS,'WIN') && substr(PHP_VERSION_ID,0,1)=='5' ) // windows
      $root= iconv("Windows-1250","UTF-8",$root);  
    return (object)array('prop'=>(object)array('id'=>$root),'down'=>$files);
  }
  else {
    return null;
  }
}
# ------------------------------------------------------------------------------------------ session
# getter a setter pro _SESSION
function session($is,$value=null) {
  $i= explode(',',$is);
  if ( is_null($value) ) {
    // getter
    switch (count($i)) {
    case 1: $value= $_SESSION[$i[0]]; break;
    case 2: $value= $_SESSION[$i[0]][$i[1]]; break;
    case 3: $value= $_SESSION[$i[0]][$i[1]][$i[2]]; break;
    }
  }
  else {
    // setter
    switch (count($i)) {
    case 1: $_SESSION[$i[0]]= $value; break;
    case 2: $_SESSION[$i[0]][$i[1]]= $value; break;
    case 3: $_SESSION[$i[0]][$i[1]][$i[2]]= $value; break;
    }
//    session_commit();
    $value= 1;
  }
  return $value;
}
/** ===========================================================================================> VPS **/
# ------------------------------------------------------------------------------------- vps historie
# 
function vps_historie ($org,$par,$export) {
  $cert= array(); // certifikát rok=>poslední číslo
  $letos= date('Y');
  list($mez1,$mez2)= explode(',',$par->parm);
  $hrana1= $letos - $mez1;
  $hrana2= $letos - $mez2;
  $vps1= $org==1 ? '3,17' : '3';
  // pole pro tabulku
  $clmn= $css= array();
// sloupce
  $tits= array("pár:26","poprvé:10","kolikrát:10","naposledy:10",
            $org==1?"VPS1:10":"1.školení:10",
            "VPS2");
  $flds= array('jm','od','n','do','vps_i','vps2');
  // sloupce
  $tits= array("pár:26","kolikrát:10","VPS2",'skupinky');
  $flds= array('jm','n','vps2','hodn');
  // doplnění nadpisů historie
  for ($r=$letos; $r>=$hrana2; $r--) {
    $r2= $r%100;
    array_push($tits,"$r2");
    array_push($flds,$r2);
  }
  // seznam VPS a základní údaje
  $rx= pdo_qry("SELECT
      r.id_rodina,r.nazev,
      GROUP_CONCAT(DISTINCT IF(t.role='a',o.jmeno,'') SEPARATOR '') as jmeno_m,
      GROUP_CONCAT(DISTINCT IF(t.role='b',o.jmeno,'') SEPARATOR '') as jmeno_z,
      MIN(IF(druh=1 AND funkce=1,YEAR(datum_od),9999)) AS OD,
      CEIL(CHAR_LENGTH(
        GROUP_CONCAT(DISTINCT IF(druh=1 AND funkce=1,YEAR(datum_od),'') SEPARATOR ''))/4) AS Nx,
      MAX(IF(druh=1 AND funkce=1,YEAR(datum_od),0)) AS DO,
      MIN(IF(druh IN ($vps1),YEAR(datum_od),9999)) as VPS_I,
      1
    FROM rodina AS r
    JOIN pobyt AS p
    JOIN akce as a ON id_akce=id_duakce
    JOIN tvori AS t USING (id_rodina)
    JOIN osoba AS o USING (id_osoba)
    WHERE spec=0 AND r.id_rodina=i0_rodina AND a.access&$org
      AND druh IN (1,$vps1)
      --  AND r.id_rodina=3329 
    GROUP BY r.id_rodina
    ORDER BY r.nazev");
  while ( $rx && ($x= pdo_fetch_object($rx)) ) {
    $idr= $x->id_rodina;
    // číslování certifikátů
    $skola= $x->VPS_I==9999 ? 0 : $x->VPS_I;
    $c1= $c2= '';
    if ( $skola ) {
      if ( !isset($cert[$skola]) ) $cert[$skola]= 0;
      $cert[$skola]++; $c1= ($org==1?'vps':'pps')."_$skola/{$cert[$skola]}";
      $cert[$skola]++; $c2= ($org==1?'vps':'pps')."_$skola/{$cert[$skola]}";
    }
    // ohlídání období
    if ( $x->DO<$hrana1 ) continue;
    $cclen= $_cinny_od ?: '-';
    // odpověď 1
    $cl= array(
      'jm'=>"{$x->jmeno_m} a {$x->jmeno_z} {$x->nazev}",
      'od'=>$x->OD,'n'=>$x->Nx,'do'=>$x->DO,
      'vps_i'=>$skola ?: '-'
    );
    // doplnění odpověďi podle mez2+1 běhů
    if ( $org==1 
//        && $idr==3329 
        ) {
      // celkový počet účastí na akcích pro činné členy
      $cl['vps2']= select('COUNT(*)','akce JOIN pobyt ON id_akce=id_duakce',
          "akce.druh=3 AND nazev RLIKE 'VPS *(2|II)' AND i0_rodina=$idr");
    }
    $clmn[$idr]= $cl;
  }
  // doplnění průběhu ročních běhů
  for ($r=$letos; $r>=$hrana2; $r--) {
    $r2= $r%100; $lk= "$r2/L"; $a[0]= "$r2/P"; $a[1]= "$r2/J"; $r1= $r+1;
    $ida_lk= select('id_duakce','akce',"access=$org && druh=1 AND YEAR(datum_od)=$r");
    $ida[0]= select('id_duakce','akce',"access=$org && druh=2 AND datum_od BETWEEN '$r-09-01' AND '$r-12-31'")?:0;
    $ida[1]= select('id_duakce','akce',"access=$org && druh=2 AND datum_od BETWEEN '$r1-01-01' AND '$r1-05-31'")?:0;
    // získej skupinky LK daného roku pro sledované VPS
    $skups= array(); // idr_vps -> [idr_ucastnik,...]
    $rs= pdo_qry("
      SELECT GROUP_CONCAT(i0_rodina ORDER BY IF(funkce=1,1,0) DESC) AS _skup
      FROM pobyt AS p
      WHERE id_akce=$ida_lk AND skupina>0
      GROUP BY skupina
      ORDER BY skupina");
    while ( $rs && (list($skup)= pdo_fetch_array($rs)) ) {
      $idrs= explode(',',$skup);
      $vps= array_shift($idrs);
      if ( isset($clmn[$vps]) )
        $skups[$vps]= $idrs;
    }
    // ohodnoť přítomnost na obnovách: 3=všichni; 2=někteří; 1=žádní
    $ox= array();
    for ($i= 0; $i<=1; $i++ ) {
      $idrs= select("GROUP_CONCAT(i0_rodina)",'pobyt',"id_akce=$ida[$i]");
      $ucast= explode(',',$idrs);
      foreach ($skups as $vps => $skup) {
        if ( $idrs ) {
          $jo= $ne= 0;
          foreach ($skup as $idr) {
            if ( in_array($idr,$ucast) ) 
              $jo++;
            else 
              $ne++;
          }
          $clmn[$vps][$r2].= 
              $jo && !$ne ? 3 : ( $jo && $ne ? 2 : (!$jo && $ne ? 1 : 0));
        }
        else {
          $clmn[$vps][$r2].= '0'; 
        }
      }
    }
  }
  // pokus o bodovací systém
  $bodys= array(
      'A' => '3:33,32,23,13,30',
      'B' => '2:22,12,20',
      'C' => '1:11,21,10',
      'X' => '0:00'
  );
  // pokus o bodovací systém
  // 3=celá skupinka, 2=částečná, 1=nikdo, 0=obnova ještě nebyla nebo VPS nepřijela
  $note= "Písmena ABC charakterizují účast na podzimní a letní obnově, jsou určena takto:";
  $bodys= array(
      'A' => '3:33,23,13,30',
      'B' => '2:32,22,12,20',
      'C' => '1:11,21,31,10',
      'X' => '0:00'
  );
  $body= $hodn= array(); // 32 -> 'B' ... 2
  foreach ($bodys as $b=>$hvs) {
    list($h,$vs)= explode(':',$hvs);
    $note.= "<br>$b = ".strtr($vs,array(3=>'c',2=>'č',1=>'n',0=>'-',','=>' ... '));
    foreach (explode(',',$vs) as $v) {
      $body[$v]= $b;
      $hodn[$v]= $h;
    }
  }
  $note.= "<br>kde c znamená, že na obnově byla celá skupinka, č znamená částečná, "
      . "n znamená nikdo nepřijel, - znamená nepřijela VPS<br><br>"
      . "Ve sloupci 'skupinky' je jakási průměrná známka - POZOR! na účast párů často VPS nemůže mít vliv (nemoc, těhotenství,...)<br><br>";
  foreach ($clmn as $vps => $cl) {
    $n= $h= 0; 
    for ($r=$letos; $r>=$hrana2; $r--) {
      $r2= $r%100; 
      if ( isset($cl[$r2]) ) {
        $n++;
        $h+= $hodn[$clmn[$vps][$r2]];
        $clmn[$vps][$r2]= strtr($clmn[$vps][$r2],$body);
      }
    }
    if ( $n ) {
      $znamka= 4-round($h/$n,1);
      $clmn[$vps]['hodn']= $znamka;
//      if ( $znamka > 2 ) {
//        $css[$vps]['hodn']= 'yellow';
//      }
    }
  }
  return vps_table($tits,$flds,$clmn,'hodn',$export,false,$note);
}
# ------------------------------------------------------------------------------------=> . vps table
# $css_or_sort = string znamená řazení podle toho sloupce, pole znamená aplikaci css
function vps_table($tits,$flds,$clmn,$css_or_sort,$export=false,$row_numbers=false,$note='') {  
  $ret= (object)array('html'=>'');
  // zobrazení tabulkou
  $tab= '';
  $thd= '';
  $n= 0;
  if ( $export ) {
    $ret->tits= $tits;
    $ret->flds= $flds;
    $ret->clmn= $clmn;
  }
  else {
    // případné řazení
    if ( is_string($css_or_sort)) {
      usort($clmn, function($a, $b) use ($css_or_sort) {
        return ($a[$css_or_sort] < $b[$css_or_sort]) ? -1 : 1;
      });
    }
    $fmt= array();
    // písmena sloupců
    if ( $row_numbers ) {
      $ths.= "<th> </th>";
      for ($a= 0; $a<count($tits); $a++) {
        $id= chr(ord('A')+$a);
        $ths.= "<th>$id</th>";
      }
      $ths.= "</tr><tr>";
    }
    // titulky
    if ( $row_numbers )
      $ths.= "<th>1</th>";
    foreach ($tits as $i=>$idw) {
      list($id,$len,$f)= explode(':',$idw);
      $ths.= "<th>$id</th>";
      if ( $f ) $fmt[$flds[$i]]= $f;
    }
    foreach ($clmn as $i=>$c) {
      $c= (array)$c;
      $tab.= "<tr>";
      if ( $row_numbers )
        $tab.= "<th>".($i+2)."</th>";
      foreach ($flds as $f) {
        $class= is_array($css_or_sort) && isset($css_or_sort[$i][$f]) 
            ? "class='{$css_or_sort[$i][$f]}'" : '';
        if ( $f=='id_osoba' || $f=='^id_osoba' )
          $tab.= "<td style='text-align:right'>".tisk2_ukaz_osobu($c[$f])."</td>";
        elseif ( $f=='^id_rodina' )
          $tab.= "<td style='text-align:right'>".tisk2_ukaz_rodinu($c['^id_rodina'])."</td>";
        elseif ( $f=='^id_pobyt' )
          $tab.= "<td style='text-align:right'>".tisk2_ukaz_pobyt($c['^id_pobyt'])."</td>";
        elseif ( is_numeric($c[$f]) || $fmt[$f]=='d' ) 
          $tab.= "<td $class style='text-align:right'>{$c[$f]}</td>";
        else {
          $tab.= "<td $class style='text-align:left'>{$c[$f]}</td>";
        }
      }
      $tab.= "</tr>";
      $n++;
    }
    $ret->html= "{$note}Seznam má $n řádků<br><br><div class='stat'>
      <table class='stat'><tr>$ths</tr>$tab</table></div>";
  }
  return $ret;
}
/** ======================================================================================> POKLADNA **/
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
                                                          debug(Array($typ,$org,$cislo,$datum),'p_denik_insert');
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
function kasa_menu_show($k1,$k2,$k3,$cond=1,$day='',$db='ezer_db2') {
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
    $html.= "<div class='karta'>Export pokladních deníků roku $rok</div>";
    $cond= " datum BETWEEN '$rok-01-01' AND '$rok-12-31'";
    $html.= kasa_export($cond,"pokladna_{$rok}",$db);
    break;
  case 'export vloni':
    $rok= date('Y')-1;
    $html.= "<div class='karta'>Export pokladních deníků roku $rok</div>";
    $cond= " datum BETWEEN '$rok-01-01' AND '$rok-12-31'";
    $html.= kasa_export($cond,"pokladna_{$rok}",$db);
    break;
  }
  return $html;
}
# -------------------------------------------------------------------------------------- kasa export
function kasa_export($cond,$file,$db) {
                                                display("kasa_export($cond,$file)");
  global $ezer_path_serv, $ezer_path_docs;
  require_once("$ezer_path_serv/licensed/xls/OLEwriter.php");
  require_once("$ezer_path_serv/licensed/xls/BIFFwriter.php");
  require_once("$ezer_path_serv/licensed/xls/Worksheet.php");
  require_once("$ezer_path_serv/licensed/xls/Workbook.php");
  $table= "$file.xls";
  $wb= new Workbook("docs/$table");
  $qry_p= "SELECT * FROM $db.pokladna ";
  $res_p= pdo_qry($qry_p);
  while ( $res_p && $p= pdo_fetch_object($res_p) ) {
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
    $qry= "SELECT * FROM $db.pdenik WHERE $cond AND pdenik.org={$p->id_pokladna} ORDER BY datum";
    $res= pdo_qry($qry);
    while ( $res && $d= pdo_fetch_object($res) ) {
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
/** ===========================================================================================> DŮM **/
# ---------------------------------------------------------------------------------- ds compare_list
function ds_compare_list($orders) {  #trace('','win1250');
  $errs= 0;
  $html= "<dl>";
  $n= 0;
  if ( $orders ) {
    foreach (explode(',',$orders) as $order) {
      $x= ds_compare($order);
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
# --------------------------------------------------------------------------------------- ds compare
function ds_compare($order) {  #trace('','win1250');
  ezer_connect('setkani');
  // údaje z objednávky
  $qry= "SELECT * FROM setkani.tx_gnalberice_order WHERE uid=$order";
  $res= pdo_qry($qry);
  if ( !$res ) fce_error(/*w*u*/("$order není platné číslo objednávky"));
  $o= pdo_fetch_object($res);
  // projití seznamu
  $qry= "SELECT * FROM setkani.ds_osoba WHERE id_order=$order ";
  $reso= pdo_qry($qry);
  $n= $n_0= $n_3= $n_9= $n_15= $n_a= $noroom= 0;
  while ( $reso && $u= pdo_fetch_object($reso) ) {
    // rozdělení podle věku
    $n++;
    $vek= ds_vek($u->narozeni,$o->fromday);
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
# ===========================================================================================> hosté
# -------------------------------------------------------------------------------------- ds kli_menu
# vygeneruje menu pro loňský, letošní a příští rok ve tvaru objektu pro ezer2 pro zobrazení klientů
# určující je datum zahájení pobytu v objednávce
function ds_kli_menu() {
  ezer_connect('setkani');
  $the= '';                     // první v tomto měsíci či později
  $rok= date('Y');
  $ted= date('Ym');
  $mesice= array(1=>'leden','únor','březen','duben','květen','červen',
    'červenec','srpen','září','říjen','listopad','prosinec');
  $mn= (object)array('type'=>'menu.left'
      ,'options'=>(object)array(),'part'=>(object)array());
  $letos= date('Y');
  for ($y= -1; $y<=1; $y++) {
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
      $res= pdo_qry($qry);
      while ( $res && $o= pdo_fetch_object($res) ) {
        $uids.= "$del{$o->uid}"; $del= ',';
        $objednavek++;
        $celkem+= $o->celkem;
      }
      $qryp= "SELECT count(*) as klientu FROM ds_osoba
             WHERE  FIND_IN_SET(id_order,'$uids')";
      $resp= pdo_qry($qryp);
      if ( $resp && $op= pdo_fetch_object($resp) ) {
        $klientu= $op->klientu;
      }
      $tit= /*w*u*/($mesice[$m])." - $celkem ($klientu)";
      $par= (object)array('od'=>$od,'do'=>$do,
        'celkem'=>$celkem,'klientu'=>$klientu,'objednavek'=>$objednavek,'uids'=>$uids,
        'mesic_rok'=>/*w*u*/($mesice[$m])." $rok");
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
# ======================================================================================> objednávky
# ------------------------------------------------------------------------------------ ds objednavka
# vrátí ID objednávky pokud existuje k této akce
function ds_objednavka($ida) {
  $order= 0;
  list($rok,$kod)= select('g_rok,g_kod','join_akce',"id_akce=$ida",'ezer_db2');
  if ( $kod ) {
    $order= select('uid','tx_gnalberice_order',
        "akce=$kod AND YEAR(FROM_UNIXTIME(fromday))=$rok",'setkani');
    $order= $order ? $order : 0;
  }
  return $order;
}
# ------------------------------------------------------------------------------------- ds import_ys
# naplní seznam účastníky dané akce
function ds_import_ys($order,$clear=0) {
  $ret= (object)array('html'=>'','conf'=>'');
  list($rok,$kod,$from,$until,$strava)= 
      select('YEAR(FROM_UNIXTIME(fromday)),akce,FROM_UNIXTIME(fromday),FROM_UNIXTIME(untilday),board',
          'setkani.tx_gnalberice_order',"uid=$order",'setkani');
  if ( $kod ) {
    // objednávka má definovaný kód akce
    ezer_connect('ezer_db2',true);
    $ida= select('id_akce','ezer_db2.join_akce',"g_kod=$kod AND g_rok=$rok",'ezer_db2');
    // zjistíme, zda je objednávka bez lidí
    ezer_connect('setkani',true);
    $pocet= select('COUNT(*)','ds_osoba',"id_order=$order",'setkani');
    if ( $pocet && $clear ) {
      query("DELETE FROM ds_osoba WHERE id_order=$order",'setkani');
      $ret->html.= "Seznam účastníků pobytu byl vyprázdněn. ";
    }
    if ( $pocet && !$clear ) {
      $ret->conf= "Seznam účastníků pobytu obsahuje $pocet lidí - mám jej vyprázdnit a načíst 
          z akce YMCA Setkání? (Pozor, případné přiřazení pokojů, lůžek a strav bude zapomenuto)";
      $ret->html= "Seznam účastníků pobytu nebyl změněn";
      goto end;
    }
    // projdeme účastníky v ezer_db2 a přeneseme společné údaje
    // a potom prijmeni,jmeno,narozeni,psc,obec,ulice,email,telefon 
    $uc= array();
    ezer_connect('ezer_db2',true);
    $rp= pdo_qry("
      SELECT s.id_osoba,prijmeni,jmeno,narozeni,
        IF(adresa,o.psc,r.psc) AS psc, 
        IF(adresa,o.obec,r.obec) AS obec,
        IF(adresa,o.ulice,r.ulice) AS ulice,
        IF(kontakt,o.email,r.emaily) AS email,
        IF(kontakt,o.telefon,r.telefony) AS telefon,
        IFNULL(nazev,prijmeni) AS rod
      FROM pobyt AS p
      JOIN spolu AS s USING (id_pobyt)
      JOIN osoba AS o USING (id_osoba)
      LEFT JOIN tvori AS t USING (id_osoba)
      LEFT JOIN rodina AS r ON r.id_rodina=IF(p.i0_rodina,p.i0_rodina,t.id_rodina)
      WHERE id_akce=$ida 
      GROUP BY id_osoba ORDER BY rod, narozeni
    ");
    while ($rp && $o= pdo_fetch_object($rp)) {
      $uc[]= $o;
    }
    // doplnění účastníků do objednávky
    ezer_connect('setkani',true);
    foreach ( $uc as $o ) {
      $ido= $o->id_osoba;
      $ds_osoba= select('id_osoba','ds_osoba',"ys_osoba=$ido AND id_order=$order",'setkani');
      if ( !$ds_osoba ) {
        $rod= substr(cz2ascii($o->rod),0,3);
        $prijmeni= uw($o->prijmeni);
        $jmeno= uw($o->jmeno);
        $obec=  uw($o->obec);
        $ulice= uw($o->ulice);
        query("INSERT INTO setkani.ds_osoba 
          (id_order,ys_osoba,rodina,prijmeni,jmeno,narozeni,psc,obec,
           ulice,email,telefon,fromday,untilday,strava) VALUES
          ($order,$ido,'$rod','$prijmeni','$jmeno','$o->narozeni','$o->psc','$obec',
           '$ulice','$o->email','$o->telefon','$from','$until',$strava)
        ",'setkani');
//        break;
      }
    }
    $ret->html.= "Seznam účastníků pobytu byl načten z akce YMCA Setkání";
  }
  else {
    $ret->html.= "Akce YMCA Setkání musí mít vyplněný kód akce (vedle stavu objednávky)";
  }
end:  
  return $ret;
}
# ------------------------------------------------------------------------------------ ds rooms_help
# vrátí popis pokojů
function ds_rooms_help($version=1) {
  $hlp= array();
  ezer_connect('setkani');
  $qry= "SELECT number,note
         FROM setkani.tx_gnalberice_room
         WHERE  NOT deleted AND NOT hidden AND version=1";
  $res= pdo_qry($qry);
  while ( $res && $o= pdo_fetch_object($res) ) {
    $hlp[]= (object)array('fld'=>"q$o->number",'hlp'=>wu($o->note));
  }
//                                                         debug($hlp);
  return $hlp;
}
# -------------------------------------------------------------------------------------- ds cen_menu
# vygeneruje menu pro loňský, letošní a příští rok ve tvaru objektu pro menu.group
# ve tvaru item {title:'2016',par:°{rok:'2016'} }
function ds_cen_menu($tit='Ceny roku') {


  $gr= (object)array(
    'type'=>'menu.group',
    'options'=>(object)array('title'=>$tit,'part'=>(object)array())
  );

    $rok= 2018;
    $par= (object)array('rok'=>$rok);
    $tm= (object)array('type'=>'item','options'=>(object)array('title'=>$rok,'par'=>$par));
    $gr->part->$iid= $tm;



  $result= (object)array('th'=>$the,'cd'=>$mn);
  return $result;
}
# -------------------------------------------------------------------------------------- ds obj_menu
# vygeneruje menu pro loňský, letošní a příští rok ve tvaru objektu pro ezer2 pro zobrazení objednávek
# určující je datum zahájení pobytu v objednávce
function ds_obj_menu() {
  global $pdo_db;
  $stav= map_cis('ds_stav');
  $the= '';                     // první objednávka v tomto měsíci či později
//                                      debug($stav,'ds_obj_menu',(object)array('win1250'=>1));
  $mesice= array(1=>'leden','únor','březen','duben','květen','červen',
    'červenec','srpen','září','říjen','listopad','prosinec');
  $mn= (object)array('type'=>'menu.left'
      ,'options'=>(object)array(),'part'=>(object)array());
  $start= date('m') <= 6 ? date('Y')-1 : date('Y');
  $ted= date('Ym');
  ezer_connect('setkani');
  for ($y= 0; $y<=2; $y++) {
    for ($m= 1; $m<=12; $m++) {
      $mm= sprintf('%02d',$m);
      $yyyy= $start+$y;
      $group= "$yyyy$mm";
      $gr= (object)array('type'=>'menu.group'
        ,'options'=>(object)array('title'=>($mesice[$m])." $yyyy"),'part'=>(object)array());
      $mn->part->$group= $gr;

      $from= mktime(0,0,0,$m,1,$yyyy);
      $until= mktime(0,0,0,$m+1,1,$yyyy);
      $qry= "/*ds_obj_menu*/SELECT uid,fromday,untilday,state,name,state FROM setkani.tx_gnalberice_order
             WHERE  NOT deleted AND NOT hidden AND untilday>=$from AND $until>fromday";
//              JOIN ezer_ys._cis ON druh='ds_stav' AND data=state
      $res= pdo_qry($qry);
      while ( $res && $o= pdo_fetch_object($res) ) {
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
# ----------------------------------------------------------------------------------------- pin_make
# vytvoř PIN k číslu x
function pin_make ($x) {
  // jediná kolize pro čísla 1..2000
  $str= str_pad($x,6,'0',STR_PAD_LEFT);
  $pin= substr(sprintf("%u",crc32(strrev($str))),-6);
  return $pin;
}
# ----------------------------------------------------------------------------------------- pin_test
# test PIN - doplníme ho nulami zleva
function pin_test ($x,$pin) {
  $str= str_pad($x,6,'0',STR_PAD_LEFT);
  $strpin= str_pad($pin,6,'0',STR_PAD_LEFT);
  $xpin= substr(sprintf("%u",crc32(strrev($str))),-6);
  return $strpin==$xpin?1:0;
}
# ==========================================================================================> rodina
# ------------------------------------------------------------------------------------------ lide_ms
# SELECT autocomplete - výběr z databáze db2:rodina+členi
function lide_ms($patt) {  #trace('','win1250');
  $a= array();
  $limit= 10;
  $n= 0;
  // rodina
  $qry= "SELECT access,id_rodina AS _key,concat(nazev,' - ',obec) AS _value
         FROM rodina
         WHERE nazev LIKE '$patt%' AND deleted='' ORDER BY nazev LIMIT $limit";
  $res= pdo_qry($qry);
  while ( $res && $t= pdo_fetch_object($res) ) {
    if ( ++$n==$limit ) break;
    $key= $t->_key;
    $org= $t->access==1 ? 'S' : ( $t->access==2 ? 'F' : '*');
    $a[$key]= "$org:{$t->_value}";
  }
  // obecné položky
  if ( !$n )
    $a[0]= /*w*u*/("... žádné jméno nezačíná '")."$patt'";
  elseif ( $n==$limit )
    $a[-999999]= /*w*u*/("... a další");
//                                                      debug($a,$patt,(object)array('win1250'=>1));
  return $a;
}
# ------------------------------------------------------------------------------------------ lide_ms
# SELECT autocomplete - výběr z databází MS (Miloš, Lída)
//function xxxlide_ms($patt) {  #trace('','win1250');
//  $a= array();
//  $limit= 10;
//  $n= 0;
//  // rodiče
//  $qry= "SELECT source,cislo AS _key,concat(jmeno,' ',jmeno_m,' a ',jmeno_z,' - ',mesto) AS _value
//         FROM ms_pary
//         WHERE jmeno LIKE '$patt%' ORDER BY jmeno LIMIT $limit";
//  $res= mysql_qry($qry);
//  while ( $res && $t= mysql_fetch_object($res) ) {
//    if ( ++$n==$limit ) break;
//    $key= "{$t->_key}".($t->source=='L'?0:1);
//    $a[$key]= "{$t->source}:{$t->_value}";
//  }
//  // obecné položky
//  if ( !$n )
//    $a[0]= /*w*u*/("... žádné jméno nezačíná '")."$patt'";
//  elseif ( $n==$limit )
//    $a[-999999]= /*w*u*/("... a další");
////                                                                 debug($a,$patt,(object)array('win1250'=>1));
//  return $a;
//}
# ------------------------------------------------------------------------------------------- rodina
# formátování autocomplete - verze pro db2
function rodina($idr) {  #trace('','win1250');
  $rod= array();
  // členové rodiny
  ezer_connect('ezer_db2');
  $rc= pdo_qry("
    SELECT
      IF(o.adresa,o.ulice,r.ulice) AS _ulice,
      IF(o.adresa,o.psc,r.psc) AS _psc,
      IF(o.adresa,o.obec,r.obec) AS _obec,
      IF(o.adresa,o.stat,r.stat) AS _stat,
      IF(o.kontakt,o.telefon,r.telefony) AS _telefon,
      IF(o.kontakt,o.email,r.emaily) AS _email,
      prijmeni,jmeno,narozeni,rc_xxxx,sex
    FROM rodina AS r
    JOIN tvori AS t USING (id_rodina)
    JOIN osoba AS o USING (id_osoba)
    WHERE id_rodina=$idr AND o.deleted='' AND r.deleted=''
    ORDER BY t.role
  ");
  while ( $rc && $c= pdo_fetch_object($rc) ) {
    $narozeni= sql_date1($c->narozeni);
    $rodcis= rodcis($c->narozeni,$c->sex).$c->rc_xxxx;
    $roky= roku($rodcis);
    $rod[]= (object)array('prijmeni'=>$c->prijmeni,'jmeno'=>$c->jmeno,'stari'=>$roky,
      'psc'=>$c->_psc,'mesto'=>$c->_obec,'ulice'=>$c->_ulice,
      'telefon'=>$c->_telefon,'email'=>$c->_email,'narozeni'=>$narozeni);
  }
  return $rod;
}
function rodcis($nar,$sex) {
  $m= substr($nar,5,2);
  $m= str_pad($m + ($sex==2 ? 50 : 0), 2, '0', STR_PAD_LEFT);
  $rc= substr($nar,2,2).$m.substr($nar,8,2);
  return $rc;
}
function roku($rc) {
  $r= rc2roky($rc);
  $roku= !$r          ? "?" : (
         $r==1        ? "rok" : (
         ($r % 10)==1 ? "let" : (
         $r<=4        ? "roky" : "roků" )));
  return /*w*u*/("$r $roku, rč:$rc");
}
# ------------------------------------------------------------------------------------------ lide_ds
# SELECT autocomplete - výběr z databáze DS
function lide_ds($patt0) {  #trace('','win1250');
  global $ezer_local;
  $a= array();
  $limit= 10;
  $n= 0;
  $patt= $patt0;
  $patt= mb_strtolower($patt,'UTF-8');
  if ( !$ezer_local )
    $patt= utf2win($patt,true);             // POZOR - je určeno jen pro použití na ostrém serveru
  // výběr ze starých dobrých klientů
  ezer_connect('setkani');
  $qry= "SELECT id_osoba AS _key,concat(prijmeni,' ',jmeno,' - ',obec,'/',id_order) AS _value
         FROM ds_osoba
         WHERE lower(prijmeni) LIKE '$patt%'
         GROUP BY _value
         ORDER BY prijmeni
         LIMIT $limit";
  $res= pdo_qry($qry);
  while ( $res && $t= pdo_fetch_object($res) ) {
    if ( ++$n==$limit ) break;
    $key= $t->_key;
    $a[$key]= wu("D:{$t->_value}");
  }
  // obecné položky
  if ( !$n )
    $a[0]= /*w*u*/("... žádné jméno nezačíná '")."$patt0'";//."INFO='$info'";
  elseif ( $n==$limit )
    $a[-999999]= /*w*u*/("... a další");//."INFO='$info0'";
//                                                      debug($a,$patt,(object)array('win1250'=>1));
  return $a;
}
# ------------------------------------------------------------------------------------------- rodina
# formátování autocomplete
function klienti($id_osoba) {  #trace('','win1250');
  $rod= array();
  // rodiče
  ezer_connect('setkani');
  $qry= "SELECT * FROM ds_osoba WHERE id_osoba=$id_osoba";
  $res= pdo_qry($qry);
  if ( $res && $p= pdo_fetch_object($res) ) {
    $cond= "id_order={$p->id_order} AND obec='{$p->obec}' AND ulice='{$p->ulice}'";
    // vybereme se stejným označením rodiny
    $qry= "SELECT * FROM ds_osoba WHERE $cond
           ORDER BY narozeni";
    $res= pdo_qry($qry);
    while ( $res && $o= pdo_fetch_object($res) ) {
    $vek= ds_vek($o->narozeni,time());
    $narozeni= sql_date1($o->narozeni);
    $rod[]= (object)array('prijmeni'=>wu($o->prijmeni),'jmeno'=>wu($o->jmeno),'stari'=>$vek,
      'psc'=>$o->psc,'mesto'=>wu($o->obec),'ulice'=>wu($o->ulice),
      'telefon'=>$o->telefon,'email'=>$o->email,'narozeni'=>$narozeni);
    }
  }
//                                              debug($rod,$id_osoba,(object)array('win1250'=>1));
  return $rod;
}
# ============================================================================================> ceny
# ------------------------------------------------------------------------------------- ds xls_hoste
# kopie ceníku
# par:
#   .op='kopie'
#   .z=starý rok
#   .na=nový rok
function ds_ceny_uprava($par) { trace('','win1250');
  $html= "";
  if ( $par->op=='kopie' ) {
    ezer_connect('setkani');
    // určení roků
    $z= date('Y')+$par->z;
    $na= date('Y')+$par->na;
    // kontrola prázdnosti nového ceníku
    $qry= "SELECT count(*) as pocet FROM setkani.ds_cena  WHERE rok=$na ";
    $res= pdo_qry($qry);
    if ( $res && $c= pdo_fetch_object($res) ) {
      if ( $c->pocet>0 )
        $html= "Cenik pro rok $na byl jiz zrejme vygenerovan. Operace prerusena.";
    }
    if ( !$html ) {
      // kopie ceníku
      $qry= "SELECT * FROM ds_cena WHERE rok=$z ORDER BY typ";
      $res= pdo_qry($qry);
      $ok= 1; $n= 0;
      while ( $res && $c= pdo_fetch_object($res) ) {
        $ins= "INSERT INTO ds_cena (rok,polozka,druh,typ,od,do,cena,dph)
               VALUES ($na,'{$c->polozka}','{$c->druh}','{$c->typ}',{$c->od},{$c->do},{$c->cena},{$c->dph})";
        display(/*w*u*/($ins));
        $n++;
        $ires= pdo_qry($ins);
        $ok&= pdo_affected_rows()==1 ? 1 : 0;
      }
      $html.= $ok&&$n ? "Zkopirovano" : "Kopie ceniku se nezdarila. Kontaktuj Martina Smidka";
    }
  }
  return $html;
}
# =========================================================================================> exporty
# ------------------------------------------------------------------------------------- ds xls_hoste
# definice Excelovského listu - seznam hostů
function ds_xls_hoste($orders,$mesic_rok) {  #trace('','win1250');
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
# ----------------------------------------------------------------------------------------- ds hoste
# vytvoří seznam hostů
# ceník beer podle předaného roku
# {table:id,obdobi:str,hoste:[[jmeno,prijmeni,adresa,narozeni,telefon,email,termin,poplatek]...]}
function ds_hoste($orders,$rok) {  #trace('','win1250');
  global $ds_cena, $ezer_path_serv;
  require_once "$ezer_path_serv/licensed/xls2/Classes/PHPExcel/Calculation/Functions.php";
  ds_cenik($rok);
//                                      debug($ds_cena,'ds_cena',(object)array('win1250'=>1));
  $x= (object)array();
  $x->table= "klienti_$obdobi";
  $x->hoste= array();
  ezer_connect('setkani');
  // zjištění klientů zahajujících pobyt v daném období
  $qry= "SELECT *,o.fromday as _of,o.untilday as _ou,p.email as p_email,
         p.fromday as _pf,p.untilday as _pu,akce
         FROM setkani.ds_osoba AS p
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
    $vek= ds_vek($h->narozeni,$pf ? $h->_pf : $h->_of);
    if ( $h->narozeni ) {
      list($y,$m,$d)= explode('-',$h->narozeni);
      $time= gmmktime(0,0,0,$m,$d,$y);
      $narozeni= PHPExcel_Shared_Date::PHPToExcel($time);
    }
    else $narozeni= 0;
    // rekreační poplatek
    if ( $vek>=18 || $vek<0 )
      $popl= $ds_cena['ubyt_C']->cena + $ds_cena['ubyt_S']->cena;
    else
      $popl= $ds_cena['ubyt_P']->cena;
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
# ================================================================================> zálohová faktura
# ASK
# ------------------------------------------------------------------------------------ ds xls_zaloha
# definice Excelovského listu - zálohové faktury
function ds_xls_zaloha($order) {  trace();//'','win1250');
  global $ezer_path_serv;
  $html= " nastala chyba";
  $name= "zal_$order";
  // vytvoření sešitu s fakturou
  $xls= "|open  $name|";
  $x= ds_zaloha($order);
  if ( $x->err ) { $html= $x->err; goto end; }
//                                                         debug($x,"ds_zaloha");
  if ( !count((array)$x) ) goto end;
  $xls.= ds_faktura('zalohova_faktura','ZÁLOHOVÁ FAKTURA',$order,$x->polozky,$x->platce,50,
    "Těšíme se na Váš pobyt v Domě setkání");
  $xls.= "|close|";
  $test= 1;
  if ( $test )
    file_put_contents("xls.txt",$xls);
  $inf= Excel2007(/*w*u*/($xls),1);
  if ( $inf ) {
    $html= " nastala chyba";
    fce_error(/*w*u*/($inf));
  }
  else
    $html= " <a href='docs/$name.xlsx' target='xls'>zálohová faktura</a>.";
end:
  return $html;
}
# ---------------------------------------------------------------------------------------- ds zaloha
# data zálohové faktury
# {objednavka:n,
#  platce:[nazev,adresa,telefon,ic]
#  polozky:[[nazev,cena,dph,pocet]...]
# }
function ds_zaloha($order) {  trace();// '','win1250');
  global $ds_cena;
  $x= (object)array();
  // zjištění údajů objednávky
  ezer_connect('setkani');
  $qry= "SELECT * FROM tx_gnalberice_order WHERE uid=$order";
  $res= pdo_qry($qry);
  if ( $res && $o= pdo_fetch_object($res) ) {
    $o->rooms= $o->rooms1;
    foreach ((array)$o as $on) if ( strstr($on,'|')!==false ) { // test na |
      fce_warning(/*w*u*/("nepřípustný znak '|' v '$on'"));
      goto end;
    }
    $obdobi= date('j.n',$o->fromday).' - '.date('j.n.Y',$o->untilday);
    $dnu= ($o->untilday-$o->fromday)/(60*60*24);
//                                                         display("pocet dnu=$dnu");
    // přečtení ceníku daného roku
    $rok= date('Y',$o->untilday);
    ds_cenik($rok);
    if ( !count($ds_cena) ) { $x->err= "není ceník pro $rok"; goto end; }
    // údaje o plátci: $ic,$dic,$adresa,$akce
    $platce= array();
    $platce[]= $o->ic ? $o->ic : '';
    $platce[]= $o->dic ? $o->dic : '';
    $platce[]= wu(($o->org ? "{$o->org}{}" : '')."{$o->firstname} {$o->name}{}".
              "{$o->address}{}{$o->zip} {$o->city}");
    $platce[]= $o->akce;
    $platce[]= $obdobi;
    $x->platce= $platce;
    // položky zálohové faktury
    // ubytování může mít slevu
    $polozky= array();
    $sleva= $o->sleva ? $o->sleva/100 : '';
    $x->polozky[]= ds_c('noc_L',$dnu*($o->adults + $o->kids_10_15 + $o->kids_3_9),$sleva);
    $x->polozky[]= ds_c('noc_A',0,$sleva,1);
    $x->polozky[]= ds_c('noc_B',0,$sleva,1);
    $x->polozky[]= ds_c('noc_P',0,$sleva,1);
    $x->polozky[]= ds_c('noc_S',0,$sleva,1);
    $x->polozky[]= ds_c('noc_Z',0,$sleva,1);
    $x->polozky[]= ds_c('ubyt_C',$dnu*($o->adults));
    $x->polozky[]= ds_c('ubyt_S',$dnu*($o->adults));
    $x->polozky[]= ds_c('ubyt_P',$dnu*($o->kids_10_15 + $o->kids_3_9 + $o->kids_3));
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
//                                              debug($x,'zaloha',(object)array('win1250'=>1));
  }
end:
  return $x;
}
# =================================================================================> konečná faktura
# ----------------------------------------------------------------------------------- ds xls_faktury
# ASK
# definice Excelovského listu - faktury podle seznamu účastníků
# položka,počet osob,počet nocí,počet nocolůžek,cena jednotková,celkem,DPH 9%,DPH 19%,základ
# *ubytování
function ds_xls_faktury($order) {  trace(); //'','win1250');
  global $ds_cena;
  $html= " nastala chyba";
  $test= 1;
  $x= ds_faktury($order);
  if ( $x->err ) { $html= $x->err; goto end; }
  if ( !count((array)$x->rodiny) ) goto end;
  $ds_cena['zzz_zzz']= 0;    // přidání prázdného řádku
  ksort($ds_cena);

//                                      debug($ds_cena,'ds_cena',(object)array('win1250'=>1));
//                                      debug($x,'faktura',(object)array('win1250'=>1));
  // barvy
  $c_edit= "ffffffaa";
  // úvodní list
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
    $faktury.= "|C$nf $sleva::proc bcolor=$c_edit|E$nf 0 ::kc bcolor=$c_edit|D$nf =";
    $An_sleva= "'rodiny'!C$nf";
    $An_zaloha= "'rodiny'!E$nf";
    # ------------------------------------------------------------- členové rodiny a položky faktury
    $i= 0;
    $clmn= "A=10,B=13,C=-30,D=-15,E=4,F=-20,G=-30,H=20,I=5,J=6";
    $tit= "|A3 jméno |B3 příjmení |C3 adresa |D3 narozen/a ::right |E3 věk |F3 telefon "
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
      // součty
    }
    $tit.= "|A$n:$B$n bcolor=ffaaaaaa |";
    $xls.= "\n\n|sheet $sheet_rodina;;L;page|columns $clmn |$tit |";
    $skupiny= count($x->rodiny)==1 ? '' : ($rodina=='' ? "neoznačených hostů" : "hostů označených '$rodina'");
    $xls.= "|A1 Konečné vyúčtování $skupiny v rámci objednávky $order ::bold size=14|";
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
      // položky
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
            if ( $druh0=='noc' ) {
              $row.= "|$B$n =(1-$A$n)*($suma) ::kc\n";
            }
            else {
              $row.= "|$B$n =$suma ::kc\n";
            }
            $suma= '0';
            $celkem.= "+$B$n";
          }
          $druh0= $druh;
        }
        if ( $cena ) {
          $lc++;
          $A= Excel5_n2col($lc);
          $val= isset($c->$dc) && $c->$dc ? $c->$dc : '0';
          $suma.= "+$A$n*{$cena->cena}";
          $row.= "|$A$n $val::bcolor=$c_edit";
        }
        else {
          $lc++;
          $B= Excel5_n2col($lc);
          $row.= "|$B$n $celkem ::kc bold\n";
        }
        $i++;
      }
//                                                                 debug($host,'host',(object)array('win1250'=>1));
      $xls.= "$row|A$n:$B$n border=,,h,|$soucty|\n";
      $n++;
    }
    $xls.= "|$soucty|\n";
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
    // položky faktury
    $polozky= array();
    foreach($ds_cena as $dc=>$cena) {
      if ( $cena ) {    // bez prázdného řádku
        list($druh,$cast)= explode('_',$dc);
        $sleva= $druh=='noc' ? "=$An_sleva" : '';
        $zaloha= "=$An_zaloha";
        $polozky[]= ds_c($dc,$cena->pocet,$sleva,$zaloha);        // id,pocet => název,cena,dph%,pocet,druh
      }
    }
    // vytvoření listu
    $xls.= ds_rozpis_faktura($sheet_rozpis,$sheet_faktura,'FAKTURA',$order,$x,$polozky,$platce,$zaloha,
      "Těšíme se na Váš další pobyt v Domě setkání",$zaloha,$suma);
    $faktury.= "";
  }
  // ------------------------------------------------------------------ ceník
//                                      debug($ds_cena,'ds_cena',(object)array('win1250'=>1));
  $xls.= <<<__XLS
  \n\n|sheet cenik;;P;page
    |columns A=35,B=20,C=20
    |A1 Seznam účtovatelných položek ::bold size=14
    |A3 položka |B3 cena vč.DPH ::right |C3 DPH ::right proc
    |A3:C3 bcolor=ffaaaaaa
__XLS;
  $n= 4;
  foreach ($ds_cena as $i=>$cena) {
    if ( $cena ) {    // bez prázdného řádku
      $dph= $cena->dph/100;
      $xls.= <<<__XLS
        |A$n {$cena->polozka} |B$n {$cena->cena} :: kc |C$n $dph :: proc
        |A$n:C$n border=,,h,
__XLS;
      $n++;
    }
  }
  # ------------------------------------------------------------------ seznam rodin (jako první)
  $nf1= $nf+1;
  $name= "fak_$order";
  $final_xls= <<<__XLS
  \n|open $name
  \n\n|sheet rodiny;;L;page
    |columns A=20,B=13,C=10,D:F=16
    |A1 Seznam rodin (skupin), kterým fakturujeme pobyt v rámci objednávky $order ::bold size=14
    |A3 rodina (skupina)|B3 osob ::right |C3 sleva ::right |D3 celková cena ::right
    |E3 záloha ::right|F3 doplatit ::right
    |A3:F3 bcolor=ffaaaaaa
    |$faktury
    |A$nf1 CELKEM ::right bold|B$nf1 =SUM(B4:B$nf) ::bold|D$nf1 =SUM(D4:D$nf) ::kc bold
    |E$nf1 =SUM(E4:E$nf) ::kc bold|F$nf1 =SUM(F4:F$nf) ::kc bold
__XLS;
  // vysvětlivky
  $n= $nf1+3;
  $final_xls.= "|A$n Upozornění: v tomto sešitu je možné upravovat pouze žlutě podložená políčka, ".
    "změny jiných políček pravděpodobně poškodí výpočet cen a DPH. Pokud je třeba měnit údaje, ".
    "které nejsou žlutě podloženy, je potřeba to udělat v systému Ans(w)er a znovy vygenerovat tento ".
    "sešit.";
  $final_xls.= "|A$n:F$n merge italic top wrap
    |rows $n=60
    |$xls|close 1";
//                                                                 display("rodiny=$faktury");
//                                                                 display(nl2br(/*w*u*/($xls)));
  $test= 1;
  if ( $test )
    file_put_contents("xls.txt",$final_xls);
  time_mark('ds_xls_faktury Excel5');
  $inf= Excel2007(/*w*u*/($final_xls),1);
  if ( $inf ) {
    $html= " nastala chyba";
    fce_error(/*w*u*/($inf));
  }
  else
    $html= " <a href='docs/$name.xlsx' target='xls'>konečná faktura</a>.";
  // případný testovací výpis
  time_mark('ds_xls_faktury end');
end:
  return /*w*u*/($html);
}
# --------------------------------------------------------------------------------------- ds faktury
# podklady ke konečné fakturaci
# {platce:[ic,dic,adresa,akce],faktury:[[rodina,počet,sleva]...],rodiny:[<host>...],<ceník>,<chyby>}
#    <host> :: {host:[jmeno,prijmeni,adresa1,adresa2,narozeni,vek,telefon,email,od,do,<ubyt>],
#               cena:{<ubyt>,<strava>,<spec>,<popl>}
#    <ubyt> :: noci:n,pokoj:1..29,pokoj_typ:P|S|B,luzko_typ:L|P|B
#  <strava> :: CC:n,CP:n,PC:n,PP:n
#    <spec> :: postylka:n,zvire_noci:n
#    <popl> :: popl:CS|P
#   <ceník> :: cenik:... viz global $ds_cena
#   <chyby> :: chyby:[[text,...]...]
#    pokoje :: A:1-2, S:14-17, L:11-13+21-29
#
function ds_faktury($order) {  trace('','win1250');
  global $ds_cena;
  $x= (object)array('faktury'=>array(),'rodiny'=>array());
//// číselníky                    1   2   3   4   5   6   7   8   9   10  11  12  13  14  15  16
//$luzko_pokoje= array(0=>'?',1=>'L','L','L','L','L','L','L','S','S','L','L','L','S','S','A','A');
  $luzko_pokoje[0]= 0;
  for ($i=11; $i<=29; $i++) $luzko_pokoje[$i]= 'L';     // normální pokoje
  for ($i= 1; $i<= 2; $i++) $luzko_pokoje[$i]= 'A';     // apartmán bezbariérový
  for ($i=14; $i<=17; $i++) $luzko_pokoje[$i]= 'S';     // apartmány
  $ds_luzko=  map_cis('ds_luzko','zkratka');  $ds_luzko[0]=  '?';
  $ds_strava= map_cis('ds_strava','zkratka'); $ds_strava[0]= '?';
//                                              debug($ds_strava,(object)array('win1250'=>1));
  // kontrola objednávky
  ezer_connect('setkani');
  $qry= "SELECT * FROM setkani.tx_gnalberice_order WHERE uid=$order";
  $res= pdo_qry($qry);
  if ( $res && $o= pdo_fetch_object($res) ) {
    $o->rooms= $o->rooms1;
    foreach ((array)$o as $on) if ( strstr($on,'|')!==false ) { // test na |
      fce_warning(/*w*u*/("nepřípustný znak '|' v '$on'"));
      goto end;
    }
    $obdobi= date('j.n',$o->fromday).' - '.date('j.n.Y',$o->untilday);
    $skoleni= $o->skoleni;
    // údaje o plátci: $ic,$dic,$adresa
    $platce= array();
    $platce[]= $o->ic ? $o->ic : '';
    $platce[]= $o->dic ? $o->dic : '';
    $platce[]= wu(($o->org ? "{$o->org}{}" : '')."{$o->firstname} {$o->name}{}".
              "{$o->address}{}{$o->zip} {$o->city}");
    $platce[]= $o->akce;
    $platce[]= $obdobi;
    $x->objednavka= array($obdobi);
    $x->platce= $platce;
    // přečtení ceníku daného roku
    $rok= date('Y',$o->untilday);
    ds_cenik($rok);
    if ( !count($ds_cena) ) { $x->err= "není ceník pro $rok"; goto end; }
    // úprava ceny programu na této akci
    $ds_cena['prog_C']->cena= $o->prog_cely;
    $ds_cena['prog_P']->cena= $o->prog_polo;
    // zjištění počtu faktur za akci
    $qry= "SELECT rodina,count(*) as pocet FROM setkani.ds_osoba
           WHERE id_order=$order GROUP BY rodina ORDER BY if(rodina='','zzzzzz',rodina)";
    $res= pdo_qry($qry);
    while ( $res && $r= pdo_fetch_object($res) ) {
      // seznam faktur
      $rid= $r->rodina ? $r->rodina : 'ostatni';
      $x->faktury[]= array($rid,$r->pocet,$o->sleva/100);
      // členové jedné rodiny s údaji
      $hoste= array();
      $err= array();
      $qry= "SELECT * FROM setkani.ds_osoba
             WHERE id_order=$order AND rodina='{$r->rodina}' ORDER BY narozeni DESC";
      $reso= pdo_qry($qry);
      while ( $reso && $h= pdo_fetch_object($reso) ) {
        foreach ((array)$h as $on) if ( strstr($on,'|')!==false ) { // test na |
          fce_warning(/*w*u*/("nepřípustný znak '|' v '$on'"));
          goto end;
        }
        $hf= sql2stamp($h->fromday); $hu= sql2stamp($h->untilday);
        $od_ts= $hf ? $hf : $o->fromday;  $od= date('j.n',$od_ts);
        $do_ts= $hu ? $hu : $o->untilday; $do= date('j.n',$do_ts);
        $vek= ds_vek($h->narozeni,$o->fromday);
        $narozeni= $h->narozeni ? sql_date1($h->narozeni): '';
        $strava= $h->strava ? $h->strava : $o->board;
        // připsání řádku
        $host= array();
        $host[]= wu($h->rodina);
        $host[]= wu($h->jmeno);
        $host[]= wu($h->prijmeni);
        $host[]= wu($h->ulice);
        $host[]= wu("{$h->psc} {$h->obec}");
        $host[]= $narozeni;
        $host[]= $vek;
        $host[]= $h->telefon;
        $host[]= $h->email;
        $host[]= $od;
        $host[]= $do;
        // položky hosta
        $pol= (object)array();
        $pol->test= "{$h->strava} : {$o->board} - $strava = {$ds_strava[$strava]}";
        $noci= round(($do_ts-$od_ts)/(60*60*24));
        $pol->vek= $vek;
        $pol->noci= $noci;
        $pol->pokoj= $h->pokoj;
        // ubytování
        $luzko= trim($ds_luzko[$h->luzko]);     // L|P|B
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
          $pol->ubyt_S= $noci;
          if ( !$skoleni ) $pol->ubyt_C= $noci;   // rekreační poplatek se neplatí za školení
        }
        else {
          $pol->ubyt_P= $noci;
        }
        // program
        $pol->prog_C= $vek>=$ds_cena['prog_C']->od  ? 1 : 0;
        $pol->prog_P= $vek>=$ds_cena['prog_P']->od && $vek<$ds_cena['prog_P']->do ? 1 : 0;
        // konec
        $hoste[]= (object)array('host'=>$host,'cena'=>$pol);
      }
      $x->rodiny[]= $hoste;
      $x->chyby[]= $err;
    }
  }
  else
    fce_error(/*w*u*/("neúplná objednávka $order"));
//                                      debug($ds_cena,'ds_cena',(object)array('win1250'=>1));
//                                      debug($x,'faktura',(object)array('win1250'=>1));
end:
  return $x;
}
# ==================================================================================> faktura obecně
# -------------------------------------------------------------------------------- ds rozpis_faktura
# definice faktury
# typ = Zálohová | ''
# ??? zaloha = 0..100  -- pokud je 100 negeneruje se řádek Záloha ... -- 8/4/2019 změněno na 0
# data zálohové faktury
# platce= [nazev,adresa,telefon,ic]
# polozky= [[nazev,cena,dph,pocet,sleva]...]
# }
function ds_rozpis_faktura($listr,$listf,$typ,$order,$x,$polozky,$platce,$zaloha,$pata,$zaloha2,&$suma) {
                                                trace('','win1250');
  $koef_dph= dph_koeficienty();
  list($ic,$dic,$adresa,$akce,$obdobi)= $platce;
//                                              debug($platce,'platce',(object)array('win1250'=>1));
  $vystaveno= Excel5_date(time());
  //list($obdobi)= $x->objednavka;
  $ymca_setkani= "YMCA Setkání, spolek{}Talichova 53, 62300 Brno{}".
                 "zaregistrovaný Krajským soudem v Brně{}spisová značka: L 8556{}".
                 "IČ: 26531135  DIČ: CZ26531135";
  $dum_setkani=  "Dolní Albeřice 1, 542 26 Horní Maršov{}".
                 "telefon: 499 874 152, 736 537 122{}dum@setkani.org{}www.alberice.setkani.org";
  // ------------------------------------------------------------------- vytvoření listu s rozpisem
  // pojmenované řádky (P,Q,R,S)
  $P= 10;               // výčet položek
  $Q= 25;               // poslední položka
  $D= 28;               // rozpis podle druhů
  $S= 34;               // poslední řádek
  // parametrizace
  $c_okraj= "ff6495ed";    $S1= $S+1;
  $xls= <<<__XLS
  \n\n|sheet $listr;B2:N$S;P;page
    |columns A=3,B=0.6,C=16,D=3,E=22,F=6,G=16,H=10,I=4,J=6,K=6,L=16,M=0,N=1,O=3
    |rows 1=18,2:44=15,5=30,7=45,9=30,10:32=20,$S=30
    |A1:O$S1 bcolor=$c_okraj |B2:N$S bcolor=ffffffff |//B2:N$S border=h

    |image img/YMCA.png,80,C2,10,0
    |D2 Dům setkání :: bold size=14
    |D3 $dum_setkani
    |D3:H5 merge italic top wrap
    |B7 Rozpis ceny za pobyt v Domě setkání ve dnech $obdobi ::bold size=16|B7:L7 merge
__XLS;
  $n= $P-1;
  $xls.= <<<__XLS
    |C$n Položka              ::wrap middle       |C$n:E$n merge bold border=h
    |F$n Počet                ::wrap middle right |F$n:F$n       bold border=h
    |G$n Druh                 ::wrap middle right |G$n:G$n       bold border=h
    |H$n Cena položky s DPH   ::wrap middle right |H$n:I$n merge bold border=h
    |J$n Sleva %              ::wrap middle right |J$n:J$n       bold border=h
    |K$n Sazba DPH            ::wrap middle right |K$n:K$n       bold border=h
    |L$n Cena s DPH           ::wrap middle right |L$n:L$n       bold border=h
__XLS;
  // řádky $P-$Q -- položky
  $n= $P;
  $sazby_dph= array();
  foreach ($polozky as $i=>$polozka) {
    list($nazev,$cena,$dph,$pocet,$druh,$sleva,$inuly)= $polozka;
    if (!in_array($dph,$sazby_dph) ) $sazby_dph[]= $dph;
    if (!isset($druhy[$druh]) ) $druhy[$druh]= $dph;
    $sleva= $sleva ?: 0;
    if ( $pocet || $inuly ) {
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
  // řádky D... -- rozpis podle druhů
  $n= $D;
  if ( count($druhy) )
  foreach($druhy as $druh=>$dph) {
    $koef= $koef_dph[round($dph*100)];
    $xls.= <<<__XLS
      |H$n $druh::right                |H$n:J$n merge right
      |K$n $dph                        ::proc border=h right
      |L$n =SUMIF(G$P:G$Q,H$n,L$P:L$Q) ::kc border=h right
      |O$n $koef                       ::kc color=$c_okraj
__XLS;
    $n++;
  }
  // ------------------------------------------------------------------- vytvoření listu s fakturou
  // pojmenované řádky (P,Q,R,S)
  $P= 22;               // výčet položek
  $Q= 34;               // poslední položka
  $R= 31;               // vyřizuje
  $S= 37;               // poslední řádek
  // parametrizace
  $L7_ic=  $ic  ? "L7 IČ $ic"   : '';
  $M7_dic= $dic ? "M7 DIČ $dic" : '';
  $c_okraj= "ff6495ed";
  $S1= $S+1;
  $xls.= <<<__XLS
  \n\n|sheet $listf;B2:N$S;P;page
    |columns A=3,B=0.6,C=16,D=3,E=22,F=6,G=1,H=10,I=4,J=6,K=6,L:M=16,N=1,O=3
    |rows 1=18,2:44=15,5=30,6=45,9=96,11=35,19=30,20:36=19,21=30,37=30,38:41=19,$S=30
    |A1:O$S1 bcolor=$c_okraj |B2:N$S bcolor=ffffffff |//B2:N$S border=h

    |image img/YMCA.png,80,C2,10,0
    |D2 Dům setkání :: bold size=14
    |D3 $dum_setkani
    |D3:H5 merge italic top wrap

    |J4 =CONCATENATE("$typ ",TEXT(E16,"0"),"/",TEXT(3000+YEAR(M15),"0")) :: bold size=16
    |J4:M5 merge right

    |C7 Dodavatel ::bold
    |C9 $ymca_setkani
    |C9:F9 merge top wrap

    |I7 Odběratel ::bold         |$L7_ic        |$M7_dic
    |I8:M10 border=h
    |J9 $adresa  |J9:M9 merge middle wrap size=14

    |C13 Peněžní ústav           |E13 Fio banka, a.s.
    |C14 Číslo účtu       ::bold |E14 2000465448/2010 ::bold
    |C15 Konstantní symbol       |E15 558 ::left
    |C16 Variabilní symbol       |E16 <číslo faktury> ::left bcolor=ffffffaa
    |C17 Specifický symbol       |E17 $akce ::left bcolor=ffffffaa

    |J12 Objednávka číslo ::bold |M12 $order  ::size=14 bold
    |J13 Dodací a platební podmínky: s daní
    |J14 Datum vystavení           |M14 $vystaveno ::date bcolor=ffffffaa
    |J15 Datum zdanitelného plnění |M15 =M14       ::date bcolor=ffffffaa
    |J16 Datum splatnosti ::bold   |M16 =M14+14    ::date bcolor=ffffffaa bold
    |J18 Způsob platby             |M18 převod/hotovost ::bcolor=ffffffaa

    |C19 Za pobyt v Domě setkání ve dnech $obdobi Vám fakturujeme: |C19:M19 merge
__XLS;
  $n= $P-1;
  $xls.= <<<__XLS
    |C$n Položka              ::wrap middle       |C$n:G$n merge bold border=h
    |H$n Cena s DPH           ::wrap middle right |H$n:J$n merge bold border=h
    |K$n Sazba DPH            ::wrap middle right |K$n:K$n       bold border=h
    |L$n DPH                  ::wrap middle right |L$n:L$n       bold border=h
    |M$n Cena bez DPH         ::wrap middle right |M$n:M$n       bold border=h
__XLS;
  // řádky $P-$Q -- položky
  $n= $P;
  $d= $D;
  for ($i= 0; $i<count($druhy); $i++ ) {
    $koef= $koef_dph[round($dph*100)];
    $xls.= <<<__XLS
      |C$n ='$listr'!H$d          |C$n:G$n merge
      |H$n ='$listr'!L$d     ::kc |H$n:J$n merge
      |K$n ='$listr'!K$d     ::proc
      |L$n =H$n*'$listr'!O$d ::kc
      |M$n =H$n-L$n          ::kc
__XLS;
//       |M$n =H$n/(1+K$n)    ::kc
    $n++; $d++;
  }
  $n--;
  $xls.= <<<__XLS
    |C$P:G$n border=h    |H$P:J$n border=h
    |K$P:K$n border=h    |L$P:L$n border=h    |M$P:M$n border=h
__XLS;
  // celková cena
  $d= $n;
  $n+= 2;
  $suma= "'$listf'!L$n";
  $xls.= <<<__XLS
    |H$n Celková cena s DPH ::middle bold |H$n:K$n merge right
    |L$n =SUM(L$P:M$d)      ::kc          |L$n:M$n merge border=h
__XLS;
  $c= $n;
  $n++;
  $xls.= <<<__XLS
    |H$n Zaplaceno zálohou  ::middle bold |H$n:K$n merge right
    |L$n $zaloha            ::kc          |L$n:M$n merge border=h
__XLS;
  $z= $n;
  $n++;
  $xls.= <<<__XLS
    |H$n Zbývá k zaplacení  ::middle bold |H$n:K$n merge right
    |L$n =L$c-L$z           ::kc          |L$n:M$n merge border=h
__XLS;
  // řádky R,S (viz výše) -- spodek faktury
  $n= $R+1;
  $xls.= <<<__XLS
    |C$R vyřizuje ::bold
    |C$n Josef Náprstek, správce Domu setkání
    |C$S:M$S border=h,,,
    |C$S $pata | C$S:M$S merge middle center wrap
__XLS;
  return $xls;
}
# --------------------------------------------------------------------------------------- ds faktura
# definice faktury
# typ = Zálohová | ''
# // zaloha = 0..100  -- pokud je 100 negeneruje se řádek Záloha ... -- 8/4/2019 změněno na 0
# data zálohové faktury
# platce= [nazev,adresa,telefon,ic]
# polozky= [[nazev,cena,dph,pocet,sleva]...]
# }
function ds_faktura($list,$typ,$order,$polozky,$platce,$zaloha=0,$pata='') {  trace();//,'win1250');
  list($ic,$dic,$adresa,$akce,$obdobi)= $platce;
  $vystaveno= Excel5_date(time());
  $ymca_setkani= "YMCA Setkání, spolek{}Talichova 53, 62300 Brno{}".
                 "zaregistrovaný Krajským soudem v Brně{}spisová značka: L 8556{}".
                 "IČ: 26531135  DIČ: CZ26531135";
  $dum_setkani=  "Dolní Albeřice 1, 542 26 Horní Maršov{}".
                 "telefon: 736 537 122{}dum@setkani.org{}www.alberice.setkani.org";
  // pojmenované řádky (P,Q,R,S)
  $P= 22;               // výčet položek
  $Q= 36;               // poslední položka
  $R= 39;               // rozpis DPH
  $S= 45;               // poslední řádek
  // parametrizace
  $L7_ic= $ic ? "L7 IČ $ic" : '';
  $M7_dic= $dic ? "M7 DIČ $dic" : '';
  $c_okraj= "ff6495ed";
  // vytvoření listu s fakturou
  $xls= <<<__XLS
    |sheet $list;B2:N$S;P;page
    |columns A=3,B=0.6,C=16,D=3,E=22,F=6,G=1,H=10,I=4,J=6,K=6,L:M=16,N=1,O=3
    |rows 1=18,2:44=15,5=30,6=45,9=96,11=35,19=30,21=30,22:38=19,39=30,40:43=19,$S=30
    |A1:O46 bcolor=$c_okraj |B2:N$S bcolor=ffffffff |//B2:N$S border=h

    |image img/YMCA.png,80,C2,10,0
    |D2 Dům setkání :: bold size=14
    |D3 $dum_setkani
    |D3:H5 merge italic top wrap

    |J4 =CONCATENATE("$typ ",TEXT(3000+YEAR(M15),"0"),"/",TEXT(E16,"0")) :: bold size=16
    |J4:M5 merge right

    |C7 Dodavatel ::bold
    |C9 $ymca_setkani
    |C9:F9 merge top wrap

    |I7 Odběratel ::bold         |$L7_ic  |$M7_dic
    |I8:M10 border=h
    |J9 $adresa  |J9:M9 merge middle wrap size=14

    |C13 Peněžní ústav           |E13 Fio banka, a.s.
    |C14 Číslo účtu       ::bold |E14 2000465448/2010 ::bold
    |C15 Konstantní symbol       |E15 558 ::left
    |C16 Variabilní symbol       |E16 <číslo faktury> ::left bcolor=ffffffaa
    |C17 Specifický symbol       |E17 $akce ::left bcolor=ffffffaa

    |J12 Objednávka číslo ::bold |M12 $order  ::size=14 bold
    |J13 Dodací a platební podmínky: s daní
    |J14 Forma úhrady              |M14 převodem
    |J14 Datum vystavení           |M14 $vystaveno ::date bcolor=ffffffaa
    |J15 Datum zdanitelného plnění |M15 =M14       ::date bcolor=ffffffaa
    |J16 Datum splatnosti ::bold   |M16 =M14+14    ::date bcolor=ffffffaa bold
    |J18 Způsob platby             |M18 převod/hotovost ::bcolor=ffffffaa
    |C19 Fakturujeme vám zálohu na pobyt ve dnech $obdobi: |C19:M19 merge
__XLS;
  $n= $P-1;
  $xls.= <<<__XLS
    |C$n Položka              ::wrap middle       |C$n:E$n merge bold border=h
    |F$n Počet                ::wrap middle right |F$n:G$n merge bold border=h
    |H$n Cena položky vč. DPH ::wrap middle right |H$n:I$n merge bold border=h
    |J$n Sleva %              ::wrap middle right |J$n:J$n       bold border=h
    |K$n Sazba DPH            ::wrap middle right |K$n:K$n       bold border=h
    |L$n DPH                  ::wrap middle right |L$n:L$n       bold border=h
    |M$n Cena bez DPH         ::wrap middle right |M$n:M$n       bold border=h
__XLS;
  // řádky $P-$Q -- položky
  $n= $P;
  $sazby_dph= array();
  $koef_dph= dph_koeficienty(); //==> . koeficienty DPH podle zákona o DPH
  foreach ($polozky as $i=>$polozka) {
    list($nazev,$cena,$dph,$pocet,$druh,$sleva,$inuly)= $polozka;
    if (!in_array($dph,$sazby_dph) ) $sazby_dph[]= $dph;
    if ( $pocet || $inuly ) {
      $koef= $koef_dph[round($dph*100)];
      if ( $dph && !$koef ) fce_error(100*$dph." je neznámá sazba");
      $xls.= <<<__XLS
        |C$n $nazev                |C$n:E$n merge
        |F$n $pocet                |F$n:G$n merge
        |H$n $cena         ::kc    |H$n:I$n merge
        |J$n $sleva        ::proc
        |K$n $dph          ::proc
        |L$n =O$n*$koef    ::kc
        |M$n =O$n-L$n      ::kc
        |O$n =F$n*H$n*(1-J$n) ::kc color=$c_okraj
__XLS;
      $n++;
    }
  }
  $xls.= <<<__XLS
    |C$P:E$Q border=h    |F$P:G$Q border=h    |H$P:I$Q border=h
    |J$P:J$Q border=h    |K$P:K$Q border=h    |L$P:L$Q border=h    |M$P:M$Q border=h
__XLS;
  // celková cena
  $n= $Q+1;
  $xls.= <<<__XLS
    |H$n Celková cena s DPH ::middle bold |H$n:K$n merge right
    |L$n =SUM(L$P:M$Q)      ::middle kc   |L$n:M$n merge border=h
__XLS;
//  if ( $zaloha<100 ) {
    $n++;
    $xls.= <<<__XLS
      |H$n Záloha $zaloha%             ::middle bold |H$n:K$n merge right
      |L$n =SUM(L$P:M$Q)*($zaloha/100) ::middle kc   |L$n:M$n merge border=h
__XLS;
//  }
  // řádky R... -- rozpis DPH
  $n= $R+1;
  $xls.= <<<__XLS
    |K$R Rozpis DPH ::bold
    |K$n Sazba::middle bold border=h right
    |L$n Daň     ::middle bold border=h right
    |M$n Základ  ::middle bold border=h right
__XLS;
  sort($sazby_dph);
  foreach($sazby_dph as $sazba) {
    $n++;
    $xls.= <<<__XLS
      |K$n $sazba                                    ::proc border=h right
      |L$n =SUMIF(K$P:K$Q,K$n,L$P:L$Q)*($zaloha/100) ::kc   border=h right
      |M$n =SUMIF(K$P:K$Q,K$n,M$P:M$Q)*($zaloha/100) ::kc   border=h right
__XLS;
  }
  // řádky R,S (viz výše) -- spodek faktury
  $n= $R+1;
  $xls.= <<<__XLS
    |C$R vyřizuje ::bold
    |C$n Josef Náprstek, správce Domu setkání
    |C$S:M$S border=h,,,
    |C$S $pata | C$S:M$S merge middle center wrap
__XLS;
  return $xls;
}
# ----------------------------------------------------------------------------------------- ds cenik
# načtení ceníku pro daný rok
function ds_cenik($rok) {  #trace('','win1250');
  global $ds_cena;
  $ds_cena= array();
  ezer_connect('setkani');
  $qry2= "SELECT * FROM ds_cena WHERE rok=$rok";
  $res2= pdo_qry($qry2);
  while ( $res2 && $c= pdo_fetch_object($res2) ) {
    $wc= $c;
    $wc->polozka= wu($c->polozka);
    $wc->druh= wu($c->druh);
    $ds_cena[$c->typ]= $wc;
  }
//                                                 debug($cena,'cena',(object)array('win1250'=>1));
}
# --------------------------------------------------------------------------------------------- ds c
# položka faktury
# id,pocet => název,cena,dph%,pocet
# inuly - zapsat do faktury i nuly
function ds_c ($id,$pocet,$sleva='',$inuly=0) { trace();
  global $ds_cena;
  $c= array($ds_cena[$id]->polozka,$ds_cena[$id]->cena,$ds_cena[$id]->dph/100,
    $pocet,trim($ds_cena[$id]->druh),$sleva,$inuly);
//   if ( $sleva ) $c[]= $sleva;
  return $c;
}
# ------------------------------------------------------------------------------------------- ds vek
# zjištění věku v době zahájení akce
function ds_vek($narozeni,$fromday) {
  if ( $narozeni=='0000-00-00' )
    $vek= -1;
  else {
    $vek= $fromday-sql2stamp($narozeni);
    $vek= round($vek/(60*60*24*365.2425),1);
  }
  return $vek;
}
?>
