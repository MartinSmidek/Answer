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
# ========================================================================================= EVIDENCE
# ---------------------------------------------------------------------------------------- elim_stav
function elim_stav() {
  global $ezer_root,$dbs;
  $stav= array(
    "ezer_root"=>$ezer_root,
    "dbs"=>$dbs
  );
                                        debug($stav);
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
  $ret= (object)array('html'=>'','ok'=>1);
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
    $x= select1('COUNT(*)','tvori',"id_osoba=$id_osoba AND id_rodina!=$id_rodina");
    if ( !$x ) $duvod[]= "není členem žádné další rodiny";
    $ret->ok= count($duvod) ? 0 : 1;
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
  case 'del_mem':
    $ret->ok= query("DELETE FROM tvori WHERE id_osoba=$id_osoba AND id_rodina=$id_rodina") ? 1 : 0;
    $ret->html= "$name byl$a vyjmut$a z $nazev";
    break;
  case 'del_oso':
    $ret->ok= query("UPDATE osoba SET deleted='D' WHERE id_osoba=$id_osoba") ? 1 : 0;
    query("DELETE FROM tvori WHERE id_osoba=$id_osoba AND id_rodina=$id_rodina");
    $ret->html= "$name byl$a smazán$a";
    break;
  case 'del_rod':
    query("UPDATE osoba JOIN tvori USING (id_osoba) SET deleted='D' WHERE id_rodina=$id_rodina");
    $no= mysql_affected_rows();
    query("UPDATE rodina SET deleted='D' WHERE id_rodina=$id_rodina");
    $nr= mysql_affected_rows();
    query("DELETE FROM tvori WHERE id_rodina=$id_rodina");
    $ret->ok= $no && $nr ? 1 : 0;
    $ami= $no==1 ? "ou" : "ami";
    $ret->html= "Byla smazána rodina s $no osob$ami";
    break;
  }
  return $ret;
}
# ----------------------------------------------------------------------------------- akce_save_role
# zapíše roli - je to netypická číselníková položka definovaná jako VARCHAR(1)
function akce_save_role($id_tvori,$role) { //trace();
  return mysql_qry("UPDATE tvori SET role='$role' WHERE id_tvori=$id_tvori");
}
# ---------------------------------------------------------------------------------------- akce_evid
# hledání a) osoby a jejích rodin b) rodiny (pokud je id_osoba=0)
# $show_deleted==1 vrátí i smazané
function akce_evid($id_osoba,$id_rodina,$show_deleted=0) { trace();
  $cleni= "";
  $rodiny= array();
  $rodina= $rodina1= $id_rodina;
  $id_osoba ? "o.id_osoba=$id_osoba" : "r.id_rodina=$id_rodina";
  if ( $id_osoba ) { // ------------------------ osoby
    $clen= array();
    $deleted_o= $show_deleted ? '' : "AND o.deleted=''";
    $deleted_rto= $show_deleted ? '' : "AND rto.deleted=''";
    $qc= mysql_qry("
      SELECT rto.id_osoba,rto.jmeno,rto.prijmeni,rto.narozeni,rt.id_tvori,rt.role,r.id_rodina,nazev
      FROM osoba AS o
      JOIN tvori AS ot ON ot.id_osoba=o.id_osoba
      JOIN rodina AS r ON r.id_rodina=ot.id_rodina
      JOIN tvori AS rt ON rt.id_rodina=r.id_rodina
      JOIN osoba AS rto ON rto.id_osoba=rt.id_osoba $deleted_rto
      WHERE o.id_osoba=$id_osoba $deleted_o
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
        $cleni.= "|$ido|$c->id_tvori|$rodiny|$c->prijmeni $c->jmeno|$c->_vek|$role";
      }
    }
  }
  else { // ------------------------------------ rodiny
    $deleted_r= $show_deleted ? '' : "AND r.deleted=''";
    $deleted_rto= $show_deleted ? '' : "AND rto.deleted=''";
    $qc= mysql_qry("
      SELECT rto.id_osoba,rto.jmeno,rto.prijmeni,rto.narozeni,rt.id_tvori,rt.role,r.id_rodina,r.nazev,
        GROUP_CONCAT(CONCAT(otr.nazev,':',otr.id_rodina)) AS _rodiny
      FROM rodina AS r
      JOIN tvori AS rt ON rt.id_rodina=r.id_rodina
      JOIN osoba AS rto ON rto.id_osoba=rt.id_osoba $deleted_rto
      JOIN tvori AS ot ON ot.id_osoba=rto.id_osoba
      JOIN rodina AS otr ON otr.id_rodina=ot.id_rodina
      WHERE r.id_rodina=$id_rodina $deleted_r
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
      $cleni.= "|$c->id_osoba|$c->id_tvori|$c->_rodiny|$c->prijmeni $c->jmeno|$vek|$c->role";
//                                                         display("{$c->jmeno} {$c->narozeni} $vek");
    }
  }
  $ret= (object)array('cleni'=>substr($cleni,1),'rodina'=>$rodina);
//                                                         debug($ret);
  return $ret;
}
# ======================================================================================== ÚČASTNÍCI
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
      SELECT s.*,o.narozeni
      FROM osoba AS o
      JOIN spolu AS s USING (id_osoba)
      JOIN pobyt AS p USING (id_pobyt)
      WHERE o.deleted='' AND $cond $AND
    ");
    while ( $qu && ($u= mysql_fetch_object($qu)) ) {
      $cleni.= ",{$u->id_osoba}";
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
                                                        debug($rodina,$rodiny);
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
    $fspo=  flds("id_spolu,_barva,s_role,dite_kat,poznamka,pecovane,pfunkce,pece_jm");

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
          $s->pece_jm= $o2->prijmeni.' '.$o2->jmeno;
          $s->s_role= 5;
          $s->_barva= 5;                        // barva: 5=osobně pečující, pfunkce=95
          # pečované
          $o1= $osoba[$ido];
          $s2= $pobyt[$spolu[$idop]]->cleni[$idop];
          $s2->pece_jm= $o1->prijmeni.' '.$o1->jmeno;
          $s2->s_role= 3;
          $s2->_barva= 3;                       // barva: 3=osobně pečované, pfunkce=92
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
        $cleni.= "$del$ido~{$o->jmeno}"; $del= "~";
        $cleni.= "~" . roku_k($o->narozeni,$akce->datum_od);      // výpočet věku
        $cleni.= "~{$s->id_tvori}~{$s->id_rodina}~{$s->role}";
        # rodiny a kmenová rodina
        $rody= explode(',',$o->_rody);
        $r= "-:0"; $kmen= '';
        foreach($rody as $rod) {
          list($role,$ir)= explode(':',$rod);
          $naz= $rodina[$ir]->nazev;
          $kmen= $kmen ? ($role=='a' || $role=='b' ? $naz : $kmen) : $naz;
                                                display("$o->jmeno/$role: $kmen ($naz,$ir)");
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
