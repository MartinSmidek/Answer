<?php # (c) 2008-2025 Martin Smidek <martin@smidek.eu>
//define("EZER_VERSION","3.3");  

# ------------------------------------------------------------------------------------- db2 rod_show
# BROWSE ASK
# načtení návrhu rodiny pro Účastníci2
# vrátí objekt {n:int,next:bool,back:bool,css:string,rod:položky z rodina nebo null}
function db2_rod_show($nazev,$n) {
  $ret= (object)array('n'=>0,'next'=>0,'back'=>0,'css'=>'','rod'=>null);
  $css= array('','ezer_ys','ezer_fa','ezer_db');
  $nazev= trim($nazev);
  $rod= array(null);
  // seznamy položek pro browse_fill kopírované z ucast2_browse_ask
  $fos=   ucast2_flds("umrti,prijmeni,rodne,sex,adresa,ulice,psc,obec,stat,kontakt,telefon"
        . ",nomail,email,gmail"
        . ",iniciace,firming,uvitano,clen,obcanka,rc_xxxx,cirkev,vzdelani,titul,zamest,zajmy,jazyk,dieta"
        . ",aktivita,note,_kmen,_geo,web_souhlas,prislusnost");
  $fspo=  ucast2_flds("id_spolu,_barva,s_role,dite_kat,poznamka,pecovane,pfunkce,pece_jm,pece_id"
          . ",o_umi");
  // načtení rodin
  $qr= pdo_qry("SELECT id_rodina AS key_rodina,ulice AS r_ulice,psc AS r_psc,obec AS r_obec,
      stat AS r_stat,
      telefony AS r_telefony,emaily AS r_emaily,spz AS r_spz,datsvatba,access AS r_access
    FROM rodina WHERE deleted='' AND nazev='$nazev'");
  while ( $qr && ($r= pdo_fetch_object($qr)) ) {
    $r->r_datsvatba= sql_date1($r->datsvatba);
    $rod[]= $r;
  }
//                                                         debug($rod,count($rod));
  // diskuse
  $ret->last= count($rod)-1;
  if ( isset($rod[$n]) ) {
    $idr= $rod[$n]->id_rodina;
    $ret->n= $n;
    $ret->rod= $rod[$n];
    $ret->back= $n>1 ?1:0;
    $ret->next= $n<count($rod)-1 ?1:0;
    $ret->css= $css[$ret->rod->r_access];

    # ==> .. duplicity
    $rr= ucast2_chain_rod($idr);
    $_dups= $rr->dup;
    $_keys= $rr->keys;

    // seznam členů rodiny
    $cleni= $del= '';
    $idr= $ret->rod->key_rodina;
    $qc= pdo_qry("
      SELECT id_tvori,role,o.*
      FROM osoba AS o
      JOIN tvori AS t USING(id_osoba)
      WHERE t.id_rodina=$idr
      ORDER BY role,narozeni
    ");
    while ( $qc && ($o= pdo_fetch_object($qc)) ) {
      $ido= $o->id_osoba;

      # ==> .. duplicita členů
      $rc= ucast2_chain_oso($ido);
      $dup= $rc->dup;
      $keys= $ret->keys;
      if ( $dup ) {
        $_dups.= $dup;
        $_keys.= ";$ido,{$s->_keys}";
      }
      $vek= $o->narozeni!='0000-00-00' ? roku_k($o->narozeni) : '?'; // výpočet věku
      $cleni.= "$del$ido~$keys~{$o->access}~~{$o->jmeno}~$dup~$vek~{$o->id_tvori}~$idr~{$o->role}";
      $cleni.= '~'.rodcis($o->narozeni,$o->sex);
      $cleni.= "~~" . sql_date1($o->narozeni);
      $cleni.= "~" . sql_date1($o->web_souhlas);                // souhlas d.m.r
      if ( !$o->adresa ) {
        $o->ulice= "®".$rod[$n]->r_ulice;
        $o->psc=   "®".$rod[$n]->r_psc;
        $o->obec=  "®".$rod[$n]->r_obec;
        $o->stat=  "®".$rod[$n]->r_stat;
      }
      if ( !$o->kontakt ) {
        $o->email=   "®".$rod[$n]->r_emaily;
        $o->telefon= "®".$rod[$n]->r_telefony;
      }
      # informace z osoba
      foreach($fos as $f=>$filler) {
        $cleni.= "~{$o->$f}";
      }
      # informace ze spolu
      $o->_spolu= 0;
      foreach($fspo as $f=>$filler) {
        $cleni.= "~{$o->$f}";
      }
      $cleni.= "~~~~~"; // korekce pro přidání kat_*
    }
    $ret->cleni= $cleni;
    $ret->_docs.= count_chars($_dups,3);
    $ret->keys_rodina= $_keys;
  }
//                                                         debug($ret,'db2_rod_show');
  return $ret;
}
# ------------------------------------------------------------------------------------- db2 oso_show
# vrátí objekt {n:int,next:bool,back:bool,css:string,oso:položky z osoba nebo null}
function db2_oso_show($prijmeni,$jmeno,$n) {
  $ret= (object)array('n'=>0,'next'=>0,'back'=>0,'css'=>'','oso'=>null);
  $css= array('','ezer_ys','ezer_fa','ezer_db');
  $prijmeni= trim($prijmeni);
  $jmeno= trim($jmeno);
  $oso= array(null);
  // načtení rodin
  $qr= pdo_qry("SELECT id_osoba AS key_osoba,access,rodne,sex,narozeni,umrti,
      adresa,ulice,psc,obec,kontakt,telefon,email
    FROM osoba WHERE deleted='' AND prijmeni='$prijmeni' AND jmeno='$jmeno' ");
  while ( $qr && ($r= pdo_fetch_object($qr)) ) {
    $r->r_datsvatba= sql_date1($r->datsvatba);
    $oso[]= $r;
  }
//                                                         debug($oso,count($oso));
  // diskuse
  $ret->last= count($oso)-1;
  if ( isset($oso[$n]) ) {
    $ret->n= $n;
    $ret->oso= $oso[$n];
    $ret->back= $n>1 ?1:0;
    $ret->next= $n<count($oso)-1 ?1:0;
    $ret->css= $css[$ret->oso->access];
  }
//                                                         debug($ret,'db2_oso_show');
  return $ret;
}
# ------------------------------------------------------------------------------------ db2 get_osoba
# vrátí objekt s osobními údaji dané osoby - s uvážení rodinných údajů
function db2_get_osoba($ido) {
  $oso= null;
  $qr= pdo_qry("
    SELECT prijmeni,jmeno,sex,
      CONCAT(TRIM(prijmeni),' ',TRIM(jmeno)) AS _name,
      IF(o.adresa,o.ulice,r.ulice) AS _ulice, IF(o.adresa,o.stat,r.stat) AS _stat,
      IF(o.adresa,o.psc,r.psc) AS _psc, IF(o.adresa,o.obec,r.obec) AS _obec,
      IF(o.kontakt AND o.email!='',o.email,r.emaily) AS _email,nomail,
      IF(o.kontakt,o.telefon,IFNULL(r.telefony,'')) AS _telefon,
      narozeni,TIMESTAMPDIFF(YEAR,narozeni,NOW()) AS _vek
    FROM osoba AS o
    LEFT JOIN tvori AS t USING(id_osoba)
    LEFT JOIN rodina AS r USING(id_rodina)
    WHERE id_osoba=$ido");
  if ( $qr ) $oso= pdo_fetch_object($qr);
//                                                         debug($oso,'db2_get_osoba');
  return $oso;
}
# --------------------------------------------------------------------------------------- datum oddo
function datum_oddo($x1,$x2) {
  $letos= date('Y');
  $d1= 0+substr($x1,8,2);
  $d2= 0+substr($x2,8,2);
  $m1= 0+substr($x1,5,2);
  $m2= 0+substr($x2,5,2);
  $r1= 0+substr($x1,0,4); 
  $r2= 0+substr($x2,0,4);
  if ( $x1==$x2 ) {  //zacatek a konec je stejny den
    $datum= "$d1. $m1" . ($r1!=$letos ? ". $r1" : '');
  }
  elseif ( $r1==$r2 ) {
    if ( $m1==$m2 ) { //zacatek a konec je stejny mesic
      $datum= "$d1. - $d2. $m1"  . ($r1!=$letos ? ". $r1" : '');
    }
    else { //ostatni pripady
      $datum= "$d1. $m1 - $d2. $m2"  . ($r1!=$letos ? ". $r1" : '');
    }
  }
  else { //ostatni pripady
    $datum= "$d1. $m1. $r1 - $d2. $m2. $r2";
  }
  return $datum;
}
# --------------------------------------------------------------------------- je_1_2_5
# výběr správného tvaru slova podle množství a tabulky tvarů pro 1,2-4,více jak 5
# např. je_1_2_5($dosp,"dospělý,dospělí,dospělých")
function je_1_2_5($kolik,$tvary) {
  list($tvar1,$tvar2,$tvar5)= explode(',',$tvary);
  return $kolik>4 ? "$kolik $tvar5" : (
         $kolik>1 ? "$kolik $tvar2" : (
         $kolik>0 ? "1 $tvar1"      : "0 $tvar5"));
}
# --------------------------------------------------------------------------- mb_strcasecmp
function mb_strcasecmp($str1, $str2, $encoding = null) {
    if (null === $encoding) { $encoding = mb_internal_encoding(); }
    return strcmp(mb_strtoupper($str1, $encoding), mb_strtoupper($str2, $encoding));
}
# =======================================================================================> . pomocné
# obsluha různých forem výpisů karet AKCE
# --------------------------------------------------------------------------------- tisk2 ukaz_osobu
# zobrazí odkaz na osobu v evidenci
function tisk2_ukaz_akci($ida,$barva='',$title='',$text='') {
  $style= $barva ? "style='color:$barva'" : '';
  $title= $title ? "title='$title'" : '';
  $text= $text ?: $ida;
  return "<b><a $style $title href='ezer://akce2.lst.akce_show/$ida'>$text</a></b>";
}
# --------------------------------------------------------------------------------- tisk2 ukaz_osobu
# zobrazí odkaz na osobu v evidenci
function tisk2_ukaz_osobu($ido,$barva='',$title='',$text='') {
  $style= $barva ? "style='color:$barva'" : '';
  $title= $title ? "title='$title'" : '';
  $text= $text ?: $ido;
  return "<b><a $style $title href='ezer://akce2.evi.evid_osoba/$ido'>$text</a></b>";
}
# -------------------------------------------------------------------------------- tisk2 ukaz_rodinu
# zobrazí odkaz na rodinu v evidenci
function tisk2_ukaz_rodinu($idr,$barva='',$title='',$text='') {
  $style= $barva ? "style='color:$barva'" : '';
  $title= $title ? "title='$title'" : '';
  $text= $text ?: $idr;
  return "<b><a $style $title href='ezer://akce2.evi.evid_rodina/$idr'>$text</a></b>";
}
# --------------------------------------------------------------------------------- tisk2 ukaz_pobyt
# zobrazí odkaz na řádek s pobytem
function tisk2_ukaz_pobyt($idp,$title='') {
  $title= $title ? "title='$title'" : '';
  return "<b><a $title href='ezer://akce2.ucast.ucast_pobyt/$idp'>$idp</a></b>";
}
# --------------------------------------------------------------------------------- tisk2 ukaz_pobyt
# zobrazí odkaz na řádek s pobytem s případným přepnutím akce
function tisk2_ukaz_pobyt_akce($idp,$ida,$barva='',$title='',$text='') {
  $style= $barva ? "style='color:$barva'" : '';
  $title= $title ? "title='$title'" : '';
  $text= $text ?: $idp;
  return "<b><a $style $title href='ezer://akce2.ucast.ucast_pobyt_akce/$idp/$ida'>$text</a></b>";
}
# ----------------------------------------------------------------------------- tisk2 ukaz_prihlasku
# zobrazí odkaz na řádek s pobytem s případným přepnutím akce
function tisk2_ukaz_prihlasku($idw,$ida,$idp,$barva='',$title='',$text='') {
  $style= $barva ? "style='color:$barva'" : '';
  $title= $title ? "title='$title'" : '';
  $text= $text ?: $idp;
  return "<b><a $style $title href='ezer://akce2.ucast.ucast_prihlaska/$idw/$ida/$idp'>$text</a></b>";
}
# -------------------------------------------------------------------------------- narozeni2roky_sql
# zjistí aktuální věk v rocích z data narození 
# pokud je předáno $now(jako timestamp) bere se věk k tomu
function narozeni2roky_sql($time_sql,$now_sql=0) {
  if (substr($time_sql,4,6)=='-00-00') $time_sql= substr($time_sql,0,4).'-01-01';
  $time= sql2stamp($time_sql);
  $now= $now_sql ? sql2stamp($now_sql) : time();
  $roky= floor((date("Ymd",$now) - date("Ymd", $time)) / 10000);
  return $roky;
}
// ---------------------------------------------- roku
// vrací zaokrouhlený počet roku od narození poteď
function sql2roku($narozeni) {
  $roku= '';
  if ( $narozeni && $narozeni!='0000-00-00' ) {
    list($y,$m,$d)= explode('-',$narozeni);
    $now= time();
    $nar= mktime(0,0,0,$m,$d?:1,$y?:1)+1;
//     $roku= ($now-$nar)/(60*60*24*365.2425);
    $roku= ceil(($now-$nar)/(60*60*24*365.2425));
  }
  return $roku;
}
// ---------------------------------------------- stari
// vrací stáří v letech k danému datu (vše ve formátu sql
function sql2stari($narozeni,$datum) {
  $datum= $datum ?: date('Y-m-d');
  $roku= '';
  if ( $narozeni && $narozeni!='0000-00-00' ) {
    list($dy,$dm,$dd)= explode('-',$narozeni);
    list($ky,$km,$kd)= explode('-',$datum);
    $roku= ($km<$dm || ($km==$dm && $kd<$dd)) ? $ky-$dy-1 : $ky-$dy;
  }
  return $roku;
}
# ---------------------------------------------------- sql2xls
// datum bez dne v týdnu
function sql2xls($datum) {
  // převeď sql tvar na uživatelskou podobu (default)
  $text= ''; $del= '.';
  if ( $datum && substr($datum,0,10)!='0000-00-00' ) {
    $y=substr($datum,0,4);
    $m=substr($datum,5,2);
    $d=substr($datum,8,2);
    $text.= date("j{$del}n{$del}Y",strtotime($datum));
  }
  return $text;
}
//# ----------------------------------------------------------------------------------------- git make
//# provede git par.cmd>.git.log a zobrazí jej
//# fetch pro lokální tj. vývojový server nepovolujeme
//function git_make($par) {
//  global $abs_root, $ezer_version;
//  $bean= preg_match('/bean/',$_SERVER['SERVER_NAME'])?1:0;
//  display("ezer$ezer_version, abs_root=$abs_root, bean=$bean");
//  if ($ezer_version!='3.1') { fce_error("POZOR není aktivní jádro 3.1 ale $ezer_version"); }
//  $cmd= $par->cmd;
//  $folder= $par->folder;
//  $lines= array();
//  $msg= "";
//  // nastav složku pro Git
//  if ( $folder=='ezer') 
//    chdir("./ezer$ezer_version");
//  elseif ( $folder=='skins') 
//    chdir("./skins");
//  elseif ( $folder=='.') 
//    chdir(".");
//  else
//    fce_error('chybná aktuální složka');
//  // proveď příkaz Git
//  $state= 0;
//  $branch= $folder=='ezer' ? 'ezer'.EZER_VERSION : 'master';
//  switch ($cmd) {
//    case 'log':
//    case 'status':
//      $exec= "git $cmd";
//      display($exec);
//      exec($exec,$lines,$state);
//      $msg.= "$state:$exec\n";
//      break;
//    case 'pull':
//      $exec= "git pull origin $branch";
//      display($exec);
//      exec($exec,$lines,$state);
//      $msg.= "$state:$exec\n";
//      break;
//    case 'fetch':
//      if ( $bean) 
//        $msg= "na vývojových serverech (*.bean) příkaz fetch není povolen ";
//      else {
//        $exec= "git pull origin $branch";
//        display($exec);
//        exec($exec,$lines,$state);
//        $msg.= "$state:$exec\n";
//        $exec= "git reset --hard origin/$branch";
//        display($exec);
//        exec($exec,$lines,$state);
//        $msg.= "$state:$exec\n";
//      }
//      break;
//  }
//  // případně se vrať na abs-root
//  if ( $folder=='ezer'||$folder=='skins') 
//    chdir($abs_root);
//  // zformátuj výstup
//  $msg= nl2br(htmlentities($msg));
//  $msg= "<i>Synology: musí být spuštěný Git Server (po aktualizaci se vypíná)</i><hr>$msg";
//  $msg.= $lines ? '<hr>'.implode('<br>',$lines) : '';
//  return $msg;
//}
# --------------------------------------------------------------------------------==> . sta2 ms stat
/** https://stackoverflow.com/questions/4563539/how-do-i-improve-this-linear-regression-function
 * linear regression function
 * @param $x array x-coords
 * @param $y array y-coords
 * @returns array() m=>slope, b=>intercept
 */
function linear_regression($x, $y) {
  // calculate number points
  $n = count($x);
  // ensure both arrays of points are the same size
  if ($n != count($y)) {
    trigger_error("linear_regression(): Number of elements in coordinate arrays do not match.", E_USER_ERROR);
  }
  // calculate sums
  $x_sum = array_sum($x);
  $y_sum = array_sum($y);
  $xx_sum = 0;
  $xy_sum = 0;
  for($i = 0; $i < $n; $i++) {
    $xy_sum+=($x[$i]*$y[$i]);
    $xx_sum+=($x[$i]*$x[$i]);
  }
  // calculate slope
  $m = (($n * $xy_sum) - ($x_sum * $y_sum)) / (($n * $xx_sum) - ($x_sum * $x_sum));
  // calculate intercept
  $b = ($y_sum - ($m * $x_sum)) / $n;
  // return result
  return array("m"=>$m, "b"=>$b);
}
# --------------------------------------------------------------------------------- dum spolu_adresa
# vrátí osobní resp. rodinnou adresu
function dum_spolu_adresa($ids) {
  $p= pdo_fetch_object(pdo_qry("
     SELECT prijmeni, jmeno, 
       IF(adresa,o.ulice,r.ulice) AS ulice,
       IF(adresa,o.psc,r.psc) AS psc, IF(adresa,o.obec,r.obec) AS obec
     FROM spolu AS s
     JOIN osoba AS o USING (id_osoba)
     LEFT JOIN tvori AS t USING (id_osoba)
     LEFT JOIN rodina AS r USING (id_rodina)
     WHERE id_spolu='$ids'
     ORDER BY role"));
  $adresa= "$p->jmeno $p->prijmeni<br>$p->ulice<br>$p->psc $p->obec";
  return $adresa;
}
# -------------------------------------------------------------------------------- db2 osoba_kontakt
# vrátí osobní údaje případně evidované jako rodinné jako objekt 
# {jmeno,prijmeni,ulice,psc,obec,stat,telefon,email,adresa}
function db2_osoba_kontakt($ido) {
  $p= pdo_fetch_object(pdo_qry("
     SELECT prijmeni, jmeno, 
       IF(adresa,o.stat,r.stat) AS stat, IF(adresa,o.ulice,r.ulice) AS ulice,
       IF(adresa,o.psc,r.psc) AS psc, IF(adresa,o.obec,r.obec) AS obec,
       IF(kontakt,o.telefon,r.telefony) AS telefon,
       IF(kontakt,o.email,r.emaily) AS email
     FROM osoba AS o 
     LEFT JOIN tvori AS t USING (id_osoba)
     LEFT JOIN rodina AS r USING (id_rodina)
     WHERE id_osoba='$ido'
     ORDER BY role LIMIT 1"));
  $p->adresa= "$p->jmeno $p->prijmeni<br>$p->ulice<br>$p->psc $p->obec";
  return $p;
}
# ---------------------------------------------------------------------------------------- clone row
function clone_row($tab,$id,$idname='') {
  $idname= $idname ?: "id_$tab";  
  $ro= pdo_qry("SELECT * FROM $tab WHERE $idname=$id");
  while ( $ro && $o= pdo_fetch_object($ro) ) {
    $del= '';
    foreach ($o as $i=>$v) {
      if ($i==$idname) continue;
      $v= pdo_real_escape_string($v);
      $set.= "$del$i='$v'"; $del= ' ,';
    }
    query("INSERT INTO $tab SET $set");
    $copy= pdo_insert_id();
    return $copy;
  }
}
