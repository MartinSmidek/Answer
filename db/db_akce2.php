<?php # (c) 2009-2010 Martin Smidek <martin@smidek.eu>
# =========================================================================================== SYSTÉM
# ----------------------------------------------------------------------------------- data_mrop_save
function data_mrop_save($par,$save=0) {
  switch ($save) {
  case 0:               // confirm
    list($pocet,$problem,$nazev)= select("COUNT(*),SUM(IF(iniciace=0,0,1)),nazev",
      "pobyt JOIN akce ON id_akce=id_duakce JOIN spolu USING (id_pobyt) JOIN osoba USING (id_osoba)",
      "id_akce=$par->akce AND YEAR(datum_od)=$par->rok AND funkce=0 AND sex=1");
    $txt= $problem
      ? "POZOR! akce $nazev/$par->rok se zúčastnilo $problem již jednou iniciovaných!"
      : "zapsat $par->rok jako rok iniciace pro $pocet účastníků akce $nazev/$par->rok?";
    break;
  case 1:               // zápis
    query("UPDATE osoba
             JOIN spolu USING (id_osoba) JOIN pobyt USING (id_pobyt) JOIN akce ON id_akce=id_duakce
           SET iniciace=$par->rok
             WHERE id_akce=$par->akce AND funkce=0 AND iniciace=0 AND sex=1");
    $n= mysql_affected_rows();
    $txt= "Rok $par->rok byl zapsán jako rok iniciace $n mužům";
    break;
  }
  return $txt;
}
# ======================================================================================== ELIMINACE
# --------------------------------------------------------------------------------------- eli_single
# je voláno po kladné odpovědi na otázku položenou fcí eli_osoba - vstupem jej její výstup
function eli_single($ret) { trace();
  global $USER;
  $now= date("Y-m-d H:i:s");
  $user= $USER->abbr;
  query("UPDATE rodina SET deleted='D rodina=$ret->r_idr' WHERE id_rodina=$ret->s_idr");
  // zápis o smazání kopie do _track jako op=x (eXtract)
  query("INSERT INTO _track (kdy,kdo,kde,klic,fld,op,old,val)
         VALUES ('$now','$user','rodina',$ret->r_idr,'','x','kopie',$ret->s_idr)");
  // odstranění vazby v tabulce TVORI
  query("DELETE FROM tvori WHERE id_tvori=$ret->s_idt");
  // nastavení adresy jako rodinné
  list($ulice,$psc,$obec,$stat)= select("ulice,psc,obec,stat","osoba","id_osoba=$ret->s_ido");
  query("UPDATE osoba SET adresa=0,ulice='',psc='',obec='',stat='' WHERE id_osoba=$ret->s_ido");
  if ( $ulice!='' ) query("INSERT INTO _track (kdy,kdo,kde,klic,fld,op,old,val)
     VALUES ('$now','$user','osoba',$ret->s_ido,'ulice','u','$ulice','')");
  if ( $psc!='' ) query("INSERT INTO _track (kdy,kdo,kde,klic,fld,op,old,val)
     VALUES ('$now','$user','osoba',$ret->s_ido,'psc','u','$psc','')");
  if ( $obec!='' ) query("INSERT INTO _track (kdy,kdo,kde,klic,fld,op,old,val)
     VALUES ('$now','$user','osoba',$ret->s_ido,'obec','u','$obec','')");
  if ( $stat!='' ) query("INSERT INTO _track (kdy,kdo,kde,klic,fld,op,old,val)
     VALUES ('$now','$user','osoba',$ret->s_ido,'stat','u','$stat','')");
  return 1;
}
# ---------------------------------------------------------------------------------------- eli_osoba
# zamění všechny výskyty kopie za originál v TVORI, SPOLU, DAR, PLATBA, MAIL a kopii smaže
# pokud je originál v kmenové rodině single, vrátí potom text otázky, zda tuto rodinu zrušit
# a v případě kladné odpovědi volat eli_single
function eli_osoba($id_orig,$id_copy) { trace();
  global $USER;
  $ret= (object)array('err'=>'','continue'=>'');
  $now= date("Y-m-d H:i:s");
  query("UPDATE tvori  SET id_osoba=$id_orig WHERE id_osoba=$id_copy");
  query("UPDATE spolu  SET id_osoba=$id_orig WHERE id_osoba=$id_copy");
  query("UPDATE dar    SET id_osoba=$id_orig WHERE id_osoba=$id_copy");
  query("UPDATE platba SET id_osoba=$id_orig WHERE id_osoba=$id_copy");
  //query("UPDATE mail  SET id_osoba=$id_orig WHERE id_osoba=$id_copy"); -- po úpravě
  query("UPDATE osoba SET deleted='D osoba=$id_orig' WHERE id_osoba=$id_copy");
  // zápis o ztotožnění osob do _track jako op=d (duplicita)
  $user= $USER->abbr;
  query("INSERT INTO _track (kdy,kdo,kde,klic,fld,op,old,val)
         VALUES ('$now','$user','osoba',$id_orig,'','d','osoba',$id_copy)");    // d=duplicita
  // zápis o smazání kopie do _track jako op=x (eXtract)
  query("INSERT INTO _track (kdy,kdo,kde,klic,fld,op,old,val)
         VALUES ('$now','$user','osoba',$id_copy,'','x','single',$id_orig)");    // x=smazání
  // případné nabídnutí dalších úprav
  $s_nazev= $s_adresa= ''; $s_idr= $s_idt= 0;
  $r_nazev= $r_adresa= '';
  $n= 0;
  $rx= mysql_qry("
    SELECT id_rodina,id_tvori,role,id_osoba,prijmeni,jmeno,
      adresa,CONCAT(o.ulice,',',o.psc,',',o.obec) AS o_adresa,
      nazev,CONCAT(r.ulice,',',r.psc,',',r.obec) AS r_adresa,
      (SELECT COUNT(*) FROM tvori WHERE tvori.id_rodina=r.id_rodina) AS _clenu
    FROM osoba AS o
      LEFT JOIN tvori AS t USING(id_osoba)
      LEFT JOIN rodina AS r USING (id_rodina)
    WHERE id_osoba=$id_orig
    ORDER BY role
  ");
  while ( $rx && ($x= mysql_fetch_object($rx)) ) {
    $n++;
    switch ($n) {
    case 1:   // "kmenová" rodina musí být triviální
      if ( $x->role!='a' && $x->role!='b' && $x->_clenu=1 ) goto end;
      $s_adresa= $x->adresa ? $x->o_adresa : $x->r_adresa;
      $s_nazev= $x->nazev;
      $ret->s_idr= $x->id_rodina;
      $ret->s_idt= $x->id_tvori;
      break 2;  // konec cyklu
    case 2:   // původní rodina
      if ( $x->role!='d' ) goto end;
      $r_adresa= $x->r_adresa;
      $r_nazev= $x->nazev;
      $ret->r_idr= $x->id_rodina;
      break;
    default:
      goto end;
    }
  }
  $ret->s_ido= $id_orig;
  $ret->continue= "originál je jako jediný člen v nové 'rodině'
    <br><b>$s_nazev</b>/$ret->s_idr s adresou $s_adresa
    <br>ale je také v původní rodině
    <br><b>$r_nazev</b>/$ret->r_idr s adresou $r_adresa,
    <br>mám tu první 'rodinu' ($s_nazev) zrušit ?";
end:
  return $ret;
}
# ======================================================================================= STATISTIKA
# ----------------------------------------------------------------------------------- sta_ukaz_osobu
# zobrazí odkaz na osobu v evidenci
function sta_ukaz_osobu($ido,$barva='') {
  $style= $barva ? "style='color:$barva'" : '';
  return "<b><a $style href='ezer://db2.evi.evid_osoba/$ido'>$ido</a></b>";
}
# -------------------------------------------------------------------------------------- sta_sestava
# sestavy pro evidenci
function sta_sestava($title,$par,$export=false) {
//                                                 debug($par,"sta_sestava($title,...,$export)");
  $ret= (object)array('html'=>'','err'=>0);
  // dekódování parametrů
  $tits= explode(',',$par->tit);
  $flds= explode(',',$par->fld);
  $clmn= array();
  $expr= array();       // pro výrazy
  // získání dat
  switch ($par->typ) {
  # Sestava ukazuje celkový počet účastníků resp. pečovatelů na akcích letošního roku,
  # rozdělený podle věku. Účastník resp. pečovatel je započítán jen jednou,
  # bez ohledu na počet akcí, jichž se zúčastnil
  case 'ucast-vek':
    $rok= date('Y')-$par->rok;
    $rx= mysql_qry("
      SELECT YEAR(a.datum_od)-YEAR(o.narozeni) AS _vek,MAX(p.funkce) AS _fce
      FROM osoba AS o
      JOIN spolu AS s USING(id_osoba)
      JOIN pobyt AS p USING (id_pobyt)
      JOIN akce  AS a ON id_akce=id_duakce
      WHERE o.deleted='' AND YEAR(datum_od)=$rok
      GROUP BY o.id_osoba
      ORDER BY $par->ord
      ");
    while ( $rx && ($x= mysql_fetch_object($rx)) ) {
      $vek= $x->_vek==$rok ? '?' : $x->_vek;    // ošetření nedefinovaného data narození
      if ( !isset($clmn[$vek]) ) $clmn[$vek]= array('_vek'=>$vek,'_uca'=>0,'_pec'=>0);
      if ( $x->_fce==99 )
        $clmn[$vek]['_pec']++;
      else
        $clmn[$vek]['_uca']++;
    }
    break;
  # Seznam obsahuje účastníky akcí v posledních letech (parametr 'parm' určuje počet let zpět) —
  case 'adresy':
    $rok= date('Y') - $par->parm;
    $rok18= date('Y')-18;
    // úprava title pro případný export do xlsx
    $par->title= $title.($par->rok ? " akcí za poslední ".($par->rok+1)." roky" : " letošních akcí");
    $idr0= -1; $ido= 0;
    $jmena= $role= $prijmeni= $akce= array();
    $adresa= '';
    $mrop= 0;
    // funkce pro přidání nové adresy do clmn: jmena,ulice,psc,obec,stat,akce,prijmeni,_clenu,id_osoba
    $add_address= function() use (&$clmn,&$jmena,&$role,&$prijmeni,&$adresa,&$akce,&$mrop,&$ido) {
      list($pr,$ul,$ps,$ob,$st)= explode('—',$adresa);
      $cl= count($jmena);
      if ( $cl==1 ) {                             // nahrazení názvu příjmením u jediného člena
        $jm= "$jmena[0] $prijmeni[0]";
      }
      else {                                      // klasická rodina
        $xy= preg_match("/\w+[\s\-]+\w+/u",$pr);   //   a rodina s různým příjmením
//                                                 display("$pr = $xy");
        $jm= $pr1= $del= ''; $n= 0;
        for ($i= 0; $i<count($jmena); $i++) {
          if ( $role[$i]=='a' || $role[$i]=='b' ) {
            $n++;
            $pr1= $prijmeni[$i];
            $jm.= "$del $jmena[$i]".($xy ? " $prijmeni[$i]" : '');
            $del= ' a ';
          }
        }
        $jm.= $n==1 ? " $pr1" : ($xy ? '' : " $pr");
      }
      $jc= implode(', ',$jmena);
      $ak= implode(' a ',$akce);
      $mr= $mrop?:'';
      $clmn[]= array('jmena'=>$jm,'ulice'=>$ul,'psc'=>$ps,'obec'=>$ob,'stat'=>$st,
                     'prijmeni'=>$pr,'_cleni'=>$jc,'akce'=>$ak,'_mrop'=>$mr,'_clenu'=>$cl,'id_osoba'=>$ido);
    };
    $rx= mysql_qry("
      SELECT
        IFNULL(IF(adresa=0,SUBSTR(MIN(CONCAT(t.role,r.nazev,'—')),2),prijmeni),prijmeni) AS _order,
        IFNULL(IF(adresa=0,SUBSTR(MIN(CONCAT(t.role,id_rodina)),2),0),0) AS _idr,
        IFNULL(IF(adresa=0,MIN(t.role),'-'),'-') AS _role,
        IFNULL(IF(adresa=0,SUBSTR(MIN(
          CONCAT(t.role,r.nazev,'—',r.ulice,'—',r.psc,'—',r.obec,'—',r.stat)),2),''),'') AS _rodina,
        id_osoba,prijmeni,jmeno,adresa,iniciace,
        -- MAX(CONCAT(YEAR(datum_od),' - ',a.nazev)) as _akce,
        MAX(CONCAT(datum_od,' - ',a.nazev)) as _akce,
        IF(ISNULL(id_rodina) OR adresa=1,CONCAT(o.ulice,'—',o.psc,'—',o.obec,'—',o.stat),'') AS _osoba
      FROM osoba AS o
        LEFT JOIN tvori AS t USING(id_osoba)
        LEFT JOIN rodina AS r USING (id_rodina)
        JOIN spolu AS s USING(id_osoba)
        JOIN pobyt AS p USING (id_pobyt)
        JOIN akce  AS a ON id_akce=id_duakce AND spec=0
      WHERE o.deleted='' AND YEAR(narozeni)<$rok18
        AND YEAR(datum_od)>=$rok AND spec=0
        -- AND o.id_osoba IN(3726,3727,5210)
        -- AND o.id_osoba IN(4537,13,14,3751)
        -- AND o.id_osoba IN(4503,4504,4507,679,680,3612,4531,4532,206,207)
        -- AND id_duakce=394
      GROUP BY o.id_osoba HAVING _role!='p'
      ORDER BY _order
      -- LIMIT 10
      ");
    while ( $rx && ($x= mysql_fetch_object($rx)) ) {
      $idr= $x->_idr;
      if ( $idr0 && $idr0==$idr ) {
        // zůstává rodina a tedy stejná adresa - jen zapamatuj další jméno, příjmení a akci
        $jmena[]= $x->jmeno;
        $role[]= $x->_role;
        $prijmeni[]= $x->prijmeni;
        $akce[]= substr($x->_akce,0,4).substr($x->_akce,10);
        $mrop= max($mrop,$x->iniciace);
      }
      else {
        // uložíme rodinu
        if ( $idr0!=-1 ) $add_address();
        // inicializace údajů další rodiny
        $ido= $x->id_osoba;
        $jmena= array($x->jmeno);
        $role= array($x->_role);
        $prijmeni= array($x->prijmeni);
        $akce= array(substr($x->_akce,0,4).substr($x->_akce,10));
        $mrop= $x->iniciace;
        $adresa= $x->_osoba ? "{$x->prijmeni}—$x->_osoba" : $x->_rodina;
        $idr0= $idr;
      }
    }
    $add_address();
    break;
  }
end:
  if ( $ret->err )
    return $ret;
  else
    return sta_table($tits,$flds,$clmn,$export);
}
# ---------------------------------------------------- sta_excel_subst
function sta_sestava_adresy_fill($matches) { trace();
  global $xA, $xn;
//                                                 debug($xA);
//                                                 debug($matches);
  if ( !isset($xA[$matches[1]]) ) fce_error("sta_excel_subst: chybný název sloupce '{$matches[1]}'");
  $A= $xA[$matches[1]];
  $n= $xn+$matches[2];
  return "$A$n";
}
# ---------------------------------------------------------------------------------------- sta_table
function sta_table($tits,$flds,$clmn,$export=false) {  trace();
  $ret= (object)array();
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
    // titulky
    foreach ($tits as $idw) {
      list($id)= explode(':',$idw);
      $ths.= "<th>$id</th>";
    }
    foreach ($clmn as $i=>$c) {
      $tab.= "<tr>";
      foreach ($flds as $f) {
        if ( $f=='id_osoba' ) {
          $tab.= "<td style='text-align:right'>".sta_ukaz_osobu($c[$f])."</td>";
        }
        else {
          $tab.= "<td style='text-align:left'>{$c[$f]}</td>";
        }
      }
      $tab.= "</tr>";
      $n++;
    }
    $ret->html= "Seznam má $n řádků<br><br><div class='stat'><table class='stat'><tr>$ths</tr>$tab</table></div>";
  }
  return $ret;
}
# obsluha různých forem výpisů karet AKCE
# ---------------------------------------------------------------------------------------- sta_excel
# generování tabulky do excelu
function sta_excel($title,$par,$tab=null) {  trace();
  global $xA, $xn;
  $result= (object)array('_error'=>0);
  $html= '';
  // získání dat
  $title= str_replace('&nbsp;',' ',$title);
  $subtitle= "ke dni ".date("j. n. Y");
  if ( !$tab ) {
    $tab= sta_sestava($title,$par,true);
    $title= $par->title ?: $title;
  }
  // vlastní export do Excelu
  $name= cz2ascii("vypis_").date("Ymd_Hi");
  $xls= <<<__XLS
    |open $name
    |sheet vypis;;L;page
    |A1 $title ::bold size=14 |A2 $subtitle ::bold size=12
__XLS;
  // titulky a sběr formátů
  $fmt= $sum= array();
  $n= 4;
  $lc= 0;
  $clmns= $del= '';
  $xA= array();                                 // překladová tabulka: název sloupce => písmeno
  if ( $tab->flds ) foreach ($tab->flds as $f) {
    $A= Excel5_n2col($lc);
    $xA[$f]= $A;
    $lc++;
  }
  $lc= 0;
  if ( $tab->tits ) foreach ($tab->tits as $idw) {
    $A= Excel5_n2col($lc);
    list($id,$w,$f,$s)= explode(':',$idw);      // název sloupce : šířka : formát : suma
    if ( $f ) $fmt[$A]= $f;
    if ( $s ) $sum[$A]= true;
    $xls.= "|$A$n $id";
    if ( $w ) {
      $clmns.= "$del$A=$w";
      $del= ',';
    }
    $lc++;
  }
  if ( $clmns ) $xls.= "\n|columns $clmns ";
  $xls.= "\n|A$n:$A$n bcolor=ffffbb00 wrap border=+h|A$n:$A$n border=t\n";
  $n1= $n= 5;                                   // první řádek dat (pro sumy)
  // datové řádky
  if ( $tab->clmn ) foreach ($tab->clmn as $i=>$c) {
    $xls.= "\n";
    $lc= 0;
    foreach ($c as $id=>$val) {
      $A= Excel5_n2col($lc);
      $format= '';
      if (isset($tab->expr[$i][$id]) ) {
        // buňka obsahuje vzorec
        $val= $tab->expr[$i][$id];
        $format.= ' bcolor=ffdddddd';
        $xn= $n;
        $val= preg_replace_callback("/\[([^,]*),([^\]]*)\]/","sta_excel_subst",$val);
      }
      else {
        // buňka obsahuje hodnotu
        $val= strtr($val,"\n\r","  ");
        if ( isset($fmt[$A]) ) {
          switch ($fmt[$A]) {
          // aplikace formátů
          case 'd': $val= sql2xls($val); $format.= ' right date'; break;
          }
        }
      }
      $format= $format ? "::$format" : '';
      $xls.= "|$A$n $val $format";
      $lc++;
    }
    $n++;
  }
  $n--;
  $xls.= "\n|A$n1:$A$n border=+h|A$n1:$A$n border=t";
  // sumy sloupců
  if ( count($sum) ) {
    $xls.= "\n";
    $nn= $n;
    $ns= $n+2;
    foreach ($sum as $A=>$x) {
      $xls.= "|$A$ns =SUM($A$n1:$A$nn) :: bcolor=ffdddddd";
    }
  }
  // konec
  $xls.= <<<__XLS
    \n|close
__XLS;
  // výstup
//   $inf= Excel2007($xls,1);
  $inf= Excel5($xls,1);
  if ( $inf ) {
    $html= " se nepodařilo vygenerovat - viz začátek chybové hlášky";
    fce_error($inf);
  }
  else {
    $html= " Výpis byl vygenerován ve formátu <a href='docs/$name.xls' target='xlsx'>Excel</a>.";
  }
  $result->html= $html;
  return $result;
}
# ---------------------------------------------------- sta_excel_subst
function sta_excel_subst($matches) { trace();
  global $xA, $xn;
//                                                 debug($xA);
//                                                 debug($matches);
  if ( !isset($xA[$matches[1]]) ) fce_error("sta_excel_subst: chybný název sloupce '{$matches[1]}'");
  $A= $xA[$matches[1]];
  $n= $xn+$matches[2];
  return "$A$n";
}
// # ---------------------------------------------------- sql2xls
// // datum bez dne v týdnu
// function sql2xls($datum) {
//   // převeď sql tvar na uživatelskou podobu (default)
//   $text= ''; $del= '.';
//   if ( $datum && substr($datum,0,10)!='0000-00-00' ) {
//     $y=substr($datum,0,4);
//     $m=substr($datum,5,2);
//     $d=substr($datum,8,2);
//     $text.= date("j{$del}n{$del}Y",strtotime($datum));
//   }
//   return $text;
// }
# ========================================================================================= EVIDENCE
# --------------------------------------------------------------------------------------- elim_rodne
function elim_rodne() {
  $html= "Tipy na shodu žen podle jejich rodného jména:";
  $tip= array();
  $zs= mysql_qry("
        SELECT id_osoba,prijmeni,rodne,jmeno,narozeni,psc,obec,
          (SELECT COUNT(*) AS _n FROM osoba AS oo WHERE oo.deleted='' AND o.rodne LIKE oo.prijmeni
            AND oo.jmeno=o.jmeno AND YEAR(oo.narozeni)=YEAR(o.narozeni)) AS x
        FROM osoba AS o
        WHERE deleted='' AND rodne!=''
        GROUP BY id_osoba HAVING x>0
        ORDER BY prijmeni");
  while (($z= mysql_fetch_object($zs))) {
    $tip[]= "$z->prijmeni $z->jmeno rozená $z->rodne";
  }
  if ( count($tip) ) {
    $html.= "<br>".implode("<br>",$tip);
  }
  else {
    $html.= "<br>... nic jsem nenalezl";
  }
  return $html;
}
# ---------------------------------------------------------------------------------------- elim_stav
function elim_stav() {
  global $ezer_root,$dbs;
  $stav= array(
    "ezer_root"=>$ezer_root,
    "dbs"=>$dbs
  );
//                                         debug($stav);
//                                         debug($_SESSION);
  return 1;
}
# -------------------------------------------------------------------------------- elim_copy_test_db
# zkopíruje důležité tabulky z ezer_$db do ezer_$db_test
function elim_copy_test_db($db) {  trace();
  $ok= mysql_qry("SET sql_mode = 'NO_AUTO_VALUE_ON_ZERO'");
  // tabulka¨, která se má jen vytvořit, má před jménem hvězdičku
  $tabs= explode(',',
//     "_user,_skill,"
    "_help,_cis,"
  . "*_touch,_track,*_todo,ezer_doc2,"
  . "akce,cenik,pobyt,spolu,osoba,tvori,rodina,g_akce,join_akce,"
  . "dar,platba,"
  . "dopis,mail,mailist"
  );
  foreach ($tabs as $xtab ) {
    $tab= $xtab;
    if ( $tab[0]=='*' ) $tab= substr($tab,1);
    if ( $ok ) $ok= mysql_qry("DROP TABLE IF EXISTS ezer_{$db}_test.$tab");
    if ( $ok ) $ok= mysql_qry("CREATE TABLE ezer_{$db}_test.$tab LIKE ezer_{$db}.$tab");
    if ( $xtab[0]!='*' )
      if ( $ok ) $ok= mysql_qry("INSERT INTO ezer_{$db}_test.$tab SELECT * FROM ezer_{$db}.$tab");
  }
  ezer_connect("ezer_{$db}");   // jinak zůstane přepnuté na test
  return $ok ? 'ok' : 'ko';
}
# ---------------------------------------------------------------------------------- elim_data_osoba
# načte data OSOBA+TVORI včetně záznamů v _track
function elim_data_osoba($ido) {  trace();
  $ret= (object)array();
  // načtení změn
  $chng_kdy= $chng_kdo= $chng_val= array();
  $max_kdy= '';
  $zs= mysql_qry("
    SELECT fld,kdo,kdy,val,op
    FROM _track
    WHERE kde='osoba' AND klic=$ido
  ");
  while (($z= mysql_fetch_object($zs))) {
    $fld= $z->fld;
    $kdy= $z->kdy;
    $kdo= $z->kdo;
    $op=  $z->op;
    $val= $z->val;
    $max_kdy= max($max_kdy,substr($kdy,0,10));
    if ( !isset($chng_kdy[$fld]) || isset($chng_kdy[$fld]) && strcmp($chng_kdy[$fld],$kdy)<0 ) {
      $chng_kdy[$fld]= $kdy;
      $chng_kdo[$fld]= "$kdo/$op: ".sql_date1($kdy);
      $chng_val[$fld]= $val;
    }
  }
  $ret->last_chng= $max_kdy;
  $ret->chng= $chng_kdo;
  // načtení hodnot
  $os= mysql_qry("
    SELECT MAX(CONCAT(datum_od,':',a.nazev)) AS _last,
      prijmeni,jmeno,sex,narozeni,rc_xxxx,psc,obec,ulice,email,telefon,o.note
    FROM osoba AS o
    LEFT JOIN spolu AS s USING(id_osoba)
    LEFT JOIN pobyt AS p USING(id_pobyt)
    LEFT JOIN akce AS a ON p.id_akce=a.id_duakce
    WHERE id_osoba=$ido GROUP BY id_osoba
  ");
  $o= mysql_fetch_object($os);
  foreach($o as $fld=>$val) {
    if ( $chng_kdy[$fld] && $chng_val[$fld]!=$val ) {
      $ret->diff[$fld]= $chng_val[$fld];
      $ret->chng[$fld]= "!{$ret->chng[$fld]}: {$chng_val[$fld]}";
    }
  }
  $ret->last_akce= $o->_last;
  // zjištění kmenové rodiny
  $kmen= ''; $idk= 0;
  $rs= mysql_qry("
    SELECT id_rodina,role,nazev
    FROM osoba AS o
    LEFT JOIN tvori AS t USING(id_osoba)
    LEFT JOIN rodina AS r USING(id_rodina)
    WHERE id_osoba=$ido
  ");
  while (($r= mysql_fetch_object($rs))) {
    if ( !$kmen || $r->role=='a' || $r->role=='b' ) {
      $kmen= $r->nazev;
      $idk= $r->id_rodina;
    }
  }
  $ret->kmen= $kmen;
  $ret->id_kmen= $idk;
                                                        debug($ret,"elim_data_osoba");
  return $ret;
}
# --------------------------------------------------------------------------------- elim_data_rodina
# načte data RODINA včetně záznamů v _track
function elim_data_rodina($idr) {  trace();
  $ret= (object)array();
  // načtení změn
  $chng_kdy= $chng_kdo= $chng_val= array();
  $max_kdy= '';
  $zs= mysql_qry("
    SELECT fld,kdo,kdy,val,op
    FROM _track
    WHERE kde='rodina' AND klic=$idr
  ");
  while (($z= mysql_fetch_object($zs))) {
    $fld= $z->fld;
    $kdy= $z->kdy;
    $kdo= $z->kdo;
    $op=  $z->op;
    $val= $z->val;
    $max_kdy= max($max_kdy,substr($kdy,0,10));
    if ( !isset($chng_kdy[$fld]) || isset($chng_kdy[$fld]) && strcmp($chng_kdy[$fld],$kdy)<0 ) {
      $chng_kdy[$fld]= $kdy;
      $chng_kdo[$fld]= "$kdo/$op: ".sql_date1($kdy);
      $chng_val[$fld]= $val;
    }
  }
  $ret->last_chng= sql_date1($max_kdy);
  $ret->chng= $chng_kdo;
  // načtení hodnot
  $os= mysql_qry("
    SELECT r.*, MAX(CONCAT(datum_od,': ',a.nazev)) AS _last
    FROM rodina AS r
    LEFT JOIN tvori AS t USING (id_rodina)
    LEFT JOIN spolu AS s USING (id_osoba)
    LEFT JOIN pobyt AS p USING (id_pobyt)
    LEFT JOIN akce AS a ON id_akce=id_duakce
    WHERE id_rodina=$idr
    GROUP BY id_rodina
  ");
  $o= mysql_fetch_object($os);
  foreach($o as $fld=>$val) {
    $ret->$fld= $val;
    if ( $chng_kdy[$fld] && $chng_val[$fld]!=$val ) {
      $ret->diff[$fld]= $chng_val[$fld];
      $ret->chng[$fld]= "!{$ret->chng[$fld]}: {$chng_val[$fld]}";
    }
  }
  $ret->datsvatba= sql_date1($ret->datsvatba);                  // svatba d.m.r
  $ret->last_akce= sql_date1(substr($o->_last,0,10)).substr($o->_last,10);
//                                                         debug($ret,"elim_data_rodina");
  return $ret;
}
# -------------------------------------------------------------------------------------- evid_delete
# zjistí, zda lze osobu smazat: dar, platba, spolu, tvori
# cmd= conf_oso|conf_rod|del_oso|del_rod
function evid_delete($id_osoba,$id_rodina,$cmd='confirm') { trace();
  global $USER;
  $ret= (object)array('html'=>'','ok'=>1);
  $user= $USER->abbr;
  $now= date("Y-m-d H:i:s");
  $duvod= array();
  list($name,$sex)= select("CONCAT(prijmeni,' ',jmeno),sex",'osoba',"id_osoba=$id_osoba");
  $a= $sex==2 ? 'a' : '';
  $nazev= select("nazev",'rodina',"id_rodina=$id_rodina");
  switch ($cmd) {
  case 'conf_oso':
    $x= select1('SUM(castka)','dar',"id_osoba=$id_osoba");
    if ( $x) $duvod[]= "je dárcem $x Kč";
    $x= select1('SUM(castka)','platba',"id_osoba=$id_osoba");
    if ( $x) $duvod[]= "zaplatil$a $x Kč";
    $x= select1('COUNT(*)','spolu',"id_osoba=$id_osoba");
    if ( $x) $duvod[]= "se zúčastnil$a $x akcí";
    $x= select1('COUNT(*)','tvori',"id_osoba=$id_osoba AND id_rodina!=$id_rodina");
    if ( $x) $duvod[]= "je členem dalších $x rodin";
    $ret->ok= count($duvod) ? 0 : 1;
    if ( $ret->ok ) {                   // lze smazat, nezůstane ale rodina prázdná?
      $x= select1('COUNT(*)','tvori',"id_rodina=$id_rodina");
      $ret->html= $x==1 ? "$name je jediným členem své rodiny, smazat i tu?" : "Opravdu smazat $name ?";
      $ret->ok= $x==1 ? 2 : 1;
    }
    else {                              // nelze smazat - existují odkazy
      $ret->html= "$name nejde smazat, protože ".implode(',',$duvod);
    }
    break;
  case 'conf_mem':
//     $x= select1('COUNT(*)','tvori',"id_osoba=$id_osoba AND id_rodina!=$id_rodina");
//     if ( !$x ) $duvod[]= "není členem žádné další rodiny";
//     $ret->ok= count($duvod) ? 0 : 1;
    if ( $ret->ok ) {                   // lze vyjmout, nezůstane ale rodina prázdná?
      $x= select1('COUNT(*)','tvori',"id_rodina=$id_rodina");
      $ret->html= $x==1 ? "$name je jediným členem rodiny $nazev, smazat ji?"
        : "Opravdu vyjmout $name z $nazev?";
      $ret->ok= $x==1 ? 2 : 1;
    }
    else {                              // nelze smazat - existují odkazy
      $ret->html= "$name nejde vyjmout z $nazev, protože ".implode(',',$duvod);
    }
    break;
  case 'conf_rod':
    $x= select1('COUNT(*)','tvori',"id_rodina=$id_rodina");
    $ret->html= $x==0 ? "Opravdu smazat prázdnou rodinu $nazev?"
      : "Rodinu $nazev nelze smazat, protože obsahuje $x členů - nejprve je třeba je vyjmout nebo vymazat";
    $ret->ok= $x==0 ? 1 : 0;
    break;
  case 'del_mem':
    $ret->ok= query("DELETE FROM tvori WHERE id_osoba=$id_osoba AND id_rodina=$id_rodina") ? 1 : 0;
    $ret->html= "$name byl$a vyjmut$a z $nazev";
    break;
  case 'del_oso':
    $ret->ok= query("UPDATE osoba SET deleted='D' WHERE id_osoba=$id_osoba") ? 1 : 0;
    query("INSERT INTO _track (kdy,kdo,kde,klic,fld,op,old,val)
           VALUES ('$now','$user','osoba',$id_osoba,'','x','','')");
    query("DELETE FROM tvori WHERE id_osoba=$id_osoba AND id_rodina=$id_rodina");
    $ret->html= "$name byl$a smazán$a";
    break;
  case 'undel_oso':
    $ret->ok= query("UPDATE osoba SET deleted='' WHERE id_osoba=$id_osoba") ? 1 : 0;
    query("INSERT INTO _track (kdy,kdo,kde,klic,fld,op,old,val)
           VALUES ('$now','$user','osoba',$id_osoba,'','o','','')");    // o=obnova
    $ret->html= "$name byl$a obnoven$a";
    break;
  case 'del_rod':
    query("UPDATE osoba JOIN tvori USING (id_osoba) SET deleted='D' WHERE id_rodina=$id_rodina");
    $no= mysql_affected_rows();
    query("UPDATE rodina SET deleted='D' WHERE id_rodina=$id_rodina");
    $nr= mysql_affected_rows();
    query("INSERT INTO _track (kdy,kdo,kde,klic,fld,op,old,val)
           VALUES ('$now','$user','rodina',$id_rodina,'','x','','')");
    query("DELETE FROM tvori WHERE id_rodina=$id_rodina");
    $ret->ok= $no && $nr ? 1 : 0;
    $ami= $no==1 ? "ou" : "ami";
    $ret->html= "Byla smazána rodina s $no osob$ami";
    break;
  case 'undel_rod':
    $ret->ok= query("UPDATE rodina SET deleted='' WHERE id_rodina=$id_rodina") ? 1 : 0;
    query("INSERT INTO _track (kdy,kdo,kde,klic,fld,op,old,val)
           VALUES ('$now','$user','rodina',$id_rodina,'','o','','')");    // o=obnova
    $ret->html= "rodina $nazev byla obnovena";
    break;
  }
  return $ret;
}
# ----------------------------------------------------------------------------------- akce_save_role
# zapíše roli - je to netypická číselníková položka definovaná jako VARCHAR(1)
function akce_save_role($id_tvori,$role) { //trace();
  return mysql_qry("UPDATE tvori SET role='$role' WHERE id_tvori=$id_tvori");
}
# --------------------------------------------------------------------------------------- evid_cleni
# hledání a) osoby a jejích rodin b) rodiny (pokud je id_osoba=0)
function evid_cleni($id_osoba,$id_rodina,$filtr) { trace();
  $msg= '';
  $cleni= "";
  $rodiny= array();
  $rodina= $rodina1= $id_rodina;
  $id_osoba ? "o.id_osoba=$id_osoba" : "r.id_rodina=$id_rodina";
  if ( $id_osoba ) { // ------------------------ osoby
    $clen= array();
    $qc= mysql_qry("
      SELECT rto.id_osoba,rto.jmeno,rto.prijmeni,rto.narozeni,rt.id_tvori,rt.role,o.deleted,
        r.id_rodina,nazev
      FROM osoba AS o
        JOIN tvori AS ot ON ot.id_osoba=o.id_osoba
        JOIN rodina AS r ON r.id_rodina=ot.id_rodina
        JOIN tvori AS rt ON rt.id_rodina=r.id_rodina
        JOIN osoba AS rto ON rto.id_osoba=rt.id_osoba
      WHERE o.id_osoba=$id_osoba AND $filtr
      ORDER BY rt.role,rto.narozeni
    ");
    while ( $qc && ($c= mysql_fetch_object($qc)) ) {
      $ido= $c->id_osoba;
      $idr= $c->id_rodina;
      $clen[$idr][$ido]= $c;
      $clen[0][$ido].= ",{$c->nazev}:$idr";
      $clen[$idr][$ido]->_vek= $c->narozeni=='0000-00-00' ? '?' : roku_k($c->narozeni);
      // určení zobrazené rodiny
      if ( !$rodina ) $rodina1=  $c->id_rodina;
      if ( !$rodina && $ido==$id_osoba && ($c->role=='a' || $c->role=='b'))  $rodina= $c->id_rodina;
    }
    if ( !$rodina ) $rodina= $rodina1;
//                                                 debug($clen,"rodina=$rodina");
    if ($clen[$rodina]) foreach($clen[$rodina] as $ido=>$c) {
      if ( $rodina && ($c->id_rodina==$rodina ||$c->id_osoba==$id_osoba)) {
        $rodiny= substr($clen[0][$ido],1);
        $role= $c->role;
        $barva= $c->deleted=='';  // nesmazaný
        $cleni.= "|$ido|$c->id_tvori|$barva|$rodiny|$c->prijmeni $c->jmeno|$c->_vek|$role";
      }
    }
  }
  else { // ------------------------------------ rodiny
    $qc= mysql_qry("
      SELECT rto.id_osoba,rto.jmeno,rto.prijmeni,rto.narozeni,rt.id_tvori,rt.role,r.id_rodina,r.nazev,
        GROUP_CONCAT(CONCAT(otr.nazev,':',otr.id_rodina)) AS _rodiny,rto.deleted
      FROM rodina AS r
        JOIN tvori AS rt ON rt.id_rodina=r.id_rodina
        JOIN osoba AS rto ON rto.id_osoba=rt.id_osoba
        JOIN tvori AS ot ON ot.id_osoba=rto.id_osoba
        JOIN rodina AS otr ON otr.id_rodina=ot.id_rodina
      WHERE r.id_rodina=$id_rodina AND $filtr
      GROUP BY id_osoba
      ORDER BY rt.role,rto.narozeni
    ");
    while ( $qc && ($c= mysql_fetch_object($qc)) ) {
      if ( !isset($rodiny[$c->id_rodina]) ) {
        $rodiny[$c->id_rodina]= "$c->nazev:$c->id_rodina";
        if ( !$rodina ) $rodina= $c->id_rodina;
      }
      if ( $c->id_rodina!=$rodina ) continue;
      $vek= $c->narozeni=='0000-00-00' ? '?' : roku_k($c->narozeni);
      $barva= $c->deleted=='';  // nesmazaný
      $cleni.= "|$c->id_osoba|$c->id_tvori|$barva|$c->_rodiny|$c->prijmeni $c->jmeno|$vek|$c->role";
//                                                         display("{$c->jmeno} {$c->narozeni} $vek");
    }
    $msg= $cleni ? '' : "rodina neobsahuje žádné členy";
  }
  $ret= (object)array('cleni'=>$cleni ? substr($cleni,1) : '','rodina'=>$rodina,'msg'=>$msg);
//                                                         debug($ret);
  return $ret;
}
# ======================================================================= EVIDENCE - BROWSE - ÚČASTI
# ------------------------------------------------------------------------------ evid_browse_act_ask
# obsluha browse s optimize:ask
# x->order= {a|d} polozka
# x->show=  {polozka:[formát,vzor/1,...],...} pro položky s neprázdným vzorem
#                                             kde formát=/ = # $ % @ * .
# x->cond= podmínka
function evid_browse_act_ask($x) {
  global $y;
  $y= (object)array('ok'=>0);
  foreach(explode(',','cmd,rows,quiet,key_id,oldkey') as $i) $y->$i= $x->$i;
  switch ($x->cmd) {
  case 'browse_load':  # ------------------------------------- browse_load
    $n= 0;
    $order= $x->order[0]=='a' ? substr($x->order,2).' ASC,' : (
            $x->order[0]=='d' ? substr($x->order,2).' DESC,' : '');
    $y->from= 0;
    $y->cursor= 0;
    $y->values= array();
    $qp= mysql_qry("
      SELECT a.id_duakce as ida,p.id_pobyt as idp,s.id_spolu as ids,p.funkce as fce,
        YEAR(a.datum_od) as rok,a.nazev as akce,p.funkce as _fce,narozeni,datum_od
      FROM akce AS a
      JOIN pobyt AS p ON a.id_duakce=p.id_akce
      JOIN spolu AS s USING(id_pobyt)
      JOIN osoba AS o USING(id_osoba)
      WHERE $x->cond
      ORDER BY $order a.id_duakce
      LIMIT 0,50
    ");
    while ( $qp && ($p= mysql_fetch_object($qp)) ) {
      $n++;
      $p->_vek= $p->narozeni!='0000-00-00' ? roku_k($p->narozeni,$p->datum_od) : '?';      // výpočet věku
      if ( $p->_vek<18 ) { $p->fce= 0; $p->_fce= '_'; }
      unset($p->datum_od,$p->narozeni);
      $y->values[]= $p;
    }
    array_unshift($y->values,null);
    $y->count= $n;
    $y->rows= $n;
    $y->ok= 1;
    break;
  default:
    fce_warning("N.Y.I. evid_browse_act_ask/{$x->cmd}");
    $y->ok= 0;
    break;
  }
  return $y;
}
# ======================================================================================== ÚČASTNÍCI
# ---------------------------------------------------------------------------- akce2_pridej_k_pobytu
# ASK získání pobytu účastníka na akci
function akce2_ido2idp($id_osoba,$id_akce) { trace();
  $idp= select("id_pobyt","spolu JOIN pobyt USING (id_pobyt)",
    "spolu.id_osoba=$id_osoba AND id_akce=$id_akce");
  return $idp;
}
# ---------------------------------------------------------------------------- akce2_pridej_k_pobytu
# ASK přidání do daného pobytu akce, pokud ještě osoba na akci není
# spolupracuje s: akce2_auto_jmena1,akce2_auto_jmena1L a číselníky: ms_akce_s_role,ms_akce_dite_kat
# info = {id,nazev,role}
function akce2_pridej_k_pobytu($id_akce,$id_pobyt,$info,$cnd='') { trace();
  $ret= (object)array('spolu'=>0,'msg'=>'');
  $ido= $info->id;
  $je= select("COUNT(*)","pobyt JOIN spolu USING(id_pobyt)","id_akce=$id_akce AND id_osoba=$ido");
  if ( $je ) {
    $ret->msg= "$info->nazev už je na této akci";
  }
  else {
    // zjištění stáří
    $datum_od= select("datum_od","akce","id_duakce=$id_akce");
    $narozeni= select("narozeni","osoba","id_osoba=$ido");
    $vek= roku_k($narozeni,$datum_od);
    $role= $info->role;
    $kat= $srole= 0;                                            // host
    // odhad typu účasti podle stáří a role
    if ( $role=='a' || $info->role=='b' )        $srole= 1;     // účastník
    elseif ( $role=='p' || $vek>=18 )            $srole= 5;     // osob.peč.
    elseif ( $role=='d' && $vek>=17 )            $srole= 6;     // dítě - G
    elseif ( $role=='d' && $vek>=13 ) { $kat= 1; $srole= 2; }   // dítě - A
    elseif ( $role=='d' && $vek>=3 )  { $kat= 3; $srole= 2; }   // dítě - C
    elseif ( $role=='d' && $vek>=2 )  { $kat= 5; $srole= 2; }   // dítě - E
    elseif ( $role=='d' && $vek>0 )   { $kat= 6; $srole= 2; }   // dítě - F
    if ( query("INSERT INTO spolu (id_pobyt,id_osoba,s_role,dite_kat)
         VALUE ($id_pobyt,$ido,$srole,$kat)") ) {
      $ret->spolu= mysql_insert_id();
    }
    else  $ret->msg= 'chyba při vkládání';
  }
                                                debug($ret,"$vek $kat $srole");
  return $ret;
}
# ----------------------------------------------------------------------------- evid_pridej_k_rodine
# ASK přidání do dané rodiny, pokud ještě osoba v rodině není
# spolupracuje s: evid_auto_jmena1,akce2_auto_jmena1L
# info = {id,nazev,role}
function evid_pridej_k_rodine($id_rodina,$info,$cnd='') { trace();
  $ret= (object)array('tvori'=>0,'msg'=>'');
  $ido= $info->id;
  $je= select("COUNT(*)","tvori","id_rodina=$id_rodina AND id_osoba=$ido");
  if ( $je ) {
    $ret->msg= "$info->nazev už v rodině je";
  }
  else {
    if ( query("INSERT INTO tvori (id_rodina,id_osoba,role) VALUE ($id_rodina,$ido,'p')") ) {
      $ret->tvori= mysql_insert_id();
      $ret->ok= 1;
    }
    else  $ret->msg= 'chyba při vkládání';
  }
  return $ret;
}
# -------------------------------------------------------------------------------- akce2_auto_jmena1
# SELECT autocomplete - výběr z dospělých osob, pokud je par.deti=1 i z deti
function akce2_auto_jmena1($patt,$par) {  #trace();
  $a= array();
  $limit= 20;
  $dnes= date("Y-m-d");
  $n= 0;
  if ( $par->patt!='whole' ) {
    $is= strpos($patt,' ');
    $patt= $is ? substr($patt,0,$is) : $patt;
  }
  // osoby
  $AND= $par->deti ? '' : "AND (narozeni='0000-00-00' OR DATEDIFF('$dnes',narozeni)/365.2425>18)";
  $qry= "SELECT prijmeni, jmeno, id_osoba AS _key
         FROM osoba
         LEFT JOIN tvori USING(id_osoba)
         WHERE deleted='' AND concat(trim(prijmeni),' ',jmeno) LIKE '$patt%' AND prijmeni!='' $AND
         ORDER BY prijmeni,jmeno LIMIT $limit";
  $res= mysql_qry($qry);
  while ( $res && $t= mysql_fetch_object($res) ) {
    if ( ++$n==$limit ) break;
    $key= $t->_key;
    $a[$key]= "{$t->prijmeni} {$t->jmeno}";
  }
  // obecné položky
  if ( !$n )
    $a[0]= "... žádné příjmení nezačíná '$patt'";
  elseif ( $n==$limit )
    $a[-999999]= "... a další";
//                                                                 debug($a,$patt);
  return $a;
}
# ------------------------------------------------------------------------------- akce2_auto_jmena1L
# formátování autocomplete
function akce2_auto_jmena1L($id_osoba) {  #trace();
  $osoba= array();
  $qry= "SELECT prijmeni, jmeno, id_osoba, YEAR(narozeni) AS rok, role,
           IF(adresa,o.ulice,r.ulice) AS ulice,
           IF(adresa,o.psc,r.psc) AS psc, IF(adresa,o.obec,r.obec) AS obec,
           IF(kontakt,o.telefon,r.telefony) AS telefon, IF(kontakt,o.email,r.emaily) AS email
         FROM osoba AS o
         LEFT JOIN tvori AS t USING(id_osoba)
         LEFT JOIN rodina AS r USING(id_rodina)
         WHERE id_osoba='$id_osoba'
         ORDER BY role";                                // preference 'a' či 'b'
  $res= mysql_qry($qry);
  if ( $res && $p= mysql_fetch_object($res) ) {
    $nazev= "$p->prijmeni $p->jmeno / $p->rok, $p->obec, $p->ulice, $p->email, $p->telefon";
    $osoba[]= (object)array('nazev'=>$nazev,'id'=>$id_osoba,'role'=>$p->role);
  }
//                                                                 debug($osoba,$id_akce);
  return $osoba;
}
# =============================================================================== ÚČASTNÍCI - BROWSE
# ---------------------------------------------------------------------------------- akce_browse_ask
# obsluha browse s optimize:ask
# x->order= {a|d} polozka
# x->show=  {polozka:[formát,vzor/1,...],...} pro položky s neprázdným vzorem
#                                             kde formát=/ = # $ % @ * .
# x->cond= podmínka
function akce_browse_ask($x) {
  // dekódování seznamu položek na pole ...x,y=z... na [...x=>x,y=>z...]
  function flds($fstr) {
    $fp= array();
    foreach(explode(',',$fstr) as $fzp) {
      list($fz,$f)= explode('=',$fzp);
      $fp[$fz]= $f ?: $fz ;
    }
    return $fp;
  }
  global $test_clmn,$test_asc, $y;
  $y= (object)array('ok'=>0);
  foreach(explode(',','cmd,rows,quiet,key_id,oldkey') as $i) $y->$i= $x->$i;
  switch ($x->cmd) {
  case 'browse_load':  # ------------------------------------- browse_load
  default:
    # vnořené SQL definující @akce, @soubeh, @app
    if ( $x->sql ) mysql_qry($x->sql);
    $pobyt= array();              // $pobyt[id_pobyt]             vše
    $skup= array();               // $skup[skupina]               seznam id_pobyt
    $osoba= array();              // $osoba[id_osoba]             atributy osob na akci
    $cleni= "";
    $osoby= "";
    $rodina= array();             // $rodina[id_rodina]           atributy rodin na akci
    $rodiny= "";
    $spolu= array();              // $spolu[id_osoba]             id_pobyt
    $tvori= array();              // $tvori[id_pobyt,id_osoba]    id_tvori,id_rodina,role,rodiny
    # ladění
    $AND= "";
//     $AND= "AND p.id_pobyt IN (20825) -- Brtník";
//     $AND= "AND p.id_pobyt IN (20488) -- Bajerovi";
//     $AND= "AND p.id_pobyt IN (20749) -- Buchtovi";
//     $AND= "AND p.id_pobyt IN (20493) -- Dykastovi";
//     $AND= "AND p.id_pobyt IN (20487) -- Baklík Baklíková";
//     $AND= "AND p.id_pobyt IN (20488,20344) -- Bajerovi a Kubínovi";
//     $AND= "AND p.id_pobyt IN (20568,20793) -- Šmídkovi + Nečasovi";

    # kontext dotazu
    if ( !$x ) $q0= mysql_qry("SET @akce:=422,@soubeh:=0,@app:='ys';");
    # podmínka
    $cond= $x->cond ?: 1;
    # atributy akce
    $qa= mysql_qry("
      SELECT @akce,@soubeh,@app,
        datum_od,DATEDIFF(a.datum_do,a.datum_od)+1 AS dnu,ma_cenik,ma_cenu,cena
      FROM akce AS a
      WHERE a.id_duakce=@akce ");
    $akce= mysql_fetch_object($qa);
    # atributy pobytu
    $qp= mysql_qry("
      SELECT *
      FROM pobyt AS p
      WHERE $cond $AND ");
    while ( $qp && ($p= mysql_fetch_object($qp)) ) {
      $pobyt[$p->id_pobyt]= $p;
    }
    # seznam účastníků akce - podle podmínky
    $qu= mysql_qry("
      SELECT s.*,o.narozeni,MIN(CONCAT(role,id_rodina)) AS _role
      FROM osoba AS o
      JOIN spolu AS s USING (id_osoba)
      JOIN pobyt AS p USING (id_pobyt)
      LEFT JOIN tvori AS t USING (id_osoba)
      WHERE o.deleted='' AND $cond $AND
      GROUP BY id_osoba
    ");
    while ( $qu && ($u= mysql_fetch_object($qu)) ) {
      $cleni.= ",{$u->id_osoba}";
      $rodiny.= ",".substr($u->_role,1);
      $pobyt[$u->id_pobyt]->cleni[$u->id_osoba]= $u;
      $spolu[$u->id_osoba]= $u->id_pobyt;
    }
    $osoby.= $cleni;
    # seznam rodinných příslušníků
    $qp= mysql_qry("
      SELECT id_pobyt,id_rodina,id_tvori,id_osoba,role,o.narozeni
      FROM pobyt AS p
      JOIN tvori AS t ON t.id_rodina=p.i0_rodina
      JOIN osoba AS o USING(id_osoba)
      WHERE $cond $AND
    ");
    while ( $qp && ($p= mysql_fetch_object($qp)) ) {
      $osoby.= ",{$p->id_osoba}";
      $rodiny.= ",{$p->id_rodina}";
      if ( !isset($pobyt[$p->id_pobyt]->cleni[$p->id_osoba]) )
        $pobyt[$p->id_pobyt]->cleni[$p->id_osoba]= (object)array();
      $pobyt[$p->id_pobyt]->cleni[$p->id_osoba]->id_tvori= $p->id_tvori;
      $pobyt[$p->id_pobyt]->cleni[$p->id_osoba]->id_rodina= $p->id_rodina;
      $pobyt[$p->id_pobyt]->cleni[$p->id_osoba]->role= $p->role;
      $pobyt[$p->id_pobyt]->cleni[$p->id_osoba]->narozeni= $p->narozeni;
    }
    # atributy osob
    $qo= mysql_qry("SELECT * FROM osoba AS o WHERE deleted='' AND id_osoba IN (0$osoby)");
    while ( $qo && ($o= mysql_fetch_object($qo)) ) {
      $osoba[$o->id_osoba]= $o;
    }
    # atributy rodin
    $qr= mysql_qry("SELECT * FROM rodina AS r WHERE deleted='' AND id_rodina IN (0$rodiny)");
    while ( $qr && ($r= mysql_fetch_object($qr)) ) {
      $r->datsvatba= sql_date1($r->datsvatba);                  // svatba d.m.r
      $rodina[$r->id_rodina]= $r;
    }
//                                                         display("rodiny:$rodiny");
//                                                         debug($rodina,$rodiny);
    # seznam rodin osob
    $qor= mysql_qry("
      SELECT id_osoba,
        IFNULL(GROUP_CONCAT(CONCAT(role,':',id_rodina) SEPARATOR ','),'') AS _rody
      FROM osoba
      JOIN tvori USING(id_osoba)
      WHERE deleted='' AND id_osoba IN (0$osoby)
      GROUP BY id_osoba
    ");
    while ( $qor && ($or= mysql_fetch_object($qor)) ) {
      if ( !isset($osoba[$or->id_osoba]) ) $osoba[$or->id_osoba]= (object)array();
      $osoba[$or->id_osoba]->_rody= $or->_rody;
    }
    # seznamy položek
    $fpob1= flds("key_pobyt=id_pobyt,key_akce=id_akce,key_osoba,key_spolu,key_rodina=i0_rodina,"
           . "c_suma,platba,xfunkce=funkce,funkce,skupina,dluh");
    $fakce= flds("dnu,datum_od");
    $frod=  flds("fotka,r_spz=spz,r_svatba=svatba,r_datsvatba=datsvatba,r_ulice=ulice,r_psc=psc,"
          . "r_obec=obec,r_stat=stat,r_telefony=telefony,r_emaily=emaily,r_note=note");
    $fpob2= flds("p_poznamka=poznamka,pokoj,budova,prednasi,luzka,pristylky,kocarek,pocetdnu"
          . ",strava_cel,strava_pol,c_nocleh=platba1,c_strava=platba2,c_program=platba3,c_sleva=platba4"
          . ",datplatby,cstrava_cel,cstrava_pol,svp,zpusobplat,naklad_d,poplatek_d,platba_d"
          . ",zpusobplat_d,datplatby_d,ubytovani,cd,avizo,sleva,vzorec,duvod_typ,duvod_text");
    //      id_osoba,jmeno,_vek,id_tvori,id_rodina,role,_rody,narozeni
    $fos=   flds("umrti,prijmeni,rodne,sex,adresa,ulice,psc,obec,stat,kontakt,telefon,nomail,email"
          . ",iniciace,uvitano,clen,obcanka,rc_xxxx,cirkev,vzdelani,titul,zamest,zajmy,jazyk"
          . ",aktivita,note,_kmen");
    $fspo=  flds("id_spolu,_barva,s_role,dite_kat,poznamka,pecovane,pfunkce,pece_jm,pece_id");

    # 1. průchod - kompletace údajů mezi pobyty
    $skup= array();
    foreach ($pobyt as $idp=>$p) {
      if ( !count($p->cleni) ) continue;
      # seřazení členů podle přítomnosti, role, věku
      uasort($p->cleni,function($a,$b) {
        $wa= $a->id_spolu==0 ? 4 : ( $a->role=='a' ? 1 : ( $a->role=='b' ? 2 : 3));
        $wb= $b->id_spolu==0 ? 4 : ( $b->role=='a' ? 1 : ( $b->role=='b' ? 2 : 3));
        return $wa == $wb ? ($a->narozeni==$b->narozeni ? 0 : ($a->narozeni > $b->narozeni ? 1 : -1))
                          : ($wa==$wb ? 0 : ($wa > $wb ? 1 : -1));
      });
      # skupinky
      if ( $p->skupina ) {
        $skup[$p->skupina][]= $idp;
      }
      # osobní pečování
      foreach ($p->cleni as $ido=>$s) {
        if ( $s->id_spolu && ($idop= $s->pecovane) ) {
          # pecujici
          $o2= $osoba[$idop];
          $s->pece_id= $o2->id_osoba;
          $s->pece_jm= $o2 ? $o2->prijmeni.' '.$o2->jmeno : '???';
          $s->s_role= 5;
          $s->_barva= 5;                        // barva: 5=osobně pečující, pfunkce=95
          # pečované
          $o1= $osoba[$ido];
          $s2= $pobyt[$spolu[$idop]]->cleni[$idop];
          if ( $s2 ) {
            $s2->pece_id= $o1->id_osoba;
            $s2->pece_jm= $o1 ? $o1->prijmeni.' '.$o1->jmeno : '???';
            $s2->s_role= 3;
            $s2->_barva= 3;                       // barva: 3=osobně pečované, pfunkce=92
          }
        }
      }
    }
    # 2. průchod - kompletace pobytu pro browse_load/ask
    $zz= array();
    foreach ($pobyt as $idp=>$p) {
      if ( !count($p->cleni) ) continue;
      $idr= $p->i0_rodina ?: 0;
      $z= (object)array();
      $_ido1= $_ido2= 0;
      # agregace informací z členů pobytu
      $nazev= array();
      $_jmena= "";
      $clenu= 0;
      $cleni= ""; $del= "";
      foreach ($p->cleni as $ido=>$s) {
        $o= $osoba[$ido];
        if ( $s->id_spolu ) {
          # spočítání účastníků kvůli platbě
          $clenu++;
          # první 2 členi na pobytu
          if ( !$_ido1 )
            $_ido1= $ido;
          elseif ( !$_ido2 )
            $_ido2= $ido;
          # výpočet jmen pobytu
          $_jmena.= "$o->jmeno ";
          if ( !$idr ) {
            # výpočet názvu pobyt
            $prijmeni= $o->prijmeni;
            if ( !in_array(trim($prijmeni),$nazev) ) $nazev[]= trim($prijmeni);
          }
          # barva
          if ( !$s->_barva )
            $s->_barva= $s->id_tvori ? 1 : 2;               // barva: 1=člen rodiny, 2=nečlen
        }
        # sestavení informace pro browse_fill
        $vek= $o->narozeni!='0000-00-00' ? roku_k($o->narozeni,$akce->datum_od) : '?'; // výpočet věku
        $cleni.= "$del$ido~{$o->jmeno}~$vek~{$s->id_tvori}~{$s->id_rodina}~{$s->role}";
        $del= "~";
        # rodiny a kmenová rodina
        $rody= explode(',',$o->_rody);
        $r= "-:0"; $kmen= '';
        foreach($rody as $rod) {
          list($role,$ir)= explode(':',$rod);
          $naz= $rodina[$ir]->nazev;
          $kmen= $kmen ? ($role=='a' || $role=='b' ? $naz : $kmen) : $naz;
//                                                 display("$o->jmeno/$role: $kmen ($naz,$ir)");
          $r.= ",$naz:$ir";
        }
        $cleni.= "~$r";                                           // rody
        $o->_kmen= $kmen;
        $cleni.= "~" . sql_date1($o->narozeni);                   // narozeniny d.m.r
        # informace z osoba
        foreach($fos as $f=>$filler) {
          $cleni.= "~{$o->$f}";
        }
        # informace ze spolu
        foreach($fspo as $f=>$filler) {
          $cleni.= "~{$s->$f}";
        }
      }
//                                                   debug($p->cleni,"členi");
//                                                   display($cleni);
      $_nazev= $idr ? $rodina[$idr]->nazev : implode(' ',$nazev);
      # zjištění dluhu
      $platba1234= $p->platba1 + $p->platba2 + $p->platba3 + $p->platba4;
      $p->c_suma= $platba1234 + $p->poplatek_d;
      $p->dluh= $akce->soubeh==1 && $akce->ma_cenik
        ? ( $p->c_suma = 0 ? 2 : ( $p->c_suma > $p->platba+$p->platba_d ? 1 : 0 ) )
        : ( $akce->ma_cenik
          ? ( $platba1234 = 0 ? 2 : ( $platba1234 > $p->platba ? 1 : 0) )
          : ( $akce->ma_cenu ? ( $clenu * $akce->cena > $p->platba ? 1 : 0) : 0 )
          );
      # pobyt I
      foreach($fpob1 as $fz=>$fp) { $z->$fz= $p->$fp; }
      # akce
      foreach($fakce as $fz=>$fp) { $z->$fz= $akce->$fp; }
      $z->_nazev= $_nazev;
      $z->_jmena= $_jmena;
      # rodina
      foreach($frod as $fz=>$fr) { $z->$fz= $rodina[$idr]->$fr; }
      # členové
      $z->r_cleni= $cleni;
      # pobyt II
      foreach($fpob2 as $fz=>$fp) { $z->$fz= $p->$fp; }
      $z->key_spolu= 0;
      $z->ido1= $_ido1;
      $z->ido2= $_ido2;
      # ok
      $zz[$idp]= $z;
    }
    # 3. průchod - kompletace údajů mezi pobyty
    foreach ($pobyt as $idp=>$p) {
      # doplnění skupinek
      $s= $del= '';
      if ( ($sk= $p->skupina) && $skup[$sk]) {
        foreach($skup[$sk] as $ip) {
          $s.= "$del$ip~{$zz[$ip]->_nazev}";
          $del= '~';
        }
      }
      if ( !isset($zz[$idp]) ) $zz[$idp]= (object)array();
      $zz[$idp]->skup= $s;
    }
    # případný výběr - zjednodušeno na show=[*,vzor]
    if ( $x->show ) foreach ( $x->show as $fld => $show) {
      $i= 0; $typ= $show->$i;
      $i= 1; $vzor= $show->$i;
      $beg= '^';
      switch ($typ) {
      case '%':
        $beg= '';
      case '*':
        $end= substr($vzor,-1)=='$' ?'$' : '.*';
        $not= substr($vzor,0,1)=='-';
        if ( $not ) $vzor= substr($vzor,1);
        $vzor= strtr($vzor,array('?'=>'.','*'=>'.*','$'=>''));
        foreach ($zz as $i=>$z) {
          $v= trim($z->$fld);
          $m= preg_match("/$beg$vzor$end/ui",$v);
//                                           display("/^$vzor$end/ui ? '$v' = $m");
          $off= $not && $m || !$not && !$m;
          if ( $off ) unset($zz[$i]);
        }
        break;
      case '=':
      case '#':
        foreach ($zz as $i=>$z) {
          $v= $z->$fld;
          $ok= $z->$fld == $vzor;
//                                           display("'$vzor'='$v' = $ok");
          if ( !$ok ) unset($zz[$i]);
        }
        break;
      default:
        display("show->{$fld}[0]='$typ' - N.Y.I");
      }
    }
    # případné řazení
    if ( $x->order && count($zz)>0 ) {
      $test_clmn= substr($x->order,2);
      $test_asc= substr($x->order,0,1)=='a' ? 1 : -1;
      // výběr řazení: numerické | alfanumerické
      $numeric= in_array($test_clmn,array('skupina'));
      if ( $numeric ) {
        usort($zz,function($a,$b) {
          global $test_clmn,$test_asc;
          $c= $a->$test_clmn == $b->$test_clmn ? 0 : ($a->$test_clmn > $b->$test_clmn ? 1 : -1);
          return $test_asc * $c;
        });
      }
      else {
        // alfanumerické je řazení podle operačního systému
        $asi_windows= preg_match('/^\w+\.ezer|192.168/',$_SERVER["SERVER_NAME"]);
        if ( $asi_windows ) {
          // asi Windows
          setlocale(LC_ALL, "cs_CZ.utf8","Czech");
          usort($zz,function($a,$b) {
            global $test_clmn,$test_asc;
            $ax= utf2win($a->$test_clmn,1); $bx= utf2win($b->$test_clmn,1);
            $c= $test_asc * strcoll($ax,$bx);
            return $c;
          });
        }
        else {
          // asi Linux
          setlocale(LC_ALL, "cs_CZ.utf8","Czech");
          usort($zz,function($a,$b) {
            global $test_clmn,$test_asc;
            $c= $test_asc * strcoll($a->$test_clmn,$b->$test_clmn);
            return $c;
          });
        }
      }
//                                                 debug($zz);
    }
    # předání pro browse
    $y->values= $zz;
    $y->from= 0;
    $y->cursor= 0;
    $y->rows= count($zz);
    $y->count= count($zz);
    $y->ok= 1;
    array_unshift($y->values,null);
  }
//                                                 debug($pobyt,'pobyt');
//                                                 debug($rodina,'rodina');
//                                                 debug($osoba,'osoba');
//                                                 debug($y);
  return $y;
}
?>
