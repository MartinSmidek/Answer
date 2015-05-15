<?php # (c) 2009-2010 Martin Smidek <martin@smidek.eu>
/** ========================================================================================= SYSTÉM **/
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
/** ====================================================================================== ELIMINACE **/
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
    case 1:   // nová rodina
      if ( $x->role!='a' && $x->role!='b' && $x->_clenu=1 ) goto end; // je jako dítě
      // je a|b v nové
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
//   $ret->s_ido= $id_orig;
//   $ret->continue= "originál je jako jediný člen v nové 'rodině'
//     <br><b>$s_nazev</b>/$ret->s_idr s adresou $s_adresa
//     <br>ale je také v původní rodině
//     <br><b>$r_nazev</b>/$ret->r_idr s adresou $r_adresa,
//     <br>mám tu první 'rodinu' ($s_nazev) zrušit ?";
end:
  return $ret;
}
/** =========================================================================================> AKCE2 **/
# --------------------------------------------------------------------------------------- akce2_mapa
# získání seznamu souřadnic bydlišť účastníků akce
function akce2_mapa($akce) {  trace();
  global $ezer_root;
  $ret= (object)array('mark'=>'','n'=>0);
  // dotaz
  $marks= $err= '';
  $err_psc= $psc= $obec= array();
  $qo=  "
    SELECT prijmeni,adresa,psc,obec,
      (SELECT MIN(CONCAT(role,psc,'x',obec))
       FROM tvori AS ot JOIN rodina AS r USING (id_rodina)
       WHERE ot.id_osoba=o.id_osoba
      ) AS r_psc
    FROM pobyt
    JOIN spolu USING (id_pobyt)
    JOIN osoba AS o USING (id_osoba)
    WHERE id_akce='$akce'
    GROUP BY id_osoba
    ";
  // najdeme použitá PSČ
  $ro= mysql_qry($qo);
  while ( $ro && ($o= mysql_fetch_object($ro)) ) {
    $p= $o->adresa ? $o->psc : substr($o->r_psc,1,5);
    $m= $o->adresa ? $o->obec : substr($o->r_psc,7);
    $psc[$p].= "$o->prijmeni ";
    $obec[$p]= $obec[$p] ?: $m;
//                                         break;
  }
//                                         debug($psc);
  // k PSČ zjistíme LAN,LNG
  $n= 0; $del= '';
  foreach ($psc as $p=>$tit) {
    $qs= "SELECT psc,lat,lng FROM uir_adr.psc_axy WHERE psc='$p'";
    $rs= mysql_qry($qs);
    if ( $rs && ($s= mysql_fetch_object($rs)) ) {
      $n++;
      $o= $obec[$p];
      $title= str_replace(',','',"$o:$tit");
//       $marks.= "$del{$s->lat},{$s->lng},$title"; $del= ';';
      $marks.= "{$del}$n,{$s->lat},{$s->lng},$title"; $del= ';';
    }
    else {
      $err_psc[$p].= " $tit";
    }
//     if ( $n==2 ) break;
  }
  // zjištění chyb
  if ( ($ne= count($err_psc)) ) {
    $err= "$ne PSČ se nepovedlo lokalizovat. Týká se to: ".implode(' a ',$err_psc);
//                                         debug($err_psc,"CHYBY");
  }
  $ret= (object)array('mark'=>$marks,'n'=>$n,'err'=>$err);
//                                         debug(explode(';',$ret->mark),"mapa_akce");
  return $ret;
}
# --------------------------------------------------------------------------------------- akce2_info
# rozšířené informace o akci
# text=1 v textové verzi, text=0 jako objekt s počty
function akce2_info($id_akce,$text=1) {  trace();
  $html= '';
  $info= (object)array('muzi'=>0,'zeny'=>0,'deti'=>0,'peco'=>0,'rodi'=>0,'skup'=>0);
  $zpusoby= map_cis('ms_akce_platba','zkratka'); // způsob => částka
  $platby= array();
  $celkem= 0;
  $aviz= 0;
  if ( $id_akce ) {
    $ucasti= $rodiny= $dosp= $muzi= $zeny= $deti= $pecounu= $err= $err2= 0;
    $odhlaseni= $neprijeli= $nahradnici= $nahradnici_osoby= 0;
    $akce= $chybi_nar= $chybi_sex= '';
    $qry= "SELECT nazev, datum_od, datum_do, now() as _ted,i0_rodina,funkce,
             COUNT(id_spolu) AS _clenu,
             SUM(IF(ROUND(DATEDIFF(a.datum_od,o.narozeni)/365.2425,1)<18,1,0)) AS _deti,
             SUM(IF(ROUND(DATEDIFF(a.datum_od,o.narozeni)/365.2425,1)>=18 AND sex=1,1,0)) AS _muzu,
             SUM(IF(ROUND(DATEDIFF(a.datum_od,o.narozeni)/365.2425,1)>=18 AND sex=2,1,0)) AS _zen,
             SUM(IF(o.narozeni='0000-00-00',1,0)) AS _err,
             GROUP_CONCAT(IF(o.narozeni='0000-00-00',CONCAT(', ',jmeno,' ',prijmeni),'') SEPARATOR '') AS _kdo,
             SUM(IF(o.sex NOT IN (1,2),1,0)) AS _err2,
             GROUP_CONCAT(IF(o.sex NOT IN (1,2),CONCAT(', ',jmeno,' ',prijmeni),'') SEPARATOR '') AS _kdo2,
             avizo,platba,datplatby,zpusobplat
           FROM akce AS a
           JOIN pobyt AS p ON a.id_duakce=p.id_akce
           JOIN spolu AS s ON p.id_pobyt=s.id_pobyt
           JOIN osoba AS o ON s.id_osoba=o.id_osoba
           -- LEFT JOIN tvori AS t ON t.id_osoba=o.id_osoba
           WHERE id_duakce='$id_akce'
           GROUP BY p.id_pobyt";
    $res= mysql_qry($qry);
    while ( $res && $p= mysql_fetch_object($res) ) {
      $fce= $p->funkce;
      // záznam plateb
      if ( $p->platba ) {
        $celkem+= $p->platba;
        $platby[$p->zpusobplat]+= $p->platba;
      }
      if ( $p->avizo ) {
        $aviz++;
      }
      // diskuse funkce=odhlášen/14 a funkce=nepřijel/10 a funkce=náhradník/9
      if ( in_array($fce,array(14,10,9) ) ) {
        $odhlaseni+= $fce==14 ? 1 : 0;
        $neprijeli+= $fce==10 ? 1 : 0;
        $nahradnici+= $fce==9 ? 1 : 0;
        $nahradnici_osoby+= $fce==9 ? $p->_clenu : 0;
      }
      else {
        // údaje účastníků jednoho pobytu
        $ucasti++;
        $muzi+= $p->_muzu;
        $zeny+= $p->_zen;
        $deti+= $p->_deti;
        $err+= $p->_err;
        $err2+= $p->_err2;
        $rodiny+= i0_rodina && $p->_clenu>1 ? 1 : 0;
        $chybi_nar.= $p->_kdo;
        $chybi_sex.= $p->_kdo2;
        if ( $p->funkce==99 )
          $pecounu+= $p->_clenu;
      }
      // údaje akce
      $akce= $p->nazev;
      $cas1= $p->_ted>$p->datum_od ? "byla" : "bude";
      $cas2= $p->_ted>$p->datum_od ? "Akce se zúčastnilo" : "Na akci je přihlášeno";
      $od= sql_date1($p->datum_od);
      $do= sql_date1($p->datum_do);
      $dne= $p->datum_od==$p->datum_do ? "dne $od" : "ve dnech $od do $do";
    }
    if ( $chybi_nar ) $chybi_nar= substr($chybi_nar,2);
    if ( $chybi_sex ) $chybi_sex= substr($chybi_sex,2);
    $dosp+= $muzi + $zeny;
    $skupin= $ucasti - ( $pecounu ? 1 : 0 );
    if ( $text ) {
      // čeština
      $_skupin=    je_1_2_5($skupin,"skupina,skupiny,skupin");
      $_pecounu=   je_1_2_5($pecounu,"pečoun,pečouni,pečounů");
      $_dospelych= je_1_2_5($dosp,"dospělý,dospělí,dospělých");
      $_muzu=      je_1_2_5($muzi,"muž,muži,mužů");
      $_zen=       je_1_2_5($zeny,"žena,ženy,žen");
      $_deti=      je_1_2_5($deti,"dítě,děti,dětí");
      $_osob=      je_1_2_5($dosp+$deti,"osoba,osoby,osob");
      $_err=       je_1_2_5($err,"osoby,osob,osob");
      $_err2=      je_1_2_5($err2,"osoby,osob,osob");
      $_rodiny=    je_1_2_5($rodiny,"rodina,rodiny,rodin");
      $_pobyt_o=   je_1_2_5($odhlaseni,"pobyt,pobyty,pobytů");
      $_pobyt_x=   je_1_2_5($neprijeli,"pobyt,pobyty,pobytů");
      $_pobyt_n=   je_1_2_5($nahradnici,"přihláška,přihlášky,přihlášek");
      $_pobyt_no=  je_1_2_5($nahradnici_osoby,"osoba,osoby,osob");
      $_aviz=      je_1_2_5($aviz,"avízo,avíza,avíz");
      // html
      $html= $dosp+$deti>0
       ? $html.= "<h3 style='margin:0px 0px 3px 0px;'>$akce</h3>akce $cas1 $dne<br><hr>$cas2"
       . ($skupin ? " $_skupin účastníků"
           .($rodiny ? ($rodiny==$ucasti ? " (všechny jako rodiny)" : " (z toho $_rodiny)") :''):'')
       . ($pecounu ? " ".($skupin?" a ":'')."$_pecounu" : '')
       . ",<br><br>tj. $_dospelych ($_muzu, $_zen) a $_deti,"
       . " <b>celkem $_osob</b>"
       : "Akce byla vložena do databáze ale nemá zatím žádné účastníky";
      if ( $odhlaseni + $neprijeli + $nahradnici > 0 ) {
        $html.= "<br><hr>";
        $msg= array();
        if ( $odhlaseni ) $msg[]= "odhlášeno: $_pobyt_o (bez storna)";
        if ( $neprijeli ) $msg[]= "zrušeno: $_pobyt_x (nepřijeli, aplikovat storno)";
        if ( $nahradnici ) $msg[]= "náhradníci: $_pobyt_n, celkem $_pobyt_no";
        $html.= implode('<br>',$msg);
      }
      if ( $err + $err2 > 0 ) {
        $html.= "<br><hr>POZOR: ";
        $html.= $err>0  ? "<br>u $_err chybí datum narození: <i>$chybi_nar</i>" : '';
        $html.= $err2>0 ? "<br>u $_err2 chybí údaj muž/žena: <i>$chybi_sex</i>" : '';
        $html.= "<br>(kvůli chybějícím údajům mohou být počty divné)";
      }
      $html.= $deti ? "<hr>Poznámka: jako děti se počítají osoby, které v době zahájení akce nemají 18 let" : '';
    }
    else {
      $info->muzi= $muzi;
      $info->zeny= $zeny;
      $info->deti= $deti;
      $info->peco= $pecounu;
      $info->rodi= $rodiny;
      $info->skup= $skupin;
    }
    // zobrazení přehledu plateb
    if ( $celkem ) {
      $html.= "<h3 style='margin-bottom:3px;'>Přehled plateb za akci</h3><table>";
      foreach ($zpusoby as $i=>$zpusob) {
        if ( $platby[$i] )
          $html.= "<tr><td>$zpusob</td><td align='right'>{$platby[$i]}</td></tr>";
      }
      $st= "style='border-top:1px solid black'";
      $av= $aviz ? "<td $st> a $_aviz platby</td>" : '';
      $html.= "<tr><td $st>CELKEM</td><td align='right' $st><b>$celkem</b></td>$av</tr>";
      $html.= "</table>";
    }
  }
  else {
    $html= "Tato akce ještě nebyla vložena do databáze
            <br><br>Vložení se provádí dvojklikem
            na řádek s akcí";
  }
  return $text ? $html : $info;
}
# ----------------------------------------------------------------------------------------- je_1_2_5
# výběr správného tvaru slova podle množství a tabulky tvarů pro 1,2-4,více jak 5
# např. je_1_2_5($dosp,"dospělý,dospělí,dospělých")
function je_1_2_5($kolik,$tvary) {
  list($tvar1,$tvar2,$tvar5)= explode(',',$tvary);
  return $kolik>4 ? "$kolik $tvar5" : (
         $kolik>1 ? "$kolik $tvar2" : (
         $kolik>0 ? "1 $tvar1"      : "0 $tvar5"));
}
// # =================================================================================================> FILES
// # funkce pro svázání souborů s tabulkou FILE
// #   add=přidání, list=vrácení seznamu, del=smazání, del_all=mazání všech
// # ---------------------- file_folder
// # pomocná: vrátí složku podle aplikace a prefix podle tabulky
// function file_folder($table,$id,&$abspath,&$relpath,&$prefix) { trace();
//   global $ezer_path_root, $ezer_root;
//   $t= substr($table,0,1);
//   $f= substr($ezer_root,0,2);
//   $r= substr($ezer_root,0,2)=='fa' ? 'f' : (
//       substr($ezer_root,0,2)=='ys' ? 's' : (
//       substr($ezer_root,0,2)=='cr' ? 'c' : 'x'));
//   $test= substr($ezer_root,-5)=='_test';
//   $relpath= $test ? "files/_test/$f/" : "files/$f/";
//   $abspath= "$ezer_path_root/$relpath";
//   $prefix=  "$r$t{$id}_";
// }
// # ----------------------------------------------------------------------------------------- file_add
// # přidá soubor do tabulky a do složky (akce|pobyt|rodina|osoba), vrátí nový jednoznačný název
// function file_add($table,$id,$f,$note='') { trace();
//   $msg= '';
//   query("INSERT INTO file (tab,id_tab,filename,filesize,note) VALUES ('$table',$id,'{$f->name}','{$f->size}','$note')");
//   return $msg;
// }
// # ---------------------------------------------------------------------------------------- file_list
// # vrátí seznam souborů patřící k danému záznamu a složku dané aplikace
// function file_list($table,$id) { trace();
//   $ret= (object)array('list'=>'','folder'=>'','prefix'=>''); $del= '';
//   file_folder($table,$id,$ret->abspath,$ret->relpath,$ret->prefix);
//   $qf= "SELECT * FROM file WHERE deleted='' AND tab='$table' AND id_tab='$id' ";
//   $rf= mysql_qry($qf);
//   while ( $rf && ($f= mysql_fetch_object($rf)) ) {
//     $ret->list.= "$del{$f->filename}:{$f->filesize}";
//     $del= ',';
//   }
//   return $ret;
// }
// # ----------------------------------------------------------------------------------------- file_del
// # poznačí soubor jako smazaný
// function file_del($table,$id,$name) { trace();
//   $ok= query("UPDATE file SET deleted='D' WHERE tab='$table' AND id_tab='$id' AND filename='$name'");
//   return $ok ? '' : mysql_error();
// }
// # ------------------------------------------------------------------------------------- file_del_all
// # odstraní všechny soubory patřící k danému záznamu
// function file_del_all($table,$id) { trace();
// //   query("UPDATE dopis SET prilohy='' WHERE id_dopis=$id_dopis");
//   return 1;
// }
/** ===================================================================================== ÚČASTNÍCI2 */
/** =======================================================================================>> VÝPISY */
# ------------------------------------------------------------------------------------- tisk_sestava
# generování sestav
#   $typ = j | p | vp | vp2 | vs | vn | vv | vj | sk | sd | d | fs | ...
function tisk_sestava($akce,$par,$title,$vypis,$export=false) { trace();
  return 0 ? 0
     : ( $par->typ=='p'  ? tisk_sestava_pary($akce,$par,$title,$vypis,$export)
//      : ( $par->typ=='P'  ? akce_sestava_pobyt($akce,$par,$title,$vypis,$export)
     : ( $par->typ=='j'  ? tisk_sestava_lidi($akce,$par,$title,$vypis,$export)
//      : ( $par->typ=='vp' ? akce_vyuctov_pary($akce,$par,$title,$vypis,$export)
//      : ( $par->typ=='vp2'? akce_vyuctov_pary2($akce,$par,$title,$vypis,$export)
     : ( $par->typ=='vs' ? akce2_strava_pary($akce,$par,$title,$vypis,$export)  // bez náhradníků
//      : ( $par->typ=='vj' ? akce_stravenky($akce,$par,$title,$vypis,$export)
//      : ( $par->typ=='vjp'? akce_stravenky($akce,$par,$title,$vypis,$export)
//      : ( $par->typ=='vn' ? akce_sestava_noci($akce,$par,$title,$vypis,$export)
     : ( $par->typ=='vv' ? tisk_text_vyroci($akce,$par,$title,$vypis,$export)
     : ( $par->typ=='vi' ? akce2_text_prehled($akce,$par,$title,$vypis,$export)
//      : ( $par->typ=='ve' ? akce_text_eko($akce,$par,$title,$vypis,$export)
//      : ( $par->typ=='sk' ? akce_skupinky($akce,$par,$title,$vypis,$export)
//      : ( $par->typ=='sd' ? akce_skup_deti($akce,$par,$title,$vypis,$export)
     : ( $par->typ=='d'  ? akce2_sestava_pecouni($akce,$par,$title,$vypis,$export)
//      : ( $par->typ=='fs' ? akce_fotoseznam($akce,$par,$title,$vypis,$export)
//      : ( $par->typ=='fx' ? akce_sestava_spec($akce,$par,$title,$vypis,$export)
//      : ( $par->typ=='fp' ? akce_sestava_pred($akce,$par,$title,$vypis,$export)
//      : ( $par->typ=='12' ? akce_jednou_dvakrat($akce,$par,$title,$vypis,$export)
//      : ( $par->typ=='cz' ? akce_cerstve_zmeny($akce,$par,$title,$vypis,$export)
     : (object)array('html'=>"<i>Tato sestava zatím není převedena do nové verze systému,
          <a href='mailto:martin@smidek.eu'>upozorněte mě</a>, že ji už potřebujete</i>")
//        )))))))))))))))
     ))))));
}
# ----------------------------------------------------------------------------------------- tisk_qry
# frekventované SQL dotazy s parametry
# pobyt_dospeli_ucastnici => _jm=jména dospělých účastníků (GROUP BY id_pobyt)
# ucastnik                => každý účastník zvlášť
# pobyt_rodiny            => _jmena, _adresa, _telefony, _emaily
function tisk_qry($typ,$flds='',$where='',$having='',$order='') { trace();
  $where=  $where  ? " WHERE $where " : '';
  $having= $having ? " HAVING $having " : '';
  $order=  $order  ? " ORDER BY $order " : '';
  switch ($typ) {
  case 'ucastnik':
    $qry= "
      SELECT $flds
      FROM akce AS a
        JOIN pobyt AS p ON a.id_duakce=p.id_akce
        JOIN spolu AS s USING(id_pobyt)
        JOIN osoba AS o ON s.id_osoba=o.id_osoba AND o.deleted=''
        LEFT JOIN tvori AS t ON t.id_osoba=o.id_osoba AND t.id_rodina=p.i0_rodina
      $where
      GROUP BY o.id_osoba $having $order
    ";
    break;
  case 'pobyt_rodiny':
    $qry= "
      SELECT id_pobyt,i0_rodina,pr.obec
        ,COUNT(pso.id_osoba) AS _ucastniku
        ,CONCAT(IF(pr.telefony!='',CONCAT(pr.telefony,','),''),
           GROUP_CONCAT(IF(pso.kontakt,pso.telefon,''))) AS _telefony
        ,CONCAT(IF(pr.emaily!='',CONCAT(pr.emaily,','),''),
           GROUP_CONCAT(IF(pso.kontakt,pso.email,''))) AS _emaily
        ,IF(i0_rodina,CONCAT(pr.nazev,' ',GROUP_CONCAT(REPLACE(TRIM(pso.jmeno),' ','-') ORDER BY role SEPARATOR ' a '))
          ,GROUP_CONCAT(DISTINCT CONCAT(pso.prijmeni,' ',REPLACE(TRIM(pso.jmeno),' ','-')) ORDER BY role SEPARATOR ' a ')) as _jmena
        ,GROUP_CONCAT(CONCAT(ps.id_spolu,'|',REPLACE(TRIM(jmeno),' ','-'),'|',prijmeni,'|',adresa,'|',pso.obec,'|'
          ,IFNULL(( SELECT CONCAT(id_tvori,'/',role)
             FROM tvori
             JOIN rodina USING (id_rodina)
             WHERE id_osoba=pso.id_osoba AND role IN ('a','b')
             GROUP BY id_osoba ),'-')
        )) AS _o
      FROM pobyt
        LEFT JOIN rodina AS pr ON pr.id_rodina=i0_rodina
        JOIN spolu AS ps USING (id_pobyt)
        JOIN osoba AS pso USING (id_osoba)
        LEFT JOIN (
          SELECT id_osoba,role,id_rodina
            FROM tvori
            JOIN rodina USING (id_rodina)
            GROUP BY id_osoba
        ) AS rto ON rto.id_osoba=pso.id_osoba AND rto.role IN ('a','b')
      $where
      -- WHERE id_pobyt IN (15209,15217,15213,15192,15199)
      GROUP BY id_pobyt $having $order
      ";
    break;
  case 'pobyt_dospeli_ucastnici':
    $flds=  $flds  ? " $flds," : '';
    $qry= "
      SELECT $flds
        IF(p.i0_rodina,CONCAT(pr.nazev,' ',GROUP_CONCAT(po.jmeno ORDER BY role SEPARATOR ' a '))
          ,GROUP_CONCAT(DISTINCT CONCAT(pso.prijmeni,' ',pso.jmeno) ORDER BY role SEPARATOR ' a ')) as _jm
      FROM pobyt AS p
        JOIN akce AS a ON p.id_akce=a.id_duakce
        LEFT JOIN rodina AS pr ON pr.id_rodina=p.i0_rodina
        JOIN spolu AS ps ON ps.id_pobyt=p.id_pobyt
        LEFT JOIN tvori AS pt ON pt.id_rodina=p.i0_rodina AND role IN ('a','b') AND ps.id_osoba=pt.id_osoba
        LEFT JOIN osoba AS po ON po.id_osoba=pt.id_osoba
        JOIN osoba AS pso ON pso.id_osoba=ps.id_osoba AND DATEDIFF(a.datum_od,pso.narozeni)/365.2425>18
      $where
      GROUP BY p.id_pobyt $having $order
    ";
    break;
  }
  $res= mysql_qry($qry);
  return $res;
 }
# -------------------------------------------------------------------------------- tisk_sestava_lidi
# generování sestavy pro účastníky $akce - jednotlivce
#   $fld = seznam položek s prefixem
#   $cnd = podmínka
#   $par->sel = seznam id_pobyt
function tisk_sestava_lidi($akce,$par,$title,$vypis,$export=false) { trace();
  $result= (object)array();
  $typ= $par->typ;
  $tit= $par->tit;
  $fld= $par->fld;
  $cnd= $par->cnd;
  $hav= $par->hav ? "HAVING {$par->hav}" : '';
  $ord= $par->ord ? $par->ord : "IF(funkce<=2,1,funkce),o.prijmeni,o.jmeno";
  $html= '';
  $href= '';
  $n= 0;
  // dekódování parametrů
  $tits= explode(',',$tit);
  $flds= explode(',',$fld);
  // číselníky
  $pfunkce= map_cis('ms_akce_pfunkce','zkratka');  $pfunkce[0]= '?';
  $dieta= map_cis('ms_akce_dieta','zkratka');  $dieta[0]= '';
  $dite_kat= map_cis('ms_akce_dite_kat','zkratka');  $dite_kat[0]= '?';
  // načtení ceníku pro dite_kat, pokud se chce _poplatek
  if ( strpos($fld,"_poplatek") ) {
    $soubezna= select("id_duakce","akce","id_hlavni=$akce");
    akce_nacti_cenik($soubezna,$cenik,$html);
  }
  // získání dat - podle $kdo
  $clmn= array();
  $expr= array();       // pro výrazy
  // případné zvláštní řazení
  switch ($ord) {
  case '_zprava':
    $ord= "CASE WHEN _vek<6 THEN 1 WHEN _vek<18 THEN 2 WHEN _vek<26 THEN 3 ELSE 9 END,prijmeni";
    break;
  }
  // případné omezení podle selected na seznam pobytů
  if ( $par->sel && $par->selected ) {
                                                display("i.par.sel=$par->sel");
    $cnd.= " AND p.id_pobyt IN ($par->selected)";
  }
  // data akce
  $r_fld= "id_rodina,nazev,ulice,psc,obec,stat,note,emaily,telefony";
  $qry=  "
    SELECT
      p.pouze,p.poznamka,p.platba,
      o.prijmeni,o.jmeno,o.narozeni,o.rc_xxxx,o.note,o.obcanka,o.clen,o.dieta,
      IFNULL(r2.id_rodina,r1.id_rodina) AS id_rodina,
      IFNULL(r2.nazev,r1.nazev) AS r_nazev,
      IF(o.adresa,o.ulice,IFNULL(r2.ulice,r1.ulice)) AS ulice,
      IF(o.adresa,o.psc,IFNULL(r2.psc,r1.psc)) AS psc,
      IF(o.adresa,o.obec,IFNULL(r2.obec,r1.obec)) AS obec,
      IF(o.adresa,o.stat,IFNULL(r2.stat,r1.stat)) AS stat,
      IF(o.kontakt,o.telefon,IFNULL(r2.telefony,r1.telefony)) AS telefony,
      IF(o.kontakt,o.email,IFNULL(r2.emaily,r1.emaily)) AS emaily,
      s.poznamka AS s_note,s.pfunkce,s.dite_kat,
      IFNULL(r2.note,r1.note) AS r_note,
      IFNULL(r2.role,r1.role) AS r_role,
      ROUND(DATEDIFF(a.datum_od,o.narozeni)/365.2425,1) AS _vek,
      (SELECT GROUP_CONCAT(prijmeni,' ',jmeno)
        FROM akce JOIN pobyt ON id_akce=akce.id_duakce
        JOIN spolu ON spolu.id_pobyt=pobyt.id_pobyt
        JOIN osoba ON osoba.id_osoba=spolu.id_osoba
        WHERE spolu.pecovane=o.id_osoba AND id_akce=$akce) AS _chuva,
      (SELECT CONCAT(prijmeni,' ',jmeno) FROM osoba
        WHERE s.pecovane=osoba.id_osoba) AS _chovany
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
      -- akce
      JOIN akce AS a ON a.id_duakce=p.id_akce
      WHERE p.id_akce=$akce AND $cnd
      GROUP BY o.id_osoba $hav
      ORDER BY $ord";
  $res= mysql_qry($qry);
  while ( $res && ($x= mysql_fetch_object($res)) ) {
    $n++;
    $clmn[$n]= array();
    // doplnění počítaných položek
    $x->narozeni_dmy= sql_date1($x->narozeni);
    foreach($flds as $f) {
      switch ($f) {
      case 'dieta':                                                   // osoba: dieta
        $clmn[$n][$f]= $dieta[$x->$f];
        break;
      case 'dite_kat':                                                // osoba: kategorie dítěte
        $clmn[$n][$f]= $dite_kat[$x->$f];
        break;
      case '_1':
        $clmn[$n][$f]= 1;
        break;
      case '_ar_note':                                                // k akci: rodina/osoba
        $clmn[$n][$f]= "{$x->poznamka} / {$x->s_note}";
        break;
      case '_tr_note':                                                // trvalá: rodina/osoba
        $clmn[$n][$f]= "{$x->r_note} / {$x->note}";
        break;
      case '_a_note':                                                 // k akci: osoba
        $clmn[$n][$f]= $x->s_note;
//         $clmn[$n][$f]= $x->s_note ? $x->$f.' / '.$x->s_note : $x->$f;
        break;
      case '_t_note':                                                 // trvalá: osoba
        $clmn[$n][$f]= $x->note;
//         $clmn[$n][$f]= $x->s_note ? $x->$f.' / '.$x->s_note : $x->$f;
        break;
      case '_narozeni6':
        $nar= $x->narozeni;
        $clmn[$n][$f]= "'".substr($nar,2,2).substr($nar,5,2).substr($nar,8,2);
        break;
      case '_ymca':
        $clmn[$n][$f]= $x->clen ? $x->clen : '';
        break;
      case 'pfunkce':
        $pf= $x->$f;
        $clmn[$n][$f]= !$pf ? 'skupinka' : (
            $pf==4 ? 'pomocný p.' : (
            $pf==5 || $pf==95 ? "os.peč. pro: {$x->_chovany}" : (
            $pf==8 ? 'skupina G' : (
            $pf==92 ? "os.peč. je: {$x->_chuva}" : '?'))));
        break;
      case '_poplatek':                                               // poplatek/dítě dle číselníku
        $kat= $dite_kat[$x->dite_kat];             // $cenik[p$kat|d$kat]= {c:cena,txt:popis}
        $clmn[$n][$f]= $kat=="?" ? "?" : $cenik["p$kat"]->c - $cenik["d$kat"]->c;
        break;
        // ---------------------------------------------------------- pro YMCA v ČR
      case '_jmenoY':
        $clmn[$n][$f]= "$x->jmeno $x->prijmeni";
        break;
      case '_adresaY':
        $clmn[$n][$f]= "$x->ulice, $x->psc, $x->obec";
        break;
      case '_narozeniY':
        $clmn[$n][$f]= $x->narozeni;
        break;
      default: $clmn[$n][$f]= $x->$f;
      }
    }
  }
//                                         debug($clmn,"sestava pro $akce,$typ,$fld,$cnd");
  return sta_table($tits,$flds,$clmn,$export);
}
# ---------------------------------------------------------------------------------- akce_pdf_prijem
# generování štítků se stručnými informace k nalepení na obálku účastníka do PDF
function tisk_pdf_prijem($akce,$par,$report_json) {  trace();
  global $json, $ezer_path_docs;
  $result= (object)array('_error'=>0);
  $html= '';
  // získání dat
  $tab= tisk_sestava_pary($akce,$par,$title,$vypis,true);
//                                         debug($tab,"tisk_sestava_pary($akce,...)"); //return;
  // projdi vygenerované záznamy
  $n= 0;
  $parss= array();
  foreach ( $tab->clmn as $xa ) {
    // definice pole substitucí
    $x= (object)$xa;
    $parss[$n]= (object)array();
    $parss[$n]->line1= "<b>{$x->prijmeni} {$x->jmena}</b>";
    $parss[$n]->line2= ($x->skupina?"skupinka <b>{$x->skupina}</b> ":'')
                     . ($x->pokoj?"pokoj <b>{$x->pokoj}</b>":'');
    $parss[$n]->line3= $x->luzka || $x->pristylky || $x->kocarek ? (
                       ($x->luzka?"lůžka <b>{$x->luzka}</b> ":'')
                     . ($x->pristylky?"přistýlky <b>{$x->pristylky} </b>":'')
                     . ($x->kocarek?"kočárek <b>{$x->kocarek}</b>":'')
                       ) : "bez ubytování";
    $parss[$n]->line4= $x->strava_cel || $x->strava_pol ? ( "strava: "
                     . ($x->strava_cel?"celá <b>{$x->strava_cel}</b> ":'')
                     . ($x->strava_pol?"poloviční <b>{$x->strava_pol}</b>":'')
                       ) : "bez stravy";
    $n++;
  }
  // předání k tisku
  $fname= 'stitky_'.date("Ymd_Hi");
  $fpath= "$ezer_path_docs/$fname.pdf";
  dop_rep_ids($report_json,$parss,$fpath);
  $result->html= " Výpis byl vygenerován ve formátu <a href='docs/$fname.pdf' target='pdf'>PDF</a>.";
  return $result;
}
# -------------------------------------------------------------------------------- tisk_sestava_pary
# generování sestavy pro účastníky $akce - rodiny
#   $fld = seznam položek s prefixem
#   $cnd = podmínka
function tisk_sestava_pary($akce,$par,$title,$vypis,$export=false) { trace();
  global $EZER;
//   function otoc($s) {
//     mb_internal_encoding("UTF-8");
//     $s= mb_strtolower($s);
//     $x= '';
//     for ($i= mb_strlen($s); $i>=0; $i--) {
//       $xi= mb_substr($s,$i,1);
//       $xi= mb_strtoupper($xi);
//       $x.= $xi;
//     }
//     return $x;
//   }
  $result= (object)array();
  $typ= $par->typ;
  $tit= $par->tit;
  $fld= $par->fld;
  $cnd= $par->cnd ? $par->cnd : 1;
  $hav= $par->hav ? "HAVING {$par->hav}" : '';
  $ord= $par->ord ? $par->ord : "a _nazev";
  $html= '';
  $href= '';
  $n= 0;
  // číselníky
  $c_ubytovani= map_cis('ms_akce_ubytovan','zkratka');  $c_ubytovani[0]= '?';
  $c_prednasi= map_cis('ms_akce_prednasi','hodnota');  $c_ubytovani[0]= '?';
  $c_platba= map_cis('ms_akce_platba','zkratka');  $c_ubytovani[0]= '?';
  // dekódování parametrů
  $tits= explode(',',$tit);
  $flds= explode(',',$fld);
  $cond= $cnd;
  // získání dat - podle $kdo
  $clmn= array();
  $expr= array();       // pro výrazy
  # diskuse souběhu: 0=normální akce, 1=hlavní akce, 2=souběžná akce
  list($hlavni,$soubezna)= select("a.id_hlavni,IFNULL(s.id_duakce,0)",
      "akce AS a LEFT JOIN akce AS s ON s.id_hlavni=a.id_duakce",
      "a.id_duakce=$akce");
  $soubeh= $soubezna ? 1 : ( $hlavni ? 2 : 0);
  $browse_par= (object)array(
    'cmd'=>'browse_load','cond'=>"$cnd AND p.id_akce=$akce",'having'=>$hav,'order'=>$ord,
    'sql'=>"SET @akce:=$akce,@soubeh:=$soubeh,@app:='{$EZER->options->root}';");
  $y= akce_browse_ask($browse_par,true);
  # rozbor výsledku browse/ask
  $i_jmeno=       1;
  $i_adresa=     12;
  $i_key_spolu=   36;
  $i_spolu_note=  40;
  $i_osoba_note=    33;
  $i_osoba_kontakt= 17;
  $i_osoba_telefon= 18;
  $i_osoba_email=   20;
  array_shift($y->values);
  foreach ($y->values as $x) {
//     if ( !in_array($x->key_pobyt,array(15209,15217,15213,15192,15199)) ) continue;
//     if ( !in_array($x->key_pobyt,array(15192)) ) continue;
//     if ( !in_array($x->key_pobyt,array(15202)) ) continue;
                                                        if ( $x->key_pobyt==21339 ) debug($x,"$x->key_pobyt");
    $n++;
    # rozbor osobních údajů: adresa nebo základní kontakt se získá 3 způsoby
    # 1. první osoba má osobní údaje - ty se použijí
    # 2. první osoba má rodinné údaje, které se shodují s i0_rodina - použijí se ty z i0_rodina
    # 3. první osoba má rodinné údaje, které se neshodují s i0_rodina - použijí se tedy její
    $telefony= $emaily= array();
    if ( $x->r_telefony ) $telefony[]= trim($x->r_telefony,",; ");
    if ( $x->r_emaily )   $emaily[]=   trim($x->r_emaily,",; ");
    # rozšířené spojení se získá slepením údajů všech účastníků
    $xs= explode('≈',$x->r_cleni);
//                                                         debug($xs,"xs");
    $pocet= 0;
    $spolu_note= "";
    $osoba_note= "";
    foreach ($xs as $i=>$xi) {
      $o= explode('~',$xi);
                                                        if ( $x->key_pobyt==21339 ) debug($o,"xi/$i");
      if ( $o[$i_key_spolu] ) {
        $pocet++;
        $jmeno= str_replace(' ','-',$o[$i_jmeno]);
        if ( $o[$i_spolu_note] ) $spolu_note.= " + $jmeno:$o[$i_spolu_note]";
        if ( $o[$i_osoba_note] ) $osoba_note.= " + $jmeno:$o[$i_osoba_note]";
        if ( $o[$i_osoba_kontakt] && $o[$i_osoba_telefon] )
          $telefony[]= trim($o[$i_osoba_telefon],",; ");
        if ( $o[$i_osoba_kontakt] && $o[$i_osoba_email] )
          $emaily[]= trim($o[$i_osoba_email],",; ");
      }
    }
    $o= explode('~',$xs[0]);
    // show: adresa, ulice, psc, obec, stat, kontakt, telefon, nomail, email
    $io= $i_adresa;
    $adresa=  $o[$io++]; $ulice= $o[$io++]; $psc= $o[$io++]; $obec= $o[$io++]; $stat= $o[$io++];
    $kontakt= $o[$io++]; $telefon= $o[$io++]; $nomail= $o[$io++]; $email= $o[$io++];
    // přepsání do výstupního pole
    $clmn[$n]= array();
    $r= 0; // 1 ukáže bez (r)
    foreach($flds as $f) {          // _pocet,poznamka,note
      switch ($f) {
      case '^id_pobyt': $c= $x->key_pobyt; break;
      case 'prijmeni':  $c= $x->_nazev; break;
      case 'jmena':     $c= $x->_jmena; break;
      case 'ulice':     $c= $adresa  ? $ulice   : substr($ulice,$r); break;
      case 'psc':       $c= $adresa  ? $psc     : substr($psc,$r);   break;
      case 'obec':      $c= $adresa  ? $obec    : substr($obec,$r);  break;
      case 'telefon':   $c= trim($kontakt ? $telefon : substr($telefon,$r),",; ");  break;
      case 'telefony':  $c= count($telefony) ? implode(', ',$telefony).';' : ''; break;
      case 'email':     $c= trim($kontakt ? $email   : substr($email,$r),",; ");  break;
      case 'emaily':    $c= count($emaily) ? implode(', ',$emaily).';' : ''; break;
      case '_pocet':    $c= $pocet; break;
      case 'poznamka':  $c= $x->p_poznamka . ($spolu_note ?: ''); break;
      case 'note':      $c= $x->r_note . ($osoba_note ?: ''); break;
      default:          $c= $x->$f; break;
      }
      $clmn[$n][$f]= $c;
    }

//     break;
//     continue;
//
//     $x->prijmeni= $x->pouze==1 ? $x->prijmeni_m : ($x->pouze==2 ? $x->prijmeni_z : $x->nazev);
//     $x->jmena=    $x->pouze==1 ? $x->jmeno_m    : ($x->pouze==2 ? $x->jmeno_z : "{$x->jmeno_m} a {$x->jmeno_z}");
//     $x->_pocet= ($x->pouze?" 1":" 2").($x->_deti?"+{$x->_deti}":'');
//     // emaily
//     $a_emaily_m= preg_split("/,\s*|;\s*/",trim($x->email_m ? $x->email_m : $x->emaily," ,;"));
//     $a_emaily_z= preg_split("/,\s*|;\s*/",trim($x->email_z ? $x->email_z : $x->emaily," ,;"));
//     $a_emaily= preg_split("/,\s*|;\s*/",trim($x->emaily," ,;"));
//     $emaily= implode(';',array_diff(array_unique(array_merge($a_emaily,$a_emaily_m,$a_emaily_z)),array('')));
//     $emaily_m= implode(';',$a_emaily_m);
//     $emaily_z= implode(';',$a_emaily_z);
//     $x->emaily= $x->pouze==1 ? $emaily_m  : ($x->pouze==2 ? $emaily_z : $emaily);
//     $x->emaily.= $x->emaily ? ';' : '';
//     // telefony
//     $a_telefony_m= preg_split("/,\s*|;\s*/",trim($x->telefon_m ? $x->telefon_m : $x->telefony," ,;"));
//     $a_telefony_z= preg_split("/,\s*|;\s*/",trim($x->telefon_z ? $x->telefon_z : $x->telefony," ,;"));
//     $a_telefony= preg_split("/,\s*|;\s*/",trim($x->telefony," ,;"));
//     $telefony= implode(';',array_diff(array_unique(array_merge($a_telefony,$a_telefony_m,$a_telefony_z)),array('')));
//     $telefony_m= implode(';',$a_telefony_m);
//     $telefony_z= implode(';',$a_telefony_z);
//     $x->telefony= $x->pouze==1 ? $telefony_m  : ($x->pouze==2 ? $telefony_z : $telefony);
//     // podle číselníku
//     $x->ubytovani= $c_ubytovani[$x->ubytovani];
//     $x->prednasi= $c_prednasi[$x->prednasi];
//     $x->zpusobplat= $c_platba[$x->zpusobplat];
//     // další
//     $n++;
//     $clmn[$n]= array();
//     foreach($flds as $f) {
// //       $clmn[$n][$f]= $f=='poznamka' && $x->r_note ? ($x->$f.' / '.$x->r_note) : $x->$f;
//       switch ($f) {
//       case '=par':      $clmn[$n][$f]= "{$x->prijmeni} {$x->jmena}"; break;
//       // fonty: ISOCTEUR, Tekton Pro
//       case '=pozpatku': $clmn[$n][$f]= otoc("{$x->prijmeni} {$x->jmena}"); break;
//       default:          $clmn[$n][$f]= $x->$f; break;
//       }
//     }
// //     break;
  }
//                                         debug($clmn,"sestava pro $akce,$typ,$fld,$cnd");
  return sta_table($tits,$flds,$clmn,$export);
}
# --------------------------------------------------------------------------------- tisk_text_vyroci
function tisk_text_vyroci($akce,$par,$title,$vypis,$export=false) { trace();
  $result= (object)array('_error'=>0);
  $html= '';
  // data akce
  $vyroci= array();
  // narozeniny
  $res= tisk_qry('ucastnik','prijmeni,jmeno,narozeni,role',
    "id_akce=$akce AND CONCAT(YEAR(datum_od),SUBSTR(narozeni,5,6)) BETWEEN datum_od AND datum_do",
    "","SUBSTR(narozeni,5,6)");
  while ( $res && ($x= mysql_fetch_object($res)) ) {
    $vyroci[$x->role=='d'?'d':'a'][]= "{$x->prijmeni} {$x->jmeno}|".sql_date1($x->narozeni);
  }
  // výročí
  $res= tisk_qry('pobyt_dospeli_ucastnici','datsvatba',
    "id_akce=$akce AND CONCAT(YEAR(datum_od),SUBSTR(datsvatba,5,6)) BETWEEN datum_od AND datum_do",
    "","SUBSTR(datsvatba,5,6)");
  while ( $res && ($x= mysql_fetch_object($res)) ) {
    $vyroci['s'][]= "$x->_jm|".sql_date1($x->datsvatba);
  }
  // nepřivítané děti mladší 2 let
  $res= tisk_qry('ucastnik','prijmeni,jmeno,narozeni,role,ROUND(DATEDIFF(a.datum_od,o.narozeni)/365.2425,1) AS _vek',
    "id_akce=$akce AND role='d' AND o.uvitano=0","_vek<2","prijmeni");
  while ( $res && ($x= mysql_fetch_object($res)) ) {
    $vyroci['v'][]= "{$x->prijmeni} {$x->jmeno}|".sql_date1($x->narozeni);
  }
  // redakce
  if ( count($vyroci['s']) ) {
    $html.= "<h3>Výročí svatby během akce</h3><table>";
    foreach($vyroci['s'] as $txt) {
      list($kdo,$kdy)= explode('|',$txt);
      $html.= "<tr><td>$kdy</td><td>$kdo</td></tr>";
    }
    $html.= "</table>";
  }
  else $html.= "<h3>Na akci nemá žádný pár výročí svatby</h3>";
  if ( count($vyroci['a']) ) {
    $html.= "<h3>Narozeniny dopělých na akci</h3><table>";
    foreach($vyroci['a'] as $txt) {
      list($kdo,$kdy)= explode('|',$txt);
      $html.= "<tr><td>$kdy</td><td>$kdo</td></tr>";
    }
    $html.= "</table>";
  }
  else $html.= "<h3>Na akci nemá žádný dospělý účastník narozeniny</h3>";
  if ( count($vyroci['d']) ) {
    $html.= "<h3>Narozeniny dětí na akci</h3><table>";
    foreach($vyroci['d'] as $txt) {
      list($kdo,$kdy)= explode('|',$txt);
      $html.= "<tr><td>$kdy</td><td>$kdo</td></tr>";
    }
    $html.= "</table>";
  }
  else $html.= "<h3>Na akci nemá žádné dítě narozeniny</h3>";
  if ( count($vyroci['v']) ) {
    $html.= "<h3>Nepřivítané děti mladší 2 let na akci</h3><table>";
    foreach($vyroci['v'] as $txt) {
      list($kdo,$kdy)= explode('|',$txt);
      $html.= "<tr><td>$kdy</td><td>$kdo</td></tr>";
    }
    $html.= "</table>";
  }
  else $html.= "<h3>Na akci nebudeme vítat žádné dítě</h3>";
  $result->html= $html;
  return $result;
}
# ----------------------------------------------------------------------------------- tisk_vyp_excel
# generování tabulky do excelu
function tisk_vyp_excel($akce,$par,$title,$vypis,$tab=null) {  trace();
  global $xA, $xn;
  $result= (object)array('_error'=>0);
  $html= '';
  // získání dat
  $title= str_replace('&nbsp;',' ',$title);
  if ( !$tab )
    $tab= tisk_sestava($akce,$par,$title,$vypis,true);
  // vlastní export do Excelu
  $name= cz2ascii("vypis_").date("Ymd_Hi");
  $xls= <<<__XLS
    |open $name
    |sheet vypis;;L;page
    |A1 $title ::bold size=14 |A2 $vypis ::bold size=12
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
    if ( $idw=='^' ) continue;
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
  $xls.= "\n|A$n:$A$n bcolor=ffc0e2c2 wrap border=+h|A$n:$A$n border=t\n";
  $n1= $n= 5;                                   // první řádek dat (pro sumy)
  // datové řádky
  if ( $tab->clmn ) foreach ($tab->clmn as $i=>$c) {
    $xls.= "\n";
    $lc= 0;
    foreach ($c as $id=>$val) {
      if ( $id[0]=='^' ) continue;
      $A= Excel5_n2col($lc);
      $format= '';
      if (isset($tab->expr[$i][$id]) ) {
        // buňka obsahuje vzorec
        $val= $tab->expr[$i][$id];
        $format.= ' bcolor=ffdddddd';
        $xn= $n;
        $val= preg_replace_callback("/\[([^,]*),([^\]]*)\]/","akce_vyp_subst",$val);
      }
      else {
        // buňka obsahuje hodnotu
        $val= strtr($val,array("\n\r"=>"  ","®"=>""));
        if ( isset($fmt[$A]) ) {
          switch ($fmt[$A]) {
          // aplikace formátů
          case 'l':                      $format.= ' left'; break;
          case 'd': $val= sql2xls($val); $format.= ' right date'; break;
          }
        }
      }
      $format= $format ? "::$format" : '';
      $val= str_replace("\n","{}",$val);        // ochrana proti řádkům v hodnotě - viz ae_slib
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
  // tabulka DPH, pokud je
  if ( $tab->DPH ) {
    $n+= 3;
    $nd1= $n;
    $xls.= "\n|A$n Tabulka DPH :: bcolor=ffc0e2c2 |A$n:B$n merge center\n";
    $n++;
    $nd= $n;
    for($i= 0; $i<count($tab->DPH); $i+= 2) {
      $lab= $tab->DPH[$i];
      $exp= $tab->DPH[$i+1];
      $xn= $ns;
      $exp= preg_replace_callback("/\[([^,]*),([^\]]*)\]/","akce_vyp_subst",$exp);
      $xls.= "|A$n $lab ::right|B$n $exp :: bcolor=ffdddddd";
      $n++;
    }
    $n--;
    $xls.= "\n|A$nd:B$n border=+h|A$nd1:B$n border=t";
  }
  // tabulka X, pokud je
  if ( $tab->X ) {
    $n+= 3;
    $nd1= $n;
    $xls.= "\n|A$n {$tab->X[0]} :: bcolor=ffc0e2c2 |A$n:B$n merge center\n";
    $n++;
    $nd= $n;
    for($i= 1; $i<count($tab->X); $i+= 2) {
      $lab= $tab->X[$i];
      $exp= $tab->X[$i+1];
      $xn= $ns;
      $exp= preg_replace_callback("/\[([^,]*),([^\]]*)\]/","akce_vyp_subst",$exp);
      $xls.= "|A$n $lab ::right|B$n $exp :: bcolor=ffdddddd";
      $n++;
    }
    $n--;
    $xls.= "\n|A$nd:B$n border=+h|A$nd1:B$n border=t";
  }
  // časová značka
  $kdy= date("j. n. Y v H:i");
  $n+= 2;
  $xls.= "|A$n Výpis byl vygenerován $kdy :: italic";
  // konec
  $xls.= <<<__XLS
    \n|close
__XLS;
  // výstup
                                                                display($xls);
  $inf= Excel5($xls,1);
  if ( $inf ) {
    $html= " se nepodařilo vygenerovat - viz začátek chybové hlášky";
    fce_error($inf);
  }
  else {
    $html= " Výpis byl vygenerován ve formátu <a href='docs/$name.xls' target='xls'>Excel</a>.";
  }
  $result->html= $html;
  return $result;
}
// function akce_vyp_subst($matches) { trace();
//   global $xA, $xn;
// //                                                 debug($xA);
// //                                                 debug($matches);
//   if ( !isset($xA[$matches[1]]) ) fce_error("akce_vyp_excel: chybný název sloupce '{$matches[1]}'");
//   $A= $xA[$matches[1]];
//   $n= $xn+$matches[2];
//   return "$A$n";
// }
# ---------------------------------------------------- sql2xls
// datum bez dne v týdnu
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
# ----------------------------------------------------------------------------==> akce2_text_prehled
function akce2_text_prehled($akce,$par,$title,$vypis,$export=false) { trace();
  $pocet= 0;
  # zobrazí odkaz na řádek s pobytem
  $sta_ukaz_pobyt= function ($idp,$jmeno) {
    return "<b><a href='ezer://db2.ucast.ucast_pobyt/$idp'>$jmeno</a></b>";
  };
  # naplní histogram podle $cond
  $akce_text_prehled_x= function ($akce,$cond,$uvest_jmena=false,$bez_tabulky=false)
                        use (&$pocet,$sta_ukaz_pobyt) {
    $html= '';
    // data akce
    $veky= $kluci= $holky= array();
    $nveky= $nkluci= $nholky= 0;
    $jmena= $deljmena= '';
    $bez= $del= '';
    // histogram věku dětí parametrizovaný přes $cond
    $qo=  "SELECT prijmeni,jmeno,narozeni,IFNULL(role,0),a.datum_od,o.sex,id_pobyt
           FROM akce AS a
           JOIN pobyt AS p ON a.id_duakce=p.id_akce
           JOIN spolu AS s USING(id_pobyt)
           JOIN osoba AS o ON s.id_osoba=o.id_osoba
           LEFT JOIN tvori AS t ON t.id_osoba=o.id_osoba AND IF(p.i0_rodina,t.id_rodina=p.i0_rodina,1)
           WHERE a.id_duakce='$akce' AND $cond AND funkce NOT IN (9,10,13,14) ORDER BY prijmeni ";
    $ro= mysql_qry($qo);
    while ( $ro && ($o= mysql_fetch_object($ro)) ) {
      $pocet++;
      $vek= narozeni2roky_sql($o->narozeni,$o->datum_od);
      $sex= $o->sex;
      $veky[$vek]++;
      $nveky++;
      if ( $sex==1 ) { $kluci[$vek]++; $nkluci++; }
      elseif ( $sex==2 ) { $holky[$vek]++; $nholky++; }
      else { $bez.= "$del{$o->prijmeni} {$o->jmeno}"; $del= ", "; }
      if ( $uvest_jmena ) {
        $jmena.= $deljmena;
        $jmena.= $sta_ukaz_pobyt($o->id_pobyt,"{$o->prijmeni} {$o->jmeno}/$vek");
        $deljmena= ", ";
      }
    }
    ksort($veky);
    // formátování výsledku
    if ( !$bez_tabulky ) {
      $html.= "<table class='stat'>";
      $r1= $r2= $r3= $r4= '';
      foreach($veky as $v=>$n) {
        $r1.= "<th align='right' width='20'>$v</th>";
        $style= $n==$kluci[$v]+$holky[$v] ? '' : " style='background-color:yellow'";
        $r2.= "<td align='right'$style>$n</td>";
        $r3.= "<td align='right'>{$kluci[$v]}</td>";
        $r4.= "<td align='right'>{$holky[$v]}</td>";
      }
      $r1.= "<th align='right'>celkem</th>";
      $style= $nveky==$nkluci+$nholky ? '' : " style='background-color:yellow'";
      $r2.= "<td align='right'$style>$nveky</td>";
      $r3.= "<td align='right'>$nkluci</td>";
      $r4.= "<td align='right'>$nholky</td>";
      $html.= "<tr><th>věk</th>$r1</tr><tr><th>počet</th>$r2</tr><tr>"
            . "<th>kluci</th>$r3</tr><tr><th>holky</th>$r4</tr></table>";
    }
    // jména
    if ( $jmena ) $html.= "<b>($jmena)</b>";
    // upozornění
    if ( $bez ) $html.= ($jmena?"<br>":'')."<i>(ani holka ani kluk: $bez)</i>";
    // předání výsledku
    return $html;
  };
  $result= (object)array();
  $html= '';
  $nedeti= $akce_text_prehled_x($akce,"t.role='d' AND p.funkce!=99 AND s.s_role NOT IN (2,3,4,5)",1,1);
  if ( $pocet>0 )
    $html.= "<h3 style='color:red'>POZOR! Děti vedené chybně jako účastníci nebo hosté</h3>$nedeti";
  // pfunkce: 0 4 5 8 92 95
  // pfunkce: 1=hlavoun, 2=instruktor, 3=pečovatel, 4=pomocný, 5=osobní, 6=mimořádný, 7=team, 8=člen G
  // funkce=99  -- pečoun, funkce=9,10,13,14 -- není na akci
  // s_role=2   -- dítě, s_role=3  -- dítě s os.peč, s_role=4  -- pom.peč, s_role=5  -- os.peč
  // dite_kat=7 -- skupina G
  // děti
  $html.= "<h2>Informace z karty Účastníci2 (bez náhradníků)</h2><h3>Celkový počet dětí na akci podle stáří (v době začátku akce) - bez os.pečounů včetně pom.pečounů</h3>";
  $html.= $akce_text_prehled_x($akce,"t.role='d' AND p.funkce!=99 AND s.s_role IN (0,1,2,3,4)");
  $html.= "<h3>Děti ve skupinkách (mimo G a osobně opečovávaných)</h3>";
  $html.= $akce_text_prehled_x($akce,"t.role='d' AND p.funkce!=99 AND s.s_role IN (2,4) AND s.dite_kat!=7");
  $html.= "<h3>Děti v péči osobního pečovatele</h3>";
  $html.= $akce_text_prehled_x($akce,"t.role='d' AND p.funkce!=99 AND s.s_role IN (3)",1);
  $html.= "<h3>Děti ve skupině G</h3>";
  $html.= $akce_text_prehled_x($akce,"t.role='d' AND p.funkce!=99 AND s.dite_kat=7",true);
  $html.= "<h3>Pomocní pečovatelé</h3>";
  $html.= $akce_text_prehled_x($akce,"t.role='d' AND p.funkce!=99 AND s.s_role IN (4)",1);
  $html.= "<h3>Osobní pečovatelé (nezařazení mezi Pečovatele)</h3>";
  $html.= $akce_text_prehled_x($akce,"p.funkce!=99 AND s.s_role IN (5) AND s.pfunkce NOT IN (5)",true);
  // osobní mezi pečouny
  $html.= "<br><hr><h3>Osobní pečovatelé (zařazení mezi Pečovatele)</h3>";
  $html.= $akce_text_prehled_x($akce,"p.funkce!=99 AND s.pfunkce IN (5)",true);
  // pečouni
  $html.= "<br><hr><h2>Informace z karty Pečouni</h2><h3>Řádní pečovatelé</h3>";
  $html.= $akce_text_prehled_x($akce,"p.funkce=99 AND s.pfunkce IN (1,2,3) ");
  $html.= "<h3>Mimořádní pečovatelé</h3>";
  $html.= $akce_text_prehled_x($akce,"p.funkce=99 AND s.pfunkce=6 ",true);
  $html.= "<h3>Team pečovatelů (s touto funkcí)</h3>";
  $html.= $akce_text_prehled_x($akce,"p.funkce=99 AND s.pfunkce IN (7) ",true);
  $html.= "<h3>Team pečovatelů (bez přiřazené funkce)</h3>";
  $html.= $akce_text_prehled_x($akce,"p.funkce=99 AND s.pfunkce IN (0) ",true);
  $result->html= "$html<br><br>";
  return $result;
}
# -------------------------------------------------------------------------------- akce2_strava_pary
# generování sestavy přehledu strav pro účastníky $akce - páry
#   $cnd = podmínka
#   $id_pobyt -- je-li udáno, počítá se jen pro tento jeden pobyt (jedněch účastníků)
# počítané položky
#   manzele = rodina.nazev muz a zena
# generované vzorce
#   platit = součet předepsaných plateb
function akce2_strava_pary($akce,$par,$title,$vypis,$export=false,$id_pobyt=0) { trace();
  $ord= $par->ord ? $par->ord : "IF(funkce<=2,1,funkce),IF(pouze=0,r.nazev,o.prijmeni)";
  $result= (object)array();
  $cnd= 1;
  $html= '';
  $href= '';
  $n= 0;
  // zjištění sloupců (0=ne)
  $tit= "Manželé a pečouni:25";  // bude opraveno podle skutečnosti před exportem
  $fld= "manzele";
  $dny= array('ne','po','út','st','čt','pá','so');
  $dny= array('n','p','ú','s','č','p','s');
  $qrya= "SELECT strava_oddo,datum_od,datum_do,DATEDIFF(datum_do,datum_od) AS _dnu
            ,DAYOFWEEK(datum_od)-1 AS _den1
          FROM akce WHERE id_duakce=$akce ";
  $resa= mysql_qry($qrya);
  if ( $resa && ($a= mysql_fetch_object($resa)) ) {
//                                                         debug($a,"akce {$a->_dnu}");
    $oo= $a->strava_oddo ? $a->strava_oddo : 'vo';
    $nd= $a->_dnu;
    for ($i= 0; $i<=$nd; $i++) {
      $den= $dny[($a->_den1+$i)%7].date('d',sql2stamp($a->datum_od)+$i*60*60*24).' ';
      if ( $i>0 || $oo[0]=='s' ) {
        $tit.= ",{$den}sc:4:r:s";
        $tit.= ",{$den}sp:4:r:s";
        $fld.= ",{$den}sc,{$den}sp";
      }
      if ( $i>0 && $i<$nd
        || $i==0   && ($oo[0]=='s' || $oo[0]=='o')
        || $i==$nd && ($oo[1]=='o' || $oo[1]=='v') ) {
        $tit.= ",{$den}oc:4:r:s";
        $tit.= ",{$den}op:4:r:s";
        $fld.= ",{$den}oc,{$den}op";
      }
      if ( $i<$nd || $oo[1]=='v' ) {
        $tit.= ",{$den}vc:4:r:s";
        $tit.= ",{$den}vp:4:r:s";
        $fld.= ",{$den}vc,{$den}vp";
      }
    }
//                                                         display($tit);
  }
  // dekódování parametrů
  $tits= explode(',',$tit);
  $flds= explode(',',$fld);
  $cond= $cnd;
  // získání dat - podle $kdo
  $clmn= array();       // pro hodnoty
  $expr= array();       // pro výrazy
  $suma= array();       // pro sumy sloupců id:::s
  $fmts= array();       // pro formáty sloupců id::f:
  for ($i= 0; $i<count($tits); $i++) {
    $idw= $tits[$i];
    $fld= $flds[$i];
    list($id,$w,$f,$sum)= explode(':',$idw);
    if ( $sum=='s' ) $suma[$fld]= 0;
    if ( isset($f) ) $fmts[$fld]= $f;
  }
  // pokud není id_pobyt tak vyloučíme náhradníky
  $cond.= $id_pobyt ? " AND id_pobyt=$id_pobyt" : " AND funkce NOT IN (9)";
  $jsou_pecouni= false;
  // data akce
  $res= tisk_qry('pobyt_dospeli_ucastnici',
    "COUNT(*) AS _pocet,funkce,pfunkce,strava_cel,strava_pol,cstrava_cel,cstrava_pol",
    "p.id_akce='$akce' AND IF(funkce=99,s_rodici=0 AND pfunkce,1) AND $cond",
    "","_jm");
  while ( $res && ($x= mysql_fetch_object($res)) ) {
//                                                         debug($x,"hodnoty");
    $n++;
    $clmn[$n]= array();
    if ( $x->funkce==99 && $x->pfunkce ) {
      // stravy pro pečouny - mají jednotně celou stravu - (s_rodici=0,pfunkce!=0 viz SQL)
      $jsou_pecouni= true;
      $clmn[$n]['manzele']= 'PEČOUNI';
      $sc= $x->_pocet;
      $k= 0;
      for ($i= 0; $i<=$nd; $i++) {
        if ( $i>0 || $oo[0]=='s' ) {
          $k++; $suma[$flds[$k]]+= $clmn[$n][$flds[$k]]= $sc;
          $k++; $suma[$flds[$k]]+= $clmn[$n][$flds[$k]]= $sp;
        }
        if ( $i>0 && $i<$nd
          || $i==0   && ($oo[0]=='s' || $oo[0]=='o')
          || $i==$nd && ($oo[1]=='o' || $oo[1]=='v') ) {
          $k++; $suma[$flds[$k]]+= $clmn[$n][$flds[$k]]= $sc;
          $k++; $suma[$flds[$k]]+= $clmn[$n][$flds[$k]]= $sp;
        }
        if ( $i<$nd || $oo[1]=='v' ) {
          $k++; $suma[$flds[$k]]+= $clmn[$n][$flds[$k]]= $sc;
          $k++; $suma[$flds[$k]]+= $clmn[$n][$flds[$k]]= $sp;
        }
      }
    }
    elseif ( $x->funkce!=99 ) {
      // stravy pro manžele
      $clmn[$n]['manzele']= $x->_jm;
//             $x->pouze==1 ? "{$x->prijmeni_m} {$x->jmeno_m}"
//          : ($x->pouze==2 ? "{$x->prijmeni_z} {$x->jmeno_z}"
//          : "{$x->nazev} {$x->jmeno_m} a {$x->jmeno_z}");
      $sc= $x->strava_cel;
      $sp= $x->strava_pol;
      $csc= $x->cstrava_cel;
      $csp= $x->cstrava_pol;
      $k= 0;
      for ($i= 0; $i<=$nd; $i++) {
        if ( $i>0 || $oo[0]=='s' ) {
          $k++; $suma[$flds[$k]]+= $clmn[$n][$flds[$k]]= $csc ? $csc[3*$i+0] : $sc;
          $k++; $suma[$flds[$k]]+= $clmn[$n][$flds[$k]]= $csp ? $csp[3*$i+0] : $sp;
        }
        if ( $i>0 && $i<$nd
          || $i==0   && ($oo[0]=='s' || $oo[0]=='o')
          || $i==$nd && ($oo[1]=='o' || $oo[1]=='v') ) {
          $k++; $suma[$flds[$k]]+= $clmn[$n][$flds[$k]]= $csc ? $csc[3*$i+1] : $sc;
          $k++; $suma[$flds[$k]]+= $clmn[$n][$flds[$k]]= $csp ? $csp[3*$i+1] : $sp;
        }
        if ( $i<$nd || $oo[1]=='v' ) {
          $k++; $suma[$flds[$k]]+= $clmn[$n][$flds[$k]]= $csc ? $csc[3*$i+2] : $sc;
          $k++; $suma[$flds[$k]]+= $clmn[$n][$flds[$k]]= $csp ? $csp[3*$i+2] : $sp;
        }
      }
    }
  }
//                                                         debug($suma,"sumy");
  $tits[0]= $jsou_pecouni ? "Manželé a pečouni:25" : "Manželé:25";
  if ( $export ) {
    $result->tits= $tits;
    $result->flds= $flds;
    $result->clmn= $clmn;
    $result->expr= $expr;
    $result->suma= $suma;
  }
  else {
    // titulky
    foreach ($tits as $idw) {
      list($id)= explode(':',$idw);
      $ths.= "<th>$id</th>";
    }
    // data
    foreach ($clmn as $i=>$c) {
      $tab.= "<tr>";
      foreach ($c as $id=>$val) {
        $style= akce_sestava_td_style($fmts[$id]);
        $tab.= "<td$style>$val</td>";
      }
      $tab.= "</tr>";
    }
    // sumy
    $sum= '';
    if ( count($suma)>0 ) {
      $sum.= "<tr>";
      foreach ($flds as $f) {
        $val= isset($suma[$f]) ? $suma[$f] : '';
        $sum.= "<th style='text-align:right'>$val</th>";
      }
      $sum.= "</tr>";
    }
    $result->html.= "<h3>Počty strav včetně pečounů</h3>";
    $result->html.= "nejsou započteni pečouni, kteří mají prázdný sloupec funkce (asi jsou jen dočasní)";
    $result->html.= "<br><br><div class='stat'><table class='stat'><tr>$ths</tr>$sum$tab</table></div>";
    $result->html.= "</br>";
    $result->href= $href;
  }
  return $result;
}
# ---------------------------------------------------------------------------- akce2_sestava_pecouni
# generování sestavy pro účastníky $akce - pečouny
#   $fld = seznam položek s prefixem
#   $cnd = podmínka
function akce2_sestava_pecouni($akce,$par,$title,$vypis,$export=false) { trace();
  $result= (object)array();
  $typ= $par->typ;
  $tit= $par->tit;
  $fld= $par->fld;
  $cnd= $par->cnd;
  $ord= $par->ord ? $par->ord : "CONCAT(o.prijmeni,' ',o.jmeno)";
  $html= '';
  $href= '';
  $n= 0;
  // číselníky                 akce.druh = ms_akce_typ:pečovatelé=7,kurz=1
  $pfunkce= map_cis('ms_akce_pfunkce','zkratka');  $pfunkce[0]= '?';
  // získání dat - podle $kdo
  $clmn= array();
  $expr= array();       // pro výrazy
  _akce2_sestava_pecouni($clmn,$akce,$fld,$cnd,$ord);
  // dekódování parametrů
  $flds= explode(',',$fld);
  $tits= explode(',',$tit);
  return sta_table($tits,$flds,$clmn,$export);
}
# --------------------------------------------------------------------------- _akce2_sestava_pecouni
# výpočet pro generování sestavy pro účastníky $akce - pečouny a pro statistiku
function _akce2_sestava_pecouni(&$clmn,$akce,$fld='_skoleni,_sluzba,_reflexe',$cnd=1,$ord=1) {
  $flds= explode(',',$fld);
  // data akce
  $rel= '';
  $rel= "-YEAR(narozeni)";
  $qry= " SELECT o.prijmeni,o.jmeno,o.narozeni,o.rc_xxxx,o.ulice,o.psc,o.obec,o.telefon,o.email,
            id_osoba,s.skupinka as skupinka,s.pfunkce,
            IF(o.note='' AND s.poznamka='','',CONCAT(o.note,' / ',s.poznamka)) AS _poznamky,
            GROUP_CONCAT(IF(xa.druh=7 AND MONTH(xa.datum_od)<=7,YEAR(xa.datum_od)$rel,'')
              ORDER BY xa.datum_od DESC SEPARATOR ' ') AS _skoleni,
            GROUP_CONCAT(IF(xa.druh=1,YEAR(xa.datum_od)$rel,'')
              ORDER BY xa.datum_od DESC SEPARATOR ' ') AS _sluzba,
            GROUP_CONCAT(IF(xa.druh=7 AND MONTH(xa.datum_od)>7,YEAR(xa.datum_od)$rel,'')
              ORDER BY xa.datum_od DESC SEPARATOR ' ') AS _reflexe,
            YEAR(narozeni)+18 AS _18
          FROM pobyt AS p
          JOIN spolu AS s USING (id_pobyt)
          JOIN osoba AS o USING (id_osoba)
          JOIN akce  AS a ON a.id_duakce=p.id_akce
          JOIN spolu AS xs USING (id_osoba)
          JOIN pobyt AS xp ON xp.id_pobyt=xs.id_pobyt AND xp.funkce=99
          JOIN akce  AS xa ON xa.id_duakce=xp.id_akce AND YEAR(xa.datum_od)<=YEAR(a.datum_od)
          -- JOIN join_akce AS xg ON xg.id_akce=xp.id_akce
          WHERE p.funkce=99 AND p.id_akce='$akce' AND $cnd
          GROUP BY id_osoba
          ORDER BY $ord";
  $res= mysql_qry($qry);
  while ( $res && ($x= mysql_fetch_object($res)) ) {
    $n++;
    $clmn[$n]= array();
    foreach($flds as $f) {
      // sumáře
      switch ($f) {
      case 'pfunkce':   $clmn[$n][$f]= $pfunkce[$x->$f]; break;
      case '^id_osoba': $clmn[$n][$f]= $x->id_osoba; break;
      case '_skoleni':
      case '_sluzba':
      case '_reflexe':
        $lst= preg_split("/\s+/",$x->$f, -1, PREG_SPLIT_NO_EMPTY);
        $lst= array_unique($lst);
        $clmn[$n][$f]= ' '.implode(' ',$lst);
        //$clmn[$n][$f]= ' '.trim(str_replace('  ',' ',$x->$f));
        break;
      default:          $clmn[$n][$f]= $x->$f;
      }
    }
  }
}
/** ==========================================================================================>> PDF **/
# -------------------------------------------------------------------------------- tisk_pdf_jmenovky
# vygenerování PDF s vizitkami s rozměrem 55x90 na rozstříhání
#   $the_json obsahuje  title:'{jmeno}<br>{prijmeni}'
function tisk_pdf_jmenovky($akce,$par,$title,$vypis,$report_json) {  trace();
  global $json, $ezer_path_docs;
  $result= (object)array('_error'=>0);
  $html= '';
  // získání dat
  mb_internal_encoding('UTF-8');
  $tab= tisk_sestava($akce,$par,$title,$vypis,true);
//                                         display($report_json);
//                                         debug($tab,"akce_sestava($akce,...)"); //return;
  $report_json= "{'format':'A4:15,10,90,55','boxes':["
    . "{'type':'text','left':0,'top':0,'width':90,'height':55,'id':'ram','style':'1,L,LTRB:0.05 dotted 250',txt:' '},"
    . "{'type':'text','left':10,'top':10,'width':80,'height':40,'id':'jmeno','txt':'{jmeno}<br />{prijmeni}','style':'30,L'}]}";
  $report_json= "{'format':'A4:15,10,90,55','boxes':["
    . "{'type':'text','left':0,'top':0,'width':90,'height':55,'id':'ram','style':'1,L,LTRB:0.05 dotted',txt:' '},"
    . "{'type':'text','left':10,'top':10,'width':80,'height':40,'id':'jmeno','txt':'{jmeno}<br />{prijmeni}','style':'30,L'}]}";
//                                         display($report_json);
  // projdi vygenerované záznamy
  $n= 0;
  $parss= array();
  foreach ( $tab->clmn as $xa ) {
    // definice pole substitucí
    $x= (object)$xa;
    $parss[$n]= (object)array();
    $fsize= mb_strlen($x->jmeno)>8 ? 13 : 14;
    $parss[$n]->jmeno= "<span style=\"font-size:{$fsize}mm;font-weight:bold\">{$x->jmeno}</span>";
    list($prijmeni)= explode(' ',$x->prijmeni);
    $fsize= mb_strlen($prijmeni)>10 ? 10 : 12;
    $parss[$n]->prijmeni= "<span style=\"font-size:{$fsize}mm;font-weight:bold\">{$prijmeni}</span>";
    $n++;
  }
  // předání k tisku
  $fname= 'jmenovky_'.date("Ymd_Hi");
  $fpath= "$ezer_path_docs/$fname.pdf";
  dop_rep_ids($report_json,$parss,$fpath);
  $result->html= " Výpis byl vygenerován ve formátu <a href='docs/$fname.pdf' target='pdf'>PDF</a>.";
  return $result;
}
# ------------------------------------------------------------------------------- tisk_pdf_stravenky
# generování štítků se stravenkami pro rodinu účastníka a pro pečouny do PDF
# pomocí akce_sestava se do objektu $x->tab vygeneruje pole s elementy pro tisk stravenky
function tisk_pdf_stravenky($akce,$par,$title,$vypis,$report_json) {  trace();
  global $json, $ezer_path_docs, $EZER;
  $result= (object)array('_error'=>0);
  $html= '';
  // získání dat
  $x= tisk_sestava($akce,$par,$title,$vypis,true);
  $header= "{$EZER->options->org}, {$x->akce->misto} {$x->akce->rok}";
  $sob= array('s'=>'snídaně','o'=>'oběd','v'=>'večeře');
  $cp=  array('c'=>'1','p'=>'1/2');
  // projdi vygenerované záznamy
  $n= 0;
  $parss= array();
  foreach ( $x->tab as $jmeno=>$dny ) {
    // vynechání prázdných míst, aby jméno bylo v prvním sloupci ze 4
    $k= 4*ceil($n/4)-$n;
    for ($i= 0; $i<$k; $i++) {
      $parss[$n]= (object)array();
      $parss[$n]->header= $parss[$n]->line1= $parss[$n]->line2= '';
      $parss[$n]->rect= $parss[$n]->ram= $parss[$n]->end= '';
      $n++;
    }
    // stravenky pro účastníka
    list($prijmeni,$jmena)= explode('|',$jmeno);
//                                                         if ( $prijmeni!="Bučkovi" ) continue;
    $parss[$n]= (object)array();
    $parss[$n]->header= $header;
    $parss[$n]->line1= "<b>$prijmeni</b>";
    $parss[$n]->line2= "$jmena";
    $parss[$n]->rect= '';
    $parss[$n]->ram= ' ';
    $parss[$n]->end= '';
    $n++;
    foreach ( $dny as $den=>$jidla ) {
      // stravenky na jeden den
      foreach ( $jidla as $jidlo=>$porce ) {
        // denní jídlo
        foreach ( $porce as $velikost=>$pocet ) {
          // porce
          for ($i= 1; $i<=$pocet; $i++) {
            // na začátku stránky dej příznak pokračování
            if ( ($n % (4*12) )==0 ) {
              $parss[$n]= (object)array();
              $parss[$n]->header= $header;
              $parss[$n]->line1= "<b>... $prijmeni</b>";
              $parss[$n]->line2= "... $jmena";
              $parss[$n]->rect= $parss[$n]->ram= $parss[$n]->end= '';
              $n++;
            }
            // text stravenky na jedno jídlo
            $parss[$n]= (object)array();
            $parss[$n]->header= $header;
            $parss[$n]->line1= "$den";
            $parss[$n]->line2= "<b>{$sob[$jidlo]}</b>";
            if ( $velikost=='c' ) {
              // celá porce
              $parss[$n]->ram= '<img src="db/img/stravenky-rastr-1.png"'
                             . ' style="width:48mm" border="0" />';
              $parss[$n]->rect=  " ";
            }
            else {
              // poloviční porce
              $parss[$n]->ram= '';
              $parss[$n]->rect=  "<b>1/2</b>";
            }
            $parss[$n]->end= '';
            $n++;
          }
        }
      }
    }
    // na konec dej koncovou značku
    $parss[$n]= (object)array();
    $parss[$n]->header= $header;
    $parss[$n]->line1= "<b>$prijmeni</b>";
    $parss[$n]->line2= "(konec stravenek)";
    $parss[$n]->rect= $parss[$n]->ram= '';
    $parss[$n]->end= ' ';
    $n++;
  }
  // předání k tisku
//                                         debug($parss,"akce_pdf_stravenky");
  $fname= 'stravenky_'.date("Ymd_Hi");
  $fpath= "$ezer_path_docs/$fname.pdf";
  dop_rep_ids($report_json,$parss,$fpath);
  $result->html= " Výpis byl vygenerován ve formátu <a href='docs/$fname.pdf' target='pdf'>PDF</a>.";
  return $result;
}
/** =================================================================================>> STATISTIKA 1 **/
# ----------------------------------------------------------------------------------- evid_sestava_1
# tabulka struktury kurzu (noví,podruhé,vícekrát,odpočívající VPS,VPS)
function sta_sestava_1($par,$title,$export=false) {
  $par->fld= 'nazev';
  $par->tit= 'nazev';
  $tab= evid_sestava_v($par,$title,true);
//                                                         debug($tab,"evid_sestava_v(,$title,$export)");
  $clmn= $suma= array();
  $tit= "rok,rodin,u nás - noví,podruhé,vícekrát,vps - odpočívající,ve službě,dětí na kurzu";
  $tits= explode(',',$tit);
  $fld= "rr,x,n,p,v,vo,vs,d";
  $flds= explode(',',$fld);
  $flds_rr= explode(',',substr($fld,3));
  for ($rrrr=date('Y');$rrrr>=1990;$rrrr--) {
    $rr= substr($rrrr,-2);
    $clmn[$rr]= array('rr'=>$rrrr,'x'=>0);
    $rows= count($tab->clmn);
    for ($n= 1; $n<=$rows; $n++) {
      if ( $xrr= $tab->clmn[$n][$rr] ) {
        $vps= 0;
        $ucast= 0;
        for ($yyyy= $rrrr; $yyyy>=1990; $yyyy--) {
          $yy= substr($yyyy,-2);
          if ( $tab->clmn[$n][$yy] ) $ucast++;
          if ( $tab->clmn[$n][$yy]=='v' ) $vps++;
        }
        // zhodnocení minulosti
        $clmn[$rr]['n']+= !$vps && $ucast==1 ? 1 : 0;
        $clmn[$rr]['p']+= !$vps && $ucast==2 ? 1 : 0;
        $clmn[$rr]['v']+= !$vps && $ucast>2  ? 1 : 0;
        $clmn[$rr]['vo']+= $vps && $xrr=='o' ? 1 : 0;
        $clmn[$rr]['vs']+= $vps && $xrr=='v' ? 1 : 0;
      }
    }
    // přepočty v daném roce
    $suma[$rr]= 0;
    foreach($flds_rr as $fld) {
      $suma[$rr]+= $clmn[$rr][$fld];
    }
  }
  // doplnění počtů dětí
  $qry= "SELECT YEAR(datum_od) AS _rok,id_akce,count(DISTINCT id_pobyt) AS _pary,
           SUM(IF(role='d',1,0)) AS _deti
         FROM pobyt AS p
         JOIN spolu AS s USING(id_pobyt)
         JOIN osoba AS o ON o.id_osoba=s.id_osoba
         JOIN tvori AS t ON t.id_osoba=o.id_osoba
         JOIN akce AS a ON a.id_duakce=p.id_akce
         WHERE a.druh=1 AND p.funkce IN (0,1) AND spec=0
         GROUP BY id_akce";
  $res= mysql_qry($qry);
  while ( $res && ($x= mysql_fetch_object($res)) ) {
    $rr= substr($x->_rok,-2);
    $clmn[$rr]['d']+= $x->_deti;
    $clmn[$rr]['x']+= $x->_pary;
  }
  // smazání prázdných
  foreach ($clmn as $r=>$c) {
    if ( !$c['x'] ) unset($clmn[$r]);
  }

//                                         debug($suma,"součty");
//                                                         debug($clmn,"evid_sestava_s:$tit;$fld");
  $par->tit= $tit;
  $par->fld= $fld;
  $par->grf= "x:n,p,v,vo,vs,d";
  $par->txt= "Pozn. Graficky je znázorněn relativní počet vzhledem k počtu párů.;
    <br>Pokud v nějakém roce bylo více běhů je zobrazen jejich součet.";
  return evid_table($par,$tits,$flds,$clmn,$export);
//     return sta_table($tits,$flds,$clmn,$export);
}
/** ===================================================================================== STATISTIKA 2 **/
# ----------------------------------------------------------------------------------- sta_ukaz_osobu
# zobrazí odkaz na osobu v evidenci
function sta_ukaz_osobu($ido,$barva='') {
  $style= $barva ? "style='color:$barva'" : '';
  return "<b><a $style href='ezer://db2.evi.evid_osoba/$ido'>$ido</a></b>";
}
# ----------------------------------------------------------------------------------- sta_ukaz_osobu
# zobrazí odkaz na řádek s pobytem
function sta_ukaz_pobyt($idp,$barva='') {
  $style= $barva ? "style='color:$barva'" : '';
  return "<b><a $style href='ezer://db2.ucast.ucast_pobyt/$idp'>$idp</a></b>";
}
# -------------------------------------------------------------------------------------- sta_sestava
# sestavy pro evidenci
function sta_sestava($title,$par,$export=false) {
//                                                 debug($par,"sta_sestava($title,...,$export)");
  $ret= (object)array('html'=>'','err'=>0);
  // dekódování parametrů
  $tits= $par->tit ? explode(',',$par->tit) : array();
  $flds= $par->fld ? explode(',',$par->fld) : array();
  $clmn= array();
  $expr= array();       // pro výrazy
  // získání dat
  switch ($par->typ) {
  # Sestava přednášejících na letních kurzech, rok= kolik let dozadu (0=jen letos)
  case 'prednasejici': // --------------------------------------->> sta přednášející na LK během let
    $do= date('Y');
    $od= $do - $par->parm + 1;
    $tits[]= "přednáška:20";
    $flds[]= 1;
    for ($rok= $do; $rok>=$od; $rok--) {
      $tits[]= "$rok:26";
      $flds[]= $rok;
    }
    $prednasky= map_cis('ms_akce_prednasi','zkratka');
    foreach ($prednasky as $pr=>$prednaska) {
      $clmn[$pr][1]= $prednaska;
      $rx= mysql_qry("SELECT prednasi,YEAR(a.datum_od) AS _rok,
          GROUP_CONCAT(DISTINCT IF(t.role='a',o.prijmeni,'') SEPARATOR '') as prijmeni_m,
          GROUP_CONCAT(DISTINCT IF(t.role='a',o.jmeno,'')    SEPARATOR '') as jmeno_m,
          GROUP_CONCAT(DISTINCT IF(t.role='b',o.prijmeni,'') SEPARATOR '') as prijmeni_z,
          GROUP_CONCAT(DISTINCT IF(t.role='b',o.jmeno,'')    SEPARATOR '') as jmeno_z,
          p.pouze,r.nazev
        FROM pobyt AS p
        JOIN spolu AS s USING(id_pobyt)
        JOIN osoba AS o ON s.id_osoba=o.id_osoba
        LEFT JOIN tvori AS t ON t.id_osoba=o.id_osoba
        LEFT JOIN rodina AS r ON r.id_rodina=IFNULL(i0_rodina,t.id_rodina)
        JOIN akce AS a ON a.id_duakce=p.id_akce
        WHERE a.druh=1 AND p.prednasi=$pr AND YEAR(a.datum_od) BETWEEN $od AND $do
        GROUP BY id_pobyt -- ,_rok
        ORDER BY _rok DESC");
      while ( $rx && ($x= mysql_fetch_object($rx)) ) {
        $jm= $x->pouze==1 ? "{$x->prijmeni_m} {$x->jmeno_m}"
           : ($x->pouze==2 ? "{$x->prijmeni_z} {$x->jmeno_z}"
           : "{$x->nazev} {$x->jmeno_m} a {$x->jmeno_z}");
        if ( isset($clmn[$pr][$x->_rok]) ) {
          $xx= "{$prednasky[$x->prednasi]}/{$x->_rok}";
          fce_warning("POZOR: přednáška $xx má více přednášejících");
        }
        $clmn[$pr][$x->_rok].= "$jm ";
      }
    }
//                                                 debug($clmn,"$od - $do");
    break;
  # Sestava ukazuje letní kurzy
  # fld:'_rok,_pec,_sko,_proc,_pecN,_skoN,_procN,_note'
  case 'ms-pecouni': // ----------------------------------------->> sta proškolení pečounů během let
    # _pec,_sko,_proc
    list($od,$do)= select("MAX(YEAR(datum_od)),MIN(YEAR(datum_od))","akce","druh=1");
    for ($rok=$od; $rok>=$do; $rok--) {
      $kurz= select1("id_duakce","akce","druh=1 AND YEAR(datum_od)=$rok");
      $akci= select1("COUNT(*)","akce","druh=7 AND YEAR(datum_od)=$rok");
      $akci= $akci ? "$akci školení" : '';
      $info= akce2_info($kurz,0); //muzi,zeny,deti,peco,rodi,skup
      // získání dat
      $_pec= $_sko= $_proc= $_pecN= $_skoN= $_procN= 0;
      $data= array();
      _akce2_sestava_pecouni($data,$kurz);
      $_pec= count($data);
      if ( !$_pec ) continue;
      foreach ($data as $d) {
        $skoleni= 0;
        $sko= array_unique(preg_split("/\s+/",$d['_skoleni'], -1, PREG_SPLIT_NO_EMPTY));
        $slu= array_unique(preg_split("/\s+/",$d['_sluzba'],  -1, PREG_SPLIT_NO_EMPTY));
        $ref= array_unique(preg_split("/\s+/",$d['_reflexe'], -1, PREG_SPLIT_NO_EMPTY));
        $leto= $slu[0];
        // výpočet školení všech
        $skoleni+= count($sko);
        foreach ($ref as $r) if ( $r<$leto ) $skoleni++;
        $_sko+= $skoleni>0 ? 1 : 0;
        // noví
        if ( count($slu)==1 ) {
          $_pecN++;
          $_skoN+= $skoleni>0 ? 1 : 0;
        }
      }
      $_proc= $_pec ? round(100*$_sko/$_pec).'%' : '';
      $_procN= $_pecN ? round(100*$_skoN/$_pecN).'%' : '';
      $note= $akci;
      $ratio= round($info->deti/$_pec,1);
      $note.= ", $ratio";
      // zobrazení výsledků
      $clmn[]= array('_rok'=>$rok,'_rodi'=>$info->rodi,'_deti'=>$info->deti,
        '_pec'=>$_pec,'_sko'=>$_sko,'_proc'=>$_proc,
        '_pecN'=>$_pecN,'_skoN'=>$_skoN,'_procN'=>$_procN,'_note'=>$note);
//       if ( $rok==2014) break;
    }
    break;
  # Sestava ukazuje celkový počet účastníků resp. pečovatelů na akcích letošního roku,
  # rozdělený podle věku. Účastník resp. pečovatel je započítán jen jednou,
  # bez ohledu na počet akcí, jichž se zúčastnil
  case 'ucast-vek': // ----------------------------------------------------------->> sta účast-věk
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
  case 'adresy': // ----------------------------------------------------------->> sta účastníci akcí
    $rok= date('Y') - $par->parm;
    $rok18= date('Y')-18;
    $AND= $par->cnd ? " AND $par->cnd " : '';
    // úprava title pro případný export do xlsx
    $par->title= $title.($par->rok ? " akcí za poslední ".($par->rok+1)." roky" : " letošních akcí");
    $idr0= -1; $ido= 0;
    $jmena= $role= $prijmeni= $akce= array();
    $adresa= '';
    $mrop= $pps= 0;
    // funkce pro přidání nové adresy do clmn: jmena,ulice,psc,obec,stat,akce,prijmeni,_clenu,id_osoba
    $add_address= function() use (&$clmn,&$jmena,&$role,&$prijmeni,&$adresa,&$akce,&$mrop,&$ido,&$pps) {
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
      $pp= $pps?:'';
      $clmn[]= array('jmena'=>$jm,'ulice'=>$ul,'psc'=>$ps,'obec'=>$ob,'stat'=>$st,
                     'prijmeni'=>$pr,'_cleni'=>$jc,'akce'=>$ak,'_mrop'=>$mr,'_pps'=>$pp,'_clenu'=>$cl,'id_osoba'=>$ido);
    };
    $rx= mysql_qry("
      SELECT
        IFNULL(IF(adresa=0,SUBSTR(MIN(CONCAT(t.role,r.nazev,'—')),2),prijmeni),prijmeni) AS _order,
        IFNULL(IF(adresa=0,SUBSTR(MIN(CONCAT(t.role,id_rodina)),2),0),0) AS _idr,
        IFNULL(IF(adresa=0,MIN(t.role),'-'),'-') AS _role,
        IFNULL(IF(adresa=0,SUBSTR(MIN(
          CONCAT(t.role,r.nazev,'—',r.ulice,'—',r.psc,'—',r.obec,'—',r.stat)),2),''),'') AS _rodina,
        id_osoba,prijmeni,jmeno,adresa,iniciace,
        MAX(IF(t.role IN ('a','b') AND p.funkce=1,YEAR(datum_od),0)) as _pps,
        -- IF(roleMAX(CONCAT(YEAR(datum_od),' - ',a.nazev)) as _akce,
        MAX(CONCAT(datum_od,' - ',a.nazev)) as _akce,
        IF(ISNULL(id_rodina) OR adresa=1,CONCAT(o.ulice,'—',o.psc,'—',o.obec,'—',o.stat),'') AS _osoba,
        IF(ISNULL(id_rodina) OR adresa=1,o.psc,r.psc) AS _psc,
        IF(ISNULL(id_rodina) OR adresa=1,o.stat,r.stat) AS _stat
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
      GROUP BY o.id_osoba HAVING _role!='p' $AND
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
        $pps= max($pps,$x->_pps);
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
        $pps= $x->_pps;
        $adresa= $x->_osoba ? "{$x->prijmeni}—$x->_osoba" : $x->_rodina;
        $idr0= $idr;
      }
    }
    $add_address();
    break;
  default:
    $ret->err= $ret->html= 'N.Y.I.';
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
        if ( $f=='id_osoba' || $f=='^id_osoba' )
          $tab.= "<td style='text-align:right'>".sta_ukaz_osobu($c[$f])."</td>";
        elseif ( $f=='^id_pobyt' )
          $tab.= "<td style='text-align:right'>".sta_ukaz_pobyt($c['^id_pobyt'])."</td>";
        else
          $tab.= "<td style='text-align:left'>{$c[$f]}</td>";
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
/** ======================================================================================= EVIDENCE **/
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
//                                                         debug($ret,"elim_data_osoba");
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
/** ===================================================================== EVIDENCE - BROWSE - ÚČASTI **/
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
/** ====================================================================================== ÚČASTNÍCI **/
# ------------------------------------------------------------------------------------ akce2_osoba2x
# ASK volané z formuláře _osoba2x při onchange.adresa a onchange.kontakt
# v ret vrací o_kontakt, r_kontakt, o_adresa, r_adresa
function akce2_osoba2x($id_osoba) { trace();
  $rets= "o_kontakt,r_kontakt,o_adresa,r_adresa";
  $adresa= "ulice,psc,obec,stat,noadresa";
  $kontakt= "telefon,email,nomail";         // rodina s -y na konci
  $kontakty= "telefony,emaily,nomaily";
  $k= sql_query("
    SELECT IFNULL(SUBSTR(
      (SELECT MIN(CONCAT(role,id_rodina))
        FROM tvori AS ot JOIN rodina AS r USING (id_rodina) WHERE ot.id_osoba=o.id_osoba
      ),2),0) AS id_rodina
    FROM osoba AS o
    WHERE o.id_osoba='$id_osoba'
    GROUP BY o.id_osoba");
  $o= sql_query("SELECT $adresa,$kontakt FROM osoba WHERE id_osoba='$id_osoba'");
  $r= sql_query("SELECT $adresa,$kontakty FROM rodina WHERE id_rodina='$k->id_rodina'");
                                                        debug($k,"kmen");
                                                        debug($r,"rodina");
                                                        debug($o,"osoba ".(empty($o)?'e':'f'));
  $ret= (object)array();
  foreach(explode(',',$rets) as $f) {
    $ret->$f= (object)array();
  }
  foreach(explode(',',$adresa) as $f) {
    $ret->o_adresa->$f= empty($o) ? '' : $o->$f;
    $ret->r_adresa->$f= empty($r) ? '' : ($f=='noadresa'||$f=='stat'?'':'®').$r->$f;
  }
  foreach(explode(',',$kontakt) as $f) { $fy= $f.'y';
    $ret->o_kontakt->$f= empty($o) ? '' : $o->$f;
    $ret->r_kontakt->$f= empty($r) ? '' : ($f=='nomail'?'':'®').$r->$fy;
  }
                                                        debug($ret,"akce2__osoba2x");
  return $ret;
}
# ------------------------------------------------------------------------------------ akce2_ido2idp
# ASK získání pobytu účastníka na akci
function akce2_ido2idp($id_osoba,$id_akce) { trace();
  $idp= select("id_pobyt","spolu JOIN pobyt USING (id_pobyt)",
    "spolu.id_osoba=$id_osoba AND id_akce=$id_akce");
  return $idp;
}
# ------------------------------------------------------------------------------------- akce2_pridej
# ASK přidání pobytu do akce, pokud ještě nebyly tyto osoby/osoba přidány
function akce2_pridej($id_akce,$info) { trace();
  $ret= (object)array('ok'=>0,'msg'=>'chyba při vkládání');
//                                                         debug($info);
  $ids= array();
  if ( $info->id )  array_push($ids,$info->id);
  if ( $info->muz ) array_push($ids,$info->muz);
  if ( $info->zen ) array_push($ids,$info->zen);
  $note= $info->nazev;
  // kontrola nepřítomnosti
  $qp= "SELECT id_pobyt FROM pobyt AS p JOIN spolu AS s USING(id_pobyt)
        WHERE id_akce=$id_akce AND id_osoba IN (".implode(',',$ids).")";
  $rp= mysql_qry($qp);
  $jsou= mysql_num_rows($rp);
  if ( $jsou ) { // už jsou na akci
    $ret->msg= "... již jsou mezi účastníky akce";
  }
  else {
    // vložení nového pobytu
    $rod= $pouze==0 && $info->rod ? $info->rod : 0;
    $qi= "INSERT pobyt (id_akce,i0_rodina) VALUES ($id_akce,$rod)";
    $ri= mysql_qry($qi); if ( !$ri ) goto end;
    $ret->pobyt= mysql_insert_id();
    // vložení účastníků
    foreach ($ids as $id) {
      $qi= "INSERT spolu (id_pobyt,id_osoba,s_role) VALUES ({$ret->pobyt},$id,1)";
      $ri= mysql_qry($qi); if ( !$ri ) goto end;
    }
    $ret->ok= 1;
    $ret->msg= "... vloženo";
  }
end:
//                                                         debug($ret,"akce2_pridej");
  return $ret;
}
# ---------------------------------------------------------------------------- akce2_pridej_k_pobytu
# ASK přidání do daného pobytu akce, pokud ještě osoba na akci není
# spolupracuje s: akce2_auto_jmena1,akce2_auto_jmena1L a číselníky: ms_akce_s_role,ms_akce_dite_kat
# info = {id,nazev,role}
function akce2_pridej_k_pobytu($id_akce,$id_pobyt,$info,$cnd='') { trace();
//                                                 debug($info,"akce2_pridej_k_pobytu");
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
    $kat= 0; $srole= 1;                                         // default= účastník, nedítě
    // odhad typu účasti podle stáří a role
    if     ( $role=='p' )                         { $kat= 0; $srole= 5; }   // osob.peč.
    elseif ( $vek>=18 || $narozeni=='0000-00-00') { $kat= 0; $srole= 1; }   // účastník
    elseif ( (!$role || $role=='d') && $vek>=17 ) { $kat= 1; $srole= 2; }   // dítě - A|G
    elseif ( (!$role || $role=='d') && $vek>=13 ) { $kat= 1; $srole= 2; }   // dítě - A
    elseif ( (!$role || $role=='d') && $vek>=3 )  { $kat= 3; $srole= 2; }   // dítě - C
    elseif ( (!$role || $role=='d') && $vek>=2 )  { $kat= 5; $srole= 2; }   // dítě - E
    elseif ( (!$role || $role=='d') && $vek>=0 )  { $kat= 6; $srole= 3; }   // dítě - F
//                                                 display("$vek= roku_k($narozeni,$datum_od), role=$role");
    if ( query("INSERT INTO spolu (id_pobyt,id_osoba,s_role,dite_kat)
         VALUE ($id_pobyt,$ido,$srole,$kat)") ) { // ms_akce_s_role, ms_akce_dite_kat
      $ret->spolu= mysql_insert_id();
    }
    else  $ret->msg= 'chyba při vkládání';
  }
//                                                 debug($ret,"$vek $kat $srole");
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
/** ============================================================================= ÚČASTNÍCI - BROWSE **/
# ---------------------------------------------------------------------------------- akce_browse_ask
# obsluha browse s optimize:ask
# x->order= {a|d} polozka
# x->show=  {polozka:[formát,vzor/1,...],...} pro položky s neprázdným vzorem
#                                             kde formát=/ = # $ % @ * .
# x->cond= podmínka
# pokud je tisk=true jsou je oddělovače řádků použit znak '≈' (oddělovač sloupců zůstává '~')
function akce_browse_ask($x,$tisk=false) {
  $delim= $tisk ? '≈' : '~';
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
//                                                         debug($x,"akce_browse_ask");
//                                                         return;
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
//     $AND= "AND p.id_pobyt IN (15202) -- NULL";
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
      SELECT s.*,o.narozeni,MIN(CONCAT(IF(role='','?',role),id_rodina)) AS _role
      FROM osoba AS o
      JOIN spolu AS s USING (id_osoba)
      JOIN pobyt AS p USING (id_pobyt)
      LEFT JOIN tvori AS t USING (id_osoba)
      WHERE o.deleted='' AND $cond $AND
      GROUP BY id_osoba
    ");
    while ( $qu && ($u= mysql_fetch_object($qu)) ) {
      $cleni.= ",{$u->id_osoba}";
      $rodiny.= substr($u->_role,1) ? ",".substr($u->_role,1) : '';
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
      $rodiny.= $p->id_rodina ? ",{$p->id_rodina}" : '';
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
    # seznam rodin osob
    $qor= mysql_qry("
      SELECT id_osoba,
        IFNULL(GROUP_CONCAT(CONCAT(role,':',id_rodina) SEPARATOR ','),'') AS _rody,
        SUBSTR(MIN(CONCAT(IF(role='','?',role),id_rodina)),2) AS _kmen
      FROM osoba
      JOIN tvori USING(id_osoba)
      WHERE deleted='' AND id_osoba IN (0$osoby)
      GROUP BY id_osoba
    ");
    while ( $qor && ($or= mysql_fetch_object($qor)) ) {
      if ( !isset($osoba[$or->id_osoba]) ) $osoba[$or->id_osoba]= (object)array();
      $osoba[$or->id_osoba]->_rody= $or->_rody;
      $kmen= $or->_kmen;
      $osoba[$or->id_osoba]->_kmen= $kmen;
      if ( !$rodina[$kmen] ) {
        # doplnění (potřebných) rodinných údajů pro kmenové rodiny
//                                                         display("{$or->id_osoba} - $kmen");
        $qr= mysql_qry("
          SELECT * -- id_rodina,nazev,ulice,obec,psc,stat,telefony,emaily
          FROM rodina AS r WHERE id_rodina=$kmen");
        while ( $qr && ($r= mysql_fetch_object($qr)) ) {
          $rodina[$kmen]= $r;
        }
      }
    }
//                                                         display("rodiny:$rodiny");
//                                                         debug($rodina,$rodiny);
    # seznamy položek
    $fpob1= flds("key_pobyt=id_pobyt,_empty=0,key_akce=id_akce,key_osoba,key_spolu,key_rodina=i0_rodina,"
           . "c_suma,platba,xfunkce=funkce,funkce,skupina,dluh");
    $fakce= flds("dnu,datum_od");
    $frod=  flds("fotka,r_spz=spz,r_svatba=svatba,r_datsvatba=datsvatba,r_rozvod=rozvod,r_ulice=ulice,r_psc=psc,"
          . "r_obec=obec,r_stat=stat,r_telefony=telefony,r_emaily=emaily,r_umi,r_note=note");
    $fpob2= flds("p_poznamka=poznamka,pokoj,budova,prednasi,luzka,pristylky,kocarek,pocetdnu"
          . ",strava_cel,strava_pol,c_nocleh=platba1,c_strava=platba2,c_program=platba3,c_sleva=platba4"
          . ",datplatby,cstrava_cel,cstrava_pol,svp,zpusobplat,naklad_d,poplatek_d,platba_d"
          . ",zpusobplat_d,datplatby_d,ubytovani,cd,avizo,sleva,vzorec,duvod_typ,duvod_text");
    //      id_osoba,jmeno,_vek,id_tvori,id_rodina,role,_rody,narozeni
    $fos=   flds("umrti,prijmeni,rodne,sex,adresa,ulice,psc,obec,stat,kontakt,telefon,nomail,email"
          . ",iniciace,uvitano,clen,obcanka,rc_xxxx,cirkev,vzdelani,titul,zamest,zajmy,jazyk,dieta"
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
      if ( !count($p->cleni) ) goto p_end;
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
//           $_jmena.= "$o->jmeno ";
          $_jmena.= str_replace(' ','-',$o->jmeno)." ";
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
        $del= $delim;
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
        $id_kmen= $o->_kmen;
        $o->_kmen= "$kmen/$id_kmen";
        $cleni.= "~" . sql_date1($o->narozeni);                   // narozeniny d.m.r
        # doplnění textů z kmenové rodiny pro zobrazení rodinných adres (jako disabled)
//                                                 debug($o,"browse - o");
//                                                 debug($rodina[$id_kmen],"browse - kmen=$id_kmen");
        if ( !$o->adresa ) {
          $o->ulice= "®".$rodina[$id_kmen]->ulice;
          $o->psc=   "®".$rodina[$id_kmen]->psc;
          $o->obec=  "®".$rodina[$id_kmen]->obec;
        }
        if ( !$o->kontakt ) {
          $o->email=   "®".$rodina[$id_kmen]->emaily;
          $o->telefon= "®".$rodina[$id_kmen]->telefony;
        }
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
      # jestli jsou dokumenty
      $z->_docs= drop_find("pobyt/","^(.*)_$idp\$") ? 'D' : '';
//                                         display("drop_find(pobyt/,^(.*)_$idp\$)={$z->_docs}");
      # rodina
      foreach($frod as $fz=>$fr) { $z->$fz= $rodina[$idr]->$fr; }
      # členové
      $z->r_cleni= $cleni;
      # pobyt II
      foreach($fpob2 as $fz=>$fp) { $z->$fz= $p->$fp; }
      $z->key_spolu= 0;
      $z->ido1= $_ido1;
      $z->ido2= $_ido2;
      $z->datplatby= sql_date1($z->datplatby);                   // d.m.r
      $z->datplatby_d= sql_date1($z->datplatby_d);               // d.m.r
      # ok
      $zz[$idp]= $z;
      continue;
    p_end: // varianta pro prázdný pobyt - definování položky _empty:1
      $zz[$idp]= (object)array('key_pobyt'=>$idp,'_empty'=>1);
    }
    # 3. průchod - kompletace údajů mezi pobyty
    foreach ($pobyt as $idp=>$p) {
      # doplnění skupinek
      $s= $del= '';
      if ( ($sk= $p->skupina) && $skup[$sk]) {
        foreach($skup[$sk] as $ip) {
          $s.= "$del$ip~{$zz[$ip]->_nazev}";
          $del= $delim;
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
